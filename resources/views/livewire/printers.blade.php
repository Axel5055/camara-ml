<section
    class="w-full space-y-6"
    x-data="{
        printers: [],
        selected: window.ticketPrinter?.get() ?? '',
        saved: window.ticketPrinter?.get() ?? '',
        status: 'idle',
        message: '',
        async load() {
            this.status = 'loading'
            this.message = ''
            try {
                this.printers = await window.ticketPrinter.list()
                this.status = 'ready'
                if (this.saved && ! this.printers.includes(this.saved)) {
                    this.printers.unshift(this.saved)
                }
            } catch (error) {
                this.status = 'error'
            }
        },
        save() {
            window.ticketPrinter.set(this.selected)
            this.saved = this.selected
            this.message = this.selected ? 'Impresora guardada. Los tickets saldrán directo a ' + this.selected + '.' : ''
        },
        clear() {
            this.selected = ''
            window.ticketPrinter.set('')
            this.saved = ''
            this.message = 'Sin impresora: los tickets se imprimirán con el diálogo del navegador.'
        },
        async test() {
            this.message = ''
            try {
                await window.ticketPrinter.test(this.selected)
                this.message = 'Prueba enviada a ' + this.selected + '.'
            } catch (error) {
                this.message = 'No se pudo imprimir la prueba. Revisa que QZ Tray esté abierto.'
            }
        },
    }"
    x-init="load()"
    data-test="printers-page"
>
    <x-page-header icon="printer" title="Impresoras" description="Elige la impresora de este equipo para que los tickets salgan solos." />

    <div class="max-w-2xl space-y-5 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        <div x-show="status === 'loading'" class="text-sm text-zinc-500">Buscando impresoras…</div>

        <div x-show="status === 'error'" x-cloak class="space-y-3 text-sm" data-test="printers-unavailable">
            <flux:callout variant="warning" icon="exclamation-triangle" heading="No se encontró QZ Tray en este equipo">
                Instala y abre <a href="https://qz.io/download/" target="_blank" rel="noopener" class="underline">QZ Tray</a>
                (gratis). Al conectarse por primera vez, marca “Remember this decision” y acepta. Mientras tanto, los tickets
                se imprimen con el diálogo del navegador.
            </flux:callout>
            <flux:button icon="arrow-path" x-on:click="load()">Reintentar</flux:button>
        </div>

        <div x-show="status === 'ready'" x-cloak class="space-y-4">
            <div>
                <flux:label>Impresora predeterminada de este equipo</flux:label>
                <select x-model="selected" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" data-test="printers-select">
                    <option value="">Ninguna (usar el diálogo del navegador)</option>
                    <template x-for="printer in printers" :key="printer">
                        <option :value="printer" x-text="printer"></option>
                    </template>
                </select>
                <flux:text class="mt-2 text-xs">Se muestran las impresoras instaladas en este equipo, incluidas las de la red. La elección se guarda solo en este navegador.</flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="primary" icon="check" x-on:click="save()" x-bind:disabled="selected === saved" data-test="printers-save">Guardar</flux:button>
                <flux:button icon="printer" x-on:click="test()" x-bind:disabled="! selected" data-test="printers-test">Imprimir prueba</flux:button>
                <flux:button variant="ghost" x-on:click="clear()" x-show="saved" data-test="printers-clear">Quitar</flux:button>
            </div>
        </div>

        <p x-show="message" x-cloak x-text="message" class="text-sm text-emerald-600 dark:text-emerald-400" data-test="printers-message"></p>
    </div>

    @if (app(\App\Services\QzSigner::class)->isConfigured())
        <div class="max-w-2xl space-y-2 rounded-xl border border-zinc-200 bg-white p-5 text-sm text-zinc-600 shadow-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300" data-test="printers-trust">
            <div class="font-medium text-zinc-900 dark:text-white">Que QZ Tray no pregunte cada vez (una vez por equipo)</div>
            <ol class="list-decimal space-y-1 ps-5">
                <li><a href="{{ route('qz.certificate') }}" download="override.crt" class="underline">Descarga el certificado</a> (override.crt).</li>
                <li>Cópialo en la carpeta de instalación de QZ Tray, normalmente <span class="font-mono">C:\Program Files\QZ Tray</span>.</li>
                <li>Cierra QZ Tray (clic derecho en su icono &gt; Exit) y ábrelo de nuevo.</li>
                <li>Al pedir permiso, marca “Remember this decision” y pulsa Allow.</li>
            </ol>
        </div>
    @endif

    <div class="max-w-2xl rounded-xl border border-zinc-200 bg-white p-5 text-sm text-zinc-600 shadow-xs dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
        <div class="mb-2 font-medium text-zinc-900 dark:text-white">Cómo funciona</div>
        <ul class="list-disc space-y-1 ps-5">
            <li>Con una impresora elegida, al crear una entrada, salida o transferencia el ticket se imprime solo en ella.</li>
            <li>Sin impresora elegida, o si QZ Tray no está abierto, el ticket se imprime como siempre, con el diálogo del navegador.</li>
            <li>Cada equipo guarda su propia impresora; hazlo en cada computadora que imprima tickets.</li>
        </ul>
    </div>
</section>
