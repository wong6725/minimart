<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<table id="doc_heading" class="text" border="0" cellpadding="0" cellspacing="0" width="100%">
	<tbody>
		<tr>
			<td class="heading_info" scope="col" align="left" valign="bottom" width="66%" style="padding:0px;">
			<?php
				$img = get_option( 'woocommerce_email_header_image' );
				if ( $img ) 
				{
					//$logo = str_replace( site_url(), ABSPATH, $img );
					echo '<p class="leftered"><img width="150" src="' . $img . '" /></p>';
				}
			?>
				<table border="0" cellpadding="0" cellspacing="0" width="100%">
					<tr>
						<td class="main" valign="top" width="50%">
							<p class="td td-offt td-offl td-offb">
							<?php
								if ( $html = get_option( 'woocommerce_email_corpinfo_text' ) ):
									echo $html;
								else:
							?>
					    		<strong>MOMAWATER SDN. BHD.</strong> (1033245-V)
								<br>NO. 66-78, PUSAT SURIA PERMATA,
								<br>JALAN UPPER LANANG, C.D.T. 123,
								<br>96000 SIBU, SARAWAK, MALAYSIA.
								<br>BRN: 201301003406 &nbsp;&nbsp;&nbsp;TIN: C22884012000
								<br>SST: Y61-1809-22000014
							<?php endif; ?>
							</p>
						</td>
						<td class="second" valign="top" width="50%">
						<?php
							if ( $html = get_option( 'woocommerce_email_secondinfo_text' ) ):
								echo $html;
							else:
						?>
							<p><b>T</b>+6084-211555</p>
							<p><b>F</b>+6084-213801</p>
							<p><b>E</b>info@momawater.com</p>
							<p><b>W</b>momawater.com</p>
						<?php endif; ?>
						</th>
					</tr>
				</table>
			</td>
			<td scope="col" align="left" valign="top" width="34%" style="padding:0px; padding-right:2px;">
				<table border="0" cellpadding="0" cellspacing="0" width="100%">
					<tr>
						<td id="doc_qr">
							<?php $qrcode = apply_filters( 'qrcode_img_data', $args['qr'], $args['quality'], $args['size'], $args['frame'] ); ?>
							<img src="<?php echo $qrcode ?>" width="112px" >
						</td>
					</tr>
					<tr>
						<th id="doc_title"><?php echo $args['title']; ?></th>
					</tr>
				</table>
			</td>
		</tr>
	</tbody>
</table>
<br/>