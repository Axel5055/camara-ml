<section class="w-full space-y-6">
    <x-page-header icon="clipboard-document-list" :title="$isRequester ? 'Mis solicitudes' : 'Solicitudes de salida'" :description="$isRequester ? 'Historial de lo que has pedido, con su estado y los motivos.' : 'Pedidos de las sucursales sin cámara: el almacenista de la cámara los autoriza o los rechaza.'">
        @can('request-exits')
            <flux:button variant="primary" icon="plus" :href="route('exit-requests.create')" wire:navigate data-test="request-new">Nueva solicitud</flux:button>
        @endcan
        @can('print-exit-summary')
            <livewire:inventory.exit-request-summary-print />
        @endcan
    </x-page-header>

    @if ($flash !== '')
        <flux:callout variant="success" icon="check-circle" :heading="$flash" data-test="request-flash" />
    @endif

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="status" label="Estado" data-test="request-status">
                <flux:select.option value="pending">Pendientes ({{ $pendingCount }})</flux:select.option>
                <flux:select.option value="all">Todas</flux:select.option>
                @foreach ($statuses as $state)
                    @unless ($state === \App\Enums\ExitRequestStatus::Pending)
                        <flux:select.option :value="$state->value">{{ $state->label() }}s</flux:select.option>
                    @endunless
                @endforeach
            </flux:select>
        </div>
    </div>

    <flux:error name="operation" />

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exit-requests-table">
        <flux:table :paginate="$requests" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Solicitud</flux:table.column>
                @unless ($isRequester)
                    <flux:table.column>Sucursal</flux:table.column>
                @endunless
                <flux:table.column>Cámara</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column>Solicitó</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($requests as $item)
                    <flux:table.row :key="$item->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="font-mono text-xs text-zinc-700 dark:text-zinc-200">{{ $item->reference() }}</div>
                            <div class="text-xs text-zinc-500">{{ $item->created_at->format('d/m/Y H:i') }}</div>
                        </flux:table.cell>
                        @unless ($isRequester)
                            <flux:table.cell>{{ $item->branch->name }}</flux:table.cell>
                        @endunless
                        <flux:table.cell>
                            <div>{{ $item->chamber->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $item->chamber->branch->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">
                            {{ number_format($item->total_boxes_requested) }}
                            @if ($item->total_boxes_approved !== null && $item->total_boxes_approved !== $item->total_boxes_requested)
                                <div class="text-xs font-normal text-zinc-500">se surtieron {{ number_format($item->total_boxes_approved) }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $item->user->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$item->status->color()">{{ $item->status->label() }}</flux:badge>
                            @if ($item->decider)
                                <div class="mt-1 text-xs text-zinc-500">{{ $item->decider->name }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @can('cancel', $item)
                                <flux:button size="sm" variant="ghost" wire:click="cancelRequest({{ $item->id }})" wire:confirm="¿Cancelar esta solicitud de salida?" data-test="request-cancel">Cancelar</flux:button>
                            @endcan
                            @if ($item->exit && auth()->user()->can('view', $item->exit))
                                <flux:button size="sm" variant="ghost" :href="route('exits.show', $item->exit)" wire:navigate>{{ $item->exit->reference }}</flux:button>
                            @endif
                            <flux:button size="sm" :variant="$item->isPending() && auth()->user()->can('decide', $item) ? 'primary' : 'ghost'" wire:click="openRequest({{ $item->id }})" data-test="request-open">
                                {{ $item->isPending() && auth()->user()->can('decide', $item) ? 'Revisar' : 'Detalle' }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $isRequester ? 6 : 7 }}" class="py-10 text-center text-zinc-500">No hay solicitudes en este estado.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="exit-request" class="w-[calc(100vw-2rem)] md:w-[48rem]" data-test="exit-request-modal">
        @if ($viewing)
            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                        <flux:icon name="clipboard-document-list" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg">Solicitud {{ $viewing->reference() }}</flux:heading>
                        <flux:text class="text-sm">
                            <flux:badge size="sm" :color="$viewing->status->color()">{{ $viewing->status->label() }}</flux:badge>
                            · {{ $viewing->user->name }} · {{ $viewing->created_at->format('d/m/Y H:i') }}
                        </flux:text>
                    </div>
                </div>

                <dl class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Sucursal solicitante</dt><dd class="text-right font-medium">{{ $viewing->branch->name }}</dd></div>
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Cámara</dt><dd class="text-right font-medium">{{ $viewing->chamber->branch->name }} · {{ $viewing->chamber->name }}</dd></div>
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Cajas pedidas</dt><dd class="font-medium tabular-nums">{{ number_format($viewing->total_boxes_requested) }}</dd></div>
                    @if ($viewing->total_boxes_approved !== null)
                        <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Cajas surtidas</dt><dd class="font-medium tabular-nums">{{ number_format($viewing->total_boxes_approved) }}</dd></div>
                    @endif
                    @if ($viewing->notes)
                        <div class="px-3 py-2"><dt class="text-zinc-500">Comentario del solicitante</dt><dd class="mt-1 whitespace-pre-line">{{ $viewing->notes }}</dd></div>
                    @endif
                    @if ($viewing->decided_at)
                        <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">{{ $viewing->status->label() }} por</dt><dd class="text-right font-medium">{{ $viewing->decider?->name ?? $viewing->user->name }} · {{ $viewing->decided_at->format('d/m/Y H:i') }}</dd></div>
                    @endif
                    @if ($viewing->decision_notes)
                        <div class="px-3 py-2" data-test="request-decision-notes"><dt class="text-zinc-500">{{ $viewing->status === \App\Enums\ExitRequestStatus::Rejected ? 'Motivo del rechazo' : 'Comentario de quien resolvió' }}</dt><dd class="mt-1 whitespace-pre-line">{{ $viewing->decision_notes }}</dd></div>
                    @endif
                    @if ($viewing->exit)
                        <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Salida generada</dt><dd class="font-mono font-medium">{{ $viewing->exit->reference }}</dd></div>
                    @endif
                </dl>

                <div class="space-y-2" data-test="request-summary">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Lo que se pidió</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($viewing->items as $item)
                            <div class="flex items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-500/30 dark:bg-indigo-500/10" wire:key="summary-{{ $item->id }}">
                                <div class="flex min-w-14 flex-col items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-white">
                                    <span class="text-2xl font-bold leading-none tabular-nums">{{ number_format($item->boxes_requested) }}</span>
                                    <span class="text-[10px] font-medium uppercase tracking-wide">{{ $item->boxes_requested === 1 ? 'caja' : 'cajas' }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-zinc-900 dark:text-white">{{ $item->product->name }}</div>
                                    @if ($item->boxes_approved !== null)
                                        <div @class(['text-xs font-medium', 'text-emerald-700 dark:text-emerald-300' => $item->boxes_approved === $item->boxes_requested, 'text-amber-700 dark:text-amber-300' => $item->boxes_approved !== $item->boxes_requested])>Se surtieron {{ number_format($item->boxes_approved) }} de {{ number_format($item->boxes_requested) }}</div>
                                    @else
                                        <div class="text-xs text-zinc-500">Pedidas por {{ $viewing->branch->name }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <flux:error name="operation" />

                @if ($printExitId !== null && $viewing->exit)
                    <div class="space-y-5 rounded-xl border border-emerald-300 bg-emerald-50 p-5 text-center dark:border-emerald-500/40 dark:bg-emerald-500/10" data-test="request-print-step">
                        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                            <flux:icon.check-circle class="size-8" />
                        </div>
                        <div class="space-y-1">
                            <div class="text-lg font-semibold text-zinc-900 dark:text-white">Salida registrada</div>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                                <span class="font-mono">{{ $viewing->exit->reference }}</span> · {{ number_format($viewing->total_boxes_approved ?? 0) }} {{ ($viewing->total_boxes_approved ?? 0) === 1 ? 'caja' : 'cajas' }} para {{ $viewing->branch->name }}
                            </p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-white">¿Quieres imprimir el ticket de la salida?</p>
                        </div>
                        <div class="mx-auto max-w-xs text-left">
                            <flux:input type="number" min="1" max="10" step="1" inputmode="numeric" wire:model="ticketCopies" label="Copias del ticket a imprimir" data-test="ticket-copies" />
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                            <flux:button variant="ghost" wire:click="skipTicket" data-test="request-skip-ticket">Ahora no</flux:button>
                            <flux:button variant="primary" icon="printer" wire:click="printTicket" wire:loading.attr="disabled" wire:target="printTicket" data-test="request-print-ticket">Imprimir ticket</flux:button>
                        </div>
                        <p class="text-xs text-zinc-500">Si ahora no, podrás imprimirlo después desde Salidas.</p>
                    </div>
                @elseif ($canDecide && $confirming)
                    <div class="space-y-4" data-test="request-confirm-step">
                        <div class="rounded-xl border border-indigo-300 bg-indigo-50 p-4 dark:border-indigo-500/40 dark:bg-indigo-500/10">
                            <div class="font-semibold text-zinc-900 dark:text-white">Confirma la salida</div>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Se registrará una salida hacia <span class="font-medium">{{ $viewing->branch->name }}</span> con estas cajas. Esta acción no se puede deshacer desde aquí.</p>
                            <ul class="mt-3 divide-y divide-indigo-200 text-sm dark:divide-indigo-500/30">
                                @foreach ($viewing->items as $item)
                                    @if (count($selected[$item->product_id] ?? []) > 0)
                                        <li class="flex items-center justify-between gap-3 py-1.5">
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $item->product->name }}</span>
                                            <span class="tabular-nums text-zinc-600 dark:text-zinc-300">{{ count($selected[$item->product_id]) }} de {{ $item->boxes_requested }} pedidas</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                            @if (trim($approveNotes) !== '')
                                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Comentario: {{ $approveNotes }}</p>
                            @endif
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <flux:button variant="ghost" icon="arrow-left" wire:click="backToReview" data-test="request-back">Volver</flux:button>
                            <flux:button variant="primary" icon="check" wire:click="approve" wire:loading.attr="disabled" wire:target="approve" data-test="request-approve">Confirmar y registrar salida</flux:button>
                        </div>
                    </div>
                @elseif ($canDecide)
                    <div class="space-y-4" data-test="request-review">
                        <flux:text class="text-sm">Revisa las cajas que saldrán. Las más antiguas vienen marcadas; quita las que no vayas a enviar o cambia por otras. No puedes enviar más de las pedidas: al llegar al máximo de un producto, desmarca una caja para poder elegir otra.</flux:text>

                        @foreach ($viewing->items as $item)
                            @php($boxes = $boxesByProduct->get($item->product_id, collect()))
                            @php($chosen = array_map('strval', $selected[$item->product_id] ?? []))
                            @php($limitReached = count($chosen) >= $item->boxes_requested)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700" wire:key="review-{{ $item->product_id }}">
                                <div class="space-y-3 border-b border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $item->product->name }}</div>
                                    <div class="grid grid-cols-3 gap-2 text-center">
                                        <div class="rounded-lg bg-indigo-50 p-2 dark:bg-indigo-500/10">
                                            <div class="text-xl font-bold tabular-nums text-indigo-700 dark:text-indigo-300">{{ number_format($item->boxes_requested) }}</div>
                                            <div class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Pidió</div>
                                        </div>
                                        <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-700/50">
                                            <div class="text-xl font-bold tabular-nums text-zinc-800 dark:text-zinc-100">{{ number_format($boxes->count()) }}</div>
                                            <div class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Disponibles</div>
                                        </div>
                                        <div @class(['rounded-lg p-2', 'bg-emerald-50 dark:bg-emerald-500/10' => count($chosen) === $item->boxes_requested, 'bg-amber-50 dark:bg-amber-500/10' => count($chosen) < $item->boxes_requested, 'bg-red-50 dark:bg-red-500/10' => count($chosen) > $item->boxes_requested])>
                                            <div @class(['text-xl font-bold tabular-nums', 'text-emerald-700 dark:text-emerald-300' => count($chosen) === $item->boxes_requested, 'text-amber-700 dark:text-amber-300' => count($chosen) < $item->boxes_requested, 'text-red-600 dark:text-red-400' => count($chosen) > $item->boxes_requested]) data-test="request-selected-{{ $item->product_id }}">{{ number_format(count($chosen)) }}</div>
                                            <div class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Enviarás</div>
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $item->boxes_requested }}" aria-valuenow="{{ count($chosen) }}">
                                            <div @class(['h-full rounded-full transition-all', 'bg-emerald-500' => count($chosen) === $item->boxes_requested, 'bg-amber-500' => count($chosen) < $item->boxes_requested, 'bg-red-500' => count($chosen) > $item->boxes_requested]) style="width: {{ min(100, (int) round(count($chosen) / max(1, $item->boxes_requested) * 100)) }}%"></div>
                                        </div>
                                        <div class="text-xs text-zinc-500">
                                            @if (count($chosen) === $item->boxes_requested)
                                                Completo: enviarás todo lo que pidieron.
                                            @elseif (count($chosen) < $item->boxes_requested)
                                                Faltan {{ $item->boxes_requested - count($chosen) }} para completar lo pedido{{ $boxes->count() < $item->boxes_requested ? ' (no hay suficientes en la cámara)' : '' }}.
                                            @else
                                                Te pasas por {{ count($chosen) - $item->boxes_requested }}: quita cajas.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if ($boxes->isEmpty())
                                    <div class="px-3 py-3 text-sm text-amber-700 dark:text-amber-300">No hay cajas disponibles de este producto en la cámara.</div>
                                @else
                                    <div class="grid max-h-56 gap-1 overflow-y-auto p-2 sm:grid-cols-2">
                                        @foreach ($boxes as $box)
                                            @php($blocked = $limitReached && ! in_array((string) $box->id, $chosen, true))
                                            <label @class(['flex items-center gap-2 rounded-md px-2 py-1 text-sm', 'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/40' => ! $blocked, 'cursor-not-allowed opacity-50' => $blocked]) wire:key="box-{{ $box->id }}">
                                                <input type="checkbox" value="{{ $box->id }}" wire:model.live="selected.{{ $item->product_id }}" @disabled($blocked) class="rounded border-zinc-300" data-test="request-box-{{ $box->id }}" />
                                                <span class="font-mono text-xs">{{ $box->barcode }}</span>
                                                <span class="ms-auto text-xs text-zinc-500">{{ $box->current_weight_kg }} kg · {{ $box->entered_at->format('d/m/y') }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <flux:textarea wire:model="approveNotes" label="Comentario al autorizar (opcional)" rows="2" />
                        <flux:error name="approveNotes" />

                        <div class="flex justify-end">
                            <flux:button variant="primary" icon="check" wire:click="askToApprove" wire:loading.attr="disabled" wire:target="askToApprove" data-test="request-ask-approve">Autorizar y registrar salida</flux:button>
                        </div>

                        <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:textarea wire:model="rejectReason" label="Motivo del rechazo" rows="2" placeholder="Explica por qué no se surte (mínimo 5 caracteres)" data-test="request-reject-reason" />
                            <flux:error name="rejectReason" />
                            <div class="flex justify-end">
                                <flux:button variant="danger" icon="x-mark" wire:click="reject" wire:loading.attr="disabled" wire:target="reject" data-test="request-reject">Rechazar solicitud</flux:button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" data-test="request-items">
                        <flux:table container:class="overflow-x-auto">
                            <flux:table.columns>
                                <flux:table.column>Producto</flux:table.column>
                                <flux:table.column align="end">Pidió</flux:table.column>
                                <flux:table.column align="end">Surtido</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($viewing->items as $item)
                                    <flux:table.row :key="'item-'.$item->id">
                                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $item->product->name }}</flux:table.cell>
                                        <flux:table.cell align="end" class="tabular-nums">{{ number_format($item->boxes_requested) }}</flux:table.cell>
                                        <flux:table.cell align="end" class="tabular-nums">{{ $item->boxes_approved === null ? '—' : number_format($item->boxes_approved) }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</section>
