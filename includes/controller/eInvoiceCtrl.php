<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_EInvoice_Class" ) ) include_once( WCWH_DIR . "/includes/classes/e-invoice.php" ); 
if ( !class_exists( "WCWH_EInvoice_Convert" ) ) include_once( WCWH_DIR . "/includes/classes/einvoice-convert.php" ); 

if ( !class_exists( "WCWH_EInvoice_Controller" ) ) 
{

class WCWH_EInvoice_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_e_invoice";

	public $Notices;
	public $className = "EInvoice_Controller";

	public $Logic;
	public $Einv;

	public $tplName = array(
		'new' => 'newINV',
		'row' => 'rowINV',
		'inv' => 'printINV',
	);

	public $useFlag = false;
	public $directPost = true;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 6,9 ];

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
		$this->Logic = new WCWH_EInvoice_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->processing_stat = $this->processing_stat;

		$this->Einv = new WCWH_EInvoice_Convert();
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
				case 'save':
					if( ! $datas['header']['ref_doc_id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( $succ )
					{
						$doc_id = $datas['header']['ref_doc_id'];
						$inv = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'e_invoice' ], [], true, [ 'usage'=>1 ] );
						if( $inv ) 
						{
							$succ = false;
							$this->Notices->set_notice( 'Invoice found '.$inv['docno'], 'info' );
						}
					}
				break;
				case 'update':
					if( ! $datas['header']['ref_doc_id'] || $datas['header']['doc_id'] )
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
				case 'cancel':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( empty( $datas['remark'] ) )
					{
						$succ = false;
						$this->Notices->set_notice( 'Remark required for Cancel Document', 'warning' );
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
					$detail = [];
					
					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;
					if( $header['ref_doc_id'] )
					{	
						$ref_header = $this->Logic->get_header( [ 'doc_id'=>$header['ref_doc_id'], 'doc_type'=>'none' ], [], true, [ 'company'=>1 ] );
						if( $ref_header )
						{
							$metas = get_document_meta( $header['ref_doc_id'] );
							$ref_header = $this->combine_meta_data( $ref_header, $metas );

							$header['warehouse_id'] = $ref_header['warehouse_id'];
							$header['client_company_code'] = $ref_header['client_company_code'];

							$header['ref_warehouse'] = $ref_header['warehouse_id'];
							$header['ref_doc_type'] = $ref_header['doc_type'];
							$header['ref_doc'] = $ref_header['docno'];
							$header['parent'] = $ref_header['doc_id'];

							$header['payment_term'] = $ref_header['payment_term'];

							$header['docno'] = $ref_header['docno'].'-I01';

							if( ! $header['doc_id'] )
							{
								$header['ref_status'] = $ref_header['status'];
							}
							if( $ref_header['sap_po'] ) $header['sap_po'] = $ref_header['sap_po'];
							if( $ref_header['discount'] ) $header['discount'] = $ref_header['discount'];

							foreach( $ref_header as $key => $val )
							{
								if( strpos( $key, 'diff' ) !== false ) 
								{
								   $header[ $key ] = $val;
								}
							}
						}

						$details = $this->Logic->get_detail( [ 'doc_id'=>$header['ref_doc_id'] ], [], false, [ 'uom'=>1, 'category'=>1, 'usage'=>1, 'ref'=>1 ] );
						if( $details )
						{
							$margining_id = 'wh_sales_order_invoice';
							$margining = [];
							if( $this->setting['general']['use_margining'] )
							{	
								$sap_po = ( $header['sap_po'] )? 1 : -1;
								$margining = apply_filters( 'wcwh_get_margining', $ref_header['warehouse_id'], $margining_id, $ref_header['client_company_code'], $header['doc_date'], 'any', $sap_po );
								//pd($margining,1);
							}

							foreach( $details as $i => $item )
							{
								$detail_metas = get_document_meta( $header['ref_doc_id'], '', $item['item_id'] );
		        				$item = $this->combine_meta_data( $item, $detail_metas );

								$row = [];
								$row['product_id'] = $item['product_id'];
								$row['bqty'] = $item['bqty'];
								$row['bunit'] = $item['bunit'];
								if( !empty( $item['foc'] ) ) $row['foc'] = $item['foc'];

								$row['ref_doc_id'] = $item['doc_id'];
								$row['ref_item_id'] = $item['item_id'];

								$row['sprice'] = $item['sprice'];
								$row['def_sprice'] = $item['def_sprice'];
								if( !empty( $item['discount'] ) ) $row['discount'] = $item['discount'];

								if( !empty( $item['custom_item'] ) ) $row['custom_item'] = $item['custom_item'];

							//margining recalc
						    if( !empty( $margining ) && ( $margining['margin'] > 0 || $margining['margin'] < 0 ) )
						    {
						    	$row['def_sprice'] = round( $row['def_sprice'] * ( 1+( $margining['margin']/100 ) ), 5 );
						    	
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

						    	$row['sprice'] = $row['def_sprice'];
						    }

						    	$row['line_total'] = $row['line_subtotal'] = round( ( $row['bqty'] - $item['foc'] ) * $row['def_sprice'], 2 );

								if( !empty( $row['discount'] ) )
								{
									$disc_amt = wh_apply_discount( $row['line_total'], $row['discount'] );
									$row['line_total'] = round( $row['line_total'] - $disc_amt, 2 );
									$row['sprice'] = round_to( $row['line_total'] / $row['bqty'], 5 );

									$row['line_discount'] = $disc_amt;
									$header['total_discount']+= $disc_amt;
								}

								$header['subtotal']+= $row['line_subtotal'];
								$header['discounted_subtotal']+= $row['line_total'];
								$header['total']+= $row['line_total'];

								$detail[] = $row;
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
					}
					
					if( !empty($detail) && $succ )
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
					else
					{
						$succ = false;
						$this->Notices->set_notice( "There is empty detail.", 'warning' );
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
								$wh_code = $this->refs['einv_id'];
								$api_url = $this->refs['einv_url'];
								$remote = apply_filters( 'wcwh_api_request', 'unpost_myinvoice', $id, $wh_code, $this->section_id, [], $api_url );
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
				case "cancel":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( !$datas['remark'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'Need Remark', 'error' );
					}
					else
					{
						if($ids)
						{
							foreach ($ids as $id)
							{
								$header = [];
								$header['doc_id'] = $id;

								$doc = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [] );
								if( empty( $doc ) ) continue;

								$succ = true;
								$proceed = false;
								
								$wh_code = $this->refs['einv_id'];
								$api_url = $this->refs['einv_url'];
								$remote = apply_filters( 'wcwh_api_request', 'cancel_myinvoice', $id, $wh_code, $this->section_id, [$datas], $api_url );
							
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

								if( $proceed && $succ )
								{
									$operation = true; $error_count = 0;
									do {
										if( $doc['status'] == 9 ) 
										{
											$act = 'incomplete';
											$doc['status'] = 6;
										}
										else if( $doc['status'] == 6 ) 
										{
											$act = 'unpost';
											$doc['status'] = 1;
										}

										$header = [];
										$header['doc_id'] = $id;
										$result = $this->Logic->child_action_handle( $act, $header );
							            if( ! $result['succ'] )
							            {
							            	$error_count++;
							                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
							            }
							            
							            if( $error_count > 0 ) break;

							            if( $doc['status'] < 3 ) 
							            {
							            	$operation = false;
							            	break;
							            }
									} while( $operation === true );

									if( $error_count > 0 ) $succ = false;
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
					}
				break;
				case "print":
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
						case 'invoice':
							$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
							if( $datas['is_email'] ) $params['is_email'] = $datas['is_email'];
							
							$doc = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [] );
							if( $doc )
							{	
								$doc['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
								//metas
								$metas = get_document_meta( $id );
								$doc = $this->combine_meta_data( $doc, $metas );

								if( $doc['payment_term'] )
								$pmt = apply_filters( 'wcwh_get_payment_term', [ 'code'=>$doc['payment_term'] ], [], true );

								$dos = $this->Logic->get_header( [ 'doc_type'=>'delivery_order', 'base_doc_id'=>$doc['ref_doc_id'] ], [], false, [ 'posting'=>1, 'meta'=>['base_doc_id'] ] );
								$inv_dos = [];
								if( $dos )
								{
									foreach( $dos as $do )
									{
										$inv_dos[] = $do['docno'];
									}
								}

								$date_format = get_option( 'date_format' );
								$params['heading']['docno'] = $doc['docno'];
								$params['heading']['discount'] = $doc['discount'];
								$params['heading']['total'] = $doc['total'];
								$params['heading']['infos'] = [
									'Invoice No.' => $doc['docno'],
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
									'UUID' => $doc['uuid'],
									'Contract No.' => $doc['ref_doc'],
									'Payment Term' => ( $pmt )? $pmt['name'] : '',
									'DO No.' => ( $inv_dos )? implode( ', ', $inv_dos ) : '',
								];

								$params['heading']['irb_qr'] = $this->refs['irb_url'].$doc['uuid'].'/share/'.$doc['longid'];

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

							    if( $doc['details'] )
							    {
							    	$detail = [];
							        foreach( $doc['details'] as $i => $item )
							        {
							        	$detail_metas = get_document_meta( $id, '', $item['item_id'] );
		        						$item = $this->combine_meta_data( $item, $detail_metas );

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

										if( $params['is_email'] )
										{
											$pdftemp = sys_get_temp_dir();
											$now = current_time( 'Y-m-d' );

											$args['filename'] = $args['filename'].'-'.$now;
											$args['save'] = 1;
											$args['path'] = $pdftemp .'/'. $args['filename'].'.pdf';

											$succ = apply_filters( 'dompdf_generator', $content, $paper, array(), $args );

											if( $succ ) return $args['path'];
										}
										else
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

				if( ( $handled[ $ref_id ]['status'] >= 6 && $action == 'post' ) || ( $handled[ $ref_id ]['status'] == 1 && $action == 'unpost' ) )
				{
					$einv = $this->refs['einv_id'];
					$remote_url = $this->refs['einv_url'];
					$succ = apply_filters( 'wcwh_einv_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $einv, $remote_url, [], true );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
					}
				}
			}
		}

		return $succ;
	}

	public function myinvoice_handler( $action = '', $response = [] )
	{
		if( empty( $action ) || empty( $response ) || empty( $response['eirno'] ) ) return false;
		if( ! in_array( $action, [ 'myinv_api_transaction', 'myinv_lhdn_search_document', 'myinv_lhdn_document' ] ) ) return false;

		$this->Notices->reset_operation_notice();
		$succ = true;

		$doc = $this->Logic->get_header( [ 'doc_id'=>$response['eirno'], 'doc_type'=>'none' ], [], true, [ 'company'=>1, 'usage'=>1 ] );
		if( empty( $doc ) ) return false;

		$datas = []; $irb_status = '';

		$datas['header']['warehouse_id'] = $doc['warehouse_id'];
		$datas['header']['docno'] = $doc['docno'];
		$datas['header']['doc_type'] = $doc['doc_type'];
		$datas['header']['doc_date'] = $doc['doc_date'];
		$datas['header']['parent'] = $doc['parent'];
		
		$datas['header']['doc_id'] = $response['eirno'];
		$datas['header']['uuid'] = $response['uuid'];
		$datas['header']['ref_uuid'] = $response['ref_uuid'];
		$datas['header']['longid'] = $response['longid'];
		$datas['header']['submission_id'] = $response['submission_id'];
		$datas['header']['submission_date'] = $response['submission_date'];
		$datas['header']['validate_date'] = $response['validate_date'];
		$datas['header']['irb_status'] = $irb_status = strtoupper( $response['irb_status'] );
		if( !empty( $response['submitter_email'] ) ) $datas['header']['submitter_email'] = $response['submitter_email'];
		if( !empty( $response['submission_error'] ) ) $datas['header']['submission_error'] = $response['submission_error'];
		
		$result = $this->action_handler( 'update-header', $datas, [], false );
		if( ! $result['succ'] )
		{
			$succ = false;
		    $this->Notices->set_notices( $this->Notices->get_operation_notice() );
		}

		if( $succ )
		{	
			if( $doc['doc_type'] == 'e_invoice' )
			{
				if( $irb_status == 'V' && $doc['status'] != 9 )
				{
					$datas = [];
					$datas['doc_id'] = $response['eirno'];
					$action = 'complete';
					$result = $this->Logic->child_action_handle( $action, $datas );
					if( ! $result['succ'] )
				    {
				    	$succ = false;
				        $this->Notices->set_notices( $this->Notices->get_operation_notice() );
				    }
				}
				else if( ( $irb_status == 'C' || $irb_status == 'I' ) && $doc['status'] != 1 )
				{
					$datas = [];
					$datas['doc_id'] = $response['eirno'];
					
					$operation = true; $error_count = 0;
					do {
						if( $doc['status'] == 9 ) 
						{
							$act = 'incomplete';
							$doc['status'] = 6;
						}
						else if( $doc['status'] == 6 ) 
						{
							$act = 'unpost';
							$doc['status'] = 1;
						}
						else if( $doc['status'] == 3 ) 
						{
							$act = 'unpost';
							$doc['status'] = 1;
						}

						$result = $this->Logic->child_action_handle( $act, $datas );
			            if( ! $result['succ'] )
			            {
			            	$error_count++;
			                $this->Notices->set_notices( $this->Notices->get_operation_notice() );
			            }
			            
			            if( $error_count > 0 ) break;

			            if( $doc['status'] < 3 ) 
			            {
			            	$operation = false;
			            	break;
			            }
					} while( $operation === true );

					if( $error_count > 0 ) $succ = false;
				}
			}
			else
			{
				if ( !class_exists( "WCWH_SaleCDNote_Class" ) ) include_once( WCWH_DIR . "/includes/classes/sale-cdnote.php" ); 
				$Inst = new WCWH_SaleCDNote_Class();

				if( $irb_status == 'V' && $doc['status'] < 6 )
				{
					$datas = [];
					$datas['doc_id'] = $response['eirno'];
					$action = 'post';
					
					$Inst->_stat_to_post = 3;
					$result = $Inst->child_action_handle( $action, $datas );
					if( ! $result['succ'] )
				    {
				    	$succ = false;
				        $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
				    }
				}
				else if( ( $irb_status == 'C' || $irb_status == 'I' ) && $doc['status'] != 1 )
				{
					$datas = [];
					$datas['doc_id'] = $response['eirno'];
					
					$operation = true; $error_count = 0;
					do {
						if( $doc['status'] == 9 ) 
						{
							$act = 'incomplete';
							$doc['status'] = 6;
						}
						else if( $doc['status'] == 6 ) 
						{
							$act = 'unpost';
							$doc['status'] = 1;
						}
						else if( $doc['status'] == 3 ) 
						{
							$act = 'refute';
							$doc['status'] = 1;
						}

						$result = $Inst->child_action_handle( $act, $datas );
			            if( ! $result['succ'] )
			            {
			            	$error_count++;
			                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			            }
			            
			            if( $error_count > 0 ) break;

			            if( $doc['status'] < 3 ) 
			            {
			            	$operation = false;
			            	break;
			            }
					} while( $operation === true );

					if( $error_count > 0 ) $succ = false;
				}
			}

			if( $succ && in_array( $irb_status, [ 'V', 'I' ] ) )
			{
				if( method_exists( $this, 'mailing' ) ) $this->mailing( $doc['doc_id'], $irb_status, $doc );
			}
		}

		return $succ;
	}

		public function mailing( $ref_id = 0, $action = '', $doc = [] )
		{
			if( ! $ref_id || ! $action ) return;

			if( empty( $doc ) )
			{
				$doc = $this->Logic->get_header( [ 'doc_id'=>$ref_id, 'doc_type'=>'none' ], [], true, [ 'company'=>1, 'usage'=>1 ] );
			}
			if( ! $doc ) return;

			set_time_limit(180); 

			$metas = $this->Logic->get_document_meta( $ref_id );
			$doc = $this->combine_meta_data( $doc, $metas );

			$receivers = [];
			$creator_id = $doc['created_by'];
			$creator = get_user_by( 'id', $creator_id );
			if( !empty( $creator ) ) $receivers[] = $creator->user_email;
			if( !empty( $doc['submitter_email'] ) ) $receivers[] = $doc['submitter_email'];

			$subject = "Minimart - MyInvoice Notification for {$doc['docno']} is ";
			$message = '';

			$attachment = ''; $file_path = "";
			switch( $action )
			{
				case 'valid':
				case 'V':
					$subject.= "VALID";

					if( $doc['uuid'] && $doc['longid'] )
					{
	                    $doc['irb_url'] = $this->refs['irb_url'].$doc['uuid'].'/share/'.$doc['longid'];
	                    $doc['qrcode'] = apply_filters( 'qrcode_img_data', $doc['irb_url'], "M", 4.4, 2 );
					}
					$message.= apply_filters( 'wcwh_get_template_content', 'email/einv-valid.php', [ 'doc'=>$doc ] );

					/*$file_url = $this->refs['einv_url']."?action=myinv_einvoice_print&docno=".$doc['docno']."&uuid=".$doc['uuid'].'&longid='.$doc['longid'];
					$pdf = file_get_contents( $file_url );
					if( !empty( $pdf ) )
					{
						$temp_dir = sys_get_temp_dir();
						$file_path = $temp_dir."/{$doc['docno']}_{$doc['uuid']}.pdf";
						file_put_contents( $file_path, $pdf );
						
						$attachment = $file_path;
					}*/
					
					$attachment = $this->inv_email_attachment( '', $doc, $ref_id );
				break;
				case 'invalid':
				case 'I':
					$subject.= "INVALID";
					
					if( !empty( $doc['submission_error'] ) ) $doc['submission_error'] = $error['submission_error'];

					$message.= apply_filters( 'wcwh_get_template_content', 'email/einv-invalid.php', [ 'doc'=>$doc ] );
				break;
			}

			$args = [
				'id' => 'lhdn_einvoice_mail',
				'section' => $this->section_id,
				'datas' => $doc,
				'ref_id' => $ref_id,
				'subject' => $subject,
				'message' => $message,
			];
			if( !empty( $attachment ) ) $args['attachment'] = realpath( $attachment );

			$args['recipient'] = implode( ",", $receivers );
			//if( current_user_cans( [ 'manage_options' ] ) ) 
				//$args['recipient'] = 'wid001@suburtiasa.com';

			if( !empty( $args['recipient'] ) )
			{
				do_action( 'wcwh_set_email', $args );
				do_action( 'wcwh_trigger_email', [] );

				if( !empty( $file_path ) ) unlink( $file_path );
			}
		}
		
		public function inv_email_attachment( $attachment = '', $datas = [], $ref_id = 0 )
		{
			if( ! $ref_id && ! $datas['doc_id'] ) return '';

			$ref_id = ( $ref_id )? $ref_id : $datas['doc_id'];

			$args = [
				'type' => 'invoice',
				'id' => [ $ref_id ],
				'is_email' => 1,
			];
			$attachment = $this->action_handler( 'print', $args );

			return !empty( $attachment )? $attachment : '';
		}
		
	public function myinvoice_synced( $datas = [], $response = [] )
	{
		if( empty( $datas ) ) return;

		if( empty( $this->setting['myinvoice']['recipient'] ) ) return;

		$doc = $datas['details']['header'];
		$subject = "Minimart - Document {$doc['docno']} are ready on MyInvoice for Submission ";
		$message = "Document {$doc['docno']} are ready on MyInvoice for Submission.";
		$message.= "<br><br>For Quick Access: ";

		$link = $this->refs['einv_url']."wp-admin/admin.php?page=myinv_mnmart";
		$message.= "<a href='{$link}'>View On MyInvoice</a>";

		$args = [
			'id' => 'myinvoice_synced_mail',
			'section' => $this->section_id,
			'datas' => $doc,
			'ref_id' => $datas['ref_id'],
			'subject' => $subject,
			'message' => $message,
		];

		$args['recipient'] = $this->setting['myinvoice']['recipient'];
		//if( current_user_cans( [ 'manage_options' ] ) ) 
			//$args['recipient'] = 'wid001@suburtiasa.com';

		if( !empty( $args['recipient'] ) )
		{
			do_action( 'wcwh_set_email', $args );
			do_action( 'wcwh_trigger_email', [] );
		}
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		echo '<div id="e_invoice_reference_content" class="col-md-7">';	
		wcwh_form_field( '', 
			[ 'id'=>'e_invoice_reference', 'class'=>['inputSearch'], 'type'=>'text', 'label'=>'', 'required'=>false, 
				'attrs'=>['data-change="#e_invoice_action"'], 'placeholder'=>'Search By SO No. (Better Input Complete SO No.)' 
			], 
			''
		);
	    echo '</div>';
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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
				{
				?>
					<button id="e_invoice_action" class="btn btn-sm btn-primary linkAction" title="Create Invoice"
						data-title="Create Invoice" 
						data-action="e_invoice_reference" data-service="<?php echo $this->section_id; ?>_action" 
						data-modal="wcwhModalForm" data-actions="close|submit" 
						data-source="#e_invoice_reference" 
					>
						Create Invoice
						<i class="fa fa-plus-circle" aria-hidden="true"></i>
					</button>
				<?php
				}
			break;
		}
	}

	public function view_reference_doc( $doc_id = 0, $title = '', $doc_type = 'sale_order' )
	{
		if( ! $doc_id || ! $doc_type ) return;

		switch( $doc_type )
		{
			case 'sale_order':
				if( ! class_exists( 'WCWH_SaleOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
				$Inst = new WCWH_SaleOrder_Controller();
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
		$succ = true;

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
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}
		
		if( ! is_array( $id ) && $id )
		{
			$header = $this->Logic->get_header( [ 's'=>$id, 'doc_type'=>'sale_order' ], [], true, [ 'company'=>1, 'posting'=>1 ] );
			
			if( $header )
			{
				$inv = $this->Logic->get_header( [ 'parent'=>$header['doc_id'], 'doc_type'=>'e_invoice' ], [], true, [ 'usage'=>1 ] );
				if( $inv ) 
				{
					$this->Notices->set_notice( 'Invoice found '.$inv['docno'], 'info' );
					return false;
				}

				$metas = get_document_meta( $header['doc_id'] );
				$header = $this->combine_meta_data( $header, $metas );
				
				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];
				$args['data']['client_company_code'] = $header['client_company_code'];
				$args['data']['sap_po'] = $header['sap_po'];
				
				$filters = [ 'doc_id'=>$header['doc_id'] ];
				$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );
			}
			else
			{
				$succ = false;
				$this->Notices->set_notice( 'SO No. not found', 'error' );
				return $succ;
			}
			
			if( $items )
			{
				$details = array();
				$total_amount = 0; $discounted_amount = 0; $final_amount = 0;
		        foreach( $items as $i => $item )
		        {
		        	$row['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        	$row['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        	
		        	$row['line_item'] = [ 
		        		'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
						'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk,
		        	];

		        	$row['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;
		        	
		        	$detail_metas = $this->Logic->get_document_meta( $header['doc_id'], '', $item['item_id'] );
		        	$item = $this->combine_meta_data( $item, $detail_metas );

		        	if( $item['custom_item'] ) 
		        	{
		        		$row['prdt_name'] = $item['custom_item'];
		        		if( $item['uom_id'] ) $row['uom_code'] = $item['uom_id'];
		        	}
		        	$row['uom_code'] = $item['uom_code'];

		        	if( $item['foc'] > 0 ) $row['bqty']-= $item['foc'];
		        	$row['foc'] = round_to( $item['foc'], 2 );

		        	$row['bqty'] = round_to( $item['bqty'], 2 );
		        	$row['bunit'] = round_to( $item['sunit'], 2 );

		        	$row['sprice'] = round_to( $item['sprice'], 2, true );
		        	$row['def_sprice'] = round_to( ( $item['def_sprice'] )? $item['def_sprice'] : $item['sprice'], 2, true );
		        	$row['total_amount'] = round_to( $item['bqty'] * $item['def_sprice'], 2, true );

		        	$row['disc'] = $item['discount'];
		        	if( strstr( $item['discount'], '%' ) )
		        	{
		        		$row['disc'] = round_to( wh_apply_discount( $row['total_amount'], $item['discount'] ), 2, true )." ({$item['discount']})";
		        	}
		        	$row['discount_amt'] = round_to( $row['total_amount'] - $row['disc'], 2, true );
		        	
		        	$total_amount+= $row['total_amount'];
		        	$discounted_amount+= $row['discount_amt'];

		        	$details[] = $row;
		        }
		        $args['data']['details'] = $details;

		        if( !$hides )
		        {
		        	$sub = [];
			     	$sub['prdt_name'] = 'SUBTOTAL:';
			     	$sub['total_amount'] = round_to( $total_amount, 2, true );
			     	$sub['discount_amt'] = round_to( $discounted_amount, 2, true );
			     	$sub['final_amount'] = round_to( $final_amount, 2, true );
			     	$args['data']['details'][] = $sub;

			     	$disc = [];
			     	$disc['final_amount'] = $disc['discount_amt'] = 0;
			     	if( $header['discount'] )
		        	{
		        		$disc['prdt_name'] = 'DISCOUNT:';
		        		$disc['disc'] = $header['discount'];
				     	$disc['discount_amt'] = round_to( wh_apply_discount( $discounted_amount, $header['discount'] ), 2, true );
				     	$disc['final_amount'] = round_to( wh_apply_discount( $final_amount, $header['discount'] ), 2, true );
				     	$args['data']['details'][] = $disc;
		        	}

		        	if( $header['fees'] )
				    {
				    	$fee = []; $fee_amt = 0;
				    	foreach( $header['fees'] as $i => $row )
				    	{
				    		$fee['prdt_name'] = $row['fee_name'].':';
					    	$fee['discount_amt'] = round_to( $row['fee'], 2, true );
					    	$args['data']['details'][] = $fee;

					    	$fee_amt+= $row['fee'];
				    	}
				    }

		        	$final = [];
			     	$final['prdt_name'] = '<strong>TOTAL:</strong>';
		        	$final['discount_amt'] = '<strong>'.round_to( $discounted_amount - $disc['discount_amt'] + $fee_amt, 2, true ).'</strong>';

		        	if( $final_amount ) 
		        		$final['final_amount'] = '<strong>'.round_to( $final_amount - $disc['final_amount'] + $fee_amt, 2, true ).'</strong>';

			     	$args['data']['details'][] = $final;
		        }

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
		        ];

		        $Inst = new WCWH_Listing();

		        $args['render'] = $Inst->get_listing( $cols, 
		        	$args['data']['details'], 
		        	[], 
		        	$hides, 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}
		
		if( $args['data']['ref_doc_id'] )
			$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );
		
		if($header)
		{
			do_action( 'wcwh_get_template', 'form/eInvoice-form.php', $args );
		}
		else
		{
			$succ = false;
			$this->Notices->set_notice( 'Please Input Valid SO No.', 'error' );
		}

		if($succ)
		{
			return $succ;
		}
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

				////---------12/9/22-----------------------------------
				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1 ] );
				///-------------------------12/9/22---------------------

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				$args['action'] = 'update-header';

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_sales_order'] ) )
				{
					$hides = [ 'sprice', 'total_amount' ];
				}
		        	
		        if( $datas['details'] )
		        {	
		        	$total_amount = 0; $discounted_amount = 0; $final_amount = 0;
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

		        		$datas['details'][$i]['disc'] = $datas['details'][$i]['discount'];
		        		if( strstr( $datas['details'][$i]['discount'], '%' ) )
		        		{
		        			$datas['details'][$i]['disc'] = round_to( wh_apply_discount( $datas['details'][$i]['total_amount'], $datas['details'][$i]['discount'] ), 2, true )." ({$datas['details'][$i]['discount']})";
		        		}
		        		$datas['details'][$i]['discount_amt'] = round_to( $datas['details'][$i]['total_amount'] - $datas['details'][$i]['disc'], 2, true );
		        		
		        		$total_amount+= $datas['details'][$i]['total_amount'];
		        		$discounted_amount+= $datas['details'][$i]['discount_amt'];

		        		if( $datas['status'] >= 9 )
		        		{
		        			$datas['details'][$i]['final_amount'] = round_to( $item['uqty'] * $datas['details'][$i]['def_sprice'], 2, true );

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

		        	if( !$hides )
		        	{
		        		$sub = [];
			        	$sub['prdt_name'] = 'SUBTOTAL:';
			        	$sub['total_amount'] = round_to( $total_amount, 2, true );
			        	$sub['discount_amt'] = round_to( $discounted_amount, 2, true );
			        	$sub['final_amount'] = round_to( $final_amount, 2, true );
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
		        	'sprice' => 'End Price',
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
			do_action( 'wcwh_templating', 'form/eInvoice-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/eInvoice-form.php', $args );
		}
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
			include_once( WCWH_DIR . "/includes/listing/eInvoiceListing.php" ); 
			$Inst = new WCWH_EInvoice_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#cd' => [ 'width' => '120px' ],
			];

			$this->Logic->processing_stat = [1,6];

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

			$metas = [ 'sap_po', 'remark', 'ref_doc_id', 'ref_doc', 'client_company_code', 'uuid', 'longid', 'validate_date', 'irb_status' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas ], [], $limit );
			
			$datas = ( $datas )? $datas : array();
			//pd( $datas );
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}