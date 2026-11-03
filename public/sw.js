/*
 * Service worker of the installable app.
 *
 * The inventory needs the server, so pages and data are never cached (a shared device must not keep
 * somebody else's records). Only static files are cached to open faster, and when the network is down a
 * navigation shows the offline page.
 */
const VERSION = 'v2';
const STATIC_CACHE = `camaras-static-${VERSION}`;
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/images/logo.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(names.filter((name) => name !== STATIC_CACHE).map((name) => caches.delete(name))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));

        return;
    }

    if (/^\/(build|images|icons)\//.test(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );
    }
});
