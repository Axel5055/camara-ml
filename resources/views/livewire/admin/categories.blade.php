<section class="w-full space-y-6">
    <x-page-header icon="tag" title="Categorías" description="Agrupan los productos del catálogo.">
        @if ($canManage)
            <flux:button variant="primary" icon="plus" wire:click="create">Nueva categoría</flux:button>
        @endif
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="categories-total">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.tag class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Categorías</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['total']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="categories-active">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                <flux:icon.check-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Activas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['active']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="categories-inactive">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-zinc-500/10 text-zinc-600 dark:text-zinc-300">
                <flux:icon.pause-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Inactivas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['inactive']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="categories-products">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.shopping-bag class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Productos</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['products']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" label="Buscar" icon="magnifying-glass" clearable placeholder="Nombre de la categoría" />
            </div>
            <flux:select wire:model.live="status" label="Estado">
                <flux:select.option value="all">Todas</flux:select.option>
                <flux:select.option value="active">Activas</flux:select.option>
                <flux:select.option value="inactive">Inactivas</flux:select.option>
            </flux:select>
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="categories-table">
        <flux:table :paginate="$categories" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column align="end">Productos</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                @if ($canManage)
                    <flux:table.column></flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($categories as $category)
                    <flux:table.row :key="$category->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $category->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($category->products_count) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$category->is_active ? 'green' : 'zinc'">{{ $category->is_active ? 'Activa' : 'Inactiva' }}</flux:badge>
                        </flux:table.cell>
                        @if ($canManage)
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $category->id }})">Editar</flux:button>
                                    <flux:button size="sm" variant="ghost" :icon="$category->is_active ? 'pause' : 'play'" wire:click="toggle({{ $category->id }})">{{ $category->is_active ? 'Desactivar' : 'Activar' }}</flux:button>
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canManage ? 4 : 3 }}" class="py-10 text-center text-zinc-500">No hay categorías que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="category-form" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Editar categoría' : 'Nueva categoría' }}</flux:heading>
            <flux:input wire:model="name" label="Nombre" class="uppercase" required />
            <flux:switch wire:model="is_active" label="Activa" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
