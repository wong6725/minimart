<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_SaleCDNote_Class" ) ) 
{

class WCWH_SaleCDNote_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_sale_cdnote";

	protected $tables = array();

	public $Notices;
	public $className = "SaleCDNote_Class";

	private $doc_type = [
		'1' => 'sale_credit_note',
		'2' => 'sale_debit_note',
	];
	
	public $useFlag = false;

	public $processing_stat = [6,9];

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();

		$this->setDocumentType( $this->doc_type );
		$this->setAccPeriodExclusive( [ $this->doc_type ] );

		$this->parent_status = array( 'full'=> '9', 'partial' => '6', 'empty' => '6' );

		$stats = $this->getStat();
		$stats['confirm'] = 3;
		$this->setStat( $stats );
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
				$this->setDocumentType( $this->doc_type[$header['note_action']] );
				$this->setUpdateUqtyFlag( false ); //false = no update uqty
				$this->_allow_empty_bqty = true;
				
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
			case "approve":
			case "reject":
			case "complete":
			case "incomplete":
			case "close":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
			case "confirm":
			case "refute":
			case "post": 
			case "unpost": 
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
		}	
		//echo "Child Action END : ".$succ."-----"; exit;
		$this->setDocumentType( $this->doc_type);

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

		//$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( is_array( $this->doc_type ) )
			$cond.= "AND a.doc_type IN ('" .implode( "','", $this->doc_type ). "') ";
		else
			$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );

		if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "a.status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );

		//$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( is_array( $this->doc_type ) )
			$cond.= "AND a.doc_type IN ('" .implode( "','", $this->doc_type ). "') ";
		else
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

			//$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
			if( is_array( $this->doc_type ) )
				$cond.= "AND a.doc_type IN ('" .implode( "','", $this->doc_type ). "') ";
			else
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
/*
	public function get_export_data( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$header_meta = [ 'supplier_company_code', 'remark', 'invoice', 'note_action', 'note_reason'];
		$detail_meta = [ 'amount', '_item_number' ];
		
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
				if( $meta_key == 'note_action' ) $note_action = $k;

			}
		}
		
		$field.= ", b.item_id, b.strg_id, c.serial AS product_id, b.bqty, b.status AS dstatus ";
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
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
			}
		}

		if(is_array($this->doc_type))
		{
			$cond.= "AND a.doc_type IN ('" .implode( "','", $this->doc_type ). "') ";
		}
		else
		{
			$cond = $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		}
		
		$grp = "";
		$ord = "";
		$l = "";

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
			if( isset( $filters['note_action'] ) && !empty( $filters['note_action'] )  )
			{
				if(is_array($filters['note_action']))
				{
					$cond.= "AND {$note_action}.meta_value IN ('" .implode( "','", $filters['note_action'] ). "') ";
				}
				else
				{
					$cond.= $wpdb->prepare( "AND {$note_action}.meta_value = %d ", $filters['note_action'] );

				}
				unset( $filters['note_action'] );
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
*/
}

}
?>