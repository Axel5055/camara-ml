<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrow-up-tray" title="Salida de mercancía" description="Captura los datos, agrega las cajas que salen y crea la salida.">
        <flux:button :href="route('exits.index')" wire:navigate icon="arrow-left">Salidas</flux:button>
    </x-page-header>

    <flux:error name="operation" />

    @php
        $cards = [
            ['key' => 'groups', 'icon' => 'rectangle-stack', 'color' => 'indigo', 'label' => 'Grupos', 'value' => number_format(count($summaries))],
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'sky', 'label' => 'Cajas', 'value' => number_format($totals['boxes'])],
            ['key' => 'kg', 'icon' => 'scale', 'color' => 'amber', 'label' => 'Peso total', 'value' => number_format((float) $totals['weight'], 3).' kg'],
        ];

        if ($showCosts) {
            $cards[] = ['key' => 'cost', 'icon' => 'banknotes', 'color' => 'emerald', 'label' => 'Costo histórico (MXN)', 'value' => '$'.number_format((float) $totals['cost'], 2)];
        }

        $originName = $chambers->firstWhere('id', $originChamberId)?->name;
        $destName = $branches->firstWhere('id', $destBranchId)?->name;
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 {{ $showCosts ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exit-new-{{ $card['key'] }}">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-{{ $card['color'] }}-500/10 text-{{ $card['color'] }}-600 dark:text-{{ $card['color'] }}-300">
                    <flux:icon :name="$card['icon']" class="size-6" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $card['label'] }}</div>
                    <div class="truncate text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $card['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Columna izquierda: datos y captura --}}
        <div class="w-full shrink-0 space-y-6 lg:sticky lg:top-4 lg:w-96">
            <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <span class="flex size-7 items-center justify-center rounded-full bg-accent/10 text-sm font-semibold text-accent-content">1</span>
                    <flux:heading size="lg">Datos de la salida</flux:heading>
                </div>

                <flux:select wire:model.live="originChamberId" label="Cámara de origen" placeholder="Selecciona una cámara">
                    @foreach ($chambers as $chamber)
                        <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="requester" label="Solicitante" placeholder="Nombre de quien solicita" />

                <flux:select wire:model.live="destBranchId" label="Sucursal destino" placeholder="Selecciona una sucursal">
                    @foreach ($branches as $branch)
                        <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($destChambers->isNotEmpty())
                    <flux:select wire:model="destChamberId" label="Cámara destino (si va a otra cámara)">
                        <flux:select.option value="">Ninguna (entrega directa)</flux:select.option>
                        @foreach ($destChambers as $chamber)
                            <flux:select.option :value="$chamber->id">{{ $chamber->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                @if ($isSuperAdmin)
                    <flux:input wire:model="occurredAt" type="datetime-local" label="Fecha y hora" />
                @else
                    <flux:input value="Se registra al guardar" label="Fecha y hora" disabled />
                @endif

                <flux:textarea wire:model="notes" label="Observaciones" rows="2" />
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <span class="flex size-7 items-center justify-center rounded-full bg-accent/10 text-sm font-semibold text-accent-content">2</span>
                    <flux:heading size="lg">Agregar cajas</flux:heading>
                </div>

                <flux:select wire:model.live="productId" label="Producto" placeholder="Selecciona un producto">
                    @foreach ($products as $product)
                        <flux:select.option :value="$product->id">{{ $product->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="productId" />

                <flux:input wire:model="quantity" wire:keydown.enter.prevent="addProduct" label="Cantidad de cajas" type="number" min="1"
                    :description="$availableForProduct !== null ? 'Disponibles: '.number_format($availableForProduct) : null" />
                <flux:error name="quantity" />

                <div class="flex items-center justify-between gap-2">
                    <flux:button variant="ghost" wire:click="clearAll" icon="arrow-path">Limpiar</flux:button>
                    <flux:button variant="primary" wire:click="addProduct" wire:loading.attr="disabled" wire:target="addProduct" icon="plus">Agregar cajas</flux:button>
                </div>
            </div>
        </div>

        {{-- Columna derecha: lista y confirmación --}}
        <div class="min-w-0 flex-1 space-y-4">
            <div class="flex items-center gap-3">
                <span class="flex size-7 items-center justify-center rounded-full bg-accent/10 text-sm font-semibold text-accent-content">3</span>
                <flux:heading size="lg">Cajas de la salida</flux:heading>
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4 text-sm shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exit-new-route">
                <div class="min-w-0 flex-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Origen</div>
                    <div class="truncate font-medium">{{ $originName ?? 'Sin seleccionar' }}</div>
                </div>
                <flux:icon name="arrow-right" class="size-5 shrink-0 text-zinc-400" />
                <div class="min-w-0 flex-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Destino</div>
                    <div class="truncate font-medium">{{ $destName ?? 'Sin seleccionar' }}</div>
                    @if ($destChamberId)
                        <flux:badge size="sm" color="sky" class="mt-1">Transferencia a otra cámara</flux:badge>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="exit-new-table">
                <flux:table container:class="overflow-x-auto">
                    <flux:table.columns>
                        <flux:table.column>Producto</flux:table.column>
                        <flux:table.column align="end">Cajas</flux:table.column>
                        <flux:table.column align="end">Peso por caja</flux:table.column>
                        <flux:table.column align="end">Precio por caja</flux:table.column>
                        <flux:table.column align="end">Peso total</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($summaries as $index => $line)
                            <flux:table.row wire:key="line-{{ $index }}-{{ $line['product_id'] }}">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $line['product'] }}</div>
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-xs text-zinc-500">Ver códigos{{ $line['count'] > $perLine ? ' (primeros '.$perLine.')' : '' }}</summary>
                                        <div class="mt-1 flex max-h-40 flex-wrap gap-1 overflow-y-auto">
                                            @foreach (array_slice($line['box_ids'], 0, $perLine) as $boxId)
                                                <flux:badge size="sm" class="font-mono">{{ $barcodes[$boxId] ?? $boxId }}</flux:badge>
                                            @endforeach
                                        </div>
                                    </details>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums font-medium">{{ number_format($line['count']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['weight'] }} kg</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $line['cost'], 2) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">{{ $line['total_weight'] }} kg</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $index }})">Quitar</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                                    <flux:icon name="archive-box-arrow-down" class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    Aún no hay cajas en la salida.<br>
                                    Elige un producto, indica cuántas cajas y presiona "Agregar cajas".
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    <flux:icon name="printer" class="size-5" />
                    Al crear la salida se imprimirá el ticket.
                </div>
                <div class="flex items-center gap-2">
                    <flux:button :href="route('exits.index')" wire:navigate variant="ghost">Cancelar</flux:button>
                    <flux:button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" :disabled="$totals['boxes'] === 0">
                        Crear salida
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <flux:modal name="variants" class="md:w-[34rem]">
        <form wire:submit="confirmVariants" class="space-y-4">
            <flux:heading size="lg">Existen variantes de {{ $pendingProduct?->name }}</flux:heading>
            <flux:text>Hay cajas con distinto peso o precio. Indica cuántas de cada variante quieres enviar; deben sumar <strong>{{ $pendingQuantity }}</strong> cajas.</flux:text>

            <flux:error name="variants" />

            <div class="space-y-2">
                @foreach ($variants as $index => $variant)
                    <div class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="variant-{{ $index }}">
                        <div class="flex-1">
                            <div class="font-medium">{{ $variant['weight'] }} kg · ${{ number_format((float) $variant['cost'], 2) }} MXN</div>
                            <div class="text-xs text-zinc-500">{{ $variant['available'] }} disponibles</div>
                        </div>
                        <flux:input size="sm" class="w-24" type="number" min="0" max="{{ $variant['available'] }}" wire:model="variantQuantities.{{ $index }}" placeholder="0" />
                        <flux:button size="sm" type="button" variant="ghost" wire:click="fillVariant({{ $index }})">Máx.</flux:button>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost" type="button">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Agregar a la salida</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
