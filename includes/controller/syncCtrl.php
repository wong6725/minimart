<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_SYNC_Class' ) ) include_once( WCWH_DIR . "/includes/classes/sync.php" ); 

if ( !class_exists( "WCWH_SYNC_Controller" ) ) 
{

class WCWH_SYNC_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_sync";

	protected $primary_key = "id";

	public $Notices;
	public $className = "TODO_Controller";

	public $Logic;
	public $WH;

	public $tplName = array(
		'new' => 'newSync',
	);

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
	}

	public function __destruct()
	{
		unset($this->Logic);
		unset($this->WH);
		unset($this->Notices);
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_SYNC_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );

		if( ! class_exists( 'WCWH_Warehouse_Class' ) ) include_once( WCWH_DIR . "/includes/classes/warehouses.php" ); 
		$this->WH = new WCWH_Warehouse_Class();
		
		add_action( 'wcwh_sync_after_handshake', array( $this, 'after_handshake' ), 10, 2 );
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
			'direction' => 'out',
			'remote_url' => '',
			'wh_code' => '',
			'section' => '',
			'ref_id' => 0,
			'ref' => '',
			'details' => '',
			'status' => 1,
			'handshake' => 0,
			'notification' => '',
			'error' => '',
			'created_at' => '',
			'lupdate_at' => '',
			'lsync_at' => '',
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

			$datas['lupdate_at'] = $now;

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
						$datas['created_at'] = $now;
						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
					}

					if( empty( $datas['remote_url'] ) && !empty( $datas['wh_code'] ) )
					{
						$wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh_code'] ], [], true );
						if( $wh )
						{
							$datas['remote_url'] = get_warehouse_meta( $wh['id'], 'api_url', true );
						}
					}

					$datas = $this->json_encoding( $datas );

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
							$result = $this->Logic->action_handler( $action, [ 'id'=>$id ], $metas, $obj );
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

	public function sync_arrangement( $ref_id = 0, $section_id = "", $action = "save", $ref = '', $wh_code = '', $data = [] )
	{
		if( ! $ref_id || ! $section_id ) return false;

		if( ! $this->setting[ $this->section_id ]['use_sync'] ) return true;
		
		$section = get_section( $section_id );

		if( ! $section || ! $section['push_service'] ) return true;

		global $wcwh;
		$datas = array();
		$targets = array();

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		
		if( $wh )
		{
			if( $wh['parent'] )
			{
				$parent = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$wh['parent'], 'status'=>1 ], [], true );
				if( $parent )
				{
					$remote_url = get_warehouse_meta( $parent['id'], 'api_url', true );
					$targets[] = [
						'wh_code' => $parent['code'],
						'remote_url' => $remote_url,
					];
				}
			}
			else
			{
				$childs = $this->WH->get_childs( $wh['id'] );
				if( $childs )
				{
					foreach( $childs as $child )
					{
						$remote_url = get_warehouse_meta( $child['id'], 'api_url', true );
						if( ! $remote_url ) continue;

						$excl = get_warehouse_meta( $child['id'], 'excl_push_'.$section_id, true );
						if( $excl ) continue;

						if( ! $wh_code || ( $wh_code && $wh_code == $child['code'] ) )
						{
							$targets[] = [
								'wh_code' => $child['code'],
								'remote_url' => $remote_url,
							];
						}
					}
				}
			}
		}

		if( ! $targets ) return true;

		$succ = true;

		foreach( $targets as $target )
		{
			if( ! $data ) $exists = $this->Logic->get_infos( [ 'wh_code'=>$target['wh_code'], 'ref_id'=>$ref_id, 'section'=>$section_id, 'status'=>1, 'handshake'=>0 ], [], true, [] );
			if( $exists )
			{
				$datas = array(
					'id' => $exists['id'],
					'lupdate_at' => current_time( 'mysql' ),
				);
				$results = $this->action_handler( 'update', $datas, [], false );
				if( ! $results['succ'] )
				{
					$succ = false;
				}
			}
			else
			{
				$datas = array(
					'direction' => 'out',
					'remote_url' => $target['remote_url'],
					'wh_code' => $target['wh_code'],
					'section' => $section_id,
					'ref_id' => $ref_id,
					'ref' => $ref,
				);
				if( $data ) $datas['details'] = $data;
				$results = $this->action_handler( 'save', $datas, [], false );
				if( ! $results['succ'] )
				{
					$succ = false;
				}
			}
		}

		return $succ;
	}

	public function einv_arrangement( $ref_id = 0, $section_id = "", $action = "save", $ref = '', $wh_code = '', $remote_url = '', $data = [], $direct = false )
	{
		if( ! $ref_id || ! $section_id || ! $wh_code || ! $remote_url ) return false;

		if( ! $this->setting[ $this->section_id ]['use_sync'] ) return true;
		
		$section = get_section( $section_id );

		if( ! $section || ! $section['push_service'] ) return true;

		global $wcwh;
		$datas = array();
		
		$targets = array();
		$targets[] = [
			'wh_code' => $wh_code,
			'remote_url' => $remote_url,
		];

		if( ! $targets ) return true;

		$succ = true;

		foreach( $targets as $target )
		{
			if( ! $data ) $exists = $this->Logic->get_infos( [ 'wh_code'=>$target['wh_code'], 'ref_id'=>$ref_id, 'section'=>$section_id, 'status'=>1, 'handshake'=>0 ], [], true, [] );

			$results = [];
			if( $exists )
			{
				$datas = array(
					'id' => $exists['id'],
					'lupdate_at' => current_time( 'mysql' ),
				);
				$results = $this->action_handler( 'update', $datas, [], false );
				if( ! $results['succ'] )
				{
					$succ = false;
				}
			}
			else
			{
				$datas = array(
					'direction' => 'out',
					'remote_url' => $target['remote_url'],
					'wh_code' => $target['wh_code'],
					'section' => $section_id,
					'ref_id' => $ref_id,
					'ref' => $ref,
				);
				if( $data ) $datas['details'] = $data;
				$results = $this->action_handler( 'save', $datas, [], false );
				if( ! $results['succ'] )
				{
					$succ = false;
				}
			}

			if( $succ && $direct )
			{
				$rid = $results['id'][0];
				$this->sync_remote_api( $rid );
			}
		}

		return $succ;
	}
	
	public function after_handshake( $datas = [], $response = [] )
	{
		if( empty( $datas ) || empty( $datas['section'] ) ) return true;

		switch( $datas['section'] )
		{
			case 'wh_e_invoice':
			case 'wh_sale_cdnote':
			case $this->refs['einv_id'].'_myinvoice':
				if (!class_exists("WCWH_EInvoice_Controller")) require_once(WCWH_DIR . "/includes/controller/eInvoiceCtrl.php");
				$Inst = new WCWH_EInvoice_Controller();
				
				if( method_exists( $Inst, 'myinvoice_synced' ) ) $Inst->myinvoice_synced( $datas, $response );
			break;
			case 'wh_self_bill':
			case 'wh_self_bill_cdnote':
				if (!class_exists("WCWH_SelfBill_Controller")) require_once(WCWH_DIR . "/includes/controller/selfBillCtrl.php");
				$Inst = new WCWH_SelfBill_Controller();
				
				if( method_exists( $Inst, 'myinvoice_synced' ) ) $Inst->myinvoice_synced( $datas, $response );
			break;
		}

		return true;
	}

	public function sync_remote_api( $sync_id = 0 )
	{
		$succ = true;
		$setting = $this->setting[ $this->section_id ];

		if( defined( 'WCWH_NO_INTEGRATE' ) && WCWH_NO_INTEGRATE ) return true;
		if( ! $setting['use_sync'] ) return true;
		
		$data_per_connect = ( $setting['data_per_connect'] )? $setting['data_per_connect'] : 20;
		$timeout = ( $setting['connection_timeout'] )? $setting['connection_timeout'] : 60;
		@set_time_limit( $timeout * 10 );

		if( $sync_id )
		{
			$ft = [ 'id'=>$sync_id, 'direction'=>'out', 'status'=>1, 'handshake'=>0 ];
			$outstanding = $this->Logic->get_infos( $ft, [ 'a.lupdate_at'=>'ASC' ], false, [], [], [ 0, $data_per_connect ] );
		}
		else
		{
			$ft = [ 'direction'=>'out', 'status'=>1, 'handshake'=>0 ];
			//$ft['not_wh_code'] = [ '1024-VPK' ];
			$outstanding = $this->Logic->get_infos( $ft, [ 'a.lupdate_at'=>'ASC' ], false, [], [], [ 0, $data_per_connect ] );
		}
		
		if( $outstanding )
		{
			$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
			$find = get_warehouse_meta( $wh['id'], 'api_find', true );
			$replace = get_warehouse_meta( $wh['id'], 'api_replace', true );

			$connections = []; $syncs = [];
			foreach( $outstanding as $transaction )
			{
				$details = array();
				switch( $transaction['section'] )
				{
					case 'wh_supplier':
						if ( !class_exists( "WCWH_Supplier_Class" ) ) require_once( WCWH_DIR . "/includes/classes/supplier.php" );
						$Inst = new WCWH_Supplier_Class();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'] ] );
					break;
					case 'wh_items':
						if ( !class_exists( "WCWH_Item_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item.php" );
						$Inst = new WCWH_Item_Class();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'] ] );
						if( $details )
						{
							foreach( $details as $i => $detail )
							{
								if( is_json( $detail['serial2'] ) ) $detail['serial2'] = json_decode( stripslashes( $detail['serial2'] ), true );
								if( $detail['serial2'] && ! is_array( $detail['serial2'] ) ) $detail['serial2'] = [ $detail['serial2'] ];
								
								$details[ $i ]['serial2'] = $detail['serial2'];

								if( $find && $replace )
								{
									foreach( $detail as $key => $val )
									{
										$details[ $i ][ $key ] = str_replace( $find, $replace, $val );
									}
								}
								
							}
						}
					break;
					//Bank In Service
					case 'wh_exchange_rate':
						if ( !class_exists( "WCWH_ExchangeRate_Class" ) ) require_once( WCWH_DIR . "/includes/classes/exchange-rate.php" );
						$Inst = new WCWH_ExchangeRate_Class();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'] , 'status'=>'all', 'flag'=>'all' ]);
					break;
					case 'wh_service_charge':
						if ( !class_exists( "WCWH_ServiceCharge_Class" ) ) require_once( WCWH_DIR . "/includes/classes/servicecharge.php" );
						$Inst = new WCWH_ServiceCharge_Class();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'] , 'status'=>'all', 'flag'=>'all' ]);
					break;
					case 'wh_items_group':
					case 'wh_items_category':
					case 'wh_uom':
						$details = json_decode( $transaction['details'], true );
					break;
					case 'wh_pricing':
						if ( !class_exists( "WCWH_Pricing" ) ) require_once( WCWH_DIR . "/includes/classes/pricing.php" );
						$Inst = new WCWH_Pricing();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'], 'seller'=>$transaction['wh_code'] ] );
					break;
					case 'wh_promo':
						if ( !class_exists( "WCWH_PromoHeader" ) ) require_once( WCWH_DIR . "/includes/classes/promo-header.php" );
						$Inst = new WCWH_PromoHeader();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'], 'seller'=>$transaction['wh_code'] ] );
					break;
					case 'wh_delivery_order':
						if ( !class_exists( "WCWH_DeliveryOrder_Class" ) ) require_once( WCWH_DIR . "/includes/classes/delivery-order.php" );
						$Inst = new WCWH_DeliveryOrder_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					case 'wh_good_return':
						if ( !class_exists( "WCWH_GoodReturn_Class" ) ) require_once( WCWH_DIR . "/includes/classes/good-return.php" );
						$Inst = new WCWH_GoodReturn_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					case 'wh_purchase_request':
						if ( !class_exists( "WCWH_PurchaseRequest_Class" ) ) require_once( WCWH_DIR . "/includes/classes/purchase-request.php" );
						$Inst = new WCWH_PurchaseRequest_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					case 'wh_acc_period':
						if ( !class_exists( "WCWH_AccPeriod_Class" ) ) require_once( WCWH_DIR . "/includes/classes/acc-period.php" );
						$Inst = new WCWH_AccPeriod_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					case 'wh_stocktake_close':
						if ( !class_exists( "WCWH_StockTakeClose_Class" ) ) require_once( WCWH_DIR . "/includes/classes/stocktake-close.php" );
						$Inst = new WCWH_StockTakeClose_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					//------ jeff closing pr 12/8/22--------//
					case 'wh_closing_pr':
						if ( !class_exists( "WCWH_ClosingPR_Class" ) ) require_once( WCWH_DIR . "/includes/classes/closingPR.php" );
						$Inst = new WCWH_ClosingPR_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					//------ jeff closing pr 12/8/22--------//
					case 'wh_member_topup':
						if ( !class_exists( "WCWH_MemberTopup_Class" ) ) require_once( WCWH_DIR . "/includes/classes/member-topup.php" );
						$Inst = new WCWH_MemberTopup_Class();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'], 'warehouse_id'=>$transaction['wh_code'] ] );
					break;
					case 'wh_task_schedule':
					    if (!class_exists("WCWH_TaskSchedule_Class")) require_once(WCWH_DIR . "/includes/classes/task-schedule.php");
					    $Inst = new WCWH_TaskSchedule_Class();

					  
						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ]);
				 	break;
				 	case 'wh_e_invoice':
				 	case 'wh_sale_cdnote':
					    if (!class_exists("WCWH_EInvoice_Controller")) require_once(WCWH_DIR . "/includes/controller/eInvoiceCtrl.php");
					    $Inst = new WCWH_EInvoice_Controller();
					    $transaction['section'] = $this->refs['einv_id'].'_myinvoice';
					  
						$details = $Inst->Einv->convert_einvoice( $transaction['ref_id'] );
				 	break;
				 	case 'wh_self_bill':
				 	case 'wh_self_bill_cdnote':
					    if (!class_exists("WCWH_SelfBill_Controller")) require_once(WCWH_DIR . "/includes/controller/selfBillCtrl.php");
					    $Inst = new WCWH_SelfBill_Controller();
					    $transaction['section'] = $this->refs['einv_id'].'_myinvoice';
					  
						$details = $Inst->Einv->convert_self_billed_einvoice( $transaction['ref_id'] );
				 	break;
				}

				if( $details )
				{
					$transaction['details'] = $details;
					$transaction['sender_count'] = count( $details );
					$connections[ $transaction['wh_code'] ][] = $transaction;

					$syncs[ $transaction['id'] ] = $details;
				}
			}
			
			if( $connections )
			{
				foreach( $connections as $wh_code => $connection )
				{
					foreach( $connection as $i => $row )
					{
						$datas = [
							'handshake' => 'wcwh_remote_api',
							'secret' => md5( 'wcx1'.md5( $wh_code.'wcwh_remote_api' ) ),
							'source_wh' => $wh['code'],
							'wh_code' => $wh_code,
							'source_url' => get_warehouse_meta( $wh['id'], 'api_url', true ),
						];
						$remote_url = $row['remote_url'];
						$datas['datas'][] = $row;

						if( defined( 'developer_debug' ) ){ pd( "Remote Url: ".$remote_url ); pd( $datas ); }

						add_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10, 4 );
						$response = wp_remote_post( $remote_url, [ 'timeout'=>$timeout, 'body'=>$datas, 'sslverify' => false ] );
						remove_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10 );

						if( ! is_wp_error( $response ) ) 
						{
							$response = json_decode( wp_remote_retrieve_body( $response ), true );
							if( defined( 'developer_debug' ) ){ pd( $response ); }
							
							if( $response && $response['connection'] && $response['authenticated'] ) 
							{	
								if( $response['result'] && is_array( $response['result'] ) )
								{
									foreach( $response['result'] as $id => $vals )
									{
										$datas = array(
											'id' => $id,
											'details' => $syncs[ $id ],
											'handshake' => ( $vals['handshake'] )? $vals['handshake'] : 0,
											'notification' => ( $vals['notification'] )? $vals['notification'] : '',
											'lsync_at' => current_time( 'mysql' ),
										);
										$results = $this->action_handler( 'update', $datas, [], false );
										if( ! $results['succ'] )
										{
											$succ = false;
										}
										else
										{
											if( $vals['handshake'] > 0 )
												do_action( 'wcwh_sync_after_handshake', $row, $vals );
										}
									}
								}
							}
						}
						else
						{
							if( defined( 'developer_debug' ) ){ pd( $response->get_error_message() ); }
						}
					}
				}
			}
		}
	}
		public function allow_api_request( $status = true, $url = '', $args = [], $url_host = '' )
		{
			return true;
		}

	public function sync_receiving( $header = array(), $detail = array() )
	{
		if( ! $header || ! $detail ) return false;

		if( defined( 'WCWH_NO_INTEGRATE' ) && WCWH_NO_INTEGRATE ) return true;
		if( ! $this->setting[ $this->section_id ]['receive_sync'] ) return true;

		$response = [];
		foreach( $detail as $i => $row )
		{
			$sheetData = [];
			$succ = false;
			$proc = true;

			$filters = [ 
				'direction'=>'in',
				'wh_code' => $header['source_wh'],
				'section' => $row['section'], 
				'ref_id' => $row['ref_id'], 
				'status' => 1, 
				'handshake' => $row['id'], 
			];
			$exists = $this->Logic->get_infos( $filters, [], true, [] );

			$received_count = count( $row['details'] );
			$outcome = [ 'received_count' => $received_count ];

			if( $received_count != $row['sender_count'] )
			{
				$proc = false;
				$outcome['notification'] = 'Receiving Not Tally';
			}

			if( ! $exists && $proc )
			{
				try
				{
					switch( $row['section'] )
					{
						case 'wh_supplier':
							if ( !class_exists( "WCWH_Supplier_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/supplierCtrl.php" );
							$Inst = new WCWH_Supplier_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_items':
							if ( !class_exists( "WCWH_Item_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/itemCtrl.php" );
							$Inst = new WCWH_Item_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						//Bank In Service
						case 'wh_exchange_rate':
							if ( !class_exists( "WCWH_ExchangeRate_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/exchangeRateCtrl.php" );
							$Inst = new WCWH_ExchangeRate_Controller();
							
							$sheetData = $row['details'];
							
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_service_charge':
							if ( !class_exists( "WCWH_ServiceCharge_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/servicechargeCtrl.php" );
							$Inst = new WCWH_ServiceCharge_Controller();
							
							$sheetData = $row['details'];
							
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_items_group':
							if ( !class_exists( "WCWH_ItemGroup_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/itemGroupCtrl.php" );
							$Inst = new WCWH_ItemGroup_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_items_category':
							if ( !class_exists( "WCWH_ItemCategory_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/itemCategoryCtrl.php" );
							$Inst = new WCWH_ItemCategory_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_uom':
							if ( !class_exists( "WCWH_UOM_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/uomCtrl.php" );
							$Inst = new WCWH_UOM_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_pricing':
							if ( !class_exists( "WCWH_Pricing_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/pricingCtrl.php" );
							$Inst = new WCWH_Pricing_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData, [ 'exim_type'=>'for_integrate' ] );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_promo':
							if ( !class_exists( "WCWH_Promo_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/promoCtrl.php" );
							$Inst = new WCWH_Promo_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_delivery_order':
							if ( !class_exists( "WCWH_DeliveryOrder_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" );
							$Inst = new WCWH_DeliveryOrder_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_good_return':
							if ( !class_exists( "WCWH_GoodReturn_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/goodReturnCtrl.php" );
							$Inst = new WCWH_GoodReturn_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_purchase_request':
							if ( !class_exists( "WCWH_PurchaseRequest_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" );
							$Inst = new WCWH_PurchaseRequest_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_acc_period':
							if ( !class_exists( "WCWH_AccPeriod_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/accPeriodCtrl.php" );
							$Inst = new WCWH_AccPeriod_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'wh_stocktake_close':
							if ( !class_exists( "WCWH_StockTakeClose_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/stockTakeCloseCtrl.php" );
							$Inst = new WCWH_StockTakeClose_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						//------ jeff closing pr 12/8/22--------//
						case 'wh_closing_pr':
							if ( !class_exists( "WCWH_ClosingPR_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/closingPRCtrl.php" );
							$Inst = new WCWH_ClosingPR_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						//------ jeff closing pr 12/8/22--------//
						case 'wh_member_topup':
							if ( !class_exists( "WCWH_MemberTopup_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/memberTopupCtrl.php" );
							$Inst = new WCWH_MemberTopup_Controller();

							$sheetData = $row['details'];
							$succ = $Inst->import_data_handler( $sheetData );

							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
						case 'myinv_api_transaction':
						case 'myinv_lhdn_search_document':
						case 'myinv_lhdn_document':
							if( !empty( $row['details']['eirno'] ) )
							{
								switch( $row['details']['doc_type'] )
								{
									case 'invoice':
									case 'credit_note':
									case 'debit_note':
									case 'refund_note':
										if( !class_exists("WCWH_EInvoice_Controller") ) require_once( WCWH_DIR . "/includes/controller/eInvoiceCtrl.php" );
							    		$Inst = new WCWH_EInvoice_Controller();

							    		$succ = $Inst->myinvoice_handler( $row['section'], $row['details'] );

							    		if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
									break;
									case 'sb_invoice':
									case 'sb_credit_note':
									case 'sb_debit_note':
									case 'sb_refund_note':
										if( !class_exists("WCWH_SelfBill_Controller") ) require_once( WCWH_DIR . "/includes/controller/selfBillCtrl.php" );
							    		$Inst = new WCWH_SelfBill_Controller();

							    		$succ = $Inst->myinvoice_handler( $row['section'], $row['details'] );

							    		if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
									break;
								}
							}
						break;
						//By Jo
						case 'wh_task_schedule' || 'wh_task_checklist':
							if ( !class_exists( "WCWH_TaskSchedule_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/taskScheduleCtrl.php" );
							$Inst = new WCWH_TaskSchedule_Controller();

								$sheetData = $row['details'];
								$succ = $Inst->import_data_handler( $sheetData);
								
							if( method_exists( $Inst, '__destruct' ) ) $Inst->__destruct();
						break;
					}
				}
				catch (\Exception $e) 
			    {
			        $succ = false;
			        $outcome['notification'] = 'Internal handling error';
			    }
			}
			else if( $exists && $proc )	//exists which means previously success
			{
				$succ = true;
				$outcome['notification'] = 'Previously success';
			}
			
			if( $succ )
			{
				$inserted = [];
				if( ! $exists )
				{
					$datas = array(
						'direction' => 'in',
						'remote_url' => $header['source_url'],
						'wh_code' => $header['source_wh'],
						'section' => $row['section'],
						'ref_id' => $row['ref_id'],
						'ref' => $row['ref'],
						'details' => $row['details'],
						'handshake' => $row['id'],
						'lsync_at' => current_time( 'mysql' ),
					);
					$results = $this->action_handler( 'save', $datas, [], false );
					if( ! $results['succ'] )
					{
						$succ = false;
						$outcome['notification'] = 'Handshake update failed';
					}
					$inserted = $this->Logic->get_infos( $filters, [], true, [] );
				}
				else
				{
					$inserted = $exists;
				}

				$outcome['handshake'] = ( $inserted )? $inserted['id'] : 0;
			}
			else
			{
				$outcome['handshake'] = 0;
				$outcome['notification'] = 'Failed data handling';
			}

			$response[ $row['id'] ] = $outcome;

			if( ! $succ ) break;
		}

		return $response;
	}

	public function api_request( $action = '', $ref_id = 0, $target = '', $section = '', $ref_data = [], $remote_url = '' )
	{
		if( ! $action || ! $ref_id ) return false;

		$succ = true;
		$setting = $this->setting[ $this->section_id ];
		$timeout = ( $setting['connection_timeout'] )? $setting['connection_timeout'] : 60;

		if( defined( 'WCWH_NO_INTEGRATE' ) && WCWH_NO_INTEGRATE ) return true;
		if( ! $setting['use_sync'] ) return true;
		
		$data = $ref_data;
		switch( $action )
		{
			case 'close_purchase_request':
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id ], [], true );
				if( $doc )
				{
					$data = $doc;
				}

				$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
				if( $wh )
				{
					if( $wh['parent'] )
					{
						$parent = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$wh['parent'], 'status'=>1 ], [], true );
						if( $parent )
						{
							if( ! $target ) $target = $parent['code'];
						}
					}
				}
			break;
			case 'reopen_purchase_request':
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id ], [], true );
				if( $doc )
				{
					$data = $doc;
				}

				$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
				if( $wh )
				{
					if( $wh['parent'] )
					{
						$parent = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$wh['parent'], 'status'=>1 ], [], true );
						if( $parent )
						{
							if( ! $target ) $target = $parent['code'];
						}
					}
				}
			break;
			case 'unpost_purchase_request':
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id ], [], true );
				if( $doc )
				{
					$data = $doc;
				}

				$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
				if( $wh )
				{
					if( $wh['parent'] )
					{
						$parent = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$wh['parent'], 'status'=>1 ], [], true );
						if( $parent )
						{
							if( ! $target ) $target = $parent['code'];
						}
					}
				}
			break;
			case 'unpost_close_purchase_request':
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id ], [], true );
				if( $doc )
				{
					$data = $doc;
				}

				$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
				if( $wh )
				{
					if( $wh['parent'] )
					{
						$parent = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$wh['parent'], 'status'=>1 ], [], true );
						if( $parent )
						{
							if( ! $target ) $target = $parent['code'];
						}
					}
				}
			break;
			case 'unpost_delivery_order':
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id ], [], true );
				if( $doc )
				{
					$data = $doc;
				}

				if( ! $target ) $target = get_document_meta( $ref_id, 'supply_to_seller', 0, true );
			break;
			case 'post_do_revise':
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id ], [], true, [ 'meta'=>[ 'ref_doc', 'ref_doc_type', 'client_company_code', 'remark' ] ] );
				if( $doc )
				{
					$data = $doc;

					$detail = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$ref_id ], [], false, [ 'item'=>1 ] );
					if( sizeof( $detail ) )
						$data['detail'] = $detail;
				}

				if( ! $target ) $target = get_document_meta( $ref_id, 'supply_to_seller', 0, true );
			break;
			case 'unpost_do_revise':
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id ], [], true );
				if( $doc )
				{
					$data = $doc;
				}

				if( ! $target ) $target = get_document_meta( $ref_id, 'supply_to_seller', 0, true );
			break;
			case 'unpost_good_return':
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id ], [], true );
				if( $doc )
				{
					$data = $doc;
				}

				if( ! $target ) $target = get_document_meta( $ref_id, 'supplier_warehouse_code', 0, true );
			break;
			case 'update_bankin_info':
				if(!$ref_data)
				{
					return [ 'succ'=>0, 'notice'=>'insufficient-data' ];
				}
				$data = $ref_data['header'];
				if( ! $target ) $target = get_document_meta( $ref_id, 'supplier_warehouse_code', 0, true );
			break;
			case 'update_customer_remittance_info':
				if(!$ref_data) return [ 'succ'=>0, 'notice'=>'insufficient-data' ];				
				if( ! $target ) return [ 'succ'=>0, 'notice'=>'Undefined Sync Target'];
				$data = $ref_data;
			break;
			case 'membership_new_no':
				if(!$ref_data) return [ 'succ'=>0, 'notice'=>'insufficient-data' ];				
				if( ! $target ) return [ 'succ'=>0, 'notice'=>'Undefined Sync Target'];
				$data = $ref_data;
			break;
			case 'membership_reset_pin':
				if(!$ref_data) return [ 'succ'=>0, 'notice'=>'insufficient-data' ];				
				if( ! $target ) return [ 'succ'=>0, 'notice'=>'Undefined Sync Target'];
				$data = $ref_data;
			break;
			case 'unpost_myinvoice':
				$data = $ref_data;
				if( empty( $ref_data ) )
				{
					$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id, 'doc_type'=>'none' ], [], true );
					if( $doc )
					{
						$data = $doc;
						$data['integrate_type'] = $target;
						$data['eirno'] = $doc['doc_id'];
					}
				}

				if( empty( $target ) ) $target = $this->refs['einv_id'];
				if( empty( $remote_url ) ) $remote_url = $this->refs['einv_url'];
			break;
			case 'cancel_myinvoice':
				$data = [];
				$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$ref_id, 'doc_type'=>'none' ], [], true, [ 'meta'=>['uuid'], 'company'=>1 ]);
				if( $doc )
				{
					$data['uuid'] = $doc['uuid'];
					$data['client_tin'] = str_replace(' ', '', $doc['tin']);
					$data['remark'] = $ref_data[0]['remark'];
				}

				if( empty( $target ) ) $target = $this->refs['einv_id'];
				if( empty( $remote_url ) ) $remote_url = $this->refs['einv_url'];
			break;
			case 'check_db_table':
				$data = $target;
			break;
		}

		if( empty( $target ) ) return [ 'succ'=>0, 'notice'=>'Receiver Not Exists' ];
		if( empty( $data ) ) return [ 'succ'=>0, 'notice'=>'Receiver Not Exists' ];

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		$target_wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$target ], [], true );
		if( empty( $remote_url ) ) $remote_url = get_warehouse_meta( $target_wh['id'], 'api_url', true );

		$datas = [
			'handshake' => 'wcwh_request_api',
			'secret' => md5( 'wcx1'.md5( $target.'wcwh_request_api' ) ),
			'source_wh' => $wh['code'],
			'wh_code' => $target,
			'source_url' => get_warehouse_meta( $wh['id'], 'api_url', true ),
			'action' => $action,
			'datas' => $data,
		];
		
		if( defined( 'developer_debug' ) ){ pd( "Remote Url: ".$remote_url ); pd( $datas ); }
			
		add_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10, 4 );
		$response = wp_remote_post( $remote_url, [ 'timeout'=>$timeout, 'body'=>$datas, 'sslverify' => false ] );
		remove_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10 );
		
		if( ! is_wp_error( $response ) ) 
		{
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			if( defined( 'developer_debug' ) ){ pd( $response ); }
			
			if( $response && $response['connection'] && $response['authenticated'] ) 
			{	
				if( $response['result'] )
				{
					return [ 'succ'=>1, 'result'=>$response['result'], 'notice'=>$response['result']['notification'] ];
				}
			}
		}
		else
		{
			if( defined( 'developer_debug' ) ){ pd( $response->get_error_message() ); }

			return [ 'succ'=>0, 'notice'=>'Remote Connection Error' ];
		}

		return [ 'succ'=>0, 'notice'=>'Remote Connection Checking Failed' ];
	}

	public function sync_responding( $action = '', $datas = [] )
	{
		if( ! $action || ! $datas ) return false;

		if( defined( 'WCWH_NO_INTEGRATE' ) && WCWH_NO_INTEGRATE ) return true;
		if( ! $this->setting[ $this->section_id ]['receive_sync'] ) return true;

		$succ = true;
		$response = [];
		try
		{
			switch( $action )
			{
				case 'minimart_connection':
					$datas['connect'] = 'success';
					$response['datas'] = $datas;
				break;
				case 'close_purchase_request':
					if ( !class_exists( "WCWH_RemoteCPR_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/remoteCPRCtrl.php" );
					$Inst = new WCWH_RemoteCPR_Controller();

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'], 'sdocno'=>$datas['sdocno'], 'warehouse_id'=>$datas['warehouse_id'] ], [], true, [ 'usage'=>1 ] );
					if( $doc )
					{
						if( $doc['status'] != 6 )
						{
							$succ = false;
							$response['notification'] = 'Document Status Does Not Match/Identical with Partner Side';
						}
						else
						{
							if( $succ && $doc['status'] == 6 )
							{
								$Inst->skip_strict_co = true;
								$result = $Inst->action_handler( 'close', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document close failed on partner side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 10;
			                    }
							}
						}

						$response['datas'] = $doc;
					}
				break;
				case 'reopen_purchase_request':
					if ( !class_exists( "WCWH_RemoteCPR_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/remoteCPRCtrl.php" );
					$Inst = new WCWH_RemoteCPR_Controller();

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'], 'sdocno'=>$datas['sdocno'], 'warehouse_id'=>$datas['warehouse_id'] ], [], true, [ 'usage'=>1 ] );
					if( $doc )
					{
						if( $doc['status'] != 10 )
						{
							$succ = false;
							$response['notification'] = 'Document Status Does Not Matched/Identical with Partner Side';
						}
						else
						{
							if( $succ && $doc['status'] == 10 )
							{
								$Inst->skip_strict_co = true;
								$result = $Inst->action_handler( 'reopen', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document re-open failed on partner side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 6;
			                    }
							}
						}

						$response['datas'] = $doc;
					}
				break;
				case 'unpost_purchase_request':
					if ( !class_exists( "WCWH_PurchaseRequest_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" );
					$Inst = new WCWH_PurchaseRequest_Controller();

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'], 'sdocno'=>$datas['sdocno'], 'warehouse_id'=>$datas['warehouse_id'] ], [], true, [ 'usage'=>1 ] );
					if( $doc )
					{
						if( $doc['status'] > 6 || $doc['t_uqty'] > 0 )
						{
							$succ = false;
							$response['notification'] = 'Document used, please advise partner side to revert';
						}
						else
						{
							if( $succ && $doc['status'] == 6 )
							{
								$Inst->skip_strict_unpost = true;
								$result = $Inst->action_handler( 'unpost', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document unpost failed on partner side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 1;
			                    }
							}
							if( $succ && $doc['status'] == 1 )
							{
								$result = $Inst->action_handler( 'delete', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document delete failed on partner side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 0;
			                    }
							}
						}

						$response['datas'] = $doc;
					}
				break;
				case 'unpost_close_purchase_request':
					if ( !class_exists( "WCWH_ClosingPR_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/closingPRCtrl.php" );
					$Inst = new WCWH_ClosingPR_Controller();

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'], 'sdocno'=>$datas['sdocno'], 'warehouse_id'=>$datas['warehouse_id'] ], [], true, [ 'usage'=>1 ] );
					if( $doc )
					{
						if( $doc['status'] > 6 || $doc['t_uqty'] > 0 )
						{
							$succ = false;
							$response['notification'] = 'Document used, please advise partner side to revert';
						}
						else
						{
							if( $succ && $doc['status'] == 6 )
							{
								$Inst->skip_strict_unpost = true;
								$result = $Inst->action_handler( 'unpost', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document unpost failed on partner side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 1;
			                    }
							}
							if( $succ && $doc['status'] == 1 )
							{
								$result = $Inst->action_handler( 'delete', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document delete failed on partner side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 0;
			                    }
							}
						}

						$response['datas'] = $doc;
					}
				break;
				case 'unpost_delivery_order':
					if ( !class_exists( "WCWH_DeliveryOrder_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" );
					$Inst = new WCWH_DeliveryOrder_Controller();

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'], 'sdocno'=>$datas['sdocno'], 'warehouse_id'=>$datas['warehouse_id'] ], [], true, [ 'usage'=>1 ] );
					if( $doc )
					{
						if( $doc['status'] > 6 || $doc['t_uqty'] > 0 )
						{
							$succ = false;
							$response['notification'] = 'Document received, please advise Receiver side to revert';
						}
						else
						{
							if( $succ && $doc['status'] == 6 )
							{
								$Inst->skip_strict_unpost = true;
								$result = $Inst->action_handler( 'unpost', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document unpost failed on Receiver side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 1;
			                    }
							}
							if( $succ && $doc['status'] == 1 )
							{
								$result = $Inst->action_handler( 'delete', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document delete failed on Receiver side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 0;
			                    }
							}
						}

						$response['datas'] = $doc;
					}
				break;
				case 'post_do_revise':
					if ( !class_exists( "WCWH_DORevise_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/DOReviseCtrl.php" );
					$Inst = new WCWH_DORevise_Controller();
					$Inst->outlet_post = false;

					if( empty( $datas['detail'] ) ) $succ = false;

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['ref_doc'], 'doc_type'=>$datas['ref_doc_type'] ], [], true, [ 'usage'=>1, 'posting'=>1 ] );
					if( $doc && $succ )
					{
						$header = [
							'docno' => $datas['docno'],
							'doc_date' => $datas['doc_date'],
							'post_date' => '',
							'client_company_code' => $datas['client_company_code'],
							'ref_doc_id' => $doc['doc_id'],
							'remark' => $datas['remark'],
							'warehouse_id' => $doc['warehouse_id'],
						];

						$details = [];
						foreach( $datas['detail'] as $i => $row )
						{
							$found_item = apply_filters( 'wcwh_get_item', [ 'serial'=>$row['prdt_serial'] ], [], true, [] );
							$doc_item = $Inst->Logic->get_detail( [ 'doc_id'=>$doc['doc_id'], 'product_id'=>$found_item['id'] ], [], true, [ 'usage'=>1 ] );

							if( $row['bqty'] > ( $doc_item['bqty'] - $doc_item['uqty'] ) )
							{
								$succ = false;
								$response['notification'] = "Receiver side insufficient deduction: Item {$found_item['code']}, {$found_item['name']} {$row['bqty']}/".( $doc_item['bqty'] - $doc_item['uqty'] );
							}

							$detail = [
								'bqty' => $row['bqty'],
								'product_id' => $found_item['id'],
								'item_id' => '',
								'ref_doc_id' => $doc['doc_id'],
								'ref_item_id' => $doc_item['item_id'],
							];

							$details[] = $detail;
						}

						if( $succ )
						{
							$do_revise = [
								'header' => $header,
								'detail' => $details,
							];
							$result = $Inst->action_handler( 'save', $do_revise );
				            if( ! $result['succ'] )
				            {
				                $succ = false;
				                $response['notification'] = 'Document create failed on Receiver side';
				            }
				            else
				            {
				            	$newdoc = $Inst->Logic->get_header( [ 'doc_id'=>$result['id'], 'doc_type'=>'do_revise' ], [], true, [ 'usage'=>1 ] );
				            
				            	if( $succ && $newdoc && $newdoc['status'] == 1 )
								{
									$Inst->Logic->useInventory = false;
									$result = $Inst->action_handler( 'post', [ 'id'=>$newdoc['doc_id'] ] );
					                if( ! $result['succ'] )
					                {
					                    $succ = false;
					                    $response['notification'] = 'Document posting failed on Receiver side';
					                }
					                else
					                {
					                	$doc['status'] = 0;
					                }
								}
				            }
						}
						
						$response['datas'] = $doc;
					}
				break;
				case 'unpost_do_revise':
					if ( !class_exists( "WCWH_DORevise_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/DOReviseCtrl.php" );
					$Inst = new WCWH_DORevise_Controller();
					$Inst->outlet_post = false;

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'] ], [], true, [ 'usage'=>1 ] );
					if( $doc )
					{
						if( $succ && $doc['status'] == 6 )
						{
							$result = $Inst->action_handler( 'unpost', [ 'id'=>$doc['doc_id'] ] );
			                if( ! $result['succ'] )
			                {
			                    $succ = false;
			                    $response['notification'] = 'Document unpost failed on Receiver side';
			                }
			                else
			                {
			                	$doc['status'] = 1;
			                }
						}
						if( $succ && $doc['status'] == 1 )
						{
							$result = $Inst->action_handler( 'delete', [ 'id'=>$doc['doc_id'] ] );
			                if( ! $result['succ'] )
			                {
			                    $succ = false;
			                    $response['notification'] = 'Document delete failed on Receiver side';
			                }
			                else
			                {
			                	$doc['status'] = 0;
			                }
						}

						$response['datas'] = $doc;
					}
				break;
				case 'unpost_good_return':
					if ( !class_exists( "WCWH_GoodReturn_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/goodReturnCtrl.php" );
					$Inst = new WCWH_GoodReturn_Controller();

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'], 'sdocno'=>$datas['sdocno'], 'warehouse_id'=>$datas['warehouse_id'] ], [], true, [ 'usage'=>1 ] );
					if( $doc )
					{
						if( $doc['status'] > 6 || $doc['t_uqty'] > 0 )
						{
							$succ = false;
							$response['notification'] = 'Document used, please advise Receiver side to revert';
						}
						else
						{
							if( $succ && $doc['status'] == 6 )
							{
								$result = $Inst->action_handler( 'unpost', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document unpost failed on Receiver side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 1;
			                    }
							}
							if( $succ && $doc['status'] == 1 )
							{
								$result = $Inst->action_handler( 'delete', [ 'id'=>$doc['doc_id'] ] );
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document delete failed on Receiver side';
			                    }
			                    else
			                    {
			                    	$doc['status'] = 0;
			                    }
							}
						}

						$response['datas'] = $doc;
					}
				break;
				case 'update_bankin_info':
					if ( !class_exists( "WCWH_BankInService_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/bankinserviceCtrl.php" );
					$Inst = new WCWH_BankInService_Controller();

					$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'], 'sdocno'=>$datas['sdocno'], 'warehouse_id'=>$datas['warehouse_id'] ], [], true, [ 'usage'=>1 ] );
					if( $doc )
					{
						if( $doc['status'] > 6 )
						{
							$succ = false;
							$response['notification'] = 'Document received, please advise Receiver side to revert';
						}
						else
						{
							if( $succ && $doc['status'] <= 6 )
							{
								$doc = [];
								$doc['header'] = $datas;
								$result = $Inst->action_handler( 'update', $doc, '');
			                    if( ! $result['succ'] )
			                    {
			                        $succ = false;
			                        $response['notification'] = 'Document update failed on Receiver side';
			                    }
			                    else
			                    {
			                    	$doc = $Inst->Logic->get_header( [ 'docno'=>$datas['docno'], 'sdocno'=>$datas['sdocno'], 'warehouse_id'=>$datas['warehouse_id'] ], [], true, [ 'usage'=>1 ] );
			                    	$metas = $Inst->Logic->get_document_meta( $doc['doc_id'] );
									$doc = $Inst->combine_meta_data( $doc, $metas );
			                    }
							}
						}

						$response['datas'] = $doc;
					}
					else
					{
						$succ = false;
						$response['notification'] = 'Document not found.';
					}
				break;
				case 'update_customer_remittance_info':
					if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
					$Inst = new WCWH_BankInInfo_Controller();

					$exist = $Inst->Logic->get_infos( [ 'id'=>$datas['id'] ], [], true, [ 'usage'=>1 ] );
					if( $exist )
					{
						$result = $Inst->action_handler( 'update', $datas);
						if( !$result['succ'])
						{
							$succ = false;
							$response['notification'] = 'Update failed on Receiver side';
						}
						else
						{
							$datas = $Inst->Logic->get_infos( [ 'id'=>$datas['id'] ], [], true, [ 'usage'=>1 ] );

						}

						$response['datas'] = $datas;
					}
					else
					{
						$succ = false;
						$response['notification'] = 'Customer ID Record not found.';
					}
				break;
				case 'membership_new_no':
					if ( !class_exists( "WCWH_Membership_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/membershipCtrl.php" );
					$Inst = new WCWH_Membership_Controller();

					$exist = $Inst->Logic->get_infos( [ 'id'=>$datas['id'] ], [], true, [] );
					if( $exist )
					{
						$result = $Inst->action_handler( 'new-serial', $datas);
						if( !$result['succ'])
						{
							$succ = false;
							$response['notification'] = 'Update failed on Receiver side';
						}
						else
						{
							$datas = $Inst->Logic->get_infos( [ 'id'=>$datas['id'] ], [], true, [ 'usage'=>1 ] );

						}

						$response['datas'] = $datas;
					}
					else
					{
						$succ = false;
						$response['notification'] = 'Member Record not found.';
					}
				break;
				case 'membership_reset_pin':
					if ( !class_exists( "WCWH_Membership_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/membershipCtrl.php" );
					$Inst = new WCWH_Membership_Controller();

					$exist = $Inst->Logic->get_infos( [ 'id'=>$datas['id'] ], [], true, [] );
					if( $exist )
					{
						$result = $Inst->action_handler( 'reset-pin', $datas);
						if( !$result['succ'])
						{
							$succ = false;
							$response['notification'] = 'Update failed on Receiver side';
						}
						else
						{
							$datas = $result['modal_data'];

						}

						$response['datas'] = $datas;
					}
					else
					{
						$succ = false;
						$response['notification'] = 'Member Record not found.';
					}
				break;
				case 'check_db_table':
					$response['datas'] = $this->dbCount();
				break;
			}
		}
		catch (\Exception $e) 
		{
			$succ = false;
			$response['notification'] = 'Internal handling error';
		}
		
		$response['succ'] = $succ? 1 : 0;

		return $response;
	}

	/*
	public function sync_remote_api_deprecated( $sync_id = 0 )
	{
		$succ = true;
		$setting = $this->setting[ $this->section_id ];

		if( defined( 'WCWH_NO_INTEGRATE' ) && WCWH_NO_INTEGRATE ) return true;
		if( ! $setting['use_sync'] ) return true;
		
		$data_per_connect = ( $setting['data_per_connect'] )? $setting['data_per_connect'] : 20;
		$timeout = ( $setting['connection_timeout'] )? $setting['connection_timeout'] : 60;

		if( $sync_id )
		{
			$ft = [ 'id'=>$sync_id, 'direction'=>'out', 'status'=>1, 'handshake'=>0 ];
			$outstanding = $this->Logic->get_infos( $ft, [ 'a.lupdate_at'=>'ASC' ], false, [], [], [ 0, $data_per_connect ] );
		}
		else
		{
			$ft = [ 'direction'=>'out', 'status'=>1, 'handshake'=>0 ];
			$outstanding = $this->Logic->get_infos( $ft, [ 'a.lupdate_at'=>'ASC' ], false, [], [], [ 0, $data_per_connect ] );
		}
		
		if( $outstanding )
		{
			$connections = []; $syncs = [];
			foreach( $outstanding as $transaction )
			{
				$details = array();
				switch( $transaction['section'] )
				{
					case 'wh_items':
						if ( !class_exists( "WCWH_Item_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item.php" );
						$Inst = new WCWH_Item_Class();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'] ] );
						if( $details )
						{
							foreach( $details as $i => $detail )
							{
								if( is_json( $detail['serial2'] ) ) $detail['serial2'] = json_decode( stripslashes( $detail['serial2'] ), true );
								if( $detail['serial2'] && ! is_array( $detail['serial2'] ) ) $detail['serial2'] = [ $detail['serial2'] ];
								
								$details[ $i ]['serial2'] = $detail['serial2'];
							}
						}
					break;
					case 'wh_items_group':
					case 'wh_items_category':
					case 'wh_uom':
						$details = json_decode( $transaction['details'], true );
					break;
					case 'wh_pricing':
						if ( !class_exists( "WCWH_Pricing" ) ) require_once( WCWH_DIR . "/includes/classes/pricing.php" );
						$Inst = new WCWH_Pricing();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'], 'seller'=>$transaction['wh_code'] ] );
					break;
					case 'wh_promo':
						if ( !class_exists( "WCWH_PromoHeader" ) ) require_once( WCWH_DIR . "/includes/classes/promo-header.php" );
						$Inst = new WCWH_PromoHeader();

						$details = $Inst->get_export_data( [ 'id'=>$transaction['ref_id'], 'seller'=>$transaction['wh_code'] ] );
					break;
					case 'wh_delivery_order':
						if ( !class_exists( "WCWH_DeliveryOrder_Class" ) ) require_once( WCWH_DIR . "/includes/classes/delivery-order.php" );
						$Inst = new WCWH_DeliveryOrder_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					case 'wh_good_return':
						if ( !class_exists( "WCWH_GoodReturn_Class" ) ) require_once( WCWH_DIR . "/includes/classes/good-return.php" );
						$Inst = new WCWH_GoodReturn_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					case 'wh_purchase_request':
						if ( !class_exists( "WCWH_PurchaseRequest_Class" ) ) require_once( WCWH_DIR . "/includes/classes/purchase-request.php" );
						$Inst = new WCWH_PurchaseRequest_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
					case 'wh_acc_period':
						if ( !class_exists( "WCWH_AccPeriod_Class" ) ) require_once( WCWH_DIR . "/includes/classes/acc-period.php" );
						$Inst = new WCWH_AccPeriod_Class();

						$details = $Inst->get_export_data( [ 'doc_id'=>$transaction['ref_id'] ] );
					break;
				}

				if( $details )
				{
					$transaction['details'] = $details;
					$transaction['sender_count'] = count( $details );
					$connections[ $transaction['wh_code'] ][] = $transaction;

					$syncs[ $transaction['id'] ] = $details;
				}
			}
			
			if( $connections )
			{
				$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );

				foreach( $connections as $wh_code => $connection )
				{
					$datas = [
						'handshake' => 'wcwh_remote_api',
						'secret' => md5( 'wcx1'.md5( $wh_code.'wcwh_remote_api' ) ),
						'source_wh' => $wh['code'],
						'source_url' => get_warehouse_meta( $wh['id'], 'api_url', true ),
					];
					$remote_url = '';

					foreach( $connection as $i => $row )
					{
						$remote_url = $row['remote_url'];
						$datas['datas'][] = $row;
					}
					
					if( defined( 'developer_debug' ) ){ pd( "Remote Url: ".$remote_url ); pd( $datas ); }
					
					add_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10, 4 );
					$response = wp_remote_post( $remote_url, [ 'timeout'=>$timeout, 'body'=>$datas ] );
					remove_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10 );
					
					if( ! is_wp_error( $response ) ) 
					{
						$response = json_decode( wp_remote_retrieve_body( $response ), true );
						if( defined( 'developer_debug' ) ){ pd( $response ); }
						if( $response && $response['connection'] && $response['authenticated'] ) 
						{	
							if( $response['result'] && is_array( $response['result'] ) )
							{
								foreach( $response['result'] as $id => $vals )
								{
									$datas = array(
										'id' => $id,
										'details' => $syncs[ $id ],
										'handshake' => ( $vals['handshake'] )? $vals['handshake'] : 0,
										'notification' => ( $vals['notification'] )? $vals['notification'] : '',
										'lsync_at' => current_time( 'mysql' ),
									);
									$results = $this->action_handler( 'update', $datas, [], false );
									if( ! $results['succ'] )
									{
										$succ = false;
									}
								}
							}
						}
					}
					else
					{
						if( defined( 'developer_debug' ) ){ pd( $response->get_error_message() ); }
					}
				}
			}
		}
	}
	*/


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_fragment( $type = 'sync' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'save':
				if( current_user_cans( [ 'wh_support' ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Sync" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Sync"
				>
					<?php echo $actions['save'] ?> Sync
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'sync':
			default:
				if( current_user_cans( [ 'wh_support' ] ) ):
			?>
				<button id="sync_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['sync'] ?>"
					data-title="<?php echo $actions['sync'] ?>" 
					data-action="sync_reference" data-service="<?php echo $this->section_id; ?>_action"  
				>
					<?php echo $actions['sync'] ?>
					<i class="fa fa-handshake" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook' 		=> $this->section_id.'_form',
			'action' 	=> 'save',
			'token' 	=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
		);

		if( $id )
		{
			$datas = $this->Logic->get_infos( [ 'id' => $id ], [], true, [] );
			if( $datas )
			{
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				
				$args['data'] = $datas;
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/sync-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/sync-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/syncListing.php" ); 
			$Inst = new WCWH_SYNC_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->styles = [
				'#id' => [ 'width' => '70px' ],
				'#direction' => [ 'width' => '90px' ],
				'#wh_code' => [ 'width' => '90px' ],
				'#ref_id' => [ 'width' => '70px' ],
				'#status' => [ 'width' => '90px' ],
				'#handshake' => [ 'width' => '90px' ],
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