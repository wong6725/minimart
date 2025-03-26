<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PosTransact_Class" ) ) include_once( WCWH_DIR . "/includes/classes/pos-transact.php" ); 

if ( !class_exists( "WCWH_PosTransact_Controller" ) ) 
{

class WCWH_PosTransact_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_pos_transact";

	public $Notices;
	public $className = "PosTransact_Controller";

	public $Logic;
	public $Comp;

	public $tplName = array(
		'new' => 'newPT',
		'row' => 'rowPT',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1 ];

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->arrangement_init();
		
		$this->set_logic();
	}

	public function arrangement_init()
	{
		$Inst = new WCWH_TODO_Class();

		$arr = $Inst->get_arrangement( [ 'section'=>$this->section_id, 'action_type'=>'approval', 'status'=>1 ] );
		if( $arr )
		{
			$this->useFlag = true;
		}
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_PosTransact_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->processing_stat = $this->processing_stat;
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function set_warehouse( $warehouse = array() )
	{
		$this->warehouse = $warehouse;

		if( ! isset( $this->warehouse['permissions'] ) )
		{
			$metas = get_warehouse_meta( $this->warehouse['id'] );
			$this->warehouse = $this->combine_meta_data( $this->warehouse, $metas );
		}

		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] )
			$this->view_outlet = true;

		$this->Logic->setWarehouse( $this->warehouse );
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_unneededFields()
	{
		return array( 
			'action', 
			'token', 
			'wh', 
			'filter',
			'_wpnonce',
			'action2',
			'_wp_http_referer',
		);
	}

	public function validate( $action , $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		if( ! $action || $action < 0 )
		{
			$succ = false;
			$this->Notices->set_notice( 'invalid-action', 'warning' );
		}

		if( ! $datas )
		{
			$succ = false;
			$this->Notices->set_notice( 'insufficient-data', 'warning' );
		}

		if( $succ )
		{
			$action = strtolower( $action );
			switch( $action )
			{
				case 'update':
				case 'save':
					if( ! $datas['detail'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'delete':
				case 'post':
				case 'unpost':
				case 'approve':
				case 'reject':
				case "complete":
				case "incomplete":
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
			}
		}

		return $succ;
	}

	public function action_handler( $action, $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		$count_succ = 0;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$header = $datas['header'];
					$detail = $datas['detail'];

					$filters = [
						'warehouse_id' => $header['warehouse_id'],
						'register' => $header['register'],
						'session' => $header['session'],
						'receipt_id' => $header['receipt_id'],
						'doc_type' => 'pos_transactions',
					];
					$exist = apply_filters( 'wcwh_get_doc_header', $filters, [], true, [ 'usage'=>1, 'meta'=>[ 'register', 'session', 'receipt_id' ] ] );
					if( $exist ) return false;

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					if( $succ )
					{
						$result = $this->Logic->child_action_handle( $action, $header, $detail );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $succ )
						{
							$outcome['id'][] = $result['id'];
							//$outcome['data'][] = $result['data'];
							$count_succ++;

							if( $action == 'save' )
							{
								//Doc Stage
						        $stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
						            'ref_type'		=> $this->section_id,
						            'ref_id'		=> $result['id'],
						            'action'        => $action,
						            'status'    	=> 1,
						        ] );
						    }
						}
					}
				break;
				case "delete":
				case "post":
				case "unpost":
				case "complete":
				case "incomplete":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
							$result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							if( $succ )
							{
								$outcome['id'][] = $result['id'];
								$count_succ++;

								//Doc Stage
								$dat = $result['data'];
								$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
								    'ref_type'	=> $this->section_id,
								    'ref_id'	=> $result['id'],
								    'action'	=> $action,
								    'status'    => $dat['status'],
								    'remark'	=> ( $datas['remark'] )? $datas['remark'] : '',
								] );
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "approve":
				case "reject":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$succ = apply_filters( 'wcwh_todo_external_action', $id, $this->section_id, $action, ( $datas['remark'] )? $datas['remark'] : '' );
							if( $succ )
							{
								$status = apply_filters( 'wcwh_get_status', $action );

								$header = [];
								$header['doc_id'] = $id;
								$header['flag'] = 0;
								$header['flag'] = ( $status > 0 )? 1 : ( ( $status < 0 )? -1 : $header['flag'] );
								$result = $this->Logic->child_action_handle( $action, $header );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}

								if( $succ )
								{
									$outcome['id'][] = $result['id'];
									$count_succ++;

									//Doc Stage
									$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
									    'ref_type'	=> $this->section_id,
									    'ref_id'	=> $result['id'],
									    'action'	=> $action,
									    'status'    => $status,
									    'remark'	=> ( $datas['remark'] )? $datas['remark'] : '',
									] );
								}
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "print":
					$this->print_form( $datas['id'] );

					exit;
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	if( is_array( $datas["id"] ) && $count_succ > 0 ) $succ = true;

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		$succ = $this->after_action( $succ, $outcome['id'], $action );
           	}
        }
        catch (\Exception $e) 
        {
            $succ = false;
        }

        $outcome['succ'] = $succ;
		
		return $outcome;
	}

	public function after_action( $succ, $id, $action = "save" )
	{
		if( ! $id ) return $succ;

		if( $succ )
		{
			$id = is_array( $id )? $id : [ $id ];

			$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false );
			$handled = [];
			foreach( $exists as $exist )
			{
				$handled[ $exist['doc_id'] ] = $exist;
			}

			foreach( $id as $ref_id )
			{
				if( $handled[ $ref_id ]['flag'] == 0 )
				{
					$succ = apply_filters( 'wcwh_todo_arrangement', $ref_id, $this->section_id, $action );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
					}
				}
			}
		}

		return $succ;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
	}

	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) ):
			?>
				<button id="pos_transact_action" class="display-none btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> Pos Transact"
					data-title="<?php echo $actions['save'] ?> Pos Transact" 
					data-action="pos_transact" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#pos_transact_reference" 
				>
					GI for Delivery
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id, 'doc_type'=>'none' ], [], true, [] );
			if( $datas )
			{	
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1 ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$args['data'] = $datas;
				unset( $args['new'] );

				$Inst = new WCWH_Listing();
		        	
		        if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];
		        		
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		if( $item['ref_doc_id'] )
		        		{
		        			$datas['details'][$i]['order'] = get_post_meta( $item['ref_doc_id'], '_order_number', true );
		        		}
		        	}
		        }
		        
		        $args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'prdt_name' => 'Item',
		        		'uom_code' => 'UOM',
		        		'order' => 'Order',
		        		'bqty' => 'Qty',
		        		'bunit' => 'Metric (kg/l)',
		        		'uprice' => 'Price',
		        		'total_amount' => 'Amt',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/posTransact-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/posTransact-form.php', $args );
		}
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/posTransactListing.php" ); 
			$Inst = new WCWH_PosTransact_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$count = $this->Logic->count_statuses( $this->warehouse['code'], $this->ref_doc_type );
			if( $count ) $Inst->viewStats = $count;

			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : ( $count[ 'process' ]? 'process' : 'all' );
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			if( empty( $filters['warehouse_id'] ) )
			{
				$filters['warehouse_id'] = $this->warehouse['code'];
			}

			$metas = [ 'remark', 'register', 'session' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}