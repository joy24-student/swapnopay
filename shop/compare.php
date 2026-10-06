<?php
require_once('header.php');

$ids_str = $_GET['ids'] ?? '';
$product_ids = [];
if (!empty($ids_str)) {
    $product_ids = array_filter(array_map('intval', explode(',', $ids_str)), fn($id) => $id > 0);
    // Limit to at most 4 products for comparison
    $product_ids = array_slice($product_ids, 0, 4);
}

$products = [];
if (!empty($product_ids)) {
    $id_placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $statement = $pdo->prepare("SELECT p.*, 
                                (SELECT COALESCE(AVG(r.rating), 0) FROM tbl_rating r WHERE r.p_id = p.p_id) as avg_rating,
                                (SELECT COUNT(r.rt_id) FROM tbl_rating r WHERE r.p_id = p.p_id) as total_reviews
                                FROM tbl_product p 
                                WHERE p.p_id IN ($id_placeholders) AND p.p_is_active = 1");
    $statement->execute($product_ids);
    $products = $statement->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
.sn-compare-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 16px 60px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
.sn-compare-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.sn-compare-header h1 {
    font-size: 26px;
    font-weight: 800;
    color: #111827;
    margin: 0;
    letter-spacing: -0.02em;
}
.sn-compare-card-container {
    overflow-x: auto;
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.sn-compare-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}
.sn-compare-table th, 
.sn-compare-table td {
    padding: 16px 20px;
    border: 1px solid #f3f4f6;
    text-align: center;
    vertical-align: middle;
}
.sn-compare-table th.sn-compare-prop,
.sn-compare-table td.sn-compare-prop {
    width: 160px;
    text-align: left;
    font-weight: 700;
    color: #4b5563;
    background-color: #f9fafb;
    border-right: 2px solid #e5e7eb;
}
.sn-compare-prod-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: inherit;
    position: relative;
}
.sn-compare-img {
    width: 140px;
    height: 140px;
    object-fit: cover;
    border-radius: 12px;
    background: #f9fafb;
    margin-bottom: 12px;
    border: 1px solid #f3f4f6;
}
.sn-compare-name {
    font-size: 15px;
    font-weight: 700;
    color: #111827;
    line-height: 1.35;
    margin-bottom: 8px;
    max-width: 200px;
}
.sn-compare-price {
    font-size: 18px;
    font-weight: 800;
    color: #059669;
}
.sn-compare-old-price {
    font-size: 13px;
    color: #9ca3af;
    text-decoration: line-through;
    margin-left: 6px;
}
.sn-stock-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.sn-stock-in {
    background: #ecfdf5;
    color: #059669;
}
.sn-stock-out {
    background: #fef2f2;
    color: #dc2626;
}
.sn-compare-btn-buy {
    background: #111827;
    color: #ffffff !important;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: background 0.2s;
}
.sn-compare-btn-buy:hover {
    background: #374151;
}
.sn-compare-remove {
    background: none;
    border: none;
    color: #ef4444;
    font-size: 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 8px;
}
.sn-compare-empty {
    text-align: center;
    padding: 60px 20px;
    background: #ffffff;
    border-radius: 16px;
    border: 1px dashed #d1d5db;
}
.sn-compare-empty i {
    font-size: 48px;
    color: #9ca3af;
    margin-bottom: 16px;
}
.sn-compare-empty h3 {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 8px;
}
.sn-compare-empty p {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 20px;
}
</style>

<div class="sn-compare-wrapper">
    <nav class="sn-breadcrumbs" style="margin-bottom: 16px;" aria-label="Breadcrumb">
        <a href="<?php echo BASE_URL; ?>">Home</a>
        <span class="sn-crumb-sep">›</span>
        <span class="sn-crumb-current">Product Comparison</span>
    </nav>

    <div class="sn-compare-header">
        <h1>Compare Products</h1>
        <?php if (!empty($products)): ?>
            <button onclick="clearComparison()" class="sn-compare-remove" style="font-weight: 600;">
                <i class="fas fa-trash-alt"></i> Clear All
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="sn-compare-empty">
            <i class="fas fa-balance-scale-right"></i>
            <h3>No Products Selected for Comparison</h3>
            <p>Browse our catalog and tap "Compare" on products to see their features side-by-side.</p>
            <a href="<?php echo BASE_URL; ?>categories.php" class="sn-compare-btn-buy" style="padding: 10px 24px; font-size: 14px;">
                <i class="fas fa-shopping-bag"></i> Browse Products
            </a>
        </div>
    <?php else: ?>
        <div class="sn-compare-card-container">
            <table class="sn-compare-table">
                <thead>
                    <tr>
                        <th class="sn-compare-prop">Product</th>
                        <?php foreach ($products as $prod): ?>
                            <th>
                                <div class="sn-compare-prod-card">
                                    <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo htmlspecialchars($prod['p_featured_photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($prod['p_name']); ?>" 
                                         class="sn-compare-img"
                                         onerror="this.src='<?php echo BASE_URL; ?>assets/images/no-image.png'">
                                    <div class="sn-compare-name"><?php echo htmlspecialchars($prod['p_name']); ?></div>
                                    <a href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$prod['p_id']; ?>" class="sn-compare-btn-buy">
                                        View Details
                                    </a>
                                    <button onclick="removeProduct(<?php echo (int)$prod['p_id']; ?>)" class="sn-compare-remove">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="sn-compare-prop">Price</td>
                        <?php foreach ($products as $prod): ?>
                            <td>
                                <span class="sn-compare-price">৳ <?php echo number_format((float)$prod['p_current_price']); ?></span>
                                <?php if (!empty($prod['p_old_price']) && (float)$prod['p_old_price'] > (float)$prod['p_current_price']): ?>
                                    <span class="sn-compare-old-price">৳ <?php echo number_format((float)$prod['p_old_price']); ?></span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="sn-compare-prop">Availability</td>
                        <?php foreach ($products as $prod): ?>
                            <td>
                                <?php if ((int)$prod['p_qty'] > 0): ?>
                                    <span class="sn-stock-badge sn-stock-in"><i class="fas fa-check"></i> In Stock</span>
                                <?php else: ?>
                                    <span class="sn-stock-badge sn-stock-out"><i class="fas fa-times"></i> Out of Stock</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="sn-compare-prop">Customer Rating</td>
                        <?php foreach ($products as $prod): ?>
                            <td>
                                <div style="display: flex; align-items: center; justify-content: center; gap: 4px; color: #f59e0b;">
                                    <i class="fas fa-star"></i>
                                    <strong style="color: #111827;"><?php echo number_format((float)$prod['avg_rating'], 1); ?></strong>
                                    <span style="color: #9ca3af; font-size: 12px;">(<?php echo (int)$prod['total_reviews']; ?>)</span>
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="sn-compare-prop">Description</td>
                        <?php foreach ($products as $prod): ?>
                            <td style="font-size: 13px; color: #4b5563; max-width: 250px; line-height: 1.5;">
                                <?php echo htmlspecialchars(mb_strimwidth(strip_tags($prod['p_short_description'] ?? ''), 0, 150, '...')); ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="sn-compare-prop">Action</td>
                        <?php foreach ($products as $prod): ?>
                            <td>
                                <a href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$prod['p_id']; ?>" class="sn-compare-btn-buy" style="width: 100%; box-sizing: border-box;">
                                    <i class="fas fa-shopping-cart"></i> Buy Now
                                </a>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function removeProduct(id) {
    const urlParams = new URLSearchParams(window.location.search);
    let ids = (urlParams.get('ids') || '').split(',').map(x => parseInt(x.trim())).filter(x => x > 0 && x !== id);
    if (ids.length > 0) {
        window.location.href = 'compare.php?ids=' + ids.join(',');
    } else {
        window.location.href = 'compare.php';
    }
}

function clearComparison() {
    window.location.href = 'compare.php';
}
</script>

<?php require_once('footer.php'); ?>
