<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Discrepancy_Rpt" ) ) 
{
	
class WCWH_Discrepancy_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "Discrepancy";

	public $tplName = array(
		'export' => 'exportDiscrepancy',
		'print' => 'printDiscrepancy',
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

			"client"		=> $prefix."client",
			"warehouse"		=> $prefix."warehouse",
			"warehousemeta" => $prefix."warehousemeta",
			
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
					switch( $datas['discrepancy_type'] )
					{
						case 'good_return':
							$datas['filename'] = 'GT Discrepancy ';
						break;
						case 'delivery_order':
							$datas['filename'] = 'DO Discrepancy ';
						break;
						case 'discrepancy':
						default:
							$datas['filename'] = 'Discrepancy ';
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
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];

					//---- 21/10 gt discrepancy
					if( !empty( $datas['discrepancy_type'] ) ) $params['export_type'] =$datas['discrepancy_type'];
					//---- 21/10 gt discrepancy
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];

					//---- 21/10 gt discrepancy
					if( !empty( $datas['discrepancy_type'] ) ) $params['export_type'] =$datas['discrepancy_type'];
					//---- 21/10 gt discrepancy

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
			case 'good_return':
				return $this->get_gt_discrepancy_report( $params );
			break;
			case 'delivery_order':
				return $this->get_do_discrepancy_report( $params );
			break;
			case 'discrepancy':
			default:
				return $this->get_discrepancy_report( $params );
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
			//---- 21/10 gt discrepancy
			case 'good_return':
				$filename = "GT Discrepancy";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'GT Discrepancy Report';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'GT Discrepancy';

				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );

				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'GT No' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Warehouse' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Product' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'11%', 'class'=>['rightered'] ],
					'UOM' => [ 'width'=>'12%', 'class'=>['centered'] ],
					'Received Qty' => [ 'width'=>'11%', 'class'=>['rightered'] ],
					'Balance' => [ 'width'=>'11%', 'class'=>['rightered'] ],
				];

				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					foreach( $datas as $i => $data )
					{
						$product = [];
						if( $data['item_code'] ) $product[] = $data['item_code'];
						if( $data['item_name'] ) $product[] = $data['item_name'];
						$data['product'] = implode( ' - ', $product );

						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category'] ) $category[] = $data['category'];
						$data['category_name'] = implode( ' - ', $category );

						$data['date'] = date_i18n( $date_format, strtotime( $data['doc_date'] ) );

						$regrouped[ $data['docno'] ][$i] = $data;
						if($rowspan[ $data['docno'] ] ) $rowspan[ $data['docno'] ] += 1;
						else $rowspan[ $data['docno'] ] = 1;
					}

					$details = [];
					if( $regrouped )
					{
						$total = 0;
						foreach( $regrouped as $order => $items )
						{
							$doc_added = '';

							foreach( $items as $i => $vals )
							{
								$row = [
									'docno' => [ 'value'=>$vals['docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
									'date' => [ 'value'=>$vals['doc_date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
									'warehouse_id' => [ 'value'=>$vals['warehouse_id'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
									'product' => [ 'value'=>$vals['product'], 'class'=>['leftered'] ],
									'bqty' => [ 'value'=>$vals['bqty'], 'class'=>['rightered'], 'num'=>1 ],
									'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
									'uqty' => [ 'value'=>$vals['uqty'], 'class'=>['rightered'], 'num'=>1 ],
									'remain_qty' => [ 'value'=>$vals['remain_qty'], 'class'=>['rightered'], 'num'=>1 ],
								];

								if( $doc_added == $order ) 
								{
									$row['docno'] = [];
									$row['date'] = [];
									$row['warehouse_id'] = [];
								}
								$doc_added = $order;

								$details[] = $row;
							}
						}
					}

					$document['detail'] = $details;
				}

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
			//---- 21/10 gt discrepancy
			case 'delivery_order':
				$filename = "DO Discrepancy";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'DO Discrepancy Report';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'DO Discrepancy';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'DO No' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Client' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Product' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'7%', 'class'=>['rightered'] ],
					'UOM' => [ 'width'=>'7%', 'class'=>['centered'] ],
					'Received Qty' => [ 'width'=>'8%', 'class'=>['rightered'] ],
					'Balance' => [ 'width'=>'8%', 'class'=>['rightered'] ],
				];

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
						if( $data['category'] ) $category[] = $data['category'];
						$data['category_name'] = implode( ' - ', $category );

						$client = [];
						if( $data['client_code'] ) $client[] = $data['client_code'];
						if( $data['client_name'] ) $client[] = $data['client_name'];
						$data['client'] = implode( ' - ', $client );

						$data['date'] = date_i18n( $date_format, strtotime( $data['doc_date'] ) );

						$regrouped[ $data['docno'] ][$i] = $data;

						//totals
						$totals[ $data['docno'] ]+= $data['selling_amt'];
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

'docno' => [ 'value'=>$vals['docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'date' => [ 'value'=>$vals['doc_date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'client' => [ 'value'=>$vals['client'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'product' => [ 'value'=>$vals['product'], 'class'=>['leftered'] ],
'bqty' => [ 'value'=>$vals['bqty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'uqty' => [ 'value'=>$vals['uqty'], 'class'=>['rightered'], 'num'=>1 ],
'remain_qty' => [ 'value'=>$vals['remain_qty'], 'class'=>['rightered'], 'num'=>1 ],

								];

								if( $doc_added == $order ) 
								{
									$row['docno'] = [];
									$row['date'] = [];
									$row['client'] = [];
								}
								$doc_added = $order;

								$details[] = $row;
							}
							
							$total+= $totals[ $order ];
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
			case 'discrepancy':
			default:
				$filename = "Discrepancy";
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Discrepancy Report';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Discrepancy';
				
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'DO No' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'Client' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Product' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'6%', 'class'=>['rightered'] ],
					'UOM' => [ 'width'=>'6%', 'class'=>['centered'] ],
					'Received Qty' => [ 'width'=>'6%', 'class'=>['rightered'] ],
					'Balance' => [ 'width'=>'6%', 'class'=>['rightered'] ],
					'Remark' => [ 'width'=>'8%', 'class'=>['centered'] ],
				];

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
						if( $data['category'] ) $category[] = $data['category'];
						$data['category_name'] = implode( ' - ', $category );

						$client = [];
						if( $data['client_code'] ) $client[] = $data['client_code'];
						if( $data['client_name'] ) $client[] = $data['client_name'];
						$data['client'] = implode( ' - ', $client );

						$data['date'] = date_i18n( $date_format, strtotime( $data['doc_date'] ) );

						$regrouped[ $data['docno'] ][$i] = $data;

						//totals
						$totals[ $data['docno'] ]+= $data['selling_amt'];
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

'docno' => [ 'value'=>$vals['docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'date' => [ 'value'=>$vals['doc_date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'client' => [ 'value'=>$vals['client'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $order ] ],
'product' => [ 'value'=>$vals['product'], 'class'=>['leftered'] ],
'bqty' => [ 'value'=>$vals['bqty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'uqty' => [ 'value'=>$vals['uqty'], 'class'=>['rightered'], 'num'=>1 ],
'remain_qty' => [ 'value'=>$vals['remain_qty'], 'class'=>['rightered'], 'num'=>1 ],
'remark' => [ 'value'=>$vals['grs_remark'], 'class'=>['centered'], 'rowspan'=>$rowspan[ $order ] ],

								];

								if( $doc_added == $order ) 
								{
									$row['docno'] = [];
									$row['date'] = [];
									$row['client'] = [];
									$row['remark'] = [];
								}
								$doc_added = $order;

								$details[] = $row;
							}
							
							$total+= $totals[ $order ];
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
		$action_id = 'discrepancy_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
			'discrepancy_type' => $type
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'good_return':
				do_action( 'wcwh_templating', 'report/export-gt_discrepency-report.php', $this->tplName['export'], $args );
			break;
			case 'delivery_order':			
				do_action( 'wcwh_templating', 'report/export-do_discrepency-report.php', $this->tplName['export'], $args );
			break;
			case 'discrepancy':
			default:
				do_action( 'wcwh_templating', 'report/export-discrepency-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'discrepancy_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
			'discrepancy_type' => $type 
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'good_return':
				do_action( 'wcwh_templating', 'report/export-gt_discrepency-report.php', $this->tplName['print'], $args );
			break;
			case 'delivery_order':
				do_action( 'wcwh_templating', 'report/export-do_discrepency-report.php', $this->tplName['print'], $args );
			break;
			case 'discrepancy':
			default:
				do_action( 'wcwh_templating', 'report/export-discrepency-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	Discrepancy
	 */
	public function discrepancy_report( $filters = array(), $order = array() )
	{
		$action_id = 'discrepancy_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/alt_discrepancyList.php" ); 
			$Inst = new WCWH_AltDiscrepancy_Report();
			$Inst->seller = $this->seller;
			
			$date_to = current_time( 'Y-m-t' );
			
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.bqty, .uqty, .remain_qty, .unpost_qty, .sprice, .selling_amt' => [ 'text-align'=>'right !important' ],
				'#bqty a span, #uqty a span, #remain_qty a span, #unpost_qty a span, #selling_amt a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_discrepancy_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	DO Discrepancy
	 */
	public function do_discrepancy_report( $filters = array(), $order = array() )
	{
		$action_id = 'do_discrepancy_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/discrepancyList.php" ); 
			$Inst = new WCWH_Discrepancy_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;

			//$filter = [ 'status'=>1, 'indication'=>1 ];
			//if( isset( $filters['seller'] ) ) $filter['seller'] = $filters['seller'];
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.bqty, .uqty, .remain_qty, .unpost_qty, .sprice, .selling_amt' => [ 'text-align'=>'right !important' ],
				'#bqty a span, #uqty a span, #remain_qty a span, #unpost_qty a span, #selling_amt a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_do_discrepancy_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	//---- 21/10 gt discrepancy
	public function gt_discrepancy_report( $filters = array(), $order = array() )
	{
		$action_id = 'gt_discrepancy_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/gtdiscrepancyList.php" ); 
			$Inst = new WCWH_GT_Discrepancy_Report();
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
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.bqty, .uqty, .remain_qty, .unpost_qty' => [ 'text-align'=>'right !important' ],
				'#bqty a span, #uqty a span, #remain_qty a span, #unpost_qty a span' => [ 'float'=>'right' ],
			];

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_gt_discrepancy_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	//---- 21/10 gt discrepancy
	
	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */

	public function get_discrepancy_report( $filters = [], $order = [], $args = [] )
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

		$sql = "";
		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		
		if( isset( $filters['seller'] ) && $filters['seller'] != $wh['id'] )		//Self view outlet
		{
			$sql = $this->get_alt_outlet_discrepancy( $filters, false );
		}
		else 									//view Self
		{
			unset( $filters['seller'] );
			
			if( $wh ) $outlets = apply_filters( 'wcwh_get_warehouse', [ 'parent'=>$wh['id'] ], [], false, [ 'meta'=>['dbname'] ] );

			if( $outlets )	//DC
			{
				$sql = $this->get_alt_discrepancy( $filters, $outlets, false );
			}
			else 			//outlet
			{
				$filters['seller'] = $wh['id'];
				$sql = $this->get_alt_outlet_discrepancy( $filters, false );
			}
		}
		
		$results = [];
		if( $sql ) $results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_alt_discrepancy( $filters = [], $outlets = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		unset( $filters['seller'] );

		$field = "h.doc_id, h.docno, h.doc_date, h.created_at, h.status AS hstatus
			, IF( s.serial IS NULL, 'Not-Synced', 'Synced' ) AS sync_status ";
		$field.= ", c.code AS client_code, c.name AS client_name, mb.meta_value AS direct_issue ";
		$field.= ", cat.slug AS category_code, cat.name AS category ";
		$field.= ", i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";
		$field.= ", d.bqty, s.uqty, ( d.bqty - IFNULL(s.uqty,0) ) AS remain_qty, dma.meta_value AS sunit ";
		$field.= ", ( d.bqty - IFNULL(s.pqty,0) ) AS unpost_qty ";
		$field.= ", dmb.meta_value AS sprice, ROUND( d.bqty * dmb.meta_value, 2 ) AS selling_amt, dmc.meta_value AS ucost";
		$field.= ", s.grs_remark ";
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb on mb.doc_id = d.doc_id AND mb.item_id = 0 AND mb.meta_key = 'direct_issue' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc on mc.doc_id = d.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supply_to_seller' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = d.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb on dmb.doc_id = d.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc on dmc.doc_id = d.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		//------------------------------------------------
		$subsql = []; $clients = [];
		foreach( $outlets as $outlet )
		{
			$filters['seller'] = $outlet['id'];
			$client_code = [];
			$subsql[] = $this->get_alt_outlet_document( $filters, $client_code, false );
			if( !empty( $client_code ) ) $clients = array_merge( $clients, $client_code );
		}

		if( $subsql )
		{
			$table.= "LEFT JOIN ( ".implode( ' UNION ALL ', $subsql )." ) s ON s.sdocno = h.sdocno AND s.serial = i.serial ";
		}
		//------------------------------------------------

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %s ", 'delivery_order', 6 );
		$cond.= "AND mc.meta_value IS NOT NULL AND s.dstatus >= 6 ";
		
		$cond.= "AND ( ( d.bqty - IFNULL(s.uqty,0) != 0 AND s.hstatus = 6 ) OR ( d.bqty - IFNULL(s.pqty,0) != 0 AND d.bqty - IFNULL(s.uqty,0) <= 0 ) ) ";

		if( ! empty( $clients ) )
		{
			$cond.= "AND ma.meta_value IN ('" .implode( "','", $clients ). "') ";
		}

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
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
				$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['client'] );
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.id = %s ", $filters['product'] );
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
		if( isset( $filters['sync'] ) )
		{
			if( $filters['sync'] == 'yes' )
				$cond.= "AND s.serial IS NOT NULL ";
			else if( $filters['sync'] == 'no' )
				$cond.= "AND s.serial IS NULL ";
		}

		$grp = "";
		$ord = "ORDER BY h.docno ASC, i.code ASC ";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
	
		return $query;
	}

	public function get_alt_outlet_document( $filters = [], &$client_code = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( ! $filters['seller'] ) return '';

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true, [ 'meta'=>['dbname'] ] );
		$dbname = ( $wh )? $wh['dbname'] : get_warehouse_meta( $filters['seller'], 'dbname', true );
		$dbname = ( $dbname )? $dbname."." : "";

		if( $wh )
		{
			$whc = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$wh['code'], 'seller'=>$filters['seller'] ], [], true, [ 'meta'=>['client_company_code'] ] );
			if( !empty( $whc['client_company_code'] ) ) $whc['client_company_code'] = json_decode( $whc['client_company_code'], true );
			$client_code = $whc['client_company_code'];
		}

		$field = "h.doc_id, h.docno, h.sdocno, h.doc_date, h.created_at, h.status AS hstatus ";
		$field.= ", ma.meta_value AS client_code, mb.meta_value AS direct_issue, mc.meta_value AS supply_to_seller ";
		$field.= ", i.serial, d.bqty, IFNULL(d.uqty,0) AS uqty, SUM( IFNULL(cd.bqty,0) ) AS pqty, d.bunit, d.uunit, d.status AS dstatus ";
		$field.= ", dma.meta_value AS sunit, dmb.meta_value AS sprice, dmc.meta_value AS ucost ";
		$field.= ", sub.grs_remark ";
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb on mb.doc_id = d.doc_id AND mb.item_id = 0 AND mb.meta_key = 'direct_issue' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc on mc.doc_id = d.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supply_to_seller' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = d.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb on dmb.doc_id = d.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc on dmc.doc_id = d.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} cd ON cd.ref_doc_id = d.doc_id AND cd.ref_item_id = d.item_id AND cd.status >= 6 ";

		//------- retrieve remark made on GR(s)
		$fld = "doc.doc_id, GROUP_CONCAT( dm.meta_value ) AS grs_remark ";
		$tbl = "{$dbname}{$this->tables['document']} doc ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['document']} gr ON gr.parent = doc.doc_id AND gr.status > 0 AND gr.doc_type = 'good_receive' ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dm on dm.doc_id = gr.doc_id AND dm.meta_key = 'remark' AND dm.meta_value IS NOT NULL ";
		$cd = $wpdb->prepare( "AND doc.doc_type = %s AND doc.status >= %s ", 'delivery_order', 6 );
		$gp = "GROUP BY doc.doc_id ";
		$subsql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$gp} " ; 
		//------
		$table.= "LEFT JOIN ({$subsql}) sub ON sub.doc_id = h.doc_id ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %s ", 'delivery_order', 6 );
		$cond.= $wpdb->prepare( "AND mc.meta_value = %s ", $wh['code'] );

		if( !empty( $whc['client_company_code'] ) )
		{
			$cond.= "AND ma.meta_value IN ('" .implode( "','", $whc['client_company_code'] ). "') ";
		}

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
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
				$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['client'] );
		}

		$grp = "GROUP BY h.doc_id, d.item_id ";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
	
		return $query;
	}

	public function get_alt_outlet_discrepancy( $filters = [], $run = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( ! $filters['seller'] ) return '';
		
		$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true, [ 'meta'=>['dbname'] ] );
		$dbname = ( $wh )? $wh['dbname'] : get_warehouse_meta( $filters['seller'], 'dbname', true );
		$dbname = ( $dbname )? $dbname."." : "";

		if( $wh )
		{
			$whc = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$wh['code'], 'seller'=>$filters['seller'] ], [], true, [ 'meta'=>['client_company_code'] ] );
			if( !empty( $whc['client_company_code'] ) ) $whc['client_company_code'] = json_decode( $whc['client_company_code'], true );
		}

		if( ! current_user_cans( [ 'hide_amt_discrepancy_wh_reports' ] ) ) 
    		$amt_fld = ", dmb.meta_value AS sprice, ROUND( d.bqty * dmb.meta_value, 2 ) AS selling_amt, dmc.meta_value AS ucost ";

		$field = "h.doc_id, h.docno, h.doc_date, h.created_at, h.status AS hstatus ";
		$field.= ", c.code AS client_code, c.name AS client_name, mb.meta_value AS direct_issue ";
		$field.= ", cat.slug AS category_code, cat.name AS category ";
		$field.= ", i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";
		$field.= ", d.bqty, d.uqty, ( d.bqty - d.uqty ) AS remain_qty, dma.meta_value AS sunit ";
		$field.= ", ( d.bqty - SUM( IFNULL(cd.bqty,0) ) ) AS unpost_qty ";
		$field.= ", sub.grs_remark ";
		$field.= $amt_fld;
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb on mb.doc_id = d.doc_id AND mb.item_id = 0 AND mb.meta_key = 'direct_issue' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc on mc.doc_id = d.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supply_to_seller' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = d.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb on dmb.doc_id = d.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc on dmc.doc_id = d.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} cd ON cd.ref_doc_id = d.doc_id AND cd.ref_item_id = d.item_id AND cd.status >= 6 ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		//------- retrieve remark made on GR(s)
		$fld = "doc.doc_id, GROUP_CONCAT( dm.meta_value ) AS grs_remark ";
		$tbl = "{$dbname}{$this->tables['document']} doc ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['document']} gr ON gr.parent = doc.doc_id AND gr.status > 0 AND gr.doc_type = 'good_receive' ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dm on dm.doc_id = gr.doc_id AND dm.meta_key = 'remark' AND dm.meta_value IS NOT NULL ";
		$cd = $wpdb->prepare( "AND doc.doc_type = %s AND doc.status >= %s ", 'delivery_order', 6 );
		$gp = "GROUP BY doc.doc_id ";
		$subsql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$gp} " ; 
		//------
		$table.= "LEFT JOIN ({$subsql}) sub ON sub.doc_id = h.doc_id ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %s ", 'delivery_order', 6 );
		$cond.= $wpdb->prepare( "AND mc.meta_value = %s ", $wh['code'] );

		if( !empty( $whc['client_company_code'] ) )
		{
			$cond.= "AND ma.meta_value IN ('" .implode( "','", $whc['client_company_code'] ). "') ";
		}

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
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
				$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['client'] );
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.id = %s ", $filters['product'] );
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

		$grp = "GROUP BY h.doc_id, d.item_id ";
		$ord = "ORDER BY h.docno ASC, i.code ASC ";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		$cond = "AND ( ( a.remain_qty != 0 AND a.hstatus = 6 ) OR ( a.unpost_qty != 0 AND a.remain_qty <= 0 ) ) ";
		$query = "SELECT a.* FROM ( {$query} ) a WHERE 1 {$cond} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
	
		return $query;
	}

	public function get_do_discrepancy_report( $filters = [], $order = [], $args = [] )
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

		$sql = "";
		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		
		if( isset( $filters['seller'] ) && $filters['seller'] != $wh['id'] )		//Self view outlet
		{
			$sql = $this->get_outlet_do_discrepancy( $filters, false );
		}
		else 									//view Self
		{
			unset( $filters['seller'] );
			
			if( $wh ) $outlets = apply_filters( 'wcwh_get_warehouse', [ 'parent'=>$wh['id'] ], [], false, [ 'meta'=>['dbname'] ] );

			if( $outlets )	//DC
			{
				$sql = $this->get_main_do_discrepancy( $filters, $outlets, false );
			}
			else 			//outlet
			{
				$filters['seller'] = $wh['id'];
				$sql = $this->get_outlet_do_discrepancy( $filters, false );
			}
		}
		
		$results = [];
		if( $sql ) $results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

		public function get_main_do_discrepancy( $filters = [], $outlets = [], $run = false )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			unset( $filters['seller'] );

			$field = "h.doc_id, h.docno, h.doc_date, h.created_at, h.status AS hstatus
				, IF( s.serial IS NULL, 'Not-Synced', 'Synced' ) AS sync_status ";
			$field.= ", c.code AS client_code, c.name AS client_name, mb.meta_value AS direct_issue ";
			$field.= ", cat.slug AS category_code, cat.name AS category ";
			$field.= ", i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";
			$field.= ", d.bqty, s.uqty, ( d.bqty - IFNULL(s.uqty,0) ) AS remain_qty, dma.meta_value AS sunit ";
			$field.= ", ( d.bqty - IFNULL(s.pqty,0) ) AS unpost_qty ";
			$field.= ", dmb.meta_value AS sprice, ROUND( d.bqty * dmb.meta_value, 2 ) AS selling_amt, dmc.meta_value AS ucost";
			
			$table = "{$dbname}{$this->tables['document']} h ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb on mb.doc_id = d.doc_id AND mb.item_id = 0 AND mb.meta_key = 'direct_issue' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc on mc.doc_id = d.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supply_to_seller' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = d.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb on dmb.doc_id = d.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc on dmc.doc_id = d.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			//------------------------------------------------
			$subsql = []; $clients = [];
			foreach( $outlets as $outlet )
			{
				$filters['seller'] = $outlet['id'];
				$client_code = [];
				$subsql[] = $this->get_outlet_document( $filters, $client_code, false );
				if( !empty( $client_code ) ) $clients = array_merge( $clients, $client_code );
			}

			if( $subsql )
			{
				$table.= "LEFT JOIN ( ".implode( ' UNION ALL ', $subsql )." ) s ON s.sdocno = h.sdocno AND s.serial = i.serial ";
			}
			//------------------------------------------------

			$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %s ", 'delivery_order', 6 );
			$cond.= "AND mc.meta_value IS NOT NULL AND s.dstatus >= 6 ";
			
			$cond.= "AND ( ( d.bqty - IFNULL(s.uqty,0) != 0 AND s.hstatus = 6 ) OR ( d.bqty - IFNULL(s.pqty,0) != 0 AND d.bqty - IFNULL(s.uqty,0) <= 0 ) ) ";

			if( ! empty( $clients ) )
			{
				$cond.= "AND ma.meta_value IN ('" .implode( "','", $clients ). "') ";
			}

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
					$cond.= "AND ma.meta_value IN ('" .implode( "','", $filters['client'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['client'] );
			}
			if( isset( $filters['product'] ) )
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND i.id = %s ", $filters['product'] );
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
			if( isset( $filters['sync'] ) )
			{
				if( $filters['sync'] == 'yes' )
					$cond.= "AND s.serial IS NOT NULL ";
				else if( $filters['sync'] == 'no' )
					$cond.= "AND s.serial IS NULL ";
			}

			$grp = "";
			$ord = "ORDER BY h.docno ASC, i.code ASC ";

			$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
			return $query;
		}

			public function get_outlet_document( $filters = [], &$client_code = [], $run = false )
			{
				global $wcwh;
				$wpdb = $this->db_wpdb;
				$prefix = $this->get_prefix();

				if( ! $filters['seller'] ) return '';

				$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true, [ 'meta'=>['dbname'] ] );
				$dbname = ( $wh )? $wh['dbname'] : get_warehouse_meta( $filters['seller'], 'dbname', true );
				$dbname = ( $dbname )? $dbname."." : "";

				if( $wh )
				{
					$whc = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$wh['code'], 'seller'=>$filters['seller'] ], [], true, [ 'meta'=>['client_company_code'] ] );
					if( !empty( $whc['client_company_code'] ) ) $whc['client_company_code'] = json_decode( $whc['client_company_code'], true );
					$client_code = $whc['client_company_code'];
				}

				$field = "h.doc_id, h.docno, h.sdocno, h.doc_date, h.created_at, h.status AS hstatus ";
				$field.= ", ma.meta_value AS client_code, mb.meta_value AS direct_issue, mc.meta_value AS supply_to_seller ";
				$field.= ", i.serial, d.bqty, IFNULL(d.uqty,0) AS uqty, SUM( IFNULL(cd.bqty,0) ) AS pqty, d.bunit, d.uunit, d.status AS dstatus ";
				$field.= ", dma.meta_value AS sunit, dmb.meta_value AS sprice, dmc.meta_value AS ucost ";
				
				$table = "{$dbname}{$this->tables['document']} h ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb on mb.doc_id = d.doc_id AND mb.item_id = 0 AND mb.meta_key = 'direct_issue' ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc on mc.doc_id = d.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supply_to_seller' ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = d.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb on dmb.doc_id = d.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc on dmc.doc_id = d.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} cd ON cd.ref_doc_id = d.doc_id AND cd.ref_item_id = d.item_id AND cd.status >= 6 ";

				$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %s ", 'delivery_order', 6 );
				$cond.= $wpdb->prepare( "AND mc.meta_value = %s ", $wh['code'] );

				if( !empty( $whc['client_company_code'] ) )
				{
					$cond.= "AND ma.meta_value IN ('" .implode( "','", $whc['client_company_code'] ). "') ";
				}

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
						$cond.= "AND ma.meta_value IN ('" .implode( "','", $filters['client'] ). "') ";
					else
						$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['client'] );
				}

				$grp = "GROUP BY h.doc_id, d.item_id ";
				$ord = "";

				$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

				if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
			
				return $query;
			}

		public function get_outlet_do_discrepancy( $filters = [], $run = false )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			if( ! $filters['seller'] ) return '';
			
			$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true, [ 'meta'=>['dbname'] ] );
			$dbname = ( $wh )? $wh['dbname'] : get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";

			if( $wh )
			{
				$whc = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$wh['code'], 'seller'=>$filters['seller'] ], [], true, [ 'meta'=>['client_company_code'] ] );
				if( !empty( $whc['client_company_code'] ) ) $whc['client_company_code'] = json_decode( $whc['client_company_code'], true );
			}

			if( ! current_user_cans( [ 'hide_amt_discrepancy_wh_reports' ] ) ) 
	    		$amt_fld = ", dmb.meta_value AS sprice, ROUND( d.bqty * dmb.meta_value, 2 ) AS selling_amt, dmc.meta_value AS ucost ";

			$field = "h.doc_id, h.docno, h.doc_date, h.created_at, h.status AS hstatus ";
			$field.= ", c.code AS client_code, c.name AS client_name, mb.meta_value AS direct_issue ";
			$field.= ", cat.slug AS category_code, cat.name AS category ";
			$field.= ", i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";
			$field.= ", d.bqty, d.uqty, ( d.bqty - d.uqty ) AS remain_qty, dma.meta_value AS sunit ";
			$field.= ", ( d.bqty - SUM( IFNULL(cd.bqty,0) ) ) AS unpost_qty ";
			$field.= $amt_fld;
			
			$table = "{$dbname}{$this->tables['document']} h ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb on mb.doc_id = d.doc_id AND mb.item_id = 0 AND mb.meta_key = 'direct_issue' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc on mc.doc_id = d.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supply_to_seller' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = d.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb on dmb.doc_id = d.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc on dmc.doc_id = d.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} cd ON cd.ref_doc_id = d.doc_id AND cd.ref_item_id = d.item_id AND cd.status >= 6 ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %s ", 'delivery_order', 6 );
			$cond.= $wpdb->prepare( "AND mc.meta_value = %s ", $wh['code'] );

			if( !empty( $whc['client_company_code'] ) )
			{
				$cond.= "AND ma.meta_value IN ('" .implode( "','", $whc['client_company_code'] ). "') ";
			}

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
					$cond.= "AND ma.meta_value IN ('" .implode( "','", $filters['client'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['client'] );
			}
			if( isset( $filters['product'] ) )
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND i.id = %s ", $filters['product'] );
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

			$grp = "GROUP BY h.doc_id, d.item_id ";
			$ord = "ORDER BY h.docno ASC, i.code ASC ";

			$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			$cond = "AND ( ( a.remain_qty != 0 AND a.hstatus = 6 ) OR ( a.unpost_qty != 0 AND a.remain_qty <= 0 ) ) ";
			$query = "SELECT a.* FROM ( {$query} ) a WHERE 1 {$cond} ";

			if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
			return $query;
		}

	//---- 21/10 gt discrepancy
	public function get_gt_discrepancy_report( $filters = [], $order = [], $args = [] )
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

		$sql = "";
		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		
		if( isset( $filters['seller'] ) && $filters['seller'] != $wh['id'] )		//Self view outlet
		{
			$sql = $this->get_outlet_gt_document( $filters, false );
		}
		else 									//view Self
		{
			unset( $filters['seller'] );
			
			if( $wh )
			{
				if( isset( $filters['warehouse_id'] ) )
					$outlets = apply_filters( 'wcwh_get_warehouse', [ 'parent'=>$wh['id'], 'code' => $filters['warehouse_id'] ], [], false, [ 'meta'=>['dbname'] ] );
				else
					$outlets = apply_filters( 'wcwh_get_warehouse', [ 'parent'=>$wh['id'] ], [], false, [ 'meta'=>['dbname'] ] );
			} 

			if( $outlets )	//DC
			{
				$sql = $this->get_main_gt_discrepancy( $filters, $outlets, false );
			}
			else 			//outlet
			{
				$filters['seller'] = $wh['id'];
				$sql = $this->get_outlet_gt_document( $filters, false );
			}
		}
		
		$results = [];
		if( $sql ) $results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_main_gt_discrepancy( $filters = [], $outlets = [], $run = false)
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$sync = true;
		$unsync = true;
		$temp_sql = [];

		if( isset( $filters['sync'] ) )
		{
			if( $filters['sync'] == 'yes' )
				$unsync = false;
			else if( $filters['sync'] == 'no' )
				$sync = false;
		}

		//-----outlet sql
		$outlet_sql = [];
		foreach( $outlets as $outlet)
		{
			if( !$outlet['dbname'] ) continue;
			
			$filters['seller'] = $outlet['id'];
			$outlet_sql[] = $this->get_outlet_gt_document( $filters, false );
		}
		$outlet_sql = implode(' UNION ALL ', $outlet_sql);

		//----get synced doc
		if( $sync )
		{
			$dc_sql = $this->get_gt_document( $filters, false );
			//-------22/11/2022 gt ref doc
			$f = "h.doc_id, h.docno, h.sdocno, h.warehouse_id, h.doc_date, h.created_at, h.hstatus ";
			$f.= ",b.gr_doc, b.do_doc ";
			$f.= ", h.bqty, h.uqty, h.remain_qty, h.unpost_qty ";
			$f.= ", h.category_code, h.category ";
			$f.= ", h.serial, h.item_code, h.item_name, h.uom ";
			$temp_sql[] = "SELECT {$f}, 'Synced' AS sync_status FROM ({$dc_sql}) h INNER JOIN ({$outlet_sql}) b ON h.sdocno = b.sdocno AND h.serial = b.serial ";
			//-------22/11/2022 gt ref doc
		}

		if( $unsync )
		{
			$filters['hstatus'] = array(1,6,9); //----excluding all synced gt in dc including the completed synced gt (status 9)
			$dc_sql = $this->get_gt_document( $filters, false );

			$temp_sql[] = "SELECT b.*, 'Not-Synced' AS sync_status FROM ({$dc_sql}) h RIGHT JOIN ({$outlet_sql}) b ON h.sdocno = b.sdocno AND h.serial = b.serial WHERE h.sdocno is NULL ";
			/*

			$join_sql = "SELECT h.*, '' AS sync_status FROM ({$dc_sql}) h LEFT JOIN ({$outlet_sql}) b ON h.sdocno = b.sdocno AND h.serial = b.serial WHERE b.sdocno is NULL UNION ALL SELECT b.*, 'Not-Synced' AS sync_status FROM ({$dc_sql}) h RIGHT JOIN ({$outlet_sql}) b ON h.sdocno = b.sdocno AND h.serial = b.serial WHERE h.sdocno is NULL ";
			$unsync_query = "SELECT * FROM ({$join_sql}) j WHERE 1 {$cond} {$grp} {$ord} ";
			*/
		}

		/* join dc self gt
		if( $o )
		{
			$dc_sql = $this->get_gt_document( $filters, false );
			$temp_sql[] = "SELECT h.*, '' AS sync_status FROM ({$dc_sql}) h LEFT JOIN ({$outlet_sql}) b ON h.sdocno = b.sdocno AND h.serial = b.serial WHERE b.sdocno is NULL ";
		}
		*/

		$field = 'a.* ';
		$table = "".implode(' UNION ALL ', $temp_sql)." ";
		$cond = '';
		$group ='';
		$order = "ORDER BY warehouse_id ASC, docno ASC, item_code ASC ";

		$query = "SELECT {$field} FROM ({$table}) a WHERE 1 {$cond} {$group} {$order} ";


		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );

		return $query;
	}

	public function get_gt_document( $filters = [], $run = false)
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$field = "h.doc_id, h.docno, h.sdocno, h.warehouse_id, h.doc_date, h.created_at, h.status AS hstatus ";
		//$field.= ", ma.meta_value AS ref_doc, mb.meta_value AS supplier_warehouse_code, mc.meta_value AS supplier_company_code ";
		$field.= ", d.bqty, d.uqty, ( d.bqty - IFNULL(d.uqty,0) ) AS remain_qty, ( d.bqty - SUM( IFNULL(cd.bqty,0) ) ) AS unpost_qty ";
		$field.= ", cat.slug AS category_code, cat.name AS category ";
		$field.= ", i.serial, i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} cd ON cd.ref_doc_id = d.doc_id AND cd.ref_item_id = d.item_id AND cd.status >= 6 ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";		
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

		//-----document meta
		/*
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = d.doc_id AND mb.item_id = 0 AND mb.meta_key = 'supplier_warehouse_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = d.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supplier_company_code' ";
		*/
		//-----document meta

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";;
		

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d ", 'good_return', 6 );

		if( isset( $filters['warehouse_id'] ) )
		{
			if( is_array( $filters['warehouse_id'] ) )
				$cond.= "AND h.warehouse_id IN ('" .implode( "','", $filters['warehouse_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.warehouse_id = %d ", $filters['warehouse_id'] );
		}

		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND i.serial IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.serial = %s ", $filters['product'] );
		}

		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.slug IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.slug IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.slug = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR cat.slug = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
		}

		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
		}

		if( isset( $filters['hstatus'] ) )
		{
			if( is_array($filters['hstatus']) )
				$cond.= "AND h.status IN ('" .implode( "','", $filters['hstatus'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.status = %d ", $filters['hstatus'] );
		}
		else
		{
			$cond.= "AND h.status BETWEEN 1 AND 6 ";
		}

		$grp = "GROUP BY h.doc_id, d.item_id ";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
		return $query;
	}

	public function get_outlet_gt_document( $filters = [], $run = false)
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( ! $filters['seller'] ) return '';

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true, [ 'meta'=>['dbname'] ] );
		$dbname = ( $wh )? $wh['dbname'] : get_warehouse_meta( $filters['seller'], 'dbname', true );
		$dbname = ( $dbname )? $dbname."." : "";

		$field = "h.doc_id, h.docno, h.sdocno, h.warehouse_id, h.doc_date, h.created_at, h.status AS hstatus ";
		$field.= ", ma.meta_value AS gr_doc, grp.docno AS do_doc ";
		//$field.= ", ma.meta_value AS ref_doc, mb.meta_value AS supplier_warehouse_code, mc.meta_value AS supplier_company_code ";
		$field.= ",d.bqty, d.uqty, ( d.bqty - IFNULL(d.uqty,0) ) AS remain_qty, (d.bqty - SUM( IFNULL(cd.bqty,0) )) AS unpost_qty ";

		$field.= ", cat.slug AS category_code, cat.name AS category ";
		$field.= ", i.serial, i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} cd ON cd.ref_doc_id = d.doc_id AND cd.ref_item_id = d.item_id AND cd.status >= 6 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";		
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";
		
		//-------22/11/2022 gt ref doc		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} map ON map.docno = ma.meta_value AND map.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} grp ON grp.doc_id = map.parent AND grp.status > 0 ";
		//-------22/11/2022 gt ref doc

		//-----document meta
		/*
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = d.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = d.doc_id AND mb.item_id = 0 AND mb.meta_key = 'supplier_warehouse_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = d.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supplier_company_code' ";
		*/
		//-----document meta

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %s ", 'good_return', 6 );

		if( isset( $filters['warehouse_id'] ) )
		{
			if( is_array( $filters['warehouse_id'] ) )
				$cond.= "AND h.warehouse_id IN ('" .implode( "','", $filters['warehouse_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.warehouse_id = %d ", $filters['warehouse_id'] );
		}

		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND i.serial IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.serial = %s ", $filters['product'] );
		}

		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.slug IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.slug IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.slug = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR cat.slug = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
		}

		$grp = "GROUP BY h.doc_id, d.item_id ";
		$ord = "";

		$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
			
		return $query;
	}
	//---- 21/10 gt discrepancy
	
} //class

}