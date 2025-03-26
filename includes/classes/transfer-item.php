<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TransferItem_Class" ) ) 
{

class WCWH_TransferItem_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_transfer_item";

	protected $tables = array();

	public $Notices;
	public $className = "TransferItem_Class";

	private $doc_type = 'transfer_item';

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

			"transaction"			=> $prefix."transaction",
			"transaction_conversion"=> $prefix."transaction_conversion",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",

			"status"			=> $prefix."status",
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

	public function get_avaliable_gr( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$dbname = $this->dbName();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
        {
            $dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

        $field = "a.* ";
		$table = "{$dbname}{$this->tables['document']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$field.= ", SUM( ti.bqty ) AS stk_bqty, SUM( ti.deduct_qty ) AS stk_uqty ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = a.doc_id AND t.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.status > 0 ";

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}
		if( isset( $filters['not_doc_id'] ) )
		{
			if( is_array( $filters['not_doc_id'] ) )
				$cond.= "AND a.doc_id NOT IN ('" .implode( "','", $filters['not_doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id != %d ", $filters['not_doc_id'] );
		}
		if( isset( $filters['warehouse_id'] ) )
		{
			if( is_array( $filters['warehouse_id'] ) )
				$cond.= "AND a.warehouse_id IN ('" .implode( "','", $filters['warehouse_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['not_warehouse_id'] ) )
		{
			if( is_array( $filters['not_warehouse_id'] ) )
				$cond.= "AND a.warehouse_id NOT IN ('" .implode( "','", $filters['not_warehouse_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.warehouse_id != %s ", $filters['not_warehouse_id'] );
		}
		if( isset( $filters['docno'] ) )
		{
			if( is_array( $filters['docno'] ) )
				$cond.= "AND a.docno IN ('" .implode( "','", $filters['docno'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.docno = %s ", $filters['docno'] );
		}
		if( isset( $filters['sdocno'] ) )
		{
			if( is_array( $filters['sdocno'] ) )
				$cond.= "AND a.sdocno IN ('" .implode( "','", $filters['sdocno'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.sdocno = %s ", $filters['sdocno'] );
		}
		if( isset( $filters['doc_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date = %s ", $filters['doc_date'] );
		}
		if( isset( $filters['doc_type'] ) )
		{
			if( $filters['doc_type'] != 'none' )
			{
				if( is_array( $filters['doc_type'] ) )
					$cond.= "AND a.doc_type IN ('" .implode( "','", $filters['doc_type'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $filters['doc_type'] );
			}
		}
		if( isset( $filters['parent'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.parent = %d ", $filters['parent'] );
		}

		$field.= ", pd.meta_value AS posting_date ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} pd ON pd.doc_id = a.doc_id AND pd.item_id = 0 AND pd.meta_key = 'posting_date' ";
		
		if( $args['meta'] )
		{
			foreach( $args['meta'] as $meta_key )
			{
				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['document_meta']} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id = 0 AND {$meta_key}.meta_key = %s ", $meta_key );
				
				if( isset( $filters[$meta_key] ) )
				{
					if( is_array( $filters[$meta_key] ) )
						$cond.= "AND {$meta_key}.meta_value IN ('" .implode( "','", $filters[$meta_key] ). "') ";
					else
						$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
				}
			}
		}

		if( $args['dmeta'] )
		{
			foreach( $args['dmeta'] as $meta_key )
			{
				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['document_meta']} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id > 0 AND {$meta_key}.meta_key = %s ", $meta_key );
				
				if( isset( $filters[$meta_key] ) )
				{
					if( is_array( $filters[$meta_key] ) )
						$cond.= "AND {$meta_key}.meta_value IN ('" .implode( "','", $filters[$meta_key] ). "') ";
					else
						$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
				}
			}
		}

		$field.= ", SUM( det.bqty ) AS t_bqty, SUM( det.uqty ) AS t_uqty ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} det ON det.doc_id = a.doc_id AND det.status != 0 ";

		$group[] = "a.doc_id";
		if( isset( $filters['product_id'] ) )
        {
            if( is_array( $filters['product_id'] ) )
				$cond.= "AND det.product_id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND det.product_id = %d ", $filters['product_id'] );
        }

        if( isset( $filters['s'] ) )
        {
        	$search = explode( ',', trim( $filters['s'] ) );	
        	$search = array_merge( $search, explode( ' ', str_replace( ',', ' ', trim( $filters['s'] ) ) ) );
        	$search = array_filter( $search );
        	
        	$cond.= "AND ( ";

        	$seg = array();
        	foreach( $search as $kw )
        	{
        		$kw = trim( $kw );
	            $cd = array();
	            $cd[] = "a.docno LIKE '%".$kw."%' ";
	            $cd[] = "a.sdocno LIKE '%".$kw."%' ";

	            if( $args['meta'] )
				{
					foreach( $args['meta'] as $meta_key )
					{
						$cd[] = "{$meta_key}.meta_value LIKE '%".$kw."%' ";
					}
				}

				if( $args['dmeta'] )
				{
					foreach( $args['dmeta'] as $meta_key )
					{
						$cd[] = "{$meta_key}.meta_value LIKE '%".$kw."%' ";
					}
				}

	            $seg[] = "( ".implode( "OR ", $cd ).") ";
        	}
        	$cond.= implode( "OR ", $seg );

        	$cond.= ") ";

            unset( $filters['status'] );
        }

		$corder = array();
        //status
        if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
        {
            unset( $filters['status'] );
			$cond.= $wpdb->prepare( "AND a.status != %d ", -1 );

            $table.= "LEFT JOIN {$dbname}{$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
        	if( $filters['status'] == 'process' && $this->processing_stat )
        	{
        		$cond.= "AND a.status IN( ".implode( ', ', $this->processing_stat )." ) ";

        		$table.= "LEFT JOIN {$dbname}{$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
            	$corder["stat.order"] = "DESC";
        	}
        	else
            	$cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
        }
        //flag
        if( isset( $filters['flag'] ) && $filters['flag'] != "" )
        {   
            $cond.= $wpdb->prepare( "AND a.flag = %s ", $filters['flag'] );
        }
        if( $this->useFlag )
        {
             $table.= "LEFT JOIN {$dbname}{$this->tables['status']} flag ON flag.status = a.flag AND flag.type = 'flag' ";
             $corder["flag.order"] = "DESC";
        }

		$isUse = ( $args && $args['usage'] )? true : false;
		$isPost = ( $args && $args['posting'] )? true : false;
		if( $isUse || $isPost )
		{
			$cond.= $wpdb->prepare( "AND a.status > %d AND a.flag = %d ", 0, 1 );

			if( $isPost )
			{
				$cond.= $wpdb->prepare( "AND a.status >= %d ", 6 );
			}
		}

		//group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.doc_date' => 'DESC', 'a.doc_id' => 'DESC' ];
        	$order = array_merge( $corder, $order );
		} 

        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord.= "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		$cond = "AND a.stk_bqty - a.stk_uqty > 0 ";
		$ord = "";
		//limit offset
        if( !empty( $limit ) )
        {
        	$l.= "LIMIT ".implode( ", ", $limit )." ";
        }

		$sql = "SELECT a.* FROM ( {$sql} ) a WHERE 1 {$cond} {$ord} {$l} ";

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