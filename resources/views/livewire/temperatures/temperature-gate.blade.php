<div wire:poll.30s="check" data-test="temperature-gate-root">
    @if ($chambers->isNotEmpty())
        {{-- A native modal dialog sits above any other modal and makes the rest of the page inert. It has no way to
             close: Escape is ignored, and if the browser closes it anyway it opens again. --}}
        <dialog
            wire:ignore.self
            wire:key="temperature-dialog"
            x-data
            x-init="$el.showModal()"
            x-on:cancel.prevent
            x-on:keydown.escape.prevent.stop
            x-on:close="$el.isConnected && $el.showModal()"
            class="m-auto max-h-[92dvh] w-[calc(100%-1.5rem)] max-w-md overflow-y-auto rounded-2xl bg-white p-0 shadow-xl backdrop:bg-zinc-950/85 dark:bg-zinc-800"
            aria-labelledby="temperature-gate-title"
            data-test="temperature-gate"
        >
            <div class="space-y-5 p-5 sm:p-6">
                <div class="space-y-1 text-center">
                    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-300">
                        <flux:icon.cube class="size-8" />
                    </div>
                    <h2 id="temperature-gate-title" class="text-lg font-semibold text-zinc-900 dark:text-white">Primera temperatura del día</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ $chambers->count() === 1 ? 'Registra la temperatura de tu cámara para continuar.' : 'Registra la temperatura de tus cámaras para continuar.' }}
                    </p>
                    <p class="text-xs text-zinc-500" data-test="temperature-gate-date">{{ now()->format('d/m/Y') }} · la fecha y la hora se registran automáticamente al guardar</p>
                </div>

                @error('operation')
                    <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" data-test="temperature-gate-error" />
                @enderror

                @if (! $confirming)
                    <form wire:submit="review" class="space-y-4">
                        @foreach ($chambers as $chamber)
                            <div wire:key="temperature-chamber-{{ $chamber->id }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="temperature-chamber">
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $chamber->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $chamber->branch->name }}</div>
                                </div>

                                <div x-show="! $wire.unmeasured[{{ $chamber->id }}]" class="space-y-2">
                                    <flux:label>Temperatura (°C)</flux:label>
                                    <div class="flex items-start gap-2">
                                        {{-- Phone decimal keypads have no minus key, hence the sign button. --}}
                                        <flux:button type="button" class="shrink-0" aria-label="Cambiar el signo" data-test="temperature-sign"
                                            x-on:click="const value = String($wire.readings[{{ $chamber->id }}] ?? ''); $wire.set('readings.{{ $chamber->id }}', value.startsWith('-') ? value.slice(1) : '-' + value, false)">+/−</flux:button>
                                        <div class="min-w-0 flex-1">
                                            <flux:input type="text" inputmode="decimal" autocomplete="off" maxlength="5" placeholder="Ej. -18.0" wire:model="readings.{{ $chamber->id }}" data-test="temperature-input" />
                                        </div>
                                    </div>
                                    <flux:error name="readings.{{ $chamber->id }}" />
                                </div>

                                <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                                    <input type="checkbox" wire:model="unmeasured.{{ $chamber->id }}" class="size-4 rounded border-zinc-300 accent-accent" data-test="temperature-unmeasured">
                                    No se pudo medir
                                </label>

                                <div x-show="$wire.unmeasured[{{ $chamber->id }}]" x-cloak>
                                    <flux:textarea wire:model="reasons.{{ $chamber->id }}" label="Motivo (obligatorio)" rows="2" maxlength="500" placeholder="Ej. El termómetro está descompuesto." data-test="temperature-reason" />
                                </div>
                            </div>
                        @endforeach

                        <flux:button type="submit" variant="primary" icon="check" class="w-full" wire:loading.attr="disabled" wire:target="review" data-test="temperature-review">Continuar</flux:button>
                    </form>
                @else
                    <div class="space-y-4" data-test="temperature-confirm">
                        <p class="text-center text-sm font-medium text-zinc-900 dark:text-white">Revisa antes de guardar. Después no podrás modificarlo.</p>

                        <ul class="space-y-2">
                            @foreach ($chambers as $chamber)
                                <li wire:key="temperature-summary-{{ $chamber->id }}" class="rounded-xl border border-zinc-200 p-4 text-center dark:border-zinc-700">
                                    <div class="text-sm text-zinc-500">{{ $chamber->name }}</div>
                                    @if ($unmeasured[$chamber->id])
                                        <div class="text-lg font-semibold text-amber-600 dark:text-amber-300">No se pudo medir</div>
                                        <div class="break-words text-sm text-zinc-600 dark:text-zinc-300">{{ trim($reasons[$chamber->id]) }}</div>
                                    @else
                                        <div class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-test="temperature-confirm-value">{{ \App\Support\Temperature::parse($readings[$chamber->id]) }} °C</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <div class="flex flex-col gap-2">
                            <flux:button variant="primary" icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-test="temperature-save">Sí, registrar</flux:button>
                            <flux:button variant="ghost" icon="pencil-square" wire:click="back" data-test="temperature-back">Corregir</flux:button>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="border-t border-zinc-200 pt-3 text-center dark:border-zinc-700">
                    @csrf
                    <flux:button type="submit" size="sm" variant="ghost" icon="arrow-right-start-on-rectangle" data-test="temperature-logout">Cerrar sesión</flux:button>
                </form>
            </div>
        </dialog>
    @endif
</div>
