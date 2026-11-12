@props([
    'draftKey',
    'fields',
    'noun' => 'captura',
])

{{--
    Respaldo local de lo capturado: se guarda en este navegador mientras el usuario escribe y, si la página se
    refresca o se pierde el internet, ofrece continuar con lo último o descartarlo. El servidor revisa todo de nuevo
    al recuperar (restoreDraft); el evento draft-saved borra el respaldo cuando el registro se guardó.
--}}
<div
    x-data="{
        storageKey: @js('draft:'.$draftKey.':'.auth()->id()),
        fields: @js($fields),
        pending: false,
        finished: false,
        failed: false,
        restoring: false,
        online: navigator.onLine,
        stored: null,
        read() {
            try {
                const draft = JSON.parse(localStorage.getItem(this.storageKey));
                if (draft && draft.data && Date.now() - draft.savedAt < 86400000) { return draft; }
                localStorage.removeItem(this.storageKey);
            } catch (error) {}
            return null;
        },
        forget() {
            try { localStorage.removeItem(this.storageKey); } catch (error) {}
        },
        meaningful(data) {
            return (Array.isArray(data.lines) && data.lines.length > 0) || !! data.productId;
        },
        init() {
            const draft = this.read();
            if (draft && this.meaningful(draft.data)) {
                this.stored = draft;
                this.pending = true;
            }
        },
        persist() {
            if (this.pending || this.finished) { return; }
            const data = {};
            this.fields.forEach((field) => { data[field] = JSON.parse(JSON.stringify($wire[field] ?? null)); });
            if (! this.meaningful(data)) { return this.forget(); }
            try { localStorage.setItem(this.storageKey, JSON.stringify({ savedAt: Date.now(), data })); } catch (error) {}
        },
        async restore() {
            this.restoring = true;
            this.failed = false;
            try {
                await $wire.restoreDraft(this.stored.data);
                this.pending = false;
            } catch (error) {
                this.failed = true;
            } finally {
                this.restoring = false;
            }
        },
        discard() {
            this.forget();
            this.pending = false;
        },
        finish() {
            this.finished = true;
            this.forget();
        },
        get summary() {
            const lines = (this.stored?.data.lines ?? []).length;
            const when = new Date(this.stored?.savedAt ?? Date.now()).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
            return lines > 0 ? lines + (lines === 1 ? ' grupo' : ' grupos') + ' · guardado el ' + when : 'Guardado el ' + when;
        },
    }"
    x-effect="persist()"
    x-on:draft-saved.window="finish()"
    x-on:online.window="online = true"
    x-on:offline.window="online = false"
    class="space-y-3"
    data-test="draft-recovery"
>
    <div
        x-show="pending"
        x-cloak
        class="flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-500/40 dark:bg-amber-500/10"
        data-test="draft-recovery-banner"
    >
        <div class="flex items-start gap-3">
            <flux:icon name="clock" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-300" />
            <div class="min-w-0">
                <div class="font-medium text-zinc-900 dark:text-white">Encontramos una {{ $noun }} sin terminar</div>
                <div class="text-sm text-zinc-600 dark:text-zinc-300" x-text="summary"></div>
                <div x-show="failed" x-cloak class="mt-1 text-sm text-red-600 dark:text-red-400">No se pudo recuperar. Revisa tu conexión e inténtalo de nuevo.</div>
            </div>
        </div>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:shrink-0">
            <flux:button type="button" variant="ghost" x-on:click="discard()" x-bind:disabled="restoring" class="w-full sm:w-auto" data-test="draft-discard">Descartar</flux:button>
            <flux:button type="button" variant="primary" icon="arrow-uturn-left" x-on:click="restore()" x-bind:disabled="restoring" class="w-full sm:w-auto" data-test="draft-restore">Continuar con lo último</flux:button>
        </div>
    </div>

    <div
        x-show="! online"
        x-cloak
        class="flex items-start gap-3 rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-200"
        data-test="draft-offline"
    >
        <flux:icon name="signal-slash" class="mt-0.5 size-5 shrink-0" />
        <span>Sin conexión a internet. Lo que capturaste está guardado en este equipo: si la página se actualiza, podrás continuar con lo último cuando vuelva la conexión.</span>
    </div>
</div>
