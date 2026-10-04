<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once __DIR__ . '/inc/supabase_storage.php'; ?>
<?php require_once('header.php'); ?>

<?php
// Handle Slide Deletion from settings tab
if (isset($_GET['action']) && $_GET['action'] === 'delete_slide' && !empty($_GET['slide_id'])) {
    $deleteId = (int)$_GET['slide_id'];
    $stmt = $pdo->prepare("DELETE FROM tbl_slider WHERE id = ?");
    $stmt->execute([$deleteId]);
    header("Location: settings.php#tab_home_features");
    exit;
}

// Ensure required columns exist across all tenant schemas safely
try {
    $pdo->exec("ALTER TABLE tbl_slider ADD COLUMN IF NOT EXISTS slide_order integer DEFAULT 1");
    $pdo->exec("ALTER TABLE tbl_slider ADD COLUMN IF NOT EXISTS is_active smallint DEFAULT 1");
    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS promo_banner1_image text DEFAULT ''");
    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS promo_banner2_image text DEFAULT ''");
    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS promo_banner1_url text DEFAULT ''");
    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS promo_banner2_url text DEFAULT ''");
    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS mobile_footer_on_off smallint DEFAULT 0");
    $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS related_products_on_off smallint DEFAULT 1");
} catch (Throwable $e) {}

// Fetch all settings data from the database
$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$settings_data = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

// Fetch Page Settings (About Us, FAQ, Contact)
try {
    $statement = $pdo->prepare("SELECT * FROM tbl_page WHERE id=1");
    $statement->execute();
    $page_data = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $page_data = [];
}
$about_title = $page_data['about_title'] ?? '';
$about_content = $page_data['about_content'] ?? '';
$about_banner = $page_data['about_banner'] ?? '';
$about_meta_title = $page_data['about_meta_title'] ?? '';
$about_meta_keyword = $page_data['about_meta_keyword'] ?? '';
$about_meta_description = $page_data['about_meta_description'] ?? '';

$faq_title = $page_data['faq_title'] ?? '';
$faq_banner = $page_data['faq_banner'] ?? '';
$faq_meta_title = $page_data['faq_meta_title'] ?? '';
$faq_meta_keyword = $page_data['faq_meta_keyword'] ?? '';
$faq_meta_description = $page_data['faq_meta_description'] ?? '';

$contact_title = $page_data['contact_title'] ?? '';
$contact_banner = $page_data['contact_banner'] ?? '';
$contact_meta_title = $page_data['contact_meta_title'] ?? '';
$contact_meta_keyword = $page_data['contact_meta_keyword'] ?? '';
$contact_meta_description = $page_data['contact_meta_description'] ?? '';

// Fetch Language Data
$lang_ids = [];
try {
    $lang_statement = $pdo->prepare("SELECT * FROM tbl_language ORDER BY lang_id ASC");
    $lang_statement->execute();
    $lang_rows = $lang_statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($lang_rows as $row) {
        $lang_ids[(int)$row['lang_id']] = $row['lang_value'];
    }
} catch (Throwable $e) {}


try {
    $slides = $pdo->query("SELECT * FROM tbl_slider ORDER BY slide_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    try {
        $slides = $pdo->query("SELECT * FROM tbl_slider ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e2) {
        $slides = [];
    }
}
// Assign variables for current values, using null coalescing operator for safety
// General Settings
$logo = $settings_data['logo'] ?? '';
$favicon = $settings_data['favicon'] ?? '';
$contact_email = $settings_data['contact_email'] ?? '';
$contact_phone = $settings_data['contact_phone'] ?? '';
$meta_title_home = $settings_data['meta_title_home'] ?? '';
$meta_keyword_home = $settings_data['meta_keyword_home'] ?? '';
$meta_description_home = $settings_data['meta_description_home'] ?? '';
$before_head = $settings_data['before_head'] ?? '';
$after_body = $settings_data['after_body'] ?? '';
$before_body = $settings_data['before_body'] ?? ''; // From user's provided settings.php
$estimated_delivery_time_local = $settings_data['estimated_delivery_time_local'] ?? '3-5 business days';
$estimated_delivery_time_international = $settings_data['estimated_delivery_time_international'] ?? '10-20 business days';

// Home Page Features
$cta_title = $settings_data['cta_title'] ?? '';
$cta_content = $settings_data['cta_content'] ?? '';
$cta_read_more_text = $settings_data['cta_read_more_text'] ?? '';
$cta_read_more_url = $settings_data['cta_read_more_url'] ?? '';
$cta_photo = $settings_data['cta_photo'] ?? '';
$featured_product_title = $settings_data['featured_product_title'] ?? '';
$featured_product_subtitle = $settings_data['featured_product_subtitle'] ?? '';
$latest_product_title = $settings_data['latest_product_title'] ?? '';
$latest_product_subtitle = $settings_data['latest_product_subtitle'] ?? '';
$popular_product_title = $settings_data['popular_product_title'] ?? '';
$popular_product_subtitle = $settings_data['popular_product_subtitle'] ?? '';
$testimonial_title = $settings_data['testimonial_title'] ?? '';
$testimonial_subtitle = $settings_data['testimonial_subtitle'] ?? '';
$testimonial_photo = $settings_data['testimonial_photo'] ?? '';
$blog_title = $settings_data['blog_title'] ?? '';
$blog_subtitle = $settings_data['blog_subtitle'] ?? '';
$newsletter_text = $settings_data['newsletter_text'] ?? '';

$total_featured_product_home = $settings_data['total_featured_product_home'] ?? 0;
$total_latest_product_home = $settings_data['total_latest_product_home'] ?? 0;
$total_popular_product_home = $settings_data['total_popular_product_home'] ?? 0;

// Flash sale end time (admin-configurable)
$flash_sale_end_time = $settings_data['flash_sale_end_time'] ?? '';

$home_service_on_off = $settings_data['home_service_on_off'] ?? 0;
$home_welcome_on_off = $settings_data['home_welcome_on_off'] ?? 0;
$home_featured_product_on_off = $settings_data['home_featured_product_on_off'] ?? 0;
$home_latest_product_on_off = $settings_data['home_latest_product_on_off'] ?? 0;
$home_popular_product_on_off = $settings_data['home_popular_product_on_off'] ?? 0;
$home_testimonial_on_off = $settings_data['home_testimonial_on_off'] ?? 0;
$home_blog_on_off = $settings_data['home_blog_on_off'] ?? 0;
$home_map_on_off = $settings_data['home_map_on_off'] ?? 0;
$home_newsletter_on_off = $settings_data['home_newsletter_on_off'] ?? 0;
$home_brand_on_off = $settings_data['home_brand_on_off'] ?? 0; // Assuming this exists or will be added
// --- Variable Declarations for Popup ---
$popup_on_off = $settings_data['popup_on_off'] ?? 0;
$popup_text   = $settings_data['popup_text'] ?? '';
$popup_link   = $settings_data['popup_link'] ?? '';
$popup_photo  = $settings_data['popup_photo'] ?? '';
// Email Settings - Reverted to original names based on user feedback
$smtp_from_name = $settings_data['smtp_from_name'] ?? ''; // Reverted to original
$smtp_from_email = $settings_data['smtp_from_email'] ?? ''; // Reverted to original
$email_method = $settings_data['email_method'] ?? 'PHP Mail';
$smtp_host = $settings_data['smtp_host'] ?? '';
$smtp_port = $settings_data['smtp_port'] ?? '';
$smtp_username = $settings_data['smtp_username'] ?? '';
$smtp_password = $settings_data['smtp_password'] ?? '';
$smtp_encryption = $settings_data['smtp_encryption'] ?? 'none'; // Confirmed consistency
$receive_email = $settings_data['receive_email'] ?? ''; // From user's provided settings.php
$receive_email_subject = $settings_data['receive_email_subject'] ?? ''; // From user's provided settings.php
$receive_email_thank_you_message = $settings_data['receive_email_thank_you_message'] ?? ''; // From user's provided settings.php
$forget_password_message = $settings_data['forget_password_message'] ?? ''; // From user's provided settings.php
    $multi_vendor_on_off = $settings_data['multi_vendor_on_off'] ?? 0;

$coin_payment_on_off = $settings_data['coin_payment_on_off'] ?? 0;

        $desktop_advanced_layout_on_off = $settings_data['desktop_advanced_layout_on_off'] ?? 0;



// Payment Gateways
$stripe_public_key = $settings_data['stripe_public_key'] ?? '';
$stripe_secret_key = $settings_data['stripe_secret_key'] ?? '';
$paypal_client_id = $settings_data['paypal_client_id'] ?? '';
$paypal_secret = $settings_data['paypal_secret'] ?? '';
$paypal_sandbox_mode = $settings_data['paypal_sandbox_mode'] ?? 0;
$paypal_email = $settings_data['paypal_email'] ?? ''; // From user's provided settings.php
$bank_detail = $settings_data['bank_detail'] ?? ''; // From user's provided settings.php
// Corrected variable names for SSLCommerz data retrieval
$sslcz_store_id = $settings_data['sslcz_store_id'] ?? '';
$sslcz_store_pass = $settings_data['sslcz_store_pass'] ?? '';
$sslcz_mode = $settings_data['sslcz_mode'] ?? 'sandbox'; // Corrected to sandbox_mode
$cod_enabled = $settings_data['cod_enabled'] ?? 1;
$payment_methods = $settings_data['payment_methods'] ?? '';
$enabled_payment_methods_array = explode(',', $payment_methods);







// API Integrations
$gemini_api_key = $settings_data['gemini_api_key'] ?? '';
$facebook_app_id = $settings_data['facebook_app_id'] ?? '';
$facebook_app_secret = $settings_data['facebook_app_secret'] ?? '';
$google_client_id = $settings_data['google_client_id'] ?? '';
$google_client_secret = $settings_data['google_client_secret'] ?? '';
// Assuming Twilio API key and secret will also be added if needed
$twilio_account_sid = $settings_data['twilio_account_sid'] ?? '';
$twilio_auth_token = $settings_data['twilio_auth_token'] ?? '';
$twilio_phone_number = $settings_data['twilio_phone_number'] ?? '';






// Review Settings
$review_feature_on_off = $settings_data['review_feature_on_off'] ?? 1;

// SMS Settings
$sms_api_key = $settings_data['sms_api_key'] ?? '';
$sms_sender_id = $settings_data['sms_sender_id'] ?? '';
$sms_feature_on_off = $settings_data['sms_feature_on_off'] ?? 0;





// Banner Settings
$banner_cart = $settings_data['banner_cart'] ?? '';
$banner_search = $settings_data['banner_search'] ?? '';
$banner_registration = $settings_data['banner_registration'] ?? '';
$banner_login = $settings_data['banner_login'] ?? '';
$banner_forget_password = $settings_data['banner_forget_password'] ?? '';
$banner_reset_password = $settings_data['banner_reset_password'] ?? '';
$banner_product_category = $settings_data['banner_product_category'] ?? '';
$banner_blog = $settings_data['banner_blog'] ?? '';
$banner_faq = $settings_data['banner_faq'] ?? '';
$banner_contact = $settings_data['banner_contact'] ?? '';
$banner_checkout = $settings_data['banner_checkout'] ?? '';
$banner_payment = $settings_data['banner_payment'] ?? '';
$banner_customer_panel = $settings_data['banner_customer_panel'] ?? '';
$banner_about = $settings_data['banner_about'] ?? '';
$banner_terms = $settings_data['banner_terms'] ?? '';
$banner_privacy = $settings_data['banner_privacy'] ?? '';
$banner_shipping = $settings_data['banner_shipping'] ?? '';
$banner_return_policy = $settings_data['banner_return_policy'] ?? '';
$banner_photo_gallery = $settings_data['banner_photo_gallery'] ?? '';
$banner_team = $settings_data['banner_team'] ?? '';







// Social Media Settings
$facebook_url = $settings_data['facebook_url'] ?? '';
$twitter_url = $settings_data['twitter_url'] ?? '';
$linkedin_url = $settings_data['linkedin_url'] ?? '';
$instagram_url = $settings_data['instagram_url'] ?? '';
$youtube_url = $settings_data['youtube_url'] ?? '';







// Footer Settings
$copyright_text = $settings_data['copyright_text'] ?? '';
$footer_about_us = $settings_data['footer_about_us'] ?? '';
$contact_address = $settings_data['contact_address'] ?? ''; // From user's provided settings.php
$contact_map_iframe = $settings_data['contact_map_iframe'] ?? ''; // From user's provided settings.php
$payment_verified_image = $settings_data['payment_verified_image'] ?? ''; // New field
$mobile_footer_on_off = isset($settings_data['mobile_footer_on_off']) ? (int)$settings_data['mobile_footer_on_off'] : 0;
$related_products_on_off = isset($settings_data['related_products_on_off']) ? (int)$settings_data['related_products_on_off'] : 1;






// Ads Settings
$ads_above_welcome_on_off = $settings_data['ads_above_welcome_on_off'] ?? 0;
$ads_above_featured_product_on_off = $settings_data['ads_above_featured_product_on_off'] ?? 0;
$ads_above_latest_product_on_off = $settings_data['ads_above_latest_product_on_off'] ?? 0;
$ads_above_popular_product_on_off = $settings_data['ads_above_popular_product_on_off'] ?? 0;
$ads_above_testimonial_on_off = $settings_data['ads_above_testimonial_on_off'] ?? 0;
$ads_category_sidebar_on_off = $settings_data['ads_category_sidebar_on_off'] ?? 0;






// Blog/Post Counts (from user's provided settings.php)
$total_recent_post_footer = $settings_data['total_recent_post_footer'] ?? 3;
$total_popular_post_footer = $settings_data['total_popular_post_footer'] ?? 3;
$total_recent_post_sidebar = $settings_data['total_recent_post_sidebar'] ?? 3;
$total_popular_post_sidebar = $settings_data['total_popular_post_sidebar'] ?? 3;


// Initialize messages
$error_message = '';
$success_message = '';

// --- Form Submission Handling ---

// Helper function for file uploads
function handle_file_upload($file_input_name, $current_file_name, $upload_dir, $prefix = '') {
    global $error_message;
    $new_file_name = $current_file_name;
    if (!empty($_FILES[$file_input_name]['name'])) {
        $file = $_FILES[$file_input_name];
        $path = $file['name'];
        $path_tmp = $file['tmp_name'] ?? '';
        $error = $file['error'] ?? UPLOAD_ERR_OK;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'png', 'jpeg', 'gif'];

        $phpFileUploadErrors = array(
            UPLOAD_ERR_OK => 'There is no error, the file uploaded with success.',
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        );

        if ($error !== UPLOAD_ERR_OK) {
            error_log("UPLOAD DIAG: Upload error for {$file_input_name}: " . ($phpFileUploadErrors[$error] ?? 'Unknown error code ' . $error));
            $error_message .= 'Failed to upload file for ' . str_replace('_', ' ', $file_input_name) . '.<br>';
            return false;
        }

        if (!in_array($ext, $allowed)) {
            $error_message .= 'You must upload a jpg, jpeg, gif or png file for ' . str_replace('_', ' ', $file_input_name) . '.<br>';
            return false;
        }

        if (!is_dir($upload_dir)) {
            if (!@mkdir($upload_dir, 0755, true)) {
                error_log("UPLOAD DIAG: Failed to create upload directory: {$upload_dir}");
                $error_message .= 'Upload directory does not exist and could not be created.<br>';
                return false;
            }
        }

        if (!is_writable($upload_dir)) {
            error_log("UPLOAD DIAG: Upload directory not writable: {$upload_dir}");
            $error_message .= 'Upload directory is not writable. Check permissions.<br>';
            return false;
        }

        if (empty($path_tmp) || !is_uploaded_file($path_tmp)) {
            error_log("UPLOAD DIAG: is_uploaded_file returned false for {$file_input_name}. tmp_name=" . var_export($path_tmp, true) . " _FILES=" . var_export($file, true));
            $error_message .= 'Possible file upload attack detected for ' . str_replace('_', ' ', $file_input_name) . '.<br>';
            return false;
        }

        // Keep the previous image until settings have been saved successfully.

        try {
            $uniq = bin2hex(random_bytes(5));
        } catch (Exception $e) {
            $uniq = uniqid();
        }
        $new_file_name = $prefix . time() . '-' . $uniq . '.' . $ext;

        if (!move_uploaded_file($path_tmp, $upload_dir . $new_file_name)) {
            error_log("UPLOAD DIAG: move_uploaded_file failed for {$file_input_name}. tmp={$path_tmp} dest={$upload_dir}{$new_file_name}");
            $error_message .= 'Failed to move uploaded file for ' . str_replace('_', ' ', $file_input_name) . '.<br>';
            return false;
        }
    }
    return $new_file_name;
}







try {
if ($_SERVER['REQUEST_METHOD']==='POST') $pdo->beginTransaction();
// General Settings Form
if(isset($_POST['form_general_settings'])) {
    $valid = 1;

    $logo = handle_file_upload('photo_logo', $logo, '../assets/uploads/', 'logo-');
    if ($logo === false) $valid = 0;

    $favicon = handle_file_upload('photo_favicon', $favicon, '../assets/uploads/', 'favicon-');
    if ($favicon === false) $valid = 0;

    if($valid == 1) {
        $statement = $pdo->prepare("UPDATE tbl_settings SET
                                    logo=?, favicon=?, contact_email=?, contact_phone=?,
                                    meta_title_home=?, meta_keyword_home=?, meta_description_home=?,
                                    before_head=?, after_body=?, before_body=?,
                                    hide_banner_desktop=?, hide_banner_mobile=?, hide_free_delivery_desktop=?, hide_free_delivery_mobile=?
                                    WHERE id=1");
        $statement->execute(array(
            $logo,
            $favicon,
            $_POST['contact_email'] ?? '',
            $_POST['contact_phone'] ?? '',
            $_POST['meta_title_home'] ?? '',
            $_POST['meta_keyword_home'] ?? '',
            $_POST['meta_description_home'] ?? '',
            $_POST['before_head'] ?? '',
            $_POST['after_body'] ?? '',
            $_POST['before_body'] ?? '',
            isset($_POST['hide_banner_desktop']) ? 1 : 0,
            isset($_POST['hide_banner_mobile']) ? 1 : 0,
            isset($_POST['hide_free_delivery_desktop']) ? 1 : 0,
            isset($_POST['hide_free_delivery_mobile']) ? 1 : 0
        ));
        $success_message = 'General Settings are updated successfully.';
    }
}

if(isset($_POST['form_popup_settings'])) {
    $popup_on_off = $_POST['popup_on_off'] ?? 0;
    $popup_text   = $_POST['popup_text'] ?? '';
    $popup_link   = $_POST['popup_link'] ?? '';
    $path = $_FILES['popup_photo']['name'] ?? '';
    $path_tmp = $_FILES['popup_photo']['tmp_name'] ?? '';

    if($path != '') {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $file_name = 'popup-'.bin2hex(random_bytes(16)).'.'.$ext;
        move_uploaded_file($path_tmp, '../assets/uploads/'.$file_name);
        $statement = $pdo->prepare("UPDATE tbl_settings SET popup_on_off=?, popup_text=?, popup_link=?, popup_photo=? WHERE id=1");
        $statement->execute(array($popup_on_off, $popup_text, $popup_link, $file_name));
    } else {
        $statement = $pdo->prepare("UPDATE tbl_settings SET popup_on_off=?, popup_text=?, popup_link=? WHERE id=1");
        $statement->execute(array($popup_on_off, $popup_text, $popup_link));
    }
    $success_message = 'Popup settings updated successfully.';
}



// Home Page Features Form
if(isset($_POST['form_home_features'])) {
    // 1. General & Hero Settings
    $hero_slider_autoplay = isset($_POST['hero_slider_autoplay']) ? 1 : 0;
    $hero_slider_interval = !empty($_POST['hero_slider_interval']) ? (int)$_POST['hero_slider_interval'] : 4500;
    $home_slider_on_off   = isset($_POST['home_slider_on_off']) ? (int)$_POST['home_slider_on_off'] : 1;
    $hero_tag             = trim($_POST['hero_tag'] ?? '');
    $hero_title           = trim($_POST['hero_title'] ?? '');
    $hero_subtitle        = trim($_POST['hero_subtitle'] ?? '');
    $hero_btn_text        = trim($_POST['hero_btn_text'] ?? '');
    $hero_btn_url         = trim($_POST['hero_btn_url'] ?? '');
    $hero_btn2_text       = trim($_POST['hero_btn2_text'] ?? '');
    $hero_btn2_url        = trim($_POST['hero_btn2_url'] ?? '');
    $hero_badge1_text     = trim($_POST['hero_badge1_text'] ?? '');
    $hero_badge2_text     = trim($_POST['hero_badge2_text'] ?? '');

    // 2. Categories Section
    $home_category_on_off = isset($_POST['home_category_on_off']) ? (int)$_POST['home_category_on_off'] : 1;
    $categories_title     = trim($_POST['categories_title'] ?? 'Shop by Category');
    $categories_subtitle  = trim($_POST['categories_subtitle'] ?? 'Explore our wide range of popular collections');

    // 3. Dual Promotional Banners
    $home_welcome_on_off  = isset($_POST['home_welcome_on_off']) ? (int)$_POST['home_welcome_on_off'] : 1;
    $promo1_tag           = trim($_POST['promo_banner1_tag'] ?? '');
    $promo1_title         = trim($_POST['promo_banner1_title'] ?? '');
    $promo1_subtitle      = trim($_POST['promo_banner1_subtitle'] ?? '');
    $promo1_btn_text      = trim($_POST['promo_banner1_btn_text'] ?? '');
    $promo1_btn_url       = trim($_POST['promo_banner1_btn_url'] ?? '');

    $promo2_tag           = trim($_POST['promo_banner2_tag'] ?? '');
    $promo2_title         = trim($_POST['promo_banner2_title'] ?? '');
    $promo2_subtitle      = trim($_POST['promo_banner2_subtitle'] ?? '');
    $promo2_btn_text      = trim($_POST['promo_banner2_btn_text'] ?? '');
    $promo2_btn_url       = trim($_POST['promo_banner2_btn_url'] ?? '');

    // 4. Featured Products Section
    $home_featured_product_on_off = isset($_POST['home_featured_product_on_off']) ? (int)$_POST['home_featured_product_on_off'] : 1;
    $featured_products_title      = trim($_POST['featured_products_title'] ?? 'Featured Products');
    $featured_products_subtitle   = trim($_POST['featured_products_subtitle'] ?? 'Handpicked best sellers and top rated products');
    $total_featured_product_home  = !empty($_POST['total_featured_product_home']) ? (int)$_POST['total_featured_product_home'] : 8;

    // 5. Trust Bar
    $home_service_on_off  = isset($_POST['home_service_on_off']) ? (int)$_POST['home_service_on_off'] : 1;
    $trust1_title         = trim($_POST['trust_item1_title'] ?? '');
    $trust1_desc          = trim($_POST['trust_item1_desc'] ?? '');
    $trust2_title         = trim($_POST['trust_item2_title'] ?? '');
    $trust2_desc          = trim($_POST['trust_item2_desc'] ?? '');
    $trust3_title         = trim($_POST['trust_item3_title'] ?? '');
    $trust3_desc          = trim($_POST['trust_item3_desc'] ?? '');
    $trust4_title         = trim($_POST['trust_item4_title'] ?? '');
    $trust4_desc          = trim($_POST['trust_item4_desc'] ?? '');

    // Fetch current image URLs from DB
    $currSettings = $pdo->query("SELECT promo_banner1_image, promo_banner2_image FROM tbl_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $promo1_image = $currSettings['promo_banner1_image'] ?? '';
    $promo2_image = $currSettings['promo_banner2_image'] ?? '';

    // Supabase Upload for Promo Banner 1
    if (!empty($_FILES['promo1_image_file']['tmp_name']) && is_uploaded_file($_FILES['promo1_image_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['promo1_image_file']['name'], PATHINFO_EXTENSION));
        $newPromo1Url = uploadFileToSupabase($_FILES['promo1_image_file']['tmp_name'], 'promo1_' . time() . '.' . $ext);
        if ($newPromo1Url) {
            $promo1_image = $newPromo1Url;
        }
    } elseif (!empty($_POST['promo_banner1_image_url'])) {
        $promo1_image = trim($_POST['promo_banner1_image_url']);
    }

    // Supabase Upload for Promo Banner 2
    if (!empty($_FILES['promo2_image_file']['tmp_name']) && is_uploaded_file($_FILES['promo2_image_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['promo2_image_file']['name'], PATHINFO_EXTENSION));
        $newPromo2Url = uploadFileToSupabase($_FILES['promo2_image_file']['tmp_name'], 'promo2_' . time() . '.' . $ext);
        if ($newPromo2Url) {
            $promo2_image = $newPromo2Url;
        }
    } elseif (!empty($_POST['promo_banner2_image_url'])) {
        $promo2_image = trim($_POST['promo_banner2_image_url']);
    }

    // Update tbl_settings in Supabase
    $updateStmt = $pdo->prepare("UPDATE tbl_settings SET 
        home_slider_on_off = ?, hero_slider_autoplay = ?, hero_slider_interval = ?,
        hero_tag = ?, hero_title = ?, hero_subtitle = ?, hero_btn_text = ?, hero_btn_url = ?, 
        hero_btn2_text = ?, hero_btn2_url = ?, hero_badge1_text = ?, hero_badge2_text = ?,
        home_category_on_off = ?, categories_title = ?, categories_subtitle = ?,
        home_welcome_on_off = ?,
        promo_banner1_tag = ?, promo_banner1_title = ?, promo_banner1_subtitle = ?, promo_banner1_btn_text = ?, promo_banner1_btn_url = ?, promo_banner1_image = ?,
        promo_banner2_tag = ?, promo_banner2_title = ?, promo_banner2_subtitle = ?, promo_banner2_btn_text = ?, promo_banner2_btn_url = ?, promo_banner2_image = ?,
        home_featured_product_on_off = ?, featured_products_title = ?, featured_products_subtitle = ?, total_featured_product_home = ?,
        home_service_on_off = ?,
        trust_item1_title = ?, trust_item1_desc = ?,
        trust_item2_title = ?, trust_item2_desc = ?,
        trust_item3_title = ?, trust_item3_desc = ?,
        trust_item4_title = ?, trust_item4_desc = ?
        WHERE id = 1");

    $updateStmt->execute([
        $home_slider_on_off, $hero_slider_autoplay, $hero_slider_interval,
        $hero_tag, $hero_title, $hero_subtitle, $hero_btn_text, $hero_btn_url,
        $hero_btn2_text, $hero_btn2_url, $hero_badge1_text, $hero_badge2_text,
        $home_category_on_off, $categories_title, $categories_subtitle,
        $home_welcome_on_off,
        $promo1_tag, $promo1_title, $promo1_subtitle, $promo1_btn_text, $promo1_btn_url, $promo1_image,
        $promo2_tag, $promo2_title, $promo2_subtitle, $promo2_btn_text, $promo2_btn_url, $promo2_image,
        $home_featured_product_on_off, $featured_products_title, $featured_products_subtitle, $total_featured_product_home,
        $home_service_on_off,
        $trust1_title, $trust1_desc,
        $trust2_title, $trust2_desc,
        $trust3_title, $trust3_desc,
        $trust4_title, $trust4_desc
    ]);

    // Process Existing Slide Orders & Active Status
    if (!empty($_POST['slide_order']) && is_array($_POST['slide_order'])) {
        foreach ($_POST['slide_order'] as $slideId => $orderVal) {
            $slideId  = (int)$slideId;
            $orderVal = (int)$orderVal;
            $isActive = isset($_POST['slide_active'][$slideId]) ? 1 : 0;
            $pdo->prepare("UPDATE tbl_slider SET slide_order = ?, is_active = ? WHERE id = ?")->execute([$orderVal, $isActive, $slideId]);
        }
    }

    // Process MULTIPLE Image Uploads for Hero Slider to Supabase Bucket
    $uploadedCount = 0;
    if (!empty($_FILES['hero_slider_photos']['name']) && is_array($_FILES['hero_slider_photos']['name'])) {
        $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(slide_order), 0) FROM tbl_slider")->fetchColumn();
        foreach ($_FILES['hero_slider_photos']['name'] as $idx => $fileName) {
            if (!empty($fileName) && !empty($_FILES['hero_slider_photos']['tmp_name'][$idx])) {
                $tmpName = $_FILES['hero_slider_photos']['tmp_name'][$idx];
                $error   = $_FILES['hero_slider_photos']['error'][$idx];
                if ($error === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                        $uniqueRemoteName = 'hero_slide_' . time() . '_' . ($idx + 1) . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        $cloudUrl = uploadFileToSupabase($tmpName, $uniqueRemoteName, 'assets');
                        if ($cloudUrl) {
                            $maxOrder++;
                            $insertStmt = $pdo->prepare("INSERT INTO tbl_slider (photo, heading, content, button_text, button_url, position, slide_order, is_active) VALUES (?, '', '', '', '', 'Center', ?, 1)");
                            $insertStmt->execute([$cloudUrl, $maxOrder]);
                            $uploadedCount++;
                        }
                    }
                }
            }
        }
    }

    // Invalidate settings cache
    @unlink(__DIR__ . '/inc/cache_settings.json');
    
    // Refresh settings data & slides for this page render
    $settings_data = $pdo->query("SELECT * FROM tbl_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    try {
        $slides = $pdo->query("SELECT * FROM tbl_slider ORDER BY slide_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        try {
            $slides = $pdo->query("SELECT * FROM tbl_slider ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e2) {
            $slides = [];
        }
    }
    
    $msgParts = ['Homepage features and customizations updated successfully!'];
    if ($uploadedCount > 0) {
        $msgParts[] = "{$uploadedCount} new hero slide(s) uploaded directly to Supabase Storage.";
    }
    $success_message = implode(' ', $msgParts);
}
// Payment Gateways Form
if(isset($_POST['form_payment_gateways'])) {
    $selected_methods = array_intersect((array)($_POST['payment_methods'] ?? []), ['Cash on Delivery','PayPal','Bank','SSLCommerz','SwapnoPay']);
    if (($_POST['cod_enabled'] ?? 0)==1) $selected_methods[]='Cash on Delivery';
    $payment_methods_selected = implode(',', array_unique($selected_methods));

    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                stripe_public_key=?, stripe_secret_key=?,
                                paypal_client_id=?, paypal_secret=?, paypal_sandbox_mode=?, paypal_email=?,
                                sslcz_store_id=?, sslcz_store_pass=?, sslcz_mode=?,
                                cod_enabled=?, payment_methods=?, bank_detail=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['stripe_public_key'] ?? '',
        $_POST['stripe_secret_key'] ?? '',
        $_POST['paypal_client_id'] ?? '',
        $_POST['paypal_secret'] ?? '',
        $_POST['paypal_sandbox_mode'] ?? 0,
        $_POST['paypal_email'] ?? '',
        $_POST['sslcz_store_id'] ?? '',
        $_POST['sslcz_store_pass'] ?? '',
        in_array($_POST['sslcz_mode'] ?? '', ['live','0'], true) ? 'live' : 'sandbox',
        $_POST['cod_enabled'] ?? 0,
        $payment_methods_selected,
        $_POST['bank_detail'] ?? ''
    ));
    $success_message = 'Payment Gateway Settings are updated successfully.';
}





// API Integrations Form
if(isset($_POST['form_api_integrations'])) {
    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                gemini_api_key=?,
                                facebook_app_id=?, facebook_app_secret=?,
                                google_client_id=?, google_client_secret=?,
                                twilio_account_sid=?, twilio_auth_token=?, twilio_phone_number=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['gemini_api_key'] ?? '',
        $_POST['facebook_app_id'] ?? '',
        $_POST['facebook_app_secret'] ?? '',
        $_POST['google_client_id'] ?? '',
        $_POST['google_client_secret'] ?? '',
        $_POST['twilio_account_sid'] ?? '',
        $_POST['twilio_auth_token'] ?? '',
        $_POST['twilio_phone_number'] ?? ''
    ));
    $success_message = 'API Integration Settings are updated successfully.';
}






// Review & Delivery Settings Form
if(isset($_POST['form_review_delivery_settings'])) {
    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                review_feature_on_off=?,
                                estimated_delivery_time_local=?,
                                estimated_delivery_time_international=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['review_feature_on_off'] ?? 0,
        $_POST['estimated_delivery_time_local'] ?? '',
        $_POST['estimated_delivery_time_international'] ?? ''
    ));
    $success_message = 'Review & Delivery Settings are updated successfully.';
}

// SMS Settings Form
if(isset($_POST['form_sms_settings'])) {
    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                sms_feature_on_off=?,
                                sms_api_key=?,
                                sms_sender_id=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['sms_feature_on_off'] ?? 0,
        $_POST['sms_api_key'] ?? '',
        $_POST['sms_sender_id'] ?? ''
    ));
    $success_message = 'SMS Settings are updated successfully.';
}





// Banner Settings Form
if(isset($_POST['form_banner_settings'])) {
    $valid = 1;
    $banner_fields = [
        'banner_cart', 'banner_search', 'banner_registration', 'banner_login',
        'banner_forget_password', 'banner_reset_password', 'banner_product_category',
        'banner_blog', 'banner_faq', 'banner_contact', 'banner_checkout',
        'banner_payment', 'banner_customer_panel', 'banner_about', 'banner_terms',
        'banner_privacy', 'banner_shipping', 'banner_return_policy',
        'banner_photo_gallery', 'banner_team'
    ];

    $update_query_parts = [];
    $update_query_values = [];

    foreach ($banner_fields as $field) {
        $updated_val = null;
        if (!empty($_FILES[$field]['tmp_name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            if (in_array($ext, $allowed_exts)) {
                $cloud_url = uploadFileToSupabase($_FILES[$field]['tmp_name'], $field . '_' . time() . '.' . $ext, 'assets');
                if ($cloud_url) {
                    $updated_val = $cloud_url;
                } else {
                    $valid = 0;
                    $error_message = 'Failed to upload ' . $field . ' to Supabase Storage.';
                    break;
                }
            } else {
                $valid = 0;
                $error_message = 'Invalid file format for ' . $field . '. Supported formats: JPG, JPEG, PNG, WEBP, GIF, SVG.';
                break;
            }
        } elseif (isset($_POST[$field . '_url']) && trim($_POST[$field . '_url']) !== '') {
            $url_val = trim($_POST[$field . '_url']);
            if ($url_val !== ($settings_data[$field] ?? '')) {
                $updated_val = $url_val;
            }
        }

        if ($updated_val !== null) {
            $update_query_parts[] = '"' . $field . '" = ?';
            $update_query_values[] = $updated_val;
            $settings_data[$field] = $updated_val;
        }
    }

    if ($valid == 1) {
        if (!empty($update_query_parts)) {
            $statement = $pdo->prepare("UPDATE tbl_settings SET " . implode(', ', $update_query_parts) . " WHERE id=1");
            $statement->execute($update_query_values);
            $success_message = 'Banner Settings updated successfully in Supabase Storage and Database.';
        } else {
            $error_message = 'No changes or new banner files selected.';
        }
    }
}





// Social Media Form
if(isset($_POST['form_social_settings'])) {
    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                facebook_url=?, twitter_url=?, linkedin_url=?,
                                instagram_url=?, youtube_url=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['facebook_url'] ?? '',
        $_POST['twitter_url'] ?? '',
        $_POST['linkedin_url'] ?? '',
        $_POST['instagram_url'] ?? '',
        $_POST['youtube_url'] ?? ''
    ));
    $success_message = 'Social Media Settings are updated successfully.';
}




// Footer Settings Form
if(isset($_POST['form_footer_settings'])) {
    $valid = 1;
    $payment_verified_image = handle_file_upload('payment_verified_image', $payment_verified_image, '../assets/uploads/', 'payment_verified-');
    if ($payment_verified_image === false) $valid = 0;

    if ($valid == 1) {
        $mobile_footer_on_off = isset($_POST['mobile_footer_on_off']) ? (int)$_POST['mobile_footer_on_off'] : 0;
        $related_products_on_off = isset($_POST['related_products_on_off']) ? (int)$_POST['related_products_on_off'] : 1;

        // Auto-add column if not exists in tenant schema
        try {
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS mobile_footer_on_off smallint DEFAULT 0");
            $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS related_products_on_off smallint DEFAULT 1");
        } catch (Throwable $e) {}

        $statement = $pdo->prepare("UPDATE tbl_settings SET
                                    copyright_text=?, footer_about_us=?, contact_address=?, contact_map_iframe=?, payment_verified_image=?,
                                    mobile_footer_on_off=?, related_products_on_off=?
                                    WHERE id=1");
        $statement->execute(array(
            $_POST['copyright_text'] ?? '',
            $_POST['footer_about_us'] ?? '',
            $_POST['contact_address'] ?? '',
            $_POST['contact_map_iframe'] ?? '',
            $payment_verified_image,
            $mobile_footer_on_off,
            $related_products_on_off
        ));
        @unlink(__DIR__ . '/inc/cache_settings.json');
        $success_message = 'Footer & Display Settings are updated successfully.';
    }
}



// Email Form Settings Form (renamed from form4)

if(isset($_POST['form_email_settings'])) {
    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                smtp_from_name=?, smtp_from_email=?, email_method=?, smtp_host=?,smtp_port=?,smtp_username=?,smtp_password=?,smtp_encryption=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['smtp_from_name'] ?? '',
        $_POST['smtp_from_email'] ?? '',
        $_POST['email_method'] ?? '',
        $_POST['smtp_host'] ?? '',
        $_POST['smtp_port'] ?? '',
        $_POST['smtp_username'] ?? '',
        $_POST['smtp_password'] ?? '',
        $_POST['smtp_encryption'] ?? ''
    ));
    $success_message = 'Email outgoing Settings are updated successfully.';
}








// Email Content Settings Form (renamed from form4)
if(isset($_POST['form_email_content_settings'])) {
    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                receive_email=?, receive_email_subject=?, receive_email_thank_you_message=?, forget_password_message=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['receive_email'] ?? '',
        $_POST['receive_email_subject'] ?? '',
        $_POST['receive_email_thank_you_message'] ?? '',
        $_POST['forget_password_message'] ?? ''
    ));
    $success_message = 'Email Content Settings are updated successfully.';
}


// Blog/Post Counts Settings Form (renamed from form5)
if(isset($_POST['form_blog_post_counts'])) {
    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                total_featured_product_home=?, total_latest_product_home=?, total_popular_product_home=?,
                                total_recent_post_footer=?, total_popular_post_footer=?,
                                total_recent_post_sidebar=?, total_popular_post_sidebar=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['total_featured_product_home'] ?? 0,
        $_POST['total_latest_product_home'] ?? 0,
        $_POST['total_popular_product_home'] ?? 0,
        $_POST['total_recent_post_footer'] ?? 0,
        $_POST['total_popular_post_footer'] ?? 0,
        $_POST['total_recent_post_sidebar'] ?? 0,
        $_POST['total_popular_post_sidebar'] ?? 0
    ));
    $success_message = 'Blog/Post Counts Settings are updated successfully.';
}

// Ads On/Off Settings Form (renamed from form11)
if(isset($_POST['form_ads_settings'])) {
    $statement = $pdo->prepare("UPDATE tbl_settings SET
                                ads_above_welcome_on_off=?,
                                ads_above_featured_product_on_off=?,
                                ads_above_latest_product_on_off=?,
                                ads_above_popular_product_on_off=?,
                                ads_above_testimonial_on_off=?,
                                ads_category_sidebar_on_off=?
                                WHERE id=1");
    $statement->execute(array(
        $_POST['ads_above_welcome_on_off'] ?? 0,
        $_POST['ads_above_featured_product_on_off'] ?? 0,
        $_POST['ads_above_latest_product_on_off'] ?? 0,
        $_POST['ads_above_popular_product_on_off'] ?? 0,
        $_POST['ads_above_testimonial_on_off'] ?? 0,
        $_POST['ads_category_sidebar_on_off'] ?? 0
    ));
    $success_message = 'Advertisement On-Off Settings are updated successfully.';
}



// --- Page Settings Form Handlers ---
// About Us Form
if(isset($_POST['form_page_about']) || isset($_POST['form_about'])) {
    $valid = 1;
    if(empty($_POST['about_title'])) {
        $valid = 0;
        $error_message .= 'About Page Title cannot be empty.<br>';
    }
    $path = $_FILES['about_banner']['name'] ?? '';
    $path_tmp = $_FILES['about_banner']['tmp_name'] ?? '';
    if(!empty($path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if(!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $valid = 0;
            $error_message .= 'You must upload a jpg, jpeg, gif, webp or png file for About banner.<br>';
        }
    }
    if($valid == 1) {
        if(!empty($path)) {
            $final_name = 'about-banner-' . time() . '.' . $ext;
            move_uploaded_file($path_tmp, '../assets/uploads/' . $final_name);
            $stmt = $pdo->prepare("UPDATE tbl_page SET about_title=?, about_content=?, about_banner=?, about_meta_title=?, about_meta_keyword=?, about_meta_description=? WHERE id=1");
            $stmt->execute([$_POST['about_title'], $_POST['about_content'] ?? '', $final_name, $_POST['about_meta_title'] ?? '', $_POST['about_meta_keyword'] ?? '', $_POST['about_meta_description'] ?? '']);
            $about_banner = $final_name;
        } else {
            $stmt = $pdo->prepare("UPDATE tbl_page SET about_title=?, about_content=?, about_meta_title=?, about_meta_keyword=?, about_meta_description=? WHERE id=1");
            $stmt->execute([$_POST['about_title'], $_POST['about_content'] ?? '', $_POST['about_meta_title'] ?? '', $_POST['about_meta_keyword'] ?? '', $_POST['about_meta_description'] ?? '']);
        }
        $about_title = $_POST['about_title'];
        $about_content = $_POST['about_content'] ?? '';
        $about_meta_title = $_POST['about_meta_title'] ?? '';
        $about_meta_keyword = $_POST['about_meta_keyword'] ?? '';
        $about_meta_description = $_POST['about_meta_description'] ?? '';
        $success_message = 'About Page Information updated successfully.';
    }
}

// FAQ Form
if(isset($_POST['form_page_faq']) || isset($_POST['form_faq'])) {
    $valid = 1;
    if(empty($_POST['faq_title'])) {
        $valid = 0;
        $error_message .= 'FAQ Page Title cannot be empty.<br>';
    }
    $path = $_FILES['faq_banner']['name'] ?? '';
    $path_tmp = $_FILES['faq_banner']['tmp_name'] ?? '';
    if(!empty($path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if(!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $valid = 0;
            $error_message .= 'You must upload a jpg, jpeg, gif, webp or png file for FAQ banner.<br>';
        }
    }
    if($valid == 1) {
        if(!empty($path)) {
            $final_name = 'faq-banner-' . time() . '.' . $ext;
            move_uploaded_file($path_tmp, '../assets/uploads/' . $final_name);
            $stmt = $pdo->prepare("UPDATE tbl_page SET faq_title=?, faq_banner=?, faq_meta_title=?, faq_meta_keyword=?, faq_meta_description=? WHERE id=1");
            $stmt->execute([$_POST['faq_title'], $final_name, $_POST['faq_meta_title'] ?? '', $_POST['faq_meta_keyword'] ?? '', $_POST['faq_meta_description'] ?? '']);
            $faq_banner = $final_name;
        } else {
            $stmt = $pdo->prepare("UPDATE tbl_page SET faq_title=?, faq_meta_title=?, faq_meta_keyword=?, faq_meta_description=? WHERE id=1");
            $stmt->execute([$_POST['faq_title'], $_POST['faq_meta_title'] ?? '', $_POST['faq_meta_keyword'] ?? '', $_POST['faq_meta_description'] ?? '']);
        }
        $faq_title = $_POST['faq_title'];
        $faq_meta_title = $_POST['faq_meta_title'] ?? '';
        $faq_meta_keyword = $_POST['faq_meta_keyword'] ?? '';
        $faq_meta_description = $_POST['faq_meta_description'] ?? '';
        $success_message = 'FAQ Page Information updated successfully.';
    }
}

// Contact Form
if(isset($_POST['form_page_contact']) || isset($_POST['form_contact'])) {
    $valid = 1;
    if(empty($_POST['contact_title'])) {
        $valid = 0;
        $error_message .= 'Contact Page Title cannot be empty.<br>';
    }
    $path = $_FILES['contact_banner']['name'] ?? '';
    $path_tmp = $_FILES['contact_banner']['tmp_name'] ?? '';
    if(!empty($path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if(!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $valid = 0;
            $error_message .= 'You must upload a jpg, jpeg, gif, webp or png file for Contact banner.<br>';
        }
    }
    if($valid == 1) {
        if(!empty($path)) {
            $final_name = 'contact-banner-' . time() . '.' . $ext;
            move_uploaded_file($path_tmp, '../assets/uploads/' . $final_name);
            $stmt = $pdo->prepare("UPDATE tbl_page SET contact_title=?, contact_banner=?, contact_meta_title=?, contact_meta_keyword=?, contact_meta_description=? WHERE id=1");
            $stmt->execute([$_POST['contact_title'], $final_name, $_POST['contact_meta_title'] ?? '', $_POST['contact_meta_keyword'] ?? '', $_POST['contact_meta_description'] ?? '']);
            $contact_banner = $final_name;
        } else {
            $stmt = $pdo->prepare("UPDATE tbl_page SET contact_title=?, contact_meta_title=?, contact_meta_keyword=?, contact_meta_description=? WHERE id=1");
            $stmt->execute([$_POST['contact_title'], $_POST['contact_meta_title'] ?? '', $_POST['contact_meta_keyword'] ?? '', $_POST['contact_meta_description'] ?? '']);
        }
        $contact_title = $_POST['contact_title'];
        $contact_meta_title = $_POST['contact_meta_title'] ?? '';
        $contact_meta_keyword = $_POST['contact_meta_keyword'] ?? '';
        $contact_meta_description = $_POST['contact_meta_description'] ?? '';
        $success_message = 'Contact Page Information updated successfully.';
    }
}

// Language Converter Form
if(isset($_POST['form_language_settings']) || isset($_POST['form_lang_settings'])) {
    if(!empty($_POST['lang_value']) && is_array($_POST['lang_value'])) {
        foreach($_POST['lang_value'] as $key => $val) {
            $stmt = $pdo->prepare("UPDATE tbl_language SET lang_value=? WHERE lang_id=?");
            $stmt->execute([$val, (int)$key]);
            $lang_ids[(int)$key] = $val;
        }
        $success_message = 'Language Settings updated successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if ($error_message !== '') { $pdo->rollBack(); $success_message=''; }
    else {
        if (isset($_POST['form_footer_settings'])) $pdo->prepare('UPDATE tbl_settings SET footer_copyright=copyright_text,footer_about=footer_about_us WHERE id=1')->execute();
        $pdo->commit();
    }
}
} catch(Throwable $error) {
    if($pdo->inTransaction()) $pdo->rollBack();
    $success_message='';$error_message='Settings could not be saved. Check the entered values and try again.';
    error_log('Store settings update failed: ' . $error->getCode());
}
// Re-fetch settings after any update to ensure displayed values are current
$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$settings_data = $statement->fetch(PDO::FETCH_ASSOC);

try {
    $statement = $pdo->prepare("SELECT * FROM tbl_page WHERE id=1");
    $statement->execute();
    $page_data = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    $about_title = $page_data['about_title'] ?? '';
    $about_content = $page_data['about_content'] ?? '';
    $about_banner = $page_data['about_banner'] ?? '';
    $about_meta_title = $page_data['about_meta_title'] ?? '';
    $about_meta_keyword = $page_data['about_meta_keyword'] ?? '';
    $about_meta_description = $page_data['about_meta_description'] ?? '';
    $faq_title = $page_data['faq_title'] ?? '';
    $faq_banner = $page_data['faq_banner'] ?? '';
    $faq_meta_title = $page_data['faq_meta_title'] ?? '';
    $faq_meta_keyword = $page_data['faq_meta_keyword'] ?? '';
    $faq_meta_description = $page_data['faq_meta_description'] ?? '';
    $contact_title = $page_data['contact_title'] ?? '';
    $contact_banner = $page_data['contact_banner'] ?? '';
    $contact_meta_title = $page_data['contact_meta_title'] ?? '';
    $contact_meta_keyword = $page_data['contact_meta_keyword'] ?? '';
    $contact_meta_description = $page_data['contact_meta_description'] ?? '';
    
    $lang_statement = $pdo->prepare("SELECT * FROM tbl_language ORDER BY lang_id ASC");
    $lang_statement->execute();
    $lang_rows = $lang_statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($lang_rows as $row) {
        $lang_ids[(int)$row['lang_id']] = $row['lang_value'];
    }
} catch (Throwable $e) {}

// Re-assign all variables with potentially updated values (using original names)
$logo = $settings_data['logo'] ?? '';
$favicon = $settings_data['favicon'] ?? '';
$contact_email = $settings_data['contact_email'] ?? '';
$contact_phone = $settings_data['contact_phone'] ?? '';
$meta_title_home = $settings_data['meta_title_home'] ?? '';
$meta_keyword_home = $settings_data['meta_keyword_home'] ?? '';
$meta_description_home = $settings_data['meta_description_home'] ?? '';
$before_head = $settings_data['before_head'] ?? '';
$after_body = $settings_data['after_body'] ?? '';
$before_body = $settings_data['before_body'] ?? '';

$cta_title = $settings_data['cta_title'] ?? '';
$cta_content = $settings_data['cta_content'] ?? '';
$cta_read_more_text = $settings_data['cta_read_more_text'] ?? '';
$cta_read_more_url = $settings_data['cta_read_more_url'] ?? '';
$cta_photo = $settings_data['cta_photo'] ?? '';
$featured_product_title = $settings_data['featured_product_title'] ?? '';
$featured_product_subtitle = $settings_data['featured_product_subtitle'] ?? '';
$latest_product_title = $settings_data['latest_product_title'] ?? '';
$latest_product_subtitle = $settings_data['latest_product_subtitle'] ?? '';
$popular_product_title = $settings_data['popular_product_title'] ?? '';
$popular_product_subtitle = $settings_data['popular_product_subtitle'] ?? '';
$testimonial_title = $settings_data['testimonial_title'] ?? '';
$testimonial_subtitle = $settings_data['testimonial_subtitle'] ?? '';
$testimonial_photo = $settings_data['testimonial_photo'] ?? '';
$blog_title = $settings_data['blog_title'] ?? '';
$blog_subtitle = $settings_data['blog_subtitle'] ?? '';
$newsletter_text = $settings_data['newsletter_text'] ?? '';

$total_featured_product_home = $settings_data['total_featured_product_home'] ?? 0;
$total_latest_product_home = $settings_data['total_latest_product_home'] ?? 0;
$total_popular_product_home = $settings_data['total_popular_product_home'] ?? 0;

$home_service_on_off = $settings_data['home_service_on_off'] ?? 0;
$home_welcome_on_off = $settings_data['home_welcome_on_off'] ?? 0;
$home_featured_product_on_off = $settings_data['home_featured_product_on_off'] ?? 0;
$home_latest_product_on_off = $settings_data['home_latest_product_on_off'] ?? 0;
$home_popular_product_on_off = $settings_data['home_popular_product_on_off'] ?? 0;
$home_testimonial_on_off = $settings_data['home_testimonial_on_off'] ?? 0;
$home_blog_on_off = $settings_data['home_blog_on_off'] ?? 0;
$home_map_on_off = $settings_data['home_map_on_off'] ?? 0;
$home_newsletter_on_off = $settings_data['home_newsletter_on_off'] ?? 0;
$home_brand_on_off = $settings_data['home_brand_on_off'] ?? 0;






// Email Settings - Using original names for consistency with likely DB schema
$smtp_from_name = $settings_data['smtp_from_name'] ?? '';
$smtp_from_email = $settings_data['smtp_from_email'] ?? '';
$email_method = $settings_data['email_method'] ?? 'PHP Mail';
$smtp_host = $settings_data['smtp_host'] ?? '';
$smtp_port = $settings_data['smtp_port'] ?? '';
$smtp_username = $settings_data['smtp_username'] ?? '';
$smtp_password = $settings_data['smtp_password'] ?? '';
$smtp_encryption = $settings_data['smtp_encryption'] ?? 'none';
$receive_email = $settings_data['receive_email'] ?? '';
$receive_email_subject = $settings_data['receive_email_subject'] ?? '';
$receive_email_thank_you_message = $settings_data['receive_email_thank_you_message'] ?? '';
$forget_password_message = $settings_data['forget_password_message'] ?? '';

$stripe_public_key = $settings_data['stripe_public_key'] ?? '';
$stripe_secret_key = $settings_data['stripe_secret_key'] ?? '';
$paypal_client_id = $settings_data['paypal_client_id'] ?? '';
$paypal_secret = $settings_data['paypal_secret'] ?? '';
$paypal_sandbox_mode = $settings_data['paypal_sandbox_mode'] ?? 0;
$paypal_email = $settings_data['paypal_email'] ?? '';
$bank_detail = $settings_data['bank_detail'] ?? '';
$sslcz_store_id = $settings_data['sslcz_store_id'] ?? '';
$sslcz_store_pass = $settings_data['sslcz_store_pass'] ?? '';
$sslcz_mode = $settings_data['sslcz_mode'] ?? 'sandbox';
$cod_enabled = $settings_data['cod_enabled'] ?? 1;
$payment_methods = $settings_data['payment_methods'] ?? '';
$enabled_payment_methods_array = explode(',', $payment_methods);

$gemini_api_key = $settings_data['gemini_api_key'] ?? '';
$facebook_app_id = $settings_data['facebook_app_id'] ?? '';
$facebook_app_secret = $settings_data['facebook_app_secret'] ?? '';
$google_client_id = $settings_data['google_client_id'] ?? '';
$google_client_secret = $settings_data['google_client_secret'] ?? '';
$twilio_account_sid = $settings_data['twilio_account_sid'] ?? '';
$twilio_auth_token = $settings_data['twilio_auth_token'] ?? '';
$twilio_phone_number = $settings_data['twilio_phone_number'] ?? '';

$review_feature_on_off = $settings_data['review_feature_on_off'] ?? 1;
$estimated_delivery_time_local = $settings_data['estimated_delivery_time_local'] ?? '3-5 business days';
$estimated_delivery_time_international = $settings_data['estimated_delivery_time_international'] ?? '10-20 business days';

$sms_api_key = $settings_data['sms_api_key'] ?? '';
$sms_sender_id = $settings_data['sms_sender_id'] ?? '';
$sms_feature_on_off = $settings_data['sms_feature_on_off'] ?? 0;

$banner_cart = $settings_data['banner_cart'] ?? '';
$banner_search = $settings_data['banner_search'] ?? '';
$banner_registration = $settings_data['banner_registration'] ?? '';
$banner_login = $settings_data['banner_login'] ?? '';
$banner_forget_password = $settings_data['banner_forget_password'] ?? '';
$banner_reset_password = $settings_data['banner_reset_password'] ?? '';
$banner_product_category = $settings_data['banner_product_category'] ?? '';
$banner_blog = $settings_data['banner_blog'] ?? '';
$banner_faq = $settings_data['banner_faq'] ?? '';
$banner_contact = $settings_data['banner_contact'] ?? '';
$banner_checkout = $settings_data['banner_checkout'] ?? '';
$banner_payment = $settings_data['banner_payment'] ?? '';
$banner_customer_panel = $settings_data['banner_customer_panel'] ?? '';
$banner_about = $settings_data['banner_about'] ?? '';
$banner_terms = $settings_data['banner_terms'] ?? '';
$banner_privacy = $settings_data['banner_privacy'] ?? '';
$banner_shipping = $settings_data['banner_shipping'] ?? '';
$banner_return_policy = $settings_data['banner_return_policy'] ?? '';
$banner_photo_gallery = $settings_data['banner_photo_gallery'] ?? '';
$banner_team = $settings_data['banner_team'] ?? '';

$facebook_url = $settings_data['facebook_url'] ?? '';
$twitter_url = $settings_data['twitter_url'] ?? '';
$linkedin_url = $settings_data['linkedin_url'] ?? '';
$instagram_url = $settings_data['instagram_url'] ?? '';
$youtube_url = $settings_data['youtube_url'] ?? '';

$copyright_text = $settings_data['copyright_text'] ?? '';
$footer_about_us = $settings_data['footer_about_us'] ?? '';
$contact_address = $settings_data['contact_address'] ?? '';
$contact_map_iframe = $settings_data['contact_map_iframe'] ?? '';
$payment_verified_image = $settings_data['payment_verified_image'] ?? '';
$mobile_footer_on_off = isset($settings_data['mobile_footer_on_off']) ? (int)$settings_data['mobile_footer_on_off'] : 0;
$related_products_on_off = isset($settings_data['related_products_on_off']) ? (int)$settings_data['related_products_on_off'] : 1;

$ads_above_welcome_on_off = $settings_data['ads_above_welcome_on_off'] ?? 0;
$ads_above_featured_product_on_off = $settings_data['ads_above_featured_product_on_off'] ?? 0;
$ads_above_latest_product_on_off = $settings_data['ads_above_latest_product_on_off'] ?? 0;
$ads_above_popular_product_on_off = $settings_data['ads_above_popular_product_on_off'] ?? 0;
$ads_above_testimonial_on_off = $settings_data['ads_above_testimonial_on_off'] ?? 0;
$ads_category_sidebar_on_off = $settings_data['ads_category_sidebar_on_off'] ?? 0;

$total_recent_post_footer = $settings_data['total_recent_post_footer'] ?? 3;
$total_popular_post_footer = $settings_data['total_popular_post_footer'] ?? 3;
$total_recent_post_sidebar = $settings_data['total_recent_post_sidebar'] ?? 3;
$total_popular_post_sidebar = $settings_data['total_popular_post_sidebar'] ?? 3;


// New variables for banner and free delivery visibility
$hide_banner_desktop = $settings_data['hide_banner_desktop'] ?? 0;
$hide_banner_mobile = $settings_data['hide_banner_mobile'] ?? 0;
$hide_free_delivery_desktop = $settings_data['hide_free_delivery_desktop'] ?? 0;
$hide_free_delivery_mobile = $settings_data['hide_free_delivery_mobile'] ?? 0;









$lang_sections = [
    'Basic' => [
        1 => 'Currency',
        2 => 'Search Product',
        3 => 'Search',
        4 => 'Submit',
        5 => 'Update',
        6 => 'Read More',
        7 => 'Serial',
        8 => 'Photo',
    ],
    'Login' => [
        9 => 'Login',
        10 => 'Customer Login',
        11 => 'Click here to login',
        12 => 'Back to Login Page',
        13 => 'Logged in as',
        14 => 'Logout',
    ],
    'Registration' => [
        15 => 'Register',
        16 => 'Customer Registration',
        17 => 'Registration Successful',
    ],
    'Cart and Checkout' => [
        18 => 'Cart',
        19 => 'View Cart',
        20 => 'Update Cart',
        154 => 'Add to Cart',
        21 => 'Back to Cart',
        22 => 'Checkout',
        23 => 'Proceed to Checkout',
        160 => 'Please login as customer to checkout',
    ],
    'Payment' => [
        24 => 'Orders',
        25 => 'Order History',
        26 => 'Order Details',
        27 => 'Payment Date and Time',
        28 => 'Transaction ID',
        29 => 'Paid Amount',
        30 => 'Payment Status',
        31 => 'Payment Method',
        32 => 'Payment ID',
        33 => 'Payment Section',
        34 => 'Select Payment Method',
        35 => 'Select a Method',
        36 => 'PayPal',
        37 => 'Stripe',
        38 => 'Bank Deposit',
        39 => 'Card Number',
        40 => 'CVV',
        41 => 'Month',
        42 => 'Year',
        43 => 'Send to this Details',
        44 => 'Transaction Information',
        45 => 'Include transaction id and other information correctly',
        46 => 'Pay Now',
    ],
    'Product' => [
        47 => 'Product Name',
        48 => 'Product Details',
        155 => 'Related Products',
        156 => 'See all the related products from below',
        49 => 'Categories',
        50 => 'Category:',
        51 => 'All Products Under',
        52 => 'Select Size',
        157 => 'Size',
        53 => 'Select Color',
        158 => 'Color',
        159 => 'Price',
        54 => 'Product Price',
        55 => 'Quantity',
        56 => 'Out of Stock',
        57 => 'Share This',
        58 => 'Share This Product',
        59 => 'Product Description',
        153 => 'No Product Found',
        60 => 'Features',
        61 => 'Conditions',
        62 => 'Return Policy',
        63 => 'Reviews',
        64 => 'Review',
        65 => 'Give a Review',
        66 => 'Write your comment (Optional)',
        67 => 'Submit Review',
        68 => 'You already have given a rating!',
        163 => 'Rating is submitted successfully!',
        69 => 'You must have to login to give a review',
        70 => 'No description found',
        71 => 'No feature found',
        72 => 'No condition found',
        73 => 'No return policy found',
        74 => 'No Review is Found',
        75 => 'Customer Name',
        76 => 'Comment',
        77 => 'Comments',
        78 => 'Rating',
        79 => 'Previous',
        80 => 'Next',
        81 => 'Sub Total',
        82 => 'Total',
        83 => 'Action',
    ],
    'Billing and Shipping' => [
        84 => 'Shipping Cost',
        85 => 'Continue Shipping',
        161 => 'Billing Address',
        86 => 'Update Billing Address',
        162 => 'Shipping Address',
        87 => 'Update Shipping Address',
        88 => 'Update Billing and Shipping Info',
    ],
    'Dashboard' => [
        89 => 'Dashboard',
        90 => 'Welcome to the Dashboard',
        91 => 'Back to Dashboard',
    ],
    'Subscribe' => [
        92 => 'Subscribe',
        93 => 'Subscribe To Our Newsletter',
    ],
    'Email Address' => [
        94 => 'Email Address',
        95 => 'Enter Your Email Address',
    ],
    'Password' => [
        96 => 'Password',
        97 => 'Forget Password',
        98 => 'Retype Password',
        99 => 'Update Password',
        100 => 'New Password',
        101 => 'Retype New Password',
        149 => 'Change Password',
    ],
    'Customer' => [
        102 => 'Full Name',
        103 => 'Company Name',
        104 => 'Phone Number',
        105 => 'Address',
        106 => 'Country',
        107 => 'City',
        108 => 'State',
        109 => 'Zip Code',
    ],
    'Other Information' => [
        110 => 'About Us',
        111 => 'Featured Posts',
        112 => 'Popular Posts',
        113 => 'Recent Posts',
        114 => 'Contact Information',
        115 => 'Contact Form',
        116 => 'Our Office',
        117 => 'Update Profile',
        118 => 'Send Message',
        119 => 'Message',
        120 => 'Find Us On Map',
    ],
    'Error Messages' => [
        121 => 'Congratulation! Payment is successful.',
        122 => 'Billing and Shipping Information is updated successfully.',
        123 => 'Customer Name can not be empty.',
        124 => 'Phone Number can not be empty.',
        125 => 'Address can not be empty.',
        126 => 'You must have to select a country.',
        127 => 'City can not be empty.',
        128 => 'State can not be empty.',
        129 => 'Zip Code can not be empty.',
        130 => 'Profile Information is updated successfully.',
        131 => 'Email Address can not be empty',
        132 => 'Email and/or Password can not be empty.',
        133 => 'Email Address does not match.',
        134 => 'Email address must be valid.',
        147 => 'Email Address Already Exists.',
        135 => 'You email address is not found in our system.',
        136 => 'Please check your email and confirm your subscription.',
        137 => 'Your email is verified successfully. You can now login to our website.',
        138 => 'Password can not be empty.',
        139 => 'Passwords do not match.',
        140 => 'Please enter new and retype passwords.',
        141 => 'Password is updated successfully.',
        142 => 'To reset your password, please click on the link below.',
        143 => 'PASSWORD RESET REQUEST - YOUR WEBSITE.COM',
        144 => 'The password reset email time (24 hours) has expired. Please again try to reset your password.',
        145 => 'A confirmation link is sent to your email address. You will get the password reset information in there.',
        146 => 'Password is reset successfully. You can now login.',
        148 => 'Sorry! Your account is inactive. Please contact to the administrator.',
        150 => 'Registration Email Confirmation for YOUR WEBSITE.',
        151 => 'Thank you for your registration! Your account has been created. To active your account click on the link below:',
        152 => 'Your registration is completed. Please check your email address to follow the process to confirm your registration.',
    ],
];

?>

<style>
    /* Basic styling for the admin panel to enhance Tailwind's utility classes */
    .tab-content {
        background-color: #ffffff;
        padding: 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .nav-tabs {
        border-bottom: none;
        margin-bottom: 1.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem; /* Space between tabs */
    }
    .nav-tabs li {
        margin-bottom: 0;
    }
    .nav-tabs li a {
        display: block;
        padding: 0.75rem 1.25rem;
        border-radius: 0.375rem; /* rounded-md */
        font-weight: 600; /* font-semibold */
        color: #4b5563; /* text-gray-700 */
        transition: all 0.2s ease-in-out;
        background-color: #e5e7eb; /* bg-gray-200 */
        border: 1px solid transparent;
        text-decoration: none;
    }
    .nav-tabs li a:hover {
        background-color: #d1d5db; /* bg-gray-300 */
        color: #1f2937; /* text-gray-900 */
    }
    .nav-tabs li.active a,
    .nav-tabs li.active a:hover {
        background-color: #4f46e5; /* bg-indigo-600 */
        color: #ffffff; /* text-white */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        font-weight: 500;
        color: #374151; /* text-gray-700 */
        margin-bottom: 0.5rem;
        display: block;
    }
    .form-control {
        display: block;
        width: 100%;
        padding: 0.625rem 1rem; /* py-2.5 px-4 */
        font-size: 1rem;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #d2d6da; /* border-gray-300 */
        border-radius: 0.375rem; /* rounded-md */
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .form-control:focus {
        border-color: #818cf8; /* indigo-300 */
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); /* ring-indigo-200 */
    }
    textarea.form-control {
        min-height: 80px;
    }
    select.form-control {
        padding-right: 2.5rem; /* For dropdown arrow */
    }

    .btn-success {
        background-color: #10b981; /* bg-green-500 */
        color: #ffffff;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 0.375rem;
        font-weight: 600;
        transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
    }
    .btn-success:hover {
        background-color: #059669; /* bg-green-600 */
        transform: translateY(-1px);
    }
    .btn-primary {
        background-color: #3b82f6; /* bg-blue-500 */
        color: #ffffff;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 500;
        transition: background-color 0.2s ease-in-out;
    }
    .btn-primary:hover {
        background-color: #2563eb; /* bg-blue-600 */
    }

    .error {
        background-color: #fee2e2; /* bg-red-100 */
        color: #dc2626; /* text-red-700 */
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #ef4444; /* border-red-500 */
    }
    .success {
        background-color: #d1fae5; /* bg-green-100 */
        color: #065f46; /* text-green-700 */
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #10b981; /* border-green-500 */
 }
    .seo-info {
        font-size: 1.25rem; /* text-xl */
        font-weight: 700; /* font-bold */
        color: #1f2937; /* text-gray-900 */
        margin-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb; /* border-gray-200 */
        padding-bottom: 0.5rem;
    }
    .existing-photo {
        max-width: 150px;
        height: auto;
        border-radius: 0.25rem;
        margin-bottom: 0.5rem;
        border: 1px solid #e5e7eb;
    }
    .help-block {
        font-size: 0.875rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }
    .checkbox label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        font-weight: normal;
        color: #374151;
    }
    .checkbox input[type="checkbox"] {
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 0.25rem;
        border: 1px solid #d2d6da;
        accent-color: #4f46e5; /* For checked state */
    }
</style>

<section class="content-header p-6 bg-white shadow-sm rounded-lg mb-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Website Settings</h1>
    </div>
</section>

<section class="content p-6">
    <div class="row">
        <div class="col-md-12">
            <?php if($error_message): ?>
            <div class="error">
                <p><?php echo $error_message; ?></p>
            </div>
            <?php endif; ?>

            <?php if($success_message): ?>
            <div class="success">
                <p><?php echo $success_message; ?></p>
            </div>
            <?php endif; ?>

            <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                <div class="nav-tabs-custom bg-white shadow-lg rounded-lg">
                    <ul class="nav nav-tabs px-4 pt-4">
                        <li class="active"><a href="#tab_general" data-toggle="tab">General</a></li>
                        <li><a href="#tab_home_features" data-toggle="tab">Home Features</a></li>
                        <li><a href="#tab_page_settings" data-toggle="tab"><i class="fa fa-file-text-o"></i> Page Settings</a></li>
                        <li><a href="#tab_language_converter" data-toggle="tab"><i class="fa fa-globe"></i> Language Converter</a></li>
                        <li><a href="#tab_payment_gateways" data-toggle="tab">Payment Gateways</a></li>
                        <li><a href="#tab_api_integrations" data-toggle="tab">API Integrations</a></li>
                        <li><a href="#tab_review_delivery" data-toggle="tab">Review & Delivery</a></li>
                        <li><a href="#tab_sms" data-toggle="tab">SMS</a></li>
                        <li><a href="#tab_banners" data-toggle="tab">Banners</a></li>
                        <li><a href="#tab_social_media" data-toggle="tab">Social Media</a></li>
                        <li><a href="#tab_email" data-toggle="tab">Email</a></li>
                        <li><a href="#tab_footer" data-toggle="tab">Footer</a></li>
                        <li><a href="#tab_ads" data-toggle="tab">Ads</a></li>
                    </ul>

                    <div class="tab-content">
                        <!-- Tab 1: General Settings -->
                        <div class="tab-pane active" id="tab_general">
                            <div class="box box-info">
                                <div class="box-body">
                                    <div class="form-group">
                                        <label for="photo_logo" class="col-sm-3 control-label">Website Logo</label>
                                        <div class="col-sm-9">
                                            <?php if (!empty($logo) && file_exists('../assets/uploads/'.$logo)): ?>
                                                <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="existing-photo"><br>
                                            <?php else: ?>
                                                <p class="text-gray-500">No logo uploaded.</p>
                                            <?php endif; ?>
                                            <input type="file" name="photo_logo" id="photo_logo" class="form-control-file">
                                            <p class="help-block">Upload a new logo (JPG, PNG, JPEG, GIF)</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="photo_favicon" class="col-sm-3 control-label">Website Favicon</label>
                                        <div class="col-sm-9">
                                            <?php if (!empty($favicon) && file_exists('../assets/uploads/'.$favicon)): ?>
                                                <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo htmlspecialchars($favicon); ?>" alt="Favicon" class="existing-photo" style="width:50px; height:50px;"><br>
                                            <?php else: ?>
                                                <p class="text-gray-500">No favicon uploaded.</p>
                                            <?php endif; ?>
                                            <input type="file" name="photo_favicon" id="photo_favicon" class="form-control-file">
                                            <p class="help-block">Upload a new favicon (JPG, PNG, JPEG, GIF)</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="contact_email" class="col-sm-3 control-label">Contact Email</label>
                                        <div class="col-sm-9">
                                            <input type="email" name="contact_email" id="contact_email" class="form-control" value="<?php echo htmlspecialchars($contact_email); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="contact_phone" class="col-sm-3 control-label">Contact Phone</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="contact_phone" id="contact_phone" class="form-control" value="<?php echo htmlspecialchars($contact_phone); ?>">
                                        </div>
                                    </div>
                                    <h3 class="seo-info mt-8">SEO & Script Settings</h3>
                                    <div class="form-group">
                                        <label for="meta_title_home" class="col-sm-3 control-label">Meta Title (Home)</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="meta_title_home" id="meta_title_home" class="form-control" value="<?php echo htmlspecialchars($meta_title_home); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="meta_keyword_home" class="col-sm-3 control-label">Meta Keyword (Home)</label>
                                        <div class="col-sm-9">
                                            <textarea name="meta_keyword_home" id="meta_keyword_home" class="form-control" rows="5"><?php echo htmlspecialchars($meta_keyword_home); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="meta_description_home" class="col-sm-3 control-label">Meta Description (Home)</label>
                                        <div class="col-sm-9">
                                            <textarea name="meta_description_home" id="meta_description_home" class="form-control" rows="5"><?php echo htmlspecialchars($meta_description_home); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="before_head" class="col-sm-3 control-label">Code before &lt;/head&gt; tag</label>
                                        <div class="col-sm-9">
                                            <textarea name="before_head" id="before_head" class="form-control" rows="5"><?php echo htmlspecialchars($before_head); ?></textarea>
                                            <p class="help-block">e.g., Google Analytics, custom CSS</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="after_body" class="col-sm-3 control-label">Code after &lt;body&gt; tag</label>
                                        <div class="col-sm-9">
                                            <textarea name="after_body" id="after_body" class="form-control" rows="5"><?php echo htmlspecialchars($after_body); ?></textarea>
                                            <p class="help-block">e.g., Google Tag Manager, custom JS</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="before_body" class="col-sm-3 control-label">Code before &lt;/body&gt; tag</label>
                                        <div class="col-sm-9">
                                            <textarea name="before_body" id="before_body" class="form-control" rows="5"><?php echo htmlspecialchars($before_body); ?></textarea>
                                            <p class="help-block">e.g., Live chat scripts</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Base URL</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="base_url" class="form-control" value="<?php echo BASE_URL; ?>">
                     
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="hide_banner_desktop" class="col-sm-3 control-label">Hide Page Banner on Desktop</label>
                                        <div class="col-sm-9">
                                            <label class="checkbox">
                                                <input type="checkbox" name="hide_banner_desktop" id="hide_banner_desktop" value="1" <?php if($hide_banner_desktop) echo 'checked'; ?>>
                                                Hide banner on desktop (visible on mobile only)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="hide_banner_mobile" class="col-sm-3 control-label">Hide Page Banner on Mobile</label>
                                        <div class="col-sm-9">
                                            <label class="checkbox">
                                                <input type="checkbox" name="hide_banner_mobile" id="hide_banner_mobile" value="1" <?php if($hide_banner_mobile) echo 'checked'; ?>>
                                                Hide banner on mobile (visible on desktop only)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="hide_free_delivery_desktop" class="col-sm-3 control-label">Hide Free Delivery on Desktop</label>
                                        <div class="col-sm-9">
                                            <label class="checkbox">
                                                <input type="checkbox" name="hide_free_delivery_desktop" id="hide_free_delivery_desktop" value="1" <?php if($hide_free_delivery_desktop) echo 'checked'; ?>>
                                                Hide 'Free Delivery' section on desktop (show on mobile only)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="hide_free_delivery_mobile" class="col-sm-3 control-label">Hide Free Delivery on Mobile</label>
                                        <div class="col-sm-9">
                                            <label class="checkbox">
                                                <input type="checkbox" name="hide_free_delivery_mobile" id="hide_free_delivery_mobile" value="1" <?php if($hide_free_delivery_mobile) echo 'checked'; ?>>
                                                Hide 'Free Delivery' section on mobile (show on desktop only)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_general_settings">Update General Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                      <div class="tab-pane" id="tab_home_features">
                            
                                <div style="display:flex; justify-content:space-between; align-items:center; background:#eff6ff; border:1px solid #bfdbfe; padding:14px 20px; border-radius:10px; margin-bottom:25px;">
                                    <div>
                                        <h4 style="margin:0 0 4px 0; color:#1e40af; font-weight:700;"><i class="fa fa-sliders"></i> Modern Homepage Customizer & Hero Slider</h4>
                                        <p style="margin:0; font-size:13px; color:#3b82f6;">All image uploads are streamed directly to Supabase Storage (<code>storefront/assets/</code>). Changes reflect immediately on your storefront.</p>
                                    </div>
                                    <div>
                                        <a href="homepage-banners.php" class="btn btn-primary" style="border-radius:20px; font-weight:700; padding:6px 18px;">
                                            <i class="fa fa-arrows-alt"></i> Full Screen Mode
                                        </a>
                                    </div>
                                </div>

                                <!-- 1. HERO SLIDER & AUTO-SLIDING MULTIPLE IMAGE UPLOAD -->
                                <div class="box box-primary" style="border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:25px;">
                                    <div class="box-header with-border" style="background:#f8fafc; padding:15px 20px;">
                                        <h3 class="box-title" style="font-weight:700; color:#1e293b;"><i class="fa fa-picture-o text-primary"></i> 1. Hero Auto-Sliding Gallery & Multi-Upload</h3>
                                        <span class="pull-right badge bg-green" style="font-size:11px; padding:5px 10px; border-radius:12px;"><i class="fa fa-cloud-upload"></i> Supabase Storage CDN</span>
                                    </div>
                                    <div class="box-body" style="padding:20px;">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Hero Section Display</label>
                                                    <select name="home_slider_on_off" class="form-control">
                                                        <option value="1" <?php if(($settings_data['home_slider_on_off'] ?? 1) == 1) echo 'selected'; ?>>Show Hero Section</option>
                                                        <option value="0" <?php if(($settings_data['home_slider_on_off'] ?? 1) == 0) echo 'selected'; ?>>Hide Hero Section</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Automatic Sliding (Autoplay)</label>
                                                    <select name="hero_slider_autoplay" class="form-control">
                                                        <option value="1" <?php if(($settings_data['hero_slider_autoplay'] ?? 1) == 1) echo 'selected'; ?>>Enabled (Auto-slides automatically)</option>
                                                        <option value="0" <?php if(($settings_data['hero_slider_autoplay'] ?? 1) == 0) echo 'selected'; ?>>Disabled (Manual slide only)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Slide Duration / Interval (Milliseconds)</label>
                                                    <div class="input-group">
                                                        <input type="number" name="hero_slider_interval" class="form-control" value="<?php echo htmlspecialchars($settings_data['hero_slider_interval'] ?? 4500); ?>" min="1000" step="500">
                                                        <span class="input-group-addon">ms</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr style="margin:15px 0;">

                                        <div class="form-group">
                                            <label style="font-size:15px; color:#1e293b;"><i class="fa fa-plus-circle text-success"></i> Add Multiple Slide Images for Home Hero:</label>
                                            <div style="border: 2px dashed #93c5fd; background:#eff6ff; border-radius:12px; padding:20px; text-align:center;">
                                                <i class="fa fa-cloud-upload" style="font-size:32px; color:#3b82f6; margin-bottom:8px;"></i>
                                                <p style="font-weight:600; margin-bottom:4px; color:#1e293b;">Select one or multiple images to upload directly to Supabase</p>
                                                <p style="font-size:12px; color:#64748b; margin-bottom:12px;">Supported formats: JPG, PNG, WEBP, GIF. Images will automatically slide on the storefront.</p>
                                                <input type="file" name="hero_slider_photos[]" multiple accept="image/*" class="form-control" style="max-width:380px; margin:0 auto; background:#fff;">
                                            </div>
                                        </div>

                                        <h4 style="font-size:15px; font-weight:700; color:#1e293b; margin-top:25px; margin-bottom:15px;">
                                            <i class="fa fa-list"></i> Current Slides in Hero Carousel (<?php echo count($slides); ?> active):
                                        </h4>

                                        <?php if (empty($slides)): ?>
                                            <div class="alert alert-info">No slides uploaded yet. Upload images above to enable sliding!</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped" style="background:#fff;">
                                                    <thead>
                                                        <tr style="background:#f1f5f9;">
                                                            <th style="width: 60px; text-align:center;">Order</th>
                                                            <th style="width: 120px; text-align:center;">Preview</th>
                                                            <th>Cloud Storage CDN URL</th>
                                                            <th style="width: 90px; text-align:center;">Active</th>
                                                            <th style="width: 90px; text-align:center;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($slides as $idx => $slide): 
                                                            $photoUrl = $slide['photo'];
                                                            if (!str_starts_with($photoUrl, 'http')) {
                                                                $photoUrl = '../assets/uploads/' . $photoUrl;
                                                            }
                                                        ?>
                                                            <tr>
                                                                <td style="vertical-align: middle; text-align:center;">
                                                                    <input type="number" name="slide_order[<?php echo $slide['id']; ?>]" value="<?php echo (int)($slide['slide_order'] ?? ($idx+1)); ?>" class="form-control text-center" style="width:65px; margin:0 auto;">
                                                                </td>
                                                                <td style="vertical-align: middle; text-align:center;">
                                                                    <a href="<?php echo htmlspecialchars($photoUrl); ?>" target="_blank">
                                                                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" style="width:85px; height:55px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1;">
                                                                    </a>
                                                                </td>
                                                                <td style="vertical-align: middle;">
                                                                    <div style="font-size:12px; font-family:monospace; color:#3b82f6; word-break:break-all;">
                                                                        <?php echo htmlspecialchars($photoUrl); ?>
                                                                    </div>
                                                                    <small class="text-muted">Slide ID: #<?php echo $slide['id']; ?></small>
                                                                </td>
                                                                <td style="vertical-align: middle; text-align:center;">
                                                                    <label style="margin:0; cursor:pointer;">
                                                                        <input type="checkbox" name="slide_active[<?php echo $slide['id']; ?>]" value="1" <?php if(($slide['is_active'] ?? 1) == 1) echo 'checked'; ?>>
                                                                        <span class="text-success" style="font-size:12px;">Active</span>
                                                                    </label>
                                                                </td>
                                                                <td style="vertical-align: middle; text-align:center;">
                                                                    <a href="settings.php?action=delete_slide&slide_id=<?php echo $slide['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this slide from hero carousel?');">
                                                                        <i class="fa fa-trash"></i> Delete
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- 2. HERO HEADINGS, BUTTONS & BADGES -->
                                <div class="box box-warning" style="border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:25px;">
                                    <div class="box-header with-border" style="background:#f8fafc; padding:15px 20px;">
                                        <h3 class="box-title" style="font-weight:700; color:#1e293b;"><i class="fa fa-font text-yellow"></i> 2. Hero Headings, Buttons & Floating Badges</h3>
                                    </div>
                                    <div class="box-body" style="padding:20px;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Eyebrow Tagline / Badge</label>
                                                    <input type="text" name="hero_tag" class="form-control" value="<?php echo htmlspecialchars($settings_data['hero_tag'] ?? 'BETTER PRODUCTS • BETTER LIFE'); ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label>Main Headline / Hero Title</label>
                                                    <input type="text" name="hero_title" class="form-control input-lg" value="<?php echo htmlspecialchars($settings_data['hero_title'] ?? 'Upgrade Your Everyday Life'); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Hero Subtitle / Description</label>
                                                    <textarea name="hero_subtitle" class="form-control" rows="3"><?php echo htmlspecialchars($settings_data['hero_subtitle'] ?? 'Discover top-quality products, unbeatable prices, and a seamless shopping experience.'); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="panel panel-default" style="background:#f8fafc; border-radius:8px; padding:12px; margin-bottom:12px;">
                                                    <h5 style="margin-top:0; font-weight:700;"><i class="fa fa-mouse-pointer text-primary"></i> Primary CTA Button</h5>
                                                    <div class="row">
                                                        <div class="col-xs-6">
                                                            <input type="text" name="hero_btn_text" class="form-control" placeholder="Label" value="<?php echo htmlspecialchars($settings_data['hero_btn_text'] ?? 'Shop Now'); ?>">
                                                        </div>
                                                        <div class="col-xs-6">
                                                            <input type="text" name="hero_btn_url" class="form-control" placeholder="URL" value="<?php echo htmlspecialchars($settings_data['hero_btn_url'] ?? 'product-category.php?id=1&type=top-category'); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="panel panel-default" style="background:#f8fafc; border-radius:8px; padding:12px; margin-bottom:12px;">
                                                    <h5 style="margin-top:0; font-weight:700;"><i class="fa fa-external-link text-info"></i> Secondary CTA Button</h5>
                                                    <div class="row">
                                                        <div class="col-xs-6">
                                                            <input type="text" name="hero_btn2_text" class="form-control" placeholder="Label" value="<?php echo htmlspecialchars($settings_data['hero_btn2_text'] ?? 'Explore All'); ?>">
                                                        </div>
                                                        <div class="col-xs-6">
                                                            <input type="text" name="hero_btn2_url" class="form-control" placeholder="URL" value="<?php echo htmlspecialchars($settings_data['hero_btn2_url'] ?? 'product-category.php?id=1&type=top-category'); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="panel panel-default" style="background:#f8fafc; border-radius:8px; padding:12px;">
                                                    <h5 style="margin-top:0; font-weight:700;"><i class="fa fa-certificate text-warning"></i> Floating Badges</h5>
                                                    <div class="row">
                                                        <div class="col-xs-6">
                                                            <input type="text" name="hero_badge1_text" class="form-control" placeholder="Badge 1" value="<?php echo htmlspecialchars($settings_data['hero_badge1_text'] ?? 'Top Brands • Best Deals'); ?>">
                                                        </div>
                                                        <div class="col-xs-6">
                                                            <input type="text" name="hero_badge2_text" class="form-control" placeholder="Badge 2" value="<?php echo htmlspecialchars($settings_data['hero_badge2_text'] ?? '⭐ 4.9/5 Rating (12k+ Reviews)'); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. CATEGORIES & FEATURED PRODUCTS -->
                                <div class="box box-success" style="border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:25px;">
                                    <div class="box-header with-border" style="background:#f8fafc; padding:15px 20px;">
                                        <h3 class="box-title" style="font-weight:700; color:#1e293b;"><i class="fa fa-th text-green"></i> 3. Category & Product Sections Controllability</h3>
                                    </div>
                                    <div class="box-body" style="padding:20px;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="panel panel-default" style="border-radius:8px; padding:14px;">
                                                    <h4 style="margin-top:0; font-weight:700; color:#0f766e;"><i class="fa fa-folder-open"></i> Shop by Category Section</h4>
                                                    <div class="form-group">
                                                        <label>Section Visibility</label>
                                                        <select name="home_category_on_off" class="form-control">
                                                            <option value="1" <?php if(($settings_data['home_category_on_off'] ?? 1) == 1) echo 'selected'; ?>>Show Category Section</option>
                                                            <option value="0" <?php if(($settings_data['home_category_on_off'] ?? 1) == 0) echo 'selected'; ?>>Hide Category Section</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Title</label>
                                                        <input type="text" name="categories_title" class="form-control" value="<?php echo htmlspecialchars($settings_data['categories_title'] ?? 'Shop by Category'); ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Subtitle</label>
                                                        <input type="text" name="categories_subtitle" class="form-control" value="<?php echo htmlspecialchars($settings_data['categories_subtitle'] ?? 'Explore our wide range of popular collections'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="panel panel-default" style="border-radius:8px; padding:14px;">
                                                    <h4 style="margin-top:0; font-weight:700; color:#b45309;"><i class="fa fa-star"></i> Featured Products Section</h4>
                                                    <div class="row">
                                                        <div class="col-xs-6">
                                                            <div class="form-group">
                                                                <label>Visibility</label>
                                                                <select name="home_featured_product_on_off" class="form-control">
                                                                    <option value="1" <?php if(($settings_data['home_featured_product_on_off'] ?? 1) == 1) echo 'selected'; ?>>Show Section</option>
                                                                    <option value="0" <?php if(($settings_data['home_featured_product_on_off'] ?? 1) == 0) echo 'selected'; ?>>Hide Section</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-xs-6">
                                                            <div class="form-group">
                                                                <label>Products Count</label>
                                                                <input type="number" name="total_featured_product_home" class="form-control" value="<?php echo htmlspecialchars($settings_data['total_featured_product_home'] ?? 8); ?>" min="4" max="24" step="2">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Title</label>
                                                        <input type="text" name="featured_products_title" class="form-control" value="<?php echo htmlspecialchars($settings_data['featured_products_title'] ?? 'Featured Products'); ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Subtitle</label>
                                                        <input type="text" name="featured_products_subtitle" class="form-control" value="<?php echo htmlspecialchars($settings_data['featured_products_subtitle'] ?? 'Handpicked best sellers and top rated products'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. PROMOTIONAL DUAL BANNERS -->
                                <div class="box box-info" style="border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:25px;">
                                    <div class="box-header with-border" style="background:#f8fafc; padding:15px 20px;">
                                        <h3 class="box-title" style="font-weight:700; color:#1e293b;"><i class="fa fa-th-large text-aqua"></i> 4. Promotional Banners (Dual Poster Cards)</h3>
                                        <label class="pull-right" style="margin:0; font-weight:normal;">
                                            <input type="checkbox" name="home_welcome_on_off" value="1" <?php if(($settings_data['home_welcome_on_off'] ?? 1) == 1) echo 'checked'; ?>> Show Dual Banners
                                        </label>
                                    </div>
                                    <div class="box-body" style="padding:20px;">
                                        <div class="row">
                                            <!-- Poster 1: Electronics -->
                                            <div class="col-md-6">
                                                <div class="panel panel-default" style="border-radius:8px; border-top: 3px solid #3b82f6;">
                                                    <div class="panel-heading" style="background:#eff6ff;"><strong>Left Poster: Top Electronics</strong></div>
                                                    <div class="panel-body">
                                                        <div class="form-group">
                                                            <label>Tag / Badge</label>
                                                            <input type="text" name="promo_banner1_tag" class="form-control" value="<?php echo htmlspecialchars($settings_data['promo_banner1_tag'] ?? 'Up to 50% Off'); ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Title</label>
                                                            <input type="text" name="promo_banner1_title" class="form-control" value="<?php echo htmlspecialchars($settings_data['promo_banner1_title'] ?? 'Top Electronics'); ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Subtitle</label>
                                                            <input type="text" name="promo_banner1_subtitle" class="form-control" value="<?php echo htmlspecialchars($settings_data['promo_banner1_subtitle'] ?? 'Laptops, Phones, Accessories & More'); ?>">
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-xs-6">
                                                                <input type="text" name="promo_banner1_btn_text" class="form-control" placeholder="Button Text" value="<?php echo htmlspecialchars($settings_data['promo_banner1_btn_text'] ?? 'Shop Now'); ?>">
                                                            </div>
                                                            <div class="col-xs-6">
                                                                <input type="text" name="promo_banner1_btn_url" class="form-control" placeholder="URL" value="<?php echo htmlspecialchars($settings_data['promo_banner1_btn_url'] ?? 'product-category.php?id=4&type=top-category'); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group" style="margin-top:10px;">
                                                            <label>Upload to Supabase Storage</label>
                                                            <input type="file" name="promo1_image_file" class="form-control" accept="image/*">
                                                        </div>
                                                        <div class="form-group">
                                                            <input type="text" name="promo_banner1_image_url" class="form-control" placeholder="Or Cloud URL" value="<?php echo htmlspecialchars($settings_data['promo_banner1_image'] ?? ''); ?>">
                                                        </div>
                                                        <?php if (!empty($settings_data['promo_banner1_image'])): ?>
                                                            <div style="background:#f8fafc; padding:8px; border-radius:6px; text-align:center;">
                                                                <img src="<?php echo htmlspecialchars($settings_data['promo_banner1_image']); ?>" style="max-height:90px; max-width:100%; border-radius:4px; object-fit:contain;">
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Poster 2: Fashion -->
                                            <div class="col-md-6">
                                                <div class="panel panel-default" style="border-radius:8px; border-top: 3px solid #f59e0b;">
                                                    <div class="panel-heading" style="background:#fffbeb;"><strong>Right Poster: Fresh Styles</strong></div>
                                                    <div class="panel-body">
                                                        <div class="form-group">
                                                            <label>Tag / Badge</label>
                                                            <input type="text" name="promo_banner2_tag" class="form-control" value="<?php echo htmlspecialchars($settings_data['promo_banner2_tag'] ?? 'Trending Deals'); ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Title</label>
                                                            <input type="text" name="promo_banner2_title" class="form-control" value="<?php echo htmlspecialchars($settings_data['promo_banner2_title'] ?? 'Fresh Styles For You'); ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Subtitle</label>
                                                            <input type="text" name="promo_banner2_subtitle" class="form-control" value="<?php echo htmlspecialchars($settings_data['promo_banner2_subtitle'] ?? 'Fashion, Footwear & Accessories'); ?>">
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-xs-6">
                                                                <input type="text" name="promo_banner2_btn_text" class="form-control" placeholder="Button Text" value="<?php echo htmlspecialchars($settings_data['promo_banner2_btn_text'] ?? 'Shop Now'); ?>">
                                                            </div>
                                                            <div class="col-xs-6">
                                                                <input type="text" name="promo_banner2_btn_url" class="form-control" placeholder="URL" value="<?php echo htmlspecialchars($settings_data['promo_banner2_btn_url'] ?? 'product-category.php?id=1&type=top-category'); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group" style="margin-top:10px;">
                                                            <label>Upload to Supabase Storage</label>
                                                            <input type="file" name="promo2_image_file" class="form-control" accept="image/*">
                                                        </div>
                                                        <div class="form-group">
                                                            <input type="text" name="promo_banner2_image_url" class="form-control" placeholder="Or Cloud URL" value="<?php echo htmlspecialchars($settings_data['promo_banner2_image'] ?? ''); ?>">
                                                        </div>
                                                        <?php if (!empty($settings_data['promo_banner2_image'])): ?>
                                                            <div style="background:#f8fafc; padding:8px; border-radius:6px; text-align:center;">
                                                                <img src="<?php echo htmlspecialchars($settings_data['promo_banner2_image']); ?>" style="max-height:90px; max-width:100%; border-radius:4px; object-fit:contain;">
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. TRUST & GUARANTEES BAR -->
                                <div class="box box-success" style="border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:25px;">
                                    <div class="box-header with-border" style="background:#f8fafc; padding:15px 20px;">
                                        <h3 class="box-title" style="font-weight:700; color:#1e293b;"><i class="fa fa-shield text-green"></i> 5. Trust & Guarantees Value Bar</h3>
                                        <label class="pull-right" style="margin:0; font-weight:normal;">
                                            <input type="checkbox" name="home_service_on_off" value="1" <?php if(($settings_data['home_service_on_off'] ?? 1) == 1) echo 'checked'; ?>> Show Trust Bar
                                        </label>
                                    </div>
                                    <div class="box-body" style="padding:20px;">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label><i class="fa fa-truck text-primary"></i> Item 1</label>
                                                    <input type="text" name="trust_item1_title" class="form-control" value="<?php echo htmlspecialchars($settings_data['trust_item1_title'] ?? 'Free Shipping'); ?>" placeholder="Title">
                                                    <input type="text" name="trust_item1_desc" class="form-control" style="margin-top:5px;" value="<?php echo htmlspecialchars($settings_data['trust_item1_desc'] ?? 'On orders over ৳ 2,000'); ?>" placeholder="Subtitle">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label><i class="fa fa-lock text-success"></i> Item 2</label>
                                                    <input type="text" name="trust_item2_title" class="form-control" value="<?php echo htmlspecialchars($settings_data['trust_item2_title'] ?? 'Secure Payment'); ?>" placeholder="Title">
                                                    <input type="text" name="trust_item2_desc" class="form-control" style="margin-top:5px;" value="<?php echo htmlspecialchars($settings_data['trust_item2_desc'] ?? '100% secure payment'); ?>" placeholder="Subtitle">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label><i class="fa fa-refresh text-warning"></i> Item 3</label>
                                                    <input type="text" name="trust_item3_title" class="form-control" value="<?php echo htmlspecialchars($settings_data['trust_item3_title'] ?? 'Easy Returns'); ?>" placeholder="Title">
                                                    <input type="text" name="trust_item3_desc" class="form-control" style="margin-top:5px;" value="<?php echo htmlspecialchars($settings_data['trust_item3_desc'] ?? '30-day return policy'); ?>" placeholder="Subtitle">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label><i class="fa fa-headphones text-info"></i> Item 4</label>
                                                    <input type="text" name="trust_item4_title" class="form-control" value="<?php echo htmlspecialchars($settings_data['trust_item4_title'] ?? '24/7 Support'); ?>" placeholder="Title">
                                                    <input type="text" name="trust_item4_desc" class="form-control" style="margin-top:5px;" value="<?php echo htmlspecialchars($settings_data['trust_item4_desc'] ?? "We're here to help"); ?>" placeholder="Subtitle">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="box-footer" style="padding:15px 20px; background:#f8fafc; text-align:right;">
                                        <button type="submit" name="form_home_features" class="btn btn-primary btn-lg" style="border-radius:25px; padding:8px 32px; font-weight:700;">
                                            <i class="fa fa-save"></i> Save All Customizations
                                        </button>
                                    </div>
                                </div>
                            
                        </div>


                        
                        <!-- TAB: PAGE SETTINGS (About Us, FAQ, Contact) -->
                        <div class="tab-pane" id="tab_page_settings">
                            <div style="display:flex; justify-content:space-between; align-items:center; background:#f0fdf4; border:1px solid #bbf7d0; padding:14px 20px; border-radius:10px; margin-bottom:25px;">
                                <div>
                                    <h4 style="margin:0 0 4px 0; color:#166534; font-weight:700;"><i class="fa fa-file-text-o"></i> Page Settings</h4>
                                    <p style="margin:0; font-size:13px; color:#15803d;">Manage content, page banners, and SEO metadata for About Us, FAQ, and Contact pages.</p>
                                </div>
                                <div>
                                    <a href="page.php" class="btn btn-default" style="border-radius:20px; font-weight:600; padding:6px 16px;">
                                        <i class="fa fa-external-link"></i> Standalone View
                                    </a>
                                </div>
                            </div>

                            <div class="nav-tabs-custom" style="box-shadow:none; border: 1px solid #e2e8f0; border-radius:8px; padding:15px; margin-bottom:20px;">
                                <ul class="nav nav-pills" style="margin-bottom:20px; display:flex; gap:8px;">
                                    <li class="active"><a href="#subtab_about" data-toggle="pill" style="border-radius:20px; font-weight:600;">About Us Page</a></li>
                                    <li><a href="#subtab_faq" data-toggle="pill" style="border-radius:20px; font-weight:600;">FAQ Page</a></li>
                                    <li><a href="#subtab_contact" data-toggle="pill" style="border-radius:20px; font-weight:600;">Contact Page</a></li>
                                </ul>

                                <div class="tab-content" style="box-shadow:none; padding:10px 0;">
                                    <!-- Subtab 1: About Us -->
                                    <div class="tab-pane active" id="subtab_about">
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Page Title <span class="text-danger">*</span></label>
                                            <div class="col-sm-8">
                                                <input class="form-control" type="text" name="about_title" value="<?php echo htmlspecialchars($about_title); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Page Content <span class="text-danger">*</span></label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" name="about_content" id="editor1"><?php echo $about_content; ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Existing Banner Photo</label>
                                            <div class="col-sm-8">
                                                <?php if(!empty($about_banner) && file_exists('../assets/uploads/'.$about_banner)): ?>
                                                    <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo htmlspecialchars($about_banner); ?>" class="existing-photo" style="height:80px;"><br>
                                                <?php else: ?>
                                                    <p class="text-muted">No banner uploaded.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">New Banner Photo</label>
                                            <div class="col-sm-8">
                                                <input type="file" name="about_banner" class="form-control-file">
                                                <p class="help-block">Upload new banner image (JPG, PNG, JPEG, GIF, WEBP)</p>
                                            </div>
                                        </div>
                                        <h3 class="seo-info mt-6">About Page SEO Settings</h3>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Title</label>
                                            <div class="col-sm-8">
                                                <input class="form-control" type="text" name="about_meta_title" value="<?php echo htmlspecialchars($about_meta_title); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Keyword</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" name="about_meta_keyword" rows="3"><?php echo htmlspecialchars($about_meta_keyword); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Description</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" name="about_meta_description" rows="3"><?php echo htmlspecialchars($about_meta_description); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" class="btn btn-success" name="form_page_about">
                                                    <i class="fa fa-save"></i> Update About Page
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Subtab 2: FAQ Page -->
                                    <div class="tab-pane" id="subtab_faq">
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Page Title <span class="text-danger">*</span></label>
                                            <div class="col-sm-8">
                                                <input class="form-control" type="text" name="faq_title" value="<?php echo htmlspecialchars($faq_title); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Existing Banner Photo</label>
                                            <div class="col-sm-8">
                                                <?php if(!empty($faq_banner) && file_exists('../assets/uploads/'.$faq_banner)): ?>
                                                    <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo htmlspecialchars($faq_banner); ?>" class="existing-photo" style="height:80px;"><br>
                                                <?php else: ?>
                                                    <p class="text-muted">No banner uploaded.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">New Banner Photo</label>
                                            <div class="col-sm-8">
                                                <input type="file" name="faq_banner" class="form-control-file">
                                                <p class="help-block">Upload new banner image (JPG, PNG, JPEG, GIF, WEBP)</p>
                                            </div>
                                        </div>
                                        <h3 class="seo-info mt-6">FAQ Page SEO Settings</h3>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Title</label>
                                            <div class="col-sm-8">
                                                <input class="form-control" type="text" name="faq_meta_title" value="<?php echo htmlspecialchars($faq_meta_title); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Keyword</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" name="faq_meta_keyword" rows="3"><?php echo htmlspecialchars($faq_meta_keyword); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Description</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" name="faq_meta_description" rows="3"><?php echo htmlspecialchars($faq_meta_description); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" class="btn btn-success" name="form_page_faq">
                                                    <i class="fa fa-save"></i> Update FAQ Page
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Subtab 3: Contact Page -->
                                    <div class="tab-pane" id="subtab_contact">
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Page Title <span class="text-danger">*</span></label>
                                            <div class="col-sm-8">
                                                <input class="form-control" type="text" name="contact_title" value="<?php echo htmlspecialchars($contact_title); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Existing Banner Photo</label>
                                            <div class="col-sm-8">
                                                <?php if(!empty($contact_banner) && file_exists('../assets/uploads/'.$contact_banner)): ?>
                                                    <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo htmlspecialchars($contact_banner); ?>" class="existing-photo" style="height:80px;"><br>
                                                <?php else: ?>
                                                    <p class="text-muted">No banner uploaded.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">New Banner Photo</label>
                                            <div class="col-sm-8">
                                                <input type="file" name="contact_banner" class="form-control-file">
                                                <p class="help-block">Upload new banner image (JPG, PNG, JPEG, GIF, WEBP)</p>
                                            </div>
                                        </div>
                                        <h3 class="seo-info mt-6">Contact Page SEO Settings</h3>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Title</label>
                                            <div class="col-sm-8">
                                                <input class="form-control" type="text" name="contact_meta_title" value="<?php echo htmlspecialchars($contact_meta_title); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Keyword</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" name="contact_meta_keyword" rows="3"><?php echo htmlspecialchars($contact_meta_keyword); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="col-sm-3 control-label">Meta Description</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" name="contact_meta_description" rows="3"><?php echo htmlspecialchars($contact_meta_description); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="col-sm-offset-3 col-sm-8">
                                                <button type="submit" class="btn btn-success" name="form_page_contact">
                                                    <i class="fa fa-save"></i> Update Contact Page
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: LANGUAGE CONVERTER -->
                        <div class="tab-pane" id="tab_language_converter">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; background:#eff6ff; border:1px solid #bfdbfe; padding:16px 20px; border-radius:10px; margin-bottom:25px;">
                                <div>
                                    <h4 style="margin:0 0 4px 0; color:#1e40af; font-weight:700;"><i class="fa fa-globe"></i> Language Converter</h4>
                                    <p style="margin:0; font-size:13px; color:#3b82f6;">Customize wording, button labels, and system texts across your online store.</p>
                                </div>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <input type="text" id="langSearchBox" class="form-control" placeholder="Search phrases (e.g. Cart, Checkout, Order)..." style="width:260px; border-radius:20px;">
                                    <button type="submit" class="btn btn-success" name="form_language_settings" style="border-radius:20px; font-weight:700; padding:6px 18px;">
                                        <i class="fa fa-check"></i> Save Language
                                    </button>
                                    <a href="language.php" class="btn btn-default" style="border-radius:20px; font-weight:600; padding:6px 16px;">
                                        <i class="fa fa-external-link"></i> Full View
                                    </a>
                                </div>
                            </div>

                            <div id="langGroupsContainer">
                                <?php foreach ($lang_sections as $sec_title => $sec_items): ?>
                                    <div class="box box-solid lang-group-box" style="border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                                        <div class="box-header with-border" style="background:#f8fafc; padding:12px 18px; border-bottom:1px solid #e2e8f0; border-top-left-radius:8px; border-top-right-radius:8px;">
                                            <h4 class="box-title" style="font-weight:700; color:#334155; font-size:15px;">
                                                <i class="fa fa-folder-open-o text-primary"></i> <?php echo htmlspecialchars($sec_title); ?>
                                                <span class="badge" style="background:#e2e8f0; color:#475569; font-weight:600; margin-left:8px;"><?php echo count($sec_items); ?></span>
                                            </h4>
                                        </div>
                                        <div class="box-body" style="padding:15px 20px;">
                                            <div class="row">
                                                <?php foreach ($sec_items as $lang_id => $lang_label): ?>
                                                    <div class="col-md-6 lang-item-col" style="margin-bottom:15px;">
                                                        <label style="font-weight:600; font-size:13px; color:#475569; display:block; margin-bottom:4px;">
                                                            <?php echo htmlspecialchars($lang_label); ?> <span class="text-danger">*</span>
                                                            <small class="text-muted pull-right">ID: <?php echo $lang_id; ?></small>
                                                        </label>
                                                        <input type="text" class="form-control" name="lang_value[<?php echo $lang_id; ?>]" value="<?php echo htmlspecialchars($lang_ids[$lang_id] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div style="text-align:right; margin-top:20px; margin-bottom:30px;">
                                <button type="submit" class="btn btn-success btn-lg" name="form_language_settings" style="border-radius:25px; padding:10px 36px; font-weight:700;">
                                    <i class="fa fa-check"></i> Save Language Settings
                                </button>
                            </div>
                        </div>

                        <!-- Tab 3: Payment Gateways -->
                        <div class="tab-pane" id="tab_payment_gateways">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">Enabled Payment Methods</h3>
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label">Select Methods</label>
                                        <div class="col-sm-9">
                                            <?php
                                            $all_methods = ['Stripe', 'PayPal', 'Bank Deposit', 'Cash on Delivery', 'SSLCommerz'];
                                            foreach ($all_methods as $method) {
                                                $checked = in_array($method, $enabled_payment_methods_array) ? 'checked' : '';
                                                echo '<div class="checkbox">';
                                                echo '<label>';
                                                echo '<input type="checkbox" name="payment_methods[]" value="'.$method.'" '.$checked.'> '.$method;
                                                echo '</label>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Stripe Settings</h3>
                                    <div class="form-group">
                                        <label for="stripe_public_key" class="col-sm-3 control-label">Stripe Public Key</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="stripe_public_key" id="stripe_public_key" class="form-control" value="<?php echo htmlspecialchars($stripe_public_key); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="stripe_secret_key" class="col-sm-3 control-label">Stripe Secret Key</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="stripe_secret_key" id="stripe_secret_key" class="form-control" value="<?php echo htmlspecialchars($stripe_secret_key); ?>">
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">PayPal Settings</h3>
                                    <div class="form-group">
                                        <label for="paypal_email" class="col-sm-3 control-label">PayPal Business Email</label>
                                        <div class="col-sm-9">
                                            <input type="email" name="paypal_email" id="paypal_email" class="form-control" value="<?php echo htmlspecialchars($paypal_email); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="paypal_client_id" class="col-sm-3 control-label">PayPal Client ID</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="paypal_client_id" id="paypal_client_id" class="form-control" value="<?php echo htmlspecialchars($paypal_client_id); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="paypal_secret" class="col-sm-3 control-label">PayPal Secret</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="paypal_secret" id="paypal_secret" class="form-control" value="<?php echo htmlspecialchars($paypal_secret); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="paypal_sandbox_mode" class="col-sm-3 control-label">PayPal Sandbox Mode</label>
                                        <div class="col-sm-9">
                                            <select name="paypal_sandbox_mode" id="paypal_sandbox_mode" class="form-control w-auto">
                                                <option value="1" <?php if($paypal_sandbox_mode == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($paypal_sandbox_mode == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">SSLCommerz Settings</h3>
                                    <div class="form-group">
                                        <label for="sslcz_store_id" class="col-sm-3 control-label">SSLCommerz Store ID</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="sslcz_store_id" id="sslcz_store_id" class="form-control" value="<?php echo htmlspecialchars($sslcz_store_id); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="sslcz_store_pass" class="col-sm-3 control-label">SSLCommerz Store Password</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="sslcz_store_pass" id="sslcz_store_pass" class="form-control" value="<?php echo htmlspecialchars($sslcz_store_pass); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="sslcz_mode" class="col-sm-3 control-label">SSLCommerz Mode</label>
                                        <div class="col-sm-9">
                                            <select name="sslcz_mode" id="sslcz_mode" class="form-control w-auto">
                                                <option value="sandbox" <?php if($sslcz_mode === 'sandbox') {echo 'selected';} ?>>Sandbox</option>
                                                <option value="live" <?php if($sslcz_mode === 'live') {echo 'selected';} ?>>Live</option>
                                            </select>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Cash on Delivery (COD)</h3>
                                    <div class="form-group">
                                        <label for="cod_enabled" class="col-sm-3 control-label">Enable COD?</label>
                                        <div class="col-sm-9">
                                            <select name="cod_enabled" id="cod_enabled" class="form-control w-auto">
                                                <option value="1" <?php if($cod_enabled == 1) {echo 'selected';} ?>>Yes</option>
                                                <option value="0" <?php if($cod_enabled == 0) {echo 'selected';} ?>>No</option>
                                            </select>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Bank Deposit Information</h3>
                                    <div class="form-group">
                                        <label for="bank_detail" class="col-sm-3 control-label">Bank Information</label>
                                        <div class="col-sm-9">
                                            <textarea name="bank_detail" id="bank_detail" class="form-control" rows="5"><?php echo htmlspecialchars($bank_detail); ?></textarea>
                                            <p class="help-block">Provide bank account details for direct transfers.</p>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_payment_gateways">Update Payment Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 4: API Integrations -->
                        <div class="tab-pane" id="tab_api_integrations">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">Google Gemini API (for AI Chat)</h3>
                                    <div class="form-group">
                                        <label for="gemini_api_key" class="col-sm-3 control-label">Gemini API Key</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="gemini_api_key" id="gemini_api_key" class="form-control" value="<?php echo htmlspecialchars($gemini_api_key); ?>">
                                            <p class="help-block">Get your API key from Google AI Studio for product inquiries.</p>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Facebook Login API</h3>
                                    <div class="form-group">
                                        <label for="facebook_app_id" class="col-sm-3 control-label">Facebook App ID</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="facebook_app_id" id="facebook_app_id" class="form-control" value="<?php echo htmlspecialchars($facebook_app_id); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="facebook_app_secret" class="col-sm-3 control-label">Facebook App Secret</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="facebook_app_secret" id="facebook_app_secret" class="form-control" value="<?php echo htmlspecialchars($facebook_app_secret); ?>">
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Google Sign-in API</h3>
                                    <div class="form-group">
                                        <label for="google_client_id" class="col-sm-3 control-label">Google Client ID</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="google_client_id" id="google_client_id" class="form-control" value="<?php echo htmlspecialchars($google_client_id); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="google_client_secret" class="col-sm-3 control-label">Google Client Secret</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="google_client_secret" id="google_client_secret" class="form-control" value="<?php echo htmlspecialchars($google_client_secret); ?>">
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Twilio API (for SMS Notifications)</h3>
                                    <div class="form-group">
                                        <label for="twilio_account_sid" class="col-sm-3 control-label">Twilio Account SID</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="twilio_account_sid" id="twilio_account_sid" class="form-control" value="<?php echo htmlspecialchars($twilio_account_sid); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="twilio_auth_token" class="col-sm-3 control-label">Twilio Auth Token</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="twilio_auth_token" id="twilio_auth_token" class="form-control" value="<?php echo htmlspecialchars($twilio_auth_token); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="twilio_phone_number" class="col-sm-3 control-label">Twilio Phone Number</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="twilio_phone_number" id="twilio_phone_number" class="form-control" value="<?php echo htmlspecialchars($twilio_phone_number); ?>">
                                            <p class="help-block">e.g., +1234567890 (Your Twilio number)</p>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_api_integrations">Update API Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 5: Review & Delivery Settings -->
                        <div class="tab-pane" id="tab_review_delivery">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">Review Settings</h3>
                                    <div class="form-group">
                                        <label for="review_feature_on_off" class="col-sm-3 control-label">Enable Review Feature?</label>
                                        <div class="col-sm-9">
                                            <select name="review_feature_on_off" id="review_feature_on_off" class="form-control w-auto">
                                                <option value="1" <?php if($review_feature_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($review_feature_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Estimated Delivery Times</h3>
                                    <div class="form-group">
                                        <label for="estimated_delivery_time_local" class="col-sm-3 control-label">Local Delivery Time</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="estimated_delivery_time_local" id="estimated_delivery_time_local" class="form-control" value="<?php echo htmlspecialchars($estimated_delivery_time_local); ?>">
                                            <p class="help-block">e.g., 3-5 business days, 24-48 hours</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="estimated_delivery_time_international" class="col-sm-3 control-label">International Delivery Time</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="estimated_delivery_time_international" id="estimated_delivery_time_international" class="form-control" value="<?php echo htmlspecialchars($estimated_delivery_time_international); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_review_delivery_settings">Update Review & Delivery Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 6: SMS Settings -->
                        <div class="tab-pane" id="tab_sms">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">SMS Settings (e.g., BulkSMSBD.net)</h3>
                                    <div class="form-group">
                                        <label for="sms_feature_on_off" class="col-sm-3 control-label">Enable SMS Feature?</label>
                                        <div class="col-sm-9">
                                            <select name="sms_feature_on_off" id="sms_feature_on_off" class="form-control w-auto">
                                                <option value="1" <?php if($sms_feature_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($sms_feature_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="sms_api_key" class="col-sm-3 control-label">SMS API Key</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="sms_api_key" id="sms_api_key" class="form-control" value="<?php echo htmlspecialchars($sms_api_key); ?>">
                                            <p class="help-block">Your API key from your SMS gateway provider.</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="sms_sender_id" class="col-sm-3 control-label">SMS Sender ID</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="sms_sender_id" id="sms_sender_id" class="form-control" value="<?php echo htmlspecialchars($sms_sender_id); ?>">
                                            <p class="help-block">Your Sender ID (e.g., your brand name, usually 11 characters).</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_sms_settings">Update SMS Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 7: Banner Settings -->
                        <div class="tab-pane" id="tab_banners">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">Page Banners</h3>
                                    <?php
                                    $banner_fields_map = [
                                        'banner_login' => 'Login Page Banner',
                                        'banner_registration' => 'Registration Page Banner',
                                        'banner_forget_password' => 'Forget Password Page Banner',
                                        'banner_reset_password' => 'Reset Password Page Banner',
                                        'banner_search' => 'Search Result Page Banner',
                                        'banner_cart' => 'Cart Page Banner',
                                        'banner_checkout' => 'Checkout Page Banner',
                                        'banner_product_category' => 'Product Category Page Banner',
                                        'banner_blog' => 'Blog Page Banner',
                                        'banner_faq' => 'FAQ Page Banner',
                                        'banner_contact' => 'Contact Page Banner',
                                        'banner_payment' => 'Payment Page Banner',
                                        'banner_customer_panel' => 'Customer Panel Banner',
                                        'banner_about' => 'About Us Page Banner',
                                        'banner_terms' => 'Terms & Conditions Page Banner',
                                        'banner_privacy' => 'Privacy Policy Page Banner',
                                        'banner_shipping' => 'Shipping Policy Page Banner',
                                        'banner_return_policy' => 'Return Policy Page Banner',
                                        'banner_photo_gallery' => 'Photo Gallery Page Banner',
                                        'banner_team' => 'Team Page Banner',
                                    ];

                                    foreach ($banner_fields_map as $field_name => $label): 
                                        $current_banner = $settings_data[$field_name] ?? '';
                                        $banner_src = '';
                                        if (!empty($current_banner)) {
                                            if (strpos($current_banner, 'http://') === 0 || strpos($current_banner, 'https://') === 0) {
                                                $banner_src = $current_banner;
                                            } elseif (file_exists('../assets/uploads/' . $current_banner)) {
                                                $banner_src = BASE_URL . 'assets/uploads/' . htmlspecialchars($current_banner);
                                            }
                                        }
                                    ?>
                                        <div class="form-group" style="background: #fbfcfe; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                                            <label for="<?php echo $field_name; ?>" class="col-sm-3 control-label">
                                                <strong><?php echo $label; ?></strong>
                                                <?php if ($field_name === 'banner_login' || $field_name === 'banner_registration'): ?>
                                                    <br><span class="label label-warning" style="display: inline-block; margin-top: 4px; font-size: 11px; padding: 3px 8px; border-radius: 4px;">Auth Side 3D Banner</span>
                                                <?php endif; ?>
                                            </label>
                                            <div class="col-sm-9">
                                                <?php if (!empty($banner_src)): ?>
                                                    <div style="margin-bottom: 12px;">
                                                        <img src="<?php echo htmlspecialchars($banner_src); ?>" alt="<?php echo $label; ?>" style="max-height: 120px; max-width: 280px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #cbd5e1; object-fit: contain; background: #ffffff; padding: 4px;">
                                                        <div style="font-size: 11px; color: #64748b; margin-top: 4px; word-break: break-all;">
                                                            <i class="fa fa-cloud" style="color: #3b82f6;"></i> <?php echo htmlspecialchars($banner_src); ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted" style="margin-bottom: 8px;"><i class="fa fa-info-circle"></i> No <?php echo strtolower($label); ?> uploaded yet.</p>
                                                <?php endif; ?>

                                                <div class="row">
                                                    <div class="col-sm-6" style="margin-bottom: 8px;">
                                                        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #475569; display: block; margin-bottom: 4px;">
                                                            <i class="fa fa-upload"></i> Upload to Supabase Storage
                                                        </label>
                                                        <input type="file" name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="form-control-file" accept="image/*">
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #475569; display: block; margin-bottom: 4px;">
                                                            <i class="fa fa-link"></i> Or Direct Supabase CDN URL
                                                        </label>
                                                        <input type="text" name="<?php echo $field_name; ?>_url" value="<?php echo htmlspecialchars($current_banner); ?>" class="form-control input-sm" placeholder="https://...supabase.co/storage/v1/object/public/storefront/assets/...">
                                                    </div>
                                                </div>
                                                <p class="help-block" style="font-size: 11px; margin-top: 4px; color: #94a3b8;">
                                                    Saved to Supabase Storage bucket <code>storefront/assets/</code>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_banner_settings">Update Banner Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 8: Social Media -->
                        <div class="tab-pane" id="tab_social_media">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">Social Media Links</h3>
                                    <div class="form-group">
                                        <label for="facebook_url" class="col-sm-3 control-label">Facebook URL</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="facebook_url" id="facebook_url" class="form-control" value="<?php echo htmlspecialchars($facebook_url); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="twitter_url" class="col-sm-3 control-label">Twitter URL</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="twitter_url" id="twitter_url" class="form-control" value="<?php echo htmlspecialchars($twitter_url); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="linkedin_url" class="col-sm-3 control-label">LinkedIn URL</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="linkedin_url" id="linkedin_url" class="form-control" value="<?php echo htmlspecialchars($linkedin_url); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="instagram_url" class="col-sm-3 control-label">Instagram URL</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="instagram_url" id="instagram_url" class="form-control" value="<?php echo htmlspecialchars($instagram_url); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="youtube_url" class="col-sm-3 control-label">YouTube URL</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="youtube_url" id="youtube_url" class="form-control" value="<?php echo htmlspecialchars($youtube_url); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_social_settings">Update Social Media Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 9: Email Settings -->
                        <div class="tab-pane" id="tab_email">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">Outgoing Email Configuration</h3>
                                    <div class="form-group">
                                        <label for="smtp_from_name" class="col-sm-3 control-label">Email From Name</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="smtp_from_name" id="smtp_from_name" class="form-control" value="<?php echo htmlspecialchars($smtp_from_name); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_from_email" class="col-sm-3 control-label">Email From Email</label>
                                        <div class="col-sm-9">
                                            <input type="email" name="smtp_from_email" id="smtp_from_email" class="form-control" value="<?php echo htmlspecialchars($smtp_from_email); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="email_method" class="col-sm-3 control-label">Email Method</label>
                                        <div class="col-sm-9">
                                            <select name="email_method" id="email_method" class="form-control w-auto">
                                                <option value="PHP Mail" <?php if($email_method == 'PHP Mail') {echo 'selected';} ?>>PHP Mail</option>
                                                <option value="SMTP" <?php if($email_method == 'SMTP') {echo 'selected';} ?>>SMTP</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_host" class="col-sm-3 control-label">SMTP Host</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="smtp_host" id="smtp_host" class="form-control" value="<?php echo htmlspecialchars($smtp_host); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_port" class="col-sm-3 control-label">SMTP Port</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="smtp_port" id="smtp_port" class="form-control" value="<?php echo htmlspecialchars($smtp_port); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_username" class="col-sm-3 control-label">SMTP Username</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="smtp_username" id="smtp_username" class="form-control" value="<?php echo htmlspecialchars($smtp_username); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_password" class="col-sm-3 control-label">SMTP Password</label>
                                        <div class="col-sm-9">
                                            <input type="password" name="smtp_password" id="smtp_password" class="form-control" value="<?php echo htmlspecialchars($smtp_password); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_encryption" class="col-sm-3 control-label">SMTP Encryption</label>
                                        <div class="col-sm-9">
                                            <select name="smtp_encryption" id="smtp_encryption" class="form-control w-auto">
<option value="NONE" <?php if(strtoupper($smtp_encryption) == 'NONE') echo 'selected'; ?>>None</option>
<option value="TLS" <?php if(strtoupper($smtp_encryption) == 'TLS') echo 'selected'; ?>>TLS</option>
<option value="SSL" <?php if(strtoupper($smtp_encryption) == 'SSL') echo 'selected'; ?>>SSL</option>        </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_email_settings">Update Outgoing Email Settings</button>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Email Content Settings</h3>
                                    <div class="form-group">
                                        <label for="receive_email" class="col-sm-3 control-label">Contact Form Recipient Email</label>
                                        <div class="col-sm-9">
                                            <input type="email" class="form-control" name="receive_email" id="receive_email" value="<?php echo htmlspecialchars($receive_email); ?>">
                                            <p class="help-block">Email address where contact form submissions will be sent.</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="receive_email_subject" class="col-sm-3 control-label">Contact Email Subject</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" name="receive_email_subject" id="receive_email_subject" value="<?php echo htmlspecialchars($receive_email_subject); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="receive_email_thank_you_message" class="col-sm-3 control-label">Contact Email Thank You Message</label>
                                        <div class="col-sm-9">
                                            <textarea class="form-control" name="receive_email_thank_you_message" id="receive_email_thank_you_message" rows="5"><?php echo htmlspecialchars($receive_email_thank_you_message); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="forget_password_message" class="col-sm-3 control-label">Forget Password Email Message</label>
                                        <div class="col-sm-9">
                                            <textarea class="form-control" name="forget_password_message" id="forget_password_message" rows="5"><?php echo htmlspecialchars($forget_password_message); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_email_content_settings">Update Email Content Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 10: Footer Settings -->
                        <div class="tab-pane" id="tab_footer">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">Footer Content</h3>
                                    <div class="form-group">
                                        <label for="copyright_text" class="col-sm-3 control-label">Copyright Text</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="copyright_text" id="copyright_text" class="form-control" value="<?php echo htmlspecialchars($copyright_text); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="footer_about_us" class="col-sm-3 control-label">Footer About Us (Short)</label>
                                        <div class="col-sm-9">
                                            <textarea name="footer_about_us" id="footer_about_us" class="form-control" rows="5"><?php echo htmlspecialchars($footer_about_us); ?></textarea>
                                            <p class="help-block">A short description about your company for the footer.</p>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Contact Information in Footer</h3>
                                    <div class="form-group">
                                        <label for="contact_address" class="col-sm-3 control-label">Contact Address</label>
                                        <div class="col-sm-9">
                                            <textarea class="form-control" name="contact_address" id="contact_address" rows="5"><?php echo htmlspecialchars($contact_address); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="contact_map_iframe" class="col-sm-3 control-label">Contact Map iFrame</label>
                                        <div class="col-sm-9">
                                            <textarea class="form-control" name="contact_map_iframe" id="contact_map_iframe" rows="5"><?php echo htmlspecialchars($contact_map_iframe); ?></textarea>
                                            <p class="help-block">Embed code for Google Map or similar.</p>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Payment Verified Image</h3>
                                    <div class="form-group">
                                        <label for="payment_verified_image" class="col-sm-3 control-label">Payment Methods Image</label>
                                        <div class="col-sm-9">
                                            <?php if (!empty($payment_verified_image) && file_exists('../assets/uploads/'.$payment_verified_image)): ?>
                                                <img src="<?php echo BASE_URL; ?>assets/uploads/<?php echo htmlspecialchars($payment_verified_image); ?>" alt="Payment Verified" class="existing-photo" style="max-width:200px;"><br>
                                            <?php else: ?>
                                                <p class="text-gray-500">No image uploaded.</p>
                                            <?php endif; ?>
                                            <input type="file" name="payment_verified_image" id="payment_verified_image" class="form-control-file">
                                            <p class="help-block">Upload an image showing accepted payment methods (JPG, PNG, JPEG, GIF)</p>
                                        </div>
                                    </div>

                                    <h3 class="seo-info mt-8">Visibility & Display Settings</h3>
                                    <div class="form-group">
                                        <label for="mobile_footer_on_off" class="col-sm-3 control-label">Footer on Mobile Screen</label>
                                        <div class="col-sm-9">
                                            <div class="radio radio-inline">
                                                <label>
                                                    <input type="radio" name="mobile_footer_on_off" value="0" <?php if($mobile_footer_on_off == 0) {echo 'checked';} ?>> <strong>Hide on Mobile (Recommended)</strong>
                                                </label>
                                            </div>
                                            <div class="radio radio-inline">
                                                <label>
                                                    <input type="radio" name="mobile_footer_on_off" value="1" <?php if($mobile_footer_on_off == 1) {echo 'checked';} ?>> Show on Mobile
                                                </label>
                                            </div>
                                            <p class="help-block">Choose whether the footer is displayed on mobile screens. Hiding keeps the mobile screen clean and app-like.</p>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="related_products_on_off" class="col-sm-3 control-label">Related Products Section</label>
                                        <div class="col-sm-9">
                                            <div class="radio radio-inline">
                                                <label>
                                                    <input type="radio" name="related_products_on_off" value="1" <?php if($related_products_on_off == 1) {echo 'checked';} ?>> <strong>Show Related Products (Default)</strong>
                                                </label>
                                            </div>
                                            <div class="radio radio-inline">
                                                <label>
                                                    <input type="radio" name="related_products_on_off" value="0" <?php if($related_products_on_off == 0) {echo 'checked';} ?>> Hide Related Products
                                                </label>
                                            </div>
                                            <p class="help-block">Controls whether the related products card row appears on the product details page.</p>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="col-sm-offset-3 col-sm-9">
                                            <button type="submit" class="btn btn-success" name="form_footer_settings">Update Footer Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 11: Ads Settings -->
                        <div class="tab-pane" id="tab_ads">
                            <div class="box box-info">
                                <div class="box-body">
                                    <h3 class="seo-info">Advertisement Section Visibility</h3>
                                    <div class="form-group">
                                        <label for="ads_above_welcome_on_off" class="col-sm-3 control-label">Above Welcome Section</label>
                                        <div class="col-sm-9">
                                            <select name="ads_above_welcome_on_off" id="ads_above_welcome_on_off" class="form-control w-auto">
                                                <option value="1" <?php if($ads_above_welcome_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($ads_above_welcome_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="ads_above_featured_product_on_off" class="col-sm-3 control-label">Above Featured Product Section</label>
                                        <div class="col-sm-9">
                                            <select name="ads_above_featured_product_on_off" id="ads_above_featured_product_on_off" class="form-control w-auto">
                                                <option value="1" <?php if($ads_above_featured_product_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($ads_above_featured_product_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="ads_above_latest_product_on_off" class="col-sm-3 control-label">Above Latest Product Section</label>
                                        <div class="col-sm-9">
                                            <select name="ads_above_latest_product_on_off" id="ads_above_latest_product_on_off" class="form-control w-auto">
                                                <option value="1" <?php if($ads_above_latest_product_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($ads_above_latest_product_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="ads_above_popular_product_on_off" class="col-sm-3 control-label">Above Popular Product Section</label>
                                        <div class="col-sm-9">
                                            <select name="ads_above_popular_product_on_off" id="ads_above_popular_product_on_off" class="form-control w-auto">
                                                <option value="1" <?php if($ads_above_popular_product_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($ads_above_popular_product_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="ads_above_testimonial_on_off" class="col-sm-3 control-label">Above Testimonial Section</label>
                                        <div class="col-sm-9">
                                            <select name="ads_above_testimonial_on_off" id="ads_above_testimonial_on_off" class="form-control w-auto">
                                                <option value="1" <?php if($ads_above_testimonial_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($ads_above_testimonial_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="ads_category_sidebar_on_off" class="col-sm-3 control-label">Category Page Sidebar Ads</label>
                                        <div class="col-sm-9">
                                            <select name="ads_category_sidebar_on_off" id="ads_category_sidebar_on_off" class="form-control w-auto">
                                                <option value="1" <?php if($ads_category_sidebar_on_off == 1) {echo 'selected';} ?>>On</option>
                                                <option value="0" <?php if($ads_category_sidebar_on_off == 0) {echo 'selected';} ?>>Off</option>
                                            </select>
                                        </div>
                                    </div>                                    
                                    <div class="form-group">
                                        <label for="" class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-success pull-left" name="form_ads_settings">Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>



                    </div>
                </div>

                

            </form>
        </div>
    </div>

</section>

<script>
$(document).ready(function() {
    // Keep active tab on page refresh / hash change
    var hash = window.location.hash;
    if (hash) {
        $('.nav-tabs a[href="' + hash + '"]').tab('show');
    }
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        if(history.pushState) {
            history.pushState(null, null, e.target.hash);
        } else {
            window.location.hash = e.target.hash;
        }
    });

    // Language Search / Filter
    $('#langSearchBox').on('keyup', function() {
        var query = $(this).val().toLowerCase().trim();
        if (query === '') {
            $('.lang-item-col').show();
            $('.lang-group-box').show();
            return;
        }
        $('.lang-item-col').each(function() {
            var label = $(this).find('label').text().toLowerCase();
            var val = $(this).find('input').val().toLowerCase();
            if (label.indexOf(query) > -1 || val.indexOf(query) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        $('.lang-group-box').each(function() {
            var visibleCount = $(this).find('.lang-item-col:visible').length;
            if (visibleCount > 0) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});
</script>

<?php require_once('footer.php'); ?>
