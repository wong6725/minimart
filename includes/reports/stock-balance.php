<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_StockBalance_Rpt" ) ) 
{
	
class WCWH_StockBalance_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "StockBalance";

	public $Logic;

	public $tplName = array(
		'export' => 'exportBalance',
		'print' => 'printBalance',
	);
	
	protected $tables = array();
	protected $dbname = '';

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	private $system_begin_date = '2020-09-01 00:00:00';

	public $default_column_title = array(
		"item_code"				=> "Item Code",
		"item_name"				=> "Item Name",
		"gtin"					=> "Gtin",
		"extra_gtin"			=> "Extra Gtin",
		"category_code"			=> "Category Code",
		"category_name"			=> "Category Name",
		"category_group_code"	=> "Category Group Code",
		"category_group_name"	=> "Category Group Name",
		"item_group"			=> "Group",
		"store_type"			=> "Store Type",
		"uom"					=> "UOM",
		"base_item_code"		=> "Base Item Code",
		"base_conversion"		=> "Base Conversion",
		"required_metric"		=> "Need Metric (kg/l)",
		"in_qty"				=> "In Qty",
		"out_qty"				=> "Out Qty",
		"pos_qty"				=> "POS Qty",
		"total_out_qty"			=> "Total Out Qty",
		"foc_qty"				=> "FOC Qty",
		"balance_qty"			=> "Balance Qty",
		"stock_in_amt"			=> "Stock In Amt",
		"stock_out_amt"			=> "Stock Out Amt",
		"pos_amt"				=> "POS Amt",
		"balance_cost"			=> "Balance Cost",
		"avg_unit_price"		=> "Bal Unit Price",
		"converted_qty"			=> "Converted Qty",
		"converted_bal_qty"		=> "Converted Bal Qty",
	);

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_db_tables();
	}
	
	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"inventory"			=> $prefix.$this->tbl,
			"pos_arc"			=> $prefix."pos_arc",
			
			"main" 				=> $prefix.'items',
			"tree"				=> $prefix."items_tree",
			"meta"				=> $prefix."itemsmeta",
			
			"uom"				=> $prefix."uom",
			"item_group"		=> $prefix."item_group",
			"item_store_type" 	=> $prefix."item_store_type",
			"category"			=> $wpdb->prefix."terms",
			"category_tree"		=> $prefix."item_category_tree",
			"taxonomy"			=> $wpdb->prefix."term_taxonomy",
			"brand"				=> $prefix."brand",
			"reprocess_item"	=> $prefix."reprocess_item",
			"item_converse"		=> $prefix."item_converse",
			
			"product"			=> $wpdb->posts,
			"product_meta"		=> $wpdb->postmeta,
			
			"supplier"			=> $prefix."supplier",
			"status"			=> $prefix."status",
			
			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",
			
			"storage"				=> $prefix."storage",
			
			"document"				=> $prefix."document",
			"document_items"		=> $prefix."document_items",
			"document_meta"			=> $prefix."document_meta",

			"order"				=> $wpdb->posts,
			"order_meta"		=> $wpdb->postmeta,
			"order_items"		=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"	=> $wpdb->prefix."woocommerce_order_itemmeta",

			"selling_price"		=> $prefix."selling_price",

			"temp_inv"			=> "temp_inventory",
		);
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
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

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":
					switch( $datas['export_type'] )
					{
						case 'stock_balance':
							$datas['filename'] = 'Stock Balance ';
						break;
					}
					
					//if( $datas['to_date'] )  $datas['filename'].= " - ".date( 'Y-m-d', strtotime( $datas['to_date'] ) );
					
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
					if( !empty( $datas['sellable'] ) ) $params['sellable'] = $datas['sellable'];

					if( !empty( $datas['pending_gr'] ) ) $params['pending_gr'] = $datas['pending_gr'];
					if( !empty( $datas['client_code'] ) ) $params['client_code'] = $datas['client_code'];

					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					
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
	protected function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		if( $this->default_column_title )
		{
			$default_column['title'] = $this->default_column_title;
		}

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		$type = $params['export_type'];
		unset( $params['export_type'] );
		
		switch( $type )
		{
			case 'stock_balance':
				return $this->get_stock_balance_report( $params );
			break;
		}
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_latest()
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( ! $this->seller ) return;

		$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true );
		if( ! $curr_wh || $curr_wh['indication'] ) return;
		
		$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
		$dbname = ( $dbname )? $dbname."." : "";

		$cond = $wpdb->prepare( "AND post_type = %s AND post_status = %s ", "pos_temp_register_or", "publish" );
		$ord = "ORDER BY post_date DESC ";
		$l = "LIMIT 0,1 ";
		$sql = "SELECT * FROM {$dbname}{$wpdb->posts} WHERE 1 {$cond} {$ord} {$l}";
		$result = $wpdb->get_row( $sql , ARRAY_A );

		if( $result )
		{
			$now = strtotime( current_time( 'mysql' ) );
			$latest_record = strtotime( $result['post_date'] );
			$max_diff_sec = 86400; //1day
			if( (int)$now - (int)$latest_record >= 86400 )
			{
				echo "<span class='required toolTip' title='Data delayed for more than 24 hours, data might failed to sync back from site.'>Latest data: {$result['post_date']}</span>";
			}
			else
			{
				echo "<span class='toolTip' title='Latest site data found.'>Latest data: {$result['post_date']}</span>";
			}
		}
	}

	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'export':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Report" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Report"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = 'summary' )
	{
		$action_id = 'balance_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		if( $this->seller ) $args['seller'] = $this->seller;
		if( $this->default_column_title ) $args['default_column_title'] = $this->default_column_title;

		switch( strtolower( $type ) )
		{
			case 'stock_balance':
				do_action( 'wcwh_templating', 'report/export-stock_balance-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function stock_balance_report( $filters = array(), $order = array() )
	{
		$action_id = 'stock_balance_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockBalanceList.php" ); 
			$Inst = new WCWH_StockBalance_Report();
			$Inst->seller = $this->seller;

			$date_to = current_time( 'Y-m-d' );
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			$filters['to_date'] = date( 'Y-m-d', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) 
			{
				$filter = [ 'id'=>$this->seller, 'status'=>1, 'visible'=>1 ];			
				$warehouse = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'company'=>1 ] );

				if($warehouse) 
				{
					$filter = [ 'seller'=>$this->seller, 'wh_code'=>$warehouse['code'], 'sys_reserved'=>'staging' ];
					$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

					$filters['wh_code'] = $warehouse['code'];
					$filters['strg_id'] = !empty( $filters['strg_id'] )? $filters['strg_id'] : $storage['id'];
				}

				$filters['seller'] = $this->seller;
			}

			$Inst->default_column_title = $this->default_column_title;
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$order = [];
			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_stock_balance_report( $filters, $order, [  ] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */

	public function get_stock_balance_report( $filters = array(), $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
			$this->dbname = $dbname;
		}

		if( isset( $filters['to_date'] ) && !$filters['until'] )
		{
			$to_date = !empty( $filters['to_date'] )? $filters['to_date'] : current_time( 'Y-m-d' );
			$to_hour = !empty( $filters['to_hour'] )? str_pad( $filters['to_hour'], 2, "0", STR_PAD_LEFT ) : "00";
			$to_minute = !empty( $filters['to_minute'] )? str_pad( $filters['to_minute'], 2, "0", STR_PAD_LEFT ) : "00";	
			$filters['until'] = date( 'Y-m-d H:i:s', strtotime( date( $to_date." {$to_hour}:{$to_minute}:00" )." -1 second" ) );
		}
		
		@set_time_limit(900);
		
		$this->temp_inventory( $filters );

		$fld = "a.id, a.serial AS gtin, a.serial2 AS extra_gtin, a.code AS code, a.name, CONCAT( grp.code, '-', grp.name ) AS item_group, CONCAT( br.code, '-', br.name ) AS brand
			, cat.slug AS category_code, cat.name AS category_name, pcat.slug AS category_group_code, pcat.name AS category_group
			, a._uom_code AS uom, CONCAT( st.code, '-', st.name ) AS store_type
			, group_concat( distinct ta.code order by t.level asc separator ',' ) as breadcrumb_code
			, a.store_type_id, a.status, a.flag, ic.base_id, b.code AS base_code, b.name AS base_name, ic.base_unit 
			, IF( rr.id > 0 AND mb.meta_value > 0 AND NOT ( UPPER( a._uom_code ) = 'KG' OR UPPER( a._uom_code ) = 'L' ) , 1, 0 ) AS required_metric ";

		$tbl = "{$dbname}{$this->tables['main']} a ";
		$tbl.= "INNER JOIN {$dbname}{$this->tables['tree']} t ON t.descendant = a.id ";
		$tbl.= "INNER JOIN {$dbname}{$this->tables['main']} ta force index(primary) ON ta.id = t.ancestor ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_group']} grp ON grp.id = a.grp_id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_store_type']} st ON st.id = a.store_type_id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = a.category ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['taxonomy']} taxo ON taxo.term_id = cat.term_id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} pcat On pcat.term_id = taxo.parent ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['meta']} ma ON ma.items_id = a.id AND ma.meta_key = '_brand' ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['brand']} br ON br.code = ma.meta_value ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['meta']} mb ON mb.items_id = a.id AND mb.meta_key = 'inconsistent_unit' ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['meta']} mc ON mc.items_id = a.id AND mc.meta_key = '_sellable' ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['reprocess_item']} rr ON rr.items_id = a.id AND rr.status > 0 ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['main']} b ON b.id = ic.base_id  ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$cond = "";

		$grp = "GROUP BY a.code, a.serial, a.id ";
		$ord = "";

		if( isset( $filters['store_type_id'] ) )
		{
            $cond.= $wpdb->prepare( "AND a.store_type_id = %s ", $filters['store_type_id'] );
		}
		if( isset( $filters['inconsistent_unit'] ) )
		{
			$cond.= $wpdb->prepare( "AND mb.meta_value = %s ", $filters['inconsistent_unit'] );
		}
		if( isset( $filters['item_id'] ) )
		{
            if( is_array( $filters['item_id'] ) )
                $cond.= "AND a.id IN ('" .implode( "','", $filters['item_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.id = %s ", $filters['item_id'] );
		}
		if( is_array( $filters['category'] ) )
		{
			$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
			$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
			$cond.= "AND ( {$catcd} ) ";
		}
		if( isset( $filters['grp_id'] ) )
		{
			if( is_array( $filters['grp_id'] ) )
                $cond.= "AND grp.id IN ('" .implode( "','", $filters['grp_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND grp.id = %s ", $filters['grp_id'] );
		}
		if( isset( $filters['_brand'] ) )
		{
			if( is_array( $filters['_brand'] ) )
                $cond.= "AND br.code IN ('" .implode( "','", $filters['_brand'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND br.code = %s ", $filters['_brand'] );
		}
		if( isset( $filters['_uom_code'] ) )
		{
			if( is_array( $filters['_uom_code'] ) )
                $cond.= "AND a._uom_code IN ('" .implode( "','", $filters['_uom_code'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a._uom_code = %s ", $filters['_uom_code'] );
		}
		if( isset( $filters['sellable'] ) )
		{
			switch( $filters['sellable'] )
			{
				case 'yes':
					$cond.= "AND ( mc.meta_value IS NULL OR TRIM( mc.meta_value ) = 'yes' ) ";
				break;
				case 'no':
					$cond.= "AND ( mc.meta_value IS NOT NULL AND TRIM( mc.meta_value ) = 'no' ) ";
				break;
				case 'force':
					$cond.= "AND ( mc.meta_value IS NOT NULL AND TRIM( mc.meta_value ) = 'force' ) ";
				break;
			}
		}
		
		$item_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

		//------------------------------------------------------

		$field = "i.code AS item_code, i.name AS item_name, i.gtin, i.extra_gtin
		, i.category_code, i.category_name
		, i.category_group_code, i.category_group AS category_group_name 
		, i.item_group, i.store_type, i.uom
		, IF( i.base_code != i.code, i.base_code, '' ) AS base_item_code, IF( i.base_code != i.code, i.base_unit, '' ) AS base_conversion
		, IF( i.required_metric > 0, 'YES', 'NO' ) AS required_metric ";

		$field.= ", t.in_qty, t.out_qty, t.pos_qty
		, t.out_qty + t.pos_qty AS total_out_qty, t.foc_qty
		, @bal_qty:= t.in_qty - t.out_qty - t.pos_qty AS balance_qty 
		, t.in_cost AS stock_in_amt, t.out_cost AS stock_out_amt, t.pos_cost AS pos_amt
		, IF( @bal_qty = 0 AND t.in_cost-t.out_cost-t.pos_cost != 0, 0, t.in_cost - t.out_cost - t.pos_cost ) AS balance_cost 
		, IF( i.base_id != i.id, ROUND( (tt.in_cost-tt.out_cost) / ( (tt.in_qty-tt.out_qty) / i.base_unit ), 5 ), ROUND( (t.in_cost-t.out_cost) / (t.in_qty-t.out_qty), 5 ) ) AS avg_unit_price 
		, @cqty:= IF( i.base_id != i.id, ROUND( (tt.in_qty-tt.out_qty-tt.pos_qty) / i.base_unit, 2 ), '' ) AS converted_qty 
		, IF( i.base_id != i.id, ROUND( @cqty + (t.in_qty-t.out_qty-t.pos_qty), 2 ), '' ) AS converted_bal_qty ";

		/*if( isset( $filters['pending_gr'] ) && isset( $filters['client_code'] ) )
		{
			$field = "i.code AS item_code, i.name AS item_name, i.gtin, i.extra_gtin
			, i.category_code, i.category_name
			, i.category_group_code, i.category_group AS category_group_name 
			, i.item_group, i.store_type, i.uom
			, IF( i.base_code != i.code, i.base_code, '' ) AS base_item_code, IF( i.base_code != i.code, i.base_unit, '' ) AS base_conversion
			, IF( i.required_metric > 0, 'YES', 'NO' ) AS required_metric ";

			$field.= ", t.in_qty, t.out_qty, t.pos_qty
			, t.out_qty + t.pos_qty AS total_out_qty, t.foc_qty
			, @bal_qty:= t.in_qty - t.out_qty - t.pos_qty AS balance_qty 
			, t.in_cost AS stock_in_amt, t.out_cost AS stock_out_amt, t.pos_cost AS pos_amt
			, IF( @bal_qty = 0 AND t.in_cost-t.out_cost-t.pos_cost != 0, 0, t.in_cost - t.out_cost - t.pos_cost ) AS balance_cost 
			
			, t.pending_gr AS pending_gr_qty, t.pending_gr_amt AS pending_gr_amt
			, t.in_qty - t.out_qty - t.pos_qty + t.pending_gr AS bal_qty_after_pending_gr
			, IF( i.base_id != i.id, ROUND( (tt.in_cost-tt.out_cost) / ( (tt.in_qty-tt.out_qty) / i.base_unit ), 5 ), ROUND( (t.in_cost-t.out_cost) / (t.in_qty-t.out_qty), 5 ) ) AS avg_unit_price 
			, @cqty:= IF( i.base_id != i.id, ROUND( (tt.in_qty-tt.out_qty-tt.pos_qty) / i.base_unit, 2 ), '' ) AS converted_qty 
			, IF( i.base_id != i.id, ROUND( @cqty + (t.in_qty-t.out_qty-t.pos_qty), 2 ), '' ) AS converted_bal_qty ";
		}*///, t.in_cost - t.out_cost - t.pos_cost + t.pending_gr_amt AS 'Balance Cost After Pending GR'

		$table = "( {$item_sql} ) i ";
		$table.= "LEFT JOIN {$this->tables['temp_inv']} t ON t.product_id = i.id ";
		$table.= "LEFT JOIN {$this->tables['temp_inv']} tt ON tt.product_id = i.base_id  ";
		
		$cond = "AND i.status > 0 ";
		$grp = "";
		$ord = "ORDER BY i.base_code ASC , i.breadcrumb_code ASC ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		$this->drop_temp_inventory();
		return $this->after_get_export_data( $results );
	}

		public function after_get_export_data( $results = [] )
		{
			if( ! $results ) return $results;

			$replaceable_items = apply_filters( 'wcwh_get_item', [ 'is_returnable'=>'1' ], [], false, [ 'meta'=>[ 'is_returnable' ] ] );
			if( ! $replaceable_items ) return $results;

			$r_codes = []; $r_items_code = []; $r_id_code = []; $ngt = [];
			foreach( $replaceable_items as $i => $item )
			{
				$r_codes[] = $item['code'];
				$r_items_code[ $item['code'] ] = $item;
				$r_id_code[ $item['id'] ] = $item['code'];

				if( $item['ref_prdt'] > 0 ) $ngt[] = $item['ref_prdt'];
			}

			$ngt_code = [];
			$ngt = array_unique( $ngt );
			foreach( $ngt as $nt )
			{
				$ngt_code[] = $r_id_code[ $nt ];
			}

			$base_pos_qty = []; $ngt_row_idx = [];
			foreach( $results as $i => $row )
			{
				if( in_array( $row['item_code'], $r_codes ) )
				{
					if( $r_items_code[ $row['item_code'] ]['ref_prdt'] > 0 )
					{
						$base_pos_qty[ $r_id_code[ $r_items_code[ $row['item_code'] ]['ref_prdt'] ] ]+= $row['pos_qty'];
						unset( $results[$i] );
					}

					if( in_array( $row['item_code'], $ngt_code ) )
					{
						$ngt_row_idx[ $row['item_code'] ] = $i;
					}
				}
			}

			if( $ngt_row_idx )
			{
				foreach( $ngt_row_idx as $code => $idx )
				{
					$results[ $idx ]['pos_qty']+= $base_pos_qty[ $code ];
					$results[ $idx ]['total_out_qty']+= $base_pos_qty[ $code ];
					$results[ $idx ]['balance_qty']-= $base_pos_qty[ $code ];

					$results[ $idx ]['pos_amt'] = round_to( $results[ $idx ]['stock_in_amt'] / $results[ $idx ]['in_qty'] * $base_pos_qty[ $code ], 2 );
					$results[ $idx ]['balance_cost']-= $results[ $idx ]['pos_amt'];

					/*if( $results[ $idx ]['Balance Qty After Pending GR'] )
						$results[ $idx ]['Balance Qty After Pending GR']-= $base_pos_qty[ $code ];*/
				}
			}

			return $results;
		}

    public function temp_inventory( $filters = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		if( isset( $filters['strg_id'] ) )
		{
			$strg = apply_filters( 'wcwh_get_storage', [ 'id'=>$filters['strg_id'] ], [], true );
		}
		//-----------------------------------------------------------
			
			$fld = "ic.base_id AS product_id 
				, SUM( a.weighted_total ) AS total_price, 0 AS total_cost 
				, a.plus_sign, SUM( IFNULL(a.bqty,0) * IFNULL(ic.base_unit,1) ) AS total_in, 0 AS total_out 
				, 0 AS pos_qty, 0 AS pending_gr, 0 AS pending_gr_amt
				, SUM( IF( a.bqty > 0 AND a.total_price = 0, a.bqty - a.deduct_qty, 0 ) ) AS foc_qty ";
			
			$tbl = "{$dbname}{$this->tables['transaction_items']} a ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['transaction']} b ON b.hid = a.hid ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document']} c ON c.doc_id = b.doc_id ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.product_id ";
			
			$cond = "AND a.status != 0 AND b.status != 0 AND a.plus_sign = '+' ";
			if( isset( $filters['warehouse_id'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
			}
			if( isset( $filters['strg_id'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.strg_id = %s ", $filters['strg_id'] );
			}
			if( isset( $filters['until'] ) )
			{
				$cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['until'] );
			}
			if( isset( $filters['item_id'] ) )
			{
	            if( is_array( $filters['item_id'] ) )
	                $cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
	            else
	                $cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
			}
			$grp = "GROUP BY ic.base_id ";
			$ord = "";
			$in_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";
		//-----------------------------------------------------------
			
			$fld = "ic.base_id AS product_id 
				, 0 AS total_price, SUM( a.weighted_total ) AS total_cost 
				, a.plus_sign, 0 AS total_in, SUM( IFNULL(a.bqty,0) * IFNULL(ic.base_unit,1) ) AS total_out 
				, 0 AS pos_qty, 0 AS pending_gr, 0 AS pending_gr_amt
				, 0 AS foc_qty ";
			
			$tbl = "{$dbname}{$this->tables['transaction_items']} a ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['transaction']} b ON b.hid = a.hid ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document']} c ON c.doc_id = b.doc_id ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.product_id ";
			
			$cond = "AND a.status != 0 AND b.status != 0 AND a.plus_sign = '-' ";
			if( isset( $filters['warehouse_id'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
			}
			if( isset( $filters['strg_id'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.strg_id = %s ", $filters['strg_id'] );
			}
			if( isset( $filters['until'] ) )
			{
				$cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['until'] );
			}
			if( isset( $filters['item_id'] ) )
			{
	            if( is_array( $filters['item_id'] ) )
	                $cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
	            else
	                $cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
			}
			$grp = "GROUP BY ic.base_id ";
			$ord = "";
			$out_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";
		//-----------------------------------------------------------
			
			$pos_arc = apply_filters( 'wcwh_get_setting', '', '', $filters['seller'], 'wcwh_pos_arc_date' );
			$fld = "ic.base_id AS product_id, 0 AS total_price, 0 AS total_cost 
				, '-' AS plus_sign, 0 AS total_in, 0 AS total_out 
				, SUM( IFNULL(k.meta_value,0) * IFNULL(ic.base_unit,1) ) AS pos_qty
				, 0 AS pending_gr, 0 AS pending_gr_amt
				, 0 AS foc_qty ";
			
			$tbl = "{$dbname}{$this->tables['order']} a ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_meta']} c ON c.post_id = a.ID AND c.meta_key = '_order_total' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
			//$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_line_total' ";
			//$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_unit' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = j.meta_value ";
			
			$cond = "AND a.post_type = 'shop_order' AND a.post_status IN( 'wc-processing', 'wc-completed' ) AND c.meta_value IS NOT NULL ";
			if( isset( $filters['until'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['until'] );
			}
			if( $pos_arc )
			{
				$arc_date = date( 'Y-m-d 00:00:00', strtotime( $pos_arc." +1 day" ) );
				$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $arc_date );
			}
			if( isset( $filters['item_id'] ) )
			{
	            if( is_array( $filters['item_id'] ) )
	                $cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
	            else
	                $cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
			}
			$grp = "GROUP BY ic.base_id ";
			$ord = "";
			$pos_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";
			
			if( $pos_arc )
			{
				$fld = "ic.base_id AS product_id, 0 AS total_price, 0 AS total_cost 
					, '-' AS plus_sign, 0 AS total_in, 0 AS total_out 
					, SUM( IFNULL(a.qty,0) * IFNULL(ic.base_unit,1) ) AS pos_qty
					, 0 AS pending_gr, 0 AS pending_gr_amt
					, 0 AS foc_qty ";
					
				$tbl = "{$dbname}{$this->tables['pos_arc']} a ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.prdt_id ";
				
				$cond = "";
				if( isset( $filters['warehouse_id'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
				}
				if( isset( $filters['item_id'] ) )
				{
					if( is_array( $filters['item_id'] ) )
						$cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
					else
						$cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
				}
				$grp = "GROUP BY ic.base_id ";
				$ord = "";
				$pos_arc_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";
			}
		//-----------------------------------------------------------
			$fld = "ic.base_id AS product_id, 0 AS total_price, 0 AS total_cost 
				, '+' AS plus_sign, 0 AS total_in, 0 AS total_out, 0 AS pos_qty 
				, SUM( ( IFNULL(b.bqty,0) - IFNULL(b.uqty,0) ) * IFNULL(ic.base_unit,1) ) AS pending_gr
				, SUM( IFNULL(b.bqty,0) * mc.meta_value ) AS pending_gr_amt
				, 0 AS foc_qty ";
			
			$tbl = "{$dbname}{$this->tables['document']} a ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = 0 AND ma.meta_key ='supply_to_seller' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = a.doc_id AND mb.item_id = 0 AND mb.meta_key ='client_company_code' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = a.doc_id AND mc.item_id = b.item_id AND mc.meta_key ='sprice' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = b.product_id ";
			
			$cond = "AND a.doc_type = 'delivery_order' AND a.status = 6 ";
			if( isset( $filters['warehouse_id'] ) )
			{
				$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['warehouse_id'] );
			}
			if( isset( $filters['client_code'] ) )
			{
				$cond.= $wpdb->prepare( "AND mb.meta_value = %s ", $filters['client_code'] );
			}
			if( isset( $filters['until'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['until'] );
			}
			if( isset( $filters['item_id'] ) )
			{
	            if( is_array( $filters['item_id'] ) )
	                $cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
	            else
	                $cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
			}
			$grp = "GROUP BY ic.base_id ";
			$ord = "";
			$gr_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";
		//-----------------------------------------------------------
		$field = "a.product_id
			, SUM( a.total_in ) AS in_qty, SUM( a.total_out ) AS out_qty, SUM( a.pos_qty ) AS pos_qty 
			, SUM( a.total_price ) AS in_cost, SUM( a.total_cost ) AS out_cost
			, ROUND( ( SUM(a.total_price)-SUM(a.total_cost) ) / ( SUM(a.total_in)-SUM(a.total_out) ) * SUM( a.pos_qty ), 2 ) AS pos_cost
			, SUM( a.pending_gr ) AS pending_gr, ROUND( SUM( a.pending_gr_amt ), 2 ) AS pending_gr_amt
			, SUM( a.foc_qty ) AS foc_qty ";
		
		if( $strg['sys_reserved'] == 'staging' )
		{
			$arc_union = "";
			if( $pos_arc && $pos_arc_sql ) $arc_union = " UNION ALL ({$pos_arc_sql}) ";
			$table = "( ({$in_sql}) UNION ALL ({$out_sql}) UNION ALL ({$pos_sql}) {$arc_union} ) a ";
			if( isset( $filters['pending_gr'] ) && isset( $filters['client_code'] ) )
			{
				$table = "( ({$in_sql}) UNION ALL ({$out_sql}) UNION ALL ({$pos_sql}) {$arc_union} UNION ALL ({$gr_sql}) ) a ";
			}
		}
		else if( $strg['sys_reserved'] == 'block_staging' )
			$table = "( ({$in_sql}) UNION ALL ({$out_sql}) ) a ";
		$cond = "";
		
		$grp = "GROUP BY a.product_id ";
		$ord = "ORDER BY a.product_id ASC ";
		$select = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_inv']} ";
		$query.= "AS ( {$select} ) ";
		$query = $wpdb->query( $query );
	}

	public function drop_temp_inventory()
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$drop = "DROP TEMPORARY TABLE {$this->tables['temp_inv']} ";
      	$succ = $wpdb->query( $drop );
	}
	
} //class

}