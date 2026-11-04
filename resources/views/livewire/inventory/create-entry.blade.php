<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrow-down-tray" title="Entrada de mercancía" description="Captura los datos, agrega los grupos de cajas que ingresan y registra la entrada.">
        <flux:button :href="route('entries.index')" wire:navigate icon="arrow-left">Entradas</flux:button>
    </x-page-header>

    <flux:error name="operation" />

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Columna izquierda: datos y captura --}}
        <div class="w-full shrink-0 space-y-6 lg:w-96">
            <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Datos de la entrada</flux:heading>

                <flux:select wire:model="chamberId" label="Cámara de destino" placeholder="Selecciona una cámara">
                    @foreach ($chambers as $chamber)
                        <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="chamberId" />

                <flux:input wire:model="occurredAt" type="datetime-local" label="Fecha y hora" />

                <flux:textarea wire:model="notes" label="Observaciones" rows="2" />
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">Agregar cajas</flux:heading>

                <flux:select wire:model="productId" label="Producto" placeholder="Selecciona un producto">
                    @foreach ($products as $product)
                        <flux:select.option :value="$product->id">{{ $product->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="productId" />

                <flux:input wire:model="boxesCount" label="Cantidad de cajas" type="number" min="1" />
                <flux:error name="boxesCount" />

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <flux:input wire:model="weight" label="Peso por caja (kg)" inputmode="decimal" placeholder="0.000" />
                        <flux:error name="weight" />
                    </div>
                    <div>
                        <flux:input wire:model="cost" label="Costo por caja (MXN)" inputmode="decimal" placeholder="0.00" />
                        <flux:error name="cost" />
                    </div>
                </div>

                <details>
                    <summary class="cursor-pointer text-sm text-zinc-500">Códigos de barras manuales (opcional)</summary>
                    <div class="mt-3 space-y-3">
                        <flux:textarea wire:model="manual" label="Un código por línea" rows="3" />
                        <flux:error name="manual" />
                        <flux:input wire:model="origin" label="Procedencia de las etiquetas" />
                        <flux:error name="origin" />
                    </div>
                </details>

                <div class="flex items-center justify-between gap-2">
                    <flux:button variant="ghost" wire:click="clearAll">Limpiar</flux:button>
                    <flux:button wire:click="addLine" wire:loading.attr="disabled" wire:target="addLine" icon="plus">Agregar cajas</flux:button>
                </div>
            </div>
        </div>

        {{-- Columna derecha: resumen, lista y confirmación --}}
        <div class="min-w-0 flex-1 space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-xs uppercase tracking-wide">Cajas</flux:text>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($totals['boxes']) }}</div>
                </div>
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-xs uppercase tracking-wide">Peso total</flux:text>
                    <div class="mt-1 whitespace-nowrap text-2xl font-semibold tabular-nums">{{ number_format((float) $totals['weight'], 3) }} <span class="text-sm font-normal text-zinc-500">kg</span></div>
                </div>
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-xs uppercase tracking-wide">Importe</flux:text>
                    <div class="mt-1 whitespace-nowrap text-2xl font-semibold tabular-nums">${{ number_format((float) $totals['cost'], 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-2 dark:border-zinc-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Producto</flux:table.column>
                        <flux:table.column align="end">Cajas</flux:table.column>
                        <flux:table.column align="end">Peso por caja</flux:table.column>
                        <flux:table.column align="end">Costo por caja</flux:table.column>
                        <flux:table.column align="end">Peso total</flux:table.column>
                        <flux:table.column align="end">Importe</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($summaries as $index => $line)
                            <flux:table.row wire:key="line-{{ $index }}-{{ $line['product_id'] }}">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $line['product'] }}</div>
                                    @if ($line['manual'] !== [])
                                        <div class="text-xs text-zinc-500">{{ count($line['manual']) }} código(s) manual(es) · {{ $line['origin'] }}</div>
                                    @endif
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-xs text-zinc-500">Ver códigos de barras</summary>
                                        <div class="mt-1 space-y-0.5 font-mono text-xs">
                                            @if ($line['barcodes'])
                                                <div>
                                                    <span class="font-sans text-zinc-500">{{ $line['barcodes']['count'] > 1 ? 'Del' : 'Código' }}</span>
                                                    {{ $line['barcodes']['first'] }}
                                                </div>
                                                @if ($line['barcodes']['count'] > 1)
                                                    <div>
                                                        <span class="font-sans text-zinc-500">al</span>
                                                        {{ $line['barcodes']['last'] }}
                                                    </div>
                                                    <div class="font-sans text-zinc-500">({{ number_format($line['barcodes']['count']) }} códigos consecutivos)</div>
                                                @endif
                                            @endif
                                            @foreach ($line['manual'] as $code)
                                                <div>{{ $code }} <span class="font-sans text-zinc-500">(manual)</span></div>
                                            @endforeach
                                        </div>
                                    </details>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ number_format($line['boxes_count']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['weight'] }} kg</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $line['cost'], 2) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['total_weight'] }} kg</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $line['total_cost'], 2) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $index }})">Quitar</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">
                                    Aún no hay cajas en la entrada.<br>
                                    Elige un producto, captura cantidad, peso y costo, y presiona "Agregar cajas".
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            @if ($summaries !== [])
                <flux:text class="text-xs">Estos son los códigos de barras que se asignarán a las cajas. El consecutivo se confirma al registrar la entrada: si otro usuario registra antes, los números pueden recorrerse.</flux:text>
            @endif

            <div class="flex justify-end">
                <flux:button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" :disabled="$totals['boxes'] === 0">
                    Registrar entrada
                </flux:button>
            </div>
        </div>
    </div>
</section>
