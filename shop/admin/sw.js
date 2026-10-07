/**
 * ShopMart Admin Control Panel Service Worker
 * Enables Admin PWA installability, Chrome desktop/side panel installation,
 * and offline network resilience.
 */

const CACHE_NAME = 'shopmart-admin-v1';
const PRECACHE_ASSETS = [
    './img/admin-pwa-icon-192.png',
    './img/admin-pwa-icon-512.png',
    './css/bootstrap.min.css',
    './css/font-awesome.min.css',
    './css/AdminLTE.min.css',
    './css/enterprise.css',
    './manifest.json'
];

// Install: Precache shell assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[SW-Admin] Precache asset skipped:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate: Clean up older cache versions and claim clients
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME && name.startsWith('shopmart-admin-')) {
                        console.log('[SW-Admin] Removing old admin cache:', name);
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: Strategy
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Never cache mutations or non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Skip dynamic APIs, live chat APIs, broadcasts, and AI voice endpoints
    if (
        url.pathname.includes('ai_admin_api.php') ||
        url.pathname.includes('admin_notifications_api.php') ||
        url.pathname.includes('broadcast-ajax.php') ||
        url.pathname.includes('live-chat.php')
    ) {
        return;
    }

    // Navigation requests (Admin pages): Network First to guarantee latest orders & data
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    return networkResponse;
                })
                .catch(() => {
                    return caches.match(request).then((cachedResponse) => {
                        if (cachedResponse) return cachedResponse;
                        return new Response(
                            `<!DOCTYPE html>
                            <html lang="en">
                            <head>
                                <meta charset="UTF-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                <title>Admin Offline - ShopMart</title>
                                <style>
                                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #0F172A; color: #F8FAFC; text-align: center; padding: 20px; }
                                    .card { background: #1E293B; border-radius: 16px; padding: 36px 24px; max-width: 440px; box-shadow: 0 10px 25px rgba(0,0,0,0.4); border: 1px solid #334155; }
                                    h2 { margin: 12px 0 8px; font-size: 22px; font-weight: 700; color: #FEDB65; }
                                    p { color: #94A3B8; font-size: 14px; margin-bottom: 24px; }
                                    button { background: #FEDB65; color: #0F172A; border: none; padding: 12px 24px; border-radius: 999px; font-weight: 700; cursor: pointer; }
                                </style>
                            </head>
                            <body>
                                <div class="card">
                                    <div style="font-size: 48px;">🛡️</div>
                                    <h2>Admin Panel Offline</h2>
                                    <p>Your device lost connection to the store backend. Please reconnect to view and manage orders.</p>
                                    <button onclick="window.location.reload()">Retry Now</button>
                                </div>
                            </body>
                            </html>`,
                            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                        );
                    });
                })
        );
        return;
    }

    // Static Assets: Stale-While-Revalidate
    if (
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font'
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseToCache);
                        });
                    }
                    return networkResponse;
                }).catch(() => cachedResponse);

                return cachedResponse || fetchPromise;
            })
        );
    }
});
