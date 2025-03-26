<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load colours
$bg              = get_option( 'woocommerce_email_background_color' );
$body            = get_option( 'woocommerce_email_body_background_color' );
$base            = get_option( 'woocommerce_email_base_color' );
$base_text       = wc_light_or_dark( $base, '#202020', '#ffffff' );
$text            = get_option( 'woocommerce_email_text_color' );

// Pick a contrasting color for links.
$link = wc_hex_is_light( $base ) ? $base : $base_text;
if ( wc_hex_is_light( $body ) ) {
	$link = wc_hex_is_light( $base ) ? $base_text : $base;
}

$bg_darker_10    = wc_hex_darker( $bg, 10 );
$body_darker_40  = wc_hex_darker( $body, 80 );
$base_lighter_20 = wc_hex_lighter( $base, 20 );
$base_lighter_40 = wc_hex_lighter( $base, 40 );
$text_lighter_20 = wc_hex_lighter( $text, 20 );

// !important; is a gmail hack to prevent styles being stripped if it doesn't like something.
?>

#body_content, .text,
body, h1, h2, h3, h4, h5, h6, p, b, a, span, div, pre, sup, sub, td, th
{
	font-family: helveticamono, Consolas, "Courier New", monospace;
	font-size:12px;
	color: <?php echo esc_attr( $text ); ?>;
	margin:0px;
	line-height:1;
}

#body_content 
{
	background-color: <?php echo esc_attr( $body ); ?>;
	position:relative;
}

#body_content table td, #body_content table th {
	padding: 2px 6px;
}

#body_content table td td {
	padding: 1px 0;
}

#body_content table td th {
	padding: 3px;
}

.leftered{ text-align:left; }
.rightered{ text-align:right; }
.centered{ text-align:center; }

.bold{ font-weight:bold; }

.td {
	padding:2px;
	border-left: 1px solid <?php echo esc_attr( $text ); ?>; 
	border-right: 1px solid <?php echo esc_attr( $text ); ?>;
	border-top: 1px solid <?php echo esc_attr( $text ); ?>; 
	border-bottom: 1px solid <?php echo esc_attr( $text ); ?>; 
}
.td-off{ border: 0px solid; }
.td-offt{ border-top: 0px none; }
.td-offb{ border-bottom: 0px none; }
.td-offl{ border-left: 0px none; }
.td-offr{ border-right: 0px none; }

.border-bottom{ border-bottom: 1px solid <?php echo esc_attr( $text ); ?>; }
.border-top{ border-top: 1px solid <?php echo esc_attr( $text ); ?>; }
.border-left{ border-left: 1px solid <?php echo esc_attr( $text ); ?>; }
.border-right{ border-right: 1px solid <?php echo esc_attr( $text ); ?>; }

.link {
	color: <?php echo esc_attr( $base ); ?>;
}

h1 { font-size: 26px; }
h2 { font-size: 22px; }
h3 { font-size: 18px; }
h4 { font-size: 14px; }
h5 { font-size: 12px; }
h6 { font-size: 10px; }

a 
{
	font-weight: normal;
	text-decoration: underline;
}

img 
{
	border: none;
	display: inline;
	height: auto;
}

.page-break
{
	page-break-after: always !important;
}

/* Customization */
#doc_heading .heading_info .main
{
	padding-left: 2px;
}
#doc_heading .heading_info .second
{
	padding-left:6px;
}
#doc_heading .heading_info .second b
{ 
	display: flex; 
	width:20px; 
}
#doc_heading #doc_title
{ 
	background-color: <?php echo esc_attr( $base ); ?>;
	font-size:14px; text-align:center; 
}
#doc_qr
{
	text-align:center;
}