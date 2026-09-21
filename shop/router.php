<?php
// Shared clean-URL routing for Caddy and the PHP development server.
$routePath=rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH));

// Handle path-based storefront slug prefix (e.g. /my-shop/product/item -> /product/item)
$trimmed = trim($routePath, '/');
$segments = explode('/', $trimmed);
$firstSegment = !empty($segments[0]) ? strtolower($segments[0]) : '';
$reserved = ['product', 'category', 'search', 'page', 'admin', 'assets', 'api', 'payment', 'customer', 'vendor', 'uploads'];
if ($firstSegment && !in_array($firstSegment, $reserved, true) && preg_match('/\A[a-z0-9](?:[a-z0-9-]{1,46})[a-z0-9]\z/', $firstSegment)) {
    $remainder = substr($trimmed, strlen($firstSegment));
    $routePath = '/' . ltrim($remainder, '/');
}

if (PHP_SAPI === 'cli-server') {
    $file=realpath(__DIR__ . $routePath);
    if($file && str_starts_with($file,__DIR__ . DIRECTORY_SEPARATOR) && is_file($file)) return false;
}
$script=null;
if (preg_match('#\A/product/([a-z0-9-]+)/?\z#',$routePath,$match)) { $script='product.php';$_GET['slug']=$match[1]; }
elseif (preg_match('#\A/category/([a-z0-9-]+)/page/([0-9]+)/?\z#',$routePath,$match)) { $script='product-category.php';$_GET['slug']=$match[1];$_GET['type']='top-category';$_GET['page']=$match[2]; }
elseif (preg_match('#\A/category/([a-z0-9-]+)(?:/([a-z0-9-]+))?(?:/([a-z0-9-]+))?/?\z#',$routePath,$match)) { $script='product-category.php';$depth=count($match)-1;$_GET['slug']=$match[$depth];$_GET['type']=[1=>'top-category',2=>'mid-category',3=>'end-category'][$depth]; }
elseif (preg_match('#\A/search/([^/]+)/?\z#',$routePath,$match)) { $script='search-result.php';$_GET['q']=$match[1]; }
elseif (preg_match('#\A/page/([0-9]+)/?\z#',$routePath,$match)) { $script='index.php';$_GET['page']=$match[1]; }
elseif($routePath==='/') { $script='index.php'; }
if(!$script) { http_response_code(404);echo 'Page not found.';exit; }
$_REQUEST=array_merge($_REQUEST,$_GET);
$_SERVER['SCRIPT_NAME']='/' . $script;
chdir(__DIR__);
require __DIR__ . '/' . $script;
