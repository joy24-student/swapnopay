		</div>

	</div>

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

	    $('#confirm-delete').on('show.bs.modal', function(e) {
	      $(this).find('.btn-ok').attr('href', $(e.relatedTarget).data('href'));
	    });
		
		$('#confirm-approve').on('show.bs.modal', function(e) {
	      $(this).find('.btn-ok').attr('href', $(e.relatedTarget).data('href'));
	    });
 
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
</body>
</html>