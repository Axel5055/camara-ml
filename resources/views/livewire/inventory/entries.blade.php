<section class="w-full space-y-6">
    <x-page-header icon="arrow-down-tray" title="Entradas" description="Registro de la mercancía que ingresa a las cámaras.">
        @can('operate-inventory')
            <flux:button variant="primary" icon="plus" :href="route('entries.create')" wire:navigate>Nueva entrada</flux:button>
        @endcan
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 {{ $showCosts ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entries-count">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.document-text class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Entradas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($summary->entries) }}</div>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entries-boxes">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.archive-box class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cajas recibidas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($summary->boxes) }}</div>
            </div>
        </div>

        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entries-weight">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
                <flux:icon.scale class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Peso recibido</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format((float) $summary->kg, 3) }} <span class="text-sm font-normal text-zinc-500">kg</span></div>
            </div>
        </div>

        @if ($showCosts)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entries-cost">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                    <flux:icon.banknotes class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Importe total</div>
                    <div class="text-2xl font-semibold text-zinc-900 dark:text-white">${{ number_format((float) $summary->cost, 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
                </div>
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" label="Referencia" icon="magnifying-glass" clearable placeholder="Buscar referencia" />
            <flux:select wire:model.live="chamberId" label="Cámara">
                <flux:select.option value="">Todas las cámaras</flux:select.option>
                @foreach ($chambers as $chamber)
                    <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="from" type="date" label="Desde" />
            <flux:input wire:model.live="to" type="date" label="Hasta" />
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entries-table">
        <flux:table :paginate="$entries" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Referencia</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Cámara</flux:table.column>
                <flux:table.column align="end">Cajas</flux:table.column>
                <flux:table.column align="end">Peso (kg)</flux:table.column>
                <flux:table.column align="end">Precio por caja</flux:table.column>
                @if ($showCosts)
                    <flux:table.column align="end">Importe (MXN)</flux:table.column>
                @endif
                <flux:table.column>Registró</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($entries as $entry)
                    <flux:table.row :key="$entry->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $entry->reference }}</span>
                            @if ($entry->status === \App\Enums\DocumentStatus::Reversed)
                                <flux:badge size="sm" color="red" class="ms-1">Revertida</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ $entry->occurred_at->format('d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ $entry->occurred_at->format('H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $entry->chamber->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $entry->chamber->branch->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ number_format($entry->total_boxes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $entry->total_weight_kg }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap tabular-nums" data-test="entry-unit-cost">
                            ${{ number_format((float) $entry->min_unit_cost, 2) }}@if ((float) $entry->min_unit_cost !== (float) $entry->max_unit_cost) – ${{ number_format((float) $entry->max_unit_cost, 2) }}@endif
                        </flux:table.cell>
                        @if ($showCosts)
                            <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">${{ number_format((float) $entry->total_cost_mxn, 2) }}</flux:table.cell>
                        @endif
                        <flux:table.cell class="text-zinc-500">{{ $entry->user->name }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" :href="route('entries.show', $entry)" wire:navigate>Detalle</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $showCosts ? 9 : 8 }}" class="py-10 text-center text-zinc-500">No hay entradas que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
