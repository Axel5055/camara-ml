<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <div data-install-block class="space-y-2">
            <flux:button type="button" variant="outline" class="w-full" icon="arrow-down-tray" data-install-app>
                Instalar aplicación
            </flux:button>

            <flux:text data-install-hint hidden class="text-center text-sm">
                <span data-insecure hidden>Esta dirección no es segura, y los navegadores solo permiten instalar desde una dirección <strong>https</strong> (o desde <strong>localhost</strong>). Abre el sistema con su dirección segura para poder instalarlo.</span>
                <span data-ios hidden>En iPhone o iPad abre esta página en Safari, toca el botón Compartir y elige «Agregar a pantalla de inicio».</span>
                <span data-other hidden>Abre el menú de tu navegador (⋮) y elige «Instalar aplicación» o «Agregar a pantalla de inicio». Si no aparece, puede que ya la tengas instalada: búscala en tus aplicaciones.</span>
            </flux:text>
        </div>

        <x-update-app-link />

        @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
