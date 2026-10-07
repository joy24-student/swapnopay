<?php
/**
 * Pixel-Perfect Order Receipt & Invoice Template
 * Matches media_1791282249465_a858b34e.png exactly.
 * Supports dynamic admin logo customization from tbl_settings.
 */

if (!isset($order) || empty($order)) {
    return;
}

// 1. Data Normalization
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
$paymentId = (string)($order['payment_id'] ?? ($order['id'] ?? 'SM' . date('Ymd') . '-0001'));
$orderNumber = !empty($order['txnid']) && str_starts_with($order['txnid'], 'ORD-') ? $order['txnid'] : ('#' . (str_starts_with($paymentId, '#') ? substr($paymentId, 1) : $paymentId));
if (!str_starts_with($orderNumber, '#')) {
    $orderNumber = '#' . $orderNumber;
}

// Dates
$rawDate = $order['payment_date'] ?? date('Y-m-d H:i:s');
$formattedOrderDate = date('d M Y, h:i A', strtotime($rawDate));
$formattedPaidDate  = date('d M Y, h:i A', strtotime($rawDate));

// Status Badges
$payStatusRaw = strtolower(trim((string)($order['payment_status'] ?? 'pending')));
$shipStatusRaw = strtolower(trim((string)($order['shipping_status'] ?? 'processing')));

$isPaid = ($payStatusRaw === 'completed' || $payStatusRaw === 'paid');
$isCancelled = ($payStatusRaw === 'cancelled' || $shipStatusRaw === 'cancelled');

$payStatusLabel = $isPaid ? 'Paid' : ($isCancelled ? 'Cancelled' : 'Pending');
$payStatusClass = $isPaid ? 'badge-paid' : ($isCancelled ? 'badge-cancelled' : 'badge-pending');

$shipStatusLabel = 'Processing';
$shipStatusClass = 'badge-processing';
if ($shipStatusRaw === 'delivered' || $shipStatusRaw === 'completed') {
    $shipStatusLabel = 'Delivered';
    $shipStatusClass = 'badge-delivered';
} elseif ($shipStatusRaw === 'shipped' || $shipStatusRaw === 'out for delivery') {
    $shipStatusLabel = 'Shipped';
    $shipStatusClass = 'badge-shipped';
} elseif ($isCancelled) {
    $shipStatusLabel = 'Cancelled';
    $shipStatusClass = 'badge-cancelled';
}

// Customer & Addresses
$custName = (string)($order['customer_name'] ?? ($order['billing_name'] ?? ($customer['cust_name'] ?? 'Customer')));
$custEmail = (string)($order['customer_email'] ?? ($order['billing_email'] ?? ($customer['cust_email'] ?? '')));
$custPhone = (string)($order['billing_phone'] ?? ($order['shipping_phone'] ?? ($customer['cust_phone'] ?? '')));

$billStreet = (string)($order['billing_street'] ?? ($order['billing_address'] ?? ($order['shipping_street'] ?? '')));
$billCity = (string)($order['billing_city'] ?? '');
$billCountry = (string)($order['billing_country'] ?? 'Bangladesh');

$shipName = (string)($order['shipping_name'] ?? $custName);
$shipStreet = (string)($order['shipping_street'] ?? ($order['shipping_address'] ?? $billStreet));
$shipCity = (string)($order['shipping_city'] ?? $billCity);
$shipCountry = (string)($order['shipping_country'] ?? $billCountry);
$shipPhone = (string)($order['shipping_phone'] ?? $custPhone);

// Payment details
$paymentMethod = (string)($order['payment_method'] ?? 'Cash on Delivery');
$txnid = (string)($order['txnid'] ?? $paymentId);
if (empty($txnid)) {
    $txnid = 'N/A';
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

// Courier & Delivery details
$shippingPartner = 'Pathao';
$trackingNumber = 'PKG' . strtoupper(substr(md5($paymentId), 0, 9));
$estDelivery = date('d M Y', strtotime('+2 days')) . ' - ' . date('d M Y', strtotime('+5 days'));

// QR Code URL (verify link)
$verifyUrl = $baseUrlResolved . 'invoice.php?payment_id=' . rawurlencode($paymentId);
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&data=' . urlencode($verifyUrl);
?>

<!-- PIXEL-PERFECT ORDER RECEIPT STYLESHEET -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');

.sn-rcpt-container {
    max-width: 1040px;
    margin: 20px auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 36px 40px;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
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
    padding-bottom: 24px;
    border-bottom: 1px solid #f1f5f9;
    flex-wrap: wrap;
    gap: 16px;
}

.sn-rcpt-brand-col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.sn-rcpt-logo-img {
    max-height: 46px;
    max-width: 220px;
    object-fit: contain;
}

.sn-rcpt-fallback-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sn-rcpt-brand-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #0284c7;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
}

.sn-rcpt-brand-title {
    font-size: 24px;
    font-weight: 800;
    color: #0284c7;
    letter-spacing: -0.5px;
    line-height: 1;
}

.sn-rcpt-brand-subtitle {
    font-size: 12.5px;
    color: #64748b;
    font-weight: 500;
    margin-top: 4px;
}

.sn-rcpt-trust-badges {
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}

.sn-rcpt-badge-item {
    display: flex;
    align-items: center;
    gap: 9px;
}

.sn-rcpt-badge-item svg {
    color: #2563eb;
    flex-shrink: 0;
}

.sn-rcpt-badge-text {
    line-height: 1.25;
}

.sn-rcpt-badge-text strong {
    font-size: 11.5px;
    font-weight: 700;
    color: #0f172a;
    display: block;
}

.sn-rcpt-badge-text span {
    font-size: 11px;
    color: #64748b;
    display: block;
}

/* 2. Title Section */
.sn-rcpt-title-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-top: 26px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 20px;
}

.sn-rcpt-title-left {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.sn-rcpt-title-icon-wrap {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
}

.sn-rcpt-title-texts h1 {
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
    letter-spacing: -0.6px;
}

.sn-rcpt-title-texts .t-sub1 {
    font-size: 13.5px;
    font-weight: 600;
    color: #334155;
    margin: 0 0 2px 0;
}

.sn-rcpt-title-texts .t-sub2 {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

.sn-rcpt-title-card {
    background: #f0f7ff;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    padding: 14px 20px;
    min-width: 320px;
}

.sn-rcpt-card-orderno {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
    letter-spacing: -0.3px;
}

.sn-rcpt-meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    margin-bottom: 5px;
}

.sn-rcpt-meta-row:last-child {
    margin-bottom: 0;
}

.sn-rcpt-meta-label {
    color: #64748b;
    font-weight: 500;
}

.sn-rcpt-meta-val {
    font-weight: 600;
    color: #0f172a;
}

.sn-status-pill {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
}

.sn-status-pill.badge-paid {
    background: #10b981;
    color: #ffffff;
}

.sn-status-pill.badge-pending {
    background: #f59e0b;
    color: #ffffff;
}

.sn-status-pill.badge-cancelled {
    background: #ef4444;
    color: #ffffff;
}

.sn-status-pill.badge-processing {
    background: #3b82f6;
    color: #ffffff;
}

.sn-status-pill.badge-shipped {
    background: #6366f1;
    color: #ffffff;
}

.sn-status-pill.badge-delivered {
    background: #10b981;
    color: #ffffff;
}

/* 3. Info Cards Grid (3 Columns) */
.sn-rcpt-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.sn-rcpt-info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 18px;
    font-size: 12px;
    line-height: 1.5;
}

.sn-rcpt-card-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 10px;
}

.sn-rcpt-card-head svg {
    color: #0f172a;
    flex-shrink: 0;
}

.sn-rcpt-user-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 13px;
    margin-bottom: 3px;
}

.sn-rcpt-addr-text {
    color: #475569;
    font-size: 12px;
    line-height: 1.45;
}

.sn-rcpt-contact-text {
    color: #64748b;
    font-size: 11.5px;
    margin-top: 4px;
}

.sn-rcpt-kv-row {
    display: flex;
    font-size: 12px;
    margin-bottom: 4px;
}

.sn-rcpt-kv-label {
    width: 100px;
    color: #64748b;
    flex-shrink: 0;
}

.sn-rcpt-kv-sep {
    margin-right: 8px;
    color: #64748b;
}

.sn-rcpt-kv-val {
    color: #0f172a;
    font-weight: 600;
    word-break: break-all;
}

/* 4. Products & Summary Section (2-Column Grid) */
.sn-rcpt-main-grid {
    display: grid;
    grid-template-columns: 1fr 310px;
    gap: 20px;
    margin-bottom: 26px;
    align-items: flex-start;
}

.sn-rcpt-table-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #ffffff;
}

.sn-rcpt-table {
    width: 100%;
    border-collapse: collapse;
}

.sn-rcpt-table thead tr {
    background: #0f2942;
    color: #ffffff;
}

.sn-rcpt-table th {
    padding: 12px 14px;
    font-size: 12.5px;
    font-weight: 700;
    letter-spacing: -0.2px;
}

.sn-rcpt-table td {
    padding: 14px;
    font-size: 12.5px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.sn-rcpt-table tbody tr:last-child td {
    border-bottom: none;
}

.sn-rcpt-col-idx {
    text-align: center;
    color: #64748b;
    font-weight: 600;
    width: 40px;
}

.sn-rcpt-col-prod {
    text-align: left;
}

.sn-rcpt-prod-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sn-rcpt-prod-thumb {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    object-fit: cover;
    background: #f8fafc;
    flex-shrink: 0;
}

.sn-rcpt-prod-title {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
    margin-bottom: 2px;
}

.sn-rcpt-prod-specs {
    font-size: 11px;
    color: #64748b;
}

.sn-rcpt-col-price {
    text-align: right;
    font-weight: 600;
    color: #334155;
    width: 105px;
    white-space: nowrap;
}

.sn-rcpt-col-qty {
    text-align: center;
    font-weight: 600;
    color: #0f172a;
    width: 75px;
}

.sn-rcpt-col-total {
    text-align: right;
    font-weight: 700;
    color: #0f172a;
    width: 105px;
    white-space: nowrap;
}

/* Right Side: Order Summary & Shipping Info */
.sn-rcpt-summary-col {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sn-rcpt-summary-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
}

.sn-rcpt-box-title {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 14px 0;
    letter-spacing: -0.3px;
}

.sn-rcpt-sum-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12.5px;
    margin-bottom: 8px;
}

.sn-rcpt-sum-label {
    color: #64748b;
}

.sn-rcpt-sum-val {
    font-weight: 600;
    color: #0f172a;
}

.sn-rcpt-discount-text {
    color: #10b981 !important;
    font-weight: 700;
}

.sn-rcpt-divider {
    height: 1px;
    background: #e2e8f0;
    margin: 12px 0 10px 0;
}

.sn-rcpt-total-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #eef6ff;
    padding: 10px 14px;
    border-radius: 8px;
    margin-top: 6px;
}

.sn-rcpt-total-title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
}

.sn-rcpt-total-amount {
    font-size: 19px;
    font-weight: 900;
    color: #1e3a5f;
}

/* Shipping Info Card */
.sn-rcpt-shipinfo-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 18px;
}

.sn-rcpt-shipinfo-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
}

.sn-rcpt-shipinfo-head svg {
    color: #2563eb;
}

/* 5. Bottom Banner / Footer */
.sn-rcpt-footer-banner {
    border-top: 1px solid #f1f5f9;
    padding-top: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
}

.sn-rcpt-foot-left {
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 420px;
}

.sn-rcpt-heart-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 1.5px solid #bfdbfe;
    background: #eff6ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    flex-shrink: 0;
}

.sn-rcpt-foot-left strong {
    font-size: 13.5px;
    font-weight: 800;
    color: #0f172a;
    display: block;
    margin-bottom: 2px;
}

.sn-rcpt-foot-left span {
    font-size: 11.5px;
    color: #64748b;
    line-height: 1.4;
    display: block;
}

.sn-rcpt-foot-mid {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 11.5px;
    color: #475569;
}

.sn-rcpt-foot-mid-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sn-rcpt-foot-mid-item svg {
    color: #2563eb;
    flex-shrink: 0;
}

.sn-rcpt-foot-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sn-rcpt-qrcode-img {
    width: 58px;
    height: 58px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 3px;
    background: #ffffff;
}

.sn-rcpt-qr-text {
    font-size: 10.5px;
    color: #64748b;
    line-height: 1.35;
    max-width: 95px;
}

/* =========================================================
   RESPONSIVE (SCREEN ONLY) & PRINT SPECIFICATION
   ========================================================= */
@media screen and (max-width: 820px) {
    .sn-rcpt-container {
        padding: 24px 20px;
        margin: 12px auto;
    }
    .sn-rcpt-cards-grid {
        grid-template-columns: 1fr;
    }
    .sn-rcpt-main-grid {
        grid-template-columns: 1fr;
    }
    .sn-rcpt-title-section {
        flex-direction: column;
    }
    .sn-rcpt-title-card {
        width: 100%;
        min-width: auto;
    }
    .sn-rcpt-header-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    .sn-rcpt-trust-badges {
        gap: 16px;
    }
    .sn-rcpt-footer-banner {
        flex-direction: column;
        align-items: flex-start;
    }
}

@page {
    size: A4 portrait;
    margin: 8mm 10mm;
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
        font-size: 11pt !important;
    }

    /* Reset all AdminLTE wrappers & sidebars */
    .wrapper,
    .content-wrapper,
    .right-side,
    .content,
    .page {
        margin: 0 !important;
        margin-left: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        border: none !important;
        box-shadow: none !important;
        width: 100% !important;
        max-width: 100% !important;
        min-height: auto !important;
        position: static !important;
        overflow: visible !important;
        float: none !important;
    }

    /* Hide chrome, navigation & buttons */
    .no-print,
    .main-header,
    .main-sidebar,
    .main-footer,
    .content-header,
    .breadcrumb,
    .btn,
    .receipt-action-bar,
    .sn-nav-action-bar,
    .sn-mobile-bottom-dock,
    .modal,
    .modal-backdrop {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Receipt container: Clean borderless full-width presentation */
    .sn-rcpt-container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 4mm 0 !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        background: #ffffff !important;
    }

    /* Preserve 2-column header */
    .sn-rcpt-header-bar {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding-bottom: 12px !important;
        border-bottom: 2px solid #0f2942 !important;
    }

    .sn-rcpt-trust-badges {
        display: flex !important;
        flex-direction: row !important;
        gap: 16px !important;
    }

    /* Preserve Title and Order Status Card side-by-side */
    .sn-rcpt-title-section {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        margin: 14px 0 16px 0 !important;
    }

    .sn-rcpt-title-card {
        min-width: 280px !important;
        padding: 10px 14px !important;
        background: #f0f7ff !important;
        border: 1px solid #bfdbfe !important;
    }

    /* Preserve 3-Column Address & Payment Cards */
    .sn-rcpt-cards-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 12px !important;
        margin-bottom: 16px !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .sn-rcpt-info-card {
        padding: 10px 12px !important;
        border: 1px solid #e2e8f0 !important;
        background: #f8fafc !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    /* Preserve 2-Column Product Table & Calculations */
    .sn-rcpt-main-grid {
        display: grid !important;
        grid-template-columns: 1fr 270px !important;
        gap: 16px !important;
        margin-bottom: 16px !important;
        align-items: flex-start !important;
    }

    .sn-rcpt-table-wrap {
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
    }

    .sn-rcpt-table thead tr {
        background: #0f2942 !important;
        color: #ffffff !important;
    }

    .sn-rcpt-table th,
    .sn-rcpt-table td {
        padding: 8px 10px !important;
        font-size: 11px !important;
    }

    .sn-rcpt-table tr {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .sn-rcpt-prod-thumb {
        width: 36px !important;
        height: 36px !important;
    }

    .sn-rcpt-summary-box,
    .sn-rcpt-shipinfo-box {
        padding: 10px 14px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        background: #f8fafc !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    /* Preserve Horizontal Footer Banner */
    .sn-rcpt-footer-banner {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
        border-top: 1px solid #cbd5e1 !important;
        padding-top: 12px !important;
        margin-top: 14px !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>

<!-- MAIN PIXEL-PERFECT RECEIPT SHEET -->
<div class="sn-rcpt-container" id="pixelPerfectReceiptSheet">

    <!-- 1. Top Header Bar -->
    <div class="sn-rcpt-header-bar">
        <!-- Store Brand / Custom Admin Logo -->
        <div class="sn-rcpt-brand-col">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" 
                     alt="<?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>" 
                     class="sn-rcpt-logo-img"
                     onerror="this.style.display='none'; document.getElementById('snRcptFallbackLogo').style.display='flex';">
            <?php endif; ?>

            <div class="sn-rcpt-fallback-brand" id="snRcptFallbackLogo" style="<?= !empty($logoUrl) ? 'display:none;' : '' ?>">
                <div class="sn-rcpt-brand-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                </div>
                <div>
                    <span class="sn-rcpt-brand-title"><?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="sn-rcpt-brand-subtitle">Better Products &nbsp; Better Life</div>
        </div>

        <!-- 3 Trust Badges -->
        <div class="sn-rcpt-trust-badges">
            <div class="sn-rcpt-badge-item">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
                <div class="sn-rcpt-badge-text">
                    <strong>Fast & Reliable</strong>
                    <span>Delivery</span>
                </div>
            </div>

            <div class="sn-rcpt-badge-item">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <polyline points="9 12 11 14 15 10"></polyline>
                </svg>
                <div class="sn-rcpt-badge-text">
                    <strong>100% Genuine</strong>
                    <span>Products</span>
                </div>
            </div>

            <div class="sn-rcpt-badge-item">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

    <!-- 2. Title Section & Order Status Card -->
    <div class="sn-rcpt-title-section">
        <div class="sn-rcpt-title-left">
            <div class="sn-rcpt-title-icon-wrap">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <line x1="10" y1="9" x2="8" y2="9"></line>
                </svg>
            </div>
            <div class="sn-rcpt-title-texts">
                <h1>Order Receipt</h1>
                <p class="t-sub1">Thank you for shopping with <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>!</p>
                <p class="t-sub2">Your order has been received and is being processed.</p>
            </div>
        </div>

        <div class="sn-rcpt-title-card">
            <div class="sn-rcpt-card-orderno">Order <?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sn-rcpt-meta-row">
                <span class="sn-rcpt-meta-label">Order Date &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</span>
                <span class="sn-rcpt-meta-val"><?= htmlspecialchars($formattedOrderDate, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="sn-rcpt-meta-row">
                <span class="sn-rcpt-meta-label">Payment Status :</span>
                <span class="sn-rcpt-meta-val">
                    <span class="sn-status-pill <?= $payStatusClass ?>"><?= $payStatusLabel ?></span>
                </span>
            </div>
            <div class="sn-rcpt-meta-row">
                <span class="sn-rcpt-meta-label">Order Status &nbsp;&nbsp;&nbsp;&nbsp;:</span>
                <span class="sn-rcpt-meta-val">
                    <span class="sn-status-pill <?= $shipStatusClass ?>"><?= $shipStatusLabel ?></span>
                </span>
            </div>
        </div>
    </div>

    <!-- 3. Three-Column Structured Cards -->
    <div class="sn-rcpt-cards-grid">
        <!-- Billing Address -->
        <div class="sn-rcpt-info-card">
            <div class="sn-rcpt-card-head">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Billing Address</span>
            </div>
            <div class="sn-rcpt-user-name"><?= htmlspecialchars($custName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sn-rcpt-addr-text">
                <?= nl2br(htmlspecialchars($billStreet, ENT_QUOTES, 'UTF-8')) ?><br>
                <?= htmlspecialchars($billCity . ', ' . $billCountry, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="sn-rcpt-contact-text">Phone: <?= htmlspecialchars($custPhone, ENT_QUOTES, 'UTF-8') ?></div>
            <?php if (!empty($custEmail)): ?>
                <div class="sn-rcpt-contact-text">Email: <?= htmlspecialchars($custEmail, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <!-- Shipping Address -->
        <div class="sn-rcpt-info-card">
            <div class="sn-rcpt-card-head">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
                <span>Shipping Address</span>
            </div>
            <div class="sn-rcpt-user-name"><?= htmlspecialchars($shipName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sn-rcpt-addr-text">
                <?= nl2br(htmlspecialchars($shipStreet, ENT_QUOTES, 'UTF-8')) ?><br>
                <?= htmlspecialchars($shipCity . ', ' . $shipCountry, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="sn-rcpt-contact-text">Phone: <?= htmlspecialchars($shipPhone, ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <!-- Payment Method -->
        <div class="sn-rcpt-info-card">
            <div class="sn-rcpt-card-head">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
                <span>Payment Method</span>
            </div>
            <div class="sn-rcpt-kv-row">
                <span class="sn-rcpt-kv-label">Method</span>
                <span class="sn-rcpt-kv-sep">:</span>
                <span class="sn-rcpt-kv-val"><?= htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="sn-rcpt-kv-row">
                <span class="sn-rcpt-kv-label">Transaction ID</span>
                <span class="sn-rcpt-kv-sep">:</span>
                <span class="sn-rcpt-kv-val" style="font-family:monospace;"><?= htmlspecialchars($txnid, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="sn-rcpt-kv-row">
                <span class="sn-rcpt-kv-label">Paid Amount</span>
                <span class="sn-rcpt-kv-sep">:</span>
                <span class="sn-rcpt-kv-val">&#2547; <?= number_format($totalAmount) ?></span>
            </div>
            <div class="sn-rcpt-kv-row">
                <span class="sn-rcpt-kv-label">Paid Date</span>
                <span class="sn-rcpt-kv-sep">:</span>
                <span class="sn-rcpt-kv-val"><?= htmlspecialchars($formattedPaidDate, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>

    <!-- 4. Products Table & Summary Section -->
    <div class="sn-rcpt-main-grid">
        <!-- Products Table -->
        <div class="sn-rcpt-table-wrap">
            <table class="sn-rcpt-table">
                <thead>
                    <tr>
                        <th class="sn-rcpt-col-idx">#</th>
                        <th class="sn-rcpt-col-prod">Product</th>
                        <th class="sn-rcpt-col-price">Price</th>
                        <th class="sn-rcpt-col-qty">Quantity</th>
                        <th class="sn-rcpt-col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itemsList)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:30px; color:#64748b;">No products found in this order.</td>
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
                            
                            $specs = [];
                            if (!empty($it['size'])) $specs[] = 'Size: ' . $it['size'];
                            if (!empty($it['color'])) $specs[] = 'Color: ' . $it['color'];
                            $specsText = !empty($specs) ? implode(' | ', $specs) : 'Standard Product';
                        ?>
                            <tr>
                                <td class="sn-rcpt-col-idx"><?= $rowNum++ ?></td>
                                <td class="sn-rcpt-col-prod">
                                    <div class="sn-rcpt-prod-cell">
                                        <?php if (!empty($photoUrl)): ?>
                                            <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" 
                                                 alt="<?= htmlspecialchars($it['product_name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?>" 
                                                 class="sn-rcpt-prod-thumb"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <div>
                                            <div class="sn-rcpt-prod-title"><?= htmlspecialchars($it['product_name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="sn-rcpt-prod-specs"><?= htmlspecialchars($specsText, ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="sn-rcpt-col-price">&#2547; <?= number_format($unitPrice) ?></td>
                                <td class="sn-rcpt-col-qty"><?= $qty ?></td>
                                <td class="sn-rcpt-col-total">&#2547; <?= number_format($lineTotal) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Right Side: Order Summary & Shipping Info -->
        <div class="sn-rcpt-summary-col">
            <!-- Order Summary -->
            <div class="sn-rcpt-summary-box">
                <h3 class="sn-rcpt-box-title">Order Summary</h3>
                <div class="sn-rcpt-sum-row">
                    <span class="sn-rcpt-sum-label">Subtotal</span>
                    <span class="sn-rcpt-sum-val">&#2547; <?= number_format($subtotal) ?></span>
                </div>
                <div class="sn-rcpt-sum-row">
                    <span class="sn-rcpt-sum-label">Shipping Fee</span>
                    <span class="sn-rcpt-sum-val">&#2547; <?= number_format($shippingCost) ?></span>
                </div>
                <div class="sn-rcpt-sum-row">
                    <span class="sn-rcpt-sum-label">Discount</span>
                    <span class="sn-rcpt-sum-val sn-rcpt-discount-text">- &#2547; <?= number_format($couponDiscount) ?></span>
                </div>
                <div class="sn-rcpt-divider"></div>
                <div class="sn-rcpt-total-banner">
                    <span class="sn-rcpt-total-title">Total</span>
                    <span class="sn-rcpt-total-amount">&#2547; <?= number_format($totalAmount) ?></span>
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="sn-rcpt-shipinfo-box">
                <div class="sn-rcpt-shipinfo-head">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                    <span>Shipping Information</span>
                </div>
                <div class="sn-rcpt-kv-row">
                    <span class="sn-rcpt-kv-label" style="width:110px;">Shipping Partner</span>
                    <span class="sn-rcpt-kv-sep">:</span>
                    <span class="sn-rcpt-kv-val"><?= htmlspecialchars($shippingPartner, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="sn-rcpt-kv-row">
                    <span class="sn-rcpt-kv-label" style="width:110px;">Tracking Number</span>
                    <span class="sn-rcpt-kv-sep">:</span>
                    <span class="sn-rcpt-kv-val" style="font-family:monospace;"><?= htmlspecialchars($trackingNumber, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="sn-rcpt-kv-row">
                    <span class="sn-rcpt-kv-label" style="width:110px;">Estimated Delivery</span>
                    <span class="sn-rcpt-kv-sep">:</span>
                    <span class="sn-rcpt-kv-val"><?= htmlspecialchars($estDelivery, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Bottom Banner / Footer -->
    <div class="sn-rcpt-footer-banner">
        <!-- Left: Heart & Thank You Message -->
        <div class="sn-rcpt-foot-left">
            <div class="sn-rcpt-heart-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
            </div>
            <div>
                <strong>Thank you for choosing <?= htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8') ?>!</strong>
                <span>We hope you love your purchase. If you have any questions, feel free to contact us.</span>
            </div>
        </div>

        <!-- Middle: Contact Details -->
        <div class="sn-rcpt-foot-mid">
            <div class="sn-rcpt-foot-mid-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="sn-rcpt-foot-mid-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <span><?= htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="sn-rcpt-foot-mid-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
                <span><?= htmlspecialchars($websiteHost, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <!-- Right: QR Code for Verification -->
        <div class="sn-rcpt-foot-right">
            <img src="<?= htmlspecialchars($qrCodeUrl, ENT_QUOTES, 'UTF-8') ?>" 
                 alt="Scan to verify order" 
                 class="sn-rcpt-qrcode-img">
            <div class="sn-rcpt-qr-text">Scan this QR code to verify your order</div>
        </div>
    </div>

</div>
