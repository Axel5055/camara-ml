<section class="w-full space-y-6">
    <x-page-header icon="shopping-bag" title="Productos" description="Catálogo de productos y sus códigos.">
        @if ($canManage)
            <flux:button variant="primary" icon="plus" wire:click="create">Nuevo producto</flux:button>
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
                <flux:select wire:model.live="categoryFilter" label="Categoría" placeholder="Todas las categorías">
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

    <flux:modal name="product-form" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Editar producto' : 'Nuevo producto' }}</flux:heading>
            <flux:select wire:model="category_id" label="Categoría" placeholder="Selecciona una categoría">
                <flux:select.option value="">Selecciona una categoría</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="code" label="Código (prefijo del código de barras)" :disabled="$codeLocked" required />
            <flux:input wire:model="name" label="Nombre" required />
            <flux:textarea wire:model="description" label="Información adicional" rows="2" />
            <flux:switch wire:model="is_active" label="Activo" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
