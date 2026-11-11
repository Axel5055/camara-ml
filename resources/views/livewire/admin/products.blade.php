<section class="w-full space-y-6">
    <x-page-header icon="shopping-bag" title="Productos" description="Catálogo de productos y sus códigos.">
        @if ($canCreate)
            <flux:button variant="primary" icon="plus" wire:click="create" data-test="product-create">Nuevo producto</flux:button>
        @endif
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="products-total">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.shopping-bag class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Productos</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['total']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="products-active">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                <flux:icon.check-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Activos</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['active']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="products-inactive">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-zinc-500/10 text-zinc-600 dark:text-zinc-300">
                <flux:icon.pause-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Inactivos</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['inactive']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="products-stocked">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.archive-box class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Con existencias</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['stocked']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por nombre, código o información adicional" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="categoryFilter" label="Categoría">
                    <flux:select.option value="">Todas las categorías</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="status" label="Estado">
                    <flux:select.option value="all">Todos</flux:select.option>
                    <flux:select.option value="active">Activos</flux:select.option>
                    <flux:select.option value="inactive">Inactivos</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="products-table">
        <flux:table :paginate="$products" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Producto</flux:table.column>
                <flux:table.column>Categoría</flux:table.column>
                <flux:table.column align="end">Cajas disponibles</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                @if ($canManage)
                    <flux:table.column></flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($products as $product)
                    <flux:table.row :key="$product->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $product->code }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $product->name }}</div>
                            @if ($product->description)
                                <div class="max-w-xs truncate text-xs text-zinc-500">{{ $product->description }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $product->category->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($product->stocked_boxes_count) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$product->is_active ? 'green' : 'zinc'">{{ $product->is_active ? 'Activo' : 'Inactivo' }}</flux:badge>
                        </flux:table.cell>
                        @if ($canManage)
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $product->id }})">Editar</flux:button>
                                    <flux:button size="sm" variant="ghost" :icon="$product->is_active ? 'pause' : 'play'" wire:click="toggle({{ $product->id }})">{{ $product->is_active ? 'Desactivar' : 'Activar' }}</flux:button>
                                    @if ($canDelete && ! $product->has_boxes && ! $product->has_entry_items)
                                        <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600 dark:text-red-400" wire:click="confirmDelete({{ $product->id }})" data-test="product-delete-{{ $product->id }}">Eliminar</flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canManage ? 6 : 5 }}" class="py-10 text-center text-zinc-500">No hay productos que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="product-delete" class="w-full md:w-[28rem]">
        <div class="space-y-5" data-test="product-delete-alert">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-500/15 text-red-600 dark:text-red-400">
                    <flux:icon name="trash" class="size-5" />
                </span>
                <div>
                    <flux:heading size="lg">Eliminar producto</flux:heading>
                    <flux:text class="mt-1">Se eliminará <strong class="text-zinc-900 dark:text-white">{{ $deletingProduct?->name }}</strong> ({{ $deletingProduct?->code }}) del catálogo. Solo es posible porque no tiene movimientos; esta acción no se puede deshacer.</flux:text>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" class="w-full sm:w-auto">Cancelar</flux:button></flux:modal.close>
                <flux:button variant="danger" class="w-full sm:w-auto" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" data-test="product-delete-confirm">Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="similar-products" class="w-full md:w-[30rem]">
        <div class="space-y-5" data-test="similar-products-alert">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400">
                    <flux:icon name="exclamation-triangle" class="size-5" />
                </span>
                <div>
                    <flux:heading size="lg">¿Seguro que quieres crearlo?</flux:heading>
                    <flux:text class="mt-1">Ya existe {{ count($similar) === 1 ? 'un producto parecido' : 'productos parecidos' }} a <strong class="text-zinc-900 dark:text-white">{{ $name }}</strong>. Revisa que no sea el mismo o que el nombre no esté mal escrito.</flux:text>
                </div>
            </div>

            <ul class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($similar as $match)
                    <li class="flex items-center justify-between gap-3 px-4 py-3" data-test="similar-product">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $match['name'] }}</div>
                            <div class="text-xs text-zinc-500">{{ $match['category'] }}{{ $match['is_active'] ? '' : ' · Inactivo' }}</div>
                        </div>
                        <span class="shrink-0 rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $match['code'] }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" class="w-full sm:w-auto">Revisar nombre</flux:button></flux:modal.close>
                <flux:button variant="primary" class="w-full sm:w-auto" wire:click="confirmSimilar" wire:loading.attr="disabled" wire:target="confirmSimilar" data-test="confirm-similar-product">Crear de todos modos</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="product-form" class="w-full md:w-[34rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Editar producto' : 'Nuevo producto' }}</flux:heading>
                <flux:text class="mt-1">{{ $editingId ? 'Actualiza los datos del producto.' : 'Registra un producto del catálogo. El código se genera solo a partir del nombre.' }}</flux:text>
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Datos del producto</div>

                <flux:input wire:model.live.debounce.300ms="name" label="Nombre" icon="shopping-bag" class="uppercase" placeholder="Ej. POLLO ENTERO" required data-test="product-name" />

                <flux:select wire:model="category_id" label="Categoría">
                    <flux:select.option value="">Selecciona una categoría</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($editingId)
                    <flux:input wire:model="code" label="Código (prefijo del código de barras)" icon="qr-code" class="font-mono" :disabled="$codeLocked" required />
                    @if ($codeLocked)
                        <flux:text class="-mt-2 text-xs">El código ya no se puede cambiar porque el producto tiene cajas registradas.</flux:text>
                    @endif
                @else
                    <flux:field>
                        <flux:label>Código (prefijo del código de barras)</flux:label>
                        <flux:input wire:model="code" icon="qr-code" readonly placeholder="Se genera con el nombre" class="font-mono" data-test="product-code" />
                        <flux:description>Se sugiere solo a partir del nombre y no se puede editar.</flux:description>
                        <flux:error name="code" />
                    </flux:field>
                @endif
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Detalles</div>

                <flux:textarea wire:model="description" label="Información adicional" rows="3" placeholder="Presentación, origen, notas…" />
            </div>

            @if ($canManage)
                <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div>
                        <div class="text-sm font-medium text-zinc-900 dark:text-white">Producto activo</div>
                        <flux:text class="text-xs">Un producto inactivo no se puede usar en nuevas entradas.</flux:text>
                    </div>
                    <flux:switch wire:model="is_active" />
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
