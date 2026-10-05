<?php
$_SERVER['HTTP_HOST'] = 'shop.swapnopay.top';
$_SERVER['REQUEST_URI'] = '/self-hosted-supabase-store-0558/admin/settings.php';
require_once __DIR__ . '/inc/config.php';

echo "=== TESTING ALL SETTINGS SECTIONS & DATABASE INTEGRITY ===\n";

// 1. Check if tbl_settings exists and has id=1
$s = $pdo->query("SELECT * FROM tbl_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
if (!$s) {
    echo "ERROR: tbl_settings has no row with id=1!\n";
} else {
    echo "OK: tbl_settings row 1 found. Existing columns: " . count($s) . "\n";
}

// 2. Check if tbl_page exists and has id=1
$p = $pdo->query("SELECT * FROM tbl_page WHERE id=1")->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    echo "WARNING: tbl_page has no row with id=1!\n";
} else {
    echo "OK: tbl_page row 1 found.\n";
}

// 3. Check tbl_language
$lCount = (int)$pdo->query("SELECT COUNT(*) FROM tbl_language")->fetchColumn();
echo "OK: tbl_language rows: {$lCount}\n";

// List of all UPDATE statements from settings.php to test inside a rolled-back transaction
$queries = [
    'General Settings' => [
        "sql" => "UPDATE tbl_settings SET logo=?, favicon=?, contact_email=?, contact_phone=?, meta_title_home=?, meta_keyword_home=?, meta_description_home=?, before_head=?, after_body=?, before_body=?, hide_banner_desktop=?, hide_banner_mobile=?, hide_free_delivery_desktop=?, hide_free_delivery_mobile=? WHERE id=1",
        "params" => ['', '', 'test@example.com', '123456', 'Title', 'Keyword', 'Desc', '', '', '', 0, 0, 0, 0]
    ],
    'Popup/Ads Settings' => [
        "sql" => "UPDATE tbl_settings SET popup_on_off=?, popup_photo=?, popup_link=?, popup_title=?, popup_text=?, popup_btn_text=?, popup_animation=?, popup_countdown_on_off=?, popup_countdown_end=?, popup_delay=?, popup_show_again=? WHERE id=1",
        "params" => [0, '', '', 'Title', 'Text', 'Claim Now', 'spin-zoom', 1, '', 2, 'session']
    ],
    'Home Features' => [
        "sql" => "UPDATE tbl_settings SET home_slider_on_off = ?, hero_slider_autoplay = ?, hero_slider_interval = ?, hero_tag = ?, hero_title = ?, hero_subtitle = ?, hero_btn_text = ?, hero_btn_url = ?, hero_btn2_text = ?, hero_btn2_url = ?, hero_badge1_text = ?, hero_badge2_text = ?, home_category_on_off = ?, categories_title = ?, categories_subtitle = ?, home_welcome_on_off = ?, promo_banner1_tag = ?, promo_banner1_title = ?, promo_banner1_subtitle = ?, promo_banner1_btn_text = ?, promo_banner1_btn_url = ?, promo_banner1_image = ?, promo_banner2_tag = ?, promo_banner2_title = ?, promo_banner2_subtitle = ?, promo_banner2_btn_text = ?, promo_banner2_btn_url = ?, promo_banner2_image = ?, home_featured_product_on_off = ?, featured_products_title = ?, featured_products_subtitle = ?, total_featured_product_home = ?, home_service_on_off = ?, trust_item1_title = ?, trust_item1_desc = ?, trust_item2_title = ?, trust_item2_desc = ?, trust_item3_title = ?, trust_item3_desc = ?, trust_item4_title = ?, trust_item4_desc = ? WHERE id = 1",
        "params" => [1, 1, 4500, '', 'Title', 'Sub', 'Btn', '#', '', '', '', '', 1, 'Cat', 'Cat Sub', 1, '', '', '', '', '', '', '', '', '', '', '', '', 1, 'Featured', 'Sub', 8, 1, '', '', '', '', '', '', '', '']
    ],
    'Payment Gateways' => [
        "sql" => "UPDATE tbl_settings SET stripe_public_key=?, stripe_secret_key=?, paypal_client_id=?, paypal_secret=?, paypal_sandbox_mode=?, paypal_email=?, sslcz_store_id=?, sslcz_store_pass=?, sslcz_mode=?, cod_enabled=?, payment_methods=?, bank_detail=? WHERE id=1",
        "params" => ['', '', '', '', 0, '', '', '', 'sandbox', 1, 'Cash on Delivery', '']
    ],
    'API Integrations' => [
        "sql" => "UPDATE tbl_settings SET gemini_api_key=?, openrouter_api_key=?, ai_provider=?, ai_pool_strategy=?, openrouter_model=?, chat_whatsapp_url=?, chat_messenger_url=?, chat_floating_icon_on_off=?, chat_call_enabled=?, facebook_app_id=?, facebook_app_secret=?, google_client_id=?, google_client_secret=?, twilio_account_sid=?, twilio_auth_token=?, twilio_phone_number=? WHERE id=1",
        "params" => ['', '', 'auto', 'round_robin', 'openrouter/free', '', '', 1, 1, '', '', '', '', '', '', '']
    ],
    'Review & Delivery' => [
        "sql" => "UPDATE tbl_settings SET review_feature_on_off=?, estimated_delivery_time_local=?, estimated_delivery_time_international=? WHERE id=1",
        "params" => [1, '2-3 days', '7-14 days']
    ],
    'SMS Settings' => [
        "sql" => "UPDATE tbl_settings SET sms_feature_on_off=?, sms_api_key=?, sms_sender_id=? WHERE id=1",
        "params" => [0, '', '']
    ],
    'Social Settings' => [
        "sql" => "UPDATE tbl_settings SET facebook_url=?, twitter_url=?, linkedin_url=?, instagram_url=?, youtube_url=? WHERE id=1",
        "params" => ['', '', '', '', '']
    ],
    'Footer Settings' => [
        "sql" => "UPDATE tbl_settings SET copyright_text=?, footer_about_us=?, contact_address=?, contact_map_iframe=?, payment_verified_image=?, mobile_footer_on_off=?, related_products_on_off=? WHERE id=1",
        "params" => ['Copyright', 'About us', 'Address', '', '', 0, 1]
    ],
    'Footer Sync' => [
        "sql" => "UPDATE tbl_settings SET footer_copyright=copyright_text,footer_about=footer_about_us WHERE id=1",
        "params" => []
    ],
    'Outgoing Email Settings' => [
        "sql" => "UPDATE tbl_settings SET smtp_from_name=?, smtp_from_email=?, email_method=?, smtp_host=?,smtp_port=?,smtp_username=?,smtp_password=?,smtp_encryption=? WHERE id=1",
        "params" => ['Store', 'store@example.com', 'smtp', 'smtp.example.com', '587', 'user', 'pass', 'tls']
    ],
    'Email Content Settings' => [
        "sql" => "UPDATE tbl_settings SET receive_email=?, receive_email_subject=?, receive_email_thank_you_message=?, forget_password_message=? WHERE id=1",
        "params" => ['admin@example.com', 'Subject', 'Thank you', 'Reset link']
    ],
    'Blog / Post Counts' => [
        "sql" => "UPDATE tbl_settings SET total_featured_product_home=?, total_latest_product_home=?, total_popular_product_home=?, total_recent_post_footer=?, total_popular_post_footer=?, total_recent_post_sidebar=?, total_popular_post_sidebar=? WHERE id=1",
        "params" => [8, 8, 8, 4, 4, 4, 4]
    ],
    'Ads Settings' => [
        "sql" => "UPDATE tbl_settings SET ads_above_welcome_on_off=?, ads_above_featured_product_on_off=?, ads_above_latest_product_on_off=?, ads_above_popular_product_on_off=?, ads_above_testimonial_on_off=?, ads_category_sidebar_on_off=? WHERE id=1",
        "params" => [0, 0, 0, 0, 0, 0]
    ],
    'About Page' => [
        "sql" => "UPDATE tbl_page SET about_title=?, about_content=?, about_meta_title=?, about_meta_keyword=?, about_meta_description=? WHERE id=1",
        "params" => ['About Us', 'Content', 'Title', 'Keywords', 'Description']
    ],
    'FAQ Page' => [
        "sql" => "UPDATE tbl_page SET faq_title=?, faq_meta_title=?, faq_meta_keyword=?, faq_meta_description=? WHERE id=1",
        "params" => ['FAQ', 'Title', 'Keywords', 'Description']
    ],
    'Contact Page' => [
        "sql" => "UPDATE tbl_page SET contact_title=?, contact_meta_title=?, contact_meta_keyword=?, contact_meta_description=? WHERE id=1",
        "params" => ['Contact', 'Title', 'Keywords', 'Description']
    ]
];

$banner_fields = [
    'banner_cart', 'banner_search', 'banner_registration', 'banner_login',
    'banner_forget_password', 'banner_reset_password', 'banner_product_category',
    'banner_blog', 'banner_faq', 'banner_contact', 'banner_checkout',
    'banner_payment', 'banner_customer_panel', 'banner_about', 'banner_terms',
    'banner_privacy', 'banner_shipping', 'banner_return_policy',
    'banner_photo_gallery', 'banner_team'
];

foreach ($banner_fields as $bf) {
    $queries["Banner: {$bf}"] = [
        "sql" => "UPDATE tbl_settings SET \"{$bf}\" = ? WHERE id=1",
        "params" => ['test.jpg']
    ];
}

$failed = 0;
foreach ($queries as $name => $test) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($test['sql']);
        $stmt->execute($test['params']);
        $pdo->rollBack();
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $failed++;
        echo "[FAIL] {$name}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== AUDIT COMPLETE: " . (count($queries) - $failed) . " passed, {$failed} failed. ===\n";

// List all existing columns in tbl_settings
echo "\n=== EXISTING COLUMNS IN tbl_settings ===\n";
$cols = array_keys($s);
sort($cols);
foreach ($cols as $c) {
    echo " - {$c}\n";
}
