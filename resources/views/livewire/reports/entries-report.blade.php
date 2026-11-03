<section class="w-full space-y-6">
    <x-page-header icon="arrow-down-tray" title="Reporte de entradas" description="Mercancía recibida en el periodo.">
        <flux:button icon="arrow-down-tray" wire:click="export">Exportar CSV</flux:button>
    </x-page-header>

    @php
        $cards = [
            ['key' => 'entries', 'icon' => 'document-text', 'color' => 'indigo', 'label' => 'Entradas', 'value' => number_format($totals->entries)],
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'sky', 'label' => 'Cajas recibidas', 'value' => number_format($totals->boxes)],
            ['key' => 'kg', 'icon' => 'scale', 'color' => 'amber', 'label' => 'Kilogramos recibidos', 'value' => number_format((float) $totals->kg, 3)],
            ['key' => 'cost', 'icon' => 'banknotes', 'color' => 'emerald', 'label' => 'Importe (MXN)', 'value' => '$'.number_format((float) $totals->cost, 2)],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entries-report-{{ $card['key'] }}">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-{{ $card['color'] }}-500/10 text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-300">
                    <flux:icon :name="$card['icon']" class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $card['label'] }}</div>
                    <div class="truncate text-2xl font-semibold text-zinc-900 dark:text-white">{{ $card['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        @include('livewire.reports.filters', ['show' => ['dates', 'chamber', 'category', 'product', 'user'], 'chamberLabel' => 'Cámara de destino'])

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entries-report-table">
        <flux:table :paginate="$rows" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Referencia</flux:table.column>
                <flux:table.column>Cámara</flux:table.column>
                <flux:table.column>Producto</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column align="end">Costo por caja</flux:table.column>
                <flux:table.column align="end">Importe</flux:table.column>
                <flux:table.column>Registró</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row wire:key="ent-{{ $loop->index }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $row->reference }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $row->chamber }}</div>
                            <div class="text-xs text-zinc-500">{{ $row->branch }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $row->product }}</div>
                            <div class="text-xs text-zinc-500">{{ $row->category }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format($row->boxes_count) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $row->total_weight_kg }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $row->unit_cost_mxn, 2) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-medium tabular-nums text-zinc-900 dark:text-white">${{ number_format((float) $row->total_cost_mxn, 2) }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $row->user }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-10 text-center text-zinc-500">No hay entradas que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
