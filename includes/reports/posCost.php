<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_POSCost_Rpt" ) ) 
{
	
class WCWH_POSCost_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "POSCost";

	public $tplName = array(
		'export' => 'exportPOSCost',
		'print' => 'printPOSCost',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

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
			"document"		=> $prefix."document",
			"document_items"=> $prefix."document_items",
			"document_meta"	=> $prefix."document_meta",

			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",

			"client"		=> $prefix."client",
			"clientmeta"	=> $prefix."clientmeta",
			"client_tree"	=> $prefix."client_tree",

			"items"         => $prefix."items",
            "itemsmeta"     => $prefix."itemsmeta",
            "item_group"    => $prefix."item_group",
            "uom"           => $prefix."uom",
            "reprocess_item"=> $prefix."reprocess_item",
            "item_converse" => $prefix."item_converse",

			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
			"customer" 		=> $prefix."customer",
			"customer_group"	=> $prefix."customer_group",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",
			
			"status"		=> $prefix."status",
			
			"wp_user"		=> $wpdb->users,
			"wp_usermeta"	=> $wpdb->usermeta,
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"warehouse"		=> $prefix."warehouse",
			"warehouse_meta"=> $prefix."warehousemeta",

			"margining"			=> $prefix."margining",
			"margining_sect"	=> $prefix."margining_sect",
			"margining_det"		=> $prefix."margining_det",
			"margining_sales"	=> $prefix."margining_sales",
			
			"temp_t"			=> "temp_t",
			"temp_so"			=> "temp_so",
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
			$date_format = get_option( 'date_format' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":
					switch( $datas['export_type'] )
					{
						case 'items':
							$datas['filename'] = 'POS Cost & Sales Items ';
						break;
					}
					
					$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					if( $datas['from_date'] ) $datas['filename'].= date( $date_format, strtotime( $datas['from_date'] ) );
					if( $datas['to_date'] )  $datas['filename'].= " - ".date( $date_format, strtotime( $datas['to_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
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

		//$default_column['title'] = [];

		//$default_column['default'] = [];

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		$type = $params['export_type'];
		unset( $params['export_type'] );
		
		switch( $type )
		{
			case 'items':
			default:
				return $this->get_pos_item_cost_report( $params );
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
			case 'print':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['print'] ?>" 
					data-title="<?php echo $actions['print'] ?> Report" data-modal="wcwhModalImEx" 
					data-actions="close|printing" 
					title="<?php echo $actions['print'] ?> Report"
				>
					<i class="fa fa-print" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = 'summary' )
	{
		$action_id = 'pos_cost_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'items':
			default:
				do_action( 'wcwh_templating', 'report/export-pos_cost-item-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	/**
	 *	Item Sales
	 */
	public function pos_item_cost_report( $filters = array(), $order = array() )
	{
		$action_id = 'pos_item_cost_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/posItemCostList.php" ); 
			$Inst = new WCWH_POSItemCost_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-d' );
			$date_to = current_time( 'Y-m-d' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'#category' => [ 'width'=>'10%' ],
				'#item_name' => [ 'width'=>'22%' ],
				'.qty, .weight, .sale_price, .sale_amt, .dc_po_price, .dc_po_amt, .dc_sale_price, .dc_sale_amt' => [ 'text-align'=>'right !important' ],
				'#qty a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_pos_item_cost_report( $filters, $order, [] );
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
	public function get_pos_item_cost_report( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		@set_time_limit(3600);

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

		$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";

			$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );

			$sql = "SELECT a.*, b.meta_value AS client_code 
				FROM {$dbname}{$this->tables['warehouse']} a
				LEFT JOIN {$dbname}{$this->tables['warehouse_meta']} b ON b.warehouse_id = a.id AND b.meta_key = 'client_company_code'
				WHERE 1 AND a.code = '{$warehouse['code']}' ";
			$result = $wpdb->get_row( $sql , ARRAY_A );
			if( $result ) $client_code = json_decode( $result['client_code'], true );
		}

		$f = $filters;
		if( $client_code ) $f['client_code'] = $client_code;
		$this->get_so_sap_canteen_einvoice( $f );
		
		$field = "j.name AS category, j.slug AS category_code ";
		if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) $field.= ", i.name AS item_name ";
		$field.= ", i.code AS item_code, i._uom_code AS uom ";
		$field.= ", SUM( e.meta_value * IFNULL(ic.base_unit,1) ) AS pos_qty, ROUND( SUM( h.meta_value * e.meta_value ), 2 ) AS pos_weight ";
		$field.= ", ROUND( SUM( g.meta_value ) / SUM( e.meta_value * IFNULL(ic.base_unit,1) ), 5 ) AS pos_price, ROUND( SUM( g.meta_value ), 2 ) AS pos_amt ";
		$field.= ", ROUND( dc.total_cost / dc.qty, 5 ) AS dc_po_price
			, ROUND( dc.total_cost / dc.qty * SUM( e.meta_value * IFNULL(ic.base_unit,1) ), 2 ) AS dc_po_amt
			, ROUND( dc.total_sale / dc.qty, 5 ) AS dc_sale_price
			, ROUND( dc.total_sale / dc.qty * SUM( e.meta_value * IFNULL(ic.base_unit,1) ), 2 ) AS dc_sale_amt ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} wh ON wh.post_id = a.ID AND wh.meta_key = 'wc_pos_warehouse_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = c.order_item_id AND l.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = d.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = ic.base_id ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} j ON j.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = j.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$table.= "LEFT JOIN {$this->tables['temp_so']} dc ON dc.product_code = i.code ";

		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} k ON k.post_id = a.ID AND k.meta_key = 'customer_id' ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value IS NOT NULL ", 'shop_order' );
		$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";

		if( $warehouse )
		{
			$cond.= $wpdb->prepare( "AND wh.meta_value = %s ", $warehouse['code'] );
		}
		
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['customer'] ) )
		{
			if( $filters['customer'] == 'guest' )
				$cond.= $wpdb->prepare( "AND ( k.meta_value IS NULL OR k.meta_value = %s ) ", '0' );
			else
				$cond.= $wpdb->prepare( "AND k.meta_value = %s ", $filters['customer'] );
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.id = %d ", $filters['product'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR j.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR j.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		if( isset( $filters['s'] ) )
		{
			$search = explode( ',', trim( $filters['s'] ) );    
			$search = array_merge( $search, explode( ' ', str_replace( ',', ' ', trim( $filters['s'] ) ) ) );
        	$search = array_filter( $search );

            $cond.= "AND ( ";

            $seg = array();
            foreach( $search as $kw )
            {
                $kw = trim( $kw );
                $cd = array();
                $cd[] = "i.name LIKE '%".$kw."%' ";
				$cd[] = "i._sku LIKE '%".$kw."%' ";
				$cd[] = "i.code LIKE '%".$kw."%' ";
				$cd[] = "i.serial LIKE '%".$kw."%' ";
				$cd[] = "j.name LIKE '%".$kw."%' ";
				$cd[] = "j.slug LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "GROUP BY ic.base_id ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'i.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		//rt($results);
		return $results;
	}

	//----------------------------------------------------------------------------------------------------
	public function get_so_sap_canteen_einvoice( $filters = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		if( isset( $filters['seller'] ) )
		{
			$db = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$db = ( $db )? $db."." : "";
		}

		$dbname = "";
		
	    $curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	    if( $curr_wh ) $filters['warehouse_id'] = $curr_wh['code'];

	    //------------------------------------------------------------------

	    $margining_id = "wh_sales_rpt_canteen_einvoice";

		//------------------------------------------------------------------

	    $field = "h.doc_id, d.item_id, h.post_date, ic.base_id AS product_id, i.code AS product_code, i._uom_code AS uom
	    	, d.bqty * IFNULL(ic.base_unit,1) AS qty
	    	, ROUND( d.bqty * pi.final_sprice, 2 ) AS total_sale 
			, ROUND( IFNULL( ib.meta_value * d.bqty, IF( mb.meta_value = 'good_issue', tti.weighted_total, ti.weighted_total ) ), 2 ) AS total_cost ";
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'base_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'base_doc_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.item_id = d.item_id AND ti.status != 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} td ON td.doc_id = d.ref_doc_id AND td.item_id = d.ref_item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} tti ON tti.item_id = td.item_id AND tti.status != 0 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = md.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pi ON pi.doc_id = md.meta_value AND pi.product_id = d.product_id AND pi.warehouse_id = ph.warehouse_id AND pi.type = 'def' AND pi.status > 0 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = d.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = ic.base_id ";
		$table.= "LEFT JOIN {$db}{$this->tables['items']} ei ON ei.code = i.code ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";
		$table.= "LEFT JOIN {$db}{$this->tables['category']} ecat ON ecat.slug = cat.slug ";
			$subsql = "SELECT ancestor AS id FROM {$db}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$db}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND mc.meta_value = %s ", 'delivery_order', 6, 'sale_order' );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['date_type'] ) )
		{
			$date_type = $filters['date_type'];
		}
		$date_type = empty( $date_type )? 'post_date' : $date_type;
		/*if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.{$date_type} >= %s ", $filters['from_date'] );
		}*/
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.{$date_type} <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['client'] ) )
		{
			if( is_array( $filters['client'] ) )
			{
				$cond.= "AND c.id IN ('" .implode( "','", $filters['client'] ). "') ";
			}
			else
			{
				$cond.= $wpdb->prepare( "AND c.id = %d ", $filters['client'] );
			}
		}
		if( isset( $filters['client_code'] ) )
		{
			if( is_array( $filters['client_code'] ) )
			{
				$cond.= "AND ma.meta_value IN ('" .implode( "','", $filters['client_code'] ). "') ";
			}
			else
			{
				$cond.= $wpdb->prepare( "AND ma.meta_value = %d ", $filters['client_code'] );
			}
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND ei.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND ei.id = %d ", $filters['product'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR ecat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR ecat.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'ic.base_id'=>'ASC', 'h.post_date'=>'DESC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$select = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		
		$query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_t']} ";
		$query.= "AS ( {$select} ) ";
		
		//$wpdb->show_errors();
		$query = $wpdb->query( $query );
		
		//---------------------------------------------
		
		$select = "SELECT * FROM {$this->tables['temp_t']} WHERE 1 GROUP BY product_id ORDER BY product_id ASC ";
		
		$query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_so']} ";
		$query.= "AS ( {$select} ) ";
		
		//$wpdb->show_errors();
		$query = $wpdb->query( $query );
        
        return $query;
	}
	
} //class

}