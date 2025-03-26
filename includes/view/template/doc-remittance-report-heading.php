<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<table id="doc_heading" class="text" border="0" cellpadding="0" cellspacing="0" width="100%">
	<tbody>
		<tr>
			<td scope="col" align="left" valign="top" width="50%" style="padding:2px;">
				<table border="0" cellpadding="0" cellspacing="0" width="100%">
					<tr>
						<th align="left" valign="top"><?php echo $args['company']." - ".$args['title']; ?></th>
					</tr>
				</table>
			</td>
			<td scope="col" align="right" valign="top" width="50%" style="padding:2px;">
				<table border="0" cellpadding="0" cellspacing="0" width="100%">
					<tr>
						<th align="right" valign="top">Printed On: </th>
						<th align="left" valign="top"><?php echo $args['print_on']; ?></th>
					</tr>
					<tr>
						<th align="right" valign="top">Printed By: </th>
						<th align="left" valign="top"><?php echo $args['print_by']; ?></th>
					</tr>
				</table>
			</td>
		</tr>
	</tbody>
</table>
<br/>