<?php
if ( !defined("ABSPATH") )
    exit;
	
if ( !class_exists( "WCWH_Notices" ) )
{

class WCWH_Notices
{
	protected $notices = array();
	
	protected $prevNotices = array();

	public $messages = array();

	public $expiry = 60;
	
	public function __construct()
	{
		$this->set_notice_message();
	}

	public function __destruct()
	{
		
	}

	public function set_notice_message()
	{
		$this->messages = apply_filters( 'wcwh_notice_messages', array(
				"oops"				=> "Oops!~~",
				"unauthorized"		=> "UNAUTHORIZED Access!",
				"invalid-submit"	=> "Oops! Invalid submission.",
				"invalid-action"	=> "No valid action selected.",
				"no-selection"		=> "No item selected.",
				"not-unique"		=> "Record found and its unique.",
				"arrange-fail"		=> "Arrangement failed.",
				
				"success"			=> "Action performed Successfully!",
				"warning"			=> "Oops! Possible incorrect inputs.",
				"error"				=> "Oops! Action Failed.",
				"bulk-select"		=> "Select some row to proceed.",
				"bulk-action"		=> "Please select action to proceed.",
				"bulk-validate"		=> "Oops! Selected item(s) invalid to proceed. Please select wisely.",
				"on-hold"			=> "Oops! This document has marked as On-Hold.",
				"prevent-action"	=> "Action not allow as it's progress further ahead.",
				//
				"insufficient-data"	=> "Not enough info to proceed.",
				
				//System messages
				"missing-parameter"	=> "Missing info", 
				"invalid-input"		=> "Invalid input",
				"invalid-action"	=> "Invalid action",
				"update-fail"		=> "Update failed",
				"create-fail"		=> "Create failed",
				"delete-fail"		=> "Delete failed",
				"post-fail"		=> "Post failed",
				"unpost-fail"		=> "UnPost failed",
				"invalid-record"	=> "Invalid record",
				"action-fail"		=> "Action failed",
				"submission-fail"	=> "Submission Failed",
				//
				"debug"				=> "DEBUG MODE IS ON",
			) 
		);
	}

	public function get_notice_messages( $code = '' )
	{
		if( $code )
		{
			if( !empty( $this->messages[$code] ) )
				return $this->messages[$code];
			else
				return $code;
		}

		return $this->messages;
	}
	
	/**
	 *	Set Notice
	 *	type: none, info, error, warning, success
	 */
	public function set_notice( $code = "", $type = "info", $remark = "" )
	{
		if( empty( $code ) ) return;

		$this->notices[] = array( 
			'type' 			=> $type, 
			'message' 		=> $this->get_notice_messages( $code ), 
			'remark' 		=> $remark,
		);
	}
	
	public function set_notices( $notices = array() )
	{
		if( ! $notices ) return;
		$this->notices = array_merge( $this->notices, $notices );
	}

	public function has_notice()
	{
		return ( is_array( $this->notices ) && $this->notices )? true : false;
	}

	/**
	 *	Count Notice by type
	 *	type: none, info, error, warning, success
	 */
	public function count_notice( $type = "error" )
	{
		$count = 0;
		foreach( $this->notices as $i => $notice )
		{
			if( $notice['type'] == $type ) $count++;
		}

		return $count;
	}

	public function get_notice( $type = "" )
	{
		if( ! $type )
			return $this->notices;
		else
		{
			$notices = array();
			foreach( $this->notices as $i => $notice )
			{
				if( $notice['type'] == $type )
				{
					$notices[] = $notice;
				}
			}

			return $notices;
		}
	}
	
	public function remove_notice( $type = "" )
	{
		if( ! $type )
		{
			$this->prevNotices = $this->notices;
			$this->notices = array();
		}
		else
		{
			foreach( $this->notices as $i => $notice )
			{
				if( $notice['type'] == $type )
				{
					unset( $this->notices[$i] );
				}
			}
		}
	}
	
	public function reset_operation_notice()
	{
		$this->remove_notice();
	}
	
	public function get_previous_notice()
	{
		return $this->prevNotices;
	}
	
	public function get_operation_notice( $type = "" )
	{
		if( ! $this->has_notice() ) return;
		
		$notices = $this->get_notice( $type );
		
		$this->remove_notice();
		
		return $notices;
	}
	
	public function notices( $dismissable = true )
	{
		if( ! $this->has_notice() ) return;
		
		foreach( $this->get_notice() as $i => $row )
		{
			$args = array();
			$args['dismissable'] 	= $dismissable;
			$args['notice_type'] 	= $row['type'];
			$args['message'] 		= $row['message'];

			do_action( 'wcwh_get_template', 'segment/notice.php', $args );
		}
		
		$this->remove_notice();
	}

	public function get_notices( $dismissable = true )
	{
		ob_start();
		
		$this->notices( $dismissable );

		return ob_get_clean();
	}

	public function set_transient( $key, $code = "", $type = "info" )
	{
		set_transient( get_current_user_id().$key, $this->get_notice_messages( $code )."||".$type, $this->expiry );
	}

	public function transient_notices( $key )
	{
		if( $msg_string = get_transient( get_current_user_id().$key ) ) 
		{
			delete_transient( get_current_user_id().$key );

			$msg_obj = explode( '||', $msg_string );
			
			$args = array();
			$args['dismissable'] 	= true;
			$args['notice_type'] 	= $msg_obj[1];
			$args['message'] 		= $msg_obj[0];

			do_action( 'wcwh_get_template', 'segment/notice.php', $args );
		}
	}

	public function get_transient_notices( $key ){
		ob_start();
		
		$this->transient_notices( $key );

		return ob_get_clean();
	}
}

}