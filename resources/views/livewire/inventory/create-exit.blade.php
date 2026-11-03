<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrow-up-tray" title="Salida de mercancía" description="Captura los datos, agrega las cajas que salen y crea la salida.">
        <flux:button :href="route('exits.index')" wire:navigate icon="arrow-left">Salidas</flux:button>
    </x-page-header>

    <flux:error name="operation" />

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Columna izquierda: datos y captura --}}
        <div class="w-full shrink-0 space-y-6 lg:w-96">
            <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Datos de la salida</flux:heading>

                <flux:select wire:model.live="originChamberId" label="Cámara de origen" placeholder="Selecciona una cámara">
                    <flux:select.option value="">Selecciona una cámara</flux:select.option>
                    @foreach ($chambers as $chamber)
                        <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="requester" label="Solicitante" placeholder="Nombre de quien solicita" />

                <flux:select wire:model.live="destBranchId" label="Sucursal destino" placeholder="Selecciona una sucursal">
                    <flux:select.option value="">Selecciona una sucursal</flux:select.option>
                    @foreach ($branches as $branch)
                        <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($destChambers->isNotEmpty())
                    <flux:select wire:model="destChamberId" label="Cámara destino (si va a otra cámara)" placeholder="Ninguna (entrega directa)">
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

            <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Agregar cajas</flux:heading>

                <flux:select wire:model.live="productId" label="Producto" placeholder="Selecciona un producto">
                    <flux:select.option value="">Selecciona un producto</flux:select.option>
                    @foreach ($products as $product)
                        <flux:select.option :value="$product->id">{{ $product->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="productId" />

                <flux:input wire:model="quantity" wire:keydown.enter.prevent="addProduct" label="Cantidad de cajas" type="number" min="1"
                    :description="$availableForProduct !== null ? 'Disponibles: '.number_format($availableForProduct) : null" />
                <flux:error name="quantity" />

                <div class="flex items-center justify-between gap-2">
                    <flux:button variant="ghost" wire:click="clearAll">Limpiar</flux:button>
                    <flux:button wire:click="addProduct" wire:loading.attr="disabled" wire:target="addProduct" icon="plus">Agregar cajas</flux:button>
                </div>
            </div>
        </div>

        {{-- Columna derecha: resumen, lista y confirmación --}}
        <div class="min-w-0 flex-1 space-y-4">
            <div class="grid gap-3 {{ $showCosts ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-xs uppercase tracking-wide">Cajas</flux:text>
                    <div class="mt-1 whitespace-nowrap text-2xl font-semibold tabular-nums">{{ number_format($totals['boxes']) }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-xs uppercase tracking-wide">Peso total</flux:text>
                    <div class="mt-1 whitespace-nowrap text-2xl font-semibold tabular-nums">{{ number_format((float) $totals['weight'], 3) }} <span class="text-sm font-normal text-zinc-500">kg</span></div>
                </div>
                @if ($showCosts)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="text-xs uppercase tracking-wide">Costo histórico</flux:text>
                        <div class="mt-1 whitespace-nowrap text-2xl font-semibold tabular-nums">${{ number_format((float) $totals['cost'], 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-zinc-200 p-2 dark:border-zinc-700">
                <flux:table>
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
                                    <div class="font-medium">{{ $line['product'] }}</div>
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-xs text-zinc-500">Ver códigos{{ $line['count'] > $perLine ? ' (primeros '.$perLine.')' : '' }}</summary>
                                        <div class="mt-1 flex max-h-40 flex-wrap gap-1 overflow-y-auto">
                                            @foreach (array_slice($line['box_ids'], 0, $perLine) as $boxId)
                                                <flux:badge size="sm" class="font-mono">{{ $barcodes[$boxId] ?? $boxId }}</flux:badge>
                                            @endforeach
                                        </div>
                                    </details>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ number_format($line['count']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['weight'] }} kg</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $line['cost'], 2) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['total_weight'] }} kg</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $index }})">Quitar</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">
                                    Aún no hay cajas en la salida.<br>
                                    Elige un producto, indica cuántas cajas y presiona "Agregar cajas".
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" :disabled="$totals['boxes'] === 0">
                    Crear salida
                </flux:button>
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
