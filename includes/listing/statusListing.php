<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Status_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_status";

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
			"title" 		=> "Title",
			"key"			=> "Key",
			"status" 		=> "Status",
			"type"			=> "Type",
			"order"			=> "Order",
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		$col = [
			'title' => [ "title", true ],
			'status' => [ "status", true ],
			'order' => [ "a.order", true ],
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
			'default' => array(
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

	public function column_title( $item ) 
	{	
		$actions = $this->get_actions( $item );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['title'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	/*public function column_key( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['key'] ]['key']}'>{$statuses[ $item['key'] ]['title']}</span>";

		return $html;
	}*/
	
} //class