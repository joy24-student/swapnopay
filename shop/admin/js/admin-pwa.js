/**
 * ShopMart Admin PWA Controller
 * Handles Service Worker registration, beforeinstallprompt capture,
 * Chrome side bar/panel launch, and install modal UI for Admin Control Panel.
 */

(function () {
    'use strict';

    let deferredPrompt = null;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    // 1. Register Admin Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js', { scope: './' })
                .then((reg) => {
                    console.log('[PWA-Admin] Service Worker registered with scope:', reg.scope);
                })
                .catch((err) => {
                    console.warn('[PWA-Admin] Service Worker registration failed:', err);
                });
        });
    }

    // 2. Capture beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        window.snAdminDeferredPrompt = e;
        console.log('[PWA-Admin] beforeinstallprompt event captured');

        // Highlight install controls
        document.querySelectorAll('.sn-pwa-header-btn, .sn-sidebar-pwa-item').forEach((el) => {
            el.classList.add('pwa-ready');
            el.style.display = '';
        });
    });

    // 3. Listen for appinstalled
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        window.snAdminDeferredPrompt = null;
        console.log('[PWA-Admin] Admin WebApp was successfully installed!');
        closeAdminPwaModal();

        const btn = document.getElementById('btnAdminPwaInstallHeader');
        if (btn) {
            btn.innerHTML = '<span class="sn-pwa-btn-icon"><i class="fa fa-check text-green"></i></span><span class="sn-pwa-btn-title hidden-xs">Installed</span>';
            btn.style.pointerEvents = 'none';
        }

        if (typeof showAdminToast === 'function') {
            showAdminToast('Admin WebApp installed successfully!', 'success');
        }
    });

    // 4. Trigger Install Function
    window.triggerAdminPwaInstall = function (e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        if (isStandalone) {
            if (typeof showAdminToast === 'function') {
                showAdminToast('Admin Panel is already running as an installed WebApp.', 'info');
            } else {
                alert('Admin Panel is already running as an installed WebApp!');
            }
            return;
        }

        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('[PWA-Admin] User accepted installation');
                    deferredPrompt = null;
                    window.snAdminDeferredPrompt = null;
                } else {
                    console.log('[PWA-Admin] User dismissed installation');
                }
            });
        } else {
            // Prompt not immediately available -> show guided install modal
            openAdminPwaModal();
        }
    };

    // 5. Open / Close Modal Dialog
    window.openAdminPwaModal = function () {
        const modal = document.getElementById('adminPwaInstallModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeAdminPwaModal = function () {
        const modal = document.getElementById('adminPwaInstallModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // 6. Launch in Chrome Side Panel / Compact Sidebar Window
    window.launchAdminSidePanel = function () {
        closeAdminPwaModal();
        const width = 430;
        const height = Math.min(880, window.screen.availHeight - 60);
        const left = Math.max(0, window.screen.availWidth - width - 20);
        const top = 40;
        const url = window.location.href;

        const sideWin = window.open(
            url,
            'ShopMart_Admin_SidePanel',
            `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes`
        );
        if (sideWin) {
            sideWin.focus();
        }
    };

    // Close on escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAdminPwaModal();
        }
    });

})();
