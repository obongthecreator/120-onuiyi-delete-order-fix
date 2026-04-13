/**
 * 120 Stand Inventory - Service Worker
 * Enables offline functionality
 * Version: 1.6.0
 */

// Cache version - update this when deploying new versions
const CACHE_VERSION = '1.7.0';
const CACHE_NAME = 'stand120-v' + CACHE_VERSION;

// Files to pre-cache (minimal set — HTML is always network-first)
const CACHE_FILES = [
    '/wp-content/plugins/120-stand-inventory/assets/css/style.css',
    '/wp-content/plugins/120-stand-inventory/assets/js/main.js',
    '/wp-content/plugins/120-stand-inventory/assets/images/logo.png'
];

// Install event — pre-cache static assets and immediately activate
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(CACHE_FILES).catch(() => {
                // Non-fatal: pre-caching may fail on first install
            }))
            .then(() => self.skipWaiting())
    );
});

// Activate event — purge old caches and take control of all clients
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name.startsWith('stand120-') && name !== CACHE_NAME)
                        .map((name) => caches.delete(name))
                );
            })
            .then(() => self.clients.claim())
    );
});

// Fetch event
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests entirely
    if (request.method !== 'GET') {
        return;
    }

    // AJAX calls: network-only with offline JSON fallback
    if (url.pathname.includes('admin-ajax.php')) {
        event.respondWith(
            fetch(request).catch(() => {
                return new Response(
                    JSON.stringify({
                        success: false,
                        offline: true,
                        message: 'You are offline. Data will sync when you reconnect.'
                    }),
                    { headers: { 'Content-Type': 'application/json' } }
                );
            })
        );
        return;
    }

    // HTML pages: network-only (with cache fallback only when truly offline)
    const acceptHeader = request.headers.get('accept') || '';
    if (acceptHeader.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Only cache successful HTML responses
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(request).then((cached) => {
                        return cached || new Response(
                            '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title></head>' +
                            '<body style="font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#1a1a2e;color:#fff;text-align:center;">' +
                            '<div><h1 style="font-size:2rem;">You are offline</h1><p>Please check your internet connection and try again.</p>' +
                            '<button onclick="location.reload()" style="margin-top:16px;padding:12px 32px;border:none;border-radius:8px;background:#8B0000;color:#fff;font-size:1rem;cursor:pointer;">Retry</button></div></body></html>',
                            { headers: { 'Content-Type': 'text/html' }, status: 503 }
                        );
                    });
                })
        );
        return;
    }

    // Static assets (CSS, JS, images): network-first with cache fallback
    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                }
                return response;
            })
            .catch(() => {
                return caches.match(request);
            })
    );
});

// Background sync for offline data
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-offline-data') {
        event.waitUntil(syncOfflineData());
    }
});

async function syncOfflineData() {
    // Placeholder — offline queue is synced by main.js when online status is restored
}

// Push notification handling (for future use)
self.addEventListener('push', (event) => {
    if (event.data) {
        const data = event.data.json();
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/wp-content/plugins/120-stand-inventory/assets/images/logo.png',
            badge: '/wp-content/plugins/120-stand-inventory/assets/images/logo.png'
        });
    }
});
