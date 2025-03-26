<?php
//Steven written
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PosCredit_Controller" ) ) 
{
	
class WCWH_PosCredit_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_pos_credit";

	protected $primary_key = "id";

	public $Notices;
	public $className = "POSCredit_Controller";

	public $tplName = array(
		'export' => 'exportPosCredit',
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
			"pos_credit"	=> "wp_stmm_wc_poin_of_sale_credit_registry",
            "posts"         => "wp_stmm_posts",
			"customer" 		=> $prefix."customer",
			// "status"		=> $prefix."status",
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
		$wpdb = $this->db_wpdb;
		$outcome = array();
		// if($this->seller){
		// 	$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
		// 	$dbname = ( $dbname )? $dbname."." : "";
		// }

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
				case "update":
					$header = $datas['header'];
					if( $header['amount'] <= $header['total'] )
					{
						$succ = true;
						$header['total'] = round_to( $header['total'], 2 );
						$header['amount'] = round_to( $header['amount'], 2 );
						$header['paid_amount'] = $header['total'] -  $header['amount'];
						$header['amount'] = -$header['amount'];
						unset($header['total']);
					}
					else
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "Credit Amount should not larger than Total Amount!", "error", $this->className."|action_handler|".$action );
					}
					if( $succ ) 
					{
						// $id = array();$header['id'];
						$credit_datas = array(
							'order_id' => $header['id'],
							'amount' => $header['amount']
						);

						$paid_datas = array(
							'post_id' => $header['id'],
							'meta_key' => 'wc_pos_amount_pay',
							'meta_value' => $header['paid_amount']
						);

						$result = $wpdb->update( "wp_stmm_wc_poin_of_sale_credit_registry", $credit_datas, array('order_id' => $header['id']) );
						$result = $wpdb->update( "wp_stmm_postmeta", $paid_datas, array('post_id' => $header['id'], 'meta_key' => 'wc_pos_amount_pay') );
						// $result = $wpdb->update( "wp_stmm_postmeta", $paid_datas, array('post_id' => $header['id']) );
						if ( false === $result )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
						}
					}
				break;
				case "delete":
					$id = $datas['id'];
					$succ = true;
					
					$credit_datas = array(
						'order_id' => $id,
						'status' => 0
					);

					$result = $wpdb->update( "wp_stmm_wc_poin_of_sale_credit_registry", $credit_datas, array('order_id' => $id) );

					if ( false === $result )
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
					}
				break;
				case "restore":
					$id = $datas['id'];
					$succ = true;
					
					$credit_datas = array(
						'order_id' => $id,
						'status' => 1
					);

					$result = $wpdb->update("wp_stmm_wc_poin_of_sale_credit_registry", $credit_datas, array('order_id' => $id) );

					if ( false === $result )
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
					}
				break;
				case "export":
					$datas['filename'] = 'POS Credit';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];

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
					data-title="<?php echo $actions['export'] ?>Pos Credit Log" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>Pos Credit Log"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}


	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
			'get_content' => $getContent,
			'wh_code'	=> $this->warehouse['code'],
		);

		if( $id )
		{
			$datas =  $this->get_infos([ 'id' => $id, 'seller' => $this->seller ], [], false, [], [], []);
			if( $datas )
			{	
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				// $Inst = new WCWH_Listing();

				$args['data'] = $datas[0];
				
				unset( $args['new'] );
			}
		}
		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/posCredit-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/posCredit-form.php', $args );
		}
	}

	public function export_form()
	{
		$action_id = 'pos_credit_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);
		if( $this->filters ) $args['filters'] = $this->filters;
		$args['filters']['seller'] = $this->seller;
		do_action( 'wcwh_templating', 'export/export-pos_credit-report.php', $this->tplName['export'], $args );
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
			include_once( WCWH_DIR."/includes/listing/posCreditListing.php" ); 
			$Inst = new WCWH_POSCredit_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->seller = $this->seller;
			
			// $date_from = current_time( 'Y-m-1' );
			// $date_to = current_time( 'Y-m-t' );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			// $filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			// $filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			// $filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			// $filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch_onoff();

			$count = $this->count_statuses( $filters );
			if( $count ) $Inst->viewStats = $count;

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

		$field = "a.order_id as id , b.post_excerpt AS receipt, c.name AS customer, c.uid, ABS(a.amount) AS amount, a.time, a.status AS credit_status, e.meta_value AS paid_amount, d.meta_value AS total ";
		$table = "{$dbname}{$this->tables['pos_credit']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$dbname}{$wpdb->posts} b ON b.id = a.order_id AND b.post_type = 'shop_order' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = a.user_id ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.order_id AND d.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.order_id AND e.meta_key = 'wc_pos_amount_pay' ";
		
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.time >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.time <= %s ", $filters['to_date'] );
		}
		
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND c.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.id = %d ", $filters['customer'] );
		}

		if( isset( $filters['id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.order_id = %d ", $filters['id'] );
		}

		//status
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
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
				
				$cd[] = "a.order_id LIKE '%".$kw."%' ";
				$cd[] = "b.post_excerpt LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.uid LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		//group
		$group = [ 'a.order_id' ];
		if( ! empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'order_id' => 'DESC' ];
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

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		return $results;
	}



	public function count_statuses( $filters = [] )
	{
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

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$fld = "'all' AS status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$this->tables['pos_credit']} ";
		$cond = $wpdb->prepare( "AND status != %d AND type = %s ", -1, "sales" );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$this->tables['pos_credit']} ";
		$cond = $wpdb->prepare( "AND status != %d AND type = %s ", -1, "sales" );
		$group = "GROUP BY status ";
		$sql2 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$sql = $sql1." UNION ALL ".$sql2;

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$outcome = array();
		if( $results )
		{
			foreach( $results as $i => $row )
			{
				$outcome[ (string)$row['status'] ] = $row['count'];
			}
		}
		return $outcome;
	}
	
} //class

}