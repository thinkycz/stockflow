const CACHE_PREFIX = 'teacha-assets-';
const CACHE_NAME = `${CACHE_PREFIX}v2`;
const BRAND_PATHS = new Set([
    '/apple-touch-icon.png',
    '/favicon.ico',
    '/manifest.webmanifest',
    '/pwa-192x192.png',
    '/pwa-512x512.png',
    '/pwa-maskable-512x512.png',
    '/teacha-mark.svg',
]);

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith(CACHE_PREFIX) &&
                                key !== CACHE_NAME,
                        )
                        .map((key) => caches.delete(key)),
                ),
            ),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET' || request.mode === 'navigate') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    if (BRAND_PATHS.has(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    if (cached !== undefined) {
        return cached;
    }

    const response = await fetch(request);
    await cacheSuccessfulResponse(cache, request, response);

    return response;
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then(async (response) => {
            await cacheSuccessfulResponse(cache, request, response);
            return response;
        })
        .catch(() => cached ?? Response.error());

    return cached ?? network;
}

async function cacheSuccessfulResponse(cache, request, response) {
    if (response.ok && response.type === 'basic') {
        await cache.put(request, response.clone());
    }
}
