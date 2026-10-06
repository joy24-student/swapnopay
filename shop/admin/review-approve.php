<?php
// admin/review-approve.php
require_once __DIR__ . '/inc/guard.php';
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once('inc/config.php');
require_once('inc/functions.php');

if(!isset($_SESSION['user'])) {
    header('location: login.php');
    exit;
}

if(!isset($_REQUEST['id'])) {
    header('location: reviews.php');
    exit;
} else {
    $statement = $pdo->prepare("SELECT * FROM tbl_review WHERE review_id=?");
    $statement->execute(array($_REQUEST['id']));
    $total = $statement->rowCount();
    if( $total == 0 ) {
        header('location: reviews.php');
        exit;
    }
}

// Get current status
$statement = $pdo->prepare("SELECT status FROM tbl_review WHERE review_id=?");
$statement->execute(array($_REQUEST['id']));
$current_status = $statement->fetchColumn();

// Toggle status
$new_status = ($current_status == 'Approved') ? 'Pending' : 'Approved';

$statement = $pdo->prepare("UPDATE tbl_review SET status=? WHERE review_id=?");
$statement->execute(array($new_status, $_REQUEST['id']));

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['ajax']) || isset($_GET['ajax']);

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'new_status' => $new_status,
        'message' => "Review marked as {$new_status} successfully!"
    ]);
    exit;
}

$_SESSION['success_message'] = 'Review status updated successfully!';
header('location: reviews.php');
exit;