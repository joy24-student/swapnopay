<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/catalog-delete.php';
try {
    deleteStoreCatalogEntry($pdo, 'end-category', (int)($_GET['id'] ?? $_POST['id'] ?? 0));
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || isset($_POST['ajax']) || isset($_GET['ajax']);
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true, 'message' => 'End category deleted successfully.']);
        exit;
    }
    header('Location: end-category.php');exit;
} catch (Throwable $error) {
    $message=$error instanceof PDOException ? 'This record is still in use. Refresh the list and try again.' : $error->getMessage();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['ajax']) || isset($_GET['ajax'])) {
        http_response_code(409);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    http_response_code(409);
    echo '<p>' . htmlspecialchars($message,ENT_QUOTES,'UTF-8') . '</p><a href="end-category.php">Return to list</a>';
}
