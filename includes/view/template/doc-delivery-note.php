<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php $header = [ 'title'=>'RETURN NOTE', 'docno'=>$args['heading']['docno'] ]; ?>
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
			height:150px; 
			max-height: 150px; 
		}
		#body_content #addresses td table td
		{ 
			height:auto; 
		}
		#body_content #remark
		{ 
			padding: 2px 6px; 
			height: 32px; 
			max-height: 32px; 
			font-size: 10px; 
		}
		#body_content .footer-title
		{
			padding: 1px 8px;
			font-size: 10px;
		}
		#body_content #signature td, #body_content #signature th
		{ 
			padding: 1px 4px; 
		}
		#body_content #signature p
		{ 
			margin: 2px; 
		}
		table.footer-inner td, table.footer-inner th
		{ 
			font-size: 10px; 
		}
		.nopad
		{ 
			padding:1px 0px !important; 
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
		$row_last_pg = 22;
		$row_max = 73;
		$pages = rowPerPage( $detail, $row_max, $row_per_pg, $row_last_pg );
	}
	//pd($pages);
?>

<?php
	if( $pages ):
		$totals = 0;
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
		$heading['first_col'] = "DELIVER TO";
		do_action( 'wcwh_get_template', 'template/doc-addresses.php', $heading );
	?>
	</div>

	<div id="content">
		<table id="break" class="td" cellspacing="0" cellpadding="6" width="100%" border="1">
			
			<thead>
				<tr>
					<th class="td centered" scope="col" width="6%">ITEM</th>
					<th class="td leftered" scope="col" width="75%">PRODUCT DESCRIPTION</th>
					<th class="td td-offr rightered" scope="col" width='12%'>QTY</th>
					<th class="td td-offl rightered" scope="col" width='7%'>UOM</th>
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
						echo "</tr>";
						$totals+= $item['qty'];
					}
				}
				
				$leftover = $row_last_pg - $i;
				for( $j = 0; $j < $leftover; $j++ )
				{
					echo "<tr>";
					echo "<td class='td td-offt td-offb'> </td>";
					echo "<td class='td td-offt td-offb'>&nbsp;</td>";
					echo "<td class='td td-offt td-offb' colspan='2'></td>";
					echo "</tr>";
				}
			?>
			</tbody>

		<?php if( $p+1 == sizeof( $pages ) ): ?>
			<tfoot>
				<tr>
					<th class="td rightered" colspan="2">TOTAL</th>
					<th class="td td-offr rightered"><?php echo round_to( $totals, 3, true, true ); ?> </th>
					<th class="td td-offb td-offl rightered"></th>
				</tr>
			</tfoot>
		<?php endif; ?>

		</table>
		<?php if( $p+1 == sizeof( $pages ) ): ?>
			<p id="remark" class="text leftered">Remarks: <?php echo $heading['remark']; ?></p>
		<?php endif; ?>
	</div>

	<?php if( $p+1 == sizeof( $pages ) ):  ?>
	<div id="footer"> 
		<p class="text footer-title leftered">E & O.E.</p>
		<table id="signature" class="td" cellspacing="0" cellpadding="6" width="100%" border="1">
			<tbody>
				<tr>
					<td class="td td-offb leftered" scope="row" rowspan="2" width="34%" valign="top">For <?php echo strtoupper( trim( $heading['sign_holder'] ) ) ?></td>
					<td class="td centered" scope="row" colspan="2">Goods received in good condition by:</td>
				</tr>
				<tr>
					<td class="td leftered" scope="col" width="33%">Transporter</td>
					<td class="td leftered" scope="col" width="33%">Receiver</td>
				</tr>
				<tr>
					<td class="td td-offt leftered">
						<table width="100%" class="footer-inner">
							<tr><td class="border-bottom" colspan="2" style="height:50px;"></td></tr>
							<tr><td class="nopad" width="20%">Name:</td><td class="leftered"></td></tr>
							<tr><td class="nopad" width="20%">Designation:</td><td class="leftered"></td></tr>
							<tr><td class="nopad">Date:</td><td></td></tr>
						</table>
					</td>
					<td class="td leftered">
						<table width="100%" class="footer-inner">
							<tr><td class="border-bottom" colspan="2" style="height:50px;"></td></tr>
							<tr><td class="nopad" width="20%">Name:</td><td class="leftered"></td></tr>
							<tr><td class="nopad" width="20%">IC:</td><td class="leftered"></td></tr>
							<tr><td class="nopad">Date:</td><td></td></tr>
						</table>
					</td>
					<td class="td leftered">
						<table width="100%" class="footer-inner">
							<tr><td class="border-bottom" colspan="2" style="height:50px;"></td></tr>
							<tr><td class="nopad">Name:</td><td></td></tr>
							<tr><td class="nopad">IC:</td><td></td></tr>
							<tr><td class="nopad">Date:</td><td></td></tr>
						</table>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<?php if( $p+1 < sizeof( $pages ) ): ?>
		<div class="page-break"></div>
	<?php endif; ?>

<?php
		endforeach;
	endif;
?>

<?php do_action( 'wcwh_get_template', 'template/doc-footer.php' ); ?>