<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PosDO_Class" ) ) include_once( WCWH_DIR . "/includes/classes/pos-do.php" ); 
if ( !class_exists( "WCWH_PosOrder_Class" ) ) include_once( WCWH_DIR . "/includes/classes/pos-order.php" ); 

if ( !class_exists( "WCWH_PosDO_Controller" ) ) 
{

class WCWH_PosDO_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_pos_do";

	public $Notices;
	public $className = "PosDO_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newDO',
		'row' => 'rowDO',
		'do' => 'printDO',
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
		$this->Logic = new WCWH_PosDO_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->processing_stat = $this->processing_stat;

		$this->PosOrder = new WCWH_PosOrder_Class( $this->db_wpdb );		
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
				case 'print':
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

					$ord_detail = [];
					if( $header['ref_order_id'] )
					{
						$filters = [ 'id' => $header['ref_order_id'] ];
						$pos_order = $this->PosOrder->get_infos( $filters, [], true, [] );

						if( $pos_order )
						{
							$filter = [ 'order_id'=>$pos_order['id'] ];
							$order_detail = $this->PosOrder->get_details( $filter, [], false );

							if( $order_detail )
							{
								foreach ($order_detail as $i => $row) {
									$ord_detail[ $row['item_id'] ] = $row;
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

							$sprice = 0;
							if( $ord_detail[ $row['product_id'] ] )
							{
								//$price = $ord_detail[ $row['product_id'] ]['price'];
								//$sprice = ( $price > 0 )? $price : $sprice;
								//$detail[$i]['sprice'] = $sprice;
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
				case "print":
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
						case 'pos_delivery_order':
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
									'Receipt No.' => $doc['ref_order_no'],
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

								if($doc['client_company_code'])
								{
									$billing = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'billing' ] );
									$shipping = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'shipping' ] );								
								}
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


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			
			$pos_receipt = $this->Logic->get_reference( $this->warehouse['code'] );
			
			$options = options_data( $pos_receipt, 'id', [ 'order_no', 'customer_code', 'customer_name', 'order_comments' ], 'New Delivery Order by POS Receipt' );
	        echo '<div id="pos_do_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'pos_do_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#pos_do_action"'], 'class'=>['select2','triggerChange'], 
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
				<button id="pos_do_action" class="btn btn-sm btn-primary linkAction display-none" title="Add <?php echo $actions['save'] ?> Delivery Order"
					data-title="<?php echo $actions['save'] ?> POS Delivery Order" 
					data-action="pos_do_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#pos_do_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> POS Delivery Order
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_reference_receipt( $id = 0, $title = '' )
	{
		if( ! $id ) return;

		if( ! class_exists( 'WCWH_PosOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/posOrderCtrl.php" );
		$Inst = new WCWH_PosOrder_Controller();
		
		$ref_segment = [];
		
		if( $Inst )
		{
			$Inst->set_warehouse(  $this->warehouse );
			
			ob_start();
			$Inst->view_form( $id, false, true, true );
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
			'wh_id'		=> $this->warehouse['id'],
		);

		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$filters = [ 'id' => $id ];

			$pos_order = $this->PosOrder->get_infos( $filters, [], true, [] );
			if( $pos_order )
			{
				$args['data']['ref_order_id'] = $pos_order['id'];
				$args['data']['ref_order_no'] = $pos_order['order_no'];
				$args['data']['doc_date'] = $pos_order['order_date'];
				$args['data']['remark'] = $pos_order['order_comments'];

				$filter = [ 'order_id'=>$pos_order['id'] ];
				$order_detail = $this->PosOrder->get_details( $filter, [], false );

				if( $order_detail )
				{
					$details = array();
					foreach( $order_detail as $o => $detail )
					{
						$filter = [ 'status'=>1, 'seller'=>$this->warehouse['id'], 'id'=>$detail['item_id'] ];
						$item = apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'isUnit'=>1, 'stocks'=>$this->warehouse['code'], 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
						if($item)
						{
							$stock = 0;
							if( $item['parent'])
							{
								$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
								$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

								$filter = [ 'wh_code'=>$this->warehouse['code'], 'strg_id'=>$storage['id'], 'item_id'=>$item['id'] ];
								$stock = apply_filters( 'wcwh_get_stocks', $filter, [], true, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
							}
							$stk = ( $stock )? $stock['qty'] : $item['stock_qty'];
		        			$stk-= ( $stock )? $stock['allocated_qty'] : $item['stock_allocated'];

		        			$details[] = [
		        				'id' =>  $item['id'],
		        				'product_id' => $item['id'],
		        				'item_id' => '',
		        				'line_item' => [ 'name'=>$item['name'], 
		        					'code'=>$item['code'], 
		        					'uom_code'=>$item['_uom_code'],
		        					'uom_fraction'=>$item['uom_fraction'],
		        					'required_unit'=>$item['required_unit'],
		        					'stocks' => $stk
		        				],
		        				'bqty' => $detail['qty'],
		        				'bunit' => '',
		        				'ref_bqty' => $detail['qty'],
		        			];
						}
						else{
							//------ Item No Found Handling
						}						
					}
					$args['data']['details'] = $details;					
				}

				$args['NoAddItem'] = !empty( $this->setting[ $this->section_id ]['add_item'] )? false : true;
				$args['NoDelItem'] = !empty( $this->setting[ $this->section_id ]['del_item'] )? false : true;
				$args['LockedBqty'] = !empty( $this->setting[ $this->section_id ]['locked_bqty'] )? true : false;
			}
		}

		if( $args['data']['ref_order_no'] )
		{
			$this->view_reference_receipt( $id, $args['data']['ref_order_no'] );		
		}

		do_action( 'wcwh_get_template', 'form/posDO-form.php', $args );
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false )
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

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'] ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1, 'stocks'=>$this->warehouse['code'] ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$args['NoAddItem'] = !empty( $this->setting[ $this->section_id ]['add_item'] )? false : true;
				$args['NoDelItem'] = !empty( $this->setting[ $this->section_id ]['del_item'] )? false : true;
				$args['LockedBqty'] = !empty( $this->setting[ $this->section_id ]['locked_bqty'] )? true : false;

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

		        		$datas['details'][$i]['ucost'] = round_to( $datas['details'][$i]['ucost'], 5, true );
		        		$datas['details'][$i]['total_cost'] = round_to( $datas['details'][$i]['bqty'] * $datas['details'][$i]['ucost'], 2, true );
		        		$total_cost+= $datas['details'][$i]['total_cost'];

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
			do_action( 'wcwh_templating', 'form/posDO-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( $args['data']['ref_order_id'] )
			{
				$this->view_reference_receipt( $args['data']['ref_order_id'], $args['data']['ref_order_no'] );					
			}

			do_action( 'wcwh_get_template', 'form/posDO-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/posDO-row.php', $this->tplName['row'] );
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

		do_action( 'wcwh_templating', 'form/posDO-print-form.php', $this->tplName['do'], $args );
	}


	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/posDOListing.php" ); 
			$Inst = new WCWH_PosDO_Listing();

			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#action' => [ 'width' => '90px' ],
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

			$metas = [ 'remark', 'vehicle', 'ref_doc_id', 'ref_doc_type', 'ref_doc', 'client_company_code', 'sales_doc', 'base_doc_type', 'base_doc_id', 'direct_issue', 'supply_to_seller', 'ref_order_id', 'ref_order_no' ];

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