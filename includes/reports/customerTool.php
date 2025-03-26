<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_CustomerTool_Rpt" ) ) 
{
	
class WCWH_CustomerTool_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "CustomerTool";

	public $tplName = array(
		'export' => 'exportCustomerTool',
		'print' => 'printCustomerTool',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $installment_cutoff = "2023-10-26";

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
			"itemsmeta"		=> $prefix."itemsmeta",
			"item_group"	=> $prefix."item_group",
			"uom"			=> $prefix."uom",

			"customer" 		=> $prefix."customer",
			"tree"			=> $prefix."customer_tree",
			"meta"			=> $prefix."customermeta",

			"customer_group"	=> $prefix."customer_group",
			"customer_job"	=> $prefix."customer_job",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",

			"credit_limit"	=> $prefix."credit_limit",
			"credit_term"	=> $prefix."credit_term",
			"credit_topup"	=> $prefix."credit_topup",
			
			"addresses"		=> $prefix."addresses",
			"company"		=> $prefix."company",
			
			"status"		=> $prefix."status",
			"wp_user"		=> $wpdb->users,
			"wp_usermeta"	=> $wpdb->usermeta,
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
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
						case 'summary':
							$filter = [ 'id'=>$datas['acc_type'] ];
							if( $this->seller ) $filter['seller'] = $this->seller;
							if( !empty( $datas['acc_type'] ) ) $acc_type = apply_filters( 'wcwh_get_account_type', $filter, [], true, [] );

							$datas['filename'] = !empty( $acc_type['code'] )? $acc_type['code'] : 'tool_credit_summary';
						break;
						case 'details':
							//$datas['filename'] = !empty( $acc_type['code'] )? 'credit_detail_'.$acc_type['code'] : 'credit_detail';
							$datas['filename'] = 'tool_credit_detail';
						break;
					}
					
					$datas['dateformat'] = 'Y'.date( 'm', strtotime( $datas['to_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['acc_type'] ) ) $params['acc_type'] = $datas['acc_type'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['cjob'] ) ) $params['cjob'] = $datas['cjob'];
					if( !empty( $datas['cgroup'] ) ) $params['cgroup'] = $datas['cgroup'];
					if( !empty( $datas['item_group'] ) ) $params['item_group'] = $datas['item_group'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					
					//pd($this->export_data_handler( $params ));
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
			case 'summary':
				if( $params['isPrint'] )
					return $this->get_customer_tool_report( $params, $order, [] );
				return $this->get_customer_tool_report( $params, $order, [ 'export'=>1 ] );
			break;
			case 'details':
				if( $params['isPrint'] )
					return $this->get_customer_tool_detail_report( $params, $order, [] );
				return $this->get_customer_tool_detail_report( $params, $order, [ 'export'=>1 ] );
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
		$action_id = 'customer_tool_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
			'setting'	=> $this->refs['setting'],
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		$type = strtolower( $type );
		$args['def_type'] = $type;
		switch( $type )
		{
			case 'summary':
				do_action( 'wcwh_templating', 'report/export-tool-report.php', $this->tplName['export'], $args );
			break;
			case 'details':
				do_action( 'wcwh_templating', 'report/export-tool-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'customer_tool_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
			'setting'	=> $this->refs['setting'],
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		
		$type = strtolower( $type );
		$args['def_type'] = $type;
		switch( $type )
		{
			case 'summary':
				do_action( 'wcwh_templating', 'report/export-tool-report.php', $this->tplName['print'], $args );
			break;
			case 'details':
			default:
				do_action( 'wcwh_templating', 'report/export-tool-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	public function customer_tool_report( $filters = array(), $order = array() )
	{
		$action_id = 'customer_tool_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/customerToolList.php" ); 
			$Inst = new WCWH_CustomerTool_Report();
			$Inst->seller = $this->seller;
			
			$term = apply_filters( 'wcwh_get_credit_term', [ 'name'=>'DEFAULT', 'seller'=>$this->seller ], [], true );
			$day_of_month = ( $term )? $term['days'] : 1;
			$offset = ( $term )? $term['offset'] : 0;
			
			/*$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
			if( $warehouse )
			{
				$day_of_month = get_warehouse_meta( $warehouse['id'], 'day_of_month', true );
			}*/

			$period = apply_filters( 'wcwh_get_credit_period', $day_of_month, $offset, $term['id'], '', $this->seller );
			$date_from = $period['from'];
			$date_to = $period['to'];

			$prev_date = date( 'Y-m-15', strtotime( $date_to." -1 month" ) );
			$prev_period = apply_filters( 'wcwh_get_credit_period', $day_of_month, $offset, $term['id'], $prev_date, $this->seller );
			$date_from = $prev_period['from'];
			$date_to = $prev_period['to'];
			
			if( $this->seller ) $filters['seller'] = $this->seller;
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.creditable, .credit_used, .balance' => [ 'text-align'=>'right !important' ],
				'#creditable a span, #credit_used a span, #balance a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_customer_tool_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function customer_tool_detail_report( $filters = array(), $order = array() )
	{
		$action_id = 'customer_tool_detail_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/customerToolDetailList.php" ); 
			$Inst = new WCWH_CustomerToolDetail_Report();
			$Inst->seller = $this->seller;

			$term = apply_filters( 'wcwh_get_credit_term', [ 'name'=>'DEFAULT', 'seller'=>$this->seller ], [], true );
			$day_of_month = ( $term )? $term['days'] : 1;
			$offset = ( $term )? $term['offset'] : 0;
			
			/*$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
			if( $warehouse )
			{
				$day_of_month = get_warehouse_meta( $warehouse['id'], 'day_of_month', true );
			}*/

			$period = apply_filters( 'wcwh_get_credit_period', $day_of_month, $offset, $term['id'], '', $this->seller );
			$date_from = $period['from'];
			$date_to = $period['to'];

			$prev_date = date( 'Y-m-15', strtotime( $date_to." -1 month" ) );
			$prev_period = apply_filters( 'wcwh_get_credit_period', $day_of_month, $offset, $term['id'], $prev_date, $this->seller );
			$date_from = $prev_period['from'];
			$date_to = $prev_period['to'];
			
			if( $this->seller ) $filters['seller'] = $this->seller;
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.credit_amt, .qty, .price, .line_total, .instalment, .calc_mth, .mth' => [ 'text-align'=>'right !important' ],
				'#credit_amt a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_customer_tool_detail_report( $filters, $order, [] );
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
	public function get_customer_tool_report( $filters = [], $order = [], $args = [] )
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
		}

		$m = 1;
		if( $filters['from_date'] && $filters['to_date'] )
		{
			$start = new DateTime( $filters['from_date'] );
			$end = new DateTime( $filters['to_date'] );
			$diff = $start->diff($end);
			$m = $diff->format('%r%m')+1;
		}
		
		$field = "a.ID AS doc_id, b.meta_value AS customer_id, a.post_date AS purchase_date
			, DATE_ADD( a.post_date, INTERVAL TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['to_date']}') MONTH) AS date
			, @pr := IF( td1.meta_value > 1, td1.meta_value, 1 ) AS period
			, @ed := DATE_ADD( a.post_date, INTERVAL (@pr-1) MONTH ) AS end_date
			, @isE := IF( @ed>='{$filters['from_date']}' AND @ed<='{$filters['to_date']}', 1, 0 ) AS is_end 
			, j.meta_value AS prdt_id
			, m.meta_value AS line_total
			, @fdiff := IF( '{$filters['from_date']}' < a.post_date, 0, TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['from_date']}')+1 ) AS fr_mth
			, @tdiff := TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['to_date']}')+1 AS to_mth
			, @calc := IF( @tdiff > @pr, @pr, @tdiff ) - @fdiff AS calc_mth
			, @crdt := ROUND( IF( @pr-1 > 0 AND a.post_date >= '{$this->installment_cutoff}', m.meta_value / @pr * 1, m.meta_value ), 2 ) AS credit_amt
			, ROUND( IF( @isE, (m.meta_value-(@crdt*(@pr-1))) + @crdt*(@calc-1), @crdt*@calc ), 2 ) AS credit_used ";
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = 'wc_pos_credit_amount' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} tr ON tr.post_id = a.ID AND tr.meta_key = 'tool_request_id' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} o ON o.id = j.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} td ON td.doc_id = tr.meta_value AND td.product_id = o.id AND td.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} td1 ON td1.doc_id = td.doc_id AND td1.item_id = td.item_id AND td1.meta_key = 'period' ";

		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
		$cond.= $wpdb->prepare( "AND b.meta_value > %d AND c.meta_value != %d AND d.meta_value IS NOT NULL ", 0, 0 );
		$cond.= "AND tr.meta_value > 0 ";
		$grp = "";
		$ord = "ORDER BY b.meta_value ASC, a.post_date DESC ";

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( a.post_date >= %s OR 
				DATE_ADD( a.post_date, INTERVAL (IF( td1.meta_value > 1 AND a.post_date >= '{$this->installment_cutoff}', td1.meta_value, 1 )-1) MONTH ) >= %s ) ", $filters['from_date'], $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( a.post_date <= %s OR 
				DATE_ADD( a.post_date, INTERVAL (IF( td1.meta_value > 1 AND a.post_date >= '{$this->installment_cutoff}', td1.meta_value, 1 )-1) MONTH ) <= %s ) ", $filters['to_date'], $filters['to_date'] );
		}
		if( isset( $filters['item_group'] ) )
		{
			if( is_array( $filters['item_group'] ) )
				$cond.= "AND o.grp_id IN ('" .implode( "','", $filters['item_group'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $filters['item_group'] );
		}
		else
		{
			$filters['item_group'] = $def = $this->refs['setting']['wh_tool_rpt']['def_item_group'];
			$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $def );
		}

		$sql1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//------------------
		$field = "h.doc_id, th1.meta_value AS customer_id, h.post_date AS purchase_date
			, DATE_ADD( h.post_date, INTERVAL TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['to_date']}') MONTH) AS date
			, @pr := IF( td1.meta_value > 1, td1.meta_value, 1 ) AS period
			, @ed := DATE_ADD( h.post_date, INTERVAL (@pr-1) MONTH ) AS end_date
			, @isE := IF( @ed>='{$filters['from_date']}' AND @ed<='{$filters['to_date']}', 1, 0 ) AS is_end 
			, d.product_id AS prdt_id
			, d1.meta_value AS line_total
			, @fdiff := IF( '{$filters['from_date']}' < h.post_date, 0, TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['from_date']}')+1 ) AS fr_mth
			, @tdiff := TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['to_date']}')+1 AS to_mth
			, @calc := IF( @tdiff > @pr, @pr, @tdiff ) - @fdiff AS calc_mth 
			, @crdt := ROUND( IF( @pr-1 > 0 AND h.post_date >= '{$this->installment_cutoff}', d1.meta_value / @pr * 1, d1.meta_value ), 2 ) AS credit_amt
			, ROUND( IF( @isE, (d1.meta_value-(@crdt*(@pr-1))) + @crdt*(@calc-1), @crdt*@calc ), 2 ) AS credit_used ";
		$table = "{$dbname}{$this->tables['document']} h 
			LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'ref_doc_type'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h2 ON h2.doc_id = h.doc_id AND h2.item_id = 0 AND h2.meta_key = 'ref_doc_id'
			LEFT JOIN {$dbname}{$this->tables['document']} th ON th.doc_id = h2.meta_value
			LEFT JOIN {$dbname}{$this->tables['document_meta']} th1 ON th1.doc_id = th.doc_id AND th1.item_id = 0 AND th1.meta_key = 'customer_id'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} d1 ON d1.doc_id = d.doc_id AND d1.item_id = d.item_id AND d1.meta_key = 'line_total'
			LEFT JOIN {$dbname}{$this->tables['items']} o ON o.id = d.product_id
			LEFT JOIN {$dbname}{$this->tables['document_items']} td ON td.doc_id = th.doc_id AND td.product_id = o.id AND td.status > 0 
			LEFT JOIN {$dbname}{$this->tables['document_meta']} td1 ON td1.doc_id = td.doc_id AND td1.item_id = td.item_id AND td1.meta_key = 'period'
		";
		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND h1.meta_value = %s ", 'sale_order', 6, 'tool_request' );
		$cond.= $wpdb->prepare( "AND th.doc_type = %s AND th.status >= %d ", 'tool_request', 6 );
		$grp = "";
		$ord = "ORDER BY th1.meta_value ASC, h.post_date DESC ";

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( h.post_date >= %s OR 
				DATE_ADD( h.post_date, INTERVAL (IF( td1.meta_value > 1 AND h.post_date >= '{$this->installment_cutoff}', td1.meta_value, 1 )-1) MONTH ) >= %s ) ", $filters['from_date'], $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( h.post_date <= %s OR 
				DATE_ADD( h.post_date, INTERVAL (IF( td1.meta_value > 1 AND h.post_date >= '{$this->installment_cutoff}', td1.meta_value, 1 )-1) MONTH ) <= %s ) ", $filters['to_date'], $filters['to_date'] );
		}
		if( isset( $filters['item_group'] ) )
		{
			$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $filters['item_group'] );
			if( is_array( $filters['item_group'] ) )
				$cond.= "AND o.grp_id IN ('" .implode( "','", $filters['item_group'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $filters['item_group'] );
		}
		else
		{
			$filters['item_group'] = $def = $this->refs['setting']['wh_tool_rpt']['def_item_group'];
			$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $def );
		}

		$sql2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		$sql = "( {$sql1} ) UNION ( {$sql2} ) ";

		$field = "a.customer_id, a.date, a.purchase_date, ROUND( SUM( a.credit_used ), 2 ) AS credit_used ";
		$sql = "SELECT {$field} FROM ( {$sql} ) a WHERE 1 GROUP BY a.doc_id ORDER BY a.customer_id ASC, a.date ASC ";

		//------------------
		$wage_fld = "tool_wage";
		$def_wage = "2215";
		if( ! empty( $filters['item_group'] ) )
		{
			if( $filters['item_group'] == $this->refs['setting']['wh_tool_rpt']['tool_wage'] )
			{
				$wage_fld = "tool_wage"; $def_wage = "2215";
			}
			else if( $filters['item_group'] == $this->refs['setting']['wh_tool_rpt']['eq_wage'] )
			{
				$wage_fld = "eq_wage"; $def_wage = "2216";
			}
		}
		
		$field = "z.customer_id, b.id, b.wh_code, b.name, b.uid, b.code, b.serial, c.code AS acc_code, MAX( z.date ) AS date
			, MAX( z.purchase_date ) AS lpurchase_at ";
		$field.= ", IF( LENGTH( c.{$wage_fld} ) > 0, c.{$wage_fld}, {$def_wage} ) AS wage_type ";
		$field.= ", SUM( z.credit_used ) AS credit_used ";

		$table = "( {$sql} ) z ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} b ON b.id = z.customer_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} c ON c.id = b.acc_type ";

		if( $args['export'] )//2215 tool oil palm, 2216 equipment oil palm
		{
			$field = "'0015' AS infotype, SUBSTRING( b.uid, -6 ) AS employee_id, b.name AS customer_name ";
			$field.= ", IF( LENGTH( c.{$wage_fld} ) > 0, c.{$wage_fld}, {$def_wage} ) AS wage_type ";
			$field.= ", SUM( z.credit_used ) AS amount, 'MYR' AS currency, DATE_FORMAT( MAX( z.date ),'%d.%m.%Y' ) AS Date ";

			$subsql = "SELECT cl.id
				FROM {$dbname}{$this->tables['credit_limit']} cl 
				WHERE 1 AND ( ( cl.scheme = 'customer' AND cl.ref_id = b.id ) OR ( cl.scheme = 'customer_group' AND cl.ref_id = b.cgroup_id ) ) 
				AND cl.status > 0 AND cl.flag > 0 
				ORDER BY cl.scheme_lvl DESC LIMIT 0,1";
			$table.= "LEFT JOIN {$dbname}{$this->tables['credit_limit']} cdl ON cdl.id = ( {$subsql} ) ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['credit_term']} cdt ON cdt.id = cdl.term_id ";
		}

		$cond = "";
		$cond.= "AND ( c.employee_prefix IS NOT NULL AND c.employee_prefix != '' ) ";
		//$cond.= $wpdb->prepare( "AND b.uid IS NOT NULL AND CHAR_LENGTH( b.uid ) > %d ", 3 );
		
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND b.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND b.acc_type = %s ", $filters['acc_type'] );
		}
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND b.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND b.id = %s ", $filters['customer'] );
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
                $cd[] = "b.name LIKE '%".$kw."%' ";
				$cd[] = "b.code LIKE '%".$kw."%' ";
				$cd[] = "b.serial LIKE '%".$kw."%' ";
				$cd[] = "b.uid LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "GROUP BY z.customer_id ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'b.uid' => 'ASC', 'b.code' => 'ASC' ];
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
	
	public function get_customer_tool_detail_report( $filters = [], $order = [], $args = [] )
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
		}

		$m = 1;
		if( $filters['from_date'] && $filters['to_date'] )
		{
			$start = new DateTime( $filters['from_date'] );
			$end = new DateTime( $filters['to_date'] );
			$diff = $start->diff($end);
			$m = $diff->format('%r%m')+1;
		}

		/*
SELECT @sd:='2023-11-06' AS sale_date, @fd:='2023-11-26' AS from_date, @td:='2023-12-25' AS to_date
, @f_dif := TIMESTAMPDIFF(MONTH, @sd, @fd) AS from_diff
, @t_dif := TIMESTAMPDIFF(MONTH, @sd, @td) AS to_diff
, DATE_ADD( @sd, INTERVAL @f_dif MONTH) AS from_mth
, DATE_ADD( @sd, INTERVAL @t_dif MONTH) AS to_mth
		*/
		
		$field = "a.ID AS order_id, g.meta_value AS receipt_no, th.docno AS request_doc, a.post_date AS purchase_date
			, DATE_ADD( a.post_date, INTERVAL TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['to_date']}') MONTH) AS doc_date
			, h.name AS customer, h.serial AS customer_code, h.uid AS employee_id ";
		$field.= ", ig.name AS item_group, i.order_item_name AS item_name, IFNULL( p.meta_value, o._uom_code ) AS uom
			, k.meta_value AS qty, l.meta_value AS price, m.meta_value AS line_total
			, @pr := IF( td1.meta_value > 1, td1.meta_value, 1 ) AS instalment
			, @ed := DATE_ADD( a.post_date, INTERVAL (@pr-1) MONTH ) AS end_date
			, @isE := IF( @ed>='{$filters['from_date']}' AND @ed<='{$filters['to_date']}', 1, 0 ) AS is_end 
			, @fdiff := IF( '{$filters['from_date']}' < a.post_date, 0, TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['from_date']}')+1 ) AS fr_mth
			, @tdiff := TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['to_date']}')+1 AS to_mth
			, @calc := IF( @tdiff > @pr, @pr, @tdiff ) - @fdiff AS calc_mth
			, @crdt := ROUND( IF( @pr -1 > 0 AND a.post_date >= '{$this->installment_cutoff}', m.meta_value / @pr * 1, m.meta_value ), 2 ) AS per_mth_amt
			, ROUND( IF( @isE, (m.meta_value-(@crdt*(@pr-1))) + @crdt*(@calc-1), @crdt*@calc ), 2 ) AS credit_amt ";

		if( $args['export'] )
		{
			$field = "g.meta_value AS receipt_no, th.docno AS request_doc, a.post_date AS purchase_date
				, DATE_ADD( a.post_date, INTERVAL TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['to_date']}') MONTH) AS doc_date
				, h.name AS customer, h.serial AS customer_code, h.uid AS employee_id
				, ig.name AS item_group, i.order_item_name AS item_name, IFNULL( p.meta_value, o._uom_code ) AS uom
				, k.meta_value AS qty, l.meta_value AS price, m.meta_value AS line_total
				, @pr := IF( td1.meta_value > 1, td1.meta_value, 1 ) AS instalment
				, @ed := DATE_ADD( a.post_date, INTERVAL (@pr-1) MONTH ) AS end_date
				, @isE := IF( @ed>='{$filters['from_date']}' AND @ed<='{$filters['to_date']}', 1, 0 ) AS is_end 
				, @fdiff := IF( '{$filters['from_date']}' < a.post_date, 0, TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['from_date']}')+1 ) AS fr_mth
				, @tdiff := TIMESTAMPDIFF(MONTH, a.post_date, '{$filters['to_date']}')+1 AS to_mth
				, @calc := IF( @tdiff > @pr, @pr, @tdiff ) - @fdiff AS calc_mth
				, @crdt := ROUND( IF( @pr -1 > 0 AND a.post_date >= '{$this->installment_cutoff}', m.meta_value / @pr * 1, m.meta_value ), 2 ) AS per_mth_amt
				, ROUND( IF( @isE, (m.meta_value-(@crdt*(@pr-1))) + @crdt*(@calc-1), @crdt*@calc ), 2 ) AS credit_amt ";
		}
		
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} ba ON ba.post_id = a.ID AND ba.meta_key = '_customer_serial' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = 'wc_pos_credit_amount' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = 'wc_pos_amount_pay' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_amount_change' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = '_order_number' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} tr ON tr.post_id = a.ID AND tr.meta_key = 'tool_request_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} th ON th.doc_id = tr.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} h ON h.id = b.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";;
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} p ON p.order_item_id = i.order_item_id AND p.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} o ON o.id = j.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_group']} ig ON ig.id = o.grp_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} at ON at.id = h.acc_type ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} td ON td.doc_id = tr.meta_value AND td.product_id = j.meta_value AND td.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} td1 ON td1.doc_id = td.doc_id AND td1.item_id = td.item_id AND td1.meta_key = 'period' ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
		$cond.= $wpdb->prepare( "AND b.meta_value > %d AND d.meta_value != %d AND c.meta_value IS NOT NULL ", 0, 0 );
		$cond.= "AND ( at.employee_prefix IS NOT NULL AND at.employee_prefix != '' ) ";
		$cond.= "AND tr.meta_value > 0 ";
		//if( ! current_user_cans( ['wh_support'] ) )
			//$cond.= $wpdb->prepare( "AND h.uid IS NOT NULL AND CHAR_LENGTH( h.uid ) > %d ", 3 );

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( a.post_date >= %s OR 
				DATE_ADD( a.post_date, INTERVAL (IF( td1.meta_value > 1 AND a.post_date >= '{$this->installment_cutoff}', td1.meta_value, 1 )-1) MONTH ) >= %s ) ", $filters['from_date'], $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( a.post_date <= %s OR 
				DATE_ADD( a.post_date, INTERVAL (IF( td1.meta_value > 1 AND a.post_date >= '{$this->installment_cutoff}', td1.meta_value, 1 )-1) MONTH ) <= %s ) ", $filters['to_date'], $filters['to_date'] );
		}
		if( isset( $filters['item_group'] ) )
		{
			if( is_array( $filters['item_group'] ) )
				$cond.= "AND o.grp_id IN ('" .implode( "','", $filters['item_group'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $filters['item_group'] );
		}
		else
		{
			$def = $this->refs['setting']['wh_tool_rpt']['def_item_group'];
			$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $def );
		}
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND h.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.acc_type = %s ", $filters['acc_type'] );
		}
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND h.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.id = %s ", $filters['customer'] );
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
                $cd[] = "g.meta_value LIKE '%".$kw."%' ";
				$cd[] = "h.name LIKE '%".$kw."%' ";
				$cd[] = "h.code LIKE '%".$kw."%' ";
				$cd[] = "h.serial LIKE '%".$kw."%' ";
				$cd[] = "h.uid LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		//order
		$def_order = $order;
		if( empty( $order ) )
		{
			$order = [ 'h.code' => 'ASC', 'a.post_date' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//-----------------------------------
		$field = "h.doc_id AS order_id, h.docno AS receipt_no, th.docno AS request_doc, h.post_date AS purchase_date
			, DATE_ADD( h.post_date, INTERVAL TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['to_date']}') MONTH) AS doc_date
			, c.name AS customer, c.serial AS customer_code, c.uid AS employee_id ";
		$field.= ", ig.name AS item_group, o.name AS item_name, o._uom_code AS uom
			, d.bqty AS qty, d2.meta_value AS price, d1.meta_value AS line_total
			, @pr := IF( td1.meta_value > 1, td1.meta_value, 1 ) AS instalment
			, @ed := DATE_ADD( h.post_date, INTERVAL (@pr-1) MONTH ) AS end_date
			, @isE := IF( @ed>='{$filters['from_date']}' AND @ed<='{$filters['to_date']}', 1, 0 ) AS is_end 
			, @fdiff := IF( '{$filters['from_date']}' < h.post_date, 0, TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['from_date']}')+1 ) AS fr_mth
			, @tdiff := TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['to_date']}')+1 AS to_mth
			, @calc := IF( @tdiff > @pr, @pr, @tdiff ) - @fdiff AS calc_mth
			, @crdt := ROUND( IF( @pr -1 > 0 AND h.post_date >= '{$this->installment_cutoff}', d1.meta_value / @pr * 1, d1.meta_value ), 2 ) AS per_mth_amt
			, ROUND( IF( @isE, (d1.meta_value-(@crdt*(@pr-1))) + @crdt*(@calc-1), @crdt*@calc ), 2 ) AS credit_amt ";

		if( $args['export'] )
		{
			$field = "h.docno AS receipt_no, th.docno AS request_doc, h.post_date AS purchase_date
				, DATE_ADD( h.post_date, INTERVAL TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['to_date']}') MONTH) AS doc_date
				, c.name AS customer, c.serial AS customer_code, c.uid AS employee_id
				, ig.name AS item_group, o.name AS item_name, o._uom_code AS uom
				, d.bqty AS qty, d2.meta_value AS price, d1.meta_value AS line_total
				, @pr := IF( td1.meta_value > 1, td1.meta_value, 1 ) AS instalment
				, @ed := DATE_ADD( h.post_date, INTERVAL (@pr-1) MONTH ) AS end_date
				, @isE := IF( @ed>='{$filters['from_date']}' AND @ed<='{$filters['to_date']}', 1, 0 ) AS is_end 
				, @fdiff := IF( '{$filters['from_date']}' < h.post_date, 0, TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['from_date']}')+1 ) AS fr_mth
				, @tdiff := TIMESTAMPDIFF(MONTH, h.post_date, '{$filters['to_date']}')+1 AS to_mth
				, @calc := IF( @tdiff > @pr, @pr, @tdiff ) - @fdiff AS calc_mth
				, @crdt := ROUND( IF( @pr -1 > 0 AND h.post_date >= '{$this->installment_cutoff}', d1.meta_value / @pr * 1, d1.meta_value ), 2 ) AS per_mth_amt
				, ROUND( IF( @isE, (d1.meta_value-(@crdt*(@pr-1))) + @crdt*(@calc-1), @crdt*@calc ), 2 ) AS credit_amt ";
		}

		$table = "{$dbname}{$this->tables['document']} h 
			LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'ref_doc_type'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h2 ON h2.doc_id = h.doc_id AND h2.item_id = 0 AND h2.meta_key = 'ref_doc_id'
			LEFT JOIN {$dbname}{$this->tables['document']} th ON th.doc_id = h2.meta_value
			LEFT JOIN {$dbname}{$this->tables['document_meta']} th1 ON th1.doc_id = th.doc_id AND th1.item_id = 0 AND th1.meta_key = 'customer_id'
			LEFT JOIN {$dbname}{$this->tables['document_items']} td ON td.doc_id = th.doc_id AND td.item_id = d.ref_item_id AND d.status > 0 
			LEFT JOIN {$dbname}{$this->tables['document_meta']} td1 ON td1.doc_id = td.doc_id AND td1.item_id = td.item_id AND td1.meta_key = 'period'
			LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = th1.meta_value
			LEFT JOIN {$dbname}{$this->tables['document_meta']} d1 ON d1.doc_id = d.doc_id AND d1.item_id = d.item_id AND d1.meta_key = 'line_total'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} d2 ON d2.doc_id = d.doc_id AND d2.item_id = d.item_id AND d2.meta_key = 'sprice'
			LEFT JOIN {$dbname}{$this->tables['items']} o ON o.id = d.product_id
			LEFT JOIN {$dbname}{$this->tables['item_group']} ig ON ig.id = o.grp_id
			LEFT JOIN {$dbname}{$this->tables['acc_type']} at ON at.id = c.acc_type
		";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND h1.meta_value = %s ", 'sale_order', 6, 'tool_request' );
		$cond.= $wpdb->prepare( "AND th.doc_type = %s AND th.status >= %d ", 'tool_request', 6 );
		$cond.= "AND ( at.employee_prefix IS NOT NULL AND at.employee_prefix != '' ) ";

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( h.post_date >= %s OR 
				DATE_ADD( h.post_date, INTERVAL (IF( td1.meta_value > 1 AND h.post_date >= '{$this->installment_cutoff}', td1.meta_value, 1 )-1) MONTH ) >= %s ) ", $filters['from_date'], $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( h.post_date <= %s OR 
				DATE_ADD( h.post_date, INTERVAL (IF( td1.meta_value > 1 AND h.post_date >= '{$this->installment_cutoff}', td1.meta_value, 1 )-1) MONTH ) <= %s ) ", $filters['to_date'], $filters['to_date'] );
		}
		if( isset( $filters['item_group'] ) )
		{
			if( is_array( $filters['item_group'] ) )
				$cond.= "AND o.grp_id IN ('" .implode( "','", $filters['item_group'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $filters['item_group'] );
		}
		else
		{
			$def = $this->refs['setting']['wh_tool_rpt']['def_item_group'];
			$cond.= $wpdb->prepare( "AND o.grp_id = %s ", $def );
		}
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND c.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.acc_type = %s ", $filters['acc_type'] );
		}
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND c.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.id = %s ", $filters['customer'] );
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
                $cd[] = "h.docno LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";
				$cd[] = "c.serial LIKE '%".$kw."%' ";
				$cd[] = "c.uid LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		//order
		$order = $def_order;
		if( empty( $order ) )
		{
			$order = [ 'c.code' => 'ASC', 'h.post_date' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//-----------------------------------

		$sql = "( {$sql1} ) UNION ( {$sql2} ) ";

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
} //class

}