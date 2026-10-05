<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/header.php';
?>

<div class="content-wrapper" style="min-height: calc(100vh - 100px); background: #f4f6f9;">
    <!-- Content Header -->
    <section class="content-header" style="padding: 15px 20px;">
        <h1 style="font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; display: flex; items-center; gap: 10px;">
            <i class="fa fa-comments text-primary"></i> Live Customer Support & AI Pilot Console
            <small style="font-size: 13px; color: #64748b; font-weight: 500;">Realtime messaging, AI handover, and bufferless WebRTC calling</small>
        </h1>
        <ol class="breadcrumb" style="background: transparent; padding: 0; margin-top: 5px;">
            <li><a href="index.php"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li class="active">Live Chat Console</li>
        </ol>
    </section>

    <!-- Main Live Chat Application -->
    <section class="content" style="padding: 0 20px 20px 20px;">
        <div class="box box-solid" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden;">
            <div class="box-body" style="padding: 0;">
                <div class="row" style="margin: 0; min-height: 720px; display: flex;">
                    
                    <!-- LEFT COLUMN: THREAD LIST (320px) -->
                    <div class="col-md-4" style="padding: 0; border-right: 1px solid #e2e8f0; background: #ffffff; display: flex; flex-direction: column;">
                        <!-- Search & Filter -->
                        <div style="padding: 15px; border-bottom: 1px solid #f1f5f9; background: #fafafa;">
                            <div class="input-group">
                                <input type="text" id="threadSearch" class="form-control" placeholder="Search conversations..." style="border-radius: 20px 0 0 20px; border-color: #cbd5e1; font-size: 13px;">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" style="border-radius: 0 20px 20px 0; border-color: #cbd5e1; background: #ffffff;">
                                        <i class="fa fa-search text-muted"></i>
                                    </button>
                                </span>
                            </div>
                        </div>

                        <!-- Active Customer Threads -->
                        <div id="threadListContainer" style="flex: 1; overflow-y: auto; max-height: 650px;">
                            <div style="padding: 30px; text-align: center; color: #94a3b8;">
                                <i class="fa fa-spinner fa-spin fa-2x"></i>
                                <p style="margin-top: 10px; font-size: 13px;">Loading conversations...</p>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: ACTIVE CONVERSATION (Flex-1) -->
                    <div class="col-md-8" style="padding: 0; background: #f8fafc; display: flex; flex-direction: column; flex: 1;">
                        
                        <!-- Empty Placeholder when no thread selected -->
                        <div id="emptyThreadState" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; text-align: center;">
                            <div style="width: 70px; height: 70px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 15px;">
                                <i class="fa fa-comments-o"></i>
                            </div>
                            <h3 style="font-weight: 700; color: #334155; margin-bottom: 8px;">Select a Conversation</h3>
                            <p style="color: #64748b; font-size: 13px; max-width: 360px;">Choose a customer thread from the left list to answer inquiries, take over from AI, or launch audio/video calls.</p>
                        </div>

                        <!-- Active Chat Panel (Hidden until thread selected) -->
                        <div id="activeThreadPanel" style="display: none; flex-direction: column; flex: 1; height: 100%;">
                            
                            <!-- Chat Top Bar -->
                            <div style="padding: 12px 20px; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; items-center; gap: 12px;">
                                    <div style="width: 42px; height: 42px; border-radius: 50%; background: #0284c7; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px;">
                                        <span id="activeCustomerAvatar">C</span>
                                    </div>
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <h4 id="activeCustomerName" style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Customer Name</h4>
                                            <span id="activeModeBadge" class="label label-primary" style="font-size: 10px; border-radius: 10px; padding: 3px 8px;">AI Copilot</span>
                                        </div>
                                        <p id="activeCustomerDetails" style="margin: 2px 0 0; font-size: 12px; color: #64748b;">Guest User • Online</p>
                                    </div>
                                </div>

                                <!-- Action Toolbar -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <!-- WebRTC Voice Call -->
                                    <button onclick="startAdminWebRtcCall('audio')" class="btn btn-default btn-sm" style="border-radius: 20px; color: #059669; border-color: #a7f3d0;" title="Start Audio Call">
                                        <i class="fa fa-phone"></i> Voice Call
                                    </button>
                                    <!-- WebRTC Video Call -->
                                    <button onclick="startAdminWebRtcCall('video')" class="btn btn-default btn-sm" style="border-radius: 20px; color: #2563eb; border-color: #bfdbfe;" title="Start Video Call">
                                        <i class="fa fa-video-camera"></i> Video Call
                                    </button>
                                    <!-- AI Handover Button -->
                                    <button id="btnAdminTakeover" onclick="toggleAdminTakeover()" class="btn btn-warning btn-sm" style="border-radius: 20px; font-weight: 600;">
                                        <i class="fa fa-handshake-o"></i> Take Over Chat
                                    </button>
                                </div>
                            </div>

                            <!-- Messages Area -->
                            <div id="adminMessagesContainer" style="flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; max-height: 520px; background: #f8fafc;">
                                <!-- Messages injected dynamically -->
                            </div>

                            <!-- Quick Reply Buttons -->
                            <div style="padding: 8px 15px; background: #f1f5f9; border-top: 1px solid #e2e8f0; display: flex; gap: 6px; overflow-x: auto; white-space: nowrap;">
                                <button onclick="insertQuickReply('Hello! How can I help you today?')" class="btn btn-xs btn-default" style="border-radius: 12px; font-size: 11px;">👋 Greeting</button>
                                <button onclick="insertQuickReply('Let me check our stock and order details for you right now.')" class="btn btn-xs btn-default" style="border-radius: 12px; font-size: 11px;">📦 Checking Order</button>
                                <button onclick="insertQuickReply('Yes, this item is eligible for cash on delivery with 3-5 days delivery.')" class="btn btn-xs btn-default" style="border-radius: 12px; font-size: 11px;">🚚 Delivery Info</button>
                                <button onclick="insertQuickReply('Thank you for contacting us! Is there anything else I can help with?')" class="btn btn-xs btn-default" style="border-radius: 12px; font-size: 11px;">🙏 Closing</button>
                            </div>

                            <!-- Input Area -->
                            <div style="padding: 12px 15px; background: #ffffff; border-top: 1px solid #e2e8f0;">
                                <form id="adminReplyForm" onsubmit="handleAdminSendReply(event)" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                                    <input type="file" id="adminFileInput" accept="image/*,application/pdf" style="display: none;" onchange="handleAdminFileUpload(this)">
                                    <button type="button" onclick="document.getElementById('adminFileInput').click()" class="btn btn-default" style="border-radius: 50%; width: 38px; height: 38px; padding: 0; color: #64748b;" title="Attach Image or Document">
                                        <i class="fa fa-paperclip"></i>
                                    </button>
                                    
                                    <input type="text" id="adminReplyInput" class="form-control" placeholder="Type your reply to customer..." style="border-radius: 20px; border-color: #cbd5e1; font-size: 13px;" autocomplete="off">
                                    
                                    <button type="submit" class="btn btn-primary" style="border-radius: 20px; padding: 6px 18px; font-weight: 600;">
                                        <i class="fa fa-paper-plane"></i> Send
                                    </button>
                                </form>
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- WEBRTC ADMIN CALL OVERLAY -->
<div id="adminCallModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.95); z-index: 99999; flex-direction: column; justify-content: space-between; padding: 30px; color: #ffffff;">
    <div style="text-align: center; margin-top: 40px;">
        <div style="width: 80px; height: 80px; margin: 0 auto 15px; border-radius: 50%; background: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 32px; box-shadow: 0 10px 25px rgba(245, 158, 11, 0.4);">
            <i id="adminCallTypeIcon" class="fa fa-phone"></i>
        </div>
        <h3 id="adminCallPeerTitle" style="font-weight: 700; margin: 0 0 5px;">Calling Customer...</h3>
        <p id="adminCallTimer" style="font-family: monospace; font-size: 14px; color: #94a3b8; margin: 0;">Connecting...</p>
    </div>

    <!-- Video Containers -->
    <div id="adminVideoWrap" style="display: none; flex: 1; position: relative; max-width: 700px; width: 100%; margin: 20px auto; border-radius: 16px; overflow: hidden; background: #000000;">
        <video id="adminRemoteVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover;"></video>
        <video id="adminLocalVideo" autoplay playsinline muted style="position: absolute; bottom: 15px; right: 15px; width: 130px; height: 180px; border-radius: 12px; border: 2px solid #ffffff; object-fit: cover;"></video>
    </div>

    <audio id="adminRemoteAudio" autoplay playsinline></audio>

    <!-- Controls -->
    <div style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 40px;">
        <button onclick="toggleAdminMic()" id="btnAdminMic" class="btn btn-default" style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.15); border: none; color: #ffffff; font-size: 18px;">
            <i class="fa fa-microphone" id="adminMicIcon"></i>
        </button>
        <button onclick="hangupAdminCall()" class="btn btn-danger" style="width: 60px; height: 60px; border-radius: 50%; font-size: 22px; box-shadow: 0 8px 20px rgba(225, 29, 72, 0.5);">
            <i class="fa fa-phone"></i>
        </button>
        <button onclick="toggleAdminCam()" id="btnAdminCam" class="btn btn-default" style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.15); border: none; color: #ffffff; font-size: 18px;">
            <i class="fa fa-video-camera" id="adminCamIcon"></i>
        </button>
    </div>
</div>

<script>
let currentThreadId = null;
let currentThreadData = null;
let pollTimer = null;
let lastMsgId = 0;

// Fetch thread list from server
async function loadThreads() {
    try {
        const res = await fetch('../live_chat_api.php?action=admin_get_threads');
        const data = await res.json();
        if (data.status === 'success') {
            renderThreadList(data.threads);
        }
    } catch (e) {
        console.error('Failed to load threads:', e);
    }
}

function renderThreadList(threads) {
    const container = document.getElementById('threadListContainer');
    if (!threads || threads.length === 0) {
        container.innerHTML = '<div style="padding: 30px; text-align: center; color: #94a3b8; font-size: 13px;">No customer chats yet.</div>';
        return;
    }

    let html = '';
    threads.forEach(t => {
        const isSelected = (currentThreadId === t.id);
        const unreadBadge = t.unread_admin > 0 ? `<span class="badge" style="background: #ef4444; font-size: 10px; margin-left: 6px;">${t.unread_admin}</span>` : '';
        const modeBadge = t.mode === 'live' 
            ? '<span class="label label-danger" style="font-size: 9px; border-radius: 8px;">LIVE</span>'
            : '<span class="label label-default" style="font-size: 9px; border-radius: 8px;">AI</span>';

        html += `
            <div onclick="selectThread(${t.id})" style="padding: 14px 16px; border-bottom: 1px solid #f1f5f9; cursor: pointer; background: ${isSelected ? '#e0f2fe' : '#ffffff'}; transition: all 0.15s ease;" class="thread-item">
                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <strong style="color: #1e293b; font-size: 14px;">${escapeHtml(t.customer_name || 'Guest')}</strong>
                        ${unreadBadge}
                    </div>
                    <div>${modeBadge}</div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b;">
                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px;">
                        ${escapeHtml(t.last_message || 'Started conversation')}
                    </span>
                    <span style="font-size: 11px; color: #94a3b8;">${formatTime(t.updated_at)}</span>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// Select a customer thread
async function selectThread(id) {
    currentThreadId = id;
    document.getElementById('emptyThreadState').style.display = 'none';
    const panel = document.getElementById('activeThreadPanel');
    panel.style.display = 'flex';

    await refreshActiveThread();
    loadThreads(); // Refresh list selection state
}

async function refreshActiveThread() {
    if (!currentThreadId) return;

    try {
        const res = await fetch(`../live_chat_api.php?action=fetch_messages&last_id=0&thread_id=${currentThreadId}`);
        const data = await res.json();
        
        if (data.status === 'success') {
            // Update thread header
            document.getElementById('activeCustomerName').textContent = `Customer #${currentThreadId}`;
            const badge = document.getElementById('activeModeBadge');
            const takeoverBtn = document.getElementById('btnAdminTakeover');

            if (data.mode === 'live') {
                badge.className = 'label label-danger';
                badge.textContent = 'Live Agent Active';
                takeoverBtn.className = 'btn btn-default btn-sm';
                takeoverBtn.innerHTML = '<i class="fa fa-robot"></i> Hand Back to AI';
            } else {
                badge.className = 'label label-primary';
                badge.textContent = 'AI Pilot Active';
                takeoverBtn.className = 'btn btn-warning btn-sm';
                takeoverBtn.innerHTML = '<i class="fa fa-handshake-o"></i> Take Over Chat';
            }

            renderMessages(data.messages);
            lastMsgId = data.messages.length > 0 ? data.messages[data.messages.length - 1].id : 0;
        }

        // Check incoming WebRTC signals
        pollAdminWebRtcSignals();
    } catch (e) {
        console.error('Error refreshing active thread:', e);
    }
}

function renderMessages(messages) {
    const container = document.getElementById('adminMessagesContainer');
    container.innerHTML = '';

    messages.forEach(m => {
        const isMe = m.sender_type === 'admin';
        const isSystem = m.sender_type === 'system';
        const isAi = m.sender_type === 'ai';

        if (isSystem) {
            container.innerHTML += `
                <div style="text-align: center; margin: 8px 0;">
                    <span style="display: inline-block; padding: 4px 12px; background: #e2e8f0; border-radius: 12px; font-size: 11px; color: #475569;">
                        ${escapeHtml(m.message)}
                    </span>
                </div>
            `;
            return;
        }

        const align = isMe ? 'flex-end' : 'flex-start';
        const bg = isMe ? '#2563eb' : (isAi ? '#fef3c7' : '#ffffff');
        const color = isMe ? '#ffffff' : (isAi ? '#92400e' : '#1e293b');
        const border = isMe ? 'none' : (isAi ? '1px solid #fde68a' : '1px solid #e2e8f0');
        const senderLabel = isMe ? 'You' : (isAi ? 'AI Pilot' : 'Customer');

        let attach = '';
        if (m.attachment_url) {
            if (m.attachment_type === 'image') {
                attach = `<div style="margin-top: 6px;"><img src="../${m.attachment_url}" style="max-width: 240px; border-radius: 8px; cursor: pointer;" onclick="window.open('../${m.attachment_url}')"></div>`;
            } else {
                attach = `<div style="margin-top: 6px;"><a href="../${m.attachment_url}" target="_blank" style="color: inherit; text-decoration: underline;">Attachment File</a></div>`;
            }
        }

        container.innerHTML += `
            <div style="display: flex; flex-direction: column; align-items: ${align};">
                <span style="font-size: 10px; color: #94a3b8; margin-bottom: 2px;">${senderLabel}</span>
                <div style="background: ${bg}; color: ${color}; border: ${border}; padding: 10px 14px; border-radius: 14px; max-width: 75%; font-size: 13px; line-height: 1.4; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    ${escapeHtml(m.message)}
                    ${attach}
                </div>
            </div>
        `;
    });

    container.scrollTop = container.scrollHeight;
}

// Send Admin Reply
async function handleAdminSendReply(e) {
    if (e && e.preventDefault) e.preventDefault();
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
            loadThreads();
        }
    } catch (e) {
        alert('Could not send reply.');
    }
}

// Insert Quick Reply
function insertQuickReply(text) {
    const input = document.getElementById('adminReplyInput');
    input.value = text;
    input.focus();
}

// Toggle Takeover between Admin and AI
async function toggleAdminTakeover() {
    if (!currentThreadId) return;
    const badge = document.getElementById('activeModeBadge');
    const targetMode = badge.textContent.includes('Live') ? 'ai' : 'live';

    try {
        const fd = new FormData();
        fd.append('thread_id', currentThreadId);
        fd.append('mode', targetMode);

        const res = await fetch('../live_chat_api.php?action=admin_takeover', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            await refreshActiveThread();
            loadThreads();
        }
    } catch (e) {
        console.error('Failed to change mode:', e);
    }
}

// Handle Admin File Attachment
async function handleAdminFileUpload(input) {
    if (!input.files || !input.files[0] || !currentThreadId) return;

    const fd = new FormData();
    fd.append('attachment', input.files[0]);

    try {
        const res = await fetch('../live_chat_api.php?action=upload_attachment', { method: 'POST', body: fd });
        const d = await res.json();
        if (d.status === 'success') {
            const sendFd = new FormData();
            sendFd.append('thread_id', currentThreadId);
            sendFd.append('attachment_url', d.url);
            sendFd.append('attachment_type', d.type);
            sendFd.append('message', '');

            await fetch('../live_chat_api.php?action=admin_send_reply', { method: 'POST', body: sendFd });
            await refreshActiveThread();
        }
    } catch (e) {
        alert('File upload failed.');
    }
    input.value = '';
}

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
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ==========================================
// ADMIN WEBRTC AUDIO & VIDEO CALL ENGINE
// ==========================================
let adminPeer = null;
let adminLocalStream = null;
let adminCallType = 'audio';
let adminCallTimerInterval = null;
let adminCallStartTime = null;

const adminRtcConfig = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
    ]
};

async function startAdminWebRtcCall(type) {
    if (!currentThreadId) {
        alert('Please select a customer conversation first.');
        return;
    }

    adminCallType = type;
    const modal = document.getElementById('adminCallModal');
    modal.style.display = 'flex';
    document.getElementById('adminCallTimer').textContent = 'Connecting...';
    document.getElementById('adminCallTypeIcon').className = type === 'video' ? 'fa fa-video-camera' : 'fa fa-phone';

    if (type === 'video') {
        document.getElementById('adminVideoWrap').style.display = 'flex';
    } else {
        document.getElementById('adminVideoWrap').style.display = 'none';
    }

    try {
        const constraints = {
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            video: type === 'video' ? { width: { ideal: 640 }, height: { ideal: 480 } } : false
        };

        adminLocalStream = await navigator.mediaDevices.getUserMedia(constraints);
        if (type === 'video') {
            document.getElementById('adminLocalVideo').srcObject = adminLocalStream;
        }

        adminPeer = new RTCPeerConnection(adminRtcConfig);
        adminLocalStream.getTracks().forEach(t => adminPeer.addTrack(t, adminLocalStream));

        adminPeer.ontrack = (e) => {
            if (type === 'video') {
                document.getElementById('adminRemoteVideo').srcObject = e.streams[0];
            } else {
                document.getElementById('adminRemoteAudio').srcObject = e.streams[0];
            }
            startAdminCallTimer();
        };

        adminPeer.onicecandidate = (e) => {
            if (e.candidate) {
                sendAdminSignal('candidate', JSON.stringify(e.candidate), type);
            }
        };

        const offer = await adminPeer.createOffer();
        await adminPeer.setLocalDescription(offer);

        sendAdminSignal('call_start', '', type);
        sendAdminSignal('offer', JSON.stringify(offer), type);

    } catch (e) {
        alert('Could not access microphone/camera. Please grant permissions.');
        hangupAdminCall();
    }
}

async function pollAdminWebRtcSignals() {
    if (!currentThreadId) return;

    try {
        const res = await fetch(`../live_chat_api.php?action=fetch_signals&receiver=admin&thread_id=${currentThreadId}`);
        const data = await res.json();
        if (data.status === 'success' && data.signals && data.signals.length > 0) {
            for (const sig of data.signals) {
                handleIncomingAdminSignal(sig);
            }
        }
    } catch (e) {}
}

async function handleIncomingAdminSignal(sig) {
    if (sig.signal_type === 'answer' && adminPeer) {
        const ans = JSON.parse(sig.payload);
        await adminPeer.setRemoteDescription(new RTCSessionDescription(ans));
    } else if (sig.signal_type === 'candidate' && adminPeer) {
        const cand = JSON.parse(sig.payload);
        await adminPeer.addIceCandidate(new RTCIceCandidate(cand));
    } else if (sig.signal_type === 'call_end') {
        hangupAdminCall(false);
    }
}

async function sendAdminSignal(type, payload, callType) {
    if (!currentThreadId) return;
    try {
        const fd = new FormData();
        fd.append('sender', 'admin');
        fd.append('thread_id', currentThreadId);
        fd.append('signal_type', type);
        fd.append('call_type', callType);
        fd.append('payload', payload);
        await fetch('../live_chat_api.php?action=call_signal', { method: 'POST', body: fd });
    } catch (e) {}
}

function startAdminCallTimer() {
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
    if (notify) sendAdminSignal('call_end', '', adminCallType);

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

    document.getElementById('adminCallModal').style.display = 'none';
    document.getElementById('adminCallTimer').textContent = 'Connecting...';
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
    if (!adminLocalStream) return;
    const track = adminLocalStream.getVideoTracks()[0];
    if (track) {
        track.enabled = !track.enabled;
        document.getElementById('adminCamIcon').className = track.enabled ? 'fa fa-video-camera' : 'fa fa-video-camera text-danger';
    }
}

// Background poller
document.addEventListener('DOMContentLoaded', () => {
    loadThreads();
    pollTimer = setInterval(() => {
        loadThreads();
        if (currentThreadId) {
            refreshActiveThread();
        }
    }, 3500);
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
