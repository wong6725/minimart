<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_POSSales_Rpt" ) ) 
{
	
class WCWH_POSSales_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "POSSales";

	public $tplName = array(
		'export' => 'exportPOSSales',
		'print' => 'printPOSSales',
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
			
			"pos_reg"		=> $wpdb->prefix."wc_poin_of_sale_registers",
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
						case 'summary':
							$datas['filename'] = 'POS Summary ';
						break;
						case 'sales':
							$datas['filename'] = 'POS Sales ';
						break;
						case 'details':
							$datas['filename'] = 'POS Sales Details ';
						break;
						case 'category':
							$datas['filename'] = 'POS Sales Category ';
						break;
						case 'items':
							$datas['filename'] = 'POS Sales Items ';
						break;
					}
					
					$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					if( $datas['from_date'] ) $datas['filename'].= date( $date_format, strtotime( $datas['from_date'] ) );
					if( $datas['to_date'] )  $datas['filename'].= " - ".date( $date_format, strtotime( $datas['to_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					if( !empty( $datas['payment_method'] ) ) $params['payment_method'] = $datas['payment_method'];
					if( !empty( $datas['order_status'] ) ) $params['order_status'] = $datas['order_status'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;

				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					if( !empty( $datas['order_status'] ) ) $params['order_status'] = $datas['order_status'];
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
		
		switch( $type )
		{
			case 'summary':
				return $this->get_pos_summary_report( $params );
			break;
			case 'sales':
				return $this->get_pos_sales_report( $params );
			break;
			case 'details':
				return $this->get_pos_sales_detail_report( $params );
			break;
			case 'category':
				return $this->get_pos_category_sales_report( $params );
			break;
			case 'items':
				return $this->get_pos_item_sales_report( $params );
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
				$filename = "POS Sales Summary";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'POS Sales Summary';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'POS Sales Summary';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Date' => [ 'width'=>'35%', 'class'=>['leftered'] ],
					'Transactions' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					//'Paid('.$currency.')' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					//'Change('.$currency.')' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Cash' => [ 'width'=>'15%', 'class'=>['rightered'] ],
					'Others' => [ 'width'=>'15%', 'class'=>['rightered'] ],
					'Credit' => [ 'width'=>'15%', 'class'=>['rightered'] ],
					'Order Total' => [ 'width'=>'25%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$total_order = 0; 
					$total_cash = 0; 
					$total_other = 0;
					$total_paid = 0; 
					$total_change = 0;
					$total_credit = 0; 
					$total_transactions = 0;
					$details = [];
					foreach( $datas as $i => $data )
					{
						$row = [

'date' => [ 'value'=>$data['date'], 'class'=>['leftered'] ],
'transactions' => [ 'value'=>$data['transactions'], 'class'=>['rightered'], 'num' => 1 ],
//'amt_paid' => [ 'value'=>$data['amt_paid'], 'class'=>['rightered'], 'num' => 1 ],
//'amt_change' => [ 'value'=>$data['amt_change'], 'class'=>['rightered'], 'num' => 1 ],
'amt_cash' => [ 'value'=>$data['amt_cash'], 'class'=>['rightered'], 'num' => 1 ],
'amt_other' => [ 'value'=>$data['amt_other'], 'class'=>['rightered'], 'num' => 1 ],
'amt_credit' => [ 'value'=>$data['amt_credit'], 'class'=>['rightered'], 'num' => 1 ],
'total' => [ 'value'=>$data['total'], 'class'=>['rightered'], 'num' => 1 ],

						];

						$details[] = $row;

						//totals
						$total_transactions += $data['transactions'];
						$total_order += $data['total'];
						$total_cash += $data['amt_cash'];
						$total_other += $data['amt_other'];
						$total_change += $data['amt_change'];
						$total_credit += $data['amt_credit'];
						$total_paid += $data['amt_paid'];
					}

					$details[] = [

						'date' => [ 'value' => 'TOTAL:', 'class' => ['leftered', 'bold']  ],
						'transactions' => [ 'value'=>$total_transactions, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						//'amt_paid' => [ 'value'=>$total_paid, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						//'amt_change' => [ 'value'=>$total_change, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'amt_cash' => [ 'value'=>$total_cash, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'amt_other' => [ 'value'=>$total_other, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'amt_credit' => [ 'value'=>$total_credit, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'total' => [ 'value'=>$total_order, 'class'=>['rightered', 'bold'], 'num' => 1 ],

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

			case 'sales':
				$filename = "POS Sales";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'POS Sales';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'POS Sales';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
				
				$document['detail_title'] = [
					'Order No.' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Employee ID' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Customer' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Credit' => [ 'width'=>'15%', 'class'=>['rightered'] ],
					'Amount' => [ 'width'=>'15%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$total_order = 0; 
					$total_credit = 0; 
					$details = [];
					foreach( $datas as $i => $data )
					{

						$customer = [];
						if( $data['receipt_cus_serial'] ) $customer[] = $data['receipt_cus_serial'];
						if( $data['customer_name'] ) $customer[] = $data['customer_name'];		

						$data['customer_info'] = implode( ' - ', $customer );

						$row = [

'order_no' => [ 'value'=>$data['order_no'], 'class'=>['leftered'] ],
'date' => [ 'value'=>date_i18n( $date_format, strtotime( $data['date'] ) ), 'class'=>['leftered'] ],
'employee_id' => [ 'value'=>$data['employee_id'], 'class'=>['leftered'] ],
'customer_info' => [ 'value'=>$data['customer_info'], 'class'=>['leftered'] ],
'amt_credit' => [ 'value'=>$data['amt_credit'], 'class'=>['rightered'], 'num' => 1 ],
'order_total' => [ 'value'=>$data['order_total'], 'class'=>['rightered'], 'num' => 1 ],

						];

						$details[] = $row;

						//totals
						$total_order += $data['order_total'];
						$total_credit += $data['amt_credit'];
					}

					$details[] = [

						'order_no' => [ 'value' => 'TOTAL:', 'class' => ['leftered', 'bold'], 'colspan' => 4  ],
						'amt_credit' => [ 'value'=>$total_credit, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						'order_total' => [ 'value'=>$total_order, 'class'=>['rightered', 'bold'], 'num' => 1 ],

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
				$filename = "POS Sales Details";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'POS Sales Details';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'POS Sales Details';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
				
				$document['detail_title'] = [
					'Order No.' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Customer' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Item' => [ 'width'=>'30%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'5%', 'class'=>['rightered'] ],
					'UOM' => [ 'width'=>'5%', 'class'=>['centered'] ],
					'Price' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Amount' => [ 'width'=>'10%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$customer = [];
						$order_item_info = [];
						if( $data['employee_id'] ) $customer[] = $data['employee_id'];
						if( $data['receipt_cus_serial'] ) $customer[] = $data['receipt_cus_serial'];
						if( $data['customer_name'] ) $customer[] = $data['customer_name'];		

						$data['customer_info'] = implode( ', ', $customer );

						if( $data['item_name'] ) $order_item_info[] = $data['item_name'];
						if( $data['item_code'] ) $order_item_info[] = $data['item_code'];	

						$data['item_info'] = implode( ', ', $order_item_info );

						$regrouped[ $data['order_no'] ][ $data['order_item_id'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['order_no'] ] += 1;

						//totals
						$totals[ $data['order_no'] ][ $data['order_item_id'] ] += $data['line_total'];
					}
					//pd( $totals );
					$details = [];
					if( $regrouped )
					{
						$total = 0;
						foreach( $regrouped as $orders => $order )
						{
							$subtotal = 0;
							$order_added = '';
							foreach( $order as $order_items => $items )
							{
								if( $totals[ $orders ][ $order_items ] )
								{
									$rowspan[ $orders ] += count( $totals[ $orders ] ) + 1;
								}

								foreach( $items as $i => $vals )
								{

									$row = [

'order_no' => [ 'value'=>$vals['order_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $orders ] ],
'date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['date'] ) ), 'class'=>['leftered'], 'rowspan' => $rowspan[ $orders ] ],
'customer_info' => [ 'value'=>$vals['customer_info'], 'class'=>['leftered'], 'rowspan' => $rowspan[ $orders ] ],
'item_info' => [ 'value'=>$vals['item_info'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'] ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'price' => [ 'value'=>$vals['price'], 'class'=>['rightered'], 'num' => 1 ],
'line_total' => [ 'value'=>$vals['line_total'], 'class'=>['rightered'], 'num' => 1 ],

									];

									if( $order_added == $orders )
									{
										$row['customer_info'] = [];
										$row['order_no'] = [];
										$row['date'] = [];
									} 
									$order_added = $orders;

									$subtotal += $totals[ $orders ][ $order_items ];

									$details[] = $row;
								}
							}

							$details[] = [
								'order_no' => [],
								'date' => [],
								'customer_info' => [],
								'item_info' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>4 ],
								'line_total' => [ 'value'=>$subtotal, 'class'=>['rightered','bold'], 'num' => 1 ],
							];

							$total += $subtotal;
						}

						$details[] = [
							'order_no' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>7 ],
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

			case 'category':
				$filename = "POS Sales Category";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'POS Sales Category';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'POS Sales Category';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
				
				$document['detail_title'] = [
					'Category' => [ 'width'=>'50%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Avg Price' => [ 'width'=>'20%', 'class'=>['rightered'] ],
					'Amount' => [ 'width'=>'20%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$total_qty = 0; 
					$total_amount = 0; 
					$details = [];
					foreach( $datas as $i => $data )
					{

						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category'] ) $category[] = $data['category'];		

						$data['category_info'] = implode( ' - ', $category );

						$row = [

'category_info' => [ 'value'=>$data['category_info'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$data['qty'], 'class'=>['rightered'] ],
'avg_price' => [ 'value'=>$data['avg_price'], 'class'=>['rightered'], 'num' => 1 ],
'line_total' => [ 'value'=>$data['line_total'], 'class'=>['rightered'], 'num' => 1 ],

						];

						$details[] = $row;

						//totals
						$total_qty += $data['qty'];
						$total_amount += $data['line_total'];
					}

					$details[] = [

						'category_info' => [ 'value' => 'TOTAL:', 'class' => ['leftered', 'bold']  ],
						'qty' => [ 'value'=>$total_qty, 'class'=>['rightered', 'bold'] ],
						'avg_price' => [],
						'line_total' => [ 'value'=>$total_amount, 'class'=>['rightered', 'bold'], 'num' => 1 ],

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

			case 'items':
				$filename = "POS Sales Items";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'POS Sales Items';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'POS Sales Items';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
				
				$document['detail_title'] = [
					'Item' => [ 'width'=>'30%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'30%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'8%', 'class'=>['rightered'] ],
					'UOM' => [ 'width'=>'8%', 'class'=>['centered'] ],
					'Avg Price' => [ 'width'=>'12%', 'class'=>['rightered'] ],
					'Amount' => [ 'width'=>'12%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$total_qty = 0; 
					$total_amount = 0; 
					$details = [];
					foreach( $datas as $i => $data )
					{
						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category'] ) $category[] = $data['category'];		

						$data['category_info'] = implode( ' - ', $category );

						$item_info = [];
						if( $data['item_code'] ) $item_info[] = $data['item_code'];
						if( $data['item_name'] ) $item_info[] = $data['item_name'];		

						$data['item_info'] = implode( ' - ', $item_info );

						$row = [

'item_info' => [ 'value'=>$data['item_info'], 'class'=>['leftered'] ],
'category' => [ 'value'=>$data['category_info'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$data['qty'], 'class'=>['rightered'] ],
'uom' => [ 'value'=>$data['uom'], 'class'=>['centered'] ],
'avg_price' => [ 'value'=>$data['avg_price'], 'class'=>['rightered'], 'num' => 1 ],
'line_total' => [ 'value'=>$data['line_total'], 'class'=>['rightered'], 'num' => 1 ],

						];

						$details[] = $row;

						//totals
						$total_qty += $data['qty'];
						$total_amount += $data['line_total'];
					}

					$details[] = [

						'item_info' => [ 'value' => 'TOTAL:', 'class' => ['leftered', 'bold'], 'colspan' => 2 ],
						'qty' => [ 'value'=>$total_qty, 'class'=>['rightered', 'bold'] ],
						'uom' => [],
						'avg_price' => [],
						'line_total' => [ 'value'=>$total_amount, 'class'=>['rightered', 'bold'], 'num' => 1 ],

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
		$action_id = 'pos_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'summary':
				do_action( 'wcwh_templating', 'report/export-pos_sales-summary-report.php', $this->tplName['export'], $args );
			break;
			case 'sales':
				do_action( 'wcwh_templating', 'report/export-pos_sales-report.php', $this->tplName['export'], $args );
			break;
			case 'details':
				do_action( 'wcwh_templating', 'report/export-pos_sales-details-report.php', $this->tplName['export'], $args );
			break;
			case 'category':
				do_action( 'wcwh_templating', 'report/export-pos_sales-category-report.php', $this->tplName['export'], $args );
			break;
			case 'items':
				do_action( 'wcwh_templating', 'report/export-pos_sales-item-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'pos_report_export';
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
				do_action( 'wcwh_templating', 'report/export-pos_sales-summary-report.php', $this->tplName['print'], $args );
			break;
			case 'sales':
				do_action( 'wcwh_templating', 'report/export-pos_sales-report.php', $this->tplName['print'], $args );
			break;
			case 'details':
				do_action( 'wcwh_templating', 'report/export-pos_sales-details-report.php', $this->tplName['print'], $args );
			break;
			case 'category':
				do_action( 'wcwh_templating', 'report/export-pos_sales-category-report.php', $this->tplName['print'], $args );
			break;
			case 'items':
				do_action( 'wcwh_templating', 'report/export-pos_sales-item-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	Summary
	 */
	public function pos_summary_report( $filters = array(), $order = array() )
	{
		$action_id = 'pos_summary_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/posSummaryList.php" ); 
			$Inst = new WCWH_POSSummary_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 100;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.transactions, .amt_paid, .amt_change, .amt_cash, .amt_other, .amt_credit, .total' => [ 'text-align'=>'right !important' ],
				'#transactions a span, #amt_cash a span, #amt_other a span, #amt_credit a span, #total a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_pos_summary_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Sales
	 */
	public function pos_sales_report( $filters = array(), $order = array() )
	{
		$action_id = 'pos_sales_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/posSalesList.php" ); 
			$Inst = new WCWH_POSSales_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-d' );
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
				'.amt_paid, .amt_change, .amt_cash, .amt_credit, .order_total' => [ 'text-align'=>'right !important' ],
				'#amt_paid a span, #amt_change a span, #amt_cash a span, #amt_credit a span, #order_total a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_pos_sales_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
		public function pos_sales_report_detail( $id = 0, $filters = array() )
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
				
				$datas = $this->get_pos_sales_report_detail( $filters );
				if( $datas )
				{
					$header_column = array ( 'order_id', 'receipt_no', 'date', 'customer', 'customer_code', 'employee_id', 'customer_serial', 
						'payment_method', 'payment_method_title', 'cgroup_name', 'cgroup_code', 'order_total', 'credit_amount', 'cash_paid', 'cash_change' );
					$detail_column = array ( 'item_no', 'item_name', 'sku', 'serial', 'uom', 'qty', 'price', 'line_total' );
					$result_data = $this->seperate_import_data( $datas , $header_column , [ 'order_id' ] , $detail_column );
					
					do_action( 'wcwh_get_template', 'report/pos_sales_report_detail.php', $result_data );
				}
			}
		}
	
	/**
	 *	Sales Details
	 */
	public function pos_sales_detail_report( $filters = array(), $order = array() )
	{
		$action_id = 'pos_sales_detail_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/posSalesDetailList.php" ); 
			$Inst = new WCWH_POSSalesDetail_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-d' );
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
				'#order_no, #status' => [ 'width' => '7%' ],
				'#customer' => [ 'width' => '10%' ],
				'#item_name' => [ 'width' => '15%' ],
				'#category' => [ 'width' => '12%' ],
				'.order_total, .item_no, .qty, .weight, .price, .line_total' => [ 'text-align'=>'right !important' ],
				'#order_total a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_pos_sales_detail_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Category Sales
	 */
	public function pos_category_sales_report( $filters = array(), $order = array() )
	{
		$action_id = 'pos_category_sales_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/posCategorySalesList.php" ); 
			$Inst = new WCWH_POSCategorySales_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-d' );
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
				'.qty, .weight, .avg_price, .line_total' => [ 'text-align'=>'right !important' ],
				'#qty a span, #weight a span, #line_total a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_pos_category_sales_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Item Sales
	 */
	public function pos_item_sales_report( $filters = array(), $order = array() )
	{
		$action_id = 'pos_item_sales_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/posItemSalesList.php" ); 
			$Inst = new WCWH_POSItemSales_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-d' );
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
				'#category' => [ 'width'=>'10%' ],
				'#item_name' => [ 'width'=>'22%' ],
				'.qty, .weight, .avg_price, .line_total' => [ 'text-align'=>'right !important' ],
				'#qty a span, #weight a span, #line_total a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_pos_item_sales_report( $filters, $order, [] );
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
	public function get_pos_summary_report( $filters = [], $order = [], $args = [] )
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

		$field = "DATE_FORMAT( a.post_date, '%Y-%m-%d' ) AS date, COUNT( a.ID ) AS transactions, ROUND( SUM( f.meta_value ), 2 ) AS amt_paid ";
		$field.= ", ROUND( SUM( g.meta_value ), 2 ) AS amt_change ";
		$field.= ", ROUND( SUM( IF( LOWER(c.meta_value) = 'cod', f.meta_value - g.meta_value, 0 ) ), 2 ) AS amt_cash ";
		$field.= ", ROUND( SUM( IF( LOWER(c.meta_value) = 'cod', 0, f.meta_value - g.meta_value ) ), 2 ) AS amt_other ";
		$field.= ", ROUND( SUM( h.meta_value ), 2 ) AS amt_credit, ROUND( SUM( b.meta_value ), 2 ) AS total ";
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_payment_method' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = 'wc_pos_id_register' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = '_pos_session_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_amount_pay' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = 'wc_pos_amount_change' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} h ON h.post_id = a.ID AND h.meta_key = 'wc_pos_credit_amount' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} i ON i.post_id = a.ID AND i.meta_key = '_payment_method_title' ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value IS NOT NULL ", 'shop_order' );

		if( isset( $filters['order_status'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_status = %s ", $filters['order_status'] );
		}
		else
		{
			$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
		}
		
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['payment_method'] ) )
		{
			$cond.= $wpdb->prepare( "AND c.meta_value = %s ", $filters['payment_method'] );
		}
		if( isset( $filters['register'] ) )
		{
			$cond.= $wpdb->prepare( "AND d.meta_value = %s ", $filters['register'] );
		}
		if( isset( $filters['session'] ) )
		{
			$cond.= $wpdb->prepare( "AND e.meta_value = %s ", $filters['session'] );
		}
		
		$grp = "GROUP BY date ";

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

	public function get_pos_sales_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "a.ID AS id, b.meta_value AS order_no, a.post_date AS date, a.post_status AS status ";
		$field.= ", fa.name AS register, g.meta_value AS session, maa.meta_value AS cashier ";
		$field.= ", d.meta_value AS payment_method, e.meta_value AS payment_method_title ";
		$field.= ", l.name AS customer_name, l.uid AS employee_id, l.serial AS customer_code, ka.meta_value AS receipt_cus_serial ";
		$field.= ", ROUND( h.meta_value, 2 ) AS amt_paid, ROUND( i.meta_value, 2 ) AS amt_change, ROUND( h.meta_value - i.meta_value, 2 ) AS amt_cash ";
		$field.= ", ROUND( j.meta_value, 2 ) AS amt_credit, ROUND( c.meta_value, 2 ) AS order_total ";
		
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_number' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = '_payment_method' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = '_payment_method_title' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_id_register' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = '_pos_session_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} h ON h.post_id = a.ID AND h.meta_key = 'wc_pos_amount_pay' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} i ON i.post_id = a.ID AND i.meta_key = 'wc_pos_amount_change' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} j ON j.post_id = a.ID AND j.meta_key = 'wc_pos_credit_amount' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} k ON k.post_id = a.ID AND k.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} ka ON ka.post_id = a.ID AND ka.meta_key = '_customer_serial' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} l ON l.id = k.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} m ON m.post_id = a.ID AND m.meta_key = 'wc_pos_served_by' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['wp_user']} ma ON ma.ID = m.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['wp_usermeta']} maa ON maa.user_id = ma.ID AND maa.meta_key = 'first_name' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['pos_reg']} fa ON fa.ID = f.meta_value ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND c.meta_value IS NOT NULL ", 'shop_order' );

		if( isset( $filters['order_status'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_status = %s ", $filters['order_status'] );
		}
		else
		{
			$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
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
				$cond.= $wpdb->prepare( "AND ( k.meta_value IS NULL OR k.meta_value = %s ) ", '0' );
			else
				$cond.= $wpdb->prepare( "AND l.id = %s ", $filters['customer'] );
		}
		if( isset( $filters['payment_method'] ) )
		{
			$cond.= $wpdb->prepare( "AND d.meta_value = %s ", $filters['payment_method'] );
		}
		if( isset( $filters['register'] ) )
		{
			$cond.= $wpdb->prepare( "AND f.meta_value = %s ", $filters['register'] );
		}
		if( isset( $filters['session'] ) )
		{
			$cond.= $wpdb->prepare( "AND g.meta_value = %s ", $filters['session'] );
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
                $cd[] = "l.name LIKE '%".$kw."%' ";
				$cd[] = "l.uid LIKE '%".$kw."%' ";
				$cd[] = "l.code LIKE '%".$kw."%' ";
				$cd[] = "l.serial LIKE '%".$kw."%' ";
				$cd[] = "b.meta_value LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'b.meta_value' => 'ASC', 'a.post_date' => 'ASC' ];
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
		public function get_pos_sales_report_detail( $filters = [], $order = [], $args = [] )
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
			
			$field = "a.id AS order_id, g.meta_value AS receipt_no, a.post_date AS date, h.name AS customer, h.serial AS customer_code, h.uid AS employee_id ";
			$field.= ", r.meta_value AS customer_serial, n.meta_value AS payment_method, o.meta_value AS payment_method_title ";
			$field.= ", cgroup.name AS cgroup_name, cgroup.code AS cgroup_code ";
			$field.= ", c.meta_value AS order_total, d.meta_value AS credit_amount, e.meta_value AS cash_paid, f.meta_value AS cash_change ";
			$field.= ", q.meta_value AS item_no, i.order_item_name AS item_name, p._sku AS sku, p.serial ";
			$field.= ", IFNULL( s.meta_value, p._uom_code ) AS uom, k.meta_value AS qty, l.meta_value AS price, m.meta_value AS line_total ";
			$table = "{$dbname}{$wpdb->posts} a ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_order_total' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = 'wc_pos_credit_amount' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = 'wc_pos_amount_pay' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_amount_change' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = '_order_number' ";
			
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} n ON n.post_id = a.ID AND n.meta_key = '_payment_method' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} o ON o.post_id = a.ID AND o.meta_key = '_payment_method_title' ";
			
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} h ON h.id = b.meta_value ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} s ON s.order_item_id = i.order_item_id AND s.meta_key = '_uom' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_price' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} q ON q.order_item_id = i.order_item_id AND q.meta_key = '_item_number' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} p ON p.id = j.meta_value ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} r ON r.post_id = a.ID AND r.meta_key = '_customer_serial' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} cgroup ON cgroup.id = h.cgroup_id ";
			
			$cond = $wpdb->prepare( "AND a.post_type = %s AND c.meta_value IS NOT NULL ", 'shop_order' );
			
			$ord = "";
			
			if( isset( $filters['id'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.id = %s ", $filters['id'] );
			}
			
			$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
			$results = $wpdb->get_results( $sql , ARRAY_A );
			
			return $results;
		}
	
	public function get_pos_sales_detail_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "a.ID AS order_id, i.order_item_id, g.meta_value AS order_no, a.post_date AS date, a.post_status AS status ";
		$field.= ", oaa.name AS register, obb.meta_value AS cashier ";
		$field.= ", h.name AS customer_name, h.uid AS employee_id, h.serial AS customer_code, ba.meta_value AS receipt_cus_serial ";
		//$field.= ", ROUND( e.meta_value - f.meta_value, 2 ) AS amt_cash, ROUND( d.meta_value, 2 ) AS credit_amount ";
		$field.= ", ROUND( c.meta_value, 2 ) AS order_total ";
		$field.= ", CAST( q.meta_value AS UNSIGNED ) AS item_no, i.order_item_name AS item_name, p._sku AS sku, p.code AS item_code, p.serial ";
		$field.= ", IFNULL( n.meta_value, p._uom_code ) AS uom, s.name AS category, s.slug AS category_code ";
		$field.= ", k.meta_value AS qty, ROUND( k.meta_value * r.meta_value, 2 ) AS weight ";
		$field.= ", ROUND( l.meta_value, 2 ) AS price, ROUND( m.meta_value, 2 ) AS line_total ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} ba ON ba.post_id = a.ID AND ba.meta_key = '_customer_serial' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_order_total' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = 'wc_pos_credit_amount' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = 'wc_pos_amount_pay' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_amount_change' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = '_order_number' ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} h ON h.id = b.meta_value ";
		
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} oa ON oa.post_id = a.ID AND oa.meta_key = 'wc_pos_id_register' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} ob ON ob.post_id = a.ID AND ob.meta_key = 'wc_pos_served_by' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['wp_user']} oba ON oba.ID = ob.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['wp_usermeta']} obb ON obb.user_id = ob.meta_value AND obb.meta_key = 'first_name' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['pos_reg']} oaa ON oaa.ID = oa.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} q ON q.order_item_id = i.order_item_id AND q.meta_key = '_item_number' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} r ON r.order_item_id = i.order_item_id AND r.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} n ON n.order_item_id = i.order_item_id AND n.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} p ON p.id = j.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} s ON s.term_id = p.category ";
		
		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = s.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND c.meta_value IS NOT NULL ", 'shop_order' );
		
		if( isset( $filters['order_status'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_status = %s ", $filters['order_status'] );
		}
		else
		{
			$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
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
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.post_date' => 'ASC', 'item_no' => 'ASC' ];
		} 
		if( empty( $order['item_no'] ) ) $order['item_no'] = 'ASC';
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

        if( !empty( $args['xtra_field'] ) ) $field.= $args['xtra_field'];
        if( !empty( $args['xtra_table'] ) ) $table.= $args['xtra_table'];
        if( !empty( $args['xtra_cond'] ) ) $cond.= $args['xtra_cond'];

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( isset( $args['get_sql'] ) ) return $sql;

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_pos_category_sales_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "j.name AS category, j.slug AS category_code ";
		$field.= ", SUM( e.meta_value ) AS qty, ROUND( SUM( h.meta_value * e.meta_value ), 2 ) AS weight ";
		$field.= ", ROUND( SUM( g.meta_value ) / SUM( e.meta_value ), 5 ) AS avg_price, ROUND( SUM( g.meta_value ), 2 ) AS line_total ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = c.order_item_id AND l.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} j ON j.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = j.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} k ON k.post_id = a.ID AND k.meta_key = 'customer_id' ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value IS NOT NULL ", 'shop_order' );
		$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
		
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
				$cond.= $wpdb->prepare( "AND ( k.meta_value IS NULL OR k.meta_value = %s ) ", '0' );
			else
				$cond.= $wpdb->prepare( "AND k.meta_value = %s ", $filters['customer'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR j.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR j.term_id = %d ", $filters['category'] );
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
                $cd[] = "j.name LIKE '%".$kw."%' ";
				$cd[] = "j.slug LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "GROUP BY j.slug ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'j.slug' => 'ASC' ];
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

	public function get_pos_item_sales_report( $filters = [], $order = [], $args = [] )
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
		
		$field = "j.name AS category, j.slug AS category_code ";
		$field.= ", c.order_item_name AS item_name, i._sku AS sku, i.code AS item_code, i.serial, i._uom_code AS uom ";
		$field.= ", SUM( e.meta_value ) AS qty, ROUND( SUM( h.meta_value * e.meta_value ), 3 ) AS weight ";
		$field.= ", ROUND( SUM( g.meta_value ) / SUM( e.meta_value ), 5 ) AS avg_price, ROUND( SUM( g.meta_value ), 2 ) AS line_total ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = c.order_item_id AND l.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} j ON j.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = j.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} k ON k.post_id = a.ID AND k.meta_key = 'customer_id' ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value IS NOT NULL ", 'shop_order' );
		$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
		
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
				$cond.= $wpdb->prepare( "AND ( k.meta_value IS NULL OR k.meta_value = %s ) ", '0' );
			else
				$cond.= $wpdb->prepare( "AND k.meta_value = %s ", $filters['customer'] );
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.id = %d ", $filters['product'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR j.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR j.term_id = %d ", $filters['category'] );
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
                $cd[] = "i.name LIKE '%".$kw."%' ";
				$cd[] = "i._sku LIKE '%".$kw."%' ";
				$cd[] = "i.code LIKE '%".$kw."%' ";
				$cd[] = "i.serial LIKE '%".$kw."%' ";
				$cd[] = "j.name LIKE '%".$kw."%' ";
				$cd[] = "j.slug LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "GROUP BY i.code ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'i.code' => 'ASC' ];
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