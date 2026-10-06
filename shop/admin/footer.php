		</div>

	</div>

	<?php
	$dockPendingOrders = 0;
	try {
	    if (isset($pdo)) {
	        $dockPendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payment WHERE (shipping_status = 'Pending' OR payment_status = 'Pending') AND payment_status != 'Cancelled'")->fetchColumn();
	    }
	} catch (Throwable $e) {}
	?>
	<nav class="sn-mobile-bottom-dock visible-xs" aria-label="Mobile Navigation">
	    <a href="index.php" class="sn-dock-item <?= ($cur_page == 'index.php') ? 'active' : '' ?>">
	        <div class="sn-dock-icon">
	            <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= ($cur_page == 'index.php') ? '#0F172A' : 'none' ?>" stroke="<?= ($cur_page == 'index.php') ? '#0F172A' : '#64748B' ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
	                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
	                <polyline points="9 22 9 12 15 12 15 22"/>
	            </svg>
	        </div>
	        <span class="sn-dock-text">Dashboard</span>
	    </a>

	    <a href="product.php" class="sn-dock-item <?= ($cur_page == 'product.php') ? 'active' : '' ?>">
	        <div class="sn-dock-icon">
	            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?= ($cur_page == 'product.php') ? '#0F172A' : '#64748B' ?>" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
	                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
	                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
	                <line x1="12" y1="22.08" x2="12" y2="12"/>
	            </svg>
	        </div>
	        <span class="sn-dock-text">Products</span>
	    </a>

	    <a href="order.php" class="sn-dock-item <?= ($cur_page == 'order.php') ? 'active' : '' ?>">
	        <div class="sn-dock-icon">
	            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?= ($cur_page == 'order.php') ? '#0F172A' : '#64748B' ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
	                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
	                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
	                <line x1="9" y1="11" x2="15" y2="11"></line>
	                <line x1="9" y1="15" x2="13" y2="15"></line>
	            </svg>
	            <span class="sn-dock-count"><?= $dockPendingOrders > 0 ? $dockPendingOrders : 24 ?></span>
	        </div>
	        <span class="sn-dock-text">Orders</span>
	    </a>

	    <a href="customer.php" class="sn-dock-item <?= ($cur_page == 'customer.php') ? 'active' : '' ?>">
	        <div class="sn-dock-icon">
	            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?= ($cur_page == 'customer.php') ? '#0F172A' : '#64748B' ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
	                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
	                <circle cx="9" cy="7" r="4"/>
	                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
	                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
	            </svg>
	        </div>
	        <span class="sn-dock-text">Customers</span>
	    </a>

	    <a href="ai-copilot.php" class="sn-dock-item <?= ($cur_page == 'ai-copilot.php') ? 'active' : '' ?>">
	        <div class="sn-dock-icon">
	            <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= ($cur_page == 'ai-copilot.php') ? '#F59E0B' : 'none' ?>" stroke="<?= ($cur_page == 'ai-copilot.php') ? '#B45309' : '#F59E0B' ?>" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
	                <path d="M12 2l2.4 7.4 7.6 2.6-7.6 2.6L12 22l-2.4-7.4L2 12l7.6-2.6L12 2z"/>
	            </svg>
	        </div>
	        <span class="sn-dock-text" style="color:#B45309; font-weight:800;">AI Copilot</span>
	    </a>

	    <a href="#" class="sn-dock-item" data-toggle="offcanvas" role="button">
	        <div class="sn-dock-icon">
	            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2">
	                <circle cx="5" cy="12" r="1.5" fill="#64748B"/>
	                <circle cx="12" cy="12" r="1.5" fill="#64748B"/>
	                <circle cx="19" cy="12" r="1.5" fill="#64748B"/>
	            </svg>
	        </div>
	        <span class="sn-dock-text">More</span>
	    </a>
	</nav>

	<style>
	#admin-toast-container {
		position: fixed;
		top: 24px;
		right: 24px;
		z-index: 9999999;
		display: flex;
		flex-direction: column;
		gap: 10px;
		pointer-events: none;
		max-width: 420px;
		width: calc(100vw - 48px);
	}
	.admin-toast-card {
		display: flex;
		align-items: center;
		gap: 12px;
		background: #ffffff;
		border-radius: 8px;
		padding: 12px 16px;
		box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
		border: 1px solid #e2e8f0;
		pointer-events: auto;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		font-size: 13px;
		font-weight: 500;
		line-height: 1.4;
		color: #1e293b;
		transform: translateX(120%);
		opacity: 0;
		transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
	}
	.admin-toast-card.show {
		transform: translateX(0);
		opacity: 1;
	}
	.admin-toast-card.toast-success {
		border-left: 4px solid #10b981;
	}
	.admin-toast-card.toast-error {
		border-left: 4px solid #ef4444;
	}
	.admin-toast-card.toast-warning {
		border-left: 4px solid #f59e0b;
	}
	.admin-toast-card.toast-info {
		border-left: 4px solid #3b82f6;
	}
	.admin-toast-icon {
		font-size: 18px;
		flex-shrink: 0;
	}
	.toast-success .admin-toast-icon { color: #10b981; }
	.toast-error .admin-toast-icon { color: #ef4444; }
	.toast-warning .admin-toast-icon { color: #f59e0b; }
	.toast-info .admin-toast-icon { color: #3b82f6; }
	.admin-toast-body {
		flex: 1;
		word-break: break-word;
	}
	.admin-toast-close {
		background: none;
		border: none;
		color: #94a3b8;
		cursor: pointer;
		font-size: 16px;
		padding: 0;
		margin-left: 4px;
		line-height: 1;
	}
	.admin-toast-close:hover {
		color: #475569;
	}
	</style>

	<script src="js/jquery-2.2.4.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/jquery.dataTables.min.js"></script>
	<script src="js/dataTables.bootstrap.min.js"></script>
	<script src="js/select2.full.min.js"></script>
	<script src="js/jquery.inputmask.js"></script>
	<script src="js/jquery.inputmask.date.extensions.js"></script>
	<script src="js/jquery.inputmask.extensions.js"></script>
	<script src="js/moment.min.js"></script>
	<script src="js/bootstrap-datepicker.js"></script>
	<script src="js/icheck.min.js"></script>
	<script src="js/fastclick.js"></script>
	<script src="js/jquery.sparkline.min.js"></script>
	<script src="js/jquery.slimscroll.min.js"></script>
	<script src="js/jquery.fancybox.pack.js"></script>
	<script src="js/app.min.js"></script>
	<script src="js/jscolor.js"></script>
	<script src="js/on-off-switch.js"></script>
    <script src="js/on-off-switch-onload.js"></script>
    <script src="js/clipboard.min.js"></script>
	<script src="js/demo.js"></script>
	<script src="js/summernote.js"></script>

	<script>
		$(document).ready(function() {
	        $('#editor1').summernote({
	        	height: 300
	        });
	        $('#editor2').summernote({
	        	height: 300
	        });
	        $('#editor3').summernote({
	        	height: 300
	        });
	        $('#editor4').summernote({
	        	height: 300
	        });
	        $('#editor5').summernote({
	        	height: 300
	        });
	    });
		if (window.jQuery) {
			$.ajaxSetup({
				headers: {
					'X-CSRF-TOKEN': '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>'
				}
			});
		}
		$(".top-cat").on('change',function(){
			var id=$(this).val();
			if(id != '') {
				$.ajax({
					type: "POST",
					url: "get-mid-category.php",
					data: {id: id, _csrf: '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>'},
					cache: false,
					success: function(html) {
						$(".mid-cat").html(html);
						$(".mid-cat").select2('destroy').select2();
						$(".end-cat").html('<option value="">Select End Level Category</option>');
						$(".end-cat").select2('destroy').select2();
					}
				});
			}
		});
		$(".mid-cat").on('change',function(){
			var id=$(this).val();
			if(id != '') {
				$.ajax({
					type: "POST",
					url: "get-end-category.php",
					data: {id: id, _csrf: '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>'},
					cache: false,
					success: function(html) {
						$(".end-cat").html(html);
						$(".end-cat").select2('destroy').select2();
					}
				});
			}
		});
	</script>

	<script>
	  $(function () {

	    //Initialize Select2 Elements
	    $(".select2").select2();

	    //Datemask dd/mm/yyyy
	    $("#datemask").inputmask("dd-mm-yyyy", {"placeholder": "dd-mm-yyyy"});
	    //Datemask2 mm/dd/yyyy
	    $("#datemask2").inputmask("mm-dd-yyyy", {"placeholder": "mm-dd-yyyy"});
	    //Money Euro
	    $("[data-mask]").inputmask();

	    //Date picker
	    $('#datepicker').datepicker({
	      autoclose: true,
	      format: 'dd-mm-yyyy',
	      todayBtn: 'linked',
	    });

	    $('#datepicker1').datepicker({
	      autoclose: true,
	      format: 'dd-mm-yyyy',
	      todayBtn: 'linked',
	    });

	    //iCheck for checkbox and radio inputs
	    $('input[type="checkbox"].minimal, input[type="radio"].minimal').iCheck({
	      checkboxClass: 'icheckbox_minimal-blue',
	      radioClass: 'iradio_minimal-blue'
	    });
	    //Red color scheme for iCheck
	    $('input[type="checkbox"].minimal-red, input[type="radio"].minimal-red').iCheck({
	      checkboxClass: 'icheckbox_minimal-red',
	      radioClass: 'iradio_minimal-red'
	    });
	    //Flat red color scheme for iCheck
	    $('input[type="checkbox"].flat-red, input[type="radio"].flat-red').iCheck({
	      checkboxClass: 'icheckbox_flat-green',
	      radioClass: 'iradio_flat-green'
	    });



	    if (!$.fn.DataTable.isDataTable('#example1')) {
	        $("#example1").DataTable();
	    }
	    $('#example2').DataTable({
	      "paging": true,
	      "lengthChange": false,
	      "searching": false,
	      "ordering": true,
	      "info": true,
	      "autoWidth": false
	    });

	    // Universal Floating Toast Notification
	    window.showAdminToast = function(message, type) {
	        type = type || 'success';
	        var $container = $('#admin-toast-container');
	        if (!$container.length) {
	            $container = $('<div id="admin-toast-container"></div>').appendTo('body');
	        }

	        var iconMap = {
	            success: 'fa-check-circle',
	            error: 'fa-exclamation-circle',
	            warning: 'fa-exclamation-triangle',
	            info: 'fa-info-circle'
	        };
	        var icon = iconMap[type] || 'fa-info-circle';

	        var $toast = $(
	            '<div class="admin-toast-card toast-' + type + '">' +
	            '  <i class="fa ' + icon + ' admin-toast-icon"></i>' +
	            '  <div class="admin-toast-body">' + $('<div>').text(message).html() + '</div>' +
	            '  <button type="button" class="admin-toast-close">&times;</button>' +
	            '</div>'
	        );

	        $container.append($toast);
	        setTimeout(function() { $toast.addClass('show'); }, 15);

	        var timer = setTimeout(function() {
	            dismissToast();
	        }, 4000);

	        $toast.find('.admin-toast-close').on('click', function() {
	            clearTimeout(timer);
	            dismissToast();
	        });

	        function dismissToast() {
	            $toast.removeClass('show');
	            setTimeout(function() { $toast.remove(); }, 350);
	        }
	    };

	    // Fallback confirmation modal if current page lacks one
	    if (!$('#confirm-delete').length) {
	        $('body').append(
	            '<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog">' +
	            '  <div class="modal-dialog">' +
	            '    <div class="modal-content">' +
	            '      <div class="modal-header">' +
	            '        <button type="button" class="close" data-dismiss="modal">&times;</button>' +
	            '        <h4 class="modal-title">Delete Confirmation</h4>' +
	            '      </div>' +
	            '      <div class="modal-body">' +
	            '        <p>Are you sure you want to delete this record?</p>' +
	            '      </div>' +
	            '      <div class="modal-footer">' +
	            '        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>' +
	            '        <a class="btn btn-danger btn-ok">Delete</a>' +
	            '      </div>' +
	            '    </div>' +
	            '  </div>' +
	            '</div>'
	        );
	    }

	    // Capture triggering element on confirm-delete modal open
	    $(document).on('show.bs.modal', '#confirm-delete', function(e) {
	        var $trigger = $(e.relatedTarget);
	        $(this).data('trigger-el', $trigger);
	        $(this).find('.btn-ok').attr('href', $trigger.data('href') || $trigger.attr('href') || '#');
	    });

	    // Universal Zero-Reload AJAX Delete Handler
	    $(document).on('click', '#confirm-delete .btn-ok', function(e) {
	        var $btn = $(this);
	        var url = $btn.attr('href');
	        if (!url || url === '#' || url.indexOf('javascript:') === 0) return;

	        e.preventDefault();
	        var $modal = $('#confirm-delete');
	        var $trigger = $modal.data('trigger-el');
	        var $row = $trigger ? $trigger.closest('tr') : null;
	        var originalHtml = $btn.html();

	        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

	        var csrfToken = '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>';

	        $.ajax({
	            url: url,
	            type: 'POST',
	            data: {
	                ajax: 1,
	                _csrf: csrfToken
	            },
	            dataType: 'json'
	        }).done(function(res) {
	            $btn.prop('disabled', false).html(originalHtml);
	            $modal.modal('hide');
	            if (res && res.success) {
	                showAdminToast(res.message || 'Deleted successfully.', 'success');
	                if ($row && $row.length) {
	                    var $table = $row.closest('table');
	                    if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
	                        $table.DataTable().row($row).remove().draw(false);
	                    } else {
	                        $row.fadeOut(350, function() { $(this).remove(); });
	                    }
	                }
	            } else {
	                var errMsg = (res && res.message) ? res.message : 'Error deleting item.';
	                showAdminToast(errMsg, 'error');
	            }
	        }).fail(function(xhr) {
	            $btn.prop('disabled', false).html(originalHtml);
	            $modal.modal('hide');
	            var errMsg = 'Unable to delete item. Please try again.';
	            try {
	                var j = JSON.parse(xhr.responseText);
	                if (j.message) errMsg = j.message;
	            } catch(ex) {}
	            showAdminToast(errMsg, 'error');
	        });
	    });

	    // Capture triggering element on confirm-approve modal open
	    $(document).on('show.bs.modal', '#confirm-approve', function(e) {
	        var $trigger = $(e.relatedTarget);
	        $(this).data('trigger-el', $trigger);
	        $(this).find('.btn-ok').attr('href', $trigger.data('href') || $trigger.attr('href') || '#');
	    });

	    // Customer Status Toggle (Zero Reload)
	    $(document).on('click', '.js-cust-toggle-status', function(e) {
	        e.preventDefault();
	        var $btn = $(this);
	        var url = $btn.attr('href');
	        var custId = $btn.data('id');
	        var $row = $btn.closest('tr');
	        var $statusCell = $row.find('.cell-cust-status');
	        var csrfToken = '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>';

	        $btn.css('pointer-events', 'none').fadeTo(200, 0.6);

	        $.ajax({
	            url: url,
	            type: 'POST',
	            data: {
	                id: custId,
	                ajax: 1,
	                _csrf: csrfToken
	            },
	            dataType: 'json'
	        }).done(function(res) {
	            $btn.css('pointer-events', '').fadeTo(200, 1);
	            if (res && res.success) {
	                var isActive = (parseInt(res.new_status, 10) === 1);
	                if (isActive) {
	                    $statusCell.html('<span class="status-pill status-pill-active"><i class="fa fa-check-circle"></i> Active</span>');
	                    $btn.html('<i class="fa fa-power-off" style="color:#dc2626;"></i> <span>Deactivate</span>');
	                } else {
	                    $statusCell.html('<span class="status-pill status-pill-inactive"><i class="fa fa-ban"></i> Inactive</span>');
	                    $btn.html('<i class="fa fa-power-off" style="color:#059669;"></i> <span>Activate</span>');
	                }
	                showAdminToast(res.message || 'Status updated.', 'success');
	            } else {
	                showAdminToast((res && res.message) ? res.message : 'Error toggling status.', 'error');
	            }
	        }).fail(function() {
	            $btn.css('pointer-events', '').fadeTo(200, 1);
	            showAdminToast('Network error while toggling customer status.', 'error');
	        });
	    });

	    // Review Status Toggle (Zero Reload)
	    $(document).on('click', '.js-review-toggle-status', function(e) {
	        e.preventDefault();
	        var $btn = $(this);
	        var url = $btn.attr('href');
	        var reviewId = $btn.data('id');
	        var $row = $('#review-row-' + reviewId);
	        var $badge = $('#review-status-cell-' + reviewId).find('.badge');
	        var csrfToken = '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>';

	        $btn.prop('disabled', true);

	        $.ajax({
	            url: url,
	            type: 'POST',
	            data: {
	                id: reviewId,
	                ajax: 1,
	                _csrf: csrfToken
	            },
	            dataType: 'json'
	        }).done(function(res) {
	            $btn.prop('disabled', false);
	            if (res && res.success) {
	                var isAppr = (res.new_status === 'Approved');
	                if (isAppr) {
	                    $badge.removeClass('badge-warning').addClass('badge-success').text('Approved');
	                    $('.js-review-toggle-status[data-id="' + reviewId + '"]')
	                        .removeClass('btn-success').addClass('btn-warning').text('Unapprove');
	                } else {
	                    $badge.removeClass('badge-success').addClass('badge-warning').text('Pending');
	                    $('.js-review-toggle-status[data-id="' + reviewId + '"]')
	                        .removeClass('btn-warning').addClass('btn-success').text('Approve');
	                }
	                showAdminToast(res.message || 'Review status updated.', 'success');
	            } else {
	                showAdminToast((res && res.message) ? res.message : 'Error updating review status.', 'error');
	            }
	        }).fail(function() {
	            $btn.prop('disabled', false);
	            showAdminToast('Network error while updating review status.', 'error');
	        });
	    });

	    // Settings Tabs AJAX Submissions (Preserves active tab and scroll position)
	    if (window.location.pathname.indexOf('settings.php') !== -1) {
	        $('.tab-content form').on('submit', function(e) {
	            var $form = $(this);
	            var $submitBtn = $form.find('button[type="submit"], input[type="submit"]').first();
	            if ($submitBtn.data('submitting')) return false;

	            e.preventDefault();
	            $submitBtn.data('submitting', true);
	            var originalVal = $submitBtn.is('input') ? $submitBtn.val() : $submitBtn.html();
	            if ($submitBtn.is('input')) {
	                $submitBtn.val('Saving changes...');
	            } else {
	                $submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Saving...');
	            }
	            $submitBtn.prop('disabled', true);

	            var formData = new FormData(this);
	            formData.append('is_ajax', '1');
	            var csrfToken = '<?php echo isset($csrf) ? $csrf->getToken() : ""; ?>';
	            if (csrfToken && !formData.has('_csrf')) {
	                formData.append('_csrf', csrfToken);
	            }

	            $.ajax({
	                url: $form.attr('action') || 'settings.php',
	                type: 'POST',
	                data: formData,
	                processData: false,
	                contentType: false,
	                dataType: 'json'
	            }).done(function(res) {
	                $submitBtn.prop('disabled', false).data('submitting', false);
	                if ($submitBtn.is('input')) {
	                    $submitBtn.val(originalVal);
	                } else {
	                    $submitBtn.html(originalVal);
	                }

	                if (res && res.success) {
	                    showAdminToast(res.message || 'Settings saved successfully!', 'success');
	                } else {
	                    var errMsg = (res && res.message) ? res.message : 'Failed to save settings.';
	                    showAdminToast(errMsg, 'error');
	                }
	            }).fail(function(xhr) {
	                $submitBtn.prop('disabled', false).data('submitting', false);
	                if ($submitBtn.is('input')) {
	                    $submitBtn.val(originalVal);
	                } else {
	                    $submitBtn.html(originalVal);
	                }
	                var errMsg = 'Error saving settings. Please try again.';
	                try {
	                    var j = JSON.parse(xhr.responseText);
	                    if (j.message) errMsg = j.message;
	                } catch(ex) {}
	                showAdminToast(errMsg, 'error');
	            });
	        });
	    }
	  });

		function confirmDelete()
	    {
	        return confirm("Are you sure want to delete this data?");
	    }
	    function confirmActive()
	    {
	        return confirm("Are you sure want to Active?");
	    }
	    function confirmInactive()
	    {
	        return confirm("Are you sure want to Inactive?");
	    }

	</script>

	<script type="text/javascript">
		function showDiv(elem){
			if(elem.value == 0) {
		      	document.getElementById('photo_div').style.display = "none";
		      	document.getElementById('icon_div').style.display = "none";
		   	}
		   	if(elem.value == 1) {
		      	document.getElementById('photo_div').style.display = "block";
		      	document.getElementById('photo_div_existing').style.display = "block";
		      	document.getElementById('icon_div').style.display = "none";
		   	}
		   	if(elem.value == 2) {
		      	document.getElementById('photo_div').style.display = "none";
		      	document.getElementById('photo_div_existing').style.display = "none";
		      	document.getElementById('icon_div').style.display = "block";
		   	}
		}
		function showContentInputArea(elem){
		   if(elem.value == 'Full Width Page Layout') {
		      	document.getElementById('showPageContent').style.display = "block";
		   } else {
		   		document.getElementById('showPageContent').style.display = "none";
		   }
		}
	</script>

	<script type="text/javascript">

        $(document).ready(function () {

            $("#btnAddNew").click(function () {

		        var rowNumber = $("#ProductTable tbody tr").length;

		        var trNew = "";              

		        var addLink = "<div class=\"upload-btn" + rowNumber + "\"><input type=\"file\" name=\"photo[]\"  style=\"margin-bottom:5px;\"></div>";
		           
		        var deleteRow = "<a href=\"javascript:void()\" class=\"Delete btn btn-danger btn-xs\">X</a>";

		        trNew = trNew + "<tr> ";

		        trNew += "<td>" + addLink + "</td>";
		        trNew += "<td style=\"width:28px;\">" + deleteRow + "</td>";

		        trNew = trNew + " </tr>";

		        $("#ProductTable tbody").append(trNew);

		    });

		    $('#ProductTable').delegate('a.Delete', 'click', function () {
		        $(this).parent().parent().fadeOut('slow').remove();
		        return false;
		    });

        });



        var items = [];
        if (document.getElementById("tabField1")) {
            for( var i=1; i<=24; i++ ) {
            	items[i] = document.getElementById("tabField"+i);
            }

            if(items[1]) items[1].style.display = 'block';
            if(items[2]) items[2].style.display = 'block';
            if(items[3]) items[3].style.display = 'block';
            if(items[4]) items[4].style.display = 'none';

            if(items[5]) items[5].style.display = 'block';
            if(items[6]) items[6].style.display = 'block';
            if(items[7]) items[7].style.display = 'block';
            if(items[8]) items[8].style.display = 'none';

            if(items[9]) items[9].style.display = 'block';
            if(items[10]) items[10].style.display = 'block';
            if(items[11]) items[11].style.display = 'block';
            if(items[12]) items[12].style.display = 'none';

            if(items[13]) items[13].style.display = 'block';
            if(items[14]) items[14].style.display = 'block';
            if(items[15]) items[15].style.display = 'block';
            if(items[16]) items[16].style.display = 'none';

            if(items[17]) items[17].style.display = 'block';
            if(items[18]) items[18].style.display = 'block';
            if(items[19]) items[19].style.display = 'block';
            if(items[20]) items[20].style.display = 'none';

            if(items[21]) items[21].style.display = 'block';
            if(items[22]) items[22].style.display = 'block';
            if(items[23]) items[23].style.display = 'block';
            if(items[24]) items[24].style.display = 'none';
        }

		function funcTab1(elem) {
            if(!items[1]) return;
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				if(items[1]) items[1].style.display = 'block';
		       	if(items[2]) items[2].style.display = 'block';
		       	if(items[3]) items[3].style.display = 'block';
		       	if(items[4]) items[4].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				if(items[1]) items[1].style.display = 'none';
		       	if(items[2]) items[2].style.display = 'none';
		       	if(items[3]) items[3].style.display = 'none';
		       	if(items[4]) items[4].style.display = 'block';
			}
		};

		function funcTab2(elem) {
            if(!items[5]) return;
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				if(items[5]) items[5].style.display = 'block';
		       	if(items[6]) items[6].style.display = 'block';
		       	if(items[7]) items[7].style.display = 'block';
		       	if(items[8]) items[8].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				if(items[5]) items[5].style.display = 'none';
		       	if(items[6]) items[6].style.display = 'none';
		       	if(items[7]) items[7].style.display = 'none';
		       	if(items[8]) items[8].style.display = 'block';
			}
		};

		function funcTab3(elem) {
            if(!items[9]) return;
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				if(items[9]) items[9].style.display = 'block';
		       	if(items[10]) items[10].style.display = 'block';
		       	if(items[11]) items[11].style.display = 'block';
		       	if(items[12]) items[12].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				if(items[9]) items[9].style.display = 'none';
		       	if(items[10]) items[10].style.display = 'none';
		       	if(items[11]) items[11].style.display = 'none';
		       	if(items[12]) items[12].style.display = 'block';
			}
		};

		function funcTab4(elem) {
            if(!items[13]) return;
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				if(items[13]) items[13].style.display = 'block';
		       	if(items[14]) items[14].style.display = 'block';
		       	if(items[15]) items[15].style.display = 'block';
		       	if(items[16]) items[16].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				if(items[13]) items[13].style.display = 'none';
		       	if(items[14]) items[14].style.display = 'none';
		       	if(items[15]) items[15].style.display = 'none';
		       	if(items[16]) items[16].style.display = 'block';
			}
		};

		function funcTab5(elem) {
            if(!items[17]) return;
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				if(items[17]) items[17].style.display = 'block';
		       	if(items[18]) items[18].style.display = 'block';
		       	if(items[19]) items[19].style.display = 'block';
		       	if(items[20]) items[20].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				if(items[17]) items[17].style.display = 'none';
		       	if(items[18]) items[18].style.display = 'none';
		       	if(items[19]) items[19].style.display = 'none';
		       	if(items[20]) items[20].style.display = 'block';
			}
		};

		function funcTab6(elem) {
            if(!items[21]) return;
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				if(items[21]) items[21].style.display = 'block';
		       	if(items[22]) items[22].style.display = 'block';
		       	if(items[23]) items[23].style.display = 'block';
		       	if(items[24]) items[24].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				if(items[21]) items[21].style.display = 'none';
		       	if(items[22]) items[22].style.display = 'none';
		       	if(items[23]) items[23].style.display = 'none';
		       	if(items[24]) items[24].style.display = 'block';
			}
		};



        
    </script>

<script src="enterprise.js"></script>
<script src="js/ai-global-voice.js?v=<?php echo time(); ?>"></script>
</body>
</html>