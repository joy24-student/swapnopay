<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/inc/catalog-delete.php';
try {
    $catId = (int)($_GET['id'] ?? 0);
    $photoStmt = $pdo->prepare("SELECT photo FROM tbl_top_category WHERE tcat_id=?");
    $photoStmt->execute([$catId]);
    $catPhoto = $photoStmt->fetchColumn();

    deleteStoreCatalogEntry($pdo, 'top-category', $catId);

    if(!empty($catPhoto) && file_exists('../assets/uploads/'.$catPhoto)) {
        @unlink('../assets/uploads/'.$catPhoto);
    }
    header('Location: top-category.php');exit;
} catch (Throwable $error) {
    http_response_code(409);
    $message=$error instanceof PDOException ? 'This record is still in use. Refresh the list and try again.' : $error->getMessage();
    echo '<p>' . htmlspecialchars($message,ENT_QUOTES,'UTF-8') . '</p><a href="top-category.php">Return to list</a>';
}
