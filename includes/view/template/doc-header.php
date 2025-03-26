<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>
		<?php echo ( $args['title'] )? $args['title'] : 'PDF' ?>
		<?php echo ( $args['docno'] )? $args['docno'] : '' ?>
	</title>
	<style>
	<?php 
		//include global template style/css
		do_action( 'wcwh_get_template', 'template/styles.php' );
	?>
	</style>
	<?php $suffix = ( defined( 'WCWH_DEBUG' ) && WCWH_DEBUG )? '' : ''; ?>
	<link rel="stylesheet" id="wcwh-main-style-css" href="<?php echo WCWH_PLUGIN_URL . "/assets/css/wh-pdf{$suffix}.css"; ?>" media="all">
</head>
	<body id="body_content"> 