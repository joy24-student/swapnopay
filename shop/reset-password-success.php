<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful - <?php echo htmlspecialchars($store_name); ?></title>
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
            min-height: 560px;
            transition: all 0.3s ease;
        }

        /* LEFT SUCCESS PANEL */
        .form-panel {
            flex: 1.08;
            padding: 48px 48px;
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

        .success-content {
            margin: auto 0;
            padding: 20px 0;
        }

        .success-icon-badge {
            width: 72px;
            height: 72px;
            background: #dcfce7;
            border: 2px solid #bbf7d0;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #16a34a;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.2);
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes popIn {
            0% { transform: scale(0.6); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-content h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.6px;
            margin-bottom: 12px;
        }

        .success-content p {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 28px;
            max-width: 440px;
        }

        /* PRIMARY BUTTON */
        .btn-cta {
            width: 100%;
            max-width: 340px;
            height: 50px;
            background: var(--primary);
            color: #1e293b;
            border: none;
            border-radius: 12px;
            font-size: 15.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 14px rgba(250, 184, 2, 0.35);
        }

        .btn-cta:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(250, 184, 2, 0.45);
        }

        .btn-cta .arrow {
            font-size: 18px;
            transition: transform 0.2s;
        }

        .btn-cta:hover .arrow {
            transform: translateX(4px);
        }

        .footer-note {
            margin-top: 24px;
            font-size: 13.5px;
            color: var(--text-muted);
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
                text-align: center;
                align-items: center;
                justify-content: center;
            }

            .m-brand-logo {
                display: flex;
                align-items: center;
                gap: 12px;
                text-decoration: none;
                margin-bottom: 32px;
            }

            .m-brand-shop {
                font-size: 22px;
                font-weight: 800;
                color: #0f172a;
                letter-spacing: -0.5px;
            }

            .m-success-icon {
                width: 80px;
                height: 80px;
                background: #dcfce7;
                border: 2px solid #bbf7d0;
                border-radius: 26px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #16a34a;
                margin-bottom: 24px;
                box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.2);
            }

            .m-heading-section h1 {
                font-size: 26px;
                font-weight: 800;
                color: var(--text-dark);
                letter-spacing: -0.5px;
                margin-bottom: 10px;
            }

            .m-heading-section p {
                font-size: 15px;
                color: var(--text-muted);
                line-height: 1.6;
                margin-bottom: 32px;
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
                text-decoration: none;
                cursor: pointer;
                box-shadow: 0 6px 20px rgba(250, 184, 2, 0.3);
            }

            .m-footer-switch {
                margin-top: 36px;
                font-size: 14px;
                font-weight: 500;
                color: var(--text-muted);
            }

            .m-footer-switch a {
                color: var(--primary);
                font-weight: 700;
                text-decoration: none;
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
        </div>

        <div class="success-content">
            <div class="success-icon-badge">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h1>Password Reset Complete!</h1>
            <p>Your password has been changed successfully. You can now use your new password to sign in to your <?php echo htmlspecialchars($store_name); ?> account.</p>
            <a href="login.php" class="btn-cta">
                <span>Sign In to Your Account</span>
                <span class="arrow">&rarr;</span>
            </a>
        </div>

        <div class="footer-note">
            Back to <a href="index.php">Store Homepage</a>
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
            <span class="m-brand-shop"><?php echo htmlspecialchars($store_name); ?></span>
        </a>

        <!-- SUCCESS ICON -->
        <div class="m-success-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>

        <!-- HEADING -->
        <div class="m-heading-section">
            <h1>Password Reset Complete!</h1>
            <p>Your password has been changed successfully. You can now sign in using your new credentials.</p>
        </div>

        <!-- CTA BUTTON -->
        <a href="login.php" class="m-btn-cta">
            <span>Sign In to Your Account</span>
            <span class="arrow">&rarr;</span>
        </a>

        <div class="m-footer-switch">
            <a href="index.php">Back to Store Homepage</a>
        </div>
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

</body>
</html>