<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_DeliveryOrder_Class" ) ) include_once( WCWH_DIR . "/includes/classes/delivery-order.php" ); 

if ( !class_exists( "WCWH_DeliveryOrder_Controller" ) ) 
{

class WCWH_DeliveryOrder_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_delivery_order";

	public $Notices;
	public $className = "DeliveryOrder_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newDO',
		'row' => 'rowDO',
		'import' => 'importDO',
		'export' => 'exportDO',
		'do' => 'printDO',
	);

	public $useFlag = false;

	public $ref_doc_type = '';

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1 ];

	public $skip_strict_unpost = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->arrangement_init();
		
		$this->set_logic();
	}

	public function __destruct()
	{
		unset($this->Logic);
		unset($this->Notices);
		unset($this->warehouse);
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
		$this->Logic = new WCWH_DeliveryOrder_Class( $this->db_wpdb );
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
						$r = 0;
						foreach( $datas['detail'] as $i => $row )
						{
							if( isset( $row['ref_item_id'] ) && $row['ref_item_id'] > 0 && isset( $row['ref_bal'] ) && $row['bqty'] > $row['ref_bal'] )
							{
								$succ = false;
								$this->Notices->set_notice( 'Item Qty could not exceed reference.', 'warning' );
							}
							$r++;
						}

						if( $this->setting[ $this->section_id ]['auto_sales_limit'] > 0 )
						{
							$row = $this->setting[ $this->section_id ]['auto_sales_limit'];
							if( ! $datas['header']['ref_doc_id'] && $r > $row )
							{
								$succ = false;
								$this->Notices->set_notice( 'Only '.$row.' rows of item are allowed', 'warning' );
							}
						}
					}
				break;
				case 'delete':
				case 'post':
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
				case 'unpost':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( ! isset( $datas['remark'] ) || empty( trim( $datas['remark'] ) ) )
					{
						$succ = false;
						$this->Notices->set_notice( 'Remark is required', 'warning' );
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
					{	//get GI
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
							
							//old SO > GI > DO handling
							if( in_array( $ref_header['doc_type'], [ 'good_issue' ] ) &&
								in_array( $ref_header['ref_doc_type'], ['sale_order', 'transfer_order'] ) 
							){
								$header['sales_doc'] = $ref_header['ref_doc'];
								$header['base_doc_type'] = $ref_header['ref_doc_type'];
								$header['base_doc_id'] = $ref_header['ref_doc_id'];
							}
							//new SO > DO > GI handling
							else if( in_array( $ref_header['doc_type'], ['sale_order', 'sale_debit_note', 'transfer_order'] ) )
							{
								$header['sales_doc'] = $ref_header['docno'];
								$header['base_doc_type'] = $ref_header['doc_type'];
								$header['base_doc_id'] = $ref_header['doc_id'];
							}
							
							$header['direct_issue'] = $ref_header['direct_issue'];

							if( in_array( $ref_header['ref_doc_type'], ['purchase_request'] ) )
							{
								if( $ref_header['purchase_warehouse_id'] )
									$header['purchase_warehouse_id'] = $ref_header['purchase_warehouse_id'];
								if( $ref_header['purchase_doc'] )
									$header['purchase_doc'] = $ref_header['purchase_doc'];
							}

							if( $header['client_company_code'] )
							{
								$seller = apply_filters( 'wcwh_get_warehouse', [ 'client_company_code'=>$header['client_company_code'] ], [], true, 
									[ 'meta'=>[ 'client_company_code' ], 'meta_like'=>[ 'client_company_code'=>1 ] 
								] );
								if( $seller ) $header['supply_to_seller'] = $seller['code'];
							}

							$ref_detail = $this->Logic->get_detail( [ 'doc_id'=>$ref_header['doc_id'] ], [], false, [ 'transact'=>1, 'usage'=>1, 'meta'=>['returnable_item', 'receive_on_deliver'] ] );
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
					else
					{
						if( $this->setting[ $this->section_id ]['use_auto_sales'] )
						{
							$header['automate_sale'] = 1;
							$header['base_doc_type'] = 'sale_order';
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

							if( !$row['receive_on_deliver'] )
							{
								if( !$row['receive_on_deliver'] && $row['returnable_item'] ) //for edit/update condition
								{
									$detail[$i]['receive_on_deliver'] = 0; //set to 0 to make sure the meta will be deleted
								}
								$detail[$i]['returnable_item'] = 0; //set to 0 to make sure the meta will be deleted
							}

							$sprice = 0;
							if( $row['ref_doc_id'] && $row['ref_item_id'] )
							{
								$price = $refDetail[ $row['ref_item_id'] ]['sprice'];
								$sprice = ( $price > 0 )? $price : $sprice;
								$detail[$i]['sprice'] = $sprice;

								if( !empty( $refDetail[ $row['ref_item_id'] ]['bunit'] ) )
									$detail[$i]['bunit'] = $refDetail[ $row['ref_item_id'] ]['bunit'];

								$sunit = $refDetail[ $row['ref_item_id'] ]['sunit'];
								if( $sunit )$detail[$i]['sunit'] = $sunit;
								
								if( $refDetail[ $row['ref_item_id'] ]['weighted_price'] )
								{
									$detail[$i]['ucost'] = $refDetail[ $row['ref_item_id'] ]['weighted_price'];
								}

								if( !empty( $refDetail[ $row['ref_item_id'] ]['returnable_item'] ) )
								{
									$detail[$i]['returnable_item'] = $refDetail[ $row['ref_item_id'] ]['returnable_item'];
								}
								if( !empty( $refDetail[ $row['ref_item_id'] ]['receive_on_deliver'] ) )
								{
									$detail[$i]['receive_on_deliver'] = $refDetail[ $row['ref_item_id'] ]['receive_on_deliver'];
								}
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
				case "post":
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

							if( $succ && in_array( $action, [ 'post', 'unpost' ] ) )
							{
								$succ = $this->direct_issue_handler( $result['id'], $action );
								$succ = $this->automate_sales_handler( $result['id'], $action );
								$succ = $this->direct_receive_handler( $result['id'], $action );

								//$succ = $this->gas_handler( $result['id'], $action );
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
							if( $this->setting[ $this->section_id ]['strict_unpost'] && ! $this->skip_strict_unpost )
							{
								$validDate = $this->Logic->document_account_period_handle( $id, '', '', $action );
								if( ! $validDate )
								{
									if( $this->Notices ) $this->Notices->set_notice( "Not Allowed. Date is out of Accounting Period!", "warning", $this->Logic->getDocumentType()."|document_action_handle" );
									$succ = false;
								}
								if( ! $succ ) break;

								//check need handshake
								$sync_seller = get_document_meta( $id, 'supply_to_seller', 0, true );
								if( ! $sync_seller ) $proceed = true;

								//check handshaked
								if( ! $proceed && $sync_seller )
								{
									$f = [ 'direction'=>'out', 'section'=>$this->section_id, 'ref_id'=>$id, 'status'=>1 ];
									$syncs = apply_filters( 'wcwh_get_sync', $f, [], false, [] );
									if( $syncs && count( $syncs ) > 0 )
									{
										foreach( $syncs as $idx => $sync )
										{
											if( $sync['handshake'] <= 0 )//&& empty( (int)$sync['lsync_at'] )
											{
												$r = apply_filters( 'wcwh_sync_handler', 'delete', [ 'id'=>$sync['id'] ] );
												if( ! $r['succ'] )
												{
													$succ = false;
													$this->Notices->set_notice( 'Remove sync failed.', 'error' );
													break;
												}
												else
												{
													$proceed = true;
												}
											}
										}
									}
									else
									{
										$proceed = true;
									}

									//remote check unpost availability
									if( ! $proceed && $succ )
									{
										$remote = apply_filters( 'wcwh_api_request', 'unpost_delivery_order', $id, $sync_seller, $this->section_id );
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
										$this->Notices->set_notice( 'Document synced, please double check on client side.', 'error' );
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

							if( $succ && in_array( $action, [ 'post', 'unpost' ] ) )
							{
								$succ = $this->direct_issue_handler( $result['id'], $action );
								$succ = $this->automate_sales_handler( $result['id'], $action );
								$succ = $this->direct_receive_handler( $result['id'], $action );

								//$succ = $this->gas_handler( $result['id'], $action );
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
				case "egt_restock":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;

							$succ = $this->direct_receive_handler( $id, $action );

							if( $succ )
							{
								$outcome['id'][] = $result['id'];
								$count_succ++;
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
				case "import":
					$files = $this->files_grouping( $_FILES['import'] );
					if( $files )
					{
						$succ = $this->import_data( $files, $datas );
					}
					else
					{
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "export":
					$datas['filename'] = 'DO';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['client_code'] ) ) $params['client_company_code'] = $datas['client_code'];
					
					//$succ = $this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
						case 'delivery_order':
							$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
							$doc = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [] );
							if( $doc )
							{	
								$doc['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
								//metas
								$metas = get_document_meta( $id );
								$doc = $this->combine_meta_data( $doc, $metas );

								$date_format = get_option( 'date_format' );
								$params['heading']['docno'] = $doc['docno'];
								$params['heading']['remark'] = $doc['remark'];
								$params['heading']['infos'] = [
									'D.O. No.' => $doc['docno'],
									'S.O. No.' => $doc['sales_doc'],
									'PR. No.' => ( $doc['purchase_doc'] )? $doc['purchase_doc'] : '',
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
								];

								$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ 'company'=>1 ] );
								if( $warehouse ) $params['heading']['sign_holder'] = $warehouse['comp_name'];

								if( $doc['base_doc_id'] )
								{
									$base_metas = get_document_meta( $doc['base_doc_id'], '', 0 );
									$doc = $this->combine_meta_data( $doc, $base_metas );
								}
								$addr_format = "{company}\n{address_1}\n{postcode} {city} {state_upper} {country_upper}\n{phone}";

								$billing = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'billing' ] );
								if( $billing )
								{
									$params['heading']['first_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'company'    => $billing['name'],
										'address_1'  => ( $doc['diff_billing_address'] )? $doc['diff_billing_address'] : $billing['address_1'],
										'city'       => ( $doc['diff_billing_city'] )? $doc['diff_billing_city'] : $billing['city'],
										'state'      => ( $doc['diff_billing_state'] )? $doc['diff_billing_state'] : $billing['state'],
										'postcode'   => ( $doc['diff_billing_postcode'] )? $doc['diff_billing_postcode'] : $billing['postcode'],
										'country'    => $billing['country'],
										'phone'		 => ( $doc['diff_billing_contact'] )? $doc['diff_billing_contact'] : $billing['contact_person'].' '.$billing['contact_no'],
									], '', $addr_format );
								}

								$shipping = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'shipping' ] );
								if( $shipping )
								{
									$params['heading']['second_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'company'    => $shipping['name'],
										'address_1'  => ( $doc['diff_shipping_address'] )? $doc['diff_shipping_address'] : $shipping['address_1'],
										'city'       => ( $doc['diff_shipping_city'] )? $doc['diff_shipping_city'] : $shipping['city'],
										'state'      => ( $doc['diff_shipping_state'] )? $doc['diff_shipping_state'] : $shipping['state'],
										'postcode'   => ( $doc['diff_shipping_postcode'] )? $doc['diff_shipping_postcode'] : $shipping['postcode'],
										'country'    => $shipping['country'],
										'phone'		 => ( $doc['diff_shipping_contact'] )? $doc['diff_shipping_contact'] : $shipping['contact_person'].' '.$shipping['contact_no'],
									], '', $addr_format );
								}

								if( $doc['status'] > 0 )
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'usage'=>1, 'ref'=>1 ] );
								else
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'ref'=>1 ] );
							    
							    if( $doc['details'] )
							    {
							    	$detail = [];
									//-------- 7/9/22 jeff DO Print View -----//
							    	$from_date = strtotime('-2 weeks', current_time( 'timestamp' ));
                					$from_date = date('Y-m-d H:i:s', $from_date);
                					//-------- 7/9/22 jeff DO Print View -----//
							        foreach( $doc['details'] as $i => $item )
							        {
							        	$detail_metas = get_document_meta( $id, '', $item['item_id'] );
		        						$item = $this->combine_meta_data( $item, $detail_metas );
										//-------- 7/9/22 jeff DO Print View -----//
										$new_prdt = apply_filters( 'wcwh_get_item', [ 'id'=>$item['product_id'], 'from_date'=>$from_date ], [], true, [] );
										$new_indicator = ($new_prdt)?' (new)':'';
										//-------- 7/9/22 jeff DO Print View -----//
										
							        	$row = [];
							        	$row['item'] = $item['prdt_code'].' - '.$item['prdt_name'].$new_indicator; //-------- 7/9/22 jeff DO Print View -----//
							        	if( $datas['view_type'] == 'category' ) $row['item'] = $item['cat_code'].' - '.$item['cat_name'];
							        	$row['uom'] = $item['uom_code'];
							        	$row['qty'] = round_to( $item['bqty'], 2 );

							        	$detail[] = $row;
							        }

							        $params['detail'] = $detail;
							    }
							}
							
							switch( strtolower( $datas['paper_size'] ) )
							{
								case 'receipt':
									$params['print'] = 1;
									ob_start();
										do_action( 'wcwh_get_template', 'template/receipt-delivery-order.php', $params );
									$content.= ob_get_clean();

									echo $content;
								break;
								case 'default':
								default:
									ob_start();
										do_action( 'wcwh_get_template', 'template/doc-delivery-order.php', $params );
									$content.= ob_get_clean();
									
									if( ! is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) || $datas['html'] > 0 )
									{
										echo $content;
									}
									else
									{
										$paper = [ 'size' => 'A4', 'orientation' => 'portrait' ];
										$args = [ 'filename' => $params['heading']['docno'] ];
										do_action( 'dompdf_generator', $content, $paper, array(), $args );
									}
								break;
							}
						break;
					}

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

				if( $succ && in_array( $action, [ 'post', 'email' ] ) && !empty( $this->warehouse['do_email'] ) )
				{
					if( method_exists( $this, 'mailing' ) ) $this->mailing( $ref_id, $action, $handled[ $ref_id ] );
				}
			}
		}

		return $succ;
	}

		public function mailing( $ref_id = 0, $action = '', $doc = [] )
		{
			if( ! $ref_id || ! $action ) return;

			if( ! $doc )
			{
				$doc = $this->Logic->get_header( [ 'doc_id' => $ref_id ], [], true );
			}

			$rmk = ! empty( $this->remark )? '<br><br>Remark: '.$this->remark : '';

			$client_code = get_document_meta( $ref_id, 'client_company_code', 0, true );
			if( $client_code )
			{
				$client = apply_filters( 'wcwh_get_client', [ 'code'=>$client_code ], [], true );
			}
			if( $client )
			{
				$client_segment = ' for '.$client['name'];
			}

			$subject = 'Delivery Order '.$doc[ $ref_id ]['docno'].$client_segment.' dispatched ';
			$message = 'Delivery Order '.$doc[ $ref_id ]['docno'].$client_segment.' has been posted, and dispatched.';

			$message.= "<style>table{border-spacing:0px;} table th, table td{ border:1px solid; padding:5px; }</style>";
			
			$details = $this->Logic->get_detail( [ 'doc_id'=>$ref_id ], [], false, [ 'uom'=>1, 'usage'=>1 ] );
			if( $details )
			{
				foreach( $details as $i => $item )
				{
					$details[$i]['num'] = ($i+1).".";
			  		$details[$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

			  		$detail_metas = $this->Logic->get_document_meta( $ref_id, '', $item['item_id'] );
			  		$details[$i] = $this->combine_meta_data( $details[$i], $detail_metas );
				}

				$message.= "<br><br>Item Details:<br>";

				$Inst = new WCWH_Listing();
				ob_start();

				echo $Inst->get_listing( [
						'num' => '',
						'prdt_name' => 'Item',
			        	'uom_code' => 'UOM',
			        	'bqty' => 'Qty',
					], 
			      	$details, 
			      	[], 
			      	$hides, 
			      	[ 'off_footer'=>true, 'list_only'=>true ]
			      );

			      $message.= ob_get_clean();
			}

			$doc_remark = get_document_meta( $ref_id, 'remark', 0, true );
			$message.= "<br><br>".$doc_remark;

			$message.= $rmk;

			$args = [
				'id' => 'delivery_order_posted_mail',
				'section' => $this->section_id,
				'datas' => $doc[ $ref_id ],
				'ref_id' => $ref_id,
				'subject' => $subject,
				'message' => $message,
			];
			$args['recipient'] = $this->warehouse['do_email'];
			//if( current_user_cans( ['wh_super_admin'] ) ) $args['recipient'] = 'wid001@suburtiasa.com';

			do_action( 'wcwh_set_email', $args );
			do_action( 'wcwh_trigger_email', [] );
		}

	public function direct_issue_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		if ( !class_exists( "WCWH_GoodIssue_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-issue.php" ); 
		$Inst = new WCWH_GoodIssue_Class( $this->db_wpdb );
		$Inst->setUpdateUqtyFlag( false );

		$succ = true;
		$issue_type = 'delivery_order';
		
		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'delivery_order' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					$header = [
						'warehouse_id' => $this->warehouse['code'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'good_issue_type' => $issue_type,
						'parent' => $doc_header['doc_id'],
						'hstatus' => 9,
						'client_company_code' => $doc_header['client_company_code'],
						'ref_doc_type' => $doc_header['ref_doc_type'],
						'ref_doc_id' => $doc_header['ref_doc_id'],
						'ref_doc' => $doc_header['ref_doc'],
						'direct_issue' => $doc_header['direct_issue'],
						'delivery_doc' => $doc_header['docno'],
						'delivery_doc_id' => $doc_header['doc_id'],
					];
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'transact'=>1, 'usage'=>1 ] );

					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							//update cost to DO
							update_document_meta( $doc_id, 'ucost', round_to( $row['weighted_total'] / $row['bqty'], 5 ), $row['item_id'] );
							update_document_meta( $doc_id, 'tcost', $row['weighted_total'], $row['item_id'] );

							if( $row['tran_bunit'] != 0 )
							{
								update_document_meta( $doc_id, 'sunit', $row['tran_bunit'], $row['item_id'] );
							}

							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$detail[] = [
								'product_id' => $row['product_id'],
								'bqty' => $row['bqty'],
								'sunit' => $row['sunit'],
								'sprice' => $row['sprice'],
								'ucost' => round_to( $row['weighted_total'] / $row['bqty'], 5 ),
								'total_cost' => $row['weighted_total'],
								'item_id' => '',
								'ref_doc_id' => $row['doc_id'],
								'ref_item_id' => $row['item_id'],
								'dstatus' => 9,
							];
						}
					}
					
					if( $header && $detail )
					{
						$result = $Inst->child_action_handle( 'save', $header, $detail );

		                if( ! $result['succ'] )
		                {
							$succ = false;
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
					}
				}
			break;
			case 'unpost':
				$gi_header = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'good_issue', 'status'=>9 ], [], true, [] );
				if( $gi_header )
				{
					$Inst->setAccPeriodExclusive( ['good_issue'] );
					$header = [ 'doc_id'=>$gi_header['doc_id'] ];
					$result = $Inst->child_action_handle( 'trash', $header, [] );
	                if( ! $result['succ'] )
	                {
						$succ = false;
	                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
	                }
				}
			break;
		}

		return $succ;
	}

	public function direct_receive_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		if ( !class_exists( "WCWH_GoodReceive_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-receive.php" ); 
		$Inst = new WCWH_GoodReceive_Class( $this->db_wpdb );
		$Inst->setUpdateUqtyFlag(false);

		$succ = true;		
		switch( $action )
		{
			case 'post':
			case 'egt_restock':
				$need_returnable = true;

				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'delivery_order' ], [], true, ['usage'=>1] );
				if( $doc_header )
				{		
					$client_code = get_document_meta( $doc_id, 'client_company_code', 0, true );
					if( $client_code )
					{
						$client = apply_filters( 'wcwh_get_client', [ 'code'=>$client_code ], [], true, [ 'meta'=>[ 'no_returnable_handling' ] ] );
						if( $client && $client['no_returnable_handling'] ) $need_returnable = false;
					}

					if( $need_returnable )
					{
						$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'transact'=>1, 'usage'=>1 ] );
						if( $doc_detail )
						{
							$detail = [];
							foreach( $doc_detail as $i => $row )
							{
								$metas = get_document_meta( $doc_id, '', $row['item_id'] );
								$row = $this->combine_meta_data( $row, $metas );

								if( $row['returnable_item'] && $row['receive_on_deliver'] )
								{
									$detail[] = [
										'product_id' => $row['returnable_item'],
										'bqty' => $row['bqty'],
										'bunit' => ($row['bunit']>0)? $row['bunit']:0,
										//'uprice' => $row['sprice'],
										'foc' => $row['foc'],
										//'total_amount' => $row['total_amount'],
										'item_id' => '',
										'ref_doc_id' => $row['doc_id'],
										'ref_item_id' => $row['item_id'],
									];
								}
								else
								{
									continue;
								}
							}

							if( $detail )
							{
								$metas = get_document_meta( $doc_id );
								$doc_header = $this->combine_meta_data( $doc_header, $metas );

								$header = [
									'warehouse_id' => ( $this->warehouse['code'] )? $this->warehouse['code'] : $doc_header['warehouse_id'],
									'doc_date' => $doc_header['doc_date'],
									'post_date' => $doc_header['post_date'],
									'parent' => $doc_header['doc_id'],
									'ref_warehouse' => $doc_header['warehouse_id'],
									'ref_doc_type' => $doc_header['doc_type'],
									'ref_doc_id' => $doc_header['doc_id'],
									'ref_doc' => $doc_header['docno'],
									'delivery_doc' => $doc_header['docno'],
									'delivery_warehouse_id' => $doc_header['warehouse_id'],
									'direct_issue' => $doc_header['direct_issue'],
									'client_company_code' => $doc_header['client_company_code'],
									'supplier_company_code' => $doc_header['supplier_company_code'],
								];
							}
						}
						
						if( $header && $detail )
						{
							$result = $Inst->child_action_handle( 'save', $header, $detail );

			                if( ! $result['succ'] )
			                {
								$succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	$result = $Inst->child_action_handle( 'post', [ 'doc_id' => $result['id'] ], [] );
			                	if( ! $result['succ'] )
					            {
					            	$succ = false;
					                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
					            }
					            else
					            {
					            	update_document_meta( $doc_id, 'automate_receipt_id', $result['id'] );
					            }
			                }
						}
					}
				}
			break;
			case 'unpost':
				$good_receive = get_document_meta( $doc_id, 'automate_receipt_id', 0, true );
				if( $good_receive )
				{
					$doc_header = $this->Logic->get_header( [ 'doc_id'=>$good_receive, 'doc_type'=>'good_receive' ], [], true, ['usage'=>1] );
					if( $doc_header )
					{
						$header = [ 'doc_id'=>$doc_header['doc_id'] ];
						$result = $Inst->child_action_handle( 'unpost', $header, [] );
						if( ! $result['succ'] )
		                {
							$succ = false;
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$result = $Inst->child_action_handle( 'delete', $header, [] );
		                	if( ! $result['succ'] )
			                {
								$succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	delete_document_meta( $doc_id, 'automate_receipt_id' );
			                }
		                }

					}
				}
			break;
		}

		return $succ;
	}


	public function automate_sales_handler( $doc_id = 0, $action = '' )
	{
		if( ! $this->setting['wh_delivery_order']['use_auto_sales'] ) return true;
		if( ! $doc_id || ! $action ) return false;
		
		$automate_sale = get_document_meta( $doc_id, 'automate_sale', 0, true );
		if( ! $automate_sale ) return true;

		if ( !class_exists( "WCWH_SaleOrder_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
		$Inst = new WCWH_SaleOrder_Controller();
		$Inst->set_warehouse( $this->warehouse );

		$succ = true;

		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'delivery_order' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					if( ! $doc_header['automate_sale'] ) return true;
					
					$header = [
						'warehouse_id' => ( $this->warehouse['code'] )? $this->warehouse['code'] : $doc_header['warehouse_id'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'client_company_code' => $doc_header['client_company_code'],
						'remark' => $doc_header['remark'],
						'derive_id' => $doc_header['doc_id'],
						'derive_docno' => $doc_header['docno'],
						'derive_type' => $doc_header['doc_type'],
						'automate_sale' => 1,
					];
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$ritem = [
								'product_id' => $row['product_id'],
								'bqty' => $row['bqty'],
								'bunit' => $row['bunit'],
								'item_id' => '',
							];
							if( !empty( $row['receive_on_deliver'] ) ) $ritem['receive_on_deliver'] = $row['receive_on_deliver'];
							if( !empty( $row['returnable_item'] ) ) $ritem['returnable_item'] = $row['returnable_item'];

							$detail[] = $ritem;
						}
					}
					
					if( $header && $detail )
					{
						$doc = [ 'header'=>$header, 'detail'=>$detail ];
						$result = $Inst->action_handler( 'save', $doc, $doc );
						
						if( ! $result['succ'] )
		                {
		                	$succ = false;
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$so_id = $result['id'];	                	
		                	$doc = [ 'id'=>$result['id'] ];
		                	$result = $Inst->action_handler( 'post', $doc, $doc );
		                	if( ! $result['succ'] )
			                {
			                	$succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	$so = $this->Logic->get_header( [ 'doc_id'=>$so_id, 'doc_type'=>'sale_order' ], [], true, [] );
			                	if($so)
			                	{
			                		$metas = get_document_meta( $so['doc_id'] );
									$so = $this->combine_meta_data( $so, $metas );

			                		$upd_header = [];
			                		$upd_header['parent'] = $so['doc_id'];
			                		$succ = $this->Logic->update_document_header( array( 'doc_id' => $doc_id ) , $upd_header );

			                		if($succ)
			                		{
			                			update_document_meta( $doc_id, 'ref_doc_id', $so['doc_id'] );
				                		update_document_meta( $doc_id, 'ref_doc_type', $so['doc_type'] );
				                		update_document_meta( $doc_id, 'ref_doc', $so['docno'] );
				                		update_document_meta( $doc_id, 'ref_status', $so['status'] );
				                		update_document_meta( $doc_id, 'ref_warehouse', $so['warehouse_id'] );

				                		update_document_meta( $doc_id, 'sales_doc', $so['docno'] );
				                		update_document_meta( $doc_id, 'base_doc_type', $so['doc_type'] );
				                		update_document_meta( $doc_id, 'base_doc_id', $so['doc_id'] );
				                		update_document_meta( $doc_id, 'direct_issue', $so['direct_issue'] );

				                		if( $doc_header['client_company_code'] )
				                		{
				                			$seller = apply_filters( 'wcwh_get_warehouse', [ 'client_company_code'=>$header['client_company_code'] ], [], true, [ 'meta'=>[ 'client_company_code' ], 'meta_like'=>[ 'client_company_code'=>1 ] ] );
				                			if( $seller ) update_document_meta( $doc_id, 'supply_to_seller', $seller['code'] );
				                		}

				                		$sDetails = [];
				                		$so_detail = $this->Logic->get_detail( [ 'doc_id'=>$so['doc_id'] ], [], false, [ 'transact'=>1, 'usage'=>1 ] );

				                		if($so_detail)
				                		{
				                			foreach ( $so_detail as $row ) 
				                			{
												$metas = get_document_meta( $row['doc_id'], '', $row['item_id'] );
												$row = $this->combine_meta_data( $row, $metas );
				                				$sDetails[ $row['product_id'] ] = $row;
				                			}

				                			foreach( $doc_detail as $i => $row )
				                			{
				                				$metas = get_document_meta( $doc_id, '', $row['item_id'] );
												$row = $this->combine_meta_data( $row, $metas );

												$ref_row = $sDetails[ $row['product_id'] ];

												$sprice = $ref_row['sprice'];
												if( $sprice > 0 ) update_document_meta( $row['doc_id'], 'sprice', $sprice, $row['item_id'] );

												$sunit = $ref_row['sunit'];
												if( $sunit > 0 ) update_document_meta( $row['doc_id'], 'sunit', $sunit, $row['item_id'] );

												$ucost = $ref_row['weighted_price'];
												if( $ucost > 0 ) update_document_meta( $row['doc_id'], 'ucost', $sunit, $row['item_id'] );

												$upd_item = [];
												$upd_item = [
													'ref_doc_id' =>$ref_row['doc_id'],
													'ref_item_id' => $ref_row['item_id'],
												];

												if( !$this->Logic->update_document_items( [ 'item_id'=>$row['item_id'] ] , $upd_item ) )
												{
													$succ = false;
												}
												else
												{
													if( !$this->Logic->update_document_items_uqty( $ref_row['item_id'], $row['bqty'], $row['bunit'], "+" ) )
														$succ = false;

													if( $succ ) $succ = $this->Logic->update_document_item_status( $ref_row['item_id'], 9 );
												}
				                			}

				                			if($succ)
				                			{
				                				$parent_doc[ $so['doc_id'] ] = $so['doc_id'];
				                				$succ = $this->Logic->update_document_header_status_handles( $parent_doc );
				                			}
				                		}
			                		}
			                	}
			                }
		                }
					}
				}
			break;
			case 'unpost':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'delivery_order' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					$upd_header = [];
            		$upd_header['parent'] = 0;
            		if( !$this->Logic->update_document_header( array( 'doc_id' => $doc_id ) , $upd_header ) )
            		{
            			$succ = false;
            		}

            		if( $succ )
            		{
            			$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );

	            		if($doc_detail)
						{
							foreach ($doc_detail as $i => $row) 
							{
								if( ! $this->Logic->update_document_items_uqty( $row['ref_item_id'], $row['bqty'], $row['bunit'], "-", $this->Logic->getParentStatus() ) )
								{
									$succ = false;
								}
								else
								{
									$upd_item = [
											'ref_doc_id' => 0,
											'ref_item_id' => 0,
									];

									if( ! $this->Logic->update_document_items( [ 'item_id'=>$row['item_id'] ] , $upd_item ) )
									{
										$succ = false;
									}
									else
									{
										$metas = get_document_meta( $row['doc_id'], '', $row['item_id'] );
										$row = $this->combine_meta_data( $row, $metas );

										if( $row['sprice'] ) delete_document_meta( $row['doc_id'], 'sprice', '', $row['item_id'] );

										if( $row['sunit'] ) delete_document_meta( $row['doc_id'], 'sunit', '', $row['item_id'] );

										if( $row['ucost'] ) delete_document_meta( $row['doc_id'], 'ucost', '', $row['item_id'] );
									}
								}								
							}
						}

						if( $succ )
						{
							$parent_doc[ $doc_header['ref_doc_id'] ] = $doc_header['ref_doc_id'];
							$succ = $this->Logic->update_document_header_status_handles( $parent_doc );

							delete_document_meta( $doc_header['doc_id'], 'ref_doc_id' );
							delete_document_meta( $doc_header['doc_id'], 'ref_doc_type' );
							delete_document_meta( $doc_header['doc_id'], 'ref_doc' );
							delete_document_meta( $doc_header['doc_id'], 'ref_status' );
							delete_document_meta( $doc_header['doc_id'], 'ref_warehouse' );

							delete_document_meta( $doc_header['doc_id'], 'sales_doc' );
							//delete_document_meta( $doc_header['doc_id'], 'base_doc_type' );
							delete_document_meta( $doc_header['doc_id'], 'base_doc_id' );
							delete_document_meta( $doc_header['doc_id'], 'direct_issue' );
							if( $doc_header['supply_to_seller'] ) delete_document_meta( $doc_header['doc_id'], 'supply_to_seller' );

							$header = [ 'id'=>$doc_header['ref_doc_id'] ];
							$result = $Inst->action_handler( 'unpost', $header );
							if( ! $result['succ'] )
			                {
								$succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	$result = $Inst->action_handler( 'delete', $header );
			                	if( ! $result['succ'] )
				                {
									$succ = false;
				                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
				                }
			                }
						}

            		}					
				}
			break;
		}

		return $succ;
	}

	/*public function gas_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		$succ = true;

		$EGT_warehouse = maybe_unserialize( get_option( 'EGT_warehouse', [] ) );
		$EGT_item = maybe_unserialize( get_option( 'EGT_warehouse_item', [] ) );
		if( ! $EGT_warehouse || ! $EGT_item ) return $succ;

		$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'sale_return' ], [], true, [] );
		if( $doc_header )
		{
			$metas = get_document_meta( $doc_id );
			$doc_header = $this->combine_meta_data( $doc_header, $metas );

			if( ! in_array( $doc_header['ref_warehouse'], $EGT_warehouse ) ) return $succ;

			$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'item'=>1 ] );
			if( $doc_detail )
			{
				$items = [];
				foreach( $doc_detail as $i => $row )
				{
					if( in_array( $row['prdt_code'], $EGT_item ) )
					{
						$items[ $row['prdt_code'] ]+= $row['bqty'];
					}
				}
			}

			if( count( $items ) > 0 )
			{
				switch( $action )
				{
					case 'post':
						foreach( $items as $code => $bqty )
						{
							$pqty = get_option( 'EGT_'.$doc_header['ref_warehouse'].'_'.$code, 0 );
							update_option( 'EGT_'.$doc_header['ref_warehouse'].'_'.$code, $pqty+$bqty );
						}
					break;
					case 'unpost':
						foreach( $items as $code => $bqty )
						{
							$pqty = get_option( 'EGT_'.$doc_header['ref_warehouse'].'_'.$code, 0 );
							update_option( 'EGT_'.$doc_header['ref_warehouse'].'_'.$code, $pqty-$bqty );
						}
					break;
				}
			}
		}

		return $succ;
	}*/


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = [];

		$default_column['header'] = [ 'doc_id', 'warehouse_id', 'docno', 'sdocno', 'doc_date', 'post_date', 'doc_type', 'hstatus', 'hflag', 'parent', 
			'client_company_code', 'supply_to_seller', 'direct_issue', 'remark', 'sales_doc', 'gr_invoice', 'gr_po', 'purchase_doc', 'purchase_warehouse_id'
		];

		$default_column['detail'] = [ 'item_id', 'strg_id', 'product_id', 'uom_id', 'bqty', 'uqty', 'bunit', 'uunit', 'ref_doc_id', 'ref_item_id', 'dstatus', 
			'sprice', 'ucost', 'sunit', '_item_number'
		];

		$default_column['title'] = array_merge( $default_column['header'], $default_column['detail'] );
		$default_column['default'] = array_merge( $default_column['header'], $default_column['detail'] );

		$default_column['unchange'] = [ 'doc_id', 'item_id' ];

		return $default_column;
	}

	public function export_data_handler( $params = array() )
	{
		$columns = $this->im_ex_default_column();

		$raws = $this->Logic->get_export_data( $params );

		$cols = array_merge( $columns['header'], $columns['detail'] );

		$datas = [];
		foreach( $raws as $i => $row )
		{
			$line = [];
			foreach( $cols as $col )
			{
				$line[ $col ] = $row[ $col ];
				$datas[$i] = $line;
			}
		}

		return $datas;
	}

	public function import_data_handler( $datas, $args = array() )
	{
		if( ! $datas ) return false;

		$succ = true;
		$columns = $this->im_ex_default_column();

		$header_col = $columns['header'];
		$detail_col = $columns['detail'];
		$unchange_col = $columns['unchange'];

		$datas = $this->seperate_import_data( $datas, $header_col, [ 'sdocno' ], $detail_col );
		//update_option('testing',$datas);
		if( $datas )
		{
			wpdb_start_transaction( $this->db_wpdb );
			
			foreach( $datas as $i => $data )
			{
				if( !empty( $unchange_col ) )
				{
					foreach( $unchange_col as $key )
					{
						unset( $data['header'][$key] );
						unset( $data['detail'][$key] );
						foreach( $data['detail'] as $i => $row )
						{
							unset( $data['detail'][$i][$key] );
						}
					}
				}
				
				$header = $data['header'];
				$details = [];

				$ref_items = [];
				if( $header['purchase_doc'] && $header['purchase_warehouse_id'] )
				{
					$ref_doc = $this->Logic->get_header( [ 'docno'=>$header['purchase_doc'], 'warehouse_id'=>$header['purchase_warehouse_id'], 'doc_type'=>'purchase_request' ], [], true, [ 'usage'=>1 ] );
					if( $ref_doc )
					{
						$header['ref_doc_id'] = $ref_doc['doc_id'];
						$header['ref_doc_type'] = $ref_doc['doc_type'];
						$header['ref_doc'] = $ref_doc['docno'];
						$header['parent'] = $ref_doc['doc_id'];

						$ref_detail = $this->Logic->get_detail( [ 'doc_id'=>$ref_doc['doc_id'] ], [], false, [ 'usage'=>1 ] );
						if( $ref_detail )
						{
							foreach( $ref_detail as $j => $det )
							{
								$ref_items[ $det['product_id'] ] = $det;
							}
						}
					}
				}
				
				foreach( $data['detail'] as $i => $row )
				{
					$found = apply_filters( 'wcwh_get_item', [ 'serial'=>$row['product_id'] ], [], true, [] );
					if( $found )
					{
						$details[ $row['_item_number'] ] = $row;
						$details[ $row['_item_number'] ]['product_id'] = $found['id'];
						//$details[$i]['name'] = $found['name'];
						//$details[$i]['code'] = $found['code'];

						if( $ref_items && !empty( $ref_items[ $found['id'] ] ) )
						{
							$details[ $row['_item_number'] ]['ref_doc_id'] = $ref_items[ $found['id'] ]['doc_id'];
							$details[ $row['_item_number'] ]['ref_item_id'] = $ref_items[ $found['id'] ]['item_id'];
						}
					}
					else
					{
						$succ = false;
					}
				}
				
				//pd($header);pd($details);
				if( $succ )
				{
					$succ = $this->Logic->import_handler( 'save', $header, $details );
					if( !$succ )
					{
						break;
					}
					else
					{
						$header_item = $this->Logic->getHeaderItem();
						$detail_items = $this->Logic->getDetailItem();
						
						if( $succ )
						{
							$doc_id = $header_item['doc_id'];
							$succ = $this->automate_purchase_handler( $doc_id, 'post' );
						}
					}
				}
			}
			
			wpdb_end_transaction( $succ, $this->db_wpdb );
		}

		return $succ;
	}

	public function automate_purchase_handler( $doc_id = 0, $action = '' )
	{
		if( ! $this->setting[ $this->section_id ]['use_auto_po'] ) return true;
		if( ! $doc_id || ! $action ) return false;

		$auto_po_source = $this->setting[ $this->section_id ]['auto_po_source'];
		if( ! $auto_po_source ) return true;

		if ( !class_exists( "WCWH_PurchaseOrder_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/purchaseOrderCtrl.php" ); 
		$Inst = new WCWH_PurchaseOrder_Controller();
		$Inst->useFlag = false;
		$Inst->set_logic();

		$succ = false;

		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'delivery_order' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					if( ! in_array( $doc_header['warehouse_id'], $auto_po_source ) ) return true;

					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc_header['warehouse_id'] ], [], true, 
						[ 'meta'=>[ 'supplier_company_code' ], ] );
					$warehouse['supplier_company_code'] = ( is_json( $warehouse['supplier_company_code'] )? json_decode( stripslashes( $warehouse['supplier_company_code'] ), true ) : $warehouse['supplier_company_code'] );

					$supplier = !empty( $warehouse['supplier_company_code'] )? $warehouse['supplier_company_code'][0] : '';

					$header = [
						'warehouse_id' => $doc_header['supply_to_seller'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => '',
						'parent' => $doc_header['doc_id'],
						'supplier_company_code' => $supplier,
						'ref_doc_type' => $doc_header['doc_type'],
						'ref_doc_id' => $doc_header['doc_id'],
						'ref_doc' => $doc_header['docno'],
						'ref_status' => $doc_header['status'],
						'remark' => $doc_header['remark'],
						'invoice' => $doc_header['gr_invoice'],
						'integrated_po' => $doc_header['gr_po'],
						'automate_purchase' => 1,
						'delivery_doc' => $doc_header['docno'],
						'delivery_warehouse_id' => $doc_header['warehouse_id'],
					];
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$uprice = round_to( $row['sprice'], 5 );

							$detail[] = [
								'product_id' => $row['product_id'],
								'bqty' => $row['bqty'],
								'bunit' => $row['bunit'],
								'ref_doc_id' => $row['doc_id'],
								'ref_item_id' => $row['item_id'],
								'uprice' => $uprice,
								'item_id' => '',
							];
						}
					}

					if( $header && $detail )
					{
						$doc = [ 'header'=>$header, 'detail'=>$detail ];
						$result = $Inst->action_handler( 'save', $doc, $doc );
						
						if( ! $result['succ'] )
		                {
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$succ = true;
		                	
		                	$doc = [ 'id'=>$result['id'] ];
		                	$result = $Inst->action_handler( 'post', $doc, $doc );
		                	if( ! $result['succ'] )
			                {
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	$succ = true;
			                }
		                }
					}
				}
			break;
		}

		return $succ;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		//mha004, modify..do not show sale debit doc when it's qty = 0
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			if( current_user_cans( [ 'access_wh_sale_cdnote' ] ))
			{ 
				$this->ref_doc_type = ['sale_order', 'sale_debit_note'];

				$reference = $this->Logic->get_reference_documents( $this->warehouse['code'], $this->ref_doc_type );
				
				foreach ($reference as $key => $value)
				{
					if($value['doc_type'] == "sale_debit_note")
					{
						if( ! $value['inventory_action'] )
						{
							unset($reference[$key]);
							break;
						}
						
						$arr = $this->Logic->get_document_items_by_doc($value['doc_id']);
						foreach($arr as $key2 => $value2)
						{
							if($value2['bqty'] <= 0)
							{
								unset($reference[$key]);
							}
						}
					}
				}
			}	
			else
			{
				$reference = $this->Logic->get_reference_documents( $this->warehouse['code'], $this->ref_doc_type );
			}	
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'client_name', 'remark' ], 'New Delivery Order by Document (GI Required)' );
	        echo '<div id="delivery_order_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'delivery_order_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#delivery_order_action"'], 'class'=>['select2','triggerChange'], 
	                'options'=> $options, 'offClass'=>true
	            ], 
	            ''
	        );
	        echo '</div>';
		}
		//end
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
				<button id="delivery_order_action" class="btn btn-sm btn-primary linkAction <?php if( ! $this->setting[ $this->section_id ]['use_auto_sales'] ) echo 'display-none'?>" title="Add <?php echo $actions['save'] ?> Delivery Order"
					data-title="<?php echo $actions['save'] ?> Delivery Order" 
					data-action="delivery_order_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#delivery_order_reference" <?php if( ! $this->setting[ $this->section_id ]['use_auto_sales'] ) echo 'data-strict="yes"' ?>
				>
					<?php echo $actions['save'] ?> Delivery Order
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?> Items" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?> Items"
				>
					<i class="fa fa-upload" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'export':
				if( current_user_cans( [ 'export_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Items" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Items"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
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
			case 'sale_debit_note':
				if( ! class_exists( 'WCWH_SaleCDNote_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/saleCDNoteCtrl.php" ); 
				$Inst = new WCWH_SaleCDNote_Controller();
			break;
			case 'sale_order':
				if( ! class_exists( 'WCWH_SaleOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
				$Inst = new WCWH_SaleOrder_Controller();
			break;
			case 'transfer_order':
				if( ! class_exists( 'WCWH_TransferOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/transferOrderCtrl.php" ); 
				$Inst = new WCWH_TransferOrder_Controller();
			break;
			case 'good_issue':
				if( ! class_exists( 'WCWH_GoodIssue_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
				$Inst = new WCWH_GoodIssue_Controller();
			break;
			case 'purchase_request':
				if( ! class_exists( 'WCWH_PurchaseRequest_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" ); 
				$Inst = new WCWH_PurchaseRequest_Controller();
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

	public function view_linked_doc( $doc_id = 0, $config = [] )
	{
		if( ! $doc_id ) return false;

		$childs = [];
		$childs = $this->Logic->get_child_doc_ids( $doc_id );
		if( !empty( $childs ) )
		{
			$Objs = []; $segments = []; $titles = [];
			foreach( $childs as $i => $doc )
			{
				$doc_type = $doc['doc_type'];
				switch( $doc_type )
				{
					case 'delivery_order':
						if( current_user_cans( [ 'access_wh_delivery_order' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_DeliveryOrder_Controller();
							$titles[ $doc_type ] = "Delivery Order";
						}
					break;
					case 'good_receive':
						if( current_user_cans( [ 'access_wh_good_receive' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_GoodReceive_Controller();
							$titles[ $doc_type ] = "Goods Receipt";
						}
					break;
					case 'good_issue':
						if( current_user_cans( [ 'access_wh_good_issue' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_GoodIssue_Controller();
							$titles[ $doc_type ] = "Goods Issue";
						}
					break;
					case 'do_revise':
						if( current_user_cans( [ 'access_wh_do_revise' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/DOReviseCtrl.php" ); 
							$Objs[ $doc_type ] = new DORevise_Controller();
							$titles[ $doc_type ] = "DO Revise";
						}
					break;
					case 'issue_return':
						if( current_user_cans( [ 'access_wh_issue_return' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/issueReturnCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_IssueReturn_Controller();
							$titles[ $doc_type ] = "Issue Return";
						}
					break;
				}

				if( !empty( $Objs[ $doc_type ] ) )
				{
					$Inst = $Objs[ $doc_type ];

					$segment = [];
					ob_start();
						$Inst->view_form( $doc['doc_id'], false, true, true, [ 'ref'=>0, 'link'=>1 ] );
					$segment[ $doc['docno'] ] = ob_get_clean();

					$args = [];
					$args[ 'id' ] = $this->section_id;
					$args[ 'accordions' ] = $segment;

					ob_start();
					do_action( 'wcwh_get_template', 'segment/accordion.php', $args );
					$segments[ $doc_type ][] = ob_get_clean();
				}
			}

			if( !empty( $segments ) )
			{
				foreach( $segments as $type => $docs )
				{
					if( !empty( $docs ) ) 
					{
						$content = implode( " ", $docs );
						echo "<div class='form-rows-group'><h5>{$titles[ $type ]}</h5>{$content}</div>";
					}
				}
			}
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

				$args['data']['purchase_doc'] = $header['purchase_doc'];
				if( in_array( $header['doc_type'], [ 'good_issue' ] ) )
				{
					if( $header['ref_doc_type'] == 'sale_order' ) $args['data']['sales_doc'] = $header['ref_doc'];
				}
				else if( in_array( $header['doc_type'], [ 'sale_order', 'transfer_order' ] ) )
				{
					$args['data']['sales_doc'] = $header['docno'];
				}
				if (in_array( $header['ref_doc_type'], [ 'tool_request' ] ))
				{
					$args['data']['remark'] = $header['remark'];
				}

				$args['data']['client_company_code'] = $header['client_company_code'];
				$args['data']['supply_to_seller'] = $header['supply_to_seller'];

				$args['NoAddItem'] = !empty( $this->setting[ $this->section_id ]['add_item'] )? false : true;
			}

			$filters = [ 'doc_id'=>$id ];
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1, 'stocks'=>$this->warehouse['code'], 'returnable'=>1 ] );
			if( $items )
			{
				$c_items = []; $inventory = [];
		        foreach( $items as $i => $item ) if( $item['parent'] > 0 ) $c_items[] = $item['product_id'];
		        if( count( $c_items ) > 0 )
		        {
		        	$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
					$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

					$filter = [ 'wh_code'=>$this->warehouse['code'], 'strg_id'=>$storage['id'], 'item_id'=>$c_items ];
					$stocks = apply_filters( 'wcwh_get_stocks', $filter, [], false, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
					if( $stocks )
					foreach( $stocks as $i => $stock )
					{
						$inventory[ $stock['id'] ] = $stock;
					}
		        }

				$details = array();
				foreach( $items as $i => $item )
				{	
					if( ! $item['product_id'] ) continue;
					$stk = ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['qty'] : $item['stock_qty'];
		        	$stk-= ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['allocated_qty'] : $item['stock_allocated'];

		        	$returnable_item = $item['returnable_item'];
		        	$metas = get_document_meta( $item['doc_id'], '', $item['item_id'] );
					$item = $this->combine_meta_data( $item, $metas );
					
					$details[] = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk, ],
						'bqty' => ( $item['bqty'] - $item['uqty'] ),
						'bunit' => ( $item['bunit'] - $item['uunit'] ),
						'ref_bqty' => $item['bqty'],
						'ref_bal' => ( $item['bqty'] - $item['uqty'] ),
						'ref_doc_id' => $item['doc_id'],
						'ref_item_id' => $item['item_id'],
						'returnable_item' => !empty( $item['returnable_item'] )? $item['returnable_item'] : $returnable_item,
						'receive_on_deliver' => $item['receive_on_deliver'],
					);
				}
				$args['data']['details'] = $details;
			}
		}

		if( $args['data']['ref_doc_id'] )
			$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/deliveryOrder-form.php', $args );
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
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'transact'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'returnable'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'] ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				$args['NoAddItem'] = !empty( $this->setting[ $this->section_id ]['add_item'] )? false : true;

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_delivery_order'] ) )
				{
					$hides = [ 'sprice', 'total_amount', 'ucost', 'total_cost', 'total_profit' ];
				}
		        	
		        if( $datas['details'] )
		        {	
		        	$c_items = []; $inventory = [];
		        	foreach( $datas['details'] as $i => $item ) if( $item['parent'] > 0 ) $c_items[] = $item['product_id'];
		        	if( count( $c_items ) > 0 )
		        	{
		        		$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
						$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

						$filter = [ 'wh_code'=>$this->warehouse['code'], 'strg_id'=>$storage['id'], 'item_id'=>$c_items ];
						$stocks = apply_filters( 'wcwh_get_stocks', $filter, [], false, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
						if( $stocks )
						foreach( $stocks as $i => $stock )
						{
							$inventory[ $stock['id'] ] = $stock;
						}
		        	}

		        	$total_amount = 0; $total_cost = 0; $total_profit = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$stk = ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['qty'] : $item['stock_qty'];
		        		$stk-= ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['allocated_qty'] : $item['stock_allocated'];
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 
							'stocks' => $stk,
		        		];

		        		$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;
		        		
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2, true );
		        		$datas['details'][$i]['bunit'] = round_to( $datas['details'][$i]['sunit'], 3, true );

		        		$datas['details'][$i]['sprice'] = round_to( $datas['details'][$i]['sprice'], 5, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['bqty'] * $datas['details'][$i]['sprice'], 2, true );
		        		$total_amount+= $datas['details'][$i]['total_amount'];

		        		$datas['details'][$i]['total_cost'] = round_to( $item['weighted_total'], 2, true );
		        		$total_cost+= $datas['details'][$i]['total_cost'];
		        		$datas['details'][$i]['ucost'] = round_to( $datas['details'][$i]['total_cost'] / $datas['details'][$i]['bqty'], 5, true );

		        		$datas['details'][$i]['total_profit'] = round_to( $datas['details'][$i]['total_amount'] - $datas['details'][$i]['total_cost'], 2, true );
		        		$total_profit+= $datas['details'][$i]['total_profit'];
		        	}

		        	if( $isView && ! $hides )
		        	{
		        		$final = [];
			        	$final['prdt_name'] = '<strong>TOTAL:</strong>';
			        	$final['total_amount'] = '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
			        	$final['total_cost'] = '<strong>'.round_to( $total_cost, 2, true ).'</strong>';
			        	$final['total_profit'] = '<strong>'.round_to( $total_profit, 2, true ).'</strong>';
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
		        		'sprice' => 'Price',
		        		'total_amount' => 'Total Amt',
		        		'ucost' => 'Cost',
		        		'total_cost' => 'Total Cost',
		        		'total_profit' => 'Profit',
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
			do_action( 'wcwh_templating', 'form/deliveryOrder-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/deliveryOrder-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/deliveryOrder-row.php', $this->tplName['row'] );
	}

	public function import_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'import',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['import'],
		);

		do_action( 'wcwh_templating', 'import/import-do.php', $this->tplName['import'], $args );
	}

	public function export_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['export'],
		);

		do_action( 'wcwh_templating', 'export/export-do.php', $this->tplName['export'], $args );
	}

	public function do_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['do'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
		);

		do_action( 'wcwh_templating', 'form/deliveryOrder-print-form.php', $this->tplName['do'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/deliveryOrderListing.php" ); 
			$Inst = new WCWH_DeliveryOrder_Listing();

			if( current_user_cans( [ 'access_'.$this->section_id ] ) )
				$this->ref_doc_type = ['sale_order','sale_debit_note'];

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

			$metas = [ 'remark', 'vehicle', 'ref_doc_id', 'ref_doc_type', 'ref_doc', 'client_company_code', 'sales_doc', 'base_doc_type', 'base_doc_id', 'direct_issue', 'supply_to_seller', 'purchase_doc' ];

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