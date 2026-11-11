<section class="w-full space-y-6">
    <x-page-header icon="building-storefront" title="Sucursales" description="Sucursales donde opera la empresa.">
        @if ($canManage)
            <flux:button variant="primary" icon="plus" wire:click="create">Nueva sucursal</flux:button>
        @endif
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="branches-total">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.building-storefront class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Sucursales</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['total']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="branches-active">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                <flux:icon.check-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Activas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['active']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="branches-inactive">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-zinc-500/10 text-zinc-600 dark:text-zinc-300">
                <flux:icon.pause-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Inactivas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['inactive']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="branches-chambers">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.cube class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cámaras</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['chambers']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" label="Buscar" icon="magnifying-glass" clearable placeholder="Nombre, código o dirección" />
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

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="branches-table">
        <flux:table :paginate="$branches" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column align="end">Cámaras</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                @if ($canManage)
                    <flux:table.column></flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($branches as $branch)
                    <flux:table.row :key="$branch->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $branch->code }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $branch->name }}</div>
                            @if ($branch->address)
                                <div class="text-xs text-zinc-500">{{ $branch->address }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($branch->chambers_count) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$branch->is_active ? 'green' : 'zinc'">{{ $branch->is_active ? 'Activa' : 'Inactiva' }}</flux:badge>
                        </flux:table.cell>
                        @if ($canManage)
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $branch->id }})">Editar</flux:button>
                                    <flux:button size="sm" variant="ghost" :icon="$branch->is_active ? 'pause' : 'play'" wire:click="toggle({{ $branch->id }})">{{ $branch->is_active ? 'Desactivar' : 'Activar' }}</flux:button>
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canManage ? 5 : 4 }}" class="py-10 text-center text-zinc-500">No hay sucursales que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="branch-form" class="w-full md:w-[30rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Editar sucursal' : 'Nueva sucursal' }}</flux:heading>
                <flux:text class="mt-1">{{ $editingId ? 'Actualiza los datos de la sucursal.' : 'Registra una sucursal para poder asignarle cámaras y enviarle salidas.' }}</flux:text>
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Datos de la sucursal</div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="code" label="Código" icon="hashtag" class="font-mono uppercase" placeholder="CENTRO" required />
                    <div class="sm:col-span-2">
                        <flux:input wire:model="name" label="Nombre" icon="building-storefront" placeholder="Ej. Sucursal Centro" required />
                    </div>
                </div>

                <flux:input wire:model="address" label="Dirección" icon="map-pin" placeholder="Calle, número y colonia (opcional)" />
            </div>

            <x-active-switch title="Sucursal activa" description="Una sucursal inactiva ya no aparece como destino de salidas." />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" type="button">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
