<div wire:poll.30s data-test="approved-entry-prompt-root">
    @if ($request)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/60 p-4" role="dialog" aria-modal="true" data-test="approved-entry-prompt">
            <div class="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 text-center shadow-xl dark:bg-zinc-800">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                    <flux:icon.check-circle class="size-8" />
                </div>
                <div class="space-y-1">
                    <div class="text-lg font-semibold text-zinc-900 dark:text-white">Entrada autorizada</div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        <span class="font-mono">{{ $request->entry->reference }}</span> · {{ $request->chamber->name }}<br>
                        {{ number_format($request->total_boxes) }} {{ $request->total_boxes === 1 ? 'caja' : 'cajas' }} · {{ $request->total_weight_kg }} kg<br>
                        Autorizó {{ $request->decider?->name }}
                    </p>
                </div>

                <div class="text-left">
                    <flux:input type="number" min="1" max="10" step="1" inputmode="numeric" wire:model="ticketCopies" label="Copias del ticket a imprimir" data-test="approved-ticket-copies" />
                </div>

                <div class="flex flex-col gap-2">
                    <flux:button variant="primary" icon="printer" wire:click="printTicket" wire:loading.attr="disabled" wire:target="printTicket" data-test="approved-print">Imprimir ticket</flux:button>
                    <flux:button variant="ghost" wire:click="dismiss" wire:loading.attr="disabled" wire:target="dismiss" data-test="approved-dismiss">Ahora no</flux:button>
                </div>
                <p class="text-xs text-zinc-500">Si ahora no, podrás imprimirlo después desde Entradas &gt; Solicitudes.</p>
            </div>
        </div>
    @endif
</div>
