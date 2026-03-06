/**
 * Driver PWA – Service Worker
 *
 * Strategy:
 *   - App shell (HTML/JS/CSS) → Cache-first (precached on install)
 *   - /api/driver/bootstrap   → Network-first; fall back to cached response
 *   - /api/driver/sync        → Network-only (queue handled by app.js)
 *   - /api/driver/upload      → Network-only
 */

const CACHE_NAME   = 'driver-app-v1';
const SHELL_ASSETS = [
    '/driver',
    '/driver/',
    '/driver/app.js',
    '/driver/manifest.json',
];

// ── Install: precache app shell ───────────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

// ── Activate: delete old caches ───────────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k !== CACHE_NAME)
                    .map(k => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

// ── Fetch ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // App shell: cache-first
    if (url.pathname.startsWith('/driver') && !url.pathname.startsWith('/api/')) {
        event.respondWith(cacheFirst(event.request));
        return;
    }

    // Bootstrap: network-first with cache fallback
    if (url.pathname === '/api/driver/bootstrap') {
        event.respondWith(networkFirstWithCache(event.request));
        return;
    }

    // Sync / Upload: network-only — app.js queues these via IndexedDB
    // (no offline fallback at the fetch level)
});

// ── Strategies ────────────────────────────────────────────────────────────────

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, response.clone());
    }
    return response;
}

async function networkFirstWithCache(request) {
    const cache = await caches.open(CACHE_NAME);

    try {
        const response = await fetch(request.clone());
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await cache.match(request);
        if (cached) return cached;
        return new Response(
            JSON.stringify({ error: 'Offline and no cached data available.' }),
            { status: 503, headers: { 'Content-Type': 'application/json' } }
        );
    }
}
