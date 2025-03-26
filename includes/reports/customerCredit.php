<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_CustomerCredit_Rpt" ) ) 
{
	
class WCWH_CustomerCredit_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "CustomerCredit";

	public $tplName = array(
		'export' => 'exportCustomerCredit',
		'print' => 'printCustomerCredit',
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

	public function __destruct()
	{
		unset($this->Notices);
		unset($this->tables);
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
			"items"			=> $prefix."items",

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

							$datas['filename'] = !empty( $acc_type['code'] )? $acc_type['code'] : 'credit_summary';
						break;
						case 'credit_limit':
							$datas['filename'] = 'credit_limit';
						break;
						case 'details':
							//$datas['filename'] = !empty( $acc_type['code'] )? 'credit_detail_'.$acc_type['code'] : 'credit_detail';
							$datas['filename'] = 'credit_detail';
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
			case 'summary':
				if( $params['isPrint'] )
					return $this->get_customer_credit_report( $params, $order, [] );
				return $this->get_customer_credit_report( $params, $order, [ 'export'=>1 ] );
			break;
			case 'credit_limit':
				return $this->get_customer_credit_limit_report( $params, $order );
			break;
			case 'details':
				if( $params['isPrint'] )
					return $this->get_customer_credit_detail_report( $params, $order, [] );
				return $this->get_customer_credit_detail_report( $params, $order, [ 'export'=>1 ] );
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
		
		switch( $type )
		{
			case 'summary':
				$filename = "Customer Credit Summary";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Customer Credit Summary';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'CC Summary';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Employee ID' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Customer Name' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Customer No.' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Acc Type' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Last Date' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Amount' => [ 'width'=>'10%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$total = 0;
					$details = [];
					foreach( $datas as $i => $data )
					{
						$row = [

'employee_id' => [ 'value'=>$data['uid'], 'class'=>['leftered'] ],
'customer_name' => [ 'value'=>$data['name'], 'class'=>['leftered'] ],
'customer_no' => [ 'value'=>$data['code'], 'class'=>['leftered'] ],
'acc_type' => [ 'value'=>$data['acc_code'], 'class'=>['leftered'] ],
'date' => [ 'value'=>$data['lpurchase_at'], 'class'=>['leftered'] ],
'amount' => [ 'value'=>$data['credit_used'], 'class'=>['rightered'], 'num' => 1 ],

						];

						$details[] = $row;

						$total+= $data['credit_used'];
					}

					$details[] = [
						'employee_id' => [ 'value'=>'TOTAL ('.$currency.'):', 'class'=>['leftered','bold'], 'colspan'=>5 ],
						'amount' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num' => 1 ],
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
			case 'details':
				$filename = "Credit Customer Details";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Credit Customer Details';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'CC Details';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
				
				$document['detail_title'] = [
					'Employee ID' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Customer' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Receipt' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Credit' => [ 'width'=>'6%', 'class'=>['rightered'] ],
					'Item' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'5%', 'class'=>['rightered'] ],
					'UOM' => [ 'width'=>'5%', 'class'=>['centered'] ],
					'Price' => [ 'width'=>'8%', 'class'=>['rightered'] ],
					'Amount' => [ 'width'=>'8%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					$credit_totals = [];
					foreach( $datas as $i => $data )
					{
						$customer = [];
						if( $data['receipt_cus_serial'] ) $customer[] = $data['receipt_cus_serial'];
						if( $data['customer'] ) $customer[] = $data['customer'];		

						$data['customer_info'] = implode( ' - ', $customer );
						$regrouped[ $data['receipt_cus_serial'] ][ $data['receipt_no'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['receipt_cus_serial'] ] += 1;
						$rowspan[ $data['receipt_no'] ] += 1;

						//totals
						$totals[ $data['receipt_cus_serial'] ][ $data['receipt_no'] ] += $data['line_total'];
						$credit_totals[ $data['receipt_cus_serial'] ][ $data['receipt_no'] ] = $data['credit_amount'];
					}
					
					$details = [];
					if( $regrouped )
					{
						$total = 0; $credit_total = 0;
						foreach( $regrouped as $customer => $receipts )
						{
							$subtotal = 0; $credit_subtotal = 0;
							$customer_added = '';
							foreach( $receipts as $receipt => $items )
							{
								$receipt_added = '';
								if( $totals[ $customer ][ $receipt ] )
								{
									$rowspan[ $receipt ] += 1;
									$rowspan[ $customer ] += count( $totals[ $customer ] ) + 1;
								}

								foreach( $items as $i => $vals )
								{
									$row = [

'employee_id' => [ 'value'=>$vals['employee_id'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $customer ] ],
'customer_info' => [ 'value'=>$vals['customer_info'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $customer ] ],
'receipt_no' => [ 'value'=>$vals['receipt_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $receipt ] ],
'date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $receipt ] ],
'credit_amount' => [ 'value'=>$vals['credit_amount'], 'class'=>['rightered'], 'num' => 1, 'rowspan' => $rowspan[ $receipt ] ],
'item_name' => [ 'value'=>$vals['item_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num' => 1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'price' => [ 'value'=>$vals['price'], 'class'=>['rightered'], 'num' => 1 ],
'line_total' => [ 'value'=>$vals['line_total'], 'class'=>['rightered'], 'num' => 1 ],

									];

									if( $customer_added == $customer )
									{
										$row['customer_info'] = [];
										$row['employee_id'] = [];
									} 
									$customer_added = $customer;

									if( $receipt_added == $receipt ) 
									{
										$row['receipt_no'] = [];
										$row['date'] = [];
										$row['credit_amount'] = [];
									}
									$receipt_added = $receipt;

									$details[] = $row;
								}

								$details[] = [
									'employee_id' => [],
									'customer_info' => [],
									'receipt_no' => [],
									'date' => [],
									'credit_amount' => [],
									'item_name' => [ 'value'=>'Receipt Total:', 'class'=>['leftered','bold'], 'colspan'=>4 ],
									'line_total' => [ 'value'=>$totals[ $customer ][ $receipt ], 'class'=>['rightered','bold'], 'num' => 1 ],
								];
								$subtotal += $totals[ $customer ][ $receipt ];
								$credit_subtotal += $credit_totals[ $customer ][ $receipt ];
							}

							$details[] = [
								'employee_id' => [],
								'customer_info' => [],
								'receipt_no' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>2 ],
								'credit_amount' => [ 'value'=>$credit_subtotal, 'class'=>['rightered','bold'], 'num' => 1 ],
								'item_name' => [ 'colspan'=>4 ],
								'line_total' => [ 'value'=>$subtotal, 'class'=>['rightered','bold'], 'num' => 1 ],
							];

							$total+= $subtotal;
							$credit_total+= $credit_subtotal;
						}

						$details[] = [
							'employee_id' => [ 'value'=>'TOTAL ('.$currency.'):', 'class'=>['leftered','bold'], 'colspan'=>4 ],
							'credit_amount' => [ 'value'=>$credit_total, 'class'=>['rightered','bold'], 'num' => 1 ],
							'item_name' => [ 'colspan'=>4 ],
							'line_total' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num' => 1 ],
						];
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
		$action_id = 'customer_credit_report';
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
				do_action( 'wcwh_templating', 'report/export-credit-report.php', $this->tplName['export'], $args );
			break;
			case 'details':
				do_action( 'wcwh_templating', 'report/export-credit-report.php', $this->tplName['export'], $args );
			break;
			case 'credit_limit':
				do_action( 'wcwh_templating', 'report/export-credit_limit-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'customer_credit_report';
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
		switch( $type )
		{
			case 'summary':
				do_action( 'wcwh_templating', 'report/export-credit-report.php', $this->tplName['print'], $args );
			break;
			case 'details':
			default:
				do_action( 'wcwh_templating', 'report/export-credit-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	public function customer_credit_report( $filters = array(), $order = array() )
	{
		$action_id = 'customer_credit_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/customerCreditList.php" ); 
			$Inst = new WCWH_CustomerCredit_Report();
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
				$datas = $this->get_customer_credit_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
		public function customer_credit_report_detail( $id = 0, $filters = array() )
		{
			$args = array();

			if( $id )
			{
				if( $this->seller ) $filters['seller'] = $this->seller;
				$filters = array_merge( [ 'id'=>$id ], $filters );
				if( $filters['from_date'] )
					$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
				if( $filters['to_date'] )
				$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );
				
				$datas = $this->get_customer_credit_report_detail( $filters );
				if( $datas )
				{
					$header_column = array ( 'order_id', 'receipt_no', 'date', 'customer', 'customer_code', 'customer_serial', 'employee_id', 'comp_code', 'comp_name', 
						'cgroup_name', 'cgroup_code', 'order_total', 'credit_amount', 'cash_paid', 'cash_change' );
					$detail_column = array ( 'item_no', 'item_name', 'sku', 'serial', 'uom', 'qty', 'price', 'line_total' );
					$result_data = $this->seperate_import_data( $datas , $header_column , [ 'order_id' ] , $detail_column );
					
					do_action( 'wcwh_get_template', 'report/credit_report_detail.php', $result_data );
				}
			}
		}

	public function customer_credit_detail_report( $filters = array(), $order = array() )
	{
		$action_id = 'customer_credit_detail_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/customerCreditDetailList.php" ); 
			$Inst = new WCWH_CustomerCreditDetail_Report();
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
				'.order_total, .credit_amount, .cash, .item_no, .qty, .price, .line_total' => [ 'text-align'=>'right !important' ],
				'#order_total a span, #credit_amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_customer_credit_detail_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function customer_credit_acc_type_report( $filters = array(), $order = array() )
	{
		$action_id = 'customer_credit_acc_type_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/customerCreditAccTypeList.php" ); 
			$Inst = new WCWH_CustomerCreditAccType_Report();
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
				'.customer_count, .order_count, .credit_used' => [ 'text-align'=>'right !important' ],
				'#customer_count a span, #order_count a span, #credit_used a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_customer_credit_acc_type_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function customer_credit_limit_report( $filters = array(), $order = array() )
	{
		$action_id = 'customer_credit_limit_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/customerCreditLimitList.php" ); 
			$Inst = new WCWH_CustomerCreditLimit_Report();
			$Inst->seller = $this->seller;
			
			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.credit_limit, .topup, .total_creditable, .total_used, .balance' => [ 'text-align'=>'right !important' ],
				'#credit_limit a span, #topup a span, #total_creditable a span, #total_used a span, #balance a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_customer_credit_limit_report( $filters, $order, [] );
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
	public function get_customer_credit_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "b.meta_value AS customer_id, a.post_date AS date, round( c.meta_value, 2 ) AS credit_used, round( e.meta_value, 2 ) AS creditable ";
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = 'wc_pos_credit_amount' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = '_total_creditable' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} tr ON tr.post_id = a.ID AND tr.meta_key = 'tool_request_id' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} mem ON mem.post_id = a.ID AND mem.meta_key = 'membership_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} db ON db.post_id = a.ID AND db.meta_key = '_debit_deduction' ";
		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
		$cond.= $wpdb->prepare( "AND b.meta_value > %d AND c.meta_value != %d AND d.meta_value IS NOT NULL ", 0, 0 );
		$cond.= "AND ( tr.meta_value IS NULL OR tr.meta_value = '' ) ";
		$cond.= "AND ( db.meta_value IS NULL OR db.meta_value = '' ) ";
		$ord = "ORDER BY b.meta_value ASC, a.post_date DESC ";

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
		}
		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//------------------
		$field = "z.customer_id, b.id, b.wh_code, b.name, b.uid, b.code, b.serial, c.code AS acc_code, MAX( z.date ) AS lpurchase_at ";
		$field.= ", SUM( z.credit_used ) AS credit_used, MAX( z.creditable ) AS creditable";
		$field.= ", ROUND( MAX( z.creditable ) - SUM( z.credit_used ), 2 ) AS balance ";

		$table = "( {$sql} ) z ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} b ON b.id = z.customer_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} c ON c.id = b.acc_type ";

		if( $args['export'] )//2209 timber. 2212 oil palm
		{
			$field = "'0015' AS infotype, SUBSTRING( b.uid, -6 ) AS employee_id, b.name AS customer_name ";
			$field.= ", IF( LENGTH( c.wage_type ) > 0, c.wage_type, IF( LENGTH( cdt.wage_type ) > 0, cdt.wage_type, '2209' ) ) AS wage_type ";
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
		public function get_customer_credit_report_detail( $filters = [], $order = [], $args = [] )
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
			
			$field = "a.id AS order_id, g.meta_value AS receipt_no, a.post_date AS date, h.name AS customer, h.code AS customer_code, h.uid AS employee_id ";
			$field.= ", r.meta_value AS customer_serial, comp.code AS comp_code, comp.name AS comp_name ";
			$field.= ", cgroup.name AS cgroup_name, cgroup.code AS cgroup_code ";
			$field.= ", c.meta_value AS order_total, d.meta_value AS credit_amount, e.meta_value AS cash_paid, f.meta_value AS cash_change ";
			$field.= ", q.meta_value AS item_no, i.order_item_name AS item_name, p._sku AS sku, p.serial ";
			$field.= ", IFNULL( n.meta_value, p._uom_code ) AS uom, k.meta_value AS qty, l.meta_value AS price, m.meta_value AS line_total ";
			$table = "{$dbname}{$wpdb->posts} a ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_order_total' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = 'wc_pos_credit_amount' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = 'wc_pos_amount_pay' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_amount_change' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = '_order_number' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} h ON h.id = b.meta_value ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_price' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} n ON n.order_item_id = i.order_item_id AND n.meta_key = '_uom' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} q ON q.order_item_id = i.order_item_id AND q.meta_key = '_item_number' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} p ON p.id = j.meta_value ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} r ON r.post_id = a.ID AND r.meta_key = '_customer_serial' ";
			
			$table.= "LEFT JOIN {$dbname}{$this->tables['company']} comp ON comp.id = h.comp_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} cgroup ON cgroup.id = h.cgroup_id ";
			
			$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
			$cond.= $wpdb->prepare( "AND b.meta_value > %d AND d.meta_value != %d AND c.meta_value IS NOT NULL ", 0, 0 );
			$ord = "ORDER BY h.code ASC, a.post_date ASC ";
			
			if( isset( $filters['id'] ) )
			{
				$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $filters['id'] );
			}
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
			}
			
			$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
			$results = $wpdb->get_results( $sql , ARRAY_A );
			
			return $results;
		}
	
	public function get_customer_credit_detail_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "a.ID AS order_id, g.meta_value AS receipt_no, a.post_date AS date, h.name AS customer, h.serial AS customer_code, h.uid AS employee_id, ba.meta_value AS receipt_cus_serial  ";
		$field.= ", c.meta_value AS order_total, d.meta_value AS credit_amount, e.meta_value AS cash_paid, f.meta_value AS cash_change, e.meta_value - f.meta_value AS cash ";
		$field.= ", n.meta_value AS item_no, i.order_item_name AS item_name, IFNULL( p.meta_value, o._uom_code ) AS uom, k.meta_value AS qty, l.meta_value AS price, m.meta_value AS line_total ";

		if( $args['export'] )
		{
			$field = "g.meta_value AS receipt_no, a.post_date AS date, h.name AS customer, h.serial AS customer_code, h.uid AS employee_id ";
			$field.= ", c.meta_value AS order_total, d.meta_value AS credit_amount, e.meta_value AS cash_paid, f.meta_value AS cash_change ";
			$field.= ", n.meta_value AS item_no, i.order_item_name AS item_name, k.meta_value AS qty, l.meta_value AS price, m.meta_value AS line_total ";
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
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} mem ON mem.post_id = a.ID AND mem.meta_key = 'membership_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} db ON db.post_id = a.ID AND db.meta_key = '_debit_deduction' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} h ON h.id = b.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} n ON n.order_item_id = i.order_item_id AND n.meta_key = '_item_number' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} p ON p.order_item_id = i.order_item_id AND p.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} o ON o.id = j.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} at ON at.id = h.acc_type ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
		$cond.= $wpdb->prepare( "AND b.meta_value > %d AND d.meta_value != %d AND c.meta_value IS NOT NULL ", 0, 0 );
		$cond.= "AND ( at.employee_prefix IS NOT NULL AND at.employee_prefix != '' ) ";
		$cond.= "AND ( tr.meta_value IS NULL OR tr.meta_value = '' ) ";
		$cond.= "AND ( db.meta_value IS NULL OR db.meta_value = '' ) ";
		//if( ! current_user_cans( ['wh_support'] ) )
			//$cond.= $wpdb->prepare( "AND h.uid IS NOT NULL AND CHAR_LENGTH( h.uid ) > %d ", 3 );

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

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_customer_credit_acc_type_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "g.code AS acc_type, f.id AS customer_id, COUNT( a.ID ) AS order_count , round( SUM( c.meta_value ), 2 ) AS credit_used ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = 'wc_pos_credit_amount' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = '_order_total' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = '_total_creditable' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} tr ON tr.post_id = a.ID AND tr.meta_key = 'tool_request_id' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} mem ON mem.post_id = a.ID AND mem.meta_key = 'membership_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} db ON db.post_id = a.ID AND db.meta_key = '_debit_deduction' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} f ON f.id = b.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} g ON g.id = f.acc_type ";

		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
		$cond.= $wpdb->prepare( "AND b.meta_value > %d AND c.meta_value != %d AND d.meta_value IS NOT NULL ", 0, 0 );
		$cond.= "AND ( tr.meta_value IS NULL OR tr.meta_value = '' ) ";
		$cond.= "AND ( db.meta_value IS NULL OR db.meta_value = '' ) ";
		$cond.= "AND ( g.employee_prefix IS NOT NULL AND g.employee_prefix != '' ) ";

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
				$cond.= "AND g.id IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND g.id = %s ", $filters['acc_type'] );
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
                $cd[] = "g.name LIKE '%".$kw."%' ";
				$cd[] = "g.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY f.id ";
		$ord = "";
		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//---------------------------------------
		$field = "a.acc_type, COUNT( a.customer_id ) AS customer_count, SUM( a.order_count ) AS order_count
			, round( SUM( a.credit_used ), 2 ) AS credit_used ";

		$table = "( {$sql} ) a ";
		$cond = "";
		
		$grp = "GROUP BY a.acc_type ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.acc_type' => 'ASC' ];
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

	public function get_customer_credit_limit_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "c.id, c.name AS customer_name, c.uid AS sap_emp_id, c.code AS customer_no 
			, act.code AS acc_type, j.name AS position, g.name AS credit_group 
			, IF( t2.id > 0, t2.from_date, t.from_date ) AS from_date, IF( t2.id > 0, t2.to_date, t.to_date ) AS to_date
			, cdl.credit_limit, SUM( cdt.credit_limit ) AS topup 
			, ROUND( cdl.credit_limit + IFNULL( SUM( cdt.credit_limit ), 0 ), 2 ) AS total_creditable ";

		$table = "{$dbname}{$this->tables['customer']} c ";

		$subsql = "SELECT cl.id
			FROM {$dbname}{$this->tables['credit_limit']} cl 
			WHERE 1 AND ( ( cl.scheme = 'customer' AND cl.ref_id = c.id ) OR ( cl.scheme = 'customer_group' AND cl.ref_id = c.cgroup_id ) ) 
			AND cl.status > 0 AND cl.flag > 0 
			ORDER BY cl.scheme_lvl DESC LIMIT 0,1";
		$table.= "LEFT JOIN {$dbname}{$this->tables['credit_limit']} cdl ON cdl.id = ( {$subsql} ) ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} act ON act.id = c.acc_type ";

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
			WHERE 1 AND t.status > 0 ";
		$table.= "LEFT JOIN ( {$subsql} ) t ON t.id = IF( act.term_id > 0, act.term_id, cdl.term_id ) ";
		//$table.= "LEFT JOIN ( {$subsql} ) t ON t.id = cdl.term_id ";
		
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
			WHERE 1 AND t.status > 0 ";
		$table.= "LEFT JOIN ( {$subsql} ) t2 ON t2.parent = t.id AND t.today > t2.from_date AND t.today < t2.to_date ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['credit_topup']} cdt ON cdt.customer_id = c.id AND cdt.effective_from >= t.from_date AND cdt.effective_from <= t.to_date AND cdt.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} g ON g.id = c.cgroup_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_job']} j ON j.id = c.cjob_id  ";

		$cond = $wpdb->prepare( "AND c.status > %d ", 0 );
		$cond.= "AND ( act.employee_prefix IS NOT NULL AND act.employee_prefix != '' ) ";

		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND c.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.id = %s ", $filters['customer'] );
		}
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND act.id IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND act.id = %s ", $filters['acc_type'] );
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
			if( is_array( $filters['cgroup'] ) )
				$cond.= "AND c.cgroup_id IN ('" .implode( "','", $filters['cgroup'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.cgroup_id = %s ", $filters['cgroup'] );
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
				$cd[] = "c.uid LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY c.id ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'c.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//-------------------------------------------------------

		$field = "a.id, a.customer_name, a.sap_emp_id, a.customer_no 
			, a.acc_type, a.position, a.credit_group 
			, a.from_date, a.to_date, a.credit_limit, a.topup 
			, a.total_creditable
			, IFNULL( ROUND( SUM( crd.amount*-1 ), 2 ), 0 ) AS total_used 
			, a.total_creditable - IFNULL( ROUND( SUM( crd.amount*-1 ), 2 ), 0 ) AS balance ";

		$table = "( {$sql} ) a ";

		$subsql = "SELECT cr.id 
			FROM {$dbname}{$wpdb->prefix}wc_poin_of_sale_credit_registry cr 
			WHERE 1 AND cr.user_id = a.id AND cr.type = 'sales' AND cr.status > 0 
			AND cr.time >= a.from_date AND cr.time <= CONCAT( a.to_date, ' 23:59:59' ) ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->prefix}wc_poin_of_sale_credit_registry crd ON crd.id IN( {$subsql} ) ";
		$cond = "";
		$grp = "GROUP BY a.id ";
		$ord = "";
		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
} //class

}