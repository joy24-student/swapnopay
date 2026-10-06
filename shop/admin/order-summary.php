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
$shopName = $settings['store_name'] ?? ($settings['meta_title_home'] ?? (defined('STORE_NAME') ? STORE_NAME : 'Online Store'));
$contactEmail = $settings['contact_email'] ?? ('support@' . ($_SERVER['HTTP_HOST'] ?? 'store.com'));
$contactPhone = $settings['contact_phone'] ?? '+880 1700-000000';
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

if (isset($_GET['print']) && $_GET['print'] == '1') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Receipt - <?= receiptText($reference) ?></title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <style>
            body { margin: 0; padding: 24px 12px; background: #f8fafc; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #0f172a; }
            .print-floating-bar {
                position: fixed;
                top: 16px;
                right: 20px;
                display: flex;
                gap: 8px;
                z-index: 99999;
                background: #ffffff;
                padding: 6px 12px;
                border-radius: 8px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.12);
                border: 1px solid #cbd5e1;
            }
            .print-btn-main {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 7px 16px;
                background: #2563eb;
                color: #ffffff;
                border: none;
                border-radius: 6px;
                font-weight: 700;
                font-size: 13px;
                cursor: pointer;
            }
            .print-btn-main:hover { background: #1d4ed8; }
            .print-btn-close {
                display: inline-flex;
                align-items: center;
                padding: 7px 14px;
                background: #f1f5f9;
                color: #334155;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                font-weight: 600;
                font-size: 13px;
                cursor: pointer;
            }
            @media print {
                .print-floating-bar { display: none !important; }
                body { padding: 0 !important; background: #ffffff !important; }
            }
        </style>
    </head>
    <body>
        <div class="print-floating-bar no-print">
            <button type="button" class="print-btn-main" onclick="window.print()">
                <i class="fa fa-print"></i> Print Document
            </button>
            <button type="button" class="print-btn-close" onclick="window.close()">
                Close
            </button>
        </div>
        <?php require_once __DIR__ . '/../inc/order_receipt_view.php'; ?>
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 400);
            });
        </script>
    </body>
    </html>
    <?php
    exit;
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

/* =========================================================
   PRINT SPECIFIC HIGH-FIDELITY STYLESHEET (ADMIN PANEL VIEW)
   ========================================================= */
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

    /* Total AdminLTE reset: eliminate 230px sidebar offset and padding */
    .wrapper,
    .content-wrapper,
    .right-side,
    .main-content,
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
        transform: none !important;
    }

    /* Hide all admin controls, chrome, menus and modal overlays */
    .main-header,
    .main-sidebar,
    .left-side,
    .main-footer,
    .content-header,
    .receipt-action-bar,
    .btn,
    .alert,
    .breadcrumb,
    .modal,
    .modal-backdrop,
    .no-print {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .receipt-wrapper {
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
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

        <!-- OFFICIAL PIXEL-PERFECT INVOICE / RECEIPT SHEET -->
        <?php require __DIR__ . '/../inc/order_receipt_view.php'; ?>
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
