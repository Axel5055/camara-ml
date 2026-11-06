/*
 * Impresión directa de tickets con QZ Tray.
 *
 * La impresora predeterminada se guarda en este navegador (cada equipo ve sus propias impresoras). Sin impresora
 * guardada, o si QZ Tray no responde, el ticket se imprime como siempre: con el diálogo del navegador.
 */
(function () {
    if (window.ticketPrinter) {
        return;
    }

    var KEY = 'ticket-printer';
    var base = document.currentScript.src.replace(/js\/ticket-printer\.js.*$/, '');
    var loading = null;

    function load() {
        if (window.qz) {
            return Promise.resolve();
        }

        if (!loading) {
            loading = new Promise(function (resolve, reject) {
                var script = document.createElement('script');
                script.src = base + 'vendor/qz-tray.js';
                script.onload = resolve;
                script.onerror = function () {
                    loading = null;
                    reject(new Error('No se pudo cargar QZ Tray.'));
                };
                document.head.appendChild(script);
            });
        }

        return loading;
    }

    function connect() {
        return load().then(function () {
            qz.security.setCertificatePromise(function (resolve) {
                fetch(base + 'impresoras/certificado', { credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.text() : ''; })
                    .then(function (certificate) { resolve(certificate || undefined); })
                    .catch(function () { resolve(); });
            });
            qz.security.setSignatureAlgorithm('SHA512');
            qz.security.setSignaturePromise(function (toSign) {
                return function (resolve) {
                    var token = document.querySelector('meta[name="csrf-token"]');

                    fetch(base + 'impresoras/firmar', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
                        body: JSON.stringify({ request: toSign }),
                    })
                        .then(function (response) { return response.ok ? response.text() : ''; })
                        .then(function (signature) { resolve(signature || undefined); })
                        .catch(function () { resolve(); });
                };
            });

            if (qz.websocket.isActive()) {
                return;
            }

            return qz.websocket.connect({ retries: 1, delay: 1 });
        });
    }

    function fail(kind, error) {
        var wrapped = error instanceof Error ? error : new Error(String(error));
        wrapped.kind = kind;

        throw wrapped;
    }

    /** Rechaza con error.kind: 'connection' (QZ Tray), 'printer' (la impresora) o 'ticket' (leer el ticket). */
    function send(printer, html, copies) {
        return connect()
            .catch(function (error) { fail('connection', error); })
            .then(function () {
                var config = qz.configs.create(printer, { units: 'mm', margins: 0 });
                var job = { type: 'pixel', format: 'html', flavor: 'plain', data: html, options: { pageWidth: 72 } };
                var jobs = [];

                // Un trabajo por copia: algunos controladores ignoran la opción "copies" de la impresora.
                for (var i = 0; i < (copies || 1); i++) {
                    jobs.push(job);
                }

                return qz.print(config, jobs)
                    .catch(function (error) { fail('printer', error); });
            });
    }

    window.ticketPrinter = {
        get: function () {
            try {
                return localStorage.getItem(KEY) || null;
            } catch (error) {
                return null;
            }
        },

        set: function (name) {
            try {
                if (name) {
                    localStorage.setItem(KEY, name);
                } else {
                    localStorage.removeItem(KEY);
                }
            } catch (error) {}
        },

        list: function () {
            return connect().then(function () { return qz.printers.find(); });
        },

        systemDefault: function () {
            return connect().then(function () { return qz.printers.getDefault(); });
        },

        /** Imprime el ticket de la URL en la impresora guardada; rechaza si no se pudo. */
        print: function (url) {
            var printer = this.get();

            if (!printer) {
                return Promise.reject(new Error('Sin impresora predeterminada.'));
            }

            var ticket = new URL(url, window.location.href);
            var copies = Math.max(1, Math.min(10, parseInt(ticket.searchParams.get('copies'), 10) || 1));
            ticket.searchParams.delete('auto');
            ticket.searchParams.delete('copies');
            ticket.searchParams.set('raw', '1');

            return fetch(ticket, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('No se pudo leer el ticket.');
                    }

                    return response.text();
                })
                .catch(function (error) { fail('ticket', error); })
                .then(function (html) { return send(printer, html, copies); });
        },

        test: function (printer) {
            var html = '<html><body style="font:12px monospace;width:72mm;margin:0;padding:3mm">'
                + '<div style="text-align:center;font-weight:bold">PRUEBA DE IMPRESIÓN</div>'
                + '<div style="text-align:center">ML Grupo Comercial</div>'
                + '<div style="text-align:center">' + new Date().toLocaleString('es-MX') + '</div></body></html>';

            return send(printer, html);
        },
    };
})();
