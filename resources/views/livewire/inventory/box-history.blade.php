<section class="mx-auto w-full max-w-7xl space-y-6">
    <x-page-header icon="archive-box" :title="$box->barcode" :description="$product->name.' · '.$product->category->name" mono>
        <flux:button :href="route('stock')" wire:navigate icon="arrow-left">Existencias</flux:button>
    </x-page-header>

    @php
        $initial = (float) $box->initial_weight_kg;
        $percent = $initial > 0 ? max(0, min(100, round((float) $box->current_weight_kg / $initial * 100))) : 0;

        $tones = [
            'green' => 'bg-green-500/10 text-green-600 dark:text-green-300',
            'red' => 'bg-red-500/10 text-red-600 dark:text-red-300',
            'sky' => 'bg-sky-500/10 text-sky-600 dark:text-sky-300',
            'emerald' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
            'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-300',
            'purple' => 'bg-purple-500/10 text-purple-600 dark:text-purple-300',
            'orange' => 'bg-orange-500/10 text-orange-600 dark:text-orange-300',
            'zinc' => 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-300',
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 {{ $showCosts ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="box-card-status">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Estado</div>
            <div class="mt-2"><flux:badge :color="$box->status->color()">{{ $box->status->label() }}</flux:badge></div>
            <div class="mt-2 text-sm text-zinc-500">
                @if ($currentChamber)
                    {{ $currentChamber->name }} · {{ $currentChamber->branch->name }}
                @else
                    Fuera de las cámaras
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="box-card-weight">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Peso actual / inicial</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $box->current_weight_kg }} <span class="text-sm font-normal text-zinc-500">/ {{ $box->initial_weight_kg }} kg</span></div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-sky-500" style="width: {{ $percent }}%"></div>
            </div>
            <div class="mt-1 text-xs text-zinc-500">{{ $percent }}% del peso original</div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="box-card-entered">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Fecha de entrada</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $box->entered_at->format('d/m/Y') }}</div>
            <div class="mt-2 text-sm text-zinc-500">{{ $box->entered_at->format('H:i') }} · {{ $movements->count() }} {{ $movements->count() === 1 ? 'movimiento' : 'movimientos' }}</div>
        </div>

        @if ($showCosts)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="box-card-cost">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Costo histórico de entrada</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">${{ number_format((float) $box->unit_cost_mxn, 2) }} <span class="text-sm font-normal text-zinc-500">MXN</span></div>
            </div>
        @endif
    </div>

    @if ($canWithdraw)
        <form wire:submit="withdraw" class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="box-withdraw-form">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-300">
                    <flux:icon name="scissors" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Retiro parcial</flux:heading>
                    <flux:text class="text-sm">Registra solo una parte del contenido. La caja conserva su código y el peso restante.</flux:text>
                </div>
            </div>
            <flux:error name="operation" />
            <div class="grid gap-3 md:grid-cols-4">
                <flux:input wire:model="weightOut" label="Peso a retirar (kg)" inputmode="decimal" />
                <flux:select wire:model="destBranchId" label="Sucursal destino" placeholder="Selecciona">
                    @foreach ($branches as $branch)
                        <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="requester" label="Solicitante" />
                <flux:input wire:model="notes" label="Observaciones" />
            </div>
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="check">Registrar retiro</flux:button>
            </div>
        </form>
    @endif

    <div class="space-y-3">
        <flux:heading size="lg">Movimientos</flux:heading>

        <ol class="relative space-y-4 border-s-2 border-zinc-200 ps-6 dark:border-zinc-700" data-test="box-timeline">
            @foreach ($movements as $movement)
                @php
                    $delta = (float) $movement->weight_after_kg - (float) $movement->weight_before_kg;
                    $from = $chamberNames[$movement->from_chamber_id] ?? null;
                    $to = $chamberNames[$movement->to_chamber_id] ?? ($branchNames[$movement->to_branch_id] ?? null);
                @endphp
                <li class="relative" wire:key="movement-{{ $movement->id }}">
                    <span class="absolute -start-[2.15rem] top-3 flex size-8 items-center justify-center rounded-full ring-4 ring-white dark:ring-zinc-900 {{ $tones[$movement->type->color()] ?? $tones['zinc'] }}">
                        <flux:icon :name="$movement->type->icon()" class="size-4" />
                    </span>

                    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" :color="$movement->type->color()">{{ $movement->type->label() }}</flux:badge>
                                @if ($movement->entry_id && isset($entryRefs[$movement->entry_id]))
                                    <a href="{{ route('entries.show', $movement->entry_id) }}" wire:navigate class="font-mono text-xs text-sky-600 hover:underline dark:text-sky-300">{{ $entryRefs[$movement->entry_id] }}</a>
                                @endif
                                @if ($movement->exit_id && isset($exitRefs[$movement->exit_id]))
                                    <a href="{{ route('exits.show', $movement->exit_id) }}" wire:navigate class="font-mono text-xs text-sky-600 hover:underline dark:text-sky-300">{{ $exitRefs[$movement->exit_id] }}</a>
                                @endif
                            </div>
                            <div class="text-right text-sm text-zinc-500" title="{{ $movement->occurred_at->diffForHumans() }}">{{ $movement->occurred_at->format('d/m/Y H:i') }}</div>
                        </div>

                        <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Origen → destino</div>
                                <div class="font-medium">{{ $from ?? '—' }} <span class="text-zinc-400">→</span> {{ $to ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Peso (kg)</div>
                                <div class="font-medium tabular-nums">
                                    {{ $movement->weight_before_kg }} <span class="text-zinc-400">→</span> {{ $movement->weight_after_kg }}
                                    @if ($delta != 0)
                                        <span class="{{ $delta < 0 ? 'text-red-600 dark:text-red-300' : 'text-green-600 dark:text-green-300' }}">({{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 3) }})</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">Estado resultante</div>
                                <flux:badge size="sm" :color="$movement->status_after->color()">{{ $movement->status_after->label() }}</flux:badge>
                            </div>
                        </div>

                        @if ($movement->notes)
                            <div class="mt-3 rounded-lg bg-zinc-50 p-2.5 text-sm text-zinc-600 dark:bg-zinc-900/40 dark:text-zinc-300">{{ $movement->notes }}</div>
                        @endif

                        <div class="mt-3 flex items-center gap-1.5 text-xs text-zinc-500">
                            <flux:icon name="user" class="size-4" />
                            {{ $movement->user->name }}
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
