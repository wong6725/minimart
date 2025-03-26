<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PurchaseOrder_Class" ) ) include_once( WCWH_DIR . "/includes/classes/purchase-order.php" ); 

if ( !class_exists( "WCWH_PurchaseOrder_Controller" ) ) 
{

class WCWH_PurchaseOrder_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_purchase_order";

	public $Notices;
	public $className = "PurchaseOrder_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newPO',
		'row' => 'rowPO',
		'import' => 'importPO',
		'export' => 'exportPO',
		'po' => 'printPO',
	);

	public $useFlag = false;
	public $directPost = true;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1, 6 ];

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
		$this->Logic = new WCWH_PurchaseOrder_Class( $this->db_wpdb );
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
							if( $row['bqty'] && 
								( is_null( $row['total_amount'] ) || $row['total_amount'] == '' ) && 
								( is_null( $row['uprice'] ) || $row['uprice'] == '' ) )
							{
								$succ = false;
								$this->Notices->set_notice( 'Please fill in either Total Amount or Unit Price.', 'warning' );
							}
						}
					}
				break;
				case 'delete':
				case 'post':
				case 'unpost':
				case 'confirm':
				case 'refute':
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

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;
					$header['invoice'] = trim( $header['invoice'] );
					if( !empty( $header['invoice'] ) && empty( $header['automate_purchase'] ) )
					{
						$ft = [ 'invoice'=>$header['invoice'], 'supplier_company_code'=>$header['supplier_company_code'] ];
						$ft['status'] = [ 1, 6, 9 ];
						if( $header['doc_id'] ) $ft['not_doc_id'] = $header['doc_id'];
						$find_inv = $this->Logic->get_header( $ft, [], false, [ 'meta'=>[ 'invoice', 'supplier_company_code' ] ] );
						if( $find_inv && count( $find_inv ) > 0 )
						{
							$succ = false;
							$this->Notices->set_notice( "Invoice: {$header['invoice']} is repeated.", 'warning' );
						}
					}

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

							//$metas = get_document_meta( $ref_header['doc_id'] );
							//$ref_header = $this->combine_meta_data( $ref_header, $metas );
						}
					}
					if( $detail )
					{
						$header['total_amount'] = 0;
						foreach( $detail as $i => $row )
						{
							if( ! $row['bqty'] && ! $row['foc'] )
							{
								unset( $detail[$i] );
								continue;
							}

							if( $row['total_amount'] > 0 )
							{
								//$detail[$i]['uprice'] = round_to( ( $row['bunit'] )? $row['total_amount'] / $row['bunit'] : $row['total_amount'] / $row['bqty'], 5 );
								$detail[$i]['uprice'] = round_to( $row['total_amount'] / $row['bqty'], 5 );
							}
							else
							{
								$uprice = ( $row['uprice'] )? $row['uprice'] : 0;
								//$detail[$i]['total_amount'] = round_to( ( $row['bunit'] )? $row['bunit'] * $uprice : $row['bqty'] * $uprice, 2 );
								$detail[$i]['total_amount'] = round_to( $row['bqty'] * $uprice, 2 );
							}

							$detail[$i]['bqty'] = $row['bqty'] + $row['foc'];
							$detail[$i]['avg_price'] = round_to( $detail[$i]['total_amount'] / ( $row['bqty'] + $row['foc'] ), 5 );

							$header['total_amount']+= $detail[$i]['total_amount'];
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
				case "update-header":
					$header = $datas['header'];
					$detail = $datas['detail'];

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;
					$header['invoice'] = trim( $header['invoice'] );
					if( !empty( $header['invoice'] ) && empty( $header['automate_purchase'] ) )
					{
						$ft = [ 'invoice'=>$header['invoice'], 'supplier_company_code'=>$header['supplier_company_code'] ];
						$ft['status'] = [ 1, 6, 9 ];
						if( $header['doc_id'] ) $ft['not_doc_id'] = $header['doc_id'];
						$find_inv = $this->Logic->get_header( $ft, [], false, [ 'meta'=>[ 'invoice', 'supplier_company_code' ] ] );
						if( $find_inv && count( $find_inv ) > 0 )
						{
							$succ = false;
							$this->Notices->set_notice( "Invoice: {$header['invoice']} is repeated.", 'warning' );
						}
					}

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
				case "confirm":
				case "refute":
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
				case "export":
					$datas['filename'] = 'PO';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['supplier_code'] ) ) $params['supplier_company_code'] = $datas['supplier_code'];
					if( !empty( $datas['status'] ) ) $params['status'] = $datas['status'];
					
					//$succ = $this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
						case 'purchase_order':
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
									'P.O. No.' => $doc['docno'],
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
								];

								if( $this->useFlag )
								{
									$f = [ 
										'ref_id' => $doc['doc_id'], 
										'section' => $this->section_id, 
										'action_type' => 'approval',
										'status' => 1,
										'flag' => 1,
									];
									$todo = apply_filters( 'wcwh_get_todo', $f, [], true, [ 'arrangement'=>1 ] );
									if( $todo )
									{
										$uid = [];
										if( $todo['created_by'] ) $uid[] = $todo['created_by'];
										if( $todo['action_by'] ) $uid[] = $todo['action_by'];
										$actors = get_simple_users( $uid, 0 );

										$creator = $actors[ $todo['created_by'] ];
										$approver = $actors[ $todo['action_by'] ];
										$params['prepare_by'] = ( $creator['name'] )? $creator['name'] : $creator['display_name'];
										$params['approve_by'] = ( $approver['name'] )? $approver['name'] : $approver['display_name'];
									}
								}
								
								$supplier = apply_filters( 'wcwh_get_supplier', [ 'code'=>$doc['supplier_company_code'] ], [], true, [ 'address'=>'default' ] );
								if( $supplier )
								{
									$params['heading']['first_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $supplier['contact_person'],
										'company'    => $supplier['name'],
										'address_1'  => $supplier['address_1'],
										'city'       => $supplier['city'],
										'state'      => $supplier['state'],
										'postcode'   => $supplier['postcode'],
										'country'    => $supplier['country'],
										'phone'		 => $supplier['contact_no'],
									] );
								}
								$self = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ 'address'=>'default' ] );
								if( $self )
								{
									$comp = apply_filters( 'wcwh_get_company', [ 'id'=>$self['comp_id'] ], [], true, [ 'address'=>'default' ] );
									$addr = ( !empty( $self['address_1'] ) || ( empty( $self['address_1'] ) && empty( $comp['address_1'] ) ) )? $self : $comp;
									$params['heading']['second_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $addr['contact_person'],
										'company'    => $addr['name'],
										'address_1'  => $addr['address_1'],
										'city'       => $addr['city'],
										'state'      => $addr['state'],
										'postcode'   => $addr['postcode'],
										'country'    => $addr['country'],
										'phone'		 => $addr['contact_no'],
									] );
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
							        	if( $datas['view_type'] == 'category' ) $row['item'] = $item['cat_code'].' - '.$item['cat_name'];
							        	$row['uom'] = $item['uom_code'];
							        	$row['qty'] = round_to( ( $item['foc'] > 0 )? $item['bqty'] - $item['foc'] : $item['bqty'], 2 );
							        	$row['foc'] = round_to( $item['foc'], 2 );

							        	$row['uprice'] = round_to( $item['uprice'], 2 );
							        	$row['total_amount'] = round_to( $item['total_amount'], 2 );

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
										do_action( 'wcwh_get_template', 'template/receipt-purchase-order.php', $params );
									$content.= ob_get_clean();

									echo $content;
								break;
								case 'default':
								default:
									ob_start();
										do_action( 'wcwh_get_template', 'template/doc-purchase-order.php', $params );
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

				if( $succ && in_array( $action, [ 'post', 'email' ] ) && !empty( $this->warehouse['po_email'] ) )
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

			$subject = 'Purchase Order '.$doc[ $ref_id ]['docno'];
			$message = 'Purchase Order '.$doc[ $ref_id ]['docno'].' has been posted to proceed.';

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
				'id' => 'purchase_order_posted_mail',
				'section' => $this->section_id,
				'datas' => $doc[ $ref_id ],
				'ref_id' => $ref_id,
				'subject' => $subject,
				'message' => $message,
			];
			$args['recipient'] = $this->warehouse['po_email'];
			//if( current_user_cans( ['wh_super_admin'] ) ) $args['recipient'] = 'wid001@suburtiasa.com';

			do_action( 'wcwh_set_email', $args );
			do_action( 'wcwh_trigger_email', [] );
		}


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = [];

		$default_column['header'] = [ 'doc_id', 'warehouse_id', 'docno', 'sdocno', 'doc_date', 'post_date', 'doc_type', 'hstatus', 'hflag', 'parent', 
			'supplier_company_code', 'remark'
		];

		$default_column['detail'] = [ 'item_id', 'strg_id', 'product_id', 'uom_id', 'bqty', 'uqty', 'bunit', 'uunit', 'ref_doc_id', 'ref_item_id', 'dstatus', 
			'uprice', 'total_amount', 'avg_price', '_item_number'
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


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'] );
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'comp_code', 'comp_name', 'remark' ], 'New PO by Document (PR if any)' );
	        echo '<div id="purchase_order_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'purchase_order_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#purchase_order_action"'], 'class'=>['select2','triggerChange'], 
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
			//----- Form Restoration 09/11/22
			case 'transient':
				$save_trans = get_transient( get_current_user_id().$this->section_id.'_save_form' );
				$update_trans = get_transient( get_current_user_id().$this->section_id.'_update_form' );

				$save_timestamp = ($save_trans['timestamp'])? $save_trans['timestamp'] : 0;
				$update_timestamp = ($update_trans['timestamp'])? $update_trans['timestamp'] : 0;
				
				if($save_timestamp < $update_timestamp)
					$flg = 'update';
				else
					$flg = 'save';

				$trans_datas = get_transient( get_current_user_id().$this->section_id.'_'.$flg.'_form' );
				$htmltext = ($trans_datas['_form']['docno'])? $trans_datas['_form']['docno'] : ucfirst($flg);

				if( current_user_cans( [ $flg.'_'.$this->section_id ] )&& $trans_datas):
				?>
					<button 
						class="btn btn-sm btn-primary linkAction"
						title="Restoring <?php echo $actions[$flg] ?> Purchase Order" 
						data-title="<?php echo $actions[$flg] ?> Purchase Order"
						data-action="<?php echo $this->section_id.'_transient_'.$flg; ?>"
						data-service="<?php echo $this->section_id; ?>_action"  
						data-modal="wcwhModalForm" 
						data-actions="close|submit"						
					>
						Restore PO ( <?php echo $htmltext ?> )
						<i class="fa fa-plus-circle" aria-hidden="true"></i>
					</button>
				<?php
				endif;
			break;
			//----- Form Restoration 09/11/22
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="purchase_order_action" class="btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Purchase Order (PO)"
					data-title="<?php echo $actions['save'] ?> Purchase Order (PO)" 
					data-action="purchase_order_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#purchase_order_reference" 
				>
					<?php echo $actions['save'] ?> Purchase Order (PO)
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
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
			case 'purchase_request':
				if( ! class_exists( 'WCWH_PurchaseRequest_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" ); 
				$Inst = new WCWH_PurchaseRequest_Controller();
			break;
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
					case 'good_receive':
						if( current_user_cans( [ 'access_wh_good_receive' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_GoodReceive_Controller();
							$titles[ $doc_type ] = "Goods Receipt";
						}
					break;
					case 'purchase_debit_note':
					case 'purchase_credit_note':
						if( current_user_cans( [ 'access_wh_purchase_cdnote' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/purchaseCDNoteCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_PurchaseCDNote_Controller();
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

	public function gen_form( $id = 0, $trans_action = '' )
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
		
		//----- Form Restoration 09/11/22
		if( $trans_action && $trans_action == 'update' ) $transient_data = get_transient( get_current_user_id().$this->section_id.'_update_form' );
		else if ( $trans_action && $trans_action == 'save' ) $transient_data = get_transient( get_current_user_id().$this->section_id.'_save_form' );
		//----- Form Restoration 09/11/22
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( is_array( $id ) && $id )
		{
			//----------------------12/9/22
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
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk,
						],
					);
				}
				//----------------------12/9/22
				$args['data']['details'] = $details;
			}
		}
		
		if( ! is_array( $id ) && $id )
		{
			$header = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [ 'company'=>1 ] );
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
			}

			$filters = [ 'doc_id'=>$id ];
			//----------------------12/9/22
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1, 'stocks'=>$this->warehouse['code'] ] );
			//----------------------12/9/22
			
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

					$metas = get_document_meta( $id, '', $item['item_id'] );
					$item = $this->combine_meta_data( $item, $metas );
					
					$uprice = ( $item['sprice'] )? $item['sprice'] : $item['ucost'];
					$details[] = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk,
						],
						'bqty' => ( $item['bqty'] - $item['uqty'] ),
						'bunit' => ( $item['bunit'] - $item['uunit'] ),
						'uprice' => $uprice,
						'ref_bqty' => $item['bqty'],
						'ref_bal' => ( $item['bqty'] - $item['uqty'] ),
						'ref_doc_id' => $item['doc_id'],
						'ref_item_id' => $item['item_id'],
						'foc' => '',
					);
				}
				$args['data']['details'] = $details;
			}
		}
		else if ($transient_data) //----- Form Restoration 09/11/22
		{
			$header = $transient_data['_form'];
			$detail = $transient_data['_detail'];
			$datas = [];

			$datas = $header;

			//------detail
			$item_ids = [];
			$temp_item = [];
			$ditem = [];
			$ref = [];

			if( $header['doc_id'] )
			{
				$args['action'] = 'update';
				$doc = $this->Logic->get_detail( [ 'doc_id'=>$header['doc_id'] ], [], false, ['usage'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );
				if($doc)
				{
					foreach( $doc as $r => $doc_item)
					{
						$ditem[$doc_item['product_id']] = $doc_item;
					}
				}

			}

			if($header['ref_doc_id'])
			{
				$ref_doc = $this->Logic->get_detail( [ 'doc_id'=>$header['ref_doc_id'] ], [], false, ['usage'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );
				if($ref_doc)
				{
					foreach( $ref_doc as $r => $doc_item)
					{
						$ref[$doc_item['product_id']] = $doc_item;
					}
				}
			}

			foreach( $detail as $i => $item ) $item_ids[] = $item['product_id'];
			$products = apply_filters( 'wcwh_get_item', ['id'=>$item_ids], [], false, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1, 'stocks'=>$this->warehouse['code'], 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
			if($products)
			{
				$c_items = []; $inventory = [];
				foreach ( $products as $i => $product ) 
				{
					$temp_item[$product['id']] = $product;
					if( $product['parent'] > 0 ) $c_items[] = $product['id'];
				}

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
			}

			foreach ( $detail as $i => $item ) 
			{
				$datas['details'][$i] = $item;
				$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
				$datas['details'][$i]['row_id'] = $item['item_id'];
				$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2 );
		        $datas['details'][$i]['foc'] = round_to( $item['foc'], 2 );

		        $datas['details'][$i]['uprice'] = round_to( $item['uprice'], 5, true );
		        $datas['details'][$i]['total_amount'] = round_to( $item['total_amount'], 2, true );

		        if( $temp_item[$item['product_id']] )
		        {
		        	$datas['details'][$i]['item_name'] = $temp_item[$item['product_id']]['name'];
		        	$datas['details'][$i]['prdt_name'] = $temp_item[$item['product_id']]['code'].' - '.$temp_item[$item['product_id']]['name'];

		        	$stk = ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['qty'] : $temp_item[$item['product_id']]['stock_qty'];
		        	$stk-= ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['allocated_qty'] : $temp_item[$item['product_id']]['stock_allocated'];

		        	$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$temp_item[$item['product_id']]['name'], 
		        			'code'=>$temp_item[$item['product_id']]['code'], 
		        			'uom_code'=>$temp_item[$item['product_id']]['_uom_code'], 
							'uom_fraction'=>$temp_item[$item['product_id']]['uom_fraction'], 
							'required_unit'=>$temp_item[$item['product_id']]['required_unit'], 
							'stocks' => $stk,
		        		];
		        }

		        if( $ref[$item['product_id']] )	
		        {
		        	$dbqty = ( $ditem[$item['product_id']] )? $ditem[$item['product_id']]['bqty'] : 0;
		        	$datas['details'][$i]['ref_bqty'] = $ref[$item['product_id']]['bqty'];
		        	$datas['details'][$i]['ref_bal'] = $ref[$item['product_id']]['bqty'] - $ref[$item['product_id']]['uqty'] + $dbqty;
		        }	        		
			}

		    $args['data'] = $datas;
		    //--------detail
		}

		//----- Form Restoration 09/11/22

		if( $args['data']['ref_doc_id'] )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/purchaseOrder-form.php', $args );
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

				////---------12/9/22-----------------------------------
				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );
				///-------------------------12/9/22---------------------

				/*
				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'], 'transact'=>1 ] );
				*/

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				if( $datas['status'] >= 6 && ! current_user_cans( [ 'wh_admin_support' ] ) )
					$args['action'] = 'update-header';

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_purchase_order'] ) )
				{
					$hides = [ 'uprice', 'total_amount', 'avg_price' ];
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

		        	$total_amount = 0; $final_amount = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['row_id'] = $item['item_id'];

		        		$datas['details'][$i]['item_name'] = $item['prdt_name'];
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
						
						$stk = ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['qty'] : $item['stock_qty'];
		        		$stk-= ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['allocated_qty'] : $item['stock_allocated'];

		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk,
		        		];

						$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;

						$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		if( $datas['details'][$i]['foc'] > 0 ) $item['bqty']-= $datas['details'][$i]['foc'];
		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2 );
		        		$datas['details'][$i]['foc'] = round_to( $datas['details'][$i]['foc'], 2 );
		        		$datas['details'][$i]['lqty'] = round_to( $item['bqty'] - $item['uqty'], 2 );
		        		
		        		$datas['details'][$i]['uprice'] = round_to( $datas['details'][$i]['uprice'], 5, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['total_amount'], 2, true );
		        		$datas['details'][$i]['avg_price'] = round_to( $datas['details'][$i]['avg_price'], 5, true );
		        		$total_amount+= $datas['details'][$i]['total_amount'];

		        		if( $datas['status'] >= 9 )
		        		{
		        			$datas['details'][$i]['final_amount'] = round_to( $item['uqty'] * $datas['details'][$i]['avg_price'], 2, true );
		        			$final_amount+= $datas['details'][$i]['final_amount'];
		        		}
		        	}

		        	if( $isView && !$hides )
		        	{
		        		$final = [];
			        	$final['prdt_name'] = '<strong>TOTAL:</strong>';
			        	$final['total_amount'] = '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
			        	if( $final_amount ) $final['final_amount'] = '<strong>'.round_to( $final_amount, 2, true ).'</strong>';
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
		        	'foc' => 'FOC',
		        	'uprice' => 'Price',
		        	'avg_price' => 'Avg Price',
		        	'total_amount' => 'Total Amt',
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
			do_action( 'wcwh_templating', 'form/purchaseOrder-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/purchaseOrder-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/purchaseOrder-row.php', $this->tplName['row'] );
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

		do_action( 'wcwh_templating', 'export/export-po.php', $this->tplName['export'], $args );
	}

	public function po_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['po'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
		);

		do_action( 'wcwh_templating', 'form/purchaseOrder-inv-form.php', $this->tplName['po'], $args );
	}


	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/purchaseOrderListing.php" ); 
			$Inst = new WCWH_PurchaseOrder_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#po' => [ 'width' => '120px' ],
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

			$metas = [ 'remark', 'purchase_doc', 'ref_doc_id', 'ref_doc', 'ref_doc_type', 'invoice', 'supplier_company_code', 'payment_method' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'child_doc'=> [ 'purchase_credit_note', 'purchase_debit_note' ],'meta'=>$metas ], [], $limit );
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