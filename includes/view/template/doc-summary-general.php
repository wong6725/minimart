<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php $header = [ 'title'=>$args['header'] ]; ?>
<?php $config = $args['config']; ?>
<?php do_action( 'wcwh_get_template', 'template/doc-header.php', $header ); ?>
	<style>
		body, p, b, a, span, td, th
		{
			font-size:12px;
		}
		@page 
		{ 
			size: A4;
			size: <?php echo ( $config['orientation'] )? $config['orientation'] : 'portrait'; ?>;
			margin-top: <?php echo ( $config['margin_top'] )? $config['margin_top'] : 20; ?>px;
			margin-bottom: <?php echo ( $config['margin_bottom'] )? $config['margin_bottom'] : 20; ?>px;
			margin-left: <?php echo ( $config['margin_left'] )? $config['margin_left'] : 20; ?>px;
			margin-right: <?php echo ( $config['margin_right'] )? $config['margin_right'] : 20; ?>px;
		}

		#body_content #signature td, #body_content #signature th
		{ 
			padding: 2px; 
		}
		#body_content #signature p
		{ 
			margin: 2px; 
		}
		#content table th, #content table td
		{
			font-size: <?php echo ( $config['font_size'] )? $config['font_size'] : 9; ?>px;
			padding:1px 3px;
		}
		.nopad
		{ 
			padding:1px 0px !important; 
		}
	</style>

	<div id="header"> 
	<?php
		$heading = $args['heading'];
		do_action( 'wcwh_get_template', 'template/doc-summary-heading.php', $args['heading'] );
	?>
	</div>

	<div id="content" style="width:100%">
		<table class="td" cellspacing="0" cellpadding="0" width="100%">
			
			<?php if( $args['detail_title'] ): ?>
			<thead>
				<tr>
				<?php foreach( $args['detail_title'] as $title => $ctrl ): ?>
				<?php
					$class = [ 'td' ];
					if( !empty( $ctrl['class'] ) )
						$class = array_merge( $class, $ctrl['class'] );
				?>
					<th class="<?php echo implode( ' ', $class ) ?>" scope="col" width="<?php echo $ctrl['width'] ?>" ><?php echo $title ?></th>
				<?php endforeach; ?>
				</tr>
			</thead>
			<?php endif; ?>
		
			<tbody>
			<?php
				$detail = $args['detail'];
				if( $detail )
				{
					foreach( $detail as $i => $row )
					{
						echo "<tr>";

						foreach( $row as $j => $col )
						{
							if( ! $col )
							{
								echo "<td class='td td-offt td-offb' valign='top'></td>";
							}
							else
							{
								$class = [ 'td' ];

								if( $col['rowspan'] ) $class[] = 'td-offb';
								$colspan = ( $col['colspan'] )? "colspan='{$col['colspan']}'" : "";
								
								if( !empty( $col['class'] ) )
									$class = array_merge( $class, $col['class'] );

								if( $col['num'] ) 
								{
									$decimal = $col['decimal']? $col['decimal'] : 2;
									$col['value'] = round_to( $col['value'], $decimal, 1, 1 );
								}
								
								if( $col['chop'] > 0 )
								{
									$chopped = str_split( $col['value'], $col['chop'] );
									$col['value'] = implode( "<be>\n", $chopped );
								}

								echo "<td class='".implode( ' ', $class )."' valign='top' {$rowspan} {$colspan}>{$col['value']}</td>";
							}
						}

						echo "</tr>";
					}
				}
			?>
			</tbody>
		</table>
		<br><br>
	</div>

<?php if( ! $config['off_signature'] ): ?>
	<div id="footer"> 
		<table id="signature" class="" cellspacing="0" cellpadding="0" width="100%" border="0">
			<tbody>
				<tr>
					<?php
						$footing = $args['footing'];
					?>
					<th class="leftered" scope="col" width="25%" valign="top">
						Verified By
						<br><br><br><?php echo ( $footing['verified'] )? $footing['verified'] : '<br>' ?>
						________________________
						<br><br>
						Date: <?php echo ( $footing['verified_date'] )? $footing['verified_date'] : '' ?>
					</th>
					<th class="leftered" scope="col" width="25%" valign="top">
						Confirmed By
						<br><br><br><?php echo ( $footing['confirmed'] )? $footing['confirmed'] : '<br>' ?>
						________________________
						<br><br>
						Date: <?php echo ( $footing['confirmed_date'] )? $footing['confirmed_date'] : '' ?>
					</th>
					<th class="leftered" scope="col" width="50%" valign="top">
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					</th>
				</tr>
			</tbody>
		</table>
	</div>
<?php endif; ?>
	<!--<div class="page-break"></div>-->

<?php do_action( 'wcwh_get_template', 'template/doc-footer.php' ); ?>