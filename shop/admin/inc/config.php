<?php
date_default_timezone_set('Asia/Dhaka');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Hosting configuration is provisioned outside every public document root. An
// unregistered Host must never fall back to another merchant's database.
$runtimeRoot = getenv('SHOP_RUNTIME_DIR') ?: ($_SERVER['SHOP_RUNTIME_DIR'] ?? null);
if (!$runtimeRoot) {
    $candidate = dirname(__DIR__, 3) . '/swapnopay-backend/data/shop-runtime';
    if (is_dir($candidate . '/hosts')) {
        $runtimeRoot = $candidate;
    }
}

$rawHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
if (str_contains($rawHost, ':')) {
    $rawHost = explode(':', $rawHost, 2)[0];
}
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = strtok($rawUri, '?');
$pathTrimmed = trim($requestPath, '/');
$segments = explode('/', $pathTrimmed);
$candidateSlug = !empty($segments[0]) ? strtolower($segments[0]) : '';

// 1. Bare platform domain visit redirect (e.g. https://shop.swapnopay.top/)
if (empty($candidateSlug) && ($rawHost === 'shop.swapnopay.top' || $rawHost === 'shops.swapnopay.top')) {
    header('Location: https://swapnopay.top/', true, 302);
    exit;
}

$runtime = null;
if ($runtimeRoot) {
    $file = $rawHost !== '' ? rtrim($runtimeRoot, '/\\') . '/hosts/' . $rawHost . '.json' : '';
    $runtime = (is_file($file)) ? json_decode(file_get_contents($file), true) : null;

    // Fallback: path-based tenant lookup (e.g. https://shop.swapnopay.top/<slug>/...)
    if (!$runtime && $candidateSlug && preg_match('/\A[a-z0-9](?:[a-z0-9-]{1,46})[a-z0-9]\z/', $candidateSlug)) {
        $slugFile = rtrim($runtimeRoot, '/\\') . '/hosts/' . $candidateSlug . '.json';
        if (!is_file($slugFile)) {
            $slugFile = rtrim($runtimeRoot, '/\\') . '/slugs/' . $candidateSlug . '.json';
        }
        if (is_file($slugFile)) {
            $runtime = json_decode(file_get_contents($slugFile), true);
            if ($runtime && empty($runtime['base_url'])) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
                    || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
                $proto = $isHttps ? 'https' : 'http';
                $runtime['base_url'] = $proto . '://' . $rawHost . '/' . $candidateSlug . '/';
            }
        }
    }
}

// 2. Tier-2 Dynamic Database Resolver & Auto-Healer (recovers stores even if files were removed)
if (!$runtime && ($candidateSlug !== '' || $rawHost !== '')) {
    $dbUrl = getenv('SHOP_DATABASE_URL') ?: getenv('DATABASE_URL') ?: null;
    if (!$dbUrl) {
        $backendEnv = dirname(__DIR__, 3) . '/swapnopay-backend/.env';
        if (is_file($backendEnv) && is_readable($backendEnv)) {
            $lines = @file($backendEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $cleanK = trim($k);
                    if ($cleanK === 'SHOP_DATABASE_URL' || $cleanK === 'DATABASE_URL') {
                        $dbUrl = trim($v, " \t\n\r\0\x0B\"'");
                        break;
                    }
                }
            }
        }
    }
    if (!$dbUrl && getenv('DB_HOST') && getenv('DB_USER')) {
        $envHost = getenv('DB_HOST');
        $envPort = getenv('DB_PORT') ?: 5432;
        $envUser = getenv('DB_USER');
        $envPass = getenv('DB_PASS') ?: '';
        $envName = getenv('DB_NAME') ?: 'postgres';
        $dbUrl = "postgresql://{$envUser}:" . urlencode($envPass) . "@{$envHost}:{$envPort}/{$envName}";
    }

    if ($dbUrl) {
        try {
            $parsed = parse_url($dbUrl);
            $dbHost = $parsed['host'] ?? '127.0.0.1';
            $dbPort = $parsed['port'] ?? 5432;
            $dbName = ltrim($parsed['path'] ?? '/postgres', '/');
            $dbUser = rawurldecode($parsed['user'] ?? 'postgres');
            $dbPass = rawurldecode($parsed['pass'] ?? '');
            $dsnControl = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};sslmode=disable";
            $pdoControl = new PDO($dsnControl, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);

            $lookupQuery = $candidateSlug !== '' ? $candidateSlug : $rawHost;
            $stmt = $pdoControl->prepare('SELECT * FROM shop_control.launches WHERE shop_slug = :q OR custom_domain = :q LIMIT 1');
            $stmt->execute([':q' => $lookupQuery]);
            $storeRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($storeRow) {
                if (!empty($storeRow['runtime_config'])) {
                    $runtime = json_decode($storeRow['runtime_config'], true);
                }
                if (!$runtime || empty($runtime['db'])) {
                    $cleanSlug = $storeRow['shop_slug'];
                    $schema = !empty($storeRow['schema_name']) ? $storeRow['schema_name'] : ('store_' . preg_replace('/[^a-z0-9_]/', '_', $cleanSlug));
                    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
                    $proto = $isHttps ? 'https' : 'http';
                    $storeBaseUrl = !empty($storeRow['custom_domain'])
                        ? "{$proto}://{$storeRow['custom_domain']}/"
                        : "{$proto}://shop.swapnopay.top/{$cleanSlug}/";

                    // Retrieve merchant's own database and Supabase credentials if configured
                    $merchantDbHost = $dbHost;
                    $merchantDbPort = (int)$dbPort;
                    $merchantDbName = $dbName;
                    $merchantDbUser = $dbUser;
                    $merchantDbPass = $dbPass;
                    $merchantSsl = 'disable';
                    $merchantSupabaseUrl = '';
                    $merchantSupabaseAnon = '';

                    try {
                        $mStmt = $pdoControl->prepare('SELECT supabase_url, supabase_anon_key, database_url FROM merchants WHERE id = ? LIMIT 1');
                        $mStmt->execute([$storeRow['merchant_id']]);
                        $mRow = $mStmt->fetch(PDO::FETCH_ASSOC);
                        if ($mRow) {
                            $merchantSupabaseUrl = $mRow['supabase_url'] ?? '';
                            $merchantSupabaseAnon = $mRow['supabase_anon_key'] ?? '';
                            if (!empty($mRow['database_url'])) {
                                $parsedMdb = parse_url($mRow['database_url']);
                                if (!empty($parsedMdb['host'])) {
                                    $merchantDbHost = $parsedMdb['host'];
                                    $merchantDbPort = (int)($parsedMdb['port'] ?? 5432);
                                    $merchantDbName = ltrim($parsedMdb['path'] ?? '/postgres', '/');
                                    $merchantDbUser = rawurldecode($parsedMdb['user'] ?? 'postgres');
                                    $merchantDbPass = rawurldecode($parsedMdb['pass'] ?? '');
                                    $merchantSsl = 'require';
                                }
                            }
                        }
                    } catch (Throwable $_) {}

                    $runtime = [
                        'merchant_id' => $storeRow['merchant_id'],
                        'base_url' => $storeBaseUrl,
                        'store_name' => $storeRow['store_name'],
                        'shop_slug' => $cleanSlug,
                        'supabase_url' => $merchantSupabaseUrl,
                        'supabase_anon_key' => $merchantSupabaseAnon,
                        'db' => [
                            'host' => $merchantDbHost,
                            'port' => $merchantDbPort,
                            'database' => $merchantDbName,
                            'user' => $merchantDbUser,
                            'password' => $merchantDbPass,
                            'schema' => $schema,
                            'sslmode' => $merchantSsl,
                        ],
                        'backend_url' => 'https://api.swapnopay.top',
                    ];
                }

                // Auto-heal: restore runtime JSON files onto disk
                if ($runtime && $runtimeRoot) {
                    $jsonPayload = json_encode($runtime, JSON_UNESCAPED_SLASHES);
                    if (is_dir($runtimeRoot . '/slugs')) {
                        @file_put_contents(rtrim($runtimeRoot, '/\\') . '/slugs/' . $storeRow['shop_slug'] . '.json', $jsonPayload);
                    }
                    if (is_dir($runtimeRoot . '/hosts')) {
                        @file_put_contents(rtrim($runtimeRoot, '/\\') . '/hosts/' . $storeRow['shop_slug'] . '.json', $jsonPayload);
                        if (!empty($storeRow['custom_domain'])) {
                            @file_put_contents(rtrim($runtimeRoot, '/\\') . '/hosts/' . $storeRow['custom_domain'] . '.json', $jsonPayload);
                        }
                    }
                }
            }
        } catch (Throwable $dbErr) {
            error_log('Store resolver DB lookup notice: ' . $dbErr->getMessage());
        }
    }
}

if (!$runtime || empty($runtime['merchant_id']) || empty($runtime['db'])) {
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (is_file($envFile)) {
        $runtime = null;
        $runtimeRoot = null;
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$name,$value] = explode('=', $line, 2);
            $name = trim($name);
            if (preg_match('/\A[A-Z_][A-Z0-9_]*\z/', $name)) {
                $cleanVal = trim($value, " \t\n\r\0\x0B\"'");
                putenv($name . '=' . $cleanVal);
                $_ENV[$name] = $cleanVal;
                $_SERVER[$name] = $cleanVal;
            }
        }
    } else {
        http_response_code(404); exit('Store not found.');
    }
}

// Tenant-isolated session
$GLOBALS['runtime'] = $runtime;
$merchantId = $runtime['merchant_id'] ?? (getenv('MERCHANT_ID') ?: 'default-merchant');
$sessionCookieName = 'SP_SESS_' . substr(md5($merchantId . '_' . ($runtime['shop_slug'] ?? '')), 0, 12);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($sessionCookieName);
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE) {
    if (($_SESSION['shop_merchant_id'] ?? '') !== $merchantId) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['shop_merchant_id'] = $merchantId;
}

$db_driver = 'pgsql';
$db = $runtime['db'] ?? [
    'host' => getenv('DB_HOST') ?: getenv('SUPABASE_DB_HOST') ?: '',
    'port' => getenv('DB_PORT') ?: getenv('SUPABASE_DB_PORT') ?: 5432,
    'database' => getenv('DB_NAME') ?: getenv('SUPABASE_DB_NAME') ?: '',
    'user' => getenv('DB_USER') ?: getenv('SUPABASE_DB_USER') ?: '',
    'password' => getenv('DB_PASS') ?: getenv('SUPABASE_DB_PASSWORD') ?: '',
    'sslmode' => getenv('DB_SSLMODE') ?: 'require'
];
if (!$runtime && getenv('DATABASE_URL')) {
    $parts = parse_url(getenv('DATABASE_URL'));
    if (!$parts || !in_array($parts['scheme'] ?? '', ['postgres','postgresql'], true)) { http_response_code(503); exit('Invalid database configuration.'); }
    parse_str($parts['query'] ?? '', $query);
    $db = [
        'host' => $parts['host'],
        'port' => $parts['port'] ?? 6543,
        'database' => rawurldecode(ltrim($parts['path'] ?? '', '/')),
        'user' => rawurldecode($parts['user'] ?? ''),
        'password' => rawurldecode($parts['pass'] ?? ''),
        'sslmode' => $query['sslmode'] ?? $db['sslmode']
    ];
}
try {
    $effectiveSslMode = $db['sslmode'] ?? 'require';
    if (($db['host'] === '127.0.0.1' || $db['host'] === 'localhost') && $effectiveSslMode === 'require') {
        $effectiveSslMode = 'disable';
    }
    $dsn = 'pgsql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['database'] . ';sslmode=' . $effectiveSslMode;
    $pdo = new PDO($dsn,$db['user'],$db['password'],[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::ATTR_PERSISTENT => false
    ]);
    if (!empty($db['schema']) && preg_match('/\A[a-z0-9_]+\z/', (string)$db['schema'])) {
        $pdo->exec('SET search_path TO "' . $db['schema'] . '", public, pg_catalog');
    }
} catch (Throwable $error) {
    error_log('Store database unavailable: ' . $error->getMessage());
    http_response_code(503); header('Retry-After: 30'); exit('The store is temporarily unavailable. Please try again shortly. (Database error: ' . htmlspecialchars($error->getMessage()) . ')');
}
if (!defined('DB_DRIVER_NAME')) define('DB_DRIVER_NAME',$db_driver);
if (!defined('SQL_RAND')) define('SQL_RAND',$db_driver === 'pgsql' ? 'RANDOM()' : 'RAND()');
// Dynamic Supabase configuration resolved automatically per merchant:
// 1. Merchant's provisioned runtime configuration (from shop provisioning / launch)
// 2. Or tenant-level database settings (tbl_settings configured by merchant)
// 3. Or environment variables if explicitly set
$resolved_supabase_url = !empty($runtime['supabase_url']) ? $runtime['supabase_url'] : '';
$resolved_supabase_anon = !empty($runtime['supabase_anon_key']) ? $runtime['supabase_anon_key'] : '';

if (empty($resolved_supabase_url)) {
    try {
        $settingSupa = $pdo->query("SELECT supabase_url, supabase_anon_key FROM tbl_settings WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!empty($settingSupa['supabase_url'])) {
            $resolved_supabase_url = $settingSupa['supabase_url'];
            $resolved_supabase_anon = $settingSupa['supabase_anon_key'] ?? '';
        }
    } catch (Throwable $_) {}
}

if (empty($resolved_supabase_url)) {
    $resolved_supabase_url = getenv('SUPABASE_URL') ?: getenv('ADMIN_SUPABASE_URL') ?: '';
    $resolved_supabase_anon = getenv('SUPABASE_ANON_KEY') ?: getenv('ADMIN_SUPABASE_ANON_KEY') ?: '';
}

if (!defined('SUPABASE_URL')) define('SUPABASE_URL', rtrim((string)$resolved_supabase_url, '/'));
if (!defined('SUPABASE_ANON_KEY')) define('SUPABASE_ANON_KEY', (string)$resolved_supabase_anon);
if (!defined('SUPABASE_SERVICE_KEY')) define('SUPABASE_SERVICE_KEY', $runtime ? '' : (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ''));
if (!defined('MERCHANT_ID')) define('MERCHANT_ID',$runtime['merchant_id'] ?? (getenv('MERCHANT_ID') ?: ''));
if (!defined('SWAPNOPAY_API_URL')) define('SWAPNOPAY_API_URL',rtrim($runtime['backend_url'] ?? $runtime['api_url'] ?? (getenv('SWAPNOPAY_API_URL') ?: 'https://api.swapnopay.top'),'/'));
$BASE_URL = $runtime['base_url'] ?? (getenv('STORE_BASE_URL') ?: '');
if (!$BASE_URL) {
    $column = $db_driver === 'pgsql' ? '"BASE_URL"' : '`BASE_URL`';
    $BASE_URL = $pdo->query('SELECT ' . $column . ' FROM tbl_settings WHERE id=1')->fetchColumn() ?: '';
}
if (!filter_var($BASE_URL,FILTER_VALIDATE_URL) || !in_array(parse_url($BASE_URL,PHP_URL_SCHEME),['https','http'],true)) {
    http_response_code(503); exit('The store address has not been configured.');
}
if (!defined('BASE_URL')) define('BASE_URL',rtrim($BASE_URL,'/') . '/');

// Tenant cache isolation helper
$tenantKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $runtime['shop_slug'] ?? $runtime['merchant_id'] ?? 'default');
if (!function_exists('getShopCacheFile')) {
    function getShopCacheFile($key) {
        global $tenantKey;
        $tk = $tenantKey ?: 'default';
        return __DIR__ . '/cache_' . $tk . '_' . $key . '.json';
    }
}

// Dynamically resolve Store / Shop Name from Cached Settings or Database
$settingsCacheFile = getShopCacheFile('settings');
$settingsRow = null;
if (file_exists($settingsCacheFile) && (time() - filemtime($settingsCacheFile) < 300)) {
    $settingsRow = json_decode(file_get_contents($settingsCacheFile), true);
}
if (!$settingsRow) {
    try {
        $settingsRow = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($settingsRow) {
            @file_put_contents($settingsCacheFile, json_encode($settingsRow));
        }
    } catch (Throwable $e) {}
}

if (!empty($settingsRow)) {
    $GLOBALS['STORE_SETTINGS'] = $settingsRow;
}

$dynamicStoreName = '';
if (!empty($settingsRow['store_name'])) {
    $dynamicStoreName = trim($settingsRow['store_name']);
} elseif (!empty($settingsRow['meta_title_home'])) {
    $dynamicStoreName = trim($settingsRow['meta_title_home']);
} elseif (!empty($runtime['store_name'])) {
    $dynamicStoreName = trim($runtime['store_name']);
} else {
    $dynamicStoreName = 'Online Store';
}

if (!defined('STORE_NAME')) define('STORE_NAME', $dynamicStoreName);
if (!defined('SHOP_NAME')) define('SHOP_NAME', $dynamicStoreName);

if (!function_exists('clean_store_name')) {
    function clean_store_name($name) {
        if (empty($name)) return '';
        $clean = preg_replace('/\[\/?(?:color(?:=[^\]]+)?|gradient(?:=[^\]]+)?|badge(?:=[^\]]+)?|gold|orange|amber|red|rose|blue|cyan|green|emerald|purple|violet|indigo|pink|yellow)\]/i', '', (string)$name);
        return trim(strip_tags($clean));
    }
}

if (!function_exists('render_store_name_html')) {
    function render_store_name_html($name) {
        if (empty($name)) return '';
        $s = (string)$name;

        // Custom hex/name color: [color=#FF5722]Word[/color] or [color=crimson]Word[/color]
        $s = preg_replace_callback('/\[color=([#a-zA-Z0-9]+)\](.*?)\[\/color\]/is', function($m) {
            $col = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            return '<span style="color:' . $col . ' !important;">' . $m[2] . '</span>';
        }, $s);

        // Gradient shortcut: [gradient]Word[/gradient] or [gradient=#F59E0B,#EC4899]Word[/gradient]
        $s = preg_replace_callback('/\[gradient(?:=(.*?))?\](.*?)\[\/gradient\]/is', function($m) {
            $colors = !empty($m[1]) ? trim($m[1]) : '#F59E0B, #EC4899';
            if (!str_contains($colors, 'linear-gradient')) {
                $grad = 'linear-gradient(135deg, ' . htmlspecialchars($colors, ENT_QUOTES, 'UTF-8') . ')';
            } else {
                $grad = htmlspecialchars($colors, ENT_QUOTES, 'UTF-8');
            }
            return '<span style="background:' . $grad . '; -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; font-weight:800; display:inline-block;">' . $m[2] . '</span>';
        }, $s);

        // Badge shortcut: [badge]Word[/badge] or [badge=#FEDB65]Word[/badge]
        $s = preg_replace_callback('/\[badge(?:=(.*?))?\](.*?)\[\/badge\]/is', function($m) {
            $bg = !empty($m[1]) ? htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8') : '#FEDB65';
            return '<span style="background:' . $bg . '; color:#0F172A; padding:2px 8px; border-radius:6px; font-size:0.82em; font-weight:800; display:inline-block; vertical-align:middle; line-height:1.2; letter-spacing:0;">' . $m[2] . '</span>';
        }, $s);

        // Built-in named color tags
        $colorAliases = [
            'gold'    => '#F59E0B',
            'orange'  => '#EA580C',
            'amber'   => '#D97706',
            'red'     => '#EF4444',
            'rose'    => '#F43F5E',
            'blue'    => '#2563EB',
            'cyan'    => '#06B6D4',
            'green'   => '#10B981',
            'emerald' => '#059669',
            'purple'  => '#8B5CF6',
            'violet'  => '#7C3AED',
            'indigo'  => '#4F46E5',
            'pink'    => '#EC4899',
            'yellow'  => '#EAB308'
        ];
        foreach ($colorAliases as $tag => $hex) {
            $s = preg_replace('/\[' . $tag . '\](.*?)\[\/' . $tag . '\]/is', '<span style="color:' . $hex . ' !important;">$1</span>', $s);
        }

        // Allow safe HTML tags: <span>, <b>, <strong>, <i>, <em>, <font>, <small>
        $allowed = '<span><b><strong><i><em><font><small>';
        return strip_tags($s, $allowed);
    }
}

if (!function_exists('getStoreName')) {
    function getStoreName($plain = false) {
        $name = defined('STORE_NAME') ? STORE_NAME : 'Online Store';
        return $plain ? clean_store_name($name) : render_store_name_html($name);
    }
}

if (!function_exists('clearShopCache')) {
    function clearShopCache($type = 'all', $id = null) {
        global $tenantKey;
        $tk = $tenantKey ?: 'default';
        $dir = __DIR__;
        if ($type === 'settings' || $type === 'all') {
            @unlink($dir . '/cache_' . $tk . '_settings.json');
            @unlink($dir . '/cache_settings.json');
        }
        if ($type === 'menu' || $type === 'all') {
            @unlink($dir . '/cache_' . $tk . '_menu.json');
            @unlink($dir . '/cache_menu.json');
        }
        if ($type === 'slides' || $type === 'all') {
            @unlink($dir . '/cache_' . $tk . '_slides.json');
            @unlink($dir . '/cache_slides.json');
        }
        if ($type === 'product' && $id) {
            @unlink($dir . '/cache_' . $tk . '_prod_' . (int)$id . '.json');
            @unlink($dir . '/cache_prod_' . (int)$id . '.json');
        }
        if ($type === 'products' || $type === 'all') {
            $files = glob($dir . '/cache_' . $tk . '_prod_*.json');
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
            @unlink($dir . '/cache_' . $tk . '_sidebar_cats.json');
            @unlink($dir . '/cache_' . $tk . '_home_feed.json');
            @unlink($dir . '/cache_sidebar_cats.json');
            @unlink($dir . '/cache_home_feed.json');
        }
    }
}


