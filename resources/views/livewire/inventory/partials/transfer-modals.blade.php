    <flux:modal name="receive-transfer" class="w-[calc(100vw-2rem)] md:w-[44rem]">
        <form
            wire:submit="askConfirmReceive"
            class="space-y-5"
            data-test="receive-form"
            x-data="{
                search: '',
                expected: @js(collect($acting?->items ?? [])->mapWithKeys(fn ($item) => [$item->box_id => (float) $item->expected_weight_kg])->all()),
                total: {{ $acting?->items->count() ?? 0 }},
                get arrived() { return $wire.received.length; },
                get missing() { return this.total - this.arrived; },
                isReceived(id) { return $wire.received.map(String).includes(String(id)); },
                diff(id) {
                    const raw = ($wire.weights?.[id] ?? '').toString().trim();
                    if (raw === '' || isNaN(raw)) { return null; }
                    return Math.round((parseFloat(raw) - this.expected[id]) * 1000) / 1000;
                },
                markAll(value) { $wire.received = value ? Object.keys(this.expected) : []; },
            }"
        >
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-content">
                    <flux:icon name="truck" class="size-5" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg">Recibir transferencia {{ $acting?->reference }}</flux:heading>
                    <flux:text class="text-sm">
                        {{ $acting?->originChamber?->name }} <span class="text-zinc-400">→</span> {{ $acting?->destChamber?->name }}
                    </flux:text>
                </div>
            </div>

            <flux:error name="operation" />

            <div class="grid grid-cols-3 gap-3 text-center" data-test="receive-summary">
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Enviadas</div>
                    <div class="text-xl font-semibold tabular-nums" x-text="total"></div>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950/40">
                    <div class="text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Llegaron</div>
                    <div class="text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300" x-text="arrived"></div>
                </div>
                <div class="rounded-lg border p-3" :class="missing > 0 ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40' : 'border-zinc-200 dark:border-zinc-700'">
                    <div class="text-xs uppercase tracking-wide" :class="missing > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-zinc-500'">Faltan</div>
                    <div class="text-xl font-semibold tabular-nums" :class="missing > 0 ? 'text-amber-700 dark:text-amber-300' : ''" x-text="missing"></div>
                </div>
            </div>

            <div x-show="missing > 0" x-cloak class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                <flux:icon name="exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
                <span>Las cajas sin marcar quedan en tránsito y se abre una incidencia. Si el peso medido difiere del esperado, captúralo para registrarlo.</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="w-full sm:min-w-40 sm:flex-1 sm:w-auto">
                    <flux:input size="sm" x-model="search" icon="magnifying-glass" placeholder="Buscar código de caja" clearable />
                </div>
                <flux:button size="sm" type="button" variant="subtle" x-on:click="markAll(true)">Marcar todas</flux:button>
                <flux:button size="sm" type="button" variant="subtle" x-on:click="markAll(false)">Quitar todas</flux:button>
            </div>

            <div class="max-h-[40vh] space-y-2 -mx-1.5 overflow-y-auto p-1.5" data-test="receive-list">
                @foreach ($acting?->items ?? [] as $item)
                    <label
                        class="block cursor-pointer rounded-xl border p-3 transition"
                        :class="isReceived({{ $item->box_id }}) ? 'border-zinc-200 dark:border-zinc-700' : 'border-amber-300 bg-amber-50/60 dark:border-amber-700 dark:bg-amber-950/20'"
                        x-show="search.trim() === '' || @js(strtolower($item->box->barcode.' '.($item->box->product?->name ?? ''))).includes(search.trim().toLowerCase())"
                        wire:key="recv-{{ $item->box_id }}"
                        data-test="receive-row"
                    >
                        <div class="flex items-start gap-3">
                            <input type="checkbox" wire:model="received" value="{{ $item->box_id }}" class="mt-1 size-5 shrink-0 rounded" />
                            <div class="min-w-0 flex-1">
                                <div class="break-all font-mono text-sm font-medium text-zinc-900 dark:text-white">{{ $item->box->barcode }}</div>
                                <div class="text-xs text-zinc-500">{{ $item->box->product?->name }} · esperado <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ rtrim(rtrim(number_format((float) $item->expected_weight_kg, 3, '.', ','), '0'), '.') }} kg</span></div>
                            </div>
                            <span x-show="! isReceived({{ $item->box_id }})" x-cloak class="shrink-0 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">No llegó</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2 ps-8" x-show="isReceived({{ $item->box_id }})">
                            <flux:input size="sm" class="!w-36" wire:model="weights.{{ $item->box_id }}" placeholder="Peso real (kg)" inputmode="decimal" aria-label="Peso real de {{ $item->box->barcode }}" />
                            <template x-if="diff({{ $item->box_id }}) !== null && diff({{ $item->box_id }}) !== 0">
                                <span class="rounded-md bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/60 dark:text-red-300" x-text="(diff({{ $item->box_id }}) > 0 ? '+' : '') + diff({{ $item->box_id }}) + ' kg'"></span>
                            </template>
                            <template x-if="diff({{ $item->box_id }}) === 0">
                                <span class="text-xs text-emerald-600 dark:text-emerald-400">Coincide</span>
                            </template>
                        </div>
                    </label>
                @endforeach
            </div>

            <flux:textarea wire:model="receiveNotes" label="Comentarios de la recepción" rows="2" placeholder="Opcional: estado de la mercancía, faltantes, retrasos u otras observaciones." />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost" type="button">Cerrar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Confirmar recepción</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-receive" class="w-[calc(100vw-2rem)] md:w-[30rem]" data-test="confirm-receive">
        @php
            $summary = $confirmSummary;
            $hasIssues = $summary['missing'] > 0 || $summary['differences'] !== [];
        @endphp

        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $hasIssues ? 'bg-amber-500/10 text-amber-600 dark:text-amber-300' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300' }}">
                    <flux:icon name="{{ $hasIssues ? 'exclamation-triangle' : 'check-circle' }}" class="size-5" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg">¿Confirmar la recepción?</flux:heading>
                    <flux:text class="text-sm">Revisa que los datos capturados sean correctos antes de continuar.</flux:text>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Enviadas</div>
                    <div class="text-xl font-semibold tabular-nums">{{ $summary['total'] }}</div>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950/40">
                    <div class="text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Llegaron</div>
                    <div class="text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $summary['arrived'] }}</div>
                </div>
                <div class="rounded-lg border p-3 {{ $summary['missing'] > 0 ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40' : 'border-zinc-200 dark:border-zinc-700' }}">
                    <div class="text-xs uppercase tracking-wide {{ $summary['missing'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-zinc-500' }}">Faltan</div>
                    <div class="text-xl font-semibold tabular-nums {{ $summary['missing'] > 0 ? 'text-amber-700 dark:text-amber-300' : '' }}">{{ $summary['missing'] }}</div>
                </div>
            </div>

            @if ($hasIssues)
                <div class="space-y-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200" data-test="confirm-receive-warning">
                    <div class="font-medium">Se registrarán incidencias:</div>
                    <ul class="list-disc space-y-1 ps-5">
                        @if ($summary['missing'] > 0)
                            <li>{{ $summary['missing'] }} {{ $summary['missing'] === 1 ? 'caja no llegó y seguirá en tránsito' : 'cajas no llegaron y seguirán en tránsito' }}.</li>
                        @endif
                        @foreach ($summary['differences'] as $difference)
                            <li><span class="break-all font-mono text-xs">{{ $difference['barcode'] }}</span>: esperado {{ $difference['expected'] }} kg, capturado {{ $difference['actual'] }} kg.</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" data-test="confirm-receive-ok">
                    Llegaron todas las cajas y no capturaste diferencias de peso.
                </div>
            @endif

            <flux:text class="text-sm">Al confirmar, las cajas que llegaron pasan a las existencias de la cámara destino y la recepción queda registrada.</flux:text>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Revisar de nuevo</flux:button></flux:modal.close>
                <flux:button variant="primary" icon="check" wire:click="receive" wire:loading.attr="disabled" wire:target="receive" class="w-full sm:w-auto">Sí, confirmar recepción</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="cancel-transfer" class="md:w-96">
        <form wire:submit="cancel" class="space-y-6">
            <flux:heading size="lg">Cancelar transferencia {{ $acting?->reference }}</flux:heading>
            @if ($acting?->status === \App\Enums\TransferStatus::Received)
                <flux:callout variant="warning" icon="exclamation-triangle" inline>
                    <flux:callout.text>Esta transferencia ya fue recibida. Las cajas regresarán desde la cámara destino a la de origen, siempre que sigan disponibles allá sin cambios. Las incidencias abiertas se cerrarán.</flux:callout.text>
                </flux:callout>
            @else
                <flux:text>Las cajas regresan a la cámara de origen.</flux:text>
            @endif
            <flux:error name="operation" />
            <flux:textarea wire:model="reason" label="Motivo" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cerrar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Cancelar transferencia</flux:button>
            </div>
        </form>
    </flux:modal>
