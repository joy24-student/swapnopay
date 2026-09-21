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

<link rel="stylesheet" href="css/enterprise.css">
<meta name="csrf-token" content="<?php echo $csrf->getToken(); ?>">
</head>

<body class="hold-transition fixed skin-blue sidebar-mini">

	<div class="wrapper">

		<header class="main-header">

			<a href="index.php" class="logo">
				<span class="logo-lg">eCommerce PHP</span>
			</a>

			<nav class="navbar navbar-static-top">
				
				<a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
					<span class="sr-only">Toggle navigation</span>
				</a>

				<span style="float:left;line-height:50px;color:#fff;padding-left:15px;font-size:18px;">Admin Panel</span>
    <!-- Top Bar ... User Inforamtion .. Login/Log out Area -->
				<div class="navbar-custom-menu">
					<ul class="nav navbar-nav">
						<li class="dropdown user user-menu">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown">
								<img src="../assets/uploads/<?php echo $_SESSION['user']['photo']; ?>" class="user-image" alt="User Image">
								<span class="hidden-xs"><?php echo $_SESSION['user']['full_name']; ?></span>
							</a>
							<ul class="dropdown-menu">
								<li class="user-footer">
									<div>
										<a href="profile-edit.php" class="btn btn-default btn-flat">Edit Profile</a>
									</div>
									<div>
										<a href="logout.php" class="btn btn-default btn-flat">Log out</a>
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
    		<section class="sidebar">
      
      			<ul class="sidebar-menu">

			        <li class="treeview <?php if($cur_page == 'index.php') {echo 'active';} ?>">
			          <a href="index.php">
			            <i class="fa fa-dashboard"></i> <span>Dashboard</span>
			          </a>
			        </li>

					
			        <li class="treeview <?php if( ($cur_page == 'settings.php') ) {echo 'active';} ?>">
			          <a href="settings.php">
			            <i class="fa fa-sliders"></i> <span>Website Settings</span>
			          </a>
			        </li>

                    <li class="treeview <?php if( ($cur_page == 'size.php') || ($cur_page == 'size-add.php') || ($cur_page == 'size-edit.php') || ($cur_page == 'color.php') || ($cur_page == 'color-add.php') || ($cur_page == 'color-edit.php') || ($cur_page == 'country.php') || ($cur_page == 'country-add.php') || ($cur_page == 'country-edit.php') || ($cur_page == 'shipping-cost.php') || ($cur_page == 'shipping-cost-edit.php') || ($cur_page == 'top-category.php') || ($cur_page == 'top-category-add.php') || ($cur_page == 'top-category-edit.php') || ($cur_page == 'mid-category.php') || ($cur_page == 'mid-category-add.php') || ($cur_page == 'mid-category-edit.php') || ($cur_page == 'end-category.php') || ($cur_page == 'end-category-add.php') || ($cur_page == 'end-category-edit.php') ) {echo 'active';} ?>">
                        <a href="#">
                            <i class="fa fa-cogs"></i>
                            <span>Shop Settings</span>
                            <span class="pull-right-container">
								<i class="fa fa-angle-left pull-right"></i>
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
                            <i class="fa fa-sticky-note"></i> <span>Order Management</span>
                        </a>
                    </li>


                     <li class="treeview <?php if( ($cur_page == 'slider.php') ) {echo 'active';} ?>">
			          <a href="slider.php">
			            <i class="fa fa-picture-o"></i> <span>Manage Sliders</span>
			          </a>
			        </li>
                    <!-- Icons to be displayed on Shop -->
			        <li class="treeview <?php if( ($cur_page == 'service.php') ) {echo 'active';} ?>">
			          <a href="service.php">
			            <i class="fa fa-list-ol"></i> <span>Services</span>
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
			        
			        
			        <!-- আপনার বর্তমান অ্যাডমিন নেভিগেশনের মধ্যে এই কোডটি যোগ করুন -->
<li class="treeview">
    <a href="#">
        <i class="fa fa-store"></i> <span>ব্যবসা/স্টোর ম্যানেজমেন্ট</span>
        <span class="pull-right-container">
            <i class="fa fa-angle-left pull-right"></i>
        </span>
    </a>
    <ul class="treeview-menu">
        <li><a href="admin_businesses.php"><i class="fa fa-circle-o"></i> সকল ব্যবসা/স্টোর দেখুন</a></li>
        <li><a href="admin_business_add.php"><i class="fa fa-circle-o"></i> নতুন ব্যবসা/স্টোর যোগ করুন</a></li>
        <!-- যদি প্রয়োজন হয়: স্টোর পণ্যের জন্য আলাদা ম্যানেজমেন্ট -->
        <!-- <li><a href="admin_business_products.php"><i class="fa fa-circle-o"></i> স্টোরের পণ্য ম্যানেজ করুন</a></li> -->
    </ul>
</li>
			     <li class="treeview">
    <a href="#">
        <i class="fa fa-money"></i> <span>কয়েন ম্যানেজমেন্ট</span>
        <span class="pull-right-container">
            <i class="fa fa-angle-left pull-right"></i>
        </span>
    </a>
    <ul class="treeview-menu">
        <li><a href="admin_manage_coins.php"><i class="fa fa-circle-o"></i> ব্যবহারকারী কয়েন দেখুন</a></li>
        <li><a href="admin_add_coins.php"><i class="fa fa-circle-o"></i> কয়েন যোগ/বাতিল করুন</a></li>
        <li><a href="admin_coin_transactions.php"><i class="fa fa-circle-o"></i> লেনদেনের ইতিহাস দেখুন</a></li>
    </ul>
</li>
<!-- Inside your existing admin navigation structure -->
<li class="treeview">
    <a href="#">
        <i class="fa fa-gift"></i> <span>Voucher Management</span>
        <span class="pull-right-container">
            <i class="fa fa-angle-left pull-right"></i>
        </span>
    </a>
    <ul class="treeview-menu">
        <li><a href="admin_vouchers.php"><i class="fa fa-circle-o"></i> View All Vouchers</a></li>
        <li><a href="admin_voucher_add.php"><i class="fa fa-circle-o"></i> Add New Voucher</a></li>
    </ul>
</li>
<!-- আপনার বর্তমান অ্যাডমিন নেভিগেশনের মধ্যে এই কোডটি যোগ করুন -->
<li class="treeview">
    <a href="#">
        <i class="fa fa-user-md"></i> <span>প্রফেশনাল ম্যানেজমেন্ট</span>
        <span class="pull-right-container">
            <i class="fa fa-angle-left pull-right"></i>
        </span>
    </a>
    <ul class="treeview-menu">
        <li><a href="admin_professional_categories.php"><i class="fa fa-circle-o"></i> ক্যাটাগরি ম্যানেজ করুন</a></li>
        <li><a href="admin_professionals.php"><i class="fa fa-circle-o"></i> সকল প্রফেশনাল দেখুন</a></li>
        <li><a href="admin_professional_reviews.php"><i class="fa fa-circle-o"></i> প্রফেশনাল রিভিউ দেখুন</a></li>
    </ul>
</li>

<!-- You'd add similar sections for Professionals, Restaurants, Drivers etc. -->
			        <li class="treeview <?php if( ($cur_page == 'page.php') ) {echo 'active';} ?>">
			          <a href="page.php">
			            <i class="fa fa-tasks"></i> <span>Page Settings</span>
			          </a>
			        </li>
<li class="treeview <?php if($cur_page == 'language.php') {echo 'active';} ?>">
			          <a href="language.php">
			           <i class="fa-solid fa-globe"></i> <span>Language Converter</span>
			          </a>
			        </li>
			        <li class="treeview <?php if( ($cur_page == 'social-media.php') ) {echo 'active';} ?>">
			          <a href="social-media.php">
			            <i class="fa fa-globe"></i> <span>Social Media</span>
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