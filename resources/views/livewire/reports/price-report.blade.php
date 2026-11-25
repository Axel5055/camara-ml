<section class="w-full space-y-6">
    <x-page-header icon="currency-dollar" title="Precios por kilo" description="Consulta del precio por kilo más reciente de cada producto, tenga o no existencias, y su historial de cambios." />

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-product-search :products="$allProducts" placeholder="Todos los productos" emptyMessage="No hay productos con ese nombre." data-test="price-product-search" wire:key="price-product-search" />
            <flux:select wire:model.live="categoryId" label="Categoría" data-test="price-category">
                <flux:select.option value="">Todas</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="price-table">
        <flux:table :paginate="$products" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Producto</flux:table.column>
                <flux:table.column align="end">Precio por kg actual</flux:table.column>
                <flux:table.column>Desde</flux:table.column>
                <flux:table.column>Cambio</flux:table.column>
                <flux:table.column align="end">Cambios de precio</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($products as $product)
                    @php
                        $latest = $product->price_changes->last();
                    @endphp
                    <flux:table.row wire:key="price-{{ $product->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $product->name }}@unless ($product->is_active) <flux:badge size="sm" color="zinc" class="ms-1">Inactivo</flux:badge>@endunless</div>
                            <div class="text-xs text-zinc-500"><span class="font-mono">{{ $product->code }}</span> · {{ $product->category->name }}</div>
                        </flux:table.cell>
                        @if ($latest)
                            <flux:table.cell align="end" data-test="price-current"><flux:badge size="sm" color="emerald" class="tabular-nums">${{ number_format((float) $latest['price'], 2) }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ \Illuminate\Support\Carbon::parse($latest['since'])->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                @if ($latest['change'] === null)
                                    <span class="text-zinc-400">Primer precio</span>
                                @elseif ((float) $latest['change'] > 0)
                                    <flux:badge size="sm" color="red" icon="arrow-trending-up" class="tabular-nums">+${{ number_format((float) $latest['change'], 2) }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green" icon="arrow-trending-down" class="tabular-nums">-${{ number_format(abs((float) $latest['change']), 2) }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($product->price_changes->count()) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="clock" wire:click="showHistory({{ $product->id }})" data-test="price-history-open">Historial</flux:button>
                            </flux:table.cell>
                        @else
                            <flux:table.cell colspan="5" class="text-zinc-400">Sin precio registrado todavía.</flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">Ningún producto coincide con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="price-history" class="w-[calc(100vw-2rem)] md:w-[32rem]" data-test="price-history-modal">
        @if ($historyProduct)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Historial de precios</flux:heading>
                    <flux:text class="mt-1">{{ $historyProduct->name }} · {{ $historyProduct->category->name }}</flux:text>
                </div>

                @if ($history->isEmpty())
                    <flux:text class="text-zinc-500">Este producto todavía no tiene precios registrados.</flux:text>
                @else
                    <div class="max-h-[50vh] overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm" data-test="price-history-table">
                            <thead class="sticky top-0 bg-accent text-accent-foreground">
                                <tr>
                                    <th class="px-4 py-2 text-start font-semibold">Desde</th>
                                    <th class="px-3 py-2 text-end font-semibold">Precio por kg</th>
                                    <th class="px-4 py-2 text-end font-semibold">Cambio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($history as $change)
                                    <tr class="even:bg-amber-100/70 dark:even:bg-zinc-700/40">
                                        <td class="whitespace-nowrap px-4 py-2">
                                            {{ \Illuminate\Support\Carbon::parse($change['since'])->format('d/m/Y') }}
                                            @if ($loop->first)
                                                <flux:badge size="sm" color="sky" class="ms-1">Actual</flux:badge>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-end"><flux:badge size="sm" color="emerald" class="tabular-nums">${{ number_format((float) $change['price'], 2) }}</flux:badge></td>
                                        <td class="whitespace-nowrap px-4 py-2 text-end">
                                            @if ($change['change'] === null)
                                                <span class="text-zinc-400">Primer precio</span>
                                            @elseif ((float) $change['change'] > 0)
                                                <flux:badge size="sm" color="red" icon="arrow-trending-up" class="tabular-nums">+${{ number_format((float) $change['change'], 2) }}</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="green" icon="arrow-trending-down" class="tabular-nums">-${{ number_format(abs((float) $change['change']), 2) }}</flux:badge>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:modal.close><flux:button variant="ghost">Cerrar</flux:button></flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</section>
