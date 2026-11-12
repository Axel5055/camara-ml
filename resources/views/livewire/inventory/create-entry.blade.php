<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="arrow-down-tray" title="Entrada de mercancía" description="Captura los datos, agrega los grupos de cajas que ingresan y registra la entrada.">
        <flux:button :href="route('entries.index')" wire:navigate icon="arrow-left">Entradas</flux:button>
    </x-page-header>

    <x-draft-recovery
        draft-key="entry"
        :fields="['chamberId', 'notes', 'clientToken', 'lines', 'productId', 'boxesCount', 'weight', 'cost']"
        noun="entrada"
    />

    <flux:error name="operation" />

    @php
        $cards = [
            ['key' => 'groups', 'icon' => 'rectangle-stack', 'color' => 'indigo', 'label' => 'Grupos', 'value' => number_format(count($summaries))],
            ['key' => 'boxes', 'icon' => 'archive-box', 'color' => 'sky', 'label' => 'Cajas', 'value' => number_format($totals['boxes'])],
            ['key' => 'kg', 'icon' => 'scale', 'color' => 'amber', 'label' => 'Peso total', 'value' => number_format((float) $totals['weight'], 3).' kg'],
        ];

        if ($showCosts) {
            $cards[] = ['key' => 'cost', 'icon' => 'banknotes', 'color' => 'emerald', 'label' => 'Importe (MXN)', 'value' => '$'.number_format((float) $totals['cost'], 2)];
        }
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 {{ $showCosts ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        @foreach ($cards as $card)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entry-new-{{ $card['key'] }}">
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
                    <flux:heading size="lg">Datos de la entrada</flux:heading>
                </div>

                <flux:select wire:model="chamberId" label="Cámara de destino">
                    <flux:select.option value="">Selecciona una cámara</flux:select.option>
                    @foreach ($chambers as $chamber)
                        <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="occurredAt" type="datetime-local" label="Fecha y hora" />

                <flux:textarea wire:model="notes" label="Observaciones" rows="2" placeholder="Proveedor, número de remisión, etc." />
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <span class="flex size-7 items-center justify-center rounded-full bg-accent/10 text-sm font-semibold text-accent-content">2</span>
                    <flux:heading size="lg">Agregar cajas</flux:heading>
                </div>

                <x-product-search :products="$products" wire:key="product-search" />
                <flux:error name="productId" />

                <flux:input wire:model.live.debounce.400ms="boxesCount" label="Cantidad de cajas" type="number" min="1" />

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <flux:input wire:model.live.debounce.400ms="weight" label="Peso por caja (kg)" inputmode="decimal" placeholder="0.000" />
                    </div>
                    <div>
                        <flux:input wire:model.live.debounce.400ms="cost" label="Costo por kg (MXN)" inputmode="decimal" placeholder="0.00" />
                    </div>
                </div>

                @if ($draft)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900/40" data-test="entry-draft">
                        <span class="text-zinc-500">Este grupo:</span>
                        <span class="font-medium tabular-nums">{{ number_format($draft['boxes']) }} cajas</span>
                        <span class="font-medium tabular-nums">{{ number_format((float) $draft['weight'], 3) }} kg</span>
                        @if ($showCosts)
                            <span class="font-medium tabular-nums">${{ number_format((float) $draft['cost'], 2) }}</span>
                        @endif
                    </div>
                @endif

                <details class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                    <summary class="cursor-pointer text-sm text-zinc-500">Códigos de barras manuales (opcional)</summary>
                    <div class="mt-3 space-y-3 pb-1">
                        <flux:textarea wire:model="manual" label="Un código por línea" rows="3" />
                        <flux:input wire:model="origin" label="Procedencia de las etiquetas" />
                    </div>
                </details>

                <div class="flex items-center justify-between gap-2">
                    <flux:button variant="ghost" wire:click="clearAll" icon="arrow-path">Limpiar</flux:button>
                    <flux:button variant="primary" wire:click="addLine" wire:loading.attr="disabled" wire:target="addLine" icon="plus">Agregar cajas</flux:button>
                </div>
            </div>
        </div>

        {{-- Columna derecha: lista y confirmación --}}
        <div class="min-w-0 flex-1 space-y-4">
            <div class="flex items-center gap-3">
                <span class="flex size-7 items-center justify-center rounded-full bg-accent/10 text-sm font-semibold text-accent-content">3</span>
                <flux:heading size="lg">Grupos de la entrada</flux:heading>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="entry-new-table">
                <flux:table container:class="overflow-x-auto">
                    <flux:table.columns>
                        <flux:table.column>Producto</flux:table.column>
                        <flux:table.column align="end">Cajas</flux:table.column>
                        <flux:table.column align="end">Peso por caja</flux:table.column>
                        <flux:table.column align="end">Costo por kg</flux:table.column>
                        <flux:table.column align="end">Peso total</flux:table.column>
                        @if ($showCosts)
                            <flux:table.column align="end">Importe</flux:table.column>
                        @endif
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($summaries as $index => $line)
                            <flux:table.row wire:key="line-{{ $index }}-{{ $line['product_id'] }}">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $line['product'] }}</div>
                                    @if ($line['manual'] !== [])
                                        <flux:badge size="sm" color="amber" class="mt-1">{{ count($line['manual']) }} código(s) manual(es) · {{ $line['origin'] }}</flux:badge>
                                    @endif
                                    <details class="mt-1" @if ($needsApproval) hidden @endif>
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
                                <flux:table.cell align="end" class="tabular-nums font-medium">{{ number_format($line['boxes_count']) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['weight'] }} kg</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">${{ number_format((float) $line['cost'], 2) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $line['total_weight'] }} kg</flux:table.cell>
                                @if ($showCosts)
                                    <flux:table.cell align="end" class="tabular-nums font-medium text-zinc-900 dark:text-white">${{ number_format((float) $line['total_cost'], 2) }}</flux:table.cell>
                                @endif
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $index }})">Quitar</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell :colspan="$showCosts ? 7 : 6" class="py-12 text-center text-zinc-500">
                                    <flux:icon name="archive-box-arrow-down" class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    Aún no hay cajas en la entrada.<br>
                                    Elige un producto, captura cantidad, peso y costo, y presiona "Agregar cajas".
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            @if ($summaries !== [] && ! $needsApproval)
                <flux:callout icon="information-circle" color="zinc">
                    <flux:callout.text>Estos son los códigos de barras que se asignarán a las cajas. El consecutivo se confirma al registrar la entrada: si otro usuario registra antes, los números pueden recorrerse.</flux:callout.text>
                </flux:callout>
            @endif

            <x-save-errors :fields="['chamberId' => 'Cámara', 'occurredAt' => 'Fecha y hora', 'notes' => 'Observaciones']" />

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    <flux:icon name="{{ $needsApproval ? 'shield-check' : 'printer' }}" class="size-5" />
                    {{ $needsApproval ? 'La entrada se registrará cuando un administrador la autorice.' : 'Al registrar se imprimirá el ticket de la entrada.' }}
                </div>
                <div class="flex items-center gap-2">
                    <flux:button :href="route('entries.index')" wire:navigate variant="ghost">Cancelar</flux:button>
                    <flux:button variant="primary" icon="check" wire:click="askConfirm" wire:loading.attr="disabled" wire:target="askConfirm" :disabled="$totals['boxes'] === 0">
                        {{ $needsApproval ? 'Solicitar autorización' : 'Registrar entrada' }}
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <flux:modal name="confirm-save" class="w-[calc(100vw-2rem)] md:w-[30rem]" data-test="confirm-save">
        @php
            $confirmChamber = $chambers->firstWhere('id', $chamberId);
        @endphp

        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                    <flux:icon name="arrow-down-tray" class="size-5" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg">{{ $needsApproval ? '¿Enviar la solicitud de entrada?' : '¿Registrar la entrada?' }}</flux:heading>
                    <flux:text class="text-sm">Revisa que los datos sean correctos antes de continuar.</flux:text>
                </div>
            </div>

            <dl class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Cámara</dt><dd class="text-right font-medium">{{ $confirmChamber ? $confirmChamber->branch->name.' · '.$confirmChamber->name : '—' }}</dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Grupos</dt><dd class="font-medium tabular-nums">{{ number_format(count($summaries)) }}</dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Cajas</dt><dd class="font-medium tabular-nums">{{ number_format($totals['boxes']) }}</dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Peso total</dt><dd class="font-medium tabular-nums">{{ number_format((float) $totals['weight'], 3) }} kg</dd></div>
                @if ($showCosts)
                    <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-zinc-500">Importe</dt><dd class="font-medium tabular-nums">${{ number_format((float) $totals['cost'], 2) }}</dd></div>
                @endif
            </dl>

            @if ($needsApproval)
                <flux:text class="text-sm" data-test="entry-needs-approval">Se avisará a los administradores. Las cajas y sus códigos de barras se crean hasta que la autoricen; si la rechazan, no se registra nada.</flux:text>
            @else
                <flux:text class="text-sm">Al confirmar se crearán las cajas con sus códigos de barras. Si algún dato está mal, regresa y corrígelo antes de registrar.</flux:text>

                @include('livewire.inventory.partials.ticket-options')
            @endif

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Revisar de nuevo</flux:button></flux:modal.close>
                <flux:button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="w-full sm:w-auto">{{ $needsApproval ? 'Sí, enviar solicitud' : 'Sí, registrar entrada' }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
