// Service worker mínimo para Morrav PWA.
// Estrategia: network-first para datos (todo lo que sea POST o /api/*) y stale-while-revalidate para assets.
// Sin caché agresivo de páginas porque el inventario tiene que ser siempre fresh.

const CACHE_NAME = 'morrav-shell-v1';
const SHELL_ASSETS = [
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS)).catch(() => {})
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Solo GET. Lo demás (POST de Livewire, formularios) pasa directo a red.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Saltar requests cross-origin (CDNs externos, etc).
    if (url.origin !== self.location.origin) return;

    // Páginas HTML: network-first, fallback a caché si no hay red.
    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    // Assets estáticos (CSS, JS, imágenes): stale-while-revalidate.
    event.respondWith(
        caches.match(request).then((cached) => {
            const fetchPromise = fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
                    }
                    return response;
                })
                .catch(() => cached);
            return cached || fetchPromise;
        })
    );
});
