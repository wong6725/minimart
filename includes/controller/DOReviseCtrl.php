<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_DORevise_Class" ) ) include_once( WCWH_DIR . "/includes/classes/do-revise.php" ); 

if ( !class_exists( "WCWH_DORevise_Controller" ) ) 
{

class WCWH_DORevise_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_do_revise";

	public $Notices;
	public $className = "DORevise_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newDO',
		'row' => 'rowDO',
		'import' => 'importDO',
		'export' => 'exportDO',
	);

	public $useFlag = false;
	public $outlet_post = true;

	public $ref_doc_type = '';

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
		$this->Logic = new WCWH_DORevise_Class( $this->db_wpdb );
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
					else
					{
						foreach( $datas['detail'] as $i => $row )
						{
							if( isset( $row['ref_item_id'] ) && $row['ref_item_id'] > 0 && isset( $row['ref_bal'] ) && $row['bqty'] > $row['ref_bal'] )
							{
								$succ = false;
								$this->Notices->set_notice( 'Item Qty could not exceed reference.', 'warning' );
							}
						}
					}
				break;
				case 'delete':
				case 'post':
				case 'unpost':
				case 'approve':
				case 'reject':
				case 'print':
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

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					$refDetail = [];
					if( $header['ref_doc_id'] )
					{
						$ref_header = $this->Logic->get_header( [ 'doc_id'=>$header['ref_doc_id'], 'doc_type'=>'none' ], [], true, [ 'company'=>1 ] );
						if( $ref_header )
						{
							$header['ref_warehouse'] = $ref_header['warehouse_id'];
							$header['ref_doc_type'] = $ref_header['doc_type'];
							$header['ref_doc'] = $ref_header['docno'];
							$header['parent'] = $header['ref_doc_id'];

							if( ! $header['doc_id'] )
							{
								$header['ref_status'] = $ref_header['status'];
							}

							$metas = get_document_meta( $header['ref_doc_id'] );
							$ref_header = $this->combine_meta_data( $ref_header, $metas );

							if( ! empty( $ref_header['base_doc_type'] ) ) $header['base_doc_type'] = $ref_header['base_doc_type'];

							if( $header['client_company_code'] )
							{
								$seller = apply_filters( 'wcwh_get_warehouse', [ 'client_company_code'=>$header['client_company_code'] ], [], true, 
									[ 'meta'=>[ 'client_company_code' ], 'meta_like'=>[ 'client_company_code'=>1 ] 
								] );
								if( $seller ) $header['supply_to_seller'] = $seller['code'];
							}

							$ref_detail = $this->Logic->get_detail( [ 'doc_id'=>$ref_header['doc_id'] ], [], false, [ 'transact'=>1, 'usage'=>1 ] );
							if( $ref_detail )
							{
								foreach( $ref_detail as $i => $row )
								{
									$metas = get_document_meta( $header['ref_doc_id'], '', $row['item_id'] );
									$row = $this->combine_meta_data( $row, $metas );

									$refDetail[ $row['item_id'] ] = $row;
								}
							}
						}
					}
					if( $detail )
					{
						foreach( $detail as $i => $row )
						{
							if( ! $row['bqty'] )
							{
								unset( $detail[$i] );
								continue;
							}

							if( $row['total_amount'] > 0 )
							{
								$detail[$i]['uprice'] = round_to( $row['total_amount'] / $row['bqty'], 5 );
							}
							else
							{
								$uprice = ( $row['uprice'] > 0 )? $row['uprice'] : 0;
								$detail[$i]['uprice'] = $uprice;
								$detail[$i]['total_amount'] = round_to( $row['bqty'] * $uprice, 2 );
							}

							if( $refDetail[ $row['ref_item_id'] ] )
							{
								$ref_row = $refDetail[ $row['ref_item_id'] ];

								if( $ref_row['weighted_total'] > 0 )
								{
									$detail[$i]['total_amount'] = round_to( $ref_row['weighted_total'] / $ref_row['bqty'] * $row['bqty'], 2 );
									$detail[$i]['uprice'] = round_to( $ref_row['weighted_total'] / $ref_row['bqty'], 5 );
								}
								else
								{
									$detail[$i]['uprice'] = ( $ref_row['weighted_price'] > 0 )? $ref_row['weighted_price'] : $detail[$i]['uprice'];
									$detail[$i]['total_amount'] = round_to( $row['bqty'] * $detail[$i]['uprice'], 2 );
								}

								//outlet side receive with unit related item
								$sunit = get_document_meta( $row['ref_doc_id'], 'sunit', $row['ref_item_id'], true );
								if( $sunit > 0 )
								{
									$detail[$i]['bunit'] = round_to( $sunit / $ref_row['bqty'] * $row['bqty'], 3 );
								}
							}

							$item = apply_filters( 'wcwh_get_item', [ 'id'=>$row['product_id'] ], [], true, [] );
							if( $item )
							{
								$detail[$i]['batch_serial'] = $item['serial'].get_datime_fragment( $header['doc_date'], 'ymd' );
							}
						}
					}

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
				case "post":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
							
							$proceed = false;
							if( $this->outlet_post )
							{
								//check need handshake
								$sync_seller = get_document_meta( $id, 'supply_to_seller', 0, true );
								if( ! $sync_seller ) $proceed = true;

								//check handshaked
								if( ! $proceed && $sync_seller )
								{
									//remote check unpost availability
									if( ! $proceed && $succ )
									{
										$remote = apply_filters( 'wcwh_api_request', 'post_do_revise', $id, $sync_seller, $this->section_id );
										if( ! $remote['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( $remote['notice'], 'error' );
										}
										else
										{
											$remote_result = $remote['result'];
											if( $remote_result['succ'] )
											{
												$proceed = true;
											}
											else
											{
												$succ = false;
												$this->Notices->set_notice( $remote_result['notification'], 'error' );
											}
										}
									}

									if( ! $proceed )
									{
										$succ = false;
										if( ! $this->Notices->has_notice() )
										$this->Notices->set_notice( 'Client side operation failed.', 'error' );
									}
								}
							}
							else
							{
								$proceed = true;
							}
							
							if( $proceed && $succ )
							{
								$result = $this->Logic->child_action_handle( $action, $header );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}
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
				case "unpost":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
							
							$proceed = false;
							if( $this->outlet_post )
							{
								//check need handshake
								$sync_seller = get_document_meta( $id, 'supply_to_seller', 0, true );
								if( ! $sync_seller ) $proceed = true;

								//check handshaked
								if( ! $proceed && $sync_seller )
								{
									//remote check unpost availability
									if( ! $proceed && $succ )
									{
										$remote = apply_filters( 'wcwh_api_request', 'unpost_do_revise', $id, $sync_seller, $this->section_id );
										if( ! $remote['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( $remote['notice'], 'error' );
										}
										else
										{
											$remote_result = $remote['result'];
											if( $remote_result['succ'] )
											{
												$proceed = true;
											}
											else
											{
												$succ = false;
												$this->Notices->set_notice( $remote_result['notification'], 'error' );
											}
										}
									}

									if( ! $proceed )
									{
										$succ = false;
										if( ! $this->Notices->has_notice() )
										$this->Notices->set_notice( 'Client side operation failed.', 'error' );
									}
								}
							}
							else
							{
								$proceed = true;
							}
							
							if( $proceed && $succ )
							{
								$result = $this->Logic->child_action_handle( $action, $header );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}
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
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

					exit;
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	//if( is_array( $datas["id"] ) && $count_succ > 0 ) $succ = true;

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

			$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false, [ 'meta'=>[ 'supply_to_seller' ] ] );
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

				$wh = $handled[ $ref_id ]['supply_to_seller'];
				if( $handled[ $ref_id ]['status'] >= 6 && $wh )
				{
					$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $wh );
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
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'], $this->ref_doc_type );
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'client_name', 'remark' ], "DO Revise by Client's DO" );
	        echo '<div id="do_revise_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'do_revise_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#do_revise_action"'], 'class'=>['select2','triggerChange'], 
	                'options'=> $options, 'offClass'=>true
	            ], 
	            ''
	        );
	        echo '</div>';
		}
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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="do_revise_action" class="btn btn-sm btn-primary linkAction display-none" title="Add <?php echo $actions['save'] ?> DO Revise"
					data-title="<?php echo $actions['save'] ?> DO Revise" 
					data-action="do_revise_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#do_revise_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> DO Revise
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_reference_doc( $doc_id = 0, $title = '', $doc_type = 'sale_order' )
	{
		if( ! $doc_id || ! $doc_type ) return;

		switch( $doc_type )
		{
			case 'delivery_order':
				if( ! class_exists( 'WCWH_DeliveryOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
				$Inst = new WCWH_DeliveryOrder_Controller();
			break;
		}
		
		$ref_segment = [];
		
		if( $Inst )
		{
			$Inst->set_warehouse(  $this->warehouse );
			
			ob_start();
			$Inst->view_form( $doc_id, false, true, true, [ 'ref'=>1, 'link'=>0 ] );
			$ref_segment[ $title ] = ob_get_clean();
			
			$args = [];
			$args[ 'accordions' ] = $ref_segment;
			$args[ 'id' ] = $this->section_id;

			do_action( 'wcwh_get_template', 'segment/accordion.php', $args );
		}
	}

	public function gen_form( $id = 0 )
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
			'ref_doc_type' => $this->ref_doc_type,
			'wh_id'		=> $this->warehouse['id'],
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$header = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [] );
			if( $header )
			{
				$metas = get_document_meta( $id );
				$header = $this->combine_meta_data( $header, $metas );

				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];

				$args['data']['client_company_code'] = $header['client_company_code'];
				$args['data']['supply_to_seller'] = $header['supply_to_seller'];

				$args['NoAddItem'] = !empty( $this->setting[ $this->section_id ]['add_item'] )? false : true;
			}

			$ol_wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$header['supply_to_seller'] ], [], true, [ 'meta'=>['dbname'] ] );
			if( $ol_wh['dbname'] ) 
			{
				$filters = [ 'doc_id' => $id ];//'uom'=>1, 'usage'=>1, 'ref'=>1,
				$items = $this->Logic->get_outlet_detail( $filters, [], false, [ 'usage'=>1, 'transact'=>1, 'outlet'=>$header['supply_to_seller'] ] );

				if( $items )
				{
					$details = array();
					foreach( $items as $i => $item )
					{	
						$details[] = array(
							'id' =>  $item['product_id'],
							'product_id' => $item['product_id'],
							'item_id' => '',
							'line_item' => [ 'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
								'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'] ],
							'bqty' => ( $item['o_bqty'] - $item['o_uqty'] ),
							'ref_bqty' => $item['o_bqty'],
							'ref_bal' => ( $item['o_bqty'] - $item['o_uqty'] ),
							'ref_doc_id' => $item['doc_id'],
							'ref_item_id' => $item['item_id'],
							'uprice' => $item['weighted_price'],
							'total_amount' => $item['weighted_total'],
						);
					}
					$args['data']['details'] = $details;
				}
			}
			else
			{
				$items = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1 ] );

				if( $items )
				{
					$details = array();
					foreach( $items as $i => $item )
					{	
						$details[] = array(
							'id' =>  $item['product_id'],
							'product_id' => $item['product_id'],
							'item_id' => '',
							'line_item' => [ 'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
								'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'] ],
							'bqty' => $item['bqty'],
							'ref_bqty' => $item['bqty'],
							'ref_bal' => ( $item['bqty'] - $item['uqty'] ),
							'ref_doc_id' => $item['doc_id'],
							'ref_item_id' => $item['item_id'],
							'uprice' => $item['ucost'],
							'total_amount' => $item['tcost'],
						);
					}
					$args['data']['details'] = $details;
				}
			}
		}

		if( $args['data']['ref_doc_id'] )
			$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/DORevise-form.php', $args );
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false, $config = [ 'ref'=>1, 'link'=>1 ] )
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
			'get_content' => $getContent,
			'ref_doc_type' => $this->ref_doc_type,
			'wh_id'		=> $this->warehouse['id'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$id ], [], true, [ 'meta'=>[ 'ref_doc', 'ref_doc_type' ] ] );
			if( $doc )
			{
				$data = $doc;

				$detail = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$id ], [], false, [ 'item'=>1 ] );
				if( sizeof( $detail ) )
					$data['detail'] = $detail;
			}

			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [] );
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				if( !empty( $datas['ref_doc_id'] ) ) 
				{
					$ref_datas = $this->Logic->get_header( [ 'doc_id'=>$datas['ref_doc_id'], 'doc_type'=>'none' ], [], true, [] );
					if( $ref_datas ) $datas['ref_doc_date'] = $ref_datas['doc_date'];
				}

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1 ] );
				
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				$args['NoAddItem'] = !empty( $this->setting[ $this->section_id ]['add_item'] )? false : true;

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_good_receive'] ) )
				{
					$hides = [ 'uprice', 'total_amount' ];
				}
		        	
		        if( $datas['details'] )
		        {	
		        	$total_amount = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['item_name'] = $item['prdt_name'];
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];

						$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;

						$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2, true );
		        		$datas['details'][$i]['uprice'] = round_to( $datas['details'][$i]['uprice'], 5, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['total_amount'], 2, true );

		        		$total_amount+= $datas['details'][$i]['total_amount'];
		        	}

		        	if( $isView && !$hides )
		        	{
		        		$final = [];
			        	$final['prdt_name'] = '<strong>TOTAL:</strong>';
			        	$final['total_amount'] = '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
			        	$datas['details'][] = $final;
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );
		        
		        $args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'prdt_name' => 'Item',
		        		'uom_code' => 'UOM',
		        		'bqty' => 'Qty',
		        		'bunit' => 'Metric (kg/l)',
		        		'uprice' => 'Price',
		        		'total_amount' => 'Total Amt',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	$hides, 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/DORevise-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/DORevise-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/DORevise-row.php', $this->tplName['row'] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/DOReviseListing.php" ); 
			$Inst = new WCWH_DORevise_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->ref_doc_type = $this->ref_doc_type;
			$Inst->styles = [
				'#action' => [ 'width' => '90px' ],
			];

			$count = $this->Logic->count_statuses( $this->warehouse['code'], $this->ref_doc_type );
			if( $count ) $Inst->viewStats = $count;

			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 'process';
			
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
			if( $this->ref_doc_type )
			{
				$filters['base_doc_type'] = $this->ref_doc_type;
			}

			$metas = [ 'remark', 'ref_doc_id', 'ref_doc_type', 'ref_doc', 'client_company_code', 'supply_to_seller' ];

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