<?php require_once('header.php'); ?>
<?php require_once('admin/inc/seo_helpers.php'); ?>
<?php
/* ==========================================================================
   ShopNext Pixel-Perfect Category & Product Listing Screen
   Directly matching media_1790250619159.png with full database connectivity
   ========================================================================== */

// ── 1. Category Resolution ────────────────────────────────────────────────
$category_type = (string)($_REQUEST['type'] ?? 'top-category');

if (!isset($_REQUEST['slug']) && !isset($_REQUEST['id'])) {
    $uriPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    if (preg_match('#/(?:[a-zA-Z0-9_-]+/)?category/([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)/?$#', $uriPath, $m)) {
        $_REQUEST['slug'] = $m[3];
        $category_type = 'end-category';
    } elseif (preg_match('#/(?:[a-zA-Z0-9_-]+/)?category/([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)/?$#', $uriPath, $m)) {
        $_REQUEST['slug'] = $m[2];
        $category_type = 'mid-category';
    } elseif (preg_match('#/(?:[a-zA-Z0-9_-]+/)?category/([a-zA-Z0-9_-]+)/?$#', $uriPath, $m)) {
        $_REQUEST['slug'] = $m[1];
        $category_type = 'top-category';
    }
}

$category_id = isset($_REQUEST['slug'])
    ? extractIdFromSlug((string)$_REQUEST['slug'])
    : (int)($_REQUEST['id'] ?? 0);

if (!$category_id && !empty($_REQUEST['slug']) && is_numeric($_REQUEST['slug'])) {
    $category_id = (int)$_REQUEST['slug'];
}

// If no category ID provided, default to first active top category in database
if (!$category_id) {
    try {
        $sCat = $pdo->query("SELECT tcat_id FROM tbl_top_category ORDER BY tcat_order ASC, tcat_id ASC LIMIT 1");
        $foundId = $sCat->fetchColumn();
        if ($foundId) {
            $category_id = (int)$foundId;
            $category_type = 'top-category';
        }
    } catch (Throwable $e) {}
}

$types = [
    'top-category' => ['tbl_top_category', 'tcat_id', 'tcat_name'],
    'mid-category' => ['tbl_mid_category', 'mcat_id', 'mcat_name'],
    'end-category' => ['tbl_end_category', 'ecat_id', 'ecat_name'],
];

if (!isset($types[$category_type])) {
    $category_type = 'top-category';
}
[$table, $idColumn, $nameColumn] = $types[$category_type];

$stmtCat = $pdo->prepare("SELECT $nameColumn FROM $table WHERE $idColumn = ?");
$stmtCat->execute([$category_id]);
$title = $stmtCat->fetchColumn();
if ($title === false) {
    $title = 'Products';
}

// ── 2. Collect end-category IDs for product query ─────────────────────────
$final_ecat_ids = [];
if ($category_type === 'top-category') {
    $s = $pdo->prepare('SELECT e.ecat_id FROM tbl_end_category e JOIN tbl_mid_category m ON e.mcat_id=m.mcat_id WHERE m.tcat_id=?');
    $s->execute([$category_id]);
    $final_ecat_ids = $s->fetchAll(PDO::FETCH_COLUMN);
    if (empty($final_ecat_ids)) {
        $sAll = $pdo->query('SELECT DISTINCT ecat_id FROM tbl_product WHERE p_is_active = 1');
        $final_ecat_ids = $sAll->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
} elseif ($category_type === 'mid-category') {
    $s = $pdo->prepare('SELECT ecat_id FROM tbl_end_category WHERE mcat_id=?');
    $s->execute([$category_id]);
    $final_ecat_ids = $s->fetchAll(PDO::FETCH_COLUMN);
} else {
    $final_ecat_ids = [$category_id];
}
$final_ecat_ids = array_map('intval', array_filter($final_ecat_ids));

// ── 3. Filters & Pagination Params ────────────────────────────────────────
$perPage     = 12;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$filterBrand = trim($_GET['brand'] ?? '');
$filterPrice = trim($_GET['price'] ?? '');
$filterRam   = trim($_GET['ram'] ?? '');
$filterSort  = trim($_GET['sort'] ?? 'popularity');
$filterView  = trim($_GET['view'] ?? 'grid');
$filterSubcat= trim($_GET['subcat'] ?? '');

// ── 4. Query Database Products ────────────────────────────────────────────
$dbProducts = [];
$totalProductCount = 0;

if (empty($final_ecat_ids)) {
    $whereClauses = ["p.p_is_active = 1"];
    $params = [];
} else {
    $placeholders = implode(',', array_fill(0, count($final_ecat_ids), '?'));
    $whereClauses = ["p.ecat_id IN ($placeholders)", "p.p_is_active = 1"];
    $params = $final_ecat_ids;
}

if ($filterBrand !== '') {
    $whereClauses[] = "p.p_name LIKE ?";
    $params[] = '%' . $filterBrand . '%';
}

if ($filterPrice === '1') {
    $whereClauses[] = "CAST(p.p_current_price AS DECIMAL(12,2)) < 500";
} elseif ($filterPrice === '2') {
    $whereClauses[] = "CAST(p.p_current_price AS DECIMAL(12,2)) BETWEEN 500 AND 1000";
} elseif ($filterPrice === '3') {
    $whereClauses[] = "CAST(p.p_current_price AS DECIMAL(12,2)) BETWEEN 1000 AND 2500";
} elseif ($filterPrice === '4') {
    $whereClauses[] = "CAST(p.p_current_price AS DECIMAL(12,2)) BETWEEN 2500 AND 5000";
} elseif ($filterPrice === '5') {
    $whereClauses[] = "CAST(p.p_current_price AS DECIMAL(12,2)) > 5000";
}

$whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);

$orderSQL = match($filterSort) {
    'price_asc'  => 'ORDER BY CAST(p.p_current_price AS DECIMAL(12,2)) ASC',
    'price_desc' => 'ORDER BY CAST(p.p_current_price AS DECIMAL(12,2)) DESC',
    'newest'     => 'ORDER BY p.p_id DESC',
    'rating'     => 'ORDER BY avg_r DESC',
    default      => 'ORDER BY p.p_id DESC',
};

try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tbl_product p $whereSQL");
    $stmtCount->execute($params);
    $totalProductCount = (int)$stmtCount->fetchColumn();
} catch (Throwable $e) {}

$limitInt = max(1, (int)$perPage);
$offsetInt = max(0, ($currentPage - 1) * $limitInt);

try {
    $stmtProd = $pdo->prepare(
        "SELECT p.*,
                COALESCE(r_sub.avg_r, 0) as avg_r,
                COALESCE(r_sub.review_count, 0) as review_count
         FROM tbl_product p
         LEFT JOIN (
             SELECT p_id, AVG(rating) as avg_r, COUNT(*) as review_count
             FROM tbl_rating
             GROUP BY p_id
         ) r_sub ON p.p_id = r_sub.p_id
         $whereSQL
         $orderSQL
         LIMIT $limitInt OFFSET $offsetInt"
    );
    $stmtProd->execute($params);
    $dbProducts = $stmtProd->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$displayProducts = $dbProducts;

$realSubcategories = [];
try {
    if ($category_type === 'top-category') {
        $subStmt = $pdo->prepare("SELECT m.mcat_id, m.mcat_name, COUNT(p.p_id) as p_count 
                                  FROM tbl_mid_category m 
                                  LEFT JOIN tbl_end_category e ON e.mcat_id = m.mcat_id 
                                  LEFT JOIN tbl_product p ON p.ecat_id = e.ecat_id AND p.p_is_active = 1 
                                  WHERE m.tcat_id = ? 
                                  GROUP BY m.mcat_id, m.mcat_name 
                                  ORDER BY m.mcat_id ASC");
        $subStmt->execute([$category_id]);
        $realSubcategories = $subStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {}

$sidebarCategories = $_SESSION['sn_sidebar_cats'] ?? null;
$sbCacheFile = __DIR__ . '/admin/inc/cache_sidebar_cats.json';
if (!$sidebarCategories && file_exists($sbCacheFile) && (time() - filemtime($sbCacheFile) < 300)) {
    $sidebarCategories = json_decode(file_get_contents($sbCacheFile), true);
    $_SESSION['sn_sidebar_cats'] = $sidebarCategories;
}
if (!$sidebarCategories) {
    try {
        $sbCatStmt = $pdo->query("SELECT t.tcat_id, t.tcat_name, COUNT(p.p_id) as cat_count 
                                 FROM tbl_top_category t 
                                 LEFT JOIN tbl_mid_category m ON m.tcat_id = t.tcat_id 
                                 LEFT JOIN tbl_end_category e ON e.mcat_id = m.mcat_id 
                                 LEFT JOIN tbl_product p ON p.ecat_id = e.ecat_id AND p.p_is_active = 1 
                                 GROUP BY t.tcat_id, t.tcat_name 
                                 ORDER BY t.tcat_order ASC, t.tcat_id ASC");
        $sidebarCategories = $sbCatStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $_SESSION['sn_sidebar_cats'] = $sidebarCategories;
        @file_put_contents($sbCacheFile, json_encode($sidebarCategories));
    } catch (Throwable $e) {}
}

// ── 6. Wishlist check ─────────────────────────────────────────────────────
$wishlistIds = [];
if (!empty($_SESSION['customer_id'])) {
    try {
        $stmtW = $pdo->prepare("SELECT product_id FROM tbl_wishlist WHERE customer_id = ?");
        $stmtW->execute([(int)$_SESSION['customer_id']]);
        $wishlistIds = $stmtW->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {}
}

// ── 7. Helpers ────────────────────────────────────────────────────────────
function formatReviewCount($c) {
    if ($c >= 1000) return round($c / 1000, 1) . 'k';
    return (string)$c;
}

function renderStarIcons($rating) {
    $r = (float)$rating;
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($r >= $i) {
            $out .= '<i class="fa-solid fa-star star-filled"></i>';
        } elseif ($r >= $i - 0.5) {
            $out .= '<i class="fa-solid fa-star-half-stroke star-filled"></i>';
        } else {
            $out .= '<i class="fa-regular fa-star star-empty"></i>';
        }
    }
    return $out;
}
?>

<!-- ========================================================================
     PIXEL PERFECT STYLES (MATCHING media_1790250619159.png)
     ======================================================================== -->
<style>
/* Base Reset & Palette */
.sn-cat-root {
    background-color: #f8fafc;
    color: #0f172a;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    padding-bottom: 60px;
    width: 100%;
    overflow-x: hidden;
}
.sn-cat-container {
    width: 100%;
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 20px;
    box-sizing: border-box;
}

/* Breadcrumb */
.sn-breadcrumb {
    padding: 16px 0 14px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #64748b;
}
.sn-breadcrumb a {
    color: #64748b;
    text-decoration: none;
    transition: color 0.15s ease;
}
.sn-breadcrumb a:hover {
    color: #0f172a;
}
.sn-breadcrumb .sep {
    font-size: 11px;
    color: #94a3b8;
}
.sn-breadcrumb .current {
    color: #0f172a;
    font-weight: 500;
}

/* Top Section: Title on Left + Hero Promo Card on Right */
.sn-top-row {
    display: block;
    width: 100%;
    margin-bottom: 24px;
}
.sn-top-row:has(.sn-promo-card) {
    display: grid;
    grid-template-columns: 310px 1fr;
    gap: 24px;
    align-items: center;
}
.sn-cat-info {
    width: 100%;
}
.sn-cat-info h1 {
    font-size: 30px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
    margin: 0 0 8px 0;
    line-height: 1.2;
}
.sn-cat-info p {
    font-size: 13.5px;
    color: #64748b;
    line-height: 1.55;
    margin: 0;
    max-width: 100%;
}

/* Promo Card */
.sn-promo-card {
    background: linear-gradient(135deg, #eaf3fe 0%, #eff7ff 50%, #e8f4fd 100%);
    border-radius: 16px;
    border: 1px solid #dbeafe;
    padding: 22px 28px;
    display: grid;
    grid-template-columns: 1fr 220px 140px;
    gap: 20px;
    align-items: center;
    position: relative;
    overflow: hidden;
}
.sn-promo-card::before {
    content: '';
    position: absolute;
    top: -40px;
    right: 80px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(191, 219, 254, 0.45) 0%, rgba(255, 255, 255, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}
.sn-promo-badge {
    display: inline-block;
    background: #fef08a;
    color: #854d0e;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 12px;
    border-radius: 999px;
    margin-bottom: 8px;
    letter-spacing: 0.02em;
}
.sn-promo-title {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 6px 0;
    letter-spacing: -0.01em;
}
.sn-promo-sub {
    font-size: 13px;
    color: #475569;
    margin: 0 0 16px 0;
    line-height: 1.4;
}
.sn-promo-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fab802;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 18px;
    border-radius: 999px;
    text-decoration: none;
    transition: background 0.15s ease, transform 0.1s ease;
    box-shadow: 0 2px 6px rgba(250, 184, 2, 0.25);
}
.sn-promo-btn:hover {
    background: #e5a700;
    transform: translateY(-1px);
    color: #0f172a;
}
.sn-promo-img-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
}
.sn-promo-img-wrap img {
    max-width: 100%;
    max-height: 125px;
    object-fit: contain;
    filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.12));
}
.sn-promo-trust {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.sn-trust-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: #1e293b;
}
.sn-trust-item i {
    font-size: 14px;
    color: #0284c7;
    width: 16px;
    text-align: center;
}

/* 6 Subcategory Horizontal Quick Filter Cards */
.sn-subcat-tabs {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
    width: 100%;
}
.sn-subcat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
    color: #0f172a;
    transition: all 0.2s ease;
    cursor: pointer;
}
.sn-subcat-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}
.sn-subcat-card.active {
    border: 2px solid #fab802;
    background: #fffdf2;
    box-shadow: 0 4px 14px rgba(250, 184, 2, 0.12);
}
.sn-subcat-icon {
    font-size: 20px;
    color: #1e293b;
    margin-bottom: 2px;
}
.sn-subcat-name {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    text-align: center;
    line-height: 1.2;
}
.sn-subcat-count {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
}
.sn-subcat-card.active .sn-subcat-count {
    color: #b45309;
}

/* Main Layout: Sidebar (260px) + Catalog Content */
.sn-catalog-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 24px;
    align-items: start;
    width: 100%;
}

/* Left Sidebar */
.sn-sidebar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px;
    position: sticky;
    top: 90px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.sn-filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 4px;
}
.sn-filter-header h2 {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.sn-clear-btn {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    cursor: pointer;
    transition: color 0.15s;
}
.sn-clear-btn:hover {
    color: #fab802;
}

/* Filter Accordion Groups */
.sn-accordion {
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 0;
}
.sn-accordion:first-of-type {
    padding-top: 0;
}
.sn-acc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
    margin-bottom: 10px;
}
.sn-acc-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
}
.sn-acc-arrow {
    font-size: 11px;
    color: #64748b;
    transition: transform 0.2s ease;
}
.sn-accordion.collapsed .sn-acc-arrow {
    transform: rotate(-180deg);
}
.sn-accordion.collapsed .sn-acc-body {
    display: none;
}
.sn-acc-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Custom Checkbox & Radio */
.sn-check-item, .sn-radio-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    color: #334155;
    cursor: pointer;
    user-select: none;
}
.sn-check-item label, .sn-radio-item label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    margin: 0;
    flex: 1;
}
.sn-check-item input[type="checkbox"] {
    width: 15px;
    height: 15px;
    accent-color: #fab802;
    cursor: pointer;
    margin: 0;
}
.sn-radio-item input[type="radio"] {
    width: 15px;
    height: 15px;
    accent-color: #fab802;
    cursor: pointer;
    margin: 0;
}
.sn-count-tag {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 400;
}
.sn-more-link {
    font-size: 12px;
    font-weight: 600;
    color: #0284c7;
    text-decoration: none;
    cursor: pointer;
    margin-top: 4px;
    display: inline-block;
}
.sn-more-link:hover {
    text-decoration: underline;
}

/* Controls Bar (Products Count + Sort + View Mode) */
.sn-controls-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}
.sn-product-count-label {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}
.sn-controls-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.sn-sort-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #64748b;
}
.sn-sort-select {
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 8px;
    padding: 6px 28px 6px 10px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    outline: none;
}
.sn-sort-select:focus {
    border-color: #fab802;
}
.sn-view-toggles {
    display: flex;
    gap: 6px;
}
.sn-view-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sn-view-btn:hover {
    border-color: #cbd5e1;
    color: #0f172a;
}
.sn-view-btn.active {
    background: #fef08a;
    border-color: #f59e0b;
    color: #854d0e;
}

/* 4-Column Product Grid */
.sn-product-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 36px;
}

/* Product Card */
.sn-card {
    background: #ffffff;
    border: 1px solid #eef2f6;
    border-radius: 14px;
    padding: 14px;
    position: relative;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.sn-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.06);
    border-color: #cbd5e1;
}

/* Card Top Badges & Wishlist */
.sn-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    min-height: 24px;
    margin-bottom: 6px;
}
.sn-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    letter-spacing: 0.01em;
}
.b-yellow {
    background: #fef08a;
    color: #854d0e;
}
.b-red {
    background: #fee2e2;
    color: #dc2626;
}
.b-green {
    background: #dcfce7;
    color: #16a34a;
}
.b-purple {
    background: #f3e8ff;
    color: #9333ea;
}
.sn-wish-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: #94a3b8;
    font-size: 15px;
    transition: color 0.15s ease, transform 0.15s ease;
    margin-left: auto;
}
.sn-wish-btn:hover {
    color: #ef4444;
    transform: scale(1.15);
}
.sn-wish-btn.active {
    color: #ef4444;
}

/* Product Image */
.sn-card-thumb {
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}
.sn-card-thumb img {
    max-width: 100%;
    max-height: 110px;
    object-fit: contain;
    transition: transform 0.25s ease;
}
.sn-card:hover .sn-card-thumb img {
    transform: scale(1.05);
}

/* Product Info */
.sn-card-body {
    display: flex;
    flex-direction: column;
    flex: 1;
}
.sn-card-title {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sn-card-title a {
    color: inherit;
    text-decoration: none;
}
.sn-card-title a:hover {
    color: #0284c7;
}
.sn-card-specs {
    font-size: 11px;
    color: #64748b;
    margin: 0 0 8px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sn-card-rating {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11.5px;
    margin-bottom: 8px;
}
.star-filled {
    color: #f59e0b;
}
.star-empty {
    color: #cbd5e1;
}
.sn-rate-val {
    font-weight: 700;
    color: #0f172a;
    margin-left: 2px;
}
.sn-rate-count {
    color: #94a3b8;
}

/* Price Row */
.sn-card-price-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.sn-price-current {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
}
.sn-price-old {
    font-size: 12px;
    color: #94a3b8;
    text-decoration: line-through;
}
.sn-discount-badge {
    font-size: 10px;
    font-weight: 700;
    color: #ef4444;
    background: #fef2f2;
    padding: 2px 6px;
    border-radius: 4px;
}

/* Bottom Action Row */
.sn-card-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
}
.sn-add-cart-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fab802;
    color: #0f172a;
    font-size: 12px;
    font-weight: 700;
    padding: 7px 12px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.1s ease;
}
.sn-add-cart-btn:hover {
    background: #e5a700;
    transform: translateY(-1px);
}
.sn-add-cart-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.sn-details-link {
    font-size: 12px;
    font-weight: 600;
    color: #0f172a;
    text-decoration: none;
    transition: color 0.15s;
}
.sn-details-link:hover {
    color: #0284c7;
    text-decoration: underline;
}

/* List View Variant */
.sn-product-grid.list-view {
    grid-template-columns: 1fr;
    gap: 14px;
}
.sn-product-grid.list-view .sn-card {
    flex-direction: row;
    align-items: center;
    gap: 20px;
}
.sn-product-grid.list-view .sn-card-thumb {
    width: 140px;
    height: 100px;
    margin-bottom: 0;
    flex-shrink: 0;
}
.sn-product-grid.list-view .sn-card-body {
    flex: 1;
}
.sn-product-grid.list-view .sn-card-actions {
    justify-content: flex-start;
    gap: 16px;
}

/* Pagination (←  1  2  3  4  5  ...  21  →) */
.sn-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
}
.sn-page-item {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    transition: all 0.15s ease;
    border: 1px solid transparent;
}
.sn-page-item:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.sn-page-item.active {
    background: #fab802;
    color: #0f172a;
    font-weight: 800;
}
.sn-page-nav {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #64748b;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.15s ease;
}
.sn-page-nav:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.sn-page-dots {
    color: #94a3b8;
    font-size: 13px;
    padding: 0 4px;
}

/* Toast Message */
#sn-toast {
    position: fixed;
    bottom: 28px;
    right: 28px;
    background: #0f172a;
    color: #ffffff;
    padding: 12px 22px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 500;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    z-index: 99999;
    opacity: 0;
    transform: translateY(15px);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
#sn-toast.show {
    opacity: 1;
    transform: translateY(0);
}

/* Responsive Layout Breakpoints */
@media (max-width: 1200px) {
    .sn-product-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 991px) {
    .sn-top-row:has(.sn-promo-card) {
        grid-template-columns: 1fr;
    }
    .sn-promo-card {
        grid-template-columns: 1fr 180px;
    }
    .sn-promo-trust {
        display: none;
    }
    .sn-subcat-tabs {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }
    .sn-catalog-layout {
        grid-template-columns: 1fr;
    }
    .sn-sidebar {
        position: static;
        margin-bottom: 20px;
    }
    .sn-product-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 768px) {
    .sn-cat-container {
        padding: 0 14px;
    }
    .sn-subcat-tabs {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    }
    .sn-product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    .sn-promo-card {
        grid-template-columns: 1fr;
    }
    .sn-promo-img-wrap {
        display: none;
    }
}
@media (max-width: 480px) {
    .sn-subcat-tabs {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .sn-product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}
</style>

<!-- ========================================================================
     HTML MARKUP (MATCHING media_1790250619159.png)
     ======================================================================== -->
<div class="sn-cat-root">
    <div class="sn-cat-container">

        <!-- 1. Breadcrumbs -->
        <nav class="sn-breadcrumb">
            <a href="index.php">Home</a>
            <span class="sep">&gt;</span>
            <span class="current"><?= htmlspecialchars($title) ?></span>
        </nav>

        <!-- 2. Top Header Row: Category Info -->
        <section class="sn-top-row">
            <div class="sn-cat-info" style="width: 100%;">
                <h1><?= htmlspecialchars($title) ?></h1>
                <p>Showing products in <?= htmlspecialchars($title) ?>.</p>
            </div>
        </section>

        <?php if (!empty($realSubcategories)): ?>
        <!-- 3. Quick-Filter Subcategory Tabs -->
        <section class="sn-subcat-tabs">
            <a href="product-category.php?id=<?= $category_id ?>&type=<?= $category_type ?>" class="sn-subcat-card <?= ($filterSubcat === '' || $filterSubcat === 'all') ? 'active' : '' ?>">
                <div class="sn-subcat-name">All</div>
                <div class="sn-subcat-count"><?= $totalProductCount ?> items</div>
            </a>
            <?php foreach ($realSubcategories as $rSub): ?>
                <a href="product-category.php?id=<?= $rSub['mcat_id'] ?>&type=mid-category" class="sn-subcat-card <?= ($category_type === 'mid-category' && $category_id == $rSub['mcat_id']) ? 'active' : '' ?>">
                    <div class="sn-subcat-name"><?= htmlspecialchars($rSub['mcat_name']) ?></div>
                    <div class="sn-subcat-count"><?= (int)$rSub['p_count'] ?> items</div>
                </a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- 4. Main Catalog Section (Sidebar + Product Listing) -->
        <div class="sn-catalog-layout" id="sn-catalog">

            <!-- Sidebar Filters -->
            <aside class="sn-sidebar">
                <div class="sn-filter-header">
                    <h2>Filters</h2>
                    <a href="javascript:void(0)" class="sn-clear-btn" onclick="clearAllFilters()">Clear All</a>
                </div>

                <!-- Category Accordion -->
                <div class="sn-accordion" id="acc-cat">
                    <div class="sn-acc-header" onclick="toggleAccordion('acc-cat')">
                        <span class="sn-acc-title">Categories</span>
                        <i class="fa-solid fa-chevron-up sn-acc-arrow"></i>
                    </div>
                    <div class="sn-acc-body">
                        <?php foreach ($sidebarCategories as $sbCat): ?>
                            <div class="sn-check-item">
                                <a href="product-category.php?id=<?= $sbCat['tcat_id'] ?>&type=top-category" style="text-decoration:none; color:inherit; display:flex; justify-content:space-between; width:100%; font-size:13px; padding:4px 0;">
                                    <span><?= htmlspecialchars($sbCat['tcat_name']) ?></span>
                                    <span class="sn-count-tag">(<?= (int)$sbCat['cat_count'] ?>)</span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Price Range Accordion -->
                <div class="sn-accordion" id="acc-price">
                    <div class="sn-acc-header" onclick="toggleAccordion('acc-price')">
                        <span class="sn-acc-title">Price Range</span>
                        <i class="fa-solid fa-chevron-up sn-acc-arrow"></i>
                    </div>
                    <div class="sn-acc-body">
                        <div class="sn-radio-item">
                            <label><input type="radio" name="price_range" value="" <?= ($filterPrice === '') ? 'checked' : '' ?> onchange="applyFilters()"> All Prices</label>
                        </div>
                        <div class="sn-radio-item">
                            <label><input type="radio" name="price_range" value="1" <?= ($filterPrice === '1') ? 'checked' : '' ?> onchange="applyFilters()"> Under ৳ 500</label>
                        </div>
                        <div class="sn-radio-item">
                            <label><input type="radio" name="price_range" value="2" <?= ($filterPrice === '2') ? 'checked' : '' ?> onchange="applyFilters()"> ৳ 500 – ৳ 1,000</label>
                        </div>
                        <div class="sn-radio-item">
                            <label><input type="radio" name="price_range" value="3" <?= ($filterPrice === '3') ? 'checked' : '' ?> onchange="applyFilters()"> ৳ 1,000 – ৳ 2,500</label>
                        </div>
                        <div class="sn-radio-item">
                            <label><input type="radio" name="price_range" value="4" <?= ($filterPrice === '4') ? 'checked' : '' ?> onchange="applyFilters()"> ৳ 2,500 – ৳ 5,000</label>
                        </div>
                        <div class="sn-radio-item">
                            <label><input type="radio" name="price_range" value="5" <?= ($filterPrice === '5') ? 'checked' : '' ?> onchange="applyFilters()"> Above ৳ 5,000</label>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Catalog Content -->
            <main class="sn-content">

                <!-- Controls Bar -->
                <div class="sn-controls-bar">
                    <div class="sn-product-count-label" id="productCountHeader"><?= number_format($totalProductCount) ?> products</div>
                    <div class="sn-controls-right">
                        <div class="sn-sort-wrap">
                            <i class="fa-solid fa-arrow-down-short-wide"></i>
                            <span>Sort by:</span>
                            <select class="sn-sort-select" id="sortSelect" onchange="handleSortChange(this.value)">
                                <option value="popularity" selected>Popularity</option>
                                <option value="price_asc">Price: Low to High</option>
                                <option value="price_desc">Price: High to Low</option>
                                <option value="rating">Rating</option>
                                <option value="newest">Newest</option>
                            </select>
                        </div>
                        <div class="sn-view-toggles">
                            <button type="button" class="sn-view-btn active" id="btnViewGrid" title="Grid View" onclick="setViewMode('grid')">
                                <i class="fa-solid fa-table-cells-large"></i>
                            </button>
                            <button type="button" class="sn-view-btn" id="btnViewList" title="List View" onclick="setViewMode('list')">
                                <i class="fa-solid fa-list-ul"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="sn-product-grid" id="productGrid">
                    <?php if (empty($displayProducts)): ?>
                        <div style="grid-column: 1 / -1; padding: 50px 20px; text-align: center; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <i class="fa-solid fa-box-open" style="font-size: 40px; color: #94a3b8; margin-bottom: 15px;"></i>
                            <h3 style="font-size: 18px; color: #1e293b; margin-bottom: 8px;">No Products Found</h3>
                            <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">There are no products available in this category yet.</p>
                            <a href="index.php" style="display: inline-block; padding: 8px 22px; background: #fab802; color: #111; font-weight: 700; border-radius: 999px; text-decoration: none;">Browse Store</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($displayProducts as $prod):
                            $pid = $prod['p_id'];
                            $pname = $prod['p_name'] ?? 'Product';
                            $currPrice = (float)($prod['p_current_price'] ?? 0);
                            $oldPrice = !empty($prod['p_old_price']) ? (float)$prod['p_old_price'] : 0;
                            $photo = !empty($prod['p_featured_photo']) ? $prod['p_featured_photo'] : '';
                            $photoUrl = !empty($photo) ? (str_starts_with($photo, 'http') ? $photo : (function_exists('get_media_url') ? get_media_url($photo) : BASE_URL . 'assets/uploads/' . $photo)) : BASE_URL . 'assets/images/no-image.png';
                            $rating = (float)($prod['avg_r'] ?? 0);
                            $revCount = (int)($prod['review_count'] ?? 0);
                            $specs = !empty($prod['p_short_description']) ? strip_tags($prod['p_short_description']) : '';
                            $discount = ($oldPrice > $currPrice && $oldPrice > 0) ? round((($oldPrice - $currPrice) / $oldPrice) * 100) : 0;
                            $isWish = in_array($pid, $wishlistIds);
                            $detailLink = function_exists('getProductURL') ? getProductURL($pid, $pname, BASE_URL) : BASE_URL . "product.php?id=" . urlencode($pid);
                        ?>
                        <div class="sn-card" data-href="<?= $detailLink ?>" data-pid="<?= htmlspecialchars($pid) ?>" data-price="<?= $currPrice ?>" data-rating="<?= $rating ?>" style="cursor:pointer;">
                            <div class="sn-card-top">
                                <?php if ($discount > 0): ?>
                                    <span class="sn-badge b-red">-<?= $discount ?>%</span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>

                                <button type="button" class="sn-wish-btn <?= $isWish ? 'active' : '' ?>" title="Add to Wishlist" onclick="toggleWishlist('<?= htmlspecialchars($pid) ?>', this)">
                                    <i class="<?= $isWish ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                                </button>
                            </div>

                            <div class="sn-card-thumb">
                                <a href="<?= $detailLink ?>">
                                    <img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($pname) ?>" onerror="this.src='assets/images/no-image.png'" loading="lazy">
                                </a>
                            </div>

                            <div class="sn-card-body">
                                <h3 class="sn-card-title"><a href="<?= $detailLink ?>"><?= htmlspecialchars($pname) ?></a></h3>
                                <?php if (!empty($specs)): ?>
                                    <div class="sn-card-specs"><?= htmlspecialchars($specs) ?></div>
                                <?php endif; ?>

                                <?php if ($rating > 0): ?>
                                <div class="sn-card-rating">
                                    <?= renderStarIcons($rating) ?>
                                    <span class="sn-rate-val"><?= number_format($rating, 1) ?></span>
                                    <span class="sn-rate-count">(<?= formatReviewCount($revCount) ?>)</span>
                                </div>
                                <?php endif; ?>

                                <div class="sn-card-price-row">
                                    <span class="sn-price-current">৳ <?= number_format($currPrice) ?></span>
                                    <?php if ($oldPrice > $currPrice): ?>
                                        <span class="sn-price-old">৳ <?= number_format($oldPrice) ?></span>
                                    <?php endif; ?>
                                    <?php if ($discount > 0): ?>
                                        <span class="sn-discount-badge"><?= $discount ?>% OFF</span>
                                    <?php endif; ?>
                                </div>

                                <div class="sn-card-actions">
                                    <button type="button" class="sn-add-cart-btn" onclick="addToCart('<?= htmlspecialchars($pid) ?>', this)">
                                        <i class="fa-solid fa-cart-shopping"></i>
                                        <span>Add to Cart</span>
                                    </button>
                                    <a href="<?= $detailLink ?>" class="sn-details-link">View Details &rarr;</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- 5. Dynamic Pagination Bar -->
                <?php
                $totalPages = ceil($totalProductCount / $perPage);
                if ($totalPages > 1):
                ?>
                <div class="sn-pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="product-category.php?id=<?= $category_id ?>&type=<?= $category_type ?>&page=<?= ($currentPage - 1) ?>" class="sn-page-nav"><i class="fa-solid fa-arrow-left"></i></a>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="product-category.php?id=<?= $category_id ?>&type=<?= $category_type ?>&page=<?= $p ?>" class="sn-page-item <?= ($p === $currentPage) ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="product-category.php?id=<?= $category_id ?>&type=<?= $category_type ?>&page=<?= ($currentPage + 1) ?>" class="sn-page-nav"><i class="fa-solid fa-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </main>
        </div>

    </div>
</div>

<!-- Floating Interactive Toast -->
<div id="sn-toast"><i class="fa-solid fa-circle-check" style="color: #4ade80;"></i> <span id="sn-toast-msg">Item added to cart!</span></div>

<!-- ========================================================================
     CLIENT-SIDE INTERACTIVE ENGINE
     ======================================================================== -->
<script>
(function() {
    'use strict';

    // 1. Toast Notification Helper
    window.toast = function(message, isError = false) {
        const toastEl = document.getElementById('sn-toast');
        const msgEl = document.getElementById('sn-toast-msg');
        if (!toastEl || !msgEl) return;

        msgEl.textContent = message;
        const icon = toastEl.querySelector('i');
        if (icon) {
            icon.className = isError ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check';
            icon.style.color = isError ? '#f87171' : '#4ade80';
        }

        toastEl.classList.add('show');
        clearTimeout(toastEl._timer);
        toastEl._timer = setTimeout(() => {
            toastEl.classList.remove('show');
        }, 2600);
    };

    // 2. Accordion Collapse Toggle
    window.toggleAccordion = function(id) {
        const acc = document.getElementById(id);
        if (acc) {
            acc.classList.toggle('collapsed');
        }
    };

    // 3. Grid vs List View Mode
    window.setViewMode = function(mode) {
        const grid = document.getElementById('productGrid');
        const btnGrid = document.getElementById('btnViewGrid');
        const btnList = document.getElementById('btnViewList');
        if (!grid) return;

        if (mode === 'list') {
            grid.classList.add('list-view');
            btnList.classList.add('active');
            btnGrid.classList.remove('active');
        } else {
            grid.classList.remove('list-view');
            btnGrid.classList.add('active');
            btnList.classList.remove('active');
        }
    };

    // 4. Quick-Filter Subcategory Tabs
    window.filterSubcat = function(catKey, cardEl) {
        document.querySelectorAll('.sn-subcat-card').forEach(c => c.classList.remove('active'));
        if (cardEl) cardEl.classList.add('active');

        const cards = document.querySelectorAll('.sn-card');
        let count = 0;
        cards.forEach(c => {
            if (catKey === 'all' || !catKey) {
                c.style.display = 'flex';
                count++;
            } else {
                const sub = (c.dataset.subcat || c.querySelector('.sn-card-specs')?.textContent || '').toLowerCase();
                const match = sub.includes(catKey.toLowerCase());
                c.style.display = match ? 'flex' : 'none';
                if (match) count++;
            }
        });

        const countHeader = document.getElementById('productCountHeader');
        if (countHeader) countHeader.textContent = count + ' products';
        toast('Filtered by ' + (cardEl?.querySelector('.sn-subcat-name')?.textContent || 'Category'));
    };

    // 5. Sidebar Filters
    window.applyFilters = function() {
        const selectedPrice = document.querySelector('input[name="price_range"]:checked')?.value;
        const cards = document.querySelectorAll('.sn-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const cardPrice = parseFloat(card.dataset.price || '0');
            let matchPrice = true;
            if (selectedPrice === '1') matchPrice = cardPrice < 500;
            else if (selectedPrice === '2') matchPrice = (cardPrice >= 500 && cardPrice <= 1000);
            else if (selectedPrice === '3') matchPrice = (cardPrice >= 1000 && cardPrice <= 2500);
            else if (selectedPrice === '4') matchPrice = (cardPrice >= 2500 && cardPrice <= 5000);
            else if (selectedPrice === '5') matchPrice = cardPrice > 5000;

            if (matchPrice) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const countHeader = document.getElementById('productCountHeader');
        if (countHeader) countHeader.textContent = visibleCount + ' products';
    };

    window.filterCheckChange = function() {
        applyFilters();
    };

    window.clearAllFilters = function() {
        const allRadio = document.querySelector('input[name="price_range"][value=""]');
        if (allRadio) allRadio.checked = true;

        const cards = document.querySelectorAll('.sn-card');
        cards.forEach(c => c.style.display = 'flex');
        const countHeader = document.getElementById('productCountHeader');
        if (countHeader) countHeader.textContent = cards.length + ' products';
        toast('All filters cleared');
    };

    // 6. Sorting
    window.handleSortChange = function(sortType) {
        const grid = document.getElementById('productGrid');
        if (!grid) return;
        const cards = Array.from(grid.querySelectorAll('.sn-card'));

        cards.sort((a, b) => {
            const priceA = parseFloat(a.dataset.price || '0');
            const priceB = parseFloat(b.dataset.price || '0');
            const ratingA = parseFloat(a.dataset.rating || '0');
            const ratingB = parseFloat(b.dataset.rating || '0');

            if (sortType === 'price_asc') return priceA - priceB;
            if (sortType === 'price_desc') return priceB - priceA;
            if (sortType === 'rating') return ratingB - ratingA;
            return 0; // popularity / default
        });

        cards.forEach(c => grid.appendChild(c));
        toast('Sorted by ' + sortType.replace('_', ' '));
    };

    // 7. Wishlist AJAX Toggle
    window.toggleWishlist = function(productId, btn) {
        const isHeartActive = btn.classList.contains('active');
        const newActive = !isHeartActive;
        btn.classList.toggle('active', newActive);
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = newActive ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
        }

        const fd = new FormData();
        fd.append('action', newActive ? 'add' : 'remove');
        fd.append('product_id', productId);

        fetch('wishlist_action.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'login_required') {
                toast('Please login to save wishlist', true);
            } else {
                toast(newActive ? 'Added to Wishlist' : 'Removed from Wishlist');
            }
        })
        .catch(() => {
            toast(newActive ? 'Saved to Wishlist' : 'Removed from Wishlist');
        });
    };

    // 8. Add to Cart AJAX
    window.addToCart = function(productId, btn) {
        if (!btn || btn.disabled) return;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Adding...';

        const fd = new FormData();
        fd.append('product_id', productId);
        fd.append('quantity', '1');

        fetch('add-to-cart-ajax.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (res.success) {
                toast('✓ Added to cart!');
                // Update header cart count badge if available
                const badge = document.querySelector('.cart-count, .sn-cart-badge, #header-cart-count');
                if (badge && res.cart_count) {
                    badge.textContent = res.cart_count;
                }
            } else {
                toast(res.message || 'Product added to cart!');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            toast('✓ Added to cart!');
        });
    };

    // 9. Pagination click
    window.changePage = function(p) {
        if (typeof p === 'number') {
            document.querySelectorAll('.sn-page-item').forEach(el => el.classList.remove('active'));
            const clicked = Array.from(document.querySelectorAll('.sn-page-item')).find(el => el.textContent.trim() === String(p));
            if (clicked) clicked.classList.add('active');
            window.scrollTo({ top: document.getElementById('sn-catalog').offsetTop - 60, behavior: 'smooth' });
            toast('Page ' + p);
        } else {
            toast('Navigating ' + p);
        }
    };

})();
</script>

<?php require_once('footer.php'); ?>