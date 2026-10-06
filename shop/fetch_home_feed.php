<?php
/**
 * AJAX Endpoint: AliExpress-Style Personalized Home Feed
 * Provides continuous scroll product fetching with multi-factor personalization
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/admin/inc/config.php');
require_once(__DIR__ . '/admin/inc/seo_helpers.php');
require_once(__DIR__ . '/inc_feed_card.php');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    $tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'for_you';
    $validTabs = ['for_you', 'trending', 'deals', 'top_rated', 'choice'];
    if (!in_array($tab, $validTabs)) {
        $tab = 'for_you';
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(30, max(4, (int)$_GET['limit'])) : 10;
    
    // Deduplication exclude list passed from client
    $excludeParam = isset($_GET['exclude']) ? trim($_GET['exclude']) : '';
    $excludeIds = [];
    if (!empty($excludeParam)) {
        $excludeIds = array_unique(array_filter(array_map('intval', explode(',', $excludeParam))));
    }

    // -------------------------------------------------------------
    // 1. GATHER PERSONALIZATION SIGNALS
    // -------------------------------------------------------------
    $signalIds = [];

    // Client-side passed recent viewed IDs (from localStorage)
    if (!empty($_GET['recent'])) {
        $clientRecents = array_filter(array_map('intval', explode(',', $_GET['recent'])));
        $signalIds = array_merge($signalIds, $clientRecents);
    }

    // Server-side session viewed products
    if (!empty($_SESSION['recently_viewed']) && is_array($_SESSION['recently_viewed'])) {
        $signalIds = array_merge($signalIds, array_map('intval', $_SESSION['recently_viewed']));
    }

    // Server-side persistent cookie viewed products
    if (!empty($_COOKIE['sn_recently_viewed'])) {
        $cRec = json_decode($_COOKIE['sn_recently_viewed'], true);
        if (is_array($cRec)) {
            $signalIds = array_merge($signalIds, array_map('intval', $cRec));
        }
    }

    // Cart items
    if (!empty($_SESSION['cart_p_id']) && is_array($_SESSION['cart_p_id'])) {
        $signalIds = array_merge($signalIds, array_map('intval', $_SESSION['cart_p_id']));
    }

    // Logged-in customer signals (Wishlist & Orders)
    if (!empty($_SESSION['customer']['cust_id'])) {
        $custId = (int)$_SESSION['customer']['cust_id'];
        
        // Wishlist product IDs
        try {
            $wStmt = $pdo->prepare("SELECT product_id FROM tbl_wishlist WHERE cust_id = ? ORDER BY wishlist_id DESC LIMIT 15");
            $wStmt->execute([$custId]);
            $wIds = $wStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $signalIds = array_merge($signalIds, array_map('intval', $wIds));
        } catch (Throwable $_) {}

        // Order history product IDs
        try {
            $oStmt = $pdo->prepare("SELECT product_id FROM tbl_order WHERE cust_id = ? ORDER BY id DESC LIMIT 15");
            $oStmt->execute([$custId]);
            $oIds = $oStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $signalIds = array_merge($signalIds, array_map('intval', $oIds));
        } catch (Throwable $_) {}
    }

    $signalIds = array_values(array_unique(array_filter($signalIds)));

    // Extract preferred category tree from interacted products
    $preferredEcats = [];
    $preferredMcats = [];
    $preferredTcats = [];

    if (!empty($signalIds)) {
        $topSignals = array_slice($signalIds, 0, 25);
        $inSignals = implode(',', $topSignals);
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

    // -------------------------------------------------------------
    // 2. BUILD THE QUERY WITH POSTGRESQL-SAFE EXPRESSIONS
    // -------------------------------------------------------------
    $where = ["p.p_is_active = 1"];
    
    // Deduplication exclusion
    if (!empty($excludeIds)) {
        $where[] = "p.p_id NOT IN (" . implode(',', $excludeIds) . ")";
    }

    // Numeric price expressions compatible with PostgreSQL varchar price columns
    $numCurr = "CAST(NULLIF(REPLACE(COALESCE(p.p_current_price::text, '0'), ',', ''), '') AS numeric)";
    $numOld  = "CAST(NULLIF(REPLACE(COALESCE(p.p_old_price::text, '0'), ',', ''), '') AS numeric)";

    $order = "p.p_id DESC";

    if ($tab === 'for_you') {
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
        $order = "({$scoreSql}) DESC, p.p_id DESC";
    } elseif ($tab === 'trending') {
        $order = "p.is_top_sale DESC, p.p_total_view DESC, p.p_id DESC";
    } elseif ($tab === 'deals') {
        $where[] = "{$numOld} > {$numCurr} AND {$numOld} > 0";
        $order = "(({$numOld} - {$numCurr}) / {$numOld}) DESC, p.p_id DESC";
    } elseif ($tab === 'top_rated') {
        $order = "COALESCE((SELECT AVG(rating) FROM tbl_rating WHERE p_id = p.p_id), 0) DESC, COALESCE((SELECT COUNT(*) FROM tbl_rating WHERE p_id = p.p_id), 0) DESC, p.p_id DESC";
    } elseif ($tab === 'choice') {
        $where[] = "(p.is_official = 1 OR p.is_premium = 1 OR p.is_free_shipping = 1 OR p.p_is_featured = 1)";
        $order = "p.is_official DESC, p.is_premium DESC, p.is_free_shipping DESC, p.p_total_view DESC, p.p_id DESC";
    }

    $offset = ($page - 1) * $limit;
    $whereSql = implode(' AND ', $where);

    // Fetch products batch safely
    $products = [];
    try {
        $sql = "
            SELECT p.*, e.mcat_id, m.tcat_id,
                   COALESCE((SELECT AVG(rating) FROM tbl_rating WHERE p_id = p.p_id), 0) as avg_rating,
                   COALESCE((SELECT COUNT(*) FROM tbl_rating WHERE p_id = p.p_id), 0) as rev_count
            FROM tbl_product p
            LEFT JOIN tbl_end_category e ON p.ecat_id = e.ecat_id
            LEFT JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id
            WHERE {$whereSql}
            ORDER BY {$order}
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $pdo->query($sql);
        $products = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $qErr) {
        // Fallback to simple robust query if complex ordering or joins had an issue
        try {
            $fallbackSql = "
                SELECT p.*, e.mcat_id, m.tcat_id,
                       0 as avg_rating, 0 as rev_count
                FROM tbl_product p
                LEFT JOIN tbl_end_category e ON p.ecat_id = e.ecat_id
                LEFT JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id
                WHERE p.p_is_active = 1 " . (!empty($excludeIds) ? "AND p.p_id NOT IN (" . implode(',', $excludeIds) . ")" : "") . "
                ORDER BY p.p_id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            $stmt = $pdo->query($fallbackSql);
            $products = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $_) {
            $products = [];
        }
    }

    // Fallback: If 'deals' or 'choice' has few items and offset reached, don't break
    if (empty($products) && $page === 1 && ($tab === 'deals' || $tab === 'choice')) {
        $fallbackSql = "
            SELECT p.*, e.mcat_id, m.tcat_id,
                   COALESCE((SELECT AVG(rating) FROM tbl_rating WHERE p_id = p.p_id), 0) as avg_rating,
                   COALESCE((SELECT COUNT(*) FROM tbl_rating WHERE p_id = p.p_id), 0) as rev_count
            FROM tbl_product p
            LEFT JOIN tbl_end_category e ON p.ecat_id = e.ecat_id
            LEFT JOIN tbl_mid_category m ON e.mcat_id = m.mcat_id
            WHERE p.p_is_active = 1 " . (!empty($excludeIds) ? "AND p.p_id NOT IN (" . implode(',', $excludeIds) . ")" : "") . "
            ORDER BY p.p_total_view DESC, p.p_id DESC
            LIMIT {$limit}
        ";
        try {
            $products = $pdo->query($fallbackSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $_) {}
    }

    // Render HTML cards
    $html = '';
    $returnedIds = [];
    foreach ($products as $prod) {
        $html .= renderAliProductCard($prod, '৳ ');
        $returnedIds[] = (int)$prod['p_id'];
    }

    $hasMore = count($products) === $limit;

    echo json_encode([
        'success'      => true,
        'tab'          => $tab,
        'page'         => $page,
        'count'        => count($products),
        'has_more'     => $hasMore,
        'next_page'    => $page + 1,
        'html'         => $html,
        'product_ids'  => $returnedIds
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

