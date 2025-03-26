<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_GoodIssue_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-issue.php" ); 

//if ( !class_exists( "WCWH_Company_Class" ) ) include_once( WCWH_DIR . "/includes/classes/company.php" ); 

if ( !class_exists( "WCWH_GoodIssue_Controller" ) ) 
{

class WCWH_GoodIssue_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_good_issue";

	public $Notices;
	public $Files;
	public $className = "GoodIssue_Controller";

	public $Logic;
	//public $Comp;

	public $tplName = array(
		'new' => 'newGI',
		'row' => 'rowGI',
		'do' => 'printDO',
	);

	public $GIType = array(
		'delivery_order'	=> 'Delivery Order',
		'reprocess'			=> 'Reprocess',
		'own_use'			=> 'Company Use',
		'vending_machine'	=> 'Vending Machine',
		'block_stock'		=> 'Block Stock',
		'transfer_item'		=> 'Transfer Item',
		'direct_consume'	=> 'Direct Consume',
		'other'				=> 'Other',
		'returnable'		=> 'Replaceable',
	);

	public $GIType_by_DocType = array(
		'purchase_request'	=> 'delivery_order',
		'sale_order' 		=> 'delivery_order',
	);

	public $direct_issue_type = 'direct_consume';

	public $useFlag = false;
	public $directPost = true;

	public $ref_doc_type = '';

	public $ref_issue_type = '';

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1, 6 ];

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		$this->Files = new WCWH_Files();

		$this->arrangement_init();
		
		$this->set_logic();
	}

	public function __destruct()
	{
		unset($this->Logic);
		unset($this->Notices);
		unset($this->Files);
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
		$this->Logic = new WCWH_GoodIssue_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->processing_stat = $this->processing_stat;

		//$this->Comp = new WCWH_Company_Class( $this->db_wpdb );
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

		$this->use_flag_decision();
	}

	public function use_flag_decision()
	{
		if( $this->useFlag )
		{
			if( in_array( $this->ref_issue_type, [ 'delivery_order', 'reprocess', 'vending_machine', 'transfer_item', 'direct_consume' ] ) )
			{
				$this->useFlag = false;
				$this->Logic->useFlag = $this->useFlag;
			}
		}
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
					if( in_array( $this->ref_issue_type, ['block_stock'] ) && ! $_FILES && ! $datas['attachment'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'Attachment is required', 'warning' );
					}

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
					$attachment = $datas['attachment'];
					$files = $_FILES;

					if( ! $header['good_issue_type'] ) $succ = false;
					if( in_array( $header['good_issue_type'], ['delivery_order'] ) && ! $header['client_company_code'] ) 
					{
						$succ = false;
						$this->Notices->set_notice( 'Client is required for Delivery Order', 'error' );
					}

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					if( $header['ref_doc_id'] && $succ )
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

							if( in_array( $ref_header['doc_type'], ['purchase_request'] ) )
							{
								$header['purchase_doc'] = $ref_header['docno'];
								$header['purchase_warehouse_id'] = $ref_header['warehouse_id'];
							}
							else if( in_array( $ref_header['doc_type'], ['sale_order'] ) )
							{
								$header['purchase_doc'] = get_document_meta( $ref_header['doc_id'], 'purchase_doc', 0, true );
								$header['purchase_warehouse_id'] = get_document_meta( $ref_header['doc_id'], 'purchase_warehouse_id', 0, true );
							}

							$header['direct_issue'] = get_document_meta( $ref_header['doc_id'], 'direct_issue', 0, true );
						}
					}

					if( $header['client_company_code'] )
					{
						$client = apply_filters( 'wcwh_get_client', [ 'code'=>$header['client_company_code'] ], [], true, [] );
						$c_wh = apply_filters( 'wcwh_get_warehouse', [ 'client_company_code'=>$header['client_company_code'] ], [], true, 
							[ 'meta'=>[ 'client_company_code' ], 'meta_like'=>[ 'client_company_code'=>1 ] 
						] );

						$sameComp = ( $c_wh && $c_wh['code'] == $this->warehouse['code']  )? true : false;
					}

					$canSell = false;
					if( ( $sameComp  && in_array( $header['good_issue_type'], ['delivery_order'] ) ) 
						|| ( in_array( $header['ref_doc_type'], ['sale_order'] ) )
					){
						$canSell = true;
						$header['warehouse_stock_method'] = 'sale_order';
					}

					if( $detail && $succ )
					{
						foreach( $detail as $i => $row )
						{
							if( ! $row['bqty'] )
							{
								unset( $detail[$i] );
								continue;
							}

							$sprice = 0;
							if( $row['ref_doc_id'] && $row['ref_item_id'] )
							{
								$price = get_document_meta( $row['ref_doc_id'], 'sprice', $row['ref_item_id'], true );
								$sprice = ( $price )? $price : $sprice;
								$detail[$i]['sprice'] = $sprice;
							}

							if( $canSell && ! $sprice && in_array( $header['good_issue_type'], ['delivery_order'] ) 
							){
								$price = apply_filters( 'wcwh_get_price', $row['product_id'], $this->warehouse['code'], 
									[ 'client_code'=>$header['client_company_code'] ], $header['doc_date'] );

								if( !$price || ! $price['unit_price'] )
								{
									$succ = false;
									$item = apply_filters( 'wcwh_get_item', [ 'id'=>$row['product_id'] ], [], true, [] );
									$item = $item['code'].' - '.$item['name'];
									$this->Notices->set_notice( 'No selling price for item '.$item.'.', 'error' );
								}
								else
									$detail[$i]['sprice'] = $price['unit_price'];
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

							$doc_id = $result['id'];
							if( !empty( $attachment ) )
							{
								$succ = $this->Files->attachment_handler( $attachment, $this->section_id, $doc_id );
							}
							if( !empty( $files ) )
							{
								$fr = $this->Files->upload_files( $files, $this->section_id, $doc_id );
								if( $fr )
								{
									update_document_meta( $doc_id, 'attachments', maybe_serialize( $fr ) );
								}
								else{
									$succ = false;
									$this->Notices->set_notice( 'File Upload Failed', 'error' );
								}
							}

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

								if( $succ && $this->directPost && $header['flag'] )	//direct post
								{
									$result = $this->Logic->child_action_handle( 'post', $header );
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

					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
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
									'G.I. No.' => $doc['docno'],
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
									'Issue Type' => $this->GIType[ $doc['good_issue_type'] ],
								];
								if( $doc['ref_doc'] ) $params['heading']['second_infos']['Ref. Doc.'] = $doc['ref_doc'];

								$client = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'shipping' ] );
								if( $client )
								{
									$params['heading']['first_infos']['Consignee'] = $client['name'];
									$params['heading']['first_infos']['Consignee Address'] = apply_filters( 'wcwh_get_formatted_address', [
											'address_1'  => $client['address_1'],
											'city'       => $client['city'],
											'state'      => $client['state'],
											'postcode'   => $client['postcode'],
											'country'    => $client['country'],
										] );
									$params['heading']['first_infos']['Contact'] = trim( $client['contact_person'].' '.$client['contact_no'] );
								}

								//base doc data
								$shipping = get_document_meta( $doc['ref_doc_id'], 'diff_shipping_address', 0, true );
								$contact = get_document_meta( $doc['ref_doc_id'], 'diff_shipping_contact', 0, true );
								if( $shipping ) $params['heading']['first_infos']['Consignee Address'] = nl2br( $shipping );
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
							        	$detail_metas = get_document_meta( $id, '', $item['item_id'] );
		        						$item = $this->combine_meta_data( $item, $detail_metas );

							        	$row = [];
							        	$row['item'] = $item['prdt_code'].' - '.$item['prdt_name'];
							        	$row['uom'] = $item['uom_code'];
							        	$row['qty'] = round_to( $item['bqty'], 2 );

							        	$detail[] = $row;
							        }

							        $params['detail'] = $detail;
							    }
							}

							ob_start();
							
								do_action( 'wcwh_get_template', 'template/doc-picking-list.php', $params );
							
							$content.= ob_get_clean();
							//echo $content;exit;
							if( is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) ){
								$paper = [ 'size' => 'A4', 'orientation' => 'portrait' ];
								$args = [ 'filename' => $params['heading']['docno'] ];
								do_action( 'dompdf_generator', $content, $paper, array(), $args );
							}
							else{
								echo $content;
							}
						break;
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

								if( $doc['client_company_code'] ) $billing = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'billing' ] );
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

								if( $doc['client_company_code'] ) $shipping = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'shipping' ] );
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
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'], $this->ref_doc_type );

			switch( $this->ref_doc_type )
			{
				case 'sales_order':
					$doc = 'SO';
				break;
				case 'transfer_order':
					$doc = 'TO';
				break;
				default:
					$doc = 'PR/SO/TO';
				break;
			}
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'client_name', 'remark' ], 'New Goods Issue by Document ('.$doc.' if any)' );
	        echo '<div id="good_issue_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'good_issue_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#delivery_order_action"'], 'class'=>['select2','triggerChange'], 
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
				<button id="delivery_order_action" class="display-none btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> GI for Delivery"
					data-title="<?php echo $actions['save'] ?> GI for Delivery" 
					data-action="good_issue_delivery_order" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#good_issue_reference" 
				>
					GI for Delivery
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'own_use':
				if( current_user_cans( [ 'save_own_use_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="own_use_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> GI for Company Use"
					data-title="<?php echo $actions['save'] ?> GI for Company Use" 
					data-action="good_issue_own_use" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Company Use
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'vending_machine':
				if( current_user_cans( [ 'save_vending_machine_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="vending_machine_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> GI for Vending Machine"
					data-title="<?php echo $actions['save'] ?> GI for Vending Machine" 
					data-action="good_issue_vending_machine" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Vending Machine
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'reprocess':
				if( current_user_cans( [ 'save_reprocess_'.$this->section_id ] ) && $this->setting['general']['use_reprocess_item'] && ! $this->view_outlet ):
			?>
				<button id="reprocess_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> GI for Reprocess"
					data-title="<?php echo $actions['save'] ?> GI for Reprocess" 
					data-action="good_issue_reprocess" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Reprocess
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'block_stock':
				if( current_user_cans( [ 'save_block_stock_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="block_stock_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> GI for Block Stock"
					data-title="<?php echo $actions['save'] ?> GI for Block Stock" 
					data-action="good_issue_block_stock" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Block Stock
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'transfer_item':
				if( current_user_cans( [ 'save_transfer_item_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="block_stock_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> GI for Transfer Item"
					data-title="<?php echo $actions['save'] ?> GI for Transfer Item" 
					data-action="good_issue_transfer_item" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Transfer Item
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'direct_consume':
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && $this->setting[ $this->section_id ]['use_direct_consume'] && ! $this->view_outlet ):
			?>
				<button id="direct_consume_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> GI for Direct Consume"
					data-title="<?php echo $actions['save'] ?> GI for Direct Consume" 
					data-action="good_issue_direct_consume" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Direct Consume
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'other':
				if( current_user_cans( [ 'save_other_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="own_use_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> Other GI"
					data-title="<?php echo $actions['save'] ?> Other GI" 
					data-action="good_issue_other" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Other
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
			case 'sale_order':
				if( ! class_exists( 'WCWH_SaleOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
				$Inst = new WCWH_SaleOrder_Controller();
			break;
			case 'transfer_order':
				if( ! class_exists( 'WCWH_TransferOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/transferOrderCtrl.php" ); 
				$Inst = new WCWH_TransferOrder_Controller();
			break;
			case 'delivery_order':
				if( ! class_exists( 'WCWH_DeliveryOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
				$Inst = new WCWH_DeliveryOrder_Controller();
			break;
			case 'good_receive':
				if( ! class_exists( 'WCWH_GoodReceive_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
				$Inst = new WCWH_GoodReceive_Controller();
			break;
			case 'purchase_debit_note':
				if( ! class_exists( 'WCWH_PurchaseCDNote_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/purchaseCDNoteCtrl.php" ); 
				$Inst = new WCWH_PurchaseCDNote_Controller();
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
					case 'reprocess':
						if( current_user_cans( [ 'access_wh_reprocess' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/reprocessCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_Reprocess_Controller();
							$titles[ $doc_type ] = "Reprocess";
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

	public function gen_form( $id = 0, $type = 'delivery_order' )
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
			'ref_issue_type' => $this->ref_issue_type,
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( is_array( $id ) && $id )
		{
			$items = apply_filters( 'wcwh_get_item', [ 'id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1, 'stocks'=>$this->warehouse['code'] ] );
			if( $items )
			{
				$c_items = []; $inventory = [];
		        foreach( $items as $i => $item ) if( $item['parent'] > 0 ) $c_items[] = $item['id'];
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
							'stocks' => $stk,
						],
					);
				}
				$args['data']['details'] = $details;
			}
		}

		$args['data']['good_issue_type'] = $type;
		$args['gi_type'] = $this->GIType;
		
		if( ! is_array( $id ) && $id )
		{
			$header = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [ 'company'=>1 ] );
			if( $header )
			{
				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];

				$args['NoAddItem'] = true;
			}

			$filters = [ 'doc_id'=>$id ];
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1, 'stocks'=>$this->warehouse['code'] ] );

			if( in_array( $header['doc_type'], [ 'purchase_request', 'sale_order' ] ) )
			{
				$args['data']['client_company_code'] = get_document_meta( $id, 'client_company_code', 0, true );
			}

			$args['data']['good_issue_type'] = $this->GIType_by_DocType[ $header['doc_type'] ];
			
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
					$stk = ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['qty'] : $item['stock_qty'];
		        	$stk-= ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['allocated_qty'] : $item['stock_allocated'];

					$details[] = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 'stocks' => $stk, ],
						'bqty' => ( $item['bqty'] - $item['uqty'] ),
						'ref_bqty' => $item['bqty'],
						'ref_bal' => ( $item['bqty'] - $item['uqty'] ),
						'ref_doc_id' => $item['doc_id'],
						'ref_item_id' => $item['item_id'],
					);
				}
				$args['data']['details'] = $details;
			}
		}

		if( $args['data']['ref_doc_id'] )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/goodIssue-form.php', $args );
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
			'ref_issue_type' => $this->ref_issue_type,
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
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				if( $datas['ref_doc_id'] ) $args['NoAddItem'] = true;

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_good_issue'] ) )
				{
					$hides = [ 'sprice', 'total_amount', 'ucost', 'total_cost', 'total_profit' ];
				}

				$attachs = $this->Files->get_infos( [ 'section_id'=>$this->section_id, 'ref_id'=>$id, 'seller'=>$args['seller'] ], [], false, [ 'usage'=>1 ] );
				if( $attachs )
				{
					if( $args['seller'] )
					{
						foreach( $attachs as $x => $attach )
						{
							if( $this->warehouse['api_url'] ) $attachs[$x]['api_url'] = $this->warehouse['api_url'];
						}
					}
					$datas['attachment'] = $attachs;
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
		        		$datas['details'][$i]['bunit'] = ( $datas['details'][$i]['sunit'] )? $datas['details'][$i]['sunit'] : $datas['details'][$i]['tran_bunit'];
		        		$datas['details'][$i]['bunit'] = round_to( $datas['details'][$i]['bunit'], 3, true );

		        		$datas['details'][$i]['sprice'] = round_to( $datas['details'][$i]['sprice'], 5, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['bqty'] * $datas['details'][$i]['sprice'], 2, true );
		        		$total_amount+= $datas['details'][$i]['total_amount'];

		        		$datas['details'][$i]['total_cost'] = round_to( ( $datas['details'][$i]['weighted_total'] )? $datas['details'][$i]['weighted_total'] : $datas['details'][$i]['bqty'] * $datas['details'][$i]['ucost'], 2, true );
		        		$total_cost+= $datas['details'][$i]['total_cost'];
		        		$datas['details'][$i]['ucost'] = round_to( $datas['details'][$i]['total_cost'] / $datas['details'][$i]['bqty'], 5, true );

		        		$datas['details'][$i]['total_profit'] = round_to( $datas['details'][$i]['total_amount'] - $datas['details'][$i]['weighted_total'], 2, true );
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

		$args['gi_type'] = $this->GIType;

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/goodIssue-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( isset( $config['ref'] ) && $config['ref'] )
			{
				if( !empty( $args['data']['delivery_doc_id'] ) )
					$this->view_reference_doc( $args['data']['delivery_doc_id'], $args['data']['delivery_doc'], 'delivery_order' );
				else if( !empty( $args['data']['ref_doc_id'] ) )
					$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );
			}

			do_action( 'wcwh_get_template', 'form/goodIssue-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/goodIssue-row.php', $this->tplName['row'] );
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
			include_once( WCWH_DIR . "/includes/listing/goodIssueListing.php" ); 
			$Inst = new WCWH_GoodIssue_Listing();
			
			
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->ref_issue_type = $this->ref_issue_type;
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#action' => [ 'width' => '90px' ],
			];
			$Inst->GIType = $this->GIType;
			//if( ! $this->setting['general']['use_reprocess_item'] ) unset( $Inst->GIType->GIType['reprocess'] );
			//if( ! $this->setting[ $this->section_id ]['use_direct_consume'] ) unset( $Inst->GIType['direct_consume'] );

			if( $this->ref_doc_type  == 'sale_order' && current_user_cans( [ 'access_'.$this->section_id ] ))
				$this->ref_doc_type = ['sale_order','sale_debit_note'];
			
			$count = $this->Logic->count_statuses( $this->warehouse['code'], $this->ref_doc_type, $this->ref_issue_type );
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
				$filters['ref_doc_type'] = $this->ref_doc_type;
			}
			if( $this->ref_issue_type )
			{
				$filters['good_issue_type'] = $this->ref_issue_type;
			}
			
			$metas = [ 'remark', 'ref_doc_id', 'ref_doc_type', 'ref_doc', 'good_issue_type', 'client_company_code', 'vending_machine' ];

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