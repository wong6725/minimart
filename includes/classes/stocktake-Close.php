<?php

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_StockTakeClose_Class" ) ) 
{

class WCWH_StockTakeClose_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_stocktake_close";

	protected $tables = array();

	public $Notices;
	public $className = "StockTakeClose_Class";

	private $doc_type = 'stocktake_close';

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
		$this->_no_details = true;

		$stats = $this->getStat();
		$stats['reopen'] = 3;
		$this->setStat( $stats );
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
			"warehouse"			=> $prefix."warehouse",
			"warehouse_tree"	=> $prefix."warehouse_tree",
			"items"				=> $prefix."items",
		);
	}
	
	public function child_action_handle( $action , $header = array() , $details = array() )
	{	
		$succ = true;
		$outcome = array();
		$user_id = get_current_user_id();
		$now = current_time( 'mysql' );

		//wpdb_start_transaction();
		
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
				//if( $this->detail_item )
					//$succ = $this->detail_meta_handle( $doc_id, $this->detail_item );
			break;
			case "delete":
			case "approve":
			case "reject":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
			case "close":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];

				$ditem = array(
					'doc_id'  			=> $doc_id,
					'strg_id'			=> '0',  
					'product_id' 		=> '0',
					'uom_id' 			=> '', 
					'bqty' 				=> '0', 
					'uqty' 				=> '0', 
					'bunit'				=> '0',
					'uunit'				=> '0',
					'ref_doc_id' 		=> '0', 
					'ref_item_id' 		=> '0', 
					'status' 			=> '0', 
				);
				$ditem['created_by'] = $user_id;
				$ditem['created_at'] = $now;
				$ditem['lupdate_by'] = $user_id;
				$ditem['lupdate_at'] = $now;
				if( $header['remark'] ) $ditem['dremark'] = $header['remark'];

				$detail_id = $this->add_document_items( $ditem );
				if( ! $detail_id ) $succ = false;

				$ditem['item_id'] = $detail_id;
				$detail = []; $detail[] = $ditem;
				if( $succ ) $succ = $this->detail_meta_handle( $doc_id, $detail );
			break;
			case "reopen":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];

				$ditem = array(
					'doc_id'  			=> $doc_id,
					'strg_id'			=> '0',  
					'product_id' 		=> '0',
					'uom_id' 			=> '', 
					'bqty' 				=> '0', 
					'uqty' 				=> '0', 
					'bunit'				=> '0',
					'uunit'				=> '0',
					'ref_doc_id' 		=> '0', 
					'ref_item_id' 		=> '0', 
					'status' 			=> '1', 
				);
				$ditem['created_by'] = $user_id;
				$ditem['created_at'] = $now;
				$ditem['lupdate_by'] = $user_id;
				$ditem['lupdate_at'] = $now;
				if( $header['remark'] ) $ditem['dremark'] = $header['remark'];

				$detail_id = $this->add_document_items( $ditem );
				if( ! $detail_id ) $succ = false;

				$ditem['item_id'] = $detail_id;
				$detail = []; $detail[] = $ditem;
				if( $succ ) $succ = $this->detail_meta_handle( $doc_id, $detail );
			break;
			case "post":
			case "unpost":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
		}	
		
		$this->succ = apply_filters( "after_{$this->doc_type}_handler", $succ, $header, $details );
		
		//wpdb_end_transaction( $succ );

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

	public function get_export_data( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$field = "a.warehouse_id, a.docno, a.sdocno, a.doc_date, a.post_date, a.doc_type, a.status, a.flag ";
		$field.= ", ma.meta_value AS remark ";

		$table = "{$this->tables['document']} a ";

			$subsql = "SELECT z.item_id FROM {$this->tables['document_items']} z WHERE z.doc_id = a.doc_id ";
			$subsql.= "ORDER BY z.created_at DESC LIMIT 0,1 ";

		$table.= "LEFT JOIN {$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.item_id = ( {$subsql} ) ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = b.item_id AND ma.meta_key = 'dremark' ";
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= %d ", $this->doc_type, 3 );
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

		if( isset( $filters['doc_id'] ) && !empty( $filters['doc_id'] )  )
		{
			$cond.= $wpdb->prepare( "AND a.doc_id = %s ", $filters['doc_id'] );
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
		if( isset( $filters['warehouse_id'] ) && !empty( $filters['warehouse_id'] )  )
		{
			$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}

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