<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * MOMAFnl_Stocks
 *
 * @class    WC_Warehouse_Stocks
 * Note: Transfer Scenario - KIV
 *       Scenario for Posting & Unpost. For partial closed Doc, may need trigger unpost and post with new qty amount
 */

class WC_Warehouse_Stocks extends WCWH_CRUD_Controller
{

	public $version = '1.0.0';
	private $_succ = false;
	protected $_tbl_warehouse_stocks = 'wc_warehouse_stocks';
	protected $_tbl_warehouse_stocks_sprice = 'wc_stocks_selling_price';
	
	private $_tbl_inventory = 'inventory_transaction';
	private $_tbl_inventory_item = 'inventory_transaction_items';

	private $_tbl_doc = '';
	private $_tbl_doc_item = '';
	
	private $_doc_type = "";
	/*
	 *	Default Data for submission
	 */
	protected $stocks_defaults = array();
	protected $item_sprice_defaults = array();

	/**
	 * Constructor 
	 */
	public function __construct() 
	{
		parent::__construct();
		
		global $wcwh;
		$prefix = $this->get_prefix();
		$this->_tbl_inventory = $prefix."tx_transaction";
		$this->_tbl_inventory_item = $prefix."tx_transaction_items";
		
		$this->_tbl_warehouse_stocks = $prefix."tx_inventory";
		$this->_tbl_warehouse_stocks_sprice = $prefix."selling_price";

		$this->_tbl_doc = $prefix."document";
		$this->_tbl_doc_item = $prefix."document_items";

		$this->stocks_defaults = array(
			'warehouse_id' 		=> '', //Warehouse
			'strg_id'			=> '0',
			'prdt_id'  			=> '',  //Product
			'qty' 				=> '0', //On hand Qty
			//'allocated_qty' 	=> '1', //Default 1
			'unit'				=> '0',
			//'total_cost' 		=> '',
			//'mavg_cost' 		=> '',
			//'total_sales_qty' 		=> '',
			//'total_sales_amount' 		=> '',
			//'mavg_sprice' 		=> ''
		);
		$this->item_sprice_defaults = array(
			'sales_item_id'   	=> '0', //Sales Order Item ID
			'order_id'			=> '0',
			'docno'				=> '',
			'warehouse_id' 		=> '', //Warehouse ID
			'strg_id'			=> '',
			'customer' 			=> '',  //Customer -> Registed User ID?
			'sales_date' 		=> '0', //Sales Date
			'tool_id'			=> '0', //Tool item id
			'prdt_id' 			=> '0', //Product 
			'uom'				=> '',
			'qty' 				=> '0', //Sales Qty
			'uprice' 			=> '0', //Sales Price
			'price'				=> '0',
			'total_amount' 		=> '0',	//Sales Amount
			'status' 			=> '1', //Sales Price
		);
		$this->user_id = get_current_user_id();
		$this->init_hooks();
	}
	/**
	 * init hooks
	 */
	private function init_hooks() {
		add_shortcode( 'testing_warehouse_stocks' , 'warehouse_testing' );
		if(! has_filter('document_post_warehouse_stocks_data_handle') )
		{
			add_filter( 'document_post_warehouse_stocks_data_handle', array( $this, 'warehouse_stocks_data_handle' ) ,5 , 4 ); // V1.0.2
		}
		if(! has_filter('warehouse_stocks_action_filter') )
		{
			add_filter( 'warehouse_stocks_action_filter', array( $this , 'warehouse_stocks_action_handle') ,5 , 3 );
		}
		if(! has_filter('warehouse_stocks_sprice_action_filter') )
		{
			add_filter( 'warehouse_stocks_sprice_action_filter', array( $this , 'warehouse_stocks_sprice_action_handle') ,5 , 2 );
		}
		if(! has_filter('warehouse_stocks_get_warehouse_items') )
		{
			add_filter( 'warehouse_stocks_get_warehouse_items', array( $this , 'get_warehouse_items') ,5 , 3 );
		}
		if(! has_filter('warehouse_stocks_get_warehouse_item') )
		{
			add_filter( 'warehouse_stocks_get_warehouse_item', array( $this , 'get_warehouse_item') ,5 , 3 );
		}
		if(! has_filter( 'warehouse_stocks_update_qty' ) )
		{
			add_filter( 'warehouse_stocks_update_qty', array( $this, 'update_warehouse_stocks_qty' ), 5, 7 );
		}
		if(! has_filter( 'warehouse_get_items_selling_price' ) )
		{
			add_filter( 'warehouse_get_items_selling_price', array( $this, 'get_warehouse_items_selling_price' ), 10, 3 );
		}
		if(! has_filter( 'warehouse_get_items_selling_price_by_order' ) )
		{
			add_filter( 'warehouse_get_items_selling_price_by_order', array( $this, 'get_warehouse_items_selling_price_by_order' ), 10, 2 );
		}
	}
	#-----------------------------------------------------------------#
	#	>	MOMAFnl Stocks Functions
	#-----------------------------------------------------------------#	
	/**
	 *	Data Handle
	 */
	public function warehouse_stocks_data_handle( $action , $type , $doc_id = array() , $item_id = array() )
	{
		if( ! $type || ! $action || ( count( $doc_id ) == 0 && count( $item_id ) == 0 ) )
			return false;
		$succ = true;
		global $wpdb;
		switch( $type )
		{
			case 'reprocess':
			case 'good_receive':
			case 'block_stock':
			case 'transfer_item':
			case 'do_revise':
				$get_sql_item =  $wpdb->prepare( "SELECT b.warehouse_id, b.strg_id, b.product_id as prdt_id, b.bqty as qty, b.bunit as unit, b.unit_price as uprice, b.total_price as total_amount, b.plus_sign, b.weighted_price, b.weighted_total
									FROM {$this->_tbl_inventory} a
									INNER JOIN {$this->_tbl_inventory_item} b ON b.hid = a.hid AND b.status != 0 
									WHERE a.doc_type = %s AND a.status != 0 " , $type );
			break;
			case 'good_issue':
			case 'good_return':
			case 'delivery_order':
			case 'sale_order':
			case 'allocation':
			case 'release':
			case 'block_action':
				$get_sql_item =  $wpdb->prepare( "SELECT b.warehouse_id, b.strg_id, b.product_id as prdt_id, b.bqty as qty, b.bunit as unit, b.unit_cost as uprice, b.total_cost as total_amount, b.plus_sign, b.weighted_price, b.weighted_total
									FROM {$this->_tbl_inventory} a
									INNER JOIN {$this->_tbl_inventory_item} b ON b.hid = a.hid AND b.status != 0 
									WHERE a.doc_type = %s AND a.status != 0 " , $type );
			break;
			case 'stock_adjust':
			case 'stocktake':
			case 'pos_transactions':
				$get_sql_item =  $wpdb->prepare( "SELECT b.warehouse_id, b.strg_id, b.product_id as prdt_id, b.bqty as qty, b.bunit as unit, IF(b.plus_sign = '+', b.unit_price, b.unit_cost) as uprice, IF(b.plus_sign = '+',b.total_price, b.total_cost) as total_amount , b.plus_sign, b.weighted_price, b.weighted_total
									FROM {$this->_tbl_inventory} a
									INNER JOIN {$this->_tbl_inventory_item} b ON b.hid = a.hid AND b.status != 0 
									WHERE a.doc_type = %s AND a.status != 0 " , $type );
			break;
			case 'purchase_debit_note':
			case 'purchase_credit_note':
				$get_sql_item =  $wpdb->prepare( "SELECT b.warehouse_id, b.strg_id, b.product_id as prdt_id, b.bqty as qty, b.bunit as unit, b.unit_cost as uprice, b.total_cost as total_amount, b.plus_sign, b.weighted_price, b.weighted_total
									FROM {$this->_tbl_inventory} a
									INNER JOIN {$this->_tbl_inventory_item} b ON b.hid = a.hid AND b.status != 0 
									WHERE a.doc_type = %s AND a.status != 0 " , $type );
			break;
			default:
				$succ = false;
			break;
		}
		if ( ! $get_sql_item )
			$succ = false;
		else 
		{
			if( count( $doc_id ) > 0 )
			{
				$get_sql_item .= " AND a.doc_id IN ( " . implode( ',', $doc_id ) . ") ";
			}
			if( count( $item_id ) > 0 )
			{
				$get_sql_item .= " AND b.item_id IN ( " . implode( ',', $item_id ) . ") ";
			}
			$result_data = $wpdb->get_results( $get_sql_item , ARRAY_A );
			if( ! $result_data )
				$succ = false;
			else 
			{
			 	//wpdb_start_transaction ();
			 	$arr_item = array();
				foreach ($result_data as $item) {
			 		$arr_item[] = $item;
				}
				$succ = $this->warehouse_stocks_action_handle( $action , $type , $arr_item );
				//wpdb_end_transaction($succ);
			}
		}
		//echo "<br />".$succ."-----------------"; exit;
		return $succ;
	}
	/**
	 *	Action Handle
	 */
	public function warehouse_stocks_action_handle( $action , $type , $details  = array() )
	{
		$succ = true;
		if( count( $details ) == 0 )
			$succ = false;
		else 
		{	
			$arr_prdt_id = array();
			$arr_prdt = array();
			$exists_item = array();
			$warehouse_id = $details[0]['warehouse_id'];
			$strg_id = ( $details[0]['strg_id'] )? $details[0]['strg_id'] : 0;
			//Get Existing Records.
			foreach ($details as $item) 
			{
				$arr_prdt[ $item['prdt_id'] ] = $item['prdt_id'];
			}
			$result_item = $this->get_warehouse_items( $warehouse_id, $strg_id , $arr_prdt );
			if( $result_item )
			{
				foreach ($result_item as $item) 
				{
					$exists_item[ $item['prdt_id'] ] = $item;
				}	
			}
			//wpdb_start_transaction ();
			switch ( strtolower( $action ) )
			{
				case "save":
				case "update":
					//Start Update
					foreach ($details as $item) 
					{
						if( ! isset ( $exists_item[ $item['prdt_id'] ] ) )
						{
							$temp = $item;
							$temp['qty'] = 0;
							$temp['unit'] = 0;
							$temp['total_amount'] = 0;
							$temp['mavg_cost'] = 0;
							$id = $this->add_warehouse_stocks( $temp );
							if( ! $id )
							{
								$succ = false;
								break;
							}
							$item['id'] = $id; //V1.0.5
							$exists_item[ $item['prdt_id'] ] = $item;//V1.0.5
						}

						if( isset ( $exists_item[ $item['prdt_id'] ] ) )
						{
							$plus_sign = ( !empty( $item['plus_sign'] ) )? $item['plus_sign'] : "+";
							$succ = $this->update_warehouse_stocks_qty( $type, $warehouse_id, $strg_id, $item['prdt_id'], $item, $plus_sign );
							if( ! $succ )
							{
								break;
							}
							$arr_prdt_id[ $item['prdt_id'] ] = $item['prdt_id'];
						}
					}
				break;
				case "delete":
					//Delete: Offset qty only, not delete record items.
					//Start Delete
					foreach ($details as $item) 
					{
						if( isset ( $exists_item[ $item['prdt_id'] ] ) )
						{
							$plus_sign = $ps = ( !empty( $item['plus_sign'] ) )? $item['plus_sign'] : "+";
							$plus_sign = ( $plus_sign === "+" )? "-" : "+";
							$succ = $this->update_warehouse_stocks_qty( $type, $warehouse_id, $strg_id, $item['prdt_id'], $item, $plus_sign, $ps );
							if( ! $succ )
							{
								break;
							}
							$arr_prdt_id[ $item['prdt_id'] ] = $item['prdt_id'];
						}
						else
						{
							//Invalid MOMAfnl for deletion items.
							$succ = false; 
							break;
						}
					}
				break;
				default:
					$succ = false;
			}
			//echo "<br />".$succ."-----------------"; exit;
			//wpdb_end_transaction($succ);
		}
		return $succ;
	}
	/**
	 *	Add Moma fnl Stocks
	 */
	public function add_warehouse_stocks( $item ){
		global $wpdb;
		$item['mavg_cost'] = $item['qty'] > 0 ? round_to( $item['total_amount'] / $item['qty'] , 2 ) : 0;
		$item['mavg_cost'] = $item['unit'] > 0 ? round_to( $item['total_amount'] / $item['unit'] , 2 ) : 0;
		$wpdb->insert(
			$this->_tbl_warehouse_stocks,
			array(
				'warehouse_id' 		=> $item['warehouse_id'],
				'strg_id'			=> $item['strg_id'],
				'prdt_id' 			=> $item['prdt_id'],
				'qty' 				=> $item['qty'],
				'unit'				=> $item['unit'],
				'total_cost' 		=> $item['total_amount'],
				'mavg_cost' 		=> $item['mavg_cost'] 
			)
		);
		$item_id = absint( $wpdb->insert_id );
		
		//idw_added - to allow stock in out hooking
		if( $item_id ) $succ = true;
		
		return $item_id;
	}
	/**
	 *	Update Moma fnl Stocks
	 */
	public function update_warehouse_stocks( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_warehouse_stocks, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Update Used On Qty, Cost - Single Update
	 */
	public function update_warehouse_stocks_qty( $type, $warehouse_id, $strg_id = 0, $prdt_id = 0, $ditem = [], $plus = "+", $def_sign = "" )
	{
		global $wpdb;
		if ( ! $warehouse_id || ! $prdt_id || ! isset( $ditem['qty'] ) || ! isset( $ditem['total_amount'] ) ) {
			return false;
		}

		$exist = $this->get_warehouse_item( $warehouse_id, $prdt_id, $strg_id );
		if( $exist === null || ! $exist )
		{
			$temp = array(
				'warehouse_id' => $warehouse_id,
				'strg_id' => $strg_id,
				'prdt_id' => $prdt_id,
				'qty' => 0,
				'unit' => 0,
				'total_amount' => 0,
				'mavg_cost' => 0,
			);
			$id = $this->add_warehouse_stocks( $temp );
		}

		switch ( $type ) {
			case 'stock_adjust':
			case 'stocktake':
			case 'pos_transactions':
				if( ! empty( $def_sign ) )
				{
					if( $plus == "-" && $def_sign == "+" )
					{
						$update_fld = $wpdb->prepare( " qty = qty ".$plus." %s, unit = unit ".$plus." %s, total_cost = total_cost ".$plus." %s
								, mavg_cost = IF( qty = 0, 0, ROUND( total_cost / qty, 5 ) )
								, total_in = total_in ".$plus." %s, total_in_unit = total_in_unit ".$plus." %s
								, total_in_cost = total_in_cost ".$plus." %s
								, total_in_avg = IF( total_in = 0, 0, ROUND( total_in_cost / total_in, 5 ) ) 
								, wa_qty = wa_qty ".$plus." %s, wa_unit = wa_unit ".$plus." %s, wa_amt = wa_amt ".$plus." %s 
								, wa_price = IF( wa_amt = 0, 0, ROUND( wa_amt / wa_qty, 5 ) ) 
								, wa_last_price = IF( wa_amt <= 0, wa_last_price, ROUND( wa_amt / wa_qty, 5 ) ) "
							, $ditem['qty'], $ditem['unit'], $ditem['total_amount']
							, $ditem['qty'], $ditem['unit'], $ditem['weighted_total'] 
							, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
						);
					}
					else if( $plus == "+" && $def_sign == "-" )
					{
						$qty_sign = ( $plus === "-" )? "+" : "-";
						$update_fld = $wpdb->prepare( " qty = qty ".$plus." %s, unit = unit ".$plus." %s, total_cost = total_cost ".$plus." %s 
								, mavg_cost = IF( qty = 0, 0, ROUND( total_cost / qty, 5 ) )
								, total_out = total_out ".$qty_sign." %s, total_out_unit = total_out_unit ".$qty_sign." %s
								, total_out_cost = total_out_cost ".$qty_sign." %s
								, total_out_avg = IF( total_out = 0, 0, ROUND( total_out_cost / total_out, 5 ) ) 
								, wa_qty = wa_qty ".$plus." %s, wa_unit = wa_unit ".$plus." %s, wa_amt = wa_amt ".$plus." %s 
								, wa_price = IF( wa_amt = 0, 0, ROUND( wa_amt / wa_qty, 5 ) ) 
								, wa_last_price = IF( wa_amt <= 0, wa_last_price, ROUND( wa_amt / wa_qty, 5 ) ) "
							, $ditem['qty'], $ditem['unit'], $ditem['total_amount']
							, $ditem['qty'], $ditem['unit'], $ditem['weighted_total'] 
							, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
						);
					}
				}
				else
				{
					if( $plus == "+" )
					{
						$update_fld = $wpdb->prepare( " qty = qty ".$plus." %s, unit = unit ".$plus." %s, total_cost = total_cost ".$plus." %s 
								, mavg_cost = IF( qty = 0, 0, ROUND( total_cost / qty, 5 ) )
								, total_in = total_in ".$plus." %s, total_in_unit = total_in_unit ".$plus." %s
								, total_in_cost = total_in_cost ".$plus." %s
								, total_in_avg = IF( total_in = 0, 0, ROUND( total_in_cost / total_in, 5 ) ) 
								, wa_qty = wa_qty ".$plus." %s, wa_unit = wa_unit ".$plus." %s, wa_amt = wa_amt ".$plus." %s 
								, wa_price = IF( wa_amt = 0, 0, ROUND( wa_amt / wa_qty, 5 ) ) 
								, wa_last_price = IF( wa_amt <= 0, wa_last_price, ROUND( wa_amt / wa_qty, 5 ) ) "
							, $ditem['qty'], $ditem['unit'], $ditem['total_amount']
							, $ditem['qty'], $ditem['unit'], $ditem['weighted_total'] 
							, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
						);
					}
					else if( $plus == "-" )
					{
						$qty_sign = ( $plus === "-" )? "+" : "-";
						$update_fld = $wpdb->prepare( " qty = qty ".$plus." %s, unit = unit ".$plus." %s, total_cost = total_cost ".$plus." %s 
								, mavg_cost = IF( qty = 0, 0, ROUND( total_cost / qty, 5 ) )
								, total_out = total_out ".$qty_sign." %s, total_out_unit = total_out_unit ".$qty_sign." %s
								, total_out_cost = total_out_cost ".$qty_sign." %s
								, total_out_avg = IF( total_out = 0, 0, ROUND( total_out_cost / total_out, 5 ) ) 
								, wa_qty = wa_qty ".$plus." %s, wa_unit = wa_unit ".$plus." %s, wa_amt = wa_amt ".$plus." %s 
								, wa_price = IF( wa_amt = 0, 0, ROUND( wa_amt / wa_qty, 5 ) ) 
								, wa_last_price = IF( wa_amt <= 0, wa_last_price, ROUND( wa_amt / wa_qty, 5 ) ) "
							, $ditem['qty'], $ditem['unit'], $ditem['total_amount']
							, $ditem['qty'], $ditem['unit'], $ditem['weighted_total'] 
							, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
						);
					}
				}
			break;
			//-------------------------------IN +
			case 'reprocess':
			case 'good_receive':
			case 'block_stock':
			case 'transfer_item':
			case 'do_revise':
				$update_fld = $wpdb->prepare( " qty = qty ".$plus." %s, unit = unit ".$plus." %s, total_cost = total_cost ".$plus." %s 
						, mavg_cost = IF( qty = 0, 0, ROUND( total_cost / qty, 5 ) )
						, total_in = total_in ".$plus." %s, total_in_unit = total_in_unit ".$plus." %s
						, total_in_cost = total_in_cost ".$plus." %s
						, total_in_avg = IF( total_in = 0, 0, ROUND( total_in_cost / total_in, 5 ) ) 
						, wa_qty = wa_qty ".$plus." %s, wa_unit = wa_unit ".$plus." %s, wa_amt = wa_amt ".$plus." %s 
						, wa_price = IF( wa_amt = 0, 0, ROUND( wa_amt / wa_qty, 5 ) ) 
						, wa_last_price = IF( wa_amt <= 0, wa_last_price, ROUND( wa_amt / wa_qty, 5 ) ) "
					, $ditem['qty'], $ditem['unit'], $ditem['total_amount']
					, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
					, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
				);
			break;
			//-------------------------------OUT -
			case 'delivery_order':
			case 'good_issue':
			case 'good_return':
			case 'block_action':
				$qty_sign = ( $plus === "+" )? "-" : "+";
				$update_fld = $wpdb->prepare( " qty = qty ".$plus." %s, unit = unit ".$plus." %s, total_cost = total_cost ".$plus." %s 
						, mavg_cost = IF( qty = 0, 0 , ROUND( total_cost / qty, 5 ) ) 
						, total_out = total_out ".$qty_sign." %s, total_out_unit = total_out_unit ".$qty_sign." %s
						, total_out_cost = total_out_cost ".$qty_sign." %s
						, total_out_avg = IF( total_out = 0, 0 , ROUND( total_out_cost / total_out, 5 ) ) 
						, wa_qty = wa_qty ".$plus." %s, wa_unit = wa_unit ".$plus." %s, wa_amt = wa_amt ".$plus." %s 
						, wa_price = IF( wa_amt = 0, 0, ROUND( wa_amt / wa_qty, 5 ) ) 
						, wa_last_price = IF( wa_amt <= 0, wa_last_price, ROUND( wa_amt / wa_qty, 5 ) ) "
					, $ditem['qty'], $ditem['unit'], $ditem['total_amount']
					, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
					, $ditem['qty'], $ditem['unit'], $ditem['weighted_total'] 
				);
			break;
			//----------------------------------------------Debit Credit
			case 'purchase_debit_note':		//-
				$qty_sign = ( $plus === "+" )? "-" : "+";
				$update_fld = $wpdb->prepare( " qty = qty ".$plus." %s, unit = unit ".$plus." %s, total_cost = total_cost ".$plus." %s 
						, mavg_cost = IF( qty = 0, 0 , ROUND( total_cost / qty, 5 ) ) 
						, total_out = total_out ".$qty_sign." %s, total_out_unit = total_out_unit ".$qty_sign." %s
						, total_out_cost = total_out_cost ".$qty_sign." %s
						, total_out_avg = IF( total_out = 0, 0 , ROUND( total_out_cost / total_out, 5 ) ) 
						, wa_qty = wa_qty ".$plus." %s, wa_unit = wa_unit ".$plus." %s, wa_amt = wa_amt ".$plus." %s 
						, wa_price = IF( wa_amt = 0, 0, ROUND( wa_amt / wa_qty, 5 ) ) 
						, wa_last_price = IF( wa_amt <= 0, wa_last_price, ROUND( wa_amt / wa_qty, 5 ) ) "
					, $ditem['qty'], $ditem['unit'], $ditem['total_amount']
					, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
					, $ditem['qty'], $ditem['unit'], $ditem['weighted_total'] 
				);
			break;
			case 'purchase_credit_note':	//+
				$update_fld = $wpdb->prepare( " qty = qty ".$plus." %s, unit = unit ".$plus." %s, total_cost = total_cost ".$plus." %s 
						, mavg_cost = IF( qty = 0, 0, ROUND( total_cost / qty, 5 ) )
						, total_in = total_in ".$plus." %s, total_in_unit = total_in_unit ".$plus." %s
						, total_in_cost = total_in_cost ".$plus." %s
						, total_in_avg = IF( total_in = 0, 0, ROUND( total_in_cost / total_in, 5 ) ) 
						, wa_qty = wa_qty ".$plus." %s, wa_unit = wa_unit ".$plus." %s, wa_amt = wa_amt ".$plus." %s 
						, wa_price = IF( wa_amt = 0, 0, ROUND( wa_amt / wa_qty, 5 ) ) 
						, wa_last_price = IF( wa_amt <= 0, wa_last_price, ROUND( wa_amt / wa_qty, 5 ) ) "
					, $ditem['qty'], $ditem['unit'], $ditem['total_amount']
					, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
					, $ditem['qty'], $ditem['unit'], $ditem['weighted_total']
				);
			break;
			//----------------------------------------------Others
			case 'sales':	//POS sales / refund
				$qty_sign = ( $plus === "+" )? "-" : "+";
				$update_fld = $wpdb->prepare( " allocated_qty = allocated_qty ".$plus." %s, total_sales_qty = total_sales_qty ".$plus." %s
						, allocated_unit = allocated_unit ".$plus." %s, total_sales_unit = total_sales_unit ".$plus." %s 
						, total_sales_amount = total_sales_amount ".$plus." %s
						, mavg_sprice = IF( total_sales_qty = 0, 0 , ROUND( total_sales_amount / total_sales_qty, 5 ) ) "
					, $ditem['qty'], $ditem['qty'], $ditem['unit'], $ditem['unit'], $ditem['total_amount'] 
				);
			break;
			case 'sale_order':	//SO post / unpost
				$update_fld = $wpdb->prepare( " total_sales_qty = total_sales_qty ".$plus." %s, total_sales_unit = total_sales_unit ".$plus." %s
						, total_sales_amount = total_sales_amount ".$plus." %s
						, mavg_sprice = IF( total_sales_qty = 0, 0 , ROUND( total_sales_amount / total_sales_qty, 5 ) ) "
					, $ditem['qty'], $ditem['unit'], $ditem['total_amount'] 
				);
			break;
			case 'allocation':
				$update_fld = $wpdb->prepare( " reserved_qty = reserved_qty ".$plus." %s, reserved_unit = reserved_unit ".$plus." %s "
					, $ditem['qty'], $ditem['unit'] 
				);
			break;
			case 'release':
				$plus = ( $plus === "+" )? "-" : "+";
				$update_fld = $wpdb->prepare( " reserved_qty = reserved_qty ".$plus." %s, reserved_unit = reserved_unit ".$plus." %s "
					, $ditem['qty'], $ditem['unit'] 
				);
			break;
			default:
				return false;
			break;
		}
		if( ! isset( $update_fld ) )
			return false;

		$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_warehouse_stocks." set ".$update_fld." WHERE warehouse_id = %s AND prdt_id = %d AND strg_id = %s ", $warehouse_id , $prdt_id, $strg_id );
		$update = $wpdb->query( $update_items_sql );
		//echo "<br />||||".$update_items_sql."----";
		if ( false === $update ) {
			return false;
		}

		//latest in handling
		if( $update )
		{
			$latest_in_types = [ 'reprocess', 'good_receive', 'transfer_item' ];
			
			if( in_array( $type, $latest_in_types ) )
			{
				$fld = "a.doc_id, a.doc_date, t.* ";
				$tbl = "$this->_tbl_doc a ";
				$tbl.= "LEFT JOIN $this->_tbl_doc_item b ON b.doc_id = a.doc_id AND b.status > 0 ";
				$tbl.= "LEFT JOIN $this->_tbl_inventory_item t ON t.item_id = b.item_id AND t.status > 0 ";
				$tbl.= "LEFT JOIN $this->_tbl_warehouse_stocks s ON s.prdt_id = t.product_id AND s.strg_id = t.strg_id ";
				$cond = "AND a.doc_type IN ( '".implode( "', '", $latest_in_types )."' ) ";
				$cond.= $wpdb->prepare( " AND a.status >= %d AND t.product_id = %d AND t.strg_id = %s ", 6, $prdt_id, $strg_id );
				$cond.= "AND b.item_id != s.latest_in_item ";
				$ord = "ORDER BY a.doc_date DESC ";
				$lmt = "LIMIT 0,1 ";
				$latest_doc_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$ord} {$lmt} ";

				$latest_doc = $wpdb->get_row( $latest_doc_sql , ARRAY_A );
				if( $latest_doc )
				{
					$update_fld = $wpdb->prepare( " latest_in_item = %s, latest_in_cost = %s "
						, $latest_doc['item_id'], $latest_doc['unit_price'] 
					);

					$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_warehouse_stocks." set ".$update_fld." WHERE warehouse_id = %s AND prdt_id = %d AND strg_id = %s ", $warehouse_id , $prdt_id, $strg_id );
					$update = $wpdb->query( $update_items_sql );

					if ( false === $update ) return false;
				}
			}
		}
		
		//idw_added - to allow stock in out hooking
		$succ = apply_filters( 'warehouse_after_update_warehouse_stocks_qty', $succ, $type, $warehouse_id, $strg_id, $prdt_id, $ditem, $plus, $def_sign );
		
		if ( false === $succ ) {
			return false;
		}
		
		return true;
	}
	/**
	 *	Get Stock Items
	 */
	public function get_warehouse_items( $warehouse_id, $strg_id = 0, $prdt_id_arr = array() ){
		global $wpdb;

		if ( ! $warehouse_id ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_warehouse_stocks." WHERE warehouse_id = %s AND strg_id = %s ", $warehouse_id, $strg_id );

		if( count( $prdt_id_arr ) > 0 )
		{
			$get_items_sql.=" AND prdt_id IN ( " . implode( ',', $prdt_id_arr ) . ")";
		}
		//echo "<br />GET ||||".$get_items_sql."----";
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Stock Item
	 */
	public function get_warehouse_item( $warehouse_id, $prdt_id, $strg_id = 0 ){
		global $wpdb;

		if ( ! $warehouse_id || !$prdt_id ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_warehouse_stocks." WHERE warehouse_id = %s AND prdt_id = %d AND strg_id = %s ", 
			$warehouse_id, $prdt_id, $strg_id );
		
		//echo "<br />GET ||||".$get_items_sql."----";
		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	#-----------------------------------------------------------------#
	#	>	MOMAFnl Selling Price Log Functions
	#-----------------------------------------------------------------#	

	/**
	 *	Action Handle
	 */
	public function warehouse_stocks_sprice_action_handle( $action , $details  = array() )
	{
		if( count ( $details ) == 0 )
			return false;

		$arr_item = array();
		$exists_item = array();
		$warehouse_id = $details[0]['warehouse_id'];
		$strg_id = ( $details[0]['strg_id'] )? $details[0]['strg_id'] : 0;
		//Get Existing Records. Record By Partner ID + Sales Item ID
		foreach ($details as $item) 
		{
			$arr_item[ $item['sales_item_id'] ] = $item['sales_item_id'];
		}
		$result_item = $this->get_warehouse_items_selling_price( $warehouse_id, $strg_id, $arr_item );
		if( $result_item )
		{
			foreach ($result_item as $item) 
			{
				$exists_item[ $item['sales_item_id'] ] = $item;
			}	
		}

		$succ = true;
		wpdb_start_transaction ();
		switch ( strtolower( $action ) ){
			case "save":
			case "update":
				foreach ($details as $item) 
				{
					$qty = 0;
					$unit = 0;
					$amt = 0;
					$ditem = wp_parse_args( $item, $this->item_sprice_defaults ); 
					if( isset ( $exists_item[ $item['sales_item_id'] ] ) )
					{
						$exists =  $exists_item[ $item['sales_item_id'] ];
						$qty = $ditem['qty'] - $exists['qty'];
						$unit = $ditem['unit'] - $exists['unit'];
						$amt = $ditem['total_amount'] - $exists['total_amount'];
						$succ = $this->update_warehouse_stocks_selling_price( array( 'id' => $exists['id'] ) , $ditem );
						if( ! $succ )
						{
							break;
						}
					}
					else
					{	
						$qty = $ditem['qty'];
						$unit = $ditem['unit'];
						$amt = $ditem['total_amount'];
						$id = $this->add_warehouse_stocks_selling_price( $ditem );
						if( ! $id )
						{
							$succ = false;
							break;
						}
					}
					$succ = $this->update_warehouse_stocks_qty( 'sales', $warehouse_id, $strg_id, $item['prdt_id'], $ditem );
					if( ! $succ )
					{
						break;
					}
				}
			break;
			case "delete":
				$upd_item['status'] = 0;
				foreach ($details as $item) 
				{
					if( isset ( $exists_item[ $item['sales_item_id'] ] ) )
					{
						$exists =  $exists_item[ $item['sales_item_id'] ];
						$qty =  ( - $exists['qty'] );
						$unit =  ( - $exists['unit'] );
						$amt =  ( - $exists['total_amount'] );
						$succ = $this->update_warehouse_stocks_qty( 'sales', $warehouse_id, $strg_id, $exists['prdt_id'], $ditem );
						if( ! $succ )
						{
							break;
						}
						$succ = $this->update_warehouse_stocks_selling_price( array( 'id' => $exists['id'] ) , $upd_item );
						if( ! $succ )
						{
							break;
						}
					}
					else
					{
						$succ = false;
						break;
					}
				}
			break;
			default:
				$succ = false;
			break;
		}
		//echo "<br />".$succ."-----------------"; exit;
		wpdb_end_transaction($succ);
		return $succ;
	}
	/**
	 *	Add Moma fnl Stocks Selling Price
	 */
	public function add_warehouse_stocks_selling_price( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_warehouse_stocks_sprice,
			array(
				'warehouse_id' 		=> $item['warehouse_id'],
				'strg_id'			=> ( $item['strg_id'] )? $item['strg_id'] : 0,
				'sales_item_id' 	=> ( $item['sales_item_id'] )? $item['sales_item_id'] : 0,
				'order_id' 			=> ( $item['order_id'] )? $item['order_id'] : 0,
				'docno' 			=> ( $item['docno'] )? $item['docno'] : 0,
				'tool_id'			=> ( $item['tool_id'] )? $item['tool_id'] : 0,
				'prdt_id' 			=> $item['prdt_id'],
				'customer' 			=> $item['customer'],
				'sales_date' 		=> $item['sales_date'],
				'uom'				=> $item['uom'],
				'qty' 				=> $item['qty'],
				'unit'				=> $item['unit'],
				'uprice' 			=> $item['uprice'],
				'price' 			=> $item['price'],
				'total_amount' 		=> $item['total_amount'],
				'status' 			=> $item['status'] 
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Update Moma fnl Stocks Selling Price
	 */
	public function update_warehouse_stocks_selling_price( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_warehouse_stocks_sprice, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Get Momafnl fnl Stocks Selling Price
	 */
	public function get_warehouse_items_selling_price( $warehouse_id, $strg_id = 0, $item_arr = array() ){
		global $wpdb;

		if ( ! $warehouse_id ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_warehouse_stocks_sprice." WHERE warehouse_id = %s AND strg_id = %s AND status = 1 ", $warehouse_id, $strg_id );

		if( count( $item_arr ) > 0 )
		{
			$get_items_sql.=" AND sales_item_id IN ( " . implode( ',', $item_arr ) . ")";
		}
		//echo "<br />GET ||||".$get_items_sql."----";
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Stocks Selling Price by POS Order
	 */
	public function get_warehouse_items_selling_price_by_order( $order_id, $warehouse_id = '' ){
		global $wpdb;

		if ( ! $order_id ) {
			return false;
		}
		$cond = "";
		$cond.= $wpdb->prepare( "AND order_id = %s ", $order_id );
		if( $warehouse_id ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $warehouse_id );

		$get_items_sql = "SELECT * FROM ".$this->_tbl_warehouse_stocks_sprice." WHERE 1 {$cond} AND status = 1 ";

		//echo "<br />GET ||||".$get_items_sql."----";
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
}
#-----------------------------------------------------------------#
#	>	Example usage
#-----------------------------------------------------------------#
new WC_Warehouse_Stocks();
/*function warehouse_testing(){
	echo "Test MOMA fnl Stocks --->>>> <br />"	;
	$succ = false;

	//Receipt to Momafnl - new added
	//Update qty, cost only
	$detail = array();
	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '11', 
		'qty' 				=> '100', //Qty Changes
		'uprice' 			=> '12',
		'total_amount' 		=> '1200'

	);	
	$detail[] = $item;

	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '46', 
		'qty' 				=> '100', 
		'uprice' 			=> '20',
		'total_amount' 		=> '2000'
	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'receive' , $detail );
	echo "<br />Receipt to MOMAfnl: ".$succ;

	//Receipt to Momafnl - minus Qty, amount for item 1
	$detail = array();
	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '11', 
		'qty' 				=> '-20', //Qty Changes from 100 to 80
		'uprice' 			=> '12',
		'total_amount' 		=> '-240'

	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'receive' , $detail );
	echo "<br />Receipt UPDATE to MOMAfnl: ".$succ;

	//Sales to Momafnl - new added
	//Update avg price, allocated Qty
	$detail = array();
	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '11', 
		'qty' 				=> '20', //Qty Changes
		'uprice' 			=> '20',
		'total_amount' 		=> '400'

	);	
	$detail[] = $item;

	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '46', 
		'qty' 				=> '20', 
		'uprice' 			=> '30',
		'total_amount' 		=> '600'
	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'sales' , $detail );
	echo "<br />Sales to MOMAfnl: ".$succ;

	//Sales Update to Momafnl - new added
	//Update avg price, allocated Qty
	$detail = array();
	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '11', 
		'qty' 				=> '10', //Qty Changes: Current Qty - Previous Qty ,Eg. Add Extra 10 Qty ( 30 - 20 )
		'uprice' 			=> '20',
		'total_amount' 		=> '200'//Amount Changes: Current Amount - Previous Amount

	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'sales' , $detail );
	echo "<br />Sales to MOMAfnl: ".$succ;

	//Sales Update to Momafnl - Changes Selling Price
	//Update avg price, allocated Qty
	$detail = array();
	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '11', 
		'qty' 				=> '0', //Qty Changes: Current Qty - Previous Qty 
		'uprice' 			=> '18', //Prices Changes: discount - 2
		'total_amount' 		=> '-60'//Amount Changes: Current Amount ( 30*20 ) - Previous Amount (30*18 )

	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'sales' , $detail );
	echo "<br />Sales to MOMAfnl: ".$succ;

	//Sales 2 to Momafnl - 
	//Update avg price, allocated Qty
	$detail = array();
	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '11', 
		'qty' 				=> '30', //Qty 
		'uprice' 			=> '22', 
		'total_amount' 		=> '660'

	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'sales' , $detail );
	echo "<br />Sales to MOMAfnl: ".$succ;

	//Sales 2 to Momafnl - Delete Sales 2
	//Update avg price, allocated Qty
	$detail = array();
	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '11', 
		'qty' 				=> '30', //Qty 
		'uprice' 			=> '22', 
		'total_amount' 		=> '660'

	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_action_filter' , 'delete' , 'sales' , $detail );
	echo "<br />Sales to MOMAfnl: ".$succ;

	//DO Post to Momafnl - new added
	$detail = array();
	$item = array(
		'warehouse_id' 		=> '1', 
		'prdt_id' 			=> '11', 
		'qty' 				=> '10', //Qty Changes
		'uprice' 			=> '0',
		'total_amount' 		=> '0'

	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'delivery' , $detail );
	echo "<br />DO Post to MOMAfnl: ".$succ;

	echo "<br /><br />Testing MOMAfnl Selling Price";

	//MOMA Selling Price - new added
	$detail = array();
	$item = array(
		'sales_item_id'   	=> '11', //Sales Order Item ID
		'warehouse_id' 		=> '1', //Partner ID
		'customer' 			=> '1',  //Customer -> Registed User ID?
		'sales_date' 		=> '2016-09-09', //Sales Date
		'prdt_id' 			=> '11', //Product 
		'qty' 				=> '20', //Sales Qty
		'uprice' 			=> '12', //Sales Price
		'total_amount' 		=> '240', //Sales Amount
	);	
	$detail[] = $item;
	$item = array(
		'sales_item_id'   	=> '12', //Sales Order Item ID
		'warehouse_id' 		=> '1', //Partner ID
		'customer' 			=> '1',  //Customer -> Registed User ID?
		'sales_date' 		=> '2016-09-09', //Sales Date
		'prdt_id' 			=> '46', //Product 
		'qty' 				=> '30', //Sales Qty
		'uprice' 			=> '20', //Sales Price
		'total_amount' 		=> '360', //Sales Amount
	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'save' , $detail );
	echo "<br />MOMA Selling Price Tracks Save: ".$succ;

	//MOMA Selling Price - update by sales item id 
	$detail = array();
	$item = array(
		'sales_item_id'   	=> '11', //Sales Order Item ID
		'warehouse_id' 		=> '1', //Partner ID
		'customer' 			=> '1',  //Customer -> Registed User ID?
		'sales_date' 		=> '2016-09-09', //Sales Date
		'prdt_id' 			=> '11', //Product 
		'qty' 				=> '25', //Sales Qty
		'uprice' 			=> '12', //Sales Price
		'total_amount' 		=> '300', //Sales Amount
	);	
	$detail[] = $item;
	$item = array(
		'sales_item_id'   	=> '12', //Sales Order Item ID
		'warehouse_id' 		=> '1', //Partner ID
		'customer' 			=> '1',  //Customer -> Registed User ID?
		'sales_date' 		=> '2016-09-09', //Sales Date
		'prdt_id' 			=> '46', //Product 
		'qty' 				=> '40', //Sales Qty
		'uprice' 			=> '20', //Sales Price
		'total_amount' 		=> '800', //Sales Amount
	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'save' , $detail );
	echo "<br />MOMA Selling Price Tracks Update: ".$succ;

	//MOMA Selling Price - update by sales item id 
	$detail = array();
	$item = array(
		'sales_item_id'   	=> '18', //Sales Order Item ID
		'warehouse_id' 		=> '1', //Partner ID
		'customer' 			=> '1',  //Customer -> Registed User ID?
		'sales_date' 		=> '2016-09-09', //Sales Date
		'prdt_id' 			=> '11', //Product 
		'qty' 				=> '15', //Sales Qty
		'uprice' 			=> '20', //Sales Price
		'total_amount' 		=> '300', //Sales Amount
	);	
	$detail[] = $item;
	$item = array(
		'sales_item_id'   	=> '19', //Sales Order Item ID
		'warehouse_id' 		=> '1', //Partner ID
		'customer' 			=> '1',  //Customer -> Registed User ID?
		'sales_date' 		=> '2016-09-09', //Sales Date
		'prdt_id' 			=> '46', //Product 
		'qty' 				=> '50', //Sales Qty
		'uprice' 			=> '20', //Sales Price
		'total_amount' 		=> '1000', //Sales Amount
	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'save' , $detail );
	echo "<br />MOMA Selling Price Tracks 2 Save: ".$succ;

	//MOMA Selling Price - Delete BY sales item id  
	$detail = array();
	$item = array(
		'sales_item_id'   	=> '11', //Sales Order Item ID
		'warehouse_id' 		=> '1', //Partner ID
	);	
	$detail[] = $item;
	$item = array(
		'sales_item_id'   	=> '12', //Sales Order Item ID
		'warehouse_id' 		=> '1', //Partner ID
	);	
	$detail[] = $item;
	//$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'delete' , $detail );
	echo "<br />MOMA Selling Price Tracks Delete: ".$succ;

	//GR POSTING with GR Doc ID
	$arr_doc = array ( 101 );
	//$succ = apply_filters( 'document_post_warehouse_stocks_data_handle' , 'update' , 'receive', $arr_doc );
	echo "<br />GR POSTING TO MOMA Stocks : ".$succ;

	//GR Un-POST with GR Doc ID
	$arr_doc = array ( 101 );
	//$succ = apply_filters( 'document_post_warehouse_stocks_data_handle' , 'delete' , 'receive', $arr_doc );
	echo "<br />GR Un-Post TO MOMA Stocks : ".$succ;
	
}*/
?>