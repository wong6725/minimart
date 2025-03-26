<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Sales_Rpt" ) ) 
{
	
class WCWH_Sales_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "Sales";

	public $Logic;

	public $tplName = array(
		'export' => 'exportSales',
		'export_sap' => 'exportSalesSAP',
		'print' => 'printSales',
	);
	
	protected $tables = array();
	protected $dbname = '';

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $doc_opts = [];
	public $need_margining;

	public $def_date_type = 'post_date';
	public $Setting = [];

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_db_tables();

		$this->setting_handler();

		$this->load_setting();

		$this->need_margining = ( $this->setting['general']['use_margining'] )? true : false;
		//$this->need_margining = false;
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
			"client_tree"	=> $prefix."client_tree",

			"items"			=> $prefix."items",
			"itemsmeta"		=> $prefix."itemsmeta",
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			"item_group"	=> $prefix."item_group",
			"uom"			=> $prefix."uom",
			"reprocess_item"=> $prefix."reprocess_item",
			
			"status"		=> $prefix."status",

			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",
			'acc_type'		=> $prefix."customer_acc_type",

			"margining"			=> $prefix."margining",
			"margining_sect"	=> $prefix."margining_sect",
			"margining_det"		=> $prefix."margining_det",
			"margining_sales"	=> $prefix."margining_sales",

			"temp_po"		=> "temp_po",
		);
	}

	public function setting_handler()
    {
        $succ = true;
        $action_id = 'wh_sales_rpt';

        $datas = $_REQUEST;
        if( empty( $datas ) ) return $succ;

    	if( empty( $datas['wcwh_'.$action_id.'_option'] ) || empty( $datas['token'] ) ) return $succ;

	    if( ! apply_filters( 'wcwh_verify_token', $datas['token'], $action_id ) )
	    { 
			$succ = false;
		}

	    if( $succ )
	    {
	    	if( current_user_cans( ['wh_admin_support'] ) )
	        	update_option( 'wcwh_'.$action_id.$this->seller.'_option', $datas['wcwh_'.$action_id.'_option'] );
	        else
	        {
	        	$options = get_option( 'wcwh_'.$action_id.$this->seller.'_option' );
	        	if( $options )
	        	{
	        		$dat = $datas['wcwh_'.$action_id.'_option'];
	        		foreach( $options as $key => $option )
	        		{
	        			if( empty( $dat[ $key ] ) )
	        			{
	        				$dat[ $key ] = $option;
	        			}
	        			else
	        			{
	        				if( is_array( $option ) )
	        				foreach( $option as $k => $opt )
	        				{
	        					if( empty( $dat[ $key ][ $k ] ) )
	        					{
	        						$dat[ $key ][ $k ] = $opt;
	        					}
	        				}
	        			}
	        		}
	        		
	        		$datas['wcwh_'.$action_id.'_option'] = $dat;
	        	}
	        	
	        	update_option( 'wcwh_'.$action_id.$this->seller.'_option', $datas['wcwh_'.$action_id.'_option'] );
	        }
	    }

	    return $succ;
    }

    public function load_setting()
    {
    	$succ = true;
        $action_id = 'wh_sales_rpt';

        $this->Setting = get_option( 'wcwh_'.$action_id.$this->seller.'_option' );
    }

    public function report_setting( $filters = array(), $order = array() )
	{
		$action_id = 'wh_sales_rpt';
		$args = array(
			'hook'		=> $action_id.'_form',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'datas'		=> $this->Setting,
			'option_name' => 'wcwh_'.$action_id.'_option',
			'seller'	=> $this->seller,
			'action_id'	=> $action_id,
		);

		do_action( 'wcwh_get_template', $action_id.'-setting.php', $args );
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
					$params = [];

					switch( $datas['export_type'] )
					{
						case 'delivery_order':
							$datas['filename'] = 'SO DO Summary ';
						break;
						case 'summary':
							$datas['filename'] = 'SO Listing ';
						break;
						case 'po_sales':
							$datas['filename'] = 'SO by PO ';
						break;
						case 'canteen_einvoice':
							$params['is_print'] = 1;
							$datas['filename'] = 'Minimart Listing ';
						break;
						case 'canteen_einvoice_sap':
							$params['export_sap'] = 1;
							$datas['filename'] = 'Minimart e-Invoice ';
						break;
						case 'non_canteen_einvoice':
							$params['is_print'] = 1;
							$datas['filename'] = 'Direct Sales Listing ';
						break;
						case 'non_canteen_einvoice_sap':
							$params['export_sap'] = 1;
							$datas['filename'] = 'Direct Sales e-Invoice ';
						break;
						case 'unimart_einvoice':
							$params['is_print'] = 1;
							$datas['filename'] = 'Unimart Direct Sales ';
						break;
						case 'unimart_einvoice_sap':
							$params['export_sap'] = 1;
							$datas['filename'] = 'Unimart Direct Sales e-Invoice ';
						break;
					}
					
					$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					if( $datas['from_date'] ) $datas['filename'].= date( $date_format, strtotime( $datas['from_date'] ) );
					if( $datas['to_date'] )  $datas['filename'].= " - ".date( $date_format, strtotime( $datas['to_date'] ) );
					
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['date_type'] ) ) $params['date_type'] = $datas['date_type'];
					if( !empty( $datas['doc_stat'] ) ) $params['doc_stat'] = $datas['doc_stat'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['doc_type'] ) ) $params['doc_type'] = $datas['doc_type'];
					if( !empty( $datas['good_issue_type'] ) ) $params['good_issue_type'] = $datas['good_issue_type'];
					if( !empty( $datas['doc_id'] ) ) $params['doc_id'] = $datas['doc_id'];
					if( !empty( $datas['sequence_doc'] ) ) $params['sequence_doc'] = $datas['sequence_doc'];
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
					if( !empty( $datas['doc_stat'] ) ) $params['doc_stat'] = $datas['doc_stat'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['doc_type'] ) ) $params['doc_type'] = $datas['doc_type'];
					if( !empty( $datas['good_issue_type'] ) ) $params['good_issue_type'] = $datas['good_issue_type'];
					if( !empty( $datas['doc_id'] ) ) $params['doc_id'] = $datas['doc_id'];
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
			case 'delivery_order':
				return $this->get_so_delivery_order_summary_report( $params );
			break;
			case 'summary':
				return $this->get_so_summary_report( $params );
			break;
			case 'po_sales':
				return $this->get_so_po_summary_report( $params );
			break;
			case 'canteen_einvoice':
				return $this->get_so_sap_canteen_einvoice( $params );
			break;
			case 'canteen_einvoice_sap':
				return $this->get_so_sap_canteen_einvoice( $params );
			break;
			case 'non_canteen_einvoice':
				return $this->get_so_sap_non_canteen_einvoice( $params );
			break;
			case 'non_canteen_einvoice_sap':
				return $this->get_so_sap_non_canteen_einvoice( $params );
			break;
			case 'unimart_einvoice':
				return $this->get_so_sap_unimart_einvoice( $params );
			break;
			case 'unimart_einvoice_sap':
				return $this->get_so_sap_unimart_einvoice( $params );
			break;
		}
	}

	public function print_handler( $params = array(), $opts = array() )
	{
		$type = $params['export_type'];
		$date_format = get_option( 'date_format' );
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		
		$params['is_print'] = 1;
		if( $opts['product_desc'] == 'category' && $opts['grouping'] ) 
			$params['grouping'] = 1;
		else
			$params['grouping'] = 0;
		
		$datas = $this->export_data_handler( $params );
		unset( $params['export_type'] );
		
		switch( $type )
		{
			case 'canteen_einvoice':
				$filename = "Minimart e-Invoice";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Minimart e-Invoice';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Minimart e-Invoice';
				
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
					'Client No' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Client' => [ 'width'=>'19%', 'class'=>['leftered'] ],
					'Invoice No' => [ 'width'=>'13%', 'class'=>['leftered'] ],
					'Delivery Order No' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'DO Date' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'28%', 'class'=>['leftered'] ],
					'Amount' => [ 'width'=>'8%', 'class'=>['rightered'] ],
				];

				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$do = []; $inv = []; $client = [];
					foreach( $datas as $i => $data )
					{	
						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];

						$data['category'] = implode( ' - ', $category );
						$regrouped[ $data['client_code'] ][ $data['invoice_no'] ][ $data['do_no'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['client_code'] ]+= 1;
						$rowspan[ $data['invoice_no'] ]+= 1;
						$rowspan[ $data['do_no'] ]+= 1;

						//totals
						$client[ $data['client_code'] ]+= $data['amount'];
						$inv[ $data['client_code'] ][ $data['invoice_no'] ]+= $data['amount'];
						$do[ $data['client_code'] ][ $data['invoice_no'] ][ $data['do_no'] ]+= $data['amount'];
					}
					
					$details = [];
					if( $regrouped )
					{
						$t_amt = 0;
						foreach( $regrouped as $lvl1 => $dat1 )
						{
							$lvl1_added = '';
							foreach( $dat1 as $lvl2 => $dat2 )
							{
								$lvl2_added = '';
								foreach( $dat2 as $lvl3 => $dat3 )
								{
									$lvl3_added = '';
									foreach( $dat3 as $i => $vals )
									{
										$row = [

'client_code' => [ 'value'=>$vals['client_code'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl1 ] ],
'client_name' => [ 'value'=>$vals['client_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl1 ] ],
'invoice_no' => [ 'value'=>$vals['invoice_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl2 ] ],
'do_no' => [ 'value'=>$vals['do_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'do_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['do_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'category' => [ 'value'=>$vals['category'], 'class'=>['leftered'] ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

										];

										if( $lvl1_added == $lvl1 ) 
										{
											$row['client_code'] = [];
											$row['client_name'] = [];
										}
										$lvl1_added = $lvl1;

										if( $lvl2_added == $lvl2 ) 
										{
											$row['invoice_no'] = [];
										}
										$lvl2_added = $lvl2;

										if( $lvl3_added == $lvl3 ) 
										{
											$row['do_no'] = [];
											$row['do_date'] = [];
										}
										$lvl3_added = $lvl3;

										$details[] = $row;
									}

									$det = [
										'client_code' => [],
										'client_name' => [],
										'invoice_no' => [],
										'do_no' => [],
										'do_date' => [],
										'category' => [ 'value'=>'DO Total:', 'class'=>['leftered','bold'] ],
										'amount' => [ 'value'=>$do[ $lvl1 ][ $lvl2 ][ $lvl3 ], 'class'=>['rightered','bold'], 'num'=>1 ],
									];
									$details[] = $det;
								}

								$det = [
									'client_code' => [],
									'client_name' => [],
									'invoice_no' => [],
									'do_no' => [ 'value'=>'Inv Total:', 'class'=>['leftered','bold'], 'colspan'=>3 ],
									'amount' => [ 'value'=>$inv[ $lvl1 ][ $lvl2 ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
								$details[] = $det;
							}

							$det = [
								'client_code' => [],
								'client_name' => [],
								'invoice_no' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>4 ],
								'amount' => [ 'value'=>$client[ $lvl1 ], 'class'=>['rightered','bold'], 'num'=>1 ],
							];
							$details[] = $det;

							$t_amt+= $client[ $lvl1 ];
						}

						$det = [
							'client' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>6 ],
							'amount' => [ 'value'=>$t_amt, 'class'=>['rightered','bold'], 'num'=>1 ],
						];
						$details[] = $det;
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
			case 'non_canteen_einvoice':
				$filename = "Direct Sales e-Invoice";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Direct Sales e-Invoice';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Direct Sales e-Invoice';
				
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
					'Client No' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Client' => [ 'width'=>'14%', 'class'=>['leftered'] ],
					'Sale Order' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'Invoice No' => [ 'width'=>'11%', 'class'=>['leftered'] ],
					'Invoice Date' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Delivery Order' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'30%', 'class'=>['leftered'] ],
					'Amount' => [ 'width'=>'8%', 'class'=>['rightered'] ],
				];

				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$do = []; $inv = []; $client = [];
					foreach( $datas as $i => $data )
					{	
						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];

						$data['category'] = implode( ' - ', $category );
						$regrouped[ $data['client_code'] ][ $data['invoice_no'] ][ $data['do_no'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['client_code'] ]+= 1;
						$rowspan[ $data['invoice_no'] ]+= 1;
						$rowspan[ $data['do_no'] ]+= 1;

						//totals
						$client[ $data['client_code'] ]+= $data['amount'];
						$inv[ $data['client_code'] ][ $data['invoice_no'] ]+= $data['amount'];
						$do[ $data['client_code'] ][ $data['invoice_no'] ][ $data['do_no'] ]+= $data['amount'];
					}
					
					$details = [];
					if( $regrouped )
					{
						$t_amt = 0;
						foreach( $regrouped as $lvl1 => $dat1 )
						{
							$lvl1_added = '';
							foreach( $dat1 as $lvl2 => $dat2 )
							{
								$lvl2_added = '';
								foreach( $dat2 as $lvl3 => $dat3 )
								{
									$lvl3_added = '';
									foreach( $dat3 as $i => $vals )
									{
										$row = [

'client_code' => [ 'value'=>$vals['client_code'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl1 ] ],
'client_name' => [ 'value'=>$vals['client_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl1 ] ],
'so_no' => [ 'value'=>$vals['so_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl2 ] ],
'invoice_no' => [ 'value'=>$vals['invoice_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl2 ] ],
'invoice_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['invoice_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'do_no' => [ 'value'=>$vals['do_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'category' => [ 'value'=>$vals['category'], 'class'=>['leftered'] ],
'amount' => [ 'value'=>$vals['amount'], 'class'=>['rightered'], 'num'=>1 ],

										];

										if( $lvl1_added == $lvl1 ) 
										{
											$row['client_code'] = [];
											$row['client_name'] = [];
										}
										$lvl1_added = $lvl1;

										if( $lvl2_added == $lvl2 ) 
										{
											$row['so_no'] = [];
											$row['invoice_no'] = [];
											$row['invoice_date'] = [];
										}
										$lvl2_added = $lvl2;

										if( $lvl3_added == $lvl3 ) 
										{
											$row['do_no'] = [];
										}
										$lvl3_added = $lvl3;

										$details[] = $row;
									}

									$det = [
										'client_code' => [],
										'client_name' => [],
										'so_no' => [],
										'invoice_no' => [],
										'invoice_date' => [],
										'do_no' => [],
										'category' => [ 'value'=>'DO Total:', 'class'=>['leftered','bold'] ],
										'amount' => [ 'value'=>$do[ $lvl1 ][ $lvl2 ][ $lvl3 ], 'class'=>['rightered','bold'], 'num'=>1 ],
									];
									$details[] = $det;
								}

								$det = [
									'client_code' => [],
									'client_name' => [],
									'so_no' => [],
									'invoice_no' => [],
									'invoice_date' => [],
									'do_no' => [ 'value'=>'Inv Total:', 'class'=>['leftered','bold'], 'colspan'=>2 ],
									'amount' => [ 'value'=>$inv[ $lvl1 ][ $lvl2 ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
								$details[] = $det;
							}

							$det = [
								'client_code' => [],
								'client_name' => [],
								'so_no' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>5 ],
								'amount' => [ 'value'=>$client[ $lvl1 ], 'class'=>['rightered','bold'], 'num'=>1 ],
							];
							$details[] = $det;

							$t_amt+= $client[ $lvl1 ];
						}

						$det = [
							'client' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>7 ],
							'amount' => [ 'value'=>$t_amt, 'class'=>['rightered','bold'], 'num'=>1 ],
						];
						$details[] = $det;
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
			case 'delivery_order':
				$filename = "SO With DO";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				if( defined( 'WCWH_PROJECT' ) && strtolower( WCWH_PROJECT ) == 'imuimu' ) $document['config']['off_signature'] = 1;
				$document['header'] = 'SO With DO Listing';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'SO With DO Listing';
				
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
					'Client' => [ 'width'=>'13%', 'class'=>['leftered'] ],
					'SO No' => [ 'width'=>'7%', 'class'=>['leftered'] ],
					'SO Date' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'DO No' => [ 'width'=>'7%', 'class'=>['leftered'] ],
					'DO Date' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'Product Description' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'4%', 'class'=>[] ],
					'UOM' => [ 'width'=>'4%', 'class'=>[] ],
					'Selling Price' => [ 'width'=>'7%', 'class'=>[] ],
					'Amount' => [ 'width'=>'7%', 'class'=>[] ],
					'Unit Cost' => [ 'width'=>'7%', 'class'=>[] ],
					'Total Cost' => [ 'width'=>'7%', 'class'=>[] ],
				];
				if( $params['grouping'] )
				{
					$document['detail_title'] = [
						'Client' => [ 'width'=>'16%', 'class'=>['leftered'] ],
						'SO No' => [ 'width'=>'8%', 'class'=>['leftered'] ],
						'SO Date' => [ 'width'=>'8%', 'class'=>['leftered'] ],
						'DO No' => [ 'width'=>'8%', 'class'=>['leftered'] ],
						'DO Date' => [ 'width'=>'8%', 'class'=>['leftered'] ],
						'Product Description' => [ 'width'=>'28%', 'class'=>['leftered'] ],
						'Qty' => [ 'width'=>'6%', 'class'=>[] ],
						'Amount' => [ 'width'=>'9%', 'class'=>[] ],
						'Total Cost' => [ 'width'=>'9%', 'class'=>[] ],
					];
				}
				if( $opts['listing_total'] == 'cost' )
				{
					unset( $document['detail_title']['Selling Price'] );
					unset( $document['detail_title']['Amount'] );
				}
				else if( $opts['listing_total'] == 'sale' )
				{
					unset( $document['detail_title']['Unit Cost'] );
					unset( $document['detail_title']['Total Cost'] );
				}

				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$total_sale = []; $total_cost = [];
					foreach( $datas as $i => $data )
					{
						$client = [];
						if( $data['client_code'] ) $client[] = $data['client_code'];
						if( $data['client_name'] ) $client[] = $data['client_name'];

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
								if( $data['category_name'] ) $product[] = $data['category_name'];
							break;
						}

						$data['client_name'] = implode( ' - ', $client );
						$data['product_name'] = implode( ' - ', $product );
						$regrouped[ $data['client_code'] ][ $data['so_docno'] ][ $data['do_docno'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['client_code'] ]+= 1;
						$rowspan[ $data['so_docno'] ]+= 1;
						$rowspan[ $data['do_docno'] ]+= 1;

						//totals
						$total_sale[ $data['client_code'] ][ $data['so_docno'] ]+= $data['total_sale'];
						$total_cost[ $data['client_code'] ][ $data['so_docno'] ]+= $data['total_cost'];
					}
					//pd($total_sale);pd($total_sale);exit;
					$details = [];
					if( $regrouped )
					{
						$t_sale = 0; $t_cost = 0;
						foreach( $regrouped as $lvl1 => $dat1 )
						{
							$subtotal_sale = 0;
							$subtotal_cost = 0;
							$lvl1_added = '';
							foreach( $dat1 as $lvl2 => $dat2 )
							{
								$lvl2_added = '';
								foreach( $dat2 as $lvl3 => $dat3 )
								{
									$lvl3_added = '';
									foreach( $dat3 as $i => $vals )
									{
										$row = [

'client' => [ 'value'=>$vals['client_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl1 ] ],
'so_docno' => [ 'value'=>$vals['so_docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl2 ] ],
'so_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['so_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'do_docno' => [ 'value'=>$vals['do_docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'do_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['do_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'product' => [ 'value'=>$vals['product_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'unit_price' => [ 'value'=>$vals['unit_price'], 'class'=>['rightered'], 'num'=>1 ],
'total_sale' => [ 'value'=>$vals['total_sale'], 'class'=>['rightered'], 'num'=>1 ],
'unit_cost' => [ 'value'=>$vals['unit_cost'], 'class'=>['rightered'], 'num'=>1 ],
'total_cost' => [ 'value'=>$vals['total_cost'], 'class'=>['rightered'], 'num'=>1 ],

										];

										if( $params['grouping'] )
										{
											$row = [

'client' => [ 'value'=>$vals['client_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl1 ] ],
'so_docno' => [ 'value'=>$vals['so_docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl2 ] ],
'so_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['so_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'do_docno' => [ 'value'=>$vals['do_docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'do_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['do_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $lvl3 ] ],
'product' => [ 'value'=>$vals['product_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'total_sale' => [ 'value'=>$vals['total_sale'], 'class'=>['rightered'], 'num'=>1 ],
'total_cost' => [ 'value'=>$vals['total_cost'], 'class'=>['rightered'], 'num'=>1 ],

											];
										}

										if( $opts['listing_total'] == 'cost' )
										{
											unset( $row['unit_price'] );
											unset( $row['total_sale'] );
										}
										else if( $opts['listing_total'] == 'sale' )
										{
											unset( $row['unit_cost'] );
											unset( $row['total_cost'] );
										}

										if( $lvl1_added == $lvl1 ) $row['client'] = [];
										$lvl1_added = $lvl1;

										if( $lvl2_added == $lvl2 ) 
										{
											$row['so_docno'] = [];
											$row['so_date'] = [];
										}
										$lvl2_added = $lvl2;

										if( $lvl3_added == $lvl3 ) 
										{
											$row['do_docno'] = [];
											$row['do_date'] = [];
										}
										$lvl3_added = $lvl3;

										$details[] = $row;
									}
								}

								$det = [
									'client' => [],
									'so_docno' => [],
									'so_date' => [],
									'do_docno' => [ 'value'=>'SO Total:', 'class'=>['leftered','bold'], 'colspan'=>6 ],
									'total_sale' => [ 'value'=>$total_sale[ $lvl1 ][ $lvl2 ], 'class'=>['rightered','bold'], 'num'=>1 ],
									'unit_cost' => [],
									'total_cost' => [ 'value'=>$total_cost[ $lvl1 ][ $lvl2 ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
								if( $params['grouping'] )
								{
									$det = [
										'client' => [],
										'so_docno' => [],
										'so_date' => [],
										'do_docno' => [ 'value'=>'SO Total:', 'class'=>['leftered','bold'], 'colspan'=>4 ],
										'total_sale' => [ 'value'=>$total_sale[ $lvl1 ][ $lvl2 ], 'class'=>['rightered','bold'], 'num'=>1 ],
										'total_cost' => [ 'value'=>$total_cost[ $lvl1 ][ $lvl2 ], 'class'=>['rightered','bold'], 'num'=>1 ],
									];
								}
								if( $opts['listing_total'] == 'cost' )
								{
									$det['do_docno']['colspan'] = ( $params['grouping'] )? 4 : 6;
									unset( $det['unit_cost'] );
									unset( $det['unit_price'] );
									unset( $det['total_sale'] );
								}
								else if( $opts['listing_total'] == 'sale' )
								{
									unset( $det['unit_cost'] );
									unset( $det['total_cost'] );
								}
								$details[] = $det;

								$subtotal_sale+= $total_sale[ $lvl1 ][ $lvl2 ];
								$subtotal_cost+= $total_cost[ $lvl1 ][ $lvl2 ];
							}

							$det = [
								'client' => [],
								'so_docno' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>8 ],
								'total_sale' => [ 'value'=>$subtotal_sale, 'class'=>['rightered','bold'], 'num'=>1 ],
								'unit_cost' => [],
								'total_cost' => [ 'value'=>$subtotal_cost, 'class'=>['rightered','bold'], 'num'=>1 ],
							];
							if( $params['grouping'] )
							{
								$det = [
									'client' => [],
									'so_docno' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>6 ],
									'total_sale' => [ 'value'=>$subtotal_sale, 'class'=>['rightered','bold'], 'num'=>1 ],
									'total_cost' => [ 'value'=>$subtotal_cost, 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}
							if( $opts['listing_total'] == 'cost' )
							{
								$det['so_docno']['colspan'] = ( $params['grouping'] )? 6 : 8;
								unset( $det['unit_cost'] );
								unset( $det['unit_price'] );
								unset( $det['total_sale'] );
							}
							else if( $opts['listing_total'] == 'sale' )
							{
								unset( $det['unit_cost'] );
								unset( $det['total_cost'] );
							}
							$details[] = $det;

							$t_sale+= $subtotal_sale;
							$t_cost+= $subtotal_cost;
						}

						$det = [
							'client' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>9 ],
							'total_sale' => [ 'value'=>$t_sale, 'class'=>['rightered','bold'], 'num'=>1 ],
							'unit_cost' => [],
							'total_cost' => [ 'value'=>$t_cost, 'class'=>['rightered','bold'], 'num'=>1 ],
						];
						if( $params['grouping'] )
						{
							$det = [
								'client' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>7 ],
								'total_sale' => [ 'value'=>$t_sale, 'class'=>['rightered','bold'], 'num'=>1 ],
								'total_cost' => [ 'value'=>$t_cost, 'class'=>['rightered','bold'], 'num'=>1 ],
							];
						}
						if( $opts['listing_total'] == 'cost' )
						{
							$det['client']['colspan'] = ( $params['grouping'] )? 7 : 9;
							unset( $det['unit_cost'] );
							unset( $det['unit_price'] );
							unset( $det['total_sale'] );
						}
						else if( $opts['listing_total'] == 'sale' )
						{
							unset( $det['unit_cost'] );
							unset( $det['total_cost'] );
						}
						$details[] = $det;
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
			case 'po_sales':
				$filename = "Sale-Order";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				if( defined( 'WCWH_PROJECT' ) && strtolower( WCWH_PROJECT ) == 'imuimu' ) $document['config']['off_signature'] = 1;
				$document['header'] = 'SO Listing By PO';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'SO Listing By PO';
				
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
					'SO No.' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'SO Date' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'PO No.' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'GR INV' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'DO No.' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'Item Description' => [ 'width'=>'23%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'6%', 'class'=>[] ],
					'UOM' => [ 'width'=>'4%', 'class'=>[] ],
					'FOC' => [ 'width'=>'4%', 'class'=>[] ],
					'Unit Price' => [ 'width'=>'6%', 'class'=>[] ],
					'Amount' => [ 'width'=>'6%', 'class'=>[] ],
				];
				if( $params['grouping'] )
				{
					$document['detail_title'] = [
						'SO No.' => [ 'width'=>'6%', 'class'=>['leftered'] ],
						'SO Date' => [ 'width'=>'6%', 'class'=>['leftered'] ],
						'PO No.' => [ 'width'=>'6%', 'class'=>['leftered'] ],
						'GR INV' => [ 'width'=>'6%', 'class'=>['leftered'] ],
						'DO No.' => [ 'width'=>'6%', 'class'=>['leftered'] ],
						'Item Description' => [ 'width'=>'23%', 'class'=>['leftered'] ],
						'Qty' => [ 'width'=>'6%', 'class'=>[] ],
						'Amount' => [ 'width'=>'6%', 'class'=>[] ],
					];
				}
				
				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$client = [];
						if( $data['client_code'] ) $client[] = $data['client_code'];
						if( $data['client_name'] ) $client[] = $data['client_name'];

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
								if( $data['category_name'] ) $product[] = $data['category_name'];
							break;
						}

						$data['client_name'] = implode( ' - ', $client );
						$data['product_name'] = implode( ' - ', $product );
						$regrouped[ $data['so_no'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['so_no'] ]+= 1;

						//totals
						$totals[ $data['so_no'] ]+= $data['line_total'];
					}
					
					$details = [];
					if( $regrouped )
					{
						$total = 0;
						foreach( $regrouped as $doc => $items )
						{
							$doc_added = '';

							foreach( $items as $i => $vals )
							{
								$row = [

'so_no' => [ 'value'=>$vals['so_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'doc_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['doc_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'po_no' => [ 'value'=>$vals['po_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'gr_invoice' => [ 'value'=>$vals['gr_invoice'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'do_no' => [ 'value'=>$vals['do_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'product' => [ 'value'=>$vals['product_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'foc' => [ 'value'=>$vals['foc'], 'class'=>['rightered'], 'num'=>1 ],
'unit_price' => [ 'value'=>$vals['unit_price'], 'class'=>['rightered'], 'num'=>1 ],
'line_total' => [ 'value'=>$vals['line_total'], 'class'=>['rightered'], 'num'=>1 ],

								];

								if( $params['grouping'] )
								{
									$row = [

'so_no' => [ 'value'=>$vals['so_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'doc_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['doc_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'po_no' => [ 'value'=>$vals['po_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'gr_invoice' => [ 'value'=>$vals['gr_invoice'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'do_no' => [ 'value'=>$vals['do_no'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'product' => [ 'value'=>$vals['product_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'line_total' => [ 'value'=>$vals['line_total'], 'class'=>['rightered'], 'num'=>1 ],

									];
								}

								if( $doc_added == $doc ) 
								{
									$row['so_no'] = [];
									$row['doc_date'] = [];
									$row['po_no'] = [];
									$row['gr_invoice'] = [];
									$row['do_no'] = [];
								}
								$doc_added = $doc;

								$details[] = $row;
							}
							
							if( $params['grouping'] )
							{
								$details[] = [
									'so_no' => [],
									'doc_date' => [],
									'po_no' => [],
									'gr_invoice' => [],
									'do_no' => [],
									'product' => [ 'value'=>'SO Total:', 'class'=>['leftered','bold'], 'colspan'=>2 ],
									'line_total' => [ 'value'=>$totals[ $doc ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}
							else
							{
								$details[] = [
									'so_no' => [],
									'doc_date' => [],
									'po_no' => [],
									'gr_invoice' => [],
									'do_no' => [],
									'product' => [ 'value'=>'SO Total:', 'class'=>['leftered','bold'], 'colspan'=>5 ],
									'line_total' => [ 'value'=>$totals[ $doc ], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}
								
							$total+= $totals[ $doc ];
						}
						
						if( $params['grouping'] )
						{
							$details[] = [
								'so_no' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>7 ],
								'line_total' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
							];
						}
						else
						{
							$details[] = [
								'so_no' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>10 ],
								'line_total' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
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
			case 'summary':
			default:
				$filename = "Sale-Order";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				if( defined( 'WCWH_PROJECT' ) && strtolower( WCWH_PROJECT ) == 'imuimu' ) $document['config']['off_signature'] = 1;
				$document['header'] = 'SO Listing';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'SO Listing';
				
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
					'Client' => [ 'width'=>'15%', 'class'=>['leftered'] ],
					'SO No.' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'SO Date' => [ 'width'=>'6%', 'class'=>['leftered'] ],
					'Item Description' => [ 'width'=>'23%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'6%', 'class'=>[] ],
					'UOM' => [ 'width'=>'4%', 'class'=>[] ],
					'FOC' => [ 'width'=>'4%', 'class'=>[] ],
					'Unit Price' => [ 'width'=>'6%', 'class'=>[] ],
					'Subtotal' => [ 'width'=>'6%', 'class'=>[] ],
					'Line Discount' => [ 'width'=>'6%', 'class'=>[] ],
					'Order Discount' => [ 'width'=>'6%', 'class'=>[] ],
					'Sell Price' => [ 'width'=>'6%', 'class'=>[] ],
					'Amount' => [ 'width'=>'6%', 'class'=>[] ],
				];
				if( $params['grouping'] )
				{
					$document['detail_title'] = [
						'Client' => [ 'width'=>'12%', 'class'=>['leftered'] ],
						'SO No.' => [ 'width'=>'9%', 'class'=>['leftered'] ],
						'SO Date' => [ 'width'=>'9%', 'class'=>['leftered'] ],
						'Item Description' => [ 'width'=>'30%', 'class'=>['leftered'] ],
						'Qty' => [ 'width'=>'6%', 'class'=>[] ],
						'FOC' => [ 'width'=>'5%', 'class'=>[] ],
						'Subtotal' => [ 'width'=>'6%', 'class'=>[] ],
						'Line Discount' => [ 'width'=>'6%', 'class'=>[] ],
						'Order Discount' => [ 'width'=>'6%', 'class'=>[] ],
						'Amount' => [ 'width'=>'6%', 'class'=>[] ],
					];
				}
				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$client = [];
						if( $data['client_code'] ) $client[] = $data['client_code'];
						if( $data['client_name'] ) $client[] = $data['client_name'];

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
								if( $data['category_name'] ) $product[] = $data['category_name'];
							break;
						}

						$data['client_name'] = implode( ' - ', $client );
						$data['product_name'] = implode( ' - ', $product );
						$regrouped[ $data['client_code'] ][ $data['docno'] ][$i] = $data;

						//rowspan handling
						$rowspan[ $data['client_code'] ]+= 1;
						$rowspan[ $data['docno'] ]+= 1;

						//totals
						$totals[ $data['client_code'] ][ $data['docno'] ]+= $data['line_final_total'];
					}
					
					$details = [];
					if( $regrouped )
					{
						$total = 0;
						foreach( $regrouped as $client => $docs )
						{
							$subtotal = 0;
							$client_added = '';
							foreach( $docs as $doc => $items )
							{
								$doc_added = '';
								if( $totals[ $client ][ $doc ] )
								{
									$rowspan[ $doc ]+= 1;
									$rowspan[ $client ]+= count( $totals[ $client ] ) + 1;
								}

								foreach( $items as $i => $vals )
								{
									$row = [

'client' => [ 'value'=>$vals['client_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $client ] ],
'docno' => [ 'value'=>$vals['docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'doc_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['doc_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'product' => [ 'value'=>$vals['product_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['centered'] ],
'foc' => [ 'value'=>$vals['foc'], 'class'=>['rightered'], 'num'=>1 ],
'def_price' => [ 'value'=>$vals['def_price'], 'class'=>['rightered'], 'num'=>1 ],
'line_subtotal' => [ 'value'=>$vals['line_subtotal'], 'class'=>['rightered'], 'num'=>1 ],
'line_discount' => [ 'value'=>$vals['line_discount'], 'class'=>['rightered'] ],
'order_discount' => [ 'value'=>$vals['order_discount'], 'class'=>['rightered'], 'rowspan'=>$rowspan[ $doc ] ],
'sprice' => [ 'value'=>$vals['final_sprice'], 'class'=>['rightered'], 'num'=>1 ],
'line_total' => [ 'value'=>$vals['line_final_total'], 'class'=>['rightered'], 'num'=>1 ],

									];

									if( $params['grouping'] )
									{
										$row = [

'client' => [ 'value'=>$vals['client_name'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $client ] ],
'docno' => [ 'value'=>$vals['docno'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'doc_date' => [ 'value'=>date_i18n( $date_format, strtotime( $vals['doc_date'] ) ), 'class'=>['leftered'], 'rowspan'=>$rowspan[ $doc ] ],
'product' => [ 'value'=>$vals['product_name'], 'class'=>['leftered'] ],
'qty' => [ 'value'=>$vals['qty'], 'class'=>['rightered'], 'num'=>1 ],
'foc' => [ 'value'=>$vals['foc'], 'class'=>['rightered'], 'num'=>1 ],
'line_subtotal' => [ 'value'=>$vals['line_subtotal'], 'class'=>['rightered'], 'num'=>1 ],
'line_discount' => [ 'value'=>$vals['line_discount'], 'class'=>['rightered'] ],
'order_discount' => [ 'value'=>$vals['order_discount'], 'class'=>['rightered'], 'rowspan'=>$rowspan[ $doc ] ],
'line_total' => [ 'value'=>$vals['line_final_total'], 'class'=>['rightered'], 'num'=>1 ],

										];
									}

									if( $client_added == $client ) $row['client'] = [];
									$client_added = $client;

									if( $doc_added == $doc ) 
									{
										$row['docno'] = [];
										$row['doc_date'] = [];
										$row['order_discount'] = [];
									}
									$doc_added = $doc;

									$details[] = $row;
								}

								if( $params['grouping'] )
								{
									$details[] = [
										'client' => [],
										'docno' => [],
										'doc_date' => [],
										'product' => [ 'value'=>'SO Total:', 'class'=>['leftered','bold'], 'colspan'=>6 ],
										'line_total' => [ 'value'=>$totals[ $client ][ $doc ], 'class'=>['rightered','bold'], 'num'=>1 ],
									];
								}
								else
								{
									$details[] = [
										'client' => [],
										'docno' => [],
										'doc_date' => [],
										'product' => [ 'value'=>'SO Total:', 'class'=>['leftered','bold'], 'colspan'=>9 ],
										'line_total' => [ 'value'=>$totals[ $client ][ $doc ], 'class'=>['rightered','bold'], 'num'=>1 ],
									];
								}
								
								$subtotal+= $totals[ $client ][ $doc ];
							}

							if( $params['grouping'] )
							{
								$details[] = [
									'client' => [],
									'docno' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>8 ],
									'line_total' => [ 'value'=>$subtotal, 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}
							else
							{
								$details[] = [
									'client' => [],
									'docno' => [ 'value'=>'Subtotal:', 'class'=>['leftered','bold'], 'colspan'=>11 ],
									'line_total' => [ 'value'=>$subtotal, 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}

							$total+= $subtotal;
						}

						if( $params['grouping'] )
						{
							$details[] = [
								'client' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>9 ],
								'line_total' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
							];
						}
						else
						{
							$details[] = [
								'client' => [ 'value'=>'TOTAL:', 'class'=>['leftered','bold'], 'colspan'=>12 ],
								'line_total' => [ 'value'=>$total, 'class'=>['rightered','bold'], 'num'=>1 ],
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
			case 'export_sap':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export_sap'] ?>" 
					data-title="<?php echo $actions['export'] ?> for SAP" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> for SAP"
				>
					<i class="fa fa-download" aria-hidden="true"></i> SAP
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
		$action_id = 'sales_report_export';
		$args = array(
			'setting'	=> $this->setting,
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
			case 'delivery_order':
				do_action( 'wcwh_templating', 'report/export-sales-do-summary-report.php', $this->tplName['export'], $args );
			break;
			case 'summary':
				do_action( 'wcwh_templating', 'report/export-sales-summary-report.php', $this->tplName['export'], $args );
			break;
			case 'po_sales':
				do_action( 'wcwh_templating', 'report/export-sales-po-summary-report.php', $this->tplName['export'], $args );
			break;
			case 'canteen_einvoice':
				$args['export_type'] = 'canteen_einvoice';
				do_action( 'wcwh_templating', 'report/export-sales-canteen-report.php', $this->tplName['export'], $args );
			break;
			case 'canteen_einvoice_sap':
				$args['export_type'] = 'canteen_einvoice_sap';
				do_action( 'wcwh_templating', 'report/export-sales-canteen-report.php', $this->tplName['export_sap'], $args );
			break;
			case 'non_canteen_einvoice':
				$args['export_type'] = 'non_canteen_einvoice';
				do_action( 'wcwh_templating', 'report/export-sales-noncanteen-report.php', $this->tplName['export'], $args );
			break;
			case 'non_canteen_einvoice_sap':
				$args['export_type'] = 'non_canteen_einvoice_sap';
				do_action( 'wcwh_templating', 'report/export-sales-noncanteen-report.php', $this->tplName['export_sap'], $args );
			break;
			case 'unimart_einvoice':
				$args['export_type'] = 'unimart_einvoice';
				do_action( 'wcwh_templating', 'report/export-sales-unimart-report.php', $this->tplName['export'], $args );
			break;
			case 'unimart_einvoice_sap':
				$args['export_type'] = 'unimart_einvoice_sap';
				do_action( 'wcwh_templating', 'report/export-sales-unimart-report.php', $this->tplName['export_sap'], $args );
			break;
		}
	}

	public function printing_form( $type = 'summary' )
	{
		$action_id = 'sales_report_export';
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
		if( $this->doc_opts ) $args['doc_opts'] = $this->doc_opts;

		switch( strtolower( $type ) )
		{
			case 'delivery_order':
				do_action( 'wcwh_templating', 'report/export-sales-do-summary-report.php', $this->tplName['print'], $args );
			break;
			case 'summary':
				do_action( 'wcwh_templating', 'report/export-sales-summary-report.php', $this->tplName['print'], $args );
			break;
			case 'po_sales':
				do_action( 'wcwh_templating', 'report/export-sales-po-summary-report.php', $this->tplName['print'], $args );
			break;
			case 'canteen_einvoice':
				do_action( 'wcwh_templating', 'report/export-sales-canteen-report.php', $this->tplName['print'], $args );
			break;
			case 'non_canteen_einvoice':
				do_action( 'wcwh_templating', 'report/export-sales-noncanteen-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	Sale Order Summary
	 */
	public function so_summary_report( $filters = array(), $order = array() )
	{
		$action_id = 'so_summary_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/salesSummaryList.php" ); 
			$Inst = new WCWH_SO_Summary_report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			$filters['doc_stat'] = !empty( $filters['doc_stat'] )? $filters['doc_stat'] : 'all';

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.qty, .foc, .sprice, .line_total, .def_price, .order_discount, .line_discount, .line_subtotal, .order_total
				, .final_sprice, .line_final_total' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_so_summary_report( $filters, $order, [] );
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
	 *	SO Delivery Order Summary
	 */
	public function so_delivery_order_summary_report( $filters = array(), $order = array() )
	{
		$action_id = 'so_delivery_order_summary_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/salesDOSummaryList.php" ); 
			$Inst = new WCWH_SO_DO_Summary_report();
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
				'.qty, .unit_price, .total_sale, .unit_cost, .total_cost, .profit' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_so_delivery_order_summary_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = [];
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['do_docno'];
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
	 *	SO Purchase Order Summary
	 */
	public function so_po_summary_report( $filters = array(), $order = array() )
	{
		$action_id = 'so_po_summary_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/salesPOSummaryList.php" ); 
			$Inst = new WCWH_SO_PO_Summary_report();
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
				'.qty, .unit_price, .line_total' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_so_po_summary_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = [];
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['so_no'];
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
	 *	Canteen/Minimart eInvoice
	 */
	public function so_sap_canteen_einvoice( $filters = array(), $order = array() )
	{
		$action_id = 'so_sap_canteen_einvoice';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/salesCanteenList.php" ); 
			$Inst = new WCWH_SO_Canteen_Listing();
			$Inst->seller = $this->seller;
			$Inst->Setting = $this->Setting;
			
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
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.qty, .unit_price, .total_sale, .unit_cost, .total_cost, .profit, .amount' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_so_sap_canteen_einvoice( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = [];
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['do_no'];
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
	 *	Non Canteen/Direct Sales eInvoice
	 */
	public function so_sap_non_canteen_einvoice( $filters = array(), $order = array() )
	{
		$action_id = 'so_sap_non_canteen_einvoice';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/salesNonCanteenList.php" ); 
			$Inst = new WCWH_SO_NonCanteen_Listing();
			$Inst->seller = $this->seller;
			$Inst->Setting = $this->Setting;
			
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
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.qty, .unit_price, .total_sale, .unit_cost, .total_cost, .profit, .amount' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_so_sap_non_canteen_einvoice( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = [];
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['do_no'];
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
	 *	Unimart eInvoice
	 */
	public function so_sap_unimart_einvoice( $filters = array(), $order = array() )
	{
		$action_id = 'so_sap_unimart_einvoice';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/salesUnimartList.php" ); 
			$Inst = new WCWH_SO_Unimart_Listing();
			$Inst->seller = $this->seller;
			$Inst->Setting = $this->Setting;
			
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
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.qty, .unit_price, .total_sale, .unit_cost, .total_cost, .profit, .amount' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_so_sap_unimart_einvoice( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}

			$doc = []; $doc_opts = [];
			foreach( $datas as $i => $dat )
			{
				$doc_opts[ $dat['doc_id'] ] = $dat['do_no'];
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
	public function get_so_summary_report( $filters = [], $order = [], $args = [] )
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

		$margining_id = "wh_sales_rpt_summary";

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

		$field = "a.doc_id, b.item_id, a.docno, a.doc_date, a.created_at, c.code AS client_code, c.name AS client_name
			, CAST( ig.meta_value AS UNSIGNED ) AS item_no, i.code AS item_code, i.name AS item_name
			, cat.slug AS category_code, cat.name AS category_name
			, @qty:= ROUND( b.bqty - IF( id.meta_value != 0, id.meta_value, 0 ), 2 ) AS qty, i._uom_code AS uom
			, @foc:= IF( id.meta_value != 0, id.meta_value, 0 ) AS foc, ia.meta_value AS def_price
			, @line_subtotal:= ROUND( @qty * ia.meta_value, 2 ) AS line_subtotal
			, @discount:= IF( ic.meta_value IS NULL, 0,IF( RIGHT( TRIM( ic.meta_value ), 1 ) = '%', 
				ROUND( ( @line_subtotal / 100 ) * REPLACE( TRIM( ic.meta_value ), '%', '' ), 2 ), ic.meta_value ) ) AS line_discount
			, ROUND( ( @line_subtotal - @discount ) / ( @qty + @foc ), 5 ) AS sprice 
			, @line_total:= ROUND( @line_subtotal - @discount, 2 ) AS line_total 
			, @fsprice:= ROUND( ROUND( ROUND( st.subtotal - st.discounted, 2 ) * ( @line_total / st.subtotal ), 2 ) / (@qty + @foc), 5 ) AS final_sprice 
			, ROUND( ROUND( st.subtotal - st.discounted, 2 ) * ( @line_total / st.subtotal ), 2 ) AS line_final_total 
			, st.subtotal AS order_subtotal, st.discounted AS order_discount
			, ROUND( st.subtotal - st.discounted, 2 ) AS order_total, b.uqty AS complete_qty
			, ROUND( @fsprice * b.uqty, 2 ) AS complete_line_total
			, ROUND( sta.order_total - ( st.order_total ), 2 ) AS adj_total_sale ";
		
		$table = "{$dbname}{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = a.doc_id AND mb.item_id = 0 AND mb.meta_key = 'discount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = a.doc_id AND mc.item_id = 0 AND mc.meta_key = 'sap_po' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['client_tree']} ";
			$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} cc ON cc.id = ( {$subsql} ) ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = b.doc_id AND ia.item_id = b.item_id AND ia.meta_key = 'def_sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = b.doc_id AND ib.item_id = b.item_id AND ib.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ic ON ic.doc_id = b.doc_id AND ic.item_id = b.item_id AND ic.meta_key = 'discount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} id ON id.doc_id = b.doc_id AND id.item_id = b.item_id AND id.meta_key = 'foc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ie ON ie.doc_id = b.doc_id AND ie.item_id = b.item_id AND ie.meta_key = 'line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ig ON ig.doc_id = b.doc_id AND ig.item_id = b.item_id AND ig.meta_key = '_item_number' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = b.product_id ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( {$subsql} ) ";

		$table.= "LEFT JOIN ( 
			SELECT st.doc_id, st.margin, st.order_subtotal AS subtotal, st.order_discount AS discounted, st.order_total 
			FROM {$dbname}{$this->tables['margining_sales']} st
			WHERE 1 AND st.type = 'def' AND st.status > 0
			GROUP BY st.type, st.doc_id
		) st ON st.doc_id = a.doc_id ";

		$table.= "LEFT JOIN ( 
			SELECT st.doc_id, st.margin, st.order_subtotal AS subtotal, st.order_discount AS discounted, st.order_total 
			FROM {$dbname}{$this->tables['margining_sales']} st
			WHERE 1 AND st.type = 'adj' AND st.status > 0
			GROUP BY st.type, st.doc_id
		) sta ON sta.doc_id = a.doc_id ";

		if( $this->need_margining )
		{
			$field = "a.doc_id, b.item_id, a.docno, a.doc_date, a.created_at, c.code AS client_code, c.name AS client_name 
			, CAST( ig.meta_value AS UNSIGNED ) AS item_no, i.code AS item_code, i.name AS item_name 
			, cat.slug AS category_code, cat.name AS category_name 
			, @mg:= IFNULL( mg.margin, 0 ) AS margin
			, @rn:= IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) AS round_nearest 
			, @qty:= ROUND( b.bqty - IF( id.meta_value != 0, id.meta_value, 0 ), 2 ) AS qty, i._uom_code AS uom 
			, @foc:= IF( id.meta_value != 0, id.meta_value, 0 ) AS foc 
			, @def_price:= IF( mg.id > 0, ROUND( CASE 
				WHEN mg.round_type = 'ROUND' THEN ROUND( ROUND( ia.meta_value*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
				WHEN mg.round_type = 'CEIL' THEN CEIL( ROUND( ia.meta_value*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
	          	WHEN mg.round_type = 'FLOOR' THEN FLOOR( ROUND( ia.meta_value*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
	          	WHEN mg.round_type IS NULL OR mg.round_type = 'DEFAULT' THEN ROUND( ia.meta_value*( 1+( @mg/100 ) ), 5 ) 
	          	END, 5 ), ia.meta_value ) AS def_price 
			, @line_subtotal:= ROUND( @qty * @def_price, 2 ) AS line_subtotal 
			, @discount:= IF( ic.meta_value IS NULL, 0, IF( RIGHT( TRIM( ic.meta_value ), 1 ) = '%', 
				ROUND( ( @line_subtotal / 100 ) * REPLACE( TRIM( ic.meta_value ), '%', '' ), 2 ), ic.meta_value ) ) AS line_discount 
			, ROUND( ( @line_subtotal - @discount ) / ( @qty + @foc ), 5 ) AS sprice 
			, @line_total:= ROUND( @line_subtotal - @discount, 2 ) AS line_total 
			, @fsprice:= ROUND( ROUND( ROUND( st.subtotal - st.discounted, 2 ) * ( @line_total / st.subtotal ), 2 ) / (@qty + @foc), 5 ) AS final_sprice 
			, ROUND( ROUND( st.subtotal - st.discounted, 2 ) * ( @line_total / st.subtotal ), 2 ) AS line_final_total 
			, st.subtotal AS order_subtotal, st.discounted AS order_discount
			, ROUND( st.subtotal - st.discounted, 2 ) AS order_total, b.uqty AS complete_qty
			, ROUND( @fsprice * b.uqty, 2 ) AS complete_line_total
			, ROUND( sta.order_total - ( st.order_total ), 2 ) AS adj_total_sale ";

			$subsql = $wpdb->prepare( "SELECT h.id 
                    FROM {$this->tables['margining']} h 
                    LEFT JOIN {$this->tables['margining_sect']} s ON s.mg_id = h.id AND s.status > 0
                    WHERE 1 AND h.status > 0 AND h.flag > 0 
                    AND h.wh_id = a.warehouse_id AND h.type = %s AND s.sub_section = %s 
                    AND ( ( h.po_inclusive = 'def' ) OR ( h.po_inclusive = 'with' AND LENGTH( mc.meta_value ) > 0 ) OR 
                            ( h.po_inclusive = 'without' AND LENGTH( mc.meta_value ) = 0 OR mc.meta_value IS NULL ) )
                    AND h.since <= a.doc_date AND ( h.until >= a.doc_date OR h.until = '' ) 
                    ORDER BY h.effective DESC, h.since DESC, h.created_at DESC 
                    LIMIT 0,1 ", 'def', $margining_id );

			//$table.= "LEFT JOIN {$this->tables['margining_det']} mgd ON mgd.id = ( {$subsql} ) ";
			$table.= "LEFT JOIN {$this->tables['margining']} mh ON mh.id = ( {$subsql} ) ";
			
			$subsql = "SELECT m.id
				FROM {$this->tables['margining']} m 
				LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
				WHERE 1 AND m.id = mh.id AND m.inclusive = 'excl' AND md.client = cc.code ";
			$table.= "LEFT JOIN {$this->tables['margining']} mx ON mx.id = ( {$subsql} ) ";

			$subsql = "SELECT m.id
				FROM {$this->tables['margining']} m 
				LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
				WHERE 1 AND m.id = mh.id 
				AND ( ( m.inclusive = 'incl' AND md.client = cc.code ) OR ( m.inclusive = 'excl' AND ( m.id != mx.id OR mx.id IS NULL ) ) ) 
				ORDER BY m.effective DESC, m.since DESC, m.created_at DESC 
				LIMIT 0,1
			";
			$table.= "LEFT JOIN {$this->tables['margining']} mg ON mg.id = ( {$subsql} ) ";
		}
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status > %d ", 'sale_order', 0 );

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
		if( isset( $filters['client'] ) )
		{
			if( is_array( $filters['client'] ) )
			{
				$catcd = "c.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$catcd.= "OR cc.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "c.id = %d ", $filters['client'] );
				$catcd = $wpdb->prepare( "OR cc.id = %d ", $filters['client'] );
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
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.docno'=>'ASC', 'c.code'=>'ASC', 'item_no' => 'ASC' ];

			if( $filters['is_print'] ) $order = [ 'c.code'=>'ASC', 'a.docno'=>'ASC', 'item_no' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $filters['grouping'] )
		{
			$field = "a.doc_id, a.docno, a.doc_date, a.created_at, a.client_code, a.client_name 
			, a.category_code, a.category_name
			, SUM( a.qty ) AS qty, SUM( a.foc ) AS foc
			, ROUND( SUM( a.line_subtotal ), 2 ) AS line_subtotal
			, ROUND( SUM( a.line_discount ), 2 ) AS line_discount
			, ROUND( SUM( a.line_total ), 2 ) AS line_total 
			, ROUND( SUM( a.line_final_total ), 2 ) AS line_final_total 
			, ROUND( SUM( a.order_subtotal ), 2 ) AS order_subtotal, ROUND( SUM( a.order_discount ), 2 ) AS order_discount 
			, ROUND( SUM( a.order_total ), 2 ) AS order_total ";

			$table = "( {$sql} ) a ";
			//$table.= "LEFT JOIN {$this->tables['temp_st']} st ON st.doc_id = a.doc_id ";
			$cond = "";
			$grp = "GROUP BY a.client_code, a.docno, a.category_code ";
			$ord = "ORDER BY a.client_code ASC, a.docno ASC, a.category_code ASC ";

			$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		}

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_so_delivery_order_summary_report( $filters = [], $order = [], $args = [] )
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

	    //------------------------------------------------------------------

	    $margining_id = "wh_sales_rpt_delivery_order";

		//------------------------------------------------------------------

		$field = "h.doc_id, ph.docno AS so_docno, ph.doc_date AS so_date, h.docno AS do_docno, h.doc_date AS do_date, h.post_date AS do_post_date 
			, me.meta_value AS remark 
			, c.code AS client_code, c.name AS client_name 
			, cat.slug AS category_code, cat.name AS category_name
			, CAST( ig.meta_value AS UNSIGNED ) AS item_no, i.code AS item_code, i.name AS item_name 
			, d.bqty AS qty, i._uom_code AS uom 
			, pi.final_sprice AS unit_price 
			, @ts:= ROUND( d.bqty * pi.final_sprice, 2 ) AS total_sale 
			, ROUND( IFNULL( ib.meta_value, IF( mb.meta_value = 'good_issue', tti.weighted_total / d.bqty, ti.weighted_total / d.bqty ) ), 5 ) AS unit_cost
			, @tc:= ROUND( IFNULL( ib.meta_value * d.bqty, IF( mb.meta_value = 'good_issue', tti.weighted_total, ti.weighted_total ) ), 2 ) AS total_cost
			, ROUND( @ts - @tc, 2 ) AS profit 
			, pia.final_sprice - pi.final_sprice AS adj_unit_price 
	    	, ROUND( ( d.bqty * pia.final_sprice ) - ( d.bqty * pi.final_sprice ), 2 ) AS adj_total_sale ";

		if( $filters['grouping'] )
		{
			$field = "h.doc_id, ph.docno AS so_docno, ph.doc_date AS so_date, h.docno AS do_docno, h.doc_date AS do_date, h.post_date AS do_post_date 
				, me.meta_value AS remark 
				, c.code AS client_code, c.name AS client_name 
				, cat.slug AS category_code, cat.name AS category_name 
				, SUM( d.bqty ) AS qty 
				, ROUND( SUM( ROUND( d.bqty * pi.final_sprice, 2 ) ), 2 ) AS total_sale
				, ROUND( SUM( ROUND( IFNULL( ib.meta_value * d.bqty, IF( mb.meta_value = 'good_issue', tti.weighted_total, ti.weighted_total ) ), 2 ) ), 2 ) AS total_cost ";
		}
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'base_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'base_doc_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} me ON me.doc_id = h.doc_id AND me.item_id = 0 AND me.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['client_tree']} ";
			$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} cc ON cc.id = ( {$subsql} ) ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ig ON ig.doc_id = h.doc_id AND ig.item_id = d.item_id AND ig.meta_key = '_item_number' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.item_id = d.item_id AND ti.status != 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} td ON td.doc_id = d.ref_doc_id AND td.item_id = d.ref_item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} tti ON tti.item_id = td.item_id AND tti.status != 0 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = md.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pi ON pi.doc_id = md.meta_value AND pi.product_id = d.product_id AND pi.warehouse_id = ph.warehouse_id AND pi.type = 'def' AND pi.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pia ON pia.doc_id = md.meta_value AND pia.product_id = d.product_id AND pia.warehouse_id = ph.warehouse_id AND pia.type = 'adj' AND pia.status > 0 ";


		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND mc.meta_value = %s ", 'delivery_order', 6, 'sale_order' );

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND h.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.doc_id = %d ", $filters['doc_id'] );
		}
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
			{
				$catcd = "c.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$catcd.= "OR cc.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "c.id = %d ", $filters['client'] );
				$catcd = $wpdb->prepare( "OR cc.id = %d ", $filters['client'] );
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
                $cd[] = "h.docno LIKE '%".$kw."%' ";
				$cd[] = "h.sdocno LIKE '%".$kw."%' ";
				$cd[] = "ph.docno LIKE '%".$kw."%' ";
				$cd[] = "ph.sdocno LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "";
		if( $filters['grouping'] )
		{
			$grp = "GROUP BY c.code, ph.docno, h.docno, i.category ";
		}

		//order
		if( empty( $order ) )
		{
			$order = [ 'ph.docno' => 'ASC', 'h.docno' => 'ASC', 'i.category' => 'ASC', 'item_no' => 'ASC' ];

			if( $filters['is_print'] ) $order = [ 'c.code'=>'ASC', 'ph.docno' => 'ASC', 'h.docno' => 'ASC', 'item_no' => 'ASC' ];

			if( $filters['grouping'] ) $order = [ 'c.code'=>'ASC', 'ph.docno' => 'ASC', 'h.docno' => 'ASC', 'i.category' => 'ASC' ];
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

    public function get_so_po_summary_report( $filters = [], $order = [], $args = [] )
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
			
			//pd( $wpdb->get_results( "SELECT * FROM {$this->tables['temp_po']} ", ARRAY_A ) );
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

		$field = "h.doc_id, h.docno AS so_no, h.doc_date AS so_date, gma.meta_value AS po_no, grh.docno AS gr_no, doh.docno AS do_no
			, md.meta_value AS gr_invoice, c.code AS client_code, c.name AS client_name 
			, cat.slug AS category_code, cat.name AS category_name
			, CAST( ig.meta_value AS UNSIGNED ) AS item_no, i.code AS item_code, i.name AS item_name 
			, d.bqty AS qty, i._uom_code AS uom, IFNULL( ic.meta_value, 0 ) AS foc 
			, ia.meta_value AS unit_price, ib.meta_value AS line_total ";

		if( $filters['grouping'] )
		{
			$field = "h.doc_id, h.docno AS so_no, h.doc_date AS so_date, gma.meta_value AS po_no, grh.docno AS gr_no, doh.docno AS do_no
				, md.meta_value AS gr_invoice, c.code AS client_code, c.name AS client_name 
				, cat.slug AS category_code, cat.name AS category_name
				, SUM( d.bqty + IFNULL( ic.meta_value, 0 ) ) AS qty  
				, ROUND( SUM( ib.meta_value ), 2 ) AS line_total ";
		}
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'ref_doc_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'gr_invoice' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['client_tree']} ";
			$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} cc ON cc.id = ( {$subsql} ) ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ic ON ic.doc_id = h.doc_id AND ic.item_id = d.item_id AND ic.meta_key = 'foc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ig ON ig.doc_id = h.doc_id AND ig.item_id = d.item_id AND ig.meta_key = '_item_number' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} grh ON grh.doc_id = h.parent AND grh.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} gma ON gma.doc_id = grh.doc_id AND gma.item_id = 0 AND gma.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} doh ON doh.parent = h.doc_id AND doh.status > 0 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND mb.meta_value = %s ", 'sale_order', 6, 'good_receive' );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}

		if( isset( $filters['sequence_doc'] ) )
		{
			$cond.= "AND ( gma.meta_value IN ('" .implode( "','", $filters['sequence_doc'] ). "') ) ";
			$table.= "LEFT JOIN {$this->tables['temp_po']} sp ON sp.po_no = gma.meta_value ";

			$order = [ 'sp.id'=>'ASC', 'item_no' => 'ASC' ];
			if( $filters['grouping'] ) $order = [ 'sp.id'=>'ASC', 'i.category' => 'ASC' ];
		}
		else
		{
			if( isset( $filters['doc_id'] ) )
			{
				if( is_array( $filters['doc_id'] ) )
					$cond.= "AND h.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND h.doc_id = %d ", $filters['doc_id'] );
			}
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
			}
		}
		
		if( isset( $filters['client'] ) )
		{
			if( is_array( $filters['client'] ) )
			{
				$catcd = "c.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$catcd.= "OR cc.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "c.id = %d ", $filters['client'] );
				$catcd = $wpdb->prepare( "OR cc.id = %d ", $filters['client'] );
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
                $cd[] = "h.docno LIKE '%".$kw."%' ";
				$cd[] = "h.sdocno LIKE '%".$kw."%' ";
				$cd[] = "grh.docno LIKE '%".$kw."%' ";
				$cd[] = "grh.sdocno LIKE '%".$kw."%' ";
				$cd[] = "doh.docno LIKE '%".$kw."%' ";
				$cd[] = "doh.sdocno LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "";
		if( $filters['grouping'] )
		{
			$grp = "GROUP BY h.docno, i.category ";
		}

		//order
		if( empty( $order ) )
		{
			$order = [ 'h.docno' => 'ASC', 'item_no' => 'ASC' ];

			if( $filters['grouping'] ) $order = [ 'h.docno' => 'ASC', 'i.category' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		//pd($sql, 1);
		return $results;
	}

	//minimart einvoice
	public function get_so_sap_canteen_einvoice( $filters = [], $order = [], $args = [] )
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

	    if( ( ! isset( $filters['client'] ) || empty( $filters['client'] ) ) && 
	    	!empty( $this->Setting['minimart_einvoice']['client'] ) )
	    	$filters['client'] = $this->Setting['minimart_einvoice']['client'];

	    if( !empty( $this->Setting['minimart_einvoice']['item_calc_profit'] ) )
	    	$icp = $this->Setting['minimart_einvoice']['item_calc_profit'];

	    //------------------------------------------------------------------

	    $margining_id = "wh_sales_rpt_canteen_einvoice";

		//------------------------------------------------------------------

	    $field = "h.doc_id, c.code AS client_code, c.name AS client_name 
	    	, CONCAT( ph.docno, '-I01' ) AS invoice_no, h.docno AS do_no, h.doc_date AS do_date, h.post_date AS do_post_date  
	    	, cat.slug AS category_code, cat.name AS category_name 
	    	, i.code AS item_code, i.name AS item_name 
	    	, d.bqty AS qty, i._uom_code AS uom 
	    	, pi.final_sprice AS unit_price 
	    	, @dts:= ROUND( d.bqty * ia.meta_value, 2 ) AS def_total_sale 
	    	, @ts:= ROUND( d.bqty * pi.final_sprice, 2 ) AS total_sale 
			, ROUND( IFNULL( ib.meta_value, IF( mb.meta_value = 'good_issue', tti.weighted_total / d.bqty, ti.weighted_total / d.bqty ) ), 5 ) AS unit_cost
			, @tc:= ROUND( IFNULL( ib.meta_value * d.bqty, IF( mb.meta_value = 'good_issue', tti.weighted_total, ti.weighted_total ) ), 2 ) AS total_cost
			, @profit:= ROUND( @ts - @tc, 2 ) AS profit 
			, pia.final_sprice - pi.final_sprice AS adj_unit_price 
	    	, ROUND( ( d.bqty * pia.final_sprice ) - ( d.bqty * pi.final_sprice ), 2 ) AS adj_total_sale ";

		if( $icp )
		{
			$field.= ", IF( d.product_id IN( ".implode( ", ", $icp )." ), @ts - @tc, @ts ) AS amount ";
		}
		else
		{
			$field.= ", @ts AS amount ";
		}
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'base_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'base_doc_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['client_tree']} ";
			$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} cc ON cc.id = ( {$subsql} ) ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = h.doc_id AND ia.item_id = d.item_id AND ia.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.item_id = d.item_id AND ti.status != 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} td ON td.doc_id = d.ref_doc_id AND td.item_id = d.ref_item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} tti ON tti.item_id = td.item_id AND tti.status != 0 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = md.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pi ON pi.doc_id = md.meta_value AND pi.product_id = d.product_id AND pi.warehouse_id = ph.warehouse_id AND pi.type = 'def' AND pi.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pia ON pia.doc_id = md.meta_value AND pia.product_id = d.product_id AND pia.warehouse_id = ph.warehouse_id AND pia.type = 'adj' AND pia.status > 0 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND mc.meta_value = %s ", 'delivery_order', 6, 'sale_order' );

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND h.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.doc_id = %d ", $filters['doc_id'] );
		}
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
			{
				$catcd = "c.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$catcd.= "OR cc.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "c.id = %d ", $filters['client'] );
				$catcd = $wpdb->prepare( "OR cc.id = %d ", $filters['client'] );
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
                $cd[] = "h.docno LIKE '%".$kw."%' ";
				$cd[] = "h.sdocno LIKE '%".$kw."%' ";
				$cd[] = "ph.docno LIKE '%".$kw."%' ";
				$cd[] = "ph.sdocno LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'c.code'=>'ASC', 'ph.docno' => 'ASC', 'h.docno' => 'ASC', 'i.category' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $filters['is_print'] )
		{
			$field = "a.client_code, a.client_name, a.invoice_no, a.do_no, a.do_date, a.category_code, a.category_name 
	    	, ROUND( SUM( a.amount ), 2 ) AS amount, a.sap_po, ROUND( SUM( a.adj_total_sale ), 2 ) AS adj_amount ";

	    	$table = "( {$sql} ) a ";
	    	$cond = "";
	    	$grp = "GROUP BY a.client_code, a.invoice_no, a.do_no, a.category_code ";
	    	$ord = "ORDER BY a.client_code ASC, a.invoice_no ASC, a.do_no ASC, a.category_code ASC ";

	    	$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		}

		if( $filters['export_sap'] )
		{
			$field = "a.client_code, a.client_name, a.invoice_no, a.do_no, a.do_post_date AS do_date, a.category_code, a.category_name 
	    	, ROUND( SUM( a.amount ), 2 ) AS amount ";

	    	$table = "( {$sql} ) a ";
	    	$cond = "";
	    	$grp = "GROUP BY a.client_code, a.invoice_no, a.do_no, a.category_code ";
	    	$ord = "ORDER BY a.client_code ASC, a.invoice_no ASC, a.do_no ASC, a.category_code ASC ";

	    	$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			/*$field = "a.client_code, a.client_name, DATE_FORMAT( a.do_date, '%m-%Y' ) AS period
			, 'I' AS invoice_type, ROUND( SUM( a.amount ), 2 ) AS amount ";

	    	$table = "( {$sql} ) a ";
	    	$cond = "";
	    	$grp = "GROUP BY a.client_code ";
	    	$ord = "ORDER BY a.client_code ASC, period ASC ";

	    	$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";*/
		}

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	//Direct Sales einvoice
	public function get_so_sap_non_canteen_einvoice( $filters = [], $order = [], $args = [] )
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

	    if( ( ! isset( $filters['client'] ) || empty( $filters['client'] ) ) && 
	    	!empty( $this->Setting['direct_sales_einvoice']['exclude_client'] ) )
	    	$filters['not_client'] = $this->Setting['direct_sales_einvoice']['exclude_client'];

	    if( !empty( $this->Setting['direct_sales_einvoice']['item_calc_profit'] ) )
	    	$icp = $this->Setting['direct_sales_einvoice']['item_calc_profit'];

	    //------------------------------------------------------------------
	    //SO margining
	    $margining_id = "wh_sales_rpt_non_canteen_einvoice";

		//------------------------------------------------------------------

	    $field = "h.doc_id, c.code AS client_code, c.name AS client_name 
	    	, ph.docno AS so_no, CONCAT( ph.docno, '-I01' ) AS invoice_no, ph.doc_date AS so_date, ph.post_date AS so_post_date
	    	, pma.meta_value AS sap_po  
	    	, pmb.meta_value AS remark, h.docno AS do_no, h.doc_date AS do_date, h.post_date AS do_post_date  
	    	, ct.slug AS main_category_code, ct.name AS main_category_name 
	    	, cat.slug AS category_code, cat.name AS category_name 
	    	, i.code AS item_code, i.name AS item_name 
	    	, d.bqty AS qty, i._uom_code AS uom 
	    	, pi.final_sprice AS unit_price 
	    	, @ts:= ROUND( d.bqty * pi.final_sprice, 2 ) AS total_sale 
			, ROUND( IFNULL( ib.meta_value, IF( mb.meta_value = 'good_issue', tti.weighted_total / d.bqty, ti.weighted_total / d.bqty ) ), 5 ) AS unit_cost
			, @tc:= ROUND( IFNULL( ib.meta_value * d.bqty, IF( mb.meta_value = 'good_issue', tti.weighted_total, ti.weighted_total ) ), 2 ) AS total_cost
			, @profit:= ROUND( @ts - @tc, 2 ) AS profit
			, pia.final_sprice - pi.final_sprice AS adj_unit_price 
	    	, ROUND( ( d.bqty * pia.final_sprice ) - ( d.bqty * pi.final_sprice ), 2 ) AS adj_total_sale ";

		if( $icp )
		{
			$field.= ", IF( d.product_id IN( ".implode( ", ", $icp )." ), @ts - @tc, @ts ) AS amount ";
		}
		else
		{
			$field.= ", @ts AS amount ";
		}
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'base_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'base_doc_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['client_tree']} ";
			$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} cc ON cc.id = ( {$subsql} ) ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = h.doc_id AND ib.item_id = d.item_id AND ib.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.item_id = d.item_id AND ti.status != 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} td ON td.doc_id = d.ref_doc_id AND td.item_id = d.ref_item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} tti ON tti.item_id = td.item_id AND tti.status != 0 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = md.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} pma ON pma.doc_id = ph.doc_id AND pma.item_id = 0 AND pma.meta_key = 'sap_po' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} pmb ON pmb.doc_id = ph.doc_id AND pmb.item_id = 0 AND pmb.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pi ON pi.doc_id = md.meta_value AND pi.product_id = d.product_id AND pi.warehouse_id = ph.warehouse_id AND pi.type = 'def' AND pi.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pia ON pia.doc_id = md.meta_value AND pia.product_id = d.product_id AND pia.warehouse_id = ph.warehouse_id AND pia.type = 'adj' AND pia.status > 0 ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND mc.meta_value = %s ", 'delivery_order', 6, 'sale_order' );

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND h.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.doc_id = %d ", $filters['doc_id'] );
		}
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
			{
				$catcd = "c.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$catcd.= "OR cc.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "c.id = %d ", $filters['client'] );
				$catcd.= $wpdb->prepare( "OR cc.id = %d ", $filters['client'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		if( isset( $filters['not_client'] ) )
		{
			if( is_array( $filters['not_client'] ) )
				$cond.= "AND c.id NOT IN ('" .implode( "','", $filters['not_client'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.id != %s ", $filters['not_client'] );
		}
		if( $icp )
		{
			//$cond.= "AND i.id NOT IN( ".implode( ", ", $icp )." ) ";
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
                $cd[] = "h.docno LIKE '%".$kw."%' ";
				$cd[] = "h.sdocno LIKE '%".$kw."%' ";
				$cd[] = "ph.docno LIKE '%".$kw."%' ";
				$cd[] = "ph.sdocno LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'c.code'=>'ASC', 'ph.docno' => 'ASC', 'h.docno' => 'ASC', 'i.category' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		if( $filters['is_print'] )
		{
			$field = "a.client_code, a.client_name, a.so_no, a.invoice_no, so_date AS invoice_date
			, a.do_no, a.do_date, a.category_code, a.category_name 
	    	, ROUND( SUM( a.amount ), 2 ) AS amount, a.sap_po, ROUND( SUM( a.adj_total_sale ), 2 ) AS adj_amount ";

	    	$table = "( {$sql} ) a ";
	    	$cond = "";
	    	$grp = "GROUP BY a.client_code, a.so_no, a.do_no, a.category_code ";
	    	$ord = "ORDER BY a.client_code ASC, a.so_no ASC, a.do_no ASC, a.category_code ASC ";

	    	$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		}

		if( $filters['export_sap'] )
		{
			$field = "a.client_code, a.client_name, a.so_no AS sale_order, a.invoice_no
			, DATE_FORMAT( a.do_post_date, '%d.%m.%Y' ) AS posting_date
			, a.do_no AS delivery_order_no, a.main_category_code, a.main_category_name
			, a.category_code AS sub_category_code, a.category_name AS sub_category_name
	    	, ROUND( SUM( a.amount ), 2 ) AS amount, a.sap_po, a.remark ";

	    	$table = "( {$sql} ) a ";
	    	$cond = "";
	    	$grp = "GROUP BY a.client_code, a.so_no, a.do_no, a.category_code ";
	    	$ord = "ORDER BY a.client_code ASC, a.so_no ASC, a.do_no ASC, a.category_code ASC ";

	    	$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		}

		if( isset( $args['get_sql'] ) ) return $sql;

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	//Unimart Direct Sales einvoice
	public function get_so_sap_unimart_einvoice( $filters = [], $order = [], $args = [] )
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

	    if( !empty( $this->Setting['unimart_einvoice']['client'] ) )
	    	$filters['client'] = $this->Setting['unimart_einvoice']['client'];

	    if( !empty( $this->Setting['direct_sales_einvoice']['item_calc_profit'] ) )
	    	$icp = $this->Setting['direct_sales_einvoice']['item_calc_profit'];

	    //------------------------------------------------------------------
	    
	    $filter = $filters; unset( $filter['export_sap'] ); unset( $filter['is_print'] );
	    $arg = [ 'get_sql'=>1 ];
	    $dc_sql = $this->get_so_sap_non_canteen_einvoice( $filter, [], $arg );

		//------------------------------------------------------------------

		if ( !class_exists( "WCWH_POSSales_Rpt" ) ) include_once( WCWH_DIR . "/includes/reports/posSales.php" ); 
		$Inst = new WCWH_POSSales_Rpt();

		$pos_sql = "";
		if( $this->Setting['unimart_einvoice']['warehouse'] )
		{
			$dbn = get_warehouse_meta( $this->Setting['unimart_einvoice']['warehouse'], 'dbname', true );
			$dbn = ( $dbn )? $dbn."." : "";

			$filter = $filters;
			$filter['seller'] = $this->Setting['unimart_einvoice']['warehouse'];
			unset( $filter['warehouse'] ); unset( $filter['export_sap'] ); unset( $filter['is_print'] );

			$arg = [ 'get_sql'=>1 ];
			$arg['xtra_table'] = "LEFT JOIN {$dbn}{$this->tables['acc_type']} acc ON acc.id = h.acc_type ";
			$arg['xtra_field'] = ", acc.code AS account_type ";

			if( !empty( $this->Setting['unimart_einvoice']['mapping'] ) )
			{
				$mapping = $this->Setting['unimart_einvoice']['mapping'];

				$cases = [];
				foreach( $mapping as $i => $vals )
				{
					$cases[] = "WHEN acc.code = '{$vals['acc_type']}' THEN '{$vals['client']}' ";
				}

				if( ! empty( $cases) ) $arg['xtra_field'].= ", CASE ".implode( " ", $cases )." END AS company ";
			}

			$pos_sql = $Inst->get_pos_sales_detail_report( $filter, [], $arg );
		}

		//------------------------------------------------------------------

		if( empty( $pos_sql ) ) $sql = $dc_sql;
		else
		{
			$field = "a.doc_id, c.code AS client_code, c.name AS client_name, b.order_no AS so_no
				, b.customer_name AS pos_buyer, b.customer_code AS buyer_code, b.account_type
				, a.invoice_no, a.so_date, a.so_post_date, a.sap_po, a.remark, a.do_no, a.do_date, a.do_post_date
				, a.main_category_code, a.main_category_name, a.category_code, a.category_name, a.item_code, a.item_name
				, b.qty, a.uom, a.unit_price, @ts:= ROUND( b.qty * a.unit_price, 2 ) AS total_sale
				, a.unit_cost, @tc:= ROUND( b.qty * a.unit_cost, 2 ) AS total_cost, @ts - @tc AS profit
				, a.adj_unit_price, ROUND( a.adj_total_sale / a.qty * b.qty, 2 ) AS adj_total_sale ";

				if( $icp )
				{
					$field.= ", IF( i.id IN( ".implode( ", ", $icp )." ), @ts - @tc, @ts ) AS amount ";
				}
				else
				{
					$field.= ", @ts AS amount ";
				}

			$table = "( {$pos_sql} ) b ";
			$table.= "LEFT JOIN ( {$dc_sql} ) a ON a.item_code = b.item_code ";
			$table.= "LEFT JOIN $dbname{$this->tables['client']} c ON c.id = b.company ";
			$table.= "LEFT JOIN $dbname{$this->tables['items']} i ON i.code = a.item_code ";

			$cond = "AND a.doc_id > 0 ";

			$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ";
		}

		if( $filters['export_sap'] )
		{
			$field = "a.client_code, a.client_name, a.so_no AS sale_order, a.invoice_no
			, DATE_FORMAT( a.do_post_date, '%d.%m.%Y' ) AS posting_date
			, a.do_no AS delivery_order_no, a.main_category_code, a.main_category_name
			, a.category_code AS sub_category_code, a.category_name AS sub_category_name
			, ROUND( SUM( a.amount ), 2 ) AS amount, a.sap_po, a.remark ";

			$table = "( {$sql} ) a ";
			$cond = "";
			$grp = "GROUP BY a.client_code, a.so_no, a.do_no, a.category_code ";
			$ord = "ORDER BY a.client_code ASC, a.so_no ASC, a.do_no ASC, a.category_code ASC ";

			$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		}

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
} //class

}