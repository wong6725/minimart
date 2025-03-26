<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ToolRequestFulfilment_Rpt" ) ) 
{
	
class WCWH_ToolRequestFulfilment_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "ToolRequestFulfilment";

	public $tplName = array(
		'export' => 'exportToolRequest',
		'print' => 'printToolRequest',
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

			"items"			=> $prefix."items",
			"item_group"	=> $prefix."item_group",
			
			"customer" 		=> $prefix."customer",
			"tree"			=> $prefix."customer_tree",
			"meta"			=> $prefix."customermeta",

			"customer_group"=> $prefix."customer_group",
			"customer_job"	=> $prefix."customer_job",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",

			"postmeta"		=> $wpdb->prefix."postmeta",
			"price"			=> $prefix."price",

			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"inventory"		=> $prefix."inventory",
			"storage"		=> $prefix."storage",
		);
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

	
	public function tool_request_fulfilment( $filters = array(), $order = array() )
	{
		$action_id = 'tool_request_fulfilment';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/toolRequestFulfilmentList.php" ); 
			$Inst = new WCWH_ToolRequestFulfilment_Report();
			$Inst->seller = $this->seller;

			if( $this->seller ) $filters['seller'] = $this->seller;
			if($filters['seller'])
			{
				$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
				$filters['wh'] = $wh['code'];
				
			}else
			{
				$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
				$filters['wh'] = $curr_wh ['code'];
			}
			
			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.bal_qty, .fulfil_amt, .fulfil_qty, .sale_amt, .sprice, .quantity, .stock_qty' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_tool_request_fulfilment_report( $filters, $order, [] );
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
	public function get_tool_request_fulfilment_report( $filters = [], $order = [], $args = [] )
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

			$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
		}
		else
		{
			$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		}
		
		$field = "a.docno, DATE_FORMAT( a.doc_date, '%Y-%m-%d' ) AS doc_date
			, e.name as customer, e.code as customer_code, e.uid AS sap_uid, e.status AS customer_status ";
		$field.= ", a2.meta_value AS remark ";
		$field.= ", g.name as item_group, f.code as item_code, f.name as item_name ";
		$field.= ", f._uom_code as uom, b3.meta_value AS instalment, b.bqty as quantity, b1.meta_value AS sprice, b2.meta_value AS sale_amt ";
		$field.= ", group_concat( distinct s.receipt separator ', ' ) as receipt ";
		if( $warehouse ) $field.= ", iv.qty - iv.allocated_qty AS stock_qty ";
		$field.= ", SUM( s.qty ) AS fulfil_qty, SUM( s.line_total ) AS fulfil_amt, b.bqty - SUM( IFNULL(s.qty,0) ) AS bal_qty ";
		
		$table = "{$dbname}{$this->tables['document']} a ";	
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d ON d.doc_id = a.doc_id AND d.item_id = 0 AND d.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} a2 ON a2.doc_id = a.doc_id AND a2.item_id = 0 AND a2.meta_key = 'remark' ";		
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} e ON e.id = d.meta_value ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b1 ON b1.doc_id = b.doc_id AND b1.item_id = b.item_id AND b1.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b2 ON b2.doc_id = b.doc_id AND b2.item_id = b.item_id AND b2.meta_key = 'sale_amt' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b3 ON b3.doc_id = b.doc_id AND b3.item_id = b.item_id AND b3.meta_key = 'period' ";	
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} f ON f.id = b.product_id ";	
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_group']} g ON g.id = f.grp_id ";

		$subsql1 = "SELECT o.post_date, o1.meta_value AS tr_id, o2.meta_value AS receipt, o4.meta_value AS customer_code
			, m.meta_value AS item_id, n.meta_value AS qty, p.meta_value AS line_total
			FROM {$dbname}{$wpdb->posts} o 
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o1 on o1.post_id = o.ID AND o1.meta_key = 'tool_request_id'
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o2 on o2.post_id = o.ID AND o2.meta_key = '_order_number'
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o3 on o3.post_id = o.ID AND o3.meta_key = '_order_total'
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o4 on o4.post_id = o.ID AND o4.meta_key = '_customer_code'
			LEFT JOIN {$dbname}{$this->tables['order_items']} l ON l.order_id = o.ID AND l.order_item_type = 'line_item' 
			LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = l.order_item_id AND m.meta_key = '_items_id'
			LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} n ON n.order_item_id = l.order_item_id AND n.meta_key = '_qty'
			LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} p ON p.order_item_id = l.order_item_id AND p.meta_key = '_line_total'
			WHERE 1 AND o.post_type = 'shop_order' AND o.post_status IN ( 'wc-processing', 'wc-completed' ) AND o3.meta_value IS NOT NULL
			AND o1.meta_value > 0 
		";

		$subsql2 = "SELECT h.post_date, th.doc_id AS tr_id, h.docno AS receipt, th1.meta_value AS customer_code
			, d.product_id AS item_id, d.bqty AS qty, d1.meta_value AS line_total
			FROM {$dbname}{$this->tables['document']} h 
			LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'ref_doc_type'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h2 ON h2.doc_id = h.doc_id AND h2.item_id = 0 AND h2.meta_key = 'ref_doc_id'
			LEFT JOIN {$dbname}{$this->tables['document']} th ON th.doc_id = h2.meta_value
			LEFT JOIN {$dbname}{$this->tables['document_meta']} th1 ON th1.doc_id = th.doc_id AND th1.item_id = 0 AND th1.meta_key = 'customer_code'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} d1 ON d1.doc_id = d.doc_id AND d1.item_id = d.item_id AND d1.meta_key = 'line_total'
			WHERE 1 AND h.doc_type = 'sale_order' AND h.status >= 6 AND h1.meta_value = 'tool_request'
			AND th.doc_type = 'tool_request' AND th.status >= 6 
		";

		$subsql = "( {$subsql1} ) UNION ( {$subsql2} ) ";

		$table.= "LEFT JOIN ( {$subsql} ) s ON s.tr_id = a.doc_id AND s.item_id = b.product_id ";

		if( $warehouse )
		{
			$table.= "LEFT JOIN {$dbname}{$this->tables['storage']} strg ON strg.wh_code = '{$warehouse['code']}' AND strg.sys_reserved = 'staging' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['inventory']} iv ON iv.warehouse_id = '{$warehouse['code']}' AND iv.strg_id = strg.id AND iv.prdt_id = f.id ";
		}
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status > %d AND a.status < %d ", 'tool_request', 1, 9 );
		
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND e.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND e.id = %s ", $filters['customer'] );
		}
		if( isset( $filters['product_id'] ) )
		{
			if( is_array( $filters['product_id'] ) )
				$cond.= "AND b.product_id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND b.product_id = %s ", $filters['product_id'] );
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
                $cd[] = "a.docno LIKE '%".$kw."%' ";
				$cd[] = "e.name LIKE '%".$kw."%' ";
				$cd[] = "e.code LIKE '%".$kw."%' ";
				$cd[] = "f.name LIKE '%".$kw."%' ";
				
                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY a.doc_id, b.product_id ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.doc_date'=>'ASC', 'a.docno'=>'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$main_sql = $sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		$cond = "AND a.bal_qty > 0 ";

		$sql = "SELECT a.* FROM ( {$main_sql} ) a WHERE 1 {$cond} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
} //class

}