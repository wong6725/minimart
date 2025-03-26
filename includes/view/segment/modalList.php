<?php 
if ( !defined("ABSPATH") ) exit;

?>

<div class="modal modalList main fade" id="wcwhModalList" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable <?php echo ( $args['size'] )? $args['size'] : 'modal-xl' ?>" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">

			</div>
			<div class="modal-footer">
				
			</div>
		</div>
	</div>
	<script type="text/template" class="footer-action-close">
		<button type="button" class="btn btn-sm btn-secondary action-close" data-dismiss="modal">Close <i class="fa fa-times" aria-hidden="true"></i></button>
	</script>
	<script type="text/template" class="footer-action-print">
		<a class="btn btn-sm btn-secondary action-print" href="" target="_blank">Print <i class="fa fa-print"></i></a>
	</script>
	<script type="text/template" class="footer-action-export">
		<button type="button" class="btn btn-sm btn-secondary action-export">Export <i class="fa fa-download" aria-hidden="true"></i></button>
	</script>
	<script type="text/template" class="footer-action-email">
		<button type="button" class="btn btn-sm btn-secondary action-email">Email <i class="fa fa-envelope" aria-hidden="true"></i></button>
	</script>
</div>