<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once('header.php'); ?>

<?php
require_once __DIR__ . '/inc/catalog.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    $error_message = 'Uploaded data (' . round((int)$_SERVER['CONTENT_LENGTH'] / 1048576, 1) . ' MB) exceeded server limits. Please select smaller photos.';
} elseif(isset($_POST['form1'])) {
    try {
        $savedProduct=saveStoreProduct($pdo,$_POST,$_FILES,(int)($_REQUEST['id'] ?? 0));
        header('Location: product-edit.php?id=' . $savedProduct . '&saved=1');exit;
    } catch(Throwable $error) {
        $error_message = htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}
if(isset($_GET['saved'])) $success_message='Product saved successfully.';
?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	}
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Edit Product</h1>
	</div>
	<div class="content-header-right">
		<a href="product.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
	$p_name = $row['p_name'];
	$p_old_price = $row['p_old_price'];
	$p_current_price = $row['p_current_price'];
	$p_qty = $row['p_qty'];
	$p_featured_photo = $row['p_featured_photo'];
	$p_description = $row['p_description'];
	$p_short_description = $row['p_short_description'];
	$p_feature = $row['p_feature'];
	$p_condition = $row['p_condition'];
	$p_return_policy = $row['p_return_policy'];
	$p_video_link = $row['p_video_link'] ?? '';
	$p_is_featured = $row['p_is_featured'];
	$p_is_active = $row['p_is_active'];
	$coupon_code = $row['coupon_code'] ?? '';
	$ecat_id = $row['ecat_id'];
}

$ecat_name = '';
$mcat_id = 0;
$tcat_id = 0;
$size_id = [];
$color_id = [];

$statement = $pdo->prepare("SELECT * 
                        FROM tbl_end_category t1
                        JOIN tbl_mid_category t2
                        ON t1.mcat_id = t2.mcat_id
                        JOIN tbl_top_category t3
                        ON t2.tcat_id = t3.tcat_id
                        WHERE t1.ecat_id=?");
$statement->execute(array($ecat_id));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
	$ecat_name = $row['ecat_name'];
    $mcat_id = $row['mcat_id'];
    $tcat_id = $row['tcat_id'];
}

$statement = $pdo->prepare("SELECT * FROM tbl_product_size WHERE p_id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
foreach ($result as $row) {
	$size_id[] = $row['size_id'];
}

$statement = $pdo->prepare("SELECT * FROM tbl_product_color WHERE p_id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
foreach ($result as $row) {
	$color_id[] = $row['color_id'];
}
?>


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

				<?php require_once __DIR__ . '/inc/ai_product_writer_widget.php'; ?>

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
		                                <option value="<?php echo $row['tcat_id']; ?>" <?php if($row['tcat_id'] == $tcat_id){echo 'selected';} ?>><?php echo $row['tcat_name']; ?></option>
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
		                            <?php
		                            $statement = $pdo->prepare("SELECT * FROM tbl_mid_category WHERE tcat_id = ? ORDER BY mcat_name ASC");
		                            $statement->execute(array($tcat_id));
		                            $result = $statement->fetchAll(PDO::FETCH_ASSOC);   
		                            foreach ($result as $row) {
		                                ?>
		                                <option value="<?php echo $row['mcat_id']; ?>" <?php if($row['mcat_id'] == $mcat_id){echo 'selected';} ?>><?php echo $row['mcat_name']; ?></option>
		                                <?php
		                            }
		                            ?>
		                        </select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">End Level Category Name <span>*</span></label>
							<div class="col-sm-4">
								<select name="ecat_id" class="form-control select2 end-cat">
		                            <option value="">Select End Level Category</option>
		                            <?php
		                            $statement = $pdo->prepare("SELECT * FROM tbl_end_category WHERE mcat_id = ? ORDER BY ecat_name ASC");
		                            $statement->execute(array($mcat_id));
		                            $result = $statement->fetchAll(PDO::FETCH_ASSOC);   
		                            foreach ($result as $row) {
		                                ?>
		                                <option value="<?php echo $row['ecat_id']; ?>" <?php if($row['ecat_id'] == $ecat_id){echo 'selected';} ?>><?php echo $row['ecat_name']; ?></option>
		                                <?php
		                            }
		                            ?>
		                        </select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">
								Product Name <span>*</span>
								<span class="sn-ai-inline-btn-group">
									<button type="button" class="sn-ai-inline-btn" data-field="name" data-action="generate_field" title="AI Generate or refine product name">
										<i class="fa fa-magic"></i> AI Title
									</button>
								</span>
							</label>
							<div class="col-sm-4">
								<input type="text" name="p_name" class="form-control" value="<?php echo $p_name; ?>">
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Old Price<br><span style="font-size:10px;font-weight:normal;">(In USD)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_old_price" class="form-control" value="<?php echo $p_old_price; ?>">
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Current Price <span>*</span><br><span style="font-size:10px;font-weight:normal;">(In USD)</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_current_price" class="form-control" value="<?php echo $p_current_price; ?>">
							</div>
						</div>	
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Quantity <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" name="p_qty" class="form-control" value="<?php echo $p_qty; ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Select Size</label>
							<div class="col-sm-4">
								<select name="size[]" class="form-control select2" multiple="multiple">
									<?php
									$is_select = '';
									$statement = $pdo->prepare("SELECT * FROM tbl_size ORDER BY size_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										if(isset($size_id)) {
											if(in_array($row['size_id'],$size_id)) {
												$is_select = 'selected';
											} else {
												$is_select = '';
											}
										}
										?>
										<option value="<?php echo $row['size_id']; ?>" <?php echo $is_select; ?>><?php echo $row['size_name']; ?></option>
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
									$is_select = '';
									$statement = $pdo->prepare("SELECT * FROM tbl_color ORDER BY color_id ASC");
									$statement->execute();
									$result = $statement->fetchAll(PDO::FETCH_ASSOC);			
									foreach ($result as $row) {
										if(isset($color_id)) {
											if(in_array($row['color_id'],$color_id)) {
												$is_select = 'selected';
											} else {
												$is_select = '';
											}
										}
										?>
										<option value="<?php echo $row['color_id']; ?>" <?php echo $is_select; ?>><?php echo $row['color_name']; ?></option>
										<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Existing Featured Photo</label>
							<div class="col-sm-4" style="padding-top:4px;">
								<img src="../assets/uploads/<?php echo $p_featured_photo; ?>" alt="" style="width:150px;">
								<input type="hidden" name="current_photo" value="<?php echo $p_featured_photo; ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Change Featured Photo </label>
							<div class="col-sm-4" style="padding-top:4px;">
								<input type="file" name="p_featured_photo">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Existing Other Photos</label>
							<div class="col-sm-8" style="padding-top:4px;">
								<table style="width:100%; max-width: 450px;">
			                        <tbody>
			                        	<?php
			                        	$statement = $pdo->prepare("SELECT * FROM tbl_product_photo WHERE p_id=?");
			                        	$statement->execute(array($_REQUEST['id']));
			                        	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			                        	if (empty($result)) {
			                        		echo '<tr><td colspan="2" style="color:#777; font-size:13px;">No other photos added yet.</td></tr>';
			                        	} else {
				                        	foreach ($result as $row) {
				                        		?>
												<tr style="border-bottom: 1px solid #f1f5f9;">
					                                <td style="padding: 6px 0;">
					                                    <img src="../assets/uploads/<?php echo $row['photo']; ?>" alt="" style="width:100px; height:70px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0;">
					                                </td>
					                                <td style="width:40px; text-align:right;">
					                                	<a onclick="return confirm('Are you sure you want to delete this photo?');" href="product-other-photo-delete.php?id=<?php echo $row['pp_id']; ?>&id1=<?php echo $_REQUEST['id']; ?>" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></a>
					                                </td>
					                            </tr>
				                        		<?php
				                        	}
			                        	}
			                        	?>
			                        </tbody>
			                    </table>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Upload More Photos</label>
							<div class="col-sm-8">
								<input type="file" name="photo[]" id="galleryPhotosInput" multiple accept="image/*" style="display:none;">
								<div class="sn-multi-upload-box" id="galleryDropzone" onclick="document.getElementById('galleryPhotosInput').click();">
									<i class="fa fa-cloud-upload" style="font-size: 28px; color: #3b82f6; margin-bottom: 6px;"></i>
									<div style="font-weight: 600; font-size: 14px; color: #1e293b;">Click or Drag &amp; Drop Multiple Photos Here</div>
									<div style="font-size: 12px; color: #64748b; margin-top: 2px;">Select multiple images at once (PNG, JPG, WebP). Max 30 photos.</div>
									<button type="button" class="btn btn-primary btn-sm" style="margin-top: 10px; border-radius: 6px; pointer-events: none;">
										<i class="fa fa-plus"></i> Select Multiple Photos
									</button>
								</div>
								<div id="galleryPreviewContainer" class="sn-gallery-preview-grid" style="display: none;"></div>
								<div id="galleryCountNotice" style="display: none; font-size: 12px; color: #16a34a; font-weight: 600; margin-top: 8px;"></div>
							</div>
						</div>
						<script>
						(function() {
							var fileInput = document.getElementById('galleryPhotosInput');
							var dropzone = document.getElementById('galleryDropzone');
							var previewContainer = document.getElementById('galleryPreviewContainer');
							var countNotice = document.getElementById('galleryCountNotice');
							if (!fileInput || !dropzone) return;

							var dt = new DataTransfer();

							function updatePreviews() {
								previewContainer.innerHTML = '';
								if (dt.files.length === 0) {
									previewContainer.style.display = 'none';
									countNotice.style.display = 'none';
									fileInput.files = dt.files;
									return;
								}

								previewContainer.style.display = 'flex';
								countNotice.style.display = 'block';
								countNotice.textContent = dt.files.length + ' new photo(s) selected for upload.';

								Array.from(dt.files).forEach(function(file, index) {
									var item = document.createElement('div');
									item.className = 'sn-gallery-preview-item';

									var img = document.createElement('img');
									img.src = URL.createObjectURL(file);
									item.appendChild(img);

									var removeBtn = document.createElement('span');
									removeBtn.className = 'sn-preview-remove';
									removeBtn.innerHTML = '&times;';
									removeBtn.title = 'Remove photo';
									removeBtn.onclick = function(e) {
										e.stopPropagation();
										var newDt = new DataTransfer();
										Array.from(dt.files).forEach(function(f, i) {
											if (i !== index) newDt.items.add(f);
										});
										dt = newDt;
										fileInput.files = dt.files;
										updatePreviews();
									};
									item.appendChild(removeBtn);
									previewContainer.appendChild(item);
								});

								fileInput.files = dt.files;
							}

							fileInput.addEventListener('change', function() {
								Array.from(fileInput.files).forEach(function(f) {
									dt.items.add(f);
								});
								updatePreviews();
							});

							['dragenter', 'dragover'].forEach(function(ev) {
								dropzone.addEventListener(ev, function(e) {
									e.preventDefault();
									e.stopPropagation();
									dropzone.classList.add('dragover');
								});
							});

							['dragleave', 'drop'].forEach(function(ev) {
								dropzone.addEventListener(ev, function(e) {
									e.preventDefault();
									e.stopPropagation();
									dropzone.classList.remove('dragover');
								});
							});

							dropzone.addEventListener('drop', function(e) {
								if (e.dataTransfer && e.dataTransfer.files.length > 0) {
									Array.from(e.dataTransfer.files).forEach(function(f) {
										if (f.type.startsWith('image/')) {
											dt.items.add(f);
										}
									});
									updatePreviews();
								}
							});
						})();
						</script>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">
								Description
								<span class="sn-ai-inline-btn-group">
									<button type="button" class="sn-ai-inline-btn" data-field="description" data-editor="editor1" title="Write description with AI">
										<i class="fa fa-magic"></i> AI Write
									</button>
									<button type="button" class="sn-ai-inline-btn secondary" data-field="description" data-action="polish" data-editor="editor1" title="Polish and improve description">
										<i class="fa fa-pencil"></i> Polish
									</button>
								</span>
							</label>
							<div class="col-sm-8">
								<textarea name="p_description" class="form-control" cols="30" rows="10" id="editor1"><?php echo $p_description; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">
								Short Description
								<span class="sn-ai-inline-btn-group">
									<button type="button" class="sn-ai-inline-btn" data-field="short_description" data-editor="editor2" title="Write short description with AI">
										<i class="fa fa-magic"></i> AI Write
									</button>
								</span>
							</label>
							<div class="col-sm-8">
								<textarea name="p_short_description" class="form-control" cols="30" rows="10" id="editor2"><?php echo $p_short_description; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">
								Features
								<span class="sn-ai-inline-btn-group">
									<button type="button" class="sn-ai-inline-btn" data-field="feature" data-editor="editor3" title="Write features & specifications with AI">
										<i class="fa fa-magic"></i> AI Write
									</button>
								</span>
							</label>
							<div class="col-sm-8">
								<textarea name="p_feature" class="form-control" cols="30" rows="10" id="editor3"><?php echo $p_feature; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">
								Conditions
								<span class="sn-ai-inline-btn-group">
									<button type="button" class="sn-ai-inline-btn" data-field="condition" data-editor="editor4" title="Write conditions & warranty with AI">
										<i class="fa fa-magic"></i> AI Write
									</button>
								</span>
							</label>
							<div class="col-sm-8">
								<textarea name="p_condition" class="form-control" cols="30" rows="10" id="editor4"><?php echo $p_condition; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">
								Return Policy
								<span class="sn-ai-inline-btn-group">
									<button type="button" class="sn-ai-inline-btn" data-field="return_policy" data-editor="editor5" title="Write return policy with AI">
										<i class="fa fa-magic"></i> AI Write
									</button>
								</span>
							</label>
							<div class="col-sm-8">
								<textarea name="p_return_policy" class="form-control" cols="30" rows="10" id="editor5"><?php echo $p_return_policy; ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Product Video Link</label>
							<div class="col-sm-8">
								<input type="text" name="p_video_link" class="form-control" value="<?php echo htmlspecialchars($p_video_link ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=... or https://...r2.dev/video.mp4">
								<small class="text-muted">Supports YouTube video links or Cloudflare R2 / direct MP4 object storage URLs (comma or newline separated for multiple videos).</small>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Coupon Code / Promotion</label>
							<div class="col-sm-4">
								<select name="coupon_code" class="form-control select2" style="width:100%;">
									<option value="">-- No Special Coupon --</option>
									<?php
									try {
										$cpStmt = $pdo->prepare("SELECT coupon_code, discount_type, discount_value, status FROM tbl_coupon WHERE status = 'active' ORDER BY coupon_id DESC");
										$cpStmt->execute();
										$couponList = $cpStmt->fetchAll(PDO::FETCH_ASSOC);
										foreach ($couponList as $cp) {
											$discLabel = ($cp['discount_type'] === 'percentage') ? $cp['discount_value'] . '%' : '$' . $cp['discount_value'];
											$sel = (isset($coupon_code) && $coupon_code === $cp['coupon_code']) ? 'selected' : '';
											echo '<option value="' . htmlspecialchars($cp['coupon_code']) . '" ' . $sel . '>' . htmlspecialchars($cp['coupon_code']) . ' (' . $discLabel . ' off)</option>';
										}
									} catch (Throwable $_) {}
									?>
								</select>
								<small class="text-muted">Link this product with an active discount coupon. Coupons can be managed in <a href="coupons.php" target="_blank">Coupons</a>.</small>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Featured?</label>
							<div class="col-sm-8">
								<select name="p_is_featured" class="form-control" style="width:auto;">
									<option value="0" <?php if($p_is_featured == '0'){echo 'selected';} ?>>No</option>
									<option value="1" <?php if($p_is_featured == '1'){echo 'selected';} ?>>Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label">Is Active?</label>
							<div class="col-sm-8">
								<select name="p_is_active" class="form-control" style="width:auto;">
									<option value="0" <?php if($p_is_active == '0'){echo 'selected';} ?>>No</option>
									<option value="1" <?php if($p_is_active == '1'){echo 'selected';} ?>>Yes</option>
								</select> 
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-3 control-label"></label>
							<div class="col-sm-6">
								<button type="submit" class="btn btn-success pull-left" name="form1">Update</button>
							</div>
						</div>
					</div>
				</div>

			</form>


		</div>
	</div>

</section>

<?php require_once('footer.php'); ?>