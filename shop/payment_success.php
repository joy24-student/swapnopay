<?php
// Add at the very top
ob_start();
session_start();

require_once('header.php');
require_once('admin/inc/config.php'); // Include database connection

// A receipt belongs to the signed-in customer and uses the saved amount.
$reference = (string)($_GET['payment_id'] ?? $_GET['tran_id'] ?? '');
$receiptQuery = $pdo->prepare('SELECT * FROM tbl_payment WHERE payment_id=? AND customer_id=? LIMIT 1');
$receiptQuery->execute([$reference,$_SESSION['customer']['cust_id'] ?? 0]);
$receipt = $receiptQuery->fetch();
if (!$receipt || empty($_SESSION['customer'])) { http_response_code(404); exit('Order not found.'); }
$_GET['amount'] = $receipt['paid_amount'];
$_GET['tran_id'] = $receipt['payment_id'];
if (($_GET['method'] ?? '') === 'cod' && $receipt['payment_method'] !== 'Cash on Delivery') { http_response_code(404); exit('Order not found.'); }


// Function to fetch country name
function getCountryName($pdo, $country_id) {
    // Assuming country_id is used for lookup
    $statement = $pdo->prepare("SELECT country_name FROM tbl_country WHERE country_id = ?");
    $statement->execute([$country_id]);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['country_name'] : 'N/A';
}

// Function to format currency
function formatCurrency($amount) {
    // Assuming LANG_VALUE_1 holds the currency symbol (e.g., '$')
    return LANG_VALUE_1 . number_format($amount, 2);
}

// Function to clear all cart and checkout session variables
function clearCartSessions() {
    // Clear the main cart content arrays
    unset($_SESSION['cart_p_id']);
    unset($_SESSION['cart_size_id']);
    unset($_SESSION['cart_size_name']);
    unset($_SESSION['cart_color_id']);
    unset($_SESSION['cart_color_name']);
    unset($_SESSION['cart_p_qty']);
    unset($_SESSION['cart_p_current_price']);
    
    // Clear related checkout and payment data
    unset($_SESSION['final_total']);
    unset($_SESSION['shipping_cost']);
    unset($_SESSION['coupon_code']);
    unset($_SESSION['coupon_discount']);
    unset($_SESSION['coupon_id']);
    unset($_SESSION['payment_data']); 
    unset($_SESSION['billing_address_details']);
    unset($_SESSION['shipping_address_details']);
}
?>
<div class="row">
    <div class="col-md-12 text-center">

        <?php if (isset($_GET['method'])): ?>
        
            <?php if ($_GET['method'] == 'cod'): ?>
                <?php 
                // === COD SUCCESS: Clear Cart Here ===
                clearCartSessions();
                if (isset($_SESSION['customer']['cust_id']) && !empty($_SESSION['customer']['cust_id'])) {
                    if (function_exists('clearCartFromDatabase')) {
                        clearCartFromDatabase($pdo, $_SESSION['customer']['cust_id']);
                    } else {
                        error_log('clearCartFromDatabase function not found during COD success.');
                    }
                }
                // ===================================
                $cod_tran_id = strip_tags($_GET['tran_id'] ?? ($_GET['payment_id'] ?? ''));
                if (!empty($cod_tran_id)) {
                    $stmt_cod = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ? OR txnid = ? LIMIT 1");
                    $stmt_cod->execute([$cod_tran_id, $cod_tran_id]);
                    $cod_payment = $stmt_cod->fetch(PDO::FETCH_ASSOC);
                    if ($cod_payment) {
                        $stmt_order = $pdo->prepare("
                            SELECT o.*, p.p_featured_photo, p.p_name AS catalog_name
                            FROM tbl_order o
                            LEFT JOIN tbl_product p ON o.product_id = p.p_id
                            WHERE o.payment_id = ?
                            ORDER BY o.id ASC
                        ");
                        $stmt_order->execute([$cod_payment['payment_id']]);
                        $order_items = $stmt_order->fetchAll(PDO::FETCH_ASSOC);
                        $orders = is_array($order_items) ? $order_items : [];

                        $stmt_settings = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1");
                        $settings = $stmt_settings ? $stmt_settings->fetch(PDO::FETCH_ASSOC) : [];

                        $order = $cod_payment;
                        $items = $orders;
                        ?>
                        <div class="page" style="padding: 20px 0 40px 0; background: #f8fafc;">
                            <div class="container text-left" style="text-align: left;">
                                <div style="max-width: 1040px; margin: 0 auto 16px auto; display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 20px; flex-wrap: wrap; gap: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:#dcfce7; color:#15803d; font-size:14px; font-weight:bold;">✓</span>
                                        <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Cash on Delivery Order Placed Successfully!</span>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <a href="invoice.php?payment_id=<?= urlencode($cod_payment['payment_id']) ?>&print=1" target="_blank" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:13px; padding:8px 16px; border-radius:8px;">
                                            <i class="fa fa-print"></i> Print Receipt
                                        </a>
                                        <a href="customer-order.php" class="btn btn-default" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:13px; padding:8px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#334155;">
                                            <i class="fa fa-list"></i> My Orders
                                        </a>
                                        <a href="index.php" class="btn btn-default" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:13px; padding:8px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#334155;">
                                            <i class="fa fa-shopping-bag"></i> Continue Shopping
                                        </a>
                                    </div>
                                </div>
                                <?php require(__DIR__ . '/inc/order_receipt_view.php'); ?>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            <?php elseif (($_GET['method'] == 'sslcommerz' || $_GET['method'] == 'swapnopay' || $_GET['method'] == 'SwapnoPay') && (isset($_GET['tran_id']) || isset($_GET['payment_id']))): ?>
                <?php
                $tran_id = strip_tags($_GET['tran_id'] ?? ($_GET['payment_id'] ?? ''));
                $method_param = strtolower($_GET['method']);

                // Fetch payment details - query by payment_id or txnid
                $stmt = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_id = ? OR txnid = ? LIMIT 1");
                $stmt->execute([$tran_id, $tran_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($payment) {
                    if (strtolower($payment['payment_status'] ?? '') !== 'completed') {
                        ?>
                        <div class="page">
                            <div class="container text-center py-5">
                                <div class="alert alert-warning" style="padding: 30px; border-radius: 12px; margin-top: 30px;">
                                    <i class="fa fa-clock-o fa-3x mb-3 text-warning" style="font-size: 50px;"></i>
                                    <h3>Payment Pending Confirmation</h3>
                                    <p>Your order (Transaction ID: <b><?= htmlspecialchars($tran_id) ?></b>) has not been confirmed as completed yet (Current status: <b><?= htmlspecialchars($payment['payment_status']) ?></b>).</p>
                                    <p>Once your payment is verified by the gateway or merchant, your order will be finalized.</p>
                                    <a href="index.php" class="btn btn-primary mt-3"><i class="fa fa-home"></i> Return to Shop</a>
                                </div>
                            </div>
                        </div>
                        <?php
                        require_once('footer.php');
                        exit;
                    }

                    $payment_id = $payment['id']; // Get the primary ID
                    $cust_id = $payment['customer_id'];
                    $paid_amount = $payment['paid_amount'];

                    // Status is strictly updated by verified server-to-server webhook or SMS confirmation.
                    // payment_success.php only displays the current state of the order.

                    // Ensure we have the customer record to display email safely
                    $customer = null;
                    if (!empty($cust_id)) {
                        $stmt_c = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ? LIMIT 1");
                        $stmt_c->execute([$cust_id]);
                        $customer = $stmt_c->fetch(PDO::FETCH_ASSOC);
                    }
                    if (!$customer) {
                        $customer = ['cust_email' => ''];
                    }

                    // =================================================================
                    // === PAYMENT SUCCESS: Clear Cart Here ===
                    // =================================================================
                    clearCartSessions();
                    // Also clear persistent DB cart for this customer if logged in
                    $db_clear_cust_id = $cust_id ?? ($payment['customer_id'] ?? null);
                    if (!empty($db_clear_cust_id)) {
                        if (function_exists('clearCartFromDatabase')) {
                            clearCartFromDatabase($pdo, $db_clear_cust_id);
                        } else {
                            error_log('clearCartFromDatabase function not found during payment success.');
                        }
                    }
                    // =================================================================

                    // Fetch order items associated with this payment ID
                    $stmt_order = $pdo->prepare("
                        SELECT o.*, p.p_featured_photo, p.p_name AS catalog_name
                        FROM tbl_order o
                        LEFT JOIN tbl_product p ON o.product_id = p.p_id
                        WHERE o.payment_id = ?
                        ORDER BY o.id ASC
                    ");
                    $stmt_order->execute([$tran_id]);
                    $order_items = $stmt_order->fetchAll(PDO::FETCH_ASSOC);
                    $orders = is_array($order_items) ? $order_items : [];

                    // Fetch store settings for Logo and Store identity
                    $stmt_settings = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1");
                    $settings = $stmt_settings ? $stmt_settings->fetch(PDO::FETCH_ASSOC) : [];

                    $order = $payment;
                    $items = $orders;

                    // --- DISPLAY SUCCESS MESSAGE AND PIXEL-PERFECT INVOICE ---
                ?>
                <div class="page" style="padding: 20px 0 40px 0; background: #f8fafc;">
                    <div class="container text-left" style="text-align: left;">
                        <div style="max-width: 1040px; margin: 0 auto 16px auto; display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 20px; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:#dcfce7; color:#15803d; font-size:14px; font-weight:bold;">✓</span>
                                <span style="font-size: 14px; font-weight: 700; color: #0f172a;">Payment Received &amp; Order Confirmed!</span>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="invoice.php?payment_id=<?= urlencode($payment['payment_id']) ?>&print=1" target="_blank" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:13px; padding:8px 16px; border-radius:8px;">
                                    <i class="fa fa-print"></i> Print Receipt
                                </a>
                                <a href="customer-order.php" class="btn btn-default" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:13px; padding:8px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#334155;">
                                    <i class="fa fa-list"></i> My Orders
                                </a>
                                <a href="index.php" class="btn btn-default" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:13px; padding:8px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#334155;">
                                    <i class="fa fa-shopping-bag"></i> Continue Shopping
                                </a>
                            </div>
                        </div>

                        <?php require(__DIR__ . '/inc/order_receipt_view.php'); ?>
                    </div>
                </div>
                <?php
                } else {
                    echo '<div class="alert alert-danger text-center">Payment record not found. Please contact support.</div>';
                }
                ?>
            <?php else: ?>
                <div class="alert alert-danger text-center">Invalid payment method or missing transaction ID.</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-danger text-center">No payment method detected.</div>
        <?php endif; ?>
    </div>
</div>
<script>
    function printInvoice() {
        var printContents = document.getElementById('printableArea').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    }
</script>
<?php require_once('footer.php'); ?>