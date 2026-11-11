<section class="w-full space-y-6">
    <x-page-header icon="adjustments-horizontal" title="Ajustes de inventario" description="Correcciones autorizadas al inventario.">
        <flux:button variant="primary" icon="plus" wire:click="create">Nuevo ajuste</flux:button>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="adjustments-total">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                <flux:icon.adjustments-horizontal class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Ajustes</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['total']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="adjustments-weight">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300">
                <flux:icon.scale class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Correcciones de peso</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['weight']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="adjustments-losses">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-red-500/10 text-red-600 dark:text-red-300">
                <flux:icon.trash class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Pérdidas y cajas dañadas</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['losses']) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="adjustments-found">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                <flux:icon.magnifying-glass-circle class="size-6" />
            </div>
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Hallazgos y estados</div>
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totals['found']) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div class="space-y-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable placeholder="Buscar por referencia, código de caja o motivo" />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:select wire:model.live="typeFilter" label="Tipo de ajuste">
                    <flux:select.option value="all">Todos los tipos</flux:select.option>
                    @foreach ($types as $typeOption)
                        <flux:select.option :value="$typeOption->value">{{ $typeOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live="from" type="date" label="Desde" />
                <flux:input wire:model.live="to" type="date" label="Hasta" />
            </div>
        </div>

        @if ($hasFilters)
            <div class="mt-4 flex justify-end">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Limpiar filtros</flux:button>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" data-test="adjustments-table">
        <flux:table :paginate="$adjustments" container:class="overflow-x-auto">
            <flux:table.columns>
                <flux:table.column>Referencia</flux:table.column>
                <flux:table.column>Fecha</flux:table.column>
                <flux:table.column>Caja</flux:table.column>
                <flux:table.column>Tipo</flux:table.column>
                <flux:table.column>Antes → después</flux:table.column>
                <flux:table.column>Motivo</flux:table.column>
                <flux:table.column>Autorizó</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($adjustments as $adjustment)
                    <flux:table.row :key="$adjustment->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/40">
                        <flux:table.cell>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">{{ $adjustment->reference }}</span>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <div class="text-zinc-900 dark:text-white">{{ $adjustment->occurred_at->format('d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500">{{ $adjustment->occurred_at->format('H:i') }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('boxes.show', $adjustment->box) }}" wire:navigate class="rounded-md bg-zinc-100 px-2 py-1 font-mono text-xs text-zinc-700 hover:underline dark:bg-zinc-700 dark:text-zinc-200">{{ $adjustment->box->barcode }}</a>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$adjustment->type->color()">{{ $adjustment->type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap tabular-nums">
                            <div class="text-zinc-500">{{ $adjustment->weight_before_kg }} kg · {{ \App\Enums\BoxStatus::tryFrom((string) $adjustment->status_before)?->label() ?? $adjustment->status_before }}</div>
                            <div class="font-medium text-zinc-900 dark:text-white">→ {{ $adjustment->weight_after_kg }} kg · {{ \App\Enums\BoxStatus::tryFrom((string) $adjustment->status_after)?->label() ?? $adjustment->status_after }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="min-w-56 max-w-sm whitespace-normal">{{ $adjustment->reason }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $users[$adjustment->authorized_by] ?? '' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">No hay ajustes que coincidan con los filtros.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="adjustment-form" class="w-full md:w-[34rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Nuevo ajuste</flux:heading>
                <flux:text class="mt-1">El ajuste queda registrado con el antes y el después, el motivo y tu usuario.</flux:text>
            </div>

            <flux:error name="operation" />

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Caja a ajustar</div>

                <flux:input wire:model.live.debounce.400ms="barcode" label="Código de barras de la caja" icon="qr-code" class="font-mono uppercase" placeholder="Escanea el código" />

                @if ($box)
                    <flux:callout icon="information-circle" color="zinc">
                        <flux:callout.heading>{{ $box->product->name }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ $box->current_weight_kg }} de {{ $box->initial_weight_kg }} kg ·
                            {{ $box->status->label() }}{{ $box->currentChamber ? ' en '.$box->currentChamber->name : '' }}
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Ajuste</div>

                <flux:select wire:model.live="type" label="Tipo de ajuste">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if (in_array($type, ['weight_correction', 'found', 'status_fix']))
                    <flux:input wire:model="newWeight" label="Nuevo peso (kg)" icon="scale" inputmode="decimal" />
                @endif
                @if ($type === 'status_fix')
                    <flux:select wire:model="newStatus" label="Nuevo estado">
                        <flux:select.option value="">Selecciona</flux:select.option>
                        @foreach ($statuses as $status)
                            <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                @if (in_array($type, ['found', 'status_fix']))
                    <flux:select wire:model="chamberId" label="Cámara (si queda disponible)">
                        <flux:select.option value="">Selecciona</flux:select.option>
                        @foreach ($chambers as $chamber)
                            <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Justificación</div>
                <flux:textarea wire:model="reason" label="Motivo" rows="3" placeholder="Qué se encontró y por qué se corrige." required />
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" type="button">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Aplicar ajuste</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
