/**
 * ShopMart Global Admin AI Voice Widget
 * Provides real-time speech operations and quick AI drawer across ALL admin pages
 */

(function() {
    'use strict';

    // Skip if already on dedicated ai-copilot.php
    if (window.location.pathname.includes('ai-copilot.php')) {
        return;
    }

    // 1. INJECT FLOATING VOICE TRIGGER & QUICK DRAWER
    const drawerHtml = `
    <!-- Global AI Quick Voice Drawer -->
    <div id="snGlobalAiDrawer" class="sn-global-ai-drawer" style="display:none;">
        <div class="sn-global-ai-header">
            <div class="sn-global-ai-title">
                <span class="sn-ai-sparkle">✨</span>
                <strong>AI Voice Copilot</strong>
                <span class="sn-ai-tag">PRO</span>
            </div>
            <div class="sn-global-ai-controls">
                <a href="ai-copilot.php" class="btn btn-xs btn-warning" title="Open Fullscreen Studio"><i class="fa fa-expand"></i> Studio</a>
                <button type="button" class="sn-ai-close-btn" onclick="toggleGlobalAiDrawer(false)">&times;</button>
            </div>
        </div>

        <div class="sn-global-ai-body" id="globalAiBody">
            <div class="sn-global-ai-greeting">
                <p><strong>Voice Assistant Ready.</strong> Speak a store command or type below.</p>
                <div class="sn-global-chips">
                    <button type="button" onclick="sendGlobalAiPrompt('What are today\'s sales and revenue?')">📊 Today's Sales</button>
                    <button type="button" onclick="sendGlobalAiPrompt('Run fraud detection scan on recent orders')">🛡️ Fraud Scan</button>
                    <button type="button" onclick="sendGlobalAiPrompt('Show all low stock products')">⚠️ Low Stock</button>
                    <button type="button" onclick="window.location.href='ai-copilot.php'">📸 5-Image Vision Ingestion</button>
                </div>
            </div>
            <div class="sn-global-ai-feed" id="globalAiFeed"></div>
        </div>

        <!-- Global Audio Waveform -->
        <div class="sn-global-waveform" id="globalWaveform" style="display:none;">
            <div class="sn-wave-dots"><span></span><span></span><span></span></div>
            <span id="globalWaveformText">Listening... Speak now</span>
        </div>

        <div class="sn-global-ai-footer">
            <button type="button" class="sn-global-mic-btn" id="globalMicBtn" onclick="toggleGlobalVoice()">
                <i class="fa fa-microphone"></i>
            </button>
            <input type="text" id="globalPromptInput" placeholder="Speak or type command..." onkeydown="if(event.key==='Enter')sendGlobalUserPrompt()">
            <button type="button" class="sn-global-send-btn" onclick="sendGlobalUserPrompt()">
                <i class="fa fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <!-- Floating Global Launcher Button (Bottom Right) -->
    <button type="button" id="snFloatingAiBtn" class="sn-floating-ai-btn" onclick="toggleGlobalAiDrawer()" title="AI Voice Copilot (Ctrl+Space)">
        <span class="sn-floating-sparkle">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3">
                <path d="M12 2l2.4 7.4 7.6 2.6-7.6 2.6L12 2l-2.4-7.4L2 12l7.6-2.6L12 2z"/>
            </svg>
        </span>
        <span class="sn-floating-label">AI Voice</span>
    </button>
    `;

    const container = document.createElement('div');
    container.id = 'snGlobalAiContainer';
    container.innerHTML = drawerHtml;
    document.body.appendChild(container);

    // 2. INJECT CSS STYLES FOR GLOBAL DRAWER
    const style = document.createElement('style');
    style.textContent = `
    .sn-floating-ai-btn {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1040;
        background: #FEDB65;
        border: 2px solid #F59E0B;
        color: #0F172A;
        border-radius: 9999px;
        padding: 10px 18px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 800;
        font-size: 13px;
        box-shadow: 0 4px 16px rgba(245, 158, 11, 0.4);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .sn-floating-ai-btn:hover {
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.55);
        background: #FDD835;
    }
    .sn-global-ai-drawer {
        position: fixed;
        bottom: 80px;
        right: 24px;
        width: 380px;
        max-width: 92vw;
        height: 520px;
        max-height: 80vh;
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        z-index: 1050;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: snDrawerSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes snDrawerSlideUp {
        from { transform: translateY(20px) scale(0.96); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }
    .sn-global-ai-header {
        background: #FFFDF0;
        border-bottom: 1px solid #FEDB65;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .sn-global-ai-title {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #0F172A;
        font-size: 14px;
    }
    .sn-ai-tag {
        background: #F59E0B;
        color: #FFFFFF;
        font-size: 9px;
        font-weight: 800;
        padding: 2px 5px;
        border-radius: 4px;
    }
    .sn-global-ai-controls {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sn-ai-close-btn {
        background: transparent;
        border: none;
        font-size: 20px;
        line-height: 1;
        color: #64748B;
        cursor: pointer;
    }
    .sn-global-ai-body {
        flex: 1;
        overflow-y: auto;
        padding: 14px;
        background: #F8FAFC;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .sn-global-ai-greeting p {
        font-size: 12.5px;
        color: #475569;
        margin: 0 0 8px 0;
    }
    .sn-global-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .sn-global-chips button {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 9999px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 600;
        color: #1E293B;
        cursor: pointer;
        transition: all 0.15s;
    }
    .sn-global-chips button:hover {
        background: #FFFBEA;
        border-color: #FEDB65;
        color: #B45309;
    }
    .sn-global-ai-feed {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .sn-global-feed-msg {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 12.5px;
        line-height: 1.5;
        color: #1E293B;
    }
    .sn-global-waveform {
        background: #FEF3C7;
        padding: 6px 14px;
        font-size: 11.5px;
        font-weight: 700;
        color: #92400E;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sn-wave-dots span {
        display: inline-block;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #EF4444;
        animation: snBounce 1s infinite alternate;
    }
    .sn-global-ai-footer {
        background: #FFFFFF;
        border-top: 1px solid #E2E8F0;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sn-global-mic-btn {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #F1F5F9;
        border: 1px solid #E2E8F0;
        color: #0F172A;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .sn-global-mic-btn.active {
        background: #EF4444;
        color: #FFFFFF;
        animation: snMicPulse 1s infinite;
    }
    .sn-global-ai-footer input {
        flex: 1;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 12.5px;
        outline: none;
    }
    .sn-global-send-btn {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #FEDB65;
        border: none;
        color: #0F172A;
        cursor: pointer;
    }
    @media (max-width: 768px) {
        .sn-floating-ai-btn {
            bottom: 74px; /* Sits above mobile bottom dock */
            right: 14px;
            padding: 8px 14px;
        }
        .sn-global-ai-drawer {
            bottom: 125px;
            right: 10px;
            left: 10px;
            width: auto;
        }
    }
    `;
    document.head.appendChild(style);

    // 3. VOICE SPEECH LOGIC IN GLOBAL DRAWER
    let isGlobalListening = false;
    let globalRecognition = null;

    function initGlobalSpeech() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) return null;

        const rec = new SpeechRecognition();
        rec.lang = 'en-US';
        rec.continuous = false;
        rec.interimResults = true;

        rec.onstart = function() {
            isGlobalListening = true;
            const mic = document.getElementById('globalMicBtn');
            const wave = document.getElementById('globalWaveform');
            if (mic) mic.classList.add('active');
            if (wave) wave.style.display = 'flex';
        };

        rec.onresult = function(e) {
            let transcript = '';
            for (let i = e.resultIndex; i < e.results.length; ++i) {
                transcript += e.results[i][0].transcript;
            }
            const input = document.getElementById('globalPromptInput');
            if (input) input.value = transcript;
        };

        rec.onerror = function() {
            stopGlobalSpeech();
        };

        rec.onend = function() {
            stopGlobalSpeech();
            const input = document.getElementById('globalPromptInput');
            if (input && input.value.trim().length > 3) {
                sendGlobalUserPrompt();
            }
        };

        return rec;
    }

    window.toggleGlobalVoice = function() {
        if (!globalRecognition) globalRecognition = initGlobalSpeech();
        if (!globalRecognition) {
            alert('Voice recognition not supported in this browser.');
            return;
        }
        if (isGlobalListening) {
            globalRecognition.stop();
        } else {
            try { globalRecognition.start(); } catch(e){}
        }
    };

    function stopGlobalSpeech() {
        isGlobalListening = false;
        const mic = document.getElementById('globalMicBtn');
        const wave = document.getElementById('globalWaveform');
        if (mic) mic.classList.remove('active');
        if (wave) wave.style.display = 'none';
    }

    // Speech Synthesis output
    function speakGlobal(text) {
        if (!window.speechSynthesis || !text) return;
        window.speechSynthesis.cancel();
        const ut = new SpeechSynthesisUtterance(text.replace(/[*_#`]/g, ''));
        ut.rate = 1.05;
        window.speechSynthesis.speak(ut);
    }

    // 4. ACTION SUBMISSION
    window.toggleGlobalAiDrawer = function(forceOpen) {
        const drawer = document.getElementById('snGlobalAiDrawer');
        if (!drawer) return;
        if (forceOpen === undefined) {
            drawer.style.display = (drawer.style.display === 'none') ? 'flex' : 'none';
        } else {
            drawer.style.display = forceOpen ? 'flex' : 'none';
        }
    };

    window.sendGlobalAiPrompt = function(prompt) {
        const input = document.getElementById('globalPromptInput');
        if (input) input.value = prompt;
        sendGlobalUserPrompt();
    };

    window.sendGlobalUserPrompt = function() {
        const input = document.getElementById('globalPromptInput');
        const text = input ? input.value.trim() : '';
        if (!text) return;

        const feed = document.getElementById('globalAiFeed');
        if (feed) {
            const userMsg = document.createElement('div');
            userMsg.className = 'sn-global-feed-msg';
            userMsg.style.background = '#0F172A';
            userMsg.style.color = '#FFFFFF';
            userMsg.textContent = text;
            feed.appendChild(userMsg);
        }

        if (input) input.value = '';

        const formData = new FormData();
        formData.append('prompt', text);

        fetch('ai_admin_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (feed) {
                const botMsg = document.createElement('div');
                botMsg.className = 'sn-global-feed-msg';
                botMsg.innerHTML = data.display_html;
                feed.appendChild(botMsg);
                feed.scrollTop = feed.scrollHeight;
            }
            if (data.voice_text) {
                speakGlobal(data.voice_text);
            }
        })
        .catch(err => {
            console.error('Global AI Error:', err);
        });
    };

    // Hotkey: Ctrl+Space opens the Global AI Drawer
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.code === 'Space') {
            e.preventDefault();
            toggleGlobalAiDrawer();
        }
    });

})();
