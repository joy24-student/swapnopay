<?php
/**
 * Admin Notification Modal & Flyout Component
 * Enterprise design matching ShopMart theme.
 */
?>
<!-- ADMIN NOTIFICATION BACKDROP -->
<div id="snAdminNotifBackdrop" class="sn-notif-backdrop" onclick="window.snAdminNotifications && window.snAdminNotifications.closeModal()"></div>

<!-- ADMIN NOTIFICATION FLYOUT / MODAL -->
<div id="snAdminNotifModal" class="sn-notif-modal" role="dialog" aria-labelledby="snNotifTitle" aria-hidden="true">
    
    <!-- Modal Header -->
    <div class="sn-notif-header">
        <div class="sn-notif-header-left">
            <div class="sn-notif-bell-icon-wrap">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="sn-notif-live-dot" title="Live Polling Active"></span>
            </div>
            <div>
                <h3 id="snNotifTitle" class="sn-notif-title">Notifications</h3>
                <span id="snNotifUnreadCountBadge" class="sn-notif-count-badge">0 new</span>
            </div>
        </div>

        <div class="sn-notif-header-actions">
            <!-- Audio Sound Toggle -->
            <button type="button" class="sn-notif-tool-btn" id="snNotifSoundBtn" onclick="window.snAdminNotifications && window.snAdminNotifications.toggleSound()" title="Toggle Notification Chime">
                <svg id="snSoundIconOn" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                </svg>
                <svg id="snSoundIconOff" style="display:none;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                    <path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"></path>
                    <path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"></path>
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                </svg>
            </button>

            <!-- Mark All As Read -->
            <button type="button" class="sn-notif-tool-btn" id="snNotifMarkAllBtn" onclick="window.snAdminNotifications && window.snAdminNotifications.markAllRead()" title="Mark all as read">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6L7 17l-5-5"></path>
                    <path d="M22 10l-7.5 7.5L13 16"></path>
                </svg>
            </button>

            <!-- Post Announcement -->
            <button type="button" class="sn-notif-tool-btn" onclick="window.snAdminNotifications && window.snAdminNotifications.openAnnounceModal()" title="Create Announcement">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </button>

            <!-- Close Modal -->
            <button type="button" class="sn-notif-close-btn" onclick="window.snAdminNotifications && window.snAdminNotifications.closeModal()" title="Close">
                &times;
            </button>
        </div>
    </div>

    <!-- Filter Category Tabs -->
    <div class="sn-notif-tabs-bar">
        <button type="button" class="sn-notif-tab active" data-category="all" onclick="window.snAdminNotifications && window.snAdminNotifications.filterCategory('all', this)">
            All <span class="sn-notif-tab-badge" id="tabCountAll">0</span>
        </button>
        <button type="button" class="sn-notif-tab" data-category="orders" onclick="window.snAdminNotifications && window.snAdminNotifications.filterCategory('orders', this)">
            Orders <span class="sn-notif-tab-badge" id="tabCountOrders">0</span>
        </button>
        <button type="button" class="sn-notif-tab" data-category="inventory" onclick="window.snAdminNotifications && window.snAdminNotifications.filterCategory('inventory', this)">
            Stock Alerts <span class="sn-notif-tab-badge" id="tabCountInventory">0</span>
        </button>
        <button type="button" class="sn-notif-tab" data-category="customers" onclick="window.snAdminNotifications && window.snAdminNotifications.filterCategory('customers', this)">
            Customers <span class="sn-notif-tab-badge" id="tabCountCustomers">0</span>
        </button>
        <button type="button" class="sn-notif-tab" data-category="system" onclick="window.snAdminNotifications && window.snAdminNotifications.filterCategory('system', this)">
            System <span class="sn-notif-tab-badge" id="tabCountSystem">0</span>
        </button>
    </div>

    <!-- Live Search Input -->
    <div class="sn-notif-search-wrap">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.3">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="snNotifSearchInput" placeholder="Filter notifications (order ID, customer, product)..." oninput="window.snAdminNotifications && window.snAdminNotifications.handleSearch(this.value)">
        <button type="button" id="snNotifSearchClear" onclick="window.snAdminNotifications && window.snAdminNotifications.clearSearch()" style="display:none;">&times;</button>
    </div>

    <!-- Notification Items List -->
    <div id="snNotifListContainer" class="sn-notif-list-container">
        <!-- Rendered via JS -->
        <div class="sn-notif-loading">
            <div class="sn-notif-spinner"></div>
            <span>Fetching real-time notifications...</span>
        </div>
    </div>

    <!-- Modal Footer -->
    <div class="sn-notif-footer">
        <a href="notifications.php" class="sn-notif-footer-link">
            <span>View All Notifications Page</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </a>
        <div class="sn-notif-footer-right">
            <button type="button" class="sn-notif-refresh-btn" onclick="window.snAdminNotifications && window.snAdminNotifications.refresh(true)" title="Refresh Now">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                <span>Live Refresh</span>
            </button>
        </div>
    </div>
</div>

<!-- EMBEDDED MODAL: CREATE ANNOUNCEMENT / CUSTOM ALERT -->
<div id="snAnnounceDialog" class="sn-announce-modal-wrap" style="display:none;">
    <div class="sn-announce-modal-card">
        <div class="sn-announce-modal-head">
            <h4><i class="fa fa-bullhorn" style="color:#0284c7;"></i> Post Store Announcement</h4>
            <button type="button" class="sn-announce-close" onclick="window.snAdminNotifications && window.snAdminNotifications.closeAnnounceModal()">&times;</button>
        </div>
        <form id="snAnnounceForm" onsubmit="window.snAdminNotifications && window.snAdminNotifications.submitAnnouncement(event)">
            <div class="sn-announce-body">
                <div class="form-group" style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:700; color:#334155;">Notification Title *</label>
                    <input type="text" id="snAnnounceTitle" class="form-control" required placeholder="e.g. Flash Sale Alert, Courier Holiday...">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:700; color:#334155;">Message Content *</label>
                    <textarea id="snAnnounceMessage" class="form-control" rows="3" required placeholder="Details about this store notification..."></textarea>
                </div>
                <div style="display:flex; gap:10px; margin-bottom:12px;">
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:700; color:#334155;">Category</label>
                        <select id="snAnnounceCategory" class="form-control">
                            <option value="system">System</option>
                            <option value="orders">Orders</option>
                            <option value="inventory">Inventory</option>
                            <option value="customers">Customers</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:700; color:#334155;">Severity Level</label>
                        <select id="snAnnounceSeverity" class="form-control">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Amber)</option>
                            <option value="danger">Urgent / Danger (Red)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:700; color:#334155;">Target Action URL (Optional)</label>
                    <input type="text" id="snAnnounceUrl" class="form-control" placeholder="e.g. order.php or product.php">
                </div>
            </div>
            <div class="sn-announce-foot">
                <button type="button" class="btn btn-default btn-sm" onclick="window.snAdminNotifications && window.snAdminNotifications.closeAnnounceModal()">Cancel</button>
                <button type="submit" id="btnSubmitAnnouncement" class="btn btn-primary btn-sm" style="font-weight:700; background:#0284c7; border-color:#0284c7;">
                    <i class="fa fa-paper-plane"></i> Post Notification
                </button>
            </div>
        </form>
    </div>
</div>

