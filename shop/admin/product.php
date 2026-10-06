<?php require_once __DIR__ . '/inc/guard.php'; ?>
<?php require_once('header.php'); ?>

<?php
try {
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS business_id integer DEFAULT NULL");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_total_view integer DEFAULT 0");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS cust_id integer DEFAULT 0");
    $pdo->exec("ALTER TABLE tbl_product ADD COLUMN IF NOT EXISTS p_video_link text DEFAULT ''");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_businesses (business_id SERIAL PRIMARY KEY, business_name varchar(255), owner_user_id integer)");
} catch (Throwable $e) {}

$cat_list = [];
try {
    $catStmt = $pdo->query("SELECT DISTINCT tcat_name FROM tbl_top_category WHERE tcat_name IS NOT NULL AND tcat_name != '' ORDER BY tcat_name ASC");
    $cat_list = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

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
?>

<section class="content-header">
	<div class="sn-page-header">
		<div>
			<h1 class="sn-page-title">Products</h1>
			<p class="sn-page-subtitle">Manage your store products, add new items, update stock and prices.</p>
		</div>
		<div>
			<a href="product-add.php" class="sn-btn-add-product">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
				Add Product
			</a>
		</div>
	</div>
</section>

<section class="content">
	<!-- Filter & Search Toolbar -->
	<div class="sn-product-toolbar">
		<div class="sn-toolbar-search-box">
			<svg class="sn-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
				<circle cx="11" cy="11" r="8"></circle>
				<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
			</svg>
			<input type="text" id="snCustomSearch" class="sn-search-input" placeholder="Search by product name, category, or SKU...">
		</div>

		<div class="sn-toolbar-filters">
			<!-- Category Filter Dropdown -->
			<div class="dropdown sn-filter-dropdown-wrap">
				<button class="sn-filter-btn dropdown-toggle" type="button" data-toggle="dropdown" id="btnFilterCategory">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
					<span id="selectedCategoryLabel">All Categories</span>
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<ul class="dropdown-menu sn-filter-menu" id="categoryFilterMenu">
					<li><a href="#" data-value="">All Categories</a></li>
					<?php foreach ($cat_list as $cat_name): ?>
						<li><a href="#" data-value="<?php echo htmlspecialchars($cat_name); ?>"><?php echo htmlspecialchars($cat_name); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<!-- Status Filter Dropdown -->
			<div class="dropdown sn-filter-dropdown-wrap">
				<button class="sn-filter-btn dropdown-toggle" type="button" data-toggle="dropdown">
					<span id="selectedStatusLabel">Status</span>
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<ul class="dropdown-menu sn-filter-menu" id="statusFilterMenu">
					<li><a href="#" data-value="">All Status</a></li>
					<li><a href="#" data-value="Yes">Active (Yes)</a></li>
					<li><a href="#" data-value="No">Inactive (No)</a></li>
				</ul>
			</div>

			<!-- Stock Filter Dropdown -->
			<div class="dropdown sn-filter-dropdown-wrap">
				<button class="sn-filter-btn dropdown-toggle" type="button" data-toggle="dropdown">
					<span id="selectedStockLabel">Stock</span>
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<ul class="dropdown-menu sn-filter-menu" id="stockFilterMenu">
					<li><a href="#" data-value="">All Stock</a></li>
					<li><a href="#" data-value="instock">In Stock (&gt;0)</a></li>
					<li><a href="#" data-value="outstock">Out of Stock (0)</a></li>
				</ul>
			</div>

			<!-- View Mode Toggles -->
			<div class="sn-view-toggles">
				<button type="button" class="sn-view-btn active" title="Grid View">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect></svg>
				</button>
				<button type="button" class="sn-view-btn" title="List View">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
				</button>
			</div>
		</div>
	</div>

	<!-- Table Card -->
	<div class="sn-table-card">
		<div class="table-responsive">
			<table id="example1" class="table sn-products-table">
				<thead>
					<tr>
						<th width="32" class="no-sort"><input type="checkbox" id="checkAll" class="sn-checkbox"></th>
						<th width="36">#</th>
						<th width="80">Photo</th>
						<th>Product Name</th>
						<th width="120">Vendor</th>
						<th width="90">Old Price</th>
						<th width="90">(৳) Price</th>
						<th width="75">Quantity</th>
						<th width="75">Featured?</th>
						<th width="75">Active?</th>
						<th width="150">Category</th>
						<th width="100" class="no-sort text-right">Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php
				$i = 0;
				foreach ($result as $row) {
					$i++;
					$photo = !empty($row['p_featured_photo']) ? htmlspecialchars($row['p_featured_photo']) : 'placeholder.svg';
					?>
					<tr>
						<td><input type="checkbox" class="sn-checkbox sn-row-check" value="<?php echo (int)$row['p_id']; ?>"></td>
						<td><?php echo $i; ?></td>
						<td>
							<img src="../assets/uploads/<?php echo $photo; ?>" alt="<?php echo htmlspecialchars($row['p_name']); ?>" class="sn-product-thumb" onerror="this.onerror=null; this.src='../assets/uploads/placeholder.svg';">
						</td>
						<td>
							<a href="product-edit.php?id=<?php echo (int)$row['p_id']; ?>" class="sn-product-title-link">
								<?php echo htmlspecialchars($row['p_name']); ?>
							</a>
						</td>
						<td>
							<?php if (!empty($row['business_name'])): ?>
								<span class="sn-badge-vendor"><?php echo htmlspecialchars($row['business_name']); ?></span>
							<?php else: ?>
								<span class="sn-badge-vendor">Admin Product</span>
							<?php endif; ?>
						</td>
						<td class="sn-td-old-price">
							<div style="font-size:11px; color:#64748B; font-weight:500;">BDT</div>
							<div style="font-size:13.5px; color:#1E293B; font-weight:600;"><?php echo number_format((float)$row['p_old_price'], 2); ?></div>
						</td>
						<td class="sn-td-price">
							<div style="font-size:11px; color:#64748B; font-weight:500;">BDT</div>
							<div style="font-size:13.5px; color:#0F172A; font-weight:700;"><?php echo number_format((float)$row['p_current_price'], 2); ?></div>
						</td>
						<td class="sn-td-qty"><?php echo (int)$row['p_qty']; ?></td>
						<td>
							<?php if ((int)$row['p_is_featured'] === 1): ?>
								<span class="sn-badge-status yes">Yes</span>
							<?php else: ?>
								<span class="sn-badge-status no">No</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ((int)$row['p_is_active'] === 1): ?>
								<span class="sn-badge-status yes">Yes</span>
							<?php else: ?>
								<span class="sn-badge-status no">No</span>
							<?php endif; ?>
						</td>
						<td>
							<div class="sn-cat-hierarchy">
								<?php if (!empty($row['tcat_name'])): ?>
									<div class="sn-cat-top"><?php echo htmlspecialchars($row['tcat_name']); ?></div>
								<?php endif; ?>
								<?php if (!empty($row['mcat_name'])): ?>
									<div class="sn-cat-mid"><?php echo htmlspecialchars($row['mcat_name']); ?></div>
								<?php endif; ?>
								<?php if (!empty($row['ecat_name'])): ?>
									<div class="sn-cat-end"><?php echo htmlspecialchars($row['ecat_name']); ?></div>
								<?php endif; ?>
								<?php if (empty($row['tcat_name']) && empty($row['mcat_name']) && empty($row['ecat_name'])): ?>
									<div class="sn-cat-mid">Uncategorized</div>
								<?php endif; ?>
							</div>
						</td>
						<td>
							<div class="sn-action-stack">
								<a href="product-edit.php?id=<?php echo (int)$row['p_id']; ?>" class="sn-btn-action edit" title="Edit Product">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
									Edit
								</a>
								<a href="#" class="sn-btn-action delete" data-href="product-delete.php?id=<?php echo (int)$row['p_id']; ?>" data-toggle="modal" data-target="#confirm-delete" title="Delete Product">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
									Delete
								</a>
							</div>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</section>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel" style="font-weight:700; color:#0F172A;">Delete Confirmation</h4>
            </div>
            <div class="modal-body" style="font-size:14px; color:#475569;">
                <p>Archive this product from the storefront?</p>
                <p style="margin-bottom:0; font-size:13px; color:#94A3B8;">Existing orders and product history will remain available.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:8px; font-weight:600;">Cancel</button>
                <a class="btn btn-danger btn-ok" style="border-radius:8px; font-weight:600;">Delete</a>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>

<script>
$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#example1')) {
        $('#example1').DataTable().destroy();
    }
    
    var productTable = $('#example1').DataTable({
        "order": [[1, "asc"]],
        "pageLength": 10,
        "autoWidth": false,
        "columnDefs": [
            { "orderable": false, "targets": [0, 11] }
        ],
        "language": {
            "info": "Showing _START_ to _END_ of _TOTAL_ products",
            "infoEmpty": "Showing 0 to 0 of 0 products",
            "infoFiltered": "(filtered from _MAX_ total products)",
            "paginate": {
                "previous": "<i class='fa fa-angle-left'></i>",
                "next": "<i class='fa fa-angle-right'></i>"
            }
        }
    });

    // Custom Search Input
    $('#snCustomSearch').on('keyup change', function() {
        productTable.search(this.value).draw();
    });

    // Category Filter Dropdown
    $('#categoryFilterMenu a').on('click', function(e) {
        e.preventDefault();
        var val = $(this).data('value') || '';
        $('#selectedCategoryLabel').text(val ? val : 'All Categories');
        productTable.column(10).search(val).draw();
    });

    // Status Filter Dropdown
    $('#statusFilterMenu a').on('click', function(e) {
        e.preventDefault();
        var val = $(this).data('value') || '';
        var label = $(this).text();
        $('#selectedStatusLabel').text(label);
        if (val) {
            productTable.column(9).search('^' + val + '$', true, false).draw();
        } else {
            productTable.column(9).search('').draw();
        }
    });

    // Stock Filter Dropdown
    var stockFilterType = '';
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            if (!stockFilterType) return true;
            var qty = parseInt(data[7], 10) || 0;
            if (stockFilterType === 'instock') {
                return qty > 0;
            } else if (stockFilterType === 'outstock') {
                return qty <= 0;
            }
            return true;
        }
    );

    $('#stockFilterMenu a').on('click', function(e) {
        e.preventDefault();
        stockFilterType = $(this).data('value') || '';
        $('#selectedStockLabel').text($(this).text());
        productTable.draw();
    });

    // Select All Checkbox
    $('#checkAll').on('change', function() {
        $('.sn-row-check').prop('checked', this.checked);
    });
});
</script>
