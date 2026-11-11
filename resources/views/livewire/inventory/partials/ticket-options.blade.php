@if (auth()->user()->isSuperAdmin())
    <div class="space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
        <flux:checkbox wire:model.live="printTicketOnSave" label="Imprimir ticket al confirmar" data-test="print-ticket-toggle" />
        @if ($printTicketOnSave)
            <flux:input type="number" min="1" max="10" step="1" inputmode="numeric" wire:model="ticketCopies" label="Copias del ticket a imprimir" data-test="ticket-copies" />
        @endif
    </div>
@else
    <flux:input type="number" min="1" max="10" step="1" inputmode="numeric" wire:model="ticketCopies" label="Copias del ticket a imprimir" data-test="ticket-copies" />
@endif
