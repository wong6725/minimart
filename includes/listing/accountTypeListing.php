<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_AccountType_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_account_type";

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
			"name" 		=> "Account Type",
			"code" 		=> "Type Code",
			"employee_prefix" => "Employee ID Prefix",
			"def_cgroup_id" => "Default Customer Group",
			"term_days" 	=> "Start Date",
			"desc"		=> "Description",
			"auto_topup"=> "Auto Credit Topup",
			"status"	=> "Status",
		);
	}

	public function get_hidden_column()
	{
		$cols = [];

		if( ! current_user_cans( [ 'wh_admin_support' ] ) ) $cols[] = 'auto_topup';

		return $cols;
	}

	public function get_sortable_columns()
	{
		$col = [
			'name' => [ "name", true ],
			'code' => [ "code", true ],
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
		$actions = array(
			'0' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support' ],
				'restore' => [ 'restore_'.$this->section_id ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
			),
		);

		return $actions;
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

	public function column_name( $item ) 
	{	
		$actions = $this->get_actions( $item, $item['status'] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['name'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_def_cgroup_id( $item )
	{
		$html = $item['cgroup_code']." - ".$item['cgroup_name'];
		$args = [ 'id'=>$item['def_cgroup_id'], 'service'=>'wh_customer_group_action', 'title'=>$html, 'permission'=>[ 'access_wh_customer_group' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_auto_topup( $item )
	{
		$html = [];

		$html[] = ( $item['auto_topup'] )? 'Yes' : 'No';
		if( $item['auto_topup'] )
		{
			$html[] = $item['plant'];
			$html[] = $item['sv'];
		}

		return implode( " | ", $html );
	}
	
	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}
	
} //class
