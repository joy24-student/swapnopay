<?php
/**
 * Customer Notifications Center
 * Responsive, Modern, Real-time & SPA-compatible
 */
$cur_page = 'notifications.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/admin/inc/notifications.php';

$merchantId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';
$customerId = !empty($_SESSION['customer']['cust_id']) ? (int)$_SESSION['customer']['cust_id'] : null;

$activeTab = trim((string)($_GET['tab'] ?? 'all'));
if (!in_array($activeTab, ['all', 'orders', 'promos', 'system'], true)) {
    $activeTab = 'all';
}

$notifications = getCustomerNotifications($pdo, $merchantId, $customerId, 50, $activeTab);
$unreadCount = getUnreadNotificationCount($pdo, $merchantId, $customerId);
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/notifications.css">

<div class="sn-notif-page-wrapper">
    <div class="sn-notif-container">
        
        <!-- Header Bar -->
        <div class="sn-notif-header">
            <div class="sn-notif-header-title">
                <a href="<?php echo BASE_URL; ?>" class="sn-btn-notif-action" aria-label="Back">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h1>
                    <span>Notifications</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="sn-notif-pill-count" id="sn-notif-header-unread"><?php echo $unreadCount; ?> new</span>
                    <?php endif; ?>
                </h1>
            </div>

            <div class="sn-notif-header-actions">
                <?php if (!empty($notifications)): ?>
                    <button type="button" class="sn-btn-notif-action" id="sn-btn-mark-all" onclick="markAllNotificationsRead()">
                        <i class="fa-solid fa-check-double"></i>
                        <span>Mark all read</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Push Notification Permission Banner -->
        <div class="sn-notif-perm-card" id="sn-notif-permission-banner" style="display: none;">
            <div class="sn-notif-perm-content">
                <div class="sn-notif-perm-icon">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div class="sn-notif-perm-text">
                    <h4>Turn On Push Notifications</h4>
                    <p>Get instant updates on your orders, tracking alerts, and exclusive discounts right on your device.</p>
                </div>
            </div>
            <button type="button" class="sn-btn-perm-enable" onclick="window.ShopNotifications && window.ShopNotifications.requestPermission()">
                Enable Alerts
            </button>
        </div>

        <!-- Navigation Tabs -->
        <div class="sn-notif-tabs">
            <a href="<?php echo BASE_URL; ?>notifications.php?tab=all" class="sn-notif-tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>">
                <i class="fa-solid fa-list-ul"></i> All
            </a>
            <a href="<?php echo BASE_URL; ?>notifications.php?tab=orders" class="sn-notif-tab <?php echo $activeTab === 'orders' ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i> Orders
            </a>
            <a href="<?php echo BASE_URL; ?>notifications.php?tab=promos" class="sn-notif-tab <?php echo $activeTab === 'promos' ? 'active' : ''; ?>">
                <i class="fa-solid fa-tag"></i> Offers & Deals
            </a>
            <a href="<?php echo BASE_URL; ?>notifications.php?tab=system" class="sn-notif-tab <?php echo $activeTab === 'system' ? 'active' : ''; ?>">
                <i class="fa-solid fa-bullhorn"></i> Updates
            </a>
        </div>

        <!-- Notifications List -->
        <?php if (!empty($notifications)): ?>
            <div class="sn-notif-list" id="sn-notifications-container">
                <?php foreach ($notifications as $notif): 
                    $type = htmlspecialchars($notif['type'] ?? 'general');
                    $isUnread = empty($notif['is_read']);
                    $timeAgo = timeAgoNotification($notif['created_at']);
                    
                    // Choose icon by type
                    $iconClass = 'fa-solid fa-bell';
                    $typeClass = 'type-system';
                    if ($type === 'order') {
                        $iconClass = 'fa-solid fa-truck-fast';
                        $typeClass = 'type-order';
                    } elseif ($type === 'promo' || $type === 'deal') {
                        $iconClass = 'fa-solid fa-fire';
                        $typeClass = 'type-promo';
                    } elseif ($type === 'broadcast') {
                        $iconClass = 'fa-solid fa-bullhorn';
                        $typeClass = 'type-broadcast';
                    } elseif ($type === 'system') {
                        $iconClass = 'fa-solid fa-shield-halved';
                        $typeClass = 'type-system';
                    }
                ?>
                    <div class="sn-notif-item <?php echo $isUnread ? 'is-unread' : ''; ?>" id="sn-notif-row-<?php echo $notif['id']; ?>" data-id="<?php echo $notif['id']; ?>">
                        <div class="sn-notif-type-icon <?php echo $typeClass; ?>">
                            <i class="<?php echo $iconClass; ?>"></i>
                        </div>

                        <div class="sn-notif-main">
                            <div class="sn-notif-topline">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($isUnread): ?>
                                        <span class="sn-unread-dot" title="Unread"></span>
                                    <?php endif; ?>
                                    <h3 class="sn-notif-title"><?php echo htmlspecialchars($notif['title']); ?></h3>
                                </div>
                                <span class="sn-notif-time"><?php echo $timeAgo; ?></span>
                            </div>

                            <p class="sn-notif-body"><?php echo nl2br(htmlspecialchars($notif['body'])); ?></p>

                            <div class="sn-notif-footer-actions">
                                <?php if (!empty($notif['action_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($notif['action_url']); ?>" class="sn-btn-notif-link" onclick="markNotificationRead(<?php echo $notif['id']; ?>, false)">
                                        <span>View</span> <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($isUnread): ?>
                                    <button type="button" class="sn-btn-mark-read" onclick="markNotificationRead(<?php echo $notif['id']; ?>)">
                                        <i class="fa-solid fa-check"></i> Mark read
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="sn-btn-notif-del" onclick="deleteNotification(<?php echo $notif['id']; ?>)" title="Delete notification">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="sn-notif-empty">
                <div class="sn-notif-empty-icon">
                    <i class="fa-regular fa-bell-slash"></i>
                </div>
                <h3>No notifications yet</h3>
                <p>You're all caught up! Updates regarding your orders and special promotions will appear here.</p>
                <a href="<?php echo BASE_URL; ?>" class="sn-btn-notif-action" style="background: #fab802; color: #0f172a; border-color: #fab802; font-weight: 700;">
                    Start Shopping
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if push permission banner should be shown
    if ('Notification' in window && Notification.permission === 'default') {
        const banner = document.getElementById('sn-notif-permission-banner');
        if (banner) banner.style.display = 'flex';
    }
});

async function markNotificationRead(id, updateUi = true) {
    try {
        const endpoint = '<?php echo BASE_URL; ?>notification-actions.php?action=mark_read&id=' + id;
        const res = await fetch(endpoint);
        const data = await res.json();
        if (data.success && updateUi) {
            const row = document.getElementById('sn-notif-row-' + id);
            if (row) {
                row.classList.remove('is-unread');
                const dot = row.querySelector('.sn-unread-dot');
                if (dot) dot.remove();
                const readBtn = row.querySelector('.sn-btn-mark-read');
                if (readBtn) readBtn.remove();
            }
            if (window.ShopNotifications) {
                window.ShopNotifications.applyBadgeCount(data.unread_count);
            }
        }
    } catch (e) {
        console.error('Error marking notification as read:', e);
    }
}

async function markAllNotificationsRead() {
    try {
        const endpoint = '<?php echo BASE_URL; ?>notification-actions.php?action=mark_all_read';
        const res = await fetch(endpoint);
        const data = await res.json();
        if (data.success) {
            document.querySelectorAll('.sn-notif-item.is-unread').forEach(row => {
                row.classList.remove('is-unread');
                const dot = row.querySelector('.sn-unread-dot');
                if (dot) dot.remove();
                const readBtn = row.querySelector('.sn-btn-mark-read');
                if (readBtn) readBtn.remove();
            });
            const unreadHeader = document.getElementById('sn-notif-header-unread');
            if (unreadHeader) unreadHeader.remove();
            if (window.ShopNotifications) {
                window.ShopNotifications.applyBadgeCount(0);
            }
            if (typeof window.showToast === 'function') {
                window.showToast('All notifications marked as read', 'success');
            }
        }
    } catch (e) {
        console.error('Error marking all notifications read:', e);
    }
}

async function deleteNotification(id) {
    if (!confirm('Are you sure you want to delete this notification?')) return;
    try {
        const endpoint = '<?php echo BASE_URL; ?>notification-actions.php?action=delete&id=' + id;
        const res = await fetch(endpoint);
        const data = await res.json();
        if (data.success) {
            const row = document.getElementById('sn-notif-row-' + id);
            if (row) {
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    row.remove();
                    const container = document.getElementById('sn-notifications-container');
                    if (container && container.children.length === 0) {
                        location.reload();
                    }
                }, 250);
            }
            if (window.ShopNotifications) {
                window.ShopNotifications.applyBadgeCount(data.unread_count);
            }
        }
    } catch (e) {
        console.error('Error deleting notification:', e);
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
