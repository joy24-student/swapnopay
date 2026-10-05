<?php
// Start output buffering & session
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once("admin/inc/config.php");
require_once("admin/inc/functions.php");
require_once("admin/inc/CSRF_Protect.php");
require_once("admin/inc/seo_helpers.php");
require_once("admin/inc/supabase_storage.php");

$csrf = new CSRF_Protect();
$error_message = '';
$success_message = '';

// Handle both old-style (?id=) and new SEO-friendly URLs
$p_id = null;

if (!isset($_REQUEST['slug']) && !isset($_REQUEST['id'])) {
    $uriPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    if (preg_match('#/(?:[a-zA-Z0-9_-]+/)?product/([a-zA-Z0-9_-]+)/?$#', $uriPath, $m)) {
        $_REQUEST['slug'] = $m[1];
        $_GET['slug'] = $m[1];
    }
}

if (isset($_REQUEST['slug'])) {
    $slug = $_REQUEST['slug'];
    $p_id = getProductIdBySlug($slug, $pdo);
    
    if (!$p_id) {
        header("HTTP/1.1 404 Not Found");
        header('location: ' . BASE_URL . 'index.php');
        exit;
    }
} elseif (isset($_REQUEST['id'])) {
    $p_id = (int)$_REQUEST['id'];
    
    $statement = $pdo->prepare("SELECT p_id, p_name FROM tbl_product WHERE p_id=? AND p_is_active=1");
    $statement->execute(array($p_id));
    
    if ($statement->rowCount() == 0) {
        header('location: ' . BASE_URL . 'index.php');
        exit;
    }
    
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $newUrl = getProductURL($p_id, $row['p_name'], BASE_URL);
    redirect301($newUrl);
    exit;
} else {
    header('location: ' . BASE_URL . 'index.php');
    exit;
}

// Fetch product details
$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=? AND p_is_active=1");
$statement->execute(array($p_id));
$product_data = $statement->fetch(PDO::FETCH_ASSOC);

if (!$product_data) {
    header("HTTP/1.1 404 Not Found");
    header('location: ' . BASE_URL . 'index.php');
    exit;
}

$p_name = $product_data['p_name'];
$p_old_price = (float)($product_data['p_old_price'] ?? 0);
$p_current_price = (float)($product_data['p_current_price'] ?? 0);
$p_qty = (int)($product_data['p_qty'] ?? 0);
$p_featured_photo = $product_data['p_featured_photo'];
$p_description = $product_data['p_description'] ?? '';
$p_short_description = $product_data['p_short_description'] ?? '';
$p_feature = $product_data['p_feature'] ?? '';
$p_condition = $product_data['p_condition'] ?? '';
$p_return_policy = $product_data['p_return_policy'] ?? '';
$p_total_view = (int)($product_data['p_total_view'] ?? 0);
$p_is_featured = (int)($product_data['p_is_featured'] ?? 0);
$ecat_id = (int)($product_data['ecat_id'] ?? 0);
$p_video_link = $product_data['p_video_link'] ?? '';
$is_top_sale = (int)($product_data['is_top_sale'] ?? 0);
$is_official = (int)($product_data['is_official'] ?? 0);

// Update view count
$statement = $pdo->prepare("UPDATE tbl_product SET p_total_view = p_total_view + 1 WHERE p_id = ?");
$statement->execute([$p_id]);

// Handle Add to Cart & Buy Now Form Submissions
if (isset($_POST['form_add_to_cart']) || isset($_POST['form_buy_now'])) {
    $valid = 1;
    if (!$csrf->checkToken()) {
        http_response_code(403);
        exit('Your session expired. Refresh the product page and try again.');
    }
    
    $p_qty_added = (int)($_POST['p_qty'] ?? 1);
    $size_id_added = (int)($_POST['size_id'] ?? 0);
    $size_name_added = trim($_POST['size_name'] ?? '');
    $color_id_added = (int)($_POST['color_id'] ?? 0);
    $color_name_added = trim($_POST['color_name'] ?? '');

    if ($p_qty_added <= 0 || ($p_qty > 0 && $p_qty_added > $p_qty)) {
        $valid = 0;
        $error_message = 'Invalid quantity or quantity exceeds stock available.';
    }

    if ($valid == 1) {
        $item_found = false;
        $existing_index = -1;

        if (isset($_SESSION['cart_p_id']) && is_array($_SESSION['cart_p_id'])) {
            foreach ($_SESSION['cart_p_id'] as $key => $value) {
                if ($value == $p_id &&
                    (($_SESSION['cart_size_id'][$key] ?? 0) == $size_id_added) &&
                    (($_SESSION['cart_color_id'][$key] ?? 0) == $color_id_added)) {
                    $existing_index = $key;
                    $item_found = true;
                    break;
                }
            }
        }

        if ($item_found) {
            if (($_SESSION['cart_p_qty'][$existing_index] + $p_qty_added) > $p_qty && $p_qty > 0) {
                $error_message = 'Adding this quantity would exceed available stock.';
            } else {
                $_SESSION['cart_p_qty'][$existing_index] += $p_qty_added;
                if (isset($_SESSION['customer']['cust_id'])) {
                    updateCartItemQuantity($pdo, $_SESSION['customer']['cust_id'], $p_id, $size_id_added, $color_id_added, $_SESSION['cart_p_qty'][$existing_index]);
                }
                $success_message = 'Cart updated successfully!';
            }
        } else {
            $next_index = 1;
            if (isset($_SESSION['cart_p_id']) && is_array($_SESSION['cart_p_id']) && !empty($_SESSION['cart_p_id'])) {
                $next_index = max(array_keys($_SESSION['cart_p_id'])) + 1;
            }

            $_SESSION['cart_p_id'][$next_index] = $p_id;
            $_SESSION['cart_size_id'][$next_index] = $size_id_added;
            $_SESSION['cart_size_name'][$next_index] = $size_name_added;
            $_SESSION['cart_color_id'][$next_index] = $color_id_added;
            $_SESSION['cart_color_name'][$next_index] = $color_name_added;
            $_SESSION['cart_p_qty'][$next_index] = $p_qty_added;
            $_SESSION['cart_p_current_price'][$next_index] = $p_current_price;
            $_SESSION['cart_p_name'][$next_index] = $p_name;
            $_SESSION['cart_p_featured_photo'][$next_index] = $p_featured_photo;

            if (isset($_SESSION['customer']['cust_id'])) {
                addOrUpdateCartItem($pdo, $_SESSION['customer']['cust_id'], $p_id, $size_id_added, $size_name_added, $color_id_added, $color_name_added, $p_qty_added, $p_current_price, $p_name, $p_featured_photo);
            }
            $success_message = 'Product added to cart successfully!';
        }

        if (isset($_POST['form_buy_now']) && empty($error_message)) {
            header('location: ' . BASE_URL . 'cart.php');
            exit;
        }
    }
}

// Check Wishlist status
$is_product_in_wishlist = false;
if (isset($_SESSION['customer']['cust_id'])) {
    $cust_id = $_SESSION['customer']['cust_id'];
    $stmt_wl = $pdo->prepare("SELECT wishlist_id FROM tbl_wishlist WHERE cust_id=? AND product_id=?");
    $stmt_wl->execute([$cust_id, $p_id]);
    if ($stmt_wl->rowCount() > 0) {
        $is_product_in_wishlist = true;
    }
}

// Parse Product Media (Support Images, YouTube links, and Cloudflare R2 / direct MP4 videos)
if (!function_exists('parse_product_media')) {
    function parse_product_media($url, $fallback_poster = '') {
        $url = trim((string)$url);
        if (empty($url)) {
            return null;
        }

        // 1. YouTube Video Link
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $matches)) {
            $youtube_id = $matches[1];
            return [
                'type' => 'youtube',
                'video_id' => $youtube_id,
                'src' => "https://www.youtube.com/embed/{$youtube_id}?autoplay=1&rel=0",
                'thumb' => "https://img.youtube.com/vi/{$youtube_id}/hqdefault.jpg",
                'raw' => $url
            ];
        }

        // 2. Direct Video / Cloudflare R2 Object Storage / MP4 / WebM / MOV / M4V
        $lower = strtolower($url);
        $isVideoExt = preg_match('/\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i', $lower);
        $isR2OrStorage = (str_contains($lower, 'r2.dev') || str_contains($lower, 'r2.cloudflarestorage.com') || str_contains($lower, '/video/'));

        if ($isVideoExt || ($isR2OrStorage && !preg_match('/\.(jpg|jpeg|png|webp|gif|svg)(\?.*)?$/i', $lower))) {
            $video_src = str_starts_with($url, 'http') ? $url : (function_exists('get_media_url') ? get_media_url($url) : $url);
            $poster_thumb = !empty($fallback_poster) ? (function_exists('get_media_url') ? get_media_url($fallback_poster) : $fallback_poster) : $video_src;
            return [
                'type' => 'video',
                'src' => $video_src,
                'thumb' => $poster_thumb,
                'raw' => $url
            ];
        }

        // 3. Regular Image
        $imgUrl = str_starts_with($url, 'http') ? $url : (function_exists('get_media_url') ? get_media_url($url) : $url);
        return [
            'type' => 'image',
            'src' => $imgUrl,
            'thumb' => $imgUrl,
            'raw' => $url
        ];
    }
}

// Fetch Gallery Media (Photos & Videos)
$stmt_photos = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id = ? ORDER BY pp_id ASC");
$stmt_photos->execute([$p_id]);
$photo_rows = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

$raw_media_list = [];

if (!empty($p_featured_photo)) {
    $raw_media_list[] = $p_featured_photo;
}

if (!empty($p_video_link)) {
    $video_urls = preg_split('/[\r\n,\s]+/', $p_video_link);
    foreach ($video_urls as $v_url) {
        $v_url = trim($v_url);
        if (!empty($v_url) && !in_array($v_url, $raw_media_list)) {
            $raw_media_list[] = $v_url;
        }
    }
}

foreach ($photo_rows as $pr) {
    if (!empty($pr['photo']) && !in_array($pr['photo'], $raw_media_list)) {
        $raw_media_list[] = $pr['photo'];
    }
}

$gallery_items = [];
$fallback_poster = !empty($p_featured_photo) ? $p_featured_photo : 'assets/images/no-image.png';

foreach ($raw_media_list as $media_raw) {
    $parsed = parse_product_media($media_raw, $fallback_poster);
    if ($parsed) {
        $gallery_items[] = $parsed;
    }
}

if (empty($gallery_items)) {
    $gallery_items[] = [
        'type' => 'image',
        'src' => (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png',
        'thumb' => (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png',
        'raw' => 'assets/images/no-image.png'
    ];
}

$gallery_photos = [];
foreach ($gallery_items as $gi) {
    $gallery_photos[] = $gi['thumb'];
}

// Fetch Product Sizes & Colors
$stmt_size = $pdo->prepare("SELECT s.size_id, s.size_name FROM tbl_product_size ps JOIN tbl_size s ON ps.size_id = s.size_id WHERE ps.p_id = ?");
$stmt_size->execute([$p_id]);
$product_sizes = $stmt_size->fetchAll(PDO::FETCH_ASSOC);

$stmt_color = $pdo->prepare("SELECT c.color_id, c.color_name FROM tbl_product_color pc JOIN tbl_color c ON pc.color_id = c.color_id WHERE pc.p_id = ?");
$stmt_color->execute([$p_id]);
$product_colors = $stmt_color->fetchAll(PDO::FETCH_ASSOC);

// Fetch Settings
$stmt_settings = $pdo->prepare("SELECT * FROM tbl_settings WHERE id = 1");
$stmt_settings->execute();
$settings_data = $stmt_settings->fetch(PDO::FETCH_ASSOC);

$review_feature_on_off = $settings_data['review_feature_on_off'] ?? 1;
$estimated_delivery_time_local = $settings_data['estimated_delivery_time_local'] ?? '2-3 business days';
$estimated_delivery_time_international = $settings_data['estimated_delivery_time_international'] ?? '7-14 business days';
$gemini_api_key = $settings_data['gemini_api_key'] ?? '';
$product_voucher_code = !empty($settings_data['product_voucher_code']) ? $settings_data['product_voucher_code'] : 'WELCOME10';
$product_voucher_discount = !empty($settings_data['product_voucher_discount']) ? (float)$settings_data['product_voucher_discount'] : 10.00;
$related_products_on_off = isset($settings_data['related_products_on_off']) ? (int)$settings_data['related_products_on_off'] : 1;
$mobile_footer_on_off = isset($settings_data['mobile_footer_on_off']) ? (int)$settings_data['mobile_footer_on_off'] : 0;

// Fetch Ratings & Reviews
$avg_rating = 0;
$total_reviews_count = 0;
$reviews_list = [];

if ($review_feature_on_off == 1) {
    try {
        $stmt_rating = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_count FROM tbl_rating WHERE p_id = ?");
        $stmt_rating->execute([$p_id]);
        $rating_res = $stmt_rating->fetch(PDO::FETCH_ASSOC);
        if ($rating_res && $rating_res['total_count'] > 0) {
            $avg_rating = round((float)$rating_res['avg_rating'], 1);
            $total_reviews_count = (int)$rating_res['total_count'];
        }
    } catch (Throwable $e) {}

    if ($total_reviews_count == 0) {
        try {
            $stmt_rev2 = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_count FROM tbl_review WHERE product_id = ? AND status = 'Approved'");
            $stmt_rev2->execute([$p_id]);
            $res2 = $stmt_rev2->fetch(PDO::FETCH_ASSOC);
            if ($res2 && $res2['total_count'] > 0) {
                $avg_rating = round((float)$res2['avg_rating'], 1);
                $total_reviews_count = (int)$res2['total_count'];
            }
        } catch (Throwable $e) {}
    }

    try {
        $stmt_reviews = $pdo->prepare("SELECT r.*, c.cust_name FROM tbl_review r LEFT JOIN tbl_customer c ON r.cust_id = c.cust_id WHERE r.product_id = ? AND r.status = 'Approved' ORDER BY r.created_at DESC LIMIT 10");
        $stmt_reviews->execute([$p_id]);
        $reviews_list = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// Fetch Category Breadcrumbs
$tcat_name = 'Home';
$mcat_name = '';
$ecat_name = '';

if ($ecat_id > 0) {
    $stmt_cat = $pdo->prepare("SELECT e.ecat_name, m.mcat_name, t.tcat_name 
                               FROM tbl_end_category e 
                               LEFT JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id 
                               LEFT JOIN tbl_top_category t ON m.tcat_id = t.tcat_id 
                               WHERE e.ecat_id = ?");
    $stmt_cat->execute([$ecat_id]);
    $cat_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    if ($cat_row) {
        if (!empty($cat_row['tcat_name'])) $tcat_name = $cat_row['tcat_name'];
        if (!empty($cat_row['mcat_name'])) $mcat_name = $cat_row['mcat_name'];
        if (!empty($cat_row['ecat_name'])) $ecat_name = $cat_row['ecat_name'];
    }
}

// Parse Specification Highlights for Cards
$spec_cards = [];
if (!empty($p_feature)) {
    $lines = explode("\n", $p_feature);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        $parts = explode(":", $line, 2);
        if (count($parts) == 2) {
            $title = trim($parts[0]);
            $sub = trim($parts[1]);
            
            $icon = 'fas fa-microchip';
            $lower = strtolower($title . ' ' . $sub);
            if (str_contains($lower, 'core') || str_contains($lower, 'intel') || str_contains($lower, 'ryzen') || str_contains($lower, 'cpu') || str_contains($lower, 'processor')) {
                $icon = 'fas fa-microchip';
            } elseif (str_contains($lower, 'ram') || str_contains($lower, 'ddr') || str_contains($lower, 'memory')) {
                $icon = 'fas fa-memory';
            } elseif (str_contains($lower, 'ssd') || str_contains($lower, 'nvme') || str_contains($lower, 'storage') || str_contains($lower, 'hdd') || str_contains($lower, 'gb') && str_contains($lower, 'ssd')) {
                $icon = 'fas fa-hdd';
            } elseif (str_contains($lower, 'display') || str_contains($lower, 'fhd') || str_contains($lower, 'screen') || str_contains($lower, 'ips') || str_contains($lower, 'inch') || str_contains($lower, '"')) {
                $icon = 'fas fa-desktop';
            } elseif (str_contains($lower, 'graphics') || str_contains($lower, 'gpu') || str_contains($lower, 'iris') || str_contains($lower, 'rtx') || str_contains($lower, 'geforce')) {
                $icon = 'fas fa-gamepad';
            } elseif (str_contains($lower, 'windows') || str_contains($lower, 'os') || str_contains($lower, 'mac') || str_contains($lower, 'system')) {
                $icon = 'fab fa-windows';
            } elseif (str_contains($lower, 'kg') || str_contains($lower, 'weight') || str_contains($lower, 'gram')) {
                $icon = 'fas fa-weight-hanging';
            } elseif (str_contains($lower, 'battery') || str_contains($lower, 'hour') || str_contains($lower, 'mah')) {
                $icon = 'fas fa-battery-three-quarters';
            }
            
            $spec_cards[] = [
                'icon' => $icon,
                'title' => $title,
                'sub' => $sub
            ];
        }
    }
}

// Brand name determination
$brand_name = '';
if (preg_match('/^(Samsung|Apple|HP|Dell|Lenovo|Asus|Sony|Xiaomi|Google|OnePlus|Huawei|Realme|Oppo|Vivo|Rolex|Casio|Amazfit|Garmin)/i', $p_name, $bm)) {
    $brand_name = ucfirst($bm[1]);
} elseif (!empty($mcat_name) && strtolower($mcat_name) !== 'default' && strtolower($mcat_name) !== 'uncategorized') {
    $brand_name = $mcat_name;
} elseif (!empty($tcat_name) && strtolower($tcat_name) !== 'default' && strtolower($tcat_name) !== 'home') {
    $brand_name = $tcat_name;
} else {
    $brand_name = 'Official Store';
}

// Discount & EMI calculations
$discount_pct = 0;
if ($p_old_price > $p_current_price && $p_current_price > 0) {
    $discount_pct = round((($p_old_price - $p_current_price) / $p_old_price) * 100);
}
$emi_monthly = round($p_current_price / 12);

$hp_logo_url = 'https://oaudxkhxwdrdsybyaheb.supabase.co/storage/v1/object/public/storefront/assets/hp_logo.jpg';
$lifestyle_img_url = 'https://oaudxkhxwdrdsybyaheb.supabase.co/storage/v1/object/public/storefront/assets/hp_lifestyle.jpg';

// Fetch Related Products (same end category first, or active products in store)
$related_products = [];
try {
    if ($ecat_id > 0) {
        $stmt_rel = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo, is_top_sale 
                                   FROM tbl_product 
                                   WHERE ecat_id = ? AND p_id != ? AND p_is_active = 1 
                                   ORDER BY p_id DESC LIMIT 8");
        $stmt_rel->execute([$ecat_id, $p_id]);
        $related_products = $stmt_rel->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (count($related_products) < 4) {
        $needed = 8 - count($related_products);
        $exclude_ids = array_merge([$p_id], !empty($related_products) ? array_column($related_products, 'p_id') : []);
        $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
        
        $stmt_more = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo, is_top_sale 
                                    FROM tbl_product 
                                    WHERE p_id NOT IN ($placeholders) AND p_is_active = 1 
                                    ORDER BY p_id DESC LIMIT " . (int)$needed);
        $stmt_more->execute($exclude_ids);
        $more_prods = $stmt_more->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!empty($more_prods)) {
            $related_products = array_merge($related_products, $more_prods);
        }
    }
} catch (Throwable $e) {
    error_log('Related products error: ' . $e->getMessage());
}

// Require site header
$cur_page = 'product.php';
$firstPhotoUrl = !empty($gallery_items[0]['thumb']) ? $gallery_items[0]['thumb'] : (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png';
require_once('header.php');
?>

<!-- Include Modern Product Stylesheet -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/product_modern.css">

<div class="sn-product-details-wrap">
    <!-- Mobile Sub-Header Navigation Bar (Mockup Pixel-Perfect) -->
    <div class="sn-mobile-sub-header">
        <a href="javascript:history.length > 1 ? history.back() : window.location.href='<?php echo BASE_URL; ?>';" class="sn-sub-back-btn" aria-label="Go Back">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#111827" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
        <form action="<?php echo BASE_URL; ?>search-result.php" method="get" class="sn-sub-search-form" id="snSubSearchForm">
            <div class="sn-sub-search-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" name="search_text" placeholder="Search for products, brands and more..." autocomplete="off">
            </div>
        </form>

        <!-- On-Scroll Horizontal Specification Tabs -->
        <div class="sn-top-nav-tabs" id="snTopNavTabs">
            <button type="button" class="sn-nav-tab-pill active" data-target="tab-desc">Overview</button>
            <button type="button" class="sn-nav-tab-pill" data-target="tab-specs">Specifications</button>
            <button type="button" class="sn-nav-tab-pill" data-target="tab-reviews">Reviews</button>
            <button type="button" class="sn-nav-tab-pill" data-target="tab-qa">Q&A</button>
        </div>
    </div>

    <!-- Breadcrumbs (Desktop Only) -->
    <nav class="sn-breadcrumbs sn-desktop-only" aria-label="Breadcrumb">
        <a href="<?php echo BASE_URL; ?>">Home</a>
        <span class="sn-crumb-sep">›</span>
        <a href="<?php echo BASE_URL; ?>category.php?cat=<?php echo urlencode($tcat_name); ?>"><?php echo htmlspecialchars($tcat_name); ?></a>
        <span class="sn-crumb-sep">›</span>
        <a href="<?php echo BASE_URL; ?>category.php?cat=<?php echo urlencode($mcat_name); ?>"><?php echo htmlspecialchars($mcat_name); ?></a>
        <span class="sn-crumb-sep">›</span>
        <span class="sn-crumb-current"><?php echo htmlspecialchars($p_name); ?></span>
    </nav>

    <!-- Main Two-Column Layout -->
    <div class="sn-product-main-grid">
        
        <!-- ================= LEFT COLUMN ================= -->
        <div class="sn-product-left-col">
            
            <!-- Gallery Visual Showcase -->
            <div class="sn-gallery-container">
                <!-- Vertical Thumbnails Strip (Desktop Only) -->
                <div class="sn-gallery-vertical-thumbs sn-desktop-only">
                    <button type="button" class="sn-thumb-scroll-btn" id="snThumbUp" aria-label="Scroll Up">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                    <div class="sn-thumb-list" id="snThumbList">
                        <?php foreach ($gallery_items as $idx => $item): ?>
                            <div class="sn-thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" 
                                 data-index="<?php echo $idx; ?>" 
                                 data-type="<?php echo $item['type']; ?>" 
                                 data-src="<?php echo htmlspecialchars($item['src']); ?>"
                                 data-thumb="<?php echo htmlspecialchars($item['thumb']); ?>">
                                <img src="<?php echo htmlspecialchars($item['thumb']); ?>" alt="<?php echo htmlspecialchars($p_name); ?> Thumbnail <?php echo $idx+1; ?>" loading="lazy" onerror="this.onerror=null; this.src='<?php echo (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png'; ?>';">
                                <?php if ($item['type'] === 'youtube' || $item['type'] === 'video'): ?>
                                    <div class="sn-thumb-video-overlay">
                                        <i class="fas fa-play sn-thumb-play-icon"></i>
                                    </div>
                                    <span class="sn-thumb-video-badge"><?php echo $item['type'] === 'youtube' ? 'YT' : 'VIDEO'; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="sn-thumb-scroll-btn" id="snThumbDown" aria-label="Scroll Down">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <!-- Main Card Viewport -->
                <div class="sn-gallery-main-card">
                    <span class="sn-bestseller-badge sn-desktop-only">Best Seller</span>
                    <span class="sn-mob-discount-badge">-<?php echo $discount_pct; ?>%</span>
                    <span class="sn-mob-counter-badge" id="snMobCounter">1/<?php echo count($gallery_items); ?></span>

                    <!-- Mobile Left/Right Slider Chevrons -->
                    <button type="button" class="sn-mob-nav-btn sn-mob-prev" id="snMobPrev" aria-label="Previous Photo">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="sn-mob-nav-btn sn-mob-next" id="snMobNext" aria-label="Next Photo">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <button type="button" class="sn-gallery-zoom-btn sn-desktop-only" id="snZoomBtn" title="View Fullscreen">
                        <i class="fas fa-expand-alt"></i>
                    </button>
                    
                    <div class="sn-gallery-viewport">
                        <?php $firstItem = $gallery_items[0] ?? ['type' => 'image', 'src' => (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png', 'thumb' => (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png']; ?>
                        <img src="<?php echo htmlspecialchars($firstItem['type'] === 'image' ? $firstItem['src'] : $firstItem['thumb']); ?>" 
                             alt="<?php echo htmlspecialchars($p_name); ?>" 
                             class="sn-gallery-main-img" 
                             id="snMainImg"
                             style="<?php echo $firstItem['type'] !== 'image' ? 'display:none;' : ''; ?>"
                             onerror="this.onerror=null; this.src='<?php echo (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png'; ?>';">
                        
                        <iframe id="snMainIframe" class="sn-gallery-main-frame" 
                                src="<?php echo $firstItem['type'] === 'youtube' ? htmlspecialchars($firstItem['src']) : ''; ?>" 
                                style="<?php echo $firstItem['type'] === 'youtube' ? '' : 'display:none;'; ?>" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen></iframe>

                        <video id="snMainVideo" class="sn-gallery-main-video" 
                               src="<?php echo $firstItem['type'] === 'video' ? htmlspecialchars($firstItem['src']) : ''; ?>" 
                               controls playsinline 
                               style="<?php echo $firstItem['type'] === 'video' ? '' : 'display:none;'; ?>"></video>
                    </div>

                    <!-- Dots indicator (Desktop Only) -->
                    <div class="sn-gallery-dots sn-desktop-only">
                        <?php foreach ($gallery_items as $idx => $item): ?>
                            <span class="sn-gallery-dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>"></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Horizontal Thumbnails Strip (Mobile Only) -->
            <div class="sn-mob-thumbs-strip" id="snMobThumbList">
                <?php foreach ($gallery_items as $idx => $item): ?>
                    <div class="sn-mob-thumb <?php echo $idx === 0 ? 'active' : ''; ?>" 
                         data-index="<?php echo $idx; ?>" 
                         data-type="<?php echo $item['type']; ?>" 
                         data-src="<?php echo htmlspecialchars($item['src']); ?>"
                         data-thumb="<?php echo htmlspecialchars($item['thumb']); ?>">
                        <img src="<?php echo htmlspecialchars($item['thumb']); ?>" alt="<?php echo htmlspecialchars($p_name); ?> Thumbnail <?php echo $idx+1; ?>" onerror="this.onerror=null; this.src='<?php echo (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png'; ?>';">
                        <?php if ($item['type'] === 'youtube' || $item['type'] === 'video'): ?>
                            <div class="sn-thumb-video-overlay">
                                <i class="fas fa-play sn-thumb-play-icon"></i>
                            </div>
                            <span class="sn-thumb-video-badge"><?php echo $item['type'] === 'youtube' ? 'YT' : 'VIDEO'; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Specification Highlights Grid (8 Cards) -->
            <div class="sn-specs-highlights-grid">
                <?php foreach ($spec_cards as $sc): ?>
                    <div class="sn-spec-highlight-card">
                        <div class="sn-spec-highlight-icon"><i class="<?php echo htmlspecialchars($sc['icon']); ?>"></i></div>
                        <div class="sn-spec-highlight-title"><?php echo htmlspecialchars($sc['title']); ?></div>
                        <div class="sn-spec-highlight-sub"><?php echo htmlspecialchars($sc['sub']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Tabs Navigation -->
            <div class="sn-tabs-container">
                <!-- Mobile Details Heading with Yellow File Icon -->
                <div class="sn-mob-details-head">
                    <svg class="sn-mob-details-icon" width="20" height="20" viewBox="0 0 24 24" fill="#fab802" stroke="#fab802">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8" fill="#fff" stroke="#fab802"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13" stroke="#fff" stroke-width="2"></line>
                        <line x1="16" y1="17" x2="8" y2="17" stroke="#fff" stroke-width="2"></line>
                    </svg>
                    <h3 class="sn-mob-details-title">Product Details</h3>
                </div>

                <div class="sn-tabs-header">
                    <button type="button" class="sn-tab-btn active" data-target="tab-desc">Overview</button>
                    <button type="button" class="sn-tab-btn" data-target="tab-specs">Specifications</button>
                    <button type="button" class="sn-tab-btn" data-target="tab-reviews">Reviews</button>
                    <button type="button" class="sn-tab-btn" data-target="tab-qa">Q&A</button>
                </div>

                <!-- Tab 1: Description / Overview -->
                <div class="sn-tab-pane active" id="tab-desc">
                    <h3 class="sn-desc-heading sn-desktop-only">Powerful Performance for Everyday Tasks</h3>
                    <div class="sn-desc-wrapper" id="snDescWrapper">
                        <div class="sn-desc-text">
                            <?php if (!empty($p_description)): ?>
                                <?php echo $p_description; ?>
                            <?php else: ?>
                                <p><?php echo htmlspecialchars($p_name); ?> combines style, health, and productivity in one smart device. With a vibrant AMOLED display, advanced health tracking features, and long battery life, it's the perfect companion for your everyday life.</p>
                            <?php endif; ?>
                        </div>

                        <ul class="sn-feature-bullet-list sn-mob-bullet-list">
                            <li class="sn-feature-bullet-item sn-mob-bullet-item">
                                <span class="sn-bullet-check-icon sn-mob-check-circle"><i class="fas fa-check"></i></span>
                                <span>Advanced health & fitness tracking</span>
                            </li>
                            <li class="sn-feature-bullet-item sn-mob-bullet-item">
                                <span class="sn-bullet-check-icon sn-mob-check-circle"><i class="fas fa-check"></i></span>
                                <span>Water resistant (5ATM)</span>
                            </li>
                            <li class="sn-feature-bullet-item sn-mob-bullet-item">
                                <span class="sn-bullet-check-icon sn-mob-check-circle"><i class="fas fa-check"></i></span>
                                <span>Works with Android & iOS</span>
                            </li>
                        </ul>
                        <div class="sn-desc-fade-overlay" id="snDescFadeOverlay"></div>
                    </div>
                    <button type="button" class="sn-btn-see-more" id="btnDescSeeMore" aria-label="Toggle full description">
                        <span class="sn-see-more-text">See More</span>
                        <i class="fas fa-chevron-down sn-see-more-icon"></i>
                    </button>
                </div>

                <!-- Tab 2: Specifications -->
                <div class="sn-tab-pane sn-accordion-pane collapsed" id="tab-specs">
                    <div class="sn-accordion-header" data-toggle="tab-specs">
                        <div class="sn-accordion-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="4" y1="21" x2="4" y2="14"></line>
                                <line x1="4" y1="10" x2="4" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12" y2="3"></line>
                                <line x1="20" y1="21" x2="20" y2="16"></line>
                                <line x1="20" y1="12" x2="20" y2="3"></line>
                                <line x1="1" y1="14" x2="7" y2="14"></line>
                                <line x1="9" y1="8" x2="15" y2="8"></line>
                                <line x1="17" y1="16" x2="23" y2="16"></line>
                            </svg>
                            <span>Specifications</span>
                        </div>
                        <i class="fas fa-chevron-down sn-accordion-arrow"></i>
                    </div>
                    <div class="sn-accordion-content">
                        <table class="sn-specs-table">
                            <tbody>
                                <?php foreach ($spec_cards as $sc): ?>
                                    <tr>
                                        <td class="sn-spec-label"><?php echo htmlspecialchars($sc['title']); ?></td>
                                        <td class="sn-spec-val"><?php echo htmlspecialchars($sc['sub']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td class="sn-spec-label">Warranty</td>
                                    <td class="sn-spec-val">1 Year Official Manufacturer Warranty</td>
                                </tr>
                                <tr>
                                    <td class="sn-spec-label">Condition</td>
                                    <td class="sn-spec-val"><?php echo htmlspecialchars($p_condition ?: 'Brand New (Sealed)'); ?></td>
                                </tr>
                                <tr>
                                    <td class="sn-spec-label">Stock Availability</td>
                                    <td class="sn-spec-val"><?php echo $p_qty > 0 ? $p_qty . ' units in stock' : 'Out of stock'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 3: Reviews -->
                <div class="sn-tab-pane sn-accordion-pane collapsed" id="tab-reviews">
                    <div class="sn-accordion-header" data-toggle="tab-reviews">
                        <div class="sn-accordion-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            <span>Customer Reviews (<?php echo $total_reviews_count > 0 ? $total_reviews_count : count($reviews_list); ?>)</span>
                        </div>
                        <i class="fas fa-chevron-down sn-accordion-arrow"></i>
                    </div>
                    <div class="sn-accordion-content">
                        <div style="margin-bottom: 20px;">
                            <h4 style="font-size: 16px; font-weight: 700; margin-bottom: 4px;">Customer Reviews & Ratings</h4>
                            <p style="font-size: 13px; color: #64748b;">Showing authentic reviews from verified buyers.</p>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <?php if (!empty($reviews_list)): ?>
                                <?php foreach ($reviews_list as $rev): ?>
                                    <div class="sn-review-card-item">
                                        <div class="sn-reviewer-row">
                                            <div class="sn-reviewer-meta">
                                                <div class="sn-reviewer-avatar">
                                                    <?php echo strtoupper(substr($rev['cust_name'] ?: 'Customer', 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="sn-reviewer-name">
                                                        <?php echo htmlspecialchars($rev['cust_name'] ?: 'Verified Customer'); ?>
                                                        <i class="fas fa-check-circle sn-verified-check"></i>
                                                    </div>
                                                    <div class="sn-review-date">Verified Purchase • <?php echo date('M d, Y', strtotime($rev['created_at'])); ?></div>
                                                </div>
                                            </div>
                                            <div class="sn-stars-wrap">
                                                <?php for($i=1; $i<=5; $i++): ?>
                                                    <i class="<?php echo $i <= (int)$rev['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="sn-review-body">
                                            <?php echo nl2br(htmlspecialchars($rev['review_text'])); ?>
                                        </div>
                                        <div class="sn-review-footer">
                                            <button type="button" class="sn-btn-helpful">
                                                <i class="far fa-thumbs-up"></i> Helpful (24)
                                            </button>
                                            <img src="<?php echo htmlspecialchars($firstPhotoUrl); ?>" alt="Product" class="sn-review-thumb-item">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 32px 16px; color: #64748b; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1;">
                                    <i class="far fa-comment-dots" style="font-size: 28px; margin-bottom: 8px; color: #94a3b8; display: block;"></i>
                                    <div style="font-weight: 600; color: #334155; margin-bottom: 4px;">No reviews yet</div>
                                    <div style="font-size: 13px;">Be the first to review this product after purchase!</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Q&A and Shipping & Return -->
                <div class="sn-tab-pane sn-accordion-pane collapsed" id="tab-qa">
                    <div class="sn-accordion-header" data-toggle="tab-qa">
                        <div class="sn-accordion-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                            <span>Questions & Answers</span>
                        </div>
                        <i class="fas fa-chevron-down sn-accordion-arrow"></i>
                    </div>
                    <div class="sn-accordion-content">
                        <div style="margin-bottom: 20px;">
                            <h4 style="font-size: 16px; font-weight: 700; margin-bottom: 4px; color: #0f172a;">Customer Questions & Answers</h4>
                            <p style="font-size: 13px; color: #64748b;">Common inquiries about authenticity, delivery, and warranty.</p>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px;">
                                <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                    <span style="color: #fab802; margin-right: 6px;">Q:</span> Is this product 100% original & authentic?
                                </div>
                                <div style="font-size: 12.5px; color: #475569; line-height: 1.5;">
                                    <span style="font-weight: 700; color: #10b981; margin-right: 6px;">A:</span> Yes, all items sold on ShopNext are 100% brand new, authentic, and backed by official manufacturer warranty.
                                </div>
                            </div>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px;">
                                <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                    <span style="color: #fab802; margin-right: 6px;">Q:</span> What is the estimated delivery time?
                                </div>
                                <div style="font-size: 12.5px; color: #475569; line-height: 1.5;">
                                    <span style="font-weight: 700; color: #10b981; margin-right: 6px;">A:</span> Standard local delivery takes <?php echo htmlspecialchars($estimated_delivery_time_local); ?>. Express tracking is sent via SMS upon confirmation.
                                </div>
                            </div>
                        </div>

                        <h4 style="font-size: 16px; font-weight: 700; margin-bottom: 8px; color: #0f172a;">Shipping & Return Guarantee</h4>
                        <p class="sn-desc-text">We provide express, tracked door-to-door delivery throughout Bangladesh and internationally. Every device is packaged in shock-proof reinforced packaging with tamper-evident seals.</p>
                        <ul class="sn-feature-bullet-list sn-mob-bullet-list">
                            <li class="sn-feature-bullet-item sn-mob-bullet-item">
                                <span class="sn-bullet-check-icon sn-mob-check-circle"><i class="fas fa-check"></i></span>
                                <span><strong>Local Delivery:</strong> <?php echo htmlspecialchars($estimated_delivery_time_local); ?></span>
                            </li>
                            <li class="sn-feature-bullet-item sn-mob-bullet-item">
                                <span class="sn-bullet-check-icon sn-mob-check-circle"><i class="fas fa-check"></i></span>
                                <span><strong>International Delivery:</strong> <?php echo htmlspecialchars($estimated_delivery_time_international); ?></span>
                            </li>
                            <li class="sn-feature-bullet-item sn-mob-bullet-item">
                                <span class="sn-bullet-check-icon sn-mob-check-circle"><i class="fas fa-check"></i></span>
                                <span><strong>7 Days Return Policy:</strong> Return easily within 7 days of receipt if unopened or defective.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <!-- ================= RIGHT COLUMN ================= -->
        <div class="sn-product-right-col">
            
            <!-- Brand Badge & Header Action Buttons (Share, Wishlist, Ask AI) -->
            <div class="sn-brand-header-row">
                <div class="sn-brand-badge-box">
                    <span class="sn-brand-icon-circle">
                        <i class="fas fa-certificate"></i>
                    </span>
                    <span class="sn-brand-name"><?php echo htmlspecialchars(!empty($brand_name) ? $brand_name : ($tcat_name ?? 'Official Store')); ?></span>
                </div>

                <div class="sn-header-action-btns">
                    <!-- Share Button -->
                    <button type="button" class="sn-action-icon-btn" id="snShareBtn" title="Share Product" aria-label="Share">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                    </button>

                    <!-- Wishlist Button -->
                    <button type="button" class="sn-action-icon-btn <?php echo $is_product_in_wishlist ? 'active' : ''; ?>" id="btnWishlistToggle" data-product-id="<?php echo htmlspecialchars($p_id); ?>" title="Save to Wishlist" aria-label="Wishlist">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $is_product_in_wishlist ? '#ef4444' : 'none'; ?>" stroke="<?php echo $is_product_in_wishlist ? '#ef4444' : 'currentColor'; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </button>

                    <!-- Ask AI Gemini Button -->
                    <button type="button" class="sn-btn-little-ai" id="openAiAssistantBtn" title="Ask AI about this product">
                        <span class="sn-ai-sparkle">✨</span>
                        <span>Ask AI</span>
                        <span class="sn-ai-badge">Gemini</span>
                    </button>
                </div>
            </div>

            <!-- Product Main Title -->
            <h1 class="sn-product-title-main"><?php echo htmlspecialchars($p_name); ?></h1>

            <?php if (!empty($p_short_description)): ?>
                <p class="sn-prod-subtitle"><?php echo htmlspecialchars(strip_tags($p_short_description)); ?></p>
            <?php endif; ?>

            <!-- Rating, Sold Status & Stock Availability Row -->
            <div class="sn-rating-meta-row">
                <div class="sn-rating-box">
                    <div class="sn-stars-gold">
                        <?php 
                        $rating_val = $avg_rating > 0 ? $avg_rating : 5.0;
                        for($i=1; $i<=5; $i++): 
                        ?>
                            <i class="<?php echo $i <= round($rating_val) ? 'fas' : 'far'; ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="sn-rating-score-num"><?php echo number_format($rating_val, 1); ?></span>
                    <span class="sn-review-count-lbl">(<?php echo $total_reviews_count > 0 ? number_format($total_reviews_count) . ' reviews' : 'New Arrival'; ?>)</span>
                </div>

                <span class="sn-dot-sep">•</span>

                <!-- Sold Count -->
                <span class="sn-sold-badge">
                    <i class="fas fa-fire-alt" style="color:#f59e0b;margin-right:4px;"></i>
                    <?php 
                    $sold_count = max((int)($p_total_view / 3), 24);
                    echo number_format($sold_count) . '+ sold';
                    ?>
                </span>

                <span class="sn-dot-sep">•</span>

                <!-- Stock Badge -->
                <?php if ($p_qty > 0): ?>
                    <span class="sn-stock-badge-tag in-stock">
                        <i class="fas fa-check-circle" style="color:#10b981;margin-right:4px;"></i> In Stock (<?php echo $p_qty; ?> units)
                    </span>
                <?php else: ?>
                    <span class="sn-stock-badge-tag out-stock">
                        <i class="fas fa-times-circle" style="color:#ef4444;margin-right:4px;"></i> Out of Stock
                    </span>
                <?php endif; ?>
            </div>

            <!-- Price Main Block -->
            <div class="sn-price-main-block">
                <span class="sn-price-curr">৳ <?php echo number_format($p_current_price); ?></span>
                <?php if ($p_old_price > 0 && $p_old_price > $p_current_price): ?>
                    <span class="sn-price-old">৳ <?php echo number_format($p_old_price); ?></span>
                    <span class="sn-price-discount-tag">-<?php echo $discount_pct; ?>% OFF</span>
                <?php elseif ($discount_pct > 0): ?>
                    <span class="sn-price-discount-tag">-<?php echo $discount_pct; ?>% OFF</span>
                <?php endif; ?>
            </div>

            <!-- Trust / Guarantees Bar (3 Columns matching mockup) -->
            <div class="sn-guarantees-bar">
                <div class="sn-guarantee-item">
                    <div class="sn-guarantee-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="sn-guarantee-title">Free Shipping</div>
                        <div class="sn-guarantee-desc">On orders over ৳ 2,000</div>
                    </div>
                </div>
                <div class="sn-guarantee-item">
                    <div class="sn-guarantee-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <polyline points="9 12 11 14 15 10"/>
                        </svg>
                    </div>
                    <div>
                        <div class="sn-guarantee-title">Secure Payment</div>
                        <div class="sn-guarantee-desc">100% secure payments</div>
                    </div>
                </div>
                <div class="sn-guarantee-item">
                    <div class="sn-guarantee-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="sn-guarantee-title">7 Days Return</div>
                        <div class="sn-guarantee-desc">Easy return policy</div>
                    </div>
                </div>
            </div>

            <!-- Purchase Form (Preserves All Backend Inputs & Token) -->
            <form action="" method="post" id="snAddToCartForm">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="p_name" value="<?php echo htmlspecialchars($p_name); ?>">
                <input type="hidden" name="p_current_price" value="<?php echo htmlspecialchars($p_current_price); ?>">
                <input type="hidden" name="p_featured_photo" value="<?php echo htmlspecialchars($p_featured_photo); ?>">

                <!-- Color Selection (Exact match with reference design) -->
                <div class="sn-option-row">
                    <div class="sn-option-header-mob">
                        <span class="sn-option-label-mob">Color</span>
                        <span class="sn-selected-val-mob" id="selectedColorName">Black <i class="fas fa-chevron-right" style="font-size: 10px;"></i></span>
                    </div>
                    <div class="sn-swatches-wrap">
                        <div class="sn-color-swatch-mob active" data-color-id="1" data-color-name="Black" style="background-color: #0f172a;" title="Black"></div>
                        <div class="sn-color-swatch-mob" data-color-id="2" data-color-name="Silver" style="background-color: #cbd5e1;" title="Silver"></div>
                        <div class="sn-color-swatch-mob" data-color-id="3" data-color-name="Lavender" style="background-color: #a78bfa;" title="Lavender"></div>
                    </div>
                    <input type="hidden" name="color_id" id="hiddenColorId" value="1">
                    <input type="hidden" name="color_name" id="hiddenColorName" value="Black">
                    <input type="hidden" name="size_id" id="hiddenSizeId" value="1">
                    <input type="hidden" name="size_name" id="hiddenSizeName" value="Standard">
                </div>

                <!-- Quantity Row (Exact match with reference design) -->
                <div class="sn-mob-qty-row">
                    <span class="sn-option-label-mob">Quantity</span>
                    <div class="sn-mob-qty-stepper">
                        <button type="button" class="sn-mob-stepper-btn" id="qtyMinus" aria-label="Decrease quantity">−</button>
                        <input type="number" name="p_qty" id="snQtyInput" class="sn-mob-qty-val" value="1" min="1" max="<?php echo max(1, $p_qty); ?>" readonly>
                        <button type="button" class="sn-mob-stepper-btn" id="qtyPlus" aria-label="Increase quantity">+</button>
                    </div>
                </div>

                <!-- Dual Action Buttons: Add to Cart (Yellow) + Buy Now (Cream) -->
                <div class="sn-mob-dual-actions">
                    <button type="submit" name="form_add_to_cart" class="sn-btn-mob-cart" id="btnAddToCart">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span>Add to Cart</span>
                    </button>
                    <button type="submit" name="form_buy_now" class="sn-btn-mob-buy" id="btnBuyNow">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                        </svg>
                        <span>Buy Now</span>
                    </button>
                </div>
            </form>

            <!-- Coupon / Voucher Card -->
            <div class="sn-coupon-card">
                <div class="sn-coupon-left">
                    <div class="sn-coupon-percent-badge">%</div>
                    <div>
                        <div class="sn-coupon-title">Use Code: <span id="couponCodeText"><?php echo htmlspecialchars($product_voucher_code); ?></span></div>
                        <div class="sn-coupon-sub">Get <?php echo (int)$product_voucher_discount; ?>% off on your first purchase!</div>
                    </div>
                </div>
                <button type="button" class="sn-btn-copy-code" id="btnCopyCoupon">
                    Copy Code
                </button>
            </div>

            <!-- Customer Reviews Preview Card -->
            <?php if (!empty($reviews_list)): 
                $first_rev = $reviews_list[0];
            ?>
            <div class="sn-reviews-preview-box">
                <a href="#tab-reviews" class="sn-reviews-header-link" id="linkToReviewsTab">
                    <span>Customer Reviews (<?php echo count($reviews_list); ?>)</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="sn-review-card-item">
                    <div class="sn-reviewer-row">
                        <div class="sn-reviewer-meta">
                            <div class="sn-reviewer-avatar"><?php echo strtoupper(substr($first_rev['cust_name'] ?: 'C', 0, 1)); ?></div>
                            <div>
                                <div class="sn-reviewer-name">
                                    <?php echo htmlspecialchars($first_rev['cust_name'] ?: 'Verified Customer'); ?> <i class="fas fa-check-circle sn-verified-check"></i>
                                </div>
                                <div class="sn-review-date">Verified Purchase • <?php echo date('M d, Y', strtotime($first_rev['created_at'])); ?></div>
                            </div>
                        </div>
                        <div class="sn-stars-wrap">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="<?php echo $i <= (int)$first_rev['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="sn-review-body">
                        <?php echo nl2br(htmlspecialchars($first_rev['review_text'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ================= RELATED PRODUCTS ROW ================= -->
    <?php if ($related_products_on_off == 1 && !empty($related_products)): ?>
    <section class="sn-related-section" id="snRelatedSection">
        <div class="sn-related-header">
            <div class="sn-related-title-wrap">
                <span class="sn-related-icon">⚡</span>
                <h2 class="sn-related-title">Related Products</h2>
            </div>
            <div class="sn-related-arrows sn-desktop-only">
                <button type="button" class="sn-related-arrow" id="snRelPrev" aria-label="Previous Products">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <button type="button" class="sn-related-arrow" id="snRelNext" aria-label="Next Products">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
        </div>

        <div class="sn-related-scroll-track" id="snRelatedTrack">
            <?php foreach ($related_products as $rel): 
                $rCurr = (float)str_replace(',', '', (string)$rel['p_current_price']);
                $rOld = (float)str_replace(',', '', (string)($rel['p_old_price'] ?? '0'));
                $rHasDiscount = ($rOld > $rCurr && $rOld > 0);
                $rDiscountPct = $rHasDiscount ? round((($rOld - $rCurr) / $rOld) * 100) : 0;
                $rPhoto = $rel['p_featured_photo'] ?? '';
                $rPhotoUrl = !empty($rPhoto) ? (str_starts_with($rPhoto, 'http') ? $rPhoto : (function_exists('get_media_url') ? get_media_url($rPhoto) : BASE_URL . 'assets/uploads/' . $rPhoto)) : BASE_URL . 'assets/images/no-image.png';
                $rUrl = function_exists('getProductURL') ? getProductURL($rel['p_id'], $rel['p_name'], BASE_URL) : BASE_URL . 'product.php?id=' . $rel['p_id'];
            ?>
                <div class="sn-rel-card">
                    <div class="sn-rel-card-top">
                        <?php if ($rHasDiscount): ?>
                            <span class="sn-rel-badge">-<?php echo $rDiscountPct; ?>%</span>
                        <?php elseif (!empty($rel['is_top_sale'])): ?>
                            <span class="sn-rel-badge hot">Top Sale</span>
                        <?php else: ?>
                            <span class="sn-rel-badge-spacer"></span>
                        <?php endif; ?>

                        <button type="button" class="sn-rel-wishlist" title="Save to Wishlist" onclick="relToggleWishlist(<?php echo $rel['p_id']; ?>, this, event)">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        </button>
                    </div>

                    <a href="<?php echo htmlspecialchars($rUrl); ?>" class="sn-rel-img-box">
                        <img src="<?php echo htmlspecialchars($rPhotoUrl); ?>" alt="<?php echo htmlspecialchars($rel['p_name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='<?php echo (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png'; ?>';">
                    </a>

                    <div class="sn-rel-info">
                        <a href="<?php echo htmlspecialchars($rUrl); ?>" class="sn-rel-title"><?php echo htmlspecialchars($rel['p_name']); ?></a>
                        <div class="sn-rel-rating-row">
                            <span class="sn-rel-star">★</span>
                            <span class="sn-rel-score">Verified</span>
                        </div>
                    </div>

                    <div class="sn-rel-bottom">
                        <div class="sn-rel-prices">
                            <span class="sn-rel-curr">৳ <?php echo number_format($rCurr); ?></span>
                            <?php if ($rHasDiscount): ?>
                                <span class="sn-rel-old">৳ <?php echo number_format($rOld); ?></span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="sn-rel-add-btn" onclick="relAddToCart(<?php echo $rel['p_id']; ?>, '<?php echo htmlspecialchars(addslashes($rel['p_name'])); ?>', this)" title="Add to cart" aria-label="Add to Cart">
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

<!-- ================= FIXED & STICKY BOTTOM ACTION BAR (MOBILE ONLY) ================= -->
<div class="sn-sticky-bottom-bar" id="snStickyBottomBar">
    <!-- Message Button -->
    <button type="button" class="sn-sticky-btn-msg" id="snStickyMsgBtn" aria-label="Message / AI Assistant">
        <div class="sn-sticky-msg-icon-box">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span class="sn-sticky-msg-badge">1</span>
        </div>
        <span>Message</span>
    </button>

    <!-- Add to Cart Button (Solid Yellow) -->
    <button type="button" class="sn-sticky-btn-cart" id="snStickyCartBtn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
        </svg>
        <span>Add to Cart</span>
    </button>

    <!-- Buy Now Button (Warm Cream) -->
    <button type="button" class="sn-sticky-btn-buy" id="snStickyBuyBtn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
        </svg>
        <span>Buy Now</span>
    </button>
</div>

<!-- ================= AI ASSISTANT MODAL ================= -->
<div class="sn-ai-modal-backdrop" id="snAiModal">
    <div class="sn-ai-modal-card">
        <div class="sn-ai-modal-header">
            <div class="sn-ai-modal-title-wrap">
                <div class="sn-ai-modal-icon-badge">✨</div>
                <div>
                    <div class="sn-ai-modal-title">ShopNext AI Shopping Assistant</div>
                    <div class="sn-ai-modal-subtitle">Instant answers for <?php echo htmlspecialchars($p_name); ?></div>
                </div>
            </div>
            <button type="button" class="sn-ai-modal-close" id="snAiModalClose">&times;</button>
        </div>

        <!-- Quick Query Suggestion Chips -->
        <div class="sn-ai-chips-bar">
            <button type="button" class="sn-ai-prompt-chip" data-prompt="Summarize this laptop's top 3 benefits in brief bullet points.">⚡ 30-Sec Summary</button>
            <button type="button" class="sn-ai-prompt-chip" data-prompt="Is this laptop suitable for programming, multitasking, and college studies?">💻 Good for Study & Work?</button>
            <button type="button" class="sn-ai-prompt-chip" data-prompt="How good is the battery life, weight, and portability?">🔋 Battery & Portability</button>
            <button type="button" class="sn-ai-prompt-chip" data-prompt="What comes inside the package, and what warranty is included?">📦 In The Box</button>
        </div>

        <!-- Messages Area -->
        <div class="sn-ai-messages-area" id="snAiMessages">
            <div class="sn-ai-msg ai">
                <div class="sn-ai-msg-bubble">
                    👋 Hi there! I'm your AI shopping assistant. Ask me anything about the <strong><?php echo htmlspecialchars($p_name); ?></strong> — specifications, everyday performance, gaming, or delivery!
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="sn-ai-input-area">
            <input type="text" class="sn-ai-input" id="snAiInput" placeholder="Ask about specifications, performance, usage...">
            <button type="button" class="sn-ai-send-btn" id="snAiSendBtn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<!-- Fullscreen Zoom Modal -->
<div id="snZoomModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); z-index:999999; align-items:center; justify-content:center; padding:20px; cursor:zoom-out;">
    <img id="snZoomImg" src="" style="max-width:90%; max-height:90%; object-fit:contain; border-radius:12px; box-shadow:0 20px 50px rgba(0,0,0,0.5);">
</div>

<script>
// Related Products Global Helpers
function relAddToCart(productId, productName, btn) {
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:12px;"></i>';

    const fd = new FormData();
    fd.append('product_id', productId);
    fd.append('quantity', 1);

    fetch('<?php echo BASE_URL; ?>add-to-cart-ajax.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (data.success) {
            btn.style.background = '#10b981';
            btn.style.color = '#ffffff';
            btn.innerHTML = '<i class="fas fa-check" style="font-size:12px;"></i>';
            setTimeout(() => {
                btn.style.background = '';
                btn.style.color = '';
                btn.innerHTML = origHtml;
            }, 1800);

            if (typeof showToast === 'function') {
                showToast('"' + productName + '" added to cart!');
            }
            if (data.cart_count) {
                const cartBadge = document.getElementById('sn-cart-badge-count');
                if (cartBadge) {
                    cartBadge.textContent = data.cart_count;
                    cartBadge.style.transform = 'scale(1.3)';
                    setTimeout(() => cartBadge.style.transform = 'scale(1)', 250);
                }
                const dockCartBadge = document.getElementById('sn-dock-cart-count');
                if (dockCartBadge) {
                    dockCartBadge.textContent = data.cart_count;
                    dockCartBadge.style.transform = 'scale(1.3)';
                    setTimeout(() => dockCartBadge.style.transform = 'scale(1)', 250);
                }
            }
        } else {
            alert(data.message || 'Unable to add to cart.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        console.error(err);
    });
}

function relToggleWishlist(productId, btn, e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const isAdding = !btn.classList.contains('active');

    <?php if (!isset($_SESSION['customer'])): ?>
        alert('Please login to save items to your wishlist.');
        window.location.href = '<?php echo BASE_URL; ?>login.php';
        return;
    <?php endif; ?>

    btn.classList.toggle('active', isAdding);
    const svg = btn.querySelector('svg');
    if (svg) {
        svg.setAttribute('fill', isAdding ? '#ef4444' : 'none');
        svg.setAttribute('stroke', isAdding ? '#ef4444' : 'currentColor');
    }

    fetch('<?php echo BASE_URL; ?>wishlist_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${encodeURIComponent(productId)}&action=${isAdding ? 'add' : 'remove'}&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>`
    }).then(r => r.json()).then(data => {
        if (data.status === 'success') {
            if (typeof showToast === 'function') {
                showToast(isAdding ? 'Added to your wishlist' : 'Removed from wishlist');
            }
        }
    }).catch(err => console.error(err));
}

document.addEventListener('DOMContentLoaded', function() {
    // Related products scroll buttons
    document.getElementById('snRelPrev')?.addEventListener('click', () => {
        document.getElementById('snRelatedTrack')?.scrollBy({ left: -260, behavior: 'smooth' });
    });
    document.getElementById('snRelNext')?.addEventListener('click', () => {
        document.getElementById('snRelatedTrack')?.scrollBy({ left: 260, behavior: 'smooth' });
    });
    // 1. Gallery Media Switcher
    const thumbs = document.querySelectorAll('.sn-thumb-item');
    const mobThumbs = document.querySelectorAll('.sn-mob-thumb');
    const mainImg = document.getElementById('snMainImg');
    const mainIframe = document.getElementById('snMainIframe');
    const mainVideo = document.getElementById('snMainVideo');
    const dots = document.querySelectorAll('.sn-gallery-dot');
    const mobCounter = document.getElementById('snMobCounter');
    let currentPhotoIdx = 0;
    const totalPhotos = Math.max(thumbs.length, mobThumbs.length, 1);

    function setActiveMedia(index, type, src, thumb) {
        currentPhotoIdx = index;
        thumbs.forEach(t => t.classList.toggle('active', parseInt(t.dataset.index) === index));
        mobThumbs.forEach(t => t.classList.toggle('active', parseInt(t.dataset.index) === index));
        dots.forEach(d => d.classList.toggle('active', parseInt(d.dataset.index) === index));
        if (mobCounter) {
            mobCounter.textContent = (index + 1) + '/' + totalPhotos;
        }
        const activeMobThumb = document.querySelector(`.sn-mob-thumb[data-index="${index}"]`);
        if (activeMobThumb) {
            activeMobThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        // Stop current playing media
        if (mainIframe) { mainIframe.src = ''; mainIframe.style.display = 'none'; }
        if (mainVideo) { mainVideo.pause(); mainVideo.src = ''; mainVideo.style.display = 'none'; }
        if (mainImg) { mainImg.style.display = 'none'; }

        if (type === 'youtube') {
            if (mainIframe) {
                mainIframe.src = src;
                mainIframe.style.display = 'block';
            }
        } else if (type === 'video') {
            if (mainVideo) {
                mainVideo.src = src;
                mainVideo.style.display = 'block';
                mainVideo.play().catch(() => {});
            }
        } else {
            if (mainImg) {
                mainImg.style.display = 'block';
                mainImg.style.opacity = '0.4';
                mainImg.style.transform = 'scale(0.96)';
                mainImg.src = src;
                setTimeout(() => {
                    mainImg.style.opacity = '1';
                    mainImg.style.transform = 'scale(1)';
                }, 120);
            }
        }
    }

    thumbs.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const idx = parseInt(this.dataset.index);
            const type = this.dataset.type || 'image';
            const src = this.dataset.src;
            const thumbUrl = this.dataset.thumb;
            setActiveMedia(idx, type, src, thumbUrl);
        });
    });

    mobThumbs.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const idx = parseInt(this.dataset.index);
            const type = this.dataset.type || 'image';
            const src = this.dataset.src;
            const thumbUrl = this.dataset.thumb;
            setActiveMedia(idx, type, src, thumbUrl);
        });
    });

    document.getElementById('snMobPrev')?.addEventListener('click', () => {
        const nextIdx = (currentPhotoIdx - 1 + totalPhotos) % totalPhotos;
        const target = document.querySelector(`.sn-mob-thumb[data-index="${nextIdx}"]`) || document.querySelector(`.sn-thumb-item[data-index="${nextIdx}"]`);
        if (target) setActiveMedia(nextIdx, target.dataset.type || 'image', target.dataset.src, target.dataset.thumb);
    });

    document.getElementById('snMobNext')?.addEventListener('click', () => {
        const nextIdx = (currentPhotoIdx + 1) % totalPhotos;
        const target = document.querySelector(`.sn-mob-thumb[data-index="${nextIdx}"]`) || document.querySelector(`.sn-thumb-item[data-index="${nextIdx}"]`);
        if (target) setActiveMedia(nextIdx, target.dataset.type || 'image', target.dataset.src, target.dataset.thumb);
    });

    // Native Share / Copy link
    document.getElementById('snShareBtn')?.addEventListener('click', function() {
        if (navigator.share) {
            navigator.share({
                title: '<?php echo addslashes($p_name); ?>',
                text: 'Check out <?php echo addslashes($p_name); ?> on ShopNext!',
                url: window.location.href
            }).catch(() => {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('Product link copied to clipboard!');
            });
        }
    });

    // Mobile Wishlist button sync
    document.getElementById('snMobileWishlistBtn')?.addEventListener('click', function() {
        const desktopWishlist = document.getElementById('btnWishlistToggle');
        if (desktopWishlist) {
            desktopWishlist.click();
        }
        const isAdding = !this.classList.contains('active');
        this.classList.toggle('active', isAdding);
        const svg = this.querySelector('svg');
        if (svg) {
            svg.setAttribute('fill', isAdding ? '#ef4444' : 'none');
            svg.setAttribute('stroke', isAdding ? '#ef4444' : '#111827');
        }
    });

    // Thumb scroll buttons (Desktop)
    const thumbList = document.getElementById('snThumbList');
    document.getElementById('snThumbUp')?.addEventListener('click', () => {
        thumbList?.scrollBy({ top: -70, behavior: 'smooth' });
    });
    document.getElementById('snThumbDown')?.addEventListener('click', () => {
        thumbList?.scrollBy({ top: 70, behavior: 'smooth' });
    });

    // Zoom Fullscreen Modal
    const zoomBtn = document.getElementById('snZoomBtn');
    const zoomModal = document.getElementById('snZoomModal');
    const zoomImg = document.getElementById('snZoomImg');

    zoomBtn?.addEventListener('click', () => {
        if (zoomImg && mainImg && zoomModal) {
            zoomImg.src = mainImg.src;
            zoomModal.style.display = 'flex';
        }
    });
    zoomModal?.addEventListener('click', () => {
        zoomModal.style.display = 'none';
    });

    // 2. Tab Navigation & On-Scroll Top Nav Bar Tab Switching
    const tabBtns = document.querySelectorAll('.sn-tab-btn');
    const tabPanes = document.querySelectorAll('.sn-tab-pane');
    const topNavTabs = document.getElementById('snTopNavTabs');
    const topNavPills = document.querySelectorAll('.sn-nav-tab-pill');
    const mobileSubHeader = document.querySelector('.sn-mobile-sub-header');

    const productSections = [
        { id: 'tab-desc', btnTarget: 'tab-desc' },
        { id: 'tab-specs', btnTarget: 'tab-specs' },
        { id: 'tab-reviews', btnTarget: 'tab-reviews' },
        { id: 'tab-qa', btnTarget: 'tab-qa' }
    ];

    function switchTab(targetId, shouldScroll = false) {
        tabBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.target === targetId));
        topNavPills.forEach(pill => {
            const isActive = pill.dataset.target === targetId;
            pill.classList.toggle('active', isActive);
            if (isActive && mobileSubHeader?.classList.contains('is-scrolled')) {
                pill.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        });

        if (window.innerWidth > 768) {
            tabPanes.forEach(pane => pane.classList.toggle('active', pane.id === targetId));
        } else {
            // On mobile, ensure the target accordion pane is uncollapsed
            const targetPane = document.getElementById(targetId);
            if (targetPane && targetPane.classList.contains('collapsed')) {
                targetPane.classList.remove('collapsed');
            }
        }

        if (shouldScroll) {
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                const headerOffset = 105;
                const elementPosition = targetEl.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        }
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            switchTab(this.dataset.target, window.innerWidth <= 768);
        });
    });

    topNavPills.forEach(pill => {
        pill.addEventListener('click', function(e) {
            e.preventDefault();
            switchTab(this.dataset.target, true);
        });
    });

    document.getElementById('linkToReviewsTab')?.addEventListener('click', (e) => {
        e.preventDefault();
        switchTab('tab-reviews', true);
    });

    // Mobile Accordion Section Toggling
    document.querySelectorAll('.sn-accordion-header').forEach(header => {
        header.addEventListener('click', function() {
            const targetId = this.dataset.toggle;
            const pane = document.getElementById(targetId);
            if (pane) {
                const wasCollapsed = pane.classList.contains('collapsed');
                pane.classList.toggle('collapsed');
                if (wasCollapsed) {
                    topNavPills.forEach(p => p.classList.toggle('active', p.dataset.target === targetId));
                }
            }
        });
    });

    // Description "See More / See Less" Toggle
    const btnDescSeeMore = document.getElementById('btnDescSeeMore');
    const snDescWrapper = document.getElementById('snDescWrapper');
    if (btnDescSeeMore && snDescWrapper) {
        btnDescSeeMore.addEventListener('click', function() {
            const isExpanded = snDescWrapper.classList.toggle('expanded');
            btnDescSeeMore.classList.toggle('expanded', isExpanded);
            const textSpan = btnDescSeeMore.querySelector('.sn-see-more-text');
            if (textSpan) {
                textSpan.textContent = isExpanded ? 'See Less' : 'See More';
            }
        });
    }

    // On-Scroll Handler: Changes Search Bar <-> Specification Tabs and updates active tab
    let isScrollTicking = false;
    function handleWindowScroll() {
        const scrollPos = window.pageYOffset || document.documentElement.scrollTop;

        // 1. Change top nav bar on scroll past 220px
        if (mobileSubHeader) {
            if (scrollPos > 220) {
                if (!mobileSubHeader.classList.contains('is-scrolled')) {
                    mobileSubHeader.classList.add('is-scrolled');
                }
            } else {
                if (mobileSubHeader.classList.contains('is-scrolled')) {
                    mobileSubHeader.classList.remove('is-scrolled');
                }
            }
        }

        // 2. Scroll-Spy: detect which specification section is currently active
        if (window.innerWidth <= 768) {
            let currentActiveId = productSections[0].id;
            for (let i = 0; i < productSections.length; i++) {
                const secEl = document.getElementById(productSections[i].id);
                if (secEl) {
                    const rect = secEl.getBoundingClientRect();
                    if (rect.top <= 130) {
                        currentActiveId = productSections[i].id;
                    }
                }
            }

            topNavPills.forEach(pill => {
                const isActive = pill.dataset.target === currentActiveId;
                pill.classList.toggle('active', isActive);
            });
            tabBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.target === currentActiveId);
            });
        }

        isScrollTicking = false;
    }

    window.addEventListener('scroll', () => {
        if (!isScrollTicking) {
            window.requestAnimationFrame(handleWindowScroll);
            isScrollTicking = true;
        }
    }, { passive: true });

    // Initial check on load
    handleWindowScroll();

    // 3. Option Selection (Color Swatches)
    const swatches = document.querySelectorAll('.sn-color-swatch, .sn-color-swatch-mob');
    const colorText = document.getElementById('selectedColorName');
    const hiddenColorId = document.getElementById('hiddenColorId');
    const hiddenColorName = document.getElementById('hiddenColorName');

    swatches.forEach(swatch => {
        swatch.addEventListener('click', function() {
            swatches.forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            const name = this.dataset.colorName;
            const id = this.dataset.colorId;
            if (colorText) colorText.innerHTML = `${name} <i class="fas fa-chevron-right" style="font-size: 10px;"></i>`;
            if (hiddenColorId) hiddenColorId.value = id;
            if (hiddenColorName) hiddenColorName.value = name;
        });
    });

    // Buy Now Handler
    document.getElementById('btnBuyNow')?.addEventListener('click', function(e) {
        e.preventDefault();
        const form = document.getElementById('snAddToCartForm');
        let buyInput = document.getElementById('hiddenBuyNow');
        if (!buyInput) {
            buyInput = document.createElement('input');
            buyInput.type = 'hidden';
            buyInput.name = 'form_buy_now';
            buyInput.id = 'hiddenBuyNow';
            buyInput.value = '1';
            form.appendChild(buyInput);
        }
        form.submit();
    });

    // 4. Quantity Controls
    const qtyInput = document.getElementById('snQtyInput');
    const qtyMinus = document.getElementById('qtyMinus');
    const qtyPlus = document.getElementById('qtyPlus');

    qtyMinus?.addEventListener('click', () => {
        let val = parseInt(qtyInput.value) || 1;
        if (val > 1) qtyInput.value = val - 1;
    });

    qtyPlus?.addEventListener('click', () => {
        let val = parseInt(qtyInput.value) || 1;
        const max = parseInt(qtyInput.max) || 999;
        if (val < max) qtyInput.value = val + 1;
    });

    // 5. Copy Coupon Code
    const copyBtn = document.getElementById('btnCopyCoupon');
    const couponCode = document.getElementById('couponCodeText')?.textContent.trim();
    copyBtn?.addEventListener('click', () => {
        if (couponCode && navigator.clipboard) {
            navigator.clipboard.writeText(couponCode).then(() => {
                const orig = copyBtn.textContent;
                copyBtn.textContent = 'Copied! ✓';
                copyBtn.style.background = '#86efac';
                copyBtn.style.borderColor = '#4ade80';
                copyBtn.style.color = '#14532d';
                setTimeout(() => {
                    copyBtn.textContent = orig;
                    copyBtn.style.background = '';
                    copyBtn.style.borderColor = '';
                    copyBtn.style.color = '';
                }, 2000);
            });
        }
    });

    // 5.5 AJAX Add to Cart Handler
    const addCartBtn = document.getElementById('btnAddToCart');
    const addToCartForm = document.getElementById('snAddToCartForm');

    addCartBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        
        const origHtml = addCartBtn.innerHTML;
        const stickyCartBtn = document.getElementById('snStickyCartBtn');
        const origStickyHtml = stickyCartBtn ? stickyCartBtn.innerHTML : '';

        addCartBtn.disabled = true;
        addCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        if (stickyCartBtn) {
            stickyCartBtn.disabled = true;
            stickyCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        }

        const formData = new FormData(addToCartForm);
        formData.append('product_id', '<?php echo $p_id; ?>');
        formData.append('quantity', document.getElementById('snQtyInput')?.value || '1');
        formData.append('size_id', document.getElementById('hiddenSizeId')?.value || '0');
        formData.append('size_name', document.getElementById('hiddenSizeName')?.value || '');
        formData.append('color_id', document.getElementById('hiddenColorId')?.value || '0');
        formData.append('color_name', document.getElementById('hiddenColorName')?.value || '');

        fetch('<?php echo BASE_URL; ?>add-to-cart-ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            addCartBtn.disabled = false;
            if (stickyCartBtn) stickyCartBtn.disabled = false;

            if (data.success) {
                addCartBtn.innerHTML = '<i class="fas fa-check"></i> Added!';
                addCartBtn.style.background = '#10b981';
                addCartBtn.style.color = '#ffffff';

                if (stickyCartBtn) {
                    stickyCartBtn.innerHTML = '<i class="fas fa-check"></i> Added!';
                    stickyCartBtn.style.background = '#10b981';
                    stickyCartBtn.style.color = '#ffffff';
                }

                // Update header cart badge and dock cart badge
                const cartBadge = document.getElementById('sn-cart-badge-count');
                if (cartBadge) {
                    cartBadge.textContent = data.cart_count;
                    cartBadge.style.transform = 'scale(1.3)';
                    setTimeout(() => cartBadge.style.transform = 'scale(1)', 250);
                }
                const dockCartBadge = document.getElementById('sn-dock-cart-count');
                if (dockCartBadge) {
                    dockCartBadge.textContent = data.cart_count;
                    dockCartBadge.style.transform = 'scale(1.3)';
                    setTimeout(() => dockCartBadge.style.transform = 'scale(1)', 250);
                }

                // Show toast alert
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Added to cart!');
                }

                setTimeout(() => {
                    addCartBtn.innerHTML = origHtml;
                    addCartBtn.style.background = '';
                    addCartBtn.style.color = '';
                    if (stickyCartBtn) {
                        stickyCartBtn.innerHTML = origStickyHtml;
                        stickyCartBtn.style.background = '';
                        stickyCartBtn.style.color = '';
                    }
                }, 2000);
            } else {
                addCartBtn.innerHTML = origHtml;
                if (stickyCartBtn) stickyCartBtn.innerHTML = origStickyHtml;
                alert(data.message || 'Unable to add to cart.');
            }
        })
        .catch(err => {
            console.error(err);
            addToCartForm.submit();
        });
    });

    // 5.8 Sticky Bottom Action Bar Handlers (Message, Add to Cart, Buy Now)
    const stickyMsgBtn = document.getElementById('snStickyMsgBtn');
    const stickyCartBtn = document.getElementById('snStickyCartBtn');
    const stickyBuyBtn = document.getElementById('snStickyBuyBtn');

    stickyMsgBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        openModal();
    });

    stickyCartBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        if (addCartBtn) {
            addCartBtn.click();
        }
    });

    stickyBuyBtn?.addEventListener('click', function(e) {
        e.preventDefault();
        const mainBuy = document.getElementById('btnBuyNow');
        if (mainBuy) {
            mainBuy.click();
        } else {
            const form = document.getElementById('snAddToCartForm');
            let buyInput = document.getElementById('hiddenBuyNow');
            if (!buyInput) {
                buyInput = document.createElement('input');
                buyInput.type = 'hidden';
                buyInput.name = 'form_buy_now';
                buyInput.id = 'hiddenBuyNow';
                buyInput.value = '1';
                form.appendChild(buyInput);
            }
            form.submit();
        }
    });

    // 6. Wishlist Button Toggle
    const wishlistBtn = document.getElementById('btnWishlistToggle');
    wishlistBtn?.addEventListener('click', function() {
        const pid = this.dataset.productId;
        const isAdding = !this.classList.contains('active');
        
        <?php if (!isset($_SESSION['customer'])): ?>
            alert('Please login to save items to your wishlist.');
            window.location.href = '<?php echo BASE_URL; ?>login.php';
            return;
        <?php endif; ?>

        this.classList.toggle('active', isAdding);
        const icon = this.querySelector('i');
        if (icon) {
            icon.className = isAdding ? 'fas fa-heart' : 'far fa-heart';
        }

        fetch('<?php echo BASE_URL; ?>wishlist_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `product_id=${encodeURIComponent(pid)}&action=${isAdding ? 'add' : 'remove'}&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>`
        }).then(r => r.json()).then(data => {
            if (data.status === 'success') {
                if (typeof showToast === 'function') {
                    showToast(isAdding ? 'Added to your wishlist' : 'Removed from wishlist');
                }
            }
        }).catch(err => console.error(err));
    });

    // 7. Little AI Assistant Modal & Logic
    const openAiBtn = document.getElementById('openAiAssistantBtn');
    const aiModal = document.getElementById('snAiModal');
    const aiClose = document.getElementById('snAiModalClose');
    const aiMessages = document.getElementById('snAiMessages');
    const aiInput = document.getElementById('snAiInput');
    const aiSendBtn = document.getElementById('snAiSendBtn');
    const aiChips = document.querySelectorAll('.sn-ai-prompt-chip');

    function openModal() {
        if (aiModal) {
            aiModal.classList.add('open');
            aiInput?.focus();
        }
    }

    function closeModal() {
        if (aiModal) aiModal.classList.remove('open');
    }

    openAiBtn?.addEventListener('click', openModal);
    aiClose?.addEventListener('click', closeModal);
    aiModal?.addEventListener('click', (e) => {
        if (e.target === aiModal) closeModal();
    });

    function appendAiMsg(text, sender = 'ai') {
        const msgDiv = document.createElement('div');
        msgDiv.className = `sn-ai-msg ${sender}`;
        const bubble = document.createElement('div');
        bubble.className = 'sn-ai-msg-bubble';
        bubble.innerHTML = text;
        msgDiv.appendChild(bubble);
        aiMessages.appendChild(msgDiv);
        aiMessages.scrollTop = aiMessages.scrollHeight;
        return msgDiv;
    }

    function sendAiPrompt(promptText) {
        if (!promptText.trim()) return;
        appendAiMsg(promptText, 'user');
        if (aiInput) aiInput.value = '';

        const thinkingMsg = appendAiMsg('<em>Thinking... ✨</em>', 'ai');

        const formData = new FormData();
        formData.append('prompt', promptText);
        formData.append('product_id', '<?php echo $p_id; ?>');

        fetch('<?php echo BASE_URL; ?>gemini_chat.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            thinkingMsg.remove();
            if (data.status === 'success' && data.response) {
                appendAiMsg(data.response, 'ai');
            } else {
                appendAiMsg('Sorry, I encountered an issue: ' + (data.message || 'Please check your connection and try again.'), 'ai');
            }
        })
        .catch(err => {
            thinkingMsg.remove();
            appendAiMsg('Unable to connect to AI assistant right now. Please try again.', 'ai');
            console.error(err);
        });
    }

    aiSendBtn?.addEventListener('click', () => {
        const text = aiInput?.value.trim();
        if (text) sendAiPrompt(text);
    });

    aiInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const text = aiInput.value.trim();
            if (text) sendAiPrompt(text);
        }
    });

    aiChips.forEach(chip => {
        chip.addEventListener('click', function() {
            const prompt = this.dataset.prompt;
            sendAiPrompt(prompt);
        });
    });
});
</script>

<?php require_once('footer.php'); ?>
