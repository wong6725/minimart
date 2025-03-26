<?php 
if ( !defined("ABSPATH") ) exit;

?>

<div class="notice-message notice-<?php echo ( $args['notice_type'] )? $args['notice_type'] : 'info' ?> <?php echo ( $args['dismissable'] )? 'is-dismissible' : ''; ?>">
	<span class="inner"><?php echo $args['message']; ?></span>
</div>