{{--
    Pie chart. With :shared="true" the highlight state (hover, picked, hl) comes from an Alpine parent, so other
    elements (e.g. table rows) can highlight a slice by label and vice versa.
--}}
@props([
    'slices' => [],
    'unit' => 'cajas',
    'shared' => false,
])

@php
    $palette = ['#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e', '#f97316', '#6366f1', '#84cc16', '#ec4899', '#06b6d4', '#d946ef', '#eab308'];
    $sorted = collect($slices)->filter(fn (array $slice): bool => $slice['value'] > 0)->sortByDesc('value')->values();
    $total = (float) $sorted->sum('value');
    $radius = 15.9155;
    $offset = 0.0;

    $items = $sorted->map(function (array $slice, int $index) use ($palette, $total): array {
        $color = $palette[$index] ?? 'hsl('.(int) round(($index * 137.508) % 360).' 65% 55%)';

        return [
            'label' => $slice['label'],
            'value' => (int) $slice['value'],
            'share' => round($slice['value'] / $total * 100, 1),
            'color' => $color,
        ];
    })->all();
@endphp

<div {{ $attributes }}>
    @if ($total <= 0)
        <div class="flex h-full min-h-48 items-center justify-center text-sm text-zinc-500">Sin datos para graficar.</div>
    @else
        <div
            x-data="{
                @unless ($shared)
                    hover: null,
                    picked: null,
                    get hl() { return this.hover ?? this.picked; },
                @endunless
                items: @js($items),
                unit: @js($unit),
                total: @js((int) $total),
                get current() { return this.items.find((item) => item.label === this.hl) ?? null; },
            }"
            class="flex flex-col items-center gap-5"
        >
            <div class="relative size-48 shrink-0">
                <svg viewBox="0 0 42 42" class="size-full -rotate-90" role="img" aria-label="Distribución en gráfica de pastel">
                    <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke-width="7" class="stroke-zinc-100 dark:stroke-zinc-700" />
                    @foreach ($items as $index => $item)
                        <circle
                            cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="{{ $item['color'] }}"
                            stroke-dasharray="{{ round($item['value'] / $total * 100, 3) }} {{ round(100 - $item['value'] / $total * 100, 3) }}"
                            stroke-dashoffset="{{ round(-$offset, 3) }}"
                            x-on:pointerenter="$event.pointerType === 'mouse' && (hover = items[{{ $index }}].label)"
                            x-on:pointerleave="hover = null"
                            x-on:click="picked = picked === items[{{ $index }}].label ? null : items[{{ $index }}].label"
                            :stroke-width="hl === items[{{ $index }}].label ? 9 : 7"
                            :opacity="current === null || hl === items[{{ $index }}].label ? 1 : 0.3"
                            class="cursor-pointer touch-manipulation transition-all duration-150"
                        >
                            <title>{{ $item['label'] }}: {{ number_format($item['value']) }} {{ $unit }} ({{ number_format($item['share'], 1) }}%)</title>
                        </circle>
                        @php($offset += $item['value'] / $total * 100)
                    @endforeach
                </svg>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-11 text-center leading-tight">
                    <span class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white" x-text="(current ? current.value : total).toLocaleString('es-MX')"></span>
                    <span class="line-clamp-2 text-xs text-zinc-500" x-text="current ? current.label + ' · ' + current.share.toFixed(1) + '%' : unit"></span>
                </div>
            </div>

            <ul class="max-h-96 w-full min-w-0 space-y-0.5 overflow-y-auto pe-1 text-sm" data-test="pie-legend">
                @foreach ($items as $index => $item)
                    <li
                        x-on:pointerenter="$event.pointerType === 'mouse' && (hover = items[{{ $index }}].label)"
                        x-on:pointerleave="hover = null"
                        x-on:click="picked = picked === items[{{ $index }}].label ? null : items[{{ $index }}].label"
                        :class="hl === items[{{ $index }}].label ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
                        class="flex cursor-pointer touch-manipulation items-center gap-2 rounded-md px-2 py-2 transition sm:py-1"
                    >
                        <span class="inline-block size-3 shrink-0 rounded-sm" style="background: {{ $item['color'] }}"></span>
                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                        <span class="shrink-0 tabular-nums text-zinc-500">{{ number_format($item['value']) }} · {{ number_format($item['share'], 1) }}%</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
