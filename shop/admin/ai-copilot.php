<?php
require_once __DIR__ . '/inc/guard.php';
$cur_page = 'ai-copilot.php';
require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="css/ai-copilot.css?v=<?php echo time(); ?>">

<section class="content-header sn-ai-content-header">
    <div class="sn-ai-header-left">
        <h1 class="sn-ai-main-title">
            <span class="sn-ai-title-sparkle">✨</span> AI Autonomous Operations & Voice Copilot
            <span class="sn-ai-badge-pro">PRO AUTONOMOUS</span>
        </h1>
        <p class="sn-ai-subtitle">Speak or type commands to create products from 5 images, control inventory, monitor fraud, generate banners, and analyze store operations.</p>
    </div>
    <div class="sn-ai-header-right">
        <div class="sn-ai-status-pill">
            <span class="sn-ai-dot-live"></span>
            <span id="aiEngineStatus">Multimodal Vision & Voice Active</span>
        </div>
        <button type="button" class="btn btn-default btn-sm sn-ai-btn-handsfree" id="handsFreeBtn" onclick="toggleHandsFreeMode()" title="Toggle Hands-Free Continuous Voice Conversation">
            <i class="fa fa-refresh" id="handsFreeIcon"></i> <span id="handsFreeText">Hands-Free: Off</span>
        </button>
        <button type="button" class="btn btn-default btn-sm sn-ai-btn-mute" id="toggleSpeechBtn" onclick="toggleSpeechSynthesis()" title="Toggle Voice Responses">
            <i class="fa fa-volume-up" id="speechVolumeIcon"></i> <span id="speechVolumeText">Voice On</span>
        </button>
        <button type="button" class="btn btn-default btn-sm" onclick="clearAiChat()" title="Clear Conversation">
            <i class="fa fa-trash-o"></i> Clear
        </button>
    </div>
</section>

<section class="content sn-ai-workspace">

    <!-- Quick Action Prompts Bar -->
    <div class="sn-ai-chips-bar">
        <span class="sn-ai-chips-label"><i class="fa fa-bolt text-yellow"></i> Autonomous Workflows:</span>
        <button type="button" class="sn-ai-chip highlight" onclick="sendAiPrompt('Run morning store audit on orders, fraud, stock and revenue')"><i class="fa fa-rocket text-primary"></i> 4-Stage Store Audit</button>
        <button type="button" class="sn-ai-chip" onclick="triggerImagePicker()"><i class="fa fa-camera text-yellow"></i> Ingest 5 Product Images</button>
        <button type="button" class="sn-ai-chip" onclick="sendAiPrompt('Run global competitor pricing intelligence and SEO search keywords benchmark')"><i class="fa fa-globe text-info"></i> Competitor Price Intel</button>
        <button type="button" class="sn-ai-chip" onclick="sendAiPrompt('Launch new flash sale campaign with coupon code and homepage banner')"><i class="fa fa-tags text-success"></i> Launch Campaign (Coupon + Banner)</button>
        <button type="button" class="sn-ai-chip" onclick="sendAiPrompt('Generate studio photo enhancement with verified badges')"><i class="fa fa-magic text-warning"></i> Studio Photo Enhancer</button>
        <button type="button" class="sn-ai-chip" onclick="sendAiPrompt('Run autonomous fraud and risk detection scan on recent orders')"><i class="fa fa-shield text-danger"></i> Fraud Risk Scan</button>
        <button type="button" class="sn-ai-chip" onclick="sendAiPrompt('Generate executive business revenue and sales analytics report')"><i class="fa fa-line-chart text-success"></i> Revenue & Sales KPI</button>
        <button type="button" class="sn-ai-chip" onclick="sendAiPrompt('Show all low stock and out of stock products in inventory')"><i class="fa fa-cubes text-danger"></i> Restock Low Inventory</button>
    </div>

    <!-- Multi-Image Dropzone Tray (Hidden until files selected or triggered) -->
    <div class="sn-ai-dropzone-tray" id="aiDropzoneTray" style="display:none;">
        <div class="sn-ai-tray-header">
            <span><i class="fa fa-images"></i> <strong>Selected Product Images for AI Vision Analysis</strong> (<span id="trayFileCount">0</span>/5)</span>
            <button type="button" class="btn btn-xs btn-default" onclick="clearSelectedImages()"><i class="fa fa-times"></i> Cancel</button>
        </div>
        <div class="sn-ai-preview-strip" id="aiPreviewStrip"></div>
        <div class="sn-ai-tray-footer">
            <span class="text-muted"><i class="fa fa-info-circle"></i> AI will automatically write titles, descriptions, assign category, calculate optimal pricing, and save into inventory.</span>
            <button type="button" class="btn btn-warning btn-sm" onclick="submitImagesWithPrompt()"><i class="fa fa-magic"></i> Analyze & Add to Inventory Now</button>
        </div>
    </div>

    <!-- Main Chat Workspace Card -->
    <div class="sn-ai-chat-card">
        
        <!-- Live Audio Waveform (Visible during Speech Recognition or Speech Output) -->
        <div class="sn-ai-waveform-bar" id="aiWaveformBar" style="display:none;">
            <div class="sn-ai-wave-dot"></div>
            <div class="sn-ai-wave-col"><span class="sn-ai-bar"></span><span class="sn-ai-bar"></span><span class="sn-ai-bar"></span><span class="sn-ai-bar"></span></div>
            <span id="aiWaveformStatus" class="sn-ai-wave-text">Listening to your voice... Speak now</span>
            <button type="button" class="btn btn-xs btn-danger" onclick="stopVoiceListening()">Stop Mic</button>
        </div>

        <!-- Chat Stream Messages -->
        <div class="sn-ai-messages-wrap" id="aiMessagesWrap">
            <!-- Welcome Bot Message -->
            <div class="sn-ai-msg sn-ai-msg-bot">
                <div class="sn-ai-avatar">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="2.3">
                        <path d="M12 2l2.4 7.4 7.6 2.6-7.6 2.6L12 22l-2.4-7.4L2 12l7.6-2.6L12 2z"/>
                    </svg>
                </div>
                <div class="sn-ai-bubble">
                    <div class="sn-ai-greeting">
                        <h4>Hello <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'Admin'); ?>, I am your ShopMart Autonomous AI Copilot!</h4>
                        <p>I have direct control over your store's database, inventory, fraud systems, categories, and settings. You can speak to me or type instructions in plain language.</p>
                        <div class="sn-ai-capabilities-grid">
                            <div class="sn-ai-cap-box">
                                <i class="fa fa-camera text-yellow"></i>
                                <strong>5-Image Vision Ingestion</strong>
                                <span>Drop up to 5 photos & say <em>"Add in inventory"</em>. I'll inspect them, write descriptions, price, and upload all photos automatically.</span>
                            </div>
                            <div class="sn-ai-cap-box">
                                <i class="fa fa-microphone text-primary"></i>
                                <strong>Voice Operations</strong>
                                <span>Press the microphone and speak naturally. I listen and speak back answers with live audio feedback.</span>
                            </div>
                            <div class="sn-ai-cap-box">
                                <i class="fa fa-shield text-danger"></i>
                                <strong>Fraud Detection</strong>
                                <span>Say <em>"Run fraud scan"</em> to detect velocity spikes, fake emails, or risky orders before shipping.</span>
                            </div>
                            <div class="sn-ai-cap-box">
                                <i class="fa fa-line-chart text-success"></i>
                                <strong>Sales & Executive Reports</strong>
                                <span>Instant revenue breakdown, top customer analysis, and automated low-stock warnings.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interactive Bottom Input Dock -->
        <div class="sn-ai-input-dock">
            
            <!-- Hidden Multi-file Picker -->
            <input type="file" id="aiImageInput" multiple accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleImageSelection(this)">

            <div class="sn-ai-input-bar">
                <!-- Attach Images Button -->
                <button type="button" class="sn-ai-btn-icon" onclick="triggerImagePicker()" title="Attach up to 5 Product Images">
                    <i class="fa fa-paperclip"></i>
                    <span class="sn-ai-attach-badge" id="attachCountBadge" style="display:none;">0</span>
                </button>

                <!-- Voice Mic Button -->
                <button type="button" class="sn-ai-btn-mic" id="voiceMicBtn" onclick="toggleVoiceListening()" title="Speak Command (Microphone)">
                    <i class="fa fa-microphone" id="micIcon"></i>
                    <span class="sn-ai-mic-pulse"></span>
                </button>

                <!-- Input Textarea -->
                <textarea id="aiPromptInput" class="sn-ai-textarea" rows="1" placeholder="Speak or type a command... (e.g. 'Add product from these images', 'Show low stock', 'Run fraud scan')" onkeydown="handleInputKeydown(event)"></textarea>

                <!-- Send Button -->
                <button type="button" class="sn-ai-btn-send" id="aiSendBtn" onclick="sendUserPrompt()" title="Execute Command">
                    <i class="fa fa-paper-plane"></i>
                </button>
            </div>
            
            <div class="sn-ai-dock-hints">
                <span><kbd>Enter</kbd> to Send</span>
                <span><kbd>Ctrl</kbd> + <kbd>Space</kbd> for Voice</span>
                <span id="voiceStatusHint" class="text-muted"><i class="fa fa-microphone"></i> Click microphone to speak</span>
            </div>
        </div>

    </div>

</section>

<!-- Audio notification effects -->
<audio id="sndBeepStart" src="../assets/audio/beep_start.mp3" preload="auto"></audio>
<audio id="sndBeepDone" src="../assets/audio/beep_done.mp3" preload="auto"></audio>

<script src="js/ai-copilot-engine.js?v=<?php echo time(); ?>"></script>

<?php require_once __DIR__ . '/footer.php'; ?>
