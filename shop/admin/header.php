<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/CSRF_Protect.php');
$csrf = new CSRF_Protect();
$error_message = '';
$success_message = '';
$error_message1 = '';
$success_message1 = '';

// Check if the user is logged in or not
if(!isset($_SESSION['user'])) {
	header('location: login.php');
	exit;
}

// Getting all language variables
$i=1;
$statement = $pdo->prepare("SELECT * FROM tbl_language ORDER BY lang_id");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
foreach ($result as $row) {
    define('LANG_VALUE_'.$i,$row['lang_value']);
    $i++;
}
?>

<!DOCTYPE html>
<html>
<head>
	<base href="<?php echo htmlspecialchars(BASE_URL . 'admin/', ENT_QUOTES, 'UTF-8'); ?>">
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Admin Panel</title>

	<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/ionicons.min.css">
	<link rel="stylesheet" href="css/datepicker3.css">
	<link rel="stylesheet" href="css/all.css">
	<link rel="stylesheet" href="css/select2.min.css">
	<link rel="stylesheet" href="css/dataTables.bootstrap.css">
	<link rel="stylesheet" href="css/jquery.fancybox.css">
	<link rel="stylesheet" href="css/AdminLTE.min.css">
	<link rel="stylesheet" href="css/_all-skins.min.css">
	<link rel="stylesheet" href="css/on-off-switch.css"/>
	<link rel="stylesheet" href="css/summernote.css">
	<link rel="stylesheet" href="style.css">

<link rel="stylesheet" href="css/enterprise.css?v=<?php echo filemtime(__DIR__ . '/css/enterprise.css'); ?>">
<link rel="stylesheet" href="css/admin-notifications.css?v=<?php echo filemtime(__DIR__ . '/css/admin-notifications.css'); ?>">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#FEDB65">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="ShopAdmin">
<link rel="apple-touch-icon" href="img/admin-pwa-icon-192.png">
<meta name="csrf-token" content="<?php echo $csrf->getToken(); ?>">
<script src="js/jquery-2.2.4.min.js"></script>
</head>

<body class="hold-transition fixed skin-blue sidebar-mini sn-shopmart-theme">

	<div class="wrapper">

		<header class="main-header">

			<!-- Logo Area (Clean White matching Sidebar) -->
			<a href="index.php" class="logo">
				<span class="logo-mini">
					<div class="sn-logo-badge-mini">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
							<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
							<line x1="3" y1="6" x2="21" y2="6"></line>
							<path d="M16 10a4 4 0 0 1-8 0"></path>
						</svg>
					</div>
				</span>
				<span class="logo-lg">
					<div class="sn-logo-wrap">
						<div class="sn-logo-badge">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
								<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
								<line x1="3" y1="6" x2="21" y2="6"></line>
								<path d="M16 10a4 4 0 0 1-8 0"></path>
							</svg>
						</div>
						<div class="sn-logo-info">
							<span class="sn-logo-title"><?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Admin Panel'); ?></span>
							<span class="sn-logo-sub">Admin Panel</span>
						</div>
					</div>
				</span>
			</a>

			<!-- Top Navbar (Warm Yellow Bar) -->
			<nav class="navbar navbar-static-top">
				
				<div class="sn-topbar-left">
					<a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
						<span class="sr-only">Toggle navigation</span>
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.4" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
					</a>
					<div class="sn-topbar-divider"></div>
					<span class="sn-topbar-title">Admin Panel</span>
				</div>

				<div class="navbar-custom-menu">
					<ul class="nav navbar-nav sn-nav-items" style="list-style: none !important; margin: 0; padding: 0;">
						<!-- Dedicated AI Voice Copilot Button (Beside Notification Bell) -->
						<li class="sn-ai-li" style="list-style: none !important;">
							<a href="ai-copilot.php" class="sn-ai-header-btn" title="AI Voice Copilot & Autonomous Operations">
								<span class="sn-ai-btn-sparkle">
									<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
										<path d="M12 2l2.4 7.4 7.6 2.6-7.6 2.6L12 22l-2.4-7.4L2 12l7.6-2.6L12 2z"/>
									</svg>
								</span>
								<span class="sn-ai-btn-title hidden-xs">AI Copilot</span>
								<span class="sn-ai-voice-dot" title="Voice & Vision Active"><i class="fa fa-microphone"></i></span>
							</a>
						</li>

						<!-- Dedicated PWA WebApp Install Button (Chrome Side Bar & Desktop) -->
						<li class="sn-pwa-li" style="list-style: none !important;">
							<a href="javascript:void(0)" onclick="window.triggerAdminPwaInstall && window.triggerAdminPwaInstall(event)" class="sn-ai-header-btn sn-pwa-header-btn" id="btnAdminPwaInstallHeader" title="Download & Install WebApp (Chrome Side Bar / Desktop)">
								<span class="sn-pwa-btn-icon" style="display:flex; align-items:center; color:#B45309;">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
										<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
										<polyline points="7 10 12 15 17 10"></polyline>
										<line x1="12" y1="15" x2="12" y2="3"></line>
									</svg>
								</span>
								<span class="hidden-xs" style="font-weight:700;">Install App</span>
							</a>
						</li>

						<!-- Notification Bell -->
						<li class="sn-bell-li" style="list-style: none !important;">
							<a href="javascript:void(0)" class="sn-bell-link" id="snAdminNotificationTrigger" onclick="window.snAdminNotifications && window.snAdminNotifications.toggleModal(event)" title="Store Notifications" role="button" aria-haspopup="dialog">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
									<path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
								</svg>
								<span class="sn-bell-badge" id="snAdminBellBadge" style="display:none;">0</span>
							</a>
						</li>

						<!-- User Profile Dropdown -->
						<?php
						$raw_name = !empty($_SESSION['user']['full_name']) ? trim($_SESSION['user']['full_name']) : (!empty($_SESSION['user']['email']) ? trim($_SESSION['user']['email']) : 'Admin');
						$display_name = $raw_name;
						if (mb_strlen($display_name) > 20) {
							$words = explode(' ', $display_name);
							if (count($words) >= 2) {
								$display_name = $words[0] . ' ' . $words[1];
							} else {
								$display_name = mb_substr($display_name, 0, 18) . '...';
							}
						}
						$display_role = !empty($_SESSION['user']['role']) ? $_SESSION['user']['role'] : 'Administrator';
						$user_avatar = !empty($_SESSION['user']['photo']) ? trim($_SESSION['user']['photo']) : '';
						if (empty($user_avatar) || $user_avatar === 'user-1.' || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $user_avatar)) {
							$user_avatar = 'user-1.png';
						}
						?>
						<li class="dropdown user user-menu sn-user-li" style="list-style: none !important;">
							<a href="#" class="dropdown-toggle sn-user-link" data-toggle="dropdown" title="<?php echo htmlspecialchars($raw_name); ?>">
								<img src="../assets/uploads/<?php echo htmlspecialchars($user_avatar); ?>" class="user-image sn-user-avatar" alt="User Image" onerror="this.onerror=null; this.src='../assets/uploads/mob_avatar_default.png';">
								<div class="sn-user-meta hidden-xs">
									<span class="sn-user-name"><?php echo htmlspecialchars($display_name); ?></span>
									<span class="sn-user-role"><?php echo htmlspecialchars($display_role); ?></span>
								</div>
								<svg class="sn-user-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
									<polyline points="6 9 12 15 18 9"></polyline>
								</svg>
							</a>
							<ul class="dropdown-menu sn-user-dropdown-menu">
								<li class="user-header" style="background:#FFFDF0; padding:18px; text-align:center;">
									<img src="../assets/uploads/<?php echo htmlspecialchars($user_avatar); ?>" class="img-circle" style="width:60px; height:60px; object-fit:cover; border:2px solid #FEDB65;" alt="User Image" onerror="this.onerror=null; this.src='../assets/uploads/mob_avatar_default.png';">
									<p style="color:#0F172A; font-weight:700; margin-top:8px;">
										<?php echo htmlspecialchars(!empty($_SESSION['user']['full_name']) ? $_SESSION['user']['full_name'] : (!empty($_SESSION['user']['email']) ? $_SESSION['user']['email'] : 'Admin')); ?>
										<small style="color:#64748B; font-weight:500; display:block;"><?php echo htmlspecialchars($_SESSION['user']['role'] ?? 'Administrator'); ?></small>
									</p>
								</li>
								<li class="user-footer" style="padding:12px; background:#F8FAFC;">
									<div class="pull-left">
										<a href="profile-edit.php" class="btn btn-default btn-sm" style="border-radius:8px; font-weight:600;">Edit Profile</a>
									</div>
									<div class="pull-right">
										<a href="logout.php" class="btn btn-default btn-sm" style="border-radius:8px; font-weight:600; color:#EF4444;">Log out</a>
									</div>
								</li>
							</ul>
						</li>
					</ul>
				</div>

			</nav>
		</header>

  		<?php $cur_page = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1); ?>
<!-- Side Bar to Manage Shop Activities -->
  		<aside class="main-sidebar">
			<!-- Mobile Sidebar Header with Close/Collapse Cross Icon -->
			<div class="sn-mobile-sidebar-header visible-xs" style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#FEDB65; border-bottom:1px solid rgba(0,0,0,0.06);">
				<div class="sn-mobile-sidebar-brand" style="display:flex; align-items:center; gap:8px;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
						<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
						<line x1="3" y1="6" x2="21" y2="6"/>
						<path d="M16 10a4 4 0 0 1-8 0"/>
					</svg>
					<span style="font-weight:700; color:#0F172A; font-size:14px;">Admin Menu</span>
				</div>
				<button type="button" class="sn-mobile-sidebar-close" data-toggle="offcanvas" aria-label="Close sidebar" title="Close Sidebar" style="background:none; border:none; color:#0F172A; font-size:22px; cursor:pointer; padding:0 4px; line-height:1;">
					&times;
				</button>
			</div>

    		<section class="sidebar">
      
      			<ul class="sidebar-menu">

			        <li class="treeview <?php if($cur_page == 'index.php') {echo 'active';} ?>">
			          <a href="index.php">
			            <i class="fa fa-home"></i> <span>Dashboard</span>
			          </a>
			        </li>

			        <li class="treeview <?php if( ($cur_page == 'settings.php') ) {echo 'active';} ?>">
			          <a href="settings.php">
			            <i class="fa fa-sliders"></i> <span>Website Settings</span>
			          </a>
			        </li>

			        <li class="treeview <?php if( ($cur_page == 'marketing.php') ) {echo 'active';} ?>">
			          <a href="#">
			            <i class="fa fa-comments"></i> <span>Messenger & WhatsApp</span>
			            <span class="pull-right-container">
			              <i class="fa fa-angle-right pull-right"></i>
			            </span>
			          </a>
			          <ul class="treeview-menu">
			            <li><a href="marketing.php?tab=messenger"><i class="fa fa-circle-o"></i> Messenger Bot</a></li>
			            <li><a href="marketing.php?tab=whatsapp"><i class="fa fa-circle-o"></i> WhatsApp Automation</a></li>
			          </ul>
			        </li>

			        <li class="treeview <?php if( ($cur_page == 'live-chat.php') ) {echo 'active';} ?>">
			          <a href="live-chat.php">
			            <i class="fa fa-commenting-o"></i> <span>Live Support Chat</span>
			          </a>
			        </li>


                    <li class="treeview <?php if( ($cur_page == 'size.php') || ($cur_page == 'size-add.php') || ($cur_page == 'size-edit.php') || ($cur_page == 'color.php') || ($cur_page == 'color-add.php') || ($cur_page == 'color-edit.php') || ($cur_page == 'country.php') || ($cur_page == 'country-add.php') || ($cur_page == 'country-edit.php') || ($cur_page == 'shipping-cost.php') || ($cur_page == 'shipping-cost-edit.php') || ($cur_page == 'top-category.php') || ($cur_page == 'top-category-add.php') || ($cur_page == 'top-category-edit.php') || ($cur_page == 'mid-category.php') || ($cur_page == 'mid-category-add.php') || ($cur_page == 'mid-category-edit.php') || ($cur_page == 'end-category.php') || ($cur_page == 'end-category-add.php') || ($cur_page == 'end-category-edit.php') ) {echo 'active';} ?>">
                        <a href="#">
                            <i class="fa fa-cogs"></i>
                            <span>Shop Settings</span>
                            <span class="pull-right-container">
								<i class="fa fa-angle-right pull-right"></i>
							</span>
                        </a>
                        <ul class="treeview-menu">
                            <li><a href="size.php"><i class="fa fa-circle-o"></i> Size</a></li>
                            <li><a href="color.php"><i class="fa fa-circle-o"></i> Color</a></li>
                            <li><a href="country.php"><i class="fa fa-circle-o"></i> Country</a></li>
                            <li><a href="shipping-cost.php"><i class="fa fa-circle-o"></i> Shipping Cost</a></li>
							<li><a href="coupons.php"><i class="fa fa-circle-o"></i> Coupon code</a></li>
                            <li><a href="top-category.php"><i class="fa fa-circle-o"></i> Top Level Category</a></li>
                            <li><a href="mid-category.php"><i class="fa fa-circle-o"></i> Mid Level Category</a></li>
                            <li><a href="end-category.php"><i class="fa fa-circle-o"></i> End Level Category</a></li>
                        </ul>
                    </li>


                    <li class="treeview <?php if( ($cur_page == 'product.php') || ($cur_page == 'product-add.php') || ($cur_page == 'product-edit.php') ) {echo 'active';} ?>">
                        <a href="product.php">
                            <i class="fa fa-shopping-bag"></i> <span>Product Management</span>
                        </a>
                    </li>

                    <!-- NEW: Product Reviews Link -->
                    <li class="treeview <?php if( ($cur_page == 'reviews.php') ) {echo 'active';} ?>">
                        <a href="reviews.php">
                            <i class="fa fa-star"></i> <span>Product Reviews</span>
                        </a>
                    </li>


                    <li class="treeview <?php if( ($cur_page == 'order.php') ) {echo 'active';} ?>">
                        <a href="order.php">
                            <i class="fa fa-cube"></i> <span>Order Management</span>
                        </a>
                    </li>


                    <!-- Icons to be displayed on Shop -->
			        <li class="treeview <?php if( ($cur_page == 'service.php') ) {echo 'active';} ?>">
			          <a href="service.php">
			            <i class="fa fa-list"></i> <span>Services</span>
			          </a>
			        </li>

			      			        <li class="treeview <?php if( ($cur_page == 'faq.php') ) {echo 'active';} ?>">
			          <a href="faq.php">
			            <i class="fa fa-question-circle"></i> <span>FAQ</span>
			          </a>
			        </li>

						<li class="treeview <?php if( ($cur_page == 'customer.php') || ($cur_page == 'customer-add.php') || ($cur_page == 'customer-edit.php') ) {echo 'active';} ?>">
			          <a href="customer.php">
			            <i class="fa fa-user-plus"></i> <span>Registered Customer</span>
			          </a>
			        </li>
			        
			        

			        <li class="treeview <?php if( ($cur_page == 'subscriber.php')||($cur_page == 'subscriber.php') ) {echo 'active';} ?>">
			          <a href="subscriber.php">
			            <i class="fa fa-hand-o-right"></i> <span>Subscriber</span>
			          </a>
			        </li>

      			</ul>
    		</section>
  		</aside>

  		<div class="content-wrapper">