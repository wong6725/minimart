<?php
if ( ! defined( 'ABSPATH' ) ) exit;

do_action( 'wcwh_email_header', $args ); 
?>

<p class="leftered"><?php echo $args['message']; ?></p>

<?php do_action( 'wcwh_email_footer', $args ); ?>