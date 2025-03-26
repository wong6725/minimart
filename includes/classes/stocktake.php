<?php

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_StockTake_Class" ) ) 
{

class WCWH_StockTake_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_stocktake";

	protected $tables = array();

	public $Notices;
	public $className = "StockTake_Class";

	private $doc_type = 'stocktake';

	public $useFlag = false;

	public $processing_stat = [];

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();

		$this->setDocumentType( $this->doc_type );

		$this->_allow_empty_bqty = true;
		$this->_stat_to_post = 3;
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}

	public function set_db_tables()
	{
		global $wcwh, $wpdb;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"document" 			=> $prefix."document",
			"document_items"	=> $prefix."document_items",
			"document_meta"		=> $prefix."document_meta",

			"items" 			=> $prefix."items",
			"itemstree"			=> $prefix."items_tree",
			"itemsmeta"			=> $prefix."itemsmeta",
			"uom"				=> $prefix."uom",
			"item_group"		=> $prefix."item_group",
			"item_store_type" 	=> $prefix."item_store_type",
			"category"			=> $wpdb->prefix."terms",
			"brand"				=> $prefix."brand",
			"status"			=> $prefix."status",
			"storage"			=> $prefix."storage",
			"inventory"			=> $prefix."inventory",
			"reprocess"			=> $prefix."reprocess_item",

			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",

			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",
		);
	}
	
	public function child_action_handle( $action , $header = array() , $details = array() )
	{	
		$succ = true;
		$outcome = array();

		wpdb_start_transaction();
		
		//UPDATE DOCUMENT
		$action = strtolower( $action );
		switch ( $action ){
			case "save":
			case "update":
				$succ = $this->document_action_handle( $action , $header , $details );
				if( ! $succ )
				{ 
					break; 
				}
				$doc_id = $this->header_item['doc_id'];
				$header_item = $this->header_item ;

				//Header Custom Field
				$succ = $this->header_meta_handle( $doc_id, $header_item );
				if( $this->detail_item )
					$succ = $this->detail_meta_handle( $doc_id, $this->detail_item );
			break;
			case "delete":
			case "approve":
			case "reject":
			case "complete":
			case "incomplete":
			case "close":
			case "confirm":
			case "unconfirm":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
			case "post":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];

				//inventory transaction
				if( isset( $doc_id ) )
				{
					$doc_id = $header['doc_id'];
					$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'save', $this->getDocumentType() , $doc_id );	
					if( ! $succ )
					{
						$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
					}
				}
			break;
			case "unpost":
				$doc_id = $header['doc_id'];	
				
				//inventory transaction
				$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'delete', $this->getDocumentType() , $doc_id );
				if( ! $succ )
				{
					$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
				}
				if( $succ )
				{
					$succ = $this->document_action_handle( $action , $header , $details );
				}
			break;
		}	
		
		$this->succ = apply_filters( "after_{$this->doc_type}_handler", $succ, $header, $details );
		
		wpdb_end_transaction( $succ );

		$outcome['succ'] = $succ; 
		$outcome['id'] = $doc_id;
		$outcome['data'] = $this->header_item;

		return $outcome;
	}

	public function count_statuses( $wh = '' )
	{
		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$fld = "'all' AS status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
		$group = "GROUP BY status ";

		$sql2 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$sql = $sql1." UNION ALL ".$sql2;

		if( $this->processing_stat )
		{
			$fld = "'process' AS status, COUNT( a.status ) AS count ";
			$tbl = "{$dbname}{$this->tables['document']} a ";
			$cond = "AND a.status IN( ".implode( ', ', $this->processing_stat )." ) ";
			$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
			if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
			$sql.= " UNION ALL SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";
		}

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$outcome = array();
		if( $results )
		{
			foreach( $results as $i => $row )
			{
				$outcome[ (string)$row['status'] ] = $row['count'];
			}
		}

		return $outcome;
	}

	public function get_stocktake_stocks( $filters = [] )
	{
		if( ! $filters || ! $filters['warehouse_id'] || ! $filters['strg_id'] ) return false;

		foreach( $filters as $key => $value )
		{
			if( is_numeric( $value ) ) continue;
			if( $value == "" || $value === null ) unset( $filters[ $key ] );
			if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
		}

		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$field = "a.product_id, a.warehouse_id, a.strg_id, SUM( a.bqty ) AS in_qty, SUM( a.bunit ) AS in_unit, 0 AS out_qty, 0 AS out_unit ";
		$field.= ", SUM( a.total_price ) AS in_amt, 0 AS out_amt ";
		$table = "{$this->tables['transaction_items']} a ";
		$table.= "LEFT JOIN {$this->tables['transaction']} b ON b.hid = a.hid ";
		$cond = $wpdb->prepare( "AND a.status > %d AND a.plus_sign = %s ", 0, "+" );
		$cond.= $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $filters['warehouse_id'], $filters['strg_id'] );
		if( isset( $filters['doc_date'] ) ) $cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['doc_date'] );
		$grp = "GROUP BY a.product_id ";
		$in_sql = "( SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} ) ";

		$field = "a.product_id, a.warehouse_id, a.strg_id, 0 AS in_qty, 0 AS in_unit, SUM( a.bqty ) AS out_qty, SUM( a.bunit ) AS out_unit ";
		$field.= ", 0 AS in_amt, SUM( a.total_cost ) AS out_amt ";
		$table = "{$this->tables['transaction_items']} a ";
		$table.= "LEFT JOIN {$this->tables['transaction']} b ON b.hid = a.hid ";
		$cond = $wpdb->prepare( "AND a.status > %d AND a.plus_sign = %s ", 0, "-" );
		$cond.= $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $filters['warehouse_id'], $filters['strg_id'] );
		if( isset( $filters['doc_date'] ) ) $cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['doc_date'] );
		$grp = "GROUP BY a.product_id ";
		$out_sql = "( SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} ) ";

		//------------------------------------------------------------------------------------

		$field = "d.meta_value AS product_id ";
		$field.= $wpdb->prepare( ", %s AS warehouse_id, %s AS strg_id ", $filters['warehouse_id'], $filters['strg_id'] );
		$field.= ", 0 AS in_qty, 0 AS in_unit ";
		$field.= ", SUM( e.meta_value ) AS out_qty, ROUND( SUM( h.meta_value * e.meta_value ), 3 ) AS out_unit ";
		$field.= ", 0 AS in_amt, SUM( inv.total_in_cost / inv.total_in * e.meta_value ) AS out_amt ";

		$table = "{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
		$table.= "LEFT JOIN {$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$this->tables['inventory']} inv ON inv.prdt_id = d.meta_value ";
		$table.= $wpdb->prepare( "AND inv.warehouse_id = %s AND inv.strg_id = %s ", $filters['warehouse_id'], $filters['strg_id'] );

		$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value > %d ", 'shop_order', 0 );
		$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
		if( isset( $filters['doc_date'] ) ) $cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['doc_date'] );

		$grp = "GROUP BY product_id ";
		$pos_sql = "( SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} ) ";

		//------------------------------------------------------------------------------------

		$field = "t.strg_id, i.id AS product_id, i._uom_code AS uom_id, i.parent ";
		$field.= ", i.name AS prdt_name, i.code AS prdt_code, i.serial AS prdt_serial ";
		$field.= ", i._self_unit AS self_unit, i._parent_unit AS parent_unit ";
		//$field.= ", ma.meta_value AS inconsistent_unit, IF( rep.id > 0 AND ma.meta_value > 0, 1, 0 ) AS required_unit ";

		$field.= ", SUM( t.in_qty ) AS stock_in_qty, SUM( t.in_unit ) AS stock_in_unit ";
		$field.= ", SUM( t.out_qty ) AS stock_out_qty, SUM( t.out_unit ) AS stock_out_unit ";
		$field.= ", ( SUM( t.in_qty ) - SUM( t.out_qty ) ) AS stock_bal_qty ";
		$field.= ", IF( SUM( t.in_qty ) - SUM( t.out_qty ) != 0, SUM( t.in_unit ) - SUM( t.out_unit ), 0 ) AS stock_bal_unit ";
		$field.= ", SUM( t.in_amt ) AS total_price, SUM( t.out_amt ) AS total_cost, ( SUM( t.in_amt ) - SUM( t.out_amt ) ) AS balance ";
		//$field.= ", inv.total_in AS inv_in_qty, inv.total_in_cost AS inv_in_cost ";
		//$field.= ", inv.total_out AS inv_out_qty, inv.total_out_cost AS inv_out_cost ";

		$table = "{$this->tables['items']} i ";
		//$table.= "LEFT JOIN {$this->tables['itemsmeta']} ma ON ma.items_id = i.id AND ma.meta_key = 'inconsistent_unit' ";
		//$table.= "LEFT JOIN {$this->tables['reprocess']} rep ON rep.items_id = i.id AND rep.status > 0 ";
		//$table.= "LEFT JOIN {$this->tables['inventory']} inv ON inv.prdt_id = i.id AND inv.strg_id = t.strg_id ";
		
		$table.= "LEFT JOIN ( {$in_sql} UNION ALL {$out_sql} UNION ALL {$pos_sql} ) t ON t.product_id = i.id ";

		$cond = $wpdb->prepare( "AND i.status > %d ", 0 );
		$grp = "GROUP BY i.id ";
		$ord = "ORDER BY i.code ASC ";
		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		
		$results = $wpdb->get_results( $sql , ARRAY_A );

		return $results;
	}
	
	/*public function get_stocktake_doc_stocks( $filters = [] )
	{
		if( ! $filters || ! $filters['warehouse_id'] || ! $filters['strg_id'] || ! $filters['doc_id'] ) return false;

		foreach( $filters as $key => $value )
		{
			if( is_numeric( $value ) ) continue;
			if( $value == "" || $value === null ) unset( $filters[ $key ] );
			if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
		}

		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$field = "a.product_id, a.warehouse_id, a.strg_id, SUM( a.bqty ) AS in_qty, SUM( a.bunit ) AS in_unit, 0 AS out_qty, 0 AS out_unit ";
		$table = "{$this->tables['transaction_items']} a ";
		$table.= "LEFT JOIN {$this->tables['transaction']} b ON b.hid = a.hid ";
		$cond = $wpdb->prepare( "AND a.status > %d AND a.plus_sign = %s ", 0, "+" );
		$cond.= $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $filters['warehouse_id'], $filters['strg_id'] );
		if( isset( $filters['doc_date'] ) ) $cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['doc_date'] );
		$grp = "GROUP BY a.product_id ";
		$in_sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} ";

		$field = "a.product_id, a.warehouse_id, a.strg_id, 0 AS in_qty, 0 AS in_unit, SUM( a.bqty ) AS out_qty, SUM( a.bunit ) AS out_unit ";
		$table = "{$this->tables['transaction_items']} a ";
		$table.= "LEFT JOIN {$this->tables['transaction']} b ON b.hid = a.hid ";
		$cond = $wpdb->prepare( "AND a.status > %d AND a.plus_sign = %s ", 0, "-" );
		$cond.= $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $filters['warehouse_id'], $filters['strg_id'] );
		if( isset( $filters['doc_date'] ) ) $cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['doc_date'] );
		$grp = "GROUP BY a.product_id ";
		$out_sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} ";

		//------------------------------------------------------------------------------------
		$field = "a.product_id, a.warehouse_id, a.strg_id ";
		$field.= ", SUM( a.in_qty ) AS in_qty, SUM( a.in_unit ) AS in_unit ";
		$field.= ", SUM( a.out_qty ) AS out_qty, SUM( a.out_unit ) AS out_unit ";
		$table = "( {$in_sql} UNION ALL {$out_sql} ) a ";
		$cond = '';
		$grp = "GROUP BY a.product_id ";
		$ord = "";
		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		//------------------------------------------------------------------------------------

		$field = "d.item_id, d.doc_id, t.strg_id, i.id AS product_id, i._uom_code AS uom_id ";
		$field.= ", d.bqty, d.uqty, d.bunit, d.uunit, d.ref_doc_id, d.ref_item_id, d.status ";
		$field.= ", i.name AS prdt_name, i._sku AS sku, i.code AS prdt_code, i.serial AS prdt_serial ";
		$field.= ", i._self_unit AS self_unit, i._content_uom AS content_uom, i._parent_unit AS parent_unit ";
		$field.= ", ma.meta_value AS inconsistent_unit, IF( rep.id > 0 AND ma.meta_value > 0, 1, 0 ) AS required_unit ";
		$field.= ", uom.name AS uom_name, uom.code AS uom_code, uom.fraction AS uom_fraction ";

		$field.= ", t.in_qty AS stock_in_qty, t.in_unit AS stock_in_unit ";
		$field.= ", t.out_qty AS stock_out_qty, t.out_unit AS stock_out_unit ";
		$field.= ", @bal_qty:= t.in_qty - t.out_qty AS stock_bal_qty ";
		$field.= ", @bal_unit:= IF( @bal_qty != 0, t.in_unit - t.out_unit, 0 ) AS stock_bal_unit ";
		$field.= ", @adj_qty:= ABS( d.bqty - @bal_qty ) AS adjust_qty, IF( @adj_qty != 0, d.bunit - @bal_unit, 0 ) AS adjust_unit ";
		$field.= ", IF( @adj_qty < 0, '-', '+' ) AS plus_sign ";

		$field.= ", group_concat( distinct ta.code order by tr.level desc separator ',' ) AS breadcrumb_code ";

		$table = "{$this->tables['items']} i ";
		$table.= "LEFT JOIN {$this->tables['itemsmeta']} ma ON ma.items_id = i.id AND ma.meta_key = 'inconsistent_unit' ";
		$table.= "LEFT JOIN {$this->tables['reprocess']} rep ON rep.items_id = i.id AND rep.status > 0 ";
		$table.= "LEFT JOIN {$this->tables['uom']} uom ON uom.code = i._uom_code ";

		$table.= "INNER JOIN {$this->tables['itemstree']} tr ON tr.descendant = i.id ";
		$table.= "INNER JOIN {$this->tables['items']} ta force index(primary) ON ta.id = tr.ancestor ";
		
		if( ! $filters['apply_all'] )
		{
			$table.= "RIGHT JOIN {$this->tables['document_items']} d ON d.product_id = i.id AND d.status > 0 ";
		}
		else
		{
			$table.= "LEFT JOIN {$this->tables['document_items']} d ON d.product_id = i.id AND d.status > 0 ";
		}
		//$table.= "LEFT JOIN {$this->tables['document_meta']} da ON da.doc_id = d.doc_id AND da.item_id = d.item_id AND d.meta_value = 'plus_sign' ";

		$table.= "LEFT JOIN ( $sql ) t ON t.product_id = i.id ";

		$cond = $wpdb->prepare( "AND i.status > %d AND d.doc_id = %s ", 0, $filters['doc_id'] );
		$grp = "GROUP BY i.code, i.serial, i.id ";
		$ord = "ORDER BY breadcrumb_code ASC ";
		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		return $results;
	}*/

	public function get_stocktake_list( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$field = "a.id, a.code AS code, a.name, CONCAT( grp.code, '-', grp.name ) AS item_group ";
		$field.= ", CONCAT( br.code, '-', br.name ) AS brand, CONCAT( cat.slug, '-', cat.name ) AS category ";
		$field.= ", IF( mb.meta_value, 'yes', '' ) AS inconsistent_unit, a._uom_code AS uom ";
		$field.= ", CONCAT( st.code, '-', st.name ) AS store_type ";
		$field.= ", group_concat( distinct ta.code order by t.level asc separator ',' ) as breadcrumb_code";
		$field.= ", bi.ancestor AS base_id, b.code AS base_code, b.name AS base_name ";

		if( $this->refs['metric'] )
		{
			foreach( $this->refs['metric'] AS $each )
			{
				$each = strtoupper($each);
				$met[] = "UPPER( a._uom_code ) = '{$each}' ";
			}

			$metric = "AND NOT ( ".implode( "OR ", $met ).") ";
		}
		
		$field.= ", IF( rr.id > 0 AND mb.meta_value > 0 {$metric}, 1, 0 ) AS required_unit ";

		$table = "{$this->tables['items']} a ";
		$table.= "INNER JOIN {$this->tables['itemstree']} t ON t.descendant = a.id ";
		$table.= "INNER JOIN {$this->tables['items']} ta force index(primary) ON ta.id = t.ancestor ";
		$table.= "LEFT JOIN {$this->tables['item_group']} grp ON grp.id = a.grp_id ";
		$table.= "LEFT JOIN {$this->tables['item_store_type']} st ON st.id = a.store_type_id ";
		$table.= "LEFT JOIN {$this->tables['category']} cat ON cat.term_id = a.category ";
		$table.= "LEFT JOIN {$this->tables['itemsmeta']} ma ON ma.items_id = a.id AND ma.meta_key = '_brand' ";
		$table.= "LEFT JOIN {$this->tables['brand']} br ON br.code = ma.meta_value ";
		$table.= "LEFT JOIN {$this->tables['itemsmeta']} mb ON mb.items_id = a.id AND mb.meta_key = 'inconsistent_unit' ";

		$table.= "LEFT JOIN {$this->tables['reprocess']} rr ON rr.items_id = a.id AND rr.status > 0 ";

		$subsql = "SELECT ancestor FROM {$this->tables['itemstree']} WHERE descendant = a.id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$this->tables['itemstree']} bi ON bi.ancestor = ( $subsql ) ";
		$table.= "LEFT JOIN {$this->tables['items']} b ON b.id = bi.ancestor ";

		$cond = "";
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['product_id'] ) )
		{
			if( is_array( $filters['product_id'] ) )
				$cond.= "AND a.id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.id = %s ", $filters['product_id'] );
		}
		if( isset( $filters['store_type'] ) )
		{
			if( is_array( $filters['store_type'] ) )
				$cond.= "AND a.store_type_id IN ('" .implode( "','", $filters['store_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.store_type_id = %s ", $filters['store_type'] );
		}
		if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
	    {
	        unset( $filters['status'] );
	    }
	    if( isset( $filters['status'] ) )
	    {   
	        $cond.= $wpdb->prepare( "AND a.status = %s ", $filters['status'] );
	    }

		$grp = "";
		$ord = "";
		$l = "";

		//group
		$group = [ 'a.code', 'a.serial', 'a.id' ];
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
		$order = [ 'base_code'=>'ASC', 'breadcrumb_code'=>'DESC' ];
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord.= "ORDER BY ".implode( ", ", $o )." ";

        //limit offset
        if( !empty( $limit ) )
        {
        	$l.= "LIMIT ".implode( ", ", $limit )." ";
        }

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}

		$results = ( method_exists( $this, 'stocktake_list_alter' ) )? $this->stocktake_list_alter( $results ) : $results;
		
		return $results;
	}
		public function stocktake_list_alter( $datas = [] )
		{
			if( ! $datas ) return $datas;

			foreach( $datas as $i => $data )
			{
				$row = [
					'code' => $data['code'],
					'name' => $data['name'],
					'item_group' => $data['item_group'],
					'brand' => $data['brand'],
					'category' => $data['category'],
					'inconsistent_unit' => $data['inconsistent_unit'],
					'uom' => $data['uom'],
					'store_type' => $data['store_type'],
					'base_code' => '',
					'base_conversion' => '',
					'bqty' => '',
					'bunit' => ( $data['required_unit'] )? '' : '-',
				];

				if( $data['base_id'] > 0 && $data['id'] != $data['base_id'] )
				{
					$row['base_code'] = $data['base_code'];
					$row['base_conversion'] = apply_filters( 'wcwh_item_uom_conversion', $data['id'], 1, $data['base_id'] );
				}

				$datas[$i] = $row;
			}

			return $datas;
		}

	public function get_variance_list( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$field = "d.item_id, a.code AS base_code, b.code AS item_code, b.name AS item_name, cat.slug AS category_code, cat.name AS category_name ";
		$field.= ", IF( ma.meta_value, 'yes', '' ) AS inconsistent_unit, b._uom_code AS uom ";
		$field.= ", group_concat( distinct ta.code order by t.level asc separator ',' ) as breadcrumb_code";
		$field.= ", ( b._self_unit * b._parent_unit ) AS conversion ";

		$field.= ", IF( b.parent > 0, 0, da.meta_value ) AS stock_bal_qty, IF( b.parent > 0, 0, db.meta_value ) AS stock_bal_unit ";
		$field.= ", cd.bqty AS count_qty, IF( b.parent > 0, cd.bqty * (b._self_unit * b._parent_unit), cd.bqty ) AS converted_qty ";
		$field.= ", cd.bunit AS count_unit, dc.meta_value AS adjust_type ";
		$field.= ", IF( b.parent > 0, 0, dd.meta_value ) AS variance_qty, IF( b.parent > 0, 0, de.meta_value ) AS variance_unit ";
		$field.= ", ROUND( IF( b.parent > 0, 0, df.meta_value ), 2 ) AS total_price ";

		$table = "{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} da ON da.doc_id = d.doc_id AND da.item_id = d.item_id AND da.meta_key = 'stock_bal_qty' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} db ON db.doc_id = d.doc_id AND db.item_id = d.item_id AND db.meta_key = 'stock_bal_unit' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} dc ON dc.doc_id = d.doc_id AND dc.item_id = d.item_id AND dc.meta_key = 'plus_sign' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} dd ON dd.doc_id = d.doc_id AND dd.item_id = d.item_id AND dd.meta_key = 'adjust_qty' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} de ON de.doc_id = d.doc_id AND de.item_id = d.item_id AND de.meta_key = 'adjust_unit' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} df ON df.doc_id = d.doc_id AND df.item_id = d.item_id AND df.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$this->tables['transaction_items']} td ON td.item_id = d.item_id AND td.status > 0 ";

		$table.= "LEFT JOIN {$this->tables['items']} a ON a.id = d.product_id ";
		$table.= "LEFT JOIN {$this->tables['category']} cat ON cat.term_id = a.category ";
		$table.= "LEFT JOIN {$this->tables['itemsmeta']} ma ON ma.items_id = a.id AND ma.meta_key = 'inconsistent_unit' ";

		$table.= "INNER JOIN {$this->tables['itemstree']} ci ON ci.ancestor = a.id ";
		$table.= "LEFT JOIN {$this->tables['document_items']} cd ON cd.doc_id = h.doc_id AND cd.product_id = ci.descendant AND cd.status > 0 ";
		$table.= "LEFT JOIN {$this->tables['items']} b ON b.id = cd.product_id ";

		$table.= "INNER JOIN {$this->tables['itemstree']} t ON t.descendant = ci.descendant ";
		$table.= "INNER JOIN {$this->tables['items']} ta force index(primary) ON ta.id = t.ancestor ";

		$cond = "AND h.doc_type = 'stocktake' AND h.status >= 3 AND ( dd.meta_value > 0 OR d.bqty > 0 ) AND a.parent = 0 ";
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND h.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.doc_id = %s ", $filters['doc_id'] );
		}
		if( isset( $filters['product_id'] ) )
		{
			if( is_array( $filters['product_id'] ) )
				$cond.= "AND a.id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.id = %d ", $filters['product_id'] );
		}

		$grp = "";
		$ord = "";
		$l = "";

		//group
		$group = [ 'ci.descendant' ];
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
		$order = [ 'a.code'=>'ASC', 'breadcrumb_code'=>'DESC' ];
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord.= "ORDER BY ".implode( ", ", $o )." ";

        //limit offset
        if( !empty( $limit ) )
        {
        	$l.= "LIMIT ".implode( ", ", $limit )." ";
        }

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}
	
} //class

}
?>