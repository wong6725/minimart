<?php 
if ( !defined("ABSPATH") ) exit;

?>

<div class="modal modalReview main fade" id="<?php echo ( $args['id'] )? $args['id'] : 'wcwhModalReview' ?>" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true" data-keyboard="false" data-backdrop="static" >
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable <?php echo ( $args['size'] )? $args['size'] : 'modal-xl' ?>" role="document" style="max-width: none; width: 90%;">
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
	<script type="text/template" class="footer-action-submit">
		<button type="button" class="btn btn-sm btn-primary action-submit">Confirm & Submit <i class="fa fa-level-up-alt" aria-hidden="true"></i></button>
	</script>
</div>
