<?php
/**
 * AJAX Endpoint: Notification Actions (mark read, delete, count)
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/notifications.php';

$action = trim((string)($_REQUEST['action'] ?? ''));
$merchantId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';
$customerId = !empty($_SESSION['customer']['cust_id']) ? (int)$_SESSION['customer']['cust_id'] : null;

switch ($action) {
    case 'mark_all_read':
        $res = markNotificationsAsRead($pdo, $merchantId, $customerId, null);
        echo json_encode(['success' => $res, 'unread_count' => 0]);
        break;

    case 'mark_read':
        $id = (int)($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            exit;
        }
        $res = markNotificationsAsRead($pdo, $merchantId, $customerId, $id);
        $count = getUnreadNotificationCount($pdo, $merchantId, $customerId);
        echo json_encode(['success' => $res, 'unread_count' => $count]);
        break;

    case 'delete':
        $id = (int)($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM tbl_notifications WHERE id = ? AND merchant_id = ?");
            $res = $stmt->execute([$id, $merchantId]);
            $count = getUnreadNotificationCount($pdo, $merchantId, $customerId);
            echo json_encode(['success' => $res, 'unread_count' => $count]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_count':
        $count = getUnreadNotificationCount($pdo, $merchantId, $customerId);
        echo json_encode(['success' => true, 'unread_count' => $count]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
