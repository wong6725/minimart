<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PosDO_Class" ) ) 
{

class WCWH_PosDO_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_pos_do";

	protected $tables = array();

	public $Notices;
	public $className = "PosDO_Class";

	private $doc_type = 'pos_delivery_order';

	public $useFlag = false;

	public $processing_stat = [];

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();

		$this->setDocumentType( $this->doc_type );

		$this->parent_status = array();
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
			"company"			=> $prefix."company",
			"client"			=> $prefix."client",
			"items"				=> $prefix."items",

			"order" 		=> $wpdb->posts,
			"ordermeta"		=> $wpdb->postmeta,
			"order_item"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"customer"		=> $prefix."customer",

			"product"		=> $wpdb->posts,
			"productmeta"	=> $wpdb->postmeta,
		);
	}
	
	public function child_action_handle( $action , $header = array() , $details  = array(), $trans = true )
	{
		$succ = true;
		$outcome = array();

		if( $trans ) wpdb_start_transaction ();

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

				if( $succ && $header_item['ref_order_id'] )
				{
					$receipt_id = $header_item['ref_order_id'];

					$postMeta['pos_delivery_doc'] = $header_item['docno'];
					foreach ($postMeta as $key => $value)
					{
						if( $value )
						{
							update_post_meta( $receipt_id, $key, $value );
						}
					}
				}
			break;
			case "delete":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];

				if( $succ )
				{
					$receipt_id = get_document_meta( $doc_id, 'ref_order_id', 0, true );

					if( $receipt_id)
					{
						$succ = delete_post_meta( $receipt_id, 'pos_delivery_doc' );
					}
				}
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
		
		if( $trans ) wpdb_end_transaction( $succ );

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

	public function get_reference(  $wh_code = '' )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$group = [];
		$order = [ 'a.ID'=>'ASC', 'a.post_date'=>'DESC' ];
		$limit = [];

		$field = "a.ID AS id, a.post_date AS order_date, a.post_status AS order_status, a.post_author AS created ";
		$field.= ",ma.meta_value AS order_no , mb.meta_value AS register, mc.meta_value AS wh_id, md.meta_value AS order_comments ";
		$field.= ", c.id AS customer_id, c.name AS customer_name, c.code AS customer_code, c.uid AS employee_no, mca.meta_value AS book_qr "; //------customer?

		$table = "{$dbname}{$this->tables['order']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} ma ON ma.post_id = a.ID AND ma.meta_key = '_order_number' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mb ON mb.post_id = a.ID AND mb.meta_key = 'wc_pos_id_register' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mc ON mc.post_id = a.ID AND mc.meta_key = 'wc_pos_warehouse_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} md ON md.post_id = a.ID AND md.meta_key = 'order_comments' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} me ON me.post_id = a.ID AND me.meta_key = 'pos_delivery_doc' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = a.post_content ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mca ON mca.post_id = a.ID AND mca.meta_key = '_customer_serial' ";		

		$cond.= $wpdb->prepare( "AND a.post_type = %s ", "shop_order" );
		$cond.= $wpdb->prepare( "AND ( a.post_status = %s OR a.post_status = %s ) ", "wc-processing", "wc-completed" );
		$cond.= $wpdb->prepare( "AND (me.meta_value IS NULL OR me.meta_value = '') " );

		if( $wh_code )
		{
			if( is_array( $wh_code ) )
				$cond.= "AND mc.meta_value IN ('" .implode( "','", $wh_code ). "') ";
			else
				$cond.= $wpdb->prepare( "AND mc.meta_value = %s ", $wh_code );
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

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		return $results;
	}
}

}
?>