<div wire:poll.60s data-test="temperature-reminder-root">
    @if ($chambers->isNotEmpty())
        {{-- A floating notice, not a modal: the page underneath keeps working. --}}
        <div class="fixed inset-x-3 bottom-3 z-40 space-y-3 rounded-2xl border border-amber-400 bg-white p-4 shadow-2xl sm:left-auto sm:right-4 sm:w-96 dark:bg-zinc-800" role="status" data-test="temperature-reminder">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-300">
                    <flux:icon.bell-alert class="size-6" />
                </div>
                <div class="min-w-0 text-sm">
                    <div class="font-semibold text-zinc-900 dark:text-white">Recordatorio: temperatura de la tarde</div>
                    <div class="break-words text-zinc-600 dark:text-zinc-300">Falta registrar {{ $chambers->pluck('name')->join(', ', ' y ') }}.</div>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button size="sm" variant="ghost" wire:click="dismiss" data-test="temperature-reminder-dismiss">Más tarde</flux:button>
                <flux:button size="sm" variant="primary" icon="arrow-right" :href="route('temperatures.index')" wire:navigate data-test="temperature-reminder-go">Registrar ahora</flux:button>
            </div>
        </div>
    @endif
</div>
