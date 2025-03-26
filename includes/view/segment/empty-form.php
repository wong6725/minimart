<?php 
if ( !defined("ABSPATH") ) exit;

?>

<form id="{id}" class="needValidate  " action="" method="post" data-token="{token}" data-hook="{hook}" novalidate="novalidate">
	<div class="inner-content">
		{content}
	</div>
	<input type="hidden" name="action" value="{action}">
</form>