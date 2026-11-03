<section class="w-full space-y-6">
    <x-page-header icon="arrow-up-tray" title="Reporte de salidas" description="Mercancía entregada en el periodo.">
        <flux:button icon="arrow-down-tray" wire:click="export">Exportar CSV</flux:button>
    </x-page-header>

    @php
        $cards = [
            ['key' => 'exits', 'icon' => 'document-text', 'color' => 'indigo', 'label' => 'Salidas', 'value' => number_format($totals->exits)],
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'sky', 'label' => 'Cajas retiradas', 'value' => number_format($totals->boxes)],
            ['key' => 'kg', 'icon' => 'scale', 'color' => 'amber', 'label' => 'Kilogramos entregados', 'value' => number_format((float) $totals->kg, 3)],
            ['key' => 'cost', 'icon' => 'banknotes', 'color' => 'emerald', 'label' => 'Costo histórico (MXN)', 'value' => '$'.number_format((float) $totals->cost, 2)],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-report-{{ $card['key'] }}">
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
        @include('livewire.reports.filters', ['show' => ['dates', 'chamber', 'category', 'product', 'user'], 'chamberLabel' => 'Cámara de origen'])

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:select wire:model.live="destBranchId" label="Sucursal de destino" placeholder="Todas">
                <flux:select.option value="">Todas</flux:select.option>
                @foreach ($branches as $branch)
                    <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="requester" label="Solicitante" icon="magnifying-glass" clearable />
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-report-table">
        <flux:table :paginate="$rows" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Referencia</flux:table.column>
                <flux:table.column>Origen → destino</flux:table.column>
                <flux:table.column>Solicitante</flux:table.column>
                <flux:table.column>Producto</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column align="end">Costo histórico</flux:table.column>
                <flux:table.column>Registró</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row wire:key="exit-{{ $loop->index }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $row->reference }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $row->origin }}</div>
                            <div class="text-xs text-zinc-500">→ {{ $row->dest }}{{ $row->dest_chamber ? ' · '.$row->dest_chamber : '' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $row->requester_name }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $row->product }}</div>
                            <div class="text-xs text-zinc-500">{{ $row->category }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format($row->boxes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $row->kg, 3) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-medium tabular-nums text-zinc-900 dark:text-white">${{ number_format((float) $row->cost, 2) }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $row->user }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-10 text-center text-zinc-500">No hay salidas que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
