<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array( "status" => -1, "type" => "default", "key" => "deleted", "title" => "Deleted", "order" => 0 ),
	array( "status" => 0, "type" => "default", "key" => "inactive", "title" => "Trashed", "order" => 1 ),
	array( "status" => 1, "type" => "default", "key" => "active", "title" => "Initial", "order" => 10 ),
	array( "status" => 6, "type" => "default", "key" => "posted", "title" => "Posted", "order" => 8 ),
	array( "status" => 9, "type" => "default", "key" => "completed", "title" => "Completed", "order" => 6 ),
	array( "status" => 10, "type" => "default", "key" => "closed", "title" => "Closed", "order" => 2 ),
	array( "status" => -1, "type" => "flag", "key" => "rejected", "title" => "Rejected", "order" => 0 ),
	array( "status" => 0, "type" => "flag", "key" => "pending", "title" => "Pending", "order" => 2 ),
	array( "status" => 1, "type" => "flag", "key" => "approved", "title" => "Approved", "order" => 1 ),
);