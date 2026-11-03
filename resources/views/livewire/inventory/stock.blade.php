<section class="w-full space-y-6">
    <x-page-header icon="archive-box" title="Existencias" description="Consulta las cajas disponibles, su peso y su valor." />

    <div class="grid gap-4 lg:grid-cols-3">
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
    </div>

    <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por código de barras o nombre de producto" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="chamberId" label="Cámara" placeholder="Todas las cámaras">
                <flux:select.option value="">Todas las cámaras</flux:select.option>
                @foreach ($chambers as $chamber)
                    <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="categoryId" label="Categoría" placeholder="Todas las categorías">
                <flux:select.option value="">Todas las categorías</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="productId" label="Producto" placeholder="Todos los productos">
                <flux:select.option value="">Todos los productos</flux:select.option>
                @foreach ($products as $product)
                    <flux:select.option :value="$product->id">{{ $product->name }}</flux:select.option>
                @endforeach
            </flux:select>
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
        </div>
    </div>

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
                        <flux:table.cell align="end">
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
</section>
