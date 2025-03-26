<?php
//Steven written
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TransactionLog_Controller" ) ) 
{
	
class WCWH_TransactionLog_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_transaction_log_rpt";

	protected $primary_key = "id";

	public $Notices;
	public $className = "TransactionLog_Controller";

	public $tplName = array(
		'export' => 'exportTransactionLog',
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
			"document"		=> $prefix."document",
			"document_items"=> $prefix."document_items",
			"document_meta"	=> $prefix."document_meta",

			"transaction"		=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_out_ref"		=> $prefix."transaction_out_ref",

			"items"			=> $prefix."items",
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",

			"client"		=> $prefix."client",
			"warehouse"		=> $prefix."warehouse",
			"warehousemeta" => $prefix."warehousemeta",
			
			"status"		=> $prefix."status",
		);
	}

	public function set_warehouse( $warehouse = array() )
	{
		$this->warehouse = $warehouse;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		$wpdb = $this->db_wpdb;
		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	// $isSave = false;
        	$result = array();
        	// $user_id = get_current_user_id();
			// $now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":
					$data = $datas['filter'];
					$datas['filename'] = 'Transaction Log';
	
					$params = [];
					if( !empty( $data['category'] ) ) $params['category'] = $data['category'];
					if( !empty( $data['product'] ) ) $params['product'] = $data['product'];

					
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

		//$default_column['title'] = [];

		//$default_column['default'] = [];

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		return $this->get_infos( $params, [], false, [], [], [] );
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
					data-title="<?php echo $actions['export'] ?>Transaction Log" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>Transaction Log"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}


	// public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false )
	// {
	// 	$args = array(
	// 		'setting'	=> $this->setting,
	// 		'section'	=> $this->section_id,
	// 		'hook'		=> $this->section_id.'_form',
	// 		'action'	=> 'save',
	// 		'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
	// 		'new'		=> 'new',
	// 		'tplName'	=> $this->tplName['new'],
	// 		'rowTpl'	=> $this->tplName['row'],
	// 		'get_content' => $getContent,
	// 		'wh_code'	=> $this->warehouse['code'],
	// 	);

	// 	if( $id )
	// 	{
	// 		$datas =  $this->get_infos([ 'id' => $id ], [], false, [], [], []);
	// 		if( $datas )
	// 		{	
	// 			$args['action'] = 'update';
	// 			if( $isView ) $args['view'] = true;

	// 			// $Inst = new WCWH_Listing();

	// 			$args['data'] = $datas[0];
				
	// 			unset( $args['new'] );
	// 		}
	// 	}
	// 	if( $templating )
	// 	{
	// 		do_action( 'wcwh_templating', 'form/posCredit-form.php', $this->tplName['new'], $args );
	// 	}
	// 	else
	// 	{
	// 		do_action( 'wcwh_get_template', 'form/posCredit-form.php', $args );
	// 	}
	// }

	public function export_form()
	{
		$action_id = 'transaction_log_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		do_action( 'wcwh_templating', 'export/export-transaction_log.php', $this->tplName['export'], $args );
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
			include_once( WCWH_DIR."/includes/listing/transactionLogList.php" ); 
			$Inst = new WCWH_TransactionLog_Listing();
			// $Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->seller = $this->seller;
			
			// $date_from = current_time( 'Y-m-1' );
			// $date_to = current_time( 'Y-m-t' );

			if( $this->seller ) $filters['seller'] = $this->seller;
			// $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
			$filters['warehouse_id'] = $this->warehouse;
			// $wh['code'];
			
			// $filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			// $filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			// $filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			// $filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch_onoff();

			// $count = $this->count_statuses( $filters );
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$Inst->per_page_limit = 5000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			// $limit = $Inst->get_data_limit();
			// $Inst->per_page_limit = 1000;
			// $Inst->set_args( [ 'off_footer'=>true ] );

			$datas = $this->get_infos( $filters, $order, false, [], [], [] );
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

		$field = "t.docno AS in_doc, c.docno AS out_doc, d.name, d._uom_code, e.name AS category, a.* ";
		$table = "{$dbname}{$this->tables['transaction']} t ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} a ON a.hid = t.hid AND a.status > 0  AND a.flag = 0 AND a.plus_sign = '+' AND a.deduct_qty > 0 AND a.deduct_qty < a.bqty ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_out_ref']} b ON b.ref_hid = a.hid AND b.ref_did = a.did ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} c ON c.hid = b.hid ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} d ON d.id = a.product_id ";
		$table.= "LEFT JOIN {$dbname}wp_stmm_terms e ON d.category = e.term_id ";

		$cond.= $wpdb->prepare( "AND t.status > %d ", 0 );
		$cond.= "AND a.hid IS NOT NULL ";

	   //  if( isset( $filters['from_date'] ) )
	   //  {
	   // 	 $cond.= $wpdb->prepare( "AND a.time >= %s ", $filters['from_date'] );
	   //  }
	   //  if( isset( $filters['to_date'] ) )
	   //  {
	   // 	 $cond.= $wpdb->prepare( "AND a.time <= %s ", $filters['to_date'] );
	   //  }

	   if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		
	    if( isset( $filters['product'] ) )
	    {
	   	 if( is_array( $filters['product'] ) )
	   		 $cond.= "AND d.name IN ('" .implode( "','", $filters['product'] ). "') ";
	   	 else
	   		 $cond.= $wpdb->prepare( "AND d.name = %s ", $filters['product'] );
	    }

		if( isset( $filters['category'] ) )
	    {
	   	 if( is_array( $filters['category'] ) )
	   		 $cond.= "AND e.name IN ('" .implode( "','", $filters['category'] ). "') ";
	   	 else
	   		 $cond.= $wpdb->prepare( "AND e.name = %s ", $filters['category'] );
	    }

	   //  if( isset( $filters['id'] ) )
	   //  {
	   // 	 $cond.= $wpdb->prepare( "AND a.order_id = %d ", $filters['id'] );
	   //  }

	   //  //status
	   //  if( isset( $filters['status'] ) )
	   //  {   
	   // 	 $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
	   //  }

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
				
	   		 $cd[] = "t.docno LIKE '%".$kw."%' ";
	   		 $cd[] = "c.docno LIKE '%".$kw."%' ";
	   		 $cd[] = "d.name LIKE '%".$kw."%' ";
			 $cd[] = "d._uom_code LIKE '%".$kw."%' ";
	   	
	   		 $seg[] = "( ".implode( "OR ", $cd ).") ";
	   	 }
	   	 $cond.= implode( "OR ", $seg );

	   	 $cond.= ") ";
	    }

		// group
	    // $group = [ 'in_doc' ];
	    // if( ! empty( $group ) )
	    // {
	   	//  $grp.= "GROUP BY ".implode( ", ", $group )." ";
	    // }
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'in_doc' => 'DESC', 'out_doc' => 'ASC', 'a.item_id' => 'ASC' ];
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