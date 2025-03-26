<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ReceiptCount_Rpt" ) ) 
{
	
class WCWH_ReceiptCount_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "ReceiptCount";

	public $tplName = array(
		'export' => 'exportReceiptCount',
		'print' => 'printReceiptCount',
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

			"count"			=> $prefix."customer_count",
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
						case 'summary':
						default:
							$datas['filename'] = 'Receipt Count ';
						break;
					}
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['acc_type'] ) ) $params['acc_type'] = $datas['acc_type'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['list_type'] ) ) $params['list_type'] = $datas['list_type'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['acc_type'] ) ) $params['acc_type'] = $datas['acc_type'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['list_type'] ) ) $params['list_type'] = $datas['list_type'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
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
			case 'summary':
			default:
				return $this->get_receipt_count_report( $params, $order, [] );
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
			case 'summary':
				$filename = "Receipts Count";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );

				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Receipts Count';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Receipts Count';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Employee ID' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Name' => [ 'width'=>'30%', 'class'=>['leftered'] ],
					'Customer QR' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Account Type' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'No. of Receipt' => [ 'width'=>'10%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$totals = 0;
					$details = [];
					foreach( $datas as $i => $data )
					{

						$row = [

'employee_id' => [ 'value'=>$data['employee_id'], 'class'=>['leftered'] ],
'name' => [ 'value'=>$data['name'], 'class'=>['leftered'] ],
'customer_qr' => [ 'value'=>$data['customer_qr'], 'class'=>['leftered'] ],
'acc_type' => [ 'value'=>$data['acc_type'], 'class'=>['leftered'] ],
'transaction' => [ 'value'=>$data['transaction'], 'class'=>['rightered'] ],

						];

						$details[] = $row;
					}

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
					data-title="<?php echo $actions['export'] ?> Receipt Count" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Receipt Count"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
			case 'print':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['print'] ?>" 
					data-title="<?php echo $actions['print'] ?> Receipt Count" data-modal="wcwhModalImEx" 
					data-actions="close|printing" 
					title="<?php echo $actions['print'] ?> Receipt Count"
				>
					<i class="fa fa-print" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = 'summary' )
	{
		$action_id = 'receipt_count_report';
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
			case 'summary':
			default:
				do_action( 'wcwh_templating', 'report/export-receipt-count-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'receipt_count_report';
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
			case 'summary':
			default:
				do_action( 'wcwh_templating', 'report/export-receipt-count-report.php', $this->tplName['print'], $args );
			break;
		}
	}


	public function receipt_count_report( $filters = array(), $order = array() )
	{
		$action_id = 'receipt_count_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/receiptCountList.php" ); 
			$Inst = new WCWH_ReceiptCount_Report();
			$Inst->seller = $this->seller;
			
			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.transaction' => [ 'text-align'=>'right !important' ],
				'#transaction a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_receipt_count_report( $filters, $order, [] );
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
	 SELECT a.serial AS customer_qr, b.uid AS employee_id, b.name AS customer_name, c.code AS acc_type, c.employee_prefix
	, a.transaction, a.purchase, a.credit
	FROM wp_stmm_wcwh_customer_count a
	LEFT JOIN wp_stmm_wcwh_customer b ON b.id = a.cust_id 
	LEFT JOIN wp_stmm_wcwh_customer_acc_type c ON c.id = b.acc_type
	WHERE 1 AND a.status != 0
	 */
	public function get_receipt_count_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "a.serial AS customer_qr, b.uid AS employee_id, b.name AS name ";
		$field.= ", c.code AS acc_type, c.employee_prefix AS acc_id, a.transaction";
		
		$table = "{$dbname}{$this->tables['count']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} b ON b.id = a.cust_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} c ON c.id = b.acc_type ";

		$cond = $wpdb->prepare( "AND a.status > %d ", 0 );
		$cond.= $wpdb->prepare( "AND b.uid IS NOT NULL AND CHAR_LENGTH( b.uid ) > %d ", 3 );
		$cond.= "AND ( c.employee_prefix IS NOT NULL AND c.employee_prefix != '' ) ";

		//$filters['list_type'] = isset( $filters['list_type'] )? $filters['list_type'] : 'latest';
		switch( $filters['list_type'] )
		{
			case 'all':

			break;
			case 'history':
				$cond.= "AND b.serial != a.serial ";
			break;
			case 'latest':
			default:
				$cond.= "AND b.serial = a.serial ";
			break;
		}
		
		if( isset( $filters['acc_type'] ) )
		{
			$cond.= $wpdb->prepare( "AND c.id = %s ", $filters['acc_type'] );
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
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.serial' => 'ASC' ];
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