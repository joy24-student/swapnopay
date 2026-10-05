<?php
if (!defined('BASE_URL')) {
    $cur_script = basename($_SERVER['PHP_SELF'] ?? '');
} else {
    $cur_script = basename($_SERVER['PHP_SELF'] ?? '');
}

$cust_name = $_SESSION['customer']['cust_name'] ?? 'Customer';
$cust_email = $_SESSION['customer']['cust_email'] ?? '';
$cust_id = (int)($_SESSION['customer']['cust_id'] ?? 0);

// Generate Initials
$name_parts = explode(' ', trim($cust_name));
$initials = '';
if (!empty($name_parts[0])) {
    $initials .= strtoupper(substr($name_parts[0], 0, 1));
}
if (!empty($name_parts[1])) {
    $initials .= strtoupper(substr($name_parts[1], 0, 1));
}
if (empty($initials)) {
    $initials = 'BS';
}

// Active page detection
$is_dashboard = ($cur_script === 'dashboard.php');
$is_profile   = in_array($cur_script, ['customer-profile-update.php', 'customer-profile.php']);
$is_wishlist  = in_array($cur_script, ['customer-wishlist.php', 'wishlist.php']);
$is_addresses = in_array($cur_script, ['customer-billing-shipping-update.php', 'customer-addresses.php', 'customer-address.php']);
$is_orders    = in_array($cur_script, ['customer-order.php', 'customer-orders.php', 'customer-returns.php']);
$is_settings  = in_array($cur_script, ['customer-password-update.php', 'customer-password.php']);
?>

<style>
@media (max-width: 991px) {
    .sn-portal-sidebar {
        display: none !important;
    }
}
</style>

<aside class="sn-portal-sidebar">
    <!-- User Profile Badge Card -->
    <div class="sn-sidebar-profile-card">
        <div class="sn-user-avatar-circle">
            <?= htmlspecialchars($initials) ?>
        </div>
        <div class="sn-user-meta">
            <h3><?= htmlspecialchars($cust_name) ?></h3>
            <p><?= htmlspecialchars($cust_email) ?></p>
            <span class="sn-verified-pill"><i class="fa-solid fa-circle-check"></i> Verified Account</span>
        </div>
    </div>

    <!-- Navigation Menu Card -->
    <div class="sn-sidebar-nav-card">
        <ul class="sn-nav-list" style="list-style: none !important; list-style-type: none !important; padding: 0 !important; margin: 0 !important;">
            <li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important;">
                <a href="dashboard.php" class="sn-nav-link <?= $is_dashboard ? 'active' : '' ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important;">
                <a href="customer-profile-update.php" class="sn-nav-link <?= $is_profile ? 'active' : '' ?>">
                    <i class="fa-regular fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important;">
                <a href="customer-wishlist.php" class="sn-nav-link <?= $is_wishlist ? 'active' : '' ?>">
                    <i class="fa-regular fa-heart"></i>
                    <span>Wishlist</span>
                </a>
            </li>
            <li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important;">
                <a href="customer-billing-shipping-update.php" class="sn-nav-link <?= $is_addresses ? 'active' : '' ?>">
                    <i class="fa-solid fa-location-dot"></i>
                    <span>Addresses</span>
                </a>
            </li>
            <li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important;">
                <a href="customer-order.php" class="sn-nav-link <?= $is_orders ? 'active' : '' ?>">
                    <i class="fa-solid fa-box"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important;">
                <a href="customer-password-update.php" class="sn-nav-link <?= $is_settings ? 'active' : '' ?>">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Need Help Support Card -->
    <div class="sn-need-help-card">
        <div class="sn-help-icon-circle">
            <i class="fa-solid fa-headset"></i>
        </div>
        <h4>Need Help?</h4>
        <p>Our support team is here for you 24/7.</p>
        <a href="contact.php" class="sn-btn-help">
            Contact Support &rarr;
        </a>
    </div>
</aside>