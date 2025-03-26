<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php $header = [ 'title'=>'Cash Withdrawal', 'docno'=>$args['heading']['docno'] ]; ?>
<?php do_action( 'wcwh_get_template', 'template/doc-header.php', $header ); ?>

	<style>
		body, p, b, a, span, td, th
		{
			font-size:12px;
		}
		@page 
		{ 
			size: A4;
			margin: 30px 30px 30px 30px;
		}

		#body_content #addresses td
		{	
			height:160px; 
			max-height: 160px; 
		}
		#body_content #addresses td table td
		{ 
			height:auto; 
		}
		#body_content #remark
		{
			margin: 0px 2px;
			padding: 2px 6px;
			height:80px;
		}
		#body_content #signature
		{
			padding: 0px 3px;
			font-size: 10px;
		}
	</style>

<?php
	//page operation
	$heading = $args['heading'];
	
	$currency = get_woocommerce_currency();//get_woocommerce_currency_symbol();
?>

	<div id="header"> 
	<?php
		$header['qr'] = $heading['docno'];
		$header['quality'] = 'M';
		$header['size'] = 4.4;
		$header['frame'] = 2;
		do_action( 'wcwh_get_template', 'template/doc-heading.php', $header );
	?>
	<?php
		$heading['first_title'] = 'Withdrawal Information:';
        $heading['second_title'] = 'Document Information:';
		do_action( 'wcwh_get_template', 'template/doc-infos.php', $heading );
	?>
	</div>

	

	<div id="footer"> 
		<p id="remark" class="text td">
			Remarks: <?php echo nl2br( $heading['remark'] ); ?>
		</p>

		<br><br><br><br><br>
		
	</div>
	



<?php do_action( 'wcwh_get_template', 'template/doc-footer.php' ); ?>