<section class="w-full space-y-6">
    <x-page-header icon="arrows-right-left" title="Transferencias" description="Envíos de cajas entre cámaras y su recepción." />

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

    <flux:modal name="receive-transfer" class="md:w-[36rem]">
        <form wire:submit="receive" class="space-y-4">
            <flux:heading size="lg">Recibir transferencia {{ $acting?->reference }}</flux:heading>
            <flux:text>Marca las cajas que llegaron. Las no marcadas quedan en tránsito con una incidencia abierta. Si el peso medido difiere, captúralo para registrar la incidencia.</flux:text>
            <flux:error name="operation" />

            <div class="max-h-80 space-y-2 overflow-y-auto">
                @foreach ($acting?->items ?? [] as $item)
                    <div class="flex items-center gap-3" wire:key="recv-{{ $item->box_id }}">
                        <input type="checkbox" wire:model="received" value="{{ $item->box_id }}" class="size-4 rounded" />
                        <span class="flex-1 font-mono text-xs">{{ $item->box->barcode }}</span>
                        <span class="text-xs text-zinc-500">esperado {{ $item->expected_weight_kg }} kg</span>
                        <flux:input size="sm" class="w-28" wire:model="weights.{{ $item->box_id }}" placeholder="Peso real" inputmode="decimal" />
                    </div>
                @endforeach
            </div>

            <flux:textarea wire:model="receiveNotes" label="Comentarios de la recepción" rows="3" placeholder="Opcional: estado de la mercancía, faltantes, retrasos u otras observaciones." />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cerrar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Confirmar recepción</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="transfer-detail" class="md:w-[46rem]" data-test="transfer-detail">
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
            @endphp

            <div class="space-y-5">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="lg">Transferencia</flux:heading>
                    <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $detail->reference }}</span>
                    <flux:badge size="sm" :color="$detail->status->color()">{{ $detail->status->label() }}</flux:badge>
                </div>
                <flux:text class="-mt-3">Salida {{ $detail->exit->reference }} · solicitada por {{ $detail->exit->requester_name }}</flux:text>

                {{-- Resultado de la transferencia --}}
                @if ($detail->status === \App\Enums\TransferStatus::Cancelled)
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/40" data-test="detail-result">
                        <div class="font-medium text-zinc-900 dark:text-white">Transferencia cancelada</div>
                        <div class="text-zinc-500">Las cajas regresaron a la cámara de origen. Motivo: {{ $detail->cancel_reason }}</div>
                    </div>
                @elseif ($detail->status === \App\Enums\TransferStatus::InTransit)
                    <div class="rounded-lg border border-amber-300/60 bg-amber-50 p-3 text-sm dark:border-amber-500/30 dark:bg-amber-500/10" data-test="detail-result">
                        <div class="font-medium text-amber-800 dark:text-amber-200">En tránsito desde hace {{ $elapsed }}</div>
                        <div class="text-amber-700/80 dark:text-amber-200/70">Esperando que la cámara de destino confirme la recepción.</div>
                    </div>
                @elseif ($incidents->isEmpty())
                    <div class="rounded-lg border border-emerald-300/60 bg-emerald-50 p-3 text-sm dark:border-emerald-500/30 dark:bg-emerald-500/10" data-test="detail-result">
                        <div class="font-medium text-emerald-800 dark:text-emerald-200">Recibida completa y sin incidencias</div>
                        <div class="text-emerald-700/80 dark:text-emerald-200/70">Llegó en {{ $elapsed }}.</div>
                    </div>
                @else
                    <div class="rounded-lg border border-red-300/60 bg-red-50 p-3 text-sm dark:border-red-500/30 dark:bg-red-500/10" data-test="detail-result">
                        <div class="font-medium text-red-800 dark:text-red-200">Recibida con {{ $incidents->count() }} {{ $incidents->count() === 1 ? 'incidencia' : 'incidencias' }}</div>
                        <div class="text-red-700/80 dark:text-red-200/70">Llegó en {{ $elapsed }}. {{ $openIncidents->count() }} {{ $openIncidents->count() === 1 ? 'sigue abierta' : 'siguen abiertas' }}.</div>
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Origen</div>
                        <div class="font-medium text-zinc-900 dark:text-white">{{ $detail->originChamber->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $detail->originChamber->branch->name }}</div>
                        <div class="mt-2 text-xs text-zinc-500">Enviada el {{ $detail->dispatched_at->format('d/m/Y H:i') }} por {{ $detail->exit->user->name }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Destino</div>
                        <div class="font-medium text-zinc-900 dark:text-white">{{ $detail->destChamber->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $detail->destChamber->branch->name }}</div>
                        <div class="mt-2 text-xs text-zinc-500">
                            @if ($detail->received_at)
                                Recibida el {{ $detail->received_at->format('d/m/Y H:i') }} por {{ $detail->receiver?->name }}
                            @elseif ($detail->cancelled_at)
                                Cancelada el {{ $detail->cancelled_at->format('d/m/Y H:i') }} por {{ $detail->canceller?->name }}
                            @else
                                Pendiente de recibir
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">Cajas enviadas</div>
                        <div class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $items->count() }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">Cajas recibidas</div>
                        <div class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $receivedItems->count() }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">Faltantes</div>
                        <div class="text-lg font-semibold tabular-nums {{ $missingItems->count() > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-white' }}">{{ $missingItems->count() }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">Peso esperado / recibido</div>
                        <div class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($expectedKg, 3) }} / {{ $detail->received_at ? number_format($receivedKg, 3) : '—' }} kg</div>
                    </div>
                </div>

                @if (filled($detail->receive_notes))
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="detail-notes">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Comentarios de la recepción</div>
                        <div class="mt-1 whitespace-pre-line text-sm text-zinc-900 dark:text-white">{{ $detail->receive_notes }}</div>
                    </div>
                @endif

                <div class="space-y-2" data-test="detail-incidents">
                    <div class="text-sm font-medium text-zinc-900 dark:text-white">Incidencias</div>
                    @forelse ($incidents as $incident)
                        <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700" wire:key="detail-incident-{{ $incident->id }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" :color="$incident->type->color()">{{ $incident->type->label() }}</flux:badge>
                                <flux:badge size="sm" :color="$incident->status === \App\Enums\IncidentStatus::Open ? 'red' : 'green'">{{ $incident->status === \App\Enums\IncidentStatus::Open ? 'Abierta' : 'Resuelta' }}</flux:badge>
                                @if ($incident->box)
                                    <span class="rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $incident->box->barcode }}</span>
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
                        <div class="rounded-lg border border-dashed border-zinc-300 p-3 text-center text-sm text-zinc-500 dark:border-zinc-600">Sin incidencias registradas.</div>
                    @endforelse
                </div>

                <div class="space-y-2" data-test="detail-boxes">
                    <div class="text-sm font-medium text-zinc-900 dark:text-white">Cajas de la transferencia</div>
                    <div class="max-h-60 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/40">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Caja</th>
                                    <th class="px-3 py-2 text-right font-medium">Esperado</th>
                                    <th class="px-3 py-2 text-right font-medium">Recibido</th>
                                    <th class="px-3 py-2 font-medium">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($items as $item)
                                    <tr wire:key="detail-item-{{ $item->id }}">
                                        <td class="px-3 py-2 font-mono text-xs">{{ $item->box->barcode }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $item->expected_weight_kg }} kg</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $item->received_weight_kg !== null ? $item->received_weight_kg.' kg' : '—' }}</td>
                                        <td class="px-3 py-2">
                                            <flux:badge size="sm" :color="match ($item->status) { \App\Enums\TransferItemStatus::Received => 'green', \App\Enums\TransferItemStatus::Missing => 'red', default => 'amber' }">
                                                {{ match ($item->status) { \App\Enums\TransferItemStatus::Received => 'Recibida', \App\Enums\TransferItemStatus::Missing => 'Faltante', default => 'En tránsito' } }}
                                            </flux:badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
