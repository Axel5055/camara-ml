<section class="w-full space-y-6">
    <x-page-header icon="arrow-up-tray" title="Salidas" description="Registro de la mercancía que sale de las cámaras.">
        @can('operate-inventory')
            <flux:button variant="primary" icon="plus" :href="route('exits.create')" wire:navigate>Nueva salida</flux:button>
        @endcan
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 {{ $showCosts ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-count">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.document-text class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Salidas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($summary->exits) }}</div>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-boxes">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.archive-box class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cajas retiradas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($summary->boxes) }}</div>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-weight">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
                <flux:icon.scale class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Peso entregado</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format((float) $summary->kg, 3) }} <span class="text-sm font-normal text-zinc-500">kg</span></div>
            </div>
        </div>

        @if ($showCosts)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-cost">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                    <flux:icon.banknotes class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Costo histórico</div>
                    <div class="text-2xl font-semibold text-zinc-900 dark:text-white">${{ number_format((float) $summary->cost, 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
                </div>
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por referencia o solicitante" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <flux:select wire:model.live="chamberId" label="Cámara de origen">
                    <flux:select.option value="">Todas las cámaras</flux:select.option>
                    @foreach ($chambers as $chamber)
                        <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="type" label="Tipo de salida">
                    <flux:select.option value="all">Todas</flux:select.option>
                    <flux:select.option value="delivery">Entrega a sucursal</flux:select.option>
                    <flux:select.option value="transfer">Transferencia a cámara</flux:select.option>
                </flux:select>
                <flux:input wire:model.live="from" type="date" label="Desde" />
                <flux:input wire:model.live="to" type="date" label="Hasta" />
            </div>
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-table">
        <flux:table :paginate="$exits" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Referencia</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Origen → destino</flux:table.column>
                <flux:table.column>Solicitante</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column align="end">Precio por caja</flux:table.column>
                @if ($showCosts)
                    <flux:table.column align="end">Costo histórico</flux:table.column>
                @endif
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($exits as $exit)
                    <flux:table.row :key="$exit->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $exit->reference }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ $exit->occurred_at->format('d/m/Y') }} <span class="text-xs text-zinc-500">{{ $exit->occurred_at->format('H:i') }}</span></div>
                            <div class="text-xs text-zinc-500">{{ $exit->user->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span>{{ $exit->originChamber->name }}</span>
                                <flux:icon.arrow-right class="size-4 shrink-0 text-zinc-400" />
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $exit->destChamber?->name ?? $exit->destBranch->name }}</span>
                            </div>
                            <div class="text-xs text-zinc-500">{{ $exit->destChamber ? 'Transferencia · '.$exit->destBranch->name : 'Entrega a sucursal' }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $exit->requester_name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($exit->total_boxes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $exit->total_weight_kg }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap tabular-nums" data-test="exit-unit-cost">
                            ${{ number_format((float) $exit->min_unit_cost, 2) }}@if ((float) $exit->min_unit_cost !== (float) $exit->max_unit_cost) – ${{ number_format((float) $exit->max_unit_cost, 2) }}@endif
                        </flux:table.cell>
                        @if ($showCosts)
                            <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">${{ number_format((float) $exit->total_cost_mxn, 2) }}</flux:table.cell>
                        @endif
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                <flux:badge size="sm" :color="$exit->status->value === 'confirmed' ? 'green' : 'red'">{{ $exit->status->label() }}</flux:badge>
                                @if ($exit->transfer)
                                    <flux:badge size="sm" :color="$exit->transfer->status->color()">{{ $exit->transfer->status->label() }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" :href="route('exits.show', $exit)" wire:navigate>Detalle</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $showCosts ? 10 : 9 }}" class="py-10 text-center text-zinc-500">No hay salidas que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
