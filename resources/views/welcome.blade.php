<x-layouts::auth :title="__('Welcome')">
    <div class="flex flex-col items-center gap-6 text-center">
        <div class="space-y-1">
            <flux:heading size="xl">Sistema de cámaras de refrigeración</flux:heading>
            <flux:text>Control de inventario por caja: entradas, salidas y transferencias.</flux:text>
        </div>

        @auth
            <flux:button variant="primary" class="w-full" :href="route('dashboard')">Ir al panel</flux:button>
        @else
            <flux:button variant="primary" class="w-full" :href="route('login')">{{ __('Log in') }}</flux:button>
        @endauth
    </div>
</x-layouts::auth>
