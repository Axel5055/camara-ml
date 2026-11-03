<section class="w-full space-y-6">
    <x-page-header icon="exclamation-triangle" title="Incidencias" description="Diferencias detectadas al recibir transferencias." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="incidents-open">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-red-500/10 text-red-600 dark:text-red-300">
                <flux:icon.exclamation-triangle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Abiertas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['open']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="incidents-resolved">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                <flux:icon.check-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Resueltas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['resolved']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="incidents-missing">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-300">
                <flux:icon.question-mark-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cajas faltantes abiertas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['missing']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="incidents-weight">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
                <flux:icon.scale class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Diferencias de peso abiertas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['weight']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por transferencia, código de caja o detalle" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="status" label="Estado">
                    <flux:select.option value="open">Abiertas</flux:select.option>
                    <flux:select.option value="resolved">Resueltas</flux:select.option>
                    <flux:select.option value="all">Todas</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="type" label="Tipo">
                    <flux:select.option value="all">Todos los tipos</flux:select.option>
                    @foreach ($types as $typeOption)
                        <flux:select.option :value="$typeOption->value">{{ $typeOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="incidents-table">
        <flux:table :paginate="$incidents" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Transferencia</flux:table.column>
                <flux:table.column>Caja</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column>Detalle</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($incidents as $incident)
                    <flux:table.row :key="$incident->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ $incident->created_at->format('d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ $incident->created_at->format('H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $incident->transfer->reference }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($incident->box)
                                <a href="{{ route('boxes.show', $incident->box) }}" wire:navigate class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 hover:underline dark:bg-zinc-700 dark:text-zinc-200">{{ $incident->box->barcode }}</a>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$incident->type->color()">{{ $incident->type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="min-w-64 max-w-sm whitespace-normal">
                            <div>{{ $incident->description }}</div>
                            @if ($incident->resolution)
                                <div class="mt-1 text-xs text-zinc-500">Resolución: {{ $incident->resolution }} ({{ $incident->resolver?->name }})</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$incident->status === \App\Enums\IncidentStatus::Open ? 'red' : 'green'">{{ $incident->status === \App\Enums\IncidentStatus::Open ? 'Abierta' : 'Resuelta' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($incident->status === \App\Enums\IncidentStatus::Open)
                                <flux:button size="sm" variant="ghost" icon="check" wire:click="askResolve({{ $incident->id }})">Resolver</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">No hay incidencias que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="resolve-incident" class="md:w-96">
        <form wire:submit="resolve" class="space-y-6">
            <flux:heading size="lg">Resolver incidencia</flux:heading>
            <flux:text>Si hay que corregir el inventario de la caja, regístralo antes en Ajustes.</flux:text>
            <flux:textarea wire:model="resolution" label="Resolución" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Marcar como resuelta</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
