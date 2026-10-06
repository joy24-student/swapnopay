<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("admin/inc/config.php");
require_once("admin/inc/functions.php");
require_once("admin/inc/CSRF_Protect.php");
$csrf = new CSRF_Protect();

// -------------------------------------------------------------------------
// 1. ENSURE CART DATA & DEMO SEEDING
// -------------------------------------------------------------------------
if (!isset($_SESSION['cart_p_id']) || !is_array($_SESSION['cart_p_id'])) {
    $_SESSION['cart_p_id'] = [];
    $_SESSION['cart_size_id'] = [];
    $_SESSION['cart_size_name'] = [];
    $_SESSION['cart_color_id'] = [];
    $_SESSION['cart_color_name'] = [];
    $_SESSION['cart_p_qty'] = [];
    $_SESSION['cart_p_current_price'] = [];
    $_SESSION['cart_p_name'] = [];
    $_SESSION['cart_p_featured_photo'] = [];
    $_SESSION['cart_p_old_price'] = [];
    $_SESSION['cart_p_subtitle'] = [];
    $_SESSION['cart_p_badge'] = [];
}

if (empty($_SESSION['cart_p_id'])) {
    header('location: cart.php');
    exit;
}

// Support selecting specific cart items from cart.php
if (!empty($_POST['selected_indexes'])) {
    $sel_idxs = json_decode($_POST['selected_indexes'], true);
    if (is_array($sel_idxs)) {
        $filtered_p_id = [];
        $filtered_size_id = [];
        $filtered_size_name = [];
        $filtered_color_id = [];
        $filtered_color_name = [];
        $filtered_p_qty = [];
        $filtered_p_current_price = [];
        $filtered_p_name = [];
        $filtered_p_featured_photo = [];
        $filtered_p_old_price = [];
        $filtered_p_subtitle = [];
        $k = 1;
        $all_p_ids = array_values($_SESSION['cart_p_id']);
        foreach ($sel_idxs as $idx) {
            $idx = (int)$idx;
            if (isset($all_p_ids[$idx])) {
                $filtered_p_id[$k] = $all_p_ids[$idx];
                $filtered_size_id[$k] = array_values($_SESSION['cart_size_id'])[$idx] ?? 0;
                $filtered_size_name[$k] = array_values($_SESSION['cart_size_name'])[$idx] ?? '';
                $filtered_color_id[$k] = array_values($_SESSION['cart_color_id'])[$idx] ?? 0;
                $filtered_color_name[$k] = array_values($_SESSION['cart_color_name'])[$idx] ?? '';
                $filtered_p_qty[$k] = array_values($_SESSION['cart_p_qty'])[$idx] ?? 1;
                $filtered_p_current_price[$k] = array_values($_SESSION['cart_p_current_price'])[$idx] ?? 0;
                $filtered_p_name[$k] = array_values($_SESSION['cart_p_name'])[$idx] ?? '';
                $filtered_p_featured_photo[$k] = array_values($_SESSION['cart_p_featured_photo'])[$idx] ?? '';
                $filtered_p_old_price[$k] = array_values($_SESSION['cart_p_old_price'])[$idx] ?? 0;
                $filtered_p_subtitle[$k] = array_values($_SESSION['cart_p_subtitle'])[$idx] ?? '';
                $k++;
            }
        }
        if (!empty($filtered_p_id)) {
            $_SESSION['cart_p_id'] = $filtered_p_id;
            $_SESSION['cart_size_id'] = $filtered_size_id;
            $_SESSION['cart_size_name'] = $filtered_size_name;
            $_SESSION['cart_color_id'] = $filtered_color_id;
            $_SESSION['cart_color_name'] = $filtered_color_name;
            $_SESSION['cart_p_qty'] = $filtered_p_qty;
            $_SESSION['cart_p_current_price'] = $filtered_p_current_price;
            $_SESSION['cart_p_name'] = $filtered_p_name;
            $_SESSION['cart_p_featured_photo'] = $filtered_p_featured_photo;
            $_SESSION['cart_p_old_price'] = $filtered_p_old_price;
            $_SESSION['cart_p_subtitle'] = $filtered_p_subtitle;
        }
    }
}

// Generate checkout security token
if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

// -------------------------------------------------------------------------
// 2. RETRIEVE CART ITEMS & CALCULATE TOTALS
// -------------------------------------------------------------------------
$arr_cart_p_id = array_values($_SESSION['cart_p_id']);
$arr_cart_p_name = array_values($_SESSION['cart_p_name']);
$arr_cart_p_qty = array_values($_SESSION['cart_p_qty']);
$arr_cart_p_current_price = array_values($_SESSION['cart_p_current_price']);
$arr_cart_p_old_price = array_values($_SESSION['cart_p_old_price'] ?? []);
$arr_cart_p_featured_photo = array_values($_SESSION['cart_p_featured_photo']);
$arr_cart_p_subtitle = array_values($_SESSION['cart_p_subtitle'] ?? []);
$total_items_count = count($arr_cart_p_id);

$table_total_price = 0;
$total_savings = 0;
for ($i = 0; $i < $total_items_count; $i++) {
    $row_qty = (int)($arr_cart_p_qty[$i] ?? 1);
    $row_price = (float)($arr_cart_p_current_price[$i] ?? 0);
    $row_old = (float)($arr_cart_p_old_price[$i] ?? 0);
    $table_total_price += ($row_price * $row_qty);
    if ($row_old > $row_price) {
        $total_savings += (($row_old - $row_price) * $row_qty);
    }
}

// Default shipping cost (Standard Delivery: ৳60)
$default_shipping_cost = 60.0;
$shipping_cost = $default_shipping_cost;

// -------------------------------------------------------------------------
// 3. COUPON CODE PROCESSING (AJAX & Form)
// -------------------------------------------------------------------------
$coupon_discount = (float)($_SESSION['coupon']['discount'] ?? 0);
$coupon_code = $_SESSION['coupon']['code'] ?? '';
$coupon_error = '';
$coupon_success = '';

if (isset($_POST['apply_coupon_ajax']) || (isset($_POST['apply_coupon']) && !empty($_POST['coupon_code']))) {
    $submitted_code = trim(strip_tags($_POST['coupon_code'] ?? ''));
    if (!empty($submitted_code)) {
        $today = date('Y-m-d');
        $coupon_stmt = $pdo->prepare("SELECT * FROM tbl_coupon WHERE coupon_code = ? AND status = 'active' AND start_date <= ? AND end_date >= ?");
        $coupon_stmt->execute([$submitted_code, $today, $today]);
        $coupon_found = $coupon_stmt->fetch(PDO::FETCH_ASSOC);

        if ($coupon_found) {
            if ($coupon_found['usage_limit'] > 0 && $coupon_found['used_count'] >= $coupon_found['usage_limit']) {
                $coupon_error = "This coupon has reached its maximum usage limit.";
                unset($_SESSION['coupon']);
                $coupon_discount = 0;
            } elseif ($coupon_found['minimum_order'] > 0 && $table_total_price < $coupon_found['minimum_order']) {
                $coupon_error = "Minimum order of ৳ " . number_format($coupon_found['minimum_order'], 2) . " required.";
                unset($_SESSION['coupon']);
                $coupon_discount = 0;
            } else {
                if ($coupon_found['discount_type'] === 'percentage') {
                    $coupon_discount = ($table_total_price * (float)$coupon_found['discount_value']) / 100;
                } else {
                    $coupon_discount = (float)$coupon_found['discount_value'];
                }
                $coupon_discount = min($table_total_price, $coupon_discount);

                $_SESSION['coupon'] = [
                    'code' => $submitted_code,
                    'discount' => $coupon_discount,
                    'coupon_id' => $coupon_found['coupon_id']
                ];
                $coupon_code = $submitted_code;
                $coupon_success = "Coupon applied! Discount: ৳ " . number_format($coupon_discount, 2);
            }
        } else {
            $coupon_error = "Invalid or expired coupon code.";
            unset($_SESSION['coupon']);
            $coupon_discount = 0;
        }
    } else {
        $coupon_error = "Please enter a coupon code.";
        unset($_SESSION['coupon']);
        $coupon_discount = 0;
    }

    if (isset($_POST['apply_coupon_ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => empty($coupon_error),
            'discount' => $coupon_discount,
            'coupon_code' => $coupon_code,
            'message' => empty($coupon_error) ? $coupon_success : $coupon_error,
            'new_total' => max(0, ($table_total_price + $shipping_cost) - $coupon_discount)
        ]);
        exit;
    }
}

$final_total = max(0, ($table_total_price + $shipping_cost) - $coupon_discount);

// Free shipping progress computation (Threshold ৳ 2,000)
$free_shipping_threshold = 2000.0;
$free_shipping_remaining = max(0, $free_shipping_threshold - $table_total_price);
$free_shipping_percent = min(100, round(($table_total_price / $free_shipping_threshold) * 100));

// -------------------------------------------------------------------------
// 4. CUSTOMER PROFILE INITIALIZATION
// -------------------------------------------------------------------------
$cust_session = $_SESSION['customer'] ?? null;

$def_full_name = $cust_session['cust_name'] ?? '';
$def_phone = $cust_session['cust_phone'] ?? '';
$def_email = $cust_session['cust_email'] ?? '';
$def_address = $cust_session['cust_address'] ?? '';
$def_apartment = '';
$def_country = $cust_session['cust_country'] ?? 18; // 18 is Bangladesh
$def_division = $cust_session['cust_state'] ?? '';
$def_district = $cust_session['cust_city'] ?? '';
$def_zip = $cust_session['cust_zip'] ?? '';

// -------------------------------------------------------------------------
// 5. ORDER & PAYMENT SUBMISSION PROCESSOR (POST)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit_checkout'])) {
    $full_name = trim(strip_tags($_POST['full_name'] ?? $def_full_name));
    $phone = trim(strip_tags($_POST['phone_number'] ?? $def_phone));
    $email = trim(strip_tags($_POST['email_address'] ?? $def_email));
    $address = trim(strip_tags($_POST['address'] ?? $def_address));
    $apartment = trim(strip_tags($_POST['apartment'] ?? ''));
    $country_id = (int)($_POST['country'] ?? 18);
    $division = trim(strip_tags($_POST['division'] ?? $def_division));
    $district = trim(strip_tags($_POST['district'] ?? $def_district));
    $chosen_shipping_method = trim(strip_tags($_POST['shipping_method'] ?? 'standard'));
    $chosen_shipping_cost = (float)($_POST['shipping_cost'] ?? 60.0);
    $chosen_payment_method = trim(strip_tags($_POST['payment_method'] ?? 'swapnopay'));
    $customer_note = trim(strip_tags($_POST['customer_note'] ?? ''));

    // Full delivery address string
    $full_delivery_address = $address;
    if (!empty($apartment)) {
        $full_delivery_address .= ' (' . $apartment . ')';
    }

    // 1. Ensure Customer Record Exists in Session and DB
    if (!isset($_SESSION['customer']) || empty($_SESSION['customer']['cust_id'])) {
        $stmt_cust = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_email = ? LIMIT 1");
        $stmt_cust->execute([$email]);
        $existing_cust = $stmt_cust->fetch(PDO::FETCH_ASSOC);

        if ($existing_cust) {
            $_SESSION['customer'] = $existing_cust;
        } else {
            // Create guest customer record
            $temp_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $now = date('Y-m-d H:i:s');
            $stmt_new = $pdo->prepare("INSERT INTO tbl_customer (
                cust_name, cust_cname, cust_email, cust_phone, cust_country,
                cust_address, cust_city, cust_state, cust_zip,
                cust_b_name, cust_b_cname, cust_b_phone, cust_b_country, cust_b_address, cust_b_city, cust_b_state, cust_b_zip,
                cust_s_name, cust_s_cname, cust_s_phone, cust_s_country, cust_s_address, cust_s_city, cust_s_state, cust_s_zip,
                cust_password, cust_token, cust_datetime, cust_timestamp, cust_status
            ) VALUES (
                ?, '', ?, ?, ?,
                ?, ?, ?, '5500',
                ?, '', ?, ?, ?, ?, ?, '5500',
                ?, '', ?, ?, ?, ?, ?, '5500',
                ?, '', ?, ?, 1
            )");
            $stmt_new->execute([
                $full_name, $email, $phone, $country_id,
                $full_delivery_address, $district, $division,
                $full_name, $phone, $country_id, $full_delivery_address, $district, $division,
                $full_name, $phone, $country_id, $full_delivery_address, $district, $division,
                $temp_password, $now, time()
            ]);
            $new_cust_id = $pdo->lastInsertId();
            $stmt_fetch = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ?");
            $stmt_fetch->execute([$new_cust_id]);
            $_SESSION['customer'] = $stmt_fetch->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        // Update customer details if requested
        if (!empty($_POST['save_address'])) {
            $stmt_up = $pdo->prepare("UPDATE tbl_customer SET 
                cust_name=?, cust_phone=?, cust_address=?, cust_city=?, cust_state=?, cust_country=?,
                cust_b_name=?, cust_b_phone=?, cust_b_address=?, cust_b_city=?, cust_b_state=?, cust_b_country=?,
                cust_s_name=?, cust_s_phone=?, cust_s_address=?, cust_s_city=?, cust_s_state=?, cust_s_country=?
                WHERE cust_id=?");
            $stmt_up->execute([
                $full_name, $phone, $full_delivery_address, $district, $division, $country_id,
                $full_name, $phone, $full_delivery_address, $district, $division, $country_id,
                $full_name, $phone, $full_delivery_address, $district, $division, $country_id,
                $_SESSION['customer']['cust_id']
            ]);
        }
    }

    // 2. Prepare Session Address Details
    $_SESSION['billing_address_details'] = [
        'name' => $full_name,
        'cname' => '',
        'phone' => $phone,
        'country_id' => $country_id,
        'address' => $full_delivery_address,
        'city' => $district,
        'state' => $division,
        'zip' => '5500',
        'email' => $email
    ];

    $_SESSION['shipping_address_details'] = [
        'name' => $full_name,
        'cname' => '',
        'phone' => $phone,
        'country_id' => $country_id,
        'address' => $full_delivery_address,
        'city' => $district,
        'state' => $division,
        'zip' => '5500',
        'email' => $email
    ];

    // 3. Compute final calculation
    $submitted_final_total = max(0, ($table_total_price + $chosen_shipping_cost) - $coupon_discount);

    $_SESSION['payment_data'] = [
        'customer_id' => $_SESSION['customer']['cust_id'],
        'customer_name' => $full_name,
        'customer_email' => $email,
        'paid_amount' => $table_total_price,
        'shipping_cost' => $chosen_shipping_cost,
        'coupon_code' => $coupon_code,
        'coupon_discount' => $coupon_discount,
        'overall_total' => $submitted_final_total,
        'cart_p_id' => $_SESSION['cart_p_id'],
        'cart_size_id' => $_SESSION['cart_size_id'] ?? [],
        'cart_size_name' => $_SESSION['cart_size_name'] ?? [],
        'cart_color_id' => $_SESSION['cart_color_id'] ?? [],
        'cart_color_name' => $_SESSION['cart_color_name'] ?? [],
        'cart_p_qty' => $_SESSION['cart_p_qty'] ?? [],
        'cart_p_current_price' => $_SESSION['cart_p_current_price'] ?? [],
        'cart_p_name' => $_SESSION['cart_p_name'] ?? [],
        'cart_p_featured_photo' => $_SESSION['cart_p_featured_photo'] ?? [],
        'coupon_id' => $_SESSION['coupon']['coupon_id'] ?? null,
        'customer_note' => $customer_note,
        'shipping_method' => $chosen_shipping_method
    ];

    // 4. Route to Selected Gateway
    if ($chosen_payment_method === 'cod') {
        // Direct Cash on Delivery Processing
        $payment_date = date('Y-m-d H:i:s');
        $payment_id = 'COD-' . time() . '-' . $_SESSION['customer']['cust_id'] . mt_rand(1000, 9999);
        $order_id = 'ORD-' . date('Ymd') . '-' . mt_rand(1000, 9999);

        try {
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
                $_SESSION['customer']['cust_id'],
                $full_name,
                $email,
                $payment_date,
                '', // txnid
                $submitted_final_total,
                $chosen_shipping_cost,
                $coupon_code,
                $coupon_discount,
                'Cash on Delivery',
                'Pending',
                'Processing',
                $payment_id,
                $customer_note,
                '', '',
                $full_name, $email, $phone, $full_delivery_address, $district, $division, 'Bangladesh', '5500',
                $full_name, $email, $phone, $full_delivery_address, $district, $division, 'Bangladesh', '5500'
            ]);

            // Insert each product into tbl_order
            $arr_p_id = array_values($_SESSION['cart_p_id']);
            $arr_qty = array_values($_SESSION['cart_p_qty']);
            $arr_price = array_values($_SESSION['cart_p_current_price']);
            $arr_p_name = array_values($_SESSION['cart_p_name']);
            $arr_size_name = array_values($_SESSION['cart_size_name'] ?? []);
            $arr_color_name = array_values($_SESSION['cart_color_name'] ?? []);

            for ($i = 0; $i < count($arr_p_id); $i++) {
                $stmt_ord = $pdo->prepare("INSERT INTO tbl_order (
                    product_id, product_name, size, color, quantity, unit_price, payment_id, order_no
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_ord->execute([
                    $arr_p_id[$i],
                    $arr_p_name[$i],
                    $arr_size_name[$i] ?? '',
                    $arr_color_name[$i] ?? '',
                    $arr_qty[$i],
                    $arr_price[$i],
                    $payment_id,
                    $order_id
                ]);
            }

            // Clear cart
            unset($_SESSION['cart_p_id'], $_SESSION['cart_size_id'], $_SESSION['cart_size_name'], $_SESSION['cart_color_id'], $_SESSION['cart_color_name'], $_SESSION['cart_p_qty'], $_SESSION['cart_p_current_price'], $_SESSION['cart_p_name'], $_SESSION['cart_p_featured_photo'], $_SESSION['coupon']);

            header("Location: payment_success.php?method=cod&payment_id=" . urlencode($payment_id));
            exit;
        } catch (Exception $e) {
            $order_error = "Order processing error: " . $e->getMessage();
        }
    } else {
        // SwapnoPay Unified Gateway (bKash, Nagad, Rocket, Upay, Cards, Net Banking)
        $mfs_provider = strip_tags($_POST['mfs_provider'] ?? 'bKash');
        if (!in_array($mfs_provider, ['bKash', 'Nagad', 'Rocket', 'Upay'])) {
            $mfs_provider = 'bKash';
        }
        $_SESSION['mfs_provider'] = $mfs_provider;
        header("Location: payment/swapnopay/process.php?provider=" . urlencode($mfs_provider));
        exit;
    }
}
require_once('header.php');
?>

<!-- ============================================================
     SHOPNEXT PIXEL-PERFECT REDESIGNED CHECKOUT SCREEN
     ============================================================ -->
<div class="sn-checkout-page-wrapper">
    <div class="sn-checkout-container">

        <!-- Mobile Header Bar (Unified compact app bar) -->
        <div class="sn-mobile-header-bar">
            <a href="cart.php" class="sn-mob-back-btn" aria-label="Back to Cart">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
            </a>
            <div class="sn-mob-title-wrap">
                <h1 class="sn-mob-main-title">Checkout <span class="sn-mob-badge-lock">🔒</span></h1>
                <p class="sn-mob-sub-title">Complete your order & fast delivery</p>
            </div>
            <div class="sn-mob-badge-safe">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <polyline points="9 12 11 14 15 10"></polyline>
                </svg>
                <div class="sn-mob-badge-text">
                    <span class="t1">100% Secure</span>
                </div>
            </div>
        </div>

        <!-- Top Navigation / Back Link (Desktop Image 1) -->
        <div class="sn-checkout-top-nav">
            <a href="cart.php" class="sn-back-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span>Back to Cart</span>
            </a>
        </div>

        <!-- Checkout Header (Desktop) -->
        <div class="sn-checkout-header">
            <h1 class="sn-checkout-title">Checkout</h1>
            <p class="sn-checkout-subtitle sn-desktop-sub">Complete your order in just a few simple steps</p>
        </div>

        <!-- Stepper Wizard (Desktop: 1 Shipping -> 2 Payment -> 3 Review & Place Order) -->
        <div class="sn-stepper-wrap">
            <div class="sn-step-item active">
                <div class="sn-step-pill">
                    <span class="sn-step-num">1</span>
                    <span class="sn-step-label">Shipping</span>
                </div>
                <div class="sn-step-indicator-bar"></div>
            </div>
            <div class="sn-step-line"></div>
            <div class="sn-step-item">
                <div class="sn-step-pill">
                    <span class="sn-step-num">2</span>
                    <span class="sn-step-label">Payment</span>
                </div>
            </div>
            <div class="sn-step-line"></div>
            <div class="sn-step-item">
                <div class="sn-step-pill">
                    <span class="sn-step-num">3</span>
                    <span class="sn-step-label">Review & Place Order</span>
                </div>
            </div>
        </div>

        <!-- Main Checkout Form & Grid -->
        <form id="mainCheckoutForm" action="checkout.php" method="post">
            <?php $csrf->echoInputField(); ?>
            <input type="hidden" name="action_submit_checkout" value="1">
            <input type="hidden" name="checkout_token" value="<?php echo htmlspecialchars($_SESSION['checkout_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="shipping_cost" id="inputShippingCost" value="<?php echo number_format($shipping_cost, 2, '.', ''); ?>">
            <input type="hidden" name="shipping_method" id="inputShippingMethod" value="standard">
            <input type="hidden" name="payment_method" id="inputPaymentMethod" value="swapnopay">

            <div class="sn-checkout-grid">

                <!-- ========================================================
                     LEFT COLUMN: SHIPPING INFO, DELIVERY METHOD, TRUST BAR
                     ======================================================== -->
                <div class="sn-checkout-left-col">

                    <!-- Mobile Saved Address Card (Image 2) -->
                    <div class="sn-mobile-address-card" id="mobileSavedAddressCard">
                        <div class="sn-card-head-compact">
                            <div class="sn-head-title-row">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <h3>Shipping Address</h3>
                            </div>
                            <button type="button" class="sn-change-addr-btn" onclick="toggleAddressEditor()">
                                <span>Change</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </button>
                        </div>
                        <div class="sn-saved-addr-box">
                            <div class="sn-saved-user-row">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                                <span class="sn-saved-name" id="previewSavedName"><?php echo htmlspecialchars($def_full_name ?: 'Guest Customer'); ?></span>
                                <span class="sn-tag-home">Delivery</span>
                            </div>
                            <div class="sn-saved-details" id="previewSavedAddress">
                                <?php if (!empty($def_address)): ?>
                                    <?php echo htmlspecialchars($def_address); ?><br>
                                    <?php echo htmlspecialchars(($def_district ? $def_district . ', ' : '') . ($def_division ? $def_division . ', ' : '') . 'Bangladesh'); ?>
                                <?php else: ?>
                                    Enter your shipping address below.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Card 1: Shipping Information (Full Editable Form) -->
                    <div class="sn-checkout-card" id="shippingInfoCard">
                        <div class="sn-card-header">
                            <div class="sn-card-icon-wrap">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                </svg>
                            </div>
                            <div class="sn-card-title-col">
                                <h2>Shipping Information</h2>
                                <p>Where should we deliver your order?</p>
                            </div>
                        </div>

                        <div class="sn-form-body">
                            <!-- Row 1: Full Name & Phone Number -->
                            <div class="sn-input-grid-2">
                                <div class="sn-form-group">
                                    <label class="sn-form-label">Full Name <span class="sn-req">*</span></label>
                                    <input type="text" name="full_name" id="fullNameInput" class="sn-form-input" required value="<?php echo htmlspecialchars($def_full_name); ?>" placeholder="Enter your full name" oninput="updateAddressPreview()">
                                </div>
                                <div class="sn-form-group">
                                    <label class="sn-form-label">Phone Number <span class="sn-req">*</span></label>
                                    <div class="sn-phone-input-wrap">
                                        <div class="sn-phone-prefix">
                                            <span class="sn-flag-bd">🇧🇩</span>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                        </div>
                                        <input type="tel" name="phone_number" id="phoneInput" class="sn-form-input sn-phone-field" required value="<?php echo htmlspecialchars($def_phone); ?>" placeholder="+880 1712 345678">
                                    </div>
                                </div>
                            </div>

                            <!-- Row 2: Email Address -->
                            <div class="sn-form-group">
                                <label class="sn-form-label">Email Address <span class="sn-req">*</span></label>
                                <div class="sn-icon-input-wrap">
                                    <svg class="sn-input-left-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                    <input type="email" name="email_address" id="emailInput" class="sn-form-input sn-has-left-icon" required value="<?php echo htmlspecialchars($def_email); ?>" placeholder="Enter your email address">
                                </div>
                            </div>

                            <!-- Row 3: Street Address -->
                            <div class="sn-form-group">
                                <label class="sn-form-label">Address <span class="sn-req">*</span></label>
                                <div class="sn-icon-input-wrap">
                                    <svg class="sn-input-left-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <input type="text" name="address" id="addressInput" class="sn-form-input sn-has-left-icon" required value="<?php echo htmlspecialchars($def_address); ?>" placeholder="Street address or village" oninput="updateAddressPreview()">
                                </div>
                            </div>

                            <!-- Row 4: Apartment, floor, etc. (optional) -->
                            <div class="sn-form-group">
                                <input type="text" name="apartment" id="apartmentInput" class="sn-form-input" placeholder="Apartment, floor, etc. (optional)" value="<?php echo htmlspecialchars($def_apartment); ?>">
                            </div>

                            <!-- Row 5: Country, Division, District -->
                            <div class="sn-input-grid-3">
                                <div class="sn-form-group">
                                    <label class="sn-form-label">Country <span class="sn-req">*</span></label>
                                    <div class="sn-select-wrap">
                                        <div class="sn-select-flag">🇧🇩</div>
                                        <select name="country" id="countrySelect" class="sn-form-select sn-has-flag">
                                            <option value="18" selected>Bangladesh</option>
                                        </select>
                                        <svg class="sn-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </div>
                                </div>
                                <div class="sn-form-group">
                                    <label class="sn-form-label">Division <span class="sn-req">*</span></label>
                                    <div class="sn-select-wrap">
                                        <select name="division" id="divisionSelect" class="sn-form-select" onchange="updateAddressPreview()">
                                            <option value="Rangpur Division" selected>Rangpur Division</option>
                                            <option value="Dhaka Division">Dhaka Division</option>
                                            <option value="Chittagong Division">Chittagong Division</option>
                                            <option value="Rajshahi Division">Rajshahi Division</option>
                                            <option value="Khulna Division">Khulna Division</option>
                                            <option value="Barisal Division">Barisal Division</option>
                                            <option value="Sylhet Division">Sylhet Division</option>
                                            <option value="Mymensingh Division">Mymensingh Division</option>
                                        </select>
                                        <svg class="sn-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </div>
                                </div>
                                <div class="sn-form-group">
                                    <label class="sn-form-label">District <span class="sn-req">*</span></label>
                                    <div class="sn-select-wrap">
                                        <select name="district" id="districtSelect" class="sn-form-select" onchange="updateAddressPreview()">
                                            <option value="Lalmonirhat" selected>Lalmonirhat</option>
                                            <option value="Kurigram">Kurigram</option>
                                            <option value="Rangpur">Rangpur</option>
                                            <option value="Dinajpur">Dinajpur</option>
                                            <option value="Nilphamari">Nilphamari</option>
                                            <option value="Gaibandha">Gaibandha</option>
                                            <option value="Dhaka">Dhaka</option>
                                            <option value="Chittagong">Chittagong</option>
                                        </select>
                                        <svg class="sn-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 6: Save this address for next time -->
                            <div class="sn-save-addr-row">
                                <label class="sn-checkbox-label">
                                    <input type="checkbox" name="save_address" value="1" checked class="sn-custom-checkbox">
                                    <span class="sn-check-box-visual">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#111827" stroke-width="3.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </span>
                                    <span class="sn-checkbox-text">Save this address for next time</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Shipping Method (Standard, Express, Pickup Point) -->
                    <div class="sn-checkout-card sn-shipping-method-card" id="deliveryMethodCard">
                        <div class="sn-card-header">
                            <div class="sn-card-icon-wrap">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="1" y="3" width="15" height="13"></rect>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                </svg>
                            </div>
                            <div class="sn-card-title-col">
                                <h2 class="sn-title-desktop">Shipping Method</h2>
                                <h2 class="sn-title-mobile">Delivery Method</h2>
                                <p>Choose how you want your order delivered.</p>
                            </div>
                        </div>

                        <div class="sn-shipping-options-grid">
                            <!-- Option 1: Standard Delivery (Selected Default) -->
                            <div class="sn-ship-card active" id="shipCardStandard" onclick="selectShipping('standard', 60)">
                                <div class="sn-ship-card-top">
                                    <div class="sn-radio-indicator">
                                        <div class="sn-radio-dot"></div>
                                    </div>
                                    <svg class="sn-ship-method-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="3" width="15" height="13"></rect>
                                        <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                    </svg>
                                    <span class="sn-badge-popular">Most Popular</span>
                                </div>
                                <div class="sn-ship-card-info">
                                    <div class="sn-ship-title">Standard Delivery</div>
                                    <div class="sn-ship-time">3-5 business days</div>
                                </div>
                                <div class="sn-ship-price">৳ 60</div>
                            </div>

                            <!-- Option 2: Express Delivery -->
                            <div class="sn-ship-card" id="shipCardExpress" onclick="selectShipping('express', 120)">
                                <div class="sn-ship-card-top">
                                    <div class="sn-radio-indicator">
                                        <div class="sn-radio-dot"></div>
                                    </div>
                                    <!-- Lightning Icon (Matches Mobile Image 2) -->
                                    <svg class="sn-ship-method-icon" width="20" height="20" viewBox="0 0 24 24" fill="#0F172A">
                                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                                    </svg>
                                </div>
                                <div class="sn-ship-card-info">
                                    <div class="sn-ship-title">Express Delivery</div>
                                    <div class="sn-ship-time">1-2 business days</div>
                                </div>
                                <div class="sn-ship-price">৳ 120</div>
                            </div>

                            <!-- Option 3: Pickup Point -->
                            <div class="sn-ship-card" id="shipCardPickup" onclick="selectShipping('pickup', 0)">
                                <div class="sn-ship-card-top">
                                    <div class="sn-radio-indicator">
                                        <div class="sn-radio-dot"></div>
                                    </div>
                                    <svg class="sn-ship-method-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 21h18"></path>
                                        <path d="M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7H3l2-4h14l2 4"></path>
                                        <line x1="5" y1="21" x2="5" y2="10.85"></line>
                                        <line x1="19" y1="21" x2="19" y2="10.85"></line>
                                        <path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"></path>
                                    </svg>
                                </div>
                                <div class="sn-ship-card-info">
                                    <div class="sn-ship-title">Pickup Point</div>
                                    <div class="sn-ship-time">Lalmonirhat (Self Pickup)</div>
                                </div>
                                <div class="sn-ship-price">৳ 0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Promo Code Card (Image 2) -->
                    <div class="sn-checkout-card sn-mobile-promo-card">
                        <div class="sn-promo-head">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                <line x1="7" y1="7" x2="7.01" y2="7"></line>
                            </svg>
                            <h3>Have a coupon code?</h3>
                        </div>
                        <div class="sn-promo-input-row">
                            <input type="text" id="couponCodeMobileInput" class="sn-form-input" placeholder="Enter coupon code" value="<?php echo htmlspecialchars($coupon_code); ?>">
                            <button type="button" class="sn-btn-apply" onclick="handleCouponSubmit('couponCodeMobileInput')">Apply</button>
                        </div>
                        <div id="couponMobileAlert" class="sn-coupon-msg"></div>
                    </div>

                    <!-- Trust Bar (Desktop: 4 columns) -->
                    <div class="sn-trust-badges-bar">
                        <div class="sn-trust-item">
                            <div class="sn-trust-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <polyline points="9 12 11 14 15 10"></polyline>
                                </svg>
                            </div>
                            <div class="sn-trust-meta">
                                <strong>Secure Checkout</strong>
                                <span>100% safe & secure payments</span>
                            </div>
                        </div>

                        <div class="sn-trust-divider"></div>

                        <div class="sn-trust-item">
                            <div class="sn-trust-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="1" y="3" width="15" height="13"></rect>
                                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                                </svg>
                            </div>
                            <div class="sn-trust-meta">
                                <strong>Free Shipping</strong>
                                <span>On orders over ৳ 2,000</span>
                            </div>
                        </div>

                        <div class="sn-trust-divider"></div>

                        <div class="sn-trust-item">
                            <div class="sn-trust-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="23 4 23 10 17 10"></polyline>
                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                </svg>
                            </div>
                            <div class="sn-trust-meta">
                                <strong>7 Days Return</strong>
                                <span>Easy return policy</span>
                            </div>
                        </div>

                        <div class="sn-trust-divider"></div>

                        <div class="sn-trust-item">
                            <div class="sn-trust-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                    <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                                </svg>
                            </div>
                            <div class="sn-trust-meta">
                                <strong>24/7 Support</strong>
                                <span>We're here to help</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ========================================================
                     RIGHT COLUMN: ORDER SUMMARY, PAYMENT METHODS, CTA BUTTON
                     ======================================================== -->
                <div class="sn-checkout-right-col">

                    <!-- Card 3: Order Summary -->
                    <div class="sn-checkout-card sn-order-summary-card" id="orderSummaryCard">
                        <div class="sn-card-header sn-header-between" onclick="toggleMobileSummaryList()">
                            <div class="sn-head-title-row">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                    <line x1="3" y1="6" x2="21" y2="6"></line>
                                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                                </svg>
                                <h2>Order Summary</h2>
                            </div>
                            <div class="sn-summary-item-badge-wrap">
                                <span class="sn-summary-item-count" id="summaryItemCountBadge"><?php echo $total_items_count; ?> items</span>
                                <svg class="sn-summary-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </div>
                        </div>

                        <!-- Product Items List -->
                        <div class="sn-summary-products-list" id="summaryProductsList">
                            <?php for ($i = 0; $i < $total_items_count; $i++): 
                                $p_name = $arr_cart_p_name[$i] ?? 'Product';
                                $p_qty = (int)($arr_cart_p_qty[$i] ?? 1);
                                $p_price = (float)($arr_cart_p_current_price[$i] ?? 0);
                                $p_sub = !empty($arr_cart_p_subtitle[$i]) ? $arr_cart_p_subtitle[$i] : 'Best Sound quality for any Android - White';
                                $p_img = !empty($arr_cart_p_featured_photo[$i]) ? $arr_cart_p_featured_photo[$i] : 'assets/uploads/default_product.jpg';
                                if (!str_starts_with($p_img, 'http')) {
                                    $p_img = 'assets/uploads/' . $p_img;
                                }
                            ?>
                            <div class="sn-summary-item-row">
                                <div class="sn-sum-thumb-wrap">
                                    <img src="<?php echo htmlspecialchars($p_img); ?>" alt="<?php echo htmlspecialchars($p_name); ?>" class="sn-sum-thumb">
                                </div>
                                <div class="sn-sum-info-col">
                                    <h4 class="sn-sum-item-name"><?php echo htmlspecialchars($p_name); ?></h4>
                                    <div class="sn-sum-item-specs"><?php echo htmlspecialchars($p_sub); ?></div>
                                    <div class="sn-sum-item-qty">Qty: <?php echo $p_qty; ?></div>
                                </div>
                                <div class="sn-sum-price-col">
                                    <span class="sn-sum-price">৳ <?php echo number_format($p_price * $p_qty); ?></span>
                                    <span class="sn-sum-price-mobile">৳ <?php echo number_format($p_price); ?> <span class="sn-mob-qty">× <?php echo $p_qty; ?></span></span>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>

                        <div class="sn-summary-divider"></div>

                        <!-- Pricing Breakdown -->
                        <div class="sn-pricing-breakdown">
                            <div class="sn-calc-row">
                                <span class="sn-calc-label">Subtotal (<?php echo $total_items_count; ?> items)</span>
                                <span class="sn-calc-val" id="summarySubtotalVal">৳ <?php echo number_format($table_total_price); ?></span>
                            </div>
                            <div class="sn-calc-row">
                                <span class="sn-calc-label sn-label-desktop">Shipping</span>
                                <span class="sn-calc-label sn-label-mobile">Shipping Fee</span>
                                <span class="sn-calc-val" id="summaryShippingVal">৳ <?php echo number_format($shipping_cost); ?></span>
                            </div>
                            <div class="sn-calc-row sn-desktop-tax-row">
                                <span class="sn-calc-label">Tax (estimated)</span>
                                <span class="sn-calc-val">৳ 0</span>
                            </div>
                            <div class="sn-calc-row sn-coupon-calc-row <?php echo ($coupon_discount > 0) ? 'active' : ''; ?>" id="summaryCouponRow">
                                <span class="sn-calc-label">Coupon Discount</span>
                                <span class="sn-calc-val sn-discount-text" id="summaryCouponVal">- ৳ <?php echo number_format($coupon_discount); ?></span>
                            </div>
                        </div>

                        <div class="sn-summary-divider"></div>

                        <!-- Total Row -->
                        <div class="sn-total-row">
                            <span class="sn-total-label">Total</span>
                            <span class="sn-total-amount" id="summaryGrandTotalVal">৳ <?php echo number_format($final_total); ?></span>
                        </div>

                        <!-- You Save Pill (Desktop Image 1) -->
                        <?php if ($total_savings > 0): ?>
                        <div class="sn-savings-badge-row">
                            <div class="sn-pill-savings">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                </svg>
                                <span>You save ৳ <?php echo number_format($total_savings); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card 4: Payment Method (Credit/Debit Card, bKash, Nagad, Cash on Delivery) -->
                    <div class="sn-checkout-card sn-payment-method-card" id="paymentMethodCard">
                        <div class="sn-card-header">
                            <div class="sn-card-icon-wrap">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                    <line x1="1" y1="10" x2="23" y2="10"></line>
                                </svg>
                            </div>
                            <div class="sn-card-title-col">
                                <h2>Payment Method</h2>
                                <p class="sn-pay-sub-desktop">Select a payment method</p>
                            </div>
                        </div>

                        <div class="sn-payment-options-list">
                            <!-- Option 1: SwapnoPay Payment Gateway -->
                            <div class="sn-pay-card active" id="payCardSwapnopay" onclick="selectPayment('swapnopay')">
                                <div class="sn-radio-indicator">
                                    <div class="sn-radio-dot"></div>
                                </div>
                                <div class="sn-pay-icon-box swapnopay-icon-bg">
                                    <img src="assets/uploads/swapnopay-logo.png" alt="SwapnoPay Official" class="sn-pay-logo-img" onerror="this.onerror=null; this.src='assets/uploads/logo_branding.png';">
                                </div>
                                <div class="sn-pay-title-col">
                                    <div class="sn-pay-name">
                                        <span>SwapnoPay Gateway</span>
                                        <span class="sn-official-badge">Official</span>
                                    </div>
                                    <div class="sn-pay-desc sn-pay-desc-desktop">bKash, Nagad, Rocket, Upay, Cards &amp; Net Banking</div>
                                    <div class="sn-pay-desc sn-pay-desc-mobile">bKash, Nagad, Rocket, Upay &amp; Cards</div>
                                </div>
                                <div class="sn-mfs-badges">
                                    <span class="sn-mfs-tag bkash">bKash</span>
                                    <span class="sn-mfs-tag nagad">Nagad</span>
                                    <span class="sn-mfs-tag rocket">Rocket</span>
                                </div>
                            </div>

                            <!-- MFS Provider Sub-Selection (appears when SwapnoPay is selected) -->
                            <div class="sn-mfs-provider-selector" id="mfsProviderSelector" style="display: block;">
                                <input type="hidden" name="mfs_provider" id="inputMfsProvider" value="bKash">
                                <div class="sn-mfs-selector-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                    <span>Choose your payment provider:</span>
                                </div>
                                <div class="sn-mfs-grid">
                                    <div class="sn-mfs-option active" id="mfsBkash" onclick="selectMfsProvider('bKash')" data-method="bKash">
                                        <div class="sn-mfs-radio"><div class="sn-mfs-radio-dot"></div></div>
                                        <div class="sn-mfs-color-bar" style="background: #e2136e;"></div>
                                        <span class="sn-mfs-label">bKash</span>
                                    </div>
                                    <div class="sn-mfs-option" id="mfsNagad" onclick="selectMfsProvider('Nagad')" data-method="Nagad">
                                        <div class="sn-mfs-radio"><div class="sn-mfs-radio-dot"></div></div>
                                        <div class="sn-mfs-color-bar" style="background: #f7941d;"></div>
                                        <span class="sn-mfs-label">Nagad</span>
                                    </div>
                                    <div class="sn-mfs-option" id="mfsRocket" onclick="selectMfsProvider('Rocket')" data-method="Rocket">
                                        <div class="sn-mfs-radio"><div class="sn-mfs-radio-dot"></div></div>
                                        <div class="sn-mfs-color-bar" style="background: #8c3494;"></div>
                                        <span class="sn-mfs-label">Rocket</span>
                                    </div>
                                    <div class="sn-mfs-option" id="mfsUpay" onclick="selectMfsProvider('Upay')" data-method="Upay">
                                        <div class="sn-mfs-radio"><div class="sn-mfs-radio-dot"></div></div>
                                        <div class="sn-mfs-color-bar" style="background: #ffcb05;"></div>
                                        <span class="sn-mfs-label">Upay</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Option 2: Cash on Delivery -->
                            <div class="sn-pay-card" id="payCardCod" onclick="selectPayment('cod')">
                                <div class="sn-radio-indicator">
                                    <div class="sn-radio-dot"></div>
                                </div>
                                <div class="sn-pay-icon-box">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M6 12h.01M18 12h.01"></path>
                                    </svg>
                                </div>
                                <div class="sn-pay-title-col">
                                    <div class="sn-pay-name">Cash on Delivery (COD)</div>
                                    <div class="sn-pay-desc sn-pay-desc-desktop">Pay with cash when your package is delivered</div>
                                    <div class="sn-pay-desc sn-pay-desc-mobile">Pay cash upon delivery</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Free shipping on orders over ৳ 2,000 progress card (Image 2) -->
                    <div class="sn-checkout-card sn-free-shipping-card" id="freeShippingBannerCard">
                        <div class="sn-free-shipping-head">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="1" y="3" width="15" height="13"></rect>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                <circle cx="18.5" cy="18.5" r="2.5"></circle>
                            </svg>
                            <div class="sn-free-shipping-texts">
                                <strong>Free shipping on orders over ৳ 2,000</strong>
                                <span id="freeShippingSubText">
                                    <?php if ($free_shipping_remaining > 0): ?>
                                        Add ৳ <?php echo number_format($free_shipping_remaining); ?> more to get free shipping!
                                    <?php else: ?>
                                        🎉 Congratulations! You unlocked free shipping!
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="sn-free-shipping-bar-track">
                            <div class="sn-free-shipping-bar-fill" id="freeShippingBarFill" style="width: <?php echo $free_shipping_percent; ?>%;"></div>
                        </div>
                    </div>

                    <!-- 100% Secure Payment Card (Image 2) -->
                    <div class="sn-checkout-card sn-card-secure-trust" id="securePaymentBadgeCard">
                        <div class="sn-secure-box-inner">
                            <div class="sn-secure-icon-wrap">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <polyline points="9 12 11 14 15 10"></polyline>
                                </svg>
                            </div>
                            <div class="sn-secure-meta">
                                <h4>100% Secure Payment</h4>
                                <p>Your payment information is encrypted and safe with us.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Action Button (Desktop: Continue to Payment ->) -->
                    <div class="sn-checkout-cta-wrap">
                        <button type="submit" class="sn-btn-primary-checkout" id="mainCheckoutSubmitBtn">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <span id="btnSubmitText">Continue to Payment</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </button>
                    </div>

                </div>

            </div>

            <!-- Sticky Bottom Mobile Action Dock (Image 2) -->
            <div class="sn-mobile-bottom-dock">
                <button type="submit" class="sn-btn-mobile-place-order" onclick="document.getElementById('mainCheckoutForm').submit()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Place Order</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </div>

        </form>

    </div>
</div>

<!-- ============================================================
     PIXEL-PERFECT CSS SPECIFICATION
     ============================================================ -->
<style>
:root {
    --sn-primary: #FBBF24;
    --sn-primary-hover: #F59E0B;
    --sn-primary-dark: #D97706;
    --sn-primary-light: #FEF3C7;
    --sn-primary-subtle: #FFFDF5;
    --sn-dark: #0F172A;
    --sn-dark-soft: #1E293B;
    --sn-muted: #64748B;
    --sn-muted-light: #94A3B8;
    --sn-border: #E2E8F0;
    --sn-border-light: #F1F5F9;
    --sn-bg-page: #F8FAFC;
    --sn-card-bg: #FFFFFF;
    --sn-green: #16A34A;
    --sn-green-bg: #DCFCE7;
    --sn-font: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

body {
    background-color: var(--sn-bg-page) !important;
    font-family: var(--sn-font) !important;
    color: var(--sn-dark) !important;
    margin: 0;
    padding: 0;
    -webkit-font-smoothing: antialiased;
}

/* Hide legacy page banner & old layout if any */
.page-banner,
.page .container:not(.sn-checkout-container) {
    display: none !important;
}

/* Hide site footer on desktop checkout to keep single-screen focus */
body:has(.sn-checkout-page-wrapper) .sn-footer-wrap {
    display: none !important;
}

/* Base Checkout Wrapper */
.sn-checkout-page-wrapper {
    background-color: var(--sn-bg-page);
    min-height: 100vh;
    padding: 24px 0 60px 0;
}

.sn-checkout-container {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Mobile App Bar */
.sn-mobile-header-bar {
    display: none;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0 16px 0;
    margin-bottom: 12px;
}

.sn-mob-back-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #FFFFFF;
    border: 1px solid var(--sn-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--sn-dark);
    text-decoration: none;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.sn-mob-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.sn-mob-brand-logo {
    height: 32px;
    max-width: 135px;
    object-fit: contain;
}

.sn-mob-brand-text {
    font-size: 19px;
    color: var(--sn-dark);
    font-weight: 700;
    letter-spacing: -0.3px;
}

.sn-mob-brand-text strong {
    color: var(--sn-primary-hover);
}

.sn-mob-badge-safe {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #EFF6FF;
    border: 1px solid #BFDBFE;
    border-radius: 9999px;
    padding: 4px 10px;
}

.sn-mob-badge-text {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}

.sn-mob-badge-text .t1 {
    font-size: 11px;
    font-weight: 700;
    color: #1E3A8A;
}

.sn-mob-badge-text .t2 {
    font-size: 9.5px;
    color: #3B82F6;
}

/* Top Back Link (Desktop Image 1) */
.sn-checkout-top-nav {
    margin-bottom: 12px;
}

.sn-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--sn-dark-soft);
    text-decoration: none;
    transition: color 0.15s ease;
}

.sn-back-link:hover {
    color: var(--sn-dark);
}

/* Checkout Header Title */
.sn-checkout-header {
    margin-bottom: 22px;
}

.sn-checkout-title {
    font-size: 32px;
    font-weight: 800;
    color: var(--sn-dark);
    letter-spacing: -0.6px;
    margin: 0 0 4px 0;
}

.sn-checkout-subtitle {
    font-size: 14px;
    color: var(--sn-muted);
    margin: 0;
}

.sn-mobile-sub {
    display: none;
}

/* Stepper Wizard */
.sn-stepper-wrap {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
}

.sn-step-item {
    display: flex;
    flex-direction: column;
    position: relative;
}

.sn-step-pill {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sn-step-num {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #F1F5F9;
    border: 1.5px solid #CBD5E1;
    color: var(--sn-muted);
    font-size: 12.5px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sn-step-label {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--sn-muted);
}

.sn-step-item.active .sn-step-num {
    background: var(--sn-primary);
    border-color: var(--sn-primary);
    color: var(--sn-dark);
}

.sn-step-item.active .sn-step-label {
    color: var(--sn-dark);
    font-weight: 700;
}

.sn-step-indicator-bar {
    height: 3px;
    background: var(--sn-primary);
    border-radius: 2px;
    margin-top: 6px;
    width: 100%;
}

.sn-step-line {
    width: 60px;
    height: 1.5px;
    background: var(--sn-border);
}

/* Two-Column Grid */
.sn-checkout-grid {
    display: grid;
    grid-template-columns: 1fr 410px;
    gap: 28px;
    align-items: flex-start;
}

/* White Cards */
.sn-checkout-card {
    background: var(--sn-card-bg);
    border: 1px solid var(--sn-border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.sn-card-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
}

.sn-header-between {
    justify-content: space-between;
    align-items: center;
}

.sn-head-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sn-head-title-row h2,
.sn-head-title-row h3 {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: var(--sn-dark);
}

.sn-card-icon-wrap {
    color: var(--sn-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 2px;
}

.sn-card-title-col h2 {
    font-size: 17.5px;
    font-weight: 700;
    color: var(--sn-dark);
    margin: 0 0 3px 0;
}

.sn-card-title-col p {
    font-size: 13px;
    color: var(--sn-muted);
    margin: 0;
}

.sn-title-mobile {
    display: none;
}

/* Form Fields */
.sn-form-body {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sn-input-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.sn-input-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

.sn-form-group {
    display: flex;
    flex-direction: column;
}

.sn-form-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--sn-dark-soft);
    margin-bottom: 6px;
}

.sn-req {
    color: #EF4444;
    font-weight: bold;
}

.sn-form-input,
.sn-form-select {
    width: 100%;
    height: 44px;
    border: 1.5px solid var(--sn-border);
    border-radius: 10px;
    padding: 0 14px;
    font-size: 14px;
    font-family: inherit;
    color: var(--sn-dark);
    background-color: #FFFFFF;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
    box-sizing: border-box;
}

.sn-form-input:focus,
.sn-form-select:focus {
    border-color: var(--sn-primary);
    box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
}

/* Phone Input */
.sn-phone-input-wrap {
    display: flex;
    align-items: center;
    width: 100%;
}

.sn-phone-prefix {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 0 10px;
    height: 44px;
    background: #F8FAFC;
    border: 1.5px solid var(--sn-border);
    border-right: none;
    border-radius: 10px 0 0 10px;
    font-size: 14px;
    color: var(--sn-muted);
}

.sn-flag-bd {
    font-size: 16px;
}

.sn-phone-field {
    border-radius: 0 10px 10px 0 !important;
}

/* Icon Input */
.sn-icon-input-wrap {
    position: relative;
    width: 100%;
}

.sn-input-left-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

.sn-has-left-icon {
    padding-left: 42px !important;
}

/* Select Dropdown */
.sn-select-wrap {
    position: relative;
    width: 100%;
}

.sn-select-flag {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    pointer-events: none;
}

.sn-has-flag {
    padding-left: 38px !important;
}

.sn-form-select {
    appearance: none;
    cursor: pointer;
    padding-right: 32px;
}

.sn-select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

/* Custom Checkbox */
.sn-save-addr-row {
    margin-top: 4px;
}

.sn-checkbox-label {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
}

.sn-custom-checkbox {
    display: none;
}

.sn-check-box-visual {
    width: 20px;
    height: 20px;
    border: 2px solid var(--sn-border);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FFFFFF;
    transition: all 0.15s ease;
}

.sn-custom-checkbox:checked + .sn-check-box-visual {
    background: var(--sn-primary);
    border-color: var(--sn-primary);
}

.sn-custom-checkbox:checked + .sn-check-box-visual svg {
    display: block;
}

.sn-check-box-visual svg {
    display: none;
}

.sn-checkbox-text {
    font-size: 13.5px;
    font-weight: 500;
    color: var(--sn-dark-soft);
}

/* Shipping Method 3-Card Grid */
.sn-shipping-options-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.sn-ship-card {
    border: 1.5px solid var(--sn-border);
    border-radius: 14px;
    padding: 16px;
    cursor: pointer;
    background: #FFFFFF;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 120px;
}

.sn-ship-card:hover {
    border-color: #CBD5E1;
}

.sn-ship-card.active {
    border: 2px solid var(--sn-primary);
    background: var(--sn-primary-subtle);
    box-shadow: 0 0 0 1px var(--sn-primary);
}

.sn-ship-card-top {
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    margin-bottom: 12px;
}

.sn-radio-indicator {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 1.5px solid #CBD5E1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FFFFFF;
    flex-shrink: 0;
}

.sn-ship-card.active .sn-radio-indicator,
.sn-pay-card.active .sn-radio-indicator {
    border-color: var(--sn-primary);
}

.sn-radio-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: transparent;
    transition: background 0.15s ease;
}

.sn-ship-card.active .sn-radio-dot,
.sn-pay-card.active .sn-radio-dot {
    background: var(--sn-primary);
}

.sn-ship-method-icon {
    flex-shrink: 0;
}

.sn-badge-popular {
    margin-left: auto;
    background: #FEF3C7;
    color: #B45309;
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 9999px;
    white-space: nowrap;
}

.sn-ship-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--sn-dark);
    margin-bottom: 2px;
}

.sn-ship-time {
    font-size: 12px;
    color: var(--sn-muted);
}

.sn-ship-price {
    font-size: 15px;
    font-weight: 700;
    color: var(--sn-dark);
    margin-top: 10px;
}

/* Trust Badges Row (Desktop Image 1) */
.sn-trust-badges-bar {
    background: #FFFFFF;
    border: 1px solid var(--sn-border);
    border-radius: 14px;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.sn-trust-item {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.sn-trust-icon {
    color: var(--sn-dark);
    display: flex;
    align-items: center;
    justify-content: center;
}

.sn-trust-meta strong {
    display: block;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    line-height: 1.2;
}

.sn-trust-meta span {
    font-size: 11.5px;
    color: var(--sn-muted);
}

.sn-trust-divider {
    width: 1px;
    height: 34px;
    background: var(--sn-border);
}

/* Order Summary Card */
.sn-summary-item-badge-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sn-summary-item-count {
    font-size: 13.5px;
    color: var(--sn-muted);
    font-weight: 500;
}

.sn-summary-chevron {
    display: none;
    color: var(--sn-muted);
}

.sn-summary-products-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 16px;
}

.sn-summary-item-row {
    display: flex;
    align-items: center;
    gap: 14px;
}

.sn-sum-thumb-wrap {
    width: 56px;
    height: 56px;
    border-radius: 10px;
    background: #F8FAFC;
    border: 1px solid var(--sn-border);
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sn-sum-thumb {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 3px;
}

.sn-sum-info-col {
    flex: 1;
    min-width: 0;
}

.sn-sum-item-name {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    margin: 0 0 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sn-sum-item-specs {
    font-size: 11.5px;
    color: var(--sn-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}

.sn-sum-item-qty {
    font-size: 11.5px;
    color: var(--sn-muted-light);
}

.sn-sum-price-col {
    text-align: right;
    flex-shrink: 0;
}

.sn-sum-price {
    font-size: 14px;
    font-weight: 700;
    color: var(--sn-dark);
}

.sn-sum-price-mobile {
    display: none;
    font-size: 14px;
    font-weight: 700;
    color: var(--sn-dark);
}

.sn-mob-qty {
    font-size: 12px;
    font-weight: 500;
    color: var(--sn-muted);
}

.sn-summary-divider {
    height: 1px;
    background: var(--sn-border);
    margin: 16px 0;
}

.sn-pricing-breakdown {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sn-calc-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13.5px;
}

.sn-calc-label {
    color: var(--sn-muted);
}

.sn-calc-val {
    font-weight: 600;
    color: var(--sn-dark);
}

.sn-label-mobile {
    display: none;
}

.sn-coupon-calc-row {
    display: none;
}

.sn-coupon-calc-row.active {
    display: flex;
}

.sn-discount-text {
    color: var(--sn-green) !important;
}

.sn-total-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
}

.sn-total-label {
    font-size: 18px;
    font-weight: 800;
    color: var(--sn-dark);
}

.sn-total-amount {
    font-size: 22px;
    font-weight: 800;
    color: var(--sn-dark);
}

/* Savings Badge */
.sn-savings-badge-row {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
}

.sn-pill-savings {
    background: var(--sn-green-bg);
    border: 1px solid #BBF7D0;
    border-radius: 9999px;
    padding: 6px 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--sn-green);
    font-size: 12.5px;
    font-weight: 700;
}

/* Payment Method Card */
.sn-payment-options-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sn-pay-card {
    border: 1.5px solid var(--sn-border);
    border-radius: 12px;
    padding: 12px 16px;
    cursor: pointer;
    background: #FFFFFF;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 12px;
}

.sn-pay-card:hover {
    border-color: #CBD5E1;
}

.sn-pay-card.active {
    border: 2px solid var(--sn-primary);
    background: var(--sn-primary-subtle);
    box-shadow: 0 0 0 1px var(--sn-primary);
}

.sn-pay-icon-box {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sn-pay-icon-box.swapnopay-icon-bg {
    background: #0F172A;
    width: auto;
    min-width: 44px;
    height: 36px;
    padding: 3px 8px;
    border-radius: 8px;
}

.sn-pay-logo-img {
    height: 24px;
    max-width: 95px;
    object-fit: contain;
}

.sn-official-badge {
    background: #EEF2FF;
    color: #4F46E5;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 6px;
    display: inline-block;
}

.sn-mfs-badges {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    margin-left: auto;
}

.sn-mfs-tag {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
}

.sn-mfs-tag.bkash { color: #E11D48; background: #FFE4E6; }
.sn-mfs-tag.nagad { color: #EA580C; background: #FFEDD5; }
.sn-mfs-tag.rocket { color: #9333EA; background: #F3E8FF; }

.sn-pay-title-col {
    flex: 1;
    min-width: 0;
}

.sn-pay-name {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    line-height: 1.2;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.sn-pay-desc {
    font-size: 11.5px;
    color: var(--sn-muted);
}

.sn-pay-desc-mobile {
    display: none;
}

.sn-card-brand-pills {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-left: auto;
}

.sn-brand-pill {
    font-size: 9.5px;
    font-weight: 800;
    padding: 2px 5px;
    border-radius: 4px;
    border: 1px solid var(--sn-border);
    background: #FFFFFF;
    letter-spacing: -0.2px;
}

.sn-brand-pill.visa {
    color: #1434CB;
}

.sn-brand-pill.mc {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 4px 6px;
}

.sn-brand-pill.mc .dot-red {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #EB001B;
}

.sn-brand-pill.mc .dot-yel {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #F79E1B;
    margin-left: -4px;
}

.sn-brand-pill.amex {
    color: #006FCF;
}

/* Primary Checkout Button */
.sn-checkout-cta-wrap {
    margin-top: 16px;
}

.sn-btn-primary-checkout {
    width: 100%;
    height: 50px;
    background: var(--sn-primary);
    border: none;
    border-radius: 12px;
    color: var(--sn-dark);
    font-size: 15.5px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(251, 191, 36, 0.3);
    transition: all 0.2s ease;
}

.sn-btn-primary-checkout:hover {
    background: var(--sn-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(251, 191, 36, 0.4);
}

/* Free Shipping Box (Mobile/Sidebar) */
.sn-free-shipping-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #FFFFFF;
    border: 1px solid var(--sn-border);
}

.sn-free-shipping-head {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sn-free-shipping-texts strong {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--sn-dark);
}

.sn-free-shipping-texts span {
    font-size: 11.5px;
    color: var(--sn-primary-dark);
    font-weight: 600;
}

.sn-free-shipping-bar-track {
    width: 100%;
    height: 6px;
    background: #E2E8F0;
    border-radius: 9999px;
    overflow: hidden;
}

.sn-free-shipping-bar-fill {
    height: 100%;
    background: var(--sn-primary);
    border-radius: 9999px;
    transition: width 0.3s ease;
}

/* 100% Secure Payment Box */
.sn-card-secure-trust {
    background: #FFFDF5;
    border: 1px solid #FEF3C7;
}

.sn-secure-box-inner {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sn-secure-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #FEF3C7;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sn-secure-meta h4 {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    margin: 0 0 2px 0;
}

.sn-secure-meta p {
    font-size: 11.5px;
    color: var(--sn-muted);
    margin: 0;
}

/* Mobile Saved Address Box */
.sn-mobile-address-card {
    display: none;
    background: #FFFFFF;
    border: 1px solid var(--sn-border);
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 20px;
}

.sn-card-head-compact {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.sn-change-addr-btn {
    background: none;
    border: none;
    color: var(--sn-muted);
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
}

.sn-saved-addr-box {
    background: var(--sn-primary-subtle);
    border: 1px solid #FEF3C7;
    border-radius: 12px;
    padding: 14px;
}

.sn-saved-user-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.sn-saved-name {
    font-size: 14.5px;
    font-weight: 700;
    color: var(--sn-dark);
}

.sn-tag-home {
    background: #FEF3C7;
    color: #B45309;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
}

.sn-saved-details {
    font-size: 12.5px;
    color: var(--sn-muted);
    line-height: 1.4;
}

/* Mobile Promo Code Box */
.sn-mobile-promo-card {
    display: none;
}

.sn-promo-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.sn-promo-head h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--sn-dark);
}

.sn-promo-input-row {
    display: flex;
    gap: 8px;
}

.sn-btn-apply {
    background: var(--sn-primary);
    border: none;
    border-radius: 10px;
    color: var(--sn-dark);
    font-weight: 700;
    font-size: 13.5px;
    padding: 0 18px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s ease;
}

.sn-btn-apply:hover {
    background: var(--sn-primary-hover);
}

.sn-coupon-msg {
    font-size: 12px;
    margin-top: 6px;
}

.sn-coupon-msg.error {
    color: #EF4444;
}

.sn-coupon-msg.success {
    color: var(--sn-green);
    font-weight: 600;
}

/* Sticky Bottom Mobile Dock */
.sn-mobile-bottom-dock {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #FFFFFF;
    padding: 12px 16px;
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08);
    z-index: 999;
}

.sn-btn-mobile-place-order {
    width: 100%;
    height: 48px;
    background: var(--sn-primary);
    border: none;
    border-radius: 12px;
    color: var(--sn-dark);
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(251, 191, 36, 0.3);
}

/* Base SwapnoPay MFS Provider Selector (Desktop) */
.sn-mfs-provider-selector {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    margin: -4px 0 12px 0;
    animation: fadeInMfs 0.25s ease;
}
@keyframes fadeInMfs {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
.sn-mfs-selector-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}
.sn-mfs-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.sn-mfs-option {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 9px 12px;
    cursor: pointer;
    transition: all 0.15s ease;
    position: relative;
    overflow: hidden;
}
.sn-mfs-option:hover {
    border-color: #94a3b8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.sn-mfs-option.active {
    border-color: #0f172a;
    background: #f0f9ff;
    box-shadow: 0 2px 8px rgba(15,23,42,0.1);
}
.sn-mfs-radio {
    width: 16px;
    height: 16px;
    border: 2px solid #cbd5e1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.sn-mfs-option.active .sn-mfs-radio {
    border-color: #0f172a;
}
.sn-mfs-radio-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: transparent;
}
.sn-mfs-option.active .sn-mfs-radio-dot {
    background: #0f172a;
}
.sn-mfs-color-bar {
    width: 4px;
    height: 22px;
    border-radius: 2px;
    flex-shrink: 0;
}
.sn-mfs-label {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}

/* ============================================================
   RESPONSIVE MEDIA QUERIES (COMPACT & SHORT-SCROLL MOBILE LAYOUT)
   ============================================================ */
@media (max-width: 1024px) {
    body {
        padding-bottom: 80px !important;
        overflow-x: hidden !important;
    }

    /* Hide desktop global site header & footer on mobile checkout */
    .sn-header-wrap,
    .sn-footer-wrap {
        display: none !important;
    }

    .sn-checkout-page-wrapper {
        padding: 4px 0 80px 0;
    }

    .sn-checkout-container {
        padding: 0 10px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    #mainCheckoutForm {
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
        box-sizing: border-box !important;
    }

    /* Mobile Header Bar - Unified 1-Row Native App Bar */
    .sn-mobile-header-bar {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 10px !important;
        padding: 6px 0 10px 0 !important;
        margin-bottom: 6px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .sn-mob-back-btn {
        width: 36px !important;
        height: 36px !important;
        border-radius: 50% !important;
        background: #FFFFFF !important;
        border: 1px solid var(--sn-border) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        color: var(--sn-dark) !important;
        text-decoration: none !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
    }

    .sn-mob-title-wrap {
        flex: 1 !important;
        min-width: 0 !important;
    }

    .sn-mob-main-title {
        font-size: 19px !important;
        font-weight: 800 !important;
        color: var(--sn-dark) !important;
        margin: 0 !important;
        line-height: 1.2 !important;
        letter-spacing: -0.3px !important;
    }

    .sn-mob-badge-lock {
        font-size: 14px !important;
    }

    .sn-mob-sub-title {
        font-size: 11.5px !important;
        color: var(--sn-muted) !important;
        margin: 2px 0 0 0 !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .sn-mob-badge-safe {
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
        background: #EFF6FF !important;
        border: 1px solid #BFDBFE !important;
        border-radius: 9999px !important;
        padding: 3px 8px !important;
        flex-shrink: 0 !important;
    }

    .sn-mob-badge-text .t1 {
        font-size: 10px !important;
        font-weight: 700 !important;
        color: #1E3A8A !important;
        line-height: 1 !important;
    }

    /* Hide desktop checkout header on mobile since integrated above */
    .sn-checkout-header {
        display: none !important;
    }

    /* Hide desktop-only elements */
    .sn-checkout-top-nav,
    .sn-stepper-wrap,
    .sn-trust-badges-bar,
    .sn-checkout-cta-wrap,
    .sn-savings-badge-row,
    .sn-desktop-tax-row,
    .sn-desktop-sub,
    .sn-title-desktop,
    .sn-pay-sub-desktop,
    .sn-pay-desc-desktop,
    .sn-sum-price,
    .sn-card-brand-pills,
    .sn-mfs-badges {
        display: none !important;
    }

    .sn-mobile-sub {
        display: block;
    }

    .sn-title-mobile {
        display: block;
    }

    .sn-pay-desc-mobile {
        display: block;
    }

    .sn-sum-price-mobile {
        display: block;
    }

    .sn-label-desktop {
        display: none;
    }

    .sn-label-mobile {
        display: inline;
    }

    /* Flatten columns so direct children stretch to 100% full width */
    .sn-checkout-grid {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        max-width: 100% !important;
        gap: 0 !important;
        box-sizing: border-box !important;
    }

    .sn-checkout-left-col,
    .sn-checkout-right-col {
        display: contents !important;
        width: 100% !important;
    }

    /* Ultra-Compact Card Shell - 100% Full Width Edge-to-Edge */
    .sn-checkout-card {
        padding: 10px 12px !important;
        border-radius: 12px !important;
        margin-bottom: 8px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .sn-mobile-address-card,
    .sn-mobile-promo-card,
    #shippingInfoCard,
    #deliveryMethodCard,
    #paymentMethodCard,
    #orderSummaryCard,
    #freeShippingBannerCard,
    #securePaymentBadgeCard {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .sn-card-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .sn-card-icon-wrap svg {
        width: 17px;
        height: 17px;
    }

    .sn-card-title-col h2 {
        font-size: 13.5px;
        font-weight: 700;
        margin: 0;
    }

    .sn-card-title-col p {
        font-size: 11px;
        margin: 0;
    }

    /* 1. Shipping Address Preview */
    .sn-mobile-address-card {
        display: block !important;
        order: 1;
        background: #FFFFFF;
        border: 1px solid var(--sn-border);
        border-radius: 12px;
        padding: 10px 12px;
        margin-bottom: 8px;
    }

    .sn-card-head-compact {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 6px;
    }

    .sn-head-title-row {
        gap: 6px;
    }

    .sn-head-title-row svg {
        width: 16px;
        height: 16px;
    }

    .sn-head-title-row h3 {
        font-size: 13.5px;
        font-weight: 700;
    }

    .sn-change-addr-btn {
        font-size: 11.5px;
        font-weight: 600;
        color: #2563EB;
        gap: 2px;
    }

    .sn-saved-addr-box {
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 6px 10px;
    }

    .sn-saved-user-row {
        gap: 6px;
        margin-bottom: 2px;
    }

    .sn-saved-user-row svg {
        width: 14px;
        height: 14px;
    }

    .sn-saved-name {
        font-size: 12.5px;
        font-weight: 700;
    }

    .sn-tag-home {
        font-size: 9.5px;
        padding: 1px 6px;
        border-radius: 4px;
    }

    .sn-saved-details {
        font-size: 11.5px;
        line-height: 1.3;
        color: var(--sn-muted);
    }

    /* 2. Full Shipping Address Form (Collapsed by default, opens if Change is clicked) */
    #shippingInfoCard {
        display: none;
        order: 2;
        padding: 10px 12px;
        border-radius: 12px;
        margin-bottom: 8px;
    }

    .sn-input-grid-2,
    .sn-input-grid-3 {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
    }

    .sn-form-group {
        margin-bottom: 8px;
    }

    .sn-form-label {
        font-size: 11.5px;
        margin-bottom: 3px;
    }

    .sn-form-input,
    .sn-form-select {
        font-size: 14px !important;
        height: 40px;
        border-radius: 8px;
    }

    .sn-phone-prefix {
        height: 40px;
    }

    /* 3. Delivery Method (Ultra-compact horizontal slim rows) */
    #deliveryMethodCard {
        order: 3;
        margin-bottom: 8px;
    }

    .sn-shipping-options-grid {
        display: flex !important;
        flex-direction: column !important;
        gap: 6px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .sn-ship-card {
        position: relative !important;
        min-height: unset !important;
        padding: 8px 12px !important;
        border-radius: 10px !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 10px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .sn-ship-card-top {
        margin-bottom: 0 !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        flex-shrink: 0 !important;
        position: static !important; /* CRITICAL: Must be static so .sn-badge-popular anchors to .sn-ship-card */
    }

    .sn-radio-indicator {
        width: 16px;
        height: 16px;
    }

    .sn-radio-dot {
        width: 8px;
        height: 8px;
    }

    .sn-ship-method-icon {
        width: 18px;
        height: 18px;
    }

    .sn-ship-card-info {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    .sn-ship-title {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--sn-dark);
        margin-bottom: 1px;
        line-height: 1.2;
    }

    .sn-ship-time {
        font-size: 11px;
        color: var(--sn-muted);
        line-height: 1.2;
    }

    .sn-ship-price {
        font-size: 13px;
        font-weight: 800;
        color: var(--sn-dark);
        margin-top: 0;
        margin-left: auto;
        flex-shrink: 0;
    }

    .sn-badge-popular {
        position: absolute !important;
        top: -7px !important;
        right: 12px !important;
        left: auto !important;
        margin-left: 0 !important;
        font-size: 9px !important;
        padding: 1px 7px !important;
        border-radius: 9999px !important;
        background: #FEF3C7 !important;
        color: #B45309 !important;
        font-weight: 700 !important;
        border: 1px solid #FDE68A !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.06) !important;
        z-index: 2 !important;
    }

    /* 4. Have a coupon code? (Compact single row) */
    .sn-mobile-promo-card {
        display: block !important;
        order: 4;
        background: #FFFFFF;
        border: 1px solid var(--sn-border);
        border-radius: 12px;
        padding: 8px 12px;
        margin-bottom: 8px;
    }

    .sn-promo-head {
        margin-bottom: 6px;
        gap: 6px;
    }

    .sn-promo-head svg {
        width: 15px;
        height: 15px;
    }

    .sn-promo-head h3 {
        font-size: 12.5px;
        font-weight: 700;
    }

    .sn-promo-input-row {
        gap: 6px;
    }

    .sn-promo-input-row .sn-form-input {
        height: 36px !important;
        font-size: 12.5px !important;
        padding: 0 10px;
        border-radius: 8px;
    }

    .sn-btn-apply {
        height: 36px;
        padding: 0 14px;
        font-size: 12px;
        font-weight: 700;
        border-radius: 8px;
    }

    .sn-coupon-msg {
        font-size: 11px;
        margin-top: 4px;
    }

    /* 5. Payment Method (Compact Cards + 1-Row MFS Selector) */
    #paymentMethodCard {
        order: 5;
        margin-bottom: 8px;
    }

    .sn-payment-options-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .sn-pay-card {
        padding: 8px 10px;
        border-radius: 10px;
        gap: 8px;
    }

    .sn-pay-icon-box {
        width: 28px;
        height: 28px;
        border-radius: 6px;
    }

    .sn-pay-icon-box.swapnopay-icon-bg {
        min-width: 36px;
        height: 28px;
        padding: 2px 5px;
    }

    .sn-pay-logo-img {
        height: 18px;
        max-width: 75px;
    }

    .sn-pay-name {
        font-size: 12.5px;
        line-height: 1.2;
    }

    .sn-official-badge {
        font-size: 8.5px;
        padding: 1px 4px;
        margin-left: 4px;
    }

    .sn-pay-desc-mobile {
        font-size: 10.5px;
        line-height: 1.2;
        display: block;
    }

    .sn-mfs-provider-selector {
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 6px 8px;
        margin: 2px 0 4px 0;
    }

    .sn-mfs-selector-label {
        font-size: 10px;
        margin-bottom: 4px;
        gap: 4px;
    }

    .sn-mfs-selector-label svg {
        width: 12px;
        height: 12px;
    }

    .sn-mfs-grid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 4px !important;
    }

    .sn-mfs-option {
        padding: 5px 2px !important;
        border-radius: 6px !important;
        gap: 3px !important;
        justify-content: center !important;
        border-width: 1px !important;
    }

    .sn-mfs-radio {
        display: none !important;
    }

    .sn-mfs-color-bar {
        width: 3px !important;
        height: 14px !important;
        border-radius: 2px !important;
    }

    .sn-mfs-label {
        font-size: 10.5px !important;
        font-weight: 700 !important;
    }

    /* 6. Order Summary (Compact + Scrollable Items Preview) */
    #orderSummaryCard {
        order: 6;
        background: #FFFFFF;
        border: 1px solid var(--sn-border);
        border-radius: 12px;
        padding: 10px 12px;
        margin-bottom: 8px;
    }

    #orderSummaryCard .sn-card-header {
        margin-bottom: 6px;
        cursor: pointer;
    }

    #orderSummaryCard .sn-card-header h2 {
        font-size: 13.5px;
        font-weight: 700;
    }

    .sn-summary-item-count {
        font-size: 11.5px;
    }

    .sn-summary-chevron {
        display: block;
        transition: transform 0.2s ease;
        transform: rotate(90deg);
    }

    .sn-summary-products-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 6px;
        max-height: 160px;
        overflow-y: auto;
    }

    .sn-summary-item-row {
        gap: 8px;
        padding-bottom: 4px;
        border-bottom: 1px dashed var(--sn-border-light);
    }

    .sn-summary-item-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .sn-sum-thumb-wrap {
        width: 36px;
        height: 36px;
        border-radius: 6px;
    }

    .sn-sum-item-name {
        font-size: 11.5px;
        margin-bottom: 1px;
    }

    .sn-sum-item-specs {
        font-size: 9.5px;
        margin-bottom: 1px;
    }

    .sn-sum-item-qty {
        font-size: 9.5px;
    }

    .sn-sum-price-mobile {
        font-size: 12px;
        font-weight: 700;
        display: block;
    }

    .sn-summary-divider {
        margin: 6px 0;
    }

    .sn-pricing-breakdown {
        gap: 5px;
    }

    .sn-calc-row {
        font-size: 12px;
    }

    .sn-total-row {
        margin-top: 4px;
        padding-top: 6px;
        border-top: 1px solid var(--sn-border);
    }

    .sn-total-label {
        font-size: 14px;
        font-weight: 800;
    }

    .sn-total-amount {
        font-size: 16.5px;
        font-weight: 800;
        color: var(--sn-dark);
    }

    /* 7. Free Shipping Progress */
    #freeShippingBannerCard {
        order: 7;
        background: #FFFFFF;
        border: 1px solid var(--sn-border);
        border-radius: 10px;
        padding: 6px 10px;
        margin-bottom: 8px;
        gap: 4px;
    }

    .sn-free-shipping-head {
        gap: 6px;
    }

    .sn-free-shipping-head svg {
        width: 15px;
        height: 15px;
    }

    .sn-free-shipping-texts strong {
        font-size: 11px;
    }

    .sn-free-shipping-texts span {
        font-size: 10px;
    }

    .sn-free-shipping-bar-track {
        height: 4px;
    }

    /* 8. 100% Secure Payment Trust */
    #securePaymentBadgeCard {
        order: 8;
        background: #FFFDF5;
        border: 1px solid #FEF3C7;
        border-radius: 10px;
        padding: 6px 10px;
        margin-bottom: 8px;
    }

    .sn-secure-box-inner {
        gap: 8px;
    }

    .sn-secure-icon-wrap {
        width: 24px;
        height: 24px;
    }

    .sn-secure-icon-wrap svg {
        width: 14px;
        height: 14px;
    }

    .sn-secure-meta h4 {
        font-size: 11px;
        margin-bottom: 1px;
    }

    .sn-secure-meta p {
        font-size: 9.5px;
    }

    /* 9. Sticky Bottom Action Dock */
    .sn-mobile-bottom-dock {
        display: block !important;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #FFFFFF;
        padding: 8px 14px max(8px, env(safe-area-inset-bottom)) 14px;
        box-shadow: 0 -3px 12px rgba(0, 0, 0, 0.08);
        border-top: 1px solid var(--sn-border);
        z-index: 9999 !important;
    }

    .sn-btn-mobile-place-order {
        width: 100%;
        height: 44px;
        background: var(--sn-primary);
        border: none;
        border-radius: 10px;
        color: var(--sn-dark);
        font-size: 15px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(251, 191, 36, 0.35);
        transition: background 0.15s ease, transform 0.1s ease;
    }

    .sn-btn-mobile-place-order:active {
        transform: scale(0.98);
        background: var(--sn-primary-hover);
    }
}

@media (max-width: 360px) {
    .sn-mob-badge-safe {
        display: none !important; /* Keep top bar clean on ultra narrow 320px screens */
    }
}
</style>

<!-- ============================================================
     INTERACTIVE FUNCTIONALITY & REAL-TIME RECALCULATION
     ============================================================ -->
<script>
let currentSubtotal = <?php echo (float)$table_total_price; ?>;
let currentShipping = <?php echo (float)$shipping_cost; ?>;
let currentDiscount = <?php echo (float)$coupon_discount; ?>;
const freeShippingThreshold = 2000;

// Format Currency
function formatTaka(val) {
    return '৳ ' + Math.round(val).toLocaleString();
}

// 1. SELECT SHIPPING METHOD
function selectShipping(method, cost) {
    currentShipping = parseFloat(cost);
    document.getElementById('inputShippingCost').value = currentShipping.toFixed(2);
    document.getElementById('inputShippingMethod').value = method;

    // Update active class on shipping cards
    document.querySelectorAll('.sn-ship-card').forEach(el => el.classList.remove('active'));
    if (method === 'standard') document.getElementById('shipCardStandard')?.classList.add('active');
    if (method === 'express') document.getElementById('shipCardExpress')?.classList.add('active');
    if (method === 'pickup') document.getElementById('shipCardPickup')?.classList.add('active');

    // Update summary
    const shipValEl = document.getElementById('summaryShippingVal');
    if (shipValEl) shipValEl.textContent = formatTaka(currentShipping);

    recalculateTotal();
}

// 2. SELECT PAYMENT METHOD
function selectPayment(method) {
    document.getElementById('inputPaymentMethod').value = method;

    // Update active class on payment cards
    document.querySelectorAll('.sn-pay-card').forEach(el => el.classList.remove('active'));
    if (method === 'swapnopay') document.getElementById('payCardSwapnopay')?.classList.add('active');
    if (method === 'cod') document.getElementById('payCardCod')?.classList.add('active');

    // Show/hide MFS provider selector
    const mfsSelector = document.getElementById('mfsProviderSelector');
    if (mfsSelector) {
        mfsSelector.style.display = (method === 'swapnopay') ? 'block' : 'none';
    }

    // Update CTA button label
    const submitBtn = document.getElementById('btnSubmitText');
    if (submitBtn) {
        if (method === 'cod') {
            submitBtn.textContent = 'Place Order (COD)';
        } else {
            const provider = document.getElementById('inputMfsProvider')?.value || 'SwapnoPay';
            submitBtn.textContent = 'Pay with ' + provider;
        }
    }

    const mobBtn = document.querySelector('.sn-btn-mobile-place-order span');
    if (mobBtn) {
        if (method === 'cod') {
            mobBtn.textContent = 'Place Order (COD)';
        } else {
            const provider = document.getElementById('inputMfsProvider')?.value || 'SwapnoPay';
            mobBtn.textContent = 'Pay with ' + provider;
        }
    }
}

function selectMfsProvider(provider) {
    document.getElementById('inputMfsProvider').value = provider;

    // Update active class on MFS options
    document.querySelectorAll('.sn-mfs-option').forEach(el => el.classList.remove('active'));
    const targetEl = document.querySelector('.sn-mfs-option[data-method="' + provider + '"]');
    if (targetEl) targetEl.classList.add('active');

    // Update button text
    const submitBtn = document.getElementById('btnSubmitText');
    if (submitBtn) submitBtn.textContent = 'Pay with ' + provider;
    const mobBtn = document.querySelector('.sn-btn-mobile-place-order span');
    if (mobBtn) mobBtn.textContent = 'Pay with ' + provider;
}

// 3. RECALCULATE TOTAL
function recalculateTotal() {
    const total = Math.max(0, (currentSubtotal + currentShipping) - currentDiscount);
    const totalEl = document.getElementById('summaryGrandTotalVal');
    if (totalEl) totalEl.textContent = formatTaka(total);

    // Free shipping threshold update
    const rem = Math.max(0, freeShippingThreshold - currentSubtotal);
    const subTxt = document.getElementById('freeShippingSubText');
    const bar = document.getElementById('freeShippingBarFill');
    if (subTxt && bar) {
        if (rem > 0) {
            subTxt.textContent = `Add ${formatTaka(rem)} more to get free shipping!`;
            const pct = Math.min(100, Math.round((currentSubtotal / freeShippingThreshold) * 100));
            bar.style.width = pct + '%';
        } else {
            subTxt.textContent = `🎉 Congratulations! You unlocked free shipping!`;
            bar.style.width = '100%';
        }
    }
}

// 4. COUPON APPLICATION (AJAX)
function handleCouponSubmit(inputId) {
    const input = document.getElementById(inputId);
    const code = input ? input.value.trim() : '';
    const alertBox = document.getElementById('couponMobileAlert');

    if (!code) {
        if (alertBox) {
            alertBox.className = 'sn-coupon-msg error';
            alertBox.textContent = 'Please enter a coupon code.';
        }
        return;
    }

    const formData = new FormData();
    formData.append('apply_coupon_ajax', '1');
    formData.append('coupon_code', code);

    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            currentDiscount = parseFloat(res.discount);
            if (alertBox) {
                alertBox.className = 'sn-coupon-msg success';
                alertBox.textContent = res.message;
            }
            // Update summary row
            const couponRow = document.getElementById('summaryCouponRow');
            const couponVal = document.getElementById('summaryCouponVal');
            if (couponRow && couponVal) {
                couponRow.classList.add('active');
                couponVal.textContent = '- ' + formatTaka(currentDiscount);
            }
            recalculateTotal();
        } else {
            if (alertBox) {
                alertBox.className = 'sn-coupon-msg error';
                alertBox.textContent = res.message || 'Invalid coupon code';
            }
        }
    })
    .catch(() => {
        if (alertBox) {
            alertBox.className = 'sn-coupon-msg error';
            alertBox.textContent = 'Error applying coupon. Please try again.';
        }
    });
}

// 5. MOBILE ADDRESS EXPANDER TOGGLE
function toggleAddressEditor() {
    const card = document.getElementById('shippingInfoCard');
    if (card) {
        if (card.style.display === 'block') {
            card.style.display = 'none';
        } else {
            card.style.display = 'block';
            card.scrollIntoView({ behavior: 'smooth' });
        }
    }
}

// 6. TOGGLE MOBILE SUMMARY LIST
function toggleMobileSummaryList() {
    if (window.innerWidth <= 1024) {
        const list = document.getElementById('summaryProductsList');
        const chevron = document.querySelector('.sn-summary-chevron');
        if (list) {
            const isHidden = (window.getComputedStyle(list).display === 'none');
            list.style.display = isHidden ? 'flex' : 'none';
            if (chevron) {
                chevron.style.transform = isHidden ? 'rotate(90deg)' : 'rotate(0deg)';
            }
        }
    }
}

// 7. REACTIVE ADDRESS SYNC
function updateAddressPreview() {
    const name = document.getElementById('fullNameInput')?.value?.trim() || 'Guest Customer';
    const addr = document.getElementById('addressInput')?.value?.trim() || '';
    const div = document.getElementById('divisionSelect')?.value?.trim() || '';
    const dist = document.getElementById('districtSelect')?.value?.trim() || '';

    const pName = document.getElementById('previewSavedName');
    const pAddr = document.getElementById('previewSavedAddress');
    if (pName) pName.textContent = name;
    if (pAddr) {
        if (addr || dist || div) {
            pAddr.innerHTML = `${addr ? addr + '<br>' : ''}${dist ? dist + ', ' : ''}${div ? div + ', ' : ''}Bangladesh`;
        } else {
            pAddr.textContent = 'Enter your shipping address below.';
        }
    }
}

// Ensure total is correctly initialized on load
document.addEventListener('DOMContentLoaded', () => {
    recalculateTotal();
    selectPayment('swapnopay');
});
</script>

<?php require_once('footer.php'); ?>