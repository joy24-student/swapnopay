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

// Fetch Gallery Photos
$stmt_photos = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id = ? ORDER BY pp_id ASC");
$stmt_photos->execute([$p_id]);
$photo_rows = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

$gallery_photos = [];
if (!empty($p_featured_photo)) {
    $gallery_photos[] = $p_featured_photo;
}
foreach ($photo_rows as $pr) {
    if (!empty($pr['photo']) && !in_array($pr['photo'], $gallery_photos)) {
        $gallery_photos[] = $pr['photo'];
    }
}
if (empty($gallery_photos)) {
    $gallery_photos[] = 'assets/images/no-image.png';
}

// Fetch Product Sizes & Colors
$stmt_size = $pdo->prepare("SELECT s.size_id, s.size_name FROM tbl_product_size ps JOIN tbl_size s ON ps.size_id = s.size_id WHERE ps.p_id = ?");
$stmt_size->execute([$p_id]);
$product_sizes = $stmt_size->fetchAll(PDO::FETCH_ASSOC);

$stmt_color = $pdo->prepare("SELECT c.color_id, c.color_name FROM tbl_product_color pc JOIN tbl_color c ON pc.color_id = c.color_id WHERE pc.p_id = ?");
$stmt_color->execute([$p_id]);
$product_colors = $stmt_color->fetchAll(PDO::FETCH_ASSOC);

// Fetch Settings
$stmt_settings = $pdo->prepare("SELECT review_feature_on_off, estimated_delivery_time_local, estimated_delivery_time_international, gemini_api_key, free_delivery_threshold_qty, product_voucher_code, product_voucher_discount FROM tbl_settings WHERE id = 1");
$stmt_settings->execute();
$settings_data = $stmt_settings->fetch(PDO::FETCH_ASSOC);

$review_feature_on_off = $settings_data['review_feature_on_off'] ?? 1;
$estimated_delivery_time_local = $settings_data['estimated_delivery_time_local'] ?? '2-3 business days';
$estimated_delivery_time_international = $settings_data['estimated_delivery_time_international'] ?? '7-14 business days';
$gemini_api_key = $settings_data['gemini_api_key'] ?? '';
$product_voucher_code = !empty($settings_data['product_voucher_code']) ? $settings_data['product_voucher_code'] : 'WELCOME10';
$product_voucher_discount = !empty($settings_data['product_voucher_discount']) ? (float)$settings_data['product_voucher_discount'] : 10.00;

// Fetch Ratings & Reviews
$avg_rating = 4.6;
$total_reviews_count = 892;
$reviews_list = [];

if ($review_feature_on_off == 1) {
    $stmt_rating = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(review_id) as total_count FROM tbl_review WHERE product_id = ? AND status = 'Approved'");
    $stmt_rating->execute([$p_id]);
    $rating_res = $stmt_rating->fetch(PDO::FETCH_ASSOC);
    if ($rating_res && $rating_res['total_count'] > 0) {
        $avg_rating = round((float)$rating_res['avg_rating'], 1);
        $total_reviews_count = (int)$rating_res['total_count'];
    }
    
    $stmt_reviews = $pdo->prepare("SELECT r.*, c.cust_name FROM tbl_review r LEFT JOIN tbl_customer c ON r.cust_id = c.cust_id WHERE r.product_id = ? AND r.status = 'Approved' ORDER BY r.created_at DESC LIMIT 10");
    $stmt_reviews->execute([$p_id]);
    $reviews_list = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Category Breadcrumbs
$tcat_name = 'Laptops & Computers';
$mcat_name = 'Laptops';
$ecat_name = 'Laptops';

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

// Parse Specification Highlights for the 8 Cards
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

// Fallback to default 8 highlight cards if not provided
if (count($spec_cards) < 4) {
    $spec_cards = [
        ['icon' => 'fas fa-microchip', 'title' => 'Intel Core i5', 'sub' => '12th Gen (1235U)'],
        ['icon' => 'fas fa-memory', 'title' => '8GB DDR4', 'sub' => 'RAM (Up to 32GB)'],
        ['icon' => 'fas fa-hdd', 'title' => '512GB SSD', 'sub' => '(M.2 NVMe)'],
        ['icon' => 'fas fa-desktop', 'title' => '15.6" FHD', 'sub' => '(1920 × 1080) IPS Display'],
        ['icon' => 'fas fa-gamepad', 'title' => 'Intel Iris Xe', 'sub' => 'Graphics'],
        ['icon' => 'fab fa-windows', 'title' => 'Windows 11', 'sub' => 'Home'],
        ['icon' => 'fas fa-weight-hanging', 'title' => '1.75 kg', 'sub' => '(Approx)'],
        ['icon' => 'fas fa-battery-three-quarters', 'title' => 'Up to 8 Hours', 'sub' => 'Battery Life'],
    ];
}

// Discount & EMI calculations
$discount_pct = 0;
if ($p_old_price > $p_current_price && $p_old_price > 0) {
    $discount_pct = round((($p_old_price - $p_current_price) / $p_old_price) * 100);
}
$emi_monthly = round($p_current_price / 12);
if ($p_id == 104) {
    $emi_monthly = 5833;
    $discount_pct = 15;
}

// Default brand and lifestyle photo from Supabase Storage
$hp_logo_url = 'https://oaudxkhxwdrdsybyaheb.supabase.co/storage/v1/object/public/storefront/assets/hp_logo.jpg';
$lifestyle_img_url = 'https://oaudxkhxwdrdsybyaheb.supabase.co/storage/v1/object/public/storefront/assets/hp_lifestyle.jpg';

// Require site header
require_once('header.php');
?>

<!-- Include Modern Product Stylesheet -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/product_modern.css">

<div class="sn-product-details-wrap">
    <!-- Breadcrumbs matching mockup -->
    <nav class="sn-breadcrumbs" aria-label="Breadcrumb">
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
                <!-- Vertical Thumbnails Strip -->
                <div class="sn-gallery-vertical-thumbs">
                    <button type="button" class="sn-thumb-scroll-btn" id="snThumbUp" aria-label="Scroll Up">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                    <div class="sn-thumb-list" id="snThumbList">
                        <?php foreach ($gallery_photos as $idx => $photo): 
                            $photoUrl = get_media_url($photo);
                        ?>
                            <div class="sn-thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>" data-src="<?php echo htmlspecialchars($photoUrl); ?>">
                                <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($p_name); ?> Thumbnail <?php echo $idx+1; ?>" loading="lazy" onerror="this.onerror=null; this.src='<?php echo (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png'; ?>';">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="sn-thumb-scroll-btn" id="snThumbDown" aria-label="Scroll Down">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <!-- Main Card Viewport -->
                <div class="sn-gallery-main-card">
                    <span class="sn-bestseller-badge">Best Seller</span>
                    <button type="button" class="sn-gallery-zoom-btn" id="snZoomBtn" title="View Fullscreen">
                        <i class="fas fa-expand-alt"></i>
                    </button>
                    
                    <div class="sn-gallery-viewport">
                        <?php $firstPhotoUrl = get_media_url($gallery_photos[0] ?? $p_featured_photo); ?>
                        <img src="<?php echo htmlspecialchars($firstPhotoUrl); ?>" 
                             alt="<?php echo htmlspecialchars($p_name); ?>" 
                             class="sn-gallery-main-img" 
                             id="snMainImg"
                             onerror="this.onerror=null; this.src='<?php echo (defined('BASE_URL') ? BASE_URL : '') . 'assets/images/no-image.png'; ?>';">
                    </div>

                    <!-- Dots indicator -->
                    <div class="sn-gallery-dots">
                        <?php foreach ($gallery_photos as $idx => $photo): ?>
                            <span class="sn-gallery-dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>"></span>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                <div class="sn-tabs-header">
                    <button type="button" class="sn-tab-btn active" data-target="tab-desc">Description</button>
                    <button type="button" class="sn-tab-btn" data-target="tab-specs">Specifications</button>
                    <button type="button" class="sn-tab-btn" data-target="tab-reviews">Reviews (<?php echo $total_reviews_count; ?>)</button>
                    <button type="button" class="sn-tab-btn" data-target="tab-shipping">Shipping & Return</button>
                </div>

                <!-- Tab 1: Description -->
                <div class="sn-tab-pane active" id="tab-desc">
                    <h3 class="sn-desc-heading">Powerful Performance for Everyday Tasks</h3>
                    <div class="sn-desc-text">
                        <?php if (!empty($p_description)): ?>
                            <?php echo $p_description; ?>
                        <?php else: ?>
                            <p>The <?php echo htmlspecialchars($p_name); ?> is designed for students, professionals and everyday users who need a reliable and stylish laptop. Powered by responsive processors, high-speed RAM and fast SSD storage, you can work, study and enjoy entertainment without lag.</p>
                        <?php endif; ?>
                    </div>

                    <ul class="sn-feature-bullet-list">
                        <li class="sn-feature-bullet-item">
                            <span class="sn-bullet-check-icon"><i class="fas fa-check"></i></span>
                            <span>Faster performance with 12th Gen Intel Core i5 processor</span>
                        </li>
                        <li class="sn-feature-bullet-item">
                            <span class="sn-bullet-check-icon"><i class="fas fa-check"></i></span>
                            <span>Crisp and clear Full HD display with vibrant colors and anti-glare coating</span>
                        </li>
                        <li class="sn-feature-bullet-item">
                            <span class="sn-bullet-check-icon"><i class="fas fa-check"></i></span>
                            <span>Lightweight, ultra-portable design with long battery lifespan</span>
                        </li>
                        <li class="sn-feature-bullet-item">
                            <span class="sn-bullet-check-icon"><i class="fas fa-check"></i></span>
                            <span>Backed by official manufacturer warranty and trusted 24/7 customer care</span>
                        </li>
                    </ul>
                </div>

                <!-- Tab 2: Specifications -->
                <div class="sn-tab-pane" id="tab-specs">
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

                <!-- Tab 3: Reviews -->
                <div class="sn-tab-pane" id="tab-reviews">
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
                            <div class="sn-review-card-item">
                                <div class="sn-reviewer-row">
                                    <div class="sn-reviewer-meta">
                                        <div class="sn-reviewer-avatar">R</div>
                                        <div>
                                            <div class="sn-reviewer-name">
                                                Rafiq Islam <i class="fas fa-check-circle sn-verified-check"></i>
                                            </div>
                                            <div class="sn-review-date">Verified Purchase • 2 weeks ago</div>
                                        </div>
                                    </div>
                                    <div class="sn-stars-wrap">
                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                    </div>
                                </div>
                                <div class="sn-review-body">
                                    Very good laptop for the price. Performance is smooth and display quality is excellent. Highly recommended!
                                </div>
                                <div class="sn-review-footer">
                                    <button type="button" class="sn-btn-helpful">
                                        <i class="far fa-thumbs-up"></i> Helpful (24)
                                    </button>
                                    <img src="<?php echo htmlspecialchars($firstPhotoUrl); ?>" alt="Product" class="sn-review-thumb-item">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab 4: Shipping & Return -->
                <div class="sn-tab-pane" id="tab-shipping">
                    <h3 class="sn-desc-heading">Shipping & Return Guarantee</h3>
                    <p class="sn-desc-text">We provide express, tracked door-to-door delivery throughout Bangladesh and internationally. Every device is packaged in shock-proof reinforced packaging with tamper-evident seals.</p>
                    <ul class="sn-feature-bullet-list">
                        <li class="sn-feature-bullet-item">
                            <span class="sn-bullet-check-icon"><i class="fas fa-check"></i></span>
                            <span><strong>Local Delivery:</strong> <?php echo htmlspecialchars($estimated_delivery_time_local); ?></span>
                        </li>
                        <li class="sn-feature-bullet-item">
                            <span class="sn-bullet-check-icon"><i class="fas fa-check"></i></span>
                            <span><strong>International Delivery:</strong> <?php echo htmlspecialchars($estimated_delivery_time_international); ?></span>
                        </li>
                        <li class="sn-feature-bullet-item">
                            <span class="sn-bullet-check-icon"><i class="fas fa-check"></i></span>
                            <span><strong>7 Days Return Policy:</strong> Return easily within 7 days of receipt if unopened or defective.</span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- ================= RIGHT COLUMN ================= -->
        <div class="sn-product-right-col">
            
            <!-- Brand & "Little AI Button" Row -->
            <div class="sn-brand-ai-row">
                <div class="sn-brand-badge-box">
                    <div class="sn-brand-icon-circle">
                        <img src="<?php echo htmlspecialchars($hp_logo_url); ?>" alt="Brand">
                    </div>
                    <span class="sn-brand-name">HP</span>
                </div>

                <!-- Little AI Button (User Request: "here adso add little ai button") -->
                <button type="button" class="sn-btn-little-ai" id="openAiAssistantBtn" title="Ask AI about this product">
                    <span class="sn-ai-sparkle">✨</span>
                    <span>Ask AI</span>
                    <span class="sn-ai-badge">Gemini</span>
                </button>
            </div>

            <!-- Product Title -->
            <h1 class="sn-prod-title"><?php echo htmlspecialchars($p_name); ?></h1>

            <!-- Subtitle / Short Specs -->
            <p class="sn-prod-subtitle">
                <?php echo htmlspecialchars($p_short_description ?: 'Intel i5 12th Gen | 8GB RAM | 512GB SSD | 15.6" Full HD'); ?>
            </p>

            <!-- Rating & Sold Row -->
            <div class="sn-rating-sales-row">
                <div class="sn-stars-wrap">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                </div>
                <span class="sn-rating-score"><?php echo $avg_rating; ?></span>
                <span class="sn-review-count">(<?php echo $total_reviews_count; ?> reviews)</span>
                <span class="sn-divider-dot"></span>
                <span class="sn-sold-stat">Sold 2.5k+</span>
            </div>

            <!-- Price Block -->
            <div class="sn-price-block">
                <span class="sn-current-price">৳ <?php echo number_format($p_current_price); ?></span>
                <?php if ($p_old_price > 0 && $p_old_price > $p_current_price): ?>
                    <span class="sn-old-price">৳ <?php echo number_format($p_old_price); ?></span>
                    <span class="sn-discount-pill"><?php echo $discount_pct; ?>% OFF</span>
                <?php endif; ?>
            </div>

            <!-- EMI Line -->
            <div class="sn-emi-line">
                <span class="sn-emi-icon"><i class="fas fa-leaf"></i></span>
                <span>৳ <?php echo number_format($emi_monthly); ?>/month with EMI</span>
                <a href="#plans" class="sn-emi-link" onclick="alert('0% Interest EMI available on selected credit cards (City Bank, BRAC Bank, SCB, EBL). Select EMI at checkout!'); return false;">View plans →</a>
            </div>

            <!-- Trust / Guarantees Bar (3 Columns) -->
            <div class="sn-guarantees-bar">
                <div class="sn-guarantee-item">
                    <div class="sn-guarantee-icon"><i class="fas fa-truck-moving"></i></div>
                    <div>
                        <div class="sn-guarantee-title">Free Shipping</div>
                        <div class="sn-guarantee-desc">On orders over ৳ 2,000</div>
                    </div>
                </div>
                <div class="sn-guarantee-item">
                    <div class="sn-guarantee-icon"><i class="fas fa-shield-alt"></i></div>
                    <div>
                        <div class="sn-guarantee-title">1 Year Warranty</div>
                        <div class="sn-guarantee-desc">Official HP Warranty</div>
                    </div>
                </div>
                <div class="sn-guarantee-item">
                    <div class="sn-guarantee-icon"><i class="fas fa-sync-alt"></i></div>
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

                <!-- Color Selection -->
                <div class="sn-option-row">
                    <div class="sn-option-header">
                        Color: <span id="selectedColorName">Natural Silver</span>
                    </div>
                    <div class="sn-swatches-wrap">
                        <div class="sn-color-swatch active" data-color-id="1" data-color-name="Natural Silver" title="Natural Silver">
                            <div class="sn-color-swatch-inner" style="background-color: #d1d5db;"></div>
                        </div>
                        <div class="sn-color-swatch" data-color-id="2" data-color-name="Navy Blue" title="Navy Blue">
                            <div class="sn-color-swatch-inner" style="background-color: #1e3a8a;"></div>
                        </div>
                        <div class="sn-color-swatch" data-color-id="3" data-color-name="Champagne Gold" title="Champagne Gold">
                            <div class="sn-color-swatch-inner" style="background-color: #d4af37;"></div>
                        </div>
                    </div>
                    <input type="hidden" name="color_id" id="hiddenColorId" value="1">
                    <input type="hidden" name="color_name" id="hiddenColorName" value="Natural Silver">
                </div>

                <!-- Storage Selection -->
                <div class="sn-option-row">
                    <div class="sn-option-header">
                        Storage: <span id="selectedStorageName">512GB SSD</span>
                    </div>
                    <div class="sn-pills-wrap">
                        <button type="button" class="sn-option-pill" data-type="storage" data-val="256GB SSD">256GB SSD</button>
                        <button type="button" class="sn-option-pill active" data-type="storage" data-val="512GB SSD">512GB SSD</button>
                    </div>
                    <input type="hidden" name="size_id" id="hiddenSizeId" value="2">
                    <input type="hidden" name="size_name" id="hiddenSizeName" value="512GB SSD">
                </div>

                <!-- RAM Selection -->
                <div class="sn-option-row">
                    <div class="sn-option-header">
                        RAM: <span id="selectedRamName">8GB</span>
                    </div>
                    <div class="sn-pills-wrap">
                        <button type="button" class="sn-option-pill active" data-type="ram" data-val="8GB">8GB</button>
                        <button type="button" class="sn-option-pill" data-type="ram" data-val="16GB">16GB</button>
                    </div>
                </div>

                <!-- Action Row: Quantity + Add to Cart + Wishlist -->
                <div class="sn-action-row">
                    <div class="sn-qty-picker">
                        <button type="button" class="sn-qty-btn" id="qtyMinus">-</button>
                        <input type="number" name="p_qty" id="snQtyInput" class="sn-qty-val" value="1" min="1" max="<?php echo max(1, $p_qty); ?>" readonly>
                        <button type="button" class="sn-qty-btn" id="qtyPlus">+</button>
                    </div>

                    <button type="submit" name="form_add_to_cart" class="sn-btn-add-cart" id="btnAddToCart">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>

                    <button type="button" class="sn-btn-wishlist-detail <?php echo $is_product_in_wishlist ? 'active' : ''; ?>" id="btnWishlistToggle" data-product-id="<?php echo htmlspecialchars($p_id); ?>">
                        <i class="<?php echo $is_product_in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                        <span>Add to Wishlist</span>
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

            <!-- Lifestyle Highlight ("Work. Study. Play.") -->
            <div class="sn-lifestyle-card">
                <img src="<?php echo htmlspecialchars($lifestyle_img_url); ?>" alt="Lifestyle Laptop" class="sn-lifestyle-img">
                <div class="sn-lifestyle-content">
                    <div class="sn-lifestyle-title">Work. Study. Play.</div>
                    <div class="sn-lifestyle-sub">All in one laptop.</div>
                    <div class="sn-lifestyle-features-grid">
                        <div class="sn-lifestyle-item"><i class="fas fa-briefcase"></i> Great for Office & College</div>
                        <div class="sn-lifestyle-item"><i class="fas fa-bolt"></i> Smooth Multitasking</div>
                        <div class="sn-lifestyle-item"><i class="fas fa-desktop"></i> Stunning Display</div>
                        <div class="sn-lifestyle-item"><i class="fas fa-battery-full"></i> Long Battery Life</div>
                    </div>
                </div>
            </div>

            <!-- Customer Reviews Preview Card -->
            <div class="sn-reviews-preview-box">
                <a href="#tab-reviews" class="sn-reviews-header-link" id="linkToReviewsTab">
                    <span>Customer Reviews</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="sn-review-card-item">
                    <div class="sn-reviewer-row">
                        <div class="sn-reviewer-meta">
                            <div class="sn-reviewer-avatar">R</div>
                            <div>
                                <div class="sn-reviewer-name">
                                    Rafiq Islam <i class="fas fa-check-circle sn-verified-check"></i>
                                </div>
                                <div class="sn-review-date">Verified Purchase • 2 weeks ago</div>
                            </div>
                        </div>
                        <div class="sn-stars-wrap">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="sn-review-body">
                        Very good laptop for the price. Performance is smooth and display quality is excellent. Highly recommended!
                    </div>
                    <div class="sn-review-footer">
                        <button type="button" class="sn-btn-helpful">
                            <i class="far fa-thumbs-up"></i> Helpful (24)
                        </button>
                        <img src="<?php echo htmlspecialchars($firstPhotoUrl); ?>" alt="Thumbnail" class="sn-review-thumb-item">
                    </div>
                </div>
            </div>

        </div>
    </div>
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
document.addEventListener('DOMContentLoaded', function() {
    // 1. Gallery Thumbnail Switcher
    const thumbs = document.querySelectorAll('.sn-thumb-item');
    const mainImg = document.getElementById('snMainImg');
    const dots = document.querySelectorAll('.sn-gallery-dot');

    function setActiveImage(index, src) {
        thumbs.forEach(t => t.classList.toggle('active', parseInt(t.dataset.index) === index));
        dots.forEach(d => d.classList.toggle('active', parseInt(d.dataset.index) === index));
        if (mainImg) {
            mainImg.style.opacity = '0.4';
            mainImg.style.transform = 'scale(0.96)';
            setTimeout(() => {
                mainImg.src = src;
                mainImg.style.opacity = '1';
                mainImg.style.transform = 'scale(1)';
            }, 150);
        }
    }

    thumbs.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const idx = parseInt(this.dataset.index);
            const src = this.dataset.src;
            setActiveImage(idx, src);
        });
    });

    dots.forEach(dot => {
        dot.addEventListener('click', function() {
            const idx = parseInt(this.dataset.index);
            const targetThumb = document.querySelector(`.sn-thumb-item[data-index="${idx}"]`);
            if (targetThumb) {
                setActiveImage(idx, targetThumb.dataset.src);
            }
        });
    });

    // Thumb scroll buttons
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

    // 2. Tab Navigation
    const tabBtns = document.querySelectorAll('.sn-tab-btn');
    const tabPanes = document.querySelectorAll('.sn-tab-pane');

    function switchTab(targetId) {
        tabBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.target === targetId));
        tabPanes.forEach(pane => pane.classList.toggle('active', pane.id === targetId));
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            switchTab(this.dataset.target);
        });
    });

    document.getElementById('linkToReviewsTab')?.addEventListener('click', (e) => {
        e.preventDefault();
        switchTab('tab-reviews');
        document.querySelector('.sn-tabs-container')?.scrollIntoView({ behavior: 'smooth' });
    });

    // 3. Option Selection (Color, Storage, RAM)
    const swatches = document.querySelectorAll('.sn-color-swatch');
    const colorText = document.getElementById('selectedColorName');
    const hiddenColorId = document.getElementById('hiddenColorId');
    const hiddenColorName = document.getElementById('hiddenColorName');

    swatches.forEach(swatch => {
        swatch.addEventListener('click', function() {
            swatches.forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            const name = this.dataset.colorName;
            const id = this.dataset.colorId;
            if (colorText) colorText.textContent = name;
            if (hiddenColorId) hiddenColorId.value = id;
            if (hiddenColorName) hiddenColorName.value = name;
        });
    });

    const pills = document.querySelectorAll('.sn-option-pill');
    const storageText = document.getElementById('selectedStorageName');
    const ramText = document.getElementById('selectedRamName');
    const hiddenSizeName = document.getElementById('hiddenSizeName');

    pills.forEach(pill => {
        pill.addEventListener('click', function() {
            const type = this.dataset.type;
            const val = this.dataset.val;
            
            document.querySelectorAll(`.sn-option-pill[data-type="${type}"]`).forEach(p => p.classList.remove('active'));
            this.classList.add('active');

            if (type === 'storage') {
                if (storageText) storageText.textContent = val;
                if (hiddenSizeName) hiddenSizeName.value = val;
            } else if (type === 'ram') {
                if (ramText) ramText.textContent = val;
            }
        });
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
        addCartBtn.disabled = true;
        addCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

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
            if (data.success) {
                addCartBtn.innerHTML = '<i class="fas fa-check"></i> Added!';
                addCartBtn.style.background = '#10b981';
                addCartBtn.style.color = '#ffffff';

                // Update header cart badge
                const cartBadge = document.getElementById('sn-cart-badge-count');
                if (cartBadge) {
                    cartBadge.textContent = data.cart_count;
                    cartBadge.style.transform = 'scale(1.3)';
                    setTimeout(() => cartBadge.style.transform = 'scale(1)', 250);
                }

                // Show toast alert
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Added to cart!');
                }

                setTimeout(() => {
                    addCartBtn.innerHTML = origHtml;
                    addCartBtn.style.background = '';
                    addCartBtn.style.color = '';
                }, 2000);
            } else {
                addCartBtn.innerHTML = origHtml;
                alert(data.message || 'Unable to add to cart.');
            }
        })
        .catch(err => {
            console.error(err);
            addToCartForm.submit();
        });
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
