<?php
if ( ! defined( 'ABSPATH' ) ) exit;

do_action( 'wcwh_email_header', $args ); 

$doc = $args['doc'];
?>
Dear Staff,
<br><br>
LHDN validation for <strong><?php echo $doc['docno']; ?></strong> 
is <strong style="color:red;"><u>INVALID</u></strong>
<br><br>
<?php if( $doc['submission_error'] ): ?>
LHDN Message: <?php echo $doc['submission_error']; ?>
<?php endif; ?>

<?php do_action( 'wcwh_email_footer', $args ); ?>