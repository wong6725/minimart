<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_BankIn_Rpt" ) ) 
{
	
class WCWH_BankIn_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "BankIn_Rpt";

	public $tplName = array(
		'export' => 'exportBankin',
		'print' => 'printBankin',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $doc_opts = [];
	public $bank_opts = [];

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
			"customer" => $prefix."customer",
			"warehouse" 	=> $prefix."warehouse",
			"warehousemeta" 	=> $prefix."warehousemeta",
			"exchange_rate" => $prefix."exchange_rate",

			"status"		=> $prefix."status",

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
						case 'bankin_daily':
							$datas['filename'] = 'Remittance_Money__Daily_Summary_Report ';
						break;
						case 'bankin':
						default:
							$datas['filename'] = 'Remittance_Money_Detail_Report ';
						break;
					}

					$params = [];
					//$datas['dateformat'] = 'YmdHis';
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					if( !empty( $datas['doc_id'] ) ) $params['doc_id'] = $datas['doc_id'];
					$params['by_currency'] = $datas['by_currency'];	//Used in Daily Summary
					$params['from_currency'] = $datas['from_currency'];
					$params['to_currency'] = $datas['to_currency'];
					//$params['warehouse'] = $datas['warehouse'];
					$params['date_type'] = $datas['date_type'];
				
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					if( !empty( $datas['doc_id'] ) ) $params['doc_id'] = $datas['doc_id'];
					$params['by_currency'] = $datas['by_currency'];
					$params['from_currency'] = $datas['from_currency'];
					$params['to_currency'] = $datas['to_currency'];
					//$params['warehouse'] = $datas['warehouse'];
					$params['date_type'] = $datas['date_type'];

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
		
		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		$type = $params['export_type'];
		unset( $params['export_type'] );
		switch( $type )
		{
			case 'bankin_daily':
				return $this->get_export_bank_in_daily_summary( $params );
			break;
			case 'bankin':
				return $this->get_export_bank_in_report( $params );
			break;
		}
	}

	protected function print_data_handler( $params = array() )
	{
		$type = $params['export_type'];
		unset( $params['export_type'] );
		
		switch( $type )
		{
			case 'bankin_daily':
				return $this->get_print_bank_in_daily_summary( $params );
			break;
			case 'bankin':
			default:
				return $this->get_print_bank_in_report( $params );
			break;
		}
	}

	public function print_handler( $params = array(), $opts = array() )
	{
		$datas = $this->print_data_handler( $params );

		$this->seller = $params['seller'];
		$type = $params['export_type'];
		unset( $params['export_type'] );
		$date_format = get_option( 'date_format' );
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		
		switch( $type )
		{
			case 'bankin_daily':
				$filename = "Remittance Money Daily Summary Report";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Remittance Money Daily Summary Report';
				$document['heading']['company'] = $warehouse['name']." (".$warehouse['code'].")";
				$document['heading']['title'] = 'Remittance Money Report'.'<br>';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				/*
					if( $this->setting['general_report']['confirm_by'] > 0 ) $superior = get_userdata( $this->setting['general_report']['confirm_by'] );
					if( $superior && in_array( 'warehouse_supervisor', $superior->roles ) && in_array( 'warehouse_executive', $user_info->roles ) )
					{
						$document['footing']['verified'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
						$document['footing']['verified_date'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );

						$document['footing']['confirmed'] = ( $superior->first_name )? $superior->first_name : $superior->display_name;
						$document['footing']['confirmed_date'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
					}
				*/
				$from_currency = get_woocommerce_currency_symbol($datas[0]['from_currency']);
				$amt_currency = "Amount (".$from_currency.")";

				//$to_currency = get_woocommerce_currency_symbol($datas[0]['to_currency']);
				//$convert_amt_currency = "Convert Amount (".$to_currency.")";

				$document['detail_title'] = [
					'Post Date' 			=> [ 'width'=>'12%', 'class'=>['leftered'] ],
					'Convert Amount Currency' => [ 'width'=>'12%', 'class'=>['leftered'] ],
					'Transactions'		 	=> [ 'width'=>'12%', 'class'=>['rightered'] ],
					"$amt_currency" 				=> [ 'width'=>'12%', 'class'=>['rightered'] ],
					'Exchange Rate ' 		=> [ 'width'=>'12%', 'class'=>['rightered'] ],
					"Convert Amount" 		=> [ 'width'=>'12%', 'class'=>['rightered'] ],
					'Service Charge (RM) ' 		=> [ 'width'=>'12%', 'class'=>['rightered'] ],
					'Total Amount (RM) ' 		=> [ 'width'=>'12%', 'class'=>['rightered'] ],

				];
				
				if( $datas )
				{
					$total_amount = 0; 
					$avg_exchange_rate = 0; 
					$total_currency_amount = 0; 
					$avg_service_change = 0;
					$total_total_amount = 0;
					
					foreach( $datas as $i => $data )
					{
							$row = [
								'date' => [ 'value'=>$data['date'], 'class'=>['leftered'] ],
								'convert_amt_currency' => [ 'value'=>$data['convert_amt_currency'], 'class'=>['leftered'] ],
								'transaction' => [ 'value'=>$data['transaction'], 'class'=>['rightered'] ],
								'amount' => [ 'value'=>$data['amount'], 'class'=>['rightered'], 'num' => 1 ],
								'exchange_rate' => [ 'value'=>$data['exchange_rate'], 'class'=>['rightered'], 'num' => 1 ],
								'convert_amount' => [ 'value'=>$data['convert_amount'], 'class'=>['rightered'], 'num' => 1 ],
								'service_charge' => [ 'value'=>$data['service_charge'], 'class'=>['rightered'], 'num' => 1 ],
								'total_amount' => [ 'value'=>$data['total_amount'], 'class'=>['rightered'], 'num' => 1 ],
							];
									
						$details[] = $row;

						$count++;
						$total_amount += $data['amount'];
						$total_transactions += $data['transaction']; 
						$avg_exchange_rate += $data['total_exchange_rate']; 
						$total_currency_amount += $data['convert_amount'];
						$avg_service_change += $data['service_charge'];
						$total_total_amount += $data['total_amount'];
					}
				
					$avg_exchange_rate = ($avg_exchange_rate/$total_transactions);
					//$avg_service_change = ($avg_service_change/$total_transactions);
					
					$details[] = [

						'docno' => [ 'value' => 'TOTAL:', 'class' => ['leftered', 'bold'], 'colspan'=>2 ],
						'transaction' => [ 'value'=>$total_transactions, 'class'=>['rightered', 'bold']],
						'amount' => [ 'value'=>$total_amount, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'exchange_rate' => [ 'value'=>"(AVG) ". round_to( $avg_exchange_rate, 2, 1, 1 ), 'class'=>['rightered', 'bold']],
						'convert_amount' => [ 'value'=>$total_currency_amount, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'service_charge' => [ 'value'=>round_to( $avg_service_change, 2, 1, 1 ), 'class'=>['rightered', 'bold']],
						'total_amount' => [ 'value'=>$total_total_amount, 'class'=>['rightered', 'bold'], 'num' => 1 ],
					];

					$document['detail'] = $details;
				}				
				ob_start();
							
					do_action( 'wcwh_get_template', 'template/doc-remittance-report.php', $document );
				
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
			case 'bankin':
			default:
				$filename = "Remittance Money Report";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Remittance Money Report';
				$document['heading']['company'] = $warehouse['name']." (".$warehouse['code'].")";
				$document['heading']['title'] = 'Remittance Money Report'.'<br>';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				/*
					if( $this->setting['general_report']['confirm_by'] > 0 ) $superior = get_userdata( $this->setting['general_report']['confirm_by'] );
					if( $superior && in_array( 'warehouse_supervisor', $superior->roles ) && in_array( 'warehouse_executive', $user_info->roles ) )
					{
						$document['footing']['verified'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
						$document['footing']['verified_date'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );

						$document['footing']['confirmed'] = ( $superior->first_name )? $superior->first_name : $superior->display_name;
						$document['footing']['confirmed_date'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
					}
				*/
				$from_currency = get_woocommerce_currency_symbol($datas[0]['er_from_currency']);
				$amt_currency = "Amount (".$from_currency.")";

				//$to_currency = get_woocommerce_currency_symbol($datas[0]['er_to_currency']);
				//$convert_amt_currency = "Convert Amount (".$to_currency.")";

				$document['detail_title'] = [
					'Doc No' 			=> [ 'width'=>'7%', 'class'=>['leftered'] ],
					'Doc Date' 			=> [ 'width'=>'7%', 'class'=>['leftered'] ],
					//'Post Date'		 	=> [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Sender Name, Employee_id, Code' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Sender Contact' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Beneficiary Name' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Bank Name' 		=> [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Account No' 		=> [ 'width'=>'10%', 'class'=>['leftered'] ],
					"$amt_currency " 	=> [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Exchange Rate ' 		=> [ 'width'=>'11%', 'class'=>['rightered'] ],
					'Convert Amount ' 	=> [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Service Charge (RM) ' 		=> [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Total Amount (RM) ' 		=> [ 'width'=>'10%', 'class'=>['rightered'] ],

				];
				
				if( $datas )
				{
					$total_amount = 0; 
					$avg_exchange_rate = 0; 
					$total_currency_amount = 0; 
					$avg_service_change = 0;
					$total_total_amount = 0;
					$count = 0;

					foreach( $datas as $i => $data )
					{
						//pd($data);
						//exit();
						$row = [
							'docno' => [ 'value'=>$data['docno'], 'class'=>['leftered'] ],
							'doc_date' => [ 'value'=> date( 'Y-m-d', strtotime( $data['doc_date'] ) )  , 'class'=>['leftered'] ],
							//'post_date' => [ 'value'=> $data['post_date'] , 'class'=>['leftered'] ],
							'name' => [ 'value'=>$data['name'].",<br>".$data['uid'].",<br>".$data['code'], 'class'=>['leftered'] ],
							'sender_contact' => [ 'value'=>$data['sender_contact'], 'class'=>['leftered'] ],
							'account_holder' => [ 'value'=> $data['account_holder'] , 'class'=>['leftered'] ],
							'bank' => [ 'value'=>$data['bank'], 'class'=>['leftered'] ],
							'account_no' => [ 'value'=>implode("-", str_split($data['account_no'], 4)), 'class'=>['leftered'] ],
							'amount' => [ 'value'=>$data['amount'], 'class'=>['rightered'], 'num' => 1 ],
							'exchange_rate' => [ 'value'=>$data['exchange_rate'], 'class'=>['rightered'], 'num' => 1 ],
							'convert_amount' => [ 'value'=>get_woocommerce_currency_symbol($data['to_currency'])." ".number_format($data['convert_amount'],2), 'class'=>['rightered'] ],
							'service_charge' => [ 'value'=>$data['service_charge'], 'class'=>['rightered'], 'num' => 1 ],
							'total_amount' => [ 'value'=>$data['total_amount'], 'class'=>['rightered'], 'num' => 1 ],
						];
									
						$details[] = $row;

						$count++;
						$total_amount += $data['amount']; 
						$avg_exchange_rate += $data['exchange_rate']; 
						$total_currency_amount += $data['convert_amount'];
						$avg_service_change += $data['service_charge'];
						$total_total_amount += $data['total_amount'];
					}

					$avg_exchange_rate = ($avg_exchange_rate/$count);
					//$avg_service_change = ($avg_service_change/$count);

					$details[] = [
						'docno' => [ 'value' => 'TOTAL:', 'class' => ['leftered', 'bold'], 'colspan' =>7  ],
						'amount' => [ 'value'=>$total_amount, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'exchange_rate' => [ 'value'=>"(AVG) ". round_to( $avg_exchange_rate, 2, 1, 1 ), 'class'=>['rightered', 'bold']],
						'convert_amount' => [ 'value'=>$total_currency_amount, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'service_charge' => [ 'value'=>round_to( $avg_service_change, 2, 1, 1 ), 'class'=>['rightered', 'bold']],
						'total_amount' => [ 'value'=>$total_total_amount, 'class'=>['rightered', 'bold'], 'num' => 1 ],
					];

					$document['detail'] = $details;
				}	
	
				ob_start();
					do_action( 'wcwh_get_template', 'template/doc-remittance-report.php', $document );
					
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

	public function export_form( $type = 'bankin' )
	{
		include_once( WCWH_DIR."/includes/reports/bankInList.php" ); 
		$Inst = new WCWH_Bank_In_report();

		$Inst->seller = $this->seller;
		$args['seller'] = $this->seller;
		
		$action_id = 'bank_in_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		if( $this->doc_opts ) $args['doc_opts'] = $this->doc_opts;
		if( $this->bank_opts ) $args['bank_opts'] = $this->bank_opts;

		switch( strtolower( $type ) )
		{
			case 'bankin_daily':
				do_action( 'wcwh_templating', 'report/export-bankin-daily-summary-report.php', $this->tplName['export'], $args );
			break;
			case 'bankin':
			default:
				do_action( 'wcwh_templating', 'report/export-bankin-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'bankin' )
	{
		include_once( WCWH_DIR."/includes/reports/bankInList.php" ); 
		$Inst = new WCWH_Bank_In_report();

		$Inst->seller = $this->seller;
		

		$action_id = 'bank_in_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
		);
		$args['seller'] = $this->seller;

		if( $this->filters ) $args['filters'] = $this->filters;
		if( $this->doc_opts ) $args['doc_opts'] = $this->doc_opts;
		if( $this->bank_opts ) $args['bank_opts'] = $this->bank_opts;

		switch( strtolower( $type ) )
		{
			case 'bankin_daily':
				do_action( 'wcwh_templating', 'report/export-bankin-daily-summary-report.php', $this->tplName['print'], $args );
			break;
			case 'bankin':
			default:
				do_action( 'wcwh_templating', 'report/export-bankin-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	Bank In Report
	 */
	public function bank_in_report ( $filters = array(), $order = array() )
	{
		$action_id = 'bank_in_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/bankInList.php" ); 
			$Inst = new WCWH_Bank_In_report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-d' );
			$date_to = current_time( 'Y-m-d' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			$filters['doc_stat'] = !empty( $filters['doc_stat'] )? $filters['doc_stat'] : 'all';
			$filters['s'] = !empty( $filters['s'] )? $filters['s'] : "";

			if( $this->seller ) $filters['seller'] = $this->seller;

			/*
				//last search-----------------------------------------------------------------
				//defaulter
					$def_filters = [];
					$def_filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $date_from ) );
					$def_filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $date_to." 23:59:59" ) );
					$def_filters['doc_stat'] = 'all';

					$def_filters['s'] = "";
					if( $this->seller ) $def_filters['seller'] = $this->seller;
					//pd(json_encode( $def_filters ));
				//current
					$curr_filters = $filters; 
					unset( $curr_filters['orderby'] );
					unset( $curr_filters['order'] );
					unset( $curr_filters['qs'] );
					unset( $curr_filters['paged'] );
					unset( $curr_filters['status'] );
					//pd(json_encode($curr_filters));
				//previous
					$prev_filters = get_transient( get_current_user_id().$this->seller.$action_id );
					//pd(json_encode( $prev_filters ));

				if( $prev_filters !== false && json_encode( $prev_filters ) != json_encode( $def_filters ) &&
					json_encode( $curr_filters ) == json_encode( $def_filters ) )
					$filters = $prev_filters;
				if( json_encode( $curr_filters ) != json_encode( $def_filters ) && 
					json_encode( $curr_filters ) != json_encode( $prev_filters ) )
					set_transient( get_current_user_id().$this->seller.$action_id, $curr_filters, 0 );

				//----------------------------------------------------------------------------
			*/
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.amount, .exchange_rate, .convert_amount, .service_charge, .total_amount' => [ 'text-align'=>'right !important' ],
				'#amount a span, #exchange_rate a span, #convert_amount a span, #service_charge a span, #total_amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();
	
			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_bank_in_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = []; $bank_opts = []; 
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['docno'];
				$bank_opts[ $dat['bank'] ] = $dat['bank'];
				$doc[] = $dat['doc_id'];

			}
			if( $doc_opts ) 
			{
				$this->doc_opts = $doc_opts;
				$Inst->doc_opts = $doc_opts;
			}
			if($bank_opts)
			{
				$this->bank_opts = $bank_opts;
				$Inst->bank_opts = $bank_opts;
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function get_bank_in_report( $filters = [], $order = [], $args = [] )
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

		$field = "a.*, c.name, c.uid, d_customer_serial.meta_value AS customer_serial,
		d_bank.meta_value AS bank,
		d_sender_contact.meta_value AS sender_contact,
		d_account_holder.meta_value AS account_holder,
		er.from_currency AS From_Currency,
		d_amount.meta_value AS amount, 
		er.rate AS exchange_rate,
		d_currency.meta_value AS to_currency, 
		er.from_currency AS er_from_currency,
		d_account_no.meta_value AS d_account_no,
		er.to_currency AS er_to_currency,
		d_convert_amount.meta_value AS convert_amount, 
		d_service.meta_value AS service_charge,
		d_total_amount.meta_value AS total_amount "; 
		
		$table = "{$dbname}{$this->tables['document']} a ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_customer_serial ON d_customer_serial.doc_id = a.doc_id AND d_customer_serial.meta_key = 'customer_serial' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_amount ON d_amount.doc_id = a.doc_id AND d_amount.meta_key = 'amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_currency ON d_currency.doc_id = a.doc_id AND d_currency.meta_key = 'currency' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_service ON d_service.doc_id = a.doc_id AND d_service.meta_key = 'service_charge' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_total_amount ON d_total_amount.doc_id = a.doc_id AND d_total_amount.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_bank ON d_bank.doc_id = a.doc_id AND d_bank.meta_key = 'bank' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_account_holder ON d_account_holder.doc_id = a.doc_id AND d_account_holder.meta_key = 'account_holder' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_sender_contact ON d_sender_contact.doc_id = a.doc_id AND d_sender_contact.meta_key = 'sender_contact' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_convert_amount ON d_convert_amount.doc_id = a.doc_id AND d_convert_amount.meta_key = 'convert_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_account_no ON d_account_no.doc_id = a.doc_id AND d_account_no.meta_key = 'account_no' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_customer_id ON d_customer_id.doc_id = a.doc_id AND d_customer_id.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = d_customer_id.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_exchange ON d_exchange.doc_id = a.doc_id AND d_exchange.meta_key = 'ref_exchange_rate' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['exchange_rate']} er ON er.id = d_exchange.meta_value ";

		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", 'bank_in', 6 );
		
		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}

		if(empty($filters['from_currency']))
		{
			$filters['from_currency'] = 'MYR';
			
		}
		if( is_array( $filters['from_currency'] ) )
			$cond.= "AND er.from_currency IN ('" .implode( "','", $filters['from_currency'] ). "') ";
		else
			$cond.= $wpdb->prepare( "AND er.from_currency = %s ", $filters['from_currency'] );

		if(!empty($filters['to_currency']))
		{
			if( is_array( $filters['to_currency'] ) )
				$cond.= "AND er.to_currency IN ('" .implode( "','", $filters['to_currency'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND er.to_currency = %s ", $filters['to_currency'] );
		}
		
		if( isset( $filters['date_type'] ) )
		{
			if( $filters['date_type'] == "post_date" )
			{
				if( isset( $filters['from_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
				}
				if( isset( $filters['to_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
				}
			}
			else
			{
				if( isset( $filters['from_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
				}
				if( isset( $filters['to_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
				}
			}
		}
		
		if( isset( $filters['bank'] ) )
		{
			if( is_array( $filters['bank'] ) )
				$cond.= "AND d_bank.meta_value IN ('" .implode( "','", $filters['bank'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND d_bank.meta_value = %s ", $filters['bank'] );
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
                $cd[] = "a.docno LIKE '%".$kw."%' ";
				$cd[] = "a.doc_date LIKE '%".$kw."%' ";
				$cd[] = "a.post_date LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.uid LIKE '%".$kw."%' ";
				$cd[] = "d_customer_serial.meta_value LIKE '%".$kw."%' ";
				$cd[] = "d_bank.meta_value LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno' => 'ASC' ];
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

	public function get_export_bank_in_report( $filters = [], $order = [], $args = [] )
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

		$field = "a.docno AS Docno, a.doc_date AS Doc_DateTime, a.post_date AS Post_DateTime,
				  c.name AS Sender_Name, CONCAT(c.uid,' ') AS Employee_ID,
				  d_receiver_contact.meta_value AS Receiver_Contact,
				  CONCAT(d_sender_contact.meta_value,' ') AS Sender_Contact,
				  d_acc_holder.meta_value AS Beneficiary_Name,
				  d_bank.meta_value AS Bank_Name,
				  d_bank_code.meta_value AS Bank_Code,
				  d_bcountry.meta_value AS Bank_Country,
				  d_baddr.meta_value AS Bank_Address,
				  d_account_no.meta_value AS Account_No,
				  er.from_currency AS From_Currency,
				  er.to_currency AS To_Currency,
				  d_amount.meta_value AS Amount, 
				  er.rate AS Exchange_Rate,
				  d_convert_amount.meta_value AS Convert_Amount, 
				  d_service.meta_value AS Service_Charge,
				  d_total_amount.meta_value AS Total_Amount,
				  a.lupdate_at AS Last_Updated_DateTime ";

		$table = "{$dbname}{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_receiver_contact ON d_receiver_contact.doc_id = a.doc_id AND d_receiver_contact.meta_key = 'receiver_contact' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_sender_contact ON d_sender_contact.doc_id = a.doc_id AND d_sender_contact.meta_key = 'sender_contact' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_customer_serial ON d_customer_serial.doc_id = a.doc_id AND d_customer_serial.meta_key = 'customer_serial' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_acc_holder ON d_acc_holder.doc_id = a.doc_id AND d_acc_holder.meta_key = 'account_holder' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_bank ON d_bank.doc_id = a.doc_id AND d_bank.meta_key = 'bank' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_bank_code ON d_bank_code.doc_id = a.doc_id AND d_bank_code.meta_key = 'bank_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_bcountry ON d_bcountry.doc_id = a.doc_id AND d_bcountry.meta_key = 'bank_country' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_currency ON d_currency.doc_id = a.doc_id AND d_currency.meta_key = 'currency' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_baddr ON d_baddr.doc_id = a.doc_id AND d_baddr.meta_key = 'bank_address' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_account_no ON d_account_no.doc_id = a.doc_id AND d_account_no.meta_key = 'account_no' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_amount ON d_amount.doc_id = a.doc_id AND d_amount.meta_key = 'amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_service ON d_service.doc_id = a.doc_id AND d_service.meta_key = 'service_charge' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_convert_amount ON d_convert_amount.doc_id = a.doc_id AND d_convert_amount.meta_key = 'convert_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_total_amount ON d_total_amount.doc_id = a.doc_id AND d_total_amount.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_customer_id ON d_customer_id.doc_id = a.doc_id AND d_customer_id.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = d_customer_id.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_exchange ON d_exchange.doc_id = a.doc_id AND d_exchange.meta_key = 'ref_exchange_rate' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['exchange_rate']} er ON er.id = d_exchange.meta_value ";

		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", 'bank_in', 6 );

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}

		if(empty($filters['from_currency']))
		{
			$filters['from_currency'] = 'MYR';
			
		}
		if( is_array( $filters['from_currency'] ) )
			$cond.= "AND er.from_currency IN ('" .implode( "','", $filters['from_currency'] ). "') ";
		else
			$cond.= $wpdb->prepare( "AND er.from_currency = %s ", $filters['from_currency'] );

		if(!empty($filters['to_currency']))
		{
			if( is_array( $filters['to_currency'] ) )
				$cond.= "AND er.to_currency IN ('" .implode( "','", $filters['to_currency'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND er.to_currency = %s ", $filters['to_currency'] );
		}
		
		if( isset( $filters['date_type'] ) )
		{
			if( $filters['date_type'] == "post_date" )
			{
				if( isset( $filters['from_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
				}
				if( isset( $filters['to_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
				}
			}
			else
			{
				if( isset( $filters['from_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
				}
				if( isset( $filters['to_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
				}
			}
		}

		if( isset( $filters['doc_stat'] ) )
		{
			if( $filters['doc_stat'] == 'posted' )
				$cond.= $wpdb->prepare( "AND a.status >= %s ", 6 );
			else if( $filters['doc_stat'] == 'all' )
				$cond.= $wpdb->prepare( "AND a.status > %s ", 0 );
			else
				$cond.= $wpdb->prepare( "AND a.status = %s ", $filters['doc_stat'] );
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if(is_array($results) && !empty($results))
		{
			foreach ($results as $key=>$value)
			{
				if($results[$key]['Account_No'])
				{
					$results[$key]['Account_No'] = implode("-", str_split($value['Account_No'], 4));
				}
			}
		}

		return $results;
	}

	public function get_print_bank_in_report( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

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

		$field = "a.*, c.name, c.uid,d_sender_contact.meta_value AS sender_contact, d_customer_serial.meta_value AS code,
		d_bank.meta_value AS bank,
		d_account_holder.meta_value AS account_holder,
		d_account_no.meta_value AS account_no,
		d_amount.meta_value AS amount, 
		er.rate AS exchange_rate,
		er.from_currency AS er_from_currency,
		er.to_currency AS er_to_currency,
		d_currency.meta_value AS to_currency, 
		d_convert_amount.meta_value AS convert_amount, 
		d_service.meta_value AS service_charge,
		d_total_amount.meta_value AS total_amount "; 
		
		$table = "{$dbname}{$this->tables['document']} a ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_customer_serial ON d_customer_serial.doc_id = a.doc_id AND d_customer_serial.meta_key = 'customer_serial' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_sender_contact ON d_sender_contact.doc_id = a.doc_id AND d_sender_contact.meta_key = 'sender_contact' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_amount ON d_amount.doc_id = a.doc_id AND d_amount.meta_key = 'amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_currency ON d_currency.doc_id = a.doc_id AND d_currency.meta_key = 'currency' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_service ON d_service.doc_id = a.doc_id AND d_service.meta_key = 'service_charge' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_total_amount ON d_total_amount.doc_id = a.doc_id AND d_total_amount.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_bank ON d_bank.doc_id = a.doc_id AND d_bank.meta_key = 'bank' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_account_holder ON d_account_holder.doc_id = a.doc_id AND d_account_holder.meta_key = 'account_holder' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_account_no ON d_account_no.doc_id = a.doc_id AND d_account_no.meta_key = 'account_no' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_convert_amount ON d_convert_amount.doc_id = a.doc_id AND d_convert_amount.meta_key = 'convert_amount' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_customer_id ON d_customer_id.doc_id = a.doc_id AND d_customer_id.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = d_customer_id.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_exchange ON d_exchange.doc_id = a.doc_id AND d_exchange.meta_key = 'ref_exchange_rate' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['exchange_rate']} er ON er.id = d_exchange.meta_value ";

		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", 'bank_in', 6 );
		
		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}
		if(empty($filters['from_currency']))
		{
			$filters['from_currency'] = 'MYR';
			
		}
		if( is_array( $filters['from_currency'] ) )
			$cond.= "AND er.from_currency IN ('" .implode( "','", $filters['from_currency'] ). "') ";
		else
			$cond.= $wpdb->prepare( "AND er.from_currency = %s ", $filters['from_currency'] );

		if(!empty($filters['to_currency']))
		{
			if( is_array( $filters['to_currency'] ) )
				$cond.= "AND er.to_currency IN ('" .implode( "','", $filters['to_currency'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND er.to_currency = %s ", $filters['to_currency'] );
		}

		if( isset( $filters['date_type'] ) )
		{
			if( $filters['date_type'] == "post_date" )
			{
				if( isset( $filters['from_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
				}
				if( isset( $filters['to_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
				}
			}
			else
			{
				if( isset( $filters['from_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
				}
				if( isset( $filters['to_date'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
				}
			}
		}

		if( isset( $filters['doc_stat'] ) )
		{
			if( $filters['doc_stat'] == 'posted' )
				$cond.= $wpdb->prepare( "AND a.status >= %s ", 6 );
			else if( $filters['doc_stat'] == 'all' )
				$cond.= $wpdb->prepare( "AND a.status > %s ", 0 );
			else
				$cond.= $wpdb->prepare( "AND a.status = %s ", $filters['doc_stat'] );
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno' => 'ASC' ];
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

	/**
	 *	Bank In Summary Report
	 */
	public function bank_in_daily_summary ( $filters = array(), $order = array() )
	{
		$action_id = 'bank_in_daily_summary';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/bankInSummaryList.php" ); 
			$Inst = new WCWH_Bank_In_Sumamary_report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			$filters['doc_stat'] = !empty( $filters['doc_stat'] )? $filters['doc_stat'] : 'all';
			$filters['s'] = !empty( $filters['s'] )? $filters['s'] : "";
			if( $this->seller ) $filters['seller'] = $this->seller;

			/*
				//last search-----------------------------------------------------------------
				//defaulter
					$def_filters = [];
					$def_filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $date_from ) );
					$def_filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $date_to." 23:59:59" ) );
					$def_filters['doc_stat'] = 'all';

					$def_filters['s'] = "";
					if( $this->seller ) $def_filters['seller'] = $this->seller;
					//pd(json_encode( $def_filters ));
				//current
					$curr_filters = $filters; 
					unset( $curr_filters['orderby'] );
					unset( $curr_filters['order'] );
					unset( $curr_filters['qs'] );
					unset( $curr_filters['paged'] );
					unset( $curr_filters['status'] );
					//pd(json_encode($curr_filters));
				//previous
					$prev_filters = get_transient( get_current_user_id().$this->seller.$action_id );
					//pd(json_encode( $prev_filters ));

				if( $prev_filters !== false && json_encode( $prev_filters ) != json_encode( $def_filters ) &&
					json_encode( $curr_filters ) == json_encode( $def_filters ) )
					$filters = $prev_filters;
				if( json_encode( $curr_filters ) != json_encode( $def_filters ) && 
					json_encode( $curr_filters ) != json_encode( $prev_filters ) )
					set_transient( get_current_user_id().$this->seller.$action_id, $curr_filters, 0 );

				//----------------------------------------------------------------------------
			*/
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.transaction, .amount, .exchange_rate, .convert_amount, .service_charge, .total_amount ' => [ 'text-align'=>'right !important' ],
				'#transaction a span, #amount a span, #exchange_rate a span, #convert_amount a span, #service_charge a span, #total_amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();
			
			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_bank_in_daily_summary( $filters, $order, [] );
				
				$datas = ( $datas )? $datas : array();
			}
		
			$doc = []; $doc_opts = []; $bank_opts = []; 
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['docno'];
				$bank_opts[ $dat['bank'] ] = $dat['bank'];
				$doc[] = $dat['doc_id'];

			}
			if( $doc_opts ) 
			{
				$this->doc_opts = $doc_opts;
				$Inst->doc_opts = $doc_opts;
			}
			if($bank_opts)
			{
				$this->bank_opts = $bank_opts;
				$Inst->bank_opts = $bank_opts;
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
	public function get_bank_in_daily_summary( $filters = [], $order = [], $args = [] )
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
		
		$field = "DATE_FORMAT( a.doc_date, '%Y-%m-%d' ) AS date, COUNT( a.doc_id ) AS transaction,
		d_currency.meta_value AS convert_amt_currency,
		ROUND( SUM( d_amount.meta_value ) , 2 ) AS amount, 
		ROUND( AVG( er.rate ) , 2 ) AS exchange_rate, ROUND( SUM( er.rate ) , 2 ) AS total_exchange_rate, 
		ROUND( SUM( d_convert_amount.meta_value ) , 2 ) AS convert_amount, 
		ROUND( SUM( d_service.meta_value ) , 2 ) AS service_charge,
		ROUND( SUM( d_total_amount.meta_value ) , 2 ) AS total_amount "; 
		
		$table = "{$dbname}{$this->tables['document']} a ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_amount ON d_amount.doc_id = a.doc_id AND d_amount.meta_key = 'amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_currency ON d_currency.doc_id = a.doc_id AND d_currency.meta_key = 'currency' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_service ON d_service.doc_id = a.doc_id AND d_service.meta_key = 'service_charge' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_total_amount ON d_total_amount.doc_id = a.doc_id AND d_total_amount.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_convert_amount ON d_convert_amount.doc_id = a.doc_id AND d_convert_amount.meta_key = 'convert_amount' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_exchange ON d_exchange.doc_id = a.doc_id AND d_exchange.meta_key = 'ref_exchange_rate' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['exchange_rate']} er ON er.id = d_exchange.meta_value ";

		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", 'bank_in', 6 );
		
		if(!empty($filters['by_currency']))
		{
			if( is_array( $filters['by_currency'] ) )
				$cond.= "AND er.to_currency IN ('" .implode( "','", $filters['by_currency'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND er.to_currency = %s ", $filters['by_currency'] );
		}
		
		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
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
                $cd[] = "a.post_date LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "GROUP BY date, d_currency.meta_value ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno' => 'ASC' ];
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

	public function get_export_bank_in_daily_summary( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( isset( $filters['seller'] ) )
		{
			
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

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

		$field = "DATE_FORMAT( a.doc_date, '%Y-%m-%d' ) AS date, COUNT( a.doc_id ) AS transaction,
		d_currency.meta_value AS convert_amt_currency,
		ROUND( SUM( d_amount.meta_value ) , 2 ) AS amount, 
		ROUND( AVG( er.rate ) , 2 ) AS exchange_rate, ROUND( SUM( er.rate ) , 2 ) AS total_exchange_rate, 
		ROUND( SUM( d_convert_amount.meta_value ) , 2 ) AS convert_amount, 
		ROUND( SUM( d_service.meta_value ) , 2 ) AS service_charge,
		ROUND( SUM( d_total_amount.meta_value ) , 2 ) AS total_amount "; 
		
		$table = "{$dbname}{$this->tables['document']} a ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_amount ON d_amount.doc_id = a.doc_id AND d_amount.meta_key = 'amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_currency ON d_currency.doc_id = a.doc_id AND d_currency.meta_key = 'currency' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_service ON d_service.doc_id = a.doc_id AND d_service.meta_key = 'service_charge' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_total_amount ON d_total_amount.doc_id = a.doc_id AND d_total_amount.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_convert_amount ON d_convert_amount.doc_id = a.doc_id AND d_convert_amount.meta_key = 'convert_amount' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_exchange ON d_exchange.doc_id = a.doc_id AND d_exchange.meta_key = 'ref_exchange_rate' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['exchange_rate']} er ON er.id = d_exchange.meta_value ";

		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", 'bank_in', 6 );

		if(!empty($filters['by_currency']))
		{
			if( is_array( $filters['by_currency'] ) )
				$cond.= "AND er.to_currency IN ('" .implode( "','", $filters['by_currency'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND er.to_currency = %s ", $filters['by_currency'] );
		}

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
		}

		$grp = "GROUP BY date, d_currency.meta_value ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno' => 'ASC' ];
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

	public function get_print_bank_in_daily_summary( $filters = [], $order = [], $args = [] )
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

		$field = "DATE_FORMAT( a.doc_date, '%Y-%m-%d' ) AS date, COUNT( a.doc_id ) AS transaction,
		d_currency.meta_value AS convert_amt_currency,
		ROUND( SUM( d_amount.meta_value ) , 2 ) AS amount, 
		ROUND( AVG( er.rate ) , 2 ) AS exchange_rate, ROUND( SUM( er.rate ) , 2 ) AS total_exchange_rate, 
		ROUND( SUM( d_convert_amount.meta_value ) , 2 ) AS convert_amount, 
		ROUND( SUM( d_service.meta_value ) , 2 ) AS service_charge,
		ROUND( SUM( d_total_amount.meta_value ) , 2 ) AS total_amount,
		er.from_currency AS from_currency, 
		er.to_currency AS to_currency ";
		
		$table = "{$dbname}{$this->tables['document']} a ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_amount ON d_amount.doc_id = a.doc_id AND d_amount.meta_key = 'amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_currency ON d_currency.doc_id = a.doc_id AND d_currency.meta_key = 'currency' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_service ON d_service.doc_id = a.doc_id AND d_service.meta_key = 'service_charge' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_total_amount ON d_total_amount.doc_id = a.doc_id AND d_total_amount.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_convert_amount ON d_convert_amount.doc_id = a.doc_id AND d_convert_amount.meta_key = 'convert_amount' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d_exchange ON d_exchange.doc_id = a.doc_id AND d_exchange.meta_key = 'ref_exchange_rate' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['exchange_rate']} er ON er.id = d_exchange.meta_value ";

		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", 'bank_in', 6 );

		if(!empty($filters['by_currency']))
		{
			if( is_array( $filters['by_currency'] ) )
				$cond.= "AND er.to_currency IN ('" .implode( "','", $filters['by_currency'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND er.to_currency = %s ", $filters['by_currency'] );
		}

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
		}

		$grp = "GROUP BY date, d_currency.meta_value ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno' => 'ASC' ];
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