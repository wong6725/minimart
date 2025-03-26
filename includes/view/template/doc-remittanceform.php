<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php $header = [ 'title'=>'REMITTANCE FORM ', 'docno'=>$args['heading']['docno'] ]; ?>
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
			margin: 20px 20px 20px 20px;
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
			echo "<br><hr><br>";
		}
		?>
		<div id="header"> 
		<?php
			$heading = $args['heading'];
			do_action( 'wcwh_get_template', 'template/doc-remittanceform-heading.php', $args['heading'] );
		?>
		</div>
		<div id="content" style="width:100%;">
			<table border=0 width="100%" style="border-spacing: 8px 8px;">
				<tbody>
					<tr>
						<th class="leftered" scope="col" width="29%" valign="bottom" colspan="2">
							NAMA PENERIMA (Beneficiary Name)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( $detail['account_holder'] )? $detail['account_holder'] : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="29%" valign="bottom" colspan="2">
							BANK PENERIMA (Beneficiary Bank)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( $detail['bank'] )? $detail['bank'] : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="29%" valign="bottom" colspan="2">
							NOMBOR REKENING (Bank Account No.)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( $detail['account_no'] )? implode("-", str_split($detail['account_no'], 4)) : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="29%" valign="bottom" colspan="2">
							UANG DI KIRIM (Money to Send)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom">  </td>
						<td class="td td-offt td-offb td-offr td-offl leftered font14 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="29%" valign="bottom" colspan="2">
							&nbsp;&nbsp;&nbsp;
							&nbsp;&nbsp;&nbsp;
							a.	UANG DI KIRIM (Amount)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font12 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( $detail['convert_amount'] )? $from_currency." ".round_to( $detail['amount'], 2, 1, 1)." &times ".$detail['exchange_rate']." = ".$to_currency." ".round_to( $detail['convert_amount'], 2, 1, 1 )  : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="29%" valign="bottom" colspan="2">
							&nbsp;&nbsp;&nbsp;
							&nbsp;&nbsp;&nbsp;
							b.	CAJ PERKHIDMATAN (Service Charge)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( $detail['service_charge'] )? $from_currency." ".round_to( $detail['service_charge'], 2, 1, 1) : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered font18" scope="col" width="29%" valign="bottom" colspan="2">
							&nbsp;&nbsp;&nbsp;
							&nbsp;&nbsp;&nbsp;
							JUMLAH (Total)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font18 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( !empty($detail['amount']) && !empty($detail['service_charge']) )? $from_currency." ".round_to(($detail['amount']+$detail['service_charge']), 2, 1, 1) : '<br>' ?>
						</td>
					</tr>
					<tr>
						<td colspan="3">
							<br><br><br>
						</td>
						<td class="rightered font14 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( $detail['doc_date'] )? date('Y-m-d',strtotime($detail['doc_date'])) : '<br>' ?>	
						</td>
					</tr>
					<tr>
						<th class="td td-offb td-offr td-offl leftered font10" scope="col" valign="top" width="20%">
							(TANDATANGAN PENGIRIM)<br>(Sender Signature)
						</th>
						<th class="td td-offb td-offr td-offl leftered" scope="col" valign="top" width="20%">
							<label class="font10">NAME:</label> <br><br><label class="font10">(TANDATANGAN SAKSI 1)<br>(Evidence Signature)</label>
						</th>
						<th class="td td-offb td-offr td-offl leftered" scope="col" valign="top" width="20%">
							<label class="font10">NAME:</label> <br><br><label class="font10">(TANDATANGAN SAKSI 2)<br>(Witness Signature)</label>
						</th>
						<th class="td td-offb td-offr td-offl rightered" scope="col" valign="top" colspan="2">
							TANGGAL<br>(Date)
						</th>
					</tr>
					
					<tr>
						<th class="leftered" scope="col" width="29%" valign="bottom" colspan="2">
							NAMA PENGIRIM (Sender Name)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( $detail['sender_name'] )? $detail['sender_name'] : '<br>' ?>
						</td>
					</tr>
					<tr>
						<th class="leftered" scope="col" width="29%" valign="bottom" colspan="2">
							NOMBOR H/P PENGIRIM (H/P No. of Sender Name)
						</th>
						<td class="rightered" scope="col" width="1%" valign="bottom"> : </td>
						<td class="td td-offt td-offr td-offl leftered font14 font-weight-bold" scope="col" width="40%" valign="bottom" colspan="2">
							<?php echo ( $detail['sender_contact'] )? $detail['sender_contact'] : '<br>' ?>
						</td>
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