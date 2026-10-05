<?php
require_once('header.php');

// Check customer authentication
if (!isset($_SESSION['customer'])) {
    header('location: ' . BASE_URL . 'logout.php');
    exit;
} else {
    $statement = $pdo->prepare("SELECT cust_status FROM tbl_customer WHERE cust_id = ? AND cust_status = ?");
    $statement->execute([$_SESSION['customer']['cust_id'], 0]);
    if ($statement->rowCount()) {
        header('location: ' . BASE_URL . 'logout.php');
        exit;
    }
}

$cust_id = (int)$_SESSION['customer']['cust_id'];

// Get filter status & search term
$filter_status = strtolower(trim($_GET['status'] ?? 'all'));
$search_query  = trim($_GET['search'] ?? '');
$time_filter   = trim($_GET['time'] ?? 'all');

// Fetch all orders for this customer
$all_orders = [];
try {
    $stmt_pay = $pdo->prepare("
        SELECT 
            p.id,
            p.payment_id,
            p.payment_date,
            p.txnid,
            p.paid_amount,
            p.payment_method,
            COALESCE(p.payment_status, 'Completed') as payment_status,
            COALESCE(p.shipping_status, 'Pending') as shipping_status
        FROM tbl_payment p
        WHERE p.customer_id = ?
        ORDER BY p.id DESC
    ");
    $stmt_pay->execute([$cust_id]);
    $raw_orders = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_orders as $ord) {
        $pid = $ord['payment_id'];
        
        // Fetch order items with product thumbnails
        $stmt_items = $pdo->prepare("
            SELECT 
                o.id,
                o.product_id,
                o.product_name,
                o.size,
                o.color,
                o.quantity,
                o.unit_price,
                p.p_featured_photo
            FROM tbl_order o
            LEFT JOIN tbl_product p ON o.product_id = p.p_id
            WHERE o.payment_id = ?
            ORDER BY o.id ASC
        ");
        $stmt_items->execute([$pid]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // Normalize status
        $ship_st = strtolower($ord['shipping_status']);
        $pay_st  = strtolower($ord['payment_status']);
        
        $status_label = 'Processing';
        $status_category = 'processing';
        
        if ($ship_st === 'delivered' || $ship_st === 'completed') {
            $status_label = 'Delivered';
            $status_category = 'delivered';
        } elseif ($ship_st === 'shipped' || $ship_st === 'out for delivery') {
            $status_label = 'Shipped';
            $status_category = 'shipped';
        } elseif ($ship_st === 'cancelled' || $pay_st === 'cancelled') {
            $status_label = 'Cancelled';
            $status_category = 'cancelled';
        } else {
            $status_label = 'Processing';
            $status_category = 'processing';
        }

        $ord['items'] = $items;
        $ord['item_count'] = count($items);
        $ord['status_label'] = $status_label;
        $ord['status_category'] = $status_category;
        $all_orders[] = $ord;
    }
} catch (Throwable $e) {}

// Calculate counts for pills
$count_all = count($all_orders);
$count_processing = 0;
$count_shipped = 0;
$count_delivered = 0;
$count_cancelled = 0;

foreach ($all_orders as $o) {
    if ($o['status_category'] === 'processing') $count_processing++;
    elseif ($o['status_category'] === 'shipped') $count_shipped++;
    elseif ($o['status_category'] === 'delivered') $count_delivered++;
    elseif ($o['status_category'] === 'cancelled') $count_cancelled++;
}

// Filter orders
$filtered_orders = array_filter($all_orders, function($o) use ($filter_status, $search_query) {
    if ($filter_status !== 'all' && $o['status_category'] !== $filter_status) {
        return false;
    }
    if (!empty($search_query)) {
        $q = strtolower($search_query);
        $match_id = str_contains(strtolower($o['payment_id']), $q);
        $match_product = false;
        foreach ($o['items'] as $it) {
            if (str_contains(strtolower($it['product_name']), $q)) {
                $match_product = true;
                break;
            }
        }
        if (!$match_id && !$match_product) return false;
    }
    return true;
});
?>

<!-- Portal Modern Stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_portal_modern.css?v=<?= time() ?>">

<style>
/* Exact styling matching media_1790349123513.png */
.sn-order-card-detailed {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: 80px 220px 1fr 170px;
    align-items: center;
    gap: 24px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
    position: relative;
}

.sn-order-card-detailed:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}

.sn-order-thumb-large {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.sn-order-thumb-large img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.sn-order-summary-col {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sn-order-id-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    white-space: nowrap;
}

.sn-order-placed-text {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

.sn-order-mini-thumbs {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
}

.sn-mini-thumb {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.sn-mini-thumb img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.sn-mini-more-pill {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 3px 6px;
    border-radius: 6px;
}

.sn-order-price-val {
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    margin-top: 6px;
}

.sn-order-view-details-link {
    font-size: 12.5px;
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    transition: color 0.15s ease;
}

.sn-order-view-details-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

/* Middle Stepper Column */
.sn-order-stepper-col {
    padding: 0 10px;
}

.sn-order-status-badge-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.sn-order-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 999px;
}

.sn-status-pill-delivered { background: #dcfce7; color: #15803d; }
.sn-status-pill-shipped   { background: #eff6ff; color: #2563eb; }
.sn-status-pill-processing{ background: #fef3c7; color: #b45309; }
.sn-status-pill-cancelled { background: #f1f5f9; color: #64748b; }

.sn-order-status-subtext {
    font-size: 12px;
    color: #64748b;
}

/* 4-Step Tracker Bar */
.sn-timeline-track {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    width: 100%;
}

.sn-track-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
    min-width: 60px;
}

.sn-track-dot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    font-weight: 800;
    color: #ffffff;
    transition: all 0.2s ease;
}

.sn-track-dot.active-green {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}

.sn-track-dot.active-blue {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
}

.sn-track-dot.active-orange {
    background: #f59e0b;
    border-color: #f59e0b;
    color: #ffffff;
}

.sn-track-line {
    flex: 1;
    height: 3px;
    background: #e2e8f0;
    margin: 0 4px;
    margin-bottom: 24px;
    position: relative;
    z-index: 1;
}

.sn-track-line.filled-green {
    background: #10b981;
}

.sn-track-line.filled-blue {
    background: #2563eb;
}

.sn-track-step-label {
    font-size: 11px;
    font-weight: 600;
    color: #334155;
    margin-top: 6px;
    text-align: center;
}

.sn-track-step-date {
    font-size: 10px;
    color: #94a3b8;
    margin-top: 2px;
    text-align: center;
}

/* Action Buttons Column */
.sn-order-actions-col {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: stretch;
}

.sn-btn-order-buy-again {
    background: #eff6ff;
    color: #2563eb;
    font-size: 12.5px;
    font-weight: 700;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 9px 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.sn-btn-order-buy-again:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.sn-btn-order-track {
    background: #ffffff;
    color: #334155;
    font-size: 12.5px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.sn-btn-order-track:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}

.sn-btn-order-cancel {
    background: #fef2f2;
    color: #ef4444;
    font-size: 12.5px;
    font-weight: 600;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 8px 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}

.sn-btn-order-cancel:hover {
    background: #ef4444;
    color: #ffffff;
}

/* Modal styling */
.sn-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
    backdrop-filter: blur(4px);
}

.sn-modal-box {
    background: #ffffff;
    border-radius: 20px;
    max-width: 640px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    position: relative;
    padding: 28px;
}

.sn-modal-close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: #f1f5f9;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 14px;
}

@media (max-width: 1024px) {
    .sn-order-card-detailed {
        grid-template-columns: 80px 1fr;
    }
    .sn-order-stepper-col {
        grid-column: 1 / -1;
    }
    .sn-order-actions-col {
        grid-column: 1 / -1;
        flex-direction: row;
    }
}

@media (max-width: 640px) {
    .sn-order-card-detailed {
        grid-template-columns: 1fr;
    }
    .sn-order-actions-col {
        flex-direction: column;
    }
}
</style>

<style>
/* ===== Yellow theme + mobile responsive overrides (My Orders) ===== */
.sn-portal-wrapper { --sn-primary:#fab802; --sn-primary-hover:#e0a400; --sn-primary-light:#fff8e1; --sn-primary-border:#fde8a1; --sn-border-focus:#fab802; }
.sn-portal-wrapper .sn-portal-main { min-width:0; max-width:100%; overflow-x:hidden; }
.sn-portal-title { color:#111827; }
.sn-portal-wrapper .sn-status-pill-btn.active { background:#fab802 !important; border-color:#fab802 !important; color:#111827 !important; font-weight:700; box-shadow:0 2px 8px rgba(250,184,2,.3); }
.sn-portal-wrapper .sn-status-pill-btn:hover { border-color:#fab802; }
.sn-order-card-detailed { border-color:#f1f5f9; }
.sn-order-card-detailed:hover { border-color:#fab802; box-shadow:0 6px 18px rgba(250,184,2,.15); }
.sn-order-view-details-link { color:#b45309; }
.sn-order-view-details-link:hover { color:#92400e; }
.sn-status-pill-shipped { background:#fff8e1; color:#b45309; }
.sn-status-pill-processing { background:#fef3c7; color:#92400e; }
.sn-track-dot.active-blue, .sn-track-dot.active-orange { background:#fab802; border-color:#fab802; color:#111827; }
.sn-track-line.filled-blue { background:#fab802; }
.sn-btn-order-buy-again, .sn-btn-order-track:first-child:not(:only-child) { }
.sn-btn-order-buy-again { background:#fab802; color:#111827; border:1px solid #fab802; box-shadow:0 2px 6px rgba(250,184,2,.3); }
.sn-btn-order-buy-again:hover { background:#e0a400; border-color:#e0a400; color:#111827; }
.sn-btn-order-track:hover { border-color:#fab802; background:#fff8e1; }
.sn-orders-search-form { margin:0; }
.sn-portal-wrapper .sn-search-input-wrap input:focus, .sn-time-select:focus { border-color:#fab802; outline:none; box-shadow:0 0 0 3px rgba(250,184,2,.2); }
.sn-modal-box #modalContent i.fa-spin { color:#fab802 !important; }

@media (max-width:768px) {
    .sn-portal-wrapper { padding:12px 0 90px; background:#fffdf5; }
    .sn-portal-container { padding:0 12px; }
    .sn-portal-layout { display:block; }
    .sn-breadcrumb { display:none; }
    .sn-orders-header-row { display:flex !important; flex-direction:column !important; align-items:stretch !important; gap:12px !important; margin-bottom:12px !important; }
    .sn-portal-title { font-size:22px !important; margin:0 !important; }
    .sn-portal-subtitle { font-size:12.5px !important; margin:2px 0 0 !important; }
    .sn-orders-controls { display:flex !important; flex-direction:row !important; gap:8px !important; width:100% !important; }
    .sn-orders-controls .sn-orders-search-form { flex:1 1 auto; min-width:0; }
    .sn-orders-controls .sn-search-input-wrap { width:100% !important; max-width:none !important; height:42px !important; border-radius:999px !important; }
    .sn-orders-controls .sn-search-input-wrap input { width:100% !important; min-width:0; font-size:13px !important; }
    .sn-orders-controls .sn-filter-dropdown-wrap { flex:0 0 auto; max-width:46%; }
    .sn-orders-controls .sn-time-select { width:100% !important; height:42px !important; font-size:13px !important; border-radius:999px !important; }

    .sn-orders-tabs-row { margin:0 -12px 14px !important; }
    .sn-status-pills-bar { display:flex !important; flex-wrap:nowrap !important; overflow-x:auto; gap:8px !important; padding:2px 12px 6px !important; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
    .sn-status-pills-bar::-webkit-scrollbar { display:none; }
    .sn-status-pill-btn { flex:0 0 auto; white-space:nowrap; font-size:12.5px !important; padding:8px 14px !important; }

    .sn-order-card-detailed { display:grid !important; grid-template-columns:64px minmax(0,1fr) !important; gap:12px !important; padding:14px !important; margin-bottom:12px !important; border-radius:16px !important; align-items:start !important; }
    .sn-order-thumb-large { width:64px; height:64px; border-radius:10px; }
    .sn-order-summary-col { min-width:0; gap:3px; }
    .sn-order-id-title { font-size:14px; white-space:normal; word-break:break-all; }
    .sn-order-placed-text { font-size:11.5px; }
    .sn-order-price-val { font-size:17px; margin-top:4px; color:#111827; }
    .sn-order-stepper-col { grid-column:1 / -1 !important; padding:10px 0 0 !important; border-top:1px dashed #f1e3b0; }
    .sn-order-status-badge-row { flex-wrap:wrap; gap:6px 8px; margin-bottom:12px; }
    .sn-order-status-subtext { font-size:11.5px; }
    .sn-track-step { min-width:0; flex:0 0 auto; width:52px; }
    .sn-track-step-label { font-size:10px; line-height:1.15; }
    .sn-track-step-date { font-size:9.5px; }
    .sn-track-line { margin-bottom:30px; }
    .sn-order-actions-col { grid-column:1 / -1 !important; flex-direction:row !important; gap:8px !important; }
    .sn-order-actions-col > button, .sn-order-actions-col > a { flex:1 1 0; min-width:0; padding:11px 8px !important; font-size:12.5px !important; border-radius:999px !important; }

    .sn-empty-state-box { padding:32px 16px !important; }

    /* modal -> bottom sheet */
    .sn-modal-backdrop { align-items:flex-end !important; padding:0 !important; }
    .sn-modal-box { max-width:100% !important; border-radius:20px 20px 0 0 !important; max-height:88vh !important; padding:20px 16px calc(20px + env(safe-area-inset-bottom)) !important; animation:snSheetUp .28s cubic-bezier(.16,1,.3,1); }
    .sn-modal-close-btn { top:12px !important; right:12px !important; }
    #modalContent h3 { padding-right:36px; }
}
@keyframes snSheetUp { from { transform:translateY(100%); } to { transform:translateY(0); } }
@media (max-width:380px) {
    .sn-orders-controls { flex-direction:column !important; }
    .sn-orders-controls .sn-filter-dropdown-wrap { max-width:none; }
}
</style>
<div class="sn-portal-wrapper">
    <div class="sn-portal-container">
        <div class="sn-portal-layout">
            <!-- Left Shared Navigation Sidebar -->
            <?php require_once('customer-sidebar.php'); ?>

            <!-- Right Main Orders Content -->
            <main class="sn-portal-main">
                <!-- Breadcrumbs -->
                <nav class="sn-breadcrumb">
                    <a href="index.php">Home</a>
                    <i class="fa-solid fa-chevron-right"></i>
                    <span>Orders</span>
                </nav>

                <!-- Header Row: Title & Search Controls -->
                <div class="sn-orders-header-row">
                    <div>
                        <h1 class="sn-portal-title">My Orders</h1>
                        <p class="sn-portal-subtitle">Track, view and manage your orders all in one place.</p>
                    </div>
                    </div>

                <!-- Status Filter Pills Bar -->
                <div class="sn-orders-tabs-row" style="margin-bottom: 24px;">
                    <div class="sn-status-pills-bar">
                        <a data-filter="all" href="customer-order.php?status=all" class="sn-status-pill-btn <?= ($filter_status === 'all') ? 'active' : '' ?>">
                            All Orders (<?= $count_all ?>)
                        </a>
                        <a data-filter="processing" href="customer-order.php?status=processing" class="sn-status-pill-btn <?= ($filter_status === 'processing') ? 'active' : '' ?>">
                            Processing (<?= $count_processing ?>)
                        </a>
                        <a data-filter="shipped" href="customer-order.php?status=shipped" class="sn-status-pill-btn <?= ($filter_status === 'shipped') ? 'active' : '' ?>">
                            Shipped (<?= $count_shipped ?>)
                        </a>
                        <a data-filter="delivered" href="customer-order.php?status=delivered" class="sn-status-pill-btn <?= ($filter_status === 'delivered') ? 'active' : '' ?>">
                            Delivered (<?= $count_delivered ?>)
                        </a>
                        <a data-filter="cancelled" href="customer-order.php?status=cancelled" class="sn-status-pill-btn <?= ($filter_status === 'cancelled') ? 'active' : '' ?>">
                            Cancelled (<?= $count_cancelled ?>)
                        </a>
                    </div>
                </div>

                <!-- Orders List -->
                <?php if (empty($all_orders)): ?>
                    <div class="sn-empty-state-box">
                        <div class="sn-empty-icon-circle">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <h3>No orders found</h3>
                        <p>We couldn't find any orders matching your criteria. Start shopping to create your first order!</p>
                        <a href="index.php" class="sn-btn-primary" style="display: inline-block; padding: 10px 24px; border-radius: 8px; text-decoration: none;">
                            Browse Products
                        </a>
                    </div>
                <?php else: ?>
                    <div class="sn-empty-state-box" id="snFilterEmpty" style="display:none"><div class="sn-empty-icon-circle"><i class="fa-solid fa-box-open"></i></div><h3>No orders here</h3><p>You have no orders in this status.</p></div>
                    <div class="sn-orders-list">
                        <?php foreach ($all_orders as $ord): 
                            $first_item = $ord['items'][0] ?? null;
                            $first_photo = !empty($first_item['p_featured_photo']) 
                                ? 'assets/uploads/' . $first_item['p_featured_photo'] 
                                : 'assets/uploads/no-image.jpg';
                            $order_date_str = date('M d, Y', strtotime($ord['payment_date'] ?? 'now'));
                            $order_timestamp = strtotime($ord['payment_date'] ?? 'now');
                            $step2_date = date('M d', $order_timestamp + 86400);
                            $step3_date = date('M d', $order_timestamp + 172800);
                            $step4_date = date('M d', $order_timestamp + 259200);
                            
                            $cat = $ord['status_category'];
                        ?>
                            <div class="sn-order-card-detailed" data-status="<?= htmlspecialchars($cat) ?>"<?= ($filter_status !== 'all' && $filter_status !== $cat) ? ' style="display:none"' : '' ?>>
                                <!-- Col 1: Large Product Thumbnail -->
                                <div class="sn-order-thumb-large">
                                    <img src="<?= htmlspecialchars($first_photo) ?>" alt="<?= htmlspecialchars($first_item['product_name'] ?? 'Product') ?>">
                                </div>

                                <!-- Col 2: Order Info Summary -->
                                <div class="sn-order-summary-col">
                                    <h3 class="sn-order-id-title">Order #<?= htmlspecialchars($ord['payment_id']) ?></h3>
                                    <p class="sn-order-placed-text">Placed on <?= $order_date_str ?> • <?= $ord['item_count'] ?> item<?= ($ord['item_count'] > 1) ? 's' : '' ?></p>
                                    
                                    <!-- Mini product previews -->
                                    <div class="sn-order-mini-thumbs">
                                        <?php 
                                        $preview_items = array_slice($ord['items'], 0, 3);
                                        foreach ($preview_items as $p_it): 
                                            $mini_photo = !empty($p_it['p_featured_photo']) 
                                                ? 'assets/uploads/' . $p_it['p_featured_photo'] 
                                                : 'assets/uploads/no-image.jpg';
                                        ?>
                                            <div class="sn-mini-thumb" title="<?= htmlspecialchars($p_it['product_name']) ?>">
                                                <img src="<?= htmlspecialchars($mini_photo) ?>" alt="">
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($ord['item_count'] > 3): ?>
                                            <span class="sn-mini-more-pill">+<?= $ord['item_count'] - 3 ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="sn-order-price-val">৳ <?= number_format((float)$ord['paid_amount'], 2) ?></div>
                                    <a href="javascript:void(0)" onclick="openOrderModal('<?= htmlspecialchars($ord['payment_id']) ?>')" class="sn-order-view-details-link">
                                        View Details &rarr;
                                    </a>
                                </div>

                                <!-- Col 3: Stepper Progress Tracker -->
                                <div class="sn-order-stepper-col">
                                    <!-- Status Badge Pill & Subtitle -->
                                    <div class="sn-order-status-badge-row">
                                        <?php if ($cat === 'delivered'): ?>
                                            <span class="sn-order-status-pill sn-status-pill-delivered">
                                                <i class="fa-solid fa-circle-check"></i> Delivered
                                            </span>
                                            <span class="sn-order-status-subtext">Delivered on <?= $step4_date ?>, <?= date('Y', $order_timestamp) ?></span>
                                        <?php elseif ($cat === 'shipped'): ?>
                                            <span class="sn-order-status-pill sn-status-pill-shipped">
                                                <i class="fa-solid fa-truck-fast"></i> Shipped
                                            </span>
                                            <span class="sn-order-status-subtext">Out for delivery • Expected by <?= $step4_date ?></span>
                                        <?php elseif ($cat === 'cancelled'): ?>
                                            <span class="sn-order-status-pill sn-status-pill-cancelled">
                                                <i class="fa-solid fa-circle-xmark"></i> Cancelled
                                            </span>
                                            <span class="sn-order-status-subtext">Order was cancelled on <?= $step2_date ?></span>
                                        <?php else: ?>
                                            <span class="sn-order-status-pill sn-status-pill-processing">
                                                <i class="fa-solid fa-gear"></i> Processing
                                            </span>
                                            <span class="sn-order-status-subtext">Preparing your order</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- 4-Step Tracker Bar -->
                                    <div class="sn-timeline-track">
                                        <!-- Step 1: Placed -->
                                        <div class="sn-track-step">
                                            <div class="sn-track-dot active-<?= ($cat === 'delivered') ? 'green' : (($cat === 'shipped') ? 'blue' : 'orange') ?>">✓</div>
                                            <span class="sn-track-step-label">Placed</span>
                                            <span class="sn-track-step-date"><?= date('M d', $order_timestamp) ?></span>
                                        </div>

                                        <!-- Line 1 -->
                                        <div class="sn-track-line <?= ($cat === 'delivered') ? 'filled-green' : (($cat === 'shipped') ? 'filled-blue' : '') ?>"></div>

                                        <!-- Step 2: Processing / Shipped -->
                                        <div class="sn-track-step">
                                            <div class="sn-track-dot <?= ($cat === 'delivered' || $cat === 'shipped') ? (($cat === 'delivered') ? 'active-green' : 'active-blue') : 'active-orange' ?>">
                                                <?= ($cat === 'delivered' || $cat === 'shipped') ? '✓' : '2' ?>
                                            </div>
                                            <span class="sn-track-step-label"><?= ($cat === 'shipped' || $cat === 'delivered') ? 'Shipped' : 'Processing' ?></span>
                                            <span class="sn-track-step-date"><?= $step2_date ?></span>
                                        </div>

                                        <!-- Line 2 -->
                                        <div class="sn-track-line <?= ($cat === 'delivered') ? 'filled-green' : (($cat === 'shipped') ? 'filled-blue' : '') ?>"></div>

                                        <!-- Step 3: Out for delivery -->
                                        <div class="sn-track-step">
                                            <div class="sn-track-dot <?= ($cat === 'delivered') ? 'active-green' : (($cat === 'shipped') ? 'active-blue' : '') ?>">
                                                <?= ($cat === 'delivered') ? '✓' : '3' ?>
                                            </div>
                                            <span class="sn-track-step-label">Out for delivery</span>
                                            <span class="sn-track-step-date"><?= ($cat === 'delivered' || $cat === 'shipped') ? $step3_date : '—' ?></span>
                                        </div>

                                        <!-- Line 3 -->
                                        <div class="sn-track-line <?= ($cat === 'delivered') ? 'filled-green' : '' ?>"></div>

                                        <!-- Step 4: Delivered -->
                                        <div class="sn-track-step">
                                            <div class="sn-track-dot <?= ($cat === 'delivered') ? 'active-green' : '' ?>">
                                                <?= ($cat === 'delivered') ? '✓' : '4' ?>
                                            </div>
                                            <span class="sn-track-step-label">Delivered</span>
                                            <span class="sn-track-step-date"><?= ($cat === 'delivered') ? $step4_date : 'Expected' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Col 4: Action Buttons -->
                                <div class="sn-order-actions-col">
                                    <?php if ($cat === 'delivered'): ?>
                                        <button type="button" class="sn-btn-order-buy-again" onclick="buyAgain('<?= htmlspecialchars($ord['payment_id']) ?>')">
                                            <i class="fa-solid fa-cart-shopping"></i> Buy Again
                                        </button>
                                        <button type="button" class="sn-btn-order-track" onclick="openOrderModal('<?= htmlspecialchars($ord['payment_id']) ?>')">
                                            <i class="fa-solid fa-location-dot"></i> Track Order
                                        </button>
                                    <?php elseif ($cat === 'shipped'): ?>
                                        <button type="button" class="sn-btn-order-track" onclick="openOrderModal('<?= htmlspecialchars($ord['payment_id']) ?>')">
                                            <i class="fa-solid fa-location-dot"></i> Track Order
                                        </button>
                                    <?php elseif ($cat === 'processing'): ?>
                                        <button type="button" class="sn-btn-order-cancel" onclick="cancelOrder('<?= htmlspecialchars($ord['payment_id']) ?>')">
                                            <i class="fa-solid fa-xmark"></i> Cancel Order
                                        </button>
                                        <button type="button" class="sn-btn-order-track" onclick="openOrderModal('<?= htmlspecialchars($ord['payment_id']) ?>')">
                                            <i class="fa-solid fa-location-dot"></i> Track Order
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="sn-btn-order-track" onclick="openOrderModal('<?= htmlspecialchars($ord['payment_id']) ?>')">
                                            <i class="fa-regular fa-file-lines"></i> View Details
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<!-- Interactive Order Tracking / Details Modal -->
<div class="sn-modal-backdrop" id="orderModal">
    <div class="sn-modal-box">
        <button type="button" class="sn-modal-close-btn" onclick="closeOrderModal()">&times;</button>
        <div id="modalContent">
            <div style="text-align: center; padding: 40px 0;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 32px; color: #fab802;"></i>
                <p style="margin-top: 12px; color: #64748b; font-size: 14px;">Loading order details...</p>
            </div>
        </div>
    </div>
</div>

<script>
function openOrderModal(paymentId) {
    const modal = document.getElementById('orderModal');
    const content = document.getElementById('modalContent');
    modal.style.display = 'flex';
    content.innerHTML = `
        <div style="text-align: center; padding: 40px 0;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 32px; color: #fab802;"></i>
            <p style="margin-top: 12px; color: #64748b; font-size: 14px;">Loading order details...</p>
        </div>
    `;

    fetch('ajax/get-order-details.php?payment_id=' + encodeURIComponent(paymentId))
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const ord = data.order;
                let itemsHtml = '';
                (ord.items || []).forEach(it => {
                    itemsHtml += `
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="${it.photo || 'assets/uploads/no-image.jpg'}" style="width: 44px; height: 44px; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <div>
                                    <div style="font-weight: 600; font-size: 13.5px; color: #0f172a;">${it.name}</div>
                                    <div style="font-size: 12px; color: #64748b;">Qty: ${it.quantity} • Unit: ৳ ${it.unit_price}</div>
                                </div>
                            </div>
                            <div style="font-weight: 700; font-size: 14px; color: #0f172a;">৳ ${(it.quantity * it.unit_price).toFixed(2)}</div>
                        </div>
                    `;
                });

                content.innerHTML = `
                    <h3 style="margin: 0 0 6px 0; font-size: 18px; font-weight: 700; color: #0f172a;">Order #${ord.payment_id}</h3>
                    <p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b;">Placed on ${ord.payment_date} • Payment Method: ${ord.payment_method}</p>
                    
                    <div style="background: #f8fafc; border-radius: 12px; padding: 14px; margin-bottom: 20px;">
                        <div style="font-size: 13px; color: #334155; margin-bottom: 4px;"><strong>Status:</strong> <span style="font-weight: 700; color: #b45309;">${ord.shipping_status}</span></div>
                        <div style="font-size: 13px; color: #334155;"><strong>Transaction ID:</strong> ${ord.txnid || 'N/A'}</div>
                    </div>

                    <h4 style="margin: 0 0 10px 0; font-size: 14.5px; font-weight: 700; color: #0f172a;">Items in this Order</h4>
                    <div style="margin-bottom: 20px;">${itemsHtml}</div>

                    <div style="background: #fbfdff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #64748b; margin-bottom: 6px;">
                            <span>Subtotal</span>
                            <span>৳ ${parseFloat(ord.paid_amount).toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #64748b; margin-bottom: 8px;">
                            <span>Shipping</span>
                            <span style="color: #15803d; font-weight: 600;">Free</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 700; color: #0f172a; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                            <span>Total Paid</span>
                            <span>৳ ${parseFloat(ord.paid_amount).toFixed(2)}</span>
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;">${data.message || 'Failed to load order.'}</div>`;
            }
        })
        .catch(err => {
            content.innerHTML = `<div style="color: #ef4444; padding: 20px; text-align: center;">Error loading order details.</div>`;
        });
}

function closeOrderModal() {
    document.getElementById('orderModal').style.display = 'none';
}

function buyAgain(paymentId) {
    fetch('ajax/buy-again.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'payment_id=' + encodeURIComponent(paymentId)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = 'cart.php';
        } else {
            alert(data.message || 'Unable to reorder items at this moment.');
        }
    })
    .catch(() => {
        window.location.href = 'cart.php';
    });
}

function cancelOrder(paymentId) {
    if (!confirm('Are you sure you want to cancel this order?')) return;
    
    fetch('ajax/cancel-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'payment_id=' + encodeURIComponent(paymentId)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.reload();
        } else {
            alert(data.message || 'Unable to cancel this order.');
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
    });
}
</script>

<script>
// Instant client-side status filtering (no page reload)
(function () {
    var pills = document.querySelectorAll('.sn-status-pill-btn[data-filter]');
    var cards = document.querySelectorAll('.sn-order-card-detailed[data-status]');
    var empty = document.getElementById('snFilterEmpty');
    function apply(f) {
        var shown = 0;
        cards.forEach(function (c) {
            var ok = (f === 'all' || c.dataset.status === f);
            c.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        pills.forEach(function (p) { p.classList.toggle('active', p.dataset.filter === f); });
        if (empty) empty.style.display = shown ? 'none' : '';
    }
    pills.forEach(function (p) {
        p.addEventListener('click', function (e) {
            e.preventDefault();
            var f = p.dataset.filter;
            apply(f);
            try { history.replaceState(null, '', f === 'all' ? 'customer-order.php' : 'customer-order.php?status=' + f); } catch (x) {}
        });
    });
    var init = new URLSearchParams(location.search).get('status') || 'all';
    apply(init.toLowerCase());
})();
</script>
<?php require_once('footer.php'); ?>
