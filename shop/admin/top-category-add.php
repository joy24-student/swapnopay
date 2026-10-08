<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	$valid = 1;

    if(empty($_POST['tcat_name'])) {
        $valid = 0;
        $error_message .= "Top Category Name can not be empty<br>";
    }

    // Handle Photo Upload
    $path = $_FILES['photo']['name'];
    $path_tmp = $_FILES['photo']['tmp_name'];
    $final_name = '';

    if($path != '') {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        $ext = strtolower($ext);
        if( $ext!='jpg' && $ext!='png' && $ext!='jpeg' && $ext!='gif' && $ext!='webp' ) {
            $valid = 0;
            $error_message .= 'You must have to upload jpg, jpeg, gif, webp or png file<br>';
        } else {
            $final_name = 'tcat-'.time().'.'.$ext;
        }
    }

    if($valid == 1) {
        if($final_name != '') {
            move_uploaded_file( $path_tmp, '../assets/uploads/'.$final_name );
        } else {
            $final_name = 'placeholder.svg';
        }

        $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(tcat_order), 0) + 1 FROM tbl_top_category")->fetchColumn();
		$statement = $pdo->prepare("INSERT INTO tbl_top_category (tcat_name,show_on_menu,tcat_order,photo) VALUES (?,?,?,?)");
		$statement->execute(array($_POST['tcat_name'],(int)($_POST['show_on_menu'] ?? 0),$maxOrder,$final_name));
            
    	$success_message = 'Top Category is added successfully.';
    	if (function_exists('clearShopCache')) { clearShopCache('menu'); }
    }
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Add Top Level Category</h1>
	</div>
	<div class="content-header-right">
		<a href="top-category.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">

			<?php if($error_message): ?>
			<div class="callout callout-danger">
				<p><?php echo $error_message; ?></p>
			</div>
			<?php endif; ?>

			<?php if($success_message): ?>
			<div class="callout callout-success">
				<p><?php echo $success_message; ?></p>
			</div>
			<?php endif; ?>

			<form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
				<div class="box box-info">
					<div class="box-body">
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Top Category Name <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" class="form-control" name="tcat_name" value="<?php if(isset($_POST['tcat_name'])){echo htmlspecialchars($_POST['tcat_name'], ENT_QUOTES, 'UTF-8');} ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Photo</label>
							<div class="col-sm-4" style="padding-top:6px;">
								<input type="file" name="photo">
								<p class="help-block" style="font-size:11px;margin-bottom:0;color:#888;">Allowed: jpg, jpeg, png, gif, webp</p>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Show on Menu? <span>*</span></label>
							<div class="col-sm-4">
								<select name="show_on_menu" class="form-control" style="width:auto;">
									<option value="1">Yes</option>
									<option value="0">No</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label"></label>
							<div class="col-sm-6">
								<button type="submit" class="btn btn-success pull-left" name="form1">Submit</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</section>

<?php require_once('footer.php'); 