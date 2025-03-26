<?php
//mimic the actuall admin-ajax
define('DOING_AJAX', true);

if( ! isset( $_REQUEST['action'] ) )
    die('-1');

//to the relative location of the wp-load.php
require_once( '../../../wp-load.php' ); 

//Typical headers
header('Content-Type: text/html');
send_nosniff_header();

//Disable caching
header('Cache-Control: no-cache');
header('Pragma: no-cache');


//Add required core functions
/** WordPress Administration Screen API */
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

/** WordPress Template Administration API */
require_once ABSPATH . 'wp-admin/includes/template.php';

/** WordPress List Table Administration API and base class */
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table-compat.php';
require_once ABSPATH . 'wp-admin/includes/list-table.php';


if( in_array( $_REQUEST['action'], [ 'print_pos_receipt' ] ) )
{
	do_action( 'wcwh_reception_init', $_REQUEST['action'] );
}
else
{
	$action = esc_attr( trim( $_REQUEST['action'] ) );

	if( strpos( $action, 'wcwh' ) !== false )
	{
	    if( is_user_logged_in() )
	        do_action( 'wcwh_ajax_'.$action );
	    else
	        do_action( 'wcwh_ajax_nopriv_'.$action );
	}
	else{
	    die('-1');
	} 
}
