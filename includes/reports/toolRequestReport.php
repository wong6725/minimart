<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ToolRequestReport_Rpt" ) ) 
{
	
class WCWH_ToolRequestReport_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "ToolRequestReport";

	public $tplName = array(
		'export' => 'exportToolRequest',
		'print' => 'printToolRequest',
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
			"document"		=> $prefix."document",
			"document_items"=> $prefix."document_items",
			"document_meta"	=> $prefix."document_meta",

			"items"			=> $prefix."items",
			"item_group"	=> $prefix."item_group",
			
			"customer" 		=> $prefix."customer",
			"tree"			=> $prefix."customer_tree",
			"meta"			=> $prefix."customermeta",

			"customer_group"=> $prefix."customer_group",
			"customer_job"	=> $prefix."customer_job",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",

			"postmeta"		=> $wpdb->prefix."postmeta",
			"price"			=> $prefix."price",

			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"inventory"		=> $prefix."inventory",
			"storage"		=> $prefix."storage",
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
							$datas['filename'] = !empty( $acc_type['code'] )? $acc_type['code'] : 'tool_request_summary';
						break;
						case 'details':
							$datas['filename'] = 'tool_request_detail';
						break;
					}
					
					$datas['dateformat'] = 'Y'.date( 'm', strtotime( $datas['to_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['product_id'] ) ) $params['product_id'] = $datas['product_id'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					if( !empty( $datas['fulfilment'] ) ) $params['fulfilment'] = $datas['fulfilment'];
					
					//pd($this->export_data_handler( $params ));
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['from_date_month'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date_month'] ) );
					if( !empty( $datas['to_date_month'] ) ) $params['to_date'] = date( 'Y-m-t H:i:s', strtotime( $datas['to_date_month']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['product_id'] ) ) $params['product_id'] = $datas['product_id'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
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

	public function print_handler( $params = array(), $opts = array() )
	{
		$datas = $this->export_data_handler( $params );
		
		$type = $params['export_type'];
		unset( $params['export_type'] );
		$date_format = get_option( 'date_format' );
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		
		switch( $type )
		{
			case 'details':
				$filename = "Tool Requisition";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Tool Requisition';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Tool Requisition';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Date' => [ 'width'=>'8%', 'class'=>['centered'] ],
					'Document' => [ 'width'=>'6%', 'class'=>['centered']],
					'Customer' => [ 'width'=>'14%', 'class'=>['centered']],
					'Customer No.' => [ 'width'=>'6%', 'class'=>['centered']],
					'Receipt' => [ 'width'=>'8%', 'class'=>['centered']],
					'Item Group' => [ 'width'=>'6%', 'class'=>['centered']],
					'Item Name' => [ 'width'=>'18%', 'class'=>['centered']],
					'UOM' => [ 'width'=>'2%', 'class'=>['centered']],
					'Request Qty' => [ 'width'=>'5%', 'class'=>['centered']],
					'Balance Qty' => [ 'width'=>'5%', 'class'=>['centered']],
					'Price ('.$currency.')' => [ 'width'=>'8%', 'class'=>['centered']], 
					'Line Total ('.$currency.')' => [ 'width'=>'14%', 'class'=>['centered']], 
					// 'Amount ('.$currency.')' => [ 'width'=>'10%', 'class'=>['centered']],
				];
				if( $datas )
				{
					
						foreach( $datas as $i => $data )
						{
							
							$regrouped[ $data['receipt'] ][ $data['item_no'] ][$i] = $data;

							//rowspan handling
							$rowspan[ $data['receipt'] ] += 1;

							//totals
							$totals[ $data['receipt'] ][ $data['item_no'] ] += $data['line_total'];
						}
					
						$details = [];
						if($regrouped)
						{
							$total = 0;
							foreach ($regrouped as $orders => $order) 
							{
								$subtotal =0;
								$order_added='';
								foreach($order as $order_items =>$items)
								{
									if( $totals[ $orders ][ $order_items ] )
									{
										$rowspan[ $orders ] += count( $totals[ $orders ] ) + 1;
									}
									foreach($items as $i => $vals)
									{
										$row = [

											'date' => [ 'value'=>$vals['date'], 'class'=>['centered'], 'rowspan'=>$rowspan[ $orders ]  ],
											'docno' => [ 'value'=>$vals['docno'], 'class'=>['centered'], 'rowspan'=>$rowspan[ $orders ]   ],
											'customer' => [ 'value'=>$vals['customer'], 'class'=>['centered'], 'rowspan'=>$rowspan[ $orders ]  ],
											'customer_code' => [ 'value'=>$vals['customer_code'], 'class'=>['centered'], 'rowspan'=>$rowspan[ $orders ]],
											'receipt' => [ 'value'=>$vals['receipt'], 'class'=>['centered'], 'rowspan'=>$rowspan[ $orders ] ],
											'group' => [ 'value'=>$vals['group'], 'class'=>['centered']],
											'item' => [ 'value'=>$vals['item'], 'class'=>['centered']],
											'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered']],
											'quantity' => [ 'value'=>$vals['quantity'], 'class'=>['centered'], 'num' => 1 ],
											'balance' => [ 'value'=>$vals['balance'], 'class'=>['centered'], 'num' => 1 ],
											'price' => [ 'value'=>$vals['price'], 'class'=>['centered'], 'num' => 1 ],
											'line_total' => [ 'value'=>$vals['line_total'], 'class'=>['rightered'], 'num' => 1 ],
											//'amount' => [ 'value'=>$vals['amount'], 'class'=>['centered'], 'num' => 1 ],
					
											];

											if( $order_added == $orders )
											{
												$row['date'] = [];
												$row['docno'] = [];
												$row['customer'] = [];
												$row['customer_code'] = [];
												$row['receipt'] = [];
											} 
											$order_added = $orders;

											$subtotal = $totals[ $orders ][ $order_items ];

											$details[] = $row;
									}
								}
								$details[] = [
									'date' => [],
									'docno' => [],
									'customer' => [],
									'customer_code' => [],
									'receipt' => [],
									'group' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>6 ],
									'line_total' => [ 'value'=>$subtotal, 'class'=>['rightered','bold'], 'num' => 1 ],
								];
								$total += $subtotal;

							}
							$details[] = [
								'date' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>11],
								'amount' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num' => 1 ],
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
			case 'summary':
				$filename = "Tool Requisition";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Tool Requisition';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Tool Requisition';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Date' => [ 'width'=>'15%', 'class'=>['centered'] ],
					'Document' => [ 'width'=>'15%', 'class'=>['centered']],
					'Customer' => [ 'width'=>'30%', 'class'=>['centered']],
					'Customer No.' => [ 'width'=>'15%', 'class'=>['centered']],
					'Receipt' => [ 'width'=>'10%', 'class'=>['centered']],
					'Total Amount ('.$currency.')' => [ 'width'=>'15%', 'class'=>['centered']],
				];
				if( $datas )
				{
					
					$details = [];
					foreach( $datas as $i => $data )
					{
						$row = [

                        'date' => [ 'value'=>$data['date'], 'class'=>['centered'] ],
                        'docno' => [ 'value'=>$data['docno'], 'class'=>['centered']  ],
                        'customer' => [ 'value'=>$data['customer'], 'class'=>['centered'] ],
                        'customer_code' => [ 'value'=>$data['customer_code'], 'class'=>['centered'] ],
						'receipt' => [ 'value'=>$data['receipt'], 'class'=>['centered'] ],
						'total_amount' => [ 'value'=>$data['total_amount'], 'class'=>['rightered'], 'num'=>1 ],
						];

						$details[] = $row;
						$total += $data['total_amount'];
						
					}
					$details[] = [
						'date' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>5],
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

			}
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
				return $this->get_tool_request_summary_report( $params, $order, [] );
				
			break;
			case 'details':
					return $this->get_tool_request_detail_report( $params, $order, [] );
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
		$action_id = 'tool_request_report';
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
				do_action( 'wcwh_templating', 'report/export-tool-request-report.php', $this->tplName['export'], $args );
			break;
			case 'details':
				do_action( 'wcwh_templating', 'report/export-tool-request-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'tool_request_report';
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
				do_action( 'wcwh_templating', 'report/export-tool-request-report.php', $this->tplName['print'], $args );
			break;
			case 'details':
				do_action( 'wcwh_templating', 'report/export-tool-request-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	
	public function tool_request_report( $filters = array(), $order = array() )
	{
		$action_id = 'tool_request_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/toolRequestDetailList.php" ); 
			$Inst = new WCWH_ToolRequestDetail_Report();
			$Inst->seller = $this->seller;

			if( $this->seller ) $filters['seller'] = $this->seller;
			if($filters['seller'])
			{
				$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
				$filters['wh'] = $wh['code'];
				
			}else
			{
				$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
				$filters['wh'] = $curr_wh ['code'];
			}

			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );

			
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
				'.bal_qty, .fulfil_amt, .fulfil_qty, .sale_amt, .sprice, .quantity, .stock_qty' => [ 'text-align'=>'right !important' ],
				];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_tool_request_detail_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function tool_request_summary_report( $filters = array(), $order = array() )
	{
		$action_id = 'tool_request_summary_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/toolRequestSummaryList.php" ); 
			$Inst = new WCWH_ToolRequestSummary_Report();
			$Inst->seller = $this->seller;

			if( $this->seller ) $filters['seller'] = $this->seller;
			if($filters['seller'])
			{
				$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
				$filters['wh'] = $wh['code'];
				
			}else
			{
				$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
				$filters['wh'] = $curr_wh ['code'];
			}

			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );

			
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
				'.total_amount' => [ 'text-align'=>'right !important' ],
				];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_tool_request_summary_report( $filters, $order, [] );
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
	public function get_tool_request_detail_report( $filters = [], $order = [], $args = [] )
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

			$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
		}
		else
		{
			$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		}
		
		$field = "a.docno, DATE_FORMAT( a.doc_date, '%Y-%m-%d' ) AS doc_date, e.name as customer, e.code as customer_code ";
		$field.= ", a2.meta_value AS remark ";
		$field.= ", g.name as item_group, f.code as item_code, f.name as item_name ";
		$field.= ", f._uom_code as uom, b3.meta_value AS instalment, b.bqty as quantity, b1.meta_value AS sprice, b2.meta_value AS sale_amt ";
		$field.= ", group_concat( distinct s.receipt separator ', ' ) as receipt ";
		if( $warehouse ) $field.= ", iv.qty - iv.allocated_qty AS stock_qty ";
		$field.= ", SUM( s.qty ) AS fulfil_qty, SUM( s.line_total ) AS fulfil_amt, b.bqty - SUM( IFNULL(s.qty,0) ) AS bal_qty ";
		
		$table = "{$dbname}{$this->tables['document']} a ";	
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d ON d.doc_id = a.doc_id AND d.item_id = 0 AND d.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} a2 ON a2.doc_id = a.doc_id AND a2.item_id = 0 AND a2.meta_key = 'remark' ";		
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} e ON e.id = d.meta_value ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b1 ON b1.doc_id = b.doc_id AND b1.item_id = b.item_id AND b1.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b2 ON b2.doc_id = b.doc_id AND b2.item_id = b.item_id AND b2.meta_key = 'sale_amt' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b3 ON b3.doc_id = b.doc_id AND b3.item_id = b.item_id AND b3.meta_key = 'period' ";	
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} f ON f.id = b.product_id ";	
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_group']} g ON g.id = f.grp_id ";

		if( isset( $filters['from_date'] ) )
		{
			$subcond1 = $wpdb->prepare( "AND o.post_date >= %s ", $filters['from_date'] );
		}
		$subsql1 = "SELECT o.post_date, o1.meta_value AS tr_id, o2.meta_value AS receipt, o4.meta_value AS customer_code
			, m.meta_value AS item_id, n.meta_value AS qty, p.meta_value AS line_total
			FROM {$dbname}{$wpdb->posts} o 
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o1 on o1.post_id = o.ID AND o1.meta_key = 'tool_request_id'
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o2 on o2.post_id = o.ID AND o2.meta_key = '_order_number'
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o3 on o3.post_id = o.ID AND o3.meta_key = '_order_total'
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o4 on o4.post_id = o.ID AND o4.meta_key = '_customer_code'
			LEFT JOIN {$dbname}{$this->tables['order_items']} l ON l.order_id = o.ID AND l.order_item_type = 'line_item' 
			LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = l.order_item_id AND m.meta_key = '_items_id'
			LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} n ON n.order_item_id = l.order_item_id AND n.meta_key = '_qty'
			LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} p ON p.order_item_id = l.order_item_id AND p.meta_key = '_line_total'
			WHERE 1 AND o.post_type = 'shop_order' AND o.post_status IN ( 'wc-processing', 'wc-completed' ) AND o3.meta_value IS NOT NULL
			AND o1.meta_value > 0 {$subcond1}
		";

		if( isset( $filters['from_date'] ) )
		{
			$subcond2 = $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
		}
		$subsql2 = "SELECT h.post_date, th.doc_id AS tr_id, h.docno AS receipt, th1.meta_value AS customer_code
			, d.product_id AS item_id, d.bqty AS qty, d1.meta_value AS line_total
			FROM {$dbname}{$this->tables['document']} h 
			LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'ref_doc_type'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h2 ON h2.doc_id = h.doc_id AND h2.item_id = 0 AND h2.meta_key = 'ref_doc_id'
			LEFT JOIN {$dbname}{$this->tables['document']} th ON th.doc_id = h2.meta_value
			LEFT JOIN {$dbname}{$this->tables['document_meta']} th1 ON th1.doc_id = th.doc_id AND th1.item_id = 0 AND th1.meta_key = 'customer_code'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} d1 ON d1.doc_id = d.doc_id AND d1.item_id = d.item_id AND d1.meta_key = 'line_total'
			WHERE 1 AND h.doc_type = 'sale_order' AND h.status >= 6 AND h1.meta_value = 'tool_request'
			AND th.doc_type = 'tool_request' AND th.status >= 6 {$subcond2}
		";

		$subsql = "( {$subsql1} ) UNION ( {$subsql2} ) ";

		$table.= "LEFT JOIN ( {$subsql} ) s ON s.tr_id = a.doc_id AND s.item_id = b.product_id ";

		if( $warehouse )
		{
			$table.= "LEFT JOIN {$dbname}{$this->tables['storage']} strg ON strg.wh_code = '{$warehouse['code']}' AND strg.sys_reserved = 'staging' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['inventory']} iv ON iv.warehouse_id = '{$warehouse['code']}' AND iv.strg_id = strg.id AND iv.prdt_id = f.id ";
		}
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status > %d ", 'tool_request',1 );
		
		if( isset( $filters['from_date'] ) && ! ( isset( $filters['fulfilment'] ) && $filters['fulfilment'] == 'all_pending' ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
		}
		
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND e.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND e.id = %s ", $filters['customer'] );
		}
		if( isset( $filters['product_id'] ) )
		{
			if( is_array( $filters['product_id'] ) )
				$cond.= "AND b.product_id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND b.product_id = %s ", $filters['product_id'] );
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
				$cd[] = "e.name LIKE '%".$kw."%' ";
				$cd[] = "e.code LIKE '%".$kw."%' ";
				$cd[] = "f.name LIKE '%".$kw."%' ";
				
                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY a.doc_id, b.product_id ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.doc_date'=>'DESC', 'a.docno'=>'DESC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$main_sql = $sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( isset( $filters['fulfilment'] ) )
		{
			/*$field = "a.docno, a.date, a.customer, a.customer_code, a.remark, a.item_group, a.item_code, a.item_name ";
			$field.= ", a.uom, a.instalment, a.quantity, a.sprice, a.sale_amt, a.receipt ";
			if( $warehouse ) $field.= ", a.stock_qty ";
			$field.= ", a.fulfil_qty, a.fulfil_amt, a.bal_qty ";*/

			$cond = "";
			if( $filters['fulfilment'] == 'done' )
			{
				$cond.= "AND a.bal_qty <= 0 ";
			}
			else if( in_array( $filters['fulfilment'], [ 'pending', 'all_pending' ] ) )
			{
				$cond.= "AND a.bal_qty > 0 ";
			}

			$sql = "SELECT a.* FROM ( {$main_sql} ) a WHERE 1 {$cond} ";
		}

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_tool_request_summary_report( $filters = [], $order = [], $args = [] )
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
		
		$field = " a.docno, DATE_FORMAT( a.doc_date, '%Y-%m-%d' ) AS doc_date, e.name as 'customer', e.code as 'customer_code', d2.meta_value AS remark ";
		$field .= ", s.receipt, s.post_date AS fulfil_date, s.total_amount";
		
		
		$table = "{$dbname}{$this->tables['document']}  a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d ON d.doc_id = a.doc_id AND d.item_id = 0 AND d.meta_key = 'customer_id'";	
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d2 ON d2.doc_id = a.doc_id AND d2.item_id = 0 AND d2.meta_key = 'remark'";		
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']}  e ON e.id = d.meta_value ";	

		if( isset( $filters['from_date'] ) )
		{
			$subcond1 = $wpdb->prepare( "AND o.post_date >= %s ", $filters['from_date'] );
		}
		$subsql1 = "SELECT o.post_date, o1.meta_value AS tr_id, o2.meta_value AS receipt, o3.meta_value AS total_amount
			FROM {$dbname}{$wpdb->posts} o 
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o1 on o1.post_id = o.ID AND o1.meta_key = 'tool_request_id'
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o2 on o2.post_id = o.ID AND o2.meta_key = '_order_number'
			LEFT JOIN {$dbname}{$this->tables['postmeta']} o3 on o3.post_id = o.ID AND o3.meta_key = '_order_total'
			WHERE 1 AND o.post_type = 'shop_order' AND o.post_status IN ( 'wc-processing', 'wc-completed' ) AND o3.meta_value IS NOT NULL
			AND o1.meta_value > 0 {$subcond1}
		";	

		if( isset( $filters['from_date'] ) )
		{
			$subcond2 = $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
		}
		$subsql2 = "SELECT h.post_date, th.doc_id AS tr_id, h.docno AS receipt, h3.meta_value AS total_amount
			FROM {$dbname}{$this->tables['document']} h 
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'ref_doc_type'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h2 ON h2.doc_id = h.doc_id AND h2.item_id = 0 AND h2.meta_key = 'ref_doc_id'
			LEFT JOIN {$dbname}{$this->tables['document_meta']} h3 ON h3.doc_id = h.doc_id AND h3.item_id = 0 AND h3.meta_key = 'total'
			LEFT JOIN {$dbname}{$this->tables['document']} th ON th.doc_id = h2.meta_value
			WHERE 1 AND h.doc_type = 'sale_order' AND h.status >= 6 AND h1.meta_value = 'tool_request'
			AND th.doc_type = 'tool_request' AND th.status >= 6 {$subcond2}
		";

		$subsql = "( {$subsql1} ) UNION ( {$subsql2} ) ";

		$table.= "LEFT JOIN ( {$subsql} ) s ON s.tr_id = a.doc_id ";
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status > %d ", 'tool_request',1 );
		
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
		}
		
		if( isset( $filters['customer'] ) )
		{
			if( is_array( $filters['customer'] ) )
				$cond.= "AND e.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND e.id = %s ", $filters['customer'] );
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
				$cd[] = "e.name LIKE '%".$kw."%' ";
				$cd[] = "e.code LIKE '%".$kw."%' ";
				
                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.doc_date'=>'DESC', 'a.docno'=>'DESC'];
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