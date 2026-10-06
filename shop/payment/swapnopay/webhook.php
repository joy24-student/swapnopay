<?php
// SwapnoPay Webhook Receiver for E-Commerce Shop
// Receives instant payment events from SwapnoPay backend / Supabase SMS edge functions
header('Content-Type: application/json');

require_once("../../admin/inc/config.php");
require_once("../../admin/inc/functions.php");

// 1. Read input payload and authenticate request
$raw_input = file_get_contents('php://input');
$payload = json_decode($raw_input, true);

if (!$payload) {
    // Fallback to $_POST
    $payload = $_POST;
}

$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? ($_SERVER['HTTP_X_SWAPNOPAY_SIGNATURE'] ?? '');
$header_secret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
$configured_secret = getenv('SWAPNOPAY_WEBHOOK_SECRET') ?: (defined('SWAPNOPAY_WEBHOOK_SECRET') ? SWAPNOPAY_WEBHOOK_SECRET : ($runtime['webhook_secret'] ?? ''));

if (empty($configured_secret)) {
    try {
        $stmt_sett = $pdo->query("SELECT swapnopay_webhook_secret FROM tbl_settings WHERE id=1");
        if ($stmt_sett && $sett_row = $stmt_sett->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($sett_row['swapnopay_webhook_secret'])) {
                $configured_secret = trim($sett_row['swapnopay_webhook_secret']);
            }
        }
    } catch (Throwable $e) {}
}

// Webhook secret MUST be configured and request MUST provide valid authentication
if (empty($configured_secret)) {
    error_log("[swapnopay-webhook] Rejecting webhook: SWAPNOPAY_WEBHOOK_SECRET is not configured.");
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: Webhook secret not configured on server']);
    exit;
}

$hmac_valid = !empty($signature) && hash_equals(hash_hmac('sha256', $raw_input, $configured_secret), $signature);
$secret_valid = !empty($header_secret) && hash_equals($configured_secret, $header_secret);
if (!$hmac_valid && !$secret_valid) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized: Invalid or missing webhook signature']);
    exit;
}

$tran_id = strip_tags($payload['tran_id'] ?? ($payload['order_id'] ?? ''));
$status = strtoupper(strip_tags($payload['status'] ?? ''));
$trx_id = strip_tags($payload['trx_id'] ?? '');
$payment_method = strip_tags($payload['payment_method'] ?? 'SwapnoPay');
$amount = (float)($payload['amount'] ?? 0);

if (empty($tran_id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing tran_id']);
    exit;
}

// 2. Fetch payment record
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ? OR txnid = ? LIMIT 1");
    $stmt->execute([$tran_id, $tran_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => "Order not found for tran_id: {$tran_id}"]);
        exit;
    }

    if ($status === 'PAID' || $status === 'COMPLETED') {
        // Mark as completed
        $update_stmt = $pdo->prepare("UPDATE tbl_payment SET 
            payment_status = 'Completed', 
            shipping_status = 'Pending',
            txnid = CASE WHEN ? != '' THEN ? ELSE txnid END,
            payment_method = CASE WHEN ? != '' THEN ? ELSE payment_method END
            WHERE payment_id = ?");
        $update_stmt->execute([$trx_id, $trx_id, $payment_method, $payment_method, $tran_id]);

        // Decrement product inventory if not already done
        if ($payment['payment_status'] !== 'Completed') {
            $order_stmt = $pdo->prepare("SELECT product_id, quantity FROM tbl_order WHERE payment_id = ?");
            $order_stmt->execute([$tran_id]);
            $items = $order_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                if (!empty($item['product_id']) && !empty($item['quantity'])) {
                    $stock_stmt = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ?");
                    $stock_stmt->execute([(int)$item['quantity'], (int)$item['product_id']]);
                }
            }
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Payment verified and order updated to Completed',
            'tran_id' => $tran_id,
            'status' => 'Completed',
            'trx_id' => $trx_id
        ]);
        exit;
    } elseif ($status === 'FAILED' || $status === 'CANCELLED') {
        $update_stmt = $pdo->prepare("UPDATE tbl_payment SET payment_status = 'Failed' WHERE payment_id = ?");
        $update_stmt->execute([$tran_id]);

        echo json_encode([
            'ok' => true,
            'message' => 'Order updated to Failed',
            'tran_id' => $tran_id,
            'status' => 'Failed'
        ]);
        exit;
    } else {
        echo json_encode([
            'ok' => true,
            'message' => 'Webhook received, no status change required',
            'tran_id' => $tran_id,
            'current_status' => $payment['payment_status']
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
