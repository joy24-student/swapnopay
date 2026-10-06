<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	} else {
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
		foreach ($result as $row) {
			$cust_status = $row['cust_status'];
		}
	}
}
?>

<?php
if($cust_status == 0) {$final = 1;} else {$final = 0;}
$statement = $pdo->prepare("UPDATE tbl_customer SET cust_status=? WHERE cust_id=?");
$statement->execute(array($final,$_REQUEST['id']));

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['ajax']) || isset($_GET['ajax']);

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'new_status' => $final,
        'message' => 'Customer status ' . ($final == 1 ? 'activated' : 'deactivated') . ' successfully.'
    ]);
    exit;
}

header('location: customer.php');
exit;