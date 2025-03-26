<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>REMITTANCE COLLECTOR <?php echo $args['heading']['docno']; ?></title>
		<style>
		<?php 
			//include global template style/css
			do_action( 'wcwh_get_template', 'template/styles.php' );
		?>
			body, p, b, strong, label, h1, h2, h3, h4, h5, h6, a, span, td, th
			{
				font-family: tahoma !important;
				color: #000 !important;
				letter-spacing:0.8px !important;
			}
			body, p, b, a, span, td, th
			{
				font-size:10px;
			}
			@media print {
	            @page 
				{ 
					margin: 0px 5px 0px 5px;
				}
				body
	            {
	            	width:auto;
	            }
	        }

			@media screen {
	            body
	            {
	            	width: 80mm;
	            }
	        }
			.header_logo, .header_company, .header_title
			{
				text-align: center;
				font-size: 12px;
			}
		
		</style>
	</head>
	<body id="body_content"> 
		<?php
			$heading = $args['heading'];
			$detail = $args['detail'];
			
			$currency = get_woocommerce_currency();//get_woocommerce_currency_symbol();
		?>
		<?php
		for ($i=0; $i < 2; $i++)
		{ 
			?>
				<div id="header"> 
					<div class="header_logo">
					<?php
						$img = get_option( 'woocommerce_email_receipt_image' );
						if ( $img ) 
						{
							//$logo = str_replace( site_url(), ABSPATH, $img );
							echo '<p class="centered"><img width="150" src="' . $img . '" /></p>';
						}
					?>
					</div>
					<div class="header_company">
					<?php $header_text = get_option( 'woocommerce_email_receipt_header_text' ); ?>
						<strong><?php echo $header_text; ?></strong>
					</div>
					<br>
					<div class="header_title">
						<strong>Remittance Money Collector</strong>
					</div>
					<div class="header_title">
						<span><?php echo $args['heading']['company'] ?></span>
					</div>
					<br>
					<div class="header_content">
						<table border="0" cellpadding="0" cellspacing="0" width="100%">
							<tr>
								<td valign='top' width='35%'><strong>Doc No</strong></td>
								<td valign='top' class='rightered' width='1%'>:</td>
								<td valign='top' class='rightered'><?php echo $args['heading']['docno']; ?></td>
							</tr>
							<tr>
								<td valign='top' width='35%'><strong>Doc Date</strong></td>
								<td valign='top' class='rightered' width='1%'>:</td>
								<td valign='top' class='rightered'><?php echo $args['heading']['doc_date']; ?></td>
							</tr>
						<?php
							if( !empty( $heading['infos1'] ) )
							{
								foreach( $heading['infos1'] as $title => $info ){
									echo "<tr>";
									echo "<td valign='top' width='35%'><strong>{$title}</strong></td>";
									echo "<td valign='top' class='rightered' width='1%'>:</td>";
									echo "<td valign='top' class='rightered'>{$info}</td>";
									echo "</tr>";
								}
							}
							?>
							
								<tr>
									<td class="td td-offl td-offr td-offt leftered" colspan="3" valign="bottom" style="height: 70px;">
										Sign:
									</td>
								</tr>
								<tr>
									<td colspan="3"><strong>Remittance Money Send By</strong></td>
								</tr>
								<tr>
									<td>Name:</td><td></td><td></td>
								</tr>
								<tr>
									<td>Date:</td><td></td><td></td>
								</tr>

								<tr>
									<td class="td td-offl td-offr td-offt leftered" colspan="3" valign="bottom" style="height: 70px;">
										Sign:
									</td>
								</tr>
								<tr>
									<td colspan="3"><strong>Remittance Money transit collector by</strong></td>
								</tr>
								<tr>
									<td>Name:</td><td></td><td></td>
								</tr>
								<tr>
									<td>Date:</td><td></td><td></td>
								</tr>

								<tr>
									<td class="td td-offl td-offr td-offt leftered" colspan="3" valign="bottom" style="height: 70px;">
										Sign:
									</td>
								</tr>
								<tr>
									<td colspan="3"><strong>HQ Remittance Money collector by</strong></td>
								</tr>
								<tr>
									<td>Name:</td><td></td><td></td>
								</tr>
								<tr>
									<td>Date:</td><td></td><td></td>
								</tr>

								<tr><td><br><br><br></td></tr>
							<?php
								if( !empty( $heading['infos3'] ) )
								{
									foreach( $heading['infos3'] as $title => $info ){
										echo "<tr>";
										echo "<td valign='top' width='30%'><strong>{$title}</strong></td>";
										echo "<td valign='top' class='rightered' width='1%'>:</td>";
										echo "<td valign='top' class='rightered'>{$info}</td>";
										echo "</tr>";
									}
								}
							?>
						</table>
						<br>
					</div>	
				</div>
				<div class="page-break"></div>
			<?php
		}
		?>
	</body>

	<?php if( $args['print'] ): ?>
		<script>
			window.print();
		</script>
	<?php endif; ?>
</html>