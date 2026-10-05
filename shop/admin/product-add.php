<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once('header.php'); ?>

<?php
require_once __DIR__ . '/inc/catalog.php';
if(isset($_POST['form1'])) {
    try {
        $savedProduct=saveStoreProduct($pdo,$_POST,$_FILES,null);
        header('Location: product-edit.php?id=' . $savedProduct . '&saved=1');exit;
    } catch(Throwable $error) {
        $error_message = htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}
if(isset($_GET['saved'])) $success_message='Product saved successfully.';
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Add Product</h1>
	</div>
	<div class="content-header-right">
		<a href="product.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>


<section class="content">

	<div class="row">
		<div class="col-md-12">

			<?php if($error_message): ?>
			<div class="callout callout-danger">
			
			<p>
			<?php echo $error_message; ?>
			</p>
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
							<label for="" class="col-sm-3 control-label">Top Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="tcat_id" class="form-control select2 top-cat">
									<option value="">Select Top Level Category</option>
									<?php
									$statement = $pdo->prepare("SELECT * FROM tbl_top_category ORDER BY tcat_name ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);	
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['tcat_id']; ?>"><?php echo $row['tcat_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Mid Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="mcat_id" class="form-control select2 mid-cat">
									<option value="">Select Mid Level Category</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">End Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="ecat_id" class="form-control select2 end-cat">
									<option value="">Select End Level Category</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Product Name <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_name" class="form-control">
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Old Price <br><span style="font-size:10px;font-weight:normal;">(In USD)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_old_price" class="form-control">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Current Price <span>*</span><br><span style="font-size:10px;font-weight:normal;">(In USD)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_current_price" class="form-control">
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Quantity <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_qty" class="form-control">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Select Size</label>
							<div class="col-sm-4">
								<select name="size[]" class="form-control select2" multiple="multiple">
									<?php
									$statement = $pdo->prepare("SELECT * FROM tbl_size ORDER BY size_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['size_id']; ?>"><?php echo $row['size_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Select Color</label>
							<div class="col-sm-4">
								<select name="color[]" class="form-control select2" multiple="multiple">
									<?php
									$statement = $pdo->prepare("SELECT * FROM tbl_color ORDER BY color_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										?>
										<option value="<?php echo $row['color_id']; ?>"><?php echo $row['color_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Featured Photo <span>*</span></label>
							<div class="col-sm-4" style="padding-top:4px;">
								<input type="file" name="p_featured_photo">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Other Photos</label>
							<div class="col-sm-4" style="padding-top:4px;">
								<table id="ProductTable" style="width:100%;">
			                        <tbody>
			                            <tr>
			                                <td>
			                                    <div class="upload-btn">
			                                        <input type="file" name="photo[]" style="margin-bottom:5px;">
			                                    </div>
			                                </td>
			                                <td style="width:28px;"><a href="javascript:void()" class="Delete btn btn-danger btn-xs">X</a></td>
			                            </tr>
			                        </tbody>
			                    </table>
							</div>
							<div class="col-sm-2">
			                    <input type="button" id="btnAddNew" value="Add Item" style="margin-top: 5px;margin-bottom:10px;border:0;color: #fff;font-size: 14px;border-radius:3px;" class="btn btn-warning btn-xs">
			                </div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Description</label>
							<div class="col-sm-8">
								<textarea name="p_description" class="form-control" cols="30" rows="10" id="editor1"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Short Description</label>
							<div class="col-sm-8">
								<textarea name="p_short_description" class="form-control" cols="30" rows="10" id="editor2"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Features</label>
							<div class="col-sm-8">
								<textarea name="p_feature" class="form-control" cols="30" rows="10" id="editor3"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Conditions</label>
							<div class="col-sm-8">
								<textarea name="p_condition" class="form-control" cols="30" rows="10" id="editor4"></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Return Policy</label>
							<div class="col-sm-8">
								<textarea name="p_return_policy" class="form-control" cols="30" rows="10" id="editor5"></textarea>
							</div>
						</div>
						
						
<!-- After return policy field -->
<div class="form-group">
    <label for="" class="col-sm-3 control-label">Product Video Link</label>
    <div class="col-sm-8">
        <input type="text" name="p_video_link" class="form-control" placeholder="https://www.youtube.com/watch?v=... or https://...r2.dev/video.mp4">
        <small class="text-muted">Supports YouTube video links or Cloudflare R2 / direct MP4 object storage URLs (comma or newline separated for multiple videos).</small>
    </div>
</div>


						
						
						
						
						
						
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Featured?</label>
							<div class="col-sm-8">
								<select name="p_is_featured" class="form-control" style="width:auto;">
									<option value="0">No</option>
									<option value="1">Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Active?</label>
							<div class="col-sm-8">
								<select name="p_is_active" class="form-control" style="width:auto;">
									<option value="0">No</option>
									<option value="1">Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label"></label>
							<div class="col-sm-6">
								<button type="submit" class="btn btn-success pull-left" name="form1">Add Product</button>
							</div>
						</div>
					</div>
				</div>

			</form>


		</div>
	</div>

</section>

<?php require_once('footer.php'); ?>