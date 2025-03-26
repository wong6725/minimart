<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_POSCDN_Class" ) ) include_once( WCWH_DIR . "/includes/classes/pos-cdn.php" ); 
if ( !class_exists( "WCWH_Customer_Class" ) ) include_once( WCWH_DIR . "/includes/classes/customer.php" ); 
if ( !class_exists( "WCWH_Item_Class" ) ) include_once( WCWH_DIR . "/includes/classes/item.php" ); 

if ( !class_exists( "WCWH_POSCDN_Controller" ) ) 
{

class WCWH_POSCDN_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_pos_cdn";

	public $Notices;
	public $className = "POSCDN_Controller";

	public $Logic;
	public $Customer;
	public $Item;

	public $tplName = array(
		'new' => 'newPOSCDN',
		'row' => 'rowPOSCDN',
	);

	protected $warehouse = array();
	protected $view_outlet = false;

	protected $doc_prefix = '-CDN';
	protected $doc_len = 2;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_POSCDN_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );

		$this->Customer = new WCWH_Customer_Class( $this->db_wpdb );
		$this->Item = new WCWH_Item_Class( $this->db_wpdb );
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
					if( ! $datas['detail'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'update':
					if( ! $datas['detail'] || ! $datas['header']['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'restore':
				case 'delete':
				case 'post':
				case 'unpost':
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

	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		$header = $datas['header'];
		$detail = $datas['detail'];

		$id = ($header['id'])? $header['id'] : $datas['id'];
		$now = current_time( 'mysql' );

		//Check Accounting Period 
		$validDate = $this->document_account_period_handle( $id, $header['order_date'], '', $action );
		//if( current_user_cans( ['manage_options'] ) ) $validDate = true;
		if( ! $validDate )
		{
			if( ! in_array( $action, [ 'delete' ] ) )
			{
				if( $this->Notices ) $this->Notices->set_notice( "Not Allowed. Date is out of Accounting Period!", "warning", $this->_doc_type."|document_action_handle" );

				return false;
			} 
		}

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

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
					//pd($header);pd($detail);

					$ref_header = [];
					if( $header['ref_doc_id'] )
					{
						$filters = [ 'id' => $header['ref_doc_id'] ];
						$ref_header = $this->Logic->get_infos( $filters, [], true, [ 'meta'=>[ 'cdn_running_no' ] ] );
					}

					$exist = [];
					if( $header['id'] )
					{
						$filter = [ 'id'=>$header['id'] ];
						$exist = $this->Logic->get_infos( $filter, [], true, [] );
					}

					if( empty( $header['wh_id'] ) )
					{
						if( $header['register'] ) $register = wc_pos_get_register( $header['register'] );
						if( $register->detail ) $reg_setting = json_decode( $register->detail );
						if( $reg_setting->assigned_warehouse ) $header['wh_id'] = ( $ref_header['wh_id'] )? $ref_header['wh_id'] : $reg_setting->assigned_warehouse;
					}

					$setting = $this->setting['pos'];
					if( $succ )
					{
						if( ! $exist )	//Create CDN
						{
							$order = wc_create_order();
							$order_id = $order->get_id();

							$ref_header['cdn_running_no'] = ( $ref_header['cdn_running_no'] )? $ref_header['cdn_running_no'] : get_post_meta( $ref_header['id'], 'cdn_running_no', true );
							if( ! $ref_header['cdn_running_no'] ) $ref_header['cdn_running_no'] = 0;
							$ref_header['cdn_running_no'] = $ref_header['cdn_running_no'] + 1;

							$header['order_no'] = $ref_header['order_no'].$this->doc_prefix.str_pad( $ref_header['cdn_running_no'], $this->doc_len, "0", STR_PAD_LEFT );
							update_post_meta( $ref_header['id'], 'cdn_running_no', $ref_header['cdn_running_no'] );

							$order->order_number = $header['order_no'];
							update_post_meta( $order_id, '_order_number', $header['order_no'] );
							update_post_meta( $order_id, 'wc_pos_warehouse_id', $header['wh_id'] );
							update_post_meta( $order_id, '_credit_debit', 1 );
							update_post_meta( $order_id, 'ref_doc_id', $header['ref_doc_id'] );

							$tool_request_id = $header['tool_request_id'] = $ref_header['tool_request_id'];
							if( $ref_header['tool_request_id'] ) update_post_meta( $order_id, 'tool_request_id', $tool_request_id );
							if( $ref_header['payment_method'] ) $order->set_payment_method( $ref_header['payment_method'] );
							else $order->set_payment_method( 'cash' );
							if( $ref_header['payment_method_title'] ) $order->set_payment_method_title( $ref_header['payment_method_title'] );
							else $order->set_payment_method_title( 'Cash' );

							$the_post = [
							    'ID' => $order_id,
								'post_excerpt' => $header['order_no'],
							];
							wp_update_post( $the_post );
						}
						else 	//Update CDN
						{
							$order = new WC_Order( $exist['id'] );
							$order_id = $order->get_id();

							$tool_request_id = $header['tool_request_id'] = get_post_meta( $order_id, 'tool_request_id', true );
						}

						$date = date( 'Y-m-d', strtotime( $now ) );
						$time = date( 'H:i:s', strtotime( $now ) );
						$header['order_date'] = ( $header['order_date'] )? date_formating( $header['order_date'], $time ) : $now;
						
						//customer change
						$new_customer_id = -1;
						$customer = [];
						if( $header['customer_id'] )
						{
							if( $header['customer_id'] > 0 )
							{
								$filter = [ 'id' => $header['customer_id'] ];
								$customer = $this->Customer->get_infos( $filter, [], true );
								$user = $this->Customer->get_user( $header['customer_id'] );
							}
							
							update_post_meta( $order_id, '_customer_code', !empty( $customer['code'] )? $customer['code'] : '' );
							update_post_meta( $order_id, '_customer_sapuid', !empty( $customer['uid'] )? $customer['uid'] : '' );
							update_post_meta( $order_id, '_customer_serial', !empty( $customer['serial'] )? $customer['serial'] : '' );
							update_post_meta( $order_id, '_customer_user', !empty( $user['ID'] )? $user['ID'] : '' );
							update_post_meta( $order_id, 'customer_id', !empty( $customer['id'] )? $customer['id'] : '' );
						}
						
						update_post_meta( $order_id, 'order_comments', $header['order_comments'] );
						update_post_meta( $order_id, 'wc_pos_id_register', $header['register'] );
						update_post_meta( $order_id, '_pos_session_id', $header['session_id'] );

						$the_post = [
						    'ID' => $order_id,
						    'post_date' => $header['order_date'],
						];
						if( $header['customer_id'] ) 
						{
							$the_post['post_content'] = ( $header['customer_id'] )? $header['customer_id'] : 0;
							$new_customer_id = ( $header['customer_id'] )? $header['customer_id'] : 0;
						}
						wp_update_post( $the_post );

						//prepare details level
						//get tool request items
						$tr_details = [];
						if( $tool_request_id )
						{
							$tr_d = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$tool_request_id ], [], false, [ 'usage'=>1 ] );
							if( $tr_d )
							{
								foreach( $tr_d as $z => $tr_row )
								{
									$tr_details[ $tr_row['product_id'] ] = $tr_row;
								}
							}
						}

						//get exists items
						$filter = [ 'order_id'=>$order_id ];
						$exist_details = $this->Logic->get_details( $filter, [], false );
						$exist_detail = [];
						if( $exist_details )
						{
							foreach( $exist_details as $i => $vals )
							{
								$exist_detail[ $vals['id'] ] = $vals;
							}
						}

						//line items handling
						foreach( $detail as $i => $item )
						{
							$order_item_id = $item['item_id'];

							$prdt = $this->Item->get_infos( [ 'id'=>$item['product_id'] ], [], true, [ 'meta'=>['virtual', 'returnable_item', 'is_returnable', 'add_gt_total'] ] );

							if( ! $order_item_id )// add
							{	
								$wc_prdt = $this->Item->get_product( $item['product_id'], true );
								
								$price = $uprice = ( !isset( $item['uprice'] ) || strlen( $item['uprice'] ) <= 0 )? 0 : $item['uprice'];
								$price = ( !isset( $item['price'] ) || strlen( $item['price'] ) <= 0 )? $price : $item['price'];

								$order_item_id = wc_add_order_item( $order_id, array(
									'order_item_name' => $prdt['name'],
									'order_item_type' => 'line_item',
								));

								wc_add_order_item_meta( $order_item_id, '_items_id', $prdt['id'] );
								wc_add_order_item_meta( $order_item_id, '_product_id', $wc_prdt['ID'] );
								wc_add_order_item_meta( $order_item_id, '_uom', $prdt['_uom_code'] );
								wc_add_order_item_meta( $order_item_id, '_uprice', $uprice );
								if( $item['metric'] > 0 )
								{
									$unit = $item['metric'] / $item['qty'];
									wc_add_order_item_meta( $order_item_id, '_unit', $unit );
									$price = round_to( $uprice * $unit, 1 );
									$price = ( ( !isset( $item['price'] ) ) )? $price : $item['price'];
								}
								wc_add_order_item_meta( $order_item_id, '_price', $price );
								wc_add_order_item_meta( $order_item_id, '_price_code', $prices['price_code'] );
								wc_add_order_item_meta( $order_item_id, '_qty', $item['qty'] );

								$total = round_to( ( ($item['qty'] == 0)? 1 : abs($item['qty']) ) * $price, 2 );
								wc_add_order_item_meta( $order_item_id, '_line_subtotal', $total );
								wc_add_order_item_meta( $order_item_id, '_line_total', $total ); 

								if( $tool_request_id && $tr_details[ $prdt['id'] ]['item_id'] )
								{
									wc_add_order_item_meta( $order_item_id, '_tool_item_id', $tr_details[ $prdt['id'] ]['item_id'] );
								}
							}
							else //update
							{
								$price = $uprice = ( !isset( $item['uprice'] ) || strlen( $item['uprice'] ) <= 0 )? 0 : $item['uprice'];
								$price = ( !isset( $item['price'] ) || strlen( $item['price'] ) <= 0 )? $price : $item['price'];
								
								wc_update_order_item_meta( $order_item_id, '_qty', $item['qty'] );
								
								wc_update_order_item_meta( $order_item_id, '_uprice', $uprice );
								wc_update_order_item_meta( $order_item_id, '_price', $price );

								if( $item['metric'] > 0 )
								{
									$unit = $item['metric'] / $item['qty'];
									wc_update_order_item_meta( $order_item_id, '_unit', $unit );
									$price = round_to( $uprice * $unit, 1 );
									$price = ( ( !isset( $item['price'] ) ) )? $price : $item['price'];

									wc_update_order_item_meta( $order_item_id, '_price', $price );
								}

								$total = round_to( ( ($item['qty'] == 0)? 1 : abs($item['qty']) ) * $price, 2 );
								wc_update_order_item_meta( $order_item_id, '_line_subtotal', $total );
								wc_update_order_item_meta( $order_item_id, '_line_total', $total ); 

								if( $tool_request_id && $tr_details[ $prdt['id'] ]['item_id'] )
								{
									$tool_item_id = wc_get_order_item_meta( $order_item_id, '_tool_item_id', true );
									if( ! $tool_item_id ) wc_add_order_item_meta( $order_item_id, '_tool_item_id', $tr_details[ $prdt['id'] ]['item_id'] );
								}

								unset( $exist_detail[ $order_item_id ] );
							}
						}
						//remove previous exist
						if( $exist_detail )
						{
							foreach( $exist_detail as $order_item_id => $item )
							{
								wc_delete_order_item( $order_item_id );
							}
						}

						$t = $order->calculate_totals();
						if( isset( $header['total'] ) && $header['total'] > 0 ) $t = $header['total'];
						update_post_meta( $order_id, '_order_total', $t );
						update_post_meta( $order_id, 'wc_pos_rounding_total', $t );

						if( isset( $header['amt_paid'] ) && is_numeric( $header['amt_paid'] ) ) 
						{
							update_post_meta( $order_id, 'wc_pos_amount_pay', $header['amt_paid'] );
							$exist['amt_paid'] = $header['amt_paid'];
						}
						if( isset( $header['amt_change'] ) && is_numeric( $header['amt_change'] ) ) 
						{
							update_post_meta( $order_id, 'wc_pos_amount_change', $header['amt_change'] );
							$exist['amt_change'] = $header['amt_change'];
						}

						if( $new_customer_id >= 0 )	//change of customer; either have or not have
						{
							if( $new_customer_id > 0 )	//from no to have or change 
							{
								$user_credits = apply_filters( 'wc_credit_limit_get_client_credits', $new_customer_id );
								update_post_meta( $order_id, '_total_creditable', $user_credits['total_creditable'] );

								$c = $t;
								if( isset( $header['total_credit'] ) && is_numeric( $header['total_credit'] ) ) $c = $header['total_credit'];
								update_post_meta( $order_id, 'wc_pos_credit_amount', $c );

								if( $exist['amt_paid'] || $exist['amt_change'] )
								{
									update_post_meta( $order_id, 'wc_pos_amount_pay', 0 );
									update_post_meta( $order_id, 'wc_pos_amount_change', 0 );
								}
							}
							else 	//same customer / no customer
							{
								update_post_meta( $order_id, '_total_creditable', 0 );
								update_post_meta( $order_id, 'wc_pos_credit_amount', 0 );

								update_post_meta( $order_id, 'wc_pos_amount_pay', $t );
								update_post_meta( $order_id, 'wc_pos_amount_change', 0 );
							}
						}
						else 	//no change of customer
						{
							update_post_meta( $order_id, 'wc_pos_credit_amount', 0 );

							update_post_meta( $order_id, 'wc_pos_amount_pay', $t );
							update_post_meta( $order_id, 'wc_pos_amount_change', 0 );
						}
					}
					
					$order->calculate_totals();
					//$order->set_status( 'wc-processing', 'Order is created Manually' );
					$order->save();
				break;
				case "delete":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$order = new WC_Order($id);
							if ( !empty($order) && $succ ) 
							{ 
								$result = $order->update_status( 'cancelled' );
								if( !$result )
								{
									$succ = false;
									if( $this->Notices ) $this->Notices->set_notice( "Order Cancellation Failed", "warning" );
								}
								else
								{
									if( $datas['remark'] ) update_post_meta( $id, 'cancel_remark', $datas['remark'] );
								}
							}
						}
					}
				break;
				case "post":
					$setting = $this->setting['pos'];

					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$order_id = $id;
							$order = new WC_Order( $order_id );
							if ( !empty($order) && $succ ) 
							{
								$filter = [ 'id'=>$order_id ];
								$header = $this->Logic->get_infos( $filter, [], true, [] );
								if( ! $header ) continue;

								$wh_id = $header['wh_id'];
								$tool_request_id = $header['tool_request_id'];

								$filter = [ 'order_id'=>$order_id ];
								$detail = $this->Logic->get_details( $filter, [], false );
								if( ! $detail ) continue;

								$tool_request = array();
								$pos_transacts = array(); $has_pos_transact = false;
								foreach( $detail as $i => $item )
								{
									$order_item_id = $item['id'];
									if( ! $order_item_id ) continue;

									$prdt = $this->Item->get_infos( [ 'id'=>$item['item_id'] ], [], true, [ 'meta'=>['virtual', 'returnable_item', 'is_returnable', 'add_gt_total'] ] );

									if( $setting['price_log'] )
									{
										$header_item = [ 'doc_type'=>'sales', 'warehouse_id'=> $wh_id ];
										$price_log = [
											'sales_item_id'   	=> $order_item_id, 
											'order_id'   		=> $order_id, 
											'docno'				=> $header['order_no'],
											'warehouse_id' 		=> $wh_id, 
											'strg_id'			=> apply_filters( 'wcwh_get_system_storage', 0, $header_item, $item ),
											'customer' 			=> ( $header['customer_id'] )? $header['customer_id'] : 0,
											'sales_date' 		=> $header['order_date'], 
											'tool_id'			=> ( $tool_request_id )? $tool_request_id : 0,
											'prdt_id' 			=> $item['item_id'],
											'uom'				=> $item['uom'],
											'qty' 				=> $item['qty'],
											'unit'				=> ( $item['metric'] )? $item['qty'] * $item['metric'] : 0,
											'uprice' 			=> $item['uprice'],
											'price' 			=> $item['price'],
											'total_amount' 		=> $item['total'],
										];

										$price_logs = [];
										$price_logs[] = $price_log;
										$result = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'save' , $price_logs );
									}

									if( $prdt['returnable_item'] > 0 )
									{
										$it = apply_filters('wcwh_get_item', [ 'id'=>$prdt['returnable_item'] ], [], true, [ 'usage'=>1 ] );
										if( $it )
										{
											$rowb = array(
												'product_id' 		=> $it['id'],
												'uom_id'			=> $it['_uom_code'],
												'bqty' 				=> $item['qty'],
												'bunit'				=> ( $item['metric'] )? $item['qty'] * $item['metric'] : 0,
												'ref_doc_id' 		=> $order_id,
												'ref_item_id' 		=> $order_item_id,
												//Custom Field 
												'uprice' 			=> 0,
												'price'				=> 0,
												'total_amount' 		=> 0,
												'plus_sign'			=> '+',
											);	
											$pos_transacts[] = $rowb;
											$has_pos_transact = true;
										}
									}
									if( $prdt['is_returnable'] && $prdt['add_gt_total'] )
									{
										$gtd = get_option( 'gt_total', 0 );
										$gtd-= $item['qty'];
										update_option( 'gt_total', $gtd );
									}

									if( $tool_request_id && $item['tool_item_id'] )
									{
										$tool_request[] = [
											'item_id' => $item['tool_item_id'],
											'doc_id' => $tool_request_id,
											'product_id' => $item['item_id'],
											'qty' => $item['qty'],
											'plus_sign' => '+',
										];
									}
								}

								//customer
								if( $header['customer_id'] )
								{
									$filter = [ 'order_id'=>$order_id ];
									$exist_credit = $this->Logic->get_credit_registry( $filter, [], true );

									if( $exist_credit )
									{
										$credit_registry = [
											'user_id' => $header['customer_id'],
											'amount' => $header['total_credit'] * -1,
											'status' => 1,
										];
										$result = $this->Logic->update_credit_registry( [ 'order_id'=>$order_id ], $credit_registry );
									}
									else
									{
										$credit_registry = [
											'user_id' => $header['customer_id'],
											'order_id' => $order_id,
											'type' => ( $header['tool_request_id'] > 0 )? 'tools' : 'sales',
											'amount' => $header['total_credit'] * -1,
											'note' => '',
											'parent' => 0,
											'time' => $now
										];
										$result = $this->Logic->add_credit_registry( $credit_registry );
									}
								}

								if( $succ && $tool_request_id && $tool_request )
								{
									$tool_doc = [
										'doc_id' => $tool_request_id,
										'details' => $tool_request,
									];
									$succ = apply_filters( 'wcwh_tool_request_completion', $succ, $tool_doc );
								}

								if( $succ && $has_pos_transact )	// POS transactions (if any)
								{
									$reg_id = ( $header['register'] )? $header['register'] : get_post_meta( $order_id, 'wc_pos_id_register', true );
									$sess_id = ( $header['session_id'] )? $header['session_id'] : get_post_meta( $order_id, '_pos_session_id', true );
									$wh_id = ( $header['wh_id'] )? $header['wh_id'] : get_post_meta( $order_id, 'wc_pos_warehouse_id', true );;

									$filters = [
										'warehouse_id' => $wh_id,
										'register' => $reg_id,
										'session' => $sess_id,
										'receipt_id' => $order_id,
										'doc_type' => 'pos_transactions',
									];
									$pos_transact_exist = apply_filters( 'wcwh_get_doc_header', $filters, [], true, [ 'usage'=>1, 'meta'=>[ 'register', 'session', 'receipt_id' ] ] );
									if( $pos_transact_exist )
									{	
										$header_item = [ 'id' => $pos_transact_exist['doc_id'] ];
										$result = apply_filters( 'wcwh_pos_transaction' , 'unpost', $header_item , [] );
										if( ! $result['succ'] )
										{
											$succ = false;
										}
										else
										{
											$result = apply_filters( 'wcwh_pos_transaction' , 'delete', $header_item, [] );
											if( ! $result['succ'] )
											{
												$succ = false;
											}
										}
									}

									if( $succ && $pos_transacts )
									{
										$header_item = [ 
											'header' => [ 
												'warehouse_id'=>$wh_id, 
												'register'=>$reg_id, 
												'session'=>$sess_id, 
												'receipt'=>$header['order_no'], 
												'receipt_id' =>$order_id 
											],
											'detail' => $pos_transacts,
										];
										$result = apply_filters( 'wcwh_pos_transaction' , 'save', $header_item , $pos_transacts );
										if( ! $result['succ'] )
										{
											$succ = false;
										}
										else
										{
											$header_item = [ 'id'=>$result['id'] ];
											$result = apply_filters( 'wcwh_pos_transaction' , 'post', $header_item, [] );

											if( ! $result['succ'] )
											{
												$succ = false;
											}
										}
									}
								}

								$result = $order->update_status( 'processing' );
								if( !$result )
								{
									$succ = false;
									if( $this->Notices ) $this->Notices->set_notice( "Order Post Failed", "warning" );
								}

								$the_post = [
								    'ID' => $order_id,
								    'post_date' => $header['order_date'],
								];
								wp_update_post( $the_post );
							}
						}
					}
				break;
				case "unpost":
					$setting = $this->setting['pos'];

					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$order_id = $id;
							$order = new WC_Order( $order_id );
							if ( !empty($order) && $succ ) 
							{
								$filter = [ 'id'=>$order_id ];
								$header = $this->Logic->get_infos( $filter, [], true, [] );
								if( ! $header ) continue;

								$wh_id = $header['wh_id'];
								$tool_request_id = $header['tool_request_id'];

								$filter = [ 'order_id'=>$order_id ];
								$detail = $this->Logic->get_details( $filter, [], false );
								if( ! $detail ) continue;

								$tool_request = array();
								$pos_transacts = array(); $has_pos_transact = false;
								foreach( $detail as $i => $item )
								{
									$order_item_id = $item['id'];
									if( ! $order_item_id ) continue;

									$prdt = $this->Item->get_infos( [ 'id'=>$item['item_id'] ], [], true, [ 'meta'=>['virtual', 'returnable_item', 'is_returnable', 'add_gt_total'] ] );

									if( $setting['price_log'] )
									{
										$header_item = [ 'doc_type'=>'sales', 'warehouse_id'=> $wh_id ];
										$price_log = [
											'sales_item_id'   	=> $order_item_id, 
											'warehouse_id' 		=> $wh_id, 
											'strg_id'			=> apply_filters( 'wcwh_get_system_storage', 0, $header_item, $item ),
										];

										$price_logs = [];
										$price_logs[] = $price_log;
										$result = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'delete' , $price_logs );
									}

									if( $prdt['returnable_item'] > 0 )
									{
										$has_pos_transact = true;
									}
									if( $prdt['is_returnable'] && $prdt['add_gt_total'] )
									{
										$gtd = get_option( 'gt_total', 0 );
										$gtd+= $item['qty'];
										update_option( 'gt_total', $gtd );
									}

									if( $tool_request_id && $item['tool_item_id'] )
									{
										$tool_request[] = [
											'item_id' => $item['tool_item_id'],
											'doc_id' => $tool_request_id,
											'product_id' => $item['item_id'],
											'qty' => $item['qty'],
											'plus_sign' => '-',
										];
									}
								}

								//customer
								if( $header['customer_id'] )
								{
									$filter = [ 'order_id'=>$order_id ];
									$exist_credit = $this->Logic->get_credit_registry( $filter, [], true );

									if( $exist_credit )
									{
										$credit_registry = [
											'user_id' => $header['customer_id'],
											'amount' => $header['total_credit'] * -1,
											'status' => 0,
										];
										$result = $this->Logic->update_credit_registry( [ 'order_id'=>$order_id ], $credit_registry );
									}
								}

								if( $succ && $tool_request_id && $tool_request )
								{
									$tool_doc = [
										'doc_id' => $tool_request_id,
										'details' => $tool_request,
									];
									$succ = apply_filters( 'wcwh_tool_request_completion', $succ, $tool_doc );
								}

								if( $succ && $has_pos_transact )	// POS transactions (if any)
								{
									$reg_id = ( $header['register'] )? $header['register'] : get_post_meta( $order_id, 'wc_pos_id_register', true );
									$sess_id = ( $header['session_id'] )? $header['session_id'] : get_post_meta( $order_id, '_pos_session_id', true );
									$wh_id = ( $header['wh_id'] )? $header['wh_id'] : get_post_meta( $order_id, 'wc_pos_warehouse_id', true );;

									$filters = [
										'warehouse_id' => $wh_id,
										'register' => $reg_id,
										'session' => $sess_id,
										'receipt_id' => $order_id,
										'doc_type' => 'pos_transactions',
									];
									$pos_transact_exist = apply_filters( 'wcwh_get_doc_header', $filters, [], true, [ 'usage'=>1, 'meta'=>[ 'register', 'session', 'receipt_id' ] ] );
									if( $pos_transact_exist )
									{	
										$header_item = [ 'id' => $pos_transact_exist['doc_id'] ];
										$result = apply_filters( 'wcwh_pos_transaction' , 'unpost', $header_item , [] );
										if( ! $result['succ'] )
										{
											$succ = false;
										}
										else
										{
											$result = apply_filters( 'wcwh_pos_transaction' , 'delete', $header_item, [] );
											if( ! $result['succ'] )
											{
												$succ = false;
											}
										}
									}
								}

								$result = $order->update_status( 'pending' );
								if( !$result )
								{
									$succ = false;
									if( $this->Notices ) $this->Notices->set_notice( "Order UnPost Failed", "warning" );
								}

								$the_post = [
								    'ID' => $order_id,
								    'post_date' => $header['order_date'],
								];
								wp_update_post( $the_post );
							}
						}
					}
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
            if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }
        finally
        {
        	if( $succ )
                if( $transact ) wpdb_end_transaction( true, $this->db_wpdb );
            else 
                if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }

        $outcome['succ'] = $succ;
		
		return $outcome;
	}

	public function after_action( $succ, $id, $action = "save" )
	{
		if( ! $id ) return $succ;

		return $succ;
	}

	public function document_account_period_handle( $ord_id = 0 , $ord_date = "", $wh = "", $action = "save" )
	{
		if( $ord_id <= 0 )	//check for doc saving
		{
			return false;
		}

		//check exists for status related changes
		$exists = [];
		if( isset( $ord_id ) && $ord_id > 0 )
		{
			$filter = [ 'id' => $ord_id ];
			$exists = $this->Logic->get_infos( $filter, [], true, [] );
			if( isset( $exists['order_date'] ) && ! empty( $exists['order_date'] ) )
			{
				$ord_date = ( $ord_date )? $ord_date : $exists['order_date'];
				$wh = ( $wh )? $wh : $exists['wh_id'];
			}
		}
		$wh = ( $wh )? $wh : $this->warehouse['code'];
		
		//Check IF Updated Document Date in Accounting Period 
		if( ! empty( $ord_date ) && ! empty( $wh ) )
		{
			$DOC = new WC_DocumentTemplate();
			if ( ! $DOC->check_document_account_period( $ord_date, $wh ) )
			{
				return false; 
			}	
		}

		return true;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		echo '<div id="pos_cdnote_reference_content" class="col-md-7">';	
		wcwh_form_field( '', 
			[ 'id'=>'pos_cdnote_reference', 'class'=>['inputSearch'], 'type'=>'text', 'label'=>'', 'required'=>false, 
				'attrs'=>[], 'placeholder'=>'Find By Receipt No. In Full' 
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
				if( current_user_cans( [ 'wh_support' ] ) ):
				?>
					<button id="pos_cdnote_action" class="btn btn-sm btn-primary linkAction" title="Create POS Credit / Debit Note" 
						data-title="Add Credit / Debit by Receipt No."
						data-action="pos_cdnote_reference" data-service="<?php echo $this->section_id; ?>_action" 
						data-modal="wcwhModalForm" data-actions="close|submit" 
						data-source="#pos_cdnote_reference" 
					>
						Add Credit / Debit
						<i class="fa fa-plus-circle" aria-hidden="true"></i>
					</button>
				<?php
				endif;
			break;
		}
	}

	public function view_reference_doc( $doc_id = 0, $title = '', $doc_type = 'pos_receipt' )
	{
		if( ! $doc_id || ! $doc_type ) return;

		switch( $doc_type )
		{
			case 'pos_receipt':
				if( ! class_exists( 'WCWH_PosOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/posOrderCtrl.php" ); 
				$Inst = new WCWH_PosOrder_Controller();
			break;
		}
		
		$ref_segment = [];
		
		if( $Inst )
		{
			$Inst->set_warehouse(  $this->warehouse );
			
			ob_start();
			$Inst->view_form( $doc_id, false, true, true );
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
			'hook' 		=> $this->section_id.'_form',
			'action' 	=> 'save',
			'token' 	=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filters = [ 'docno' => $id ];

			$datas = $this->Logic->get_infos( $filters, [], true, [] );
			if( $datas )
			{
				//$filter = [ 'order_id'=>$datas['id'] ];
				//$datas['details'] = $this->Logic->get_details( $filter, [], false );

				$datas['ref_doc_id'] = $datas['id'];
				$datas['ref_docno'] = $datas['order_no'];
				unset( $datas['id'] );
				unset( $datas['order_no'] );
				unset( $datas['order_date'] );
				unset( $datas['order_status'] );
				unset( $datas['total'] );
				unset( $datas['total_credit'] );
				unset( $datas['amt_paid'] );
				unset( $datas['amt_change'] );
			}

			/*if( $datas['details'] )
			{
				foreach( $datas['details'] as $i => $item )
		    	{
		    		$datas['details'][$i]['item_name'] = $item['order_item_name'];
		    		$datas['details'][$i]['prdt_name'] = $item['item_code'].' - '.$item['item_name'];
		    		$datas['details'][$i]['line_item'] = [ 
		    			'name'=>$item['item_name'], 'code'=>$item['item_code'], 'uom_code'=>$item['uom'], 
						'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		    		];
		    	}
			}*/

			$args['data'] = $datas;
		}

		if( $args['data']['ref_doc_id'] )
			$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_docno'], 'pos_receipt' );

		do_action( 'wcwh_get_template', 'form/posCDN-form.php', $args );
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false, $config = [ 'ref'=>1, 'link'=>1 ] )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook' 		=> $this->section_id.'_form',
			'action' 	=> 'save',
			'token' 	=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
			'get_content' => $getContent,
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filters = [ 'id' => $id ];

			$datas = $this->Logic->get_infos( $filters, [], true, [] );
			if( $datas )
			{
				$filter = [ 'order_id'=>$datas['id'] ];
				$datas['details'] = $this->Logic->get_details( $filter, [], false );

				if( !empty( $datas['ref_doc_id'] ) )
				{
					$filters = [ 'id' => $datas['ref_doc_id'] ];
					$ref_doc = $this->Logic->get_infos( $filters, [], true, [] );
				}
			}

			$args['action'] = 'update';
			if( $isView ) $args['view'] = true;

			$Inst = new WCWH_Listing();

			if( $datas['details'] )
			{
				$total_row = [ 'prdt_name'=>'TOTAL', 'qty'=> 0, 'metric'=>0, 'subtotal'=>0, 'total'=>0 ];
				foreach( $datas['details'] as $i => $item )
		    	{
		    		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['order_id']} - {$item['id']}'>".($i+1).".</span>" : ($i+1).".";

		    		$datas['details'][$i]['item_name'] = $item['order_item_name'];
		    		$datas['details'][$i]['prdt_name'] = $item['item_code'].' - '.$item['item_name'];
		    		$datas['details'][$i]['line_item'] = [ 
		    			'name'=>$item['item_name'], 'code'=>$item['item_code'], 'uom_code'=>$item['uom'], 
						'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		    		];

		    		$total_row['qty']+= $item['qty'];
		    		$total_row['metric']+= $item['metric'] * $item['qty'];
		    		$total_row['subtotal']+= $item['subtotal'];
		    		$total_row['total']+= $item['total'];
		    	}

		    	if( $isView ) $datas['details'][] = $total_row;
			}

			$args['data'] = $datas;
			unset( $args['new'] );

			$args['render'] = $Inst->get_listing( [
					'num' => '',
		        	'prdt_name' => 'Item',
		        	'uom' => 'UOM',
		        	'price_code' => 'Price COde',
		        	'uprice' => 'Unit Price',
		        	'qty' => 'Qty',
		        	'metric' => 'Metric (kg/l)',
		        	'price' => 'Item Price',
		        	'subtotal' => 'Subtotal',
		        	'total' => 'Total',
				], 
		    	$datas['details'], 
		    	[], 
		    	$hides, 
		    	[ 'off_footer'=>true, 'list_only'=>true ]
		    );
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/posCDN-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $ref_doc['order_no'], 'pos_receipt' );

			do_action( 'wcwh_get_template', 'form/posCDN-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/posCDN-row.php', $this->tplName['row'] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/POSCDNListing.php" ); 
			$Inst = new WCWH_POSCDN_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			//$Inst->set_args( [ 'per_page_row'=>50 ] );

			$Inst->styles = [
				'.list-stat.list-processing' => [ 'border'=>'1px solid #C6E1C6', 'background'=>'#C6E1C6', 'color'=>'#5b841b !important' ],
				'.list-stat.list-pending' => [ 'border'=>'1px solid #E5E5E5', 'background'=>'#E5E5E5', 'color'=>'#777 !important' ],
				'.list-stat.list-on-hold' => [ 'border'=>'1px solid #F8DDA7', 'background'=>'#F8DDA7', 'color'=>'#94660c !important' ],
				'.list-stat.list-completed' => [ 'border'=>'1px solid #C8D7E1', 'background'=>'#C8D7E1', 'color'=>'#2e4453 !important' ],
				'.list-stat.list-cancelled' => [ 'border'=>'1px solid #E5E5E5', 'background'=>'#E5E5E5', 'color'=>'#777 !important' ],
				'.list-stat.list-refunded' => [ 'border'=>'1px solid #E5E5E5', 'background'=>'#E5E5E5', 'color'=>'#777 !important' ],
				'.list-stat.list-failed' => [ 'border'=>'1px solid #EBA3A3', 'background'=>'#EBA3A3', 'color'=>'#761919 !important' ],

				'.btn-outline-processing' => [ 'color'=>'#5b841b !important', 'background'=>'transparent', 'border'=>'1px solid #C6E1C6' ],
				'.btn-outline-pending' => [ 'color'=>'#777 !important', 'background'=>'transparent', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-on-hold' => [ 'color'=>'#94660c !important', 'background'=>'transparent', 'border'=>'1px solid #F8DDA7' ],
				'.btn-outline-completed' => [ 'color'=>'#2e4453 !important', 'background'=>'transparent', 'border'=>'1px solid #C8D7E1' ],
				'.btn-outline-cancelled' => [ 'color'=>'#777 !important', 'background'=>'transparent', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-refunded' => [ 'color'=>'#777 !important', 'background'=>'transparent', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-failed' => [ 'color'=>'#761919 !important', 'background'=>'transparent', 'border'=>'1px solid #EBA3A3' ],

				'.btn-outline-processing:hover' => [ 'color'=>'#5b841b !important', 'background'=>'#C6E1C6', 'border'=>'1px solid #C6E1C6' ],
				'.btn-outline-pending:hover' => [ 'color'=>'#777 !important', 'background'=>'#E5E5E5', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-on-hold:hover' => [ 'color'=>'#94660c !important', 'background'=>'#F8DDA7', 'border'=>'1px solid #F8DDA7' ],
				'.btn-outline-completed:hover' => [ 'color'=>'#2e4453 !important', 'background'=>'#C8D7E1', 'border'=>'1px solid #C8D7E1' ],
				'.btn-outline-cancelled:hover' => [ 'color'=>'#777 !important', 'background'=>'#E5E5E5', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-refunded:hover' => [ 'color'=>'#777 !important', 'background'=>'#E5E5E5', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-failed:hover' => [ 'color'=>'#761919 !important', 'background'=>'#EBA3A3', 'border'=>'1px solid #EBA3A3' ],

				'.btn-outline-processing.active' => [ 'color'=>'#5b841b !important', 'background'=>'#C6E1C6', 'border'=>'1px solid #C6E1C6' ],
				'.btn-outline-pending.active' => [ 'color'=>'#777 !important', 'background'=>'#E5E5E5', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-on-hold.active' => [ 'color'=>'#94660c !important', 'background'=>'#F8DDA7', 'border'=>'1px solid #F8DDA7' ],
				'.btn-outline-completed.active' => [ 'color'=>'#2e4453 !important', 'background'=>'#C8D7E1', 'border'=>'1px solid #C8D7E1' ],
				'.btn-outline-cancelled.active' => [ 'color'=>'#777 !important', 'background'=>'#E5E5E5', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-refunded.active' => [ 'color'=>'#777 !important', 'background'=>'#E5E5E5', 'border'=>'1px solid #E5E5E5' ],
				'.btn-outline-failed.active' => [ 'color'=>'#761919 !important', 'background'=>'#EBA3A3', 'border'=>'1px solid #EBA3A3' ],
			];

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$filters['cd_note'] = 1;
			$datas = $this->Logic->get_infos( $filters, $order, false, [], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}