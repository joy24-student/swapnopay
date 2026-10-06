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
    // 5. CLIENT-SIDE IMAGE COMPRESSION & MULTI-IMAGE ATTACHMENT ENGINE
    // =========================================================================
    window.triggerImagePicker = function() {
        if (fileInput) fileInput.click();
    };

    window.handleImageSelection = function(input) {
        if (!input.files || input.files.length === 0) return;
        addFiles(Array.from(input.files));
        input.value = '';
    };

    function formatBytes(bytes, decimals = 1) {
        if (!bytes || bytes <= 0) return '0 B';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    /**
     * High-Performance Client-Side Image Compression via HTML5 Canvas
     * Auto-resizes to e-commerce resolution (max 1600px), converts to WebP, and strips EXIF
     */
    function compressImageFile(file, maxDimension = 1600, quality = 0.82) {
        return new Promise((resolve) => {
            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                resolve({ file: file, originalSize: file.size, compressedSize: file.size, savedPct: 0 });
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    let width = img.width;
                    let height = img.height;

                    if (width > maxDimension || height > maxDimension) {
                        if (width > height) {
                            height = Math.round((height * maxDimension) / width);
                            width = maxDimension;
                        } else {
                            width = Math.round((width * maxDimension) / height);
                            height = maxDimension;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(function(blob) {
                        if (!blob || blob.size >= file.size) {
                            // If compression didn't reduce size (already tiny), keep original
                            resolve({
                                file: file,
                                originalSize: file.size,
                                compressedSize: file.size,
                                savedPct: 0,
                                dimensions: `${img.width}×${img.height}`,
                                dataUrl: e.target.result
                            });
                            return;
                        }

                        const compName = file.name.replace(/\.[^/.]+$/, "") + ".webp";
                        const compressedFile = new File([blob], compName, {
                            type: 'image/webp',
                            lastModified: Date.now()
                        });

                        const origSize = file.size;
                        const compSize = compressedFile.size;
                        const savedPct = Math.max(0, Math.round(((origSize - compSize) / origSize) * 100));

                        resolve({
                            file: compressedFile,
                            originalSize: origSize,
                            compressedSize: compSize,
                            savedPct: savedPct,
                            dimensions: `${width}×${height}`,
                            dataUrl: canvas.toDataURL('image/webp', quality)
                        });
                    }, 'image/webp', quality);
                };
                img.onerror = () => resolve({ file: file, originalSize: file.size, compressedSize: file.size, savedPct: 0, dataUrl: e.target.result });
                img.src = e.target.result;
            };
            reader.onerror = () => resolve({ file: file, originalSize: file.size, compressedSize: file.size, savedPct: 0 });
            reader.readAsDataURL(file);
        });
    }

    async function addFiles(files) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        
        // Show non-blocking indicator in tray
        if (dropzoneTray) {
            dropzoneTray.style.display = 'block';
            if (previewStrip) previewStrip.innerHTML = '<div style="padding:10px; font-size:12px; color:#1A73E8;"><i class="fa fa-spinner fa-spin"></i> Compressing & optimizing photos for instant upload...</div>';
        }

        for (const file of files) {
            if (selectedFiles.length >= 5) {
                alert('You can attach a maximum of 5 product images at a time.');
                break;
            }
            if (!allowedTypes.includes(file.type)) {
                alert(`File "${file.name}" is not a supported image format.`);
                continue;
            }

            // Run client-side compression
            const result = await compressImageFile(file, 1600, 0.82);
            result.file._compressionMeta = result; // attach meta
            selectedFiles.push(result.file);
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

        // Calculate total compression stats
        let totalOrig = 0;
        let totalComp = 0;
        selectedFiles.forEach(f => {
            const m = f._compressionMeta;
            if (m) {
                totalOrig += m.originalSize || f.size;
                totalComp += m.compressedSize || f.size;
            } else {
                totalOrig += f.size;
                totalComp += f.size;
            }
        });

        const totalSavedPct = totalOrig > 0 ? Math.max(0, Math.round(((totalOrig - totalComp) / totalOrig) * 100)) : 0;
        const statsHtml = totalSavedPct > 0 ? `<span style="color:#059669; font-weight:700; margin-left:8px;"><i class="fa fa-bolt"></i> Auto-Compressed: ${formatBytes(totalOrig)} → ${formatBytes(totalComp)} (-${totalSavedPct}%)</span>` : '';

        const attachHeader = dropzoneTray.querySelector('.gemini-attach-header');
        if (attachHeader) {
            attachHeader.innerHTML = `
                <span><i class="fa fa-images"></i> <strong>Selected Photos for Vision Analysis</strong> (${selectedFiles.length}/5) ${statsHtml}</span>
                <button type="button" class="gemini-attach-clear" onclick="clearSelectedImages()">&times; Clear</button>
            `;
        }

        previewStrip.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const card = document.createElement('div');
            card.className = 'sn-ai-thumb-card';

            const m = file._compressionMeta;
            const badgeText = (m && m.savedPct > 0) ? `-${m.savedPct}%` : 'OPTIMIZED';

            const reader = new FileReader();
            reader.onload = function(e) {
                card.innerHTML = `
                    <div class="sn-ai-thumb-box" style="position:relative;">
                        <img src="${e.target.result}" alt="${file.name}" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                        <button type="button" class="sn-ai-thumb-del" onclick="removeSelectedFile(${index})" style="position:absolute; top:2px; right:2px; width:18px; height:18px; border-radius:50%; background:rgba(0,0,0,0.7); color:#fff; border:none; cursor:pointer; line-height:1; font-size:12px;">&times;</button>
                        ${index === 0 ? '<span class="sn-ai-primary-tag" style="position:absolute; bottom:2px; left:2px; background:#1A73E8; color:#fff; font-size:8px; font-weight:800; padding:1px 4px; border-radius:3px;">PRIMARY</span>' : `<span class="sn-ai-gallery-tag" style="position:absolute; bottom:2px; left:2px; background:rgba(0,0,0,0.6); color:#fff; font-size:8px; padding:1px 4px; border-radius:3px;">#${index + 1}</span>`}
                        <span class="sn-ai-compress-tag" title="Original: ${formatBytes(m ? m.originalSize : file.size)} → Compressed: ${formatBytes(file.size)}" style="position:absolute; top:2px; left:2px; background:#10B981; color:#fff; font-size:8px; font-weight:800; padding:1px 4px; border-radius:3px;">${badgeText}</span>
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
            if (compressModal && compressModal.style.display !== 'none') {
                closeImageCompressorModal();
            } else {
                stopGeminiLive();
            }
        }
    });

    // =========================================================================
    // 8. DEDICATED IMAGE UPLOAD & COMPRESS ENGINE STUDIO
    // =========================================================================
    let compressEngineQueue = [];
    const compressModal = document.getElementById('geminiCompressModal');
    const compDropzone = document.getElementById('compressEngineDropzone');
    const compFileInput = document.getElementById('compressEngineFileInput');
    const compResultsGrid = document.getElementById('compressResultsGrid');
    const compSummaryBar = document.getElementById('compressEngineSummary');
    const btnDownloadAll = document.getElementById('btnDownloadAllComp');
    const btnFeedAi = document.getElementById('btnFeedToAiCatalog');

    window.openImageCompressorModal = function() {
        if (compressModal) {
            compressModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeImageCompressorModal = function() {
        if (compressModal) {
            compressModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // Drag and Drop listeners
    if (compDropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            compDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                compDropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            compDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                compDropzone.classList.remove('dragover');
            }, false);
        });

        compDropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                handleCompressEngineFiles(dt.files);
            }
        });
    }

    function getPresetConfig() {
        const preset = document.getElementById('compEnginePreset') ? document.getElementById('compEnginePreset').value : 'store';
        if (preset === 'ultra') {
            return { maxDimension: 1200, quality: 0.65, label: 'Ultra Shrink (65%)' };
        } else if (preset === 'hd') {
            return { maxDimension: 2048, quality: 0.90, label: 'High Detail (90%)' };
        }
        return { maxDimension: 1600, quality: 0.82, label: 'Storefront Optimal (82%)' };
    }

    window.recalculateCompressionPreset = async function() {
        if (!compressEngineQueue.length) return;
        const config = getPresetConfig();
        const rawFiles = compressEngineQueue.map(item => item.originalFile);
        compressEngineQueue = [];
        await processCompressFiles(rawFiles, config);
    };

    window.handleCompressEngineFiles = async function(files) {
        if (!files || !files.length) return;
        const config = getPresetConfig();
        await processCompressFiles(Array.from(files), config);
    };

    async function processCompressFiles(files, config) {
        if (compDropzone) {
            compDropzone.innerHTML = `
                <div class="gemini-compress-drop-icon"><i class="fa fa-spinner fa-spin"></i></div>
                <h3>Optimizing & Compressing ${files.length} Photo(s)...</h3>
                <p>Generating high-efficiency WebP files in browser</p>
            `;
        }

        for (const file of files) {
            if (!file.type.startsWith('image/')) continue;
            try {
                const compResult = await compressImageFile(file, config.maxDimension, config.quality);
                compResult.file._compressionMeta = compResult;
                compressEngineQueue.push({
                    originalFile: file,
                    compressedFile: compResult.file,
                    originalSize: compResult.originalSize,
                    compressedSize: compResult.compressedSize,
                    savedPct: compResult.savedPct,
                    dimensions: compResult.dimensions,
                    dataUrl: compResult.dataUrl,
                    name: file.name
                });
            } catch (err) {
                console.error('Compression error on file:', file.name, err);
            }
        }

        if (compDropzone) {
            compDropzone.innerHTML = `
                <input type="file" id="compressEngineFileInput" multiple accept="image/*" style="display:none;" onchange="handleCompressEngineFiles(this.files)">
                <div class="gemini-compress-drop-icon"><i class="fa fa-cloud-upload"></i></div>
                <h3>Drag & Drop More Photos, or <span style="color:#2563EB; text-decoration:underline;">Browse Files</span></h3>
                <p>Supports JPG, PNG, WEBP &bull; Auto-rotates phone photos</p>
            `;
        }

        renderCompressEngineResults();
    }

    function renderCompressEngineResults() {
        if (!compResultsGrid || !compSummaryBar) return;

        if (compressEngineQueue.length === 0) {
            compResultsGrid.innerHTML = '';
            compSummaryBar.style.display = 'none';
            if (btnDownloadAll) btnDownloadAll.style.display = 'none';
            if (btnFeedAi) btnFeedAi.style.display = 'none';
            return;
        }

        compSummaryBar.style.display = 'grid';
        if (btnDownloadAll) btnDownloadAll.style.display = 'inline-flex';
        if (btnFeedAi) btnFeedAi.style.display = 'inline-flex';

        let totalOrig = 0;
        let totalComp = 0;
        compressEngineQueue.forEach(item => {
            totalOrig += item.originalSize;
            totalComp += item.compressedSize;
        });

        const totalSaved = totalOrig > 0 ? Math.max(0, Math.round(((totalOrig - totalComp) / totalOrig) * 100)) : 0;

        const countEl = document.getElementById('compStatCount');
        const origEl = document.getElementById('compStatOrig');
        const compEl = document.getElementById('compStatComp');
        const savedEl = document.getElementById('compStatSaved');

        if (countEl) countEl.textContent = compressEngineQueue.length;
        if (origEl) origEl.textContent = formatBytes(totalOrig);
        if (compEl) compEl.textContent = formatBytes(totalComp);
        if (savedEl) savedEl.textContent = `-${totalSaved}% (${formatBytes(totalOrig - totalComp)})`;

        compResultsGrid.innerHTML = '';
        compressEngineQueue.forEach((item, idx) => {
            const card = document.createElement('div');
            card.className = 'gemini-comp-result-card';
            card.innerHTML = `
                <div class="gemini-comp-thumb-wrap">
                    <img src="${item.dataUrl}" alt="${item.name}">
                    <span class="gemini-comp-badge">-${item.savedPct}%</span>
                </div>
                <div class="gemini-comp-meta">
                    <span class="gemini-comp-filename" title="${item.name}">${item.name}</span>
                    <div class="gemini-comp-sizes">
                        <span>${formatBytes(item.originalSize)} &rarr; <strong style="color:#059669;">${formatBytes(item.compressedSize)}</strong></span>
                        <span style="font-size:10px; color:#94A3B8;">${item.dimensions}</span>
                    </div>
                </div>
                <div class="gemini-comp-actions">
                    <button type="button" class="gemini-comp-btn primary" onclick="downloadSingleCompressed(${idx})" title="Download optimized WebP">
                        <i class="fa fa-download"></i> Save WebP
                    </button>
                    <button type="button" class="gemini-comp-btn" onclick="removeCompressEngineItem(${idx})" title="Remove item" style="flex:0 0 28px; padding:0; color:#EF4444;">
                        &times;
                    </button>
                </div>
            `;
            compResultsGrid.appendChild(card);
        });
    }

    window.downloadSingleCompressed = function(index) {
        const item = compressEngineQueue[index];
        if (!item) return;
        const link = document.createElement('a');
        link.href = item.dataUrl;
        const baseName = item.name.replace(/\.[^/.]+$/, "");
        link.download = `${baseName}-optimized.webp`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    window.downloadAllCompressedFiles = function() {
        if (!compressEngineQueue.length) return;
        compressEngineQueue.forEach((item, index) => {
            setTimeout(() => {
                downloadSingleCompressed(index);
            }, index * 250);
        });
    };

    window.removeCompressEngineItem = function(index) {
        compressEngineQueue.splice(index, 1);
        renderCompressEngineResults();
    };

    window.clearCompressEngineList = function() {
        compressEngineQueue = [];
        renderCompressEngineResults();
    };

    window.sendCompressedToAiCatalog = function() {
        if (!compressEngineQueue.length) return;
        const top5 = compressEngineQueue.slice(0, 5);
        selectedFiles = top5.map(item => item.compressedFile);
        renderImagePreviews();
        updateSendButtonState();
        closeImageCompressorModal();

        if (promptInput) {
            promptInput.value = "Analyze these compressed product photos, write SEO titles, specs, price and add to inventory catalog";
            updateSendButtonState();
            promptInput.focus();
        }
    };

})();
