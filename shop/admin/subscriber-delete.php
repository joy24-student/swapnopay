<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php
// Preventing the direct access of this page.
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $pdo->prepare("SELECT * FROM tbl_subscriber WHERE subs_id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	}
}
?>

<?php

	// Delete from tbl_subscriber
	$statement = $pdo->prepare("DELETE FROM tbl_subscriber WHERE subs_id=?");
	$statement->execute(array($_REQUEST['id']));

	$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
	    || isset($_POST['ajax']) || isset($_GET['ajax']);
	if ($isAjax) {
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(['success' => true, 'message' => 'Subscriber deleted successfully.']);
	    exit;
	}

	header('location: subscriber.php');
	exit;
?>