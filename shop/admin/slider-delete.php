<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $pdo->prepare("SELECT * FROM tbl_slider WHERE id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	}
}
?>

<?php

	// Getting photo ID to unlink from folder
	$statement = $pdo->prepare("SELECT * FROM tbl_slider WHERE id=?");
	$statement->execute(array($_REQUEST['id']));
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
	foreach ($result as $row) {
		$photo = $row['photo'];
	}

	// Unlink the photo
	if($photo!='') {
		unlink('../assets/uploads/'.$photo);	
	}

	// Delete from tbl_slider
	$statement = $pdo->prepare("DELETE FROM tbl_slider WHERE id=?");
	$statement->execute(array($_REQUEST['id']));

	$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
	    || isset($_POST['ajax']) || isset($_GET['ajax']);
	if ($isAjax) {
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(['success' => true, 'message' => 'Slider deleted successfully.']);
	    exit;
	}

	header('location: slider.php');
	exit;
?>