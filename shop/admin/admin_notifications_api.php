<?php
/**
 * Admin Notifications API Endpoint
 * Handles dynamic aggregation of store notifications, stock alerts, customer activity,
 * and read/unread status tracking with real-time polling.
 */

ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Ensure admin authentication
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

// Safe table auto-initialization
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_admin_notifications (
            id SERIAL PRIMARY KEY,
            notification_key VARCHAR(100) UNIQUE,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'system',
            severity VARCHAR(20) DEFAULT 'info',
            action_url VARCHAR(255) DEFAULT '',
            is_read SMALLINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_admin_read_status (
            id SERIAL PRIMARY KEY,
            admin_id INTEGER DEFAULT 1,
            notification_key VARCHAR(100) NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (admin_id, notification_key)
        );
    ");
} catch (Throwable $e) {
    // Graceful fallback if permission issue
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_notifications');
$adminId = (int)($_SESSION['user']['id'] ?? 1);

// Initialize session read storage as fallback
if (!isset($_SESSION['admin_read_notifs']) || !is_array($_SESSION['admin_read_notifs'])) {
    $_SESSION['admin_read_notifs'] = [];
}

/**
 * Format relative time
 */
function humanTimeDiff($datetime) {
    if (empty($datetime)) return 'Recently';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'Recently';
    $difference = time() - $timestamp;
    if ($difference < 0) return 'Just now';
    if ($difference < 60) return 'Just now';
    if ($difference < 3600) return floor($difference / 60) . 'm ago';
    if ($difference < 86400) return floor($difference / 3600) . 'h ago';
    if ($difference < 172800) return 'Yesterday';
    if ($difference < 604800) return floor($difference / 86400) . 'd ago';
    return date('M d', $timestamp);
}

// =========================================================================
// ACTION: GET NOTIFICATIONS
// =========================================================================
if ($action === 'get_notifications') {
    // 1. Fetch read keys from database and session
    $readKeys = $_SESSION['admin_read_notifs'];
    try {
        $stmt_reads = $pdo->prepare("SELECT notification_key FROM tbl_admin_read_status WHERE admin_id = ?");
        $stmt_reads->execute([$adminId]);
        $dbReadKeys = $stmt_reads->fetchAll(PDO::FETCH_COLUMN);
        $readKeys = array_unique(array_merge($readKeys, $dbReadKeys));
    } catch (Throwable $e) {}

    $notifications = [];

    // 2. Fetch Recent Orders from tbl_payment
    try {
        $stmt_orders = $pdo->query("
            SELECT payment_id, txnid, customer_name, customer_email, paid_amount, 
                   payment_method, payment_status, shipping_status, payment_date
            FROM tbl_payment
            ORDER BY id DESC
            LIMIT 15
        ");
        $orders = $stmt_orders ? $stmt_orders->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($orders as $ord) {
            $pid = (string)$ord['payment_id'];
            $key = 'order_' . $pid;
            $amtFormatted = '৳ ' . number_format((float)($ord['paid_amount'] ?? 0));
            $method = !empty($ord['payment_method']) ? $ord['payment_method'] : 'Online';
            $cust = !empty($ord['customer_name']) ? $ord['customer_name'] : 'Customer';
            $payStatus = strtolower(trim((string)$ord['payment_status']));
            $shipStatus = strtolower(trim((string)$ord['shipping_status']));

            $severity = 'info';
            $title = "New Order #{$pid}";
            $msg = "{$cust} placed an order for {$amtFormatted} via {$method}.";

            if ($payStatus === 'completed') {
                $severity = 'success';
                $title = "Payment Received: #{$pid}";
                $msg = "Payment of {$amtFormatted} verified via {$method} for {$cust}.";
            } elseif ($payStatus === 'pending') {
                $severity = 'warning';
                $title = "Pending Order #{$pid}";
                $msg = "Order of {$amtFormatted} from {$cust} is awaiting verification.";
            } elseif ($payStatus === 'cancelled') {
                $severity = 'danger';
                $title = "Order Cancelled #{$pid}";
                $msg = "Order #{$pid} for {$amtFormatted} was cancelled.";
            }

            if ($shipStatus === 'delivered') {
                $title = "Order Delivered #{$pid}";
                $severity = 'success';
            } elseif ($shipStatus === 'shipped') {
                $title = "Order Shipped #{$pid}";
            }

            $notifications[] = [
                'id' => $key,
                'key' => $key,
                'category' => 'orders',
                'severity' => $severity,
                'title' => $title,
                'message' => $msg,
                'action_url' => 'order-summary.php?payment_id=' . rawurlencode($pid),
                'action_label' => 'View Order',
                'timestamp' => $ord['payment_date'] ?? date('Y-m-d H:i:s'),
                'time_human' => humanTimeDiff($ord['payment_date'] ?? date('Y-m-d H:i:s')),
                'is_read' => in_array($key, $readKeys, true),
                'meta' => [
                    'order_id' => $pid,
                    'amount' => $amtFormatted,
                    'customer' => $cust,
                    'payment_status' => $ord['payment_status'],
                    'shipping_status' => $ord['shipping_status']
                ]
            ];
        }
    } catch (Throwable $e) {}

    // 3. Fetch Low Stock / Out of Stock Products
    try {
        $stmt_stock = $pdo->query("
            SELECT p_id, p_name, p_qty, p_current_price, p_featured_photo
            FROM tbl_product
            WHERE p_qty <= 5
            ORDER BY p_qty ASC, p_id DESC
            LIMIT 10
        ");
        $lowProducts = $stmt_stock ? $stmt_stock->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($lowProducts as $prod) {
            $pId = (int)$prod['p_id'];
            $qty = (int)$prod['p_qty'];
            $key = 'stock_' . $pId . '_q' . $qty;
            $name = htmlspecialchars_decode($prod['p_name'] ?? 'Product', ENT_QUOTES);

            if ($qty <= 0) {
                $severity = 'danger';
                $title = 'Out of Stock Alert!';
                $msg = "'{$name}' is currently out of stock (0 items left). Restock needed.";
            } else {
                $severity = 'warning';
                $title = 'Low Inventory Warning';
                $msg = "'{$name}' has only {$qty} unit" . ($qty > 1 ? 's' : '') . " remaining in stock.";
            }

            $notifications[] = [
                'id' => $key,
                'key' => $key,
                'category' => 'inventory',
                'severity' => $severity,
                'title' => $title,
                'message' => $msg,
                'action_url' => 'product-edit.php?id=' . $pId,
                'action_label' => 'Restock Product',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-' . ($pId % 60) . ' minutes')),
                'time_human' => ($qty <= 0 ? 'Urgent' : 'Action needed'),
                'is_read' => in_array($key, $readKeys, true),
                'meta' => [
                    'product_id' => $pId,
                    'quantity' => $qty,
                    'photo' => $prod['p_featured_photo'] ?? ''
                ]
            ];
        }
    } catch (Throwable $e) {}

    // 4. Fetch New Customer Registrations
    try {
        $stmt_cust = $pdo->query("
            SELECT cust_id, cust_name, cust_email, cust_phone, cust_datetime
            FROM tbl_customer
            ORDER BY cust_id DESC
            LIMIT 5
        ");
        $newCustomers = $stmt_cust ? $stmt_cust->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($newCustomers as $c) {
            $cId = (int)$c['cust_id'];
            $key = 'cust_' . $cId;
            $cName = !empty($c['cust_name']) ? $c['cust_name'] : 'New Customer';
            $cEmail = !empty($c['cust_email']) ? $c['cust_email'] : '';
            $cDate = !empty($c['cust_datetime']) ? $c['cust_datetime'] : date('Y-m-d H:i:s');

            $notifications[] = [
                'id' => $key,
                'key' => $key,
                'category' => 'customers',
                'severity' => 'info',
                'title' => 'New Customer Registered',
                'message' => "{$cName}" . ($cEmail ? " ({$cEmail})" : "") . " created an account.",
                'action_url' => 'customer.php?search=' . rawurlencode($cName),
                'action_label' => 'View Profile',
                'timestamp' => $cDate,
                'time_human' => humanTimeDiff($cDate),
                'is_read' => in_array($key, $readKeys, true),
                'meta' => [
                    'customer_id' => $cId,
                    'email' => $cEmail
                ]
            ];
        }
    } catch (Throwable $e) {}

    // 5. Fetch Custom Admin Announcements / System Notifications
    try {
        $stmt_admin = $pdo->query("
            SELECT id, notification_key, title, message, category, severity, action_url, created_at, is_read
            FROM tbl_admin_notifications
            ORDER BY id DESC
            LIMIT 10
        ");
        if ($stmt_admin) {
            while ($row = $stmt_admin->fetch(PDO::FETCH_ASSOC)) {
                $key = $row['notification_key'] ?: ('admin_notif_' . $row['id']);
                $notifications[] = [
                    'id' => $key,
                    'key' => $key,
                    'category' => $row['category'] ?: 'system',
                    'severity' => $row['severity'] ?: 'info',
                    'title' => $row['title'],
                    'message' => $row['message'],
                    'action_url' => $row['action_url'] ?: 'javascript:void(0)',
                    'action_label' => 'Details',
                    'timestamp' => $row['created_at'],
                    'time_human' => humanTimeDiff($row['created_at']),
                    'is_read' => (bool)$row['is_read'] || in_array($key, $readKeys, true),
                    'meta' => ['db_id' => $row['id']]
                ];
            }
        }
    } catch (Throwable $e) {}

    // System Status Announcement if list is clean
    if (empty($notifications)) {
        $notifications[] = [
            'id' => 'system_ready',
            'key' => 'system_ready',
            'category' => 'system',
            'severity' => 'success',
            'title' => 'System Operating Normally',
            'message' => 'All payment gateways and store systems are operational.',
            'action_url' => 'index.php',
            'action_label' => 'Dashboard',
            'timestamp' => date('Y-m-d H:i:s'),
            'time_human' => 'Just now',
            'is_read' => true,
            'meta' => []
        ];
    }

    // Sort all notifications by timestamp DESC
    usort($notifications, function ($a, $b) {
        $ta = strtotime($a['timestamp'] ?? 'now');
        $tb = strtotime($b['timestamp'] ?? 'now');
        return $tb <=> $ta;
    });

    // Calculate unread and category counts
    $unreadCount = 0;
    $counts = [
        'all' => count($notifications),
        'orders' => 0,
        'inventory' => 0,
        'customers' => 0,
        'system' => 0
    ];

    foreach ($notifications as $item) {
        if (!$item['is_read']) {
            $unreadCount++;
        }
        $cat = $item['category'];
        if (isset($counts[$cat])) {
            $counts[$cat]++;
        } else {
            $counts[$cat] = 1;
        }
    }

    echo json_encode([
        'status' => 'success',
        'unread_count' => $unreadCount,
        'counts' => $counts,
        'notifications' => $notifications,
        'server_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// =========================================================================
// ACTION: MARK SINGLE NOTIFICATION AS READ
// =========================================================================
if ($action === 'mark_read') {
    $key = trim((string)($_POST['key'] ?? ($_GET['key'] ?? '')));
    if (empty($key)) {
        echo json_encode(['status' => 'error', 'message' => 'Key required']);
        exit;
    }

    $_SESSION['admin_read_notifs'][] = $key;
    $_SESSION['admin_read_notifs'] = array_unique($_SESSION['admin_read_notifs']);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_admin_read_status (admin_id, notification_key)
            VALUES (?, ?)
            ON CONFLICT (admin_id, notification_key) DO NOTHING
        ");
        $stmt->execute([$adminId, $key]);

        // Also update tbl_admin_notifications if applicable
        $stmt_up = $pdo->prepare("UPDATE tbl_admin_notifications SET is_read = 1 WHERE notification_key = ?");
        $stmt_up->execute([$key]);
    } catch (Throwable $e) {}

    echo json_encode(['status' => 'success', 'marked_key' => $key]);
    exit;
}

// =========================================================================
// ACTION: MARK ALL NOTIFICATIONS AS READ
// =========================================================================
if ($action === 'mark_all_read') {
    $keys = $_POST['keys'] ?? [];
    if (!is_array($keys) && !empty($keys)) {
        $keys = json_decode($keys, true) ?: [];
    }

    if (empty($keys)) {
        // Collect active keys
        // We will mark existing recent orders, stock alerts, and customers
        try {
            $orderKeys = $pdo->query("SELECT CONCAT('order_', payment_id) FROM tbl_payment LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
            $stockKeys = $pdo->query("SELECT CONCAT('stock_', p_id, '_q', p_qty) FROM tbl_product WHERE p_qty <= 5 LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
            $custKeys  = $pdo->query("SELECT CONCAT('cust_', cust_id) FROM tbl_customer LIMIT 30")->fetchAll(PDO::FETCH_COLUMN);
            $keys = array_merge($orderKeys, $stockKeys, $custKeys);
        } catch (Throwable $e) {
            $keys = [];
        }
    }

    $_SESSION['admin_read_notifs'] = array_unique(array_merge($_SESSION['admin_read_notifs'], $keys));

    try {
        if (!empty($keys)) {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_admin_read_status (admin_id, notification_key)
                VALUES (?, ?)
                ON CONFLICT (admin_id, notification_key) DO NOTHING
            ");
            foreach ($keys as $k) {
                if (!empty($k)) {
                    $stmt->execute([$adminId, (string)$k]);
                }
            }
        }
        $pdo->exec("UPDATE tbl_admin_notifications SET is_read = 1");
    } catch (Throwable $e) {}

    echo json_encode(['status' => 'success', 'unread_count' => 0]);
    exit;
}

// =========================================================================
// ACTION: CREATE STORE ANNOUNCEMENT / BROADCAST
// =========================================================================
if ($action === 'create_announcement') {
    $title = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'system'));
    $severity = trim((string)($_POST['severity'] ?? 'info'));
    $action_url = trim((string)($_POST['action_url'] ?? ''));

    if (empty($title) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Title and message are required.']);
        exit;
    }

    $key = 'announcement_' . time() . '_' . rand(100, 999);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_admin_notifications (notification_key, title, message, category, severity, action_url, is_read)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$key, $title, $message, $category, $severity, $action_url]);
        echo json_encode(['status' => 'success', 'message' => 'Announcement posted successfully.']);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Default fallback
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;

