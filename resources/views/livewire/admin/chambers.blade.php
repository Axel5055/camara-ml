<section class="w-full space-y-6">
    <x-page-header icon="cube" title="Cámaras de refrigeración" description="Cámaras de refrigeración de cada sucursal.">
        @if ($canManage)
            <flux:button variant="primary" icon="plus" wire:click="create">Nueva cámara</flux:button>
        @endif
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="chambers-total">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.cube class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cámaras</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['total']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="chambers-active">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                <flux:icon.check-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Activas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['active']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="chambers-inactive">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-zinc-500/10 text-zinc-600 dark:text-zinc-300">
                <flux:icon.pause-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Inactivas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['inactive']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="chambers-boxes">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.archive-box class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cajas almacenadas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['boxes']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" label="Buscar" icon="magnifying-glass" clearable placeholder="Nombre o código de la cámara" />
            </div>
            <flux:select wire:model.live="branchFilter" label="Sucursal">
                <flux:select.option value="">Todas las sucursales</flux:select.option>
                @foreach ($branches as $branch)
                    <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                @endforeach
            </flux:select>
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

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="chambers-table">
        <flux:table :paginate="$chambers" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Código</flux:table.column>
                <flux:table.column>Cámara</flux:table.column>
                <flux:table.column>Sucursal</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                @if ($canManage)
                    <flux:table.column></flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($chambers as $chamber)
                    <flux:table.row :key="$chamber->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $chamber->code }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $chamber->name }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $chamber->branch->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($chamber->boxes_count) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$chamber->is_active ? 'green' : 'zinc'">{{ $chamber->is_active ? 'Activa' : 'Inactiva' }}</flux:badge>
                        </flux:table.cell>
                        @if ($canManage)
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $chamber->id }})">Editar</flux:button>
                                    <flux:button size="sm" variant="ghost" :icon="$chamber->is_active ? 'pause' : 'play'" wire:click="toggle({{ $chamber->id }})">{{ $chamber->is_active ? 'Desactivar' : 'Activar' }}</flux:button>
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canManage ? 6 : 5 }}" class="py-10 text-center text-zinc-500">No hay cámaras que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="chamber-form" class="w-full md:w-[30rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Editar cámara' : 'Nueva cámara' }}</flux:heading>
                <flux:text class="mt-1">{{ $editingId ? 'Actualiza los datos de la cámara.' : 'Registra una cámara de refrigeración dentro de una sucursal.' }}</flux:text>
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Datos de la cámara</div>

                <flux:select wire:model="branch_id" label="Sucursal">
                    <flux:select.option value="">Selecciona una sucursal</flux:select.option>
                    @foreach ($branches as $branch)
                        <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="code" label="Código" icon="hashtag" class="font-mono uppercase" placeholder="C1" required />
                    <div class="sm:col-span-2">
                        <flux:input wire:model="name" label="Nombre" icon="cube" placeholder="Ej. Cámara 1" required />
                    </div>
                </div>
            </div>

            <x-active-switch title="Cámara activa" description="No se pueden registrar entradas, salidas ni transferencias en una cámara inactiva." />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" type="button">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
