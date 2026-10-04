<?php
/**
 * SEO SLUG HELPER FUNCTIONS
 * Handles URL slug generation and retrieval from database
 */

/**
 * Convert string to SEO-friendly slug
 * Example: "Sony Headphones 2024" -> "sony-headphones-2024"
 */
function generateSlug($text) {
    // Convert to lowercase
    $slug = strtolower($text);
    
    // Replace spaces with hyphens
    $slug = str_replace(' ', '-', $slug);
    
    // Remove special characters, keep only alphanumeric and hyphens
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    
    // Replace multiple consecutive hyphens with single hyphen
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    
    return $slug;
}

/**
 * Generate unique slug by appending ID
 * Example: "sony-headphones-2024-1234" (where 1234 is the product ID)
 */
function generateUniqueSlug($text, $id) {
    $baseSlug = generateSlug($text);
    return $baseSlug . '-' . $id;
}

/**
 * Extract ID from slug
 * Example: "sony-headphones-2024-1234" -> 1234
 */
function extractIdFromSlug($slug) {
    $parts = explode('-', $slug);
    $id = end($parts);
    return is_numeric($id) ? (int)$id : null;
}

/**
 * Get product ID by slug
 */
function getProductIdBySlug($slug, $pdo) {
    $id = extractIdFromSlug($slug);
    
    if ($id) {
        $statement = $pdo->prepare("SELECT p_id FROM tbl_product WHERE p_id=? AND p_is_active=1");
        $statement->execute(array($id));
        if ($statement->rowCount() > 0) {
            return $id;
        }
    }
    
    // Check if the slug itself is numeric
    if (is_numeric($slug)) {
        $statement = $pdo->prepare("SELECT p_id FROM tbl_product WHERE p_id=? AND p_is_active=1");
        $statement->execute([(int)$slug]);
        if ($statement->rowCount() > 0) {
            return (int)$slug;
        }
    }

    // Fallback: check slug column or name
    try {
        $stmt = $pdo->prepare("SELECT p_id FROM tbl_product WHERE slug=? AND p_is_active=1 LIMIT 1");
        $stmt->execute([$slug]);
        $found = $stmt->fetchColumn();
        if ($found) return (int)$found;
    } catch (Throwable $e) {}

    return false;
}

/**
 * Get category ID by slug (for any category type)
 */
function getCategoryIdBySlug($slug, $type, $pdo) {
    $id = extractIdFromSlug($slug);
    
    if (!$id) {
        return false;
    }
    
    $table = '';
    $idColumn = '';
    
    switch($type) {
        case 'top-category':
            $table = 'tbl_top_category';
            $idColumn = 'tcat_id';
            break;
        case 'mid-category':
            $table = 'tbl_mid_category';
            $idColumn = 'mcat_id';
            break;
        case 'end-category':
            $table = 'tbl_end_category';
            $idColumn = 'ecat_id';
            break;
        default:
            return false;
    }
    
    // Verify category exists with this ID
    $statement = $pdo->prepare("SELECT $idColumn FROM $table WHERE $idColumn=?");
    $statement->execute(array($id));
    
    if ($statement->rowCount() > 0) {
        return $id;
    }
    
    return false;
}

/**
 * Update product slug in database (add column if doesn't exist)
 */
function updateProductSlug($productId, $productName, $pdo) {
    try {
        $slug = generateUniqueSlug($productName, $productId);
        
        $statement = $pdo->prepare("UPDATE tbl_product SET slug=? WHERE p_id=?");
        $statement->execute(array($slug, $productId));
        
        return $slug;
    } catch (Exception $e) {
        error_log("Error updating product slug: " . $e->getMessage());
        return false;
    }
}

/**
 * Update category slug in database
 */
function updateCategorySlug($categoryId, $categoryName, $type, $pdo) {
    try {
        $table = '';
        $idColumn = '';
        $slugColumn = '';
        
        switch($type) {
            case 'top-category':
                $table = 'tbl_top_category';
                $idColumn = 'tcat_id';
                $slugColumn = 'slug';
                break;
            case 'mid-category':
                $table = 'tbl_mid_category';
                $idColumn = 'mcat_id';
                $slugColumn = 'slug';
                break;
            case 'end-category':
                $table = 'tbl_end_category';
                $idColumn = 'ecat_id';
                $slugColumn = 'slug';
                break;
            default:
                return false;
        }
        
        $slug = generateUniqueSlug($categoryName, $categoryId);
        
        $statement = $pdo->prepare("UPDATE $table SET $slugColumn=? WHERE $idColumn=?");
        $statement->execute(array($slug, $categoryId));
        
        return $slug;
    } catch (Exception $e) {
        error_log("Error updating category slug: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate product slug URL
 * Example: /product/sony-headphones-2024-1234
 */
function getProductURL($productId, $productName, $baseUrl = '') {
    $slug = generateUniqueSlug($productName, $productId);
    return rtrim($baseUrl, '/') . '/product/' . $slug;
}

/**
 * Generate category slug URL
 * Examples:
 * Top: /category/electronics-1
 * Mid: /category/electronics-1/smartphones-2
 * End: /category/electronics-1/smartphones-2/android-phones-3
 */
function getCategoryURL($categoryId, $categoryName, $type = 'top-category', $parentSlug = '', $baseUrl = '') {
    $slug = generateUniqueSlug($categoryName, $categoryId);
    if ($type !== 'top-category' && !$parentSlug) return rtrim($baseUrl,'/') . '/product-category.php?type=' . rawurlencode($type) . '&slug=' . rawurlencode($slug);
    $url = rtrim($baseUrl, '/') . '/category/';
    
    if (!empty($parentSlug)) {
        $url .= $parentSlug . '/';
    }
    
    $url .= $slug;
    
    return $url;
}

/**
 * Generate search URL
 * Example: /search/smartphone
 */
function getSearchURL($query, $baseUrl = '') {
    $slug = generateSlug($query);
    return rtrim($baseUrl, '/') . '/search/' . $slug;
}

/**
 * Generate pagination URL
 * Example: /page/2
 */
function getPaginationURL($pageNumber, $baseUrl = '') {
    return rtrim($baseUrl, '/') . '/page/' . (int)$pageNumber;
}

/**
 * Generate category pagination URL
 * Example: /category/electronics-1/page/2
 */
function getCategoryPaginationURL($categorySlug, $pageNumber, $baseUrl = '') {
    return rtrim($baseUrl, '/') . '/category/' . $categorySlug . '/page/' . (int)$pageNumber;
}

/**
 * Redirect with 301 (Moved Permanently) - Use for deleted/moved products
 */
function redirect301($newUrl) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $newUrl);
    exit;
}

/**
 * Redirect with 302 (Found) - Use for temporary redirects
 */
function redirect302($newUrl) {
    header("HTTP/1.1 302 Found");
    header("Location: " . $newUrl);
    exit;
}
?>
