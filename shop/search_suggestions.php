<?php
// search_suggestions.php
// This script provides product name suggestions for the search bar.

// Include the database connection and configuration
require_once('admin/inc/config.php');
require_once('admin/inc/functions.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$suggestions = [];

if (strlen($query) >= 1) {
    try {
        $searchTerm = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $prefixTerm = mb_strtolower($query, 'UTF-8') . '%';

        // Search in product names and descriptions with prefix matches ordered first for typing completion
        $statement = $pdo->prepare(
            "SELECT 
                p_id, 
                p_name,
                p_featured_photo,
                p_current_price,
                ecat_id
             FROM tbl_product 
             WHERE (
                 LOWER(p_name) LIKE ? 
                 OR LOWER(COALESCE(p_short_description, '')) LIKE ? 
                 OR LOWER(COALESCE(p_description, '')) LIKE ?
             ) 
             AND p_is_active = 1
             ORDER BY 
                 CASE 
                     WHEN LOWER(p_name) LIKE ? THEN 0 
                     WHEN LOWER(p_name) LIKE ? THEN 1 
                     ELSE 2 
                 END, 
                 p_name ASC
             LIMIT 12"
        );
        
        $statement->execute([$searchTerm, $searchTerm, $searchTerm, $prefixTerm, $searchTerm]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        // Format suggestions with additional info for typing completion
        foreach ($results as $product) {
            $suggestions[] = [
                'id' => (int)$product['p_id'],
                'name' => $product['p_name'],
                'price' => $product['p_current_price'],
                'image' => !empty($product['p_featured_photo']) ? $product['p_featured_photo'] : '',
                'url' => BASE_URL . 'product.php?id=' . (int)$product['p_id'],
                'completion' => $product['p_name']
            ];
        }
    } catch (Throwable $e) {
        // Return error response
        http_response_code(500);
        $suggestions = ['error' => 'Database error occurred: ' . $e->getMessage()];
    }
} else {
    // Return empty suggestions if query is empty
    $suggestions = [];
}

echo json_encode($suggestions);
?>
