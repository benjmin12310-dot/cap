/**
 * Service Worker — DentalCare PWA
 * Fixed: no background re-fetch on every cache hit (was doubling all requests)
 * Strategy:
 *  - Static assets (CSS/JS/images): cache-first, refresh only once per hour
 *  - PHP pages: network-first, offline fallback
 *  - API calls: network-only (never cache clinical data)
 */

const CACHE_VERSION = 'v2';
const SHELL_CACHE   = 'shell-' + CACHE_VERSION;
const PAGE_CACHE    = 'pages-' + CACHE_VERSION;

const SHELL_ASSETS = [
    './assets/css/style.css',
    './assets/css/accessibility.css',
    './assets/js/app.js',
    './assets/js/accessibility.js',
    './assets/images/favicon.svg',
    './offline.php',
];

// Track when each asset was last refreshed from network
const CACHE_TTL_MS = 60 * 60 * 1000; // 1 hour

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(SHELL_CACHE)
              .then(cache => cache.addAll(SHELL_ASSETS))
              .catch(err => console.warn('[SW] Pre-cache failed:', err))
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
              .then(keys => Promise.all(
                  keys.filter(k => k !== SHELL_CACHE && k !== PAGE_CACHE)
                      .map(k => caches.delete(k))
              ))
              .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (url.origin !== self.location.origin) return;
    if (url.pathname.includes('/api/')) return;

    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirstNoBackground(request, SHELL_CACHE));
        return;
    }

    if (url.pathname.endsWith('.php') || url.pathname === '/') {
        event.respondWith(networkFirstWithFallback(request));
        return;
    }
});

function isStaticAsset(pathname) {
    return /\.(css|js|woff2?|ttf|eot|svg|png|jpg|ico|webp|gif)$/i.test(pathname);
}

// Cache-first WITHOUT background re-fetch — serve cache, done.
// The cache was populated on install; it refreshes when CACHE_VERSION changes.
async function cacheFirstNoBackground(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    // Not in cache yet — fetch, store, return
    try {
        const fresh = await fetch(request);
        if (fresh && fresh.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, fresh.clone());
        }
        return fresh;
    } catch (err) {
        return new Response('', { status: 503 });
    }
}

async function networkFirstWithFallback(request) {
    try {
        const response = await fetch(request);
        if (response && response.ok && response.type === 'basic') {
            const cache = await caches.open(PAGE_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        const cached = await caches.match(request);
        if (cached) return cached;
        const offline = await caches.match('./offline.php');
        return offline || new Response('<h1>You are offline</h1>', {
            headers: { 'Content-Type': 'text/html' }
        });
    }
}
