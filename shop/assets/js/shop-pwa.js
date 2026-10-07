/**
 * ShopMart Storefront PWA Controller
 * Handles Service Worker registration, beforeinstallprompt capture,
 * Chrome side bar/panel launch, and install modal UI.
 */

(function () {
    'use strict';

    let deferredPrompt = null;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    // 1. Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js', { scope: './' })
                .then((reg) => {
                    console.log('[PWA] Storefront Service Worker registered with scope:', reg.scope);
                })
                .catch((err) => {
                    console.warn('[PWA] Storefront Service Worker registration failed:', err);
                });
        });
    }

    // 2. Capture beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent default mini-infobar on mobile Chrome
        e.preventDefault();
        deferredPrompt = e;
        window.snShopDeferredPrompt = e;
        console.log('[PWA] Storefront beforeinstallprompt captured');

        // Show / highlight install buttons across the UI
        document.querySelectorAll('.sn-pwa-install-btn, #btnShopPwaInstall, #snShopPwaMobileItem').forEach((el) => {
            el.classList.add('pwa-ready');
            el.style.display = 'inline-flex';
        });
    });

    // 3. Listen for appinstalled
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        window.snShopDeferredPrompt = null;
        console.log('[PWA] ShopMart Storefront WebApp was successfully installed!');
        closeShopPwaModal();

        document.querySelectorAll('.sn-pwa-install-btn, #btnShopPwaInstall').forEach((el) => {
            el.innerHTML = '<span style="font-weight:700; color:#16a34a;">✓ Installed</span>';
            el.disabled = true;
        });

        if (typeof showShopToast === 'function') {
            showShopToast('ShopMart app installed successfully!');
        }
    });

    // 4. Primary Install Trigger Function
    window.triggerShopPwaInstall = function (e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        if (isStandalone) {
            alert('ShopMart is already running as an installed WebApp!');
            return;
        }

        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('[PWA] User accepted the installation prompt');
                    deferredPrompt = null;
                    window.snShopDeferredPrompt = null;
                } else {
                    console.log('[PWA] User dismissed the installation prompt');
                }
            });
        } else {
            // Prompt not directly available (desktop Chrome address bar, iOS, or already prompted)
            openShopPwaModal();
        }
    };

    // 5. Open / Close Modal Dialog
    window.openShopPwaModal = function () {
        const modal = document.getElementById('shopPwaInstallModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeShopPwaModal = function () {
        const modal = document.getElementById('shopPwaInstallModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // 6. Launch in Chrome Side Panel / Sidebar Compact Window
    window.launchShopSidePanel = function () {
        closeShopPwaModal();
        const width = 420;
        const height = Math.min(840, window.screen.availHeight - 60);
        const left = Math.max(0, window.screen.availWidth - width - 20);
        const top = 40;
        const url = window.location.href;

        const sideWin = window.open(
            url,
            'ShopMart_SidePanel',
            `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes`
        );
        if (sideWin) {
            sideWin.focus();
        }
    };

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeShopPwaModal();
        }
    });

})();
