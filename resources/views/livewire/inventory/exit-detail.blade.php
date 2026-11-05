<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrow-up-tray" :title="'Salida ' . $stockExit->reference" description="Detalle del registro y de las cajas que salieron." mono>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canEdit)
                <flux:button variant="primary" icon="pencil-square" wire:click="openEdit" data-test="exit-edit">Corregir</flux:button>
            @endif
            <flux:button icon="printer" x-on:click="$dispatch('print-ticket', { url: @js(route('exits.ticket', [$stockExit, 'auto' => 1])) })" data-test="exit-print">Ticket</flux:button>
            <flux:button :href="route('exits.export', [$stockExit, 'pdf'])" icon="document-arrow-down">PDF</flux:button>
            <flux:button :href="route('exits.export', [$stockExit, 'xlsx'])" icon="table-cells">Excel</flux:button>
            <flux:button :href="route('exits.index')" wire:navigate icon="arrow-left">Salidas</flux:button>
        </div>
    </x-page-header>

    @if ($stockExit->reversed_at)
        <div class="flex items-start gap-3 rounded-xl border border-red-300/60 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" data-test="exit-reversed">
            <flux:icon name="arrow-uturn-left" class="mt-0.5 size-5 shrink-0" />
            <div>
                <div class="font-medium">Salida revertida</div>
                Revertida el {{ $stockExit->reversed_at->format('d/m/Y H:i') }}{{ $reversedBy ? ' por '.$reversedBy->name : '' }}. Motivo: {{ $stockExit->reversed_reason }}
            </div>
        </div>
    @endif

    @if ($corrections->isNotEmpty())
        <div class="rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10" data-test="exit-corrections">
            <div class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-200">
                <flux:icon name="pencil-square" class="size-5" />
                Esta salida fue corregida {{ $corrections->count() }} {{ $corrections->count() === 1 ? 'vez' : 'veces' }}
            </div>
            <ul class="mt-2 space-y-1 border-s-2 border-amber-300/70 ps-3 text-amber-700/90 dark:border-amber-500/40 dark:text-amber-200/80">
                @foreach ($corrections as $correction)
                    <li><span class="font-medium">{{ $correction->created_at->format('d/m/Y H:i') }}</span> · {{ $correction->user?->name }}: {{ $correction->new_values['reason'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $cards = [
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'sky', 'label' => 'Cajas', 'value' => number_format($stockExit->total_boxes)],
            ['key' => 'kg', 'icon' => 'scale', 'color' => 'amber', 'label' => 'Peso total', 'value' => number_format((float) $stockExit->total_weight_kg, 3).' kg'],
        ];

        if ($showCosts) {
            $cards[] = ['key' => 'cost', 'icon' => 'banknotes', 'color' => 'emerald', 'label' => 'Costo histórico', 'value' => '$'.number_format((float) $stockExit->total_cost_mxn, 2)];
        }
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 {{ $showCosts ? 'xl:grid-cols-3' : '' }}">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exit-card-{{ $card['key'] }}">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-{{ $card['color'] }}-500/10 text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-300">
                    <flux:icon :name="$card['icon']" class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $card['label'] }}</div>
                    <div class="truncate text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $card['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <div class="min-w-0 flex-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Origen</div>
                <div class="font-medium">{{ $originChamber->name }}</div>
                <div class="text-xs text-zinc-500">{{ $originChamber->branch->name }}</div>
            </div>
            <flux:icon name="arrow-right" class="size-5 shrink-0 text-zinc-400" />
            <div class="min-w-0 flex-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Destino</div>
                <div class="font-medium">{{ $destBranch->name }}</div>
                <div class="text-xs text-zinc-500">{{ $destChamber ? 'Cámara '.$destChamber->name : 'Entrega directa (sin cámara)' }}</div>
            </div>
        </div>

        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex items-start gap-3">
                <flux:icon name="calendar-days" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Fecha y hora</div><div class="font-medium">{{ $stockExit->occurred_at->format('d/m/Y H:i') }}</div></div>
            </div>
            <div class="flex items-start gap-3">
                <flux:icon name="hand-raised" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Solicitante</div><div class="font-medium">{{ $stockExit->requester_name }}</div></div>
            </div>
            <div class="flex items-start gap-3">
                <flux:icon name="user" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Registró</div><div class="font-medium">{{ $registeredBy->name }}</div></div>
            </div>
            <div class="flex items-start gap-3">
                <flux:icon name="check-badge" class="mt-0.5 size-5 text-zinc-400" />
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Estado</div>
                    <div class="flex flex-wrap gap-1">
                        <flux:badge size="sm" :color="$stockExit->status->value === 'confirmed' ? 'green' : 'red'">{{ $stockExit->status->label() }}</flux:badge>
                        @if ($transfer)
                            <flux:badge size="sm" :color="$transfer->status->color()">Transferencia {{ $transfer->reference }} · {{ $transfer->status->label() }}</flux:badge>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($stockExit->notes)
            <div class="mt-5 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900/40">
                <div class="mb-0.5 text-xs uppercase tracking-wide text-zinc-500">Observaciones</div>
                {{ $stockExit->notes }}
            </div>
        @endif
    </div>

    <div class="space-y-2">
        <flux:heading size="lg">Resumen por producto</flux:heading>
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exit-summary-table">
            <flux:table container:class="overflow-x-auto">
                <flux:table.columns>
                    <flux:table.column>Producto</flux:table.column>
                    <flux:table.column align="end">Cajas</flux:table.column>
                    <flux:table.column align="end">Peso</flux:table.column>
                    @if ($showCosts)
                        <flux:table.column align="end">Costo histórico</flux:table.column>
                    @endif
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($perProduct as $row)
                        <flux:table.row wire:key="pp-{{ $loop->index }}">
                            <flux:table.cell class="font-medium">{{ $row->product }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($row->boxes) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $row->kg, 3) }} kg</flux:table.cell>
                            @if ($showCosts)
                                <flux:table.cell align="end" class="font-medium tabular-nums">${{ number_format((float) $row->cost, 2) }}</flux:table.cell>
                            @endif
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <div class="space-y-2">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:heading size="lg">Cajas de la salida</flux:heading>
            <div class="w-full sm:w-80">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por producto o código de barras" clearable data-test="exit-box-search" />
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exit-boxes-table">
            <flux:table :paginate="$items" container:class="overflow-x-auto">
                <flux:table.columns>
                    <flux:table.column>Código de barras</flux:table.column>
                    <flux:table.column>Producto</flux:table.column>
                    <flux:table.column>Tipo</flux:table.column>
                    <flux:table.column align="end">Peso que salió</flux:table.column>
                    <flux:table.column align="end">Peso restante en la caja</flux:table.column>
                    @if ($showCosts)
                        <flux:table.column align="end">Costo histórico</flux:table.column>
                    @endif
                    <flux:table.column>Estado actual</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($items as $item)
                        <flux:table.row :key="$item->id">
                            <flux:table.cell class="font-mono text-xs">{{ $item->box->barcode }}</flux:table.cell>
                            <flux:table.cell>{{ $item->box->product->name }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$item->kind->value === 'full' ? 'blue' : 'amber'">{{ $item->kind->value === 'full' ? 'Caja completa' : 'Retiro parcial' }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $item->weight_out_kg }} kg</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $item->kind->value === 'full' ? '—' : $item->weight_after_kg.' kg' }}</flux:table.cell>
                            @if ($showCosts)
                                <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $item->cost_out_mxn, 2) }}</flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$item->box->status->color()">{{ $item->box->status->label() }}</flux:badge>
                                @unless ($item->is_active)
                                    <flux:badge size="sm" color="red">{{ $stockExit->status->value === 'confirmed' ? 'Quitada por corrección' : 'Revertida' }}</flux:badge>
                                @endunless
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" :href="route('boxes.show', $item->box)" wire:navigate>Historial</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="py-8 text-center text-zinc-500">No hay cajas que coincidan con la búsqueda.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    @if ($canEdit)
        <flux:modal name="edit-exit" class="md:w-[44rem]">
            <form wire:submit="saveEdit" class="space-y-5" data-test="exit-edit-form">
                <div>
                    <flux:heading size="lg">Corregir salida {{ $stockExit->reference }}</flux:heading>
                    <flux:text class="mt-1">
                        @if ($canEditBoxes)
                            Cambia los datos del encabezado, quita las cajas que salieron por error (regresan a la cámara de origen) o agrega otras disponibles en esa cámara.
                        @else
                            Esta salida {{ $transfer ? 'generó una transferencia' : 'incluye retiros parciales' }}: solo se pueden corregir el solicitante y las observaciones{{ $transfer ? '' : ', la fecha y el destino' }}. Para cambiar cajas, revierte la salida y registra otra.
                        @endif
                    </flux:text>
                </div>

                <flux:error name="operation" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="editRequester" label="Solicitante" required />
                    <flux:input wire:model="editDate" type="datetime-local" label="Fecha y hora" :disabled="(bool) $transfer" />
                    <flux:select wire:model="editBranchId" label="Sucursal destino" :disabled="(bool) $transfer">
                        @foreach ($branches as $branch)
                            <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="editNotes" label="Observaciones" />
                </div>

                @if ($canEditBoxes)
                    <div class="space-y-2">
                        <flux:heading>Cajas de la salida</flux:heading>
                        <flux:text class="text-xs">Marca las cajas que se quitan de la salida.</flux:text>
                        <div class="max-h-56 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @foreach ($editableItems as $editable)
                                <label class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm" wire:key="rm-{{ $editable->id }}">
                                    <input type="checkbox" value="{{ $editable->box_id }}" wire:model="removeIds" class="rounded border-zinc-300" />
                                    <span class="font-mono text-xs">{{ $editable->box->barcode }}</span>
                                    <span class="text-zinc-500">{{ $editable->box->product->name }} · {{ $editable->weight_out_kg }} kg</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2">
                        <flux:heading>Agregar cajas</flux:heading>
                        <div class="flex items-start gap-2">
                            <div class="flex-1"><flux:input wire:model="addBarcode" wire:keydown.enter.prevent="addBox" placeholder="Código de barras de la caja" /></div>
                            <flux:button type="button" wire:click="addBox" icon="plus">Agregar</flux:button>
                        </div>
                        <flux:error name="addBarcode" />
                        @foreach ($pendingBoxes as $pending)
                            <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800" wire:key="add-{{ $pending->id }}">
                                <span><span class="font-mono text-xs">{{ $pending->barcode }}</span> <span class="text-zinc-500">· {{ $pending->product->name }}</span></span>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removePendingAdd({{ $pending->id }})" />
                            </div>
                        @endforeach
                    </div>
                @endif

                <flux:textarea wire:model="editReason" label="Motivo de la corrección" rows="2" placeholder="Qué se capturó mal y qué se corrige." required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Guardar corrección</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
