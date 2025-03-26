<?php

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Adjustment_Class" ) ) 
{

class WCWH_Adjustment_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_stock_adjust";

	protected $tables = array();

	public $Notices;
	public $className = "Adjustment_Class";

	private $doc_type = 'stock_adjust';

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

			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",

			"items"			=> $prefix."items",
			"itemsmeta"		=> $prefix."itemsmeta",
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

				//calc gt
				if($succ )
				{
					$details = $this->get_document_items_by_doc($doc_id);
					if( $details )
					foreach ($details as $i => $row) 
					{
						$is_returnable = get_items_meta( $row['product_id'], 'is_returnable', true );
						if( $is_returnable )
						{
							$add_gt_total = get_items_meta( $row['product_id'], 'add_gt_total', true );
							if( $add_gt_total )
							{
								$gtd = get_option( 'gt_total', 0 );
								$gtd+= $row['bqty'];
								update_option( 'gt_total', $gtd );
							}
						}
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

				//calc gt
				if($succ )
				{
					$details = $this->get_document_items_by_doc($doc_id);
					if( $details )
					foreach ($details as $i => $row) 
					{
						$is_returnable = get_items_meta( $row['product_id'], 'is_returnable', true );
						if( $is_returnable )
						{
							$add_gt_total = get_items_meta( $row['product_id'], 'add_gt_total', true );
							if( $add_gt_total )
							{
								$gtd = get_option( 'gt_total', 0 );
								$gtd-= $row['bqty'];
								update_option( 'gt_total', $gtd );
							}
						}
					}
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

	public function get_available_stock_in( $warehouse = '', $strg = '' )
	{
		if( ! $warehouse || ! $strg ) return false;

		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$field = "a.product_id, a.warehouse_id, a.strg_id, SUM( a.bqty ) AS bqty, SUM( a.bunit ) AS bunit ";
		$field.= ", SUM( a.deduct_qty ) AS deduct_qty, SUM( a.bqty ) - SUM( a.deduct_qty ) AS leftover_qty ";
		$field.= ", SUM( a.deduct_unit ) AS deduct_unit, SUM( a.bunit ) - SUM( a.deduct_unit ) AS leftover_unit ";

		$table = "{$this->tables['transaction_items']} a ";
		$table.= "LEFT JOIN {$this->tables['items']} b ON b.id = a.product_id ";
		
		$cond = $wpdb->prepare( "AND a.status > %d AND a.plus_sign = %s AND a.deduct_qty < a.bqty ", 0, "+" );
		$cond.= $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $warehouse, $strg );

		$grp = "GROUP BY a.product_id ";

		$ord = "ORDER BY b.code ASC ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		return $results;
	}

	public function get_available_stock_in_2( $warehouse = '', $strg = '' )
	{
		if( ! $warehouse || ! $strg ) return false;

		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$field = "a.product_id, a.warehouse_id, a.strg_id, SUM( a.bqty ) AS in_qty, SUM( a.bunit ) AS in_unit, 0 AS out_qty, 0 AS out_unit ";
		$table = "{$this->tables['transaction_items']} a ";
		$cond = $wpdb->prepare( "AND a.status > %d AND a.plus_sign = %s ", 0, "+" );
		$cond.= $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $warehouse, $strg );
		$grp = "GROUP BY a.product_id ";
		$in_sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} ";

		$field = "a.product_id, a.warehouse_id, a.strg_id, 0 AS in_qty, 0 AS in_unit, SUM( a.bqty ) AS out_qty, SUM( a.bunit ) AS out_unit ";
		$table = "{$this->tables['transaction_items']} a ";
		$cond = $wpdb->prepare( "AND a.status > %d AND a.plus_sign = %s ", 0, "-" );
		$cond.= $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $warehouse, $strg['strg_id'] );
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

		$field = "a.product_id, a.warehouse_id, a.strg_id ";
		$field.= ", a.in_qty AS bqty, a.in_unit AS bunit ";
		$field.= ", a.out_qty AS deduct_qty, a.in_qty - a.out_qty AS leftover_qty ";
		$field.= ", a.out_unit AS deduct_unit, a.in_unit - a.out_unit AS leftover_unit ";
		$table = "( $sql ) a ";
		$table.= "LEFT JOIN {$this->tables['items']} b ON b.id = a.product_id ";
		$cond = '';
		$grp = "";
		$ord = "ORDER BY b.code ASC ";
		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		return $results;
	}
	
} //class

}
?>