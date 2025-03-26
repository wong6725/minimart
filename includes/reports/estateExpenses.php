<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_EstateExpenses_Rpt" ) ) 
{
	
class WCWH_EstateExpenses_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "EstateExpenses";

	public $tplName = array(
		'export' => 'exportEstateExpenses',
		'print' => 'printEstateExpenses',
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
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"customer" 		=> $prefix."customer",
			"tree"			=> $prefix."customer_tree",
			"meta"			=> $prefix."customermeta",
			"customer_group"	=> $prefix."customer_group",
			"customer_job"	=> $prefix."customer_job",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",

			"items"			=> $prefix."items",
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
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
						case 'category':
							$datas['filename'] = 'Estate Expenses Category ';
						break;
						case 'detail':
							$datas['filename'] = 'Estate Expenses ';
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
				return $this->get_estate_expenses_category_report( $params );
			break;
			case 'detail':
			default:
				return $this->get_estate_expenses_detail_report( $params );
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
				$filename = "Estate-Expenses-Category";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Estate Expenses Category';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Estate Expenses Category';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Category' => [ 'width'=>'50%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'25%', 'class'=>['rightered'] ],
					'Total ('.$currency.')' => [ 'width'=>'25%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$totals = 0; $qtys = 0;
					$details = [];
					foreach( $datas as $i => $data )
					{
						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];

						$data['category_name'] = implode( ' - ', $category );

						$row = [

'category' => [ 'value'=>$data['category_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$data['qty'], 'class'=>['rightered'], 'num'=>1 ],
'amount' => [ 'value'=>$data['amount'], 'class'=>['rightered'], 'num'=>1 ],

						];

						$details[] = $row;

						//totals
						$totals+= $data['amount'];
						$qtys+= $data['qty'];
					}

					$details[] = [
						'category' => [ 'value'=>'Total:', 'class'=>['leftered','bold'] ],
						'qty' => [ 'value'=>$qtys, 'class'=>['rightered','bold'], 'num'=>1 ],
						'amount' => [ 'value'=>$totals, 'class'=>['rightered','bold'], 'num'=>1 ],
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
			case 'detail':
			default:
				$filename = "Estate-Expenses-Detail";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Estate Expenses Detail';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Estate Expenses Detail';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Receipt' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Customer' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Remark' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Product' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'5%', 'class'=>['rightered'] ],
					'UOM' => [ 'width'=>'5%', 'class'=>['centered'] ],
					'Total ('.$currency.')' => [ 'width'=>'10%', 'class'=>['rightered'] ],
				];

				if( $params['grouping'] == 'category' )
				{
					$document['detail_title'] = [
						'Receipt' => [ 'width'=>'10%', 'class'=>['leftered'] ],
						'Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
						'Customer' => [ 'width'=>'15%', 'class'=>['leftered'] ],
						'Remark' => [ 'width'=>'20%', 'class'=>['leftered'] ],
						'Category' => [ 'width'=>'25%', 'class'=>['leftered'] ],
						'Qty' => [ 'width'=>'10%', 'class'=>['rightered'] ],
						'Total ('.$currency.')' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					];
				}
				
				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$customer = [];
						if( $data['customer_code'] ) $customer[] = $data['customer_code'];
						if( $data['customer_name'] ) $customer[] = $data['customer_name'];
						$data['customer_name'] = implode( ' - ', $customer );

						$product = [];
						if( $data['item_code'] ) $product[] = $data['item_code'];
						if( $data['item_name'] ) $product[] = $data['item_name'];
						$data['product'] = implode( ' - ', $product );

						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];
						$data['category_name'] = implode( ' - ', $category );

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
'date' => [ 'value'=>$vals['date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'customer' => [ 'value'=>$vals['customer_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'remark' => [ 'value'=>$vals['remark'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'category' => [ 'value'=>$vals['category_name'], 'class'=>['leftered'] ],
'product' => [ 'value'=>$vals['product'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

								];

								if( $params['grouping'] == 'category' )
								{
									$row = [

'order_no' => [ 'value'=>$vals['order_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'date' => [ 'value'=>$vals['date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'customer' => [ 'value'=>$vals['customer_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'remark' => [ 'value'=>$vals['remark'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'category' => [ 'value'=>$vals['category_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

									];
								}

								if( $doc_added == $order ) 
								{
									$row['order_no'] = [];
									$row['date'] = [];
									$row['customer'] = [];
									$row['remark'] = [];
								}
								$doc_added = $order;

								$details[] = $row;
							}

							if( $params['grouping'] == 'category' )
							{
								$details[] = [
									'order_no' => [],
									'date' => [],
									'customer' => [],
									'remark' => [],
									'category' => [ 'value'=>$order.' Total:', 'class'=>['leftered','bold'], 'colspan'=>2 ],
									'amount' => [ 'value'=>$totals[ $order ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}
							else
							{
								$details[] = [
									'order_no' => [],
									'date' => [],
									'customer' => [],
									'remark' => [],
									'product' => [ 'value'=>$order.' Total:', 'class'=>['leftered','bold'], 'colspan'=>4 ],
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
								'order_no' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>8 ],
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
		$action_id = 'estate_expenses_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
			'acc_type'	=> $this->acc_type,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'category':
				do_action( 'wcwh_templating', 'report/export-et_exp_category-report.php', $this->tplName['export'], $args );
			break;
			case 'detail':
			default:
				do_action( 'wcwh_templating', 'report/export-et_exp_detail-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'estate_expenses_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
			'acc_type'	=> $this->acc_type,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'category':
				do_action( 'wcwh_templating', 'report/export-et_exp_category-report.php', $this->tplName['print'], $args );
			break;
			case 'detail':
			default:
				do_action( 'wcwh_templating', 'report/export-et_exp_detail-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	EstateExpenses Detail
	 */
	public function estate_expenses_detail_report( $filters = array(), $order = array() )
	{
		$action_id = 'estate_expenses_detail_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/estateExpensesList.php" ); 
			$Inst = new WCWH_EstateExpenses_Report();
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
			$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_expense_acc' ] ] );
			if( $wh )
			{
				$filters['acc_type'] = ( is_json( $wh['estate_expense_acc'] )? json_decode( stripslashes( $wh['estate_expense_acc'] ), true ) : $wh['estate_expense_acc'] );
				$Inst->acc_type = $filters['acc_type'];
			}
			//pd($filters);
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.qty, .metric, .price, .amount' => [ 'text-align'=>'right !important' ],
				'#qty a span, #metric a span, #amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_estate_expenses_detail_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	EstateExpenses Category
	 */
	public function estate_expenses_category_report( $filters = array(), $order = array() )
	{
		$action_id = 'estate_expenses_category_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/estateExpensesCatList.php" ); 
			$Inst = new WCWH_EstateExpensesCat_Report();
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
			$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_expense_acc' ] ] );
			if( $wh )
			{
				$filters['acc_type'] = ( is_json( $wh['estate_expense_acc'] )? json_decode( stripslashes( $wh['estate_expense_acc'] ), true ) : $wh['estate_expense_acc'] );
				$Inst->acc_type = $filters['acc_type'];
			}
			//pd($filters);
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.qty, .metric, .amount' => [ 'text-align'=>'right !important' ],
				'#qty a span, #metric a span, #amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_estate_expenses_category_report( $filters, $order, [] );
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
	public function get_estate_expenses_detail_report( $filters = [], $order = [], $args = [] )
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

			$filter = [ 'status'=>1, 'indication'=>1 ];
			$filter['seller'] = $filters['seller'];
			$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_expense_acc' ] ] );
			if( $wh && ! isset( $filters['acc_type'] ) )
			{
				$filters['acc_type'] = ( is_json( $wh['estate_expense_acc'] )? json_decode( stripslashes( $wh['estate_expense_acc'] ), true ) : $wh['estate_expense_acc'] );
			}
		}

		//----------------------------------
		
		if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) $prdt_fld = ", it.name AS item_name ";
		$field = "c.meta_value AS order_no, a.post_date AS date 
			, cust.code AS customer_code, cust.name AS customer_name, e.meta_value AS remark 
			, cat.slug AS category_code, cat.name AS category_name
			, it.code AS item_code{$prdt_fld}, it._uom_code AS uom
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
				$cond.= "AND cust.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND cust.id = %d ", $filters['customer'] );
		}
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND cust.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND cust.acc_type = %d ", $filters['acc_type'] );
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
                $cd[] = "it.name LIKE '%".$kw."%' ";
				$cd[] = "it.code LIKE '%".$kw."%' ";
				$cd[] = "cat.name LIKE '%".$kw."%' ";
				$cd[] = "cat.slug LIKE '%".$kw."%' ";
				$cd[] = "cust.name LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		if( $filters['grouping'] == 'category' )//|| ! current_user_cans( [ 'item_visible_wh_reports' ] )
		{
			
			$field.= ", a.category, a.category_code ";
			$field.= ", SUM( a.qty ) AS qty, SUM( a.weight ), SUM( a.amount ) AS amount ";

			$field = "c.meta_value AS order_no, a.post_date AS date 
				, cust.code AS customer_code, cust.name AS customer_name, e.meta_value AS remark 
				, cat.slug AS category_code, cat.name AS category_name
				, SUM( k.meta_value ) AS qty, ROUND( SUM( k.meta_value * n.meta_value ), 3 ) AS metric
				, ROUND( SUM( m.meta_value ), 2 ) AS amount ";

			$grp = "GROUP BY order_no, category_code ";
		}
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'order_no' => 'ASC', 'it.code' => 'ASC' ];
			if( $filters['grouping'] == 'category' )//|| ! current_user_cans( [ 'item_visible_wh_reports' ] )
			{
				$order = [ 'order_no' => 'ASC', 'category_code' => 'ASC' ];
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

	public function get_estate_expenses_category_report( $filters = [], $order = [], $args = [] )
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

			$filter = [ 'status'=>1, 'indication'=>1 ];
			$filter['seller'] = $filters['seller'];
			$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_expense_acc' ] ] );
			if( $wh && ! isset( $filters['acc_type'] ) )
			{
				$filters['acc_type'] = ( is_json( $wh['estate_expense_acc'] )? json_decode( stripslashes( $wh['estate_expense_acc'] ), true ) : $wh['estate_expense_acc'] );
			}
		}

		//----------------------------------
		$field = " ";
		$field.= "cat.slug AS category_code, cat.name AS category_name 
			, SUM( k.meta_value ) AS qty, ROUND( SUM( k.meta_value * n.meta_value ), 3 ) AS metric
			, ROUND( SUM(  m.meta_value ), 2 ) AS amount ";
		
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
				$cond.= "AND cust.id IN ('" .implode( "','", $filters['customer'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND cust.id = %d ", $filters['customer'] );
		}
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND cust.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND cust.acc_type = %d ", $filters['acc_type'] );
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
				$cd[] = "a.category LIKE '%".$kw."%' ";
				$cd[] = "a.category_code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$grp = "GROUP BY category_code ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'category_code' => 'ASC' ];
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