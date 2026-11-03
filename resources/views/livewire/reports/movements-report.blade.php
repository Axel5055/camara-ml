<section class="w-full space-y-6">
    <x-page-header icon="queue-list" title="Reporte de movimientos" description="Bitácora de los movimientos de cada caja.">
        <flux:button icon="arrow-down-tray" wire:click="export">Exportar CSV</flux:button>
    </x-page-header>

    @php
        $cards = [
            ['key' => 'movements', 'icon' => 'queue-list', 'color' => 'indigo', 'label' => 'Movimientos', 'value' => $totals->movements],
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'sky', 'label' => 'Cajas involucradas', 'value' => $totals->boxes],
            ['key' => 'users', 'icon' => 'users', 'color' => 'emerald', 'label' => 'Usuarios que actuaron', 'value' => $totals->users],
            ['key' => 'types', 'icon' => 'tag', 'color' => 'amber', 'label' => 'Tipos de movimiento', 'value' => $totals->types],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="movements-{{ $card['key'] }}">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-{{ $card['color'] }}-500/10 text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-300">
                    <flux:icon :name="$card['icon']" class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $card['label'] }}</div>
                    <div class="truncate text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($card['value']) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        @include('livewire.reports.filters', ['show' => ['dates', 'chamber', 'category', 'product', 'user']])

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:select wire:model.live="type" label="Tipo de movimiento" placeholder="Todos">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($types as $typeOption)
                    <flux:select.option :value="$typeOption->value">{{ $typeOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="movements-table">
        <flux:table :paginate="$rows" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Movimiento</flux:table.column>
                <flux:table.column>Caja</flux:table.column>
                <flux:table.column>Origen → destino</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column>Usuario</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    @php($movementType = \App\Enums\MovementType::from($row->type))
                    <flux:table.row wire:key="mov-{{ $row->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$movementType->color()">{{ $movementType->label() }}</flux:badge>
                            @if ($row->notes)
                                <div class="mt-1 max-w-xs whitespace-normal text-xs text-zinc-500">{{ $row->notes }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $row->barcode }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <span class="text-zinc-900 dark:text-white">{{ $row->from_chamber ?? '—' }}</span>
                            <span class="text-zinc-400">→</span>
                            <span class="text-zinc-900 dark:text-white">{{ $row->to_chamber ?? ($row->to_branch ?? '—') }}</span>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">
                            <span class="text-zinc-500">{{ $row->weight_before_kg }}</span> → <span class="font-medium text-zinc-900 dark:text-white">{{ $row->weight_after_kg }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $row->user }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">No hay movimientos que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
