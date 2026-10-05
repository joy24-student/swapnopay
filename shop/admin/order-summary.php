<?php
require_once __DIR__ . '/inc/guard.php';

$reference = (string)($_GET['payment_id'] ?? $_GET['id'] ?? '');
if (!$reference && isset($_GET['order_id'])) {
    $stmt = $pdo->prepare('SELECT payment_id FROM tbl_order WHERE id=?');
    $stmt->execute([(int)$_GET['order_id']]);
    $reference = (string)$stmt->fetchColumn();
}

$stmt = $pdo->prepare('SELECT * FROM tbl_payment WHERE payment_id=?');
$stmt->execute([$reference]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

// Fetch items joined with product catalog for images and details
$stmt = $pdo->prepare('
    SELECT o.*, p.p_featured_photo, p.p_name AS catalog_name, p.p_current_price 
    FROM tbl_order o 
    LEFT JOIN tbl_product p ON o.product_id = p.p_id 
    WHERE o.payment_id = ? 
    ORDER BY o.id ASC
');
$stmt->execute([$reference]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch store settings for Logo, Phone, Address, Email, Name
$stmt = $pdo->query("SELECT * FROM tbl_settings WHERE id=1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$shopLogo = $settings['logo'] ?? '';
$shopName = $settings['meta_title_home'] ?? 'ShopNext Online Store';
$contactEmail = $settings['contact_email'] ?? 'support@shopnext.style';
$contactPhone = $settings['contact_phone'] ?? '+880 1700-123456';
$contactAddress = $settings['contact_address'] ?? 'Dhaka, Bangladesh';

// Fetch customer account details if customer_id exists
$customer = null;
if (!empty($order['customer_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ?");
    $stmt->execute([(int)$order['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Calculations
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += (float)$item['unit_price'] * (int)$item['quantity'];
}
$shippingCost = (float)($order['shipping_cost'] ?? 0);
$couponDiscount = (float)($order['coupon_discount'] ?? 0);
$paidAmount = (float)($order['paid_amount'] ?? ($subtotal + $shippingCost - $couponDiscount));

$isPaid = ($order['payment_status'] === 'Completed');
$isCash = in_array($order['payment_method'], ['COD', 'Cash on Delivery', 'Cash'], true);
$isCancelled = ($order['payment_status'] === 'Cancelled');
$amountPaid = $isPaid ? $paidAmount : ($isCash ? 0.00 : $paidAmount);
$balanceDue = max(0.00, $paidAmount - $amountPaid);

// Address resolution
$custName = $order['customer_name'] ?: ($customer['cust_name'] ?? 'Valued Customer');
$custEmail = $order['customer_email'] ?: ($customer['cust_email'] ?? '');
$custPhone = $order['shipping_phone'] ?: ($order['billing_phone'] ?: ($customer['cust_phone'] ?? ''));

$shipName = $order['shipping_name'] ?: $custName;
$shipStreet = $order['shipping_street'] ?: ($order['shipping_address'] ?: ($customer['cust_address'] ?? ''));
$shipCity = $order['shipping_city'] ?: ($customer['cust_city'] ?? '');
$shipState = $order['shipping_state'] ?: ($customer['cust_state'] ?? '');
$shipZip = $order['shipping_zip'] ?: ($customer['cust_zip'] ?? '');
$shipCountry = $order['shipping_country'] ?: ($customer['cust_country'] ?? 'Bangladesh');
$shipPhone = $order['shipping_phone'] ?: $custPhone;

$billName = $order['billing_name'] ?: $custName;
$billStreet = $order['billing_street'] ?: ($order['billing_address'] ?: $shipStreet);
$billCity = $order['billing_city'] ?: $shipCity;
$billState = $order['billing_state'] ?: $shipState;
$billZip = $order['billing_zip'] ?: $shipZip;
$billCountry = $order['billing_country'] ?: $shipCountry;
$billPhone = $order['billing_phone'] ?: $custPhone;

function receiptText($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/header.php';
?>

<style>
/* =========================================================
   EXECUTIVE ORDER RECEIPT & INVOICE STYLES
   ========================================================= */
.receipt-wrapper {
    max-width: 960px;
    margin: 0 auto;
    padding-bottom: 40px;
}
.receipt-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    flex-wrap: wrap;
    gap: 12px;
}
.receipt-sheet {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 36px 40px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    color: #1e293b;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    position: relative;
}
.receipt-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 24px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 20px;
}
.receipt-brand-logo {
    max-height: 56px;
    max-width: 220px;
    object-fit: contain;
    margin-bottom: 10px;
}
.receipt-company-info {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
}
.receipt-title-box {
    text-align: right;
}
.receipt-main-title {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.5px;
    margin: 0 0 6px;
    text-transform: uppercase;
}
.receipt-ref-code {
    font-size: 14px;
    font-family: "Courier New", Courier, monospace;
    font-weight: 700;
    color: #2563eb;
    background: #eff6ff;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #dbeafe;
    display: inline-block;
    margin-bottom: 8px;
}
.receipt-meta-pills {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    flex-wrap: wrap;
    margin-top: 6px;
}
.status-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.status-paid { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.status-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.status-cancelled { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.status-delivered { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.status-shipped { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.status-processing { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }

/* 3-Column Info Cards */
.receipt-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 28px;
}
@media (max-width: 768px) {
    .receipt-grid-3 { grid-template-columns: 1fr; }
    .receipt-title-box { text-align: left; }
    .receipt-meta-pills { justify-content: flex-start; }
}
.receipt-info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 18px;
    font-size: 13px;
    line-height: 1.6;
}
.receipt-info-card h4 {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin: 0 0 10px;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.receipt-info-card strong {
    color: #0f172a;
    font-size: 14px;
}

/* Itemized Products Table */
.receipt-table-box {
    margin-bottom: 28px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.receipt-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.receipt-table thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    padding: 12px 16px;
    border-bottom: 2px solid #e2e8f0;
    letter-spacing: 0.5px;
}
.receipt-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.receipt-table tbody tr:last-child td {
    border-bottom: none;
}
.receipt-table tbody tr:hover {
    background: #fafafa;
}
.receipt-prod-img {
    width: 58px;
    height: 58px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    flex-shrink: 0;
}
.receipt-prod-meta {
    display: flex;
    align-items: center;
    gap: 14px;
}
.receipt-badge-option {
    display: inline-block;
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    margin-right: 4px;
    margin-top: 4px;
}

/* Financial Summary & Calculations */
.receipt-bottom-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 24px;
    margin-bottom: 30px;
}
@media (max-width: 768px) {
    .receipt-bottom-grid { grid-template-columns: 1fr; }
}
.receipt-notes-panel {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    padding: 18px;
    font-size: 12px;
    color: #475569;
}
.receipt-calc-table {
    width: 100%;
    font-size: 13px;
}
.receipt-calc-table td {
    padding: 7px 0;
    vertical-align: middle;
}
.receipt-calc-table td:last-child {
    text-align: right;
    font-weight: 600;
}
.receipt-calc-total {
    border-top: 2px solid #0f172a;
    border-bottom: 2px solid #0f172a;
    padding: 12px 0 !important;
}
.receipt-calc-total td {
    font-size: 16px !important;
    font-weight: 800 !important;
    color: #0f172a !important;
}

/* Barcode simulation */
.barcode-box {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px dashed #e2e8f0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}
.barcode-lines {
    font-family: 'Libre Barcode 39', 'Code 128', monospace;
    font-size: 32px;
    letter-spacing: 4px;
    color: #0f172a;
    line-height: 1;
}

/* Footer & Signatures */
.receipt-sign-row {
    display: flex;
    justify-content: space-between;
    margin-top: 36px;
    padding-top: 24px;
    border-top: 1px solid #f1f5f9;
    font-size: 12px;
    color: #64748b;
}
.receipt-sign-box {
    text-align: center;
    width: 180px;
}
.receipt-sign-line {
    border-top: 1px solid #cbd5e1;
    margin-top: 36px;
    padding-top: 4px;
}

/* =========================================================
   PRINT SPECIFIC HIGH-FIDELITY STYLESHEET
   ========================================================= */
@media print {
    body, html {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 12px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .main-header,
    .main-sidebar,
    .main-footer,
    .content-header,
    .receipt-action-bar,
    .btn,
    .alert,
    .breadcrumb {
        display: none !important;
    }
    .content-wrapper,
    .content {
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        border: none !important;
        min-height: auto !important;
    }
    .receipt-wrapper {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .receipt-sheet {
        box-shadow: none !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 0 !important;
        padding: 24px !important;
    }
    .receipt-info-card,
    .receipt-table-box,
    .receipt-notes-panel {
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
    }
    .receipt-table thead th {
        background: #f1f5f9 !important;
        color: #000000 !important;
    }
    .no-print {
        display: none !important;
    }
}
</style>

<section class="content-header no-print">
    <h1>
        Official Order Receipt
        <small>Order #<?= receiptText($reference) ?></small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="index.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="order.php">Orders</a></li>
        <li class="active">Receipt #<?= receiptText($reference) ?></li>
    </ol>
</section>

<section class="content">
    <div class="receipt-wrapper">
        <!-- SCREEN ACTION BAR -->
        <div class="receipt-action-bar no-print">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <a class="btn btn-default btn-sm" href="order.php" style="border-radius: 6px; font-weight: 600;">
                    <i class="fa fa-arrow-left"></i> Back to Orders
                </a>
                <button class="btn btn-primary btn-sm" type="button" onclick="window.print()" style="border-radius: 6px; font-weight: 700;">
                    <i class="fa fa-print"></i> Print Receipt / Invoice
                </button>
                <button class="btn btn-success btn-sm" type="button" onclick="sendWhatsAppReceipt('<?= receiptText($reference) ?>', '<?= receiptText($shipPhone) ?>', '<?= receiptText($custName) ?>', '<?= number_format($paidAmount, 2, '.', '') ?>')" style="border-radius: 6px; font-weight: 700; background: #059669; border-color: #059669;">
                    <i class="fa fa-whatsapp"></i> Send WhatsApp Receipt
                </button>
            </div>

            <!-- QUICK WORKFLOW ACTIONS -->
            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                <?php if (!$isCancelled && $isCash && !$isPaid): ?>
                    <form method="post" action="order-change-status.php" style="display:inline-block; margin:0;">
                        <input type="hidden" name="id" value="<?= receiptText($reference) ?>">
                        <button class="btn btn-success btn-sm" type="submit" style="border-radius: 6px; font-weight: 600;">
                            <i class="fa fa-check-circle"></i> Confirm Cash Received
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!$isCancelled && ($isCash || $isPaid) && !in_array($order['shipping_status'], ['Delivered', 'Completed'], true)): ?>
                    <form method="post" action="shipping-change-status.php" style="display:inline-block; margin:0;">
                        <input type="hidden" name="id" value="<?= receiptText($reference) ?>">
                        <input type="hidden" name="task" value="<?= $order['shipping_status'] === 'Shipped' ? 'Delivered' : 'Shipped' ?>">
                        <button class="btn btn-info btn-sm" type="submit" style="border-radius: 6px; font-weight: 600;">
                            <i class="fa fa-truck"></i> <?= $order['shipping_status'] === 'Shipped' ? 'Mark as Delivered' : 'Mark as Shipped' ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!$isCancelled && !$isPaid && !in_array($order['shipping_status'], ['Shipped', 'Delivered', 'Completed'], true)): ?>
                    <a class="btn btn-danger btn-sm" href="order-delete.php?id=<?= rawurlencode($reference) ?>" onclick="return confirm('Are you sure you want to cancel this order?')" style="border-radius: 6px; font-weight: 600;">
                        <i class="fa fa-times"></i> Cancel Order
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- OFFICIAL INVOICE / RECEIPT SHEET -->
        <div class="receipt-sheet">
            <!-- HEADER: STORE IDENTITY & INVOICE META -->
            <div class="receipt-header">
                <div>
                    <?php if (!empty($shopLogo) && file_exists(__DIR__ . '/../assets/uploads/' . $shopLogo)): ?>
                        <img src="../assets/uploads/<?= receiptText($shopLogo) ?>" alt="<?= receiptText($shopName) ?>" class="receipt-brand-logo">
                    <?php else: ?>
                        <div style="font-size: 22px; font-weight: 900; color: #0f172a; margin-bottom: 8px;">
                            <i class="fa fa-shopping-bag text-primary"></i> <?= receiptText($shopName) ?>
                        </div>
                    <?php endif; ?>
                    <div class="receipt-company-info">
                        <strong><?= receiptText($shopName) ?></strong><br>
                        <?= receiptText($contactAddress) ?><br>
                        <i class="fa fa-phone text-muted"></i> <?= receiptText($contactPhone) ?> &nbsp;|&nbsp; 
                        <i class="fa fa-envelope text-muted"></i> <?= receiptText($contactEmail) ?><br>
                        <i class="fa fa-globe text-muted"></i> https://<?= receiptText($_SERVER['HTTP_HOST'] ?? 'shopnext.style') ?>
                    </div>
                </div>

                <div class="receipt-title-box">
                    <h2 class="receipt-main-title">Official Receipt</h2>
                    <div class="receipt-ref-code"><?= receiptText($reference) ?></div>
                    <div style="font-size: 13px; color: #64748b; margin-bottom: 6px;">
                        <strong>Date:</strong> <?= !empty($order['payment_date']) ? date('M d, Y · h:i A', strtotime($order['payment_date'])) : date('M d, Y') ?>
                    </div>

                    <div class="receipt-meta-pills">
                        <?php if ($order['payment_status'] === 'Completed'): ?>
                            <span class="status-pill status-paid"><i class="fa fa-check-circle"></i> Paid</span>
                        <?php elseif ($order['payment_status'] === 'Cancelled'): ?>
                            <span class="status-pill status-cancelled"><i class="fa fa-times-circle"></i> Cancelled</span>
                        <?php else: ?>
                            <span class="status-pill status-pending"><i class="fa fa-clock-o"></i> Pending Payment</span>
                        <?php endif; ?>

                        <?php if ($order['shipping_status'] === 'Delivered'): ?>
                            <span class="status-pill status-delivered"><i class="fa fa-check"></i> Delivered</span>
                        <?php elseif ($order['shipping_status'] === 'Shipped'): ?>
                            <span class="status-pill status-shipped"><i class="fa fa-truck"></i> Shipped</span>
                        <?php elseif ($order['shipping_status'] === 'Processing'): ?>
                            <span class="status-pill status-processing"><i class="fa fa-refresh fa-spin"></i> Processing</span>
                        <?php else: ?>
                            <span class="status-pill" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;"><i class="fa fa-box"></i> Pending Dispatch</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 3-COLUMN STRUCTURED DETAILS -->
            <div class="receipt-grid-3">
                <!-- Billed To -->
                <div class="receipt-info-card">
                    <h4><i class="fa fa-user text-primary"></i> Customer (Billed To)</h4>
                    <strong><?= receiptText($custName) ?></strong><br>
                    <?php if (!empty($custEmail)): ?>
                        <div style="color: #64748b; word-break: break-all;"><i class="fa fa-envelope-o text-muted"></i> <?= receiptText($custEmail) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($custPhone)): ?>
                        <div style="color: #0f172a; font-weight: 600;"><i class="fa fa-phone text-muted"></i> <?= receiptText($custPhone) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($order['customer_id'])): ?>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Account ID: #CUST-<?= (int)$order['customer_id'] ?></div>
                    <?php endif; ?>
                </div>

                <!-- Shipped To -->
                <div class="receipt-info-card">
                    <h4><i class="fa fa-map-marker text-danger"></i> Delivery Destination</h4>
                    <strong><?= receiptText($shipName) ?></strong><br>
                    <?= !empty($shipStreet) ? nl2br(receiptText($shipStreet)) . '<br>' : '' ?>
                    <?= receiptText($shipCity) ?><?= (!empty($shipState) ? ', ' . receiptText($shipState) : '') ?> <?= receiptText($shipZip) ?><br>
                    <?= receiptText($shipCountry) ?><br>
                    <div style="font-weight: 600; color: #0f172a; margin-top: 4px;"><i class="fa fa-mobile-phone text-muted"></i> Recipient Tel: <?= receiptText($shipPhone) ?></div>
                </div>

                <!-- Payment & Order Meta -->
                <div class="receipt-info-card">
                    <h4><i class="fa fa-credit-card text-success"></i> Payment & Dispatch</h4>
                    <div><strong>Method:</strong> <?= receiptText($order['payment_method'] ?: 'Online Gateway') ?></div>
                    <?php if (!empty($order['txnid'])): ?>
                        <div style="font-size: 12px; color: #475569;"><strong>Txn ID:</strong> <code style="font-size:11px;"><?= receiptText($order['txnid']) ?></code></div>
                    <?php endif; ?>
                    <?php if (!empty($order['ssl_payment_method'])): ?>
                        <div style="font-size: 12px; color: #64748b;"><strong>Gateway:</strong> <?= receiptText($order['ssl_payment_method']) ?></div>
                    <?php endif; ?>
                    <div style="margin-top: 4px;"><strong>Fulfillment:</strong> Standard Express Courier</div>
                    <div style="font-size: 11px; color: #64748b;">Invoice Currency: BDT (Bangladeshi Taka)</div>
                </div>
            </div>

            <!-- ITEMIZED PRODUCT TABLE -->
            <div class="receipt-table-box">
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">#</th>
                            <th>Product Description</th>
                            <th style="width: 130px; text-align: right;">Unit Price</th>
                            <th style="width: 80px; text-align: center;">Qty</th>
                            <th style="width: 140px; text-align: right;">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 28px; color: #94a3b8;">
                                    <i class="fa fa-cube fa-2x"></i><br>No itemized products found for this order record.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $idx = 1; foreach ($items as $item): 
                                $lineTotal = (float)$item['unit_price'] * (int)$item['quantity'];
                                $photoName = $item['p_featured_photo'] ?? '';
                                $hasPhoto = !empty($photoName) && file_exists(__DIR__ . '/../assets/uploads/' . $photoName);
                            ?>
                                <tr>
                                    <td style="text-align: center; color: #94a3b8; font-weight: 600;"><?= $idx++ ?></td>
                                    <td>
                                        <div class="receipt-prod-meta">
                                            <?php if ($hasPhoto): ?>
                                                <img src="../assets/uploads/<?= receiptText($photoName) ?>" alt="<?= receiptText($item['product_name']) ?>" class="receipt-prod-img">
                                            <?php else: ?>
                                                <div class="receipt-prod-img" style="display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 20px;">
                                                    <i class="fa fa-cube"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="color: #0f172a; font-size: 14px; display: block; line-height: 1.3;">
                                                    <?= receiptText($item['product_name']) ?>
                                                </strong>
                                                <div style="margin-top: 4px;">
                                                    <?php if (!empty($item['size'])): ?>
                                                        <span class="receipt-badge-option"><i class="fa fa-tag text-muted"></i> Size: <?= receiptText($item['size']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['color'])): ?>
                                                        <span class="receipt-badge-option"><i class="fa fa-tint text-muted"></i> Color: <?= receiptText($item['color']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['product_id'])): ?>
                                                        <span class="receipt-badge-option" style="background: #e0f2fe; color: #0369a1;">SKU/ID: #<?= (int)$item['product_id'] ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: right; font-weight: 600; color: #334155;">
                                        BDT <?= number_format((float)$item['unit_price'], 2) ?>
                                    </td>
                                    <td style="text-align: center; font-weight: 700; color: #0f172a;">
                                        <?= (int)$item['quantity'] ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 800; color: #0f172a;">
                                        BDT <?= number_format($lineTotal, 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- FINANCIAL SUMMARY & REMARKS -->
            <div class="receipt-bottom-grid">
                <!-- Left: Order Notes & Packing Barcode -->
                <div class="receipt-notes-panel">
                    <div style="font-weight: 700; color: #0f172a; font-size: 13px; margin-bottom: 8px;">
                        <i class="fa fa-info-circle text-primary"></i> Order Notes & Fulfillment Instructions
                    </div>
                    <?php if (!empty($order['payment_note']) || !empty($order['customer_note'])): ?>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px; font-style: italic; color: #1e293b;">
                            "<?= receiptText($order['payment_note'] ?: $order['customer_note']) ?>"
                        </div>
                    <?php else: ?>
                        <p style="margin: 0 0 8px; color: #64748b;">No special delivery instructions provided by the customer.</p>
                    <?php endif; ?>

                    <?php if ($isCash && !$isPaid): ?>
                        <div style="background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; padding: 8px 12px; border-radius: 6px; font-weight: 600; font-size: 12px;">
                            <i class="fa fa-money"></i> Cash On Delivery: Collect <strong>BDT <?= number_format($paidAmount, 2) ?></strong> in cash upon parcel handover.
                        </div>
                    <?php elseif ($isPaid): ?>
                        <div style="background: #f0fdf4; border: 1px solid #dcfce7; color: #166534; padding: 8px 12px; border-radius: 6px; font-weight: 600; font-size: 12px;">
                            <i class="fa fa-check-circle"></i> Payment verified in full via <?= receiptText($order['payment_method']) ?>. Hand over parcel without collection.
                        </div>
                    <?php endif; ?>

                    <div class="barcode-box">
                        <div style="font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase;">Tracking Barcode / Scan ID</div>
                        <div class="barcode-lines">||| | |||| | ||| || ||| |||| |</div>
                        <div style="font-size: 11px; font-family: monospace; color: #475569;"><?= receiptText($reference) ?></div>
                    </div>
                </div>

                <!-- Right: Calculations Table -->
                <div>
                    <table class="receipt-calc-table">
                        <tr>
                            <td style="color: #64748b;">Items Subtotal:</td>
                            <td>BDT <?= number_format($subtotal, 2) ?></td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">Shipping / Delivery Fee:</td>
                            <td>BDT <?= number_format($shippingCost, 2) ?></td>
                        </tr>
                        <?php if ($couponDiscount > 0): ?>
                            <tr>
                                <td style="color: #059669;">
                                    Coupon Discount <?= !empty($order['coupon_code']) ? '(' . receiptText($order['coupon_code']) . ')' : '' ?>:
                                </td>
                                <td style="color: #059669;">- BDT <?= number_format($couponDiscount, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="receipt-calc-total">
                            <td>Grand Total:</td>
                            <td>BDT <?= number_format($paidAmount, 2) ?></td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding-top: 10px;">Amount Paid:</td>
                            <td style="padding-top: 10px; color: #059669;">BDT <?= number_format($amountPaid, 2) ?></td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">Balance Due / COD:</td>
                            <td style="color: <?= $balanceDue > 0 ? '#dc2626' : '#64748b' ?>; font-weight: 700;">
                                BDT <?= number_format($balanceDue, 2) ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- FOOTER NOTICE & DISPATCH AUTHORIZATION -->
            <div class="receipt-sign-row">
                <div style="font-size: 11px; color: #94a3b8; line-height: 1.6; max-width: 480px;">
                    <strong>Thank you for choosing <?= receiptText($shopName) ?>!</strong><br>
                    All items are thoroughly inspected prior to packing. For returns, warranty claims, or billing inquiries, please contact our support desk with your invoice number.
                </div>
                <div class="receipt-sign-box">
                    <div class="receipt-sign-line">Authorized Signatory / QA</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MODAL: SEND WHATSAPP RECEIPT -->
<div id="receiptWaModal" class="modal fade no-print" role="dialog">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: #059669; color: #ffffff;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight: 700;"><i class="fa fa-whatsapp"></i> Send WhatsApp Receipt</h4>
            </div>
            <form onsubmit="handleSendReceiptWa(event)">
                <div class="modal-body" style="padding: 20px;">
                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600;">Customer Phone Number</label>
                        <input type="text" id="waReceiptPhone" class="form-control" required style="border-radius: 8px;">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600;">Customer Name</label>
                        <input type="text" id="waReceiptName" class="form-control" required style="border-radius: 8px;">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600;">Order Reference</label>
                        <input type="text" id="waReceiptRef" class="form-control" readonly style="border-radius: 8px; background: #f1f5f9;">
                    </div>
                    <input type="hidden" id="waReceiptAmount" value="">
                    <div id="waReceiptResult" style="display: none; padding: 10px; border-radius: 8px; font-size: 12px; margin-top: 10px;"></div>
                </div>
                <div class="modal-footer" style="background: #f8fafc;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px;">Cancel</button>
                    <button type="submit" id="btnSubmitWaReceipt" class="btn btn-success" style="border-radius: 6px; font-weight: 700; background: #059669; border-color: #059669;">
                        <i class="fa fa-paper-plane"></i> Dispatch Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto print if requested via query parameter
<?php if (!empty($_GET['print']) || !empty($_GET['auto_print'])): ?>
window.addEventListener('load', function() {
    setTimeout(function() {
        window.print();
    }, 600);
});
<?php endif; ?>

function sendWhatsAppReceipt(ref, phone, name, amount) {
    document.getElementById('waReceiptRef').value = ref;
    document.getElementById('waReceiptPhone').value = phone || '';
    document.getElementById('waReceiptName').value = name || '';
    document.getElementById('waReceiptAmount').value = amount || '';
    const resBox = document.getElementById('waReceiptResult');
    if (resBox) resBox.style.display = 'none';
    $('#receiptWaModal').modal('show');
}

async function handleSendReceiptWa(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitWaReceipt');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending via Meta Cloud...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('trigger_key', 'order_placed');
    fd.append('phone', document.getElementById('waReceiptPhone').value);
    fd.append('customer_name', document.getElementById('waReceiptName').value);
    fd.append('order_id', document.getElementById('waReceiptRef').value);
    fd.append('order_total', document.getElementById('waReceiptAmount').value);

    try {
        const res = await fetch('../marketing_api.php?action=trigger_whatsapp_event', { method: 'POST', body: fd });
        const d = await res.json();
        const resBox = document.getElementById('waReceiptResult');
        if (resBox) {
            resBox.style.display = 'block';
            if (d.status === 'success') {
                resBox.className = 'alert alert-success';
                resBox.innerHTML = '<i class="fa fa-check-circle"></i> ' + d.message + (d.wamid ? '<br><small style="font-family:monospace;">' + d.wamid + '</small>' : '');
                setTimeout(function() { $('#receiptWaModal').modal('hide'); }, 2200);
            } else {
                resBox.className = 'alert alert-danger';
                resBox.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ' + (d.message || 'Error sending notification.');
            }
        }
    } catch (err) {
        alert('Could not dispatch WhatsApp receipt.');
    } finally {
        btn.innerHTML = orig;
        btn.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
