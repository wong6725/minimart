<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_CustomerPurchases_Rpt" ) ) 
{
	
class WCWH_CustomerPurchases_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "CustomerPurchases_Rpt";

	public $tplName = array(
		'export' => 'exportCustomerPurchases',
		'print' => 'printCustomerPurchases',
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
			"customer" 		=> $prefix."customer",
			"tree"			=> $prefix."customer_tree",
			"meta"			=> $prefix."customermeta",
			"customer_group"	=> $prefix."customer_group",
			"customer_job"	=> $prefix."customer_job",
			"addresses"		=> $prefix."addresses",
			"company"		=> $prefix."company",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",
			"status"		=> $prefix."status",
			"wp_user"		=> $wpdb->users,
			"wp_usermeta"	=> $wpdb->usermeta,
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",
			"items"			=> $prefix."items",

			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",

			"credit_limit"	=> $prefix."credit_limit",
			"credit_term"	=> $prefix."credit_term",
			"credit_topup"	=> $prefix."credit_topup",
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
						case 'purchase':
						default:
							$datas['filename'] = 'customer_purchases';
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
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					
					//pd($this->export_data_handler( $params ));
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['acc_type'] ) ) $params['acc_type'] = $datas['acc_type'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['cjob'] ) ) $params['cjob'] = $datas['cjob'];
					if( !empty( $datas['cgroup'] ) ) $params['cgroup'] = $datas['cgroup'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					$params['isPrint'] = 1;
					//pd( $params );
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
			case 'purchase':
				return $this->get_customer_purchase_report( $params, $order, [] );
			break;
			case 'summary':
			default:
				return $this->get_customer_purchase_summary( $params, $order, [] );
			break;
		}
	}

	public function print_handler( $params = array(), $opts = array() )
	{
		$datas = $this->export_data_handler( $params );

		$type = $params['export_type'];
		unset( $params['export_type'] );
		$date_format = get_option( 'date_format' );
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();

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

	public function export_form( $type = 'purchase' )
	{
		$action_id = 'customer_purchases_report';
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
			case 'purchase':
				do_action( 'wcwh_templating', 'report/export-customer_purchase-report.php', $this->tplName['export'], $args );
			break;
			case 'summary':
			default:
				do_action( 'wcwh_templating', 'report/export-customer_purchase_summary-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'customer_purchases_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		
		$type = strtolower( $type );
		$args['def_type'] = $type;
		/*switch( $type )
		{
			case 'purchase':
			default:
				do_action( 'wcwh_templating', 'report/export-credit-report.php', $this->tplName['print'], $args );
			break;
		}*/
	}

	public function customer_purchases_summary( $filters = array(), $order = array() )
	{
		$action_id = 'customer_purchases_summary';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/customerPurchaseSummaryList.php" ); 
			$Inst = new WCWH_CustomerPurchaseSummary_Report();
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
				'.credit_amt, .total_creditable' => [ 'text-align'=>'right !important' ],
				'#credit_amt a span, #total_creditable a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_customer_purchase_summary( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function customer_purchases_report( $filters = array(), $order = array() )
	{
		$action_id = 'customer_purchases_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/customerPurchaseList.php" ); 
			$Inst = new WCWH_CustomerPurchase_Report();
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
				'.total_used, .qty, .item_total' => [ 'text-align'=>'right !important' ],
				'#total_used a span, #item_total a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_customer_purchase_report( $filters, $order, [] );
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
	public function get_customer_purchase_summary( $filters = [], $order = [], $args = [] )
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
		
		//, cj.name AS position, cg.name AS credit_group
		$field = "c.id, c.name AS customer_name, c.uid AS employee_id, c.code AS customer_no , at.code AS acc_type
			, j.name AS position, g.name AS credit_group
			, cdl.credit_limit + IFNULL( SUM( cdt.credit_limit ), 0 ) AS total_creditable
			, c.credit_used AS credit_amt ";

		//--------------------------------------------------
			$fld = "c.id, c.name, c.uid, c.code, c.cgroup_id, c.acc_type, c.cjob_id, c.status, SUM( c.credit_used ) AS credit_used ";
			$union = [];
			$union[] = "( SELECT c.id, c.name, c.uid, c.code, c.cgroup_id, c.acc_type, c.cjob_id, c.status, 0 AS credit_used 
				FROM {$dbname}{$this->tables['customer']} c
				WHERE 1 AND c.status > 0 ) ";

			$cd = "";
			if( isset( $filters['from_date'] ) )
			{
				$cd.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cd.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
			}
			$union[] = "( SELECT f.id, f.name, f.uid, f.code, f.cgroup_id, f.acc_type, f.cjob_id, f.status
				, round( SUM( c.meta_value ), 2 ) AS credit_used 
				FROM {$dbname}{$wpdb->posts} a 
				LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' 
				LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = 'wc_pos_credit_amount' 
				LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = '_order_total' 
				LEFT JOIN {$dbname}{$this->tables['customer']} f ON f.id = b.meta_value
				WHERE 1 AND a.post_type = 'shop_order' AND a.post_status IN ( 'wc-processing', 'wc-completed' ) 
				AND b.meta_value > 0 AND c.meta_value > 0 AND d.meta_value IS NOT NULL {$cd} 
				GROUP BY f.id ) ";
			$subsql = "SELECT {$fld} FROM ( ".implode( " UNION ALL ", $union )." ) c GROUP BY c.id ";
		//--------------------------------------------------

		$table = "( {$subsql} ) c ";

			$subsql = "SELECT cl.id
				FROM {$dbname}{$this->tables['credit_limit']} cl 
				WHERE 1 AND ( 
					( cl.scheme = 'customer' AND cl.ref_id = c.id ) OR ( cl.scheme = 'customer_group' AND cl.ref_id = c.cgroup_id ) 
				) 
				AND cl.status > 0 AND cl.flag > 0 
				ORDER BY cl.scheme_lvl DESC LIMIT 0,1";
		$table.= "LEFT JOIN {$dbname}{$this->tables['credit_limit']} cdl ON cdl.id = ( {$subsql} ) ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} at ON at.id = c.acc_type ";

			$subsql = "SELECT t.*
				, @today:= DATE_FORMAT( IF( t.apply_date IS NOT NULL AND t.apply_date != '', CONCAT( t.apply_date, '-15' ), NOW() ), '%Y-%m-%d' ) AS today 
				, CASE 
				WHEN DAY(@today) >= t.days
					THEN CONCAT( DATE_FORMAT( @today, '%Y-%m-' ), t.days ) + INTERVAL t.offset DAY 
				WHEN DAY(@today) < t.days
					THEN CONCAT( DATE_FORMAT( @today - INTERVAL 1 MONTH, '%Y-%m-' ), t.days ) + INTERVAL t.offset DAY 
				END AS from_date 
				, CASE 
				WHEN DAY(@today) >= t.days
					THEN CONCAT( DATE_FORMAT( @today + INTERVAL 1 MONTH, '%Y-%m-' ), t.days ) - INTERVAL 1 DAY 
				WHEN DAY(@today) < t.days
					THEN CONCAT( DATE_FORMAT( @today, '%Y-%m-' ), t.days ) - INTERVAL 1 DAY 
				END AS to_date 
				FROM {$dbname}{$this->tables['credit_term']} t 
				WHERE 1 ";
		$table.= "LEFT JOIN ( {$subsql} ) t ON t.id = IF( at.term_id > 0, at.term_id, cdl.term_id ) ";
		//$table.= "LEFT JOIN ( {$subsql} ) t ON t.id = cdl.term_id ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['credit_topup']} cdt ON cdt.customer_id = c.id AND cdt.effective_from >= t.from_date AND cdt.effective_from <= t.to_date AND cdt.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} g ON g.id = c.cgroup_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_job']} j ON j.id = c.cjob_id  ";

		$cond = "AND ( at.employee_prefix IS NOT NULL AND at.employee_prefix != '' ) ";
		
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND c.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.acc_type = %s ", $filters['acc_type'] );
		}
		if( isset( $filters['cjob'] ) )
		{
			if( is_array( $filters['cjob'] ) )
				$cond.= "AND c.cjob_id IN ('" .implode( "','", $filters['cjob'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.cjob_id = %s ", $filters['cjob'] );
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
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";
				$cd[] = "c.serial LIKE '%".$kw."%' ";
				$cd[] = "c.uid LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY c.id ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'c.code' => 'ASC', 'c.credit_used' => 'DESC' ];
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

	public function get_customer_purchase_report( $filters = [], $order = [], $args = [] )
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
		
		//, cj.name AS position, cg.name AS credit_group
		$field = "c.name AS customer_name, c.code AS customer_no, c.serial AS customer_code, c.uid AS employee_id 
			, acc.code AS acc_type 
			, cat.slug AS category_code, cat.name AS category_name 
			, p.code AS item_code, p.name AS item_name 
			, SUM( imb.meta_value ) AS qty, p._uom_code AS uom, ROUND( SUM( imc.meta_value ), 2 ) AS item_total, t.total_amt AS total_used ";
		
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} ma ON ma.post_id = a.ID AND ma.meta_key = 'customer_id' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} mb ON mb.post_id = a.ID AND mb.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} mc ON mc.post_id = a.ID AND mc.meta_key = 'wc_pos_credit_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = ma.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_job']} cj ON cj.id = c.cjob_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} cg ON cg.id = c.cgroup_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} acc ON acc.id = c.acc_type ";

		$sub = $this->get_customer_purchase_total( $filters );
		$table.= "LEFT JOIN ( {$sub} ) t ON t.customer_id = c.id ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} ima ON ima.order_item_id = i.order_item_id AND ima.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} imb ON imb.order_item_id = i.order_item_id AND imb.meta_key = '_qty' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} imc ON imc.order_item_id = i.order_item_id AND imc.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} p ON p.id = ima.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = p.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
		$cond.= $wpdb->prepare( "AND ma.meta_value > %d AND mc.meta_value > %d ", 0, 0 );
		$cond.= "AND ( acc.employee_prefix IS NOT NULL AND acc.employee_prefix != '' ) ";

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND c.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.acc_type = %s ", $filters['acc_type'] );
		}
		if( isset( $filters['cjob'] ) )
		{
			if( is_array( $filters['cjob'] ) )
				$cond.= "AND c.cjob_id IN ('" .implode( "','", $filters['cjob'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.cjob_id = %s ", $filters['cjob'] );
		}
		if( isset( $filters['cgroup'] ) )
		{
			if( is_array( $filters['cjob'] ) )
				$cond.= "AND c.cgroup_id IN ('" .implode( "','", $filters['cgroup'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.cgroup_id = %s ", $filters['cgroup'] );
		}
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND c.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.id = %s ", $filters['customer'] );
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
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";
				$cd[] = "c.serial LIKE '%".$kw."%' ";
				$cd[] = "c.uid LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY c.id, p.id ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'total_amt' => 'DESC', 'c.code' => 'ASC', 'item_total' => 'DESC', 'p.code' => 'ASC' ];
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

		public function get_customer_purchase_total( $filters = [], $run = false, $args = [] )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			if( isset( $filters['seller'] ) )
			{
				$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
				$dbname = ( $dbname )? $dbname."." : "";
			}
			
			$field = "c.id AS customer_id, ROUND( SUM( mc.meta_value ), 2 ) AS total_amt ";
			
			$table = "{$dbname}{$wpdb->posts} a ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} ma ON ma.post_id = a.ID AND ma.meta_key = 'customer_id' ";
			//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} mb ON mb.post_id = a.ID AND mb.meta_key = '_order_total' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} mc ON mc.post_id = a.ID AND mc.meta_key = 'wc_pos_credit_amount' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = ma.meta_value ";
			
			$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
			$cond.= $wpdb->prepare( "AND ma.meta_value > %d AND mc.meta_value > %d ", 0, 0 );

			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
			}
			if( isset( $filters['acc_type'] ) )
			{
				if( is_array( $filters['acc_type'] ) )
					$cond.= "AND c.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND c.acc_type = %s ", $filters['acc_type'] );
			}
			if( isset( $filters['cjob'] ) )
			{
				if( is_array( $filters['cjob'] ) )
					$cond.= "AND c.cjob_id IN ('" .implode( "','", $filters['cjob'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND c.cjob_id = %s ", $filters['cjob'] );
			}
			if( isset( $filters['cgroup'] ) )
			{
				if( is_array( $filters['cjob'] ) )
					$cond.= "AND c.cgroup_id IN ('" .implode( "','", $filters['cgroup'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND c.cgroup_id = %s ", $filters['cgroup'] );
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
					$cd[] = "c.name LIKE '%".$kw."%' ";
					$cd[] = "c.code LIKE '%".$kw."%' ";
					$cd[] = "c.serial LIKE '%".$kw."%' ";
					$cd[] = "c.uid LIKE '%".$kw."%' ";

	                $seg[] = "( ".implode( "OR ", $cd ).") ";
	            }
	            $cond.= implode( "OR ", $seg );

	            $cond.= ") ";
			}

			$grp = "GROUP BY c.id ";
			
	        $ord = "";

			$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
		}
	
} //class

}