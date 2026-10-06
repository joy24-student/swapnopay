<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/orders.php';

try {
    $ref = trim((string)($_POST['id'] ?? $_GET['id'] ?? ''));
    $task = trim((string)($_POST['task'] ?? $_GET['task'] ?? 'shipped'));
    $action = strtolower($task);

    if (in_array($action, ['processing', 'pending'], true)) {
        $stmt = $pdo->prepare("SELECT payment_status FROM tbl_payment WHERE payment_id=?");
        $stmt->execute([$ref]);
        $currPay = $stmt->fetchColumn() ?: 'Pending';
        setStoreOrderStatus($pdo, $ref, ucfirst($action), $currPay, true);
    } else {
        $validAction = ($action === 'delivered') ? 'delivered' : 'shipped';
        updateStoreOrder($pdo, $ref, $validAction);
    }
    $_SESSION['order_notice'] = 'Shipping status updated successfully.';
} catch(Throwable $error) {
    $_SESSION['order_error'] = $error instanceof PDOException ? 'The order could not be updated. Please try again.' : $error->getMessage();
}

$redirect = (string)($_POST['redirect'] ?? $_GET['redirect'] ?? 'order.php');
if (!preg_match('/^([a-zA-Z0-9_\-\.\?=&]+)$/', $redirect) || str_contains($redirect, '://')) {
    $redirect = 'order.php';
}

header('Location: ' . $redirect);
exit;
