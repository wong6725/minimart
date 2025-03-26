<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<table id="addresses" class="td" cellspacing="0" cellpadding="0" width="100%" style="vertical-align: top;" border="1">
	<tbody>
		<tr>
			<td class="td" scope="col" valign="top" width="66%">
			<?php if( $args['first_title'] ): ?>	
				<p class="text"><b><?php echo $args['first_title']; ?></b></p>
			<?php endif; ?>

				<table border="0" cellpadding="0" cellspacing="0" width="100%">
				<?php
					if( !empty( $args['first_infos'] ) )
					{
						foreach( $args['first_infos'] as $title => $info )
						{
							echo "<tr>";
							echo "<td valign='top' width='30%'><strong>{$title}</strong></td>";
							echo "<td valign='top' class='rightered' width='1%'>:</td>";
							echo "<td valign='top'>{$info}</td>";
							echo "</tr>";
						}
					}
				?>
				</table>
			</td>
			<td class="td" scope="col" valign="top" width="34%">
			<?php if( $args['second_title'] ): ?>	
				<p class="text"><b><?php echo $args['second_title']; ?></b></p>
			<?php endif; ?>

				<table border="0" cellpadding="0" cellspacing="0" width="100%">
				<?php
					if( !empty( $args['second_infos'] ) )
					{
						foreach( $args['second_infos'] as $title => $info )
						{
							echo "<tr>";
							echo "<td valign='top' width='35%'><strong>{$title}</strong></td>";
							echo "<td valign='top' class='rightered' width='1%'>:</td>";
							echo "<td valign='top'>{$info}</td>";
							echo "</tr>";
						}
					}
				?>
				</table>
			</td>
		</tr>
	</tbody>
</table>
<br/>
