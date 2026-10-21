<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';

$customerId = (int)($_POST['customer_id'] ?? 0);
$newPassword = trim((string)($_POST['new_password'] ?? ''));
$redirectUrl = trim((string)($_POST['redirect_url'] ?? 'customer-order.php'));

// Basic security checks
if ($customerId <= 0) {
    $customerId = (int)($_SESSION['customer']['cust_id'] ?? 0);
}

if ($customerId <= 0 || strlen($newPassword) < 6) {
    $_SESSION['flash_error'] = 'Password must be at least 6 characters long.';
    header('Location: ' . (!empty($redirectUrl) ? $redirectUrl : 'index.php'));
    exit;
}

try {
    // Verify customer exists
    $stmt = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $_SESSION['flash_error'] = 'Customer record not found.';
        header('Location: ' . (!empty($redirectUrl) ? $redirectUrl : 'index.php'));
        exit;
    }

    // Hash and update password
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $upStmt = $pdo->prepare("UPDATE tbl_customer SET cust_password = ?, cust_status = 1 WHERE cust_id = ?");
    $upStmt->execute([$passwordHash, $customerId]);

    // Keep logged in
    unset($customer['cust_password']);
    $_SESSION['customer'] = $customer;

    $_SESSION['flash_success'] = '🎉 Password set successfully! You can now log in anytime using your phone number: ' . htmlspecialchars($customer['cust_phone'] ?: $customer['cust_email']);

    // Check if AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'success',
            'message' => 'Password created successfully! You can now log in anytime using your phone number.'
        ]);
        exit;
    }

    header('Location: ' . (!empty($redirectUrl) ? $redirectUrl : 'customer-order.php'));
    exit;
} catch (Throwable $e) {
    error_log('Error setting guest password: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not set password. Please try again.';
    header('Location: ' . (!empty($redirectUrl) ? $redirectUrl : 'index.php'));
    exit;
}
