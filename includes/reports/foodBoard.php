<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_FoodBoard_Rpt" ) ) 
{
	
class WCWH_FoodBoard_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "FoodBoard";

	public $tplName = array(
		'export' => 'exportFoodBoard',
		'print' => 'printFoodBoard',
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
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
			"status"		=> $prefix."status",
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"customer" 		=> $prefix."customer",
			"customertree"	=> $prefix."customer_tree",
			"customermeta"	=> $prefix."customermeta",

			"client"		=> $prefix."client",
			"clientmeta"	=> $prefix."clientmeta",
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
						case 'category':
							$datas['filename'] = 'Foodboard Category ';
						break;
						case 'detail':
							$datas['filename'] = 'Foodboard ';
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
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['grouping'] ) ) $params['grouping'] = $datas['grouping'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['grouping'] ) ) $params['grouping'] = $datas['grouping'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];

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
			case 'category':
				return $this->get_foodboard_category_report( $params );
			break;
			case 'detail':
			default:
				return $this->get_foodboard_detail_report( $params );
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
			case 'category':
				$filename = "FoodBoard-Category";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'FoodBoard Category';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'FB Category';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Customer' => [ 'width'=>'40%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'40%', 'class'=>['leftered'] ],
					'Total ('.$currency.')' => [ 'width'=>'20%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];
						$data['category_name'] = implode( ' - ', $category );

						$customer = [];
						if( $data['customer_code'] ) $customer[] = $data['customer_code'];
						if( $data['customer_name'] ) $customer[] = $data['customer_name'];
						$data['customer_name'] = implode( ' - ', $customer );

						$regrouped[ $data['customer_code'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['customer_code'] ]+= 1;

						//totals
						$totals[ $data['customer_code'] ]+= $data['amount'];
					}

					$details = [];
					if( $regrouped )
					{
						$total = 0;
						foreach( $regrouped as $order => $items )
						{
							$doc_added = '';
							if( $totals[ $order ] )
							{
								$rowspan[ $order ]+= 1;
							}

							foreach( $items as $i => $vals )
							{
								$row = [

'customer' => [ 'value'=>$vals['customer_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'category' => [ 'value'=>$vals['category_name'], 'class'=>['leftered'] ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

								];

								if( $doc_added == $order ) 
								{
									$row['customer'] = [];
								}
								$doc_added = $order;

								$details[] = $row;
							}

							$details[] = [
								'customer' => [],
								'category' => [ 'value'=>$order.' Total:', 'class'=>['leftered','bold'] ],
								'amount' => [ 'value'=>$totals[ $order ], 'class'=>['rightered','bold'], 'num'=>1 ],
							];

							$total+= $totals[ $order ];
						}

						$details[] = [
							'customer' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>2 ],
							'amount' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
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
			case 'detail':
			default:
				$filename = "FoodBoard-Detail";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'FoodBoard Detail';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'FB Detail';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'SO/Receipt' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'DO' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Customer' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Product' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'10%', 'class'=>['rightered'], 'num'=>1 ],
					'UOM' => [ 'width'=>'10%', 'class'=>['centered'] ],
					'Amount ('.$currency.')' => [ 'width'=>'10%', 'class'=>['rightered'], 'num'=>1 ],
				];

				if( $params['grouping'] == 'category' )
				{
					$document['detail_title'] = [
						'SO/Receipt' => [ 'width'=>'10%', 'class'=>['leftered'] ],
						'DO' => [ 'width'=>'10%', 'class'=>['leftered'] ],
						'Date' => [ 'width'=>'15%', 'class'=>['leftered'] ],
						'Customer' => [ 'width'=>'15%', 'class'=>['leftered'] ],
						'Category' => [ 'width'=>'25%', 'class'=>['leftered'] ],
						'Qty' => [ 'width'=>'10%', 'class'=>['rightered'], 'num'=>1 ],
						'Amount ('.$currency.')' => [ 'width'=>'15%', 'class'=>['rightered'], 'num'=>1 ],
					];
				}

				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$product = [];
						if( $data['item_code'] ) $product[] = $data['item_code'];
						if( $data['item_name'] ) $product[] = $data['item_name'];
						$data['product'] = implode( ' - ', $product );

						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];
						$data['category_name'] = implode( ' - ', $category );

						$customer = [];
						if( $data['customer_code'] ) $customer[] = $data['customer_code'];
						if( $data['customer_name'] ) $customer[] = $data['customer_name'];
						$data['customer_name'] = implode( ' - ', $customer );

						$data['date'] = date_i18n( $date_format, strtotime( $data['date'] ) );

						$regrouped[ $data['order_no'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['order_no'] ]+= 1;

						//totals
						$totals[ $data['order_no'] ]+= $data['amount'];
					}

					$details = [];
					if( $regrouped )
					{
						$total = 0;
						foreach( $regrouped as $order => $items )
						{
							$doc_added = '';
							if( $totals[ $order ] )
							{
								$rowspan[ $order ]+= 1;
							}

							foreach( $items as $i => $vals )
							{
								$row = [

'order_no' => [ 'value'=>$vals['order_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'do_no' => [ 'value'=>$vals['do_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'date' => [ 'value'=>$vals['date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'customer' => [ 'value'=>$vals['customer_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'product' => [ 'value'=>$vals['product'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

								];

								if( $params['grouping'] == 'category' )
								{
									$row = [

'order_no' => [ 'value'=>$vals['order_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'do_no' => [ 'value'=>$vals['do_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'date' => [ 'value'=>$vals['date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'customer' => [ 'value'=>$vals['customer_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'category' => [ 'value'=>$vals['category_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

									];
								}

								if( $doc_added == $order ) 
								{
									$row['order_no'] = [];
									$row['do_no'] = [];
									$row['date'] = [];
									$row['customer'] = [];
								}
								$doc_added = $order;

								$details[] = $row;
							}

							if( $params['grouping'] == 'category' )
							{
								$details[] = [
									'order_no' => [],
									'do_no' => [],
									'date' => [],
									'customer' => [],
									'category' => [ 'value'=>$order.' Total:', 'class'=>['leftered','bold'], 'colspan'=>2 ],
									'amount' => [ 'value'=>$totals[ $order ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}
							else
							{
								$details[] = [
									'order_no' => [],
									'do_no' => [],
									'date' => [],
									'customer' => [],
									'product' => [ 'value'=>$order.' Total:', 'class'=>['leftered','bold'], 'colspan'=>3 ],
									'amount' => [ 'value'=>$totals[ $order ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}
							
							$total+= $totals[ $order ];
						}

						if( $params['grouping'] == 'category' )
						{
							$details[] = [
								'order_no' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>6 ],
								'amt' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
							];
						}
						else
						{
							$details[] = [
								'order_no' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>7 ],
								'amt' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
							];
						}
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
		$action_id = 'foodboard_report_export';
		$args = array(
			'setting'	=> $this->setting,
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'category':
				do_action( 'wcwh_templating', 'report/export-fb_category-report.php', $this->tplName['export'], $args );
			break;
			case 'detail':
			default:
				do_action( 'wcwh_templating', 'report/export-fb_detail-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'foodboard_report_export';
		$args = array(
			'setting'	=> $this->setting,
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
			case 'category':
				do_action( 'wcwh_templating', 'report/export-fb_category-report.php', $this->tplName['print'], $args );
			break;
			case 'detail':
			default:
				do_action( 'wcwh_templating', 'report/export-fb_detail-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	FoodBoard Detail
	 */
	public function foodboard_detail_report( $filters = array(), $order = array() )
	{
		$action_id = 'foodboard_detail_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/foodBoardList.php" ); 
			$Inst = new WCWH_FoodBoard_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;

			$filter = [ 'status'=>1, 'indication'=>1 ];
			if( isset( $filters['seller'] ) ) $filter['seller'] = $filters['seller'];
			$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'foodboard_client', 'foodboard_customer' ] ] );
			if( $wh )
			{
				if( empty( $filters['client'] ) && empty( $filters['customer'] ) )
				{
					$filters['client'] = !empty( $filters['client'] )? $filters['client'] : ( is_json( $wh['foodboard_client'] )? json_decode( stripslashes( $wh['foodboard_client'] ), true ) : $wh['foodboard_client'] );
					$filters['customer'] = !empty( $filters['customer'] )? $filters['customer'] : ( is_json( $wh['foodboard_customer'] )? json_decode( stripslashes( $wh['foodboard_customer'] ), true ) : $wh['foodboard_customer'] );
				}
			}
			//pd($filters);
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.qty, .weight, .price, .amount' => [ 'text-align'=>'right !important' ],
				'#qty a span, #weight a span, #amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_foodboard_detail_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	FoodBoard Category
	 */
	public function foodboard_category_report( $filters = array(), $order = array() )
	{
		$action_id = 'foodboard_category_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/foodBoardCatList.php" ); 
			$Inst = new WCWH_FoodBoard_Category_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;

			$filter = [ 'status'=>1, 'indication'=>1 ];
			if( isset( $filters['seller'] ) ) $filter['seller'] = $filters['seller'];
			$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'foodboard_client', 'foodboard_customer' ] ] );
			if( $wh )
			{
				if( empty( $filters['client'] ) && empty( $filters['customer'] ) )
				{
					$filters['client'] = !empty( $filters['client'] )? $filters['client'] : ( is_json( $wh['foodboard_client'] )? json_decode( stripslashes( $wh['foodboard_client'] ), true ) : $wh['foodboard_client'] );
					$filters['customer'] = !empty( $filters['customer'] )? $filters['customer'] : ( is_json( $wh['foodboard_customer'] )? json_decode( stripslashes( $wh['foodboard_customer'] ), true ) : $wh['foodboard_customer'] );
				}
			}
			//pd($filters);
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.qty, .weight, .amount' => [ 'text-align'=>'right !important' ],
				'#qty a span, #weight a span, #amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_foodboard_category_report( $filters, $order, [] );
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
	public function get_foodboard_detail_report( $filters = [], $order = [], $args = [] )
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

		//----------------------------------
		if( isset( $filters['client'] ) && isset( $filters['customer'] ) )
		{
			$segment = $this->get_delivery_order( $filters, false );
			$subsql = "{$segment} ";

			$subsql.= "UNION ALL ";

			$segment = $this->get_pos( $filters, false );
			$subsql.= "{$segment} ";
		}
		else if( isset( $filters['client'] ) && ! isset( $filters['customer'] ) )
		{
			$segment = $this->get_delivery_order( $filters, false );
			$subsql = "{$segment} ";
		}
		else if( ! isset( $filters['client'] ) && isset( $filters['customer'] ) )
		{
			$segment = $this->get_pos( $filters, false );
			$subsql = "{$segment} ";
		}
		else
			return false;
		//----------------------------------
		
		if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) $prdt_fld = ", a.item_name ";
		$field = "a.order_no, a.do_no, a.date
			, a.customer_code, a.customer_name, a.remark 
			, a.category_code, a.category_name 
			, a.item_code{$prdt_fld}, a.uom 
			, a.qty, a.metric, a.price, a.amount ";

		$table = "( $subsql ) a ";
		
		$cond = "";
		$grp = "";
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
                $cd[] = "a.order_no LIKE '%".$kw."%' ";
                $cd[] = "a.customer_code LIKE '%".$kw."%' ";
				$cd[] = "a.customer_name LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		if( $filters['grouping'] == 'category' )//|| ! current_user_cans( [ 'item_visible_wh_reports' ] )
		{
			$field = "a.order_no, a.do_no, a.date 
				, a.customer_code, a.customer_name 
				, a.category_code, a.category_name 
				, SUM( a.qty ) AS qty, SUM( a.metric ) AS metric, SUM( a.amount ) AS amount ";

			$grp = "GROUP BY a.order_no, a.customer_code, a.category_code ";
		}
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.order_no' => 'ASC', 'a.customer_code' => 'ASC', 'a.item_code' => 'ASC' ];
			if( $filters['grouping'] == 'category' )//|| ! current_user_cans( [ 'item_visible_wh_reports' ] )
			{
				$order = [ 'a.order_no' => 'ASC', 'a.customer_code' => 'ASC', 'a.category_code' => 'ASC' ];
			}
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

	public function get_foodboard_category_report( $filters = [], $order = [], $args = [] )
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

		//----------------------------------
		if( isset( $filters['client'] ) && isset( $filters['customer'] ) )
		{
			$segment = $this->get_delivery_order( $filters, false );
			$subsql = "{$segment} ";

			$subsql.= "UNION ALL ";

			$segment = $this->get_pos( $filters, false );
			$subsql.= "{$segment} ";
		}
		else if( isset( $filters['client'] ) && ! isset( $filters['customer'] ) )
		{
			$segment = $this->get_delivery_order( $filters, false );
			$subsql = "{$segment} ";
		}
		else if( ! isset( $filters['client'] ) && isset( $filters['customer'] ) )
		{
			$segment = $this->get_pos( $filters, false );
			$subsql = "{$segment} ";
		}
		else
			return false;
		//----------------------------------
		
		$field = "a.customer_code, a.customer_name
			, a.category_code, a.category_name 
			, SUM( a.qty ) AS qty, SUM( a.metric ) AS metric, SUM( a.amount ) AS amount ";

		$table = "( $subsql ) a ";
		
		$cond = "";
		$grp = "";
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
                $cd[] = "a.customer_code LIKE '%".$kw."%' ";
				$cd[] = "a.customer_name LIKE '%".$kw."%' ";
				$cd[] = "a.category_code LIKE '%".$kw."%' ";
				$cd[] = "a.category_name LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY a.customer_code, a.category_code ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.customer_code' => 'ASC', 'a.category_code' => 'ASC' ];
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

		public function get_delivery_order( $filters = [], $run = false )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();
			$dbname = "";

		    $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
			if( $wh['parent'] > 0 )
			{
			    $filters['warehouse_id'] = $wh['code'];
			}

			$field = "md.meta_value AS order_no, h.docno AS do_no, h.doc_date AS date 
				, c.code AS customer_code, c.name AS customer_name, mc.meta_value AS remark 
				, cat.slug AS category_code, cat.name AS category_name 
				, i.code AS item_code, i.name AS item_name, i._uom_code AS uom 
				, d.bqty AS qty, IFNULL( dma.meta_value, d.bunit ) AS metric, dmb.meta_value AS price 
				, ROUND( d.bqty * dmb.meta_value, 2 ) AS amount ";
			
			$table = "{$dbname}{$this->tables['document']} h ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'supply_to_seller' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'remark' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'sales_doc' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d ", 'delivery_order', 6 );

			if( isset( $filters['warehouse_id'] ) )
			{
				$cond.= $wpdb->prepare( "AND mb.meta_value = %s ", $filters['warehouse_id'] );
			}
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
			}
			if( isset( $filters['client'] ) )
			{
				if( is_array( $filters['client'] ) )
					$cond.= "AND ma.meta_value IN ('" .implode( "','", $filters['client'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND ma.meta_value = %d ", $filters['client'] );
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

			$grp = "";
			$ord = "";

			$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
			return $query;
		}

		public function get_pos( $filters = [], $run = false )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			if( isset( $filters['seller'] ) )
			{
				$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
				$dbname = ( $dbname )? $dbname."." : "";
			}

			$field = "c.meta_value AS order_no, '' AS do_no, a.post_date AS date 
				, cust.code AS customer_code, cust.name AS customer_name, e.meta_value AS remark 
				, cat.slug AS category_code, cat.name AS category_name
				, it.code AS item_code, it.name AS item_name, it._uom_code AS uom
				, k.meta_value AS qty, ROUND( k.meta_value * n.meta_value, 3 ) AS metric, ROUND( l.meta_value, 2 ) AS price
				, ROUND( m.meta_value, 2 ) AS amount ";
			
			$table = "{$dbname}{$wpdb->posts} a ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_order_number' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = '_order_total' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = 'order_comments' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} cust ON cust.id = b.meta_value ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_price' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} n ON n.order_item_id = i.order_item_id AND n.meta_key = '_unit' ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} it ON it.id = j.meta_value ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = it.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value > %d ", 'shop_order', 0 );
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
				if( is_array( $filters['customer'] ) )
					$cond.= "AND b.meta_value IN ('" .implode( "','", $filters['customer'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND b.meta_value = %d ", $filters['customer'] );
			}
			if( isset( $filters['product'] ) )
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND it.id IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND it.id = %d ", $filters['product'] );
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

			$grp = "";
			$ord = "";

			$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
			return $query;
		}

		//deprecated since 2.6.6
		public function get_good_issue( $filters = [], $run = false )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			if( isset( $filters['seller'] ) )
			{
				$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
				$dbname = ( $dbname )? $dbname."." : "";
			}
			if( isset( $filters['seller'] ) )
		    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
		    else
		    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		    if( $curr_wh ) $filters['warehouse_id'] = $curr_wh['code'];

			$field = "doh.docno AS order_no, doh.doc_date AS date, h.doc_date AS receive_date ";
			$field.= ", cat.slug AS category_code, cat.name AS category ";
			$field.= ", i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";
			$field.= ", d.bqty AS qty, IFNULL( dma.meta_value, d.bunit ) AS weight, dmb.meta_value AS price, ROUND( d.bqty * dmb.meta_value, 2 ) AS amount ";
			
			$table = "{$dbname}{$this->tables['document']} h ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'client_company_code' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} grd ON grd.doc_id = d.ref_doc_id AND grd.item_id = d.ref_item_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} dod ON dod.doc_id = grd.ref_doc_id AND dod.item_id = grd.ref_item_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document']} doh ON doh.doc_id = dod.doc_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} doma ON doma.doc_id = dod.doc_id AND doma.item_id = dod.item_id AND doma.meta_key = 'ucost'  ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND d.status > %d ", 'good_issue', 6, 0 );
			$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", 'direct_consume' );

			if( isset( $filters['warehouse_id'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
			}
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
			}
			if( isset( $filters['client'] ) )
			{
				if( is_array( $filters['client'] ) )
					$cond.= "AND mb.meta_value IN ('" .implode( "','", $filters['client'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND mb.meta_value = %d ", $filters['client'] );
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

			$grp = "";
			$ord = "";

			$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
			return $query;
		}
	
} //class

}