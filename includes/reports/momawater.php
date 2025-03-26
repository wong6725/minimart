<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_MOMAwater_Rpt" ) ) 
{
	
class WCWH_MOMAwater_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "MOMAwater";

	public $tplName = array(
		'export' => 'exportMOMAwater',
		'print' => 'printMOMAwater',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $def_date_type = 'post_date';

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

			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",

			"client"		=> $prefix."client",
			"clientmeta"	=> $prefix."clientmeta",

			"items"			=> $prefix."items",
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
			"status"		=> $prefix."status",
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",
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
						case 'detail':
							$datas['filename'] = 'MOMAwater Detail ';
						break;
						case 'summary':
						default:
							$datas['filename'] = 'MOMAwater Summary ';
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
					if( !empty( $datas['date_type'] ) ) $params['date_type'] = $datas['date_type'];
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['date_type'] ) ) $params['date_type'] = $datas['date_type'];
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
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
			case 'detail':
				return $this->get_momawater_detail_report( $params );
			break;
			case 'summary':
			default:
				return $this->get_momawater_summary_report( $params );
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
			case 'detail':
				$filename = "MOMAwater-Detail";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'MOMAwater Detail';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'MOMAwater';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Client' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'SO Number' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Product Description' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'8%', 'class'=>['rightered'], 'num'=>1 ],
					'UOM' => [ 'width'=>'8%', 'class'=>['centered'] ],
					//'Cost' => [ 'width'=>'10%', 'class'=>['rightered'], 'num'=>1 ],
					'Amount' => [ 'width'=>'10%', 'class'=>['rightered'], 'num'=>1 ],
				];

				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					$costs = [];
					foreach( $datas as $i => $data )
					{
						$client = [];
						if( $data['client_code'] ) $client[] = $data['client_code'];
						if( $data['client_name'] ) $client[] = $data['client_name'];
						$data['client'] = implode( ' - ', $client );

						$product = [];
						if( $data['item_code'] ) $product[] = $data['item_code'];
						if( $data['item_name'] ) $product[] = $data['item_name'];
						$data['product'] = implode( ' - ', $product );

						$regrouped[ $data['client'] ][ $data['docno'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['client'] ]+= 1;
						$rowspan[ $data['docno'] ]+= 1;

						//totals
						$totals[ $data['client'] ][ $data['docno'] ]+= $data['amount'];
						$costs[ $data['client'] ][ $data['docno'] ]+= $data['total_cost'];
					}

					$details = [];
					if( $regrouped )
					{
						$total = 0; $cost = 0;
						foreach( $regrouped as $main => $docs )
						{
							$subtotal = 0; $cost_subtotal = 0;
							$client_added = '';
							foreach( $docs as $doc => $items )
							{
								$doc_added = '';
								if( $totals[ $main ][ $doc ] )
								{
									$rowspan[ $doc ]+= 0;
									$rowspan[ $main ]+= count( $totals[ $main ] ) + 1;
								}

								foreach( $items as $i => $vals )
								{
									$row = [

'client' => [ 'value'=>$vals['client'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $main ] ],
'docno' => [ 'value'=>$vals['docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'doc_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['doc_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'product' => [ 'value'=>$vals['product'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
//'cost' => [ 'value'=>$vals['total_cost'], 'class'=>['rightered'], 'num'=>1 ],
'amt' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

									];

									if( $client_added == $main ) $row['client'] = [];
									$client_added = $main;

									if( $doc_added == $doc ) 
									{
										$row['docno'] = [];
										$row['doc_date'] = [];
									}
									$doc_added = $doc;

									$details[] = $row;
								}

								/*$details[] = [
									'client' => [],
									'docno' => [],
									'doc_date' => [],
									'product' => [ 'value'=>'SO Total:', 'class'=>['leftered','bold'], 'colspan'=>3 ],
									'cost' => [ 'value'=>$costs[ $main ][ $doc ], 'class'=>['rightered','bold'], 'num'=>1 ],
									'amt' => [ 'value'=>$totals[ $main ][ $doc ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];*/
								$cost_subtotal+= $costs[ $main ][ $doc ];
								$subtotal+= $totals[ $main ][ $doc ];
							}

							$details[] = [
								'client' => [],
								'docno' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>5 ],
								//'cost' => [ 'value'=>$cost_subtotal, 'class'=>['rightered','bold'], 'num'=>1 ],
								'amt' => [ 'value'=>$subtotal, 'class'=>['rightered','bold'], 'num'=>1 ],
							];

							$total+= $subtotal;
							$cost+= $cost_subtotal;
						}

						$details[] = [
							'client' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>6 ],
							//'cost' => [ 'value'=>$cost, 'class'=>['rightered','bold'], 'num'=>1 ],
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
			case 'summary':
			default:
				$filename = "MOMAwater-Summary";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'MOMAwater Summary';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'MOMAwater';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Client' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Product Description' => [ 'width'=>'30%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'10%', 'class'=>['rightered'], 'num'=>1 ],
					'UOM' => [ 'width'=>'10%', 'class'=>['centered'] ],
					//'Cost' => [ 'width'=>'15%', 'class'=>['rightered'], 'num'=>1 ],
					'Amount' => [ 'width'=>'15%', 'class'=>['rightered'], 'num'=>1 ],
				];

				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					$costs = [];
					foreach( $datas as $i => $data )
					{
						$client = [];
						if( $data['client_code'] ) $client[] = $data['client_code'];
						if( $data['client_name'] ) $client[] = $data['client_name'];
						$data['client'] = implode( ' - ', $client );

						$product = [];
						if( $data['item_code'] ) $product[] = $data['item_code'];
						if( $data['item_name'] ) $product[] = $data['item_name'];
						$data['product'] = implode( ' - ', $product );

						$regrouped[ $data['client'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['client'] ]+= 1;

						//totals
						$totals[ $data['client'] ]+= $data['amount'];
						$costs[ $data['client'] ]+= $data['total_cost'];
					}

					$details = [];
					if( $regrouped )
					{
						$total = 0; $cost = 0;
						foreach( $regrouped as $key => $items )
						{
							$doc_added = '';
							if( $totals[ $key ] )
							{
								$rowspan[ $key ]+= 1;
							}

							foreach( $items as $i => $vals )
							{
								$row = [

'client' => [ 'value'=>$vals['client'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $key ] ],
'product' => [ 'value'=>$vals['product'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
//'cost' => [ 'value'=>$vals['total_cost'], 'class'=>['rightered'], 'num'=>1 ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

								];

								if( $doc_added == $key ) 
								{
									$row['client'] = [];
								}
								$doc_added = $key;

								$details[] = $row;
							}

							$details[] = [
								'client' => [],
								'product' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>3 ],
								//'cost' => [ 'value'=>$costs[ $key ], 'class'=>['rightered','bold'], 'num'=>1 ],
								'amount' => [ 'value'=>$totals[ $key ], 'class'=>['rightered','bold'], 'num'=>1 ],
							];
							
							$total+= $totals[ $key ];
							$cost+= $costs[ $key ];
						}

						$details[] = [
							'client' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>4 ],
							//'cost' => [ 'value'=>$cost, 'class'=>['rightered','bold'], 'num'=>1 ],
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

	public function export_form( $type = 'summary' )
	{
		$action_id = 'momawater_report_export';
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
			case 'detail':
				do_action( 'wcwh_templating', 'report/export-mwt_detail-report.php', $this->tplName['export'], $args );
			break;
			case 'summary':
			default:
				do_action( 'wcwh_templating', 'report/export-mwt_summary-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'momawater_report_export';
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
			case 'detail':
				do_action( 'wcwh_templating', 'report/export-mwt_detail-report.php', $this->tplName['print'], $args );
			break;
			case 'summary':
			default:
				do_action( 'wcwh_templating', 'report/export-mwt_summary-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	MOMAwater Summary
	 */
	public function momawater_summary_report( $filters = array(), $order = array() )
	{
		$action_id = 'momawater_summary_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/mwtSummaryList.php" ); 
			$Inst = new WCWH_MWTSummary_Report();
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
				'.qty, .avg_cost, .total_cost, .avg_price, .amount, .profit' => [ 'text-align'=>'right !important' ],
				'#qty a span, #total_cost a span, #amount a span,  #profit a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_momawater_summary_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	MOMAwater Detail
	 */
	public function momawater_detail_report( $filters = array(), $order = array() )
	{
		$action_id = 'momawater_detail_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/mwtDetailList.php" ); 
			$Inst = new WCWH_MWTDetail_Report();
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
				'.qty, .cost, .total_cost, .price, .amount, .profit' => [ 'text-align'=>'right !important' ],
				'#qty a span, #total_cost a span, #amount a span, #profit a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_momawater_detail_report( $filters, $order, [] );
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
	public function get_momawater_summary_report( $filters = [], $order = [], $args = [] )
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

		if( $this->setting['momawater_report']['items'] )
			$filters['product'] = $this->setting['momawater_report']['items'];

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$sql = $this->get_momawater_detail_report( $filters, [], ['sql'=>1] );

		//-------------------------------------------------------------------
		$field = "a.client_code, a.client_name ";
		$field.= ", a.item_code, a.item_name, a.uom, SUM( a.qty ) AS qty ";
		$field.= ", ROUND( SUM( a.total_cost ) / SUM( a.qty ), 2 ) AS avg_cost ";
		$field.= ", ROUND( SUM( a.total_cost ), 2 ) AS total_cost ";
		$field.= ", ROUND( SUM( a.amount ) / SUM( a.qty ), 2 ) AS avg_price";
		$field.= ", ROUND( SUM( a.amount ), 2 ) AS amount ";
		$field.= ", ROUND( SUM( a.amount ) - SUM( a.total_cost ), 2 ) AS profit ";
		
		$table = "( {$sql} ) a ";
		$cond = "";

		$grp = "GROUP BY a.client_code, a.item_code ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.client_code' => 'ASC', 'a.item_code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		//pd($sql);
		return $results;
	}

	public function get_momawater_detail_report( $filters = [], $order = [], $args = [] )
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

		if( $this->setting['momawater_report']['items'] )
			$filters['product'] = $this->setting['momawater_report']['items'];

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
		
		$field = "c.code AS client_code, c.name AS client_name, ph.docno, ph.doc_date, ph.created_at ";
		$field.= ", i.code AS item_code, i.name AS item_name, i._uom_code AS uom, d.bqty AS qty ";
		//$field.= ", @cost := ROUND( IF( ti.unit_cost > 0, ( ti.unit_cost * ti.bqty / d.bqty ), dmc.meta_value ), 5 ) AS cost ";
		//$field.= ", @tcost := ROUND( IF( dmd.meta_value > 0, dmd.meta_value, IF( ti.total_cost > 0, ti.total_cost, d.bqty * @cost ) ), 2 ) AS total_cost ";
		$field.= ", @cost := ROUND( IF( ti.weighted_total > 0, ti.weighted_total / d.bqty, dmc.meta_value ), 5 ) AS cost ";
		$field.= ", @tcost := ROUND( IF( ti.weighted_total > 0, ti.weighted_total, dmd.meta_value ), 2 ) AS total_cost ";
		$field.= ", ROUND( dmb.meta_value, 2 ) AS price";
		$field.= ", @amt := ROUND( d.bqty * dmb.meta_value, 2 ) AS amount ";
		$field.= ", ROUND( IF( @amt, @amt, 0 ) - @tcost, 2 ) AS profit ";
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc_id' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'good_issue_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'client_company_code' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} me ON me.doc_id = h.doc_id AND me.item_id = 0 AND me.meta_key = 'delivery_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'tcost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id AND t.status != 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id AND ti.status != 0 ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_conversion']} tc ON tc.item_id = d.item_id AND tc.status != 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = ma.meta_value ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['document']} pc ON pc.parent = h.doc_id AND pc.warehouse_id = h.warehouse_id AND pc.status != 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = mc.meta_value  ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		
		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d ", 'delivery_order', 6 );
		$cond.= $wpdb->prepare( "AND ph.doc_type = %s ", 'sale_order' );
		
		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['date_type'] ) )
		{
			$date_type = $filters['date_type'];
		}
		$date_type = empty( $date_type )? $this->def_date_type : $date_type;
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.{$date_type} >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.{$date_type} <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['client'] ) )
		{
			if( is_array( $filters['client'] ) )
				$cond.= "AND c.id IN ('" .implode( "','", $filters['client'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.id = %d ", $filters['client'] );
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND d.product_id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND d.product_id = %d ", $filters['product'] );
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
                $cd[] = "h.docno LIKE '%".$kw."%' ";
				$cd[] = "ph.docno LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'c.code' => 'ASC', 'docno' => 'ASC', 'i.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $args['sql'] ) return $sql;

		$results = $wpdb->get_results( $sql , ARRAY_A );
		//pd($sql);
		return $results;
	}
	
} //class

}