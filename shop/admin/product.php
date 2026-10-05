<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once('header.php'); ?>

<section class="content-header">
	<div class="content-header-left">
		<h1>View Products</h1>
	</div>
	<div class="content-header-right">
		<a href="product-add.php" class="btn btn-primary btn-sm">Add Product</a>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="example1" class="table table-bordered table-hover table-striped">
					<thead class="thead-dark">
							<tr>
								<th width="10">#</th>
								<th>Photo</th>
								<th width="160">Product Name</th>
								<th width="80">Vendor</th>
								<th width="60">Old Price</th>
								<th width="60">(C) Price</th>
								<th width="60">Quantity</th>
								<th>Featured?</th>
								<th>Active?</th>
								<th>Category</th>
								<th width="80">Action</th>
							</tr>
						</thead>
						<tbody>
<?php
try {
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS business_id integer DEFAULT NULL");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_total_view integer DEFAULT 0");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS cust_id integer DEFAULT 0");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_video_link text DEFAULT ''");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_businesses (business_id SERIAL PRIMARY KEY, business_name varchar(255), owner_user_id integer)");
} catch (Throwable $e) {}

$i=0;
$statement = $pdo->prepare("SELECT
							t1.p_id,
							t1.p_name,
							t1.p_old_price,
							t1.p_current_price,
							t1.p_qty,
							t1.p_featured_photo,
							t1.p_is_featured,
							t1.p_is_active,
							t1.ecat_id,
							t1.business_id,

							t2.ecat_id AS end_ecat_id,
							t2.ecat_name,

							t3.mcat_id,
							t3.mcat_name,

							t4.tcat_id,
							t4.tcat_name,
							
							b.business_name,
							b.owner_user_id

							FROM tbl_product t1
							LEFT JOIN tbl_end_category t2
							ON t1.ecat_id = t2.ecat_id
							LEFT JOIN tbl_mid_category t3
							ON t2.mcat_id = t3.mcat_id
							LEFT JOIN tbl_top_category t4
							ON t3.tcat_id = t4.tcat_id
							LEFT JOIN tbl_businesses b
							ON t1.business_id = b.business_id
							ORDER BY t1.p_id DESC
							");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
	$i++;
	?>
	<tr>
		<td><?php echo $i; ?></td>
		<td style="width:82px;"><img src="../assets/uploads/<?php echo htmlspecialchars($row['p_featured_photo'] ?: 'placeholder.svg'); ?>" alt="<?php echo htmlspecialchars($row['p_name']); ?>" style="width:80px;"></td>
		<td><?php echo htmlspecialchars($row['p_name']); ?></td>
		<td>
			<?php 
			if(!empty($row['business_name'])) {
				echo '<span class="badge badge-info">' . htmlspecialchars($row['business_name']) . '</span>';
			} else {
				echo '<span class="badge badge-secondary">Admin Product</span>';
			}
			?>
		</td>
		<td>BDT <?php echo number_format((float)$row['p_old_price'], 2); ?></td>
		<td>BDT <?php echo number_format((float)$row['p_current_price'], 2); ?></td>
		<td><?php echo $row['p_qty']; ?></td>
		<td>
			<?php if($row['p_is_featured'] == 1) {echo '<span class="badge badge-success" style="background-color:green;">Yes</span>';} else {echo '<span class="badge badge-success" style="background-color:red;">No</span>';} ?>
		</td>
		<td>
			<?php if($row['p_is_active'] == 1) {echo '<span class="badge badge-success" style="background-color:green;">Yes</span>';} else {echo '<span class="badge badge-danger" style="background-color:red;">No</span>';} ?>
		</td>
		<td><?php echo htmlspecialchars($row['tcat_name'] ?? 'Uncategorized'); ?><?php if(!empty($row['mcat_name'])): ?><br><?php echo htmlspecialchars($row['mcat_name']); ?><?php endif; ?><?php if(!empty($row['ecat_name'])): ?><br><?php echo htmlspecialchars($row['ecat_name']); ?><?php endif; ?></td>
		<td>										
			<a href="product-edit.php?id=<?php echo $row['p_id']; ?>" class="btn btn-primary btn-xs">Edit</a>
			<a href="#" class="btn btn-danger btn-xs" data-href="product-delete.php?id=<?php echo $row['p_id']; ?>" data-toggle="modal" data-target="#confirm-delete">Delete</a>  
		</td>
	</tr>
								<?php
							}
							?>							
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>


<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel">Delete Confirmation</h4>
            </div>
            <div class="modal-body">
                <p>Archive this product from the storefront?</p>
                <p>Existing orders and product history will remain available.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger btn-ok">Delete</a>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
