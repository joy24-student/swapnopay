<?php
/**
 * AJAX Endpoint: Save / Update FCM Token
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$fcmToken = trim((string)($input['token'] ?? ''));
$deviceType = trim((string)($input['device_type'] ?? 'web'));
if (empty($deviceType)) $deviceType = 'web';

if (empty($fcmToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token is required']);
    exit;
}

$merchantId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';
$customerId = !empty($_SESSION['customer']['cust_id']) ? (int)$_SESSION['customer']['cust_id'] : null;

try {
    // Upsert token in PostgreSQL
    $stmt = $pdo->prepare("
        INSERT INTO tbl_fcm_tokens (merchant_id, customer_id, fcm_token, device_type, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON CONFLICT (fcm_token) DO UPDATE 
        SET customer_id = COALESCE(EXCLUDED.customer_id, tbl_fcm_tokens.customer_id),
            device_type = EXCLUDED.device_type,
            updated_at = NOW()
    ");
    $stmt->execute([$merchantId, $customerId, $fcmToken, $deviceType]);

    echo json_encode([
        'success' => true,
        'message' => 'FCM token registered successfully',
        'customer_id' => $customerId
    ]);
} catch (Throwable $e) {
    error_log("save-fcm-token error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Could not save token']);
}
