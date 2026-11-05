<section class="w-full space-y-6">
    <x-page-header icon="shield-check" title="Auditoría" description="Historial de cambios y acciones en el sistema." />

    @php
        $eventLabels = [
            'branch.created' => 'Sucursal creada', 'branch.updated' => 'Sucursal actualizada',
            'chamber.created' => 'Cámara creada', 'chamber.updated' => 'Cámara actualizada',
            'category.created' => 'Categoría creada', 'category.updated' => 'Categoría actualizada',
            'product.created' => 'Producto creado', 'product.updated' => 'Producto actualizado',
            'user.created' => 'Usuario creado', 'user.updated' => 'Usuario actualizado',
            'exit.reversed' => 'Salida revertida', 'transfer.cancelled' => 'Transferencia cancelada',
            'inventory.adjusted' => 'Inventario ajustado', 'stock.adjusted' => 'Existencias ajustadas', 'box.edited' => 'Caja editada', 'entry.corrected' => 'Entrada corregida', 'exit.corrected' => 'Salida corregida', 'incident.resolved' => 'Incidencia resuelta',
        ];
        $eventLabel = fn (string $event): string => $eventLabels[$event] ?? str_replace(['.', '_'], ' ', $event);
        $eventColor = fn (string $event): string => match (true) {
            str_ends_with($event, '.created') => 'green',
            str_ends_with($event, '.updated') => 'sky',
            str_ends_with($event, '.reversed'), str_ends_with($event, '.cancelled') => 'red',
            str_ends_with($event, '.adjusted'), str_ends_with($event, '.corrected'), str_ends_with($event, '.edited') => 'amber',
            str_ends_with($event, '.resolved') => 'emerald',
            default => 'zinc',
        };
        $show = fn (mixed $value): string => is_scalar($value) || $value === null ? (is_bool($value) ? ($value ? 'sí' : 'no') : (string) ($value ?? '—')) : json_encode($value, JSON_UNESCAPED_UNICODE);
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="audit-total">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.document-text class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Registros</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['total']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="audit-today">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                <flux:icon.calendar-days class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Hoy</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['today']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="audit-users">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.users class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Usuarios que actuaron</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['users']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="audit-events">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
                <flux:icon.tag class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Tipos de evento</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['events']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="event" label="Evento">
                <flux:select.option value="">Todos los eventos</flux:select.option>
                @foreach ($events as $eventOption)
                    <flux:select.option :value="$eventOption">{{ $eventLabel($eventOption) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="userId" label="Usuario">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($users as $user)
                    <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="dateFrom" type="date" label="Desde" />
            <flux:input wire:model.live="dateTo" type="date" label="Hasta" />
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="audit-table">
        <flux:table :paginate="$logs" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Usuario</flux:table.column>
                <flux:table.column>Evento</flux:table.column>
                <flux:table.column>Registro</flux:table.column>
                <flux:table.column>Cambios</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($logs as $log)
                    @php
                        $before = (array) ($log->old_values ?? []);
                        $after = (array) ($log->new_values ?? []);
                        $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
                    @endphp
                    <flux:table.row :key="$log->id" class="align-top hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ $log->created_at->format('d/m/Y') }} <span class="text-xs text-zinc-500">{{ $log->created_at->format('H:i') }}</span></div>
                            <div class="text-xs text-zinc-500">{{ $log->ip_address }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $log->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <flux:badge size="sm" :color="$eventColor($log->event)">{{ $eventLabel($log->event) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            @if ($log->auditable_type)
                                <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="min-w-72 max-w-md whitespace-normal">
                            @forelse ($keys as $key)
                                <div class="break-words text-xs" wire:key="chg-{{ $log->id }}-{{ $key }}">
                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $key }}:</span>
                                    @if (array_key_exists($key, $before) && array_key_exists($key, $after))
                                        <span class="text-zinc-500 line-through">{{ $show($before[$key]) }}</span> → <span class="text-zinc-900 dark:text-white">{{ $show($after[$key]) }}</span>
                                    @elseif (array_key_exists($key, $after))
                                        <span class="text-zinc-900 dark:text-white">{{ $show($after[$key]) }}</span>
                                    @else
                                        <span class="text-zinc-500 line-through">{{ $show($before[$key]) }}</span>
                                    @endif
                                </div>
                            @empty
                                <span class="text-zinc-400">—</span>
                            @endforelse
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">No hay registros de auditoría que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
