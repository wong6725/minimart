<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php $header = [ 'title'=>'PICKING LIST', 'docno'=>$args['heading']['docno'] ]; ?>
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
	$detail = $args['detail'];

	$pages = [];
	if( $detail )
	{
		$row_per_pg = 24;
		$row_last_pg = 24;
		$row_max = 56;
		$pages = rowPerPage( $detail, $row_max, $row_per_pg, $row_last_pg );
	}
	//pd($pages);

	$currency = get_woocommerce_currency();//get_woocommerce_currency_symbol();
?>

<?php
	if( $pages ):
		$total_qty = 0;
		foreach( $pages as $p => $page ):
			$heading['infos']['Page'] = ( $p+1 ).' of '.sizeof( $pages );
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
		$heading['first_title'] = 'Please arrange to load as follows:';
		do_action( 'wcwh_get_template', 'template/doc-infos.php', $heading );
	?>
	</div>

	<div id="content">
		<table id="break" class="td" cellspacing="0" cellpadding="6" width="100%" border="1">
			
			<thead>
				<tr>
					<th valign="top" class="td centered" scope="col" width="6%">ITEM</th>
					<th valign="top" class="td leftered" scope="col" width="60%">PRODUCT DESCRIPTION</th>
					<th valign="top" class="td td-offr rightered" scope="col" width='12%'>QTY</th>
					<th valign="top" class="td td-offl rightered" scope="col" width='7%'>UOM</th>
					<th valign="top" class="td td-offl centered" scope="col" width='15%'>Note</th>
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
						echo "<td class='td td-offt td-offb td-offl rightered' valign='top'>{$item['uom']}</td>";
						echo "<td class='td td-offt td-offb'></td>";
						echo "</tr>";
						
						$total_qty+= $item['qty'];
					}
				}
				
				$leftover = $row_last_pg - $i;
				for( $j = 0; $j < $leftover; $j++ )
				{
					echo "<tr>";
					echo "<td class='td td-offt td-offb'> </td>";
					echo "<td class='td td-offt td-offb'>&nbsp;</td>";
					echo "<td class='td td-offt td-offb' colspan='2'></td>";
					echo "<td class='td td-offt td-offb'> </td>";
					echo "</tr>";
				}
			?>
			</tbody>

		<?php if( $p+1 == sizeof( $pages ) ): ?>
			<tfoot>
				<tr>
					<th class="td rightered" colspan="2">TOTAL</th>
					<th class="td td-offr rightered"><?php echo round_to( $total_qty, 2, true, true ); ?> </th>
					<th class="td td-offb td-offl rightered"></th>
					<th class="td td-offb rightered"></th>
				</tr>
			</tfoot>
		<?php endif; ?>

		</table>
		<br>
	</div>

	<div id="footer"> 
		<p id="remark" class="text td">
			Remarks: <?php echo nl2br( $heading['remark'] ); ?>
		</p>

		<br><br><br><br><br>
		<p id="signature" class="text">This document is computer generated and no signature is required.</p>
	</div>
	<?php if( $p+1 < sizeof( $pages ) ): ?>
		<div class="page-break"></div>
	<?php endif; ?>

<?php
		endforeach;
	endif;
?>

<?php do_action( 'wcwh_get_template', 'template/doc-footer.php' ); ?>