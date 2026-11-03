{{-- "Actualizar aplicación": compares the installed version with the server and, if different, clears the service worker and caches and reloads. --}}
<div
    x-data="{
        updating: false,
        notify(text, variant = 'success') {
            document.dispatchEvent(new CustomEvent('toast-show', {
                detail: { duration: 5000, slots: { text }, dataset: { variant } },
            }));
        },
        notifyIfUpdated() {
            try {
                if (sessionStorage.getItem('app-updated')) {
                    sessionStorage.removeItem('app-updated');
                    setTimeout(() => this.notify('Aplicación actualizada correctamente.'), 600);
                }
            } catch (error) {}
        },
        async clearAndReload() {
            try {
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    await Promise.all(registrations.map((registration) => registration.unregister()));
                }

                if ('caches' in window) {
                    const keys = await caches.keys();
                    await Promise.all(keys.map((key) => caches.delete(key)));
                }
            } catch (error) {
                // If the browser refuses to clean up, a normal reload still works.
            }

            try {
                sessionStorage.setItem('app-updated', '1');
            } catch (error) {}

            window.location.reload();
        },
        async update() {
            this.updating = true;

            try {
                const response = await fetch('{{ route('app-version') }}', { cache: 'no-store' });
                const { version } = await response.json();
                const current = document.querySelector('meta[name=app-version]')?.content;

                if (version === current) {
                    this.notify('Ya estás usando la versión más actual.');
                    this.updating = false;

                    return;
                }

                await this.clearAndReload();
            } catch (error) {
                this.notify('No se pudo buscar actualizaciones. Revisa tu conexión.', 'danger');
                this.updating = false;
            }
        },
    }"
    x-init="notifyIfUpdated()"
    class="text-center"
>
    <button
        type="button"
        x-on:click="update()"
        x-bind:disabled="updating"
        class="text-sm text-zinc-500 underline underline-offset-2 hover:text-zinc-800 disabled:opacity-60 dark:text-zinc-400 dark:hover:text-zinc-200"
        data-test="update-app-link"
    >
        <span x-show="! updating">Actualizar aplicación</span>
        <span x-show="updating" style="display: none">Buscando actualización…</span>
    </button>
</div>
