<?php
//Steven written
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PosPrice_Controller" ) ) 
{
	
class WCWH_PosPrice_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_pos_price";

	protected $primary_key = "id";

	public $Notices;
	public $className = "POSPrice_Controller";

	public $tplName = array(
		'export' => 'exportPosPrice',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_db_tables();
	}

	public function get_section_id()
	{
		return $this->section_id;
	}
	
	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"items"			=> $prefix."items",
			"category"		=> $wpdb->prefix."terms",
			"item_group"	=> $prefix."item_group",
			"uom"			=> $prefix."uom",
			
			"customer" 		=> $prefix."customer",
			"customer_group"	=> $prefix."customer_group",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",
			
			"status"		=> $prefix."status",
			
			"wp_user"		=> $wpdb->users,
			"wp_usermeta"	=> $wpdb->usermeta,
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"selling_price"	=> $prefix."selling_price",
			"storage"	=> $prefix."storage",
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
					$datas['dateformat'] = 'YmdHis';
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					
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
		return $this->get_infos( $params );
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
					data-title="<?php echo $actions['export'] ?>Pos Price Log" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>Pos Price Log"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form()
	{
		$action_id = 'pos_price_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		do_action( 'wcwh_templating', 'export/export-pos_price-report.php', $this->tplName['export'], $args );
	}

	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_listing( $filters = array(), $order = array() )
	{	
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/posPriceList.php" ); 
			$Inst = new WCWH_POSPrice_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->styles = [
				'.transactions, .amt_paid, .amt_change, .amt_cash, .amt_credit, .total' => [ 'text-align'=>'right !important' ],
				'#transactions a span, #amt_cash a span, #amt_credit a span, #total a span' => [ 'float'=>'right' ],
			];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->get_infos( $filters, $order, false, [], [], $limit );
			$datas = ( $datas )? $datas : array();
			
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
	public function get_infos( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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

		$field = "c.meta_value AS receipt, a.sales_item_id AS item_id, a.warehouse_id, d.code AS storage_code, d.name AS storage_name, e.code AS customer_code, e.uid AS employee_id, e.name AS customer_name, a.sales_date, f.code AS item_code, f.name AS item_name, g.name AS category_name, g.slug AS category_code, a.uom, a.qty, a.unit, a.uprice, a.price, a.total_amount, a.status ";
		$table = "{$dbname}{$this->tables['selling_price']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} o ON o.order_item_id = a.sales_item_id ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->posts} b ON b.ID = o.order_id ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = b.ID AND c.meta_key = '_order_number' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['storage']} d ON d.id = a.strg_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} e ON e.id = a.customer ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} f ON f.id = a.prdt_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} g ON g.term_id = f.category ";
		
		$cond = $wpdb->prepare( "AND b.post_type = %s ", 'shop_order' );
		
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.sales_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.sales_date <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND f.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND f.id = %d ", $filters['product'] );
		}
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND e.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND e.id = %d ", $filters['customer'] );
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
				$cd[] = "c.meta_value LIKE '%".$kw."%' ";
				$cd[] = "e.name LIKE '%".$kw."%' ";
				$cd[] = "f.name LIKE '%".$kw."%' ";
				$cd[] = "f.code LIKE '%".$kw."%' ";
				$cd[] = "g.name LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'receipt' => 'DESC', 'item_id' => 'DESC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

        //limit offset
        if( !empty( $limit ) )
        {
        	$l.= "LIMIT ".implode( ", ", $limit )." ";
        }

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}
	
} //class

}