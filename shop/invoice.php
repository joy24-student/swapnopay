<?php
/**
 * Standalone Customer & Public Order Receipt / Invoice
 * Pixel-Perfect matching media_1791282249465_a858b34e.png
 */
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("admin/inc/config.php");
require_once("admin/inc/functions.php");

$reference = trim((string)($_GET['payment_id'] ?? ($_GET['id'] ?? ($_GET['order_id'] ?? ''))));

if (empty($reference)) {
    header("Location: index.php");
    exit;
}

// Fetch payment & order record
$stmt = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ? OR txnid = ? LIMIT 1");
$stmt->execute([$reference, $reference]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Receipt Not Found</title><link rel="stylesheet" href="assets/css/bootstrap.min.css"></head><body style="padding:50px; text-align:center;"><h2>Order Receipt Not Found</h2><p>We could not find an order matching reference: ' . htmlspecialchars($reference) . '</p><a href="index.php" class="btn btn-primary">Return to Store</a></body></html>';
    exit;
}

$paymentId = $order['payment_id'];

// Fetch items with catalog details
$stmt_items = $pdo->prepare("
    SELECT o.*, p.p_featured_photo, p.p_name AS catalog_name
    FROM tbl_order o
    LEFT JOIN tbl_product p ON o.product_id = p.p_id
    WHERE o.payment_id = ?
    ORDER BY o.id ASC
");
$stmt_items->execute([$paymentId]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Fetch store settings for Logo and Store identity
$stmt_settings = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1");
$settings = $stmt_settings ? $stmt_settings->fetch(PDO::FETCH_ASSOC) : [];

// Fetch customer account details if customer_id exists
$customer = null;
if (!empty($order['customer_id'])) {
    $stmt_cust = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ?");
    $stmt_cust->execute([(int)$order['customer_id']]);
    $customer = $stmt_cust->fetch(PDO::FETCH_ASSOC);
}

// Print mode
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt <?= htmlspecialchars($paymentId) ?> - <?= htmlspecialchars($settings['store_name'] ?? ($settings['meta_title_home'] ?? (defined('STORE_NAME') ? STORE_NAME : 'Store'))) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/uploads/<?= htmlspecialchars($settings['favicon'] ?? 'favicon.png') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body {
            background-color: #f8fafc;
            margin: 0;
            padding: 20px 10px 40px 10px;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .sn-nav-action-bar {
            max-width: 1040px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            flex-wrap: wrap;
            gap: 10px;
        }

        .sn-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }

        .sn-btn-primary {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        .sn-btn-primary:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        .sn-btn-default {
            background: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }

        .sn-btn-default:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .sn-nav-action-bar,
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body <?= $isPrint ? 'onload="window.print()"' : '' ?>>

    <!-- Top Action Bar (Hidden when printed) -->
    <div class="sn-nav-action-bar no-print">
        <div style="display:flex; align-items:center; gap:8px;">
            <a href="<?= isset($_SESSION['customer']) ? 'customer-order.php' : 'index.php' ?>" class="sn-btn-action sn-btn-default">
                <i class="fa fa-arrow-left"></i> <?= isset($_SESSION['customer']) ? 'My Orders' : 'Continue Shopping' ?>
            </a>
            <span style="font-size:13px; color:#64748b; margin-left:8px;">
                Receipt Reference: <strong style="color:#0f172a;"><?= htmlspecialchars($paymentId) ?></strong>
            </span>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" class="sn-btn-action sn-btn-primary" onclick="window.print()">
                <i class="fa fa-print"></i> Print Receipt / Invoice
            </button>
            <a href="index.php" class="sn-btn-action sn-btn-default">
                <i class="fa fa-shopping-bag"></i> Store Home
            </a>
        </div>
    </div>

    <!-- Official Pixel-Perfect Order Receipt -->
    <?php require_once(__DIR__ . '/inc/order_receipt_view.php'); ?>

</body>
</html>

