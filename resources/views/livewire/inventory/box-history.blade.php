<section class="w-full space-y-6">
    <x-page-header icon="archive-box" :title="$box->barcode" :description="$product->name" mono>
        <flux:button :href="route('stock')" wire:navigate icon="arrow-left">Existencias</flux:button>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Estado</flux:text>
            <flux:badge :color="$box->status->color()">{{ $box->status->label() }}</flux:badge>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Peso actual / inicial</flux:text>
            <flux:heading>{{ $box->current_weight_kg }} / {{ $box->initial_weight_kg }} kg</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Fecha de entrada</flux:text>
            <flux:heading>{{ $box->entered_at->format('d/m/Y H:i') }}</flux:heading>
        </div>
        @if ($showCosts)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>Costo histórico de entrada</flux:text>
                <flux:heading>${{ number_format((float) $box->unit_cost_mxn, 2) }} MXN</flux:heading>
            </div>
        @endif
    </div>

    @if ($canWithdraw)
        <form wire:submit="withdraw" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">Retiro parcial</flux:heading>
            <flux:text>Registra solo una parte del contenido. La caja conserva su código y el peso restante.</flux:text>
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
            <flux:button type="submit" variant="primary">Registrar retiro</flux:button>
        </form>
    @endif

    <flux:heading size="lg">Movimientos</flux:heading>
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Fecha</flux:table.column>
            <flux:table.column>Movimiento</flux:table.column>
            <flux:table.column>Origen → destino</flux:table.column>
            <flux:table.column>Peso (kg)</flux:table.column>
            <flux:table.column>Estado</flux:table.column>
            <flux:table.column>Responsable</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($movements as $movement)
                <flux:table.row :key="$movement->id">
                    <flux:table.cell>{{ $movement->occurred_at->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $movement->type->label() }}<div class="text-xs text-zinc-500">{{ $movement->notes }}</div></flux:table.cell>
                    <flux:table.cell>
                        {{ $chamberNames[$movement->from_chamber_id] ?? '—' }} →
                        {{ $chamberNames[$movement->to_chamber_id] ?? ($branchNames[$movement->to_branch_id] ?? '—') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $movement->weight_before_kg }} → {{ $movement->weight_after_kg }}</flux:table.cell>
                    <flux:table.cell>{{ $movement->status_after->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $movement->user->name }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
