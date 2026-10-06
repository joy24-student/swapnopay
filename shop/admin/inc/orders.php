<?php
function updateStoreOrder(PDO $pdo,string $reference,string $action,?int $customerId=null): void {
    if(!$reference || strlen($reference)>255 || !in_array($action,['paid','shipped','delivered','cancel'],true)) throw new RuntimeException('Choose a valid order action.');
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('SELECT * FROM tbl_payment WHERE payment_id=? FOR UPDATE');$stmt->execute([$reference]);$order=$stmt->fetch();
        if(!$order) throw new RuntimeException('Order not found.');
        if($customerId!==null && (string)$order['customer_id']!==(string)$customerId) throw new RuntimeException('Order not found.');
        if($order['payment_status']==='Cancelled') {
            if($action==='cancel') {$pdo->commit();return;}
            throw new RuntimeException('A cancelled order cannot be updated.');
        }
        $cash=in_array($order['payment_method'],['COD','Cash on Delivery','Cash'],true);
        if($action==='paid') {
            if(!$cash) throw new RuntimeException('Online payments must be verified by the payment provider.');
            $pdo->prepare("UPDATE tbl_payment SET payment_status='Completed' WHERE payment_id=?")->execute([$reference]);
        } elseif($action==='cancel') {
            if($customerId!==null && time()-strtotime($order['payment_date'])>=86400) throw new RuntimeException('The 24-hour cancellation window has expired. Please contact the store.');
            if($order['payment_status']==='Completed' || in_array($order['shipping_status'],['Shipped','Delivered','Completed'],true)) throw new RuntimeException('Paid or dispatched orders require a return or refund review before cancellation.');
            $stmt=$pdo->prepare('SELECT product_id,SUM(quantity) AS quantity FROM tbl_order WHERE payment_id=? GROUP BY product_id ORDER BY product_id');$stmt->execute([$reference]);
            foreach($stmt->fetchAll() as $item) $pdo->prepare('UPDATE tbl_product SET p_qty=p_qty+? WHERE p_id=?')->execute([(int)$item['quantity'],(int)$item['product_id']]);
            $pdo->prepare("UPDATE tbl_payment SET payment_status='Cancelled',shipping_status='Cancelled' WHERE payment_id=?")->execute([$reference]);
        } else {
            if(!$cash && $order['payment_status']!=='Completed') throw new RuntimeException('Verify payment before shipping this order.');
            if(in_array($order['shipping_status'],['Delivered','Completed'],true) && $action==='shipped') throw new RuntimeException('A delivered order cannot return to shipped.');
            $pdo->prepare('UPDATE tbl_payment SET shipping_status=? WHERE payment_id=?')->execute([$action==='shipped' ? 'Shipped' : 'Delivered',$reference]);
        }
        $pdo->commit();

        // -------------------------------------------------------------------------
        // AUTOMATED SMS & EMAIL NOTIFICATION DISPATCH
        // -------------------------------------------------------------------------
        try {
            $settingsStmt = $pdo->query("SELECT * FROM tbl_settings WHERE id=1");
            $settings = $settingsStmt ? ($settingsStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

            $autoSmsEnabled = isset($settings['auto_order_sms_on_off']) ? (int)$settings['auto_order_sms_on_off'] : 1;
            $autoEmailEnabled = isset($settings['auto_order_email_on_off']) ? (int)$settings['auto_order_email_on_off'] : 1;
            $smsFeature = isset($settings['sms_feature_on_off']) ? (int)$settings['sms_feature_on_off'] : 1;

            $custName = !empty($order['customer_name']) ? $order['customer_name'] : 'Customer';
            $custEmail = !empty($order['customer_email']) ? $order['customer_email'] : '';
            $custPhone = !empty($order['customer_phone']) ? $order['customer_phone'] : '';
            $shopName = !empty($settings['meta_title_home']) ? $settings['meta_title_home'] : 'ShopNext';
            $orderTotal = !empty($order['paid_amount']) ? ('BDT ' . number_format((float)$order['paid_amount'])) : '';

            $statusText = ucfirst($action);
            if ($action === 'paid') $statusText = 'Confirmed / Paid';
            elseif ($action === 'shipped') $statusText = 'Shipped';
            elseif ($action === 'delivered') $statusText = 'Delivered';
            elseif ($action === 'cancel') $statusText = 'Cancelled';

            require_once __DIR__ . '/functions.php';

            // Send Automated SMS
            if ($autoSmsEnabled && $smsFeature && !empty($custPhone)) {
                $smsTpl = '';
                if ($action === 'paid') $smsTpl = $settings['sms_order_placed_template'] ?? '';
                elseif ($action === 'shipped') $smsTpl = $settings['sms_order_shipped_template'] ?? '';
                elseif ($action === 'delivered') $smsTpl = $settings['sms_order_completed_template'] ?? '';

                if (empty($smsTpl)) {
                    $smsTpl = "Dear {customer_name}, your order #{order_id} status is now {status}. Total: {order_total}. Thank you, {shop_name}.";
                }

                $smsMsg = str_replace(
                    ['{customer_name}', '{order_id}', '{order_total}', '{shop_name}', '{status}'],
                    [$custName, $reference, $orderTotal, $shopName, $statusText],
                    $smsTpl
                );

                if (function_exists('sendSMS')) {
                    sendSMS($custPhone, $smsMsg);
                }
            }

            // Send Automated Email
            if ($autoEmailEnabled && !empty($custEmail)) {
                $emailSubject = "Order #{$reference} Status Updated: {$statusText} - {$shopName}";
                $emailBody = "
                    <div style='font-family: Arial, sans-serif; padding: 24px; color: #1e293b; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;'>
                        <h2 style='color: #0f172a; margin-top: 0;'>Order Status Update Notification</h2>
                        <p>Dear <strong>" . htmlspecialchars($custName) . "</strong>,</p>
                        <p>The status of your order <strong>#" . htmlspecialchars($reference) . "</strong> has been updated to: <span style='color: #0284c7; font-weight: bold; font-size: 15px;'>" . htmlspecialchars($statusText) . "</span>.</p>
                        <table style='width: 100%; max-width: 500px; border-collapse: collapse; margin: 18px 0; background: #ffffff; border-radius: 8px; border: 1px solid #cbd5e1;'>
                            <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 14px; color: #64748b;'>Order Reference:</td><td style='padding: 10px 14px; font-weight: bold;'>" . htmlspecialchars($reference) . "</td></tr>
                            <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 14px; color: #64748b;'>Order Total:</td><td style='padding: 10px 14px; font-weight: bold; color: #059669;'>" . htmlspecialchars($orderTotal) . "</td></tr>
                            <tr><td style='padding: 10px 14px; color: #64748b;'>New Status:</td><td style='padding: 10px 14px; font-weight: bold; color: #0284c7;'>" . htmlspecialchars($statusText) . "</td></tr>
                        </table>
                        <p style='margin-top: 20px;'>Thank you for choosing <strong>" . htmlspecialchars($shopName) . "</strong>!</p>
                    </div>
                ";

                if (function_exists('send_email')) {
                    send_email($custEmail, $custName, $emailSubject, $emailBody);
                }
            }

            // Automated In-App & Push Notification
            try {
                require_once __DIR__ . '/notifications.php';
                $merchantId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';
                $custId = !empty($order['customer_id']) ? (int)$order['customer_id'] : null;
                $notifTitle = "Order #{$reference} Updated";
                $notifBody = "Your order #{$reference} is now {$statusText}. Total: {$orderTotal}. Tap to track.";
                $actionUrl = defined('BASE_URL') ? BASE_URL . 'customer-order.php' : 'customer-order.php';
                broadcastPushNotification($pdo, $merchantId, $notifTitle, $notifBody, $actionUrl, 'order', $custId);
            } catch (Throwable $notifErr) {
                error_log("Order in-app/push error: " . $notifErr->getMessage());
            }
        } catch (Throwable $e) {
            error_log("Order status update notification error: " . $e->getMessage());
        }

    } catch(Throwable $error) {if($pdo->inTransaction()) $pdo->rollBack();throw $error;}
}

function setStoreOrderStatus(PDO $pdo, string $reference, string $shippingStatus, string $paymentStatus, bool $notifyCustomer = true, string $adminNote = ''): void {
    if (!$reference || strlen($reference) > 255) {
        throw new RuntimeException('Choose a valid order reference.');
    }
    $allowedShipping = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    $allowedPayment = ['Pending', 'Completed', 'Cancelled'];

    if (!in_array($shippingStatus, $allowedShipping, true)) {
        throw new RuntimeException('Invalid shipping / fulfillment status: ' . htmlspecialchars($shippingStatus));
    }
    if (!in_array($paymentStatus, $allowedPayment, true)) {
        throw new RuntimeException('Invalid payment status: ' . htmlspecialchars($paymentStatus));
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM tbl_payment WHERE payment_id=? FOR UPDATE');
        $stmt->execute([$reference]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $prevPayment = $order['payment_status'] ?? 'Pending';
        $prevShipping = $order['shipping_status'] ?? 'Pending';

        $isNowCancelled = ($shippingStatus === 'Cancelled' || $paymentStatus === 'Cancelled');
        $wasCancelled = ($prevPayment === 'Cancelled' || $prevShipping === 'Cancelled');

        if ($isNowCancelled && !$wasCancelled) {
            // Restore inventory
            $itemStmt = $pdo->prepare('SELECT product_id, SUM(quantity) AS quantity FROM tbl_order WHERE payment_id=? GROUP BY product_id ORDER BY product_id');
            $itemStmt->execute([$reference]);
            foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $pdo->prepare('UPDATE tbl_product SET p_qty = p_qty + ? WHERE p_id = ?')->execute([(int)$item['quantity'], (int)$item['product_id']]);
            }
        } elseif (!$isNowCancelled && $wasCancelled) {
            // Deduct inventory when re-activating
            $itemStmt = $pdo->prepare('SELECT product_id, SUM(quantity) AS quantity FROM tbl_order WHERE payment_id=? GROUP BY product_id ORDER BY product_id');
            $itemStmt->execute([$reference]);
            foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $pdo->prepare('UPDATE tbl_product SET p_qty = GREATEST(0, p_qty - ?) WHERE p_id = ?')->execute([(int)$item['quantity'], (int)$item['product_id']]);
            }
        }

        if (!empty($adminNote)) {
            $existingNote = (string)($order['payment_note'] ?? '');
            $newNote = trim($existingNote . "\n[" . date('Y-m-d H:i') . "] " . $adminNote);
            $pdo->prepare('UPDATE tbl_payment SET shipping_status=?, payment_status=?, payment_note=? WHERE payment_id=?')
                ->execute([$shippingStatus, $paymentStatus, $newNote, $reference]);
        } else {
            $pdo->prepare('UPDATE tbl_payment SET shipping_status=?, payment_status=? WHERE payment_id=?')
                ->execute([$shippingStatus, $paymentStatus, $reference]);
        }

        $pdo->commit();

        if ($notifyCustomer && ($prevShipping !== $shippingStatus || $prevPayment !== $paymentStatus)) {
            try {
                $settingsStmt = $pdo->query("SELECT * FROM tbl_settings WHERE id=1");
                $settings = $settingsStmt ? ($settingsStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

                $autoSmsEnabled = isset($settings['auto_order_sms_on_off']) ? (int)$settings['auto_order_sms_on_off'] : 1;
                $autoEmailEnabled = isset($settings['auto_order_email_on_off']) ? (int)$settings['auto_order_email_on_off'] : 1;
                $smsFeature = isset($settings['sms_feature_on_off']) ? (int)$settings['sms_feature_on_off'] : 1;

                $custName = !empty($order['customer_name']) ? $order['customer_name'] : 'Customer';
                $custEmail = !empty($order['customer_email']) ? $order['customer_email'] : '';
                $custPhone = !empty($order['customer_phone']) ? $order['customer_phone'] : '';
                $shopName = !empty($settings['meta_title_home']) ? $settings['meta_title_home'] : 'ShopNext';
                $orderTotal = !empty($order['paid_amount']) ? ('BDT ' . number_format((float)$order['paid_amount'], 2)) : '';

                require_once __DIR__ . '/functions.php';

                if ($autoSmsEnabled && $smsFeature && !empty($custPhone)) {
                    $smsMsg = "Dear {$custName}, your order #{$reference} status updated: Fulfillment: {$shippingStatus}, Payment: {$paymentStatus}. Total: {$orderTotal}. Thank you, {$shopName}.";
                    if (function_exists('sendSMS')) {
                        sendSMS($custPhone, $smsMsg);
                    }
                }

                if ($autoEmailEnabled && !empty($custEmail)) {
                    $emailSubject = "Order #{$reference} Status Updated: {$shippingStatus} - {$shopName}";
                    $emailBody = "
                        <div style='font-family: Arial, sans-serif; padding: 24px; color: #1e293b; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;'>
                            <h2 style='color: #0f172a; margin-top: 0;'>Order Status Update Notification</h2>
                            <p>Dear <strong>" . htmlspecialchars($custName) . "</strong>,</p>
                            <p>The status of your order <strong>#" . htmlspecialchars($reference) . "</strong> has been updated:</p>
                            <table style='width: 100%; max-width: 500px; border-collapse: collapse; margin: 18px 0; background: #ffffff; border-radius: 8px; border: 1px solid #cbd5e1;'>
                                <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 14px; color: #64748b;'>Order Reference:</td><td style='padding: 10px 14px; font-weight: bold;'>" . htmlspecialchars($reference) . "</td></tr>
                                <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 14px; color: #64748b;'>Fulfillment Status:</td><td style='padding: 10px 14px; font-weight: bold; color: #0284c7;'>" . htmlspecialchars($shippingStatus) . "</td></tr>
                                <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 10px 14px; color: #64748b;'>Payment Status:</td><td style='padding: 10px 14px; font-weight: bold; color: #059669;'>" . htmlspecialchars($paymentStatus) . "</td></tr>
                                <tr><td style='padding: 10px 14px; color: #64748b;'>Order Total:</td><td style='padding: 10px 14px; font-weight: bold;'>" . htmlspecialchars($orderTotal) . "</td></tr>
                            </table>
                            <p style='margin-top: 20px;'>Thank you for choosing <strong>" . htmlspecialchars($shopName) . "</strong>!</p>
                        </div>";
                    if (function_exists('send_email')) {
                        send_email($custEmail, $custName, $emailSubject, $emailBody);
                    }
                }

                // Automated In-App & Push Notification
                try {
                    require_once __DIR__ . '/notifications.php';
                    $merchantId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';
                    $custId = !empty($order['customer_id']) ? (int)$order['customer_id'] : null;
                    $notifTitle = "Order #{$reference} Status: {$shippingStatus}";
                    $notifBody = "Fulfillment is {$shippingStatus}, payment is {$paymentStatus}. Total: {$orderTotal}. Tap to track.";
                    $actionUrl = defined('BASE_URL') ? BASE_URL . 'customer-order.php' : 'customer-order.php';
                    broadcastPushNotification($pdo, $merchantId, $notifTitle, $notifBody, $actionUrl, 'order', $custId);
                } catch (Throwable $notifErr) {
                    error_log("Order in-app/push error: " . $notifErr->getMessage());
                }
            } catch (Throwable $notifErr) {
                error_log("Order notification error: " . $notifErr->getMessage());
            }
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
