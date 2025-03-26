<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TradeIn_Rpt" ) ) 
{
	
class WCWH_TradeIn_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "TradeIn";

	public $tplName = array(
		'export' => 'exportTradeIn',
		'print' => 'printTradeIn',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $limit_type = 'once_per_person';

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
			"items"			=> $prefix."items",
			"item_group"	=> $prefix."item_group",
			"uom"			=> $prefix."uom",

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

			"promo"			=> $prefix."promo_header",
			"promo_detail"	=> $prefix."promo_detail",
			"promometa"		=> $prefix."promo_headermeta",
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
						default:
							$datas['filename'] = 'Trade In ';
						break;
					}
					
					//$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					//if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					//if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['promotion'] ) ) $params['promotion'] = $datas['promotion'];
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
			default:
				return $this->get_trade_in_report( $params );
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

	public function export_form( $type = 'details' )
	{
		$action_id = 'trade_in_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
			'limit_type'=> $this->limit_type,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			default:
				do_action( 'wcwh_templating', 'report/export-trade_in-report.php', $this->tplName['export'], $args );
			break;
		}
	}
	
	/**
	 *	Trade In Details
	 */
	public function trade_in_report( $filters = array(), $order = array() )
	{
		$action_id = 'trade_in_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/tradeInList.php" ); 
			$Inst = new WCWH_TradeIn_Report_List();
			$Inst->seller = $this->seller;
			$Inst->limit_type = $this->limit_type;
			
			$date_from = current_time( 'Y-m-1' );
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
				'.qty, .price, .line_total' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_trade_in_report( $filters, $order, [] );
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
	public function get_trade_in_report( $filters = [], $order = [], $args = [] )
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

		$sub_cd = $wpdb->prepare( "AND a.status > 0 AND ma.meta_value = %s ", $this->limit_type );
		$sub_cd.= "AND cd.type = 'condition' AND cd.match = 'item' ";
		if( isset( $filters['promotion'] ) )
		{
			$filters['promotion'] = is_array( $filters['promotion'] )? $filters['promotion'] : [ $filters['promotion'] ];

			$sub_cd.= "AND a.id IN ('" .implode( "','", $filters['promotion'] ). "') ";
		}
		$subsql = "SELECT a.id, a.docno, a.from_date, a.to_date, ma.meta_value AS limit_type
			, cd.product_id, cd.amount
			FROM {$dbname}{$this->tables['promo']} a 
			LEFT JOIN {$dbname}{$this->tables['promometa']} ma ON ma.promo_header_id = a.id AND ma.meta_key = 'limit_type' 
			LEFT JOIN {$dbname}{$this->tables['promo_detail']} cd ON cd.promo_id = a.id AND cd.status > 0 
			WHERE 1 {$sub_cd}  ";
		//$results = $wpdb->get_results( $subsql , ARRAY_A );
		//rt($results);
		
		$field = "a.ID AS order_id, i.order_item_id, g.meta_value AS order_no, a.post_date AS date ";
		$field.= ", h.name AS customer_name, h.uid AS employee_id, h.serial AS customer_code ";
		$field.= ", p.name AS item_name, p._sku AS sku, p.code AS item_code, p.serial ";
		$field.= ", IFNULL( n.meta_value, p._uom_code ) AS uom, s.name AS category, s.slug AS category_code ";
		$field.= ", k.meta_value AS qty ";
		$field.= ", ROUND( l.meta_value, 2 ) AS price, ROUND( m.meta_value, 2 ) AS line_total ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = '_order_number' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} pr ON pr.post_id = a.ID AND pr.meta_key LIKE 'wc_pos_promotion_%' ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} h ON h.id = b.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} n ON n.order_item_id = i.order_item_id AND n.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} p ON p.id = j.meta_value ";

		$table.= "LEFT JOIN ( {$subsql} ) pm ON pm.product_id = p.id ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} s ON s.term_id = p.category ";
		
		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = s.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond.= $wpdb->prepare( "AND a.post_type = %s AND c.meta_value IS NOT NULL ", 'shop_order' );
		$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) AND pm.product_id > 0 ";
		
		if( isset( $filters['promotion'] ) )
		{
			$filters['promotion'] = is_array( $filters['promotion'] )? $filters['promotion'] : [ $filters['promotion'] ];
			$cd = [];
			foreach( $filters['promotion'] as $promo_id )
			{
				$cd[] = "wc_pos_promotion_{$promo_id}";
			}

			$cond.= "AND pr.meta_key IN ('" .implode( "','", $cd ). "') ";
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
				$cond.= $wpdb->prepare( "AND ( b.meta_value IS NULL OR b.meta_value = %s ) ", '0' );
			else
				$cond.= $wpdb->prepare( "AND h.id = %s ", $filters['customer'] );
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND p.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND p.id = %d ", $filters['product'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR s.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR s.term_id = %d ", $filters['category'] );
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
                $cd[] = "g.meta_value LIKE '%".$kw."%' ";
				$cd[] = "h.name LIKE '%".$kw."%' ";
				$cd[] = "h.uid LIKE '%".$kw."%' ";
				$cd[] = "h.code LIKE '%".$kw."%' ";
				$cd[] = "h.serial LIKE '%".$kw."%' ";
				$cd[] = "s.name LIKE '%".$kw."%' ";
				$cd[] = "s.slug LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY a.ID, i.order_item_id ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.post_date' => 'ASC' ];
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