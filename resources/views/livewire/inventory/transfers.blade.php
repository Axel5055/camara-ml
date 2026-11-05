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
                                <flux:button size="sm" variant="ghost" icon="eye" wire:click="showDetail({{ $transfer->id }})" data-test="transfer-detail-{{ $transfer->id }}">Detalle</flux:button>
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
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">No hay transferencias que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="receive-transfer" class="w-[calc(100vw-2rem)] md:w-[44rem]">
        <form
            wire:submit="receive"
            class="space-y-5"
            data-test="receive-form"
            x-data="{
                search: '',
                expected: @js(collect($acting?->items ?? [])->mapWithKeys(fn ($item) => [$item->box_id => (float) $item->expected_weight_kg])->all()),
                total: {{ $acting?->items->count() ?? 0 }},
                get arrived() { return $wire.received.length; },
                get missing() { return this.total - this.arrived; },
                isReceived(id) { return $wire.received.map(String).includes(String(id)); },
                diff(id) {
                    const raw = ($wire.weights?.[id] ?? '').toString().trim();
                    if (raw === '' || isNaN(raw)) { return null; }
                    return Math.round((parseFloat(raw) - this.expected[id]) * 1000) / 1000;
                },
                markAll(value) { $wire.received = value ? Object.keys(this.expected) : []; },
            }"
        >
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                    <flux:icon name="truck" class="size-5" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg">Recibir transferencia {{ $acting?->reference }}</flux:heading>
                    <flux:text class="text-sm">
                        {{ $acting?->originChamber?->name }} <span class="text-zinc-400">→</span> {{ $acting?->destChamber?->name }}
                    </flux:text>
                </div>
            </div>

            <flux:error name="operation" />

            <div class="grid grid-cols-3 gap-3 text-center" data-test="receive-summary">
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Enviadas</div>
                    <div class="text-xl font-semibold tabular-nums" x-text="total"></div>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950/40">
                    <div class="text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Llegaron</div>
                    <div class="text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300" x-text="arrived"></div>
                </div>
                <div class="rounded-lg border p-3" :class="missing > 0 ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40' : 'border-zinc-200 dark:border-zinc-700'">
                    <div class="text-xs uppercase tracking-wide" :class="missing > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-zinc-500'">Faltan</div>
                    <div class="text-xl font-semibold tabular-nums" :class="missing > 0 ? 'text-amber-700 dark:text-amber-300' : ''" x-text="missing"></div>
                </div>
            </div>

            <div x-show="missing > 0" x-cloak class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                <flux:icon name="exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
                <span>Las cajas sin marcar quedan en tránsito y se abre una incidencia. Si el peso medido difiere del esperado, captúralo para registrarlo.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="w-full sm:min-w-40 sm:flex-1 sm:w-auto">
                    <flux:input size="sm" x-model="search" icon="magnifying-glass" placeholder="Buscar código de caja" clearable />
                </div>
                <flux:button size="sm" type="button" variant="subtle" x-on:click="markAll(true)">Marcar todas</flux:button>
                <flux:button size="sm" type="button" variant="subtle" x-on:click="markAll(false)">Quitar todas</flux:button>
            </div>

            <div class="max-h-[40vh] space-y-2 overflow-y-auto pe-1" data-test="receive-list">
                @foreach ($acting?->items ?? [] as $item)
                    <label
                        class="block cursor-pointer rounded-xl border p-3 transition"
                        :class="isReceived({{ $item->box_id }}) ? 'border-zinc-200 dark:border-zinc-700' : 'border-amber-300 bg-amber-50/60 dark:border-amber-700 dark:bg-amber-950/20'"
                        x-show="search.trim() === '' || @js(strtolower($item->box->barcode.' '.($item->box->product?->name ?? ''))).includes(search.trim().toLowerCase())"
                        wire:key="recv-{{ $item->box_id }}"
                        data-test="receive-row"
                    >
                        <div class="flex items-start gap-3">
                            <input type="checkbox" wire:model="received" value="{{ $item->box_id }}" class="mt-1 size-5 shrink-0 rounded" />
                            <div class="min-w-0 flex-1">
                                <div class="break-all font-mono text-sm font-medium text-zinc-900 dark:text-white">{{ $item->box->barcode }}</div>
                                <div class="text-xs text-zinc-500">{{ $item->box->product?->name }} · esperado <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ rtrim(rtrim(number_format((float) $item->expected_weight_kg, 3, '.', ','), '0'), '.') }} kg</span></div>
                            </div>
                            <span x-show="! isReceived({{ $item->box_id }})" x-cloak class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">No llegó</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2 ps-8" x-show="isReceived({{ $item->box_id }})">
                            <flux:input size="sm" class="!w-36" wire:model="weights.{{ $item->box_id }}" placeholder="Peso real (kg)" inputmode="decimal" aria-label="Peso real de {{ $item->box->barcode }}" />
                            <template x-if="diff({{ $item->box_id }}) !== null && diff({{ $item->box_id }}) !== 0">
                                <span class="rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/60 dark:text-red-300" x-text="(diff({{ $item->box_id }}) > 0 ? '+' : '') + diff({{ $item->box_id }}) + ' kg'"></span>
                            </template>
                            <template x-if="diff({{ $item->box_id }}) === 0">
                                <span class="text-xs text-emerald-600 dark:text-emerald-400">Coincide</span>
                            </template>
                        </div>
                    </label>
                @endforeach
            </div>

            <flux:textarea wire:model="receiveNotes" label="Comentarios de la recepción" rows="2" placeholder="Opcional: estado de la mercancía, faltantes, retrasos u otras observaciones." />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost" type="button">Cerrar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Confirmar recepción</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="transfer-detail" class="w-[calc(100vw-2rem)] md:w-[48rem]" data-test="transfer-detail">
        @if ($detail)
            @php
                $items = $detail->items;
                $receivedItems = $items->where('status', \App\Enums\TransferItemStatus::Received);
                $missingItems = $items->where('status', \App\Enums\TransferItemStatus::Missing);
                $incidents = $detail->incidents;
                $openIncidents = $incidents->where('status', \App\Enums\IncidentStatus::Open);
                $closedAt = $detail->received_at ?? $detail->cancelled_at;
                $elapsed = $detail->dispatched_at->diffForHumans($closedAt ?? now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'short' => false]);
                $expectedKg = $items->sum(fn ($item) => (float) $item->expected_weight_kg);
                $receivedKg = $receivedItems->sum(fn ($item) => (float) $item->received_weight_kg);
                $kg = fn ($value): string => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
                $isReceived = $detail->received_at !== null;
                $receivedExpectedKg = $receivedItems->sum(fn ($item) => (float) $item->expected_weight_kg);
                $delta = round($receivedKg - $receivedExpectedKg, 3);
            @endphp

            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                        <flux:icon name="arrows-right-left" class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg">Transferencia</flux:heading>
                            <span class="rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $detail->reference }}</span>
                            <flux:badge size="sm" :color="$detail->status->color()">{{ $detail->status->label() }}</flux:badge>
                        </div>
                        <flux:text class="text-sm">Salida {{ $detail->exit->reference }} · solicitada por {{ $detail->exit->requester_name }}</flux:text>
                    </div>
                </div>

                {{-- Resultado de la transferencia --}}
                @if ($detail->status === \App\Enums\TransferStatus::Cancelled)
                    <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/40" data-test="detail-result">
                        <flux:icon name="x-circle" class="mt-0.5 size-5 shrink-0 text-zinc-500" />
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-white">Transferencia cancelada</div>
                            <div class="text-zinc-500">Las cajas regresaron a la cámara de origen. Motivo: {{ $detail->cancel_reason }}</div>
                        </div>
                    </div>
                @elseif ($detail->status === \App\Enums\TransferStatus::InTransit)
                    <div class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 p-3 text-sm dark:border-amber-500/30 dark:bg-amber-500/10" data-test="detail-result">
                        <flux:icon name="truck" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-300" />
                        <div>
                            <div class="font-medium text-amber-800 dark:text-amber-200">En tránsito desde hace {{ $elapsed }}</div>
                            <div class="text-amber-700/80 dark:text-amber-200/70">Esperando que la cámara de destino confirme la recepción.</div>
                        </div>
                    </div>
                @elseif ($incidents->isEmpty())
                    <div class="flex items-start gap-3 rounded-xl border border-emerald-300/60 bg-emerald-50 p-3 text-sm dark:border-emerald-500/30 dark:bg-emerald-500/10" data-test="detail-result">
                        <flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-300" />
                        <div>
                            <div class="font-medium text-emerald-800 dark:text-emerald-200">Recibida completa y sin incidencias</div>
                            <div class="text-emerald-700/80 dark:text-emerald-200/70">Llegó en {{ $elapsed }}.</div>
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-3 rounded-xl border border-red-300/60 bg-red-50 p-3 text-sm dark:border-red-500/30 dark:bg-red-500/10" data-test="detail-result">
                        <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-red-600 dark:text-red-300" />
                        <div>
                            <div class="font-medium text-red-800 dark:text-red-200">Recibida con {{ $incidents->count() }} {{ $incidents->count() === 1 ? 'incidencia' : 'incidencias' }}</div>
                            <div class="text-red-700/80 dark:text-red-200/70">Llegó en {{ $elapsed }}. {{ $openIncidents->count() }} {{ $openIncidents->count() === 1 ? 'sigue abierta' : 'siguen abiertas' }}.</div>
                        </div>
                    </div>
                @endif

                {{-- Ruta --}}
                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Origen</div>
                        <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $detail->originChamber->name }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $detail->originChamber->branch->name }}</div>
                        <div class="mt-2 flex items-start gap-1.5 text-xs text-zinc-500">
                            <flux:icon name="paper-airplane" class="mt-px size-3.5 shrink-0" />
                            <span>Enviada el {{ $detail->dispatched_at->format('d/m/Y H:i') }} por {{ $detail->exit->user->name }}</span>
                        </div>
                    </div>
                    <flux:icon name="arrow-right" class="hidden size-5 shrink-0 text-zinc-400 sm:block" />
                    <flux:icon name="arrow-down" class="mx-auto size-5 shrink-0 text-zinc-400 sm:hidden" />
                    <div class="min-w-0 flex-1 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Destino</div>
                        <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $detail->destChamber->name }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $detail->destChamber->branch->name }}</div>
                        <div class="mt-2 flex items-start gap-1.5 text-xs text-zinc-500">
                            <flux:icon name="{{ $isReceived ? 'check' : ($detail->cancelled_at ? 'x-mark' : 'clock') }}" class="mt-px size-3.5 shrink-0" />
                            <span>
                                @if ($isReceived)
                                    Recibida el {{ $detail->received_at->format('d/m/Y H:i') }} por {{ $detail->receiver?->name }}
                                @elseif ($detail->cancelled_at)
                                    Cancelada el {{ $detail->cancelled_at->format('d/m/Y H:i') }} por {{ $detail->canceller?->name }}
                                @else
                                    Pendiente de recibir
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Resumen --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4" data-test="detail-summary">
                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Enviadas</div>
                        <div class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $items->count() }}</div>
                        <div class="text-xs tabular-nums text-zinc-500">{{ $kg($expectedKg) }} kg</div>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950/40">
                        <div class="text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Recibidas</div>
                        <div class="text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $receivedItems->count() }}</div>
                        <div class="text-xs tabular-nums text-emerald-700/80 dark:text-emerald-300/80">{{ $isReceived ? $kg($receivedKg).' kg' : '—' }}</div>
                    </div>
                    <div class="rounded-xl border p-3 {{ $missingItems->count() > 0 ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/40' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <div class="text-xs uppercase tracking-wide {{ $missingItems->count() > 0 ? 'text-red-700 dark:text-red-300' : 'text-zinc-500' }}">Faltantes</div>
                        <div class="text-xl font-semibold tabular-nums {{ $missingItems->count() > 0 ? 'text-red-700 dark:text-red-300' : 'text-zinc-900 dark:text-white' }}">{{ $missingItems->count() }}</div>
                        <div class="text-xs text-zinc-500">&nbsp;</div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">Diferencia</div>
                        @if ($isReceived)
                            <div class="text-xl font-semibold tabular-nums {{ $delta == 0 ? 'text-zinc-900 dark:text-white' : 'text-red-600 dark:text-red-400' }}">{{ ($delta > 0 ? '+' : '').$kg($delta) }}</div>
                            <div class="text-xs text-zinc-500">kg en cajas recibidas</div>
                        @else
                            <div class="text-xl font-semibold text-zinc-900 dark:text-white">—</div>
                            <div class="text-xs text-zinc-500">sin recepción</div>
                        @endif
                    </div>
                </div>

                @if (filled($detail->receive_notes))
                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-test="detail-notes">
                        <div class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-zinc-500">
                            <flux:icon name="chat-bubble-left-ellipsis" class="size-4" /> Comentarios de la recepción
                        </div>
                        <div class="mt-1 whitespace-pre-line text-sm text-zinc-900 dark:text-white">{{ $detail->receive_notes }}</div>
                    </div>
                @endif

                {{-- Incidencias --}}
                <div class="space-y-2" data-test="detail-incidents">
                    <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                        Incidencias
                        @if ($incidents->isNotEmpty())
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950/60 dark:text-red-300">{{ $incidents->count() }}</span>
                        @endif
                    </div>
                    @forelse ($incidents as $incident)
                        <div class="rounded-xl border p-3 text-sm {{ $incident->status === \App\Enums\IncidentStatus::Open ? 'border-red-200 bg-red-50/50 dark:border-red-900 dark:bg-red-950/20' : 'border-zinc-200 dark:border-zinc-700' }}" wire:key="detail-incident-{{ $incident->id }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" :color="$incident->type->color()">{{ $incident->type->label() }}</flux:badge>
                                <flux:badge size="sm" :color="$incident->status === \App\Enums\IncidentStatus::Open ? 'red' : 'green'">{{ $incident->status === \App\Enums\IncidentStatus::Open ? 'Abierta' : 'Resuelta' }}</flux:badge>
                                @if ($incident->box)
                                    <span class="break-all rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $incident->box->barcode }}</span>
                                @endif
                            </div>
                            <div class="mt-2 text-zinc-900 dark:text-white">{{ $incident->description }}</div>
                            @if ($incident->expected_weight_kg !== null)
                                <div class="mt-1 text-xs text-zinc-500">Esperado {{ $incident->expected_weight_kg }} kg · recibido {{ $incident->received_weight_kg ?? '—' }} kg</div>
                            @endif
                            <div class="mt-1 text-xs text-zinc-500">Reportada por {{ $incident->reporter?->name }} el {{ $incident->created_at->format('d/m/Y H:i') }}</div>
                            @if ($incident->status === \App\Enums\IncidentStatus::Resolved)
                                <div class="mt-1 text-xs text-zinc-500">Resuelta por {{ $incident->resolver?->name }} el {{ $incident->resolved_at?->format('d/m/Y H:i') }}: {{ $incident->resolution }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 p-3 text-center text-sm text-zinc-500 dark:border-zinc-600">Sin incidencias registradas.</div>
                    @endforelse
                </div>

                {{-- Cajas --}}
                <div
                    class="space-y-2"
                    data-test="detail-boxes"
                    x-data="{ search: '' }"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm font-medium text-zinc-900 dark:text-white">Cajas de la transferencia <span class="font-normal text-zinc-500">({{ $items->count() }})</span></div>
                        @if ($items->count() > 5)
                            <div class="w-full sm:w-56">
                                <flux:input size="sm" x-model="search" icon="magnifying-glass" placeholder="Buscar código o producto" clearable />
                            </div>
                        @endif
                    </div>

                    <div class="max-h-72 space-y-2 overflow-y-auto pe-1">
                        @foreach ($items as $item)
                            @php
                                $itemStatus = $item->status;
                                $itemColor = match ($itemStatus) { \App\Enums\TransferItemStatus::Received => 'green', \App\Enums\TransferItemStatus::Missing => 'red', default => 'amber' };
                                $itemLabel = match ($itemStatus) { \App\Enums\TransferItemStatus::Received => 'Recibida', \App\Enums\TransferItemStatus::Missing => 'Faltante', default => 'En tránsito' };
                                $itemDiff = $item->received_weight_kg !== null ? round((float) $item->received_weight_kg - (float) $item->expected_weight_kg, 3) : null;
                            @endphp
                            <div
                                class="rounded-xl border p-3 {{ $itemStatus === \App\Enums\TransferItemStatus::Missing ? 'border-red-200 bg-red-50/50 dark:border-red-900 dark:bg-red-950/20' : 'border-zinc-200 dark:border-zinc-700' }}"
                                x-show="search.trim() === '' || @js(strtolower($item->box->barcode.' '.($item->box->product?->name ?? ''))).includes(search.trim().toLowerCase())"
                                wire:key="detail-item-{{ $item->id }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="break-all font-mono text-sm font-medium text-zinc-900 dark:text-white">{{ $item->box->barcode }}</div>
                                        <div class="text-xs text-zinc-500">{{ $item->box->product?->name }}</div>
                                    </div>
                                    <flux:badge size="sm" :color="$itemColor" class="shrink-0">{{ $itemLabel }}</flux:badge>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500">
                                    <span>Esperado <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-300">{{ $kg($item->expected_weight_kg) }} kg</span></span>
                                    <span>Recibido <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-300">{{ $item->received_weight_kg !== null ? $kg($item->received_weight_kg).' kg' : '—' }}</span></span>
                                    @if ($itemDiff !== null && $itemDiff != 0)
                                        <span class="rounded-md bg-red-100 px-2 py-0.5 font-medium tabular-nums text-red-700 dark:bg-red-950/60 dark:text-red-300">{{ ($itemDiff > 0 ? '+' : '').$kg($itemDiff) }} kg</span>
                                    @elseif ($itemDiff !== null)
                                        <span class="text-emerald-600 dark:text-emerald-400">Coincide</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:modal.close><flux:button variant="ghost">Cerrar</flux:button></flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="cancel-transfer" class="md:w-96">
        <form wire:submit="cancel" class="space-y-6">
            <flux:heading size="lg">Cancelar transferencia {{ $acting?->reference }}</flux:heading>
            @if ($acting?->status === \App\Enums\TransferStatus::Received)
                <flux:callout variant="warning" icon="exclamation-triangle" inline>
                    <flux:callout.text>Esta transferencia ya fue recibida. Las cajas regresarán desde la cámara destino a la de origen, siempre que sigan disponibles allá sin cambios. Las incidencias abiertas se cerrarán.</flux:callout.text>
                </flux:callout>
            @else
                <flux:text>Las cajas regresan a la cámara de origen.</flux:text>
            @endif
            <flux:error name="operation" />
            <flux:textarea wire:model="reason" label="Motivo" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cerrar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Cancelar transferencia</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
