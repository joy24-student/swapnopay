<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$cur_page = 'order-tracking.php';
require_once __DIR__ . '/header.php';

$search_phone = trim((string)($_GET['phone'] ?? ($_POST['phone'] ?? '')));
$search_order_id = trim((string)($_GET['id'] ?? ($_GET['order_id'] ?? ($_POST['order_id'] ?? ''))));

if (empty($search_phone) && isset($_SESSION['customer']['cust_phone'])) {
    $search_phone = $_SESSION['customer']['cust_phone'];
}

$matched_order = null;
$order_items = [];
$search_error = '';

if (!empty($search_order_id)) {
    try {
        $clean_order_id = ltrim($search_order_id, '#');
        $sql = "
            SELECT p.*, c.cust_name as customer_acc_name, c.cust_phone as customer_acc_phone, c.cust_email as customer_acc_email
            FROM tbl_payment p
            LEFT JOIN tbl_customer c ON p.customer_id = c.cust_id
            LEFT JOIN tbl_order o ON o.payment_id = p.payment_id
            WHERE (
                p.payment_id = :ord_id 
                OR p.txnid = :ord_id 
                OR o.order_no = :ord_id
            )
        ";

        $params = [':ord_id' => $clean_order_id];

        if (!empty($search_phone)) {
            $clean_phone = preg_replace('/[^0-9]/', '', $search_phone);
            $sql .= " AND (
                p.billing_phone LIKE :phone 
                OR p.shipping_phone LIKE :phone 
                OR c.cust_phone LIKE :phone
            )";
            $params[':phone'] = '%' . substr($clean_phone, -10) . '%';
        }

        $sql .= " ORDER BY p.id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $matched_order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($matched_order) {
            $stmt_items = $pdo->prepare("
                SELECT o.*, p.p_featured_photo 
                FROM tbl_order o
                LEFT JOIN tbl_product p ON o.product_id = p.p_id
                WHERE o.payment_id = ?
                ORDER BY o.id ASC
            ");
            $stmt_items->execute([$matched_order['payment_id']]);
            $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $search_error = 'No matching order found for Order ID: ' . htmlspecialchars($search_order_id) . ($search_phone ? ' with phone ' . htmlspecialchars($search_phone) : '') . '. Please check your details and try again.';
        }
    } catch (Throwable $e) {
        error_log('Order tracking error: ' . $e->getMessage());
        $search_error = 'An error occurred while retrieving order status. Please try again.';
    }
}
?>

<div class="sn-page-container" style="background: #f8fafc; min-height: 80vh; padding: 30px 16px 60px 16px;">
    <div style="max-width: 860px; margin: 0 auto;">

        <!-- Header -->
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 54px; height: 54px; border-radius: 16px; background: #fffbeb; border: 1.5px solid #fef08a; color: #b45309; font-size: 24px; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(250, 184, 2, 0.15);">
                <i class="fa fa-map-marker-alt"></i>
            </div>
            <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px 0; letter-spacing: -0.5px;">Track Your Order</h1>
            <p style="font-size: 14px; color: #64748b; margin: 0;">Enter your Phone Number and Order ID to check live delivery updates.</p>
        </div>

        <!-- Search Card -->
        <div style="background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 20px -2px rgba(0,0,0,0.05); margin-bottom: 25px;">
            <form action="order-tracking.php" method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
                <div style="flex: 1 1 240px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Mobile Number</label>
                    <div style="position: relative;">
                        <i class="fa fa-phone" style="position: absolute; left: 14px; top: 14px; color: #94a3b8; font-size: 14px;"></i>
                        <input type="text" name="phone" value="<?= htmlspecialchars($search_phone) ?>" placeholder="e.g. 01700000000" style="width: 100%; height: 44px; padding-left: 38px; padding-right: 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 14px; outline: none; transition: border-color 0.2s;" required>
                    </div>
                </div>
                <div style="flex: 1 1 240px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Order ID / Payment ID</label>
                    <div style="position: relative;">
                        <i class="fa fa-receipt" style="position: absolute; left: 14px; top: 14px; color: #94a3b8; font-size: 14px;"></i>
                        <input type="text" name="id" value="<?= htmlspecialchars($search_order_id) ?>" placeholder="e.g. ORD-20261018-8371 or COD-..." style="width: 100%; height: 44px; padding-left: 38px; padding-right: 14px; border-radius: 10px; border: 1.5px solid #cbd5e1; font-size: 14px; outline: none; transition: border-color 0.2s;" required>
                    </div>
                </div>
                <div style="flex: 0 0 auto; display: flex; align-items: flex-end;">
                    <button type="submit" style="height: 44px; padding: 0 24px; border-radius: 10px; background: #fab802; color: #0f172a; font-weight: 700; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background 0.15s; box-shadow: 0 2px 8px rgba(250, 184, 2, 0.25);">
                        <i class="fa fa-search"></i> Track Status
                    </button>
                </div>
            </form>

            <?php if (!empty($search_error)): ?>
                <div style="margin-top: 16px; padding: 12px 16px; border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 13.5px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-exclamation-circle" style="color: #ef4444; font-size: 16px;"></i>
                    <span><?= htmlspecialchars($search_error) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($matched_order): ?>
            <?php
            $ship_status = strtolower($matched_order['shipping_status'] ?? 'pending');
            $pay_status = strtolower($matched_order['payment_status'] ?? 'pending');
            
            // Stepper progress index (0 to 4)
            $step_index = 1;
            if ($ship_status === 'shipped') {
                $step_index = 2;
            } elseif ($ship_status === 'out_for_delivery' || $ship_status === 'out for delivery') {
                $step_index = 3;
            } elseif ($ship_status === 'delivered') {
                $step_index = 4;
            } elseif ($ship_status === 'cancelled') {
                $step_index = -1;
            }

            $orderNoDisplay = !empty($matched_order['txnid']) && str_starts_with($matched_order['txnid'], 'ORD-') ? $matched_order['txnid'] : ($matched_order['payment_id'] ?? '');
            ?>

            <!-- Result Card -->
            <div style="background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 20px -2px rgba(0,0,0,0.05); margin-bottom: 25px;">
                
                <!-- Order Summary Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Tracking Order</div>
                        <div style="font-size: 20px; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($orderNoDisplay) ?></div>
                        <div style="font-size: 12.5px; color: #64748b; margin-top: 2px;">Placed on <?= date('d M Y, h:i A', strtotime($matched_order['payment_date'] ?? 'now')) ?></div>
                    </div>
                    <div style="text-align: right;">
                        <span style="display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; background: <?= $step_index === 4 ? '#dcfce7; color: #15803d;' : ($step_index === -1 ? '#fee2e2; color: #991b1b;' : '#fef3c7; color: #b45309;') ?>">
                            <?= htmlspecialchars(ucfirst($ship_status)) ?>
                        </span>
                        <div style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 4px;">৳ <?= number_format((float)$matched_order['paid_amount'], 2) ?></div>
                    </div>
                </div>

                <!-- Visual Stepper Timeline -->
                <?php if ($step_index >= 0): ?>
                <div style="margin: 25px 0 35px 0;">
                    <div style="display: flex; justify-content: space-between; position: relative;">
                        <!-- Background progress bar line -->
                        <div style="position: absolute; top: 18px; left: 30px; right: 30px; height: 4px; background: #e2e8f0; z-index: 1;">
                            <div style="height: 100%; width: <?= ($step_index / 4) * 100 ?>%; background: #10b981; transition: width 0.4s ease;"></div>
                        </div>

                        <!-- Step 0: Placed -->
                        <div style="position: relative; z-index: 2; text-align: center; flex: 1;">
                            <div style="width: 38px; height: 38px; margin: 0 auto 8px auto; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; box-shadow: 0 0 0 4px #dcfce7;">
                                <i class="fa fa-check"></i>
                            </div>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a;">Placed</div>
                        </div>

                        <!-- Step 1: Processing -->
                        <div style="position: relative; z-index: 2; text-align: center; flex: 1;">
                            <div style="width: 38px; height: 38px; margin: 0 auto 8px auto; border-radius: 50%; background: <?= $step_index >= 1 ? '#10b981' : '#f1f5f9' ?>; color: <?= $step_index >= 1 ? '#fff' : '#94a3b8' ?>; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; <?= $step_index === 1 ? 'box-shadow: 0 0 0 4px #fef08a; background: #fab802; color: #0f172a;' : ($step_index > 1 ? 'box-shadow: 0 0 0 4px #dcfce7;' : '') ?>">
                                <i class="fa fa-box"></i>
                            </div>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a;">Packed</div>
                        </div>

                        <!-- Step 2: Shipped -->
                        <div style="position: relative; z-index: 2; text-align: center; flex: 1;">
                            <div style="width: 38px; height: 38px; margin: 0 auto 8px auto; border-radius: 50%; background: <?= $step_index >= 2 ? '#10b981' : '#f1f5f9' ?>; color: <?= $step_index >= 2 ? '#fff' : '#94a3b8' ?>; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; <?= $step_index === 2 ? 'box-shadow: 0 0 0 4px #fef08a; background: #fab802; color: #0f172a;' : ($step_index > 2 ? 'box-shadow: 0 0 0 4px #dcfce7;' : '') ?>">
                                <i class="fa fa-truck"></i>
                            </div>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a;">Shipped</div>
                        </div>

                        <!-- Step 3: Out for Delivery -->
                        <div style="position: relative; z-index: 2; text-align: center; flex: 1;">
                            <div style="width: 38px; height: 38px; margin: 0 auto 8px auto; border-radius: 50%; background: <?= $step_index >= 3 ? '#10b981' : '#f1f5f9' ?>; color: <?= $step_index >= 3 ? '#fff' : '#94a3b8' ?>; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; <?= $step_index === 3 ? 'box-shadow: 0 0 0 4px #fef08a; background: #fab802; color: #0f172a;' : ($step_index > 3 ? 'box-shadow: 0 0 0 4px #dcfce7;' : '') ?>">
                                <i class="fa fa-motorcycle"></i>
                            </div>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a;">On The Way</div>
                        </div>

                        <!-- Step 4: Delivered -->
                        <div style="position: relative; z-index: 2; text-align: center; flex: 1;">
                            <div style="width: 38px; height: 38px; margin: 0 auto 8px auto; border-radius: 50%; background: <?= $step_index >= 4 ? '#10b981' : '#f1f5f9' ?>; color: <?= $step_index >= 4 ? '#fff' : '#94a3b8' ?>; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; <?= $step_index === 4 ? 'box-shadow: 0 0 0 4px #dcfce7;' : '' ?>">
                                <i class="fa fa-home"></i>
                            </div>
                            <div style="font-size: 12px; font-weight: 700; color: #0f172a;">Delivered</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Order Items List -->
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0;">Items in this Shipment (<?= count($order_items) ?>)</h3>
                    <div style="border: 1px solid #f1f5f9; border-radius: 12px; overflow: hidden;">
                        <?php foreach ($order_items as $item): ?>
                        <div style="display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background: #fff;">
                            <?php $thumb = !empty($item['p_featured_photo']) ? 'assets/uploads/' . $item['p_featured_photo'] : 'assets/images/no-image.png'; ?>
                            <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" style="width: 52px; height: 52px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="flex: 1;">
                                <div style="font-size: 13.5px; font-weight: 600; color: #0f172a; line-height: 1.3;"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                                    <?php if (!empty($item['size'])): ?>Size: <strong><?= htmlspecialchars($item['size']) ?></strong> &bull; <?php endif; ?>
                                    <?php if (!empty($item['color'])): ?>Color: <strong><?= htmlspecialchars($item['color']) ?></strong> &bull; <?php endif; ?>
                                    Qty: <strong><?= (int)$item['quantity'] ?></strong>
                                </div>
                            </div>
                            <div style="font-size: 14px; font-weight: 700; color: #0f172a;">
                                ৳ <?= number_format((float)$item['unit_price'] * (int)$item['quantity'], 2) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Shipping & Action Details Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid #f1f5f9;">
                        <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Delivery Address</div>
                        <div style="font-size: 13px; font-weight: 600; color: #0f172a;"><?= htmlspecialchars($matched_order['shipping_name'] ?: $matched_order['customer_name']) ?></div>
                        <div style="font-size: 12.5px; color: #475569; margin-top: 2px; line-height: 1.4;">
                            <?= htmlspecialchars($matched_order['shipping_street'] ?: $matched_order['shipping_address']) ?><br>
                            <?= htmlspecialchars($matched_order['shipping_city'] . ', ' . $matched_order['shipping_state']) ?><br>
                            Phone: <?= htmlspecialchars($matched_order['shipping_phone']) ?>
                        </div>
                    </div>

                    <div style="background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid #f1f5f9;">
                        <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Payment Information</div>
                        <div style="font-size: 13px; font-weight: 600; color: #0f172a;"><?= htmlspecialchars($matched_order['payment_method']) ?></div>
                        <div style="font-size: 12.5px; color: #475569; margin-top: 2px; line-height: 1.4;">
                            Payment Status: <strong><?= htmlspecialchars(ucfirst($pay_status)) ?></strong><br>
                            Amount to Pay: <strong>৳ <?= number_format((float)$matched_order['paid_amount'], 2) ?></strong>
                        </div>
                        <div style="margin-top: 12px;">
                            <a href="invoice.php?payment_id=<?= urlencode($matched_order['payment_id']) ?>&print=1" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #4338ca; text-decoration: none;">
                                <i class="fa fa-print"></i> Download & Print Official Invoice
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Guest Set Password CTA -->
                <?php if (str_ends_with($matched_order['customer_acc_email'] ?? '', '@storefront.local')): ?>
                <div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); border-radius: 14px; padding: 18px 20px; color: #fff; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <div>
                            <div style="font-size: 11px; font-weight: 700; color: #fab802; text-transform: uppercase;">Guest Account</div>
                            <div style="font-size: 15px; font-weight: 700; margin: 2px 0;">Want to log in and see all orders in one dashboard?</div>
                            <div style="font-size: 12.5px; color: #cbd5e1;">Set a password for phone <strong><?= htmlspecialchars($matched_order['customer_acc_phone']) ?></strong></div>
                        </div>
                        <form action="set_guest_password.php" method="POST" style="display: flex; gap: 8px;">
                            <input type="hidden" name="customer_id" value="<?= (int)$matched_order['customer_id'] ?>">
                            <input type="hidden" name="redirect_url" value="customer-order.php">
                            <input type="password" name="new_password" placeholder="Create password (6+ chars)" minlength="6" required style="height: 38px; padding: 0 12px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #fff; font-size: 13px; width: 200px;">
                            <button type="submit" style="height: 38px; padding: 0 16px; border-radius: 8px; background: #fab802; color: #0f172a; font-weight: 700; font-size: 13px; border: none; cursor: pointer;">
                                Save &amp; Log In
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
