<?php
require_once('header.php');

$store_name = $settings['site_name'] ?? $settings['meta_title_home'] ?? 'ShopNext';
$store_address = !empty($settings['contact_address']) ? $settings['contact_address'] : 'Dhaka, Bangladesh';
$store_phone = !empty($settings['contact_phone']) ? $settings['contact_phone'] : '+880 1700-000000';
$store_email = !empty($settings['contact_email']) ? $settings['contact_email'] : 'support@swapnopay.top';

// Clean phone for whatsapp & tel
$clean_phone = preg_replace('/[^0-9+]/', '', $store_phone);

// Fetch top categories for browsing
$top_cats = [];
try {
    $stmt_cats = $pdo->query("SELECT * FROM tbl_top_category WHERE show_on_menu = 1 ORDER BY tcat_id ASC LIMIT 8");
    if ($stmt_cats) {
        $top_cats = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}
?>

<style>
.sn-stores-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 16px 60px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
.sn-stores-hero {
    text-align: center;
    padding: 32px 16px 24px;
    margin-bottom: 24px;
}
.sn-stores-hero h1 {
    font-size: 28px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 8px;
    letter-spacing: -0.02em;
}
.sn-stores-hero p {
    font-size: 15px;
    color: #6b7280;
    max-width: 600px;
    margin: 0 auto;
}
.sn-stores-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}
.sn-store-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.sn-store-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.08);
}
.sn-store-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #ecfdf5;
    color: #059669;
    font-size: 12px;
    font-weight: 700;
    border-radius: 20px;
    margin-bottom: 12px;
}
.sn-store-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 8px;
}
.sn-store-info-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 14px;
    color: #4b5563;
}
.sn-store-info-row i {
    color: #f59e0b;
    margin-top: 3px;
    font-size: 16px;
    width: 20px;
    text-align: center;
}
.sn-store-hours {
    background: #f9fafb;
    border-radius: 12px;
    padding: 14px;
    margin: 16px 0;
    font-size: 13px;
}
.sn-store-hours-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    color: #374151;
}
.sn-store-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
    flex-wrap: wrap;
}
.sn-btn-primary {
    background: #111827;
    color: #ffffff !important;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}
.sn-btn-primary:hover {
    background: #374151;
}
.sn-btn-secondary {
    background: #f3f4f6;
    color: #1f2937 !important;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s;
}
.sn-btn-secondary:hover {
    background: #e5e7eb;
}
.sn-perks-section {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    border-radius: 16px;
    padding: 32px 24px;
    margin-bottom: 40px;
}
.sn-perks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    text-align: center;
}
.sn-perk-item i {
    font-size: 28px;
    color: #f59e0b;
    margin-bottom: 10px;
}
.sn-perk-item h4 {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 4px;
    color: #111827;
}
.sn-perk-item p {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
}
.sn-cat-section-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 16px;
}
.sn-cat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
}
.sn-cat-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    text-decoration: none !important;
    color: #1f2937 !important;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 80px;
}
.sn-cat-card:hover {
    border-color: #f59e0b;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
    color: #d97706 !important;
}
@media (max-width: 640px) {
    .sn-stores-hero h1 { font-size: 22px; }
    .sn-stores-grid { grid-template-columns: 1fr; }
    .sn-store-actions { flex-direction: column; }
    .sn-btn-primary, .sn-btn-secondary { width: 100%; justify-content: center; }
}
</style>

<div class="sn-stores-wrapper">
    <!-- Breadcrumbs -->
    <nav class="sn-breadcrumbs" style="margin-bottom: 16px;" aria-label="Breadcrumb">
        <a href="<?php echo BASE_URL; ?>">Home</a>
        <span class="sn-crumb-sep">›</span>
        <span class="sn-crumb-current">Stores & Outlets</span>
    </nav>

    <!-- Header -->
    <div class="sn-stores-hero">
        <h1>Our Stores & Pickup Outlets</h1>
        <p>Visit our flagship store and pickup locations for instant order collection, customer service, and smooth exchanges.</p>
    </div>

    <!-- Stores Grid -->
    <div class="sn-stores-grid">
        <!-- Main Flagship Store -->
        <div class="sn-store-card">
            <div>
                <span class="sn-store-badge"><i class="fas fa-check-circle"></i> Flagship Store & Pickup Point</span>
                <div class="sn-store-title"><?php echo htmlspecialchars($store_name); ?> Main Outlet</div>
                
                <div class="sn-store-info-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Address:</strong><br>
                        <?php echo nl2br(htmlspecialchars($store_address)); ?>
                    </div>
                </div>

                <div class="sn-store-info-row">
                    <i class="fas fa-phone-alt"></i>
                    <div>
                        <strong>Hotline:</strong><br>
                        <a href="tel:<?php echo htmlspecialchars($clean_phone); ?>" style="color: inherit; text-decoration: underline;"><?php echo htmlspecialchars($store_phone); ?></a>
                    </div>
                </div>

                <div class="sn-store-info-row">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>Email Support:</strong><br>
                        <a href="mailto:<?php echo htmlspecialchars($store_email); ?>" style="color: inherit; text-decoration: underline;"><?php echo htmlspecialchars($store_email); ?></a>
                    </div>
                </div>

                <div class="sn-store-hours">
                    <div style="font-weight: 700; color: #111827; margin-bottom: 6px;"><i class="far fa-clock"></i> Business Hours</div>
                    <div class="sn-store-hours-row">
                        <span>Saturday – Thursday:</span>
                        <strong>09:00 AM – 10:00 PM</strong>
                    </div>
                    <div class="sn-store-hours-row">
                        <span>Friday:</span>
                        <strong>02:00 PM – 10:00 PM</strong>
                    </div>
                </div>
            </div>

            <div class="sn-store-actions">
                <a href="tel:<?php echo htmlspecialchars($clean_phone); ?>" class="sn-btn-primary">
                    <i class="fas fa-phone-alt"></i> Call Store
                </a>
                <a href="https://maps.google.com/?q=<?php echo urlencode($store_address); ?>" target="_blank" rel="noopener" class="sn-btn-secondary">
                    <i class="fas fa-directions"></i> Get Directions
                </a>
                <a href="<?php echo BASE_URL; ?>contact.php" class="sn-btn-secondary">
                    <i class="fas fa-paper-plane"></i> Contact Us
                </a>
            </div>
        </div>

        <!-- Express Collection & Partner Hub -->
        <div class="sn-store-card">
            <div>
                <span class="sn-store-badge" style="background: #eff6ff; color: #2563eb;"><i class="fas fa-box-open"></i> Express Click & Collect Hub</span>
                <div class="sn-store-title"><?php echo htmlspecialchars($store_name); ?> Express Pickup</div>
                
                <div class="sn-store-info-row">
                    <i class="fas fa-shipping-fast"></i>
                    <div>
                        <strong>Free In-Store Collection:</strong><br>
                        Order online and pick up your items at our counter with zero shipping fee.
                    </div>
                </div>

                <div class="sn-store-info-row">
                    <i class="fas fa-sync-alt"></i>
                    <div>
                        <strong>Instant Returns & Sizing Exchanges:</strong><br>
                        Bring any online order within 7 days for fast in-person exchange or inspection.
                    </div>
                </div>

                <div class="sn-store-info-row">
                    <i class="fas fa-headset"></i>
                    <div>
                        <strong>Live Customer Desk:</strong><br>
                        Our team is available at the counter to help you set up or test any product.
                    </div>
                </div>

                <div class="sn-store-hours">
                    <div style="font-weight: 700; color: #111827; margin-bottom: 6px;"><i class="fas fa-shield-alt"></i> In-Store Guarantee</div>
                    <div style="color: #4b5563; font-size: 13px;">
                        Check your products on the spot prior to completing handover.
                    </div>
                </div>
            </div>

            <div class="sn-store-actions">
                <a href="<?php echo BASE_URL; ?>categories.php" class="sn-btn-primary">
                    <i class="fas fa-shopping-bag"></i> Browse Catalog
                </a>
                <a href="<?php echo BASE_URL; ?>deals.php" class="sn-btn-secondary">
                    <i class="fas fa-bolt"></i> View Deals
                </a>
            </div>
        </div>
    </div>

    <!-- Perks Row -->
    <div class="sn-perks-section">
        <div class="sn-perks-grid">
            <div class="sn-perk-item">
                <i class="fas fa-truck-moving"></i>
                <h4>Fast Delivery</h4>
                <p>Nationwide coverage across Bangladesh with reliable shipping partners.</p>
            </div>
            <div class="sn-perk-item">
                <i class="fas fa-undo-alt"></i>
                <h4>7 Days Easy Return</h4>
                <p>Hassle-free replacement guarantee if anything doesn't match.</p>
            </div>
            <div class="sn-perk-item">
                <i class="fas fa-shield-check"></i>
                <h4>100% Genuine</h4>
                <p>Direct authentic sourcing with complete quality guarantee.</p>
            </div>
            <div class="sn-perk-item">
                <i class="fas fa-comments"></i>
                <h4>24/7 Support</h4>
                <p>Live AI Copilot & agent assistance ready anytime.</p>
            </div>
        </div>
    </div>

    <!-- Category Browser -->
    <?php if (!empty($top_cats)): ?>
        <h3 class="sn-cat-section-title">Explore Categories</h3>
        <div class="sn-cat-grid">
            <?php foreach ($top_cats as $tc): ?>
                <a href="<?php echo BASE_URL; ?>product-category.php?id=<?php echo (int)$tc['tcat_id']; ?>&type=top-category" class="sn-cat-card">
                    <i class="fas fa-folder-open" style="font-size: 20px; color: #f59e0b; margin-bottom: 8px;"></i>
                    <span><?php echo htmlspecialchars($tc['tcat_name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once('footer.php'); ?>
