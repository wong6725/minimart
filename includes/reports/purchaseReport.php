<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Purchase_Rpt" ) ) 
{
	
class WCWH_Purchase_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "Purchase_Rpt";

	public $tplName = array(
		'export' => 'exportPurchase',
		'print' => 'printPurchase',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $doc_opts = [];

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

			"supplier"		=> $prefix."supplier",
			"suppliermeta"	=> $prefix."suppliermeta",
			"supplier_tree"	=> $prefix."supplier_tree",

			"items"			=> $prefix."items",
			"itemsmeta"		=> $prefix."itemsmeta",
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			"item_group"	=> $prefix."item_group",
			"uom"			=> $prefix."uom",
			"reprocess_item"=> $prefix."reprocess_item",

			"payment_method"=> $prefix."payment_method",
			
			"status"		=> $prefix."status",

			"temp_po"		=> "temp_po",
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
						case 'e_payment':
							$datas['filename'] = 'e-Payment ';
						break;
						case 'payment_method':
							$datas['filename'] = 'PO by Payment Method ';
						break;
						case 'summary':
						default:
							$datas['filename'] = 'PO Summary ';
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
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['supplier'] ) ) $params['supplier'] = $datas['supplier'];
					if( !empty( $datas['payment_method'] ) ) $params['payment_method'] = $datas['payment_method'];
					if( !empty( $datas['doc_id'] ) ) $params['doc_id'] = $datas['doc_id'];
					if( !empty( $datas['grouping'] ) ) $params['grouping'] = $datas['grouping'];
					if( !empty( $datas['sequence_doc'] ) ) $params['sequence_doc'] = $datas['sequence_doc'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					$params['exporting'] = 1;
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['supplier'] ) ) $params['supplier'] = $datas['supplier'];
					if( !empty( $datas['payment_method'] ) ) $params['payment_method'] = $datas['payment_method'];
					if( !empty( $datas['doc_id'] ) ) $params['doc_id'] = $datas['doc_id'];
					if( !empty( $datas['grouping'] ) ) $params['grouping'] = $datas['grouping'];
					if( !empty( $datas['sequence_doc'] ) ) $params['sequence_doc'] = $datas['sequence_doc'];
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
			case 'e_payment':
				return $this->get_sap_e_payment_report( $params );
			break;
			case 'payment_method':
				return $this->get_po_payment_method_report( $params );
			break;
			case 'summary':
			default:
				return $this->get_po_summary_report( $params );
			break;
		}
	}

	public function print_handler( $params = array(), $opts = array() )
	{
		$params['is_print'] = 1;
		$datas = $this->export_data_handler( $params );

		$type = $params['export_type'];
		unset( $params['export_type'] );
		$date_format = get_option( 'date_format' );
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		
		switch( $type )
		{
			case 'e_payment':
				$filename = "e-Payment";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'e-Payment';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'e-Payment';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				if( $this->setting['general_report']['confirm_by'] > 0 ) $superior = get_userdata( $this->setting['general_report']['confirm_by'] );
				if( $superior && in_array( 'warehouse_supervisor', $superior->roles ) && in_array( 'warehouse_executive', $user_info->roles ) )
				{
					$document['footing']['verified'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
					$document['footing']['verified_date'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );

					$document['footing']['confirmed'] = ( $superior->first_name )? $superior->first_name : $superior->display_name;
					$document['footing']['confirmed_date'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				}
				
				$document['detail_title'] = [
					'Supplier' => [ 'width'=>'14%', 'class'=>['leftered'] ],
					'PO Number' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'PO Date' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'Invoice' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Inv Date' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'GR Date' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'Item Category' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Amount' => [ 'width'=>'10%', 'class'=>[] ],
				];
				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$supplier = [];
						if( $data['supplier_code'] ) $supplier[] = $data['supplier_code'];
						if( $data['supplier_name'] ) $supplier[] = $data['supplier_name'];

						$product = [];
						if( $data['category_code'] ) $product[] = $data['category_code'];
						if( $data['category_name'] ) $product[] = $data['category_name'];
						
						$data['supplier_name'] = implode( ' - ', $supplier );
						$data['product_name'] = implode( ' - ', $product );
						$regrouped[ $data['supplier_code'] ][ $data['po_no'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['supplier_code'] ]+= 1;
						$rowspan[ $data['po_no'] ]+= 1;

						//totals
						$totals[ $data['supplier_code'] ][ $data['po_no'] ]+= $data['amount'];
					}
					
					$details = [];
					if( $regrouped )
					{
						$total = 0;
						foreach( $regrouped as $supplier => $docs )
						{
							$subtotal = 0;
							$supplier_added = '';
							foreach( $docs as $doc => $items )
							{
								$doc_added = '';
								if( $totals[ $supplier ][ $doc ] )
								{
									$rowspan[ $doc ]+= 1;
									$rowspan[ $supplier ]+= count( $totals[ $supplier ] ) + 1;
								}

								foreach( $items as $i => $vals )
								{
									$row = [

'supplier' => [ 'value'=>$vals['supplier_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $supplier ] ],
'po_no' => [ 'value'=>$vals['po_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'po_date' => [ 'value'=>$vals['po_date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'invoice_no' => [ 'value'=>$vals['invoice_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ], 'chop'=>15 ],
'invoice_date' => [ 'value'=>$vals['invoice_date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'gr_date' => [ 'value'=>$vals['gr_date'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'product' => [ 'value'=>$vals['product_name'], 'class'=>['leftered'] ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

									];

									if( $supplier_added == $supplier ) $row['supplier'] = [];
									$supplier_added = $supplier;

									if( $doc_added == $doc ) 
									{
										$row['po_no'] = [];
										$row['po_date'] = [];
										$row['invoice_no'] = [];
										$row['invoice_date'] = [];
										$row['gr_date'] = [];
									}
									$doc_added = $doc;

									$details[] = $row;
								}

								$details[] = [
									'supplier' => [],
									'po_no' => [],
									'po_date' => [],
									'invoice_no' => [],
									'invoice_date' => [],
									'gr_date' => [],
									'product' => [ 'value'=>'PO Total:', 'class'=>['leftered','bold'], 'colspan'=>1 ],
									'amount' => [ 'value'=>$totals[ $supplier ][ $doc ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
								$subtotal+= $totals[ $supplier ][ $doc ];
							}

							$details[] = [
								'supplier' => [],
								'po_no' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>6 ],
								'amt' => [ 'value'=>$subtotal, 'class'=>['rightered','bold'], 'num'=>1 ],
							];

							$total+= $subtotal;
						}

						$details[] = [
							'supplier' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>7 ],
							'amt' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
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
			case 'payment_method':
				$filename = "PO-PaymentMethod";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'PO by Payment Method';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'PO by Payment Method';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'PO No.' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Supplier' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Invoice' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Total Amount' => [ 'width'=>'17%', 'class'=>['rightered'], 'num'=>1 ],
					'Payment Method' => [ 'width'=>'18%', 'class'=>['centered'] ],
				];

				if( $datas )
				{
					$total_amt = 0;
					$details = [];
					foreach( $datas as $i => $data )
					{
						$supplier = [];
						if( $data['supplier_code'] ) $supplier[] = $data['supplier_code'];
						if( $data['supplier_name'] ) $supplier[] = $data['supplier_name'];
						$data['supplier'] = implode( ' - ', $supplier );

						$row = [

'docno' => [ 'value'=>$data['docno'], 'class'=>['leftered'] ],
'doc_date' => [ 'value'=>$data['doc_date'], 'class'=>['leftered'] ],
'supplier' => [ 'value'=>$data['supplier'], 'class'=>['leftered'] ],
'invoice' => [ 'value'=>$data['invoice'], 'class'=>['leftered'] ],
'total_amount' => [ 'value'=>$data['total_amount'], 'class'=>['rightered'], 'num'=>1 ],
'payment_method' => [ 'value'=>$data['payment_method'], 'class'=>['centered'] ],

						];

						$total_amt+= $data['total_amount'];

						$details[] = $row;
					}

					$details[] = [
						'docno' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>4 ],
						'total_amount' => [ 'value'=>$total_amt, 'class'=>['rightered','bold'], 'num'=>1 ],
						'payment_method' => [ 'value'=>'', 'class'=>['centered','bold'] ],
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
			case 'summary':
			default:
				$filename = "PO-Summary";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'PO Summary';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'PO Summary';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				if( $this->setting['general_report']['confirm_by'] > 0 ) $superior = get_userdata( $this->setting['general_report']['confirm_by'] );
				if( $superior && in_array( 'warehouse_supervisor', $superior->roles ) && in_array( 'warehouse_executive', $user_info->roles ) )
				{
					$document['footing']['verified'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
					$document['footing']['verified_date'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );

					$document['footing']['confirmed'] = ( $superior->first_name )? $superior->first_name : $superior->display_name;
					$document['footing']['confirmed_date'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				}
				
				$document['detail_title'] = [
					'Supplier' => [ 'width'=>'12%', 'class'=>['leftered'] ],
					'PO Number' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Date' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Invoice' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Product Description' => [ 'width'=>'20%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'6%', 'class'=>[] ],
					'UOM' => [ 'width'=>'5%', 'class'=>[] ],
					//'FOC' => [ 'width'=>'5%', 'class'=>[] ],
					'Unit Price' => [ 'width'=>'8%', 'class'=>[] ],
					'Amount' => [ 'width'=>'9%', 'class'=>[] ],
				];
				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$supplier = [];
						if( $data['supplier_code'] ) $supplier[] = $data['supplier_code'];
						if( $data['supplier'] ) $supplier[] = $data['supplier'];

						$product = [];
						switch( $opts['product_desc'] )
						{
							case 'item':
								if( $data['item_code'] ) $product[] = $data['item_code'];
								if( $data['item_name'] ) $product[] = $data['item_name'];
							break;
							case 'category':
							default:
								if( $data['category_code'] ) $product[] = $data['category_code'];
								if( $data['category'] ) $product[] = $data['category'];
							break;
						}
						
						$data['supplier_name'] = implode( ' - ', $supplier );
						$data['product_name'] = implode( ' - ', $product );
						$regrouped[ $data['supplier_code'] ][ $data['docno'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['supplier_code'] ]+= 1;
						$rowspan[ $data['docno'] ]+= 1;

						//totals
						if( $data['status'] > 6 )
						{
							$totals[ $data['supplier_code'] ][ $data['docno'] ]+= $data['fin_amount'];
						}
						else
						{
							$totals[ $data['supplier_code'] ][ $data['docno'] ]+= $data['line_amount'];
						}
					}
					
					$details = [];
					if( $regrouped )
					{
						$total = 0;
						foreach( $regrouped as $supplier => $docs )
						{
							$subtotal = 0;
							$supplier_added = '';
							foreach( $docs as $doc => $items )
							{
								$doc_added = '';
								if( $totals[ $supplier ][ $doc ] )
								{
									$rowspan[ $doc ]+= 1;
									$rowspan[ $supplier ]+= count( $totals[ $supplier ] ) + 1;
								}

								foreach( $items as $i => $vals )
								{
									$row = [

'supplier' => [ 'value'=>$vals['supplier_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $supplier ] ],
'docno' => [ 'value'=>$vals['docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'doc_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['doc_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'invoice' => [ 'value'=>$vals['invoice'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ], 'chop'=>15 ],
'product' => [ 'value'=>$vals['product_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>( $vals['status'] > 6 ? $vals['fin_qty'] : $vals['qty']+$vals['foc'] ), 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
//'foc' => [ 'value'=>$vals['foc'], 'class'=>['rightered'], 'num'=>1 ],
'uprice' => [ 'value'=>( $vals['status'] > 6 ? $vals['avg_price'] : $vals['uprice'] ), 'class'=>['rightered'], 'num'=>1 ],
'amt' => [ 'value'=>( $vals['status'] > 6 ? $vals['fin_amount'] : $vals['line_amount'] ), 'class'=>['rightered'], 'num'=>1 ],

									];

									if( $supplier_added == $supplier ) $row['supplier'] = [];
									$supplier_added = $supplier;

									if( $doc_added == $doc ) 
									{
										$row['docno'] = [];
										$row['doc_date'] = [];
										$row['invoice'] = [];
									}
									$doc_added = $doc;

									$details[] = $row;
								}

								$details[] = [
									'supplier' => [],
									'docno' => [],
									'doc_date' => [],
									'invoice' => [],
									'product' => [ 'value'=>'PO Total:', 'class'=>['leftered','bold'], 'colspan'=>4 ],
									'amt' => [ 'value'=>$totals[ $supplier ][ $doc ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
								$subtotal+= $totals[ $supplier ][ $doc ];
							}

							$details[] = [
								'supplier' => [],
								'docno' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>7 ],
								'amt' => [ 'value'=>$subtotal, 'class'=>['rightered','bold'], 'num'=>1 ],
							];

							$total+= $subtotal;
						}

						$details[] = [
							'supplier' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>8 ],
							'amt' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
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
		$action_id = 'purchase_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		if( $this->doc_opts ) $args['doc_opts'] = $this->doc_opts;

		switch( strtolower( $type ) )
		{
			case 'e_payment':
				do_action( 'wcwh_templating', 'report/export-purchase-epayment-report.php', $this->tplName['export'], $args );
			break;
			case 'payment_method':
				do_action( 'wcwh_templating', 'report/export-purchase-pm-report.php', $this->tplName['export'], $args );
			break;
			case 'summary':
			default:
				do_action( 'wcwh_templating', 'report/export-purchase-summary-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'purchase_report_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		if( $this->doc_opts ) $args['doc_opts'] = $this->doc_opts;

		switch( strtolower( $type ) )
		{
			case 'e_payment':
				do_action( 'wcwh_templating', 'report/export-purchase-epayment-report.php', $this->tplName['print'], $args );
			break;
			case 'payment_method':
				do_action( 'wcwh_templating', 'report/export-purchase-pm-report.php', $this->tplName['print'], $args );
			break;
			case 'summary':
			default:
				do_action( 'wcwh_templating', 'report/export-purchase-summary-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	PO Summary
	 */
	public function po_summary_report( $filters = array(), $order = array() )
	{
		$action_id = 'po_summary_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/purchaseSummaryList.php" ); 
			$Inst = new WCWH_PO_Summary_report();
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

			//last search-----------------------------------------------------------------
			//defaulter
				$def_filters = [];
				$def_filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $date_from ) );
				$def_filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $date_to." 23:59:59" ) );
				$def_filters['doc_stat'] = 'all';
				$def_filters['s'] = "";
				if( $this->seller ) $def_filters['seller'] = $this->seller;
				//pd(json_encode( $def_filters ), 0, 22);
			//current
				$curr_filters = $filters; 
				unset( $curr_filters['orderby'] );
				unset( $curr_filters['order'] );
				unset( $curr_filters['qs'] );
				unset( $curr_filters['paged'] );
				unset( $curr_filters['status'] );
				unset( $curr_filters['supplier'] );
				//pd(json_encode($curr_filters), 0, 22);
			//previous
				$prev_filters = get_transient( get_current_user_id().$this->seller.$action_id );
				//pd(json_encode( $prev_filters ), 0, 22);
			
			if( $prev_filters !== false && json_encode( $prev_filters ) != json_encode( $def_filters ) &&
				json_encode( $curr_filters ) == json_encode( $def_filters ) )
				$filters = $prev_filters;
			if( json_encode( $curr_filters ) != json_encode( $def_filters ) ||
				json_encode( $curr_filters ) != json_encode( $prev_filters ) )
				set_transient( get_current_user_id().$this->seller.$action_id, $curr_filters, 0 );

			//----------------------------------------------------------------------------
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.qty, .foc, .uprice, .line_amount, .avg_price, .fin_qty, .fin_amount' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_po_summary_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = [];
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['docno'];
				$doc[] = $dat['doc_id'];
			}
			if( $doc_opts ) 
			{
				$this->doc_opts = $doc_opts;
				$Inst->doc_opts = $doc_opts;
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	PO Payment Method
	 */
	public function po_payment_method_report( $filters = array(), $order = array() )
	{
		$action_id = 'po_payment_method_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/purchasePaymentMethodList.php" ); 
			$Inst = new WCWH_PO_PaymentMethod_report();
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
				'.total_amount' => [ 'text-align'=>'right !important' ],
				'#total_amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_po_payment_method_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = [];
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['docno'];
				$doc[] = $dat['doc_id'];
			}
			if( $doc_opts ) 
			{
				$this->doc_opts = $doc_opts;
				$Inst->doc_opts = $doc_opts;
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	SAP e-Payment
	 */
	public function sap_e_payment_report( $filters = array(), $order = array() )
	{
		$action_id = 'sap_e_payment_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/purchaseEPaymentList.php" ); 
			$Inst = new WCWH_PO_ePayment_report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			$filters['s'] = !empty( $filters['s'] )? $filters['s'] : "";
			if( $this->seller ) $filters['seller'] = $this->seller;

			//last search-----------------------------------------------------------------
			//defaulter
				$def_filters = [];
				$def_filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $date_from ) );
				$def_filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $date_to." 23:59:59" ) );
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

			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 100;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.amount, .dn_amt, .cn_amt, .final_amt' => [ 'text-align'=>'right !important' ],
				'#amount a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_sap_e_payment_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = [];
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['po_no'];
				$doc[] = $dat['doc_id'];
			}
			if( $doc_opts ) 
			{
				$this->doc_opts = $doc_opts;
				$Inst->doc_opts = $doc_opts;
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
	public function get_po_summary_report( $filters = [], $order = [], $args = [] )
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

		if( isset( $filters['sequence_doc'] ) )
		{
			$filters['sequence_doc'] = array_filter( $filters['sequence_doc'] );

			$query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_po']} (
				`id` int(11) NOT NULL AUTO_INCREMENT, 
				`po_no` varchar(50) NOT NULL DEFAULT '',
				PRIMARY KEY (`id`), 
				KEY `po_no` (`po_no`) 
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1; ";
			$query = $wpdb->query( $query );

			$query = "INSERT INTO {$this->tables['temp_po']} (`po_no`) VALUES ";
			$ql = [];
			foreach( $filters['sequence_doc'] as $i => $sequence_doc )
			{
				$filters['sequence_doc'][$i] = trim( $sequence_doc );
				$ql[] = "( '{$filters['sequence_doc'][$i]}' )";
			}
			$query.= implode( ', ', $ql )."; ";
			$query = $wpdb->query( $query );
		}

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

	    //====================================================================
	    //GR subquery
	    $gr_tbl = ""; $gr_cond = "";
	    if( isset( $filters['sequence_doc'] ) )
		{
			$gr_cond.= "AND a.docno IN ('" .implode( "','", $filters['sequence_doc'] ). "') ";
		}
		else
		{
			if( isset( $filters['doc_id'] ) )
			{
				if( is_array( $filters['doc_id'] ) )
					$gr_cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
				else
					$gr_cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
			}
			if( isset( $filters['from_date'] ) )
			{
				$gr_cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$gr_cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
			}
		}

	    $gr_sub = "SELECT a.doc_id, a.docno, GROUP_CONCAT( b.docno separator ',' ) AS gr_docno
			FROM {$dbname}{$this->tables['document']} a 
			LEFT JOIN {$dbname}{$this->tables['document']} b ON b.parent = a.doc_id AND b.status > 1
			WHERE 1 AND a.doc_type = 'purchase_order' AND a.status > 0 AND b.doc_type = 'good_receive' 
			{$gr_cond}
			GROUP BY a.doc_id ";

		//====================================================================

		$field = "a.doc_id, b.item_id, s.name AS supplier, s.code AS supplier_code, a.docno, a.doc_date, a.created_at
			, mb.meta_value AS invoice, md.meta_value AS ref_po_no, gr.gr_docno, mc.meta_value AS remark ";
		$field.= ", cat.slug AS category_code, cat.name AS category, i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";
		$field.= ", ROUND( b.bqty - IF( id.meta_value != 0, id.meta_value, 0 ), 2 ) AS qty, IF( id.meta_value != 0, id.meta_value, 0 ) AS foc ";
		$field.= ", ia.meta_value AS uprice, IFNULL( ib.meta_value, 0 ) AS line_amount, ic.meta_value AS avg_price ";
		$field.= ", b.uqty AS fin_qty, ( b.uqty * ( IFNULL( ib.meta_value, 0 ) / b.bqty ) ) AS fin_amount ";

		if( $filters['is_print'] )
		{
			$field.= ", a.status ";
		}
		
		$table = "{$dbname}{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = 0 AND ma.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = a.doc_id AND mb.item_id = 0 AND mb.meta_key = 'invoice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = a.doc_id AND mc.item_id = 0 AND mc.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = a.doc_id AND md.item_id = 0 AND md.meta_key = 'integrated_po' ";
		$table.= "LEFT JOIN ( {$gr_sub} ) gr ON gr.doc_id = a.doc_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} s ON s.code = ma.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['supplier_tree']} ";
			$subsql.= "WHERE 1 AND descendant = s.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} ss ON ss.id = ( {$subsql} ) ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = b.doc_id AND ia.item_id = b.item_id AND ia.meta_key = 'uprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = b.doc_id AND ib.item_id = b.item_id AND ib.meta_key = 'total_amount'  ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ic ON ic.doc_id = b.doc_id AND ic.item_id = b.item_id AND ic.meta_key = 'avg_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} id ON id.doc_id = b.doc_id AND id.item_id = b.item_id AND id.meta_key = 'foc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = b.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status > %d ", 'purchase_order', 0 );
		
		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}

		if( isset( $filters['sequence_doc'] ) )
		{
			$cond.= "AND ( a.docno IN ('" .implode( "','", $filters['sequence_doc'] ). "') ";
			$cond.= "OR md.meta_value IN ('" .implode( "','", $filters['sequence_doc'] ). "') ) ";

			$table.= "LEFT JOIN {$this->tables['temp_po']} sp ON sp.po_no = a.docno ";

			$order = [ 'sp.id'=>'asc', 'cat.slug' => 'ASC', 'i.code' => 'ASC' ];
		}
		else
		{
			if( isset( $filters['doc_id'] ) )
			{
				if( is_array( $filters['doc_id'] ) )
					$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
			}
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
			}
		}

		if( isset( $filters['supplier'] ) )
		{
			if( is_array( $filters['supplier'] ) )
			{
				$catcd = "s.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$catcd.= "OR ss.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "s.id = %d ", $filters['supplier'] );
				$catcd = $wpdb->prepare( "OR ss.id = %d ", $filters['supplier'] );
				$cond.= "AND ( {$catcd} ) ";
			}
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
		if( isset( $filters['doc_stat'] ) )
		{
			if( $filters['doc_stat'] == 'posted' )
				$cond.= $wpdb->prepare( "AND a.status >= %s ", 6 );
			else if( $filters['doc_stat'] == 'all' )
				$cond.= $wpdb->prepare( "AND a.status > %s ", 0 );
			else
				$cond.= $wpdb->prepare( "AND a.status = %s ", $filters['doc_stat'] );
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
				$cd[] = "a.sdocno LIKE '%".$kw."%' ";
				$cd[] = "s.name LIKE '%".$kw."%' ";
				$cd[] = "s.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 's.code' => 'ASC', 'a.docno' => 'ASC', 'i.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		//pd($results,1);
		return $results;
	}

	public function get_po_payment_method_report( $filters = [], $order = [], $args = [] )
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
		if( isset( $filters['seller'] ) )
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
	    else
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	    if( $curr_wh ) $filters['warehouse_id'] = $curr_wh['code'];

	    $field = "a.doc_id, a.docno, a.doc_date, a.created_at, s.code AS supplier_code, s.name AS supplier, md.meta_value AS invoice
			, mc.meta_value AS remark, ROUND( IFNULL( SUM( ib.meta_value ), 0 ), 2 ) AS total_amount, pm.name AS payment_method ";
		
		$table = "{$dbname}{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = 0 AND ma.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = a.doc_id AND ma.item_id = 0 AND mb.meta_key = 'payment_method' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = a.doc_id AND mc.item_id = 0 AND mc.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = a.doc_id AND md.item_id = 0 AND md.meta_key = 'invoice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} s ON s.code = ma.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['supplier_tree']} ";
			$subsql.= "WHERE 1 AND descendant = s.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} ss ON ss.id = ( {$subsql} ) ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['payment_method']} pm ON pm.id = mb.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = b.doc_id AND ia.item_id = b.item_id AND ia.meta_key = 'uprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = b.doc_id AND ib.item_id = b.item_id AND ib.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ic ON ic.doc_id = b.doc_id AND ic.item_id = b.item_id AND ic.meta_key = 'avg_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} id ON id.doc_id = b.doc_id AND id.item_id = b.item_id AND id.meta_key = 'foc' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = b.product_id ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

		//$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		//$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", 'purchase_order', 6 );
		
		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}
		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['supplier'] ) )
		{
			if( is_array( $filters['supplier'] ) )
			{
				$catcd = "s.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$catcd.= "OR ss.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "s.id = %d ", $filters['supplier'] );
				$catcd = $wpdb->prepare( "OR ss.id = %d ", $filters['supplier'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		if( isset( $filters['payment_method'] ) )
		{
			if( is_array( $filters['payment_method'] ) )
				$cond.= "AND pm.id IN ('" .implode( "','", $filters['payment_method'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND pm.id = %s ", $filters['payment_method'] );
		}
		/*if( isset( $filters['product'] ) )
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
		}*/
		if( isset( $filters['doc_stat'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.status = %s ", $filters['doc_stat'] );
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
				$cd[] = "a.sdocno LIKE '%".$kw."%' ";
				$cd[] = "s.name LIKE '%".$kw."%' ";
				$cd[] = "s.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "GROUP BY a.doc_id ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno' => 'DESC', 'a.doc_date' => 'DESC' ];
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

	public function get_sap_e_payment_report( $filters = [], $order = [], $args = [] )
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

		$filters['sequence_doc'] = array_filter( $filters['sequence_doc'] );
		if( isset( $filters['sequence_doc'] ) )
		{
			$query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_po']} (
				`id` int(11) NOT NULL AUTO_INCREMENT, 
				`po_no` varchar(50) NOT NULL DEFAULT '',
				PRIMARY KEY (`id`), 
				KEY `po_no` (`po_no`) 
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1; ";
			$query = $wpdb->query( $query );

			$query = "INSERT INTO {$this->tables['temp_po']} (`po_no`) VALUES ";
			$ql = [];
			foreach( $filters['sequence_doc'] as $i => $sequence_doc )
			{
				$filters['sequence_doc'][$i] = trim( $sequence_doc );
				$ql[] = "( '{$filters['sequence_doc'][$i]}' )";
			}
			$query.= implode( ', ', $ql )."; ";
			$query = $wpdb->query( $query );
		}

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

		$field = "a.doc_id, s.code AS supplier_code, s.name AS supplier_name
			, a.docno AS po_no, DATE_FORMAT( a.doc_date, '%Y.%m.%d' ) AS po_date
			, mb.meta_value AS invoice_no, md.meta_value AS ref_po_no, DATE_FORMAT( mc.meta_value, '%Y.%m.%d' ) AS invoice_date 
			, DATE_FORMAT( gr.doc_date, '%Y.%m.%d' ) AS gr_date 
			, cat.slug AS category_code, cat.name AS category_name 
			, ROUND( IFNULL( SUM( dn.amount ), 0 ), 2 ) AS dn_amt
			, ROUND( IFNULL( SUM( cn.amount ), 0 ), 2 ) AS cn_amt
			, ROUND( IFNULL( SUM( ia.meta_value ), 0 ), 2 ) AS amount
			, ROUND( IFNULL( SUM( ia.meta_value ), 0 ) - IFNULL( SUM( dn.amount ), 0 ) + IFNULL( SUM( cn.amount ), 0 ), 2 ) AS final_amt ";

		if( $filters['exporting'] )
		{
			$field = "CONCAT( s.code, '-', s.name ) AS 'Supplier Name' 
			, a.docno AS 'PO Number', DATE_FORMAT( a.doc_date, '%Y.%m.%d' ) AS 'PO Date' 
			, CONCAT( '_', mb.meta_value ) AS 'Invoice No', DATE_FORMAT( mc.meta_value, '%Y.%m.%d' ) AS 'Inv Date' 
			, DATE_FORMAT( gr.doc_date, '%Y.%m.%d' ) AS 'GR Date' 
			, CONCAT( cat.slug, ' - ', cat.name ) AS 'Item Category' 
			, ROUND( IFNULL( SUM( ia.meta_value ), 0 ) - IFNULL( SUM( dn.amount ), 0 ) + IFNULL( SUM( cn.amount ), 0 ), 2 ) AS 'Amt' ";
		}
		
		$table = "{$dbname}{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = 0 AND ma.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = a.doc_id AND mb.item_id = 0 AND mb.meta_key = 'invoice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = a.doc_id AND mc.item_id = 0 AND mc.meta_key = 'invoice_date' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = a.doc_id AND md.item_id = 0 AND md.meta_key = 'integrated_po' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} s ON s.code = ma.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['supplier_tree']} ";
			$subsql.= "WHERE 1 AND descendant = s.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} ss ON ss.id = ( {$subsql} ) ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = b.doc_id AND ia.item_id = b.item_id AND ia.meta_key = 'total_amount'  ";

			$subsql = "SELECT h.doc_id FROM {$dbname}{$this->tables['document']} h WHERE 1 
				AND h.parent = a.doc_id AND h.doc_type = 'good_receive' AND h.status >= 6 
				ORDER BY h.doc_date ASC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} gr ON gr.doc_id = ( {$subsql} ) ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = b.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( {$subsql} ) ";

			$debit_sql = "SELECT h.doc_id, ROUND( d1.meta_value, 2 ) AS amount, d.product_id, d.ref_doc_id, d.ref_item_id
				FROM {$dbname}{$this->tables['document']} h
				LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0
				LEFT JOIN {$dbname}{$this->tables['document_meta']} d1 ON d1.doc_id = d.doc_id AND d1.item_id = d.item_id AND d1.meta_key = 'amount'
				WHERE 1 AND h.doc_type = 'purchase_debit_note' AND h.status >= 6 ";
		$table.= "LEFT JOIN ( {$debit_sql} ) dn ON dn.ref_doc_id = a.doc_id AND dn.ref_item_id = b.item_id ";

			$credit_sql = "SELECT h.doc_id, ROUND( d1.meta_value, 2 ) AS amount, d.product_id, d.ref_doc_id, d.ref_item_id
				FROM {$dbname}{$this->tables['document']} h
				LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0
				LEFT JOIN {$dbname}{$this->tables['document_meta']} d1 ON d1.doc_id = d.doc_id AND d1.item_id = d.item_id AND d1.meta_key = 'amount'
				WHERE 1 AND h.doc_type = 'purchase_credit_note' AND h.status >= 6 ";
		$table.= "LEFT JOIN ( {$credit_sql} ) cn ON cn.ref_doc_id = a.doc_id AND dn.ref_item_id = b.item_id ";
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", 'purchase_order', 6 );
		
		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}

		if( isset( $filters['sequence_doc'] ) )
		{
			$cond.= "AND ( a.docno IN ('" .implode( "','", $filters['sequence_doc'] ). "') ";
			$cond.= "OR md.meta_value IN ('" .implode( "','", $filters['sequence_doc'] ). "') ) ";

			$table.= "LEFT JOIN {$this->tables['temp_po']} sp ON sp.po_no = a.docno ";

			$order = [ 'sp.id'=>'asc', 'cat.slug' => 'ASC' ];
		}
		else
		{
			if( isset( $filters['doc_id'] ) )
			{
				if( is_array( $filters['doc_id'] ) )
					$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
			}
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
			}
		}
		
		if( isset( $filters['supplier'] ) )
		{
			if( is_array( $filters['supplier'] ) )
			{
				$catcd = "s.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$catcd.= "OR ss.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "s.id = %d ", $filters['supplier'] );
				$catcd = $wpdb->prepare( "OR ss.id = %d ", $filters['supplier'] );
				$cond.= "AND ( {$catcd} ) ";
			}
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
				$cd[] = "a.sdocno LIKE '%".$kw."%' ";
				$cd[] = "s.name LIKE '%".$kw."%' ";
				$cd[] = "s.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "GROUP BY s.code, a.docno, cat.slug ";

		//order
		if( empty( $order ) )
		{
			$order = [ 's.code' => 'ASC', 'a.docno' => 'ASC', 'cat.slug' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		if( isset( $filters['sequence_doc'] ) )
		{
			$delete = "DELETE FROM {$this->tables['temp_po']} WHERE 1; ";
			$result = $wpdb->query( $delete );
		}
		
		return $results;
	}
	
} //class

}