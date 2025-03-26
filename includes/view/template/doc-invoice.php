<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php $header = [ 'title'=>'INVOICE', 'docno'=>$args['heading']['docno'] ]; ?>
<?php do_action( 'wcwh_get_template', 'template/doc-header.php', $header ); ?>

	<style>
		body, p, b, a, span, td, th
		{
			font-size:11px;
		}
		@page 
		{ 
			size: A4;
			margin: 20px 20px 20px 20px;
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
		#body_content #currency
		{ 
			padding: 0px 3px;
			height:40px; 
		}
		#body_content #footer-info
		{
			padding: 0px 3px;
			height:100px;
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
	$detail = $args['detail'];

	$new_detail = [];
	if( $detail )
	{
		$r = 0; $num = 0;
		foreach( $detail as $i => $row )
		{
			if( $row['qty'] > 0 )
			{
				$new_detail[] = $row;
			}

			if( $row['foc'] > 0 )
			{
				$row['isFoc'] = true;
				$row['item'].= '(FOC)';
				$row['qty'] = $row['foc'];
				$row['sprice'] = $row['total_amount'] = $row['final_amount'] = $row['discount'] = 0;

				$new_detail[] = $row;
			}
		}
	}

	$pages = [];
	if( $new_detail )
	{
		$row_per_pg = 37;
		$row_last_pg = 25;
		$row_max = 48;

		if( $heading['fees'] )
		{
			$row_last_pg = 25 - count( $heading['fees'] );
		}

		$pages = rowPerPage( $new_detail, $row_max, $row_per_pg, $row_last_pg );
	}
	//pd($pages);

	$currency = get_woocommerce_currency();//get_woocommerce_currency_symbol();
?>

<?php
	if( $pages ):
		$total_qty = 0;
		$total_amount = 0;
		$total_discount = 0;
		$final_total = 0;
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
		$heading['second_col'] = "DELIVER TO";
		do_action( 'wcwh_get_template', 'template/doc-addresses.php', $heading );
	?>
	</div>

	<div id="content">
		<table id="break" class="td" cellspacing="0" cellpadding="6" width="100%" border="1">
			<thead>
				<tr>
					<th valign="top" class="td centered" scope="col" width="5%">ITEM</th>
					<th valign="top" class="td leftered" scope="col" width="44%">PRODUCT DESCRIPTION</th>
					<th valign="top" class="td td-offr rightered" scope="col" width='7%'>QTY</th>
					<th valign="top" class="td td-offl leftered" scope="col" width='4%'>UOM</th>
					<th valign="top" class="td td-offl centered" scope="col" width='10%'>PRICE<br>(<?php echo $currency; ?>)</th>
					<th valign="top" class="td td-offl centered" scope="col" width='10%'>AMOUNT<br>(<?php echo $currency; ?>)</th>
					<th valign="top" class="td td-offl centered" scope="col" width='10%'>DISCOUNT</th>
					<th valign="top" class="td td-offl centered" scope="col" width='10%'>TOTAL<br>(<?php echo $currency; ?>)</th>
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
						echo "<td class='td td-offt td-offb td-offr rightered' valign='top'>".round_to( $item['qty'], 2, true, true )."</td>";
						echo "<td class='td td-offt td-offb td-offl leftered' valign='top'>{$item['uom']}</td>";
						echo "<td class='td td-offt td-offb rightered' valign='top'>".round_to( $item['def_sprice'], 2, true, true )."</td>";
						echo "<td class='td td-offt td-offb rightered' valign='top'>".round_to( $item['total_amount'], 2, true, true )."</td>";
						echo "<td class='td td-offt td-offb rightered' valign='top'>".$item['disc'].$item['disc_separator'].round_to( $item['discount'], 2, true, true )."</td>";
						echo "<td class='td td-offt td-offb rightered' valign='top'>".round_to( $item['final_amount'], 2, true, true )."</td>";
						echo "</tr>";
						
						$total_qty+= $item['qty'];
						$total_amount+= $item['total_amount'];
						$total_discount+= $item['discount'];
						$final_total+= $item['final_amount'];
					}
				}
				
				$leftover = $row_last_pg - $i;
				for( $j = 0; $j < $leftover; $j++ )
				{
					echo "<tr>";
					echo "<td class='td td-offt td-offb'> </td>";
					echo "<td class='td td-offt td-offb'>&nbsp;</td>";
					echo "<td class='td td-offt td-offb' colspan='2'></td>";
					echo "<td class='td td-offt td-offb'>&nbsp;</td>";
					echo "<td class='td td-offt td-offb'>&nbsp;</td>";
					echo "<td class='td td-offt td-offb'>&nbsp;</td>";
					echo "<td class='td td-offt td-offb'>&nbsp;</td>";
					echo "</tr>";
				}
			?>
			</tbody>
		
		<?php if( $p+1 == sizeof( $pages ) ): ?>
			<tfoot>
				<tr>
					<th class="td leftered" colspan="2">SUBTOTAL</th>
					<th class="td td-offr rightered"><?php echo round_to( $total_qty, 2, true, true ); ?></th>
					<th class="td td-offb td-offl rightered"></th>
					<th class="td td-offb td-offl rightered"></th>
					<th class="td td-offb td-offl rightered"><?php echo round_to( $total_amount, 2, true, true ); ?></th>
					<th class="td td-offb td-offl rightered"><?php echo round_to( $total_discount, 2, true, true ); ?></th>
					<th class="td td-offb td-offl rightered"><?php echo round_to( $final_total, 2, true, true ); ?></th>
				</tr>

			<?php if( $heading['discount'] ): ?>
				<?php $discount = round_to( wh_apply_discount( $final_total, $heading['discount'] ), 2 ); ?>
				<tr>
					<th class="td leftered" colspan="6">DISCOUNT</th>
					<th class="td td-offb rightered"><?php echo $heading['discount']; ?></th>
					<th class="td td-offb rightered"><?php echo round_to( $discount, 2, true, true ); ?></th>
				</tr>
			<?php endif; ?>

			<?php 
				$fee_amt = 0;
				if( $heading['fees'] ): 
				foreach( $heading['fees'] as $j => $fee ):
					$fee_amt+= $fee['fee'];
			?>
				<tr>
					<th class="td leftered" colspan="7"><?php echo $fee['fee_name'] ?></th>
					<th class="td td-offb rightered"><?php echo round_to( $fee['fee'], 2, true, true ); ?></th>
				</tr>
			<?php endforeach; ?>
			<?php endif; ?>

				<tr>
					<th class="td leftered" colspan="7">TOTAL</th>
					<?php $final_total = $final_total - ( $discount? $discount : 0 ) + $fee_amt; ?>
					<th class="td td-offb td-offl rightered"><?php echo round_to( $final_total, 2, true, true ); ?></th>
				</tr>
			</tfoot>
		<?php endif; ?>

		</table>
		<br>
	</div>

	<div id="footer"> 
	<?php if( $p+1 == sizeof( $pages ) ): ?>
		<p id="currency" class="text">
		<?php
			echo convertCurrencyToWords( $final_total, 'upper', [ 'prefix'=>wcwh_currency_prefix( $currency ), 'midfix'=>'Cents', 'suffix'=>'Only' ] ); 
		?>
		</p>

		<p id="footer-info" class="text">
		<?php
			if( $p+1 == sizeof( $pages ) ): 
				echo ""; 
			endif;
		?>
		</p>
		
		<p id="signature" class="text">This invoice is computer generated and no signature is required.</p>
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