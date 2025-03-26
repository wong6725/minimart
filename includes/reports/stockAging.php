<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_StockAging_Rpt" ) ) 
{
	
class WCWH_StockAging_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "StockAging";

	public $tplName = array(
		'export' => 'exportStockAging',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $def_export_title = [];

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

			"warehouse"		=> $prefix."warehouse",
			"warehousemeta"	=> $prefix."warehousemeta",

			"items"			=> $prefix."items",
			"itemsmeta"		=> $prefix."itemsmeta",
			"item_converse"	=> $prefix."item_converse",
			
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
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
			$date_format = get_option( 'date_format' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":			
					$datas['filename'] = "Stock Aging {$datas['period_type']} ";	
					//$datas['nodate'] = 1;
					$datas['dateformat'] = 'YmdHis';

					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['period_type'] ) ) $params['period_type'] = $datas['period_type'];
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

		if( $this->def_export_title )
			$default_column['title'] = $this->def_export_title;

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		return $this->get_stock_aging_report( $params );
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

	public function export_form()
	{
		$action_id = 'stock_aging_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		do_action( 'wcwh_templating', 'report/export-stock_aging-report.php', $this->tplName['export'], $args );
	}

	/**
	 *	Aging Report
	 */
	public function stock_aging_report( $filters = array(), $order = array() )
	{
		$action_id = 'stock_aging_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockAgingList.php" ); 
			$Inst = new WCWH_StockAging_Report();
			$Inst->seller = $this->seller;

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.wcwh-page .widefat td, .wcwh-page .widefat th' => [ 'font-size' => '10px', 'padding' => '1.5px' ],
				'thead th' => [ 'top'=>'68px !important' ],
				'thead .thead th' => [ 'top'=>'50px !important', 'text-align'=>'center' ],
				'.total_qty, .total_amt, .qty_1, .amt_1, .qty_2, .amt_2, .qty_3, .amt_3, .qty_4, .amt_4, .qty_5, .amt_5
					, .qty_6, .amt_6, .above_qty, .above_amt' => [ 'text-align'=>'right !important' ],

				'#total_qty, #qty_1, #qty_2, #qty_3, #qty_4, #qty_5, #qty_6, #above_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'.total_qty, .qty_1, .qty_2, .qty_3, .qty_4, .qty_5, .qty_6, .above_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_stock_aging_report( $filters, $order, [] );
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
	public function get_stock_aging_report( $filters = [], $order = [], $args = [] )
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
			$wh = apply_filters( 'wcwh_get_warehouse', ['id'=>$filters['seller']], [], true, [] );
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$this->dbname = $dbname = ( $dbname )? $dbname."." : "";
		}
		else
		{
			$wh = apply_filters( 'wcwh_get_warehouse', ['indication'=>1], [], true, [ 'usage'=>1 ] );
		}
		$strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$wh['code'], 'doc_type'=>'inventory', 'seller'=>$filters['seller'] ] );
	    
		if( ! $filters['period_type'] ) $filters['period_type'] = 'MONTH';
		$type = strtoupper( $filters['period_type'] );
		$field = "i.code AS prdt_code, i.name AS prdt_name, i._uom_code AS uom, cat.slug AS category_code, cat.name AS category_name ";
		$field.= ", ROUND( SUM( a.bal_qty ), 2 ) AS total_qty, ROUND( SUM( a.bal_amt ), 2 ) AS total_amt ";
		$field.= ", ROUND( SUM( IF( TIMESTAMPDIFF( MONTH, a.post_date, CURDATE() ) = 0, a.bal_qty, 0 ) ), 2 ) AS 'qty_1'
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 0, a.bal_amt, 0 ) ), 2 ) AS 'amt_1'
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 1, a.bal_qty, 0 ) ), 2 ) AS 'qty_2' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 1, a.bal_amt, 0 ) ), 2 ) AS 'amt_2' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 2, a.bal_qty, 0 ) ), 2 ) AS 'qty_3' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 2, a.bal_amt, 0 ) ), 2 ) AS 'amt_3' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 3, a.bal_qty, 0 ) ), 2 ) AS 'qty_4' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 3, a.bal_amt, 0 ) ), 2 ) AS 'amt_4' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 4, a.bal_qty, 0 ) ), 2 ) AS 'qty_5' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 4, a.bal_amt, 0 ) ), 2 ) AS 'amt_5' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 5, a.bal_qty, 0 ) ), 2 ) AS 'qty_6' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) = 5, a.bal_amt, 0 ) ), 2 ) AS 'amt_6' 
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) > 5, a.bal_qty, 0 ) ), 2 ) AS above_qty
		, ROUND( SUM( IF( TIMESTAMPDIFF( {$type}, a.post_date, CURDATE() ) > 5, a.bal_amt, 0 ) ), 2 ) AS above_amt ";
		
		switch( strtoupper( $filters['period_type'] ) )
		{
			case 'QUARTER':
				$this->def_export_title = [ 
					'Item Code', 'Item Name', 'UOM', 'Category Code', 'Category Name', 
					'Total Qty', 'Total Amt',
					'3 M Qty', '3 M Amt',
					'6 M Qty', '6 M Amt',
					'9 M Qty', '9 M Amt',
					'12 M Qty', '12 M Amt',
					'15 M Qty', '15 M Amt',
					'18 M Qty', '18 M Amt',
					'Above Qty', 'Above Amt',
				];
			break;
			case 'YEAR':
				$this->def_export_title = [ 
					'Item Code', 'Item Name', 'UOM', 'Category Code', 'Category Name', 
					'Total Qty', 'Total Amt',
					'1 Y Qty', '1 Y Amt',
					'2 Y Qty', '2 Y Amt',
					'3 Y Qty', '3 Y Amt',
					'4 Y Qty', '4 Y Amt',
					'5 Y Qty', '5 Y Amt',
					'6 Y Qty', '6 Y Amt',
					'Above Qty', 'Above Amt',
				];
			break;
			case 'MONTH':
			default:
				$this->def_export_title = [ 
					'Item Code', 'Item Name', 'UOM', 'Category Code', 'Category Name', 
					'Total Qty', 'Total Amt',
					'1 Y Qty', '1 Y Amt',
					'2 Y Qty', '2 Y Amt',
					'3 Y Qty', '3 Y Amt',
					'4 Y Qty', '4 Y Amt',
					'5 Y Qty', '5 Y Amt',
					'6 Y Qty', '6 Y Amt',
					'Above Qty', 'Above Amt',
				];
			break;
		}

		$subsql = "
			SELECT h.doc_id, h.docno, h.doc_type, IFNULL(te.meta_value,h.post_date ) AS post_date
			, ti.hid, ti.did, ti.item_id, ic.base_id AS product_id
			, @bqty:= ti.bqty * IFNULL(ic.base_unit,0) AS bqty
			, @dqty:= ti.deduct_qty * IFNULL(ic.base_unit,0) AS deduct_qty
			, @balqty:= @bqty - @dqty AS bal_qty
			, ti.weighted_total AS total_price, ROUND( ti.weighted_total / @bqty * @balqty, 2 ) AS bal_amt
			, ti.status, ti.flag
			FROM {$dbname}{$this->tables['transaction']} t 
			LEFT JOIN {$dbname}{$this->tables['document']} h ON h.doc_id = t.doc_id 
			LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.status > 0
			LEFT JOIN {$dbname}{$this->tables['transaction_meta']} te ON te.hid = ti.hid AND te.did = ti.did AND te.ddid = 0 AND te.meta_key = 'prod_expiry'
			LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = ti.product_id
			WHERE 1 AND t.status > 0 AND h.status >= 6 
			AND ti.plus_sign = '+' AND ti.flag = 0
			AND ti.warehouse_id = '{$wh['code']}' AND ti.strg_id = '{$strg_id}'
		";

        $table = "( {$subsql} ) a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = a.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$cond = "";

		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond .= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond .= $wpdb->prepare( "AND i.id = %s ", $filters['product'] );
		}
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

		$grp = "GROUP BY a.product_id ";

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
		
		return $results;
	}

} //class

}