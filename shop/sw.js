/**
 * ShopMart Customer Storefront Service Worker
 * Enables PWA installability, background asset caching, and offline resilience.
 */

const CACHE_NAME = 'shopmart-storefront-v1';
const PRECACHE_ASSETS = [
    './assets/img/pwa-icon-192.png',
    './assets/img/pwa-icon-512.png',
    './assets/css/bootstrap.min.css',
    './assets/css/main.css',
    './assets/css/responsive.css',
    './assets/css/style.css',
    './manifest.json'
];

// Install: Precache shell icons and essential styles
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[SW] Precache asset skipped:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate: Clean up older cache versions and take immediate control
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME && name.startsWith('shopmart-storefront-')) {
                        console.log('[SW] Removing old storefront cache:', name);
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: Strategy depending on request type
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Never interfere with non-GET requests (e.g., checkout POST, cart mutations)
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Skip payment gateway webhooks, external APIs, and live chat socket / SSE streams
    if (
        url.pathname.includes('/payment/') ||
        url.pathname.includes('/ajax/') ||
        url.pathname.includes('live_chat_api.php') ||
        url.pathname.includes('marketing_api.php')
    ) {
        return;
    }

    // Navigation (HTML pages): Network-First to guarantee real-time inventory and carts
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    return networkResponse;
                })
                .catch(() => {
                    // If offline, try cached page or fallback
                    return caches.match(request).then((cachedResponse) => {
                        if (cachedResponse) return cachedResponse;
                        return new Response(
                            `<!DOCTYPE html>
                            <html lang="en">
                            <head>
                                <meta charset="UTF-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                <title>Offline - ShopMart</title>
                                <style>
                                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #F8FAFC; color: #0F172A; text-align: center; padding: 20px; }
                                    .card { background: #ffffff; border-radius: 16px; padding: 36px 24px; max-width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #E2E8F0; }
                                    h2 { margin: 12px 0 8px; font-size: 22px; font-weight: 700; }
                                    p { color: #64748B; font-size: 14px; margin-bottom: 24px; }
                                    button { background: #FEDB65; color: #0F172A; border: none; padding: 12px 24px; border-radius: 999px; font-weight: 700; cursor: pointer; }
                                </style>
                            </head>
                            <body>
                                <div class="card">
                                    <div style="font-size: 48px;">🛍️</div>
                                    <h2>You are currently offline</h2>
                                    <p>Please check your internet connection to continue browsing products and managing your cart.</p>
                                    <button onclick="window.location.reload()">Retry Connection</button>
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

    // Static Assets (Images, Fonts, CSS, JS): Stale-While-Revalidate
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
