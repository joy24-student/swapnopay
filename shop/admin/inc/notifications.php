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
 * Ensures required notification tables and settings columns exist in the active database
 */
function initNotificationTables(PDO $pdo): void {
    static $done = false;
    if ($done) return;

    try {
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'pgsql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_notifications (
                    id SERIAL PRIMARY KEY,
                    merchant_id VARCHAR(100) NOT NULL DEFAULT 'local-merchant-001',
                    customer_id INTEGER,
                    title VARCHAR(255) NOT NULL,
                    body TEXT NOT NULL,
                    type VARCHAR(50) DEFAULT 'general',
                    order_id INTEGER,
                    is_read BOOLEAN DEFAULT FALSE,
                    icon_url VARCHAR(500),
                    action_url VARCHAR(500),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notif_merchant ON tbl_notifications(merchant_id);"); } catch (Throwable $e) {}
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notif_customer ON tbl_notifications(customer_id);"); } catch (Throwable $e) {}

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_fcm_tokens (
                    id SERIAL PRIMARY KEY,
                    merchant_id VARCHAR(100) NOT NULL DEFAULT 'local-merchant-001',
                    customer_id INTEGER,
                    fcm_token TEXT NOT NULL UNIQUE,
                    device_type VARCHAR(50) DEFAULT 'android',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");
            try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fcm_tokens_merchant ON tbl_fcm_tokens(merchant_id);"); } catch (Throwable $e) {}

            $cols = ['fcm_server_key', 'firebase_api_key', 'firebase_auth_domain', 'firebase_project_id', 'firebase_storage_bucket', 'firebase_messaging_sender_id', 'firebase_app_id', 'firebase_vapid_key', 'firebase_service_account_json'];
            foreach ($cols as $col) {
                try {
                    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS {$col} TEXT");
                } catch (Throwable $e) {}
            }
        } else {
            // MySQL / MariaDB fallback
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    merchant_id VARCHAR(100) NOT NULL DEFAULT 'local-merchant-001',
                    customer_id INT NULL,
                    title VARCHAR(255) NOT NULL,
                    body TEXT NOT NULL,
                    type VARCHAR(50) DEFAULT 'general',
                    order_id INT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    icon_url VARCHAR(500) NULL,
                    action_url VARCHAR(500) NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_notif_merchant (merchant_id),
                    INDEX idx_notif_customer (customer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_fcm_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    merchant_id VARCHAR(100) NOT NULL DEFAULT 'local-merchant-001',
                    customer_id INT NULL,
                    fcm_token VARCHAR(500) NOT NULL UNIQUE,
                    device_type VARCHAR(50) DEFAULT 'android',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_fcm_tokens_merchant (merchant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $cols = ['fcm_server_key', 'firebase_api_key', 'firebase_auth_domain', 'firebase_project_id', 'firebase_storage_bucket', 'firebase_messaging_sender_id', 'firebase_app_id', 'firebase_vapid_key', 'firebase_service_account_json'];
            foreach ($cols as $col) {
                try {
                    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN {$col} TEXT NULL");
                } catch (Throwable $e) {}
            }
        }
        $done = true;
    } catch (Throwable $e) {
        error_log("initNotificationTables error: " . $e->getMessage());
    }
}

/**
 * Automatically parses Firebase JSON configuration file (google-services.json, service-account.json, or web config)
 * and extracts all credentials.
 */
function parseFirebaseConfigFile(string $jsonString): array {
    $data = json_decode($jsonString, true);
    if (!is_array($data)) {
        return [];
    }

    $extracted = [];

    // 1. Google Services JSON (Android config from Firebase Console: google-services.json)
    if (isset($data['project_info']) && is_array($data['project_info'])) {
        $pInfo = $data['project_info'];
        $extracted['firebase_project_id'] = (string)($pInfo['project_id'] ?? '');
        $extracted['firebase_messaging_sender_id'] = (string)($pInfo['project_number'] ?? '');
        $extracted['firebase_storage_bucket'] = (string)($pInfo['storage_bucket'] ?? '');
        if (!empty($extracted['firebase_project_id'])) {
            $extracted['firebase_auth_domain'] = $extracted['firebase_project_id'] . '.firebaseapp.com';
            if (empty($extracted['firebase_storage_bucket'])) {
                $extracted['firebase_storage_bucket'] = $extracted['firebase_project_id'] . '.appspot.com';
            }
        }

        if (isset($data['client']) && is_array($data['client']) && !empty($data['client'])) {
            $client = $data['client'][0];
            if (isset($client['client_info']['mobilesdk_app_id'])) {
                $extracted['firebase_app_id'] = (string)$client['client_info']['mobilesdk_app_id'];
            }
            if (isset($client['api_key']) && is_array($client['api_key']) && !empty($client['api_key'])) {
                $extracted['firebase_api_key'] = (string)($client['api_key'][0]['current_key'] ?? '');
            }
        }
        $extracted['_detected_type'] = 'Google Services JSON (google-services.json)';
    }

    // 2. Google Service Account JSON (service-account.json / firebase-adminsdk-xxx.json)
    elseif (isset($data['type']) && $data['type'] === 'service_account') {
        $extracted['firebase_project_id'] = (string)($data['project_id'] ?? '');
        if (!empty($extracted['firebase_project_id'])) {
            $extracted['firebase_auth_domain'] = $extracted['firebase_project_id'] . '.firebaseapp.com';
            $extracted['firebase_storage_bucket'] = $extracted['firebase_project_id'] . '.appspot.com';
        }
        $extracted['firebase_service_account_json'] = $jsonString;
        $extracted['_detected_type'] = 'Google Cloud / Firebase Service Account Key';
    }

    // 3. Web App Config JSON or generic key-value map
    else {
        $extracted['firebase_api_key'] = (string)($data['apiKey'] ?? $data['api_key'] ?? $data['firebase_api_key'] ?? '');
        $extracted['firebase_auth_domain'] = (string)($data['authDomain'] ?? $data['auth_domain'] ?? $data['firebase_auth_domain'] ?? '');
        $extracted['firebase_project_id'] = (string)($data['projectId'] ?? $data['project_id'] ?? $data['firebase_project_id'] ?? '');
        $extracted['firebase_storage_bucket'] = (string)($data['storageBucket'] ?? $data['storage_bucket'] ?? $data['firebase_storage_bucket'] ?? '');
        $extracted['firebase_messaging_sender_id'] = (string)($data['messagingSenderId'] ?? $data['messaging_sender_id'] ?? $data['firebase_messaging_sender_id'] ?? '');
        $extracted['firebase_app_id'] = (string)($data['appId'] ?? $data['app_id'] ?? $data['firebase_app_id'] ?? '');
        $extracted['firebase_vapid_key'] = (string)($data['vapidKey'] ?? $data['vapid_key'] ?? $data['firebase_vapid_key'] ?? '');
        $extracted['fcm_server_key'] = (string)($data['serverKey'] ?? $data['server_key'] ?? $data['fcm_server_key'] ?? '');
        $extracted['_detected_type'] = 'Firebase Web Configuration';
    }

    return array_filter($extracted, static fn($v) => $v !== '');
}

if (isset($pdo) && $pdo instanceof PDO) {
    initNotificationTables($pdo);
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
        $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'pgsql') {
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
            return (int)($row['id'] ?? 0);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_notifications 
                (merchant_id, customer_id, title, body, type, order_id, is_read, icon_url, action_url, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())
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
            return (int)$pdo->lastInsertId();
        }
    } catch (Throwable $e) {
        error_log("createNotification error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Generates or retrieves a cached OAuth 2.0 Access Token from Google for Firebase HTTP v1
 */
function getFirebaseV1AccessToken(string $serviceAccountJson): ?string {
    $sa = json_decode($serviceAccountJson, true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
        return null;
    }

    $tokenCacheFile = sys_get_temp_dir() . '/fcm_v1_token_' . md5($sa['client_email']) . '.json';
    if (file_exists($tokenCacheFile)) {
        $cached = @json_decode((string)@file_get_contents($tokenCacheFile), true);
        if (is_array($cached) && !empty($cached['access_token']) && ($cached['expires_at'] ?? 0) > (time() + 90)) {
            return (string)$cached['access_token'];
        }
    }

    $now = time();
    $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
    $claims = rtrim(strtr(base64_encode(json_encode([
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ])), '+/', '-_'), '=');

    $jwtUnsigned = $header . '.' . $claims;
    $binarySignature = '';
    $pkey = openssl_pkey_get_private($sa['private_key']);
    if (!$pkey) {
        error_log("FCM V1 OAuth: Invalid private key in service account JSON");
        return null;
    }

    if (!openssl_sign($jwtUnsigned, $binarySignature, $pkey, OPENSSL_ALGO_SHA256)) {
        error_log("FCM V1 OAuth: OpenSSL signing failed");
        return null;
    }

    $jwtSignature = rtrim(strtr(base64_encode($binarySignature), '+/', '-_'), '=');
    $assertion = $jwtUnsigned . '.' . $jwtSignature;

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $assertion
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        $tokenData = json_decode($res, true);
        if (!empty($tokenData['access_token'])) {
            $expiresIn = (int)($tokenData['expires_in'] ?? 3600);
            @file_put_contents($tokenCacheFile, json_encode([
                'access_token' => $tokenData['access_token'],
                'expires_at' => $now + $expiresIn
            ]));
            return (string)$tokenData['access_token'];
        }
    }

    error_log("FCM V1 OAuth Token Request failed HTTP {$httpCode}: " . $res);
    return null;
}

/**
 * Sends a native Push Notification to a list of device tokens via Firebase Cloud Messaging (FCM)
 * Supports both modern Firebase HTTP v1 (OAuth 2.0) and Legacy HTTP API.
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

    $serviceAccountJson = trim((string)($settings['firebase_service_account_json'] ?? ''));
    $projectId = trim((string)($settings['firebase_project_id'] ?? ''));
    $serverKey = trim((string)($settings['fcm_server_key'] ?? ''));
    if (empty($serverKey)) {
        $serverKey = getenv('FCM_SERVER_KEY') ?: '';
    }

    $defaultIcon = defined('BASE_URL') ? BASE_URL . 'assets/uploads/default_logo.png' : '';
    $finalIcon = $iconUrl ?: $defaultIcon;
    $finalUrl = $actionUrl ?: (defined('BASE_URL') ? BASE_URL : '/');
    $isCallType = (isset($extraData['type']) && strtolower((string)$extraData['type']) === 'call');

    // 1. Attempt Modern Firebase HTTP v1 first if service account JSON is available
    $v1AccessToken = null;
    if (!empty($serviceAccountJson)) {
        $saData = json_decode($serviceAccountJson, true);
        if (is_array($saData) && !empty($saData['project_id']) && empty($projectId)) {
            $projectId = (string)$saData['project_id'];
        }
        $v1AccessToken = getFirebaseV1AccessToken($serviceAccountJson);
    }

    if ($v1AccessToken && !empty($projectId)) {
        // --- MODERN FIREBASE HTTP v1 DISPATCH ---
        $v1Url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $baseData = $isCallType ? [
            'title' => $title,
            'body' => $body,
            'caller_name' => $title,
            'call_note' => $body,
            'type' => 'call',
            'action' => 'call',
            'incoming_call' => 'true',
            'url' => $finalUrl,
            'click_action' => $finalUrl,
            'icon' => $finalIcon,
            'timestamp' => (string)time()
        ] : [
            'title' => $title,
            'body' => $body,
            'url' => $finalUrl,
            'click_action' => $finalUrl,
            'icon' => $finalIcon,
            'timestamp' => (string)time()
        ];

        $mergedData = array_merge($baseData, $extraData);
        $dataPayload = [];
        foreach ($mergedData as $k => $v) {
            $dataPayload[(string)$k] = is_scalar($v) ? (string)$v : json_encode($v);
        }

        foreach ($tokens as $token) {
            if ($isCallType) {
                // High-priority pure DATA payload (NO top-level notification object)
                // Ensures onMessageReceived() is invoked by Android even when app is killed/background
                $messagePayload = [
                    'message' => [
                        'token' => $token,
                        'data' => $dataPayload,
                        'android' => [
                            'priority' => 'HIGH'
                        ]
                    ]
                ];
            } else {
                $messagePayload = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body
                        ],
                        'data' => $dataPayload,
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'sound' => 'default',
                                'click_action' => $finalUrl,
                                'icon' => $finalIcon
                            ]
                        ]
                    ]
                ];
            }

            $ch = curl_init($v1Url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $v1AccessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messagePayload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                error_log("FCM V1 cURL Error: " . $curlErr);
                $result['failed']++;
                $result['message'] = $curlErr;
                continue;
            }

            if ($httpCode === 200) {
                $result['sent']++;
            } else {
                $result['failed']++;
                error_log("FCM V1 HTTP {$httpCode}: " . $response);
                $respData = json_decode($response, true);
                $errStatus = $respData['error']['status'] ?? '';
                if ($errStatus === 'NOT_FOUND' || (isset($respData['error']['message']) && stripos($respData['error']['message'], 'Requested entity was not found') !== false)) {
                    try {
                        $delStmt = $pdo->prepare("DELETE FROM tbl_fcm_tokens WHERE fcm_token=?");
                        $delStmt->execute([$token]);
                    } catch (Throwable $e) {}
                }
                $result['message'] = "FCM V1 Error: " . ($respData['error']['message'] ?? "HTTP {$httpCode}");
            }
        }

        if ($result['sent'] > 0) {
            $result['success'] = true;
            $result['message'] = "Sent successfully via FCM HTTP v1 to {$result['sent']} device(s).";
        }

        return $result;
    }

    // 2. Fallback to Legacy HTTP API if Server Key is configured
    if (empty($serverKey)) {
        $result['message'] = 'Firebase is not configured. Please add your Service Account JSON (recommended) or FCM Server Key in Firebase Settings.';
        return $result;
    }

    // Split tokens into batches of 500 for FCM limits
    $batches = array_chunk($tokens, 500);

    foreach ($batches as $batchTokens) {
        if ($isCallType) {
            // For Instant Call Alerts: Must be pure high-priority DATA payload WITHOUT top-level "notification" key.
            // If "notification" is present, Android OS intercepts it in the background and NEVER invokes
            // onMessageReceived(), preventing the IncomingCallActivity from waking the phone and ringing!
            $payload = [
                'registration_ids' => array_values($batchTokens),
                'data' => array_merge([
                    'title' => $title,
                    'body' => $body,
                    'caller_name' => $title,
                    'call_note' => $body,
                    'type' => 'call',
                    'action' => 'call',
                    'incoming_call' => 'true',
                    'url' => $finalUrl,
                    'click_action' => $finalUrl,
                    'icon' => $finalIcon,
                    'timestamp' => (string)time()
                ], $extraData),
                'priority' => 'high',
                'content_available' => true,
                'android' => [
                    'priority' => 'high'
                ]
            ];
        } else {
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
                'priority' => 'high',
                'content_available' => true,
                'android' => [
                    'priority' => 'high'
                ]
            ];
        }

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

/**
 * Dispatch high-priority push notification directly to registered Admin Android devices.
 */
function sendAdminPushNotification(
    PDO $pdo,
    string $title,
    string $body,
    ?string $actionUrl = null,
    array $extraData = []
): array {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT fcm_token FROM tbl_fcm_tokens 
            WHERE device_type = 'admin_android' OR device_type = 'admin'
        ");
        $stmt->execute();
        $adminTokens = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (empty($adminTokens)) {
            return ['success' => false, 'message' => 'No admin device tokens registered'];
        }

        $defaultAdminUrl = defined('BASE_URL') ? BASE_URL . 'admin/' : '/admin/';
        $finalUrl = $actionUrl ?: $defaultAdminUrl;

        return sendFCMPushNotification(
            $pdo,
            $adminTokens,
            $title,
            $body,
            $finalUrl,
            null,
            $extraData
        );
    } catch (Throwable $e) {
        error_log("sendAdminPushNotification error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

