<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Stage_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_stage";

	private $users;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		$this->users = get_simple_users();
	}

	public function set_refs()
	{
		global $wcwh;
		$this->refs = ( $this->refs )? $this->refs : $wcwh->get_plugin_ref();
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}
	
	public function get_columns() 
	{
		return array(
			"id"		=> "ID",
			"ref_type" 	=> "Reference Type",
			"ref_id" 	=> "Ref ID",
			"status" 	=> "Status",
			"proceed_status"	=> "Proceeding",
			"halt_status"	=> "Halting",
			"latest_stage"	=> "Latest",
			"action"	=> "Action",
			"remark"	=> "Remark",
			"action_by"	=> "Actor",
			"action_at"	=> "Datetime",
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		$col = [
			'id' => [ "id", true ],
			'ref_type' => [ "ref_type", true ],
			'ref_id' => [ "ref_id", true ],
			'latest_stage' => [ "latest_stage", true ],
			'action' => [ "action", true ],
			'action_at' => [ "action_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();
		
		return $actions;
	}

	public function action_statuses()
	{
		return array(
			'0' 	=> [ "key" => "normal", 'title' => 'Normal' ],
			'10'	=> [ "key" => "reopen", 'title' => 'Reopen' ],
			'20'	=> [ "key" => "confirmed", 'title' => 'Confirmed' ],
			'50'	=> [ "key" => "approved", 'title' => 'Approved' ],
			'60'	=> [ "key" => "processing", 'title' => 'Processing' ],
			'80'	=> [ "key" => "posted", 'title' => 'Posted' ],
			'90'	=> [ "key" => "completed", 'title' => 'Completed' ],
			'100'	=> [ "key" => "closed", 'title' => 'Closed' ],
			'-20'	=> [ "key" => "locked", 'title' => 'Locked' ],
			'-30'	=> [ "key" => "recall", 'title' => 'Recall' ],
			'-40'	=> [ "key" => "rejected", 'title' => 'Rejected' ],
			'-55'	=> [ "key" => "regret", 'title' => 'Regret' ],
			'-70' 	=> [ "key" => "on-hold", 'title' => 'On Hold' ],
			'-100' 	=> [ "key" => "cancelled", 'title' => 'Cancelled' ],
		);
	}

	public function get_data_alters( $datas = array() )
	{
		return $datas;
	}

    public function render()
    {
		if( ! $this ) return;

		$this->search_box( 'Search', "s" );
		$this->prepare_items();
		$this->display();
	}

	public function get_status_action( $item )
	{
		return array(
			'default' => array(
				'view' => [ 'wh_support' ],
			),
		);
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_id( $item ) 
	{	
		$actions = $this->get_actions( $item );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['id'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_proceed_status( $item )
	{
		$statuses = $this->action_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['proceed_status'] ]['key']}'>{$statuses[ $item['proceed_status'] ]['title']}</span>";

		return $html;
	}

	public function column_halt_status( $item )
	{
		$statuses = $this->action_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['halt_status'] ]['key']}'>{$statuses[ $item['halt_status'] ]['title']}</span>";

		return $html;
	}

	public function column_action_by( $item )
	{
		$user = ( $this->users )? $this->users[ $item['action_by'] ] : $item['action_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;

		return $user;
	}
	
} //class