<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php
include 'inc/config.php';
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id > 0) {
	$statement = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id=? ORDER BY mcat_name ASC");
	$statement->execute([$id]);
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	echo '<option value="">Select Mid Level Category</option>';
	foreach ($result as $row) {
		echo '<option value="' . (int)$row['mcat_id'] . '">' . htmlspecialchars($row['mcat_name']) . '</option>';
	}
}