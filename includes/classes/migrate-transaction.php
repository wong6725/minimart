<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Inventory Transaction
 *
 * @class    WC_InventoryTrans
 * 
 * Scenario Not Included: 
 * - Stock IN: change Unit price after Posted. ( Need Mapping on Stock Out Unit Costs if Exists)
 *
 * V1.0.9
 * - Add table {wp}inventory_transaction_meta
 */
class WC_InventoryTrans extends WCWH_CRUD_Controller
{
	private $_succ = false;
	private $_tbl_inventory = 'inventory_transaction';
	private $_tbl_inventory_item = 'inventory_transaction_items';
	private $_tbl_inventory_ref = 'inventory_transaction_ref';
	private $_tbl_inventory_meta = 'inventory_transaction_meta'; //V1.0.9
	private $_tbl_postmeta = 'postmeta'; //V1.0.9
	private $_tbl_stockout_method = 'inventory_stockout_method'; //V1.0.9
	private $_tbl_item = '';
	private $_tbl_item_meta = '';
	private $_tbl_item_tree = '';
	private $_tbl_item_converse = '';
	private $_tbl_itemize = '';
	private $_tbl_itemize_meta = '';
	private $_tbl_conversion_item = '';
	private $_tbl_weighted_item = '';

	private $header_defaults = array();
	private $item_defaults = array();
	private $ref_defaults = array();
	private $conversion_defaults = array();
	private $itemize_defaults = array();
	private $weighted_defaults = array();

	private $temp_data = array();

	protected $enforce_stockout = 0;

	private $className = 'WC_InventoryTrans';
	public $Notices;

	//V1.0.3 add status In transit
	public $plugin_ref = array(
		"doc_plus_sign" => array( 
			"delivery_order" => "-", 
			"do_revise" => "+", 
			"shop_order" => "-", 
			"good_receive" => "+", 
			"block_stock" => "+", 
			"block_action" => "-",
			"reprocess" => "+",
			"transfer_item" => "+",
			"good_issue" => "-",
		) //For Default By Doc Type if Not provided
	);
	/**
	 * Constructor for the Period
	 */
	public function __construct() 
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		global $wcwh;
		$prefix = $this->get_prefix();
		$this->_tbl_inventory = $prefix."tx_transaction";
		$this->_tbl_inventory_item = $prefix."tx_transaction_items";
		$this->_tbl_inventory_ref = $prefix."tx_transaction_out_ref";
		$this->_tbl_inventory_meta = $prefix."tx_transaction_meta";
		$this->_tbl_conversion_item = $prefix."tx_transaction_conversion";
		$this->_tbl_weighted_item = $prefix."tx_transaction_weighted";

		$this->_tbl_postmeta = $prefix."items";
		$this->_tbl_stockout_method = $prefix."stockout_method";

		$this->_tbl_item = $prefix."items";
		$this->_tbl_item_meta = $prefix."itemsmeta";
		$this->_tbl_item_tree = $prefix."items_tree";
		$this->_tbl_item_converse = $prefix."item_converse";

		$this->_tbl_itemize = $prefix."itemize";
		$this->_tbl_itemize_meta = $prefix."itemizemeta";

		$this->header_defaults = array(
			'docno'  			=> '',  //Document No
			'doc_id' 			=> '0', //Document Post ID
			'doc_type' 			=> '',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
			'doc_post_date' 	=> '',  //Inventory In/Out Date
  			'plus_sign'			=> '',  //Debit/Credit
			'lupdate_by' 		=> '',
			'lupdate_at' 		=> ''
		);
		$this->item_defaults = array(
			'hid'  				=> '',  //Header ID
			'item_id'			=> '',  //Document Item ID
			'product_id' 		=> '0', //Product ID
			'warehouse_id' 		=> '',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
			'strg_id'			=> '0',
			'batch'				=> '',
			'bqty' 				=> '0', //Qty
			'bunit'				=> '0',
			'unit_cost'			=> '0', //Cost
			'total_cost'		=> '0', //Cost
			'unit_price'		=> '0', //Price
			'total_price'		=> '0', //Price
			'weighted_price'	=> '0',	//weighted average cost
			'weighted_total'	=> '0',	//weighted average cost
			'lupdate_by' 		=> '0',
			'lupdate_at' 		=> ''
		);
		$this->ref_defaults = array(
			'hid'  				=> '',  //Header Link ID
			'did' 				=> '',  //Document Post ID
			'bqty' 				=> '0', //Qty
			'bunit'				=> '0',
			'unit_cost'			=> '0', //Cost
			'ref_hid' 			=> '',  //Reference Doc
			'ref_did' 			=> ''  //Reference Item
		);
		$this->conversion_defaults = array(
			'hid'				=> '',
			'item_id'			=> '',
			'from_prdt_id'		=> '0',
			'to_prdt_id'		=> '0',
			'from_qty'			=> '0',
			'to_qty'			=> '0',
			'uprice'			=> '0',
			'total_price'		=> '0',
			'status'			=> '1',
			'lupdate_by' 		=> '0',
			'lupdate_at' 		=> ''
		);
		$this->itemize_defaults = array(
			'product_id'		=> '',
			'_sku'				=> '',
			'in_did'			=> '0',
			'out_did'			=> '0',
			'code'				=> '',
			'serial'			=> '',
			'desc'				=> '',
			'bunit'				=> '0',
			'unit_cost'			=> '0',
			'unit_price'		=> '0',
			'expiry'			=> '',
		);
		$this->weighted_defaults = array(
			'did'				=> '',
			'item_id'			=> '',
			'product_id' 		=> '0', //Product ID
			'warehouse_id' 		=> '',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
			'strg_id'			=> '0',
			'qty'				=> '0',
			'unit'				=> '0',
			'price'				=> '0',
			'amount'			=> '0',
			'type'				=> '1',
			'plus_sign'			=> '',
			'bal_qty'			=> '0',
			'bal_unit'			=> '0',
			'bal_price'			=> '0',
			'bal_amount'		=> '0',
			'status'			=> '1',
			'lupdate_by' 		=> '0',
			'lupdate_at' 		=> ''
		);
		$this->user_id = get_current_user_id();
		$this->init_hooks();
	}
	/**
	 * init hooks
	 */
	private function init_hooks() {
		remove_all_filters( 'warehouse_inventory_transaction_filter', 1 );
		//$options = get_option( 'warehouse_option' );
		add_filter( 'warehouse_inventory_transaction_filter', array( $this, 'inventory_transaction_data_handle' ) ,1 , 4 );

		add_filter( 'warehouse_get_inventory_transaction_item_latest_price', array( $this, 'get_inventory_transaction_item_latest_price' ), 10, 4 );
		add_filter( 'warehouse_get_inventory_transaction_item_weighted_price', array( $this, 'get_inventory_transaction_item_weighted_price' ), 10, 4 );

		add_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10, 2 );

		add_filter( 'wcwh_inventory_get_notices', array( $this, 'get_operation_notice' ), 10, 1 );

		add_filter( 'wcwh_get_exist_inventory_transaction', array( $this, 'get_exist_inventory_transaction' ), 10, 2 );
	}
	/**
	 *	pop notice back 
	 */
	public function get_operation_notice( $args = true )
	{
		return ( $this->Notices )? $this->Notices->get_operation_notice() : [];
	}
	/**
	 * for generate batch no 
	 */
	public function docno_replacer( $sdocno, $doc_type = '' )
	{
		if( $doc_type && $doc_type == 'batch' )
		{	
			$datas = $this->temp_data;
			$ref = array();
			
			if( $datas['header'] )
			{
				$docno = $datas['header']['docno'];
			}
			if( $datas['row'] )
			{
				$item_number = $datas['row']['item_number'];
			}
			
			$find = [ 
				'batch' => '{batch}',
			];

			$replace = [ 
				'batch' => $docno.str_pad( $item_number, 3, "0", STR_PAD_LEFT ),
			];

			$sdocno = str_replace( $find, $replace, $sdocno );
		}

		return $sdocno;
	}
	/**
	 *	Add/Update Inventory Transaction
	 * 	V1.0.9 - add join document meta to get '_stock_out_type', 'prod_expiry' value
	 */
	public function inventory_transaction_data_handle ( $action , $doc_type, $doc_id , $arr_item = array() )
	{
		if ( ! $doc_type || ! $doc_id ) 
			return false;

		global $wpdb;
		$succ = true;
		$prefix = $this->get_prefix();
	 	$_tbl_document = $prefix.'document';
		$_tbl_document_items = $prefix.'document_items';
	 	$_tbl_document_meta = $prefix.'document_meta';
	 	$_tbl_postmeta = $this->_tbl_postmeta; //V1.0.9

	 	$_tbl_doc_itemize = $prefix."document_itemize";
	 	$direct_tbl = "";

	 	$tbl_t_item = $prefix."transaction_items";

	 	//$def_qty_field = ", IF( ti.product_id IS NULL, b.bqty, IF(ti.plus_sign = '+' AND ti.deduct_qty > ti.bqty, ti.deduct_qty, ti.bqty ) )AS bqty, b.bunit ";
	 	$def_qty_field = ", IF( ti.product_id IS NULL, b.bqty, ti.bqty ) AS bqty, b.bunit ";
	 	$cd = "";
		switch( $doc_type )
		{
			case 'reprocess':
			case 'good_receive':
			case 'block_stock':
			case 'transfer_item':
			case 'do_revise':
				$plus_sign = "+";
				$direct_fld = ", 0 as ref_doc_id, 0 as ref_item_id , '".$plus_sign."' as item_plus ";
				break;
			case 'good_issue':
			case 'delivery_order':
			case 'block_action':
				$plus_sign = "-";
				$direct_fld = ", 0 as ref_doc_id, 0 as ref_item_id , '".$plus_sign."' as item_plus ";
				break;
			case 'good_return':
				$plus_sign = "-";
				$direct_fld = ", 0 as ref_doc_id, 0 as ref_item_id , '".$plus_sign."' as item_plus ";
				$this->enforce_stockout = 3;
				break;
			case 'stock_adjust':
			case 'pos_transactions':
				$plus_sign = "*";
				$direct_fld = ", 0 as ref_doc_id, 0 as ref_item_id , e.meta_value as item_plus ";
				break;
			case 'stocktake':
				$plus_sign = "*";
				$direct_fld = ", b.ref_doc_id, b.ref_item_id , e.meta_value as item_plus, b.bqty AS count_qty, b.bunit AS count_unit ";
				//$direct_fld.= ", x.meta_value AS stock_bal_qty, w.meta_value AS stock_bal_unit ";

				$def_qty_field = ", IF( z.meta_value, z.meta_value, 0 ) AS bqty, IF( y.meta_value, y.meta_value, 0 ) AS bunit ";
				$direct_tbl.= "LEFT JOIN {$_tbl_document_meta} z ON z.doc_id = a.doc_id AND z.item_id = b.item_id AND z.meta_key = 'adjust_qty' ";
				$direct_tbl.= "LEFT JOIN {$_tbl_document_meta} y ON y.doc_id = a.doc_id AND y.item_id = b.item_id AND y.meta_key = 'adjust_unit' ";
				//$direct_tbl = "LEFT JOIN {$_tbl_document_meta} x ON x.doc_id = a.doc_id AND x.item_id = b.item_id AND x.meta_key = 'stock_bal_qty' ";
				//$direct_tbl.= "LEFT JOIN {$_tbl_document_meta} w ON w.doc_id = a.doc_id AND w.item_id = b.item_id AND w.meta_key = 'stock_bal_unit' ";

				$cd = "AND z.meta_value > 0 ";
				break;
			case 'purchase_debit_note':		//-
				$plus_sign = "-";
				$direct_fld = ", 0 as ref_doc_id, 0 as ref_item_id , '".$plus_sign."' as item_plus, '1' AS need_converse ";
				$def_qty_field = ", 0 AS bqty, 0 AS bunit ";
				break;
			case 'purchase_credit_note':	//+
				$plus_sign = "+";
				$direct_fld = ", 0 as ref_doc_id, 0 as ref_item_id , '".$plus_sign."' as item_plus, '1' AS need_converse ";
				$def_qty_field = ", 0 AS bqty, 0 AS bunit ";
				break;
			default :
				$succ = false;
			break;
		}

		$field = "a.doc_id, a.docno, a.doc_type, a.post_date as doc_post_date, f.meta_value as prod_expiry, '".$plus_sign."' as plus_sign ";
		//$field.= ", b.item_id, b.product_id, a.warehouse_id, b.strg_id {$def_qty_field} ";
		$field.= ", b.item_id, IFNULL(ti.product_id,b.product_id) AS product_id, a.warehouse_id, b.strg_id {$def_qty_field} ";
		$field.= ", IFNULL(c.meta_value,0) as unit_price, IFNULL(d.meta_value,0) as total_price ";
		$field.= ", prod._stock_out_type AS stock_out_type {$direct_fld}, g.meta_value as item_number
			, i.meta_value AS imp_amt ";

		$table = "{$_tbl_document} a ";
		$table.= "LEFT JOIN {$_tbl_document_items} b ON b.doc_id = a.doc_id AND b.status != 0 ";
		$table.= "LEFT JOIN {$_tbl_document_meta} c ON c.doc_id = a.doc_id AND c.item_id = b.item_id AND c.meta_key = 'uprice' ";
		$table.= "LEFT JOIN {$_tbl_document_meta} d ON d.doc_id = a.doc_id AND d.item_id = b.item_id AND d.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$_tbl_document_meta} e ON e.doc_id = a.doc_id AND e.item_id = b.item_id AND e.meta_key = 'plus_sign' ";
		$table.= "LEFT JOIN {$_tbl_postmeta} prod ON prod.id = b.product_id ";
		$table.= "LEFT JOIN {$_tbl_document_meta} f ON f.doc_id = a.doc_id AND f.item_id = b.item_id AND f.meta_key = 'prod_expiry'";
		$table.= "LEFT JOIN {$_tbl_document_meta} g ON g.doc_id = a.doc_id AND g.item_id = b.item_id AND g.meta_key = '_item_number'";
		$table.= "LEFT JOIN {$_tbl_document_meta} h ON h.doc_id = a.doc_id AND h.item_id = b.item_id AND h.meta_key = 'stock_item_id'";
		$table.= "LEFT JOIN {$_tbl_document_meta} i ON i.doc_id = a.doc_id AND i.item_id = b.item_id AND i.meta_key = 'transact_imp_amt'";

		$table.= "LEFT JOIN {$tbl_t_item} ti ON ti.item_id = b.item_id AND ti.status > 0 AND ti.strg_id = b.strg_id ";
		$table.= $direct_tbl;

		$cond = $wpdb->prepare( "AND a.status != 0 AND a.doc_type = %s AND a.doc_id = %d ", $doc_type , $doc_id );
		
		if( count($arr_item) > 0 )
		{
			$cond.= " AND b.item_id IN ( " . implode( ',', $arr_item ) . ")";
		}

		$get_items_sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$cd} ";
		$data = $wpdb->get_results( $get_items_sql , ARRAY_A );
		
		//Separate Data to Header/ Detail
		$header_column = array ( 'doc_id', 'docno', 'doc_type', 'doc_post_date' , 'plus_sign', 'need_converse' );
		$detail_column = array ( 'item_id', 'product_id', 'warehouse_id', 'strg_id', 'bqty', 'bunit' , 'unit_price' , 'total_price', 'ref_doc_id', 'ref_item_id', 'item_plus','prod_expiry','stock_out_type','item_number','stock_item_id', 'imp_amt');
		$result_data = $this->seperate_import_data( $data , $header_column , [ 'doc_id' ], $detail_column );

		$header = $result_data[0]['header'];
		$details = $result_data[0]['detail'];
		/*$itemize = array();
		foreach( $details as $i =>  $detail_item )
		{
			$fld = "id AS line_id, item_id, product_id, _sku, code, serial, bunit, unit_cost, unit_price, expiry, metas ";
			$cond = $wpdb->prepare( "AND item_id = %d AND product_id = %d ", $detail_item['item_id'], $detail_item['product_id'] );
			$itemize_sql = "SELECT {$fld} FROM {$_tbl_doc_itemize} WHERE 1 {$cond}";
			$items = $wpdb->get_results( $itemize_sql , ARRAY_A );
			if( $items )
			{
				$details[ $i ]['itemize'] = $items;
			}
		}*/
		
		//Apply Action:
		$succ = $this->inventory_transaction_handle( $action , $header, $details );
		return $succ;
	}

	/**
	 *	Add/Update Inventory Transaction
	 */
	public function inventory_transaction_handle( $action , $header = array() , $details  = array() )
	{
		if( $this->Notices ) $this->Notices->reset_operation_notice();
		$succ = true;
		
		$arr_active_items = array();
		$arr_active_item_id = array();
		$arr_active_docs = array();
		$detail_items = array();
		//wpdb_start_transaction();
		$action = strtolower( $action );
		switch ( $action ){
			case "save":
			case "update":
				$header_item = wp_parse_args( $header, $this->header_defaults );
				$header_item['status'] = 1;
				$header_item['lupdate_by'] = $this->user_id;
				$header_item['lupdate_at'] = current_time( 'mysql' );
				$header_item['doc_post_date'] = get_datime_fragment( $header_item['doc_post_date'], 'Y-m-d');
				$header_item['plus_sign'] = ( ! empty( $header_item['plus_sign'] ) ) ? $header_item['plus_sign'] : $plugin_ref['doc_plus_sign'][$header_item['doc_type']];
				$save_flag = false;
				//UPDATE HEADER
				$exist = $this->get_exist_inventory_transaction( $header_item['doc_id'] , $header_item['doc_type'] );
				if( ! $exist )
				{
					//Add New Record
					$doc_id = $this->add_inventory_transaction( $header_item );
					if( ! $doc_id )
						$succ = false;

					$header_item['hid'] = $doc_id;
					$save_flag = true; //New Added Document
				}
				else 
				{
					//Update Record
					if( ! $this->update_inventory_transaction( array( 'hid' => $exist['hid'] ) , $header_item ) )
						$succ = false;
					$header_item['hid'] = $exist['hid'];
					$arr_active_docs[] = $exist['hid'];

					//Offset Qty, Amount on STOCK
					if( $succ )
						$succ = apply_filters( 'document_post_warehouse_stocks_data_handle' , "delete" , $header_item['doc_type'], array ( $header_item['doc_id'] ) );
				}
				//UPDATE DETAIL ITEM
				if( $succ ){
					foreach ( $details as $detail_item )
					{
						$exists_item = null; 

						if( $header_item['need_converse'] )
							$detail_item = $this->transaction_conversion( $detail_item, $header_item['hid'] );
						if( $detail_item === false )
							$succ = false;
						
						$itemize = array();
						if( $detail_item['itemize'] && count( $detail_item['itemize'] ) > 0 )
						{
							$itemize = $detail_item['itemize'];
							unset( $detail_item['itemize'] );
						}

						$ditem = wp_parse_args( $detail_item, $this->item_defaults );
						$ditem['hid'] = $header_item['hid'];
						$ditem['plus_sign'] = isset($ditem['item_plus']) && ! empty($ditem['item_plus']) ? $ditem['item_plus'] : $header_item['plus_sign'];
						$ditem['status'] = 1;
						$ditem['lupdate_by'] = $this->user_id;
						$ditem['lupdate_at'] = current_time( 'mysql' );
						$ditem['stock_out_type'] = $detail_item['stock_out_type']; //V1.0.9
						$ditem['prod_expiry'] = $detail_item['prod_expiry']; //V1.0.9
						
						$ditem['weighted_price'] = 0;
						$ditem['weighted_total'] = 0;

						if( $ditem['total_price'] > 0 && $ditem['bqty'] > 0 )
							$ditem['unit_price'] = $ditem['total_price'] / $ditem['bqty'];

						if( empty( $ditem['total_price'] ) && $ditem['unit_price'] > 0 )
							$ditem['total_price'] = $ditem['bqty'] * $ditem['unit_price'];

						if( $ditem['plus_sign'] == "-" && $ditem['total_price'] > 0 )
						{
							$ditem['specify_cost'] = $ditem['total_price'];
							if( $ditem['bqty'] <= 0 ) $ditem['total_cost'] = $ditem['total_price'];

							//$ditem['total_price'] = 0;
						}
						/*else if( $ditem['plus_sign'] == "-" && in_array( $header_item['doc_type'], [ 'stock_adjust', 'stocktake' ] ) )
						{
							if( $ditem['total_price'] > 0 )
							{
								$ditem['weighted_total'] = $ditem['total_price'];
								$ditem['weighted_price'] = $ditem['weighted_total'] / $ditem['bqty'];
							}
							
							$ditem['unit_price'] = 0;
							$ditem['total_price'] = 0;
						}*/
						
						if( $save_flag )
						{
							if( $ditem['plus_sign'] == "+" ) 
							{
								$this->temp_data = [ 'header'=>$header_item, 'row'=>$detail_item ];
								$ditem['batch'] = apply_filters( 'warehouse_generate_docno', $ditem['batch'], 'batch' );
								$this->temp_data = array();
							}
							$detail_id = $this->add_inventory_transaction_item( $ditem );
							if( ! $detail_id )
								$succ = false;
							$ditem['did'] = $detail_id;
						}
						else 
						{
							$exists_item = $this->get_exist_inventory_transaction_item( $ditem['hid'], $ditem['item_id'], $ditem['product_id'], $ditem['warehouse_id'], $ditem['strg_id'] );
							if( ! $exists_item )
							{
								if( $ditem['plus_sign'] == "+" ) 
								{
									$this->temp_data = [ 'header'=>$header_item, 'row'=>$detail_item ];
									$ditem['batch'] = apply_filters( 'warehouse_generate_docno', $ditem['batch'], 'batch' );
									$this->temp_data = array();
								}
								$detail_id = $this->add_inventory_transaction_item( $ditem );
								if( ! $detail_id )
									$succ = false;
								$ditem['did'] = $detail_id;
								$save_item_flag = true; //New Added Item
							}
							else 
							{
								$upd_item = array_map_key( $ditem, $this->item_defaults ); //V1.0.4
								$upd_item['flag'] = $exists_item['deduct_qty'] > 0 && $exists_item['deduct_qty'] == $upd_item['bqty'] ? 1 : 0; //V1.0.9
								$this->temp_data = [ 'header'=>$header_item, 'row'=>$detail_item ];
								$upd_item['batch'] = !empty( $exists_item['batch'] )? $exists_item['batch'] : apply_filters( 'warehouse_generate_docno', $ditem['batch'], 'batch' );
								$this->temp_data = array();
								if ( ! $this->update_inventory_transaction_item( array( 'did' => $exists_item['did'] ) , $upd_item ) )//V1.0.4
								{
									$succ = false;
								}
								$ditem['did'] = $exists_item['did'];
								$ditem['total_cost'] =  $exists_item['total_cost'];
								$save_item_flag = false;
							}	
						}

						//Itemize
						if( count( $itemize ) )
						{
							if( ! $this->itemize_handler( $itemize, $ditem ) )
							{
								$succ = false;
							}
						}

						//Add IV Item Meta V1.0.9
						$detail_keys = array( 'stock_out_type', 'prod_expiry' );
						foreach( $detail_item as $key => $value ){
							$value = isset( $value ) ? sanitize_text_field( $value ) : '';
							if( in_array( $key, $detail_keys ) ){
								if( ! $this->add_inventory_meta_value( $key , $value , $ditem['hid'], $ditem['did'] ) )
								{
									$succ = false;
									break;
								}
							}
						}
						//END IV Item Meta V1.0.9

						//UPDATE REFERENCE DATA
						if( $succ ){
							$new_flag = $save_flag || $save_item_flag ? true : false;
							$succ = $this->update_fifo_document_handle(  $header_item['doc_post_date'] , $ditem , $exists_item , $new_flag );
						}
						if( $succ === false ) 
							break;

						//UPDATE Weighted Transaction data
						if( $succ ){
							$ditem['imp_amt'] = $detail_item['imp_amt'];
							$succ = $this->weighted_item_handle( $ditem , $exists_item );
						}
						if( $succ === false ) 
							break;

						$arr_active_items[] = $ditem['did'];
						$arr_active_item_id[] = $ditem['item_id'];
						$detail_items[] = $ditem;
					}
					//Remove deleted item
					if( $succ && ! $save_flag )
					{
						$exists_delete_item = $this->get_exist_inventory_transaction_item_deletion( $arr_active_docs , $arr_active_items ); //Item to be deleted
						if ( $exists_delete_item )
						{
							//INActive Document - V1.0.1
							if( $succ )
							{
								$delete_cond = "";
								if( count($arr_active_docs) > 0 ) 
								{
									$delete_cond .=" AND hid IN ( " . implode( ',', $arr_active_docs ) . ")";
								}
								if( count($arr_active_items) > 0 ) 
								{
									$delete_did_cond = $delete_cond;
									$delete_did_cond.=" AND did NOT IN ( " . implode( ',', $arr_active_items ) . ")";
								}
								$succ = $this->deactive_inventory_transaction( $delete_did_cond , 'detail' );
								if( count( $arr_active_item_id ) > 0 )
								{
									$delete_item_idcond = $delete_cond;
									$delete_item_idcond .=" AND item_id NOT IN ( " . implode( ',', $arr_active_item_id ) . ")";
								}
								if( $header_item['need_converse'] )
								$succ = $this->deactive_inventory_transaction_conversion( $delete_item_idcond );
							}
							//OFFSET uqty - V1.0.1
							$succ = $this->deleted_fifo_document_item_handles( $exists_delete_item );

							//Deactive Reference Item
							if( $succ )
							{
								$succ = $this->deactive_inventory_transaction( $delete_cond , 'ref' );
							}

							if( $succ )
							{
								$succ = $this->weighted_offset_handle( $exists_delete_item );
							}
						}
					}
				}
				//UPdate Qty, Amount on MOMA STOCK
				if( $succ )
					$succ = apply_filters( 'document_post_warehouse_stocks_data_handle' , $action , $header_item['doc_type'], array ( $header_item['doc_id'] ) );
			break;
			case "delete":
				if( ! $header['doc_id'] || ! $header['doc_type'] )
				{
					$succ = false;
				}
				if( $succ )
				{
					$exists = $this->get_exist_inventory_transaction( $header['doc_id'], $header['doc_type'] );
					if ( ! $exists )
						$succ = false;	
				}
				//Offset Qty, Amount on MOMA STOCK
				if( $succ )
					$succ = apply_filters( 'document_post_warehouse_stocks_data_handle', $action, $exists['doc_type'], array( $exists['doc_id'] ) );

				if( $succ )
				{
					$delete_cond .=" AND hid = ".$exists['hid'];
					$succ = $this->deactive_inventory_transaction(  $delete_cond , 'header' );

					$exists_delete_item = $this->get_exist_inventory_transaction_item_deletion( array ( $exists['hid'] ) ); //Item to be deleted
					if ( $exists_delete_item )
					{
						//results_table ( $exists_delete_item ); 
						//INActive Document - V1.0.1
						if( $succ )
						{
							$succ = $this->deactive_inventory_transaction( $delete_cond , 'detail' );
							if( $header_item['need_converse'] )
								$succ = $this->deactive_inventory_transaction_conversion( $delete_cond );
						}
						//OFFSET uqty - V1.0.1
						$succ = $this->deleted_fifo_document_item_handles( $exists_delete_item );

						//Deactive Reference Item
						if( $succ )
						{
							$succ = $this->deactive_inventory_transaction( $delete_cond , 'ref' );
						}

						if( $succ )
						{
							$succ = $this->weighted_offset_handle( $exists_delete_item );
						}
					}
				}
			break;
			case "post_cndn":
				$header_item = wp_parse_args( $header, $this->header_defaults );

				//UPDATE DETAIL ITEM
				if( $succ ){
					foreach ( $details as $detail_item )
					{
						$detail_item = $this->transaction_conversion( $detail_item, $header_item['hid'] );
						if( $detail_item === false )
							$succ = false;

						$ditem = wp_parse_args( $detail_item, $this->item_defaults );
						$ditem['hid'] = $header_item['hid'];
						$ditem['plus_sign'] = isset($ditem['item_plus']) && ! empty($ditem['item_plus']) ? $ditem['item_plus'] : $header_item['plus_sign'];
						$ditem['status'] = 1;
						$ditem['lupdate_by'] = $this->user_id;
						$ditem['lupdate_at'] = current_time( 'mysql' );
						$ditem['stock_out_type'] = $detail_item['stock_out_type']; //V1.0.9
						$ditem['prod_expiry'] = $detail_item['prod_expiry']; //V1.0.9
						
						$ditem['weighted_price'] = 0;
						$ditem['weighted_total'] = 0;

						if( $ditem['total_price'] > 0 && $ditem['bqty'] > 0 )
							$ditem['unit_price'] = $ditem['total_price'] / $ditem['bqty'];

						if( empty( $ditem['total_price'] ) && $ditem['unit_price'] > 0 )
							$ditem['total_price'] = $ditem['bqty'] * $ditem['unit_price'];

						if( $ditem['plus_sign'] == "-" && $ditem['total_price'] > 0 )
						{
							$ditem['specify_cost'] = $ditem['total_price'];
							if( $ditem['bqty'] <= 0 ) $ditem['total_cost'] = $ditem['total_price'];

							//$ditem['total_price'] = 0;
						}
						//UPDATE Weighted Transaction data
						if( $succ ){
							$ditem['imp_amt'] = $detail_item['imp_amt'];
							$succ = $this->weighted_item_handle( $ditem );
						}
						if( $succ === false ) 
							break;

						$detail_items[] = $ditem;
					}
				}
				//UPdate Qty, Amount on MOMA STOCK
				if( $succ )
					$succ = apply_filters( 'document_post_warehouse_stocks_data_handle' , $action , $header_item['doc_type'], array ( $header_item['doc_id'] ) );
			break;
			case "unpost_cndn":
				$header_item = wp_parse_args( $header, $this->header_defaults );

				//UPDATE DETAIL ITEM
				if( $succ && $details )
				{
					foreach ( $details as $detail_item )
					{
						$exists_item = null; 

						$detail_item = $this->transaction_conversion( $detail_item, $header_item['hid'] );
						if( $detail_item === false )
							$succ = false;

						$ditem = wp_parse_args( $detail_item, $this->item_defaults );
						$ditem['hid'] = $header_item['hid'];
						$ditem['plus_sign'] = isset($ditem['item_plus']) && ! empty($ditem['item_plus']) ? $ditem['item_plus'] : $header_item['plus_sign'];
						$ditem['status'] = 1;
						$ditem['lupdate_by'] = $this->user_id;
						$ditem['lupdate_at'] = current_time( 'mysql' );
						$ditem['stock_out_type'] = $detail_item['stock_out_type']; //V1.0.9
						$ditem['prod_expiry'] = $detail_item['prod_expiry']; //V1.0.9
						
						$ditem['weighted_price'] = 0;
						$ditem['weighted_total'] = 0;

						if( $ditem['total_price'] > 0 && $ditem['bqty'] > 0 )
							$ditem['unit_price'] = $ditem['total_price'] / $ditem['bqty'];

						if( empty( $ditem['total_price'] ) && $ditem['unit_price'] > 0 )
							$ditem['total_price'] = $ditem['bqty'] * $ditem['unit_price'];

						if( $ditem['plus_sign'] == "-" && $ditem['total_price'] > 0 )
						{
							$ditem['specify_cost'] = $ditem['total_price'];
							if( $ditem['bqty'] <= 0 ) $ditem['total_cost'] = $ditem['total_price'];

							//$ditem['total_price'] = 0;
						}

						$detail_items[] = $ditem;
					}
					$succ = $this->weighted_offset_handle( $detail_items );
				}
				//UPdate Qty, Amount on MOMA STOCK
				if( $succ )
					$succ = apply_filters( 'document_post_warehouse_stocks_data_handle' , $action , $header_item['doc_type'], array ( $header_item['doc_id'] ) );
			break;
			case "update-item":
				$hd_item = wp_parse_args( $header, $this->header_defaults );
				$header_item = $this->get_exist_inventory_transaction( $hd_item['doc_id'] , $hd_item['doc_type'] );

				//$header_item = wp_parse_args( $header, $this->header_defaults );
				if( ! $header['doc_id'] || ! $header['doc_type'] )
				{
					$succ = false;
				}
				//UPDATE DETAIL ITEM
				if( $succ )
				{
					$arr_item = array();
					foreach ( $details as $detail_item )
					{
						$arr_item[ $detail_item['item_id']] = $detail_item['item_id'];
					}
					//Offset Qty, Amount on MOMA STOCK
					if( $succ )
						$succ = apply_filters( 'document_post_warehouse_stocks_data_handle' , "delete" , $header_item['doc_type'], array ( $header_item['doc_id'] ) , $arr_item);

					foreach ( $details as $detail_item )
					{
						$exists_item = null; 

						$itemize = array();
						if( $detail_item['itemize'] && count( $detail_item['itemize'] ) > 0 )
						{
							$itemize = $detail_item['itemize'];
							unset( $detail_item['itemize'] );
						}

						$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
						$ditem['hid'] = $header_item['hid'];
						$ditem['plus_sign'] = $header_item['plus_sign'];
						$ditem['status'] = 1;
						$ditem['lupdate_by'] = $this->user_id;
						$ditem['lupdate_at'] = current_time( 'mysql' );
						$ditem['stock_out_type'] = $detail_item['stock_out_type']; //V1.0.9
						$ditem['prod_expiry'] = $detail_item['prod_expiry']; //V1.0.9

						$exists_item = $this->get_exist_inventory_transaction_item( $ditem['hid'], $ditem['item_id'], $ditem['product_id'], $ditem['warehouse_id'], $ditem['strg_id'] );
						if( ! $exists_item )
						{
							$succ = false; //Invalid Item Exists
							break;
						}
						else 
						{
							$upd_item = array_map_key( $ditem, $this->item_defaults ); //V1.0.4
							if ( ! $this->update_inventory_transaction_item( array( 'did' => $exists_item['did'] ) , $upd_item ) )//V1.0.4
							{
								$succ = false;
							}
							$ditem['did'] = $exists_item['did'];
							$ditem['total_cost'] =  $exists_item['total_cost'];

						}	

						//Itemize
						if( count( $itemize ) )
						{
							if( ! $this->itemize_handler( $itemize, $ditem ) )
							{
								$succ = false;
							}
						}

						//UPDATE REFERENCE DATA
						if( $succ ){
							$new_flag = $save_flag || $save_item_flag ? true : false;
							$succ = $this->update_fifo_document_handle(  $header_item['doc_post_date'] , $ditem , $exists_item  );
						}
						if( $succ === false ) 
							break;
						$detail_items[] = $ditem;
					}
					//UPdate Qty, Amount on MOMA STOCK
					if( $succ )
						$succ = apply_filters( 'document_post_warehouse_stocks_data_handle' , 'save' , $header_item['doc_type'], array ( $header_item['doc_id'] ) , $arr_item);

				}
			break;
			default:
				$succ = false;
			break;
		}
		//echo "<br /> END FUNCTION: ".$succ."----"; exit;
		//wpdb_end_transaction( $succ ) ;
		if( $succ )
		{	//Add product expiration after performing FIFO	idw added v1.2.0
			$succ = apply_filters( 'warehouse_add_doc_prod_expiry', $succ, $action, $header_item, $detail_items );
		}
		$this->succ = $succ;

		return $succ;
	}
	/**
	 *	transaction conversion
	 */
	public function transaction_conversion( $ditem = array(), $hid = 0 )
	{
		if( ! $ditem ) return $ditem;

		global $wpdb;

		//get childs
		$fld = "b.*, a.* "; //, c.meta_value AS inconsistent, d.meta_value AS kg_stock ";
		$tbl = "{$this->_tbl_item_tree} a ";
		$tbl.= "INNER JOIN {$this->_tbl_item} b ON b.id = a.ancestor ";
		//$tbl.= "LEFT JOIN {$this->_tbl_item_meta} c ON c.items_id = b.id AND c.meta_key = 'inconsistent_unit' ";
		//$tbl.= "LEFT JOIN {$this->_tbl_item_meta} d ON d.items_id = b.id AND d.meta_key = 'kg_stock' ";
		$cond = $wpdb->prepare( "AND a.descendant = %d ", $ditem['product_id'] );
		$ord = "ORDER BY a.level ASC ";
		
		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} $ord ;";
		$hierarchy = $wpdb->get_results( $sql , ARRAY_A );
		if( !$hierarchy ) return $ditem;

		//get refs
		$last_hierarchy = $hierarchy[ count( $hierarchy ) - 1 ];
		$ref_prdt_id = $last_hierarchy['ref_prdt'];
		if( $ref_prdt_id )
		{
			$fld = "b.* "; //, c.meta_value AS inconsistent, d.meta_value AS kg_stock ";
			$tbl = "{$this->_tbl_item} a ";
			$tbl.= "LEFT JOIN {$this->_tbl_item} b ON b.id = a.ref_prdt ";
			//$tbl.= "LEFT JOIN {$this->_tbl_item_meta} c ON c.items_id = b.id AND c.meta_key = 'inconsistent_unit' ";
			//$tbl.= "LEFT JOIN {$this->_tbl_item_meta} d ON d.items_id = b.id AND d.meta_key = 'kg_stock' ";
			$cond = $wpdb->prepare( "AND a.id = %d ", $last_hierarchy['id'] );
			
			$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ;";
			$ref = $wpdb->get_row( $sql , ARRAY_A );
			if( $ref )
			{
				$hierarchy[] = $ref;
			}
		}

		if( $ditem['total_price'] > 0 && $ditem['bqty'] )
			$ditem['unit_price'] = $ditem['total_price'] / $ditem['bqty'];

		if( empty( $ditem['total_price'] ) && $ditem['unit_price'] > 0 )
			$ditem['total_price'] = $ditem['bqty'] * $ditem['unit_price'];

		$new_item = [];
		$qty = $ditem['bqty']; 
		$id = $ditem['product_id'];
		$uprice = $ditem['unit_price']; 
		$total_price = $ditem['total_price'];
		
		//conversion through hierarchy
		if( count( $hierarchy ) > 1 )
		{
			$prev = [];
			foreach( $hierarchy AS $i => $row )
			{
				$id = $row['id'];

				$row['_self_unit'] = ( $row['_self_unit'] > 0 )? $row['_self_unit'] : 1;
				$prev['_parent_unit'] = ( $prev['_parent_unit'] > 0 )? $prev['_parent_unit'] : 1;
				$qty = $qty * ( $row['_self_unit'] * $prev['_parent_unit'] );
				$uprice = ( $qty )? $total_price / $qty : 0;

				$prev = $row;
				$last_hierarchy = $row;
			}

			$new_item = [
				'product_id' => $id,
				'qty' => $qty,
				'unit_price' => $uprice,
				'total_price' => $total_price,
			];
		}
		
		//unit to qty
		/*if( in_array( strtoupper( $last_hierarchy['_uom_code'] ), [ 'KG', 'L' ] ) && $last_hierarchy['inconsistent'] && $ditem['bunit'] > 0 )
		{
			$new_item = [
				'product_id' => $last_hierarchy['id'],
				'qty' => $ditem['bunit'],
				'unit_price' => $total_price / $ditem['bunit'],
				'total_price' => $total_price,
			];
			$ditem['bunit'] = 0;
		}*/
		/*if( in_array( strtoupper( $last_hierarchy['_content_uom'] ), [ 'KG', 'L' ] ) && $last_hierarchy['kg_stock'] && $ditem['bunit'] > 0 )
		{
			$new_item = [
				'product_id' => $last_hierarchy['id'],
				'qty' => !empty( $ditem['bunit'] )? $ditem['bunit'] : $last_hierarchy['_parent_unit'],
				'unit_price' => $total_price / ( !empty( $ditem['bunit'] )? $ditem['bunit'] : $last_hierarchy['_parent_unit'] ),
				'total_price' => $total_price,
			];
			$ditem['bunit'] = 0;
		}*/
		
		if( ! $new_item ) return $ditem;

		$conversion = [
			'hid' => $hid,
			'item_id' => $ditem['item_id'],
			'from_prdt_id' => $ditem['product_id'],
			'to_prdt_id' => $new_item['product_id'],
			'from_qty' => $ditem['bqty'],
			'to_qty' => $new_item['qty'],
			'uprice' => $new_item['unit_price'],
			'total_price' => $new_item['total_price'],
			'lupdate_by' => $this->user_id,
			'lupdate_at' => current_time( 'mysql' ),
		];

		$citem = wp_parse_args( $conversion, $this->conversion_defaults );

		if( $hid )
		{
			$exists = $this->get_transaction_conversion_item( $hid, $ditem['item_id'], $ditem['product_id'], $new_item['product_id'] );
			if( ! $exists )
			{
				$convsersion_id = $this->add_transaction_conversion_item( $citem );
			}
			else
			{
				if( ! $this->update_transaction_conversion_item( [ 'cid'=>$exists['cid'] ], $citem ) )
				{
					if( $this->Notices ) $this->Notices->set_notice( "Failed on UOM conversion", "error", $this->className."|transaction_conversion" );
					return false;
				}
				else
					$convsersion_id = $exists['cid'];
			}

			if( ! $convsersion_id ) 
			{
				if( $this->Notices ) $this->Notices->set_notice( "Failed on UOM conversion", "error", $this->className."|transaction_conversion" );
				return false;
			}
		} 

		$ditem['product_id'] = $conversion['to_prdt_id'];
		$ditem['bqty'] = $conversion['to_qty'];
		$ditem['unit_price'] = $conversion['uprice'];
		$ditem['total_price'] = $conversion['total_price'];

		return $ditem;
	}
	/**
	 *	Add Transaction Conversion Item
	 */
	public function add_transaction_conversion_item( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_conversion_item,
			array(
				'hid' 				=> $item['hid'],
				'item_id' 			=> $item['item_id'],
				'from_prdt_id' 		=> $item['from_prdt_id'],
				'to_prdt_id' 		=> $item['to_prdt_id'],
				'from_qty'			=> $item['from_qty'],
				'to_qty'			=> $item['to_qty'],
				'uprice' 			=> $item['uprice'],
				'total_price'		=> $item['total_price'],
				'status' 			=> $item['status'],
				'lupdate_by' 		=> $item['lupdate_by'],
				'lupdate_at' 	    => $item['lupdate_at']
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Update Transaction Conversion Item
	 */
	public function update_transaction_conversion_item( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_conversion_item, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Get Transaction Conversion Item
	 */
	public function get_transaction_conversion_item( $hid, $item_id, $from_prdt_id, $to_prdt_id )
	{
		global $wpdb;

		if ( ! $hid || ! $item_id || ! $from_prdt_id || ! $to_prdt_id ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_conversion_item." WHERE hid = %d AND item_id = %d AND from_prdt_id = %d AND to_prdt_id = %s AND status = 1", $hid , $item_id , $from_prdt_id , $to_prdt_id );

		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Deactive Transaction Conversion Item
	 */
	public function deactive_inventory_transaction_conversion( $cond ){
		global $wpdb;
		if ( ! $cond || empty ( $cond ) ) {
			return false;
		}

		$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_conversion_item." set status = 0, lupdate_by = %d , lupdate_at = %s WHERE status != 0 " , $this->user_id , get_current_time('Y-m-d H:i:s') );

		if( ! empty ( $cond ) ) 
		{
			$update_items_sql .= $cond;
		}
		//echo "<br /> DELETE ITEM---> ".$update_items_sql;
		$update = $wpdb->query( $update_items_sql );
		if ( false === $update ) {
			return false;
		}
		return true;
	}

//----------------------

	/**
	 *	Inventory Transaction Item Weighted Handle
	 */
	public function weighted_item_handle( $ditem = array(), $exists_item = array() )
	{
		if( count( $ditem ) == 0 )
		{
			return false;
		}
		$ditem_row = $this->get_inventory_transaction_item( $ditem['did'] );
		
		$succ = true;
		$bal_qty = 0; $bal_unit = 0; $bal_amount = 0;

		$last_bal = $this->get_previous_weighted_balance( $ditem['product_id'], $ditem['warehouse_id'], $ditem['strg_id'] );
		if( $ditem['plus_sign'] == '-' && ! $last_bal ) 
		{
			if( $this->Notices ) 
				$this->Notices->set_notice( "Missing Stock Balance for Stock Out!", "error", $this->className."|weighted_document_item" );
			return false;
		}
		if( $last_bal )
		{
			$bal_qty = $last_bal['bal_qty'];
			$bal_unit = $last_bal['bal_unit'];
			$bal_amount = $last_bal['bal_amount'];
		}

		$ditem = wp_parse_args( $ditem, $this->weighted_defaults );
		$ditem['qty'] = $ditem['bqty'];
		$ditem['unit'] = ( $ditem['bunit'] > 0 )? $ditem['bunit'] : $ditem_row['bunit'];
		switch( $ditem['plus_sign'] )
		{
			case "-"; //Stk Out -
				$ditem['price'] = round( $last_bal['bal_price'], 5 );
				$ditem['amount'] = round( $ditem['price'] * $ditem['qty'], 2 );

				if( $ditem['specify_cost'] > 0 )
				{
					$ditem['amount'] = round( $ditem['specify_cost'], 2 );
					$ditem['price'] = ( $ditem['qty'] )? round( $ditem['amount'] / $ditem['qty'], 5 ) : 0;
				}
				else if( ! $ditem['specify_cost'] && $ditem['imp_amt'] )
				{
					$ditem['amount'] = 0;
					$ditem['price'] = 0;
				}

				$ditem['bal_qty'] = round( $bal_qty - $ditem['qty'], 2 );
				$ditem['bal_unit'] = round( $bal_unit - $ditem['unit'], 3 );
				$ditem['bal_amount'] = round( $bal_amount - $ditem['amount'], 2 );
				$ditem['bal_price'] = ( $ditem['bal_qty'] )? round( $ditem['bal_amount'] / $ditem['bal_qty'], 5 ) : 0;

				if( $bal_qty - $ditem['qty'] < 0 )
				{
					$succ = false;

					$prdt = apply_filters( 'wcwh_get_item', [ 'id'=>$ditem['product_id'] ], [], true, [] );
					$note = ( $prdt )? "Item {$prdt['name']} - {$prdt['code']} " : "";
					if( $this->Notices ) $this->Notices->set_notice( $note."Insufficient balance", "error", $this->className."|weighted_document_item" );
				}
				else if( $bal_qty - $ditem['qty'] == 0 )
				{
					$ditem['amount'] = round( $last_bal['bal_amount'], 2 );
					$ditem['price'] = ( $ditem['qty'] )? round( $ditem['amount'] / $ditem['qty'], 5 ) : 0;
					$ditem['unit'] = round( $last_bal['bal_unit'], 3 );

					$ditem['bal_unit'] = round( $bal_unit - $ditem['unit'], 3 );
					$ditem['bal_amount'] = round( $bal_amount - $ditem['amount'], 2 );
					$ditem['bal_price'] = ( $ditem['bal_qty'] )? round( $ditem['bal_amount'] / $ditem['bal_qty'], 5 ) : 0;
				}
			break;
			case "+"; //Stk In +
				$ditem['price'] = $ditem['unit_price'];
				$ditem['amount'] = $ditem['total_price'];

				$ditem['bal_qty'] = round( $bal_qty + $ditem['qty'], 2 );
				$ditem['bal_unit'] = round( $bal_unit + $ditem['unit'], 3 );
				$ditem['bal_amount'] = round( $bal_amount + $ditem['amount'], 2 );
				$ditem['bal_price'] = ( $ditem['bal_qty'] )? round( $ditem['bal_amount'] / $ditem['bal_qty'], 5 ) : 0;
			break;
			default:
				$succ = false;
			break;
		}
		$ditem['lupdate_by'] = $this->user_id;
		$ditem['lupdate_at'] = current_time( 'mysql' );

		if( $succ )
		{
			/*$filters = [
				'did' => $ditem['did'],
				'item_id' => $ditem['item_id'],
				'product_id' => $ditem['product_id'],
				'warehouse_id' => $ditem['warehouse_id'],
				'strg_id' => $ditem['strg_id'],
				'type' => $ditem['type'],
				'plus_sign' => $ditem['plus_sign'],
			];
			$exists_item = $this->get_weighted_transaction_item( $filters );*/
			$exists_item = false;
			if( ! $exists_item )
			{
				$wid = $this->add_weighted_transaction_item( $ditem );
				if( ! $wid ) $succ = false;
				$ditem['wid'] = $wid;
			}
			else
			{
				$upd_item = array_map_key( $ditem, $this->weighted_defaults );
				if ( ! $this->update_weighted_transaction_item( array( 'did' => $exists_item['wid'] ) , $upd_item ) )//V1.0.4
				{
					$succ = false;
				}
				$ditem['wid'] = $exists_item['wid'];
			}
		}

		if( $succ && $ditem['hid'] && $ditem['did'] )
		{
			if ( ! $this->update_inventory_transaction_item_custom( $ditem['hid'],  $ditem['did'] , 'weighted_avg', $ditem ) )
			{
				$succ = false;
			}
		}

		return $succ;
	}
	/**
	 *	Weighted transaction offset
	 */
	public function weighted_offset_handle( $deleted_items )
	{
		if( ! $deleted_items || count($deleted_items) == 0 ) return false;
		
		$succ = true;
		foreach( $deleted_items as $ditem )
		{
			$bal_qty = 0; $bal_unit = 0; $bal_amount = 0;

			$last_bal = $this->get_previous_weighted_balance( $ditem['product_id'], $ditem['warehouse_id'], $ditem['strg_id'] );
			if( ! $last_bal ) 
			{
				if( $this->Notices ) 
					$this->Notices->set_notice( "Missing Stock Balance for Stock Out!", "error", $this->className."|weighted_document_item" );
				$succ = false;
				break;
			}
			if( $last_bal )
			{
				$bal_qty = $last_bal['bal_qty'];
				$bal_unit = $last_bal['bal_unit'];
				$bal_amount = $last_bal['bal_amount'];
			}

			$ditem = wp_parse_args( $ditem, $this->weighted_defaults );
			$ditem['qty'] = $ditem['bqty'];
			$ditem['unit'] = $ditem['bunit'];
			$ditem['type'] = '-1';

			switch( $ditem['plus_sign'] )
			{
				case "-"; //Stk Out revert +
					$ditem['price'] = round( $ditem['weighted_price'], 5 );
					$ditem['amount'] = round( $ditem['weighted_total'], 2 );

					$ditem['bal_qty'] = round( $bal_qty + $ditem['qty'], 2 );
					$ditem['bal_unit'] = round( $bal_unit + $ditem['unit'], 3 );
					$ditem['bal_amount'] = round( $bal_amount + $ditem['amount'], 2 );
					$ditem['bal_price'] = ( $ditem['bal_qty'] )? round( $ditem['bal_amount'] / $ditem['bal_qty'], 5 ) : 0;
				break;
				case "+"; //Stk In revert -
					$ditem['price'] = $ditem['unit_price'];
					$ditem['amount'] = $ditem['total_price'];

					$ditem['bal_qty'] = round( $bal_qty - $ditem['qty'], 2 );
					$ditem['bal_unit'] = round( $bal_unit - $ditem['unit'], 3 );
					$ditem['bal_amount'] = round( $bal_amount - $ditem['amount'], 2 );
					$ditem['bal_price'] = ( $ditem['bal_qty'] )? round( $ditem['bal_amount'] / $ditem['bal_qty'], 5 ) : 0;
				break;
				default:
					$succ = false;
				break;
			}

			$ditem['lupdate_by'] = $this->user_id;
			$ditem['lupdate_at'] = current_time( 'mysql' );

			/*$filters = [
				'did' => $ditem['did'],
				'item_id' => $ditem['item_id'],
				'product_id' => $ditem['product_id'],
				'warehouse_id' => $ditem['warehouse_id'],
				'strg_id' => $ditem['strg_id'],
				'type' => $ditem['type'],
				'plus_sign' => $ditem['plus_sign'],
			];
			$exists_item = $this->get_weighted_transaction_item( $filters );*/
			$exists_item = false;
			if( ! $exists_item )
			{
				$wid = $this->add_weighted_transaction_item( $ditem );
				if( ! $wid ) $succ = false;
				$ditem['wid'] = $wid;
			}
			else
			{
				$upd_item = array_map_key( $ditem, $this->weighted_defaults );
				if ( ! $this->update_weighted_transaction_item( array( 'did' => $exists_item['wid'] ) , $upd_item ) )//V1.0.4
				{
					$succ = false;
				}
				$ditem['wid'] = $exists_item['wid'];
			}

			/*if( $succ )
			{
				if ( ! $this->update_inventory_transaction_item_custom( $ditem['hid'],  $ditem['did'] , 'weighted_avg', $ditem ) )
				{
					$succ = false;
				}
			}*/
		}

		return $succ;
	}

	/**
	 *	Get previous weighted transaction for calculation
	 */
	public function get_previous_weighted_balance( $product_id, $warehouse_id, $strg_id = 0, $cond = "" )
	{
		if ( ! $product_id || ! $warehouse_id ) return false;

		global $wpdb;
		$get_items_sql = $wpdb->prepare( 
			"SELECT a.* 
				FROM ".$this->_tbl_weighted_item." a 
				WHERE 1 AND a.status > 0 
				AND a.product_id = %s AND a.warehouse_id = %s AND a.strg_id = %s 
				{$cond}
			ORDER BY lupdate_at DESC, a.wid DESC
			LIMIT 0,1 "
		, $product_id, $warehouse_id, $strg_id );

		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Add Transaction weighted Item
	 */
	public function add_weighted_transaction_item( $item )
	{
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_weighted_item,
			array(
				'did' 				=> $item['did'],
				'item_id' 			=> $item['item_id'],
				'product_id'		=> $item['product_id'],
				'warehouse_id'		=> $item['warehouse_id'],
				'strg_id'			=> $item['strg_id'],
				'qty' 				=> $item['qty'],
				'unit' 				=> isset( $item['unit'] )? $item['unit'] : 0,
				'price'				=> $item['price'],
				'amount'			=> $item['amount'],
				'type'				=> isset( $item['type'] )? $item['type'] : '1',
				'plus_sign'			=> $item['plus_sign'],
				'bal_qty' 			=> $item['bal_qty'],
				'bal_unit' 			=> $item['bal_unit'],
				'bal_price' 		=> $item['bal_price'],
				'bal_amount'		=> $item['bal_amount'],
				'status' 			=> $item['status'],
				'lupdate_by' 		=> $item['lupdate_by'],
				'lupdate_at' 	    => $item['lupdate_at']
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Update Transaction Conversion Item
	 */
	public function update_weighted_transaction_item( $cond , $item )
	{
		if ( ! $cond || ! $item ) return false;

		global $wpdb;
		$update = $wpdb->update( $this->_tbl_weighted_item, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Get Weighted Transaction Item
	 */
	public function get_weighted_transaction_item( $conds = [], $single = false )
	{
		if ( ! $conds ) return false;

		global $wpdb;

		$cd = ""; $has_stat = false;
		foreach( $conds as $key => $val )
		{
			if( is_array( $val ) )
			{
				$cd.= "AND {$key} IN( '".implode( "', '", $val )."' ) ";
			}
			else
			{
				$cd.= $wpdb->prepare( "AND {$key} = %s ", $val );
			}

			if( $key == 'status' ) $has_stat = true;
		}

		if( ! $has_stat ) $cd.= $wpdb->prepare( "AND status > %d ", 0 );
		
		$get_items_sql  = "SELECT * FROM ".$this->_tbl_weighted_item." WHERE 1 {$cd} ";

		if( $single )
			return $wpdb->get_row( $get_items_sql , ARRAY_A );
		else
			return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Deactive Weighted Transaction Item
	 */
	public function deactive_weighted_transaction_item( $cond )
	{
		if ( ! $cond || empty ( $cond ) ) return false;

		global $wpdb;
		$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_weighted_item." set status = 0, lupdate_by = %d , lupdate_at = %s WHERE status != 0 " , $this->user_id , get_current_time('Y-m-d H:i:s') );

		if( ! empty ( $cond ) ) 
		{
			$update_items_sql .= $cond;
		}
		//echo "<br /> DELETE ITEM---> ".$update_items_sql;
		$update = $wpdb->query( $update_items_sql );
		if ( false === $update ) {
			return false;
		}
		return true;
	}

//----------------------

	/**
	 *	Add Inventory Transaction
	 */
	public function add_inventory_transaction( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_inventory,
			array(
				'docno' 			=> $item['docno'],
				'doc_id' 			=> $item['doc_id'],
				'doc_type' 			=> $item['doc_type'],
				'doc_post_date' 	=> $item['doc_post_date'],
				'plus_sign' 		=> $item['plus_sign'],
				'status' 			=> $item['status'],
				'lupdate_by' 		=> $item['lupdate_by'],
				'lupdate_at' 	    => $item['lupdate_at']
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Add Inventory Transaction Item
	 */
	public function add_inventory_transaction_item( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_inventory_item,
			array(
				'hid' 				=> $item['hid'],
				'item_id' 			=> $item['item_id'],
				'product_id' 		=> $item['product_id'],
				'warehouse_id' 		=> $item['warehouse_id'],
				'strg_id'			=> $item['strg_id'],
				'batch'				=> $item['batch'],
				'bqty' 				=> $item['bqty'],
				'bunit'				=> $item['bunit'],
				//'unit_cost' 		=> $item['unit_cost'],
				'total_cost' 		=> ( $item['total_cost'] > 0 )? $item['total_cost'] : 0,
				'unit_price' 		=> $item['unit_price'],
				'total_price' 		=> $item['total_price'],
				'weighted_price'	=> ( $item['weighted_price'] > 0 )? $item['weighted_price'] : 0,
				'weighted_total'	=> ( $item['weighted_total'] > 0 )? $item['weighted_total'] : 0,
				'plus_sign' 		=> $item['plus_sign'],
				'status' 			=> $item['status'],
				'lupdate_by' 		=> $item['lupdate_by'],
				'lupdate_at' 	    => $item['lupdate_at']
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Update Inventory Transaction
	 */
	public function update_inventory_transaction( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_inventory, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Update Inventory Transaction Item
	 */
	public function update_inventory_transaction_item( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_inventory_item, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	FIFO Item - Add Inventory reference
	 */
	public function add_inventory_transaction_ref( $item ){
		global $wpdb;

		$wpdb->insert(
			$this->_tbl_inventory_ref,
			array(
				'hid' 				=> $item['hid'],
				'did' 				=> $item['did'],
				'bqty' 				=> $item['bqty'],
				'bunit'				=> $item['bunit'],
				'unit_cost' 		=> $item['unit_cost'],
				'ref_hid' 			=> $item['ref_hid'],
				'ref_did' 			=> $item['ref_did']
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	FIFO Item - Update Inventory reference
	 */
	public function update_inventory_transaction_ref( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_inventory_ref, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Add Inventory Meta - V1.0.9
	 */
	public function add_inventory_transaction_meta( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_inventory_meta,
			array(
				'hid' 			=> $item['hid'],
				'did' 			=> $item['did'],
				'ddid' 			=> $item['ddid'],
				'meta_key' 		=> $item['meta_key'],
				'meta_value' 	=> $item['meta_value']
			),
			array(
				'%d', '%d', '%d', '%s', '%s'
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Inventory Meta Handle V1.0.9
	 */
	public function add_inventory_meta_value( $meta_key , $meta_value , $hid, $did = 0, $ddid = 0 ){
		global $wpdb;
		$succ = true;

		if( ! $hid )
		{
			return false;
		}
		$exists = $this->get_inventory_transaction_meta( $hid , $meta_key , $did, $ddid );
		if ( ! $exists )
		{
			if( ! empty ( $meta_value ) ) //DELETE IF EMPTY
			{
				$wpdb->insert(
					$this->_tbl_inventory_meta,
					array(
						'hid' 			=> $hid,
						'did' 			=> $did,
						'ddid' 			=> $ddid,
						'meta_key' 		=> $meta_key,
						'meta_value' 	=> $meta_value
					)
				);
				$meta_id = absint( $wpdb->insert_id ); 
				if( ! $meta_id )
					$succ = false;
			}
		} else {
			if( ! $meta_value || empty ( $meta_value ) ) //DELETE IF EMPTY
			{
				if( ! $this->delete_inventory_transaction_meta( array( "meta_key" => $meta_key , "hid" => $hid , "did" => $did, "ddid" => $ddid ) ) ) 
					return false;
			}
			else //UPDATE IF NOT EMPTY
			{
				$meta_id = $exists['meta_id']; //V1.0.3
				if( ! $this->update_inventory_transaction_meta( $exists['meta_id'] , array( "meta_value" => $meta_value) ) )
				{
					$succ = false;
				}
			}
		}
		return $succ;
	}
	/**
	 *	Update Inventory Meta - V1.0.9
	 */
	public function update_inventory_transaction_meta( $meta_id , $item ){
		global $wpdb;

		if ( ! $meta_id ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_inventory_meta, $item, array( 'meta_id' => $meta_id ) );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Delete Inventory Meta - V1.0.9
	 */
	public function delete_inventory_transaction_meta( $cond ){
		global $wpdb;

		if ( ! $cond ) {
			return false;
		}
		$update = $wpdb->delete( $this->_tbl_inventory_meta, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Get Inventory Meta- V1.0.9
	 */
	public function get_inventory_transaction_meta( $hid , $meta_key, $did = 0 , $ddid = 0 ){
		global $wpdb;

		if ( ! $hid || empty ( $meta_key ) ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_inventory_meta." WHERE hid = %d AND did = %d AND ddid = %d AND meta_key = %s", $hid , $did , $ddid , $meta_key );
		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Inventory Transaction Item
	 */
	public function get_inventory_transaction_item( $did, $conds = [] )
	{
		if ( ! $did ) return false;

		global $wpdb;

		$cd = ""; $has_status = false;
		if( $conds )
		{
			foreach( $conds as $key => $val )
			{
				if( is_array( $val ) )
				{
					$cd.= "AND {$key} IN( '".implode( "', '", $val )."' ) ";
				}
				else
				{
					$cd.= $wpdb->prepare( "AND {$key} = %s ", $val );
				}

				if( $key == 'status' ) $has_status = true;
			}
		}

		if( ! $has_status ) $cd.= "AND status != 0 ";
		
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_inventory_item." WHERE did = %d {$cd} ", $did );
		
		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Inventory Transaction Header with doc id, doc_type
	 */
	public function get_exist_inventory_transaction( $doc_id, $doc_type ){
		global $wpdb;

		if ( empty( $doc_id ) || empty ( $doc_type ) ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_inventory." WHERE doc_id = %d AND doc_type = %s AND status = 1 ", $doc_id ,$doc_type );
		//echo "<br /> DDDD: ".$get_items_sql;
		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Exist Inventory Transaction Item
	 */
	public function get_exist_inventory_transaction_item( $hid , $item_id , $product_id , $warehouse_id, $strg_id = 0 ){
		global $wpdb;

		if ( ! $hid || ! $item_id || ! $product_id || ! $warehouse_id ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_inventory_item." WHERE hid = %d AND item_id = %d AND product_id = %d AND warehouse_id = %s AND strg_id = %d AND status = 1", $hid , $item_id , $product_id , $warehouse_id, $strg_id );
		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/** 
	 *	Get Inventory Transaction latest Price
	 */
	public function get_inventory_transaction_item_latest_price( $product_id = 0, $warehouse_id = '' ,$strg_id = 0, $plus_sign = "+" )
	{
		global $wpdb;

		if ( ! $strg_id || ! $product_id || ! $warehouse_id ) return false;

		$fld = "a.*, b.docno, b.doc_id, b.doc_type, b.doc_post_date, a.product_id, c.from_prdt_id ";

		if( $plus_sign == "+" )
		{
			$fld.= ", a.unit_price AS uprice, IF( c.from_prdt_id > 0, a.total_price / c.from_qty, 0 ) AS converse_uprice ";
		}
		else if( $plus_sign == "-" )
		{
			$fld.= ", a.unit_cost AS uprice, IF( c.from_prdt_id > 0, a.total_cost / c.from_qty, 0 ) AS converse_uprice ";
		}
		
		$tbl = "{$this->_tbl_inventory_item} a ";
		$tbl.= "LEFT JOIN {$this->_tbl_inventory} b ON b.hid = a.hid ";
		$tbl.= "LEFT JOIN {$this->_tbl_conversion_item} c ON c.hid = a.hid AND c.item_id = a.item_id ";

		$cond = $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $warehouse_id, $strg_id );
		$cond.= $wpdb->prepare( "AND a.plus_sign = %s ", $plus_sign );
		$cond.= "AND b.doc_type NOT IN ( 'stock_adjust', 'stocktake', 'pos_transactions' ) ";

		$cond.= $wpdb->prepare( "AND ( a.product_id = %s OR c.from_prdt_id = %s ) ", $product_id, $product_id );

		if( $plus_sign == "+" )
			$cond.= $wpdb->prepare( "AND a.unit_price > %d ", 0 );
		else if( $plus_sign == "-" )
			$cond.= $wpdb->prepare( "AND a.unit_cost > %d ", 0 );

		$grp = "";
		$ord = "ORDER BY b.doc_post_date desc ";
		$l = "limit 0, 1";

		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";

		return $wpdb->get_row( $sql , ARRAY_A );
	}
	/** 
	 *	Get Inventory Transaction Weighted Price
	 SELECT a.*, ic.*
	 FROM wp_stmm_wcwh_transaction_weighted a 
	 LEFT JOIN wp_stmm_wcwh_item_converse ic ON ic.base_id = a.product_id
	 WHERE 1 AND ic.item_id IN ( 314, 206,9999 ) AND ic.item_id = 314
	 */
	public function get_inventory_transaction_item_weighted_price( $product_id = 0, $warehouse_id = '', $strg_id = 0, $plus_sign = "+" )
	{
		if ( ! $product_id || ! $warehouse_id || ! $strg_id ) return false;

		global $wpdb;

		$sql = $wpdb->prepare( "SELECT DISTINCT ancestor AS item_id FROM {$this->_tbl_item_tree} WHERE descendant = %s 
			UNION ALL
			SELECT DISTINCT descendant AS item_id FROM {$this->_tbl_item_tree} WHERE ancestor = %s ", $product_id, $product_id );
		$parent_child = $wpdb->get_col( $sql );
		$parent_child = array_unique( $parent_child );

		$fld = "a.*, ic.item_id AS request_item_id, ic.base_id, ic.level, ic.base_unit 
			, ROUND( @cqty := a.bal_qty / IFNULL(ic.base_unit,1), 2 ) AS converse_qty, ROUND( a.bal_amount / @cqty, 5 ) AS converse_uprice ";
		
		$tbl = "{$this->_tbl_weighted_item} a ";
		$tbl.= "LEFT JOIN {$this->_tbl_item_converse} ic ON ic.base_id = a.product_id ";

		$cond = "AND a.status > 0 AND ( a.bal_qty > 0 AND a.bal_amount > 0 ) ";
		$cond.= $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $warehouse_id, $strg_id );
		$cond.= "AND ic.item_id IN ( '".implode( "','", $parent_child )."' ) ";
		$cond.= $wpdb->prepare( "AND ic.item_id = %s ", $product_id );

		$grp = "";
		$ord = "ORDER BY a.lupdate_at DESC, a.wid DESC ";
		$l = "LIMIT 0,1 ";

		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";

		return $wpdb->get_row( $sql , ARRAY_A );
	}
	/**
	 *	Get Inventory Transaction Item : To be deleted
	 *	V1.0.9: add select product stockout type
	 */
	public function get_exist_inventory_transaction_item_deletion( $active_doc , $active_items = array() )
	{
		global $wpdb;

		if ( ! $active_doc ) {
			return false;
		}
		$get_items_sql = "SELECT a.did,a.hid,a.item_id,a.product_id,a.warehouse_id,a.strg_id,a.bqty,a.bunit,a.plus_sign,a.deduct_qty,a.deduct_unit,a.unit_cost,a.total_cost,a.unit_price,a.total_price,a.weighted_price,a.weighted_total
		, prod._stock_out_type as stock_out_type
							FROM ".$this->_tbl_inventory_item." a 
							LEFT JOIN ".$this->_tbl_postmeta." prod ON prod.id = a.product_id 
							WHERE a.status != 0 "; //V1.0.9
		if( count($active_doc) > 0 ) 
		{
			$get_items_sql.=" AND hid IN ( " . implode( ',', $active_doc ) . ")";
		}
		if( count($active_items) > 0 ) 
		{
			$get_items_sql.=" AND did NOT IN ( " . implode( ',', $active_items ) . ")";
		}
		//echo "<br />".$get_items_sql."---------<br />";
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Inventory Transaction Ref
	 */
	public function get_inventory_ref( $ddid ){
		global $wpdb;

		if ( ! $ddid ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_inventory_ref." WHERE ddid = %d AND status = 1", $ddid );

		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Inventory Transaction Ref
	 */
	public function get_inventory_ref_by_item( $hid , $did , $ref_hid = 0 , $ref_did = 0 , $status = 1 ){
		global $wpdb;

		if ( ! $did || ! $hid ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT a.* FROM ".$this->_tbl_inventory_ref." a WHERE a.hid = %d AND a.did = %d ", $hid , $did );
		if( $ref_hid > 0 )
		{
			$get_items_sql .= $wpdb->prepare( " AND a.ref_hid = %d ", $ref_hid );
		}
		if( $ref_did > 0 )
		{
			$get_items_sql .= $wpdb->prepare( " AND a.ref_did = %d ", $ref_did );
		}
		if( $status > 0 )
		{
			$get_items_sql .= $wpdb->prepare( " AND a.status = %d ", $status );
		}
		$get_items_sql .= " ORDER BY ddid ";
		//echo "<br />".$get_items_sql."------";
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Inventory Transaction Ref Available Item - V1.0.9
	 *  - Offset purpose
	 */
	public function get_outstanding_inventory_ref_by_item( $hid , $did , $stock_out_type = 1 ){
		global $wpdb;

		if ( ! $did || ! $hid ) {
			return false;
		}

		if( $this->enforce_stockout > 0 ) $stock_out_type = $this->enforce_stockout;

		$stockout_type = $this->get_stock_out_type( $stock_out_type );
		$default_expiry = $stockout_type['prod_expiry']['default_value'];
		$get_items_sql  = $wpdb->prepare( "SELECT a.*,in_doc.doc_post_date, IFNULL(prod.meta_value, %s) as prod_expiry  
			FROM ".$this->_tbl_inventory_ref." a 
			LEFT JOIN ".$this->_tbl_inventory_meta." item ON item.hid = a.hid AND item.did = a.did AND item.ddid = a.ddid AND item.meta_key = 'stockin_id'
			LEFT JOIN ".$this->_tbl_inventory_meta." prod ON prod.did = item.meta_value AND prod.meta_key = 'prod_expiry'
			LEFT JOIN ".$this->_tbl_inventory." in_doc ON in_doc.hid = prod.hid
			WHERE a.hid = %d AND a.did = %d ", $default_expiry , $hid , $did );
		$ordby = $stockout_type['normal'];
		if( !empty( $ordby ) )
		{
			$get_items_sql .= " ORDER BY ".$ordby.",ddid ASC";
		}
		else
		{
			$get_items_sql .= " ORDER BY ddid ";
		}
		//echo "<br />".$get_items_sql."------"; exit;
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Inventory Transaction Available Item
	 */
	public function get_outstanding_inventory_transaction_item( $product_id , $warehouse_id, $strg_id = 0 , $plus_sign = '+', $date = '', $cond = '', $stock_out_type = 1 ){
		global $wpdb;

		if ( ! $product_id || ! $warehouse_id ) {
			return false;
		}

		if( $this->enforce_stockout > 0 ) $stock_out_type = $this->enforce_stockout;

		$stockout_type = $this->get_stock_out_type( $stock_out_type );
		$default_expiry = $stockout_type['prod_expiry']['default_value'];
		$get_items_sql  = $wpdb->prepare( "SELECT b.did, b.hid, b.item_id, b.product_id, b.warehouse_id, b.strg_id, b.bqty, b.bunit,b.deduct_qty,b.deduct_unit,b.unit_cost,b.total_cost,b.unit_price, b.total_price, b.weighted_price, b.weighted_total, b.plus_sign,b.status ,a.doc_post_date, IFNULL(prod.meta_value, %s) as prod_expiry
			FROM ".$this->_tbl_inventory." a 
			INNER JOIN ".$this->_tbl_inventory_item." b ON b.hid = a.hid
			LEFT JOIN ".$this->_tbl_inventory_meta." prod ON prod.hid = b.hid AND prod.did = b.did AND prod.meta_key = 'prod_expiry'
			WHERE b.product_id = %d AND b.warehouse_id = %s AND b.strg_id = %d AND b.status = 1 AND a.status = 1 AND b.plus_sign = %s AND b.flag = 0 AND b.bqty > b.deduct_qty "
			, $default_expiry , $product_id , $warehouse_id, $strg_id, $plus_sign );
		
		if( ! empty( $date ) ) 
		{
			$get_items_sql .= " AND a.doc_post_date <= '".$date."'";
		}

		if( ! empty( $cond ) ) 
		{
			$get_items_sql .= " ".$cond;
		}
		$ordby = $stockout_type['normal'];
		if( !empty( $ordby ) )
		{
			$get_items_sql .= " ORDER BY ".$ordby;
		}
		//echo "<br />".$get_items_sql."------"; exit;
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Inventory Transaction Item FIFO Handle
	 */
	public function update_fifo_document_handle ( $doc_post_date , $ditem = array(), $exists_item = array() , $new_flag = false )
	{
		if( empty( $doc_post_date ) || count( $ditem ) == 0 )
		{
			return false;
		}
		$succ = true;
		switch( $ditem['plus_sign'] )
		{
			case "-"; //Stk Out
				if( $new_flag )
				{
					$os_qty = $ditem['bqty'];
					$os_unit = $ditem['bunit'];
				}
				else 
				{
					//edited qty < previous issue qty , reallocated FIFO items
					if ( ! $new_flag && $ditem['bqty'] < $exists_item['deduct_qty'] && $exists_item['deduct_qty'] > 0 ) 
					{
						echo "<br /> Error Return Qty > Issue/DO Qty!!!";
						//Return Qty > Issue qty
						$succ = false;
					}
					else if( $exists_item['bqty'] != $ditem['bqty'] )
					{
						if ( $ditem['bqty'] > $exists_item['bqty'] )  //edited qty > previous qty , allocated FIFO items
						{
							$os_qty = $ditem['bqty'] - $exists_item['bqty'];
							$os_unit = $ditem['bunit'] - $exists_item['bunit'];
						}
						else  //edited qty < previous qty , offset items BY LIFO
						{
							$outstanding_offset_qty = $exists_item['bqty'] - $ditem['bqty'];
							$outstanding_offset_unit = $exists_item['bunit'] - $ditem['bunit'];
							$succ = $this->offset_fifo_document_item( $ditem , $outstanding_offset_qty, $outstanding_offset_unit );
						}
					}
				}
				if( $os_qty > 0 )
				{
					$succ = $this->update_fifo_document_item ( $doc_post_date , $ditem , $os_qty, $os_unit , $new_flag );
				}
			break;
			case "+"; //Stk In
				//edited qty < previous issue qty , reallocated FIFO items
				if ( ! $new_flag && $ditem['bqty'] < $exists_item['deduct_qty'] && $exists_item['deduct_qty'] > 0 ) 
				{
					$offset_qty = $exists_item['deduct_qty'] - $ditem['bqty'];
					$offset_unit = $exists_item['deduct_unit'] - $ditem['bunit'];
					$reallocated = true; //True if $ditem[bqty] < $exists_item[deduct_qty]

					//Offset Previous FIFO Link with LIFO
					$succ = $this->offset_fifo_document_item( $ditem , $offset_qty , $offset_unit , $reallocated );
				}
			break;
			default:
				$succ = false;
		}
		return $succ;
	}
	/**
	 *	Inventory Transaction Item - FIFO ITEM Linking
	 */
	public function update_fifo_document_item ( $doc_post_date , $ditem = array() , $os_qty = 0, $os_unit = 0 , $new_flag = false )
	{
		if( $os_qty <= 0 ) //No Changes
			return true;
		$succ = true;
		$outstanding_qty = $os_qty ;
		$outstanding_unit = $os_unit ;
		$isEmptyUnit = ( $outstanding_unit != 0 )? false : true;
		$plus_sign = $ditem['plus_sign'] == "-" ? "+" : "-";
		$total_cost = 0;// isset( $ditem[total_cost] ) ?  $ditem[total_cost] : 
		$cond = "";
		if( isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] > 0 )
		{
			$cond = " AND b.item_id = ".$ditem['ref_item_id'];
		}
		else if( isset( $ditem['stock_item_id'] ) && $ditem['stock_item_id'] > 0 )
		{
			$cond = " AND b.item_id = ".$ditem['stock_item_id'];
		}
		//echo $cond; $doc_post_date
		$inv_date = '';
		if( $this->setting['wh_inventory']['strict_doc_date_deduction'] ) $inv_date = $doc_post_date;
		$ref_items = $this->get_outstanding_inventory_transaction_item( $ditem['product_id'] , $ditem['warehouse_id'], $ditem['strg_id'] , $plus_sign , $inv_date , $cond , $ditem['stock_out_type'] );
		//echo "<br />Before FIFO----".$os_qty; results_table($ref_items);
		foreach( $ref_items as $ref )
		{
			$outstanding_qty = round( $outstanding_qty, 2 );
			$outstanding_unit = round( $outstanding_unit, 3 );
			if( $outstanding_qty == 0 )
				break;

			$upd_qty = 0; $upd_unit = 0;
			if( round( $ref['bqty'] - $ref['deduct_qty'], 2 ) < $outstanding_qty )
			{
				$upd_qty = $ref['bqty'] - $ref['deduct_qty'];
				$upd_unit = $ref['bunit'] - $ref['deduct_unit'];

				//if( $ditem['product_id'] == 470 ){ pd($ref); }
			}
			else 
			{
				$upd_qty = $outstanding_qty;

				if( $isEmptyUnit )
				{
					$avai_qty = round( $ref['bqty'] - $ref['deduct_qty'], 2 );
					$avai_unit = round( $ref['bunit'] - $ref['deduct_unit'], 3 );

					if( $avai_qty == $upd_qty ) 
						$upd_unit = $avai_unit;
					else
						$upd_unit = round( $avai_unit / $avai_qty * $upd_qty, 3 );

					if( $upd_unit > $avai_unit ) $upd_unit = $avai_unit;
				}
				else
				{
					$upd_unit = $outstanding_unit;
				}
			}

			if( $isEmptyUnit && $upd_unit != 0 )
			{
				if( ! $this->update_inventory_transaction_item_bunit( $ditem['hid'] ,  $ditem['did'] , $upd_unit , "+" ) )
				{
					$succ =false;
					break;
				}
			}

			if( ! $new_flag ) //If not new added record, check if exists
				$exist_refs = $this->get_inventory_ref_by_item( $ditem['hid'], $ditem['did'] , $ref['hid'] , $ref['did']);

			if( ! $exist_refs )
			{
				//New Added Document Linking
				$ref_data = array(
					'hid' 				=> $ditem['hid'],
					'did' 				=> $ditem['did'],
					'bqty' 				=> $upd_qty,
					'bunit'				=> $upd_unit,
					'unit_cost'			=> isset( $ref['total_price'] ) ? round( $ref['total_price'] / $ref['bqty'], 5 ) : 0,
					'ref_hid' 			=> $ref['hid'],
					'ref_did' 			=> $ref['did']
				);

				$ref_id = $this->add_inventory_transaction_ref ( $ref_data );
				if( ! $ref_id )
				{
					$succ = false; 
				}
			}
			else
			{
				$exist_ref = $exist_refs[0];
				//Updated Existing Document Linking
				$new_bqty = $exist_ref['bqty'] + $upd_qty;
				$new_bunit = $exist_ref['bunit'] + $upd_unit;
				if( ! $this->update_inventory_transaction_ref( array( 'ddid' => $exist_ref['ddid'] ) , array ( 'bqty' => $new_bqty, 'bunit' => $new_bunit )) )
				{
					$succ = false;
					break;
				}
				$ref_id = $exist_ref['ddid'];
			}
			//Add IV Item Meta V1.0.9
			if( $succ ){
				if( ! $this->add_inventory_meta_value( 'stockin_id' , $ref['did'] , $ditem['hid'], $ditem['did'], $ref_id ) )
				{
					$succ = false;
					break;
				}
			}
			//END IV Item Meta V1.0.9

			$total_cost += $upd_qty * round( $ref['total_price'] / $ref['bqty'], 5 );
			//if( $upd_unit > 0 ) $total_cost += $upd_unit * $ref['unit_price'] ;
			//Update Linked Document Deduct Qty
			if( ! $this->update_inventory_transaction_item_deduct_qty( $ref['hid'] ,  $ref['did'] , $upd_qty, $upd_unit , $plus_sign ) )
			{
				$succ =false;
				break;
			}
			$outstanding_qty -= $upd_qty;
			//if( $ditem['product_id'] == 470 ){ echo $upd_qty.":".$outstanding_qty." "; }
			if( ! $isEmptyUnit ) $outstanding_unit -= $upd_unit;
			if( $succ == false )
				break;
		}
		if( $outstanding_qty > 0 ) //Remain Qty > 0 = No enough stocks
		{
			$succ = false;
			$prdt = apply_filters( 'wcwh_get_item', [ 'id'=>$ditem['product_id'] ], [], true, [] );
			$note = ( $prdt )? "Item {$prdt['name']} - {$prdt['code']} " : "";
			if( $this->Notices ) $this->Notices->set_notice( $note."Insufficient stock {$ditem['product_id']}", "error", $this->className."|update_fifo_document_item" );
		}

		if( $succ ) //UPDATE AVG COST, TOTAL COST on Item
		{
			if ( ! $this->update_inventory_transaction_item_custom( $ditem['hid'],  $ditem['did'] , 'cost', $total_cost , '+' ) )
			{
				$succ = false;
			}
		}
		$ref_items = $this->get_outstanding_inventory_transaction_item( $ditem['product_id'] , $ditem['warehouse_id'], $ditem['strg_id'] , $plus_sign , $doc_post_date , $cond , $ditem['stock_out_type'] );
		//echo "<br />AFTER FIFO----".$os_qty; results_table($ref_items);
		return $succ;
	}
	/**
	 *	Offset/Remove Inventory Transaction Item - FIFO ITEM Linking
	 */
	public function offset_fifo_document_item ( $ditem = array() , $os_qty = 0, $os_unit = 0 , $reallocated = false )
	{
		if( $os_qty <= 0 ) //No Changes
			return true;
		$succ = true;
		$outstanding_offset_qty = $os_qty ;
		$outstanding_offset_unit = $os_unit ;

		//echo "<br /> BEFORE OFFSET : ".$reallocated;
		if( $reallocated )
		{
			//edited qty < deducted qty , reallocated FIFO child items
			$exist_ref = $this->get_inventory_ref_child( $ditem['hid'] ,$ditem['did'] );
		}
		else 
		{
			//edited qty < previous qty , offset FIFO child items qty
			//$exist_ref = $this->get_inventory_ref_by_item( $ditem['hid'], $ditem['did'] );
			$exist_ref = $this->get_outstanding_inventory_ref_by_item( $ditem['hid'], $ditem['did'] ,$ditem['stock_out_type']); //V1.0.9
		}
		//results_table ( $exist_ref );
		$len = count( $exist_ref );
		$reallocated_ref_item = array();

		for( $r = $len - 1; $r >= 0; $r-- )
		{
			$outstanding_offset_qty = round( $outstanding_offset_qty, 2 );
			$outstanding_offset_unit = round( $outstanding_offset_unit, 3 );
			if( $outstanding_offset_qty == 0 )
				break;

			$offset_qty = 0; $offset_unit = 0;
			if( round( $exist_ref[$r]['bqty'], 2 ) < $outstanding_offset_qty )
			{
				$offset_qty = $exist_ref[$r]['bqty'];
				$offset_unit = $exist_ref[$r]['bunit'];
			}
			else 
			{
				$offset_qty = $outstanding_offset_qty;
				$offset_unit = $outstanding_offset_unit;
			}

			/*if( $offset_unit != 0 )
			{	
				if( ! $this->update_inventory_transaction_item_bunit( $ditem['hid'] ,  $ditem['did'] , $offset_unit , "-" ) )
				{
					$succ =false;
					break;
				}
			}*/

			$new_bqty = $exist_ref[$r]['bqty'] - $offset_qty;
			$new_bunit = $exist_ref[$r]['bunit'] - $offset_unit;
			$new_status = $new_bqty == 0 ? 0: 1;
			//echo $new_bqty."---".$offset_qty."---".$new_bqty."|||";
			$exist_ref[$r]['bqty'] = $offset_qty;
			$exist_ref[$r]['bunit'] = $offset_unit;
			$exist_ref[$r]['product_id'] = $ditem['product_id'];
			$exist_ref[$r]['warehouse_id'] = $ditem['warehouse_id'];
			$exist_ref[$r]['strg_id'] = $ditem['strg_id'];
			$exist_ref[$r]['plus_sign'] = $ditem['plus_sign'] == "+" ? "-" : "+";
			$exist_ref[$r]['stock_out_type'] = $ditem['stock_out_type'] == "" ? "1" : $ditem['stock_out_type'];
			$reallocated_ref_item[] = $exist_ref[$r];

			if( ! $this->update_inventory_transaction_ref( array( 'ddid' => $exist_ref[$r]['ddid'] ), array ( 'bqty' => $new_bqty, 'bunit' => $new_bunit , 'status' => $new_status ) ) )
			{
				$succ =false;
				break;
			}
			$total_cost = $offset_qty * $exist_ref[$r]['unit_cost'];
			//if( $offset_unit > 0 ) $total_cost = $offset_unit * $exist_ref[$r]['unit_cost'];
			if( ! $reallocated )
			{
				if( ! $this->update_inventory_transaction_item_deduct_qty( $exist_ref[$r]['ref_hid'] ,  $exist_ref[$r]['ref_did'] , $offset_qty, $offset_unit , "-" ) )
				{
					$succ =false;
					break;
				}
				if ( ! $this->update_inventory_transaction_item_custom( $ditem['hid'],  $ditem['did'] , 'cost', $total_cost , '-' ) )
				{
					$succ = false;
					break;
				}
			}
			else 
			{
				if ( ! $this->update_inventory_transaction_item_custom( $exist_ref[$r]['hid'],  $exist_ref[$r]['did']  , 'cost', $total_cost , '-' ) )
				{
					$succ = false;
				}
			}

			$outstanding_offset_qty -= $offset_qty;
			$outstanding_offset_unit -= $offset_unit;
		}
		if( $outstanding_offset_qty > 0 )
		{
			$succ = false;
			$prdt = apply_filters( 'wcwh_get_item', [ 'id'=>$ditem['product_id'] ], [], true, [] );
			$note = ( $prdt )? "Item {$prdt['name']} - {$prdt['code']} " : "";
			if( $this->Notices ) $this->Notices->set_notice( $note."Insufficient stocks {$ditem['product_id']}", "error", $this->className."|offset_fifo_document_item" );
		}

		//Reallocated FIFO Items.
		if( $reallocated && $succ )
		{
			if( ! $this->update_inventory_transaction_item_deduct_qty( $ditem['hid'] ,  $ditem['did'] , $os_qty, $os_unit , "-" ) )
			{
				$succ =false;
			}
			for( $u = count( $reallocated_ref_item )-1 ; $u >= 0 ; $u-- ) // FIFO
			{
				$outstanding_qty = $reallocated_ref_item[$u]['bqty'];
				$outstanding_unit = $reallocated_ref_item[$u]['bunit'];
				$succ = $this->update_fifo_document_item ( $reallocated_ref_item[$u]['doc_post_date'] ,  $reallocated_ref_item[$u] , $outstanding_qty, $outstanding_unit , $new_flag );
				if( ! $succ )
					break;
			}
		} 
		return $succ;
	}
	/**
	 *	Action for Deleted Items
	 */
	public function deleted_fifo_document_item_handles( $deleted_items ){

		if( ! $deleted_items || count($deleted_items) == 0 )
			return false;
		$succ = true;
		foreach( $deleted_items as $items )
		{
			switch( $items['plus_sign'] )
			{
				case "-"; //Stk Out
					if( $new_flag )
					{
						$os_qty = $ditem['bqty'];
						$os_unit = $ditem['bunit'];
					}
					else 
					{
						//edited qty < previous issue qty , reallocated FIFO items
						if ( $items['deduct_qty'] > 0 ) 
						{
							//Return Qty > Issue qty
							$succ = false;
						}
						else if( $items['bqty'] > 0 )
						{
							$outstanding_offset_qty = $items['bqty'] ;
							$outstanding_offset_unit = $items['bunit'] ;
							$succ = $this->offset_fifo_document_item( $items , $outstanding_offset_qty, $outstanding_offset_unit );
						}
					}
				break;
				case "+"; //Stk In
					//edited qty < previous issue qty , reallocated FIFO items
					if ( $items['deduct_qty'] > 0 ) 
					{
						$offset_qty = $items['deduct_qty'];
						$offset_unit = $items['deduct_unit'];
						$reallocated = true; 
						//Offset Previous FIFO Link with LIFO
						$succ = $this->offset_fifo_document_item( $items , $offset_qty , $offset_unit , $reallocated );
					}
				break;
				default:
					$succ = false;
			}
		}
		return true;
	}
	/**
	 *	Update Inventory Transaction Item Deduct Qty
	 */
	public function update_inventory_transaction_item_deduct_qty( $hid, $did , $qty, $unit = 0 , $plus = '+' ){
		global $wpdb;

		if ( ! $hid || ! $did || ! $qty ) {
			return false;
		}

		$update_items_sql  = $wpdb->prepare( "UPDATE ".$this->_tbl_inventory_item." 
			SET deduct_qty = deduct_qty ".$plus." %s, deduct_unit = deduct_unit ".$plus." %s ,flag = IF( bqty = deduct_qty, 1, 0) WHERE hid = %d AND did = %d AND status = 1 " , $qty, $unit , $hid , $did );
		$update = $wpdb->query( $update_items_sql );
		//echo "<br />#### --- ".$update_items_sql;
		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Update Inventory Transaction Item Base Unit
	 */
	public function update_inventory_transaction_item_bunit( $hid, $did , $unit = 0 , $plus = '+' ){
		global $wpdb;

		if ( ! $hid || ! $did || ! $unit ) {
			return false;
		}

		$update_items_sql  = $wpdb->prepare( "UPDATE ".$this->_tbl_inventory_item." 
			SET bunit = bunit ".$plus." %s WHERE hid = %d AND did = %d " , $unit , $hid , $did );
		$update = $wpdb->query( $update_items_sql );
		//echo "<br />#### --- ".$update_items_sql;
		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Update Inventory Transaction Item Deduct Qty
	 */
	public function update_inventory_transaction_item_custom( $hid, $did , $type , $value , $plus = '+' ){
		global $wpdb;

		if ( ! $hid || ! $did || ! $type ) {
			return false;
		}
		switch ( $type ) {
			case 'deduct':
				$custom_update = $wpdb->prepare( "deduct_qty = deduct_qty ".$plus." %s, flag = IF( bqty = deduct_qty, 1, 0)", $value ) ;
				break;
			case 'deduct_unit':
				$custom_update = $wpdb->prepare( "deduct_unit = deduct_unit ".$plus." %s ", $value ) ;
				break;
			case 'cost':
				$custom_update = $wpdb->prepare( "total_cost = total_cost ".$plus." %s, unit_cost = IF(bqty = 0, 0.00, ( total_cost / bqty ) )", $value );
				break;
			case 'weighted_avg':
				$custom_update = $wpdb->prepare( "weighted_total = %s, weighted_price = %s ", $value['amount'], $value['price'] );
				break;
			default:
				return false;
				break;
		}
		$update_items_sql  = $wpdb->prepare( "UPDATE ".$this->_tbl_inventory_item." 
			SET ".$custom_update." WHERE hid = %d AND did = %d AND status = 1 " , $hid , $did );
		$update = $wpdb->query( $update_items_sql );
		//echo "<br />#@#@#@#@#@#@# --- ".$update_items_sql;
		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Deactive Inventory Transaction
	 */
	public function deactive_inventory_transaction( $cond , $type = "" ){
		global $wpdb;
		if ( ! $cond || empty ( $cond ) ) {
			return false;
		}

		switch ( $type ) {
			case 'header':
				$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_inventory." set status = 0 , lupdate_by = %d , lupdate_at = %s WHERE status != 0 " , $this->user_id , get_current_time('Y-m-d H:i:s') );
				break;
			case 'detail':
				$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_inventory_item." set status = 0 , deduct_qty = 0, deduct_unit = 0, unit_cost = 0, total_cost = 0, lupdate_by = %d , lupdate_at = %s WHERE status != 0 " , $this->user_id , get_current_time('Y-m-d H:i:s') );
				break;
			case 'ref':
				$update_items_sql = "UPDATE ".$this->_tbl_inventory_ref." set status = 0 WHERE status != 0 " ;
				break;
			default:
				break;
		}
		if( ! empty ( $cond ) ) 
		{
			$update_items_sql .= $cond;
		}
		//echo "<br /> DELETE ITEM---> ".$update_items_sql;
		$update = $wpdb->query( $update_items_sql );
		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	get list - for unlink (deletion)
	 */
	public function get_inventory_ref_child( $ref_hid , $ref_did ){
		global $wpdb;

		if ( ! $ref_hid || ! $ref_did ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT a.*, b.doc_post_date FROM ".$this->_tbl_inventory_ref." a
			INNER JOIN ".$this->_tbl_inventory." b ON a.hid = b.hid
			WHERE a.ref_hid = %d AND a.ref_did = %d AND a.status = 1 ", $ref_hid , $ref_did );
		$get_items_sql .= " ORDER BY b.doc_post_date ";

		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/*
	*	Seperate array to header/detail
	*/
	public function seperate_import_data( $file_data , $header_col , $header_unique , $detail_col )
	{
		$result_data = array();
		$header_data = array();
		$header_exist = array();
		$row_index = 0 ; $header_index = 0;

		for ( $i = 0; $i < count( $file_data ); $i++ ) {

			//check duplicate header
			$unique = array();
			for ( $j = 0; $j < count( $header_unique ); $j++ ) {
				$unique[$header_unique[$j]] = $file_data[$i][$header_unique[$j]];
			}
			$header_string = implode( '|' , $unique );
			if( array_key_exists( $header_string, $header_exist) ) {
				$header_index = $header_exist[$header_string];
			}
			else
			{
				$data = array();
				for ( $j = 0; $j < count( $header_col ); $j++ ) {
					$data[$header_col[$j]] = $file_data[$i][$header_col[$j]];
				}
				$result_data[$row_index]['header'] = $data ;
				$header_index = $row_index;
				$header_exist[$header_string] = $header_index; //header row index
				$row_index++;
			}
			$itm_data = array();
			for ( $j = 0; $j < count( $detail_col ); $j++ ) {
				$itm_data[$detail_col[$j]] = $file_data[$i][$detail_col[$j]];
			}
			$result_data[$header_index]['detail'][] = $itm_data ;
		}
		return $result_data;
	}
	/**
	 *	Get Stock Out Type - V1.0.9
	 */
	public function get_stock_out_type( $id ){
		global $wpdb;

		if ( ! $id ) {
			$id = 1;
		}
		if( ! isset( $this->arr_stockout_type ) )
		{
			$arr_stockout_type = array();
			$get_items_sql  = $wpdb->prepare( "SELECT ref_id, order_type, ordering, default_value FROM ".$this->_tbl_stockout_method." WHERE ref_id = %d ORDER BY ref_id, priority ASC", $id );

			$result = $wpdb->get_results( $get_items_sql , ARRAY_A );
			foreach ( $result as $item )
			{
				$offset_ord = $item['ordering'] == "ASC" ? "DESC" : "ASC";
				$connector = empty( $arr_stockout_type[$item['ref_id']]['normal']) ? "" : ",";
				$arr_stockout_type[$item['ref_id']]['normal'] .= $connector.$item['order_type']." ".$item['ordering'] ;
				$arr_stockout_type[$item['ref_id']]['offset'] .= $connector.$item['order_type']." ".$offset_ord ;
				$arr_stockout_type[$item['ref_id']][$item['order_type']] = $item ;
			}
			$this->arr_stockout_type = $arr_stockout_type;
		}
		return isset( $this->arr_stockout_type[$id] ) ? $this->arr_stockout_type[$id] : $this->arr_stockout_type[1];
	}

	/**
	 *	Itemize Handler
	 */
	public function itemize_handler( $items, $detail_item = array() )
	{
		if( ! $items || ! $detail_item || ! $detail_item['did'] || empty( $detail_item['plus_sign'] ) ) return false;
		$succ = true;

		foreach( $items as $i => $item )
		{
			if( $item['line_id'] ) unset( $item['line_id'] );

			if( $detail_item['plus_sign'] == '-' )
				$item['out_did'] = $detail_item['did'];
			else if( $detail_item['plus_sign'] == '+' )
				$item['in_did'] = $detail_item['did'];

			if( $item['id'] )
			{
				$exist = $this->get_itemize_item( $item['id'] );
				if( ! $exist ) $succ = false;

				if( $succ )
				{
					if( ! $this->update_itemize_item( array( 'id' => $exist['id'] ) , $item ) )
						$succ = false;
				}
			}
			else
			{
				$line_item = wp_parse_args( $item, $this->itemize_defaults );

				//Add New Record
				$line_id = $this->add_itemize_item( $line_item );
				if( ! $line_id )
					$succ = false;
				
				$item['id'] = $line_id;
			}
		}

		return $succ;
	}
	/**
	 *	Get Itemize
	 */
	public function get_itemize_item( $id = 0, $args = array() ){
		global $wpdb;

		if( ! $id && ! $args ) return false;

		$fld = " * "; 
		$table = "{$this->_tbl_itemize} ";
		$cond = $wpdb->prepare(" WHERE %d ", 1 );
		
		if( $id > 0 && $this->primary_key )
		{
			$cond .= $wpdb->prepare(" AND id = %d ", $id );
		}
		
		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				if( is_array( $val ) )
					$cond .= " AND {$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND {$key} = %s ", $val );
			}
		}

		$sql = "SELECT {$fld} FROM {$table} {$cond} ;";

		return $wpdb->get_results( $sql , ARRAY_A );
	}
	/**
	 *	Add Itemize Item
	 */
	public function add_itemize_item( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_itemize,
			array(
				'product_id' 		=> $item['product_id'],
				'_sku' 				=> $item['_sku'],
				'in_did' 			=> $item['in_did'],
				'out_did'			=> $item['out_did'],
				'code'				=> $item['code'],
				'serial'			=> $item['serial'],
				'desc'				=> $item['desc'],
				'bunit' 			=> $item['bunit'],
				'unit_cost' 		=> $item['unit_cost'],
				'unit_price' 		=> $item['unit_price'],
				'expiry'			=> $item['expiry'],
				'status' 			=> $item['status'],
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Update Itemize Item
	 */
	public function update_itemize_item( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_itemize, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
}

#-----------------------------------------------------------------#
#	>	Example usage
#-----------------------------------------------------------------#
new WC_InventoryTrans();
/*function testing4(){
	echo "IV POSTING START --->>>> <br />"	;
/*
	//GR Trans - Save
	$header = array(
		'docno'  			=> 'GR0001',  //Document No
		'doc_id' 			=> '94', //Document Post ID
		'doc_type' 			=> 'receive',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-01',  //Inventory In/Out Date
		'plus_sign'			=> '+',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '185', //Document item ID
		'product_id' 		=> '11',
		'warehouse_id' 		=> '1', 
		'strg_id'			=> '0',
		'bqty' 				=> '100', //Qty
		//'unit_cost' 		=> '0.00', //Cost
		//'total_cost' 		=> '0.00', //Cost
		'unit_price' 		=> '18.00', //Price
		'total_price' 		=> '1800.00', //Price
	);	
	$detail[] = $item;

	$item = array(
		'item_id' 			=> '186', //Document item ID
		'product_id' 		=> '46', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '120', //Qty
		'unit_price' 		=> '20.00', //Price
		'total_price' 		=> '2400.00', //Price
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />GR IN 1 Success ";
	else 
		echo "<br />GR IN 1 FAIL ";

	//GR Trans - Save 2
	$header = array(
		'docno'  			=> 'GR0003',  //Document No
		'doc_id' 			=> '101', //Document Post ID
		'doc_type' 			=> 'receive',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-03',  //Inventory In/Out Date
		'plus_sign'			=> '+',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '201', //Document item ID
		'product_id' 		=> '11',
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '200', //Qty
		'unit_price' 		=> '20.00', //Price
		'total_price' 		=> '4000.00', //Price
	);	
	$detail[] = $item;

	$item = array(
		'item_id' 			=> '202', //Document item ID
		'product_id' 		=> '46', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '300', //Qty
		'unit_price' 		=> '25.00', //Price
		'total_price' 		=> '7500.00', //Price
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />GR IN 2 Success ";
	else 
		echo "<br />GR IN 2 FAIL ";

	//DO 1 - Save
	$header = array(
		'docno'  			=> 'DO0001',  //Document No
		'doc_id' 			=> '112', //Document Post ID
		'doc_type' 			=> 'delivery',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-06',  //Inventory In/Out Date
		'plus_sign'			=> '-',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '211', //Document item ID
		'product_id' 		=> '11', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '10', //Qty
		'unit_price' 		=> '30.00', //Price
		'total_price' 		=> '300.00', //Price
	);	
	$detail[] = $item;

	$item = array(
		'item_id' 			=> '212', //Document item ID
		'product_id' 		=> '46', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '10', //Qty
		'unit_price' 		=> '40.00', //Price
		'total_price' 		=> '400.00', //Price
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />DO 1 Success ";
	else 
		echo "<br />DO 1 FAIL ";

	//DO 1 - UPdate
	$header = array(
		'docno'  			=> 'DO0001',  //Document No
		'doc_id' 			=> '112', //Document Post ID
		'doc_type' 			=> 'delivery',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-06',  //Inventory In/Out Date
		'plus_sign'			=> '-',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '211', //Document item ID
		'product_id' 		=> '11', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '120', //Qty
		'unit_price' 		=> '40.00', //Price
		'total_price' 		=> '4800.00', //Price
	);	
	$detail[] = $item;

	$item = array(
		'item_id' 			=> '212', //Document item ID
		'product_id' 		=> '46', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '80', //Qty
		'unit_price' 		=> '20.00', //Price
		'total_price' 		=> '1600.00', //Price
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />DO 1 Update Success ";
	else 
		echo "<br />DO 1 Update FAIL ";


	//GR Trans 1 - Update Item 1 Less Qty
	$header = array(
		'docno'  			=> 'GR0001',  //Document No
		'doc_id' 			=> '94', //Document Post ID
		'doc_type' 			=> 'receive',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-01',  //Inventory In/Out Date
		'plus_sign'			=> '+',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '185', //Document item ID
		'product_id' 		=> '11',
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '80', //Qty
		'unit_price' 		=> '18.00', //Price
		'total_price' 		=> '1440.00', //Price
	);	
	$detail[] = $item;
	$item = array(
		'item_id' 			=> '186', //Document item ID
		'product_id' 		=> '46', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '120', //Qty
		'unit_price' 		=> '20.00', //Price
		'total_price' 		=> '2400.00', //Price
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />GR IN 1 Update Success ";
	else 
		echo "<br />GR IN 1 Update FAIL ";


	//DO 1 - UPdate 2 - Remove 2nd Item
	$header = array(
		'docno'  			=> 'DO0001',  //Document No
		'doc_id' 			=> '112', //Document Post ID
		'doc_type' 			=> 'delivery',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-06',  //Inventory In/Out Date
		'plus_sign'			=> '-',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '211', //Document item ID
		'product_id' 		=> '11', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '120', //Qty
		'unit_price' 		=> '40.00', //Price
		'total_price' 		=> '4800.00', //Price
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />DO 1 Update 2 Success ";
	else 
		echo "<br />DO 1 Update 2 FAIL ";


	//DO 1 - UPdate 2 - Remove 1st Item
	$header = array(
		'docno'  			=> 'GR0001',  //Document No
		'doc_id' 			=> '94', //Document Post ID
		'doc_type' 			=> 'receive',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-01',  //Inventory In/Out Date
		'plus_sign'			=> '+',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '186', //Document item ID
		'product_id' 		=> '46', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '120', //Qty
		'unit_price' 		=> '20.00', //Price
		'total_price' 		=> '2400.00', //Price
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />GR IN 1 Update 2 Success ";
	else 
		echo "<br />GR IN 1 Update 2 FAIL ";


	//DO 1 - Delete
	$header = array(
		'docno'  			=> 'GR0001',  //Document No
		'doc_id' 			=> '94', //Document Post ID
		'doc_type' 			=> 'receive',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-01',  //Inventory In/Out Date
		'plus_sign'			=> '+',  //Debit/Credit
	);
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'delete' , $header, $detail );
	if( $succ )
		echo "<br />GR IN 1 Delete Success ";
	else 
		echo "<br />GR IN 1 Delete FAIL ";

	echo "<br /> Processed Data : ";
	//$succ = $inv_trans->inventory_transaction_data_handle ( 'save', 'delivery' , 112 );
	$doc_id = 112;
	//$succ = apply_filters ('warehouse_inventory_transaction_filter', 'save', 'delivery' , $doc_id );
	//$succ = apply_filters ('warehouse_inventory_transaction_filter', 'delete', 'delivery' , $doc_id );


	//DO 3
	$header = array(
		'docno'  			=> 'DO0003',  //Document No
		'doc_id' 			=> '120', //Document Post ID
		'doc_type' 			=> 'delivery',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2016-09-09',  //Inventory In/Out Date
		'plus_sign'			=> '-',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '221', //Document item ID
		'product_id' 		=> '46', //Document Post ID
		'warehouse_id' 		=> '1', 
		'bqty' 				=> '150', //Qty
		'unit_price' 		=> '40.00', //Price
		'total_price' 		=> '6000.00', //Price
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />GR IN 1 Delete Success ";
	else 
		echo "<br />GR IN 1 Delete FAIL ";

	//GR Trans - Save 4
	$header = array(
		'docno'  			=> 'GR00000008',  //Document No
		'doc_id' 			=> '77', //Document Post ID
		'doc_type' 			=> 'receive',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2017-01-24',  //Inventory In/Out Date
		'plus_sign'			=> '+',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '148', //Document item ID
		'product_id' 		=> '70',
		'warehouse_id' 		=> '67', 
		'bqty' 				=> '100', //Qty
		'unit_price' 		=> '55.56', //Price
		'total_price' 		=> '6667.20', //Price
		'stock_out_type'	=> 2
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'delete' , $header, $detail );
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />GR IN 2 Success ";
	else 
		echo "<br />GR IN 2 FAIL ";

	//DO 4 
	$header = array(
		'docno'  			=> 'DO00000008',  //Document No
		'doc_id' 			=> '81', //Document Post ID
		'doc_type' 			=> 'delivery',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
		'doc_post_date' 	=> '2017-01-25',  //Inventory In/Out Date
		'plus_sign'			=> '-',  //Debit/Credit
	);
	$detail = array();
	$item = array(
		'item_id' 			=> '154', //Document item ID
		'product_id' 		=> '70', //Document Post ID
		'warehouse_id' 		=> '67', 
		'bqty' 				=> '70', //Qty
		'stock_out_type'	=> 2
	);	
	$detail[] = $item;
	$inv_trans = new WC_InventoryTrans();
	//$succ = $inv_trans->inventory_transaction_handle( 'save' , $header, $detail );
	if( $succ )
		echo "<br />DO 4 Update 2 Success ";
	else 
		echo "<br />DO 4 Update 2 FAIL ";
	//results_table ( $inv_trans->get_outstanding_inventory_transaction_item( '46', '1', '+' ));
}*/
?>