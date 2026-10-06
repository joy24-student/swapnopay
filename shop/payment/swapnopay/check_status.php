<?php
ob_start();
session_start();
require_once("../../admin/inc/config.php");

header('Content-Type: application/json');

$tran_id = strip_tags($_GET['tran_id'] ?? '');
if (empty($tran_id)) {
    echo json_encode(['status' => 'ERROR', 'message' => 'Missing tran_id']);
    exit;
}

$supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : getenv('SUPABASE_URL');
$supabase_key = defined('SUPABASE_ANON_KEY') && !empty(SUPABASE_ANON_KEY) 
    ? SUPABASE_ANON_KEY 
    : (getenv('SUPABASE_ANON_KEY') ?: (defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : getenv('SUPABASE_SERVICE_KEY')));

$api_url = defined('SWAPNOPAY_API_URL') && !empty(SWAPNOPAY_API_URL) ? SWAPNOPAY_API_URL : 'https://api.swapnopay.top';
$merchant_id = defined('MERCHANT_ID') && !empty(MERCHANT_ID) ? MERCHANT_ID : ($runtime['merchant_id'] ?? '');
$gateway_order_id = (!empty($_SESSION['pending_tran_id']) && hash_equals((string)$_SESSION['pending_tran_id'], $tran_id))
    ? (string)($_SESSION['pending_gateway_order_id'] ?? $tran_id)
    : $tran_id;

$status = 'PENDING';
$paid_amount = 0;
$paid_at = null;
$trx_id = '';

// Check 1: Direct Supabase Connection (if available)
if (!empty($supabase_url) && !empty($supabase_key)) {
    $clean_supabase_url = rtrim($supabase_url, '/');
    $orderQuery = 'tran_id=eq.' . rawurlencode($tran_id) . '&select=status,paid_at,payment_method,amount,matched_trx_id&limit=1';
    if (!empty($merchant_id)) $orderQuery .= '&merchant_id=eq.' . rawurlencode($merchant_id);
    $ch = curl_init("{$clean_supabase_url}/rest/v1/orders?{$orderQuery}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: {$supabase_key}",
        "Authorization: Bearer {$supabase_key}"
    ]);
    $res = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    $orders = json_decode($res, true);
    if (!empty($orders[0])) {
        $status = $orders[0]['status'] ?? 'PENDING';
        $paid_amount = $orders[0]['amount'] ?? 0;
        $paid_at = $orders[0]['paid_at'] ?? null;
        $trx_id = $orders[0]['matched_trx_id'] ?? '';
    } elseif ($res === false || $http_code < 200 || $http_code >= 300) {
        $status = 'ERROR';
        $status_message = $curl_error ?: 'Merchant payment database is unavailable';
    }
} else {
    // Check 2: SwapnoPay Central Gateway Status Endpoint
    $query = "order_id=" . urlencode($gateway_order_id);
    if (!empty($merchant_id)) {
        $query .= "&merchant_id=" . urlencode($merchant_id);
    }
    // Also try tran_id as fallback identifier
    $query .= "&tran_id=" . urlencode($tran_id);
    $ch = curl_init("{$api_url}/v1/payment/check-status?{$query}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    $gateway_res = json_decode($res, true);
    if ($res !== false && $http_code >= 200 && $http_code < 300 && !empty($gateway_res)) {
        // Normalize response: backend may return status in different formats
        $raw_status = $gateway_res['status'] ?? ($gateway_res['order_status'] ?? '');
        if (strtoupper($raw_status) === 'PAID' || strtoupper($raw_status) === 'COMPLETED') {
            $status = 'PAID';
        } elseif (!empty($raw_status)) {
            $status = strtoupper($raw_status);
        }
        $paid_amount = $gateway_res['amount'] ?? ($gateway_res['paid_amount'] ?? 0);
        $paid_at = $gateway_res['paid_at'] ?? ($gateway_res['payment_time'] ?? null);
        $trx_id = $gateway_res['trx_id'] ?? ($gateway_res['matched_trx_id'] ?? '');
    } else {
        $status = $http_code === 404 ? 'NOT_FOUND' : 'ERROR';
        $status_message = $curl_error ?: ($gateway_res['error'] ?? 'Payment gateway status could not be checked');
    }
}

// Check 3: If status is PAID, atomically update local tbl_payment and inventory if not yet marked Completed!
if ($status === 'PAID') {
    try {
        $pdo->beginTransaction();
        $stmt_check = $pdo->prepare("SELECT payment_status, paid_amount FROM tbl_payment WHERE payment_id = ? LIMIT 1 FOR UPDATE");
        $stmt_check->execute([$tran_id]);
        $row_pay = $stmt_check->fetch();
        if ($row_pay && abs((float)$row_pay['paid_amount'] - (float)$paid_amount) > 0.01) {
            $pdo->rollBack();
            $status = 'ERROR';
            $status_message = 'The gateway amount does not match this order';
        } elseif ($row_pay && $row_pay['payment_status'] !== 'Completed') {
            $stmt_up = $pdo->prepare("UPDATE tbl_payment SET payment_status = 'Completed', shipping_status = 'Pending', bank_transaction_info = ? WHERE payment_id = ? AND payment_status <> 'Completed'");
            $stmt_up->execute([$trx_id ?: 'PAID_GATEWAY', $tran_id]);

            if ($stmt_up->rowCount() === 1) {
                // Deduct stock only in the same transaction as the first paid transition.
                $stmt_items = $pdo->prepare("SELECT product_id, quantity FROM tbl_order WHERE payment_id = ?");
                $stmt_items->execute([$tran_id]);
                foreach ($stmt_items->fetchAll() as $item) {
                    $stmt_stock = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ?");
                    $stmt_stock->execute([(int)$item['quantity'], (int)$item['product_id']]);
                }
            }
            $pdo->commit();
        } else {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Local payment completion sync error: " . $e->getMessage());
    }

    echo json_encode([
        'status' => 'PAID',
        'amount' => $paid_amount,
        'paid_at' => $paid_at,
        'trx_id' => $trx_id
    ]);
    exit;
}

// Fallback to local table status if already Completed locally
try {
    $stmt = $pdo->prepare("SELECT payment_status, paid_amount FROM tbl_payment WHERE payment_id = ? LIMIT 1");
    $stmt->execute([$tran_id]);
    $row = $stmt->fetch();
    if ($row && $row['payment_status'] === 'Completed') {
        echo json_encode(['status' => 'PAID', 'amount' => $row['paid_amount']]);
        exit;
    }
} catch (Exception $e) {}

echo json_encode(['status' => $status, 'message' => $status_message ?? null]);
