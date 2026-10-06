<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/orders.php';

$reference = trim((string)($_POST['id'] ?? $_GET['id'] ?? ''));

try {
    if (!$reference) {
        throw new RuntimeException('Order reference is missing.');
    }

    if (isset($_POST['shipping_status']) || isset($_POST['payment_status'])) {
        $shippingStatus = trim((string)($_POST['shipping_status'] ?? 'Pending'));
        $paymentStatus = trim((string)($_POST['payment_status'] ?? 'Pending'));
        $notify = !empty($_POST['notify_customer']);
        $note = trim((string)($_POST['admin_note'] ?? ''));

        setStoreOrderStatus($pdo, $reference, $shippingStatus, $paymentStatus, $notify, $note);
        $_SESSION['order_notice'] = "Order #{$reference} status updated (Fulfillment: {$shippingStatus}, Payment: {$paymentStatus}).";
    } elseif (isset($_POST['action']) || isset($_GET['action'])) {
        $action = trim((string)($_POST['action'] ?? $_GET['action'] ?? 'paid'));
        updateStoreOrder($pdo, $reference, $action);
        $_SESSION['order_notice'] = "Order #{$reference} updated successfully.";
    } else {
        updateStoreOrder($pdo, $reference, 'paid');
        $_SESSION['order_notice'] = "Order #{$reference} marked as paid.";
    }
} catch (Throwable $error) {
    $_SESSION['order_error'] = $error instanceof PDOException ? 'The order could not be updated. Please try again.' : $error->getMessage();
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['ajax']) || isset($_GET['ajax']);

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    if (isset($_SESSION['order_error'])) {
        $err = $_SESSION['order_error'];
        unset($_SESSION['order_error']);
        echo json_encode(['success' => false, 'message' => $err]);
    } else {
        $msg = $_SESSION['order_notice'] ?? "Order #{$reference} updated successfully.";
        unset($_SESSION['order_notice']);
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'reference' => $reference,
            'shipping_status' => $shippingStatus ?? null,
            'payment_status' => $paymentStatus ?? null
        ]);
    }
    exit;
}

$redirect = (string)($_POST['redirect'] ?? $_GET['redirect'] ?? 'order.php');
if (!preg_match('/^([a-zA-Z0-9_\-\.\?=&]+)$/', $redirect) || str_contains($redirect, '://')) {
    $redirect = 'order.php';
}

header('Location: ' . $redirect);
exit;

