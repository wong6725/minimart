<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_UnprocessedDoc_Rpt" ) ) 
{
	
class WCWH_UnprocessedDoc_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "UnprocessedDoc";

	public $tplName = array(
		'export' => 'exportUnprocessedDoc',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $def_export_title = [];

	public $DocType = array(
		'good_receive'		=> 'Goods Receipt',
		'reprocess'			=> 'Reprocess',
		'transfer_item'		=> 'Transfer Item',
		'delivery_order'	=> 'Delivery Order',
		'good_issue'		=> 'Goods Issue',
		'good_return'		=> 'Goods Return',
	);

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

			"client"		=> $prefix."client",
			"clientmeta"	=> $prefix."clientmeta",
			"client_tree"	=> $prefix."client_tree",

			"supplier"		=> $prefix."supplier",
			"suppliermeta"	=> $prefix."suppliermeta",
			"supplier_tree"	=> $prefix."supplier_tree",

			"warehouse"		=> $prefix."warehouse",
			"warehousemeta"	=> $prefix."warehousemeta",

			"items"			=> $prefix."items",
			"itemsmeta"		=> $prefix."itemsmeta",
			
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
			"reprocess_item"=> $prefix."reprocess_item",
			
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
					$datas['filename'] = "Unprocessed Document ";	
					$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					if( $datas['from_date'] ) $datas['filename'].= date( $date_format, strtotime( $datas['from_date'] ) );
					if( $datas['to_date'] )  $datas['filename'].= " - ".date( $date_format, strtotime( $datas['to_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['supplier'] ) ) $params['supplier'] = $datas['supplier'];
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['doc_type'] ) ) $params['doc_type'] = $datas['doc_type'];
					
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
		return $this->get_unprocessed_doc_report( $params );
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
					data-title="<?php echo $actions['export'] ?> Movement by Document Report" data-modal="wcwhModalImEx" 
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
		$action_id = 'unprocessed_doc_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
			'DocType'	=> $this->DocType,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		do_action( 'wcwh_templating', 'report/export-unprocessed_doc-report.php', $this->tplName['export'], $args );
	}

	/**
	 *	UnProcessed Document
	 */
	public function unprocessed_doc_report( $filters = array(), $order = array() )
	{
		$action_id = 'unprocessed_doc_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/unprocessDocList.php" ); 
			$Inst = new WCWH_UnprocessedDoc_Report();
			$Inst->seller = $this->seller;
			$Inst->DocType = $this->DocType;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
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
				'.qty, .in_price, .in_amt, .sell_price, .sell_amt' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_unprocessed_doc_report( $filters, $order, [] );
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
	public function get_unprocessed_doc_report( $filters = [], $order = [], $args = [] )
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
			$this->dbname = $dbname = ( $dbname )? $dbname."." : "";
		}
		if( isset( $filters['seller'] ) )
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
	    else
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	    if( $curr_wh ) $filters['warehouse_id'] = $curr_wh['code'];

	    if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) $prdt_fld = ", i.name AS item_name ";
	    if( ! current_user_cans( [ 'hide_amt_unprocessed_doc_wh_reports' ] ) ) 
	    	$amt_fld = ", a.in_price, a.in_amt, a.sell_price, a.sell_amt, a.cost_price, a.cost_amt ";
		$field = "a.doc_id, a.docno, a.doc_date, a.doc_type, a.issue_type
			, a.supplier, a.client 
			, CASE WHEN a.doc_status = 0 THEN 'deleted' WHEN a.doc_status = 1 THEN 'ready' WHEN a.doc_status > 5 THEN 'posted' END AS doc_status
			, CASE WHEN a.inv_type = '+' THEN 'IN+' WHEN a.inv_type = '-' THEN 'OUT-' END AS movement_type
			, a.ref_doc, a.ref_doc_type
			, cat.slug AS category_code, cat.name AS category_name
			, i.code AS item_code{$prdt_fld}, i._uom_code AS uom, a.bqty, a.uqty
			{$amt_fld} ";

		$union = [];
        $union[] = $this->get_goods_receipt( $filters );
        $union[] = $this->get_reprocess( $filters );
        $union[] = $this->get_transfer_item( $filters );
        $union[] = $this->get_sale_delivery_order( $filters );
        $union[] = $this->get_reverse_sale_delivery_order( $filters );
        $union[] = $this->get_transfer_delivery_order( $filters );
        $union[] = $this->get_good_issue( $filters );
        $union[] = $this->get_good_return( $filters );

        $table = "( ";
        if( $union ) $table.= "( ".implode( " ) UNION ALL ( ", $union )." ) ";
        $table.= ") a ";
		
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
		if( isset( $filters['doc_type'] ) )
		{
			if( is_array( $filters['doc_type'] ) )
				$cond .= "AND a.doc_type IN ('" .implode( "','", $filters['doc_type'] ). "') ";
			else
				$cond .= $wpdb->prepare( "AND a.doc_type = %s ", $filters['doc_type'] );
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
				$cd[] = "a.ref_doc LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno' => 'ASC', 'i.code' => 'ASC' ];
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

	public function get_goods_receipt( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = !empty( $this->dbname )? $this->dbname : "";

		$field = "h.doc_id, h.docno, h.doc_date, h.doc_type, '' AS issue_type
			, CONCAT( s.code, ' - ', s.name ) AS supplier, '' AS client 
			, h.status AS doc_status, '+' AS inv_type, ma.meta_value AS ref_doc, mb.meta_value AS ref_doc_type
			, d.product_id, d.bqty, d.uqty
			, ia.meta_value AS in_price, IFNULL( ib.meta_value, ia.meta_value * d.bqty ) AS in_amt
			, 0 AS sell_price, 0 AS sell_amt
			, 0 AS cost_price, 0 AS cost_amt ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'uprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} s ON s.code = mc.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['supplier_tree']} ";
			$subsql.= "WHERE 1 AND descendant = s.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} ss ON ss.id = ( {$subsql} ) ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status = %d AND h.flag > %d ", 'good_receive', 1, 0 );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
        }
        if( isset( $filters['supplier'] ) )
		{
			if( is_array( $filters['supplier'] ) )
			{
				$catcd = "s.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$catcd.= "OR ss.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "s.id = %d ", $filters['supplier'] );
				$catcd = $wpdb->prepare( "OR ss.id = %d ", $filters['supplier'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}

		$grp = "";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

	public function get_reprocess( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = !empty( $this->dbname )? $this->dbname : "";

		$field = "h.doc_id, h.docno, h.doc_date, h.doc_type, '' AS issue_type, '' AS supplier, '' AS client 
			, h.status AS doc_status, '+' AS inv_type, ma.meta_value AS ref_doc, mb.meta_value AS ref_doc_type
			, d.product_id, d.bqty, d.uqty
			, ia.meta_value AS in_price, IFNULL( ib.meta_value, ia.meta_value * d.bqty ) AS in_amt
			, 0 AS sell_price, 0 AS sell_amt
			, 0 AS cost_price, 0 AS cost_amt ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'uprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'total_amount' ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status = %d AND h.flag > %d ", 'reprocess', 1, 0 );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
        }

		$grp = "";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

	public function get_transfer_item( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = !empty( $this->dbname )? $this->dbname : "";

		$field = "h.doc_id, h.docno, h.doc_date, h.doc_type, '' AS issue_type, '' AS supplier, '' AS client 
			, h.status AS doc_status, '+' AS inv_type, ma.meta_value AS ref_doc, mb.meta_value AS ref_doc_type
			, d.product_id, d.bqty, d.uqty
			, ia.meta_value AS in_price, IFNULL( ib.meta_value, ia.meta_value * d.bqty ) AS in_amt
			, 0 AS sell_price, 0 AS sell_amt
			, 0 AS cost_price, 0 AS cost_amt ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'uprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'total_amount' ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status = %d AND h.flag > %d ", 'transfer_item', 1, 0 );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
        }

		$grp = "";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

	public function get_sale_delivery_order( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = !empty( $this->dbname )? $this->dbname : "";

		$field = "h.doc_id, h.docno, h.doc_date, h.doc_type, '' AS issue_type 
			, '' AS supplier, CONCAT( c.code, ' - ', c.name ) AS client 
			, h.status AS doc_status, '-' AS inv_type, ma.meta_value AS ref_doc, mb.meta_value AS ref_doc_type
			, d.product_id, d.bqty, d.uqty
			, 0 AS in_price, 0 AS in_amt
			, ia.meta_value AS sell_price, ROUND( d.bqty * ia.meta_value, 2 ) AS sell_amt
			, ib.meta_value AS cost_price, ROUND( d.bqty * ib.meta_value, 2 ) AS cost_amt ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = mc.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['client_tree']} ";
			$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} cc ON cc.id = ( {$subsql} ) ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status = %d AND h.flag > %d ", 'delivery_order', 1, 0 );
		$cond.= $wpdb->prepare( "AND mb.meta_value = %s ", 'sale_order' );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
        }
        if( isset( $filters['client'] ) )
		{
			if( is_array( $filters['client'] ) )
			{
				$catcd = "c.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$catcd.= "OR cc.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "c.id = %d ", $filters['client'] );
				$catcd = $wpdb->prepare( "OR cc.id = %d ", $filters['client'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}

		$grp = "";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

	public function get_reverse_sale_delivery_order( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = !empty( $this->dbname )? $this->dbname : "";

		$field = "h.doc_id, h.docno, h.doc_date, h.doc_type, '' AS issue_type 
			, '' AS supplier, CONCAT( c.code, ' - ', c.name ) AS client 
			, h.status AS doc_status, '-' AS inv_type, '' AS ref_doc, mb.meta_value AS ref_doc_type
			, d.product_id, d.bqty, d.uqty
			, 0 AS in_price, 0 AS in_amt
			, ia.meta_value AS sell_price, ROUND( d.bqty * ia.meta_value, 2 ) AS sell_amt
			, ib.meta_value AS cost_price, ROUND( d.bqty * ib.meta_value, 2 ) AS cost_amt ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'automate_sale' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'base_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = mc.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['client_tree']} ";
			$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} cc ON cc.id = ( {$subsql} ) ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status = %d AND h.flag > %d ", 'delivery_order', 1, 0 );
		$cond.= $wpdb->prepare( "AND mb.meta_value = %s AND ma.meta_value > 0 ", 'sale_order' );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
        }
        if( isset( $filters['client'] ) )
		{
			if( is_array( $filters['client'] ) )
			{
				$catcd = "c.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$catcd.= "OR cc.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "c.id = %d ", $filters['client'] );
				$catcd = $wpdb->prepare( "OR cc.id = %d ", $filters['client'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}

		$grp = "";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

	public function get_transfer_delivery_order( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = !empty( $this->dbname )? $this->dbname : "";

		$field = "h.doc_id, h.docno, h.doc_date, h.doc_type, '' AS issue_type
			, '' AS supplier, CONCAT( w.code, ' - ', w.name ) AS client 
			, h.status AS doc_status, '-' AS inv_type, ma.meta_value AS ref_doc, mb.meta_value AS ref_doc_type
			, d.product_id, d.bqty, d.uqty
			, 0 AS in_price, 0 AS in_amt
			, 0 AS sell_price, 0 AS sell_amt
			, ib.meta_value AS cost_price, ROUND( d.bqty * ib.meta_value, 2 ) AS cost_amt ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supply_to_seller' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['warehouse']} w ON w.code = mc.meta_value ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status = %d AND h.flag > %d ", 'delivery_order', 1, 0 );
		$cond.= $wpdb->prepare( "AND mb.meta_value = %s ", 'transfer_order' );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
        }

		$grp = "";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

	public function get_good_issue( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = !empty( $this->dbname )? $this->dbname : "";

		$field = "h.doc_id, h.docno, h.doc_date, h.doc_type, mc.meta_value AS issue_type
			, '' AS supplier, '' AS client 
			, h.status AS doc_status, '-' AS inv_type, ma.meta_value AS ref_doc, mb.meta_value AS ref_doc_type
			, d.product_id, d.bqty, d.uqty
			, 0 AS in_price, 0 AS in_amt
			, 0 AS sell_price, 0 AS sell_amt
			, ia.meta_value AS cost_price, ROUND( d.bqty * ia.meta_value, 2 ) AS cost_amt ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'good_issue_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'ucost' ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status = %d AND h.flag > %d ", 'good_issue', 1, 0 );
		$cond.= "AND mc.meta_value IN ( 'reprocess', 'own_use', 'other', 'vending_machine', 'block_stock', 'transfer_item', 'good_return' ) ";

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
        }

		$grp = "";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

	public function get_good_return( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = !empty( $this->dbname )? $this->dbname : "";

		$field = "h.doc_id, h.docno, h.doc_date, h.doc_type, '' AS issue_type 
			, CONCAT( s.code, ' - ', s.name ) AS supplier, '' AS client 
			, h.status AS doc_status, '-' AS inv_type, ma.meta_value AS ref_doc, mb.meta_value AS ref_doc_type
			, d.product_id, d.bqty, d.uqty
			, 0 AS in_price, 0 AS in_amt
			, 0 AS sell_price, 0 AS sell_amt
			, ia.meta_value AS cost_price, ROUND( d.bqty * ia.meta_value, 2 ) AS cost_amt ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'good_issue_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} s ON s.code = md.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['supplier_tree']} ";
			$subsql.= "WHERE 1 AND descendant = s.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} ss ON ss.id = ( {$subsql} ) ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status = %d AND h.flag > %d ", 'good_return', 1, 0 );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
        }
        if( isset( $filters['supplier'] ) )
		{
			if( is_array( $filters['supplier'] ) )
			{
				$catcd = "s.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$catcd.= "OR ss.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "s.id = %d ", $filters['supplier'] );
				$catcd = $wpdb->prepare( "OR ss.id = %d ", $filters['supplier'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}

		$grp = "";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

} //class

}