<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_BankInCollector_Class" ) ) 
{

class WCWH_BankInCollector_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_bankin_collector";

	protected $tables = array();

	public $Notices;
	public $className = "BankInCollector_Class";

	private $doc_type = 'bankin_collector';

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

		$this->_no_details = true;
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
				if($action == 'update' && $succ)
				{
					if($header['bankAccID'] && $header['bankAccID'] != 'new')
					{
						$succ = delete_document_meta( $header['doc_id'], 'new_bankinfo_id' );
					}
				}
				if( ! $succ )
				{
					break; 
				}
				$doc_id = $this->header_item['doc_id'];
				$header_item = $this->header_item ;

				//Header Custom Field
				$succ = $this->header_meta_handle( $doc_id, $header_item );
				//$succ = $this->detail_meta_handle( $doc_id, $this->detail_item );
			break;
			case "delete":
				$doc_id = $header['doc_id'];
				$succ = $this->document_action_handle( $action , $header , $details );
				$header_item = $this->header_item;
				$metas = $this->get_document_meta( $doc_id);
				if($succ && $metas)
				{
					foreach($metas as $key => $value)
					{
						$metas[$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
					}
					if($metas['bankAccID'] == 'new' && $metas['new_bankinfo_id'])
					{
						if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
						$Inst = new WCWH_BankInInfo_Controller( $this->db_wpdb );
						$data = [];
						$data['id'] = $metas['new_bankinfo_id'];
						$succ = $Inst->action_handler('delete',$data,'',false);
					}

				}
				//----
				//action handling for the bank_info when posted doc is delected?????
				//---
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
				$metas = $this->get_document_meta( $doc_id);
				if($succ && $metas)
				{
					foreach($metas as $key => $value)
					{
						$metas[$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
					}
					if($metas['bankAccID'] == 'new' && !$metas['new_bankinfo_id'])
					{
						if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
						$Inst = new WCWH_BankInInfo_Controller( $this->db_wpdb );
						$datas = array_intersect_key( $metas, $Inst->get_defaultFields() );
						$succ = $Inst->action_handler('save',$datas,'',false);

						if($succ)
						{
							$succ = $this->add_document_meta_value('new_bankinfo_id',$succ['id'][0],$doc_id);
						}
					}
					elseif( $metas['bankAccID'] == 'new' && $metas['new_bankinfo_id'])
					{
						if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
						$Inst = new WCWH_BankInInfo_Controller( $this->db_wpdb );
						$data = [];
						$data['id'] = $metas['new_bankinfo_id'];
						$succ = $Inst->action_handler('restore',$data,'',false);

						if( $succ )
						{	
							$datas = array_intersect_key( $metas, $Inst->get_defaultFields() );
							$datas['id'] = $metas['new_bankinfo_id'];
							$succ = $Inst->action_handler('update',$datas,'',false);
						}
					}

				}
			break;
			case "unpost":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
				$metas = $this->get_document_meta( $doc_id);
				foreach($metas as $key => $value)
				{
					$metas[$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
				}
				if($succ && $metas['bankAccID'] == 'new' && $metas['new_bankinfo_id'])
				{
					if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
					$Inst = new WCWH_BankInInfo_Controller( $this->db_wpdb );
					$data = [];
					$data['id'] = $metas['new_bankinfo_id'];
					$succ = $Inst->action_handler('delete',$data,'',false);
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

	public function get_bankin_servises( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
		$table = "{$dbname}{$this->_tbl_document} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$isWarehouse = ( $args && $args['warehouse'] )? true : false;
		$isCompany = ( $args && $args['company'] )? true : false;
		if( $isWarehouse || $isCompany )
		{
			$field.= ", wh.id AS wh_id, wh.code AS wh_code, wh.name AS wh_name ";
			$table.= "LEFT JOIN {$dbname}{$prefix}warehouse wh ON wh.code = a.warehouse_id ";
		}
		
		if( $isCompany )
		{
			$field.= ", comp.id AS comp_id, comp.custno AS comp_custno, comp.code AS comp_code, comp.name AS comp_name ";
			$table.= "LEFT JOIN {$dbname}{$prefix}company comp ON comp.id = wh.comp_id ";
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

		if( $filters['from_doc'] && $filters['to_doc'] && $args['prefix'] )
		{
			$cond.= "AND REPLACE( a.docno, '{$args['prefix']}', '' ) >= '{$filters['from_doc']}' ";
			$cond.= "AND REPLACE( a.docno, '{$args['prefix']}', '' ) <= '{$filters['to_doc']}' ";
		}

		//doc_type
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", "bank_in" );

		$field.= ", pd.meta_value AS posting_date ";
		$table.= "LEFT JOIN {$dbname}{$this->_tbl_document_meta} pd ON pd.doc_id = a.doc_id AND pd.item_id = 0 AND pd.meta_key = 'posting_date' ";
		
		if( $args['meta'] )
		{
			foreach( $args['meta'] as $meta_key )
			{
				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->_tbl_document_meta} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id = 0 AND {$meta_key}.meta_key = %s ", $meta_key );

				if( isset( $filters[$meta_key] ) )
				{
					if( is_array( $filters[$meta_key] ) )
						$cond.= "AND {$meta_key}.meta_value IN ('" .implode( "','", $filters[$meta_key] ). "') ";
					else
					{
						if( $filters[$meta_key] == 'IS_NULL' )
						{
							$cond.= "AND ( {$meta_key}.meta_value IS NULL OR {$meta_key}.meta_value = '' ) ";
						}
						else if( $filters[$meta_key] == 'IS_NOT_NULL' )
						{
							$cond.= "AND {$meta_key}.meta_value IS NOT NULL ";
						}
						else
						{
							$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
						}
					}
				}
			}
		}

        if( $args['doc_date_lesser'] )
        {
        	$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $args['doc_date_lesser'] );
        }
        if( $args['doc_date_greater'] )
        {
        	$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $args['doc_date_greater'] );
        }

        if( $args['selection'] )
        {
        	$table.= "LEFT JOIN {$dbname}{$this->_tbl_document_meta} clt ON clt.doc_id = a.doc_id AND clt.item_id = 0 AND clt.meta_key = 'collected' ";
        	if( $args['selection'] === true || $args['selection'] == 1 )
        		$cond.= "AND ( clt.meta_value IS NULL OR clt.meta_value = '' OR clt.meta_value = 0 ) ";
        	else if( $args['selection'] > 1 )
        		$cond.= $wpdb->prepare( "AND ( clt.meta_value IS NULL OR clt.meta_value = '' OR clt.meta_value = 0 OR clt.meta_value = %s ) ", $args['selection'] );
        }
        if( $args['checking'] )
        {
        	$table.= "LEFT JOIN {$dbname}{$this->_tbl_document_meta} clt ON clt.doc_id = a.doc_id AND clt.item_id = 0 AND clt.meta_key = 'collected' ";
        	$cond.= "AND clt.meta_value > 0 ";
        	if( $args['checking'] > 1 )
        		$cond.= $wpdb->prepare( "AND clt.meta_value != %s ", $args['checking'] );
        }
        if( !empty( $args['incl'] ) )
        {
        	$cond.= "OR a.doc_id IN ( ".$args['incl']." ) ";
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

	            $seg[] = "( ".implode( "OR ", $cd ).") ";
        	}
        	$cond.= implode( "OR ", $seg );

        	$cond.= ") ";

            unset( $filters['status'] );
        }

		$corder = array();
        //status
		//if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) ) //original version
		if( ! isset( $filters['status'] ) || ( !is_array($filters['status']) && isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) ) //mha004 modified v2.8.3.23
        {
            unset( $filters['status'] );
			$cond.= $wpdb->prepare( "AND a.status > %d ", -1 );

            $table.= "LEFT JOIN {$dbname}{$this->_tbl_status} stat ON stat.status = a.status AND stat.type = 'default' ";
            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
        	if( $filters['status'] == 'process' && $this->processing_stat )
        	{
        		$cond.= "AND a.status IN( ".implode( ', ', $this->processing_stat )." ) ";

        		$table.= "LEFT JOIN {$dbname}{$this->_tbl_status} stat ON stat.status = a.status AND stat.type = 'default' ";
            	$corder["stat.order"] = "DESC";
        	}
        	else
			{
				if( is_array( $filters['status'] ) )
					$cond.= "AND a.status IN ('" .implode( "','", $filters['status'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
				////wid001 modified v2.8.1.3
			}
        }
        //flag
        if( isset( $filters['flag'] ) && $filters['flag'] != "" )
        {   
            $cond.= $wpdb->prepare( "AND a.flag = %s ", $filters['flag'] );
        }
        if( $this->useFlag )
        {
             $table.= "LEFT JOIN {$dbname}{$this->_tbl_status} flag ON flag.status = a.flag AND flag.type = 'flag' ";
             $corder["flag.order"] = "DESC";
        }

		$isUse = ( $args && isset( $args['usage'] ) )? true : false;
		$isPost = ( $args && $args['posting'] )? true : false;
		if( $isUse || $isPost )
		{
			$cond.= $wpdb->prepare( "AND a.status >= %d AND a.flag = %d ", $args['usage'], 1 );

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