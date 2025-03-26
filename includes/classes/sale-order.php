<?php

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_SaleOrder_Class" ) ) 
{

class WCWH_SaleOrder_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_sales_order";

	protected $tables = array();

	public $Notices;
	public $className = "SaleOrder_Class";

	private $doc_type = 'sale_order';

	public $useFlag = false;

	public $processing_stat = [];

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();

		$this->setDocumentType( $this->doc_type );
		$this->setAccPeriodExclusive( [ $this->doc_type ] );

		$this->parent_status = array( 'full'=> '9', 'partial' => '6', 'empty' => '6' );
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
		);
	}
	
	public function child_action_handle( $action , $header = array() , $details = array() )
	{	
		$succ = true;
		$outcome = array();

		wpdb_start_transaction();

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
		$action = strtolower( $action );
		switch ( $action )
		{
			case "save":
			case "save-post":
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
				$succ = $this->detail_meta_handle( $doc_id, $this->detail_item );
			break;
			case "update-header":
				$this->_no_details = true;
				$this->_allow_flagged_edit = true;
				$succ = $this->document_action_handle( 'update' , $header , $details );
				if( ! $succ )
				{ 
					break; 
				}
				$doc_id = $this->header_item['doc_id'];
				$header_item = $this->header_item ;

				//Header Custom Field
				$succ = $this->header_meta_handle( $doc_id, $header_item );
			break;
			case "delete":
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

				/*if( $succ && $detail_item )
				{
					$details = array();
					foreach( $detail_item as $i => $items )
					{	
						if( $items['product_id'] <= 0 || $items['product_id'] == null ) continue;

						$sprice = get_document_meta( $doc_id, 'sprice', $items['item_id'], true );
						$sprice = ( $sprice )? $sprice : 0;
						$total = get_document_meta( $doc_id, 'line_total', $items['item_id'], true );
						$total = ( $total > 0 )? $total : $items['bqty'] * $sprice;

						$detail = array(
							'warehouse_id' 		=> $header_item['warehouse_id'], 
							'strg_id'			=> apply_filters( 'wcwh_get_system_storage', $items['strg_id'], $header_item ),
							'prdt_id' 			=> $items['product_id'], 
							'qty' 				=> $items['bqty'],
							'unit'				=> $items['bunit'],
							'uprice' 			=> $sprice, 
							'total_amount' 		=> $total,
						);	
						
						if( $items['product_id'] ) $details[] = $detail;
					}

					if( $succ && $details ) $succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'sale_order', $details );	
				}*/
			break;
			case "unpost":
				$doc_id = $header['doc_id'];	
				$header_item = $this->get_document_header( $doc_id );
				$detail_item = $this->get_document_items_by_doc( $doc_id );

				/*if( $succ && $detail_item )
				{	
					$details = array();
					foreach( $detail_item as $i => $items )
					{	
						if( $items['product_id'] <= 0 || $items['product_id'] == null ) continue;
						
						$sprice = get_document_meta( $doc_id, 'sprice', $items['item_id'], true );
						$sprice = ( $sprice )? $sprice : 0;
						$total = get_document_meta( $doc_id, 'line_total', $items['item_id'], true );
						$total = ( $total > 0 )? $total : $items['bqty'] * $sprice;

						$detail = array(
							'warehouse_id' 		=> $header_item['warehouse_id'], 
							'strg_id'			=> apply_filters( 'wcwh_get_system_storage', $items['strg_id'], $header_item ),
							'prdt_id' 			=> $items['product_id'], 
							'qty' 				=> $items['bqty'],
							'unit'				=> $items['bunit'],
							'uprice' 			=> $sprice, 
							'total_amount' 		=> $total,
						);	
						$details[] = $detail;
					}

					if( $succ && $details ) $succ = apply_filters( 'warehouse_stocks_action_filter' , 'delete' , 'sale_order', $details );	
				}*/

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

	public function document_items_sorting( $details )
	{
		if( ! $details ) return $details;

		$items = []; $idxs = [];
		foreach ( $details as $i => $detail_item )
		{
			if( ! $detail_item['product_id'] ) continue;

			$items[] = $detail_item['product_id'];
			$idxs[ $detail_item['product_id'] ] = $i;
		}

		if( $items )
		{
			$sort = apply_filters( 'wcwh_get_item', [ 'id'=>$items ], [ 'grp.code'=>'ASC', 'cat.slug'=>'ASC', 'a.code'=>'ASC' ], false, [ 'group'=>1, 'category'=>1 ] );
		}
		
		
		if( $sort )//_item_number
		{
			foreach( $sort as $j => $item )
			{
				$idx = $idxs[ $item['id'] ];

				$details[ $idx ]['_item_number'] = $j;
			}
		}

		return $details;
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

	public function get_reference_documents( $wh_code = '' )
	{
		if( ! $wh_code ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$group = [];
		$order = [ 'doc.warehouse_id'=>'ASC', 'doc.docno'=>'ASC', 'doc.doc_date'=>'DESC' ];
		$limit = [];
		$grp = "";
		$ord = "";
		$l = "";

		//get pr from child with different company
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code, d.meta_value AS remark ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "INNER JOIN {$this->tables['warehouse_tree']} st ON st.descendant = s.id ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} dc ON dc.id = st.ancestor ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type=%s AND doc.status = %d ", "purchase_request", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id != %s ", $wh_code );
		$cond.= $wpdb->prepare( "AND dc.code = %s AND dc.comp_id != s.comp_id ", $wh_code );
		$sql1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";
		
		//get DO from diff WH 
		/*$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code, d.meta_value AS remark ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'supply_to_seller' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON e.doc_id = doc.doc_id AND e.item_id = 0 AND e.meta_key = 'invoice' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} f ON f.doc_id = doc.doc_id AND f.item_id = 0 AND f.meta_key = 'direct_issue' ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} cwh ON cwh.code = c.meta_value ";
		$table.= "LEFT JOIN {$this->tables['company']} ccomp ON ccomp.id = cwh.id ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "delivery_order", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id != %s ", $wh_code );
		$cond.= $wpdb->prepare( "AND cwh.code = %s ", $wh_code );
		$cond.= $wpdb->prepare( "AND ( f.meta_value IS NOT NULL AND f.meta_value = %d ) ", 1 );
		$sql2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";*/

		//Get tool_request
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code, d.meta_value AS remark ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type=%s AND doc.status = %d ", "tool_request", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id= %s ", $wh_code );
		
		$sql2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		$unionSql = $sql1." UNION ALL ".$sql2;
		$cond = "";

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

		$sql = "SELECT doc.* FROM ( {$unionSql} ) doc WHERE 1 {$cond} {$grp} {$ord} {$l} ;";

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
} //class

}
?>