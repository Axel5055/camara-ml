<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrows-right-left" :title="'Transferencia ' . $transfer->reference" description="Detalle del envío y de las cajas que viajan entre cámaras." mono>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canCorrect)
                <flux:button variant="primary" icon="pencil-square" wire:click="openEdit" data-test="transfer-edit">Corregir</flux:button>
            @endif
            @if ($canReceive)
                <flux:button variant="primary" icon="inbox-arrow-down" wire:click="askReceive({{ $transfer->id }})" data-test="transfer-receive">Recibir</flux:button>
            @endif
            @if ($canCancel)
                <flux:button variant="danger" icon="x-circle" wire:click="askCancel({{ $transfer->id }})" data-test="transfer-cancel">Cancelar</flux:button>
            @endif
            <flux:button icon="printer" x-on:click="$dispatch('print-ticket', { url: @js(route('transfers.ticket', [$transfer, 'auto' => 1])) })" data-test="transfer-print">Ticket</flux:button>
            <flux:button :href="route('transfers.export', [$transfer, 'pdf'])" icon="document-arrow-down">PDF</flux:button>
            <flux:button :href="route('transfers.export', [$transfer, 'xlsx'])" icon="table-cells">Excel</flux:button>
            <flux:button :href="route('transfers.index')" wire:navigate icon="arrow-left">Transferencias</flux:button>
        </div>
    </x-page-header>

    @php
        $openIncidents = $incidents->where('status', \App\Enums\IncidentStatus::Open)->count();
        $closedAt = $transfer->received_at ?? $transfer->cancelled_at;
        $elapsed = $transfer->dispatched_at->diffForHumans($closedAt ?? now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'short' => false]);
    @endphp

    @if ($transfer->status === \App\Enums\TransferStatus::Cancelled)
        <div class="flex items-start gap-3 rounded-xl border border-red-300/60 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" data-test="transfer-result">
            <flux:icon name="x-circle" class="mt-0.5 size-5 shrink-0" />
            <div>
                <div class="font-medium">Transferencia cancelada</div>
                Cancelada el {{ $transfer->cancelled_at?->format('d/m/Y H:i') }}{{ $canceller ? ' por '.$canceller->name : '' }}. Las cajas regresaron a la cámara de origen. Motivo: {{ $transfer->cancel_reason }}
            </div>
        </div>
    @elseif ($transfer->status === \App\Enums\TransferStatus::InTransit)
        <div class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10" data-test="transfer-result">
            <flux:icon name="truck" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-300" />
            <div>
                <div class="font-medium text-amber-800 dark:text-amber-200">En tránsito desde hace {{ $elapsed }}</div>
                <div class="text-amber-700/80 dark:text-amber-200/70">Esperando que la cámara de destino confirme la recepción.</div>
            </div>
        </div>
    @elseif ($incidents->isEmpty())
        <div class="flex items-start gap-3 rounded-xl border border-emerald-300/60 bg-emerald-50 p-4 text-sm dark:border-emerald-500/30 dark:bg-emerald-500/10" data-test="transfer-result">
            <flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-300" />
            <div>
                <div class="font-medium text-emerald-800 dark:text-emerald-200">Recibida completa y sin incidencias</div>
                <div class="text-emerald-700/80 dark:text-emerald-200/70">Llegó en {{ $elapsed }}.</div>
            </div>
        </div>
    @else
        <div class="flex items-start gap-3 rounded-xl border border-red-300/60 bg-red-50 p-4 text-sm dark:border-red-500/30 dark:bg-red-500/10" data-test="transfer-result">
            <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-red-600 dark:text-red-300" />
            <div>
                <div class="font-medium text-red-800 dark:text-red-200">Recibida con {{ $incidents->count() }} {{ $incidents->count() === 1 ? 'incidencia' : 'incidencias' }}</div>
                <div class="text-red-700/80 dark:text-red-200/70">Llegó en {{ $elapsed }}. {{ $openIncidents }} {{ $openIncidents === 1 ? 'sigue abierta' : 'siguen abiertas' }}.</div>
            </div>
        </div>
    @endif

    @if ($corrections->isNotEmpty())
        <div class="rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10" data-test="transfer-corrections">
            <div class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-200">
                <flux:icon name="pencil-square" class="size-5" />
                Esta transferencia fue corregida {{ $corrections->count() }} {{ $corrections->count() === 1 ? 'vez' : 'veces' }}
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
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'sky', 'label' => 'Cajas', 'value' => number_format($totalBoxes)],
            ['key' => 'kg', 'icon' => 'scale', 'color' => 'amber', 'label' => 'Peso enviado', 'value' => number_format($totalKg, 3).' kg'],
        ];

        if ($showCosts) {
            $cards[] = ['key' => 'cost', 'icon' => 'banknotes', 'color' => 'emerald', 'label' => 'Costo histórico', 'value' => '$'.number_format((float) $exit->total_cost_mxn, 2)];
        }
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 {{ $showCosts ? 'xl:grid-cols-3' : '' }}">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="transfer-card-{{ $card['key'] }}">
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
        <div class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Origen</div>
                <div class="font-medium">{{ $originChamber->name }}</div>
                <div class="text-xs text-zinc-500">{{ $originChamber->branch->name }}</div>
            </div>
            <flux:icon name="arrow-down" class="size-5 shrink-0 self-center text-zinc-400 sm:hidden" />
            <flux:icon name="arrow-right" class="hidden size-5 shrink-0 text-zinc-400 sm:block" />
            <div class="min-w-0 flex-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                <div class="text-xs uppercase tracking-wide text-zinc-500">Destino</div>
                <div class="font-medium">{{ $destChamber->name }}</div>
                <div class="text-xs text-zinc-500">{{ $destChamber->branch->name }}</div>
            </div>
        </div>

        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex items-start gap-3">
                <flux:icon name="calendar-days" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Enviada</div><div class="font-medium">{{ $transfer->dispatched_at->format('d/m/Y H:i') }}</div></div>
            </div>
            <div class="flex items-start gap-3">
                <flux:icon name="hand-raised" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Solicitante</div><div class="font-medium">{{ $exit->requester_name }}</div></div>
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
                        <flux:badge size="sm" :color="$transfer->status->color()">{{ $transfer->status->label() }}</flux:badge>
                        <a href="{{ route('exits.show', $exit) }}" wire:navigate><flux:badge size="sm" color="zinc" icon="arrow-up-tray">Salida {{ $exit->reference }}</flux:badge></a>
                    </div>
                </div>
            </div>
            @if ($transfer->received_at)
                <div class="flex items-start gap-3">
                    <flux:icon name="inbox-arrow-down" class="mt-0.5 size-5 text-zinc-400" />
                    <div><div class="text-xs uppercase tracking-wide text-zinc-500">Recibida</div><div class="font-medium">{{ $transfer->received_at->format('d/m/Y H:i') }}{{ $receiver ? ' · '.$receiver->name : '' }}</div></div>
                </div>
            @endif
        </div>

        @if ($exit->notes)
            <div class="mt-5 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900/40">
                <div class="mb-0.5 text-xs uppercase tracking-wide text-zinc-500">Observaciones del envío</div>
                {{ $exit->notes }}
            </div>
        @endif

        @if ($transfer->receive_notes)
            <div class="mt-3 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900/40">
                <div class="mb-0.5 text-xs uppercase tracking-wide text-zinc-500">Observaciones de la recepción</div>
                {{ $transfer->receive_notes }}
            </div>
        @endif
    </div>

    @if ($incidents->isNotEmpty())
        <div class="space-y-2">
            <flux:heading size="lg">Incidencias</flux:heading>
            <div class="space-y-2" data-test="transfer-incidents">
                @foreach ($incidents as $incident)
                    <div class="flex flex-wrap items-start gap-3 rounded-xl border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800" wire:key="inc-{{ $incident->id }}">
                        <flux:badge size="sm" :color="$incident->type->color()">{{ $incident->type->label() }}</flux:badge>
                        <div class="min-w-0 flex-1">
                            @if ($incident->box)
                                <div class="font-mono text-xs">{{ $incident->box->barcode }}</div>
                            @endif
                            <div>{{ $incident->description }}</div>
                            @if ($incident->expected_weight_kg !== null || $incident->received_weight_kg !== null)
                                <div class="text-xs text-zinc-500">Peso esperado {{ $incident->expected_weight_kg ?? '—' }} kg, recibido {{ $incident->received_weight_kg ?? '—' }} kg</div>
                            @endif
                        </div>
                        <flux:badge size="sm" :color="$incident->status->value === 'open' ? 'red' : 'green'">{{ $incident->status->value === 'open' ? 'Abierta' : 'Resuelta' }}</flux:badge>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-2">
        <flux:heading size="lg">Resumen por producto</flux:heading>
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="transfer-summary-table">
            <flux:table container:class="overflow-x-auto">
                <flux:table.columns>
                    <flux:table.column>Producto</flux:table.column>
                    <flux:table.column align="end">Cajas</flux:table.column>
                    <flux:table.column align="end">Peso</flux:table.column>
                    <flux:table.column align="end">Precio por caja</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($perProduct as $row)
                        <flux:table.row wire:key="pp-{{ $loop->index }}">
                            <flux:table.cell class="font-medium">{{ $row->product }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($row->boxes) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $row->kg, 3) }} kg</flux:table.cell>
                            <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">${{ number_format((float) $row->min_unit_cost, 2) }}@if ((float) $row->min_unit_cost !== (float) $row->max_unit_cost) – ${{ number_format((float) $row->max_unit_cost, 2) }}@endif</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <div class="space-y-2">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:heading size="lg">Cajas de la transferencia</flux:heading>
            <div class="w-full sm:w-80">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por producto o código de barras" clearable data-test="transfer-box-search" />
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="transfer-boxes-table">
            <flux:table :paginate="$items" container:class="overflow-x-auto">
                <flux:table.columns>
                    <flux:table.column>Código de barras</flux:table.column>
                    <flux:table.column>Producto</flux:table.column>
                    <flux:table.column align="end">Peso enviado</flux:table.column>
                    <flux:table.column align="end">Peso recibido</flux:table.column>
                    <flux:table.column align="end">Precio por caja</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($items as $item)
                        <flux:table.row :key="$item->id">
                            <flux:table.cell class="font-mono text-xs">{{ $item->box->barcode }}</flux:table.cell>
                            <flux:table.cell>{{ $item->box->product->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $item->expected_weight_kg }} kg</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $item->received_weight_kg !== null ? $item->received_weight_kg.' kg' : '—' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $item->box->unit_cost_mxn, 2) }}</flux:table.cell>
                            <flux:table.cell>
                                @switch($item->status->value)
                                    @case('received')<flux:badge size="sm" color="green">Recibida</flux:badge>@break
                                    @case('missing')<flux:badge size="sm" color="red">Faltante</flux:badge>@break
                                    @default<flux:badge size="sm" color="amber">Esperada</flux:badge>
                                @endswitch
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" :href="route('boxes.show', $item->box)" wire:navigate>Historial</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">No hay cajas que coincidan con la búsqueda.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    @if ($canCorrect)
        <flux:modal name="edit-transfer" class="w-[calc(100vw-2rem)] md:w-[48rem]">
            @php
                $kgFormat = fn ($value): string => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
                $removedIds = array_map('intval', $removeIds);
                $baseBoxes = 0;
                $baseKg = 0.0;
                $baseCost = 0.0;
                $removedBoxes = 0;
                $removedKg = 0.0;
                $removedCost = 0.0;

                foreach ($editableItems as $editable) {
                    $baseBoxes++;
                    $baseKg += (float) $editable->expected_weight_kg;
                    $baseCost += (float) $editable->box->unit_cost_mxn;

                    if (in_array($editable->box_id, $removedIds, true)) {
                        $removedBoxes++;
                        $removedKg += (float) $editable->expected_weight_kg;
                        $removedCost += (float) $editable->box->unit_cost_mxn;
                    }
                }

                $addedBoxes = $pendingBoxes->count();
                $addedKg = (float) $pendingBoxes->sum(fn ($box) => (float) $box->current_weight_kg);
                $addedCost = (float) $pendingBoxes->sum(fn ($box) => (float) $box->unit_cost_mxn);
                $newBoxes = $baseBoxes - $removedBoxes + $addedBoxes;
                $newKg = $baseKg - $removedKg + $addedKg;
                $newCost = $baseCost - $removedCost + $addedCost;
            @endphp

            <form wire:submit="saveEdit" class="space-y-5" data-test="transfer-edit-form">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                        <flux:icon name="pencil-square" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg">Corregir transferencia {{ $transfer->reference }}</flux:heading>
                        <flux:text class="text-sm">Ajusta los datos capturados y explica el motivo de la corrección.</flux:text>
                    </div>
                </div>

                @if ($inTransit)
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
                        <div>Esta transferencia ya fue recibida: solo se pueden corregir el solicitante y las observaciones. Para cambiar lo demás, cancélala y registra otra.</div>
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
                            <flux:input wire:model="editDate" type="datetime-local" label="Fecha y hora de envío" :disabled="! $inTransit" />
                            <flux:select wire:model="editDestChamberId" label="Cámara destino" :disabled="! $inTransit">
                                @foreach ($destChambers as $chamber)
                                    <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="editNotes" label="Observaciones" />
                        </div>
                    </div>

                    @if ($inTransit)
                        <div class="space-y-3" x-data="{ search: '' }">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                                    <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">2</span>
                                    Cajas de la transferencia
                                    <span class="font-normal text-zinc-500">({{ $baseBoxes }})</span>
                                </div>
                                @if ($removedBoxes > 0)
                                    <span class="rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/60 dark:text-red-300" data-test="transfer-edit-removing">{{ $removedBoxes }} {{ $removedBoxes === 1 ? 'se quitará' : 'se quitarán' }}</span>
                                @endif
                            </div>
                            <flux:text class="text-xs">Marca las cajas que no debían viajar.</flux:text>

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
                                            <div class="text-xs text-zinc-500">{{ $editable->box->product->name }} · {{ $kgFormat($editable->expected_weight_kg) }} kg · ${{ number_format((float) $editable->box->unit_cost_mxn, 2) }} por caja</div>
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
                                    <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300" data-test="transfer-edit-adding">{{ $addedBoxes }} {{ $addedBoxes === 1 ? 'se agregará' : 'se agregarán' }}</span>
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

                        <div class="grid grid-cols-2 gap-3 text-center {{ $showCosts ? 'sm:grid-cols-3' : '' }}" data-test="transfer-edit-totals">
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
                                <div class="col-span-2 rounded-lg border border-zinc-200 p-3 sm:col-span-1 dark:border-zinc-700" data-test="transfer-edit-amount">
                                    <div class="text-xs uppercase tracking-wide text-zinc-500">Costo histórico</div>
                                    <div class="text-xl font-semibold tabular-nums">${{ number_format($newCost, 2) }}</div>
                                    <div class="text-xs text-zinc-500">antes ${{ number_format($baseCost, 2) }}</div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                            <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">{{ $inTransit ? 4 : 2 }}</span>
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

    @include('livewire.inventory.partials.transfer-modals')
</section>
