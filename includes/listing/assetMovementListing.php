<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_AssetMovement_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_asset_movement";

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
			'cb'			=> '<input type="checkbox" />',
			"code" 			=> "Movement Code",
			"asset_no"		=> "Asset No.",
			"asset_code"	=> "Asset Code",
			"location_code"	=> "Destination",
			"post_date"		=> "Dispatch Date",
			"end_date"		=> "Complete Date",
			"status"		=> "Status",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
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

		if( current_user_can( 'complete_'.$this->section_id ) )
			$actions['complete'] = $this->refs['actions']['complete'];
		
		return $actions;
		
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
				'view' => [ 'wcwh_user' ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
			),
			'6'	=> array(
				'view' => [ 'wcwh_user' ],
				'complete' => [ 'complete_'.$this->section_id ],
			),
		);
	}

	public function get_action_btn( $item, $action = "view", $args = array() )
	{
		$btn = "";
		$icons = $this->get_icons();
		$actions = $this->refs['actions'];
		$services = ( $args['services'] )? : $this->section_id.'_action';
		$title = ( $args['title'] )? $args['title'] : $item['name'];
		$id = ( $args['id'] )? $args['id'] : ( ( $item['doc_id'] )? $item['doc_id'] : $item['id'] );
		$serial = ( $args['serial'] )? $args['serial'] : $item['serial'];
		
		switch( $action )
		{
			case 'view':
			default:
				$btn = '<a class="linkAction toolTip btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'" 
					data-modal="wcwhModalView" data-actions="close" data-title="'.$actions[ $action ].' '.$title.'" 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'edit':
				if( !$this->useFlag || ( $this->useFlag && $item['flag'] == 0 ) )
				$btn = '<a class="linkAction toolTip btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'" 
					data-modal="wcwhModalForm" data-actions="close|submit" data-title="'.$actions[ $action ].' '.$title.'" 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'complete':
				$btn = '<a class="linkAction toolTip btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?"
					>Complete</a>';
			break;
			case 'qr':
				$btn = '<a class="jsPrint toolTip btn btn-xs btn-info btn-none-'.$action.'" title="Print '.$actions[ $action ].'" 
					data-code="'.$serial.'" data-print_type="qr" 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'barcode':
				$btn = '<a class="jsPrint toolTip btn btn-xs btn-info btn-none-'.$action.'" title="Print '.$actions[ $action ].'" 
					data-code="'.$serial.'" data-print_type="barcode" 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
		}

		return $btn;
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

	public function column_code( $item ) 
	{	
		$actions = $this->get_actions( $item, $item['status'] );
		
		return sprintf( '%1$s %2$s', $indent.'<strong>'.$item['code'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_asset_no( $item )
	{
		$actions = array();
		if( $item['asset_no'] )
		{
			$actions['qr'] = $this->get_action_btn( $item, 'qr', [ 'serial'=>$item['asset_no'] ] );
			$actions['barcode'] = $this->get_action_btn( $item, 'barcode', [ 'serial'=>$item['asset_no'] ] );
		}
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['asset_no'].'</strong>', $this->row_actions( $actions, true ) ); 
	}

	public function column_asset_code( $item )
	{
		$html = $item['asset_code'];
		$args = [ 'id'=>$item['asset_id'], 'service'=>'wh_asset_action', 'title'=>$html, 'permission'=>[ 'access_wh_asset' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_location_code( $item )
	{
		$val = [ $item['location_code'], $item['wh_code'], $item['comp_name'] ];
		$val = array_filter( $val );
		$html = implode( ', ', $val );
		$args = [ 'id'=>$item['comp_id'], 'service'=>'wh_company_action', 'title'=>$html, 'permission'=>[ 'access_wh_company' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_created( $item )
	{
		$user = ( $this->users )? $this->users[ $item['created_by'] ] : $item['created_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;
		$date = $item['created_at'];

		$html = $user.'<br>'.$date;
		
		$args = [ 
			'action' => 'view_doc', 
			'id' => $item['id'], 
			'service' => 'wh_stage_action', 
			'title' => $html, 
			'desc' => 'View State Change',
			'permission' => [ 'wcwh_user' ] 
		];
		return $this->get_external_btn( $html, $args );
	}

	public function column_lupdate( $item )
	{
		$user = ( $this->users )? $this->users[ $item['lupdate_by'] ] : $item['lupdate_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;
		$date = $item['lupdate_at'];

		$html = $user.'<br>'.$date;

		$args = [ 
			'action' => 'view_doc', 
			'id' => $item['id'], 
			'service' => 'wh_logs_action', 
			'title' => $html, 
			'desc' => 'View History',
			'permission' => [ 'wcwh_user' ] 
		];
		return $this->get_external_btn( $html, $args );
	}
	
} //class