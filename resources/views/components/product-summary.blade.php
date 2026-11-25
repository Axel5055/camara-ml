@props([
    'rows',
    'showCosts' => false,
    'showPerBox' => false,
    'testId' => 'product-summary',
    'totalTestId' => 'product-summary-total',
    'amountTestId' => null,
])

@php
    $rows = collect($rows);
    $totalBoxes = (int) $rows->sum('boxes');
    $totalKg = (float) $rows->sum('kg');
    $totalAmount = (float) $rows->sum('amount');
@endphp

<div class="overflow-hidden rounded-xl border border-blue-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="{{ $testId }}">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[34rem] border-collapse text-sm">
            <thead>
                <tr class="bg-blue-900 text-white">
                    <th class="px-4 py-3 text-start font-semibold">Producto</th>
                    <th class="px-3 py-3 text-center font-semibold">Cajas</th>
                    @if ($showPerBox)
                        <th class="px-3 py-3 text-center font-semibold">Peso por caja</th>
                    @endif
                    <th class="px-3 py-3 text-center font-semibold">Peso</th>
                    <th class="px-3 py-3 text-center font-semibold">Costo por kg</th>
                    @if ($showCosts)
                        <th class="px-4 py-3 text-end font-semibold">Importe (MXN)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="bg-white even:bg-amber-100/70 dark:bg-zinc-800 dark:even:bg-zinc-700/40">
                        <td class="px-4 py-2.5">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $row['product'] }}</div>
                            @if (! empty($row['sub']))
                                <div class="text-xs text-zinc-500">{{ $row['sub'] }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center"><flux:badge size="sm" color="sky" class="tabular-nums">{{ number_format((int) $row['boxes']) }}</flux:badge></td>
                        @if ($showPerBox)
                            <td class="whitespace-nowrap px-3 py-2.5 text-center tabular-nums">{{ $row['per_box'] ?? '—' }}</td>
                        @endif
                        <td class="whitespace-nowrap px-3 py-2.5 text-center"><flux:badge size="sm" color="amber" class="tabular-nums">{{ number_format((float) $row['kg'], 3) }} kg</flux:badge></td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center tabular-nums">{{ $row['price'] }}</td>
                        @if ($showCosts)
                            <td class="px-4 py-2.5 text-end" @if ($amountTestId) data-test="{{ $amountTestId }}" @endif><flux:badge size="sm" color="emerald" class="tabular-nums">${{ number_format((float) $row['amount'], 2) }}</flux:badge></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-blue-200 bg-blue-50 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-900/40" data-test="{{ $totalTestId }}">
        <div class="flex items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-blue-900 text-white"><flux:icon.cube class="size-5" /></span>
            <span class="font-semibold text-zinc-900 dark:text-white">Total de productos diferentes:</span>
            <flux:badge color="sky" class="tabular-nums">{{ number_format($rows->count()) }}</flux:badge>
        </div>
        <div class="flex items-center gap-2 lg:border-s lg:border-zinc-300 lg:ps-6 dark:lg:border-zinc-600">
            <span class="text-zinc-700 dark:text-zinc-200">Total de cajas:</span>
            <flux:badge color="sky" class="tabular-nums">{{ number_format($totalBoxes) }}</flux:badge>
        </div>
        <div class="flex items-center gap-2 lg:border-s lg:border-zinc-300 lg:ps-6 dark:lg:border-zinc-600">
            <span class="text-zinc-700 dark:text-zinc-200">Peso total:</span>
            <flux:badge color="amber" class="tabular-nums">{{ number_format($totalKg, 3) }} kg</flux:badge>
        </div>
        @if ($showCosts)
            <div class="flex items-center gap-2 lg:border-s lg:border-zinc-300 lg:ps-6 dark:lg:border-zinc-600">
                <span class="text-zinc-700 dark:text-zinc-200">Importe total:</span>
                <flux:badge color="emerald" class="tabular-nums">${{ number_format($totalAmount, 2) }}</flux:badge>
            </div>
        @endif
    </div>
</div>
