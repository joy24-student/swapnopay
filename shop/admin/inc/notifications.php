<?php
/**
 * ShopNext & SwapnoPay Push Notification & FCM Engine
 * Handles In-App Notifications, Customer Alerts, and Native Web/App Push via Firebase Cloud Messaging
 */

if (!defined('BASE_URL')) {
    // If called directly or without BASE_URL defined
    @require_once __DIR__ . '/config.php';
}

/**
 * Creates an in-app notification record in tbl_notifications
 */
function createNotification(
    PDO $pdo,
    string $merchantId,
    ?int $customerId,
    string $title,
    string $body,
    string $type = 'general',
    ?int $orderId = null,
    ?string $actionUrl = null,
    ?string $iconUrl = null
): int {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_notifications 
            (merchant_id, customer_id, title, body, type, order_id, is_read, icon_url, action_url, created_at)
            VALUES (?, ?, ?, ?, ?, ?, false, ?, ?, NOW())
            RETURNING id
        ");
        $stmt->execute([
            $merchantId ?: 'local-merchant-001',
            $customerId ?: null,
            trim($title),
            trim($body),
            $type ?: 'general',
            $orderId ?: null,
            $iconUrl ?: null,
            $actionUrl ?: null
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['id'] ?? $pdo->lastInsertId());
    } catch (Throwable $e) {
        error_log("createNotification error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Sends a native Push Notification to a list of device tokens via Firebase Cloud Messaging (FCM)
 */
function sendFCMPushNotification(
    PDO $pdo,
    array $tokens,
    string $title,
    string $body,
    ?string $actionUrl = null,
    ?string $iconUrl = null,
    array $extraData = []
): array {
    $result = [
        'success' => false,
        'sent' => 0,
        'failed' => 0,
        'total' => count($tokens),
        'message' => ''
    ];

    if (empty($tokens)) {
        $result['message'] = 'No device tokens provided';
        return $result;
    }

    // Retrieve FCM server credentials from tbl_settings
    try {
        $settingsStmt = $pdo->query("SELECT * FROM tbl_settings WHERE id=1");
        $settings = $settingsStmt ? ($settingsStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        $settings = [];
    }

    $serverKey = trim((string)($settings['fcm_server_key'] ?? ''));
    if (empty($serverKey)) {
        $serverKey = getenv('FCM_SERVER_KEY') ?: '';
    }

    if (empty($serverKey)) {
        $result['message'] = 'FCM Server Key is not configured in settings. In-app notification was saved.';
        return $result;
    }

    $defaultIcon = defined('BASE_URL') ? BASE_URL . 'assets/uploads/default_logo.png' : '';
    $finalIcon = $iconUrl ?: $defaultIcon;
    $finalUrl = $actionUrl ?: (defined('BASE_URL') ? BASE_URL : '/');

    // Split tokens into batches of 500 for FCM limits
    $batches = array_chunk($tokens, 500);

    foreach ($batches as $batchTokens) {
        $payload = [
            'registration_ids' => array_values($batchTokens),
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => $finalIcon,
                'click_action' => $finalUrl,
                'sound' => 'default',
                'badge' => '1',
                'vibrate' => [200, 100, 200]
            ],
            'data' => array_merge([
                'title' => $title,
                'body' => $body,
                'url' => $finalUrl,
                'click_action' => $finalUrl,
                'icon' => $finalIcon,
                'timestamp' => time()
            ], $extraData),
            'priority' => 'high'
        ];

        $headers = [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("FCM cURL Error: " . $curlErr);
            $result['failed'] += count($batchTokens);
            $result['message'] = $curlErr;
            continue;
        }

        if ($httpCode === 200 && $response) {
            $respData = json_decode($response, true);
            $successCount = (int)($respData['success'] ?? 0);
            $failureCount = (int)($respData['failure'] ?? 0);
            $result['sent'] += $successCount;
            $result['failed'] += $failureCount;
            $result['success'] = ($result['sent'] > 0);

            // Clean up invalid or expired tokens
            if (!empty($respData['results']) && is_array($respData['results'])) {
                foreach ($respData['results'] as $idx => $tokenRes) {
                    if (isset($tokenRes['error']) && in_array($tokenRes['error'], ['NotRegistered', 'InvalidRegistration'], true)) {
                        $badToken = $batchTokens[$idx] ?? null;
                        if ($badToken) {
                            try {
                                $delStmt = $pdo->prepare("DELETE FROM tbl_fcm_tokens WHERE fcm_token=?");
                                $delStmt->execute([$badToken]);
                            } catch (Throwable $e) {}
                        }
                    }
                }
            }
        } else {
            error_log("FCM HTTP {$httpCode} Response: " . $response);
            $result['failed'] += count($batchTokens);
            $result['message'] = "FCM HTTP Error {$httpCode}";
        }
    }

    if ($result['sent'] > 0) {
        $result['success'] = true;
        $result['message'] = "Sent successfully to {$result['sent']} device(s).";
    }

    return $result;
}

/**
 * Broadcasts a notification to all customers or a specific customer,
 * saving both in-app notification and sending FCM push alerts to all registered devices.
 */
function broadcastPushNotification(
    PDO $pdo,
    string $merchantId,
    string $title,
    string $body,
    ?string $actionUrl = null,
    string $type = 'broadcast',
    ?int $targetCustomerId = null
): array {
    // 1. Create in-app notification in DB
    $notifId = createNotification(
        $pdo,
        $merchantId,
        $targetCustomerId,
        $title,
        $body,
        $type,
        null,
        $actionUrl
    );

    // 2. Fetch target device tokens
    $tokens = [];
    try {
        if ($targetCustomerId && $targetCustomerId > 0) {
            $stmt = $pdo->prepare("
                SELECT fcm_token FROM tbl_fcm_tokens 
                WHERE merchant_id = ? AND customer_id = ?
            ");
            $stmt->execute([$merchantId ?: 'local-merchant-001', $targetCustomerId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT fcm_token FROM tbl_fcm_tokens 
                WHERE merchant_id = ?
            ");
            $stmt->execute([$merchantId ?: 'local-merchant-001']);
        }
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        error_log("Error fetching FCM tokens: " . $e->getMessage());
    }

    // 3. Dispatch FCM Push
    $pushResult = sendFCMPushNotification(
        $pdo,
        $tokens,
        $title,
        $body,
        $actionUrl,
        null,
        ['notif_id' => $notifId, 'type' => $type]
    );

    return [
        'success' => true,
        'notif_id' => $notifId,
        'tokens_found' => count($tokens),
        'push_sent' => $pushResult['sent'] ?? 0,
        'push_failed' => $pushResult['failed'] ?? 0,
        'push_message' => $pushResult['message'] ?? ''
    ];
}

/**
 * Retrieves notifications for a given customer and/or merchant broadcasts
 */
function getCustomerNotifications(
    PDO $pdo,
    string $merchantId,
    ?int $customerId,
    int $limit = 50,
    ?string $type = null
): array {
    try {
        $mId = $merchantId ?: 'local-merchant-001';
        $params = [$mId];
        $whereSql = "WHERE merchant_id = ?";

        if ($customerId && $customerId > 0) {
            $whereSql .= " AND (customer_id = ? OR customer_id IS NULL OR customer_id = 0)";
            $params[] = $customerId;
        } else {
            $whereSql .= " AND (customer_id IS NULL OR customer_id = 0)";
        }

        if (!empty($type) && $type !== 'all') {
            if ($type === 'orders') {
                $whereSql .= " AND type = 'order'";
            } elseif ($type === 'promos') {
                $whereSql .= " AND (type = 'promo' OR type = 'deal')";
            } elseif ($type === 'system') {
                $whereSql .= " AND (type = 'system' OR type = 'broadcast' OR type = 'general')";
            } else {
                $whereSql .= " AND type = ?";
                $params[] = $type;
            }
        }

        $sql = "SELECT * FROM tbl_notifications {$whereSql} ORDER BY created_at DESC LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log("getCustomerNotifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Returns unread notification count
 */
function getUnreadNotificationCount(PDO $pdo, string $merchantId, ?int $customerId): int {
    try {
        $mId = $merchantId ?: 'local-merchant-001';
        if ($customerId && $customerId > 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM tbl_notifications 
                WHERE merchant_id = ? AND is_read = false 
                AND (customer_id = ? OR customer_id IS NULL OR customer_id = 0)
            ");
            $stmt->execute([$mId, $customerId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM tbl_notifications 
                WHERE merchant_id = ? AND is_read = false 
                AND (customer_id IS NULL OR customer_id = 0)
            ");
            $stmt->execute([$mId]);
        }
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Marks one or all notifications as read
 */
function markNotificationsAsRead(PDO $pdo, string $merchantId, ?int $customerId, ?int $notificationId = null): bool {
    try {
        $mId = $merchantId ?: 'local-merchant-001';
        if ($notificationId && $notificationId > 0) {
            $stmt = $pdo->prepare("UPDATE tbl_notifications SET is_read = true WHERE id = ? AND merchant_id = ?");
            return $stmt->execute([$notificationId, $mId]);
        } else {
            if ($customerId && $customerId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE tbl_notifications SET is_read = true 
                    WHERE merchant_id = ? AND (customer_id = ? OR customer_id IS NULL OR customer_id = 0)
                ");
                return $stmt->execute([$mId, $customerId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tbl_notifications SET is_read = true 
                    WHERE merchant_id = ? AND (customer_id IS NULL OR customer_id = 0)
                ");
                return $stmt->execute([$mId]);
            }
        }
    } catch (Throwable $e) {
        error_log("markNotificationsAsRead error: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper to format relative time (e.g. "Just now", "5 mins ago", "2 hours ago")
 */
function timeAgoNotification(string $datetime): string {
    $time = strtotime($datetime);
    if (!$time) return 'Recently';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}
