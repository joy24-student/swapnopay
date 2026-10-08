<?php
require_once __DIR__ . '/inc/guard.php';
require_once('header.php');
require_once('inc/notifications.php');

$merchantId = defined('MERCHANT_ID') && MERCHANT_ID ? MERCHANT_ID : 'local-merchant-001';

// Handle Save Firebase Settings POST
$settingsUpdated = false;
$settingsError = '';
if (isset($_POST['save_firebase_settings'])) {
    try {
        $fcmServerKey = trim((string)($_POST['fcm_server_key'] ?? ''));
        $firebaseApiKey = trim((string)($_POST['firebase_api_key'] ?? ''));
        $firebaseAuthDomain = trim((string)($_POST['firebase_auth_domain'] ?? ''));
        $firebaseProjectId = trim((string)($_POST['firebase_project_id'] ?? ''));
        $firebaseStorageBucket = trim((string)($_POST['firebase_storage_bucket'] ?? ''));
        $firebaseSenderId = trim((string)($_POST['firebase_messaging_sender_id'] ?? ''));
        $firebaseAppId = trim((string)($_POST['firebase_app_id'] ?? ''));
        $firebaseVapidKey = trim((string)($_POST['firebase_vapid_key'] ?? ''));

        $stmt = $pdo->prepare("
            UPDATE tbl_settings SET 
                fcm_server_key = ?,
                firebase_api_key = ?,
                firebase_auth_domain = ?,
                firebase_project_id = ?,
                firebase_storage_bucket = ?,
                firebase_messaging_sender_id = ?,
                firebase_app_id = ?,
                firebase_vapid_key = ?
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
            $firebaseVapidKey
        ]);

        // Invalidate settings cache
        $settingsCacheFile = __DIR__ . '/inc/cache_settings.json';
        @unlink($settingsCacheFile);

        $settingsUpdated = true;
    } catch (Throwable $e) {
        $settingsError = $e->getMessage();
    }
}

// Fetch current settings
$stmtSettings = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1");
$currentSettings = $stmtSettings ? ($stmtSettings->fetch(PDO::FETCH_ASSOC) ?: []) : [];

// Fetch statistics
$totalNotifications = 0;
$totalDevices = 0;
$totalCustomers = 0;
try {
    $totalNotifications = (int)$pdo->query("SELECT COUNT(*) FROM tbl_notifications WHERE merchant_id = " . $pdo->quote($merchantId))->fetchColumn();
    $totalDevices = (int)$pdo->query("SELECT COUNT(*) FROM tbl_fcm_tokens WHERE merchant_id = " . $pdo->quote($merchantId))->fetchColumn();
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM tbl_customer WHERE cust_status = '1'")->fetchColumn();
} catch (Throwable $e) {}

// Fetch notifications list
$stmtList = $pdo->prepare("
    SELECT * FROM tbl_notifications 
    WHERE merchant_id = ? 
    ORDER BY created_at DESC 
    LIMIT 100
");
$stmtList->execute([$merchantId]);
$notificationList = $stmtList->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="icon fa fa-check"></i> Firebase Cloud Messaging credentials updated successfully!
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
                        <div class="callout callout-info" style="border-radius: 6px;">
                            <h4><i class="fa fa-info-circle"></i> Firebase Cloud Messaging (FCM) Integration</h4>
                            <p>Configure your Firebase credentials below so the store can send real-time native push notifications to customer devices, Android WebView apps, and browsers.</p>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="save_firebase_settings" value="1">

                            <div class="form-group">
                                <label style="font-weight: 700;">FCM Server Key (Legacy HTTP Server Key)</label>
                                <input type="password" class="form-control" name="fcm_server_key" value="<?php echo htmlspecialchars($currentSettings['fcm_server_key'] ?? ''); ?>" placeholder="AAAA... (found in Firebase Console > Project Settings > Cloud Messaging > Cloud Messaging API)">
                                <small class="text-muted">Used by the server backend to authenticate push requests to Firebase API.</small>
                            </div>

                            <hr>

                            <h4><i class="fa fa-globe"></i> Web & WebView Client App Credentials</h4>
                            <p class="text-muted">Obtained from Firebase Console &gt; Project Settings &gt; General &gt; Your Apps (Web App config):</p>

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Firebase Web API Key (apiKey)</label>
                                        <input type="text" class="form-control" name="firebase_api_key" value="<?php echo htmlspecialchars($currentSettings['firebase_api_key'] ?? ''); ?>" placeholder="AIzaSy...">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Firebase Project ID (projectId)</label>
                                        <input type="text" class="form-control" name="firebase_project_id" value="<?php echo htmlspecialchars($currentSettings['firebase_project_id'] ?? ''); ?>" placeholder="my-store-12345">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Messaging Sender ID (messagingSenderId)</label>
                                        <input type="text" class="form-control" name="firebase_messaging_sender_id" value="<?php echo htmlspecialchars($currentSettings['firebase_messaging_sender_id'] ?? ''); ?>" placeholder="1029384756">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Firebase App ID (appId)</label>
                                        <input type="text" class="form-control" name="firebase_app_id" value="<?php echo htmlspecialchars($currentSettings['firebase_app_id'] ?? ''); ?>" placeholder="1:1029384756:web:abcd1234">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Auth Domain (authDomain)</label>
                                        <input type="text" class="form-control" name="firebase_auth_domain" value="<?php echo htmlspecialchars($currentSettings['firebase_auth_domain'] ?? ''); ?>" placeholder="my-store.firebaseapp.com">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Storage Bucket (storageBucket)</label>
                                        <input type="text" class="form-control" name="firebase_storage_bucket" value="<?php echo htmlspecialchars($currentSettings['firebase_storage_bucket'] ?? ''); ?>" placeholder="my-store.appspot.com">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label style="font-weight: 700;">Web Push VAPID Key (Voluntary Application Server Identity)</label>
                                <textarea class="form-control" name="firebase_vapid_key" rows="2" placeholder="BOn0f9... (found under Cloud Messaging > Web configuration > Web Push certificates)"><?php echo htmlspecialchars($currentSettings['firebase_vapid_key'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg" style="font-weight: 700; border-radius: 6px; padding: 10px 24px;">
                                <i class="fa fa-save" style="margin-right: 6px;"></i> Save Firebase Configuration
                            </button>
                        </form>
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
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
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
</script>

<?php require_once('footer.php'); ?>
