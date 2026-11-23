<div>
    <flux:modal.trigger name="exit-request-summary">
        <flux:button icon="printer" data-test="summary-open">Imprimir ticket mesa</flux:button>
    </flux:modal.trigger>

    <flux:modal name="exit-request-summary" class="w-[calc(100vw-2rem)] md:w-[30rem]">
        <form wire:submit="print" class="space-y-5">
            <div>
                <flux:heading size="lg">Concentrado de solicitudes</flux:heading>
                <flux:text class="mt-1">Un solo ticket con lo autorizado por solicitudes de sucursal, sumado por producto, de un día o de un rango de fechas.</flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button type="button" size="sm" wire:click="today" data-test="summary-today">Hoy</flux:button>
                <flux:button type="button" size="sm" wire:click="yesterday" data-test="summary-yesterday">Ayer</flux:button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="from" type="date" label="Desde" data-test="summary-from" />
                <flux:input wire:model="to" type="date" label="Hasta" data-test="summary-to" />
            </div>

            <flux:select wire:model="chamberId" label="Cámara de origen" data-test="summary-chamber">
                <flux:select.option value="">{{ $chambers->count() === 1 ? $chambers->first()->name : ($seesAllChambers ? 'Todas las cámaras' : 'Todas mis cámaras') }}</flux:select.option>
                @if ($chambers->count() > 1)
                    @foreach ($chambers as $chamber)
                        <flux:select.option :value="$chamber->id">{{ $chamber->branch->name }} · {{ $chamber->name }}</flux:select.option>
                    @endforeach
                @endif
            </flux:select>

            <flux:select wire:model="branchId" label="Sucursal que solicitó" data-test="summary-branch">
                <flux:select.option value="">Todas las sucursales</flux:select.option>
                @foreach ($branches as $branch)
                    <flux:select.option :value="$branch->id">{{ $branch->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="number" min="1" max="10" step="1" inputmode="numeric" wire:model="ticketCopies" label="Copias del ticket a imprimir" data-test="summary-copies" />

            <flux:error name="from" />
            <flux:error name="to" />
            <flux:error name="chamberId" />
            <flux:error name="branchId" />
            <flux:error name="ticketCopies" />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close><flux:button variant="ghost" type="button" class="w-full sm:w-auto">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="printer" class="w-full sm:w-auto" data-test="summary-print">Imprimir ticket</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
