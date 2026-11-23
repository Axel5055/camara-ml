<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-page-header icon="clipboard-document-list" title="Nueva solicitud de salida" description="Pide producto a una cámara; el almacenista la autoriza o la rechaza.">
        <flux:button :href="route('exit-requests.index')" wire:navigate icon="arrow-left">Mis solicitudes</flux:button>
    </x-page-header>

    <x-draft-recovery
        draft-key="exit-request"
        :fields="['branchId', 'chamberId', 'notes', 'clientToken', 'lines', 'productId', 'quantity']"
        noun="solicitud"
    />

    <flux:error name="operation" />

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <div class="w-full shrink-0 space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs lg:w-96 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center gap-3">
                <span class="flex size-7 items-center justify-center rounded-full bg-accent/10 text-sm font-semibold text-accent-content">1</span>
                <flux:heading size="lg">¿Qué necesitas?</flux:heading>
            </div>

            @if ($isSuperAdmin)
                <flux:select wire:model="branchId" label="Sucursal que solicita" data-test="request-branch">
                    <flux:select.option value="">Selecciona una sucursal</flux:select.option>
                    @foreach ($branches as $branch)
                        <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="branchId" />
            @endif

            <flux:select wire:model.live="chamberId" label="Cámara a la que le pides" data-test="request-chamber">
                <flux:select.option value="">Selecciona una cámara</flux:select.option>
                @foreach ($chambers as $chamber)
                    <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="chamberId" />

            <x-product-search :products="$products" wire:key="request-product-search" data-test="request-product" />
            <flux:error name="productId" />

            @if ($productId !== null && $chamberId !== null)
                <flux:text class="text-sm" data-test="request-available">
                    Disponibles ahora en la cámara: <span class="font-semibold tabular-nums">{{ number_format((int) ($available[$productId] ?? 0)) }}</span> {{ (int) ($available[$productId] ?? 0) === 1 ? 'caja' : 'cajas' }}
                </flux:text>
            @endif

            <flux:input wire:model="quantity" wire:keydown.enter.prevent="addProduct" label="Cantidad de cajas" type="number" min="1" inputmode="numeric" data-test="request-quantity" />
            <flux:error name="quantity" />

            <flux:button type="button" variant="primary" icon="plus" wire:click="addProduct" class="w-full" data-test="request-add">Agregar a la solicitud</flux:button>
        </div>

        <div class="min-w-0 flex-1 space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center gap-3">
                <span class="flex size-7 items-center justify-center rounded-full bg-accent/10 text-sm font-semibold text-accent-content">2</span>
                <flux:heading size="lg">Tu solicitud</flux:heading>
            </div>

            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" data-test="request-lines">
                <flux:table container:class="overflow-x-auto">
                    <flux:table.columns>
                        <flux:table.column>Producto</flux:table.column>
                        <flux:table.column align="end">Pides</flux:table.column>
                        <flux:table.column align="end">Disponibles</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($lines as $index => $line)
                            <flux:table.row :key="'line-'.$line['product_id']">
                                <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $line['product'] }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ number_format($line['boxes']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums text-zinc-500">{{ $chamberId !== null ? number_format((int) ($available[$line['product_id']] ?? 0)) : '—' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $index }})" aria-label="Quitar" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">Agrega los productos que necesitas.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            <flux:textarea wire:model="notes" label="Comentario (opcional)" rows="2" placeholder="Para cuándo lo necesitas, referencias, etc." />
            <flux:error name="notes" />

            <div class="flex flex-col-reverse items-stretch justify-between gap-3 sm:flex-row sm:items-center">
                <flux:text class="text-sm">Total: <span class="font-semibold tabular-nums">{{ number_format($totalBoxes) }}</span> {{ $totalBoxes === 1 ? 'caja' : 'cajas' }}</flux:text>
                <flux:button type="button" variant="primary" icon="paper-airplane" wire:click="save" wire:loading.attr="disabled" wire:target="save" :disabled="$lines === []" data-test="request-save">Enviar solicitud</flux:button>
            </div>
        </div>
    </div>
</section>
