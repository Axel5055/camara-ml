<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        {{-- Every screen shares the same centered column, so content never stretches edge to edge. --}}
        <div class="mx-auto w-full max-w-7xl">
            <div class="mb-2 flex items-center justify-end gap-1">
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" size="sm" square aria-label="Apariencia" data-test="appearance-menu">
                        <flux:icon.sun class="hidden size-5 dark:block" />
                        <flux:icon.moon class="size-5 dark:hidden" />
                    </flux:button>

                    <flux:menu>
                        <flux:menu.radio.group x-data x-model="$flux.appearance">
                            <flux:menu.radio value="light" icon="sun">Claro</flux:menu.radio>
                            <flux:menu.radio value="dark" icon="moon">Oscuro</flux:menu.radio>
                            <flux:menu.radio value="system" icon="computer-desktop">Automático</flux:menu.radio>
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>

                <livewire:notifications-bell />
            </div>

            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
