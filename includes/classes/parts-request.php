<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PartsRequest_Class" ) ) 
{

class WCWH_PartsRequest_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_parts_request";

	protected $tables = array();

	public $Notices;
	public $className = "PartsRequest_Class";

	private $doc_type = 'parts_request';

	public $useFlag = false;

	public $processing_stat = [];
	
	protected $warehouse = array();

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

			"items"		=> $prefix."items",
			"customer"	=> $prefix."customer",
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
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];

				if($succ)
				{
					$meta = $this->get_doc_meta($doc_id, 'ordering_ref');
					if($meta)
					{
						$ref = maybe_unserialize( htmlspecialchars_decode($meta['meta_value']) );
						if($ref)
						{
							foreach ($ref as $ref_id => $ref_docno) 
							{
								$result = $this->delete_document_meta(["meta_key" => "pr_ordering" , "doc_id" => $ref_id ]);
								if(!$result) $succ = false;
							}
						}
					}
				}
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
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
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

	public function get_parts_request_pre_orders( $search = '', $seller = 0 )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $seller ) )
		{
			$dbname = get_warehouse_meta( $seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$field = "h.doc_id, h.docno, h.doc_date, h.status, h2.meta_value AS total_amount, h4.meta_value AS remark
		, c.id AS customer_id, c.name AS customer_name, c.code AS customer_code
		, d.item_id, d.product_id, d.bqty AS qty, d1.meta_value AS price, d2.meta_value AS line_total
		, i.name AS prdt_name, i._sku AS sku, i.code AS prdt_code, i._uom_code AS prdt_uom ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} h2 ON h2.doc_id = h.doc_id AND h2.item_id = 0 AND h2.meta_key = 'total_amount' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} h3 ON h3.doc_id = h.doc_id AND h3.item_id = 0 AND h3.meta_key = 'register_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} h4 ON h4.doc_id = h.doc_id AND h4.item_id = 0 AND h4.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d1 ON d1.doc_id = h.doc_id AND d1.item_id = d.item_id AND d1.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d2 ON d2.doc_id = h.doc_id AND d2.item_id = d.item_id AND d2.meta_key = 'sale_amt' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = h1.meta_value ";

		$cond = $wpdb->prepare( "AND h.doc_type = %s ", $this->doc_type );

		if( isset( $filters['status'] ) )
		{
			if( strtolower( $filters['status'] ) != 'all' )
			{
				$cond.= $wpdb->prepare( "AND h.status = %s ", $filters['status'] );
			}
		}
		else
		{
			$cond.= "AND h.status = 6 ";
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

	            $seg[] = "( ".implode( "OR ", $cd ).") ";
        	}
        	$cond.= implode( "OR ", $seg );

        	$cond.= ") ";
        }

        $grp = "";
		$ord = "ORDER BY h.doc_date DESC ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		return $wpdb->get_results( $sql , ARRAY_A );
	}

	public function get_parts_request_fulfilment( $doc_id = 0, $seller = 0 )
	{
		if( ! $doc_id ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( isset( $seller ) )
		{
			$dbname = get_warehouse_meta( $seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		
		$field = "a.ID as order_id, tr.meta_value AS doc_id, b.meta_value AS customer_id, a.post_date AS date ";
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} tr ON tr.post_id = a.ID AND tr.meta_key = 'tool_request_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} spr ON spr.post_id = a.ID AND spr.meta_key = 'tool_doc_type' ";

		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status IN ( 'wc-processing', 'wc-completed' ) ", 'shop_order' );
		$cond.= $wpdb->prepare( "AND b.meta_value > %d AND d.meta_value > %d AND tr.meta_value > %d ", 0, 0, 0 );
		$cond.= $wpdb->prepare( "AND spr.meta_value = %s AND tr.meta_value = %s ", $this->doc_type, $doc_id );

		$grp = "";
		$ord = "ORDER BY a.post_date ASC ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		return $results;
	}
}

}
?>