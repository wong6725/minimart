<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<table id="addresses" class="td" cellspacing="0" cellpadding="0" width="100%" style="vertical-align: top;" border="1">
	<tbody>
		<tr>
		<?php if( $args['first_col'] && $args['second_col'] ): ?>
			<td class="td" scope="col" valign="top" width="33%">
				<p class="text"><b><?php echo $args['first_col']; ?>:</b></p>
				<p class="text"><b><?php echo strtoupper( $args['first_addr'] ); ?></b></p>
			</td>
			<td class="td" scope="col" valign="top" width="33%">
				<p class="text"><b><?php echo $args['second_col']; ?>:</b></p>
				<p class="text"><b><?php echo strtoupper( $args['second_addr'] ); ?></b></p>
			</td>
		<?php elseif( $args['first_col'] && ! $args['second_col'] ): ?>
			<td class="td" scope="col" valign="top" width="66%">
				<p class="text"><b><?php echo $args['first_col']; ?>:</b></p>
				<p class="text"><b><?php echo strtoupper( $args['first_addr'] ); ?></b></p>
			</td>
		<?php endif; ?>

			<td class="td" scope="col" valign="top" width="34%">
				<table border="0" cellpadding="0" cellspacing="0" width="100%">
				<?php
					if( !empty( $args['infos'] ) ){
						foreach( $args['infos'] as $title => $info ){
							echo "<tr>";
							echo "<td valign='top' width='35%'><strong>{$title}</strong></td>";
							echo "<td valign='top' class='rightered' width='1%'>:</td>";

							if ( strpos( trim( $info ), ' ' ) !== false ) 
							{
                               $info = trim( $info );
                            }
                            else
                            {
                                $info = trim( $info );
								$chopped = str_split( $info, 21 );
								$info = implode( "<br>\n", $chopped );
                            }

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
