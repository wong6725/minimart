<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_GoodReceive_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-receive.php" ); 

if ( !class_exists( "WCWH_GoodReceive_Controller" ) ) 
{

class WCWH_GoodReceive_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_good_receive";

	public $Notices;
	public $className = "GoodReceive_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newGR',
		'row' => 'rowGR',
		'expiryrow' => 'rowExpiry',
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
		$this->Logic = new WCWH_GoodReceive_Class( $this->db_wpdb );
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
						$prdt_ids = [];
						foreach( $datas['detail'] as $i => $row )
						{
							$prdt_ids[] = $row['product_id'];
						}

						$prdts = [];
						$items = apply_filters( 'wcwh_get_item', [ 'id'=>$prdt_ids ], [], false, [ 'usage'=>1, 'uom'=>1, 'isUnit'=>1 ] );
						if( $items )
						{
							foreach( $items as $i => $item )
							{
								$prdts[ $item['id'] ] = $item;
							}
						}

						$ref_items = [];
						foreach( $datas['detail'] as $i => $row )
						{
							if( isset( $row['ref_item_id'] ) && $row['ref_item_id'] > 0 )
							{
								$ref_items[ $row['ref_item_id'] ]['bqty']+= $row['bqty'];
								$ref_items[ $row['ref_item_id'] ]['bunit']+= $row['bunit'];
								$ref_items[ $row['ref_item_id'] ]['ref_bal']+= $row['ref_bal'];
							}

							$row['foc'] = ( $row['foc'] )? $row['foc'] : 0;
							if( ( $row['bqty'] - $row['foc'] ) && 
								( is_null( $row['total_amount'] ) || $row['total_amount'] == '' ) && 
								( is_null( $row['uprice'] ) || $row['uprice'] == '' ) )
							{
								$succ = false;
								$this->Notices->set_notice( 'Please fill in either Total Amount or Unit Price.', 'warning' );
								break;
							}

							if( $prdts[ $row['product_id'] ] && $prdts[ $row['product_id'] ]['required_unit'] )
							{
								if( ! $row['bunit'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'Some item(s) required Metric(kg/l).', 'warning' );
									break;
								}
							}
						}

						if( !empty( $ref_items ) )
						{
							foreach( $ref_items as $ref_item_id => $row )
							{
								if( isset( $row['ref_bal'] ) && $row['bqty'] > $row['ref_bal'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'Item Qty could not exceed reference.', 'warning' );
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
					if( !empty( $header['invoice'] ) )
					{
						$ft = [ 'invoice'=>$header['invoice'], 'supplier_company_code'=>$header['supplier_company_code'] ];
						if( $header['doc_id'] ) $ft['not_doc_id'] = $header['doc_id'];
						$find_inv = $this->Logic->get_header( $ft, [], false, [ 'usage'=>1, 'meta'=>[ 'invoice', 'supplier_company_code' ] ] );
						if( $find_inv && count( $find_inv ) > 0 )
						{
							$succ = false;
							$this->Notices->set_notice( "Invoice: {$header['invoice']} is repeated.", 'warning' );
						}
					}

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

							$metas = get_document_meta( $ref_header['doc_id'] );
							$ref_header = $this->combine_meta_data( $ref_header, $metas );

							if( !empty( $ref_header['delivery_warehouse_id'] ) ) 
								$header['delivery_warehouse_id'] = $ref_header['delivery_warehouse_id'];
							
							if( in_array( $ref_header['doc_type'], [ 'delivery_order' ] ) )
							{
								$header['delivery_warehouse_id'] = $header['ref_warehouse'];
								if( $ref_header['direct_issue'] ) 
								{
									$header['direct_issue'] = $ref_header['direct_issue'];
									$header['client_company_code'] = $ref_header['client_company_code'];
								}

								if( $ref_header['purchase_doc'] && $ref_header['purchase_warehouse_id'] )
								{
									$pr = $this->Logic->get_header( [ 
										'docno'=>$ref_header['purchase_doc'], 
										'warehouse_id'=>$ref_header['purchase_warehouse_id'], 
										'doc_type'=>'purchase_request' 
									], [], true, [ 'posting'=>1 ] );
									if( $pr )
									{
										$header['purchase_request_doc_id'] = $pr['doc_id'];
									}
								}
							}

							$ref_detail = $this->Logic->get_detail( [ 'doc_id'=>$header['ref_doc_id'] ], [], false, [ 'usage'=>1 ] );
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

							if( $this->setting[ $this->section_id ]['no_kg'] )
							{
								$args = [ 'isMetric'=>'yes' ];
								if( $this->setting[ $this->section_id ]['no_kg_excl_cat'] )
									$args[ 'isMetricExclCat' ] = $this->setting[ $this->section_id ]['no_kg_excl_cat'];

								$found = apply_filters( 'wcwh_get_item', [ 'id'=>$row['product_id'] ], [], true, $args );
								if( $found )
								{
									unset( $detail[$i] );
									continue;
								}
							}

							if( $header['ref_doc_id'] > 0 )
							{
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

								if( $ref_row['total_amount'] > 0 )
								{
									$detail[$i]['total_amount'] = round_to( $ref_row['total_amount'] / $ref_row['bqty'] * $row['bqty'], 2 );
									$detail[$i]['uprice'] = round_to( $ref_row['total_amount'] / $ref_row['bqty'], 5 );
								}
								else
								{
									$detail[$i]['uprice'] = ( $ref_row['uprice'] > 0 )? $ref_row['uprice'] : $detail[$i]['uprice'];
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
							
							if( empty( $detail[$i]['prod_expiry'] ) && $this->setting[ $this->section_id ]['use_expiry'] ) 
							{
								if ( !class_exists( "WCWH_ItemExpiry_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item-expiry.php" );
								$Inst = new WCWH_ItemExpiry_Class();
								$now_date = date('Y-m-d', time());
								$expiry = $Inst->get_expiry( $now_date, $detail[$i]['product_id'] );
								if( $expiry ) $detail[$i]['prod_expiry'] = $expiry;
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

							$exist = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [] );
							if( in_array( $action, [ 'post' ] ) && $exist['status'] >= 6 ) continue;

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

							if( $succ && in_array( $action, [ 'post', 'unpost' ] ) )
							{
								$succ = $this->direct_issue_handler( $result['id'], $action );
							}
							if( $succ && in_array( $action, [ 'post' ] ) )
							{
								$succ = $this->automate_sales_handler( $result['id'], $action );
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

	public function direct_issue_handler( $doc_id = 0, $action = '' )
	{
		if( ! $this->setting[ $this->section_id ]['use_direct_issue'] ) return true;
		if( ! $doc_id || ! $action ) return false;

		if ( !class_exists( "WCWH_GoodIssue_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-issue.php" ); 
		$Inst = new WCWH_GoodIssue_Class( $this->db_wpdb );
		$Inst->setUpdateUqtyFlag( false );

		$direct_issue = get_document_meta( $doc_id, 'direct_issue', 0, true );
		if( ! $direct_issue ) return true;

		$succ = false;
		$issue_type = 'direct_consume';
		
		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'good_receive' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					if( ! $doc_header['direct_issue'] ) return true;

					$header = [
						'warehouse_id' => $this->warehouse['code'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'good_issue_type' => $issue_type,
						'parent' => $doc_header['doc_id'],
						'hstatus' => 9,
						'client_company_code' => ( $doc_header['client_company_code'] )? $doc_header['client_company_code'] : $this->setting[ $this->section_id ]['direct_issue_client'],
						'ref_doc_type' => $doc_header['doc_type'],
						'ref_doc_id' => $doc_header['doc_id'],
						'ref_doc' => $doc_header['docno'],
						'direct_issue' => $doc_header['direct_issue'],
					];
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$uprice = ( $row['uprice'] )? $row['uprice'] : round_to( $row['total_amount'] / $row['bqty'], 5 );
							$detail[] = [
								'product_id' => $row['product_id'],
								'bqty' => $row['bqty'],
								'bunit' => $row['bunit'],
								'sprice' => $uprice,
								'ucost' => $uprice,
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
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                    $succ = true;
		                }
					}
				}
			break;
			case 'unpost':
				$gi_header = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'good_issue', 'status'=>9 ], [], true, [] );
				if( $gi_header )
				{
					$header = [ 'doc_id'=>$gi_header['doc_id'] ];
					$result = $Inst->child_action_handle( 'trash', $header, [] );
	                if( ! $result['succ'] )
	                {
	                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
	                }
	                else
	                {
	                    $succ = true;
	                }
				}
			break;
		}

		return $succ;
	}

	public function automate_sales_handler( $doc_id = 0, $action = '' )
	{
		if( ! $this->setting[ $this->section_id ]['use_auto_sales'] ) return true;
		if( ! $doc_id || ! $action ) return false;

		$automate_client_code = get_document_meta( $doc_id, 'client_automate_sale', 0, true );
		if( ! $automate_client_code ) return true;

		if ( !class_exists( "WCWH_SaleOrder_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
		$Inst = new WCWH_SaleOrder_Controller();
		$Inst->set_warehouse( $this->warehouse );

		$succ = false;
		$margining_id = 'wh_good_receive_sale_order_automate';

		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'good_receive' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					if( ! $doc_header['client_automate_sale'] ) return true;

					$header = [
						'warehouse_id' => ( $this->warehouse['code'] )? $this->warehouse['code'] : $doc_header['warehouse_id'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'parent' => $doc_header['doc_id'],
						'client_company_code' => $doc_header['client_automate_sale'],
						'ref_doc_type' => $doc_header['doc_type'],
						'ref_doc_id' => $doc_header['doc_id'],
						'ref_doc' => $doc_header['docno'],
						'ref_status' => $doc_header['status'],
						'remark' => $doc_header['remark'],
						'gr_invoice' => $doc_header['invoice'],
						'gr_po' => $doc_header['ref_doc'],
						'automate_sale' => 1,
					];

					$margining = [];
					if( $this->setting['general']['use_margining'] )
					{	
						$margining = apply_filters( 'wcwh_get_margining', $header['warehouse_id'], $margining_id, $header['client_company_code'], $header['doc_date'] );
						//pd($margining);exit;
					}
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$t_amt = $row['total_amount'];
							$price = $row['uprice'];

							//margining recalc
							if( !empty( $margining ) && ( $margining['margin'] > 0 || $margining['margin'] < 0 ) )
							{
								$t_amt = round( $t_amt / ( 1 - ( $margining['margin'] / 100 ) ), 2 );
								$price = round( $price / ( 1 - ( $margining['margin'] / 100 ) ), 2 );

								if( $t_amt ) 
									$uprice = round_to( $t_amt / $row['bqty'], 5 );
								else
									$uprice = round_to( $price, 5 );
								
								$rn = ( $margining['round_nearest'] != 0 )? abs( $margining['round_nearest'] ) : 0.01;
								switch( $margining['round_type'] )
								{	
									case 'ROUND':
										$uprice = round( $uprice / $rn ) * $rn;
									break;
									case 'CEIL':
										$uprice = ceil( $uprice / $rn ) * $rn;
									break;
									case 'FLOOR':
										$uprice = floor( $uprice / $rn ) * $rn;
									break;
									default:
										$uprice = $uprice;
									break;
								}
							}

							$detail[] = [
								'product_id' => $row['product_id'],
								'bqty' => $row['bqty'],
								'bunit' => $row['bunit'],
								'ref_doc_id' => $row['doc_id'],
								'ref_item_id' => $row['item_id'],
								'cprice' => $uprice? $uprice : 0,
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
				$nxt_header = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'sale_order' ], [], true, ['usage'=>1] );
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


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'], $this->warehouse );
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'invoice', 'supplier_name', 'remark' ], 'New Goods Receipt by Document (PO/DO if any)' );
	        echo '<div id="good_receive_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'good_receive_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#good_receive_action"'], 'class'=>['select2','triggerChange'], 
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
					$x = ( $this->setting[ $this->section_id ]['use_ref_only'] )? 'display-none' : '';
			?>
				<button id="good_receive_action" class="<?php echo $x; ?> btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Goods Receipt"
					data-title="<?php echo $actions['save'] ?> Goods Receipt" 
					data-action="good_receive_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#good_receive_reference" 
				>
					<?php echo $actions['save'] ?> Goods Receipt
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_reference_doc( $doc_id = 0, $title = '', $doc_type = 'purchase_order' )
	{
		if( ! $doc_id || ! $doc_type ) return;

		switch( $doc_type )
		{
			case 'purchase_order':
				if( ! class_exists( 'WCWH_PurchaseOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/purchaseOrderCtrl.php" ); 
				$Inst = new WCWH_PurchaseOrder_Controller();
			break;
			case 'delivery_order':
				if( ! class_exists( 'WCWH_DeliveryOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
				$Inst = new WCWH_DeliveryOrder_Controller();
			break;
			case 'block_action':
				if( ! class_exists( 'WCWH_BlockAction_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/blockActionCtrl.php" ); 
				$Inst = new WCWH_BlockAction_Controller();
			break;
			case 'sale_return':
				if( ! class_exists( 'WCWH_SaleReturn_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/saleReturnCtrl.php" ); 
				$Inst = new WCWH_SaleReturn_Controller();
			break;
			case 'sale_credit_note':
				if( ! class_exists( 'WCWH_SaleCDNote_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/saleCDNoteCtrl.php" ); 
				$Inst = new WCWH_SaleCDNote_Controller();
			break;
			case 'issue_return':
				if( ! class_exists( 'WCWH_IssueReturn_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/issueReturnCtrl.php" ); 
				$Inst = new WCWH_IssueReturn_Controller();
			break;
			case 'purchase_credit_note':
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
					case 'good_issue':
						if( current_user_cans( [ 'access_wh_good_issue' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_GoodIssue_Controller();
							$titles[ $doc_type ] = "Goods Issue";
						}
					break;
					case 'good_return':
						if( current_user_cans( [ 'access_wh_good_return' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/goodReturnCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_GoodReturn_Controller();
							$titles[ $doc_type ] = "Goods Return";
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
			'rowexpiryTpl'	=> $this->tplName['expiryrow'],
			'wh_id'		=> $this->warehouse['id'],
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( is_array( $id ) && $id )
		{
			$items = apply_filters( 'wcwh_get_item', [ 'id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
			if( $items )
			{
				$details = array();
				foreach( $items as $i => $item )
				{	
					$details[$i] = array(
						'id' =>  $item['id'],
						'bqty' => '',
						'product_id' => $item['id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['name'], 'code'=>$item['code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
						],
					);
				}
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
				$args['data']['invoice'] = $header['invoice'];
				$args['data']['client_automate_sale'] = $header['client_automate_sale'];

				$args['data']['source_doc_type'] = $header['ref_doc_type'];

				if( in_array( $header['doc_type'], ['delivery_order'] ) )
				{
					$args['data']['ref_post_date'] = $header['post_date'];
					$args['data']['doc_date'] = $header['doc_date'];
					$args['data']['delivery_doc'] = $header['docno'];
					$args['data']['supplier_company_code'] = $header['supplier_company_code'];
					if( $header['purchase_doc'] ) $args['data']['purchase_doc'] = $header['purchase_doc'];
				}
				else if( in_array( $header['doc_type'], ['purchase_order'] ) )
				{
					$args['data']['purchase_doc'] = $header['docno'];
					$args['data']['supplier_company_code'] = $header['supplier_company_code'];
					if( $header['delivery_doc'] ) $args['data']['delivery_doc'] = $header['delivery_doc'];
				}

				$args['NoAddItem'] = true;
				$ref_id = $header['doc_id'];
			}

			$filters = [ 'doc_id'=>$id ];
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );
			
			if( $items )
			{
				$details = array();
				
				//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//				
				$from_date = strtotime('-2 weeks', current_time( 'timestamp' ));
                $from_date = date('Y-m-d H:i:s', $from_date);
                $row_style ='style="background-color: #F5FFF5"';
				//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
				
				foreach( $items as $i => $item )
				{	
					$metas = get_document_meta( $id, '', $item['item_id'] );
					$item = $this->combine_meta_data( $item, $metas );
					
					$uprice = 0;
					if( in_array( $header['doc_type'], ['delivery_order'] ) )
					{
						$uprice = ( $item['sprice'] )? $item['sprice'] : 0;
					}
					else if( in_array( $header['doc_type'], ['purchase_order'] ) )
					{
						$uprice = ( $item['avg_price'] )? $item['avg_price'] : $item['uprice'];
					}
					else if( in_array( $header['doc_type'], ['sale_credit_note', 'sale_debit_note'] ) )
					{
						$uprice = ( $item['sprice'] )? $item['sprice'] : $item['def_sprice'];
					}
					
					//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
					$new_prdt = apply_filters( 'wcwh_get_item', [ 'id'=>$item['product_id'], 'from_date'=>$from_date ], [], true, [] );
					$row_styling = ( $new_prdt )? $row_style : '';
					
					$details[] = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
						],
						'bqty' => ( $item['bqty'] - $item['uqty'] ),
						'bunit' => ( $item['bunit'] - $item['uunit'] ),
						'foc' => $item['foc'],
						'uprice' => $uprice,
						'total_amount' => isset( $item['total_amount'] )? $item['total_amount'] : $item['line_total'],
						'ref_bqty' => $item['bqty'],
						'ref_bal' => ( $item['bqty'] - $item['uqty'] ),
						'ref_doc_id' => $item['doc_id'],
						'ref_item_id' => $item['item_id'],
						'row_styling' => $row_styling,
					);
					//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
				}
				$args['data']['details'] = $details;
			}
		}

		if( $args['data']['ref_doc_id'] )
			$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/goodReceive-form.php', $args );
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
			'rowexpiryTpl'	=> $this->tplName['expiryrow'],
			'get_content' => $getContent,
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

				$datas['source_doc_type'] = $this->Logic->get_document_meta( $datas['ref_doc_id'], 'ref_doc_type', 0, true );

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1, 'transact' => 1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1 ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				if( $datas['ref_doc_id'] ) $args['NoAddItem'] = true;

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_good_receive'] ) )
				{
					$hides = [ 'uprice', 'total_amount' ];
				}
		        	
		        if( $datas['details'] )
		        {
		        	$total_amount = 0; $doc_items = [];
					//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
					$from_date = strtotime('-2 weeks', current_time( 'timestamp' ));
                	$from_date = date('Y-m-d H:i:s', $from_date);
                	$row_style ='style="background-color: #F5FFF5"';
					//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
		        	foreach( $datas['details'] as $i => $item )
		        	{
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
						$new_prdt = apply_filters( 'wcwh_get_item', [ 'id'=>$item['product_id'], 'from_date'=>$from_date ], [], true, [] );
						if( $new_prdt ) $datas['details'][$i]['row_styling'] = $row_style;
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['item_name'] = $item['prdt_name'];
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];

		        		$doc_items[ $item['ref_item_id'] ]['bqty'] = $item['ref_bqty'];
		        		$doc_items[ $item['ref_item_id'] ]['uqty'] = $item['ref_uqty'];
		        		$doc_items[ $item['ref_item_id'] ]['bunit'] = $item['ref_bunit'];
		        		$doc_items[ $item['ref_item_id'] ]['uunit'] = $item['ref_uunit'];
		        		$doc_items[ $item['ref_item_id'] ]['used_qty']+= $item['bqty'];
		        		if( ! isset( $doc_items[ $item['ref_item_id'] ]['idx'] ) )
		        		{
		        			$doc_items[ $item['ref_item_id'] ]['idx'] = $i;
		        			$doc_items[ $item['ref_item_id'] ]['item_id'] = $item['item_id'];
		        		}
		        		else
		        		{
		        			$datas['details'][$i]['parent_item_id'] = $doc_items[ $item['ref_item_id'] ]['item_id'];
		        			$datas['details'][$i]['fi'] = $doc_items[ $item['ref_item_id'] ]['idx'];
		        		}

						$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['serial'] = $this->column_serial( $datas['details'][$i], $datas, $Inst );

		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2, true );
		        		$datas['details'][$i]['lqty'] = round_to( $item['bqty'] - $item['uqty'], 2 );
						$datas['details'][$i]['outstanding'] = round_to( $item['bqty'] - $item['deduct_qty'], 2 );
		        		$datas['details'][$i]['uprice'] = round_to( $datas['details'][$i]['uprice'], 5, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['total_amount'], 2, true );

		        		$total_amount+= $datas['details'][$i]['total_amount'];
		        	}
		        	
		        	if( !empty( $doc_items ) )
		        	{
		        		foreach( $doc_items as $item )
		        		{
		        			$datas['details'][ $item['idx'] ]['ref_bal'] = $item['bqty'] - $item['uqty'] + $item['used_qty'];
		        		}
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
		        		'serial' => 'Barcode',
		        		'bqty' => 'Qty',
		        		'bunit' => 'Metric (kg/l)',
		        		'uprice' => 'Price',
		        		'total_amount' => 'Total Amt',
		        		'prod_expiry' => 'Expiry',
		        		'lqty' => 'Leftover',
						'outstanding' => 'Outstanding',
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
			do_action( 'wcwh_templating', 'form/goodReceive-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/goodReceive-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}
		public function column_serial( $item, $datas, $Inst )
		{
			$actions = array();

			$batch_serial = ( $item['batch_serial'] )? $item['batch_serial'] : $item['prdt_serial'].get_datime_fragment( $datas['doc_date'], 'ymd' );
		
			if( $item['prdt_serial'] ) 
			{	
				$data = [ 'dataitem'=>$item['item_name'], 'dataserial'=>$batch_serial ];
				
				$actions['qr'] = $Inst->get_action_btn( $item, 'qr', [ 'serial'=>$batch_serial, 'tpl'=>'product_label', 'datas'=>$data ] );
				$actions['barcode'] = $Inst->get_action_btn( $item, 'barcode', [ 'serial'=>$batch_serial, 'tpl'=>'product_label', 'datas'=>$data ] );
			}

			return sprintf( '%1$s %2$s', '<strong>'.$item['batch_serial'].'</strong>', $Inst->row_actions( $actions, true ) ); 
		}

	public function view_row()
	{
		$args = [
			'setting'	=> $this->setting,
			'section' => $this->section_id,
			'expiryrow' => $this->tplName['expiryrow'],
		];
		do_action( 'wcwh_templating', 'segment/goodReceive-row.php', $this->tplName['row'], $args );
	}

	public function view_expiry_row()
	{
		$args = [
			'setting'	=> $this->setting,
			'section' => $this->section_id,
		];
		do_action( 'wcwh_templating', 'segment/goodReceive-expiryRow.php', $this->tplName['expiryrow'], $args );
	}

	public function print_tpl()
	{
		$tpl_code = "gritemlabel0001";
		$tpl = apply_filters( 'wcwh_get_suitable_template', $tpl_code );
		if( $tpl )
		{
			do_action( 'wcwh_templating', $tpl['tpl_path'].$tpl['tpl_file'], 'product_label', $args );
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
			include_once( WCWH_DIR . "/includes/listing/goodReceiveListing.php" ); 
			$Inst = new WCWH_GoodReceive_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

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

			$metas = [ 'remark', 'delivery_doc', 'invoice', 'ref_doc', 'ref_doc_id', 'ref_doc_type', 'purchase_doc', 'purchase_request_doc_id', 'supplier_company_code' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas, 'transact_out'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}