<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php $header = [ 'title'=>'REMITTANCE COLLECTOR ', 'docno'=>$args['heading']['docno'] ]; ?>
<?php do_action( 'wcwh_get_template', 'template/doc-header.php', $header ); ?>
	<style>
		body, p, b, a, span, td, th
		{
			font-size:12px;
		}
		hr
		{
			border: 2px dashed black;
		}
		@page 
		{ 
			size: A4;
			margin: 50px 30px 30px 30px;
		}
	</style>

	<?php 
		$detail = $args['detail']; 
		$from_currency = get_woocommerce_currency_symbol($detail['from_currency']);//get_woocommerce_currency_symbol();
		$to_currency = get_woocommerce_currency_symbol($detail['currency']);//get_woocommerce_currency_symbol();
	?>

	<?php
	for ($i=0; $i < 2 ; $i++) 
	{ 
		if($i==1)
		{
			echo "<br><br><hr><br><br><br>";
		}
		?>
		<div id="header"> 
		<?php
			$heading = $args['heading'];
		?>
			<table id="doc_heading" class="text" border="0" cellpadding="0" cellspacing="0" width="100%">
				<tbody>
					<tr>
						<td scope="col" align="left" valign="top" width="60%" style="padding:2px;">
							<table border="0" cellpadding="0" cellspacing="0" width="100%">
								<tr>
									<th align="left" valign="top"><?php echo $heading['company'] ?></th>
								</tr>
								<tr>
									<th align="left" valign="top" class="font18"><?php echo $heading['title'] ?></th>
								</tr>
							</table>
						</td>
						<td scope="col" align="right" valign="top" width="40%" style="padding:2px;">
							<table border="0" cellpadding="0" cellspacing="0" width="100%">
								<tr>
									<th align="right" valign="top">Printed On : </th>
									<th align="left" valign="top"><?php echo $heading['print_on']; ?></th>
								</tr>
								<tr>
									<th align="right" valign="top">Printed By : </th>
									<th align="left" valign="top"><?php echo $heading['print_by'] ?></th>
								</tr>
							</table>
						</td>
					</tr>
				</tbody>
			</table>
			<br/>
		</div>
		<div id="content" style="width:100%;">
			<table border=0 width="100%" style="border-spacing: 8px 8px;">
				<tbody>
					<tr>
						<th class="leftered" scope="col" width="50%" valign="bottom" colspan="2">
							Document No.
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" valign="bottom">
							<?php echo ( $heading['docno'] )? $heading['docno'] : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="50%" valign="bottom" colspan="2">
							Document Date
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" valign="bottom">
							<?php echo ( $heading['doc_date'] )? $heading['doc_date'] : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="50%" valign="bottom" colspan="2">
							Remittance Money Receipts
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" valign="bottom">
							<?php echo ( $detail['from_docno'] && $detail['to_docno'] )? $detail['from_docno']." - ".$detail['to_docno'] : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="50%" valign="bottom" colspan="2">
							Total Receipts
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" valign="bottom">
							<?php echo ( $detail['order_count'] )? $detail['order_count'] : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="50%" valign="bottom" colspan="2">
							Total Remittance Amount (RM)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" valign="bottom">
							<?php echo round_to( $detail['total_amount'] - $detail['service_charge'], 2,1,1 ); ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="50%" valign="bottom" colspan="2">
							Total Service Charge (RM)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" valign="bottom">
							<?php echo round_to( $detail['service_charge'], 2,1,1 ); ?>
						</td>
					</tr>
					<tr>
						<th class="leftered font18" scope="col" width="50%" valign="bottom" colspan="2">
							Total Amount (RM)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font18 font-weight-bold" scope="col" valign="bottom">
							<?php echo round_to( $detail['total_amount'], 2,1,1 ); ?>
						</td>
					</tr>

					<tr>
						<td colspan="4">
							<br><br><br>
						</td>
					</tr>
					<tr>
						<th class="td td-offb td-offr td-offl leftered" scope="col" valign="top" width="30%">
							<label class="font10"><strong>Remittance Money Send By:</strong></label><br><br>
							<label class="font10">Name:</label><br><br>
							<label class="font10">Date:</label><br><br>
						</th>
						<th class="td td-offb td-offr td-offl leftered" scope="col" valign="top" width="30%">
							<label class="font10"><strong>Remittance Money Transit Collector By:</strong></label><br><br>
							<label class="font10">Name:</label><br><br>
							<label class="font10">Date:</label><br><br>
						</th>
						<th></th>
						<th class="td td-offb td-offr td-offl leftered" scope="col" valign="top">
							<label class="font10"><strong>HQ Remittance Money Collector By:</strong></label><br><br>
							<label class="font10">Name:</label><br><br>
							<label class="font10">Date:</label><br><br>
						</th>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
	?>
	
	<!--<div class="page-break"></div>-->

<?php do_action( 'wcwh_get_template', 'template/doc-footer.php' ); ?>

<script>
	window.print();
</script>