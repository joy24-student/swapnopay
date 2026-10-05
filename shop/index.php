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

require_once('header.php'); 

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

// Featured Products Defaults & Data
$featured_products_title = !empty($s['featured_products_title']) ? $s['featured_products_title'] : 'Featured Products';
$featuredProducts = [];
try {
    $prodStmt = $pdo->prepare("SELECT p_id, p_name, p_short_description, p_current_price, p_old_price, p_featured_photo, is_top_sale 
                             FROM tbl_product 
                             WHERE p_is_featured = 1 AND p_is_active = 1 
                             ORDER BY p_id DESC 
                             LIMIT 8");
    $prodStmt->execute();
    $featuredProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $_) {}

if (empty($featuredProducts)) {
    // If no products explicitly marked featured, display active store products
    try {
        $prodStmt = $pdo->prepare("SELECT p_id, p_name, p_short_description, p_current_price, p_old_price, p_featured_photo, is_top_sale 
                                 FROM tbl_product 
                                 WHERE p_is_active = 1 
                                 ORDER BY p_id DESC 
                                 LIMIT 8");
        $prodStmt->execute();
        $featuredProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $_) {}
}

// Fetch Real Ratings from Database
$ratingMap = [];
if (!empty($featuredProducts)) {
    try {
        $prodIds = array_column($featuredProducts, 'p_id');
        $inClause = implode(',', array_map('intval', $prodIds));
        $rStmt = $pdo->query("SELECT p_id, AVG(rating) as avg_rating, COUNT(*) as rev_count FROM tbl_rating WHERE p_id IN ($inClause) GROUP BY p_id");
        $rRows = $rStmt ? $rStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rRows as $rr) {
            $ratingMap[$rr['p_id']] = [
                'rating' => round((float)$rr['avg_rating'], 1),
                'count' => $rr['rev_count']
            ];
        }
    } catch (Throwable $_) {}
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

.sn-main-content {
    margin-top: 0 !important;
    padding: 16px 0 48px 0 !important;
}

.sn-container {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 16px;
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

/* 4. PAYDAY SALE PROMO BANNER */
.sn-payday-section {
    margin-bottom: 26px;
}

.sn-payday-banner {
    background: linear-gradient(100deg, #0d121c 0%, #151d2a 50%, #1e293b 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    padding: 24px 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

.sn-payday-left {
    background: #0f172a;
    border: 2px solid #fab802;
    border-radius: 14px;
    padding: 12px 20px;
    text-align: center;
    transform: rotate(-3deg);
    box-shadow: 0 4px 14px rgba(250, 184, 2, 0.2);
    flex-shrink: 0;
}

.sn-payday-badge-title {
    font-size: 18px;
    font-weight: 900;
    color: #ffffff;
    line-height: 1.05;
    letter-spacing: 0.5px;
}

.sn-payday-badge-sub {
    font-size: 11px;
    font-weight: 800;
    color: #fab802;
    margin-top: 4px;
    letter-spacing: 0.8px;
}

.sn-payday-center {
    flex: 1;
    padding: 0 32px;
    color: #ffffff;
}

.sn-payday-center-title {
    font-size: 26px;
    font-weight: 800;
    line-height: 1.15;
    margin-bottom: 4px;
    letter-spacing: -0.5px;
    color: #ffffff;
}

.sn-payday-center-sub {
    font-size: 13.5px;
    color: #94a3b8;
    margin-bottom: 14px;
}

.sn-payday-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fab802;
    color: #0f172a !important;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 20px;
    border-radius: 50px;
    text-decoration: none !important;
    box-shadow: 0 4px 12px rgba(250, 184, 2, 0.3);
    transition: all 0.2s ease;
}

.sn-payday-btn:hover {
    background: #e5a700;
    transform: translateY(-1px);
}

.sn-payday-right {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    max-height: 120px;
}

.sn-payday-right img {
    max-height: 110px;
    max-width: 180px;
    object-fit: contain;
    filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.25));
}

/* 5. FLASH SALE SECTION */
.sn-flash-section {
    margin-bottom: 26px;
}

.sn-flash-scroll {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.sn-flash-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    position: relative;
    text-decoration: none !important;
    color: var(--sn-dark) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.25s ease;
}

.sn-flash-card:hover {
    transform: translateY(-3px);
    border-color: #f1f5f9;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    outline: none !important;
}

.sn-flash-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 8px;
}

.sn-flash-discount {
    background: #ef4444;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 6px;
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
}

.sn-flash-wishlist.active svg {
    fill: #ef4444;
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
    transition: transform 0.3s ease;
}

.sn-flash-card:hover .sn-flash-img-box img {
    transform: scale(1.05);
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

/* 6. DAILY SHIRA DEALS */
.sn-shira-section {
    margin-bottom: 26px;
}

.sn-shira-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.sn-shira-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none !important;
    color: var(--sn-dark) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.25s ease;
}

.sn-shira-card:hover {
    transform: translateY(-3px);
    border-color: #f1f5f9;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
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
    transition: transform 0.3s ease;
}

.sn-shira-card:hover .sn-shira-img-box img {
    transform: scale(1.05);
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

/* 7. FEATURED PRODUCTS SECTION */
.sn-featured-section {
    margin-bottom: 26px;
}

.sn-products-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
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
}

.sn-product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    border-color: #f1f5f9;
    outline: none !important;
}

.sn-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 5px;
    background: var(--sn-primary);
    color: #0f172a;
    z-index: 5;
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
        grid-template-columns: repeat(3, 1fr);
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
        padding: 4px 10px !important;
        font-size: 9.5px !important;
    }
    .sn-payday-right img {
        max-height: 52px !important;
        max-width: 70px !important;
    }

    /* Flash sale mobile */
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
        width: 175px !important;
        flex-shrink: 0 !important;
        padding: 12px !important;
    }
    .sn-flash-img-box {
        height: 120px !important;
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
        flex-shrink: 0 !important;
        padding: 10px !important;
    }
    .sn-shira-img-box {
        height: 95px !important;
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

        <!-- ============================================================
             4. PAYDAY SALE PROMO BANNER (MATCHING SCREENSHOT)
             ============================================================ -->
        <section class="sn-payday-section">
            <div class="sn-payday-banner">
                <!-- Left: Angled Badge -->
                <div class="sn-payday-left">
                    <div class="sn-payday-badge-title">PAYDAY<br>SALE</div>
                    <div class="sn-payday-badge-sub">UP TO 80% OFF</div>
                </div>

                <!-- Center: Promo Offer -->
                <div class="sn-payday-center">
                    <div class="sn-payday-center-title">Extra 15% OFF</div>
                    <div class="sn-payday-center-sub">On Your First Order</div>
                    <a href="<?php echo BASE_URL; ?>product-category.php" class="sn-payday-btn">
                        <span>Claim Now</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>

                <!-- Right: Shopping Cart with Packages Image -->
                <div class="sn-payday-right">
                    <img src="assets/uploads/payday_cart.jpg" alt="Payday Shopping Cart" loading="lazy">
                </div>
            </div>
        </section>

        <!-- ============================================================
             5. FLASH SALE SECTION (MATCHING SCREENSHOT)
             ============================================================ -->
        <section class="sn-flash-section">
            <div class="sn-section-header">
                <h2 class="sn-section-title">
                    <span class="sn-flash-icon">⚡</span>
                    <span>Flash Sale</span>
                </h2>
                <a href="<?php echo BASE_URL; ?>deals.php" class="sn-view-all">
                    <span>Shop More</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </a>
            </div>

            <div class="sn-flash-scroll">
                <?php
                // Fetch dynamic flash sale items from active products in the store's database
                $dbFlash = [];
                try {
                    $flashQuery = $pdo->query("SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo FROM tbl_product WHERE p_is_active=1 ORDER BY p_id DESC LIMIT 8");
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
                        $flashItems[] = [
                            'id' => (int)$prod['p_id'],
                            'name' => $prod['p_name'],
                            'curr' => number_format($curr, 2),
                            'old' => ($old > $curr && $old > 0) ? number_format($old, 2) : '',
                            'discount' => $discountStr,
                            'img' => $imgUrl,
                            'fallback' => BASE_URL . 'assets/images/no-image.png'
                        ];
                    }
                }

                foreach ($flashItems as $fi):
                    $prodUrl = function_exists('getProductURL') ? getProductURL($fi['id'], $fi['name'], BASE_URL) : BASE_URL . 'product.php?id=' . $fi['id'];
                ?>
                    <a href="<?php echo htmlspecialchars($prodUrl); ?>" class="sn-flash-card">
                        <div class="sn-flash-card-top">
                            <span class="sn-flash-discount"><?php echo $fi['discount']; ?></span>
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
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============================================================
             6. DAILY SHIRA DEALS SECTION (MATCHING SCREENSHOT)
             ============================================================ -->
        <section class="sn-shira-section">
            <div class="sn-section-header">
                <h2 class="sn-section-title">
                    <span class="sn-flash-icon">🕒</span>
                    <span>Daily Shira Deals</span>
                </h2>
                <a href="<?php echo BASE_URL; ?>deals.php" class="sn-view-all">
                    <span>Shop Now</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </a>
            </div>

            <div class="sn-shira-grid">
                <?php
                $shiraDeals = [];
                try {
                    $sdCats = $pdo->query("SELECT tcat_id, tcat_name, photo FROM tbl_top_category WHERE LOWER(tcat_name) != 'shop' ORDER BY tcat_order ASC, tcat_id ASC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
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

                if (empty($shiraDeals)) {
                    try {
                        $pDeals = $pdo->query("SELECT p_id, p_name, p_featured_photo, p_current_price, p_old_price FROM tbl_product WHERE p_is_active = 1 ORDER BY (CASE WHEN p_old_price > p_current_price THEN 0 ELSE 1 END), p_id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($pDeals as $pd) {
                            $rPhoto = $pd['p_featured_photo'] ?? '';
                            $rPhotoUrl = !empty($rPhoto) ? (str_starts_with($rPhoto, 'http') ? $rPhoto : (function_exists('get_media_url') ? get_media_url($rPhoto) : BASE_URL . 'assets/uploads/' . $rPhoto)) : BASE_URL . 'assets/images/no-image.png';
                            $pOld = (float)($pd['p_old_price'] ?? 0);
                            $pCurr = (float)($pd['p_current_price'] ?? 0);
                            $badge = ($pOld > $pCurr && $pOld > 0) ? ('-' . round((($pOld - $pCurr) / $pOld) * 100) . '%') : 'Hot Deal';
                            $shiraDeals[] = [
                                'name' => $pd['p_name'],
                                'badge' => $badge,
                                'img' => $rPhotoUrl,
                                'url' => BASE_URL . 'product.php?id=' . $pd['p_id']
                            ];
                        }
                    } catch (Throwable $_) {}
                }

                foreach ($shiraDeals as $sd):
                ?>
                    <a href="<?php echo htmlspecialchars($sd['url']); ?>" class="sn-shira-card">
                        <div class="sn-shira-img-box">
                            <img src="<?php echo htmlspecialchars($sd['img']); ?>" alt="<?php echo htmlspecialchars($sd['name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/no-image.png';">
                        </div>
                        <span class="sn-shira-badge"><?php echo $sd['badge']; ?></span>
                        <span class="sn-shira-name"><?php echo htmlspecialchars($sd['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============================================================
             7. FEATURED PRODUCTS & PROMO (DESKTOP EXTENSION)
             ============================================================ -->
        <?php if ($featured_on == 1 && !empty($featuredProducts)): ?>
        <section class="sn-featured-section sn-desktop-only">
            <div class="sn-section-header">
                <h2 class="sn-section-title"><?php echo htmlspecialchars($featured_products_title); ?></h2>
                <a href="<?php echo BASE_URL; ?>product-category.php" class="sn-view-all">
                    <span>View All</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </a>
            </div>

            <div class="sn-products-grid">
                <?php foreach ($featuredProducts as $idx => $p): 
                    $currPrice = (float)str_replace(',', '', $p['p_current_price']);
                    $oldPrice = (float)str_replace(',', '', $p['p_old_price'] ?? '0');
                    $hasDiscount = ($oldPrice > $currPrice);
                    $discountPct = $hasDiscount ? round((($oldPrice - $currPrice) / $oldPrice) * 100) : 0;
                    
                    $rInfo = $ratingMap[$p['p_id']] ?? null;
                    $score = $rInfo ? $rInfo['rating'] : 0;
                    $reviewsLabel = $rInfo ? $rInfo['count'] : 0;

                    $prodPhoto = !empty($p['p_featured_photo']) ? $p['p_featured_photo'] : 'assets/images/no-image.png';
                    if (!str_starts_with($prodPhoto, 'http')) {
                        $prodPhoto = BASE_URL . 'assets/uploads/' . $prodPhoto;
                    }
                ?>
                    <div class="sn-product-card">
                        <?php if ($idx === 0): ?>
                            <span class="sn-badge">Best Seller</span>
                        <?php elseif ($hasDiscount): ?>
                            <span class="sn-badge">-<?php echo $discountPct; ?>%</span>
                        <?php endif; ?>

                        <a href="<?php echo BASE_URL; ?>product.php?id=<?php echo $p['p_id']; ?>" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; flex:1;">
                            <div class="sn-product-img-box">
                                <img src="<?php echo htmlspecialchars($prodPhoto); ?>" alt="<?php echo htmlspecialchars($p['p_name']); ?>" loading="lazy">
                            </div>
                            <h3 class="sn-product-title"><?php echo htmlspecialchars($p['p_name']); ?></h3>
                            <p class="sn-product-spec"><?php echo htmlspecialchars($p['p_short_description'] ?? ''); ?></p>
                            
                            <?php if ($score > 0 && $reviewsLabel > 0): ?>
                            <div class="sn-product-rating">
                                <span class="sn-rating-star">★</span>
                                <span><?php echo $score; ?></span>
                                <span class="sn-rating-count">(<?php echo $reviewsLabel; ?>)</span>
                            </div>
                            <?php endif; ?>
                        </a>

                        <div class="sn-product-bottom">
                            <div class="sn-price-box">
                                <span class="sn-current-price"><?php echo $currencySymbol . number_format($currPrice); ?></span>
                                <?php if ($hasDiscount): ?>
                                    <span class="sn-old-price"><?php echo $currencySymbol . number_format($oldPrice); ?></span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="sn-btn-cart" onclick="homeAddToCart(<?php echo $p['p_id']; ?>, '<?php echo htmlspecialchars(addslashes($p['p_name'])); ?>', this)" title="Add to cart" aria-label="Add to Cart">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
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
document.addEventListener('DOMContentLoaded', function() {
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
});

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

// Home Wishlist Toggle AJAX
function homeToggleWishlist(productId, btn, e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
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

// Toast Feedback System
let homeToastTimer = null;
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
    const desktopBadge = document.getElementById('sn-cart-badge-count');
    if (desktopBadge) desktopBadge.textContent = count;
    const mobileBadge = document.querySelector('.sn-dock-cart-badge');
    if (mobileBadge) mobileBadge.textContent = count;
}
</script>

<?php require_once('footer.php'); ?>