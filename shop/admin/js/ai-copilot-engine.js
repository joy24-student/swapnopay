/**
 * ShopMart Google Gemini Aesthetic AI Operations & Gemini Live Engine
 * Features: Real-time Gemini Live Voice Conversation, Dynamic Web Audio Aurora Orb,
 * Multimodal 5-Image Vision Ingestion, and Autonomous Operations Dispatch.
 */

(function() {
    'use strict';

    // Global State
    let selectedFiles = [];
    let isListening = false;
    let isSpeaking = false;
    let isLiveActive = false;
    let isLiveMicMuted = false;
    let speechRecognition = null;
    let isVoiceMuted = localStorage.getItem('sn_ai_voice_muted') === 'true';
    let isHandsFree = localStorage.getItem('sn_ai_handsfree') === 'true';

    // Audio Analysis State for Gemini Aurora Orb
    let audioContext = null;
    let analyserNode = null;
    let microphoneStream = null;
    let audioAnimFrame = null;

    // DOM Elements - Gemini UI
    const promptInput = document.getElementById('aiPromptInput');
    const messagesWrap = document.getElementById('aiMessagesWrap');
    const geminiHero = document.getElementById('geminiHero');
    const previewStrip = document.getElementById('aiPreviewStrip');
    const dropzoneTray = document.getElementById('aiDropzoneTray');
    const trayFileCount = document.getElementById('trayFileCount');
    const attachCountBadge = document.getElementById('attachCountBadge');
    const voiceMicBtn = document.getElementById('voiceMicBtn');
    const waveformBar = document.getElementById('aiWaveformBar');
    const waveformStatus = document.getElementById('aiWaveformStatus');
    const aiSendBtn = document.getElementById('aiSendBtn');
    const toggleSpeechBtn = document.getElementById('toggleSpeechBtn');
    const speechVolumeIcon = document.getElementById('speechVolumeIcon');
    const speechVolumeText = document.getElementById('speechVolumeText');
    const handsFreeBtn = document.getElementById('handsFreeBtn');
    const handsFreeText = document.getElementById('handsFreeText');
    const handsFreeIcon = document.getElementById('handsFreeIcon');
    const fileInput = document.getElementById('aiImageInput');

    // DOM Elements - Gemini Live Overlay
    const geminiLiveOverlay = document.getElementById('geminiLiveOverlay');
    const geminiLiveStateLabel = document.getElementById('geminiLiveStateLabel');
    const geminiLiveTranscript = document.getElementById('geminiLiveTranscript');
    const geminiLiveResponse = document.getElementById('geminiLiveResponse');
    const geminiLiveCardSlot = document.getElementById('geminiLiveCardSlot');
    const geminiLiveMicBtn = document.getElementById('geminiLiveMicBtn');
    const geminiLiveMicLabel = document.getElementById('geminiLiveMicLabel');
    const liveAttachCount = document.getElementById('liveAttachCount');
    const geminiAuroraOrb = document.getElementById('geminiAuroraOrb');

    // =========================================================================
    // 1. SPEECH RECOGNITION (STT) INITIALIZATION
    // =========================================================================
    function initSpeechRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            console.warn('Speech recognition not supported in this browser.');
            return null;
        }

        const recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.lang = 'en-US';

        recognition.onstart = function() {
            isListening = true;
            if (voiceMicBtn) voiceMicBtn.classList.add('listening');
            if (waveformBar && !isLiveActive) {
                waveformBar.style.display = 'flex';
                if (waveformStatus) waveformStatus.textContent = 'Listening to your voice... Speak now';
            }

            if (isLiveActive) {
                updateLiveStatus('Listening', '#38BDF8');
                if (geminiLiveMicBtn) geminiLiveMicBtn.classList.add('active');
                if (geminiLiveMicLabel) geminiLiveMicLabel.textContent = 'Listening';
            }
        };

        recognition.onresult = function(event) {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                transcript += event.results[i][0].transcript;
            }

            if (promptInput) {
                promptInput.value = transcript;
                updateSendButtonState();
            }

            // Live Mode real-time subtitles
            if (isLiveActive && geminiLiveTranscript) {
                geminiLiveTranscript.textContent = transcript || 'Listening...';
            }
        };

        recognition.onerror = function(event) {
            console.warn('Speech recognition error:', event.error);
            stopVoiceListening();
        };

        recognition.onend = function() {
            stopVoiceListening();

            const text = promptInput ? promptInput.value.trim() : '';
            if (text.length > 2) {
                if (isLiveActive) {
                    updateLiveStatus('Thinking...', '#A855F7');
                    if (geminiLiveTranscript) geminiLiveTranscript.textContent = `"${text}"`;
                }
                setTimeout(() => {
                    sendUserPrompt();
                }, 350);
            } else if (isLiveActive && !isSpeaking && !isLiveMicMuted) {
                // Auto restart listening in Gemini Live mode if idle
                setTimeout(() => {
                    if (isLiveActive && !isSpeaking && !isListening) {
                        tryStartRecognition();
                    }
                }, 500);
            }
        };

        return recognition;
    }

    speechRecognition = initSpeechRecognition();

    function tryStartRecognition() {
        if (!speechRecognition) speechRecognition = initSpeechRecognition();
        if (!speechRecognition) return;

        if (window.speechSynthesis) window.speechSynthesis.cancel();
        try {
            speechRecognition.start();
        } catch (e) {
            console.warn('Recognition start exception:', e);
        }
    }

    window.toggleVoiceListening = function() {
        if (isLiveActive) {
            toggleLiveMic();
            return;
        }

        if (isListening) {
            if (speechRecognition) speechRecognition.stop();
            stopVoiceListening();
        } else {
            playAudioCue('mic');
            tryStartRecognition();
        }
    };

    window.stopVoiceListening = function() {
        isListening = false;
        if (speechRecognition) {
            try { speechRecognition.stop(); } catch(e) {}
        }
        if (voiceMicBtn) voiceMicBtn.classList.remove('listening');
        if (waveformBar && !isLiveActive) waveformBar.style.display = 'none';
    };

    // =========================================================================
    // 2. SPEECH SYNTHESIS (VOICE OUTPUT)
    // =========================================================================
    function speakText(text) {
        if (isVoiceMuted || !text || !window.speechSynthesis) return;

        window.speechSynthesis.cancel(); // Stop prior audio
        const cleanText = text.replace(/[*_#`]/g, '').trim();
        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.rate = 1.05;
        utterance.pitch = 1.0;

        // Choose friendly natural voice if available
        const voices = window.speechSynthesis.getVoices();
        const preferredVoice = voices.find(v => v.lang.startsWith('en') && (v.name.includes('Google') || v.name.includes('Natural') || v.name.includes('Samantha') || v.name.includes('Daniel')));
        if (preferredVoice) utterance.voice = preferredVoice;

        utterance.onstart = function() {
            isSpeaking = true;
            if (waveformBar && !isLiveActive) {
                waveformBar.style.display = 'flex';
                if (waveformStatus) waveformStatus.textContent = 'Gemini Speaking...';
            }

            if (isLiveActive) {
                updateLiveStatus('Gemini Speaking...', '#10B981');
                if (geminiLiveResponse) {
                    geminiLiveResponse.style.display = 'block';
                    geminiLiveResponse.textContent = cleanText;
                }
                startSyntheticOrbWave();
            }
        };

        utterance.onend = function() {
            isSpeaking = false;
            stopSyntheticOrbWave();

            if (waveformBar && !isListening && !isLiveActive) waveformBar.style.display = 'none';

            if (isLiveActive) {
                updateLiveStatus('Listening', '#38BDF8');
                if (geminiLiveTranscript) geminiLiveTranscript.textContent = 'Speak naturally. Gemini is listening...';
                // Auto re-arm microphone in Gemini Live mode
                if (!isLiveMicMuted) {
                    setTimeout(() => {
                        if (isLiveActive && !isSpeaking) {
                            tryStartRecognition();
                        }
                    }, 400);
                }
            } else if (isHandsFree && !isListening) {
                // Auto re-arm for standard hands-free mode
                setTimeout(() => {
                    playAudioCue('mic');
                    tryStartRecognition();
                }, 600);
            }
        };

        utterance.onerror = function() {
            isSpeaking = false;
            stopSyntheticOrbWave();
            if (waveformBar && !isListening && !isLiveActive) waveformBar.style.display = 'none';
        };

        window.speechSynthesis.speak(utterance);
    }

    // =========================================================================
    // 3. GEMINI LIVE IMMERSIVE CONVERSATION CONTROLLER
    // =========================================================================
    window.startGeminiLive = function() {
        isLiveActive = true;
        isLiveMicMuted = false;

        if (geminiLiveOverlay) {
            geminiLiveOverlay.style.display = 'flex';
        }

        // Initialize Live Audio Reactivity via Web Audio API
        initLiveAudioAnalyser();

        // Play activation chime
        playAudioCue('live_start');

        // Start listening
        setTimeout(() => {
            tryStartRecognition();
        }, 300);
    };

    window.stopGeminiLive = function() {
        isLiveActive = false;
        if (geminiLiveOverlay) {
            geminiLiveOverlay.style.display = 'none';
        }

        // Stop microphone & audio analysis
        stopLiveAudioAnalyser();
        stopVoiceListening();

        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }

        playAudioCue('done');
    };

    window.toggleLiveMic = function() {
        if (!isLiveActive) return;

        isLiveMicMuted = !isLiveMicMuted;
        if (isLiveMicMuted) {
            stopVoiceListening();
            updateLiveStatus('Mic Muted', '#EF4444');
            if (geminiLiveMicBtn) geminiLiveMicBtn.classList.remove('active');
            if (geminiLiveMicLabel) geminiLiveMicLabel.textContent = 'Muted';
            if (geminiLiveTranscript) geminiLiveTranscript.textContent = 'Microphone paused. Tap to resume.';
        } else {
            updateLiveStatus('Listening', '#38BDF8');
            if (geminiLiveMicBtn) geminiLiveMicBtn.classList.add('active');
            if (geminiLiveMicLabel) geminiLiveMicLabel.textContent = 'Listening';
            if (geminiLiveTranscript) geminiLiveTranscript.textContent = 'Listening... Speak naturally.';
            playAudioCue('mic');
            tryStartRecognition();
        }
    };

    window.interruptGeminiVoice = function() {
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
        isSpeaking = false;
        stopSyntheticOrbWave();

        if (isLiveActive) {
            updateLiveStatus('Listening (Interrupted)', '#38BDF8');
            if (geminiLiveResponse) geminiLiveResponse.style.display = 'none';
            if (geminiLiveTranscript) geminiLiveTranscript.textContent = 'I am listening. Go ahead...';
            tryStartRecognition();
        }
    };

    window.interruptOrToggleLiveVoice = function() {
        if (isSpeaking) {
            interruptGeminiVoice();
        } else {
            toggleLiveMic();
        }
    };

    window.triggerImagePickerInLive = function() {
        if (fileInput) fileInput.click();
    };

    function updateLiveStatus(label, color) {
        if (geminiLiveStateLabel) geminiLiveStateLabel.textContent = label;
        const tag = document.getElementById('geminiLiveStatusTag');
        if (tag) tag.style.color = color;
    }

    // =========================================================================
    // 4. WEB AUDIO API DYNAMIC AURORA ORB REACTIVITY
    // =========================================================================
    function initLiveAudioAnalyser() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            audioContext = new AudioContext();
            analyserNode = audioContext.createAnalyser();
            analyserNode.fftSize = 64;

            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                    microphoneStream = stream;
                    const source = audioContext.createMediaStreamSource(stream);
                    source.connect(analyserNode);
                    startOrbAnimationLoop();
                }).catch(err => {
                    console.log('Mic stream direct analyser fallback:', err);
                    startSyntheticOrbLoop();
                });
            } else {
                startSyntheticOrbLoop();
            }
        } catch (e) {
            console.log('AudioContext init note:', e);
            startSyntheticOrbLoop();
        }
    }

    function stopLiveAudioAnalyser() {
        if (audioAnimFrame) cancelAnimationFrame(audioAnimFrame);
        if (microphoneStream) {
            microphoneStream.getTracks().forEach(track => track.stop());
            microphoneStream = null;
        }
        if (audioContext && audioContext.state !== 'closed') {
            audioContext.close();
            audioContext = null;
        }
    }

    function startOrbAnimationLoop() {
        if (!analyserNode || !geminiAuroraOrb) return;
        const bufferLength = analyserNode.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);

        function renderFrame() {
            if (!isLiveActive) return;
            audioAnimFrame = requestAnimationFrame(renderFrame);

            analyserNode.getByteFrequencyData(dataArray);
            let sum = 0;
            for (let i = 0; i < bufferLength; i++) {
                sum += dataArray[i];
            }
            const average = sum / bufferLength;
            const norm = Math.min(1, average / 80);

            if (geminiAuroraOrb && isListening) {
                const scale = 1 + norm * 0.35;
                const glow = 50 + norm * 70;
                geminiAuroraOrb.style.transform = `scale(${scale})`;
                geminiAuroraOrb.style.boxShadow = `0 0 ${glow}px rgba(0, 242, 254, 0.7), inset 0 0 40px rgba(127, 0, 255, 0.7)`;
            }
        }
        renderFrame();
    }

    let syntheticOrbTimer = null;
    function startSyntheticOrbWave() {
        if (geminiAuroraOrb) {
            geminiAuroraOrb.style.animationDuration = '1.8s';
            geminiAuroraOrb.style.transform = 'scale(1.15)';
            geminiAuroraOrb.style.boxShadow = '0 0 80px rgba(16, 185, 129, 0.8), inset 0 0 50px rgba(0, 242, 254, 0.7)';
        }
    }

    function stopSyntheticOrbWave() {
        if (geminiAuroraOrb) {
            geminiAuroraOrb.style.animationDuration = '6s';
            geminiAuroraOrb.style.transform = 'scale(1)';
            geminiAuroraOrb.style.boxShadow = '0 0 60px rgba(0, 242, 254, 0.5), inset 0 0 40px rgba(127, 0, 255, 0.6)';
        }
    }

    function startSyntheticOrbLoop() {
        // Fallback procedural breathing handled cleanly by CSS @keyframes geminiOrbMorph
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
            } else if (type === 'live_start') {
                // Cosmic ascending chord
                osc.frequency.setValueAtTime(440, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.35);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                osc.start();
                osc.stop(ctx.currentTime + 0.4);
            }
        } catch(e) {}
    }

    // =========================================================================
    // 5. MULTI-IMAGE DRAG & DROP & ATTACHMENT HANDLING
    // =========================================================================
    window.triggerImagePicker = function() {
        if (fileInput) fileInput.click();
    };

    window.handleImageSelection = function(input) {
        if (!input.files || input.files.length === 0) return;
        addFiles(Array.from(input.files));
        input.value = '';
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
                alert(`File "${file.name}" exceeds 8MB.`);
                continue;
            }
            selectedFiles.push(file);
        }
        renderImagePreviews();
        updateSendButtonState();
    }

    function renderImagePreviews() {
        if (!previewStrip || !dropzoneTray) return;

        if (selectedFiles.length === 0) {
            dropzoneTray.style.display = 'none';
            if (attachCountBadge) attachCountBadge.style.display = 'none';
            if (liveAttachCount) liveAttachCount.style.display = 'none';
            return;
        }

        dropzoneTray.style.display = 'block';
        if (attachCountBadge) {
            attachCountBadge.textContent = selectedFiles.length;
            attachCountBadge.style.display = 'inline-flex';
        }
        if (liveAttachCount) {
            liveAttachCount.textContent = selectedFiles.length;
            liveAttachCount.style.display = 'inline-flex';
        }
        if (trayFileCount) trayFileCount.textContent = selectedFiles.length;

        previewStrip.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const card = document.createElement('div');
            card.className = 'sn-ai-thumb-card';

            const reader = new FileReader();
            reader.onload = function(e) {
                card.innerHTML = `
                    <div class="sn-ai-thumb-box" style="position:relative;">
                        <img src="${e.target.result}" alt="${file.name}" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                        <button type="button" class="sn-ai-thumb-del" onclick="removeSelectedFile(${index})" style="position:absolute; top:2px; right:2px; width:18px; height:18px; border-radius:50%; background:rgba(0,0,0,0.7); color:#fff; border:none; cursor:pointer; line-height:1; font-size:12px;">&times;</button>
                        ${index === 0 ? '<span class="sn-ai-primary-tag" style="position:absolute; bottom:2px; left:2px; background:#1A73E8; color:#fff; font-size:8px; font-weight:800; padding:1px 4px; border-radius:3px;">PRIMARY</span>' : `<span class="sn-ai-gallery-tag" style="position:absolute; bottom:2px; left:2px; background:rgba(0,0,0,0.6); color:#fff; font-size:8px; padding:1px 4px; border-radius:3px;">#${index + 1}</span>`}
                    </div>
                `;
            };
            reader.readAsDataURL(file);
            previewStrip.appendChild(card);
        });
    }

    window.removeSelectedFile = function(index) {
        selectedFiles.splice(index, 1);
        renderImagePreviews();
        updateSendButtonState();
    };

    window.clearSelectedImages = function() {
        selectedFiles = [];
        renderImagePreviews();
        updateSendButtonState();
    };

    // =========================================================================
    // 6. PROMPT DISPATCH & BACKEND COMMUNICATION
    // =========================================================================
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

    function updateSendButtonState() {
        const hasText = promptInput && promptInput.value.trim().length > 0;
        const hasFiles = selectedFiles.length > 0;
        if (aiSendBtn) {
            if (hasText || hasFiles) {
                aiSendBtn.classList.add('active');
            } else {
                aiSendBtn.classList.remove('active');
            }
        }
    }

    if (promptInput) {
        promptInput.addEventListener('input', function() {
            // Auto grow height up to 120px
            this.style.height = 'auto';
            this.style.height = Math.min(120, this.scrollHeight) + 'px';
            updateSendButtonState();
        });
    }

    window.sendUserPrompt = function() {
        const text = promptInput ? promptInput.value.trim() : '';
        if (!text && selectedFiles.length === 0) {
            if (promptInput) promptInput.focus();
            return;
        }

        // Hide Gemini Hero on first interaction
        if (geminiHero) {
            geminiHero.style.display = 'none';
        }

        // Append User Message to Chat Viewport
        appendUserMessage(text, selectedFiles);

        // Prepare FormData
        const formData = new FormData();
        formData.append('prompt', text || 'Analyze these product images and add to inventory.');
        selectedFiles.forEach(file => {
            formData.append('images[]', file);
        });

        // Clear input and thumbnail preview
        const attachedCount = selectedFiles.length;
        selectedFiles = [];
        renderImagePreviews();
        if (promptInput) {
            promptInput.value = '';
            promptInput.style.height = 'auto';
        }
        updateSendButtonState();

        // Show Thinking Indicator
        const loadingMsgId = appendThinkingIndicator();

        // Update Live Status if in Live Mode
        if (isLiveActive) {
            updateLiveStatus('Executing action...', '#A855F7');
        }

        // Send AJAX Request to ai_admin_api.php
        fetch('ai_admin_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            removeThinkingIndicator(loadingMsgId);
            playAudioCue('done');

            if (data.status === 'success') {
                appendBotMessage(data.display_html);

                // Surface in Gemini Live card slot if in live mode
                if (isLiveActive && geminiLiveCardSlot) {
                    geminiLiveCardSlot.innerHTML = data.display_html;
                }

                if (data.voice_text) {
                    speakText(data.voice_text);
                }
            } else {
                const errHtml = `<div class="sn-ai-card"><p class="text-danger"><i class="fa fa-exclamation-triangle"></i> ${data.message || 'Error executing request.'}</p></div>`;
                appendBotMessage(errHtml);
                if (isLiveActive && geminiLiveCardSlot) {
                    geminiLiveCardSlot.innerHTML = errHtml;
                }
                if (data.voice_text) speakText(data.voice_text);
            }
        })
        .catch(err => {
            console.error('Gemini API Error:', err);
            removeThinkingIndicator(loadingMsgId);
            const connErr = `<div class="sn-ai-card"><p class="text-danger"><i class="fa fa-plug"></i> Failed to communicate with store assistant. Please check network connection.</p></div>`;
            appendBotMessage(connErr);
            if (isLiveActive && geminiLiveCardSlot) {
                geminiLiveCardSlot.innerHTML = connErr;
            }
            speakText("There was a connection issue communicating with the store assistant.");
        });
    };

    // =========================================================================
    // 7. CHAT RENDERING HELPERS
    // =========================================================================
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
                <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
                    <path d="M14 0C14 7.73199 7.73199 14 0 14C7.73199 14 14 20.268 14 28C14 20.268 20.268 14 28 14C20.268 14 14 7.73199 14 0Z" fill="url(#geminiThinkingGrad)" />
                    <defs>
                        <linearGradient id="geminiThinkingGrad" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#1A73E8"/>
                            <stop offset="0.5" stop-color="#9333EA"/>
                            <stop offset="1" stop-color="#FF5252"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <div class="sn-ai-bubble thinking-bubble">
                <div class="sn-ai-dots">
                    <span></span><span></span><span></span>
                </div>
                <small class="text-muted" style="margin-left:8px; font-weight:600;">Gemini analyzing & executing...</small>
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
                <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
                    <path d="M14 0C14 7.73199 7.73199 14 0 14C7.73199 14 14 20.268 14 28C14 20.268 20.268 14 28 14C20.268 14 14 7.73199 14 0Z" fill="url(#geminiMsgGrad)" />
                    <defs>
                        <linearGradient id="geminiMsgGrad" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#1A73E8"/>
                            <stop offset="0.5" stop-color="#9333EA"/>
                            <stop offset="1" stop-color="#FF5252"/>
                        </linearGradient>
                    </defs>
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
        if (!confirm('Start a new chat conversation?')) return;
        if (messagesWrap) {
            messagesWrap.innerHTML = '';
            if (geminiHero) {
                geminiHero.style.display = 'flex';
                messagesWrap.appendChild(geminiHero);
            }
        }
        if (geminiLiveCardSlot) {
            geminiLiveCardSlot.innerHTML = '';
        }
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // =========================================================================
    // 8. CONTROLS & SHORTCUTS
    // =========================================================================
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

    // Hotkey: Ctrl+Space launches Gemini Live directly!
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey && e.code === 'Space') || (e.altKey && e.key.toLowerCase() === 'a')) {
            e.preventDefault();
            if (isLiveActive) {
                stopGeminiLive();
            } else {
                startGeminiLive();
            }
        } else if (e.key === 'Escape' && isLiveActive) {
            stopGeminiLive();
        }
    });

})();
