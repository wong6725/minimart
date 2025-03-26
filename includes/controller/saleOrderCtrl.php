<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_SaleOrder_Class" ) ) include_once( WCWH_DIR . "/includes/classes/sale-order.php" ); 

if ( !class_exists( "WCWH_SaleOrder_Controller" ) ) 
{

class WCWH_SaleOrder_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_sales_order";

	public $Notices;
	public $className = "SaleOrder_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newSO',
		'row' => 'rowSO',
		'fee' => 'fee',
		'cusrow' => 'rowCusSO',
		'pl' => 'printPL',
		'inv' => 'printINV',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1, 6 ];

	protected $remark = "";

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
		$this->Logic = new WCWH_SaleOrder_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->processing_stat = $this->processing_stat;

		add_filter( 'wcwh_einvoice_necessity', array( $this, 'einvoice_necessity' ), 10, 2 );
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
						$prdt_ids = [];
						foreach( $datas['detail'] as $i => $row )
						{
							$prdt_ids[$i] = $row['product_id'];
						}

						if( $datas['header']['client_company_code'] )
						{
							$client = apply_filters( 'wcwh_get_client', [ 'code'=>$datas['header']['client_company_code'] ], [], true, [ 'meta'=>['no_metric_sale'] ] );
							if( $client && $client['no_metric_sale'] )
							{
								$args = [ 'isMetric'=>'yes' ];
								if( $this->setting[ $this->section_id ]['no_kg_excl_cat'] )
									$args[ 'isMetricExclCat' ] = $this->setting[ $this->section_id ]['no_kg_excl_cat'];

								$exists = apply_filters( 'wcwh_get_item', [ 'id'=>$prdt_ids ], [], false, $args );
								if( $exists )
								{
									$succ = false;
									$this->Notices->set_notice( "Item with UOM: ".implode( ", ", $this->refs['metric'] )." Not allowed for selected client", 'warning' );
								}
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
					$fees = $datas['fee'];

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;
					
					if( in_array( $header['client_company_code'], $this->setting[ $this->section_id ]['direct_issue_client'] ) )
					{
						$header['direct_issue'] = 1;
					}
					else
					{
						$header['direct_issue'] = 0;
					}

					$ref_detail = [];
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

							if( in_array( $ref_header['doc_type'], [ 'purchase_request', 'purchase_order' ] ) )
							{
								$header['purchase_warehouse_id'] = $header['ref_warehouse'];
							}

							if( in_array( $ref_header['doc_type'], [ 'tool_request', 'purchase_request' ] ) )
							{
								$metas = get_document_meta( $ref_header['doc_id'] );
								$ref_header = $this->combine_meta_data( $ref_header, $metas );
								if( $metas['customer_id'] ) $header['customer_id'] = $ref_header['customer_id'];
								if( $metas['customer_code'] ) $header['customer_code'] = $ref_header['customer_code'];
								if( $metas['customer_uid'] ) $header['customer_uid'] = $ref_header['customer_uid'];
								if( $metas['acc_code'] ) $header['acc_code'] = $ref_header['acc_code'];

								$rdetail = $this->Logic->get_detail( [ 'doc_id'=>$ref_header['doc_id'], 'doc_type'=>$ref_header['doc_type'] ], [], false, ['meta'=>['sprice','sale_amt'] ] );
								if( $rdetail )
								{
									foreach( $rdetail as $z => $rrow )
									{
										$ref_detail[ $rrow['item_id'] ] = $rrow;
									}
								}
							}
						}
					}

					if( $detail )
					{	
						$header['total'] = 0;
						foreach( $detail as $i => $row )
						{
							$price = ( $row['product_id'] )? apply_filters( 'wcwh_get_price', $row['product_id'], $this->warehouse['code'], 
								[ 'client_code'=>$header['client_company_code'] ], $header['doc_date'] ) : [];
							if( isset( $row['cprice'] ) ) $price['unit_price'] = $row['cprice'];
							unset( $detail[$i]['cprice'] );

							if( !$row['receive_on_deliver'] )
							{
								if( !$row['receive_on_deliver'] && $row['returnable_item'] ) //for edit/update condition
								{
									$detail[$i]['receive_on_deliver'] = 0; //set to 0 to make sure the meta will be deleted
								}
								$detail[$i]['returnable_item'] = 0; //set to 0 to make sure the meta will be deleted
							}
							
							if( ! $header['automate_sale'] && ( ! $price || ! isset( $price['unit_price'] ) || $price['unit_price'] <= 0 ) )
							{
								$succ = false;
								$item = apply_filters( 'wcwh_get_item', [ 'id'=>$row['product_id'] ], [], true, [] );
								$item = $item['code'].' - '.$item['name'];
								$this->Notices->set_notice( 'No selling price for item '.$item.' on date '.$header['doc_date'].'.', 'error' );
							}
							else
							{
								if( $row['product_id'] )
									$item = apply_filters( 'wcwh_get_item', [ 'id'=>$row['product_id'] ], [], true, [ 'isUnit'=>1 ] );
								$detail[$i]['sprice'] = $price['unit_price'];

								if( $item['inconsistent_unit'] && $item['required_unit'] )
								{
									if( in_array( $ref_header['doc_type'], [ 'purchase_request' ] ) && !empty( $ref_detail[ $row['ref_item_id'] ]['bunit'] ) )
									{
										$per_unit = round_to( round_to( $ref_detail[ $row['ref_item_id'] ]['bunit'], 3 ) / $row['bqty'], 3 );
										$detail[$i]['sunit'] = round_to( $ref_detail[ $row['ref_item_id'] ]['bunit'], 3 );
										$detail[$i]['sprice'] = round_to( $per_unit * $price['unit_price'], 2 );
										$detail[$i]['bunit'] = round_to( $ref_detail[ $row['ref_item_id'] ]['bunit'], 3 );
									} 
									else if( isset( $row['sunit'] ) && $row['sunit'] > 0 )
									{
										$per_unit = round_to( round_to( $row['sunit'], 3 ) / $row['bqty'], 3 );
										$detail[$i]['sunit'] = round_to( $row['sunit'], 3 );
										$detail[$i]['sprice'] = round_to( $per_unit * $price['unit_price'], 2 );
									}
									else
									{
										$strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'doc_type'=>'sale_order', 'warehouse_id'=>$this->warehouse['code'] ], [] );
										$f = [
											'warehouse_id' => $this->warehouse['code'],
											'strg_id' => $strg_id,
											'prdt_id' => $row['product_id'],
										];
										$inv = apply_filters( 'wcwh_get_inventory', $f, [], true, [] );

										if( ! $inv || ( $inv && $inv['total_in_unit'] <= 0  ) )
										{
											if( !empty( $this->setting['inv_db_suffix'] ) )
											{
												$arg = [ 'db_suffix' => $this->setting['inv_db_suffix'] ];
												$inv = apply_filters( 'wcwh_get_inventory', $f, [], true, $arg );

												if( ! $inv || ( $inv && $inv['total_in_unit'] <= 0  ) )
												{
													$succ = false;
													$item = $item['code'].' - '.$item['name'];
													$this->Notices->set_notice( "{$item} with inconsistent metric(kg/l), required stocks for selling price.", 'error' );
												}
												else
												{
													$per_unit = round_to( ( $inv['total_in_unit'] ) / $inv['total_in'], 3 );
													$detail[$i]['sunit'] = round_to( $per_unit * $row['bqty'], 3 );
													$detail[$i]['sprice'] = round_to( $per_unit * $price['unit_price'], 2 );
												}
											}
											else
											{
												$succ = false;
												$item = $item['code'].' - '.$item['name'];
												$this->Notices->set_notice( "{$item} with inconsistent metric(kg/l), required stocks for selling price.", 'error' );
											}
										}
										else
										{
											$per_unit = ( $inv['wa_unit'] > 0 )? round_to( ( $inv['wa_unit'] ) / $inv['wa_qty'], 3 ) : round_to( ( $inv['total_in_unit'] ) / $inv['total_in'], 3 );
											$detail[$i]['sunit'] = round_to( $per_unit * $row['bqty'], 3 );
											$detail[$i]['sprice'] = round_to( $per_unit * $price['unit_price'], 2 );
										}
									}

									$detail[$i]['unit_price'] = $price['unit_price'];
								}
							}

							if( in_array( $ref_header['doc_type'], [ 'tool_request' ] ) )
							{
								if( $ref_detail[ $row['ref_item_id'] ] ) 
									$detail[$i]['sprice'] = $ref_detail[ $row['ref_item_id'] ]['sprice'];
							}

							$detail[$i]['def_sprice'] = round_to( $detail[$i]['sprice'], 2 );

							$detail[$i]['line_subtotal'] = $row['bqty'] * round_to( $detail[$i]['sprice'], 2 );
							$detail[$i]['line_total'] = $detail[$i]['line_subtotal'];
							if( $row['discount'] )
							{
								$disc_amt = wh_apply_discount( $detail[$i]['line_total'], $row['discount'] );
								$detail[$i]['line_total'] = $detail[$i]['line_total'] - $disc_amt;
								$detail[$i]['sprice'] = round_to( $detail[$i]['line_total'] / $row['bqty'], 5 );

								$detail[$i]['line_discount'] = $disc_amt;
								$header['total_discount']+= $disc_amt;
							}
							$header['subtotal']+= $detail[$i]['line_subtotal'];
							$header['discounted_subtotal']+= $detail[$i]['line_total'];
							$header['total']+= $detail[$i]['line_total'];

							if( $row['foc'] > 0 )
								$detail[$i]['bqty'] = $row['bqty'] + $row['foc'];
						}
						
						$total = $header['total'];
						if( $header['discount'] )
						{
							$disc_amt = wh_apply_discount( $header['total'], $header['discount'] );
							$header['total'] = $header['total'] - $disc_amt;

							$header['sale_discount'] = $disc_amt;
							$header['total_discount']+= $disc_amt;
						}
						
						//prorate
						foreach( $detail as $i => $row )
						{
							$rate = $row['line_total'] / $total;
							$detail[$i]['line_total'] = $header['total'] * $rate;
							$detail[$i]['sprice'] = round_to( $detail[$i]['line_total'] / $row['bqty'], 5 );
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
							$doc_id = $result['id'];
							delete_document_meta( $doc_id, 'fee' );
							if( $fees )
							{	
								foreach( $fees as $i => $fee )
								{
									add_document_meta( $doc_id, 'fee', maybe_serialize( $fee ) );
								}
							}

							if( in_array( $ref_header['doc_type'], [ 'tool_request' ] ) )
							{
								$tool_doc = [ 'doc_id'=>$ref_header['doc_id'] ];
								$succ = apply_filters( 'wcwh_tool_request_completion', $succ, $tool_doc );
							}

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
				case "update-header":
					$header = $datas['header'];
					$detail = $datas['detail'];

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					$result = $this->Logic->child_action_handle( $action, $header, [] );
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
					}
				break;
				case "delete":
				case "post":
				case "unpost":
				case "complete":
				case "incomplete":
				case "close":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
							$doc_header = $this->Logic->get_header( [ 'doc_id'=>$header['doc_id'], 'doc_type'=>'none' ], [], true, ['meta'=>['ref_doc_type','ref_doc_id'] ] );
							//$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$header['doc_id'] ], [], false, [ 'usage'=>1 ] );
							if( $succ && in_array( $action, [ 'incomplete' ] ) )
							{
								$succ = $this->automate_sales_handler( $id, $action );
							}
							
							if( $succ ) $result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							if( $succ && in_array( $action, [ 'post' ] ) )
							{
								$succ = $this->automate_sales_handler( $result['id'], $action );
							}

							if( $succ && in_array( $action, [ 'post', 'unpost' ] ) )
							{
								$succ = $this->einvoice_handler( $result['id'], $action );
							}

							if( $succ )
							{
								$outcome['id'][] = $result['id'];
								$count_succ++;

								if( in_array( $doc_header['ref_doc_type'], [ 'tool_request' ] ) )
								{
									$tool_doc = [ 'doc_id'=>$doc_header['ref_doc_id'] ];
									$succ = apply_filters( 'wcwh_tool_request_completion', $succ, $tool_doc );
								}

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
				case "email":
					$succ = true;
					$outcome['id'] = $datas['id'];
					$this->remark = $datas['remark'];
				break;
				case "print":
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
						case 'invoice':
							$margining_id = 'wh_sales_order_invoice';

							$lhdn = $this->Logic->get_header( [ 'doc_type'=>'e_invoice', 'parent'=>$id ], [], true, [ 'posting'=>1 ] );
							if( $lhdn )
							{
								if ( !class_exists( "WCWH_EInvoice_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/eInvoiceCtrl.php" ); 
								$Inst = new WCWH_EInvoice_Controller();
								$Inst->set_warehouse( $this->warehouse );
								$datas['id'] = $lhdn['doc_id'];
								$result = $Inst->action_handler( 'print', $datas, $lhdn );
								exit;
							}

							$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
							$doc = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [] );
							if( $doc )
							{	
								$doc['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
								//metas
								$metas = get_document_meta( $id );
								$doc = $this->combine_meta_data( $doc, $metas );

								if( $doc['payment_term'] )
								$pmt = apply_filters( 'wcwh_get_payment_term', [ 'code'=>$doc['payment_term'] ], [], true );

								$dos = $this->Logic->get_header( [ 'doc_type'=>'delivery_order', 'base_doc_id'=>$id ], [], false, [ 'posting'=>1, 'meta'=>['base_doc_id'] ] );
								$inv_dos = [];
								if( $dos )
								{
									foreach( $dos as $do )
									{
										$inv_dos[] = $do['docno'];
									}
								}

								$date_format = get_option( 'date_format' );
								$params['heading']['docno'] = $doc['docno'].'-I01';
								$params['heading']['discount'] = $doc['discount'];
								$params['heading']['total'] = $doc['total'];
								$params['heading']['infos'] = [
									'Invoice No.' => $doc['docno'].'-I01',
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
									'Contract No.' => $doc['docno'],
									'Payment Term' => ( $pmt )? $pmt['name'] : '',
									'DO No.' => ( $inv_dos )? implode( ', ', $inv_dos ) : '',
								];

								if( $doc['fee'] )
								{
									$doc['fee'] = is_array( $doc['fee'] )? $doc['fee'] : [ 0=>$doc['fee'] ];
									foreach( $doc['fee'] as $fee )
									{
										$params['heading']['fees'][] = maybe_unserialize( $fee );
									}
								}

								$addr_format = "{company}\n{address_1}\n{postcode} {city} {state_upper} {country_upper}\n{phone}";

								$billing = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'billing' ] );
								if( $billing )
								{
									$params['heading']['first_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'company'    => $billing['name'],
										'address_1'  => ( $doc['diff_billing_address'] )? $doc['diff_billing_address'] : $billing['address_1'],
										'postcode'   => ( $doc['diff_billing_postcode'] )? $doc['diff_billing_postcode'] : $billing['postcode'],
										'city'       => ( $doc['diff_billing_city'] )? $doc['diff_billing_city'] : $billing['city'],
										'state'      => ( $doc['diff_billing_state'] )? $doc['diff_billing_state'] : $billing['state'],
										'country'    => ( $doc['diff_billing_country'] )? $doc['diff_billing_country'] : $billing['country'],
										'phone'		 => ( $doc['diff_billing_contact'] )? $doc['diff_billing_contact'] : $billing['contact_person'].' '.$billing['contact_no'],
									], '', $addr_format );
								}
								
								$shipping = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'shipping' ] );
								if( $shipping )
								{
									$params['heading']['second_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'company'    => $shipping['name'],
										'address_1'  => ( $doc['diff_shipping_address'] )? $doc['diff_shipping_address'] : $shipping['address_1'],
										'postcode'   => ( $doc['diff_shipping_postcode'] )? $doc['diff_shipping_postcode'] : $shipping['postcode'],
										'city'       => ( $doc['diff_shipping_city'] )? $doc['diff_shipping_city'] : $shipping['city'],
										'state'      => ( $doc['diff_shipping_state'] )? $doc['diff_shipping_state'] : $shipping['state'],
										'country'    => ( $doc['diff_shipping_country'] )? $doc['diff_shipping_country'] :$shipping['country'],
										'phone'		 => ( $doc['diff_shipping_contact'] )? $doc['diff_shipping_contact'] : $shipping['contact_person'].' '.$shipping['contact_no'],
									], '', $addr_format );
								}

								if( $doc['status'] > 0 )
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'usage'=>1, 'ref'=>1 ] );
								else
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'ref'=>1 ] );
							    
							    $margining = [];
								if( $this->setting['general']['use_margining'] )
								{	
									$sap_po = ( $doc['sap_po'] )? 1 : -1;
									$margining = apply_filters( 'wcwh_get_margining', $doc['warehouse_id'], $margining_id, $doc['client_company_code'], $doc['doc_date'], 'any', $sap_po );
									//pd($margining,1);
								}

							    if( $doc['details'] )
							    {
							    	$detail = [];
							        foreach( $doc['details'] as $i => $item )
							        {
							        	$detail_metas = get_document_meta( $id, '', $item['item_id'] );
		        						$item = $this->combine_meta_data( $item, $detail_metas );

		        						if( $doc['status'] >= 9 )
							        	{
							        		$item['lqty'] = round_to( $item['bqty'] - $item['uqty'], 2 );
							        		if( $item['uqty'] <= 0 ) continue;

							        		$item['bqty'] = $item['uqty'];
							        	}

							        	$row = [];
							        	$row['item'] = $item['prdt_code'].' - '.$item['prdt_name'];
							        	if( ! $item['product_id'] ) $row['item'] = $item['custom_item'];
							        	if( $datas['view_type'] == 'category' ) $row['item'] = $item['cat_code'].' - '.$item['cat_name'];
							        	if( $withSpec ){ $row['item'].= nl2br( "<br>Spec: ".$item['spec'] ); }
							        	$row['uom'] = $item['uom_code'];

							        	$row['qty'] = round_to( ( $item['foc'] > 0 )? $item['bqty'] - $item['foc'] : $item['bqty'], 2 );
							        	$row['foc'] = round_to( $item['foc'], 2 );

							        	$row['sprice'] = round_to( $item['sprice'], 2 );
							        	$row['def_sprice'] = round_to( ( $item['def_sprice'] )? $item['def_sprice'] : $item['sprice'], 2 );
							        	$row['total_amount'] = round_to( $row['qty'] * $row['def_sprice'], 2 );

						        		if( strstr( $item['discount'], '%' ) )
						        		{
						        			$row['discount'] = wh_apply_discount( $row['total_amount'], $item['discount'] );
						        			$row['disc'] = "".$item['discount'];
						        			$row['disc_separator'] = ";";
						        		}
						        		else
						        		{
						        			$row['discount'] = round_to( $item['discount'], 2, true );
						        		}
						        		$row['final_amount'] = round_to( $row['total_amount'] - $row['discount'], 2, true );

						        		//margining recalc
						        		if( !empty( $margining ) && ( $margining['margin'] > 0 || $margining['margin'] < 0 ) )
						        		{
						        			$row['def_sprice'] = round( $row['def_sprice'] * ( 1+( $margining['margin']/100 ) ), 2 );
						        			
						        			$rn = ( $margining['round_nearest'] != 0 )? abs( $margining['round_nearest'] ) : 0.01;
						        			switch( $margining['round_type'] )
						        			{	
						        				case 'ROUND':
						        					$row['def_sprice'] = round( $row['def_sprice'] / $rn ) * $rn;
						        				break;
						        				case 'CEIL':
						        					$row['def_sprice'] = ceil( $row['def_sprice'] / $rn ) * $rn;
						        				break;
						        				case 'FLOOR':
						        					$row['def_sprice'] = floor( $row['def_sprice'] / $rn ) * $rn;
						        				break;
						        				default:
						        					$row['def_sprice'] = $row['def_sprice'];
						        				break;
						        			}

						        			//recalc
						        			$row['total_amount'] = round_to( $row['qty'] * $row['def_sprice'], 2 );
						        			if( strstr( $item['discount'], '%' ) )
							        		{
							        			$row['discount'] = wh_apply_discount( $row['total_amount'], $item['discount'] );
							        			$row['disc'] = "".$item['discount'].";";
							        		}
							        		else
							        		{
							        			$row['discount'] = round_to( $item['discount'], 2, true );
							        		}
							        		$row['final_amount'] = round_to( $row['total_amount'] - $row['discount'], 2, true );
						        		}

							        	$detail[] = $row;
							        }
							        //pd($detail,1);
							        $params['detail'] = $detail;
							    }
							}

							switch( strtolower( $datas['paper_size'] ) )
							{
								case 'receipt':
									$params['print'] = 1;
									ob_start();
										do_action( 'wcwh_get_template', 'template/receipt-invoice.php', $params );
									$content = ob_get_clean();

									echo $content;
								break;
								case 'default':
								default:
									ob_start();
										do_action( 'wcwh_get_template', 'template/doc-invoice.php', $params );
									$content = apply_filters( "wcwh_{$this->section_id}_invoice_content", ob_get_clean(), $params );
									
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
						case 'picking_list':
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
								$params['heading']['second_infos'] = [
									'SO. No.' => $doc['docno'],
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
									'Print Date' => date_i18n( $date_format, current_time( 'mysql' ) ),
								];
								if( $doc['ref_doc'] ) $params['heading']['second_infos']['Ref. Doc.'] = $doc['ref_doc'];

								$client = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'shipping' ] );
								if( $client )
								{
									$addr_format = "{company}\n{address_1}\n{postcode} {city} {state_upper} {country_upper}";

									$params['heading']['first_infos']['Consignee'] = $client['name'];
									$params['heading']['first_infos']['Consignee Address'] = apply_filters( 'wcwh_get_formatted_address', [
										'address_1'  => ( $doc['diff_shipping_address'] )? $doc['diff_shipping_address'] : $client['address_1'],
										'postcode'   => ( $doc['diff_shipping_postcode'] )? $doc['diff_shipping_postcode'] : $client['postcode'],
										'city'       =>  ( $doc['diff_shipping_city'] )? $doc['diff_shipping_city'] : $client['city'],
										'state'      => ( $doc['diff_shipping_state'] )? $doc['diff_shipping_state'] : $client['state'],
										'country'    => ( $doc['diff_shipping_country'] )? $doc['diff_shipping_country'] : $client['country'],
									], '', $addr_format );
									$params['heading']['first_infos']['Contact'] = trim( $client['contact_person'].' '.$client['contact_no'] );
								}

								//base doc data
								$contact = get_document_meta( $doc['doc_id'], 'diff_shipping_contact', 0, true );
								if( $contact ) $params['heading']['first_infos']['Contact'] = nl2br( $contact );

								$params['heading']['first_infos'] = [
									'Consignee' => ( $params['heading']['first_infos']['Consignee'] )? $params['heading']['first_infos']['Consignee'] : 'N/A',
									'Consignee Address' => ( $params['heading']['first_infos']['Consignee Address'] )? $params['heading']['first_infos']['Consignee Address'] : 'N/A',
									'Contact' => ( $params['heading']['first_infos']['Contact'] )? $params['heading']['first_infos']['Contact'] : 'N/A',
								];

								if( $doc['status'] > 0 )
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1 ] );
								else
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1 ] );
							    
							    if( $doc['details'] )
							    {
							    	$detail = [];
							        foreach( $doc['details'] as $i => $item )
							        {
							        	if( ! $item['product_id'] ) continue;

							        	$item['lqty'] = $item['bqty'] - $item['uqty'];
							        	if( $item['lqty'] <= 0 ) continue;

							        	$detail_metas = get_document_meta( $id, '', $item['item_id'] );
		        						$item = $this->combine_meta_data( $item, $detail_metas );

							        	$row = [];
							        	$row['item'] = $item['prdt_code'].' - '.$item['prdt_name'];
							        	$row['uom'] = $item['uom_code'];
							        	$row['qty'] = round_to( $item['lqty'], 2 );

							        	$detail[] = $row;
							        }

							        $params['detail'] = $detail;
							    }
							}

							if( $params )
							{
								add_document_meta( $doc['doc_id'], 'pl_print_'.current_time( 'Ymd' ), serialize( $params ) );
							}
								
							switch( strtolower( $datas['paper_size'] ) )
							{
								case 'receipt':
									$params['print'] = 1;
									ob_start();
										do_action( 'wcwh_get_template', 'template/receipt-picking-list.php', $params );
									$content.= ob_get_clean();

									echo $content;
								break;
								case 'default':
								default:
									ob_start();
										do_action( 'wcwh_get_template', 'template/doc-picking-list.php', $params );
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

			$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false );
			$handled = [];
			foreach( $exists as $exist )
			{
				$handled[ $exist['doc_id'] ] = $exist;
			}

			if ( !class_exists( "WCWH_StockMovementWA_Class" ) ) require_once( WCWH_DIR . "/includes/classes/stock-movement-wa.php" );
			$Inst = new WCWH_StockMovementWA_Class();

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

				if( $succ && in_array( $action, [ 'post', 'email' ] ) && !empty( $this->warehouse['so_email'] ) )
				{
					if( method_exists( $this, 'mailing' ) ) $this->mailing( $ref_id, $action, $handled[ $ref_id ] );
				}

				if( $succ && $handled[ $ref_id ] && in_array( $action, [ 'save', 'update', 'update-header', 'delete', 'post', 'unpost' ] ) )
				{
					$doc = $handled[ $ref_id ];
					$filters = [
						'warehouse_id' => $doc['warehouse_id'],
						'doc_id' => $doc['doc_id'],
						'date' => $doc['doc_date'],
					];
					$succ = $Inst->margining_sales_handler( $filters, 'wh_sales_order_invoice' );
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

			$subject = 'Sale Order '.$doc[ $ref_id ]['docno'].$client_segment.' ready for Delivery Instruction ';
			$message = 'Sale Order '.$doc[ $ref_id ]['docno'].$client_segment.' has been posted, and advice to proceed with delivery.';

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

			  		if( $details[$i]['custom_item'] ) 
			  		{
			  			$details[$i]['prdt_name'] = $details[$i]['custom_item'];
			  			if( $item['uom_id'] ) $details[$i]['uom_code'] = $item['uom_id'];
			  		}

			  		$details[$i]['tqty'] = $details[$i]['bqty'];
			  		if( $details[$i]['foc'] > 0 ) $details[$i]['bqty']-= $details[$i]['foc'];
			  		$details[$i]['foc'] = round_to( $details[$i]['foc'], 2 );
				}

				$message.= "<br><br>Item Details:<br>";

				$Inst = new WCWH_Listing();
				ob_start();

				echo $Inst->get_listing( [
						'num' => '',
						'prdt_name' => 'Item',
			        	'uom_code' => 'UOM',
			        	'bqty' => 'Qty',
			        	'foc' => 'FOC',
			        	'tqty' => 'Total Qty',
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
				'id' => 'sale_order_posted_mail',
				'section' => $this->section_id,
				'datas' => $doc[ $ref_id ],
				'ref_id' => $ref_id,
				'subject' => $subject,
				'message' => $message,
			];
			$args['recipient'] = $this->warehouse['so_email'];
			//if( current_user_cans( ['wh_super_admin'] ) ) $args['recipient'] = 'wid001@suburtiasa.com';

			do_action( 'wcwh_set_email', $args );
			do_action( 'wcwh_trigger_email', [] );
		}

	public function automate_sales_handler( $doc_id = 0, $action = '' )
	{
		if( ! $this->setting['wh_good_receive']['use_auto_sales'] ) return true;
		if( ! $doc_id || ! $action ) return false;
		
		$automate_sale = get_document_meta( $doc_id, 'automate_sale', 0, true );
		if( ! $automate_sale ) return true;

		if ( !class_exists( "WCWH_DeliveryOrder_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
		$Inst = new WCWH_DeliveryOrder_Controller();
		$Inst->set_warehouse( $this->warehouse );

		$succ = false;

		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'sale_order' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					if( ! $doc_header['automate_sale'] ) return true;
					
					$header = [
						'warehouse_id' => ( $this->warehouse['code'] )? $this->warehouse['code'] : $doc_header['warehouse_id'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'parent' => $doc_header['doc_id'],
						'client_company_code' => $doc_header['client_company_code'],
						'ref_doc_type' => $doc_header['doc_type'],
						'ref_doc_id' => $doc_header['doc_id'],
						'ref_doc' => $doc_header['docno'],
						'ref_status' => $doc_header['status'],
						'remark' => $doc_header['remark'],
						'gr_invoice' => $doc_header['gr_invoice'],
						'gr_po' => $doc_header['gr_po'],
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

							$uprice = round_to( $row['sprice'], 5 );

							$detail[] = [
								'product_id' => $row['product_id'],
								'bqty' => $row['bqty'],
								'bunit' => $row['bunit'],
								'ref_doc_id' => $row['doc_id'],
								'ref_item_id' => $row['item_id'],
								'stock_item_id' => $row['ref_item_id'],
								'sprice' => $uprice,
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
			case 'unpost':
			case 'incomplete':
				$nxt_header = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'delivery_order' ], [], true, ['usage'=>1] );
				if( $nxt_header )
				{
					$doc = [ 'id'=>$nxt_header['doc_id'] ];

					$operation = true; $error_count = 0;
					do {
						if( $nxt_header['status'] >= 9 ) 
						{
							$action = 'incomplete';
							$nxt_header['status'] = 6;
						}
						else if( $nxt_header['status'] >= 6 && $nxt_header['status'] < 9 ) 
						{
							$action = 'unpost';
							$nxt_header['status'] = 1;
						}
						else if( $nxt_header['status'] >= 1 && $nxt_header['status'] < 6 ) 
						{
							$action = 'delete';
							$nxt_header['status'] = 0;
						}

						$result = $Inst->action_handler( $action, $doc, [] );
		                if( ! $result['succ'] )
		                {
		                	$error_count++;
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                
		                if( $error_count > 0 ) break;

		                if( $nxt_header['status'] <= 0 ) 
		                {
		                	$operation = false;
		                	break;
		                }
					} while( $operation === true );

					if( $error_count <= 0 ) $succ = true;
				}
			break;
		}

		return $succ;
	}

	public function einvoice_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;
		if( !isset( $this->warehouse['einv'] ) || empty( $this->warehouse['einv'] ) || !$this->warehouse['einv'] ) return true;

		$now = current_time( 'mysql' );
		if( (int)time_diff( $this->refs['einv_start'], $now ) < 0 ) return true;

		if( ! apply_filters( 'wcwh_einvoice_necessity', false, $doc_id ) ) return true;

		if ( !class_exists( "WCWH_EInvoice_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/eInvoiceCtrl.php" ); 
		$Inst = new WCWH_EInvoice_Controller();
		$Inst->set_warehouse( $this->warehouse );

		$succ = false;

		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'sale_order' ], [], true, [] );
				//$einv = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'e_invoice' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );
					
					$header = [
						'ref_doc_id' => $doc_header['doc_id'],
						'doc_date' => $doc_header['doc_date'],
						'doc_date' => $doc_header['doc_date'],
					];
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$detail[] = $row;
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
		                	/*$result = $Inst->action_handler( 'post', $doc, $doc );
		                	if( ! $result['succ'] )
			                {
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	$succ = true;
			                }*/
		                }
					}
				}
			break;
			case 'unpost':
				$nxt_header = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'e_invoice' ], [], true, ['usage'=>1] );
				if( $nxt_header && ( $nxt_header['status'] <= 6 && $nxt_header['status'] >= 1 ) )
				{
					$doc = [ 'id'=>$nxt_header['doc_id'] ];

					$operation = true; $error_count = 0;
					do {
						if( $nxt_header['status'] == 6 ) 
						{
							$action = 'unpost';
							$nxt_header['status'] = 1;
						}
						else if( $nxt_header['status'] == 1 && $nxt_header['status'] < 6 ) 
						{
							$action = 'delete';
							$nxt_header['status'] = 0;
						}

						$result = $Inst->action_handler( $action, $doc, [] );
		                if( ! $result['succ'] )
		                {
		                	$error_count++;
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                
		                if( $error_count > 0 ) break;

		                if( $nxt_header['status'] <= 0 ) 
		                {
		                	$operation = false;
		                	break;
		                }
					} while( $operation === true );

					if( $error_count <= 0 ) $succ = true;
				}
				else
				{
					$succ = false;
					$this->Notices->set_notice( 'Document proceed to next step, Unable to revert', 'warning' );
				}
			break;
		}

		return $succ;
	}

		public function einvoice_necessity( $required = false, $doc_id = 0 )
		{
			if( $doc_id )
			{
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'sale_order', 'sap_po'=>'IS_NOT_NULL' ], [], true, [ 'meta'=>['sap_po', 'client_company_code' ] ] );
				if( !empty( $doc_header ) ) $required = true;

				/*if( !empty( $doc_header['client_company_code'] ) )
				{
					$buyer = apply_filters( 'wcwh_get_client', [ 'code'=>$doc_header['client_company_code'] ], [], true );
					if( empty( $buyer['tin'] ) )
					{
						$required = false;
						$this->Notices->set_notice( 'Client Does Not Have IRB Info', 'info' );
					}
				}*/
			}

			return $required;
		}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'] );
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'comp_name', 'remark' ], 'New Sales Order by Document (PR/TR if any)' );
	        echo '<div id="sale_order_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'sale_order_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#sale_order_action"'], 'class'=>['select2','triggerChange'], 
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
				<button id="sale_order_action" class="btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Sales Order"
					data-title="<?php echo $actions['save'] ?> Sales Order" 
					data-action="sale_order_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#sale_order_reference" 
				>
					<?php echo $actions['save'] ?> Sales Order
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
			case 'purchase_request':
				if( ! class_exists( 'WCWH_PurchaseRequest_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" ); 
				$Inst = new WCWH_PurchaseRequest_Controller();
			break;
			case 'good_receive':
				if( ! class_exists( 'WCWH_GoodReceive_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
				$Inst = new WCWH_GoodReceive_Controller();
			break;
			case 'tool_request':
				if( ! class_exists( 'WCWH_ToolRequest_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/toolRequestCtrl.php" ); 
				$Inst = new WCWH_ToolRequest_Controller();
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
					case 'good_issue':
						if( current_user_cans( [ 'access_wh_good_issue' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_GoodIssue_Controller();
							$titles[ $doc_type ] = "Goods Issue";
						}
					break;
					case 'delivery_order':
						if( current_user_cans( [ 'access_wh_delivery_order' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_DeliveryOrder_Controller();
							$titles[ $doc_type ] = "Delivery Order";
						}
					break;
					case 'e_invoice':
						if( current_user_cans( [ 'access_wh_e_invoice' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/eInvoiceCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_EInvoice_Controller();
							$titles[ $doc_type ] = "E Invoice";
						}
					break;
					case 'sale_debit_note':
					case 'sale_credit_note':
						if( current_user_cans( [ 'access_wh_sale_cdnote' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/saleCDNoteCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_SaleCDNote_Controller();
							$titles[ $doc_type ] = "Debit / Credit Note";
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
			'section' => $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
			'rowCusTpl'	=> $this->tplName['cusrow'],
			'feeTpl'	=> $this->tplName['fee'],
			'seller'	=> $this->warehouse['code'],
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( is_array( $id ) && $id )
		{
			$filter = [ 'id'=>$id, 'seller'=>$this->warehouse['code'] ];
			//$items = apply_filters( 'wcwh_get_latest_price', $filter, [], false, [ 'usage'=>1, 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
			$items = apply_filters( 'wcwh_get_latest_price', $filter, [], false, [ 'usage'=>1, 'uom'=>1, 'category'=>1, 'isUnit'=>1, 'inventory'=>$this->warehouse['id'] ] );
			if( $items )
			{
				////---------12/9/22-----------------------------------
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
				////---------12/9/22-----------------------------------
				$details = array();
				foreach( $items as $i => $item )
				{	
					$stk = ( $inventory[ $item['id'] ] )? $inventory[ $item['id'] ]['qty'] : $item['stock_qty'];
		        	$stk-= ( $inventory[ $item['id'] ] )? $inventory[ $item['id'] ]['allocated_qty'] : $item['stock_allocated'];
					$details[$i] = array(
						'id' =>  $item['id'],
						'bqty' => '',
						'product_id' => $item['id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['name'], 'code'=>$item['code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'],
							'unit_price' => $item['unit_price'], 'stocks' => $stk,
						],
					);
				}
				$args['data']['details'] = $details;
			}
		}
		
		if( ! is_array( $id ) && $id )
		{
			$header = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [ 'warehouse'=>1, 'company'=>1 ] );
			if( $header )
			{
				$metas = get_document_meta( $id );
				$header = $this->combine_meta_data( $header, $metas );
				
				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];
				$args['data']['purchase_doc'] = $header['docno'];
				$args['data']['client_company_code'] = $header['client_company_code'];

				if(in_array( $header['doc_type'], [ 'tool_request' ] ))
				{
					$employee = apply_filters( 'wcwh_get_customer', $filters, [], false, ['account'=>1] );
					foreach ($employee as $key => $value) 
					{
						if($value['uid'] == $header['customer_uid'])
						{
							$customer = $value['uid'].", ".$value['code'].", ".$value['name'];
							break;
						}
					}
					$args['data']['remark'] = $customer.( $header['remark']? ", \n".$header['remark']."\n" : "" );
				}
			}

			$filters = [ 'doc_id'=>$id ];
			////---------12/9/22-----------------------------------
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1, 'returnable'=>1 ] );
			////---------12/9/22-----------------------------------
			//$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );

			if( in_array( $header['doc_type'], [ 'purchase_request' ] ) )
			{
				$wh_client = get_warehouse_meta( $header['wh_id'], 'client_company_code' );
				if( empty( $args['data']['client_company_code'] ) ) $args['data']['client_company_code'] = $wh_client;
			}
			
			if( $items )
			{
				////---------12/9/22-----------------------------------
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
				////---------12/9/22-----------------------------------
				$details = array();
				foreach( $items as $i => $item )
				{
					$ffqty = 0;
					if(in_array( $header['doc_type'], [ 'tool_request' ] ))
					{
						$ffqty = get_document_meta( $item['doc_id'], 'fulfill_qty', $item['item_id'], true );
					}
					////---------12/9/22-----------------------------------
					$stk = ( $inventory[ $item['id'] ] )? $inventory[ $item['id'] ]['qty'] : $item['stock_qty'];
		        	$stk-= ( $inventory[ $item['id'] ] )? $inventory[ $item['id'] ]['allocated_qty'] : $item['stock_allocated'];	
					$details[] = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk,
						],
						'bqty' => ( $item['bqty'] - $item['uqty'] - $ffqty ),
						'lqty' => round_to( $item['bqty'] - $item['uqty'] - $ffqty, 2 ),
						'ref_bqty' => $item['bqty'],
						'ref_bal' => ( $item['bqty'] - $item['uqty'] - $ffqty ),
						'ref_doc_id' => $item['doc_id'],
						'ref_item_id' => $item['item_id'],
					);
					////---------12/9/22-----------------------------------
				}
				$args['data']['details'] = $details;
			}
		}

		if( $args['data']['ref_doc_id'] )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/saleOrder-form.php', $args );
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false, $config = [ 'ref'=>1, 'link'=>1 ] )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section' => $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
			'rowCusTpl'	=> $this->tplName['cusrow'],
			'feeTpl'	=> $this->tplName['fee'],
			'get_content' => $getContent,
			'seller'	=> $this->warehouse['code'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true );
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

				/*
				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1 ] );
				*/
				////---------12/9/22-----------------------------------
				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1, 'returnable'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );
				///-------------------------12/9/22---------------------

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				if( $datas['status'] >= 6 && ! current_user_cans( [ 'wh_admin_support' ] ) )
					$args['action'] = 'update-header';

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_sales_order'] ) )
				{
					$hides = [ 'sprice', 'total_amount' ];
				}
		        	
		        if( $datas['details'] )
		        {
		        	$margining_id = 'wh_sales_order_invoice';
		        	if( $isView && $this->setting['general']['use_margining'] )
					{	
						$sap_po = ( $datas['sap_po'] )? 1 : -1;
						$margining = apply_filters( 'wcwh_get_margining', $datas['warehouse_id'], $margining_id, $datas['client_company_code'], $datas['doc_date'], 'any', $sap_po );
						//pd($margining,1);
					}

					////---------12/9/22-----------------------------------
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
		        	////---------12/9/22-----------------------------------
					
		        	$total_amount = 0; $discounted_amount = 0; $final_amount = 0; $inv_amt = 0; $inv_dis_amt = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		////---------12/9/22-----------------------------------
		        		$stk = ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['qty'] : $item['stock_qty'];
		        		$stk-= ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['allocated_qty'] : $item['stock_allocated'];
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk,
		        		];
		        		////---------12/9/22-----------------------------------
		        		/*
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];
		        		*/

		        		$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;
		        		
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		if( $datas['details'][$i]['custom_item'] ) 
		        		{
		        			$datas['details'][$i]['prdt_name'] = $datas['details'][$i]['custom_item'];
		        			if( $item['uom_id'] ) $datas['details'][$i]['uom_code'] = $item['uom_id'];
		        		}

		        		if( $datas['details'][$i]['foc'] > 0 ) $datas['details'][$i]['bqty']-= $datas['details'][$i]['foc'];
		        		$datas['details'][$i]['foc'] = round_to( $datas['details'][$i]['foc'], 2 );

		        		$datas['details'][$i]['bqty'] = round_to( $datas['details'][$i]['bqty'], 2 );
		        		$datas['details'][$i]['bunit'] = round_to( $datas['details'][$i]['sunit'], 2 );

		        		$datas['details'][$i]['uqty'] = round_to( $item['uqty'], 2 );
		        		$datas['details'][$i]['lqty'] = round_to( $item['bqty'] - $item['uqty'], 2 );

		        		$datas['details'][$i]['cprice'] = ( $datas['details'][$i]['unit_price'] )? $datas['details'][$i]['unit_price'] : $datas['details'][$i]['def_sprice'];
		        		$datas['details'][$i]['cprice'] = ( $datas['details'][$i]['cprice'] )? $datas['details'][$i]['cprice'] : $datas['details'][$i]['sprice'];
		        		$datas['details'][$i]['cprice'] = round_to( $datas['details'][$i]['cprice'], 2, true );

		        		$datas['details'][$i]['sprice'] = round_to( $datas['details'][$i]['sprice'], 2, true );
		        		$datas['details'][$i]['def_sprice'] = round_to( ( $datas['details'][$i]['def_sprice'] )? $datas['details'][$i]['def_sprice'] : $datas['details'][$i]['sprice'], 2, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['bqty'] * $datas['details'][$i]['def_sprice'], 2, true );

		        		$disc_amount = $datas['details'][$i]['disc'] = $datas['details'][$i]['discount'];
		        		if( strstr( $datas['details'][$i]['discount'], '%' ) )
		        		{
		        			$disc_amount = round_to( wh_apply_discount( $datas['details'][$i]['total_amount'], $datas['details'][$i]['discount'] ), 2, true );
		        			$datas['details'][$i]['disc'] = $disc_amount." ({$datas['details'][$i]['discount']})";
		        		}
		        		$datas['details'][$i]['discount_amt'] = round_to( $datas['details'][$i]['total_amount'] - $disc_amount, 2, true );

		        		$total_amount+= $datas['details'][$i]['total_amount'];
		        		$discounted_amount+= $datas['details'][$i]['discount_amt'];
		        		$def_sprice = $datas['details'][$i]['def_sprice'];

		        		//margining recalc
						if( $isView && !empty( $margining ) && ( $margining['margin'] > 0 || $margining['margin'] < 0 ) )
						{
							$def_sprice = $datas['details'][$i]['def_sprice'];
							$def_sprice = round( $def_sprice * ( 1+( $margining['margin']/100 ) ), 2 );
							
							$rn = ( $margining['round_nearest'] != 0 )? abs( $margining['round_nearest'] ) : 0.01;
							switch( $margining['round_type'] )
							{	
								case 'ROUND':
									$def_sprice = round( $def_sprice / $rn ) * $rn;
								break;
								case 'CEIL':
									$def_sprice = ceil( $def_sprice / $rn ) * $rn;
								break;
								case 'FLOOR':
									$def_sprice = floor( $def_sprice / $rn ) * $rn;
								break;
								default:
									$def_sprice = $def_sprice;
								break;
							}

							//recalc
							$row_amt = round_to( $datas['details'][$i]['bqty'] * $def_sprice, 2 );
							$disc_amt = $datas['details'][$i]['discount'];
							if( strstr( $datas['details'][$i]['discount'], '%' ) )
							{
								$disc_amt = wh_apply_discount( $row_amt, $datas['details'][$i]['discount'] );
								$datas['details'][$i]['disc'] = "<del>{$datas['details'][$i]['disc']}</del><br>".$disc_amt." ({$datas['details'][$i]['discount']})";
							}
							else
								$datas['details'][$i]['disc'] = "<del>{$datas['details'][$i]['disc']}</del><br>".$disc_amt;

							$datas['details'][$i]['def_sprice'] = "<del>{$datas['details'][$i]['def_sprice']}</del><br>".round_to( $def_sprice, 2, 1 );
							$datas['details'][$i]['total_amount'] = "<del>{$datas['details'][$i]['total_amount']}</del><br>".round_to( $row_amt, 2, 1 );
							$datas['details'][$i]['discount_amt'] = "<del>{$datas['details'][$i]['discount_amt']}</del><br>".round_to( $row_amt - $disc_amt, 2, 1 );

							$inv_amt+= round_to( $row_amt, 2, true );
		        			$inv_dis_amt+= round_to( $row_amt - $disc_amt, 2, true );
						}

		        		if( $datas['status'] >= 9 )
		        		{
		        			$datas['details'][$i]['final_amount'] = round_to( $item['uqty'] * $def_sprice, 2, true );

		        			$datas['details'][$i]['final_disc'] = $datas['details'][$i]['disc'];
		        			if( strstr( $datas['details'][$i]['discount'], '%' ) )
			        		{
			        			$datas['details'][$i]['final_disc'] = round_to( wh_apply_discount( $datas['details'][$i]['final_amount'], $datas['details'][$i]['discount'] ), 2 )." ({$datas['details'][$i]['discount']})";
			        		}
			        		$datas['details'][$i]['final_amount'] = round_to( $datas['details'][$i]['final_amount'] - $datas['details'][$i]['final_disc'], 2, true );
		        			
		        			$final_amount+= $datas['details'][$i]['final_amount'];
		        		}
		        	}

		        	if( $datas['fee'] )
				    {
				    	$datas['fee'] = is_array( $datas['fee'] )? $datas['fee'] : [ 0=>$datas['fee'] ];
				    	$fee = [];
				    	foreach( $datas['fee'] as $i => $row )
				    	{
				    		$datas['fees'][] = $row = maybe_unserialize( $row );
				    	}
				    }

		        	if( $isView && !$hides )
		        	{
		        		$sub = [];
			        	$sub['prdt_name'] = 'SUBTOTAL:';
			        	$sub['total_amount'] = round_to( $total_amount, 2, true );
			        	$sub['discount_amt'] = round_to( $discounted_amount, 2, true );
			        	$sub['final_amount'] = round_to( $final_amount, 2, true );

			        	if( $isView && !empty( $margining ) && ( $margining['margin'] > 0 || $margining['margin'] < 0 ) )
			        	{
			        		$sub['total_amount'] = "<del>{$sub['total_amount']}</del><br>".round_to( $inv_amt, 2, 1 );
			        		$sub['discount_amt'] = "<del>{$sub['discount_amt']}</del><br>".round_to( $inv_dis_amt, 2, 1 );
			        	}
			        	$datas['details'][] = $sub;

			        	$disc = [];
			        	$disc['final_amount'] = $disc['discount_amt'] = 0;
			        	if( $datas['discount'] )
		        		{
		        			$disc['prdt_name'] = 'DISCOUNT:';
		        			$disc['disc'] = $datas['discount'];
				        	$disc['discount_amt'] = round_to( wh_apply_discount( $discounted_amount, $datas['discount'] ), 2, true );
				        	$disc['final_amount'] = round_to( wh_apply_discount( $final_amount, $datas['discount'] ), 2, true );
				        	$datas['details'][] = $disc;
		        		}

		        		if( $datas['fees'] )
					    {
					    	$fee = []; $fee_amt = 0;
					    	foreach( $datas['fees'] as $i => $row )
					    	{
					    		$fee['prdt_name'] = $row['fee_name'].':';
						    	$fee['discount_amt'] = round_to( $row['fee'], 2, true );
						    	$datas['details'][] = $fee;

						    	$fee_amt+= $row['fee'];
					    	}
					    }

		        		$final = [];
			        	$final['prdt_name'] = '<strong>TOTAL:</strong>';
		        		$final['discount_amt'] = '<strong>'.round_to( $discounted_amount - $disc['discount_amt'] + $fee_amt, 2, true ).'</strong>';
		        		if( $isView && !empty( $margining ) && ( $margining['margin'] > 0 || $margining['margin'] < 0 ) )
			        	{
			        		$def_amt = round_to( $discounted_amount - $disc['discount_amt'] + $fee_amt, 2, true );
			        		$final['discount_amt'] = "<del>{$def_amt}</del><br>";
			        		$final['discount_amt'].= '<strong>'.round_to( $inv_dis_amt - $disc['discount_amt'] + $fee_amt, 2, true ).'</strong>';
			        	}

		        		if( $final_amount ) 
		        			$final['final_amount'] = '<strong>'.round_to( $final_amount - $disc['final_amount'] + $fee_amt, 2, true ).'</strong>';

			        	$datas['details'][] = $final;
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );

				$cols = [
		        	'num' => '',
		        	'prdt_name' => 'Item',
		        	'uom_code' => 'UOM',
		        	'bqty' => 'Qty',
		        	'bunit' => 'Metric (kg/l)',
		        	'foc' => 'FOC',
		        	'def_sprice' => 'Price',
		        	'total_amount' => 'Amt',
		        	'disc' => 'Discount',
		        	'discount_amt' => 'Total',
		        	//'sprice' => 'End Price',
		        	'lqty' => 'Leftover',
		        ];
		        if( $datas['status'] >= 9 )
		        {
		        	$cols['uqty'] = 'Final Qty';
		        	$cols['final_amount'] = 'Final Amt';
		        }

		        $args['render'] = $Inst->get_listing( $cols, 
		        	$datas['details'], 
		        	[], 
		        	$hides, 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/saleOrder-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/saleOrder-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}

	public function view_row()
	{
		$args = [
			'setting'	=> $this->setting,
			'section' => $this->section_id,
		];
		do_action( 'wcwh_templating', 'segment/saleOrder-row.php', $this->tplName['row'], $args );
	}

	public function view_custom_row()
	{
		$args = [
			'setting'	=> $this->setting,
			'section' => $this->section_id,
		];
		do_action( 'wcwh_templating', 'segment/saleOrder-customRow.php', $this->tplName['cusrow'], $args );
	}

	public function fee_row()
	{
		$args = [
			'setting'	=> $this->setting,
			'section' => $this->section_id,
		];
		do_action( 'wcwh_templating', 'segment/saleOrder-FeeRow.php', $this->tplName['fee'], $args );
	}

	public function pl_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['pl'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
		);

		do_action( 'wcwh_templating', 'form/saleOrder-pl-form.php', $this->tplName['pl'], $args );
	}

	public function inv_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['inv'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
		);

		do_action( 'wcwh_templating', 'form/saleOrder-inv-form.php', $this->tplName['inv'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/saleOrderListing.php" ); 
			$Inst = new WCWH_SaleOrder_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#pl' => [ 'width' => '60px' ],
				'#inv' => [ 'width' => '120px' ],
			];

			$count = $this->Logic->count_statuses( $this->warehouse['code'] );
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

			$metas = [ 'remark', 'client_company_code', 'ref_doc_id', 'ref_doc', 'ref_doc_type', 'purchase_doc', 'direct_issue', 'sap_po' ];

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