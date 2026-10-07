<?php
require_once __DIR__ . '/inc/guard.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$custId = (int)($_POST['cust_id'] ?? 0);
if ($custId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID.']);
    exit;
}

$name = trim((string)($_POST['cust_name'] ?? ''));
$email = trim((string)($_POST['cust_email'] ?? ''));
$phone = trim((string)($_POST['cust_phone'] ?? ''));
$city = trim((string)($_POST['cust_city'] ?? ''));
$state = trim((string)($_POST['cust_state'] ?? ''));
$status = isset($_POST['cust_status']) ? (int)$_POST['cust_status'] : 1;

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Customer name cannot be empty.']);
    exit;
}

try {
    // Check if customer exists
    $chkStmt = $pdo->prepare("SELECT cust_id FROM tbl_customer WHERE cust_id = ?");
    $chkStmt->execute([$custId]);
    if ($chkStmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Customer record not found.']);
        exit;
    }

    $updateStmt = $pdo->prepare("
        UPDATE tbl_customer 
        SET cust_name = ?, cust_email = ?, cust_phone = ?, cust_city = ?, cust_state = ?, cust_status = ?
        WHERE cust_id = ?
    ");
    $updateStmt->execute([$name, $email, $phone, $city, $state, $status, $custId]);

    echo json_encode([
        'success' => true,
        'message' => 'Customer profile updated successfully.',
        'data' => [
            'cust_id' => $custId,
            'cust_name' => $name,
            'cust_email' => $email,
            'cust_phone' => $phone,
            'cust_city' => $city,
            'cust_state' => $state,
            'cust_status' => $status
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

