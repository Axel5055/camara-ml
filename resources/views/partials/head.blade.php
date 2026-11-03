<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="app-version" content="{{ \App\Http\Controllers\AppVersionController::version() }}">
<meta name="theme-color" content="#e30613">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="ML Cámaras">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<script>
    (function () {
        var installEvent = null;
        var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {});
            });
        }

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            installEvent = event;
        });

        window.addEventListener('appinstalled', function () {
            installEvent = null;
            document.querySelectorAll('[data-install-block]').forEach(function (block) { block.hidden = true; });
        });

        document.addEventListener('DOMContentLoaded', function () {
            if (standalone) {
                document.querySelectorAll('[data-install-block]').forEach(function (block) { block.hidden = true; });
            }
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-install-app]')) {
                return;
            }

            event.preventDefault();

            if (installEvent) {
                installEvent.prompt();
                installEvent.userChoice.then(function () { installEvent = null; });

                return;
            }

            var ios = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
            var secure = window.isSecureContext;
            document.querySelectorAll('[data-install-hint]').forEach(function (hint) {
                hint.hidden = false;
                hint.querySelectorAll('[data-insecure]').forEach(function (el) { el.hidden = secure; });
                hint.querySelectorAll('[data-ios]').forEach(function (el) { el.hidden = !secure || !ios; });
                hint.querySelectorAll('[data-other]').forEach(function (el) { el.hidden = !secure || ios; });
            });
        });
    })();
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
