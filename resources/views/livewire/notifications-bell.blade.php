<div
    wire:poll.60s
    class="relative"
    data-test="notifications-bell"
    x-data="{ open: false, tab: '{{ $isRequester || $pendingCount > 0 || $items->isEmpty() ? 'pending' : 'stock' }}' }"
    x-on:keydown.escape.window="open = false"
    x-on:click.outside="open = false"
>
    <flux:button variant="ghost" size="sm" square icon="bell" aria-label="Notificaciones" class="relative" x-on:click="open = ! open" x-bind:aria-expanded="open">
        @if ($count > 0)
            <span class="absolute end-0 top-0 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-4 text-white ring-2 ring-zinc-100 dark:ring-zinc-900" data-test="bell-count">{{ $count > 99 ? '99+' : $count }}</span>
        @endif
    </flux:button>

    <div
        x-show="open"
        x-transition.opacity.duration.100ms
        style="display: none;"
        class="fixed inset-x-2 top-14 z-50 overflow-hidden rounded-lg border border-zinc-200 bg-white p-[.3125rem] shadow-lg sm:absolute sm:inset-x-auto sm:end-0 sm:top-auto sm:mt-2 sm:w-96 dark:border-zinc-600 dark:bg-zinc-700"
        data-test="bell-panel"
    >
        <div class="px-3 py-2">
            <flux:heading size="sm">Notificaciones</flux:heading>
        </div>

        @unless ($isRequester)
            <div class="flex gap-1 px-1 pb-2" role="tablist">
                <button type="button" role="tab" x-on:click="tab = 'pending'" x-bind:class="tab === 'pending' ? 'bg-zinc-200 text-zinc-900 dark:bg-zinc-600 dark:text-white' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-600/60'" class="flex min-w-0 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium" data-test="bell-tab-pending">
                    Pendientes
                    @if ($pendingCount > 0)
                        <span class="rounded-full bg-amber-600 px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $pendingCount }}</span>
                    @endif
                </button>
                <button type="button" role="tab" x-on:click="tab = 'stock'" x-bind:class="tab === 'stock' ? 'bg-zinc-200 text-zinc-900 dark:bg-zinc-600 dark:text-white' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-600/60'" class="flex min-w-0 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium" data-test="bell-tab-stock">
                    Stock bajo
                    @if ($items->isNotEmpty())
                        <span class="rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $items->count() }}</span>
                    @endif
                </button>
            </div>
        @endunless

        <div class="my-1 h-px bg-zinc-200 dark:bg-zinc-600"></div>

        <div x-show="tab === 'pending'" style="display: none;" class="max-h-80 overflow-y-auto" data-test="bell-pending">
            @if ($exitRequests->isNotEmpty())
                <div data-test="bell-exit-requests">
                    <div class="mx-1 mb-1 mt-2 flex items-center justify-between gap-2 rounded-md border-s-4 border-sky-500 bg-sky-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">
                        <span class="truncate">{{ $isRequester ? 'Tus solicitudes de salida' : 'Salidas por decidir' }}</span>
                        <span class="shrink-0">{{ $exitRequests->count() }}</span>
                    </div>
                    @foreach ($exitRequests as $exitRequest)
                        <a href="{{ route('exit-requests.index', ['estado' => $exitRequest->isPending() ? 'pending' : 'all']) }}" wire:navigate wire:key="bell-exit-request-{{ $exitRequest->id }}" x-on:click="open = false" class="block rounded-md px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-600/60">
                            <div class="flex w-full items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-900 dark:text-white">
                                        @if ($isRequester)
                                            {{ $exitRequest->reference() }} · {{ $exitRequest->total_boxes_requested }} {{ $exitRequest->total_boxes_requested === 1 ? 'caja' : 'cajas' }}
                                        @else
                                            {{ $exitRequest->branch->name }} pide {{ $exitRequest->total_boxes_requested }} {{ $exitRequest->total_boxes_requested === 1 ? 'caja' : 'cajas' }}
                                        @endif
                                    </div>
                                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-300">{{ $exitRequest->chamber->name }} · {{ $exitRequest->created_at->format('d/m/Y H:i') }}</div>
                                </div>
                                <flux:badge size="sm" class="shrink-0" :color="$exitRequest->status->color()">{{ $exitRequest->status->label() }}</flux:badge>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($myExitRequests->isNotEmpty())
                <div data-test="bell-my-exit-requests">
                    <div class="mx-1 mb-1 mt-2 flex items-center justify-between gap-2 rounded-md border-s-4 border-rose-500 bg-rose-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-rose-800 dark:bg-rose-500/15 dark:text-rose-200">
                        <span class="truncate">Tus solicitudes de salida</span>
                        <span class="shrink-0">{{ $myExitRequests->count() }}</span>
                    </div>
                    @foreach ($myExitRequests as $exitRequest)
                        <a href="{{ route('exit-requests.mine', ['estado' => $exitRequest->isPending() ? 'pending' : 'all']) }}" wire:navigate wire:key="bell-my-exit-request-{{ $exitRequest->id }}" x-on:click="open = false" class="block rounded-md px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-600/60">
                            <div class="flex w-full items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $exitRequest->reference() }} · {{ $exitRequest->total_boxes_requested }} {{ $exitRequest->total_boxes_requested === 1 ? 'caja' : 'cajas' }}</div>
                                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-300">{{ $exitRequest->chamber->name }} · {{ $exitRequest->created_at->format('d/m/Y H:i') }}</div>
                                </div>
                                <flux:badge size="sm" class="shrink-0" :color="$exitRequest->status->color()">{{ $exitRequest->status->label() }}</flux:badge>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($requests->isNotEmpty())
                <div data-test="bell-requests">
                    <div class="mx-1 mb-1 mt-2 flex items-center justify-between gap-2 rounded-md border-s-4 border-emerald-500 bg-emerald-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">
                        <span class="truncate">{{ $canSeeTransfers ? 'Tus solicitudes de entrada' : 'Entradas por autorizar' }}</span>
                        <span class="shrink-0">{{ $requests->count() }}</span>
                    </div>
                    @foreach ($requests as $request)
                        <a href="{{ route('entry-requests.index', ['estado' => $request->isPending() ? 'pending' : 'all']) }}" wire:navigate wire:key="bell-request-{{ $request->id }}" x-on:click="open = false" class="block rounded-md px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-600/60">
                            <div class="flex w-full items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-900 dark:text-white">
                                        @if ($canSeeTransfers)
                                            {{ $request->total_boxes }} {{ $request->total_boxes === 1 ? 'caja' : 'cajas' }} · {{ $request->chamber->name }}
                                        @else
                                            {{ $request->user->name }} pide registrar {{ $request->total_boxes }} {{ $request->total_boxes === 1 ? 'caja' : 'cajas' }}
                                        @endif
                                    </div>
                                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-300">{{ $request->chamber->name }} · {{ $request->chamber->branch->name }} · {{ $request->created_at->format('d/m/Y H:i') }}</div>
                                </div>
                                <flux:badge size="sm" class="shrink-0" :color="$request->status->color()">{{ $request->status->label() }}</flux:badge>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($transfers->isNotEmpty())
                <div data-test="bell-transfers">
                    <div class="mx-1 mb-1 mt-2 flex items-center justify-between gap-2 rounded-md border-s-4 border-indigo-500 bg-indigo-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-200">
                        <span class="truncate">Transferencias en tránsito</span>
                        <span class="shrink-0">{{ $transfers->count() }}</span>
                    </div>
                    @foreach ($transfers as $transfer)
                        @php($incoming = in_array($transfer->dest_chamber_id, $chamberIds, true))
                        <a href="{{ route('transfers.index', ['estado' => 'in_transit']) }}" wire:navigate wire:key="bell-transfer-{{ $transfer->id }}" x-on:click="open = false" class="block rounded-md px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-600/60">
                            <div class="flex w-full items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-900 dark:text-white">
                                        @if ($incoming)
                                            {{ $transfer->originChamber->branch->name }} te envió {{ $transfer->items_count }} {{ $transfer->items_count === 1 ? 'caja' : 'cajas' }}
                                        @else
                                            Enviaste {{ $transfer->items_count }} {{ $transfer->items_count === 1 ? 'caja' : 'cajas' }} a {{ $transfer->destChamber->branch->name }}
                                        @endif
                                    </div>
                                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-300">{{ $transfer->reference }} · {{ $transfer->originChamber->name }} → {{ $transfer->destChamber->name }}</div>
                                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-300">{{ $transfer->dispatched_at->format('d/m/Y H:i') }}</div>
                                </div>
                                <flux:badge size="sm" class="shrink-0 whitespace-nowrap" :color="$incoming ? 'indigo' : 'amber'">{{ $incoming ? 'Por recibir' : 'Esperando recepción' }}</flux:badge>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($exitRequests->isEmpty() && $myExitRequests->isEmpty() && $requests->isEmpty() && $transfers->isEmpty())
                <div class="px-3 py-6 text-center text-sm text-zinc-500">No tienes pendientes.</div>
            @endif
        </div>

        @unless ($isRequester)
            <div x-show="tab === 'stock'" style="display: none;" class="max-h-80 overflow-y-auto" data-test="bell-list">
                <flux:text class="px-3 pt-2 text-xs">Productos con {{ $threshold }} cajas o menos disponibles.</flux:text>
                @forelse ($items as $item)
                    <a href="{{ route('stock') }}" wire:navigate wire:key="bell-{{ $item->chamber_id }}-{{ $item->product_id }}" x-on:click="open = false" class="block rounded-md px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-600/60">
                        <div class="flex w-full items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $item->product }}</div>
                                <div class="truncate text-xs text-zinc-500 dark:text-zinc-300">{{ $item->chamber }} · {{ $item->branch }}</div>
                            </div>
                            <flux:badge size="sm" class="shrink-0" :color="$item->boxes === 0 ? 'red' : 'amber'">
                                {{ $item->boxes === 0 ? 'Agotado' : $item->boxes.' '.($item->boxes === 1 ? 'caja' : 'cajas') }}
                            </flux:badge>
                        </div>
                    </a>
                @empty
                    <div class="px-3 py-6 text-center text-sm text-zinc-500">No hay productos con stock bajo.</div>
                @endforelse
            </div>
        @endunless

        <div class="my-1 h-px bg-zinc-200 dark:bg-zinc-600"></div>

        <a href="{{ $isRequester ? route('exit-requests.index') : route('dashboard') }}" wire:navigate x-on:click="open = false" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-zinc-800 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-600/60">
            <flux:icon.chart-bar class="size-4 text-zinc-500 dark:text-zinc-300" />
            {{ $isRequester ? 'Ver mis solicitudes' : 'Ver el panel' }}
        </a>
    </div>
</div>
