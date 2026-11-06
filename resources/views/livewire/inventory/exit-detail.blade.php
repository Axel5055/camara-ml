<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrow-up-tray" :title="'Salida ' . $stockExit->reference" description="Detalle del registro y de las cajas que salieron." mono>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canEdit)
                <flux:button variant="primary" icon="pencil-square" wire:click="openEdit" data-test="exit-edit">Corregir</flux:button>
            @endif
            @if ($canReverse)
                <flux:button variant="danger" icon="arrow-uturn-left" wire:click="openReverse" data-test="exit-reverse">Revertir</flux:button>
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
                    <flux:table.column align="end">Precio por caja</flux:table.column>
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
                            <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">${{ number_format((float) $row->min_unit_cost, 2) }}@if ((float) $row->min_unit_cost !== (float) $row->max_unit_cost) – ${{ number_format((float) $row->max_unit_cost, 2) }}@endif</flux:table.cell>
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
                    <flux:table.column align="end">Precio por caja</flux:table.column>
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
                            <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $item->unit_cost_snapshot_mxn, 2) }}</flux:table.cell>
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

    @if ($canReverse)
        <flux:modal name="reverse-exit" class="w-[calc(100vw-2rem)] md:w-[28rem]">
            <form wire:submit="reverse" class="space-y-5" data-test="exit-reverse-form">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-500/10 text-red-600 dark:text-red-300">
                        <flux:icon name="arrow-uturn-left" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg">Revertir salida {{ $stockExit->reference }}</flux:heading>
                        <flux:text class="text-sm">
                            @if ($transfer)
                                Se cancelará la transferencia {{ $transfer->reference }} y las cajas volverán a la cámara de origen.
                            @else
                                Las cajas vuelven a la cámara de origen. El movimiento original se conserva en el historial.
                            @endif
                        </flux:text>
                    </div>
                </div>
                <flux:error name="operation" />
                <flux:textarea wire:model="reverseReason" label="Motivo" rows="3" placeholder="Por qué se revierte esta salida." required />
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="danger" icon="arrow-uturn-left" class="w-full sm:w-auto">Revertir salida</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($canEdit)
        <flux:modal name="edit-exit" class="w-[calc(100vw-2rem)] md:w-[48rem]">
            @php
                $kgFormat = fn ($value): string => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
                $removedIds = array_map('intval', $removeIds);
                $baseBoxes = 0;
                $baseKg = 0.0;
                $baseCost = 0.0;
                $removedBoxes = 0;
                $removedKg = 0.0;
                $removedCost = 0.0;

                if ($canEditBoxes) {
                    foreach ($editableItems as $editable) {
                        $baseBoxes++;
                        $baseKg += (float) $editable->weight_out_kg;
                        $baseCost += (float) $editable->cost_out_mxn;

                        if (in_array($editable->box_id, $removedIds, true)) {
                            $removedBoxes++;
                            $removedKg += (float) $editable->weight_out_kg;
                            $removedCost += (float) $editable->cost_out_mxn;
                        }
                    }
                }

                $addedBoxes = $pendingBoxes->count();
                $addedKg = (float) $pendingBoxes->sum(fn ($box) => (float) $box->current_weight_kg);
                $addedCost = (float) $pendingBoxes->sum(fn ($box) => (float) $box->unit_cost_mxn * (float) $box->current_weight_kg / max((float) $box->initial_weight_kg, 0.001));
                $newBoxes = $baseBoxes - $removedBoxes + $addedBoxes;
                $newKg = $baseKg - $removedKg + $addedKg;
                $newCost = $baseCost - $removedCost + $addedCost;
            @endphp

            <form wire:submit="saveEdit" class="space-y-5" data-test="exit-edit-form">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                        <flux:icon name="pencil-square" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg">Corregir salida {{ $stockExit->reference }}</flux:heading>
                        <flux:text class="text-sm">Ajusta los datos capturados y explica el motivo de la corrección.</flux:text>
                    </div>
                </div>

                @if ($canEditBoxes)
                    <div class="flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-sm text-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                        <flux:icon name="information-circle" class="mt-0.5 size-4 shrink-0" />
                        <div class="space-y-1">
                            <div>Las cajas que quites regresan a la cámara de origen.</div>
                            <div>Puedes agregar otras cajas disponibles en esa cámara escribiendo su código de barras.</div>
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <flux:icon name="exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
                        <div>
                            Esta salida {{ $transfer ? 'generó una transferencia' : 'incluye retiros parciales' }}: solo se pueden corregir el solicitante y las observaciones{{ $transfer ? '' : ', la fecha y el destino' }}. Para cambiar cajas, revierte la salida y registra otra.
                        </div>
                    </div>
                @endif

                <flux:error name="operation" />

                <div class="max-h-[55vh] space-y-5 -mx-1.5 overflow-y-auto p-1.5">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                            <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">1</span>
                            Datos generales
                        </div>
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
                    </div>

                    @if ($canEditBoxes)
                        <div class="space-y-3" x-data="{ search: '' }">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">2</span>
                                    Cajas de la salida
                                    <span class="font-normal text-zinc-500">({{ $baseBoxes }})</span>
                                </div>
                                @if ($removedBoxes > 0)
                                    <span class="rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/60 dark:text-red-300" data-test="exit-edit-removing">{{ $removedBoxes }} {{ $removedBoxes === 1 ? 'se quitará' : 'se quitarán' }}</span>
                                @endif
                            </div>
                            <flux:text class="text-xs">Marca las cajas que salieron por error.</flux:text>

                            @if ($baseBoxes > 5)
                                <flux:input size="sm" x-model="search" icon="magnifying-glass" placeholder="Buscar código o producto" clearable />
                            @endif

                            <div class="max-h-64 space-y-2 -mx-1.5 overflow-y-auto p-1.5">
                                @foreach ($editableItems as $editable)
                                    @php
                                        $isRemoved = in_array($editable->box_id, $removedIds, true);
                                    @endphp
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition {{ $isRemoved ? 'border-red-300 bg-red-50/60 dark:border-red-800 dark:bg-red-950/20' : 'border-zinc-200 dark:border-zinc-700' }}"
                                        x-show="search.trim() === '' || @js(strtolower($editable->box->barcode.' '.$editable->box->product->name)).includes(search.trim().toLowerCase())"
                                        wire:key="rm-{{ $editable->id }}"
                                    >
                                        <input type="checkbox" value="{{ $editable->box_id }}" wire:model.live="removeIds" class="mt-1 size-5 shrink-0 rounded border-zinc-300" />
                                        <div class="min-w-0 flex-1">
                                            <div class="break-all font-mono text-sm font-medium text-zinc-900 dark:text-white">{{ $editable->box->barcode }}</div>
                                            <div class="text-xs text-zinc-500">{{ $editable->box->product->name }} · {{ $kgFormat($editable->weight_out_kg) }} kg · ${{ number_format((float) $editable->unit_cost_snapshot_mxn, 2) }} por caja</div>
                                        </div>
                                        @if ($isRemoved)
                                            <span class="shrink-0 rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/60 dark:text-red-300">Se quitará</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                                <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">3</span>
                                Agregar cajas
                                @if ($addedBoxes > 0)
                                    <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300" data-test="exit-edit-adding">{{ $addedBoxes }} {{ $addedBoxes === 1 ? 'se agregará' : 'se agregarán' }}</span>
                                @endif
                            </div>
                            <div class="flex items-start gap-2">
                                <div class="flex-1"><flux:input wire:model="addBarcode" wire:keydown.enter.prevent="addBox" placeholder="Código de barras de la caja" icon="qr-code" /></div>
                                <flux:button type="button" wire:click="addBox" icon="plus">Agregar</flux:button>
                            </div>
                            <flux:error name="addBarcode" />

                            @foreach ($pendingBoxes as $pending)
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-emerald-300 bg-emerald-50/60 p-3 dark:border-emerald-800 dark:bg-emerald-950/20" wire:key="add-{{ $pending->id }}">
                                    <div class="min-w-0">
                                        <div class="break-all font-mono text-sm font-medium text-zinc-900 dark:text-white">{{ $pending->barcode }}</div>
                                        <div class="text-xs text-zinc-500">{{ $pending->product->name }} · {{ $kgFormat($pending->current_weight_kg) }} kg · ${{ number_format((float) $pending->unit_cost_mxn, 2) }} por caja</div>
                                    </div>
                                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removePendingAdd({{ $pending->id }})" aria-label="Quitar {{ $pending->barcode }}" />
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-center {{ $showCosts ? 'sm:grid-cols-3' : '' }}" data-test="exit-edit-totals">
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Cajas</div>
                                <div class="text-xl font-semibold tabular-nums">{{ number_format($newBoxes) }}</div>
                                <div class="text-xs text-zinc-500">antes {{ number_format($baseBoxes) }}</div>
                            </div>
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Peso total</div>
                                <div class="text-xl font-semibold tabular-nums">{{ $kgFormat($newKg) }} kg</div>
                                <div class="text-xs text-zinc-500">antes {{ $kgFormat($baseKg) }} kg</div>
                            </div>
                            @if ($showCosts)
                                <div class="col-span-2 rounded-lg border border-zinc-200 p-3 sm:col-span-1 dark:border-zinc-700" data-test="exit-edit-amount">
                                    <div class="text-xs uppercase tracking-wide text-zinc-500">Costo histórico</div>
                                    <div class="text-xl font-semibold tabular-nums">${{ number_format($newCost, 2) }}</div>
                                    <div class="text-xs text-zinc-500">antes ${{ number_format($baseCost, 2) }}</div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                            <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">{{ $canEditBoxes ? 4 : 2 }}</span>
                            Motivo de la corrección
                        </div>
                        <flux:textarea wire:model="editReason" rows="2" placeholder="Qué se capturó mal y qué se corrige." required />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto">Guardar corrección</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
