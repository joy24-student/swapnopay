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
    <!-- Header -->
    <div class="order-header-bar">
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

    <!-- Sticky Control Section (KPI Ribbon + Filter/Segmented Tabs Bar) -->
    <div class="sticky-controls-section">
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
    <div class="order-table-container table-responsive">
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
                    <tr>
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
                        <td>
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
                        <td>
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
                            <button type="button" class="btn-formal-update" 
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

                                    <?php if ($payStatus !== 'Completed'): ?>
                                        <li>
                                            <a href="javascript:void(0)" onclick="quickSetStatus('<?= htmlspecialchars($reference, ENT_QUOTES) ?>', '<?= htmlspecialchars($shipStatus, ENT_QUOTES) ?>', 'Completed')">
                                                <i class="fa fa-check" style="color: #059669;"></i> Mark as Paid
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($shipStatus !== 'Delivered' && $shipStatus !== 'Cancelled'): ?>
                                        <li>
                                            <a href="javascript:void(0)" onclick="quickSetStatus('<?= htmlspecialchars($reference, ENT_QUOTES) ?>', 'Delivered', '<?= htmlspecialchars($payStatus, ENT_QUOTES) ?>')">
                                                <i class="fa fa-check-circle" style="color: #16a34a;"></i> Mark as Delivered
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($payStatus !== 'Cancelled' && $shipStatus !== 'Cancelled'): ?>
                                        <li>
                                            <a href="order-delete.php?id=<?= rawurlencode($reference) ?>" onclick="return confirm('Are you sure you want to cancel this order? Stock will be restored.');" style="color: #dc2626;">
                                                <i class="fa fa-ban" style="color: #dc2626;"></i> Cancel Order
                                            </a>
                                        </li>
                                    <?php endif; ?>
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
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; flex-wrap: wrap; gap: 10px; padding: 4px 2px;">
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

// 1-Click Quick Status Change (from dropdown)
function quickSetStatus(orderId, shippingStatus, paymentStatus) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'order-change-status.php';

    var idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = orderId;
    form.appendChild(idInput);

    var shipInput = document.createElement('input');
    shipInput.type = 'hidden';
    shipInput.name = 'shipping_status';
    shipInput.value = shippingStatus;
    form.appendChild(shipInput);

    var payInput = document.createElement('input');
    payInput.type = 'hidden';
    payInput.name = 'payment_status';
    payInput.value = paymentStatus;
    form.appendChild(payInput);

    var redirInput = document.createElement('input');
    redirInput.type = 'hidden';
    redirInput.name = 'redirect';
    redirInput.value = window.location.href;
    form.appendChild(redirInput);

    // CSRF Token if present in meta tag
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = csrfMeta.getAttribute('content');
        form.appendChild(csrfInput);
    }

    document.body.appendChild(form);
    form.submit();
}

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
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
