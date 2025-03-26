<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Simplify_Reprocess_Class" ) ) 
{

class WCWH_Simplify_Reprocess_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_simplify_reprocess";

	protected $tables = array();

	public $Notices;
	public $className = "Simplify_Reprocess_Class";

	private $doc_type = 'reprocess';

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
				if( $header['doc_id'] ) $exist_items = $this->get_document_items_by_doc( $header['doc_id'] );
				$succ = $this->document_action_handle( $action , $header , $details );
				if( ! $succ ) //V1.0.3
				{ 
					break; 
				}
				$doc_id = $this->header_item[doc_id];
				$header_item = $this->header_item ;

				$succ = $this->header_meta_handle( $doc_id, $header_item );
				$succ = $this->detail_meta_handle( $doc_id, $this->detail_item );

				//update parent status
				if( $header_item['parent'] )
				{
					$succ = $this->update_document_status( [ $header_item['parent'] ], $this->parent_status['full'] );
				}

				//FIFO Functions Here ON Update
				/*if( isset( $doc_id ) && $header_item['status'] >= 6 )
				{
					$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'update', $this->getDocumentType() , $doc_id );
					if( ! $succ )
					{
						$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
					}
				}*/
			break;
			case "delete":
				$header_item = $this->get_document_header( $header['doc_id'] );
				$detail_items = $this->get_document_items_by_doc( $header['doc_id'] );
				
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item[doc_id];

				//update parent status
				if( $succ && $header_item['parent'] )
				{
					$succ = $this->update_document_status( [ $header_item['parent'] ], $this->parent_status['empty'] );
				}
			break;
			case "approve":
			case "reject":
			case "complete":
			case "incomplete":
			case "close":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item[doc_id];
			break;
			case "post": 
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item[doc_id];

				//FIFO Functions Here ON Posting
				if( isset( $doc_id ) )
				{
					$doc_id = $header[doc_id];
					$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'save', $this->getDocumentType() , $doc_id );	
					if( ! $succ )
					{
						$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
					}
				}
			break;
			case "unpost": 
				$doc_id = $header[doc_id];	

				//FIFO Functions Here ON Posting 
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

		//get GI from current seller
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$table = "{$this->tables['document']} doc ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} gt ON gt.doc_id = doc.doc_id AND gt.item_id = 0 AND gt.meta_key = 'good_issue_type' ";

		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "good_issue", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s ", $wh_code );
		$cond.= $wpdb->prepare( "AND gt.meta_value = %s ", $this->doc_type );

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
}

}
?>