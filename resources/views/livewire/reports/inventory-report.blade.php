<section class="w-full space-y-6">
    <x-page-header icon="chart-bar" title="Reporte de inventario" description="Existencias actuales por cámara y producto.">
        <flux:button icon="arrow-down-tray" wire:click="export">Exportar CSV</flux:button>
    </x-page-header>

    @php
        $cards = [
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'indigo', 'label' => 'Cajas', 'value' => number_format($totals->boxes)],
            ['key' => 'kg', 'icon' => 'scale', 'color' => 'sky', 'label' => 'Peso total (kg)', 'value' => number_format((float) $totals->kg, 3)],
            ['key' => 'partial', 'icon' => 'scissors', 'color' => 'amber', 'label' => 'Cajas parciales', 'value' => number_format($totals->partial_boxes)],
            ['key' => 'cost', 'icon' => 'banknotes', 'color' => 'emerald', 'label' => 'Costo histórico restante', 'value' => '$'.number_format((float) $totals->cost, 2)],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="inventory-{{ $card['key'] }}">
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
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="groupBy" label="Agrupar por">
                <flux:select.option value="chamber">Cámara</flux:select.option>
                <flux:select.option value="branch">Sucursal (consolidado)</flux:select.option>
                <flux:select.option value="category">Categoría</flux:select.option>
                <flux:select.option value="product">Producto</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="status" label="Estado de las cajas">
                <flux:select.option value="available">Disponibles</flux:select.option>
                <flux:select.option value="dispatched">Entregadas (retiradas)</flux:select.option>
                <flux:select.option value="in_transit">En tránsito</flux:select.option>
                <flux:select.option value="depleted">Agotadas</flux:select.option>
                <flux:select.option value="written_off">Baja</flux:select.option>
                <flux:select.option value="all">Todas</flux:select.option>
            </flux:select>
        </div>
        <div class="mt-4">
            @include('livewire.reports.filters', ['show' => ['dates', 'chamber', 'category', 'product']])
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="inventory-table">
        <flux:table container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Grupo</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column align="end">Parciales</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column align="end">Costo histórico restante</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row wire:key="inv-{{ $loop->index }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $row->label }}</div>
                            @if ($row->extra)
                                <div class="text-xs text-zinc-500">{{ $row->extra }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format($row->boxes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">
                            @if ($row->partial_boxes > 0)
                                <flux:badge size="sm" color="amber">{{ number_format($row->partial_boxes) }}</flux:badge>
                            @else
                                <span class="text-zinc-400">0</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $row->kg, 3) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $row->cost, 2) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">No hay existencias que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
                @if ($rows->isNotEmpty())
                    <flux:table.row class="bg-zinc-50 dark:bg-zinc-700/40">
                        <flux:table.cell class="font-semibold text-zinc-900 dark:text-white">Total</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format($totals->boxes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format($totals->partial_boxes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format((float) $totals->kg, 3) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums">${{ number_format((float) $totals->cost, 2) }}</flux:table.cell>
                    </flux:table.row>
                @endif
            </flux:table.rows>
        </flux:table>
    </div>
</section>
