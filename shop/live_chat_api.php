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

        // If in live mode, trigger email alert if admin is away
        if ($thread['mode'] === 'live') {
            notify_admin_live_message($pdo, $thread, $message ?: '[Attachment sent]');
        }

        echo json_encode([
            'status' => 'success',
            'message' => $newMsg,
            'mode' => $thread['mode']
        ]);
        exit;
    }

    case 'fetch_messages': {
        $thread = get_or_create_thread($pdo);
        $lastId = (int)($_GET['last_id'] ?? $_POST['last_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM tbl_shop_chat_messages WHERE thread_id = ? AND id > ? ORDER BY id ASC");
        $stmt->execute([$thread['id'], $lastId]);
        $newMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Refresh thread info
        $st = $pdo->prepare("SELECT mode, call_status, active_call_type FROM tbl_shop_chat_threads WHERE id = ?");
        $st->execute([$thread['id']]);
        $curr = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'status' => 'success',
            'messages' => $newMessages,
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
            $pdo->prepare("UPDATE tbl_shop_chat_threads SET call_status = 'ringing', active_call_type = ? WHERE id = ?")
                ->execute([$callType, $threadId]);
        } else if ($type === 'call_end') {
            $pdo->prepare("UPDATE tbl_shop_chat_threads SET call_status = 'idle', active_call_type = 'none' WHERE id = ?")
                ->execute([$threadId]);
            // Purge unprocessed signals for this thread
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

    case 'fetch_signals': {
        $receiver = $_GET['receiver'] ?? 'customer'; // 'customer' or 'admin'
        $threadId = (int)($_GET['thread_id'] ?? 0);

        if ($threadId === 0) {
            $thread = get_or_create_thread($pdo);
            $threadId = $thread['id'];
        }

        // Fetch signals sent by the OTHER peer that haven't been processed
        $otherSender = ($receiver === 'customer') ? 'admin' : 'customer';
        $stmt = $pdo->prepare("
            SELECT * FROM tbl_shop_chat_signals 
            WHERE thread_id = ? AND sender = ? AND processed = 0
            ORDER BY id ASC
        ");
        $stmt->execute([$threadId, $otherSender]);
        $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($signals)) {
            $ids = array_column($signals, 'id');
            $inClause = implode(',', array_map('intval', $ids));
            $pdo->exec("UPDATE tbl_shop_chat_signals SET processed = 1 WHERE id IN ($inClause)");
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
                   (SELECT message FROM tbl_shop_chat_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) as last_message,
                   (SELECT created_at FROM tbl_shop_chat_messages WHERE thread_id = t.id ORDER BY id DESC LIMIT 1) as last_message_at
            FROM tbl_shop_chat_threads t
            ORDER BY t.updated_at DESC
            LIMIT 50
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

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
}
