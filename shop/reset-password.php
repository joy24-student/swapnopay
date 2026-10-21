<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';
require_once __DIR__ . '/admin/inc/CSRF_Protect.php';
$csrf = new CSRF_Protect();

// If already logged in, redirect to dashboard
if (isset($_SESSION['customer'])) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

// Fetch settings for store branding & side banner
$stmt = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$banner_login = !empty($settings['banner_login']) 
    ? $settings['banner_login'] 
    : 'assets/uploads/banner_login.jpg';

if (!str_starts_with($banner_login, 'http') && !str_starts_with($banner_login, 'assets/uploads/')) {
    $banner_login = 'assets/uploads/' . ltrim($banner_login, '/');
}

$store_name = defined('STORE_NAME') && !empty(STORE_NAME) ? STORE_NAME : 'Store';

// Retrieve email & token from POST or GET
$email = trim((string)($_POST['email'] ?? $_GET['email'] ?? ''));
$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));

$invalid_link = false;
$error_message = '';
$success_message = '';

if (empty($email) || empty($token)) {
    $invalid_link = true;
    $error_message = 'No reset token provided. Please use the reset link sent to your email or request a new one.';
} else {
    // Validate customer by email and token
    $stmt = $pdo->prepare("SELECT * FROM tbl_customer WHERE lower(cust_email) = ? AND cust_token != '' AND cust_token = ? LIMIT 1");
    $stmt->execute([strtolower($email), $token]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $invalid_link = true;
        $error_message = 'This password reset link is invalid or has already been used. Please request a new link.';
    } else {
        $saved_time = (int)($customer['cust_timestamp'] ?? 0);
        // Expire token after 24 hours (86400 seconds)
        if (time() - $saved_time > 86400) {
            $invalid_link = true;
            $error_message = 'This password reset link has expired (links are valid for 24 hours). Please request a new one.';
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['form_reset']) || isset($_POST['form1']))) {
    if ($invalid_link) {
        $error_message = 'Cannot update password because the reset link is no longer valid.';
    } elseif (!$csrf->isTokenValid($_POST['_csrf'] ?? '')) {
        $error_message = 'Security validation failed or expired. Please refresh the page and try again.';
    } else {
        $new_password = (string)($_POST['cust_new_password'] ?? '');
        $confirm_password = (string)($_POST['cust_re_password'] ?? '');

        if (empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please enter and confirm your new password.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'The two passwords do not match. Please re-type them.';
        } else {
            // Hash password securely with BCRYPT (compatible with login.php)
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            // Update customer password, clear token and timestamp, ensure account is active (cust_status=1)
            $update_stmt = $pdo->prepare("UPDATE tbl_customer SET cust_password = ?, cust_token = '', cust_timestamp = '', cust_status = 1 WHERE cust_id = ?");
            $update_stmt->execute([$hashed_password, $customer['cust_id']]);

            // Redirect to success page
            header("Location: " . BASE_URL . "reset-password-success.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - <?php echo htmlspecialchars($store_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            min-height: 600px;
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
            margin-bottom: 24px;
        }

        .header-section h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.6px;
            margin-bottom: 8px;
        }

        .header-section p {
            color: var(--text-muted);
            font-size: 14.5px;
            line-height: 1.5;
        }

        .alert-box {
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 13.5px;
            line-height: 1.5;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
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
            margin-bottom: 18px;
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
            padding: 0 44px 0 44px;
            background: #f8fafc;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            font-size: 14.5px;
            color: var(--text-dark);
            outline: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3.5px rgba(250, 184, 2, 0.18);
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

        .pwd-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 5px;
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
            margin-top: 10px;
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

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            background: #0f172a;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #1e293b;
            color: #fab802;
        }

        .footer-note {
            margin-top: 32px;
            font-size: 13.5px;
            color: var(--text-muted);
            text-align: center;
        }

        .footer-note a {
            color: var(--text-dark);
            font-weight: 700;
            text-decoration: none;
        }

        .footer-note a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        /* RIGHT SIDE PANEL */
        .side-panel {
            flex: 0.92;
            background: linear-gradient(145deg, #eef4ff 0%, #fdf8eb 100%);
            border-left: 1px solid rgba(226, 232, 240, 0.8);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .side-panel::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(250, 184, 2, 0.15) 0%, transparent 70%);
            pointer-events: none;
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
            max-height: 280px;
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
                display: none;
            }

            .mobile-auth-container {
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                min-height: 100dvh;
                background: #ffffff;
                padding: 32px 20px 24px;
            }

            .content-wrap {
                width: 100%;
                max-width: 420px;
                margin: 0 auto;
                display: flex;
                flex-direction: column;
                flex: 1;
            }

            .m-brand-logo {
                display: flex;
                align-items: center;
                gap: 12px;
                text-decoration: none;
                margin-bottom: 24px;
            }

            .m-brand-title {
                display: flex;
                flex-direction: column;
            }

            .m-brand-shop {
                font-size: 22px;
                font-weight: 800;
                color: #0f172a;
                letter-spacing: -0.5px;
            }

            .m-heading-section {
                margin-bottom: 24px;
            }

            .m-heading-section h1 {
                font-size: 26px;
                font-weight: 800;
                color: var(--text-dark);
                letter-spacing: -0.5px;
                margin-bottom: 6px;
            }

            .m-heading-section p {
                font-size: 14.5px;
                color: var(--text-muted);
                line-height: 1.5;
            }

            .m-input-group {
                margin-bottom: 18px;
            }

            .m-input-group label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: #334155;
                margin-bottom: 8px;
            }

            .m-input-wrapper {
                position: relative;
                display: flex;
                align-items: center;
            }

            .m-field-icon {
                position: absolute;
                left: 16px;
                width: 20px;
                height: 20px;
                color: #94a3b8;
                pointer-events: none;
            }

            .m-input-wrapper input {
                width: 100%;
                height: 52px;
                padding: 0 48px 0 48px;
                background: #f8fafc;
                border: 1.5px solid var(--border-color);
                border-radius: 16px;
                font-size: 15px;
                color: var(--text-dark);
                outline: none;
                transition: all 0.2s;
            }

            .m-input-wrapper input:focus {
                border-color: var(--primary);
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(250, 184, 2, 0.2);
            }

            .m-btn-cta {
                width: 100%;
                height: 52px;
                background: var(--primary);
                color: var(--text-dark);
                border: none;
                border-radius: 26px;
                font-size: 16px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                cursor: pointer;
                margin-top: 10px;
                box-shadow: 0 6px 20px rgba(250, 184, 2, 0.3);
            }

            .m-footer-switch {
                margin-top: 36px;
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
     1. DESKTOP VIEW (> 768px) — 2-COLUMN MODERN LAYOUT
     ========================================================================= -->
<div class="auth-container">
    <!-- LEFT PANEL: RESET PASSWORD FORM -->
    <div class="form-panel">
        <div>
            <!-- LOGO -->
            <a href="index.php" class="brand-logo" title="Back to <?php echo htmlspecialchars($store_name); ?>">
                <div class="logo-bag">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1e293b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                </div>
                <span><?php echo htmlspecialchars($store_name); ?></span>
            </a>

            <!-- HEADING -->
            <div class="header-section">
                <h1>Create New Password</h1>
                <p>Choose a strong password to secure your account.</p>
            </div>

            <!-- NOTIFICATIONS -->
            <?php if (!empty($error_message)): ?>
                <div class="alert-box alert-error">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($invalid_link): ?>
                <div style="margin-top: 24px; display:flex; flex-direction:column; gap:12px;">
                    <a href="forget-password.php" class="btn-secondary" style="width:fit-content;">
                        Request New Reset Link &rarr;
                    </a>
                    <a href="login.php" style="color:var(--primary); font-size:13.5px; font-weight:600; text-decoration:none; margin-top:6px;">
                        &larr; Back to Sign In
                    </a>
                </div>
            <?php else: ?>
                <!-- RESET FORM -->
                <form action="" method="post" id="resetFormDesktop">
                    <?php $csrf->echoInputField(); ?>
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <!-- NEW PASSWORD FIELD -->
                    <div class="input-group">
                        <label for="cust_new_password">New Password</label>
                        <div class="input-wrapper">
                            <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <input type="password" name="cust_new_password" id="cust_new_password" placeholder="At least 6 characters" required autocomplete="new-password">
                            <button type="button" class="btn-toggle-eye" id="toggleNewPwdBtn" title="Toggle password visibility">
                                <svg id="eyeIcon1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <div class="pwd-hint">Must be at least 6 characters long</div>
                    </div>

                    <!-- CONFIRM PASSWORD FIELD -->
                    <div class="input-group">
                        <label for="cust_re_password">Confirm New Password</label>
                        <div class="input-wrapper">
                            <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <input type="password" name="cust_re_password" id="cust_re_password" placeholder="Re-type your password" required autocomplete="new-password">
                            <button type="button" class="btn-toggle-eye" id="toggleRePwdBtn" title="Toggle password visibility">
                                <svg id="eyeIcon2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- CTA BUTTON -->
                    <button type="submit" name="form_reset" class="btn-cta" id="resetSubmitBtn">
                        <span>Update Password</span>
                        <span class="arrow">&rarr;</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- FOOTER SWITCH -->
        <div class="footer-note">
            Remember your credentials? <a href="login.php">Back to Sign In</a>
        </div>
    </div>

    <!-- RIGHT PANEL: 3D GRAPHIC & VALUE PROPOSITIONS -->
    <div class="side-panel">
        <div class="slogan-box">
            <div class="slogan-title">Your Next<br>Favorite Find</div>
            <svg class="slogan-underline" width="95" height="12" viewBox="0 0 95 12" fill="none">
                <path d="M2 9C28 3 70 3 93 8" stroke="#fab802" stroke-width="3" stroke-linecap="round"/>
            </svg>
        </div>

        <div class="banner-stage">
            <img src="<?php echo htmlspecialchars($banner_login); ?>" alt="<?php echo htmlspecialchars($store_name); ?> Shopping">
        </div>

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
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <div class="trust-label">24/7</div>
                <div class="trust-sub">Assistance</div>
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
        <a href="index.php" class="m-brand-logo" title="Back to <?php echo htmlspecialchars($store_name); ?>">
            <svg width="40" height="44" viewBox="0 0 48 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 16.5 C9 15.5 9.8 14.5 11 14.5 L37 14.5 C38.2 14.5 39 15.5 39 16.5 L36 43 C36 45 34 46.5 32 46.5 L16 46.5 C14 46.5 12 45 12 43 Z" fill="#FBBF24" stroke="#0F172A" stroke-width="2.8" stroke-linejoin="round"/>
                <path d="M19 14.5 V10 C19 7 21 5 24 5 C27 5 29 7 29 10 V14.5" stroke="#0F172A" stroke-width="2.8" stroke-linecap="round"/>
                <path d="M20 28 C21.5 31 26.5 31 28 28" stroke="#0F172A" stroke-width="2.8" stroke-linecap="round"/>
            </svg>
            <div class="m-brand-title">
                <span class="m-brand-shop"><?php echo htmlspecialchars($store_name); ?></span>
            </div>
        </a>

        <!-- HEADING -->
        <div class="m-heading-section">
            <h1>Create New Password</h1>
            <p>Set a secure password for your account</p>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if (!empty($error_message)): ?>
            <div class="alert-box alert-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($invalid_link): ?>
            <div style="margin-top: 20px; display:flex; flex-direction:column; gap:12px;">
                <a href="forget-password.php" class="m-btn-cta" style="text-decoration:none;">
                    <span>Request New Reset Link</span>
                    <span class="arrow">&rarr;</span>
                </a>
                <div class="m-footer-switch" style="margin-top: 16px;">
                    <a href="login.php">Back to Sign In</a>
                </div>
            </div>
        <?php else: ?>
            <!-- MOBILE FORM -->
            <form action="" method="post" id="resetFormMobile">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <!-- NEW PASSWORD -->
                <div class="m-input-group">
                    <label for="m_cust_new_password">New Password</label>
                    <div class="m-input-wrapper">
                        <svg class="m-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" name="cust_new_password" id="m_cust_new_password" placeholder="At least 6 characters" required autocomplete="new-password">
                        <button type="button" class="btn-toggle-eye" id="mToggleNewPwdBtn" title="Toggle password visibility">
                            <svg id="mEyeIcon1" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- CONFIRM PASSWORD -->
                <div class="m-input-group">
                    <label for="m_cust_re_password">Confirm New Password</label>
                    <div class="m-input-wrapper">
                        <svg class="m-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" name="cust_re_password" id="m_cust_re_password" placeholder="Re-type password" required autocomplete="new-password">
                        <button type="button" class="btn-toggle-eye" id="mToggleRePwdBtn" title="Toggle password visibility">
                            <svg id="mEyeIcon2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- CTA BUTTON -->
                <button type="submit" name="form_reset" class="m-btn-cta">
                    <span>Update Password</span>
                    <span class="arrow">&rarr;</span>
                </button>
            </form>

            <!-- FOOTER SWITCH -->
            <div class="m-footer-switch">
                Remember your credentials? <a href="login.php">Back to Sign In</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- BOTTOM TRUST BADGE -->
    <div class="m-trust-badge">
        <svg width="18" height="20" viewBox="0 0 20 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 1L2 4.5V10.5C2 15.8 5.4 20.7 10 21.8C14.6 20.7 18 15.8 18 10.5V4.5L10 1Z" fill="#FDE68A" stroke="#F59E0B" stroke-width="1.2"/>
            <rect x="7" y="11" width="6" height="5" rx="1" fill="#0F172A"/>
            <path d="M8 11V9.5C8 8.4 8.9 7.5 10 7.5C11.1 7.5 12 8.4 12 9.5V11" stroke="#0F172A" stroke-width="1.3" stroke-linecap="round"/>
        </svg>
        <span>Secure password recovery &bull; SwapnoPay Protected</span>
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
    bindPasswordToggle('toggleNewPwdBtn', 'cust_new_password', 'eyeIcon1');
    bindPasswordToggle('toggleRePwdBtn', 'cust_re_password', 'eyeIcon2');
    bindPasswordToggle('mToggleNewPwdBtn', 'm_cust_new_password', 'mEyeIcon1');
    bindPasswordToggle('mToggleRePwdBtn', 'm_cust_re_password', 'mEyeIcon2');
</script>

</body>
</html>
