<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';
require_once __DIR__ . '/admin/inc/CSRF_Protect.php';
$csrf = new CSRF_Protect();

// Fetch Settings
$settings = $pdo->query("SELECT * FROM tbl_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
$currencySymbol = !empty($settings['currency_symbol']) ? $settings['currency_symbol'] : 'BDT';
$favicon = !empty($settings['favicon']) ? $settings['favicon'] : 'default_favicon.png';

// Active Category selection (default to Men's Clothing ID 2 to match mockup)
$active_cat_id = isset($_GET['cat_id']) ? trim($_GET['cat_id']) : '2';

// Desktop User-Agent check: redirect desktop browsers to full desktop category listing
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isMobileUA = (bool)preg_match('/(android|bb|mobile|phone|iphone|ipad|ipod|tablet|kindle|silk|opera mini)/i', $userAgent);

if (!$isMobileUA && !isset($_GET['mobile_mode'])) {
    $targetId = (is_numeric($active_cat_id) && (int)$active_cat_id > 0) ? (int)$active_cat_id : 1;
    header('Location: product-category.php?id=' . $targetId . '&type=top-category');
    exit;
}

// ── Helper to retrieve subcategories & products for a top category ────────────
function getCategoryData($pdo, $tcat_id, $currencySymbol) {
    if ($tcat_id === 'foryou' || $tcat_id === '0') {
        // "For you" smart mix
        $subcategories = [
            ['name' => 'Matching Sets', 'photo' => 'cat_mockup/sub_matching_sets.png', 'url' => 'product-category.php?id=2&type=mid-category'],
            ['name' => 'Down Coats & Parkas', 'photo' => 'cat_mockup/sub_down_coats.png', 'url' => 'product-category.php?id=3&type=mid-category'],
            ['name' => 'Jackets & Light Coats', 'photo' => 'cat_mockup/sub_jackets.png', 'url' => 'product-category.php?id=4&type=mid-category'],
            ['name' => 'Underwear', 'photo' => 'cat_mockup/sub_underwear.png', 'url' => 'product-category.php?id=5&type=mid-category'],
            ['name' => 'Hoodies & Sweatshirts', 'photo' => 'cat_mockup/sub_hoodies.png', 'url' => 'product-category.php?id=6&type=mid-category'],
            ['name' => 'Jeans', 'photo' => 'cat_mockup/sub_jeans.png', 'url' => 'product-category.php?id=7&type=mid-category'],
            ['name' => 'Suits & Separates', 'photo' => 'cat_mockup/sub_suits.png', 'url' => 'product-category.php?id=8&type=mid-category'],
            ['name' => 'Wool & Trench Coats', 'photo' => 'cat_mockup/sub_trench_coats.png', 'url' => 'product-category.php?id=9&type=mid-category'],
            ['name' => 'Polo Shirts', 'photo' => 'cat_mockup/sub_polo_shirts.png', 'url' => 'product-category.php?id=10&type=mid-category'],
            ['name' => 'Denim Tops', 'photo' => 'cat_mockup/sub_denim_tops.png', 'url' => 'product-category.php?id=11&type=mid-category'],
            ['name' => 'Shirts', 'photo' => 'cat_mockup/sub_shirts.png', 'url' => 'product-category.php?id=12&type=mid-category'],
        ];

        $stmtProd = $pdo->query("
            SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo, p_total_view, 1 as has_choice 
            FROM tbl_product 
            WHERE p_is_active = 1 
            ORDER BY CASE WHEN p_name LIKE '%Shark Skin%' THEN 1 WHEN p_name LIKE '%Autumn/Winter%' THEN 2 ELSE 3 END, p_id DESC 
            LIMIT 10
        ");
        $products = $stmtProd ? $stmtProd->fetchAll(PDO::FETCH_ASSOC) : [];
        return ['subcategories' => $subcategories, 'products' => $products, 'title' => 'For you'];
    }

    $tcat_id_int = (int)$tcat_id;
    
    // Top category title
    $catStmt = $pdo->prepare("SELECT tcat_name FROM tbl_top_category WHERE tcat_id = ?");
    $catStmt->execute([$tcat_id_int]);
    $catTitle = $catStmt->fetchColumn() ?: 'Category';

    // Subcategories (tbl_mid_category)
    $subStmt = $pdo->prepare("
        SELECT m.mcat_id, m.mcat_name, m.photo,
               (SELECT p.p_featured_photo 
                FROM tbl_end_category e 
                JOIN tbl_product p ON p.ecat_id = e.ecat_id 
                WHERE e.mcat_id = m.mcat_id AND p.p_is_active = 1 AND p.p_featured_photo IS NOT NULL AND p.p_featured_photo != ''
                LIMIT 1) as prod_photo
        FROM tbl_mid_category m
        WHERE m.tcat_id = ?
        ORDER BY m.mcat_id ASC
    ");
    $subStmt->execute([$tcat_id_int]);
    $rawSubs = $subStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $subcategories = [];
    foreach ($rawSubs as $sub) {
        $photo = !empty($sub['photo']) ? $sub['photo'] : (!empty($sub['prod_photo']) ? $sub['prod_photo'] : 'cat_all.jpg');
        $subcategories[] = [
            'id' => $sub['mcat_id'],
            'name' => $sub['mcat_name'],
            'photo' => $photo,
            'url' => 'product-category.php?id=' . $sub['mcat_id'] . '&type=mid-category'
        ];
    }

    // Products for this category (ensure Shark Skin and Polo tracksuit appear first for Men's clothing)
    $prodStmt = $pdo->prepare("
        SELECT p.p_id, p.p_name, p.p_current_price, p.p_old_price, p.p_featured_photo, p.p_total_view, p.is_top_sale,
               CASE WHEN p.p_name LIKE '%Autumn/Winter%' OR p.p_is_featured = 1 THEN 1 ELSE 0 END as has_choice,
               COALESCE((SELECT AVG(r.rating) FROM tbl_rating r WHERE r.p_id = p.p_id), 5.0) as avg_rating,
               COALESCE((SELECT COUNT(*) FROM tbl_rating r WHERE r.p_id = p.p_id), 0) as rating_count
        FROM tbl_product p
        JOIN tbl_end_category e ON p.ecat_id = e.ecat_id
        JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id
        WHERE m.tcat_id = ? AND p.p_is_active = 1
        ORDER BY 
            CASE 
                WHEN p.p_name LIKE '%Shark Skin%' THEN 1 
                WHEN p.p_name LIKE '%Autumn/Winter%' THEN 2 
                ELSE 3 
            END ASC,
            p.p_is_featured DESC, 
            p.p_id DESC
        LIMIT 16
    ");
    $prodStmt->execute([$tcat_id_int]);
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fallback if empty
    if (empty($products)) {
        $fallbackStmt = $pdo->query("
            SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo, p_total_view, is_top_sale, 1 as has_choice, 5.0 as avg_rating
            FROM tbl_product 
            WHERE p_is_active = 1 
            ORDER BY p_id DESC 
            LIMIT 8
        ");
        $products = $fallbackStmt ? $fallbackStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    return ['subcategories' => $subcategories, 'products' => $products, 'title' => $catTitle];
}

// ── Handle AJAX Request ───────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_category_data') {
    header('Content-Type: application/json; charset=utf-8');
    $reqCatId = trim($_GET['tcat_id'] ?? '2');
    $data = getCategoryData($pdo, $reqCatId, $currencySymbol);
    echo json_encode([
        'status' => 'success',
        'tcat_id' => $reqCatId,
        'title' => $data['title'],
        'subcategories' => $data['subcategories'],
        'products' => $data['products'],
        'currency' => $currencySymbol
    ]);
    exit;
}

// Fetch all Top Categories for the Left Rail
$tcatsStmt = $pdo->query("SELECT tcat_id, tcat_name FROM tbl_top_category ORDER BY tcat_order ASC, tcat_id ASC");
$allTopCategories = $tcatsStmt ? $tcatsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Initial category data for Server-Side Rendering
$initialData = getCategoryData($pdo, $active_cat_id, $currencySymbol);
$subcategories = $initialData['subcategories'];
$products = $initialData['products'];

// Cart item count
$cartCount = 0;
if (!empty($_SESSION['cart_p_qty'])) {
    foreach ($_SESSION['cart_p_qty'] as $qty) {
        $cartCount += (int)$qty;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Categories - <?php echo htmlspecialchars($settings['meta_title_home'] ?? 'ShopNext'); ?></title>
    <link rel="icon" type="image/png" href="assets/uploads/<?php echo htmlspecialchars($favicon); ?>">

    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/spa-skeleton.css?v=<?php echo time(); ?>">

    <script>
    if (window.innerWidth > 768 && !window.location.search.includes('mobile_mode=1')) {
        var targetId = "<?php echo (is_numeric($active_cat_id) && (int)$active_cat_id > 0) ? (int)$active_cat_id : 1; ?>";
        window.location.replace("product-category.php?id=" + targetId + "&type=top-category");
    }
    </script>

    <style>
        :root {
            --sn-yellow: #fab802;
            --sn-yellow-hover: #e5a700;
            --sn-yellow-light: #fffbeb;
            --sn-yellow-border: #fef08a;
            --sn-amber-dark: #b45309;
            --sn-dark: #111827;
            --sn-gray-50: #fafafa;
            --sn-gray-100: #f4f4f5;
            --sn-gray-200: #e4e4e7;
            --sn-gray-400: #a1a1aa;
            --sn-gray-600: #52525b;
            --sn-gray-800: #27272a;
            --sn-badge-red: #ef4444;
            --sn-price-red: #e11d48;
            --sn-rail-width: 90px;
            --sn-header-height: 52px;
            --sn-ticker-height: 32px;
            --sn-dock-height: 56px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            height: 100%;
            overflow: hidden !important;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #ffffff;
            color: var(--sn-dark);
            min-height: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden !important;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Viewport: 100% on Mobile, Clean Full Desktop on Desktop ────────── */
        .sn-cat-app-viewport {
            width: 100%;
            max-width: 100%;
            height: 100vh;
            height: 100dvh;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            position: relative;
            margin: 0 auto;
            overflow: hidden;
        }

        @media (min-width: 769px) {
            html, body {
                height: auto;
                overflow: auto !important;
            }
            body {
                background-color: #f8fafc;
                display: block;
                padding: 20px 0;
                overflow: auto !important;
            }
            .sn-cat-app-viewport {
                max-width: 1240px;
                min-height: 80vh;
                height: auto;
                border-radius: 16px;
                box-shadow: 0 4px 25px rgba(0, 0, 0, 0.06);
                border: 1px solid #e2e8f0;
                overflow: visible;
            }
        }

        /* ── 1. Top Search Header ────────────────────────────────────────── */
        .sn-cat-header {
            height: var(--sn-header-height);
            padding: 8px 12px;
            background: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 30;
            border-bottom: 1px solid #f1f1f3;
            width: 100%;
        }

        .sn-search-pill-form {
            flex: 1 1 auto;
            min-width: 0;
            height: 38px;
            background: #ffffff;
            border: 1.5px solid #27272a;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            padding: 0 4px 0 10px;
            position: relative;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .sn-search-pill-form:focus-within {
            border-color: var(--sn-yellow);
            box-shadow: 0 0 0 2px rgba(250, 184, 2, 0.25);
        }

        .sn-camera-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #18181b;
            padding: 0;
            flex-shrink: 0;
            transition: transform 0.15s;
        }

        .sn-camera-btn:active {
            transform: scale(0.92);
        }

        .sn-search-divider {
            width: 1px;
            height: 18px;
            background-color: #d4d4d8;
            margin: 0 6px;
            flex-shrink: 0;
        }

        .sn-search-input {
            flex: 1 1 0%;
            min-width: 0;
            width: 0;
            border: none;
            outline: none;
            background: transparent;
            font-size: 14px;
            color: #18181b;
            font-family: inherit;
            font-weight: 500;
        }

        .sn-search-input::placeholder {
            color: #52525b;
            font-weight: 500;
        }

        .sn-search-submit-btn {
            width: 30px;
            height: 30px;
            background: #18181b;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            cursor: pointer;
            flex-shrink: 0;
            transition: background-color 0.2s, transform 0.15s;
        }

        .sn-search-submit-btn:hover {
            background: var(--sn-yellow);
            color: #18181b;
        }

        .sn-search-submit-btn:active {
            transform: scale(0.92);
        }

        .sn-bell-btn {
            background: none;
            border: none;
            cursor: pointer;
            position: relative;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #18181b;
            flex-shrink: 0;
            text-decoration: none;
        }

        .sn-bell-badge {
            position: absolute;
            top: 1px;
            right: -2px;
            background: var(--sn-badge-red);
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            min-width: 18px;
            height: 18px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px;
            line-height: 1;
            border: 1.5px solid #ffffff;
        }

        /* ── 2. Sale Ticker Banner ───────────────────────────────────────── */
        .sn-sale-ticker {
            height: var(--sn-ticker-height);
            background: #f7f7f8;
            border-bottom: 1px solid #ebecee;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            font-size: 12px;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            z-index: 25;
            width: 100%;
        }

        .sn-ticker-brand {
            font-weight: 800;
            letter-spacing: -0.2px;
            color: #18181b;
        }

        .sn-ticker-countdown {
            color: #52525b;
            font-weight: 400;
        }

        /* ── 3. Master-Detail Two-Column Split Layout ────────────────────── */
        /* ── 3. Master-Detail Two-Column Split Layout ────────────────────── */
        .sn-cat-main-split {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            overflow: hidden;
            position: relative;
            background: #ffffff;
            width: 100%;
        }

        /* Left Rail: Top Categories */
        .sn-cat-left-rail {
            width: var(--sn-rail-width);
            height: 100%;
            background: #f4f4f6;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            border-right: 1px solid #eaeaea;
            flex-shrink: 0;
        }

        .sn-cat-left-rail::-webkit-scrollbar {
            display: none;
        }

        .sn-rail-item {
            width: 100%;
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 10px 6px;
            font-size: 12px;
            line-height: 1.25;
            color: #52525b;
            font-weight: 500;
            cursor: pointer;
            position: relative;
            background: #f4f4f6;
            transition: all 0.2s ease;
            user-select: none;
            text-decoration: none;
        }

        .sn-rail-item .sn-rail-indicator {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 26px;
            background: var(--sn-yellow);
            border-radius: 0 4px 4px 0;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .sn-rail-item.active {
            background: #ffffff;
            color: var(--sn-dark);
            font-weight: 700;
        }

        /* Active category title in yellow accent */
        .sn-rail-item.active span {
            color: #d97706;
            font-weight: 700;
        }

        .sn-rail-item.active .sn-rail-indicator {
            opacity: 1;
        }

        .sn-rail-item:active {
            background: #ededf0;
        }

        /* Right Panel: Content View */
        .sn-cat-right-panel {
            flex: 1;
            min-width: 0;
            height: 100%;
            background: #ffffff;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding: 10px 10px 90px 10px;
        }

        .sn-cat-right-panel::-webkit-scrollbar {
            display: none;
        }

        /* Subcategory Filter Pills */
        .sn-sub-filter-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
            padding-bottom: 8px;
            margin-bottom: 6px;
        }

        .sn-sub-filter-pills::-webkit-scrollbar {
            display: none;
        }

        .sn-sub-pill {
            font-size: 11.5px;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 9999px;
            background: #f4f4f6;
            color: #52525b;
            white-space: nowrap;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: all 0.2s;
            text-decoration: none;
        }

        .sn-sub-pill.active {
            border-color: var(--sn-yellow);
            color: var(--sn-amber-dark);
            background: var(--sn-yellow-light);
            font-weight: 700;
        }

        /* 3-Column Subcategories Visual Grid */
        .sn-subcategories-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px 8px;
            margin-top: 4px;
        }

        .sn-subcat-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease;
            min-width: 0;
        }

        .sn-subcat-card:active {
            transform: scale(0.96);
        }

        .sn-subcat-img-box {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 5px;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.03);
        }

        .sn-subcat-img-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .sn-subcat-name {
            font-size: 11px;
            line-height: 1.25;
            font-weight: 500;
            color: #18181b;
            text-align: center;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
            min-height: 27px;
        }

        /* "View More" 3-Dots Box */
        .sn-subcat-more-box {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #f1f2f4;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #71717a;
            margin-bottom: 5px;
            transition: background-color 0.2s;
        }

        .sn-subcat-card:hover .sn-subcat-more-box {
            background: var(--sn-yellow-light);
            color: var(--sn-amber-dark);
        }

        .sn-more-dots {
            display: flex;
            gap: 4px;
        }

        .sn-more-dots span {
            width: 5px;
            height: 5px;
            background: currentColor;
            border-radius: 50%;
        }

        /* ── 4. Featured Product Cards (2 Columns) ───────────────────────── */
        .sn-products-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .sn-cat-prod-card {
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            border: 1px solid #f1f1f3;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            min-width: 0;
            box-sizing: border-box;
        }

        .sn-cat-prod-card:active {
            transform: scale(0.97);
        }

        .sn-prod-img-box {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
        }

        .sn-prod-img-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .sn-prod-info {
            padding: 8px 6px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .sn-prod-title-wrap {
            margin-bottom: 4px;
        }

        .sn-choice-pill {
            background: var(--sn-yellow);
            color: #000000;
            font-size: 9.5px;
            font-weight: 800;
            padding: 1px 4.5px;
            border-radius: 3px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 3px;
            letter-spacing: -0.2px;
            line-height: 1.3;
        }

        .sn-prod-title {
            font-size: 11.5px;
            font-weight: 500;
            color: #18181b;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sn-prod-meta {
            font-size: 10.5px;
            color: #71717a;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 4px;
        }

        .sn-prod-star {
            color: #f59e0b;
        }

        .sn-prod-price {
            font-size: 14px;
            font-weight: 800;
            color: var(--sn-price-red);
            margin-top: auto;
            display: flex;
            align-items: baseline;
            gap: 3px;
        }

        .sn-price-currency {
            font-size: 11px;
            font-weight: 800;
        }

        /* ── 5. Floating Action: Feedback Pill ────────────────────────────── */
        .sn-feedback-fab {
            position: absolute;
            right: 12px;
            bottom: 68px;
            background: #ffffff;
            border: 1px solid #e4e4e7;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            border-radius: 9999px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            font-weight: 600;
            color: #3f3f46;
            cursor: pointer;
            z-index: 45;
            transition: all 0.2s ease;
        }

        .sn-feedback-fab:hover {
            border-color: var(--sn-yellow);
            color: #18181b;
            box-shadow: 0 6px 16px rgba(250, 184, 2, 0.25);
        }

        .sn-feedback-fab:active {
            transform: scale(0.95);
        }

        /* ── 6. Mobile Bottom Navigation Dock (Yellow Theme) ─────────────── */
        .sn-dock {
            height: var(--sn-dock-height);
            background: #ffffff;
            border-top: 1px solid #ebecee;
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 0 4px;
            position: relative;
            z-index: 50;
            flex-shrink: 0;
            width: 100%;
        }

        .sn-dock-item {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            text-decoration: none;
            color: #71717a;
            font-size: 10px;
            font-weight: 500;
            transition: color 0.15s;
            position: relative;
        }

        .sn-dock-icon-box {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sn-dock-item.active {
            color: #18181b;
            font-weight: 700;
        }

        .sn-dock-item.active svg {
            color: var(--sn-yellow);
            stroke: var(--sn-yellow);
        }

        .sn-dock-item.active span {
            color: #18181b;
        }

        .sn-dock-badge {
            position: absolute;
            top: -4px;
            right: -8px;
            background: var(--sn-badge-red);
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            min-width: 15px;
            height: 15px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 2px;
            border: 1px solid #ffffff;
        }

        /* ── Feedback Modal ──────────────────────────────────────────────── */
        .sn-modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .sn-modal-backdrop.open {
            display: flex;
        }

        .sn-modal-box {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 360px;
            padding: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.2s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .sn-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .sn-modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #18181b;
        }

        .sn-modal-close {
            background: none;
            border: none;
            font-size: 18px;
            color: #71717a;
            cursor: pointer;
        }

        .sn-feedback-textarea {
            width: 100%;
            height: 90px;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            padding: 10px;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            resize: none;
            margin-bottom: 14px;
        }

        .sn-feedback-textarea:focus {
            border-color: var(--sn-yellow);
        }

        .sn-feedback-submit {
            width: 100%;
            height: 40px;
            background: var(--sn-yellow);
            color: #18181b;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s;
        }

        .sn-feedback-submit:hover {
            background: var(--sn-yellow-hover);
        }
    </style>
</head>
<body>
<div id="sn-page-container" class="sn-page-container">
<div class="sn-cat-app-viewport">

    <!-- ── 1. Header with Search Pill & Bell ─────────────────────────────── -->
    <header class="sn-cat-header">
        <form action="search-result.php" method="get" class="sn-search-pill-form" id="catSearchForm">
            <!-- Camera / Photo Search Icon -->
            <button type="button" class="sn-camera-btn" id="catCameraBtn" title="Photo Search">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
                    <circle cx="12" cy="13" r="3"></circle>
                </svg>
            </button>
            <input type="file" id="catCameraInput" accept="image/*" capture="environment" style="display:none;">

            <div class="sn-search-divider"></div>

            <!-- Search Query Input -->
            <input type="text" name="search_text" class="sn-search-input" id="catSearchInput" placeholder="Sim800l" value="<?php echo htmlspecialchars($_GET['search_text'] ?? ''); ?>">
            <button type="button" class="sn-search-clear-btn" id="catSearchClearBtn" title="Clear" aria-label="Clear search" style="display:none; background: #e2e8f0; border: none; border-radius: 50%; width: 20px; height: 20px; min-width: 20px; min-height: 20px; align-items: center; justify-content: center; cursor: pointer; color: #64748b; margin-right: 4px; padding: 0;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <!-- Search Submit Button -->
            <button type="submit" class="sn-search-submit-btn" aria-label="Search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
        </form>

        <!-- Notification Bell with 99 badge -->
        <a href="deals.php" class="sn-bell-btn" title="Notifications & Deals">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span class="sn-bell-badge">99</span>
        </a>
    </header>

    <!-- ── 2. Promotion Sale Ticker ──────────────────────────────────────── -->
    <div class="sn-sale-ticker">
        <span class="sn-ticker-brand">PARTY READY SALE</span>
        <span class="sn-ticker-countdown">Ends : Oct 8, 12:59 GMT+06:00</span>
    </div>

    <!-- ── 3. Master-Detail Two-Column Split Area ───────────────────────── -->
    <main class="sn-cat-main-split">

        <!-- Left Vertical Rail: Categories -->
        <aside class="sn-cat-left-rail" id="catLeftRail">
            <!-- "For you" (Top Tab) -->
            <div class="sn-rail-item <?php echo ($active_cat_id === 'foryou') ? 'active' : ''; ?>" data-tcat-id="foryou">
                <div class="sn-rail-indicator"></div>
                <span>For you</span>
            </div>

            <!-- Dynamic Top Categories from Database -->
            <?php foreach ($allTopCategories as $tcat): 
                if ($tcat['tcat_id'] == 1) continue; // Skip generic placeholder if present
                $isActive = ($active_cat_id == $tcat['tcat_id']) ? 'active' : '';
            ?>
                <div class="sn-rail-item <?php echo $isActive; ?>" data-tcat-id="<?php echo $tcat['tcat_id']; ?>">
                    <div class="sn-rail-indicator"></div>
                    <span><?php echo htmlspecialchars($tcat['tcat_name']); ?></span>
                </div>
            <?php endforeach; ?>
        </aside>

        <!-- Right Content Panel -->
        <section class="sn-cat-right-panel" id="catRightPanel">

            <!-- Subcategory Filter Pills -->
            <div class="sn-sub-filter-pills" id="subFilterPills">
                <span class="sn-sub-pill active">For you</span>
                <span class="sn-sub-pill">Brand</span>
                <span class="sn-sub-pill">Trending</span>
                <span class="sn-sub-pill">New In</span>
            </div>

            <!-- 3-Column Subcategories Visual Grid -->
            <div class="sn-subcategories-grid" id="subcategoriesGrid">
                <?php 
                $count = 0;
                foreach ($subcategories as $sub): 
                    $count++;
                    if ($count > 11) break; // Keep first 11 items to leave 12th for "View More"
                    $photoPath = (strpos($sub['photo'], 'http') === 0) ? $sub['photo'] : 'assets/uploads/' . $sub['photo'];
                ?>
                    <a href="<?php echo htmlspecialchars($sub['url']); ?>" class="sn-subcat-card">
                        <div class="sn-subcat-img-box">
                            <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="<?php echo htmlspecialchars($sub['name']); ?>" loading="lazy" onerror="this.src='assets/uploads/cat_all.jpg';">
                        </div>
                        <div class="sn-subcat-name"><?php echo htmlspecialchars($sub['name']); ?></div>
                    </a>
                <?php endforeach; ?>

                <!-- 12th Card: View More -->
                <a href="product-category.php?id=<?php echo urlencode($active_cat_id); ?>&type=top-category" class="sn-subcat-card" id="catViewMoreCard">
                    <div class="sn-subcat-more-box">
                        <div class="sn-more-dots">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                    <div class="sn-subcat-name">View More</div>
                </a>
            </div>

            <!-- 2-Column Product Cards Grid (No heading to match mockup directly) -->
            <div class="sn-products-grid" id="productsGrid">
                <?php foreach ($products as $prod): 
                    $photoPath = (strpos($prod['p_featured_photo'], 'http') === 0) ? $prod['p_featured_photo'] : 'assets/uploads/' . $prod['p_featured_photo'];
                    $rating = !empty($prod['avg_rating']) ? number_format((float)$prod['avg_rating'], 1) : '5.0';
                    $views = !empty($prod['p_total_view']) ? $prod['p_total_view'] : 31;
                    $hasChoice = !empty($prod['has_choice']);
                ?>
                    <a href="product.php?id=<?php echo $prod['p_id']; ?>" class="sn-cat-prod-card">
                        <div class="sn-prod-img-box">
                            <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="<?php echo htmlspecialchars($prod['p_name']); ?>" loading="lazy" onerror="this.src='assets/uploads/cat_all.jpg';">
                        </div>
                        <div class="sn-prod-info">
                            <div class="sn-prod-title-wrap">
                                <?php if ($hasChoice): ?>
                                    <span class="sn-choice-pill">Choice</span>
                                <?php endif; ?>
                                <span class="sn-prod-title"><?php echo htmlspecialchars($prod['p_name']); ?></span>
                            </div>
                            <div class="sn-prod-meta">
                                <span><?php echo $views; ?> sold</span>
                                <span>|</span>
                                <span class="sn-prod-star">★</span>
                                <span><?php echo $rating; ?></span>
                            </div>
                            <div class="sn-prod-price">
                                <span class="sn-price-currency"><?php echo htmlspecialchars($currencySymbol); ?></span>
                                <span><?php echo number_format((float)$prod['p_current_price'], 2); ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

        </section>

    </main>

    <!-- ── 4. Floating Action: Feedback Pill ─────────────────────────────── -->
    <button class="sn-feedback-fab" id="openFeedbackModalBtn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
        </svg>
        <span>Feedback</span>
    </button>

    <!-- ── 5. Mobile Bottom Navigation Dock ──────────────────────────────── -->
    <nav class="sn-dock">
        <!-- 1. Home -->
        <a href="<?php echo BASE_URL; ?>" class="sn-dock-item active">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </div>
            <span>Home</span>
        </a>

        <!-- 2. Deals -->
        <a href="<?php echo BASE_URL; ?>deals.php" class="sn-dock-item">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <span>Deals</span>
        </a>

        <!-- 3. Messages / AI Support -->
        <a href="deals.php" class="sn-dock-item">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span class="sn-dock-badge">5</span>
            </div>
            <span>Messages</span>
        </a>

        <!-- 4. Cart -->
        <a href="cart.php" class="sn-dock-item">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <?php if ($cartCount > 0): ?>
                    <span class="sn-dock-badge"><?php echo $cartCount; ?></span>
                <?php endif; ?>
            </div>
            <span>Cart</span>
        </a>

        <!-- 5. Account -->
        <?php if (isset($_SESSION['customer'])): ?>
            <a href="dashboard.php" class="sn-dock-item">
                <div class="sn-dock-icon-box">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <span>Account</span>
            </a>
        <?php else: ?>
            <a href="login.php" class="sn-dock-item">
                <div class="sn-dock-icon-box">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <span>Account</span>
            </a>
        <?php endif; ?>
    </nav>

</div>

<!-- ── Feedback Modal Dialog ─────────────────────────────────────────────── -->
<div class="sn-modal-backdrop" id="feedbackModal">
    <div class="sn-modal-box">
        <div class="sn-modal-header">
            <span class="sn-modal-title">Help us improve</span>
            <button type="button" class="sn-modal-close" id="closeFeedbackModalBtn">&times;</button>
        </div>
        <p style="font-size:12px; color:#71717a; margin-bottom:12px;">What can we do to make your shopping experience even better?</p>
        <textarea class="sn-feedback-textarea" id="feedbackInput" placeholder="Tell us your feedback or thoughts..."></textarea>
        <button type="button" class="sn-feedback-submit" id="submitFeedbackBtn">Submit Feedback</button>
    </div>
</div>

<script>
(function() {
    // 1. Camera / Photo Search click
    const camBtn = document.getElementById('catCameraBtn');
    const camInput = document.getElementById('catCameraInput');
    if (camBtn && camInput) {
        camBtn.addEventListener('click', () => camInput.click());
        camInput.addEventListener('change', () => {
            if (camInput.files && camInput.files[0]) {
                const searchInput = document.getElementById('catSearchInput');
                searchInput.value = camInput.files[0].name.replace(/\.[^/.]+$/, "");
                document.getElementById('catSearchForm').submit();
            }
        });
    }

    // Categories Search Clear Button
    const catInp = document.getElementById('catSearchInput');
    const catClr = document.getElementById('catSearchClearBtn');
    if (catInp && catClr) {
        const updateCatClr = () => {
            catClr.style.display = (catInp.value && catInp.value.trim().length > 0) ? 'inline-flex' : 'none';
        };
        catInp.addEventListener('input', updateCatClr);
        catInp.addEventListener('keyup', updateCatClr);
        catClr.addEventListener('click', () => {
            catInp.value = '';
            updateCatClr();
            catInp.focus();
        });
        updateCatClr();
    }

    // 2. Feedback Modal
    const modal = document.getElementById('feedbackModal');
    const openBtn = document.getElementById('openFeedbackModalBtn');
    const closeBtn = document.getElementById('closeFeedbackModalBtn');
    const submitBtn = document.getElementById('submitFeedbackBtn');
    const feedbackInput = document.getElementById('feedbackInput');

    if (openBtn && modal) {
        openBtn.addEventListener('click', () => {
            modal.classList.add('open');
            feedbackInput.focus();
        });
    }
    if (closeBtn && modal) {
        closeBtn.addEventListener('click', () => modal.classList.remove('open'));
    }
    if (submitBtn && modal) {
        submitBtn.addEventListener('click', () => {
            const val = feedbackInput.value.trim();
            if (!val) {
                alert('Please enter your feedback before submitting.');
                return;
            }
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;
            setTimeout(() => {
                alert('Thank you! Your feedback has been received.');
                modal.classList.remove('open');
                feedbackInput.value = '';
                submitBtn.textContent = 'Submit Feedback';
                submitBtn.disabled = false;
            }, 500);
        });
    }

    // 3. Subcategory Filter Pills Click
    document.querySelectorAll('.sn-sub-pill').forEach(pill => {
        pill.addEventListener('click', function() {
            document.querySelectorAll('.sn-sub-pill').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // 4. Client-side Category Switcher (AJAX with Caching)
    const categoryCache = {};
    const railItems = document.querySelectorAll('.sn-rail-item');
    const subGrid = document.getElementById('subcategoriesGrid');
    const prodGrid = document.getElementById('productsGrid');
    const rightPanel = document.getElementById('catRightPanel');
    const viewMoreCard = document.getElementById('catViewMoreCard');

    railItems.forEach(item => {
        item.addEventListener('click', function() {
            const tcatId = this.getAttribute('data-tcat-id');
            if (!tcatId) return;

            // Update active state in left rail
            railItems.forEach(r => r.classList.remove('active'));
            this.classList.add('active');

            // Scroll item into comfortable view
            this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            // Update URL without page reload
            try {
                const newUrl = window.location.pathname + '?cat_id=' + encodeURIComponent(tcatId);
                window.history.pushState({ cat_id: tcatId }, '', newUrl);
            } catch(e) {}

            // Update "View More" link target
            if (viewMoreCard) {
                viewMoreCard.href = 'product-category.php?id=' + encodeURIComponent(tcatId) + '&type=top-category';
            }

            // Check if already cached
            if (categoryCache[tcatId]) {
                renderCategoryContent(categoryCache[tcatId]);
                return;
            }

            // Show subtle shimmer or loader while fetching
            subGrid.style.opacity = '0.4';
            prodGrid.style.opacity = '0.4';

            fetch('categories.php?action=get_category_data&tcat_id=' + encodeURIComponent(tcatId))
                .then(res => res.json())
                .then(data => {
                    subGrid.style.opacity = '1';
                    prodGrid.style.opacity = '1';
                    if (data.status === 'success') {
                        categoryCache[tcatId] = data;
                        renderCategoryContent(data);
                        rightPanel.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                })
                .catch(err => {
                    subGrid.style.opacity = '1';
                    prodGrid.style.opacity = '1';
                    console.error('Category fetch error:', err);
                });
        });
    });

    function renderCategoryContent(data) {
        const subs = data.subcategories || [];
        const prods = data.products || [];
        const currency = data.currency || 'BDT';

        // 1. Render Subcategories
        let subHtml = '';
        let count = 0;
        subs.forEach(s => {
            count++;
            if (count > 11) return;
            const sPhoto = (s.photo && typeof s.photo === 'string') ? s.photo : '';
            const photo = sPhoto.startsWith('http') ? sPhoto : (sPhoto ? 'assets/uploads/' + sPhoto : 'assets/uploads/cat_all.jpg');
            subHtml += `
                <a href="${escapeHtml(s.url)}" class="sn-subcat-card">
                    <div class="sn-subcat-img-box">
                        <img src="${escapeHtml(photo)}" alt="${escapeHtml(s.name)}" loading="lazy" onerror="this.src='assets/uploads/cat_all.jpg';">
                    </div>
                    <div class="sn-subcat-name">${escapeHtml(s.name)}</div>
                </a>
            `;
        });

        // Add 12th "View More" card
        subHtml += `
            <a href="product-category.php?id=${encodeURIComponent(data.tcat_id)}&type=top-category" class="sn-subcat-card">
                <div class="sn-subcat-more-box">
                    <div class="sn-more-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
                <div class="sn-subcat-name">View More</div>
            </a>
        `;
        subGrid.innerHTML = subHtml;

        // 2. Render Products
        let prodHtml = '';
        prods.forEach(p => {
            const pPhoto = (p.p_featured_photo && typeof p.p_featured_photo === 'string') ? p.p_featured_photo : '';
            const photo = pPhoto.startsWith('http') ? pPhoto : (pPhoto ? 'assets/uploads/' + pPhoto : 'assets/uploads/cat_all.jpg');
            const rating = p.avg_rating ? parseFloat(p.avg_rating).toFixed(1) : '5.0';
            const views = p.p_total_view || 31;
            const price = parseFloat(p.p_current_price || 0).toFixed(2);
            const hasChoice = Boolean(p.has_choice);

            prodHtml += `
                <a href="product.php?id=${p.p_id}" class="sn-cat-prod-card">
                    <div class="sn-prod-img-box">
                        <img src="${escapeHtml(photo)}" alt="${escapeHtml(p.p_name)}" loading="lazy" onerror="this.src='assets/uploads/cat_all.jpg';">
                    </div>
                    <div class="sn-prod-info">
                        <div class="sn-prod-title-wrap">
                            ${hasChoice ? '<span class="sn-choice-pill">Choice</span>' : ''}
                            <span class="sn-prod-title">${escapeHtml(p.p_name)}</span>
                        </div>
                        <div class="sn-prod-meta">
                            <span>${views} sold</span>
                            <span>|</span>
                            <span class="sn-prod-star">★</span>
                            <span>${rating}</span>
                        </div>
                        <div class="sn-prod-price">
                            <span class="sn-price-currency">${escapeHtml(currency)}</span>
                            <span>${price}</span>
                        </div>
                    </div>
                </a>
            `;
        });
        prodGrid.innerHTML = prodHtml;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</div><!-- /#sn-page-container -->
<script src="assets/js/spa-navigation.js?v=<?php echo time(); ?>"></script>
</body>
</html>
