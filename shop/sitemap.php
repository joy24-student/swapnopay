<?php
/**
 * DYNAMIC SITEMAP GENERATOR FOR SEO
 * 
 * This script generates sitemaps for:
 * 1. Main sitemap index
 * 2. Products sitemap
 * 3. Categories sitemap
 * 4. Pages sitemap
 * 
 * Usage:
 * - /sitemap.xml - Main sitemap index
 * - /sitemap-products.xml - All products
 * - /sitemap-categories.xml - All categories
 * - /sitemap-pages.xml - Important pages
 */

require_once("admin/inc/config.php");
require_once("admin/inc/seo_helpers.php");

header("Content-Type: application/xml; charset=UTF-8");

// Determine which sitemap to generate
$type = isset($_GET['type']) ? $_GET['type'] : 'index';

$baseUrl = rtrim(BASE_URL, '/') . '/';

switch($type) {
    case 'products':
        generateProductsSitemap($baseUrl, $pdo);
        break;
    case 'categories':
        generateCategoriesSitemap($baseUrl, $pdo);
        break;
    case 'pages':
        generatePagesSitemap($baseUrl);
        break;
    case 'index':
    default:
        generateSitemapIndex($baseUrl);
        break;
}

/**
 * Generate main sitemap index (points to other sitemaps)
 */
function generateSitemapIndex($baseUrl) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Products sitemap
    echo '  <sitemap>' . "\n";
    echo '    <loc>' . htmlspecialchars($baseUrl . 'sitemap.php?type=products', ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
    echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
    echo '  </sitemap>' . "\n";
    
    // Categories sitemap
    echo '  <sitemap>' . "\n";
    echo '    <loc>' . htmlspecialchars($baseUrl . 'sitemap.php?type=categories', ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
    echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
    echo '  </sitemap>' . "\n";
    
    // Pages sitemap
    echo '  <sitemap>' . "\n";
    echo '    <loc>' . htmlspecialchars($baseUrl . 'sitemap.php?type=pages', ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
    echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
    echo '  </sitemap>' . "\n";
    
    echo '</sitemapindex>' . "\n";
}

/**
 * Generate products sitemap
 * Max 50,000 URLs per sitemap (Google limit)
 */
function generateProductsSitemap($baseUrl, $pdo) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    try {
        // Get all active products
        $statement = $pdo->prepare("
            SELECT p_id, p_name, p_last_updated 
            FROM tbl_product 
            WHERE p_is_active=1 
            ORDER BY p_id DESC 
            LIMIT 50000
        ");
        $statement->execute();
        $products = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $url = getProductURL($product['p_id'], $product['p_name'], $baseUrl);
            $lastMod = !empty($product['p_last_updated']) ? 
                      date('Y-m-d', strtotime($product['p_last_updated'])) : 
                      date('Y-m-d');
            
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
            echo '    <lastmod>' . $lastMod . '</lastmod>' . "\n";
            echo '    <changefreq>weekly</changefreq>' . "\n";
            echo '    <priority>0.8</priority>' . "\n";
            echo '  </url>' . "\n";
        }
    } catch (Exception $e) {
        error_log("Error generating products sitemap: " . $e->getMessage());
    }
    
    echo '</urlset>' . "\n";
}

/**
 * Generate categories sitemap
 */
function generateCategoriesSitemap($baseUrl, $pdo) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    try {
        // Top categories
        $statement = $pdo->prepare("SELECT tcat_id, tcat_name FROM tbl_top_category ORDER BY tcat_id");
        $statement->execute();
        $topCategories = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($topCategories as $cat) {
            $url = getCategoryURL($cat['tcat_id'], $cat['tcat_name'], 'top-category', '', $baseUrl);
            
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            echo '    <changefreq>weekly</changefreq>' . "\n";
            echo '    <priority>0.7</priority>' . "\n";
            echo '  </url>' . "\n";
        }
        
        // Mid categories
        $statement = $pdo->prepare("SELECT mcat_id, mcat_name FROM tbl_mid_category ORDER BY mcat_id");
        $statement->execute();
        $midCategories = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($midCategories as $cat) {
            $url = getCategoryURL($cat['mcat_id'], $cat['mcat_name'], 'mid-category', '', $baseUrl);
            
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            echo '    <changefreq>weekly</changefreq>' . "\n";
            echo '    <priority>0.6</priority>' . "\n";
            echo '  </url>' . "\n";
        }
        
        // End categories
        $statement = $pdo->prepare("SELECT ecat_id, ecat_name FROM tbl_end_category ORDER BY ecat_id");
        $statement->execute();
        $endCategories = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($endCategories as $cat) {
            $url = getCategoryURL($cat['ecat_id'], $cat['ecat_name'], 'end-category', '', $baseUrl);
            
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
            echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            echo '    <changefreq>monthly</changefreq>' . "\n";
            echo '    <priority>0.5</priority>' . "\n";
            echo '  </url>' . "\n";
        }
    } catch (Exception $e) {
        error_log("Error generating categories sitemap: " . $e->getMessage());
    }
    
    echo '</urlset>' . "\n";
}

/**
 * Generate pages sitemap (static pages)
 */
function generatePagesSitemap($baseUrl) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    $pages = array(
        array('url' => '', 'priority' => '1.0', 'changefreq' => 'daily'),
        array('url' => 'categories.php', 'priority' => '0.9', 'changefreq' => 'weekly'),
        array('url' => 'deals.php', 'priority' => '0.8', 'changefreq' => 'daily'),
        array('url' => 'stores.php', 'priority' => '0.7', 'changefreq' => 'monthly'),
        array('url' => 'about.php', 'priority' => '0.7', 'changefreq' => 'monthly'),
        array('url' => 'contact.php', 'priority' => '0.6', 'changefreq' => 'monthly'),
        array('url' => 'faq.php', 'priority' => '0.6', 'changefreq' => 'weekly'),
    );
    
    foreach ($pages as $page) {
        $url = $baseUrl . $page['url'];
        
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
        echo '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
        echo '    <priority>' . $page['priority'] . '</priority>' . "\n";
        echo '  </url>' . "\n";
    }
    
    echo '</urlset>' . "\n";
}
?>
