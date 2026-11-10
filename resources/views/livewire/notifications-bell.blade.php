<div
    wire:poll.60s
    class="relative"
    data-test="notifications-bell"
    x-data="{ open: false, tab: '{{ $requestsToAttend > 0 ? 'requests' : ($canSeeTransfers && $transfers->isNotEmpty() ? 'transfers' : ($requests->isNotEmpty() ? 'requests' : 'stock')) }}' }"
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
        class="absolute end-0 z-50 mt-2 w-96 max-w-[calc(100vw-2rem)] rounded-lg border border-zinc-200 bg-white p-[.3125rem] shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
        data-test="bell-panel"
    >
        <div class="px-3 py-2">
            <flux:heading size="sm">Notificaciones</flux:heading>
        </div>

        <div class="flex gap-1 px-1 pb-2" role="tablist">
            <button type="button" role="tab" x-on:click="tab = 'requests'" x-bind:class="tab === 'requests' ? 'bg-zinc-200 text-zinc-900 dark:bg-zinc-600 dark:text-white' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-600/60'" class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium" data-test="bell-tab-requests">
                Entradas
                @if ($requestsToAttend > 0)
                    <span class="rounded-full bg-amber-600 px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $requestsToAttend }}</span>
                @endif
            </button>
            @if ($canSeeTransfers)
                <button type="button" role="tab" x-on:click="tab = 'transfers'" x-bind:class="tab === 'transfers' ? 'bg-zinc-200 text-zinc-900 dark:bg-zinc-600 dark:text-white' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-600/60'" class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium" data-test="bell-tab-transfers">
                    Transferencias
                    @if ($transfers->isNotEmpty())
                        <span class="rounded-full bg-indigo-600 px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $transfers->count() }}</span>
                    @endif
                </button>
            @endif
                <button type="button" role="tab" x-on:click="tab = 'stock'" x-bind:class="tab === 'stock' ? 'bg-zinc-200 text-zinc-900 dark:bg-zinc-600 dark:text-white' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-600/60'" class="flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium" data-test="bell-tab-stock">
                    Stock bajo
                    @if ($items->isNotEmpty())
                        <span class="rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-4 text-white">{{ $items->count() }}</span>
                    @endif
                </button>
        </div>

        <div class="my-1 h-px bg-zinc-200 dark:bg-zinc-600"></div>

        <div x-show="tab === 'requests'" style="display: none;" class="max-h-80 overflow-y-auto" data-test="bell-requests">
            <flux:text class="px-3 pt-2 text-xs">{{ $canSeeTransfers ? 'Tus solicitudes de entrada.' : 'Entradas que esperan tu autorización.' }}</flux:text>
            @forelse ($requests as $request)
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
                        <flux:badge size="sm" :color="$request->status->color()">{{ $request->status->label() }}</flux:badge>
                    </div>
                </a>
            @empty
                <div class="px-3 py-6 text-center text-sm text-zinc-500">No hay solicitudes de entrada.</div>
            @endforelse
        </div>

        @if ($canSeeTransfers)
            <div x-show="tab === 'transfers'" style="display: none;" class="max-h-80 overflow-y-auto" data-test="bell-transfers">
                <flux:text class="px-3 pt-2 text-xs">Envíos en tránsito hacia o desde tus cámaras.</flux:text>
                @forelse ($transfers as $transfer)
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
                            <flux:badge size="sm" :color="$incoming ? 'indigo' : 'amber'">{{ $incoming ? 'Por recibir' : 'Esperando recepción' }}</flux:badge>
                        </div>
                    </a>
                @empty
                    <div class="px-3 py-6 text-center text-sm text-zinc-500">No hay transferencias en tránsito.</div>
                @endforelse
            </div>
        @endif

        <div x-show="tab === 'stock'" style="display: none;" class="max-h-80 overflow-y-auto" data-test="bell-list">
            <flux:text class="px-3 pt-2 text-xs">Productos con {{ $threshold }} cajas o menos disponibles.</flux:text>
            @forelse ($items as $item)
                <a href="{{ route('stock') }}" wire:navigate wire:key="bell-{{ $item->chamber_id }}-{{ $item->product_id }}" x-on:click="open = false" class="block rounded-md px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-600/60">
                    <div class="flex w-full items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $item->product }}</div>
                            <div class="truncate text-xs text-zinc-500 dark:text-zinc-300">{{ $item->chamber }} · {{ $item->branch }}</div>
                        </div>
                        <flux:badge size="sm" :color="$item->boxes === 0 ? 'red' : 'amber'">
                            {{ $item->boxes === 0 ? 'Agotado' : $item->boxes.' '.($item->boxes === 1 ? 'caja' : 'cajas') }}
                        </flux:badge>
                    </div>
                </a>
            @empty
                <div class="px-3 py-6 text-center text-sm text-zinc-500">No hay productos con stock bajo.</div>
            @endforelse
        </div>

        <div class="my-1 h-px bg-zinc-200 dark:bg-zinc-600"></div>

        <a href="{{ route('dashboard') }}" wire:navigate x-on:click="open = false" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-zinc-800 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-600/60">
            <flux:icon.chart-bar class="size-4 text-zinc-500 dark:text-zinc-300" />
            Ver el panel
        </a>
    </div>
</div>
