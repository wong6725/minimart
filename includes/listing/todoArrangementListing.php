<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_todoArrangement_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_arrangement";

	public $useFlag;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();
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
			'cb'			=> '<input type="checkbox" />',
			"section"		=> "Section",
			"id"			=> "ID",
			"match_status"	=> "Match Status",
			"match_proceed" => "Match Proceed",
			"match_halt"	=> "Match Halt",
			"action_type"	=> "Action Type",
			"title"			=> "Title",
			"desc"			=> "Description",
			"status"		=> "Status",
			"created_at"	=> "Added Date",
			"lupdate_at"	=> "Update Date",
		);
	}

	public function get_hidden_column()
	{
		$col = array();

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'section' => [ "section", true ],
			'id' => [ "id", true ],
			'action_type' => [ "action_type", true ],
			'created_at' => [ "created_at", true ],
			'lupdate_at' => [ "lupdate_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'wh_configure' ) )
			$actions['delete'] = $this->refs['actions']['delete'];
		
		return $actions;
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
			'0' => array(
				'view' => [ 'wh_configure' ],
				'restore' => [ 'wh_configure' ],
			),
			'1' => array(
				'view' => [ 'wh_configure' ],
				'edit' => [ 'wh_configure' ],
				'delete' => [ 'wh_configure' ],
			),
		);
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['id'], $item['id'] );
		
		return $html;
    }

	public function column_section( $item ) 
	{	
		$actions = $this->get_actions( $item, $item['status'] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['section'].' - '.$item['section_title'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_action_type( $item )
	{
		return $this->refs['action_type'][ $item['action_type'] ];
	}
	
} //class