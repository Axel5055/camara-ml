<section class="mx-auto w-full max-w-7xl space-y-6">
    @php
        $description = match (true) {
            (bool) $selected => 'Información de la cámara '.$selected->name.' · '.$selected->branch->name.'.',
            $allChambers->count() > 1 => 'Información concentrada de '.(auth()->user()->seesAllChambers() ? 'todas las cámaras' : 'tus '.$allChambers->count().' cámaras').'.',
            default => 'Resumen general del inventario.',
        };

        $tones = [
            'indigo' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-300',
            'green' => 'bg-green-500/10 text-green-600 dark:text-green-300',
            'red' => 'bg-red-500/10 text-red-600 dark:text-red-300',
            'sky' => 'bg-sky-500/10 text-sky-600 dark:text-sky-300',
            'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-300',
            'violet' => 'bg-violet-500/10 text-violet-600 dark:text-violet-300',
            'emerald' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
        ];

        $kpis = [
            ['key' => 'stock', 'icon' => 'archive-box', 'tone' => 'indigo', 'label' => 'Cajas disponibles', 'value' => number_format($totalBoxes), 'unit' => null, 'hint' => number_format((float) $totalKg, 3).' kg en '.$chambers->count().' '.($chambers->count() === 1 ? 'cámara' : 'cámaras'), 'href' => route('stock')],
            ['key' => 'entries-today', 'icon' => 'arrow-down-tray', 'tone' => 'green', 'label' => 'Entradas de hoy', 'value' => number_format((int) $entriesToday->boxes), 'unit' => 'cajas', 'hint' => number_format((float) $entriesToday->kg, 3).' kg', 'href' => route('entries.index')],
            ['key' => 'exits-today', 'icon' => 'arrow-up-tray', 'tone' => 'red', 'label' => 'Salidas de hoy', 'value' => number_format((int) $exitsToday->boxes), 'unit' => 'cajas', 'hint' => number_format((float) $exitsToday->kg, 3).' kg', 'href' => route('exits.index')],
        ];

        if ($showTransfers) {
            $kpis[] = ['key' => 'incoming', 'icon' => 'truck', 'tone' => 'sky', 'label' => 'Transferencias por recibir', 'value' => (string) $incoming, 'unit' => null, 'hint' => 'en tránsito hacia tus cámaras', 'href' => route('transfers.index')];
        }

        $kpis[] = ['key' => 'low-stock', 'icon' => 'exclamation-triangle', 'tone' => 'amber', 'label' => 'Stock bajo', 'value' => (string) $lowStock->count(), 'unit' => null, 'hint' => 'productos con '.$threshold.' cajas o menos', 'href' => auth()->user()->can('view-reports') ? route('reports.low-stock') : null, 'alert' => $lowStock->isNotEmpty()];

        if ($openIncidents !== null) {
            $kpis[] = ['key' => 'incidents', 'icon' => 'flag', 'tone' => 'violet', 'label' => 'Incidencias abiertas', 'value' => (string) $openIncidents, 'unit' => null, 'hint' => 'de transferencias', 'href' => route('admin.incidents')];
        }

        if ($inventoryValue !== null) {
            $kpis[] = ['key' => 'value', 'icon' => 'banknotes', 'tone' => 'emerald', 'label' => 'Valor del inventario', 'value' => '$'.number_format((float) $inventoryValue, 2), 'unit' => 'MXN', 'hint' => 'a costo histórico', 'href' => null];
        }
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
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as $kpi)
            @php
                $card = 'flex items-center gap-4 rounded-xl border p-4 shadow-xs '.(($kpi['alert'] ?? false)
                    ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950'
                    : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800');
            @endphp
            @if ($kpi['href'])
                <a href="{{ $kpi['href'] }}" wire:navigate class="{{ $card }} transition hover:border-zinc-300 hover:shadow-sm dark:hover:border-zinc-600" data-test="kpi-{{ $kpi['key'] }}">
            @else
                <div class="{{ $card }}" data-test="kpi-{{ $kpi['key'] }}">
            @endif
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $tones[$kpi['tone']] }}">
                    <flux:icon :name="$kpi['icon']" class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $kpi['label'] }}</div>
                    <div class="whitespace-nowrap text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $kpi['value'] }} @if ($kpi['unit'])<span class="text-sm font-normal text-zinc-500">{{ $kpi['unit'] }}</span>@endif</div>
                    <div class="truncate text-xs text-zinc-500">{{ $kpi['hint'] }}</div>
                </div>
            @if ($kpi['href'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    {{-- Gráfica diaria --}}
    <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="daily-chart">
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Stock bajo --}}
        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="low-stock-list">
            <div class="mb-3 flex items-center justify-between gap-2">
                <div>
                    <flux:heading size="lg">Productos con stock bajo</flux:heading>
                    <flux:text class="text-sm">{{ $threshold }} cajas disponibles o menos</flux:text>
                </div>
                <flux:badge :color="$lowStock->isEmpty() ? 'green' : 'amber'">{{ $lowStock->count() }}</flux:badge>
            </div>

            <div class="max-h-80 space-y-2 overflow-y-auto">
                @forelse ($lowStock as $item)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-2.5 text-sm dark:border-zinc-700" wire:key="low-{{ $item->chamber_id }}-{{ $item->product_id }}">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $item->product }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $item->chamber }} · {{ $item->branch }}</div>
                        </div>
                        <flux:badge size="sm" :color="$item->boxes === 0 ? 'red' : 'amber'">
                            {{ $item->boxes === 0 ? 'Agotado' : $item->boxes.' '.($item->boxes === 1 ? 'caja' : 'cajas') }}
                        </flux:badge>
                    </div>
                @empty
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <div class="flex size-10 items-center justify-center rounded-full bg-green-500/10 text-green-600 dark:text-green-300">
                            <flux:icon name="check-circle" class="size-6" />
                        </div>
                        <flux:text>Todo en orden: ningún producto con stock bajo.</flux:text>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Existencias por categoría --}}
        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="category-stock">
            <flux:heading size="lg">Existencias por categoría</flux:heading>
            <flux:text class="mb-4 text-sm">Cajas disponibles</flux:text>

            @php($maxCategory = max(1, (int) $categories->max('boxes')))
            <div class="space-y-4">
                @forelse ($categories as $category)
                    <div wire:key="cat-{{ $loop->index }}">
                        <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="truncate font-medium">{{ $category->category }}</span>
                            <span class="shrink-0 tabular-nums text-zinc-500">{{ number_format($category->boxes) }} cajas · {{ number_format((float) $category->kg, 1) }} kg</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="h-2 rounded-full bg-accent" style="width: {{ $category->boxes / $maxCategory * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <flux:text class="py-6 text-center">Aún no hay cajas disponibles.</flux:text>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Productos concentrados por cámara (una cámara a la vez) --}}
    @if ($tableChamber || $tableAll)
        <div
            x-data="{ hover: null, picked: null, get hl() { return this.hover ?? this.picked; } }"
            class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-800"
            data-test="chamber-products"
        >
            <div class="flex flex-wrap items-center gap-3 border-b border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                    <flux:icon name="archive-box" class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">Productos por cámara</flux:heading>
                    @if ($tableAll)
                        <flux:text class="text-sm">Concentrado de todas tus cámaras — {{ number_format($chamberSummary->count()) }} {{ $chamberSummary->count() === 1 ? 'cámara' : 'cámaras' }}</flux:text>
                    @else
                        <flux:text class="text-sm">{{ $tableChamber->branch->name }} · {{ $tableChamber->name }} — {{ number_format($productStock->count()) }} {{ $productStock->count() === 1 ? 'producto' : 'productos' }}</flux:text>
                    @endif
                </div>
            </div>

            <div class="grid gap-3 border-b border-zinc-200 bg-zinc-50 p-4 sm:grid-cols-3 dark:border-zinc-700 dark:bg-zinc-900/30">
                <flux:select wire:model.live="tableChamberId" data-test="products-chamber-filter" aria-label="Cámara">
                    @if ($allChambers->count() > 1)
                        <flux:select.option value="0" :selected="$tableAll">Todas las cámaras (concentrado)</flux:select.option>
                    @endif
                    @foreach ($allChambers as $option)
                        <flux:select.option :value="$option->id" :selected="$option->id === $tableChamber?->id">{{ $option->branch->name }} · {{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="tableCategoryId" data-test="products-category-filter" aria-label="Categoría">
                    <flux:select.option value="">Todas las categorías</flux:select.option>
                    @foreach ($tableCategories as $id => $name)
                        <flux:select.option :value="$id" :selected="$id === $tableCategoryId">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live.debounce.300ms="tableSearch" icon="magnifying-glass" placeholder="Buscar producto o código" clearable data-test="products-search" />
            </div>

            <div class="grid lg:grid-cols-5">
            <div class="min-w-0 lg:col-span-3">
            @if ($tableAll)
                <div class="p-2" data-test="chamber-summary">
                    <flux:table container:class="overflow-x-auto">
                        <flux:table.columns>
                            <flux:table.column>Sucursal</flux:table.column>
                            <flux:table.column>Cámara</flux:table.column>
                            <flux:table.column align="end">Cajas disponibles</flux:table.column>
                            <flux:table.column align="end">Peso disponible (kg)</flux:table.column>
                            @if ($inventoryValue !== null)
                                <flux:table.column align="end">Valor (MXN)</flux:table.column>
                            @endif
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($chamberSummary as $row)
                                <flux:table.row :key="'cs-'.$loop->index" x-on:pointerenter="$event.pointerType === 'mouse' && (hover = @js($row->chamber))" x-on:pointerleave="hover = null" x-on:click="picked = picked === @js($row->chamber) ? null : @js($row->chamber)" x-bind:class="hl === @js($row->chamber) && 'bg-zinc-100 dark:bg-zinc-700/60'" class="cursor-pointer touch-manipulation transition-colors" data-test="summary-row">
                                    <flux:table.cell class="text-zinc-500">{{ $row->branch }}</flux:table.cell>
                                    <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $row->chamber }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums font-medium">{{ number_format((int) $row->boxes) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $row->kg, 3) }}</flux:table.cell>
                                    @if ($inventoryValue !== null)
                                        <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white" data-test="chamber-value">${{ number_format((float) $row->value, 2) }}</flux:table.cell>
                                    @endif
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="{{ $inventoryValue !== null ? 5 : 4 }}">
                                        <div class="py-6 text-center text-zinc-500">No hay cajas disponibles con esos filtros.</div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                            @if ($chamberSummary->count() > 1)
                                <flux:table.row class="bg-zinc-50 dark:bg-zinc-900/40">
                                    <flux:table.cell class="font-semibold" colspan="2">Total</flux:table.cell>
                                    <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format((int) $chamberSummary->sum('boxes')) }}</flux:table.cell>
                                    <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format((float) $chamberSummary->sum('kg'), 3) }}</flux:table.cell>
                                    @if ($inventoryValue !== null)
                                        <flux:table.cell align="end" class="font-semibold tabular-nums">${{ number_format((float) $chamberSummary->sum('value'), 2) }}</flux:table.cell>
                                    @endif
                                </flux:table.row>
                            @endif
                        </flux:table.rows>
                    </flux:table>
                </div>
            @else
            <div class="p-2">
                <flux:table container:class="overflow-x-auto">
                    <flux:table.columns>
                        <flux:table.column>Producto</flux:table.column>
                        <flux:table.column align="end">Cajas</flux:table.column>
                        <flux:table.column align="end">Peso (kg)</flux:table.column>
                        @if ($inventoryValue !== null)
                            <flux:table.column align="end">Valor (MXN)</flux:table.column>
                        @endif
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($productStock as $row)
                            <flux:table.row :key="'ps-'.$tableChamber->id.'-'.$row->code" x-on:pointerenter="$event.pointerType === 'mouse' && (hover = @js($row->product))" x-on:pointerleave="hover = null" x-on:click="picked = picked === @js($row->product) ? null : @js($row->product)" x-bind:class="hl === @js($row->product) && 'bg-zinc-100 dark:bg-zinc-700/60'" class="cursor-pointer touch-manipulation transition-colors" data-test="product-row">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $row->product }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2"><span class="font-mono text-xs text-zinc-500">{{ $row->code }}</span> <flux:badge size="sm" :color="$categoryColors[$row->category_id] ?? 'zinc'" data-test="category-badge">{{ $row->category }}</flux:badge></div>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums font-medium">{{ number_format((int) $row->boxes) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ number_format((float) $row->kg, 3) }}</flux:table.cell>
                                @if ($inventoryValue !== null)
                                    <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white" data-test="product-value">${{ number_format((float) $row->value, 2) }}</flux:table.cell>
                                @endif
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="{{ $inventoryValue !== null ? 4 : 3 }}">
                                    <div class="py-6 text-center text-zinc-500">
                                        {{ $tableHasStock ? 'Ningún producto coincide con los filtros.' : 'Esta cámara no tiene cajas disponibles.' }}
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                        @if ($productStock->count() > 1)
                            <flux:table.row class="bg-zinc-50 dark:bg-zinc-900/40">
                                <flux:table.cell class="font-semibold" colspan="1">Total</flux:table.cell>
                                <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format((int) $productStock->sum('boxes')) }}</flux:table.cell>
                                <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format((float) $productStock->sum('kg'), 3) }}</flux:table.cell>
                                @if ($inventoryValue !== null)
                                    <flux:table.cell align="end" class="font-semibold tabular-nums">${{ number_format((float) $productStock->sum('value'), 2) }}</flux:table.cell>
                                @endif
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>
            </div>
            @endif
            </div>

            <div class="border-t border-zinc-200 p-5 lg:col-span-2 lg:border-l lg:border-t-0 dark:border-zinc-700" data-test="chamber-pie">
                <flux:heading size="lg">{{ $tableAll ? 'Cajas por cámara' : 'Cajas por producto' }}</flux:heading>
                <flux:text class="mb-4 text-sm">Distribución de las cajas disponibles según los filtros.</flux:text>
                <x-pie-chart
                    :shared="true"
                    :slices="$tableAll
                        ? $chamberSummary->map(fn ($row) => ['label' => $row->chamber, 'value' => (int) $row->boxes])->all()
                        : $productStock->map(fn ($row) => ['label' => $row->product, 'value' => (int) $row->boxes])->all()"
                    wire:key="pie-{{ $tableAll ? 'all' : $tableChamber->id }}-{{ $tableCategoryId }}-{{ $tableSearch }}"
                />
            </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Últimas entradas --}}
        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="recent-entries">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">Últimas entradas</flux:heading>
                <flux:button size="sm" variant="ghost" :href="route('entries.index')" wire:navigate icon-trailing="chevron-right">Ver todas</flux:button>
            </div>
            <div class="space-y-2">
                @forelse ($recentEntries as $entry)
                    <a href="{{ route('entries.show', $entry) }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-zinc-200 p-2.5 text-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/40" wire:key="re-{{ $entry->id }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $tones['green'] }}">
                            <flux:icon name="arrow-down-tray" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $entry->reference }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $entry->occurred_at->format('d/m/Y H:i') }} · {{ $entry->chamber->name }}</div>
                        </div>
                        <div class="text-end tabular-nums">
                            <div class="font-medium">{{ number_format($entry->total_boxes) }} cajas</div>
                            <div class="text-xs text-zinc-500">{{ $entry->total_weight_kg }} kg</div>
                        </div>
                    </a>
                @empty
                    <flux:text class="py-6 text-center">Sin entradas registradas.</flux:text>
                @endforelse
            </div>
        </div>

        {{-- Últimas salidas --}}
        <div class="min-w-0 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="recent-exits">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">Últimas salidas</flux:heading>
                <flux:button size="sm" variant="ghost" :href="route('exits.index')" wire:navigate icon-trailing="chevron-right">Ver todas</flux:button>
            </div>
            <div class="space-y-2">
                @forelse ($recentExits as $exit)
                    <a href="{{ route('exits.show', $exit) }}" wire:navigate class="flex items-center gap-3 rounded-lg border border-zinc-200 p-2.5 text-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/40" wire:key="rx-{{ $exit->id }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $tones['red'] }}">
                            <flux:icon name="arrow-up-tray" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ $exit->reference }} · {{ $exit->destBranch->name }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $exit->occurred_at->format('d/m/Y H:i') }} · {{ $exit->requester_name }}</div>
                        </div>
                        <div class="text-end tabular-nums">
                            <div class="font-medium">{{ number_format($exit->total_boxes) }} cajas</div>
                            <div class="text-xs text-zinc-500">{{ $exit->total_weight_kg }} kg</div>
                        </div>
                    </a>
                @empty
                    <flux:text class="py-6 text-center">Sin salidas registradas.</flux:text>
                @endforelse
            </div>
        </div>
    </div>

</section>
