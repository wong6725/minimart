<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_TODO_Class' ) ) include_once( WCWH_DIR . "/includes/classes/todo.php" ); 

if ( !class_exists( "WCWH_TODO_Controller" ) ) 
{

class WCWH_TODO_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_todo";

	protected $primary_key = "id";

	public $Notices;
	public $className = "TODO_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newTODO',
	);

	public $listview = 'approval';

	public $allowTrigger = true;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
	}

	public function __destruct()
	{
		unset($this->Logic);
		unset($this->Notices);
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_TODO_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
	}

	public function get_section_id()
	{
		return $this->section_id;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'arr_id' => 0,
			'ref_id' => 0,
			'docno' => '',
			'doc_title' => '',
			'status' => 1,
			'flag' => 0,
			'remark' => '',
			'created_by' => 0,
			'created_at' => '',
			'action_taken' => 0,
			'action_by' => 0,
			'action_at' => '',
		);
	}

	protected function get_uniqueFields()
	{
		return array();
	}

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
				case 'update':
				case 'delete':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				default:
					if( is_numeric( $action ) )
					{
						if( ! isset( $datas['id'] ) || ! $datas['id'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'insufficient-data', 'error' );
						}
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
		$count_succ = 0;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

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
					$datas = $this->data_sanitizing( $datas );
					
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];
			
					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						if( ! $datas['docno'] )
						{
							$datas['docno'] = apply_filters( 'warehouse_generate_docno', $datas['docno'], $this->section_id );
						}
						$datas['created_by'] = $user_id;
						$datas['created_at'] = $now;
						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
					}

					//$datas = $this->json_encoding( $datas );

					if( $succ )
					{
						$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $result['succ'] )
						{
							$outcome['id'][] = $result['id'];
							//$outcome['data'][] = $result['data'];
						}
					}
				break;
				case "delete":
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$datas['id'] = $id;
							$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							$outcome['id'][] = $result['id'];
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
				default:
					if( ! $action )
					{
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}

					if( $succ )
					{
						$extracted = $this->extract_data( $datas );
						$datas = $extracted['datas'];
						$metas = $extracted['metas'];
						$metas['remark'] = ( $metas['remark'] )? $metas['remark'] : $datas['remark'];

						$ids = $datas['id'];
						$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

						if( $ids )
						{
							$datas['flag'] = 1;
							$datas['action_by'] = $user_id;
							$datas['action_at'] = $now;

							foreach( $ids as $id )
							{
								$todo = $this->Logic->get_infos( [ 'id' => $id, 'status'=>1, 'flag'=>0 ], [], true, [ 'arrangement'=>1 ] );
								$datas['id'] = $id;

								if( $todo )
								{
									$todo_action = $this->Logic->get_todo_action( 0, [ 'arr_id'=>$todo['arr_id'], 'next_action'=>$action ] );
									if( ! $todo_action )
									{
										$succ = false;
										$this->Notices->set_notice( 'invalid-record', 'error' );
									}

									if( $succ )
									{
										$datas['action_taken'] = $todo_action['id'];
										$result = $this->Logic->action_handler( 'update', $datas, $metas, $obj );
										if( ! $result['succ'] )
										{
											$succ = false;
											$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
											break;
										}
									}
								}
								else
								{
									$succ = false;
									$this->Notices->set_notice( 'invalid-record', 'error' );
								}

								if( $succ )
								{
									$outcome['id'][] = $result['id'];
									$count_succ++;

									if( $todo )
									{
										//update section status
										/*
										if( ! $this->Logic->update_section_document_status( $todo['ref_id'], $todo['section'], $status ) )
										{
											$succ = false;
										}
										*/

										if( $this->allowTrigger && $todo_action['trigger_action'] )
										{
											$succ = $this->todo_trigger_action( $todo['ref_id'], $todo['section'], $todo_action['trigger_action'], $metas );
										}
									}
								}
							}
						}
						else {
							$succ = false;
							$this->Notices->set_notice( 'insufficient-data', 'error' );
						}
					}
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

	public function todo_trigger_action( $id = 0, $section = "", $action = "", $metas = array() )
	{
		if( ! $id || ! $section || ! $action ) return false;

		$succ = true;
		$Inst = array();

		switch( $section )
		{
			case 'wh_company':
				if( ! class_exists( 'WCWH_Company_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/companyCtrl.php" ); 
				$Inst = new WCWH_Company_Controller();
			break;
			case 'wh_warehouse':
				if( ! class_exists( 'WCWH_Warehouse_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/warehouseCtrl.php" ); 
				$Inst = new WCWH_Warehouse_Controller();
			break;
			case 'wh_brand':
				if( ! class_exists( 'WCWH_Brand_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/brandCtrl.php" ); 
				$Inst = new WCWH_Brand_Controller();
			break;
			case 'wh_supplier':
				if( ! class_exists( 'WCWH_Supplier_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/supplierCtrl.php" ); 
				$Inst = new WCWH_Supplier_Controller();
			break;
			case 'wh_asset':
				if( ! class_exists( 'WCWH_Asset_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/assetCtrl.php" ); 
				$Inst = new WCWH_Asset_Controller();
			break;
			case 'wh_items':
				if( ! class_exists( 'WCWH_Item_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/itemCtrl.php" ); 
				$Inst = new WCWH_Item_Controller();
			break;
			case 'wh_reprocess_item':
				if( ! class_exists( 'WCWH_ReprocessItem_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/reprocessItemCtrl.php" ); 
				$Inst = new WCWH_ReprocessItem_Controller();
			break;
			case 'wh_pricing':
				if( ! class_exists( 'WCWH_Pricing_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/pricingCtrl.php" ); 
				$Inst = new WCWH_Pricing_Controller();
			break;
			case 'wh_margin':
				if( ! class_exists( 'WCWH_Margin_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/marginCtrl.php" ); 
				$Inst = new WCWH_Margin_Controller();
			break;
			case 'wh_promo':
				if( ! class_exists( 'WCWH_Promo_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/promoCtrl.php" ); 
				$Inst = new WCWH_Promo_Controller();
			break;
			case 'wh_customer':
				if( ! class_exists( 'WCWH_Customer_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/customerCtrl.php" ); 
				$Inst = new WCWH_Customer_Controller();
			break;
			case 'wh_customer_group':
				if( ! class_exists( 'WCWH_CustomerGroup_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/customerGroupCtrl.php" ); 
				$Inst = new WCWH_CustomerGroup_Controller();
			break;
			case 'wh_customer_job':
				if( ! class_exists( 'WCWH_CustomerJob_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/customerJobCtrl.php" ); 
				$Inst = new WCWH_CustomerJob_Controller();
			break;
			case 'wh_origin_group':
				if( ! class_exists( 'WCWH_OriginGroup_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/originGroupCtrl.php" ); 
				$Inst = new WCWH_OriginGroup_Controller();
			break;
			case 'wh_credit':
				if( ! class_exists( 'WCWH_Credit_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/creditCtrl.php" ); 
				$Inst = new WCWH_Credit_Controller();
			break;
			case 'wh_credit_term':
				if( ! class_exists( 'WCWH_CreditTerm_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/creditTermCtrl.php" ); 
				$Inst = new WCWH_CreditTerm_Controller();
			break;
			case 'wh_credit_topup':
				if( ! class_exists( 'WCWH_CreditTopup_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/creditTopupCtrl.php" ); 
				$Inst = new WCWH_CreditTopup_Controller();
			break;
			case 'wh_payment_term':
				if( ! class_exists( 'WCWH_PaymentTerm_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/paymentTermCtrl.php" ); 
				$Inst = new WCWH_PaymentTerm_Controller();
			break;
			//
			case 'wh_purchase_request':
				if( ! class_exists( 'WCWH_PurchaseRequest_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" ); 
				$Inst = new WCWH_PurchaseRequest_Controller();
			break;
			case 'wh_purchase_order':
				if( ! class_exists( 'WCWH_PurchaseOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/purchaseOrderCtrl.php" ); 
				$Inst = new WCWH_PurchaseOrder_Controller();
			break;
			case 'wh_sales_order':
				if( ! class_exists( 'WCWH_SaleOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
				$Inst = new WCWH_SaleOrder_Controller();
			break;
			case 'wh_transfer_order':
				if( ! class_exists( 'WCWH_TransferOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/TransferOrderCtrl.php" ); 
				$Inst = new WCWH_TransferOrder_Controller();
			break;
			case 'wh_good_issue':
				if( ! class_exists( 'WCWH_GoodIssue_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
				$Inst = new WCWH_GoodIssue_Controller();
			break;
			case 'wh_reprocess':
				if( ! class_exists( 'WCWH_Reprocess_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/reprocessCtrl.php" ); 
				$Inst = new WCWH_Reprocess_Controller();
			break;
			case 'wh_delivery_order':
				if( ! class_exists( 'WCWH_DeliveryOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
				$Inst = new WCWH_DeliveryOrder_Controller();
			break;
			case 'wh_good_receive':
				if( ! class_exists( 'WCWH_GoodReceive_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
				$Inst = new WCWH_GoodReceive_Controller();
			break;
			case 'wh_good_return':
				if( ! class_exists( 'WCWH_GoodReturn_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodReturnCtrl.php" ); 
				$Inst = new WCWH_GoodReturn_Controller();
			break;
			case 'wh_block_stock':
				if( ! class_exists( 'WCWH_BlockStock_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/blockStockCtrl.php" ); 
				$Inst = new WCWH_BlockStock_Controller();
			break;
			case 'wh_block_action':
				if( ! class_exists( 'WCWH_BlockAction_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/blockActionCtrl.php" ); 
				$Inst = new WCWH_BlockAction_Controller();
			break;
			case 'wh_stock_adjust':
				if( ! class_exists( 'WCWH_Adjustment_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/adjustmentCtrl.php" ); 
				$Inst = new WCWH_Adjustment_Controller();
			break;
		}

		$datas = [ 'id' => $id ];
		if( $metas )
		{
			foreach( $metas as $key => $meta )
			{
				$datas[ $key ] = $meta;
			}
		}

		if( $Inst )
		{	
			$result = $Inst->action_handler( $action, $datas, [], false );
			if( !$result['succ'] )
			{
				$succ = false;
			}
		}

		return $succ;
	}

	public function todo_external_action( $ref_id = 0, $section_id = "", $action = "", $remark = "" )
	{
		if( ! $ref_id || ! $section_id || ! $action ) return false;

		$succ = true;

		$exists = $this->Logic->get_infos( [ 'ref_id'=>$ref_id, 'section'=>$section_id, 'status'=>1, 'flag'=>0 ], [], true, [ 'arrangement'=>1 ] );
		if( $exists )
		{
			/*$todo_action = $this->Logic->get_todo_action( 0, [ 'arr_id'=>$exists['arr_id'], 'next_action'=>$action ] );
			if( ! $todo_action )
			{
				$succ = false;
			}*/

			if( $succ )
			{
				$this->allowTrigger = false;
				$data = array(
					'id' => $exists['id'],
					'remark' => $remark,
				);
				$results = $this->action_handler( $action, $data, [], false );
				if( ! $results['succ'] )
				{
					$succ = false;
				}
				$this->allowTrigger = true;
			}
		}
		else
		{
			$done = $this->Logic->get_infos( [ 'ref_id'=>$ref_id, 'section'=>$section_id, 'status'=>1, 'flag'=>1 ], [ 'a.id'=>'DESC' ], true, [ 'arrangement'=>1 ] );
			//pd($done);
			if( ! $done )
			{
				$succ = false;
			}
		}

		return $succ;
	}

	public function todo_arrangement( $ref_id = 0, $section_id = "", $action = "save" )
	{
		if( ! $ref_id || ! $section_id ) return false;

		$succ = true;
		$action = strtolower( $action );
		switch( $action )
		{
			case 'delete':
				$exists = $this->Logic->get_infos( [ 'ref_id'=>$ref_id, 'section'=>$section_id, 'status'=>1, 'flag'=>0 ], [], true, [ 'arrangement'=>1 ] );

				if( $exists )
				{
					$datas = array(
						'id' => $exists['id'],
						'status' => 0,
					);
					$results = $this->action_handler( 'update', $datas, [], false );
					if( ! $results['succ'] )
					{
						$succ = false;
					}
				}
			break;
			case 'save':
			case 'update':
			case 'restore':
				$exists = $this->Logic->get_infos( [ 'ref_id'=>$ref_id, 'section'=>$section_id, 'status'=>1, 'flag'=>0 ], [], true, [ 'arrangement'=>1 ] );
				if( $exists && $action == 'update' )	//assign new todo for each changes
				{
					$datas = array(
						'id' => $exists['id'],
						'status' => 0,
					);
					$results = $this->action_handler( 'update', $datas, [], false );
					if( ! $results['succ'] )
					{
						$succ = false;
					}
				}
				
				if( $succ )
				{
					$result = $this->Logic->check_todo_arrangement( $ref_id, $section_id );
					if( $result )	//Need / Can add arrangement
					{
						$datas = array(
							'arr_id' => $result['arr_id'],
							'ref_id' => $result['ref_id'],
							'doc_title' => $this->Logic->todo_title_replacer( $result['title'], $result ),
						);
						$results = $this->action_handler( 'save', $datas, [], false );
						if( ! $results['succ'] )
						{
							$succ = false;
						}
					}
				}
			break;
			default:
				$exists = $this->Logic->get_infos( [ 'ref_id'=>$ref_id, 'section'=>$section_id, 'status'=>1, 'flag'=>0 ], [], true, [ 'arrangement'=>1 ] );
				if( $exists && $action == 'update' )	//assign new todo for each changes
				{
					$datas = array(
						'id' => $exists['id'],
						'status' => 0,
					);
					$results = $this->action_handler( 'update', $datas, [], false );
					if( ! $results['succ'] )
					{
						$succ = false;
					}
				}
				
				if( $succ )
				{
					$result = $this->Logic->check_todo_arrangement( $ref_id, $section_id );
					if( $result )	//Need / Can add arrangement
					{
						$datas = array(
							'arr_id' => $result['arr_id'],
							'ref_id' => $result['ref_id'],
							'doc_title' => $this->Logic->todo_title_replacer( $result['title'], $result ),
						);
						$results = $this->action_handler( 'save', $datas, [], false );
						if( ! $results['succ'] )
						{
							$succ = false;
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
	public function set_listview( $listview = 'approval' )
	{
		$this->listview = $listview;
	}

	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
	}

	public function view_form( $id = 0, $templating = true, $isView = true )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'token' => apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['new'],
		);

		if( $id )
		{
			$datas = $this->Logic->get_infos( [ 'id' => $id ], [], true, [ 'arrangement'=>1 ] );
			if( $datas )
			{
				if( $isView ) $args['view'] = true;
				$args['data'] = $datas;
			}
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
			include_once( WCWH_DIR . "/includes/listing/todoListing.php" ); 
			$Inst = new WCWH_TODO_Listing();
			$Inst->set_section_id( $this->section_id );

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			//$count = $this->Logic->count_statuses();
			//if( $count ) $Inst->viewStats = $count;

			$datas = $this->Logic->get_todo( $this->listview, $filters );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function view_history_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_history_listing"
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/todoHistoryListing.php" ); 
			$Inst = new WCWH_TODOHistory_Listing();
			$Inst->set_section_id( $this->section_id );

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			if( isset( $filters['status'] ) )
			{
				$filters['flag'] = $filters['status'];
				if( $filters['flag'] == 'all' ) unset( $filters['flag'] );
			}
			$filters['status'] = 1;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();
			
			$datas = $this->Logic->get_infos( $filters, $order, false, [ 'arrangement'=>1, 'action'=>1, 'section'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}