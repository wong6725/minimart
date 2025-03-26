<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_DORevise_Class" ) ) 
{

class WCWH_DORevise_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_do_revise";

	protected $tables = array();

	public $Notices;
	public $className = "DORevise_Class";

	private $doc_type = 'do_revise';

	public $useFlag = false;
	public $useInventory = true;

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
			"itemsmeta"			=> $prefix."itemsmeta",
			"reprocess_item"	=> $prefix."reprocess_item",
			"uom"				=> $prefix."uom",
			"category"			=> $wpdb->prefix."terms",

			"transaction"		=> $prefix."transaction",
			"transaction_items" => $prefix."transaction_items",
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
				if( ! $succ ) //V1.0.3
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
				$header_item = $this->header_item;
				$doc_id = $header_item['doc_id'];
				$detail_item = $this->detail_item;

				//inventory transaction
				if( isset( $doc_id ) && $succ && $this->useInventory )
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
				$t_exists = apply_filters( 'wcwh_get_exist_inventory_transaction', $doc_id, $this->getDocumentType() );
				if ( ! $t_exists )
				{
					if( $succ )
					{
						$succ = $this->document_action_handle( $action , $header , $details );
					}
				}
				else
				{
					$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'delete', $this->getDocumentType() , $doc_id );
					if( ! $succ )
					{
						$this->Notices->set_notices( apply_filters( 'wcwh_inventory_get_notices', true ) );
					}
					
					if( $succ )
					{
						$succ = $this->document_action_handle( $action , $header , $details );
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

	public function count_statuses( $wh = '', $ref_doc_type = '' )
	{
		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$fld = "'all' AS status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
		if( $ref_doc_type )
		{
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'base_doc_type' ";
			$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $ref_doc_type );
		}
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
		if( $ref_doc_type )
		{
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'base_doc_type' ";
			$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $ref_doc_type );
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
			if( $ref_doc_type )
			{
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'base_doc_type' ";
				$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $ref_doc_type );
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

		$client_company_code = [];
		$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$wh_code, 'parent'=>0 ], [], true );
		if( $curr_wh )
		{
			$whs = apply_filters( 'wcwh_get_warehouse', [ 'parent'=>$curr_wh['id'] ], [ 'id'=>'ASC' ], false, [ 'meta'=>['dbname'] ] );
			if( $whs )
			{	
				foreach( $whs as $i => $wh )
				{
					$whc = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$wh['code'], 'seller'=>$wh['id'] ], [], true, [ 'meta'=>['client_company_code'] ] );
					if( !empty( $whc['client_company_code'] ) ) $whc['client_company_code'] = json_decode( $whc['client_company_code'], true );
					if( !empty( $whc['client_company_code'] ) )
					{
						$whs[$i]['client_company_code'] = $whc['client_company_code'];
						foreach( $whc['client_company_code'] as $ccode )
						{
							$client_company_code[] = $ccode;
						}
					}
				}
			}
		}
		else return false;

		$client_company_code = array_filter( $client_company_code );

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$group = [ 'h.doc_id' ];
		$order = [ 'h.warehouse_id'=>'ASC', 'h.docno'=>'ASC', 'h.doc_date'=>'DESC' ];
		$limit = [];

		//get DO from current seller & found on client side
		$field = "h.*, cli.name AS client_name, mb.meta_value AS remark
			, SUM( d.bqty ) AS t_bqty, SUM( d.uqty ) AS t_uqty ";
		
		$table = "{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'base_doc_type' ";
		$table.= "LEFT JOIN {$this->tables['client']} cli ON cli.code = ma.meta_value ";
		$table.= "LEFT JOIN {$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";

		$cond = "";
		$cond.= $wpdb->prepare( "AND h.warehouse_id = %s AND h.doc_type = %s ", $curr_wh['code'], 'delivery_order' );
		$cond.= $wpdb->prepare( "AND h.status >= %d ", 6 );

		if( ! empty( $whs ) )
		{
			$subsql = [];
			foreach( $whs as $wh )
			{
				if( empty( $wh['dbname'] ) || empty( $wh['client_company_code'] ) ) continue;

				$dbname = $wh['dbname'].".";
				$fld = "a.warehouse_id, a.docno, a.sdocno, a.doc_date, a.doc_type, a.status
					, SUM( c.bqty ) AS t_bqty, SUM( c.uqty ) AS t_uqty ";
				$tbl = "{$dbname}{$this->tables['document']} a
					LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key = 'client_company_code'
					LEFT JOIN {$dbname}{$this->tables['document_items']} c ON c.doc_id = a.doc_id AND c.status > 0 ";
				$cd = "AND a.doc_type = 'delivery_order' AND a.warehouse_id = '{$curr_wh['code']}' AND a.status = 6 ";
				$cd.= "AND b.meta_value IN ( '".implode( "', '", $wh['client_company_code'] )."' ) ";
				$grp = "GROUP BY a.doc_id ";
				$query = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$grp} ";

				$subsql[] = $query;
			}
			if( !empty( $subsql ) )
			{
				$union = "( ".implode( ") UNION ALL (", $subsql )." ) ";
				$table.= "LEFT JOIN ( {$union} ) o ON o.docno = h.docno AND o.warehouse_id = h.warehouse_id ";

				$field.= ", o.t_bqty AS ot_bqty, o.t_uqty AS ot_uqty ";
				$cond.= $wpdb->prepare( "AND o.t_uqty > %s AND o.t_bqty != o.t_uqty ", 0 );
			}
			else
			{
				$field.= ", 0 AS ot_bqty, 0 AS ot_uqty ";
			}
		}

		if( !empty( $client_company_code ) )
		{
			$cond.= "AND ma.meta_value IN( '".implode( "', '", $client_company_code )."' ) ";
		}

		if( ! empty( $ref_doc_type ) )
			$cond.= $wpdb->prepare( "AND mc.meta_value = %s ", $ref_doc_type );
		else
			$cond.= "AND mc.meta_value IN ( 'sale_order', 'transfer_order' ) ";

		$grp = "";
		$ord = "";
		$l = "";

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

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ";

		$cond = "AND a.t_uqty + a.ot_uqty < a.t_bqty ";
		$sql = "SELECT a.* FROM ( {$sql} ) a WHERE 1 {$cond} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		return $results;
	}

	public function get_outlet_detail( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
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

        $field = "a.* ";
		$table = "{$this->tables['document_items']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$field.= ", prdt.name AS prdt_name, prdt._sku AS sku, prdt.code AS prdt_code, prdt.serial AS prdt_serial, prdt._uom_code AS uom, prdt._self_unit AS self_unit, prdt._content_uom AS content_uom, prdt._parent_unit AS parent_unit, prdt.parent ";
		$table.= "LEFT JOIN {$this->tables['items']} prdt ON prdt.id = a.product_id ";

		$field.= ", meta_a.meta_value AS inconsistent_unit ";
		$table.= "LEFT JOIN {$this->tables['itemsmeta']} meta_a ON meta_a.items_id = prdt.id AND meta_a.meta_key = 'inconsistent_unit' ";

		$field.= ", meta_b.meta_value AS spec ";
		$table.= "LEFT JOIN {$this->tables['itemsmeta']} meta_b ON meta_b.items_id = prdt.id AND meta_b.meta_key = 'spec' ";
		
		if( $this->refs['metric'] )
		{
			foreach( $this->refs['metric'] AS $each )
			{
				$each = strtoupper($each);
				$met[] = "UPPER( prdt._uom_code ) = '{$each}' ";
			}

			$metric = "AND NOT ( ".implode( "OR ", $met ).") ";
		}
		$field.= ", IF( rep.id > 0 AND meta_a.meta_value > 0 {$metric}, 1, 0 ) AS required_unit ";
		$table.= "LEFT JOIN {$this->tables['reprocess_item']} rep ON rep.items_id = a.product_id AND rep.status > 0 ";
		
		$field.= ", uom.name AS uom_name, uom.code AS uom_code, uom.fraction AS uom_fraction ";
		$table.= "LEFT JOIN {$this->tables['uom']} uom ON uom.code = prdt._uom_code ";
		
		$field.= ", cat.name AS cat_name, cat.slug AS cat_code ";
		$table.= "LEFT JOIN {$this->tables['category']} cat ON cat.term_id = prdt.category ";

		if( $args && !empty( $args['outlet'] ) )
		{
			$ol_wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$args['outlet'] ], [], true, [ 'meta'=>['dbname'] ] );
			if( $ol_wh['dbname'] ) 
			{
				$dbname = $ol_wh['dbname'].".";

				$field.= ", od.bqty AS o_bqty, od.uqty AS o_uqty ";
				$table.= "LEFT JOIN {$this->tables['document']} h ON h.doc_id = a.doc_id ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document']} oh ON oh.docno = h.docno AND oh.status > 1 ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['items']} oi ON oi.serial = prdt.serial ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} od ON od.doc_id = oh.doc_id AND od.product_id = oi.id AND od.status > 0 ";
				$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} oma ON oma.doc_id = od.doc_id AND oma.item_id = od.item_id AND oma.meta_key = 'sunit' ";
			}
		}
		
		$isRef = ( $args && $args['ref'] )? true : false;
		if( $isRef )
		{
			$field.= ", ref.bqty AS ref_bqty, ref.uqty AS ref_uqty, ref.bunit AS ref_bunit, ref.uunit AS ref_uunit ";
			$table.= "LEFT JOIN {$this->tables['document_items']} ref ON ref.doc_id = a.ref_doc_id AND ref.item_id = a.ref_item_id ";
		}

		$istransact = ( $args && $args['transact'] )? true : false;
		if( $istransact )
		{
			$field.= ", itran.product_id AS tran_prdt_id, itran.bqty AS tran_bqty, itran.bunit AS tran_bunit, itran.unit_cost, itran.total_cost, itran.unit_price, itran.total_price, itran.plus_sign, itran.weighted_price, itran.weighted_total ";
			$field.= ", itran.deduct_qty, itran.deduct_unit, itran.status AS tran_status, itran.flag AS tran_flag ";
			$table.= "LEFT JOIN {$this->tables['transaction_items']} itran ON itran.item_id = a.item_id AND itran.status != 0 ";
		}

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
		if( isset( $filters['item_id'] ) )
		{
			if( is_array( $filters['item_id'] ) )
				$cond.= "AND a.item_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.item_id = %d ", $filters['item_id'] );
		}
		if( isset( $filters['strg_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.strg_id = %s ", $filters['strg_id'] );
		}
		if( isset( $filters['product_id'] ) )
		{
			if( is_array( $filters['product_id'] ) )
				$cond.= "AND a.product_id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.product_id = %d ", $filters['product_id'] );
		}
		if( isset( $filters['uom_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.uom_id = %s ", $filters['uom_id'] );
		}
		if( isset( $filters['ref_doc_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.ref_doc_id = %d ", $filters['ref_doc_id'] );
		}
		if( isset( $filters['ref_item_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.ref_item_id = %d ", $filters['ref_item_id'] );
		}
		
		if( $args['meta'] )
		{
			foreach( $args['meta'] as $meta_key )
			{
				if( $meta_key == '_item_key' ) continue;

				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$this->tables['document_meta']} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id = a.item_id AND {$meta_key}.meta_key = %s ", $meta_key );
			}
		}

		$field.= ", CAST( idx.meta_value AS UNSIGNED ) AS idx ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} idx ON idx.doc_id = a.doc_id AND idx.item_id = a.item_id AND idx.meta_key = '_item_number' ";

		$corder = array();
        //status
		if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
		{
			unset( $filters['status'] );
		}
		else
		{
			$cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
		}

		$isUse = ( $args && $args['usage'] )? true : false;
		$isPost = ( $args && $args['posting'] )? true : false;
		if( $isUse || $isPost )
		{
			$cond.= $wpdb->prepare( "AND a.status > %d ", 0 );

			if( $isPost )
			{
				$cond.= $wpdb->prepare( "AND a.status == %d ", 6 );
			}
		}

		//group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

		//order
        $order = !empty( $order )? $order : [ 'idx' => 'ASC', 'a.item_id' => 'ASC' ];
        $order = array_merge( $corder, $order );
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
}

}
?>