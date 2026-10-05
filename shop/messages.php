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

$site_name = $settings['meta_title_home'] ?? 'ShopNext';
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
    $chat_messenger_url = "https://m.me/shopnext";
} elseif (!preg_match('/^https?:\/\//i', $chat_messenger_url)) {
    $chat_messenger_url = "https://m.me/" . ltrim($chat_messenger_url, '@');
}

$chat_call_enabled = isset($settings['chat_call_enabled']) ? (int)$settings['chat_call_enabled'] : 1;
$is_embed = isset($_GET['embed']) && $_GET['embed'] === '1';

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
<body class="bg-slate-100 flex justify-center items-center min-h-screen text-slate-900 <?php echo $is_embed ? 'p-0 bg-white' : 'p-0 sm:p-4'; ?>">

<!-- MAIN APP CONTAINER -->
<div class="w-full <?php echo $is_embed ? 'max-w-none h-screen rounded-none shadow-none' : 'max-w-md h-screen sm:h-[90vh] sm:rounded-3xl sm:shadow-2xl'; ?> bg-white flex flex-col relative overflow-hidden border border-slate-200">

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
                <p class="font-semibold text-slate-900 mb-1">Hello! How can I assist your shopping today?</p>
                <p>I can recommend best-sellers, find discounts, answer product questions, or add items directly to your cart!</p>
            </div>
        </div>

        <!-- Suggestion Pills -->
        <div id="quickPrompts" class="flex flex-wrap gap-1.5 pt-1 pl-9">
            <button onclick="sendQuickPrompt('What are your top trending products today?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                🔥 Top Trending
            </button>
            <button onclick="sendQuickPrompt('Do you have any discount deals available?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                🏷️ Active Deals
            </button>
            <button onclick="sendQuickPrompt('Can you suggest stylish men and women items?')" class="text-[11px] bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-medium px-3 py-1.5 rounded-full shadow-2xs transition active:scale-95">
                ✨ Style Guide
            </button>
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
    <div id="callOverlay" class="hidden absolute inset-0 bg-slate-950/95 z-50 flex flex-col justify-between p-6 text-white backdrop-blur-md">
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

</div>

<!-- JAVASCRIPT LOGIC & WEBRTC ENGINE -->
<script>
    // State management
    let chatMode = 'ai'; // 'ai' or 'live'
    let currentThread = null;
    let lastMessageId = 0;
    let pollInterval = null;
    let hasSentFirstPrompt = localStorage.getItem('sn_chat_prompt_sent') === 'true';

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
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => appendMessageBubble(msg));
                    lastMessageId = data.messages[data.messages.length - 1].id;
                    scrollChatToBottom();
                }

                // Start polling for new messages & signals
                startPolling();
            }
        } catch (e) {
            console.error('Failed to init chat:', e);
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

                const res = await fetch('gemini_chat.php', {
                    method: 'POST',
                    body: fd
                });
                const aiData = await res.json();
                showTyping(false);

                if (aiData.status === 'success') {
                    appendMessageBubble({
                        sender_type: 'ai',
                        message: aiData.reply || 'Here is what I found for you!',
                        product_data: aiData.product || null
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
        const isUser = msg.sender_type === 'customer';
        const isSystem = msg.sender_type === 'system';

        if (isSystem) {
            appendSystemNotice(msg.message);
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = `flex items-start gap-2.5 ${isUser ? 'justify-end' : 'justify-start'}`;

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
                attachmentHtml = `<div class="mt-2 rounded-xl overflow-hidden border border-slate-200/50"><img src="${msg.attachment_url}" class="w-full max-h-56 object-cover" alt="Attachment" onclick="window.open('${msg.attachment_url}')"></div>`;
            } else {
                attachmentHtml = `<div class="mt-2"><a href="${msg.attachment_url}" target="_blank" class="text-xs text-blue-500 underline flex items-center gap-1"><i class="fa-solid fa-file"></i> View Attachment</a></div>`;
            }
        }

        let productCardHtml = '';
        if (msg.product_data) {
            let p = typeof msg.product_data === 'string' ? JSON.parse(msg.product_data) : msg.product_data;
            productCardHtml = `
                <div class="mt-3 p-2.5 bg-slate-50 border border-slate-200/80 rounded-xl flex items-center gap-3 shadow-2xs">
                    <img src="${p.photo || 'assets/uploads/product_featured_default.jpg'}" class="w-14 h-14 object-cover rounded-lg shrink-0 border border-slate-200" alt="${p.name}">
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-slate-800 text-xs truncate">${p.name}</h4>
                        <div class="text-amber-600 font-bold text-xs mt-0.5">$${p.current_price}</div>
                        <button onclick="quickAddToCart(${p.id}, this)" class="mt-1.5 px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white font-semibold text-[10px] rounded-lg shadow-sm transition active:scale-95 flex items-center gap-1">
                            <i class="fa-solid fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </div>
            `;
        }

        wrap.innerHTML = `
            ${avatar}
            <div class="${bubbleClasses}">
                <p class="whitespace-pre-wrap">${escapeHtml(msg.message)}</p>
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
                btnElement.className = 'mt-1.5 px-3 py-1 bg-emerald-600 text-white font-semibold text-[10px] rounded-lg shadow-sm';
                
                // Update badge
                const badge = document.getElementById('dockCartBadge');
                if (badge) {
                    badge.textContent = data.cart_count || ((parseInt(badge.textContent) || 0) + 1);
                    badge.classList.remove('hidden');
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

    // Image & File Upload
    async function handleAttachmentUpload(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        vanishSocialChannels();

        const fd = new FormData();
        fd.append('attachment', file);

        try {
            showTyping(true);
            const res = await fetch('live_chat_api.php?action=upload_attachment', { method: 'POST', body: fd });
            const data = await res.json();
            showTyping(false);

            if (data.status === 'success') {
                // Post as message
                const msgFd = new FormData();
                msgFd.append('attachment_url', data.url);
                msgFd.append('attachment_type', data.type);
                msgFd.append('message', '');

                const sendRes = await fetch('live_chat_api.php?action=send_message', { method: 'POST', body: msgFd });
                const sendData = await sendRes.json();
                if (sendData.message) {
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
        input.value = '';
    }

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
                        lastMessageId = Math.max(lastMessageId, m.id);
                    });
                    scrollChatToBottom();
                }

                // 2. Fetch incoming WebRTC signals
                pollWebRtcSignals();
            } catch (e) {}
        }, 3000);
    }

    // ==========================================
    // BUFFERLESS WEBRTC AUDIO & VIDEO CALL ENGINE
    // ==========================================
    let peerConnection = null;
    let localStream = null;
    let isMutedAudio = false;
    let isMutedVideo = false;
    let callTimerInterval = null;
    let callStartTime = null;
    let currentCallType = 'audio';

    const rtcConfig = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    };

    async function startWebRtcCall(type) {
        currentCallType = type;
        document.getElementById('callOverlay').classList.remove('hidden');
        document.getElementById('callDuration').textContent = 'Connecting...';
        document.getElementById('callTypeIcon').className = type === 'video' ? 'fa-solid fa-video' : 'fa-solid fa-phone';

        if (type === 'video') {
            document.getElementById('videoContainer').classList.remove('hidden');
        } else {
            document.getElementById('videoContainer').classList.add('hidden');
        }

        try {
            // Bufferless audio constraints (zero-lag echo cancellation & noise suppression)
            const mediaConstraints = {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    latency: 0
                },
                video: type === 'video' ? { width: { ideal: 640 }, height: { ideal: 480 }, frameRate: { ideal: 24 } } : false
            };

            localStream = await navigator.mediaDevices.getUserMedia(mediaConstraints);

            if (type === 'video') {
                document.getElementById('localVideo').srcObject = localStream;
            }

            peerConnection = new RTCPeerConnection(rtcConfig);

            // Add local tracks
            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));

            // Remote track handler
            peerConnection.ontrack = (event) => {
                if (type === 'video') {
                    document.getElementById('remoteVideo').srcObject = event.streams[0];
                } else {
                    document.getElementById('remoteAudio').srcObject = event.streams[0];
                }
                startCallTimer();
            };

            // ICE Candidate trickling
            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignal('candidate', JSON.stringify(event.candidate), type);
                }
            };

            // Create Offer
            const offer = await peerConnection.createOffer();
            await peerConnection.setLocalDescription(offer);

            sendSignal('call_start', '', type);
            sendSignal('offer', JSON.stringify(offer), type);

        } catch (err) {
            console.error('Media error:', err);
            alert('Could not access microphone/camera. Please grant permissions.');
            hangupCall();
        }
    }

    async function pollWebRtcSignals() {
        try {
            const res = await fetch('live_chat_api.php?action=fetch_signals&receiver=customer');
            const data = await res.json();
            if (data.status === 'success' && data.signals && data.signals.length > 0) {
                for (const sig of data.signals) {
                    handleIncomingSignal(sig);
                }
            }
        } catch (e) {}
    }

    async function handleIncomingSignal(sig) {
        if (sig.signal_type === 'call_start') {
            // Incoming call from Admin
            if (confirm(`Incoming ${sig.call_type} call from Store Specialist. Accept?`)) {
                answerCall(sig.call_type);
            } else {
                sendSignal('call_end', '', sig.call_type);
            }
        } else if (sig.signal_type === 'offer' && peerConnection) {
            const offerDesc = JSON.parse(sig.payload);
            await peerConnection.setRemoteDescription(new RTCSessionDescription(offerDesc));
            const answer = await peerConnection.createAnswer();
            await peerConnection.setLocalDescription(answer);
            sendSignal('answer', JSON.stringify(answer), sig.call_type);
        } else if (sig.signal_type === 'answer' && peerConnection) {
            const ansDesc = JSON.parse(sig.payload);
            await peerConnection.setRemoteDescription(new RTCSessionDescription(ansDesc));
        } else if (sig.signal_type === 'candidate' && peerConnection) {
            const cand = JSON.parse(sig.payload);
            await peerConnection.addIceCandidate(new RTCIceCandidate(cand));
        } else if (sig.signal_type === 'call_end') {
            hangupCall(false);
        }
    }

    async function answerCall(type) {
        currentCallType = type;
        document.getElementById('callOverlay').classList.remove('hidden');
        document.getElementById('callTypeIcon').className = type === 'video' ? 'fa-solid fa-video' : 'fa-solid fa-phone';

        if (type === 'video') {
            document.getElementById('videoContainer').classList.remove('hidden');
        } else {
            document.getElementById('videoContainer').classList.add('hidden');
        }

        try {
            const mediaConstraints = {
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
                video: type === 'video' ? { width: { ideal: 640 }, height: { ideal: 480 } } : false
            };

            localStream = await navigator.mediaDevices.getUserMedia(mediaConstraints);
            if (type === 'video') {
                document.getElementById('localVideo').srcObject = localStream;
            }

            peerConnection = new RTCPeerConnection(rtcConfig);
            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));

            peerConnection.ontrack = (event) => {
                if (type === 'video') {
                    document.getElementById('remoteVideo').srcObject = event.streams[0];
                } else {
                    document.getElementById('remoteAudio').srcObject = event.streams[0];
                }
                startCallTimer();
            };

            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignal('candidate', JSON.stringify(event.candidate), type);
                }
            };
        } catch (e) {
            console.error('Answer call error:', e);
            hangupCall();
        }
    }

    async function sendSignal(type, payload, callType) {
        try {
            const fd = new FormData();
            fd.append('sender', 'customer');
            fd.append('signal_type', type);
            fd.append('call_type', callType);
            fd.append('payload', payload);
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
        if (notifyPeer) {
            sendSignal('call_end', '', currentCallType);
        }

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

        document.getElementById('callOverlay').classList.add('hidden');
        document.getElementById('callDuration').textContent = 'Connecting...';
    }

    // Start on load
    document.addEventListener('DOMContentLoaded', initChatState);
</script>
</body>
</html>
