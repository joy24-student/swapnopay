<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/admin/inc/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Authenticating... - <?php echo htmlspecialchars(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            background: #ffffff;
            border-radius: 28px;
            padding: 40px 32px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            border: 1.5px solid #e2e8f0;
        }
        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 28px;
            text-decoration: none;
        }
        .brand-logo span { color: #fab802; }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f1f5f9;
            border-top: 4px solid #fab802;
            border-radius: 50%;
            animation: spin 0.8s cubic-bezier(0.6, 0.2, 0.4, 0.8) infinite;
            margin: 0 auto 24px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        h2 { font-size: 20px; font-weight: 800; color: #0a2569; margin-bottom: 8px; }
        p { font-size: 14px; color: #64748b; line-height: 1.5; }
        .error-box {
            display: none;
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
            border-radius: 14px;
            padding: 14px;
            font-size: 13.5px;
            margin-top: 20px;
        }
        .btn-retry {
            display: inline-block;
            margin-top: 18px;
            background: #fab802;
            color: #0f172a;
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 4px 14px rgba(250, 184, 2, 0.3);
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand-logo">
        <svg width="36" height="40" viewBox="0 0 48 50" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 16.5 C9 15.5 9.8 14.5 11 14.5 L37 14.5 C38.2 14.5 39 15.5 39 16.5 L36 43 C36 45 34 46.5 32 46.5 L16 46.5 C14 46.5 12 45 12 43 Z" fill="#FBBF24" stroke="#0F172A" stroke-width="2.8" stroke-linejoin="round"/>
            <path d="M19 14.5 V10 C19 7 21 5 24 5 C27 5 29 7 29 10 V14.5" stroke="#0F172A" stroke-width="2.8" stroke-linecap="round"/>
            <path d="M20 28 C21.5 31 26.5 31 28 28" stroke="#0F172A" stroke-width="2.8" stroke-linecap="round"/>
        </svg>
        <span>Shop<span style="color:#fab802;">Next</span></span>
    </div>

    <div class="spinner" id="spinner"></div>
    <h2 id="statusTitle">Connecting with Google</h2>
    <p id="statusMsg">Please wait while we verify your Google credentials and prepare your account...</p>

    <div class="error-box" id="errorBox"></div>
</div>

<script>
    const SUPABASE_URL = '<?php echo defined("SUPABASE_URL") && SUPABASE_URL ? SUPABASE_URL : "https://pueowrrkspsykbwzwgua.supabase.co"; ?>';
    const SUPABASE_ANON_KEY = '<?php echo defined("SUPABASE_ANON_KEY") && SUPABASE_ANON_KEY ? SUPABASE_ANON_KEY : "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InB1ZW93cnJrc3BzeWtid3p3Z3VhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTA5MDkwNzksImV4cCI6MjEwNjQ4NTA3OX0.f4wk8rYN6SzdCglQO3aFFrMh8oo96Q-5L7oAhhYTuuw"; ?>';
    const supabase = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

    async function checkAuthSession() {
        try {
            // Supabase checks URL hash fragments automatically
            const { data: { session }, error } = await supabase.auth.getSession();
            
            if (error) {
                showError(error.message);
                return;
            }

            if (!session) {
                // If not parsed immediately, wait 600ms and try once more
                setTimeout(async () => {
                    const retry = await supabase.auth.getSession();
                    if (retry.data && retry.data.session) {
                        syncAndRedirect(retry.data.session.user);
                    } else {
                        showError('Could not retrieve your Google session. Please try again.');
                    }
                }, 600);
                return;
            }

            syncAndRedirect(session.user);
        } catch (err) {
            showError(err.message || 'An unexpected error occurred.');
        }
    }

    async function syncAndRedirect(user) {
        document.getElementById('statusTitle').innerText = 'Setting up Account';
        document.getElementById('statusMsg').innerText = 'Synchronizing profile details with <?php echo addslashes(defined('STORE_NAME') ? STORE_NAME : 'Store'); ?>...';

        const meta = user.user_metadata || {};
        const payload = {
            supabase_uid: user.id,
            email: user.email,
            name: meta.full_name || meta.name || user.email.split('@')[0],
            avatar_url: meta.avatar_url || meta.picture || '',
            google_id: user.identities?.[0]?.id || user.id
        };

        try {
            const resp = await fetch('supabase_auth_sync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await resp.json();
            if (result.success) {
                document.getElementById('statusTitle').innerText = 'Success!';
                document.getElementById('statusMsg').innerText = 'Redirecting to your destination...';
                window.location.href = result.redirect || 'dashboard.php';
            } else {
                showError(result.message || 'Database synchronization failed.');
            }
        } catch (e) {
            showError('Communication error syncing account with server.');
        }
    }

    function showError(msg) {
        document.getElementById('spinner').style.display = 'none';
        document.getElementById('statusTitle').innerText = 'Authentication Failed';
        document.getElementById('statusMsg').innerText = 'We were unable to sign you in using Google.';
        const errBox = document.getElementById('errorBox');
        errBox.style.display = 'block';
        errBox.innerHTML = msg + '<br><a href="login.php" class="btn-retry">Back to Sign In</a>';
    }

    // Trigger on page load
    window.addEventListener('load', checkAuthSession);
</script>
</body>
</html>
