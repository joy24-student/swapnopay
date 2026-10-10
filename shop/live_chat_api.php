<?php
/**
 * Production Live Chat & WebRTC Calling API
 * Supports Customer Live Chat, Admin Realtime Console, AI Handover, Attachment Uploads,
 * Inactive Admin Email Alerts, and Peer-to-Peer Low Latency WebRTC Signals.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';

// Safe PostgreSQL schema initialization
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_shop_chat_threads (
            id SERIAL PRIMARY KEY,
            thread_token VARCHAR(64) UNIQUE NOT NULL,
            customer_name VARCHAR(120) DEFAULT 'Customer',
            customer_email VARCHAR(180) DEFAULT '',
            customer_phone VARCHAR(60) DEFAULT '',
            customer_session VARCHAR(100) DEFAULT '',
            mode VARCHAR(20) DEFAULT 'ai',
            status VARCHAR(20) DEFAULT 'active',
            unread_admin INT DEFAULT 0,
            unread_customer INT DEFAULT 0,
            active_call_type VARCHAR(20) DEFAULT 'none',
            call_status VARCHAR(20) DEFAULT 'idle',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS tbl_shop_chat_messages (
            id SERIAL PRIMARY KEY,
            thread_id INT NOT NULL,
            sender_type VARCHAR(20) NOT NULL, -- 'customer', 'ai', 'admin', 'system'
            message TEXT DEFAULT '',
            attachment_url TEXT DEFAULT '',
            attachment_type VARCHAR(30) DEFAULT '',
            product_data JSONB DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS tbl_shop_chat_signals (
            id SERIAL PRIMARY KEY,
            thread_id INT NOT NULL,
            sender VARCHAR(20) NOT NULL, -- 'customer' or 'admin'
            signal_type VARCHAR(30) NOT NULL, -- 'offer', 'answer', 'candidate', 'call_start', 'call_end'
            call_type VARCHAR(20) DEFAULT 'audio', -- 'audio' or 'video'
            payload TEXT NOT NULL,
            processed SMALLINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_chat_msg_thread ON tbl_shop_chat_messages (thread_id);
        CREATE INDEX IF NOT EXISTS idx_chat_signal_thread ON tbl_shop_chat_signals (thread_id, processed);
    ");
} catch (Throwable $e) {
    // Database schema already exists or fallback handled
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Get or create customer chat thread based on session/cookie token
 */
function get_or_create_thread(PDO $pdo): array {
    if (empty($_SESSION['shop_chat_token'])) {
        $token = bin2hex(random_bytes(16));
        $_SESSION['shop_chat_token'] = $token;
    } else {
        $token = $_SESSION['shop_chat_token'];
    }

    $cName = 'Customer';
    $cEmail = '';
    $cPhone = '';

    if (!empty($_SESSION['customer']['cust_name'])) {
        $cName = $_SESSION['customer']['cust_name'];
        $cEmail = $_SESSION['customer']['cust_email'] ?? '';
        $cPhone = $_SESSION['customer']['cust_phone'] ?? '';
    }

    $stmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_threads WHERE thread_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$thread) {
        $ins = $pdo->prepare("
            INSERT INTO tbl_shop_chat_threads (thread_token, customer_name, customer_email, customer_phone, customer_session, mode, status)
            VALUES (?, ?, ?, ?, ?, 'ai', 'active')
            RETURNING *
        ");
        $ins->execute([$token, $cName, $cEmail, $cPhone, session_id()]);
        $thread = $ins->fetch(PDO::FETCH_ASSOC);
    } else if (!empty($_SESSION['customer']['cust_name']) && $thread['customer_name'] === 'Customer') {
        $up = $pdo->prepare("UPDATE tbl_shop_chat_threads SET customer_name = ?, customer_email = ?, customer_phone = ? WHERE id = ?");
        $up->execute([$cName, $cEmail, $cPhone, $thread['id']]);
        $thread['customer_name'] = $cName;
        $thread['customer_email'] = $cEmail;
        $thread['customer_phone'] = $cPhone;
    }

    return $thread;
}

/**
 * Send email notification to store admin when user messages in live mode and admin has not responded recently
 */
function notify_admin_live_message(PDO $pdo, array $thread, string $messageContent) {
    try {
        $lastEmailFile = sys_get_temp_dir() . '/last_chat_email_' . $thread['id'] . '.txt';
        $now = time();
        if (file_exists($lastEmailFile)) {
            $lastTime = (int)@file_get_contents($lastEmailFile);
            if (($now - $lastTime) < 600) { // 10 minutes throttle per thread
                return;
            }
        }

        $stmt = $pdo->prepare("SELECT receive_email, contact_email, smtp_from_name FROM tbl_settings WHERE id = 1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        $adminEmail = !empty($settings['receive_email']) ? $settings['receive_email'] : ($settings['contact_email'] ?? '');
        if (empty($adminEmail)) {
            return;
        }

        $fromName = $settings['smtp_from_name'] ?: 'Store Live Chat';
        $subject = "Live Customer Message - " . htmlspecialchars($thread['customer_name']);
        
        $body = "<h3>New Live Customer Support Message</h3>"
              . "<p><strong>Customer:</strong> " . htmlspecialchars($thread['customer_name']) . " (" . htmlspecialchars($thread['customer_email'] ?: 'Guest') . ")</p>"
              . "<p><strong>Message:</strong><br><em>" . nl2br(htmlspecialchars($messageContent)) . "</em></p>"
              . "<p><a href='" . (defined('BASE_URL') ? BASE_URL : '') . "admin/live-chat.php' style='display:inline-block;padding:10px 18px;background:#2563EB;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;'>Open Admin Live Chat Console</a></p>";

        send_email($adminEmail, 'Store Admin', $subject, $body);
        @file_put_contents($lastEmailFile, (string)$now);
    } catch (Throwable $e) {
        // Suppress email exceptions so chat is never disrupted
    }
}

// ---------------------- ROUTE HANDLERS ---------------------- //

switch ($action) {
    case 'get_state': {
        $thread = get_or_create_thread($pdo);

        // Fetch messages for thread
        $stmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_messages WHERE thread_id = ? ORDER BY id ASC");
        $stmt->execute([$thread['id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Clear customer unread count
        $pdo->prepare("UPDATE tbl_shop_chat_threads SET unread_customer = 0 WHERE id = ?")->execute([$thread['id']]);

        // Check admin online status (active in last 5 minutes)
        $adminActive = false;
        try {
            $adminFile = sys_get_temp_dir() . '/admin_last_chat_ping.txt';
            if (file_exists($adminFile) && (time() - (int)file_get_contents($adminFile)) < 300) {
                $adminActive = true;
            }
        } catch (Throwable $e) {}

        echo json_encode([
            'status' => 'success',
            'thread' => $thread,
            'messages' => $messages,
            'admin_online' => $adminActive
        ]);
        exit;
    }

    case 'send_message': {
        $thread = get_or_create_thread($pdo);
        $message = trim($_POST['message'] ?? '');
        $attachment = trim($_POST['attachment_url'] ?? '');
        $attachmentType = trim($_POST['attachment_type'] ?? '');
        $productData = !empty($_POST['product_data']) ? $_POST['product_data'] : null;
        if (is_array($productData)) {
            $productData = json_encode($productData);
        }

        if ($message === '' && $attachment === '' && empty($productData)) {
            echo json_encode(['status' => 'error', 'message' => 'Empty message']);
            exit;
        }

        // Insert customer message
        $stmt = $pdo->prepare("
            INSERT INTO tbl_shop_chat_messages (thread_id, sender_type, message, attachment_url, attachment_type, product_data)
            VALUES (?, 'customer', ?, ?, ?, ?)
            RETURNING *
        ");
        $stmt->execute([$thread['id'], $message, $attachment, $attachmentType, $productData]);
        $newMsg = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update thread stats
        $pdo->prepare("UPDATE tbl_shop_chat_threads SET unread_admin = unread_admin + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$thread['id']]);

        // If in live mode, trigger email alert and mobile push if admin is away
        if ($thread['mode'] === 'live') {
            notify_admin_live_message($pdo, $thread, $message ?: '[Attachment sent]');
            try {
                require_once __DIR__ . '/admin/inc/notifications.php';
                $custTitle = !empty($thread['customer_name']) ? $thread['customer_name'] : 'Customer #' . $thread['id'];
                $chatUrl = (defined('BASE_URL') ? BASE_URL : '') . 'admin/live-chat.php';
                sendAdminPushNotification(
                    $pdo,
                    '💬 ' . $custTitle,
                    mb_substr($message ?: 'Sent an attachment', 0, 100),
                    $chatUrl,
                    [
                        'type' => 'chat',
                        'action' => 'chat',
                        'thread_id' => (string)$thread['id'],
                        'customer_name' => $custTitle,
                        'url' => $chatUrl,
                        'target_url' => $chatUrl
                    ]
                );
            } catch (Throwable $e) {}
        }

        echo json_encode([
            'status' => 'success',
            'message' => $newMsg,
            'mode' => $thread['mode']
        ]);
        exit;
    }

    case 'fetch_messages': {
        $threadId = !empty($_GET['thread_id']) ? (int)$_GET['thread_id'] : (!empty($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0);
        $thread = null;
        if ($threadId > 0) {
            $thStmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_threads WHERE id = ? LIMIT 1");
            $thStmt->execute([$threadId]);
            $thread = $thStmt->fetch(PDO::FETCH_ASSOC);
            if ($thread) {
                try {
                    $pdo->prepare("UPDATE tbl_shop_chat_threads SET unread_admin = 0 WHERE id = ?")->execute([$threadId]);
                } catch (Throwable $e) {}
            }
        }
        if (!$thread) {
            $thread = get_or_create_thread($pdo);
        }

        $lastId = (int)($_GET['last_id'] ?? $_POST['last_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_messages WHERE thread_id = ? AND id > ? ORDER BY id ASC");
        $stmt->execute([$thread['id'], $lastId]);
        $newMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Refresh thread info
        $st = $pdo->prepare("SELECT mode, call_status, active_call_type, customer_name, customer_email, customer_phone, created_at, updated_at FROM tbl_shop_chat_threads WHERE id = ?");
        $st->execute([$thread['id']]);
        $curr = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        // Fetch recent orders for this customer if email or phone exists
        $recentOrders = [];
        if (!empty($curr['customer_email']) || !empty($curr['customer_phone'])) {
            try {
                $oQuery = "SELECT id, payment_id, customer_name, customer_email, paid_amount, payment_method, payment_status, shipping_status, payment_date, shipping_address, shipping_city, shipping_state, billing_address, billing_city, billing_state FROM tbl_payment WHERE 1=0";
                $oParams = [];
                if (!empty($curr['customer_email'])) {
                    $oQuery .= " OR customer_email = ?";
                    $oParams[] = $curr['customer_email'];
                }
                if (!empty($curr['customer_phone'])) {
                    $cleanPh = preg_replace('/[^0-9]/', '', $curr['customer_phone']);
                    if (strlen($cleanPh) >= 8) {
                        $shortPh = substr($cleanPh, -8);
                        $oQuery .= " OR shipping_phone LIKE ? OR billing_phone LIKE ?";
                        $oParams[] = '%' . $shortPh . '%';
                        $oParams[] = '%' . $shortPh . '%';
                    }
                }
                $oQuery .= " ORDER BY id DESC LIMIT 5";
                $oStmt = $pdo->prepare($oQuery);
                $oStmt->execute($oParams);
                $recentOrders = $oStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($recentOrders as &$ord) {
                    $pId = $ord['payment_id'];
                    $iStmt = $pdo->prepare("SELECT o.product_name, o.size, o.color, o.quantity, o.unit_price, p.p_featured_photo FROM tbl_order o LEFT JOIN tbl_product p ON p.p_id = o.product_id WHERE o.payment_id = ? LIMIT 1");
                    $iStmt->execute([$pId]);
                    $ord['item'] = $iStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                unset($ord);
            } catch (Throwable $e) {}
        }

        echo json_encode([
            'status' => 'success',
            'thread' => array_merge($thread, $curr),
            'messages' => $newMessages,
            'orders' => $recentOrders,
            'mode' => $curr['mode'] ?? 'ai',
            'call_status' => $curr['call_status'] ?? 'idle',
            'active_call_type' => $curr['active_call_type'] ?? 'none'
        ]);
        exit;
    }

    case 'switch_mode': {
        $thread = get_or_create_thread($pdo);
        $newMode = ($_POST['mode'] ?? '') === 'live' ? 'live' : 'ai';

        $pdo->prepare("UPDATE tbl_shop_chat_threads SET mode = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$newMode, $thread['id']]);

        // Insert system alert message
        $sysNotice = ($newMode === 'live')
            ? "Connecting you to a Live Human Specialist. Please hold on..."
            : "Switched back to AI Shopping Assistant. Ask anything!";

        // Clean up previous mode switch system notices in this thread so they don't pile up
        try {
            $pdo->prepare("DELETE FROM tbl_shop_chat_messages WHERE thread_id = ? AND sender_type = 'system' AND (message LIKE 'Connecting you to a Live Human%' OR message LIKE 'Switched back to AI%')")
                ->execute([$thread['id']]);
        } catch (Throwable $e) {}

        $pdo->prepare("INSERT INTO tbl_shop_chat_messages (thread_id, sender_type, message) VALUES (?, 'system', ?)")
            ->execute([$thread['id'], $sysNotice]);

        if ($newMode === 'live') {
            notify_admin_live_message($pdo, $thread, "Customer requested a live agent connection.");
        }

        echo json_encode(['status' => 'success', 'mode' => $newMode, 'notice' => $sysNotice]);
        exit;
    }

    case 'upload_attachment': {
        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
            exit;
        }

        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'mp3', 'wav', 'ogg'];

        if (!in_array($ext, $allowed, true)) {
            echo json_encode(['status' => 'error', 'message' => 'Unsupported file format']);
            exit;
        }

        $uploadDir = __DIR__ . '/assets/uploads/chat/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $filename = 'chat_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $publicUrl = 'assets/uploads/chat/' . $filename;
            $type = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? 'image' : ($ext === 'pdf' ? 'document' : 'audio');
            echo json_encode(['status' => 'success', 'url' => $publicUrl, 'type' => $type]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Could not move uploaded file']);
        }
        exit;
    }

    // ---------------------- WEBRTC SIGNALING ENGINE ---------------------- //
    case 'call_signal': {
        $sender = $_POST['sender'] ?? 'customer'; // 'customer' or 'admin'
        $type = $_POST['signal_type'] ?? '';      // 'offer', 'answer', 'candidate', 'call_start', 'call_end'
        $callType = $_POST['call_type'] ?? 'audio'; // 'audio' or 'video'
        $payload = $_POST['payload'] ?? '';
        $threadId = (int)($_POST['thread_id'] ?? 0);

        if ($threadId === 0) {
            $thread = get_or_create_thread($pdo);
            $threadId = $thread['id'];
        }

        if ($type === 'call_start') {
            // Clean up any stale signals from previous calls so new call starts fresh
            $pdo->prepare("DELETE FROM tbl_shop_chat_signals WHERE thread_id = ?")->execute([$threadId]);
            $pdo->prepare("UPDATE tbl_shop_chat_threads SET call_status = 'ringing', active_call_type = ? WHERE id = ?")
                ->execute([$callType, $threadId]);

            // Dispatch instant high-priority FCM Call Push to customer app so phone rings even when app is closed/locked
            if ($sender === 'admin') {
                try {
                    require_once __DIR__ . '/admin/inc/notifications.php';
                    $mId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';

                    // Find customer info associated with this chat thread
                    $thStmt = $pdo->prepare("SELECT customer_name, customer_email, customer_phone FROM tbl_shop_chat_threads WHERE id = ?");
                    $thStmt->execute([$threadId]);
                    $thData = $thStmt->fetch(PDO::FETCH_ASSOC);

                    $targetCustId = null;
                    if (!empty($thData['customer_email'])) {
                        $cStmt = $pdo->prepare("SELECT cust_id FROM tbl_customer WHERE cust_email = ? LIMIT 1");
                        $cStmt->execute([$thData['customer_email']]);
                        $targetCustId = $cStmt->fetchColumn() ?: null;
                    }
                    if (!$targetCustId && !empty($thData['customer_phone'])) {
                        $cStmt = $pdo->prepare("SELECT cust_id FROM tbl_customer WHERE cust_phone = ? LIMIT 1");
                        $cStmt->execute([$thData['customer_phone']]);
                        $targetCustId = $cStmt->fetchColumn() ?: null;
                    }

                    $devTokens = [];
                    if ($targetCustId) {
                        $stmtTokens = $pdo->prepare("
                            SELECT DISTINCT fcm_token FROM tbl_fcm_tokens 
                            WHERE merchant_id = ? AND customer_id = ? AND device_type NOT IN ('admin_android', 'admin')
                        ");
                        $stmtTokens->execute([$mId, $targetCustId]);
                        $devTokens = $stmtTokens->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    }

                    // Fallback to customer-only devices (strictly NEVER include admin devices)
                    if (empty($devTokens)) {
                        $stmtTokens = $pdo->prepare("
                            SELECT DISTINCT fcm_token FROM tbl_fcm_tokens 
                            WHERE merchant_id = ? AND device_type NOT IN ('admin_android', 'admin')
                            ORDER BY updated_at DESC LIMIT 5
                        ");
                        $stmtTokens->execute([$mId]);
                        $devTokens = $stmtTokens->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    }

                    if (!empty($devTokens)) {
                        $callLabel = ($callType === 'video') ? 'Video Call' : 'Voice Call';
                        $storeBaseUrl = defined('BASE_URL') ? BASE_URL : '/';
                        $callActionUrl = rtrim($storeBaseUrl, '/') . '/messages.php?auto_answer=1';
                        sendFCMPushNotification(
                            $pdo,
                            $devTokens,
                            'Abir Luxe Store (' . $callLabel . ')',
                            'Store Admin is calling you...',
                            $callActionUrl,
                            null,
                            [
                                'type' => 'call',
                                'action' => 'call',
                                'incoming_call' => 'true',
                                'call_type' => $callType,
                                'thread_id' => (string)$threadId,
                                'caller_name' => 'Abir Luxe Store',
                                'call_note' => 'Incoming ' . $callLabel,
                                'url' => $callActionUrl,
                                'target_url' => $callActionUrl
                            ]
                        );
                    }
                } catch (Throwable $e) {
                    error_log("FCM call push error in live_chat_api: " . $e->getMessage());
                }
            } else if ($sender === 'customer') {
                try {
                    require_once __DIR__ . '/admin/inc/notifications.php';
                    $callLabel = ($callType === 'video') ? 'Video Call' : 'Voice Call';
                    $storeBaseUrl = defined('BASE_URL') ? BASE_URL : '/';
                    $adminCallUrl = rtrim($storeBaseUrl, '/') . '/admin/live-chat.php?thread_id=' . $threadId . '&auto_answer=1';

                    $custStmt = $pdo->prepare("SELECT customer_name, customer_email, customer_phone FROM tbl_shop_chat_threads WHERE id = ?");
                    $custStmt->execute([$threadId]);
                    $threadRow = $custStmt->fetch(PDO::FETCH_ASSOC);
                    $callerName = !empty($threadRow['customer_name']) ? $threadRow['customer_name'] : 'Customer #' . $threadId;

                    sendAdminPushNotification(
                        $pdo,
                        $callerName . ' (' . $callLabel . ')',
                        'Customer requested live support consultation',
                        $adminCallUrl,
                        [
                            'type' => 'call',
                            'action' => 'call',
                            'incoming_call' => 'true',
                            'call_type' => $callType,
                            'thread_id' => (string)$threadId,
                            'caller_name' => $callerName,
                            'call_note' => 'Customer requested live ' . $callLabel,
                            'url' => $adminCallUrl,
                            'target_url' => $adminCallUrl
                        ]
                    );
                } catch (Throwable $e) {
                    error_log("FCM admin call push error in live_chat_api: " . $e->getMessage());
                }
            }
        } else if ($type === 'answer') {
            $pdo->prepare("UPDATE tbl_shop_chat_threads SET call_status = 'in_call' WHERE id = ?")
                ->execute([$threadId]);
        } else if ($type === 'call_end') {
            // Retrieve previous call status & details to log accurately
            $thStmt = $pdo->prepare("SELECT call_status, active_call_type, customer_name FROM tbl_shop_chat_threads WHERE id = ?");
            $thStmt->execute([$threadId]);
            $thRow = $thStmt->fetch(PDO::FETCH_ASSOC);
            $prevStatus = $thRow['call_status'] ?? 'idle';
            $actualCallType = (!empty($thRow['active_call_type']) && $thRow['active_call_type'] !== 'none') ? $thRow['active_call_type'] : $callType;
            $isVoice = ($actualCallType === 'audio');
            $callTypeLabel = $isVoice ? 'Voice Call' : 'Video Call';
            $callIcon = $isVoice ? '📞' : '📹';

            // Parse payload for duration, decline, or cancel state
            $durationStr = '';
            $isDeclined = false;
            $isCancelled = false;

            if (!empty($payload)) {
                $payloadData = @json_decode($payload, true);
                if (is_array($payloadData)) {
                    if (!empty($payloadData['duration'])) {
                        $durationStr = trim($payloadData['duration']);
                    }
                    if (!empty($payloadData['status']) && $payloadData['status'] === 'declined') {
                        $isDeclined = true;
                    }
                    if (!empty($payloadData['status']) && $payloadData['status'] === 'cancelled') {
                        $isCancelled = true;
                    }
                } else {
                    $pStr = strtolower(trim($payload));
                    if ($pStr === 'declined') {
                        $isDeclined = true;
                    } else if ($pStr === 'cancelled') {
                        $isCancelled = true;
                    } else if (strpos($pStr, 'ended:') === 0) {
                        $durationStr = substr($pStr, 6);
                    }
                }
            }

            // Construct WhatsApp-style system call log message
            if (!empty($durationStr) && $durationStr !== '00:00') {
                $callLogText = "{$callIcon} {$callTypeLabel} ended • {$durationStr}";
            } else if ($isDeclined) {
                $callLogText = "🚫 {$callTypeLabel} declined";
            } else if ($prevStatus === 'ringing' || $isCancelled) {
                if ($sender === 'customer') {
                    $callLogText = "📵 Missed {$callTypeLabel}";
                } else {
                    $callLogText = "{$callIcon} Outgoing {$callTypeLabel} • No answer";
                }
            } else {
                $callLogText = "{$callIcon} {$callTypeLabel} ended";
            }

            // Insert system message into chat messages table
            try {
                $msgStmt = $pdo->prepare("
                    INSERT INTO tbl_shop_chat_messages (thread_id, sender_type, message)
                    VALUES (?, 'system', ?)
                ");
                $msgStmt->execute([$threadId, $callLogText]);
            } catch (Throwable $e) {
                error_log("Failed to insert call log message: " . $e->getMessage());
            }

            $pdo->prepare("UPDATE tbl_shop_chat_threads SET call_status = 'idle', active_call_type = 'none', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$threadId]);
            $pdo->prepare("DELETE FROM tbl_shop_chat_signals WHERE thread_id = ?")->execute([$threadId]);
        }

        // Store signal for the other party
        $pdo->prepare("
            INSERT INTO tbl_shop_chat_signals (thread_id, sender, signal_type, call_type, payload)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$threadId, $sender, $type, $callType, $payload]);

        echo json_encode(['status' => 'success']);
        exit;
    }

    case 'get_call_status': {
        $threadId = !empty($_GET['thread_id']) ? (int)$_GET['thread_id'] : (!empty($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0);
        $thread = null;

        if ($threadId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_threads WHERE id = ?");
            $stmt->execute([$threadId]);
            $thread = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Find any thread with an active or ringing call
            $stmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_threads WHERE call_status IN ('ringing', 'in_call') ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute();
            $thread = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$thread) {
            echo json_encode(['status' => 'success', 'has_call' => false]);
            exit;
        }

        $hasCall = in_array($thread['call_status'], ['ringing', 'in_call']);
        $offerPayload = null;

        if ($hasCall) {
            // Retrieve latest offer signal for this thread (from whoever initiated the call)
            $sigStmt = $pdo->prepare("
                SELECT payload FROM tbl_shop_chat_signals 
                WHERE thread_id = ? AND signal_type = 'offer' 
                ORDER BY id DESC LIMIT 1
            ");
            $sigStmt->execute([$thread['id']]);
            $sig = $sigStmt->fetch(PDO::FETCH_ASSOC);
            if ($sig) {
                $offerPayload = $sig['payload'];
            }
        }

        echo json_encode([
            'status' => 'success',
            'has_call' => $hasCall,
            'thread_id' => (int)$thread['id'],
            'call_status' => $thread['call_status'],
            'call_type' => $thread['active_call_type'] ?: 'audio',
            'customer_name' => $thread['customer_name'] ?: 'Customer #' . $thread['id'],
            'customer_phone' => $thread['customer_phone'] ?: '',
            'offer' => $offerPayload
        ]);
        exit;
    }

    case 'fetch_signals': {
        $receiver = $_GET['receiver'] ?? 'customer'; // 'customer' or 'admin'
        $threadId = (int)($_GET['thread_id'] ?? 0);

        // Auto-purge stale signals older than 90 seconds
        try {
            $pdo->exec("DELETE FROM tbl_shop_chat_signals WHERE created_at < NOW() - INTERVAL '90 seconds'");
        } catch (Throwable $e) {}

        if ($receiver === 'admin') {
            $signals = [];

            // 1. Check for incoming calls across ALL customer threads
            // If another customer is calling (call_start), admin needs to be notified regardless of focused thread!
            $callStartStmt = $pdo->prepare("
                SELECT s.*, t.customer_name, t.customer_phone 
                FROM tbl_shop_chat_signals s
                JOIN tbl_shop_chat_threads t ON t.id = s.thread_id
                WHERE s.sender = 'customer' AND s.processed = 0 AND s.signal_type = 'call_start'
                ORDER BY s.id ASC LIMIT 1
            ");
            $callStartStmt->execute();
            $incomingCall = $callStartStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($incomingCall)) {
                $signals = array_merge($signals, $incomingCall);
                if ($threadId === 0) {
                    $threadId = (int)$incomingCall[0]['thread_id'];
                }
            }

            // 2. If admin is focused on a specific thread, fetch all remaining signals for that thread (offer, answer, candidate, call_end)
            if ($threadId > 0) {
                $activeStmt = $pdo->prepare("
                    SELECT s.*, t.customer_name, t.customer_phone
                    FROM tbl_shop_chat_signals s
                    JOIN tbl_shop_chat_threads t ON t.id = s.thread_id
                    WHERE s.thread_id = ? AND s.sender = 'customer' AND s.processed = 0 AND s.signal_type != 'call_start'
                    ORDER BY s.id ASC
                ");
                $activeStmt->execute([$threadId]);
                $threadSignals = $activeStmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($threadSignals)) {
                    $signals = array_merge($signals, $threadSignals);
                }

                // If ringing, ensure offer is present even if previously marked processed
                $thCheck = $pdo->prepare("SELECT call_status FROM tbl_shop_chat_threads WHERE id = ?");
                $thCheck->execute([$threadId]);
                $thRow = $thCheck->fetch(PDO::FETCH_ASSOC);
                if ($thRow && $thRow['call_status'] === 'ringing') {
                    $hasOffer = false;
                    foreach ($signals as $s) {
                        if ($s['signal_type'] === 'offer') { $hasOffer = true; break; }
                    }
                    if (!$hasOffer) {
                        $offStmt = $pdo->prepare("
                            SELECT s.*, t.customer_name, t.customer_phone 
                            FROM tbl_shop_chat_signals s 
                            JOIN tbl_shop_chat_threads t ON t.id = s.thread_id
                            WHERE s.thread_id = ? AND s.sender = 'customer' AND s.signal_type = 'offer' 
                            ORDER BY s.id DESC LIMIT 1
                        ");
                        $offStmt->execute([$threadId]);
                        $offRow = $offStmt->fetch(PDO::FETCH_ASSOC);
                        if ($offRow) {
                            $signals[] = $offRow;
                        }
                    }
                }
            }

            if (!empty($signals)) {
                $ids = array_column($signals, 'id');
                $inClause = implode(',', array_map('intval', $ids));
                // Only mark processed for candidate and answer signals; keep offer/call_start active while ringing
                $pdo->exec("UPDATE tbl_shop_chat_signals SET processed = 1 WHERE id IN ($inClause) AND signal_type NOT IN ('call_start', 'offer')");
            }

            echo json_encode(['status' => 'success', 'signals' => $signals]);
            exit;
        }

        // Customer polling
        if ($threadId === 0) {
            $thread = get_or_create_thread($pdo);
            $threadId = $thread['id'];
        }

        // Fetch signals sent by admin for this customer's thread
        $stmt = $pdo->prepare("
            SELECT * FROM tbl_shop_chat_signals 
            WHERE thread_id = ? AND sender = 'admin' AND processed = 0
            ORDER BY id ASC
        ");
        $stmt->execute([$threadId]);
        $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If thread is currently ringing, ensure call_start and offer are provided even if polled earlier!
        $thCheck = $pdo->prepare("SELECT call_status FROM tbl_shop_chat_threads WHERE id = ?");
        $thCheck->execute([$threadId]);
        $thRow = $thCheck->fetch(PDO::FETCH_ASSOC);
        if ($thRow && $thRow['call_status'] === 'ringing') {
            $hasCallStart = false;
            $hasOffer = false;
            foreach ($signals as $s) {
                if ($s['signal_type'] === 'call_start') $hasCallStart = true;
                if ($s['signal_type'] === 'offer') $hasOffer = true;
            }
            if (!$hasCallStart) {
                $csStmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_signals WHERE thread_id = ? AND sender = 'admin' AND signal_type = 'call_start' ORDER BY id DESC LIMIT 1");
                $csStmt->execute([$threadId]);
                $csRow = $csStmt->fetch(PDO::FETCH_ASSOC);
                if ($csRow) {
                    array_unshift($signals, $csRow);
                }
            }
            if (!$hasOffer) {
                $offStmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_signals WHERE thread_id = ? AND sender = 'admin' AND signal_type = 'offer' ORDER BY id DESC LIMIT 1");
                $offStmt->execute([$threadId]);
                $offRow = $offStmt->fetch(PDO::FETCH_ASSOC);
                if ($offRow) {
                    $signals[] = $offRow;
                }
            }
        }

        if (!empty($signals)) {
            $ids = array_column($signals, 'id');
            $inClause = implode(',', array_map('intval', $ids));
            // Mark candidate/answer as processed; keep offer/call_start active while ringing so reload can auto-answer
            $pdo->exec("UPDATE tbl_shop_chat_signals SET processed = 1 WHERE id IN ($inClause) AND signal_type NOT IN ('call_start', 'offer')");
        }

        echo json_encode(['status' => 'success', 'signals' => $signals]);
        exit;
    }

    // ---------------------- ADMIN SPECIFIC ENDPOINTS ---------------------- //
    case 'admin_ping': {
        // Track admin activity timestamp
        $adminFile = sys_get_temp_dir() . '/admin_last_chat_ping.txt';
        @file_put_contents($adminFile, (string)time());
        echo json_encode(['status' => 'success']);
        exit;
    }

    case 'admin_get_threads': {
        // Update admin activity timestamp
        $adminFile = sys_get_temp_dir() . '/admin_last_chat_ping.txt';
        @file_put_contents($adminFile, (string)time());

        $stmt = $pdo->query("
            SELECT t.*, 
                   COALESCE((SELECT message FROM tbl_shop_chat_messages WHERE thread_id = t.id AND sender_type != 'system' ORDER BY id DESC LIMIT 1), 'Customer opened chat session') as last_message,
                   COALESCE((SELECT created_at FROM tbl_shop_chat_messages WHERE thread_id = t.id AND sender_type != 'system' ORDER BY id DESC LIMIT 1), t.updated_at) as last_message_at
            FROM tbl_shop_chat_threads t
            ORDER BY t.updated_at DESC
            LIMIT 60
        ");
        $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'threads' => $threads]);
        exit;
    }

    case 'admin_send_reply': {
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $attachment = trim($_POST['attachment_url'] ?? '');
        $attachmentType = trim($_POST['attachment_type'] ?? '');

        if (!$threadId || ($message === '' && $attachment === '')) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_shop_chat_messages (thread_id, sender_type, message, attachment_url, attachment_type)
            VALUES (?, 'admin', ?, ?, ?)
            RETURNING *
        ");
        $stmt->execute([$threadId, $message, $attachment, $attachmentType]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);

        $pdo->prepare("UPDATE tbl_shop_chat_threads SET unread_customer = unread_customer + 1, mode = 'live', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$threadId]);

        // Omnichannel WhatsApp dispatch if customer has a valid phone registered
        try {
            $thStmt = $pdo->prepare("SELECT customer_phone, customer_name FROM tbl_shop_chat_threads WHERE id = ?");
            $thStmt->execute([$threadId]);
            $thData = $thStmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($thData['customer_phone']) && function_exists('send_whatsapp_cloud_api')) {
                $waText = "Support Update: " . ($message !== '' ? $message : "[Sent an attachment: " . $attachment . "]");
                send_whatsapp_cloud_api($thData['customer_phone'], $waText);
            }
        } catch (Throwable $waErr) {
            // Log or ignore silently to not disrupt main chat flow
        }

        echo json_encode(['status' => 'success', 'message' => $msg]);
        exit;
    }

    case 'admin_takeover': {
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $mode = ($_POST['mode'] ?? 'live') === 'ai' ? 'ai' : 'live';

        $pdo->prepare("UPDATE tbl_shop_chat_threads SET mode = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$mode, $threadId]);

        $notice = ($mode === 'live') ? "A customer support agent has joined this chat." : "Chat handed over to AI assistant.";
        $pdo->prepare("INSERT INTO tbl_shop_chat_messages (thread_id, sender_type, message) VALUES (?, 'system', ?)")
            ->execute([$threadId, $notice]);

        echo json_encode(['status' => 'success', 'mode' => $mode]);
        exit;
    }

    case 'admin_update_status': {
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $status = strtolower(trim($_POST['status'] ?? 'active'));
        $allowed = ['active', 'pending', 'processing', 'blocked', 'resolved', 'closed'];

        if ($threadId && in_array($status, $allowed, true)) {
            $pdo->prepare("UPDATE tbl_shop_chat_threads SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$status, $threadId]);
            echo json_encode(['status' => 'success', 'new_status' => $status]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        }
        exit;
    }

    case 'admin_delete_thread': {
        $threadId = (int)($_POST['thread_id'] ?? $_GET['thread_id'] ?? 0);
        if (!$threadId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing thread ID']);
            exit;
        }

        try {
            // Delete associated signals, messages and the thread
            $pdo->prepare("DELETE FROM tbl_shop_chat_signals WHERE thread_id = ?")->execute([$threadId]);
            $pdo->prepare("DELETE FROM tbl_shop_chat_messages WHERE thread_id = ?")->execute([$threadId]);
            $del = $pdo->prepare("DELETE FROM tbl_shop_chat_threads WHERE id = ?");
            $del->execute([$threadId]);

            echo json_encode(['status' => 'success', 'message' => 'Conversation deleted successfully', 'thread_id' => $threadId]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
}
