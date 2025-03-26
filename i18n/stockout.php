<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	0 	=> array( "ref_id" => "1", "name" => "FIFO", "order_type" => "doc_post_date", "ordering" => "ASC", "priority" => 1, "default_value" => "" ),
	1	=> array( "ref_id" => "2", "name" => "FEFO,FIFO", "order_type" => "prod_expiry", "ordering" => "ASC", "priority" => 1, "default_value" => "1970-01-01" ),
	2	=> array( "ref_id" => "2", "name" => "FEFO,FIFO", "order_type" => "doc_post_date", "ordering" => "ASC", "priority" => 2, "default_value" => "" ),
	3	=> array( "ref_id" => "3", "name" => "LIFO", "order_type" => "doc_post_date", "ordering" => "DESC", "priority" => 1, "default_value" => "" ),
);