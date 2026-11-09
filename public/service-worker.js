const CACHE_NAME = 'jazzel-pwa-v2';
const OFFLINE_URL = '/offline.html';
const CORE_ASSETS = [
    OFFLINE_URL,
    '/assets/img/pwa-icon-192.png',
    '/assets/img/pwa-icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(CORE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => Promise.all(
                cacheNames
                    .filter((cacheName) => cacheName.startsWith('jazzel-pwa-') && cacheName !== CACHE_NAME)
                    .map((cacheName) => caches.delete(cacheName))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const requestUrl = new URL(request.url);

    if (
        request.method !== 'GET'
        || requestUrl.origin !== self.location.origin
        || request.mode !== 'navigate'
    ) {
        return;
    }

    event.respondWith(
        fetch(request).catch(async () => {
            const offlinePage = await caches.match(OFFLINE_URL);

            return offlinePage || Response.error();
        })
    );
});
