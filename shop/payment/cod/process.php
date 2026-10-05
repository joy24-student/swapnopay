<?php
ob_start();
session_start();
require_once __DIR__ . '/../../admin/inc/config.php';
require_once __DIR__ . '/../../admin/inc/functions.php';
require_once __DIR__ . '/../../admin/inc/CSRF_Protect.php';

// Validate customer session
if (empty($_SESSION['customer']['cust_id'])) {
    header('Location: ../../login.php');
    exit;
}

// CSRF check
$csrf = new CSRF_Protect();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf->checkToken()) {
    http_response_code(403);
    exit('Your checkout session expired. Return to checkout and try again.');
}

// Validate cart data
$payment_data = $_SESSION['payment_data'] ?? [];
$cart_ids = $_SESSION['cart_p_id'] ?? ($payment_data['cart_p_id'] ?? []);
if (empty($cart_ids) || !is_array($cart_ids)) {
    header('Location: ../../cart.php');
    exit;
}

$billing_details = $_SESSION['billing_address_details'] ?? [];
$shipping_details = $_SESSION['shipping_address_details'] ?? [];
$customer = $_SESSION['customer'];

$customer_id = (int)$customer['cust_id'];
$customer_name = (string)($customer['cust_name'] ?? ($billing_details['name'] ?? 'Customer'));
$customer_email = (string)($customer['cust_email'] ?? '');
$customer_note = substr(strip_tags((string)($_POST['customer_note'] ?? '')), 0, 2000);
$payment_date = date('Y-m-d H:i:s');
$payment_id = 'COD-' . time() . '-' . $customer_id . '-' . mt_rand(1000, 9999);

$shipping_cost = (float)($payment_data['shipping_cost'] ?? ($_SESSION['shipping_cost'] ?? 0));
$coupon_code = (string)($payment_data['coupon_code'] ?? ($_SESSION['coupon_code'] ?? ''));
$coupon_discount = (float)($payment_data['coupon_discount'] ?? ($_SESSION['coupon_discount'] ?? 0));
$coupon_id = $payment_data['coupon_id'] ?? ($_SESSION['coupon_id'] ?? null);

// Calculate overall total from cart items if not present in payment_data
$calculated_total = 0;
foreach ($cart_ids as $key => $p_id) {
    $qty = (int)($_SESSION['cart_p_qty'][$key] ?? ($payment_data['cart_p_qty'][$key] ?? 1));
    $unit_price = (float)($_SESSION['cart_p_current_price'][$key] ?? ($payment_data['cart_p_current_price'][$key] ?? 0));
    $calculated_total += ($qty * $unit_price);
}
$overall_total = (float)($payment_data['overall_total'] ?? max(0, ($calculated_total + $shipping_cost) - $coupon_discount));

try {
    $pdo->beginTransaction();

    // 1. Insert into tbl_payment (32 columns matching Postgres schema)
    $stmt_payment = $pdo->prepare("INSERT INTO tbl_payment (
        customer_id, customer_name, customer_email, payment_date, 
        txnid, paid_amount, shipping_cost, coupon_code, coupon_discount, 
        payment_method, payment_status, shipping_status, payment_id, payment_note, 
        card_number, bank_transaction_info,                 
        billing_name, billing_email, billing_phone, billing_street, billing_city, 
        billing_state, billing_country, billing_zip, shipping_name, shipping_email, 
        shipping_phone, shipping_street, shipping_city, shipping_state, shipping_country, shipping_zip
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt_payment->execute([
        $customer_id,
        $customer_name,
        $customer_email,
        $payment_date,
        '', // txnid
        $overall_total,
        $shipping_cost,
        $coupon_code,
        $coupon_discount,
        'Cash on Delivery',
        'Pending', // payment_status
        'Pending', // shipping_status
        $payment_id,
        $customer_note,
        '', // card_number
        '', // bank_transaction_info
        $billing_details['name'] ?? $customer_name,
        $customer_email,
        $billing_details['phone'] ?? '',
        $billing_details['address'] ?? '',
        $billing_details['city'] ?? '',
        $billing_details['state'] ?? '',
        (string)($billing_details['country_id'] ?? ''),
        $billing_details['zip'] ?? '',
        $shipping_details['name'] ?? $customer_name,
        $customer_email,
        $shipping_details['phone'] ?? ($billing_details['phone'] ?? ''),
        $shipping_details['address'] ?? ($billing_details['address'] ?? ''),
        $shipping_details['city'] ?? ($billing_details['city'] ?? ''),
        $shipping_details['state'] ?? ($billing_details['state'] ?? ''),
        (string)($shipping_details['country_id'] ?? ($billing_details['country_id'] ?? '')),
        $shipping_details['zip'] ?? ($billing_details['zip'] ?? '')
    ]);

    // 2. Insert into tbl_order and decrement product stock
    $stmt_order = $pdo->prepare("INSERT INTO tbl_order (
        cust_id, product_id, product_name, size, color, quantity, unit_price, payment_id, coupon_code, coupon_discount
    ) VALUES (?,?,?,?,?,?,?,?,?,?)");

    $stmt_stock = $pdo->prepare("UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ?");

    foreach ($cart_ids as $key => $product_id) {
        $p_name = (string)($_SESSION['cart_p_name'][$key] ?? ($payment_data['cart_p_name'][$key] ?? 'Product'));
        $p_size = (string)($_SESSION['cart_size_name'][$key] ?? ($payment_data['cart_size_name'][$key] ?? ''));
        $p_color = (string)($_SESSION['cart_color_name'][$key] ?? ($payment_data['cart_color_name'][$key] ?? ''));
        $p_qty = max(1, (int)($_SESSION['cart_p_qty'][$key] ?? ($payment_data['cart_p_qty'][$key] ?? 1)));
        $p_price = (float)($_SESSION['cart_p_current_price'][$key] ?? ($payment_data['cart_p_current_price'][$key] ?? 0));

        $stmt_order->execute([
            $customer_id,
            (int)$product_id,
            $p_name,
            $p_size,
            $p_color,
            $p_qty,
            $p_price,
            $payment_id,
            $coupon_code,
            $coupon_discount
        ]);

        $stmt_stock->execute([$p_qty, (int)$product_id]);
    }

    // 3. Handle Coupon Usage increment
    if (!empty($coupon_id)) {
        $pdo->prepare("UPDATE tbl_coupon SET used_count = used_count + 1 WHERE coupon_id = ?")->execute([(int)$coupon_id]);
    } elseif (!empty($coupon_code)) {
        $pdo->prepare("UPDATE tbl_coupon SET used_count = used_count + 1 WHERE coupon_code = ?")->execute([$coupon_code]);
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('COD checkout failed: ' . $error->getMessage());
    http_response_code(500);
    exit('Order could not be saved: ' . htmlspecialchars($error->getMessage()) . '. <a href="../../checkout.php">Return to Checkout</a>');
}

// 4. SUPABASE REALTIME SYNC (Push to Merchant Supabase orders & order_items)
$supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : getenv('SUPABASE_URL');
$supabase_key = defined('SUPABASE_SERVICE_KEY') && !empty(SUPABASE_SERVICE_KEY) 
    ? SUPABASE_SERVICE_KEY 
    : (defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : getenv('SUPABASE_ANON_KEY'));

if (!empty($supabase_url) && !empty($supabase_key)) {
    try {
        $order_number = 'ORD-' . date('Ymd') . '-' . mt_rand(1000, 9999);
        $clean_supabase_url = rtrim($supabase_url, '/');

        $merchant_id = !empty($runtime['merchant_id']) ? $runtime['merchant_id'] : null;
        if (!$merchant_id) {
            $ch_m = curl_init("{$clean_supabase_url}/rest/v1/merchants?select=id&limit=1");
            curl_setopt($ch_m, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_m, CURLOPT_HTTPHEADER, [
                "apikey: {$supabase_key}",
                "Authorization: Bearer {$supabase_key}"
            ]);
            $res_m = curl_exec($ch_m);
            curl_close($ch_m);
            $merchants = json_decode($res_m, true);
            $merchant_id = !empty($merchants[0]['id']) ? $merchants[0]['id'] : null;
        }

        if ($merchant_id) {
            $order_payload = json_encode([
                'merchant_id' => $merchant_id,
                'tran_id' => $payment_id,
                'order_number' => $order_number,
                'amount' => $overall_total,
                'total_amount' => $overall_total,
                'subtotal' => $overall_total - $shipping_cost + $coupon_discount,
                'shipping_cost' => $shipping_cost,
                'discount_amount' => $coupon_discount,
                'cus_phone' => (string)($shipping_details['phone'] ?? ($billing_details['phone'] ?? '')),
                'cus_name' => $customer_name,
                'cus_email' => $customer_email,
                'shipping_address' => (string)($shipping_details['address'] ?? ($billing_details['address'] ?? '')),
                'shipping_city' => (string)($shipping_details['city'] ?? ($billing_details['city'] ?? '')),
                'payment_method' => 'COD',
                'status' => 'PENDING',
                'order_status' => 'CONFIRMED',
                'customer_note' => $customer_note,
                'expires_at' => date('c', strtotime('+7 days'))
            ]);

            $ch_order = curl_init("{$clean_supabase_url}/rest/v1/orders");
            curl_setopt($ch_order, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_order, CURLOPT_POST, true);
            curl_setopt($ch_order, CURLOPT_POSTFIELDS, $order_payload);
            curl_setopt($ch_order, CURLOPT_HTTPHEADER, [
                "apikey: {$supabase_key}",
                "Authorization: Bearer {$supabase_key}",
                "Content-Type: application/json",
                "Prefer: return=representation"
            ]);
            $res_order = curl_exec($ch_order);
            curl_close($ch_order);

            $inserted_order = json_decode($res_order, true);
            $supabase_order_id = !empty($inserted_order[0]['id']) ? $inserted_order[0]['id'] : null;

            if ($supabase_order_id) {
                $items_payload = [];
                foreach ($cart_ids as $key => $pid) {
                    $item_qty = max(1, (int)($_SESSION['cart_p_qty'][$key] ?? ($payment_data['cart_p_qty'][$key] ?? 1)));
                    $item_price = (float)($_SESSION['cart_p_current_price'][$key] ?? ($payment_data['cart_p_current_price'][$key] ?? 0));
                    $items_payload[] = [
                        'order_id' => $supabase_order_id,
                        'product_name' => (string)($_SESSION['cart_p_name'][$key] ?? ($payment_data['cart_p_name'][$key] ?? 'Product')),
                        'size' => (string)($_SESSION['cart_size_name'][$key] ?? ($payment_data['cart_size_name'][$key] ?? '')),
                        'color' => (string)($_SESSION['cart_color_name'][$key] ?? ($payment_data['cart_color_name'][$key] ?? '')),
                        'quantity' => $item_qty,
                        'unit_price' => $item_price,
                        'total_price' => $item_qty * $item_price
                    ];
                }

                $ch_items = curl_init("{$clean_supabase_url}/rest/v1/order_items");
                curl_setopt($ch_items, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_items, CURLOPT_POST, true);
                curl_setopt($ch_items, CURLOPT_POSTFIELDS, json_encode($items_payload));
                curl_setopt($ch_items, CURLOPT_HTTPHEADER, [
                    "apikey: {$supabase_key}",
                    "Authorization: Bearer {$supabase_key}",
                    "Content-Type: application/json"
                ]);
                curl_exec($ch_items);
                curl_close($ch_items);
            }
        }
    } catch (Throwable $e) {
        error_log("Supabase realtime order push error: " . $e->getMessage());
    }
}

// 5. Clear cart session variables
unset(
    $_SESSION['cart_p_id'],
    $_SESSION['cart_size_id'],
    $_SESSION['cart_size_name'],
    $_SESSION['cart_color_id'],
    $_SESSION['cart_color_name'],
    $_SESSION['cart_p_qty'],
    $_SESSION['cart_p_current_price'],
    $_SESSION['cart_p_name'],
    $_SESSION['cart_p_featured_photo'],
    $_SESSION['coupon_code'],
    $_SESSION['coupon_discount'],
    $_SESSION['coupon_id'],
    $_SESSION['shipping_cost'],
    $_SESSION['overall_total'],
    $_SESSION['final_total'],
    $_SESSION['payment_data'],
    $_SESSION['billing_address_details'],
    $_SESSION['shipping_address_details']
);

// 6. Redirect to receipt page
header('Location: ../../payment_success.php?method=cod&payment_id=' . rawurlencode($payment_id));
exit;