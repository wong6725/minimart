<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array( 
		"section" => "permission", 
		"section_id" => "wh_permission", 
		"scheme" => array(
			"role" => 0,
			"user" => 10,
		),
	),
	array( 
		"section" => "storage_group_rel", 
		"section_id" => "wh_storage_group", 
		"scheme" => array(
			"default" => 0,
			"category" => 10,
			"item" => 20,
		),
	),
	array( 
		"section" => "storage_term_rel", 
		"section_id" => "wh_storage_term", 
		"scheme" => array(
			"default" => 0,
			"category" => 10,
			"item" => 20,
		),
	),
	array( 
		"section" => "pricing", 
		"section_id" => "wh_pricing", 
		"scheme" => array(
			"default" => 0,
			"client_code" => 10,
			"customer_group" => 20,
		),
	),
	array( 
		"section" => "credit_limit", 
		"section_id" => "wh_credit", 
		"scheme" => array(
			"default" => 0,
			"customer_group" => 10,
			"customer" => 20,
		),
	),
);