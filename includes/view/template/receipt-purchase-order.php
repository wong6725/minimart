<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>PURCHASE ORDER <?php echo $args['heading']['docno']; ?></title>
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
					margin: 0px 10px 0px 10px;
				}
	        }

			@media screen {
	            body
	            {
	            	width: 80mm;
	            }
	        }

			.header_logo, .header_company, .header_title
			{
				text-align: center;
				font-size: 12px;
			}
			#body_content #currency
			{ 
				padding: 2px 6px;
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
			<strong>PURCHASE ORDER</strong>
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
				if( !empty( $heading['first_addr'] ) )
				{
					echo "<tr>";
					echo "<td valign='top' width='30%'><strong>VENDOR:</strong></td>";
					echo "<td valign='top' class='rightered' width='1%'>:</td>";
					echo "<td valign='top' class='rightered'>{$heading['first_addr']}</td>";
					echo "</tr>";
				}
				if( !empty( $heading['second_addr'] ) )
				{
					echo "<tr>";
					echo "<td valign='top' width='30%'><strong>DELIVER TO:</strong></td>";
					echo "<td valign='top' class='rightered' width='1%'>:</td>";
					echo "<td valign='top' class='rightered'>{$heading['second_addr']}</td>";
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
					<th valign="top" class="td td-offl td-offr leftered" scope="col" width="52%">ITEM</th>
					<th valign="top" class="td td-offl td-offr rightered" scope="col" width='10%'>QTY</th>
					<th valign="top" class="td td-offl td-offr leftered" scope="col" width='8%'>UOM</th>
					<th valign="top" class="td td-offl td-offr leftered" scope="col" width='10%'>PRICE</th>
					<th valign="top" class="td td-offl td-offr leftered" scope="col" width='10%'>TOTAL</th>
				</tr>
			</thead>
			
			<tbody>
			<?php
				if( $detail )
				{
					$total_qty = 0;
					$total_amount = 0;
					$i = 0;
					foreach( $detail as $item )
					{	
						$i++;
						echo "<tr>";
						echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'>{$i}</td>";
						echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'>{$item['item']}</td>";
						echo "<td class='td td-offl td-offr td-offt td-offb rightered' valign='top'>".round_to( $item['qty'], 0, true, true )."</td>";
						echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'>{$item['uom']}</td>";
						echo "<td class='td td-offl td-offr td-offt td-offb rightered' valign='top'>".round_to( $item['uprice'], 2, true, true )."</td>";
						echo "<td class='td td-offl td-offr td-offt td-offb rightered' valign='top'>".round_to( $item['total_amount'], 2, true, true )."</td>";
						echo "</tr>";

						if( $item['foc'] > 0 )
						{
							echo "<tr>";
							echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'></td>";
							echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'>FOC</td>";
							echo "<td class='td td-offl td-offr td-offt td-offb rightered' valign='top'>".round_to( $item['foc'], 0, true, true )."</td>";
							echo "<td class='td td-offl td-offr td-offt td-offb leftered' valign='top'>{$item['uom']}</td>";
							echo "<td class='td td-offl td-offr td-offt td-offb rightered' valign='top'>".round_to( 0, 2, true, true )."</td>";
							echo "<td class='td td-offl td-offr td-offt td-offb rightered' valign='top'>".round_to( 0, 2, true, true )."</td>";
							echo "</tr>";
						}
						
						$total_qty+= $item['qty']+$item['foc'];
						$total_amount+= $item['total_amount'];
					}
				}
			?>
			</tbody>

			<tfoot>
				<tr>
					<th class="td td-offl td-offr td-offb leftered"></th>
					<th class="td td-offl td-offr td-offb leftered"><strong>TOTAL:</strong></th>
					<th class="td td-offl td-offr td-offb rightered"><?php echo round_to( $total_qty, 0, true, true ); ?></th>
					<th class="td td-offl td-offr td-offb rightered"></th>
					<th class="td td-offl td-offr td-offb rightered"></th>
					<th class="td td-offl td-offr td-offb rightered"><?php echo round_to( $total_amount, 2, true, true ); ?></th>
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
					<td class="td td-offl td-offr td-offb leftered"><strong>Prepared By: </strong></td>
					<td class="td td-offl td-offr td-offb rightered">
						<?php echo ( $args['prepare_by'] )? $args['prepare_by'] : '<br>'; ?>
					</td>
				</tr>
				<tr>
					<td class="td td-offl td-offr td-offt leftered"><strong>Approved By: </strong></td>
					<td class="td td-offl td-offr td-offt rightered">
						<?php echo ( $args['approve_by'] )? $args['approve_by'] : '<br>'; ?>
					</td>
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