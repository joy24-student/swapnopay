<?php
require_once __DIR__ . '/inc/guard.php';
$cur_page = 'ai-copilot.php';
require_once __DIR__ . '/header.php';

$admin_name = $_SESSION['user']['full_name'] ?? 'Admin';
// Extract first name for friendly Gemini greeting
$admin_first_name = explode(' ', trim($admin_name))[0] ?? 'Admin';
?>

<link rel="stylesheet" href="css/ai-copilot.css?v=<?php echo time(); ?>">

<div class="gemini-app-container">

    <!-- Gemini Top Navigation Bar -->
    <header class="gemini-topbar">
        <div class="gemini-topbar-left">
            <div class="gemini-brand">
                <svg class="gemini-sparkle-logo" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14 0C14 7.73199 7.73199 14 0 14C7.73199 14 14 20.268 14 28C14 20.268 20.268 14 28 14C20.268 14 14 7.73199 14 0Z" fill="url(#geminiGrad)" />
                    <defs>
                        <linearGradient id="geminiGrad" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#1A73E8"/>
                            <stop offset="0.35" stop-color="#8AB4F8"/>
                            <stop offset="0.7" stop-color="#9333EA"/>
                            <stop offset="1" stop-color="#FF5252"/>
                        </linearGradient>
                    </defs>
                </svg>
                <div class="gemini-brand-text">
                    <span class="gemini-title">Gemini Copilot</span>
                    <span class="gemini-model-badge">2.5 Flash Autonomous</span>
                </div>
            </div>
        </div>

        <div class="gemini-topbar-right">
            <!-- Launch Gemini Live Button -->
            <button type="button" class="gemini-live-pill-btn" onclick="startGeminiLive()" title="Start Real-time Gemini Live Voice Conversation">
                <div class="gemini-live-sparkle-icon">
                    <span class="gemini-live-dot"></span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/>
                    </svg>
                </div>
                <span class="gemini-live-btn-text">Gemini Live</span>
                <span class="gemini-live-indicator-ring"></span>
            </button>

            <!-- Hands-Free Toggle -->
            <button type="button" class="gemini-icon-btn" id="handsFreeBtn" onclick="toggleHandsFreeMode()" title="Toggle Hands-Free Continuous Voice Mode">
                <i class="fa fa-refresh" id="handsFreeIcon"></i>
                <span class="gemini-btn-hint" id="handsFreeText">Hands-Free: Off</span>
            </button>

            <!-- Voice Output Audio Mute -->
            <button type="button" class="gemini-icon-btn" id="toggleSpeechBtn" onclick="toggleSpeechSynthesis()" title="Toggle Voice Audio Speech Output">
                <i class="fa fa-volume-up" id="speechVolumeIcon"></i>
                <span class="gemini-btn-hint" id="speechVolumeText">Voice On</span>
            </button>

            <!-- Clear Conversation -->
            <button type="button" class="gemini-icon-btn" onclick="clearAiChat()" title="Start New Conversation">
                <i class="fa fa-pencil-square-o"></i>
                <span class="gemini-btn-hint">New Chat</span>
            </button>
        </div>
    </header>

    <!-- Main Gemini Conversation Canvas -->
    <main class="gemini-canvas">
        
        <!-- Live Audio Bar (Inline Voice Feedback) -->
        <div class="gemini-inline-wave-bar" id="aiWaveformBar" style="display:none;">
            <div class="gemini-wave-spectrum">
                <span class="gemini-wave-bar"></span>
                <span class="gemini-wave-bar"></span>
                <span class="gemini-wave-bar"></span>
                <span class="gemini-wave-bar"></span>
                <span class="gemini-wave-bar"></span>
            </div>
            <span id="aiWaveformStatus" class="gemini-wave-label">Listening live... Speak naturally</span>
            <button type="button" class="gemini-wave-stop-btn" onclick="stopVoiceListening()">Stop Mic</button>
        </div>

        <!-- Chat Stream & Gemini Welcoming Screen -->
        <div class="gemini-messages-viewport" id="aiMessagesWrap">

            <!-- Gemini Hero Welcoming Screen (Fades away once conversation starts) -->
            <div class="gemini-hero-container" id="geminiHero">
                <div class="gemini-hero-sparkle">
                    <svg width="48" height="48" viewBox="0 0 28 28" fill="none">
                        <path d="M14 0C14 7.73199 7.73199 14 0 14C7.73199 14 14 20.268 14 28C14 20.268 20.268 14 28 14C20.268 14 14 7.73199 14 0Z" fill="url(#geminiHeroGrad)" />
                        <defs>
                            <linearGradient id="geminiHeroGrad" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#4285F4"/>
                                <stop offset="0.3" stop-color="#9B72CF"/>
                                <stop offset="0.6" stop-color="#D96570"/>
                                <stop offset="1" stop-color="#1E88E5"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <h1 class="gemini-hero-title">
                    Hello, <span class="gemini-gradient-text"><?php echo htmlspecialchars($admin_first_name); ?></span>
                </h1>
                <h2 class="gemini-hero-subtitle">How can I help you manage ShopMart today?</h2>

                <!-- Gemini Prompt Suggestion Cards Grid -->
                <div class="gemini-cards-grid">
                    
                    <div class="gemini-card-item" onclick="triggerImagePicker()">
                        <div class="gemini-card-icon" style="background:#EEF2FF; color:#4F46E5;">
                            <i class="fa fa-camera"></i>
                        </div>
                        <div class="gemini-card-body">
                            <h3>Ingest 5 Product Images</h3>
                            <p>Upload up to 5 photos; AI writes SEO titles, specs, sets prices & adds to catalog.</p>
                        </div>
                        <span class="gemini-card-arrow">&rarr;</span>
                    </div>

                    <div class="gemini-card-item" onclick="sendAiPrompt('Run morning store audit on orders, fraud, stock and revenue')">
                        <div class="gemini-card-icon" style="background:#FEF3C7; color:#D97706;">
                            <i class="fa fa-rocket"></i>
                        </div>
                        <div class="gemini-card-body">
                            <h3>4-Stage Store Audit</h3>
                            <p>Autonomous morning routine checking pending orders, fraud shield & stock levels.</p>
                        </div>
                        <span class="gemini-card-arrow">&rarr;</span>
                    </div>

                    <div class="gemini-card-item" onclick="sendAiPrompt('Run global competitor pricing intelligence and SEO search keywords benchmark')">
                        <div class="gemini-card-icon" style="background:#E0F2FE; color:#0284C7;">
                            <i class="fa fa-globe"></i>
                        </div>
                        <div class="gemini-card-body">
                            <h3>Competitor Price Intel</h3>
                            <p>Compare benchmarks across Daraz, AliExpress, and Amazon with margin suggestions.</p>
                        </div>
                        <span class="gemini-card-arrow">&rarr;</span>
                    </div>

                    <div class="gemini-card-item" onclick="sendAiPrompt('Launch new flash sale campaign with coupon code and homepage banner')">
                        <div class="gemini-card-icon" style="background:#DCFCE7; color:#15803D;">
                            <i class="fa fa-tags"></i>
                        </div>
                        <div class="gemini-card-body">
                            <h3>Launch Flash Sale</h3>
                            <p>Generate 20% off discount coupon, dynamic slider banner graphic & auto-link storefront.</p>
                        </div>
                        <span class="gemini-card-arrow">&rarr;</span>
                    </div>

                </div>

                <!-- Secondary Quick Action Chips -->
                <div class="gemini-quick-chips">
                    <span class="gemini-chip" onclick="sendAiPrompt('Run autonomous fraud and risk detection scan on recent orders')"><i class="fa fa-shield text-danger"></i> Fraud Risk Scan</span>
                    <span class="gemini-chip" onclick="sendAiPrompt('Show all low stock and out of stock products in inventory')"><i class="fa fa-cubes text-warning"></i> Restock Low Inventory</span>
                    <span class="gemini-chip" onclick="sendAiPrompt('Generate executive business revenue and sales analytics report')"><i class="fa fa-line-chart text-success"></i> Revenue KPI Report</span>
                    <span class="gemini-chip" onclick="sendAiPrompt('Generate studio photo enhancement with verified badges')"><i class="fa fa-magic text-info"></i> Studio Photo Enhancer</span>
                </div>
            </div>

        </div>

        <!-- Floating Gemini Input Pill Bar -->
        <div class="gemini-dock-wrapper">
            
            <!-- Hidden Multi-file Picker -->
            <input type="file" id="aiImageInput" multiple accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleImageSelection(this)">

            <!-- Floating Input Capsule -->
            <div class="gemini-capsule-bar">

                <!-- Image Attachment Preview Strip (inside the capsule) -->
                <div class="gemini-capsule-attachments" id="aiDropzoneTray" style="display:none;">
                    <div class="gemini-attach-header">
                        <span><i class="fa fa-images"></i> <strong>Selected Photos for Vision Analysis</strong> (<span id="trayFileCount">0</span>/5)</span>
                        <button type="button" class="gemini-attach-clear" onclick="clearSelectedImages()">&times; Clear</button>
                    </div>
                    <div class="gemini-attach-strip" id="aiPreviewStrip"></div>
                </div>

                <div class="gemini-input-row">
                    <!-- Attach Photos (+) Button -->
                    <button type="button" class="gemini-tool-btn" onclick="triggerImagePicker()" title="Attach up to 5 Product Images">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span class="gemini-tool-badge" id="attachCountBadge" style="display:none;">0</span>
                    </button>

                    <!-- Textarea Input -->
                    <textarea id="aiPromptInput" class="gemini-textarea" rows="1" placeholder="Ask Gemini or speak to control your store..." onkeydown="handleInputKeydown(event)"></textarea>

                    <div class="gemini-actions-cluster">
                        <!-- Mic Button for standard STT -->
                        <button type="button" class="gemini-tool-btn gemini-mic-btn" id="voiceMicBtn" onclick="toggleVoiceListening()" title="Speak Command (Microphone)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                <line x1="12" y1="19" x2="12" y2="23"></line>
                                <line x1="8" y1="23" x2="16" y2="23"></line>
                            </svg>
                        </button>

                        <!-- Gemini Live Launch Button (inside the capsule) -->
                        <button type="button" class="gemini-tool-btn gemini-live-capsule-btn" onclick="startGeminiLive()" title="Enter Gemini Live Conversation Mode">
                            <span class="gemini-live-glow-dots">
                                <span></span><span></span><span></span>
                            </span>
                            <span class="gemini-live-tooltip">Live</span>
                        </button>

                        <!-- Send Button -->
                        <button type="button" class="gemini-send-btn" id="aiSendBtn" onclick="sendUserPrompt()" title="Send prompt">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                </div>

            </div>

            <!-- Footer micro-hints -->
            <div class="gemini-dock-footer">
                <span>Gemini can make errors. Verify critical financial data before finalizing.</span>
                <span class="gemini-dock-keyhints"><kbd>Enter</kbd> to Send • <kbd>Ctrl</kbd> + <kbd>Space</kbd> for Live Voice</span>
            </div>

        </div>

    </main>

</div>

<!-- ==============================================================================
     GEMINI LIVE IMMERSIVE CONVERSATION STUDIO (FULLSCREEN / COSMIC MODE)
     ============================================================================== -->
<div class="gemini-live-overlay" id="geminiLiveOverlay" style="display:none;">
    
    <!-- Gemini Live Ambient Glow Background -->
    <div class="gemini-live-backdrop">
        <div class="gemini-live-aurora-glow"></div>
    </div>

    <!-- Live Top Bar -->
    <div class="gemini-live-topbar">
        <div class="gemini-live-brand">
            <svg class="gemini-sparkle-logo" viewBox="0 0 28 28" fill="none" width="22" height="22">
                <path d="M14 0C14 7.73199 7.73199 14 0 14C7.73199 14 14 20.268 14 28C14 20.268 20.268 14 28 14C20.268 14 14 7.73199 14 0Z" fill="url(#geminiLiveLogoGrad)" />
                <defs>
                    <linearGradient id="geminiLiveLogoGrad" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#4FACFE"/>
                        <stop offset="0.5" stop-color="#00F2FE"/>
                        <stop offset="1" stop-color="#9333EA"/>
                    </linearGradient>
                </defs>
            </svg>
            <span class="gemini-live-title">Gemini Live</span>
            <span class="gemini-live-status-tag" id="geminiLiveStatusTag">
                <span class="gemini-live-dot-pulse"></span>
                <span id="geminiLiveStateLabel">Listening</span>
            </span>
        </div>

        <div class="gemini-live-actions">
            <!-- Minimize / Close -->
            <button type="button" class="gemini-live-close-btn" onclick="stopGeminiLive()" title="End Gemini Live Session">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- Center Stage: The Iconic Gemini Live Aurora Orb -->
    <div class="gemini-live-center-stage">
        
        <div class="gemini-orb-container" id="geminiOrbContainer" onclick="interruptOrToggleLiveVoice()">
            <!-- Animated Aurora Fluid Mesh Orb -->
            <div class="gemini-aurora-orb" id="geminiAuroraOrb">
                <div class="gemini-orb-core"></div>
                <div class="gemini-orb-halo"></div>
                <div class="gemini-orb-particles"></div>
            </div>

            <!-- Wave Equalizer Rings -->
            <div class="gemini-equalizer-rings">
                <span class="gemini-ring ring-1"></span>
                <span class="gemini-ring ring-2"></span>
                <span class="gemini-ring ring-3"></span>
            </div>
        </div>

        <!-- Live Subtitles & Captions -->
        <div class="gemini-live-captions-box">
            <p class="gemini-live-transcript" id="geminiLiveTranscript">Speak naturally. Gemini is listening...</p>
            <p class="gemini-live-response" id="geminiLiveResponse" style="display:none;"></p>
        </div>

        <!-- Floating Result Action Card in Live Mode -->
        <div class="gemini-live-card-container" id="geminiLiveCardSlot"></div>

    </div>

    <!-- Gemini Live Bottom Controls Bar -->
    <div class="gemini-live-controls-bar">
        
        <!-- Attach 5 Photos button -->
        <button type="button" class="gemini-live-ctl-btn" onclick="triggerImagePickerInLive()" title="Attach Product Photos">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                <circle cx="12" cy="13" r="4"></circle>
            </svg>
            <span class="gemini-live-badge-count" id="liveAttachCount" style="display:none;">0</span>
            <span class="gemini-ctl-label">Photos</span>
        </button>

        <!-- Interrupt / Pause Voice button -->
        <button type="button" class="gemini-live-ctl-btn" id="geminiLiveInterruptBtn" onclick="interruptGeminiVoice()" title="Interrupt Gemini Voice">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>
            </svg>
            <span class="gemini-ctl-label">Interrupt</span>
        </button>

        <!-- Main Mute / Unmute Mic -->
        <button type="button" class="gemini-live-ctl-btn gemini-ctl-mic active" id="geminiLiveMicBtn" onclick="toggleLiveMic()" title="Mute/Unmute Mic">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                <line x1="12" y1="19" x2="12" y2="23"></line>
                <line x1="8" y1="23" x2="16" y2="23"></line>
            </svg>
            <span class="gemini-ctl-label" id="geminiLiveMicLabel">Listening</span>
        </button>

        <!-- End Session Button (Red Hangup) -->
        <button type="button" class="gemini-live-ctl-btn gemini-ctl-end" onclick="stopGeminiLive()" title="End Live Conversation">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.47 2 2 6.47 2 12c0 2.21.72 4.25 1.93 5.91l-1.64 1.64c-.39.39-.39 1.02 0 1.41.39.39 1.02.39 1.41 0l1.64-1.64C7.05 20.53 9.39 21.3 12 21.3c5.53 0 10-4.47 10-10S17.53 2 12 2zm5 11H7v-2h10v2z"/>
            </svg>
            <span class="gemini-ctl-label">End Live</span>
        </button>

    </div>

</div>

<script src="js/ai-copilot-engine.js?v=<?php echo time(); ?>"></script>

<?php require_once __DIR__ . '/footer.php'; ?>
