const SHELL_CACHE = 'digital-library-shell-v1';
const IMAGE_CACHE = 'digital-library-images-v1';
const SHELL_ASSETS = [
    '/offline.html',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
    '/pwa/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_ASSETS)),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('digital-library-'))
                    .filter((key) => ![SHELL_CACHE, IMAGE_CACHE].includes(key))
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

function isSensitivePath(pathname) {
    return pathname.startsWith('/admin')
        || pathname.startsWith('/install')
        || pathname.startsWith('/health')
        || pathname.startsWith('/read/')
        || /\/book\/[^/]+\/download$/.test(pathname);
}

async function trimCache(cacheName, maximumEntries) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();

    while (keys.length > maximumEntries) {
        const oldest = keys.shift();

        if (oldest) {
            await cache.delete(oldest);
        }
    }
}

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) return cached;

    const response = await fetch(request);

    if (response.ok && response.type === 'basic') {
        await cache.put(request, response.clone());
        await trimCache(cacheName, 120);
    }

    return response;
}

async function imageNetworkFirst(request) {
    const cache = await caches.open(IMAGE_CACHE);

    try {
        const response = await fetch(request);

        if (response.ok && response.type === 'basic') {
            await cache.put(request, response.clone());
            await trimCache(IMAGE_CACHE, 80);
        }

        return response;
    } catch {
        const cached = await cache.match(request);

        if (cached) return cached;

        throw new Error('Image unavailable offline.');
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || isSensitivePath(url.pathname)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const cache = await caches.open(SHELL_CACHE);

                return cache.match('/offline.html');
            }),
        );

        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, SHELL_CACHE));

        return;
    }

    if (
        request.destination === 'image'
        && (
            url.pathname.startsWith('/storage/')
            || url.pathname.startsWith('/pwa/')
        )
    ) {
        event.respondWith(imageNetworkFirst(request));
    }
});
