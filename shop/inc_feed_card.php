<?php
/**
 * AliExpress Style Product Card Renderer for ShopNext Home Feed
 * Used by both SSR index.php and AJAX fetch_home_feed.php
 */

if (!function_exists('renderAliProductCard')) {
    function renderAliProductCard($p, $currencySymbol = '৳ ') {
        $currPrice = (float)str_replace(',', '', (string)$p['p_current_price']);
        $oldPrice = (float)str_replace(',', '', (string)($p['p_old_price'] ?? '0'));
        $hasDiscount = ($oldPrice > $currPrice && $oldPrice > 0);
        $discountPct = $hasDiscount ? round((($oldPrice - $currPrice) / $oldPrice) * 100) : 0;
        
        $score = isset($p['avg_rating']) ? round((float)$p['avg_rating'], 1) : 0;
        $reviewsCount = isset($p['rev_count']) ? (int)$p['rev_count'] : 0;

        // Image handling
        $prodPhoto = !empty($p['p_featured_photo']) ? $p['p_featured_photo'] : 'assets/images/no-image.png';
        if (!str_starts_with($prodPhoto, 'http')) {
            $prodPhoto = BASE_URL . 'assets/uploads/' . $prodPhoto;
        }

        // SEO friendly URL
        $pUrl = function_exists('getProductURL') ? getProductURL($p['p_id'], $p['p_name'], BASE_URL) : BASE_URL . 'product.php?id=' . $p['p_id'];

        // Realistic AliExpress style sold count derived from views
        $views = (int)($p['p_total_view'] ?? 0);
        $soldCount = max(18, (int)round($views / 2));
        $soldLabel = $soldCount >= 1000 ? round($soldCount / 1000, 1) . 'k+ sold' : $soldCount . '+ sold';

        // Badges
        $isChoice = !empty($p['is_official']) || !empty($p['is_premium']);
        $isFreeShip = !empty($p['is_free_shipping']);
        $isTopSale = !empty($p['is_top_sale']);
        $isFeatured = !empty($p['p_is_featured']);

        ob_start();
        ?>
        <div class="sn-product-card sn-feed-card" data-id="<?php echo (int)$p['p_id']; ?>" data-href="<?php echo htmlspecialchars($pUrl); ?>" style="cursor:pointer; min-width:0; max-width:100%; width:100%; box-sizing:border-box; overflow:hidden;">
            <!-- Top Badges -->
            <div class="sn-feed-top-badges">
                <?php if ($hasDiscount && $discountPct > 0): ?>
                    <span class="sn-badge sn-badge-discount">-<?php echo $discountPct; ?>%</span>
                <?php elseif ($isChoice): ?>
                    <span class="sn-badge sn-badge-choice">Choice</span>
                <?php elseif ($isTopSale): ?>
                    <span class="sn-badge sn-badge-topsale">Best Seller</span>
                <?php elseif ($isFeatured): ?>
                    <span class="sn-badge">Featured</span>
                <?php endif; ?>
            </div>

            <!-- Quick Wishlist Button -->
            <button type="button" class="sn-card-wishlist" title="Save to Wishlist" aria-label="Save to Wishlist" onclick="homeToggleWishlist(<?php echo (int)$p['p_id']; ?>, this, event)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
            </button>

            <!-- Card Link & Image -->
            <a href="<?php echo htmlspecialchars($pUrl); ?>" class="sn-product-card-link" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; flex:1; min-width:0; max-width:100%; width:100%; box-sizing:border-box; overflow:hidden;">
                <div class="sn-product-img-box">
                    <img src="<?php echo htmlspecialchars($prodPhoto); ?>" alt="<?php echo htmlspecialchars($p['p_name']); ?>" loading="lazy" decoding="async" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/no-image.png';">
                </div>

                <!-- Product Title -->
                <h3 class="sn-product-title" title="<?php echo htmlspecialchars($p['p_name']); ?>"><?php echo htmlspecialchars($p['p_name']); ?></h3>
                
                <?php if (!empty($p['p_short_description'])): ?>
                    <p class="sn-product-spec"><?php echo htmlspecialchars($p['p_short_description']); ?></p>
                <?php endif; ?>

                <!-- AliExpress Feature Mini Pills -->
                <div class="sn-feed-pill-row">
                    <?php if ($isChoice): ?>
                        <span class="sn-pill-choice">Choice</span>
                    <?php endif; ?>
                    <?php if ($isFreeShip): ?>
                        <span class="sn-pill-free">Free Delivery</span>
                    <?php endif; ?>
                    <?php if ($score >= 4.5): ?>
                        <span class="sn-pill-top"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block; vertical-align:-1px; margin-right:2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><?php echo $score; ?></span>
                    <?php endif; ?>
                </div>

                <!-- Social Proof: Rating & Sold Count -->
                <div class="sn-product-rating">
                    <span class="sn-rating-star"><svg width="11" height="11" viewBox="0 0 24 24" fill="#f59e0b" stroke="none" style="display:inline-block; vertical-align:-1px; margin-right:1px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                    <span><?php echo ($score > 0 ? $score : '4.8'); ?></span>
                    <?php if ($reviewsCount > 0): ?>
                        <span class="sn-rating-count">(<?php echo $reviewsCount; ?>)</span>
                    <?php endif; ?>
                    <span class="sn-feed-dot-sep">·</span>
                    <span class="sn-feed-sales"><?php echo $soldLabel; ?></span>
                </div>
            </a>

            <!-- Bottom Price & Cart Action -->
            <div class="sn-product-bottom">
                <div class="sn-price-box">
                    <div class="sn-price-row">
                        <span class="sn-current-price"><?php echo $currencySymbol . number_format($currPrice); ?></span>
                        <?php if ($hasDiscount): ?>
                            <span class="sn-old-price"><?php echo $currencySymbol . number_format($oldPrice); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="sn-btn-cart" onclick="homeAddToCart(<?php echo (int)$p['p_id']; ?>, '<?php echo htmlspecialchars(addslashes($p['p_name'])); ?>', this)" title="Add to cart" aria-label="Add to Cart">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

