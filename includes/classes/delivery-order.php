<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_DeliveryOrder_Class" ) ) 
{

class WCWH_DeliveryOrder_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_delivery_order";

	protected $tables = array();

	public $Notices;
	public $className = "DeliveryOrder_Class";

	private $doc_type = 'delivery_order';

	public $useFlag = false;

	public $processing_stat = [];

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();

		$this->setDocumentType( $this->doc_type );

		$this->parent_status = array( 'full'=> '9', 'partial' => '6', 'empty' => '6' );
		
		//$this->setAccPeriodExclusive( [ 'delivery_order' ] );
	}

	public function __destruct()
	{
		unset($this->db_wpdb);
		unset($this->Notices);
		unset($this->tables);
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}

	public function set_db_tables()
	{
		global $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"document" 			=> $prefix."document",
			"document_items"	=> $prefix."document_items",
			"document_meta"		=> $prefix."document_meta",
			"warehouse"			=> $prefix."warehouse",
			"warehouse_tree"	=> $prefix."warehouse_tree",
			"company"			=> $prefix."company",
			"client"			=> $prefix."client",
			"items"				=> $prefix."items",
		);
	}
	
	public function child_action_handle( $action , $header = array() , $details  = array() )
	{
		$succ = true;
		$outcome = array();

		wpdb_start_transaction ();

		if( ! $this->check_stocktake( $action, $header ) )
		{
			$succ = false;

			$outcome['succ'] = $succ;

			return $outcome;
		}

		if( $header['doc_id'] )
		{
			$ref_stat = get_document_meta( $header['doc_id'], 'ref_status', 0, true );
			if( $ref_stat )
			{
				$this->parent_status['partial'] = $ref_stat;
				$this->parent_status['empty'] = $ref_stat;
			}
		}

		//UPDATE DOCUMENT
		switch ( strtolower( $action ) )
		{
			case "save":
			case "update":
				//load prev doc for allocation comparison
				$prev_items = array();
				if( $header['doc_id'] ) $exist_items = $this->get_document_items_by_doc( $header['doc_id'] );
				if( $exist_items )
				{
					foreach( $exist_items as $row )
					{	
						$prev_items[ $row['item_id'] ] = $row;
					}
				}

				$succ = $this->document_action_handle( $action , $header , $details );
				if( ! $succ ) //V1.0.3
				{ 
					break; 
				}
				$doc_id = $this->header_item['doc_id'];
				$header_item = $this->header_item ;

				//Header Custom Field
				$succ = $this->header_meta_handle( $doc_id, $header_item );
				$succ = $this->detail_meta_handle( $doc_id, $this->detail_item );

				//Allocation handler
				if( $succ && $this->setting['wh_inventory']['use_reserved'] )
				{
					$details = array();
					$cur_items = array();
					foreach ( $this->detail_item as $items )
					{
						$cur_items[ $items['item_id'] ] = $items;

						$prev_qty = 0; $prev_unit = 0;
						if( $prev_items[ $items['item_id'] ] )
						{
							$prev_qty = $prev_items[ $items['item_id'] ]['bqty'];
							$prev_unit = $prev_items[ $items['item_id'] ]['bunit'];
						}
						
						$detail = array(
							'warehouse_id' 		=> $header_item['warehouse_id'], 
							'strg_id'			=> $items['strg_id'],
							'prdt_id' 			=> $items['product_id'], 
							'qty' 				=> $items['bqty'] - $prev_qty,
							'unit'				=> $items['bunit'] - $prev_unit,
							'uprice' 			=> 0,
							'total_amount' 		=> 0,
						);
						$details[] = $detail;
					}
					//stock allocation	
					if( $succ ) $succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'allocation', $details );	

					//if pervious have more item
					if( $succ && $prev_items )
					{	
						$details = array();
						foreach( $prev_items as $item_id => $items )
						{
							if( ! $cur_items[ $item_id ] )
							{
								$detail = array(
									'warehouse_id' 		=> $header_item['warehouse_id'], 
									'strg_id'			=> $items['strg_id'],
									'prdt_id' 			=> $items['product_id'], 
									'qty' 				=> $items['bqty'],
									'unit'				=> $items['bunit'],
									'uprice' 			=> 0,
									'total_amount' 		=> 0,
								);
								$details[] = $detail;
							}
						}
						//stock allocation
						if( $succ && $details ) $succ = apply_filters( 'warehouse_stocks_action_filter' , 'delete' , 'allocation', $details );	
					}
				}

				//asset transaction
				if( $succ ) $succ = apply_filters( 'warehouse_asset_movement_filter', 'save', $doc_id );
			break;
			case "delete":
				$doc_id = $header['doc_id'];	
				$detail_item = $this->get_document_items_by_doc( $doc_id );

				$succ = $this->document_action_handle( $action , $header , $details );
				$header_item = $this->header_item;

				//Allocation handler
				if( $succ && $detail_item && $this->setting['wh_inventory']['use_reserved'] )
				{
					$details = array();
					foreach( $detail_item as $i => $items )
					{	
						$detail = array(
							'warehouse_id' 		=> $header_item['warehouse_id'], 
							'strg_id'			=> $items['strg_id'],
							'prdt_id' 			=> $items['product_id'], 
							'qty' 				=> $items['bqty'],
							'unit'				=> $items['bunit'],
							'uprice' 			=> 0, 
							'total_amount' 		=> 0,
						);	
						$details[] = $detail;
					}
					//stock allocation
					if( $succ && $details ) $succ = apply_filters( 'warehouse_stocks_action_filter' , 'delete' , 'allocation', $details );	
				}

				//asset transaction
				if( $succ ) $succ = apply_filters( 'warehouse_asset_movement_filter', $action, $doc_id );
			break;
			case "approve":
			case "reject":
			case "complete":
			case "incomplete":
			case "close":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
			case "post":
				$succ = $this->document_action_handle( $action , $header , $details );
				$header_item = $this->header_item;
				$doc_id = $header_item['doc_id'];
				$detail_item = $this->detail_item;

				//Allocation handler
				if( $succ && $detail_item && $this->setting['wh_inventory']['use_reserved'] )
				{
					$details = array();
					foreach( $detail_item as $i => $items )
					{	
						$detail = array(
							'warehouse_id' 		=> $header_item['warehouse_id'], 
							'strg_id'			=> $items['strg_id'],
							'prdt_id' 			=> $items['product_id'], 
							'qty' 				=> $items['bqty'],
							'unit'				=> $items['bunit'],
							'uprice' 			=> 0, 
							'total_amount' 		=> 0,
						);	
						$details[] = $detail;
					}
					//stock allocation
					if( $succ && $details ) $succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'release', $details );	
				}

				//asset transaction
				if( $succ ) $succ = apply_filters( 'warehouse_asset_movement_filter', $action, $doc_id );

				//inventory transaction
				if( isset( $doc_id ) && $succ )
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
				$header_item = $this->get_document_header( $doc_id );
				$detail_item = $this->get_document_items_by_doc( $doc_id );

				//asset transaction
				if( $succ ) $succ = apply_filters( 'warehouse_asset_movement_filter', $action, $doc_id );
				
				//inventory transaction
				$t_exists = apply_filters( 'wcwh_get_exist_inventory_transaction', $doc_id, $this->getDocumentType() );
				if ( ! $t_exists )
				{
					if( $succ )
					{
						$succ = $this->document_action_handle( $action , $header , $details );
					}
				}
				else
				{
					$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'delete', $this->getDocumentType() , $doc_id );
					if( ! $succ )
					{
						$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
					}
					
					if( $succ )
					{
						$succ = $this->document_action_handle( $action , $header , $details );
					}
				}

				//Allocation handler
				if( $succ && $detail_item && $this->setting['wh_inventory']['use_reserved'] )
				{
					$details = array();
					foreach( $detail_item as $i => $items )
					{	
						$detail = array(
							'warehouse_id' 		=> $header_item['warehouse_id'], 
							'strg_id'			=> $items['strg_id'],
							'prdt_id' 			=> $items['product_id'], 
							'qty' 				=> $items['bqty'],
							'unit'				=> $items['bunit'],
							'uprice' 			=> 0, 
							'total_amount' 		=> 0,
						);	
						$details[] = $detail;
					}
					//stock allocation
					if( $succ && $details ) $succ = apply_filters( 'warehouse_stocks_action_filter' , 'delete' , 'release', $details );	
				}
			break;
		}	
		//echo "Child Action END : ".$succ."-----"; exit;
		$this->succ = apply_filters( "after_{$this->doc_type}_handler", $succ, $header, $details );
		
		wpdb_end_transaction( $succ );

		$outcome['succ'] = $succ; 
		$outcome['id'] = $doc_id;
		$outcome['data'] = $this->header_item;

		return $outcome;
	}

	public function count_statuses( $wh = '', $doc_type = '' )
	{
		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$fld = "'all' AS status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
		if( $doc_type )
		{
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'base_doc_type' ";
			$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
		}
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
		if( $doc_type )
		{
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'base_doc_type' ";
			$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
		}
		$group = "GROUP BY a.status ";
		$sql2 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$sql = $sql1." UNION ALL ".$sql2;

		if( $this->processing_stat )
		{
			$fld = "'process' AS status, COUNT( a.status ) AS count ";
			$tbl = "{$dbname}{$this->tables['document']} a ";
			$cond = "AND a.status IN( ".implode( ', ', $this->processing_stat )." ) ";
			$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
			if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
			if( $doc_type )
			{
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'base_doc_type' ";
				$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
			}
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

	public function get_reference_documents( $wh_code = '', $ref_doc_type = '' )
	{
		if( ! $wh_code ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$group = [];
		$order = [ 'doc.warehouse_id'=>'ASC', 'doc.docno'=>'ASC', 'doc.doc_date'=>'DESC' ];
		$limit = [];

		//get SO / TO from current seller
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", cli.name AS client_name, d.meta_value AS remark, e.meta_value AS inventory_action ";
		$table = "{$this->tables['document']} doc ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON e.doc_id = doc.doc_id AND e.item_id = 0 AND e.meta_key = 'inventory_action' ";
		$table.= "LEFT JOIN {$this->tables['client']} cli ON cli.code = c.meta_value ";

		$cond.= $wpdb->prepare( "AND doc.status = %d ", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s ", $wh_code );

		if( ! empty( $ref_doc_type ) )
		{
			if( is_array( $ref_doc_type ) )
				$cond.= "AND doc.doc_type IN ('" .implode( "','", $ref_doc_type ). "') ";
			else
				$cond.= $wpdb->prepare( "AND doc.doc_type = %s ", $ref_doc_type );
		}
		else
			$cond.= "AND doc.doc_type IN ( 'sale_order', 'transfer_order' ) ";

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
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

		return $results;
	}

	public function get_export_data( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$header_meta = [ 'client_company_code', 'supply_to_seller', 'direct_issue', 'remark', 'sales_doc', 'gr_invoice', 'gr_po', 'purchase_doc', 'purchase_warehouse_id' ];
		$detail_meta = [ 'sprice', 'ucost', 'sunit', '_item_number' ];
		
		$field = "a.doc_id, a.warehouse_id, a.docno, a.sdocno, a.doc_date, a.post_date, a.doc_type, a.status AS hstatus, a.flag AS hflag, a.parent ";
		$table = "{$this->tables['document']} a ";
		
		$client_company_code_key = '';
		if( $header_meta )
		{
			foreach( $header_meta as $i => $meta_key )
			{
				$k = 'h'.($i+1);
				$field.= ", IFNULL( {$k}.meta_value, '' ) AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$this->_tbl_document_meta} {$k} ON {$k}.doc_id = a.doc_id AND {$k}.item_id = 0 AND {$k}.meta_key = %s ", $meta_key );
				
				if( $meta_key == 'client_company_code' ) $client_company_code_key = $k;
			}
		}
		
		$field.= ", b.item_id, b.strg_id, c.serial AS product_id, b.uom_id, b.bqty, b.uqty, b.bunit, b.uunit, '0' AS ref_doc_id, '0' AS ref_item_id, b.status AS dstatus ";
		$table.= "LEFT JOIN {$this->tables['document_items']} b ON b.doc_id = a.doc_id ";
		$table.= "LEFT JOIN {$this->tables['items']} c ON c.id = b.product_id ";
		if( $detail_meta )
		{
			foreach( $detail_meta as $i => $meta_key )
			{
				$k = 'd'.($i+1);
				if( $meta_key == '_item_number' )
					$field.= ", CAST( {$k}.meta_value AS UNSIGNED ) AS {$meta_key} ";
				else
					$field.= ", IFNULL( {$k}.meta_value, '' ) AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$this->_tbl_document_meta} {$k} ON {$k}.doc_id = a.doc_id AND {$k}.item_id = b.item_id AND {$k}.meta_key = %s ", $meta_key );
			}
		}
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status = %d ", $this->doc_type, 6 );
		$grp = "";
		$ord = "";
		$l = "";

		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
			}
		}

		if( !empty( $filters ) )
		{
			if( isset( $filters['from_date'] ) && !empty( $filters['from_date'] )  )
			{
				$cond.= $wpdb->prepare( "AND a.lupdate_at >= %s ", $filters['from_date'] );
				unset( $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) && !empty( $filters['to_date'] )  )
			{
				$cond.= $wpdb->prepare( "AND a.lupdate_at <= %s ", $filters['to_date'] );
				unset( $filters['to_date'] );
			}
			if( isset( $filters['client_company_code'] ) && !empty( $filters['client_company_code'] )  )
			{
				$cond.= $wpdb->prepare( "AND {$client_company_code_key}.meta_value = %s ", $filters['client_company_code'] );
				unset( $filters['client_company_code'] );
			}

			foreach( $filters as $key => $val )
			{
				if( is_array( $val ) )
					$cond .=  " AND a.{$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND a.{$key} = %s ", $val );
			}
		}
		$cond.= $wpdb->prepare( "AND b.status > %d ", 0 );

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'a.doc_id' => 'ASC', '_item_number' => 'ASC' ];
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
}

}
?>