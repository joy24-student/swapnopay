<?php
if (!isset($order) || empty($order) || !isset($pdo)) {
    return;
}

$isGuestAccount = false;
$guestPhone = $order['shipping_phone'] ?? ($order['billing_phone'] ?? '');
$targetCustId = (int)($order['customer_id'] ?? 0);

if ($targetCustId > 0) {
    try {
        $st_chk = $pdo->prepare("SELECT cust_id, cust_name, cust_email, cust_phone, cust_password FROM tbl_customer WHERE cust_id = ? LIMIT 1");
        $st_chk->execute([$targetCustId]);
        $cRow = $st_chk->fetch(PDO::FETCH_ASSOC);
        if ($cRow) {
            $guestPhone = !empty($cRow['cust_phone']) ? $cRow['cust_phone'] : $guestPhone;
            if (str_ends_with($cRow['cust_email'], '@storefront.local')) {
                $isGuestAccount = true;
            }
        }
    } catch (Throwable $_) {}
}

if (!empty($_SESSION['flash_success'])) {
    echo '<div style="max-width: 1040px; margin: 0 auto 16px auto; padding: 14px 18px; border-radius: 12px; background: #ecfdf5; border: 1.5px solid #6ee7b7; color: #065f46; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;">';
    echo '<i class="fa fa-check-circle" style="font-size: 18px; color: #10b981;"></i>';
    echo '<span>' . htmlspecialchars($_SESSION['flash_success']) . '</span>';
    echo '</div>';
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    echo '<div style="max-width: 1040px; margin: 0 auto 16px auto; padding: 14px 18px; border-radius: 12px; background: #fef2f2; border: 1.5px solid #fca5a5; color: #991b1b; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;">';
    echo '<i class="fa fa-exclamation-triangle" style="font-size: 18px; color: #ef4444;"></i>';
    echo '<span>' . htmlspecialchars($_SESSION['flash_error']) . '</span>';
    echo '</div>';
    unset($_SESSION['flash_error']);
}
?>

<?php if ($isGuestAccount && !empty($guestPhone)): ?>
<div style="max-width: 1040px; margin: 0 auto 18px auto; background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%); border-radius: 14px; padding: 20px 24px; color: #fff; box-shadow: 0 10px 25px -5px rgba(49, 46, 129, 0.25);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="max-width: 580px;">
            <div style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <span style="background: rgba(250, 184, 2, 0.2); border: 1px solid rgba(250, 184, 2, 0.4); color: #fab802; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Instant Account Setup</span>
                <span style="color: #cbd5e1; font-size: 12.5px;">Log in anytime with Phone: <strong style="color: #fff;"><?= htmlspecialchars($guestPhone) ?></strong></span>
            </div>
            <h4 style="margin: 0 0 4px 0; font-size: 17px; font-weight: 700; color: #fff; letter-spacing: -0.2px;">Set a password to manage this order &amp; future purchases</h4>
            <p style="margin: 0; font-size: 13px; color: #c7d2fe; line-height: 1.45;">Track live courier delivery, print invoices, and view order history anytime using your mobile number and password.</p>
        </div>
        <form action="set_guest_password.php" method="POST" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="customer_id" value="<?= $targetCustId ?>">
            <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'customer-order.php') ?>">
            <div style="position: relative;">
                <input type="password" name="new_password" placeholder="Create password (6+ chars)" minlength="6" required style="height: 42px; padding: 0 14px; border-radius: 10px; border: 1.5px solid #6366f1; background: #0f172a; color: #fff; font-size: 13.5px; width: 220px; outline: none;">
            </div>
            <button type="submit" style="background: #fab802; color: #0f172a; font-weight: 700; font-size: 13.5px; height: 42px; padding: 0 20px; border-radius: 10px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 14px rgba(250, 184, 2, 0.3); transition: transform 0.15s;">
                <i class="fa fa-lock"></i> Save Password
            </button>
        </form>
    </div>
</div>
<?php endif; ?>
