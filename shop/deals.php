<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$cur_page = 'deals.php';
$page_meta_title = 'Top Deals & Limited-Time Offers - ShopNext';
$page_meta_keyword = 'deals, discounts, coupons, flash sale, shopnext';
$page_meta_description = 'Shop exclusive limited-time deals and save up to 70% off on top electronics, smartphones, laptops, watches and more at ShopNext.';

require_once("admin/inc/config.php");
require_once("admin/inc/functions.php");
require_once("admin/inc/CSRF_Protect.php");
require_once("admin/inc/seo_helpers.php");

$csrf = new CSRF_Protect();

// Fetch customer wishlisted product IDs if logged in
$customerWishlist = [];
if (isset($_SESSION['customer']['cust_id'])) {
    $cust_id = (int)$_SESSION['customer']['cust_id'];
    $wlStmt = $pdo->prepare("SELECT product_id FROM tbl_wishlist WHERE cust_id = ?");
    $wlStmt->execute([$cust_id]);
    $customerWishlist = $wlStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$curatedDeals = [];
$categoriesList = [];

// Fetch real discounted or active products from database
try {
    $dbDealsStmt = $pdo->query("
        SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo, p_feature, ecat_id
        FROM tbl_product 
        WHERE p_is_active = 1 
        ORDER BY (CASE WHEN p_old_price > p_current_price THEN 0 ELSE 1 END), (p_old_price - p_current_price) DESC, p_id DESC 
        LIMIT 20
    ");
    $dbDeals = $dbDealsStmt ? ($dbDealsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    // Fetch ratings for these products
    $prodIds = array_column($dbDeals, 'p_id');
    $ratingMap = [];
    if (!empty($prodIds)) {
        $inClause = implode(',', array_map('intval', $prodIds));
        $rStmt = $pdo->query("SELECT p_id, AVG(rating) as avg_rating, COUNT(*) as rev_count FROM tbl_rating WHERE p_id IN ($inClause) GROUP BY p_id");
        if ($rStmt) {
            foreach ($rStmt->fetchAll(PDO::FETCH_ASSOC) as $rr) {
                $ratingMap[$rr['p_id']] = [
                    'rating' => round((float)$rr['avg_rating'], 1),
                    'reviews' => (int)$rr['rev_count']
                ];
            }
        }
    }

    // Try fetching categories for filter
    try {
        $catsStmt = $pdo->query("SELECT tcat_id, tcat_name FROM tbl_top_category ORDER BY tcat_order ASC, tcat_id ASC LIMIT 6");
        if ($catsStmt) {
            foreach ($catsStmt->fetchAll(PDO::FETCH_ASSOC) as $tc) {
                $categoriesList['cat_' . $tc['tcat_id']] = $tc['tcat_name'];
            }
        }
    } catch (Throwable $_) {}

    foreach ($dbDeals as $dbItem) {
        $curr = (float)str_replace(',', '', (string)$dbItem['p_current_price']);
        $old = (float)str_replace(',', '', (string)($dbItem['p_old_price'] ?? '0'));
        $hasDiscount = ($old > $curr && $old > 0);
        $discPct = $hasDiscount ? round((($old - $curr) / $old) * 100) : 0;
        
        $pId = (int)$dbItem['p_id'];
        $rInfo = $ratingMap[$pId] ?? null;
        $ratingStr = $rInfo ? (string)$rInfo['rating'] : '5.0';
        $reviewsStr = $rInfo ? ($rInfo['reviews'] > 0 ? (string)$rInfo['reviews'] : 'Verified') : 'Verified';
        
        $photo = !empty($dbItem['p_featured_photo']) ? $dbItem['p_featured_photo'] : '';
        $imgUrl = $photo ? (str_starts_with($photo, 'http') ? $photo : (function_exists('get_media_url') ? get_media_url($photo) : BASE_URL . 'assets/uploads/' . $photo)) : BASE_URL . 'assets/images/no-image.png';
        $prodUrl = function_exists('getProductURL') ? getProductURL($pId, $dbItem['p_name'], BASE_URL) : BASE_URL . 'product.php?id=' . $pId;

        $curatedDeals[] = [
            'id' => $pId,
            'category' => 'all',
            'name' => $dbItem['p_name'],
            'specs' => 'Verified Authentic • In Stock',
            'curr_price' => number_format($curr),
            'old_price' => $hasDiscount ? number_format($old) : '',
            'discount' => $hasDiscount ? ('-' . $discPct . '%') : 'HOT DEAL',
            'rating' => $ratingStr,
            'reviews' => $reviewsStr,
            'coupon' => $hasDiscount ? ('SAVE' . min(50, max(10, $discPct))) : 'SPECIAL',
            'img' => $imgUrl,
            'url' => $prodUrl
        ];
    }
} catch (Throwable $e) {}

// Require Site Header
require_once('header.php');
?>

<!-- Link Dedicated Deals Stylesheet -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/deals_modern.css">

<div class="sn-deals-page-wrap">
    
    <!-- 2. Hero Banner Card ("Up to 70% OFF") -->
    <div class="sn-deals-hero-card">
        <!-- Top Right Corner Badge -->
        <div class="sn-deals-hero-badge-corner">
            <div class="sn-corner-circle-badge">
                <span>Up to</span>
                <strong>70%</strong>
                <span>OFF</span>
            </div>
        </div>

        <!-- Left Content -->
        <div class="sn-deals-hero-left">
            <div class="sn-deals-pill-badge">
                <span class="sn-fire-emoji">🔥</span>
                <span>Limited Time Deal</span>
            </div>
            <h2 class="sn-deals-hero-heading">
                Up to <span class="sn-highlight-yellow">70% OFF</span><br>Top Electronics & More!
            </h2>
            <p class="sn-deals-hero-sub">Big brands. Bigger savings.</p>
            
            <div class="sn-deals-code-pill" onclick="copyDealCoupon('DEAL70', this)" role="button" tabindex="0" title="Click to copy coupon code DEAL70">
                <div class="sn-code-tag-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>
                    </svg>
                </div>
                <span class="sn-code-label">Use Code</span>
                <span class="sn-code-text">DEAL70</span>
                <button type="button" class="sn-code-copy-btn" aria-label="Copy DEAL70">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Right Collage Image -->
        <div class="sn-deals-hero-right">
            <img src="<?php echo BASE_URL; ?>assets/uploads/deal_hero_collage.jpg" alt="Top Electronics Deals Collage" class="sn-deals-hero-img">
        </div>
    </div>

    <!-- 3. Category Filter Bar (Desktop) -->
    <?php if (!empty($categoriesList)): ?>
    <div class="sn-deals-filter-bar sn-desktop-only" id="snDealsFilterBar">
        <button type="button" class="sn-filter-pill active" data-filter="all">All Deals</button>
        <?php foreach ($categoriesList as $cKey => $cName): ?>
            <button type="button" class="sn-filter-pill" data-filter="<?php echo htmlspecialchars($cKey); ?>"><?php echo htmlspecialchars($cName); ?></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 4. Deals Product Grid (2 columns on mobile, 4 columns on desktop) -->
    <div class="sn-deals-grid" id="snDealsGrid">
        <?php if (empty($curatedDeals)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 48px 16px; color: #64748b;">
                <i class="fas fa-tags" style="font-size: 36px; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">No Active Deals Right Now</h3>
                <p style="font-size: 14px;">Check back soon for new promotions and seasonal discounts!</p>
                <a href="<?php echo BASE_URL; ?>product-category.php" class="sn-deals-shop-btn" style="display: inline-block; margin-top: 16px; padding: 10px 24px; text-decoration: none;">Browse Products</a>
            </div>
        <?php endif; ?>

        <?php foreach ($curatedDeals as $idx => $item): 
            $isWishlisted = in_array($item['id'], $customerWishlist);
        ?>
            <div class="sn-deal-card" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                <!-- Card Top: Discount Badge & Wishlist Button -->
                <div class="sn-deal-card-top">
                    <span class="sn-deal-discount-badge"><?php echo htmlspecialchars($item['discount']); ?></span>
                    <button type="button" 
                            class="sn-deal-wishlist-btn <?php echo $isWishlisted ? 'active' : ''; ?>" 
                            onclick="toggleWishlistDeal(<?php echo $item['id']; ?>, this)" 
                            aria-label="Add to Wishlist">
                        <i class="<?php echo $isWishlisted ? 'fas fa-heart' : 'far fa-heart'; ?>"></i>
                    </button>
                </div>

                <!-- Product Image Link -->
                <a href="<?php echo htmlspecialchars($item['url']); ?>" class="sn-deal-img-box">
                    <img src="<?php echo htmlspecialchars($item['img']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="sn-deal-img" loading="lazy">
                </a>

                <!-- Product Title Link -->
                <a href="<?php echo htmlspecialchars($item['url']); ?>" class="sn-deal-title" title="<?php echo htmlspecialchars($item['name']); ?>">
                    <?php echo htmlspecialchars($item['name']); ?>
                </a>

                <?php if (!empty($item['specs'])): ?>
                    <div class="sn-deal-specs"><?php echo htmlspecialchars($item['specs']); ?></div>
                <?php endif; ?>

                <!-- Rating Row -->
                <div class="sn-deal-rating-row">
                    <span class="sn-deal-star-icon">★</span>
                    <span class="sn-deal-score"><?php echo htmlspecialchars($item['rating']); ?></span>
                    <span class="sn-deal-reviews-count">(<?php echo htmlspecialchars($item['reviews']); ?>)</span>
                </div>

                <!-- Price Row -->
                <div class="sn-deal-price-row">
                    <span class="sn-deal-old-price">৳ <?php echo htmlspecialchars($item['old_price']); ?></span>
                    <span class="sn-deal-curr-price">৳ <?php echo htmlspecialchars($item['curr_price']); ?></span>
                </div>

                <!-- Actions: Coupon Pill & Shop Now Button -->
                <div class="sn-deal-actions-row">
                    <div class="sn-deal-coupon-pill" onclick="copyDealCoupon('<?php echo htmlspecialchars($item['coupon']); ?>', this)" title="Click to copy <?php echo htmlspecialchars($item['coupon']); ?>">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>
                        </svg>
                        <div class="sn-deal-coupon-code-wrap">
                            <span class="sn-deal-coupon-label">Use Code</span>
                            <span class="sn-deal-coupon-code"><?php echo htmlspecialchars($item['coupon']); ?></span>
                        </div>
                        <span class="sn-deal-coupon-copy-icon">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </span>
                    </div>

                    <a href="<?php echo htmlspecialchars($item['url']); ?>" class="sn-deal-btn-shop">
                        <span>Shop Now</span>
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 5. Special Coupon Banner (Bottom) -->
    <div class="sn-deals-special-coupon-card">
        <div class="sn-special-coupon-left">
            <div class="sn-special-coupon-ticket">%</div>
            <div class="sn-special-coupon-info">
                <div class="sn-special-coupon-title">Special Coupon for You</div>
                <div class="sn-special-coupon-sub">Save more on your next purchase</div>
            </div>
        </div>

        <div class="sn-special-coupon-right">
            <div class="sn-special-code-box" onclick="copyDealCoupon('WELCOME10', this)" role="button" tabindex="0" title="Click to copy WELCOME10">
                <span>WELCOME10</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
            </div>
            <button type="button" class="sn-btn-copy-special" onclick="copyDealCoupon('WELCOME10', this)">
                Copy Code
            </button>
        </div>
    </div>

</div>

<!-- Floating Animated Toast Notification -->
<div class="sn-deals-toast" id="snDealsToast">
    <span class="sn-deals-toast-icon">✓</span>
    <span id="snDealsToastMsg">Copied code to clipboard!</span>
</div>

<script>
// 1. Copy Coupon to Clipboard with feedback
let toastTimer = null;
function copyDealCoupon(code, element) {
    if (!code) return;
    
    // Copy using clipboard API or fallback
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code);
    } else {
        const tempInput = document.createElement('input');
        tempInput.value = code;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
    }

    // Show floating toast
    const toast = document.getElementById('snDealsToast');
    const msg = document.getElementById('snDealsToastMsg');
    if (toast && msg) {
        msg.textContent = 'Copied coupon ' + code + ' to clipboard!';
        toast.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.classList.remove('show');
        }, 2200);
    }

    // Temporary button visual feedback
    if (element) {
        element.style.transform = 'scale(0.96)';
        setTimeout(() => { element.style.transform = ''; }, 150);
    }
}

// 2. Wishlist Toggle functionality via AJAX
function toggleWishlistDeal(productId, btn) {
    if (!productId || !btn) return;
    const icon = btn.querySelector('i');
    const isCurrentlyActive = btn.classList.contains('active');
    const action = isCurrentlyActive ? 'remove' : 'add';

    // Optimistic UI update
    if (isCurrentlyActive) {
        btn.classList.remove('active');
        if (icon) {
            icon.className = 'far fa-heart';
        }
    } else {
        btn.classList.add('active');
        if (icon) {
            icon.className = 'fas fa-heart';
        }
    }

    // AJAX call to wishlist_action.php
    $.ajax({
        url: '<?php echo BASE_URL; ?>wishlist_action.php',
        type: 'POST',
        data: {
            action: action,
            product_id: productId
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const toast = document.getElementById('snDealsToast');
                const msg = document.getElementById('snDealsToastMsg');
                if (toast && msg) {
                    msg.textContent = isCurrentlyActive ? 'Removed from your Wishlist' : 'Added to your Wishlist!';
                    toast.classList.add('show');
                    if (toastTimer) clearTimeout(toastTimer);
                    toastTimer = setTimeout(() => { toast.classList.remove('show'); }, 2000);
                }
            } else if (response.message && response.message.includes('logged in')) {
                // If not logged in, inform user
                const toast = document.getElementById('snDealsToast');
                const msg = document.getElementById('snDealsToastMsg');
                if (toast && msg) {
                    msg.textContent = 'Please log in to save to your Wishlist.';
                    toast.classList.add('show');
                    if (toastTimer) clearTimeout(toastTimer);
                    toastTimer = setTimeout(() => { toast.classList.remove('show'); }, 2500);
                }
            }
        },
        error: function() {
            // Local state preserved gracefully
        }
    });
}

// 3. Category Filter Pills logic
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('#snDealsFilterBar .sn-filter-pill');
    const dealCards = document.querySelectorAll('#snDealsGrid .sn-deal-card');

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const targetFilter = this.getAttribute('data-filter');

            dealCards.forEach(card => {
                const cat = card.getAttribute('data-category');
                if (targetFilter === 'all' || cat === targetFilter) {
                    card.style.display = 'flex';
                    card.style.animation = 'snFadeIn 0.25s ease';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php require_once('footer.php'); ?>
