<?php
if ( !defined("ABSPATH") ) exit;

?>

<?php if( ! $args['get_content'] ): ?>
<form id="actionRemark" class="" action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="{service}" novalidate >
<?php endif; ?>
    
	<div class="form-row">
        <div class="col form-group">
            <textarea id="remark" class="form-control" name="remark" ></textarea>
            <label for="remark" class="control-label">Remark</label>
        </div>
    </div>
	
    <!-- <input type="hidden" name="id" value="{id}" /> -->

<?php if( ! $args['get_content'] ): ?>
	<!-- <input type="hidden" name="action" value="{action}" /> -->
</form>
<?php endif; ?>