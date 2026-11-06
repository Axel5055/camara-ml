<section class="w-full space-y-6">
    <x-page-header icon="arrows-right-left" title="Transferencias" description="Envíos de cajas entre cámaras y su recepción.">
        @can('operate-inventory')
            <flux:button variant="primary" icon="plus" :href="route('transfers.create')" wire:navigate>Nueva transferencia</flux:button>
        @endcan
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="transfers-in_transit">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
                <flux:icon.truck class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">En tránsito</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($counts['in_transit']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="transfers-received">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                <flux:icon.check-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Recibidas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($counts['received']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="transfers-cancelled">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-zinc-500/10 text-zinc-600 dark:text-zinc-300">
                <flux:icon.x-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Canceladas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($counts['cancelled']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="transfers-incidents">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-red-500/10 text-red-600 dark:text-red-300">
                <flux:icon.exclamation-triangle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Con incidencias</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($counts['incidents']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por referencia o solicitante" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <flux:select wire:model.live="direction" label="Dirección">
                    <flux:select.option value="all">Todas</flux:select.option>
                    <flux:select.option value="incoming">Por recibir en mis cámaras</flux:select.option>
                    <flux:select.option value="outgoing">Enviadas desde mis cámaras</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="status" label="Estado">
                    <flux:select.option value="all">Todos los estados</flux:select.option>
                    <flux:select.option value="in_transit">En tránsito</flux:select.option>
                    <flux:select.option value="received">Recibidas</flux:select.option>
                    <flux:select.option value="cancelled">Canceladas</flux:select.option>
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

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="transfers-table">
        <flux:table :paginate="$transfers" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Referencia</flux:table.column>
                <flux:table.column>Enviada</flux:table.column>
                <flux:table.column>Origen → destino</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column align="end">Precio por caja</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transfers as $transfer)
                    <flux:table.row :key="$transfer->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $transfer->reference }}</span>
                            <div class="mt-1 text-xs text-zinc-500">{{ $transfer->exit->reference }} · {{ $transfer->exit->requester_name }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ $transfer->dispatched_at->format('d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ $transfer->dispatched_at->format('H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <span>{{ $transfer->originChamber->name }}</span>
                                <flux:icon.arrow-right class="size-4 shrink-0 text-zinc-400" />
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $transfer->destChamber->name }}</span>
                            </div>
                            <div class="text-xs text-zinc-500">{{ $transfer->originChamber->branch->name }} → {{ $transfer->destChamber->branch->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($transfer->items_count) }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap tabular-nums" data-test="transfer-unit-cost">
                            ${{ number_format((float) $transfer->min_unit_cost, 2) }}@if ((float) $transfer->min_unit_cost !== (float) $transfer->max_unit_cost) – ${{ number_format((float) $transfer->max_unit_cost, 2) }}@endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                <flux:badge size="sm" :color="$transfer->status->color()">{{ $transfer->status->label() }}</flux:badge>
                                @if ($transfer->incidents_count > 0)
                                    <flux:badge size="sm" :color="$transfer->open_incidents_count > 0 ? 'red' : 'zinc'" icon="exclamation-triangle">{{ $transfer->incidents_count }} {{ $transfer->incidents_count === 1 ? 'incidencia' : 'incidencias' }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('transfers.show', $transfer)" wire:navigate data-test="transfer-detail-{{ $transfer->id }}">Detalle</flux:button>
                                @can('receive', $transfer)
                                    <flux:button size="sm" variant="primary" icon="inbox-arrow-down" wire:click="askReceive({{ $transfer->id }})">Recibir</flux:button>
                                @endcan
                                @can('cancel', $transfer)
                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="askCancel({{ $transfer->id }})">Cancelar</flux:button>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">No hay transferencias que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @include('livewire.inventory.partials.transfer-modals')
</section>
