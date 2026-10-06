<?php
require_once('Parsedown.php');
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// More detailed error logging
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/php_errors.log');

// Debug function
function log_debug($message) {
    $log_file = dirname(__FILE__) . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

log_debug("=== AI Chat Pool Script Started ===");

// 1. CONFIGURATION & DATABASE CONNECTION
$config_path = dirname(__FILE__) . '/admin/inc/config.php';
if (!file_exists($config_path)) {
    log_debug("ERROR: Config file not found at: {$config_path}");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Configuration file missing']);
    exit();
}

require_once($config_path);

// 2. INPUT VALIDATION
if (!isset($_POST['prompt']) || empty(trim($_POST['prompt']))) {
    http_response_code(400);
    $error_message = "Error: Missing prompt.";
    log_debug($error_message);
    echo json_encode(['status' => 'error', 'message' => $error_message]);
    exit();
}

$prompt = trim($_POST['prompt']);
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

log_debug("Processing - Prompt: '{$prompt}', Product ID: {$product_id}");

// 3. FETCH AI POOL SETTINGS FROM DATABASE
try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection not established");
    }

    $statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id = 1");
    $statement->execute();
    $settings = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        throw new Exception("No settings found in database");
    }
} catch (Exception $e) {
    http_response_code(500);
    $error_message = "Database error: " . $e->getMessage();
    log_debug($error_message);
    echo json_encode(['status' => 'error', 'message' => $error_message]);
    exit();
}

$raw_gemini_keys = $settings['gemini_api_key'] ?? '';
$raw_openrouter_keys = $settings['openrouter_api_key'] ?? '';
$ai_provider = strtolower(trim($settings['ai_provider'] ?? 'auto'));
$ai_pool_strategy = strtolower(trim($settings['ai_pool_strategy'] ?? 'round_robin'));
$preferred_openrouter_model = trim($settings['openrouter_model'] ?? 'openrouter/free');

/**
 * Parse comma/newline/semicolon separated API keys into a deduplicated array
 */
function parse_api_key_pool($raw) {
    if (empty($raw)) return [];
    $parts = preg_split('/[\r\n,;]+/', (string)$raw);
    $clean = [];
    foreach ($parts as $part) {
        $trimmed = trim($part);
        if ($trimmed !== '' && !in_array($trimmed, $clean, true)) {
            $clean[] = $trimmed;
        }
    }
    return $clean;
}

$gemini_keys = parse_api_key_pool($raw_gemini_keys);
$openrouter_keys = parse_api_key_pool($raw_openrouter_keys);

// 4. KEY POOL COORDINATOR (ROUND-ROBIN, RANDOM, FAILOVER + COOLDOWN HEALTH TRACKING)
function get_pool_state_path() {
    $tenant_key = (defined('MERCHANT_ID') && MERCHANT_ID) ? MERCHANT_ID : __DIR__;
    return sys_get_temp_dir() . '/sp_ai_pool_' . md5((string)$tenant_key) . '.json';
}

function load_pool_state() {
    $file = get_pool_state_path();
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return [
        'cursors' => ['gemini' => 0, 'openrouter' => 0],
        'cooldowns' => [],
        'stats' => []
    ];
}

function save_pool_state($state) {
    $file = get_pool_state_path();
    @file_put_contents($file, json_encode($state), LOCK_EX);
}

/**
 * Order keys according to the configured pooling strategy (round_robin, failover, random)
 * while placing healthy (non-cooled-down) keys ahead of cooled-down keys.
 */
function get_ordered_pool_keys($provider, $keys, $strategy, &$state) {
    $count = count($keys);
    if ($count === 0) return [];

    $now = time();
    $indices = range(0, $count - 1);

    if ($strategy === 'random') {
        shuffle($indices);
    } elseif ($strategy === 'round_robin') {
        $cursor = isset($state['cursors'][$provider]) ? intval($state['cursors'][$provider]) : 0;
        $start = $cursor % $count;
        $state['cursors'][$provider] = ($cursor + 1) % $count;
        $rotated = [];
        for ($i = 0; $i < $count; $i++) {
            $rotated[] = ($start + $i) % $count;
        }
        $indices = $rotated;
    }
    // For 'failover', $indices stays [0, 1, 2, ...]

    $healthy = [];
    $cooling = [];
    foreach ($indices as $idx) {
        $key = $keys[$idx];
        $key_id = $provider . ':' . substr( hash('sha256', $key), 0, 16 );
        $cooldown_until = intval($state['cooldowns'][$key_id] ?? 0);
        if ($cooldown_until > $now) {
            $cooling[] = ['key' => $key, 'idx' => $idx, 'key_id' => $key_id, 'cooldown_until' => $cooldown_until];
        } else {
            $healthy[] = ['key' => $key, 'idx' => $idx, 'key_id' => $key_id, 'cooldown_until' => 0];
        }
    }

    // Sort cooled-down keys by earliest expiry in case all keys are on cooldown
    usort($cooling, function($a, $b) {
        return $a['cooldown_until'] <=> $b['cooldown_until'];
    });

    return array_merge($healthy, $cooling);
}

function mark_key_success(&$state, $key_id) {
    unset($state['cooldowns'][$key_id]);
    $state['stats'][$key_id] = [
        'last_success' => time(),
        'status' => 'healthy'
    ];
}

function mark_key_failure(&$state, $key_id, $http_status) {
    // 429 Rate Limit -> 60s cooldown; 400/401/402/403 Auth/Quota -> 300s cooldown; 5xx -> 20s cooldown
    $cooldown_seconds = 30;
    if ($http_status === 429) {
        $cooldown_seconds = 60;
    } elseif (in_array($http_status, [400, 401, 402, 403], true)) {
        $cooldown_seconds = 300;
    } elseif ($http_status >= 500) {
        $cooldown_seconds = 20;
    }
    $state['cooldowns'][$key_id] = time() + $cooldown_seconds;
    $state['stats'][$key_id] = [
        'last_error_code' => $http_status,
        'last_error_at' => time(),
        'status' => 'cooldown'
    ];
}

// 5. FETCH PRODUCT DETAILS (IF SPECIFIC PRODUCT) OR GENERAL STORE CONTEXT
$product_details = null;
$store_products_summary = '';
if ($product_id > 0) {
    try {
        $statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id = ? AND p_is_active = 1");
        $statement->execute([$product_id]);
        $product_details = $statement->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        exit();
    }
} else {
    // Fetch top active products for general store inquiries
    try {
        $stmt_top = $pdo->prepare("SELECT p_name, p_current_price, p_old_price, p_qty FROM tbl_product WHERE p_is_active = 1 ORDER BY p_total_view DESC, p_id DESC LIMIT 8");
        $stmt_top->execute();
        $top_rows = $stmt_top->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $lines = [];
        foreach ($top_rows as $r) {
            $lines[] = "- {$r['p_name']}: ৳ " . number_format((float)$r['p_current_price']) . " (Stock: {$r['p_qty']})";
        }
        $store_products_summary = implode("\n", $lines);
    } catch (Throwable $e) {
        $store_products_summary = '';
    }
}

// Helper for local smart fallback response when no keys are configured or all keys fail
function render_smart_fallback($prompt, $product_details) {
    $promptLower = strtolower($prompt);
    if (!$product_details) {
        if (str_contains($promptLower, 'deal') || str_contains($promptLower, 'discount') || str_contains($promptLower, 'sale')) {
            $ai_markdown = "### ⚡ Today's Top Deals & Offers!\n\n" .
                           "- **Payday Sale:** Extra 15% OFF on your first order + up to 80% off site-wide!\n" .
                           "- **Apple AirPods Pro:** Now ৳ 27,999 (was ৳ 32,999, Save 15%)\n" .
                           "- **Samsung Galaxy Watch 6:** Now ৳ 26,999 (was ৳ 29,999, Save 12%)\n" .
                           "- **Free Delivery:** Available on all orders over ৳ 2,000!\n\n" .
                           "Which category are you shopping for today?";
        } elseif (str_contains($promptLower, 'laptop') || str_contains($promptLower, 'tech') || str_contains($promptLower, 'phone')) {
            $ai_markdown = "### 💻 Top Tech & Gadget Picks\n\n" .
                           "- **HP Pavilion 15:** 12th Gen i5, 8GB RAM, 512GB SSD for ৳ 68,000.\n" .
                           "- **ASUS ROG Strix G15:** Ryzen 7 Gaming powerhouse for ৳ 1,12,999.\n" .
                           "- **iPhone 15 (128GB):** Premium flagship for ৳ 89,999.\n\n" .
                           "Would you like recommendations for everyday work, college, or high-performance gaming?";
        } elseif (str_contains($promptLower, 'shipping') || str_contains($promptLower, 'deliver') || str_contains($promptLower, 'return')) {
            $ai_markdown = "### 🚚 Shipping & Return Policy\n\n" .
                           "- **Free Shipping:** Nationwide on all orders over ৳ 2,000.\n" .
                           "- **Delivery Time:** 2-3 business days inside Dhaka, 3-5 days nationwide.\n" .
                           "- **7 Days Return:** Hassle-free easy return policy if you're not completely satisfied!\n\n" .
                           "Need help finding products or tracking orders?";
        } else {
            $currStore = defined('STORE_NAME') ? STORE_NAME : 'Our Store';
            $ai_markdown = "### 🛍️ Welcome to {$currStore} AI Assistant!\n\n" .
                           "I'm here to help you discover the best prices, compare gadgets, find deals, and answer questions!\n\n" .
                           "- Tap or ask: *\"What are today's top deals?\"*\n" .
                           "- Tap or ask: *\"Recommend the best laptop under ৳ 70k\"*\n" .
                           "- Tap or ask: *\"Tell me about free shipping and returns\"*";
        }
    } else {
        $pName = $product_details['p_name'];
        $pPrice = number_format((float)$product_details['p_current_price']);
        $pQty = (int)$product_details['p_qty'];

        if (str_contains($promptLower, 'summar') || str_contains($promptLower, '30-sec') || str_contains($promptLower, 'overview')) {
            $ai_markdown = "### ⚡ 30-Second Summary of {$pName}\n\n" .
                           "- **Power & Speed:** Features high-efficiency performance designed for seamless daily use.\n" .
                           "- **Reliable Quality:** Crafted with durable materials and verified quality assurance.\n" .
                           "- **Peace of Mind:** Backed by Official Warranty & 7 Days Easy Return Policy.\n\n" .
                           "💡 *Available right now for **৳ {$pPrice}** ({$pQty} units in stock)!*";
        } elseif (str_contains($promptLower, 'battery') || str_contains($promptLower, 'portab') || str_contains($promptLower, 'weight')) {
            $ai_markdown = "### 🔋 Portability & Everyday Convenience\n\n" .
                           "- **Built for Daily Use:** Engineered for long-lasting reliability and convenience.\n" .
                           "- **Travel Friendly:** Ergonomic and lightweight design makes it easy to carry anywhere.";
        } elseif (str_contains($promptLower, 'study') || str_contains($promptLower, 'work') || str_contains($promptLower, 'college') || str_contains($promptLower, 'program')) {
            $ai_markdown = "### 💻 Great for Work, Study & Daily Tasks\n\n" .
                           "- **Dependable Performance:** Handles everyday productivity and multitasking smoothly.\n" .
                           "- **Great Value:** Priced competitively at **৳ {$pPrice}** with fast delivery.";
        } elseif (str_contains($promptLower, 'box') || str_contains($promptLower, 'package') || str_contains($promptLower, 'warranty')) {
            $ai_markdown = "### 📦 Package Contents & Warranty\n\n" .
                           "- **1x** {$pName} (Brand New & Original)\n" .
                           "- **Standard Accessories & User Guide**\n" .
                           "- **Official Warranty** with 7 Days Easy Return support.";
        } else {
            $ai_markdown = "### 💡 Recommendation for {$pName}\n\n" .
                           "The **{$pName}** is one of our top-rated items, currently priced at **৳ {$pPrice}** with **{$pQty} units available** in stock!\n\n" .
                           "- **Quality Assured:** 100% authentic product with quality check.\n" .
                           "- **Fast Delivery:** 2-3 business days inside Dhaka, 3-5 days nationwide.\n" .
                           "- **Service:** Covered by Official Warranty and 7 Days Easy Return.";
        }
    }

    $Parsedown = new Parsedown();
    return $Parsedown->text($ai_markdown);
}

// If neither Gemini nor OpenRouter keys are configured, use smart fallback
if (empty($gemini_keys) && empty($openrouter_keys)) {
    echo json_encode([
        'status' => 'success',
        'provider' => 'local_fallback',
        'response' => render_smart_fallback($prompt, $product_details)
    ]);
    exit();
}

// 6. PREPARE THE PROMPT FOR AI
if ($product_details) {
    $ai_prompt_text = "You are an expert sales consultant for our online store. Your goal is to provide a smart, well-structured, and highly convincing response to a customer query.\n\n" .
        "### INSTRUCTIONS:\n" .
        "1. **Direct Answer First:** Start by answering the User's Query directly and concisely in a friendly, humanlike tone.\n" .
        "2. **Smart Structure:** Use bold headings and bullet points to make the information easy to read. Use emojis naturally and ask a helpful follow-up question.\n" .
        "3. **Persuasive Tone:** Highlight the benefits of the product and create a sense of value. Mention that we have stock available if the count is high.\n" .
        "4. **Factual:** Use only the product details provided below. Do not invent features.\n\n" .
        "### PRODUCT DATA:\n" .
        "- **Name:** {$product_details['p_name']}\n" .
        "- **Current Price:** ৳ {$product_details['p_current_price']}\n" .
        "- **Stock Status:** {$product_details['p_qty']} units available\n" .
        "- **Product Details:** " . strip_tags((string)($product_details['p_description'] ?? '')) . "\n\n" .
        "### CUSTOMER INQUIRY:\n" .
        "\"{$prompt}\"\n\n" .
        "### YOUR PROFESSIONAL RESPONSE:";
} else {
    $catalog_block = $store_products_summary !== '' ? "\n### FEATURED CATALOG ITEMS:\n{$store_products_summary}\n" : "";
    $ai_prompt_text = "You are a warm, helpful, and persuasive AI Shopping Assistant for our online store.\n" .
        "Answer the customer's question concisely using markdown headings, bullet points, and emojis.\n" .
        "Store Policies: Free shipping on orders over ৳ 2,000; 2-3 days delivery in Dhaka (3-5 days nationwide); 7-day easy returns.\n" .
        $catalog_block . "\n" .
        "### CUSTOMER INQUIRY:\n\"{$prompt}\"\n\n### YOUR HELPFUL RESPONSE:";
}

// 7. GEMINI & OPENROUTER MULTI-KEY POOL EXECUTORS
function call_gemini_key_pool($gemini_keys, $prompt_text, $strategy, &$pool_state) {
    if (empty($gemini_keys)) return null;

    $models = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
        'gemini-1.5-flash'
    ];

    $ordered_keys = get_ordered_pool_keys('gemini', $gemini_keys, $strategy, $pool_state);

    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt_text]
                ]
            ]
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_ONLY_HIGH'
            ]
        ]
    ]);

    foreach ($ordered_keys as $entry) {
        $api_key = $entry['key'];
        $key_id = $entry['key_id'];
        $key_num = $entry['idx'] + 1;

        foreach ($models as $model_id) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model_id . ":generateContent?key=" . urlencode($api_key);
            log_debug("Trying Gemini Key #{$key_num} with model {$model_id}");

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response_body = curl_exec($ch);
            $http_status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_errno($ch) ? curl_error($ch) : '';
            curl_close($ch);

            if ($curl_err !== '') {
                log_debug("Gemini Key #{$key_num} cURL Error: {$curl_err}");
                mark_key_failure($pool_state, $key_id, 503);
                break; // Try next key in pool
            }

            $result = json_decode((string)$response_body, true);
            if ($http_status >= 200 && $http_status < 300 && !empty($result['candidates'][0]['content']['parts'][0]['text'])) {
                mark_key_success($pool_state, $key_id);
                return [
                    'text' => $result['candidates'][0]['content']['parts'][0]['text'],
                    'provider' => 'gemini',
                    'model' => $model_id,
                    'key_index' => $key_num
                ];
            }

            $err_msg = $result['error']['message'] ?? "HTTP {$http_status}";
            log_debug("Gemini Key #{$key_num} ({$model_id}) failed [{$http_status}]: {$err_msg}");

            // If key is rate-limited (429) or invalid/unauthorized (400/401/403), cooldown this key and rotate to next key immediately
            if (in_array($http_status, [429, 400, 401, 402, 403], true)) {
                mark_key_failure($pool_state, $key_id, $http_status);
                break; // Move to next Gemini key in the pool
            }
        }
    }

    return null;
}

function call_openrouter_key_pool($openrouter_keys, $prompt_text, $preferred_model, $strategy, &$pool_state) {
    if (empty($openrouter_keys)) return null;

    $fallback_models = [
        'openrouter/free',
        'google/gemini-2.5-flash',
        'google/gemini-2.0-flash-001',
        'meta-llama/llama-3.3-70b-instruct:free',
        'meta-llama/llama-3.1-8b-instruct:free',
        'qwen/qwen-2.5-72b-instruct:free',
        'deepseek/deepseek-chat:free',
        'microsoft/phi-3-medium-128k-instruct:free'
    ];

    $models_to_try = [];
    if (!empty($preferred_model)) {
        $models_to_try[] = $preferred_model;
    }
    foreach ($fallback_models as $fm) {
        if (!in_array($fm, $models_to_try, true)) {
            $models_to_try[] = $fm;
        }
    }

    $ordered_keys = get_ordered_pool_keys('openrouter', $openrouter_keys, $strategy, $pool_state);
    $referer = defined('BASE_URL') ? BASE_URL : 'https://swapnopay.top';

    foreach ($ordered_keys as $entry) {
        $api_key = $entry['key'];
        $key_id = $entry['key_id'];
        $key_num = $entry['idx'] + 1;

        foreach ($models_to_try as $model_id) {
            log_debug("Trying OpenRouter Key #{$key_num} with model {$model_id}");

            $payload = json_encode([
                'model' => $model_id,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt_text]
                ],
                'temperature' => 0.35
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://openrouter.ai/api/v1/chat/completions',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $api_key,
                    'Content-Type: application/json',
                    'HTTP-Referer: ' . $referer,
                    'X-Title: ' . (defined('STORE_NAME') ? STORE_NAME : 'Store') . ' AI Assistant'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 22,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response_body = curl_exec($ch);
            $http_status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_errno($ch) ? curl_error($ch) : '';
            curl_close($ch);

            if ($curl_err !== '') {
                log_debug("OpenRouter Key #{$key_num} cURL Error: {$curl_err}");
                mark_key_failure($pool_state, $key_id, 503);
                break; // Rotate to next OpenRouter key
            }

            $result = json_decode((string)$response_body, true);
            $content = trim((string)($result['choices'][0]['message']['content'] ?? ''));
            if ($http_status >= 200 && $http_status < 300 && $content !== '') {
                mark_key_success($pool_state, $key_id);
                return [
                    'text' => $content,
                    'provider' => 'openrouter',
                    'model' => $model_id,
                    'key_index' => $key_num
                ];
            }

            $err_msg = $result['error']['message'] ?? "HTTP {$http_status}";
            log_debug("OpenRouter Key #{$key_num} ({$model_id}) failed [{$http_status}]: {$err_msg}");

            // If key is rate-limited (429) or unauthorized/out-of-credits (401/402/403), cooldown key and rotate immediately
            if (in_array($http_status, [401, 402, 403, 429], true)) {
                mark_key_failure($pool_state, $key_id, $http_status);
                break; // Move to next OpenRouter key in the pool
            }
        }
    }

    return null;
}

// 8. ORCHESTRATE POOL EXECUTION BASED ON PROVIDER MODE
$pool_state = load_pool_state();
$ai_result = null;

if ($ai_provider === 'openrouter') {
    // Primary: OpenRouter Pool -> Failover: Gemini Pool
    $ai_result = call_openrouter_key_pool($openrouter_keys, $ai_prompt_text, $preferred_openrouter_model, $ai_pool_strategy, $pool_state);
    if (!$ai_result && !empty($gemini_keys)) {
        log_debug("OpenRouter pool exhausted; failing over to Gemini key pool.");
        $ai_result = call_gemini_key_pool($gemini_keys, $ai_prompt_text, $ai_pool_strategy, $pool_state);
    }
} else {
    // 'auto' or 'gemini' -> Primary: Gemini Pool -> Failover: OpenRouter Pool
    $ai_result = call_gemini_key_pool($gemini_keys, $ai_prompt_text, $ai_pool_strategy, $pool_state);
    if (!$ai_result && !empty($openrouter_keys)) {
        log_debug("Gemini pool exhausted; failing over to OpenRouter key pool.");
        $ai_result = call_openrouter_key_pool($openrouter_keys, $ai_prompt_text, $preferred_openrouter_model, $ai_pool_strategy, $pool_state);
    }
}

save_pool_state($pool_state);

// 9. FORMAT & RETURN RESPONSE
if ($ai_result && !empty($ai_result['text'])) {
    $Parsedown = new Parsedown();
    $ai_html = $Parsedown->text($ai_result['text']);
    echo json_encode([
        'status' => 'success',
        'provider' => $ai_result['provider'],
        'model' => $ai_result['model'],
        'pool_key_index' => $ai_result['key_index'],
        'response' => $ai_html
    ]);
    log_debug("Success via {$ai_result['provider']} (Key #{$ai_result['key_index']}, Model: {$ai_result['model']})");
} else {
    // Graceful fallback if all keys in both pools are rate-limited or unreachable
    log_debug("All configured keys in pool failed; serving smart local fallback.");
    echo json_encode([
        'status' => 'success',
        'provider' => 'smart_fallback',
        'response' => render_smart_fallback($prompt, $product_details)
    ]);
}

log_debug("=== AI Chat Pool Script Completed ===");
?>