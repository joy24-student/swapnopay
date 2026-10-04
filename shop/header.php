<?php
ob_start();
// Check if session is already active before starting
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database config and functions using require_once to prevent redeclaration errors
require_once("admin/inc/config.php");
require_once("admin/inc/functions.php"); 
require_once("admin/inc/CSRF_Protect.php");
$csrf = new CSRF_Protect();
$error_message = '';
$success_message = '';
$error_message1 = '';
$success_message1 = '';

// Getting all language variables (cached for performance)
$langCacheFile = __DIR__ . '/admin/inc/cache_lang.json';
$langValues = null;
if (file_exists($langCacheFile) && (time() - filemtime($langCacheFile) < 86400)) {
    $langValues = json_decode(file_get_contents($langCacheFile), true);
}
if (!$langValues) {
    $langValues = $pdo->query("SELECT lang_value FROM tbl_language ORDER BY lang_id")->fetchAll(PDO::FETCH_COLUMN);
    @file_put_contents($langCacheFile, json_encode($langValues));
}
$i = 1;
foreach ($langValues as $lv) {
    if (!defined('LANG_VALUE_'.$i)) {
        define('LANG_VALUE_'.$i, $lv);
    }
    $i++;
}

// Fetch general website settings (cached for 60 seconds)
$settingsCacheFile = __DIR__ . '/admin/inc/cache_settings.json';
$settings = null;
if (file_exists($settingsCacheFile) && (time() - filemtime($settingsCacheFile) < 60)) {
    $settings = json_decode(file_get_contents($settingsCacheFile), true);
}
if (!$settings) {
    $settings = $pdo->query("SELECT * FROM tbl_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
    @file_put_contents($settingsCacheFile, json_encode($settings));
} 

// Assign settings variables
$logo = $settings['logo'] ?? 'default_logo.png';
$favicon = $settings['favicon'] ?? 'default_favicon.png';
$contact_email = $settings['contact_email'] ?? 'Not added';
$contact_phone = $settings['contact_phone'] ?? 'Not added ';
$meta_title_home = $settings['meta_title_home'] ?? 'E-commerce Website';
$meta_keyword_home = $settings['meta_keyword_home'] ?? 'ecommerce, shop, online store';
$meta_description_home = $settings['meta_description_home'] ?? 'Your one-stop online shop.';
$before_head = $settings['before_head'] ?? '';
$after_body = $settings['after_body'] ?? '';

// New settings for feature visibility
$wishlist_feature_on_off = $settings['wishlist_feature_on_off'] ?? 1;
$compare_feature_on_off = $settings['compare_feature_on_off'] ?? 0;
$store_feature_on_off = $settings['store_feature_on_off'] ?? 0;


// Order expiration belongs in a scheduled, transactional job. Page views never mutate orders.

// Meta tags for dynamic pages
$cur_page = basename($_SERVER["SCRIPT_NAME"]); 
$page_meta_title = $meta_title_home;
$page_meta_keyword = $meta_keyword_home;
$page_meta_description = $meta_description_home;
$og_photo = $logo; 
$og_title = $meta_title_home;
$og_slug = $cur_page;
$og_description = $meta_description_home;

// Override meta tags for specific pages
if ($cur_page == 'about.php') {
    $stmt_page = $pdo->prepare("SELECT about_meta_title, about_meta_keyword, about_meta_description FROM tbl_page WHERE id=1");
    $stmt_page->execute();
    $page_data = $stmt_page->fetch(PDO::FETCH_ASSOC);
    if ($page_data) {
        $page_meta_title = $page_data['about_meta_title'];
        $page_meta_keyword = $page_data['about_meta_keyword'];
        $page_meta_description = $page_data['about_meta_description'];
    }
}

if ($cur_page == 'product.php' && isset($_REQUEST['id'])) {
    $statement_product = $pdo->prepare("SELECT p_name, p_featured_photo, p_description FROM tbl_product WHERE p_id=?");
    $statement_product->execute(array($_REQUEST['id']));
    $product_row = $statement_product->fetch(PDO::FETCH_ASSOC);
    if ($product_row) {
        $og_photo = $product_row['p_featured_photo'];
        $og_title = $product_row['p_name'];
        $og_slug = 'product.php?id=' . $_REQUEST['id'];
        $og_description = substr(strip_tags($product_row['p_description']), 0, 200) . '...';
        $page_meta_title = $og_title; 
        $page_meta_description = $og_description;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
    <meta name="robots" content="index, follow">

    <title><?php echo htmlspecialchars($page_meta_title); ?></title>
    <meta name="keywords" content="<?php echo htmlspecialchars($page_meta_keyword); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($page_meta_description); ?>">

    <link rel="icon" type="image/png" href="assets/uploads/<?php echo htmlspecialchars($favicon); ?>">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"> 
    <link rel="stylesheet" href="assets/css/owl.carousel.min.css">
    <link rel="stylesheet" href="assets/css/owl.theme.default.min.css">
    <link rel="stylesheet" href="assets/css/jquery.bxslider.min.css">
    <link rel="stylesheet" href="assets/css/magnific-popup.css">
    <link rel="stylesheet" href="assets/css/rating.css">
    <link rel="stylesheet" href="assets/css/spacing.css">
    <link rel="stylesheet" href="assets/css/bootstrap-touch-slider.css">
    <link rel="stylesheet" href="assets/css/animate.min.css">
    <link rel="stylesheet" href="assets/css/tree-menu.css">
    <link rel="stylesheet" href="assets/css/select2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/modern_shop.css">

    <?php if ($cur_page == 'blog-single.php' || $cur_page == 'product.php'): ?>
        <meta property="og:title" content="<?php echo htmlspecialchars($og_title); ?>">
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?php echo htmlspecialchars(BASE_URL . $og_slug); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>">
        <meta property="og:image" content="assets/uploads/<?php echo htmlspecialchars($og_photo); ?>">
    <?php endif; ?>

    <?php echo $before_head; ?>

    <style>
        /* General Reset & Body */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            outline: none !important;
        }

        *:focus,
        *:focus-visible,
        *:focus-within,
        *:hover,
        *:active {
            outline: none !important;
            outline-color: transparent !important;
            outline-width: 0 !important;
            outline-style: none !important;
        }

        a,
        a:hover,
        a:focus,
        a:active,
        a:focus-visible,
        button,
        button:hover,
        button:focus,
        button:active,
        button:focus-visible {
            outline: none !important;
            outline-style: none !important;
            -webkit-tap-highlight-color: transparent;
        }

        .sn-nav-link,
        .sn-nav-link:hover,
        .sn-nav-link:focus,
        .sn-nav-link:active,
        .sn-nav-link:focus-visible {
            outline: none !important;
            border: none !important;
            box-shadow: none !important;
        }

        .sn-flash-card,
        .sn-flash-card:hover,
        .sn-flash-card:focus,
        .sn-flash-card:active,
        .sn-flash-card:focus-visible,
        .sn-product-card,
        .sn-product-card:hover,
        .sn-product-card:focus,
        .sn-product-card:active,
        .sn-product-card:focus-visible,
        .sn-shira-card,
        .sn-shira-card:hover,
        .sn-shira-card:focus,
        .sn-shira-card:active,
        .sn-shira-card:focus-visible,
        .sn-category-scroll-item,
        .sn-category-scroll-item:hover,
        .sn-category-scroll-item:focus,
        .sn-category-scroll-item:active,
        .sn-category-scroll-item:focus-visible {
            outline: none !important;
        }

        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            padding-top: 0;
            padding-bottom: 0;
        }

        /* Main Header Styles */
        .main-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 10px 20px;
            position: relative;
            z-index: 990;
        }

        .main-header .container-fluid {
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }

        /* Desktop Header Content */
        .desktop-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        
        /* NEW: Wrapper for Hamburger and Logo */
        .desktop-logo-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* NEW: Desktop Menu Toggle Button */
        #desktop-menu-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        #desktop-menu-btn:hover {
            color: #007bff;
        }

        .header-logo a {
            display: block;
        }

        .header-logo img {
            max-height: 45px;
            width: auto;
        }

        /* Desktop Search Bar */
        .header-search {
            flex-grow: 1;
            max-width: 600px;
            border: 2px solid #007bff;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            display: none;
        }

        .header-search.active {
            display: flex;
        }

        .header-search:focus-within {
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        .header-search input {
            flex-grow: 1;
            padding: 10px 15px;
            border: none;
            outline: none;
            font-size: 1em;
            color: #333;
            background-color: #f8f8f8;
        }

        .header-search button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.2s ease;
        }
        .header-search button:hover {
            background-color: #0056b3;
        }

        /* Search Suggestions Dropdown */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 0 0 4px 4px;
        }

        .search-suggestions.active {
            display: block;
        }

        .suggestion-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.2s;
        }

        .suggestion-item:hover {
            background-color: #f8f9fa;
        }

        .suggestion-item img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }

        .suggestion-content {
            flex: 1;
        }

        .suggestion-name {
            font-weight: 500;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .suggestion-price {
            font-size: 0.85em;
            color: #e74c3c;
            font-weight: 600;
        }

        .header-search {
            position: relative;
        }

        .header-action-icons {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-action-icons .icon-btn {
            background: none;
            border: none;
            font-size: 1.3em;
            color: #555;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .header-action-icons .icon-btn:hover {
            background-color: #f0f0f0;
            color: #007bff;
        }
        .header-action-icons .icon-btn.active {
            color: #007bff;
        }

        .header-user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-user-actions .user-link {
            color: #333;
            text-decoration: none;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .header-user-actions .user-link:hover {
            background-color: #f0f0f0;
            color: #007bff;
        }

        .header-user-actions .cart-link {
            position: relative;
            color: #333;
            text-decoration: none;
            font-size: 1.5em;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .header-user-actions .cart-link:hover {
            background-color: #f0f0f0;
            color: #e74c3c;
        }

        .header-user-actions .cart-count {
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 3px 7px;
            font-size: 0.7em;
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 20px;
            text-align: center;
            border: 1px solid #fff;
        }

        /* Mobile Header Content */
        .mobile-header-content {
            display: none;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-header-content .mobile-logo img {
            max-height: 40px;
        }

        .mobile-icon-btn {
            background: none;
            border: none;
            font-size: 1.4em;
            color: #333;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.2s ease;
        }

        .mobile-icon-btn:hover {
            background-color: #f0f0f0;
        }

        .mobile-header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Mobile Search Bar */
        .mobile-search-bar-expanded {
            display: none;
            margin-top: 10px;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .mobile-search-bar-expanded.active {
            display: flex;
        }

        .mobile-search-bar-expanded input {
            flex-grow: 1;
            padding: 8px 12px;
            border: none;
            outline: none;
            font-size: 0.9em;
            background-color: #f8f8f8;
        }

        .mobile-search-bar-expanded button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease;
        }


        /* Desktop Vertical Navigation (Sidebar) */
        .desktop-sidebar {
            position: fixed;
            top: 0;
            left: -250px; /* Hidden by default */
            width: 250px;
            height: 100%;
            background-color: #2c3e50;
            color: #ecf0f1;
            padding-top: 20px;
            transition: left 0.3s ease-in-out;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }

        .desktop-sidebar.active {
            left: 0; 
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 15px 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-logo img {
            max-height: 50px;
            width: auto;
        }

        .sidebar-toggle-btn {
            background: none;
            border: none;
            color: #ecf0f1;
            font-size: 1.5em;
            cursor: pointer;
            padding: 5px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
        }

        .sidebar-menu li {
            position: relative;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color 0.2s ease, color 0.2s ease;
            font-size: 1.1em;
        }

        .sidebar-menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar-menu li a:hover {
            background-color: #34495e;
            color: #fff;
        }

        /* Submenu arrows */
        .sidebar-menu .submenu-arrow,
        .sidebar-menu .submenu-arrow-level-1,
        .sidebar-menu .submenu-arrow-level-2 {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 0.8em;
        }

        .sidebar-menu li.has-submenu.open > a .submenu-arrow,
        .sidebar-menu li.has-submenu-level-1.open > a .submenu-arrow-level-1,
        .sidebar-menu li.has-submenu-level-2.open > a .submenu-arrow-level-2 {
            transform: rotate(90deg);
        }


        .sidebar-menu .submenu,
        .sidebar-menu .submenu-level-2,
        .sidebar-menu .submenu-level-3 {
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: #34495e;
            display: none;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            max-height: 0;
        }

        .sidebar-menu .submenu.open,
        .sidebar-menu .submenu-level-2.open,
        .sidebar-menu .submenu-level-3.open {
            max-height: 500px;
            display: block;
        }

        .sidebar-menu .submenu li a,
        .sidebar-menu .submenu-level-2 li a,
        .sidebar-menu .submenu-level-3 li a {
            padding-left: 35px;
            font-size: 1em;
        }

        .sidebar-menu .submenu-level-2 li a {
            padding-left: 50px;
        }

        .sidebar-menu .submenu-level-3 li a {
            padding-left: 65px;
        }

        .sidebar-menu .submenu li a:hover,
        .sidebar-menu .submenu-level-2 li a:hover,
        .sidebar-menu .submenu-level-3 li a:hover {
            background-color: #49637c;
        }

        /* Mobile Menu Overlay */
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        /* Main content wrapper */
        .content-wrapper-main {
            transition: margin-left 0.3s ease-in-out;
            margin-left: 0;
            padding-top: 0;
            padding-bottom: 0;
        }
        
        /* Content Pushed State */
        .content-wrapper-main.sidebar-active {
            margin-left: 250px;
        }

        /* ============================================================
           PIXEL-PERFECT MOBILE BOTTOM NAVIGATION DOCK (CART-CONSISTENT)
           ============================================================ */
        .mobile-bottom-nav,
        .sn-mobile-bottom-nav {
            display: none;
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            height: 56px !important;
            min-height: 56px !important;
            max-height: 56px !important;
            background: #ffffff !important;
            border-top: 1px solid #f1f5f9 !important;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.04) !important;
            z-index: 1000 !important;
            align-items: center !important;
            justify-content: space-around !important;
            padding: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            transition: none !important;
        }

        .mobile-bottom-nav .sn-dock-item,
        .sn-mobile-bottom-nav .sn-dock-item,
        .mobile-bottom-nav .nav-item {
            flex: 1 1 0 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            height: 56px !important;
            max-height: 56px !important;
            padding: 4px 2px !important;
            margin: 0 !important;
            text-decoration: none !important;
            color: #64748b !important;
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            position: relative !important;
            cursor: pointer !important;
            box-sizing: border-box !important;
            -webkit-tap-highlight-color: transparent !important;
            transition: color 0.15s ease !important;
        }

        .mobile-bottom-nav .sn-dock-item:hover,
        .mobile-bottom-nav .sn-dock-item:focus,
        .mobile-bottom-nav .sn-dock-item:active,
        .sn-mobile-bottom-nav .sn-dock-item:hover,
        .sn-mobile-bottom-nav .sn-dock-item:focus,
        .sn-mobile-bottom-nav .sn-dock-item:active {
            text-decoration: none !important;
            outline: none !important;
        }

        .mobile-bottom-nav .sn-dock-item.active,
        .sn-mobile-bottom-nav .sn-dock-item.active,
        .mobile-bottom-nav .nav-item.active {
            color: #0f172a !important;
            background: transparent !important;
            border-radius: 0 !important;
            padding: 4px 2px !important;
            flex-direction: column !important;
            gap: 0 !important;
        }

        .mobile-bottom-nav .sn-dock-icon-box,
        .sn-mobile-bottom-nav .sn-dock-icon-box {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
            height: 22px !important;
            line-height: 1 !important;
            margin: 0 0 2px 0 !important;
            background: transparent !important;
        }

        .mobile-bottom-nav .sn-dock-icon-box svg,
        .sn-mobile-bottom-nav .sn-dock-icon-box svg {
            width: 20px !important;
            height: 20px !important;
            display: block !important;
            flex-shrink: 0 !important;
            stroke: #64748b !important;
            stroke-width: 2px !important;
            transition: stroke 0.15s ease, fill 0.15s ease !important;
        }

        .mobile-bottom-nav .sn-dock-item.active .sn-dock-icon-box svg,
        .sn-mobile-bottom-nav .sn-dock-item.active .sn-dock-icon-box svg {
            stroke: #0f172a !important;
        }

        .mobile-bottom-nav .sn-dock-item.active svg[fill="currentColor"],
        .sn-mobile-bottom-nav .sn-dock-item.active svg[fill="currentColor"] {
            fill: #0f172a !important;
        }

        .mobile-bottom-nav .sn-dock-badge-count,
        .sn-mobile-bottom-nav .sn-dock-badge-count {
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #0f172a !important;
            margin-left: 3px !important;
            line-height: 1 !important;
            display: inline-block !important;
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
        }

        .mobile-bottom-nav .sn-dock-item > span:not(.sn-dock-badge-count),
        .sn-mobile-bottom-nav .sn-dock-item > span:not(.sn-dock-badge-count),
        .mobile-bottom-nav .nav-item span {
            font-size: 11px !important;
            font-weight: 600 !important;
            line-height: 1.15 !important;
            letter-spacing: -0.2px !important;
            color: #64748b !important;
            text-align: center !important;
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            transition: color 0.15s ease !important;
        }

        .mobile-bottom-nav .sn-dock-item.active > span:not(.sn-dock-badge-count),
        .sn-mobile-bottom-nav .sn-dock-item.active > span:not(.sn-dock-badge-count),
        .mobile-bottom-nav .nav-item.active span {
            color: #0f172a !important;
            font-weight: 700 !important;
        }


        /* Responsive Overrides */
        @media (max-width: 991px) { /* Tablets and Mobile */
            .desktop-header-content {
                display: none;
            }
            .mobile-header-content {
                display: flex;
            }
            .main-header {
                padding: 8px 15px;
                position: sticky;
                top: 0;
                width: 100%;
                box-sizing: border-box;
            }

            .mobile-search-bar-expanded.active {
                display: flex;
            }

            .desktop-sidebar {
                left: -250px;
                box-shadow: 3px 0 15px rgba(0,0,0,0.3);
            }

            .mobile-bottom-nav,
            .sn-mobile-bottom-nav {
                display: flex !important;
            }

            body {
                padding-top: 60px;
                padding-bottom: 60px;
            }
            .content-wrapper-main {
                min-height: calc(100vh - 60px - 60px);
            }
        }

        @media (min-width: 992px) { /* Desktop */
            .desktop-header-content {
                display: flex;
            }
            .mobile-header-content, .mobile-search-bar-expanded {
                display: none !important;
            }
            .mobile-bottom-nav,
            .sn-mobile-bottom-nav {
                display: none !important;
            }
            .desktop-sidebar {
                position: fixed;
            }
            .content-wrapper-main {
                min-height: 100vh;
                padding-bottom: 0;
                padding-top: 0;
            }
            body {
                padding-top: 0;
                padding-bottom: 0;
            }
                /* Fixed header on desktop: full width by default, shifts when sidebar active */
                .main-header {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    width: 100%;
                    z-index: 995;
                    transition: left 0.25s ease, width 0.25s ease;
                }
                /* When sidebar is visible, push header right and reduce width */
                .main-header.with-sidebar {
                    left: 250px;
                    width: calc(100% - 250px);
                }
                /* Modern ShopNext storefront uses sticky/in-flow header */
                .content-wrapper-main {
                    margin-top: 0 !important;
                    padding-top: 0 !important;
                }
                .sn-nav-bar {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 28px !important;
                    width: 100% !important;
                    margin: 0 auto !important;
                    padding-bottom: 4px !important;
                }
        }

        body.shopnext-theme,
        body.shopnext-theme .content-wrapper-main {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Smooth Search Input Hide / Show on Scroll */
        .sn-search-form {
            max-height: 70px;
            transition: max-height 0.32s cubic-bezier(0.2, 0.8, 0.2, 1),
                        opacity 0.25s ease,
                        transform 0.32s cubic-bezier(0.2, 0.8, 0.2, 1),
                        margin 0.28s ease,
                        padding 0.28s ease;
            transform-origin: top center;
        }

        .sn-search-form.sn-search-hidden {
            max-height: 0 !important;
            opacity: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            transform: translateY(-8px) scaleY(0.8) !important;
            pointer-events: none !important;
            overflow: hidden !important;
        }

        @media (min-width: 769px) {
            .sn-search-form {
                max-width: 580px;
                transition: max-width 0.32s cubic-bezier(0.2, 0.8, 0.2, 1),
                            max-height 0.32s cubic-bezier(0.2, 0.8, 0.2, 1),
                            opacity 0.25s ease,
                            transform 0.32s cubic-bezier(0.2, 0.8, 0.2, 1),
                            margin 0.28s ease;
            }
            .sn-search-form.sn-search-hidden {
                max-width: 0 !important;
                opacity: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                transform: scale(0.95) !important;
                pointer-events: none !important;
                overflow: hidden !important;
            }
        }

        @media (max-width: 768px) {
            .sn-header-wrap {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                background: #ffffff !important;
                border-bottom: 1px solid #f1f5f9 !important;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05) !important;
                z-index: 1000 !important;
                transition: box-shadow 0.25s ease;
            }
            body.shopnext-theme .content-wrapper-main {
                padding-top: 96px !important;
                padding-bottom: 72px !important;
                transition: padding-top 0.3s ease;
            }
            body.shopnext-theme.sn-search-scrolled .content-wrapper-main {
                padding-top: 56px !important;
            }
        }


        /* --- Horizontal Scroll Wrapper --- */
.horizontal-scroll-wrapper {
    display: flex;
    overflow-x: auto;
    gap: 20px;
    padding-bottom: 20px;
    scrollbar-width: thin; /* Firefox */
}
.horizontal-scroll-wrapper::-webkit-scrollbar {
    height: 6px;
}
.horizontal-scroll-wrapper::-webkit-scrollbar-thumb {
    background: #007bff;
    border-radius: 10px;
}
/* Fixed width for items in scroll */
.horizontal-scroll-wrapper .product-card,
.horizontal-scroll-wrapper .cat-item {
    flex: 0 0 240px; /* Adjust width as needed */
    width: 240px;
}

/* --- Round Category Images --- */
.cat-item.round-style .cat-img-box {
    width: 120px;
    height: 120px;
    margin: 0 auto 15px auto;
}
.cat-item.round-style .cat-img-box img {
    border-radius: 50%;
    width: 100%;
    height: 100%;
    object-fit: cover;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

/* --- Product Buttons Invisible until Hover --- */
.product-card .hover-btns {
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    transition: all 0.3s ease-in-out;
    bottom: 10px; /* Position it */
}
.product-card:hover .hover-btns {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

/* --- Scroll Top Button --- */
#scrollTopBtn {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 30px;
    z-index: 9999;
    border: none;
    outline: none;
    background-color: #007bff;
    color: white;
    cursor: pointer;
    padding: 15px;
    border-radius: 50%;
    font-size: 18px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
#scrollTopBtn:hover {
    background-color: #555;
}

/* --- Modern Welcome Animated Popup Modal --- */
.custom-popup {
    display: none;
    position: fixed;
    z-index: 999999;
    left: 0; top: 0;
    width: 100vw; height: 100vh;
    background-color: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    overflow-y: auto;
    padding: 20px;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.custom-popup.sn-popup-active {
    display: flex !important;
    opacity: 1;
}
.custom-popup-content {
    background: #ffffff;
    width: 100%;
    max-width: 480px;
    margin: auto;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.15);
    position: relative;
    text-align: center;
    transform-origin: center center;
}
.close-popup {
    position: absolute;
    top: 14px; right: 14px;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.95);
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    color: #1e293b;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.close-popup:hover {
    background: #ef4444;
    color: #ffffff;
    transform: rotate(90deg) scale(1.1);
}
.sn-popup-banner-link {
    display: block;
    width: 100%;
    overflow: hidden;
    background: #f1f5f9;
}
.sn-popup-banner-img {
    width: 100%;
    max-height: 260px;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}
.sn-popup-banner-link:hover .sn-popup-banner-img {
    transform: scale(1.03);
}
.sn-popup-body {
    padding: 22px 24px 26px 24px;
}
.sn-popup-title {
    margin: 0 0 8px 0;
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
}
.sn-popup-desc {
    margin: 0 0 16px 0;
    font-size: 14px;
    color: #475569;
    line-height: 1.5;
}

/* Urgency Countdown Timer */
.sn-popup-countdown {
    background: linear-gradient(135deg, #fef2f2, #fff1f2);
    border: 1px solid #fecdd3;
    border-radius: 12px;
    padding: 10px 14px;
    margin-bottom: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.sn-timer-label {
    font-size: 12px;
    font-weight: 700;
    color: #e11d48;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.sn-timer-boxes {
    display: flex;
    align-items: center;
    gap: 6px;
}
.sn-timer-box {
    background: #ffffff;
    border: 1px solid #fda4af;
    border-radius: 6px;
    padding: 4px 8px;
    min-width: 44px;
    box-shadow: 0 2px 4px rgba(225, 29, 72, 0.08);
}
.sn-timer-box span {
    display: block;
    font-size: 16px;
    font-weight: 800;
    color: #be123c;
    line-height: 1;
}
.sn-timer-box small {
    display: block;
    font-size: 9px;
    color: #881337;
    text-transform: uppercase;
    margin-top: 2px;
}
.sn-timer-sep {
    font-weight: 800;
    color: #e11d48;
    font-size: 16px;
}
.sn-popup-cta-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #ffffff !important;
    font-weight: 700;
    font-size: 15px;
    padding: 12px 28px;
    border-radius: 9999px;
    text-decoration: none !important;
    width: 100%;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    transition: all 0.2s ease;
}
.sn-popup-cta-btn:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45);
}

/* --- Entrance Animations --- */
/* 1. 3D Spin & Zoom In */
.anim-spin-zoom {
    animation: snSpinZoom 0.85s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes snSpinZoom {
    0% { transform: scale(0.1) rotate3d(0, 1, 1, 360deg); opacity: 0; }
    100% { transform: scale(1) rotate(0deg); opacity: 1; }
}

/* 2. 3D Perspective Flip */
.anim-flip-3d {
    animation: snFlip3d 0.75s cubic-bezier(0.23, 1, 0.32, 1) forwards;
}
@keyframes snFlip3d {
    0% { transform: perspective(800px) rotateY(-90deg) scale(0.6); opacity: 0; }
    100% { transform: perspective(800px) rotateY(0deg) scale(1); opacity: 1; }
}

/* 3. Elastic Bounce Pop */
.anim-bounce-pop {
    animation: snBouncePop 0.7s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
}
@keyframes snBouncePop {
    0% { transform: scale(0.3); opacity: 0; }
    60% { transform: scale(1.08); opacity: 1; }
    85% { transform: scale(0.96); }
    100% { transform: scale(1); opacity: 1; }
}

/* 4. Smooth Slide Up */
.anim-slide-up {
    animation: snSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes snSlideUp {
    0% { transform: translateY(120px) scale(0.9); opacity: 0; }
    100% { transform: translateY(0) scale(1); opacity: 1; }
}

/* 5. Smooth Slide Down */
.anim-slide-down {
    animation: snSlideDown 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes snSlideDown {
    0% { transform: translateY(-120px) scale(0.9); opacity: 0; }
    100% { transform: translateY(0) scale(1); opacity: 1; }
}

/* 6. Radiant Glow Pulse */
.anim-glow-pulse {
    animation: snGlowPulse 0.8s ease-out forwards;
}
@keyframes snGlowPulse {
    0% { transform: scale(0.6); opacity: 0; box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); }
    50% { transform: scale(1.04); opacity: 1; box-shadow: 0 0 35px 10px rgba(99, 102, 241, 0.6); }
    100% { transform: scale(1); opacity: 1; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); }
}

/* 7. Pendulum Wiggle & Swing */
.anim-wiggle-swing {
    animation: snWiggleSwing 0.85s ease-in-out forwards;
}
@keyframes snWiggleSwing {
    0% { transform: scale(0.5) rotate(-18deg); opacity: 0; }
    40% { transform: scale(1.03) rotate(14deg); opacity: 1; }
    65% { transform: scale(0.98) rotate(-8deg); }
    85% { transform: scale(1.01) rotate(4deg); }
    100% { transform: scale(1) rotate(0deg); opacity: 1; }
}

/* --- Extra Footer Links --- */
.extra-footer-section {
    background: #232f3e;
    color: #fff;
    padding: 30px 0;
    margin-top: 30px;
}
.extra-footer-section a { color: #ccc; text-decoration: none; display: block; margin-bottom: 8px; }
.extra-footer-section a:hover { color: #fff; padding-left: 5px; transition: 0.2s; }
.extra-footer-section h4 { color: #fff; margin-bottom: 20px; font-size: 16px; font-weight: bold; text-transform: uppercase; }
@media(max-width: 768px) { .extra-footer-section { display: none; } }
    </style>
    <!-- jQuery Library -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="shopnext-theme <?php echo ($cur_page == 'product.php') ? 'sn-product-page' : ''; ?>">
<?php echo $after_body; ?>

<header class="sn-header-wrap">
    <div class="sn-container">
        <!-- Top Row -->
        <div class="sn-header-top">
            <!-- Brand Logo -->
            <a href="<?php echo BASE_URL; ?>" class="sn-brand-logo">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 2L3 6V20C3 20.5304 3.21071 21.0391 3.58579 21.4142C3.96086 21.7893 4.46957 22 5 22H19C19.5304 22 20.0391 21.7893 20.4142 21.4142C20.7893 21.0391 21 20.5304 21 20V6L18 2H6Z" fill="#F59E0B" stroke="#111827" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3 6H21" stroke="#111827" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 10C16 11.0609 15.5786 12.0783 14.8284 12.8284C14.0783 13.5786 13.0609 14 12 14C10.9391 14 9.92172 13.5786 9.17157 12.8284C8.42143 12.0783 8 11.0609 8 10" stroke="#111827" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Shop<span class="sn-logo-text-next">Next</span></span>
            </a>

            <!-- Search Bar -->
            <form action="<?php echo BASE_URL; ?>search-result.php" method="get" class="sn-search-form" id="sn-search-form">
                <div class="sn-search-input-wrap">
                    <svg class="sn-search-icon-left" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" name="search_text" id="sn-search-input" class="sn-search-input" placeholder="Search for products, brands and more..." autocomplete="off">
                    <button type="button" class="sn-scan-btn" title="Scan Barcode / Product" onclick="openShopAiModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                    <button type="submit" class="sn-search-btn" aria-label="Search">Search</button>
                </div>
                <div class="sn-search-suggestions" id="sn-search-suggestions"></div>
            </form>

            <!-- Actions: Bell (Mobile) + Account (Desktop) + Cart -->
            <div class="sn-header-actions">
                <!-- Notifications Bell / Deals -->
                <a href="<?php echo BASE_URL; ?>deals.php" class="sn-bell-btn" title="Special Offers & Deals">
                    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </a>

                <?php if (isset($_SESSION['customer'])): ?>
                    <a href="<?php echo BASE_URL; ?>dashboard.php" class="sn-account-btn sn-desktop-only">
                        <svg class="sn-account-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span><?php echo htmlspecialchars(explode(' ', $_SESSION['customer']['cust_name'] ?? 'Account')[0]); ?></span>
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>login.php" class="sn-account-btn sn-desktop-only">
                        <svg class="sn-account-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>Account</span>
                    </a>
                <?php endif; ?>

                <a href="<?php echo BASE_URL; ?>cart.php" class="sn-cart-btn" id="sn-header-cart-btn" aria-label="Cart">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <?php
                    $cart_item_count = 0;
                    if (!empty($_SESSION['cart_p_qty'])) {
                        foreach ($_SESSION['cart_p_qty'] as $q) { $cart_item_count += (int)$q; }
                    }
                    ?>
                    <span class="sn-cart-badge" id="sn-cart-badge-count" style="<?php echo $cart_item_count > 0 ? '' : 'display:none;'; ?>"><?php echo $cart_item_count; ?></span>
                </a>
            </div>
        </div>

        <!-- Secondary Navigation Row: Home | All Categories ▾ | Deals | New Arrivals | Brands | Contact -->
        <nav class="sn-nav-bar sn-desktop-only">
            <a href="<?php echo BASE_URL; ?>" class="sn-nav-link <?php echo ($cur_page == 'index.php' || $cur_page == '') ? 'active' : ''; ?>">Home</a>

            <div class="sn-dropdown-parent">
                <a href="<?php echo BASE_URL; ?>product-category.php?id=1&type=top-category" class="sn-nav-link">
                    <span>All Categories</span>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </a>
                <div class="sn-category-dropdown">
                    <?php
                    $navCategories = $pdo->query("SELECT tcat_id, tcat_name FROM tbl_top_category WHERE show_on_menu = 1 ORDER BY tcat_order ASC, tcat_id ASC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($navCategories as $nc):
                    ?>
                        <a href="<?php echo BASE_URL; ?>product-category.php?id=<?php echo $nc['tcat_id']; ?>&type=top-category"><?php echo htmlspecialchars($nc['tcat_name']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <a href="<?php echo BASE_URL; ?>deals.php" class="sn-nav-link <?php echo ($cur_page == 'deals.php') ? 'active' : ''; ?>">Deals</a>
            <a href="<?php echo BASE_URL; ?>product-category.php?id=2&type=top-category" class="sn-nav-link">New Arrivals</a>
            <a href="<?php echo BASE_URL; ?>product-category.php?id=3&type=top-category" class="sn-nav-link">Brands</a>
            <a href="<?php echo BASE_URL; ?>contact.php" class="sn-nav-link <?php echo ($cur_page == 'contact.php') ? 'active' : ''; ?>">Contact</a>
        </nav>
</header>

<script>
(function() {
    let lastScrollY = window.pageYOffset || document.documentElement.scrollTop;
    let isTicking = false;
    const scrollDelta = 6;

    function handleScrollSearch() {
        const currentScrollY = window.pageYOffset || document.documentElement.scrollTop;
        const searchForm = document.getElementById('sn-search-form');
        const searchInput = document.getElementById('sn-search-input');
        const body = document.body;

        if (!searchForm) return;

        // Keep search open if user is currently typing or input has focus
        if (searchInput && (document.activeElement === searchInput || searchForm.contains(document.activeElement))) {
            lastScrollY = currentScrollY;
            isTicking = false;
            return;
        }

        // At top of screen (<= 25px) -> always show search input
        if (currentScrollY <= 25) {
            searchForm.classList.remove('sn-search-hidden');
            body.classList.remove('sn-search-scrolled');
        } else if (currentScrollY > lastScrollY + scrollDelta && currentScrollY > 60) {
            // Scroll down -> hide search input field
            searchForm.classList.add('sn-search-hidden');
            body.classList.add('sn-search-scrolled');
        } else if (currentScrollY < lastScrollY - scrollDelta) {
            // Scroll up -> show search input field
            searchForm.classList.remove('sn-search-hidden');
            body.classList.remove('sn-search-scrolled');
        }

        lastScrollY = Math.max(0, currentScrollY);
        isTicking = false;
    }

    window.addEventListener('scroll', function() {
        if (!isTicking) {
            window.requestAnimationFrame(handleScrollSearch);
            isTicking = true;
        }
    }, { passive: true });
})();
</script>

<div class="desktop-sidebar">
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL; ?>" class="sidebar-logo">
            <img src="assets/uploads/<?php echo htmlspecialchars($logo); ?>" alt="Logo">
        </a>
        <button class="sidebar-toggle-btn" id="sidebar-toggle-btn">
            <i class="fas fa-times"></i> 
        </button>
    </div>
    <ul class="sidebar-menu">
        <li><a href="<?php echo BASE_URL; ?>"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="has-submenu">
            <a href="#"><i class="fas fa-th-list"></i> Categories <i class="fas fa-chevron-down submenu-arrow"></i></a>
            <ul class="submenu">
                <?php
                $menuCacheFile = __DIR__ . '/admin/inc/cache_menu.json';
                $menuData = null;
                if (file_exists($menuCacheFile) && (time() - filemtime($menuCacheFile) < 60)) {
                    $menuData = json_decode(file_get_contents($menuCacheFile), true);
                }
                if (!$menuData) {
                    $all_tcat = $pdo->query("SELECT * FROM tbl_top_category WHERE show_on_menu=1 ORDER BY tcat_id ASC")->fetchAll(PDO::FETCH_ASSOC);
                    $all_tcat = $pdo->query("SELECT * FROM tbl_top_category WHERE show_on_menu=1 ORDER BY tcat_order ASC, tcat_id ASC")->fetchAll(PDO::FETCH_ASSOC);
                    $all_mcat_raw = $pdo->query("SELECT * FROM tbl_mid_category ORDER BY mcat_id ASC")->fetchAll(PDO::FETCH_ASSOC);
                    $all_ecat_raw = $pdo->query("SELECT * FROM tbl_end_category ORDER BY ecat_id ASC")->fetchAll(PDO::FETCH_ASSOC);
                    $mcat_by_tcat = [];
                    foreach ($all_mcat_raw as $m) { $mcat_by_tcat[$m['tcat_id']][] = $m; }
                    $ecat_by_mcat = [];
                    foreach ($all_ecat_raw as $e) { $ecat_by_mcat[$e['mcat_id']][] = $e; }
                    $menuData = ['tcat' => $all_tcat, 'mcat' => $mcat_by_tcat, 'ecat' => $ecat_by_mcat];
                    @file_put_contents($menuCacheFile, json_encode($menuData));
                }
                $all_tcat = $menuData['tcat'];
                $mcat_by_tcat = $menuData['mcat'];
                $ecat_by_mcat = $menuData['ecat'];
                $GLOBALS['all_tcat'] = $all_tcat;
                $all_tcat = $menuData['tcat'] ?? [];
                $mcat_by_tcat = $menuData['mcat'] ?? [];
                $ecat_by_mcat = $menuData['ecat'] ?? [];

                foreach ($all_tcat as $row_tcat) {
                    $mid_cats = $mcat_by_tcat[$row_tcat['tcat_id']] ?? [];
                    $has_mid_categories = !empty($mid_cats);
                    ?>
                    <li class="has-submenu-level-1">
                        <a href="<?php echo BASE_URL; ?>product-category.php?id=<?php echo $row_tcat['tcat_id']; ?>&type=top-category">
                            <span class="lbl"><?php echo htmlspecialchars($row_tcat['tcat_name']); ?></span>
                            <?php if ($has_mid_categories): ?>
                                <i class="fas fa-chevron-right submenu-arrow-level-1"></i>
                            <?php endif; ?>
                        </a>
                        <?php if ($has_mid_categories): ?>
                            <ul class="submenu-level-2">
                                <?php foreach ($mid_cats as $row_mcat):
                                    $end_cats = $ecat_by_mcat[$row_mcat['mcat_id']] ?? [];
                                    $has_end_categories = !empty($end_cats);
                                ?>
                                    <li class="has-submenu-level-2">
                                        <a href="<?php echo BASE_URL; ?>product-category.php?id=<?php echo $row_mcat['mcat_id']; ?>&type=mid-category">
                                            <span class="lbl lbl1"><?php echo htmlspecialchars($row_mcat['mcat_name']); ?></span>
                                            <?php if ($has_end_categories): ?>
                                                <i class="fas fa-chevron-right submenu-arrow-level-2"></i>
                                            <?php endif; ?>
                                        </a>
                                        <?php if ($has_end_categories): ?>
                                            <ul class="submenu-level-3">
                                                <?php foreach ($end_cats as $row_ecat): ?>
                                                    <li>
                                                        <a href="<?php echo BASE_URL; ?>product-category.php?id=<?php echo $row_ecat['ecat_id']; ?>&type=end-category">
                                                            <span class="lbl lbl1"><?php echo htmlspecialchars($row_ecat['ecat_name']); ?></span>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                    <?php
                }
                ?>
            </ul>
        </li>
        <li><a href="<?php echo BASE_URL; ?>cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
        <?php if ($wishlist_feature_on_off == 1): ?>
        <li><a href="<?php echo BASE_URL; ?>wishlist.php"><i class="fas fa-heart"></i> Wishlist</a></li>
        <?php endif; ?>
        <?php if ($compare_feature_on_off == 1): ?>
        <li><a href="<?php echo BASE_URL; ?>compare.php"><i class="fas fa-balance-scale"></i> Compare</a></li>
        <?php endif; ?>
        <?php if ($store_feature_on_off == 1): ?>
        <li><a href="<?php echo BASE_URL; ?>stores.php"><i class="fas fa-store"></i> Stores</a></li>
        <?php endif; ?>
        <li><a href="<?php echo BASE_URL; ?>customer-order.php"><i class="fas fa-box-open"></i> My Orders</a></li>
        <li><a href="<?php echo BASE_URL; ?>contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
        <?php if(isset($_SESSION['customer'])): ?>
            <li><a href="<?php echo BASE_URL; ?>customer-profile-update.php"><i class="fas fa-user-circle"></i> Profile</a></li>
            <li><a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        <?php else: ?>
            <li><a href="<?php echo BASE_URL; ?>login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <li><a href="<?php echo BASE_URL; ?>registration.php"><i class="fas fa-user-plus"></i> Register</a></li>
        <?php endif; ?>
    </ul>
</div>

<div class="mobile-menu-overlay" id="mobile-menu-overlay"></div>

<div class="content-wrapper-main">
    <!-- ============================================================
         MODERN SHOPNEXT MOBILE BOTTOM NAVIGATION DOCK (PIXEL-PERFECT)
         ============================================================ -->
    <div class="mobile-bottom-nav sn-mobile-bottom-nav">
        <!-- 1. Home (Active on homepage & product detail) -->
        <a href="<?php echo BASE_URL; ?>" class="sn-dock-item <?php echo ($cur_page == 'index.php' || $cur_page == '' || $cur_page == 'product.php') ? 'active' : ''; ?>">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?php echo ($cur_page == 'index.php' || $cur_page == '' || $cur_page == 'product.php') ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </div>
            <span>Home</span>
        </a>

        <!-- 2. Deals -->
        <a href="<?php echo BASE_URL; ?>deals.php" class="sn-dock-item <?php echo ($cur_page == 'deals.php') ? 'active' : ''; ?>">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?php echo ($cur_page == 'deals.php') ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <span>Deals</span>
        </a>

        <!-- 3. Messages / AI Support -->
        <a href="javascript:void(0)" onclick="typeof openShopAiModal === 'function' ? openShopAiModal() : (document.getElementById('openAiAssistantBtn') ? document.getElementById('openAiAssistantBtn').click() : alert('Support and messages'));" class="sn-dock-item">
            <div class="sn-dock-icon-box sn-dock-has-badge">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span class="sn-dock-badge-count">5</span>
            </div>
            <span>Messages</span>
        </a>

        <!-- 4. Cart -->
        <a href="<?php echo BASE_URL; ?>cart.php" class="sn-dock-item <?php echo ($cur_page == 'cart.php') ? 'active' : ''; ?>">
            <div class="sn-dock-icon-box sn-dock-has-badge">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <?php 
                    $dock_cart_count = 0;
                    if (!empty($_SESSION['cart_p_qty'])) {
                        foreach ($_SESSION['cart_p_qty'] as $q) { $dock_cart_count += (int)$q; }
                    }
                ?>
                <span class="sn-dock-badge-count" id="sn-dock-cart-count" style="<?php echo $dock_cart_count > 0 ? '' : 'display:none;'; ?>"><?php echo $dock_cart_count; ?></span>
            </div>
            <span>Cart</span>
        </a>

        <!-- 5. Account -->
        <?php if(isset($_SESSION['customer'])): ?>
            <a href="<?php echo BASE_URL; ?>dashboard.php" class="sn-dock-item <?php echo ($cur_page == 'dashboard.php' || $cur_page == 'customer-profile-update.php' || $cur_page == 'customer-order.php') ? 'active' : ''; ?>">
                <div class="sn-dock-icon-box">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <span>Account</span>
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>login.php" class="sn-dock-item <?php echo ($cur_page == 'login.php' || $cur_page == 'registration.php') ? 'active' : ''; ?>">
                <div class="sn-dock-icon-box">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <span>Account</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- ============================================================
         GLOBAL SHOPPING AI ASSISTANT MODAL (TRIGGERED BY CENTER DOCK)
         ============================================================ -->
    <div class="sn-global-ai-backdrop" id="snGlobalAiModal" style="display:none;">
        <div class="sn-global-ai-card">
            <div class="sn-global-ai-header">
                <div class="sn-global-ai-title-wrap">
                    <div class="sn-global-ai-icon">✨</div>
                    <div>
                        <div class="sn-global-ai-title">ShopNext AI Shopping Assistant</div>
                        <div class="sn-global-ai-sub">Smart deal finder & product recommendations</div>
                    </div>
                </div>
                <button type="button" class="sn-global-ai-close" onclick="closeShopAiModal()">&times;</button>
            </div>

            <!-- Suggestion Chips Bar -->
            <div class="sn-global-ai-chips">
                <button type="button" class="sn-ai-chip" onclick="sendAiQuickPrompt('What are today\'s best flash sale deals and payday discounts?')">⚡ Top Deals Today</button>
                <button type="button" class="sn-ai-chip" onclick="sendAiQuickPrompt('Recommend the best laptop under ৳ 70,000 for work and study')">💻 Best Laptops</button>
                <button type="button" class="sn-ai-chip" onclick="sendAiQuickPrompt('What are the latest smartphones available with discount?')">📱 Smartphones</button>
                <button type="button" class="sn-ai-chip" onclick="sendAiQuickPrompt('Tell me about free shipping threshold and delivery times')">🚚 Shipping Policy</button>
            </div>

            <!-- Messages Stream -->
            <div class="sn-global-ai-chat" id="snGlobalAiMessages">
                <div class="sn-ai-bubble ai">
                    👋 Hi there! I'm your <strong>ShopNext AI Shopping Assistant</strong>.<br>Ask me anything about today's deals, flash discounts, gadget specs, or order shipping!
                </div>
            </div>

            <!-- Input Bar -->
            <div class="sn-global-ai-input-wrap">
                <input type="text" id="snGlobalAiInput" class="sn-global-ai-input" placeholder="Ask about deals, products, specifications..." onkeydown="if(event.key==='Enter') sendShopAiMessage()">
                <button type="button" class="sn-global-ai-send" onclick="sendShopAiMessage()" aria-label="Send">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const desktopMenuBtn = document.getElementById('desktop-menu-btn'); // NEW BUTTON
    const desktopSidebar = document.querySelector('.desktop-sidebar');
    const contentWrapperMain = document.querySelector('.content-wrapper-main');
    const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
    const sidebarCloseBtn = document.getElementById('sidebar-toggle-btn');
    
    const mobileSearchToggle = document.getElementById('mobile-search-toggle');
    const mobileSearchBarExpanded = document.getElementById('mobile-search-bar-expanded');

    const desktopSearchToggle = document.getElementById('desktop-search-toggle');
    const desktopSearchBar = document.getElementById('desktop-search-bar');
    const desktopSearchInput = document.getElementById('desktop-search-input');
    const desktopSearchSubmitBtn = document.getElementById('desktop-search-submit-btn');
    const mobileSearchInput = document.getElementById('mobile-search-input');
    const mobileSearchSubmitBtnMobile = document.getElementById('mobile-search-submit-btn-mobile');

    const hasSubmenus = document.querySelectorAll('.sidebar-menu li.has-submenu, .sidebar-menu li.has-submenu-level-1, .sidebar-menu li.has-submenu-level-2');

    function openSidebar() {
        desktopSidebar.classList.add('active');
        contentWrapperMain.classList.add('sidebar-active');
        mobileMenuOverlay.style.display = 'block';
        // adjust header and content after opening
        adjustHeaderForSidebar();
    }

    function closeSidebar() {
        desktopSidebar.classList.remove('active');
        contentWrapperMain.classList.remove('sidebar-active');
        mobileMenuOverlay.style.display = 'none';
        
        hasSubmenus.forEach(item => {
            if (item.classList.contains('open')) {
                item.classList.remove('open');
                const submenu = item.querySelector('ul');
                if (submenu) {
                    submenu.style.maxHeight = '0';
                    setTimeout(() => {
                        submenu.style.display = 'none';
                    }, 300);
                }
            }
        });
        // adjust header and content after closing
        adjustHeaderForSidebar();
    }

    // Toggle sidebar on mobile menu button click
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            if (desktopSidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    // NEW: Toggle sidebar on DESKTOP menu button click
    if (desktopMenuBtn) {
        desktopMenuBtn.addEventListener('click', function() {
            if (desktopSidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    // Close sidebar when clicking the close button inside sidebar
    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', function() {
            closeSidebar();
        });
    }

    // Close sidebar when clicking outside
    if (mobileMenuOverlay) {
        mobileMenuOverlay.addEventListener('click', function() {
            closeSidebar();
        });
    }

    hasSubmenus.forEach(item => {
        const link = item.querySelector('a');
        const submenu = item.querySelector('ul');

        if (link && submenu) {
            link.addEventListener('click', function(e) {
                const isToggleLink = link.getAttribute('href') === '#' || 
                                     link.getAttribute('href').startsWith('javascript:') ||
                                     link.querySelector('.submenu-arrow') ||
                                     link.querySelector('.submenu-arrow-level-1') ||
                                     link.querySelector('.submenu-arrow-level-2');

                if (isToggleLink) {
                    e.preventDefault();
                    const parentUl = item.closest('ul');
                    if (parentUl) {
                        parentUl.querySelectorAll('li.has-submenu.open, li.has-submenu-level-1.open, li.has-submenu-level-2.open').forEach(otherItem => {
                            if (otherItem !== item) {
                                otherItem.classList.remove('open');
                                const otherSubmenu = otherItem.querySelector('ul');
                                if (otherSubmenu) {
                                    otherSubmenu.style.maxHeight = '0';
                                    setTimeout(() => {
                                        otherSubmenu.style.display = 'none';
                                    }, 300);
                                }
                            }
                        });
                    }

                    item.classList.toggle('open');
                    if (item.classList.contains('open')) {
                        submenu.style.display = 'block';
                        submenu.style.maxHeight = submenu.scrollHeight + 'px';
                    } else {
                        submenu.style.maxHeight = '0';
                        setTimeout(() => {
                            submenu.style.display = 'none';
                        }, 300);
                    }
                }
            });
        }
    });

    // Search Autocomplete Functionality
    let searchTimeout;
    const desktopSuggestionsDiv = document.getElementById('sn-search-suggestions') || document.getElementById('desktop-search-suggestions');
    const activeSearchInput = document.getElementById('sn-search-input') || document.getElementById('desktop-search-input');

    if (activeSearchInput && desktopSuggestionsDiv) {
        activeSearchInput.addEventListener('input', function() {
            const query = this.value.trim();

            clearTimeout(searchTimeout);

            if (query.length < 2) {
                desktopSuggestionsDiv.classList.remove('active');
                desktopSuggestionsDiv.innerHTML = '';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`<?php echo BASE_URL; ?>search_suggestions.php?query=${encodeURIComponent(query)}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (Array.isArray(data) && data.length > 0 && !data[0].error) {
                            let html = '';
                            data.forEach(product => {
                                let imagePath = '<?php echo BASE_URL; ?>assets/images/no-image.png';
                                if (product.image) {
                                    imagePath = product.image.startsWith('http') ? product.image : `<?php echo BASE_URL; ?>assets/uploads/${product.image}`;
                                }
                                const name = product.name.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                html += `
                                    <div class="sn-suggestion-row" onclick="window.location.href='${product.url}';" style="cursor: pointer;">
                                        <img src="${imagePath}" class="sn-suggestion-img" alt="${name}" onerror="this.src='<?php echo BASE_URL; ?>assets/images/no-image.png'">
                                        <div class="sn-suggestion-info">
                                            <div class="sn-suggestion-title">${name}</div>
                                            <div class="sn-suggestion-price">৳ ${parseFloat(product.price).toLocaleString()}</div>
                                        </div>
                                    </div>
                                `;
                            });
                            desktopSuggestionsDiv.innerHTML = html;
                            desktopSuggestionsDiv.classList.add('active');
                        } else {
                            desktopSuggestionsDiv.innerHTML = '<div style="padding: 12px 16px; color: #999; font-size: 13px;">No products found</div>';
                            desktopSuggestionsDiv.classList.add('active');
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        desktopSuggestionsDiv.classList.remove('active');
                    });
            }, 250);
        });

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#sn-search-form') && !e.target.closest('#desktop-search-bar')) {
                desktopSuggestionsDiv.classList.remove('active');
            }
        });
    }

    function performSearch(inputElement) {
        const searchText = inputElement.value.trim();
        if (searchText) {
            window.location.href = `<?php echo BASE_URL; ?>search-result.php?search_text=${encodeURIComponent(searchText)}`;
        } else {
            inputElement.focus();
        }
    }

    if (desktopSearchToggle) {
        desktopSearchToggle.addEventListener('click', function() {
            desktopSearchBar.classList.toggle('active');
            this.classList.toggle('active');
            if (desktopSearchBar.classList.contains('active')) {
                desktopSearchInput.focus();
            } else {
                desktopSearchInput.value = '';
            }
        });
    }

    if (desktopSearchSubmitBtn) {
        desktopSearchSubmitBtn.addEventListener('click', function() {
            performSearch(desktopSearchInput);
        });
        desktopSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch(desktopSearchInput);
            }
        });
    }

    if (mobileSearchToggle) {
        mobileSearchToggle.addEventListener('click', function() {
            mobileSearchBarExpanded.classList.toggle('active');
            if (mobileSearchBarExpanded.classList.contains('active')) {
                mobileSearchInput.focus();
            } else {
                mobileSearchInput.value = '';
            }
        });
    }

    if (mobileSearchSubmitBtnMobile) {
        mobileSearchSubmitBtnMobile.addEventListener('click', function() {
            performSearch(mobileSearchInput);
        });
        mobileSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch(mobileSearchInput);
            }
        });
    }

    // Ensure sidebar is closed on larger screens (default closed) and on resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            desktopSidebar.classList.remove('active');
            contentWrapperMain.classList.remove('sidebar-active');
            mobileSearchBarExpanded.classList.remove('active');
        } else {
            desktopSidebar.classList.remove('active');
            contentWrapperMain.classList.remove('sidebar-active');
            desktopSearchBar.classList.remove('active');
            desktopSearchToggle.classList.remove('active');
        }
        // adjust header sizing and content spacing on resize
        adjustHeaderForSidebar();
    });

    // Initial check for desktop view to ensure sidebar is closed by default
    if (window.innerWidth >= 992) {
        desktopSidebar.classList.remove('active');
        contentWrapperMain.classList.remove('sidebar-active');
    }
    // helper to adjust header position/width and content top margin
    const mainHeader = document.querySelector('.main-header');
    function adjustHeaderForSidebar() {
        if (contentWrapperMain && (document.querySelector('.sn-header-wrap') || !mainHeader)) {
            contentWrapperMain.style.marginTop = '0px';
            const snHeader = document.querySelector('.sn-header-wrap');
            if (snHeader && window.innerWidth <= 768) {
                contentWrapperMain.style.paddingTop = snHeader.offsetHeight + 'px';
            } else if (snHeader) {
                contentWrapperMain.style.paddingTop = '0px';
            }
            return;
        }
        if (!mainHeader || !contentWrapperMain) return;
        // compute header height and apply as top margin to content
        const headerHeight = mainHeader.offsetHeight || 65;
        if (window.innerWidth >= 992) {
            if (desktopSidebar.classList.contains('active')) {
                mainHeader.classList.add('with-sidebar');
                mainHeader.style.left = '250px';
                mainHeader.style.width = 'calc(100% - 250px)';
            } else {
                mainHeader.classList.remove('with-sidebar');
                mainHeader.style.left = '0';
                mainHeader.style.width = '100%';
            }
            contentWrapperMain.style.marginTop = headerHeight + 'px';
        } else {
            // mobile: ensure header occupies full width and content margin
            mainHeader.classList.remove('with-sidebar');
            mainHeader.style.left = '0';
            mainHeader.style.width = '100%';
            // on mobile header is sticky; leave small margin if necessary
            contentWrapperMain.style.marginTop = headerHeight + 'px';
        }
    }

    // ensure correct layout on initial load
    adjustHeaderForSidebar();
});// Scroll Top Button Logic
var mybutton = document.getElementById("scrollTopBtn");
window.onscroll = function() {scrollFunction()};
function scrollFunction() {
  if (mybutton) {
      if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        mybutton.style.display = "block";
      } else {
        mybutton.style.display = "none";
      }
  }
}
function topFunction() {
  window.scrollTo({top: 0, behavior: 'smooth'});
}

// --- Welcome Animated Popup Logic & Urgency Countdown ---
document.addEventListener("DOMContentLoaded", function(){
    <?php if(($settings['popup_on_off']??0) == 1): ?>
    var popupFreq = '<?php echo htmlspecialchars($settings['popup_show_again'] ?? 'session'); ?>';
    var shouldShow = false;

    if (popupFreq === 'always') {
        shouldShow = true;
    } else if (popupFreq === 'session') {
        shouldShow = !sessionStorage.getItem('sn_popup_shown_session');
    } else if (popupFreq === '24hours') {
        var lastShown = localStorage.getItem('sn_popup_shown_24h');
        if (!lastShown || (Date.now() - parseInt(lastShown, 10)) > 24 * 60 * 60 * 1000) {
            shouldShow = true;
        }
    } else if (popupFreq === 'once') {
        shouldShow = !localStorage.getItem('sn_popup_shown_forever');
    }

    if (shouldShow) {
        var delayMs = <?php echo max(0, (int)($settings['popup_delay'] ?? 2)) * 1000; ?>;
        setTimeout(function(){
            var popup = document.getElementById('promoPopup');
            if (popup) {
                popup.classList.add('sn-popup-active');
            }
        }, delayMs);
    }

    // Countdown Timer Engine
    <?php if(($settings['popup_countdown_on_off']??1) == 1): ?>
    (function initPopupCountdown() {
        var rawEnd = '<?php echo trim($settings['popup_countdown_end'] ?? ''); ?>';
        var endTime;

        if (rawEnd) {
            var parsed = new Date(rawEnd.replace(/-/g, '/')).getTime();
            endTime = (!isNaN(parsed) && parsed > Date.now()) ? parsed : (Date.now() + 2 * 60 * 60 * 1000);
        } else {
            // Rolling 2-hour urgency countdown stored in sessionStorage
            var storedEnd = sessionStorage.getItem('sn_popup_rolling_end');
            if (storedEnd && parseInt(storedEnd, 10) > Date.now()) {
                endTime = parseInt(storedEnd, 10);
            } else {
                endTime = Date.now() + (2 * 60 * 60 * 1000);
                sessionStorage.setItem('sn_popup_rolling_end', endTime);
            }
        }

        function updateTimer() {
            var diff = Math.max(0, endTime - Date.now());
            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var secs = Math.floor((diff % (1000 * 60)) / 1000);

            var elD = document.getElementById('snTimerDays');
            var elH = document.getElementById('snTimerHours');
            var elM = document.getElementById('snTimerMins');
            var elS = document.getElementById('snTimerSecs');

            if (elD) elD.textContent = String(days).padStart(2, '0');
            if (elH) elH.textContent = String(hours).padStart(2, '0');
            if (elM) elM.textContent = String(mins).padStart(2, '0');
            if (elS) elS.textContent = String(secs).padStart(2, '0');
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    })();
    <?php endif; ?>
    <?php endif; ?>

    // Popup Close Handlers
    function closeWelcomePopup() {
        var popup = document.getElementById('promoPopup');
        if (popup) {
            popup.classList.remove('sn-popup-active');
            popup.style.display = "none";
        }
        var popupFreq = '<?php echo htmlspecialchars($settings['popup_show_again'] ?? 'session'); ?>';
        if (popupFreq === 'session') sessionStorage.setItem('sn_popup_shown_session', 'true');
        if (popupFreq === '24hours') localStorage.setItem('sn_popup_shown_24h', String(Date.now()));
        if (popupFreq === 'once') localStorage.setItem('sn_popup_shown_forever', 'true');
    }

    var closeBtn = document.querySelector(".close-popup");
    if (closeBtn) {
        closeBtn.onclick = function(e) {
            e.preventDefault();
            closeWelcomePopup();
        };
    }
    var popupOverlay = document.getElementById('promoPopup');
    if (popupOverlay) {
        popupOverlay.addEventListener('click', function(e) {
            if (e.target === popupOverlay) {
                closeWelcomePopup();
            }
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeWelcomePopup();
    });
// Global AI Assistant Modal Logic
window.openShopAiModal = function() {
    const modal = document.getElementById('snGlobalAiModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const inp = document.getElementById('snGlobalAiInput');
            if (inp) inp.focus();
        }, 150);
    }
};

window.closeShopAiModal = function() {
    const modal = document.getElementById('snGlobalAiModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
};

window.sendAiQuickPrompt = function(promptText) {
    const inp = document.getElementById('snGlobalAiInput');
    if (inp) inp.value = promptText;
    sendShopAiMessage();
};

window.sendShopAiMessage = function() {
    const inp = document.getElementById('snGlobalAiInput');
    const msgBox = document.getElementById('snGlobalAiMessages');
    if (!inp || !msgBox) return;
    const text = inp.value.trim();
    if (!text) return;

    // Append user message
    const userDiv = document.createElement('div');
    userDiv.className = 'sn-ai-bubble user';
    userDiv.innerText = text;
    msgBox.appendChild(userDiv);
    inp.value = '';
    msgBox.scrollTop = msgBox.scrollHeight;

    // Loading indicator
    const aiDiv = document.createElement('div');
    aiDiv.className = 'sn-ai-bubble ai loading';
    aiDiv.innerHTML = '<span class="sn-ai-dots"><span>.</span><span>.</span><span>.</span></span> Finding best recommendations...';
    msgBox.appendChild(aiDiv);
    msgBox.scrollTop = msgBox.scrollHeight;

    const fd = new FormData();
    fd.append('prompt', text);
    fd.append('product_id', '0');

    fetch('<?php echo BASE_URL; ?>gemini_chat.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        aiDiv.classList.remove('loading');
        if (d.status === 'success' && d.response) {
            aiDiv.innerHTML = d.response;
        } else {
            aiDiv.innerHTML = "Sorry, I couldn't process your request right now. Please try again!";
        }
        msgBox.scrollTop = msgBox.scrollHeight;
    })
    .catch(err => {
        aiDiv.classList.remove('loading');
        aiDiv.innerHTML = "Error connecting to AI service. Please check connection.";
        msgBox.scrollTop = msgBox.scrollHeight;
    });
};
</script>

<?php if(($settings['show_scroll_top_btn']??0) == 1): ?>
    <button onclick="topFunction()" id="scrollTopBtn" title="Go to top"><i class="fas fa-arrow-up"></i></button>
<?php endif; ?>

<?php if(($settings['popup_on_off']??0) == 1): 
    $popup_photo_src = '';
    $raw_photo = $settings['popup_photo'] ?? '';
    if (!empty($raw_photo)) {
        if (str_starts_with($raw_photo, 'http://') || str_starts_with($raw_photo, 'https://')) {
            $popup_photo_src = $raw_photo;
        } elseif (file_exists(__DIR__ . '/assets/uploads/' . $raw_photo)) {
            $popup_photo_src = BASE_URL . 'assets/uploads/' . htmlspecialchars($raw_photo);
        } else {
            $popup_photo_src = BASE_URL . 'assets/uploads/' . htmlspecialchars($raw_photo);
        }
    }
    $anim_class = 'anim-' . htmlspecialchars($settings['popup_animation'] ?? 'spin-zoom');
    $popup_target_link = !empty($settings['popup_link']) ? $settings['popup_link'] : '#';
?>
<div id="promoPopup" class="custom-popup" role="dialog" aria-modal="true">
  <div class="custom-popup-content <?php echo $anim_class; ?>">
    <button type="button" class="close-popup" aria-label="Close dialog">&times;</button>
    
    <?php if(!empty($popup_photo_src)): ?>
        <a href="<?php echo htmlspecialchars($popup_target_link); ?>" class="sn-popup-banner-link">
            <img src="<?php echo htmlspecialchars($popup_photo_src); ?>" alt="<?php echo htmlspecialchars($settings['popup_title'] ?? 'Special Offer'); ?>" class="sn-popup-banner-img">
        </a>
    <?php endif; ?>

    <div class="sn-popup-body">
        <?php if(!empty($settings['popup_title'])): ?>
            <h3 class="sn-popup-title"><?php echo htmlspecialchars($settings['popup_title']); ?></h3>
        <?php endif; ?>

        <?php if(!empty($settings['popup_text'])): ?>
            <div class="sn-popup-desc">
                <?php echo nl2br(htmlspecialchars($settings['popup_text'])); ?>
            </div>
        <?php endif; ?>

        <?php if(($settings['popup_countdown_on_off']??1) == 1): ?>
        <div class="sn-popup-countdown" id="snPopupCountdown">
            <span class="sn-timer-label">⏰ Limited Time Offer Ends In:</span>
            <div class="sn-timer-boxes">
                <div class="sn-timer-box"><span id="snTimerDays">00</span><small>Days</small></div>
                <div class="sn-timer-sep">:</div>
                <div class="sn-timer-box"><span id="snTimerHours">00</span><small>Hours</small></div>
                <div class="sn-timer-sep">:</div>
                <div class="sn-timer-box"><span id="snTimerMins">00</span><small>Mins</small></div>
                <div class="sn-timer-sep">:</div>
                <div class="sn-timer-box"><span id="snTimerSecs">00</span><small>Secs</small></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!empty($settings['popup_link'])): ?>
            <a href="<?php echo htmlspecialchars($popup_target_link); ?>" class="sn-popup-cta-btn">
                <span><?php echo htmlspecialchars(!empty($settings['popup_btn_text']) ? $settings['popup_btn_text'] : 'Claim Offer Now'); ?></span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </a>
        <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

