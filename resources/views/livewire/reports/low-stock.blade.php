<section class="w-full space-y-6">
    <x-page-header icon="bell-alert" title="Existencias bajas" description="Productos con pocas cajas disponibles.">
        <div class="flex gap-2">
            <flux:modal.trigger name="threshold-form"><flux:button icon="plus">Configurar umbral</flux:button></flux:modal.trigger>
            <flux:button icon="arrow-down-tray" wire:click="export">Exportar CSV</flux:button>
        </div>
    </x-page-header>

    @php
        $cards = [
            ['key' => 'thresholds', 'icon' => 'adjustments-horizontal', 'color' => 'indigo', 'label' => 'Umbrales configurados'],
            ['key' => 'low', 'icon' => 'bell-alert', 'color' => 'amber', 'label' => 'En alerta'],
            ['key' => 'empty', 'icon' => 'exclamation-triangle', 'color' => 'red', 'label' => 'Sin existencias'],
            ['key' => 'healthy', 'icon' => 'check-circle', 'color' => 'emerald', 'label' => 'Con existencias suficientes'],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="low-stock-{{ $card['key'] }}">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-{{ $card['color'] }}-500/10 text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-300">
                    <flux:icon :name="$card['icon']" class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $card['label'] }}</div>
                    <div class="truncate text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals[$card['key']]) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="search" label="Producto" icon="magnifying-glass" clearable placeholder="Buscar por nombre de producto" />
            <flux:select wire:model.live="chamberFilter" label="Cámara">
                <flux:select.option value="">Todas</flux:select.option>
                @foreach ($chambers as $chamber)
                    <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <flux:switch wire:model.live="showAll" label="Mostrar también los umbrales que se cumplen" />
            @if ($hasFilters)
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="low-stock-table">
        <flux:table container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Cámara</flux:table.column>
                <flux:table.column>Producto</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column align="end">Cajas / mínimo</flux:table.column>
                <flux:table.column align="end">Kg / mínimo</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    @php
                        $isLow = ($row->min_boxes !== null && $row->boxes < $row->min_boxes) || ($row->min_weight_kg !== null && (float) $row->kg < (float) $row->min_weight_kg);
                        [$statusLabel, $statusColor] = (int) $row->boxes === 0 ? ['Sin existencias', 'red'] : ($isLow ? ['Bajo', 'amber'] : ['Suficiente', 'green']);
                    @endphp
                    <flux:table.row wire:key="low-{{ $row->threshold_id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $row->chamber }}</div>
                            <div class="text-xs text-zinc-500">{{ $row->branch }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap font-medium text-zinc-900 dark:text-white">{{ $row->product }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$statusColor">{{ $statusLabel }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ number_format($row->boxes) }}</span> <span class="text-zinc-500">/ {{ $row->min_boxes ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ number_format((float) $row->kg, 3) }}</span> <span class="text-zinc-500">/ {{ $row->min_weight_kg ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteThreshold({{ $row->threshold_id }})" wire:confirm="¿Eliminar este umbral?">Quitar umbral</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">Sin existencias bajas.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="threshold-form" class="md:w-96">
        <form wire:submit="saveThreshold" class="space-y-6">
            <flux:heading size="lg">Umbral de existencias</flux:heading>
            <flux:select wire:model="thresholdChamberId" label="Cámara" placeholder="Selecciona">
                @foreach ($chambers as $chamber)
                    <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="thresholdProductId" label="Producto" placeholder="Selecciona">
                @foreach ($products as $product)
                    <flux:select.option :value="$product->id">{{ $product->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="minBoxes" label="Mínimo de cajas" type="number" min="0" />
            <flux:input wire:model="minWeight" label="Mínimo de kilogramos" inputmode="decimal" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
