<section class="w-full space-y-6">
    <x-page-header icon="users" title="Usuarios" description="Cuentas de acceso al sistema y sus roles.">
        <flux:button variant="primary" icon="plus" wire:click="create">Nuevo usuario</flux:button>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="users-total">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.users class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Usuarios</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['total']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="users-super_admin">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-300">
                <flux:icon.shield-check class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Super administradores</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['super_admin']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="users-admin">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.eye class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Administradores</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['admin']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="users-manager">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-300">
                <flux:icon.building-storefront class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Gerentes</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['manager']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="users-warehouse">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
                <flux:icon.archive-box class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Almacenistas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['warehouse']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="users-inactive">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-zinc-500/10 text-zinc-600 dark:text-zinc-300">
                <flux:icon.pause-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Inactivos</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['inactive']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por nombre o correo" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="roleFilter" label="Rol">
                    <flux:select.option value="all">Todos los roles</flux:select.option>
                    @foreach ($roles as $roleOption)
                        <flux:select.option :value="$roleOption->value">{{ $roleOption->label() }}</flux:select.option>
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

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="users-table">
        <flux:table :paginate="$users" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Usuario</flux:table.column>
                <flux:table.column>Rol</flux:table.column>
                <flux:table.column>Sucursal / cámaras</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row :key="$user->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" />
                                <div class="min-w-0">
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $user->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($user->role->value) { 'super_admin' => 'purple', 'admin' => 'sky', 'manager' => 'teal', default => 'amber' }">{{ $user->role->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($user->role->value === 'manager')
                                <div class="flex flex-wrap gap-1" data-test="manager-scope">
                                    @foreach ($user->managedBranches as $managedBranch)
                                        <flux:badge size="sm" color="teal">{{ $managedBranch->name }} (completa)</flux:badge>
                                    @endforeach
                                    @foreach ($user->chambers as $chamber)
                                        <flux:badge size="sm" color="zinc">{{ $chamber->name }}</flux:badge>
                                    @endforeach
                                </div>
                            @elseif ($user->branch)
                                <div>{{ $user->branch->name }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($user->chambers as $chamber)
                                        <flux:badge size="sm" color="zinc">{{ $chamber->name }}</flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-zinc-400">{{ $user->role->value === 'warehouse' ? '—' : 'Todas las cámaras' }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$user->is_active ? 'green' : 'zinc'">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $user->id }})">Editar</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">No hay usuarios que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="user-form" class="w-full md:w-[38rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Editar usuario' : 'Nuevo usuario' }}</flux:heading>
                <flux:text class="mt-1">{{ $editingId ? 'Actualiza los datos, el rol y el alcance de la cuenta.' : 'Crea una cuenta y define qué puede ver o hacer.' }}</flux:text>
            </div>

            <flux:error name="operation" />

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Datos de la cuenta</div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" label="Nombre" icon="user" required />
                    <flux:input wire:model="email" label="Correo" type="email" icon="envelope" required />
                </div>

                <div
                    x-data="{
                        suggested: '',
                        copied: false,
                        suggest() {
                            const pools = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '!@#$%&*?-_'];
                            const rand = (max) => crypto.getRandomValues(new Uint32Array(1))[0] % max;
                            const pick = (chars) => chars[rand(chars.length)];
                            const all = pools.join('');
                            const chars = pools.map(pick);
                            while (chars.length < 14) { chars.push(pick(all)); }
                            for (let i = chars.length - 1; i > 0; i--) {
                                const j = rand(i + 1);
                                [chars[i], chars[j]] = [chars[j], chars[i]];
                            }
                            this.suggested = chars.join('');
                            this.copied = false;
                            $wire.password = this.suggested;
                        },
                        async copy() {
                            try { await navigator.clipboard.writeText(this.suggested); this.copied = true; } catch (e) {}
                        },
                    }"
                    class="space-y-2"
                >
                    <flux:input wire:model="password" label="{{ $editingId ? 'Nueva contraseña (opcional)' : 'Contraseña' }}" type="password" icon="key" viewable autocomplete="new-password" data-test="user-password" />

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button type="button" size="sm" variant="ghost" icon="sparkles" x-on:click="suggest()" data-test="suggest-password">Sugerir contraseña</flux:button>

                        <template x-if="suggested !== '' && $wire.password === suggested">
                            <div class="flex min-w-0 items-center gap-2 rounded-lg bg-zinc-100 px-2 py-1 dark:bg-zinc-700/60" data-test="suggested-password">
                                <code class="truncate font-mono text-sm text-zinc-800 dark:text-zinc-100" x-text="suggested"></code>
                                <flux:button type="button" size="xs" variant="subtle" icon="clipboard" x-on:click="copy()">
                                    <span x-text="copied ? 'Copiada' : 'Copiar'"></span>
                                </flux:button>
                            </div>
                        </template>
                    </div>
                    <flux:text class="text-xs">Mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos. Cópiala para entregársela al usuario.</flux:text>
                </div>
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Rol y acceso</div>

                <flux:select wire:model.live="role" label="Rol">
                    @foreach ($roles as $roleOption)
                        <flux:select.option :value="$roleOption->value">{{ $roleOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:text class="text-xs">{{ \App\Enums\UserRole::tryFrom($role)?->description() }}</flux:text>

                @if ($role === 'warehouse')
                    <flux:select wire:model.live="branch_id" label="Sucursal">
                        <flux:select.option value="">Selecciona una sucursal</flux:select.option>
                        @foreach ($branches as $branch)
                            <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($branchChambers->isNotEmpty())
                        <flux:checkbox.group wire:model="chamberIds" label="Cámaras autorizadas">
                            <div class="grid max-h-48 gap-2 overflow-y-auto rounded-lg bg-zinc-50 p-3 sm:grid-cols-2 dark:bg-zinc-900/40">
                                @foreach ($branchChambers as $chamber)
                                    <flux:checkbox :value="$chamber->id" :label="$chamber->name" wire:key="chamber-{{ $chamber->id }}" />
                                @endforeach
                            </div>
                        </flux:checkbox.group>
                    @endif
                @endif

                @if ($role === 'manager')
                    <flux:checkbox.group wire:model="branchIds" label="Sucursales (con todas sus cámaras)">
                        <div class="grid max-h-40 gap-2 overflow-y-auto rounded-lg bg-zinc-50 p-3 sm:grid-cols-2 dark:bg-zinc-900/40">
                            @foreach ($branches as $branch)
                                <flux:checkbox :value="$branch->id" :label="$branch->name" wire:key="manager-branch-{{ $branch->id }}" />
                            @endforeach
                        </div>
                    </flux:checkbox.group>

                    <flux:checkbox.group wire:model="chamberIds" label="Cámaras individuales">
                        <div class="grid max-h-48 gap-2 overflow-y-auto rounded-lg bg-zinc-50 p-3 sm:grid-cols-2 dark:bg-zinc-900/40">
                            @foreach ($allChambers as $chamber)
                                <flux:checkbox :value="$chamber->id" :label="$chamber->branch->name.' · '.$chamber->name" wire:key="manager-chamber-{{ $chamber->id }}" />
                            @endforeach
                        </div>
                    </flux:checkbox.group>
                @endif
            </div>

            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <div class="text-sm font-medium text-zinc-900 dark:text-white">Cuenta activa</div>
                    <flux:text class="text-xs">Una cuenta inactiva no puede iniciar sesión.</flux:text>
                </div>
                <flux:switch wire:model="is_active" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
