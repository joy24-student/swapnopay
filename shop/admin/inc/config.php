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
$runtime = null;
if ($runtimeRoot) {
    // Hosted checkout supports COD until provider verification and reconciliation
    // are integrated. Legacy callback URLs must not create or mark payments.
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('~/(?:^|[a-z0-9-]+/)payment/(?!cod/)[^/]+/~i', $requestPath)) {
        http_response_code(503); exit('Online payments are not configured for this store. Please use cash on delivery.');
    }
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (str_contains($host, ':')) {
        $host = explode(':', $host, 2)[0];
    }
    if (!preg_match('/\A[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?\z/', $host)) {
        http_response_code(404); exit('Store not found.');
    }
    $file = rtrim($runtimeRoot, '/\\') . '/hosts/' . $host . '.json';
    $runtime = is_file($file) ? json_decode(file_get_contents($file), true) : null;

    // Fallback: path-based tenant lookup (e.g. https://shop.swapnopay.top/<slug>/...)
    if (!$runtime) {
        $pathTrimmed = trim($requestPath, '/');
        $segments = explode('/', $pathTrimmed);
        $candidateSlug = !empty($segments[0]) ? strtolower($segments[0]) : '';
        if ($candidateSlug && preg_match('/\A[a-z0-9](?:[a-z0-9-]{1,46})[a-z0-9]\z/', $candidateSlug)) {
            $slugFile = rtrim($runtimeRoot, '/\\') . '/hosts/' . $candidateSlug . '.json';
            if (!is_file($slugFile)) {
                $slugFile = rtrim($runtimeRoot, '/\\') . '/slugs/' . $candidateSlug . '.json';
            }
            if (is_file($slugFile)) {
                $runtime = json_decode(file_get_contents($slugFile), true);
                if ($runtime) {
                    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $port = (!empty($_SERVER['SERVER_PORT']) && !in_array((int)$_SERVER['SERVER_PORT'], [80, 443], true)) ? ':' . $_SERVER['SERVER_PORT'] : '';
                    $runtime['base_url'] = $proto . '://' . $host . $port . '/' . $candidateSlug . '/';
                }
            }
        }
    }

    if (!$runtime || empty($runtime['merchant_id']) || empty($runtime['db'])) {
        http_response_code(404); exit('Store not found.');
    }

    // Tenant-isolated session
    $sessionCookieName = 'SP_SESS_' . substr(md5($runtime['merchant_id']), 0, 12);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name($sessionCookieName);
        session_start();
    }
    // Bind authentication to the store even if someone supplies a session ID
    // originally created on a different tenant's hostname.
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (($_SESSION['shop_merchant_id'] ?? '') !== $runtime['merchant_id']) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['shop_merchant_id'] = $runtime['merchant_id'];
    }
} else {
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (is_file($envFile)) foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$name,$value] = explode('=', $line, 2);
        $name = trim($name);
        if (preg_match('/\A[A-Z_][A-Z0-9_]*\z/', $name) && getenv($name) === false) putenv($name . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}

$db_driver = $runtime ? 'pgsql' : (getenv('DB_DRIVER') ?: 'mysql');
$db = $runtime['db'] ?? [
    'host' => getenv('DB_HOST') ?: getenv('SUPABASE_DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: getenv('SUPABASE_DB_PORT') ?: ($db_driver === 'pgsql' ? 5432 : 3306),
    'database' => getenv('DB_NAME') ?: getenv('SUPABASE_DB_NAME') ?: 'ecommerceweb',
    'user' => getenv('DB_USER') ?: getenv('SUPABASE_DB_USER') ?: '',
    'password' => getenv('DB_PASS') ?: getenv('SUPABASE_DB_PASSWORD') ?: '',
    'sslmode' => getenv('DB_SSLMODE') ?: 'require'
];
if (!$runtime && getenv('DATABASE_URL')) {
    $parts = parse_url(getenv('DATABASE_URL'));
    if (!$parts || !in_array($parts['scheme'] ?? '', ['postgres','postgresql','mysql'], true)) { http_response_code(503); exit('Invalid database configuration.'); }
    $db_driver = $parts['scheme'] === 'mysql' ? 'mysql' : 'pgsql';
    parse_str($parts['query'] ?? '', $query);
    $db = ['host'=>$parts['host'], 'port'=>$parts['port'] ?? ($db_driver === 'pgsql' ? 5432 : 3306),
        'database'=>rawurldecode(ltrim($parts['path'] ?? '', '/')), 'user'=>rawurldecode($parts['user'] ?? ''),
        'password'=>rawurldecode($parts['pass'] ?? ''), 'sslmode'=>$query['sslmode'] ?? $db['sslmode']];
}
try {
    if (!$db['user'] || !$db['database'] || !in_array($db_driver,['pgsql','mysql'],true)) throw new RuntimeException('Database credentials missing');
    foreach (['host','port','database','sslmode'] as $part) if (strpbrk((string)$db[$part], ";\r\n") !== false) throw new RuntimeException('Invalid database configuration');
    $dsn = $db_driver . ':host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['database'];
    $dsn .= $db_driver === 'pgsql' ? ';sslmode=' . $db['sslmode'] : ';charset=utf8mb4';
    $pdo = new PDO($dsn,$db['user'],$db['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
} catch (Throwable $error) {
    error_log('Store database unavailable: ' . $error->getCode());
    http_response_code(503); header('Retry-After: 30'); exit('The store is temporarily unavailable. Please try again shortly.');
}
define('DB_DRIVER_NAME',$db_driver);
define('SQL_RAND',$db_driver === 'pgsql' ? 'RANDOM()' : 'RAND()');
// Never expose platform service-role credentials to a hosted PHP storefront.
define('SUPABASE_URL',$runtime ? '' : (getenv('SUPABASE_URL') ?: ''));
define('SUPABASE_ANON_KEY',$runtime ? '' : (getenv('SUPABASE_ANON_KEY') ?: ''));
define('SUPABASE_SERVICE_KEY',$runtime ? '' : (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ''));
define('MERCHANT_ID',$runtime['merchant_id'] ?? (getenv('MERCHANT_ID') ?: ''));
$BASE_URL = $runtime['base_url'] ?? (getenv('STORE_BASE_URL') ?: '');
if (!$BASE_URL) {
    $column = $db_driver === 'pgsql' ? '"BASE_URL"' : '`BASE_URL`';
    $BASE_URL = $pdo->query('SELECT ' . $column . ' FROM tbl_settings WHERE id=1')->fetchColumn() ?: '';
}
if (!filter_var($BASE_URL,FILTER_VALIDATE_URL) || !in_array(parse_url($BASE_URL,PHP_URL_SCHEME),['https','http'],true)) {
    http_response_code(503); exit('The store address has not been configured.');
}
define('BASE_URL',rtrim($BASE_URL,'/') . '/');
