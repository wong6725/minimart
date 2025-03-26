<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_GoodReceive_Class" ) ) 
{

class WCWH_GoodReceive_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_good_receive";

	protected $tables = array();

	public $Notices;
	public $className = "GoodReceive_Class";

	private $doc_type = 'good_receive';

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
			"supplier"			=> $prefix."supplier",
			"client"			=> $prefix."client",
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

				//FIFO Functions Here ON Update
				if( isset( $doc_id ) && $header_item['status'] >= 6 )
				{
					$direct_issue = get_document_meta( $doc_id, 'direct_issue', 0, true );
					if( $direct_issue && $this->setting[ $this->section_id ]['use_direct_issue'] )
					{
						$issued = 1;
					}
					else
					{
						/*$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'update', $this->getDocumentType() , $doc_id );
						if( ! $succ )
						{
							$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
						}*/
					}
				}
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

				//FIFO Functions Here ON Posting
				if( isset( $doc_id ) )
				{
					$doc_id = $header['doc_id'];
					
					$direct_issue = get_document_meta( $doc_id, 'direct_issue', 0, true );
					if( $direct_issue && $this->setting[ $this->section_id ]['use_direct_issue'] )
					{
						$issued = 1;
					}
					else
					{
						$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'save', $this->getDocumentType() , $doc_id );
						if( ! $succ )
						{
							$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
						}
					}
				}
				if($succ)
				{
					$succ = $this->returnable_issue_handler( $doc_id, $action );
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

				$direct_issue = get_document_meta( $doc_id, 'direct_issue', 0, true );
				if( $direct_issue && $this->setting[ $this->section_id ]['use_direct_issue'] )
				{
					$succ = $this->document_action_handle( $action , $header , $details );
				}
				else
				{
					//FIFO Functions Here ON Posting 
					$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'delete', $this->getDocumentType() , $doc_id );
					if( $succ )
					{
						$succ = $this->document_action_handle( $action , $header , $details );
						if( ! $succ )
						{
							$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
						}
					}
				}
				if($succ)
				{
					$succ = $this->returnable_issue_handler( $doc_id, $action );
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

	public function get_reference_documents( $wh_code = '', $wh = [] )
	{
		if( ! $wh_code ) return false;

		if( ! $wh || ! isset( $wh['client_company_code'] ) )
		{
			$wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$wh_code ], [], true );
			$metas = get_warehouse_meta( $wh['id'] );
			$wh = $this->combine_meta_data( $wh, $metas );
		}
		$wh['client_company_code'] = is_json( $wh['client_company_code'] )? json_decode( stripslashes( $wh['client_company_code'] ), true ) : $wh['client_company_code'];
		if( !empty( $wh['client_company_code'] ) ) $wh['client_company_code'] = array_filter( $wh['client_company_code'] );

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$group = [];
		$order = [ 'doc.warehouse_id'=>'ASC', 'doc.docno'=>'ASC', 'doc.doc_date'=>'DESC' ];
		$limit = [];
		$grp = "";
		$ord = "";
		$l = "";

		//get DO from diff WH 
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", ccomp.code AS supplier_code, ccomp.name AS supplier_name, d.meta_value AS remark, e.meta_value AS invoice ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'supply_to_seller' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON e.doc_id = doc.doc_id AND e.item_id = 0 AND e.meta_key = 'invoice' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} f ON f.doc_id = doc.doc_id AND f.item_id = 0 AND f.meta_key = 'client_company_code' ";
		//$table.= "LEFT JOIN {$this->tables['document_meta']} f ON f.doc_id = doc.doc_id AND f.item_id = 0 AND f.meta_key = 'direct_issue' ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} cwh ON cwh.code = c.meta_value ";
		$table.= "LEFT JOIN {$this->tables['company']} ccomp ON ccomp.id = cwh.id ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "delivery_order", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id != %s ", $wh_code );
		$cond.= $wpdb->prepare( "AND cwh.code = %s ", $wh_code );
		if( ! empty( $wh['client_company_code'] ) ) $cond.= "AND f.meta_value IN( '".implode( "', '", $wh['client_company_code'] )."' ) ";
		//$cond.= "AND ( f.meta_value IS NULL OR f.meta_value = '' ) ";
		$sql1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		//get PO from self
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", supp.code AS supplier_code, supp.name AS supplier_name, d.meta_value AS remark, e.meta_value AS invoice ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON e.doc_id = doc.doc_id AND e.item_id = 0 AND e.meta_key = 'invoice' ";
		$table.= "LEFT JOIN {$this->tables['supplier']} supp ON supp.code = c.meta_value ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "purchase_order", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s ", $wh_code );
		$sql2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		//get SO CN from self
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", cl.code AS supplier_code, cl.name AS supplier_name, d.meta_value AS remark, '' AS invoice ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON e.doc_id = doc.doc_id AND e.item_id = 0 AND e.meta_key = 'inventory_action' ";
		$table.= "LEFT JOIN {$this->tables['client']} cl ON cl.code = c.meta_value ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "sale_credit_note", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s AND e.meta_value > 0 ", $wh_code );
		$sql3 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		//get PO CN from self
		$field = "doc.*, s.name AS wh_name, s.code AS wh_code, comp.name AS comp_name, comp.code AS comp_code ";
		$field.= ", supp.code AS supplier_code, supp.name AS supplier_name, d.meta_value AS remark, '' AS invoice ";
		$table = "{$this->tables['document']} doc ";
		$table.= "LEFT JOIN {$this->tables['warehouse']} s ON s.code = doc.warehouse_id ";
		$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = s.comp_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = doc.doc_id AND c.item_id = 0 AND c.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON d.doc_id = doc.doc_id AND d.item_id = 0 AND d.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON e.doc_id = doc.doc_id AND e.item_id = 0 AND e.meta_key = 'inventory_action' ";
		$table.= "LEFT JOIN {$this->tables['supplier']} supp ON supp.code = c.meta_value ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND doc.doc_type = %s AND doc.status = %d ", "purchase_credit_note", 6 );
		$cond.= $wpdb->prepare( "AND doc.warehouse_id = %s AND e.meta_value > 0 ", $wh_code );
		$sql4 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		$unionSql = $sql1." UNION ALL ".$sql2." UNION ALL ".$sql3." UNION ALL ".$sql4;
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

	public function returnable_issue_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		if ( !class_exists( "WCWH_GoodIssue_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-issue.php" ); 
		$Inst = new WCWH_GoodIssue_Class( $this->db_wpdb );
		$Inst->setUpdateUqtyFlag( false );

		$succ = false;
		$issue_type = 'returnable';

		switch( $action )
		{
			case 'post':
				$doc_header = $this->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'good_receive' ], [], true, [ 'meta'=>['supplier_company_code'] ] );
				if( $doc_header['supplier_company_code'] )
				{
					$supplier = apply_filters( 'wcwh_get_supplier', [ 'code'=>$doc_header['supplier_company_code'] ], [], true, [ 'meta'=>['no_egt_handle'] ] );
				}
				
				//--------22/11/2022 Repleaceable
				$returnable_item = apply_filters( 'wcwh_get_item', [ 'returnable'=>1 ], [], false, ['meta'=>['returnable_item', 'auto_replacing'],'usage'=>1 ] );
				//--------22/11/2022 Repleaceable
				if($doc_header && $returnable_item && !$supplier['no_egt_handle'])
				{
					$doc_detail = $this->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if($doc_detail)
					{
						$detail = [];
						$ri = [];
						foreach($returnable_item as $i => $item)
						{
							if($item['auto_replacing'] == 'yes')
							{
								$ri[$item['id']] = $item;
							}
						}

						foreach ($doc_detail as $i => $row) 
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							if( $ri[$row['product_id']] )
							{
								$detail[] = [
									'product_id' => $ri[$row['product_id']]['returnable_item'],
									'bqty' => $row['bqty'],
									'bunit' => $row['bunit'],
									'item_id' => '',
									'ref_doc_id' => $row['doc_id'],
									'ref_item_id' => $row['item_id'],
									'dstatus' => 1,
								];
							}
							else
							{
								continue;								
							}
						
						}
					}
					
					if($detail)
					{
						$metas = get_document_meta( $doc_id );
						$doc_header = $this->combine_meta_data( $doc_header, $metas );

						$header = [
							'warehouse_id' => $doc_header['warehouse_id'],
							'doc_date' => $doc_header['doc_date'],
							'post_date' => $doc_header['post_date'],
							'good_issue_type' => $issue_type,
							'parent' => $doc_header['doc_id'],
							'hstatus' => 1,
							'client_company_code' => ( $doc_header['client_company_code'] )? $doc_header['client_company_code'] : $this->setting[ $this->section_id ]['direct_issue_client'],
							'ref_doc_type' => $doc_header['doc_type'],
							'ref_doc_id' => $doc_header['doc_id'],
							'ref_doc' => $doc_header['docno'],
						];
					}
					else
					{
						return true;
					}

					if( $header && $detail )
					{
						$result = $Inst->child_action_handle( 'save', $header, $detail, false );
		                if( ! $result['succ'] )
		                {
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$gi['doc_id'] = $result['id'];
		                	$result = $Inst->child_action_handle( 'post', $gi, [], false );
		                	if( ! $result['succ'] )
		                	{
		                		$this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                	}
		                	else
		                	{
		                		$succ = true;
		                	}
		                    
		                }
					}
				}
				else
				{
					$succ = true;
				}
			break;
			case 'unpost':
				$gi_header = $this->get_header( [ 'good_issue_type'=>'returnable', 'parent'=>$doc_id, 'doc_type'=>'good_issue', 'status'=>6 ], [], true, ['meta'=>['good_issue_type']] );
				if( $gi_header )
				{
					$header = [ 'doc_id'=>$gi_header['doc_id'] ];
					$result = $Inst->child_action_handle( 'unpost', $header, [], false );
	                if( ! $result['succ'] )
	                {
	                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
	                }
	                else
	                {
	                	$result = $Inst->child_action_handle( 'delete', $header );
	                    if( ! $result['succ'] )
		                {
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$succ = true;
		                }
	                }
				}
				else
				{
					$succ = true;
				}
			break;
		}

		return $succ;
	}
}

}
?>