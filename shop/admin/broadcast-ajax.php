<?php
/**
 * Admin Broadcast Notification AJAX Handler
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/notifications.php';

// Verify admin session
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized admin access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$title = trim((string)($input['title'] ?? ''));
$body = trim((string)($input['body'] ?? ''));
$type = trim((string)($input['type'] ?? 'broadcast'));
$actionUrl = trim((string)($input['action_url'] ?? ''));
$targetAudience = trim((string)($input['target_audience'] ?? 'all'));
$targetCustomerId = null;

if (empty($title) || empty($body)) {
    echo json_encode(['success' => false, 'error' => 'Title and Message body are required.']);
    exit;
}

$merchantId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';

// If target audience is a specific customer email or ID
if ($targetAudience === 'specific') {
    $custEmailOrId = trim((string)($input['customer_target'] ?? ''));
    if (!empty($custEmailOrId)) {
        if (filter_var($custEmailOrId, FILTER_VALIDATE_EMAIL)) {
            $cStmt = $pdo->prepare("SELECT cust_id FROM tbl_customer WHERE cust_email = ?");
            $cStmt->execute([$custEmailOrId]);
            $targetCustomerId = $cStmt->fetchColumn() ?: null;
        } else {
            $targetCustomerId = (int)$custEmailOrId;
        }
    }
}

try {
    $result = broadcastPushNotification(
        $pdo,
        $merchantId,
        $title,
        $body,
        $actionUrl,
        $type,
        $targetCustomerId
    );

    echo json_encode([
        'success' => true,
        'message' => 'Notification broadcast sent successfully!',
        'details' => $result
    ]);
} catch (Throwable $e) {
    error_log("Broadcast AJAX error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to broadcast: ' . $e->getMessage()]);
}
