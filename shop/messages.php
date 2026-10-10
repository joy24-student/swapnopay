<?php
/**
 * Production Live Chat & AI Copilot Screen
 * Pixel-Perfect Mobile & Desktop Experience with Realtime AI Pooling, WhatsApp/Messenger channels,
 * Product Recommendations, One-Tap AJAX Add to Cart, and Bufferless WebRTC Calls.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/inc/config.php';
require_once __DIR__ . '/admin/inc/functions.php';

// Fetch settings
try {
    $stmt = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $settings = [];
}

$site_name = !empty($settings['store_name']) ? $settings['store_name'] : (!empty($settings['meta_title_home']) ? $settings['meta_title_home'] : (defined('STORE_NAME') ? STORE_NAME : 'Store'));
$contact_phone = $settings['contact_phone'] ?? '';
$chat_whatsapp_url = trim($settings['chat_whatsapp_url'] ?? '');
if (empty($chat_whatsapp_url)) {
    $clean_phone = preg_replace('/[^0-9]/', '', $contact_phone);
    $chat_whatsapp_url = !empty($clean_phone) ? "https://wa.me/{$clean_phone}" : "https://wa.me/";
} elseif (!preg_match('/^https?:\/\//i', $chat_whatsapp_url)) {
    $chat_whatsapp_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $chat_whatsapp_url);
}

$chat_messenger_url = trim($settings['chat_messenger_url'] ?? '');
if (empty($chat_messenger_url)) {
    $chat_messenger_url = !empty($settings['facebook_url']) ? $settings['facebook_url'] : '#';
} elseif (!preg_match('/^https?:\/\//i', $chat_messenger_url)) {
    $chat_messenger_url = "https://m.me/" . ltrim($chat_messenger_url, '@');
}

$chat_call_enabled = isset($settings['chat_call_enabled']) ? (int)$settings['chat_call_enabled'] : 1;
$is_embed = isset($_GET['embed']) && $_GET['embed'] === '1';

// Product context (forwarded from product page)
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$product_info = null;
$product_url = '';
if ($product_id > 0) {
    try {
        $stmt_p = $pdo->prepare("SELECT p_id, p_name, p_current_price, p_old_price, p_featured_photo FROM tbl_product WHERE p_id = ?");
        $stmt_p->execute([$product_id]);
        $product_info = $stmt_p->fetch(PDO::FETCH_ASSOC);
        if ($product_info) {
            $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $scheme = $is_https ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = defined('BASE_URL') ? BASE_URL : '/shop/';
            $product_url = rtrim($base, '/') . '/product.php?id=' . $product_id;
            if (!preg_match('/^https?:\/\//i', $product_url)) {
                $product_url = $scheme . '://' . $host . (str_starts_with($product_url, '/') ? '' : '/') . $product_url;
            }

            // Pre-fill WhatsApp inquiry URL
            $wa_inquiry = "Hi! I am inquiring about: " . $product_info['p_name'] . " (৳ " . number_format((float)$product_info['p_current_price']) . ")\n" . $product_url;
            $wa_separator = (strpos($chat_whatsapp_url, '?') !== false) ? '&' : '?';
            $chat_whatsapp_url .= $wa_separator . 'text=' . urlencode($wa_inquiry);
        }
    } catch (Throwable $e) {
        $product_info = null;
    }
}

// Calculate cart count
$cart_count = 0;
if (!empty($_SESSION['cart_p_qty'])) {
    foreach ($_SESSION['cart_p_qty'] as $q) {
        $cart_count += (int)$q;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Messages & AI Copilot - <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Google Fonts & Tailwind Play CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#111827',
                        accent: '#F59E0B',
                        brandBlue: '#2563EB',
                        brandGreen: '#10B981',
                        chatBg: '#F8FAFC',
                        bubbleAi: '#FFFFFF',
                        bubbleUser: '#111827'
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0F172A;
            -webkit-tap-highlight-color: transparent;
        }
        .chat-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .chat-scroll::-webkit-scrollbar-thumb {
            background-color: #E2E8F0;
            border-radius: 4px;
        }
        .animate-bounce-short {
            animation: bounceShort 1.4s infinite ease-in-out both;
        }
        .animate-bounce-short:nth-child(1) { animation-delay: -0.32s; }
        .animate-bounce-short:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounceShort {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1.0); }
        }
        .touch-action-manipulation {
            touch-action: manipulation;
        }
        /* Vanish transition */
        .social-channel-box {
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 140px;
            opacity: 1;
            overflow: hidden;
        }
        .social-channel-box.vanished {
            max-height: 0 !important;
            opacity: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            pointer-events: none;
        }

        /* Pixel-perfect bottom nav matching global header */
        .mobile-bottom-nav,
        .sn-mobile-bottom-nav {
            display: flex !important;
            flex-direction: row !important;
            position: relative !important;
            width: 100% !important;
            height: 56px !important;
            min-height: 56px !important;
            max-height: 56px !important;
            background: #ffffff !important;
            border-top: 1px solid #f1f5f9 !important;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.04) !important;
            z-index: 20 !important;
            align-items: center !important;
            justify-content: space-around !important;
            padding: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            flex-shrink: 0 !important;
        }
        .sn-mobile-bottom-nav .sn-dock-item {
            flex: 1 1 0 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            height: 56px !important;
            max-height: 56px !important;
            padding: 4px 2px !important;
            margin: 0 !important;
            text-decoration: none !important;
            color: #64748b !important;
            background: transparent !important;
            border: none !important;
            position: relative !important;
            box-sizing: border-box !important;
            -webkit-tap-highlight-color: transparent !important;
            transition: color 0.15s ease !important;
        }
        .sn-mobile-bottom-nav .sn-dock-item.active {
            color: #fab802 !important;
        }
        .sn-mobile-bottom-nav .sn-dock-icon-box {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
            width: 26px !important;
            height: 22px !important;
            line-height: 1 !important;
            margin: 0 0 2px 0 !important;
            background: transparent !important;
        }
        .sn-mobile-bottom-nav .sn-dock-icon-box svg {
            width: 20px !important;
            height: 20px !important;
            display: block !important;
            flex-shrink: 0 !important;
            stroke: #64748b !important;
            stroke-width: 2px !important;
            transition: stroke 0.15s ease, fill 0.15s ease !important;
        }
        .sn-mobile-bottom-nav .sn-dock-item.active .sn-dock-icon-box svg {
            stroke: #fab802 !important;
            fill: #fab802 !important;
        }
        .sn-mobile-bottom-nav .sn-dock-badge-count {
            position: absolute !important;
            top: -4px !important;
            right: -7px !important;
            background: #ef4444 !important;
            color: #ffffff !important;
            font-size: 9px !important;
            font-weight: 800 !important;
            min-width: 14px !important;
            height: 14px !important;
            border-radius: 999px !important;
            padding: 0 3px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            line-height: 1 !important;
            margin: 0 !important;
            border: 1.5px solid #ffffff !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15) !important;
            z-index: 5 !important;
        }
        .sn-mobile-bottom-nav .sn-dock-item > span:not(.sn-dock-badge-count) {
            font-size: 11px !important;
            font-weight: 600 !important;
            line-height: 1.15 !important;
            letter-spacing: -0.2px !important;
            color: #64748b !important;
            text-align: center !important;
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            transition: color 0.15s ease !important;
        }
        .sn-mobile-bottom-nav .sn-dock-item.active > span:not(.sn-dock-badge-count) {
            color: #fab802 !important;
            font-weight: 700 !important;
        }
    </style>
</head>
<body class="bg-slate-100 flex justify-center items-center min-h-screen text-slate-900 <?php echo $is_embed ? 'p-0 bg-white h-screen overflow-hidden' : 'p-0 sm:p-4'; ?>">

<!-- MAIN APP CONTAINER -->
<div class="w-full <?php echo $is_embed ? 'max-w-none h-full rounded-none shadow-none border-0' : 'max-w-md h-screen sm:h-[90vh] sm:rounded-3xl sm:shadow-2xl border border-slate-200'; ?> bg-white flex flex-col relative overflow-hidden">

    <!-- TOP HEADER -->
    <header class="bg-white border-b border-slate-100 px-4 py-3 flex items-center justify-between z-20 shadow-sm shrink-0">
        <div class="flex items-center gap-3">
            <?php if (!$is_embed): ?>
            <a href="index.php" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition active:scale-95" aria-label="Go Back">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <?php endif; ?>
            <div class="relative">
                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-amber-400 to-amber-600 flex items-center justify-center text-white font-bold shadow-md shadow-amber-200/50">
                    <span id="headerAvatarIcon"><i class="fa-solid fa-wand-magic-sparkles text-sm"></i></span>
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full"></span>
            </div>
            <div>
                <div class="flex items-center gap-1.5">
                    <h1 class="font-bold text-slate-800 text-sm tracking-tight" id="headerTitle">AI Shop Pilot</h1>
                    <span class="text-blue-500 text-xs" title="Official Store Assistant"><i class="fa-solid fa-circle-check"></i></span>
                </div>
                <p class="text-[11px] text-slate-500 flex items-center gap-1" id="headerSubtitle">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Online • Instant replies
                </p>
            </div>
        </div>

        <!-- Mode Toggle & Calling Controls -->
        <div class="flex items-center gap-1.5">
            <!-- Channel Restore Toggle -->
            <button id="btnToggleChannels" onclick="toggleSocialChannels()" class="w-9 h-9 rounded-full bg-slate-50 hover:bg-emerald-50 text-slate-600 hover:text-emerald-600 border border-slate-200 flex items-center justify-center transition active:scale-95" title="Show / Hide WhatsApp & Messenger">
                <i class="fa-brands fa-whatsapp text-xs"></i>
            </button>

            <?php if ($chat_call_enabled): ?>
            <button id="btnVoiceCall" onclick="startWebRtcCall('audio')" class="w-9 h-9 rounded-full bg-slate-50 hover:bg-emerald-50 text-slate-600 hover:text-emerald-600 border border-slate-200 flex items-center justify-center transition active:scale-95" title="Start Audio Call">
                <i class="fa-solid fa-phone text-xs"></i>
            </button>
            <button id="btnVideoCall" onclick="startWebRtcCall('video')" class="w-9 h-9 rounded-full bg-slate-50 hover:bg-blue-50 text-slate-600 hover:text-blue-600 border border-slate-200 flex items-center justify-center transition active:scale-95" title="Start Video Call">
                <i class="fa-solid fa-video text-xs"></i>
            </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- PINNED PRODUCT CONTEXT BANNER (When opened from product page) -->
    <?php if (!empty($product_info)): ?>
    <div id="pinnedProductBanner" class="px-3.5 py-2 bg-gradient-to-r from-amber-50 to-orange-50/70 border-b border-amber-200/70 flex items-center justify-between gap-2.5 shrink-0 shadow-2xs">
        <div class="flex items-center gap-2.5 min-w-0">
            <img src="<?php echo !empty($product_info['p_featured_photo']) ? 'assets/uploads/' . htmlspecialchars($product_info['p_featured_photo']) : 'assets/uploads/product_featured_default.jpg'; ?>" class="w-9 h-9 object-cover rounded-lg border border-amber-200/90 shrink-0 shadow-2xs" alt="<?php echo htmlspecialchars($product_info['p_name']); ?>">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="text-[9.5px] font-bold uppercase tracking-wider text-amber-800 bg-amber-100/80 px-1.5 py-0.5 rounded">Inquiring Item</span>
                    <span class="text-[11px] font-extrabold text-amber-600">৳ <?php echo number_format((float)$product_info['p_current_price']); ?></span>
                </div>
                <div class="text-[11.5px] font-bold text-slate-800 truncate" title="<?php echo htmlspecialchars($product_info['p_name']); ?>">
                    <?php echo htmlspecialchars($product_info['p_name']); ?>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-1 shrink-0">
            <a href="<?php echo htmlspecialchars($product_url); ?>" target="_top" class="px-2 py-1 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-[10.5px] font-semibold rounded-lg flex items-center gap-1 transition active:scale-95" title="View Product Details">
                <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i> View
            </a>
            <button type="button" onclick="forwardProductLinkToChat(false)" class="px-2 py-1 bg-slate-900 hover:bg-black text-white text-[10.5px] font-bold rounded-lg flex items-center gap-1 shadow-2xs transition active:scale-95" title="Re-send product card to chat">
                <i class="fa-solid fa-paper-plane text-[9px]"></i> Send
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- SOCIAL CHANNELS BANNER (WHATSAPP & MESSENGER) -->
    <!-- Vanishes completely as requested once the user sends a prompt -->
    <div id="socialChannelsBar" class="social-channel-box px-4 pt-3 pb-2 bg-gradient-to-r from-amber-50/60 to-orange-50/60 border-b border-amber-100/60 shrink-0">
        <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-800/80 flex items-center gap-1">
                <i class="fa-solid fa-bolt text-amber-500"></i> Direct Messaging
            </span>
            <span class="text-[10px] text-slate-400">Vanishes on chat</span>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <!-- WhatsApp Button -->
            <a href="<?php echo htmlspecialchars($chat_whatsapp_url); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 py-2 px-3 bg-white hover:bg-emerald-50 border border-emerald-200/80 rounded-xl text-emerald-700 font-semibold text-xs shadow-sm transition active:scale-95">
                <i class="fa-brands fa-whatsapp text-emerald-500 text-base"></i>
                <span>WhatsApp</span>
            </a>
            <!-- Messenger Button -->
            <a href="<?php echo htmlspecialchars($chat_messenger_url); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 py-2 px-3 bg-white hover:bg-blue-50 border border-blue-200/80 rounded-xl text-blue-700 font-semibold text-xs shadow-sm transition active:scale-95">
                <i class="fa-brands fa-facebook-messenger text-blue-500 text-base"></i>
                <span>Messenger</span>
            </a>
        </div>
    </div>

    <!-- CHAT MESSAGES SCROLL CONTAINER -->
    <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#F8FAFC] chat-scroll">
        <!-- Initial Welcome Bubble from AI -->
        <div class="flex items-start gap-2.5">
            <div class="w-7 h-7 rounded-full bg-amber-500 text-white flex items-center justify-center text-xs shrink-0 shadow-sm">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <div class="max-w-[85%] bg-white rounded-2xl rounded-tl-sm p-3 shadow-sm border border-slate-100 text-xs text-slate-800 leading-relaxed">
                <?php if (!empty($product_info)): ?>
                <p class="font-semibold text-slate-900 mb-1">Hello! Ask me anything about <?php echo htmlspecialchars($product_info['p_name']); ?>!</p>
                <p>I can provide technical specs, warranty & delivery info, or help you add it directly to your cart!</p>
                <?php else: ?>
                <p class="font-semibold text-slate-900 mb-1">Hello! How can I assist your shopping today?</p>
                <p>I can recommend best-sellers, find discounts, answer product questions, or add items directly to your cart!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Suggestion Pills -->
        <div id="quickPrompts" class="flex flex-wrap gap-1.5 pt-1 pl-9">
            <?php if (!empty($product_info)): ?>
            <button onclick="sendQuickPrompt('What are the key specifications and features of <?php echo addslashes($product_info['p_name']); ?>?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                ⚡ Key Specs
            </button>
            <button onclick="sendQuickPrompt('What warranty and delivery timeframe apply to this item?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                🛡️ Warranty & Delivery
            </button>
            <button onclick="sendQuickPrompt('Is this product currently in stock and ready to ship?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                📦 In Stock?
            </button>
            <?php else: ?>
            <button onclick="sendQuickPrompt('What are your top trending products today?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                🔥 Top Trending
            </button>
            <button onclick="sendQuickPrompt('Do you have any discount deals available?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                🏷️ Active Deals
            </button>
            <button onclick="sendQuickPrompt('Can you suggest stylish men and women items?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                ✨ Style Guide
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TYPING INDICATOR -->
    <div id="typingIndicator" class="hidden px-4 py-2 bg-[#F8FAFC] flex items-center gap-2 pl-12 text-slate-400 text-xs">
        <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce-short"></span>
        <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce-short"></span>
        <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce-short"></span>
        <span class="text-[11px] text-slate-400 font-medium ml-1">AI Copilot is thinking...</span>
    </div>

    <!-- BOTTOM INPUT BAR -->
    <div class="bg-white border-t border-slate-200 p-2.5 shrink-0 z-10">
        <form id="chatForm" onsubmit="handleChatSubmit(event)" class="flex items-center gap-2">
            <!-- Image / Attachment Button -->
            <input type="file" id="attachmentInput" accept="image/*,application/pdf" class="hidden" onchange="handleAttachmentUpload(this)">
            <button type="button" onclick="document.getElementById('attachmentInput').click()" class="w-10 h-10 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition active:scale-95 shrink-0" title="Attach Image or Document">
                <i class="fa-solid fa-paperclip text-sm"></i>
            </button>

            <!-- Mode Toggle Icon Button (Beside File Upload) -->
            <button type="button" id="btnModeToggle" onclick="toggleChatMode()" class="w-10 h-10 rounded-full bg-amber-50 hover:bg-amber-100 border border-amber-200/80 text-amber-600 flex items-center justify-center transition active:scale-95 shrink-0" title="Switch to Human Support">
                <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
            </button>

            <!-- Text Input -->
            <div class="relative flex-1">
                <input type="text" id="chatInput" placeholder="Ask anything about products, sizing, delivery..." autocomplete="off" class="w-full bg-slate-100 focus:bg-white text-slate-800 text-xs sm:text-sm placeholder-slate-400 px-4 py-2.5 rounded-full border border-transparent focus:border-amber-400 focus:ring-2 focus:ring-amber-100 focus:outline-none transition">
            </div>

            <!-- Send Button -->
            <button type="submit" id="btnSend" class="w-10 h-10 rounded-full bg-slate-900 hover:bg-black text-white flex items-center justify-center transition active:scale-95 shadow-md shadow-slate-900/20 shrink-0" aria-label="Send Message">
                <i class="fa-solid fa-arrow-up text-sm"></i>
            </button>
        </form>
    </div>

    <!-- 5-TAB MOBILE BOTTOM DOCK (Hidden when in desktop embed mode) -->
    <?php if (!$is_embed): ?>
    <div class="mobile-bottom-nav sn-mobile-bottom-nav">
        <!-- 1. Home -->
        <a href="index.php" class="sn-dock-item">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </div>
            <span>Home</span>
        </a>

        <!-- 2. Deals -->
        <a href="deals.php" class="sn-dock-item">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <span>Deals</span>
        </a>

        <!-- 3. Messages (Active) -->
        <a href="messages.php" class="sn-dock-item active">
            <div class="sn-dock-icon-box sn-dock-has-badge">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#fab802" stroke="#fab802" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span class="sn-dock-badge-count" style="display:none;" id="sn-dock-messages-badge">1</span>
            </div>
            <span>Messages</span>
        </a>

        <!-- 4. Cart -->
        <a href="cart.php" class="sn-dock-item">
            <div class="sn-dock-icon-box sn-dock-has-badge">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span class="sn-dock-badge-count" id="dockCartBadge" style="<?php echo $cart_count > 0 ? '' : 'display:none;'; ?>"><?php echo $cart_count; ?></span>
            </div>
            <span>Cart</span>
        </a>

        <!-- 5. Account -->
        <a href="<?php echo isset($_SESSION['customer']) ? 'dashboard.php' : 'login.php'; ?>" class="sn-dock-item">
            <div class="sn-dock-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <span>Account</span>
        </a>
    </div>
    <?php endif; ?>

    <!-- WEBRTC BUFFERLESS CALL MODAL OVERLAY -->
    <div id="callOverlay" class="hidden fixed inset-0 bg-slate-950/95 z-[150] flex flex-col justify-between p-6 text-white backdrop-blur-md">
        <!-- Call Top Info -->
        <div class="text-center pt-8">
            <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-tr from-amber-400 to-amber-600 flex items-center justify-center text-3xl shadow-xl shadow-amber-500/20 mb-3 animate-pulse">
                <i id="callTypeIcon" class="fa-solid fa-phone"></i>
            </div>
            <h3 class="font-bold text-lg" id="callPeerName">Store Support Specialist</h3>
            <p class="text-xs text-slate-300 tracking-wider font-mono mt-1" id="callDuration">Connecting call...</p>
        </div>

        <!-- Video Windows -->
        <div id="videoContainer" class="hidden flex-1 relative my-4 rounded-2xl overflow-hidden bg-black border border-slate-800 flex items-center justify-center">
            <video id="remoteVideo" autoplay playsinline class="w-full h-full object-cover"></video>
            <video id="localVideo" autoplay playsinline muted class="absolute bottom-3 right-3 w-24 h-32 rounded-xl object-cover border-2 border-white/50 shadow-lg"></video>
        </div>

        <!-- Hidden Audio Elements -->
        <audio id="remoteAudio" autoplay playsinline></audio>

        <!-- Call Action Buttons -->
        <div class="flex items-center justify-center gap-6 pb-6">
            <button id="btnToggleMic" onclick="toggleMuteAudio()" class="w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition active:scale-95">
                <i class="fa-solid fa-microphone text-base" id="micIcon"></i>
            </button>
            <button onclick="hangupCall()" class="w-14 h-14 rounded-full bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center transition active:scale-95 shadow-lg shadow-rose-600/40">
                <i class="fa-solid fa-phone-slash text-lg"></i>
            </button>
            <button id="btnToggleCam" onclick="toggleMuteVideo()" class="w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition active:scale-95">
                <i class="fa-solid fa-video text-base" id="camIcon"></i>
            </button>
        </div>
    </div>

    <!-- INCOMING CALL MODAL FOR CUSTOMER -->
    <div id="incomingCallModal" class="hidden fixed inset-0 z-[150] bg-slate-950/95 backdrop-blur-md flex flex-col items-center justify-center p-6 text-white text-center">
        <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-amber-400 to-amber-600 flex items-center justify-center text-4xl shadow-2xl shadow-amber-500/40 mb-5 animate-pulse">
            <i id="incomingCallIcon" class="fa-solid fa-phone"></i>
        </div>
        <h3 class="font-bold text-xl text-white mb-1" id="incomingCallerTitle">Store Support Specialist</h3>
        <p class="text-sm text-slate-300 mb-8" id="incomingCallSubtitle">Incoming audio call...</p>
        <div class="flex items-center justify-center gap-10">
            <!-- Decline Button -->
            <button type="button" onclick="declineIncomingCall()" class="flex flex-col items-center gap-2 text-xs font-semibold text-rose-300 active:scale-95 transition">
                <div class="w-16 h-16 rounded-full bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center text-2xl shadow-lg shadow-rose-600/40">
                    <i class="fa-solid fa-phone-slash"></i>
                </div>
                <span>Decline</span>
            </button>
            <!-- Accept Button -->
            <button type="button" onclick="acceptIncomingCall()" class="flex flex-col items-center gap-2 text-xs font-semibold text-emerald-300 active:scale-95 transition">
                <div class="w-16 h-16 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center text-2xl shadow-lg shadow-emerald-500/40 animate-bounce">
                    <i class="fa-solid fa-phone"></i>
                </div>
                <span>Accept</span>
            </button>
        </div>
    </div>

    <!-- 1. FULLSCREEN CHAT IMAGE LIGHTBOX WITH CROSS BUTTON -->
    <div id="chatImageLightbox" class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md hidden flex-col justify-between p-4" onclick="closeChatImageLightbox()">
        <!-- Top bar with close cross button -->
        <div class="flex items-center justify-between text-white w-full px-2 py-2" onclick="event.stopPropagation()">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                <i class="fa-solid fa-image text-amber-400"></i> Image Preview
            </div>
            <div class="flex items-center gap-3">
                <a id="chatLightboxDownload" href="#" download="chat-photo.jpg" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition active:scale-95" title="Download Image">
                    <i class="fa-solid fa-download text-sm"></i>
                </a>
                <button type="button" onclick="closeChatImageLightbox()" class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition active:scale-95" title="Close (Esc)">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
        </div>
        <!-- Center image container -->
        <div class="flex-1 flex items-center justify-center min-h-0 p-2" onclick="event.stopPropagation()">
            <img id="chatLightboxImg" src="" alt="Photo Preview" class="max-w-[95vw] max-h-[80vh] object-contain rounded-xl shadow-2xl transition duration-200">
        </div>
        <div class="text-center text-xs text-slate-400 py-1" onclick="event.stopPropagation()">
            Click outside or press Escape to close
        </div>
    </div>

    <!-- 2. PRE-SEND IMAGE ATTACHMENT PREVIEW MODAL WITH CROSS BUTTON -->
    <div id="attachmentPreviewModal" class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-sm w-full overflow-hidden shadow-2xl flex flex-col border border-slate-100" onclick="event.stopPropagation()">
            <!-- Header with cross button -->
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700 tracking-wide flex items-center gap-2">
                    <i class="fa-solid fa-camera text-amber-500"></i> Send Photo Preview
                </span>
                <button type="button" onclick="cancelAttachmentPreview()" class="w-8 h-8 rounded-full bg-slate-200 hover:bg-slate-300 text-slate-700 flex items-center justify-center transition active:scale-95" title="Close / Cancel">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
            <!-- Image preview -->
            <div class="p-3 bg-slate-900/5 flex items-center justify-center max-h-72 overflow-hidden">
                <img id="attachmentPreviewImg" src="" alt="Preview" class="max-h-64 max-w-full rounded-xl object-contain shadow-sm">
            </div>
            <!-- Caption & Action Buttons -->
            <div class="p-4 flex flex-col gap-3 bg-white">
                <input type="text" id="attachmentCaptionInput" placeholder="Add a caption... (optional)" class="w-full text-xs bg-slate-100 px-3.5 py-2.5 rounded-xl border border-transparent focus:border-amber-400 focus:bg-white focus:outline-none">
                <div class="flex items-center justify-end gap-2">
                    <button type="button" onclick="cancelAttachmentPreview()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Cancel
                    </button>
                    <button type="button" id="btnConfirmSendAttachment" onclick="confirmSendAttachment()" class="px-5 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-600 text-slate-900 shadow-md transition flex items-center gap-1.5 active:scale-95">
                        <i class="fa-solid fa-paper-plane text-xs"></i> Send
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- JAVASCRIPT LOGIC & WEBRTC ENGINE -->
<script>
    // State management
    let chatMode = 'ai'; // 'ai' or 'live'
    let currentThread = null;
    let lastMessageId = 0;
    let pollInterval = null;
    let hasSentFirstPrompt = localStorage.getItem('sn_chat_prompt_sent') === 'true';

    // Pinned / Inquiring Product Context
    const currentProductInfo = <?php echo !empty($product_info) ? json_encode([
        'id' => (int)$product_info['p_id'],
        'name' => $product_info['p_name'],
        'price' => number_format((float)$product_info['p_current_price']),
        'photo' => !empty($product_info['p_featured_photo']) ? 'assets/uploads/' . $product_info['p_featured_photo'] : 'assets/uploads/product_featured_default.jpg',
        'url' => $product_url
    ]) : 'null'; ?>;

    // Check if WhatsApp/Messenger vanished state should be restored
    if (hasSentFirstPrompt) {
        document.addEventListener('DOMContentLoaded', () => {
            const bar = document.getElementById('socialChannelsBar');
            if (bar) bar.classList.add('vanished');
        });
    }

    // Initialize Chat State
    async function initChatState() {
        try {
            const res = await fetch('live_chat_api.php?action=get_state');
            const data = await res.json();
            if (data.status === 'success') {
                currentThread = data.thread;
                chatMode = data.thread.mode || 'ai';
                updateModeUI();

                // Render existing messages
                let hasProductInThread = false;
                if (data.messages && data.messages.length > 0) {
                    let maxId = 0;
                    data.messages.forEach(msg => {
                        appendMessageBubble(msg);
                        if (msg.id) {
                            maxId = Math.max(maxId, parseInt(msg.id, 10) || 0);
                        }
                        if (currentProductInfo && msg.message && msg.message.includes(currentProductInfo.name)) {
                            hasProductInThread = true;
                        }
                    });
                    lastMessageId = Math.max(lastMessageId, maxId);
                    scrollChatToBottom();
                }

                // Start polling for new messages & signals
                startPolling();

                // Auto-answer incoming call if opened via call answer action
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('auto_answer') === '1') {
                    setTimeout(() => {
                        handleAutoAnswerFlowCustomer();
                    }, 400);
                }

                // Auto-forward product link if inquiring from product page
                if (currentProductInfo && currentProductInfo.id) {
                    const sessionKey = 'sn_chat_p_auto_forwarded_' + currentProductInfo.id;
                    const alreadyForwarded = sessionStorage.getItem(sessionKey) === 'true' || hasProductInThread;
                    if (!alreadyForwarded) {
                        sessionStorage.setItem(sessionKey, 'true');
                        setTimeout(() => {
                            forwardProductLinkToChat(true);
                        }, 350);
                    }
                }
            }
        } catch (e) {
            console.error('Failed to init chat:', e);
        }
    }

    // Forward Product Link into Chat Thread
    async function forwardProductLinkToChat(isAuto = false) {
        if (!currentProductInfo) return;

        // Vanish WhatsApp/Messenger banner immediately
        vanishSocialChannels();

        const messageText = `Hi! I'm inquiring about this product:\n"${currentProductInfo.name}"\nPrice: ৳ ${currentProductInfo.price}\nProduct Link: ${currentProductInfo.url}`;

        // Render user message bubble with product card
        appendMessageBubble({
            sender_type: 'customer',
            message: messageText,
            product_data: currentProductInfo,
            created_at: new Date().toISOString()
        });
        scrollChatToBottom();

        if (chatMode === 'ai') {
            showTyping(true);
            try {
                const fd = new FormData();
                const aiPrompt = isAuto
                    ? `Hi, I am inquiring about "${currentProductInfo.name}". Could you give me a clear overview of this item, its key features, warranty, stock availability, and delivery options?`
                    : `Please tell me more about "${currentProductInfo.name}".`;
                fd.append('prompt', aiPrompt);
                fd.append('product_id', currentProductInfo.id);

                const res = await fetch('gemini_chat.php', { method: 'POST', body: fd });
                const aiData = await res.json();
                showTyping(false);

                if (aiData.status === 'success') {
                    appendMessageBubble({
                        sender_type: 'ai',
                        message: aiData.reply || aiData.response || `Here is the product information for ${currentProductInfo.name}!`,
                        product_data: currentProductInfo
                    });
                } else {
                    appendMessageBubble({
                        sender_type: 'ai',
                        message: aiData.message || `I am ready to help you with ${currentProductInfo.name}. Feel free to ask about specifications or delivery!`,
                        product_data: currentProductInfo
                    });
                }
                scrollChatToBottom();
            } catch (err) {
                showTyping(false);
                appendMessageBubble({
                    sender_type: 'ai',
                    message: `Welcome! Feel free to ask any questions about ${currentProductInfo.name}, or click WhatsApp above for instant direct chat!`,
                    product_data: currentProductInfo
                });
                scrollChatToBottom();
            }
        } else {
            // Live human support mode
            try {
                const fd = new FormData();
                fd.append('message', messageText);
                fd.append('product_data', JSON.stringify(currentProductInfo));
                const res = await fetch('live_chat_api.php?action=send_message', { method: 'POST', body: fd });
                const d = await res.json();
                if (d.message) {
                    lastMessageId = d.message.id;
                }
            } catch (err) {
                console.error('Error forwarding product in live chat:', err);
            }
        }
    }

    // Toggle between AI Copilot and Live Human Support
    async function toggleChatMode() {
        const targetMode = (chatMode === 'ai') ? 'live' : 'ai';
        try {
            const fd = new FormData();
            fd.append('mode', targetMode);
            const res = await fetch('live_chat_api.php?action=switch_mode', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.status === 'success') {
                chatMode = targetMode;
                updateModeUI();
                appendSystemNotice(data.notice);
                scrollChatToBottom();
            }
        } catch (e) {
            console.error('Error switching mode:', e);
        }
    }

    function updateModeUI() {
        const btnToggle = document.getElementById('btnModeToggle');
        const headerTitle = document.getElementById('headerTitle');
        const headerSub = document.getElementById('headerSubtitle');
        const avatarIcon = document.getElementById('headerAvatarIcon');

        if (chatMode === 'live') {
            if (btnToggle) {
                btnToggle.innerHTML = '<i class="fa-solid fa-headset text-xs text-blue-600"></i>';
                btnToggle.title = 'Switch to AI Copilot';
            }
            headerTitle.textContent = 'Live Support Team';
            headerSub.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500"></span> Connected to Specialist';
            avatarIcon.innerHTML = '<i class="fa-solid fa-headset text-sm"></i>';
        } else {
            if (btnToggle) {
                btnToggle.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles text-xs text-amber-500"></i>';
                btnToggle.title = 'Switch to Human Support';
            }
            headerTitle.textContent = 'AI Shop Pilot';
            headerSub.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Online • Instant replies';
            avatarIcon.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles text-sm"></i>';
        }
    }

    // Send Quick Suggestion Prompt
    function sendQuickPrompt(text) {
        document.getElementById('chatInput').value = text;
        handleChatSubmit(new Event('submit'));
    }

    // Handle Form Submit
    async function handleChatSubmit(e) {
        if (e && e.preventDefault) e.preventDefault();
        const input = document.getElementById('chatInput');
        const messageText = input.value.trim();
        if (!messageText) return;

        // 1. Pixel-perfect requirement: VANISH WhatsApp and Messenger immediately!
        vanishSocialChannels();

        input.value = '';

        // Render user message bubble immediately
        appendMessageBubble({
            sender_type: 'customer',
            message: messageText,
            created_at: new Date().toISOString()
        });
        scrollChatToBottom();

        // If in AI mode: send to high-performance multi-engine pool
        if (chatMode === 'ai') {
            showTyping(true);
            try {
                const fd = new FormData();
                fd.append('prompt', messageText);
                if (currentProductInfo && currentProductInfo.id) {
                    fd.append('product_id', currentProductInfo.id);
                }

                const res = await fetch('gemini_chat.php', {
                    method: 'POST',
                    body: fd
                });
                const aiData = await res.json();
                showTyping(false);

                if (aiData.status === 'success') {
                    appendMessageBubble({
                        sender_type: 'ai',
                        message: aiData.reply || aiData.response || 'Here is what I found for you!',
                        product_data: aiData.product || (currentProductInfo || null)
                    });
                } else {
                    appendMessageBubble({
                        sender_type: 'ai',
                        message: aiData.message || 'I am ready to help you discover products. Feel free to ask about any category!'
                    });
                }
                scrollChatToBottom();
            } catch (err) {
                showTyping(false);
                appendMessageBubble({
                    sender_type: 'ai',
                    message: "I'm having a brief connection pause. You can also switch to Human Support using the button above!"
                });
            }
        } else {
            // In Live mode: send to store admin / support queue
            try {
                const fd = new FormData();
                fd.append('message', messageText);
                if (currentProductInfo && currentProductInfo.id) {
                    fd.append('product_data', JSON.stringify(currentProductInfo));
                }
                const res = await fetch('live_chat_api.php?action=send_message', { method: 'POST', body: fd });
                const d = await res.json();
                if (d.message) {
                    lastMessageId = d.message.id;
                }
            } catch (err) {
                console.error('Error sending message:', err);
            }
        }
    }

    // Vanish WhatsApp and Messenger elements smoothly
    function vanishSocialChannels() {
        if (!hasSentFirstPrompt) {
            hasSentFirstPrompt = true;
            localStorage.setItem('sn_chat_prompt_sent', 'true');
            const bar = document.getElementById('socialChannelsBar');
            if (bar) {
                bar.classList.add('vanished');
            }
        }
    }

    // Restore WhatsApp and Messenger elements anytime on demand
    function restoreSocialChannels() {
        localStorage.removeItem('sn_chat_prompt_sent');
        hasSentFirstPrompt = false;
        const bar = document.getElementById('socialChannelsBar');
        if (bar) {
            bar.classList.remove('vanished');
        }
    }

    // Toggle WhatsApp and Messenger banner visibility
    function toggleSocialChannels() {
        const bar = document.getElementById('socialChannelsBar');
        if (!bar) return;
        if (bar.classList.contains('vanished')) {
            restoreSocialChannels();
        } else {
            bar.classList.add('vanished');
        }
    }

    // Append Message Bubble into DOM
    function appendMessageBubble(msg) {
        const container = document.getElementById('chatMessages');
        if (!container) return;

        // Deduplication 1: If message with this ID already rendered, skip
        if (msg.id && container.querySelector(`[data-msg-id="${msg.id}"]`)) {
            lastMessageId = Math.max(lastMessageId, parseInt(msg.id, 10) || 0);
            return;
        }

        // Deduplication 2: If message with this attachment URL already rendered, skip
        if (msg.attachment_url && container.querySelector(`[data-attachment-url="${encodeURIComponent(msg.attachment_url)}"]`)) {
            if (msg.id) lastMessageId = Math.max(lastMessageId, parseInt(msg.id, 10) || 0);
            return;
        }

        const isUser = msg.sender_type === 'customer';
        const isSystem = msg.sender_type === 'system';

        if (isSystem) {
            appendSystemNotice(msg.message);
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = `flex items-start gap-2.5 ${isUser ? 'justify-end' : 'justify-start'}`;
        if (msg.id) {
            wrap.setAttribute('data-msg-id', msg.id);
        }
        if (msg.attachment_url) {
            wrap.setAttribute('data-attachment-url', encodeURIComponent(msg.attachment_url));
        }

        let avatar = '';
        if (!isUser) {
            const isAgent = msg.sender_type === 'admin';
            avatar = `
                <div class="w-7 h-7 rounded-full ${isAgent ? 'bg-blue-600' : 'bg-amber-500'} text-white flex items-center justify-center text-xs shrink-0 shadow-sm">
                    <i class="fa-solid ${isAgent ? 'fa-headset' : 'fa-wand-magic-sparkles'}"></i>
                </div>
            `;
        }

        let bubbleClasses = isUser
            ? 'bg-slate-900 text-white rounded-2xl rounded-tr-sm p-3 max-w-[85%] text-xs shadow-sm leading-relaxed'
            : 'bg-white text-slate-800 rounded-2xl rounded-tl-sm p-3 max-w-[85%] text-xs shadow-sm border border-slate-100 leading-relaxed';

        let attachmentHtml = '';
        if (msg.attachment_url) {
            if (msg.attachment_type === 'image') {
                attachmentHtml = `<div class="mt-2 rounded-xl overflow-hidden border border-slate-200/50 cursor-pointer group" onclick="openChatImageLightbox('${encodeURI(msg.attachment_url)}')" title="Click to view full photo"><img src="${msg.attachment_url}" class="w-full max-h-56 object-cover group-hover:opacity-95 transition" alt="Attachment"></div>`;
            } else {
                attachmentHtml = `<div class="mt-2"><a href="${msg.attachment_url}" target="_blank" class="text-xs text-blue-500 underline flex items-center gap-1"><i class="fa-solid fa-file"></i> View Attachment</a></div>`;
            }
        }

        let productCardHtml = '';
        if (msg.product_data) {
            let p = typeof msg.product_data === 'string' ? JSON.parse(msg.product_data) : msg.product_data;
            const pPrice = p.current_price || p.price || '';
            const pPhoto = p.photo || 'assets/uploads/product_featured_default.jpg';
            const pUrl = p.url || `product.php?id=${p.id}`;
            productCardHtml = `
                <div class="mt-2.5 p-2.5 bg-slate-50 border border-slate-200/90 rounded-xl flex items-center gap-3 shadow-2xs">
                    <img src="${pPhoto}" class="w-12 h-12 object-cover rounded-lg shrink-0 border border-slate-200" alt="${escapeHtml(p.name)}">
                    <div class="flex-1 min-w-0">
                        <a href="${pUrl}" target="_top" class="font-bold text-slate-800 text-xs truncate block hover:text-amber-600 transition">${escapeHtml(p.name)}</a>
                        <div class="text-amber-600 font-bold text-xs mt-0.5">৳ ${escapeHtml(pPrice)}</div>
                        <div class="flex items-center gap-1.5 mt-1.5">
                            <button onclick="quickAddToCart(${p.id}, this)" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white font-semibold text-[10px] rounded-lg shadow-sm transition active:scale-95 flex items-center gap-1">
                                <i class="fa-solid fa-cart-plus"></i> Add to Cart
                            </button>
                            <a href="${pUrl}" target="_top" class="px-2 py-1 bg-white hover:bg-slate-100 border border-slate-200 text-slate-600 font-semibold text-[10px] rounded-lg transition active:scale-95">
                                View
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }

        let bubbleContent = '';
        if (isUser) {
            bubbleContent = `<p class="whitespace-pre-wrap">${escapeHtml(msg.message)}</p>`;
        } else {
            const hasHtml = /<[a-z][\s\S]*>/i.test(msg.message);
            bubbleContent = hasHtml
                ? `<div class="prose prose-sm max-w-none text-slate-800 leading-relaxed text-xs">${msg.message}</div>`
                : `<p class="whitespace-pre-wrap">${escapeHtml(msg.message)}</p>`;
        }

        wrap.innerHTML = `
            ${avatar}
            <div class="${bubbleClasses}">
                ${bubbleContent}
                ${attachmentHtml}
                ${productCardHtml}
            </div>
        `;

        container.appendChild(wrap);
    }

    function appendSystemNotice(text) {
        const container = document.getElementById('chatMessages');
        const div = document.createElement('div');
        div.className = 'text-center my-2';
        div.innerHTML = `<span class="inline-block px-3 py-1 bg-slate-200/80 text-slate-600 rounded-full text-[10px] font-medium">${escapeHtml(text)}</span>`;
        container.appendChild(div);
    }

    function showTyping(show) {
        const el = document.getElementById('typingIndicator');
        if (el) el.classList.toggle('hidden', !show);
        if (show) scrollChatToBottom();
    }

    function scrollChatToBottom() {
        const c = document.getElementById('chatMessages');
        if (c) c.scrollTop = c.scrollHeight;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // 1-Tap Quick Add to Cart via AJAX
    async function quickAddToCart(productId, btnElement) {
        const origHtml = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Adding...';
        btnElement.disabled = true;

        try {
            const fd = new FormData();
            fd.append('product_id', productId);
            fd.append('quantity', 1);

            const res = await fetch('add-to-cart-ajax.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success || data.status === 'success') {
                btnElement.innerHTML = '<i class="fa-solid fa-check"></i> Added!';
                btnElement.className = 'px-2.5 py-1 bg-emerald-600 text-white font-semibold text-[10px] rounded-lg shadow-sm';
                
                // Update badge
                const badge = document.getElementById('dockCartBadge');
                if (badge) {
                    badge.textContent = data.cart_count || ((parseInt(badge.textContent) || 0) + 1);
                    badge.classList.remove('hidden');
                }

                // If running in embed/iframe, notify parent window to update cart badge!
                if (window.parent && window.parent !== window) {
                    try {
                        window.parent.postMessage({
                            type: 'CART_UPDATED',
                            cart_count: data.cart_count || ((parseInt(badge ? badge.textContent : 0) || 0) + 1)
                        }, '*');
                    } catch (e) {}
                }
            } else {
                btnElement.innerHTML = origHtml;
                btnElement.disabled = false;
                alert(data.message || 'Could not add product to cart.');
            }
        } catch (e) {
            btnElement.innerHTML = origHtml;
            btnElement.disabled = false;
        }
    }

    // ==========================================================================
    // IMAGE PREVIEW & ATTACHMENT UPLOAD (With Cross / Cancel Button)
    // ==========================================================================
    let pendingAttachmentFile = null;

    function openChatImageLightbox(url) {
        if (!url) return;
        const lightbox = document.getElementById('chatImageLightbox');
        const img = document.getElementById('chatLightboxImg');
        const dl = document.getElementById('chatLightboxDownload');
        if (img) img.src = url;
        if (dl) dl.href = url;
        if (lightbox) {
            lightbox.classList.remove('hidden');
            lightbox.classList.add('flex');
        }
    }

    function closeChatImageLightbox() {
        const lightbox = document.getElementById('chatImageLightbox');
        if (lightbox) {
            lightbox.classList.add('hidden');
            lightbox.classList.remove('flex');
            const img = document.getElementById('chatLightboxImg');
            if (img) img.src = '';
        }
    }

    function handleAttachmentUpload(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        pendingAttachmentFile = file;

        // If file is an image, show preview modal with cross button
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewImg = document.getElementById('attachmentPreviewImg');
                const captionInput = document.getElementById('attachmentCaptionInput');
                const modal = document.getElementById('attachmentPreviewModal');
                if (previewImg) previewImg.src = e.target.result;
                if (captionInput) captionInput.value = '';
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
                if (captionInput) setTimeout(() => captionInput.focus(), 150);
            };
            reader.readAsDataURL(file);
        } else {
            // Non-image files: send directly
            executeAttachmentSend(file, '');
            input.value = '';
        }
    }

    function cancelAttachmentPreview() {
        pendingAttachmentFile = null;
        const input = document.getElementById('attachmentInput');
        if (input) input.value = '';
        const modal = document.getElementById('attachmentPreviewModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        const previewImg = document.getElementById('attachmentPreviewImg');
        if (previewImg) previewImg.src = '';
    }

    async function confirmSendAttachment() {
        if (!pendingAttachmentFile) return;
        const file = pendingAttachmentFile;
        const caption = document.getElementById('attachmentCaptionInput')?.value?.trim() || '';
        cancelAttachmentPreview();
        await executeAttachmentSend(file, caption);
    }

    async function executeAttachmentSend(file, caption) {
        vanishSocialChannels();
        const fd = new FormData();
        fd.append('attachment', file);

        try {
            showTyping(true);
            const res = await fetch('live_chat_api.php?action=upload_attachment', { method: 'POST', body: fd });
            const data = await res.json();
            showTyping(false);

            if (data.status === 'success') {
                const msgFd = new FormData();
                msgFd.append('attachment_url', data.url);
                msgFd.append('attachment_type', data.type);
                msgFd.append('message', caption);

                const sendRes = await fetch('live_chat_api.php?action=send_message', { method: 'POST', body: msgFd });
                const sendData = await sendRes.json();
                if (sendData.message) {
                    if (sendData.message.id) {
                        lastMessageId = Math.max(lastMessageId, parseInt(sendData.message.id, 10) || 0);
                    }
                    appendMessageBubble(sendData.message);
                    scrollChatToBottom();
                }
            } else {
                alert(data.message || 'Upload failed');
            }
        } catch (e) {
            showTyping(false);
            alert('Upload error');
        }
    }

    // Keyboard support: Escape closes image lightbox and preview modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeChatImageLightbox();
            cancelAttachmentPreview();
        }
    });

    // Background message & signal poller
    function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(async () => {
            try {
                // 1. Fetch new messages
                const res = await fetch(`live_chat_api.php?action=fetch_messages&last_id=${lastMessageId}`);
                const data = await res.json();
                if (data.status === 'success' && data.messages && data.messages.length > 0) {
                    data.messages.forEach(m => {
                        appendMessageBubble(m);
                        if (m.id) {
                            lastMessageId = Math.max(lastMessageId, parseInt(m.id, 10) || 0);
                        }
                    });
                    scrollChatToBottom();
                }

                // 2. Fetch incoming WebRTC signals
                pollWebRtcSignals();
            } catch (e) {}
        }, 3000);
    }

    // ==========================================
    // ==========================================================================
    // ROBUST WEBRTC AUDIO & VIDEO CALL ENGINE (Production Ready)
    // ==========================================================================
    let peerConnection = null;
    let localStream = null;
    let isMutedAudio = false;
    let isMutedVideo = false;
    let callTimerInterval = null;
    let callStartTime = null;
    let currentCallType = 'audio';
    let pendingOfferSignal = null;
    let queuedCandidates = [];
    let fastSignalTimer = null;
    let callRingtoneInterval = null;
    let custWebRtcAudioCtx = null;
    let custRemoteAudioSourceNode = null;

    const rtcConfig = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' },
            { urls: 'stun:stun.cloudflare.com:3478' },
            {
                urls: [
                    'turn:80.225.247.237:3478?transport=udp',
                    'turn:80.225.247.237:3478?transport=tcp'
                ],
                username: 'swapno',
                credential: 'SwapnoWebRtcTurn2026!'
            },
            {
                urls: [
                    'turn:openrelay.metered.ca:80',
                    'turn:openrelay.metered.ca:443',
                    'turn:openrelay.metered.ca:443?transport=tcp'
                ],
                username: 'openrelay',
                credential: 'openrelay'
            }
        ],
        iceCandidatePoolSize: 10
    };

    // Singleton Web Audio context for customer side
    function getCustWebRtcAudioContext() {
        if (!custWebRtcAudioCtx || custWebRtcAudioCtx.state === 'closed') {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                custWebRtcAudioCtx = new AudioCtx();
            }
        }
        if (custWebRtcAudioCtx && custWebRtcAudioCtx.state === 'suspended') {
            custWebRtcAudioCtx.resume().catch(() => {});
        }
        return custWebRtcAudioCtx;
    }

    // User-gesture audio unlocker: bypasses Chrome/Safari autoplay restrictions
    function unlockCustAudioPlayback() {
        const ctx = getCustWebRtcAudioContext();
        if (ctx && ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
        const audioEl = document.getElementById('remoteAudio');
        if (audioEl) {
            audioEl.muted = false;
            audioEl.play().catch(() => {});
        }
    }

    // Single-instance Web Audio Ringtone Generator (100% reliable, zero asset dependencies)
    function playCallRingtone() {
        stopCallRingtone();
        const triggerTone = () => {
            try {
                const ctx = getCustWebRtcAudioContext();
                if (!ctx) return;
                const now = ctx.currentTime;
                const osc1 = ctx.createOscillator();
                const osc2 = ctx.createOscillator();
                const gain = ctx.createGain();

                osc1.type = 'sine';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(440, now);
                osc2.frequency.setValueAtTime(480, now);

                osc1.connect(gain);
                osc2.connect(gain);
                gain.connect(ctx.destination);

                gain.gain.setValueAtTime(0.12, now);
                gain.gain.setValueAtTime(0.12, now + 0.4);
                gain.gain.setValueAtTime(0, now + 0.45);
                gain.gain.setValueAtTime(0.12, now + 0.65);
                gain.gain.setValueAtTime(0.12, now + 1.05);
                gain.gain.setValueAtTime(0, now + 1.1);

                osc1.start(now);
                osc2.start(now);
                osc1.stop(now + 1.15);
                osc2.stop(now + 1.15);
            } catch (e) {}
        };

        triggerTone();
        callRingtoneInterval = setInterval(triggerTone, 2200);
    }

    function stopCallRingtone() {
        if (callRingtoneInterval) {
            clearInterval(callRingtoneInterval);
            callRingtoneInterval = null;
        }
    }

    // High frequency signal poller during active calls (600ms)
    function startFastSignalPolling() {
        if (fastSignalTimer) clearInterval(fastSignalTimer);
        fastSignalTimer = setInterval(pollWebRtcSignals, 600);
    }

    function stopFastSignalPolling() {
        if (fastSignalTimer) {
            clearInterval(fastSignalTimer);
            fastSignalTimer = null;
        }
    }

    // Fallback MediaStream retriever
    async function getCustMediaStreamWithFallback(type) {
        const preferredConstraints = {
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            },
            video: type === 'video' ? { width: { ideal: 640 }, height: { ideal: 480 } } : false
        };

        try {
            return await navigator.mediaDevices.getUserMedia(preferredConstraints);
        } catch (prefErr) {
            console.warn('[WebRTC Cust] High-spec constraints rejected, falling back to basic audio/video:', prefErr);
            const basicConstraints = {
                audio: true,
                video: type === 'video' ? true : false
            };
            return await navigator.mediaDevices.getUserMedia(basicConstraints);
        }
    }

    // Dual remote audio delivery: HTML5 <audio> element + Web Audio Destination fallback
    function attachCustRemoteAudio(stream) {
        const remoteAudio = document.getElementById('remoteAudio');
        if (remoteAudio) {
            remoteAudio.srcObject = stream;
            remoteAudio.muted = false;
            remoteAudio.volume = 1.0;
            const playPromise = remoteAudio.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    console.log('[WebRTC Cust] Audio playing via HTML5 audio element.');
                }).catch(async (playErr) => {
                    console.warn('[WebRTC Cust] HTML5 audio play blocked, engaging Web Audio API destination:', playErr);
                    try {
                        const ctx = getCustWebRtcAudioContext();
                        if (ctx) {
                            await ctx.resume();
                            if (custRemoteAudioSourceNode) {
                                try { custRemoteAudioSourceNode.disconnect(); } catch (e) {}
                            }
                            custRemoteAudioSourceNode = ctx.createMediaStreamSource(stream);
                            custRemoteAudioSourceNode.connect(ctx.destination);
                            console.log('[WebRTC Cust] Web Audio route connected successfully.');
                        }
                    } catch (webaudioErr) {
                        console.error('[WebRTC Cust] Web Audio fallback failed:', webaudioErr);
                    }
                });
            }
        }
    }

    async function startWebRtcCall(type) {
        unlockCustAudioPlayback();
        currentCallType = type;
        pendingOfferSignal = null;
        queuedCandidates = [];

        const overlay = document.getElementById('callOverlay');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        document.getElementById('callDuration').textContent = 'Calling specialist...';
        document.getElementById('callTypeIcon').className = type === 'video' ? 'fa-solid fa-video' : 'fa-solid fa-phone';

        if (type === 'video') {
            document.getElementById('videoContainer').classList.remove('hidden');
            document.getElementById('videoContainer').classList.add('flex');
        } else {
            document.getElementById('videoContainer').classList.add('hidden');
            document.getElementById('videoContainer').classList.remove('flex');
        }

        playCallRingtone();
        startFastSignalPolling();

        try {
            localStream = await getCustMediaStreamWithFallback(type);

            if (type === 'video') {
                const localVid = document.getElementById('localVideo');
                if (localVid) localVid.srcObject = localStream;
            }

            peerConnection = new RTCPeerConnection(rtcConfig);
            localStream.getTracks().forEach(track => {
                track.enabled = true;
                peerConnection.addTrack(track, localStream);
            });

            peerConnection.ontrack = (event) => {
                stopCallRingtone();
                const track = event.track;
                const stream = (event.streams && event.streams[0]) ? event.streams[0] : null;

                if (track.kind === 'audio') {
                    const audioStream = stream || new MediaStream([track]);
                    attachCustRemoteAudio(audioStream);
                } else if (track.kind === 'video') {
                    const videoStream = stream || new MediaStream([track]);
                    const remoteVid = document.getElementById('remoteVideo');
                    if (remoteVid) {
                        remoteVid.srcObject = videoStream;
                        remoteVid.play().catch(e => console.warn('Video play request:', e));
                    }
                }
                startCallTimer();
            };

            peerConnection.oniceconnectionstatechange = () => {
                console.log('[WebRTC Cust] ICE State:', peerConnection.iceConnectionState);
                if (peerConnection.iceConnectionState === 'connected' || peerConnection.iceConnectionState === 'completed') {
                    stopCallRingtone();
                } else if (peerConnection.iceConnectionState === 'failed') {
                    console.warn('[WebRTC Cust] ICE failed, attempting restart...');
                    if (typeof peerConnection.restartIce === 'function') {
                        peerConnection.restartIce();
                    }
                }
            };

            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignal('candidate', JSON.stringify(event.candidate), type);
                }
            };

            // 1. Notify server call has started
            await sendSignal('call_start', '', type);

            // 2. Create offer with explicit receive audio option
            const offer = await peerConnection.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: type === 'video'
            });
            await peerConnection.setLocalDescription(offer);

            // 3. Send offer payload
            await sendSignal('offer', JSON.stringify(offer), type);

        } catch (err) {
            console.error('Call media error:', err);
            stopCallRingtone();
            stopFastSignalPolling();
            alert('Could not access microphone/camera. Please grant permission.');
            hangupCall();
        }
    }

    async function pollWebRtcSignals() {
        try {
            const threadParam = (currentThread && currentThread.id) ? `&thread_id=${currentThread.id}` : '';
            const res = await fetch(`live_chat_api.php?action=fetch_signals&receiver=customer${threadParam}`);
            const data = await res.json();
            if (data.status === 'success' && data.signals && data.signals.length > 0) {
                for (const sig of data.signals) {
                    await handleIncomingSignal(sig);
                }
            }
        } catch (e) {}
    }

    async function handleIncomingSignal(sig) {
        if (sig.signal_type === 'call_start') {
            currentCallType = sig.call_type || 'audio';
            startFastSignalPolling();
            playCallRingtone();

            const modal = document.getElementById('incomingCallModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.getElementById('incomingCallIcon').className = currentCallType === 'video' ? 'fa-solid fa-video' : 'fa-solid fa-phone';
                document.getElementById('incomingCallSubtitle').textContent = `Incoming ${currentCallType} call from Store Support...`;
            }
        } else if (sig.signal_type === 'offer') {
            pendingOfferSignal = sig.payload;
            if (peerConnection && peerConnection.signalingState !== 'closed') {
                try {
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(JSON.parse(sig.payload)));
                    const answer = await peerConnection.createAnswer({
                        offerToReceiveAudio: true,
                        offerToReceiveVideo: currentCallType === 'video'
                    });
                    await peerConnection.setLocalDescription(answer);
                    sendSignal('answer', JSON.stringify(answer), sig.call_type || currentCallType);
                    await drainQueuedCandidates(peerConnection);
                } catch (e) {
                    console.error('Error handling offer:', e);
                }
            }
        } else if (sig.signal_type === 'answer') {
            stopCallRingtone();
            if (peerConnection && peerConnection.signalingState === 'have-local-offer') {
                try {
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(JSON.parse(sig.payload)));
                    await drainQueuedCandidates(peerConnection);
                } catch (e) {
                    console.error('Error handling answer:', e);
                }
            }
        } else if (sig.signal_type === 'candidate') {
            try {
                const cand = JSON.parse(sig.payload);
                if (cand && (cand.candidate || cand.candidate === '')) {
                    if (peerConnection && peerConnection.remoteDescription && peerConnection.remoteDescription.type) {
                        await peerConnection.addIceCandidate(new RTCIceCandidate(cand));
                    } else {
                        queuedCandidates.push(cand);
                    }
                }
            } catch (e) {}
        } else if (sig.signal_type === 'call_end') {
            hangupCall(false);
        }
    }

    async function drainQueuedCandidates(pc) {
        if (!pc || !pc.remoteDescription) return;
        const candidates = [...queuedCandidates];
        queuedCandidates = [];
        for (const cand of candidates) {
            try {
                if (cand && (cand.candidate || cand.candidate === '')) {
                    await pc.addIceCandidate(new RTCIceCandidate(cand));
                }
            } catch (e) {
                console.warn('[WebRTC Cust] Candidate add error:', e);
            }
        }
    async function handleAutoAnswerFlowCustomer() {
        console.log('[WebRTC Cust] Auto-answering incoming call...');
        unlockCustAudioPlayback();
        try {
            const threadParam = (currentThread && currentThread.id) ? `&thread_id=${currentThread.id}` : '';
            const res = await fetch(`live_chat_api.php?action=get_call_status${threadParam}`);
            const data = await res.json();
            if (data.status === 'success' && data.has_call) {
                currentCallType = data.call_type || 'audio';
                if (data.offer) {
                    pendingOfferSignal = data.offer;
                }
                await acceptIncomingCall();
                return;
            }
        } catch (e) {
            console.warn('[WebRTC Cust] Auto-answer check error:', e);
        }

        // Fallback: poll signals and answer if incoming modal opened
        await pollWebRtcSignals();
        const modal = document.getElementById('incomingCallModal');
        if (modal && !modal.classList.contains('hidden')) {
            await acceptIncomingCall();
        }
    }

    async function acceptIncomingCall() {
        unlockCustAudioPlayback();
        stopCallRingtone();
        const incomingModal = document.getElementById('incomingCallModal');
        if (incomingModal) {
            incomingModal.classList.add('hidden');
            incomingModal.classList.remove('flex');
        }

        const overlay = document.getElementById('callOverlay');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        document.getElementById('callTypeIcon').className = currentCallType === 'video' ? 'fa-solid fa-video' : 'fa-solid fa-phone';
        document.getElementById('callDuration').textContent = 'Connecting...';

        if (currentCallType === 'video') {
            document.getElementById('videoContainer').classList.remove('hidden');
            document.getElementById('videoContainer').classList.add('flex');
        } else {
            document.getElementById('videoContainer').classList.add('hidden');
            document.getElementById('videoContainer').classList.remove('flex');
        }

        try {
            localStream = await getCustMediaStreamWithFallback(currentCallType);

            if (currentCallType === 'video') {
                const localVid = document.getElementById('localVideo');
                if (localVid) localVid.srcObject = localStream;
            }

            peerConnection = new RTCPeerConnection(rtcConfig);
            localStream.getTracks().forEach(track => {
                track.enabled = true;
                peerConnection.addTrack(track, localStream);
            });

            peerConnection.ontrack = (event) => {
                stopCallRingtone();
                const track = event.track;
                const stream = (event.streams && event.streams[0]) ? event.streams[0] : null;

                if (track.kind === 'audio') {
                    const audioStream = stream || new MediaStream([track]);
                    attachCustRemoteAudio(audioStream);
                } else if (track.kind === 'video') {
                    const videoStream = stream || new MediaStream([track]);
                    const remoteVid = document.getElementById('remoteVideo');
                    if (remoteVid) {
                        remoteVid.srcObject = videoStream;
                        remoteVid.play().catch(e => console.warn('Video play request:', e));
                    }
                }
                startCallTimer();
            };

            peerConnection.oniceconnectionstatechange = () => {
                console.log('[WebRTC Cust] ICE State:', peerConnection.iceConnectionState);
                if (peerConnection.iceConnectionState === 'connected' || peerConnection.iceConnectionState === 'completed') {
                    stopCallRingtone();
                } else if (peerConnection.iceConnectionState === 'failed') {
                    console.warn('[WebRTC Cust] ICE failed, attempting restart...');
                    if (typeof peerConnection.restartIce === 'function') {
                        peerConnection.restartIce();
                    }
                }
            };

            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignal('candidate', JSON.stringify(event.candidate), currentCallType);
                }
            };

            if (!pendingOfferSignal) {
                try {
                    const threadParam = (currentThread && currentThread.id) ? `&thread_id=${currentThread.id}` : '';
                    const qRes = await fetch(`live_chat_api.php?action=get_call_status${threadParam}`);
                    const qData = await qRes.json();
                    if (qData.status === 'success' && qData.offer) {
                        pendingOfferSignal = qData.offer;
                    }
                } catch (e) {}
            }

            if (pendingOfferSignal) {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(JSON.parse(pendingOfferSignal)));
                const answer = await peerConnection.createAnswer({
                    offerToReceiveAudio: true,
                    offerToReceiveVideo: currentCallType === 'video'
                });
                await peerConnection.setLocalDescription(answer);
                sendSignal('answer', JSON.stringify(answer), currentCallType);
                await drainQueuedCandidates(peerConnection);
            } else {
                console.log('[WebRTC Cust] Waiting for remote offer signal...');
                startFastSignalPolling();
            }

        } catch (e) {
            console.error('Answer call error:', e);
            alert('Could not access microphone/camera. Call disconnected.');
            hangupCall();
        }
    }

    function declineIncomingCall() {
        stopCallRingtone();
        stopFastSignalPolling();
        const incomingModal = document.getElementById('incomingCallModal');
        if (incomingModal) {
            incomingModal.classList.add('hidden');
            incomingModal.classList.remove('flex');
        }
        sendSignal('call_end', JSON.stringify({ status: 'declined' }), currentCallType);
        pendingOfferSignal = null;
        queuedCandidates = [];
        setTimeout(initChatState, 800);
    }

    async function sendSignal(type, payload, callType) {
        try {
            const fd = new FormData();
            fd.append('sender', 'customer');
            if (currentThread && currentThread.id) {
                fd.append('thread_id', currentThread.id);
            }
            fd.append('signal_type', type);
            fd.append('call_type', callType || currentCallType);
            fd.append('payload', payload || '');
            await fetch('live_chat_api.php?action=call_signal', { method: 'POST', body: fd });
        } catch (e) {}
    }

    function toggleMuteAudio() {
        if (!localStream) return;
        isMutedAudio = !isMutedAudio;
        localStream.getAudioTracks().forEach(t => t.enabled = !isMutedAudio);
        document.getElementById('micIcon').className = isMutedAudio ? 'fa-solid fa-microphone-slash text-rose-400' : 'fa-solid fa-microphone';
    }

    function toggleMuteVideo() {
        if (!localStream) return;
        isMutedVideo = !isMutedVideo;
        localStream.getVideoTracks().forEach(t => t.enabled = !isMutedVideo);
        document.getElementById('camIcon').className = isMutedVideo ? 'fa-solid fa-video-slash text-rose-400' : 'fa-solid fa-video';
    }

    function startCallTimer() {
        callStartTime = Date.now();
        if (callTimerInterval) clearInterval(callTimerInterval);
        callTimerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
            const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const secs = String(elapsed % 60).padStart(2, '0');
            document.getElementById('callDuration').textContent = `${mins}:${secs}`;
        }, 1000);
    }

    function hangupCall(notifyPeer = true) {
        stopCallRingtone();
        stopFastSignalPolling();

        let callPayload = '';
        if (callStartTime) {
            const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
            const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
            const secs = String(elapsed % 60).padStart(2, '0');
            callPayload = JSON.stringify({ status: 'ended', duration: `${mins}:${secs}`, elapsed: elapsed });
        } else {
            callPayload = JSON.stringify({ status: 'cancelled' });
        }

        if (notifyPeer) {
            sendSignal('call_end', callPayload, currentCallType);
        }

        callStartTime = null;

        if (callTimerInterval) {
            clearInterval(callTimerInterval);
            callTimerInterval = null;
        }

        if (localStream) {
            localStream.getTracks().forEach(t => t.stop());
            localStream = null;
        }

        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }

        if (custRemoteAudioSourceNode) {
            try { custRemoteAudioSourceNode.disconnect(); } catch (e) {}
            custRemoteAudioSourceNode = null;
        }
        const remoteAudio = document.getElementById('remoteAudio');
        if (remoteAudio) {
            remoteAudio.srcObject = null;
        }
        const remoteVid = document.getElementById('remoteVideo');
        if (remoteVid) {
            remoteVid.srcObject = null;
        }

        pendingOfferSignal = null;
        queuedCandidates = [];

        const incomingModal = document.getElementById('incomingCallModal');
        if (incomingModal) {
            incomingModal.classList.add('hidden');
            incomingModal.classList.remove('flex');
        }

        document.getElementById('callOverlay').classList.add('hidden');
        document.getElementById('callDuration').textContent = 'Connecting...';
    }

    // Start on load
    document.addEventListener('DOMContentLoaded', initChatState);
</script>
</body>
</html>
