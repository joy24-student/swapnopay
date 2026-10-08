<?php
/**
 * SwapnoPay Admin - AI Product Content Generator
 * Generates Description, Features, Short Description, Conditions, Return Policy & Title
 * Powered by Gemini Multi-Key Pool & OpenRouter with Smart Algorithmic Fallback
 */

require_once __DIR__ . '/inc/guard.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Ensure request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$action = trim($_POST['action'] ?? 'generate_all');
$prompt = trim($_POST['prompt'] ?? '');
$product_name = trim($_POST['product_name'] ?? '');
$category_name = trim($_POST['category_name'] ?? '');
$tone = trim($_POST['tone'] ?? 'persuasive');
$language = trim($_POST['language'] ?? 'en');
$target_field = trim($_POST['target_field'] ?? 'all');
$current_content = trim($_POST['current_content'] ?? '');

// If prompt is empty but product_name is provided, use product_name as prompt
if ($prompt === '' && $product_name !== '') {
    $prompt = $product_name;
}

if ($prompt === '' && $current_content === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please provide a product prompt, description, or product name.'
    ]);
    exit;
}

// 1. Fetch AI settings from database
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $settings = [];
}

$raw_gemini_keys = $settings['gemini_api_key'] ?? '';
$raw_openrouter_keys = $settings['openrouter_api_key'] ?? '';
$ai_provider = strtolower(trim($settings['ai_provider'] ?? 'auto'));
$ai_pool_strategy = strtolower(trim($settings['ai_pool_strategy'] ?? 'round_robin'));
$preferred_openrouter_model = trim($settings['openrouter_model'] ?? 'openrouter/free');

function parse_key_pool($raw) {
    if (empty($raw)) return [];
    $parts = preg_split('/[\r\n,;]+/', (string)$raw);
    $clean = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && !in_array($p, $clean, true)) {
            $clean[] = $p;
        }
    }
    return $clean;
}

$gemini_keys = parse_key_pool($raw_gemini_keys);
$openrouter_keys = parse_key_pool($raw_openrouter_keys);

/**
 * Call Gemini API with model rotation
 */
function call_gemini_writer($gemini_keys, $system_instruction, $user_prompt) {
    if (empty($gemini_keys)) return null;

    $models = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
        'gemini-1.5-flash'
    ];

    $combined_prompt = $system_instruction . "\n\n" . $user_prompt;

    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $combined_prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topP' => 0.95,
            'maxOutputTokens' => 2048
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH']
        ]
    ]);

    foreach ($gemini_keys as $api_key) {
        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err !== '' || $status < 200 || $status >= 300) {
                continue;
            }

            $decoded = json_decode((string)$response, true);
            $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ($text !== '') {
                return [
                    'text' => $text,
                    'provider' => 'gemini',
                    'model' => $model
                ];
            }
        }
    }

    return null;
}

/**
 * Call OpenRouter API with failover models
 */
function call_openrouter_writer($openrouter_keys, $preferred_model, $system_instruction, $user_prompt) {
    if (empty($openrouter_keys)) return null;

    $models = array_unique(array_filter([
        $preferred_model,
        'google/gemini-2.5-flash',
        'meta-llama/llama-3.3-70b-instruct:free',
        'deepseek/deepseek-chat:free',
        'qwen/qwen-2.5-72b-instruct:free',
        'openrouter/free'
    ]));

    foreach ($openrouter_keys as $api_key) {
        foreach ($models as $model) {
            $payload = json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system_instruction],
                    ['role' => 'user', 'content' => $user_prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 2048
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://openrouter.ai/api/v1/chat/completions',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $api_key,
                    'HTTP-Referer: https://swapnopay.top',
                    'X-Title: SwapnoPay AI Product Studio'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err !== '' || $status < 200 || $status >= 300) {
                continue;
            }

            $decoded = json_decode((string)$response, true);
            $text = $decoded['choices'][0]['message']['content'] ?? '';
            if ($text !== '') {
                return [
                    'text' => $text,
                    'provider' => 'openrouter',
                    'model' => $model
                ];
            }
        }
    }

    return null;
}

/**
 * Intelligent Algorithmic Fallback Generator
 * Ensures high-quality rich e-commerce content even when keys are missing or offline
 */
function generate_smart_fallback_content($prompt, $product_name, $category_name, $tone, $language, $target_field = 'all', $current_content = '') {
    $mainName = $product_name !== '' ? $product_name : trim(explode("\n", $prompt)[0]);
    if (strlen($mainName) > 60) {
        $mainName = substr($mainName, 0, 60);
    }
    $mainName = ucwords(trim($mainName, " ,.-"));

    $isBn = ($language === 'bn');
    $isBilingual = ($language === 'bilingual');

    // Detect keywords from prompt to contextualize content
    $promptLower = strtolower($prompt . ' ' . $current_content . ' ' . $category_name);
    $isElectronics = preg_match('/(phone|headphone|earbud|bluetooth|wireless|battery|laptop|watch|speaker|gadget|camera|usb|charger|screen|smart)/i', $promptLower);
    $isFashion = preg_match('/(shirt|dress|cotton|pant|jean|t-shirt|cloth|fabric|wear|shoe|sneaker|leather|jacket|hoodie)/i', $promptLower);
    $isBeauty = preg_match('/(skin|cream|serum|hair|shampoo|oil|lotion|glow|beauty|organic|herbal)/i', $promptLower);

    if ($isBn) {
        $descTitle = "{$mainName} - প্রিমিয়াম কোয়ালিটি ও চমৎকার পারফরম্যান্স";
        $p1 = "আপনার দৈনন্দিন জীবনকে আরও সহজ, আধুনিক ও আনন্দময় করতে <strong>{$mainName}</strong> নিয়ে এসেছে সর্বাধুনিক প্রযুক্তি ও চমৎকার ডিজাইন। এটি অত্যন্ত টেকসই উপাদানে তৈরি এবং সর্বোচ্চ স্থায়িত্ব নিশ্চিত করতে প্রতিটি ধাপে কোয়ালিটি চেক করা হয়েছে।";
        $p2 = "ব্যবহারকারীদের সুবিধার কথা মাথায় রেখে এর নিখুঁত ফিনিশিং এবং আকর্ষণীয় আউটলুক এটিকে প্রিমিয়াম লুক প্রদান করে। কাজের ক্ষেত্রে হোক বা অবসরে, এটি আপনাকে দেবে নিরবচ্ছিন্ন ও সন্তোষজনক অভিজ্ঞতা।";
        $p3 = "আজই অর্ডার করুন এবং উপভোগ করুন দ্রুততম ডেলিভারি ও শতভাগ ক্যাশ অন ডেলিভারি সুবিধা!";

        $shortDesc = "<p><strong>{$mainName}</strong> আপনার পছন্দের শীর্ষে থাকার মতো প্রিমিয়াম পণ্য। আকর্ষণীয় ডিজাইন, চমৎকার ডিউরেবিলিটি এবং নির্ভরযোগ্য পারফরম্যান্সে তৈরি। ৭ দিনের সহজ রিটার্ন পলিসি এবং অফিসিয়াল ওয়ারেন্টি সহ পাওয়া যাচ্ছে।</p>";

        $features = "<ul>\n" .
            "<li><strong>প্রিমিয়াম বিল্ড কোয়ালিটি:</strong> দীর্ঘস্থায়ী ও টেকসই উপাদানে তৈরি।</li>\n" .
            "<li><strong>আধুনিক ডিজাইন:</strong> অত্যন্ত স্লিক, স্টাইলিশ ও ব্যবহার-বান্ধব।</li>\n" .
            "<li><strong>উচ্চ মানসম্পন্ন কার্যক্ষমতা:</strong> নিরবচ্ছিন্ন ও দ্রুতগতির সন্তোষজনক অভিজ্ঞতা।</li>\n" .
            "<li><strong>সহজ বহনযোগ্যতা:</strong> হালকা ওজনের ফলে যেকোনো স্থানে সহজে ব্যবহারোপযোগী।</li>\n" .
            "<li><strong>১০০% অরিজিনাল ও অথেন্টিক:</strong> ফ্যাক্টরি সিল প্যাক প্রোডাক্ট।</li>\n" .
            "</ul>";

        $conditions = "<ul>\n" .
            "<li><strong>কন্ডিশন:</strong> ১০০% একদম নতুন এবং ইনট্যাক্ট ফ্যাক্টরি সিল প্যাক।</li>\n" .
            "<li><strong>কোয়ালিটি অ্যাসুরেন্স:</strong> ডেলিভারির পূর্বে প্রতিটি প্রোডাক্টের মান যাচাই করা হয়।</li>\n" .
            "<li><strong>ওয়ারেন্টি:</strong> অফিশিয়াল সার্ভিস ওয়ারেন্টি সহ নিশ্চিত সহায়তা।</li>\n" .
            "<li><strong>সতর্কতা:</strong> সরাসরি অতিরিক্ত আর্দ্রতা ও অতিরিক্ত তাপ থেকে দূরে রাখুন।</li>\n" .
            "</ul>";

        $returnPolicy = "<ul>\n" .
            "<li><strong>৭ দিনের সহজ রিটার্ন:</strong> কোনো ত্রুটি থাকলে সহজে ৭ দিনের মধ্যে রিপ্লেসমেন্ট সুবিধা।</li>\n" .
            "<li><strong>রিটার্ন শর্তাবলী:</strong> প্রোডাক্টের মূল বক্স, ইনভয়েস এবং সকল এক্সেসরিজ অক্ষত থাকতে হবে।</li>\n" .
            "<li><strong>গ্রাহক সহায়তা:</strong> দ্রুত সমাধানের জন্য আমাদের লাইভ সাপোর্ট বা হটলাইনে যোগাযোগ করুন।</li>\n" .
            "</ul>";
    } else {
        // English / Bilingual
        $descTitle = "{$mainName} — Premium Engineering & Exceptional Performance";
        $p1 = "Elevate your everyday lifestyle with the all-new <strong>{$mainName}</strong>. Meticulously engineered for users who refuse to compromise on quality, performance, or aesthetic appeal, this product delivers an unrivaled user experience from the moment you unbox it.";
        $p2 = "Crafted from high-grade, durable materials with precision ergonomics, it seamlessly balances durability with modern style. Whether for daily work, active recreation, or personal relaxation, it guarantees consistent reliability, superior ergonomics, and long-lasting satisfaction.";
        $p3 = "Experience the next level of convenience and sophistication. Backed by our verified authenticity guarantee and fast nationwide doorstep delivery.";

        $shortDesc = "<p><strong>{$mainName}</strong> brings together top-tier craftsmanship, dependable durability, and intuitive convenience in one complete package. Backed by official warranty and easy 7-day hassle-free replacement support.</p>";

        if ($isElectronics) {
            $features = "<ul>\n" .
                "<li><strong>Advanced High-Efficiency Architecture:</strong> Engineered for responsive, lag-free daily performance.</li>\n" .
                "<li><strong>Extended Battery & Energy Optimization:</strong> Designed for long-lasting endurance on every charge.</li>\n" .
                "<li><strong>Premium Build & Ergonomics:</strong> Sleek, durable materials crafted for comfortable everyday handling.</li>\n" .
                "<li><strong>Smart Connectivity & Compatibility:</strong> Plug-and-play synchronization across all modern devices.</li>\n" .
                "<li><strong>Quality Inspected:</strong> 100% factory original with full official warranty support.</li>\n" .
                "</ul>";
        } elseif ($isFashion) {
            $features = "<ul>\n" .
                "<li><strong>Ultra-Comfort Breathable Fabric:</strong> Tailored with premium-grade textiles for all-day comfort.</li>\n" .
                "<li><strong>Tailored Modern Silhouette:</strong> Flattering fit suitable for both casual outings and formal occasions.</li>\n" .
                "<li><strong>Fade & Shrink Resistant:</strong> Color-lock technology keeps it vibrant wash after wash.</li>\n" .
                "<li><strong>Reinforced Stitching:</strong> High-density seams designed for maximum durability.</li>\n" .
                "<li><strong>Effortless Care:</strong> Machine washable with quick-drying ease.</li>\n" .
                "</ul>";
        } else {
            $features = "<ul>\n" .
                "<li><strong>Superior Build Quality:</strong> Constructed from robust, eco-conscious materials for extended longevity.</li>\n" .
                "<li><strong>Ergonomic & Elegant Design:</strong> Modern aesthetics that seamlessly complement your lifestyle.</li>\n" .
                "<li><strong>Intuitive & Easy to Use:</strong> Hassle-free operation with zero complicated setup required.</li>\n" .
                "<li><strong>Certified Authentic:</strong> Brand new, genuine manufacturer packaging with quality seals.</li>\n" .
                "<li><strong>Customer Satisfaction Guaranteed:</strong> Tested to meet rigorous performance standards.</li>\n" .
                "</ul>";
        }

        $conditions = "<ul>\n" .
            "<li><strong>Condition:</strong> 100% Brand New, Unused, and Factory Sealed in original packaging.</li>\n" .
            "<li><strong>Quality Inspection:</strong> Multi-point quality inspected prior to dispatch.</li>\n" .
            "<li><strong>Warranty Coverage:</strong> Official 1-Year Service & Support Warranty included.</li>\n" .
            "<li><strong>Care Instructions:</strong> Store in a cool, dry place and follow the provided care manual.</li>\n" .
            "</ul>";

        $returnPolicy = "<ul>\n" .
            "<li><strong>7-Day Easy Return & Exchange:</strong> Hassle-free replacement for any defective or mismatched item.</li>\n" .
            "<li><strong>Eligibility Requirements:</strong> Item must remain in unblemished condition with original box, tags, and receipt.</li>\n" .
            "<li><strong>Instant Customer Support:</strong> Dedicated support team ready to assist via live chat or phone for swift processing.</li>\n" .
            "</ul>";
    }

    $description = "<h3>{$descTitle}</h3>\n<p>{$p1}</p>\n<p>{$p2}</p>\n<p>{$p3}</p>";

    return [
        'name' => $mainName,
        'description' => $description,
        'short_description' => $shortDesc,
        'feature' => $features,
        'condition' => $conditions,
        'return_policy' => $returnPolicy
    ];
}

// 2. Prepare System Instruction and User Prompt for AI
$toneMap = [
    'persuasive' => 'persuasive, high-converting, and benefit-focused for e-commerce sales',
    'professional' => 'authoritative, professional, trustworthy, and clean',
    'technical' => 'spec-rich, detailed, technical, and engineering-focused',
    'luxury' => 'elegant, sophisticated, exclusive, and high-end luxury appeal',
    'casual' => 'friendly, conversational, vibrant, and engaging for modern shoppers'
];
$selectedTone = $toneMap[$tone] ?? $toneMap['persuasive'];

$langInstruction = 'Write primarily in English with clear, engaging vocabulary.';
if ($language === 'bn') {
    $langInstruction = 'Write entirely in natural, polished Standard Bengali (বাংলা). Use correct Bengali grammar and appealing e-commerce phrases.';
} elseif ($language === 'bilingual') {
    $langInstruction = 'Write with a modern bilingual touch (clear English with Bengali phrases or Bengali highlights where appropriate for Bangladeshi shoppers).';
}

$systemInstruction = <<<INSTRUCTION
You are an expert e-commerce copywriter, merchandiser, and conversion rate optimization specialist.
Your task is to generate compelling, rich, formatted product content for an online shopping store.

CRITICAL INSTRUCTIONS:
1. Output MUST be a valid, parseable JSON object with NO markdown wrapper, NO commentary, NO preambles.
2. Format all content using clean, semantic HTML tags (<h3>, <p>, <ul>, <li>, <strong>, <em>, <span>). Do NOT use Markdown asterisks or hashtags.
3. Tone: {$selectedTone}
4. Language Instruction: {$langInstruction}
5. The JSON object must contain the following keys:
   - "name": Refined, attractive, SEO-friendly product title.
   - "short_description": 2-3 crisp, punchy HTML sentences (<p>...</p>) summarizing top appeal for quick view/cards.
   - "description": Comprehensive, persuasive HTML product overview with <h3> headings, styled paragraphs <p>, and key value propositions.
   - "feature": Structured HTML bulleted list (<ul><li>...</li></ul>) of specifications, build quality, functionality, and capabilities.
   - "condition": Structured HTML bulleted list (<ul><li>...</li></ul>) covering authenticity, 100% brand new status, factory seal, warranty terms, and care tips.
   - "return_policy": Structured HTML bulleted list (<ul><li>...</li></ul>) covering 7-day easy return guarantee, conditions, and customer support.
INSTRUCTION;

$userPrompt = "PRODUCT PROMPT / SPECIFICATIONS:\n" . $prompt;
if ($product_name !== '') {
    $userPrompt .= "\nCURRENT PRODUCT NAME: " . $product_name;
}
if ($category_name !== '') {
    $userPrompt .= "\nCATEGORY: " . $category_name;
}
if ($current_content !== '' && $action === 'polish_field') {
    $userPrompt .= "\nEXISTING CONTENT TO IMPROVE/EXPAND:\n" . $current_content;
    $userPrompt .= "\nTARGET FIELD TO IMPROVE: " . $target_field;
}

// 3. Attempt AI Execution (Gemini -> OpenRouter -> Fallback)
$ai_result = null;

if ($ai_provider === 'openrouter') {
    // OpenRouter first, then Gemini
    $ai_result = call_openrouter_writer($openrouter_keys, $preferred_openrouter_model, $systemInstruction, $userPrompt);
    if (!$ai_result) {
        $ai_result = call_gemini_writer($gemini_keys, $systemInstruction, $userPrompt);
    }
} else {
    // Gemini first (or auto), then OpenRouter
    $ai_result = call_gemini_writer($gemini_keys, $systemInstruction, $userPrompt);
    if (!$ai_result) {
        $ai_result = call_openrouter_writer($openrouter_keys, $preferred_openrouter_model, $systemInstruction, $userPrompt);
    }
}

$parsed_data = null;
$provider_used = 'fallback';

if ($ai_result && !empty($ai_result['text'])) {
    $raw = trim($ai_result['text']);
    // Strip markdown code fences if present (```json ... ```)
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $raw, $m)) {
        $raw = trim($m[1]);
    }

    $json = json_decode($raw, true);
    if (is_array($json) && (!empty($json['description']) || !empty($json['feature']) || !empty($json['short_description']))) {
        $parsed_data = $json;
        $provider_used = $ai_result['provider'] . ' (' . $ai_result['model'] . ')';
    }
}

// 4. If AI failed, missing keys, or could not parse JSON, use smart fallback
if (!$parsed_data) {
    $parsed_data = generate_smart_fallback_content($prompt, $product_name, $category_name, $tone, $language, $target_field, $current_content);
    if ($provider_used === 'fallback') {
        $provider_used = 'AI Smart Template Engine';
    }
}

// Ensure all keys exist
$response_data = [
    'name' => (string)($parsed_data['name'] ?? $product_name),
    'description' => (string)($parsed_data['description'] ?? ''),
    'short_description' => (string)($parsed_data['short_description'] ?? ''),
    'feature' => (string)($parsed_data['feature'] ?? ''),
    'condition' => (string)($parsed_data['condition'] ?? ''),
    'return_policy' => (string)($parsed_data['return_policy'] ?? '')
];

echo json_encode([
    'status' => 'success',
    'provider' => $provider_used,
    'action' => $action,
    'target_field' => $target_field,
    'data' => $response_data
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
