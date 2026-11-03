<section class="w-full space-y-6">
    @php
        $description = match (true) {
            (bool) $selected => 'Información de la cámara '.$selected->name.' · '.$selected->branch->name.'.',
            $allChambers->count() > 1 => 'Información concentrada de '.(auth()->user()->seesAllChambers() ? 'todas las cámaras' : 'tus '.$allChambers->count().' cámaras').'.',
            default => 'Resumen general del inventario.',
        };
    @endphp

    <x-page-header icon="home" title="Panel" :description="$description">
        @if ($allChambers->count() > 1)
            <div class="w-full sm:w-72">
                <flux:select wire:model.live="chamberId" label="Cámara" data-test="chamber-filter">
                    <flux:select.option value="">Todas las cámaras (concentrado)</flux:select.option>
                    @foreach ($allChambers as $option)
                        <flux:select.option :value="$option->id">{{ $option->branch->name }} · {{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </x-page-header>

    {{-- Indicadores --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:bg-zinc-800 dark:border-zinc-700" data-test="kpi-stock">
            <flux:text class="text-xs uppercase tracking-wide">Cajas disponibles</flux:text>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($totalBoxes) }}</div>
            <flux:text class="text-xs">{{ number_format((float) $totalKg, 3) }} kg en {{ $chambers->count() }} {{ $chambers->count() === 1 ? 'cámara' : 'cámaras' }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:bg-zinc-800 dark:border-zinc-700" data-test="kpi-entries-today">
            <flux:text class="text-xs uppercase tracking-wide">Entradas de hoy</flux:text>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format((int) $entriesToday->boxes) }} <span class="text-sm font-normal text-zinc-500">cajas</span></div>
            <flux:text class="text-xs">{{ number_format((float) $entriesToday->kg, 3) }} kg</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:bg-zinc-800 dark:border-zinc-700" data-test="kpi-exits-today">
            <flux:text class="text-xs uppercase tracking-wide">Salidas de hoy</flux:text>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format((int) $exitsToday->boxes) }} <span class="text-sm font-normal text-zinc-500">cajas</span></div>
            <flux:text class="text-xs">{{ number_format((float) $exitsToday->kg, 3) }} kg</flux:text>
        </div>
        @if ($showTransfers)
            <a href="{{ route('transfers.index') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:bg-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" data-test="kpi-incoming">
            <flux:text class="text-xs uppercase tracking-wide">Transferencias por recibir</flux:text>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $incoming }}</div>
            <flux:text class="text-xs">en tránsito hacia tus cámaras</flux:text>
        </a>
        @endif
        <div class="rounded-xl border p-4 shadow-xs {{ $lowStock->isNotEmpty() ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800' }}" data-test="kpi-low-stock">
            <flux:text class="text-xs uppercase tracking-wide">Stock bajo</flux:text>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $lowStock->count() }}</div>
            <flux:text class="text-xs">productos con {{ $threshold }} cajas o menos</flux:text>
        </div>
        @if ($openIncidents !== null)
            <a href="{{ route('admin.incidents') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:bg-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" data-test="kpi-incidents">
                <flux:text class="text-xs uppercase tracking-wide">Incidencias abiertas</flux:text>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $openIncidents }}</div>
                <flux:text class="text-xs">de transferencias</flux:text>
            </a>
        @endif
        @if ($inventoryValue !== null)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:bg-zinc-800 dark:border-zinc-700" data-test="kpi-value">
                <flux:text class="text-xs uppercase tracking-wide">Valor del inventario</flux:text>
                <div class="mt-1 whitespace-nowrap text-2xl font-semibold tabular-nums">${{ number_format((float) $inventoryValue, 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
                <flux:text class="text-xs">a costo histórico</flux:text>
            </div>
        @endif
    </div>

    {{-- Gráfica diaria --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="daily-chart">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="lg">Entradas y salidas por día</flux:heading>
                <flux:text class="text-sm">Últimos {{ $days }} días · {{ $metric === 'kg' ? 'kilogramos' : 'cajas' }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:select wire:model.live="metric" size="sm" class="w-32">
                    <flux:select.option value="boxes">Cajas</flux:select.option>
                    <flux:select.option value="kg">Kilogramos</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="days" size="sm" class="w-32">
                    <flux:select.option value="7">7 días</flux:select.option>
                    <flux:select.option value="14">14 días</flux:select.option>
                    <flux:select.option value="30">30 días</flux:select.option>
                </flux:select>
            </div>
        </div>

        <x-bar-chart
            :labels="$series['labels']"
            :series="[
                ['name' => 'Entradas', 'color' => '#f59e42', 'values' => $series['entries']],
                ['name' => 'Salidas', 'color' => '#3f51d0', 'values' => $series['exits']],
            ]"
            :unit="$metric === 'kg' ? ' kg' : ''"
        />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Stock bajo --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="low-stock-list">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <flux:heading size="lg">Productos con stock bajo</flux:heading>
                    <flux:text class="text-sm">{{ $threshold }} cajas disponibles o menos</flux:text>
                </div>
                <flux:badge :color="$lowStock->isEmpty() ? 'green' : 'amber'">{{ $lowStock->count() }}</flux:badge>
            </div>

            <div class="max-h-80 space-y-2 overflow-y-auto">
                @forelse ($lowStock as $item)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700" wire:key="low-{{ $item->chamber_id }}-{{ $item->product_id }}">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $item->product }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $item->chamber }} · {{ $item->branch }}</div>
                        </div>
                        <flux:badge size="sm" :color="$item->boxes === 0 ? 'red' : 'amber'">
                            {{ $item->boxes === 0 ? 'Agotado' : $item->boxes.' '.($item->boxes === 1 ? 'caja' : 'cajas') }}
                        </flux:badge>
                    </div>
                @empty
                    <flux:text class="py-6 text-center">Todo en orden: ningún producto con stock bajo.</flux:text>
                @endforelse
            </div>
        </div>

        {{-- Existencias por categoría --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="category-stock">
            <flux:heading size="lg">Existencias por categoría</flux:heading>
            <flux:text class="mb-3 text-sm">Cajas disponibles</flux:text>

            @php($maxCategory = max(1, (int) $categories->max('boxes')))
            <div class="space-y-3">
                @forelse ($categories as $category)
                    <div wire:key="cat-{{ $loop->index }}">
                        <div class="mb-1 flex items-baseline justify-between text-sm">
                            <span class="font-medium">{{ $category->category }}</span>
                            <span class="tabular-nums text-zinc-500">{{ number_format($category->boxes) }} cajas · {{ number_format((float) $category->kg, 1) }} kg</span>
                        </div>
                        <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-2 rounded-full bg-accent" style="width: {{ $category->boxes / $maxCategory * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <flux:text class="py-6 text-center">Aún no hay cajas disponibles.</flux:text>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Últimas entradas --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="recent-entries">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">Últimas entradas</flux:heading>
                <flux:button size="sm" variant="ghost" :href="route('entries.index')" wire:navigate>Ver todas</flux:button>
            </div>
            <div class="space-y-2">
                @forelse ($recentEntries as $entry)
                    <a href="{{ route('entries.show', $entry) }}" wire:navigate class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" wire:key="re-{{ $entry->id }}">
                        <div>
                            <div class="font-medium">{{ $entry->reference }}</div>
                            <div class="text-xs text-zinc-500">{{ $entry->occurred_at->format('d/m/Y H:i') }} · {{ $entry->chamber->name }}</div>
                        </div>
                        <div class="text-end tabular-nums">
                            <div>{{ number_format($entry->total_boxes) }} cajas</div>
                            <div class="text-xs text-zinc-500">{{ $entry->total_weight_kg }} kg</div>
                        </div>
                    </a>
                @empty
                    <flux:text class="py-6 text-center">Sin entradas registradas.</flux:text>
                @endforelse
            </div>
        </div>

        {{-- Últimas salidas --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="recent-exits">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">Últimas salidas</flux:heading>
                <flux:button size="sm" variant="ghost" :href="route('exits.index')" wire:navigate>Ver todas</flux:button>
            </div>
            <div class="space-y-2">
                @forelse ($recentExits as $exit)
                    <a href="{{ route('exits.show', $exit) }}" wire:navigate class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800" wire:key="rx-{{ $exit->id }}">
                        <div>
                            <div class="font-medium">{{ $exit->reference }} · {{ $exit->destBranch->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $exit->occurred_at->format('d/m/Y H:i') }} · {{ $exit->requester_name }}</div>
                        </div>
                        <div class="text-end tabular-nums">
                            <div>{{ number_format($exit->total_boxes) }} cajas</div>
                            <div class="text-xs text-zinc-500">{{ $exit->total_weight_kg }} kg</div>
                        </div>
                    </a>
                @empty
                    <flux:text class="py-6 text-center">Sin salidas registradas.</flux:text>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Existencias por cámara --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Sucursal</flux:table.column>
                <flux:table.column>Cámara</flux:table.column>
                <flux:table.column align="end">Cajas disponibles</flux:table.column>
                <flux:table.column align="end">Peso disponible (kg)</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($chambers as $chamber)
                    <flux:table.row :key="$chamber->id">
                        <flux:table.cell>{{ $chamber->branch->name }}</flux:table.cell>
                        <flux:table.cell>{{ $chamber->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((int) ($stock[$chamber->id]->boxes ?? 0)) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) ($stock[$chamber->id]->kg ?? 0), 3) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">No tienes cámaras asignadas.</flux:table.cell>
                    </flux:table.row>
                @endforelse
                @if ($chambers->count() > 1)
                    <flux:table.row>
                        <flux:table.cell class="font-semibold" colspan="2">Total</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format($totalBoxes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format((float) $totalKg, 3) }}</flux:table.cell>
                    </flux:table.row>
                @endif
            </flux:table.rows>
        </flux:table>
    </div>
</section>
