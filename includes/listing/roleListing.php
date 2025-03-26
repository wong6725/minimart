<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_role_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_permission";

	public $def_roles = [];

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
			'cb'		=> '<input type="checkbox" />',
			"role" 		=> "Role",
			"name" 		=> "Role Name",
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		$col = [];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();
		
		return $actions;
	}

    public function render()
    {
		if( ! $this ) return;

		//$this->search_box( 'Search', "s" );
		$this->prepare_items();
		$this->display();
	}

	public function get_status_action( $item )
	{
		return [
			'default' => [
				'view' => [ 'wh_manage_role' ],
				//'edit' => [ 'wh_manage_role' ],
				'delete' => [ 'wh_manage_role' ],
			],
		];
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

	public function column_role( $item ) 
	{	
		if( ! in_array( $item['role'], $this->def_roles ) )
			$actions = $this->get_actions( $item );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['role'].'</strong>', $this->row_actions( $actions, true ) );  
	}
	
} //class