<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-900">
        <flux:sidebar sticky collapsible="mobile" class="dark border-e border-zinc-700 bg-zinc-800 dark:border-zinc-700 dark:bg-zinc-800">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @php($warehouseNav = in_array(auth()->user()->role->value, ['warehouse', 'admin'], true))
                <flux:sidebar.group heading="Inventario" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Panel</flux:sidebar.item>
                    <flux:sidebar.item icon="archive-box" :href="route('stock')" :current="request()->routeIs('stock', 'boxes.show')" wire:navigate>Existencias</flux:sidebar.item>
                    <div x-data='{ open: {{ $warehouseNav ? "true" : '$persist(true).as("nav-entries")' }} }' data-test="nav-entries">
                        <flux:sidebar.item as="button" type="button" icon="arrow-down-tray" x-on:click="open = ! open" x-bind:aria-expanded="open" class="w-full">
                            <span class="flex w-full items-center justify-between gap-2">
                                Entradas
                                <flux:icon name="chevron-down" class="size-4 text-zinc-400 transition-transform duration-200" x-bind:class="open ? '' : '-rotate-90'" />
                            </span>
                        </flux:sidebar.item>
                        <div x-show="open" x-collapse class="ms-5 mt-0.5 grid gap-0.5 border-s border-zinc-700 ps-2">
                            <flux:sidebar.item icon="list-bullet" :href="route('entries.index')" :current="request()->routeIs('entries.index', 'entries.show')" wire:navigate>Todas las entradas</flux:sidebar.item>
                            @php($pendingRequests = \App\Models\EntryRequest::query()->where('status', 'pending')->when(auth()->user()->role === \App\Enums\UserRole::Warehouse, fn ($query) => $query->where('user_id', auth()->id()))->count())
                            <flux:sidebar.item icon="shield-check" :href="route('entry-requests.index')" :current="request()->routeIs('entry-requests.index')" :badge="$pendingRequests > 0 ? $pendingRequests : null" badge-color="amber" wire:navigate data-test="nav-entry-requests">Solicitudes</flux:sidebar.item>
                            @can('operate-inventory')
                                <flux:sidebar.item icon="plus-circle" :href="route('entries.create')" :current="request()->routeIs('entries.create')" wire:navigate>Nueva entrada</flux:sidebar.item>
                            @endcan
                        </div>
                    </div>
                    <div x-data='{ open: {{ $warehouseNav ? "true" : '$persist(true).as("nav-exits")' }} }' data-test="nav-exits">
                        <flux:sidebar.item as="button" type="button" icon="arrow-up-tray" x-on:click="open = ! open" x-bind:aria-expanded="open" class="w-full">
                            <span class="flex w-full items-center justify-between gap-2">
                                Salidas
                                <flux:icon name="chevron-down" class="size-4 text-zinc-400 transition-transform duration-200" x-bind:class="open ? '' : '-rotate-90'" />
                            </span>
                        </flux:sidebar.item>
                        <div x-show="open" x-collapse class="ms-5 mt-0.5 grid gap-0.5 border-s border-zinc-700 ps-2">
                            <flux:sidebar.item icon="list-bullet" :href="route('exits.index')" :current="request()->routeIs('exits.index', 'exits.show')" wire:navigate>Todas las salidas</flux:sidebar.item>
                            @can('operate-inventory')
                                <flux:sidebar.item icon="plus-circle" :href="route('exits.create')" :current="request()->routeIs('exits.create')" wire:navigate>Nueva salida</flux:sidebar.item>
                            @endcan
                        </div>
                    </div>
                    @can('viewAny', \App\Models\Transfer::class)
                        <div x-data='{ open: {{ $warehouseNav ? "true" : '$persist(true).as("nav-transfers")' }} }' data-test="nav-transfers">
                            <flux:sidebar.item as="button" type="button" icon="arrows-right-left" x-on:click="open = ! open" x-bind:aria-expanded="open" class="w-full">
                                <span class="flex w-full items-center justify-between gap-2">
                                    Transferencias
                                    <flux:icon name="chevron-down" class="size-4 text-zinc-400 transition-transform duration-200" x-bind:class="open ? '' : '-rotate-90'" />
                                </span>
                            </flux:sidebar.item>
                            <div x-show="open" x-collapse class="ms-5 mt-0.5 grid gap-0.5 border-s border-zinc-700 ps-2">
                                @php($pendingTransfers = \App\Models\Transfer::query()->where('status', \App\Enums\TransferStatus::InTransit)->when(auth()->user()->role === \App\Enums\UserRole::Warehouse, fn ($query) => $query->whereIn('dest_chamber_id', \App\Models\Chamber::query()->accessibleBy(auth()->user())->select('id')))->count())
                                <flux:sidebar.item icon="list-bullet" :href="route('transfers.index')" :current="request()->routeIs('transfers.index')" :badge="$pendingTransfers > 0 ? $pendingTransfers : null" badge-color="amber" wire:navigate :data-test="$pendingTransfers > 0 ? 'nav-pending-transfers' : null">Todas las transferencias</flux:sidebar.item>
                                @can('operate-inventory')
                                    <flux:sidebar.item icon="plus-circle" :href="route('transfers.create')" :current="request()->routeIs('transfers.create')" wire:navigate>Nueva transferencia</flux:sidebar.item>
                                @endcan
                            </div>
                        </div>
                    @endcan
                </flux:sidebar.group>

                <flux:sidebar.group heading="Equipo" class="grid">
                    <flux:sidebar.item icon="printer" :href="route('printers')" :current="request()->routeIs('printers')" wire:navigate>Impresoras</flux:sidebar.item>
                </flux:sidebar.group>

                @can('view-catalogs')
                    <flux:sidebar.group heading="Administración" class="grid">
                        <flux:sidebar.item icon="building-storefront" :href="route('admin.branches')" :current="request()->routeIs('admin.branches')" wire:navigate>Sucursales</flux:sidebar.item>
                        <flux:sidebar.item icon="cube" :href="route('admin.chambers')" :current="request()->routeIs('admin.chambers')" wire:navigate>Cámaras</flux:sidebar.item>
                        <flux:sidebar.item icon="tag" :href="route('admin.categories')" :current="request()->routeIs('admin.categories')" wire:navigate>Categorías</flux:sidebar.item>
                        <flux:sidebar.item icon="shopping-bag" :href="route('admin.products')" :current="request()->routeIs('admin.products')" wire:navigate>Productos</flux:sidebar.item>
                        @if (auth()->user()->isSuperAdmin())
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>Usuarios</flux:sidebar.item>
                        <flux:sidebar.item icon="exclamation-triangle" :href="route('admin.incidents')" :current="request()->routeIs('admin.incidents')" wire:navigate>Incidencias</flux:sidebar.item>
                        <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.adjustments')" :current="request()->routeIs('admin.adjustments')" wire:navigate>Ajustes</flux:sidebar.item>
                        <flux:sidebar.item icon="shield-check" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>Auditoría</flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endcan

                @if (auth()->user()->isSuperAdmin())
                    <flux:sidebar.group heading="Reportes" class="grid">
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.inventory')" :current="request()->routeIs('reports.inventory')" wire:navigate>Inventario</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('reports.entries')" :current="request()->routeIs('reports.entries')" wire:navigate>Entradas</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-up-tray" :href="route('reports.exits')" :current="request()->routeIs('reports.exits')" wire:navigate>Salidas</flux:sidebar.item>
                        <flux:sidebar.item icon="queue-list" :href="route('reports.movements')" :current="request()->routeIs('reports.movements')" wire:navigate>Movimientos</flux:sidebar.item>
                        <flux:sidebar.item icon="bell-alert" :href="route('reports.low-stock')" :current="request()->routeIs('reports.low-stock')" wire:navigate>Existencias bajas</flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <div
            x-data="{
                open: false,
                status: 'printing',
                kind: '',
                printer: '',
                url: '',
                timer: null,
                printWithBrowser(url) {
                    const frame = document.createElement('iframe')
                    frame.setAttribute('aria-hidden', 'true')
                    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'
                    frame.src = url
                    document.body.appendChild(frame)
                    setTimeout(() => frame.remove(), 120000)
                },
                printTicket(url) {
                    this.printer = window.ticketPrinter?.get() ?? ''

                    if (! this.printer) {
                        return this.printWithBrowser(url)
                    }

                    clearTimeout(this.timer)
                    this.url = url
                    this.status = 'printing'
                    this.kind = ''
                    this.open = true

                    window.ticketPrinter.print(url).then(() => {
                        this.status = 'done'
                        this.timer = setTimeout(() => this.open = false, 2500)
                    }).catch((error) => {
                        this.status = 'error'
                        this.kind = error?.kind ?? 'printer'
                    })
                },
                fallback() {
                    this.open = false
                    this.printWithBrowser(this.url)
                },
            }"
            x-on:print-ticket.window="printTicket($event.detail.url)"
            @if (session('print_ticket')) x-init="$nextTick(() => printTicket(@js(session('print_ticket'))))" @endif
        >
            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/60 p-4" role="dialog" aria-modal="true" data-test="print-status">
                <div class="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 text-center shadow-xl dark:bg-zinc-800">
                    <div x-show="status === 'printing'" class="space-y-3" data-test="print-status-printing">
                        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-300">
                            <flux:icon.loading class="size-7" />
                        </div>
                        <div class="text-lg font-semibold text-zinc-900 dark:text-white">Imprimiendo ticket…</div>
                        <p class="text-sm text-zinc-500">Enviando a <span class="font-medium" x-text="printer"></span></p>
                    </div>

                    <div x-show="status === 'done'" x-cloak class="space-y-3" data-test="print-status-done">
                        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                            <flux:icon.check-circle class="size-8" />
                        </div>
                        <div class="text-lg font-semibold text-zinc-900 dark:text-white">Ticket impreso correctamente</div>
                    </div>

                    <div x-show="status === 'error'" x-cloak class="space-y-4" data-test="print-status-error">
                        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-red-500/10 text-red-600 dark:text-red-300">
                            <flux:icon.exclamation-triangle class="size-8" />
                        </div>
                        <div class="text-lg font-semibold text-zinc-900 dark:text-white">No se pudo imprimir el ticket</div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            <template x-if="kind === 'connection'"><span>No hay conexión con QZ Tray. Verifica que esté abierto en esta computadora (icono en la barra de tareas de Windows).</span></template>
                            <template x-if="kind === 'ticket'"><span>No se pudo cargar el ticket. Revisa tu conexión a internet e inténtalo de nuevo.</span></template>
                            <template x-if="kind !== 'connection' && kind !== 'ticket'"><span>No se detectó la impresora <strong x-text="printer"></strong>. Verifica que esté encendida y conectada: por USB, o en la misma red (Wi‑Fi o cable) que esta computadora.</span></template>
                        </p>
                        <div class="flex flex-col gap-2">
                            <flux:button variant="primary" icon="arrow-path" x-on:click="printTicket(url)" data-test="print-status-retry">Reintentar</flux:button>
                            <flux:button icon="printer" x-on:click="fallback()" data-test="print-status-browser">Imprimir con el navegador</flux:button>
                            <flux:button variant="ghost" x-on:click="open = false" data-test="print-status-close">Cerrar</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (auth()->user()->role === \App\Enums\UserRole::Warehouse)
            <livewire:approved-entry-prompt />
        @endif

        <script src="{{ asset('js/ticket-printer.js') }}?v={{ filemtime(public_path('js/ticket-printer.js')) }}"></script>

        @fluxScripts
    </body>
</html>
