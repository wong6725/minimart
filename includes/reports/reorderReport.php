<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Reorder_Rpt" ) ) 
{
	
class WCWH_Reorder_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "Reorder";

	public $tplName = array(
		'export' => 'exportReorder',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $warehouse;
	public $outlets;
	public $filters = array();
	public $noList = false;

	protected $dbname = "";

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

			"items"			=> $prefix."items",
			"items_tree"	=> $prefix."items_tree",
			"itemsmeta"		=> $prefix."itemsmeta",
			"item_group"	=> $prefix."item_group",
			"store_type"	=> $prefix."item_store_type",
			"uom"			=> $prefix."uom",
			"reprocess_item"=> $prefix."reprocess_item",
			"item_converse"	=> $prefix."item_converse",

			"item_relation" => $prefix."item_relation",
			"reorder_type"	=> $prefix."item_reorder_type",

			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",

			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"inventory"		=> $prefix."inventory",

			"client"		=> $prefix."client",

			"selling_price"	=> $prefix."selling_price",

			"status"		=> $prefix."status",
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
					$datas['filename'] = 'Reorder Report ';
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['sales_period'] ) ) $params['sales_period'] = $datas['sales_period'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					
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
		$order = [];
		
		switch( $type )
		{
			default:
				return $this->get_reorder_report( $params, $order, [ 'category'=>1, 'uom'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
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

		$this->warehouse = $curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true );
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
					data-title="<?php echo $actions['export'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = 'summary' )
	{
		$action_id = 'reorder_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		$type = strtolower( $type );
		$args['def_type'] = $type;
		switch( $type )
		{
			default:
				do_action( 'wcwh_templating', 'report/export-reorder-report.php', $this->tplName['export'], $args );
			break;
		}
	}


	public function reorder_report( $filters = array(), $order = array() )
	{
		$action_id = 'reorder_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/reorderReportList.php" ); 
			$Inst = new WCWH_Reorder_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['sales_period'] = empty( $filters['sales_period'] )? 6 : $filters['sales_period'];

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.lead_time, .order_period, .hms_month, .hms_qty, .hms_metric, .stock_bal, .rov, .po_qty, .final_rov' => [ 'text-align'=>'right !important' ],
				'#lead_time a span, #order_period a span, #hms_qty a span, #hms_metric a span, #stock_bal a span, #rov a span, #po_qty a span, #final_rov a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_reorder_report( $filters, $order, [ 'category'=>1, 'uom'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
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
	public function get_reorder_report( $filters = [], $order = [], $args = [] )
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
			$this->warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );

			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$this->dbname = $dbname = ( $dbname )? $dbname."." : "";

			$ft = [ 'doc_type'=>'inventory', 'warehouse_id'=>$this->warehouse['code'] ];
			$ft['seller'] = $filters['seller'];
			$strg = apply_filters( 'wcwh_get_system_storage', 0, $ft, [] );
		}
		else
		{
			$this->warehouse = apply_filters( 'wcwh_get_warehouse', ['indication'=>1], [], true, [ 'usage'=>1 ] );

			$ft = [ 'doc_type'=>'inventory', 'warehouse_id'=>$this->warehouse['code'] ];
			$ft['seller'] = $this->warehouse['id'];
			$strg = apply_filters( 'wcwh_get_system_storage', 0, $ft, [] );
		}
		if( $this->warehouse['indication'] && $this->warehouse['parent'] <= 0 )//should be at DC
		{
			$sellers = apply_filters( 'wcwh_get_warehouse', ['parent'=>$this->warehouse['id']], [], false, [ 'usage'=>1, 'meta'=>['dbname'] ] );
			if( $sellers )
			{
				foreach( $sellers as $i => $seller )
				{
					$sellers[$i]['wh_code'] = str_replace( [ " ", "-" ], [ "", "_" ], $seller['code'] );

					if( empty( $seller['dbname'] ) ) unset( $sellers[ $i ] );
				}
			}

			$this->outlets = $sellers;
		}

		$field = "a.id AS item_id, a.serial AS item_serial, a._sku AS item_sku, a.code AS item_code, a.name AS item_name ";

		$table = "{$dbname}{$this->tables['items']} a ";
		$cond = "";

		//tree concat
		$cgroup = array();
		$isTree = ( $args && $args['tree'] )? true : false;
		$needTree = ( $args && $args['needTree'] )? true : false;
		if( $isTree || $needTree )
		{
			if( $isTree )
			{
				$field.= ", group_concat( distinct ta.code order by t.level desc separator ',' ) as breadcrumb_code ";
				//$field.= ", group_concat( distinct ta.serial order by t.level desc separator ',' ) as breadcrumb_serial ";
				$field.= ", group_concat( distinct ta.id order by t.level desc separator ',' ) as breadcrumb_id ";
				$field.= ", group_concat( ta.status order by t.level desc separator ',' ) as breadcrumb_status ";
			}
			if( $needTree )
			{
				$field.= ", group_concat( distinct ta.code order by t.level desc separator ',' ) as breadcrumb_code ";
			}
			
			$table.= "INNER JOIN {$dbname}{$this->tables['items_tree']} t ON t.descendant = a.id ";
			$table.= "INNER JOIN {$dbname}{$this->tables['items']} ta force index(primary) ON ta.id = t.ancestor ";
			$table.= "INNER JOIN {$dbname}{$this->tables['items_tree']} tt ON tt.ancestor = a.id ";

			$cgroup = [ "a.code", "a.serial", "a.id " ];
		}

		$isCat = ( $args && $args['category'] )? true : false;
		if( $isCat )
		{
			$field.= ", cat.slug AS category_code, cat.name AS category_name ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = a.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			if( isset( $filters['category'] ) )
			{
				if( is_array( $filters['category'] ) )
				{
					$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
					$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
					$cond.= "AND ( {$catcd} ) ";
				}
				else
				{
					$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
					$catcd = $wpdb->prepare( "OR cat.term_id = %d ", $filters['category'] );
					$cond.= "AND ( {$catcd} ) ";
				}
			}
		}

		$isUom = ( $args && $args['uom'] )? true : false;
		if( $isUom )
		{
			$field.= ", uom.name AS uom_name, uom.code AS uom_code, uom.fraction AS uom_fraction ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['uom']} uom ON uom.code = a._uom_code ";
		}

		$isGrp = ( $args && $args['group'] )? true : false;
		if( $isGrp )
		{
			$field.= ", grp.name AS grp_name, grp.code AS grp_code ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['item_group']} grp ON grp.id = a.grp_id ";
		}

		$isStore = ( $args && $args['store'] )? true : false;
		if( $isStore )
		{
			$field.= ", store.name AS store_name, store.code AS store_code ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['store_type']} store ON store.id = a.store_type_id  ";
		}

		$isMetric = ( $args && $args['isMetric'] )? true : false;
		if( $isMetric && $this->refs['metric'] )
		{
			if( $args['isMetric'] == 'yes' )
			{
				if( $args['isMetricExclCat'] )
				{
					$args['isMetricExclCat'] = ( is_array( $args['isMetricExclCat'] ) )? $args['isMetricExclCat'] : [ $args['isMetricExclCat'] ];
					$cond.= "AND a.category NOT IN ( '".implode( "', '", $args['isMetricExclCat'] )."' ) ";
				}
				$cond.= "AND UPPER( a._uom_code ) IN ( '".implode( "', '", $this->refs['metric'] )."' ) ";
			}
			else if( $args['isMetric'] == 'no' )
			{
				if( $args['isMetricExclCat'] )
				{
					$args['isMetricExclCat'] = ( is_array( $args['isMetricExclCat'] ) )? $args['isMetricExclCat'] : [ $args['isMetricExclCat'] ];
					$cond.= "AND ( UPPER( a._uom_code ) NOT IN ( '".implode( "', '", $this->refs['metric'] )."' ) OR a.category IN ( '".implode( "', '", $args['isMetricExclCat'] )."' ) ) ";
				}
				else
				{
					$cond.= "AND UPPER( a._uom_code ) NOT IN ( '".implode( "', '", $this->refs['metric'] )."' ) ";
				}
			}
		}

		$field.= ", b.code AS base_item_code, b.name AS base_item_name, ic.base_unit AS to_base_conversion 
			, IFNULL(rt.name,'Default HMS Qty') AS order_type, IFNULL(rt.lead_time,0) AS lead_time, IFNULL(rt.order_period,0) AS order_period
			, h.hms_month, @hms_qty:= ROUND( IFNULL(h.hms_qty,0) / IFNULL(ic.base_unit,1), 2 ) AS hms_qty
			, @hms_unit:= ROUND( IFNULL(h.hms_unit,0), 3 ) AS hms_metric
			, @s_bal:= ROUND( ( IFNULL(iv.qty,0) - IFNULL(iv.pos_qty,0) ) / IFNULL(ic.base_unit,1), 2 ) AS stock_bal
			, @rov:= ROUND( IF( rt.name IS NULL, @hms_qty, 
				( @hms_qty * ( IFNULL(rt.lead_time,0) / 30 ) ) + ( @hms_qty * ( IFNULL(rt.order_period,0) / 30 ) ) ), 2 ) AS rov 
			, @po_qty:= ROUND( IFNULL(po.lqty,0) / IFNULL(ic.base_unit,1), 2 ) AS po_qty
			, ROUND( IF( IFNULL(@rov,0) - IFNULL(@po_qty,0) - IFNULL(@s_bal,0) < 0, 0, IFNULL(@rov,0) - IFNULL(@po_qty,0) - IFNULL(@s_bal,0) ), 2 ) AS final_rov ";

		//reorder type
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_relation']} irt ON irt.items_id = a.id ";
        $table.= $wpdb->prepare( "AND irt.wh_id = %s AND irt.rel_type = %s ", $this->warehouse['code'], 'reorder_type' );
        $table.= "LEFT JOIN {$dbname}{$this->tables['reorder_type']} rt ON rt.id = irt.reorder_type ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['itemsmeta']} mb ON mb.items_id = a.id AND mb.meta_key = 'inconsistent_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} b ON b.id = ic.base_id ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['items']} z ON z.parent = a.id ";

		//------------------------------------ Inventory
			$fld = "iv.prdt_id, SUM( iv.qty * IFNULL(ic.base_unit,1) ) AS qty, SUM( iv.allocated_qty * IFNULL(ic.base_unit,1) ) AS pos_qty ";
			$tbl = "{$dbname}{$this->tables['inventory']} iv ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = iv.prdt_id ";
			$cd = $wpdb->prepare( "AND iv.warehouse_id = %s AND iv.strg_id = %d ", $this->warehouse['code'], $strg );

	        $grp = "GROUP BY ic.base_id ";
	        $ord = "";
			$inv_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$grp} {$ord} ";

        //------------------------------------
        $table.= "LEFT JOIN ( {$inv_sql} ) iv ON iv.prdt_id = ic.base_id ";

		//------------------------------------ PO
			$fld = "ic.base_id AS product_id, SUM( ( d.bqty - d.uqty ) * IFNULL(ic.base_unit,1) ) AS lqty ";
			$tbl = "{$dbname}{$this->tables['document']} h ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = d.product_id ";
			$cd = $wpdb->prepare( "AND h.status = %d ", 6 );
			$cd.= $wpdb->prepare( "AND h.doc_type = %s AND ( d.bqty - d.uqty ) > %d ", 'purchase_order', 0 );

	        $grp = "GROUP BY ic.base_id ";
	        $ord = "";
			$po_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$grp} {$ord} ";

        //------------------------------------
        $table.= "LEFT JOIN ( {$po_sql} ) po ON po.product_id = ic.base_id ";

		$sales_period = !empty( $filters['sales_period'] )? $filters['sales_period'] : 6;
		$sales_period = ( $sales_period > 12 )? 12 : $sales_period;
		$today = current_time( "Y-m-1" );
		$from = date( 'Y-m-d', strtotime( date( $today )." -{$sales_period} month" ) );
		$to = current_time( "Y-m-t" );

		$filters['from_date'] = $from." 00:00:00";
		$filters['to_date'] = $to." 23:59:59";
		
		$hms_sql = $this->get_highest_monthly_sales( $filters );
		$table.= "LEFT JOIN ( {$hms_sql} ) h ON h.product_id = ic.base_id ";

		$cond.= $wpdb->prepare( "AND a.status > %d ", 0 );
		//$cond.= $wpdb->prepare( "AND a.status > %d AND ( z.id <= 0 OR z.id IS NULL ) ", 0 );

		if( isset( $filters['product'] ) )
		{
			if( $isTree || $needTree )
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND ( tt.descendant IN ('" .implode( "','", $filters['product'] ). "') OR t.ancestor IN ('" .implode( "','", $filters['product'] ). "') ) ";
				else
					$cond.= $wpdb->prepare( "AND ( tt.descendant = %s OR t.ancestor = %s ) ", $filters['product'], $filters['product'] );
			}
			else
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND a.id IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.id = %d ", $filters['product'] );
			}
		}
		if( isset( $filters['grp_id'] ) )
		{
			if( is_array( $filters['grp_id'] ) )
				$cond.= "AND a.grp_id IN ('" .implode( "','", $filters['grp_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.grp_id = %s ", $filters['grp_id'] );
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
                $cd[] = "a.name LIKE '%".$kw."%' ";
				$cd[] = "a._sku LIKE '%".$kw."%' ";
				$cd[] = "a.code LIKE '%".$kw."%' ";
				$cd[] = "a.serial LIKE '%".$kw."%' ";
				$cd[] = "cat.name LIKE '%".$kw."%' ";
				$cd[] = "cat.slug LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$corder = array();

		$isTreeOrder = ( $args && $args['treeOrder'] )? true : false;
        if( ( $isTree || $needTree ) && $isTreeOrder )
        {
        	$corder[ $args['treeOrder'][0] ] = $args['treeOrder'][1];
        }
		
		$grp = "";
		//group
		$group = [];
		$group = array_merge( $cgroup, $group );
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}
		else
		{
			$grp = "GROUP BY a.code, a.serial, a.id ";
		}

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.code' => 'ASC' ];
        	$order = array_merge( $corder, $order );
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord.= "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( isset( $filters['sufficient'] ) )
		{
			$cnd = "";
			if( $filters['sufficient'] == 'insufficient' ) $cnd.= "AND a.final_rov > 0 ";
			else $cnd.= "AND a.final_rov <= 0 ";

			$sql = "SELECT a.* FROM ( {$sql} ) a WHERE 1 {$cnd} ";
		}

		$results = $wpdb->get_results( $sql , ARRAY_A );
		//rt($results);
		return $results;
	}

		/*
SELECT 
a.product_id, a.code, a.hms_month, IFNULL(a.hms_qty,0) AS hms_qty, IFNULL(a.hms_unit,0) AS hms_unit 
FROM
(
    SELECT
        a.month AS hms_month,
        ic.base_id AS product_id, a.code,
        SUM(a.bqty * IFNULL(ic.base_unit, 1)) AS hms_qty,
        SUM(a.bunit) AS hms_unit
    FROM
    (
        SELECT
            DATE_FORMAT(h.doc_date, '%Y-%m') AS MONTH,
            i.code,
            SUM( d.bqty ) AS bqty,
            0 AS bunit
        FROM wp_stmm_wcwh_document h
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
        LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'base_doc_type'
        LEFT JOIN wp_stmm_wcwh_document_meta mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'client_company_code'
        LEFT JOIN wp_stmm_wcwh_client c ON c.code = mb.meta_key
		LEFT JOIN wp_stmm_wcwh_items i ON i.id = d.product_id
        WHERE
            1 AND h.doc_type = 'delivery_order' AND h.status IN( 6, 9 ) AND ma.meta_value = 'sale_order' 
			AND c.id NOT IN('2', '11', '29', '42', '50') AND h.doc_date >= '2022-03-01 00:00:00' AND h.doc_date <= '2022-09-30 23:59:59'
        GROUP BY
            DATE_FORMAT(h.doc_date, '%Y-%m'),
            i.code
    UNION ALL
		SELECT
			DATE_FORMAT(h.doc_date, '%Y-%m') AS MONTH,
			i.code,
			SUM( d.bqty ) AS bqty,
			0 AS bunit
		FROM wp_stmm_wcwh_document h
		LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
		LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type'
		LEFT JOIN wp_stmm_wcwh_items i ON i.id = d.product_id
		WHERE
			1 AND h.status IN( 6, 9 ) AND h.doc_type = 'good_issue' AND ma.meta_value IN('own_use', 'other') 
			AND h.doc_date >= '2022-03-01 00:00:00' AND h.doc_date <= '2022-09-30 23:59:59'
		GROUP BY
			DATE_FORMAT(h.doc_date, '%Y-%m'),
			i.code
	UNION ALL
		SELECT DATE_FORMAT(a.sales_date, '%Y-%m') AS MONTH, i.code, SUM( a.qty ) AS bqty, SUM(a.unit) AS bunit
		FROM wp_stmm_wcwh_selling_price a
		LEFT JOIN wp_stmm_wcwh_items i ON i.id = a.prdt_id
		WHERE
			1 AND a.status > 0 AND a.sales_date >= '2022-03-01 00:00:00' AND a.sales_date <= '2022-09-30 23:59:59'
		GROUP BY
			DATE_FORMAT(a.sales_date, '%Y-%m'),
			i.code
	) a
	LEFT JOIN wp_stmm_wcwh_items i ON i.code = a.code 
	LEFT JOIN wp_stmm_wcwh_item_converse ic ON ic.item_id = i.id
	GROUP BY
		a.month,
		ic.base_id
	ORDER BY
		ic.base_id ASC,
		hms_qty
	DESC
) a
WHERE 1 
GROUP BY
a.code
ORDER BY
a.code ASC
		*/
		public function get_highest_monthly_sales( $filters = [], $run = false, $args = [] )
		{
			global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            //------------------------------------hms - sale order
				$fld = "DATE_FORMAT( h.doc_date, '%Y-%m' ) AS month, i.code, SUM( d.bqty ) AS bqty, 0 AS bunit ";
				$tbl = "{$dbname}{$this->tables['document']} h ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'base_doc_type' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'client_company_code' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = mb.meta_key ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
				$cd = $wpdb->prepare( "AND h.status IN( 6, 9 ) AND h.doc_type = %s AND ma.meta_value = %s ", 'delivery_order', 'sale_order' );

				if( !empty( $this->setting['canteen_einvoice']['client'] ) )
				{
	    			$canteen = $this->setting['canteen_einvoice']['client'];
	    			$cd.= "AND c.id NOT IN( '".implode( "', '", $canteen )."' ) ";
				}

				if( isset( $filters['from_date'] ) )
	            {
	                $cd.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
	            }
	            if( isset( $filters['to_date'] ) )
	            {
	                $cd.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
	            }

	            $grp = "GROUP BY DATE_FORMAT( h.doc_date, '%Y-%m' ), i.code ";
	            $ord = "";
				$sales_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$grp} {$ord} ";

			//------------------------------------hms - own use
				$fld = "DATE_FORMAT( h.doc_date, '%Y-%m' ) AS month, i.code, SUM( d.bqty ) AS bqty, 0 AS bunit ";
				$tbl = "{$dbname}{$this->tables['document']} h ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
				$cd = $wpdb->prepare( "AND h.status IN( 6, 9 ) AND h.doc_type = %s ", 'good_issue' );
				$cd.= "AND ma.meta_value IN ( 'own_use', 'other' ) ";

				if( isset( $filters['from_date'] ) )
	            {
	                $cd.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
	            }
	            if( isset( $filters['to_date'] ) )
	            {
	                $cd.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
	            }

	            $grp = "GROUP BY DATE_FORMAT( h.doc_date, '%Y-%m' ), i.code ";
	            $ord = "";
				$ownuse_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$grp} {$ord} ";

			//------------------------------------hms - pos
				$fld = "DATE_FORMAT( a.sales_date, '%Y-%m' ) AS month, i.code, SUM( a.qty ) AS bqty, SUM( a.unit ) AS bunit ";
				$tbl = "{$dbname}{$this->tables['selling_price']} a ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = a.prdt_id ";
				$cd = $wpdb->prepare( "AND a.status > %d ", 0 );

				if( isset( $filters['from_date'] ) )
	            {
	                $cd.= $wpdb->prepare( "AND a.sales_date >= %s ", $filters['from_date'] );
	            }
	            if( isset( $filters['to_date'] ) )
	            {
	                $cd.= $wpdb->prepare( "AND a.sales_date <= %s ", $filters['to_date'] );
	            }

	            $grp = "GROUP BY DATE_FORMAT( a.sales_date, '%Y-%m' ), i.code ";
	            $ord = "";
				$pos_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$grp} {$ord} ";

			//------------------------------------outlet - pos
				$ol_sql = []; $ol_ownuse_sql = [];
				if( $this->outlets )
				{	
					foreach( $this->outlets as $i => $outlet )
					{
						$dbn = $outlet['dbname'].".";

					//Outlet POS
						$fld = "DATE_FORMAT( a.sales_date, '%Y-%m' ) AS month, i.code, SUM( a.qty ) AS bqty, SUM( a.unit ) AS bunit ";
						$tbl = "{$dbn}{$this->tables['selling_price']} a ";
						$tbl.= "LEFT JOIN {$dbn}{$this->tables['items']} i ON i.id = a.prdt_id ";
						$cd = $wpdb->prepare( "AND a.status > %d ", 0 );

						if( isset( $filters['from_date'] ) )
			            {
			                $cd.= $wpdb->prepare( "AND a.sales_date >= %s ", $filters['from_date'] );
			            }
			            if( isset( $filters['to_date'] ) )
			            {
			                $cd.= $wpdb->prepare( "AND a.sales_date <= %s ", $filters['to_date'] );
			            }

			            $grp = "GROUP BY DATE_FORMAT( a.sales_date, '%Y-%m' ), i.code ";
			            $ord = "";
						$ol_sql[] = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$grp} {$ord} ";

					//Outlet Company Use
						$fld = "DATE_FORMAT( h.doc_date, '%Y-%m' ) AS month, i.code, SUM( d.bqty ) AS bqty, 0 AS bunit ";
						$tbl = "{$dbn}{$this->tables['document']} h ";
						$tbl.= "LEFT JOIN {$dbn}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
						$tbl.= "LEFT JOIN {$dbn}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' ";
						$tbl.= "LEFT JOIN {$dbn}{$this->tables['items']} i ON i.id = d.product_id ";
						$cd = $wpdb->prepare( "AND h.status IN( 6, 9 ) AND h.doc_type = %s ", 'good_issue' );
						$cd.= "AND ma.meta_value IN ( 'own_use', 'other' ) ";

						if( isset( $filters['from_date'] ) )
			            {
			                $cd.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
			            }
			            if( isset( $filters['to_date'] ) )
			            {
			                $cd.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
			            }

			            $grp = "GROUP BY DATE_FORMAT( h.doc_date, '%Y-%m' ), i.code ";
			            $ord = "";
						$ol_ownuse_sql[] = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$grp} {$ord} ";
					}
				}
				
			$union_sql = "{$sales_sql} UNION ALL {$ownuse_sql} UNION ALL {$pos_sql} ";
			if( $ol_sql )
			{
				$union_sql.= " UNION ALL ".implode( " UNION ALL ", $ol_sql );
			}
			if( $ol_ownuse_sql )
			{
				$union_sql.= " UNION ALL ".implode( " UNION ALL ", $ol_ownuse_sql );
			}
			
			$inner_sql = "SELECT a.month AS hms_month, ic.base_id AS product_id, a.code
				, SUM( a.bqty * IFNULL(ic.base_unit, 1) ) AS hms_qty, SUM(a.bunit) AS hms_unit
				FROM ( $union_sql ) a 
				LEFT JOIN {$dbname}{$this->tables['items']} i ON i.code = a.code 
				LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = i.id
				GROUP BY a.month, ic.base_id
				ORDER BY ic.base_id ASC, hms_qty DESC ";

            //------------------------------------
            $field = "a.product_id, a.code
				, a.hms_month, IFNULL(a.hms_qty,0) AS hms_qty, IFNULL(a.hms_unit,0) AS hms_unit ";
            
            $table = "( {$inner_sql} ) a ";

            $cond = "";

            $grp = "GROUP BY a.code ";

            $ord = "ORDER BY a.code ASC ";

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
		}
	
} //class

}