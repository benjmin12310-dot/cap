/**
 * Service Worker — DentalCare PWA
 *
 * Strategy:
 *  - App shell (CSS, JS, fonts): Cache-first, update in background
 *  - PHP pages: Network-first, fall back to offline page only if network fails
 *  - API calls: Network-only (never serve stale clinical data)
 */

const CACHE_VERSION = 'v1';
const SHELL_CACHE   = 'shell-' + CACHE_VERSION;
const PAGE_CACHE    = 'pages-' + CACHE_VERSION;

// Static assets to pre-cache on install
const SHELL_ASSETS = [
  './assets/css/style.css',
  './assets/css/accessibility.css',
  './assets/js/app.js',
  './assets/js/accessibility.js',
  './assets/images/favicon.svg',
  './offline.php',
];

// ── Install: pre-cache app shell ──────────────────────────────────────────────
self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(SHELL_CACHE).then(cache => cache.addAll(SHELL_ASSETS))
         .catch(err => console.warn('[SW] Shell pre-cache failed (some assets may be missing):', err))
  );
});

// ── Activate: clean up old caches ────────────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(k => k !== SHELL_CACHE && k !== PAGE_CACHE)
          .map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch: routing logic ──────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET, cross-origin, and API calls entirely (always fresh)
  if (request.method !== 'GET') return;
  if (url.origin !== self.location.origin) return;
  if (url.pathname.includes('/api/')) return;

  // Static assets (CSS, JS, images, fonts) — cache-first
  if (isStaticAsset(url.pathname)) {
    event.respondWith(cacheFirst(request, SHELL_CACHE));
    return;
  }

  // PHP pages — network-first, offline fallback
  if (url.pathname.endsWith('.php') || url.pathname === '/') {
    event.respondWith(networkFirstWithFallback(request));
    return;
  }
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function isStaticAsset(pathname) {
  return /\.(css|js|woff2?|ttf|eot|svg|png|jpg|ico|webp|gif)$/i.test(pathname);
}

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) {
    // Update in background
    fetch(request).then(res => {
      if (res && res.ok) caches.open(cacheName).then(c => c.put(request, res));
    }).catch(() => {});
    return cached;
  }
  const fresh = await fetch(request);
  if (fresh && fresh.ok) {
    const cache = await caches.open(cacheName);
    cache.put(request, fresh.clone());
  }
  return fresh;
}

async function networkFirstWithFallback(request) {
  try {
    const response = await fetch(request);
    // Cache successful page loads for offline fallback
    if (response && response.ok && response.type === 'basic') {
      const cache = await caches.open(PAGE_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    // Network unavailable — try the page cache, then offline page
    const cached = await caches.match(request);
    if (cached) return cached;
    const offline = await caches.match('./offline.php');
    return offline || new Response('<h1>You are offline</h1>', { headers: { 'Content-Type': 'text/html' } });
  }
}
