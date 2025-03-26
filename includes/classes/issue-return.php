<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_IssueReturn_Class" ) ) 
{

class WCWH_IssueReturn_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_issue_return";

	protected $tables = array();

	public $Notices;
	public $className = "IssueReturn_Class";

	private $doc_type = 'issue_return';

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

			"transaction"		=> $prefix."transaction",
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
			break;
			case "delete":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
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
			case "unpost":
				$succ = $this->document_action_handle( $action , $header , $details );
				$header_item = $this->header_item;
				$doc_id = $header_item['doc_id'];
				$detail_item = $this->detail_item;
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

	public function count_statuses( $wh = '' )
	{
		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$fld = "'all' AS status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
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

		//get GI from current seller
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", d.meta_value AS remark, REPLACE( e.meta_value, '_', ' ' ) AS good_issue_type, '' AS client ";
		$table = "{$this->tables['document']} doc ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON e.doc_id = doc.doc_id AND e.item_id = 0 AND e.meta_key = 'good_issue_type' ";

		$table.= "LEFT JOIN {$this->tables['transaction']} t ON t.doc_id = doc.doc_id AND t.doc_type = doc.doc_type AND t.status > 0 ";

		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", 'good_issue', 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s AND t.hid > %d ", $wh_code, 0 );

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

		$sql1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ";

		//---------------------------------------------------------------------------

		$group = [];
		$order = [ 'doc.warehouse_id'=>'ASC', 'doc.docno'=>'ASC', 'doc.doc_date'=>'DESC' ];
		$limit = [];

		//get GI from current seller
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", d.meta_value AS remark, 'delivery order' AS good_issue_type, cl.name AS client ";
		$table = "{$this->tables['document']} doc ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON e.doc_id = doc.doc_id AND e.item_id = 0 AND e.meta_key = 'client_company_code' ";

		$table.= "LEFT JOIN {$this->tables['client']} cl ON cl.code = e.meta_value ";
		$table.= "LEFT JOIN {$this->tables['transaction']} t ON t.doc_id = doc.doc_id AND t.doc_type = doc.doc_type AND t.status > 0 ";

		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", 'delivery_order', 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s AND t.hid > %d ", $wh_code, 0 );

		if( $this->setting['canteen_einvoice']['client'] )
		{
			$cond.= "AND cl.id NOT IN ( '".implode( "', '", $this->setting['canteen_einvoice']['client'] )."' ) ";
		}

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

		$sql2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ";

		$sql = "( {$sql1} ) UNION ALL ( {$sql2} ) ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		return $results;
	}
}

}
?>