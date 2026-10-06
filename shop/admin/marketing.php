<?php
require_once __DIR__ . '/inc/guard.php';

// Handle AJAX actions before headers are sent
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];
    
    if ($action === 'get_messages') {
        $phone = trim($_GET['phone'] ?? '');
        try {
            $stmt = $pdo->prepare("SELECT * FROM tbl_whatsapp_messages WHERE customer_phone = ? ORDER BY id ASC");
            $stmt->execute([$phone]);
            echo json_encode(['status' => 'success', 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'send_message') {
        $phone = trim($_POST['phone'] ?? '');
        $name = trim($_POST['customer_name'] ?? '');
        $text = trim($_POST['message_text'] ?? '');
        $order_id = trim($_POST['order_id'] ?? '');
        if ($phone !== '' && $text !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO tbl_whatsapp_messages (customer_phone, customer_name, sender_type, message_text, order_id, status, created_at) VALUES (?, ?, 'admin', ?, ?, 'sent', NOW())");
                $stmt->execute([$phone, $name, $text, $order_id ?: null]);
                
                $id = $pdo->lastInsertId();
                $newMsg = [
                    'id' => $id,
                    'customer_phone' => $phone,
                    'customer_name' => $name,
                    'sender_type' => 'admin',
                    'message_text' => $text,
                    'order_id' => $order_id,
                    'status' => 'sent',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                echo json_encode(['status' => 'success', 'message' => $newMsg]);
            } catch (Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }
        echo json_encode(['status' => 'error', 'message' => 'Missing phone or text']);
        exit;
    }

    if ($action === 'get_threads') {
        try {
            $sql = "
                SELECT m1.*
                FROM tbl_whatsapp_messages m1
                JOIN (
                    SELECT customer_phone, MAX(id) as max_id
                    FROM tbl_whatsapp_messages
                    GROUP BY customer_phone
                ) m2 ON m1.id = m2.max_id
                ORDER BY m1.id DESC
            ";
            $threads = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'threads' => $threads]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

require_once __DIR__ . '/header.php';

$activeTab = $_GET['tab'] ?? 'whatsapp';
if (!in_array($activeTab, ['messenger', 'whatsapp'], true)) {
    $activeTab = 'whatsapp';
}

$subTab = $_GET['sub'] ?? 'chat'; // 'chat' or 'settings'

$success_message = '';
$error_message = '';

// Migration: Ensure necessary columns and tables exist
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_bot_keyword (
                id SERIAL PRIMARY KEY,
                keyword VARCHAR(100) NOT NULL,
                reply_text TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tbl_whatsapp_messages (
                id SERIAL PRIMARY KEY,
                customer_phone VARCHAR(30) NOT NULL,
                customer_name VARCHAR(100),
                sender_type VARCHAR(20) NOT NULL DEFAULT 'admin',
                message_text TEXT NOT NULL,
                order_id VARCHAR(50),
                status VARCHAR(20) DEFAULT 'sent',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_wa_msg_phone ON tbl_whatsapp_messages(customer_phone);
            ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_order_placed_template text DEFAULT '';
            ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_order_shipped_template text DEFAULT '';
            ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_order_completed_template text DEFAULT '';
            ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS whatsapp_auto_order_on_off smallint DEFAULT 1;
        ");
    }
} catch (Throwable $e) {}

// Handle Delete Keyword Rule
if (isset($_GET['action']) && $_GET['action'] === 'delete_rule' && !empty($_GET['rule_id'])) {
    $rule_id = (int)$_GET['rule_id'];
    $stmt = $pdo->prepare("DELETE FROM tbl_bot_keyword WHERE id = ?");
    $stmt->execute([$rule_id]);
    header("Location: marketing.php?tab=messenger&msg=rule_deleted");
    exit;
}

// Handle Toggle Keyword Rule Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle_rule' && !empty($_GET['rule_id'])) {
    $rule_id = (int)$_GET['rule_id'];
    $newStatus = ($_GET['current'] ?? '') === 'Active' ? 'Inactive' : 'Active';
    $stmt = $pdo->prepare("UPDATE tbl_bot_keyword SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $rule_id]);
    header("Location: marketing.php?tab=messenger&msg=rule_updated");
    exit;
}

// Handle Save Messenger Settings
if (isset($_POST['save_messenger_settings'])) {
    $chat_messenger_url = trim($_POST['chat_messenger_url'] ?? '');
    $chat_floating_icon_on_off = (int)($_POST['chat_floating_icon_on_off'] ?? 1);
    try {
        $stmt = $pdo->prepare("UPDATE tbl_settings SET chat_messenger_url = ?, chat_floating_icon_on_off = ? WHERE id = 1");
        $stmt->execute([$chat_messenger_url, $chat_floating_icon_on_off]);
        $success_message = "Messenger settings updated successfully!";
    } catch (Throwable $e) {
        $error_message = "Failed to update Messenger settings: " . $e->getMessage();
    }
}

// Handle Add Keyword Rule
if (isset($_POST['add_bot_rule'])) {
    $keyword = strtolower(trim($_POST['keyword'] ?? ''));
    $reply_text = trim($_POST['reply_text'] ?? '');
    if ($keyword === '' || $reply_text === '') {
        $error_message = "Both keyword and automated response are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO tbl_bot_keyword (keyword, reply_text, status) VALUES (?, ?, 'Active')");
            $stmt->execute([$keyword, $reply_text]);
            $success_message = "Keyword rule for '" . htmlspecialchars($keyword) . "' added successfully!";
        } catch (Throwable $e) {
            $error_message = "Failed to add keyword rule: " . $e->getMessage();
        }
    }
}

// Handle Save WhatsApp Settings
if (isset($_POST['save_whatsapp_settings'])) {
    $chat_whatsapp_url = trim($_POST['chat_whatsapp_url'] ?? '');
    $chat_floating_icon_on_off = (int)($_POST['chat_floating_icon_on_off'] ?? 1);
    $wa_placed = trim($_POST['whatsapp_order_placed_template'] ?? '');
    $wa_shipped = trim($_POST['whatsapp_order_shipped_template'] ?? '');
    $wa_completed = trim($_POST['whatsapp_order_completed_template'] ?? '');
    $wa_auto = (int)($_POST['whatsapp_auto_order_on_off'] ?? 1);

    try {
        $stmt = $pdo->prepare("UPDATE tbl_settings SET 
            chat_whatsapp_url = ?, 
            chat_floating_icon_on_off = ?, 
            whatsapp_order_placed_template = ?, 
            whatsapp_order_shipped_template = ?, 
            whatsapp_order_completed_template = ?, 
            whatsapp_auto_order_on_off = ? 
            WHERE id = 1");
        $stmt->execute([$chat_whatsapp_url, $chat_floating_icon_on_off, $wa_placed, $wa_shipped, $wa_completed, $wa_auto]);
        $success_message = "WhatsApp settings and order templates saved successfully!";
    } catch (Throwable $e) {
        $error_message = "Failed to save WhatsApp settings: " . $e->getMessage();
    }
}

// Notification query message
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'rule_deleted') $success_message = "Keyword rule deleted successfully.";
    if ($_GET['msg'] === 'rule_updated') $success_message = "Keyword rule status updated.";
}

// Fetch current settings from DB
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$chat_messenger_url = $settings['chat_messenger_url'] ?? '';
$chat_whatsapp_url = $settings['chat_whatsapp_url'] ?? '';
$chat_floating_icon_on_off = (int)($settings['chat_floating_icon_on_off'] ?? 1);
$whatsapp_order_placed_template = $settings['whatsapp_order_placed_template'] ?? "Hello {customer_name}, thank you for your order #{order_id} at {shop_name}! Total amount: ৳{order_total}. We will notify you once shipped.";
$whatsapp_order_shipped_template = $settings['whatsapp_order_shipped_template'] ?? "Hello {customer_name}, your order #{order_id} from {shop_name} has been shipped! It will arrive soon.";
$whatsapp_order_completed_template = $settings['whatsapp_order_completed_template'] ?? "Hello {customer_name}, your order #{order_id} has been delivered successfully. Thank you for shopping with {shop_name}!";
$whatsapp_auto_order_on_off = (int)($settings['whatsapp_auto_order_on_off'] ?? 1);

// Fetch bot keyword rules
$bot_rules = [];
try {
    $stmt = $pdo->query("SELECT * FROM tbl_bot_keyword ORDER BY id DESC");
    $bot_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Fetch WhatsApp chat threads
$threads = [];
try {
    $sql = "
        SELECT m1.*
        FROM tbl_whatsapp_messages m1
        JOIN (
            SELECT customer_phone, MAX(id) as max_id
            FROM tbl_whatsapp_messages
            GROUP BY customer_phone
        ) m2 ON m1.id = m2.max_id
        ORDER BY m1.id DESC
    ";
    $threads = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>

<style>
/* WhatsApp Web Style Clean Light Interface */
.wa-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    height: 700px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.wa-sidebar {
    width: 320px;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    background: #ffffff;
}
.wa-sidebar-header {
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.wa-search-box {
    padding: 10px 14px;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}
.wa-thread-list {
    flex: 1;
    overflow-y: auto;
}
.wa-thread-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer;
    transition: background 0.15s ease;
    gap: 12px;
}
.wa-thread-item:hover {
    background: #f8fafc;
}
.wa-thread-item.active {
    background: #f0fdf4;
    border-left: 3px solid #22c55e;
}
.wa-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #25d366;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
}
.wa-thread-info {
    flex: 1;
    min-width: 0;
}
.wa-thread-name {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    justify-content: space-between;
}
.wa-thread-time {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
}
.wa-thread-snippet {
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 2px 0 0;
}

/* Chat Main View */
.wa-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #efeae2;
}
.wa-main-header {
    padding: 10px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.wa-messages-body {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.wa-date-divider {
    align-self: center;
    background: #ffffff;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    margin: 5px 0 10px;
}
.wa-bubble {
    max-width: 70%;
    padding: 8px 12px;
    font-size: 13px;
    line-height: 1.45;
    word-wrap: break-word;
    box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    position: relative;
}
.wa-bubble-in {
    align-self: flex-start;
    background: #ffffff;
    color: #111b21;
    border-radius: 0 8px 8px 8px;
}
.wa-bubble-out {
    align-self: flex-end;
    background: #d9fdd3;
    color: #111b21;
    border-radius: 8px 0 8px 8px;
}
.wa-bubble-meta {
    font-size: 10px;
    color: #667781;
    float: right;
    margin-left: 10px;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 3px;
}
.wa-quick-bar {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 8px 16px;
    display: flex;
    gap: 6px;
    overflow-x: auto;
    white-space: nowrap;
}
.wa-input-bar {
    padding: 12px 16px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.wa-empty-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #64748b;
    padding: 40px;
    text-align: center;
    background: #fafafa;
}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>Messenger &amp; WhatsApp Automation</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible" style="border-radius: 4px;">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible" style="border-radius: 4px;">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs (Light Minimal Design) -->
            <ul class="nav nav-tabs" style="margin-bottom: 20px; font-weight: 600; font-size: 14px;">
                <li class="<?php echo $activeTab === 'whatsapp' ? 'active' : ''; ?>">
                    <a href="marketing.php?tab=whatsapp"><i class="fa fa-whatsapp text-success"></i> WhatsApp Chat &amp; Automation</a>
                </li>
                <li class="<?php echo $activeTab === 'messenger' ? 'active' : ''; ?>">
                    <a href="marketing.php?tab=messenger"><i class="fa fa-commenting text-primary"></i> Messenger Bot</a>
                </li>
            </ul>

            <?php if ($activeTab === 'whatsapp'): ?>
                <!-- Sub-bar for WhatsApp: Chat Console vs Settings -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div class="btn-group">
                        <a href="marketing.php?tab=whatsapp&sub=chat" class="btn btn-sm <?php echo $subTab === 'chat' ? 'btn-primary' : 'btn-default'; ?>" style="font-weight: 600;">
                            <i class="fa fa-comments"></i> WhatsApp Live Chat Console
                        </a>
                        <a href="marketing.php?tab=whatsapp&sub=settings" class="btn btn-sm <?php echo $subTab === 'settings' ? 'btn-primary' : 'btn-default'; ?>" style="font-weight: 600;">
                            <i class="fa fa-sliders"></i> Order Alert Templates &amp; Setup
                        </a>
                    </div>
                    <?php if ($subTab === 'chat'): ?>
                        <button type="button" class="btn btn-success btn-sm" onclick="promptNewChat()" style="font-weight: 600;">
                            <i class="fa fa-plus"></i> New Customer Chat
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($subTab === 'chat'): ?>
                    <!-- ========================================== -->
                    <!-- FULL WHATSAPP CHATTING INTERFACE -->
                    <!-- ========================================== -->
                    <div class="wa-container">
                        <!-- Sidebar: Contacts / Threads -->
                        <div class="wa-sidebar">
                            <div class="wa-sidebar-header">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #25d366; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                                        <i class="fa fa-whatsapp"></i>
                                    </div>
                                    <strong style="color: #1e293b; font-size: 13px;">WhatsApp Chats</strong>
                                </div>
                                <span class="label label-success" style="font-size: 10px; border-radius: 10px;"><i class="fa fa-circle"></i> Connected</span>
                            </div>

                            <div class="wa-search-box">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="waThreadSearch" onkeyup="filterThreads()" class="form-control" placeholder="Search customer or phone..." style="border-radius: 15px;">
                                </div>
                            </div>

                            <div class="wa-thread-list" id="waThreadList">
                                <?php if (empty($threads)): ?>
                                    <div style="padding: 30px 15px; text-align: center; color: #94a3b8; font-size: 13px;">
                                        <i class="fa fa-comments-o fa-2x" style="margin-bottom: 8px;"></i>
                                        <p>No conversations yet.<br>Click "New Customer Chat" to start.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($threads as $idx => $t): ?>
                                        <div class="wa-thread-item <?php echo $idx === 0 ? 'active' : ''; ?>" 
                                             data-phone="<?php echo htmlspecialchars($t['customer_phone']); ?>"
                                             data-name="<?php echo htmlspecialchars($t['customer_name'] ?: 'Customer'); ?>"
                                             onclick="selectThread('<?php echo htmlspecialchars($t['customer_phone']); ?>', '<?php echo htmlspecialchars(addslashes($t['customer_name'] ?: 'Customer')); ?>', this)">
                                            <div class="wa-avatar">
                                                <?php echo strtoupper(substr($t['customer_name'] ?: $t['customer_phone'], 0, 1)); ?>
                                            </div>
                                            <div class="wa-thread-info">
                                                <div class="wa-thread-name">
                                                    <span><?php echo htmlspecialchars($t['customer_name'] ?: $t['customer_phone']); ?></span>
                                                    <span class="wa-thread-time"><?php echo date('h:i A', strtotime($t['created_at'])); ?></span>
                                                </div>
                                                <div class="wa-thread-snippet">
                                                    <?php if ($t['sender_type'] === 'admin'): ?>
                                                        <span style="color: #53bdeb;">✓✓ </span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($t['message_text']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Main Chat Screen -->
                        <div class="wa-main">
                            <!-- Empty placeholder if no threads -->
                            <div id="waEmptyState" class="wa-empty-chat" style="<?php echo empty($threads) ? 'display:flex;' : 'display:none;'; ?>">
                                <div style="width: 70px; height: 70px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 15px;">
                                    <i class="fa fa-whatsapp"></i>
                                </div>
                                <h3 style="font-weight: 700; color: #334155; margin: 0 0 6px;">WhatsApp Chat Console</h3>
                                <p style="font-size: 13px; max-width: 380px;">Select a conversation from the left or start a new direct chat with a customer.</p>
                                <button type="button" class="btn btn-success btn-sm" onclick="promptNewChat()" style="margin-top: 10px;">
                                    <i class="fa fa-plus"></i> Start New Chat
                                </button>
                            </div>

                            <!-- Active Chat Panel -->
                            <div id="waActiveChatPanel" style="<?php echo empty($threads) ? 'display:none;' : 'display:flex; flex-direction:column; height:100%;'; ?>">
                                <!-- Chat Header -->
                                <div class="wa-main-header">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="wa-avatar" id="activeChatAvatar">C</div>
                                        <div>
                                            <h4 id="activeChatName" style="margin: 0; font-size: 14px; font-weight: 700; color: #1e293b;">Customer Name</h4>
                                            <p id="activeChatPhone" style="margin: 2px 0 0; font-size: 12px; color: #64748b;">+8801700000000 • WhatsApp Active</p>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="button" onclick="openWhatsAppWeb()" class="btn btn-default btn-sm" title="Open in official WhatsApp Web" style="border-radius: 6px;">
                                            <i class="fa fa-external-link text-success"></i> WhatsApp Web
                                        </button>
                                        <button type="button" onclick="openWhatsAppDirect()" class="btn btn-default btn-sm" title="Direct wa.me mobile link" style="border-radius: 6px;">
                                            <i class="fa fa-mobile text-primary"></i> wa.me
                                        </button>
                                    </div>
                                </div>

                                <!-- Messages List Area -->
                                <div class="wa-messages-body" id="waMessagesContainer">
                                    <div class="wa-date-divider">TODAY</div>
                                    <!-- Messages rendered dynamically via JS -->
                                </div>

                                <!-- Quick Order Templates Chips -->
                                <div class="wa-quick-bar">
                                    <span style="font-size: 11px; font-weight: 700; color: #64748b; line-height: 24px; margin-right: 4px;">Templates:</span>
                                    <button type="button" onclick="useTemplate('order_confirmed')" class="btn btn-xs btn-default" style="border-radius: 12px;">📦 Order Confirmed</button>
                                    <button type="button" onclick="useTemplate('order_shipped')" class="btn btn-xs btn-default" style="border-radius: 12px;">🚚 Shipped &amp; Tracking</button>
                                    <button type="button" onclick="useTemplate('payment_link')" class="btn btn-xs btn-default" style="border-radius: 12px;">💳 Payment Request</button>
                                    <button type="button" onclick="useTemplate('thank_you')" class="btn btn-xs btn-default" style="border-radius: 12px;">🙏 Thank You</button>
                                </div>

                                <!-- Input Bar -->
                                <div class="wa-input-bar">
                                    <button type="button" class="btn btn-default btn-sm" style="border-radius: 50%; width: 34px; height: 34px; padding: 0; color: #64748b;" title="Emoji">
                                        😊
                                    </button>
                                    <input type="text" id="waMessageInput" class="form-control" placeholder="Type a message..." style="border-radius: 20px; font-size: 13px;" onkeypress="handleKeyPress(event)" autocomplete="off">
                                    <button type="button" onclick="sendMessage()" class="btn btn-success" style="border-radius: 20px; padding: 6px 18px; font-weight: 600;">
                                        <i class="fa fa-paper-plane"></i> Send
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- ========================================== -->
                    <!-- WHATSAPP SETTINGS & TEMPLATES TAB -->
                    <!-- ========================================== -->
                    <div class="row">
                        <div class="col-md-7">
                            <div class="box box-success">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-sliders text-success"></i> WhatsApp Storefront &amp; Order Alerts</h3>
                                </div>
                                <form action="marketing.php?tab=whatsapp&sub=settings" method="post" class="form-horizontal">
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label for="chat_whatsapp_url" class="col-sm-4 control-label">WhatsApp Number / URL</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" name="chat_whatsapp_url" id="chat_whatsapp_url" value="<?php echo htmlspecialchars($chat_whatsapp_url); ?>" placeholder="e.g. 8801700000000 or https://wa.me/8801700000000">
                                                <p class="help-block" style="font-size: 12px; margin-top: 5px;">Link used when storefront visitors click the WhatsApp chat button.</p>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="chat_floating_icon_on_off" class="col-sm-4 control-label">Floating WhatsApp Button</label>
                                            <div class="col-sm-8">
                                                <select name="chat_floating_icon_on_off" id="chat_floating_icon_on_off" class="form-control">
                                                    <option value="1" <?php if ($chat_floating_icon_on_off == 1) echo 'selected'; ?>>Enabled (Show on storefront)</option>
                                                    <option value="0" <?php if ($chat_floating_icon_on_off == 0) echo 'selected'; ?>>Disabled</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="whatsapp_auto_order_on_off" class="col-sm-4 control-label">Auto Order WhatsApp Alerts</label>
                                            <div class="col-sm-8">
                                                <select name="whatsapp_auto_order_on_off" id="whatsapp_auto_order_on_off" class="form-control">
                                                    <option value="1" <?php if ($whatsapp_auto_order_on_off == 1) echo 'selected'; ?>>Enabled (Notify customers automatically)</option>
                                                    <option value="0" <?php if ($whatsapp_auto_order_on_off == 0) echo 'selected'; ?>>Disabled</option>
                                                </select>
                                            </div>
                                        </div>

                                        <hr style="margin: 15px 0;">
                                        <div class="callout callout-info" style="font-size: 12px; margin-bottom: 15px; padding: 10px 15px;">
                                            <strong>Supported Placeholders:</strong> <code>{customer_name}</code>, <code>{order_id}</code>, <code>{order_total}</code>, <code>{payment_method}</code>, <code>{shop_name}</code>
                                        </div>

                                        <div class="form-group">
                                            <label for="whatsapp_order_placed_template" class="col-sm-4 control-label">1. Order Placed Template</label>
                                            <div class="col-sm-8">
                                                <textarea name="whatsapp_order_placed_template" id="whatsapp_order_placed_template" rows="3" class="form-control"><?php echo htmlspecialchars($whatsapp_order_placed_template); ?></textarea>
                                                <p class="help-block" style="font-size: 11px;">Sent when customer places an order.</p>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="whatsapp_order_shipped_template" class="col-sm-4 control-label">2. Order Shipped Template</label>
                                            <div class="col-sm-8">
                                                <textarea name="whatsapp_order_shipped_template" id="whatsapp_order_shipped_template" rows="3" class="form-control"><?php echo htmlspecialchars($whatsapp_order_shipped_template); ?></textarea>
                                                <p class="help-block" style="font-size: 11px;">Sent when order status changes to Shipped.</p>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="whatsapp_order_completed_template" class="col-sm-4 control-label">3. Order Completed Template</label>
                                            <div class="col-sm-8">
                                                <textarea name="whatsapp_order_completed_template" id="whatsapp_order_completed_template" rows="3" class="form-control"><?php echo htmlspecialchars($whatsapp_order_completed_template); ?></textarea>
                                                <p class="help-block" style="font-size: 11px;">Sent when order delivery is completed.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" name="save_whatsapp_settings" class="btn btn-success">
                                            <i class="fa fa-check"></i> Save WhatsApp Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-lightbulb-o text-warning"></i> WhatsApp Cloud API &amp; Webhook</h3>
                                </div>
                                <div class="box-body" style="font-size: 13px; line-height: 1.6; color: #475569;">
                                    <p>Your WhatsApp Live Chat console connects directly to customer numbers via standard WhatsApp protocols.</p>
                                    <ul style="padding-left: 18px; margin-bottom: 15px;">
                                        <li>Instant 1-Click WhatsApp Web sync</li>
                                        <li>Message persistence in your database</li>
                                        <li>Real-time customer conversation history</li>
                                        <li>Dynamic order status and invoice dispatch</li>
                                    </ul>
                                    <a href="marketing.php?tab=whatsapp&sub=chat" class="btn btn-primary btn-block">
                                        <i class="fa fa-comments"></i> Return to Live Chat Console
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- ========================================== -->
                <!-- TAB: MESSENGER BOT -->
                <!-- ========================================== -->
                <div class="row">
                    <!-- Storefront Messenger Config -->
                    <div class="col-md-5">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-sliders"></i> Storefront Messenger Settings</h3>
                            </div>
                            <form action="marketing.php?tab=messenger" method="post" class="form-horizontal">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="chat_messenger_url" class="col-sm-4 control-label">Messenger Link / Username</label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="chat_messenger_url" id="chat_messenger_url" value="<?php echo htmlspecialchars($chat_messenger_url); ?>" placeholder="e.g. https://m.me/yourpage or username">
                                            <p class="help-block" style="font-size: 12px; margin-top: 5px;">Link used when storefront visitors click the Messenger chat button.</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="chat_floating_icon_on_off" class="col-sm-4 control-label">Floating Chat Widget</label>
                                        <div class="col-sm-8">
                                            <select name="chat_floating_icon_on_off" id="chat_floating_icon_on_off" class="form-control">
                                                <option value="1" <?php if ($chat_floating_icon_on_off == 1) echo 'selected'; ?>>Enabled (Show on storefront)</option>
                                                <option value="0" <?php if ($chat_floating_icon_on_off == 0) echo 'selected'; ?>>Disabled</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-4 control-label">Live Support Handover</label>
                                        <div class="col-sm-8" style="padding-top: 7px;">
                                            <a href="live-chat.php" class="btn btn-default btn-sm" target="_blank">
                                                <i class="fa fa-headphones text-success"></i> Open Live Support Console <i class="fa fa-external-link"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit" name="save_messenger_settings" class="btn btn-success">
                                        <i class="fa fa-check"></i> Save Messenger Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Add Rule Box -->
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-plus-circle"></i> Add Keyword Auto-Reply</h3>
                            </div>
                            <form action="marketing.php?tab=messenger" method="post">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="keyword">Trigger Keyword</label>
                                        <input type="text" name="keyword" id="keyword" class="form-control" placeholder="e.g. price, delivery, return, contact" required>
                                        <p class="help-block" style="font-size: 12px;">Triggered when user message contains this keyword.</p>
                                    </div>
                                    <div class="form-group">
                                        <label for="reply_text">Automated Response</label>
                                        <textarea name="reply_text" id="reply_text" rows="3" class="form-control" placeholder="Write response to send to customer..." required></textarea>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit" name="add_bot_rule" class="btn btn-primary">
                                        <i class="fa fa-plus"></i> Add Auto-Reply Rule
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Active Rules Table -->
                    <div class="col-md-7">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-list"></i> Keyword Auto-Reply Rules</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th style="width: 130px;">Keyword</th>
                                            <th>Automated Response</th>
                                            <th style="width: 80px; text-align: center;">Status</th>
                                            <th style="width: 120px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($bot_rules)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted" style="padding: 20px;">
                                                    No keyword rules added yet. Use the form on the left to add your first rule.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($bot_rules as $i => $rule): ?>
                                                <tr>
                                                    <td><?php echo $i + 1; ?></td>
                                                    <td><code><?php echo htmlspecialchars($rule['keyword']); ?></code></td>
                                                    <td><?php echo nl2br(htmlspecialchars($rule['reply_text'])); ?></td>
                                                    <td style="text-align: center;">
                                                        <?php if ($rule['status'] === 'Active'): ?>
                                                            <span class="label label-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="label label-default">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <a href="marketing.php?tab=messenger&action=toggle_rule&rule_id=<?php echo $rule['id']; ?>&current=<?php echo $rule['status']; ?>" class="btn btn-default btn-xs" title="Toggle status">
                                                            <i class="fa fa-power-off <?php echo $rule['status'] === 'Active' ? 'text-green' : 'text-muted'; ?>"></i>
                                                        </a>
                                                        <a href="marketing.php?tab=messenger&action=delete_rule&rule_id=<?php echo $rule['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Are you sure you want to delete this rule?');" title="Delete rule">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<!-- JavaScript for WhatsApp Chatting Interface -->
<script>
var currentPhone = '<?php echo !empty($threads) ? htmlspecialchars($threads[0]['customer_phone']) : ''; ?>';
var currentName = '<?php echo !empty($threads) ? htmlspecialchars(addslashes($threads[0]['customer_name'] ?: 'Customer')) : ''; ?>';

document.addEventListener('DOMContentLoaded', function() {
    if (currentPhone) {
        loadMessages(currentPhone, currentName);
    }
});

function selectThread(phone, name, element) {
    currentPhone = phone;
    currentName = name;
    
    // Highlight active thread
    document.querySelectorAll('.wa-thread-item').forEach(function(el) {
        el.classList.remove('active');
    });
    if (element) element.classList.add('active');

    // Update active header
    document.getElementById('activeChatName').textContent = name || phone;
    document.getElementById('activeChatPhone').textContent = phone + ' • WhatsApp Active';
    document.getElementById('activeChatAvatar').textContent = (name || phone).charAt(0).toUpperCase();

    document.getElementById('waEmptyState').style.display = 'none';
    document.getElementById('waActiveChatPanel').style.display = 'flex';

    loadMessages(phone, name);
}

function loadMessages(phone, name) {
    var container = document.getElementById('waMessagesContainer');
    container.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fa fa-spinner fa-spin"></i> Loading chat...</div>';

    fetch('marketing.php?ajax=get_messages&phone=' + encodeURIComponent(phone))
        .then(function(res) { return res.json(); })
        .then(function(data) {
            container.innerHTML = '<div class="wa-date-divider">TODAY</div>';
            if (data.status === 'success' && data.messages.length > 0) {
                data.messages.forEach(function(msg) {
                    appendMessageBubble(msg);
                });
                scrollChatToBottom();
            } else {
                container.innerHTML += '<div style="text-align:center; color:#94a3b8; font-size:12px; margin-top:20px;">No message history with this customer yet. Send a greeting below!</div>';
            }
        })
        .catch(function(err) {
            container.innerHTML = '<div style="text-align:center; color:#ef4444; padding:20px;">Failed to load messages.</div>';
        });
}

function appendMessageBubble(msg) {
    var container = document.getElementById('waMessagesContainer');
    var isOut = (msg.sender_type === 'admin');
    
    var bubble = document.createElement('div');
    bubble.className = 'wa-bubble ' + (isOut ? 'wa-bubble-out' : 'wa-bubble-in');

    var timeStr = 'Just now';
    if (msg.created_at) {
        var d = new Date(msg.created_at);
        if (!isNaN(d)) {
            timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }

    var tickHtml = isOut ? '<span style="color: #53bdeb; margin-left: 4px;">✓✓</span>' : '';

    bubble.innerHTML = escapeHtml(msg.message_text) + 
        '<div class="wa-bubble-meta">' + timeStr + tickHtml + '</div>';

    container.appendChild(bubble);
}

function sendMessage() {
    var input = document.getElementById('waMessageInput');
    var text = input.value.trim();
    if (!text || !currentPhone) return;

    var fd = new FormData();
    fd.append('phone', currentPhone);
    fd.append('customer_name', currentName);
    fd.append('message_text', text);

    // Optimistically render
    appendMessageBubble({
        sender_type: 'admin',
        message_text: text,
        created_at: new Date().toISOString()
    });
    scrollChatToBottom();
    input.value = '';

    fetch('marketing.php?ajax=send_message', {
        method: 'POST',
        body: fd
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            // Update sidebar snippet
            var activeItem = document.querySelector('.wa-thread-item.active .wa-thread-snippet');
            if (activeItem) {
                activeItem.innerHTML = '<span style="color:#53bdeb;">✓✓ </span>' + escapeHtml(text);
            }
        }
    });
}

function handleKeyPress(e) {
    if (e.key === 'Enter') {
        sendMessage();
    }
}

function scrollChatToBottom() {
    var container = document.getElementById('waMessagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

function openWhatsAppWeb() {
    if (!currentPhone) return;
    var clean = currentPhone.replace(/[^0-9]/g, '');
    window.open('https://web.whatsapp.com/send?phone=' + clean, '_blank');
}

function openWhatsAppDirect() {
    if (!currentPhone) return;
    var clean = currentPhone.replace(/[^0-9]/g, '');
    window.open('https://wa.me/' + clean, '_blank');
}

function useTemplate(type) {
    var input = document.getElementById('waMessageInput');
    var name = currentName || 'Customer';
    var text = '';

    if (type === 'order_confirmed') {
        text = 'Hello ' + name + ', your order has been confirmed! We are preparing it for delivery.';
    } else if (type === 'order_shipped') {
        text = 'Hello ' + name + ', your order has been handed over to courier. Track delivery via our website.';
    } else if (type === 'payment_link') {
        text = 'Dear ' + name + ', please complete your payment via SwapnoPay to proceed with order dispatch.';
    } else if (type === 'thank_you') {
        text = 'Thank you for shopping with us, ' + name + '! We hope you enjoy your purchase.';
    }

    input.value = text;
    input.focus();
}

function promptNewChat() {
    var phone = prompt('Enter customer WhatsApp phone number (e.g. 01712345678):');
    if (!phone) return;
    var name = prompt('Enter customer name (optional):') || 'Customer';
    
    var clean = phone.replace(/[^0-9]/g, '');
    if (clean.startsWith('01') && clean.length === 11) {
        clean = '88' + clean;
    }

    currentPhone = clean;
    currentName = name;

    // Check if item exists in sidebar, otherwise prepend
    var list = document.getElementById('waThreadList');
    var existing = list.querySelector('[data-phone="' + clean + '"]');
    if (existing) {
        selectThread(clean, name, existing);
    } else {
        var div = document.createElement('div');
        div.className = 'wa-thread-item active';
        div.setAttribute('data-phone', clean);
        div.setAttribute('data-name', name);
        div.onclick = function() { selectThread(clean, name, div); };
        div.innerHTML = 
            '<div class="wa-avatar">' + name.charAt(0).toUpperCase() + '</div>' +
            '<div class="wa-thread-info">' +
                '<div class="wa-thread-name">' +
                    '<span>' + escapeHtml(name) + '</span>' +
                    '<span class="wa-thread-time">Just now</span>' +
                '</div>' +
                '<div class="wa-thread-snippet">New chat started</div>' +
            '</div>';
        
        list.insertBefore(div, list.firstChild);
        selectThread(clean, name, div);
    }
}

function filterThreads() {
    var query = document.getElementById('waThreadSearch').value.toLowerCase();
    document.querySelectorAll('.wa-thread-item').forEach(function(item) {
        var name = (item.getAttribute('data-name') || '').toLowerCase();
        var phone = (item.getAttribute('data-phone') || '').toLowerCase();
        if (name.includes(query) || phone.includes(query)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
