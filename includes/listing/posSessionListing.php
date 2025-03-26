<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_POSSession_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_pos_session";

	public $seller = 0;

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
			"no" 			=> "",
			"register_name" => "POS Name",
			"register_id" 	=> "POS ID",
			"opened"		=> "Open At",
			"closed"		=> "Close At",
			"cashier_id"	=> "Cashier",
			"status"		=> "Status",
			"opening"		=> "Opening Amt",
			"closing"		=> "Closing Amt",
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		$col = [
			'register_name' => [ "register_name", true ],
			'register_id' => [ "register_id", true ],
			'opened' => [ "opened", true ],
			'closed' => [ "closed", true ],
			'cashier_id' => [ "display_name", true ],
			'opening' => [ "opening", true ],
			'closing' => [ "closing", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();
		
		return $actions;
	}

	public function get_data_alters( $datas = array() )
	{
		return $datas;
	}

	public function get_statuses()
	{
		return array(
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'1'		=> array( 'key' => 'yes', 'title' => 'Open' ),
			'0'		=> array( 'key' => 'no', 'title' => 'Closed' ),
			//'-1'	=> array( 'key' => 'deleted', 'title' => 'Deleted' ),
		);
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
				'edit' => [ 'wh_admin_support', 'manage_pos_order' ],
			),
		);
	}

	public function action_btn_addon( $btn, $item, $action = "view", $args = array() )
	{
		$icons = $this->get_icons();
		$actions = $this->refs['actions'];
		$services = ( $args['services'] )? : $this->section_id.'_action';
		$title = ( $args['title'] )? $args['title'] : $item['name'];
		$id = ( $args['id'] )? $args['id'] : ( ( $item['doc_id'] )? $item['doc_id'] : $item['id'] );
		$serial = ( $args['serial'] )? $args['serial'] : $item['serial'];
		
		$attrs = array();
		$html_attr = "";
		if( !empty( $args['datas'] ) )
		{
			foreach( $args['datas'] as $key => $value )
			{
				$attrs[] = "data-{$key}='{$value}'";
			}
			if( $attrs )
			{
				$html_attr = implode( " ", $attrs );
			}
		}

		if(DB_NAME == 'mndc' && $action == 'edit'){
			return;
		}
		
		switch( $action )
		{
			case 'edit':
				if( !$this->useFlag || 
					( $this->useFlag && $item['flag'] == 0 ) || 
					$this->forfeitFlagCheck || 
					current_user_cans( [ 'wh_support', 'wh_admin_support' ] ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'" 
					data-modal="wcwhModalForm" data-actions="close|submit" data-title="'.$actions[ $action ].' '.$title.'" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'closing-report':
				$params = [ 
					'page' => 'wc_pos-print', 
					'print' => 'report', 
					'report' => $item['register_id'], 
					'session' => $item['id'],
				];
				if( $this->seller > 0 ) $params['seller'] = $this->seller;
				$url = wp_nonce_url( admin_url( "admin.php".add_query_arg( $params, '' ) ), 'print_pos_report' );

				$btn = '<a class="btn btn-xs btn-info" title="Reprint Closing Report"
					href="'.$url.'" target="_blank"
					><i class="fa fa-print"></i></a>';
			break;
		}

		return $btn;
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_no( $item ) 
	{	
		return $item['no'];
    }

    //http://localhost/minimart/pmn/wp-admin/admin.php?page=wc_pos_registers&report=3&session=127
    //http://localhost/minimart/pmn/wp-admin/admin.php?page=wc_pos-print&print=report&report=3&session=127&_wpnonce=0ad6a89dbe
	public function column_register_name( $item ) 
	{	
		if( ! $item['status'] )
		{
			if( current_user_cans( [ 'wh_admin_support', 'manage_pos_order' ] ) ) $actions['edit'] = $this->get_action_btn( $item, 'edit' );
			$actions['closing-report'] = $this->get_action_btn( $item, 'closing-report' );
		}
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['register_name'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_cashier_id( $item )
	{
		return $item['display_name'];
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}
	
} //class