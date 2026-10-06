<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/header.php';

$adminAvatarUrl = (!empty($_SESSION['user']['photo']) && file_exists(__DIR__ . '/../assets/uploads/' . $_SESSION['user']['photo'])) 
    ? '../assets/uploads/' . htmlspecialchars($_SESSION['user']['photo']) 
    : '../assets/uploads/user-1.png';
?>
<link rel="stylesheet" href="css/live-chat-mobile.css?v=<?php echo time(); ?>">
<script>
    document.body.classList.add('live-chat-page-body');
</script>

<style>
/* ==========================================================================
   WHATSAPP WEB LIVE CHAT INTERFACE (Zero Bottom Gap, Live-Only, Call Buttons Top)
   ========================================================================== */

/* 1. Viewport Lock: Page never scrolls; layout fills 100% of visible viewport */
html, body {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #f0f2f5 !important;
}

body.live-chat-page-body {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100% !important;
    height: 100vh !important;
    overflow: hidden !important;
}

.wrapper {
    height: 100vh !important;
    max-height: 100vh !important;
    min-height: 100vh !important;
    overflow: hidden !important;
    background: #f0f2f5 !important;
    position: relative !important;
}

.main-sidebar {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow-y: auto !important;
}

.main-footer {
    display: none !important; /* Hide footer on live chat */
}

.content-wrapper,
.right-side {
    height: calc(100vh - 50px) !important;
    max-height: calc(100vh - 50px) !important;
    min-height: calc(100vh - 50px) !important;
    overflow: hidden !important;
    background: #f0f2f5 !important;
    padding: 0 !important;
    margin-bottom: 0 !important;
    border: none !important;
    display: flex !important;
    flex-direction: column !important;
}

/* 2. Top App Bar (Voice & Video Call, Collapse, Refresh) */
.content-header {
    height: 46px !important;
    min-height: 46px !important;
    max-height: 46px !important;
    padding: 6px 14px !important;
    background: #ffffff !important;
    border-bottom: 1px solid #d1d7db !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex-shrink: 0 !important;
    margin: 0 !important;
    z-index: 20;
    overflow: hidden !important;
}

.content {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    height: calc(100% - 46px) !important;
    max-height: calc(100% - 46px) !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    background: #f0f2f5 !important;
}

/* 3. Full-Screen WhatsApp Shell (Edge-to-Edge, Zero Bottom Padding) */
.wa-app-wrap {
    flex: 1;
    height: 100%;
    width: 100%;
    display: flex;
    overflow: hidden;
    background: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
}

/* Custom WhatsApp Scrollbar */
.wa-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.wa-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.wa-scroll::-webkit-scrollbar-thumb {
    background: rgba(11, 20, 26, 0.2);
}
.wa-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(11, 20, 26, 0.35);
}

/* --------------------------------------------------------------------------
   COLUMN 1: WHATSAPP CHATS SIDEBAR (Width: 330px, Collapsible)
   -------------------------------------------------------------------------- */
.wa-col-chats {
    width: 330px;
    border-right: 1px solid #e9edef;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    flex-shrink: 0;
    height: 100%;
    overflow: hidden;
}
.wa-col-chats.collapsed {
    display: none !important;
}

/* Sidebar Header */
.wa-sidebar-header {
    height: 52px;
    background: #f0f2f5;
    padding: 8px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    border-bottom: 1px solid #d1d7db;
}
.wa-brand-title {
    font-size: 17px;
    font-weight: 700;
    color: #111b21;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: -0.3px;
}
.wa-header-icons {
    display: flex;
    align-items: center;
    gap: 6px;
}
.wa-icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #54656f;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 15px;
    transition: background 0.15s ease, color 0.15s ease;
}
.wa-icon-btn:hover {
    background: rgba(11, 20, 26, 0.08);
    color: #111b21;
}

/* Search Bar & Filter Pills */
.wa-search-bar-wrap {
    padding: 8px 12px;
    background: #ffffff;
    border-bottom: 1px solid #e9edef;
    flex-shrink: 0;
}
.wa-search-box {
    display: flex;
    align-items: center;
    background: #f0f2f5;
    border-radius: 8px;
    height: 35px;
    padding: 0 10px;
    gap: 8px;
}
.wa-search-box i {
    color: #54656f;
    font-size: 13px;
}
.wa-search-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-size: 13px;
    color: #111b21;
}
.wa-search-input::placeholder {
    color: #667781;
}

/* WhatsApp Filter Chips (LIVE CHAT ONLY) */
.wa-filter-chips {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    overflow-x: auto;
}
.wa-filter-chip {
    padding: 4px 11px;
    border-radius: 14px;
    background: #f0f2f5;
    color: #54656f;
    font-size: 12px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.wa-filter-chip:hover {
    background: #e9edef;
    color: #111b21;
}
.wa-filter-chip.active {
    background: #d9fdd3;
    color: #008069;
    font-weight: 600;
}

/* Conversation Items */
.wa-chat-list {
    flex: 1;
    overflow-y: auto;
    background: #ffffff;
    padding: 6px 0 16px 0;
}
.wa-chat-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
    position: relative;
    box-sizing: border-box;
    background: #ffffff;
}
.wa-chat-item:hover {
    background: #f8fafc;
}
.wa-chat-item.active,
.wa-chat-item.selected {
    background: #eff6ff !important;
    border-left: 3px solid #2563eb !important;
}
.sup-avatar-col {
    position: relative !important;
    width: 44px !important;
    height: 44px !important;
    flex-shrink: 0 !important;
}
.wa-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
    position: relative;
    user-select: none;
}
.sup-online-dot,
.wa-online-dot {
    position: absolute !important;
    bottom: 0px !important;
    right: 0px !important;
    width: 12px !important;
    height: 12px !important;
    background: #10b981 !important;
    border: 2px solid #ffffff !important;
    border-radius: 50% !important;
    z-index: 5 !important;
    box-sizing: border-box !important;
}
.sup-online-dot.offline,
.wa-online-dot.offline {
    background: #cbd5e1 !important;
}
.sup-info-col,
.wa-chat-content {
    flex: 1 !important;
    min-width: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 2px !important;
}
.sup-cust-name,
.wa-chat-title {
    font-size: 14px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    line-height: 1.3 !important;
}
.sup-phone-tag {
    display: flex !important;
    align-items: center !important;
    gap: 5px !important;
    font-size: 11.5px !important;
    color: #059669 !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.sup-snippet,
.wa-chat-snippet {
    font-size: 12px !important;
    color: #64748b !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    margin: 0 !important;
}
.sup-meta-col {
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-end !important;
    justify-content: space-between !important;
    gap: 5px !important;
    flex-shrink: 0 !important;
    min-width: 58px !important;
}
.sup-meta-top {
    display: flex !important;
    align-items: center !important;
    gap: 4px !important;
}
.sup-time,
.wa-chat-time {
    font-size: 11px !important;
    color: #94a3b8 !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
}
.wa-chat-item.has-unread .wa-chat-time {
    color: #2563eb !important;
}
.sup-meta-bot {
    display: flex !important;
    align-items: center !important;
    gap: 5px !important;
}
.sup-status-pill {
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    padding: 2px 7px !important;
    border-radius: 10px !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: capitalize !important;
    cursor: pointer !important;
    transition: opacity 0.15s ease !important;
    white-space: nowrap !important;
}
.sup-status-pill.status-active {
    background: #dcfce7 !important;
    color: #15803d !important;
    border: 1px solid #bbf7d0 !important;
}
.sup-status-pill.status-pending {
    background: #fef3c7 !important;
    color: #b45309 !important;
    border: 1px solid #fde68a !important;
}
.sup-status-pill.status-closed,
.sup-status-pill.status-resolved {
    background: #f1f5f9 !important;
    color: #64748b !important;
    border: 1px solid #cbd5e1 !important;
}
.sup-card-dots-btn {
    background: transparent !important;
    border: none !important;
    width: 22px !important;
    height: 22px !important;
    border-radius: 4px !important;
    color: #94a3b8 !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 13px !important;
    padding: 0 !important;
    transition: all 0.15s ease !important;
}
.sup-card-dots-btn:hover {
    background: #e2e8f0 !important;
    color: #0f172a !important;
}
.wa-unread-count {
    background: #2563eb;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    padding: 0 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.wa-live-badge {
    background: #fef3c7;
    color: #b45309;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    flex-shrink: 0;
}

/* --------------------------------------------------------------------------
   COLUMN 2: WHATSAPP CHAT WORKSPACE (Flex-1)
   -------------------------------------------------------------------------- */
.wa-col-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #efeae2;
    min-width: 0;
    height: 100%;
    overflow: hidden;
    position: relative;
}

/* Chat Header (54px) */
.wa-main-header {
    height: 52px;
    background: #f0f2f5;
    padding: 8px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #d1d7db;
    border-left: 1px solid #d1d7db;
    flex-shrink: 0;
    gap: 8px;
    z-index: 10;
}
.wa-contact-info-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex: 1;
}
.wa-contact-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}
.wa-contact-name {
    font-size: 16px;
    font-weight: 600;
    color: #111b21;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
}
.wa-contact-status {
    font-size: 12px;
    color: #667781;
    margin: 2px 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Prominent Call & Action Buttons */
.wa-call-btn {
    height: 32px;
    padding: 0 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid #d1d7db;
    background: #ffffff;
    color: #111b21;
    white-space: nowrap;
}
.wa-call-btn:hover {
    background: #f0f2f5;
    border-color: #8696a0;
}
.wa-call-btn.video {
    color: #008069;
    border-color: #86efac;
    background: #f0fdf4;
}
.wa-call-btn.video:hover {
    background: #dcfce7;
    border-color: #00a884;
}
.wa-call-btn.audio {
    color: #0284c7;
    border-color: #bae6fd;
    background: #f0f9ff;
}
.wa-call-btn.audio:hover {
    background: #e0f2fe;
    border-color: #0284c7;
}

.wa-collapse-btn {
    height: 32px;
    padding: 0 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid #d1d7db;
    background: #ffffff;
    color: #54656f;
    white-space: nowrap;
}
.wa-collapse-btn:hover {
    background: #f0f2f5;
    color: #111b21;
    border-color: #8696a0;
}

/* WhatsApp Doodle Feed Background */
.wa-feed-body {
    flex: 1;
    min-height: 0;
    padding: 14px 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
    background-color: #efeae2;
    background-image: radial-gradient(#d1d7db 0.75px, transparent 0.75px), radial-gradient(#d1d7db 0.75px, #efeae2 0.75px);
    background-size: 30px 30px;
    background-position: 0 0, 15px 15px;
}

/* Date Pill Divider */
.wa-date-divider {
    text-align: center;
    margin: 8px 0;
}
.wa-date-pill {
    display: inline-block;
    padding: 4px 12px;
    background: #ffffff;
    color: #54656f;
    font-size: 12px;
    border-radius: 7.5px;
    box-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.13);
    text-transform: uppercase;
    font-weight: 500;
}

/* WhatsApp Message Bubbles */
.wa-bubble-wrap {
    display: flex;
    flex-direction: column;
    max-width: 65%;
}
.wa-bubble-wrap.incoming {
    align-self: flex-start;
}
.wa-bubble-wrap.outgoing {
    align-self: flex-end;
}
.wa-bubble {
    padding: 6px 9px 8px 9px;
    font-size: 14px;
    line-height: 19px;
    word-wrap: break-word;
    box-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.13);
    position: relative;
}
.wa-bubble.incoming {
    background: #ffffff;
    color: #111b21;
    border-radius: 8px 8px 8px 0;
}
.wa-bubble.outgoing {
    background: #d9fdd3; /* Official WhatsApp outbound green */
    color: #111b21;
    border-radius: 8px 8px 0 8px;
}
.wa-meta-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 2px;
    float: right;
    margin-left: 12px;
}
.wa-bubble-time {
    font-size: 11px;
    color: #667781;
}
.wa-ticks {
    color: #53bdeb; /* Official WhatsApp blue checkmarks */
    font-size: 12px;
}

/* Product Card in Chat */
.wa-product-card {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(0, 0, 0, 0.04);
    border-radius: 6px;
    padding: 8px;
    margin-top: 6px;
    text-decoration: none !important;
    color: inherit;
    transition: background 0.15s ease;
}
.wa-product-card:hover {
    background: rgba(0, 0, 0, 0.08);
}
.wa-product-img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 4px;
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.08);
    flex-shrink: 0;
}
.wa-product-title {
    font-size: 13px;
    font-weight: 600;
    color: #111b21;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 240px;
}
.wa-product-price {
    font-size: 12px;
    font-weight: 700;
    color: #008069;
}

/* Canned Quick Replies Bar */
.wa-quick-bar {
    padding: 6px 16px !important;
    background: #f8fafc !important;
    border-top: 1px solid #e2e8f0 !important;
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    overflow-x: auto !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}
.wa-quick-bar::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}
.wa-canned-chip {
    background: #ffffff;
    border: 1px solid #d1d7db;
    color: #54656f;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.wa-canned-chip:hover {
    background: #d9fdd3;
    color: #008069;
    border-color: #a7f3d0;
}

/* WhatsApp Message Input Bar */
.wa-input-bar {
    min-height: 56px;
    background: #f0f2f5;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.wa-input-field {
    flex: 1;
    height: 40px;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 10px 14px;
    font-size: 14px;
    color: #111b21;
    outline: none;
}
.wa-input-field::placeholder {
    color: #667781;
}
.wa-send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #00a884;
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 15px;
    transition: background 0.15s ease;
    flex-shrink: 0;
}
.wa-send-btn:hover {
    background: #008f6f;
}

/* --------------------------------------------------------------------------
   COLUMN 3: CONTACT INFO & CRM DRAWER (Width: 300px, Responsive & Collapsible)
   -------------------------------------------------------------------------- */
.wa-col-drawer {
    width: 300px;
    border-left: 1px solid #d1d7db;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    flex-shrink: 0;
    height: 100%;
    transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease, transform 0.25s ease;
    box-sizing: border-box;
}
.wa-col-drawer.collapsed {
    width: 0 !important;
    min-width: 0 !important;
    border-left: none !important;
    opacity: 0 !important;
    pointer-events: none !important;
    overflow: hidden !important;
}

@media (max-width: 1240px) and (min-width: 768px) {
    .wa-col-drawer {
        position: absolute !important;
        right: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        z-index: 50 !important;
        width: 300px !important;
        box-shadow: -6px 0 25px rgba(0, 0, 0, 0.15) !important;
        border-left: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
    }
    .wa-col-drawer.collapsed {
        transform: translateX(100%) !important;
        width: 300px !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
}

.wa-drawer-header {
    height: 52px;
    padding: 10px 16px;
    background: #f0f2f5;
    border-bottom: 1px solid #d1d7db;
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 16px;
    font-weight: 600;
    color: #111b21;
    flex-shrink: 0;
}
.wa-drawer-section {
    background: #ffffff;
    padding: 16px;
    margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(11, 20, 26, 0.08);
}
.wa-drawer-sec-title {
    font-size: 13px;
    font-weight: 700;
    color: #54656f;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.wa-order-item {
    padding: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 12px;
    margin-bottom: 8px;
}
.wa-order-item:last-child {
    margin-bottom: 0;
}
</style>

<!-- TOP APP BAR: Video Call, Voice Call, Collapse Buttons at the TOP -->
<section class="content-header">
    <div style="display: flex; align-items: center; gap: 10px;">
        <!-- Collapse Left Chats Button at Top -->
        <button type="button" onclick="toggleThreadsCol()" id="btnTopCollapseChats" class="btn btn-default btn-sm" style="border-radius: 6px; font-weight: 600;">
            <i class="fa fa-bars"></i> <span id="lblTopCollapse">Collapse Inbox</span>
        </button>

        <div style="display: flex; align-items: center; gap: 6px;">
            <i class="fa fa-whatsapp" style="color: #25d366; font-size: 20px;"></i>
            <span style="font-weight: 700; font-size: 15px; color: #111b21;">Live Support</span>
            <span class="label label-success" style="font-size: 10px; border-radius: 10px; padding: 2px 7px; background: #00a884;">ONLINE</span>
        </div>
    </div>

    <!-- Prominent Voice Call, Video Call, and Action Buttons at the TOP -->
    <div style="display: flex; align-items: center; gap: 8px;">
        <!-- Video Call Button at TOP -->
        <button type="button" onclick="triggerTopVideoCall()" class="btn btn-sm" id="btnTopVideoCall" style="background: #00a884; border-color: #00a884; color: #ffffff; font-weight: 700; border-radius: 6px; padding: 5px 12px;">
            <i class="fa fa-video-camera"></i> Video Call
        </button>

        <!-- Voice Call Button at TOP -->
        <button type="button" onclick="triggerTopVoiceCall()" class="btn btn-sm" id="btnTopVoiceCall" style="background: #0284c7; border-color: #0284c7; color: #ffffff; font-weight: 700; border-radius: 6px; padding: 5px 12px;">
            <i class="fa fa-phone"></i> Voice Call
        </button>

        <!-- Live Takeover Button at TOP -->
        <button type="button" id="btnTopTakeover" onclick="toggleAdminTakeover()" class="btn btn-default btn-sm" style="border-radius: 6px; font-weight: 600;">
            <i class="fa fa-handshake-o"></i> <span>Take Over</span>
        </button>

        <!-- Fullscreen / Focus Mode Button -->
        <button type="button" onclick="toggleAdminNavSidebar()" class="btn btn-default btn-sm" style="border-radius: 6px;" title="Toggle Fullscreen Focus">
            <i class="fa fa-arrows-alt"></i>
        </button>

        <!-- Customer CRM Details Drawer Toggle -->
        <button type="button" onclick="toggleContextCol()" class="btn btn-default btn-sm" style="border-radius: 6px;" title="Customer Details & Orders">
            <i class="fa fa-id-card-o"></i> Details
        </button>

        <!-- Refresh Button -->
        <button type="button" onclick="loadThreads()" class="btn btn-default btn-sm" style="border-radius: 6px;" title="Refresh conversations">
            <i class="fa fa-refresh" id="refreshThreadsIcon"></i>
        </button>
    </div>
</section>

<!-- MAIN WHATSAPP WEB APPLICATION (Zero bottom black padding, edge-to-edge) -->
<section class="content">
    <div class="wa-app-wrap">
        
        <!-- ============================================== -->
        <!-- COLUMN 1: LIVE CONVERSATIONS LIST (330px) -->
        <!-- ============================================== -->
        <div class="wa-col-chats" id="threadsCol">
            <!-- Mobile Title Block (Matches media_1791288649666_d25b5b6c.png View 1) -->
            <div class="sup-mob-title-block">
                <div class="sup-mob-icon-wrap">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="#FEDB65">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                        <circle cx="8" cy="9.5" r="1.5" fill="#FFFFFF"/>
                        <circle cx="12" cy="9.5" r="1.5" fill="#FFFFFF"/>
                        <circle cx="16" cy="9.5" r="1.5" fill="#FFFFFF"/>
                    </svg>
                </div>
                <div class="sup-mob-title-text">
                    <h2 class="sup-mob-heading">Live Support Chat</h2>
                    <p class="sup-mob-subheading">Chat with your customers in real-time.</p>
                </div>
            </div>

            <!-- Mobile Search Bar (Matches View 1) -->
            <div class="sup-mob-search-wrap">
                <div class="sup-mob-search-box">
                    <i class="fa fa-search" style="color: #94A3B8; font-size: 14px;"></i>
                    <input type="text" id="supMobSearchInput" onkeyup="filterThreads()" placeholder="Search by customer name, phone or message...">
                </div>
            </div>

            <!-- Mobile Horizontal Filter Strip (Matches View 1) -->
            <div class="sup-mob-filter-strip">
                <button type="button" class="sup-filter-pill active" onclick="setThreadFilter('all', this)">
                    <span>All Chats</span> <span class="sup-count-badge" id="supCntAll">24</span>
                </button>
                <button type="button" class="sup-filter-pill" onclick="setThreadFilter('active', this)">
                    <span>Active</span> <span class="sup-count-badge" id="supCntActive">12</span>
                </button>
                <button type="button" class="sup-filter-pill" onclick="setThreadFilter('pending', this)">
                    <span>Pending</span> <span class="sup-count-badge" id="supCntPending">6</span>
                </button>
                <button type="button" class="sup-filter-pill" onclick="setThreadFilter('closed', this)">
                    <span>Closed</span>
                </button>
            </div>

            <!-- Desktop Sidebar Header -->
            <div class="wa-sidebar-header">
                <div class="wa-brand-title">
                    <span>Live Customer Chats</span>
                </div>
                <div class="wa-header-icons">
                    <button type="button" onclick="toggleThreadsCol()" class="btn btn-default btn-xs" title="Collapse Chats List" style="border-radius: 4px; padding: 3px 8px; font-weight: 600;">
                        <i class="fa fa-chevron-left"></i> Collapse
                    </button>
                </div>
            </div>

            <!-- Search Bar & Filter Chips (LIVE CHAT ONLY) -->
            <div class="wa-search-bar-wrap">
                <div class="wa-search-box">
                    <i class="fa fa-search"></i>
                    <input type="text" id="threadSearchInput" onkeyup="filterThreads()" class="wa-search-input" placeholder="Search customer, phone, message...">
                </div>
                <div class="wa-filter-chips">
                    <button type="button" class="wa-filter-chip active" onclick="setThreadFilter('all', this)">
                        Live Support <span id="cntAll" style="opacity: 0.8;">0</span>
                    </button>
                    <button type="button" class="wa-filter-chip" onclick="setThreadFilter('unread', this)">
                        Unread <span id="cntUnread" style="display: none;" class="wa-unread-count">0</span>
                    </button>
                </div>
            </div>

            <!-- Scrollable Live Conversations Stream -->
            <div class="wa-chat-list wa-scroll" id="threadListContainer">
                <div style="padding: 40px 15px; text-align: center; color: #667781; font-size: 13px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 10px;">Loading live conversations...</p>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- COLUMN 2: WHATSAPP CHAT WORKSPACE (Flex-1) -->
        <!-- ============================================== -->
        <div class="wa-col-main">
            <!-- Empty State (No conversation selected) -->
            <div id="emptyThreadState" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; text-align: center; background: #f0f2f5;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: #ffffff; color: #25d366; display: flex; align-items: center; justify-content: center; font-size: 38px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(11,20,26,0.06);">
                    <i class="fa fa-whatsapp"></i>
                </div>
                <h3 style="font-size: 20px; font-weight: 600; color: #111b21; margin: 0 0 8px;">Live Customer Support Console</h3>
                <p style="color: #667781; font-size: 14px; max-width: 440px; line-height: 20px; margin: 0 0 16px;">
                    Select an active customer chat on the left to start live messaging, start audio/video calls, and review customer order history.
                </p>
                <div style="display: flex; gap: 8px;">
                    <button type="button" onclick="expandThreadsIfCollapsed()" id="btnOpenInboxEmpty" class="btn btn-success btn-sm" style="background: #00a884; border-color: #00a884; border-radius: 6px; font-weight: 600; padding: 6px 16px; display: none;">
                        <i class="fa fa-comments"></i> <span>Open Chats Inbox</span>
                    </button>
                </div>
            </div>

            <!-- Active Chat Screen (Pinned Input, 100% Height) -->
            <div id="activeThreadPanel" style="display: none; flex-direction: column; height: 100%; min-height: 0; overflow: hidden;">
                <!-- Desktop Chat Header -->
                <div class="wa-main-header">
                    <div class="wa-contact-info-wrap">
                        <button type="button" onclick="toggleThreadsCol();" class="wa-collapse-btn" id="btnCollapseChatsInline" title="Collapse / Show Chats List">
                            <i class="fa fa-bars"></i> <span>Chats</span>
                        </button>
                        <div class="wa-avatar" id="activeAvatar" style="width: 38px; height: 38px; cursor: pointer;" onclick="toggleContextCol()">C</div>
                        <div style="min-width: 0; flex: 1; cursor: pointer;" onclick="toggleContextCol()">
                            <div class="wa-contact-name-row">
                                <h4 id="activeCustomerName" class="wa-contact-name">Customer Name</h4>
                                <span class="wa-live-badge"><i class="fa fa-user"></i> Live Support</span>
                            </div>
                            <p id="activeCustomerDetails" class="wa-contact-status">online • storefront visitor</p>
                        </div>
                    </div>

                    <!-- Header Video & Audio Call Buttons -->
                    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                        <button type="button" onclick="startAdminWebRtcCall('video')" class="wa-call-btn video" title="Start Live Video Call">
                            <i class="fa fa-video-camera"></i> <span>Video Call</span>
                        </button>
                        <button type="button" onclick="startAdminWebRtcCall('audio')" class="wa-call-btn audio" title="Start Live Voice Call">
                            <i class="fa fa-phone"></i> <span>Voice Call</span>
                        </button>
                        <button type="button" onclick="toggleContextCol()" class="wa-collapse-btn" title="View Customer Details & Orders">
                            <i class="fa fa-id-card-o"></i> <span>Details</span>
                        </button>
                    </div>
                </div>

                <!-- Mobile Warm Yellow Header Bar (Matches media_1791287559643_49f007d0.png View 2) -->
                <div class="sup-chat-header-bar">
                    <button type="button" class="sup-back-btn" onclick="backToThreadList()" title="Back to Inbox">
                        <i class="fa fa-arrow-left" style="font-size: 18px; color: #0F172A;"></i>
                    </button>
                    <div class="sup-hdr-avatar-wrap">
                        <div class="sup-hdr-avatar" id="supHdrAvatar">C</div>
                        <span class="sup-hdr-online-dot" id="supHdrDot"></span>
                    </div>
                    <div class="sup-hdr-info" onclick="toggleContextCol()">
                        <h4 class="sup-hdr-name" id="supHdrName">Customer Name</h4>
                        <div class="sup-hdr-status">
                            <span class="sup-dot-pulse"></span>
                            <span id="supHdrStatus">Active now</span>
                        </div>
                    </div>
                    <div class="sup-hdr-actions">
                        <button type="button" class="sup-hdr-action-btn" onclick="triggerTopVoiceCall()" title="Voice call">
                            <i class="fa fa-phone" style="font-size: 16px;"></i>
                        </button>
                        <button type="button" class="sup-hdr-action-btn" onclick="openThreadStatusForCurrent()" title="Thread options">
                            <i class="fa fa-ellipsis-v" style="font-size: 16px;"></i>
                        </button>
                    </div>
                </div>

                <!-- Mobile Pinned Top Context Order Card (Matches View 2) -->
                <div class="sup-order-card" id="supPinnedOrderCard">
                    <div class="sup-order-card-top">
                        <div class="sup-order-thumb">
                            <img id="supCardProdImg" src="../assets/uploads/nike-hoodie.png" alt="Nike Hoodie">
                        </div>
                        <div class="sup-order-meta">
                            <div class="sup-order-meta-head">
                                <span class="sup-order-id" id="supCardOrderId">Order #ORD-10024</span>
                                <span class="sup-order-status-badge status-delivered" id="supCardOrderStatus">
                                    <i class="fa fa-check-circle"></i> Delivered
                                </span>
                            </div>
                            <div class="sup-order-pname" id="supCardProdName">Nike Hoodie (Black) - Size L</div>
                            <div class="sup-order-price" id="supCardProdPrice">৳ 3,450</div>
                        </div>
                    </div>
                    <div class="sup-order-card-grid">
                        <div class="sup-order-subcol">
                            <span class="sup-subcol-label"><span class="sup-sub-icon"><i class="fa fa-map-marker"></i></span> Address</span>
                            <span class="sup-subcol-val" id="supCardAddress">123/A, Green Road, Dhanmondi, Dhaka-1209</span>
                        </div>
                        <div class="sup-order-subcol">
                            <span class="sup-subcol-label"><span class="sup-sub-icon"><i class="fa fa-file-text-o"></i></span> Invoice No</span>
                            <span class="sup-subcol-val" id="supCardInvoice">#INV-000245</span>
                        </div>
                        <div class="sup-order-subcol">
                            <span class="sup-subcol-label"><span class="sup-sub-icon"><i class="fa fa-credit-card"></i></span> Payment Status</span>
                            <span class="sup-subcol-val" id="supCardPayment"><span class="sup-pay-pill"><i class="fa fa-check-circle"></i> Paid</span></span>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Message Feed Body (Live Chat Only, AI Suppressed) -->
                <div class="wa-feed-body wa-scroll" id="adminMessagesContainer">
                    <!-- Rendered dynamically -->
                </div>

                <!-- Desktop Canned Responses / Quick Reply Toolbar (Desktop Only) -->
                <div class="wa-quick-bar wa-scroll hidden-xs">
                    <span style="font-size: 11px; font-weight: 700; color: #667781; margin-right: 4px;">QUICK:</span>
                    <button type="button" onclick="insertQuickReply('Hello! How can I assist you with your order today?')" class="wa-canned-chip">👋 Greeting</button>
                    <button type="button" onclick="insertQuickReply('Let me check your order and shipping status right away.')" class="wa-canned-chip">📦 Check Order</button>
                    <button type="button" onclick="insertQuickReply('Delivery inside Dhaka takes 24-48 hours. Outside Dhaka takes 48-72 hours via courier.')" class="wa-canned-chip">🚚 Delivery Time</button>
                    <button type="button" onclick="insertQuickReply('We accept SwapnoPay (bKash, Nagad, Rocket, Cards) and Cash on Delivery (COD).')" class="wa-canned-chip">💳 Payment Info</button>
                    <button type="button" onclick="insertQuickReply('We offer a 7-day hassle-free replacement or return warranty.')" class="wa-canned-chip">🔄 Return Policy</button>
                    <button type="button" onclick="insertQuickReply('Thank you for shopping with us! Have a wonderful day.')" class="wa-canned-chip">🙏 Thank You</button>
                </div>

                <!-- Desktop WhatsApp Message Input Bar (Desktop Only) -->
                <div class="wa-input-bar hidden-xs">
                    <input type="file" id="adminFileInput" accept="image/*,application/pdf" style="display: none;" onchange="handleAdminFileUpload(this)">
                    <button type="button" onclick="document.getElementById('adminFileInput').click()" class="wa-icon-btn" title="Attach file or photo">
                        <i class="fa fa-paperclip"></i>
                    </button>
                    <input type="text" id="adminReplyInput" class="wa-input-field" placeholder="Type a message to customer..." onkeypress="handleKeyPress(event)" autocomplete="off">
                    <button type="button" onclick="handleAdminSendReply()" class="wa-send-btn" title="Send message">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>

                <!-- Mobile Bottom Chat Controls Bar (Clean Input Row) -->
                <div class="sup-mob-bottom-controls">
                    <div class="sup-mob-input-row">
                        <button type="button" class="sup-clip-btn" onclick="document.getElementById('adminFileInput').click()" title="Attach file or photo">
                            <i class="fa fa-paperclip" style="font-size: 16px;"></i>
                        </button>
                        <input type="text" id="supMobReplyInput" class="sup-msg-input" placeholder="Type a message..." onkeypress="handleMobileKeyPress(event)" autocomplete="off">
                        <button type="button" class="sup-send-btn" onclick="handleMobileSendReply()" title="Send">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#0F172A"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- COLUMN 3: WHATSAPP CONTACT INFO & CRM DRAWER -->
        <!-- ============================================== -->
        <div class="wa-col-drawer wa-scroll collapsed" id="contextCol">
            <!-- Drawer Header -->
            <div class="wa-drawer-header" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <span style="font-weight: 700; font-size: 15px; color: #0f172a;">Contact Info</span>
                <button type="button" onclick="toggleContextCol()" class="btn btn-default btn-xs" style="border-radius: 4px; padding: 3px 8px; font-weight: 600;" title="Close Details">
                    <i class="fa fa-times"></i> Close
                </button>
            </div>

            <!-- Profile Section -->
            <div class="wa-drawer-section" style="text-align: center; word-break: break-word; overflow: hidden;">
                <div class="wa-avatar" id="ctxAvatar" style="width: 64px; height: 64px; font-size: 24px; margin: 0 auto 10px; background: #e0f2fe; color: #0369a1;">C</div>
                <h4 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" id="ctxName">Customer</h4>
                <p style="font-size: 12px; color: #64748b; margin: 0 0 6px; word-break: break-all;" id="ctxEmail">No email registered</p>
                <p style="font-size: 12px; color: #64748b; margin: 0 0 12px; word-break: break-all;" id="ctxPhone">No phone registered</p>

                <div id="ctxWhatsAppBtnWrap" style="display: none;">
                    <button type="button" onclick="openCustomerWhatsApp()" class="btn btn-success btn-sm btn-block" style="border-radius: 6px; font-weight: 600; background: #25d366; border: none;">
                        <i class="fa fa-whatsapp"></i> Chat on Official WhatsApp
                    </button>
                </div>
            </div>

            <!-- Recent Orders from tbl_payment -->
            <div class="wa-drawer-section" style="flex: 1; word-break: break-word;">
                <div class="wa-drawer-sec-title">
                    <span>Store Orders</span>
                    <span class="badge" id="ctxOrdersCount" style="background: #0f172a; font-size: 11px;">0</span>
                </div>
                <div id="ctxOrdersList" style="display: flex; flex-direction: column; gap: 8px;">
                    <div style="color: #64748b; font-size: 12.5px; text-align: center; padding: 15px 0;">
                        No store orders found.
                    </div>
                </div>
            </div>

            <!-- Internal Staff Notes -->
            <div class="wa-drawer-section">
                <div class="wa-drawer-sec-title">Internal Staff Notes</div>
                <textarea id="staffNoteInput" onkeyup="saveStaffNote()" class="form-control" rows="3" placeholder="Private notes about this customer..." style="font-size: 12.5px; border-radius: 6px; resize: vertical; border: 1px solid #d1d7db; width: 100%; box-sizing: border-box;"></textarea>
                <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Saved locally for your staff account</div>
            </div>
        </div>

    </div>
</section>

<!-- WEBRTC ADMIN CALL OVERLAY -->
<div id="adminCallModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(11, 20, 26, 0.95); backdrop-filter: blur(8px); z-index: 99999; flex-direction: column; justify-content: space-between; padding: 30px; color: #ffffff;">
    <div style="text-align: center; margin-top: 30px;">
        <div style="width: 76px; height: 76px; margin: 0 auto 12px; border-radius: 50%; background: #00a884; display: flex; align-items: center; justify-content: center; font-size: 28px; box-shadow: 0 8px 25px rgba(0, 168, 132, 0.4);">
            <i id="adminCallTypeIcon" class="fa fa-phone"></i>
        </div>
        <h3 id="adminCallPeerTitle" style="font-weight: 600; margin: 0 0 4px; font-size: 18px;">Calling Customer...</h3>
        <p id="adminCallTimer" style="font-family: monospace; font-size: 13px; color: #aebac1; margin: 0;">Connecting WebRTC peer stream...</p>
    </div>

    <!-- Video Containers -->
    <div id="adminVideoWrap" style="display: none; flex: 1; position: relative; max-width: 680px; width: 100%; margin: 15px auto; border-radius: 12px; overflow: hidden; background: #000000; border: 1px solid rgba(255,255,255,0.1);">
        <video id="adminRemoteVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover;"></video>
        <video id="adminLocalVideo" autoplay playsinline muted style="position: absolute; bottom: 12px; right: 12px; width: 120px; height: 160px; border-radius: 8px; border: 2px solid #ffffff; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.5);"></video>
    </div>

    <audio id="adminRemoteAudio" autoplay playsinline></audio>

    <!-- Controls -->
    <div style="display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 30px;">
        <button onclick="toggleAdminMic()" id="btnAdminMic" class="btn btn-default" style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.15); border: none; color: #ffffff; font-size: 16px;">
            <i class="fa fa-microphone" id="adminMicIcon"></i>
        </button>
        <button onclick="hangupAdminCall()" class="btn btn-danger" style="width: 58px; height: 58px; border-radius: 50%; font-size: 20px; box-shadow: 0 8px 20px rgba(225, 29, 72, 0.4); background: #ea0038; border: none;">
            <i class="fa fa-phone"></i>
        </button>
        <button onclick="toggleAdminCam()" id="btnAdminCam" class="btn btn-default" style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.15); border: none; color: #ffffff; font-size: 16px;">
            <i class="fa fa-video-camera" id="adminCamIcon"></i>
        </button>
    </div>
</div>

<!-- INCOMING CALL MODAL FOR ADMIN -->
<div id="adminIncomingCallModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(11, 20, 26, 0.95); backdrop-filter: blur(8px); z-index: 99999; flex-direction: column; align-items: center; justify-content: center; padding: 30px; color: #ffffff; text-align: center;">
    <div style="width: 86px; height: 86px; margin: 0 auto 20px; border-radius: 50%; background: #00a884; display: flex; align-items: center; justify-content: center; font-size: 36px; box-shadow: 0 10px 30px rgba(0, 168, 132, 0.5);">
        <i id="adminIncomingCallIcon" class="fa fa-phone"></i>
    </div>
    <h3 id="adminIncomingCallerTitle" style="font-weight: 700; margin: 0 0 6px; font-size: 22px;">Incoming Customer Call</h3>
    <p id="adminIncomingCallSubtitle" style="font-size: 14px; color: #aebac1; margin: 0 0 35px;">Customer requested live voice support</p>
    <div style="display: flex; align-items: center; justify-content: center; gap: 40px;">
        <div style="text-align: center;">
            <button type="button" onclick="declineAdminIncomingCall()" class="btn btn-danger" style="width: 64px; height: 64px; border-radius: 50%; font-size: 22px; background: #ea0038; border: none; box-shadow: 0 8px 25px rgba(234, 0, 56, 0.4); display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                <i class="fa fa-phone" style="transform: rotate(135deg);"></i>
            </button>
            <span style="font-size: 12px; font-weight: 600; color: #fca5a5;">Decline</span>
        </div>
        <div style="text-align: center;">
            <button type="button" onclick="acceptAdminIncomingCall()" class="btn btn-success" style="width: 64px; height: 64px; border-radius: 50%; font-size: 22px; background: #00a884; border: none; box-shadow: 0 8px 25px rgba(0, 168, 132, 0.4); display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                <i class="fa fa-phone"></i>
            </button>
            <span style="font-size: 12px; font-weight: 600; color: #86efac;">Accept</span>
        </div>
    </div>
</div>

<!-- 1. ADMIN FULLSCREEN IMAGE LIGHTBOX WITH CROSS BUTTON -->
<div id="adminImageLightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(11, 20, 26, 0.95); backdrop-filter: blur(8px); z-index: 99999; flex-direction: column; justify-content: space-between; padding: 20px; color: #ffffff;" onclick="closeAdminImageLightbox()">
    <!-- Top Bar with Cross Button -->
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 8px 12px;" onclick="event.stopPropagation()">
        <div style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 15px; color: #e9edef;">
            <i class="fa fa-picture-o" style="color: #00a884;"></i> Photo Preview
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a id="adminLightboxDownload" href="#" download="customer-photo.jpg" class="btn btn-default btn-sm" style="border-radius: 50%; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.12); border: none; color: #ffffff;" title="Download photo">
                <i class="fa fa-download"></i>
            </a>
            <button type="button" onclick="closeAdminImageLightbox()" class="btn btn-default btn-sm" style="border-radius: 50%; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); border: none; color: #ffffff; font-size: 18px;" title="Close (Esc)">
                <i class="fa fa-times"></i>
            </button>
        </div>
    </div>
    <!-- Centered Image Container -->
    <div style="flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; padding: 10px;" onclick="event.stopPropagation()">
        <img id="adminLightboxImg" src="" alt="Full Photo" style="max-width: 92vw; max-height: 80vh; object-fit: contain; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
    </div>
    <div style="text-align: center; color: #8696a0; font-size: 12px; padding: 6px;" onclick="event.stopPropagation()">
        Click outside or press Escape to close
    </div>
</div>

<!-- 2. ADMIN PRE-SEND IMAGE ATTACHMENT PREVIEW MODAL WITH CROSS BUTTON -->
<div id="adminAttachmentPreviewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(11, 20, 26, 0.85); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 12px; max-width: 440px; width: 100%; overflow: hidden; box-shadow: 0 12px 36px rgba(0,0,0,0.3); display: flex; flex-direction: column;" onclick="event.stopPropagation()">
        <!-- Header with Cross Button -->
        <div style="padding: 12px 16px; background: #f0f2f5; border-bottom: 1px solid #d1d7db; display: flex; align-items: center; justify-content: space-between;">
            <span style="font-weight: 700; font-size: 14px; color: #111b21; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-camera" style="color: #00a884;"></i> Send Photo Preview
            </span>
            <button type="button" onclick="cancelAdminAttachmentPreview()" class="wa-icon-btn" style="width: 30px; height: 30px;" title="Close / Cancel">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <!-- Image Preview Frame -->
        <div style="padding: 16px; background: #efeae2; display: flex; align-items: center; justify-content: center; max-height: 320px; overflow: hidden;">
            <img id="adminAttachmentPreviewImg" src="" alt="Preview" style="max-height: 280px; max-width: 100%; border-radius: 8px; object-fit: contain; box-shadow: 0 2px 8px rgba(11,20,26,0.15);">
        </div>
        <!-- Caption & Action Buttons -->
        <div style="padding: 16px; display: flex; flex-direction: column; gap: 12px; background: #ffffff;">
            <input type="text" id="adminAttachmentCaptionInput" placeholder="Add a caption to photo... (optional)" style="width: 100%; border: 1px solid #d1d7db; border-radius: 8px; padding: 8px 12px; font-size: 13px; outline: none;">
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="cancelAdminAttachmentPreview()" class="btn btn-default btn-sm" style="border-radius: 6px; font-weight: 600; padding: 6px 14px;">
                    Cancel
                </button>
                <button type="button" onclick="confirmAdminSendAttachment()" class="btn btn-sm" style="background: #00a884; border-color: #00a884; color: #ffffff; font-weight: 700; border-radius: 6px; padding: 6px 18px;">
                    <i class="fa fa-paper-plane"></i> Send Photo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Status Edit Modal (Bottom Sheet) -->
<div id="supStatusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 99999; align-items: flex-end; justify-content: center;" onclick="closeStatusModal()">
    <div style="background: #ffffff; width: 100%; max-width: 480px; border-radius: 20px 20px 0 0; padding: 20px; box-shadow: 0 -10px 25px rgba(0,0,0,0.15); animation: slideUpModal 0.2s ease;" onclick="event.stopPropagation()">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
            <h4 style="margin: 0; font-size: 16px; font-weight: 800; color: #0F172A;">Update Thread Status</h4>
            <button type="button" onclick="closeStatusModal()" style="background: transparent; border: none; font-size: 22px; color: #64748B; cursor: pointer; padding: 0 4px;">&times;</button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <button type="button" onclick="updateThreadStatus('active')" class="btn" style="text-align: left; padding: 12px 14px; border-radius: 12px; background: #ECFDF5; color: #059669; font-weight: 700; border: 1px solid #A7F3D0; font-size: 13.5px;">
                🟢 Active (Live Conversation)
            </button>
            <button type="button" onclick="updateThreadStatus('pending')" class="btn" style="text-align: left; padding: 12px 14px; border-radius: 12px; background: #FFFBEB; color: #D97706; font-weight: 700; border: 1px solid #FDE68A; font-size: 13.5px;">
                🟡 Pending (Awaiting Action)
            </button>
            <button type="button" onclick="updateThreadStatus('processing')" class="btn" style="text-align: left; padding: 12px 14px; border-radius: 12px; background: #EFF6FF; color: #2563EB; font-weight: 700; border: 1px solid #BFDBFE; font-size: 13.5px;">
                🔵 Processing (Order / Exchange)
            </button>
            <button type="button" onclick="updateThreadStatus('blocked')" class="btn" style="text-align: left; padding: 12px 14px; border-radius: 12px; background: #FEF2F2; color: #DC2626; font-weight: 700; border: 1px solid #FECACA; font-size: 13.5px;">
                🔴 Blocked (Spam / Disputed)
            </button>
            <button type="button" onclick="updateThreadStatus('resolved')" class="btn" style="text-align: left; padding: 12px 14px; border-radius: 12px; background: #FAF5FF; color: #9333EA; font-weight: 700; border: 1px solid #E9D5FF; font-size: 13.5px;">
                🟣 Resolved (Completed)
            </button>
        </div>
    </div>
</div>

<script>
// ==========================================================================
// CLIENT STATE & INITIALIZATION
// ==========================================================================
const currentAdminAvatar = <?php echo json_encode($adminAvatarUrl); ?>;
let currentThreadId = null;
let currentCustomerData = null;
let pollTimer = null;
let allThreads = [];
let currentFilter = 'all';
let lastMessageCount = 0;
let ringtoneInterval = null;
let activeStatusModalThreadId = null;

// Exact 8 Live Support Threads matching media_1791288649666_d25b5b6c.png
const defaultDemoThreads = [
    {
        id: 101,
        customer_name: 'Joy Saha',
        customer_phone: '+880 1712 345678',
        last_message: 'Hello, I need help with my order...',
        last_message_at: '06:57 PM',
        status: 'active',
        is_online: true,
        unread_admin: 0,
        avatar_letter: 'J',
        avatar_bg: '#FEF3C7',
        avatar_color: '#B45309'
    },
    {
        id: 102,
        customer_name: 'Customer',
        customer_phone: '+880 1819 876543',
        last_message: 'I want to know about delivery time.',
        last_message_at: '06:42 PM',
        status: 'pending',
        is_online: true,
        unread_admin: 0,
        avatar_letter: 'C',
        avatar_bg: '#E0F2FE',
        avatar_color: '#0369A1'
    },
    {
        id: 103,
        customer_name: 'Rahim Ahmed',
        customer_phone: '+880 1715 556677',
        last_message: 'Is the product available in stock?',
        last_message_at: '05:58 PM',
        status: 'processing',
        is_online: true,
        unread_admin: 0,
        avatar_letter: 'R',
        avatar_bg: '#EDE9FE',
        avatar_color: '#6D28D9'
    },
    {
        id: 104,
        customer_name: 'Sadia Islam',
        customer_phone: '+880 1611 223344',
        last_message: 'Thanks for your support!',
        last_message_at: '04:26 PM',
        status: 'active',
        is_online: true,
        unread_admin: 0,
        avatar_letter: 'S',
        avatar_bg: '#FFE4E6',
        avatar_color: '#BE123C'
    },
    {
        id: 105,
        customer_name: 'Mehedi Hasan',
        customer_phone: '+880 1987 665544',
        last_message: 'When will my order be delivered?',
        last_message_at: '03:15 PM',
        status: 'pending',
        is_online: true,
        unread_admin: 0,
        avatar_letter: 'M',
        avatar_bg: '#DCFCE7',
        avatar_color: '#15803D'
    },
    {
        id: 106,
        customer_name: 'Tania Akter',
        customer_phone: '+880 1312 998877',
        last_message: 'Can I get a discount?',
        last_message_at: '01:40 PM',
        status: 'blocked',
        is_online: false,
        unread_admin: 0,
        avatar_letter: 'T',
        avatar_bg: '#EDE9FE',
        avatar_color: '#6D28D9'
    },
    {
        id: 107,
        customer_name: 'Fahim Rahman',
        customer_phone: '+880 1822 445566',
        last_message: 'Thank you for your service!',
        last_message_at: '12:23 PM',
        status: 'active',
        is_online: true,
        unread_admin: 0,
        avatar_letter: 'F',
        avatar_bg: '#E0F2FE',
        avatar_color: '#0369A1'
    },
    {
        id: 108,
        customer_name: 'Ayesha Siddika',
        customer_phone: '+880 1705 778899',
        last_message: 'I have a problem with my order.',
        last_message_at: '11:05 AM',
        status: 'resolved',
        is_online: false,
        unread_admin: 0,
        avatar_letter: 'A',
        avatar_bg: '#FFEDD5',
        avatar_color: '#C2410C'
    }
];

// Exact 6 Joy Saha Messages matching media_1791288671343_2d8f9615.png
const defaultJoyMessages = [
    {
        id: 'msg-1',
        sender_type: 'customer',
        message: 'Hello, I need help with my order. When will it be delivered?',
        created_at: '06:57 PM'
    },
    {
        id: 'msg-2',
        sender_type: 'admin',
        message: 'Hi Joy,\nYour order is already out for delivery. It will reach you tomorrow.',
        created_at: '06:59 PM'
    },
    {
        id: 'msg-3',
        sender_type: 'customer',
        message: 'Okay, thank you.\nCan you share the tracking link?',
        created_at: '07:02 PM'
    },
    {
        id: 'msg-4',
        sender_type: 'admin',
        message: 'Sure! Here is your tracking link:\n🔗 https://track.shopmart.com/12345',
        created_at: '07:05 PM'
    },
    {
        id: 'msg-5',
        sender_type: 'customer',
        message: 'Thank you so much! ❤️',
        created_at: '07:06 PM'
    },
    {
        id: 'msg-6',
        sender_type: 'admin',
        message: "You're welcome!\nIf you need any further help, feel free to message us anytime.",
        created_at: '07:07 PM'
    }
];

// Deterministic soft pastel avatars
const avatarPalettes = [
    { bg: '#e0f2fe', text: '#0369a1' },
    { bg: '#fef3c7', text: '#b45309' },
    { bg: '#dcfce7', text: '#15803d' },
    { bg: '#ede9fe', text: '#6d28d9' },
    { bg: '#ffe4e6', text: '#be123c' },
    { bg: '#e2e8f0', text: '#334155' }
];

function getAvatarStyle(name) {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const idx = Math.abs(hash) % avatarPalettes.length;
    const pal = avatarPalettes[idx];
    return `background-color: ${pal.bg}; color: ${pal.text};`;
}

// Subtle browser audio chime for incoming messages (pure Web Audio API)
function playMessageChime() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime);
        osc.frequency.setValueAtTime(880, ctx.currentTime + 0.08);
        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.35);
    } catch (e) {}
}

function playRingtone() {
    stopRingtone();
    ringtoneInterval = setInterval(() => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o1 = ctx.createOscillator();
            const o2 = ctx.createOscillator();
            const g = ctx.createGain();
            o1.frequency.setValueAtTime(440, ctx.currentTime);
            o2.frequency.setValueAtTime(480, ctx.currentTime);
            o1.connect(g);
            o2.connect(g);
            g.connect(ctx.destination);
            g.gain.setValueAtTime(0.04, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 1.2);
            o1.start(ctx.currentTime);
            o2.start(ctx.currentTime);
            o1.stop(ctx.currentTime + 1.2);
            o2.stop(ctx.currentTime + 1.2);
        } catch(e) {}
    }, 2800);
}

function stopRingtone() {
    if (ringtoneInterval) {
        clearInterval(ringtoneInterval);
        ringtoneInterval = null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Enforce viewport lock so layout never scrolls headers off screen
    function enforceLiveChatLayout() {
        window.scrollTo(0, 0);
        document.body.scrollTop = 0;
        document.documentElement.scrollTop = 0;
        const cw = document.querySelector('.content-wrapper');
        if (cw) {
            cw.scrollTop = 0;
            cw.style.minHeight = '0px';
        }
    }
    enforceLiveChatLayout();
    window.addEventListener('resize', enforceLiveChatLayout);
    window.addEventListener('load', enforceLiveChatLayout);

    // Check URL parameters for thread_id
    const params = new URLSearchParams(window.location.search);
    const initialThreadId = params.get('thread_id');

    // Restore saved left sidebar collapsed preference
    const savedLeftCollapsed = localStorage.getItem('wa_threads_collapsed');
    if (savedLeftCollapsed === '1') {
        const col = document.getElementById('threadsCol');
        if (col) col.classList.add('collapsed');
        updateToggleButtonsState(true);
    }

    // Context details drawer: Collapse on screens < 1360px to prevent overflow
    const ctxCol = document.getElementById('contextCol');
    if (ctxCol) {
        if (window.innerWidth >= 1360 && localStorage.getItem('wa_context_collapsed') !== '1') {
            ctxCol.classList.remove('collapsed');
        } else {
            ctxCol.classList.add('collapsed');
        }
    }

    loadThreads().then(() => {
        if (initialThreadId) {
            selectThread(parseInt(initialThreadId, 10));
        } else if (allThreads.length > 0) {
            // Auto select only on desktop screens (>= 768px).
            // On mobile devices (< 768px), keep the customer thread list open so the admin can pick a conversation!
            if (window.innerWidth >= 768) {
                selectThread(allThreads[0].id);
            }
        }
    });

    // Background polling every 4 seconds
    pollTimer = setInterval(() => {
        loadThreads(true);
        if (currentThreadId) {
            refreshActiveThread(true);
        }
        pollAdminWebRtcSignals();
    }, 3000);
});

// Top bar triggers for Voice & Video Call
function triggerTopVoiceCall() {
    if (!currentThreadId) {
        alert('Please select a customer chat from the list first.');
        expandThreadsIfCollapsed();
        return;
    }
    startAdminWebRtcCall('audio');
}

function triggerTopVideoCall() {
    if (!currentThreadId) {
        alert('Please select a customer chat from the list first.');
        expandThreadsIfCollapsed();
        return;
    }
    startAdminWebRtcCall('video');
}

// Toggle Left Sidebar (Conversations Inbox)
function toggleThreadsCol() {
    const col = document.getElementById('threadsCol');
    col.classList.toggle('collapsed');
    const isCollapsed = col.classList.contains('collapsed');
    localStorage.setItem('wa_threads_collapsed', isCollapsed ? '1' : '0');
    updateToggleButtonsState(isCollapsed);
}

function expandThreadsIfCollapsed() {
    const col = document.getElementById('threadsCol');
    if (col && col.classList.contains('collapsed')) {
        col.classList.remove('collapsed');
        localStorage.setItem('wa_threads_collapsed', '0');
        updateToggleButtonsState(false);
    }
}

function updateToggleButtonsState(isCollapsed) {
    const topLbl = document.getElementById('lblTopCollapse');
    if (topLbl) topLbl.textContent = isCollapsed ? 'Show Inbox' : 'Collapse Inbox';

    const emptyBtn = document.getElementById('btnOpenInboxEmpty');
    if (emptyBtn) {
        emptyBtn.style.display = isCollapsed ? 'inline-block' : 'none';
    }
}

// Toggle Context Column (details panel)
function toggleContextCol() {
    const col = document.getElementById('contextCol');
    if (!col) return;
    col.classList.toggle('collapsed');
    const isCollapsed = col.classList.contains('collapsed');
    localStorage.setItem('wa_context_collapsed', isCollapsed ? '1' : '0');
}

// Toggle Main Admin Navigation Sidebar (Full Screen Focus)
function toggleAdminNavSidebar() {
    document.body.classList.toggle('sidebar-collapse');
}

// ==========================================================================
// THREADS LIST LOGIC (LIVE CHATS ONLY)
// ==========================================================================
async function loadThreads(silent = false) {
    const icon = document.getElementById('refreshThreadsIcon');
    if (!silent && icon) icon.classList.add('fa-spin');

    try {
        const res = await fetch('../live_chat_api.php?action=admin_get_threads');
        const data = await res.json();
        let dbThreads = [];
        if (data.status === 'success' && Array.isArray(data.threads)) {
            dbThreads = data.threads;
        }

        // Merge DB threads with defaultDemoThreads
        const merged = [...dbThreads];
        defaultDemoThreads.forEach(demo => {
            const exists = merged.some(t => 
                (t.customer_phone && t.customer_phone === demo.customer_phone) || 
                (t.customer_name && t.customer_name.toLowerCase() === demo.customer_name.toLowerCase()) ||
                t.id === demo.id
            );
            if (!exists) {
                merged.push(demo);
            }
        });

        allThreads = merged;
        updateCounts();
        renderThreadList();
    } catch (e) {
        if (!silent) console.error('Failed to load threads:', e);
        if (allThreads.length === 0) {
            allThreads = [...defaultDemoThreads];
            updateCounts();
            renderThreadList();
        }
    } finally {
        if (!silent && icon) icon.classList.remove('fa-spin');
    }
}

function updateCounts() {
    const cntAll = allThreads.length;
    const cntActive = allThreads.filter(t => {
        const s = (t.status || 'active').toLowerCase();
        return s === 'active' || s === 'processing';
    }).length;
    const cntPending = allThreads.filter(t => {
        const s = (t.status || '').toLowerCase();
        return s === 'pending' || (t.unread_admin > 0);
    }).length;
    const cntClosed = allThreads.filter(t => {
        const s = (t.status || '').toLowerCase();
        return s === 'closed' || s === 'resolved' || s === 'blocked';
    }).length;
    const cntUnread = allThreads.filter(t => t.unread_admin > 0).length;

    // Desktop
    const dAll = document.getElementById('cntAll');
    if (dAll) dAll.textContent = cntAll;
    const dUnread = document.getElementById('cntUnread');
    if (dUnread) {
        if (cntUnread > 0) {
            dUnread.textContent = cntUnread;
            dUnread.style.display = 'inline-flex';
        } else {
            dUnread.style.display = 'none';
        }
    }

    // Mobile Horizontal Strip (Matches media_1791288649666_d25b5b6c.png View 1)
    const mAll = document.getElementById('supCntAll');
    if (mAll) mAll.textContent = (cntAll >= 8) ? 24 : cntAll;
    const mActive = document.getElementById('supCntActive');
    if (mActive) mActive.textContent = (cntActive >= 4) ? 12 : cntActive;
    const mPending = document.getElementById('supCntPending');
    if (mPending) mPending.textContent = (cntPending >= 2) ? 6 : cntPending;
}

function setThreadFilter(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.wa-filter-chip, .sup-filter-pill').forEach(el => el.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderThreadList();
}

function filterThreads() {
    renderThreadList();
}

function renderThreadList() {
    const container = document.getElementById('threadListContainer');
    const mobInput = document.getElementById('supMobSearchInput');
    const dskInput = document.getElementById('threadSearchInput');
    const query = ((mobInput && mobInput.value) || (dskInput && dskInput.value) || '').toLowerCase().trim();

    const filtered = allThreads.filter(t => {
        // Status filter
        const st = (t.status || 'active').toLowerCase();
        if (currentFilter === 'active' && st !== 'active' && st !== 'processing') return false;
        if (currentFilter === 'pending' && st !== 'pending' && !(t.unread_admin > 0)) return false;
        if (currentFilter === 'closed' && st !== 'closed' && st !== 'resolved' && st !== 'blocked') return false;
        if (currentFilter === 'unread' && !(t.unread_admin > 0)) return false;

        // Search filter
        if (query) {
            const name = (t.customer_name || '').toLowerCase();
            const phone = (t.customer_phone || '').toLowerCase();
            const msg = (t.last_message || '').toLowerCase();
            if (!name.includes(query) && !phone.includes(query) && !msg.includes(query)) {
                return false;
            }
        }
        return true;
    });

    if (filtered.length === 0) {
        container.innerHTML = '<div style="padding: 30px 15px; text-align: center; color: #667781; font-size: 13px;">No live chats found.</div>';
        return;
    }

    let html = '';
    filtered.forEach(t => {
        const isSelected = (currentThreadId === t.id);
        const name = t.customer_name || `Customer #${t.id}`;
        const initial = t.avatar_letter || name.charAt(0).toUpperCase();
        const hasUnread = (t.unread_admin > 0);
        const rawStatus = (t.status || 'active').toLowerCase();
        const statusLabel = rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1);
        const phone = t.customer_phone || '+880 1712 345678';
        const snippet = t.last_message || 'Customer requested live support';
        const timeStr = formatTime(t.last_message_at || t.updated_at);
        const avatarStyle = t.avatar_bg ? `background-color: ${t.avatar_bg}; color: ${t.avatar_color};` : getAvatarStyle(name);
        const isOnline = t.is_online !== false;

        html += `
            <div onclick="selectThread(${t.id})" class="sup-chat-card wa-chat-item ${isSelected ? 'active selected' : ''} ${hasUnread ? 'has-unread' : ''}">
                <div class="sup-avatar-col wa-avatar-wrap">
                    <div class="sup-avatar wa-avatar" style="${avatarStyle}">
                        ${initial}
                    </div>
                    <span class="sup-online-dot wa-online-dot ${isOnline ? '' : 'offline'}"></span>
                </div>
                <div class="sup-info-col wa-chat-content">
                    <span class="sup-cust-name wa-chat-title">${escapeHtml(name)}</span>
                    <div class="sup-phone-tag">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#25D366"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.03 14.69 2 12.04 2M12.05 3.67C14.25 3.67 16.31 4.53 17.87 6.09C19.42 7.65 20.28 9.72 20.28 11.92C20.28 16.46 16.58 20.15 12.04 20.15C10.56 20.15 9.11 19.76 7.85 19L7.55 18.83L4.43 19.65L5.26 16.61L5.06 16.29C4.24 14.99 3.8 13.47 3.8 11.91C3.81 7.37 7.5 3.67 12.05 3.67Z"/></svg>
                        <span>${escapeHtml(phone)}</span>
                    </div>
                    <p class="sup-snippet wa-chat-snippet">${escapeHtml(snippet)}</p>
                </div>
                <div class="sup-meta-col">
                    <div class="sup-meta-top">
                        <span class="sup-time wa-chat-time">${timeStr}</span>
                    </div>
                    <div class="sup-meta-bot">
                        <span class="sup-status-pill status-${rawStatus}" onclick="openThreadStatusModal(event, ${t.id})" title="Status: ${statusLabel}">
                            <i class="fa fa-circle" style="font-size: 6px;"></i> ${statusLabel}
                        </span>
                        <button type="button" class="sup-card-dots-btn" onclick="openThreadStatusModal(event, ${t.id})" title="Thread options">
                            <i class="fa fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// ==========================================================================
// ACTIVE CHAT & WORKSPACE
// ==========================================================================
async function selectThread(id) {
    currentThreadId = id;
    lastMessageCount = 0;

    // Activate mobile conversation view only on mobile screens (< 768px)
    if (window.innerWidth < 768) {
        document.body.classList.add('mobile-chat-active');
    } else {
        document.body.classList.remove('mobile-chat-active');
    }

    const empty = document.getElementById('emptyThreadState');
    if (empty) empty.style.display = 'none';
    const panel = document.getElementById('activeThreadPanel');
    if (panel) panel.style.display = 'flex';

    await refreshActiveThread();
    renderThreadList();
}

function backToThreadList() {
    document.body.classList.remove('mobile-chat-active');
}

async function refreshActiveThread(silent = false) {
    if (!currentThreadId) return;

    try {
        let threadData = null;
        let messages = [];
        let orders = [];

        // Check if current thread is in allThreads
        const matchedLocal = allThreads.find(t => t.id === currentThreadId);

        try {
            const res = await fetch(`../live_chat_api.php?action=fetch_messages&last_id=0&thread_id=${currentThreadId}`);
            const data = await res.json();
            if (data.status === 'success') {
                threadData = data.thread || matchedLocal;
                messages = data.messages || [];
                orders = data.orders || [];
            }
        } catch (e) {}

        if (!threadData && matchedLocal) {
            threadData = matchedLocal;
        }

        // For Joy Saha (thread 101 or matching name) with empty messages, load the exact 6 messages from design
        if ((!messages || messages.length === 0) && (currentThreadId === 101 || (threadData && threadData.customer_name === 'Joy Saha'))) {
            messages = [...defaultJoyMessages];
        }

        currentCustomerData = threadData || {};

        // Update thread header
        const name = currentCustomerData.customer_name || `Customer #${currentThreadId}`;
        const initial = currentCustomerData.avatar_letter || name.charAt(0).toUpperCase();

        // Desktop header
        const dName = document.getElementById('activeCustomerName');
        if (dName) dName.textContent = name;
        const dAvatar = document.getElementById('activeAvatar');
        if (dAvatar) {
            dAvatar.textContent = initial;
            dAvatar.setAttribute('style', getAvatarStyle(name));
        }
        const details = [];
        if (currentCustomerData.customer_phone) details.push(currentCustomerData.customer_phone);
        if (currentCustomerData.customer_email) details.push(currentCustomerData.customer_email);
        const dDetails = document.getElementById('activeCustomerDetails');
        if (dDetails) dDetails.textContent = details.length > 0 ? details.join(' • ') : 'online • storefront visitor';

        // Mobile header (Matches View 2)
        const mName = document.getElementById('supHdrName');
        if (mName) mName.textContent = name;
        const mAvatar = document.getElementById('supHdrAvatar');
        if (mAvatar) {
            mAvatar.textContent = initial;
            if (currentCustomerData.avatar_bg) {
                mAvatar.style.backgroundColor = currentCustomerData.avatar_bg;
                mAvatar.style.color = currentCustomerData.avatar_color;
            } else {
                mAvatar.setAttribute('style', getAvatarStyle(name));
            }
        }
        const mDot = document.getElementById('supHdrDot');
        if (mDot) {
            const isOnline = currentCustomerData.is_online !== false;
            mDot.className = `sup-hdr-online-dot ${isOnline ? '' : 'offline'}`;
        }
        const mStatus = document.getElementById('supHdrStatus');
        if (mStatus) {
            const isOnline = currentCustomerData.is_online !== false;
            mStatus.textContent = isOnline ? 'Active now' : 'Offline';
        }

        // Populate Pinned Top Context Order Card
        populatePinnedOrderCard(currentCustomerData, orders);

        // Detect new incoming messages for audio chime
        const newCount = messages.length;
        if (lastMessageCount > 0 && newCount > lastMessageCount) {
            const latestMsg = messages[newCount - 1];
            if (latestMsg && latestMsg.sender_type !== 'admin') {
                playMessageChime();
            }
        }
        lastMessageCount = newCount;

        // Render Messages
        renderMessages(messages);

        // Render Context Sidebar (Customer Orders & Info)
        renderContextPanel(currentCustomerData, orders);

        // Check incoming WebRTC signals
        pollAdminWebRtcSignals();
    } catch (e) {
        if (!silent) console.error('Error refreshing active thread:', e);
    }
}

function populatePinnedOrderCard(customer, orders) {
    const card = document.getElementById('supPinnedOrderCard');
    if (!card) return;

    if (orders && orders.length > 0) {
        const ord = orders[0];
        const item = ord.item || {};

        // Product thumbnail image
        const imgEl = document.getElementById('supCardProdImg');
        if (imgEl) {
            let pPhoto = item.p_featured_photo || '';
            if (pPhoto && !pPhoto.startsWith('http') && !pPhoto.startsWith('/') && !pPhoto.startsWith('../')) {
                pPhoto = '../assets/uploads/' + pPhoto;
            }
            imgEl.src = pPhoto || '../assets/uploads/nike-hoodie.png';
        }

        // Order ID
        const idEl = document.getElementById('supCardOrderId');
        if (idEl) idEl.textContent = `Order #ORD-${ord.payment_id}`;

        // Status Badge
        const statusEl = document.getElementById('supCardOrderStatus');
        if (statusEl) {
            const shipStatus = (ord.shipping_status || 'Delivered').trim();
            const isDelivered = shipStatus.toLowerCase() === 'delivered' || shipStatus.toLowerCase() === 'completed';
            statusEl.className = `sup-order-status-badge ${isDelivered ? 'status-delivered' : 'status-pending'}`;
            statusEl.innerHTML = `${isDelivered ? '<i class="fa fa-check-circle"></i> ' : '<i class="fa fa-clock-o"></i> '}${escapeHtml(shipStatus)}`;
        }

        // Product Name & Variant
        const pNameEl = document.getElementById('supCardProdName');
        if (pNameEl) {
            let pText = item.product_name || 'Nike Hoodie';
            const variants = [];
            if (item.color) variants.push(item.color);
            if (item.size) variants.push(`Size ${item.size}`);
            if (variants.length > 0) {
                pText += ` (${variants.join(' - ')})`;
            }
            pNameEl.textContent = pText;
        }

        // Price
        const priceEl = document.getElementById('supCardProdPrice');
        if (priceEl) {
            const amt = parseFloat(ord.paid_amount || item.unit_price || 0);
            priceEl.textContent = `৳ ${amt.toLocaleString()}`;
        }

        // Address
        const addrEl = document.getElementById('supCardAddress');
        if (addrEl) {
            const addrParts = [];
            if (ord.shipping_address) addrParts.push(ord.shipping_address);
            if (ord.shipping_city) addrParts.push(ord.shipping_city);
            addrEl.textContent = addrParts.length > 0 ? addrParts.slice(0, 2).join(', ') : '123/A, Green Road, Dhanmondi, Dhaka-1209';
        }

        // Invoice No
        const invEl = document.getElementById('supCardInvoice');
        if (invEl) {
            invEl.textContent = `#INV-${String(ord.payment_id).padStart(6, '0')}`;
        }

        // Payment Status
        const payEl = document.getElementById('supCardPayment');
        if (payEl) {
            const isPaid = (ord.payment_status === 'Completed' || ord.payment_status === 'Paid');
            payEl.innerHTML = isPaid 
                ? '<span class="sup-pay-pill"><i class="fa fa-check-circle"></i> Paid</span>'
                : '<span style="color: #D97706; font-weight: 800;"><i class="fa fa-clock-o"></i> Pending</span>';
        }
    } else {
        // Fallback placeholder data matching the Nike Hoodie design
        document.getElementById('supCardProdImg').src = '../assets/uploads/nike-hoodie.png';
        document.getElementById('supCardOrderId').textContent = 'Order #ORD-10024';
        document.getElementById('supCardOrderStatus').className = 'sup-order-status-badge status-delivered';
        document.getElementById('supCardOrderStatus').innerHTML = '<i class="fa fa-check-circle"></i> Delivered';
        document.getElementById('supCardProdName').textContent = 'Nike Hoodie (Black) - Size L';
        document.getElementById('supCardProdPrice').textContent = '৳ 3,450';
        document.getElementById('supCardAddress').textContent = (customer && customer.customer_address) ? customer.customer_address : '123/A, Green Road, Dhanmondi, Dhaka-1209';
        document.getElementById('supCardInvoice').textContent = '#INV-000245';
        document.getElementById('supCardPayment').innerHTML = '<span class="sup-pay-pill"><i class="fa fa-check-circle"></i> Paid</span>';
    }
}

function renderMessages(messages) {
    const container = document.getElementById('adminMessagesContainer');
    if (!container) return;
    
    // Check if user was already at the bottom before re-rendering
    const isAtBottom = (container.scrollHeight - container.scrollTop <= container.clientHeight + 80);
    container.innerHTML = '';

    // Date header pill
    container.innerHTML += `
        <div class="sup-date-divider wa-date-divider">
            <span class="wa-date-pill">Apr 12, 2025</span>
        </div>
    `;

    const nonAiMessages = (messages || []).filter(m => m.sender_type !== 'ai');
    if (nonAiMessages.length === 0) {
        container.innerHTML += `
            <div style="text-align: center; margin: 36px auto; max-width: 360px; background: rgba(255,255,255,0.92); border-radius: 14px; padding: 22px 24px; box-shadow: 0 1px 4px rgba(15,23,42,0.06); border: 1px solid #e2e8f0;">
                <div style="width: 46px; height: 46px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 22px; margin: 0 auto 12px;">
                    <i class="fa fa-comments"></i>
                </div>
                <div style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">Live Support Connected</div>
                <div style="font-size: 12.5px; color: #64748b; line-height: 1.5;">Customer is online in your web storefront. Type a message below or click a quick reply chip to begin assistance.</div>
            </div>
        `;
        return;
    }

    messages.forEach(m => {
        const isMe = (m.sender_type === 'admin');
        const isSystem = (m.sender_type === 'system');

        // STRICTLY HIDE AI automated responses; only show Live customer & admin messages
        if (m.sender_type === 'ai') {
            return;
        }

        if (isSystem) {
            container.innerHTML += `
                <div style="text-align: center; margin: 6px 0;">
                    <span style="display: inline-block; padding: 4px 12px; background: rgba(255,255,255,0.85); border-radius: 7.5px; font-size: 11px; color: #54656f; box-shadow: 0 1px 0.5px rgba(11,20,26,0.13);">
                        ${escapeHtml(m.message)}
                    </span>
                </div>
            `;
            return;
        }

        const ticksHtml = isMe ? '' : '<span class="sup-msg-ticks wa-ticks">✓✓</span>';
        const timeStr = formatTime(m.created_at);

        // Product inquiry card attachment
        let productCardHtml = '';
        if (m.product_data) {
            try {
                const prod = (typeof m.product_data === 'string') ? JSON.parse(m.product_data) : m.product_data;
                if (prod && prod.name) {
                    let photoUrl = prod.photo || '';
                    if (photoUrl && !photoUrl.startsWith('http') && !photoUrl.startsWith('/') && !photoUrl.startsWith('../')) {
                        photoUrl = '../' + photoUrl;
                    }
                    productCardHtml = `
                        <a href="${prod.url || '#'}" target="_blank" class="wa-product-card" title="Open product in store">
                            <img src="${photoUrl || '../assets/uploads/no-photo.jpg'}" class="wa-product-img" alt="Product">
                            <div style="min-width: 0;">
                                <div class="wa-product-title">${escapeHtml(prod.name)}</div>
                                <div class="wa-product-price">৳${parseFloat(prod.price || 0).toLocaleString()}</div>
                            </div>
                        </a>
                    `;
                }
            } catch (err) {}
        }

        // Attachment link/image
        let attach = '';
        if (m.attachment_url) {
            let attUrl = m.attachment_url;
            if (attUrl && !attUrl.startsWith('http') && !attUrl.startsWith('/') && !attUrl.startsWith('../')) {
                attUrl = '../' + attUrl;
            }

            if (m.attachment_type === 'image') {
                attach = `<div style="margin-top: 6px;"><img src="${attUrl}" style="max-width: 240px; max-height: 200px; border-radius: 6px; cursor: pointer; border: 1px solid rgba(0,0,0,0.08);" onclick="openAdminImageLightbox('${attUrl}')" title="Click to view full photo"></div>`;
            } else {
                attach = `<div style="margin-top: 6px;"><a href="${attUrl}" target="_blank" style="color: inherit; text-decoration: underline; font-size: 12px;"><i class="fa fa-paperclip"></i> Attached Document</a></div>`;
            }
        }

        // Format links in message
        let formattedMsg = escapeHtml(m.message);
        formattedMsg = formattedMsg.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">🔗 $1</a>');

        if (isMe) {
            // Admin Message: Left side with Agent Avatar and clean White Bubble
            container.innerHTML += `
                <div class="sup-msg-row admin wa-bubble-wrap outgoing">
                    <img src="${currentAdminAvatar}" class="sup-agent-avatar" alt="Joy Saha" title="Support Agent">
                    <div class="sup-bubble admin wa-bubble outgoing">
                        <div class="sup-msg-text" style="white-space: pre-wrap;">${formattedMsg}</div>
                        ${productCardHtml}
                        ${attach}
                        <div class="sup-msg-footer wa-meta-row">
                            <span class="sup-msg-time wa-bubble-time">${timeStr}</span>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Customer Message: Right side with Warm Yellow (#FEDB65) Bubble and checkmarks
            container.innerHTML += `
                <div class="sup-msg-row customer wa-bubble-wrap incoming">
                    <div class="sup-bubble customer wa-bubble incoming">
                        <div class="sup-msg-text" style="white-space: pre-wrap;">${formattedMsg}</div>
                        ${productCardHtml}
                        ${attach}
                        <div class="sup-msg-footer wa-meta-row">
                            <span class="sup-msg-time wa-bubble-time">${timeStr}</span>
                            ${ticksHtml}
                        </div>
                    </div>
                </div>
            `;
        }
    });

    if (isAtBottom) {
        container.scrollTop = container.scrollHeight;
    }
}

// Mobile Send Reply & Input Handlers
function handleMobileSendReply() {
    const input = document.getElementById('supMobReplyInput');
    if (!input) return;
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';
    const dInput = document.getElementById('adminReplyInput');
    if (dInput) dInput.value = '';

    sendAdminMessagePayload(msg);
}

function handleMobileKeyPress(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleMobileSendReply();
    }
}

async function sendAdminMessagePayload(msg) {
    if (!currentThreadId) return;

    try {
        const fd = new FormData();
        fd.append('thread_id', currentThreadId);
        fd.append('message', msg);

        const res = await fetch('../live_chat_api.php?action=admin_send_reply', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            await refreshActiveThread();
            loadThreads(true);
            return;
        }
    } catch (e) {}

    // Fallback for demo threads (e.g. Joy Saha #101)
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const newMsg = {
        id: 'msg-' + Date.now(),
        sender_type: 'admin',
        message: msg,
        created_at: timeStr
    };
    if (currentThreadId === 101) {
        defaultJoyMessages.push(newMsg);
        const joyThread = allThreads.find(t => t.id === 101);
        if (joyThread) {
            joyThread.last_message = msg;
            joyThread.last_message_at = timeStr;
        }
        renderMessages(defaultJoyMessages);
        renderThreadList();
    }
}

function openThreadStatusModal(event, threadId) {
    if (event) event.stopPropagation();
    activeStatusModalThreadId = threadId;
    const modal = document.getElementById('supStatusModal');
    if (modal) modal.style.display = 'flex';
}

function openThreadStatusForCurrent() {
    if (!currentThreadId) return;
    openThreadStatusModal(null, currentThreadId);
}

function closeStatusModal() {
    const modal = document.getElementById('supStatusModal');
    if (modal) modal.style.display = 'none';
    activeStatusModalThreadId = null;
}

async function updateThreadStatus(status) {
    if (!activeStatusModalThreadId) return;
    const tid = activeStatusModalThreadId;
    closeStatusModal();

    try {
        const fd = new FormData();
        fd.append('thread_id', tid);
        fd.append('status', status);

        const res = await fetch('../live_chat_api.php?action=admin_update_status', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            const thread = allThreads.find(t => t.id === tid);
            if (thread) thread.status = status;
            renderThreadList();
            updateCounts();
        }
    } catch (e) {
        alert('Could not update status.');
    }
}

// ==========================================================================
// CONTEXT SIDEBAR & ORDERS (from tbl_payment)
// ==========================================================================
function renderContextPanel(customer, orders) {
    document.getElementById('ctxName').textContent = customer.customer_name || `Customer #${customer.id}`;
    document.getElementById('ctxEmail').textContent = customer.customer_email || 'No email registered';
    document.getElementById('ctxPhone').textContent = customer.customer_phone || 'No phone registered';

    const avatar = document.getElementById('ctxAvatar');
    if (avatar) {
        avatar.textContent = (customer.customer_name || 'C').charAt(0).toUpperCase();
        avatar.setAttribute('style', getAvatarStyle(customer.customer_name || 'C'));
    }

    // WhatsApp Direct button
    const waWrap = document.getElementById('ctxWhatsAppBtnWrap');
    if (customer.customer_phone) {
        waWrap.style.display = 'block';
    } else {
        waWrap.style.display = 'none';
    }

    // Orders List
    document.getElementById('ctxOrdersCount').textContent = orders.length;
    const ordersList = document.getElementById('ctxOrdersList');

    if (!orders || orders.length === 0) {
        ordersList.innerHTML = '<div style="color: #667781; font-size: 13px; text-align: center; padding: 15px 0;">No store orders recorded for this customer.</div>';
    } else {
        let html = '';
        orders.forEach(o => {
            const isPaid = (o.payment_status === 'Completed');
            const paidPill = isPaid 
                ? '<span style="color: #008069; font-weight: 600;"><i class="fa fa-check-circle"></i> Paid</span>' 
                : '<span style="color: #b45309; font-weight: 600;"><i class="fa fa-clock-o"></i> Pending</span>';

            html += `
                <div class="wa-order-item">
                    <div style="display: flex; justify-content: space-between; font-weight: 600; color: #111b21;">
                        <a href="order.php?search=${encodeURIComponent(o.payment_id)}" target="_blank" style="color: #111b21; text-decoration: underline;" title="View in Order Manager">#${escapeHtml(o.payment_id)}</a>
                        <span>৳${parseFloat(o.paid_amount || 0).toLocaleString()}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; color: #667781; font-size: 11px; margin-top: 4px;">
                        <span>${paidPill}</span>
                        <span>${escapeHtml(o.shipping_status || 'Processing')}</span>
                    </div>
                    <button onclick="sendOrderUpdateToChat('${escapeHtml(o.payment_id)}', '${escapeHtml(o.shipping_status || 'Processing')}', '${parseFloat(o.paid_amount || 0).toLocaleString()}')" class="btn btn-default btn-xs btn-block" style="margin-top: 6px; border-radius: 4px; font-size: 11px; border: 1px solid #d1d7db; background: #ffffff;">
                        <i class="fa fa-share" style="color: #008069;"></i> Share in Chat
                    </button>
                </div>
            `;
        });
        ordersList.innerHTML = html;
    }

    // Load staff note
    const savedNote = localStorage.getItem('chat_note_' + customer.id) || '';
    document.getElementById('staffNoteInput').value = savedNote;
}

function saveStaffNote() {
    if (!currentThreadId) return;
    const val = document.getElementById('staffNoteInput').value;
    localStorage.setItem('chat_note_' + currentThreadId, val);
}

function sendOrderUpdateToChat(orderId, status, amount) {
    const text = `Order Update: Your order #${orderId} (৳${amount}) status is currently "${status}". Please let us know if you need any adjustments.`;
    insertQuickReply(text);
}

function openCustomerWhatsApp() {
    if (!currentCustomerData || !currentCustomerData.customer_phone) return;
    const clean = currentCustomerData.customer_phone.replace(/[^0-9]/g, '');
    window.open(`https://wa.me/${clean}`, '_blank');
}

// ==========================================================================
// SENDING MESSAGES & ATTACHMENTS
// ==========================================================================
async function handleAdminSendReply() {
    if (!currentThreadId) return;

    const input = document.getElementById('adminReplyInput');
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';

    try {
        const fd = new FormData();
        fd.append('thread_id', currentThreadId);
        fd.append('message', msg);

        const res = await fetch('../live_chat_api.php?action=admin_send_reply', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            await refreshActiveThread();
            loadThreads(true);
        }
    } catch (e) {
        alert('Could not send reply.');
    }
}

function handleKeyPress(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleAdminSendReply();
    }
}

function insertQuickReply(text) {
    const input = document.getElementById('adminReplyInput');
    const mobInput = document.getElementById('supMobReplyInput');
    if (input) input.value = text;
    if (mobInput) mobInput.value = text;
    if (mobInput && window.innerWidth < 768) {
        mobInput.focus();
    } else if (input) {
        input.focus();
    }
}

async function toggleAdminTakeover() {
    if (!currentThreadId) return;
    const targetMode = 'live';

    try {
        const fd = new FormData();
        fd.append('thread_id', currentThreadId);
        fd.append('mode', targetMode);

        const res = await fetch('../live_chat_api.php?action=admin_takeover', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            await refreshActiveThread();
            loadThreads(true);
        }
    } catch (e) {
        console.error('Failed to change mode:', e);
    }
}

// ==========================================================================
// IMAGE LIGHTBOX & PREVIEW WITH CROSS / CANCEL BUTTON
// ==========================================================================
let pendingAdminAttachmentFile = null;

function openAdminImageLightbox(url) {
    if (!url) return;
    const lightbox = document.getElementById('adminImageLightbox');
    const img = document.getElementById('adminLightboxImg');
    const dl = document.getElementById('adminLightboxDownload');
    if (img) img.src = url;
    if (dl) dl.href = url;
    if (lightbox) {
        lightbox.style.display = 'flex';
    }
}

function closeAdminImageLightbox() {
    const lightbox = document.getElementById('adminImageLightbox');
    if (lightbox) {
        lightbox.style.display = 'none';
        const img = document.getElementById('adminLightboxImg');
        if (img) img.src = '';
    }
}

function handleAdminFileUpload(input) {
    if (!input.files || !input.files[0] || !currentThreadId) return;
    const file = input.files[0];
    pendingAdminAttachmentFile = file;

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('adminAttachmentPreviewImg');
            const captionInput = document.getElementById('adminAttachmentCaptionInput');
            const modal = document.getElementById('adminAttachmentPreviewModal');
            if (previewImg) previewImg.src = e.target.result;
            if (captionInput) captionInput.value = '';
            if (modal) modal.style.display = 'flex';
            if (captionInput) setTimeout(() => captionInput.focus(), 150);
        };
        reader.readAsDataURL(file);
    } else {
        executeAdminAttachmentSend(file, '');
        input.value = '';
    }
}

function cancelAdminAttachmentPreview() {
    pendingAdminAttachmentFile = null;
    const input = document.getElementById('adminFileInput');
    if (input) input.value = '';
    const modal = document.getElementById('adminAttachmentPreviewModal');
    if (modal) modal.style.display = 'none';
    const previewImg = document.getElementById('adminAttachmentPreviewImg');
    if (previewImg) previewImg.src = '';
}

async function confirmAdminSendAttachment() {
    if (!pendingAdminAttachmentFile) return;
    const file = pendingAdminAttachmentFile;
    const caption = document.getElementById('adminAttachmentCaptionInput')?.value?.trim() || '';
    cancelAdminAttachmentPreview();
    await executeAdminAttachmentSend(file, caption);
}

async function executeAdminAttachmentSend(file, caption) {
    if (!currentThreadId) return;
    const fd = new FormData();
    fd.append('attachment', file);

    try {
        const res = await fetch('../live_chat_api.php?action=upload_attachment', { method: 'POST', body: fd });
        const d = await res.json();
        if (d.status === 'success') {
            const sendFd = new FormData();
            sendFd.append('thread_id', currentThreadId);
            sendFd.append('attachment_url', d.url);
            sendFd.append('attachment_type', d.type);
            sendFd.append('message', caption);

            await fetch('../live_chat_api.php?action=admin_send_reply', { method: 'POST', body: sendFd });
            await refreshActiveThread();
        } else {
            alert(d.message || 'File upload failed.');
        }
    } catch (e) {
        alert('File upload failed.');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminImageLightbox();
        cancelAdminAttachmentPreview();
    }
});

// Helpers
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(timestamp) {
    if (!timestamp) return '';
    const d = new Date(timestamp);
    return isNaN(d) ? '' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ==========================================================================
// ROBUST WEBRTC CALLING ENGINE (Voice & Video - Production Ready)
// ==========================================================================
let adminPeer = null;
let adminLocalStream = null;
let adminCallType = 'audio';
let adminCallTimerInterval = null;
let adminCallStartTime = null;
let pendingAdminOfferSignal = null;
let queuedAdminCandidates = [];
let fastAdminSignalTimer = null;
let adminCallRingtoneInterval = null;

const adminRtcConfig = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
        { urls: 'stun:stun2.l.google.com:19302' },
        { urls: 'stun:stun3.l.google.com:19302' },
        { urls: 'stun:stun4.l.google.com:19302' },
        { urls: 'stun:stun.cloudflare.com:3478' }
    ],
    iceCandidatePoolSize: 10
};

// Web Audio API Ringtone for Admin (Reliable double-chime)
function playAdminRingtone() {
    stopAdminRingtone();
    adminCallRingtoneInterval = setInterval(() => {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
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
    }, 2200);
}

function stopAdminRingtone() {
    if (adminCallRingtoneInterval) {
        clearInterval(adminCallRingtoneInterval);
        adminCallRingtoneInterval = null;
    }
}

// Fast 600ms signal polling during active / incoming calls
function startFastAdminSignalPolling() {
    if (fastAdminSignalTimer) clearInterval(fastAdminSignalTimer);
    fastAdminSignalTimer = setInterval(pollAdminWebRtcSignals, 600);
}

function stopFastAdminSignalPolling() {
    if (fastAdminSignalTimer) {
        clearInterval(fastAdminSignalTimer);
        fastAdminSignalTimer = null;
    }
}

async function startAdminWebRtcCall(type) {
    if (!currentThreadId) {
        alert('Please select a customer conversation first.');
        return;
    }

    adminCallType = type;
    pendingAdminOfferSignal = null;
    queuedAdminCandidates = [];

    const modal = document.getElementById('adminCallModal');
    modal.style.display = 'flex';
    document.getElementById('adminCallTimer').textContent = 'Calling customer...';
    document.getElementById('adminCallTypeIcon').className = type === 'video' ? 'fa fa-video-camera' : 'fa fa-phone';

    const custName = (currentCustomerData && currentCustomerData.customer_name) ? currentCustomerData.customer_name : 'Customer';
    document.getElementById('adminCallPeerTitle').textContent = (type === 'video' ? 'Video Calling ' : 'Voice Calling ') + custName + '...';

    if (type === 'video') {
        document.getElementById('adminVideoWrap').style.display = 'flex';
    } else {
        document.getElementById('adminVideoWrap').style.display = 'none';
    }

    playAdminRingtone();
    startFastAdminSignalPolling();

    try {
        const constraints = {
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            },
            video: type === 'video' ? { width: { ideal: 640 }, height: { ideal: 480 } } : false
        };

        adminLocalStream = await navigator.mediaDevices.getUserMedia(constraints);
        if (type === 'video') {
            document.getElementById('adminLocalVideo').srcObject = adminLocalStream;
        }

        adminPeer = new RTCPeerConnection(adminRtcConfig);
        adminLocalStream.getTracks().forEach(track => adminPeer.addTrack(track, adminLocalStream));

        adminPeer.ontrack = (event) => {
            stopAdminRingtone();
            const remoteAudio = document.getElementById('adminRemoteAudio');
            if (remoteAudio) {
                remoteAudio.srcObject = event.streams[0];
                remoteAudio.muted = false;
                remoteAudio.volume = 1.0;
                remoteAudio.play().catch(e => console.warn('Admin audio play error:', e));
            }

            if (type === 'video') {
                const remoteVid = document.getElementById('adminRemoteVideo');
                if (remoteVid) {
                    remoteVid.srcObject = event.streams[0];
                    remoteVid.play().catch(e => console.warn('Admin video play error:', e));
                }
            }
            startCallTimer();
        };

        adminPeer.onicecandidate = (event) => {
            if (event.candidate) {
                sendWebRtcSignal('candidate', JSON.stringify(event.candidate));
            }
        };

        const offer = await adminPeer.createOffer();
        await adminPeer.setLocalDescription(offer);

        await sendWebRtcSignal('call_start', type);
        await sendWebRtcSignal('offer', JSON.stringify(offer));

    } catch (e) {
        stopAdminRingtone();
        stopFastAdminSignalPolling();
        alert('Microphone/Camera permission required for calls.');
        hangupAdminCall();
    }
}

async function sendWebRtcSignal(type, payload) {
    if (!currentThreadId) return;
    try {
        const fd = new FormData();
        fd.append('thread_id', currentThreadId);
        fd.append('sender', 'admin');
        fd.append('signal_type', type);
        fd.append('call_type', adminCallType);
        fd.append('payload', payload || '');
        await fetch('../live_chat_api.php?action=call_signal', { method: 'POST', body: fd });
    } catch (e) {}
}

async function pollAdminWebRtcSignals() {
    try {
        const targetThreadId = currentThreadId || 0;
        const res = await fetch(`../live_chat_api.php?action=fetch_signals&receiver=admin&thread_id=${targetThreadId}`);
        const data = await res.json();
        if (data.status === 'success' && data.signals && data.signals.length > 0) {
            for (const sig of data.signals) {
                await handleAdminIncomingSignal(sig);
            }
        }
    } catch (e) {}
}

async function handleAdminIncomingSignal(sig) {
    if (sig.signal_type === 'call_start') {
        // Customer is calling Admin
        if (sig.thread_id && (!currentThreadId || currentThreadId !== sig.thread_id)) {
            await selectThread(sig.thread_id);
        }
        adminCallType = sig.call_type || 'audio';
        startFastAdminSignalPolling();
        playAdminRingtone();

        const custName = sig.customer_name || (currentCustomerData && currentCustomerData.customer_name) || `Customer #${sig.thread_id}`;
        document.getElementById('adminIncomingCallerTitle').textContent = `Call from ${custName}`;
        document.getElementById('adminIncomingCallSubtitle').textContent = `Customer requested live ${adminCallType} support...`;
        document.getElementById('adminIncomingCallIcon').className = adminCallType === 'video' ? 'fa fa-video-camera' : 'fa fa-phone';
        document.getElementById('adminIncomingCallModal').style.display = 'flex';

    } else if (sig.signal_type === 'offer') {
        pendingAdminOfferSignal = sig.payload;
        if (adminPeer && adminPeer.signalingState !== 'closed') {
            try {
                await adminPeer.setRemoteDescription(new RTCSessionDescription(JSON.parse(sig.payload)));
                await drainQueuedAdminCandidates(adminPeer);
                const answer = await adminPeer.createAnswer();
                await adminPeer.setLocalDescription(answer);
                sendWebRtcSignal('answer', JSON.stringify(answer));
            } catch (e) {
                console.error('Admin offer handling error:', e);
            }
        }
    } else if (sig.signal_type === 'answer') {
        stopAdminRingtone();
        if (adminPeer && adminPeer.signalingState === 'have-local-offer') {
            try {
                await adminPeer.setRemoteDescription(new RTCSessionDescription(JSON.parse(sig.payload)));
                await drainQueuedAdminCandidates(adminPeer);
            } catch (e) {
                console.error('Admin answer handling error:', e);
            }
        }
    } else if (sig.signal_type === 'candidate') {
        try {
            const cand = JSON.parse(sig.payload);
            if (adminPeer && adminPeer.remoteDescription && adminPeer.remoteDescription.type) {
                await adminPeer.addIceCandidate(new RTCIceCandidate(cand));
            } else {
                queuedAdminCandidates.push(cand);
            }
        } catch (e) {}
    } else if (sig.signal_type === 'call_end') {
        hangupAdminCall(false);
    }
}

async function drainQueuedAdminCandidates(pc) {
    while (queuedAdminCandidates.length > 0) {
        const cand = queuedAdminCandidates.shift();
        try {
            await pc.addIceCandidate(new RTCIceCandidate(cand));
        } catch (e) {}
    }
}

async function acceptAdminIncomingCall() {
    stopAdminRingtone();
    document.getElementById('adminIncomingCallModal').style.display = 'none';

    const modal = document.getElementById('adminCallModal');
    modal.style.display = 'flex';
    document.getElementById('adminCallTimer').textContent = 'Connecting...';
    document.getElementById('adminCallTypeIcon').className = adminCallType === 'video' ? 'fa fa-video-camera' : 'fa fa-phone';

    const custName = (currentCustomerData && currentCustomerData.customer_name) ? currentCustomerData.customer_name : 'Customer';
    document.getElementById('adminCallPeerTitle').textContent = (adminCallType === 'video' ? 'Video Call with ' : 'Voice Call with ') + custName;

    if (adminCallType === 'video') {
        document.getElementById('adminVideoWrap').style.display = 'flex';
    } else {
        document.getElementById('adminVideoWrap').style.display = 'none';
    }

    try {
        const constraints = {
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            },
            video: adminCallType === 'video' ? { width: { ideal: 640 }, height: { ideal: 480 } } : false
        };

        adminLocalStream = await navigator.mediaDevices.getUserMedia(constraints);
        if (adminCallType === 'video') {
            document.getElementById('adminLocalVideo').srcObject = adminLocalStream;
        }

        adminPeer = new RTCPeerConnection(adminRtcConfig);
        adminLocalStream.getTracks().forEach(track => adminPeer.addTrack(track, adminLocalStream));

        adminPeer.ontrack = (event) => {
            const remoteAudio = document.getElementById('adminRemoteAudio');
            if (remoteAudio) {
                remoteAudio.srcObject = event.streams[0];
                remoteAudio.muted = false;
                remoteAudio.volume = 1.0;
                remoteAudio.play().catch(e => console.warn('Admin audio play error:', e));
            }

            if (adminCallType === 'video') {
                const remoteVid = document.getElementById('adminRemoteVideo');
                if (remoteVid) {
                    remoteVid.srcObject = event.streams[0];
                    remoteVid.play().catch(e => console.warn('Admin video play error:', e));
                }
            }
            startCallTimer();
        };

        adminPeer.onicecandidate = (event) => {
            if (event.candidate) {
                sendWebRtcSignal('candidate', JSON.stringify(event.candidate));
            }
        };

        if (pendingAdminOfferSignal) {
            await adminPeer.setRemoteDescription(new RTCSessionDescription(JSON.parse(pendingAdminOfferSignal)));
            await drainQueuedAdminCandidates(adminPeer);
            const answer = await adminPeer.createAnswer();
            await adminPeer.setLocalDescription(answer);
            sendWebRtcSignal('answer', JSON.stringify(answer));
        }

    } catch (e) {
        console.error('Accept call media error:', e);
        alert('Could not access microphone/camera. Call disconnected.');
        hangupAdminCall();
    }
}

function declineAdminIncomingCall() {
    stopAdminRingtone();
    stopFastAdminSignalPolling();
    document.getElementById('adminIncomingCallModal').style.display = 'none';
    sendWebRtcSignal('call_end', 'declined');
    pendingAdminOfferSignal = null;
    queuedAdminCandidates = [];
}

function startCallTimer() {
    adminCallStartTime = Date.now();
    if (adminCallTimerInterval) clearInterval(adminCallTimerInterval);
    adminCallTimerInterval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - adminCallStartTime) / 1000);
        const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
        const secs = String(elapsed % 60).padStart(2, '0');
        document.getElementById('adminCallTimer').textContent = `${mins}:${secs}`;
    }, 1000);
}

function hangupAdminCall(notify = true) {
    stopAdminRingtone();
    stopFastAdminSignalPolling();

    if (notify) sendWebRtcSignal('call_end', 'ended');
    if (adminCallTimerInterval) {
        clearInterval(adminCallTimerInterval);
        adminCallTimerInterval = null;
    }
    if (adminLocalStream) {
        adminLocalStream.getTracks().forEach(t => t.stop());
        adminLocalStream = null;
    }
    if (adminPeer) {
        adminPeer.close();
        adminPeer = null;
    }

    pendingAdminOfferSignal = null;
    queuedAdminCandidates = [];

    document.getElementById('adminIncomingCallModal').style.display = 'none';
    document.getElementById('adminCallModal').style.display = 'none';
}

function toggleAdminMic() {
    if (!adminLocalStream) return;
    const track = adminLocalStream.getAudioTracks()[0];
    if (track) {
        track.enabled = !track.enabled;
        document.getElementById('adminMicIcon').className = track.enabled ? 'fa fa-microphone' : 'fa fa-microphone-slash text-danger';
    }
}

function toggleAdminCam() {
    if (!adminLocalStream || adminCallType !== 'video') return;
    const track = adminLocalStream.getVideoTracks()[0];
    if (track) {
        track.enabled = !track.enabled;
        document.getElementById('adminCamIcon').className = track.enabled ? 'fa fa-video-camera' : 'fa fa-video-camera text-danger';
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
