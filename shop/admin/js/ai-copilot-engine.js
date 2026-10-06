/**
 * ShopMart AI Autonomous Operations & Voice Engine
 * Handles Multimodal Image Ingestion, Web Speech API (STT & TTS), Action Dispatch, and Real-time UI Cards
 */

(function() {
    'use strict';

    // Global State
    let selectedFiles = [];
    let isListening = false;
    let isSpeaking = false;
    let speechRecognition = null;
    let isVoiceMuted = localStorage.getItem('sn_ai_voice_muted') === 'true';

    // DOM Elements
    const promptInput = document.getElementById('aiPromptInput');
    const messagesWrap = document.getElementById('aiMessagesWrap');
    const previewStrip = document.getElementById('aiPreviewStrip');
    const dropzoneTray = document.getElementById('aiDropzoneTray');
    const trayFileCount = document.getElementById('trayFileCount');
    const attachCountBadge = document.getElementById('attachCountBadge');
    const voiceMicBtn = document.getElementById('voiceMicBtn');
    const waveformBar = document.getElementById('aiWaveformBar');
    const waveformStatus = document.getElementById('aiWaveformStatus');
    const voiceStatusHint = document.getElementById('voiceStatusHint');
    const toggleSpeechBtn = document.getElementById('toggleSpeechBtn');
    const speechVolumeIcon = document.getElementById('speechVolumeIcon');
    const speechVolumeText = document.getElementById('speechVolumeText');
    const fileInput = document.getElementById('aiImageInput');

    // 1. INITIALIZE SPEECH RECOGNITION
    function initSpeechRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            if (voiceStatusHint) voiceStatusHint.innerHTML = '<span class="text-warning"><i class="fa fa-info-circle"></i> Speech recognition not supported in this browser. Use Chrome, Edge, or Safari.</span>';
            return null;
        }

        const recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.lang = 'en-US';

        recognition.onstart = function() {
            isListening = true;
            if (voiceMicBtn) voiceMicBtn.classList.add('listening');
            if (waveformBar) waveformBar.style.display = 'flex';
            if (waveformStatus) waveformStatus.textContent = 'Listening to your voice... Speak now';
            if (voiceStatusHint) voiceStatusHint.innerHTML = '<span class="text-success"><i class="fa fa-dot-circle-o"></i> Listening live...</span>';
        };

        recognition.onresult = function(event) {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                transcript += event.results[i][0].transcript;
            }
            if (promptInput) {
                promptInput.value = transcript;
            }
        };

        recognition.onerror = function(event) {
            console.warn('Speech recognition error:', event.error);
            stopVoiceListening();
        };

        recognition.onend = function() {
            stopVoiceListening();
            // If user said something and stopped, auto-trigger execution
            const text = promptInput ? promptInput.value.trim() : '';
            if (text.length > 3) {
                setTimeout(() => {
                    sendUserPrompt();
                }, 400);
            }
        };

        return recognition;
    }

    speechRecognition = initSpeechRecognition();

    window.toggleVoiceListening = function() {
        if (!speechRecognition) {
            speechRecognition = initSpeechRecognition();
            if (!speechRecognition) {
                alert('Speech Recognition is not supported on this browser. Please use Chrome, Edge, or Safari.');
                return;
            }
        }

        if (isListening) {
            speechRecognition.stop();
            stopVoiceListening();
        } else {
            // Stop any ongoing speech synthesis first
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            try {
                speechRecognition.start();
            } catch (e) {
                console.warn('Speech start error:', e);
            }
        }
    };

    window.stopVoiceListening = function() {
        isListening = false;
        if (speechRecognition) {
            try { speechRecognition.stop(); } catch(e) {}
        }
        if (voiceMicBtn) voiceMicBtn.classList.remove('listening');
        if (waveformBar) waveformBar.style.display = 'none';
        if (voiceStatusHint) voiceStatusHint.innerHTML = '<i class="fa fa-microphone"></i> Click microphone to speak';
    };

    // 2. SPEECH SYNTHESIS (VOICE OUTPUT)
    function speakText(text) {
        if (isVoiceMuted || !text || !window.speechSynthesis) return;

        window.speechSynthesis.cancel(); // Stop prior audio
        const cleanText = text.replace(/[*_#`]/g, '').trim();
        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.rate = 1.05;
        utterance.pitch = 1.0;

        // Choose friendly English voice if available
        const voices = window.speechSynthesis.getVoices();
        const preferredVoice = voices.find(v => v.lang.startsWith('en') && (v.name.includes('Google') || v.name.includes('Natural') || v.name.includes('Samantha') || v.name.includes('Daniel')));
        if (preferredVoice) utterance.voice = preferredVoice;

        utterance.onstart = function() {
            isSpeaking = true;
            if (waveformBar) {
                waveformBar.style.display = 'flex';
                waveformStatus.textContent = 'AI Speaking...';
            }
        };

        utterance.onend = function() {
            isSpeaking = false;
            if (waveformBar && !isListening) waveformBar.style.display = 'none';

            // Hands-Free Continuous Mode: auto re-arm mic for ongoing conversation
            if (isHandsFree && !isListening) {
                setTimeout(() => {
                    playAudioCue('mic');
                    window.toggleVoiceListening();
                }, 700);
            }
        };

        utterance.onerror = function() {
            isSpeaking = false;
            if (waveformBar && !isListening) waveformBar.style.display = 'none';
        };

        window.speechSynthesis.speak(utterance);
    }

    // Audio Cues using Native Web Audio API
    function playAudioCue(type) {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            gain.gain.setValueAtTime(0.04, ctx.currentTime);

            if (type === 'mic') {
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
                osc.start();
                osc.stop(ctx.currentTime + 0.2);
            } else if (type === 'done') {
                osc.frequency.setValueAtTime(523.25, ctx.currentTime); // C5
                osc.frequency.exponentialRampToValueAtTime(1046.50, ctx.currentTime + 0.25); // C6
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
                osc.start();
                osc.stop(ctx.currentTime + 0.3);
            }
        } catch(e) {}
    }

    // Hands-Free Mode Toggle
    let isHandsFree = localStorage.getItem('sn_ai_handsfree') === 'true';
    const handsFreeBtn = document.getElementById('handsFreeBtn');
    const handsFreeText = document.getElementById('handsFreeText');
    const handsFreeIcon = document.getElementById('handsFreeIcon');

    window.toggleHandsFreeMode = function() {
        isHandsFree = !isHandsFree;
        localStorage.setItem('sn_ai_handsfree', isHandsFree);
        updateHandsFreeUI();
        if (isHandsFree && !isListening) {
            playAudioCue('mic');
            window.toggleVoiceListening();
        }
    };

    function updateHandsFreeUI() {
        if (!handsFreeBtn) return;
        if (isHandsFree) {
            handsFreeBtn.classList.add('active');
            if (handsFreeText) handsFreeText.textContent = 'Hands-Free: ON';
            if (handsFreeIcon) handsFreeIcon.className = 'fa fa-refresh fa-spin text-success';
        } else {
            handsFreeBtn.classList.remove('active');
            if (handsFreeText) handsFreeText.textContent = 'Hands-Free: Off';
            if (handsFreeIcon) handsFreeIcon.className = 'fa fa-refresh text-muted';
        }
    }
    updateHandsFreeUI();

    window.toggleSpeechSynthesis = function() {
        isVoiceMuted = !isVoiceMuted;
        localStorage.setItem('sn_ai_voice_muted', isVoiceMuted);
        updateVoiceMuteButtonUI();
        if (isVoiceMuted && window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
    };

    function updateVoiceMuteButtonUI() {
        if (!toggleSpeechBtn) return;
        if (isVoiceMuted) {
            speechVolumeIcon.className = 'fa fa-volume-off text-muted';
            speechVolumeText.textContent = 'Voice Off';
            toggleSpeechBtn.classList.add('muted');
        } else {
            speechVolumeIcon.className = 'fa fa-volume-up text-success';
            speechVolumeText.textContent = 'Voice On';
            toggleSpeechBtn.classList.remove('muted');
        }
    }
    updateVoiceMuteButtonUI();

    // 3. MULTI-IMAGE DRAG & DROP & ATTACHMENT HANDLING
    window.triggerImagePicker = function() {
        if (fileInput) fileInput.click();
    };

    window.handleImageSelection = function(input) {
        if (!input.files || input.files.length === 0) return;
        addFiles(Array.from(input.files));
        input.value = ''; // Reset input to allow re-selecting same files if desired
    };

    function addFiles(files) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        for (const file of files) {
            if (selectedFiles.length >= 5) {
                alert('You can attach a maximum of 5 product images at a time.');
                break;
            }
            if (!allowedTypes.includes(file.type)) {
                alert(`File "${file.name}" is not a valid image. Only JPG, PNG, and WebP are supported.`);
                continue;
            }
            if (file.size > 8 * 1024 * 1024) {
                alert(`File "${file.name}" exceeds the 8MB size limit.`);
                continue;
            }
            selectedFiles.push(file);
        }
        renderImagePreviews();
    }

    function renderImagePreviews() {
        if (!previewStrip || !dropzoneTray) return;

        if (selectedFiles.length === 0) {
            dropzoneTray.style.display = 'none';
            if (attachCountBadge) attachCountBadge.style.display = 'none';
            return;
        }

        dropzoneTray.style.display = 'block';
        if (attachCountBadge) {
            attachCountBadge.textContent = selectedFiles.length;
            attachCountBadge.style.display = 'inline-block';
        }
        if (trayFileCount) trayFileCount.textContent = selectedFiles.length;

        previewStrip.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const card = document.createElement('div');
            card.className = 'sn-ai-thumb-card';

            const reader = new FileReader();
            reader.onload = function(e) {
                card.innerHTML = `
                    <div class="sn-ai-thumb-box">
                        <img src="${e.target.result}" alt="${file.name}">
                        <button type="button" class="sn-ai-thumb-del" onclick="removeSelectedFile(${index})" title="Remove Image">&times;</button>
                        ${index === 0 ? '<span class="sn-ai-primary-tag">Primary</span>' : `<span class="sn-ai-gallery-tag">#${index + 1}</span>`}
                    </div>
                    <span class="sn-ai-thumb-name" title="${file.name}">${file.name}</span>
                `;
            };
            reader.readAsDataURL(file);
            previewStrip.appendChild(card);
        });
    }

    window.removeSelectedFile = function(index) {
        selectedFiles.splice(index, 1);
        renderImagePreviews();
    };

    window.clearSelectedImages = function() {
        selectedFiles = [];
        renderImagePreviews();
    };

    window.submitImagesWithPrompt = function() {
        if (selectedFiles.length === 0) {
            alert('Please select at least one product image first.');
            return;
        }
        if (!promptInput.value.trim()) {
            promptInput.value = `Add these ${selectedFiles.length} product images into inventory with full SEO details, category, and pricing.`;
        }
        sendUserPrompt();
    };

    // Drag and Drop listeners onto chat card
    const chatCard = document.querySelector('.sn-ai-chat-card');
    if (chatCard) {
        ['dragenter', 'dragover'].forEach(eventName => {
            chatCard.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                chatCard.classList.add('sn-ai-drag-over');
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            chatCard.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                chatCard.classList.remove('sn-ai-drag-over');
            }, false);
        });
        chatCard.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                addFiles(Array.from(files));
            }
        });
    }

    // 4. DISPATCH AI PROMPT & COMMUNICATE WITH BACKEND API
    window.sendAiPrompt = function(text) {
        if (promptInput) promptInput.value = text;
        sendUserPrompt();
    };

    window.handleInputKeydown = function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendUserPrompt();
        }
    };

    window.sendUserPrompt = function() {
        const text = promptInput ? promptInput.value.trim() : '';
        if (!text && selectedFiles.length === 0) {
            if (promptInput) promptInput.focus();
            return;
        }

        // Append User Message to Chat
        appendUserMessage(text, selectedFiles);

        // Prepare FormData
        const formData = new FormData();
        formData.append('prompt', text || 'Analyze these product images and add to inventory.');
        selectedFiles.forEach(file => {
            formData.append('images[]', file);
        });

        // Clear input and files from tray
        const attachedCount = selectedFiles.length;
        selectedFiles = [];
        renderImagePreviews();
        if (promptInput) promptInput.value = '';

        // Show Thinking Indicator
        const loadingMsgId = appendThinkingIndicator();

        // Send AJAX Request
        fetch('ai_admin_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            removeThinkingIndicator(loadingMsgId);
            if (data.status === 'success') {
                appendBotMessage(data.display_html);
                if (data.voice_text) {
                    speakText(data.voice_text);
                }
            } else {
                appendBotMessage(`<div class="sn-ai-card sn-ai-card-error"><p><i class="fa fa-exclamation-triangle text-danger"></i> ${data.message || 'Error processing request.'}</p></div>`);
                if (data.voice_text) speakText(data.voice_text);
            }
        })
        .catch(err => {
            console.error('AI Request Error:', err);
            removeThinkingIndicator(loadingMsgId);
            appendBotMessage(`<div class="sn-ai-card sn-ai-card-error"><p><i class="fa fa-exclamation-triangle text-danger"></i> Failed to communicate with the AI engine. Please verify connectivity.</p></div>`);
            speakText("There was a connection issue communicating with the store assistant.");
        });
    };

    // 5. DOM CHAT RENDERING HELPERS
    function appendUserMessage(text, files) {
        if (!messagesWrap) return;
        const msgDiv = document.createElement('div');
        msgDiv.className = 'sn-ai-msg sn-ai-msg-user';

        let filesHtml = '';
        if (files && files.length > 0) {
            filesHtml = '<div class="sn-ai-msg-attached-strip">';
            files.forEach(f => {
                const blobUrl = URL.createObjectURL(f);
                filesHtml += `<img src="${blobUrl}" class="sn-ai-msg-thumb" alt="${f.name}">`;
            });
            filesHtml += `</div><small class="sn-ai-attached-label"><i class="fa fa-paperclip"></i> ${files.length} Photo(s) Attached</small>`;
        }

        msgDiv.innerHTML = `
            <div class="sn-ai-bubble">
                ${filesHtml}
                ${text ? `<p class="sn-ai-user-text">${escapeHtml(text)}</p>` : ''}
            </div>
            <div class="sn-ai-avatar user-avatar">
                <i class="fa fa-user"></i>
            </div>
        `;
        messagesWrap.appendChild(msgDiv);
        scrollToBottom();
    }

    function appendThinkingIndicator() {
        if (!messagesWrap) return null;
        const id = 'loading_' + Date.now();
        const msgDiv = document.createElement('div');
        msgDiv.className = 'sn-ai-msg sn-ai-msg-bot';
        msgDiv.id = id;
        msgDiv.innerHTML = `
            <div class="sn-ai-avatar">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3">
                    <path d="M12 2l2.4 7.4 7.6 2.6-7.6 2.6L12 2l-2.4-7.4L2 12l7.6-2.6L12 2z"/>
                </svg>
            </div>
            <div class="sn-ai-bubble thinking-bubble">
                <div class="sn-ai-dots">
                    <span></span><span></span><span></span>
                </div>
                <small class="text-muted" style="margin-left:8px;">AI Copilot analyzing & executing action...</small>
            </div>
        `;
        messagesWrap.appendChild(msgDiv);
        scrollToBottom();
        return id;
    }

    function removeThinkingIndicator(id) {
        if (!id) return;
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function appendBotMessage(html) {
        if (!messagesWrap) return;
        const msgDiv = document.createElement('div');
        msgDiv.className = 'sn-ai-msg sn-ai-msg-bot';
        msgDiv.innerHTML = `
            <div class="sn-ai-avatar">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3">
                    <path d="M12 2l2.4 7.4 7.6 2.6-7.6 2.6L12 2l-2.4-7.4L2 12l7.6-2.6L12 2z"/>
                </svg>
            </div>
            <div class="sn-ai-bubble">
                ${html}
            </div>
        `;
        messagesWrap.appendChild(msgDiv);
        scrollToBottom();
    }

    function scrollToBottom() {
        if (!messagesWrap) return;
        messagesWrap.scrollTop = messagesWrap.scrollHeight;
    }

    window.clearAiChat = function() {
        if (!confirm('Clear this conversation history?')) return;
        if (messagesWrap) {
            messagesWrap.innerHTML = `
                <div class="sn-ai-msg sn-ai-msg-bot">
                    <div class="sn-ai-avatar">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3">
                            <path d="M12 2l2.4 7.4 7.6 2.6-7.6 2.6L12 2l-2.4-7.4L2 12l7.6-2.6L12 2z"/>
                        </svg>
                    </div>
                    <div class="sn-ai-bubble">
                        <p>Conversation cleared. Ready for your next command or product image batch!</p>
                    </div>
                </div>
            `;
        }
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Keyboard Shortcuts: Ctrl+Space or Alt+A to toggle Voice Mic
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey && e.code === 'Space') || (e.altKey && e.key.toLowerCase() === 'a')) {
            e.preventDefault();
            toggleVoiceListening();
        }
    });

})();
