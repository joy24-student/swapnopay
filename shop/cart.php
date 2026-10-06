<?php 
$cur_page = 'cart.php';
require_once('header.php'); 

// -------------------------------------------------------------------------
// 1. ENSURE CART ARRAYS ARE PROPERLY INITIALIZED
// -------------------------------------------------------------------------
if (!isset($_SESSION['cart_p_id']) || !is_array($_SESSION['cart_p_id'])) {
    $_SESSION['cart_p_id'] = [];
    $_SESSION['cart_size_id'] = [];
    $_SESSION['cart_size_name'] = [];
    $_SESSION['cart_color_id'] = [];
    $_SESSION['cart_color_name'] = [];
    $_SESSION['cart_p_qty'] = [];
    $_SESSION['cart_p_current_price'] = [];
    $_SESSION['cart_p_name'] = [];
    $_SESSION['cart_p_featured_photo'] = [];
    $_SESSION['cart_p_old_price'] = [];
    $_SESSION['cart_p_subtitle'] = [];
    $_SESSION['cart_p_badge'] = [];
}

// -------------------------------------------------------------------------
// 2. DATABASE SCHEMA SAFEGUARDS & PERSISTENT CART SYNC
// -------------------------------------------------------------------------
try {
    // Ensure customer cart table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tbl_customer_carts` (
      `cart_id` int(11) NOT NULL AUTO_INCREMENT,
      `customer_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `size_id` int(11) DEFAULT 0,
      `size_name` varchar(255) DEFAULT '',
      `color_id` int(11) DEFAULT 0,
      `color_name` varchar(255) DEFAULT '',
      `quantity` int(11) NOT NULL DEFAULT 1,
      `price_at_add` decimal(10,2) NOT NULL DEFAULT 0.00,
      `product_name` varchar(255) DEFAULT '',
      `product_photo` varchar(255) DEFAULT NULL,
      `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`cart_id`),
      KEY `customer_id` (`customer_id`),
      KEY `product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure wishlist table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tbl_wishlist` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `cust_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `added_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `cust_id` (`cust_id`),
      KEY `product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

// If customer is logged in, load persisted cart from database if session is empty
if (empty($_SESSION['cart_p_id']) && isset($_SESSION['customer']['cust_id'])) {
    loadCartFromDatabase($pdo, (int)$_SESSION['customer']['cust_id']);
}

// -------------------------------------------------------------------------
// 3. LIVE PRODUCT DATA HYDRATION FROM DATABASE (tbl_product)
// -------------------------------------------------------------------------
if (!empty($_SESSION['cart_p_id'])) {
    $numeric_pids = array_unique(array_filter($_SESSION['cart_p_id'], function($id) {
        return is_numeric($id) && (int)$id > 0;
    }));

    if (!empty($numeric_pids)) {
        try {
            $inClause = implode(',', array_fill(0, count($numeric_pids), '?'));
            $prodHydrateStmt = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_old_price, p_qty, p_featured_photo, p_short_description FROM tbl_product WHERE p_id IN ($inClause) AND p_is_active = 1");
            $prodHydrateStmt->execute(array_values($numeric_pids));
            $liveProducts = [];
            while ($pRow = $prodHydrateStmt->fetch(PDO::FETCH_ASSOC)) {
                $liveProducts[$pRow['p_id']] = $pRow;
            }

            foreach ($_SESSION['cart_p_id'] as $k => $pid) {
                if (isset($liveProducts[$pid])) {
                    $_SESSION['cart_p_name'][$k] = $liveProducts[$pid]['p_name'];
                    $_SESSION['cart_p_current_price'][$k] = (float)$liveProducts[$pid]['p_current_price'];
                    if (!empty($liveProducts[$pid]['p_old_price'])) {
                        $_SESSION['cart_p_old_price'][$k] = (float)$liveProducts[$pid]['p_old_price'];
                    }
                    if (!empty($liveProducts[$pid]['p_featured_photo'])) {
                        $_SESSION['cart_p_featured_photo'][$k] = $liveProducts[$pid]['p_featured_photo'];
                    }
                    if (!empty($liveProducts[$pid]['p_short_description'])) {
                        $_SESSION['cart_p_subtitle'][$k] = strip_tags($liveProducts[$pid]['p_short_description']);
                    }
                }
            }
        } catch (Exception $e) {}
    }
}

// -------------------------------------------------------------------------
// 5. PREPARE CART DATA FOR RENDERING
// -------------------------------------------------------------------------
$cart_p_ids = array_values($_SESSION['cart_p_id'] ?? []);
$cart_size_ids = array_values($_SESSION['cart_size_id'] ?? []);
$cart_size_names = array_values($_SESSION['cart_size_name'] ?? []);
$cart_color_ids = array_values($_SESSION['cart_color_id'] ?? []);
$cart_color_names = array_values($_SESSION['cart_color_name'] ?? []);
$cart_p_qtys = array_values($_SESSION['cart_p_qty'] ?? []);
$cart_p_current_prices = array_values($_SESSION['cart_p_current_price'] ?? []);
$cart_p_names = array_values($_SESSION['cart_p_name'] ?? []);
$cart_p_featured_photos = array_values($_SESSION['cart_p_featured_photo'] ?? []);
$cart_p_old_prices = array_values($_SESSION['cart_p_old_price'] ?? []);
$cart_p_subtitles = array_values($_SESSION['cart_p_subtitle'] ?? []);
$cart_p_badges = array_values($_SESSION['cart_p_badge'] ?? []);

$total_cart_items = count($cart_p_ids);
$currency = '৳ ';

// Calculate initial subtotal & savings
$initial_subtotal = 0;
$initial_savings = 0;
for ($i = 0; $i < $total_cart_items; $i++) {
    $qty = (int)($cart_p_qtys[$i] ?? 1);
    $price = (float)($cart_p_current_prices[$i] ?? 0);
    $old_price = (float)($cart_p_old_prices[$i] ?? ($price * 1.2));
    $initial_subtotal += ($price * $qty);
    if ($old_price > $price) {
        $initial_savings += (($old_price - $price) * $qty);
    }
}

// Check coupon discount
$coupon_discount = 0;
$applied_coupon = $_SESSION['cart_coupon'] ?? null;
if ($applied_coupon) {
    if ($applied_coupon['type'] === 'percentage') {
        $coupon_discount = ($initial_subtotal * (float)$applied_coupon['value']) / 100;
    } else {
        $coupon_discount = min($initial_subtotal, (float)$applied_coupon['value']);
    }
}

$initial_total = max(0, $initial_subtotal - $coupon_discount);

// -------------------------------------------------------------------------
// 6. RECOMMENDATIONS: "You Might Also Like" (Connected to tbl_product)
// -------------------------------------------------------------------------
$recommendations = [];
try {
    $recStmt = $pdo->query("SELECT p_id, p_name, p_short_description, p_current_price, p_old_price, p_featured_photo FROM tbl_product WHERE p_is_active = 1 ORDER BY p_is_featured DESC, p_id DESC LIMIT 4");
    $dbRecRows = $recStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dbRecRows as $dr) {
        $p_id = (int)$dr['p_id'];
        $p_price = (float)$dr['p_current_price'];
        $p_old = !empty($dr['p_old_price']) ? (float)$dr['p_old_price'] : 0;
        $disc = ($p_old > $p_price && $p_old > 0) ? round((($p_old - $p_price) / $p_old) * 100) . '% OFF' : '';
        $img = !empty($dr['p_featured_photo']) 
            ? (str_starts_with($dr['p_featured_photo'], 'http') ? $dr['p_featured_photo'] : 'assets/uploads/' . $dr['p_featured_photo']) 
            : 'assets/uploads/default_product.jpg';
        $recommendations[] = [
            'id' => $p_id,
            'name' => $dr['p_name'],
            'subtitle' => !empty($dr['p_short_description']) ? strip_tags($dr['p_short_description']) : '',
            'price' => $p_price,
            'old_price' => $p_old,
            'discount' => $disc,
            'image' => $img
        ];
    }
} catch (Exception $e) {}
?>

<div class="sn-cart-page-wrapper">
    <div class="sn-container">
        
        <!-- Top Title Bar (Identical layout to deals.php) -->
        <div class="sn-deals-title-bar">
            <a href="index.php" class="sn-deals-back-btn" aria-label="Go back">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
            </a>
            <div class="sn-deals-title-content">
                <h1 class="sn-deals-title">Cart <span class="sn-deals-badge-sparkle">🛒</span></h1>
                <p class="sn-deals-subtitle">Review items, apply coupons & quick checkout <span id="pageItemCount" style="display:none;"><?php echo $total_cart_items; ?></span></p>
            </div>
        </div>

        <?php if ($total_cart_items === 0): ?>
            <!-- ========================================================
                 EMPTY CART STATE
                 ======================================================== -->
            <div class="sn-empty-cart-stage" id="snEmptyCartView">
                <div class="sn-empty-icon-ring">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </div>
                <h2>Your Cart is Empty</h2>
                <p>Looks like you haven't added anything to your cart yet.</p>
                <div class="sn-empty-actions">
                    <a href="index.php" class="sn-btn-primary">Start Shopping</a>
                </div>
            </div>
        <?php else: ?>

            <!-- ========================================================
                 MAIN TWO-COLUMN GRID (Desktop & Tablet)
                 ======================================================== -->
            <div class="sn-cart-layout-grid">
                
                <!-- LEFT COLUMN: CART ITEMS LIST -->
                <div class="sn-cart-left-col">
                    
                    <!-- Main Items Container Card -->
                    <div class="sn-cart-card">
                        
                        <!-- Toolbar Row: Select All + Bulk Actions -->
                        <div class="sn-cart-toolbar">
                            <label class="sn-checkbox-label">
                                <input type="checkbox" id="selectAllCheckbox" checked>
                                <span class="sn-custom-check"></span>
                                <span class="sn-toolbar-text">Select All (<span id="selectedCountToolbar"><?php echo $total_cart_items; ?></span>)</span>
                            </label>

                            <div class="sn-toolbar-actions">
                                <button type="button" class="sn-tool-btn" onclick="batchMoveToWishlist()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                    <span>Move to Wishlist</span>
                                </button>
                                <button type="button" class="sn-tool-btn" onclick="batchRemoveSelected()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    <span>Remove</span>
                                </button>
                            </div>
                        </div>

                        <!-- Product Rows List -->
                        <div class="sn-cart-items-list" id="cartItemsList">
                            <?php 
                            for ($i = 0; $i < $total_cart_items; $i++): 
                                $p_id = $cart_p_ids[$i];
                                $p_name = $cart_p_names[$i];
                                $p_qty = (int)($cart_p_qtys[$i] ?? 1);
                                $p_price = (float)($cart_p_current_prices[$i] ?? 0);
                                $p_old_price = (float)($cart_p_old_prices[$i] ?? ($p_price * 1.18));
                                $p_photo = $cart_p_featured_photos[$i];
                                if (!str_starts_with($p_photo, 'http') && !empty($p_photo)) {
                                    $p_photo = 'assets/uploads/' . $p_photo;
                                }
                                $p_subtitle = $cart_p_subtitles[$i] ?? 'Standard Edition';
                                $p_badge = $cart_p_badges[$i] ?? '';
                                $p_color = $cart_color_names[$i] ?? 'Default';
                                $discount_pct = round((($p_old_price - $p_price) / max(1, $p_old_price)) * 100);
                                $row_total = $p_price * $p_qty;
                            ?>
                            <div class="sn-cart-item-row" data-index="<?php echo $i; ?>" data-id="<?php echo $p_id; ?>" data-price="<?php echo $p_price; ?>" data-old-price="<?php echo $p_old_price; ?>">
                                
                                <!-- Selection Checkbox -->
                                <div class="sn-item-checkbox-cell">
                                    <label class="sn-checkbox-label">
                                        <input type="checkbox" class="sn-item-checkbox" value="<?php echo $i; ?>" checked onchange="handleItemCheckboxChange()">
                                        <span class="sn-custom-check"></span>
                                    </label>
                                </div>

                                <!-- Thumbnail with optional badge -->
                                <div class="sn-item-image-cell">
                                    <div class="sn-img-container">
                                        <?php if (!empty($p_badge)): ?>
                                            <span class="sn-top-rated-tag">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                <?php echo htmlspecialchars($p_badge); ?>
                                            </span>
                                        <?php endif; ?>
                                        <img src="<?php echo htmlspecialchars($p_photo); ?>" alt="<?php echo htmlspecialchars($p_name); ?>" loading="lazy" onerror="this.src='assets/uploads/default_product.jpg'">
                                    </div>
                                </div>

                                <!-- Product Details -->
                                <div class="sn-item-details-cell">
                                    <h2 class="sn-item-name">
                                        <a href="product.php?id=<?php echo $p_id; ?>"><?php echo htmlspecialchars($p_name); ?></a>
                                    </h2>
                                    
                                    <?php if (!empty($p_subtitle)): ?>
                                        <div class="sn-item-specs"><?php echo htmlspecialchars($p_subtitle); ?></div>
                                    <?php endif; ?>

                                    <!-- Color Swatch Row -->
                                    <?php if (!empty($p_color) && $p_color !== 'Default'): ?>
                                    <div class="sn-item-variant-swatches">
                                        <span class="sn-swatch-label">Color: <strong class="sn-active-color-name"><?php echo htmlspecialchars($p_color); ?></strong></span>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Stock Status Badge -->
                                    <div class="sn-stock-status">
                                        <span class="sn-stock-pill in-stock">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            In Stock
                                        </span>
                                    </div>

                                    <!-- Bottom Action Links (Move to Wishlist | Remove) -->
                                    <div class="sn-item-action-links">
                                        <button type="button" class="sn-item-link-btn" onclick="moveToWishlist(<?php echo $i; ?>, <?php echo $p_id; ?>, '<?php echo addslashes($p_name); ?>')">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                            <span>Move to Wishlist</span>
                                        </button>
                                        <button type="button" class="sn-item-link-btn" onclick="removeSingleItem(<?php echo $i; ?>, <?php echo $p_id; ?>)">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            <span>Remove</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Unit Price & Stepper Control -->
                                <div class="sn-item-pricing-cell">
                                    <div class="sn-price-stack">
                                        <div class="sn-curr-price">৳ <?php echo number_format($p_price); ?></div>
                                        <?php if ($p_old_price > $p_price): ?>
                                            <div class="sn-old-price">৳ <?php echo number_format($p_old_price); ?></div>
                                            <div class="sn-discount-badge"><?php echo $discount_pct; ?>% OFF</div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Quantity Stepper -->
                                    <div class="sn-stepper-control">
                                        <button type="button" class="sn-step-btn minus" onclick="updateItemQuantity(<?php echo $i; ?>, -1)" aria-label="Decrease quantity">−</button>
                                        <input type="text" readonly class="sn-qty-input" value="<?php echo $p_qty; ?>" data-index="<?php echo $i; ?>" aria-label="Quantity">
                                        <button type="button" class="sn-step-btn plus" onclick="updateItemQuantity(<?php echo $i; ?>, 1)" aria-label="Increase quantity">+</button>
                                    </div>
                                </div>

                                <!-- Row Item Total -->
                                <div class="sn-item-total-cell">
                                    <div class="sn-item-total-price" id="lineTotal-<?php echo $i; ?>">
                                        ৳ <?php echo number_format($row_total); ?>
                                    </div>
                                </div>

                            </div>
                            <?php endfor; ?>
                        </div>

                    </div>

                    <!-- Shop with Confidence Banner -->
                    <div class="sn-confidence-banner">
                        <div class="sn-conf-left">
                            <div class="sn-conf-shield">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <path d="m9 12 2 2 4-4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="sn-conf-title">Shop with Confidence</h3>
                                <p class="sn-conf-desc">Secure checkout • 7-day return • 100% genuine products</p>
                            </div>
                        </div>
                        <a href="about.php" class="sn-conf-learn-more">
                            <span>Learn More</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                    </div>

                </div>

                <!-- RIGHT COLUMN: ORDER SUMMARY CARD -->
                <div class="sn-cart-right-col">
                    <div class="sn-order-summary-card">
                        <h2 class="sn-summary-heading">Order Summary</h2>

                        <!-- Breakdown lines -->
                        <div class="sn-summary-rows">
                            <div class="sn-summary-row">
                                <span class="label">Subtotal (<span id="summarySelectedCount"><?php echo $total_cart_items; ?></span> items)</span>
                                <span class="val" id="summarySubtotalVal">৳ <?php echo number_format($initial_subtotal); ?></span>
                            </div>
                            <div class="sn-summary-row">
                                <span class="label">Shipping</span>
                                <span class="val sn-shipping-free">FREE</span>
                            </div>
                            <div class="sn-summary-row">
                                <span class="label">Tax (estimated)</span>
                                <span class="val" id="summaryTaxVal">৳ 0</span>
                            </div>

                            <div class="sn-summary-row sn-coupon-row <?php echo !empty($applied_coupon) ? 'active' : ''; ?>" id="summaryCouponRow">
                                <span class="label">Coupon Discount (<span id="couponCodeLabel"><?php echo htmlspecialchars($applied_coupon['code'] ?? ''); ?></span>)</span>
                                <span class="val text-green" id="couponDiscountVal">-৳ <?php echo number_format($coupon_discount); ?></span>
                            </div>
                        </div>

                        <hr class="sn-summary-divider">

                        <!-- Total Line -->
                        <div class="sn-total-line">
                            <span class="sn-total-label">Total</span>
                            <span class="sn-total-amount" id="summaryTotalVal">৳ <?php echo number_format($initial_total); ?></span>
                        </div>

                        <!-- Savings Badge Tag -->
                        <div class="sn-savings-pill" id="savingsPillWrap">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.2">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                <line x1="7" y1="7" x2="7.01" y2="7"></line>
                            </svg>
                            <span>You save <strong id="savingsTotalAmount">৳ <?php echo number_format($initial_savings + $coupon_discount); ?></strong></span>
                        </div>

                        <!-- Have a Promo Code? Box -->
                        <div class="sn-promo-card">
                            <div class="sn-promo-header">
                                <div class="sn-promo-tag-icon">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2.2">
                                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                    </svg>
                                </div>
                                <div>
                                    <div class="sn-promo-title">Have a Promo Code?</div>
                                    <div class="sn-promo-sub">Enter your code to get discounts</div>
                                </div>
                            </div>

                            <div class="sn-promo-input-row">
                                <input type="text" id="promoInput" class="sn-promo-input" placeholder="Enter promo code" value="<?php echo htmlspecialchars($applied_coupon['code'] ?? ''); ?>">
                                <button type="button" class="sn-promo-btn" id="promoApplyBtn" onclick="handleApplyPromo()">Apply</button>
                            </div>
                            <div class="sn-promo-alert" id="promoAlertBox"></div>
                        </div>

                        <!-- Proceed to Checkout Primary Button -->
                        <form action="checkout.php" method="post" id="checkoutForm">
                            <?php $csrf->echoInputField(); ?>
                            <input type="hidden" name="selected_indexes" id="selectedIndexesInput" value="">
                            <button type="submit" class="sn-btn-checkout" id="proceedCheckoutBtn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                <span>Proceed to Checkout</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                            </button>
                        </form>

                        <!-- OR Divider -->
                        <div class="sn-or-divider">
                            <span>OR</span>
                        </div>

                        <!-- Payment Methods Logos -->
                        <div class="sn-payment-icons-row">
                            <!-- Apple Pay -->
                            <div class="sn-pay-pill" title="Apple Pay">
                                <svg width="34" height="16" viewBox="0 0 40 20" fill="#111827">
                                    <path d="M10.8 11.2c-.3 2.1-1.8 4.2-3.8 4.1-1-.1-1.9-.7-2.6-.7-.8 0-1.7.7-2.7.7-2 0-3.6-2.1-3.6-4.5 0-3 1.9-4.6 3.8-4.6 1 0 1.9.7 2.5.7.6 0 1.6-.7 2.8-.7 1 .1 2.3.6 3 1.6-2 1.2-1.7 3.9.6 4.7zm-2.8-7.7c.5-.7.9-1.6.8-2.5-.8 0-1.8.6-2.3 1.2-.5.6-.9 1.5-.8 2.4.9.1 1.8-.4 2.3-1.1zM18.5 4.8h3.3v10.4h-1.5v-1.6c-.6 1.1-1.7 1.8-2.8 1.8-2.3 0-3.7-1.8-3.7-4.4 0-2.6 1.5-4.4 3.7-4.4 1.1 0 2.1.6 2.7 1.7v-3.5zm-1.4 9.1c1.5 0 2.4-1.2 2.4-3.1 0-1.8-.9-3.1-2.4-3.1-1.5 0-2.4 1.2-2.4 3.1 0 1.8.9 3.1 2.4 3.1zM28.8 13.9c-.4.9-1.2 1.5-2.2 1.5-1.5 0-2.4-1.1-2.4-2.8 0-1.7 1-2.8 2.4-2.8.9 0 1.8.6 2.2 1.5v2.6zm0-5.1c-.6-.7-1.4-1.1-2.3-1.1-2.2 0-3.8 1.8-3.8 4.3 0 2.6 1.5 4.3 3.8 4.3 1 0 1.8-.5 2.3-1.2v1.1h1.5V7.9h-1.5v.9zM34.8 7.9l-2.4 6.7h-1.5l.9-2.2-2.3-4.5h1.7l1.4 3.2 1.4-3.2h1.8z"/>
                                </svg>
                            </div>
                            <!-- Google Pay -->
                            <div class="sn-pay-pill" title="Google Pay">
                                <svg width="34" height="16" viewBox="0 0 40 20">
                                    <path d="M12.4 10.3c0-.3 0-.7-.1-1H6v2.1h3.6c-.2.9-.7 1.7-1.5 2.2v1.8h2.4c1.4-1.3 2.2-3.2 2.2-5.1z" fill="#4285F4"/>
                                    <path d="M6 16.8c1.9 0 3.6-.6 4.7-1.7l-2.4-1.8c-.6.4-1.4.7-2.3.7-1.8 0-3.3-1.2-3.8-2.9H-.1v1.9C1.1 15.3 3.4 16.8 6 16.8z" fill="#34A853"/>
                                    <path d="M2.2 11.1c-.2-.7-.2-1.5 0-2.2V7H-.1C-.6 8.3-.6 9.7-.1 11.1l2.3-1.8z" fill="#FBBC05"/>
                                    <path d="M6 5.4c1 0 2 .4 2.7 1.1l2-2C9.4 3.3 7.8 2.6 6 2.6 3.4 2.6 1.1 4.1-.1 6.5l2.3 1.8c.5-1.7 2-2.9 3.8-2.9z" fill="#EA4335"/>
                                    <path d="M18.8 5.6h3.4v7.4h-1.5v-1.1c-.4.8-1.3 1.3-2.2 1.3-1.8 0-3-1.3-3-3.1 0-1.8 1.2-3.1 3-3.1.9 0 1.7.4 2.2 1.2V5.6zm-.9 6.5c1.1 0 1.8-.8 1.8-2.2 0-1.3-.7-2.1-1.8-2.1-1.1 0-1.8.8-1.8 2.1 0 1.4.7 2.2 1.8 2.2zM28.4 11.2l-2.1 4.8h-1.5l.8-1.8-2-4.8h1.6l1.2 3.1 1.2-3.1h1.6z" fill="#5F6368"/>
                                </svg>
                            </div>
                            <!-- VISA -->
                            <div class="sn-pay-pill" title="VISA">
                                <span class="sn-visa-text">VISA</span>
                            </div>
                            <!-- Mastercard -->
                            <div class="sn-pay-pill" title="Mastercard">
                                <span class="sn-mc-circles">
                                    <span class="mc-circle red"></span>
                                    <span class="mc-circle yellow"></span>
                                </span>
                            </div>
                            <!-- bKash -->
                            <div class="sn-pay-pill bkash-pill" title="bKash">
                                <span class="sn-bkash-text">bKash</span>
                            </div>
                        </div>

                        <!-- 256-bit SSL Security text -->
                        <div class="sn-ssl-guarantee">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                            <div>
                                <div class="ssl-main">Your payment information is safe and secure</div>
                                <div class="ssl-sub">With 256-bit SSL encryption.</div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        <?php endif; ?>

        <!-- ============================================================
             "YOU MIGHT ALSO LIKE" SECTION
             ============================================================ -->
        <section class="sn-recommendations-section">
            <div class="sn-recom-head">
                <h2 class="sn-recom-title">You Might Also Like</h2>
                <a href="product-category.php" class="sn-recom-link">
                    <span>View All</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
            </div>

            <div class="sn-recom-grid">
                <?php foreach ($recommendations as $rec): ?>
                <div class="sn-recom-card">
                    <a href="product.php?id=<?php echo $rec['id']; ?>" class="sn-recom-img-box">
                        <img src="<?php echo htmlspecialchars($rec['image']); ?>" alt="<?php echo htmlspecialchars($rec['name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='assets/uploads/default_product.jpg';">
                    </a>
                    <div class="sn-recom-content">
                        <h4 class="sn-recom-item-name">
                            <a href="product.php?id=<?php echo $rec['id']; ?>"><?php echo htmlspecialchars($rec['name']); ?></a>
                        </h4>
                        <?php if (!empty($rec['subtitle'])): ?>
                            <div class="sn-recom-item-sub"><?php echo htmlspecialchars($rec['subtitle']); ?></div>
                        <?php endif; ?>
                        
                        <div class="sn-recom-price-row">
                            <span class="sn-recom-price">৳ <?php echo number_format($rec['price']); ?></span>
                            <?php if ($rec['old_price'] > $rec['price']): ?>
                                <span class="sn-recom-old-price">৳ <?php echo number_format($rec['old_price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($rec['discount'])): ?>
                            <div class="sn-recom-discount"><?php echo htmlspecialchars($rec['discount']); ?></div>
                        <?php endif; ?>

                        <button type="button" class="sn-recom-add-btn" onclick="quickAddToCart(<?php echo $rec['id']; ?>, '<?php echo addslashes($rec['name']); ?>', <?php echo $rec['price']; ?>, '<?php echo htmlspecialchars($rec['image']); ?>', '<?php echo addslashes($rec['subtitle']); ?>')" title="Add to Cart" aria-label="Add to cart">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#111827" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
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

        <!-- ============================================================
             BOTTOM TRUST / ASSURANCE BAR
             ============================================================ -->
        <div class="sn-trust-assurance-bar">
            <!-- 1. Free Shipping -->
            <div class="sn-trust-cell">
                <div class="sn-trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
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
            <div class="sn-trust-cell">
                <div class="sn-trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>
                </div>
                <div class="sn-trust-info">
                    <h4>Secure Payment</h4>
                    <p>100% secure payment</p>
                </div>
            </div>

            <!-- 3. Easy Returns -->
            <div class="sn-trust-cell">
                <div class="sn-trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                </div>
                <div class="sn-trust-info">
                    <h4>Easy Returns</h4>
                    <p>30-day return policy</p>
                </div>
            </div>

            <!-- 4. 24/7 Support -->
            <div class="sn-trust-cell">
                <div class="sn-trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
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

    </div>
</div>

<!-- ============================================================
     STICKY MOBILE FLOATING VOUCHER BAR & CHECKOUT DOCK (IMAGE 2)
     ============================================================ -->
<?php if ($total_cart_items > 0): ?>
<div class="sn-mobile-sticky-dock">
    <!-- Floating Voucher Teaser -->
    <div class="sn-mobile-voucher-bar">
        <div class="sn-mv-left">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#e11d48">
                <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/>
            </svg>
            <span>Buy ৳ 299 save 5% off</span>
        </div>
        <button type="button" class="sn-mv-add-btn" onclick="applyPromo('SAVE5')">Add</button>
    </div>

    <!-- Bottom Action Dock -->
    <div class="sn-mobile-dock-row">
        <label class="sn-mobile-all-check">
            <input type="checkbox" id="mobileSelectAll" checked onchange="toggleSelectAllFromMobile(this)">
            <span class="sn-custom-check"></span>
            <span>All</span>
        </label>

        <div class="sn-mobile-totals-col">
            <div class="sn-mob-subtotal">Subtotal: <strong id="mobileSubtotalVal">৳ <?php echo number_format($initial_total); ?></strong></div>
            <div class="sn-mob-shipping">Shipping Fee: <span>৳ 0</span></div>
        </div>

        <button type="button" class="sn-mobile-checkout-btn" onclick="submitMobileCheckout()">
            Checkout(<span id="mobileCheckoutBadge"><?php echo $total_cart_items; ?></span>)
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     TOAST NOTIFICATIONS
     ============================================================ -->
<div id="snToastContainer" class="sn-toast-container"></div>

<!-- ============================================================
     PIXEL-PERFECT CSS STYLING
     ============================================================ -->
<style>
/* CSS VARIABLES & BASE RESET */
:root {
    --sn-font: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --sn-primary: #fab802;
    --sn-primary-hover: #e5a700;
    --sn-primary-light: #fffbeb;
    --sn-primary-active: #d97706;
    --sn-dark: #0f172a;
    --sn-dark-soft: #1e293b;
    --sn-muted: #64748b;
    --sn-muted-light: #94a3b8;
    --sn-border: #f1f5f9;
    --sn-border-strong: #e2e8f0;
    --sn-bg-page: #f8fafc;
    --sn-card-bg: #ffffff;
    --sn-green: #059669;
    --sn-green-bg: #ecfdf5;
    --sn-red: #ef4444;
    --sn-red-bg: #fee2e2;
}

body.shopnext-theme {
    background-color: var(--sn-bg-page);
    font-family: var(--sn-font);
    color: var(--sn-dark);
}

/* Sticky Right Summary Column on Desktop */
@media (min-width: 1025px) {
    .sn-cart-right-col {
        position: sticky;
        top: 24px;
        align-self: start;
    }
}

.sn-cart-page-wrapper {
    padding: 24px 0 60px 0;
}

/* 1. TOP TITLE BAR (MATCHING DEALS SCREEN) */
.sn-deals-title-bar {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
}
.sn-deals-back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    color: var(--sn-dark);
    text-decoration: none;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: all 0.15s ease;
    flex-shrink: 0;
    margin-top: 1px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.sn-deals-back-btn:hover {
    background-color: #f1f5f9;
    transform: translateX(-2px);
    color: var(--sn-dark);
}
.sn-deals-title-content {
    flex: 1;
}
.sn-deals-title {
    font-size: 22px;
    font-weight: 800;
    color: var(--sn-dark);
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 2px 0;
    line-height: 1.2;
    letter-spacing: -0.3px;
}
.sn-deals-badge-sparkle {
    font-size: 20px;
    line-height: 1;
}
.sn-deals-subtitle {
    font-size: 13px;
    color: var(--sn-muted);
    margin: 0;
    line-height: 1.35;
    font-weight: 500;
}
@media (min-width: 769px) {
    .sn-deals-title-bar {
        margin-bottom: 24px;
    }
    .sn-deals-title {
        font-size: 26px;
    }
    .sn-deals-subtitle {
        font-size: 14px;
    }
}

/* Auto-saved notification badge */
.sn-auto-saved-badge {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    padding: 10px 18px;
    border-radius: 9999px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.sn-saved-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}
.sn-saved-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--sn-dark);
}
.sn-saved-desc {
    font-size: 11.5px;
    color: var(--sn-muted);
}

/* 3. TWO-COLUMN LAYOUT */
.sn-cart-layout-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 380px;
    gap: 28px;
    align-items: start;
    margin-bottom: 48px;
}

/* 4. LEFT COLUMN - CART ITEMS CARD */
.sn-cart-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
    overflow: hidden;
}

/* Toolbar */
.sn-cart-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    border-bottom: 1px solid #f8fafc;
}
.sn-toolbar-text {
    font-size: 14px;
    font-weight: 700;
    color: var(--sn-dark);
    user-select: none;
}
.sn-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}
.sn-tool-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    color: var(--sn-muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    padding: 4px 0;
    transition: all 0.2s ease;
}
.sn-tool-btn:hover {
    color: var(--sn-dark);
}

/* Custom Checkbox */
.sn-checkbox-label {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    position: relative;
    user-select: none;
    margin: 0;
}
.sn-checkbox-label input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}
.sn-custom-check {
    width: 20px;
    height: 20px;
    background-color: #ffffff;
    border: 2px solid #cbd5e1;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease-in-out;
}
.sn-checkbox-label:hover input ~ .sn-custom-check {
    border-color: #f59e0b;
}
.sn-checkbox-label input:checked ~ .sn-custom-check {
    background-color: #f59e0b;
    border-color: #f59e0b;
}
.sn-checkbox-label input:checked ~ .sn-custom-check:after {
    content: "";
    display: block;
    width: 5px;
    height: 9px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    margin-bottom: 2px;
}

/* Product Rows */
.sn-cart-items-list {
    display: flex;
    flex-direction: column;
}
.sn-cart-item-row {
    display: grid;
    grid-template-columns: 24px 110px minmax(0, 1fr) 140px 110px;
    gap: 20px;
    align-items: center;
    padding: 24px;
    border-bottom: 1px solid #f8fafc;
    transition: all 0.3s ease;
}
.sn-cart-item-row:last-child {
    border-bottom: none;
}
.sn-cart-item-row.removing {
    opacity: 0;
    transform: translateX(-30px);
}

/* Item Image */
.sn-img-container {
    width: 110px;
    height: 100px;
    background: #f8fafc;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    border: 1px solid #f1f5f9;
}
.sn-img-container img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    transition: transform 0.25s ease;
}
.sn-img-container:hover img {
    transform: scale(1.06);
}
.sn-top-rated-tag {
    position: absolute;
    top: 6px;
    left: 6px;
    background: #fffbeb;
    border: 1px solid #fef3c7;
    color: #92400e;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 3px;
    z-index: 2;
}

/* Item Details */
.sn-item-name {
    font-size: 15.5px;
    font-weight: 700;
    margin: 0 0 5px 0;
    line-height: 1.35;
}
.sn-item-name a {
    color: var(--sn-dark);
    text-decoration: none;
    transition: color 0.15s;
}
.sn-item-name a:hover {
    color: #f59e0b;
}
.sn-item-specs {
    font-size: 12.5px;
    color: var(--sn-muted);
    margin-bottom: 8px;
}
.sn-item-variant-swatches {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 12.5px;
    color: var(--sn-muted);
}
.sn-active-color-name {
    color: var(--sn-dark);
}
.sn-swatch-dots {
    display: flex;
    align-items: center;
    gap: 5px;
}
.sn-color-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background-color: #cbd5e1;
    border: 1px solid rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}
.sn-color-dot.dark { background-color: #334155; }
.sn-color-dot.silver { background-color: #e2e8f0; }
.sn-color-dot.active {
    box-shadow: 0 0 0 2px #ffffff, 0 0 0 3.5px #f59e0b;
}

/* In Stock badge */
.sn-stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
}
.sn-stock-pill.in-stock {
    background-color: #ecfdf5;
    color: #059669;
}

/* Item Action Links */
.sn-item-action-links {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 10px;
}
.sn-item-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: none;
    border: none;
    font-size: 12px;
    font-weight: 600;
    color: var(--sn-muted);
    cursor: pointer;
    padding: 0;
    transition: color 0.15s;
}
.sn-item-link-btn:hover {
    color: var(--sn-dark);
}

/* Pricing & Stepper */
.sn-price-stack {
    margin-bottom: 12px;
}
.sn-curr-price {
    font-size: 17px;
    font-weight: 800;
    color: var(--sn-dark);
    line-height: 1.2;
}
.sn-old-price {
    font-size: 12.5px;
    color: #94a3b8;
    text-decoration: line-through;
    margin-top: 2px;
}
.sn-discount-badge {
    display: inline-block;
    background: #fee2e2;
    color: #ef4444;
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    margin-top: 4px;
}

/* Stepper */
.sn-stepper-control {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 9999px;
    padding: 2px 6px;
    gap: 6px;
}
.sn-step-btn {
    width: 26px;
    height: 26px;
    border: none;
    background: none;
    color: var(--sn-dark);
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.15s;
}
.sn-step-btn:hover {
    background-color: #f1f5f9;
}
.sn-qty-input {
    width: 24px;
    border: none;
    background: none;
    text-align: center;
    font-size: 14px;
    font-weight: 700;
    color: var(--sn-dark);
    outline: none;
}

/* Item Total */
.sn-item-total-cell {
    text-align: right;
}
.sn-item-total-price {
    font-size: 18px;
    font-weight: 800;
    color: var(--sn-dark);
    letter-spacing: -0.3px;
}

/* Shop with Confidence Banner */
.sn-confidence-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 16px;
    padding: 16px 22px;
    margin-top: 20px;
    gap: 16px;
}
.sn-conf-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.sn-conf-shield {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(59, 130, 246, 0.1);
}
.sn-conf-title {
    font-size: 14px;
    font-weight: 800;
    color: #1e3a8a;
    margin: 0;
}
.sn-conf-desc {
    font-size: 12.5px;
    color: #3b82f6;
    margin: 2px 0 0 0;
}
.sn-conf-learn-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #2563eb;
    text-decoration: none;
    white-space: nowrap;
}
.sn-conf-learn-more:hover {
    text-decoration: underline;
}

/* 5. RIGHT COLUMN - ORDER SUMMARY CARD */
.sn-order-summary-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #fef3c7;
    padding: 26px;
    box-shadow: 0 4px 16px rgba(245, 158, 11, 0.04);
}
.sn-summary-heading {
    font-size: 20px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0 0 20px 0;
    letter-spacing: -0.3px;
}
.sn-summary-rows {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.sn-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 14px;
}
.sn-summary-row .label {
    color: var(--sn-muted);
}
.sn-summary-row .val {
    font-weight: 700;
    color: var(--sn-dark);
}
.sn-shipping-free {
    color: #10b981 !important;
    font-weight: 800 !important;
}
.sn-coupon-row {
    display: none;
}
.sn-coupon-row.active {
    display: flex;
}
.text-green {
    color: #059669 !important;
}

.sn-summary-divider {
    border: none;
    border-top: 1px solid #f1f5f9;
    margin: 18px 0;
}

/* Total row */
.sn-total-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.sn-total-label {
    font-size: 18px;
    font-weight: 800;
    color: var(--sn-dark);
}
.sn-total-amount {
    font-size: 22px;
    font-weight: 800;
    color: var(--sn-dark);
    letter-spacing: -0.5px;
}

/* Savings Pill */
.sn-savings-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #059669;
    font-size: 12.5px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 9999px;
    margin-bottom: 20px;
    width: 100%;
    justify-content: center;
}
.sn-savings-pill strong {
    font-weight: 800;
}

/* Have a Promo Code? Box */
.sn-promo-card {
    background: #fffdf5;
    border: 1px solid #fef3c7;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 20px;
}
.sn-promo-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
}
.sn-promo-tag-icon {
    margin-top: 2px;
}
.sn-promo-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #78350f;
}
.sn-promo-sub {
    font-size: 11.5px;
    color: #92400e;
}
.sn-promo-input-row {
    display: flex;
    gap: 8px;
}
.sn-promo-input {
    flex: 1;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 13px;
    color: var(--sn-dark);
    outline: none;
    transition: border-color 0.15s;
}
.sn-promo-input:focus {
    border-color: #f59e0b;
}
.sn-promo-btn {
    background: #fde047;
    border: 1px solid #facc15;
    color: #713f12;
    font-size: 13px;
    font-weight: 700;
    padding: 0 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
}
.sn-promo-btn:hover {
    background: #eab308;
}
.sn-promo-alert {
    font-size: 12px;
    margin-top: 8px;
    display: none;
}
.sn-promo-alert.success {
    display: block;
    color: #059669;
}
.sn-promo-alert.error {
    display: block;
    color: #dc2626;
}

/* Proceed to Checkout Button */
.sn-btn-checkout {
    width: 100%;
    background: #fab802;
    border: none;
    color: #111827;
    font-size: 15px;
    font-weight: 800;
    padding: 14px 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(250, 184, 2, 0.35);
    transition: all 0.2s ease;
}
.sn-btn-checkout:hover {
    background: #e5a700;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(250, 184, 2, 0.45);
}
.sn-btn-checkout:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* OR divider */
.sn-or-divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 20px 0 16px 0;
    color: #94a3b8;
    font-size: 11.5px;
    font-weight: 600;
}
.sn-or-divider::before, .sn-or-divider::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid #f1f5f9;
}
.sn-or-divider span {
    padding: 0 10px;
}

/* Payment Icons Row */
.sn-payment-icons-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.sn-pay-pill {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    min-width: 48px;
}
.sn-visa-text {
    font-size: 13px;
    font-weight: 900;
    color: #1e3a8a;
    letter-spacing: 0.5px;
}
.sn-mc-circles {
    display: flex;
    align-items: center;
}
.mc-circle {
    width: 14px;
    height: 14px;
    border-radius: 50%;
}
.mc-circle.red { background: #ef4444; }
.mc-circle.yellow { background: #f59e0b; margin-left: -5px; }
.bkash-pill {
    background: #fdf2f8;
    border-color: #fbcfe8;
}
.sn-bkash-text {
    font-size: 12px;
    font-weight: 800;
    color: #e11d48;
}

/* 256-bit SSL guarantee note */
.sn-ssl-guarantee {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11.5px;
    color: var(--sn-muted);
    border-top: 1px solid #f8fafc;
    padding-top: 14px;
}
.ssl-main {
    font-weight: 600;
    color: var(--sn-dark);
}
.ssl-sub {
    font-size: 11px;
}

/* 6. "YOU MIGHT ALSO LIKE" SECTION */
.sn-recommendations-section {
    margin-top: 20px;
    margin-bottom: 48px;
}
.sn-recom-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.sn-recom-title {
    font-size: 20px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0;
    letter-spacing: -0.3px;
}
.sn-recom-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    text-decoration: none;
    transition: color 0.15s;
}
.sn-recom-link:hover {
    color: #f59e0b;
}

.sn-recom-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.sn-recom-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: all 0.25s ease;
}
.sn-recom-card:hover {
    border-color: #fef08a;
    box-shadow: 0 6px 18px rgba(0,0,0,0.05);
    transform: translateY(-2px);
}
.sn-recom-img-box {
    width: 72px;
    height: 72px;
    background: #f8fafc;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
.sn-recom-img-box img {
    max-width: 85%;
    max-height: 85%;
    object-fit: contain;
}
.sn-recom-content {
    flex: 1;
    min-width: 0;
}
.sn-recom-item-name {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    margin: 0 0 3px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sn-recom-item-sub {
    font-size: 11.5px;
    color: var(--sn-muted);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sn-recom-price-row {
    display: flex;
    align-items: baseline;
    gap: 6px;
}
.sn-recom-price {
    font-size: 14.5px;
    font-weight: 800;
    color: var(--sn-dark);
}
.sn-recom-old-price {
    font-size: 11.5px;
    color: #94a3b8;
    text-decoration: line-through;
}
.sn-recom-discount {
    font-size: 10px;
    font-weight: 700;
    color: #d97706;
}
.sn-recom-add-btn {
    position: absolute;
    right: 14px;
    bottom: 14px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #fab802;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(250, 184, 2, 0.3);
    transition: all 0.2s ease;
}
.sn-recom-add-btn:hover {
    background: #e5a700;
    transform: scale(1.08);
}

/* 7. BOTTOM TRUST / ASSURANCE BAR */
.sn-trust-assurance-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 18px;
    padding: 24px 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.sn-trust-cell {
    display: flex;
    align-items: center;
    gap: 16px;
}
.sn-trust-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.sn-trust-info h4 {
    font-size: 14px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0 0 2px 0;
}
.sn-trust-info p {
    font-size: 12px;
    color: var(--sn-muted);
    margin: 0;
}

/* 8. EMPTY CART STAGE */
.sn-empty-cart-stage {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
    padding: 60px 24px;
    text-align: center;
    margin: 40px auto;
    max-width: 540px;
}
.sn-empty-icon-ring {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #fffbeb;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px auto;
}
.sn-empty-cart-stage h2 {
    font-size: 24px;
    font-weight: 800;
    color: var(--sn-dark);
    margin-bottom: 8px;
}
.sn-empty-cart-stage p {
    font-size: 14px;
    color: var(--sn-muted);
    margin-bottom: 24px;
    max-width: 360px;
    margin-left: auto;
    margin-right: auto;
}
.sn-empty-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}
.sn-btn-primary {
    background: #fab802;
    color: #111827;
    font-weight: 800;
    font-size: 14px;
    padding: 12px 24px;
    border-radius: 10px;
    text-decoration: none;
    transition: background 0.15s;
}
.sn-btn-primary:hover {
    background: #e5a700;
    color: #111827;
}
.sn-btn-secondary {
    background: #f1f5f9;
    color: var(--sn-dark);
    font-weight: 700;
    font-size: 14px;
    padding: 12px 20px;
    border-radius: 10px;
    text-decoration: none;
}

/* 9. STICKY MOBILE DOCK (HIDDEN ON DESKTOP) */
.sn-mobile-sticky-dock {
    display: none;
}

/* 10. FLOATING TOAST NOTIFICATIONS */
.sn-toast-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.sn-toast {
    background: #0f172a;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.18);
    animation: toastSlideUp 0.3s ease-out;
}
.sn-toast.success { border-left: 4px solid #10b981; }
.sn-toast.error { border-left: 4px solid #ef4444; }

@keyframes toastSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ============================================================
   RESPONSIVE BREAKPOINTS (Mobile & Tablet)
   ============================================================ */
@media (max-width: 1024px) {
    .sn-cart-layout-grid {
        grid-template-columns: 1fr;
    }
    .sn-recom-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .sn-trust-assurance-bar {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .sn-cart-page-wrapper {
        padding-top: 14px;
        padding-bottom: 120px;
    }
    .sn-cart-page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 16px;
    }
    .sn-page-title {
        font-size: 22px;
    }
    .sn-auto-saved-badge {
        width: 100%;
        border-radius: 12px;
    }

    /* Product row becomes mobile card layout */
    .sn-cart-item-row {
        grid-template-columns: 24px 85px 1fr;
        grid-template-areas: 
            "check img details"
            "check img pricing"
            "check img total";
        gap: 12px;
        padding: 16px;
    }
    .sn-item-checkbox-cell { grid-area: check; }
    .sn-item-image-cell { grid-area: img; }
    .sn-img-container { width: 85px; height: 85px; }
    .sn-item-details-cell { grid-area: details; }
    .sn-item-pricing-cell { grid-area: pricing; }
    .sn-item-total-cell { grid-area: total; text-align: left; margin-top: -6px; }

    /* Hide Order Summary Card on Mobile as requested */
    .sn-cart-right-col {
        display: none !important;
    }
    .sn-cart-layout-grid {
        margin-bottom: 20px !important;
    }

    /* "You Might Also Like" section: 2 columns on mobile */
    .sn-recommendations-section {
        margin-top: 16px !important;
        margin-bottom: 24px !important;
    }
    .sn-recom-head {
        margin-bottom: 12px !important;
    }
    .sn-recom-title {
        font-size: 16.5px !important;
    }
    .sn-recom-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }
    .sn-recom-card {
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 10px 10px 12px 10px !important;
        border-radius: 14px !important;
        position: relative !important;
        gap: 0 !important;
        background: #ffffff !important;
        border: 1px solid #f1f5f9 !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02) !important;
    }
    .sn-recom-img-box {
        width: 100% !important;
        height: 120px !important;
        background: #f8fafc !important;
        border-radius: 10px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin-bottom: 8px !important;
        overflow: hidden !important;
        text-decoration: none !important;
    }
    .sn-recom-img-box img {
        max-width: 90% !important;
        max-height: 90% !important;
        object-fit: contain !important;
    }
    .sn-recom-content {
        width: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        position: relative !important;
        padding-right: 30px !important;
    }
    .sn-recom-item-name {
        font-size: 12px !important;
        font-weight: 700 !important;
        line-height: 1.3 !important;
        margin: 0 0 3px 0 !important;
        height: 31px !important;
        white-space: normal !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        display: -webkit-box !important;
        -webkit-line-clamp: 2 !important;
        -webkit-box-orient: vertical !important;
        color: var(--sn-dark) !important;
    }
    .sn-recom-item-name a {
        color: inherit !important;
        text-decoration: none !important;
    }
    .sn-recom-item-sub {
        font-size: 10px !important;
        color: var(--sn-muted) !important;
        margin-bottom: 4px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .sn-recom-price-row {
        display: flex !important;
        align-items: baseline !important;
        gap: 5px !important;
        margin-bottom: 2px !important;
        flex-wrap: wrap !important;
    }
    .sn-recom-price {
        font-size: 13.5px !important;
        font-weight: 800 !important;
        color: var(--sn-dark) !important;
    }
    .sn-recom-old-price {
        font-size: 10.5px !important;
        color: #94a3b8 !important;
        text-decoration: line-through !important;
    }
    .sn-recom-discount {
        font-size: 9.5px !important;
        font-weight: 700 !important;
        color: #ef4444 !important;
        min-height: 14px !important;
    }
    .sn-recom-add-btn {
        position: absolute !important;
        right: 8px !important;
        bottom: 8px !important;
        width: 28px !important;
        height: 28px !important;
        border-radius: 50% !important;
        background: #fab802 !important;
        border: none !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 2px 6px rgba(250, 184, 2, 0.3) !important;
        cursor: pointer !important;
        z-index: 2 !important;
    }
    .sn-recom-add-btn svg {
        width: 14px !important;
        height: 14px !important;
    }
    .sn-trust-assurance-bar {
        grid-template-columns: 1fr;
        padding: 20px;
    }

    /* Mobile Sticky Bottom Checkout & Voucher Dock */
    .sn-mobile-sticky-dock {
        display: flex;
        flex-direction: column;
        position: fixed;
        bottom: 56px; /* Sits directly above bottom nav */
        left: 0;
        right: 0;
        z-index: 999;
        background: #ffffff;
        border-top: 1px solid #f1f5f9;
        box-shadow: 0 -4px 16px rgba(0,0,0,0.06);
    }
    .sn-mobile-voucher-bar {
        background: #fff1f2;
        border-bottom: 1px solid #ffe4e6;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 12px;
        font-weight: 700;
        color: #e11d48;
    }
    .sn-mv-left {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sn-mv-add-btn {
        background: #ffffff;
        border: 1px solid #fda4af;
        color: #e11d48;
        font-size: 11px;
        font-weight: 800;
        padding: 2px 10px;
        border-radius: 9999px;
        cursor: pointer;
    }
    .sn-mobile-dock-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 16px;
        gap: 12px;
    }
    .sn-mobile-all-check {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 700;
        color: var(--sn-dark);
        margin: 0;
    }
    .sn-mobile-totals-col {
        text-align: right;
        flex: 1;
    }
    .sn-mob-subtotal {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--sn-dark);
    }
    .sn-mob-subtotal strong {
        color: #b45309;
    }
    .sn-mob-shipping {
        font-size: 11px;
        color: var(--sn-muted);
    }
    .sn-mobile-checkout-btn {
        background: #fab802;
        border: none;
        color: #111827;
        font-size: 14px;
        font-weight: 800;
        padding: 10px 18px;
        border-radius: 10px;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(250, 184, 2, 0.3);
    }
}
</style>

<!-- ============================================================
     REAL-TIME INTERACTIVE JAVASCRIPT LOGIC
     ============================================================ -->
<script>
// Global state
let currentSubtotal = <?php echo (float)$initial_subtotal; ?>;
let appliedDiscount = <?php echo (float)$coupon_discount; ?>;
let appliedCouponCode = '<?php echo addslashes($applied_coupon['code'] ?? ''); ?>';

// Formatting helper: ৳ 117,997
function formatCurrency(amount) {
    return '৳ ' + Math.round(amount).toLocaleString('en-US');
}

// Show Toast
function showToast(message, type = 'success') {
    const container = document.getElementById('snToastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `sn-toast ${type}`;
    toast.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>${message}</span>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'all 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// 1. SELECT ALL CHECKBOX LOGIC
const selectAllCheckbox = document.getElementById('selectAllCheckbox');
const mobileSelectAll = document.getElementById('mobileSelectAll');

function toggleSelectAll(checked) {
    const itemCheckboxes = document.querySelectorAll('.sn-item-checkbox');
    itemCheckboxes.forEach(cb => cb.checked = checked);
    if (selectAllCheckbox) selectAllCheckbox.checked = checked;
    if (mobileSelectAll) mobileSelectAll.checked = checked;
    recalculateCart();
}

if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        toggleSelectAll(this.checked);
    });
}

function toggleSelectAllFromMobile(cb) {
    toggleSelectAll(cb.checked);
}

function handleItemCheckboxChange() {
    const all = document.querySelectorAll('.sn-item-checkbox');
    const checked = document.querySelectorAll('.sn-item-checkbox:checked');
    const isAllChecked = all.length > 0 && all.length === checked.length;
    
    if (selectAllCheckbox) selectAllCheckbox.checked = isAllChecked;
    if (mobileSelectAll) mobileSelectAll.checked = isAllChecked;
    
    recalculateCart();
}

// 2. QUANTITY STEPPER (INCREMENT / DECREMENT)
function updateItemQuantity(index, delta) {
    const row = document.querySelector(`.sn-cart-item-row[data-index="${index}"]`);
    if (!row) return;

    const input = row.querySelector('.sn-qty-input');
    let currentQty = parseInt(input.value) || 1;
    let newQty = currentQty + delta;
    if (newQty < 1) newQty = 1;

    input.value = newQty;
    
    const price = parseFloat(row.dataset.price) || 0;
    const rowTotal = price * newQty;
    const lineTotalEl = document.getElementById(`lineTotal-${index}`);
    if (lineTotalEl) {
        lineTotalEl.textContent = formatCurrency(rowTotal);
    }

    recalculateCart();

    // Background sync with cart-ajax-handler.php
    const pid = row.dataset.id;
    const formData = new FormData();
    formData.append('action', 'update_qty');
    formData.append('index', index);
    formData.append('qty', newQty);
    formData.append('product_id', pid);

    fetch('cart-ajax-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.summary) {
            updateHeaderCartBadges(data.summary.total_qty);
        }
    })
    .catch(err => console.error('Cart sync error:', err));
}

// 3. RECALCULATE CART TOTALS & SAVINGS
function recalculateCart() {
    const rows = document.querySelectorAll('.sn-cart-item-row');
    let subtotal = 0;
    let totalSavings = 0;
    let selectedCount = 0;
    const selectedIndices = [];

    rows.forEach(row => {
        const checkbox = row.querySelector('.sn-item-checkbox');
        if (checkbox && checkbox.checked) {
            const index = row.dataset.index;
            selectedIndices.push(index);
            selectedCount++;

            const qty = parseInt(row.querySelector('.sn-qty-input').value) || 1;
            const price = parseFloat(row.dataset.price) || 0;
            const oldPrice = parseFloat(row.dataset.oldPrice) || (price * 1.18);

            subtotal += (price * qty);
            if (oldPrice > price) {
                totalSavings += ((oldPrice - price) * qty);
            }
        }
    });

    currentSubtotal = subtotal;

    // Recalculate coupon discount
    let couponDiscount = 0;
    if (appliedCouponCode) {
        if (appliedCouponCode.includes('10')) couponDiscount = (subtotal * 10) / 100;
        else if (appliedCouponCode.includes('15')) couponDiscount = (subtotal * 15) / 100;
        else if (appliedCouponCode.includes('20')) couponDiscount = (subtotal * 20) / 100;
        else if (appliedCouponCode.includes('5')) couponDiscount = (subtotal * 5) / 100;
        else if (appliedCouponCode === 'SHOPNEXT') couponDiscount = Math.min(subtotal, 500);
        else couponDiscount = appliedDiscount;
    }

    const grandTotal = Math.max(0, subtotal - couponDiscount);

    // Update DOM elements
    const summarySelectedCount = document.getElementById('summarySelectedCount');
    const selectedCountToolbar = document.getElementById('selectedCountToolbar');
    const pageItemCount = document.getElementById('pageItemCount');
    const summarySubtotalVal = document.getElementById('summarySubtotalVal');
    const summaryTotalVal = document.getElementById('summaryTotalVal');
    const savingsTotalAmount = document.getElementById('savingsTotalAmount');
    const mobileSubtotalVal = document.getElementById('mobileSubtotalVal');
    const mobileCheckoutBadge = document.getElementById('mobileCheckoutBadge');
    const proceedCheckoutBtn = document.getElementById('proceedCheckoutBtn');
    const selectedIndexesInput = document.getElementById('selectedIndexesInput');

    if (summarySelectedCount) summarySelectedCount.textContent = selectedCount;
    if (selectedCountToolbar) selectedCountToolbar.textContent = selectedCount;
    if (pageItemCount) pageItemCount.textContent = rows.length;
    if (summarySubtotalVal) summarySubtotalVal.textContent = formatCurrency(subtotal);
    if (summaryTotalVal) summaryTotalVal.textContent = formatCurrency(grandTotal);
    if (savingsTotalAmount) savingsTotalAmount.textContent = formatCurrency(totalSavings + couponDiscount);
    if (mobileSubtotalVal) mobileSubtotalVal.textContent = formatCurrency(grandTotal);
    if (mobileCheckoutBadge) mobileCheckoutBadge.textContent = selectedCount;
    if (selectedIndexesInput) selectedIndexesInput.value = JSON.stringify(selectedIndices);

    if (proceedCheckoutBtn) {
        proceedCheckoutBtn.disabled = (selectedCount === 0);
    }
}

// 4. REMOVE SINGLE ITEM
function removeSingleItem(index, productId) {
    if (!confirm('Are you sure you want to remove this item from your cart?')) return;

    const row = document.querySelector(`.sn-cart-item-row[data-index="${index}"]`);
    if (row) {
        row.classList.add('removing');
        setTimeout(() => {
            row.remove();
            recalculateCart();
            checkEmptyState();
        }, 280);
    }

    const formData = new FormData();
    formData.append('action', 'delete_item');
    formData.append('index', index);
    formData.append('product_id', productId);

    fetch('cart-ajax-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast('Item removed from cart.');
        if (data.summary) {
            updateHeaderCartBadges(data.summary.total_qty);
        }
    })
    .catch(err => console.error(err));
}

// 5. BATCH REMOVE SELECTED
function batchRemoveSelected() {
    const checked = document.querySelectorAll('.sn-item-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one item to remove.');
        return;
    }

    if (!confirm(`Are you sure you want to remove ${checked.length} selected item(s)?`)) return;

    const indices = [];
    checked.forEach(cb => {
        indices.push(cb.value);
        const row = cb.closest('.sn-cart-item-row');
        if (row) {
            row.classList.add('removing');
            setTimeout(() => row.remove(), 250);
        }
    });

    setTimeout(() => {
        recalculateCart();
        checkEmptyState();
    }, 300);

    const formData = new FormData();
    formData.append('action', 'bulk_delete');
    formData.append('indexes', JSON.stringify(indices));

    fetch('cart-ajax-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast('Selected items removed from cart.');
        if (data.summary) {
            updateHeaderCartBadges(data.summary.total_qty);
        }
    });
}

// 6. MOVE TO WISHLIST
function moveToWishlist(index, productId, productName) {
    const row = document.querySelector(`.sn-cart-item-row[data-index="${index}"]`);
    if (row) {
        row.classList.add('removing');
        setTimeout(() => {
            row.remove();
            recalculateCart();
            checkEmptyState();
        }, 280);
    }

    const formData = new FormData();
    formData.append('action', 'move_to_wishlist');
    formData.append('index', index);
    formData.append('product_id', productId);
    formData.append('product_name', productName);

    fetch('cart-ajax-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(`"${productName}" moved to your Wishlist!`, 'success');
        if (data.summary) {
            updateHeaderCartBadges(data.summary.total_qty);
        }
    });
}

function batchMoveToWishlist() {
    const checked = document.querySelectorAll('.sn-item-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one item to move to wishlist.');
        return;
    }

    checked.forEach(cb => {
        const row = cb.closest('.sn-cart-item-row');
        if (row) {
            const index = row.dataset.index;
            const pid = row.dataset.id;
            const name = row.querySelector('.sn-item-name a').textContent.trim();
            moveToWishlist(index, pid, name);
        }
    });
}

// 7. COLOR SWATCH SELECTOR
function selectColor(el, colorName) {
    const row = el.closest('.sn-cart-item-row');
    if (!row) return;
    row.querySelectorAll('.sn-color-dot').forEach(dot => dot.classList.remove('active'));
    el.classList.add('active');
    const colorLabel = row.querySelector('.sn-active-color-name');
    if (colorLabel) colorLabel.textContent = colorName;
    showToast(`Color updated to ${colorName}`);
}

// 8. PROMO CODE APPLICATION
function handleApplyPromo(manualCode = null) {
    const input = document.getElementById('promoInput');
    const code = manualCode || (input ? input.value.trim().toUpperCase() : '');
    const alertBox = document.getElementById('promoAlertBox');

    if (!code) {
        if (alertBox) {
            alertBox.className = 'sn-promo-alert error';
            alertBox.textContent = 'Please enter a promo code.';
        }
        return;
    }

    const formData = new FormData();
    formData.append('action', 'apply_coupon');
    formData.append('code', code);

    fetch('cart-ajax-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            appliedCouponCode = code;
            appliedDiscount = data.summary.discount;
            if (alertBox) {
                alertBox.className = 'sn-promo-alert success';
                alertBox.textContent = data.message;
            }
            showToast(data.message, 'success');

            const couponRow = document.getElementById('summaryCouponRow');
            const couponCodeLabel = document.getElementById('couponCodeLabel');
            const couponDiscountVal = document.getElementById('couponDiscountVal');
            if (couponRow) couponRow.classList.add('active');
            if (couponCodeLabel) couponCodeLabel.textContent = code;
            if (couponDiscountVal) couponDiscountVal.textContent = '- ' + formatCurrency(appliedDiscount);

            recalculateCart();
        } else {
            if (alertBox) {
                alertBox.className = 'sn-promo-alert error';
                alertBox.textContent = data.message;
            }
            showToast(data.message, 'error');
        }
    });
}

function applyPromo(code) {
    const input = document.getElementById('promoInput');
    if (input) input.value = code;
    handleApplyPromo(code);
}

// 9. QUICK ADD RECOMMENDATION TO CART
function quickAddToCart(productId, productName, price, image, subtitle) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', 1);

    fetch('add-to-cart-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showToast(`"${productName}" added to your cart!`, 'success');
        if (data.cart_count) {
            updateHeaderCartBadges(data.cart_count);
        }
        // Smoothly reload page after 800ms to integrate the new item
        setTimeout(() => window.location.reload(), 700);
    })
    .catch(() => {
        showToast(`"${productName}" added to cart!`, 'success');
        setTimeout(() => window.location.reload(), 700);
    });
}

// 10. CHECK EMPTY STATE & AUTO-TRANSITION
function checkEmptyState() {
    const rows = document.querySelectorAll('.sn-cart-item-row');
    if (rows.length === 0) {
        window.location.href = 'cart.php?empty=1';
    }
}

// 11. UPDATE HEADER CART BADGES
function updateHeaderCartBadges(count) {
    const desktopBadge = document.getElementById('sn-cart-badge-count');
    if (desktopBadge) desktopBadge.textContent = count;
    const mobileBadge = document.querySelector('.sn-dock-cart-badge');
    if (mobileBadge) mobileBadge.textContent = count;
}

// 12. MOBILE CHECKOUT SUBMISSION
function submitMobileCheckout() {
    const checked = document.querySelectorAll('.sn-item-checkbox:checked');
    if (checked.length === 0) {
        if (typeof showToast === 'function') {
            showToast('Please select at least one item to proceed to checkout.', 'error');
        } else {
            alert('Please select at least one item to proceed to checkout.');
        }
        return;
    }
    recalculateCart();
    const form = document.getElementById('checkoutForm');
    if (form) {
        form.submit();
    }
}

// Form Checkout Submission Check
document.addEventListener('DOMContentLoaded', function() {
    recalculateCart();

    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('.sn-item-checkbox:checked');
            if (checked.length === 0) {
                e.preventDefault();
                alert('Please select at least one item to proceed to checkout.');
                return false;
            }
        });
    }
});
</script>

<?php require_once('footer.php'); ?>