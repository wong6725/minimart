<?php if ( !defined("ABSPATH") ) exit; ?>

<?php //pd($args); ?>

<div id="credit_report_detail" class="printable">
<style>
	.each_order{ margin-bottom:24px; }
	.widefat tfoot td, .widefat th, .widefat thead td{font-weight:600;text-align:left;}
	.widefat tfoot td.rightered, .widefat th.rightered, .widefat thead td.rightered, .widefat tbody td.rightered{ text-align:right; }
	table.details .num{ width:50px; }
	table.details .item{ width:40%; }
	@media print {
		.widefat{width:100%;}
		table.widefat{ border: 1px solid #ccd0d4; }
		.widefat thead td, .widefat thead th{ border-bottom:1px solid #ccd0d4; }
		body{ font-size:10px !important; }
	}
</style>

<?php 

$total_payable = 0;
$total_credit = 0;

foreach( $args as $i => $order ): 
?>

<?php
	$header = $order['header'];
	$details = $order['detail'];
	
	$total_payable+= $header['order_total'];
	$total_credit+= $header['credit_amount'];
?>
<div class="each_order">
	<table class="header wp-list-table widefat fixed rows">
		<tr>
			<th>Receipt No: </th><td><?php echo $header['receipt_no']; ?></td>
			<th>Date: </th><td><?php echo $header['date']; ?></td>
		</tr>
		<tr>
			<th>Customer: </th><td><?php echo $header['customer']; ?></td>
			<th>Customer No: </th><td><?php echo $header['customer_serial']; ?></td>
		</tr>
		<tr>
			<th>Employee ID: </th><td><?php echo $header['employee_id']; ?></td>
			<th>Customer Group: </th><td><?php echo $header['cgroup_code']; ?></td>
		</tr>
		<tr>
			<td colspan="4">
			
				<table class="details wp-list-table widefat fixed striped rows">
					<thead>
						<tr>
							<th class="num">No.</th>
							<th class="item">Item</th>
							<th class="uom">UOM</th>
							<th class="qty rightered">Qty</th>
							<th class="price rightered">Price(RM)</th>
							<th class="amt rightered">Amount(RM)</th>
						</tr>
					</thead>
					<tbody>
					<?php
						$subtotal = 0;
					?>
					
					<?php foreach( $details as $j => $item ): ?>
					
					<?php
						$subtotal+= $item['line_total'];
					?>
						<tr>
							<td class="num"><?php echo $j+1 ?>.</td>
							<td class="item">
								<?php echo $item['item_name']; ?>
							</td>
							<td class="uom"><?php echo $item['uom']; ?></td>
							<td class="qty rightered"><?php echo $item['qty']; ?></td>
							<td class="price rightered"><?php echo round_to( $item['price'], 2, true ); ?></td>
							<td class="amt rightered"><?php echo round_to( $item['line_total'], 2, true ); ?></td>
						</tr>
					
					<?php endforeach; ?>
					</tbody>
					<tfoot>
						
					</tfoot>
				</table>
				
			</td>
		</tr>
		<tr>
			<th colspan="3" class="rightered">Subtotal: </th><th class="rightered"><?php echo round_to( $subtotal, 2, true ); ?></th>
		</tr>
		<tr>
			<th colspan="3" class="rightered">Total Payable: </th><th class="rightered"><?php echo round_to( $header['order_total'], 2, true ); ?></th>
		</tr>
	<?php if( $header['credit_amount'] ): ?>
		<tr>
			<th colspan="3" class="rightered">Credit: </th><th class="rightered"><?php echo round_to( $header['credit_amount'], 2, true ); ?></th>
		</tr>
	<?php endif; ?>
	<?php if( $header['cash_paid'] ): ?>
		<tr>
			<th colspan="3" class="rightered">Cash: </th><th class="rightered"><?php echo round_to( $header['cash_paid'], 2, true ); ?></th>
		</tr>
		<tr>
			<th colspan="3" class="rightered">Change: </th><th class="rightered"><?php echo round_to( $header['cash_change'], 2, true ); ?></th>
		</tr>
	<?php endif; ?>
	</table>
</div>

<?php endforeach; ?>
	<table class="summary wp-list-table widefat fixed rows">
		<tr>
			<th>Total Payable(RM): </th><th><?php echo round_to( $total_payable, 2, true ); ?></th>
			<th>Total Credit(RM): </th><th><?php echo round_to( $total_credit, 2, true ); ?></th>
		</tr>
	</table>

</div>