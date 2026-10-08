<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/notifications.php';
require_once('header.php');

$merchantId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';

// Handle Save Firebase Settings POST
$settingsUpdated = false;
$settingsError = '';
$detectedJsonType = '';
if (isset($_POST['save_firebase_settings'])) {
    try {
        // 1. Process uploaded JSON configuration file if supplied
        $jsonConfig = [];
        if (!empty($_FILES['firebase_json_file']['tmp_name']) && is_uploaded_file($_FILES['firebase_json_file']['tmp_name'])) {
            $rawContent = (string)@file_get_contents($_FILES['firebase_json_file']['tmp_name']);
            if ($rawContent !== '') {
                $jsonConfig = parseFirebaseConfigFile($rawContent);
            }
        } elseif (!empty($_POST['firebase_json_raw'])) {
            $jsonConfig = parseFirebaseConfigFile((string)$_POST['firebase_json_raw']);
        }

        if (!empty($jsonConfig['_detected_type'])) {
            $detectedJsonType = $jsonConfig['_detected_type'];
            unset($jsonConfig['_detected_type']);
        }

        // 2. Fetch current settings to preserve existing credentials if not overwritten
        $stmtCurr = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1");
        $curr = $stmtCurr ? ($stmtCurr->fetch(PDO::FETCH_ASSOC) ?: []) : [];

        $fcmServerKey = trim((string)($_POST['fcm_server_key'] ?? $jsonConfig['fcm_server_key'] ?? $curr['fcm_server_key'] ?? ''));
        $firebaseApiKey = trim((string)($jsonConfig['firebase_api_key'] ?? $_POST['firebase_api_key'] ?? $curr['firebase_api_key'] ?? ''));
        $firebaseAuthDomain = trim((string)($jsonConfig['firebase_auth_domain'] ?? $_POST['firebase_auth_domain'] ?? $curr['firebase_auth_domain'] ?? ''));
        $firebaseProjectId = trim((string)($jsonConfig['firebase_project_id'] ?? $_POST['firebase_project_id'] ?? $curr['firebase_project_id'] ?? ''));
        $firebaseStorageBucket = trim((string)($jsonConfig['firebase_storage_bucket'] ?? $_POST['firebase_storage_bucket'] ?? $curr['firebase_storage_bucket'] ?? ''));
        $firebaseSenderId = trim((string)($jsonConfig['firebase_messaging_sender_id'] ?? $_POST['firebase_messaging_sender_id'] ?? $curr['firebase_messaging_sender_id'] ?? ''));
        $firebaseAppId = trim((string)($jsonConfig['firebase_app_id'] ?? $_POST['firebase_app_id'] ?? $curr['firebase_app_id'] ?? ''));
        $firebaseVapidKey = trim((string)($jsonConfig['firebase_vapid_key'] ?? $_POST['firebase_vapid_key'] ?? $curr['firebase_vapid_key'] ?? ''));
        $firebaseServiceAccount = trim((string)($jsonConfig['firebase_service_account_json'] ?? $_POST['firebase_service_account_json'] ?? $curr['firebase_service_account_json'] ?? ''));

        $stmt = $pdo->prepare("
            UPDATE tbl_settings SET 
                fcm_server_key = ?,
                firebase_api_key = ?,
                firebase_auth_domain = ?,
                firebase_project_id = ?,
                firebase_storage_bucket = ?,
                firebase_messaging_sender_id = ?,
                firebase_app_id = ?,
                firebase_vapid_key = ?,
                firebase_service_account_json = ?
            WHERE id = 1
        ");
        $stmt->execute([
            $fcmServerKey,
            $firebaseApiKey,
            $firebaseAuthDomain,
            $firebaseProjectId,
            $firebaseStorageBucket,
            $firebaseSenderId,
            $firebaseAppId,
            $firebaseVapidKey,
            $firebaseServiceAccount
        ]);

        // Invalidate settings cache
        $settingsCacheFile = __DIR__ . '/inc/cache_settings.json';
        @unlink($settingsCacheFile);

        $settingsUpdated = true;
    } catch (Throwable $e) {
        $settingsError = $e->getMessage();
    }
}

// Fetch current settings safely
$currentSettings = [];
try {
    $stmtSettings = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1");
    if ($stmtSettings) {
        $currentSettings = $stmtSettings->fetch(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    error_log("Failed to fetch settings: " . $e->getMessage());
}

// Fetch statistics safely
$totalNotifications = 0;
$totalDevices = 0;
$totalCustomers = 0;
try {
    $totalNotifications = (int)$pdo->query("SELECT COUNT(*) FROM tbl_notifications WHERE merchant_id = " . $pdo->quote($merchantId))->fetchColumn();
    $totalDevices = (int)$pdo->query("SELECT COUNT(*) FROM tbl_fcm_tokens WHERE merchant_id = " . $pdo->quote($merchantId))->fetchColumn();
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM tbl_customer WHERE cust_status = '1'")->fetchColumn();
} catch (Throwable $e) {
    error_log("Failed to fetch notification stats: " . $e->getMessage());
}

// Fetch notifications list safely
$notificationList = [];
try {
    $stmtList = $pdo->prepare("
        SELECT * FROM tbl_notifications 
        WHERE merchant_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmtList->execute([$merchantId]);
    $notificationList = $stmtList->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log("Failed to fetch notification list: " . $e->getMessage());
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1><i class="fa fa-bullhorn text-yellow"></i> Broadcast Push Notifications & Alerts</h1>
    </div>
    <div class="content-header-right">
        <a href="index.php" class="btn btn-primary btn-sm"><i class="fa fa-dashboard"></i> Back to Dashboard</a>
    </div>
</section>

<section class="content">

    <!-- Top Statistics Cards -->
    <div class="row">
        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="info-box bg-yellow">
                <span class="info-box-icon"><i class="ionicons ion-android-notifications"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Notifications Sent</span>
                    <span class="info-box-number"><?php echo number_format($totalNotifications); ?></span>
                    <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                    <span class="progress-description">In-App & Push Notifications</span>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="info-box bg-green">
                <span class="info-box-icon"><i class="ionicons ion-android-phone-portrait"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Registered App Devices (FCM)</span>
                    <span class="info-box-number"><?php echo number_format($totalDevices); ?></span>
                    <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                    <span class="progress-description">Subscribed to Push Alerts</span>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-sm-6 col-xs-12">
            <div class="info-box bg-aqua">
                <span class="info-box-icon"><i class="ionicons ion-ios-people"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active Customer Reach</span>
                    <span class="info-box-number"><?php echo number_format($totalCustomers); ?></span>
                    <div class="progress"><div class="progress-bar" style="width: 100%"></div></div>
                    <span class="progress-description">Registered Store Accounts</span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($settingsUpdated): ?>
        <div class="alert alert-success alert-dismissible" style="border-radius: 8px;">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h4><i class="icon fa fa-check"></i> Firebase Configuration Updated Successfully!</h4>
            <?php if (!empty($detectedJsonType)): ?>
                Configuration parameters were automatically extracted from <strong><?php echo htmlspecialchars($detectedJsonType); ?></strong> and saved to database.
            <?php else: ?>
                Firebase Cloud Messaging credentials updated successfully.
            <?php endif; ?>
        </div>
    <?php elseif (!empty($settingsError)): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="icon fa fa-ban"></i> Error saving settings: <?php echo htmlspecialchars($settingsError); ?>
        </div>
    <?php endif; ?>

    <!-- Custom Tabs -->
    <div class="nav-tabs-custom" style="border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <ul class="nav nav-tabs">
            <li class="active">
                <a href="#tab_compose" data-toggle="tab">
                    <i class="fa fa-paper-plane text-yellow"></i> <strong>Compose & Broadcast Push</strong>
                </a>
            </li>
            <li>
                <a href="#tab_history" data-toggle="tab">
                    <i class="fa fa-history text-aqua"></i> <strong>Notification History & Logs</strong>
                </a>
            </li>
            <li>
                <a href="#tab_settings" data-toggle="tab">
                    <i class="fa fa-cog text-green"></i> <strong>Firebase FCM Configuration</strong>
                </a>
            </li>
        </ul>

        <div class="tab-content" style="padding: 24px;">

            <!-- ========================================== -->
            <!-- TAB 1: COMPOSE & BROADCAST                 -->
            <!-- ========================================== -->
            <div class="tab-pane active" id="tab_compose">
                <div class="row">
                    <!-- Composer Form -->
                    <div class="col-md-7">
                        <div id="fullBroadcastAlert" style="display:none;" class="alert"></div>

                        <form id="fullBroadcastForm">
                            <div class="form-group">
                                <label style="font-size: 14px; font-weight: 700;">Notification Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control input-lg" id="fullNotifTitle" name="title" placeholder="e.g. 🔥 Weekend Mega Deal: Flat 30% Off on all Products!" required style="border-radius: 6px;">
                            </div>

                            <div class="form-group">
                                <label style="font-size: 14px; font-weight: 700;">Notification Body / Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="fullNotifBody" name="body" rows="4" placeholder="e.g. Upgrade your lifestyle with our top picks this weekend. Use code WEEKEND30 at checkout. Tap to start shopping!" required style="border-radius: 6px; resize: vertical;"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label style="font-size: 13px; font-weight: 600;">Category / Type</label>
                                        <select class="form-control" id="fullNotifType" name="type" style="border-radius: 6px;">
                                            <option value="broadcast" selected>📢 Store Announcement / Notice</option>
                                            <option value="promo">⚡ Flash Deal & Discount Offer</option>
                                            <option value="order">📦 Order Tracking & Fulfillment</option>
                                            <option value="call">📞 Instant Store Call (Rings Phone Full-Screen Even Locked)</option>
                                            <option value="system">🛡️ Security & System Alert</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label style="font-size: 13px; font-weight: 600;">Target Audience</label>
                                        <select class="form-control" id="fullNotifAudience" name="target_audience" style="border-radius: 6px;" onchange="toggleFullTargetAudience(this.value)">
                                            <option value="all" selected>All App Users & Devices (Global)</option>
                                            <option value="specific">Specific Customer (Email / ID)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" id="fullSpecificCustWrap" style="display: none;">
                                <label style="font-size: 13px; font-weight: 600;">Customer Email or ID</label>
                                <input type="text" class="form-control" id="fullCustTarget" name="customer_target" placeholder="customer@example.com or Customer ID" style="border-radius: 6px;">
                            </div>

                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600;">Action Link / Destination URL</label>
                                <div class="input-group">
                                    <span class="input-group-addon" style="background: #f4f6f9; font-size: 12px;"><?php echo BASE_URL; ?></span>
                                    <input type="text" class="form-control" id="fullNotifActionUrl" name="action_url" placeholder="deals.php or product.php?id=12" style="border-radius: 0 6px 6px 0;">
                                </div>
                                <small class="text-muted">When user taps the notification on their phone/browser, they will immediately be directed to this page.</small>
                            </div>

                            <div style="margin-top: 25px; display: flex; gap: 12px;">
                                <button type="button" class="btn btn-warning btn-lg" id="btnSendFullBroadcast" onclick="submitFullBroadcast()" style="font-weight: 700; border-radius: 6px; padding: 12px 28px; box-shadow: 0 2px 6px rgba(243, 156, 18, 0.4);">
                                    <i class="fa fa-paper-plane" style="margin-right: 6px;"></i> Send Push Broadcast Now
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Smartphone Mockup Preview -->
                    <div class="col-md-5">
                        <div style="background: #eef2f6; padding: 30px 20px; border-radius: 12px; text-align: center;">
                            <h4 style="font-weight: 700; margin-top: 0; color: #1e293b; margin-bottom: 20px;">
                                <i class="fa fa-mobile text-primary" style="font-size: 22px; margin-right: 6px;"></i>
                                Smartphone Lockscreen Push Preview
                            </h4>

                            <!-- Phone Shell Mockup -->
                            <div style="background: #0f172a; border-radius: 28px; padding: 22px 14px 28px 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); max-width: 320px; margin: 0 auto; text-align: left; color: #fff; border: 4px solid #334155;">
                                <!-- Phone Notch & Status Bar -->
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; font-size: 11px; color: #94a3b8; padding: 0 6px;">
                                    <span><?php echo date('h:i A'); ?></span>
                                    <div style="width: 60px; height: 12px; background: #000; border-radius: 10px;"></div>
                                    <span><i class="fa fa-wifi"></i> 100%</span>
                                </div>

                                <!-- Push Notification Banner Card -->
                                <div style="background: rgba(255, 255, 255, 0.95); color: #0f172a; border-radius: 14px; padding: 12px; box-shadow: 0 8px 18px rgba(0,0,0,0.2); backdrop-filter: blur(10px);">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <div style="width: 20px; height: 20px; background: #fab802; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #000; font-weight: bold;">
                                                <i class="fa fa-shopping-bag"></i>
                                            </div>
                                            <span style="font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;">SwapnoPay Store</span>
                                        </div>
                                        <span style="font-size: 10px; color: #94a3b8;">Just now</span>
                                    </div>
                                    <div id="fullMockupTitle" style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px;">
                                        🔥 Weekend Mega Deal: Flat 30% Off on all Products!
                                    </div>
                                    <div id="fullMockupBody" style="font-size: 11.5px; color: #475569; line-height: 1.35;">
                                        Upgrade your lifestyle with our top picks this weekend. Use code WEEKEND30 at checkout. Tap to start shopping!
                                    </div>
                                </div>

                                <div style="text-align: center; margin-top: 30px; font-size: 26px; color: #64748b;">
                                    <i class="fa fa-fingerprint"></i>
                                </div>
                            </div>
                            <div style="margin-top: 15px; font-size: 12px; color: #64748b;">
                                Works natively on Android WebView, PWA, Chrome, and Safari!
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TAB 2: NOTIFICATION HISTORY & LOGS         -->
            <!-- ========================================== -->
            <div class="tab-pane" id="tab_history">
                <div class="table-responsive">
                    <table id="tblBroadcastHistory" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Category</th>
                                <th>Target</th>
                                <th>Action URL</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($notificationList)): ?>
                                <?php $idx = 1; foreach ($notificationList as $notifItem): ?>
                                    <tr>
                                        <td><?php echo $idx++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($notifItem['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(mb_strimwidth($notifItem['body'], 0, 80, '...')); ?></td>
                                        <td>
                                            <span class="label label-<?php 
                                                echo $notifItem['type'] === 'order' ? 'primary' : 
                                                    ($notifItem['type'] === 'promo' ? 'warning' : 
                                                    ($notifItem['type'] === 'system' ? 'danger' : 'success')); 
                                            ?>">
                                                <?php echo htmlspecialchars($notifItem['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (empty($notifItem['customer_id'])): ?>
                                                <span class="label label-default"><i class="fa fa-globe"></i> All Devices</span>
                                            <?php else: ?>
                                                <span class="label label-info"><i class="fa fa-user"></i> Customer #<?php echo $notifItem['customer_id']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($notifItem['action_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($notifItem['action_url']); ?>" target="_blank" class="btn btn-xs btn-default">
                                                    <i class="fa fa-external-link"></i> Link
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted" style="white-space: nowrap;">
                                            <?php echo date('M d, Y h:i A', strtotime($notifItem['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted" style="padding: 20px;">No notifications found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TAB 3: FIREBASE FCM CONFIGURATION          -->
            <!-- ========================================== -->
            <div class="tab-pane" id="tab_settings">
                <div class="row">
                    <div class="col-md-8">

                        <!-- 1-Click Firebase JSON Auto-Setup Card -->
                        <div class="box box-solid" style="border-radius: 12px; border: 1.5px solid #93c5fd; background: #f0f9ff; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(2, 132, 199, 0.08);">
                            <div class="box-body" style="padding: 24px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                                    <h4 style="margin: 0; font-weight: 800; color: #0369a1; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-magic text-yellow" style="font-size: 20px;"></i> 1-Click Firebase JSON Auto-Setup
                                    </h4>
                                    <span class="label label-primary" style="font-size: 11px; padding: 5px 10px; border-radius: 6px;">
                                        <i class="fa fa-bolt"></i> Auto-Detects All Credentials
                                    </span>
                                </div>
                                
                                <p style="color: #475569; font-size: 13.5px; line-height: 1.5; margin-bottom: 18px;">
                                    Upload or drag & drop your Firebase config file (<code>google-services.json</code>, <code>serviceAccountKey.json</code>, or Web JSON). All Project IDs, API Keys, App IDs, and Sender IDs will be detected and configured automatically!
                                </p>

                                <!-- Drag & Drop Zone -->
                                <div id="firebaseJsonDropzone" style="border: 2px dashed #0284c7; background: #ffffff; border-radius: 12px; padding: 26px 18px; text-align: center; cursor: pointer; transition: all 0.25s ease;"
                                     ondragover="handleJsonDragOver(event)" ondragleave="handleJsonDragLeave(event)" ondrop="handleJsonDrop(event)" onclick="document.getElementById('firebaseJsonFileInput').click()">
                                    <div style="width: 52px; height: 52px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 10px;">
                                        <i class="fa fa-cloud-upload"></i>
                                    </div>
                                    <div style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                        Click to browse or Drag &amp; Drop your Firebase <span class="text-primary">.json</span> file here
                                    </div>
                                    <div style="font-size: 12.5px; color: #64748b;">
                                        Supports <code>google-services.json</code> (Android), Service Account JSON, or Web app config
                                    </div>
                                    <div style="margin-top: 14px;">
                                        <button type="button" class="btn btn-primary btn-sm" style="border-radius: 6px; font-weight: 700; padding: 6px 18px;">
                                            <i class="fa fa-folder-open-o"></i> Select .json File
                                        </button>
                                    </div>
                                </div>

                                <!-- Live Extraction Feedback Banner -->
                                <div id="jsonExtractionFeedback" style="display: none; margin-top: 16px; border-radius: 10px; padding: 16px; border: 1.5px solid #86efac; background: #f0fdf4;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                                        <div style="display: flex; align-items: center; gap: 8px; color: #166534; font-weight: 700; font-size: 14px;">
                                            <i class="fa fa-check-circle" style="font-size: 18px; color: #16a34a;"></i>
                                            <span id="jsonDetectedTypeTitle">Firebase Configuration Detected!</span>
                                        </div>
                                        <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('firebaseSettingsForm').submit();" style="font-weight: 700; border-radius: 6px; box-shadow: 0 2px 6px rgba(22, 163, 74, 0.3);">
                                            <i class="fa fa-save"></i> Apply & Save Configuration Now
                                        </button>
                                    </div>
                                    <div id="jsonExtractedItems" style="font-size: 12.5px; color: #15803d; line-height: 1.6;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Configuration / Review Form -->
                        <form method="POST" action="" enctype="multipart/form-data" id="firebaseSettingsForm">
                            <input type="hidden" name="save_firebase_settings" value="1">
                            <input type="hidden" name="firebase_json_raw" id="firebaseJsonRaw">
                            <input type="file" id="firebaseJsonFileInput" name="firebase_json_file" accept=".json,application/json" style="display: none;" onchange="handleJsonFileSelect(this.files)">

                            <div class="box box-default" style="border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
                                <div class="box-header with-border" style="padding: 16px 20px;">
                                    <h3 class="box-title" style="font-size: 15px; font-weight: 700; color: #0f172a;">
                                        <i class="fa fa-sliders text-aqua"></i> Credential Parameters (Auto-Filled or Manual Edit)
                                    </h3>
                                </div>
                                <div class="box-body" style="padding: 20px;">

                                    <div class="form-group">
                                        <label style="font-weight: 700;">FCM Server Key (Legacy HTTP Server Key)</label>
                                        <input type="password" class="form-control" name="fcm_server_key" id="firebase_fcm_server_key" value="<?php echo htmlspecialchars($currentSettings['fcm_server_key'] ?? ''); ?>" placeholder="AAAA... (found in Firebase Console > Project Settings > Cloud Messaging > Cloud Messaging API)" style="border-radius: 6px;">
                                        <small class="text-muted">Used by server to dispatch push alerts to customer devices and browsers.</small>
                                    </div>

                                    <hr style="margin: 20px 0;">

                                    <h4 style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 14px;">
                                        <i class="fa fa-globe text-primary"></i> Client & Web App Configuration
                                    </h4>

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label style="font-weight: 600;">Firebase Project ID (projectId)</label>
                                                <input type="text" class="form-control" name="firebase_project_id" id="firebase_project_id" value="<?php echo htmlspecialchars($currentSettings['firebase_project_id'] ?? ''); ?>" placeholder="my-store-12345" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label style="font-weight: 600;">Messaging Sender ID (messagingSenderId)</label>
                                                <input type="text" class="form-control" name="firebase_messaging_sender_id" id="firebase_messaging_sender_id" value="<?php echo htmlspecialchars($currentSettings['firebase_messaging_sender_id'] ?? ''); ?>" placeholder="1029384756" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label style="font-weight: 600;">Firebase Web API Key (apiKey)</label>
                                                <input type="text" class="form-control" name="firebase_api_key" id="firebase_api_key" value="<?php echo htmlspecialchars($currentSettings['firebase_api_key'] ?? ''); ?>" placeholder="AIzaSy..." style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label style="font-weight: 600;">Firebase App ID (appId)</label>
                                                <input type="text" class="form-control" name="firebase_app_id" id="firebase_app_id" value="<?php echo htmlspecialchars($currentSettings['firebase_app_id'] ?? ''); ?>" placeholder="1:1029384756:web:abcd1234" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label style="font-weight: 600;">Auth Domain (authDomain)</label>
                                                <input type="text" class="form-control" name="firebase_auth_domain" id="firebase_auth_domain" value="<?php echo htmlspecialchars($currentSettings['firebase_auth_domain'] ?? ''); ?>" placeholder="my-store.firebaseapp.com" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label style="font-weight: 600;">Storage Bucket (storageBucket)</label>
                                                <input type="text" class="form-control" name="firebase_storage_bucket" id="firebase_storage_bucket" value="<?php echo htmlspecialchars($currentSettings['firebase_storage_bucket'] ?? ''); ?>" placeholder="my-store.appspot.com" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label style="font-weight: 600;">Web Push VAPID Key</label>
                                        <textarea class="form-control" name="firebase_vapid_key" id="firebase_vapid_key" rows="2" placeholder="BOn0f9... (found under Cloud Messaging > Web configuration > Web Push certificates)" style="border-radius: 6px;"><?php echo htmlspecialchars($currentSettings['firebase_vapid_key'] ?? ''); ?></textarea>
                                    </div>

                                    <input type="hidden" name="firebase_service_account_json" id="firebase_service_account_json" value="<?php echo htmlspecialchars($currentSettings['firebase_service_account_json'] ?? ''); ?>">

                                    <div style="margin-top: 25px;">
                                        <button type="submit" class="btn btn-success btn-lg" style="font-weight: 700; border-radius: 6px; padding: 11px 28px; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);">
                                            <i class="fa fa-save" style="margin-right: 6px;"></i> Save Firebase Configuration
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Right Column: How to Get Firebase JSON Guide -->
                    <div class="col-md-4">
                        <div class="box box-solid box-default" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                            <div class="box-header with-border" style="padding: 16px 20px;">
                                <h4 class="box-title" style="font-size: 14.5px; font-weight: 800; color: #0f172a;">
                                    <i class="fa fa-question-circle text-primary"></i> Where to get your .json file?
                                </h4>
                            </div>
                            <div class="box-body" style="padding: 20px; font-size: 13px; color: #334155; line-height: 1.6;">
                                <div style="margin-bottom: 16px;">
                                    <strong style="color: #0f172a; display: block; margin-bottom: 4px;">
                                        Option 1: google-services.json (Easiest)
                                    </strong>
                                    <ol style="padding-left: 20px; margin: 0; color: #475569;">
                                        <li>Open <a href="https://console.firebase.google.com/" target="_blank" style="font-weight: 600; text-decoration: underline;">Firebase Console <i class="fa fa-external-link"></i></a></li>
                                        <li>Click <strong>Project Settings ⚙️</strong> &gt; <strong>General</strong></li>
                                        <li>Under <strong>Your Apps</strong>, click your Android App</li>
                                        <li>Click <strong>Download google-services.json</strong></li>
                                        <li>Drop the file into the upload box on the left!</li>
                                    </ol>
                                </div>

                                <div style="margin-bottom: 16px;">
                                    <strong style="color: #0f172a; display: block; margin-bottom: 4px;">
                                        Option 2: Service Account Key
                                    </strong>
                                    <p style="margin: 0; color: #475569;">
                                        In Firebase Console &gt; <strong>Project Settings</strong> &gt; <strong>Service accounts</strong> &gt; Click <strong>Generate new private key</strong> and upload the downloaded <code>.json</code> file.
                                    </p>
                                </div>

                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                                    <strong style="color: #0284c7; display: block; margin-bottom: 4px;">
                                        <i class="fa fa-key"></i> FCM Legacy Server Key
                                    </strong>
                                    <p style="margin: 0; font-size: 12px; color: #64748b;">
                                        Go to <strong>Project Settings</strong> &gt; <strong>Cloud Messaging</strong>. Under "Cloud Messaging API (Legacy)", copy the Server Key if enabled.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</section>

<script>
// Sync Live Mockup
document.getElementById('fullNotifTitle').addEventListener('input', function(e) {
    document.getElementById('fullMockupTitle').textContent = e.target.value.trim() || '🔥 Weekend Mega Deal: Flat 30% Off on all Products!';
});
document.getElementById('fullNotifBody').addEventListener('input', function(e) {
    document.getElementById('fullMockupBody').textContent = e.target.value.trim() || 'Upgrade your lifestyle with our top picks this weekend. Use code WEEKEND30 at checkout. Tap to start shopping!';
});

function toggleFullTargetAudience(val) {
    var wrap = document.getElementById('fullSpecificCustWrap');
    wrap.style.display = (val === 'specific') ? 'block' : 'none';
}

function submitFullBroadcast() {
    var title = document.getElementById('fullNotifTitle').value.trim();
    var body = document.getElementById('fullNotifBody').value.trim();
    var type = document.getElementById('fullNotifType').value;
    var audience = document.getElementById('fullNotifAudience').value;
    var custTarget = document.getElementById('fullCustTarget').value.trim();
    var actionUrl = document.getElementById('fullNotifActionUrl').value.trim();
    var alertBox = document.getElementById('fullBroadcastAlert');
    var btn = document.getElementById('btnSendFullBroadcast');

    if (!title || !body) {
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = '<i class="fa fa-exclamation-circle"></i> Please fill in both the Title and Message Body.';
        alertBox.style.display = 'block';
        return;
    }

    if (audience === 'specific' && !custTarget) {
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = '<i class="fa fa-exclamation-circle"></i> Please provide a customer email or customer ID.';
        alertBox.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Broadcasting to devices...';
    alertBox.style.display = 'none';

    fetch('broadcast-ajax.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>'
        },
        body: JSON.stringify({
            _csrf: '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>',
            title: title,
            body: body,
            type: type,
            target_audience: audience,
            customer_target: custTarget,
            action_url: actionUrl
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-paper-plane"></i> Send Push Broadcast Now';
        if (data.success) {
            alertBox.className = 'alert alert-success';
            var msg = '<i class="fa fa-check-circle"></i> ' + data.message;
            if (data.details && data.details.push_message) {
                msg += '<br><small>' + data.details.push_message + '</small>';
            }
            alertBox.innerHTML = msg;
            alertBox.style.display = 'block';

            // Reset form
            document.getElementById('fullNotifTitle').value = '';
            document.getElementById('fullNotifBody').value = '';
            document.getElementById('fullNotifActionUrl').value = '';

            setTimeout(function() {
                location.reload();
            }, 1800);
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ' + (data.error || 'Failed to broadcast notification.');
            alertBox.style.display = 'block';
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-paper-plane"></i> Send Push Broadcast Now';
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Network error: ' + err.message;
        alertBox.style.display = 'block';
    });
}

// ==========================================
// 1-Click Firebase JSON Auto-Setup Handlers
// ==========================================
function handleJsonDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    var dropzone = document.getElementById('firebaseJsonDropzone');
    if (dropzone) {
        dropzone.style.borderColor = '#2563eb';
        dropzone.style.background = '#eff6ff';
    }
}

function handleJsonDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    var dropzone = document.getElementById('firebaseJsonDropzone');
    if (dropzone) {
        dropzone.style.borderColor = '#0284c7';
        dropzone.style.background = '#ffffff';
    }
}

function handleJsonDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleJsonDragLeave(e);
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        handleJsonFileSelect(e.dataTransfer.files);
    }
}

function handleJsonFileSelect(files) {
    if (!files || files.length === 0) return;
    var file = files[0];
    if (!file.name.toLowerCase().endsWith('.json') && file.type !== 'application/json') {
        alert('Please select a valid .json file (e.g. google-services.json or serviceAccountKey.json)');
        return;
    }

    var reader = new FileReader();
    reader.onload = function(evt) {
        try {
            var rawText = evt.target.result;
            var data = JSON.parse(rawText);
            document.getElementById('firebaseJsonRaw').value = rawText;

            var extracted = {};
            var detectedType = 'Firebase Web Configuration';

            // 1. google-services.json (Android app config from Firebase Console)
            if (data.project_info && typeof data.project_info === 'object') {
                detectedType = 'Google Services JSON (' + file.name + ')';
                extracted.project_id = data.project_info.project_id || '';
                extracted.sender_id = data.project_info.project_number || '';
                extracted.storage_bucket = data.project_info.storage_bucket || '';
                if (extracted.project_id) {
                    extracted.auth_domain = extracted.project_id + '.firebaseapp.com';
                    if (!extracted.storage_bucket) {
                        extracted.storage_bucket = extracted.project_id + '.appspot.com';
                    }
                }

                if (Array.isArray(data.client) && data.client.length > 0) {
                    var client = data.client[0];
                    if (client.client_info && client.client_info.mobilesdk_app_id) {
                        extracted.app_id = client.client_info.mobilesdk_app_id;
                    }
                    if (Array.isArray(client.api_key) && client.api_key.length > 0 && client.api_key[0].current_key) {
                        extracted.api_key = client.api_key[0].current_key;
                    }
                }
            }
            // 2. Google Cloud / Firebase Service Account Key (service-account.json)
            else if (data.type === 'service_account') {
                detectedType = 'Firebase Service Account Key (' + file.name + ')';
                extracted.project_id = data.project_id || '';
                if (extracted.project_id) {
                    extracted.auth_domain = extracted.project_id + '.firebaseapp.com';
                    extracted.storage_bucket = extracted.project_id + '.appspot.com';
                }
                extracted.service_account = rawText;
            }
            // 3. Web App Config JSON
            else {
                detectedType = 'Firebase Web Config (' + file.name + ')';
                extracted.api_key = data.apiKey || data.api_key || data.firebase_api_key || '';
                extracted.auth_domain = data.authDomain || data.auth_domain || data.firebase_auth_domain || '';
                extracted.project_id = data.projectId || data.project_id || data.firebase_project_id || '';
                extracted.storage_bucket = data.storageBucket || data.storage_bucket || data.firebase_storage_bucket || '';
                extracted.sender_id = data.messagingSenderId || data.messaging_sender_id || data.firebase_messaging_sender_id || '';
                extracted.app_id = data.appId || data.app_id || data.firebase_app_id || '';
                extracted.vapid_key = data.vapidKey || data.vapid_key || data.firebase_vapid_key || '';
                extracted.server_key = data.serverKey || data.server_key || data.fcm_server_key || '';
            }

            var populatedCount = 0;
            var summaryList = [];

            function setAndHighlight(inputId, val, label) {
                if (val && document.getElementById(inputId)) {
                    var el = document.getElementById(inputId);
                    el.value = val;
                    el.style.borderColor = '#10b981';
                    el.style.backgroundColor = '#f0fdf4';
                    el.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2)';
                    populatedCount++;
                    summaryList.push('<strong>' + label + ':</strong> <code>' + (val.length > 25 ? val.substring(0, 22) + '...' : val) + '</code>');
                }
            }

            if (extracted.project_id) setAndHighlight('firebase_project_id', extracted.project_id, 'Project ID');
            if (extracted.api_key) setAndHighlight('firebase_api_key', extracted.api_key, 'API Key');
            if (extracted.sender_id) setAndHighlight('firebase_messaging_sender_id', extracted.sender_id, 'Sender ID');
            if (extracted.app_id) setAndHighlight('firebase_app_id', extracted.app_id, 'App ID');
            if (extracted.auth_domain) setAndHighlight('firebase_auth_domain', extracted.auth_domain, 'Auth Domain');
            if (extracted.storage_bucket) setAndHighlight('firebase_storage_bucket', extracted.storage_bucket, 'Storage Bucket');
            if (extracted.vapid_key) setAndHighlight('firebase_vapid_key', extracted.vapid_key, 'VAPID Key');
            if (extracted.server_key) setAndHighlight('firebase_fcm_server_key', extracted.server_key, 'Server Key');
            if (extracted.service_account && document.getElementById('firebase_service_account_json')) {
                document.getElementById('firebase_service_account_json').value = extracted.service_account;
            }

            // Show feedback
            var feedbackBox = document.getElementById('jsonExtractionFeedback');
            var titleEl = document.getElementById('jsonDetectedTypeTitle');
            var itemsEl = document.getElementById('jsonExtractedItems');

            titleEl.innerHTML = '🎉 Auto-Extracted ' + populatedCount + ' Parameters from ' + detectedType;
            itemsEl.innerHTML = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 6px; margin-top: 8px;">' +
                summaryList.map(function(item) { return '<div style="background: rgba(255,255,255,0.7); padding: 4px 8px; border-radius: 4px;">✓ ' + item + '</div>'; }).join('') +
                '</div>';
            feedbackBox.style.display = 'block';

            feedbackBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        } catch (err) {
            alert('Error parsing JSON file: ' + err.message + '. Please ensure the file contains valid JSON.');
        }
    };
    reader.readAsText(file);
}
</script>

<?php require_once('footer.php'); ?>
