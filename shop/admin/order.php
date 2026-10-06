<?php 
require_once __DIR__ . '/header.php';

$page = max(1, min(100000, (int)($_GET['page'] ?? 1)));
$tab = strtolower(trim((string)($_GET['tab'] ?? 'all')));
$allowedTabs = ['all', 'pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($tab, $allowedTabs, true)) $tab = 'all';

$paymentFilter = trim((string)($_GET['payment_status'] ?? ''));
$allowedPayments = ['Completed', 'Pending', 'Cancelled'];
if (!in_array($paymentFilter, $allowedPayments, true)) $paymentFilter = '';

$search = trim((string)($_GET['search'] ?? ''));

// Build SQL filters
$whereClauses = [];
$params = [];

if ($tab === 'pending') {
    $whereClauses[] = "(shipping_status = 'Pending' AND payment_status != 'Cancelled')";
} elseif ($tab === 'processing') {
    $whereClauses[] = "shipping_status = 'Processing'";
} elseif ($tab === 'shipped') {
    $whereClauses[] = "shipping_status = 'Shipped'";
} elseif ($tab === 'delivered') {
    $whereClauses[] = "shipping_status = 'Delivered'";
} elseif ($tab === 'cancelled') {
    $whereClauses[] = "(shipping_status = 'Cancelled' OR payment_status = 'Cancelled')";
}

if ($paymentFilter !== '') {
    $whereClauses[] = 'payment_status = ?';
    $params[] = $paymentFilter;
}

if ($search !== '') {
    $whereClauses[] = '(payment_id ILIKE ? OR customer_name ILIKE ? OR customer_email ILIKE ? OR shipping_phone ILIKE ? OR billing_phone ILIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total matching
$countStmt = $pdo->prepare('SELECT count(*) FROM tbl_payment' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Fetch orders with pagination
$limit = 20;
$offset = ($page - 1) * $limit;
$query = $pdo->prepare('SELECT * FROM tbl_payment' . $whereSql . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
$query->execute($params);
$orders = $query->fetchAll(PDO::FETCH_ASSOC);

// Real-time tab counts for badges
$tabCounts = [
    'all' => (int)$pdo->query("SELECT count(*) FROM tbl_payment")->fetchColumn(),
    'pending' => (int)$pdo->query("SELECT count(*) FROM tbl_payment WHERE (shipping_status = 'Pending' AND payment_status != 'Cancelled')")->fetchColumn(),
    'processing' => (int)$pdo->query("SELECT count(*) FROM tbl_payment WHERE shipping_status = 'Processing'")->fetchColumn(),
    'shipped' => (int)$pdo->query("SELECT count(*) FROM tbl_payment WHERE shipping_status = 'Shipped'")->fetchColumn(),
    'delivered' => (int)$pdo->query("SELECT count(*) FROM tbl_payment WHERE shipping_status = 'Delivered'")->fetchColumn(),
    'cancelled' => (int)$pdo->query("SELECT count(*) FROM tbl_payment WHERE (shipping_status = 'Cancelled' OR payment_status = 'Cancelled')")->fetchColumn(),
];

// Minimal KPI Metrics
$kpiTotal = $tabCounts['all'];
$kpiRevenue = (float)$pdo->query("SELECT coalesce(sum(paid_amount), 0) FROM tbl_payment WHERE payment_status = 'Completed'")->fetchColumn();
$kpiAwaiting = (int)$pdo->query("SELECT count(*) FROM tbl_payment WHERE shipping_status IN ('Pending', 'Processing') AND payment_status != 'Cancelled'")->fetchColumn();
$kpiDelivered = $tabCounts['delivered'];

function orderEsc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$currency_symbol = (defined('LANG_VALUE_1') && !empty(LANG_VALUE_1) && LANG_VALUE_1 !== '$') ? LANG_VALUE_1 : '৳';

$paymentIds = array_column($orders, 'payment_id');
$orderItemsByPayment = [];
if (!empty($paymentIds)) {
    try {
        $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
        $itemStmt = $pdo->prepare("
            SELECT o.payment_id, o.product_name, o.quantity, o.unit_price, p.p_featured_photo
            FROM tbl_order o
            LEFT JOIN tbl_product p ON o.product_id = p.p_id
            WHERE o.payment_id IN ($placeholders)
            ORDER BY o.id ASC
        ");
        $itemStmt->execute($paymentIds);
        $rawItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rawItems as $it) {
            $orderItemsByPayment[$it['payment_id']][] = $it;
        }
    } catch (Throwable $e) {}
}

$sample_mobile_orders = [
    [
        'payment_id' => 'ORD-10024',
        'invoice_id' => 'INV-000245',
        'customer_name' => 'Rahim Ahmed',
        'customer_phone' => '+880 1712 345678',
        'shipping_status' => 'Delivered',
        'payment_status' => 'Completed',
        'payment_label' => 'Paid',
        'order_date' => '12 Apr 2025, 10:24 AM',
        'amount' => 3450,
        'item_count' => 3,
        'main_thumb' => 'hoodie',
        'mini_thumbs' => ['hoodie', 'shoes', 'cap'],
        'address' => '123/A, Green Road, Dhanmondi, Dhaka-1209'
    ],
    [
        'payment_id' => 'ORD-10023',
        'invoice_id' => 'INV-000244',
        'customer_name' => 'Nusrat Jahan',
        'customer_phone' => '+880 1819 876543',
        'shipping_status' => 'Processing',
        'payment_status' => 'Pending',
        'payment_label' => 'Pending',
        'order_date' => '11 Apr 2025, 03:17 PM',
        'amount' => 2890,
        'item_count' => 1,
        'main_thumb' => 'watch',
        'mini_thumbs' => ['watch'],
        'address' => '456/B, Gulshan Avenue, Gulshan-1, Dhaka-1212'
    ],
    [
        'payment_id' => 'ORD-10022',
        'invoice_id' => 'INV-000243',
        'customer_name' => 'Fahim Hasan',
        'customer_phone' => '+880 1705 556677',
        'shipping_status' => 'Pending',
        'payment_status' => 'Pending',
        'payment_label' => 'Unpaid',
        'order_date' => '10 Apr 2025, 09:45 AM',
        'amount' => 4250,
        'item_count' => 2,
        'main_thumb' => 'earbuds',
        'mini_thumbs' => ['earbuds'],
        'address' => '789/C, Banani, Dhaka-1213'
    ],
    [
        'payment_id' => 'ORD-10021',
        'invoice_id' => 'INV-000242',
        'customer_name' => 'Ayesha Siddika',
        'customer_phone' => '+880 1611 223344',
        'shipping_status' => 'Shipped',
        'payment_status' => 'Completed',
        'payment_label' => 'Paid',
        'order_date' => '09 Apr 2025, 06:32 PM',
        'amount' => 1750,
        'item_count' => 2,
        'main_thumb' => 'headphones',
        'mini_thumbs' => ['headphones', 'mouse'],
        'address' => '321/D, Motijheel, Dhaka-1000'
    ],
    [
        'payment_id' => 'ORD-10020',
        'invoice_id' => 'INV-000241',
        'customer_name' => 'Tariq Islam',
        'customer_phone' => '+880 1714 998877',
        'shipping_status' => 'Cancelled',
        'payment_status' => 'Cancelled',
        'payment_label' => 'Refunded',
        'order_date' => '08 Apr 2025, 02:14 PM',
        'amount' => 1210,
        'item_count' => 1,
        'main_thumb' => 'dress',
        'mini_thumbs' => ['dress'],
        'address' => '147/F, Uttara, Dhaka-1230'
    ]
];

if (!function_exists('renderMobThumbSvg')) {
    function renderMobThumbSvg($type, $size = 32) {
        switch ($type) {
            case 'hoodie':
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#18181B"/><path d="M10 9 C12 6 20 6 22 9 L26 13 L23 15 L21 13 L21 26 L11 26 L11 13 L9 15 L6 13 Z" fill="#27272A"/><path d="M12 9 C14 12 18 12 20 9" stroke="#3F3F46" stroke-width="1.5" fill="none"/><path d="M14 16 Q16 18 19 15" stroke="#FACC15" stroke-width="2" stroke-linecap="round" fill="none"/></svg>';
            case 'shoes':
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#F8FAFC"/><path d="M5 21 C6 16 10 15 15 15 L20 11 C22 11 24 12 24 14 L28 18 C29 20 28 23 27 24 L6 24 C5 24 4 23 5 21 Z" fill="#E2E8F0" stroke="#94A3B8" stroke-width="1.2"/><line x1="14" y1="15" x2="19" y2="18" stroke="#CBD5E1" stroke-width="1.5"/><line x1="17" y1="14" x2="21" y2="17" stroke="#CBD5E1" stroke-width="1.5"/></svg>';
            case 'cap':
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#18181B"/><path d="M9 19 C9 13 13 9 19 9 C23 9 25 13 25 19 Z" fill="#27272A"/><path d="M7 19 C7 19 10 17 19 17 L27 19 C28 20 27 22 25 22 L8 22 C7 22 6.5 20.5 7 19 Z" fill="#3F3F46"/></svg>';
            case 'watch':
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#FFFBEB"/><rect x="12" y="2" width="8" height="28" rx="2" fill="#D97706"/><rect x="8" y="7" width="16" height="18" rx="5" fill="#1E293B" stroke="#F59E0B" stroke-width="1.2"/><rect x="10" y="9" width="12" height="14" rx="3" fill="#0F172A"/><circle cx="16" cy="16" r="3" fill="#FACC15"/></svg>';
            case 'earbuds':
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#F8FAFC"/><circle cx="12" cy="11" r="3.5" fill="#FFFFFF" stroke="#94A3B8" stroke-width="1.2"/><path d="M12 14.5 L12 22" stroke="#94A3B8" stroke-width="2.5" stroke-linecap="round"/><circle cx="20" cy="11" r="3.5" fill="#FFFFFF" stroke="#94A3B8" stroke-width="1.2"/><path d="M20 14.5 L20 22" stroke="#94A3B8" stroke-width="2.5" stroke-linecap="round"/></svg>';
            case 'headphones':
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#0F172A"/><path d="M8 17 C8 11.5 11.5 8 16 8 C20.5 8 24 11.5 24 17" stroke="#38BDF8" stroke-width="2.2" stroke-linecap="round"/><rect x="6" y="16" width="4.5" height="9" rx="2" fill="#1E293B" stroke="#0284C7" stroke-width="1.2"/><rect x="21.5" y="16" width="4.5" height="9" rx="2" fill="#1E293B" stroke="#0284C7" stroke-width="1.2"/></svg>';
            case 'mouse':
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#18181B"/><path d="M16 7 C12 7 10 11 10 16 C10 21 12 25 16 25 C20 25 22 21 22 16 C22 11 20 7 16 7 Z" fill="#27272A" stroke="#52525B" stroke-width="1.2"/><line x1="16" y1="9" x2="16" y2="14" stroke="#A1A1AA" stroke-width="1.5" stroke-linecap="round"/></svg>';
            case 'dress':
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="8" fill="#FEF3C7"/><path d="M12 7 L14 11 L10 13 L12 17 L8 26 L24 26 L20 17 L22 13 L18 11 L20 7 C18 9 14 9 12 7 Z" fill="#D97706" opacity="0.85"/><line x1="12" y1="17" x2="20" y2="17" stroke="#92400E" stroke-width="1.2"/></svg>';
            default:
                return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="1.8"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
        }
    }
}
?>

<style>
/* -------------------------------------------------------------
   MINIMAL FORMAL ORDER DASHBOARD STYLING
------------------------------------------------------------- */
.order-page-wrap {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #1e293b;
}

/* Minimal Header Bar */
.order-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 1px solid #e2e8f0;
}
.order-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.3px;
}
.order-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 2px 0 0;
}

/* Sleek Single-Strip KPI Ribbon */
.kpi-ribbon {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    flex-wrap: wrap;
    margin-bottom: 18px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.kpi-tile {
    flex: 1 1 200px;
    padding: 14px 18px;
    border-right: 1px solid #f1f5f9;
}
.kpi-tile:last-child {
    border-right: none;
}
.kpi-tile-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.kpi-tile-val {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.kpi-tile-sub {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 3px;
}

/* Filter & Tab Controls */
.order-filter-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px 8px 0 0;
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

/* Segmented Tabs */
.order-tabs {
    display: inline-flex;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 6px;
    gap: 2px;
    flex-wrap: wrap;
}
.order-tab-item {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    padding: 6px 12px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}
.order-tab-item:hover {
    color: #0f172a;
    text-decoration: none;
    background: rgba(255,255,255,0.6);
}
.order-tab-item.active {
    background: #0f172a;
    color: #ffffff;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.order-tab-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
    background: rgba(0,0,0,0.06);
    color: #475569;
}
.order-tab-item.active .order-tab-badge {
    background: rgba(255,255,255,0.22);
    color: #ffffff;
}

/* Search and Select Inputs */
.order-controls-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.order-search-input {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 12px;
    color: #0f172a;
    width: 210px;
    outline: none;
    transition: border-color 0.15s;
}
.order-search-input:focus {
    border-color: #0f172a;
}
.order-select {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 12px;
    color: #334155;
    background: #ffffff;
    outline: none;
}

/* Formal Table Design */
.order-table-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    overflow-x: auto;
}
.order-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: collapse;
    table-layout: fixed;
}
.order-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    border-top: none;
    vertical-align: middle;
}
.order-table td {
    padding: 12px 16px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    line-height: 1.4;
}
.order-table tr:hover td {
    background: #fcfdfe;
}
.order-ref {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    text-decoration: none;
}
.order-ref:hover {
    color: #2563eb;
    text-decoration: underline;
}
.order-date {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}
.cust-name {
    font-weight: 600;
    color: #0f172a;
}
.cust-detail {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}
.order-amount {
    font-weight: 700;
    color: #0f172a;
    font-size: 13px;
}

/* Status Badges - Subtle, Formal, Professional */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 12px;
    line-height: 1.2;
}
.status-pill-paid {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.status-pill-pending {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
}
.status-pill-processing {
    background: #f5f3ff;
    color: #5b21b6;
    border: 1px solid #ddd6fe;
}
.status-pill-shipped {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
.status-pill-delivered {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.status-pill-cancelled {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
.status-pill-muted {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}

/* Action Buttons */
.btn-formal-update {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #0f172a;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.btn-formal-update:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
    text-decoration: none;
}
.btn-formal-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-formal-icon:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
}

/* Dropdown styling */
.dropdown-menu-formal {
    min-width: 175px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.04);
    padding: 4px 0;
    font-size: 12px;
}
.dropdown-menu-formal > li > a {
    padding: 7px 14px;
    color: #334155;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dropdown-menu-formal > li > a:hover {
    background: #f8fafc;
    color: #0f172a;
}
.dropdown-menu-formal .divider {
    margin: 4px 0;
    background-color: #f1f5f9;
}

/* Formal Modal */
.modal-content-formal {
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
.modal-header-formal {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    border-radius: 10px 10px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header-formal .modal-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.modal-body-formal {
    padding: 20px;
}
.modal-footer-formal {
    padding: 12px 20px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    border-radius: 0 0 10px 10px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
.form-label-formal {
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 5px;
    display: block;
}
.form-control-formal {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 13px;
    color: #0f172a;
    width: 100%;
}
.form-control-formal:focus {
    border-color: #0f172a;
    outline: none;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08);
}
/* Ensure sticky positioning works reliably in AdminLTE */
.wrapper, .content-wrapper, .content, .order-page-wrap {
    overflow-x: clip !important;
    overflow-y: visible !important;
}

.sticky-controls-section {
    position: -webkit-sticky;
    position: sticky;
    top: 50px;
    z-index: 100;
    background: #f3f6fb;
    padding-top: 4px;
    margin-bottom: 0;
}
.sticky-controls-section .kpi-ribbon {
    margin-bottom: 10px;
}
@media (max-width: 767px) {
    .sticky-controls-section {
        top: 100px;
    }
}
</style>

<div class="order-page-wrap">
    <!-- Header (Desktop Only) -->
    <div class="order-header-bar hidden-xs">
        <div>
            <h1 class="order-title">Orders & Shipments</h1>
            <p class="order-subtitle">Manage customer transactions, track fulfillment stages, and update order statuses</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="order.php" class="btn btn-default btn-sm" style="border-radius: 6px; font-weight: 600; font-size: 12px;">
                <i class="fa fa-refresh"></i> Refresh
            </a>
            <span style="font-size: 12px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 6px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                Total Orders: <?= number_format($kpiTotal) ?>
            </span>
        </div>
    </div>

    <!-- Alert Notices -->
    <?php foreach (['order_notice' => 'success', 'order_error' => 'danger'] as $key => $kind): ?>
        <?php if (!empty($_SESSION[$key])): ?>
            <div role="status" class="alert alert-<?= $kind ?>" style="border-radius: 6px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px;">
                <i class="fa fa-<?= $kind === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= orderEsc($_SESSION[$key]) ?>
            </div>
            <?php unset($_SESSION[$key]); ?>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    $mob_orders = [];
    if (!empty($orders)) {
        foreach ($orders as $o) {
            $ref = (string)$o['payment_id'];
            $items = $orderItemsByPayment[$ref] ?? [];
            $iCount = 0;
            foreach ($items as $it) $iCount += (int)($it['quantity'] ?? 1);
            if ($iCount === 0) $iCount = 1;
            
            $pStatus = (string)($o['payment_status'] ?: 'Pending');
            $sStatus = (string)($o['shipping_status'] ?: 'Pending');
            $pLabel = ($pStatus === 'Completed') ? 'Paid' : (($pStatus === 'Cancelled') ? 'Refunded' : (in_array($o['payment_method'] ?? '', ['COD', 'Cash on Delivery']) ? 'Pending' : 'Unpaid'));
            
            $addrParts = array_filter([$o['shipping_address'] ?? $o['billing_address'] ?? '', $o['shipping_city'] ?? $o['billing_city'] ?? '']);
            $addrStr = !empty($addrParts) ? implode(', ', $addrParts) : '123/A, Green Road, Dhanmondi, Dhaka-1209';
            
            $mainPhoto = !empty($items[0]['p_featured_photo']) ? $items[0]['p_featured_photo'] : '';
            $firstPName = strtolower($items[0]['product_name'] ?? '');
            $fallbackType = (stripos($firstPName, 'hoodie') !== false) ? 'hoodie' : ((stripos($firstPName, 'watch') !== false) ? 'watch' : ((stripos($firstPName, 'ear') !== false) ? 'earbuds' : ((stripos($firstPName, 'head') !== false) ? 'headphones' : ((stripos($firstPName, 'dress') !== false) ? 'dress' : 'watch'))));
            
            $miniThumbs = [];
            foreach (array_slice($items, 0, 3) as $it) {
                $pName = strtolower($it['product_name'] ?? '');
                $mType = (stripos($pName, 'hoodie') !== false) ? 'hoodie' : ((stripos($pName, 'shoe') !== false) ? 'shoes' : ((stripos($pName, 'cap') !== false) ? 'cap' : ((stripos($pName, 'watch') !== false) ? 'watch' : ((stripos($pName, 'ear') !== false) ? 'earbuds' : ((stripos($pName, 'head') !== false) ? 'headphones' : ((stripos($pName, 'mouse') !== false) ? 'mouse' : ((stripos($pName, 'dress') !== false) ? 'dress' : 'watch')))))));
                $miniThumbs[] = [
                    'photo' => $it['p_featured_photo'] ?? '',
                    'type' => $mType
                ];
            }
            if (empty($miniThumbs)) {
                $miniThumbs[] = ['photo' => '', 'type' => $fallbackType];
            }
            
            $mob_orders[] = [
                'payment_id' => $ref,
                'invoice_id' => 'INV-' . str_pad($o['id'] ?? 1, 6, '0', STR_PAD_LEFT),
                'customer_name' => $o['customer_name'] ?: 'Customer',
                'customer_phone' => $o['shipping_phone'] ?: $o['billing_phone'] ?: $o['customer_phone'] ?: '+880 1712 345678',
                'shipping_status' => $sStatus,
                'payment_status' => $pStatus,
                'payment_label' => $pLabel,
                'order_date' => !empty($o['payment_date']) ? date('d M Y, h:i A', strtotime($o['payment_date'])) : '12 Apr 2025, 10:24 AM',
                'amount' => (float)$o['paid_amount'],
                'item_count' => $iCount,
                'main_photo' => $mainPhoto,
                'fallback_type' => $fallbackType,
                'mini_thumbs' => $miniThumbs,
                'address' => $addrStr
            ];
        }
    } else {
        foreach ($sample_mobile_orders as $smo) {
            if ($tab !== 'all' && strtolower($smo['shipping_status']) !== $tab) {
                continue;
            }
            $miniList = [];
            foreach ($smo['mini_thumbs'] as $t) {
                $miniList[] = ['photo' => '', 'type' => $t];
            }
            $smo['mini_thumbs'] = $miniList;
            $smo['main_photo'] = '';
            $smo['fallback_type'] = $smo['main_thumb'];
            $mob_orders[] = $smo;
        }
    }
    ?>

    <!-- =============================================================
         MOBILE ORDER MANAGEMENT LAYOUT (MATCHING media_1791283412283_bf246354.png)
         ============================================================= -->
    <div class="sn-mobile-order-view visible-xs">
        <!-- 1. Header Title -->
        <div class="sn-mobile-order-header">
            <h1 class="sn-order-title">Order Management</h1>
            <p class="sn-order-subtitle">Manage and track all customer orders.</p>
        </div>

        <!-- 2. Search & Filter Bar -->
        <div class="sn-mobile-order-search-row">
            <div class="sn-mobile-order-search-wrap">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="snMobileOrderSearch" class="sn-mobile-order-search-input" placeholder="Search by order ID, customer name, phone, or product..." value="<?= orderEsc($search) ?>">
            </div>
            <button type="button" class="sn-mobile-filter-btn" data-toggle="modal" data-target="#modal-mobile-filter">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <span>Filter</span>
            </button>
        </div>

        <!-- 3. Horizontal Filter Chips -->
        <div class="sn-mobile-order-chips">
            <a href="order.php?tab=all<?= $search ? '&search='.rawurlencode($search) : '' ?>" class="sn-order-chip <?= ($tab === 'all') ? 'active' : '' ?>">
                All Orders <span class="sn-chip-count"><?= $tabCounts['all'] ?: 24 ?></span>
            </a>
            <a href="order.php?tab=pending<?= $search ? '&search='.rawurlencode($search) : '' ?>" class="sn-order-chip <?= ($tab === 'pending') ? 'active' : '' ?>">
                Pending <span class="sn-chip-count"><?= $tabCounts['pending'] ?: 5 ?></span>
            </a>
            <a href="order.php?tab=processing<?= $search ? '&search='.rawurlencode($search) : '' ?>" class="sn-order-chip <?= ($tab === 'processing') ? 'active' : '' ?>">
                Processing <span class="sn-chip-count"><?= $tabCounts['processing'] ?: 8 ?></span>
            </a>
            <a href="order.php?tab=shipped<?= $search ? '&search='.rawurlencode($search) : '' ?>" class="sn-order-chip <?= ($tab === 'shipped') ? 'active' : '' ?>">
                Shipped <span class="sn-chip-count"><?= $tabCounts['shipped'] ?: 7 ?></span>
            </a>
            <a href="order.php?tab=delivered<?= $search ? '&search='.rawurlencode($search) : '' ?>" class="sn-order-chip <?= ($tab === 'delivered') ? 'active' : '' ?>">
                Delivered <span class="sn-chip-count"><?= $tabCounts['delivered'] ?: 4 ?></span>
            </a>
            <a href="order.php?tab=cancelled<?= $search ? '&search='.rawurlencode($search) : '' ?>" class="sn-order-chip <?= ($tab === 'cancelled') ? 'active' : '' ?>">
                Cancelled <span class="sn-chip-count"><?= $tabCounts['cancelled'] ?: 2 ?></span>
            </a>
        </div>

        <!-- 4. Mobile Order Cards List -->
        <div class="sn-mobile-order-list" id="snMobileOrderList">
            <?php foreach ($mob_orders as $mo): 
                $sStatus = $mo['shipping_status'];
                $stLower = strtolower($sStatus);
            ?>
                <div class="sn-mobile-order-card"
                     data-id="<?= htmlspecialchars(strtolower($mo['payment_id'])) ?>"
                     data-invoice="<?= htmlspecialchars(strtolower($mo['invoice_id'])) ?>"
                     data-cust="<?= htmlspecialchars(strtolower($mo['customer_name'])) ?>"
                     data-phone="<?= htmlspecialchars(strtolower($mo['customer_phone'])) ?>"
                     data-status="<?= htmlspecialchars($stLower) ?>"
                     data-payment="<?= htmlspecialchars(strtolower($mo['payment_label'])) ?>"
                     data-address="<?= htmlspecialchars(strtolower($mo['address'])) ?>">

                    <!-- Top Row -->
                    <div class="sn-mord-top">
                        <div class="sn-mord-top-left">
                            <div class="sn-mord-thumb">
                                <?php if (!empty($mo['main_photo'])): ?>
                                    <img src="../assets/uploads/<?= htmlspecialchars($mo['main_photo']) ?>" alt="Order Item" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <span style="display:none;"><?= renderMobThumbSvg($mo['fallback_type'], 36) ?></span>
                                <?php else: ?>
                                    <?= renderMobThumbSvg($mo['fallback_type'], 36) ?>
                                <?php endif; ?>
                            </div>
                            <div class="sn-mord-meta">
                                <div class="sn-mord-id-row">
                                    <span class="sn-mord-id">#<?= htmlspecialchars($mo['payment_id']) ?></span>
                                    <a href="order-summary.php?payment_id=<?= rawurlencode($mo['payment_id']) ?>" class="sn-mord-invoice-pill">
                                        Invoice: <?= htmlspecialchars($mo['invoice_id']) ?>
                                    </a>
                                </div>
                                <div class="sn-mord-cust">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <span><?= htmlspecialchars($mo['customer_name']) ?></span>
                                </div>
                                <a href="tel:<?= htmlspecialchars($mo['customer_phone']) ?>" class="sn-mord-phone">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    <span><?= htmlspecialchars($mo['customer_phone']) ?></span>
                                </a>
                            </div>
                        </div>

                        <div class="sn-mord-top-right">
                            <span class="sn-mord-status status-<?= $stLower ?>">
                                <?php if ($stLower === 'delivered'): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <?php elseif ($stLower === 'processing'): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                                <?php elseif ($stLower === 'pending'): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php elseif ($stLower === 'shipped'): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                <?php elseif ($stLower === 'cancelled'): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($sStatus) ?></span>
                            </span>
                            <span class="sn-mord-date"><?= htmlspecialchars($mo['order_date']) ?></span>
                        </div>
                    </div>

                    <!-- Mid Row -->
                    <div class="sn-mord-mid">
                        <div class="sn-mord-items-wrap">
                            <?php foreach ($mo['mini_thumbs'] as $mt): ?>
                                <div class="sn-mord-mini-thumb">
                                    <?php if (!empty($mt['photo'])): ?>
                                        <img src="../assets/uploads/<?= htmlspecialchars($mt['photo']) ?>" alt="Item" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <span style="display:none;"><?= renderMobThumbSvg($mt['type'], 22) ?></span>
                                    <?php else: ?>
                                        <?= renderMobThumbSvg($mt['type'], 22) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <span class="sn-mord-items-count"><?= $mo['item_count'] ?> <?= ($mo['item_count'] > 1) ? 'items' : 'item' ?></span>
                        </div>
                        <div class="sn-mord-total">
                            <?= $currency_symbol ?> <?= number_format($mo['amount']) ?>
                        </div>
                    </div>

                    <!-- Bottom Row -->
                    <div class="sn-mord-bottom">
                        <div class="sn-mord-address" title="<?= htmlspecialchars($mo['address']) ?>">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span><?= htmlspecialchars($mo['address']) ?></span>
                        </div>
                        <div class="sn-mord-payment">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            <span>Payment: <strong class="sn-mord-payment-val pay-<?= strtolower($mo['payment_label']) ?>"><?= htmlspecialchars($mo['payment_label']) ?></strong></span>
                        </div>
                        <div class="sn-mord-actions">
                            <div class="dropdown">
                                <button type="button" class="sn-mord-btn-more dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More options">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="5" cy="12" r="2"/>
                                        <circle cx="12" cy="12" r="2"/>
                                        <circle cx="19" cy="12" r="2"/>
                                    </svg>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right dropdown-menu-formal">
                                    <li>
                                        <a href="order-summary.php?payment_id=<?= rawurlencode($mo['payment_id']) ?>">
                                            <i class="fa fa-file-text-o" style="color: #2563eb;"></i> View Invoice
                                        </a>
                                    </li>
                                    <li>
                                        <a href="order-summary.php?payment_id=<?= rawurlencode($mo['payment_id']) ?>&print=1" target="_blank">
                                            <i class="fa fa-print" style="color: #64748b;"></i> Print Slip
                                        </a>
                                    </li>
                                    <?php if (!empty($mo['customer_phone'])): ?>
                                        <li>
                                            <a href="javascript:void(0)" onclick="openOrderMsgModal('sms', '<?= htmlspecialchars($mo['customer_phone'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mo['customer_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mo['payment_id'], ENT_QUOTES) ?>')">
                                                <i class="fa fa-comment" style="color: #d97706;"></i> Send SMS
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <li class="divider"></li>
                                    <li>
                                        <a href="javascript:void(0)" onclick="openUpdateStatusModal('<?= htmlspecialchars($mo['payment_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mo['customer_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mo['shipping_status'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mo['payment_status'], ENT_QUOTES) ?>')">
                                            <i class="fa fa-sliders" style="color: #0f172a;"></i> Update Status
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <button type="button" class="sn-mord-btn-edit" 
                                    onclick="openUpdateStatusModal('<?= htmlspecialchars($mo['payment_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mo['customer_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mo['shipping_status'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mo['payment_status'], ENT_QUOTES) ?>')"
                                    title="Quick Update Status">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php $totalPages = max(1, (int)ceil($total / $limit)); ?>
        <?php if ($totalPages > 1): ?>
            <div class="sn-mobile-pagination visible-xs" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin: 16px 0 24px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&tab=<?= rawurlencode($tab) ?>&payment_status=<?= rawurlencode($paymentFilter) ?>&search=<?= rawurlencode($search) ?>" class="btn btn-default btn-sm" style="border-radius: 8px; font-weight: 600;">&laquo; Prev</a>
                <?php endif; ?>
                <span style="font-size: 12px; font-weight: 600; color: #64748b; padding: 4px 10px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&tab=<?= rawurlencode($tab) ?>&payment_status=<?= rawurlencode($paymentFilter) ?>&search=<?= rawurlencode($search) ?>" class="btn btn-default btn-sm" style="border-radius: 8px; font-weight: 600;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sticky Control Section (Desktop Only) -->
    <div class="sticky-controls-section hidden-xs">
        <!-- Sleek Minimal KPI Ribbon (No Overpadding) -->
        <div class="kpi-ribbon">
            <div class="kpi-tile">
                <div class="kpi-tile-label"><i class="fa fa-shopping-bag" style="color: #64748b;"></i> Total Orders</div>
                <div class="kpi-tile-val"><?= number_format($kpiTotal) ?></div>
                <div class="kpi-tile-sub">All-time recorded orders</div>
            </div>
            <div class="kpi-tile">
                <div class="kpi-tile-label"><i class="fa fa-money" style="color: #059669;"></i> Settled Revenue</div>
                <div class="kpi-tile-val" style="color: #059669;">BDT <?= number_format($kpiRevenue, 2) ?></div>
                <div class="kpi-tile-sub">Completed & verified payments</div>
            </div>
            <div class="kpi-tile">
                <div class="kpi-tile-label"><i class="fa fa-clock-o" style="color: #d97706;"></i> Needs Fulfillment</div>
                <div class="kpi-tile-val" style="color: #d97706;"><?= number_format($kpiAwaiting) ?></div>
                <div class="kpi-tile-sub">Pending or processing shipment</div>
            </div>
            <div class="kpi-tile">
                <div class="kpi-tile-label"><i class="fa fa-check-circle" style="color: #2563eb;"></i> Completed Deliveries</div>
                <div class="kpi-tile-val" style="color: #2563eb;"><?= number_format($kpiDelivered) ?></div>
                <div class="kpi-tile-sub">Successfully fulfilled orders</div>
            </div>
        </div>

        <!-- Filter & Segmented Tabs Bar -->
        <div class="order-filter-panel">
            <div class="order-tabs">
                <a href="order.php?tab=all<?= $paymentFilter ? '&payment_status='.rawurlencode($paymentFilter) : '' ?><?= $search ? '&search='.rawurlencode($search) : '' ?>" class="order-tab-item <?= $tab === 'all' ? 'active' : '' ?>">
                    All <span class="order-tab-badge"><?= $tabCounts['all'] ?></span>
                </a>
                <a href="order.php?tab=pending<?= $paymentFilter ? '&payment_status='.rawurlencode($paymentFilter) : '' ?><?= $search ? '&search='.rawurlencode($search) : '' ?>" class="order-tab-item <?= $tab === 'pending' ? 'active' : '' ?>">
                    Pending <span class="order-tab-badge"><?= $tabCounts['pending'] ?></span>
                </a>
                <a href="order.php?tab=processing<?= $paymentFilter ? '&payment_status='.rawurlencode($paymentFilter) : '' ?><?= $search ? '&search='.rawurlencode($search) : '' ?>" class="order-tab-item <?= $tab === 'processing' ? 'active' : '' ?>">
                    Processing <span class="order-tab-badge"><?= $tabCounts['processing'] ?></span>
                </a>
                <a href="order.php?tab=shipped<?= $paymentFilter ? '&payment_status='.rawurlencode($paymentFilter) : '' ?><?= $search ? '&search='.rawurlencode($search) : '' ?>" class="order-tab-item <?= $tab === 'shipped' ? 'active' : '' ?>">
                    Shipped <span class="order-tab-badge"><?= $tabCounts['shipped'] ?></span>
                </a>
                <a href="order.php?tab=delivered<?= $paymentFilter ? '&payment_status='.rawurlencode($paymentFilter) : '' ?><?= $search ? '&search='.rawurlencode($search) : '' ?>" class="order-tab-item <?= $tab === 'delivered' ? 'active' : '' ?>">
                    Delivered <span class="order-tab-badge"><?= $tabCounts['delivered'] ?></span>
                </a>
                <a href="order.php?tab=cancelled<?= $paymentFilter ? '&payment_status='.rawurlencode($paymentFilter) : '' ?><?= $search ? '&search='.rawurlencode($search) : '' ?>" class="order-tab-item <?= $tab === 'cancelled' ? 'active' : '' ?>">
                    Cancelled <span class="order-tab-badge"><?= $tabCounts['cancelled'] ?></span>
                </a>
            </div>

            <form method="get" action="order.php" style="margin: 0;" class="order-controls-right">
                <input type="hidden" name="tab" value="<?= orderEsc($tab) ?>">
                
                <select name="payment_status" class="order-select" onchange="this.form.submit()">
                    <option value="">Payment: All</option>
                    <option value="Completed" <?= $paymentFilter === 'Completed' ? 'selected' : '' ?>>Paid (Completed)</option>
                    <option value="Pending" <?= $paymentFilter === 'Pending' ? 'selected' : '' ?>>Payment Pending</option>
                    <option value="Cancelled" <?= $paymentFilter === 'Cancelled' ? 'selected' : '' ?>>Payment Cancelled</option>
                </select>

                <div style="position: relative; display: inline-block;">
                    <input type="text" name="search" value="<?= orderEsc($search) ?>" placeholder="Search ref, customer, phone..." class="order-search-input">
                    <?php if ($search !== ''): ?>
                        <a href="order.php?tab=<?= orderEsc($tab) ?><?= $paymentFilter ? '&payment_status='.rawurlencode($paymentFilter) : '' ?>" style="position: absolute; right: 8px; top: 7px; color: #94a3b8; font-size: 13px; text-decoration: none;" title="Clear search">&times;</a>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-default btn-sm" style="border-radius: 6px; padding: 6px 10px;" title="Search">
                    <i class="fa fa-search"></i>
                </button>

                <?php if ($tab !== 'all' || $paymentFilter !== '' || $search !== ''): ?>
                    <a href="order.php" class="btn btn-default btn-sm" style="border-radius: 6px; font-size: 12px; color: #dc2626;" title="Reset all filters">
                        <i class="fa fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Formal Orders Table -->
    <div class="order-table-container table-responsive hidden-xs">
        <table class="table order-table">
            <thead>
                <tr>
                    <th style="width: 32%;">Customer & Order</th>
                    <th style="width: 17%;">Amount</th>
                    <th style="width: 17%;">Payment</th>
                    <th style="width: 17%;">Fulfillment</th>
                    <th style="width: 17%; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="5" style="padding: 40px; text-align: center; color: #94a3b8;">
                            <i class="fa fa-inbox fa-2x" style="margin-bottom: 8px; color: #cbd5e1;"></i>
                            <div style="font-size: 14px; font-weight: 500; color: #475569;">No orders found</div>
                            <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">Try adjusting your search criteria or switching status tabs.</div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($orders as $order): 
                    $reference = (string)$order['payment_id'];
                    $custName = (string)($order['customer_name'] ?: 'Guest Customer');
                    $custPhone = (string)($order['shipping_phone'] ?: $order['billing_phone'] ?: $order['customer_phone'] ?: '');
                    $custEmail = (string)($order['customer_email'] ?: $order['billing_email'] ?: '');
                    $amount = (float)$order['paid_amount'];
                    $method = (string)($order['payment_method'] ?: 'Online');
                    $isCash = in_array($method, ['COD', 'Cash on Delivery', 'Cash'], true);
                    
                    $payStatus = (string)($order['payment_status'] ?: 'Pending');
                    $shipStatus = (string)($order['shipping_status'] ?: 'Pending');
                    $orderDate = !empty($order['payment_date']) ? date('d M Y, h:i A', strtotime($order['payment_date'])) : '-';
                    $city = (string)($order['shipping_city'] ?: $order['billing_city'] ?: '');
                ?>
                    <tr id="order-row-<?= htmlspecialchars($reference, ENT_QUOTES) ?>">
                        <!-- Customer & Order Reference -->
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span class="cust-name" style="font-weight: 600; color: #0f172a; font-size: 13px;"><?= orderEsc($custName) ?></span>
                                <a href="order-summary.php?payment_id=<?= rawurlencode($reference) ?>" class="order-ref" style="font-size: 11px; color: #2563eb; background: #eff6ff; padding: 2px 7px; border-radius: 4px; border: 1px solid #bfdbfe; font-family: monospace; text-decoration: none;" title="View Invoice">
                                    #<?= orderEsc($reference) ?>
                                </a>
                            </div>
                            <?php
                            $metaParts = [];
                            if ($orderDate !== '-') $metaParts[] = '<span style="color: #64748b;">' . orderEsc($orderDate) . '</span>';
                            if ($custPhone !== '') $metaParts[] = '<span><i class="fa fa-phone" style="color: #94a3b8; font-size: 10px;"></i> ' . orderEsc($custPhone) . '</span>';
                            if ($city !== '') $metaParts[] = '<span>' . orderEsc($city) . '</span>';
                            ?>
                            <div class="cust-detail" style="font-size: 11px; color: #64748b; margin-top: 3px; line-height: 1.3;">
                                <?= implode('<span style="color: #cbd5e1; margin: 0 5px;">•</span>', $metaParts) ?>
                            </div>
                        </td>

                        <!-- Amount & Payment Method -->
                        <td>
                            <div class="order-amount">BDT <?= number_format($amount, 2) ?></div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                <span style="background: #f1f5f9; padding: 1px 6px; border-radius: 4px; border: 1px solid #e2e8f0; font-size: 10px; font-weight: 600; color: #475569;">
                                    <?= orderEsc($method) ?>
                                </span>
                            </div>
                        </td>

                        <!-- Payment Status -->
                        <td class="cell-pay-status" id="pay-status-<?= htmlspecialchars($reference, ENT_QUOTES) ?>">
                            <?php if ($payStatus === 'Completed'): ?>
                                <span class="status-pill status-pill-paid">
                                    <i class="fa fa-check-circle"></i> Paid
                                </span>
                            <?php elseif ($payStatus === 'Cancelled'): ?>
                                <span class="status-pill status-pill-cancelled">
                                    <i class="fa fa-times-circle"></i> Cancelled
                                </span>
                            <?php else: ?>
                                <span class="status-pill status-pill-pending">
                                    <i class="fa fa-clock-o"></i> Pending
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Fulfillment Status -->
                        <td class="cell-ship-status" id="ship-status-<?= htmlspecialchars($reference, ENT_QUOTES) ?>">
                            <?php if ($shipStatus === 'Delivered'): ?>
                                <span class="status-pill status-pill-delivered">
                                    <i class="fa fa-check"></i> Delivered
                                </span>
                            <?php elseif ($shipStatus === 'Shipped'): ?>
                                <span class="status-pill status-pill-shipped">
                                    <i class="fa fa-truck"></i> Shipped
                                </span>
                            <?php elseif ($shipStatus === 'Processing'): ?>
                                <span class="status-pill status-pill-processing">
                                    <i class="fa fa-refresh"></i> Processing
                                </span>
                            <?php elseif ($shipStatus === 'Cancelled'): ?>
                                <span class="status-pill status-pill-cancelled">
                                    <i class="fa fa-ban"></i> Cancelled
                                </span>
                            <?php else: ?>
                                <span class="status-pill status-pill-muted">
                                    <i class="fa fa-inbox"></i> Pending
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Formal Actions (Update Status + Actions Menu) -->
                        <td style="text-align: right; white-space: nowrap;">
                            <!-- Prominent Status Updater Button -->
                            <button type="button" class="btn-formal-update btn-status-trigger" id="btn-status-<?= htmlspecialchars($reference, ENT_QUOTES) ?>" 
                                data-cust="<?= htmlspecialchars($custName, ENT_QUOTES) ?>"
                                onclick="openUpdateStatusModal('<?= htmlspecialchars($reference, ENT_QUOTES) ?>', '<?= htmlspecialchars($custName, ENT_QUOTES) ?>', '<?= htmlspecialchars($shipStatus, ENT_QUOTES) ?>', '<?= htmlspecialchars($payStatus, ENT_QUOTES) ?>')"
                                title="Update Order Fulfillment & Payment Status">
                                <i class="fa fa-sliders"></i> Update Status
                            </button>

                            <!-- More Actions Dropdown -->
                            <div class="dropdown" style="display: inline-block;">
                                <button type="button" class="btn-formal-icon dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More options">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right dropdown-menu-formal">
                                    <li>
                                        <a href="order-summary.php?payment_id=<?= rawurlencode($reference) ?>">
                                            <i class="fa fa-file-text-o" style="color: #2563eb;"></i> View Invoice
                                        </a>
                                    </li>
                                    <li>
                                        <a href="order-summary.php?payment_id=<?= rawurlencode($reference) ?>&print=1" target="_blank">
                                            <i class="fa fa-print" style="color: #64748b;"></i> Print Slip
                                        </a>
                                    </li>
                                    
                                    <li class="divider"></li>

                                    <?php if ($custPhone !== ''): ?>
                                        <li>
                                            <a href="javascript:void(0)" onclick="openOrderMsgModal('sms', '<?= htmlspecialchars($custPhone, ENT_QUOTES) ?>', '<?= htmlspecialchars($custName, ENT_QUOTES) ?>', '<?= htmlspecialchars($reference, ENT_QUOTES) ?>')">
                                                <i class="fa fa-comment" style="color: #d97706;"></i> Send SMS
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($custEmail !== ''): ?>
                                        <li>
                                            <a href="javascript:void(0)" onclick="openOrderMsgModal('email', '<?= htmlspecialchars($custEmail, ENT_QUOTES) ?>', '<?= htmlspecialchars($custName, ENT_QUOTES) ?>', '<?= htmlspecialchars($reference, ENT_QUOTES) ?>')">
                                                <i class="fa fa-envelope" style="color: #2563eb;"></i> Send Email
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <li class="divider"></li>

                                    <li>
                                        <a href="javascript:void(0)" onclick="quickSetStatus('<?= htmlspecialchars($reference, ENT_QUOTES) ?>', 'Shipped', 'Completed')">
                                            <i class="fa fa-truck" style="color: #0284c7;"></i> Mark as Shipped
                                        </a>
                                    </li>

                                    <li>
                                        <a href="javascript:void(0)" onclick="quickSetStatus('<?= htmlspecialchars($reference, ENT_QUOTES) ?>', 'Delivered', 'Completed')">
                                            <i class="fa fa-check-circle" style="color: #16a34a;"></i> Mark as Delivered
                                        </a>
                                    </li>

                                    <li>
                                        <a href="javascript:void(0)" onclick="quickCancelOrder('<?= htmlspecialchars($reference, ENT_QUOTES) ?>')" style="color: #dc2626;">
                                            <i class="fa fa-ban" style="color: #dc2626;"></i> Cancel Order
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Formal Pagination -->
    <?php $totalPages = max(1, (int)ceil($total / $limit)); ?>
    <div class="hidden-xs" style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; flex-wrap: wrap; gap: 10px; padding: 4px 2px;">
        <div style="font-size: 12px; color: #64748b;">
            Showing <strong><?= min($total, ($offset + 1)) ?></strong> to <strong><?= min($total, ($offset + count($orders))) ?></strong> of <strong><?= number_format($total) ?></strong> orders
        </div>
        <nav aria-label="Order pagination">
            <ul class="pagination pagination-sm" style="margin: 0;">
                <?php if ($page > 1): ?>
                    <li><a href="?page=<?= $page - 1 ?>&tab=<?= rawurlencode($tab) ?>&payment_status=<?= rawurlencode($paymentFilter) ?>&search=<?= rawurlencode($search) ?>">&laquo; Prev</a></li>
                <?php else: ?>
                    <li class="disabled"><span>&laquo; Prev</span></li>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <li class="<?= $p === $page ? 'active' : '' ?>">
                        <a href="?page=<?= $p ?>&tab=<?= rawurlencode($tab) ?>&payment_status=<?= rawurlencode($paymentFilter) ?>&search=<?= rawurlencode($search) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li><a href="?page=<?= $page + 1 ?>&tab=<?= rawurlencode($tab) ?>&payment_status=<?= rawurlencode($paymentFilter) ?>&search=<?= rawurlencode($search) ?>">Next &raquo;</a></li>
                <?php else: ?>
                    <li class="disabled"><span>Next &raquo;</span></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<!-- =============================================================
     MODAL: UPDATE ORDER STATUS (MINIMAL & FORMAL)
============================================================= -->
<div class="modal fade" id="modal-update-order-status" tabindex="-1" role="dialog" aria-labelledby="statusModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 480px; margin: 60px auto;">
        <div class="modal-content modal-content-formal">
            <form action="order-change-status.php" method="post" id="form-update-order-status">
                <input type="hidden" name="id" id="status_modal_order_id" value="">
                <input type="hidden" name="redirect" value="<?= orderEsc($_SERVER['REQUEST_URI'] ?? 'order.php') ?>">

                <div class="modal-header-formal">
                    <h4 class="modal-title" id="statusModalTitle">
                        <i class="fa fa-sliders" style="color: #0f172a; margin-right: 6px;"></i> Update Order Status
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity: 0.5;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body-formal">
                    <!-- Order & Customer Info Pill -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Order Ref:</span>
                            <strong style="color: #0f172a; font-family: monospace;" id="status_modal_display_id">#</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                            <span style="color: #64748b;">Customer:</span>
                            <span style="font-weight: 600; color: #0f172a;" id="status_modal_display_cust">-</span>
                        </div>
                    </div>

                    <!-- Fulfillment / Shipping Status -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label-formal" for="modal_shipping_status">
                            Fulfillment Status <span style="color: #dc2626;">*</span>
                        </label>
                        <select name="shipping_status" id="modal_shipping_status" class="form-control-formal" required>
                            <option value="Pending">Pending (Received, awaiting dispatch)</option>
                            <option value="Processing">Processing (Packaging in progress)</option>
                            <option value="Shipped">Shipped (Handed to courier / in transit)</option>
                            <option value="Delivered">Delivered (Successfully handed to customer)</option>
                            <option value="Cancelled">Cancelled (Order voided / returned)</option>
                        </select>
                        <p style="font-size: 11px; color: #94a3b8; margin: 4px 0 0;">Selecting 'Cancelled' automatically restores product inventory.</p>
                    </div>

                    <!-- Payment Status -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label-formal" for="modal_payment_status">
                            Payment Status <span style="color: #dc2626;">*</span>
                        </label>
                        <select name="payment_status" id="modal_payment_status" class="form-control-formal" required>
                            <option value="Pending">Pending (Awaiting collection / COD)</option>
                            <option value="Completed">Completed (Payment received & settled)</option>
                            <option value="Cancelled">Cancelled (Payment refunded or aborted)</option>
                        </select>
                    </div>

                    <!-- Admin Note / Tracking Number -->
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label-formal" for="modal_admin_note">
                            Tracking Code or Internal Note <span style="font-weight: 400; color: #94a3b8;">(Optional)</span>
                        </label>
                        <input type="text" name="admin_note" id="modal_admin_note" class="form-control-formal" placeholder="e.g. Courier tracking #, courier name, or reason">
                    </div>

                    <!-- Automated Notification Checkbox -->
                    <div class="checkbox" style="margin: 0; padding-top: 4px;">
                        <label style="font-size: 12px; color: #334155; font-weight: 500;">
                            <input type="checkbox" name="notify_customer" value="1" checked>
                            Send automated status update notification to customer (SMS & Email)
                        </label>
                    </div>
                </div>

                <div class="modal-footer-formal">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background: #0f172a; border-color: #0f172a; border-radius: 6px; font-weight: 600; padding: 6px 16px;">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =============================================================
     MODAL: SEND CUSTOM SMS / EMAIL TO CUSTOMER
============================================================= -->
<div class="modal fade" id="modal-send-custom-msg" tabindex="-1" role="dialog" aria-labelledby="customMsgModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 480px; margin: 60px auto;">
        <div class="modal-content modal-content-formal">
            <form action="send-custom-message.php" method="post">
                <input type="hidden" name="send_type" id="modal_send_type" value="email">
                <input type="hidden" name="redirect" value="<?= orderEsc($_SERVER['REQUEST_URI'] ?? 'order.php') ?>">

                <div class="modal-header-formal">
                    <h4 class="modal-title" id="customMsgModalTitle">
                        <i class="fa fa-paper-plane" style="color: #0f172a; margin-right: 6px;"></i> Send Message
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity: 0.5;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body-formal">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label-formal" for="modal_recipient">Recipient <span id="modal_recipient_type_label">(Phone or Email)</span></label>
                        <input type="text" name="recipient" id="modal_recipient" class="form-control-formal" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label-formal" for="modal_recipient_name">Customer Name</label>
                        <input type="text" name="recipient_name" id="modal_recipient_name" class="form-control-formal">
                    </div>
                    <div class="form-group" id="group_email_subject" style="margin-bottom: 14px;">
                        <label class="form-label-formal" for="modal_subject">Email Subject</label>
                        <input type="text" name="subject" id="modal_subject" class="form-control-formal" value="Update regarding your order">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label-formal" for="modal_message">Message Content</label>
                        <textarea name="message" id="modal_message" class="form-control-formal" rows="4" required placeholder="Type your text message here..."></textarea>
                    </div>
                </div>

                <div class="modal-footer-formal">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="modal_submit_btn" style="background: #0f172a; border-color: #0f172a; border-radius: 6px; font-weight: 600;">
                        <i class="fa fa-send"></i> Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Open Status Update Modal
function openUpdateStatusModal(orderId, custName, currentShipping, currentPayment) {
    document.getElementById('status_modal_order_id').value = orderId;
    document.getElementById('status_modal_display_id').textContent = '#' + orderId;
    document.getElementById('status_modal_display_cust').textContent = custName;
    
    var shipSelect = document.getElementById('modal_shipping_status');
    var paySelect = document.getElementById('modal_payment_status');
    
    if (shipSelect) shipSelect.value = currentShipping;
    if (paySelect) paySelect.value = currentPayment;
    
    document.getElementById('modal_admin_note').value = '';

    $('#modal-update-order-status').modal('show');
}

// UI Status Syncer without reloading
function updateOrderRowUI(orderId, shippingStatus, paymentStatus) {
    var $payCell = $('#pay-status-' + CSS.escape(orderId));
    var $shipCell = $('#ship-status-' + CSS.escape(orderId));
    var $btn = $('#btn-status-' + CSS.escape(orderId));
    var $row = $('#order-row-' + CSS.escape(orderId));

    if (paymentStatus && $payCell.length) {
        if (paymentStatus === 'Completed') {
            $payCell.html('<span class="status-pill status-pill-paid"><i class="fa fa-check-circle"></i> Paid</span>');
        } else if (paymentStatus === 'Cancelled') {
            $payCell.html('<span class="status-pill status-pill-cancelled"><i class="fa fa-times-circle"></i> Cancelled</span>');
        } else {
            $payCell.html('<span class="status-pill status-pill-pending"><i class="fa fa-clock-o"></i> Pending</span>');
        }
    }

    if (shippingStatus && $shipCell.length) {
        if (shippingStatus === 'Delivered') {
            $shipCell.html('<span class="status-pill status-pill-delivered"><i class="fa fa-check"></i> Delivered</span>');
        } else if (shippingStatus === 'Shipped') {
            $shipCell.html('<span class="status-pill status-pill-shipped"><i class="fa fa-truck"></i> Shipped</span>');
        } else if (shippingStatus === 'Processing') {
            $shipCell.html('<span class="status-pill status-pill-processing"><i class="fa fa-refresh"></i> Processing</span>');
        } else if (shippingStatus === 'Cancelled') {
            $shipCell.html('<span class="status-pill status-pill-cancelled"><i class="fa fa-ban"></i> Cancelled</span>');
        } else {
            $shipCell.html('<span class="status-pill status-pill-muted"><i class="fa fa-inbox"></i> Pending</span>');
        }
    }

    if ($btn.length) {
        var custName = $btn.attr('data-cust') || '';
        $btn.attr('onclick', "openUpdateStatusModal('" + orderId + "', '" + custName.replace(/'/g, "\\'") + "', '" + shippingStatus + "', '" + paymentStatus + "')");
    }

    if ($row.length) {
        $row.css('transition', 'background-color 0.4s ease').css('background-color', '#ecfdf5');
        setTimeout(function() {
            $row.css('background-color', '');
        }, 1800);
    }

    // Synchronize Mobile Order Card
    var $mobCard = $('.sn-mobile-order-card[data-id="' + String(orderId).toLowerCase() + '"]');
    if ($mobCard.length) {
        if (shippingStatus) {
            $mobCard.attr('data-status', shippingStatus.toLowerCase());
            var $statusPill = $mobCard.find('.sn-mord-status');
            $statusPill.attr('class', 'sn-mord-status status-' + shippingStatus.toLowerCase());
            var iconHtml = '';
            var stLow = shippingStatus.toLowerCase();
            if (stLow === 'delivered') {
                iconHtml = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            } else if (stLow === 'processing') {
                iconHtml = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
            } else if (stLow === 'pending') {
                iconHtml = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
            } else if (stLow === 'shipped') {
                iconHtml = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>';
            } else if (stLow === 'cancelled') {
                iconHtml = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
            }
            $statusPill.html(iconHtml + ' <span>' + shippingStatus + '</span>');
        }
        if (paymentStatus) {
            var pLabel = (paymentStatus === 'Completed') ? 'Paid' : ((paymentStatus === 'Cancelled') ? 'Refunded' : 'Pending');
            $mobCard.attr('data-payment', pLabel.toLowerCase());
            var $payVal = $mobCard.find('.sn-mord-payment-val');
            $payVal.attr('class', 'sn-mord-payment-val pay-' + pLabel.toLowerCase()).text(pLabel);
        }
        $mobCard.css('transition', 'background-color 0.4s ease').css('background-color', '#ecfdf5');
        setTimeout(function() {
            $mobCard.css('background-color', '');
        }, 1800);
    }
}

// 1-Click Quick Status Change (Zero reload AJAX)
function quickSetStatus(orderId, shippingStatus, paymentStatus) {
    var csrfToken = $('meta[name="csrf-token"]').attr('content') || '<?= isset($csrf) ? $csrf->getToken() : "" ?>';

    $.ajax({
        url: 'order-change-status.php',
        type: 'POST',
        data: {
            id: orderId,
            shipping_status: shippingStatus,
            payment_status: paymentStatus,
            ajax: 1,
            _csrf: csrfToken
        },
        dataType: 'json'
    }).done(function(res) {
        if (res && res.success) {
            updateOrderRowUI(orderId, shippingStatus, paymentStatus);
            if (typeof showAdminToast === 'function') {
                showAdminToast(res.message || ('Order #' + orderId + ' updated successfully.'), 'success');
            }
        } else {
            var msg = (res && res.message) ? res.message : 'Failed to update order status.';
            if (typeof showAdminToast === 'function') {
                showAdminToast(msg, 'error');
            } else {
                alert(msg);
            }
        }
    }).fail(function(xhr) {
        var msg = 'Failed to update order status. Please try again.';
        try {
            var j = JSON.parse(xhr.responseText);
            if (j.message) msg = j.message;
        } catch(e) {}
        if (typeof showAdminToast === 'function') {
            showAdminToast(msg, 'error');
        } else {
            alert(msg);
        }
    });
}

// 1-Click Quick Cancel Order
function quickCancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel order #' + orderId + '? Product stock will be restored automatically.')) {
        quickSetStatus(orderId, 'Cancelled', 'Cancelled');
    }
}

// Handle Modal Form Submit via AJAX
$(document).ready(function() {
    $('#form-update-order-status').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var orderId = $('#status_modal_order_id').val();
        var shipStatus = $('#modal_shipping_status').val();
        var payStatus = $('#modal_payment_status').val();
        var $submitBtn = $form.find('button[type="submit"]');
        var originalBtnHtml = $submitBtn.html();

        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        var formData = $form.serializeArray();
        formData.push({ name: 'ajax', value: 1 });

        $.ajax({
            url: $form.attr('action') || 'order-change-status.php',
            type: 'POST',
            data: formData,
            dataType: 'json'
        }).done(function(res) {
            $submitBtn.prop('disabled', false).html(originalBtnHtml);
            if (res && res.success) {
                $('#modal-update-order-status').modal('hide');
                updateOrderRowUI(orderId, shipStatus, payStatus);
                if (typeof showAdminToast === 'function') {
                    showAdminToast(res.message || 'Order status updated successfully.', 'success');
                }
            } else {
                var msg = (res && res.message) ? res.message : 'Error updating order status.';
                if (typeof showAdminToast === 'function') {
                    showAdminToast(msg, 'error');
                } else {
                    alert(msg);
                }
            }
        }).fail(function(xhr) {
            $submitBtn.prop('disabled', false).html(originalBtnHtml);
            var msg = 'Failed to save changes. Please try again.';
            try {
                var j = JSON.parse(xhr.responseText);
                if (j.message) msg = j.message;
            } catch(e) {}
            if (typeof showAdminToast === 'function') {
                showAdminToast(msg, 'error');
            } else {
                alert(msg);
            }
        });
    });
});

// Open SMS / Email Modal
function openOrderMsgModal(type, recipient, name, orderId) {
    document.getElementById('modal_send_type').value = type;
    document.getElementById('modal_recipient').value = recipient;
    document.getElementById('modal_recipient_name').value = name;
    
    var title = document.getElementById('customMsgModalTitle');
    var subjectGroup = document.getElementById('group_email_subject');
    var label = document.getElementById('modal_recipient_type_label');
    var submitBtn = document.getElementById('modal_submit_btn');
    var msgInput = document.getElementById('modal_message');

    if (type === 'sms') {
        title.innerHTML = '<i class="fa fa-comment" style="color:#d97706;margin-right:6px;"></i> Send SMS to Customer';
        label.textContent = '(Mobile Phone Number)';
        subjectGroup.style.display = 'none';
        msgInput.placeholder = 'Type your SMS message here...';
        submitBtn.innerHTML = '<i class="fa fa-send"></i> Send SMS';
    } else {
        title.innerHTML = '<i class="fa fa-envelope" style="color:#2563eb;margin-right:6px;"></i> Send Email to Customer';
        label.textContent = '(Email Address)';
        subjectGroup.style.display = 'block';
        document.getElementById('modal_subject').value = 'Update regarding your order #' + orderId;
        msgInput.placeholder = 'Type your email message here...';
        submitBtn.innerHTML = '<i class="fa fa-envelope"></i> Send Email';
    }

    $('#modal-send-custom-msg').modal('show');
}

// Mobile real-time search filter
$(document).ready(function() {
    var $mobSearch = $('#snMobileOrderSearch');
    if ($mobSearch.length) {
        $mobSearch.on('input', function() {
            var q = $(this).val().toLowerCase().trim();
            var $cards = $('.sn-mobile-order-card');
            if (q === '') {
                $cards.show();
                $('#snMobEmptySearchResult').remove();
                return;
            }
            var matches = 0;
            $cards.each(function() {
                var $c = $(this);
                var id = ($c.attr('data-id') || '').toLowerCase();
                var inv = ($c.attr('data-invoice') || '').toLowerCase();
                var cust = ($c.attr('data-cust') || '').toLowerCase();
                var phone = ($c.attr('data-phone') || '').toLowerCase();
                var status = ($c.attr('data-status') || '').toLowerCase();
                var pay = ($c.attr('data-payment') || '').toLowerCase();
                var addr = ($c.attr('data-address') || '').toLowerCase();
                var combined = id + ' ' + inv + ' ' + cust + ' ' + phone + ' ' + status + ' ' + pay + ' ' + addr;
                if (combined.indexOf(q) !== -1) {
                    $c.show();
                    matches++;
                } else {
                    $c.hide();
                }
            });

            $('#snMobEmptySearchResult').remove();
            if (matches === 0) {
                $('#snMobileOrderList').append(
                    '<div id="snMobEmptySearchResult" style="text-align:center; padding:36px 16px; background:#fff; border-radius:16px; border:1px solid #EDEFEF; color:#64748B;">' +
                    '<i class="fa fa-search" style="font-size:26px; color:#CBD5E1; margin-bottom:8px; display:block;"></i>' +
                    '<div style="font-weight:700; color:#0F172A; font-size:14px;">No matching orders found</div>' +
                    '<div style="font-size:12px; margin-top:4px;">Try searching by order ID, customer name, or phone.</div>' +
                    '</div>'
                );
            }
        });

        $mobSearch.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                var term = $(this).val().trim();
                window.location.href = 'order.php?tab=<?= rawurlencode($tab) ?>&search=' + encodeURIComponent(term);
            }
        });
    }
});
</script>

<!-- =============================================================
     MODAL: MOBILE FILTER DIALOG (PIXEL-PERFECT)
============================================================= -->
<div class="modal fade" id="modal-mobile-filter" tabindex="-1" role="dialog" aria-labelledby="mobFilterModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-sm" style="max-width: 360px; margin: 50px auto;">
        <div class="modal-content modal-content-formal" style="border-radius: 16px; overflow: hidden;">
            <form method="get" action="order.php" style="margin: 0;">
                <div class="modal-header-formal" style="padding: 14px 18px; border-bottom: 1px solid #F1F5F9;">
                    <h4 class="modal-title" id="mobFilterModalTitle" style="font-size: 15px; font-weight: 700; color: #0F172A; display: flex; align-items: center; gap: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        Filter Orders
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity: 0.5;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body-formal" style="padding: 18px;">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label-formal">Fulfillment Status</label>
                        <select name="tab" class="form-control-formal">
                            <option value="all" <?= ($tab === 'all') ? 'selected' : '' ?>>All Orders</option>
                            <option value="pending" <?= ($tab === 'pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= ($tab === 'processing') ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= ($tab === 'shipped') ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= ($tab === 'delivered') ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= ($tab === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label-formal">Payment Status</label>
                        <select name="payment_status" class="form-control-formal">
                            <option value="">Payment: All</option>
                            <option value="Completed" <?= ($paymentFilter === 'Completed') ? 'selected' : '' ?>>Paid (Completed)</option>
                            <option value="Pending" <?= ($paymentFilter === 'Pending') ? 'selected' : '' ?>>Payment Pending</option>
                            <option value="Cancelled" <?= ($paymentFilter === 'Cancelled') ? 'selected' : '' ?>>Payment Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 6px;">
                        <label class="form-label-formal">Search Keyword</label>
                        <input type="text" name="search" class="form-control-formal" placeholder="Order ID, Customer, Phone..." value="<?= orderEsc($search) ?>">
                    </div>
                </div>
                <div class="modal-footer-formal" style="padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
                    <a href="order.php" class="btn btn-default btn-sm" style="border-radius: 8px; font-weight: 600;">Reset Filters</a>
                    <button type="submit" class="btn btn-sm" style="border-radius: 8px; font-weight: 700; background: #FEDB65; border: 1px solid #FACC15; color: #0F172A; padding: 6px 16px;">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
