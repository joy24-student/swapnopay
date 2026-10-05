<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php
include 'inc/config.php';
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id > 0) {
	$statement = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id=? ORDER BY ecat_name ASC");
	$statement->execute([$id]);
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	echo '<option value="">Select End Level Category</option>';
	foreach ($result as $row) {
		echo '<option value="' . (int)$row['ecat_id'] . '">' . htmlspecialchars($row['ecat_name']) . '</option>';
	}
}