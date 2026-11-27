{{--
    Line chart of the morning and afternoon temperatures, oldest day first. Lines are SVG stretched to the box;
    markers and labels are HTML so they keep their size on any screen. The log below is its table view.
--}}
@props([
    'points' => [],
])

@php
    use App\Enums\TemperatureLevel;

    $normalFrom = (float) config('temperature.levels.normal_from');
    $normalTo = (float) config('temperature.levels.normal_to');
    $warningTo = (float) config('temperature.levels.warning_to');

    $series = [
        'morning' => ['label' => 'Mañana', 'line' => 'stroke-[#2a78d6] dark:stroke-[#3987e5]', 'dot' => 'bg-[#2a78d6] dark:bg-[#3987e5]'],
        'afternoon' => ['label' => 'Tarde', 'line' => 'stroke-[#b87700] dark:stroke-[#c98500]', 'dot' => 'bg-[#b87700] dark:bg-[#c98500]'],
    ];

    $points = array_values($points);
    $count = count($points);

    $values = collect($points)
        ->flatMap(fn (array $point): array => [$point['morning']['value'], $point['afternoon']['value']])
        ->merge([$normalFrom, $warningTo])
        ->filter(fn ($value): bool => $value !== null)
        ->map(fn ($value): float => (float) $value);

    $hasData = collect($points)->contains(fn (array $point): bool => $point['morning']['value'] !== null || $point['afternoon']['value'] !== null);

    // A round scale with up to five steps around the values and the level limits.
    $low = (float) ($values->min() ?? 0);
    $high = (float) ($values->max() ?? 0);
    $step = 1;

    foreach ([1, 2, 5, 10, 20, 25] as $candidate) {
        $step = $candidate;

        if (($high - $low + 2) / $candidate <= 5) {
            break;
        }
    }

    $bottom = floor(($low - 1) / $step) * $step;
    $top = ceil(($high + 1) / $step) * $step;
    $ticks = range($top, $bottom, -$step);

    $x = fn (int $index): float => round(($index + 0.5) / max($count, 1) * 100, 3);
    $y = fn (float $value): float => round(($top - $value) / ($top - $bottom) * 100, 3);

    // Crowded periods keep only the markers that matter: out of range and days without neighbours.
    $dense = $count > 31;

    // What the floating box says about each day, one block per shift.
    $readout = array_map(fn (array $point): array => [
        'date' => $point['date'],
        'shifts' => collect($series)->map(function (array $style, string $key) use ($point): array {
            $slot = $point[$key];
            $level = TemperatureLevel::tryFrom((string) $slot['level']);

            return [
                'label' => $style['label'],
                'dot' => $style['dot'],
                'value' => match (true) {
                    $slot['value'] !== null => $slot['value'].' °C',
                    $slot['status'] === 'recorded' => 'No se pudo medir',
                    $slot['status'] === 'missing' => 'Sin registro',
                    $slot['status'] === 'pending' => 'Pendiente',
                    default => 'Sin dato',
                },
                'class' => $slot['value'] !== null ? $level?->textClass() : 'text-zinc-500 dark:text-zinc-400',
                'alert' => $level?->isAlert() ? $level->label() : null,
                'time' => $slot['recorded_at'] === null ? null : 'Registrada: '.$slot['recorded_at'],
                'corrected' => $slot['corrected'],
            ];
        })->values()->all(),
    ], $points);

    $describe = fn (array $day): string => $day['date'].' — '.collect($day['shifts'])
        ->map(fn (array $shift): string => $shift['label'].': '.$shift['value'].($shift['alert'] ? ' ('.$shift['alert'].')' : '').($shift['time'] ? '. '.$shift['time'] : ''))
        ->implode(' · ');
@endphp

<div {{ $attributes }}>
    @if (! $hasData)
        <div class="flex min-h-48 items-center justify-center text-sm text-zinc-500">Sin datos para graficar.</div>
    @else
        <div
            x-data="{
                days: @js($readout),
                active: null,
                left: 0,
                // Puts the box beside the day, on whichever side fits, without leaving the chart.
                show(index) {
                    this.active = index;
                    this.$nextTick(() => {
                        const width = this.$refs.plot.clientWidth;
                        const box = this.$refs.box.offsetWidth;
                        const point = ((index + 0.5) / this.days.length) * width;
                        const beside = point + 12 + box <= width ? point + 12 : point - 12 - box;

                        this.left = Math.max(0, Math.min(beside, width - box));
                    });
                },
            }"
            x-on:mouseleave="active = null"
            x-on:click.outside="active = null"
            class="space-y-3"
        >
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                @foreach ($series as $style)
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full {{ $style['dot'] }}"></span>{{ $style['label'] }}</span>
                @endforeach
                @foreach (TemperatureLevel::alerts() as $level)
                    <span class="flex items-center gap-1.5"><span class="size-2.5 {{ $level->markerClass() }}"></span>{{ $level->label() }}</span>
                @endforeach
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-4 border-y border-dashed border-emerald-500 bg-emerald-500/20"></span>Normal: {{ $normalFrom }} a {{ $normalTo }} °C</span>
            </div>

            <div class="text-xs text-zinc-500">Toca o pasa el cursor sobre un día para ver sus lecturas.</div>

            <div class="flex gap-2">
                <div class="relative h-56 w-8 shrink-0 text-right text-[11px] tabular-nums text-zinc-500 sm:h-64">
                    @foreach ($ticks as $tick)
                        <span class="absolute right-0 -translate-y-1/2" style="top: {{ $y($tick) }}%">{{ $tick + 0 }}°</span>
                    @endforeach
                </div>

                <div x-ref="plot" class="relative h-56 min-w-0 flex-1 sm:h-64" role="img" aria-label="Gráfica de temperaturas por día, mañana y tarde">
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" aria-hidden="true">
                        @foreach ($ticks as $tick)
                            <line x1="0" x2="100" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" class="stroke-zinc-200 dark:stroke-zinc-700" vector-effect="non-scaling-stroke" />
                        @endforeach
                        {{-- Level zones, warmest on top: red, yellow, normal and too cold. --}}
                        @foreach ([[$top, $warningTo, 'fill-red-500/10'], [$warningTo, $normalTo, 'fill-yellow-400/10'], [$normalTo, $normalFrom, 'fill-emerald-500/10'], [$normalFrom, $bottom, 'fill-sky-400/10']] as [$from, $to, $fill])
                            @if ($from > $to)
                                <rect x="0" width="100" y="{{ $y((float) $from) }}" height="{{ round($y((float) $to) - $y((float) $from), 3) }}" class="{{ $fill }}" />
                            @endif
                        @endforeach
                        @foreach ([[$normalFrom, 'stroke-emerald-500'], [$normalTo, 'stroke-emerald-500'], [$warningTo, 'stroke-red-500']] as [$limit, $stroke])
                            <line x1="0" x2="100" y1="{{ $y($limit) }}" y2="{{ $y($limit) }}" class="{{ $stroke }}" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
                        @endforeach
                        @foreach ($series as $key => $style)
                            {{-- A day without a value breaks the line. --}}
                            @foreach ($points as $index => $point)
                                @if ($index > 0 && $point[$key]['value'] !== null && $points[$index - 1][$key]['value'] !== null)
                                    <line x1="{{ $x($index - 1) }}" y1="{{ $y((float) $points[$index - 1][$key]['value']) }}" x2="{{ $x($index) }}" y2="{{ $y((float) $point[$key]['value']) }}" class="{{ $style['line'] }}" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                                @endif
                            @endforeach
                        @endforeach
                    </svg>

                    <div x-show="active !== null" x-cloak class="absolute inset-y-0 w-px bg-zinc-400" x-bind:style="`left: ${(active + 0.5) / {{ $count }} * 100}%`"></div>

                    @foreach ($series as $key => $style)
                        @foreach ($points as $index => $point)
                            @php
                                $value = $point[$key]['value'];
                                $isolated = ($points[$index - 1][$key]['value'] ?? null) === null && ($points[$index + 1][$key]['value'] ?? null) === null;
                                $level = TemperatureLevel::tryFrom((string) $point[$key]['level']);
                                $alert = (bool) $level?->isAlert();
                            @endphp
                            @if ($value !== null && ($alert || ! $dense || $isolated))
                                <span
                                    class="absolute -translate-x-1/2 -translate-y-1/2 ring-2 ring-white dark:ring-zinc-800 {{ $alert ? 'size-3 '.$level->markerClass() : 'size-2 rounded-full '.$style['dot'] }}"
                                    style="left: {{ $x($index) }}%; top: {{ $y((float) $value) }}%"
                                    data-test="temperature-point"
                                ></span>
                            @endif
                        @endforeach
                    @endforeach

                    <div class="absolute inset-0 flex">
                        @foreach ($points as $index => $point)
                            <button type="button" class="h-full min-w-0 flex-1 cursor-crosshair focus-visible:bg-zinc-500/10" aria-label="{{ $describe($readout[$index]) }}"
                                x-on:mouseenter="show({{ $index }})" x-on:focus="show({{ $index }})" x-on:click="show({{ $index }})"></button>
                        @endforeach
                    </div>

                    {{-- Floating box; it lets the pointer through so it never hides the days underneath. --}}
                    {{-- Invisible instead of x-show, so its width can be measured before it appears. --}}
                    <div x-ref="box"
                        class="pointer-events-none invisible absolute left-0 top-1 z-10 w-52 max-w-full space-y-2 rounded-xl border border-zinc-200 bg-white p-3 text-left shadow-xl dark:border-zinc-600 dark:bg-zinc-900"
                        x-bind:class="{ 'invisible': active === null }"
                        x-bind:style="`left: ${left}px`" aria-hidden="true" data-test="temperature-tooltip">
                        <div class="text-sm font-semibold text-zinc-900 dark:text-white" x-text="days[active]?.date"></div>
                        <template x-for="shift in days[active]?.shifts ?? []" :key="shift.label">
                            <div class="border-t border-zinc-200 pt-2 dark:border-zinc-700">
                                <div class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-zinc-500">
                                    <span class="size-2 rounded-full" x-bind:class="shift.dot"></span><span x-text="shift.label"></span>
                                </div>
                                <div class="text-base font-semibold tabular-nums" x-bind:class="shift.class">
                                    <span x-text="shift.value"></span>
                                    <span class="text-xs font-medium" x-show="shift.alert" x-text="'· ' + shift.alert"></span>
                                </div>
                                <div class="text-[11px] leading-snug tabular-nums text-zinc-600 dark:text-zinc-300" x-show="shift.time" x-text="shift.time"></div>
                                <div class="text-[11px] text-zinc-500" x-show="shift.corrected">Valor corregido</div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex justify-between pl-10 text-[11px] tabular-nums text-zinc-500">
                <span>{{ $points[0]['label'] }}</span>
                @if ($count > 2)
                    <span>{{ $points[intdiv($count - 1, 2)]['label'] }}</span>
                @endif
                @if ($count > 1)
                    <span>{{ $points[$count - 1]['label'] }}</span>
                @endif
            </div>
        </div>
    @endif
</div>
