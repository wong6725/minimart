<?php 
if ( !defined("ABSPATH") ) exit;

?>

<div class="modal modalConfirm main fade" id="wcwhModalConfirm" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable <?php echo ( $args['size'] )? $args['size'] : 'modal-md' ?>" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"></h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-message">
				<h4 class="confirm-message"></h4>
			</div>
			<div class="modal-body">

			</div>
			<div class="modal-footer">
				
			</div>
		</div>
	</div>
	<script type="text/template" class="footer-action-no">
		<button type="button" class="btn btn-sm btn-secondary action-no" data-dismiss="modal">No <i class="fa fa-times" aria-hidden="true"></i></button>
	</script>
	<script type="text/template" class="footer-action-yes">
		<button type="button" class="btn btn-sm btn-primary action-yes">Yes <i class="fa fa-check" aria-hidden="true"></i></button>
	</script>
</div>