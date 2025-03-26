<?php
if ( ! defined( 'ABSPATH' ) ) exit;

do_action( 'wcwh_email_header', $args ); 

$doc = $args['doc'];
?>
Dear Staff,
<br><br>
LHDN validation for <strong><?php echo $doc['docno']; ?></strong> 
is <strong style="color:#00c851;"><u>VALID</u></strong>
<br><br>
UUID: <?php echo $doc['uuid']; ?>
<?php if( $doc['qrcode'] ): ?>
<br>QR:
<br><img id="qrcode" src="<?php echo $doc['qrcode']; ?>" style="width:128px;" > 
<br><a href="<?php echo $doc['irb_url']; ?>" target="_blank">View on LHDN</a>
<?php endif; ?>
<br><br>

<?php do_action( 'wcwh_email_footer', $args ); ?>