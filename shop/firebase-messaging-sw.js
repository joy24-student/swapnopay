/* Firebase Cloud Messaging Service Worker for ShopNext / SwapnoPay */
importScripts('https://www.gstatic.com/firebasejs/10.13.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.0/firebase-messaging-compat.js');

// Parse query params if passed during SW registration
const urlParams = new URLSearchParams(location.search);
const apiKey = urlParams.get('apiKey') || '';
const projectId = urlParams.get('projectId') || '';
const messagingSenderId = urlParams.get('messagingSenderId') || '';
const appId = urlParams.get('appId') || '';

if (projectId && messagingSenderId) {
    try {
        firebase.initializeApp({
            apiKey: apiKey,
            projectId: projectId,
            messagingSenderId: messagingSenderId,
            appId: appId
        });
        const messaging = firebase.messaging();

        messaging.onBackgroundMessage(function(payload) {
            console.log('[firebase-messaging-sw.js] Received background message:', payload);
            const notificationTitle = payload.notification?.title || payload.data?.title || 'SwapnoPay Notification';
            const notificationOptions = {
                body: payload.notification?.body || payload.data?.body || '',
                icon: payload.notification?.icon || payload.data?.icon || '/assets/uploads/default_logo.png',
                badge: '/assets/uploads/default_logo.png',
                vibrate: [200, 100, 200],
                data: {
                    url: payload.data?.url || payload.data?.click_action || payload.notification?.click_action || '/'
                }
            };
            return self.registration.showNotification(notificationTitle, notificationOptions);
        });
    } catch (e) {
        console.warn('[firebase-messaging-sw.js] Firebase init error:', e);
    }
}

// Fallback native push listener for standard web push
self.addEventListener('push', function(event) {
    if (!event.data) return;
    try {
        const data = event.data.json();
        const title = data.notification?.title || data.title || 'Store Notification';
        const options = {
            body: data.notification?.body || data.body || '',
            icon: data.notification?.icon || data.icon || '/assets/uploads/default_logo.png',
            badge: '/assets/uploads/default_logo.png',
            vibrate: [200, 100, 200],
            data: {
                url: data.data?.url || data.url || data.click_action || '/'
            }
        };
        event.waitUntil(self.registration.showNotification(title, options));
    } catch (err) {
        // Plain text push
        const text = event.data.text();
        event.waitUntil(self.registration.showNotification('Store Notification', {
            body: text,
            icon: '/assets/uploads/default_logo.png'
        }));
    }
});

// Click listener to navigate user to the relevant screen
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(windowClients) {
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
