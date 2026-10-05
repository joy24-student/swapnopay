<?php
ob_start();
session_start();
require_once("../../admin/inc/config.php");
require_once("../../admin/inc/functions.php");

// Validate customer session and cart
if (!isset($_SESSION['customer']) || !isset($_SESSION['cart_p_id']) || empty($_SESSION['cart_p_id'])) {
    header('location: ../../cart.php');
    exit;
}

$payment_data = $_SESSION['payment_data'] ?? [];
$billing_details = $_SESSION['billing_address_details'] ?? [];
$shipping_details = $_SESSION['shipping_address_details'] ?? [];

$selected_method = strip_tags($_POST['mfs_provider'] ?? ($_GET['provider'] ?? 'bKash'));
if (!in_array($selected_method, ['bKash', 'Nagad', 'Rocket', 'Upay'])) {
    $selected_method = 'bKash';
}

$customer_note = strip_tags($_POST['customer_note'] ?? '');
$tran_id = 'SWP-' . time() . '-' . mt_rand(1000, 9999);
$order_number = 'ORD-' . date('Ymd') . '-' . mt_rand(1000, 9999);
$payment_date = date('Y-m-d H:i:s');
$total_amount = (float)($payment_data['overall_total'] ?? 0);

// ----------------------------------------------------------------------------
// 1. Insert into Supabase Orders & Order Items
// ----------------------------------------------------------------------------
// 1. Insert into Supabase Orders & Order Items
// ----------------------------------------------------------------------------
$supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : getenv('SUPABASE_URL');
$supabase_service_key = defined('SUPABASE_SERVICE_KEY') && !empty(SUPABASE_SERVICE_KEY) 
    ? SUPABASE_SERVICE_KEY 
    : getenv('SUPABASE_SERVICE_KEY');

$supabase_order_id = null;
$gateway_order_id = null;

// Retrieve merchant settings from tbl_settings if not set in runtime/constants
$merchant_id = $runtime['merchant_id'] ?? (defined('MERCHANT_ID') ? MERCHANT_ID : (getenv('MERCHANT_ID') ?: null));
$gateway_api_key = trim((string)($runtime['gateway_api_key'] ?? (defined('SWAPNOPAY_API_KEY') ? SWAPNOPAY_API_KEY : (getenv('SWAPNOPAY_API_KEY') ?: ''))));
$api_url = defined('SWAPNOPAY_API_URL') && !empty(SWAPNOPAY_API_URL) ? SWAPNOPAY_API_URL : 'https://api.swapnopay.top';

try {
    $stmt_sett = $pdo->query("SELECT swapnopay_merchant_id, swapnopay_api_key, swapnopay_api_url FROM tbl_settings WHERE id=1");
    if ($stmt_sett && $sett_row = $stmt_sett->fetch(PDO::FETCH_ASSOC)) {
        if (empty($merchant_id) && !empty($sett_row['swapnopay_merchant_id'])) {
            $merchant_id = trim($sett_row['swapnopay_merchant_id']);
        }
        if (empty($gateway_api_key) && !empty($sett_row['swapnopay_api_key'])) {
            $gateway_api_key = trim($sett_row['swapnopay_api_key']);
        }
        if (!empty($sett_row['swapnopay_api_url'])) {
            $api_url = rtrim(trim($sett_row['swapnopay_api_url']), '/');
        }
    }
} catch (Throwable $e) {}

if (!$merchant_id) {
    http_response_code(503);
    exit('This store is not connected to a payment merchant. Please set Merchant ID in Payment Settings.');
}

// If dedicated Supabase and service key are available, attempt direct insert
if (!empty($supabase_url) && !empty($supabase_service_key)) {
    try {
        $clean_supabase_url = rtrim($supabase_url, '/');
        $order_payload = json_encode([
            'merchant_id' => $merchant_id,
            'tran_id' => $tran_id,
            'order_number' => $order_number,
            'amount' => $total_amount,
            'total_amount' => $total_amount,
            'subtotal' => (float)($payment_data['paid_amount'] ?? $total_amount),
            'shipping_cost' => (float)($payment_data['shipping_cost'] ?? 0),
            'discount_amount' => (float)($payment_data['coupon_discount'] ?? 0),
            'cus_phone' => (string)($shipping_details['phone'] ?? ($billing_details['phone'] ?? '01700000000')),
            'cus_name' => (string)($payment_data['customer_name'] ?? 'Customer'),
            'cus_email' => (string)($payment_data['customer_email'] ?? ''),
            'shipping_address' => (string)($shipping_details['address'] ?? ''),
            'shipping_city' => (string)($shipping_details['city'] ?? ''),
            'payment_method' => $selected_method,
            'status' => 'PENDING',
            'customer_note' => $customer_note,
            'expires_at' => date('c', strtotime('+15 minutes')) // 15-minute verification window
        ]);

        $ch_order = curl_init("{$clean_supabase_url}/rest/v1/orders");
        curl_setopt($ch_order, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_order, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch_order, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch_order, CURLOPT_POST, true);
        curl_setopt($ch_order, CURLOPT_POSTFIELDS, $order_payload);
        curl_setopt($ch_order, CURLOPT_HTTPHEADER, [
            "apikey: {$supabase_service_key}",
            "Authorization: Bearer {$supabase_service_key}",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ]);
        $res_order = curl_exec($ch_order);
        $order_http_code = (int)curl_getinfo($ch_order, CURLINFO_HTTP_CODE);
        curl_close($ch_order);

        $inserted_order = json_decode($res_order, true);
        $supabase_order_id = !empty($inserted_order[0]['id']) ? $inserted_order[0]['id'] : null;
        $gateway_order_id = $supabase_order_id;

        if ($supabase_order_id) {
            $items_payload = [];
            foreach($payment_data['cart_p_id'] as $key => $pid) {
                $items_payload[] = [
                    'order_id' => $supabase_order_id,
                    'product_name' => (string)($payment_data['cart_p_name'][$key] ?? 'Product'),
                    'size' => (string)($payment_data['cart_size_name'][$key] ?? ''),
                    'color' => (string)($payment_data['cart_color_name'][$key] ?? ''),
                    'quantity' => (int)($payment_data['cart_p_qty'][$key] ?? 1),
                    'unit_price' => (float)($payment_data['cart_p_current_price'][$key] ?? 0),
                    'total_price' => (float)(($payment_data['cart_p_qty'][$key] ?? 1) * ($payment_data['cart_p_current_price'][$key] ?? 0))
                ];
            }

            $ch_items = curl_init("{$clean_supabase_url}/rest/v1/order_items");
            curl_setopt($ch_items, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_items, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch_items, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch_items, CURLOPT_POST, true);
            curl_setopt($ch_items, CURLOPT_POSTFIELDS, json_encode($items_payload));
            curl_setopt($ch_items, CURLOPT_HTTPHEADER, [
                "apikey: {$supabase_service_key}",
                "Authorization: Bearer {$supabase_service_key}",
                "Content-Type: application/json"
            ]);
            curl_exec($ch_items);
            curl_close($ch_items);
        }
    } catch (Exception $e) {
        error_log("SwapnoPay Supabase direct order insert notice: " . $e->getMessage());
    }
}

// Fallback or Hosted Storefront Mode: Call SwapnoPay Central Gateway API
if (!$supabase_order_id) {
    try {
        $gateway_api_key = trim((string)($runtime['gateway_api_key'] ?? (defined('SWAPNOPAY_API_KEY') ? SWAPNOPAY_API_KEY : getenv('SWAPNOPAY_API_KEY'))));
        $api_url = defined('SWAPNOPAY_API_URL') && !empty(SWAPNOPAY_API_URL) ? SWAPNOPAY_API_URL : 'https://api.swapnopay.top';

        if ($merchant_id && $gateway_api_key !== '') {
            $items_payload = [];
            if (!empty($payment_data['cart_p_id'])) {
                foreach($payment_data['cart_p_id'] as $key => $pid) {
                    $items_payload[] = [
                        'product_name' => (string)($payment_data['cart_p_name'][$key] ?? 'Product'),
                        'size' => (string)($payment_data['cart_size_name'][$key] ?? ''),
                        'color' => (string)($payment_data['cart_color_name'][$key] ?? ''),
                        'quantity' => (int)($payment_data['cart_p_qty'][$key] ?? 1),
                        'unit_price' => (float)($payment_data['cart_p_current_price'][$key] ?? 0),
                        'total_price' => (float)(($payment_data['cart_p_qty'][$key] ?? 1) * ($payment_data['cart_p_current_price'][$key] ?? 0))
                    ];
                }
            }

            $api_payload = json_encode([
                'merchant_id' => $merchant_id,
                'tran_id' => $tran_id,
                'order_number' => $order_number,
                'amount' => $total_amount,
                'cus_phone' => (string)($shipping_details['phone'] ?? ($billing_details['phone'] ?? '01700000000')),
                'cus_name' => (string)($payment_data['customer_name'] ?? 'Customer'),
                'cus_email' => (string)($payment_data['customer_email'] ?? ''),
                'payment_method' => $selected_method,
                'items' => $items_payload
            ]);

            $ch_api = curl_init("{$api_url}/v1/payment/create-order");
            curl_setopt($ch_api, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_api, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch_api, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch_api, CURLOPT_POST, true);
            curl_setopt($ch_api, CURLOPT_POSTFIELDS, $api_payload);
            curl_setopt($ch_api, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "X-API-Key: {$gateway_api_key}",
                "X-Merchant-ID: {$merchant_id}"
            ]);
            $res_api = curl_exec($ch_api);
            $http_code = (int)curl_getinfo($ch_api, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch_api);
            curl_close($ch_api);

            $created_order = json_decode($res_api, true);
            if ($res_api !== false && $http_code >= 200 && $http_code < 300 && !empty($created_order['order_id'])) {
                $gateway_order_id = $created_order['order_id'];
                $supabase_order_id = $gateway_order_id;
            } else {
                error_log("SwapnoPay create-order notice (HTTP {$http_code}): " . ($curl_error ?: ($created_order['error'] ?? 'Gateway rejected order')));
            }
        }
    } catch (Exception $e) {
        error_log("SwapnoPay Gateway API order creation error: " . $e->getMessage());
    }
}

if (!$supabase_order_id && !$gateway_order_id) {
    // If external APIs are unavailable, allow order to proceed with local transaction ID so customer can still complete payment
    $gateway_order_id = $tran_id;
}
// ----------------------------------------------------------------------------
// 2. Insert into Legacy Tables for Local Compatibility
// ----------------------------------------------------------------------------
try {
    $pdo->beginTransaction();
    $stmt_pay = $pdo->prepare("INSERT INTO tbl_payment (
        customer_id, customer_name, customer_email, payment_date, 
        txnid, paid_amount, shipping_cost, coupon_code, coupon_discount, 
        payment_method, payment_status, shipping_status, payment_id, payment_note, 
        card_number, bank_transaction_info,                 
        billing_name, billing_email, billing_phone, billing_street, billing_city, 
        billing_state, billing_country, billing_zip, shipping_name, shipping_email, 
        shipping_phone, shipping_street, shipping_city, shipping_state, shipping_country, shipping_zip
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt_pay->execute([
        $payment_data['customer_id'] ?? 0,
        $payment_data['customer_name'] ?? 'Customer',
        $payment_data['customer_email'] ?? '',
        $payment_date,
        '',
        $total_amount,
        $payment_data['shipping_cost'] ?? 0,
        $payment_data['coupon_code'] ?? '',
        $payment_data['coupon_discount'] ?? 0,
        $selected_method,
        'Pending',
        'Pending',
        $tran_id,
        $customer_note,
        '', '',
        $billing_details['name'] ?? '',
        $payment_data['customer_email'] ?? '',
        $billing_details['phone'] ?? '',
        $billing_details['address'] ?? '',
        $billing_details['city'] ?? '',
        $billing_details['state'] ?? '',
        $billing_details['country_id'] ?? '',
        $billing_details['zip'] ?? '',
        $shipping_details['name'] ?? '',
        $payment_data['customer_email'] ?? '',
        $shipping_details['phone'] ?? '',
        $shipping_details['address'] ?? '',
        $shipping_details['city'] ?? '',
        $shipping_details['state'] ?? '',
        $shipping_details['country_id'] ?? '',
        $shipping_details['zip'] ?? ''
    ]);

    foreach($payment_data['cart_p_id'] as $key => $product_id) {
        $stmt_item = $pdo->prepare("INSERT INTO tbl_order (
            cust_id, product_id, product_name, size, color, quantity, unit_price, payment_id, coupon_code, coupon_discount
        ) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt_item->execute([
            $payment_data['customer_id'] ?? 0,           
            $product_id,
            $payment_data['cart_p_name'][$key] ?? '',
            $payment_data['cart_size_name'][$key] ?? '',
            $payment_data['cart_color_name'][$key] ?? '',
            $payment_data['cart_p_qty'][$key] ?? 1,
            $payment_data['cart_p_current_price'][$key] ?? 0,
            $tran_id,
            $payment_data['coupon_code'] ?? '',
            $payment_data['coupon_discount'] ?? 0
        ]);
    }
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Legacy payment table insert skipped: " . $e->getMessage());
    http_response_code(500);
    exit('Your order could not be saved. No payment has been requested; please try again.');
}

// Store pending order details in session
$_SESSION['pending_tran_id'] = $tran_id;
$_SESSION['pending_gateway_order_id'] = $gateway_order_id ?: $tran_id;
$_SESSION['pending_order_number'] = $order_number;
$_SESSION['pending_method'] = $selected_method;
$_SESSION['pending_amount'] = $total_amount;

// Redirect to real-time verification screen
header("Location: verify.php?tran_id={$tran_id}&method={$selected_method}");
exit;

