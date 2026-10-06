<?php 
require_once(__DIR__ . '/header.php'); 

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
    <div class="sn-cart-single-container">
        
        <!-- Breadcrumbs (Home > Shopping Cart) -->
        <nav class="sn-breadcrumbs" aria-label="breadcrumb">
            <a href="index.php">Home</a>
            <span class="sn-bc-sep">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </span>
            <span class="sn-bc-current">Shopping Cart</span>
        </nav>

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
                 PAGE TITLE BAR (Formal Header)
                 ======================================================== -->
            <div class="sn-cart-page-header">
                <div class="sn-cart-heading-wrap">
                    <h1 class="sn-page-title">Shopping Cart</h1>
                    <p class="sn-page-subtitle">
                        <span id="pageItemCount"><?php echo $total_cart_items; ?></span> item(s) in your cart
                    </p>
                </div>

                <div class="sn-auto-saved-badge">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Items saved automatically</span>
                </div>
            </div>

            <!-- ========================================================
                 SINGLE COLUMN FORMAL TABLE LAYOUT
                 ======================================================== -->
            <div class="sn-cart-single-col">
                
                <!-- CART ITEMS FORMAL TABLE CARD -->
                <div class="sn-cart-card">
                    
                    <!-- Table Toolbar -->
                    <div class="sn-cart-toolbar">
                        <label class="sn-checkbox-label">
                            <input type="checkbox" id="selectAllCheckbox" checked>
                            <span class="sn-custom-check"></span>
                            <span class="sn-toolbar-text">Select All (<span id="selectedCountToolbar"><?php echo $total_cart_items; ?></span>)</span>
                        </label>

                        <div class="sn-toolbar-actions">
                            <button type="button" class="sn-tool-btn" onclick="batchMoveToWishlist()">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                <span>Move to Wishlist</span>
                            </button>
                            <button type="button" class="sn-tool-btn" onclick="batchRemoveSelected()">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                <span>Remove Selected</span>
                            </button>
                        </div>
                    </div>

                    <!-- Formal Table Container -->
                    <div class="sn-table-responsive">
                        <table class="sn-formal-cart-table">
                            <thead>
                                <tr>
                                    <th class="th-check" style="width: 44px; text-align: center;">#</th>
                                    <th class="th-img" style="width: 80px;">Item</th>
                                    <th class="th-desc">Product Details</th>
                                    <th class="th-price" style="width: 120px; text-align: right;">Unit Price</th>
                                    <th class="th-qty" style="width: 130px; text-align: center;">Quantity</th>
                                    <th class="th-total" style="width: 130px; text-align: right;">Total</th>
                                    <th class="th-action" style="width: 80px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cartItemsList">
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
                                    $p_subtitle = $cart_p_subtitles[$i] ?? '';
                                    $p_badge = $cart_p_badges[$i] ?? '';
                                    $p_color = $cart_color_names[$i] ?? 'Default';
                                    $discount_pct = round((($p_old_price - $p_price) / max(1, $p_old_price)) * 100);
                                    $row_total = $p_price * $p_qty;
                                ?>
                                <tr class="sn-cart-item-row" data-index="<?php echo $i; ?>" data-id="<?php echo $p_id; ?>" data-price="<?php echo $p_price; ?>" data-old-price="<?php echo $p_old_price; ?>">
                                    
                                    <!-- Selection Checkbox -->
                                    <td class="sn-item-checkbox-cell" style="text-align: center;">
                                        <label class="sn-checkbox-label">
                                            <input type="checkbox" class="sn-item-checkbox" value="<?php echo $i; ?>" checked onchange="handleItemCheckboxChange()">
                                            <span class="sn-custom-check"></span>
                                        </label>
                                    </td>

                                    <!-- Thumbnail -->
                                    <td class="sn-item-image-cell">
                                        <div class="sn-img-container">
                                            <img src="<?php echo htmlspecialchars($p_photo); ?>" alt="<?php echo htmlspecialchars($p_name); ?>" loading="lazy" onerror="this.src='assets/uploads/default_product.jpg'">
                                        </div>
                                    </td>

                                    <!-- Product Details -->
                                    <td class="sn-item-details-cell">
                                        <h2 class="sn-item-name">
                                            <a href="product.php?id=<?php echo $p_id; ?>"><?php echo htmlspecialchars($p_name); ?></a>
                                        </h2>
                                        
                                        <?php if (!empty($p_subtitle)): ?>
                                            <div class="sn-item-specs"><?php echo htmlspecialchars($p_subtitle); ?></div>
                                        <?php endif; ?>

                                        <?php if (!empty($p_color) && $p_color !== 'Default'): ?>
                                        <div class="sn-item-variant-swatches">
                                            <span class="sn-swatch-label">Color: <strong class="sn-active-color-name"><?php echo htmlspecialchars($p_color); ?></strong></span>
                                        </div>
                                        <?php endif; ?>

                                        <div class="sn-stock-status">
                                            <span class="sn-stock-pill in-stock">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                In Stock
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Unit Price -->
                                    <td class="sn-item-pricing-cell" style="text-align: right;">
                                        <div class="sn-curr-price">৳ <?php echo number_format($p_price); ?></div>
                                        <?php if ($p_old_price > $p_price): ?>
                                            <div class="sn-old-price">৳ <?php echo number_format($p_old_price); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Quantity Stepper -->
                                    <td style="text-align: center;">
                                        <div class="sn-stepper-control">
                                            <button type="button" class="sn-step-btn minus" onclick="updateItemQuantity(<?php echo $i; ?>, -1)" aria-label="Decrease quantity">−</button>
                                            <input type="text" readonly class="sn-qty-input" value="<?php echo $p_qty; ?>" data-index="<?php echo $i; ?>" aria-label="Quantity">
                                            <button type="button" class="sn-step-btn plus" onclick="updateItemQuantity(<?php echo $i; ?>, 1)" aria-label="Increase quantity">+</button>
                                        </div>
                                    </td>

                                    <!-- Line Total -->
                                    <td class="sn-item-total-cell" style="text-align: right;">
                                        <div class="sn-item-total-price" id="lineTotal-<?php echo $i; ?>">
                                            ৳ <?php echo number_format($row_total); ?>
                                        </div>
                                    </td>

                                    <!-- Action Buttons -->
                                    <td style="text-align: center;">
                                        <div class="sn-row-actions">
                                            <button type="button" class="sn-action-btn-icon" onclick="moveToWishlist(<?php echo $i; ?>, <?php echo $p_id; ?>, '<?php echo addslashes($p_name); ?>')" title="Move to Wishlist">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                            </button>
                                            <button type="button" class="sn-action-btn-icon remove" onclick="removeSingleItem(<?php echo $i; ?>, <?php echo $p_id; ?>)" title="Remove Item">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </div>
                                    </td>

                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                </div>

                <!-- ORDER SUMMARY & PROMO CALCULATION BLOCK (Below Table in Single Column) -->
                <div class="sn-formal-summary-section">
                    <div class="sn-summary-grid-box">
                        
                        <!-- Left Block: Promo Code -->
                        <div class="sn-promo-subcard">
                            <h3 class="sn-block-title">Have a Promo Code?</h3>
                            <p class="sn-block-sub">Enter your promo code to apply instant savings to your order.</p>
                            
                            <div class="sn-promo-input-row">
                                <input type="text" id="promoInput" class="sn-promo-input" placeholder="Enter promo code" value="<?php echo htmlspecialchars($applied_coupon['code'] ?? ''); ?>">
                                <button type="button" class="sn-promo-btn" id="promoApplyBtn" onclick="handleApplyPromo()">Apply Code</button>
                            </div>
                            <div class="sn-promo-alert" id="promoAlertBox"></div>
                        </div>

                        <!-- Right Block: Calculation Summary Table -->
                        <div class="sn-summary-calc-card">
                            <h3 class="sn-block-title">Order Calculation</h3>
                            
                            <table class="sn-summary-table">
                                <tbody>
                                    <tr>
                                        <td class="lbl">Subtotal (<span id="summarySelectedCount"><?php echo $total_cart_items; ?></span> items)</td>
                                        <td class="val" id="summarySubtotalVal">৳ <?php echo number_format($initial_subtotal); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Shipping</td>
                                        <td class="val sn-shipping-free">FREE</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Tax (estimated)</td>
                                        <td class="val" id="summaryTaxVal">৳ 0</td>
                                    </tr>
                                    <tr class="sn-coupon-row <?php echo !empty($applied_coupon) ? 'active' : ''; ?>" id="summaryCouponRow">
                                        <td class="lbl">Coupon Discount (<span id="couponCodeLabel"><?php echo htmlspecialchars($applied_coupon['code'] ?? ''); ?></span>)</td>
                                        <td class="val text-green" id="couponDiscountVal">-৳ <?php echo number_format($coupon_discount); ?></td>
                                    </tr>
                                    <tr class="sn-total-tr">
                                        <td class="lbl-total">Total Amount</td>
                                        <td class="val-total" id="summaryTotalVal">৳ <?php echo number_format($initial_total); ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="sn-savings-pill" id="savingsPillWrap">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.2">
                                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                </svg>
                                <span>You save <strong id="savingsTotalAmount">৳ <?php echo number_format($initial_savings + $coupon_discount); ?></strong></span>
                            </div>

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
                        </div>

                    </div>
                </div>

                <!-- Confidence Guarantee Banner -->
                <div class="sn-confidence-banner">
                    <div class="sn-conf-left">
                        <div class="sn-conf-shield">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                <path d="m9 12 2 2 4-4"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="sn-conf-title">Shop with Confidence</h3>
                            <p class="sn-conf-desc">256-bit SSL encrypted checkout • 7-day easy return • Genuine products guaranteed</p>
                        </div>
                    </div>
                </div>

            </div>

        <?php endif; ?>

        <!-- ============================================================
             "YOU MIGHT ALSO LIKE" SECTION
             ============================================================ -->
        <?php if (!empty($recommendations)): ?>
        <section class="sn-recommendations-section">
            <div class="sn-recom-head">
                <h2 class="sn-recom-title">You Might Also Like</h2>
                <a href="product-category.php?id=1&type=top-category" class="sn-recom-link">
                    <span>View All</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
            </div>

            <div class="sn-recom-grid">
                <?php foreach ($recommendations as $rec): 
                    $recUrl = function_exists('getProductURL') ? getProductURL($rec['id'], $rec['name'], BASE_URL) : BASE_URL . 'product.php?id=' . $rec['id'];
                ?>
                <div class="sn-recom-card" data-href="<?php echo htmlspecialchars($recUrl); ?>" style="cursor:pointer;">
                    <a href="<?php echo htmlspecialchars($recUrl); ?>" class="sn-recom-img-box">
                        <img src="<?php echo htmlspecialchars($rec['image']); ?>" alt="<?php echo htmlspecialchars($rec['name']); ?>" loading="lazy">
                    </a>
                    <div class="sn-recom-content">
                        <h4 class="sn-recom-item-name"><a href="<?php echo htmlspecialchars($recUrl); ?>" style="color:inherit; text-decoration:none;"><?php echo htmlspecialchars($rec['name']); ?></a></h4>
                        <div class="sn-recom-item-sub"><?php echo htmlspecialchars($rec['subtitle']); ?></div>
                        
                        <div class="sn-recom-price-row">
                            <span class="sn-recom-price">৳ <?php echo number_format($rec['price']); ?></span>
                            <?php if ($rec['old_price'] > $rec['price']): ?>
                                <span class="sn-recom-old-price">৳ <?php echo number_format($rec['old_price']); ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="sn-recom-add-btn" onclick="quickAddToCart(<?php echo $rec['id']; ?>, '<?php echo addslashes($rec['name']); ?>', <?php echo $rec['price']; ?>, '<?php echo htmlspecialchars($rec['image']); ?>', '<?php echo addslashes($rec['subtitle']); ?>')" title="Add to Cart">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#111827" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============================================================
             BOTTOM TRUST / ASSURANCE BAR
             ============================================================ -->
        <div class="sn-trust-assurance-bar">
            <div class="sn-trust-cell">
                <div class="sn-trust-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
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

            <div class="sn-trust-cell">
                <div class="sn-trust-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>
                </div>
                <div class="sn-trust-info">
                    <h4>Secure Payment</h4>
                    <p>100% secure payment</p>
                </div>
            </div>

            <div class="sn-trust-cell">
                <div class="sn-trust-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                </div>
                <div class="sn-trust-info">
                    <h4>Easy Returns</h4>
                    <p>30-day return policy</p>
                </div>
            </div>

            <div class="sn-trust-cell">
                <div class="sn-trust-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2H3z"></path>
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
     STICKY MOBILE FLOATING VOUCHER BAR & CHECKOUT DOCK
     ============================================================ -->
<?php if ($total_cart_items > 0): ?>
<div class="sn-mobile-sticky-dock">
    <div class="sn-mobile-dock-row">
        <label class="sn-mobile-all-check">
            <input type="checkbox" id="mobileSelectAll" checked onchange="toggleSelectAllFromMobile(this)">
            <span class="sn-custom-check"></span>
            <span>All</span>
        </label>

        <div class="sn-mobile-totals-col">
            <div class="sn-mob-subtotal">Total: <strong id="mobileSubtotalVal">৳ <?php echo number_format($initial_total); ?></strong></div>
            <div class="sn-mob-shipping">Shipping: <span>৳ 0</span></div>
        </div>

        <button type="button" class="sn-mobile-checkout-btn" onclick="document.getElementById('checkoutForm').submit()">
            Checkout (<span id="mobileCheckoutBadge"><?php echo $total_cart_items; ?></span>)
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     TOAST NOTIFICATIONS
     ============================================================ -->
<div id="snToastContainer" class="sn-toast-container"></div>

<!-- ============================================================
     FORMAL MINIMAL DESKTOP SINGLE COLUMN STYLING
     ============================================================ -->
<style>
:root {
    --sn-font: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --sn-primary: #fab802;
    --sn-primary-hover: #e5a700;
    --sn-dark: #0f172a;
    --sn-dark-soft: #1e293b;
    --sn-muted: #64748b;
    --sn-muted-light: #94a3b8;
    --sn-border: #f1f5f9;
    --sn-border-strong: #e2e8f0;
    --sn-bg-page: #f8fafc;
    --sn-card-bg: #ffffff;
    --sn-green: #059669;
}

html, body {
    max-width: 100vw;
    overflow-x: hidden !important;
}

body.shopnext-theme {
    background-color: var(--sn-bg-page);
    font-family: var(--sn-font);
    color: var(--sn-dark);
}

.sn-cart-page-wrapper {
    width: 100%;
    padding: 24px 0 60px 0;
    box-sizing: border-box;
}

/* SINGLE COLUMN CONTAINER - MAX WIDTH 920px CENTERED */
.sn-cart-single-container {
    width: 100%;
    max-width: 920px;
    margin: 0 auto !important;
    padding-left: 20px;
    padding-right: 20px;
    box-sizing: border-box;
}

/* 1. BREADCRUMBS */
.sn-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    color: var(--sn-muted);
    margin-bottom: 20px;
}
.sn-breadcrumbs a {
    color: var(--sn-muted);
    text-decoration: none;
    transition: color 0.2s;
}
.sn-breadcrumbs a:hover {
    color: var(--sn-dark);
}
.sn-bc-sep {
    display: flex;
    align-items: center;
    color: #cbd5e1;
}
.sn-bc-current {
    color: var(--sn-dark);
    font-weight: 600;
}

/* 2. PAGE HEADER BAR */
.sn-cart-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--sn-border-strong);
}
.sn-page-title {
    font-size: 26px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0;
    line-height: 1.2;
    letter-spacing: -0.4px;
}
.sn-page-subtitle {
    font-size: 13.5px;
    color: var(--sn-muted);
    margin: 4px 0 0 0;
}
.sn-auto-saved-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ffffff;
    border: 1px solid var(--sn-border-strong);
    padding: 6px 14px;
    border-radius: 9999px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--sn-muted);
}

/* 3. SINGLE COLUMN MAIN WRAPPER */
.sn-cart-single-col {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* 4. FORMAL CART CARD & TABLE */
.sn-cart-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid var(--sn-border-strong);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    overflow: hidden;
}

/* Toolbar */
.sn-cart-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    background: #f8fafc;
    border-bottom: 1px solid var(--sn-border-strong);
}
.sn-toolbar-text {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    user-select: none;
}
.sn-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}
.sn-tool-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: none;
    border: none;
    color: var(--sn-muted);
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    transition: color 0.15s ease;
}
.sn-tool-btn:hover {
    color: var(--sn-dark);
}

/* Checkbox Styling */
.sn-checkbox-label {
    display: inline-flex;
    align-items: center;
    gap: 10px;
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
    width: 18px;
    height: 18px;
    background-color: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}
.sn-checkbox-label:hover input ~ .sn-custom-check {
    border-color: var(--sn-dark);
}
.sn-checkbox-label input:checked ~ .sn-custom-check {
    background-color: var(--sn-dark);
    border-color: var(--sn-dark);
}
.sn-checkbox-label input:checked ~ .sn-custom-check:after {
    content: "";
    display: block;
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    margin-bottom: 2px;
}

/* Formal HTML Table Structure */
.sn-table-responsive {
    width: 100%;
    overflow-x: auto;
}
.sn-formal-cart-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13.5px;
}
.sn-formal-cart-table thead tr {
    background: #f8fafc;
    border-bottom: 1px solid var(--sn-border-strong);
}
.sn-formal-cart-table thead th {
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}
.sn-formal-cart-table tbody tr {
    border-bottom: 1px solid var(--sn-border);
    transition: background-color 0.15s;
}
.sn-formal-cart-table tbody tr:last-child {
    border-bottom: none;
}
.sn-formal-cart-table tbody tr:hover {
    background-color: #fdfdfd;
}
.sn-formal-cart-table tbody td {
    padding: 16px;
    vertical-align: middle;
}
.sn-cart-item-row.removing {
    opacity: 0;
    transform: translateX(-20px);
    transition: all 0.25s ease;
}

/* Image Thumbnail in Table */
.sn-img-container {
    width: 68px;
    height: 68px;
    background: #f8fafc;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--sn-border-strong);
    overflow: hidden;
}
.sn-img-container img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

/* Details Cell */
.sn-item-name {
    font-size: 14.5px;
    font-weight: 700;
    margin: 0 0 3px 0;
    line-height: 1.3;
}
.sn-item-name a {
    color: var(--sn-dark);
    text-decoration: none;
    transition: color 0.15s;
}
.sn-item-name a:hover {
    color: #0284c7;
}
.sn-item-specs {
    font-size: 12px;
    color: var(--sn-muted);
    margin-bottom: 4px;
}
.sn-item-variant-swatches {
    font-size: 12px;
    color: var(--sn-muted);
    margin-bottom: 4px;
}
.sn-active-color-name {
    color: var(--sn-dark);
    font-weight: 600;
}
.sn-stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    color: #059669;
}

/* Pricing Cell */
.sn-curr-price {
    font-size: 15px;
    font-weight: 800;
    color: var(--sn-dark);
}
.sn-old-price {
    font-size: 12px;
    color: #94a3b8;
    text-decoration: line-through;
    margin-top: 1px;
}

/* Quantity Stepper in Table */
.sn-stepper-control {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid var(--sn-border-strong);
    border-radius: 6px;
    padding: 2px;
}
.sn-step-btn {
    width: 26px;
    height: 26px;
    border: none;
    background: none;
    color: var(--sn-dark);
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: background 0.15s;
}
.sn-step-btn:hover {
    background: #f1f5f9;
}
.sn-qty-input {
    width: 32px;
    border: none;
    background: none;
    text-align: center;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--sn-dark);
    outline: none;
}

/* Line Total */
.sn-item-total-price {
    font-size: 16px;
    font-weight: 800;
    color: var(--sn-dark);
}

/* Table Row Action Buttons */
.sn-row-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.sn-action-btn-icon {
    width: 30px;
    height: 30px;
    border: 1px solid var(--sn-border-strong);
    background: #ffffff;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--sn-muted);
    cursor: pointer;
    transition: all 0.15s ease;
}
.sn-action-btn-icon:hover {
    color: var(--sn-dark);
    border-color: #cbd5e1;
    background: #f8fafc;
}
.sn-action-btn-icon.remove:hover {
    color: #ef4444;
    border-color: #fca5a5;
    background: #fef2f2;
}

/* 5. FORMAL ORDER SUMMARY SECTION (BELOW TABLE IN SINGLE COLUMN) */
.sn-formal-summary-section {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid var(--sn-border-strong);
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.sn-summary-grid-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
}
@media (max-width: 768px) {
    .sn-summary-grid-box {
        grid-template-columns: 100%;
        gap: 24px;
    }
}
.sn-block-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0 0 4px 0;
}
.sn-block-sub {
    font-size: 12.5px;
    color: var(--sn-muted);
    margin: 0 0 16px 0;
}

/* Promo Subcard */
.sn-promo-input-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}
.sn-promo-input {
    flex: 1;
    background: #ffffff;
    border: 1px solid var(--sn-border-strong);
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 13.5px;
    color: var(--sn-dark);
    outline: none;
    transition: border-color 0.15s;
}
.sn-promo-input:focus {
    border-color: var(--sn-dark);
}
.sn-promo-btn {
    background: #f1f5f9;
    border: 1px solid var(--sn-border-strong);
    color: var(--sn-dark);
    font-size: 13px;
    font-weight: 700;
    padding: 0 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
}
.sn-promo-btn:hover {
    background: #e2e8f0;
}
.sn-promo-alert {
    font-size: 12px;
    margin-top: 6px;
    display: none;
}
.sn-promo-alert.success { display: block; color: #059669; }
.sn-promo-alert.error { display: block; color: #dc2626; }

/* Calculation Table */
.sn-summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
}
.sn-summary-table td {
    padding: 8px 0;
    font-size: 13.5px;
}
.sn-summary-table td.lbl {
    color: var(--sn-muted);
}
.sn-summary-table td.val {
    text-align: right;
    font-weight: 700;
    color: var(--sn-dark);
}
.sn-summary-table .sn-shipping-free {
    color: #059669 !important;
    font-weight: 800;
}
.sn-coupon-row {
    display: none;
}
.sn-coupon-row.active {
    display: table-row;
}
.text-green { color: #059669 !important; }
.sn-summary-table tr.sn-total-tr td {
    padding-top: 14px;
    border-top: 1px solid var(--sn-border-strong);
}
.lbl-total {
    font-size: 16px;
    font-weight: 800;
    color: var(--sn-dark);
}
.val-total {
    text-align: right;
    font-size: 20px;
    font-weight: 800;
    color: var(--sn-dark);
}

/* Savings Pill */
.sn-savings-pill {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #059669;
    font-size: 12.5px;
    font-weight: 600;
    padding: 8px 14px;
    border-radius: 6px;
    margin-bottom: 16px;
}

/* Proceed Button */
.sn-btn-checkout {
    width: 100%;
    background: #fab802;
    border: none;
    color: #111827;
    font-size: 15px;
    font-weight: 800;
    padding: 13px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sn-btn-checkout:hover {
    background: #e5a700;
}
.sn-btn-checkout:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Confidence Banner */
.sn-confidence-banner {
    background: #f8fafc;
    border: 1px solid var(--sn-border-strong);
    border-radius: 8px;
    padding: 14px 20px;
}
.sn-conf-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.sn-conf-shield {
    display: flex;
    align-items: center;
    justify-content: center;
}
.sn-conf-title {
    font-size: 13.5px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0;
}
.sn-conf-desc {
    font-size: 12px;
    color: var(--sn-muted);
    margin: 2px 0 0 0;
}

/* 6. RECOMMENDATIONS SECTION */
.sn-recommendations-section {
    margin-top: 36px;
}
.sn-recom-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.sn-recom-title {
    font-size: 18px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0;
}
.sn-recom-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    font-weight: 700;
    color: var(--sn-dark);
    text-decoration: none;
}
.sn-recom-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.sn-recom-card {
    background: #ffffff;
    border: 1px solid var(--sn-border-strong);
    border-radius: 8px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}
.sn-recom-img-box {
    width: 54px;
    height: 54px;
    background: #f8fafc;
    border-radius: 6px;
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
    font-size: 12.5px;
    font-weight: 700;
    color: var(--sn-dark);
    margin: 0 0 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sn-recom-item-sub {
    font-size: 11px;
    color: var(--sn-muted);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sn-recom-price-row {
    display: flex;
    align-items: baseline;
    gap: 5px;
}
.sn-recom-price {
    font-size: 13.5px;
    font-weight: 800;
    color: var(--sn-dark);
}
.sn-recom-old-price {
    font-size: 11px;
    color: #94a3b8;
    text-decoration: line-through;
}
.sn-recom-add-btn {
    position: absolute;
    right: 10px;
    bottom: 10px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #fab802;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

/* 7. TRUST ASSURANCE BAR */
.sn-trust-assurance-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    background: #ffffff;
    border: 1px solid var(--sn-border-strong);
    border-radius: 8px;
    padding: 16px 20px;
    margin-top: 36px;
}
.sn-trust-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.sn-trust-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid var(--sn-border-strong);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.sn-trust-info h4 {
    font-size: 13px;
    font-weight: 800;
    color: var(--sn-dark);
    margin: 0 0 1px 0;
}
.sn-trust-info p {
    font-size: 11.5px;
    color: var(--sn-muted);
    margin: 0;
}

/* 8. EMPTY CART STAGE */
.sn-empty-cart-stage {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid var(--sn-border-strong);
    padding: 48px 20px;
    text-align: center;
    margin: 40px auto;
    max-width: 480px;
}
.sn-empty-icon-ring {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #fffbeb;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px auto;
}
.sn-empty-cart-stage h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--sn-dark);
    margin-bottom: 6px;
}
.sn-empty-cart-stage p {
    font-size: 13.5px;
    color: var(--sn-muted);
    margin-bottom: 20px;
}
.sn-btn-primary {
    background: #fab802;
    color: #111827;
    font-weight: 800;
    font-size: 13.5px;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-block;
}

/* STICKY MOBILE DOCK (HIDDEN ON DESKTOP) */
.sn-mobile-sticky-dock {
    display: none;
}

/* TOAST NOTIFICATIONS */
.sn-toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.sn-toast {
    background: #0f172a;
    color: #ffffff;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: toastSlideUp 0.25s ease-out;
}
.sn-toast.success { border-left: 3px solid #10b981; }
.sn-toast.error { border-left: 3px solid #ef4444; }

@keyframes toastSlideUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

/* MOBILE RESPONSIVE STYLING */
@media (max-width: 768px) {
    .sn-cart-page-wrapper {
        padding-top: 14px;
        padding-bottom: 120px;
    }
    .sn-cart-single-container {
        padding-left: 12px;
        padding-right: 12px;
    }
    .sn-cart-page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .sn-page-title {
        font-size: 20px;
    }
    
    /* Table headers responsive */
    .sn-formal-cart-table thead {
        display: none; /* Hide table headers on narrow mobile */
    }
    .sn-formal-cart-table, 
    .sn-formal-cart-table tbody, 
    .sn-formal-cart-table tr, 
    .sn-formal-cart-table td {
        display: block;
        width: 100%;
        box-sizing: border-box;
    }
    .sn-formal-cart-table tbody tr {
        padding: 14px;
        border-bottom: 1px solid var(--sn-border-strong);
        position: relative;
    }
    .sn-formal-cart-table tbody td {
        padding: 4px 0;
        border: none;
    }
    .sn-item-checkbox-cell {
        position: absolute;
        top: 16px;
        left: 14px;
        z-index: 2;
    }
    .sn-item-image-cell {
        margin-left: 32px;
        margin-bottom: 8px;
    }
    .sn-item-details-cell {
        margin-left: 32px;
        margin-bottom: 8px;
    }
    .sn-item-pricing-cell {
        text-align: left !important;
        margin-left: 32px;
        margin-bottom: 8px;
    }
    .sn-stepper-control {
        margin-left: 32px;
    }
    .sn-item-total-cell {
        text-align: left !important;
        margin-left: 32px;
        margin-top: 6px;
    }
    .sn-row-actions {
        position: absolute;
        top: 14px;
        right: 14px;
    }
    
    .sn-recom-grid {
        grid-template-columns: 100%;
    }
    .sn-trust-assurance-bar {
        grid-template-columns: 100%;
    }

    .sn-mobile-sticky-dock {
        display: block;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 999;
        background: #ffffff;
        border-top: 1px solid var(--sn-border-strong);
        box-shadow: 0 -2px 10px rgba(0,0,0,0.06);
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
        cursor: pointer;
        user-select: none;
    }
    .sn-mobile-all-check input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
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
        font-size: 13.5px;
        font-weight: 800;
        padding: 9px 16px;
        border-radius: 6px;
        cursor: pointer;
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
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>${message}</span>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'all 0.25s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 250);
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
        }, 250);
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
    }, 280);

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
        }, 250);
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

// 7. PROMO CODE APPLICATION
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

// 8. QUICK ADD RECOMMENDATION TO CART
let isQuickCartSubmitting = false;
function quickAddToCart(productId, productName, price, image, subtitle) {
    if (isQuickCartSubmitting) return;
    isQuickCartSubmitting = true;

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
        setTimeout(() => window.location.reload(), 600);
    })
    .catch(() => {
        showToast(`"${productName}" added to cart!`, 'success');
        setTimeout(() => window.location.reload(), 600);
    });
}

// 9. CHECK EMPTY STATE & AUTO-TRANSITION
function checkEmptyState() {
    const rows = document.querySelectorAll('.sn-cart-item-row');
    if (rows.length === 0) {
        window.location.href = 'cart.php?empty=1';
    }
}

// 10. UPDATE HEADER CART BADGES
function updateHeaderCartBadges(count) {
    const desktopBadge = document.getElementById('sn-cart-badge-count');
    if (desktopBadge) desktopBadge.textContent = count;
    const mobileBadge = document.querySelector('.sn-dock-cart-badge');
    if (mobileBadge) mobileBadge.textContent = count;
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

<?php require_once(__DIR__ . '/footer.php'); ?>