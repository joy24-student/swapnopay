<?php require_once('header.php'); ?>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);                            
foreach ($result as $row) {
    $banner_cart = $row['banner_cart'];
}
?>

<?php
$error_message = '';
if(isset($_POST['form1'])) {
    $i = 0;
    $statement = $pdo->prepare("SELECT * FROM tbl_product");
    $statement->execute();
    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $i++;
        $table_product_id[$i] = $row['p_id'];
        $table_quantity[$i] = $row['p_qty'];
    }

    $i=0;
    foreach($_POST['product_id'] as $val) {
        $i++;
        $arr1[$i] = $val;
    }
    $i=0;
    foreach($_POST['quantity'] as $val) {
        $i++;
        $arr2[$i] = $val;
    }
    $i=0;
    foreach($_POST['product_name'] as $val) {
        $i++;
        $arr3[$i] = $val;
    }
    
    $allow_update = 1;
    for($i=1;$i<=count($arr1);$i++) {
        for($j=1;$j<=count($table_product_id);$j++) {
            if($arr1[$i] == $table_product_id[$j]) {
                $temp_index = $j;
                break;
            }
        }
        if($table_quantity[$temp_index] < $arr2[$i]) {
        	$allow_update = 0;
            $error_message .= '"'.$arr2[$i].'" items are not available for "'.$arr3[$i].'"\n';
        } else {
            $_SESSION['cart_p_qty'][$i] = $arr2[$i];
            
            // NEW: Update database with new quantity if customer is logged in
            if (isset($_SESSION['customer']['cust_id'])) {
                require_once('admin/inc/functions.php');
                // Get the product_id, size_id, color_id for this item
                $product_id = $arr1[$i];
                $size_id = $_SESSION['cart_size_id'][$i] ?? null;
                $color_id = $_SESSION['cart_color_id'][$i] ?? null;
                $new_qty = $arr2[$i];
                
                updateCartItemQuantity($pdo, $_SESSION['customer']['cust_id'], $product_id, $size_id, $color_id, $new_qty);
            }
        }
    }
    
    // Save cart to database after quantity update
    if($allow_update == 0) {
        $error_message .= '\nOther items quantity are updated successfully!';
        ?>
        <script>alert('<?php echo $error_message; ?>');</script>
        <?php
    } else {
        ?>
        <script>alert('All Items Quantity Update is Successful!');</script>
        <?php
    }
}
?>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<div class="page-banner" style="background-image: linear-gradient(135deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.5) 100%), url(assets/uploads/<?php echo $banner_cart; ?>)">
    <div class="overlay"></div>
    <div class="page-banner-inner">
        <h1><?php echo LANG_VALUE_18; ?></h1>
        <p>Review & manage your selected items</p>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <?php if(!isset($_SESSION['cart_p_id'])): ?>
                    <div class="empty-cart">
                        <div class="empty-cart-icon">
                            <i class="fa fa-shopping-cart"></i>
                        </div>
                        <h2 class="text-center">Your cart is empty</h2>
                        <p class="text-center">Add products to the cart to view them here</p>
                        <div class="text-center">
                            <a href="index.php" class="btn btn-primary btn-gradient">Start Shopping</a>
                        </div>
                    </div>
                <?php else: ?>
                
                <!-- Desktop Layout -->
                <div class="desktop-cart d-none d-md-block">
                    <div class="cart-header">
                        <div class="row">
                            <div class="col-12">
                                <div class="cart-progress">
                                    <div class="progress-steps">
                                        <div class="step active">
                                            <div class="step-number">1</div>
                                            <div class="step-label">Cart</div>
                                        </div>
                                        <div class="step">
                                            <div class="step-number">2</div>
                                            <div class="step-label">Checkout</div>
                                        </div>
                                        <div class="step">
                                            <div class="step-number">3</div>
                                            <div class="step-label">Confirmation</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form id="cart-form" action="checkout.php" method="post">
                        <?php $csrf->echoInputField(); ?>
                        <div class="cart-container">
                            <div class="cart-header-row">
                                <div class="cart-col-check">
                                    <input type="checkbox" id="select-all" class="select-all-checkbox" checked>
                                    <label for="select-all">Select All</label>
                                </div>
                                <div class="cart-col-product">Product</div>
                                <div class="cart-col-price">Price</div>
                                <div class="cart-col-quantity">Quantity</div>
                                <div class="cart-col-total">Total</div>
                                <div class="cart-col-actions">Actions</div>
                            </div>

                            <?php
                            $table_total_price = 0;
                            $i = 0;
                            $cart_p_ids = array_values($_SESSION['cart_p_id']);
                            $cart_size_ids = array_values($_SESSION['cart_size_id']);
                            $cart_size_names = array_values($_SESSION['cart_size_name']);
                            $cart_color_ids = array_values($_SESSION['cart_color_id']);
                            $cart_color_names = array_values($_SESSION['cart_color_name']);
                            $cart_p_qtys = array_values($_SESSION['cart_p_qty']);
                            $cart_p_current_prices = array_values($_SESSION['cart_p_current_price']);
                            $cart_p_names = array_values($_SESSION['cart_p_name']);
                            $cart_p_featured_photos = array_values($_SESSION['cart_p_featured_photo']);
                            ?>

                            <div class="cart-items">
                                <?php for($i=0;$i<count($cart_p_ids);$i++): 
                                    $row_total_price = $cart_p_current_prices[$i] * $cart_p_qtys[$i];
                                    $table_total_price += $row_total_price;
                                ?>
                                <div class="cart-item-row animate-on-scroll" data-id="<?php echo $cart_p_ids[$i]; ?>">
                                    <div class="cart-col-check">
                                        <input type="checkbox" name="selected_items[]" value="<?php echo $i; ?>" class="item-checkbox" checked>
                                    </div>
                                    <div class="cart-col-product">
                                        <div class="product-info">
                                            <div class="product-image">
                                                <img src="assets/uploads/<?php echo $cart_p_featured_photos[$i]; ?>" alt="<?php echo $cart_p_names[$i]; ?>" class="product-img">
                                            </div>
                                            <div class="product-details">
                                                <h4 class="product-name">
                                                    <a href="product.php?id=<?php echo $cart_p_ids[$i]; ?>">
                                                        <?php echo $cart_p_names[$i]; ?>
                                                    </a>
                                                </h4>
                                                <div class="product-variants">
                                                    <?php if($cart_size_names[$i]): ?>
                                                    <span class="variant-tag size-tag">Size: <?php echo $cart_size_names[$i]; ?></span>
                                                    <?php endif; ?>
                                                    <?php if($cart_color_names[$i]): ?>
                                                    <span class="variant-tag color-tag">Color: <?php echo $cart_color_names[$i]; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="cart-col-price">
                                        <div class="price-display">
                                            <span class="currency"><?php echo LANG_VALUE_1; ?></span>
                                            <span class="price-value"><?php echo number_format($cart_p_current_prices[$i], 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="cart-col-quantity">
                                        <div class="quantity-control">
                                            <button type="button" class="quantity-btn minus" onclick="updateQuantity(<?php echo $i; ?>, -1)">-</button>
                                            <input type="number" 
                                                   class="quantity-input" 
                                                   name="quantity[]" 
                                                   value="<?php echo $cart_p_qtys[$i]; ?>" 
                                                   min="1"
                                                   data-index="<?php echo $i; ?>"
                                                   data-price="<?php echo $cart_p_current_prices[$i]; ?>"
                                                   onchange="updateItemTotal(<?php echo $i; ?>)">
                                            <button type="button" class="quantity-btn plus" onclick="updateQuantity(<?php echo $i; ?>, 1)">+</button>
                                        </div>
                                        <input type="hidden" name="product_id[]" value="<?php echo $cart_p_ids[$i]; ?>">
                                        <input type="hidden" name="product_name[]" value="<?php echo $cart_p_names[$i]; ?>">
                                    </div>
                                    <div class="cart-col-total">
                                        <div class="total-display" id="item-total-<?php echo $i; ?>">
                                            <span class="currency"><?php echo LANG_VALUE_1; ?></span>
                                            <span class="total-value"><?php echo number_format($row_total_price, 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="cart-col-actions">
                                        <button type="button" class="action-btn delete-btn" onclick="removeItem(<?php echo $i; ?>, <?php echo $cart_p_ids[$i]; ?>, <?php echo $cart_size_ids[$i]; ?>, <?php echo $cart_color_ids[$i]; ?>)" title="Remove item">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        <button type="button" class="action-btn save-btn" onclick="saveForLater(<?php echo $cart_p_ids[$i]; ?>)" title="Save for later">
                                            <i class="fa fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>

                            <div class="cart-summary">
                                <div class="summary-card">
                                    <h3>Order Summary</h3>
                                    <div class="summary-row">
                                        <span>Subtotal (<span id="selected-count"><?php echo count($cart_p_ids); ?></span> items)</span>
                                        <span class="summary-value" id="subtotal-display"><?php echo LANG_VALUE_1 . number_format($table_total_price, 2); ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Shipping</span>
                                        <span class="summary-value">Calculated at checkout</span>
                                    </div>
                                    <div class="summary-row total-row">
                                        <span>Estimated Total</span>
                                        <span class="summary-value total-value" id="total-display"><?php echo LANG_VALUE_1 . number_format($table_total_price, 2); ?></span>
                                    </div>
                                    <div class="summary-actions">
                                        <a href="index.php" class="btn btn-outline">Continue Shopping</a>
                                        <button type="submit" class="btn btn-primary btn-gradient checkout-btn">
                                            <span>Proceed to Checkout</span>
                                            <i class="fa fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Mobile Layout -->
                <div class="mobile-cart d-md-none">
                    <div class="mobile-cart-header">
                        <h3>Your Cart (<span id="mobile-item-count"><?php echo count($cart_p_ids); ?></span> items)</h3>
                        <div class="select-all-mobile">
                            <input type="checkbox" id="select-all-mobile" checked>
                            <label for="select-all-mobile">Select All</label>
                        </div>
                    </div>

                    <div class="mobile-cart-items">
                        <?php for($i=0;$i<count($cart_p_ids);$i++): 
                            $row_total_price = $cart_p_current_prices[$i] * $cart_p_qtys[$i];
                        ?>
                        <div class="mobile-cart-item" data-id="<?php echo $cart_p_ids[$i]; ?>">
                            <div class="mobile-item-header">
                                <input type="checkbox" name="mobile_selected[]" value="<?php echo $i; ?>" class="mobile-item-checkbox" checked>
                                <button class="mobile-delete-btn" onclick="removeItem(<?php echo $i; ?>, <?php echo $cart_p_ids[$i]; ?>, <?php echo $cart_size_ids[$i]; ?>, <?php echo $cart_color_ids[$i]; ?>)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                            
                            <div class="mobile-item-content">
                                <div class="mobile-item-image">
                                    <img src="assets/uploads/<?php echo $cart_p_featured_photos[$i]; ?>" alt="<?php echo $cart_p_names[$i]; ?>">
                                </div>
                                <div class="mobile-item-details">
                                    <h4 class="mobile-item-name"><?php echo $cart_p_names[$i]; ?></h4>
                                    <div class="mobile-item-variants">
                                        <?php if($cart_size_names[$i]): ?>
                                        <span class="mobile-variant">Size: <?php echo $cart_size_names[$i]; ?></span>
                                        <?php endif; ?>
                                        <?php if($cart_color_names[$i]): ?>
                                        <span class="mobile-variant">Color: <?php echo $cart_color_names[$i]; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mobile-item-price">
                                        <span class="price"><?php echo LANG_VALUE_1 . number_format($cart_p_current_prices[$i], 2); ?></span>
                                    </div>
                                    
                                    <div class="mobile-item-controls">
                                        <div class="mobile-quantity-control">
                                            <button class="mobile-qty-btn minus" onclick="updateQuantity(<?php echo $i; ?>, -1)">-</button>
                                            <input type="number" 
                                                   class="mobile-qty-input" 
                                                   value="<?php echo $cart_p_qtys[$i]; ?>" 
                                                   min="1"
                                                   data-index="<?php echo $i; ?>"
                                                   data-price="<?php echo $cart_p_current_prices[$i]; ?>"
                                                   onchange="updateItemTotal(<?php echo $i; ?>)">
                                            <button class="mobile-qty-btn plus" onclick="updateQuantity(<?php echo $i; ?>, 1)">+</button>
                                        </div>
                                        <div class="mobile-item-total" id="mobile-item-total-<?php echo $i; ?>">
                                            <?php echo LANG_VALUE_1 . number_format($row_total_price, 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="product_id[]" value="<?php echo $cart_p_ids[$i]; ?>">
                            <input type="hidden" name="product_name[]" value="<?php echo $cart_p_names[$i]; ?>">
                            <input type="hidden" name="quantity[]" value="<?php echo $cart_p_qtys[$i]; ?>">
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div class="mobile-cart-summary">
                        <div class="mobile-summary-card">
                            <div class="mobile-summary-row">
                                <span>Subtotal (<span id="mobile-selected-count"><?php echo count($cart_p_ids); ?></span> items)</span>
                                <span class="mobile-summary-value" id="mobile-subtotal"><?php echo LANG_VALUE_1 . number_format($table_total_price, 2); ?></span>
                            </div>
                            <div class="mobile-summary-row">
                                <span>Shipping</span>
                                <span class="mobile-summary-value">Calculated at checkout</span>
                            </div>
                            <div class="mobile-summary-total">
                                <span>Estimated Total</span>
                                <span class="mobile-total-value" id="mobile-total"><?php echo LANG_VALUE_1 . number_format($table_total_price, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="mobile-cart-actions">
                            <a href="index.php" class="mobile-action-btn continue-btn">
                                <i class="fa fa-shopping-bag"></i>
                                <span>Continue Shopping</span>
                            </a>
                            <form action="checkout.php" method="post" class="checkout-form-mobile">
                                <?php $csrf->echoInputField(); ?>
                                <button type="submit" class="mobile-action-btn checkout-btn-mobile btn-gradient">
                                    <span>Checkout</span>
                                    <i class="fa fa-arrow-right"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    /* Global Styles */
    .empty-cart {
        text-align: center;
        padding: 60px 20px;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 20px;
        margin: 40px 0;
        animation: fadeIn 0.8s ease;
    }
    
    .empty-cart-icon {
        font-size: 80px;
        color: #6c757d;
        margin-bottom: 20px;
        animation: bounce 2s infinite;
    }
    
    .btn-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        transition: all 0.3s ease;
    }
    
    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }
    
    /* Desktop Layout Styles */
    .desktop-cart {
        animation: slideUp 0.5s ease;
    }
    
    .cart-progress {
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .progress-steps {
        display: flex;
        justify-content: center;
        gap: 40px;
    }
    
    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }
    
    .step:not(:last-child):after {
        content: '';
        position: absolute;
        top: 20px;
        right: -30px;
        width: 60px;
        height: 2px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
    
    .step-number {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        margin-bottom: 10px;
    }
    
    .step.active .step-number {
        animation: pulse 2s infinite;
    }
    
    .cart-header-row {
        display: grid;
        grid-template-columns: 50px 2fr 1fr 1fr 1fr 80px;
        gap: 20px;
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0;
        font-weight: 600;
    }
    
    .cart-items {
        background: white;
        border-radius: 0 0 10px 10px;
        overflow: hidden;
        box-shadow: 0 5px 25px rgba(0,0,0,0.1);
    }
    
    .cart-item-row {
        display: grid;
        grid-template-columns: 50px 2fr 1fr 1fr 1fr 80px;
        gap: 20px;
        padding: 25px 20px;
        border-bottom: 1px solid #eee;
        align-items: center;
        transition: all 0.3s ease;
    }
    
    .cart-item-row:hover {
        background: #f8f9ff;
        transform: translateX(5px);
    }
    
    .cart-item-row.removing {
        animation: slideOut 0.3s ease forwards;
    }
    
    .product-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .product-image {
        width: 80px;
        height: 80px;
        border-radius: 10px;
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .product-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .product-img:hover {
        transform: scale(1.1);
    }
    
    .product-details h4 {
        margin: 0 0 8px 0;
        font-size: 16px;
    }
    
    .product-details h4 a {
        color: #333;
        text-decoration: none;
    }
    
    .product-details h4 a:hover {
        color: #667eea;
    }
    
    .product-variants {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .variant-tag {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .size-tag {
        background: #e3f2fd;
        color: #1976d2;
    }
    
    .color-tag {
        background: #f3e5f5;
        color: #7b1fa2;
    }
    
    .price-display, .total-display {
        font-weight: 600;
        color: #333;
    }
    
    .currency {
        color: #667eea;
        font-weight: 500;
    }
    
    .quantity-control {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .quantity-btn {
        width: 32px;
        height: 32px;
        border: 2px solid #667eea;
        background: white;
        color: #667eea;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: bold;
    }
    
    .quantity-btn:hover {
        background: #667eea;
        color: white;
        transform: scale(1.1);
    }
    
    .quantity-input {
        width: 60px;
        height: 40px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        text-align: center;
        font-weight: 600;
        transition: border-color 0.3s ease;
    }
    
    .quantity-input:focus {
        border-color: #667eea;
        outline: none;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .delete-btn {
        background: linear-gradient(135deg, #ff6b6b 0%, #ff4757 100%);
        color: white;
    }
    
    .save-btn {
        background: linear-gradient(135deg, #4cd964 0%, #5ac8fa 100%);
        color: white;
    }
    
    .action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .cart-summary {
        margin-top: 30px;
    }
    
    .summary-card {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        position: sticky;
        top: 20px;
    }
    
    .summary-card h3 {
        margin-bottom: 25px;
        color: #333;
        font-weight: 600;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px dashed #eee;
    }
    
    .summary-row:last-child {
        border-bottom: none;
    }
    
    .summary-value {
        font-weight: 600;
        color: #333;
    }
    
    .total-row {
        font-size: 18px;
        font-weight: 700;
    }
    
    .total-value {
        color: #667eea;
        font-size: 24px;
    }
    
    .summary-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }
    
    .summary-actions .btn {
        flex: 1;
        padding: 15px;
        font-weight: 600;
    }
    
    .checkout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    /* Mobile Layout Styles */
    .mobile-cart {
        animation: fadeIn 0.5s ease;
    }
    
    .mobile-cart-header {
        background: white;
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .mobile-cart-header h3 {
        margin: 0;
        font-size: 18px;
    }
    
    .select-all-mobile {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .mobile-cart-items {
        margin-bottom: 20px;
    }
    
    .mobile-cart-item {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        animation: slideIn 0.5s ease;
        position: relative;
    }
    
    .mobile-cart-item.removing {
        animation: slideOut 0.3s ease forwards;
    }
    
    .mobile-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .mobile-delete-btn {
        background: linear-gradient(135deg, #ff6b6b 0%, #ff4757 100%);
        color: white;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .mobile-item-content {
        display: flex;
        gap: 15px;
    }
    
    .mobile-item-image {
        width: 80px;
        height: 80px;
        border-radius: 10px;
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .mobile-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .mobile-item-details {
        flex: 1;
    }
    
    .mobile-item-name {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
    }
    
    .mobile-item-variants {
        display: flex;
        gap: 8px;
        margin-bottom: 10px;
    }
    
    .mobile-variant {
        padding: 3px 10px;
        background: #f0f0f0;
        border-radius: 12px;
        font-size: 11px;
    }
    
    .mobile-item-price {
        font-weight: 600;
        color: #667eea;
        margin-bottom: 15px;
    }
    
    .mobile-item-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .mobile-quantity-control {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .mobile-qty-btn {
        width: 30px;
        height: 30px;
        border: 1px solid #667eea;
        background: white;
        color: #667eea;
        border-radius: 50%;
        font-weight: bold;
    }
    
    .mobile-qty-input {
        width: 50px;
        height: 35px;
        border: 1px solid #ddd;
        border-radius: 8px;
        text-align: center;
    }
    
    .mobile-item-total {
        font-weight: 700;
        color: #333;
        font-size: 16px;
    }
    
    .mobile-cart-summary {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        position: sticky;
        bottom: 20px;
        margin-bottom: 20px;
    }
    
    .mobile-summary-card {
        margin-bottom: 25px;
    }
    
    .mobile-summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        font-size: 14px;
    }
    
    .mobile-summary-value {
        font-weight: 600;
    }
    
    .mobile-summary-total {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #667eea;
        font-size: 18px;
        font-weight: 700;
    }
    
    .mobile-total-value {
        color: #667eea;
        font-size: 22px;
    }
    
    .mobile-cart-actions {
        display: flex;
        gap: 12px;
    }
    
    .mobile-action-btn {
        flex: 1;
        padding: 16px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .continue-btn {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }
    
    .checkout-btn-mobile {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .mobile-action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }
    
    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOut {
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-20px); }
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(102, 126, 234, 0); }
        100% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0); }
    }
    
    /* Ensure only one layout shows at a time when Bootstrap utilities are not present */
    .desktop-cart { display: block; }
    .mobile-cart { display: none; }
    /* Responsive */
    @media (max-width: 768px) {
        /* On small screens show mobile, hide desktop */
        .desktop-cart { display: none !important; }
        .mobile-cart { display: block !important; }
        .cart-header-row {
            display: none;
        }
        
        .summary-actions {
            flex-direction: column;
        }
        
        .mobile-summary-row {
            font-size: 16px;
        }
        
        .mobile-total-value {
            font-size: 24px;
        }
    }

    /* compact mobile checkout for very small screens */
    @media (max-width: 480px) {
        .mobile-cart-summary {
            padding: 12px !important;
            bottom: 8px !important;
            border-radius: 12px !important;
        }

        .mobile-summary-card {
            margin-bottom: 12px !important;
            padding: 8px 6px;
        }

        .mobile-summary-row {
            font-size: 13px !important;
        }

        .mobile-summary-total {
            font-size: 16px !important;
            padding-top: 12px !important;
            border-top-width: 1px !important;
        }

        .mobile-total-value {
            color: #667eea;
            font-size: 18px !important;
        }

        .mobile-action-btn {
            padding: 10px !important;
            font-size: 14px !important;
        }

        .checkout-btn-mobile {
            padding: 10px 12px !important;
        }

        /* ensure page content isn't hidden behind sticky footer card */
        .mobile-cart { padding-bottom: 110px !important; }
    }

    /* additional compacting for small-to-medium phones */
    @media (max-width: 600px) {
        .mobile-cart-summary { padding: 10px !important; }
        .mobile-summary-card { margin-bottom: 10px !important; }
        .mobile-summary-row { font-size: 13px !important; }
        .mobile-action-btn { padding: 10px 8px !important; font-size: 13px !important; }
        .checkout-btn-mobile { padding: 8px 10px !important; }
        .mobile-cart { padding-bottom: 100px !important; }
    }
</style>

<script>
// Global variables
let cartTotal = <?php echo (isset($table_total_price) && is_numeric($table_total_price)) ? $table_total_price : 0; ?>;
let cartItems = <?php echo (!empty($cart_p_ids) && is_array($cart_p_ids)) ? count($cart_p_ids) : 0; ?>;

// Update quantity function
function updateQuantity(index, change) {
    const input = document.querySelector(`.quantity-input[data-index="${index}"]`);
    const mobileInput = document.querySelector(`.mobile-qty-input[data-index="${index}"]`);
    
    let newValue = parseInt(input.value) + change;
    if (newValue < 1) newValue = 1;
    
    input.value = newValue;
    if (mobileInput) mobileInput.value = newValue;
    
    updateItemTotal(index);
}

// Update item total price
function updateItemTotal(index) {
    const input = document.querySelector(`.quantity-input[data-index="${index}"]`);
    const price = parseFloat(input.dataset.price);
    const quantity = parseInt(input.value);
    const itemTotal = price * quantity;
    
    // Update desktop display
    const totalDisplay = document.getElementById(`item-total-${index}`);
    if (totalDisplay) {
        const currency = totalDisplay.querySelector('.currency');
        const totalValue = totalDisplay.querySelector('.total-value');
        totalValue.textContent = itemTotal.toFixed(2);
    }
    
    // Update mobile display
    const mobileTotal = document.getElementById(`mobile-item-total-${index}`);
    if (mobileTotal) {
        mobileTotal.textContent = '<?php echo LANG_VALUE_1; ?>' + itemTotal.toFixed(2);
    }
    
    // Update main totals
    updateMainTotals();
}

// Update main totals
function updateMainTotals() {
    let subtotal = 0;
    // Use a Set to avoid counting the same item twice (desktop + mobile)
    const indexes = new Set();
    document.querySelectorAll('.item-checkbox:checked, .mobile-item-checkbox:checked').forEach(checkbox => {
        indexes.add(checkbox.value);
    });

    // For each unique index, pick the available quantity input (prefer desktop input)
    indexes.forEach(index => {
        let input = document.querySelector(`.quantity-input[data-index="${index}"]`);
        if (!input) {
            input = document.querySelector(`.mobile-qty-input[data-index="${index}"]`);
        }
        if (input) {
            const price = parseFloat(input.dataset.price || input.getAttribute('data-price') || 0);
            const quantity = parseInt(input.value) || 0;
            subtotal += price * quantity;
        }
    });
    const selectedCount = indexes.size;
    
    // Update desktop displays
    const subtotalEl = document.getElementById('subtotal-display');
    const totalEl = document.getElementById('total-display');
    const selectedCountEl = document.getElementById('selected-count');
    if (subtotalEl) subtotalEl.textContent = '<?php echo LANG_VALUE_1; ?>' + subtotal.toFixed(2);
    if (totalEl) totalEl.textContent = '<?php echo LANG_VALUE_1; ?>' + subtotal.toFixed(2);
    if (selectedCountEl) selectedCountEl.textContent = selectedCount;

    // Update mobile displays if they exist
    const mobileSubtotal = document.getElementById('mobile-subtotal');
    const mobileTotal = document.getElementById('mobile-total');
    const mobileSelectedCount = document.getElementById('mobile-selected-count');
    const mobileItemCount = document.getElementById('mobile-item-count');
    if (mobileSubtotal) mobileSubtotal.textContent = '<?php echo LANG_VALUE_1; ?>' + subtotal.toFixed(2);
    if (mobileTotal) mobileTotal.textContent = '<?php echo LANG_VALUE_1; ?>' + subtotal.toFixed(2);
    if (mobileSelectedCount) mobileSelectedCount.textContent = selectedCount;
    if (mobileItemCount) mobileItemCount.textContent = selectedCount;
    
    cartTotal = subtotal;
}

// Remove item from cart
function removeItem(index, productId, sizeId, colorId) {
    if (confirm('Are you sure you want to remove this item from your cart?')) {
        // Show removing animation
        const itemRow = document.querySelector(`.cart-item-row[data-id="${productId}"]`);
        const mobileItem = document.querySelector(`.mobile-cart-item[data-id="${productId}"]`);
        
        if (itemRow) itemRow.classList.add('removing');
        if (mobileItem) mobileItem.classList.add('removing');
        
        // Send AJAX request to remove item
        setTimeout(() => {
            window.location.href = `cart-item-delete.php?id=${productId}&size=${sizeId}&color=${colorId}`;
        }, 300);
    }
}

// Save for later function
function saveForLater(productId) {
    // In a real app, this would send an AJAX request
    alert('This item has been saved for later!');
}

// Initialize select all functionality and sync behavior
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const selectAllMobile = document.getElementById('select-all-mobile');

    function getUniqueItemIndexes() {
        const idxs = new Set();
        document.querySelectorAll('.item-checkbox, .mobile-item-checkbox').forEach(cb => idxs.add(cb.value));
        return idxs;
    }

    function updateSelectAllCheckboxes() {
        const allIdx = getUniqueItemIndexes();
        if (allIdx.size === 0) {
            if (selectAll) selectAll.checked = false;
            if (selectAllMobile) selectAllMobile.checked = false;
            return;
        }
        const checkedIdx = new Set();
        document.querySelectorAll('.item-checkbox:checked, .mobile-item-checkbox:checked').forEach(cb => checkedIdx.add(cb.value));
        const allChecked = checkedIdx.size === allIdx.size;
        if (selectAll) selectAll.checked = allChecked;
        if (selectAllMobile) selectAllMobile.checked = allChecked;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const desktopChecks = document.querySelectorAll('.item-checkbox');
            desktopChecks.forEach(cb => cb.checked = this.checked);
            const mobileChecks = document.querySelectorAll('.mobile-item-checkbox');
            mobileChecks.forEach(cb => cb.checked = this.checked);
            updateMainTotals();
            updateSelectAllCheckboxes();
        });
    }

    if (selectAllMobile) {
        selectAllMobile.addEventListener('change', function() {
            const mobileChecks = document.querySelectorAll('.mobile-item-checkbox');
            mobileChecks.forEach(cb => cb.checked = this.checked);
            const desktopChecks = document.querySelectorAll('.item-checkbox');
            desktopChecks.forEach(cb => cb.checked = this.checked);
            updateMainTotals();
            updateSelectAllCheckboxes();
        });
    }

    // Individual checkbox changes: update totals and sync select-all
    document.querySelectorAll('.item-checkbox, .mobile-item-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            updateMainTotals();
            updateSelectAllCheckboxes();
        });
    });

    // Input change listeners
    document.querySelectorAll('.quantity-input, .mobile-qty-input').forEach(input => {
        input.addEventListener('change', function() {
            updateItemTotal(this.dataset.index);
        });
    });

    // Animate items on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-on-scroll');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.cart-item-row, .mobile-cart-item').forEach(el => {
        observer.observe(el);
    });

    // Initialize state
    updateMainTotals();
    updateSelectAllCheckboxes();
});

// Form submission handling
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('cart-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const selectedItems = document.querySelectorAll('.item-checkbox:checked');
            if (selectedItems.length === 0) {
                e.preventDefault();
                alert('Please select at least one item to checkout.');
                return false;
            }
        });
    }
});
</script>

<?php require_once('footer.php'); ?>