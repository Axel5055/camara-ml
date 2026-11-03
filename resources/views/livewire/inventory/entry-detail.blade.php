<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrow-down-tray" :title="'Entrada ' . $entry->reference" description="Detalle del registro y de las cajas que ingresaron." mono>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canEdit)
                <flux:button variant="primary" icon="pencil-square" wire:click="openEdit" data-test="entry-edit">Corregir</flux:button>
            @endif
            <flux:button icon="printer" x-on:click="$dispatch('print-ticket', { url: @js(route('entries.ticket', [$entry, 'auto' => 1])) })" data-test="entry-print">Ticket</flux:button>
            <flux:button :href="route('entries.export', [$entry, 'pdf'])" icon="document-arrow-down">PDF</flux:button>
            <flux:button :href="route('entries.export', [$entry, 'xlsx'])" icon="table-cells">Excel</flux:button>
            <flux:button :href="route('entries.index')" wire:navigate icon="arrow-left">Entradas</flux:button>
        </div>
    </x-page-header>

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
                    @if ($showCosts)
                        <flux:table.column align="end">Costo por caja</flux:table.column>
                    @endif
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
                            @if ($showCosts)
                                <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $item->unit_cost_mxn, 2) }}</flux:table.cell>
                            @endif
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

    @if ($canEdit)
        <flux:modal name="edit-entry" class="md:w-[44rem]">
            <form wire:submit="saveEdit" class="space-y-5" data-test="entry-edit-form">
                <div>
                    <flux:heading size="lg">Corregir entrada {{ $entry->reference }}</flux:heading>
                    <flux:text class="mt-1">Cambia la fecha, las observaciones o los datos de cada grupo. El peso, el costo y la cantidad de cajas solo se pueden cambiar mientras las cajas del grupo sigan intactas en la cámara (sin salidas, transferencias ni ajustes). Si quitas cajas se dan de baja como anuladas; si agregas, se generan códigos nuevos.</flux:text>
                </div>

                <flux:error name="operation" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="editDate" type="datetime-local" label="Fecha y hora" />
                    <flux:input wire:model="editNotes" label="Observaciones" />
                </div>

                <div class="space-y-3">
                    @foreach ($items as $item)
                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="edit-item-{{ $item->id }}">
                            <div class="mb-2 text-sm font-medium text-zinc-900 dark:text-white">{{ $item->product->name }}</div>
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

                <flux:textarea wire:model="editReason" label="Motivo de la corrección" rows="2" placeholder="Qué se capturó mal y qué se corrige." required />
                <flux:error name="editReason" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Guardar corrección</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
