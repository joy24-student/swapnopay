<?php
/**
 * SwapnoPay AI Product Studio Widget
 * Full AI-powered Product Content Generator for Add & Edit Product Pages
 */
?>

<!-- ================= AI PRODUCT CONTENT STUDIO STYLES ================= -->
<style>
.sn-ai-studio-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e0e7ff;
    box-shadow: 0 10px 30px -5px rgba(79, 70, 229, 0.08), 0 4px 6px -2px rgba(79, 70, 229, 0.04);
    margin-bottom: 25px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.sn-ai-studio-card:hover {
    box-shadow: 0 14px 35px -5px rgba(79, 70, 229, 0.12), 0 6px 10px -2px rgba(79, 70, 229, 0.06);
}
.sn-ai-studio-header {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
    color: #ffffff;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.sn-ai-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}
.sn-ai-icon-sparkle {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #f59e0b, #ec4899, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
}
.sn-ai-title {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.3px;
    color: #ffffff;
}
.sn-ai-subtitle {
    margin: 2px 0 0 0;
    font-size: 12px;
    color: #c7d2fe;
    font-weight: 400;
}
.sn-ai-badges {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.sn-ai-badge {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(4px);
}
.sn-ai-badge.highlight {
    background: #10b981;
    border-color: #059669;
    color: #ffffff;
}
.sn-ai-studio-body {
    padding: 20px;
    background: #fbfbfe;
}
.sn-ai-prompt-box {
    position: relative;
    margin-bottom: 15px;
}
.sn-ai-prompt-input {
    width: 100%;
    min-height: 90px;
    padding: 14px 16px;
    border-radius: 10px;
    border: 1.5px solid #cbd5e1;
    background: #ffffff;
    font-size: 14px;
    line-height: 1.5;
    color: #0f172a;
    transition: all 0.2s ease;
    resize: vertical;
    box-sizing: border-box;
}
.sn-ai-prompt-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
    background: #ffffff;
}
.sn-ai-quick-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}
.sn-ai-presets-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.sn-ai-chip {
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 16px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.15s ease;
    user-select: none;
}
.sn-ai-chip:hover {
    background: #4338ca;
    color: #ffffff;
    border-color: #4338ca;
}
.sn-ai-use-title-btn {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-size: 12px;
    padding: 5px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
}
.sn-ai-use-title-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
.sn-ai-controls-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 18px;
    background: #ffffff;
    padding: 14px 16px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.sn-ai-control-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}
.sn-ai-select {
    width: 100%;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    font-size: 13px;
    color: #1e293b;
    background: #ffffff;
    cursor: pointer;
}
.sn-ai-select:focus {
    outline: none;
    border-color: #6366f1;
}
.sn-ai-field-toggles {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 12px;
    color: #334155;
    margin-bottom: 18px;
    padding: 10px 14px;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.sn-ai-checkbox-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    margin: 0;
    font-weight: 500;
}
.sn-ai-checkbox-item input[type="checkbox"] {
    margin: 0;
    cursor: pointer;
}
.sn-ai-submit-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.sn-ai-btn-generate {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #8b5cf6 100%);
    color: #ffffff;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
    flex: 1;
    min-height: 46px;
}
.sn-ai-btn-generate:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
    background: linear-gradient(135deg, #4338ca 0%, #4f46e5 50%, #7c3aed 100%);
    color: #ffffff;
}
.sn-ai-btn-generate:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

/* Shimmering Loader */
.sn-ai-loading-box {
    display: none;
    margin-top: 20px;
    background: #ffffff;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e0e7ff;
    text-align: center;
}
.sn-ai-spinner {
    display: inline-block;
    width: 36px;
    height: 36px;
    border: 3px solid rgba(99, 102, 241, 0.2);
    border-radius: 50%;
    border-top-color: #6366f1;
    animation: snSpin 0.8s linear infinite;
    margin-bottom: 10px;
}
@keyframes snSpin {
    to { transform: rotate(360deg); }
}
.sn-ai-loading-text {
    font-size: 14px;
    font-weight: 600;
    color: #312e81;
    margin-bottom: 4px;
}
.sn-ai-loading-sub {
    font-size: 12px;
    color: #64748b;
}

/* Result Preview Drawer / Modal */
.sn-ai-results-box {
    display: none;
    margin-top: 20px;
    background: #ffffff;
    border-radius: 10px;
    border: 1.5px solid #10b981;
    box-shadow: 0 10px 25px rgba(16, 185, 129, 0.12);
    overflow: hidden;
}
.sn-ai-results-header {
    background: #ecfdf5;
    border-bottom: 1px solid #a7f3d0;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.sn-ai-results-title {
    font-size: 15px;
    font-weight: 700;
    color: #065f46;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sn-ai-results-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.sn-ai-btn-apply-all {
    background: #10b981;
    color: #ffffff;
    border: none;
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
}
.sn-ai-btn-apply-all:hover {
    background: #059669;
    color: #ffffff;
}
.sn-ai-preview-tabs {
    display: flex;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    overflow-x: auto;
}
.sn-ai-tab-btn {
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    border: none;
    background: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    white-space: nowrap;
}
.sn-ai-tab-btn.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
    background: #ffffff;
}
.sn-ai-tab-content {
    padding: 18px;
    max-height: 280px;
    overflow-y: auto;
    font-size: 13px;
    line-height: 1.6;
    color: #1e293b;
}
.sn-ai-tab-pane {
    display: none;
}
.sn-ai-tab-pane.active {
    display: block;
}

/* Inline Label Buttons */
.sn-ai-inline-btn-group {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: 8px;
    vertical-align: middle;
}
.sn-ai-inline-btn {
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s ease;
}
.sn-ai-inline-btn:hover {
    background: #4338ca;
    color: #ffffff;
    border-color: #4338ca;
}
.sn-ai-inline-btn.secondary {
    background: #f8fafc;
    color: #475569;
    border-color: #cbd5e1;
}
.sn-ai-inline-btn.secondary:hover {
    background: #334155;
    color: #ffffff;
}

/* Flash animation on applied editor */
@keyframes snFlashGreen {
    0% { outline: 3px solid rgba(16, 185, 129, 0.9); box-shadow: 0 0 20px rgba(16, 185, 129, 0.5); }
    100% { outline: 3px solid transparent; box-shadow: none; }
}
.sn-ai-flash-applied {
    animation: snFlashGreen 1.8s ease forwards;
}

/* Mini Single Field Modal */
.sn-ai-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    z-index: 999999;
    align-items: center;
    justify-content: center;
    padding: 16px;
    backdrop-filter: blur(2px);
}
.sn-ai-modal-dialog {
    background: #ffffff;
    border-radius: 12px;
    max-width: 520px;
    width: 100%;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    animation: snModalIn 0.2s ease-out;
}
@keyframes snModalIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.sn-ai-modal-header {
    background: #1e1b4b;
    color: #ffffff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.sn-ai-modal-header h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sn-ai-modal-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
}
.sn-ai-modal-close:hover {
    color: #ffffff;
}
.sn-ai-modal-body {
    padding: 18px;
}
.sn-ai-modal-footer {
    padding: 12px 18px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>

<!-- ================= HERO AI CONTENT STUDIO ================= -->
<div class="sn-ai-studio-card" id="sn_ai_studio">
    <div class="sn-ai-studio-header">
        <div class="sn-ai-title-wrap">
            <div class="sn-ai-icon-sparkle">
                <i class="fa fa-magic"></i>
            </div>
            <div>
                <h3 class="sn-ai-title">AI Product Content Studio</h3>
                <p class="sn-ai-subtitle">Write Full Description, Features, Conditions & Policies with Just One Prompt</p>
            </div>
        </div>
        <div class="sn-ai-badges">
            <span class="sn-ai-badge"><i class="fa fa-bolt"></i> Gemini & Multi-Model AI</span>
            <span class="sn-ai-badge highlight"><i class="fa fa-check-circle"></i> 1-Click Auto-Fill</span>
        </div>
    </div>

    <div class="sn-ai-studio-body">
        <!-- Prompt Box -->
        <div class="sn-ai-prompt-box">
            <textarea id="sn_ai_prompt" class="sn-ai-prompt-input" placeholder="Type your product prompt, specs, or raw details here... (e.g., 'Anker Soundcore Life Q30 Wireless ANC Headphones with 40h battery, fast USB-C charge, Hi-Res audio, multi-device connection, deep bass, foldable travel case')"></textarea>
        </div>

        <!-- Quick Action & Presets -->
        <div class="sn-ai-quick-actions">
            <div class="sn-ai-presets-wrap">
                <span style="font-size: 11px; font-weight: 700; color: #64748b; margin-right: 4px;">PRESETS:</span>
                <span class="sn-ai-chip" data-preset="electronics">🎧 Electronics</span>
                <span class="sn-ai-chip" data-preset="fashion">👗 Fashion</span>
                <span class="sn-ai-chip" data-preset="beauty">💄 Health & Beauty</span>
                <span class="sn-ai-chip" data-preset="gadget">📱 Smart Gadgets</span>
                <span class="sn-ai-chip" data-preset="home">🏠 Home & Kitchen</span>
            </div>

            <button type="button" id="sn_ai_use_title_btn" class="sn-ai-use-title-btn" title="Copy current Product Name into prompt">
                <i class="fa fa-lightbulb-o text-warning"></i> Use Product Name as Prompt
            </button>
        </div>

        <!-- Tone, Language & Options Grid -->
        <div class="sn-ai-controls-grid">
            <div class="sn-ai-control-group">
                <label for="sn_ai_tone"><i class="fa fa-paint-brush"></i> Tone of Voice</label>
                <select id="sn_ai_tone" class="sn-ai-select">
                    <option value="persuasive" selected>Persuasive & Engaging (High Sales)</option>
                    <option value="professional">Professional & Trustworthy</option>
                    <option value="technical">Technical & Spec-Heavy</option>
                    <option value="luxury">Luxury & Premium Appeal</option>
                    <option value="casual">Casual & Modern Shopper</option>
                </select>
            </div>

            <div class="sn-ai-control-group">
                <label for="sn_ai_lang"><i class="fa fa-globe"></i> Language</label>
                <select id="sn_ai_lang" class="sn-ai-select">
                    <option value="en" selected>English (Default)</option>
                    <option value="bn">বাংলা (Bengali)</option>
                    <option value="bilingual">Bilingual (English + বাংলা)</option>
                </select>
            </div>
        </div>

        <!-- Fields Selection Checklist -->
        <div class="sn-ai-field-toggles">
            <span style="font-weight: 700; color: #475569; margin-right: 6px;">Fields to Generate:</span>
            <label class="sn-ai-checkbox-item">
                <input type="checkbox" id="chk_field_desc" checked> Description
            </label>
            <label class="sn-ai-checkbox-item">
                <input type="checkbox" id="chk_field_short" checked> Short Description
            </label>
            <label class="sn-ai-checkbox-item">
                <input type="checkbox" id="chk_field_feat" checked> Features & Specs
            </label>
            <label class="sn-ai-checkbox-item">
                <input type="checkbox" id="chk_field_cond" checked> Conditions & Warranty
            </label>
            <label class="sn-ai-checkbox-item">
                <input type="checkbox" id="chk_field_ret" checked> Return Policy
            </label>
            <label class="sn-ai-checkbox-item">
                <input type="checkbox" id="chk_field_title"> Refine Product Name
            </label>
        </div>

        <!-- Submit Button Bar -->
        <div class="sn-ai-submit-bar">
            <button type="button" id="sn_ai_generate_all_btn" class="sn-ai-btn-generate">
                <i class="fa fa-magic"></i> Generate Everything with AI
            </button>
        </div>

        <!-- Loading Box -->
        <div class="sn-ai-loading-box" id="sn_ai_loader">
            <div class="sn-ai-spinner"></div>
            <div class="sn-ai-loading-text" id="sn_ai_loader_text">AI is crafting your product content...</div>
            <div class="sn-ai-loading-sub">Writing engaging descriptions, technical specifications, and return policies.</div>
        </div>

        <!-- Results Preview & 1-Click Apply Drawer -->
        <div class="sn-ai-results-box" id="sn_ai_results_box">
            <div class="sn-ai-results-header">
                <div class="sn-ai-results-title">
                    <i class="fa fa-check-circle text-success" style="font-size: 18px;"></i>
                    <span>Generated Content Ready! <small id="sn_ai_provider_tag" style="color: #047857; font-weight: 500;"></small></span>
                </div>
                <div class="sn-ai-results-actions">
                    <button type="button" id="sn_ai_apply_all_btn" class="sn-ai-btn-apply-all">
                        <i class="fa fa-bolt"></i> Apply All to Form Now
                    </button>
                    <button type="button" id="sn_ai_dismiss_preview_btn" class="btn btn-default btn-xs">
                        <i class="fa fa-times"></i> Close Preview
                    </button>
                </div>
            </div>

            <!-- Preview Tabs -->
            <div class="sn-ai-preview-tabs">
                <button type="button" class="sn-ai-tab-btn active" data-tab="desc">Description</button>
                <button type="button" class="sn-ai-tab-btn" data-tab="short">Short Desc</button>
                <button type="button" class="sn-ai-tab-btn" data-tab="feat">Features</button>
                <button type="button" class="sn-ai-tab-btn" data-tab="cond">Conditions</button>
                <button type="button" class="sn-ai-tab-btn" data-tab="ret">Return Policy</button>
                <button type="button" class="sn-ai-tab-btn" data-tab="title">Title</button>
            </div>

            <!-- Tab Panes -->
            <div class="sn-ai-tab-content">
                <div class="sn-ai-tab-pane active" id="tab_preview_desc"></div>
                <div class="sn-ai-tab-pane" id="tab_preview_short"></div>
                <div class="sn-ai-tab-pane" id="tab_preview_feat"></div>
                <div class="sn-ai-tab-pane" id="tab_preview_cond"></div>
                <div class="sn-ai-tab-pane" id="tab_preview_ret"></div>
                <div class="sn-ai-tab-pane" id="tab_preview_title"></div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MINI SINGLE FIELD MODAL ================= -->
<div class="sn-ai-modal-overlay" id="sn_ai_single_modal">
    <div class="sn-ai-modal-dialog">
        <div class="sn-ai-modal-header">
            <h4><i class="fa fa-magic"></i> <span id="sn_ai_modal_field_name">AI Field Writer</span></h4>
            <button type="button" class="sn-ai-modal-close" id="sn_ai_modal_close">&times;</button>
        </div>
        <div class="sn-ai-modal-body">
            <input type="hidden" id="sn_ai_modal_target_field" value="">
            <input type="hidden" id="sn_ai_modal_target_editor" value="">
            <input type="hidden" id="sn_ai_modal_action" value="generate_field">

            <div class="form-group" style="margin-bottom: 12px;">
                <label style="font-size: 12px; font-weight: 600; color: #475569;">Prompt / Instructions for this field:</label>
                <textarea id="sn_ai_modal_prompt" class="form-control" rows="3" placeholder="Enter instructions or leave blank to use product details..."></textarea>
            </div>

            <div class="row">
                <div class="col-xs-6">
                    <label style="font-size: 12px; font-weight: 600; color: #475569;">Tone:</label>
                    <select id="sn_ai_modal_tone" class="form-control input-sm">
                        <option value="persuasive">Persuasive</option>
                        <option value="professional">Professional</option>
                        <option value="technical">Technical</option>
                        <option value="luxury">Luxury</option>
                        <option value="casual">Casual</option>
                    </select>
                </div>
                <div class="col-xs-6">
                    <label style="font-size: 12px; font-weight: 600; color: #475569;">Language:</label>
                    <select id="sn_ai_modal_lang" class="form-control input-sm">
                        <option value="en">English</option>
                        <option value="bn">বাংলা (Bengali)</option>
                        <option value="bilingual">Bilingual</option>
                    </select>
                </div>
            </div>

            <div id="sn_ai_modal_loading" style="display:none; text-align:center; padding: 15px 0;">
                <div class="sn-ai-spinner" style="width:28px; height:28px;"></div>
                <div style="font-size: 13px; font-weight: 600; color: #4338ca;">Generating with AI...</div>
            </div>
        </div>
        <div class="sn-ai-modal-footer">
            <button type="button" class="btn btn-default btn-sm" id="sn_ai_modal_cancel">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="sn_ai_modal_submit" style="background:#4f46e5; border-color:#4338ca;">
                <i class="fa fa-magic"></i> Generate & Apply
            </button>
        </div>
    </div>
</div>

<!-- ================= AI PRODUCT WRITER JAVASCRIPT ================= -->
<script>
(function($) {
    'use strict';

    // Store generated results in memory
    var generatedCache = null;

    // Helper: Set content into Summernote or plain textarea
    function setEditorContent(editorId, htmlContent) {
        var $el = $('#' + editorId);
        if (!$el.length) return;

        // Try Summernote API
        if ($el.next('.note-editor').length && typeof $el.summernote === 'function') {
            $el.summernote('code', htmlContent);
        } else {
            $el.val(htmlContent);
        }
        $el.trigger('change');

        // Flash green animation on editor container
        var $container = $el.next('.note-editor').length ? $el.next('.note-editor') : $el;
        $container.removeClass('sn-ai-flash-applied');
        void $container[0].offsetWidth; // trigger reflow
        $container.addClass('sn-ai-flash-applied');
    }

    // Helper: Get content from Summernote or plain textarea
    function getEditorContent(editorId) {
        var $el = $('#' + editorId);
        if (!$el.length) return '';
        if ($el.next('.note-editor').length && typeof $el.summernote === 'function') {
            return $el.summernote('code');
        }
        return $el.val();
    }

    // Helper: Show Admin Toast Notification
    function showAiToast(type, message) {
        if (typeof window.showAdminToast === 'function') {
            window.showAdminToast(type, message);
            return;
        }
        // Fallback banner toast
        var $toast = $('<div style="position:fixed; top:20px; right:20px; z-index:9999999; background:#10b981; color:#fff; padding:12px 20px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.2); font-weight:600; font-size:14px; display:flex; align-items:center; gap:8px;">' +
            '<i class="fa fa-check-circle" style="font-size:18px;"></i> ' + message + '</div>');
        if (type === 'error') {
            $toast.css('background', '#ef4444').find('i').attr('class', 'fa fa-exclamation-triangle');
        }
        $('body').append($toast);
        setTimeout(function() {
            $toast.fadeOut(400, function() { $(this).remove(); });
        }, 3500);
    }

    $(document).ready(function() {
        // 1. Preset chips click handler
        $('.sn-ai-chip').on('click', function() {
            var preset = $(this).data('preset');
            var pName = $('input[name="p_name"]').val().trim();
            var text = '';

            switch(preset) {
                case 'electronics':
                    text = (pName ? pName + ' - ' : '') + 'High-performance smart electronic gadget with fast charging, long-lasting battery, premium build quality, modern connectivity, and official warranty.';
                    break;
                case 'fashion':
                    text = (pName ? pName + ' - ' : '') + 'Trendy premium apparel crafted from 100% breathable organic cotton, stylish modern fit, durable reinforced stitching, suitable for everyday casual and party wear.';
                    break;
                case 'beauty':
                    text = (pName ? pName + ' - ' : '') + 'Dermatologically tested organic skincare and beauty formula with natural active ingredients, deep hydration, visible glow, safe for all skin types.';
                    break;
                case 'gadget':
                    text = (pName ? pName + ' - ' : '') + 'Sleek next-generation smart gadget with ultra-responsive sensors, wireless connectivity, ergonomic lightweight design, and plug-and-play simplicity.';
                    break;
                case 'home':
                    text = (pName ? pName + ' - ' : '') + 'Essential modern home and kitchen accessory engineered for maximum durability, space-saving compact design, easy maintenance, and long service life.';
                    break;
            }

            var current = $('#sn_ai_prompt').val().trim();
            $('#sn_ai_prompt').val(text).focus();
        });

        // 2. "Use Product Name as Prompt" button
        $('#sn_ai_use_title_btn').on('click', function() {
            var pName = $('input[name="p_name"]').val().trim();
            if (!pName) {
                alert('Please enter a Product Name first in the form below, or type your prompt directly here.');
                $('input[name="p_name"]').focus();
                return;
            }
            $('#sn_ai_prompt').val(pName + ' - High quality original product with full features, durable build, and complete specifications.').focus();
        });

        // 3. Tab navigation in preview drawer
        $('.sn-ai-tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            $('.sn-ai-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.sn-ai-tab-pane').removeClass('active');
            $('#tab_preview_' + tab).addClass('active');
        });

        $('#sn_ai_dismiss_preview_btn').on('click', function() {
            $('#sn_ai_results_box').slideUp(250);
        });

        // 4. Main "Generate Everything with AI" Button
        $('#sn_ai_generate_all_btn').on('click', function() {
            var prompt = $('#sn_ai_prompt').val().trim();
            var pName = $('input[name="p_name"]').val().trim();

            if (!prompt && !pName) {
                alert('Please enter a product prompt or product name to let AI generate your content.');
                $('#sn_ai_prompt').focus();
                return;
            }

            var tone = $('#sn_ai_tone').val();
            var lang = $('#sn_ai_lang').val();
            var catName = $('.end-cat option:selected').text() || $('.mid-cat option:selected').text() || $('.top-cat option:selected').text() || '';

            var $btn = $(this);
            $btn.prop('disabled', true);
            $('#sn_ai_results_box').hide();
            $('#sn_ai_loader').slideDown(200);

            // Animated status text steps
            var steps = [
                'Analyzing product details...',
                'Drafting high-converting description...',
                'Formatting technical specifications...',
                'Generating warranty & return policies...',
                'Finalizing rich HTML content...'
            ];
            var stepIdx = 0;
            var stepTimer = setInterval(function() {
                stepIdx = (stepIdx + 1) % steps.length;
                $('#sn_ai_loader_text').text(steps[stepIdx]);
            }, 1800);

            $.ajax({
                url: 'ai-product-writer.php',
                type: 'POST',
                data: {
                    action: 'generate_all',
                    prompt: prompt,
                    product_name: pName,
                    category_name: catName,
                    tone: tone,
                    language: lang
                },
                dataType: 'json',
                success: function(res) {
                    clearInterval(stepTimer);
                    $btn.prop('disabled', false);
                    $('#sn_ai_loader').hide();

                    if (res && res.status === 'success' && res.data) {
                        generatedCache = res.data;
                        $('#sn_ai_provider_tag').text('(' + (res.provider || 'AI') + ')');

                        // Fill preview panes
                        $('#tab_preview_desc').html(res.data.description || '<em>None</em>');
                        $('#tab_preview_short').html(res.data.short_description || '<em>None</em>');
                        $('#tab_preview_feat').html(res.data.feature || '<em>None</em>');
                        $('#tab_preview_cond').html(res.data.condition || '<em>None</em>');
                        $('#tab_preview_ret').html(res.data.return_policy || '<em>None</em>');
                        $('#tab_preview_title').html('<strong>' + (res.data.name || pName) + '</strong>');

                        $('#sn_ai_results_box').slideDown(300);

                        // Auto-scroll to results preview
                        $('html, body').animate({
                            scrollTop: $('#sn_ai_results_box').offset().top - 80
                        }, 400);

                        showAiToast('success', 'AI generated all product sections! Click "Apply All to Form" below.');
                    } else {
                        var errMsg = (res && res.message) ? res.message : 'Unknown error generating AI content.';
                        showAiToast('error', errMsg);
                    }
                },
                error: function(xhr, status, error) {
                    clearInterval(stepTimer);
                    $btn.prop('disabled', false);
                    $('#sn_ai_loader').hide();
                    showAiToast('error', 'Request failed: ' + (error || 'Network error'));
                }
            });
        });

        // 5. "Apply All to Form" Button
        $('#sn_ai_apply_all_btn').on('click', function() {
            if (!generatedCache) {
                alert('No generated content found. Please click Generate first.');
                return;
            }

            var appliedCount = 0;

            // Description -> editor1
            if ($('#chk_field_desc').is(':checked') && generatedCache.description) {
                setEditorContent('editor1', generatedCache.description);
                appliedCount++;
            }

            // Short Description -> editor2
            if ($('#chk_field_short').is(':checked') && generatedCache.short_description) {
                setEditorContent('editor2', generatedCache.short_description);
                appliedCount++;
            }

            // Features -> editor3
            if ($('#chk_field_feat').is(':checked') && generatedCache.feature) {
                setEditorContent('editor3', generatedCache.feature);
                appliedCount++;
            }

            // Conditions -> editor4
            if ($('#chk_field_cond').is(':checked') && generatedCache.condition) {
                setEditorContent('editor4', generatedCache.condition);
                appliedCount++;
            }

            // Return Policy -> editor5
            if ($('#chk_field_ret').is(':checked') && generatedCache.return_policy) {
                setEditorContent('editor5', generatedCache.return_policy);
                appliedCount++;
            }

            // Product Name
            if ($('#chk_field_title').is(':checked') && generatedCache.name) {
                $('input[name="p_name"]').val(generatedCache.name).trigger('change');
                $('input[name="p_name"]').addClass('sn-ai-flash-applied');
                appliedCount++;
            }

            showAiToast('success', '🎉 Applied ' + appliedCount + ' sections to product form successfully!');

            // Scroll down to the editors so the user sees the filled content
            $('html, body').animate({
                scrollTop: $('#editor1').closest('.form-group').offset().top - 100
            }, 500);
        });

        // 6. Inline Field Buttons Click Handler
        $(document).on('click', '.sn-inline-ai-btn', function(e) {
            e.preventDefault();
            var field = $(this).data('field');
            var editorId = $(this).data('editor');
            var action = $(this).data('action') || 'generate_field';

            var fieldLabels = {
                'name': 'Product Title',
                'description': 'Product Description',
                'short_description': 'Short Description',
                'feature': 'Features & Specifications',
                'condition': 'Conditions & Warranty',
                'return_policy': 'Return Policy'
            };

            var currentVal = editorId ? getEditorContent(editorId) : $('input[name="p_name"]').val();
            var masterPrompt = $('#sn_ai_prompt').val().trim() || $('input[name="p_name"]').val().trim();

            $('#sn_ai_modal_target_field').val(field);
            $('#sn_ai_modal_target_editor').val(editorId || '');
            $('#sn_ai_modal_action').val(action);
            $('#sn_ai_modal_field_name').text((action === 'polish' ? 'Polish & Enhance: ' : 'AI Write: ') + (fieldLabels[field] || field));

            // Prefill modal prompt
            if (action === 'polish') {
                $('#sn_ai_modal_prompt').val(currentVal ? $('<div>').html(currentVal).text().substring(0, 300) : masterPrompt);
                $('#sn_ai_modal_submit').html('<i class="fa fa-magic"></i> Polish & Update');
            } else {
                $('#sn_ai_modal_prompt').val(masterPrompt);
                $('#sn_ai_modal_submit').html('<i class="fa fa-magic"></i> Generate & Insert');
            }

            $('#sn_ai_modal_loading').hide();
            $('#sn_ai_modal_submit').prop('disabled', false);
            $('#sn_ai_single_modal').css('display', 'flex');
        });

        // Modal close handlers
        $('#sn_ai_modal_close, #sn_ai_modal_cancel').on('click', function() {
            $('#sn_ai_single_modal').hide();
        });

        $('#sn_ai_single_modal').on('click', function(e) {
            if ($(e.target).is('#sn_ai_single_modal')) {
                $(this).hide();
            }
        });

        // Modal Submit Handler
        $('#sn_ai_modal_submit').on('click', function() {
            var field = $('#sn_ai_modal_target_field').val();
            var editorId = $('#sn_ai_modal_target_editor').val();
            var action = $('#sn_ai_modal_action').val();
            var prompt = $('#sn_ai_modal_prompt').val().trim();
            var tone = $('#sn_ai_modal_tone').val();
            var lang = $('#sn_ai_modal_lang').val();
            var pName = $('input[name="p_name"]').val().trim();

            var currentContent = editorId ? getEditorContent(editorId) : '';

            var $btn = $(this);
            $btn.prop('disabled', true);
            $('#sn_ai_modal_loading').show();

            $.ajax({
                url: 'ai-product-writer.php',
                type: 'POST',
                data: {
                    action: action,
                    target_field: field,
                    prompt: prompt || pName,
                    product_name: pName,
                    current_content: currentContent,
                    tone: tone,
                    language: lang
                },
                dataType: 'json',
                success: function(res) {
                    $btn.prop('disabled', false);
                    $('#sn_ai_modal_loading').hide();

                    if (res && res.status === 'success' && res.data) {
                        var content = res.data[field];
                        if (content) {
                            if (editorId) {
                                setEditorContent(editorId, content);
                            } else if (field === 'name') {
                                $('input[name="p_name"]').val(content).trigger('change');
                            }
                            $('#sn_ai_single_modal').hide();
                            showAiToast('success', 'Field updated with AI successfully!');
                        } else {
                            showAiToast('error', 'Could not extract content for this field.');
                        }
                    } else {
                        showAiToast('error', (res && res.message) ? res.message : 'Error generating content.');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false);
                    $('#sn_ai_modal_loading').hide();
                    showAiToast('error', 'Request failed: ' + error);
                }
            });
        });

    });
})(jQuery);
</script>

