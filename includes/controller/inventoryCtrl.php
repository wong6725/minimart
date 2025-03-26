<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Inventory_Class" ) ) include_once( WCWH_DIR . "/includes/classes/inventory.php" ); 

if ( !class_exists( "WCWH_Inventory_Controller" ) ) 
{

class WCWH_Inventory_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_inventory";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Inventory_Controller";

	public $Logic;

	public $tplName = array(
		'export' => 'exportInv',
	);

	protected $warehouse = array();
	protected $view_outlet = false;

	public $filters = array();

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_Inventory_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
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
				case "export":
					$datas['filename'] = 'Inventory';

					$params = [];

					$params['to_date'] = !empty( $datas['to_date'] )? $datas['to_date'] : current_time( 'Y-m-d' );
					$params['to_hour'] = !empty( $datas['to_hour'] )? str_pad( $datas['to_hour'], 2, "0", STR_PAD_LEFT ) : "00";
					$params['to_minute'] = !empty( $datas['to_minute'] )? str_pad( $datas['to_minute'], 2, "0", STR_PAD_LEFT ) : "00";
					
					$params['until'] = date( 'Y-m-d H:i:s', strtotime( date( $params['to_date']." {$params['to_hour']}:{$params['to_minute']}:00" )." -1 second" ) );

					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['wh_code'] ) ) $params['warehouse_id'] = $datas['wh_code'];
					if( !empty( $datas['strg_id'] ) ) $params['strg_id'] = $datas['strg_id'];
					if( !empty( $datas['store_type_id'] ) ) $params['store_type_id'] = $datas['store_type_id'];
					if( !empty( $datas['inconsistent_unit'] ) ) $params['inconsistent_unit'] = $datas['inconsistent_unit'];
					if( !empty( $datas['item_id'] ) ) $params['item_id'] = $datas['item_id'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['grp_id'] ) ) $params['grp_id'] = $datas['grp_id'];
					if( !empty( $datas['_brand'] ) ) $params['_brand'] = $datas['_brand'];
					if( !empty( $datas['_uom_code'] ) ) $params['_uom_code'] = $datas['_uom_code'];

					if( !empty( $datas['pending_gr'] ) ) $params['pending_gr'] = $datas['pending_gr'];
					if( !empty( $datas['client_code'] ) ) $params['client_code'] = $datas['client_code'];					
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
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

	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		return $default_column;
	}

	public function export_data_handler( $params = array() )
	{
		return $this->Logic->get_export_data( $params );
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_fragment( $type = 'export' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'export':
			default:
				if( current_user_cans( [ 'export_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
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
			'wh_code'	=> $this->warehouse['code'],
			'seller'	=> $this->warehouse['id'],
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		do_action( 'wcwh_templating', 'export/export-inventory.php', $this->tplName['export'], $args );
	}

	public function view_transaction( $id = 0, $filters = array() )
	{
		$args = [ 'setting'	=> $this->setting, 'section' => $this->section_id ];
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filter = [
				'product_id' => $id,
				'warehouse_id' => $this->warehouse['code'],
				'strg_id' => $filters['strg_id'],
				'transact' => $filters['transact'],
			];
			$datas = $this->Logic->get_transaction( $filter, [], false, [] );
			if( $datas )
			{	
				include_once( WCWH_DIR."/includes/listing/sub_list/invTransactListing.php" ); 
				$Inst = new WCWH_InvTransact_List();
				$Inst->seller = $args['seller'];
				$Inst->per_page_limit = 10000;
				$Inst->set_args( [ 'off_footer'=>true, 'list_only'=>true ] );

				$Inst->styles = [
					'.plus_sign' => [ 'text-align'=>'center !important' ],
					'.from_qty , .bqty, .bal_qty, .bunit, .unit_price, .total_price, .unit_cost, .total_cost' => [ 'text-align'=>'right !important' ],
				];
				
				$Inst->set_details( $datas );
				$Inst->render();
			}
		}
	}

	public function view_reserved( $id = 0, $filters = array() )
	{
		$args = [ 'setting'	=> $this->setting, 'section' => $this->section_id ];
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filter = [
				'product_id' => $id,
				'warehouse_id' => $this->warehouse['code'],
				'strg_id' => $filters['strg_id'],
				'transact' => $filters['transact'],
			];
			$datas = $this->Logic->get_reserved( $filter, [], false, [] );
			if( $datas )
			{	
				include_once( WCWH_DIR."/includes/listing/sub_list/invReservedListing.php" ); 
				$Inst = new WCWH_InvReserved_List();
				$Inst->seller = $args['seller'];
				$Inst->per_page_limit = 10000;
				$Inst->set_args( [ 'off_footer'=>true, 'list_only'=>true ] );

				$Inst->styles = [
					'.plus_sign' => [ 'text-align'=>'center !important' ],
					'.from_qty , .bqty, .bal_qty, .bunit, .unit_price, .total_price, .unit_cost, .total_cost' => [ 'text-align'=>'right !important' ],
				];
				
				$Inst->set_details( $datas );
				$Inst->render();
			}
		}
	}

	public function view_movement( $id = 0, $filters = array() )
	{
		$args = [ 'setting'	=> $this->setting, 'section' => $this->section_id ];
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filter = [
				'product_id' => $id,
				'warehouse_id' => $this->warehouse['code'],
				'strg_id' => $filters['strg_id'],
				'transact' => $filters['transact'],
			];
			$datas = $this->Logic->get_movement( $filter, [], false, [] );
			if( $datas )
			{	
				include_once( WCWH_DIR."/includes/listing/sub_list/invMovementListing.php" ); 
				$Inst = new WCWH_InvMovement_List();
				$Inst->seller = $args['seller'];
				$Inst->per_page_limit = 10000;
				$Inst->set_args( [ 'off_footer'=>true, 'list_only'=>true ] );

				$Inst->styles = [
					'.plus_sign' => [ 'text-align'=>'center !important' ],
					'.from_qty , .bqty, .bal_qty, .bunit, .unit_price, .total_price, .unit_cost, .total_cost' => [ 'text-align'=>'right !important' ],
				];
				
				$Inst->set_details( $datas );
				$Inst->render();
			}
		}
	}

	public function view_pos_sales( $id = 0, $filters = array() )
	{
		$args = [ 'setting'	=> $this->setting, 'section' => $this->section_id ];
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$from_date = date( 'Y-m-d 00:00:00', strtotime( current_time( 'Y-m-d' )." -1 month" ) );
			$to_date = date( 'Y-m-d 23:59:59', strtotime( current_time( 'Y-m-d' ) ) );
				
			$last_transact = $this->Logic->get_last_sale( $id );
			if( $last_transact )
			{
				$from_date = date( 'Y-m-d 00:00:00 ', strtotime( $last_transact['sales_date']." -1 month" ) );
				$to_date = date( 'Y-m-d 23:59:59 ', strtotime( $last_transact['sales_date'] ) );
			}
			
			$filter = [
				'product_id' => $id,
				'warehouse_id' => $this->warehouse['code'],
				'strg_id' => $filters['strg_id'],
				'from_date' => $from_date,
				'to_date' => $to_date,
			];
			$datas = $this->Logic->get_pos_sales( $filter, [], false, [] );
			
			if( $datas )
			{	
				include_once( WCWH_DIR."/includes/listing/sub_list/invPosListing.php" ); 
				$Inst = new WCWH_InvPOS_List();
				$Inst->seller = $args['seller'];
				$Inst->per_page_limit = 10000;
				$Inst->set_args( [ 'off_footer'=>true, 'list_only'=>true ] );

				$Inst->styles = [
					'.qty, .weight, .avg_weight, .avg_price, .line_total' => [ 'text-align'=>'right !important' ],
				];
				
				$Inst->set_details( $datas );
				$Inst->render();
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
			if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

			$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
			if( $args['seller'] > 0 ) $filter['seller'] = $args['seller'];
			$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

			include_once( WCWH_DIR."/includes/listing/inventoryListing.php" ); 
			$Inst = new WCWH_Inventory_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->seller = $args['seller'];
			$Inst->advSearch = array( 'isOn'=>1 );
			$Inst->styles = [
				'.conversion' => [ 'text-align'=>'center', 'font-size'=>'15px !important' ],
				'.total_in, .total_out, .qty, .allocated_qty, .total_sales_qty, .reserved_qty, .balance' => [ 
					'text-align'=>'right !important', 
					'font-size'=>'15px !important',
					'color'=> '#000 !important' 
				],
				'.total_in, .total_out, .allocated_qty, .total_sales_qty' => [ 'font-weight'=>'600' ],
				'.qty' => [ 'font-weight'=>'700' ],
				'.balance' => [ 'font-weight'=>'800' ],
				'.subQty' => [ 'font-size' =>'12px' ],
				'.isChild' => [ 'color'=>'#888 !important' ],
				'.isChild.clr-red' => [ 'color'=>'#faa !important' ],
			];
			
			$filters['seller'] = $args['seller'];
			$filters['wh_code'] = $this->warehouse['code'];
			$filters['strg_id'] = !empty( $filters['strg_id'] )? $filters['strg_id'] : $storage['id'];
			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 1;
			$filters['stock_condition'] = !empty( $filters['stock_condition'] )? $filters['stock_condition'] : 'yes';
			
			$Inst->filters = $filters;
			$this->filters = $Inst->filters;
			$Inst->advSearch_onoff();
			
			$Inst->bulks = array( 
				'data-modal' => 'wcwhModalForm',
				'data-actions' => 'close|submit',
				'data-title' => 'New',
				'data-tpl' => '', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_inventory( $filters, $order, false, 
				[ 'group'=>1, 'store'=>1, 'category'=>1, 'brand'=>1, 'inconsistent'=>1, 'converse'=>1, 
					'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] 
				], [], $limit 
			);
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}