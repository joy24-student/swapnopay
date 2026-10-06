<?php
require_once __DIR__ . '/inc/guard.php';
$id=filter_var($_REQUEST['id'] ?? null,FILTER_VALIDATE_INT);
if(!$id) {http_response_code(400);exit('Invalid product.');}
$pdo->prepare('UPDATE tbl_product SET p_is_active=0 WHERE p_id=?')->execute([$id]);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['ajax']) || isset($_GET['ajax']);

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
    exit;
}

header('Location: product.php');exit;
