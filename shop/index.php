<?php 
// -------------------------------------------------------------------------
// 0. FRONT CONTROLLER CLEAN-URL ROUTING (SEO / Friendly URLs)
// -------------------------------------------------------------------------
$rawUri = $_SERVER['REQUEST_URI'] ?? '';
$path = strtok($rawUri, '?');
if (preg_match('#/(?:[a-zA-Z0-9_-]+/)?product/([a-zA-Z0-9_-]+)/?$#', $path, $m)) {
    $_REQUEST['slug'] = $m[1];
    $_GET['slug'] = $m[1];
    require __DIR__ . '/product.php';
    exit;
}
if (preg_match('#/(?:[a-zA-Z0-9_-]+/)?category(?:/.*)?$#', $path)) {
    require __DIR__ . '/product-category.php';
    exit;
}
if (preg_match('#/(?:[a-zA-Z0-9_-]+/)?search(?:/.*)?$#', $path)) {
    require __DIR__ . '/search-result.php';
    exit;
}
if (preg_match('#/(?:[a-zA-Z0-9_-]+/)?page/([0-9]+)/?$#', $path, $m)) {
    $_REQUEST['page'] = (int)$m[1];
    $_GET['page'] = (int)$m[1];
}

require_once(__DIR__ . '/header.php'); 
if (file_exists(__DIR__ . '/inc_feed_card.php')) {
    require_once(__DIR__ . '/inc_feed_card.php');
}
if (!function_exists('renderAliProductCard')) {
    function renderAliProductCard($p, $currencySymbol = '৳ ') {
        $pId = (int)($p['p_id'] ?? 0);
        $pName = htmlspecialchars($p['p_name'] ?? '');
        $currPrice = (float)str_replace(',', '', (string)($p['p_current_price'] ?? 0));
        $prodPhoto = !empty($p['p_featured_photo']) ? $p['p_featured_photo'] : 'assets/images/no-image.png';
        if (!str_starts_with($prodPhoto, 'http')) {
            $prodPhoto = BASE_URL . 'assets/uploads/' . $prodPhoto;
        }
        $pUrl = function_exists('getProductURL') ? getProductURL($pId, $p['p_name'] ?? '', BASE_URL) : BASE_URL . 'product.php?id=' . $pId;
        return '<div class="sn-product-card sn-feed-card" data-id="' . $pId . '" data-href="' . htmlspecialchars($pUrl) . '">
            <a href="' . htmlspecialchars($pUrl) . '" class="sn-product-card-link" style="text-decoration:none; color:inherit;">
                <div class="sn-product-img-box"><img src="' . htmlspecialchars($prodPhoto) . '" alt="' . $pName . '" loading="lazy"></div>
                <h3 class="sn-product-title">' . $pName . '</h3>
                <div class="sn-price-current">' . $currencySymbol . number_format($currPrice, 2) . '</div>
            </a>
        </div>';
    }
}

// -------------------------------------------------------------------------
// 1. FETCH GLOBAL SETTINGS & SLIDERS FROM SUPABASE (MICROCACHED)
// -------------------------------------------------------------------------
$settingsCacheFile = __DIR__ . '/admin/inc/cache_settings.json';
$s = null;
if (file_exists($settingsCacheFile) && (time() - filemtime($settingsCacheFile) < 30)) {
    $s = json_decode(file_get_contents($settingsCacheFile), true);
}
if (!$s) {
    $s = $pdo->query("SELECT * FROM tbl_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
    @file_put_contents($settingsCacheFile, json_encode($s));
}

// Section Toggles
$slider_on          = isset($s['home_slider_on_off']) ? (int)$s['home_slider_on_off'] : 1;
$category_on        = isset($s['home_category_on_off']) ? (int)$s['home_category_on_off'] : 1;
$featured_on        = isset($s['home_featured_product_on_off']) ? (int)$s['home_featured_product_on_off'] : 1;
$promo_on           = isset($s['home_welcome_on_off']) ? (int)$s['home_welcome_on_off'] : 1;
$service_on         = isset($s['home_service_on_off']) ? (int)$s['home_service_on_off'] : 1;
$marquee_on         = isset($s['home_marquee_on_off']) ? (int)$s['home_marquee_on_off'] : 1;
$payday_on          = isset($s['payday_banner_on_off']) ? (int)$s['payday_banner_on_off'] : 1;

// Hero Defaults & Controls
$hero_tag           = !empty($s['hero_tag']) ? $s['hero_tag'] : 'BETTER PRODUCTS • BETTER LIFE';
$hero_title         = !empty($s['hero_title']) ? $s['hero_title'] : 'Upgrade Your Everyday Life';
$hero_subtitle      = !empty($s['hero_subtitle']) ? $s['hero_subtitle'] : 'Discover top-quality products, unbeatable prices, and a seamless shopping experience.';
$hero_btn_text      = !empty($s['hero_btn_text']) ? $s['hero_btn_text'] : 'Shop Now';
$hero_btn_url       = !empty($s['hero_btn_url']) ? $s['hero_btn_url'] : 'product-category.php?id=1&type=top-category';
$hero_slider_autoplay = isset($s['hero_slider_autoplay']) ? (int)$s['hero_slider_autoplay'] : 1;
$hero_slider_interval = !empty($s['hero_slider_interval']) ? (int)$s['hero_slider_interval'] : 4500;

// Query Hero Slides from Supabase
try {
    $heroSlides = $pdo->query("SELECT * FROM tbl_slider WHERE is_active = 1 ORDER BY slide_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $_) {
    try {
        $heroSlides = $pdo->query("SELECT * FROM tbl_slider ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $__) {
        $heroSlides = [];
    }
}
if (empty($heroSlides)) {
    $fallbackImg = !empty($s['hero_image']) ? $s['hero_image'] : 'https://oaudxkhxwdrdsybyaheb.supabase.co/storage/v1/object/public/storefront/assets/hero_products_collage.jpg';
    $heroSlides = [['id' => 1, 'photo' => $fallbackImg]];
}

// Categories Section Defaults & Data
$categories_title = !empty($s['categories_title']) ? $s['categories_title'] : 'Shop by Category';
$categories = [];
try {
    $catStmt = $pdo->query("SELECT tcat_id, tcat_name, photo FROM tbl_top_category WHERE show_on_menu = 1 AND LOWER(tcat_name) != 'shop' ORDER BY tcat_order ASC, tcat_id ASC LIMIT 10");
    $categories = $catStmt ? ($catStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    if (empty($categories)) {
        $catStmt2 = $pdo->query("SELECT tcat_id, tcat_name, photo FROM tbl_top_category WHERE LOWER(tcat_name) != 'shop' ORDER BY tcat_id ASC LIMIT 10");
        $categories = $catStmt2 ? ($catStmt2->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
} catch (Throwable $_) {}

// Dual Promo Banners Defaults
$promo1_tag   = !empty($s['promo_banner1_tag']) ? $s['promo_banner1_tag'] : 'Up to 50% Off';
$promo1_title = !empty($s['promo_banner1_title']) ? $s['promo_banner1_title'] : 'Top Electronics';
$promo1_sub   = !empty($s['promo_banner1_subtitle']) ? $s['promo_banner1_subtitle'] : 'Laptops, Phones, Accessories & More';
$promo1_btn   = !empty($s['promo_banner1_btn_text']) ? $s['promo_banner1_btn_text'] : 'Shop Now';
$promo1_url   = !empty($s['promo_banner1_btn_url']) ? $s['promo_banner1_btn_url'] : 'product-category.php?id=4&type=top-category';
$promo1_img   = !empty($s['promo_banner1_image']) ? $s['promo_banner1_image'] : 'https://oaudxkhxwdrdsybyaheb.supabase.co/storage/v1/object/public/storefront/assets/promo_electronics.jpg';

$promo2_tag   = !empty($s['promo_banner2_tag']) ? $s['promo_banner2_tag'] : 'Trending Deals';
$promo2_title = !empty($s['promo_banner2_title']) ? $s['promo_banner2_title'] : 'Fresh Styles For You';
$promo2_sub   = !empty($s['promo_banner2_subtitle']) ? $s['promo_banner2_subtitle'] : 'Fashion, Footwear & Accessories';
$promo2_btn   = !empty($s['promo_banner2_btn_text']) ? $s['promo_banner2_btn_text'] : 'Shop Now';
$promo2_url   = !empty($s['promo_banner2_btn_url']) ? $s['promo_banner2_btn_url'] : 'product-category.php?id=1&type=top-category';
$promo2_img   = !empty($s['promo_banner2_image']) ? $s['promo_banner2_image'] : 'https://oaudxkhxwdrdsybyaheb.supabase.co/storage/v1/object/public/storefront/assets/promo_fashion.jpg';

// -------------------------------------------------------------------------
// 2. PERSONALIZED HOME FEED (ALIEXPRESS STYLE) INITIAL BATCH
// -------------------------------------------------------------------------
$featured_products_title = !empty($s['featured_products_title']) ? $s['featured_products_title'] : 'More to Love';
$featured_limit = (!empty($s['total_featured_product_home']) && (int)$s['total_featured_product_home'] > 0) ? (int)$s['total_featured_product_home'] : 10;
$featuredProducts = [];

// Gather server-side personalization signals
$ssrSignalIds = [];
if (!empty($_SESSION['recently_viewed']) && is_array($_SESSION['recently_viewed'])) {
    $ssrSignalIds = array_merge($ssrSignalIds, array_map('intval', $_SESSION['recently_viewed']));
}
if (!empty($_COOKIE['sn_recently_viewed'])) {
    $cRec = json_decode($_COOKIE['sn_recently_viewed'], true);
    if (is_array($cRec)) {
        $ssrSignalIds = array_merge($ssrSignalIds, array_map('intval', $cRec));
    }
}
if (!empty($_SESSION['cart_p_id']) && is_array($_SESSION['cart_p_id'])) {
    $ssrSignalIds = array_merge($ssrSignalIds, array_map('intval', $_SESSION['cart_p_id']));
}
if (!empty($_SESSION['customer']['cust_id'])) {
    $cId = (int)$_SESSION['customer']['cust_id'];
    try {
        $wStmt = $pdo->prepare("SELECT product_id FROM tbl_wishlist WHERE cust_id = ? ORDER BY wishlist_id DESC LIMIT 10");
        $wStmt->execute([$cId]);
        $wIds = $wStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $ssrSignalIds = array_merge($ssrSignalIds, array_map('intval', $wIds));
    } catch (Throwable $_) {}
}
$ssrSignalIds = array_values(array_unique(array_filter($ssrSignalIds)));

$preferredEcats = [];
$preferredMcats = [];
$preferredTcats = [];

if (!empty($ssrSignalIds)) {
    $inSignals = implode(',', array_slice($ssrSignalIds, 0, 20));
    try {
        $catStmt = $pdo->query("
            SELECT p.p_id, p.ecat_id, e.mcat_id, m.tcat_id
            FROM tbl_product p
            LEFT JOIN tbl_end_category e ON p.ecat_id = e.ecat_id
            LEFT JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id
            WHERE p.p_id IN ($inSignals)
        ");
        while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['ecat_id'])) $preferredEcats[$row['ecat_id']] = ($preferredEcats[$row['ecat_id']] ?? 0) + 1;
            if (!empty($row['mcat_id'])) $preferredMcats[$row['mcat_id']] = ($preferredMcats[$row['mcat_id']] ?? 0) + 1;
            if (!empty($row['tcat_id'])) $preferredTcats[$row['tcat_id']] = ($preferredTcats[$row['tcat_id']] ?? 0) + 1;
        }
    } catch (Throwable $_) {}
}

$numCurr = "CAST(NULLIF(REPLACE(COALESCE(p.p_current_price::text, '0'), ',', ''), '') AS numeric)";
$numOld  = "CAST(NULLIF(REPLACE(COALESCE(p.p_old_price::text, '0'), ',', ''), '') AS numeric)";

$scoreParts = [];
if (!empty($preferredEcats)) {
    $scoreParts[] = "(CASE WHEN p.ecat_id IN (" . implode(',', array_keys($preferredEcats)) . ") THEN 60 ELSE 0 END)";
}
if (!empty($preferredMcats)) {
    $scoreParts[] = "(CASE WHEN e.mcat_id IN (" . implode(',', array_keys($preferredMcats)) . ") THEN 30 ELSE 0 END)";
}
if (!empty($preferredTcats)) {
    $scoreParts[] = "(CASE WHEN m.tcat_id IN (" . implode(',', array_keys($preferredTcats)) . ") THEN 15 ELSE 0 END)";
}
$scoreParts[] = "(CASE WHEN {$numOld} > {$numCurr} AND {$numOld} > 0 THEN (({$numOld} - {$numCurr}) / {$numOld}) * 25 ELSE 0 END)";
$scoreParts[] = "(CASE WHEN p.p_is_featured = 1 THEN 20 ELSE 0 END)";
$scoreParts[] = "(CASE WHEN p.is_top_sale = 1 THEN 15 ELSE 0 END)";
$scoreParts[] = "(CASE WHEN p.is_official = 1 OR p.is_premium = 1 THEN 10 ELSE 0 END)";
$scoreParts[] = "LEAST(p.p_total_view * 0.02, 30)";

$scoreSql = implode(' + ', $scoreParts);
$initialOrder = "({$scoreSql}) DESC, p.p_id DESC";

try {
    $prodStmt = $pdo->query("
        SELECT p.*, e.mcat_id, m.tcat_id,
               COALESCE((SELECT AVG(rating) FROM tbl_rating WHERE p_id = p.p_id), 0) as avg_rating,
               COALESCE((SELECT COUNT(*) FROM tbl_rating WHERE p_id = p.p_id), 0) as rev_count
        FROM tbl_product p
        LEFT JOIN tbl_end_category e ON p.ecat_id = e.ecat_id
        LEFT JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id
        WHERE p.p_is_active = 1
        ORDER BY {$initialOrder}
        LIMIT {$featured_limit}
    ");
    $featuredProducts = $prodStmt ? ($prodStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $_) {
    try {
        $prodStmt = $pdo->query("SELECT p.*, COALESCE((SELECT AVG(rating) FROM tbl_rating WHERE p_id = p.p_id), 0) as avg_rating, COALESCE((SELECT COUNT(*) FROM tbl_rating WHERE p_id = p.p_id), 0) as rev_count FROM tbl_product p WHERE p.p_is_active = 1 ORDER BY p.p_total_view DESC, p.p_id DESC LIMIT {$featured_limit}");
        $featuredProducts = $prodStmt ? ($prodStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $__) {
        $featuredProducts = [];
    }
}

// Trust Bar Defaults
$trust1_title = !empty($s['trust_item1_title']) ? $s['trust_item1_title'] : 'Free Shipping';
$trust1_desc  = !empty($s['trust_item1_desc']) ? $s['trust_item1_desc'] : 'On orders over ৳ 2,000';
$trust2_title = !empty($s['trust_item2_title']) ? $s['trust_item2_title'] : 'Secure Payment';
$trust2_desc  = !empty($s['trust_item2_desc']) ? $s['trust_item2_desc'] : '100% secure payment';
$trust3_title = !empty($s['trust_item3_title']) ? $s['trust_item3_title'] : 'Easy Returns';
$trust3_desc  = !empty($s['trust_item3_desc']) ? $s['trust_item3_desc'] : '30-day return policy';
$trust4_title = !empty($s['trust_item4_title']) ? $s['trust_item4_title'] : '24/7 Support';
$trust4_desc  = !empty($s['trust_item4_desc']) ? $s['trust_item4_desc'] : "We're here to help";
$currencySymbol = '৳ ';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ==========================================================================
   ShopNext - Flagship Pixel-Perfect Storefront Homepage Styles
   ========================================================================== */
:root {
    --sn-bg-page: #f8fafc;
    --sn-primary: #fab802;
    --sn-primary-hover: #e5a700;
    --sn-dark: #0f172a;
    --sn-muted: #64748b;
    --sn-border: #f1f5f9;
}

*, *::before, *::after {
    box-sizing: border-box;
    outline: none !important;
}

*:focus,
*:focus-visible,
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

html, body {
    overflow-x: hidden;
    max-width: 100vw;
}

body {
    background-color: var(--sn-bg-page);
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--sn-dark);
    margin: 0;
    padding: 0;
}

@media (min-width: 769px) {
    .content-wrapper-main {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
}

.content-wrapper-main,
.sn-page-container {
    overflow-x: clip;
    max-width: 100vw;
}

.sn-main-content {
    margin-top: 0 !important;
    padding: 16px 0 48px 0 !important;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: clip;
}

.sn-container {
    max-width: 1240px;
    width: 100%;
    margin: 0 auto;
    padding: 0 16px;
    box-sizing: border-box;
}

/* SECTION HEADERS */
.sn-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.sn-section-title {
    font-size: 20px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0;
    letter-spacing: -0.4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sn-flash-icon {
    font-size: 18px;
    line-height: 1;
}

.sn-view-all {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    font-weight: 700;
    color: #64748b !important;
    text-decoration: none !important;
    transition: color 0.2s ease;
}

.sn-view-all:hover {
    color: #0f172a !important;
}

/* 1. HERO SECTION */
.sn-hero-section {
    margin-top: 0 !important;
    padding-top: 0 !important;
    margin-bottom: 22px;
}

.sn-hero-card {
    background: linear-gradient(105deg, #fdfbf7 0%, #fffefb 40%, #fef8e7 75%, #fef3c7 100%);
    border: 1px solid rgba(245, 230, 195, 0.7);
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28px 44px;
    position: relative;
    overflow: hidden;
    min-height: 330px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.sn-hero-counter-badge {
    position: absolute;
    top: 12px;
    right: 14px;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 999px;
    z-index: 10;
    display: none;
}

.sn-hero-left {
    max-width: 440px;
    z-index: 10;
}

.sn-hero-top-badge-row {
    margin-bottom: 6px;
}

.sn-hero-mega-badge {
    display: inline-block;
    background: #fef08a;
    color: #854d0e;
    font-size: 11px;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: 999px;
    letter-spacing: 0.3px;
}

.sn-hero-eyebrow {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #64748b;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.sn-hero-heading {
    font-size: 38px;
    font-weight: 800;
    line-height: 1.15;
    color: var(--sn-dark);
    margin: 0 0 10px 0;
    letter-spacing: -0.8px;
}

.sn-hero-subtitle {
    font-size: 14px;
    line-height: 1.48;
    color: #475569;
    margin: 0 0 18px 0;
    max-width: 400px;
}

.sn-hero-actions {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.sn-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--sn-primary);
    color: #0f172a !important;
    padding: 10px 24px;
    border-radius: 50px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none !important;
    transition: all 0.2s ease;
    box-shadow: 0 3px 12px rgba(250, 184, 2, 0.3);
}

.sn-btn-primary:hover {
    background: var(--sn-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(250, 184, 2, 0.4);
}

.sn-hero-dots {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sn-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #cbd5e1;
    cursor: pointer;
    transition: all 0.25s ease;
}

.sn-dot.active {
    width: 22px;
    height: 6px;
    border-radius: 3px;
    background: var(--sn-primary);
}

.sn-hero-right {
    flex: 1;
    max-width: 520px;
    height: 290px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sn-hero-slider-wrap {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sn-hero-slider-track {
    position: relative;
    width: 100%;
    height: 100%;
}

.sn-hero-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 0.5s ease;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sn-hero-slide.active {
    opacity: 1;
    pointer-events: auto;
    z-index: 2;
}

.sn-hero-slide .sn-hero-image {
    max-height: 270px;
    max-width: 100%;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 14px 24px rgba(0, 0, 0, 0.08));
}

.sn-hero-doodle-badge {
    position: absolute;
    top: 8px;
    right: 14px;
    text-align: center;
    font-family: 'Caveat', cursive, sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.05;
    transform: rotate(5deg);
    pointer-events: none;
    z-index: 5;
}

.sn-static-doodle-ray {
    position: absolute;
    top: 40%;
    left: 3%;
    pointer-events: none;
    z-index: 4;
    color: #fab802;
    opacity: 0.95;
}

.sn-slider-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 15;
    opacity: 0;
    transition: all 0.2s ease;
    color: var(--sn-dark);
}

.sn-hero-card:hover .sn-slider-arrow {
    opacity: 1;
}

.sn-slider-arrow:hover {
    background: #ffffff;
    color: var(--sn-primary);
    border-color: rgba(0, 0, 0, 0.08);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
    outline: none !important;
}

.sn-slider-prev { left: 12px; }
.sn-slider-next { right: 12px; }

/* 2. HORIZONTAL CATEGORIES BAR */
.sn-category-section {
    margin-bottom: 24px;
}

.sn-category-scroll-wrap {
    display: grid;
    grid-template-columns: repeat(11, 1fr);
    gap: 12px;
}

.sn-category-scroll-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none !important;
    color: var(--sn-dark) !important;
    transition: transform 0.2s ease;
}

.sn-category-scroll-item:hover {
    transform: translateY(-3px);
}

.sn-category-scroll-box {
    width: 100%;
    aspect-ratio: 1;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
    overflow: hidden;
}

.sn-category-scroll-item:hover .sn-category-scroll-box {
    border-color: #fab802;
    box-shadow: 0 6px 18px rgba(250, 184, 2, 0.18);
    background: #fffefb;
}

.sn-category-scroll-box img {
    max-width: 80%;
    max-height: 80%;
    object-fit: contain;
    transition: transform 0.2s ease;
}

.sn-category-scroll-item:hover .sn-category-scroll-box img {
    transform: scale(1.08);
}

.sn-category-scroll-name {
    font-size: 11.5px;
    font-weight: 700;
    color: #1e293b;
    text-align: center;
    margin-top: 6px;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 95px;
}

/* 3. VALUE PROPOSITION / TRUST BAR */
.sn-trust-section {
    margin-bottom: 24px;
}

.sn-trust-bar {
    background: #ffffff;
    border-radius: 16px;
    padding: 16px 28px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
}

.sn-trust-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sn-trust-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #0f172a;
}

.sn-trust-info h4 {
    font-size: 13px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 2px 0;
}

.sn-trust-info p {
    font-size: 11.5px;
    color: #64748b;
    margin: 0;
}

/* ==========================================================================
   LIVE DEAL MARQUEE RIBBON (DARAZ / ALIEXPRESS TICKER - LIGHT GRADIENT)
   ========================================================================== */
.sn-live-marquee-wrap {
    background: linear-gradient(135deg, #ffffff 0%, #fff7ed 50%, #fef3c7 100%);
    border-radius: 14px;
    padding: 10px 18px;
    margin-bottom: 24px;
    overflow: hidden;
    position: relative;
    border: 1.5px solid #fed7aa;
    box-shadow: 0 4px 18px rgba(245, 158, 11, 0.08), 0 1px 3px rgba(0, 0, 0, 0.03);
    display: flex;
    align-items: center;
}

.sn-live-marquee-wrap::before,
.sn-live-marquee-wrap::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 34px;
    z-index: 2;
    pointer-events: none;
}
.sn-live-marquee-wrap::before {
    left: 0;
    background: linear-gradient(90deg, #ffffff 30%, transparent);
}
.sn-live-marquee-wrap::after {
    right: 0;
    background: linear-gradient(270deg, #fef3c7 30%, transparent);
}

.sn-live-marquee-track {
    display: inline-flex;
    align-items: center;
    gap: 36px;
    white-space: nowrap;
    animation: snMarqueeScroll 28s linear infinite;
    will-change: transform;
}

.sn-live-marquee-wrap:hover .sn-live-marquee-track {
    animation-play-state: paused;
}

@keyframes snMarqueeScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.sn-marquee-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #1e293b;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none !important;
}

.sn-marquee-tag {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    padding: 2.5px 8px;
    border-radius: 6px;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    box-shadow: 0 1px 4px rgba(245, 158, 11, 0.25);
}

.sn-marquee-tag.red {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    box-shadow: 0 1px 4px rgba(239, 68, 68, 0.25);
}

.sn-marquee-tag.purple {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: #ffffff;
    box-shadow: 0 1px 4px rgba(139, 92, 246, 0.25);
}

.sn-marquee-tag.green {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    box-shadow: 0 1px 4px rgba(16, 185, 129, 0.25);
}

.sn-marquee-sep {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #f59e0b;
    opacity: 0.8;
}

/* ==========================================================================
   4. PAYDAY SALE PROMO BANNER (MATCHING SCREENSHOT + HIGH MOTION)
   ========================================================================== */
.sn-payday-section {
    margin-bottom: 26px;
}

.sn-payday-banner {
    background: linear-gradient(135deg, #ffffff 0%, #fffbeb 40%, #fef3c7 100%);
    border: 1.5px solid #fde68a;
    border-radius: 20px;
    padding: 24px 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 28px rgba(245, 158, 11, 0.08), 0 2px 8px rgba(0, 0, 0, 0.03);
}

/* Diagonal Shimmer Sweep Light Animation */
.sn-payday-banner::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(60deg, transparent 40%, rgba(255, 255, 255, 0.6) 50%, transparent 60%);
    transform: translateX(-100%) rotate(25deg);
    animation: snPaydayShimmer 6s infinite ease-in-out;
    pointer-events: none;
}

@keyframes snPaydayShimmer {
    0% { transform: translateX(-100%) rotate(25deg); }
    35%, 100% { transform: translateX(100%) rotate(25deg); }
}

/* Left Angled Badge with Warm Amber/Gold Outline */
.sn-payday-left {
    background: #ffffff;
    border: 2px solid #f59e0b;
    border-radius: 14px;
    padding: 12px 20px;
    text-align: center;
    transform: rotate(-3deg);
    box-shadow: 0 4px 18px rgba(245, 158, 11, 0.2), inset 0 0 12px rgba(254, 243, 199, 0.5);
    flex-shrink: 0;
    animation: snBadgeNeon 3s infinite ease-in-out alternate;
    position: relative;
    z-index: 1;
}

@keyframes snBadgeNeon {
    0% {
        border-color: #f59e0b;
        box-shadow: 0 4px 16px rgba(245, 158, 11, 0.2), inset 0 0 8px rgba(254, 243, 199, 0.4);
    }
    100% {
        border-color: #d97706;
        box-shadow: 0 6px 22px rgba(245, 158, 11, 0.35), inset 0 0 16px rgba(254, 243, 199, 0.7);
    }
}

.sn-payday-badge-title {
    font-size: 18px;
    font-weight: 900;
    color: #0f172a;
    line-height: 1.05;
    letter-spacing: 0.5px;
}

/* Solid Amber Bar under SALE */
.sn-payday-badge-bar {
    width: 100%;
    height: 3px;
    background: #f59e0b;
    border-radius: 2px;
    margin: 4px auto 3px auto;
    box-shadow: 0 0 6px rgba(245, 158, 11, 0.4);
}

.sn-payday-badge-sub {
    font-size: 11px;
    font-weight: 800;
    color: #d97706;
    letter-spacing: 0.8px;
}

.sn-payday-center {
    flex: 1;
    padding: 0 32px;
    color: #0f172a;
    position: relative;
    z-index: 1;
}

.sn-payday-center-title {
    font-size: 26px;
    font-weight: 900;
    line-height: 1.15;
    margin-bottom: 4px;
    letter-spacing: -0.5px;
    color: #0f172a;
}

.sn-payday-center-sub {
    font-size: 14px;
    color: #64748b;
    font-weight: 500;
    margin-bottom: 14px;
}

/* Claim Now Button with Radiating Glow Pulse */
.sn-payday-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #ffffff !important;
    font-size: 13px;
    font-weight: 700;
    padding: 10px 24px;
    border-radius: 50px;
    text-decoration: none !important;
    box-shadow: 0 4px 16px rgba(245, 158, 11, 0.35);
    transition: all 0.25s ease;
    animation: snGlowPulse 2.4s infinite ease-in-out;
}

@keyframes snGlowPulse {
    0%, 100% {
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35), 0 0 12px rgba(245, 158, 11, 0.15);
    }
    50% {
        box-shadow: 0 4px 22px rgba(245, 158, 11, 0.6), 0 0 24px rgba(245, 158, 11, 0.3);
    }
}

.sn-payday-btn:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 6px 20px rgba(217, 119, 6, 0.45);
}

.sn-payday-btn svg {
    transition: transform 0.25s ease;
}

.sn-payday-btn:hover svg {
    transform: translateX(4px);
}

/* Right 3D Cart Floating Animation */
.sn-payday-right {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    max-height: 120px;
    position: relative;
    z-index: 1;
}

.sn-payday-right img {
    max-height: 115px;
    max-width: 180px;
    object-fit: contain;
    filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.1));
    animation: snCartFloat 3.8s ease-in-out infinite alternate;
    will-change: transform;
}

@keyframes snCartFloat {
    0% {
        transform: translateY(0px) rotate(0deg);
    }
    50% {
        transform: translateY(-6px) rotate(-1.5deg);
    }
    100% {
        transform: translateY(2px) rotate(1deg);
    }
}

/* ==========================================================================
   MULTI-CARD SLIDING CAROUSEL SYSTEM & SECTION HEADERS
   ========================================================================== */
.sn-header-right-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sn-carousel-nav {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.sn-carousel-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    padding: 0;
}

.sn-carousel-btn:hover:not(:disabled) {
    background: #fab802;
    border-color: #fab802;
    color: #0f172a;
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(250, 184, 2, 0.35);
}

.sn-carousel-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    pointer-events: none;
}

.sn-carousel-container {
    position: relative;
    width: 100%;
}

/* ==========================================================================
   5. FLASH SALE SECTION (MULTI-CARD CAROUSEL + LIVE COUNTDOWN + STOCK METER)
   ========================================================================== */
.sn-flash-section {
    margin-bottom: 28px;
}

.sn-flash-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.sn-flash-countdown {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 4px 12px;
    border-radius: 999px;
    box-shadow: 0 1px 4px rgba(239, 68, 68, 0.08);
}

.sn-countdown-label {
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.6px;
    color: #ef4444;
    font-weight: 800;
}

.sn-countdown-boxes {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.sn-timer-box {
    background: #ef4444;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    min-width: 22px;
    height: 20px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    font-variant-numeric: tabular-nums;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.25);
}

.sn-timer-colon {
    font-weight: 900;
    color: #ef4444;
    font-size: 11px;
}

/* Multi-Card Smooth Sliding Track */
.sn-flash-scroll {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    padding: 4px 2px 14px 2px;
}

.sn-flash-scroll::-webkit-scrollbar {
    display: none;
}

.sn-flash-card {
    flex: 0 0 calc((100% - 48px) / 4);
    min-width: 220px;
    scroll-snap-align: start;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    position: relative;
    text-decoration: none !important;
    color: var(--sn-dark) !important;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.sn-flash-card:hover {
    transform: translateY(-4px);
    border-color: rgba(250, 184, 2, 0.5);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
}

.sn-flash-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 8px;
}

.sn-flash-discount {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    padding: 2.5px 8px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.25);
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.sn-flash-wishlist {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s ease;
}

.sn-flash-wishlist:hover,
.sn-flash-wishlist.active {
    background: #fef2f2;
    color: #ef4444;
    border-color: #fecaca;
    transform: scale(1.1);
}

.sn-flash-wishlist.active svg {
    fill: #ef4444;
}

/* Heart Pop Micro-Interaction */
@keyframes snHeartPop {
    0% { transform: scale(1); }
    40% { transform: scale(1.4); }
    70% { transform: scale(0.88); }
    100% { transform: scale(1); }
}

.sn-heart-pop {
    animation: snHeartPop 0.38s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
}

.sn-flash-img-box {
    width: 100%;
    height: 155px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    background: #f8fafc;
    border-radius: 12px;
    overflow: hidden;
}

.sn-flash-img-box img {
    max-height: 135px;
    max-width: 85%;
    object-fit: contain;
    transition: transform 0.35s ease;
}

.sn-flash-card:hover .sn-flash-img-box img {
    transform: scale(1.07);
}

.sn-flash-title {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    line-height: 1.25;
    margin: 0 0 8px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 34px;
}

.sn-flash-pricing-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-top: auto;
}

.sn-flash-curr-price {
    font-size: 16.5px;
    font-weight: 800;
    color: var(--sn-dark);
}

.sn-flash-old-price {
    font-size: 12px;
    color: #94a3b8;
    text-decoration: line-through;
}

/* Stock Claim Meter Bar */
.sn-flash-stock-wrap {
    margin-top: 10px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sn-flash-stock-bar {
    width: 100%;
    height: 6px;
    background: #fee2e2;
    border-radius: 999px;
    overflow: hidden;
}

.sn-flash-stock-fill {
    height: 100%;
    background: linear-gradient(90deg, #f97316 0%, #ef4444 100%);
    border-radius: 999px;
    transition: width 0.6s ease;
}

.sn-flash-stock-text {
    font-size: 10.5px;
    font-weight: 700;
    color: #ef4444;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ==========================================================================
   6. DAILY SHIRA DEALS (MULTI-CARD CAROUSEL)
   ========================================================================== */
.sn-shira-section {
    margin-bottom: 28px;
}

.sn-shira-grid {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    padding: 4px 2px 14px 2px;
}

.sn-shira-grid::-webkit-scrollbar {
    display: none;
}

.sn-shira-card {
    flex: 0 0 calc((100% - 48px) / 4);
    min-width: 200px;
    scroll-snap-align: start;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none !important;
    color: var(--sn-dark) !important;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

.sn-shira-card:hover {
    transform: translateY(-4px);
    border-color: rgba(250, 184, 2, 0.5);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    outline: none !important;
}

.sn-shira-img-box {
    width: 100%;
    height: 135px;
    background: #f8fafc;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    overflow: hidden;
}

.sn-shira-img-box img {
    max-height: 120px;
    max-width: 85%;
    object-fit: contain;
    transition: transform 0.35s ease;
}

.sn-shira-card:hover .sn-shira-img-box img {
    transform: scale(1.07);
}

.sn-shira-badge {
    background: #fef08a;
    color: #854d0e;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 10px;
    border-radius: 999px;
    margin-bottom: 6px;
}

.sn-shira-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--sn-dark);
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

/* 7. ALIEXPRESS STYLE PERSONALIZED FEED & FEATURED SECTION */
.sn-featured-section,
.sn-feed-section {
    margin-bottom: 34px;
}

.sn-feed-header-wrap {
    margin-bottom: 14px;
}

.sn-feed-title-icon {
    font-size: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.sn-feed-header-badge {
    background: #fef3c7;
    color: #92400e;
    font-size: 11px;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 999px;
    border: 1px solid #fde68a;
    letter-spacing: -0.2px;
}

.sn-feed-hint-text {
    font-size: 12.5px;
    color: #64748b;
    font-weight: 500;
}

.sn-feed-tabs-wrap {
    position: -webkit-sticky;
    position: sticky;
    top: var(--sn-feed-sticky-top, 0px);
    z-index: 95;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding: 10px 4px;
    margin: 0 0 16px 0;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.sn-feed-tabs-wrap::-webkit-scrollbar {
    display: none;
}

.sn-feed-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    border-radius: 999px;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.sn-feed-tab svg {
    flex-shrink: 0;
}

.sn-feed-tab:hover {
    border-color: #cbd5e1;
    color: #0f172a;
    background: #f8fafc;
    transform: translateY(-1px);
}

.sn-feed-tab.active {
    background: linear-gradient(135deg, #ffffff 0%, #fffbeb 40%, #fef3c7 100%);
    color: #92400e;
    border-color: #f59e0b;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.2);
    font-weight: 800;
}

.sn-products-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 14px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.sn-product-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.25s ease;
    min-width: 0;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.sn-product-card-link {
    min-width: 0;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.sn-product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    border-color: #f1f5f9;
    outline: none !important;
}

.sn-badge {
    font-size: 10px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 5px;
    background: var(--sn-primary);
    color: #0f172a;
    z-index: 5;
    display: inline-block;
}

.sn-feed-top-badges {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 5;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sn-badge-discount {
    background: linear-gradient(135deg, #ef4444 0%, #f97316 100%) !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    padding: 2px 7px !important;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
}

.sn-badge-choice {
    background: #0f172a !important;
    color: #fab802 !important;
    font-weight: 800 !important;
    padding: 2px 7px !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.25);
}

.sn-badge-topsale {
    background: #f59e0b !important;
    color: #0f172a !important;
}

.sn-card-wishlist {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 6;
    color: #64748b;
    transition: all 0.2s ease;
}

.sn-card-wishlist:hover,
.sn-card-wishlist.active {
    color: #ef4444;
    background: #ffffff;
    transform: scale(1.12);
}

.sn-feed-pill-row {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 2px 0 6px 0;
    flex-wrap: wrap;
}

.sn-pill-choice {
    background: #0f172a;
    color: #fab802;
    font-size: 9.5px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.sn-pill-free {
    background: #dcfce7;
    color: #15803d;
    font-size: 9.5px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
}

.sn-pill-top {
    background: #fef3c7;
    color: #b45309;
    font-size: 9.5px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
}

.sn-feed-dot-sep {
    color: #cbd5e1;
    font-weight: 700;
    margin: 0 1px;
}

.sn-feed-sales {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}

.sn-feed-skeleton {
    margin-top: 14px;
}

.sn-skeleton-card {
    pointer-events: none;
    background: #ffffff;
}

.sn-skeleton-box {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: snSkeletonShimmer 1.5s infinite;
    border-radius: 8px;
}

@keyframes snSkeletonShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.sn-btn-feed-more {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    color: #0f172a;
    font-weight: 700;
    font-size: 13px;
    padding: 10px 24px;
    border-radius: 999px;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}

.sn-btn-feed-more:hover {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15,23,42,0.12);
}

.sn-feed-end {
    text-align: center;
    padding: 30px 16px 10px 16px;
}

.sn-feed-end-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    padding: 9px 22px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.sn-feed-btn-top {
    display: block;
    margin: 12px auto 0 auto;
    background: transparent;
    border: none;
    color: #d97706;
    font-weight: 800;
    font-size: 12px;
    cursor: pointer;
    text-decoration: underline;
    transition: color 0.15s ease;
}

.sn-feed-btn-top:hover {
    color: #b45309;
}

.sn-product-img-box {
    width: 100%;
    height: 145px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    background: #f8fafc;
    border-radius: 12px;
    overflow: hidden;
}

.sn-product-img-box img {
    max-height: 130px;
    max-width: 85%;
    object-fit: contain;
    transition: transform 0.25s ease;
}

.sn-product-card:hover .sn-product-img-box img {
    transform: scale(1.05);
}

.sn-product-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--sn-dark);
    margin: 0 0 3px 0;
    line-height: 1.25;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 32px;
}

.sn-product-spec {
    font-size: 11px;
    color: #64748b;
    margin: 0 0 6px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sn-product-rating {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11.5px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 10px;
}

.sn-rating-star {
    color: #fab802;
    font-size: 13px;
}

.sn-rating-count {
    color: #94a3b8;
    font-size: 10.5px;
    font-weight: 500;
}

.sn-product-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 8px;
    border-top: 1px solid #f8fafc;
}

.sn-price-box {
    display: flex;
    flex-direction: column;
}

.sn-current-price {
    font-size: 14.5px;
    font-weight: 800;
    color: var(--sn-dark);
}

.sn-old-price {
    font-size: 11px;
    color: #94a3b8;
    text-decoration: line-through;
}

.sn-btn-cart {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #fab802;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #0f172a;
    box-shadow: 0 2px 6px rgba(250, 184, 2, 0.3);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.sn-btn-cart:hover {
    background: #e5a700;
    transform: scale(1.08);
}

/* SPINNER & TOAST NOTIFICATION */
@keyframes snSpin {
    to { transform: rotate(360deg); }
}

.sn-spin {
    animation: snSpin 0.75s linear infinite;
}

.sn-home-toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    background: #0f172a;
    color: #ffffff;
    padding: 10px 22px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 700;
    z-index: 99999;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    display: flex;
    align-items: center;
    gap: 8px;
    pointer-events: none;
    opacity: 0;
    transition: all 0.25s ease;
}

.sn-home-toast.visible {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* RESPONSIVE BREAKPOINTS (Tablet & Mobile) */
@media (max-width: 1024px) {
    .sn-category-scroll-wrap {
        grid-template-columns: repeat(6, 1fr);
    }
    .sn-products-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        width: 100%;
        box-sizing: border-box;
    }
    .sn-hero-heading {
        font-size: 32px;
    }
    .sn-hero-card {
        padding: 24px 30px;
    }
}

@media (max-width: 768px) {
    .sn-desktop-only {
        display: none !important;
    }
    .sn-hero-counter-badge {
        display: block !important;
    }
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
        padding: 0 !important;
    }
    .sn-header-wrap .sn-container {
        padding: 0 14px !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    body.shopnext-theme .content-wrapper-main {
        padding-top: 96px !important;
        padding-bottom: 72px !important;
    }
    .sn-header-top {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 8px 0 6px 0 !important;
        gap: 8px 0 !important;
        width: 100% !important;
    }
    .sn-brand-logo {
        order: 1 !important;
        font-size: 20px !important;
    }
    .sn-header-actions {
        order: 2 !important;
        margin-left: auto !important;
        display: flex !important;
        align-items: center !important;
        gap: 14px !important;
    }
    .sn-search-form {
        order: 3 !important;
        flex: 0 0 100% !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 2px 0 0 0 !important;
        max-height: none !important;
        opacity: 1 !important;
        transform: none !important;
        pointer-events: auto !important;
        visibility: visible !important;
    }
    .sn-search-input-wrap {
        height: 38px !important;
        display: flex !important;
        align-items: center !important;
        width: 100% !important;
        border-radius: 999px !important;
        border: 1.5px solid #f1f5f9 !important;
        padding: 0 4px 0 12px !important;
        background: #ffffff !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03) !important;
    }
    .sn-search-input {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        width: auto !important;
        border: none !important;
        outline: none !important;
        background: transparent !important;
        padding: 0 6px !important;
        font-size: 11.5px !important;
    }
    .sn-search-btn {
        background: #fab802 !important;
        color: #111827 !important;
        font-weight: 700 !important;
        font-size: 12px !important;
        padding: 0 16px !important;
        height: 30px !important;
        border-radius: 999px !important;
        border: none !important;
    }
    .sn-hero-section {
        margin-top: 0 !important;
        padding-top: 0 !important;
        margin-bottom: 14px;
    }
    .sn-hero-card {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 14px 16px !important;
        border-radius: 18px !important;
        min-height: auto !important;
    }
    .sn-hero-left {
        max-width: 54% !important;
        margin-bottom: 0 !important;
        text-align: left !important;
    }
    .sn-hero-heading {
        font-size: 20px !important;
        line-height: 1.15 !important;
        margin-bottom: 4px !important;
    }
    .sn-hero-subtitle {
        font-size: 10.5px !important;
        line-height: 1.3 !important;
        margin-bottom: 10px !important;
    }
    .sn-btn-primary {
        padding: 6px 14px !important;
        font-size: 11px !important;
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.25) !important;
    }
    .sn-hero-right {
        width: 44% !important;
        height: auto !important;
        max-height: 120px !important;
    }
    .sn-hero-slide .sn-hero-image {
        max-height: 110px !important;
    }

    /* Category horizontal scroll on mobile */
    .sn-category-scroll-wrap {
        display: flex !important;
        gap: 12px !important;
        overflow-x: auto !important;
        padding: 4px 2px 8px 2px !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
    }
    .sn-category-scroll-wrap::-webkit-scrollbar {
        display: none !important;
    }
    .sn-category-scroll-item {
        width: 62px !important;
        flex-shrink: 0 !important;
    }
    .sn-category-scroll-box {
        width: 56px !important;
        height: 56px !important;
        border-radius: 14px !important;
    }
    .sn-category-scroll-name {
        font-size: 10px !important;
        max-width: 62px !important;
    }

    /* Trust bar mobile horizontal scroll */
    .sn-trust-bar {
        display: flex !important;
        overflow-x: auto !important;
        gap: 10px !important;
        padding: 8px 12px !important;
        border-radius: 12px !important;
        scrollbar-width: none !important;
    }
    .sn-trust-bar::-webkit-scrollbar {
        display: none !important;
    }
    .sn-trust-item {
        flex-shrink: 0 !important;
        gap: 6px !important;
    }
    .sn-trust-icon {
        width: 22px !important;
        height: 22px !important;
    }
    .sn-trust-info h4 {
        font-size: 10px !important;
    }
    .sn-trust-info p {
        font-size: 8.5px !important;
    }

    /* Live Deal Marquee mobile */
    .sn-live-marquee-wrap {
        padding: 7px 10px !important;
        margin-bottom: 16px !important;
        border-radius: 10px !important;
    }
    .sn-marquee-item {
        font-size: 11.5px !important;
        gap: 6px !important;
    }
    .sn-marquee-tag {
        font-size: 8.5px !important;
        padding: 1.5px 5px !important;
    }

    /* Payday banner mobile */
    .sn-payday-banner {
        padding: 12px 14px !important;
        border-radius: 16px !important;
        margin-bottom: 18px !important;
    }
    .sn-payday-left {
        padding: 6px 10px !important;
        border-radius: 10px !important;
    }
    .sn-payday-badge-title {
        font-size: 12px !important;
    }
    .sn-payday-badge-bar {
        height: 2px !important;
        margin: 2px auto !important;
    }
    .sn-payday-badge-sub {
        font-size: 7.5px !important;
    }
    .sn-payday-center {
        padding: 0 10px !important;
    }
    .sn-payday-center-title {
        font-size: 14px !important;
        margin-bottom: 2px !important;
    }
    .sn-payday-center-sub {
        font-size: 9.5px !important;
        margin-bottom: 6px !important;
    }
    .sn-payday-btn {
        padding: 5px 12px !important;
        font-size: 9.5px !important;
    }
    .sn-payday-right img {
        max-height: 55px !important;
        max-width: 72px !important;
    }

    /* Flash sale mobile */
    .sn-flash-header-left {
        gap: 8px !important;
    }
    .sn-flash-countdown {
        padding: 2.5px 8px !important;
    }
    .sn-countdown-label {
        font-size: 8.5px !important;
    }
    .sn-timer-box {
        font-size: 9.5px !important;
        min-width: 18px !important;
        height: 17px !important;
        padding: 0 2px !important;
    }
    .sn-flash-scroll {
        display: flex !important;
        gap: 12px !important;
        overflow-x: auto !important;
        scrollbar-width: none !important;
        padding-bottom: 6px !important;
        -webkit-overflow-scrolling: touch !important;
    }
    .sn-flash-scroll::-webkit-scrollbar {
        display: none !important;
    }
    .sn-flash-card {
        width: 170px !important;
        min-width: 170px !important;
        flex: 0 0 170px !important;
        flex-shrink: 0 !important;
        padding: 12px !important;
    }
    .sn-flash-img-box {
        height: 120px !important;
    }
    .sn-flash-stock-text {
        font-size: 9.5px !important;
    }

    /* Daily Shira deals mobile */
    .sn-shira-grid {
        display: flex !important;
        gap: 12px !important;
        overflow-x: auto !important;
        scrollbar-width: none !important;
        padding-bottom: 6px !important;
        -webkit-overflow-scrolling: touch !important;
    }
    .sn-shira-grid::-webkit-scrollbar {
        display: none !important;
    }
    .sn-shira-card {
        width: 140px !important;
        min-width: 140px !important;
        flex: 0 0 140px !important;
        flex-shrink: 0 !important;
        padding: 10px !important;
    }
    .sn-shira-img-box {
        height: 95px !important;
    }

    /* Mobile Container Padding & Clamp */
    .sn-container {
        padding: 0 10px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Featured Products mobile layout - STRICT 2-COLUMN CLAMPING */
    .sn-featured-section,
    .sn-feed-section {
        margin-bottom: 24px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
    }
    .sn-products-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .sn-product-card {
        padding: 10px 8px !important;
        border-radius: 12px !important;
        min-width: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
    }
    .sn-product-card-link {
        min-width: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .sn-product-img-box {
        width: 100% !important;
        max-width: 100% !important;
        height: 125px !important;
        border-radius: 10px !important;
        margin-bottom: 6px !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
    }
    .sn-product-img-box img {
        max-height: 110px !important;
        max-width: 100% !important;
        width: auto !important;
        object-fit: contain !important;
    }
    .sn-product-title {
        font-size: 11.5px !important;
        min-height: 28px !important;
        margin-bottom: 2px !important;
        width: 100% !important;
        max-width: 100% !important;
        word-break: break-word !important;
        overflow: hidden !important;
    }
    .sn-product-spec {
        font-size: 9.5px !important;
        margin-bottom: 4px !important;
        width: 100% !important;
        max-width: 100% !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        display: block !important;
    }
    .sn-product-rating {
        font-size: 10px !important;
        margin-bottom: 5px !important;
        width: 100% !important;
        max-width: 100% !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .sn-product-bottom {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 4px !important;
        padding-top: 6px !important;
        box-sizing: border-box !important;
    }
    .sn-price-box {
        min-width: 0 !important;
        flex: 1 !important;
        overflow: hidden !important;
    }
    .sn-price-row {
        display: flex !important;
        align-items: baseline !important;
        gap: 4px !important;
        flex-wrap: wrap !important;
    }
    .sn-current-price {
        font-size: 13px !important;
        white-space: nowrap !important;
    }
    .sn-old-price {
        font-size: 9.5px !important;
        white-space: nowrap !important;
    }
    .sn-btn-cart {
        width: 28px !important;
        height: 28px !important;
        flex-shrink: 0 !important;
    }
    .sn-btn-cart svg {
        width: 13px !important;
        height: 13px !important;
    }
    .sn-badge {
        font-size: 9px !important;
        padding: 2px 6px !important;
        top: 8px !important;
        left: 8px !important;
    }

    /* AliExpress Feed Mobile Layout */
    .sn-feed-section {
        margin-bottom: 24px !important;
    }
    .sn-feed-header-badge {
        font-size: 9.5px !important;
        padding: 2px 6px !important;
    }
    .sn-feed-tabs-wrap {
        --sn-feed-sticky-top: var(--sn-mobile-header-height, 50px);
        padding: 8px 4px !important;
        margin: 0 0 12px 0 !important;
        gap: 6px !important;
    }
    .sn-feed-tab {
        padding: 6px 13px !important;
        font-size: 11.5px !important;
        gap: 5px !important;
    }
    .sn-card-wishlist {
        width: 26px !important;
        height: 26px !important;
        top: 8px !important;
        right: 8px !important;
    }
    .sn-card-wishlist svg {
        width: 12px !important;
        height: 12px !important;
    }
    .sn-feed-pill-row {
        gap: 3px !important;
        margin: 1px 0 4px 0 !important;
    }
    .sn-pill-choice, .sn-pill-free, .sn-pill-top {
        font-size: 8.5px !important;
        padding: 1px 4px !important;
    }
    .sn-feed-sales {
        font-size: 10px !important;
    }
    .sn-btn-feed-more {
        padding: 8px 18px !important;
        font-size: 12px !important;
    }
    .sn-feed-end-badge {
        font-size: 11.5px !important;
        padding: 7px 16px !important;
    }
}
</style>

<main class="sn-main-content">
    <div class="sn-container">
        
        <!-- ============================================================
             1. HERO CARD SECTION ("Big Brands Bigger Savings")
             ============================================================ -->
        <?php if ($slider_on == 1): ?>
        <section class="sn-hero-section">
            <div class="sn-hero-card">
                <!-- Mobile Slide Counter Badge (Top Right) -->
                <span class="sn-hero-counter-badge" id="snHeroCounterBadge">1/<?php echo count($heroSlides); ?></span>
                <button type="button" class="sn-slider-arrow sn-slider-prev sn-desktop-only" id="snHeroPrev" aria-label="Previous Slide">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <button type="button" class="sn-slider-arrow sn-slider-next sn-desktop-only" id="snHeroNext" aria-label="Next Slide">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>

                <!-- Left Details -->
                <div class="sn-hero-left">
                    <div class="sn-hero-top-badge-row">
                        <span class="sn-hero-mega-badge">Mega Sale</span>
                    </div>
                    <div class="sn-hero-eyebrow sn-desktop-only"><?php echo htmlspecialchars($hero_tag); ?></div>
                    <h1 class="sn-hero-heading">Big Brands<br>Bigger Savings</h1>
                    <p class="sn-hero-subtitle">Up to 60% Off on Electronics, Home & More!</p>
                    
                    <div class="sn-hero-actions">
                        <a href="<?php echo htmlspecialchars($hero_btn_url); ?>" class="sn-btn-primary">
                            <span>Shop Now</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>

                    <!-- 3 Dots Indicator (Desktop only) -->
                    <div class="sn-hero-dots sn-desktop-only" id="snHeroDots">
                        <?php 
                        $dotsCount = max(3, count($heroSlides));
                        for ($i = 0; $i < $dotsCount; $i++): 
                        ?>
                            <span class="sn-dot <?php if ($i === 0) echo 'active'; ?>" data-slide="<?php echo $i % max(1, count($heroSlides)); ?>"></span>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Right Static Collage -->
                <div class="sn-hero-right">
                    <div class="sn-hero-slider-wrap">
                        <div class="sn-hero-slider-track" id="snHeroSliderTrack">
                            <?php foreach ($heroSlides as $i => $slide): 
                                $slideImg = $slide['photo'];
                                if (!str_starts_with($slideImg, 'http')) {
                                    $slideImg = BASE_URL . 'assets/uploads/' . $slideImg;
                                }
                            ?>
                                <div class="sn-hero-slide <?php if ($i === 0) echo 'active'; ?>" data-slide-index="<?php echo $i; ?>">
                                    <img src="<?php echo htmlspecialchars($slideImg); ?>" alt="Hero Slide <?php echo $i+1; ?>" class="sn-hero-image" loading="<?php echo ($i === 0 ? 'eager' : 'lazy'); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Static Top Brands Best Deals Doodle (Desktop only) -->
                        <div class="sn-hero-doodle-badge sn-desktop-only">
                            <div>Top Brands<br>Best Deals</div>
                            <svg class="sn-doodle-arrow" width="30" height="30" viewBox="0 0 45 45" fill="none">
                                <path d="M10 5 C 28 12, 34 26, 22 38 M 16 33 L 22 38 L 27 32" stroke="#0f172a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>

                        <!-- Static Yellow Spark Rays Doodle (Desktop only) -->
                        <div class="sn-static-doodle-ray sn-desktop-only">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0L14.2 9.2L23 12L14.2 14.8L12 24L9.8 14.8L1 12L9.8 9.2L12 0Z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============================================================
             2. HORIZONTAL CATEGORIES BAR (MATCHING SCREENSHOT)
             ============================================================ -->
        <?php if ($category_on == 1): ?>
        <section class="sn-category-section">
            <div class="sn-section-header" style="margin-bottom: 10px;">
                <h2 class="sn-section-title" style="display:flex; align-items:center; gap:7px;">
                    <span style="display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:7px; background:#fef3c7; color:#d97706;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                    </span>
                    <span><?php echo htmlspecialchars($categories_title); ?></span>
                </h2>
                <a href="<?php echo BASE_URL; ?>categories.php" class="sn-view-all">
                    <span>See All</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </a>
            </div>

            <!-- Horizontal Swipe Container -->
            <div class="sn-category-scroll-wrap">
                <!-- 1. All Categories -->
                <a href="<?php echo BASE_URL; ?>categories.php" class="sn-category-scroll-item">
                    <div class="sn-category-scroll-box" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-color: #fde68a;">
                        <img src="assets/uploads/cat_all.jpg" alt="All Categories" loading="lazy" onerror="this.onerror=null; this.src='assets/uploads/cat_mockup/sub_matching_sets.png';">
                    </div>
                    <span class="sn-category-scroll-name">All Categories</span>
                </a>

                <?php 
                foreach ($categories as $cat):
                    $cPhoto = !empty($cat['photo']) ? $cat['photo'] : 'cat_all.jpg';
                    if (!str_starts_with($cPhoto, 'http') && !file_exists(__DIR__ . '/' . $cPhoto)) {
                        $cPhoto = 'assets/uploads/' . $cPhoto;
                    }
                ?>
                    <a href="<?php echo BASE_URL; ?>categories.php?cat_id=<?php echo $cat['tcat_id']; ?>" class="sn-category-scroll-item">
                        <div class="sn-category-scroll-box">
                            <img src="<?php echo htmlspecialchars($cPhoto); ?>" alt="<?php echo htmlspecialchars($cat['tcat_name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='assets/uploads/cat_all.jpg';">
                        </div>
                        <span class="sn-category-scroll-name"><?php echo htmlspecialchars($cat['tcat_name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============================================================
             3. TRUST HIGHLIGHTS VALUE BAR (PILL CONTAINER)
             ============================================================ -->
        <?php if ($service_on == 1): ?>
        <section class="sn-trust-section">
            <div class="sn-trust-bar">
                <!-- 1. Free Shipping -->
                <div class="sn-trust-item">
                    <div class="sn-trust-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </div>
                    <div class="sn-trust-info">
                        <h4>Free Shipping</h4>
                        <p>On orders over ৳ 2,000</p>
                    </div>
                </div>

                <!-- 2. Secure Payment -->
                <div class="sn-trust-item">
                    <div class="sn-trust-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <div class="sn-trust-info">
                        <h4>Secure Payment</h4>
                        <p>100% secure payments</p>
                    </div>
                </div>

                <!-- 3. 7 Days Return -->
                <div class="sn-trust-item">
                    <div class="sn-trust-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1 4 1 10 7 10"></polyline>
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                        </svg>
                    </div>
                    <div class="sn-trust-info">
                        <h4>7 Days Return</h4>
                        <p>Easy return policy</p>
                    </div>
                </div>

                <!-- 4. 24/7 Support -->
                <div class="sn-trust-item">
                    <div class="sn-trust-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                            <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                        </svg>
                    </div>
                    <div class="sn-trust-info">
                        <h4>24/7 Support</h4>
                        <p>We're here to help</p>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($marquee_on == 1): ?>
        <?php
        $marquee_items = [
            [
                'tag' => !empty($s['marquee_item1_tag']) ? $s['marquee_item1_tag'] : 'HOT',
                'tag_class' => 'red',
                'text' => !empty($s['marquee_item1_text']) ? $s['marquee_item1_text'] : 'MEGA SALE IS LIVE • Up to 80% Off Top Brands',
                'url' => !empty($s['marquee_item1_url']) ? (str_starts_with($s['marquee_item1_url'], 'http') ? $s['marquee_item1_url'] : BASE_URL . ltrim($s['marquee_item1_url'], '/')) : ''
            ],
            [
                'tag' => !empty($s['marquee_item2_tag']) ? $s['marquee_item2_tag'] : 'VOUCHER',
                'tag_class' => '',
                'text' => !empty($s['marquee_item2_text']) ? $s['marquee_item2_text'] : 'Extra 15% OFF On Your First Order',
                'url' => !empty($s['marquee_item2_url']) ? (str_starts_with($s['marquee_item2_url'], 'http') ? $s['marquee_item2_url'] : BASE_URL . ltrim($s['marquee_item2_url'], '/')) : ''
            ],
            [
                'tag' => !empty($s['marquee_item3_tag']) ? $s['marquee_item3_tag'] : 'FREE DELIVERY',
                'tag_class' => 'green',
                'text' => !empty($s['marquee_item3_text']) ? $s['marquee_item3_text'] : 'Free Shipping Across Bangladesh on ৳2,000+',
                'url' => !empty($s['marquee_item3_url']) ? (str_starts_with($s['marquee_item3_url'], 'http') ? $s['marquee_item3_url'] : BASE_URL . ltrim($s['marquee_item3_url'], '/')) : ''
            ],
            [
                'tag' => !empty($s['marquee_item4_tag']) ? $s['marquee_item4_tag'] : 'FLASH DEAL',
                'tag_class' => 'purple',
                'text' => !empty($s['marquee_item4_text']) ? $s['marquee_item4_text'] : 'Limited Time Deals Refreshing Every 6 Hours',
                'url' => !empty($s['marquee_item4_url']) ? (str_starts_with($s['marquee_item4_url'], 'http') ? $s['marquee_item4_url'] : BASE_URL . ltrim($s['marquee_item4_url'], '/')) : ''
            ],
            [
                'tag' => !empty($s['marquee_item5_tag']) ? $s['marquee_item5_tag'] : '100% AUTHENTIC',
                'tag_class' => '',
                'text' => !empty($s['marquee_item5_text']) ? $s['marquee_item5_text'] : 'Verified Brands & 7 Days Hassle-Free Returns',
                'url' => !empty($s['marquee_item5_url']) ? (str_starts_with($s['marquee_item5_url'], 'http') ? $s['marquee_item5_url'] : BASE_URL . ltrim($s['marquee_item5_url'], '/')) : ''
            ]
        ];
        ?>
        <!-- ============================================================
             LIVE DEAL MARQUEE RIBBON (DARAZ / ALIEXPRESS INFINITE TICKER)
             ============================================================ -->
        <div class="sn-live-marquee-wrap">
            <div class="sn-live-marquee-track">
                <?php for ($loop = 0; $loop < 2; $loop++): ?>
                    <?php foreach ($marquee_items as $mItem): ?>
                        <?php if (!empty($mItem['url'])): ?>
                            <a href="<?php echo htmlspecialchars($mItem['url']); ?>" class="sn-marquee-item">
                                <span class="sn-marquee-tag <?php echo $mItem['tag_class']; ?>"><?php echo htmlspecialchars($mItem['tag']); ?></span>
                                <span><?php echo htmlspecialchars($mItem['text']); ?></span>
                            </a>
                        <?php else: ?>
                            <span class="sn-marquee-item">
                                <span class="sn-marquee-tag <?php echo $mItem['tag_class']; ?>"><?php echo htmlspecialchars($mItem['tag']); ?></span>
                                <span><?php echo htmlspecialchars($mItem['text']); ?></span>
                            </span>
                        <?php endif; ?>
                        <span class="sn-marquee-sep"><svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg></span>
                    <?php endforeach; ?>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($payday_on == 1): ?>
        <?php
        $payday_badge_title  = !empty($s['payday_badge_title']) ? $s['payday_badge_title'] : "PAYDAY\nSALE";
        $payday_badge_sub    = !empty($s['payday_badge_sub']) ? $s['payday_badge_sub'] : 'UP TO 80% OFF';
        $payday_center_title = !empty($s['payday_center_title']) ? $s['payday_center_title'] : 'Extra 15% OFF';
        $payday_center_sub   = !empty($s['payday_center_sub']) ? $s['payday_center_sub'] : 'On Your First Order';
        $payday_btn_text     = !empty($s['payday_btn_text']) ? $s['payday_btn_text'] : 'Claim Now';
        $payday_btn_url      = !empty($s['payday_btn_url']) ? $s['payday_btn_url'] : 'product-category.php';
        if (!str_starts_with($payday_btn_url, 'http')) {
            $payday_btn_url = BASE_URL . ltrim($payday_btn_url, '/');
        }
        $payday_image        = !empty($s['payday_image']) ? $s['payday_image'] : 'assets/uploads/payday_cart_transparent.png';
        if (!str_starts_with($payday_image, 'http') && !str_starts_with($payday_image, 'assets/')) {
            $payday_image = 'assets/uploads/' . ltrim($payday_image, '/');
        }
        ?>
        <!-- ============================================================
             4. PAYDAY SALE PROMO BANNER (MATCHING SCREENSHOT + MOTION)
             ============================================================ -->
        <section class="sn-payday-section">
            <div class="sn-payday-banner">
                <!-- Left: Angled Badge with Warm Amber Bar -->
                <div class="sn-payday-left">
                    <div class="sn-payday-badge-title"><?php echo nl2br(htmlspecialchars($payday_badge_title)); ?></div>
                    <div class="sn-payday-badge-bar"></div>
                    <div class="sn-payday-badge-sub"><?php echo htmlspecialchars($payday_badge_sub); ?></div>
                </div>

                <!-- Center: Promo Offer -->
                <div class="sn-payday-center">
                    <div class="sn-payday-center-title"><?php echo htmlspecialchars($payday_center_title); ?></div>
                    <div class="sn-payday-center-sub"><?php echo htmlspecialchars($payday_center_sub); ?></div>
                    <a href="<?php echo htmlspecialchars($payday_btn_url); ?>" class="sn-payday-btn">
                        <span><?php echo htmlspecialchars($payday_btn_text); ?></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>

                <!-- Right: Shopping Cart with Packages Image (Floating Levitation) -->
                <div class="sn-payday-right">
                    <img src="<?php echo htmlspecialchars($payday_image); ?>" alt="Payday Shopping Cart" loading="lazy" onerror="this.onerror=null; this.src='assets/uploads/payday_cart.jpg';">
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============================================================
             5. FLASH SALE SECTION (MULTI-CARD SLIDING CAROUSEL + LIVE COUNTDOWN)
             ============================================================ -->
        <section class="sn-flash-section">
            <div class="sn-section-header">
                <div class="sn-flash-header-left">
                    <h2 class="sn-section-title">
                        <span class="sn-flash-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </span>
                        <span>Flash Sale</span>
                    </h2>
                    <!-- Live Countdown Clock -->
                    <div class="sn-flash-countdown" id="snFlashCountdown" title="Deals end soon!">
                        <span class="sn-countdown-label">Ends In</span>
                        <div class="sn-countdown-boxes">
                            <span class="sn-timer-box" id="snFlashH">04</span>
                            <span class="sn-timer-colon">:</span>
                            <span class="sn-timer-box" id="snFlashM">28</span>
                            <span class="sn-timer-colon">:</span>
                            <span class="sn-timer-box" id="snFlashS">15</span>
                        </div>
                    </div>
                </div>

                <div class="sn-header-right-actions">
                    <!-- Carousel Sliding Arrow Controls -->
                    <div class="sn-carousel-nav sn-desktop-only">
                        <button type="button" class="sn-carousel-btn" id="snFlashPrev" onclick="slideMultiCards('snFlashScroll', -1)" aria-label="Previous Flash Deals">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                        <button type="button" class="sn-carousel-btn" id="snFlashNext" onclick="slideMultiCards('snFlashScroll', 1)" aria-label="Next Flash Deals">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    </div>
                    <a href="<?php echo BASE_URL; ?>deals.php" class="sn-view-all">
                        <span>Shop More</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>
            </div>

            <div class="sn-carousel-container">
                <div class="sn-flash-scroll" id="snFlashScroll">
                    <?php
                    // Fetch dynamic flash sale items from active products in the store's database
                    $dbFlash = [];
                    try {
                        $flashQuery = $pdo->query("SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo FROM tbl_product WHERE p_is_active=1 ORDER BY p_id DESC LIMIT 12");
                        if ($flashQuery) $dbFlash = $flashQuery->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {}

                    $flashItems = [];
                    if (!empty($dbFlash)) {
                        foreach ($dbFlash as $prod) {
                            $curr = (float)str_replace(',', '', (string)$prod['p_current_price']);
                            $old = (float)str_replace(',', '', (string)($prod['p_old_price'] ?? '0'));
                            $discountStr = ($old > $curr && $old > 0) ? '-' . round((($old - $curr) / $old) * 100) . '%' : 'HOT';
                            $photo = !empty($prod['p_featured_photo']) ? $prod['p_featured_photo'] : '';
                            $imgUrl = $photo ? (str_starts_with($photo, 'http') ? $photo : BASE_URL . 'assets/uploads/' . $photo) : BASE_URL . 'assets/images/no-image.png';
                            
                            // Calculate realistic claimed percentage
                            $pidInt = (int)$prod['p_id'];
                            $soldPct = 55 + (($pidInt * 19) % 40); // 55% - 94%

                            $flashItems[] = [
                                'id' => $pidInt,
                                'name' => $prod['p_name'],
                                'curr' => number_format($curr, 2),
                                'old' => ($old > $curr && $old > 0) ? number_format($old, 2) : '',
                                'discount' => $discountStr,
                                'sold' => $soldPct,
                                'img' => $imgUrl,
                                'fallback' => BASE_URL . 'assets/images/no-image.png'
                            ];
                        }
                    }

                    foreach ($flashItems as $fi):
                        $prodUrl = function_exists('getProductURL') ? getProductURL($fi['id'], $fi['name'], BASE_URL) : BASE_URL . 'product.php?id=' . $fi['id'];
                    ?>
                        <a href="<?php echo htmlspecialchars($prodUrl); ?>" class="sn-flash-card" data-href="<?php echo htmlspecialchars($prodUrl); ?>">
                            <div class="sn-flash-card-top">
                                <span class="sn-flash-discount">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2c-.6 2.3-2.1 4.2-4.1 5.4C6 8.5 5 10.6 5 13c0 3.9 3.1 7 7 7s7-3.1 7-7c0-2.8-1.5-5.3-3.7-6.5-.4 1.3-1.3 2.4-2.5 3-1-3-1.8-6.1-.8-7.5z"/></svg>
                                    <span><?php echo $fi['discount']; ?></span>
                                </span>
                                <button type="button" class="sn-flash-wishlist" title="Save to Wishlist" onclick="homeToggleWishlist(<?php echo $fi['id']; ?>, this, event)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                </button>
                            </div>
                            <div class="sn-flash-img-box">
                                <img src="<?php echo htmlspecialchars($fi['img']); ?>" alt="<?php echo htmlspecialchars($fi['name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='<?php echo htmlspecialchars($fi['fallback']); ?>';">
                            </div>
                            <h3 class="sn-flash-title"><?php echo htmlspecialchars($fi['name']); ?></h3>
                            <div class="sn-flash-pricing-row">
                                <div class="sn-flash-curr-price">৳ <?php echo $fi['curr']; ?></div>
                                <?php if (!empty($fi['old'])): ?>
                                    <div class="sn-flash-old-price">৳ <?php echo $fi['old']; ?></div>
                                <?php endif; ?>
                            </div>
                            <!-- Stock Claim Meter -->
                            <div class="sn-flash-stock-wrap">
                                <div class="sn-flash-stock-bar">
                                    <div class="sn-flash-stock-fill" style="width: <?php echo $fi['sold']; ?>%;"></div>
                                </div>
                                <span class="sn-flash-stock-text">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="#ef4444" stroke="none"><path d="M12 2c-.6 2.3-2.1 4.2-4.1 5.4C6 8.5 5 10.6 5 13c0 3.9 3.1 7 7 7s7-3.1 7-7c0-2.8-1.5-5.3-3.7-6.5-.4 1.3-1.3 2.4-2.5 3-1-3-1.8-6.1-.8-7.5z"/></svg>
                                    <span><?php echo $fi['sold']; ?>% Claimed</span>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ============================================================
             6. DAILY SHIRA DEALS SECTION (MULTI-CARD SLIDING CAROUSEL)
             ============================================================ -->
        <section class="sn-shira-section">
            <div class="sn-section-header">
                <h2 class="sn-section-title">
                    <span class="sn-flash-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </span>
                    <span>Daily Shira Deals</span>
                </h2>
                <div class="sn-header-right-actions">
                    <!-- Carousel Sliding Arrow Controls -->
                    <div class="sn-carousel-nav sn-desktop-only">
                        <button type="button" class="sn-carousel-btn" id="snShiraPrev" onclick="slideMultiCards('snShiraGrid', -1)" aria-label="Previous Deals">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                        <button type="button" class="sn-carousel-btn" id="snShiraNext" onclick="slideMultiCards('snShiraGrid', 1)" aria-label="Next Deals">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    </div>
                    <a href="<?php echo BASE_URL; ?>deals.php" class="sn-view-all">
                        <span>Shop Now</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>
            </div>

            <div class="sn-carousel-container">
                <div class="sn-shira-grid" id="snShiraGrid">
                    <?php
                    $shiraDeals = [];
                    try {
                        $sdCats = $pdo->query("SELECT tcat_id, tcat_name, photo FROM tbl_top_category WHERE LOWER(tcat_name) != 'shop' ORDER BY tcat_order ASC, tcat_id ASC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($sdCats as $sc) {
                            $cPhoto = !empty($sc['photo']) ? $sc['photo'] : 'cat_all.jpg';
                            if (!str_starts_with($cPhoto, 'http') && !file_exists(__DIR__ . '/' . $cPhoto)) {
                                $cPhoto = 'assets/uploads/' . $cPhoto;
                            }
                            $shiraDeals[] = [
                                'name' => $sc['tcat_name'],
                                'badge' => 'Featured',
                                'img' => $cPhoto,
                                'url' => BASE_URL . 'categories.php?cat_id=' . $sc['tcat_id']
                            ];
                        }
                    } catch (Throwable $_) {}

                    if (empty($shiraDeals) || count($shiraDeals) < 4) {
                        try {
                            $pDeals = $pdo->query("SELECT p_id, p_name, p_featured_photo, p_current_price, p_old_price FROM tbl_product WHERE p_is_active = 1 ORDER BY (CASE WHEN p_old_price > p_current_price THEN 0 ELSE 1 END), p_id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($pDeals as $pd) {
                                $rPhoto = $pd['p_featured_photo'] ?? '';
                                $rPhotoUrl = !empty($rPhoto) ? (str_starts_with($rPhoto, 'http') ? $rPhoto : (function_exists('get_media_url') ? get_media_url($rPhoto) : BASE_URL . 'assets/uploads/' . $rPhoto)) : BASE_URL . 'assets/images/no-image.png';
                                $pOld = (float)($pd['p_old_price'] ?? 0);
                                $pCurr = (float)($pd['p_current_price'] ?? 0);
                                $badge = ($pOld > $pCurr && $pOld > 0) ? ('-' . round((($pOld - $pCurr) / $pOld) * 100) . '%') : 'Hot Deal';
                                $pSlugUrl = function_exists('getProductURL') ? getProductURL($pd['p_id'], $pd['p_name'], BASE_URL) : BASE_URL . 'product.php?id=' . $pd['p_id'];
                                $shiraDeals[] = [
                                    'name' => $pd['p_name'],
                                    'badge' => $badge,
                                    'img' => $rPhotoUrl,
                                    'url' => $pSlugUrl
                                ];
                            }
                        } catch (Throwable $_) {}
                    }

                    foreach ($shiraDeals as $sd):
                    ?>
                        <a href="<?php echo htmlspecialchars($sd['url']); ?>" class="sn-shira-card" data-href="<?php echo htmlspecialchars($sd['url']); ?>">
                            <div class="sn-shira-img-box">
                                <img src="<?php echo htmlspecialchars($sd['img']); ?>" alt="<?php echo htmlspecialchars($sd['name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/no-image.png';">
                            </div>
                            <span class="sn-shira-badge"><?php echo $sd['badge']; ?></span>
                            <span class="sn-shira-name"><?php echo htmlspecialchars($sd['name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ============================================================
             7. ALIEXPRESS STYLE PERSONALIZED FEED & CONTINUOUS SCROLL
             ============================================================ -->
        <?php if ($featured_on == 1): ?>
        <section class="sn-feed-section sn-featured-section" id="snHomeFeedSection">
            <div class="sn-feed-header-wrap">
                <div class="sn-section-header" style="margin-bottom: 8px;">
                    <h2 class="sn-section-title" style="display: flex; align-items: center; gap: 8px;">
                        <span class="sn-feed-title-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#f59e0b" stroke="#d97706" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </span>
                        <span><?php echo htmlspecialchars($featured_products_title ?: 'Featured Products'); ?></span>
                        <span class="sn-feed-header-badge">Personalized For You</span>
                    </h2>
                    <a href="<?php echo BASE_URL; ?>product-category.php" class="sn-view-all">
                        <span>View All</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>
            </div>
            
            <!-- AliExpress Style Category & Feed Tabs (Sticky Filter Bar) -->
            <div class="sn-feed-tabs-wrap" id="snFeedTabs">
                <button type="button" class="sn-feed-tab active" data-tab="for_you">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <span>For You</span>
                </button>
                <button type="button" class="sn-feed-tab" data-tab="trending">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c-.6 2.3-2.1 4.2-4.1 5.4C6 8.5 5 10.6 5 13c0 3.9 3.1 7 7 7s7-3.1 7-7c0-2.8-1.5-5.3-3.7-6.5-.4 1.3-1.3 2.4-2.5 3-1-3-1.8-6.1-.8-7.5z"/></svg>
                    <span>Best Sellers</span>
                </button>
                <button type="button" class="sn-feed-tab" data-tab="deals">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    <span>Super Deals</span>
                </button>
                <button type="button" class="sn-feed-tab" data-tab="top_rated">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#f59e0b" stroke="#d97706" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <span>Top Rated</span>
                </button>
                <button type="button" class="sn-feed-tab" data-tab="choice">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
                    <span>Choice</span>
                </button>
            </div>

            <!-- Product Feed Grid -->
            <div class="sn-products-grid" id="snProductsFeedGrid">
                <?php 
                $initialIds = [];
                if (!empty($featuredProducts)) {
                    foreach ($featuredProducts as $p) {
                        $initialIds[] = (int)$p['p_id'];
                        echo renderAliProductCard($p, $currencySymbol);
                    }
                }
                ?>
            </div>

            <!-- AliExpress Shimmer Skeleton Loader (Pulsing while fetching) -->
            <div class="sn-products-grid sn-feed-skeleton" id="snFeedSkeleton" style="display: none;">
                <?php for ($sk = 0; $sk < 4; $sk++): ?>
                    <div class="sn-product-card sn-skeleton-card">
                        <div class="sn-product-img-box sn-skeleton-box"></div>
                        <div class="sn-skeleton-box" style="width: 85%; height: 14px; margin-bottom: 6px;"></div>
                        <div class="sn-skeleton-box" style="width: 55%; height: 12px; margin-bottom: 12px;"></div>
                        <div class="sn-product-bottom">
                            <div class="sn-skeleton-box" style="width: 60px; height: 18px;"></div>
                            <div class="sn-skeleton-box" style="width: 30px; height: 30px; border-radius: 50%;"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Sentinel element for IntersectionObserver infinite scroll -->
            <div id="snFeedSentinel" style="height: 20px; margin-top: 10px;"></div>

            <!-- Manual Load More button fallback -->
            <div class="sn-feed-actions-row" id="snFeedActionsRow" style="text-align: center; margin: 18px 0 10px 0;">
                <button type="button" class="sn-btn-feed-more" id="snFeedMoreBtn" onclick="triggerFeedLoadMore()">
                    <span>Load More Deals</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
            </div>

            <!-- End of Feed Banner -->
            <div id="snFeedEnd" class="sn-feed-end" style="display: none;">
                <div class="sn-feed-end-badge">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>You've seen all top personalized recommendations!</span>
                </div>
                <button type="button" class="sn-feed-btn-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
                    <span>Back to Top</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline-block; vertical-align:middle; margin-left:4px;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                </button>
            </div>
        </section>
        <?php endif; ?>

    </div>
    <div id="sn-home-toast" class="sn-home-toast" style="display:none;"></div>
</main>

<!-- ============================================================
     HERO SLIDER AUTOMATIC ENGINE (JS)
     ============================================================ -->
<script>
function initHeroSlider() {
    const slides = document.querySelectorAll('.sn-hero-slide');
    const dots = document.querySelectorAll('#snHeroDots .sn-dot');
    const prevBtn = document.getElementById('snHeroPrev');
    const nextBtn = document.getElementById('snHeroNext');
    const heroCard = document.querySelector('.sn-hero-card');
    const counterBadge = document.getElementById('snHeroCounterBadge');

    if (slides.length <= 1) return;

    let currentIndex = 0;
    const totalSlides = slides.length;
    const autoplayEnabled = <?php echo ($hero_slider_autoplay == 1 ? 'true' : 'false'); ?>;
    const intervalMs = <?php echo (int)($hero_slider_interval > 0 ? $hero_slider_interval : 4500); ?>;
    let autoPlayTimer = null;

    function showSlide(index) {
        if (index < 0) index = totalSlides - 1;
        if (index >= totalSlides) index = 0;

        slides.forEach((slide, i) => {
            slide.classList.toggle('active', i === index);
        });

        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
        });

        if (counterBadge) {
            counterBadge.textContent = (index + 1) + '/' + totalSlides;
        }

        currentIndex = index;
    }

    function nextSlide() {
        showSlide(currentIndex + 1);
    }

    function prevSlide() {
        showSlide(currentIndex - 1);
    }

    function startAutoplay() {
        if (autoplayEnabled && !autoPlayTimer) {
            autoPlayTimer = setInterval(nextSlide, intervalMs);
        }
    }

    function stopAutoplay() {
        if (autoPlayTimer) {
            clearInterval(autoPlayTimer);
            autoPlayTimer = null;
        }
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            stopAutoplay();
            nextSlide();
            startAutoplay();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            stopAutoplay();
            prevSlide();
            startAutoplay();
        });
    }

    dots.forEach((dot, idx) => {
        dot.addEventListener('click', function() {
            stopAutoplay();
            showSlide(idx);
            startAutoplay();
        });
    });

    if (heroCard) {
        heroCard.addEventListener('mouseenter', stopAutoplay);
        heroCard.addEventListener('mouseleave', startAutoplay);

        // Touch swipe support for mobile
        let startX = 0;
        heroCard.addEventListener('touchstart', function(e) {
            startX = e.touches[0].clientX;
            stopAutoplay();
        }, { passive: true });

        heroCard.addEventListener('touchend', function(e) {
            const endX = e.changedTouches[0].clientX;
            const diff = startX - endX;
            if (Math.abs(diff) > 40) {
                if (diff > 0) nextSlide();
                else prevSlide();
            }
            startAutoplay();
        }, { passive: true });
    }

    startAutoplay();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeroSlider);
} else {
    initHeroSlider();
}
document.addEventListener('shopnext:page-loaded', initHeroSlider);

// Home Add To Cart AJAX
function homeAddToCart(productId, productName, btn) {
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<svg class="sn-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="#0f172a"></path></svg>`;

    const fd = new FormData();
    fd.append('product_id', productId);
    fd.append('quantity', 1);

    fetch('add-to-cart-ajax.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (data.success) {
            showHomeToast('"' + productName + '" added to cart!', 'success');
            if (data.cart_count) {
                updateCartBadges(data.cart_count);
            }
        } else {
            showHomeToast(data.message || 'Added to cart!', 'success');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        showHomeToast('"' + productName + '" added to cart!', 'success');
    });
}

// Home Wishlist Toggle AJAX with Heart Pop Animation
function homeToggleWishlist(productId, btn, e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    btn.classList.add('sn-heart-pop');
    setTimeout(() => btn.classList.remove('sn-heart-pop'), 400);
    btn.classList.toggle('active');
    const isSaved = btn.classList.contains('active');

    const fd = new FormData();
    fd.append('product_id', productId);
    fd.append('action', isSaved ? 'add' : 'remove');

    fetch('wishlist_action.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'error' && data.message && data.message.includes('logged in')) {
            showHomeToast('Please log in to save to your wishlist', 'info');
        } else {
            showHomeToast(isSaved ? 'Saved to wishlist!' : 'Removed from wishlist', 'info');
        }
    })
    .catch(() => {
        showHomeToast(isSaved ? 'Saved to wishlist!' : 'Removed from wishlist', 'info');
    });
}

// ============================================================
// DARAZ & ALIEXPRESS MULTI-CARD CAROUSEL & COUNTDOWN CONTROLLER
// ============================================================
function slideMultiCards(trackId, direction) {
    const track = document.getElementById(trackId);
    if (!track) return;
    // Advance multiple cards (~75% of container width)
    const slideAmount = Math.max(260, Math.floor(track.clientWidth * 0.75)) * direction;
    track.scrollBy({ left: slideAmount, behavior: 'smooth' });
}

function setupCarouselTrack(trackId, prevBtnId, nextBtnId) {
    const track = document.getElementById(trackId);
    const prevBtn = document.getElementById(prevBtnId);
    const nextBtn = document.getElementById(nextBtnId);
    if (!track) return;

    function updateNavButtons() {
        const maxScroll = track.scrollWidth - track.clientWidth - 5;
        if (prevBtn) prevBtn.disabled = track.scrollLeft <= 5;
        if (nextBtn) nextBtn.disabled = track.scrollLeft >= maxScroll;
    }

    track.addEventListener('scroll', updateNavButtons, { passive: true });
    window.addEventListener('resize', updateNavButtons, { passive: true });
    updateNavButtons();

    // Mouse drag scrolling support on desktop
    let isDown = false;
    let startX = 0;
    let scrollLeft = 0;

    track.addEventListener('mousedown', (e) => {
        if (e.target.closest('button') || e.target.closest('a')) return;
        isDown = true;
        startX = e.pageX - track.offsetLeft;
        scrollLeft = track.scrollLeft;
        track.style.cursor = 'grabbing';
    });
    track.addEventListener('mouseleave', () => { isDown = false; track.style.cursor = ''; });
    track.addEventListener('mouseup', () => { isDown = false; track.style.cursor = ''; });
    track.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - track.offsetLeft;
        const walk = (x - startX) * 1.5;
        track.scrollLeft = scrollLeft - walk;
    });
}

function initFlashCountdown() {
    const hEl = document.getElementById('snFlashH');
    const mEl = document.getElementById('snFlashM');
    const sEl = document.getElementById('snFlashS');
    if (!hEl || !mEl || !sEl) return;

    function updateTimer() {
        const now = new Date();
        const currentH = now.getHours();
        const nextCycleH = (Math.floor(currentH / 6) + 1) * 6;
        const target = new Date(now);
        target.setHours(nextCycleH, 0, 0, 0);

        let diff = Math.max(0, Math.floor((target.getTime() - now.getTime()) / 1000));
        const hours = Math.floor(diff / 3600);
        diff %= 3600;
        const minutes = Math.floor(diff / 60);
        const seconds = diff % 60;

        hEl.textContent = String(hours).padStart(2, '0');
        mEl.textContent = String(minutes).padStart(2, '0');
        sEl.textContent = String(seconds).padStart(2, '0');
    }
    updateTimer();
    setInterval(updateTimer, 1000);
}

function initHomeCarousels() {
    setupCarouselTrack('snFlashScroll', 'snFlashPrev', 'snFlashNext');
    setupCarouselTrack('snShiraGrid', 'snShiraPrev', 'snShiraNext');
    initFlashCountdown();
}

// Toast Feedback System
var homeToastTimer = null;
function showHomeToast(msg, type) {
    let toast = document.getElementById('sn-home-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'sn-home-toast';
        toast.className = 'sn-home-toast';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.display = 'flex';
    toast.classList.add('visible');

    if (homeToastTimer) clearTimeout(homeToastTimer);
    homeToastTimer = setTimeout(() => {
        toast.classList.remove('visible');
        setTimeout(() => toast.style.display = 'none', 300);
    }, 2800);
}

// Synchronize Header and Mobile Cart Badges
function updateCartBadges(count) {
    const badgeSelectors = [
        '#sn-cart-badge-count',
        '#sn-dock-cart-count',
        '.sn-dock-cart-badge',
        '#snSubCartCount',
        '.sn-cart-count',
        '.cart-count'
    ];
    badgeSelectors.forEach(sel => {
        document.querySelectorAll(sel).forEach(el => {
            el.textContent = count;
            el.style.display = count > 0 ? '' : 'none';
            el.style.transform = 'scale(1.4)';
            el.style.transition = 'transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            setTimeout(() => { el.style.transform = 'scale(1)'; }, 250);
        });
    });
    window.dispatchEvent(new CustomEvent('shopnext:cart-updated', { detail: { count: count } }));
}

// ============================================================
// ALIEXPRESS STYLE PERSONALIZED FEED & CONTINUOUS SCROLL CONTROLLER
// ============================================================
var snFeedTab = 'for_you';
var snFeedPage = 1;
var snFeedLoading = false;
var snFeedHasMore = true;
var snLoadedIds = new Set(<?php echo json_encode($initialIds ?? []); ?>);

function getClientRecentViewedIds() {
    try {
        const raw = localStorage.getItem('recentlyViewed');
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                return parsed.map(item => typeof item === 'object' ? item.id : item).filter(Boolean);
            }
        }
    } catch(e) {}
    return [];
}

async function fetchNextFeedBatch(reset = false) {
    if (snFeedLoading || (!snFeedHasMore && !reset)) return;
    
    snFeedLoading = true;
    const skeleton = document.getElementById('snFeedSkeleton');
    const moreBtn = document.getElementById('snFeedMoreBtn');
    const endNotice = document.getElementById('snFeedEnd');
    const grid = document.getElementById('snProductsFeedGrid');
    
    if (skeleton) skeleton.style.display = 'grid';
    if (moreBtn) moreBtn.style.display = 'none';
    if (endNotice && reset) endNotice.style.display = 'none';

    const targetPage = reset ? 1 : (snFeedPage + 1);
    const excludeArr = reset ? [] : Array.from(snLoadedIds);
    const recentArr = getClientRecentViewedIds();

    const params = new URLSearchParams({
        tab: snFeedTab,
        page: targetPage,
        limit: 10,
        exclude: excludeArr.join(','),
        recent: recentArr.join(',')
    });

    try {
        const res = await fetch('fetch_home_feed.php?' + params.toString());
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        if (data.success && data.html) {
            if (reset) {
                grid.innerHTML = data.html;
                snLoadedIds = new Set(data.product_ids || []);
                snFeedPage = 1;
            } else {
                grid.insertAdjacentHTML('beforeend', data.html);
                if (data.product_ids && Array.isArray(data.product_ids)) {
                    data.product_ids.forEach(id => snLoadedIds.add(id));
                }
                snFeedPage = targetPage;
            }

            snFeedHasMore = Boolean(data.has_more);
            bindFeedCardNavigation();

            if (!snFeedHasMore) {
                if (endNotice) endNotice.style.display = 'block';
                if (moreBtn) moreBtn.style.display = 'none';
            } else {
                if (moreBtn) moreBtn.style.display = 'inline-flex';
            }
        } else {
            snFeedHasMore = false;
            if (endNotice) endNotice.style.display = 'block';
            if (moreBtn) moreBtn.style.display = 'none';
        }
    } catch (err) {
        console.warn('Feed fetch error:', err);
        if (moreBtn) {
            moreBtn.style.display = 'inline-flex';
            moreBtn.innerHTML = '<span>Retry Loading</span>';
        }
    } finally {
        snFeedLoading = false;
        if (skeleton) skeleton.style.display = 'none';
    }
}

function triggerFeedLoadMore() {
    fetchNextFeedBatch(false);
}

function bindFeedCardNavigation() {
    document.querySelectorAll('.sn-feed-card:not([data-bound])').forEach(card => {
        card.setAttribute('data-bound', '1');
        card.addEventListener('click', function(e) {
            var targetEl = e.target instanceof Element ? e.target : (e.target && e.target.parentElement instanceof Element ? e.target.parentElement : null);
            if (!targetEl || targetEl.closest('.sn-btn-cart') || targetEl.closest('.sn-card-wishlist') || targetEl.closest('a')) {
                return;
            }
            const href = this.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    });
}

function initAliFeed() {
    bindFeedCardNavigation();

    // Tab buttons
    const tabs = document.querySelectorAll('#snFeedTabs .sn-feed-tab');
    tabs.forEach(tabBtn => {
        tabBtn.addEventListener('click', function() {
            if (snFeedLoading) return;
            const newTab = this.getAttribute('data-tab');
            if (newTab === snFeedTab) return;

            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            snFeedTab = newTab;
            snFeedPage = 1;
            snFeedHasMore = true;
            snLoadedIds = new Set();

            const grid = document.getElementById('snProductsFeedGrid');
            if (grid) grid.innerHTML = '';

            fetchNextFeedBatch(true);
        });
    });

    // IntersectionObserver for Continuous Scroll Fetching
    const sentinel = document.getElementById('snFeedSentinel');
    if (sentinel && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            const entry = entries[0];
            if (entry && entry.isIntersecting && !snFeedLoading && snFeedHasMore) {
                fetchNextFeedBatch(false);
            }
        }, {
            root: null,
            rootMargin: '450px',
            threshold: 0.01
        });
        observer.observe(sentinel);
    }

    // Scroll fallback (supports all browsers and fast fling scrolling)
    let scrollTimeout = null;
    window.addEventListener('scroll', function() {
        if (scrollTimeout) return;
        scrollTimeout = setTimeout(() => {
            scrollTimeout = null;
            if (snFeedLoading || !snFeedHasMore) return;
            const scrollBottom = window.innerHeight + window.scrollY;
            const docHeight = document.documentElement.offsetHeight || document.body.offsetHeight;
            if (scrollBottom >= docHeight - 750) {
                fetchNextFeedBatch(false);
            }
        }, 150);
    }, { passive: true });
}

function initHomePage() {
    initAliFeed();
    initHomeCarousels();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHomePage);
} else {
    initHomePage();
}
document.addEventListener('shopnext:page-loaded', initHomePage);
</script>

<?php require_once(__DIR__ . '/footer.php'); ?>