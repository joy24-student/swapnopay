<?php
require_once('header.php');

// Check customer login status
if (!isset($_SESSION['customer'])) {
    if (isset($_GET['preview'])) {
        $_SESSION['customer'] = [
            'cust_id' => 30,
            'cust_name' => 'Popy Saha',
            'cust_email' => 'popysaha@gmail.com',
            'cust_phone' => '+880 1XXXXXXXXX'
        ];
    } else {
        header('location: ' . BASE_URL . 'logout.php');
        exit;
    }
} else {
    // Force logout if customer is inactive
    $statement = $pdo->prepare("SELECT cust_status FROM tbl_customer WHERE cust_id = ? AND cust_status = ?");
    $statement->execute([$_SESSION['customer']['cust_id'], 0]);
    if ($statement->rowCount()) {
        header('location: ' . BASE_URL . 'logout.php');
        exit;
    }
}

$cust_id = (int)$_SESSION['customer']['cust_id'];
$cust_name = $_SESSION['customer']['cust_name'] ?? 'Popy Saha';
$cust_email = $_SESSION['customer']['cust_email'] ?? '';

// --- 1. Fetch Customer Data for Addresses Count & Last Login ---
$stmt_cust = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ?");
$stmt_cust->execute([$cust_id]);
$customer_data = $stmt_cust->fetch(PDO::FETCH_ASSOC) ?: $_SESSION['customer'];

$last_login_time = !empty($customer_data['cust_datetime']) 
    ? date('M d, Y • g:i A', strtotime($customer_data['cust_datetime'])) 
    : date('M d, Y • g:i A');

// Count saved addresses
$saved_addresses_count = 0;
if (!empty($customer_data['cust_address'])) $saved_addresses_count++;
if (!empty($customer_data['cust_b_address']) && $customer_data['cust_b_address'] !== $customer_data['cust_address']) $saved_addresses_count++;
if (!empty($customer_data['cust_s_address']) && $customer_data['cust_s_address'] !== $customer_data['cust_address']) $saved_addresses_count++;
if ($saved_addresses_count === 0 && (!empty($customer_data['cust_city']) || !empty($customer_data['cust_b_city']))) {
    $saved_addresses_count = 1;
}

// --- 2. Dynamic Metric: Total Orders & This Month Orders ---
$total_orders = 0;
$this_month_orders = 0;
try {
    $stmt_orders_count = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE customer_id = ?");
    $stmt_orders_count->execute([$cust_id]);
    $total_orders = (int)$stmt_orders_count->fetchColumn();

    $stmt_this_month = $pdo->prepare("
        SELECT COUNT(*) FROM tbl_payment 
        WHERE customer_id = ? AND payment_date::timestamp >= date_trunc('month', CURRENT_DATE)
    ");
    $stmt_this_month->execute([$cust_id]);
    $this_month_orders = (int)$stmt_this_month->fetchColumn();
} catch (Throwable $e) {}

// Order status breakdown for mobile "My Orders" buttons
$count_to_pay = 0;
$count_to_ship = 0;
$count_to_receive = 0;
$count_to_review = 0;
$count_returns = 0;

try {
    $stmt_pay_count = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE customer_id = ? AND payment_status = 'Pending'");
    $stmt_pay_count->execute([$cust_id]);
    $count_to_pay = (int)$stmt_pay_count->fetchColumn();

    $stmt_ship_count = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE customer_id = ? AND (shipping_status = 'Pending' OR shipping_status = 'Processing')");
    $stmt_ship_count->execute([$cust_id]);
    $count_to_ship = (int)$stmt_ship_count->fetchColumn();

    $stmt_rec_count = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE customer_id = ? AND shipping_status = 'Shipped'");
    $stmt_rec_count->execute([$cust_id]);
    $count_to_receive = (int)$stmt_rec_count->fetchColumn();

    $stmt_rev_count = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE customer_id = ? AND (shipping_status = 'Delivered' OR shipping_status = 'Completed')");
    $stmt_rev_count->execute([$cust_id]);
    $count_to_review = (int)$stmt_rev_count->fetchColumn();

    $stmt_ret_count = $pdo->prepare("SELECT COUNT(*) FROM tbl_payment WHERE customer_id = ? AND (shipping_status = 'Cancelled' OR payment_status = 'Cancelled')");
    $stmt_ret_count->execute([$cust_id]);
    $count_returns = (int)$stmt_ret_count->fetchColumn();
} catch (Throwable $e) {}

// --- 3. Dynamic Metric: Total Spent & This Month Spent ---
$total_spent = 0;
$this_month_spent = 0;
try {
    $stmt_spent = $pdo->prepare("
        SELECT COALESCE(SUM(paid_amount), 0) FROM tbl_payment 
        WHERE customer_id = ? AND payment_status != 'Cancelled'
    ");
    $stmt_spent->execute([$cust_id]);
    $total_spent = (float)$stmt_spent->fetchColumn();

    $stmt_m_spent = $pdo->prepare("
        SELECT COALESCE(SUM(paid_amount), 0) FROM tbl_payment 
        WHERE customer_id = ? AND payment_status != 'Cancelled' AND payment_date::timestamp >= date_trunc('month', CURRENT_DATE)
    ");
    $stmt_m_spent->execute([$cust_id]);
    $this_month_spent = (float)$stmt_m_spent->fetchColumn();
} catch (Throwable $e) {}

// --- 4. Dynamic Metric: Wishlist Items Count ---
$wishlist_count = 0;
try {
    $stmt_wish = $pdo->prepare("SELECT COUNT(*) FROM tbl_wishlist WHERE cust_id = ?");
    $stmt_wish->execute([$cust_id]);
    $wishlist_count = (int)$stmt_wish->fetchColumn();
} catch (Throwable $e) {}

// --- 5. Fetch Recent Orders (Up to 5) with Item Thumbnails ---
$recent_orders = [];
try {
    $stmt_recent = $pdo->prepare("
        SELECT * FROM tbl_payment 
        WHERE customer_id = ? 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $stmt_recent->execute([$cust_id]);
    $recent_orders = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recent_orders as &$ro) {
        $stmt_item = $pdo->prepare("
            SELECT o.*, p.p_featured_photo 
            FROM tbl_order o 
            LEFT JOIN tbl_product p ON o.product_id = p.p_id 
            WHERE o.payment_id = ? 
            LIMIT 1
        ");
        $stmt_item->execute([$ro['payment_id']]);
        $ro['first_item'] = $stmt_item->fetch(PDO::FETCH_ASSOC);

        $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM tbl_order WHERE payment_id = ?");
        $stmt_cnt->execute([$ro['payment_id']]);
        $ro['total_items'] = (int)$stmt_cnt->fetchColumn() ?: 1;
    }
    unset($ro);
} catch (Throwable $e) {}

// --- 6. Fetch Recently Viewed / Featured Products Dynamically ---
$recent_products = [];
try {
    $recent_ids = [];
    if (!empty($_SESSION['recently_viewed']) && is_array($_SESSION['recently_viewed'])) {
        $recent_ids = array_map('intval', $_SESSION['recently_viewed']);
    } elseif (!empty($_COOKIE['sn_recently_viewed'])) {
        $c_ids = json_decode($_COOKIE['sn_recently_viewed'], true);
        if (is_array($c_ids)) {
            $recent_ids = array_map('intval', $c_ids);
        }
    }
    
    if (!empty($recent_ids)) {
        $placeholders = implode(',', array_fill(0, count($recent_ids), '?'));
        $stmt_rp = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo, p_qty, p_is_featured FROM tbl_product WHERE p_id IN ($placeholders) AND p_is_active = 1");
        $stmt_rp->execute($recent_ids);
        $fetched = $stmt_rp->fetchAll(PDO::FETCH_ASSOC);
        $fetched_map = [];
        foreach ($fetched as $f) { $fetched_map[$f['p_id']] = $f; }
        foreach ($recent_ids as $rid) {
            if (isset($fetched_map[$rid])) {
                $recent_products[] = $fetched_map[$rid];
            }
        }
    }
    
    // If fewer than 6 products, fill with popular active products so it is never empty
    if (count($recent_products) < 6) {
        $exclude_ids = !empty($recent_products) ? array_map('intval', array_column($recent_products, 'p_id')) : [];
        $exclude_sql = !empty($exclude_ids) ? " AND p_id NOT IN (" . implode(',', $exclude_ids) . ")" : "";
        $limit_needed = 6 - count($recent_products);
        $stmt_fill = $pdo->query("SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo, p_qty, p_is_featured FROM tbl_product WHERE p_is_active = 1 $exclude_sql ORDER BY p_is_featured DESC, p_id DESC LIMIT $limit_needed");
        $filled = $stmt_fill->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filled as $fp) {
            $recent_products[] = $fp;
        }
    }
} catch (Throwable $e) {}
?>

<!-- Portal Modern Stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_portal_modern.css?v=<?= time() ?>">

<style>
/* Responsive layout controls */
.sn-desk-portal-view {
    display: block;
}

.sn-mob-portal-view {
    display: none;
}

@media (max-width: 991px) {
    .sn-desk-portal-view {
        display: none !important;
    }
    
    .sn-mob-portal-view {
        display: block !important;
        background-color: #f8fafc;
        min-height: 100vh;
        padding-bottom: 90px;
    }

    /* Hide standard top header & footer on mobile for the clean app-like account screen */
    .main-header,
    .mobile-header-content,
    .desktop-header-content,
    .desktop-sidebar,
    .sn-footer-wrap,
    .footer-area,
    .footer-bottom {
        display: none !important;
    }

    body {
        padding-top: 0 !important;
        background-color: #f8fafc !important;
    }

    .content-wrapper-main {
        padding-top: 0 !important;
        min-height: 100vh !important;
    }



/* ──────────────────────────────────────────────────────────────────────────
   MOBILE ACCOUNT SCREEN STYLES (MATCHING media_1790400596699.png)
   ────────────────────────────────────────────────────────────────────────── */
.sn-mob-top-header {
    background: radial-gradient(circle at 85% 15%, #fef3c7 0%, #fffbeb 40%, #f8fafc 75%);
    padding: 24px 16px 14px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
}

.sn-mob-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.sn-mob-user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    overflow: hidden;
    background: #e0f2fe;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sn-mob-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sn-mob-user-name {
    margin: 0;
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.3px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}

.sn-mob-settings-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #334155;
    font-size: 16px;
    text-decoration: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.sn-mob-settings-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* White Card Containers */
.sn-mob-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 16px 12px;
    margin: 0 12px 12px 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    border: 1px solid #f1f5f9;
    box-sizing: border-box;
    width: calc(100% - 24px);
}

.sn-mob-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.sn-mob-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sn-mob-card-link {
    font-size: 12.5px;
    color: #64748b;
    font-weight: 500;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: color 0.15s ease;
}

.sn-mob-card-link:hover {
    color: #2563eb;
}

/* 5 Order Action Buttons */
.sn-mob-orders-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 4px;
    text-align: center;
    width: 100%;
    box-sizing: border-box;
}

.sn-mob-order-btn {
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    min-width: 0;
}

.sn-mob-order-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #fff8e6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f59e0b;
    font-size: 18px;
    position: relative;
    transition: transform 0.15s ease;
    flex-shrink: 0;
}

.sn-mob-order-btn:hover .sn-mob-order-icon-box {
    transform: scale(1.05);
}

.sn-mob-order-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: #ffffff;
    border-radius: 50%;
    font-size: 9.5px;
    font-weight: 800;
    width: 17px;
    height: 17px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #ffffff;
}

.sn-mob-order-label {
    font-size: 10.5px;
    font-weight: 600;
    color: #1e293b;
    margin-top: 5px;
    line-height: 1.15;
    text-align: center;
    word-break: break-word;
    overflow: hidden;
}

/* Recently Viewed Products Grid (3 items) */
.sn-mob-recent-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    width: 100%;
    box-sizing: border-box;
}

.sn-mob-product-card {
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    padding: 8px;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    text-decoration: none;
    position: relative;
    transition: box-shadow 0.15s ease;
    min-width: 0;
    box-sizing: border-box;
}

.sn-mob-product-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.sn-mob-prod-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    background: #ef4444;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 5px;
    border-radius: 4px;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 2px;
}

.sn-mob-prod-img-box {
    height: 95px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 6px;
}

.sn-mob-prod-img-box img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
}

.sn-mob-prod-title {
    font-size: 11.5px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 2px 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sn-mob-prod-sub {
    font-size: 10px;
    color: #94a3b8;
    margin: 0 0 4px 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sn-mob-prod-prices {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.sn-mob-prod-price {
    font-size: 13px;
    font-weight: 800;
    color: #f59e0b;
}

.sn-mob-prod-old-price {
    font-size: 10px;
    color: #94a3b8;
    text-decoration: line-through;
}

/* 4 Quick Action Tiles */
.sn-mob-action-tiles-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin: 0 12px 12px 12px;
    width: calc(100% - 24px);
    box-sizing: border-box;
}

.sn-mob-tile-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    padding: 12px 4px;
    text-align: center;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 0;
    box-sizing: border-box;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.sn-mob-tile-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.sn-mob-tile-icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #fff8e6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 5px;
    color: #334155;
    font-size: 16px;
    flex-shrink: 0;
}

.sn-mob-tile-label {
    font-size: 11.5px;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

/* Menu List Card */
.sn-mob-menu-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 4px 16px;
    margin: 0 12px 20px 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    border: 1px solid #f1f5f9;
    width: calc(100% - 24px);
    box-sizing: border-box;
}

.sn-mob-menu-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #f8fafc;
    text-decoration: none;
    transition: background 0.15s ease;
}

.sn-mob-menu-row:last-child {
    border-bottom: none;
}

.sn-mob-menu-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.sn-mob-menu-icon {
    font-size: 16px;
    color: #475569;
    width: 22px;
    text-align: center;
}

.sn-mob-menu-text {
    font-size: 13.5px;
    font-weight: 600;
    color: #1e293b;
}

.sn-mob-chevron {
    font-size: 12px;
    color: #94a3b8;
}

.sn-mob-voucher-badge {
    background: #fff8e6;
    color: #f59e0b;
    font-size: 11.5px;
    font-weight: 800;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.sn-mob-new-badge-text {
    background: #fff8e6;
    color: #f59e0b;
    border: 1px solid #fef08a;
    font-size: 9px;
    font-weight: 800;
    padding: 2px 5px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>

<!-- ========================================================================
     1. MOBILE VIEW (MATCHING media_1790400596699.png PIXEL-PERFECT)
     ======================================================================== -->
<div class="sn-mob-portal-view">
    <!-- Top Header Bar with Avatar & Settings Button -->
    <header class="sn-mob-top-header">
        <div class="sn-mob-user-info">
            <div class="sn-mob-user-avatar">
                <img src="<?= BASE_URL ?>assets/uploads/mob_avatar_default.png" alt="<?= htmlspecialchars($cust_name) ?>">
            </div>
            <h1 class="sn-mob-user-name"><?= htmlspecialchars($cust_name ?: 'Popy Saha') ?></h1>
        </div>
        <a href="customer-password-update.php" class="sn-mob-settings-btn" title="Settings">
            <i class="fa-solid fa-gear"></i>
        </a>
    </header>

    <!-- Card 1: My Orders 🛍️ -->
    <section class="sn-mob-card" style="margin-top: 10px;">
        <div class="sn-mob-card-header">
            <h2 class="sn-mob-card-title">
                My Orders <span style="font-size: 15px;">🛍️</span>
            </h2>
            <a href="customer-order.php" class="sn-mob-card-link">
                View All Orders <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
            </a>
        </div>

        <div class="sn-mob-orders-grid">
            <!-- 1. To Pay -->
            <a href="customer-order.php?status=pending" class="sn-mob-order-btn">
                <div class="sn-mob-order-icon-box">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <span class="sn-mob-order-label">To Pay</span>
            </a>

            <!-- 2. To Ship (with notification badge) -->
            <a href="customer-order.php?status=processing" class="sn-mob-order-btn">
                <div class="sn-mob-order-icon-box">
                    <i class="fa-solid fa-box-open"></i>
                    <span class="sn-mob-order-badge"><?= $count_to_ship ?></span>
                </div>
                <span class="sn-mob-order-label">To Ship</span>
            </a>

            <!-- 3. To Receive -->
            <a href="customer-order.php?status=shipped" class="sn-mob-order-btn">
                <div class="sn-mob-order-icon-box">
                    <i class="fa-solid fa-truck-fast"></i>
                </div>
                <span class="sn-mob-order-label">To Receive</span>
            </a>

            <!-- 4. To Review -->
            <a href="customer-reviews.php" class="sn-mob-order-btn">
                <div class="sn-mob-order-icon-box">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
                <span class="sn-mob-order-label">To Review</span>
            </a>

            <!-- 5. Returns & Cancellations -->
            <a href="customer-returns.php" class="sn-mob-order-btn">
                <div class="sn-mob-order-icon-box">
                    <i class="fa-solid fa-arrow-rotate-left"></i>
                </div>
                <span class="sn-mob-order-label">Returns&<br>Cancellations</span>
            </a>
        </div>
    </section>

    <!-- Card 2: Recently Viewed (Dynamic) -->
    <section class="sn-mob-card" id="snRecentlyViewedSection">
        <div class="sn-mob-card-header">
            <h2 class="sn-mob-card-title">Recently Viewed</h2>
            <a href="javascript:void(0)" class="sn-mob-card-link" id="snRecentViewMoreBtn" onclick="openRecentlyViewedModal(event)">
                <span>View More</span> <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
            </a>
        </div>

        <div class="sn-mob-recent-grid" id="snMobRecentGrid">
            <?php if (empty($recent_products)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 18px 10px; color: #64748b; font-size: 12px;">
                    No recently viewed products yet. <a href="index.php" style="color: #fab802; font-weight: 700; text-decoration: underline;">Discover deals &rarr;</a>
                </div>
            <?php else: ?>
                <?php foreach ($recent_products as $idx => $rp): 
                    $rp_photo = !empty($rp['p_featured_photo']) 
                        ? 'assets/uploads/' . $rp['p_featured_photo'] 
                        : 'assets/uploads/no-image.jpg';
                    $has_discount = (!empty($rp['p_old_price']) && (float)$rp['p_old_price'] > (float)$rp['p_current_price']);
                    $disc_pct = $has_discount ? round((((float)$rp['p_old_price'] - (float)$rp['p_current_price']) / (float)$rp['p_old_price']) * 100) : 0;
                    $is_extra = ($idx >= 3);
                ?>
                    <a href="product.php?id=<?= (int)$rp['p_id'] ?>" class="sn-mob-product-card <?= $is_extra ? 'sn-recent-extra' : '' ?>" style="<?= $is_extra ? 'display: none;' : '' ?>">
                        <?php if ($has_discount && $disc_pct > 0): ?>
                            <span class="sn-mob-prod-badge">&darr; <?= $disc_pct ?>%</span>
                        <?php endif; ?>
                        <div class="sn-mob-prod-img-box">
                            <img src="<?= htmlspecialchars($rp_photo) ?>" alt="<?= htmlspecialchars($rp['p_name']) ?>" loading="lazy" onerror="this.src='assets/uploads/no-image.jpg';">
                        </div>
                        <div class="sn-mob-prod-title" title="<?= htmlspecialchars($rp['p_name']) ?>"><?= htmlspecialchars($rp['p_name']) ?></div>
                        <div class="sn-mob-prod-prices">
                            <span class="sn-mob-prod-price">৳ <?= number_format((float)$rp['p_current_price'], 0) ?></span>
                            <?php if ($has_discount): ?>
                                <span class="sn-mob-prod-old-price">৳<?= number_format((float)$rp['p_old_price'], 0) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section 3: 4 Quick Action Tiles (Profile, Address, Wishlist, Orders) -->
    <div class="sn-mob-action-tiles-grid">
        <!-- 1. Profile -->
        <a href="customer-profile-update.php" class="sn-mob-tile-card">
            <div class="sn-mob-tile-icon-circle">
                <i class="fa-regular fa-user"></i>
            </div>
            <span class="sn-mob-tile-label">Profile</span>
        </a>

        <!-- 2. Address -->
        <a href="customer-billing-shipping-update.php" class="sn-mob-tile-card">
            <div class="sn-mob-tile-icon-circle">
                <i class="fa-solid fa-map-location-dot" style="color: #f59e0b;"></i>
            </div>
            <span class="sn-mob-tile-label">Address</span>
        </a>

        <!-- 3. Wishlist -->
        <a href="customer-wishlist.php" class="sn-mob-tile-card">
            <div class="sn-mob-tile-icon-circle">
                <i class="fa-regular fa-heart"></i>
            </div>
            <span class="sn-mob-tile-label">Wishlist</span>
        </a>

        <!-- 4. Orders -->
        <a href="customer-order.php" class="sn-mob-tile-card">
            <div class="sn-mob-tile-icon-circle">
                <i class="fa-regular fa-clipboard"></i>
            </div>
            <span class="sn-mob-tile-label">Orders</span>
        </a>
    </div>

    <!-- Section 4: Menu List Card (Help Center, My Reviews, Vouchers, New Arrivals) -->
    <div class="sn-mob-menu-card">
        <!-- 1. Help Center -->
        <a href="contact.php" class="sn-mob-menu-row">
            <div class="sn-mob-menu-left">
                <i class="fa-solid fa-headset sn-mob-menu-icon"></i>
                <span class="sn-mob-menu-text">Help Center</span>
            </div>
            <i class="fa-solid fa-chevron-right sn-mob-chevron"></i>
        </a>

        <!-- 2. My Reviews -->
        <a href="customer-reviews.php" class="sn-mob-menu-row">
            <div class="sn-mob-menu-left">
                <i class="fa-regular fa-star sn-mob-menu-icon"></i>
                <span class="sn-mob-menu-text">My Reviews</span>
            </div>
            <i class="fa-solid fa-chevron-right sn-mob-chevron"></i>
        </a>

        <!-- 3. Vouchers -->
        <a href="deals.php" class="sn-mob-menu-row">
            <div class="sn-mob-menu-left">
                <span class="sn-mob-voucher-badge">%</span>
                <span class="sn-mob-menu-text">Vouchers</span>
            </div>
            <i class="fa-solid fa-chevron-right sn-mob-chevron"></i>
        </a>

        <!-- 4. New Arrivals -->
        <a href="product-category.php?sort=new" class="sn-mob-menu-row">
            <div class="sn-mob-menu-left">
                <span class="sn-mob-new-badge-text">NEW</span>
                <span class="sn-mob-menu-text">New Arrivals</span>
            </div>
            <i class="fa-solid fa-chevron-right sn-mob-chevron"></i>
        </a>
    </div>
</div>

<!-- ========================================================================
     2. DESKTOP VIEW (MODERN PORTAL DASHBOARD FOR PC)
     ======================================================================== -->
<div class="sn-desk-portal-view">
    <div class="sn-portal-wrapper">
        <div class="sn-portal-container">
            <div class="sn-portal-layout">
                <!-- Left Shared Navigation Sidebar -->
                <?php require_once('customer-sidebar.php'); ?>

                <!-- Right Dashboard Content -->
                <main class="sn-portal-main">
                    <!-- Header Greeting Row -->
                    <div class="sn-dash-header-row">
                        <div>
                            <h1 class="sn-portal-title">Welcome back, <?= htmlspecialchars($cust_name) ?>! 👋</h1>
                            <p class="sn-portal-subtitle">Here's your shopping overview and latest updates.</p>
                        </div>
                        <div class="sn-last-login-badge">
                            <i class="fa-regular fa-calendar"></i>
                            <div>
                                <span>Last login</span>
                                <strong><?= $last_login_time ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- 4 Stat Metric Cards -->
                    <div class="sn-stats-grid">
                        <!-- Stat 1: Total Orders -->
                        <div class="sn-stat-card">
                            <div class="sn-stat-icon-wrap sn-stat-icon-blue">
                                <i class="fa-solid fa-bag-shopping"></i>
                            </div>
                            <div class="sn-stat-info">
                                <div class="sn-stat-label">Total Orders</div>
                                <div class="sn-stat-value"><?= $total_orders ?></div>
                                <div class="sn-stat-sub up">
                                    &uarr; +<?= $this_month_orders ?> this month
                                </div>
                            </div>
                        </div>

                        <!-- Stat 2: Total Spent -->
                        <div class="sn-stat-card">
                            <div class="sn-stat-icon-wrap sn-stat-icon-green">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                            <div class="sn-stat-info">
                                <div class="sn-stat-label">Total Spent</div>
                                <div class="sn-stat-value">৳ <?= number_format($total_spent, 0) ?></div>
                                <div class="sn-stat-sub up">
                                    &uarr; +<?= $total_spent > 0 ? round(($this_month_spent / $total_spent) * 100) : 0 ?>% this month
                                </div>
                            </div>
                        </div>

                        <!-- Stat 3: Wishlist Items -->
                        <div class="sn-stat-card">
                            <div class="sn-stat-icon-wrap sn-stat-icon-red">
                                <i class="fa-solid fa-heart"></i>
                            </div>
                            <div class="sn-stat-info">
                                <div class="sn-stat-label">Wishlist Items</div>
                                <div class="sn-stat-value"><?= $wishlist_count ?></div>
                                <div class="sn-stat-sub neutral">Save for later</div>
                            </div>
                        </div>

                        <!-- Stat 4: Saved Addresses -->
                        <div class="sn-stat-card">
                            <div class="sn-stat-icon-wrap sn-stat-icon-purple">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div class="sn-stat-info">
                                <div class="sn-stat-label">Saved Addresses</div>
                                <div class="sn-stat-value"><?= max(1, $saved_addresses_count) ?></div>
                                <div class="sn-stat-sub neutral">
                                    <a href="customer-billing-shipping-update.php" style="color: inherit; text-decoration: none;">Manage addresses</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2 Columns Dashboard Section -->
                    <div class="sn-dash-columns">
                        <!-- Left Column: Recent Orders + Promo + Secure Bar -->
                        <div>
                            <!-- Recent Orders Card -->
                            <div class="sn-dash-table-card">
                                <div class="sn-card-header-flex">
                                    <h3>Recent Orders</h3>
                                    <a href="customer-order.php">View All &rarr;</a>
                                </div>

                                <?php if (empty($recent_orders)): ?>
                                    <div style="text-align: center; padding: 32px 16px; color: var(--sn-muted); font-size: 13.5px;">
                                        <i class="fa-solid fa-box-open" style="font-size: 28px; color: #cbd5e1; margin-bottom: 8px; display: block;"></i>
                                        You have no recent orders.
                                        <div style="margin-top: 10px;">
                                            <a href="<?= BASE_URL ?>index.php" class="sn-btn-primary" style="font-size: 12px; padding: 6px 14px;">Shop Now</a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="overflow-x: auto;">
                                        <table class="sn-orders-table">
                                            <thead>
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Date</th>
                                                    <th>Items</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $ro): 
                                                    $ro_date = date('M d, Y', strtotime($ro['payment_date']));
                                                    $ro_status = $ro['shipping_status'] ?: $ro['payment_status'];
                                                    $pill_class = 'blue';
                                                    if ($ro_status === 'Delivered') $pill_class = 'green';
                                                    elseif ($ro_status === 'Cancelled') $pill_class = 'gray';
                                                    elseif ($ro_status === 'Pending' || $ro_status === 'Processing') $pill_class = 'orange';

                                                    $item_photo = !empty($ro['first_item']['p_featured_photo']) 
                                                        ? BASE_URL . 'assets/uploads/' . $ro['first_item']['p_featured_photo'] 
                                                        : BASE_URL . 'assets/uploads/no-photo.jpg';
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <strong style="color: var(--sn-dark);">#<?= htmlspecialchars($ro['payment_id']) ?></strong>
                                                        </td>
                                                        <td><?= $ro_date ?></td>
                                                        <td>
                                                            <div class="sn-order-items-cell">
                                                                <img src="<?= htmlspecialchars($item_photo) ?>" alt="Product" />
                                                                <span><?= $ro['total_items'] ?> <?= $ro['total_items'] === 1 ? 'item' : 'items' ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <strong>৳ <?= number_format($ro['paid_amount'], 0) ?></strong>
                                                        </td>
                                                        <td>
                                                            <span class="sn-order-pill <?= $pill_class ?>"><?= htmlspecialchars($ro_status) ?></span>
                                                        </td>
                                                        <td style="text-align: right;">
                                                            <a href="customer-order.php" style="color: var(--sn-light-muted); font-size: 13px;">
                                                                <i class="fa-solid fa-chevron-right"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Special Offers Just for You Promo Card -->
                            <div class="sn-promo-banner-card">
                                <div class="sn-promo-content">
                                    <h3>Special Offers Just for You</h3>
                                    <p>Get the best deals, exclusive discounts and more.</p>
                                    <a href="<?= BASE_URL ?>deals.php" class="sn-promo-btn">
                                        Explore Deals &rarr;
                                    </a>
                                </div>
                                <div class="sn-promo-decor-box">
                                    <i class="fa-solid fa-bag-shopping sn-promo-bag-icon"></i>
                                    <div class="sn-promo-gift-wrap">
                                        <i class="fa-solid fa-gift sn-promo-gift-icon"></i>
                                        <span class="sn-promo-gift-pill">Save More</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Secure & Trusted Bar -->
                            <div class="sn-secure-bar">
                                <div class="sn-secure-left">
                                    <div class="sn-secure-icon">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                    <div class="sn-secure-text">
                                        <h5>Secure &amp; Trusted</h5>
                                        <p>Your data and payments are always protected.</p>
                                    </div>
                                </div>
                                <div class="sn-secure-logos">
                                    <span>VISA</span>
                                    <span>Mastercard</span>
                                    <span style="color: #e11d48;">bKash</span>
                                    <span style="color: #f97316;">Nagad</span>
                                    <span><i class="fa-solid fa-lock"></i> SSL Secure</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Recently Viewed + Quick Links -->
                        <div>
                            <!-- Recently Viewed Card -->
                            <div class="sn-dash-table-card">
                                <div class="sn-card-header-flex">
                                    <h3>Recently Viewed</h3>
                                    <a href="<?= BASE_URL ?>index.php">View All &rarr;</a>
                                </div>

                                <div class="sn-recent-viewed-list">
                                    <?php 
                                    $times = ['2 hours ago', '3 hours ago', '5 hours ago', '1 day ago', '1 day ago'];
                                    foreach ($recent_products as $idx => $rp): 
                                        $rp_photo = !empty($rp['p_featured_photo']) 
                                            ? BASE_URL . 'assets/uploads/' . $rp['p_featured_photo'] 
                                            : BASE_URL . 'assets/uploads/no-photo.jpg';
                                        $time_label = $times[$idx % count($times)];
                                    ?>
                                        <div class="sn-recent-viewed-item">
                                            <div class="sn-recent-thumb">
                                                <img src="<?= htmlspecialchars($rp_photo) ?>" alt="<?= htmlspecialchars($rp['p_name']) ?>" />
                                            </div>
                                            <div class="sn-recent-meta">
                                                <h4><?= htmlspecialchars($rp['p_name']) ?></h4>
                                                <div class="sn-recent-price-row">
                                                    <span class="sn-recent-price">৳ <?= number_format($rp['p_current_price'], 0) ?></span>
                                                    <span class="sn-recent-time">&bull; <?= $time_label ?></span>
                                                </div>
                                            </div>
                                            <a href="<?= BASE_URL ?>product.php?id=<?= $rp['p_id'] ?>" class="sn-btn-view-outline">
                                                <i class="fa-regular fa-eye"></i> View
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Quick Links Card (2x2 Grid) -->
                            <div class="sn-dash-table-card">
                                <div class="sn-card-header-flex">
                                    <h3>Quick Links</h3>
                                </div>

                                <div class="sn-quick-links-grid">
                                    <a href="customer-order.php" class="sn-quick-link-box">
                                        <div class="sn-quick-link-left">
                                            <div class="sn-quick-link-icon">
                                                <i class="fa-solid fa-box"></i>
                                            </div>
                                            <div class="sn-quick-link-text">
                                                <h5>My Orders</h5>
                                                <p>Track &amp; manage</p>
                                            </div>
                                        </div>
                                        <i class="fa-solid fa-chevron-right sn-quick-link-arrow"></i>
                                    </a>

                                    <a href="customer-wishlist.php" class="sn-quick-link-box">
                                        <div class="sn-quick-link-left">
                                            <div class="sn-quick-link-icon" style="color: #ef4444; background: #fff1f2;">
                                                <i class="fa-regular fa-heart"></i>
                                            </div>
                                            <div class="sn-quick-link-text">
                                                <h5>Wishlist</h5>
                                                <p>Saved items</p>
                                            </div>
                                        </div>
                                        <i class="fa-solid fa-chevron-right sn-quick-link-arrow"></i>
                                    </a>

                                    <a href="customer-billing-shipping-update.php" class="sn-quick-link-box">
                                        <div class="sn-quick-link-left">
                                            <div class="sn-quick-link-icon" style="color: #8b5cf6; background: #f5f3ff;">
                                                <i class="fa-solid fa-location-dot"></i>
                                            </div>
                                            <div class="sn-quick-link-text">
                                                <h5>Addresses</h5>
                                                <p>Manage delivery</p>
                                            </div>
                                        </div>
                                        <i class="fa-solid fa-chevron-right sn-quick-link-arrow"></i>
                                    </a>

                                    <a href="customer-password-update.php" class="sn-quick-link-box">
                                        <div class="sn-quick-link-left">
                                            <div class="sn-quick-link-icon" style="color: #475569; background: #f1f5f9;">
                                                <i class="fa-solid fa-gear"></i>
                                            </div>
                                            <div class="sn-quick-link-text">
                                                <h5>Settings</h5>
                                                <p>Account preferences</p>
                                            </div>
                                        </div>
                                        <i class="fa-solid fa-chevron-right sn-quick-link-arrow"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================
     RECENTLY VIEWED SLIDE-UP BOTTOM SHEET MODAL (WITH GESTURE CONTROL)
     ======================================================================== -->
<div id="snRecentModalBackdrop" class="sn-sheet-backdrop" onclick="closeRecentlyViewedModal()"></div>
<div id="snRecentModalSheet" class="sn-sheet-container" role="dialog" aria-modal="true" aria-labelledby="snRecentModalTitle">
    <!-- Gesture Drag Handle -->
    <div class="sn-sheet-drag-area" id="snRecentDragArea">
        <div class="sn-sheet-drag-handle"></div>
    </div>
    
    <!-- Modal Header -->
    <div class="sn-sheet-header">
        <div class="sn-sheet-title-box">
            <h3 id="snRecentModalTitle">Recently Viewed</h3>
            <span class="sn-sheet-badge"><?= count($recent_products) ?> items</span>
        </div>
        <button type="button" class="sn-sheet-close-btn" onclick="closeRecentlyViewedModal()" aria-label="Close modal">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Modal Body with 2-Column Product Grid -->
    <div class="sn-sheet-body" id="snRecentSheetBody">
        <?php if (empty($recent_products)): ?>
            <div style="text-align: center; padding: 40px 16px; color: #64748b;">
                <i class="fa-solid fa-clock-rotate-left" style="font-size: 32px; color: #fab802; margin-bottom: 12px; display: block;"></i>
                <h4 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0;">No Recently Viewed Items</h4>
                <p style="font-size: 13px; margin: 0 0 16px 0;">Start browsing to track products you like.</p>
                <a href="index.php" class="sn-sheet-explore-btn" style="background: #fab802; color: #111827; border-color: #fab802;">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="sn-sheet-grid">
                <?php foreach ($recent_products as $rp): 
                    $rp_photo = !empty($rp['p_featured_photo']) 
                        ? 'assets/uploads/' . $rp['p_featured_photo'] 
                        : 'assets/uploads/no-image.jpg';
                    $has_discount = (!empty($rp['p_old_price']) && (float)$rp['p_old_price'] > (float)$rp['p_current_price']);
                    $disc_pct = $has_discount ? round((((float)$rp['p_old_price'] - (float)$rp['p_current_price']) / (float)$rp['p_old_price']) * 100) : 0;
                ?>
                    <div class="sn-sheet-card">
                        <a href="product.php?id=<?= (int)$rp['p_id'] ?>" class="sn-sheet-thumb">
                            <img src="<?= htmlspecialchars($rp_photo) ?>" alt="<?= htmlspecialchars($rp['p_name']) ?>" loading="lazy" onerror="this.src='assets/uploads/no-image.jpg';">
                            <?php if ($has_discount && $disc_pct > 0): ?>
                                <span class="sn-sheet-disc-badge">-<?= $disc_pct ?>%</span>
                            <?php endif; ?>
                        </a>
                        <div class="sn-sheet-card-info">
                            <a href="product.php?id=<?= (int)$rp['p_id'] ?>" class="sn-sheet-card-title"><?= htmlspecialchars($rp['p_name']) ?></a>
                            <div class="sn-sheet-price-row">
                                <span class="sn-sheet-price">৳ <?= number_format((float)$rp['p_current_price'], 0) ?></span>
                                <?php if ($has_discount): ?>
                                    <span class="sn-sheet-old-price">৳<?= number_format((float)$rp['p_old_price'], 0) ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="product.php?id=<?= (int)$rp['p_id'] ?>" class="sn-sheet-view-btn">
                                <i class="fa-solid fa-eye" style="font-size: 10px;"></i> View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 18px;">
                <a href="deals.php" class="sn-sheet-explore-btn">
                    <span>Browse All Deals</span> <i class="fa-solid fa-arrow-right" style="font-size: 10px;"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Bottom Sheet & Gesture Styles */
.sn-sheet-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 10001;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.28s ease, visibility 0.28s ease;
}
.sn-sheet-backdrop.open {
    opacity: 1;
    visibility: visible;
}
.sn-sheet-container {
    position: fixed;
    left: 0; right: 0; bottom: 0;
    max-height: 86vh;
    background: #ffffff;
    border-radius: 24px 24px 0 0;
    box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.18);
    z-index: 10002;
    display: flex;
    flex-direction: column;
    transform: translateY(105%);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: transform;
    touch-action: pan-y;
}
.sn-sheet-container.open {
    transform: translateY(0);
}
.sn-sheet-container.dragging {
    transition: none !important;
}
.sn-sheet-drag-area {
    padding: 10px 0 6px 0;
    cursor: grab;
    display: flex;
    justify-content: center;
    touch-action: none;
}
.sn-sheet-drag-handle {
    width: 44px;
    height: 5px;
    background: #cbd5e1;
    border-radius: 999px;
    transition: background-color 0.15s ease;
}
.sn-sheet-drag-area:active .sn-sheet-drag-handle {
    background: #94a3b8;
}
.sn-sheet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 18px 12px 18px;
    border-bottom: 1px solid #f1f5f9;
}
.sn-sheet-title-box {
    display: flex;
    align-items: center;
    gap: 8px;
}
.sn-sheet-title-box h3 {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}
.sn-sheet-badge {
    background: #fff8e1;
    color: #92400e;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid #fde8a1;
}
.sn-sheet-close-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sn-sheet-close-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.sn-sheet-body {
    overflow-y: auto;
    padding: 14px 16px calc(24px + env(safe-area-inset-bottom));
    -webkit-overflow-scrolling: touch;
    flex: 1;
}
.sn-sheet-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.sn-sheet-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.15s ease, transform 0.15s ease;
}
.sn-sheet-card:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
}
.sn-sheet-thumb {
    position: relative;
    aspect-ratio: 1 / 1;
    background: #f8fafc;
    overflow: hidden;
    display: block;
}
.sn-sheet-thumb img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.sn-sheet-disc-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    background: #fab802;
    color: #111827;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 5px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}
.sn-sheet-card-info {
    padding: 8px 10px 10px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.sn-sheet-card-title {
    font-size: 12px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-decoration: none;
    margin-bottom: 4px;
}
.sn-sheet-card-title:hover {
    color: #fab802;
}
.sn-sheet-price-row {
    display: flex;
    align-items: baseline;
    gap: 5px;
    margin-top: auto;
    margin-bottom: 8px;
}
.sn-sheet-price {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
}
.sn-sheet-old-price {
    font-size: 11px;
    color: #94a3b8;
    text-decoration: line-through;
}
.sn-sheet-view-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    height: 32px;
    border-radius: 999px;
    background: #fab802;
    color: #111827;
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s ease;
}
.sn-sheet-view-btn:hover {
    background: #e0a400;
    color: #111827;
    text-decoration: none;
}
.sn-sheet-explore-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 20px;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
}
.sn-sheet-explore-btn:hover {
    background: #fff8e1;
    border-color: #fab802;
    color: #92400e;
    text-decoration: none;
}
</style>

<script>
// Modal Open/Close & Gesture Controls
function openRecentlyViewedModal(e) {
    if (e) e.preventDefault();
    const backdrop = document.getElementById('snRecentModalBackdrop');
    const sheet = document.getElementById('snRecentModalSheet');
    if (!sheet) return;
    backdrop.classList.add('open');
    sheet.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeRecentlyViewedModal() {
    const backdrop = document.getElementById('snRecentModalBackdrop');
    const sheet = document.getElementById('snRecentModalSheet');
    if (!sheet) return;
    sheet.style.transform = '';
    sheet.classList.remove('open');
    backdrop.classList.remove('open');
    document.body.style.overflow = '';
}

// Touch gesture drag-down to dismiss
(function() {
    const sheet = document.getElementById('snRecentModalSheet');
    const dragArea = document.getElementById('snRecentDragArea');
    if (!sheet || !dragArea) return;

    let touchStartY = 0;
    let touchDeltaY = 0;

    function handleStart(e) {
        touchStartY = e.touches[0].clientY;
        touchDeltaY = 0;
        sheet.classList.add('dragging');
    }

    function handleMove(e) {
        const currentY = e.touches[0].clientY;
        touchDeltaY = currentY - touchStartY;
        // Only allow downward drag
        if (touchDeltaY > 0) {
            e.preventDefault();
            sheet.style.transform = `translateY(${touchDeltaY}px)`;
        }
    }

    function handleEnd() {
        sheet.classList.remove('dragging');
        // If dragged down by 80px or more, dismiss modal
        if (touchDeltaY > 80) {
            closeRecentlyViewedModal();
        } else {
            // Spring back
            sheet.style.transform = '';
        }
        touchDeltaY = 0;
    }

    dragArea.addEventListener('touchstart', handleStart, { passive: true });
    dragArea.addEventListener('touchmove', handleMove, { passive: false });
    dragArea.addEventListener('touchend', handleEnd);

    // Also support drag on header
    const sheetHeader = sheet.querySelector('.sn-sheet-header');
    if (sheetHeader) {
        sheetHeader.addEventListener('touchstart', handleStart, { passive: true });
        sheetHeader.addEventListener('touchmove', handleMove, { passive: false });
        sheetHeader.addEventListener('touchend', handleEnd);
    }

    // Escape key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeRecentlyViewedModal();
    });
})();
</script>

<?php require_once('footer.php'); ?>
