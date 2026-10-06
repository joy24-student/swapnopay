<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/orders.php';
try {
    updateStoreOrder($pdo,(string)($_POST['id'] ?? $_GET['id'] ?? ''),'cancel');
    $_SESSION['order_notice']='Order updated successfully.';
} catch(Throwable $error) {
    $_SESSION['order_error']=$error instanceof PDOException ? 'The order could not be updated. Please try again.' : $error->getMessage();
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['ajax']) || isset($_GET['ajax']);

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    if (isset($_SESSION['order_error'])) {
        $err = $_SESSION['order_error']; unset($_SESSION['order_error']);
        echo json_encode(['success' => false, 'message' => $err]);
    } else {
        $msg = $_SESSION['order_notice'] ?? 'Order cancelled successfully.'; unset($_SESSION['order_notice']);
        echo json_encode(['success' => true, 'message' => $msg]);
    }
    exit;
}

header('Location: order.php');exit;
