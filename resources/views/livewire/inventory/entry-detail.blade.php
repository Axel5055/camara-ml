<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrow-down-tray" :title="'Entrada ' . $entry->reference" description="Detalle del registro y de las cajas que ingresaron." mono>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canEdit)
                <flux:button variant="primary" icon="pencil-square" wire:click="openEdit" data-test="entry-edit">Corregir</flux:button>
            @endif
            @if ($canReverse)
                <flux:button variant="danger" icon="x-circle" wire:click="openReverse" data-test="entry-reverse">Cancelar entrada</flux:button>
            @endif
            <flux:button icon="printer" x-on:click="$dispatch('print-ticket', { url: @js(route('entries.ticket', [$entry, 'auto' => 1])) })" data-test="entry-print">Ticket</flux:button>
            <flux:button :href="route('entries.export', [$entry, 'pdf'])" icon="document-arrow-down">PDF</flux:button>
            <flux:button :href="route('entries.export', [$entry, 'xlsx'])" icon="table-cells">Excel</flux:button>
            <flux:button :href="route('entries.index')" wire:navigate icon="arrow-left">Entradas</flux:button>
        </div>
    </x-page-header>

    @if ($entry->status === \App\Enums\DocumentStatus::Reversed)
        <div class="flex items-start gap-3 rounded-xl border border-red-300/60 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" data-test="entry-reversed">
            <flux:icon name="x-circle" class="mt-0.5 size-5 shrink-0" />
            <div>
                <div class="font-medium">Entrada cancelada</div>
                @if ($reversal)
                    Cancelada el {{ $reversal->created_at->format('d/m/Y H:i') }}{{ $reversal->user ? ' por '.$reversal->user->name : '' }}. Motivo: {{ $reversal->new_values['reason'] ?? '' }}
                @endif
            </div>
        </div>
    @endif

    @if ($corrections->isNotEmpty())
        <div class="rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10" data-test="entry-corrections">
            <div class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-200">
                <flux:icon name="pencil-square" class="size-5" />
                Esta entrada fue corregida {{ $corrections->count() }} {{ $corrections->count() === 1 ? 'vez' : 'veces' }}
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
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'sky', 'label' => 'Cajas', 'value' => number_format($entry->total_boxes), 'hint' => number_format($stock->boxes).' disponibles ahora'],
            ['key' => 'kg', 'icon' => 'scale', 'color' => 'amber', 'label' => 'Peso total', 'value' => number_format((float) $entry->total_weight_kg, 3).' kg', 'hint' => number_format((float) $stock->kg, 3).' kg en existencia'],
        ];

        if ($showCosts) {
            $cards[] = ['key' => 'cost', 'icon' => 'banknotes', 'color' => 'emerald', 'label' => 'Importe', 'value' => '$'.number_format((float) $entry->total_cost_mxn, 2), 'hint' => 'MXN'];
        }
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 {{ $showCosts ? 'xl:grid-cols-3' : '' }}">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entry-card-{{ $card['key'] }}">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-{{ $card['color'] }}-500/10 text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-300">
                    <flux:icon :name="$card['icon']" class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $card['label'] }}</div>
                    <div class="truncate text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $card['value'] }}</div>
                    <div class="text-xs text-zinc-500">{{ $card['hint'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex items-start gap-3">
                <flux:icon name="calendar-days" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Fecha y hora</div><div class="font-medium">{{ $entry->occurred_at->format('d/m/Y H:i') }}</div></div>
            </div>
            <div class="flex items-start gap-3">
                <flux:icon name="building-storefront" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Cámara de destino</div><div class="font-medium">{{ $chamber->name }}</div><div class="text-xs text-zinc-500">{{ $chamber->branch->name }}</div></div>
            </div>
            <div class="flex items-start gap-3">
                <flux:icon name="user" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Registró</div><div class="font-medium">{{ $registeredBy->name }}</div></div>
            </div>
            <div class="flex items-start gap-3">
                <flux:icon name="check-badge" class="mt-0.5 size-5 text-zinc-400" />
                <div><div class="text-xs uppercase tracking-wide text-zinc-500">Estado</div><flux:badge size="sm" :color="$entry->status->value === 'confirmed' ? 'green' : 'red'">{{ $entry->status->label() }}</flux:badge></div>
            </div>
        </div>
        @if ($entry->notes)
            <div class="mt-5 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900/40">
                <div class="mb-0.5 text-xs uppercase tracking-wide text-zinc-500">Observaciones</div>
                {{ $entry->notes }}
            </div>
        @endif
    </div>

    <div class="space-y-2">
        <flux:heading size="lg">Grupos de la entrada</flux:heading>
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entry-groups-table">
            <flux:table container:class="overflow-x-auto">
                <flux:table.columns>
                    <flux:table.column>Producto</flux:table.column>
                    <flux:table.column align="end">Cajas</flux:table.column>
                    <flux:table.column align="end">Peso por caja</flux:table.column>
                    <flux:table.column align="end">Costo por caja</flux:table.column>
                    <flux:table.column align="end">Peso total</flux:table.column>
                    @if ($showCosts)
                        <flux:table.column align="end">Importe</flux:table.column>
                    @endif
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($items as $item)
                        <flux:table.row :key="$item->id">
                            <flux:table.cell><div class="font-medium">{{ $item->product->name }}</div><div class="text-xs text-zinc-500">{{ $item->product->category->name }}</div></flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($item->boxes_count) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $item->weight_per_box_kg }} kg</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $item->unit_cost_mxn, 2) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $item->total_weight_kg }} kg</flux:table.cell>
                            @if ($showCosts)
                                <flux:table.cell align="end" class="font-medium tabular-nums">${{ number_format((float) $item->total_cost_mxn, 2) }}</flux:table.cell>
                            @endif
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <div class="space-y-2">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:heading size="lg">Cajas que ingresaron</flux:heading>
            <div class="w-full sm:w-80">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por producto o código de barras" clearable data-test="entry-box-search" />
            </div>
        </div>
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entry-boxes-table">
            <flux:table :paginate="$boxes" container:class="overflow-x-auto">
                <flux:table.columns>
                    <flux:table.column>Código de barras</flux:table.column>
                    <flux:table.column>Producto</flux:table.column>
                    <flux:table.column align="end">Peso inicial</flux:table.column>
                    <flux:table.column align="end">Peso actual</flux:table.column>
                    <flux:table.column>Ubicación actual</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($boxes as $box)
                        <flux:table.row :key="$box->id">
                            <flux:table.cell class="font-mono text-xs">{{ $box->barcode }}</flux:table.cell>
                            <flux:table.cell>{{ $box->product->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $box->initial_weight_kg }} kg</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $box->current_weight_kg }} kg</flux:table.cell>
                            <flux:table.cell>{{ $box->currentChamber?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$box->status->color()">{{ $box->status->label() }}</flux:badge>
                                @if ($box->voided_at)
                                    <flux:badge size="sm" color="zinc">Anulada por corrección</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" :href="route('boxes.show', $box)" wire:navigate>Historial</flux:button>
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

    @if ($canReverse)
        <flux:modal name="reverse-entry" class="w-[calc(100vw-2rem)] md:w-[28rem]">
            <form wire:submit="reverse" class="space-y-5" data-test="entry-reverse-form">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-500/10 text-red-600 dark:text-red-300">
                        <flux:icon name="x-circle" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg">Cancelar entrada {{ $entry->reference }}</flux:heading>
                        <flux:text class="text-sm">Las {{ number_format($entry->total_boxes) }} cajas salen del inventario y la entrada queda marcada como cancelada. Solo es posible si ninguna caja se ha movido; el registro se conserva en el historial.</flux:text>
                    </div>
                </div>
                <flux:error name="operation" />
                <flux:textarea wire:model="reverseReason" label="Motivo" rows="3" placeholder="Por qué se cancela esta entrada." required />
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Volver</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="danger" icon="x-circle" class="w-full sm:w-auto">Cancelar entrada</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($canEdit)
        <flux:modal name="edit-entry" class="w-[calc(100vw-2rem)] md:w-[46rem]">
            <form
                wire:submit="saveEdit"
                class="space-y-5"
                data-test="entry-edit-form"
                x-data="{
                    orig: @js($items->mapWithKeys(fn ($item) => [$item->id => ['boxes' => (int) $item->boxes_count, 'weight' => (float) $item->weight_per_box_kg, 'cost' => (float) $item->unit_cost_mxn]])->all()),
                    boxes(id) { return parseInt($wire.editItems?.[id]?.boxes_count) || 0; },
                    weight(id) { const n = parseFloat($wire.editItems?.[id]?.weight_per_box_kg); return isNaN(n) ? 0 : n; },
                    cost(id) { const n = parseFloat($wire.editItems?.[id]?.unit_cost_mxn); return isNaN(n) ? 0 : n; },
                    amount(id) { return Math.round(this.boxes(id) * this.cost(id) * 100) / 100; },
                    get totalAmount() { return Math.round(Object.keys(this.orig).reduce((sum, id) => sum + this.amount(id), 0) * 100) / 100; },
                    get origAmount() { return Math.round(Object.values(this.orig).reduce((sum, row) => sum + row.boxes * row.cost, 0) * 100) / 100; },
                    money(value) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value); },
                    delta(id) { return this.boxes(id) - this.orig[id].boxes; },
                    kg(id) { return Math.round(this.boxes(id) * this.weight(id) * 1000) / 1000; },
                    get totalBoxes() { return Object.keys(this.orig).reduce((sum, id) => sum + this.boxes(id), 0); },
                    get totalKg() { return Math.round(Object.keys(this.orig).reduce((sum, id) => sum + this.kg(id), 0) * 1000) / 1000; },
                    get origBoxes() { return Object.values(this.orig).reduce((sum, row) => sum + row.boxes, 0); },
                    get origKg() { return Math.round(Object.values(this.orig).reduce((sum, row) => sum + row.boxes * row.weight, 0) * 1000) / 1000; },
                    fmt(value) { return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 3 }).format(value); },
                    signed(value) { return (value > 0 ? '+' : '') + this.fmt(value); },
                }"
            >
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                        <flux:icon name="pencil-square" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg">Corregir entrada {{ $entry->reference }}</flux:heading>
                        <flux:text class="text-sm">Ajusta los datos capturados y explica el motivo de la corrección.</flux:text>
                    </div>
                </div>

                <div class="flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-sm text-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                    <flux:icon name="information-circle" class="mt-0.5 size-4 shrink-0" />
                    <div class="space-y-1">
                        <div>El peso, el costo y la cantidad de cajas solo se pueden cambiar mientras las cajas del grupo sigan intactas en la cámara (sin salidas, transferencias ni ajustes).</div>
                        <div>Si quitas cajas se dan de baja como anuladas; si agregas, se generan códigos nuevos.</div>
                    </div>
                </div>

                <flux:error name="operation" />

                <div class="max-h-[55vh] space-y-5 -mx-1.5 overflow-y-auto p-1.5">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                            <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">1</span>
                            Datos generales
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="editDate" type="datetime-local" label="Fecha y hora" />
                            <flux:input wire:model="editNotes" label="Observaciones" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                            <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">2</span>
                            Grupos de la entrada
                        </div>

                        @foreach ($items as $item)
                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="edit-item-{{ $item->id }}">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $item->product->name }}</div>
                                        <div class="text-xs text-zinc-500">{{ $item->product->category->name }} · actualmente {{ number_format($item->boxes_count) }} cajas de {{ rtrim(rtrim(number_format((float) $item->weight_per_box_kg, 3, '.', ','), '0'), '.') }} kg</div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span x-show="delta({{ $item->id }}) !== 0" x-cloak class="rounded-md px-2 py-0.5 text-xs font-medium"
                                            :class="delta({{ $item->id }}) > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300'"
                                            x-text="signed(delta({{ $item->id }})) + ' cajas'"></span>
                                        <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200" x-text="fmt(kg({{ $item->id }})) + ' kg'"></span>
                                    </div>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <flux:input wire:model="editItems.{{ $item->id }}.boxes_count" type="number" min="1" label="Cajas" />
                                    <flux:input wire:model="editItems.{{ $item->id }}.weight_per_box_kg" inputmode="decimal" label="Peso por caja (kg)" />
                                    @if ($showCosts)
                                        <flux:input wire:model="editItems.{{ $item->id }}.unit_cost_mxn" inputmode="decimal" label="Costo por caja (MXN)" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-center {{ $showCosts ? 'sm:grid-cols-3' : '' }}" data-test="edit-entry-totals">
                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Cajas</div>
                            <div class="text-xl font-semibold tabular-nums" x-text="fmt(totalBoxes)"></div>
                            <div class="text-xs text-zinc-500">antes <span x-text="fmt(origBoxes)"></span></div>
                        </div>
                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="text-xs uppercase tracking-wide text-zinc-500">Peso total</div>
                            <div class="text-xl font-semibold tabular-nums"><span x-text="fmt(totalKg)"></span> kg</div>
                            <div class="text-xs text-zinc-500">antes <span x-text="fmt(origKg)"></span> kg</div>
                        </div>
                        @if ($showCosts)
                            <div class="col-span-2 rounded-lg border border-zinc-200 p-3 sm:col-span-1 dark:border-zinc-700" data-test="edit-entry-amount">
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Importe</div>
                                <div class="text-xl font-semibold tabular-nums" x-text="money(totalAmount)"></div>
                                <div class="text-xs text-zinc-500">antes <span x-text="money(origAmount)"></span></div>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                            <span class="flex size-6 items-center justify-center rounded-full bg-accent/10 text-xs font-semibold text-accent-content">3</span>
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
