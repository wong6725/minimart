<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_Warehouse_Class' ) ) include_once( WCWH_DIR . "/includes/classes/warehouses.php" ); 
if ( !class_exists( "WCWH_Addresses_Class" ) ) include_once( WCWH_DIR . "/includes/classes/addresses.php" ); 

if( ! class_exists( 'WCWH_Storage_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/storageCtrl.php" ); 

if ( !class_exists( "WCWH_Warehouse_Controller" ) ) 
{

class WCWH_Warehouse_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_warehouse";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Warehouse_Controller";

	public $Logic;
	public $Storage;
	public $Addr;

	public $tplName = array(
		'new' => 'newWarehouse',
	);

	public $useFlag = false;

	public $wh_capabilities = array( 
		array( "key" => "purchase_request", "title" => "Purchase Request" ),
		array( "key" => "purchase_order", "title" => "Purchase Order" ),
		array( "key" => "good_receive", "title" => "Goods Receipt" ),
		array( "key" => "good_return", "title" => "Goods Return" ),
		array( "key" => "reprocess", "title" => "Reprocess" ),
		array( "key" => "sales_order", "title" => "Sales Order" ),
		array( "key" => "transfer_order", "title" => "Transfer Order" ),
		array( "key" => "good_issue", "title" => "Goods Issue" ),
		array( "key" => "delivery_order", "title" => "Delivery Order" ),
		array( "key" => "block_stock", "title" => "Block Stock" ),
		array( "key" => "transfer_item", "title" => "Transfer Item" ),
		array( "key" => "stock_adjust", "title" => "Stock Adjust" ),
		array( "key" => "stocktake", "title" => "StockTake" ),
		array( "key" => "pos_transact", "title" => "Pos Transact" ),
		array( "key" => "storage", "title" => "Storage" ),
		//array( "key" => "storing", "title" => "Storing" ),
		//array( "key" => "picking", "title" => "Picking" ),
	);

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
		$this->Logic = new WCWH_Warehouse_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;

		$this->Storage = new WCWH_Storage_Controller();

		$this->Addr = new WCWH_Addresses_Class( $this->db_wpdb );
		$this->Addr->set_section_id( $this->section_id );
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
			'code' => '',
			'name' => '',
			'comp_id' => 0,
			'capability' => '',
			'indication' => 0,
			'visible' => 0,
			'parent' => 0,
			'status' => 1,
			'flag' => ( $this->useFlag )? 0 : 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}

	protected function get_defaultAddrFields()
	{
		return array(
			'ref_type' => '',
			'ref_id' => 0,
			'addr_type' => '',
			'address_1' => '',
			'address_2' => '',
			'country' => '',
			'state' => '',
			'city' => '',
			'postcode' => '',
			'contact_person' => '',
			'contact_no' => '',
			'status' => 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}

	protected function get_uniqueFields()
	{
		return array(
			'code'
		);
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
				case 'restore':
				case 'delete':
				case 'approve':
				case 'reject':
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

	public function validate_unique( $action, $datas = array() )
	{
		$succ = true;

		$unique = $this->get_uniqueFields();
		if( $unique )
		{
			foreach( $unique as $key )
			{
				$result = $this->Logic->get_infos( [ $key => $datas[$key] ], [], true );
				if( $result ) 
				{	
					if( ! $datas[ $this->get_primaryKey() ] || 
						( $datas[ $this->get_primaryKey() ] && $datas[ $this->get_primaryKey() ] != $result[ $this->get_primaryKey() ] ) )
					{
						$succ = false;
					}
				}
			}
		}

		if( ! $succ )
			$this->Notices->set_notice( 'not-unique', 'error' );

		return $succ;
	}

	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$isSave = false;
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$datas['lupdate_by'] = $user_id;
			$datas['lupdate_at'] = $now;

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$datas = $this->data_sanitizing( $datas );

					$address = $datas['address']; unset( $datas['address'] );
					
					$datas['indication'] = ( $datas['indication'] )? 1 : 0;
					$datas['visible'] = ( $datas['visible'] )? 1 : 0;
					
					if( ! $datas['code'] )
					{
						if( $datas[ $this->get_primaryKey() ] )
						{
							$scode = get_warehouse_meta( $datas[ $this->get_primaryKey() ], 'scode', true );
							$datas['code'] = ( $scode )? $scode : $datas['code'];
							
							if( ! $datas['code'] )
							{
								$exist = $this->Logic->get_infos( [ $this->get_primaryKey() => $datas[ $this->get_primaryKey() ] ], [], true );
								$datas['code'] = ( $exist['code'] )? $exist['code'] : $datas['code'];
							}
						}
						$this->temp_data = $datas;
							if( empty( $datas['code'] ) )
							{
								$datas['scode'] = apply_filters( 'warehouse_generate_docno', $datas['code'], $this->section_id );
								$datas['code'] = $datas['scode'];
							}
						$this->temp_data = array();
					}

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];
			
					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
						}

						$datas['created_by'] = $user_id;
						$datas['created_at'] = $now;

						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
						$isSave = true;
					}

					if( $datas[ $this->get_primaryKey() ] && $action == 'update' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
						}

						if( $datas['parent'] == $datas[ $this->get_primaryKey() ] ) $datas['parent'] = 0;
					}

					$datas = $this->json_encoding( $datas );
					$metas = $this->json_encoding( $metas );

					if( $succ )
					{
						$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $succ )
						{
							$outcome['id'][] = $result['id'];
							//$outcome['data'][] = $result['data'];

							if( ! $metas['has_pos'] ) delete_warehouse_meta( $result['id'], 'has_pos' );
							if( ! $metas['view_outlet'] ) delete_warehouse_meta( $result['id'], 'view_outlet' );
							if( ! $metas['hidden'] ) delete_warehouse_meta( $result['id'], 'hidden' );

							if( $isSave )
							{
								//Doc Stage
						        $stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
						            'ref_type'		=> $this->section_id,
						            'ref_id'		=> $result['id'],
						            'action'        => $action,
						            'status'    	=> 1,
						        ] );
						    }

						    $addr_ids = array();
						    $exists = $this->Addr->get_infos( [ 'ref_type'=>$this->section_id, 'ref_id'=>$result['id'], 'addr_type'=>'default' ] );
						    if( $exists )
							{
								foreach( $exists as $exist )
								{	
									$addr_ids[] = $exist['id'];
								}
							}
							
							if( $address && ( 
								!empty( trim( $address['contact_person'] ) ) 
								|| !empty( trim( $address['contact_no'] ) )
								|| !empty( trim( $address['address_1'] ) ) 
							) )
							{
								$address['ref_type'] = $this->section_id;
								$address['ref_id'] = $result['id'];
								$address['addr_type'] = 'default';

								$address['lupdate_by'] = $user_id;
								$address['lupdate_at'] = $now;

								if( ! $address['id'] || ! in_array( $address['id'], $addr_ids ) )	//save
								{
									$address['created_by'] = $user_id;
									$address['created_at'] = $now;
									unset($address['id']);

									$address = wp_parse_args( $address, $this->get_defaultAddrFields() );

									$child_result = $this->Addr->action_handler( 'save', $address );
								}
								else if( $address['id'] && in_array( $address['id'], $addr_ids ) )	//update
								{
									$child_result = $this->Addr->action_handler( 'update', $address );
								}

								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}
							}
						}
					}
				break;
				case "delete":
				case "delete-permanent":
				case "restore":
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
							
							if( $succ )
							{
								$outcome['id'][] = $result['id'];

								//Doc Stage
								$dat = $result['data'];
								$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
								    'ref_type'	=> $this->section_id,
								    'ref_id'	=> $result['id'],
								    'action'	=> $action,
								    'status'    => $dat['status'],
								    'remark'	=> ( $metas['remark'] )? $metas['remark'] : '',
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
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];
					
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$succ = apply_filters( 'wcwh_todo_external_action', $id, $this->section_id, $action, ( $metas['remark'] )? $metas['remark'] : '' );
							if( $succ )
							{
								$status = apply_filters( 'wcwh_get_status', $action );
							
								$datas['flag'] = 0;
								$datas['flag'] = ( $status > 0 )? 1 : ( ( $status < 0 )? -1 : $datas['flag'] );

								$datas['id'] = $id;
								$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}

								if( $succ )
								{
									$outcome['id'][] = $result['id'];

									//Doc Stage
									$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
									    'ref_type'	=> $this->section_id,
									    'ref_id'	=> $result['id'],
									    'action'	=> $action,
									    'status'    => $status,
									    'remark'	=> ( $metas['remark'] )? $metas['remark'] : '',
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

		if( $succ )
		{
			$id = is_array( $id )? $id : [ $id ];

			foreach( $id as $ref_id )
			{
				$succ = apply_filters( 'wcwh_todo_arrangement', $ref_id, $this->section_id, $action );
				if( ! $succ )
				{
					$this->Notices->set_notice( 'arrange-fail', 'error' );
				}
			}
		}

		if( $succ )
		{	
			$exists = $this->Logic->get_infos( [ 'id' => $id ], [], true );
			if( ! $exists )
			{
				$succ = false;
				$this->Notices->set_notice( 'invalid-record', 'error' );
			}
			else
			{
				$storages = array(
					array(
						'wh_code' => $exists['code'],
						'name'	=> 'Inventory',
						'sys_reserved' => 'staging',
						'storable' => 1,
						'single_sku' => 0,
						'stackable' => 1,
					),
					array(
						'wh_code' => $exists['code'],
						'name'	=> 'Block Stocks Area',
						'sys_reserved' => 'block_staging',
						'storable' => 1,
						'single_sku' => 0,
						'stackable' => 1,
					),
				);

				switch( $action )
				{
					case 'save':
						foreach( $storages as $storage )
						{
							$result = $this->Storage->action_handler( 'save', $storage );
							if( ! $result['succ'] )
							{
								$succ = false;
							}
						}
					break;
				}
			}
		}

		return $succ;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Warehouse" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Warehouse"
				>
					<?php echo $actions['save'] ?> Warehouse
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
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
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'capability'	=> $this->wh_capabilities,
			'permission'	=> apply_filters( 'wcwh_get_i18n', 'major-permission' )
		);

		if( $id )
		{
			$datas = $this->Logic->get_infos( [ 'id' => $id ], [], true, [ 'parent'=>true, 'company'=>true ] );
			if( $datas )
			{
				$metas = get_warehouse_meta( $id );

				$filters = [ 'ref_type'=>$this->section_id, 'ref_id'=>$id, 'addr_type'=>'default' ];
				$addr = $this->Addr->get_infos( $filters, [], true );
				if( $addr )
				{
					$datas['address'] = $addr;
				}

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$datas['capability'] = ( is_json( $datas['capability'] ) )? json_decode( $datas['capability'], true ) : $datas['capability'];
				
				$args['data'] = $datas;
				if( $metas )
				{
					foreach( $metas as $key => $value )
					{
						$args['data'][$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
						if( is_json( $args['data'][$key] ) )
						{
							$args['data'][$key] = json_decode( $args['data'][$key], true );
						}
					}
				}
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/warehouse-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/warehouse-form.php', $args );
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
			include_once( WCWH_DIR."/includes/listing/warehouseListing.php" ); 
			$Inst = new WCWH_Warehouse_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->wh_capabilities = $this->wh_capabilities;
			$Inst->useFlag = $this->useFlag;

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

			$datas = $this->Logic->get_infos( $filters, $order, false, [ 'parent'=>true, 'company'=>true, 'tree'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}