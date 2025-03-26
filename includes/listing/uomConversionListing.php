<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_UOMConversion_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_uom_conversion";

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
			"from_uom" 	=> "From UOM",
			"to_uom" 	=> "To UOM",
			"from_unit"	=> "From Unit",
			"to_unit"	=> "To Unit",
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		$col = [
			'from_uom' => [ "from_uom", true ],
			'to_uom' => [ "to_uom", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'delete_'.$this->section_id ) )
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
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
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

	public function column_from_uom( $item ) 
	{	
		$actions = $this->get_actions( $item );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['from_uom'].'</strong>', $this->row_actions( $actions, true ) );  
	}
	
} //class