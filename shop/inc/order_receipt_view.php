<?php
/**
 * Pixel-Perfect Order Receipt & Invoice Template
 * Matches media_1792345620247_243409dd.jpg exactly.
 * Single-Page Landscape Format Print Engine.
 * Fully Dynamic Courier & Shipping Information (Zero Fake Data).
 */

if (!isset($order) || empty($order)) {
    return;
}

// 1. Data Normalization & Store Identity
$shopSettings = $settings ?? [];
$shopLogo = $shopSettings['logo'] ?? '';
$shopName = !empty($shopSettings['store_name']) ? $shopSettings['store_name'] : (!empty($shopSettings['meta_title_home']) ? $shopSettings['meta_title_home'] : (defined('STORE_NAME') ? STORE_NAME : 'Store'));
$contactEmail = !empty($shopSettings['contact_email']) ? $shopSettings['contact_email'] : ('support@' . ($_SERVER['HTTP_HOST'] ?? 'store.com'));
$contactPhone = !empty($shopSettings['contact_phone']) ? $shopSettings['contact_phone'] : '+880 1700-000000';
$contactAddress = !empty($shopSettings['contact_address']) ? $shopSettings['contact_address'] : 'Dhaka, Bangladesh';

$websiteHost = $_SERVER['HTTP_HOST'] ?? 'store';
$baseUrlResolved = defined('BASE_URL') ? BASE_URL : (rtrim('https://' . $websiteHost, '/') . '/');

// Logo path resolution
$logoUrl = '';
if (!empty($shopLogo)) {
    if (str_starts_with($shopLogo, 'http://') || str_starts_with($shopLogo, 'https://')) {
        $logoUrl = $shopLogo;
    } else {
        $cleanLogo = basename($shopLogo);
        $logoUrl = $baseUrlResolved . 'assets/uploads/' . $cleanLogo;
    }
}

// Order & Payment Reference
$paymentId = (string)($order['payment_id'] ?? ($order['id'] ?? 'SWP-' . date('Ymd') . '-0001'));
$orderNumber = !empty($order['txnid']) && str_starts_with($order['txnid'], 'SWP-') ? $order['txnid'] : ('#' . (str_starts_with($paymentId, '#') ? substr($paymentId, 1) : $paymentId));
if (!str_starts_with($orderNumber, '#')) {
    $orderNumber = '#' . $orderNumber;
}

// Order Dates
$rawDate = $order['payment_date'] ?? ($order['created_at'] ?? date('Y-m-d H:i:s'));
$orderTs = strtotime($rawDate);
if (!$orderTs || $orderTs < 100000) {
    $orderTs = time();
}
$formattedOrderDate = date('d M Y, h:i A', $orderTs);
$formattedPaidDate  = date('d M Y, h:i A', $orderTs);

// Dynamic Estimated Delivery Range (order date + 2 days to + 5 days)
if (!empty($order['estimated_delivery'])) {
    $estDelivery = trim($order['estimated_delivery']);
} else {
    $estStart = date('d M Y', strtotime('+2 days', $orderTs));
    $estEnd   = date('d M Y', strtotime('+5 days', $orderTs));
    $estDelivery = $estStart . ' – ' . $estEnd;
}

// Status Badges
$payStatusRaw = strtolower(trim((string)($order['payment_status'] ?? 'pending')));
$shipStatusRaw = strtolower(trim((string)($order['shipping_status'] ?? 'processing')));

$isPaid = ($payStatusRaw === 'completed' || $payStatusRaw === 'paid');
$isCancelled = ($payStatusRaw === 'cancelled' || $shipStatusRaw === 'cancelled');

$payStatusLabel = $isPaid ? 'Paid' : ($isCancelled ? 'Cancelled' : 'Pending');
$payStatusBadgeClass = $isPaid ? 'sn-pill-green' : ($isCancelled ? 'sn-pill-red' : 'sn-pill-yellow');

$shipStatusLabel = 'Processing';
$shipStatusBadgeClass = 'sn-pill-blue';
if ($shipStatusRaw === 'delivered' || $shipStatusRaw === 'completed') {
    $shipStatusLabel = 'Delivered';
    $shipStatusBadgeClass = 'sn-pill-green';
} elseif ($shipStatusRaw === 'shipped' || $shipStatusRaw === 'out for delivery' || $shipStatusRaw === 'out_for_delivery') {
    $shipStatusLabel = 'Shipped';
    $shipStatusBadgeClass = 'sn-pill-indigo';
} elseif ($isCancelled) {
    $shipStatusLabel = 'Cancelled';
    $shipStatusBadgeClass = 'sn-pill-red';
}

// Customer & Addresses
$custName = (string)($order['customer_name'] ?? ($order['billing_name'] ?? ($customer['cust_name'] ?? 'Customer')));
$custEmail = (string)($order['customer_email'] ?? ($order['billing_email'] ?? ($customer['cust_email'] ?? '')));
$custPhone = (string)($order['billing_phone'] ?? ($order['shipping_phone'] ?? ($customer['cust_phone'] ?? '')));

$billStreet = (string)($order['billing_street'] ?? ($order['billing_address'] ?? ($order['shipping_street'] ?? '')));
$billCity = (string)($order['billing_city'] ?? '');
$billZip = (string)($order['billing_zip'] ?? '');
$billCountry = (string)($order['billing_country'] ?? 'Bangladesh');

$shipName = (string)($order['shipping_name'] ?? $custName);
$shipStreet = (string)($order['shipping_street'] ?? ($order['shipping_address'] ?? $billStreet));
$shipCity = (string)($order['shipping_city'] ?? $billCity);
$shipZip = (string)($order['shipping_zip'] ?? $billZip);
$shipCountry = (string)($order['shipping_country'] ?? $billCountry);
$shipPhone = (string)($order['shipping_phone'] ?? $custPhone);

// Payment details
$paymentMethod = (string)($order['payment_method'] ?? 'bKash');
$txnid = (string)($order['txnid'] ?? $paymentId);
if (empty($txnid) || str_starts_with($txnid, 'SWP-') || $txnid === $paymentId) {
    $displayTxnId = !empty($order['bank_transaction_info']) ? $order['bank_transaction_info'] : 'N/A';
} else {
    $displayTxnId = $txnid;
}

// Calculations
$subtotal = 0;
$itemsList = $items ?? ($orders ?? []);
foreach ($itemsList as $it) {
    $subtotal += (float)$it['unit_price'] * (int)$it['quantity'];
}
$shippingCost = isset($order['shipping_cost']) ? (float)$order['shipping_cost'] : 0.0;
$couponDiscount = isset($order['coupon_discount']) ? (float)$order['coupon_discount'] : 0.0;
$totalAmount = isset($order['paid_amount']) && (float)$order['paid_amount'] > 0 
    ? (float)$order['paid_amount'] 
    : max(0, ($subtotal + $shippingCost - $couponDiscount));

// DYNAMIC Shipping Partner & Tracking (Zero Hardcoding / Zero Fake Hash)
$shippingPartner = '';
if (!empty($order['shipping_partner'])) {
    $shippingPartner = trim($order['shipping_partner']);
} elseif (!empty($order['courier_name'])) {
    $shippingPartner = trim($order['courier_name']);
} elseif (!empty($order['courier'])) {
    $shippingPartner = trim($order['courier']);
} elseif (!empty($shopSettings['shipping_partner'])) {
    $shippingPartner = trim($shopSettings['shipping_partner']);
} elseif (!empty($shopSettings['default_courier'])) {
    $shippingPartner = trim($shopSettings['default_courier']);
}
if (empty($shippingPartner)) {
    $shippingPartner = 'Standard Delivery';
}

$trackingNumber = '';
if (!empty($order['tracking_number'])) {
    $trackingNumber = trim($order['tracking_number']);
} elseif (!empty($order['courier_consignment_id'])) {
    $trackingNumber = trim($order['courier_consignment_id']);
} elseif (!empty($order['consignment_id'])) {
    $trackingNumber = trim($order['consignment_id']);
} elseif (!empty($order['tracking_id'])) {
    $trackingNumber = trim($order['tracking_id']);
}
if (empty($trackingNumber)) {
    $trackingNumber = 'Pending Assignment';
}

// QR Code URL (points to live order tracking & invoice verification)
$verifyUrl = $baseUrlResolved . 'invoice.php?payment_id=' . rawurlencode($paymentId);
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&data=' . urlencode($verifyUrl);

// Payment method brand renderer (uses official high-resolution brand logos, never text)
function renderPaymentBrandLogo($method) {
    global $baseUrlResolved;
    $m = strtolower(trim((string)$method));
    $base = !empty($baseUrlResolved) ? rtrim($baseUrlResolved, '/') . '/' : (defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/');

    $bkashImg  = $base . 'assets/logos/bkash.png';
    $nagadImg  = $base . 'assets/logos/nagad.png';
    $rocketImg = $base . 'assets/logos/rocket.png';
    $upayImg   = $base . 'assets/logos/upay.png';

    if (str_contains($m, 'bkash')) {
        return '<div class="sn-pm-header-logo-wrap sn-pm-header-bkash" title="bKash Official Gateway">
            <img src="' . htmlspecialchars($bkashImg, ENT_QUOTES, 'UTF-8') . '" 
                 alt="bKash" 
                 class="sn-pm-brand-img"
                 onerror="this.onerror=null; this.src=\'' . htmlspecialchars($base . 'assets/logos/bkash.svg', ENT_QUOTES, 'UTF-8') . '\';">
        </div>';
    } elseif (str_contains($m, 'nagad')) {
        return '<div class="sn-pm-header-logo-wrap sn-pm-header-nagad" title="Nagad Official Gateway">
            <img src="' . htmlspecialchars($nagadImg, ENT_QUOTES, 'UTF-8') . '" 
                 alt="Nagad" 
                 class="sn-pm-brand-img"
                 onerror="this.onerror=null; this.src=\'' . htmlspecialchars($base . 'assets/logos/nagad.svg', ENT_QUOTES, 'UTF-8') . '\';">
        </div>';
    } elseif (str_contains($m, 'rocket')) {
        return '<div class="sn-pm-header-logo-wrap sn-pm-header-rocket" title="DBBL Rocket Official Gateway">
            <img src="' . htmlspecialchars($rocketImg, ENT_QUOTES, 'UTF-8') . '" 
                 alt="Rocket" 
                 class="sn-pm-brand-img">
        </div>';
    } elseif (str_contains($m, 'upay')) {
        return '<div class="sn-pm-header-logo-wrap sn-pm-header-upay" title="UCB Upay Official Gateway">
            <img src="' . htmlspecialchars($upayImg, ENT_QUOTES, 'UTF-8') . '" 
                 alt="Upay" 
                 class="sn-pm-brand-img">
        </div>';
    } elseif (str_contains($m, 'cod') || str_contains($m, 'cash')) {
        return '<div class="sn-pm-header-logo-wrap sn-pm-header-cod" title="Cash on Delivery">
            <span style="font-size:11px; font-weight:800; color:#059669; letter-spacing:0.3px;">COD</span>
        </div>';
    } else {
        return '<div class="sn-pm-header-logo-wrap">
            <span style="font-size:11px; font-weight:700; color:#0f2942;">' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '</span>
        </div>';
    }
}

// Payment method official badge renderer for key-value row (eliminates raw text)
function renderPaymentMethodBadge($method) {
    global $baseUrlResolved;
    $m = strtolower(trim((string)$method));
    $base = !empty($baseUrlResolved) ? rtrim($baseUrlResolved, '/') . '/' : (defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/');

    $bkashImg  = $base . 'assets/logos/bkash.png';
    $nagadImg  = $base . 'assets/logos/nagad.png';
    $rocketImg = $base . 'assets/logos/rocket.png';
    $upayImg   = $base . 'assets/logos/upay.png';

    if (str_contains($m, 'bkash')) {
        return '<span class="sn-pm-badge sn-pm-badge-bkash">
            <img src="' . htmlspecialchars($bkashImg, ENT_QUOTES, 'UTF-8') . '" 
                 alt="bKash" 
                 class="sn-badge-logo" 
                 onerror="this.onerror=null; this.src=\'' . htmlspecialchars($base . 'assets/logos/bkash.svg', ENT_QUOTES, 'UTF-8') . '\';">
            <span class="sn-badge-name">bKash</span>
        </span>';
    } elseif (str_contains($m, 'nagad')) {
        return '<span class="sn-pm-badge sn-pm-badge-nagad">
            <img src="' . htmlspecialchars($nagadImg, ENT_QUOTES, 'UTF-8') . '" 
                 alt="Nagad" 
                 class="sn-badge-logo" 
                 onerror="this.onerror=null; this.src=\'' . htmlspecialchars($base . 'assets/logos/nagad.svg', ENT_QUOTES, 'UTF-8') . '\';">
            <span class="sn-badge-name">Nagad</span>
        </span>';
    } elseif (str_contains($m, 'rocket')) {
        return '<span class="sn-pm-badge sn-pm-badge-rocket">
            <img src="' . htmlspecialchars($rocketImg, ENT_QUOTES, 'UTF-8') . '" 
                 alt="Rocket" 
                 class="sn-badge-logo">
            <span class="sn-badge-name">Rocket</span>
        </span>';
    } elseif (str_contains($m, 'upay')) {
        return '<span class="sn-pm-badge sn-pm-badge-upay">
            <img src="' . htmlspecialchars($upayImg, ENT_QUOTES, 'UTF-8') . '" 
                 alt="Upay" 
                 class="sn-badge-logo">
            <span class="sn-badge-name">Upay</span>
        </span>';
    } elseif (str_contains($m, 'cod') || str_contains($m, 'cash')) {
        return '<span class="sn-pm-badge sn-pm-badge-cod">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                <circle cx="12" cy="12" r="2"></circle>
                <path d="M6 12h.01M18 12h.01"></path>
            </svg>
            <span class="sn-badge-name">Cash on Delivery</span>
        </span>';
    } else {
        return '<span class="sn-pm-badge" style="background:#f1f5f9; color:#0f172a; border-color:#cbd5e1;">
            <span class="sn-badge-name">' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '</span>
        </span>';
    }
}
?>

<!-- GOOGLE FONTS FOR EXACT LAYOUT MATCHING -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
/* =========================================================
   PIXEL-PERFECT ORDER RECEIPT - LANDSCAPE SINGLE PAGE STYLES
   Matches media_1792345620247_243409dd.jpg exactly.
   ========================================================= */

.sn-rcpt-container {
    max-width: 1060px;
    margin: 20px auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 24px 28px;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #0f172a;
    box-sizing: border-box;
    position: relative;
}

/* 1. Header Bar */
.sn-rcpt-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 18px;
    border-bottom: 1px solid #f1f5f9;
    gap: 16px;
}

.sn-rcpt-brand-col {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sn-rcpt-logo-img {
    max-height: 44px;
    max-width: 200px;
    object-fit: contain;
}

.sn-rcpt-brand-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #0f2942;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    flex-shrink: 0;
}

.sn-rcpt-brand-title {
    font-size: 26px;
    font-weight: 800;
    color: #0f2942;
    letter-spacing: -0.5px;
    line-height: 1;
    text-transform: uppercase;
}

.sn-rcpt-brand-subtitle {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 500;
    margin-top: 4px;
    letter-spacing: -0.1px;
}

.sn-rcpt-trust-badges {
    display: flex;
    align-items: center;
}

.sn-rcpt-badge-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sn-rcpt-badge-item svg {
    color: #0f2942;
    flex-shrink: 0;
}

.sn-rcpt-badge-text strong {
    font-size: 11.5px;
    font-weight: 700;
    color: #0f2942;
    display: block;
    line-height: 1.2;
}

.sn-rcpt-badge-text span {
    font-size: 11px;
    color: #64748b;
    display: block;
    line-height: 1.2;
}

.sn-rcpt-badge-divider {
    width: 1px;
    height: 28px;
    background: #e2e8f0;
    margin: 0 16px;
}

/* 2. Hero Row (Receipt Title + Order Hero Card) */
.sn-rcpt-hero-row {
    display: flex;
    justify-content: space-between;
    align-items: stretch;
    margin-top: 20px;
    margin-bottom: 18px;
    gap: 18px;
}

.sn-rcpt-hero-left {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    flex: 1;
}

.sn-rcpt-hero-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e40af;
    flex-shrink: 0;
}

.sn-rcpt-hero-texts h1 {
    font-size: 24px;
    font-weight: 800;
    color: #0f2942;
    margin: 0 0 3px 0;
    letter-spacing: -0.5px;
    line-height: 1.15;
}

.sn-rcpt-hero-texts .sn-sub1 {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 2px 0;
}

.sn-rcpt-hero-texts .sn-sub2 {
    font-size: 11.5px;
    color: #64748b;
    margin: 0;
}

/* Hero Order Card (Right side) */
.sn-rcpt-hero-card {
    background: #f1f6fd;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.sn-rcpt-card-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sn-rcpt-card-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.sn-rcpt-card-doc-badge {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: #2563eb;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sn-rcpt-card-ordernum {
    font-size: 14.5px;
    font-weight: 800;
    color: #0f2942;
    letter-spacing: -0.3px;
}

.sn-rcpt-card-row {
    display: flex;
    align-items: center;
    font-size: 11.5px;
    line-height: 1.4;
}

.sn-rcpt-card-label {
    width: 100px;
    color: #64748b;
    font-weight: 500;
}

.sn-rcpt-card-colon {
    margin-right: 8px;
    color: #64748b;
}

.sn-rcpt-card-val {
    font-weight: 600;
    color: #0f2942;
    display: flex;
    align-items: center;
    gap: 6px;
}

.sn-rcpt-card-divider {
    width: 1px;
    align-self: stretch;
    background: #dbeafe;
}

.sn-rcpt-card-right {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-left: 4px;
}

.sn-rcpt-clock-icon {
    color: #0f2942;
    flex-shrink: 0;
}

.sn-rcpt-est-label {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}

.sn-rcpt-est-val {
    font-size: 12.5px;
    font-weight: 700;
    color: #0f2942;
    margin-top: 2px;
    white-space: nowrap;
}

/* Badges */
.sn-pill-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.35;
}

.sn-pill-green {
    background: #dcfce7;
    color: #15803d;
}

.sn-pill-blue {
    background: #e0f2fe;
    color: #0369a1;
}

.sn-pill-indigo {
    background: #e0e7ff;
    color: #4338ca;
}

.sn-pill-yellow {
    background: #fef3c7;
    color: #b45309;
}

.sn-pill-red {
    background: #fee2e2;
    color: #b91c1c;
}

/* 3. Middle Row 1: Three Address & Payment Cards */
.sn-rcpt-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 16px;
}

.sn-rcpt-info-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 11.5px;
    line-height: 1.4;
    display: flex;
    flex-direction: column;
}

.sn-rcpt-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.sn-rcpt-card-heading {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #0f2942;
}

.sn-rcpt-card-heading svg {
    color: #0f2942;
    flex-shrink: 0;
}

.sn-rcpt-cust-name {
    font-weight: 700;
    color: #0f2942;
    font-size: 12.5px;
    margin-bottom: 3px;
}

.sn-rcpt-addr-content {
    color: #475569;
    font-size: 11.5px;
    line-height: 1.35;
    margin-bottom: 6px;
    text-transform: uppercase;
    flex: 1;
}

.sn-rcpt-contact-line {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #334155;
    font-size: 11.5px;
    margin-top: 3px;
}

.sn-rcpt-contact-line svg {
    color: #0f2942;
    flex-shrink: 0;
}

/* Payment Method Card Content */
.sn-pm-logo {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.sn-pm-header-logo-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    padding: 3px 8px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.sn-pm-brand-img {
    height: 24px;
    max-height: 24px;
    max-width: 85px;
    object-fit: contain;
    display: block;
}

/* Official Payment Method Badges (Replacing raw plain text) */
.sn-pm-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 9px 3px 6px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    border: 1px solid transparent;
    vertical-align: middle;
}

.sn-pm-badge .sn-badge-logo {
    height: 16px;
    width: auto;
    max-width: 26px;
    object-fit: contain;
    border-radius: 2px;
    display: inline-block;
}

.sn-pm-badge-bkash {
    background: #fdf2f6;
    border-color: #fbcfe8;
}
.sn-pm-badge-bkash .sn-badge-name {
    color: #e2136e;
    font-weight: 800;
    letter-spacing: -0.2px;
}

.sn-pm-badge-nagad {
    background: #fff7ed;
    border-color: #fed7aa;
}
.sn-pm-badge-nagad .sn-badge-name {
    color: #ed1c24;
    font-weight: 800;
}

.sn-pm-badge-rocket {
    background: #faf5ff;
    border-color: #e9d5ff;
}
.sn-pm-badge-rocket .sn-badge-name {
    color: #8c3494;
    font-weight: 800;
}

.sn-pm-badge-upay {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.sn-pm-badge-upay .sn-badge-name {
    color: #0054a6;
    font-weight: 800;
}

.sn-pm-badge-cod {
    background: #ecfdf5;
    border-color: #a7f3d0;
}
.sn-pm-badge-cod .sn-badge-name {
    color: #059669;
    font-weight: 800;
    letter-spacing: 0.3px;
}

/* Footer Channels Strip */
.sn-foot-channels {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 5px;
    padding-top: 5px;
    border-top: 1px dashed #e2e8f0;
}

.sn-foot-channels-label {
    font-size: 10px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.sn-foot-chan-img {
    height: 14px;
    max-height: 14px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 1px 1px rgba(0,0,0,0.05));
}

.sn-rcpt-kv-row {
    display: flex;
    align-items: center;
    font-size: 11.5px;
    margin-bottom: 4px;
    line-height: 1.35;
}

.sn-rcpt-kv-row:last-child {
    margin-bottom: 0;
}

.sn-rcpt-kv-label {
    width: 95px;
    color: #64748b;
    flex-shrink: 0;
}

.sn-rcpt-kv-colon {
    margin-right: 8px;
    color: #64748b;
}

.sn-rcpt-kv-val {
    color: #0f2942;
    font-weight: 600;
}

/* 4. Middle Row 2: Order Items & Summary Section */
.sn-rcpt-main-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 14px;
    margin-bottom: 16px;
    align-items: stretch;
}

.sn-rcpt-items-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sn-rcpt-items-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
}

.sn-rcpt-items-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 700;
    color: #0f2942;
}

.sn-rcpt-items-title svg {
    color: #0f2942;
}

.sn-rcpt-count-pill {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
}

.sn-rcpt-table {
    width: 100%;
    border-collapse: collapse;
}

.sn-rcpt-table thead tr {
    background: #fafbfc;
    border-bottom: 1px solid #e2e8f0;
}

.sn-rcpt-table th {
    padding: 8px 10px;
    font-size: 11.5px;
    font-weight: 600;
    color: #475569;
}

.sn-rcpt-table td {
    padding: 8px 10px;
    font-size: 11.5px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.sn-rcpt-table tbody tr:last-child td {
    border-bottom: none;
}

.sn-col-idx {
    text-align: center;
    color: #64748b;
    font-weight: 600;
    width: 32px;
}

.sn-col-prod {
    text-align: left;
}

.sn-prod-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sn-prod-thumb {
    width: 38px;
    height: 38px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    object-fit: cover;
    background: #f8fafc;
    flex-shrink: 0;
}

.sn-prod-title {
    font-size: 12px;
    font-weight: 700;
    color: #0f2942;
    line-height: 1.25;
}

.sn-prod-sub {
    font-size: 11px;
    color: #64748b;
    line-height: 1.2;
    margin-top: 1px;
}

.sn-col-var {
    text-align: left;
    color: #475569;
    font-size: 11.5px;
    width: 130px;
}

.sn-col-price {
    text-align: center;
    color: #0f2942;
    font-weight: 600;
    width: 70px;
    white-space: nowrap;
}

.sn-col-qty {
    text-align: center;
    color: #0f2942;
    font-weight: 600;
    width: 60px;
}

.sn-col-total {
    text-align: right;
    color: #0f2942;
    font-weight: 700;
    width: 75px;
    white-space: nowrap;
}

/* Right Summary & Shipping Card */
.sn-rcpt-side-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
}

.sn-side-header {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13.5px;
    font-weight: 700;
    color: #0f2942;
    margin-bottom: 10px;
}

.sn-side-header svg {
    color: #0f2942;
}

.sn-sum-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    margin-bottom: 6px;
}

.sn-sum-label {
    color: #475569;
}

.sn-sum-val {
    font-weight: 600;
    color: #0f2942;
}

.sn-discount-green {
    color: #10b981 !important;
    font-weight: 600;
}

.sn-sum-divider {
    height: 1px;
    background: #e2e8f0;
    margin: 8px 0;
}

.sn-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
    margin-bottom: 12px;
}

.sn-total-text {
    font-size: 16px;
    font-weight: 800;
    color: #0f2942;
}

.sn-total-num {
    font-size: 22px;
    font-weight: 900;
    color: #0f2942;
    letter-spacing: -0.5px;
}

/* Shipping Info Section */
.sn-shipinfo-section {
    border-top: 1px solid #f1f5f9;
    padding-top: 10px;
    margin-top: auto;
}

.sn-shipinfo-header {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12.5px;
    font-weight: 700;
    color: #0f2942;
    margin-bottom: 8px;
}

.sn-shipinfo-header svg {
    color: #0f2942;
}

.sn-ship-row {
    display: flex;
    align-items: center;
    font-size: 11px;
    margin-bottom: 4px;
    line-height: 1.35;
}

.sn-ship-row:last-child {
    margin-bottom: 0;
}

.sn-ship-label {
    width: 105px;
    color: #64748b;
    flex-shrink: 0;
}

.sn-ship-colon {
    margin-right: 8px;
    color: #64748b;
}

.sn-ship-val {
    color: #0f2942;
    font-weight: 600;
    white-space: nowrap;
}

/* 5. Bottom Banner / Footer */
.sn-rcpt-footer-banner {
    background: #f0f6fc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.sn-foot-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.sn-foot-qrcode-img {
    width: 50px;
    height: 50px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 2px;
    background: #ffffff;
    flex-shrink: 0;
}

.sn-foot-divider {
    width: 1px;
    height: 38px;
    background: #dbeafe;
}

.sn-foot-text strong {
    font-size: 12.5px;
    font-weight: 800;
    color: #0f2942;
    display: block;
    margin-bottom: 1px;
}

.sn-foot-text p {
    font-size: 11px;
    color: #64748b;
    margin: 0 0 3px 0;
    line-height: 1.25;
}

.sn-foot-secure {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
}

.sn-foot-secure svg {
    color: #2563eb;
}

.sn-foot-mid {
    display: flex;
    flex-direction: column;
    gap: 3px;
    font-size: 11px;
    color: #475569;
}

.sn-foot-contact-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sn-foot-contact-item svg {
    color: #0f2942;
    flex-shrink: 0;
}

.sn-foot-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    transform: rotate(-4deg);
    margin-right: 8px;
    flex-shrink: 0;
}

.sn-sign-text {
    font-family: 'Caveat', cursive, sans-serif;
    font-size: 26px;
    font-weight: 700;
    color: #2563eb;
    line-height: 1;
}

.sn-sign-underline {
    margin-top: -2px;
}

/* Responsive scroll wrapper for items table */
.sn-rcpt-table-scroll {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* =========================================================
   RESPONSIVE SCREEN STYLES (TABLETS & MOBILE PHONES)
   Leaves @media print completely untouched in Landscape format
   ========================================================= */
@media screen and (max-width: 860px) {
    .sn-rcpt-container {
        margin: 12px auto;
        padding: 18px 16px;
        border-radius: 12px;
        max-width: 100%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }

    /* Header Bar: Stack Brand & Trust Badges */
    .sn-rcpt-header-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
        padding-bottom: 14px;
    }

    .sn-rcpt-trust-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 14px;
        width: 100%;
    }

    .sn-rcpt-badge-divider {
        display: none;
    }

    .sn-rcpt-badge-item {
        gap: 8px;
    }

    /* Hero Row: Stack title and hero card */
    .sn-rcpt-hero-row {
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
        margin-top: 14px;
        margin-bottom: 14px;
    }

    .sn-rcpt-hero-left {
        width: 100%;
    }

    .sn-rcpt-hero-card {
        width: 100%;
        box-sizing: border-box;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 14px;
    }

    .sn-rcpt-card-divider {
        width: 100%;
        height: 1px;
        align-self: auto;
        margin: 2px 0;
    }

    .sn-rcpt-card-right {
        padding-left: 0;
        width: 100%;
    }

    /* Middle Row 1: 3-column cards stack to 1 column */
    .sn-rcpt-cards-grid {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 14px;
    }

    .sn-rcpt-info-card {
        padding: 12px 14px;
    }

    /* Middle Row 2: 2-column main grid stacks to 1 column */
    .sn-rcpt-main-grid {
        grid-template-columns: 1fr;
        gap: 14px;
        margin-bottom: 14px;
    }

    /* Table styling on mobile */
    .sn-rcpt-table {
        min-width: 520px;
    }

    /* Footer: Stack QR, contacts, and signature */
    .sn-rcpt-footer-banner {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 16px;
    }

    .sn-foot-left {
        width: 100%;
    }

    .sn-foot-mid {
        width: 100%;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
    }

    .sn-foot-right {
        align-self: flex-end;
        margin-right: 4px;
        margin-top: 4px;
    }
}

@media screen and (max-width: 480px) {
    .sn-rcpt-container {
        margin: 6px auto;
        padding: 14px 12px;
        border-radius: 10px;
    }

    .sn-rcpt-brand-title {
        font-size: 20px;
    }

    .sn-rcpt-brand-subtitle {
        font-size: 10.5px;
    }

    .sn-rcpt-hero-texts h1 {
        font-size: 20px;
    }

    .sn-rcpt-hero-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
    }

    .sn-rcpt-hero-card {
        padding: 10px 12px;
    }

    .sn-rcpt-card-ordernum {
        font-size: 13.5px;
    }

    .sn-rcpt-table {
        min-width: 460px;
    }

    .sn-rcpt-table th, 
    .sn-rcpt-table td {
        padding: 6px 8px;
        font-size: 11px;
    }

    .sn-prod-thumb {
        width: 32px;
        height: 32px;
    }

    .sn-prod-title {
        font-size: 11.5px;
    }

    .sn-foot-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .sn-foot-divider {
        display: none;
    }
}

/* =========================================================
   PRINT SPECIFICATION - FORCED LANDSCAPE & SINGLE PAGE FIT
   ========================================================= */
@page {
    size: landscape;
    margin: 6mm 8mm;
}

@media print {
    *, *:before, *:after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    body, html {
        background: #ffffff !important;
        color: #0f172a !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        min-height: auto !important;
        height: auto !important;
        overflow: visible !important;
        font-size: 10pt !important;
    }

    /* Container: Borderless, exact fit on landscape sheet */
    .sn-rcpt-container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 auto !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        background: #ffffff !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        page-break-after: avoid !important;
        page-break-before: avoid !important;
    }

    /* Keep side-by-side grids strictly locked in print */
    .sn-rcpt-header-bar {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding-bottom: 12px !important;
    }

    .sn-rcpt-hero-row {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        margin-top: 14px !important;
        margin-bottom: 12px !important;
        page-break-inside: avoid !important;
    }

    .sn-rcpt-cards-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 10px !important;
        margin-bottom: 12px !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .sn-rcpt-info-card {
        padding: 10px 12px !important;
        page-break-inside: avoid !important;
    }

    .sn-rcpt-main-grid {
        display: grid !important;
        grid-template-columns: 1fr 310px !important;
        gap: 12px !important;
        margin-bottom: 12px !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .sn-rcpt-table th, 
    .sn-rcpt-table td {
        padding: 6px 8px !important;
        font-size: 10.5px !important;
    }

    .sn-rcpt-prod-thumb {
        width: 32px !important;
        height: 32px !important;
    }

    .sn-rcpt-table-scroll {
        overflow: visible !important;
    }

    .sn-rcpt-footer-banner {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        padding: 8px 14px !important;
        margin-top: 10px !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    /* Hide unnecessary UI chrome when printing */
    .no-print,
    .sn-nav-action-bar,
    .receipt-action-bar,
    .print-floating-bar,
    .main-header,
    .main-sidebar,
    .left-side,
    .main-footer,
    .content-header,
    .breadcrumb,
    .btn,
    .modal,
    .modal-backdrop {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
    }
}
</style>

<!-- MAIN RECEIPT CANVAS -->
<div class="sn-rcpt-container" id="pixelPerfectReceiptSheet">

    <!-- 1. Top Header Bar -->
    <div class="sn-rcpt-header-bar">
        <!-- Store Identity / Custom Logo -->
        <div class="sn-rcpt-brand-col">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" 
                     alt="<?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>" 
                     class="sn-rcpt-logo-img"
                     onerror="this.style.display='none'; document.getElementById('snRcptFallbackLogo').style.display='flex';">
            <?php endif; ?>

            <div class="sn-rcpt-brand-icon-box" id="snRcptFallbackLogo" style="<?= !empty($logoUrl) ? 'display:none;' : '' ?>">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
            </div>
            <div>
                <div class="sn-rcpt-brand-title"><?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="sn-rcpt-brand-subtitle">Better Products &nbsp;·&nbsp; Better Life</div>
            </div>
        </div>

        <!-- 3 Trust Badges -->
        <div class="sn-rcpt-trust-badges">
            <div class="sn-rcpt-badge-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
                <div class="sn-rcpt-badge-text">
                    <strong>Fast &amp; Reliable</strong>
                    <span>Delivery</span>
                </div>
            </div>

            <div class="sn-rcpt-badge-divider"></div>

            <div class="sn-rcpt-badge-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <polyline points="9 12 11 14 15 10"></polyline>
                </svg>
                <div class="sn-rcpt-badge-text">
                    <strong>100% Genuine</strong>
                    <span>Products</span>
                </div>
            </div>

            <div class="sn-rcpt-badge-divider"></div>

            <div class="sn-rcpt-badge-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                    <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                </svg>
                <div class="sn-rcpt-badge-text">
                    <strong>24/7 Customer</strong>
                    <span>Support</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Hero Row (Order Receipt Title + Order Hero Card) -->
    <div class="sn-rcpt-hero-row">
        <div class="sn-rcpt-hero-left">
            <div class="sn-rcpt-hero-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <line x1="10" y1="9" x2="8" y2="9"></line>
                </svg>
            </div>
            <div class="sn-rcpt-hero-texts">
                <h1>Order Receipt</h1>
                <div class="sn-sub1">Thank you for shopping with <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>!</div>
                <div class="sn-sub2">Your order has been received and is being processed.</div>
            </div>
        </div>

        <div class="sn-rcpt-hero-card">
            <div class="sn-rcpt-card-left">
                <div class="sn-rcpt-card-header">
                    <div class="sn-rcpt-card-doc-badge">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                    </div>
                    <div class="sn-rcpt-card-ordernum">Order <?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="sn-rcpt-card-row">
                    <span class="sn-rcpt-card-label">Order Date</span>
                    <span class="sn-rcpt-card-colon">:</span>
                    <span class="sn-rcpt-card-val"><?= htmlspecialchars($formattedOrderDate, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="sn-rcpt-card-row">
                    <span class="sn-rcpt-card-label">Payment Status</span>
                    <span class="sn-rcpt-card-colon">:</span>
                    <span class="sn-rcpt-card-val">
                        <?= htmlspecialchars($payStatusLabel, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8') ?>)
                        <span class="sn-pill-badge <?= $payStatusBadgeClass ?>"><?= $payStatusLabel ?></span>
                    </span>
                </div>

                <div class="sn-rcpt-card-row">
                    <span class="sn-rcpt-card-label">Order Status</span>
                    <span class="sn-rcpt-card-colon">:</span>
                    <span class="sn-rcpt-card-val">
                        <span class="sn-pill-badge <?= $shipStatusBadgeClass ?>"><?= $shipStatusLabel ?></span>
                    </span>
                </div>
            </div>

            <div class="sn-rcpt-card-divider"></div>

            <div class="sn-rcpt-card-right">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sn-rcpt-clock-icon">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <div>
                    <div class="sn-rcpt-est-label">Estimated Delivery</div>
                    <div class="sn-rcpt-est-val"><?= htmlspecialchars($estDelivery, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Middle Row 1: Three Address & Payment Cards -->
    <div class="sn-rcpt-cards-grid">
        <!-- Billing Address -->
        <div class="sn-rcpt-info-card">
            <div class="sn-rcpt-card-top">
                <div class="sn-rcpt-card-heading">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Billing Address</span>
                </div>
            </div>
            <div class="sn-rcpt-cust-name"><?= htmlspecialchars($custName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sn-rcpt-addr-content">
                <?= nl2br(htmlspecialchars($billStreet, ENT_QUOTES, 'UTF-8')) ?><?= !empty($billCity) ? ', ' . htmlspecialchars($billCity, ENT_QUOTES, 'UTF-8') : '' ?><?= !empty($billZip) ? ' ' . htmlspecialchars($billZip, ENT_QUOTES, 'UTF-8') : '' ?>
            </div>
            <?php if (!empty($custPhone)): ?>
                <div class="sn-rcpt-contact-line">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1v3.5a1 1 0 01-1 1C10.61 21.01 2.99 13.39 2.99 4a1 1 0 011-1H7.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    <span>Phone: <?= htmlspecialchars($custPhone, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($custEmail)): ?>
                <div class="sn-rcpt-contact-line">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <span>Email: <?= htmlspecialchars($custEmail, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Shipping Address -->
        <div class="sn-rcpt-info-card">
            <div class="sn-rcpt-card-top">
                <div class="sn-rcpt-card-heading">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                    <span>Shipping Address</span>
                </div>
            </div>
            <div class="sn-rcpt-cust-name"><?= htmlspecialchars($shipName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sn-rcpt-addr-content">
                <?= nl2br(htmlspecialchars($shipStreet, ENT_QUOTES, 'UTF-8')) ?><?= !empty($shipCity) ? ', ' . htmlspecialchars($shipCity, ENT_QUOTES, 'UTF-8') : '' ?><?= !empty($shipZip) ? ' ' . htmlspecialchars($shipZip, ENT_QUOTES, 'UTF-8') : '' ?>
            </div>
            <?php if (!empty($shipPhone)): ?>
                <div class="sn-rcpt-contact-line">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1v3.5a1 1 0 01-1 1C10.61 21.01 2.99 13.39 2.99 4a1 1 0 011-1H7.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    <span>Phone: <?= htmlspecialchars($shipPhone, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Method -->
        <div class="sn-rcpt-info-card">
            <div class="sn-rcpt-card-top">
                <div class="sn-rcpt-card-heading">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    <span>Payment Method</span>
                </div>
                <div><?= renderPaymentBrandLogo($paymentMethod) ?></div>
            </div>

            <div class="sn-rcpt-kv-row" style="align-items: center;">
                <span class="sn-rcpt-kv-label">Method</span>
                <span class="sn-rcpt-kv-colon">:</span>
                <span class="sn-rcpt-kv-val"><?= renderPaymentMethodBadge($paymentMethod) ?></span>
            </div>
            <div class="sn-rcpt-kv-row">
                <span class="sn-rcpt-kv-label">Transaction ID</span>
                <span class="sn-rcpt-kv-colon">:</span>
                <span class="sn-rcpt-kv-val" style="font-family:monospace;"><?= htmlspecialchars($displayTxnId, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="sn-rcpt-kv-row">
                <span class="sn-rcpt-kv-label">Paid Amount</span>
                <span class="sn-rcpt-kv-colon">:</span>
                <span class="sn-rcpt-kv-val">&#2547;<?= number_format($totalAmount) ?></span>
            </div>
            <div class="sn-rcpt-kv-row">
                <span class="sn-rcpt-kv-label">Paid Date</span>
                <span class="sn-rcpt-kv-colon">:</span>
                <span class="sn-rcpt-kv-val"><?= htmlspecialchars($formattedPaidDate, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>

    <!-- 4. Middle Row 2: Order Items Table + Summary & Shipping Section -->
    <div class="sn-rcpt-main-grid">
        <!-- Order Items Table -->
        <div class="sn-rcpt-items-card">
            <div class="sn-rcpt-items-header">
                <div class="sn-rcpt-items-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                    <span>Order Items</span>
                </div>
                <div class="sn-rcpt-count-pill"><?= count($itemsList) ?> <?= count($itemsList) === 1 ? 'Item' : 'Items' ?></div>
            </div>

            <div class="sn-rcpt-table-scroll">
            <table class="sn-rcpt-table">
                <thead>
                    <tr>
                        <th class="sn-col-idx">#</th>
                        <th class="sn-col-prod">Product</th>
                        <th class="sn-col-var">Variant</th>
                        <th class="sn-col-price">Price</th>
                        <th class="sn-col-qty">Quantity</th>
                        <th class="sn-col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itemsList)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:24px; color:#64748b;">No products found in this order.</td>
                        </tr>
                    <?php else: ?>
                        <?php $rowNum = 1; foreach ($itemsList as $it): 
                            $unitPrice = (float)($it['unit_price'] ?? 0);
                            $qty = (int)($it['quantity'] ?? 1);
                            $lineTotal = $unitPrice * $qty;
                            
                            $photo = $it['p_featured_photo'] ?? '';
                            $photoUrl = '';
                            if (!empty($photo)) {
                                $photoUrl = str_starts_with($photo, 'http') ? $photo : $baseUrlResolved . 'assets/uploads/' . basename($photo);
                            }
                            
                            // Variant extraction matching the layout
                            $variantParts = [];
                            if (!empty($it['color'])) {
                                $variantParts[] = trim($it['color']);
                            }
                            if (!empty($it['size'])) {
                                $variantParts[] = trim($it['size']);
                            }
                            $variantText = !empty($variantParts) ? implode(' · ', $variantParts) : '—';
                        ?>
                            <tr>
                                <td class="sn-col-idx"><?= $rowNum++ ?></td>
                                <td class="sn-col-prod">
                                    <div class="sn-prod-cell">
                                        <?php if (!empty($photoUrl)): ?>
                                            <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" 
                                                 alt="<?= htmlspecialchars($it['product_name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>" 
                                                 class="sn-prod-thumb"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <div>
                                            <div class="sn-prod-title"><?= htmlspecialchars($it['product_name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if ($variantText !== '—'): ?>
                                                <div class="sn-prod-sub"><?= htmlspecialchars($variantText, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="sn-col-var"><?= htmlspecialchars($variantText, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="sn-col-price">&#2547;<?= number_format($unitPrice) ?></td>
                                <td class="sn-col-qty"><?= $qty ?></td>
                                <td class="sn-col-total">&#2547;<?= number_format($lineTotal) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Right Side: Order Summary & Dynamic Shipping Info -->
        <div class="sn-rcpt-side-card">
            <!-- Order Summary -->
            <div>
                <div class="sn-side-header">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    <span>Order Summary</span>
                </div>

                <div class="sn-sum-row">
                    <span class="sn-sum-label">Subtotal</span>
                    <span class="sn-sum-val">&#2547;<?= number_format($subtotal) ?></span>
                </div>
                <div class="sn-sum-row">
                    <span class="sn-sum-label">Shipping Fee</span>
                    <span class="sn-sum-val">&#2547;<?= number_format($shippingCost) ?></span>
                </div>
                <div class="sn-sum-row">
                    <span class="sn-sum-label">Discount</span>
                    <span class="sn-sum-val sn-discount-green">- &#2547;<?= number_format($couponDiscount) ?></span>
                </div>

                <div class="sn-sum-divider"></div>

                <div class="sn-total-row">
                    <span class="sn-total-text">Total</span>
                    <span class="sn-total-num">&#2547;<?= number_format($totalAmount) ?></span>
                </div>
            </div>

            <!-- Dynamic Shipping Information (No fake pathao, everything dynamic) -->
            <div class="sn-shipinfo-section">
                <div class="sn-shipinfo-header">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#0f2942" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                    <span>Shipping Information</span>
                </div>

                <div class="sn-ship-row">
                    <span class="sn-ship-label">Shipping Partner</span>
                    <span class="sn-ship-colon">:</span>
                    <span class="sn-ship-val"><?= htmlspecialchars($shippingPartner, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="sn-ship-row">
                    <span class="sn-ship-label">Tracking Number</span>
                    <span class="sn-ship-colon">:</span>
                    <span class="sn-ship-val" style="font-family:monospace;"><?= htmlspecialchars($trackingNumber, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="sn-ship-row">
                    <span class="sn-ship-label">Estimated Delivery</span>
                    <span class="sn-ship-colon">:</span>
                    <span class="sn-ship-val"><?= htmlspecialchars($estDelivery, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Bottom Banner / Footer -->
    <div class="sn-rcpt-footer-banner">
        <!-- Left: QR Code + Thank you -->
        <div class="sn-foot-left">
            <img src="<?= htmlspecialchars($qrCodeUrl, ENT_QUOTES, 'UTF-8') ?>" 
                 alt="Scan to verify order" 
                 class="sn-foot-qrcode-img">

            <div class="sn-foot-divider"></div>

            <div class="sn-foot-text">
                <strong>Thank you for choosing <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>!</strong>
                <p>We hope you love your purchase. If you have any questions, feel free to contact us.</p>
                <div class="sn-foot-secure">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <polyline points="9 12 11 14 15 10"></polyline>
                    </svg>
                    <span>Your order is safe and secure</span>
                </div>
                <div class="sn-foot-channels">
                    <span class="sn-foot-channels-label">Gateways:</span>
                    <img src="<?= htmlspecialchars($baseUrlResolved . 'assets/logos/bkash.png', ENT_QUOTES, 'UTF-8') ?>" alt="bKash" title="bKash" class="sn-foot-chan-img">
                    <img src="<?= htmlspecialchars($baseUrlResolved . 'assets/logos/nagad.png', ENT_QUOTES, 'UTF-8') ?>" alt="Nagad" title="Nagad" class="sn-foot-chan-img">
                    <img src="<?= htmlspecialchars($baseUrlResolved . 'assets/logos/rocket.png', ENT_QUOTES, 'UTF-8') ?>" alt="Rocket" title="Rocket" class="sn-foot-chan-img">
                    <img src="<?= htmlspecialchars($baseUrlResolved . 'assets/logos/upay.png', ENT_QUOTES, 'UTF-8') ?>" alt="Upay" title="Upay" class="sn-foot-chan-img">
                </div>
            </div>
        </div>

        <!-- Middle: Contact Details -->
        <div class="sn-foot-mid">
            <div class="sn-foot-contact-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
                <span><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="sn-foot-contact-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1v3.5a1 1 0 01-1 1C10.61 21.01 2.99 13.39 2.99 4a1 1 0 011-1H7.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/>
                </svg>
                <span><?= htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="sn-foot-contact-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <span><?= htmlspecialchars(str_starts_with($websiteHost, 'http') ? $websiteHost : 'http://' . $websiteHost, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <!-- Right: Handwritten "Shop Smarter" Script -->
        <div class="sn-foot-right">
            <span class="sn-sign-text">Shop Smarter</span>
            <svg class="sn-sign-underline" width="90" height="10" viewBox="0 0 90 10" fill="none">
                <path d="M2 6C25 2 65 3 88 7" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
        </div>
    </div>

</div>
