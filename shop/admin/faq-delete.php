<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $pdo->prepare("SELECT * FROM tbl_faq WHERE faq_id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	}
}
?>

<?php
	// Delete from tbl_faq
	$statement = $pdo->prepare("DELETE FROM tbl_faq WHERE faq_id=?");
	$statement->execute(array($_REQUEST['id']));

	$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
	    || isset($_POST['ajax']) || isset($_GET['ajax']);
	if ($isAjax) {
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(['success' => true, 'message' => 'FAQ deleted successfully.']);
	    exit;
	}

	header('location: faq.php');
	exit;
?>