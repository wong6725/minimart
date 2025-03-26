<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_TodoAction_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_todo_action";

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
			"arr_id" 		=> "Arr ID",
			"section"		=> "Section ID",
			"section_name"	=> "Section Name",
			"next_action" 	=> "Next Action",
			"responsible"	=> "Responsible",
			"trigger_action" => "Trigger Action",
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		return array();
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

	public function column_arr_id( $item ) 
	{	
		$actions = $this->get_actions( $item );

		$html = $item['arr_id'];
		$args = [ 'id'=>$item['arr_id'], 'service'=>'wh_arrangement_action', 'title'=>$html, 'permission'=>[ 'wh_configure' ] ];
		$html = $this->get_external_btn( $html, $args );
		
		return sprintf( '%1$s %2$s', '<strong>'.$html.'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_next_action( $item )
	{
		return $this->refs['actions'][ $item['next_action'] ];
	}

	public function column_trigger_action( $item )
	{
		return $this->refs['actions'][ $item['trigger_action'] ];
	}
	
} //class