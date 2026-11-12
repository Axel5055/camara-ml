<section class="w-full space-y-6">
    <x-page-header icon="archive-box" title="Existencias" :description="$showCosts ? 'Consulta las cajas disponibles, su peso y su valor.' : 'Consulta las cajas disponibles, su peso y su precio por caja.'" />

    <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="stock-view-toggle">
        <flux:button size="sm" :variant="$isSummary ? 'ghost' : 'primary'" icon="queue-list" wire:click="showView('boxes')">Por caja</flux:button>
        <flux:button size="sm" :variant="$isSummary ? 'primary' : 'ghost'" icon="building-storefront" wire:click="showView('summary')" data-test="stock-view-summary">Concentrado por cámara</flux:button>
    </div>

    <div class="grid gap-4 {{ $showCosts ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }}">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="stock-boxes">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.archive-box class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cajas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($summary->boxes) }}</div>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="stock-weight">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.scale class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Peso actual</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format((float) $summary->kg, 3) }} <span class="text-sm font-normal text-zinc-500">kg</span></div>
            </div>
        </div>

        @if ($showCosts)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="stock-value">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                    <flux:icon.banknotes class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Valor total</div>
                    <div class="text-2xl font-semibold text-zinc-900 dark:text-white">${{ number_format((float) $summary->value, 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
                    <div class="text-xs text-zinc-500">a costo, según el peso actual</div>
                </div>
            </div>
        @endif
    </div>

    <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por código de barras o nombre de producto" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="chamberId" label="Cámara">
                <flux:select.option value="">Todas las cámaras</flux:select.option>
                @foreach ($chambers as $chamber)
                    <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="categoryId" label="Categoría">
                <flux:select.option value="">Todas las categorías</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <x-product-search :products="$products" placeholder="Todos los productos" emptyMessage="No hay productos con ese nombre." data-test="stock-product-search" wire:key="stock-product-search-{{ $categoryId }}" />
            @unless ($isSummary)
            <flux:select wire:model.live="status" label="Estado">
                <flux:select.option value="available">Disponibles</flux:select.option>
                <flux:select.option value="partial">Parciales</flux:select.option>
                @if ($canSeeAllStatuses)
                    <flux:select.option value="in_transit">En tránsito</flux:select.option>
                    <flux:select.option value="dispatched">Entregadas</flux:select.option>
                    <flux:select.option value="depleted">Agotadas</flux:select.option>
                    <flux:select.option value="written_off">Baja</flux:select.option>
                    <flux:select.option value="all">Todas</flux:select.option>
                @endif
            </flux:select>
            @endunless
        </div>
    </div>

    @if ($isSummary)
        <div class="space-y-6" data-test="stock-summary">
            @forelse ($summaries as $block)
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-800" wire:key="summary-{{ $block['chamber']->id }}" data-test="stock-summary-chamber">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $block['chamber']->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $block['chamber']->branch->name }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <flux:badge size="sm" color="indigo">{{ number_format($block['boxes']) }} cajas</flux:badge>
                            <flux:badge size="sm" color="sky">{{ number_format($block['kg'], 3) }} kg</flux:badge>
                            @if ($showCosts)
                                <flux:badge size="sm" color="emerald">${{ number_format($block['value'], 2) }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    <div class="p-2">
                        <flux:table container:class="overflow-x-auto">
                            <flux:table.columns>
                                <flux:table.column>Producto</flux:table.column>
                                <flux:table.column align="end">Cajas</flux:table.column>
                                <flux:table.column align="end">Peso total (kg)</flux:table.column>
                                <flux:table.column align="end">Peso prom. por caja</flux:table.column>
                                @if ($showCosts)
                                    <flux:table.column align="end">Costo total (MXN)</flux:table.column>
                                    <flux:table.column align="end">Costo prom. por kg</flux:table.column>
                                @endif
                                @if ($canAdjust)
                                    <flux:table.column></flux:table.column>
                                @endif
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($block['rows'] as $row)
                                    <flux:table.row :key="$block['chamber']->id.'-'.$row->product_id">
                                        <flux:table.cell>
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ $row->product->name }}</div>
                                            @if ($row->partial_boxes > 0)
                                                <flux:badge size="sm" color="amber" class="mt-1">{{ $row->partial_boxes }} parciales</flux:badge>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell align="end" class="tabular-nums font-medium">{{ number_format($row->boxes) }}</flux:table.cell>
                                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $row->kg, 3) }}</flux:table.cell>
                                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $row->kg / $row->boxes, 3) }}</flux:table.cell>
                                        @if ($showCosts)
                                            <flux:table.cell align="end" class="tabular-nums font-medium">${{ number_format((float) $row->value, 2) }}</flux:table.cell>
                                            <flux:table.cell align="end" class="tabular-nums">${{ number_format($row->kg > 0 ? (float) $row->value / (float) $row->kg : 0, 2) }}</flux:table.cell>
                                        @endif
                                        @if ($canAdjust)
                                            <flux:table.cell align="end">
                                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openAdjust({{ $block['chamber']->id }}, {{ $row->product_id }})" data-test="stock-adjust">Ajustar</flux:button>
                                            </flux:table.cell>
                                        @endif
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 bg-white p-10 text-center text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">No hay existencias que coincidan con los filtros.</div>
            @endforelse
        </div>
    @else
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="stock-table">
        <flux:table :paginate="$boxes" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Producto</flux:table.column>
                <flux:table.column>Cámara</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column align="end">Precio (MXN)</flux:table.column>
                <flux:table.column>Entrada</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($boxes as $box)
                    <flux:table.row :key="$box->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $box->barcode }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $box->product->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $box->category->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($box->currentChamber)
                                <div>{{ $box->currentChamber->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $box->currentChamber->branch->name }}</div>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $box->current_weight_kg }}</div>
                            @if ($box->isPartial())
                                <flux:badge size="sm" color="amber" class="mt-1">parcial · de {{ $box->initial_weight_kg }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">${{ number_format((float) $box->unit_cost_mxn, 2) }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $box->entered_at->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" inset="top bottom" :color="$box->status->color()">{{ $box->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @if ($canAdjust && $box->status === \App\Enums\BoxStatus::Available)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openEditBox({{ $box->id }})" data-test="box-edit">Editar</flux:button>
                            @endif
                            <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" :href="route('boxes.show', $box)" wire:navigate>Historial</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500">No hay cajas que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    @endif

    @if ($canAdjust)
        <flux:modal name="adjust-stock" class="w-full md:w-[36rem]">
            <form wire:submit="saveAdjust" class="space-y-6" data-test="stock-adjust-form">
                <div>
                    <flux:heading size="lg">Ajustar existencias</flux:heading>
                    <flux:text class="mt-1">{{ $adjustLabel }}</flux:text>
                </div>

                <flux:callout icon="information-circle" color="zinc">
                    <flux:callout.text>
                        Escribe los valores correctos. Si bajas el total se dan de baja las cajas más antiguas; si lo subes se crean cajas nuevas con el peso y costo indicados. El peso por caja solo se aplica a cajas completas; las parciales conservan el suyo.
                        @if ($adjustFigures && $adjustFigures['mixed_weights'])
                            Hay cajas con pesos distintos: el valor mostrado es el promedio y solo se aplica si lo cambias.
                        @endif
                    </flux:callout.text>
                </flux:callout>

                <flux:error name="operation" />

                <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Valores correctos</div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <flux:input wire:model="adjustBoxes" type="number" min="0" label="Cajas" icon="archive-box" />
                        <flux:input wire:model="adjustWeight" inputmode="decimal" label="Peso por caja (kg)" icon="scale" />
                        <flux:input wire:model="adjustCost" inputmode="decimal" label="Costo por kg (MXN)" icon="currency-dollar" />
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Justificación</div>
                    <flux:textarea wire:model="adjustReason" label="Motivo del ajuste" rows="2" placeholder="Qué se contó o se encontró distinto." required />
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close><flux:button variant="ghost" type="button">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check">Guardar ajuste</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="edit-box" class="w-full md:w-[32rem]">
            <form wire:submit="saveEditBox" class="space-y-6" data-test="box-edit-form">
                <div>
                    <flux:heading size="lg">Editar caja</flux:heading>
                    @if ($editBox)
                        <flux:text class="mt-1"><span class="font-mono">{{ $editBox->barcode }}</span> · {{ $editBox->product->name }}</flux:text>
                    @endif
                </div>

                <flux:callout icon="information-circle" color="zinc">
                    <flux:callout.text>
                        @if ($editBox && $editBox->isPartial())
                            Esta caja ya tuvo retiros parciales (peso inicial {{ $editBox->initial_weight_kg }} kg): solo cambia su peso actual, que no puede superar el inicial.
                        @else
                            La caja está completa: el nuevo peso sustituye también al inicial, como si se hubiera capturado bien.
                        @endif
                    </flux:callout.text>
                </flux:callout>

                <flux:error name="operation" />

                <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Datos de la caja</div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="editWeight" inputmode="decimal" label="Peso (kg)" icon="scale" />
                        <flux:input wire:model="editCost" inputmode="decimal" label="Costo por kg (MXN)" icon="currency-dollar" />
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Justificación</div>
                    <flux:textarea wire:model="editReason" label="Motivo del cambio" rows="2" placeholder="Qué estaba mal capturado." required />
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close><flux:button variant="ghost" type="button">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check">Guardar cambios</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
