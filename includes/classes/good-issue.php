<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_GoodIssue_Class" ) ) 
{

class WCWH_GoodIssue_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_good_issue";

	protected $tables = array();

	public $Notices;
	public $className = "GoodIssue_Class";

	private $doc_type = 'good_issue';

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
		);
	}
	
	public function child_action_handle( $action , $header = array() , $details  = array(), $trans = true )
	{
		$succ = true;
		$outcome = array();

		if($trans)wpdb_start_transaction ();

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
				//load prev doc for comparison
				/*$prev_items = array();
				if( $header['doc_id'] ) $exist_items = $this->get_document_items_by_doc( $header['doc_id'] );
				if( $exist_items )
				{
					foreach( $exist_items as $row )
					{	
						$prev_items[ $row['item_id'] ] = $row;
					}
				}*/

				$succ = $this->document_action_handle( $action , $header , $details );
				if( ! $succ )
				{ 
					break; 
				}
				$doc_id = $this->header_item['doc_id'];
				$header_item = $this->header_item;

				$succ = $this->header_meta_handle( $doc_id, $header_item );
				$succ = $this->detail_meta_handle( $doc_id, $this->detail_item );

				//After update Allocation handler
				/*$details = array();
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
				}*/
			break;
			case "delete":
				$doc_id = $header['doc_id'];	
				$detail_item = $this->get_document_items_by_doc( $doc_id );

				$succ = $this->document_action_handle( $action , $header , $details );
				$header_item = $this->header_item;
				
				//Allocation handler
				/*if( $succ && $detail_item )
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
				}*/
			break;
			case "approve":
			case "reject":
			case "complete":
			case "incomplete":
			case "close":
			case "trash":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
			case "post":
				$succ = $this->document_action_handle( $action , $header , $details );
				$header_item = $this->header_item;
				$doc_id = $header_item['doc_id'];
				$detail_item = $this->detail_item;

				//Allocation handler
				/*if( $succ && $detail_item )
				{
					$warehouse_stock_method = get_document_meta( $doc_id, 'warehouse_stock_method', 0, true );

					$details = array();
					foreach( $detail_item as $i => $items )
					{	
						$sprice = get_document_meta( $doc_id, 'sprice', $items['item_id'], true );
						$sprice = ( $sprice )? $sprice : 0;
						$total = $items['bqty'] * $sprice;
						if( $items['bunit'] > 0 ) $total = $items['bunit'] * $sprice;

						$detail = array(
							'warehouse_id' 		=> $header_item['warehouse_id'], 
							'strg_id'			=> $items['strg_id'],
							'prdt_id' 			=> $items['product_id'], 
							'qty' 				=> $items['bqty'],
							'unit'				=> $items['bunit'],
							'uprice' 			=> $sprice, 
							'total_amount' 		=> round_to( $total ),
						);	
						$details[] = $detail;
					}
					
					//sales	
					if( $succ && $details && in_array( $warehouse_stock_method, ['sale_order'] ) ) 
						$succ = apply_filters( 'warehouse_stocks_action_filter' , 'save' , 'sale_order', $details );		
				}*/

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
				
				//inventory transaction
				$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'delete', $this->getDocumentType() , $doc_id );
				if( ! $succ )
				{
					$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
				}

				//Allocation handler
				/*if( $succ && $detail_item )
				{
					$warehouse_stock_method = get_document_meta( $doc_id, 'warehouse_stock_method', 0, true );

					$details = array();
					foreach( $detail_item as $i => $items )
					{	
						$sprice = get_document_meta( $doc_id, 'sprice', $items['item_id'], true );
						$sprice = ( $sprice )? $sprice : 0;
						$total = $items['bqty'] * $sprice;
						if( $items['bunit'] > 0 ) $total = $items['bunit'] * $sprice;

						$detail = array(
							'warehouse_id' 		=> $header_item['warehouse_id'], 
							'strg_id'			=> $items['strg_id'],
							'prdt_id' 			=> $items['product_id'], 
							'qty' 				=> $items['bqty'],
							'unit'				=> $items['bunit'],
							'uprice' 			=> $sprice, 
							'total_amount' 		=> round_to( $total ),
						);	
						$details[] = $detail;
					}
					
					//sales	
					if( $succ && $details && in_array( $warehouse_stock_method, ['sale_order'] ) ) 
						$succ = apply_filters( 'warehouse_stocks_action_filter' , 'delete' , 'sale_order', $details );	
				}*/
				
				if( $succ )
				{
					$succ = $this->document_action_handle( $action , $header , $details );
				}
			break;
		}	
		//echo "Child Action END : ".$succ."-----"; exit;
		$this->succ = apply_filters( "after_{$this->doc_type}_handler", $succ, $header, $details );
		
		if($trans)wpdb_end_transaction( $succ );

		$outcome['succ'] = $succ; 
		$outcome['id'] = $doc_id;
		$outcome['data'] = $this->header_item;

		return $outcome;
	}

	public function count_statuses( $wh = '', $doc_type = '', $issue_type = '' )
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
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'ref_doc_type' ";
			//$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
			if( is_array( $doc_type ) )
				$cond.= "AND b.meta_value IN ('" .implode( "','", $doc_type ). "') ";
			else
				$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
		}
		if( $issue_type )
		{
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} c ON c.doc_id = a.doc_id AND c.item_id = 0 AND c.meta_key = 'good_issue_type' ";
			$cond.= $wpdb->prepare( "AND c.meta_value = %s ", $issue_type );
		}
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		
		$fld = "a.status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		
		if( $wh ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
		if( $doc_type )
		{
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'ref_doc_type' ";
			//$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
			if( is_array( $doc_type ) )
				$cond.= "AND b.meta_value IN ('" .implode( "','", $doc_type ). "') ";
			else
				$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
		}
		if( $issue_type )
		{
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} c ON c.doc_id = a.doc_id AND c.item_id = 0 AND c.meta_key = 'good_issue_type' ";
			$cond.= $wpdb->prepare( "AND c.meta_value = %s ", $issue_type );
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
				//$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'base_doc_type' ";
				//$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
				
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'ref_doc_type' ";
				if( is_array( $doc_type ) )
					$cond.= "AND b.meta_value IN ('" .implode( "','", $doc_type ). "') ";
				else
					$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $doc_type );
			}
			if( $issue_type )
			{
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} c ON c.doc_id = a.doc_id AND c.item_id = 0 AND c.meta_key = 'good_issue_type' ";
				$cond.= $wpdb->prepare( "AND c.meta_value = %s ", $issue_type );
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
		$grp = "";
		$ord = "";
		$l = "";

		//get pr from child under same company
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", comp.name AS client_name, d.meta_value AS remark ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "INNER JOIN {$this->tables['warehouse_tree']} st ON st.descendant = s.id ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} dc ON dc.id = st.ancestor ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "purchase_request", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id != %s ", $wh_code );
		$cond.= $wpdb->prepare( "AND dc.code = %s AND dc.comp_id = s.comp_id ", $wh_code );
		$sql1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		//get SO from current warehouse
		/*$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", cli.name AS client_name, d.meta_value AS remark ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['client']} cli ON cli.code = c.meta_value ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "sale_order", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s ", $wh_code );
		$sql2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		//get TO from current warehouse
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", cli.name AS client_name, d.meta_value AS remark ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['client']} cli ON cli.code = c.meta_value ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "transfer_order", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s ", $wh_code );
		$sql3 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";*/

		$unionSql = $sql1;//." UNION ALL ".$sql2." UNION ALL ".$sql3;
		/*if( $ref_doc_type )
		{
			switch( $ref_doc_type )
			{
				case 'sales_order':
					$unionSql = $sql2;
				break;
				case 'transfer_order':
					$unionSql = $sql3;
				break;
				default:
					$unionSql = $sql1." UNION ALL ".$sql2." UNION ALL ".$sql3;
				break;
			}
		}*/

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
}

}
?>