<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>DELIVERY NOTE <?php echo $args['heading']['docno']; ?></title>
		<style>
		<?php 
			//include global template style/css
			do_action( 'wcwh_get_template', 'template/styles.php' );
		?>
			body, p, b, strong, label, h1, h2, h3, h4, h5, h6, a, span, td, th
			{
				font-family: tahoma !important;
				color: #000 !important;
				letter-spacing:0.8px !important;
			}
			body, p, b, a, span, td, th
			{
				font-size:10px;
			}
			@media print {
	            @page 
				{ 
					margin: 0px 5px 0px 5px;
				}
				body
	            {
	            	max-width: 47mm;
	            }
	        }

			@media screen {
	            body
	            {
	            	width: 60mm;
	            }
	        }

			.header_logo, .header_company, .header_title
			{
				text-align: center;
				font-size: 12px;
			}
			#body_content #remark
			{
				margin: 0px 2px;
				padding: 2px 6px;
				min-height:40px;
			}
		</style>
	</head>
	<body id="body_content"> 
<?php
	$heading = $args['heading'];
	$detail = $args['detail'];
	
	$currency = get_woocommerce_currency();//get_woocommerce_currency_symbol();
?>
	<div id="header"> 
		<div class="header_logo">
		<?php
			$img = get_option( 'woocommerce_email_receipt_image' );
			if ( $img ) 
			{
				//$logo = str_replace( site_url(), ABSPATH, $img );
				echo '<p class="centered"><img width="150" src="' . $img . '" /></p>';
			}
		?>
		</div>
		<div class="header_company">
		<?php $header_text = get_option( 'woocommerce_email_receipt_header_text' ); ?>
			<strong><?php echo $header_text; ?></strong>
		</div>
		<br>
		<div class="header_title">
			<strong>RETURN NOTE</strong>
		</div>
		<br>
		<div class="header_content">
			<table border="0" cellpadding="0" cellspacing="0" width="100%">
			<?php
				if( !empty( $heading['infos'] ) )
				{
					foreach( $heading['infos'] as $title => $info ){
						echo "<tr>";
						echo "<td valign='top' width='30%'><strong>{$title}</strong></td>";
						echo "<td valign='top' class='rightered' width='1%'>:</td>";
						echo "<td valign='top' class='rightered'>{$info}</td>";
						echo "</tr>";
					}
				}
				if( !empty( $heading['first_addr'] ) || !empty( $heading['second_addr'] ) )
				{
					echo "<tr>";
					echo "<td valign='top' width='30%'><strong>DELIVER TO:</strong></td>";
					echo "<td valign='top' class='rightered' width='1%'>:</td>";
					if( !empty( $heading['first_addr'] ) ) echo "<td valign='top' class='rightered'>{$heading['first_addr']}</td>";
					else if( !empty( $heading['second_addr'] ) ) echo "<td valign='top' class='rightered'>{$heading['second_addr']}</td>";
					echo "</tr>";
				}
			?>
			</table>
			<br>
		</div>
	</div>

	<div id="content">
		<table border="0" class="" cellspacing="0" cellpadding="0" width="100%" >
			<thead>
				<tr>
					<th valign="top" class="td td-offl td-offr leftered" scope="col" width="3%"></th>
					<th valign="top" class="td td-offl td-offr leftered" scope="col" width="65%">ITEM</th>
					<th valign="top" class="td td-offl td-offr rightered" scope="col" width='15%'>QTY</th>
					<th valign="top" class="td td-offl td-offr leftered" scope="col" width='10%'>UOM</th>
				</tr>
			</thead>
			
			<tbody>
			<?php
				if( $detail )
				{
					$i = 0;
					foreach( $detail as $item )
					{
						$i++;
						echo "<tr>";
						echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'>{$i}</td>";
						echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'>{$item['item']}</td>";
						echo "<td class='td td-offl td-offr td-offt td-offb rightered' valign='top'>".round_to( $item['qty'], 0, true, true )."</td>";
						echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'>{$item['uom']}</td>";
						echo "</tr>";
						
						$total_qty+= $item['qty'];
					}
				}
			?>
			</tbody>

			<tfoot>
				<tr>
					<th class="td td-offl td-offr leftered"></th>
					<th class="td td-offl td-offr leftered"><strong>TOTAL:</strong></th>
					<th class="td td-offl td-offr rightered"><?php echo round_to( $total_qty, 0, true, true ); ?> </th>
					<th class="td td-offl td-offr leftered"></th>
				</tr>
			</tfoot>
		</table>
		<br>
	</div>

	<div id="footer"> 
		<p id="remark" class="text">
		<?php if( !empty( $heading['remark'] ) ): ?>
			Remarks: <?php echo nl2br( $heading['remark'] ); ?>
		<?php endif; ?>
		</p>
		<table id="signature" class="" cellspacing="0" cellpadding="0" width="100%" border="0">
			<tbody>
				<tr>
					<td class="td td-offl td-offr td-offb leftered" colspan="2">
						<strong>For <?php echo strtoupper( trim( $heading['sign_holder'] ) ) ?></strong>
					</td>
				</tr>
				<tr>
					<td>Name:</td><td></td>
				</tr>
				<tr>
					<td>Date:</td><td></td>
				</tr>
				<tr>
					<td class="td td-offl td-offr td-offb leftered" colspan="2"><strong>Transporter</strong></td>
				</tr>
				<tr>
					<td>Name:</td><td></td>
				</tr>
				<tr>
					<td>Date:</td><td></td>
				</tr>
				<tr>
					<td class="td td-offl td-offr td-offb leftered" colspan="2"><strong>Receiver</strong></td>
				</tr>
				<tr>
					<td>Name:</td><td></td>
				</tr>
				<tr>
					<td>Date:</td><td></td>
				</tr>
			</tbody>
		</table>
		<p>&nbsp;<br>&nbsp;</p>
	</div>
	<div class="page-break"></div>
	</body>
	<?php if( $args['print'] ): ?>
	<script>
    	window.print();
	</script>
<?php endif; ?>
</html>