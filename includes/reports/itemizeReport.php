<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Itemize_Rpt" ) ) 
{
	
class WCWH_Itemize_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "Itemize";

	public $tplName = array(
		'export' => 'exportItemize',
		'print' => 'printItemize',
	);
	
	protected $tables = array();

	public $seller = 0;
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
			"itemize"		=> $prefix."itemize",
			"itemizemeta"	=> $prefix."itemizemeta",

			"items"			=> $prefix."items",
			"itemsmeta"		=> $prefix."itemsmeta",

			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",

			"status"		=> $prefix."status",
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"order"			=> $wpdb->posts,
			"ordermeta"		=> $wpdb->postmeta,

			"selling_price"	=> $prefix."selling_price",
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
					$filter = [ 'id'=>$datas['acc_type'] ];
					if( $this->seller ) $filter['seller'] = $this->seller;
					if( !empty( $datas['acc_type'] ) ) $acc_type = apply_filters( 'wcwh_get_account_type', $filter, [], true, [] );
					
					switch( $datas['export_type'] )
					{
						default:
							$datas['filename'] = 'Itemize Report ';
						break;
					}
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];

					//$this->export_data_handler( $params );
					$this->print_handler( $params, $datas );
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
				return $this->get_itemize_report( $params, $order, [] );
			break;
		}
	}

	public function print_handler( $params = array(), $opts = array() )
	{
		$datas = $this->export_data_handler( $params );
		$type = $params['export_type'];
		unset( $params['export_type'] );
		$date_format = get_option( 'date_format' );
		switch( $type )
		{
			default:
				$filename = "Itemize Report";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );

				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Itemize Report';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Itemize Report';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Product' => [ 'width'=>'30%', 'class'=>['leftered'] ],
					'Expiry' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Opening Qty' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Stock In Qty' => [ 'width'=>'12%', 'class'=>['rightered'] ],
					'Stock Out Qty' => [ 'width'=>'12%', 'class'=>['rightered'] ],
					'Closing Qty' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Sale Qty' => [ 'width'=>'8%', 'class'=>['rightered'] ],
					'Sale Amt' => [ 'width'=>'8%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$op_qty = 0; $in_qty = 0; $out_qty = 0; $balance_qty = 0; $sale_qty = 0; $sale_amt = 0;
					$details = [];
					foreach( $datas as $i => $data )
					{
						$product = [];
						if( $data['product_code'] ) $product[] = $data['product_code'];
						if( $data['product_name'] ) $product[] = $data['product_name'];

						$data['product'] = implode( ' - ', $product );

						$row = [

'product' => [ 'value'=>$data['product'], 'class'=>['leftered'] ],
'expiry' => [ 'value'=>$data['expiry'], 'class'=>['leftered'] ],
'op_qty' => [ 'value'=>$data['op_qty'], 'class'=>['rightered'], 'num'=>1 ],
'in_qty' => [ 'value'=>$data['in_qty'], 'class'=>['rightered'], 'num'=>1 ],
'out_qty' => [ 'value'=>$data['out_qty'], 'class'=>['rightered'], 'num'=>1 ],
'balance_qty' => [ 'value'=>$data['balance_qty'], 'class'=>['rightered'], 'num'=>1 ],
'sale_qty' => [ 'value'=>$data['sale_qty'], 'class'=>['rightered'], 'num'=>1 ],
'sale_amt' => [ 'value'=>$data['sale_amt'], 'class'=>['rightered'], 'num'=>1 ],

						];

						$op_qty+= $data['op_qty'];
						$in_qty+= $data['in_qty'];
						$out_qty+= $data['out_qty'];
						$balance_qty+= $data['balance_qty'];
						$sale_qty+= $data['sale_qty'];
						$sale_amt+= $data['sale_amt'];

						$details[] = $row;
					}

					$details[] = [
						'product' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>1 ],
						'expiry' => [ 'value'=>'', 'class'=>[], 'colspan'=>1 ],
						'op_qty' => [ 'value'=>$op_qty, 'class'=>['rightered','bold'], 'num'=>1 ],
						'in_qty' => [ 'value'=>$in_qty, 'class'=>['rightered','bold'], 'num'=>1 ],
						'out_qty' => [ 'value'=>$out_qty, 'class'=>['rightered','bold'], 'num'=>1 ],
						'balance_qty' => [ 'value'=>$balance_qty, 'class'=>['rightered','bold'], 'num'=>1 ],
						'sale_qty' => [ 'value'=>$sale_qty, 'class'=>['rightered','bold'], 'num'=>1 ],
						'sale_amt' => [ 'value'=>$sale_amt, 'class'=>['rightered','bold'], 'num'=>1 ],
					];

					$document['detail'] = $details;
				}
				//pd($document);
				ob_start();
							
					do_action( 'wcwh_get_template', 'template/doc-summary-general.php', $document );
				
				$content.= ob_get_clean();
				//echo $content;exit;
				if( is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) ){
					$paper = [ 'size' => 'A4', 'orientation' => $opts['orientation']? $opts['orientation'] : 'portrait' ];
					$args = [ 'filename' => $filename ];
					do_action( 'dompdf_generator', $content, $paper, array(), $args );
				}
				else{
					echo $content;
				}
			break;
		}

		exit;
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
					data-title="<?php echo $actions['export'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
			case 'print':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['print'] ?>" 
					data-title="<?php echo $actions['print'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|printing" 
					title="<?php echo $actions['print'] ?>"
				>
					<i class="fa fa-print" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = 'summary' )
	{
		$action_id = 'itemize_report';
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
				do_action( 'wcwh_templating', 'report/export-itemize-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'itemize_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			default:
				do_action( 'wcwh_templating', 'report/export-itemize-report.php', $this->tplName['print'], $args );
			break;
		}
	}


	public function itemize_report( $filters = array(), $order = array() )
	{
		$action_id = 'itemize_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/itemizeReportList.php" ); 
			$Inst = new WCWH_Itemize_Report();
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
				'.op_qty, .in_qty, .out_qty, .balance_qty, .sale_qty, .sale_amt' => [ 'text-align'=>'right !important' ],
				'#op_qty a span, #in_qty a span, #out_qty a span, #balance_qty a span, #sale_qty a span, #sale_amt a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_itemize_report( $filters, $order, [] );
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
SELECT a.product_id, a.product_code, a.product_name, a.uom, a.expiry
, a.op_qty, a.in_qty, a.out_qty, a.op_qty + a.in_qty - a.out_qty AS balance_qty , a.sale_qty, a.sale_amt 
FROM (
	SELECT a.product_id, i.code AS product_code, i.name AS product_name, i._uom_code AS uom
	, a.expiry, SUM( a.op_qty ) AS op_qty, SUM( a.in_qty ) AS in_qty, SUM( a.out_qty ) AS out_qty, SUM( a.sale_qty ) AS sale_qty, SUM( a.sale_amt ) AS sale_amt 
	FROM (
		SELECT a.product_id, a.expiry, SUM( a.in_qty ) - SUM( a.out_qty ) AS op_qty, 0 AS in_qty, 0 AS out_qty, 0 AS sale_qty, 0 AS sale_amt 
		FROM ( 
			SELECT a.product_id, a.expiry , SUM( 1 ) AS in_qty, 0 AS out_qty, 0 AS sale_qty, 0 AS sale_amt 
			FROM wp_stmm_wcwh_itemize a 
			WHERE 1 AND a.created_at <= '2021-10-31 23:59:59' 
			GROUP BY a.product_id, a.expiry 
				UNION ALL 
			SELECT a.product_id, a.expiry , 0 AS in_qty, SUM( 1 ) AS out_qty, SUM( 1 ) AS sale_qty, SUM( a.unit_price ) AS sale_amt 
			FROM wp_stmm_wcwh_itemize a 
			LEFT JOIN wp_stmm_woocommerce_order_items oi ON oi.order_item_id = a.sales_item_id 
			LEFT JOIN wp_stmm_posts o ON o.ID = oi.order_id 
			WHERE 1 AND a.status > 0 AND a.stock = 0 AND o.post_status IN( 'wc-processing', 'wc-completed' ) AND o.post_type = 'shop_order'
			AND o.post_date <= '2021-10-31 23:59:59' 
			GROUP BY a.product_id, a.expiry 
		) a 
		WHERE 1 GROUP BY a.product_id, a.expiry 
			UNION ALL
		SELECT a.product_id, a.expiry, 0 AS op_qty, SUM( a.in_qty ) AS in_qty, SUM( a.out_qty ) AS out_qty, SUM( a.sale_qty ) AS sale_qty, SUM( a.sale_amt ) AS sale_amt 
		FROM ( 
			SELECT a.product_id, a.expiry , SUM( 1 ) AS in_qty, 0 AS out_qty, 0 AS sale_qty, 0 AS sale_amt 
			FROM wp_stmm_wcwh_itemize a 
			WHERE 1 AND a.created_at >= '2021-11-01 00:00:00' AND a.created_at <= '2021-11-30 23:59:59' 
			GROUP BY a.product_id, a.expiry 
				UNION ALL 
			SELECT a.product_id, a.expiry , 0 AS in_qty, SUM( 1 ) AS out_qty, SUM( 1 ) AS sale_qty, SUM( a.unit_price ) AS sale_amt 
			FROM wp_stmm_wcwh_itemize a 
			LEFT JOIN wp_stmm_woocommerce_order_items oi ON oi.order_item_id = a.sales_item_id 
			LEFT JOIN wp_stmm_posts o ON o.ID = oi.order_id 
			WHERE 1 AND a.status > 0 AND a.stock = 0 AND o.post_status IN( 'wc-processing', 'wc-completed' ) AND o.post_type = 'shop_order'
			AND o.post_date >= '2021-11-01 00:00:00' AND o.post_date <= '2021-11-30 23:59:59' 
			GROUP BY a.product_id, a.expiry 
		) a
		WHERE 1 GROUP BY a.product_id, a.expiry
	) a 
	LEFT JOIN wp_stmm_wcwh_items i ON i.id = a.product_id
	WHERE 1 
	GROUP BY a.product_id, a.expiry
) a 
WHERE 1 AND ( a.op_qty > 0 OR a.in_qty > 0 OR a.out_qty > 0 )
ORDER BY a.product_id ASC, a.expiry ASC
	 */
	public function get_itemize_report( $filters = [], $order = [], $args = [] )
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

		$field = "a.product_id, a.expiry, a.created_at 
			, SUM( a.in_qty ) - SUM( a.out_qty ) AS op_qty, 0 AS in_qty, 0 AS out_qty, 0 AS sale_qty, 0 AS sale_amt ";

		$f = [ 'to_date' => date( 'Y-m-d 23:59:59', strtotime( $filters['from_date']." -1 day " ) ) ];
		$f['product'] = $filters['product'];
		$in_query = $this->get_itemize_in( $f );
		$sale_query = $this->get_itemize_sale( $f );
		$out_query = $this->get_itemize_other_out( $f );
		$table = "( ({$in_query}) UNION ALL ({$sale_query}) UNION ALL ({$out_query}) ) a ";
		$cond = "";
		$grp = "GROUP BY a.product_id, a.expiry, a.created_at ";
		$ord = "";
		$op_sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//-----------------------------------------------------------------

		if( ! current_user_cans( [ 'hide_amt_itemize_wh_reports' ] ) ) 
	    	$amt_fld = ", SUM( a.sale_amt ) AS sale_amt ";
		$field = "a.product_id, a.expiry, a.created_at 
			, 0 AS op_qty, SUM( a.in_qty ) AS in_qty, SUM( a.out_qty ) AS out_qty
			, SUM( a.sale_qty ) AS sale_qty {$amt_fld} ";

		$in_query = $this->get_itemize_in( $filters );
		$sale_query = $this->get_itemize_sale( $filters );
		$out_query = $this->get_itemize_other_out( $filters );
		$table = "( ({$in_query}) UNION ALL ({$sale_query}) UNION ALL ({$out_query}) ) a ";
		$cond = "";
		$grp = "GROUP BY a.product_id, a.expiry, a.created_at ";
		$ord = "";
		$cs_sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//-----------------------------------------------------------------

		$field = "a.product_id, i.code AS product_code, i.name AS product_name, i._uom_code AS uom
			, a.expiry, a.created_at 
			, SUM( a.op_qty ) AS op_qty, SUM( a.in_qty ) AS in_qty, SUM( a.out_qty ) AS out_qty
			, SUM( a.sale_qty ) AS sale_qty, SUM( a.sale_amt ) AS sale_amt ";
		
		$table = "( {$op_sql} UNION ALL {$cs_sql} ) a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = a.product_id ";
		
		$cond = "";
		$grp = "GROUP BY a.product_id, a.expiry, a.created_at ";

		$sub_sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//-----------------------------------------------------------------

		$field = "a.product_id, a.product_code, a.product_name, a.uom, a.expiry, a.created_at 
			, a.op_qty, a.in_qty, a.out_qty, a.op_qty + a.in_qty - a.out_qty AS balance_qty , a.sale_qty, a.sale_amt ";
		
		$table = "( {$sub_sql} ) a ";
		
		$cond = "AND ( a.op_qty > 0 OR a.in_qty > 0 OR a.out_qty > 0 ) ";
		$grp = "GROUP BY a.product_id, a.expiry, a.created_at ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.product_code' => 'ASC', 'a.created_at' => 'ASC', 'a.expiry' => 'ASC' ];
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

		/*
		SELECT a.product_id, a.expiry , SUM( 1 ) AS in_qty, 0 AS out_qty, 0 AS sale_qty, 0 AS sale_amt 
		FROM wp_stmm_wcwh_itemize a 
		WHERE 1 AND a.created_at <= '2021-10-31 23:59:59' 
		GROUP BY a.product_id, a.expiry 
		*/
		public function get_itemize_in( $filters = [], $run = false )
		{
			global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $field.= " a.product_id, a.expiry, DATE_FORMAT( a.created_at, '%Y-%m-%d' ) AS created_at 
				, SUM( 1 ) AS in_qty, 0 AS out_qty, 0 AS sale_qty, 0 AS sale_amt ";
            
            $table = "{$dbname}{$this->tables['itemize']} a ";

            $cond = "";

            if( isset( $filters['product'] ) )
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND a.product_id IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.product_id = %d ", $filters['product'] );
			}
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.created_at >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.created_at <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY a.product_id, a.expiry, DATE_FORMAT( a.created_at, '%Y-%m-%d' ) ";

            $ord = "";

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
		}

		/*
		SELECT a.product_id, a.expiry , 0 AS in_qty, SUM( 1 ) AS out_qty, SUM( 1 ) AS sale_qty, SUM( a.unit_price ) AS sale_amt 
		FROM wp_stmm_wcwh_itemize a 
		LEFT JOIN wp_stmm_woocommerce_order_items oi ON oi.order_item_id = a.sales_item_id 
		LEFT JOIN wp_stmm_posts o ON o.ID = oi.order_id 
		WHERE 1 AND a.status > 0 AND a.stock = 0 AND o.post_status IN( 'wc-processing', 'wc-completed' ) AND o.post_type = 'shop_order'
		AND o.post_date <= '2021-10-31 23:59:59' 
		GROUP BY a.product_id, a.expiry 
		*/
		public function get_itemize_sale( $filters = [], $run = false, $args = [] )
		{
			global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";
			
			//archieve checks
			$arc_dbname = ( $dbname )? str_replace( '.', '', $dbname )."_arc." : $wpdb->dbname."_arc.";
			$sql = "SELECT a.* FROM {$arc_dbname}{$this->tables['selling_price']} a WHERE 1 LIMIT 0,1 ";
			$check = $wpdb->get_row( $sql, ARRAY_A );
			$arc_exist = sizeof( $check )? true : false;

            $field.= " a.product_id, a.expiry, DATE_FORMAT( a.created_at, '%Y-%m-%d' ) AS created_at 
				, 0 AS in_qty, SUM( 1 ) AS out_qty, SUM( 1 ) AS sale_qty, SUM( a.unit_price ) AS sale_amt ";
            
            $table = "{$dbname}{$this->tables['itemize']} a ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} oi ON oi.order_item_id = a.sales_item_id ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['order']} o ON o.ID = oi.order_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['selling_price']} s ON s.sales_item_id = a.sales_item_id AND s.status > 0 ";
			
			if( $arc_exist )
				$table.= "LEFT JOIN {$arc_dbname}{$this->tables['selling_price']} hs ON hs.sales_item_id = a.sales_item_id AND hs.status > 0 AND s.id IS NULL ";

            $cond = $wpdb->prepare( "AND a.status > %d AND a.stock = %d ", 0, 0 );
            //$cond.= "AND o.post_status IN( 'wc-processing', 'wc-completed' ) ";
            //$cond.= $wpdb->prepare( "AND o.post_type = %s ", 'shop_order' );
            //$cond.= $wpdb->prepare( "AND s.status > %d ", 0 );

            if( isset( $filters['product'] ) )
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND a.product_id IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.product_id = %d ", $filters['product'] );
			}
            if( isset( $filters['from_date'] ) )
            {
				if( $arc_exist )
					$cond.= $wpdb->prepare( "AND ( s.sales_date >= %s OR hs.sales_date >= %s ) ", $filters['from_date'], $filters['from_date'] );
				else
					$cond.= $wpdb->prepare( "AND s.sales_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
				if( $arc_exist )
					$cond.= $wpdb->prepare( "AND ( s.sales_date <= %s OR hs.sales_date <= %s ) ", $filters['to_date'], $filters['to_date'] );
				else 
					$cond.= $wpdb->prepare( "AND s.sales_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY a.product_id, a.expiry, DATE_FORMAT( a.created_at, '%Y-%m-%d' ) ";

            $ord = "";

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
		}

		/*
		SELECT a.product_id, a.expiry , 0 AS in_qty, SUM( 1 ) AS out_qty, SUM( 1 ) AS sale_qty, SUM( a.unit_price ) AS sale_amt 
		FROM wp_stmm_wcwh_itemize a 
		WHERE 1 AND a.status <= 0 
		GROUP BY a.product_id, a.expiry 
		*/
		public function get_itemize_other_out( $filters = [], $run = false, $args = [] )
		{
			global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $field.= " a.product_id, a.expiry, DATE_FORMAT( a.created_at, '%Y-%m-%d' ) AS created_at 
				, 0 AS in_qty, SUM( 1 ) AS out_qty, 0 AS sale_qty, 0 AS sale_amt ";
            
            $table = "{$dbname}{$this->tables['itemize']} a ";

            $cond = $wpdb->prepare( "AND a.status <= %d ", 0 );

            if( isset( $filters['product'] ) )
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND a.product_id IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.product_id = %d ", $filters['product'] );
			}
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.lupdate_at >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.lupdate_at <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY a.product_id, a.expiry, DATE_FORMAT( a.created_at, '%Y-%m-%d' ) ";

            $ord = "";

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
		}
	
} //class

}