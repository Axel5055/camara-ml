<section class="w-full space-y-6">
    <x-page-header icon="shield-check" title="Solicitudes de entrada" description="Las entradas de los almacenistas se registran cuando un administrador las autoriza.">
        @can('operate-inventory')
            <flux:button variant="primary" icon="plus" :href="route('entries.create')" wire:navigate>Nueva entrada</flux:button>
        @endcan
    </x-page-header>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="status" label="Estado" data-test="request-status">
                <flux:select.option value="pending">Pendientes ({{ $pendingCount }})</flux:select.option>
                <flux:select.option value="all">Todas</flux:select.option>
                @foreach ($statuses as $state)
                    @unless ($state === \App\Enums\EntryRequestStatus::Pending)
                        <flux:select.option :value="$state->value">{{ $state->label() }}s</flux:select.option>
                    @endunless
                @endforeach
            </flux:select>
        </div>
    </div>

    <flux:error name="operation" />

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entry-requests-table">
        <flux:table :paginate="$requests" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Solicitud</flux:table.column>
                <flux:table.column>Cámara</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column>Solicitó</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($requests as $item)
                    <flux:table.row :key="$item->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="font-mono text-xs text-zinc-700 dark:text-zinc-200">SOL-{{ str_pad((string) $item->id, 5, '0', STR_PAD_LEFT) }}</div>
                            <div class="text-xs text-zinc-500">{{ $item->created_at->format('d/m/Y H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $item->chamber->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $item->chamber->branch->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($item->total_boxes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $item->total_weight_kg }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $item->user->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$item->status->color()">{{ $item->status->label() }}</flux:badge>
                            @if ($item->decider)
                                <div class="mt-1 text-xs text-zinc-500">{{ $item->decider->name }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @can('cancel', $item)
                                <flux:button size="sm" variant="ghost" wire:click="cancelRequest({{ $item->id }})" wire:confirm="¿Cancelar esta solicitud de entrada?" data-test="request-cancel">Cancelar</flux:button>
                            @endcan
                            @if ($item->entry)
                                <flux:button size="sm" variant="ghost" :href="route('entries.show', $item->entry)" wire:navigate>{{ $item->entry->reference }}</flux:button>
                            @endif
                            <flux:button size="sm" :variant="$item->isPending() && auth()->user()->can('decide', $item) ? 'primary' : 'ghost'" wire:click="openRequest({{ $item->id }})" data-test="request-open">
                                {{ $item->isPending() && auth()->user()->can('decide', $item) ? 'Revisar' : 'Detalle' }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">No hay solicitudes en este estado.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="entry-request" class="w-[calc(100vw-2rem)] md:w-[44rem]" data-test="entry-request-modal">
        @if ($viewing)
            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                        <flux:icon name="shield-check" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg">Solicitud SOL-{{ str_pad((string) $viewing->id, 5, '0', STR_PAD_LEFT) }}</flux:heading>
                        <flux:text class="text-sm">
                            <flux:badge size="sm" :color="$viewing->status->color()">{{ $viewing->status->label() }}</flux:badge>
                            · {{ $viewing->user->name }} · {{ $viewing->created_at->format('d/m/Y H:i') }}
                        </flux:text>
                    </div>
                </div>

                <dl class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Cámara</dt><dd class="text-right font-medium">{{ $viewing->chamber->branch->name }} · {{ $viewing->chamber->name }}</dd></div>
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Fecha de la entrada</dt><dd class="font-medium">{{ $viewing->occurred_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Cajas</dt><dd class="font-medium tabular-nums">{{ number_format($viewing->total_boxes) }}</dd></div>
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Peso total</dt><dd class="font-medium tabular-nums">{{ $viewing->total_weight_kg }} kg</dd></div>
                    @can('view-costs')
                        <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Importe</dt><dd class="font-medium tabular-nums">${{ number_format((float) $viewing->total_cost_mxn, 2) }}</dd></div>
                    @endcan
                    @if ($viewing->notes)
                        <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Notas</dt><dd class="text-right">{{ $viewing->notes }}</dd></div>
                    @endif
                </dl>

                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm" data-test="request-groups">
                        <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-700/40">
                            <tr>
                                <th class="px-3 py-2">Producto</th>
                                <th class="px-3 py-2 text-right">Cajas</th>
                                <th class="px-3 py-2 text-right">kg por caja</th>
                                <th class="px-3 py-2 text-right">Precio por caja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($viewing->groups as $group)
                                <tr>
                                    <td class="px-3 py-2">
                                        {{ $products[$group['product_id']] ?? 'Producto #'.$group['product_id'] }}
                                        @if ($group['manual_barcodes'] !== [])
                                            <div class="text-xs text-amber-600">{{ count($group['manual_barcodes']) }} código(s) manual(es) · {{ $group['manual_barcode_origin'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($group['boxes_count']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $group['weight_per_box_kg'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">${{ number_format((float) $group['unit_cost_mxn'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($viewing->decision_notes)
                    <div class="rounded-lg bg-zinc-100 p-3 text-sm dark:bg-zinc-700/40" data-test="request-decision-notes">
                        <span class="font-medium">{{ $viewing->status === \App\Enums\EntryRequestStatus::Rejected ? 'Motivo del rechazo' : 'Comentario' }}:</span> {{ $viewing->decision_notes }}
                    </div>
                @endif

                @if ($viewing->status === \App\Enums\EntryRequestStatus::Approved && $viewing->entry)
                    <div class="flex flex-wrap items-end gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="request-print">
                        <flux:input type="number" min="1" max="10" step="1" inputmode="numeric" wire:model="ticketCopies" label="Copias del ticket" class="w-28" data-test="request-ticket-copies" />
                        <flux:button icon="printer" wire:click="printTicket" data-test="request-print-ticket">Imprimir ticket de {{ $viewing->entry->reference }}</flux:button>
                    </div>
                @endif

                @if ($canDecide)
                    <flux:error name="operation" />
                    <flux:textarea wire:model="rejectReason" label="Motivo (obligatorio solo si la rechazas)" rows="2" data-test="request-reject-reason" />

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Cerrar</flux:button></flux:modal.close>
                        <flux:button variant="danger" icon="x-mark" wire:click="reject" wire:loading.attr="disabled" wire:target="reject,approve" class="w-full sm:w-auto" data-test="request-reject">Rechazar</flux:button>
                        <flux:button variant="primary" icon="check" wire:click="approve" wire:loading.attr="disabled" wire:target="reject,approve" class="w-full sm:w-auto" data-test="request-approve">Autorizar y registrar</flux:button>
                    </div>
                @else
                    <div class="flex justify-end">
                        <flux:modal.close><flux:button variant="ghost" type="button">Cerrar</flux:button></flux:modal.close>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>
</section>
