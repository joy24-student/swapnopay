<?php
if (empty($settings)) {
    $statement = $pdo->query("SELECT * FROM tbl_settings WHERE id=1");
    $settings = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
}
$footer_about = $settings['footer_about'] ?? '';
$contact_email = $settings['contact_email'] ?? '';
$contact_phone = $settings['contact_phone'] ?? '';
$contact_address = $settings['contact_address'] ?? '';
$footer_copyright = $settings['footer_copyright'] ?? '';
$total_recent_post_footer = $settings['total_recent_post_footer'] ?? 0;
$total_popular_post_footer = $settings['total_popular_post_footer'] ?? 0;
$newsletter_on_off = $settings['newsletter_on_off'] ?? 0;
$before_body = $settings['before_body'] ?? '';
$stripe_public_key = $settings['stripe_public_key'] ?? '';
$stripe_secret_key = $settings['stripe_secret_key'] ?? '';

// Newsletter subscription handling
if (isset($_POST['form_subscribe']) && !empty($_POST['email_subscribe'])) {
    $sub_email = trim($_POST['email_subscribe']);
    if (filter_var($sub_email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt_check = $pdo->prepare("SELECT subs_id FROM tbl_subscriber WHERE subs_email = ?");
            $stmt_check->execute([$sub_email]);
            if ($stmt_check->rowCount() == 0) {
                $key = md5(uniqid(rand(), true));
                $stmt_ins = $pdo->prepare("INSERT INTO tbl_subscriber (subs_email, subs_date, subs_date_time, subs_hash, subs_active) VALUES (?, ?, ?, ?, ?)");
                $stmt_ins->execute([$sub_email, date('Y-m-d'), date('Y-m-d H:i:s'), $key, 1]);
                echo "<script>alert('Thank you for subscribing to our newsletter!');</script>";
            } else {
                echo "<script>alert('This email is already subscribed.');</script>";
            }
        } catch (Exception $e) {
            // Ignore DB errors on newsletter
        }
    }
}

// Mobile footer visibility from settings (Default 0 = hidden on mobile)
$mobile_footer_on_off = isset($settings['mobile_footer_on_off']) ? (int)$settings['mobile_footer_on_off'] : 0;
?>

<?php if ($mobile_footer_on_off == 0): ?>
<style>
@media (max-width: 768px) {
    .sn-footer-wrap,
    .sn-footer-wrap.sn-mobile-footer-hidden,
    footer.sn-footer-wrap {
        display: none !important;
    }
}
</style>
<?php endif; ?>

<footer class="sn-footer-wrap <?php echo ($mobile_footer_on_off == 0 ? 'sn-mobile-footer-hidden' : ''); ?>">
    <div class="sn-container">
        <div class="sn-footer-top">
            <!-- Brand & Socials -->
            <div class="sn-footer-brand">
                <a href="<?php echo BASE_URL; ?>" class="sn-brand-logo">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 2L3 6V20C3 20.5304 3.21071 21.0391 3.58579 21.4142C3.96086 21.7893 4.46957 22 5 22H19C19.5304 22 20.0391 21.7893 20.4142 21.4142C20.7893 21.0391 21 20.5304 21 20V6L18 2H6Z" fill="#F59E0B" stroke="#111827" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M3 6H21" stroke="#111827" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 10C16 11.0609 15.5786 12.0783 14.8284 12.8284C14.0783 13.5786 13.0609 14 12 14C10.9391 14 9.92172 13.5786 9.17157 12.8284C8.42143 12.0783 8 11.0609 8 10" stroke="#111827" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Shop<span class="sn-logo-text-next">Next</span></span>
                </a>
                <h4>Your One-Stop Shop</h4>
                <p>We bring you the best products from trusted brands, with a focus on quality, affordability and customer satisfaction.</p>
                <div class="sn-footer-socials">
                    <a href="https://facebook.com" class="sn-social-link" target="_blank" rel="noopener" aria-label="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://youtube.com" class="sn-social-link" target="_blank" rel="noopener" aria-label="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <a href="https://twitter.com" class="sn-social-link" target="_blank" rel="noopener" aria-label="X">
                        <i class="fab fa-twitter"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="sn-footer-col">
                <h5>Quick Links</h5>
                <ul class="sn-footer-links">
                    <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>product-category.php?id=1&type=top-category">All Categories</a></li>
                    <li><a href="<?php echo BASE_URL; ?>product-category.php?id=1&type=top-category">Deals</a></li>
                    <li><a href="<?php echo BASE_URL; ?>product-category.php?id=2&type=top-category">New Arrivals</a></li>
                    <li><a href="<?php echo BASE_URL; ?>about.php">About Us</a></li>
                    <li><a href="<?php echo BASE_URL; ?>contact.php">Contact</a></li>
                </ul>
            </div>

            <!-- Customer Service -->
            <div class="sn-footer-col">
                <h5>Customer Service</h5>
                <ul class="sn-footer-links">
                    <li><a href="<?php echo BASE_URL; ?>faq.php">Help Center</a></li>
                    <li><a href="<?php echo BASE_URL; ?>contact.php">Shipping Information</a></li>
                    <li><a href="<?php echo BASE_URL; ?>contact.php">Return & Refund</a></li>
                    <li><a href="<?php echo BASE_URL; ?>contact.php">Terms & Conditions</a></li>
                    <li><a href="<?php echo BASE_URL; ?>contact.php">Privacy Policy</a></li>
                </ul>
            </div>

            <!-- Newsletter -->
            <div class="sn-footer-col">
                <h5>Subscribe to Our Newsletter</h5>
                <p class="sn-newsletter-desc">Get the latest updates, deals and exclusive promotions.</p>
                <form action="" method="post" class="sn-newsletter-form">
                    <?php if (isset($csrf)) $csrf->echoInputField(); ?>
                    <input type="email" name="email_subscribe" class="sn-newsletter-input" placeholder="Enter your email address" required>
                    <button type="submit" name="form_subscribe" class="sn-newsletter-btn" aria-label="Subscribe">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="sn-footer-bottom">
            <p class="sn-footer-copyright">© <?php echo date('Y'); ?> ShopNext. All rights reserved.</p>
            <div class="sn-payment-icons">
                <span style="font-weight:700; font-size:12px; color:#111827; margin-right:8px;">VISA</span>
                <span style="font-weight:700; font-size:12px; color:#EA580C; margin-right:8px;">Mastercard</span>
                <span style="font-weight:700; font-size:12px; color:#2563EB; margin-right:8px;">AMEX</span>
                <span style="font-weight:800; font-size:12px; color:#E11D48; margin-right:8px;">bKash</span>
                <span style="font-weight:800; font-size:12px; color:#D97706;">Nagad</span>
            </div>
        </div>
    </div>
</footer>

<script src="assets/js/jquery-2.2.4.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/js/custom.js"></script>

<?php echo $before_body; ?>
</body>
</html>