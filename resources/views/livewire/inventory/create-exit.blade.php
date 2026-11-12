<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header
        :icon="$isTransfer ? 'arrows-right-left' : 'arrow-up-tray'"
        :title="$isTransfer ? 'Nueva transferencia' : 'Salida de mercancía'"
        :description="$isTransfer ? 'Envía cajas de una cámara a otra; quedan en tránsito hasta que el destino las reciba.' : 'Captura los datos, agrega las cajas que salen y crea la salida.'"
    >
        <flux:button :href="route($isTransfer ? 'transfers.index' : 'exits.index')" wire:navigate icon="arrow-left">{{ $isTransfer ? 'Transferencias' : 'Salidas' }}</flux:button>
    </x-page-header>

    <x-draft-recovery
        :draft-key="$isTransfer ? 'transfer' : 'exit'"
        :fields="['originChamberId', $isTransfer ? 'destChamberId' : 'destBranchId', 'deliveryChamberId', 'requester', 'notes', 'clientToken', 'lines', 'productId', 'quantity']"
        :noun="$isTransfer ? 'transferencia' : 'salida'"
    />

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
        $destName = $isTransfer
            ? ($destChambers->firstWhere('id', $destChamberId) ? $destChambers->firstWhere('id', $destChamberId)->branch->name.' · '.$destChambers->firstWhere('id', $destChamberId)->name : null)
            : ($branches->firstWhere('id', $destBranchId)?->name.($deliveryChambers->firstWhere('id', $deliveryChamberId) ? ' · Cámara '.$deliveryChambers->firstWhere('id', $deliveryChamberId)->name : ''));
        $word = $isTransfer ? 'la transferencia' : 'la salida';
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
                    <flux:heading size="lg">Datos de {{ $word }}</flux:heading>
                </div>

                <flux:select wire:model.live="originChamberId" label="Cámara de origen">
                    <flux:select.option value="">Selecciona una cámara</flux:select.option>
                    @foreach ($chambers as $chamber)
                        <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="requester" label="Solicitante" placeholder="Nombre de quien solicita" />

                @if ($isTransfer)
                    <flux:select wire:model.live="destChamberId" label="Cámara destino">
                        <flux:select.option value="">Selecciona una cámara</flux:select.option>
                        @foreach ($destChambers as $chamber)
                            <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:select wire:model.live="destBranchId" label="Sucursal destino">
                        <flux:select.option value="">Selecciona una sucursal</flux:select.option>
                        @foreach ($branches as $branch)
                            <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="deliveryChamberId" label="Cámara de la sucursal" :disabled="$deliveryChambers->isEmpty()"
                        description="Solo se habilita si la sucursal destino tiene cámaras disponibles." data-test="exit-delivery-chamber">
                        <flux:select.option value="">Sin cámara · salida directa</flux:select.option>
                        @foreach ($deliveryChambers as $chamber)
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

                <x-product-search :products="$products" wire:key="product-search-{{ $originChamberId }}" />
                <flux:error name="productId" />

                <flux:input wire:model="quantity" wire:keydown.enter.prevent="addProduct" label="Cantidad de cajas" type="number" min="1"
                    :description="$availableForProduct !== null ? 'Disponibles: '.number_format($availableForProduct) : null" />

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
                <flux:heading size="lg">Cajas de {{ $word }}</flux:heading>
            </div>

            <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 text-sm shadow-xs sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-800" data-test="exit-new-route">
                <div class="min-w-0 flex-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Origen</div>
                    <div class="truncate font-medium">{{ $originName ?? 'Sin seleccionar' }}</div>
                </div>
                <flux:icon name="arrow-right" class="hidden size-5 shrink-0 text-zinc-400 sm:block" />
                <flux:icon name="arrow-down" class="mx-auto size-5 shrink-0 text-zinc-400 sm:hidden" />
                <div class="min-w-0 flex-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/40">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Destino</div>
                    <div class="truncate font-medium">{{ $destName ?? 'Sin seleccionar' }}</div>
                    @if ($isTransfer)
                        <span class="mt-1 inline-block rounded-md bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-950/60 dark:text-sky-300">Quedará en tránsito hasta su recepción</span>
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
                                    Aún no hay cajas en {{ $word }}.<br>
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
                    Al crear {{ $word }} se imprimirá el ticket.
                </div>
                <div class="flex items-center gap-2">
                    <flux:button :href="route($isTransfer ? 'transfers.index' : 'exits.index')" wire:navigate variant="ghost">Cancelar</flux:button>
                    <flux:button variant="primary" icon="check" wire:click="askConfirm" wire:loading.attr="disabled" wire:target="askConfirm" :disabled="$totals['boxes'] === 0">
                        {{ $isTransfer ? 'Enviar transferencia' : 'Crear salida' }}
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <flux:modal name="variants" class="w-[calc(100vw-2rem)] md:w-[40rem]">
        @php
            $chosenTotal = collect($variantQuantities)->sum(fn ($quantity): int => max(0, (int) $quantity));
            $complete = $chosenTotal === $pendingQuantity;
            $progress = $pendingQuantity > 0 ? min(100, $chosenTotal / $pendingQuantity * 100) : 0;
        @endphp
        <form wire:submit="confirmVariants" class="space-y-5" data-test="variants-form">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                    <flux:icon name="squares-2x2" class="size-5" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg" class="truncate">{{ $pendingProduct?->name }}</flux:heading>
                    <flux:text class="text-sm">Hay cajas con distinto peso o precio. Reparte las <strong>{{ $pendingQuantity }}</strong> cajas {{ $isTransfer ? 'de la transferencia' : 'de la salida' }} entre estas variantes.</flux:text>
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="variants-progress">
                <div class="mb-2 flex items-baseline justify-between text-sm">
                    <span class="text-zinc-500">Cajas repartidas</span>
                    <span class="font-semibold tabular-nums {{ $complete ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-900 dark:text-white' }}">{{ $chosenTotal }} de {{ $pendingQuantity }}</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                    <div class="h-2 rounded-full transition-all {{ $complete ? 'bg-emerald-500' : ($chosenTotal > $pendingQuantity ? 'bg-red-500' : 'bg-amber-500') }}" style="width: {{ $progress }}%"></div>
                </div>
            </div>

            <flux:error name="variants" />

            <div class="max-h-[50vh] space-y-3 -mx-1.5 overflow-y-auto p-1.5">
                @foreach ($variants as $index => $variant)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 {{ (int) ($variantQuantities[$index] ?? 0) > 0 ? 'bg-accent/5 border-accent/40' : '' }}" wire:key="variant-{{ $index }}" data-test="variant-card">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Peso por caja</div>
                                <div class="mt-0.5 whitespace-nowrap text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ rtrim(rtrim(number_format((float) $variant['weight'], 3, '.', ','), '0'), '.') }} <span class="text-sm font-normal text-zinc-500">kg</span></div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Costo por caja</div>
                                <div class="mt-0.5 whitespace-nowrap text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">${{ number_format((float) $variant['cost'], 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                            <flux:badge size="sm" color="zinc" icon="archive-box">{{ number_format($variant['available']) }} disponibles</flux:badge>
                            <div class="flex items-center gap-2">
                                <flux:input size="sm" class="!w-24" type="number" min="0" max="{{ $variant['available'] }}" wire:model.live.debounce.300ms="variantQuantities.{{ $index }}" placeholder="0" aria-label="Cajas de esta variante" />
                                <flux:button size="sm" type="button" variant="subtle" wire:click="fillVariant({{ $index }})">Máx.</flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost" type="button">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="plus">Agregar a {{ $word }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-save" class="w-[calc(100vw-2rem)] md:w-[30rem]" data-test="confirm-save">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                    <flux:icon name="{{ $isTransfer ? 'arrows-right-left' : 'arrow-up-tray' }}" class="size-5" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg">{{ $isTransfer ? '¿Enviar la transferencia?' : '¿Registrar la salida?' }}</flux:heading>
                    <flux:text class="text-sm">Revisa que los datos sean correctos antes de continuar.</flux:text>
                </div>
            </div>

            <dl class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Origen</dt><dd class="text-right font-medium">{{ $originName ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Destino</dt><dd class="text-right font-medium">{{ $destName ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Solicitante</dt><dd class="text-right font-medium">{{ $requester !== '' ? $requester : '—' }}</dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Cajas</dt><dd class="font-medium tabular-nums">{{ number_format($totals['boxes']) }}</dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Peso total</dt><dd class="font-medium tabular-nums">{{ number_format((float) $totals['weight'], 3) }} kg</dd></div>
                @if ($showCosts)
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Costo histórico</dt><dd class="font-medium tabular-nums">${{ number_format((float) $totals['cost'], 2) }}</dd></div>
                @endif
            </dl>

            @if ($isTransfer)
                <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    Las cajas saldrán de la cámara de origen y quedarán en tránsito hasta que la cámara destino confirme la recepción.
                </div>
            @else
                <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    La salida se confirma al instante: las cajas dejan las existencias de la cámara de origen.
                </div>
            @endif

            <flux:text class="text-sm">Si algún dato está mal, regresa y corrígelo antes de continuar.</flux:text>

            @include('livewire.inventory.partials.ticket-options')

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Revisar de nuevo</flux:button></flux:modal.close>
                <flux:button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="w-full sm:w-auto">{{ $isTransfer ? 'Sí, enviar transferencia' : 'Sí, registrar salida' }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
