<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_BankInService_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_bankin_service";

	public $useFlag;

	protected $users;

	protected $outlet_permission = ['view', 'update_api'];

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
			"docno" 		=> "Doc No.",
			"bis" 			=> "Remittance Form",
			"doc_date"		=> "Doc Date",
			"sender_name"	=> "Sender",
			"amount"		=> "Amount (RM)",
			"service_charge"=> "Service Charge (RM)",
			"currency"		=> "To Currency",
			"exchange_rate" => "Exchange Rate",
			"convert_amount"=> "Exchange Amount",
			"status"		=> "Status",
			"approval"		=> "Approval Status",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
			"remark"		=> "Remark",
		);
	}

	public function get_hidden_column()
	{
		$col = [];
		if( ! $this->useFlag ) $col[] = 'approval';

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'docno' => [ "docno", true ],
			'doc_date' => [ "doc_date", true ],
			'sender_name' => [ "sender_name", true ],
			'currency' => [ "currency", true ],
			'reference' => [ "prt_docno", true ],
			'created' => [ "created_at", true ],
			'lupdate' => [ "lupdate_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'post_'.$this->section_id ) )
			$actions['post'] = $this->refs['actions']['post'];
		if( current_user_can( 'delete_'.$this->section_id ) )
			$actions['delete'] = $this->refs['actions']['delete'];
		
		$actions['print_a4'] = $this->refs['actions']['print']." (A4)";
		$actions['print_receipt'] = $this->refs['actions']['print']." (Receipt)";
		
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
		if($this->view_outlet && $this->outlet_permission) $temp = 'update_api';
		else $temp = 'edit';
	
		return array(
			'0' => array(
				'view' => [ 'wcwh_user' ],
				//$temp => [ 'wh_admin_support' ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				$temp => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
				'post' => [ 'post_'.$this->section_id ],
				//'approve' => [ 'approve_'.$this->section_id ],
				//'reject' => [ 'reject_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				$temp => [ 'wh_admin_support' ],
				'unpost' => [ 'unpost_'.$this->section_id ],
				'complete' => [ 'complete_'.$this->section_id ],
			),
			'9' => array(
				'view' => [ 'wcwh_user' ],
				//$temp => [ 'wh_admin_support' ],
				//'close' => [ 'close_'.$this->section_id ],
			),
		);
	}
	
	public function action_btn_addon( $btn, $item, $action = "view", $args = array() )
	{
		$icons = $this->get_icons();
		$actions = $this->refs['actions'];
		$services = ( $args['services'] )? : $this->section_id.'_action';
		$title = ( $args['title'] )? $args['title'] : $item['docno'];
		$id = ( $args['id'] )? $args['id'] : ( ( $item['doc_id'] )? $item['doc_id'] : $item['id'] );
		
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
		
		switch( $action )
		{
			case 'bank_in':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printBIS" 
				 	data-title="Print Remittance Form" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> Form</a>';
			break;
		}

		return $btn;
	}

	/**
	 * 	Overriding Action Btn
	 * 	--------------------------------------------------------------------------------------------- 
	 */

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
		//----------------test jeff------------------------//
		else if( $status_actions && $status_actions[ (string) $status ] && $this->view_outlet && $this->outlet_permission )
		{
			foreach( $status_actions[ (string) $status ] as $action => $permission )
			{	
				if( empty( $permission ) || ( $permission && current_user_cans( $permission ) ) )
				{
					if( in_array($action, $this->outlet_permission) )
						$actions[] = $this->get_action_btn( $item, $action, $args );
				}
			}
		}
		//----------------test jeff------------------------//
		else
		{
			$actions[] = $this->get_action_btn( $item, 'view', $args );
		}
		
		return $actions;
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

		//------test jeff-----//
		if( $this->view_outlet && ! $args['force'] && ! $this->outlet_permission) $action = 'view';
		
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
			//-----test jeff--------------------------//
			case 'update_api':
				if( !$this->useFlag || 
					( $this->useFlag && $item['flag'] == 0 ) || 
					$this->forfeitFlagCheck || 
					current_user_cans( [ 'wh_support', 'wh_admin_support' ] ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-edit" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'" 
					data-modal="wcwhModalForm" data-actions="close|submit" data-title="'.$actions[ $action ].' '.$title.'" 
					'.$html_attr.' 
					><i class="fa '.$icons[ 'edit' ].'"></i></a>';
			break;
			//-----test jeff--------------------------//
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
	
	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Sender</label>
				<?php
					$meta = [ 'amount', 'service_charge', 'currency', 'convert_amount', 'exchange_rate', 'sender_name', 'customer_id' ];
					$filters = ['seller'=>$this->warehouse['id']];
					$options = options_data( apply_filters( 'wcwh_get_bankin_service', $filters, $order , false, [ 'parent'=>1, 'meta'=>$meta ] ), 'sender_name', [ 'sender_name' ], '');	
		
					wcwh_form_field( 'filter[sender_name][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['sender_name'] )? $this->filters['sender_name'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Currency</label>
				<?php
					//$currency = get_woocommerce_currencies();

					$options = options_data( apply_filters( 'wcwh_get_bankin_service', $filters, $order , false, [ 'parent'=>1, 'meta'=>$meta ] ), 'currency', [ 'currency' ], '');	

					wcwh_form_field( 'filter[currency][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['currency'] )? $this->filters['currency'] : '', $view 
	                ); 
				?>
			</div>
		</div>
		<div class="row">
			<?php
				wcwh_form_field( 'section', 
	                [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[], ], 
	                $this->section_id, $view 
	            ); 
			?>
		</div>
	<?php
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		foreach ($item as $key => $value)
		{
			if($item['status'] == 1 || $item['status'] == 6)
			{
				$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['doc_id'], $item['doc_id'] );
			}
		}
		
		return $html;
    }

	public function column_docno( $item ) 
	{	
		$actions = $this->get_actions( $item, $item['status'], [ 'title'=>$item['docno'] ] );
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_bis( $item )
	{
		$actions = [];
		$actions['bis'] = $this->get_action_btn( $item, 'bank_in', ['force'=>true]); 
	
		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_post_date( $item )
	{
		return !empty( $item['posting_date'] )? $item['posting_date'] : ( !empty( (int)$item['post_date'] )? $item['post_date'] : '' );
	}

	public function column_sender_name( $item )
	{		
		return $item['sender_name'];
	}

	public function column_amount( $item )
	{
		return "<strong class='font14'>".round_to( $item['amount'], 2, 1, 1 )."</strong>";
	}

	public function column_currency( $item )
	{
		$currency = get_woocommerce_currencies();
		foreach ($currency as $key => $value)
		{
			if($key == $item['currency'])
			{
				$html = '<strong>'.$key."</strong><br>(".$value.")";
			}
		}
		return $html;
	}

	public function column_service_charge( $item )
	{
		return "<strong class='font14'>".round_to( $item['service_charge'], 2, 1, 1 )."</strong>";
	}

	public function column_exchange_rate( $item )
	{
		return "<strong class='font14'>".round_to( $item['exchange_rate'], 2, 1, 1 )."</strong>";
	}

	public function column_convert_amount( $item )
	{
		return "<strong class='font14'>".round_to( $item['convert_amount'], 2, 1, 1 )."</strong>";
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_approval( $item )
	{
		$approval = $this->get_approvals();
		$html = "<span class='list-stat list-{$approval[ $item['flag'] ]['key']}'>{$approval[ $item['flag'] ]['title']}</span>";

		return $html;
	}

	public function column_created( $item )
	{
		$user = ( $this->users )? $this->users[ $item['created_by'] ] : $item['created_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;
		$date = $item['created_at'];

		$html = $user.'<br>'.$date;
		
		$args = [ 
			'action' => 'view_doc', 
			'id' => $item['doc_id'], 
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
			'id' => $item['doc_id'], 
			'service' => 'wh_logs_action', 
			'title' => $html, 
			'desc' => 'View History',
			'permission' => [ 'wcwh_user' ] 
		];
		return $this->get_external_btn( $html, $args );
	}
	
} //class