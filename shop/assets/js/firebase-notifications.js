/**
 * ShopNext & SwapnoPay Firebase Push Notifications & In-App Alerts Client Engine
 * Supports: Modern Browsers, Mobile WebViews, Android Bridge, and Zero-Reload SPA
 */

(function(window, document) {
    'use strict';

    const ShopNotifications = {
        config: window.SHOP_FIREBASE_CONFIG || null,
        messaging: null,
        currentToken: null,
        isSupported: false,

        init() {
            this.checkSupport();
            this.setupAndroidBridge();
            this.setupBadgeListeners();
            this.updateBadgeCount();

            if (this.isSupported && this.config && this.config.apiKey && this.config.projectId) {
                this.initFirebase();
            }

            // Expose globally
            window.ShopNotifications = this;
        },

        checkSupport() {
            this.isSupported = ('serviceWorker' in navigator && 'Notification' in window && 'fetch' in window);
        },

        setupAndroidBridge() {
            // Check if loaded inside an Android WebView with JSInterface
            if (window.AndroidBridge || window.AndroidNotification || window.Android) {
                const bridge = window.AndroidBridge || window.AndroidNotification || window.Android;
                console.log('[ShopNotifications] Android Native Bridge detected');
                
                // If Android app can provide FCM token directly
                if (typeof bridge.getFcmToken === 'function') {
                    try {
                        const token = bridge.getFcmToken();
                        if (token) {
                            this.saveTokenToServer(token, 'android');
                        }
                    } catch (e) {
                        console.warn('[ShopNotifications] Error reading token from AndroidBridge:', e);
                    }
                }
            }

            // Global callback if Android native code pushes token asynchronously
            window.onNativeFcmTokenReceived = (token) => {
                console.log('[ShopNotifications] Received token from native Android:', token);
                this.saveTokenToServer(token, 'android');
            };
        },

        async initFirebase() {
            try {
                if (!window.firebase || !window.firebase.messaging) {
                    // Dynamically load Firebase SDK if not present
                    await this.loadScript('https://www.gstatic.com/firebasejs/10.13.0/firebase-app-compat.js');
                    await this.loadScript('https://www.gstatic.com/firebasejs/10.13.0/firebase-messaging-compat.js');
                }

                if (!firebase.apps.length) {
                    firebase.initializeApp({
                        apiKey: this.config.apiKey,
                        authDomain: this.config.authDomain,
                        projectId: this.config.projectId,
                        storageBucket: this.config.storageBucket,
                        messagingSenderId: this.config.messagingSenderId,
                        appId: this.config.appId
                    });
                }

                this.messaging = firebase.messaging();

                // If already granted, retrieve token quietly
                if (Notification.permission === 'granted') {
                    this.registerServiceWorkerAndGetToken(false);
                }

                // Foreground message listener
                this.messaging.onMessage((payload) => {
                    console.log('[ShopNotifications] Foreground message received:', payload);
                    this.handleForegroundMessage(payload);
                });

            } catch (err) {
                console.warn('[ShopNotifications] Firebase initialization skipped or failed:', err);
            }
        },

        loadScript(src) {
            return new Promise((resolve, reject) => {
                if (document.querySelector(`script[src="${src}"]`)) {
                    return resolve();
                }
                const s = document.createElement('script');
                s.src = src;
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
        },

        async requestPermission() {
            if (!this.isSupported) {
                this.showToast('Push notifications are not supported on this browser/device.', 'warning');
                return false;
            }

            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    this.showToast('Push notifications enabled successfully! 🔔', 'success');
                    await this.registerServiceWorkerAndGetToken(true);
                    // Hide permission banner if visible
                    const banner = document.getElementById('sn-notif-permission-banner');
                    if (banner) banner.style.display = 'none';
                    return true;
                } else if (permission === 'denied') {
                    this.showToast('Notification permission was blocked in browser settings.', 'warning');
                    return false;
                }
            } catch (e) {
                console.error('[ShopNotifications] Error requesting permission:', e);
                return false;
            }
        },

        async registerServiceWorkerAndGetToken(userInitiated = false) {
            try {
                const swUrl = (window.SHOP_BASE_URL || '') + 'firebase-messaging-sw.js?' + new URLSearchParams({
                    apiKey: this.config?.apiKey || '',
                    projectId: this.config?.projectId || '',
                    messagingSenderId: this.config?.messagingSenderId || '',
                    appId: this.config?.appId || ''
                }).toString();

                const registration = await navigator.serviceWorker.register(swUrl, { scope: window.SHOP_BASE_URL || '/' });
                console.log('[ShopNotifications] Service Worker registered with scope:', registration.scope);

                if (!this.messaging) return;

                const tokenOptions = {
                    serviceWorkerRegistration: registration
                };
                if (this.config?.vapidKey) {
                    tokenOptions.vapidKey = this.config.vapidKey;
                }

                const token = await this.messaging.getToken(tokenOptions);
                if (token) {
                    this.currentToken = token;
                    console.log('[ShopNotifications] FCM Token obtained:', token.substring(0, 15) + '...');
                    await this.saveTokenToServer(token, this.getDeviceType());
                } else if (userInitiated) {
                    console.warn('[ShopNotifications] No registration token available.');
                }
            } catch (err) {
                console.error('[ShopNotifications] Error obtaining FCM token:', err);
            }
        },

        async saveTokenToServer(token, deviceType = 'web') {
            try {
                const endpoint = (window.SHOP_BASE_URL || '') + 'save-fcm-token.php';
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: token, device_type: deviceType })
                });
                const data = await response.json();
                console.log('[ShopNotifications] Token server sync response:', data);
            } catch (err) {
                console.warn('[ShopNotifications] Failed to save FCM token to backend:', err);
            }
        },

        getDeviceType() {
            const ua = navigator.userAgent || '';
            if (/android/i.test(ua)) return 'android';
            if (/iPad|iPhone|iPod/.test(ua)) return 'ios';
            return 'web';
        },

        handleForegroundMessage(payload) {
            const title = payload.notification?.title || payload.data?.title || 'New Notification';
            const body = payload.notification?.body || payload.data?.body || '';
            const actionUrl = payload.data?.url || payload.data?.click_action || 'notifications.php';

            // 1. Play subtle chime
            this.playNotificationSound();

            // 2. Display interactive in-app toast
            this.showInAppBanner(title, body, actionUrl);

            // 3. Update unread badge counter
            this.updateBadgeCount(1);

            // 4. Dispatch event for SPA screens
            window.dispatchEvent(new CustomEvent('shopnext:notification-received', { detail: payload }));
        },

        showInAppBanner(title, body, url) {
            const container = document.getElementById('sn-toast-container') || document.body;
            const banner = document.createElement('div');
            banner.className = 'sn-notif-push-banner';
            banner.innerHTML = `
                <div class="sn-push-banner-content" onclick="window.ShopNextSPA ? window.ShopNextSPA.navigate('${url}') : window.location.href='${url}'">
                    <div class="sn-push-icon">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="sn-push-text">
                        <div class="sn-push-title">${this.escapeHtml(title)}</div>
                        <div class="sn-push-body">${this.escapeHtml(body)}</div>
                    </div>
                </div>
                <button type="button" class="sn-push-close" onclick="event.stopPropagation(); this.parentElement.remove();">&times;</button>
            `;
            document.body.appendChild(banner);

            setTimeout(() => {
                banner.classList.add('sn-push-show');
            }, 50);

            setTimeout(() => {
                banner.classList.remove('sn-push-show');
                setTimeout(() => banner.remove(), 400);
            }, 6000);
        },

        showToast(message, type = 'info') {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
                return;
            }
            alert(message);
        },

        playNotificationSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            } catch (e) {}
        },

        async updateBadgeCount(increment = 0) {
            try {
                const endpoint = (window.SHOP_BASE_URL || '') + 'notification-actions.php?action=get_count';
                const res = await fetch(endpoint);
                const data = await res.json();
                if (data && data.success) {
                    const count = parseInt(data.unread_count, 10) || 0;
                    this.applyBadgeCount(count);
                }
            } catch (e) {}
        },

        applyBadgeCount(count) {
            document.querySelectorAll('.sn-notif-badge').forEach(badge => {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.textContent = '0';
                    badge.style.display = 'none';
                }
            });
        },

        setupBadgeListeners() {
            window.addEventListener('shopnext:notification-read', () => {
                this.updateBadgeCount();
            });
            document.addEventListener('shopnext:page-loaded', () => {
                this.updateBadgeCount();
                if ('Notification' in window && Notification.permission === 'default') {
                    const banner = document.getElementById('sn-notif-permission-banner');
                    if (banner) banner.style.display = 'flex';
                }
            });
        },

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }
    };

    // Auto initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ShopNotifications.init());
    } else {
        ShopNotifications.init();
    }

})(window, document);
