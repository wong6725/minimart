<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PurchaseOrder_Class" ) ) 
{

class WCWH_PurchaseOrder_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_purchase_order";

	protected $tables = array();

	public $Notices;
	public $className = "PurchaseOrder_Class";

	private $doc_type = 'purchase_order';

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
			"supplier"			=> $prefix."supplier",
			"items"				=> $prefix."items",
		);
	}
	
	public function child_action_handle( $action , $header = array() , $details  = array() )
	{
		$succ = true;
		$outcome = array();

		wpdb_start_transaction ();

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
			case "confirm":
			case "refute":
			case "approve":
			case "reject":
			case "complete":
			case "incomplete":
			case "close":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
			case "post": 
			case "unpost": 
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
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

	public function count_statuses(  $wh = '' )
	{
		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$fld = "'all' AS status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "a.status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
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

		//get PR from self
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code
			,'' AS supplier_code, '' AS supplier_name, d.meta_value AS remark ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "purchase_request", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s ", $wh_code );
		$sql1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		if( $this->setting['wh_delivery_order']['use_auto_po'] )
		{
			//get DO from diff WH 
			$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
			$field.= ", ccomp.code AS supplier_code, ccomp.name AS supplier_name, d.meta_value AS remark ";
			$table = "{$this->tables['document']} doc ";
			$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
			$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
			$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'supply_to_seller' ";
			$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
			//$table.= "LEFT JOIN {$this->tables['document_meta']} f ON f.doc_id = doc.doc_id AND f.item_id = 0 AND f.meta_key = 'client_company_code' ";
			$table.= "LEFT JOIN {$this->tables['warehouse']} cwh ON cwh.code = c.meta_value ";
			$table.= "LEFT JOIN {$this->tables['company']} ccomp ON ccomp.id = cwh.id ";
			$cond = "";
			$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "delivery_order", 6 );
			$cond.= $wpdb->prepare( "AND doc.warehouse_id != %s ", $wh_code );
			$cond.= $wpdb->prepare( "AND cwh.code = %s ", $wh_code );
			
			$sql2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";
		}

		$unionSql = $sql1;
		$unionSql.= !empty( $sql2 )? " UNION ALL ".$sql2 : "";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

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

	public function get_export_data( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$header_meta = [ 'supplier_company_code', 'remark' ];
		$detail_meta = [ 'uprice', 'total_amount', 'avg_price', '_item_number' ];
		
		$field = "a.doc_id, a.warehouse_id, a.docno, a.sdocno, a.doc_date, a.post_date, a.doc_type, a.status AS hstatus, a.flag AS hflag, a.parent ";
		$table = "{$this->tables['document']} a ";
		
		$supplier_company_code_key = '';
		if( $header_meta )
		{
			foreach( $header_meta as $i => $meta_key )
			{
				$k = 'h'.($i+1);
				$field.= ", {$k}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$this->_tbl_document_meta} {$k} ON {$k}.doc_id = a.doc_id AND {$k}.item_id = 0 AND {$k}.meta_key = %s ", $meta_key );
				
				if( $meta_key == 'supplier_company_code' ) $supplier_company_code_key = $k;
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
				$field.= ", {$k}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$this->_tbl_document_meta} {$k} ON {$k}.doc_id = a.doc_id AND {$k}.item_id = b.item_id AND {$k}.meta_key = %s ", $meta_key );
			}
		}
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
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
			if( isset( $filters['status'] ) && !empty( $filters['status'] )  )
			{
				$cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
			}
			else
			{
				$cond.= $wpdb->prepare( "AND a.status = %d ", 6 );
			}
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
			if( isset( $filters['supplier_company_code'] ) && !empty( $filters['supplier_company_code'] )  )
			{
				$cond.= $wpdb->prepare( "AND {$supplier_company_code_key}.meta_value = %s ", $filters['supplier_company_code'] );
				unset( $filters['supplier_company_code'] );
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
        $order = !empty( $order )? $order : [ 'a.doc_id' => 'ASC' ];
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