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

</div><!-- /#sn-page-container -->
</div><!-- /.content-wrapper-main -->

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
                    <span><?php echo htmlspecialchars($store_name ?? (defined('STORE_NAME') ? STORE_NAME : 'Store')); ?></span>
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
                    <li><a href="<?php echo BASE_URL; ?>categories.php">All Categories</a></li>
                    <li><a href="<?php echo BASE_URL; ?>deals.php">Deals</a></li>
                    <li><a href="<?php echo BASE_URL; ?>product-category.php?id=1&type=top-category">New Arrivals</a></li>
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
                    <li><a href="<?php echo BASE_URL; ?>customer-returns.php">Return & Refund</a></li>
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
            <p class="sn-footer-copyright">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($store_name ?? (defined('STORE_NAME') ? STORE_NAME : 'Store')); ?>. All rights reserved.</p>
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

<?php
$chat_floating_icon_on_off = isset($settings['chat_floating_icon_on_off']) ? (int)$settings['chat_floating_icon_on_off'] : 1;
$cur_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>

<?php if ($chat_floating_icon_on_off == 1 && $cur_script !== 'messages.php'): ?>
<!-- ========================================================
     PC / DESKTOP FLOATING LIVE CHAT & AI COPILOT WIDGET
     ======================================================== -->
<style>
@media (max-width: 768px) {
    #snDesktopChatTrigger,
    #snDesktopChatWindow {
        display: none !important;
    }
}
#snDesktopChatTrigger {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 999998;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: linear-gradient(135deg, #111827 0%, #1F2937 100%);
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: 2px solid rgba(245, 158, 11, 0.5);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
#snDesktopChatTrigger:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 16px 30px -4px rgba(0, 0, 0, 0.35);
    border-color: #F59E0B;
}
#snDesktopChatWindow {
    position: fixed;
    bottom: 94px;
    right: 24px;
    width: 390px;
    height: 600px;
    max-height: calc(100vh - 120px);
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.08);
    z-index: 999999;
    display: none;
    flex-direction: column;
    overflow: hidden;
    transition: all 0.25s ease-out;
}
#snDesktopChatWindow.active {
    display: flex;
    animation: snChatSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes snChatSlideIn {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>

<!-- Floating Button on Desktop -->
<div id="snDesktopChatTrigger" onclick="toggleSnDesktopChat()" title="Chat with AI Copilot & Live Support">
    <div style="position: relative; display: flex; align-items: center; justify-content: center;">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <span style="position: absolute; top: -3px; right: -3px; width: 10px; height: 10px; background-color: #10B981; border: 2px solid #111827; border-radius: 50%;"></span>
    </div>
</div>

<!-- Floating Chat Drawer Window -->
<div id="snDesktopChatWindow">
    <!-- Window Bar -->
    <div style="background: #111827; color: #ffffff; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1);">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10B981;"></span>
            <strong style="font-size: 13px; font-weight: 700; color: #F9FAFB;">Shop Assistant & Live Chat</strong>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <a href="messages.php" target="_blank" title="Open Fullscreen" style="color: #9CA3AF; text-decoration: none; padding: 4px; display: flex; align-items: center;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#9CA3AF'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <polyline points="9 21 3 21 3 15"></polyline>
                    <line x1="21" y1="3" x2="14" y2="10"></line>
                    <line x1="3" y1="21" x2="10" y2="14"></line>
                </svg>
            </a>
            <button onclick="toggleSnDesktopChat()" style="background: none; border: none; color: #9CA3AF; cursor: pointer; padding: 4px; display: flex; align-items: center;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#9CA3AF'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>
    <!-- Embedded Live Chat View -->
    <iframe id="snChatIframe" src="about:blank" style="width: 100%; height: 100%; border: none; flex: 1;" allow="camera; microphone; autoplay; display-capture"></iframe>
</div>

<script>
function toggleSnDesktopChat() {
    const win = document.getElementById('snDesktopChatWindow');
    const iframe = document.getElementById('snChatIframe');
    if (!win) return;
    
    if (win.classList.contains('active')) {
        win.classList.remove('active');
    } else {
        if (iframe.getAttribute('src') === 'about:blank') {
            iframe.setAttribute('src', 'messages.php?embed=1');
        }
        win.classList.add('active');
    }
}
</script>
<?php endif; ?>

<script src="assets/js/jquery-2.2.4.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="assets/js/jquery.magnific-popup.min.js"></script>
<script src="assets/js/owl.carousel.min.js"></script>
<script src="assets/js/jquery.bxslider.min.js"></script>
<script src="assets/js/bootstrap-touch-slider.js"></script>
<script src="assets/js/rating.js"></script>
<script src="assets/js/select2.full.min.js"></script>
<script src="assets/js/custom.js"></script>
<script src="assets/js/spa-navigation.js?v=<?php echo time(); ?>"></script>
<script>
window.SHOP_BASE_URL = '<?php echo BASE_URL; ?>';
window.SHOP_FIREBASE_CONFIG = {
    apiKey: <?php echo json_encode($settings['firebase_api_key'] ?? ''); ?>,
    authDomain: <?php echo json_encode($settings['firebase_auth_domain'] ?? ''); ?>,
    projectId: <?php echo json_encode($settings['firebase_project_id'] ?? ''); ?>,
    storageBucket: <?php echo json_encode($settings['firebase_storage_bucket'] ?? ''); ?>,
    messagingSenderId: <?php echo json_encode($settings['firebase_messaging_sender_id'] ?? ''); ?>,
    appId: <?php echo json_encode($settings['firebase_app_id'] ?? ''); ?>,
    vapidKey: <?php echo json_encode($settings['firebase_vapid_key'] ?? ''); ?>
};
</script>
<script src="assets/js/firebase-notifications.js?v=<?php echo file_exists(__DIR__ . '/assets/js/firebase-notifications.js') ? filemtime(__DIR__ . '/assets/js/firebase-notifications.js') : time(); ?>"></script>

<?php echo $before_body; ?>
</body>
</html>