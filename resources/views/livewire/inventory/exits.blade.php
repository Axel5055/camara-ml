<section class="w-full space-y-6">
    <x-page-header icon="arrow-up-tray" :title="$isRequested ? 'Salidas por solicitud' : 'Salidas'" :description="$isRequested ? 'Mercancía que salió al autorizarse una solicitud de una sucursal.' : 'Registro de la mercancía que sale de las cámaras, registrada directamente por el almacenista.'">
        @if ($isRequested)
            @can('print-exit-summary')
                <livewire:inventory.exit-request-summary-print />
            @endcan
        @else
            @can('operate-inventory')
                <flux:button variant="primary" icon="plus" :href="route('exits.create')" wire:navigate>Nueva salida</flux:button>
            @endcan
        @endif
    </x-page-header>

    @can('viewAny', \App\Models\ExitRequest::class)
    <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-source-tabs">
        <flux:button size="sm" :variant="$isRequested ? 'ghost' : 'primary'" icon="hand-raised" :href="route('exits.index')" wire:navigate data-test="exits-tab-direct">Directas</flux:button>
        <flux:button size="sm" :variant="$isRequested ? 'primary' : 'ghost'" icon="clipboard-document-check" :href="route('exits.requested')" wire:navigate data-test="exits-tab-requested">Por solicitud</flux:button>
    </div>
    @endcan

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
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Importe</div>
                    <div class="text-2xl font-semibold text-zinc-900 dark:text-white">${{ number_format((float) $summary->cost, 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
                </div>
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por referencia o solicitante" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <flux:select wire:model.live="chamberId" label="Cámara de origen">
                    <flux:select.option value="">Todas las cámaras</flux:select.option>
                    @foreach ($chambers as $chamber)
                        <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                    @endforeach
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

    <div class="overflow-hidden rounded-xl border border-accent/30 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exits-table">
        <flux:table :paginate="$exits" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Referencia</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Origen → destino</flux:table.column>
                <flux:table.column>Solicitante</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column align="end">Costo por kg</flux:table.column>
                @if ($showCosts)
                    <flux:table.column align="end">Importe (MXN)</flux:table.column>
                @endif
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($exits as $exit)
                    <flux:table.row :key="$exit->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $exit->reference }}</span>
                            @if ($exit->exitRequest?->awaitsValidation())
                                <div class="mt-1" data-test="exit-origin">
                                    <flux:badge size="sm" color="sky" icon="clipboard-document-check" class="whitespace-nowrap">Por solicitud · {{ $exit->exitRequest->reference() }}</flux:badge>
                                </div>
                            @endif
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
                            <div class="text-xs text-zinc-500">{{ $exit->destChamber ? 'Transferencia · '.$exit->destBranch->name : 'Entrega a sucursal'.($exit->deliveryChamber ? ' · Cámara '.$exit->deliveryChamber->name : '') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $exit->requester_name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white"><flux:badge size="sm" color="sky" class="tabular-nums">{{ number_format($exit->total_boxes) }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums"><flux:badge size="sm" color="amber" class="tabular-nums">{{ $exit->total_weight_kg }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap tabular-nums" data-test="exit-unit-cost">
                            ${{ number_format((float) $exit->min_price_kg, 2) }}@if ((float) $exit->min_price_kg !== (float) $exit->max_price_kg) – ${{ number_format((float) $exit->max_price_kg, 2) }}@endif
                        </flux:table.cell>
                        @if ($showCosts)
                            <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white"><flux:badge size="sm" color="emerald" class="tabular-nums">${{ number_format((float) $exit->total_cost_mxn, 2) }}</flux:badge></flux:table.cell>
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
                            <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                @if ($exit->exitRequest?->awaitsValidation())
                                    @can('validate', $exit->exitRequest)
                                        <flux:button size="sm" variant="primary" icon="check-badge" wire:click="askToConfirm({{ $exit->id }})" data-test="exit-confirm">Confirmar</flux:button>
                                    @endcan
                                @endif
                                <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" :href="route('exits.show', $exit)" wire:navigate>Detalle</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $showCosts ? 10 : 9 }}" class="py-10 text-center text-zinc-500">{{ $isRequested ? 'No hay salidas por solicitud que coincidan con los filtros.' : 'No hay salidas que coincidan con los filtros.' }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="confirm-requested-exit" class="w-[calc(100vw-2rem)] md:w-[28rem]" data-test="confirm-requested-exit-modal">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">¿Confirmar esta salida?</flux:heading>
                <flux:text class="mt-2">
                    @if ($confirming)
                        La salida <span class="font-mono font-medium">{{ $confirming->reference }}</span> ({{ number_format($confirming->total_boxes) }} {{ $confirming->total_boxes == 1 ? 'caja' : 'cajas' }}) pasará a <span class="font-medium">Salidas directas</span> y se quitará de Por solicitud.
                    @endif
                </flux:text>
            </div>
            <flux:error name="operation" />
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" class="w-full sm:w-auto">Cancelar</flux:button></flux:modal.close>
                <flux:button variant="primary" icon="check-badge" wire:click="confirmRequested" wire:loading.attr="disabled" wire:target="confirmRequested" class="w-full sm:w-auto" data-test="exit-confirm-submit">Sí, confirmar</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
