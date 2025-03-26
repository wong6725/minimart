<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_StockInOut_Rpt" ) ) 
{

class WCWH_StockInOut_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "InOut";

	public $Logic;

	protected $warehouse = array();

	public $tplName = array(
		'export' => 'exportMovement',
	);
	
	protected $tables = array();
	protected $dbname = '';

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $def_export_title = [];

	private $system_begin_date = '2020-09-01 00:00:00';

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_db_tables();
	}

	public function setWarehouse( $wh )
    {
    	$this->warehouse = $wh;
    }
	
	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"document"				=> $prefix."document",
			"document_items"		=> $prefix."document_items",

			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_weighted"	=> $prefix."transaction_weighted",

			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",

			"items"					=> $prefix."items",
			"uom"					=> $prefix."uom",

			"category"				=> $wpdb->prefix."terms",
			"category_tree"			=> $prefix."item_category_tree",
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

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":
					switch( $datas['export_type'] )
					{
						case 'stock_inout':
							$datas['filename'] = 'Stock In Out ';
						break;
					}
					
					$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					if( $datas['from_date'] ) $datas['filename'].= " ".date( 'Y-m-d', strtotime( $datas['from_date'] ) );
					if( $datas['to_date'] )  $datas['filename'].= " - ".date( 'Y-m-d', strtotime( $datas['to_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['month'] ) ) $params['month'] = date( 'Y-m', strtotime( $datas['month'] ) );
					if( !empty( $datas['from_month'] ) ) $params['from_month'] = date( 'Y-m', strtotime( $datas['from_month'] ) );
					if( !empty( $datas['to_month'] ) ) $params['to_month'] = date( 'Y-m', strtotime( $datas['to_month'] ) );
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['date_type'] ) ) $params['date_type'] = $datas['date_type'];
					if( !empty( $datas['group'] ) ) $params['group'] = $datas['group'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['supplier'] ) ) $params['supplier'] = $datas['supplier'];
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['doc_type'] ) ) $params['doc_type'] = $datas['doc_type'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					if( !empty( $datas['grouping'] ) ) $params['grouping'] = $datas['grouping'];
					if( !empty( $datas['follow_dc'] ) ) $params['follow_dc'] = $datas['follow_dc'];
					if( !empty( $datas['inconsistent_unit'] ) ) $params['inconsistent_unit'] = $datas['inconsistent_unit'];
					if( !empty( $datas['uom'] ) ) $params['uom'] = $datas['uom'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;
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
		$type = $params['export_type'];
		unset( $params['export_type'] );
		
		switch( $type )
		{
			case 'stock_inout':
				$datas = $this->get_stock_inout_report($params);

				$qty_in = 0;
				$qty_out = 0;
				$unit_in = 0;
				$unit_out = 0;
				$t_total_price = 0;
				$t_total_cost = 0;

				if( $datas )
				{
					foreach( $datas as $data )
					{
						$qty_in+= ( $data['plus_sign'] == '+' && $data['bqty'] )? $data['bqty'] : 0;
						$qty_out+= ( $data['plus_sign'] == '-' && $data['bqty'] )? $data['bqty'] : 0;
						$unit_in+= ( $data['plus_sign'] == '+' && $data['metric'] )? $data['metric'] : 0;
						$unit_out+= ( $data['plus_sign'] == '-' && $data['metric'] )? $data['metric'] : 0;
						$t_total_price+= ( $data['total_price'] )? $data['total_price'] : 0;
						$t_total_cost+= ( $data['total_cost'] )? $data['total_cost'] : 0;
					}
				}
				
				if( array_column($datas, 'bqty') ) $bqtyTotal = round_to( $qty_in - $qty_out, 2, 1, 1 );
				if( array_column($datas, 'metric') ) $metricTotal = round_to( $unit_in - $unit_out, 2, 1, 1 );
				if( array_column($datas, 'unit_price') ) $unitPriceTotal = ($qty_in != 0) ? round_to($t_total_price / $qty_in, 5, 1, 1) : 0;
				if( array_column($datas, 'total_price') ) $totalPriceTotal = round_to( $t_total_price, 2, 1, 1 );
				if( array_column($datas, 'unit_cost') ) $unitCostTotal = ($qty_out != 0) ? round_to($t_total_cost / $qty_out, 5, 1, 1) : 0;
				if( array_column($datas, 'total_cost') ) $totalCostTotal = round_to( $t_total_cost, 2, 1, 1 );
				if( array_column($datas, 'bal_amount') ) $balAmountTotal = round_to( $t_total_price - $t_total_cost, 2, 1, 1 );
				
				// Add total row to data
				$totalRow = array(
					'no' => '',
					'prdt_name' => 'Total', // Adjust as needed
					'category' => '',
					'uom' => '',
					'lupdate_at' => '',
					'plus_sign' => '', // Assuming these fields have numeric values to sum
					'bqty' => $bqtyTotal,
					'metric' => $metricTotal,
					'unit_price' => $unitPriceTotal,
					'total_price' => $totalPriceTotal, // Set the total value here
					'unit_cost' => $unitCostTotal,
					'total_cost' => $totalCostTotal,
					'bal_qty' => '',
					'bal_unit' => '',
					'bal_price' => '',
					'bal_amount' => $balAmountTotal
				);

				// Prepend number column to each row
				foreach ($datas as $key => $data) {
					$datas[$key] = array_merge(array('no' => $key + 1), $data);
				}

				// Add an empty row
				$emptyRow = array_fill_keys(array_keys($totalRow), '');

				// Add empty row between data and total row
				array_push($datas, $emptyRow, $totalRow);

				return $datas;
		}
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
		$action_id = 'movement_export';
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
			case 'stock_inout':
				do_action( 'wcwh_templating', 'report/export-stock_inout-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	/**
	 *	Stock In/Out
	 */
	public function stock_inout_report( $filters = array(), $order = array() )
	{
		$action_id = 'stock_inout_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockInOutList.php" ); 
			$Inst = new WCWH_StockInOut_Report();
			$Inst->seller = $this->seller;
			
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
				'.plus_sign, .uom' => [ 'text-align'=>'center !important' ],
				'.bqty, .bal_qty, .bal_unit, .metric, .unit_price, .total_price, .unit_cost, .total_cost, .bal_price, .bal_amount' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_stock_inout_report( $filters, [], [] );
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
	/*
	Stock In/Out document:
	*/
	public function get_stock_inout_report( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
			}
		}

		if( isset( $filters['seller'] ) || ( $this->warehouse['view_outlet'] && $this->warehouse['id'] ) )
        {
        	$sid = !empty( $filters['seller'] )? $filters['seller'] : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $sid, 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

		$field = "it.name AS prdt_name, cat.name AS category, it._uom_code AS uom, b.doc_post_date ";
		$field.= ", w.plus_sign, w.qty AS bqty, w.unit AS metric
			, IF( (w.plus_sign='+' AND w.type>0) OR (w.plus_sign='-' AND w.type<0), w.amount, 0 ) AS total_price
			, IF( (w.plus_sign='+' AND w.type>0) OR (w.plus_sign='-' AND w.type<0), w.price, 0 ) AS unit_price 
			, IF( (w.plus_sign='-' AND w.type>0) OR (w.plus_sign='+' AND w.type<0), w.amount, 0 ) AS total_cost
			, IF( (w.plus_sign='-' AND w.type>0) OR (w.plus_sign='+' AND w.type<0), w.price, 0 ) AS unit_cost
			, w.bal_qty, w.bal_unit, w.bal_price, w.bal_amount";

		$table = "{$dbname}{$this->tables['transaction_weighted']} w ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} a ON a.did = w.did AND a.item_id = w.item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} b ON b.hid = a.hid ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} it ON it.id = w.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = it.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$cond.= $wpdb->prepare( "AND w.status != %d ", 0 );

		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND it.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND it.id = %d ", $filters['product'] );
		}
		if( isset( $filters['product_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND w.product_id = %s ", $filters['product_id'] );
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
		if( isset( $filters['uom'] ) )
		{
			if( is_array( $filters['uom'] ) )
				$cond.= "AND it._uom_code IN ('" .implode( "','", $filters['uom'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND it._uom_code = %s ", $filters['uom'] );
		}
		if( isset( $filters['transact'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.plus_sign = %s ", $filters['transact'] );
		}
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND b.doc_post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['to_date'] );
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
				$cd[] = "it.name LIKE '%".$kw."%' ";
				$cd[] = "it._uom_code LIKE '%".$kw."%' ";
				$cd[] = "cat.name LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		//group
		if( !empty( $group ) )
		{
			$grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
		$order = !empty( $order )? $order : [ 'w.lupdate_at' => 'ASC', 'w.wid' => 'ASC' ];
		$o = array();
		foreach( $order as $order_by => $seq )
		{
			$o[] = "{$order_by} {$seq} ";
		}
		$ord.= "ORDER BY ".implode( ", ", $o )." ";

		//limit offset
		if( !empty( $limit ) )
		{
			$l.= "LIMIT ".implode( ", ", $limit )." ";
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}
	
}
}