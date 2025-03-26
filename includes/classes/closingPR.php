<?php

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ClosingPR_Class" ) ) 
{

class WCWH_ClosingPR_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_closing_pr";

	protected $tables = array();

	public $Notices;
	public $className = "WCWH_ClosingPR_Class";

	private $doc_type = 'closed_purchase_request';

	public $useFlag = false;

	public $processing_stat = [];

	//public $_allow_empty_bqty = true;

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
			"items"				=> $prefix."items",
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
			case "delete":
			case "post":
			case "unpost":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
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

		$fld = "'all' AS status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "a.status, COUNT( a.status ) AS count ";
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

	public function get_goods_receipt_by_pr( $id , $exclude_gr_ids = '')
	{
		if( ! $id ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$dbname = $this->dbName();

		$group = [];
		$order = [ 'doc.warehouse_id'=>'ASC', 'doc.docno'=>'ASC', 'doc.doc_date'=>'DESC' ];
		$limit = [];
		$grp = "";
		$ord = "";
		$l = "";

		$field = "doc.*, dm.* ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} dm ON dm.doc_id = doc.doc_id AND dm.meta_key= 'purchase_request_doc_id' ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "good_receive", 6);
		$cond.= $wpdb->prepare( "AND dm.meta_value = %s ", $id);
		$cond.= "AND doc.doc_id NOT IN ('" .implode( "','", $exclude_gr_ids ). "') ";

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

	public function get_reference_documents( $wh_code = '' )
	{
		if( ! $wh_code ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$group = [];
		$order = [ 'doc.warehouse_id'=>'ASC', 'doc.docno'=>'ASC', 'doc.doc_date'=>'DESC' ];
		$limit = [];
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, d.meta_value AS remark, gr_meta.doc_id AS good_receive_doc_id ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} gr_meta ON gr_meta.meta_key = 'purchase_request_doc_id' AND gr_meta.meta_value = doc.doc_id  ";
		$table.= "LEFT JOIN {$this->tables['document']} gr_doc ON gr_doc.doc_id = gr_meta.doc_id ";

		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "purchase_request", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s ", $wh_code );
		$cond.= $wpdb->prepare( "AND gr_meta.doc_id IS NOT NULL ");
		$cond.= $wpdb->prepare( "AND gr_doc.doc_type = %s AND gr_doc.status >= %d ", "good_receive", 6);

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
		$header_meta = [ 'purchase_doc', 'remark'  ];
		$detail_meta = [ '_item_number' ];
		
		$field = "a.doc_id, a.warehouse_id, a.docno, a.sdocno, a.doc_date, a.post_date, a.doc_type, a.status AS hstatus, a.flag AS hflag ";
		$table = "{$this->tables['document']} a ";
		
		if( $header_meta )
		{
			foreach( $header_meta as $i => $meta_key )
			{
				$k = 'h'.($i+1);
				$field.= ", {$k}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$this->_tbl_document_meta} {$k} ON {$k}.doc_id = a.doc_id AND {$k}.item_id = 0 AND {$k}.meta_key = %s ", $meta_key );
			}
		}
		
		$field.= ", b.item_id, b.strg_id, c.serial AS product_id, b.uom_id, b.bqty, b.uqty, b.bunit, b.uunit, '0' AS ref_doc_id, '0' AS ref_item_id, b.status AS dstatus ";
		$table.= "LEFT JOIN {$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
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

			foreach( $filters as $key => $val )
			{
				if( is_array( $val ) )
					$cond .=  " AND a.{$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND a.{$key} = %s ", $val );
			}
		}
		$cond.= $wpdb->prepare( "AND a.status > %d ", 0 );

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
	
} //class

}
?>