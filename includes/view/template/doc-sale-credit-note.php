<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php $header = [ 'title'=>'CREDIT NOTE', 'docno'=>$args['heading']['docno'] ]; ?>
<?php do_action( 'wcwh_get_template', 'template/doc-header.php', $header ); ?>

	<style>
		body, p, b, a, span, td, th
		{
			font-size:11px;
		}
		@page 
		{ 
			size: A4;
			margin: 30px 30px 30px 30px;
		}

		#body_content #addresses td
		{	
			height:150px; 
			max-height: 150px; 
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
			padding: 0px 2px;
		}
	</style>

<?php
	//page operation
	$heading = $args['heading'];
	$detail = $args['detail'];

	$pages = [];
	if( $detail )
	{
		$row_per_pg = 34;
		$row_last_pg = 25;
		$row_max = 54;
		$pages = rowPerPage( $detail, $row_max, $row_per_pg, $row_last_pg );
	}
	//pd($pages);

	$currency = get_woocommerce_currency();//get_woocommerce_currency_symbol();
?>

<?php
	if( $pages ):
		$total_qty = 0;
		$total_foc = 0;
		$total_amount = 0;
		foreach( $pages as $p => $page ):
			$heading['infos']['Page'] = ( $p+1 ).' of '.sizeof( $pages );
?>
	<div id="header"> 
	<?php
		$header['qr'] = !empty( $heading['irb_qr'] )? $heading['irb_qr'] : $heading['docno'];
		$header['quality'] = 'M';
		$header['size'] = 4.4;
		$header['frame'] = 2;
		do_action( 'wcwh_get_template', 'template/doc-heading.php', $header );
	?>
	<?php
		$heading['first_col'] = "BILL TO";
		$heading['second_col'] = "FROM";
		do_action( 'wcwh_get_template', 'template/doc-addresses.php', $heading );
	?>
	</div>

	<div id="content">
		<table id="break" class="td" cellspacing="0" cellpadding="6" width="100%" border="1">
			<thead>
				<tr>
					<th valign="top" class="td centered" scope="col" width="6%">ITEM</th>
					<th valign="top" class="td leftered" scope="col" width="51%">PRODUCT DESCRIPTION</th>
					<th valign="top" class="td rightered" scope="col" width='8%'>QTY</th>
					<!--<th valign="top" class="td td-offl leftered" scope="col" width='5%'>UOM</th>
					<th valign="top" class="td td-offl centered" scope="col" width='8%'>FOC</th>-->
					<th valign="top" class="td td-offl rightered" scope="col" width='11%'>UNIT PRICE<br>(<?php echo $currency; ?>)</th>
					<th valign="top" class="td td-offl rightered" scope="col" width='11%'>TOTAL AMOUNT<br>(<?php echo $currency; ?>)</th>
				</tr>
			</thead>
			
			<tbody>
			<?php
				$detail = $page;

				if( $detail )
				{
					$i = 0;
					foreach( $detail as $item )
					{
						$i+= $item['row'];
						echo "<tr>";
						echo "<td class='td td-offt td-offb centered' valign='top'>{$item['num']}</td>";
						echo "<td class='td td-offt td-offb leftered' valign='top'>{$item['item']}</td>";
						echo "<td class='td td-offt td-offb rightered' valign='top'>".round_to( $item['qty'], 2, true, true )."</td>";
						//echo "<td class='td td-offt td-offb td-offl leftered' valign='top'>{$item['uom']}</td>";
						//echo "<td class='td td-offt td-offb td-offl rightered' valign='top'>".round_to( $item['foc'], 2, true, true )."</td>";
						echo "<td class='td td-offt td-offb td-offl rightered' valign='top'>".round_to( $item['uprice'], 2, true, true )."</td>";
						echo "<td class='td td-offt td-offb td-offl rightered' valign='top'>".round_to( $item['total_amount'], 2, true, true )."</td>";
						echo "</tr>";
						
						//$total_qty+= $item['qty'];
						//$total_foc+= $item['foc'];
						$total_qty += $item['qty'];
						$total_uprice += $item['uprice'];
						$total_amount += $item['total_amount'];
					}
				}
				
				$leftover = $row_last_pg - $i;
				for( $j = 0; $j < $leftover; $j++ )
				{
					echo "<tr>";
					echo "<td class='td td-offt td-offb'></td>";
					echo "<td class='td td-offt td-offb'>&nbsp;</td>";
					echo "<td class='td td-offt td-offb'></td>";
					echo "<td class='td td-offt td-offb'> </td>";
					echo "<td class='td td-offt td-offb'> </td>";
					echo "</tr>";
				}
			?>
			</tbody>

		<?php if( $p+1 == sizeof( $pages ) ): ?>
			<tfoot>
				<tr>
					<th class="td leftered" colspan="2">TOTAL</th>
					<th class="td td-offb td-offl rightered"><?php echo round_to( $total_qty, 2, true, true ); ?></th>
					<th class="td td-offr rightered"><?php echo round_to( $total_uprice, 2, true, true ); ?> </th>
					<th class="td td-offr rightered"><?php echo round_to( $total_amount, 2, true, true ); ?> </th>
				</tr>
			</tfoot>
		<?php endif; ?>

		</table>
		<br>
	</div>

	<div id="footer"> 
	<?php if( $p+1 == sizeof( $pages ) ): ?>
		<p id="remark" class="text td">
			<strong>Reasons:</strong> <?php echo nl2br( $heading['note_reason'] ); ?>
			<br>
			<strong>Remarks:</strong> <?php echo nl2br( $heading['remark'] ); ?>
		</p>
		<br>
		<table id="signature" class="" cellspacing="0" cellpadding="6" width="100%" border="0">
			<tbody>
				<tr>
					<td class="leftered" scope="col" width="33%" valign="top">Prepared By: </td>
					<td class="leftered" scope="col" width="34%" valign="top"></td>
					<td class="leftered" scope="col" width="33%" valign="top">Approved By: </td>
				</tr>
				<tr>
					<td class="td td-offt td-offl td-offr leftered" scope="col" width="33%" valign="top">
						<br>
						<?php echo ( $args['prepare_by'] )? $args['prepare_by'] : '<br>'; ?>
					</td>
					<td class="leftered" scope="col" width="34%" valign="top"></td>
					<td class="td td-offt td-offl td-offr leftered" scope="col" width="33%" valign="top">
						<br>
						<?php echo ( $args['approve_by'] )? $args['approve_by'] : '<br>'; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<!--<p id="signature" class="text">This document is computer generated and no signature is required.</p>-->
	<?php endif; ?>
	</div>
	<?php if( $p+1 < sizeof( $pages ) ): ?>
		<div class="page-break"></div>
	<?php endif; ?>

<?php
		endforeach;
	endif;
?>

<?php do_action( 'wcwh_get_template', 'template/doc-footer.php' ); ?>