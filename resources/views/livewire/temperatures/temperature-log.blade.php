<section class="w-full space-y-6">
    <x-page-header icon="chart-bar-square" title="Temperaturas" :description="$ownOnly ? 'Las temperaturas que has registrado. No se pueden modificar.' : 'Bitácora de temperaturas por cámara, registradas por la mañana y por la tarde.'" />

    @if ($afternoonPending->isNotEmpty())
        <div class="space-y-3 rounded-xl border border-amber-400/60 bg-amber-50 p-4 shadow-xs dark:bg-amber-500/10" data-test="temperature-afternoon-panel">
            <div>
                <flux:heading size="lg">Registrar la temperatura de la tarde de hoy</flux:heading>
                <flux:text>No bloquea el sistema. Solo puedes registrarla hoy, {{ now()->format('d/m/Y') }}.</flux:text>
            </div>
            <ul class="space-y-2">
                @foreach ($afternoonPending as $pendingChamber)
                    <li wire:key="afternoon-{{ $pendingChamber->id }}" class="flex flex-col gap-2 rounded-lg bg-white p-3 sm:flex-row sm:items-center sm:justify-between dark:bg-zinc-800">
                        <div class="min-w-0">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $pendingChamber->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $pendingChamber->branch->name }}</div>
                        </div>
                        <flux:button variant="primary" icon="plus" class="w-full sm:w-auto" wire:click="askToCapture({{ $pendingChamber->id }})" data-test="temperature-capture-open">Registrar temperatura</flux:button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('operate-inventory')
        <flux:modal name="temperature-capture" class="w-full md:w-[28rem]">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Temperatura de la tarde</flux:heading>
                    <flux:text class="mt-1">{{ $capturing?->name }} · la fecha y la hora se registran automáticamente al guardar.</flux:text>
                </div>

                @error('operation')
                    <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
                @enderror

                @if (! $captureConfirming)
                    <form wire:submit="reviewCapture" class="space-y-4">
                        <div x-show="! $wire.captureUnmeasured" class="space-y-2">
                            <flux:label>Temperatura (°C)</flux:label>
                            <div class="flex items-start gap-2">
                                <flux:button type="button" class="shrink-0" aria-label="Cambiar el signo"
                                    x-on:click="const value = String($wire.captureValue ?? ''); $wire.set('captureValue', value.startsWith('-') ? value.slice(1) : '-' + value, false)">+/−</flux:button>
                                <div class="min-w-0 flex-1">
                                    <flux:input type="text" inputmode="decimal" autocomplete="off" maxlength="5" placeholder="Ej. -18.0" wire:model="captureValue" data-test="temperature-capture-value" />
                                </div>
                            </div>
                            <flux:error name="captureValue" />
                        </div>

                        <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                            <input type="checkbox" wire:model="captureUnmeasured" class="size-4 rounded border-zinc-300 accent-accent">
                            No se pudo medir
                        </label>

                        <div x-show="$wire.captureUnmeasured" x-cloak>
                            <flux:textarea wire:model="captureReason" label="Motivo (obligatorio)" rows="2" maxlength="500" />
                        </div>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Cancelar</flux:button></flux:modal.close>
                            <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto">Continuar</flux:button>
                        </div>
                    </form>
                @else
                    <div class="space-y-4">
                        <p class="text-center text-sm font-medium text-zinc-900 dark:text-white">Revisa antes de guardar. Después no podrás modificarlo.</p>
                        <div class="rounded-xl border border-zinc-200 p-4 text-center dark:border-zinc-700">
                            @if ($captureUnmeasured)
                                <div class="text-lg font-semibold text-amber-600 dark:text-amber-300">No se pudo medir</div>
                                <div class="break-words text-sm text-zinc-600 dark:text-zinc-300">{{ trim($captureReason) }}</div>
                            @else
                                <div class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ \App\Support\Temperature::parse($captureValue) }} °C</div>
                            @endif
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <flux:button variant="ghost" icon="pencil-square" wire:click="backToCapture">Corregir</flux:button>
                            <flux:button variant="primary" icon="check" wire:click="saveCapture" wire:loading.attr="disabled" wire:target="saveCapture" data-test="temperature-capture-save">Sí, registrar</flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </flux:modal>
    @endcan

    @if ($chambers->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">No tienes cámaras asignadas.</div>
    @else
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <flux:select wire:model.live="chamberId" label="Cámara" data-test="temperature-chamber-filter">
                        @foreach ($chambers as $option)
                            <flux:select.option :value="$option->id">{{ $option->branch->name }} · {{ $option->name }}{{ $option->is_active ? '' : ' (inactiva)' }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:input type="date" wire:model.live="dateFrom" label="Desde" :max="now()->toDateString()" />
                <flux:input type="date" wire:model.live="dateTo" label="Hasta" :max="now()->toDateString()" />
            </div>
            <flux:text class="mt-3 text-xs">Se muestran como máximo 92 días a la vez.</flux:text>
        </div>

        <div @class(['grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4', 'xl:grid-cols-6' => ! $ownOnly, 'xl:grid-cols-5' => $ownOnly])>
            @foreach (array_filter([
                ['key' => 'readings', 'label' => 'Registros', 'color' => 'text-zinc-900 dark:text-white'],
                ...array_map(fn (\App\Enums\TemperatureLevel $level): array => ['key' => $level->value, 'label' => $level->label(), 'color' => $level->textClass()], \App\Enums\TemperatureLevel::alerts()),
                ['key' => 'unmeasured', 'label' => 'Sin medición', 'color' => 'text-amber-600 dark:text-amber-300'],
                $ownOnly ? null : ['key' => 'missing', 'label' => 'Sin registro', 'color' => 'text-zinc-900 dark:text-white'],
            ]) as $tile)
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="temperature-total-{{ $tile['key'] }}">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $tile['label'] }}</div>
                    <div class="text-2xl font-semibold {{ $tile['color'] }}">{{ number_format($totals[$tile['key']]) }}</div>
                </div>
            @endforeach
        </div>

        <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="temperature-chart">
            <flux:heading size="lg">Gráfica de {{ $chamber?->name }}</flux:heading>
            <div wire:key="temperature-chart-{{ md5(json_encode([$chamberId, $chart])) }}">
                <x-temperature-chart :points="$chart" />
            </div>
        </div>

        <div class="space-y-3" data-test="temperature-log">
            <flux:heading size="lg">Bitácora</flux:heading>

            {{-- Table from tablet up. --}}
            <div class="hidden overflow-hidden rounded-xl border border-accent/30 bg-white shadow-xs md:block dark:border-zinc-700 dark:bg-zinc-800">
                <flux:table container:class="overflow-x-auto">
                    <flux:table.columns>
                        <flux:table.column>Fecha</flux:table.column>
                        <flux:table.column>Mañana</flux:table.column>
                        <flux:table.column>Tarde</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($days as $day)
                            <flux:table.row :key="'row-'.$chamberId.'-'.$day['date']->toDateString()">
                                <flux:table.cell class="whitespace-nowrap align-top font-medium text-zinc-900 dark:text-white">{{ $day['date']->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell class="w-2/5 whitespace-normal align-top">@include('livewire.temperatures.temperature-slot', ['slot' => $day['morning']])</flux:table.cell>
                                <flux:table.cell class="w-2/5 whitespace-normal align-top">@include('livewire.temperatures.temperature-slot', ['slot' => $day['afternoon']])</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="py-10 text-center text-zinc-500">No hay registros en este periodo.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Cards on phones. --}}
            <div class="space-y-3 md:hidden">
                @forelse ($days as $day)
                    <div wire:key="card-{{ $chamberId }}-{{ $day['date']->toDateString() }}" class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="temperature-card">
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $day['date']->format('d/m/Y') }}</div>
                        <div class="mt-2 space-y-3">
                            <div>
                                <div class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Mañana</div>
                                @include('livewire.temperatures.temperature-slot', ['slot' => $day['morning']])
                            </div>
                            <div>
                                <div class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">Tarde</div>
                                @include('livewire.temperatures.temperature-slot', ['slot' => $day['afternoon']])
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">No hay registros en este periodo.</div>
                @endforelse
            </div>
        </div>
    @endif

    @if ($canCorrect)
        <flux:modal name="temperature-correction" class="w-full md:w-[30rem]">
            <form wire:submit="correct" class="space-y-5">
                <div>
                    <flux:heading size="lg">Corregir temperatura</flux:heading>
                    <flux:text class="mt-1">El valor capturado se conserva; la corrección queda con tu nombre y no se puede borrar.</flux:text>
                </div>

                @if ($correcting)
                    <div class="rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                        <div class="font-medium text-zinc-900 dark:text-white">{{ $correcting->chamber->name }} · {{ $correcting->reading_date->format('d/m/Y') }} · {{ $correcting->shift->label() }}</div>
                        <div class="text-zinc-500">Valor actual: {{ $correcting->effectiveTemperature() === null ? 'no se pudo medir' : $correcting->effectiveTemperature().' °C' }}</div>
                    </div>
                @endif

                @error('operation')
                    <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
                @enderror

                <flux:input wire:model="correctionValue" label="Temperatura correcta (°C)" placeholder="Ej. -18.0" autocomplete="off" required data-test="correction-value" />
                <flux:textarea wire:model="correctionReason" label="Motivo" rows="3" maxlength="500" required data-test="correction-reason" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="correct" data-test="correction-submit">Guardar corrección</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
