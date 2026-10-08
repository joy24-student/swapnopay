<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';
require_once __DIR__ . '/admin/inc/CSRF_Protect.php';
$csrf = new CSRF_Protect();

// Reject raw/unverified social login attempts (must go through Supabase auth flow)
if (isset($_POST['social_login_email'])) {
    http_response_code(403);
    exit('Use email and password or Google Sign-In.');
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['customer'])) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

// Fetch banner & settings for desktop side panel
$stmt = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$is_supabase_ready = defined('SUPABASE_URL') && !empty(SUPABASE_URL) && defined('SUPABASE_ANON_KEY') && !empty(SUPABASE_ANON_KEY);
$show_google_login = $is_supabase_ready && (isset($settings['show_google_login']) ? (int)$settings['show_google_login'] : 1);
$show_facebook_login = $is_supabase_ready && (isset($settings['show_facebook_login']) ? (int)$settings['show_facebook_login'] : 1);
$show_social_buttons = ($show_google_login || $show_facebook_login);

$banner_login = !empty($settings['banner_login']) 
    ? $settings['banner_login'] 
    : 'assets/uploads/banner_login.jpg';

if (!str_starts_with($banner_login, 'http')) {
    $banner_login = 'assets/uploads/' . ltrim($banner_login, '/');
}

$error_message = '';
$success_message = '';

// Handle standard email/password or phone login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['form_login']) || isset($_POST['form1']))) {
    if (!$csrf->isTokenValid($_POST['_csrf'] ?? '')) {
        $error_message = 'Invalid or expired CSRF token. Please refresh the page.';
    } else {
        $login_identifier = trim((string)($_POST['cust_email'] ?? ''));
        $password = (string)($_POST['cust_password'] ?? '');

        if (empty($login_identifier) || empty($password)) {
            $error_message = 'Please enter both your email/phone and password.';
        } else {
            $email_lower = strtolower($login_identifier);
            $stmt = $pdo->prepare("SELECT * FROM tbl_customer WHERE lower(cust_email) = ? OR (cust_phone != '' AND cust_phone = ?) LIMIT 1");
            $stmt->execute([$email_lower, $login_identifier]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                $error_message = 'No account found with this email or phone number. Please sign up.';
            } else {
                $password_valid = false;

                // Verify with Bcrypt
                if (!empty($customer['cust_password']) && password_verify($password, $customer['cust_password'])) {
                    $password_valid = true;
                } elseif (!empty($customer['cust_password']) && $customer['cust_password'] === md5($password)) {
                    // Migration for legacy md5 passwords
                    $password_valid = true;
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $updateHash = $pdo->prepare("UPDATE tbl_customer SET cust_password = ? WHERE cust_id = ?");
                    $updateHash->execute([$newHash, $customer['cust_id']]);
                }

                if (!$password_valid) {
                    $error_message = 'The password you entered is incorrect.';
                } elseif ((int)($customer['cust_status'] ?? 0) === 0) {
                    $error_message = 'Your account is currently inactive. Please check your verification or contact support.';
                } else {
                    // Successful login
                    unset($customer['cust_password']);
                    $_SESSION['customer'] = $customer;

                    if (defined('MERCHANT_ID') && MERCHANT_ID) {
                        $_SESSION['shop_merchant_id'] = MERCHANT_ID;
                    }

                    if (function_exists('loadCartFromDatabase')) {
                        loadCartFromDatabase($pdo, $customer['cust_id']);
                    }

                    $redirectUrl = !empty($_SESSION['cart_p_id']) ? 'checkout.php' : 'dashboard.php';
                    header("Location: " . BASE_URL . $redirectUrl);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <style>
        :root {
            --primary: #fab802;
            --primary-hover: #e5a700;
            --text-dark: #0f172a;
            --heading-navy: #0a2569;
            --text-muted: #64748b;
            --text-sub: #596b88;
            --border-color: #e2e8f0;
            --bg-page: #f8fafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        /* =====================================================================
           1. ORIGINAL DESKTOP LAYOUT (> 768px)
           ===================================================================== */
        body {
            background-color: var(--bg-page);
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 24px 24px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .mobile-auth-container {
            display: none;
        }

        .auth-container {
            width: 100%;
            max-width: 960px;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.08), 0 0 1px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
            overflow: hidden;
            display: flex;
            flex-direction: row;
            min-height: 640px;
            transition: all 0.3s ease;
        }

        /* LEFT FORM PANEL */
        .form-panel {
            flex: 1.08;
            padding: 44px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #ffffff;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            text-decoration: none;
            font-size: 23px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.4px;
        }

        .brand-logo .logo-bag {
            width: 30px;
            height: 30px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(250, 184, 2, 0.35);
        }

        .brand-logo span {
            color: var(--primary);
        }

        .header-section {
            margin-top: 24px;
            margin-bottom: 22px;
        }

        .header-section h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.6px;
            margin-bottom: 6px;
        }

        .header-section p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13.5px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 7px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper svg.field-icon {
            position: absolute;
            left: 14px;
            width: 18px;
            height: 18px;
            color: #94a3b8;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-wrapper input {
            width: 100%;
            height: 48px;
            padding: 0 16px 0 44px;
            background: #f8fafc;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            font-size: 14.5px;
            color: var(--text-dark);
            outline: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-wrapper input.with-toggle {
            padding-right: 44px;
        }

        .input-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3.5px rgba(250, 184, 2, 0.18);
        }

        .input-wrapper input:focus + svg.field-icon {
            color: var(--primary);
        }

        .btn-toggle-eye {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .btn-toggle-eye:hover {
            color: var(--text-dark);
        }

        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 14px;
            margin-bottom: 22px;
            font-size: 13px;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            cursor: pointer;
            font-weight: 500;
            user-select: none;
        }

        .remember-label input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: var(--primary);
            border-radius: 4px;
            cursor: pointer;
        }

        .forgot-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* PRIMARY BUTTON */
        .btn-cta {
            width: 100%;
            height: 48px;
            background: var(--primary);
            color: #1e293b;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 14px rgba(250, 184, 2, 0.35);
        }

        .btn-cta:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(250, 184, 2, 0.45);
        }

        .btn-cta:active {
            transform: translateY(0);
        }

        .btn-cta .arrow {
            font-size: 18px;
            transition: transform 0.2s;
        }

        .btn-cta:hover .arrow {
            transform: translateX(3px);
        }

        /* DIVIDER */
        .divider-row {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0 16px;
            color: #94a3b8;
            font-size: 12.5px;
        }

        .divider-row::before,
        .divider-row::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }

        .divider-row span {
            padding: 0 14px;
        }

        /* GOOGLE BUTTON */
        .btn-google {
            width: 100%;
            height: 48px;
            background: #ffffff;
            color: #334155;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .social-btn-container {
            display: flex;
            gap: 12px;
            width: 100%;
        }

        .btn-facebook {
            width: 100%;
            height: 48px;
            background: #ffffff;
            color: #1877F2;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-facebook:hover {
            background: #f0f4ff;
            border-color: #93c5fd;
            color: #0b57d0;
        }

        .btn-google:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .footer-note {
            margin-top: 22px;
            text-align: center;
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .footer-note a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
            margin-left: 3px;
        }

        .footer-note a:hover {
            text-decoration: underline;
        }

        /* RIGHT DISPLAY PANEL */
        .side-panel {
            flex: 0.95;
            background: linear-gradient(180deg, #f0f7ff 0%, #e0f2fe 100%);
            padding: 40px 36px 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            border-left: 1px solid rgba(226, 232, 240, 0.7);
        }

        .slogan-box {
            text-align: right;
            padding-right: 10px;
            z-index: 2;
        }

        .slogan-title {
            font-family: 'Caveat', cursive, sans-serif;
            font-size: 29px;
            font-weight: 700;
            color: #1e3a8a;
            line-height: 1.05;
            transform: rotate(-6deg);
            display: inline-block;
        }

        .slogan-underline {
            display: block;
            margin-left: auto;
            margin-top: 2px;
            transform: rotate(-6deg);
        }

        .banner-stage {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            padding: 10px 0;
            z-index: 1;
        }

        .banner-stage img {
            max-width: 96%;
            max-height: 320px;
            object-fit: contain;
            filter: drop-shadow(0 15px 25px rgba(30, 58, 138, 0.1));
            animation: softFloat 4s ease-in-out infinite alternate;
        }

        @keyframes softFloat {
            0% { transform: translateY(0); }
            100% { transform: translateY(-8px); }
        }

        /* TRUST ITEMS */
        .trust-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            padding-top: 18px;
            border-top: 1px solid rgba(191, 219, 254, 0.6);
            z-index: 2;
        }

        .trust-item {
            text-align: center;
        }

        .trust-icon {
            width: 36px;
            height: 36px;
            margin: 0 auto 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e293b;
        }

        .trust-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.25;
        }

        .trust-sub {
            font-size: 10.5px;
            font-weight: 500;
            color: #64748b;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* =====================================================================
           2. MOBILE PIXEL-PERFECT LAYOUT (<= 768px ONLY)
           ===================================================================== */
        @media (max-width: 768px) {
            body {
                background-color: #ffffff;
                background-image: none;
                min-height: 100vh;
                min-height: 100dvh;
                padding: 0;
                display: block;
            }

            .auth-container {
                display: none !important;
            }

            .mobile-auth-container {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                width: 100%;
                min-height: 100vh;
                min-height: 100dvh;
                background: #ffffff;
                padding: 36px 20px 24px;
            }

            .m-brand-logo {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                text-decoration: none;
                margin-bottom: 32px;
            }

            .m-brand-title {
                font-size: 29px;
                font-weight: 800;
                letter-spacing: -0.5px;
                line-height: 1;
            }

            .m-brand-shop { color: #0f172a; }
            .m-brand-next { color: #fab802; }

            .m-heading-section {
                text-align: left;
                margin-bottom: 24px;
            }

            .m-heading-section h1 {
                font-size: 28px;
                font-weight: 800;
                color: var(--heading-navy);
                letter-spacing: -0.6px;
                line-height: 1.2;
                margin-bottom: 6px;
            }

            .m-heading-section p {
                font-size: 15px;
                font-weight: 500;
                color: var(--text-sub);
                line-height: 1.4;
            }

            .m-input-group {
                margin-bottom: 15px;
            }

            .m-input-wrapper {
                position: relative;
                display: flex;
                align-items: center;
                width: 100%;
            }

            .m-input-wrapper svg.m-field-icon {
                position: absolute;
                left: 16px;
                width: 20px;
                height: 20px;
                color: #475569;
                pointer-events: none;
            }

            .m-input-wrapper input {
                width: 100%;
                height: 54px;
                padding: 0 18px 0 48px;
                background: #f8fafc;
                border: 1.5px solid var(--border-color);
                border-radius: 16px;
                font-size: 15px;
                font-weight: 500;
                color: var(--text-dark);
                outline: none;
                transition: all 0.2s ease;
            }

            .m-input-wrapper input::placeholder {
                color: #8c9bb0;
                font-weight: 400;
            }

            .m-input-wrapper input:focus {
                background: #ffffff;
                border-color: var(--primary);
                box-shadow: 0 0 0 3.5px rgba(250, 184, 2, 0.18);
            }

            .m-input-wrapper input.with-toggle {
                padding-right: 48px;
            }

            .m-forgot-row {
                display: flex;
                justify-content: flex-end;
                margin-top: 4px;
                margin-bottom: 18px;
            }

            .m-forgot-link {
                font-size: 13.5px;
                font-weight: 600;
                color: var(--primary);
                text-decoration: none;
            }

            .m-btn-cta {
                width: 100%;
                height: 54px;
                background: var(--primary);
                color: var(--text-dark);
                border: none;
                border-radius: 28px;
                font-size: 16px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                cursor: pointer;
                margin-top: 6px;
                box-shadow: 0 6px 20px rgba(250, 184, 2, 0.3);
            }

            .m-divider-row {
                display: flex;
                align-items: center;
                text-align: center;
                margin: 26px 0 20px;
                color: #94a3b8;
                font-size: 13px;
                font-weight: 500;
            }

            .m-divider-row::before,
            .m-divider-row::after {
                content: '';
                flex: 1;
                border-bottom: 1.5px solid var(--border-color);
            }

            .m-divider-row span {
                padding: 0 14px;
            }

            .m-social-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 14px;
            }

            .m-btn-social {
                width: 100%;
                height: 50px;
                background: #ffffff;
                color: var(--text-dark);
                border: 1.5px solid var(--border-color);
                border-radius: 25px;
                font-size: 14.5px;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                cursor: pointer;
            }

            .m-footer-switch {
                margin-top: 32px;
                margin-bottom: 24px;
                text-align: center;
                font-size: 14px;
                font-weight: 500;
                color: var(--text-muted);
            }

            .m-footer-switch a {
                color: var(--primary);
                font-weight: 700;
                text-decoration: none;
                margin-left: 4px;
            }

            .m-trust-badge {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                margin-top: auto;
                padding-top: 16px;
                color: #64748b;
                font-size: 12.5px;
                font-weight: 500;
            }
        }
    </style>
</head>
<body>

<!-- =========================================================================
     1. DESKTOP VIEW (> 768px) — ORIGINAL 2-COLUMN LAYOUT
     ========================================================================= -->
<div class="auth-container">
    <!-- LEFT PANEL: LOGIN FORM -->
    <div class="form-panel">
        <div>
            <!-- LOGO -->
            <a href="index.php" class="brand-logo" title="Back to <?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?>">
                <div class="logo-bag">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                </div>
                <span><?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?></span>
            </a>

            <!-- HEADING -->
            <div class="header-section">
                <h1>Welcome Back</h1>
                <p>Enter your credentials to access your <?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?> account.</p>
            </div>

            <!-- NOTIFICATIONS -->
            <?php if (!empty($error_message)): ?>
                <div class="alert-box alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert-box alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- FORM -->
            <form action="" method="post" id="loginFormDesktop">
                <?php $csrf->echoInputField(); ?>

                <!-- EMAIL FIELD -->
                <div class="input-group">
                    <label for="cust_email">Email Address</label>
                    <div class="input-wrapper">
                        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                        <input type="text" name="cust_email" id="cust_email" placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['cust_email'] ?? ''); ?>" required autocomplete="username">
                    </div>
                </div>

                <!-- PASSWORD FIELD -->
                <div class="input-group">
                    <label for="cust_password">Password</label>
                    <div class="input-wrapper">
                        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" name="cust_password" id="cust_password" class="with-toggle" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="btn-toggle-eye" id="togglePasswordBtn" title="Toggle password visibility">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- REMEMBER ME & FORGOT PASSWORD -->
                <div class="options-row">
                    <label class="remember-label">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                    <a href="forget-password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <!-- CTA BUTTON -->
                <button type="submit" name="form_login" class="btn-cta" id="loginSubmitBtn">
                    <span>Login</span>
                    <span class="arrow">&rarr;</span>
                </button>
            </form>

            <?php if ($show_social_buttons): ?>
            <!-- DIVIDER -->
            <div class="divider-row">
                <span>or continue with</span>
            </div>

            <div class="social-btn-container">
                <?php if ($show_google_login): ?>
                <!-- GOOGLE AUTH BUTTON -->
                <button type="button" class="btn-google js-google-login" id="googleLoginBtn" style="<?php echo (!$show_facebook_login ? 'width: 100%;' : 'flex: 1;'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Google</span>
                </button>
                <?php endif; ?>

                <?php if ($show_facebook_login): ?>
                <!-- FACEBOOK AUTH BUTTON -->
                <button type="button" class="btn-facebook js-facebook-login" id="facebookLoginBtnDesktop" style="<?php echo (!$show_google_login ? 'width: 100%;' : 'flex: 1;'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="12" fill="#1877F2"/>
                        <path d="M14.5 12H12.7V18H10.2V12H9V9.9H10.2V8.4C10.2 7.2 10.8 5.5 13.2 5.5L15 5.5V7.5H13.7C13.1 7.5 12.7 7.8 12.7 8.5V9.9H15L14.5 12Z" fill="white"/>
                    </svg>
                    <span>Facebook</span>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- FOOTER SWITCH -->
        <div class="footer-note">
            Don't have an account? <a href="registration.php">Sign up</a>
        </div>
    </div>

    <!-- RIGHT PANEL: 3D GRAPHIC & VALUE PROPOSITIONS -->
    <div class="side-panel">
        <!-- CURSIVE SLOGAN -->
        <div class="slogan-box">
            <div class="slogan-title">Your Next<br>Favorite Find</div>
            <svg class="slogan-underline" width="95" height="12" viewBox="0 0 95 12" fill="none">
                <path d="M2 9C28 3 70 3 93 8" stroke="#fab802" stroke-width="3" stroke-linecap="round"/>
            </svg>
        </div>

        <!-- 3D GRAPHIC -->
        <div class="banner-stage">
            <img src="<?php echo htmlspecialchars($banner_login); ?>" alt="<?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?> Best Products">
        </div>

        <!-- 3 TRUST BADGES -->
        <div class="trust-grid">
            <div class="trust-item">
                <div class="trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>
                    </svg>
                </div>
                <div class="trust-label">Fast &amp; Reliable</div>
                <div class="trust-sub">Shipping</div>
            </div>

            <div class="trust-item">
                <div class="trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>
                    </svg>
                </div>
                <div class="trust-label">Secure</div>
                <div class="trust-sub">Payments</div>
            </div>

            <div class="trust-item">
                <div class="trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>
                    </svg>
                </div>
                <div class="trust-label">24/7</div>
                <div class="trust-sub">Support</div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     2. MOBILE VIEW (<= 768px) — PIXEL-PERFECT MOBILE LAYOUT
     ========================================================================= -->
<div class="mobile-auth-container">
    <div class="content-wrap">
        <!-- LOGO -->
        <a href="index.php" class="m-brand-logo" title="Back to <?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?>">
            <svg width="42" height="46" viewBox="0 0 48 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 16.5 C9 15.5 9.8 14.5 11 14.5 L37 14.5 C38.2 14.5 39 15.5 39 16.5 L36 43 C36 45 34 46.5 32 46.5 L16 46.5 C14 46.5 12 45 12 43 Z" fill="#FBBF24" stroke="#0F172A" stroke-width="2.8" stroke-linejoin="round"/>
                <path d="M19 14.5 V10 C19 7 21 5 24 5 C27 5 29 7 29 10 V14.5" stroke="#0F172A" stroke-width="2.8" stroke-linecap="round"/>
                <path d="M20 28 C21.5 31 26.5 31 28 28" stroke="#0F172A" stroke-width="2.8" stroke-linecap="round"/>
            </svg>
            <div class="m-brand-title">
                <span class="m-brand-shop"><?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?></span>
            </div>
        </a>

        <!-- HEADING -->
        <div class="m-heading-section">
            <h1>Welcome Back!</h1>
            <p>Sign in to continue shopping</p>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if (!empty($error_message)): ?>
            <div class="alert-box alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-box alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- MOBILE LOGIN FORM -->
        <form action="" method="post" id="loginFormMobile">
            <?php $csrf->echoInputField(); ?>

            <!-- EMAIL OR PHONE NUMBER -->
            <div class="m-input-group">
                <div class="m-input-wrapper">
                    <svg class="m-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                    <input type="text" name="cust_email" placeholder="Email or phone number" value="<?php echo htmlspecialchars($_POST['cust_email'] ?? ''); ?>" required autocomplete="username">
                </div>
            </div>

            <!-- PASSWORD -->
            <div class="m-input-group">
                <div class="m-input-wrapper">
                    <svg class="m-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" name="cust_password" id="m_cust_password" class="with-toggle" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="btn-toggle-eye" id="mTogglePasswordBtn" title="Toggle password visibility">
                        <svg id="mEyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- FORGOT PASSWORD -->
            <div class="m-forgot-row">
                <a href="forget-password.php" class="m-forgot-link">Forgot Password?</a>
            </div>

            <!-- CTA BUTTON -->
            <button type="submit" name="form_login" class="m-btn-cta">
                <span>Sign In</span>
                <span class="arrow">&rarr;</span>
            </button>
        </form>

        <?php if ($show_social_buttons): ?>
        <!-- DIVIDER -->
        <div class="m-divider-row">
            <span>or continue with</span>
        </div>

        <!-- SOCIAL LOGINS -->
        <div class="m-social-grid" style="<?php echo (!$show_google_login || !$show_facebook_login) ? 'grid-template-columns: 1fr;' : ''; ?>">
            <?php if ($show_google_login): ?>
            <button type="button" class="m-btn-social js-google-login">
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span>Google</span>
            </button>
            <?php endif; ?>

            <?php if ($show_facebook_login): ?>
            <button type="button" class="m-btn-social js-facebook-login" id="facebookLoginBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="12" fill="#1877F2"/>
                    <path d="M14.5 12H12.7V18H10.2V12H9V9.9H10.2V8.4C10.2 7.2 10.8 5.5 13.2 5.5L15 5.5V7.5H13.7C13.1 7.5 12.7 7.8 12.7 8.5V9.9H15L14.5 12Z" fill="white"/>
                </svg>
                <span>Facebook</span>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- FOOTER SWITCH -->
        <div class="m-footer-switch">
            Don't have an account? <a href="registration.php">Sign Up</a>
        </div>
    </div>

    <!-- BOTTOM TRUST BADGE -->
    <div class="m-trust-badge">
        <svg width="18" height="20" viewBox="0 0 20 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 1L2 4.5V10.5C2 15.8 5.4 20.7 10 21.8C14.6 20.7 18 15.8 18 10.5V4.5L10 1Z" fill="#FDE68A" stroke="#F59E0B" stroke-width="1.2"/>
            <rect x="7" y="11" width="6" height="5" rx="1" fill="#0F172A"/>
            <path d="M8 11V9.5C8 8.4 8.9 7.5 10 7.5C11.1 7.5 12 8.4 12 9.5V11" stroke="#0F172A" stroke-width="1.3" stroke-linecap="round"/>
        </svg>
        <span>Secure login &bull; Your data is protected</span>
    </div>
</div>

<script>
    function bindPasswordToggle(btnId, inputId, iconId) {
        const toggleBtn = document.getElementById(btnId);
        const pwdInput = document.getElementById(inputId);
        const eyeIcon = document.getElementById(iconId);
        if (toggleBtn && pwdInput && eyeIcon) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = pwdInput.type === 'password';
                pwdInput.type = isPassword ? 'text' : 'password';
                if (isPassword) {
                    eyeIcon.innerHTML = '<path d="m2 2 20 20"/><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>';
                } else {
                    eyeIcon.innerHTML = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
                }
            });
        }
    }
    bindPasswordToggle('togglePasswordBtn', 'cust_password', 'eyeIcon');
    bindPasswordToggle('mTogglePasswordBtn', 'm_cust_password', 'mEyeIcon');

    // Supabase Social OAuth integration
    const SUPABASE_URL = <?php echo json_encode(defined("SUPABASE_URL") ? SUPABASE_URL : ""); ?>;
    const SUPABASE_ANON_KEY = <?php echo json_encode(defined("SUPABASE_ANON_KEY") ? SUPABASE_ANON_KEY : ""); ?>;
    const supabase = (SUPABASE_URL && SUPABASE_ANON_KEY && window.supabase) ? window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY) : null;

    document.querySelectorAll('.js-google-login').forEach(googleBtn => {
        googleBtn.addEventListener('click', async function() {
            if (!supabase) {
                alert('Social login is not configured for this store.');
                return;
            }
            const origHtml = googleBtn.innerHTML;
            try {
                googleBtn.disabled = true;
                googleBtn.innerHTML = `
                    <div style="width: 18px; height: 18px; border: 2.5px solid #cbd5e1; border-top: 2.5px solid #fab802; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                    <span>Connecting...</span>
                `;
                const callbackUrl = '<?php echo BASE_URL; ?>supabase_auth_callback.php';
                const { error } = await supabase.auth.signInWithOAuth({
                    provider: 'google',
                    options: { redirectTo: callbackUrl }
                });
                if (error) {
                    alert('Google Sign-In Error: ' + error.message);
                    googleBtn.disabled = false;
                    googleBtn.innerHTML = origHtml;
                }
            } catch (err) {
                console.error(err);
                alert('Failed to initialize Google Sign-in. Please try again.');
                googleBtn.disabled = false;
                googleBtn.innerHTML = origHtml;
            }
        });
    });

    document.querySelectorAll('.js-facebook-login').forEach(fbBtn => {
        fbBtn.addEventListener('click', async function() {
            if (!supabase) {
                alert('Social login is not configured for this store.');
                return;
            }
            const origHtml = fbBtn.innerHTML;
            try {
                fbBtn.disabled = true;
                fbBtn.innerHTML = `
                    <div style="width: 18px; height: 18px; border: 2.5px solid #cbd5e1; border-top: 2.5px solid #1877F2; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                    <span>Connecting...</span>
                `;
                const callbackUrl = '<?php echo BASE_URL; ?>supabase_auth_callback.php';
                const { error } = await supabase.auth.signInWithOAuth({
                    provider: 'facebook',
                    options: { redirectTo: callbackUrl }
                });
                if (error) {
                    alert('Facebook Sign-In: ' + error.message);
                    fbBtn.disabled = false;
                    fbBtn.innerHTML = origHtml;
                }
            } catch (err) {
                console.error(err);
                alert('Facebook Sign-In error. Please try again.');
                fbBtn.disabled = false;
                fbBtn.innerHTML = origHtml;
            }
        });
    });
</script>
</body>
</html>
