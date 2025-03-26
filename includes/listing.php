<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WP_List_Table" ) ) 
{
	require_once( ABSPATH . "wp-admin/includes/class-wp-list-table.php" );
}

if ( !class_exists( "WCWH_Listing" ) )
{

	/**
	 *	Function to activate & overridden in subclass
	 *
	 *	print_column_footers()
	 *	filter_search()
	 *	extra_row()
	 */

class WCWH_Listing extends WP_List_Table 
{
	protected $setting = [];

	protected static $data;
	protected $found_data;
	
	protected $columns = array();
	protected $details = array();
	protected $sortable  = array();
	protected $hides = array();
	protected $args = array();
	protected $header_cols = array();
	protected $header_match = '';
	protected $prev_match = '';
	
	public $filters = array();
	public $viewStats = array();
	public $statField = '';
	public $bulks = array();
	
	public $isSearch = false;
	public $advSearch = array();
	public $styles = array();

	public $print_view = false;
	public $generated = false;

	public $paginate = true;
	public $per_page_limit = 100;
	public $hasDBOrd = false;

	protected $warehouse = array();
	public $view_outlet = false;

	public function __construct()
	{
		parent::__construct( array(
			"singular"  => "Row",	//singular name of the listed records
			"plural"    => "Rows",	//plural name of the listed records
			"ajax"      => true   	//does this table support ajax?
		) );

		global $wcwh;
		$this->setting = $wcwh->get_setting();
		
		$this->set_args();
	}

	public function __destruct()
	{
		unset($this->setting);
		unset($this->warehouse);
		unset($this->details);
	}

	public function set_warehouse( $wh )
	{
		$this->warehouse = $wh;

		if( ! $wh['indication'] && $wh['view_outlet'] ) $this->view_outlet = true;
		if( $this->users && ! empty( $wh['dbname'] ) ) $this->users = get_simple_users( 0, true, $wh['dbname']."." );
	}

	public function listing( $columns = array(), $details = array(), $sortable = array(), $hides = array(), $args = array() )
	{
		$this->paginate = false;
		$this->generated = true;

		$this->set_columns( $columns );
		$this->set_details( $details );
		$this->set_sortable( $sortable );
		$this->set_hides( $hides );
		$this->set_args( $args );

		$this->prepare_items();
		$this->display();
	}

	public function get_listing( $columns = array(), $details = array(), $sortable = array(), $hides = array(), $args = array() )
	{
		ob_start();
		
		$this->listing( $columns, $details, $sortable, $hides, $args );

		return ob_get_clean();
	}

	public function set_columns( $columns = array() )
	{
		if( $columns && is_array( $columns ) )
		{
			$temp = [];
			foreach( $columns as $key => $val )
			{
				if( is_numeric( $key ) )
				{
					$temp[ $val ] = ucfirst( $val );
				}
			}

			if( !empty( $temp ) ) $columns = $temp;
		}

		if( $columns ) $this->columns = $columns;
	}

	public function set_details( $details = array() )
	{
		if( $details ) $this->details = $details;

		if( ! $this->columns && $details )
		{
			$keys = array_keys( $details[0] );
			$this->set_columns( $keys );
		}
	}

	public function set_sortable( $sortable = array() )
	{
		if( $sortable ) $this->sortable = $sortable;
	}

	public function set_hides( $hides = array() )
	{
		if( $hides ) $this->hides = $hides;
	}
	
	public function set_args( $args = array() )
	{
		$this->args = wp_parse_args(
			$args,
			array( 
				'per_page_row'	=> ( $this->paginate )? $this->per_page_limit : 999999, 
				'pagination' 	=> ( $this->paginate )? true : false,
				'order_by' 		=> '',
				'order' 		=> 'desc',
				'mobile_expand'	=> true,
				'class'			=> array(),
				'off_footer'	=> false,
				'list_only'		=> false,
			)
		);
	}

	public function no_items() 
	{
		echo "Nothing found.";
	}
	
	public function get_columns() 
	{
		return $this->columns;
	}

	public function get_header_cols() 
	{
		return $this->header_cols;
	}

	public function get_header_match() 
	{
		return $this->header_match;
	}
	
	public function get_sortable_columns() 
	{
		return $this->sortable;
	}
	
	public function get_hidden_column()
	{
		return $this->hides;
	}
	
	public function column_default( $item, $column_name ) 
	{
		return $item[$column_name];
	}
	
	public function get_listing_data()
	{
		return $this->details;
	}
	
	public function get_data_alters( $data = array() )
	{
		return $data;
	}
	
	public function get_statuses()
	{
		return array(
			'process' => array( 'key' => 'process', 'title' => 'Processing' ),
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'1'		=> array( 'key' => 'active', 'title' => 'Ready' ),
			'3'		=> array( 'key' => 'confirm', 'title' => 'Confirm' ),
			'6'		=> array( 'key' => 'posted', 'title' => 'Posted' ),
			'9'		=> array( 'key' => 'completed', 'title' => 'Completed' ),
			'10'	=> array( 'key' => 'closed', 'title' => 'Closed' ),
			'0'		=> array( 'key' => 'inactive', 'title' => 'Trashed' ),
			'-1'	=> array( 'key' => 'deleted', 'title' => 'Deleted' ),
		);
	}
	
	public function get_approvals()
	{
		return array(
			'0'		=> array( 'key' => 'pending', 'title' => 'Pending' ),
			'1'		=> array( 'key' => 'approved', 'title' => 'Approved' ),
			'-1'	=> array( 'key' => 'rejected', 'title' => 'Rejected' ),
		);
	}
	
	public function get_icons()
	{
		return array(
			'view'		=> 'fa-search',
			'edit'		=> 'fa-pencil-alt',
			'duplicate' => 'fa-copy',
			'restore'	=> 'fa-redo flip-x',
			'delete'	=> 'fa-trash-alt',
			'remove'	=> 'fa-recycle',
			'cancel'	=> 'fa-times-circle',
			'confirm'	=> 'fa-check-circle',
			'refute'	=> 'fa-times-circle',
			'post'		=> 'fa-share-square',
			'unpost'	=> 'fa-undo',
			'approve'	=> 'fa-check-circle',
			'reject'	=> 'fa-times-circle',
			'on-hold'	=> 'fa-ban',
			'email'		=> 'fa-envelope',
			'print'		=> 'fa-print',
			'qr'		=> 'fa-qrcode',
			'barcode'	=> 'fa-barcode',
			'delete-permanent' => 'fa-trash-alt',
			'sync'		=> 'fa-handshake',
			'complete'	=> 'fa-check-square',
			'incomplete'=> 'fa-square',
			'export'	=> 'fa-download',
			'import'	=> 'fa-upload',
			'close'		=> 'fa-power-off',
			'reopen'	=> 'fa-power-off',
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

		if( $this->view_outlet && ! $args['force'] ) $action = 'view';
		
		switch( $action )
		{
			case 'view':
			default:
				$action_btns = ( $this->print_view )? "print|close" : "close";
				$args = [ 'action'=>'print', 'id'=>$id, 'section'=>$this->section_id ];
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'" data-href="'.wcwh_href( $args ).'"
					data-modal="wcwhModalView" data-actions="'.$action_btns.'" data-title="'.$actions[ $action ].' '.$title.'"
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
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
			case 'duplicate':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'" 
					data-modal="wcwhModalForm" data-actions="close|submit" data-title="'.$actions[ $action ].' '.$title.'" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'restore':
				if( !$this->useFlag || ( $this->useFlag && $item['flag'] >= 0 ) || current_user_cans( [ 'wh_support', 'wh_admin_support' ] ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'delete':
				//if( !$this->useFlag || ( $this->useFlag && $item['flag'] <= 0 ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'delete-permanent':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-delete" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'cancel':
				//if( !$this->useFlag || ( $this->useFlag && $item['flag'] <= 0 ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'confirm':
				if( $this->useFlag && $item['flag'] == 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'refute':
				if( $this->useFlag && $item['flag'] == 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'approve':
				if( $this->useFlag && $item['flag'] == 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'reject':
				if( $this->useFlag && $item['flag'] == 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'post':
				if( $item['flag'] > 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'unpost':
				if( $item['flag'] > 0 && ( $item['t_uqty'] <= 0 ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'complete':
				if( ( $item['flag'] > 0 && ( $item['t_uqty'] > 0 ) ) || current_user_cans( [ 'wh_support', 'wh_admin_support' ] ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'incomplete':
				if( ( $item['flag'] > 0 && ( $item['t_uqty'] > 0 ) ) || current_user_cans( [ 'wh_support', 'wh_admin_support' ] ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'close':
				if( $item['flag'] > 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'reopen':
				if( $item['flag'] > 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'sync':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'qr':
				$btn = '<a class="jsPrint  btn btn-xs btn-info btn-none-'.$action.'" title="Print '.$actions[ $action ].'" 
					data-code="'.$serial.'" data-print_type="qr" '.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
				if( $args['tpl'] )
				{
					
					$btn = '<a class="jsTpl  btn btn-xs btn-info btn-none-'.$action.' toggle-modal" data-action="view" data-tpl="'.$args['tpl'].'" 
							data-title="Print '.$actions[ $action ].'" data-modal="wcwhModalPrint" 
							data-actions="print|close" title="Print '.$actions[ $action ].'" 
							data-code="'.$serial.'" data-print_type="qr" 
							'.$html_attr.' 
						><i class="fa '.$icons[ $action ].'"></i></a>';
				}
			break;
			case 'barcode':
				$btn = '<a class="jsPrint toolTip btn btn-xs btn-info btn-none-'.$action.'" title="Print '.$actions[ $action ].'" 
					data-code="'.$serial.'" data-print_type="barcode" 
					><i class="fa '.$icons[ $action ].'"></i></a>';
				if( $args['tpl'] )
				{
					$btn = '<a class="jsTpl toolTip btn btn-xs btn-info btn-none-'.$action.' toggle-modal" data-action="view" data-tpl="'.$args['tpl'].'" 
							data-title="Print '.$actions[ $action ].'" data-modal="wcwhModalPrint" 
							data-actions="print|close" title="Print '.$actions[ $action ].'" 
							data-code="'.$serial.'" data-print_type="barcode" data-width="'.( ( $args['width'] )? $args['width'] : 3 ).'"
							'.$html_attr.' 
						><i class="fa '.$icons[ $action ].'"></i></a>';
				}
			break;
			case 'print':
				$print_type = $item['print_type'];
				$print_type = ( $args['print_type'] )?  $args['print_type'] : $print_type;

				$view_type = $args['view_type'];

				$prnt_title = $item['doc_code'];
				$prnt_title = ( $args['doc_code'] )? $args['doc_code'] : $prnt_title;

				$title = $actions[ $action ];
				$title.= ( $args['title_addon'] )? ' '.$args['title_addon'] : '';

				$vals = [ 'action'=>$action, 'id'=>$id, 'section'=>$this->section_id, 'type'=>$print_type ];
				if( $view_type ) $vals['view_type'] = $view_type;

				$btn = '<a class="toolTip btn btn-xs btn-light btn-none-'.$action.'" title="'.$title.'" target="_blank"
					href="'.wcwh_href( $vals ).'"
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i> '.$prnt_title.' </a>';
			break;
		}

		$btn = method_exists( $this, 'action_btn_addon' )? $this->action_btn_addon( $btn, $item, $action, $args ) : $btn;

		return $btn;
	}
	
	public function get_external_btn( $btn, $args = array() )	//[ 'id'=>'', 'service'=>'', 'title'=>'' ]
	{
		if( !$args || !$args['id'] ) return $btn;
		
		$icons = $this->get_icons();
		$actions = $this->refs['actions'];
		
		$args['action'] = !empty( $args['action'] )? $args['action'] : 'view';
		$args['modal'] = !empty( $args['modal'] )? $args['modal'] : 'wcwhModalView';
		$args['modal_actions'] = !empty( $args['modal_actions'] )? $args['modal_actions'] : 'close';
		
		$permission = $args['permission'];
		if( empty( $permission ) || ( $permission && current_user_cans( $permission ) ) )
		{
			$desc = $args['desc'] ? $args['desc'] : $actions[ $args['action'] ].' '.$args['title'];
			$btn = '<a class="linkAction toolTip cursor-pointer" title="'.$actions[ $args['action'] ].'" 
					data-id="'.$args['id'].'" data-action="'.$args['action'].'" data-service="'.$args['service'].'" 
					data-modal="'.$args['modal'].'" data-actions="'.$args['modal_actions'].'" data-title="'.$desc.'" 
					>'.$args['title'].'</a>';
		}

		return $btn;
	}
	
	public function get_status_action( $item )
	{
		return array();
	}
	
	public function get_actions( $item, $status = 'default', $args = array() )
	{
		$status_actions = $this->get_status_action( $item );
		$actions = [];

		if( $status_actions && $status_actions[ (string) $status ] && ! $this->view_outlet )
		{
			foreach( $status_actions[ (string) $status ] as $action => $permission )
			{	
				if( empty( $permission ) || ( $permission && current_user_cans( $permission ) ) )
				{
					$actions[] = $this->get_action_btn( $item, $action, $args );
				}
			}
		}
		else
		{
			$actions[] = $this->get_action_btn( $item, 'view', $args );
		}
		
		return $actions;
	}
	
	public function get_views()
    {
    	if( ! $this->viewStats ) return;
		
		$field = ( $this->statField )? $this->statField : 'status';
		
		$statuses = $this->get_statuses();
		$current = ( isset( $this->filters[$field] ) && $this->filters[$field] !== "" )? $this->filters[$field] : "all";

        $stats = array();
        foreach( $statuses as $a => $value )
        {
        	if( isset( $this->viewStats[ (string)$a ] ) && $this->viewStats[ (string)$a ] >= 0 )
        	{
        		$stats[ (string)$a ] = $this->viewStats[ (string)$a ];
        	}
        }

        $views = array();
        if( $stats )
        {
        	foreach( $stats as $stat => $count )
        	{
				$class = array( 'btn-outline-'.$statuses[$stat]['key'] );
				if( (string)$current === (string)$stat ) $class[] = 'active';
				
        		$btn = '<a class="btn btn-xs '.implode( ' ', $class ).' " data-'.$field.'="'.$stat.'" >';
        		$btn.= $statuses[$stat]['title'].'&nbsp;<sup class="count">'.$count.'</sup></a>';
        		$views[ $statuses[$stat]['key'] ] = $btn;
        	}
        }

        return $views;
    }

    public function advSearch_onoff( $excl = [] )
    {
    	$filters = $this->filters;

    	$temp = $filters; 
    	unset( $temp['status'] ); 
    	unset( $temp['qs'] );
    	unset( $temp['paged'] );
    	unset( $temp['orderby'] );
    	unset( $temp['order'] ); 

    	if( $excl )
    	{
    		foreach( $excl as $xcl )
    		{
    			unset( $temp[ $xcl ] );
    		}
    	}
    	
    	$temp = array_filter( $temp );
		if( !empty( $temp ) ) $this->isSearch = true;
		else $this->isSearch = false;
    }

    public function get_pagenum() 
	{
		$pagenum = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 0;
		$pagenum = !empty( $this->filters['paged'] )? $this->filters['paged'] : $pagenum;

		if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] ) {
			$pagenum = $this->_pagination_args['total_pages'];
		}

		return max( 1, $pagenum );
	}

	public function usort_reorder( $a, $b ) 
	{
		$filters = $this->filters;
		$orderby = ( $filters['orderby'] )? $filters['orderby'] : $this->args['order_by'];
		
		$order = ( $filters['order'] )? $filters['order'] : ( $this->args['order']? $this->args['order'] : 'desc' );

		if( is_numeric( $a[$orderby] ) && is_numeric( $b[$orderby] ) )
		{
			if( $a[$orderby] == $b[$orderby] ) $result = 0;
			$result = ( $a[$orderby] < $b[$orderby] )? -1 : 1;
		}
		elseif( strtotime( $a[$orderby] ) && strtotime( $b[$orderby] ) )
		{
			$t1 = strtotime($a[$orderby]);
   			$t2 = strtotime($b[$orderby]);

			$result = $t1 - $t2;
		}
		else
		{
			$result = strcmp( $a[$orderby], $b[$orderby] );
		}
		
		return ( $order === 'asc' ) ? $result : -$result;
	}

	public function get_data_ordering()
	{
		$order = [];
		$filters = $this->filters;

		if( isset( $filters['orderby'] ) && !empty( $filters['orderby'] ) )
		{
			list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

			$ord = '';
			foreach( $sortable as $key => $val )
			{
				if( $val[0] == $filters['orderby'] )
				{
					$ord = ( $val[1] )? 'DESC' : 'ASC';
					$order[ $filters['orderby'] ] = ( isset( $filters['order'] ) && !empty( $filters['order'] ) )? $filters['order'] : $ord;
					$this->hasDBOrd = true;
				}
			}
		}
		
		return $order;
	}

	public function get_data_limit()
	{
		$limit = [];
		$filters = $this->filters;
		$per_page = $this->args['per_page_row'];

		if( $this->viewStats && ! $this->isSearch )
		{
			$keys = array_keys( $this->viewStats );
			$stat = isset( $filters['status'] )? $filters['status'] : $keys[0];

			if( $stat != '' )
			{
				$total_items = $this->viewStats[ $stat ];
				$total_pages = ceil( $total_items / $per_page );

				$page = ( isset( $filters['paged'] ) && !empty( $filters['paged'] ) )? $filters['paged'] : 1;
				$page = ( $page > $total_pages )? max( 1, $total_pages ) : $page;

				$limit[] = ( $page - 1 ) * $per_page;
				$limit[] = $per_page;
			}
		}

		return $limit;
	}
	
	public function prepare_items()
	{
		$per_page = $this->args['per_page_row'];
		
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_column();
		$sortable = $this->get_sortable_columns();
		if( method_exists( $this, 'get_manual_sortable_columns' ) )
		{
			$sortable = array_merge( $sortable, $this->get_manual_sortable_columns() );
		}
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$filters = $this->filters;
		
		self::$data = $this->get_data_alters( $this->get_listing_data() );
		$total_items = count( self::$data );

		if( $this->args['pagination'] )
		{
			$current_page = $this->get_pagenum();

			if( $this->viewStats && ! $this->isSearch )
			{
				$keys = array_keys( $this->viewStats );
				$stat = isset( $filters['status'] )? $filters['status'] : $keys[0];

				if( $stat != '' )
				{
					$total_items = $this->viewStats[ $stat ];
				}
				
				if( self::$data && ( !empty( $this->args['order_by'] ) || !empty( $this->filters['orderby'] ) ) && ! $this->hasDBOrd )
				{
					usort( self::$data, array( &$this, 'usort_reorder' ) );
				}

				$this->items = self::$data;
			}
			else
			{	
				if( self::$data && ( !empty( $this->args['order_by'] ) || !empty( $this->filters['orderby'] ) ) && ! $this->hasDBOrd )
				{
					usort( self::$data, array( &$this, 'usort_reorder' ) );
				}
				
				if( self::$data )
					$this->found_data = array_slice( self::$data,( ( $current_page-1 )* $per_page ), $per_page );

				$this->items = $this->found_data;
			}
		}
		else
		{
			if( self::$data && ( !empty( $this->args['order_by'] ) || !empty( $this->filters['orderby'] ) ) && ! $this->hasDBOrd )
			{
				usort( self::$data, array( &$this, 'usort_reorder' ) );
			}
			
			$this->items = self::$data;
		}
		
		$this->set_pagination_args( 
			array(
				'total_items'   	=> $total_items,
				'per_page' 			=> $per_page,
				'infinite_scroll'	=> false,
			) 
		);
	}

	/**
	 *	---------------------------------------------------------------------------
	 *	Customize original lib for own use
	 *	---------------------------------------------------------------------------
	 */
	public function search_box( $text, $input_id ) 
	{
		$input_id = $input_id . '-search-input';
		
		$order_by = !empty( $_REQUEST['orderby'] )? $_REQUEST['orderby'] : $this->filters['orderby'];
		$order = !empty( $_REQUEST['order'] )? $_REQUEST['order'] : $this->filters['order'];
		$s = !empty( _admin_search_query() )? _admin_search_query() : $this->filters['s'];
		
		echo '<input id="orderby-input" type="hidden" name="filter[orderby]" value="' . esc_attr( $order_by ) . '" />';
		
		echo '<input id="order-input" type="hidden" name="filter[order]" value="' . esc_attr( $order ) . '" />';
		?>	
		<div class="filtering-section">
			<div class="advanceSearch">
				<div class="collapse <?php echo ( $this->advSearch['isOn'] || $this->isSearch )? 'show' : '' ?>" id="filterMore">
					<h5 class="">Advance Search</h5>
					<?php if( method_exists( $this, 'filter_search' ) ) $this->filter_search(); ?>
					<div class="row">
						<div class="search-section segment col-md-4">
							<?php if( ! $this->advSearch['hide_search'] ): ?>
							<input type="search" id="<?php echo esc_attr( $input_id ); ?>" class="form-control form-control-sm onfocusFocus" name="filter[s]" value="<?php echo $s; ?>" placeholder="Search" />
							<sup title="Search multiple by ',' or space' ' separated" data-toggle="tooltip" data-placement="right">&nbsp;?&nbsp;</sup>
							<span>&nbsp;&nbsp;</span>
							<?php endif; ?>
							<?php wcwh_submit_button( $text, 'primary btn-sm', '', false, array( 'id' => 'search-submit' ), true ); ?>
						</div>
					</div>
					<hr class="separator">
				</div>
			</div>
			
			<div class="row">
				<div class="search-section col-md-4">
					<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo $text; ?>:</label>
					<input type="search" id="quick-search" class="searchFiltering form-control form-control-sm onfocusFocus" 
					name="filter[qs]" value="" placeholder="Find in list below" />

					<span>&nbsp;&nbsp;</span>
					<a class="adv-search btn btn-primary btn-sm <?php echo ( $this->advSearch['isOn'] || $this->isSearch )? '' : 'collapsed' ?>" title="Advance Search" data-toggle="collapse" href="#filterMore" aria-expanded="false" aria-controls="filterMore"><span>More </span>
		  			</a>
		  		</div>
				<div class="test"></div>
			</div>
			<div class="row">
			<?php if( method_exists( $this, 'search_box_after' ) ) $this->search_box_after(); ?>
			</div>
		</div>
		<?php
	}

	public function get_table_classes()
	{
		return array_merge( 
			array( 'widefat', 'fixed', 'striped', $this->_args['plural'], 'filterable' ), 
			( is_array( $this->args['class'] ) && $this->args['class'] )? $this->args['class'] : array() 
		);
	}

	public function display() 
	{
		$singular = $this->_args['singular'];
		
		$this->heading_styles();

		$this->display_tablenav( 'top' );

		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
			<table id="<?php echo ( $this->args['id'] )? $this->args['id'] : ''; ?>" 
			class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?> <?php echo $this->generated? '' : 'wh-table'; ?>">
				
				<thead>
					<?php if( method_exists( $this, 'print_column_main' ) ): ?>
					<tr class="thead">
					<?php $this->print_column_main(); ?>
					</tr>
					<?php endif; ?>
					<tr>
						<?php $this->print_column_headers(); ?>
					</tr>
				</thead>

				<tbody id="the-list"
					<?php
						if ( $singular ) 
						{
							echo " data-wp-lists='list:$singular'";
						}
					?>
					>
					<?php $this->display_rows_or_placeholder(); ?>
				</tbody>

				<tfoot>
				<?php if( method_exists( $this, 'print_column_footers' ) ): ?>
					<tr>
					<?php $this->print_column_footers( $this->items, self::$data ); ?>
					</tr>
				<?php endif; ?>
				<?php if( method_exists( $this, 'print_final_footers' ) ): ?>
					<tr>
					<?php $this->print_final_footers( $this->items, self::$data ); ?>
					</tr>
				<?php endif; ?>
				<?php if( ! $this->args['off_footer'] ): ?>
					<tr>
						<?php $this->print_column_headers( false ); ?>
					</tr>
					<?php if( method_exists( $this, 'print_column_main' ) ): ?>
					<tr class="thead">
					<?php $this->print_column_main(); ?>
					</tr>
					<?php endif; ?>
				<?php endif; ?>
				</tfoot>

			</table>
		<?php
		$this->display_tablenav( 'bottom' );
	}
	
	public function get_styles()
	{
		return $this->styles;
	}
	
	public function heading_styles()
	{
		?>
			<style>
			<?php 
				if( $this->get_styles() )
				{
					foreach( $this->styles as $header => $style )
					{
						echo $header."{";
						
						foreach( $style as $key => $val )
						{
							echo $key.": ".$val.";";
						}
						
						echo "}";
					}
				}
			?>
			</style>
		<?php
	}

	protected function display_tablenav( $which ) 
	{
		if( $this->args['list_only'] ) return;
		
		if ( 'top' === $which ) 
		{
			wp_nonce_field( 'bulk-' . $this->_args['plural'] );
		}
		?>
			<div class="tablenav <?php echo esc_attr( $which ); ?>">

				<div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
				</div>
				<?php
					$this->nav_views( $which );
					//$this->extra_tablenav( $which );
					$this->pagination( $which );
				?>

				<div class="alignright actions">
					<span class="scan-description"></span>
				</div>

				<br class="clear" />
			</div>
		<?php
	}

	protected function bulk_actions( $which = '' ) 
	{
		if ( is_null( $this->_actions ) ) 
		{
			$this->_actions = $this->get_bulk_actions();

			if( $this->view_outlet ) $this->_actions = array();

			$this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$two            = '';
		} else 
		{
			$two = '2';
		}
		
		if( $this->args['list_only'] || empty( $this->_actions ) ) return;

		echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action' ) . '</label>';
		echo '<select class="browser-default custom-select custom-select-sm" name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";
		echo '<option value="-1">' . __( 'Bulk Actions' ) . "</option>\n";

		foreach ( $this->_actions as $name => $title ) 
		{
			$class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

			echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
		}

		echo "</select>\n";
		
		$attrs = wp_parse_args( 
			$this->bulks, 
			array(
				'id' => "doaction$two",
				'data-title' => "Bulk Action",
				'data-modal' => "wcwhModalConfirm",
				'data-actions' => "no|yes",
				'data-message' => "Confirm to Proceed?",
			)
		);
		wcwh_submit_button( 'Apply', 'primary btn-sm bulk-action linkAction', '', false, $attrs, true );
		
		echo "\n";
	}

	public function nav_views( $which ) 
	{
		if ( 'bottom' === $which ) return;
		if( $this->args['list_only'] ) return;
		
		$views = $this->get_views();
		$views = apply_filters( "views_{$this->screen->id}", $views );

		if ( empty( $views ) ) 
		{
			return;
		}

		$this->screen->render_screen_reader_content( 'heading_views' );
		
		$field = ( $this->statField )? $this->statField : 'status';
		
		echo '<div class="alignleft actions bulkactions">';
		echo "<ul class='subsubsub statuses' data-key='{$field}'>\n";
		foreach ( $views as $class => $view ) 
		{
			$views[ $class ] = "\t<li class='$class'>$view";
		}
		echo implode( " &nbsp; </li>\n", $views ) . "</li>\n";
		echo '</ul>';
		echo '</div>';
	}

	protected function row_actions( $actions, $always_visible = true ) 
	{
		if( ! $actions ) return '';

		$action_count = count( $actions );
		$i            = 0;

		if ( ! $action_count ) 
		{
			return '';
		}

		$out = '<div class="' . ( $always_visible ? 'row-actions visible' : 'row-actions' ) . '">';
		foreach ( $actions as $action => $link ) 
		{
			++$i;
			( $i == $action_count ) ? $sep = '' : $sep = ' ';
			$out                          .= "<span class='$action'>$link$sep</span>";
		}
		$out .= '</div>';

		//$out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';

		return $out;
	}

	public function display_rows() 
	{
		$h_match = $this->get_header_match();
		$this->prev_match = '';

		$c = 0;
		foreach ( $this->items as $item ) 
		{
			$c++;
			$item['no'] = $c;
			$this->single_row( $item );

			if( $h_match && $item[ $h_match ] )
			{
				if( $item[ $h_match ] != $this->prev_match ) $this->prev_match = $item[ $h_match ];
			}
		}
	}

	public function display_child_rows( $items ) 
	{
		if( ! $items ) return;

		foreach ( $items as $item ) 
		{
			$this->single_row( $item );
		}
	}

	public function single_row( $item ) 
	{	
		$hasParent = ( isset( $item['parent'] ) && $item['parent'] )? true : false;
		$hasChild = ( isset( $item['childs'] ) && $item['childs'] )? true : false;

		$class = array();
		if( $this->args['mobile_expand'] ) $class[] = 'is-expanded';
		if( $hasParent ) $class[] = 'hasParent';
		if( $hasChild ) $class[] = 'hasChild';
		if( method_exists( $this, 'row_class' ) )
		{			
			$class = $this->row_class( $class, $item );
		}

		$attr = array();
		if( $hasParent ) $attr[]= 'id="parent_'.$item['parent'].'"';
		if( method_exists( $this, 'row_attr' ) )
		{			
			$attr = $this->row_attr( $attr, $item );
		}

		if( isset( $item['status'] ) && $item['status'] ) $class[] = 'status_'.$item['status'];
		
		echo '<tr '.$attr.' data-id="'.( isset( $item['id'] )? $item['id'] : 0 ).'" class="'.implode( ' ', $class ).'">';
		$this->single_row_columns( $item );
		echo '</tr>';
		
		if( $hasChild )
		{
			$this->display_child_rows( $item['childs'] );
		}

		if( method_exists( $this, 'extra_row' ) )
		{
			echo '<tr class="extra-items"><td class="colspanchange" colspan="' . $this->get_column_count() . '">';
			$this->extra_row();
			echo '</td></tr>';
		}
	}

	protected function single_row_columns( $item ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$h_cols = $this->get_header_cols();
		$h_match = $this->get_header_match();
		$need = false;
		if( $h_match && $item[ $h_match ] ) $need = true;

		foreach ( $columns as $column_name => $column_display_name ) 
		{
			$classes = "$column_name column-$column_name";
			if ( $primary === $column_name ) 
			{
				$classes .= ' has-row-actions column-primary';
			}

			if ( in_array( $column_name, $hidden ) ) 
			{
				$classes .= ' hidden';
			}

			// Comments column uses HTML in the display name with screen reader text.
			// Instead of using esc_attr(), we strip tags to get closer to a user-friendly string.
			$data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';

			$attributes = "class='$classes' $data";

			if ( 'cb' === $column_name ) 
			{
				echo '<th scope="row" class="check-column">';
				echo $this->column_cb( $item );
				echo '</th>';
			} 
			elseif ( 'no' === $column_name ) 
			{
				echo '<td class="num-column">';
				echo $this->column_no( $item );
				echo '</td>';
			} 
			elseif ( method_exists( $this, '_column_' . $column_name ) ) 
			{
				echo call_user_func(
					array( $this, '_column_' . $column_name ),
					$item,
					$classes,
					$data,
					$primary
				);
			} 
			elseif ( method_exists( $this, 'column_' . $column_name ) ) 
			{
				echo "<td $attributes>";
				if( $need && in_array( $column_name, $h_cols ) )
				{
					if( $item[ $h_match ] != $this->prev_match )
					{
						echo call_user_func( array( $this, 'column_' . $column_name ), $item );
						echo $this->handle_row_actions( $item, $column_name, $primary );
					}
				}
				else
				{
					echo call_user_func( array( $this, 'column_' . $column_name ), $item );
					echo $this->handle_row_actions( $item, $column_name, $primary );
				}
				echo '</td>';
			} 
			else 
			{
				echo "<td $attributes>";
				if( $need && in_array( $column_name, $h_cols ) )
				{
					if( $item[ $h_match ] != $this->prev_match )
					{
						echo $this->column_default( $item, $column_name );
						echo $this->handle_row_actions( $item, $column_name, $primary );
					}
				}
				else
				{
					echo $this->column_default( $item, $column_name );
					echo $this->handle_row_actions( $item, $column_name, $primary );
				}
				echo '</td>';
			}
		}
	}
	
	protected function handle_row_actions( $item, $column_name, $primary ) 
	{
		$html = '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';
		$html = '';
		return $column_name === $primary ? $html : '';
	}

	public function print_column_headers( $with_id = true ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url = remove_query_arg( 'paged', $current_url );

		if ( isset( $_GET['orderby'] ) ) 
		{
			$current_orderby = $_GET['orderby'];
		} else {
			if( !empty( $this->filters['orderby'] ) )
				$current_orderby = $this->filters['orderby'];
			else
				$current_orderby = '';
		}
		
		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) 
		{
			$current_order = 'desc';
		} else {
			if( !empty( $this->filters['order'] ) )
				$current_order = $this->filters['order'];
			else
				$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) 
		{
			static $cb_counter = 1;
			$columns['cb']     = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
				. '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}

		foreach ( $columns as $column_key => $column_display_name ) 
		{
			$class = array( 'manage-column', "column-$column_key", $column_key );

			if ( in_array( $column_key, $hidden ) ) 
			{
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) 
			{
				$class[] = 'check-column';
			}
			elseif ( 'no' === $column_key ) 
			{
				$class[] = 'num-column';
			} 
			elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) ) 
			{
				$class[] = 'num';
			}

			if ( $column_key === $primary ) 
			{
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) 
			{
				list( $orderby, $desc_first ) = $sortable[ $column_key ];

				$reset = '';

				if ( $current_orderby === $orderby ) 
				{
					$desc = 'Sorted by '.ucfirst( $current_order );
					$order   = 'asc' === $current_order ? 'desc' : 'asc';

					$class[] = 'sorted';
					$class[] = $current_order;

					$desc.= ', Click for '.ucfirst( $order );

					$reset = '&nbsp;<a class="sortable-reset toolTip" title="Reset Sorting"><i class="fa fa-times-circle"></i></a>';
				} 
				else 
				{
					$order   = $desc_first ? 'desc' : 'asc';
					$desc = 'Sortable '.ucfirst( $order );
					
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}

				//$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';

				$column_display_name = '<a class="sortable-col toolTip" data-orderby="'.$orderby.'" data-order="'.$order.'" title="'.$desc.'"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>'.$reset;
			}

			$tag   = ( 'cb' === $column_key ) ? 'th' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) 
			{
				$class = "class='" . join( ' ', $class ) . "'";
			}

			echo "<$tag $scope $id $class>$column_display_name</$tag>";
		}
	}

	protected function pagination( $which ) 
	{
		$output = '<sup class="line-items font-black"></sup>'; 
		
		if ( empty( $this->_pagination_args ) ) return;

		$total_items     = $this->_pagination_args['total_items'];
		$total_pages     = $this->_pagination_args['total_pages'];
		$infinite_scroll = false;
		if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
			$infinite_scroll = $this->_pagination_args['infinite_scroll'];
		}

		if ( 'top' === $which && $total_pages > 1 ) {
			$this->screen->render_screen_reader_content( 'heading_pagination' );
		}

		/*$output = '<span class="displaying-num">' . sprintf(
			/* translators: %s: Number of items. 
			_n( '%s item', '%s items', $total_items ),
			number_format_i18n( $total_items )
		) . '</span>'; 
		*/
		//
		$output.= '<span class="displaying-num">' . sprintf(
			'/<sub>%s</sub>',
			number_format_i18n( $total_items )
		) . '</span>'; 

		$current              = $this->get_pagenum();
		$removable_query_args = wp_removable_query_args();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

		$current_url = remove_query_arg( $removable_query_args, $current_url );

		$page_links = array();

		$total_pages_before = '<span class="paging-input">';
		$total_pages_after  = '</span></span>';

		$disable_first = false;
		$disable_last  = false;
		$disable_prev  = false;
		$disable_next  = false;

		if ( 1 == $current ) {
			$disable_first = true;
			$disable_prev  = true;
		}
		if ( 2 == $current ) {
			$disable_first = true;
		}
		if ( $total_pages == $current ) {
			$disable_last = true;
			$disable_next = true;
		}
		if ( $total_pages - 1 == $current ) {
			$disable_last = true;
		}

		if ( $disable_first ) {
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='first-page button pg-btn' data-page='1'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				__( 'First page' ),
				'&laquo;'
			);
		}

		if ( $disable_prev ) {
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='prev-page button pg-btn' data-page='%d'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				 max( 1, $current - 1 ),
				__( 'Previous page' ),
				'&lsaquo;'
			);
		}

		if ( 'bottom' === $which ) {
			$html_current_page  = $current;
			$total_pages_before = '<span class="screen-reader-text">' . __( 'Current Page' ) . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
		} else {
			$html_current_page = sprintf(
				"%s<input class='current-page integer minmaxnum onfocusFocus' id='current-page-selector' type='number' min='1' max='%d' name='filter[paged]' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
				'<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page' ) . '</label>',
				$total_pages,
				$current,
				strlen( $total_pages )
			);
		}
		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[]     = $total_pages_before . sprintf(
			/* translators: 1: Current page, 2: Total pages. */
			_x( '%1$s of %2$s', 'paging' ),
			$html_current_page,
			$html_total_pages
		) . $total_pages_after;

		if ( $disable_next ) {
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='next-page button pg-btn' data-page='%d'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				min( $total_pages, $current + 1 ),
				__( 'Next page' ),
				'&rsaquo;'
			);
		}

		if ( $disable_last ) {
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='last-page button pg-btn' data-page='%d'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				$total_pages,
				__( 'Last page' ),
				'&raquo;'
			);
		}

		$pagination_links_class = 'pagination-links';
		if ( ! empty( $infinite_scroll ) ) {
			$pagination_links_class .= ' hide-if-js';
		}
		$output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

		if ( $total_pages ) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		} else {
			$page_class = ' no-pages';
		}
		$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

		echo $this->_pagination;
	}
	
} //class

}