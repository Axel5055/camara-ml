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
                <flux:sidebar.group heading="Inventario" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Panel</flux:sidebar.item>
                    <flux:sidebar.item icon="archive-box" :href="route('stock')" :current="request()->routeIs('stock', 'boxes.show')" wire:navigate>Existencias</flux:sidebar.item>
                    <div x-data="{ open: $persist(true).as('nav-entries') }" data-test="nav-entries">
                        <flux:sidebar.item as="button" type="button" icon="arrow-down-tray" x-on:click="open = ! open" x-bind:aria-expanded="open" class="w-full">
                            <span class="flex w-full items-center justify-between gap-2">
                                Entradas
                                <flux:icon name="chevron-down" class="size-4 text-zinc-400 transition-transform duration-200" x-bind:class="open ? '' : '-rotate-90'" />
                            </span>
                        </flux:sidebar.item>
                        <div x-show="open" x-collapse class="ms-5 mt-0.5 grid gap-0.5 border-s border-zinc-700 ps-2">
                            <flux:sidebar.item icon="list-bullet" :href="route('entries.index')" :current="request()->routeIs('entries.index', 'entries.show')" wire:navigate>Todas las entradas</flux:sidebar.item>
                            @can('operate-inventory')
                                <flux:sidebar.item icon="plus-circle" :href="route('entries.create')" :current="request()->routeIs('entries.create')" wire:navigate>Nueva entrada</flux:sidebar.item>
                            @endcan
                        </div>
                    </div>
                    <div x-data="{ open: $persist(true).as('nav-exits') }" data-test="nav-exits">
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
                        <div x-data="{ open: $persist(true).as('nav-transfers') }" data-test="nav-transfers">
                            <flux:sidebar.item as="button" type="button" icon="arrows-right-left" x-on:click="open = ! open" x-bind:aria-expanded="open" class="w-full">
                                <span class="flex w-full items-center justify-between gap-2">
                                    Transferencias
                                    <flux:icon name="chevron-down" class="size-4 text-zinc-400 transition-transform duration-200" x-bind:class="open ? '' : '-rotate-90'" />
                                </span>
                            </flux:sidebar.item>
                            <div x-show="open" x-collapse class="ms-5 mt-0.5 grid gap-0.5 border-s border-zinc-700 ps-2">
                                <flux:sidebar.item icon="list-bullet" :href="route('transfers.index')" :current="request()->routeIs('transfers.index')" wire:navigate>Todas las transferencias</flux:sidebar.item>
                                @can('operate-inventory')
                                    <flux:sidebar.item icon="plus-circle" :href="route('transfers.create')" :current="request()->routeIs('transfers.create')" wire:navigate>Nueva transferencia</flux:sidebar.item>
                                @endcan
                            </div>
                        </div>
                    @endcan
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
                printTicket(url) {
                    const frame = document.createElement('iframe')
                    frame.setAttribute('aria-hidden', 'true')
                    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'
                    frame.src = url
                    document.body.appendChild(frame)
                    setTimeout(() => frame.remove(), 120000)
                },
            }"
            x-on:print-ticket.window="printTicket($event.detail.url)"
            @if (session('print_ticket')) x-init="$nextTick(() => printTicket(@js(session('print_ticket'))))" @endif
        ></div>

        @fluxScripts
    </body>
</html>
