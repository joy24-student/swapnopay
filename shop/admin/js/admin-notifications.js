/**
 * Admin Notification Modal & Flyout Engine
 * Real-time polling, Web Audio chime, category filtering, search, and read status tracking.
 */

(function () {
    'use strict';

    class AdminNotificationManager {
        constructor() {
            this.apiUrl = 'admin_notifications_api.php';
            this.notifications = [];
            this.unreadCount = 0;
            this.previousUnreadCount = null;
            this.activeCategory = 'all';
            this.searchQuery = '';
            this.isOpen = false;
            this.pollTimer = null;
            this.pollInterval = 25000; // 25 seconds
            this.soundEnabled = localStorage.getItem('sn_notif_sound_enabled') !== 'false';
            this.audioCtx = null;

            this.init();
        }

        init() {
            // Bind DOM events
            document.addEventListener('DOMContentLoaded', () => {
                this.updateSoundButtonUI();
                this.fetchNotifications(true);
                this.startPolling();
                this.bindGlobalShortcuts();
            });

            // Visibility API: resume polling when tab becomes visible
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.fetchNotifications(true);
                    this.startPolling();
                } else {
                    this.stopPolling();
                }
            });
        }

        // =========================================================
        // AUDIO NOTIFICATION CHIME (WEB AUDIO API)
        // =========================================================
        playChime() {
            if (!this.soundEnabled) return;
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;

                if (!this.audioCtx) {
                    this.audioCtx = new AudioContext();
                }

                if (this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }

                const now = this.audioCtx.currentTime;

                // Two-tone friendly chime (E5 -> A5)
                const osc1 = this.audioCtx.createOscillator();
                const gain1 = this.audioCtx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(659.25, now); // E5
                gain1.gain.setValueAtTime(0.15, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
                osc1.connect(gain1);
                gain1.connect(this.audioCtx.destination);
                osc1.start(now);
                osc1.stop(now + 0.35);

                const osc2 = this.audioCtx.createOscillator();
                const gain2 = this.audioCtx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880, now + 0.12); // A5
                gain2.gain.setValueAtTime(0.2, now + 0.12);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
                osc2.connect(gain2);
                gain2.connect(this.audioCtx.destination);
                osc2.start(now + 0.12);
                osc2.stop(now + 0.55);
            } catch (e) {
                // Audio context may be restricted by autoplay policy until user gesture
            }
        }

        toggleSound() {
            this.soundEnabled = !this.soundEnabled;
            localStorage.setItem('sn_notif_sound_enabled', this.soundEnabled ? 'true' : 'false');
            this.updateSoundButtonUI();

            if (this.soundEnabled) {
                this.playChime();
                this.showToast('Notification chime enabled', 'success');
            } else {
                this.showToast('Notification chime muted', 'info');
            }
        }

        updateSoundButtonUI() {
            const onIcon = document.getElementById('snSoundIconOn');
            const offIcon = document.getElementById('snSoundIconOff');
            const btn = document.getElementById('snNotifSoundBtn');
            if (onIcon && offIcon) {
                if (this.soundEnabled) {
                    onIcon.style.display = 'block';
                    offIcon.style.display = 'none';
                    if (btn) btn.title = 'Notification chime is ON (Click to mute)';
                } else {
                    onIcon.style.display = 'none';
                    offIcon.style.display = 'block';
                    if (btn) btn.title = 'Notification chime is MUTED (Click to unmute)';
                }
            }
        }

        // =========================================================
        // DATA FETCHING & POLLING
        // =========================================================
        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => {
                this.fetchNotifications(false);
            }, this.pollInterval);
        }

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        }

        async fetchNotifications(isSilent = false) {
            try {
                const res = await fetch(this.apiUrl + '?action=get_notifications', { credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();

                if (data.status === 'success') {
                    const newUnread = parseInt(data.unread_count, 10) || 0;

                    // Play chime if unread count increased
                    if (this.previousUnreadCount !== null && newUnread > this.previousUnreadCount) {
                        this.playChime();
                    }

                    this.previousUnreadCount = newUnread;
                    this.unreadCount = newUnread;
                    this.notifications = data.notifications || [];

                    this.updateHeaderBadge(newUnread);
                    this.updateTabCounts(data.counts || {});
                    this.renderList();
                }
            } catch (err) {
                if (!isSilent) console.error('Error fetching admin notifications:', err);
            }
        }

        refresh(manual = false) {
            const container = document.getElementById('snNotifListContainer');
            if (manual && container) {
                container.innerHTML = `
                    <div class="sn-notif-loading">
                        <div class="sn-notif-spinner"></div>
                        <span>Refreshing live notifications...</span>
                    </div>
                `;
            }
            this.fetchNotifications(false).then(() => {
                if (manual) this.showToast('Notifications refreshed', 'info');
            });
        }

        // =========================================================
        // UI BADGES & COUNTS
        // =========================================================
        updateHeaderBadge(count) {
            const bellBadge = document.getElementById('snAdminBellBadge');
            if (bellBadge) {
                if (count > 0) {
                    bellBadge.textContent = count > 99 ? '99+' : count;
                    bellBadge.style.display = 'flex';
                    bellBadge.classList.add('sn-badge-pulse');
                } else {
                    bellBadge.style.display = 'none';
                    bellBadge.classList.remove('sn-badge-pulse');
                }
            }

            const modalBadge = document.getElementById('snNotifUnreadCountBadge');
            if (modalBadge) {
                modalBadge.textContent = count > 0 ? `${count} new` : 'All read';
                modalBadge.style.background = count > 0 ? '#fee2e2' : '#dcfce7';
                modalBadge.style.color = count > 0 ? '#b91c1c' : '#15803d';
            }
        }

        updateTabCounts(counts) {
            const setTab = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = val || 0;
            };
            setTab('tabCountAll', counts.all || this.notifications.length);
            setTab('tabCountOrders', counts.orders || 0);
            setTab('tabCountInventory', counts.inventory || 0);
            setTab('tabCountCustomers', counts.customers || 0);
            setTab('tabCountSystem', counts.system || 0);
        }

        // =========================================================
        // MODAL TOGGLE & DISPLAY
        // =========================================================
        toggleModal(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (this.isOpen) {
                this.closeModal();
            } else {
                this.openModal();
            }
        }

        openModal() {
            this.isOpen = true;
            const modal = document.getElementById('snAdminNotifModal');
            const backdrop = document.getElementById('snAdminNotifBackdrop');
            if (modal && backdrop) {
                backdrop.classList.add('active');
                modal.classList.add('active');
                modal.setAttribute('aria-hidden', 'false');
            }
            // Refresh on open
            this.fetchNotifications(true);
        }

        closeModal() {
            this.isOpen = false;
            const modal = document.getElementById('snAdminNotifModal');
            const backdrop = document.getElementById('snAdminNotifBackdrop');
            if (modal && backdrop) {
                modal.classList.remove('active');
                backdrop.classList.remove('active');
                modal.setAttribute('aria-hidden', 'true');
            }
            this.closeAnnounceModal();
        }

        bindGlobalShortcuts() {
            document.addEventListener('keydown', (e) => {
                // ESC closes modal
                if (e.key === 'Escape' && this.isOpen) {
                    this.closeModal();
                }
                // Alt + N shortcut toggles notifications
                if (e.altKey && (e.key === 'n' || e.key === 'N')) {
                    e.preventDefault();
                    this.toggleModal();
                }
            });
        }

        // =========================================================
        // FILTERING & SEARCH
        // =========================================================
        filterCategory(category, btn) {
            this.activeCategory = category;
            const tabs = document.querySelectorAll('.sn-notif-tab');
            tabs.forEach(t => t.classList.remove('active'));
            if (btn) btn.classList.add('active');
            this.renderList();
        }

        handleSearch(query) {
            this.searchQuery = (query || '').trim().toLowerCase();
            const clearBtn = document.getElementById('snNotifSearchClear');
            if (clearBtn) {
                clearBtn.style.display = this.searchQuery ? 'block' : 'none';
            }
            this.renderList();
        }

        clearSearch() {
            const input = document.getElementById('snNotifSearchInput');
            if (input) input.value = '';
            this.handleSearch('');
        }

        // =========================================================
        // RENDERING
        // =========================================================
        renderList() {
            const container = document.getElementById('snNotifListContainer');
            if (!container) return;

            // Filter by active category
            let filtered = this.notifications.filter(item => {
                if (this.activeCategory !== 'all' && item.category !== this.activeCategory) {
                    return false;
                }
                if (this.searchQuery) {
                    const haystack = `${item.title} ${item.message} ${item.category} ${item.action_url}`.toLowerCase();
                    if (!haystack.includes(this.searchQuery)) {
                        return false;
                    }
                }
                return true;
            });

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="sn-notif-empty">
                        <div class="sn-notif-empty-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 14 14"></polyline>
                            </svg>
                        </div>
                        <h4>No notifications found</h4>
                        <p>${this.searchQuery ? 'No notifications matching your search.' : 'You are all caught up in this category!'}</p>
                    </div>
                `;
                return;
            }

            const unreadItems = filtered.filter(i => !i.is_read);
            const readItems = filtered.filter(i => i.is_read);

            let html = '';

            if (unreadItems.length > 0) {
                html += `<div class="sn-notif-section-head">New (${unreadItems.length})</div>`;
                unreadItems.forEach(item => {
                    html += this.renderItemHtml(item);
                });
            }

            if (readItems.length > 0) {
                html += `<div class="sn-notif-section-head">Earlier</div>`;
                readItems.forEach(item => {
                    html += this.renderItemHtml(item);
                });
            }

            container.innerHTML = html;
        }

        renderItemHtml(item) {
            const isUnread = !item.is_read;
            const sevClass = `sev-${item.severity || 'info'}`;
            const catClass = item.category === 'customers' ? 'cat-cust' : sevClass;

            // Icon by category
            let iconSvg = '';
            if (item.category === 'orders') {
                iconSvg = `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>`;
            } else if (item.category === 'inventory') {
                iconSvg = `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
            } else if (item.category === 'customers') {
                iconSvg = `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>`;
            } else {
                iconSvg = `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;
            }

            const esc = (s) => (s ? String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : '');

            return `
                <div class="sn-notif-item ${isUnread ? 'unread' : 'read'}" data-key="${esc(item.key)}">
                    <div class="sn-notif-avatar ${catClass}">
                        ${iconSvg}
                    </div>
                    <div class="sn-notif-content-wrap">
                        <div class="sn-notif-row-top">
                            <span class="sn-notif-item-title">
                                ${isUnread ? '<span class="sn-notif-dot-unread"></span>' : ''}
                                ${esc(item.title)}
                            </span>
                            <span class="sn-notif-item-time">${esc(item.time_human)}</span>
                        </div>
                        <div class="sn-notif-item-desc">${esc(item.message)}</div>
                        <div class="sn-notif-item-actions">
                            ${item.action_url && item.action_url !== 'javascript:void(0)' ? `
                                <a href="${esc(item.action_url)}" class="sn-notif-btn-act" onclick="window.snAdminNotifications && window.snAdminNotifications.markRead('${esc(item.key)}')">
                                    <span>${esc(item.action_label || 'View')}</span>
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                </a>
                            ` : ''}
                            ${isUnread ? `
                                <button type="button" class="sn-notif-btn-mark-single" onclick="window.snAdminNotifications && window.snAdminNotifications.markRead('${esc(item.key)}')">
                                    Mark as read
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        // =========================================================
        // ACTIONS: MARK READ & MARK ALL READ
        // =========================================================
        async markRead(key) {
            if (!key) return;

            // Optimistic update
            const target = this.notifications.find(n => n.key === key);
            if (target && !target.is_read) {
                target.is_read = true;
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.updateHeaderBadge(this.unreadCount);
                this.renderList();
            }

            try {
                const fd = new FormData();
                fd.append('action', 'mark_read');
                fd.append('key', key);
                await fetch(this.apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            } catch (e) {}
        }

        async markAllRead() {
            // Optimistic update
            this.notifications.forEach(n => { n.is_read = true; });
            this.unreadCount = 0;
            this.updateHeaderBadge(0);
            this.renderList();
            this.showToast('All notifications marked as read', 'success');

            try {
                const fd = new FormData();
                fd.append('action', 'mark_all_read');
                await fetch(this.apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
            } catch (e) {}
        }

        // =========================================================
        // SUB-MODAL: ANNOUNCEMENTS
        // =========================================================
        openAnnounceModal() {
            const dlg = document.getElementById('snAnnounceDialog');
            if (dlg) {
                dlg.style.display = 'flex';
                const input = document.getElementById('snAnnounceTitle');
                if (input) input.focus();
            }
        }

        closeAnnounceModal() {
            const dlg = document.getElementById('snAnnounceDialog');
            if (dlg) dlg.style.display = 'none';
        }

        async submitAnnouncement(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitAnnouncement');
            const orig = btn ? btn.innerHTML : '';
            if (btn) {
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Posting...';
                btn.disabled = true;
            }

            const fd = new FormData();
            fd.append('action', 'create_announcement');
            fd.append('title', document.getElementById('snAnnounceTitle').value);
            fd.append('message', document.getElementById('snAnnounceMessage').value);
            fd.append('category', document.getElementById('snAnnounceCategory').value);
            fd.append('severity', document.getElementById('snAnnounceSeverity').value);
            fd.append('action_url', document.getElementById('snAnnounceUrl').value);

            try {
                const res = await fetch(this.apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showToast(data.message, 'success');
                    this.closeAnnounceModal();
                    document.getElementById('snAnnounceForm').reset();
                    this.fetchNotifications(false);
                } else {
                    this.showToast(data.message || 'Error posting announcement', 'danger');
                }
            } catch (err) {
                this.showToast('Failed to post announcement.', 'danger');
            } finally {
                if (btn) {
                    btn.innerHTML = orig;
                    btn.disabled = false;
                }
            }
        }

        // =========================================================
        // FLOATING TOAST HELPER
        // =========================================================
        showToast(msg, type = 'info') {
            if (window.showAdminToast) {
                window.showAdminToast(msg, type);
                return;
            }
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: ${type === 'success' ? '#10b981' : (type === 'danger' ? '#ef4444' : '#0f172a')};
                color: #fff;
                padding: 10px 18px;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 700;
                box-shadow: 0 4px 14px rgba(0,0,0,0.18);
                z-index: 99999;
                transition: opacity 0.3s;
                font-family: sans-serif;
            `;
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 2500);
        }
    }

    // Expose globally
    window.snAdminNotifications = new AdminNotificationManager();

})();

