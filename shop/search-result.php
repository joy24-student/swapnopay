<?php require_once('header.php'); ?>

<?php
if (!isset($_REQUEST['search_text']) || $_REQUEST['search_text'] === '') {
    if (isset($_REQUEST['q']) && $_REQUEST['q'] !== '') {
        $_REQUEST['search_text'] = $_REQUEST['q'];
    } else {
        $uriPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        if (preg_match('#/(?:[a-zA-Z0-9_-]+/)?search/([^/]+)/?$#', $uriPath, $m)) {
            $_REQUEST['search_text'] = urldecode($m[1]);
        }
    }
}

if (!isset($_REQUEST['search_text']) || trim($_REQUEST['search_text']) === '') {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : 'index.php'));
    exit;
}

$search_text = trim(strip_tags($_REQUEST['search_text']));
$search_like = '%' . $search_text . '%';
$search_prefix = $search_text . '%';

/* ---------- Filters & sorting (all server-side) ---------- */
$allowedSorts = ['best', 'popular', 'newest', 'price_asc', 'price_desc'];
$sort      = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts, true) ? $_GET['sort'] : 'best';
$onSale    = !empty($_GET['sale']) ? 1 : 0;
$inStock   = !empty($_GET['stock']) ? 1 : 0;
$minRating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$minRating = ($minRating >= 1 && $minRating <= 5) ? $minRating : 0;
$priceMin  = (isset($_GET['min']) && $_GET['min'] !== '' && is_numeric($_GET['min'])) ? max(0, (float)$_GET['min']) : null;
$priceMax  = (isset($_GET['max']) && $_GET['max'] !== '' && is_numeric($_GET['max'])) ? max(0, (float)$_GET['max']) : null;

$priceExpr = "CAST(p.p_current_price AS DECIMAL(12,2))";
$oldExpr   = "CAST(NULLIF(p.p_old_price,'') AS DECIMAL(12,2))";

$where  = ["p.p_is_active = 1", "p.p_name LIKE ?"];
$params = [$search_like];
if ($onSale)  { $where[] = "$oldExpr > $priceExpr"; }
if ($inStock) { $where[] = "p.p_qty > 0"; }
if ($priceMin !== null) { $where[] = "$priceExpr >= ?"; $params[] = $priceMin; }
if ($priceMax !== null) { $where[] = "$priceExpr <= ?"; $params[] = $priceMax; }
if ($minRating) {
    $where[] = "(SELECT COALESCE(AVG(r.rating),0) FROM tbl_rating r WHERE r.p_id = p.p_id) >= ?";
    $params[] = $minRating;
}
$whereSql = implode(' AND ', $where);

$orderSql = "CASE WHEN p.p_name LIKE ? THEN 0 ELSE 1 END, p.p_is_featured DESC, p.p_total_view DESC, p.p_id DESC";
$orderParams = [$search_prefix];
switch ($sort) {
    case 'popular':    $orderSql = "p.p_total_view DESC, p.p_id DESC"; $orderParams = []; break;
    case 'newest':     $orderSql = "p.p_id DESC"; $orderParams = []; break;
    case 'price_asc':  $orderSql = "$priceExpr ASC, p.p_id DESC"; $orderParams = []; break;
    case 'price_desc': $orderSql = "$priceExpr DESC, p.p_id DESC"; $orderParams = []; break;
}

$limit = 20;
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_product p WHERE $whereSql");
$countStmt->execute($params);
$total_pages = (int)$countStmt->fetchColumn();
$lastpage = max(1, (int)ceil($total_pages / $limit));
if ($page > $lastpage) $page = $lastpage;
$start = ($page - 1) * $limit;

$sql = "SELECT p.*,
               (SELECT COALESCE(AVG(r.rating),0) FROM tbl_rating r WHERE r.p_id = p.p_id) AS avg_rating,
               (SELECT COUNT(*) FROM tbl_rating r WHERE r.p_id = p.p_id) AS rating_count
        FROM tbl_product p
        WHERE $whereSql
        ORDER BY $orderSql
        LIMIT " . (int)$limit . " OFFSET " . (int)$start;
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, $orderParams));
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* URL builder that preserves the current state */
$base = BASE_URL . 'search-result.php';
$state = ['search_text' => $search_text, 'sort' => $sort, 'sale' => $onSale, 'stock' => $inStock,
          'rating' => $minRating, 'min' => $priceMin, 'max' => $priceMax];
$buildUrl = function (array $override = []) use ($base, $state) {
    $q = array_merge($state, $override);
    $q = array_filter($q, function ($v, $k) {
        if ($k === 'search_text') return true;
        if ($k === 'sort') return $v !== 'best';
        return $v !== null && $v !== '' && $v !== 0 && $v !== '0';
    }, ARRAY_FILTER_USE_BOTH);
    return $base . '?' . http_build_query($q);
};
$activeFilters = ($onSale ? 1 : 0) + ($inStock ? 1 : 0) + ($minRating ? 1 : 0) + ($priceMin !== null ? 1 : 0) + ($priceMax !== null ? 1 : 0);
$sortLabels = ['best' => 'Best match', 'popular' => 'Popular', 'newest' => 'Newest', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low'];
$cur = LANG_VALUE_1;
?>

<style>
/* ===== Search Results (yellow theme) ===== */
.srp { --y:#fab802; --y-d:#e0a400; --y-l:#fff8e1; --ink:#111827; --mut:#6b7280; --line:#f1f5f9; background:#fff; font-family:inherit; }
.srp * { box-sizing:border-box; }
.srp-wrap { max-width:1280px; margin:0 auto; padding:0 12px 24px; }
.srp-bar { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px 0 8px; }
.srp-title { font-size:14px; color:var(--mut); margin:0; min-width:0; }
.srp-title b { color:var(--ink); }
.srp-title strong { color:var(--ink); word-break:break-word; }
.srp-sale-banner { display:flex; align-items:center; justify-content:center; gap:6px; background:var(--y-l); border:1px solid #fde8a1; color:#92400e; font-size:12px; font-weight:700; border-radius:10px; padding:8px 10px; margin:4px 0 10px; }
.srp-sale-banner i { color:var(--y-d); }

/* sticky chip row */
.srp-chips { position:sticky; top:96px; z-index:90; background:#fff; margin:0 -12px; padding:8px 12px; display:flex; gap:8px; overflow-x:auto; scrollbar-width:none; -ms-overflow-style:none; border-bottom:1px solid var(--line); transition:top .25s ease; }
body.sn-header-scrolled-away .srp-chips { top:58px; }
.srp-chips::-webkit-scrollbar { display:none; }
.srp-chip { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; height:36px; padding:0 14px; border-radius:999px; border:1.5px solid #e5e7eb; background:#fff; color:var(--ink); font-size:13px; font-weight:600; text-decoration:none; white-space:nowrap; cursor:pointer; transition:all .15s; }
.srp-chip:hover { border-color:var(--y); color:var(--ink); text-decoration:none; }
.srp-chip.active { background:var(--y); border-color:var(--y); color:var(--ink); box-shadow:0 2px 6px rgba(250,184,2,.3); }
.srp-chip .cnt { background:var(--ink); color:#fff; border-radius:999px; font-size:10px; min-width:17px; height:17px; display:inline-flex; align-items:center; justify-content:center; padding:0 4px; }

/* applied filters */
.srp-applied { display:flex; flex-wrap:wrap; gap:6px; padding:10px 0 0; }
.srp-tag { display:inline-flex; align-items:center; gap:6px; background:var(--y-l); border:1px solid #fde8a1; color:#92400e; font-size:12px; font-weight:600; border-radius:999px; padding:4px 10px; text-decoration:none; }
.srp-tag:hover { background:#fff1bf; text-decoration:none; color:#92400e; }
.srp-clear { font-size:12px; font-weight:700; color:var(--ink); text-decoration:underline; padding:4px 4px; }

/* grid */
.srp-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; padding-top:12px; }
.srp-card { position:relative; display:flex; flex-direction:column; background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; transition:box-shadow .2s, transform .2s; }
.srp-card:hover { box-shadow:0 8px 22px rgba(17,24,39,.08); transform:translateY(-2px); }
.srp-thumb { position:relative; display:block; aspect-ratio:1/1; background:#f8fafc; overflow:hidden; }
.srp-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.srp-badge { position:absolute; top:8px; left:8px; background:var(--y); color:var(--ink); font-size:11px; font-weight:800; padding:3px 8px; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,.12); }
.srp-badge.oos { background:#111827; color:#fff; }
.srp-body { padding:8px 10px 10px; display:flex; flex-direction:column; gap:4px; flex:1; }
.srp-name { font-size:13px; line-height:1.35; color:var(--ink); font-weight:500; margin:0; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:35px; }
.srp-name a { color:inherit; text-decoration:none; }
.srp-rate { display:flex; align-items:center; gap:4px; font-size:11px; color:var(--mut); min-height:16px; }
.srp-stars { color:var(--y); letter-spacing:1px; font-size:11px; }
.srp-price-row { display:flex; align-items:baseline; flex-wrap:wrap; gap:6px; margin-top:auto; }
.srp-price { font-size:16px; font-weight:800; color:var(--ink); }
.srp-old { font-size:12px; color:#9ca3af; text-decoration:line-through; }
.srp-off { font-size:11px; font-weight:800; color:#b45309; background:var(--y-l); border-radius:5px; padding:1px 6px; }
.srp-add { display:flex; align-items:center; justify-content:center; gap:6px; margin-top:6px; height:34px; border-radius:999px; background:var(--y); color:var(--ink); font-weight:700; font-size:12.5px; text-decoration:none; transition:background .15s, transform .1s; }
.srp-add:hover { background:var(--y-d); color:var(--ink); text-decoration:none; }
.srp-add:active { transform:scale(.97); }
.srp-add.disabled { background:#e5e7eb; color:#6b7280; pointer-events:none; }

/* empty */
.srp-empty { text-align:center; padding:48px 16px; }
.srp-empty .ico { width:84px; height:84px; margin:0 auto 14px; border-radius:50%; background:var(--y-l); display:flex; align-items:center; justify-content:center; font-size:34px; color:var(--y-d); }
.srp-empty h3 { font-size:18px; margin:0 0 6px; color:var(--ink); }
.srp-empty p { color:var(--mut); font-size:13px; margin:0 0 16px; }
.srp-btn { display:inline-flex; align-items:center; justify-content:center; height:40px; padding:0 22px; border-radius:999px; background:var(--y); color:var(--ink); font-weight:700; text-decoration:none; border:none; cursor:pointer; font-size:14px; }
.srp-btn:hover { background:var(--y-d); color:var(--ink); text-decoration:none; }
.srp-btn.ghost { background:#fff; border:1.5px solid #e5e7eb; }

/* pagination */
.srp-pager { display:flex; justify-content:center; align-items:center; gap:6px; padding:20px 0 4px; flex-wrap:wrap; }
.srp-pg { min-width:38px; height:38px; padding:0 12px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1.5px solid #e5e7eb; background:#fff; color:var(--ink); font-weight:700; font-size:13px; text-decoration:none; }
.srp-pg:hover { border-color:var(--y); text-decoration:none; color:var(--ink); }
.srp-pg.cur { background:var(--y); border-color:var(--y); }
.srp-pg.off { opacity:.4; pointer-events:none; }

/* bottom sheet */
.srp-ov { position:fixed; inset:0; background:rgba(17,24,39,.5); z-index:2000; opacity:0; visibility:hidden; transition:opacity .2s, visibility .2s; }
.srp-ov.open { opacity:1; visibility:visible; }
.srp-sheet { position:fixed; left:0; right:0; bottom:0; z-index:2001; background:#fff; border-radius:20px 20px 0 0; max-height:88vh; display:flex; flex-direction:column; transform:translateY(100%); transition:transform .28s cubic-bezier(.16,1,.3,1); }
.srp-sheet.open { transform:translateY(0); }
.srp-sh-head { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--line); }
.srp-sh-head h4 { margin:0; font-size:16px; font-weight:800; color:var(--ink); }
.srp-x { width:32px; height:32px; border-radius:50%; border:none; background:#f3f4f6; font-size:16px; cursor:pointer; }
.srp-sh-body { padding:14px 16px; overflow-y:auto; }
.srp-sec { margin-bottom:18px; }
.srp-sec h5 { font-size:13px; font-weight:800; color:var(--ink); margin:0 0 10px; }
.srp-pr { display:flex; align-items:center; gap:8px; }
.srp-pr input { flex:1; min-width:0; height:42px; border:1.5px solid #e5e7eb; border-radius:10px; padding:0 12px; font-size:14px; outline:none; }
.srp-pr input:focus { border-color:var(--y); box-shadow:0 0 0 3px rgba(250,184,2,.2); }
.srp-opts { display:flex; flex-wrap:wrap; gap:8px; }
.srp-opt { position:relative; }
.srp-opt input { position:absolute; opacity:0; pointer-events:none; }
.srp-opt span { display:inline-flex; align-items:center; gap:6px; height:36px; padding:0 14px; border-radius:999px; border:1.5px solid #e5e7eb; font-size:13px; font-weight:600; cursor:pointer; color:var(--ink); }
.srp-opt input:checked + span { background:var(--y); border-color:var(--y); }
.srp-sh-foot { display:flex; gap:10px; padding:12px 16px calc(12px + env(safe-area-inset-bottom)); border-top:1px solid var(--line); }
.srp-sh-foot .srp-btn { flex:1; }
.srp-sort-list a { display:flex; align-items:center; justify-content:space-between; padding:14px 4px; border-bottom:1px solid var(--line); color:var(--ink); font-size:14px; font-weight:600; text-decoration:none; }
.srp-sort-list a.on { color:#b45309; }
.srp-sort-list a i { color:var(--y-d); }

@media (min-width:600px)  { .srp-grid { grid-template-columns:repeat(3,1fr); gap:14px; } }
@media (min-width:900px)  { .srp-grid { grid-template-columns:repeat(4,1fr); } .srp-chips { top:auto; position:static; margin:0; padding:8px 0; } .srp-sheet { left:50%; right:auto; width:480px; margin-left:-240px; bottom:auto; top:50%; border-radius:20px; transform:translateY(-40%); opacity:0; visibility:hidden; } .srp-sheet.open { transform:translateY(-50%); opacity:1; visibility:visible; } }
@media (min-width:1200px) { .srp-grid { grid-template-columns:repeat(5,1fr); } }
@media (max-width:768px) { .srp-wrap { padding-bottom:80px; } }
</style>

<div class="srp">
<div class="srp-wrap">

    <div class="srp-bar">
        <p class="srp-title">
            <b><?php echo number_format($total_pages); ?></b> result<?php echo $total_pages === 1 ? '' : 's'; ?> for
            "<strong><?php echo htmlspecialchars($search_text, ENT_QUOTES, 'UTF-8'); ?></strong>"
        </p>
    </div>

    <div class="srp-chips" id="srp-chips">
        <button type="button" class="srp-chip<?php echo $activeFilters ? ' active' : ''; ?>" data-open="filter">
            <i class="fas fa-sliders-h"></i> Filters
            <?php if ($activeFilters): ?><span class="cnt"><?php echo $activeFilters; ?></span><?php endif; ?>
        </button>
        <button type="button" class="srp-chip<?php echo $sort !== 'best' ? ' active' : ''; ?>" data-open="sort">
            <?php echo $sort === 'best' ? 'Sort by' : htmlspecialchars($sortLabels[$sort]); ?> <i class="fas fa-chevron-down" style="font-size:10px"></i>
        </button>
        <a class="srp-chip<?php echo $onSale ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['sale' => $onSale ? 0 : 1, 'page' => null])); ?>"><i class="fas fa-bolt"></i> Sale</a>
        <a class="srp-chip<?php echo $inStock ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['stock' => $inStock ? 0 : 1, 'page' => null])); ?>">In stock</a>
        <a class="srp-chip<?php echo $minRating >= 4 ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['rating' => $minRating >= 4 ? 0 : 4, 'page' => null])); ?>"><i class="fas fa-star"></i> 4+</a>
        <a class="srp-chip<?php echo $sort === 'price_asc' ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['sort' => $sort === 'price_asc' ? 'best' : 'price_asc', 'page' => null])); ?>">Price <i class="fas fa-arrow-up" style="font-size:10px"></i></a>
    </div>

    <?php if ($activeFilters): ?>
    <div class="srp-applied">
        <?php if ($onSale): ?><a class="srp-tag" href="<?php echo htmlspecialchars($buildUrl(['sale' => 0, 'page' => null])); ?>">On sale <i class="fas fa-times"></i></a><?php endif; ?>
        <?php if ($inStock): ?><a class="srp-tag" href="<?php echo htmlspecialchars($buildUrl(['stock' => 0, 'page' => null])); ?>">In stock <i class="fas fa-times"></i></a><?php endif; ?>
        <?php if ($minRating): ?><a class="srp-tag" href="<?php echo htmlspecialchars($buildUrl(['rating' => 0, 'page' => null])); ?>"><?php echo $minRating; ?>★ &amp; up <i class="fas fa-times"></i></a><?php endif; ?>
        <?php if ($priceMin !== null): ?><a class="srp-tag" href="<?php echo htmlspecialchars($buildUrl(['min' => null, 'page' => null])); ?>">Min <?php echo $cur . rtrim(rtrim(number_format($priceMin, 2, '.', ''), '0'), '.'); ?> <i class="fas fa-times"></i></a><?php endif; ?>
        <?php if ($priceMax !== null): ?><a class="srp-tag" href="<?php echo htmlspecialchars($buildUrl(['max' => null, 'page' => null])); ?>">Max <?php echo $cur . rtrim(rtrim(number_format($priceMax, 2, '.', ''), '0'), '.'); ?> <i class="fas fa-times"></i></a><?php endif; ?>
        <a class="srp-clear" href="<?php echo htmlspecialchars($base . '?search_text=' . urlencode($search_text)); ?>">Clear all</a>
    </div>
    <?php endif; ?>

    <?php if (!$total_pages): ?>
        <div class="srp-empty">
            <div class="ico"><i class="fas fa-search"></i></div>
            <h3>No results found</h3>
            <p><?php echo $activeFilters ? 'Try removing some filters.' : 'Check the spelling or try a different keyword.'; ?></p>
            <?php if ($activeFilters): ?>
                <a class="srp-btn" href="<?php echo htmlspecialchars($base . '?search_text=' . urlencode($search_text)); ?>">Clear filters</a>
            <?php else: ?>
                <a class="srp-btn" href="<?php echo BASE_URL; ?>">Continue shopping</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="srp-grid">
        <?php foreach ($result as $row):
            $price = (float)$row['p_current_price'];
            $old   = ($row['p_old_price'] !== '' && $row['p_old_price'] !== null) ? (float)$row['p_old_price'] : 0;
            $off   = ($old > $price && $old > 0) ? (int)round((($old - $price) / $old) * 100) : 0;
            $avg   = (float)$row['avg_rating'];
            $rc    = (int)$row['rating_count'];
            $oos   = ((int)$row['p_qty'] <= 0);
            $link  = 'product.php?id=' . (int)$row['p_id'];
            $fmt   = function ($n) { return rtrim(rtrim(number_format($n, 2), '0'), '.'); };
        ?>
            <article class="srp-card">
                <a class="srp-thumb" href="<?php echo $link; ?>">
                    <img src="assets/uploads/<?php echo htmlspecialchars($row['p_featured_photo']); ?>" alt="<?php echo htmlspecialchars($row['p_name']); ?>" loading="lazy">
                    <?php if ($oos): ?><span class="srp-badge oos">Sold out</span>
                    <?php elseif ($off): ?><span class="srp-badge">-<?php echo $off; ?>%</span><?php endif; ?>
                </a>
                <div class="srp-body">
                    <h3 class="srp-name"><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($row['p_name']); ?></a></h3>
                    <div class="srp-rate">
                        <?php if ($rc): ?>
                            <span class="srp-stars"><?php for ($i = 1; $i <= 5; $i++) echo $i <= round($avg) ? '★' : '☆'; ?></span>
                            <span><?php echo number_format($avg, 1); ?> (<?php echo $rc; ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="srp-price-row">
                        <span class="srp-price"><?php echo $cur . $fmt($price); ?></span>
                        <?php if ($off): ?>
                            <span class="srp-old"><?php echo $cur . $fmt($old); ?></span>
                            <span class="srp-off">-<?php echo $off; ?>%</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($oos): ?>
                        <span class="srp-add disabled">Out of stock</span>
                    <?php else: ?>
                        <a class="srp-add" href="<?php echo $link; ?>"><i class="fas fa-shopping-cart"></i> Add to Cart</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        </div>

        <?php if ($lastpage > 1):
            $from = max(1, $page - 2); $to = min($lastpage, $page + 2); ?>
        <nav class="srp-pager" aria-label="Pagination">
            <a class="srp-pg<?php echo $page <= 1 ? ' off' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['page' => $page - 1])); ?>">&laquo;</a>
            <?php if ($from > 1): ?><a class="srp-pg" href="<?php echo htmlspecialchars($buildUrl(['page' => 1])); ?>">1</a><?php if ($from > 2) echo '<span>…</span>'; endif; ?>
            <?php for ($i = $from; $i <= $to; $i++): ?>
                <a class="srp-pg<?php echo $i === $page ? ' cur' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($to < $lastpage): if ($to < $lastpage - 1) echo '<span>…</span>'; ?><a class="srp-pg" href="<?php echo htmlspecialchars($buildUrl(['page' => $lastpage])); ?>"><?php echo $lastpage; ?></a><?php endif; ?>
            <a class="srp-pg<?php echo $page >= $lastpage ? ' off' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['page' => $page + 1])); ?>">&raquo;</a>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Overlay + sheets -->
<div class="srp-ov" id="srp-ov"></div>

<div class="srp-sheet" id="srp-sort" role="dialog" aria-label="Sort">
    <div class="srp-sh-head"><h4>Sort by</h4><button class="srp-x" data-close type="button" aria-label="Close">&times;</button></div>
    <div class="srp-sh-body srp-sort-list">
        <?php foreach ($sortLabels as $k => $label): ?>
            <a class="<?php echo $sort === $k ? 'on' : ''; ?>" href="<?php echo htmlspecialchars($buildUrl(['sort' => $k, 'page' => null])); ?>">
                <?php echo $label; ?><?php if ($sort === $k) echo '<i class="fas fa-check"></i>'; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="srp-sheet" id="srp-filter" role="dialog" aria-label="Filters">
    <form method="get" action="<?php echo $base; ?>" style="display:flex;flex-direction:column;min-height:0;flex:1">
        <input type="hidden" name="search_text" value="<?php echo htmlspecialchars($search_text, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($sort !== 'best'): ?><input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>"><?php endif; ?>
        <div class="srp-sh-head"><h4>Filters</h4><button class="srp-x" data-close type="button" aria-label="Close">&times;</button></div>
        <div class="srp-sh-body">
            <div class="srp-sec">
                <h5>Price range (<?php echo $cur; ?>)</h5>
                <div class="srp-pr">
                    <input type="number" inputmode="decimal" min="0" step="any" name="min" placeholder="Min" value="<?php echo $priceMin !== null ? htmlspecialchars((string)$priceMin) : ''; ?>">
                    <span>–</span>
                    <input type="number" inputmode="decimal" min="0" step="any" name="max" placeholder="Max" value="<?php echo $priceMax !== null ? htmlspecialchars((string)$priceMax) : ''; ?>">
                </div>
            </div>
            <div class="srp-sec">
                <h5>Deals &amp; availability</h5>
                <div class="srp-opts">
                    <label class="srp-opt"><input type="checkbox" name="sale" value="1" <?php echo $onSale ? 'checked' : ''; ?>><span><i class="fas fa-bolt"></i> On sale</span></label>
                    <label class="srp-opt"><input type="checkbox" name="stock" value="1" <?php echo $inStock ? 'checked' : ''; ?>><span>In stock</span></label>
                </div>
            </div>
            <div class="srp-sec">
                <h5>Customer rating</h5>
                <div class="srp-opts">
                    <?php foreach ([0 => 'Any', 4 => '4★ & up', 3 => '3★ & up', 2 => '2★ & up'] as $v => $l): ?>
                        <label class="srp-opt"><input type="radio" name="rating" value="<?php echo $v; ?>" <?php echo $minRating === $v ? 'checked' : ''; ?>><span><?php echo $l; ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="srp-sh-foot">
            <a class="srp-btn ghost" href="<?php echo htmlspecialchars($base . '?search_text=' . urlencode($search_text)); ?>">Reset</a>
            <button type="submit" class="srp-btn">Show <?php echo number_format($total_pages); ?> results</button>
        </div>
    </form>
</div>
</div>

<script>
(function () {
    var ov = document.getElementById('srp-ov');
    var sheets = { sort: document.getElementById('srp-sort'), filter: document.getElementById('srp-filter') };
    function closeAll() {
        ov.classList.remove('open');
        Object.keys(sheets).forEach(function (k) { sheets[k].classList.remove('open'); });
        document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-open]').forEach(function (b) {
        b.addEventListener('click', function () {
            closeAll();
            ov.classList.add('open');
            sheets[b.getAttribute('data-open')].classList.add('open');
            document.body.style.overflow = 'hidden';
        });
    });
    ov.addEventListener('click', closeAll);
    document.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', closeAll); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });

    // Keep the header search box showing the current query
    var inp = document.getElementById('sn-search-input');
    if (inp && !inp.value) inp.value = <?php echo json_encode($search_text); ?>;
})();
</script>

<?php require_once('footer.php'); ?>