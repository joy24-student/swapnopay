<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/catalog-delete.php';
try {
    $catId = (int)($_REQUEST['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
    $catStmt = $pdo->prepare("SELECT photo, banner FROM tbl_top_category WHERE tcat_id=?");
    $catStmt->execute([$catId]);
    $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
    $catPhoto = $catRow['photo'] ?? '';
    $catBanner = $catRow['banner'] ?? '';

    deleteStoreCatalogEntry($pdo, 'top-category', $catId);
    if (function_exists('clearShopCache')) {
        clearShopCache('menu');
        clearShopCache('categories');
        clearShopCache('products');
        clearShopCache('all');
    }
    unset($_SESSION['sn_sidebar_cats']);

    if(!empty($catPhoto) && $catPhoto !== 'placeholder.svg' && file_exists('../assets/uploads/'.$catPhoto)) {
        @unlink('../assets/uploads/'.$catPhoto);
    }
    if(!empty($catBanner) && file_exists('../assets/uploads/'.$catBanner)) {
        @unlink('../assets/uploads/'.$catBanner);
    }
    
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || isset($_POST['ajax']) || isset($_GET['ajax']);
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true, 'message' => 'Top category deleted successfully.']);
        exit;
    }

    header('Location: top-category.php');exit;
} catch (Throwable $error) {
    $message=$error instanceof PDOException ? 'This record is still in use. Refresh the list and try again.' : $error->getMessage();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['ajax']) || isset($_GET['ajax'])) {
        http_response_code(409);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    http_response_code(409);
    echo '<p>' . htmlspecialchars($message,ENT_QUOTES,'UTF-8') . '</p><a href="top-category.php">Return to list</a>';
}
