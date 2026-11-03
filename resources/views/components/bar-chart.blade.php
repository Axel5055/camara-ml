@props([
    'labels' => [],
    'series' => [],
    'height' => 'h-56',
    'unit' => '',
])

@php
    $max = max(1, ...collect($series)->flatMap(fn (array $serie): array => $serie['values'])->all());
    $step = count($labels) > 16 ? 3 : (count($labels) > 10 ? 2 : 1);
@endphp

<div {{ $attributes }}>
    <div class="mb-3 flex flex-wrap items-center gap-4 text-sm">
        @foreach ($series as $serie)
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block size-3 rounded-sm" style="background: {{ $serie['color'] }}"></span>
                {{ $serie['name'] }}
                <span class="text-zinc-500">({{ rtrim(rtrim(number_format(array_sum($serie['values']), 3, '.', ','), '0'), '.') }}{{ $unit }})</span>
            </span>
        @endforeach
    </div>

    <div class="flex gap-2">
        <div class="flex {{ $height }} flex-col justify-between text-[10px] tabular-nums text-zinc-500">
            <span>{{ rtrim(rtrim(number_format($max, 3, '.', ','), '0'), '.') }}</span>
            <span>0</span>
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex {{ $height }} items-end gap-1 border-b border-s border-zinc-200 ps-1 dark:border-zinc-700">
                @foreach ($labels as $i => $label)
                    <div class="flex h-full min-w-0 flex-1 items-end justify-center gap-0.5">
                        @foreach ($series as $serie)
                            @php($value = $serie['values'][$i] ?? 0)
                            <div
                                class="w-full max-w-5 rounded-t-sm transition-all hover:opacity-80"
                                style="height: {{ $value > 0 ? max(2, $value / $max * 100) : 0 }}%; background: {{ $serie['color'] }}"
                                title="{{ $label }} · {{ $serie['name'] }}: {{ rtrim(rtrim(number_format($value, 3, '.', ','), '0'), '.') }}{{ $unit }}"
                            ></div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex gap-1 ps-1">
                @foreach ($labels as $i => $label)
                    <div class="min-w-0 flex-1 text-center text-[10px] text-zinc-500">{{ $i % $step === 0 ? $label : '' }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>
