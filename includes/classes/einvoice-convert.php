<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WCWH_EInvoice_Convert extends WCWH_CRUD_Controller
{
	private $temp_data = [];
	private $className = 'EInvoice_Convert';
	
	protected $tables = [];

	public $Notices;

	public $defineTypeCode = [
		"e_invoice" => "01", 
		"conso_invoice" => "01",
		"sale_credit_note" => "02", 
		"sale_debit_note" => "03",
		"sb_invoice" => "11", 
		"sb_credit_note" => "12", 
		"sb_debit_note" => "13",
	];

	public $defineDocType = [
		"e_invoice" => "invoice", 
		"conso_invoice" => "invoice", 
		"sale_credit_note" => "credit_note", 
		"sale_debit_note" => "debit_note",
		"sb_invoice" => "sb_invoice", 
		"sb_credit_note" => "sb_credit_note", 
		"sb_debit_note" => "sb_debit_note",
	];

	public $defineCurrency = [
		"MYR" => "MYR", 
		"USD" => "USD", 
		"SGD" => "SGD", 
		"BND" => "BND", 
		"AUD" => "AUD", 
		"JPY" => "JPY", 
		"CNY" => "CNY", 
	];

	public $defineCountry = [
		"MY" => "MYS", 
		"CN" => "CHN", 
		"SG" => "SGP", 
		"BN" => "BRN", 
		"AU" => "AUS", 
	];

	public $defineState = [
		"JHR" => "01", 
		"KDH" => "02", 
		"KTN" => "03", 
		"MLK" => "04", 
		"NSN" => "05", 
		"PHG" => "06", 
		"PNG" => "07", 
		"PRK" => "08", 
		"PLS" => "09", 
		"SGR" => "10",
		"TRG" => "11", 
		"SBH" => "12", 
		"SWK" => "13", 
		"KUL" => "14", 
		"PJY" => "16", 
	];

	public $defineUOM = [
		"BOT" => "XGB", 
		"BOX" => "XBX", 
		"CAN" => "XCX",
		"CTN" => "XCT",
		"CAR" => "XCT", 
		"G" => "GRM",
		"JAR" => "XJR",
		"KG" => "KGM",
		"L" => "K62", 
		"mL" => "MLT",
		"PAC" => "XPK",
		"Pair" => "PR",
		"PCS" => "XPP", 
		"Roll" => "XRO",
		"TIN" => "XTN",
		"UNT" => "XUN", 
		"SET" => "SET", 
		"BDL" => "XBE",
		"m" => "MTR",
	];

	public function __construct() 
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		global $wcwh;
		$prefix = $this->get_prefix();

		$this->user_id = get_current_user_id();
	}

	public function convert_einvoice( $doc_id = 0 )
	{
		if( ! $doc_id ) return false;

		//Header
		$header = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id, 'doc_type'=>'none', ], [], true, ['usage'=>1, 'meta'=>[ 'ref_doc_type' ] ] );	

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$header['warehouse_id'] ], [], true, ['company'=>1]);
		if( empty( $wh ) ) return false;

		$metas = get_document_meta( $header['doc_id'] );
		$header = $this->combine_meta_data( $header, $metas );
		$header['currency'] = 'MYR';
		$header['comp_id'] = $header['warehouse_id'];

		if( in_array( $header['doc_type'], [ 'sale_credit_note', 'sale_debit_note' ] ) && $header['ref_doc_type'] == 'sale_order' )
		{
			$ref_header = apply_filters( 'wcwh_get_doc_header', [ 'parent'=>$header['parent'], 'doc_type'=>'e_invoice', ], [], true, ['usage'=>1 ] );
			if( $ref_header )
			{
				$ref_metas = get_document_meta( $ref_header['doc_id'] );
				$ref_header = $this->combine_meta_data( $ref_header, $ref_metas );

				$header['ref_doc'] = $ref_header['docno'];
				$header['ref_uuid'] = $ref_header['uuid'];

				foreach( $ref_header as $key => $val )
				{
					if( strpos( $key, 'diff' ) !== false ) 
					{
					   $header[ $key ] = $val;
					}
				}
			}	
		}
		
		//Details
		$exemp_flag = false; //tax exemption
		$header_total_tax = 0;

		$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'uom'=>1, 'usage'=>1] );
		if($details)
		{
			foreach ($details as $key => $row)
			{
				$detail_metas = get_document_meta( $row['doc_id'], '', $row['item_id'] );
				$row = $this->combine_meta_data( $row, $detail_metas );
				
				if($row['exemp_reason'])
				{
					$exemp_flag = true;
					$exemp_reason = $row['exemp_reason'];
				}

				$header_total_tax += $row['line_total_tax'];
				$header_total_tax += $row['line_exemp_tax'];
			}
		}
	
		//Supplier
		$supplier = apply_filters( 'wcwh_get_company', [ 'id'=>$wh['comp_id'] ], [], true, ['address'=>'default', 'meta'=>['email','msic'] ]);

		//Buyer
		$buyer = apply_filters( 'wcwh_get_client', [ 'code'=>$header['client_company_code'] ], [], true, ['address'=>'billing']);
		if($buyer['parent'] > 0) //if got parent, use parent's TIN number...
		{
			$buyer_parent = apply_filters( 'wcwh_get_client', [ 'id'=>$buyer['parent'] ], [], true, ['address'=>'billing']);
		}

		//If got TAX
		if($header['total_tax'] > 0)
		{
			$total_tax 		= round_to( $header['total_tax'], 2, 1);
			$total_net 		= round_to( ($header['total'] - $header['total_tax']), 2, 1);
			$total_discount = ($header['total_discount'] > 0 ) ? round_to( $header['total_discount'], 2, 1) : 0;
			$total_excl_tax = round_to( ($header['total'] - $header['total_tax']), 2, 1);
			$total_incl_tax = round_to( $header['total'], 2, 1);

			$total_payable 	= round_to( $header['total'], 2, 1);
		}
		else
		{
			$total_tax 		= $header_total_tax;
			$total_net 		= round_to( ($header['subtotal'] ), 2, 1);
			$total_discount = ($header['total_discount'] > 0 ) ? round_to( $header['total_discount'], 2, 1) : 0;
			$total_excl_tax = round_to( ($header['subtotal'] - $header['total_discount'] ), 2, 1);
			$total_incl_tax = round_to( ( $header['total'] ), 2, 1);

			$total_payable 	= round_to( $header['total'], 2, 1);
		}
		//End
 
		//Supplier's Info
		if( $supplier )
		{
			$supplier_msic 		= $supplier['msic'];

			$supplier_name 		= $supplier['name'];
			$supplier_tin 		= str_replace(' ', '', $supplier['tin']);
			$supplier_id_type 	= $supplier['id_type'];
			$supplier_id 		= $supplier['id_code'];
			$supplier_sst 		= $supplier['sst_no'];
			$supplier_contact 	= preg_replace("/[^0-9]/", "", $supplier['contact_no']);
			$supplier_mail 		= $supplier['email'];
			$supplier_address 	= $supplier['address_1'];
			$supplier_postcode 	= $supplier['postcode'];
			$supplier_city 		= $supplier['city'];

			$supplier_country	= $this->defineCountry[ $supplier['country'] ];
			$supplier_state		= !empty( $supplier['state'] )? $this->defineState[ $supplier['state'] ] : '17';
		}
		//End

		//Buyer's Info
		$conso = false;
		if( $buyer )
		{
			if( !empty( $header['diff_billing_address'] ) ) $buyer['address_1'] = $header['diff_billing_address'];
			if( !empty( $header['diff_billing_country'] ) ) $buyer['country'] = $header['diff_billing_country'];
			if( !empty( $header['diff_billing_state'] ) ) $buyer['state'] = $header['diff_billing_state'];
			if( !empty( $header['diff_billing_city'] ) ) $buyer['city'] = $header['diff_billing_city'];
			if( !empty( $header['diff_billing_postcode'] ) ) $buyer['postcode'] = $header['diff_billing_postcode'];

			$buyer_name 	= $buyer['name'];
			$buyer_tin 		= ($buyer_parent) ? str_replace(' ', '', $buyer_parent['tin']) : str_replace(' ', '', $buyer['tin']);
			$buyer_id_type 	= ($buyer_parent) ? $buyer_parent['id_type'] : $buyer['id_type'];
			$buyer_id 		= ($buyer_parent) ? $buyer_parent['id_code'] : $buyer['id_code'];
			$buyer_sst 		= ($buyer_parent) ? $buyer_parent['sst_no'] : $buyer['sst_no'];
			$buyer_contact 	= preg_replace("/[^0-9]/", "", $buyer['contact_no']);
			$buyer_address 	= !empty( $buyer['address_1'] )? $buyer['address_1'] : $supplier['address_1'];
			$buyer_postcode = !empty( $buyer['postcode'] )? $buyer['postcode'] : $supplier['postcode'];
			$buyer_city 	= !empty( $buyer['city'] )? $buyer['city'] : $supplier['city'];

			$buyer_country 	= $this->defineCountry[ $buyer['country'] ];
			$buyer_state 	= !empty( $buyer['state'] )? $this->defineState[ $buyer['state'] ] : '17';

			if( empty( trim( $buyer_tin ) ) )
			{
				$buyer_tin = 'EI00000000010';
				$buyer_id_type = 'BRN';
				$buyer_id = '000000000000';
				$buyer_sst = '';
				$conso = true;
			}
			if( empty( trim( $buyer_contact ) ) ) $buyer_contact = 'NA';
		}
		//End 

		//Start Convert (Header)
		$data_convert = [];

		//Get type code and currency
		$type_code = (!empty($this->defineTypeCode[$header['doc_type']])) ? $this->defineTypeCode[$header['doc_type']] : '';
		$currency = (!empty($this->defineCurrency[$header['currency']])) ? $this->defineCurrency[$header['currency']] : '';
		//$msic_act = 'Production of natural mineral water and other bottled water';

		$data_convert['header'] = [
            'comp_id' 			=> $supplier_tin,
            'docno' 			=> $header['docno'],
            'doc_date' 			=> !empty( $header['submit_date'] )? $header['submit_date'] : $header['doc_date'],
            'doc_type' 			=> $this->defineDocType[ $header['doc_type'] ],
            'eirno' 			=> $header['doc_id'],
			'type_code' 		=> $type_code, 
            'currency' 			=> $currency,
            'total_excl_tax' 	=> $total_excl_tax,
            'total_incl_tax' 	=> $total_incl_tax,
            'total_payable' 	=> $total_payable,
            'total_net' 		=> $total_net,
            'total_discount' 	=> $total_discount,
			'addon_dicount_amt' => $total_discount,
            'total_tax' 		=> $total_tax,
			'exchange_rate'		=> 0.00,
			'exchange_currency'	=> $currency,
			'supplier_msic'		=> ( !empty($supplier_msic) ) ? $supplier_msic : '47199',
			//'msic_activity'		=> $msic_act,
            'createBy' 			=> $header['created_by'],
            'createdAt' 		=> $header['created_at'],
            'lupdateBy' 		=> $header['lupdate_by'],
            'lupdateAt' 		=> $header['lupdate_at'],

			'supplier_name'		=> $supplier_name,
			'supplier_tin' 		=> $supplier_tin,
			'supplier_id_type'	=> $supplier_id_type,
			'supplier_id'	 	=> $supplier_id,
			'supplier_sst' 		=> $supplier_sst,
			'supplier_contact' 	=> $supplier_contact,
			'supplier_mail'		=> $supplier_mail,
			'supplier_address' 	=> $supplier_address,
			'supplier_postcode' => $supplier_postcode,
			'supplier_city' 	=> $supplier_city,
			'supplier_state' 	=> $supplier_state,
			'supplier_country' 	=> $supplier_country,

			'buyer_name'		=> $buyer_name,
			'buyer_tin' 		=> $buyer_tin,
			'buyer_id_type'	 	=> $buyer_id_type,
			'buyer_id'	 		=> $buyer_id,
			'buyer_sst' 		=> $buyer_sst,
			'buyer_contact' 	=> $buyer_contact,
			'buyer_address' 	=> $buyer_address,
			'buyer_postcode' 	=> $buyer_postcode,
			'buyer_city' 		=> $buyer_city,
			'buyer_state' 		=> $buyer_state,
			'buyer_country' 	=> $buyer_country,

			'integrate_type'    => $this->refs['einv_id'],
			'decimal_quantity' 	=> 2,
			'decimal_uprice' 	=> 5,
			'status' 			=> $header['status'],
		];

		if( ! empty( $header['ref_doc'] ) ) $data_convert['header']['ref_doc'] = $header['ref_doc'];
		if( ! empty( $header['ref_uuid'] ) ) $data_convert['header']['ref_uuid'] = $header['ref_uuid'];
		else unset( $data_convert['header']['ref_doc'] );

		//minimart addon
		if( ! empty( $header['sap_po'] ) ) $data_convert['header']['sap_po'] = $header['sap_po'];

		//If got tax exemption
		if($exemp_flag)
		{
			$data_convert['header']['tax_exemp_reason'] = $exemp_reason;
			$data_convert['header']['tax_exemp_amt'] = 0;
		}
		//End

		//Start Convert (Details)
		if($details)
		{
			foreach ($details as $key => $row)
			{
				$detail_metas = get_document_meta( $row['doc_id'], '', $row['item_id'] );
				$row = $this->combine_meta_data( $row, $detail_metas );

				if( !empty( $row['custom_item'] ) ) $row['prdt_name'] = $row['custom_item'];
				
				$unit_price = ( $row['bqty'] != 0 )? round_to( $row['line_subtotal'] / $row['bqty'], $data_convert['header']['decimal_uprice'], 1) : 0;
				$subtotal = round_to( ($row['line_subtotal'] - $row['line_subtotal_tax']), 2, 1);

				$data_convert['details'][] = 
				[
					'type' 			=> 'item',
					'bqty' 			=> ( $row['bqty'] )? $row['bqty'] : 0,
					'uom_id' 		=> ( $row['uom_code'] )? $this->defineUOM[ $row['uom_code'] ] : '',
					'ref_item_no'	=> $row['item_id'],
					'classification'=> ( $conso )? '004' : '022',
					'description'	=> ( $row['prdt_name'] )? $row['prdt_name'] : '-',
					'uprice' 		=> $unit_price,
					'subtotal'		=> ( $subtotal )? $subtotal : '0.00',
					'total_excl_tax'=> ( $subtotal )? $subtotal : '0.00',
					'tax_amount'	=> '0.00',

					'discount_rate' => ( $row['discount'] > 0 ) ? str_replace('%', '', $row['discount']) : '0.00',
					'discount_amt'	=> ( $row['line_discount'] > 0 ) ? $row['line_discount'] : '0.00',
				];
			
				//Tax Type
				$tax_type = '06';

				//Tax Amount
				$tax_amount = 0;
				if($row['line_exemp_tax'] > 0)
					$tax_amount = $row['line_exemp_tax'];
				else if( $row['line_total_tax'] > 0 )
					$tax_amount = $row['line_total_tax'];
				else
					$tax_amount = 0;

				$data_convert['details'][] = 
				[
					'type' => 'item_tax',
					'ref_item_no'		=> $row['item_id'],
					'tax_amount'		=> $tax_amount,
					'tax_type'			=> $tax_type,
					'tax_rate_type'		=> ( $tax_type ) ? 'percent' : '',
					'tax_rate_amt'		=> ( $row['tax_rate'] ) ? $row['tax_rate'] : '0.00',
					'tax_exemp_reason'	=> ( !empty($row['exemp_reason']) ) ? $row['exemp_reason'] : '',
					'tax_exemp_amt'		=> ( !empty($row['line_exemp_tax']) ) ? '0.00' : '0.00',
				];

				$taxes[$tax_type] += $tax_amount;
			}

			if($taxes)
			{
				foreach ($taxes as $key => $value)
				{
					$data_convert['details'][] =
					[
						'type'				=> 'doc_tax', 
						'ref_item_no'		=> '000',
						'tax_amount'		=> $value,
						'tax_type'			=> $key
					];
				}
			}
		}
		//pd($data_convert,1);
		if($data_convert)
			return $data_convert;
		else
			return false;
	}

	public function convert_self_billed_einvoice( $doc_id = 0 )
	{
		if( ! $doc_id ) return false;

		//Header
		$header = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id, 'doc_type'=>'none', ], [], true, ['usage'=>1, 'meta'=>[ 'ref_doc_type' ] ] );	

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$header['warehouse_id'] ], [], true, ['company'=>1]);
		if( empty( $wh ) ) return false;

		$metas = get_document_meta( $header['doc_id'] );
		$header = $this->combine_meta_data( $header, $metas );
		$header['currency'] = 'MYR';
		$header['comp_id'] = $header['warehouse_id'];

		if( in_array( $header['doc_type'], [ 'sb_credit_note', 'sb_debit_note' ] ) && $header['ref_doc_type'] == 'sb_invoice' )
		{
			$ref_header = apply_filters( 'wcwh_get_doc_header', [ 'parent'=>$header['parent'], 'doc_type'=>'sb_invoice', ], [], true, ['usage'=>1 ] );
			if( $ref_header )
			{
				$ref_metas = get_document_meta( $ref_header['doc_id'] );
				$ref_header = $this->combine_meta_data( $ref_header, $ref_metas );

				$header['ref_doc'] = $ref_header['docno'];
				$header['ref_uuid'] = $ref_header['uuid'];

				foreach( $ref_header as $key => $val )
				{
					if( strpos( $key, 'diff' ) !== false ) 
					{
					   $header[ $key ] = $val;
					}
				}
			}	
		}
		
		//Details
		$exemp_flag = false; //tax exemption
		$header_total_tax = 0;

		$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'uom'=>1, 'usage'=>1] );
		if($details)
		{
			foreach ($details as $key => $row)
			{
				$detail_metas = get_document_meta( $row['doc_id'], '', $row['item_id'] );
				$row = $this->combine_meta_data( $row, $detail_metas );
				
				if($row['exemp_reason'])
				{
					$exemp_flag = true;
					$exemp_reason = $row['exemp_reason'];
				}

				$header_total_tax += $row['line_total_tax'];
				$header_total_tax += $row['line_exemp_tax'];
			}
		}		

		//Supplier
		$supplier = apply_filters( 'wcwh_get_supplier', [ 'code'=>$header['supplier_company_code'] ], [], true, ['address'=>'billing']);

		//Buyer
		$buyer = apply_filters( 'wcwh_get_company', [ 'id'=>$wh['comp_id'] ], [], true, ['address'=>'default', 'meta'=>['email','msic'] ]);

		//If got TAX
		if($header['total_tax'] > 0)
		{
			$total_tax 		= round_to( $header['total_tax'], 2, 1);
			$total_net 		= round_to( ($header['total'] - $header['total_tax']), 2, 1);
			$total_discount = ($header['total_discount'] > 0 ) ? round_to( $header['total_discount'], 2, 1) : 0;
			$total_excl_tax = round_to( ($header['total'] - $header['total_tax']), 2, 1);
			$total_incl_tax = round_to( $header['total'], 2, 1);

			$total_payable 	= round_to( $header['total'], 2, 1);
		}
		else
		{
			$total_tax 		= $header_total_tax;
			$total_net 		= round_to( ($header['subtotal'] ), 2, 1);
			$total_discount = ($header['total_discount'] > 0 ) ? round_to( $header['total_discount'], 2, 1) : 0;
			$total_excl_tax = round_to( ($header['subtotal'] - $header['total_discount'] ), 2, 1);
			$total_incl_tax = round_to( ( $header['total'] ), 2, 1);

			$total_payable 	= round_to( $header['total'], 2, 1);
		}
		//End
 
		//Supplier's Info
		if( $supplier )
		{
			$supplier_msic 		= '00000';

			$supplier_name 		= $supplier['name'];
			$supplier_tin 		= str_replace(' ', '', $supplier['tin']);
			$supplier_id_type 	= 'BRN';
			$supplier_id 		= '000000000000';
			$supplier_sst 		= '';
			$supplier_contact 	= preg_replace("/[^0-9]/", "", $supplier['contact_no']);
			$supplier_mail 		= $supplier['email'];
			$supplier_address 	= !empty( $supplier['address_1'] )? $supplier['address_1'] : 'NA';
			$supplier_postcode 	= $supplier['postcode'];
			$supplier_city 		= !empty( $supplier['city'] )? $supplier['city'] : 'NA';

			$supplier_country	= !empty( $supplier['country'] )? $this->defineCountry[ $supplier['country'] ] : 'MYS';
			$supplier_state		= !empty( $supplier['state'] )? $this->defineState[ $supplier['state'] ] : '17';

			if( empty( trim( $supplier_tin ) ) )
			{
				$supplier_tin = 'EI00000000010';
			}
			if( empty( trim( $supplier_contact ) ) ) $supplier_contact = 'NA';
		}
		//End

		//Buyer's Info
		if( $buyer )
		{
			$buyer_name 	= $buyer['name'];
			$buyer_tin 		= str_replace(' ', '', $buyer['tin']);
			$buyer_id_type 	= $buyer['id_type'];
			$buyer_id 		= $buyer['id_code'];
			$buyer_sst 		= $buyer['sst_no'];
			$buyer_contact 	= preg_replace("/[^0-9]/", "", $buyer['contact_no']);
			$buyer_mail 	= $buyer['email'];
			$buyer_address 	= $buyer['address_1'];
			$buyer_postcode = $buyer['postcode'];
			$buyer_city 	= $buyer['city'];

			$buyer_country 	= $this->defineCountry[ $buyer['country'] ];
			$buyer_state 	= !empty( $buyer['state'] )? $this->defineState[ $buyer['state'] ] : '17';
		}
		//End 

		//Start Convert (Header)
		$data_convert = [];

		//Get type code and currency
		$type_code = (!empty($this->defineTypeCode[$header['doc_type']])) ? $this->defineTypeCode[$header['doc_type']] : '';
		$currency = (!empty($this->defineCurrency[$header['currency']])) ? $this->defineCurrency[$header['currency']] : 'MYR';
		//$msic_act = 'Production of natural mineral water and other bottled water';

		$data_convert['header'] = [
            'comp_id' 			=> $buyer_tin,
            'docno' 			=> $header['docno'],
            'doc_date' 			=> !empty( $header['submit_date'] )? $header['submit_date'] : $header['doc_date'],
            'doc_type' 			=> $this->defineDocType[ $header['doc_type'] ],
            'eirno' 			=> $header['doc_id'],
			'type_code' 		=> $type_code, 
            'currency' 			=> $currency,
            'total_excl_tax' 	=> $total_excl_tax,
            'total_incl_tax' 	=> $total_incl_tax,
            'total_payable' 	=> $total_payable,
            'total_net' 		=> $total_net,
            'total_discount' 	=> $total_discount,
            'total_tax' 		=> $total_tax,
			'exchange_rate'		=> 0.00,
			'exchange_currency'	=> $currency,
			'supplier_msic'		=> ( !empty($supplier_msic) ) ? $supplier_msic : '00000',
			//'msic_activity'		=> $msic_act,
            'createBy' 			=> $header['created_by'],
            'createdAt' 		=> $header['created_at'],
            'lupdateBy' 		=> $header['lupdate_by'],
            'lupdateAt' 		=> $header['lupdate_at'],

			'supplier_name'		=> $supplier_name,
			'supplier_tin' 		=> $supplier_tin,
			'supplier_id_type'	=> $supplier_id_type,
			'supplier_id'	 	=> $supplier_id,
			'supplier_sst' 		=> $supplier_sst,
			'supplier_contact' 	=> $supplier_contact,
			'supplier_mail'		=> $supplier_mail,
			'supplier_address' 	=> $supplier_address,
			'supplier_postcode' => $supplier_postcode,
			'supplier_city' 	=> $supplier_city,
			'supplier_state' 	=> $supplier_state,
			'supplier_country' 	=> $supplier_country,

			'buyer_name'		=> $buyer_name,
			'buyer_tin' 		=> $buyer_tin,
			'buyer_id_type'	 	=> $buyer_id_type,
			'buyer_id'	 		=> $buyer_id,
			'buyer_sst' 		=> $buyer_sst,
			'buyer_contact' 	=> $buyer_contact,
			'buyer_mail'		=> $buyer_mail,
			'buyer_address' 	=> $buyer_address,
			'buyer_postcode' 	=> $buyer_postcode,
			'buyer_city' 		=> $buyer_city,
			'buyer_state' 		=> $buyer_state,
			'buyer_country' 	=> $buyer_country,

			'integrate_type'    => $this->refs['einv_id'],
			'decimal_quantity' 	=> 2,
			'decimal_uprice' 	=> 5,
			'status' 			=> $header['status'],
		];

		if( ! empty( $header['ref_doc'] ) ) $data_convert['header']['ref_doc'] = $header['ref_doc'];
		if( ! empty( $header['ref_uuid'] ) ) $data_convert['header']['ref_uuid'] = $header['ref_uuid'];

		//If got tax exemption
		if($exemp_flag)
		{
			$data_convert['header']['tax_exemp_reason'] = $exemp_reason;
			$data_convert['header']['tax_exemp_amt'] = 0;
		}
		//End

		//Start Convert (Details)
		if($details)
		{
			foreach ($details as $key => $row)
			{
				$detail_metas = get_document_meta( $row['doc_id'], '', $row['item_id'] );
				$row = $this->combine_meta_data( $row, $detail_metas );
				
				$unit_price = ( $row['bqty'] != 0 )? round_to( $row['line_subtotal'] / $row['bqty'], $data_convert['header']['decimal_uprice'], 1) : 0;
				$subtotal = round_to( ($row['line_subtotal'] - $row['line_subtotal_tax']), 2, 1);

				$data_convert['details'][] = 
				[
					'type' 			=> 'item',
					'bqty' 			=> ( $row['bqty'] )? $row['bqty'] : 0,
					'uom_id' 		=> ( $row['uom_code'] )? $this->defineUOM[ $row['uom_code'] ] : '',
					'ref_item_no'	=> $row['item_id'],
					'classification'=> '036',//Others
					'description'	=> trim( ( !empty($row['item_doc'])? $row['item_doc'] : '' )."; ".( !empty($row['prdt_name'])? $row['prdt_name'] : '' ) ),
					'uprice' 		=> $unit_price,
					'subtotal'		=> ( $subtotal )? $subtotal : '0.00',
					'total_excl_tax'=> ( $subtotal )? $subtotal : '0.00',
					'tax_amount'	=> '0.00',

					'discount_rate' => ( $row['discount'] > 0 ) ? str_replace('%', '', $row['discount']) : '0.00',
					'discount_amt'	=> ( $row['line_discount'] > 0 ) ? $row['line_discount'] : '0.00',
				];
			
				//Tax Type
				$tax_type = '06';

				//Tax Amount
				$tax_amount = 0;
				if($row['line_exemp_tax'] > 0)
					$tax_amount = $row['line_exemp_tax'];
				else if( $row['line_total_tax'] > 0 )
					$tax_amount = $row['line_total_tax'];
				else
					$tax_amount = 0;

				$data_convert['details'][] = 
				[
					'type' => 'item_tax',
					'ref_item_no'		=> $row['item_id'],
					'tax_amount'		=> $tax_amount,
					'tax_type'			=> $tax_type,
					'tax_rate_type'		=> ( $tax_type ) ? 'percent' : '',
					'tax_rate_amt'		=> ( $row['tax_rate'] ) ? $row['tax_rate'] : '0.00',
					'tax_exemp_reason'	=> ( !empty($row['exemp_reason']) ) ? $row['exemp_reason'] : '',
					'tax_exemp_amt'		=> ( !empty($row['line_exemp_tax']) ) ? '0.00' : '0.00',
				];

				$taxes[$tax_type] += $tax_amount;
			}

			if($taxes)
			{
				foreach ($taxes as $key => $value)
				{
					$data_convert['details'][] =
					[
						'type'				=> 'doc_tax', 
						'ref_item_no'		=> '000',
						'tax_amount'		=> $value,
						'tax_type'			=> $key
					];
				}
			}
		}
		//pd($data_convert,1);
		if($data_convert)
			return $data_convert;
		else
			return false;
	}
}
?>