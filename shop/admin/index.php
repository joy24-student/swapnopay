<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once('header.php'); ?>

<section class="content-header">
	<h1>Dashboard</h1>
</section>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_top_category");
$statement->execute();
$total_top_category = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_mid_category");
$statement->execute();
$total_mid_category = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_end_category");
$statement->execute();
$total_end_category = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_product");
$statement->execute();
$total_product = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_status='1'");
$statement->execute();
$total_customers = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_subscriber WHERE subs_active='1'");
$statement->execute();
$total_subscriber = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_shipping_cost");
$statement->execute();
$available_shipping = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=?");
$statement->execute(array('Completed'));
$total_order_completed = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE shipping_status=?");
$statement->execute(array('Completed'));
$total_shipping_completed = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=?");
$statement->execute(array('Pending'));
$total_order_pending = $statement->rowCount();

$statement = $pdo->prepare("SELECT * FROM tbl_payment WHERE payment_status=? AND shipping_status=?");
$statement->execute(array('Completed','Pending'));
$total_order_complete_shipping_pending = $statement->rowCount();

// Notification & FCM Stats
$total_notifications_sent = 0;
$total_fcm_devices = 0;
$recent_broadcasts = [];
try {
    $total_notifications_sent = (int)$pdo->query("SELECT COUNT(*) FROM tbl_notifications")->fetchColumn();
    $total_fcm_devices = (int)$pdo->query("SELECT COUNT(*) FROM tbl_fcm_tokens")->fetchColumn();
    $recent_broadcasts = $pdo->query("SELECT * FROM tbl_notifications ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>

<section class="content">
<div class="row">
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-primary">
                <div class="inner">
                  <h3><?php echo $total_product; ?></h3>

                  <p>Products</p>
                </div>
                <div class="icon">
                  <i class="ionicons ion-android-cart"></i>
                </div>
                
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-maroon">
                <div class="inner">
                  <h3><?php echo $total_order_pending; ?></h3>

                  <p>Pending Orders</p>
                </div>
                <div class="icon">
                  <i class="ionicons ion-clipboard"></i>
                </div>
                
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-green">
                <div class="inner">
                  <h3><?php echo $total_order_completed; ?></h3>

                  <p>Completed Orders</p>
                </div>
                <div class="icon">
                  <i class="ionicons ion-android-checkbox-outline"></i>
                </div>
               
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-xs-6">
              <!-- small box -->
              <div class="small-box bg-aqua">
                <div class="inner">
                  <h3><?php echo $total_shipping_completed; ?></h3>

                  <p>Completed Shipping</p>
                </div>
                <div class="icon">
                  <i class="ionicons ion-checkmark-circled"></i>
                </div>
                
              </div>
            </div>
			<!-- ./col -->
			
			<div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-orange">
				  <div class="inner">
					<h3><?php echo $total_order_complete_shipping_pending; ?></h3>
  
					<p>Pending Shippings</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-load-a"></i>
				  </div>
				  
				</div>
			  </div>

			  <div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-red">
				  <div class="inner">
					<h3><?php echo $total_customers; ?></h3>
  
					<p>Active Customers</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-person-stalker"></i>
				  </div>
				  
				</div>
			  </div>

			  <div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-yellow">
				  <div class="inner">
					<h3><?php echo $total_subscriber; ?></h3>
  
					<p>Subscriber</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-person-add"></i>
				  </div>
				  
				</div>
			  </div>

			  <div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-teal">
				  <div class="inner">
					<h3><?php echo $available_shipping; ?></h3>
  
					<p>Available Shippings</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-location"></i>
				  </div>
				  
				</div>
			  </div>

			  <div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-olive">
				  <div class="inner">
					<h3><?php echo $total_top_category; ?></h3>
  
					<p>Top Categories</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-arrow-up-b"></i>
				  </div>
				  
				</div>
			  </div>

			  <div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-blue">
				  <div class="inner">
					<h3><?php echo $total_mid_category; ?></h3>
  
					<p>Mid Categories</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-android-menu"></i>
				  </div>
				  
				</div>
			  </div>

			  <div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-maroon">
				  <div class="inner">
					<h3><?php echo $total_end_category; ?></h3>
  
					<p>End Categories</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-arrow-down-b"></i>
				  </div>
				  
				</div>
			  </div>

			  <div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-purple">
				  <div class="inner">
					<h3><?php echo $total_notifications_sent; ?></h3>
					<p>Notifications Sent</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-android-notifications"></i>
				  </div>
				</div>
			  </div>

			  <div class="col-lg-3 col-xs-6">
				<!-- small box -->
				<div class="small-box bg-navy">
				  <div class="inner">
					<h3><?php echo $total_fcm_devices; ?></h3>
					<p>App Devices (FCM)</p>
				  </div>
				  <div class="icon">
					<i class="ionicons ion-android-phone-portrait"></i>
				  </div>
				</div>
			  </div>

		  </div>

<!-- Broadcast Push Notification Center (Main Dashboard Section) -->
<div class="row" style="margin-top: 20px;">
    <!-- Broadcast Form -->
    <div class="col-md-7">
        <div class="box box-warning" style="border-top: 3px solid #f39c12; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="box-header with-border" style="display: flex; align-items: center; justify-content: space-between;">
                <h3 class="box-title" style="font-weight: 700;">
                    <i class="fa fa-bullhorn text-yellow" style="margin-right: 8px;"></i>
                    Broadcast Push Notification (App & Web)
                </h3>
                <a href="broadcast-notification.php" class="btn btn-xs btn-default" style="font-weight: 600;">
                    <i class="fa fa-sliders"></i> Full Manager & Settings
                </a>
            </div>

            <div class="box-body" style="padding: 20px;">
                <div id="dashBroadcastAlert" style="display:none;" class="alert"></div>

                <form id="dashBroadcastForm">
                    <div class="form-group">
                        <label for="dashNotifTitle" style="font-size: 13px; font-weight: 600;">Notification Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control input-lg" id="dashNotifTitle" name="title" placeholder="e.g. Flash Sale Live! 50% Off Everything Today" required style="border-radius: 6px;">
                    </div>

                    <div class="form-group">
                        <label for="dashNotifBody" style="font-size: 13px; font-weight: 600;">Message Body <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="dashNotifBody" name="body" rows="3" placeholder="e.g. Hurry, grab your favorite fashion and gadgets before deal ends tonight! Tap to view deals." required style="border-radius: 6px; resize: vertical;"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="dashNotifType" style="font-size: 13px; font-weight: 600;">Notification Category</label>
                                <select class="form-control" id="dashNotifType" name="type" style="border-radius: 6px;">
                                    <option value="broadcast" selected>📢 General Broadcast / Notice</option>
                                    <option value="promo">⚡ Flash Deal & Promotion</option>
                                    <option value="order">📦 Order & Shipping Alert</option>
                                    <option value="system">🛡️ System & Security Notice</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="dashNotifAudience" style="font-size: 13px; font-weight: 600;">Target Audience</label>
                                <select class="form-control" id="dashNotifAudience" name="target_audience" style="border-radius: 6px;" onchange="toggleDashTargetAudience(this.value)">
                                    <option value="all" selected>All App Users & Devices (Global)</option>
                                    <option value="specific">Specific Customer (Email / ID)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="dashSpecificCustWrap" style="display: none;">
                        <label for="dashCustTarget" style="font-size: 13px; font-weight: 600;">Customer Email or ID</label>
                        <input type="text" class="form-control" id="dashCustTarget" name="customer_target" placeholder="customer@example.com or Customer ID" style="border-radius: 6px;">
                    </div>

                    <div class="form-group">
                        <label for="dashNotifActionUrl" style="font-size: 13px; font-weight: 600;">Target Screen / Link URL (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-addon" style="background: #f4f6f9; font-size: 12px;"><?php echo BASE_URL; ?></span>
                            <input type="text" class="form-control" id="dashNotifActionUrl" name="action_url" placeholder="deals.php or product.php?id=12" style="border-radius: 0 6px 6px 0;">
                        </div>
                        <small class="text-muted">Where the customer lands when they tap the push notification banner.</small>
                    </div>

                    <button type="button" class="btn btn-warning btn-lg btn-block" id="btnSendDashBroadcast" onclick="submitDashBroadcast()" style="font-weight: 700; border-radius: 6px; padding: 12px; font-size: 15px; box-shadow: 0 2px 6px rgba(243, 156, 18, 0.4);">
                        <i class="fa fa-paper-plane" style="margin-right: 6px;"></i> Send Push Broadcast Now
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Live Smartphone Preview & Recent Broadcasts -->
    <div class="col-md-5">
        <!-- Live Push Mockup -->
        <div class="box box-default" style="border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); background: #ffffff;">
            <div class="box-header with-border">
                <h3 class="box-title" style="font-size: 14px; font-weight: 700;">
                    <i class="fa fa-mobile text-primary" style="font-size: 18px; margin-right: 6px;"></i>
                    Live Mobile Push Banner Preview
                </h3>
            </div>
            <div class="box-body" style="background: #eef2f6; padding: 25px 15px; border-radius: 0 0 8px 8px;">
                <!-- Phone Lockscreen Card -->
                <div style="background: #1e293b; border-radius: 18px; padding: 16px 14px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); max-width: 340px; margin: 0 auto; color: #fff;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 11px; color: #94a3b8;">
                        <span id="previewTime"><?php echo date('h:i A'); ?></span>
                        <span><i class="fa fa-wifi"></i> &bull; 100%</span>
                    </div>

                    <!-- Push Notification Banner inside lockscreen -->
                    <div style="background: rgba(255, 255, 255, 0.95); color: #0f172a; border-radius: 12px; padding: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.12);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="width: 18px; height: 18px; background: #fab802; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #000; font-weight bold;">
                                    <i class="fa fa-shopping-bag"></i>
                                </div>
                                <span style="font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase;">ShopNext Store</span>
                            </div>
                            <span style="font-size: 10px; color: #94a3b8;">now</span>
                        </div>
                        <div id="mockupTitle" style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px;">
                            Flash Sale Live! 50% Off Everything Today
                        </div>
                        <div id="mockupBody" style="font-size: 11.5px; color: #475569; line-height: 1.35;">
                            Hurry, grab your favorite fashion and gadgets before deal ends tonight! Tap to view deals.
                        </div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 10px; font-size: 11px; color: #64748b;">
                    <i class="fa fa-info-circle"></i> Real-time preview of native push on Android / iOS WebView
                </div>
            </div>
        </div>

        <!-- Recent Broadcasts List -->
        <div class="box box-solid" style="border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="box-header with-border" style="display: flex; align-items: center; justify-content: space-between;">
                <h3 class="box-title" style="font-size: 14px; font-weight: 700;">
                    <i class="fa fa-history text-muted" style="margin-right: 6px;"></i>
                    Recent Broadcast Activity
                </h3>
            </div>
            <div class="box-body no-padding">
                <table class="table table-striped table-condensed" style="font-size: 12px; margin: 0;">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_broadcasts)): ?>
                            <?php foreach ($recent_broadcasts as $rb): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars(mb_strimwidth($rb['title'], 0, 28, '...')); ?></strong>
                                    </td>
                                    <td>
                                        <span class="label label-<?php echo $rb['type'] === 'order' ? 'primary' : ($rb['type'] === 'promo' ? 'warning' : 'success'); ?>">
                                            <?php echo htmlspecialchars($rb['type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo date('M d, H:i', strtotime($rb['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted" style="padding: 15px;">No broadcasts sent yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time Push Mockup Sync
document.getElementById('dashNotifTitle').addEventListener('input', function(e) {
    document.getElementById('mockupTitle').textContent = e.target.value.trim() || 'Flash Sale Live! 50% Off Everything Today';
});
document.getElementById('dashNotifBody').addEventListener('input', function(e) {
    document.getElementById('mockupBody').textContent = e.target.value.trim() || 'Hurry, grab your favorite fashion and gadgets before deal ends tonight! Tap to view deals.';
});

function toggleDashTargetAudience(val) {
    var wrap = document.getElementById('dashSpecificCustWrap');
    wrap.style.display = (val === 'specific') ? 'block' : 'none';
}

function submitDashBroadcast() {
    var title = document.getElementById('dashNotifTitle').value.trim();
    var body = document.getElementById('dashNotifBody').value.trim();
    var type = document.getElementById('dashNotifType').value;
    var audience = document.getElementById('dashNotifAudience').value;
    var custTarget = document.getElementById('dashCustTarget').value.trim();
    var actionUrl = document.getElementById('dashNotifActionUrl').value.trim();
    var alertBox = document.getElementById('dashBroadcastAlert');
    var btn = document.getElementById('btnSendDashBroadcast');

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

            // Reset title and body
            document.getElementById('dashNotifTitle').value = '';
            document.getElementById('dashNotifBody').value = '';
            document.getElementById('dashNotifActionUrl').value = '';
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
</section>

<?php require_once('footer.php'); ?>