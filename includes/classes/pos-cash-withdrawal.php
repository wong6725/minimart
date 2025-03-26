<?php

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PosCashWithdrawal_Class" ) ) 
{

class WCWH_PosCashWithdrawal_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_pos_cash_withdrawal";

	protected $tables = array();

	public $Notices;
	public $className = "PosCashWithdrawal_Class";

	private $doc_type = 'cash_withdrawal';

	public $useFlag = false;

	public $processing_stat = [];

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();
		$this->_allow_empty_bqty = true;
		$this->_upd_uqty_flag = false;
		$this->set_db_tables();

		$this->setDocumentType( $this->doc_type );
		$this->setAccPeriodExclusive( [ $this->doc_type ] );
	}

	protected function dbName()
	{
		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] && $this->warehouse['dbname'] )
		{
			return $this->warehouse['dbname'].".";
		}

		return '';
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}

    public function setWarehouse( $wh )
    {
    	$this->warehouse = $wh;
    }

	public function set_db_tables()
	{
		global $wcwh;
		
		$prefix = $this->get_prefix();

		$this->tables = array(
			"document" 			=> $prefix."document",
			"document_items"	=> $prefix."document_items",
			"document_meta"		=> $prefix."document_meta",
			"items"				=> $prefix."items",
			"doc_runningno"		=> $prefix."doc_runningno"
		);
	}
	
	public function child_action_handle( $action , $header = array() , $details = array() )
	{	
		$succ = true;
		$outcome = array();
        
		$user_id = get_current_user_id();
		$now = current_time( 'mysql' );
		wpdb_start_transaction();
		
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
				if( $succ ) 
				{
					if($details)
					{
						$succ = $this->detail_meta_handle( $doc_id, $this->detail_item);
					}
					
					
				}
				
				
			break;
			case "delete":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];

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

		public function count_statuses( $wh = '',$db = '' )
	{
		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();
		if($db)$dbname = $db.'.';
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

	public function get_pos_cash( $filters = [], $order = [], $args = [] )
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
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$field = "DATE_FORMAT( a.post_date, '%Y-%m-%d' ) AS date";
		$field.= ", ROUND( SUM( f.meta_value - g.meta_value ), 2 ) AS amt_cash ";
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_payment_method' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = 'wc_pos_id_register' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = '_pos_session_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_amount_pay' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = 'wc_pos_amount_change' ";
		
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value > %d ", 'shop_order', 0 );
		$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
		
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date']." 00:00:00" );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date']." 23:59:59" );
		}
		if( isset( $filters['payment_method'] ) )
		{
			$cond.= $wpdb->prepare( "AND c.meta_value = %s ", $filters['payment_method'] );
		}
		if( isset( $filters['register'] ) )
		{
			$cond.= $wpdb->prepare( "AND d.meta_value = %s ", $filters['register'] );
		}
		if( isset( $filters['session'] ) )
		{
			$cond.= $wpdb->prepare( "AND e.meta_value = %s ", $filters['session'] );
		}
		
		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.post_date' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_withdrawed_cash( $filters = [], $order = [], $args = [] )
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
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";

		}

		if($args && $args['dbname'])
		{
			$dbname = $args['dbname'].".";
		}

			$field = "SUM(b.meta_value) as 'amt'";
			$table = " {$dbname}{$this->tables['document']} a ";
			$table .= " LEFT JOIN {$dbname}{$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.meta_key = 'amt'";
			$table .= " LEFT JOIN {$dbname}{$this->tables['document_meta']} c ON c.doc_id = a.doc_id AND c.meta_key = 'total'";
		
			$table .= " LEFT JOIN {$dbname}{$this->tables['document_meta']} e ON e.doc_id = a.doc_id AND e.meta_key = 'to_date'";
			$cond = " AND a.doc_type = '{$this->doc_type}' AND a.status > 0";

		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( " AND CONVERT(d.meta_value,datetime) >= %s ", $filters['from_date']);
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( " AND CONVERT(e.meta_value,datetime) <= %s",$filters['to_date']);
		}

		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.post_date' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	/**
	 *	Get Header
	 */
	public function get_header( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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

		if($args && $args['dbname'])
		{
			$dbname = $args['dbname'].".";
		}

        $field = "a.* ";
		$table = "{$dbname}{$this->tables['document']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$isParent = ( $args && $args['parent'] )? true : false;
		if( $isParent )
		{
			$field.= ", prt.warehouse_id AS prt_warehouse_id, prt.docno AS prt_docno, prt.doc_date AS prt_doc_date, prt.doc_type AS prt_doc_type ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document']} prt ON prt.doc_id = a.parent ";
		}

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
		else
		{
			if( $this->doc_type != 'none' )
			{
				if( is_array( $this->doc_type ) )
					$cond.= "AND a.doc_type IN ('" .implode( "','", $this->doc_type ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
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

				if( $meta_key == 'client_company_code' )
				{
					$table.= "LEFT JOIN {$dbname}{$this->_tbl_client} c ON c.code = {$meta_key}.meta_value ";
					$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->_tbl_client_tree} ";
					$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
					$table.= "LEFT JOIN {$dbname}{$this->_tbl_client} cc ON cc.id = ( {$subsql} ) ";

					if( isset( $filters[$meta_key] ) )
					{
						if( is_array( $filters[$meta_key] ) )
						{
							$catcd = "c.code IN ('" .implode( "','", $filters[$meta_key] ). "') ";
							$catcd.= "OR cc.code IN ('" .implode( "','", $filters[$meta_key] ). "') ";
							$cond.= "AND ( {$catcd} ) ";
						}
						else
						{
							$catcd = $wpdb->prepare( "c.code = %s ", $filters[$meta_key] );
							$catcd = $wpdb->prepare( "OR cc.code = %s ", $filters[$meta_key] );
							$cond.= "AND ( {$catcd} ) ";
						}
					}
				}
				else
				{
					if( isset( $filters[$meta_key] ) )
					{
						if( is_array( $filters[$meta_key] ) )
							$cond.= "AND {$meta_key}.meta_value IN ('" .implode( "','", $filters[$meta_key] ). "') ";
						else
							$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
					}
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
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} det ON det.doc_id = a.doc_id AND det.status >= 0 ";

		$group[] = "a.doc_id";
		if( isset( $filters['product_id'] ) )
        {
            if( is_array( $filters['product_id'] ) )
				$cond.= "AND det.product_id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND det.product_id = %d ", $filters['product_id'] );
        }

        if( $args['doc_date_lesser'] )
        {
        	$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $args['doc_date_lesser'] );
        }
        if( $args['doc_date_greater'] )
        {
        	$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $args['doc_date_greater'] );
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
			
			if($filters['CDNote_Status']) //PO Credit/Debit Note Used
			{
				$filters['status'] = $filters['CDNote_Status'];//'process';//wid001 modified v2.8.1.3
			}
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

	public function get_detail( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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

		
		if($args && $args['dbname'])
		{
			$dbname = $args['dbname'].".";
		}

        $field = "a.* ";
		$table = "{$dbname}{$this->tables['document_items']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";
		
		$isItem = ( $args && $args['item'] )? true : false;
		$isUom = ( $args && $args['uom'] )? true : false;
		$isCategory = ( $args && $args['category'] )? true : false;
		$isReturnable = ( $args && $args['returnable'] )? true : false;
		if( $isItem || $isUom || $isCategory || $isReturnable)
		{
			$field.= ", prdt.name AS prdt_name, prdt._sku AS sku, prdt.code AS prdt_code, prdt.serial AS prdt_serial, prdt._uom_code AS uom, prdt._self_unit AS self_unit, prdt._content_uom AS content_uom, prdt._parent_unit AS parent_unit, prdt.parent ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_product} prdt ON prdt.id = a.product_id ";

			$field.= ", meta_a.meta_value AS inconsistent_unit ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_product_meta} meta_a ON meta_a.items_id = prdt.id AND meta_a.meta_key = 'inconsistent_unit' ";

			$field.= ", meta_b.meta_value AS spec ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_product_meta} meta_b ON meta_b.items_id = prdt.id AND meta_b.meta_key = 'spec' ";
			
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
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_reprocess_item} rep ON rep.items_id = a.product_id AND rep.status > 0 ";

			$group = array_merge( $group, [ 'a.item_id' ] );
		}
		if( $isUom )
		{
			$field.= ", uom.name AS uom_name, uom.code AS uom_code, uom.fraction AS uom_fraction ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_uom} uom ON uom.code = prdt._uom_code ";
		}
		if( $isCategory )
		{
			$field.= ", cat.name AS cat_name, cat.slug AS cat_code ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_category} cat ON cat.term_id = prdt.category ";
		}
		if( $isReturnable )
		{
			$field.= ", meta_c.meta_value AS returnable_item ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_product_meta} meta_c ON meta_c.items_id = prdt.id AND meta_c.meta_key = 'returnable_item' ";
		}
		
		$isRefTransact = ( $args && $args['ref_transact'] )? true : false;
		$isRef = ( $args && $args['ref'] )? true : false;
		if( $isRef || $isRefTransact )
		{
			$field.= ", ref.bqty AS ref_bqty, ref.uqty AS ref_uqty, ref.bunit AS ref_bunit, ref.uunit AS ref_uunit ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} ref ON ref.doc_id = a.ref_doc_id AND ref.item_id = a.ref_item_id ";

			if( $isRefTransact )
			{
				$field.= ", ritran.product_id AS ref_tran_prdt_id, ritran.bqty AS ref_tran_bqty, ritran.bunit AS ref_tran_bunit
					, ritran.unit_cost AS ref_unit_cost, ritran.total_cost AS ref_total_cost
					, ritran.unit_price AS ref_unit_price, ritran.total_price AS ref_total_price, ritran.plus_sign AS ref_plus_sign
					, ritran.weighted_price AS ref_weighted_price, ritran.weighted_total AS ref_weighted_total ";
				$field.= ", ritran.deduct_qty AS ref_deduct_qty, ritran.deduct_unit AS ref_deduct_unit, ritran.status AS ref_tran_status, ritran.flag AS ref_tran_flag ";
				$table.= "LEFT JOIN {$dbname}{$this->_tbl_transaction_items} ritran ON ritran.item_id = ref.item_id AND ritran.status != 0 ";
			}
		}

		$isTransact = ( $args && $args['transact'] )? true : false;
		if( $isTransact )
		{
			$field.= ", itran.product_id AS tran_prdt_id, itran.bqty AS tran_bqty, itran.bunit AS tran_bunit, itran.unit_cost, itran.total_cost, itran.unit_price, itran.total_price, itran.plus_sign, itran.weighted_price, itran.weighted_total ";
			$field.= ", itran.deduct_qty, itran.deduct_unit, itran.status AS tran_status, itran.flag AS tran_flag ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_transaction_items} itran ON itran.item_id = a.item_id AND itran.status != 0 ";
		}

		$isStocks = ( $args && $args['stocks'] )? true : false;
		if( $isStocks )
		{
			$field.= ", inv.qty AS stock_qty, inv.allocated_qty AS stock_allocated ";
			
			if( $isItem || $isUom || $isCategory || $isReturnable )
			{
				$table.= $wpdb->prepare( " LEFT JOIN {$dbname}{$this->_tbl_inventory} inv ON inv.prdt_id = IF( prdt.ref_prdt > 0, prdt.ref_prdt, a.product_id ) 
					AND inv.warehouse_id = %s ", $args['stocks'] );
			}
			else
			{
				$table.= $wpdb->prepare( " LEFT JOIN {$dbname}{$this->_tbl_inventory} inv ON inv.prdt_id = a.product_id AND inv.warehouse_id = %s ", $args['stocks'] );
			}
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_storage} strg ON strg.id = inv.strg_id ";
			$cond.= $wpdb->prepare( "AND ( strg.sys_reserved = %s OR inv.id IS NULL ) ", 'staging' );
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
			$cond.= $wpdb->prepare( "AND a.strg_id = %d ", $filters['strg_id'] );
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
			$cond.= $wpdb->prepare( "AND a.uom_id = %d ", $filters['uom_id'] );
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
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['document_meta']} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id = a.item_id AND {$meta_key}.meta_key = %s ", $meta_key );
			}
		}

		$field.= ", CAST( idx.meta_value AS UNSIGNED ) AS idx ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} idx ON idx.doc_id = a.doc_id AND idx.item_id = a.item_id AND idx.meta_key = '_item_number' ";

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

	public function get_document_meta( $doc_id = 0, $meta_key = '', $item_id = 0, $single = false,$db='' )
	{
		if( ! $doc_id ) return false;
		
		global $wpdb;
		$dbname = $this->dbName();
		if($db) $dbname = $db.'.';
		$cond = $wpdb->prepare( " AND doc_id = %d", $doc_id );
		if( !empty( $meta_key ) ) $cond.= $wpdb->prepare( " AND meta_key = %s", $meta_key );
		$cond.= $wpdb->prepare( " AND item_id = %d", $item_id );
		
		$sql  = "SELECT * FROM {$dbname}{$wpdb->doc_meta} WHERE 1 {$cond} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$metas = array();
		if( $results )
		{
			foreach( $results as $row )
			{
				if( !empty( $meta_key ) )
				{
					$metas[] = $row['meta_value'];
				}
				else
				{
					$metas[$row['meta_key']][] = $row['meta_value'];
				}
			}
		}
		
		if( !empty( $meta_key ) && $single )
		{
			return $metas[0];
		}

		return $metas;
	}
	
	//customized document_action_handle
	// public function cw_action_handle( $action , $header = array() , $details  = array() )
	// {
	// 	if( $this->Notices ) $this->Notices->reset_operation_notice();
	// 	$succ = true;
	// 	$this->user_id = !empty( $this->user_id )? $this->user_id : get_current_user_id();
	// 	$this->user_id = empty( $this->user_id )? 0 : $this->user_id;
	// 	$parent_doc = array();  //Affected Linked Document
	// 	$action = strtolower( $action );

	// 	$doc_time = ( $header['doc_time'] )? $header['doc_time'] : '';
	// 	$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'], $doc_time ) : current_time( 'mysql' );
		
	// 	//Check Accounting Period V1.0.3
	// 	$validDate = $this->document_account_period_handle( $header['doc_id'], $header['posting_date'], $header['warehouse_id'], $action );
	// 	if( ! $validDate )
	// 	{
	// 		if( $this->Notices ) $this->Notices->set_notice( "Not Allowed. Date is out of Accounting Period!", "warning", $this->_doc_type."|document_action_handle" );
	// 		return false;
	// 	}
		
	// 	switch ( $action )
	// 	{
	// 		case "save":
	// 		case "save-post":
	// 		case "update":
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			$header_item['doc_type'] = !empty( $header['doc_type'] )? $header['doc_type'] : $this->doc_type;
	// 			$header_item['lupdate_by'] = $this->user_id;
	// 			$header_item['lupdate_at'] = current_time( 'mysql' );

	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
	// 			{
	// 				//New Created
	// 				$this->temp_data = $header;
	// 				$doc_prefix = 'X';
	// 				$sdocno = $doc_prefix.current_time( 'YmdHis' );
	// 				$header_item['sdocno'] = !empty( $header['sdocno'] )? $header['sdocno'] : $this->cw_generate_docno($sdocno,$this->doc_type,$doc_prefix);
	// 				$this->temp_data = array();
	// 				$header_item['docno'] = empty( $header_item['docno'] ) ? $header_item['sdocno'] : $header_item['docno'];
	// 				$header_item['status'] = isset( $header['hstatus'] )? $header['hstatus'] : 1;
	// 				$header_item['flag'] = ( $this->useFlag )? 0 : 1;
	// 				$header_item['flag'] = isset( $header['hflag'] )? $header['hflag'] : $header_item['flag'];
	// 				$header_item['created_by'] = $this->user_id;
	// 				$header_item['created_at'] = current_time( 'mysql' );
	// 				if( $action == 'save-post' ) 
	// 				{
	// 					$header_item['post_date'] = ( !empty( (int)$header['posting_date'] ) )? date_formating( $header['posting_date'] ) : current_time( 'mysql' );
	// 					$header_item['post_date'] = !empty( (int)$header_item['post_date'] )? $header_item['post_date'] : current_time( 'mysql' );
	// 				}
	// 				$doc_id = $this->cw_add_document_header( $header_item );
	// 				if( ! $doc_id )
	// 				{
	// 					$succ = false;
	// 					if( $this->Notices ) $this->Notices->set_notice( "create-fail", "error", $this->_doc_type.$this->_doc_type."|document_action_handle" );
	// 				}
	// 				$header['doc_id']= $doc_id;

	// 				if( isset( $header_item['hstatus'] ) ){ unset( $header['hstatus'] ); unset( $header_item['hstatus'] ); } 
	// 				$header_item['doc_id'] = $doc_id;
	// 				if( $succ )
	// 				{
	// 					$itm_cnt = 1; //V1.0.6
	// 					//Add Document item
	// 					if( $details && ! $this->_no_details )
	// 					{
	// 						$details = $this->document_items_sorting( $details );
	// 						foreach ( $details as $detail_item )
	// 						{
	// 							$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
	// 							if( ! $this->_allow_empty_bqty && $ditem['bqty'] <= 0 ) continue;
	// 							$ditem['doc_id'] = $header_item['doc_id'];
	// 							$ditem['lupdate_by'] = $this->user_id;
	// 							$ditem['lupdate_at'] = current_time( 'mysql' );
	// 							$ditem['status'] = isset( $detail_item['dstatus'] )? $detail_item['dstatus'] : 1;
								
	// 							$ditem['created_by'] = $this->user_id;
	// 							$ditem['created_at'] = current_time( 'mysql' );
	// 							$ditem['strg_id'] = apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );
	// 							$ditem['uom_id'] = !empty( $ditem['uom_id'] )? $ditem['uom_id'] : '';
								
	// 							if( isset( $ditem['ref_doc_id'] ) && ! $ditem['ref_doc_id'] ) $ditem['ref_doc_id'] = "0";
	// 							if( isset( $ditem['ref_item_id'] ) && ! $ditem['ref_item_id'] ) $ditem['ref_item_id'] = "0";
								
	// 							$detail_id = $this->cw_add_document_items( $ditem );
	// 							if( ! $detail_id )
	// 								$succ = false;
	// 							$ditem['item_id'] = $detail_id;

	// 							$detail_item['item_id']= $ditem['item_id'];
	// 							$detail_item['_item_number'] = $itm_cnt++; //V1.0.6
	// 							$detail_item['strg_id'] = $ditem['strg_id'];
	// 							if( isset( $detail_item['dstatus'] ) ) unset( $detail_item['dstatus'] );
								
	// 							if( $this->_upd_uqty_flag && isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] != "0" ) // V1.0.2 //V1.0.7
	// 							{
	// 								$succ = $this->update_items_uqty_handles( $exist_item , $ditem );
	// 								$parent_doc[$ditem['ref_doc_id']] = $ditem['ref_doc_id'];
	// 								if( $exist_item['ref_doc_id'] != "" && $exist_item['ref_doc_id'] != $ditem['ref_doc_id'] )
	// 								{
	// 									$parent_doc[$exist_item['ref_doc_id']] = $exist_item['ref_doc_id'];
	// 								}
	// 							}

	// 							$this->detail_item[] = $detail_item;
	// 						}
	// 					}
	// 				}
	// 			}
	// 			else  //UPDATE
	// 			{
	// 				//Validation on Document Status V1.0.3
	// 				$exist_header = $this->cw_get_document_header( $header_item['doc_id'] ); 
	// 				if( ! $exist_header )
	// 				{
	// 					if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
	// 					$succ = false;
	// 				} 
	// 				else if ( $exist_header == "0" )
	// 				{
	// 					if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
	// 					$succ = false;
	// 				}
	// 				else if ( count( $this->allowed_status ) > 0 && ! in_array( $exist_header['status'] , $this->allowed_status ) )
	// 				{
	// 					if( $this->Notices ) $this->Notices->set_notice( "Current document not allow to edit", "warning", $this->_doc_type."|document_action_handle" );
	// 					$succ = false;
	// 				}
	// 				if( $succ && $this->useFlag && $exist_header['flag'] != 0 && ! $this->_allow_flagged_edit && 
	// 					!current_user_cans( [ 'wh_support', 'wh_admin_support' ] ) )
	// 				{
	// 					if( $this->Notices ) $this->Notices->set_notice( "Current document not allow to edit", "warning", $this->_doc_type."|document_action_handle" );
	// 					$succ = false;
	// 				}

	// 				if( $succ ) //V1.0.3
	// 				{
	// 					$header_item['docno'] = empty ( $header_item['docno'] ) ? $exist_header['docno'] : $header_item['docno']; //V1.0.3 
	// 					$header_item['docno'] = empty ( $header_item['docno'] ) ? $exist_header['sdocno'] : $header_item['docno']; //V1.0.3 
	// 					$header_defaults = $this->header_defaults; unset( $header_defaults['post_date'] );
	// 					$upd_header = array_map_key( $header_item , $header_defaults ); //V1.0.3 
	// 					$succ = $this->cw_update_document_header( array ( 'doc_id' => $header_item['doc_id'] ) , $upd_header );
	// 				}

	// 				if( $succ )
	// 				{
	// 					$active_item = array(); //For deletion on non active items
	// 					$arr_item_id = array(); //Existing updated item_id
	// 					$exist_items = array(); //Existing updated items

	// 					//Get Submitted item_id - V1.0.1
	// 					if( $details )
	// 					{
	// 						foreach ( $details as $detail_item )
	// 						{
	// 							if( isset( $detail_item['item_id'] ) && ! empty( $detail_item['item_id'] ) ) //V1.0.3
	// 							{
	// 								$arr_item_id[] = $detail_item['item_id'];
	// 							}
	// 						}
	// 					}
	// 					//Get Existing Item - V1.0.1
	// 					if( count($arr_item_id) > 0 )
	// 					{
	// 						$exist_items_arr = $this->cw_get_exists_document_items_by_item_id( $arr_item_id );
	// 						foreach ( $exist_items_arr as $exist_ditems )
	// 						{
	// 							$exist_items[ $exist_ditems['item_id'] ] = $exist_ditems;
	// 						}
	// 					}

	// 					$itm_cnt = 1; //V1.0.6
	// 					//Update Document item
	// 					if( $details && ! $this->_no_details )
	// 					{
	// 						$details = $this->document_items_sorting( $details );
	// 						foreach ( $details as $detail_item )
	// 						{
	// 							$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
	// 							$ditem['doc_id'] = $header_item['doc_id'];
	// 							$ditem['lupdate_by'] = $this->user_id;
	// 							$ditem['lupdate_at'] = current_time( 'mysql' );

	// 							//fix ref null issue
	// 							$ditem['ref_doc_id'] = ( isset( $ditem['ref_doc_id'] ) && $ditem['ref_doc_id'] == "" )? 0 : $ditem['ref_doc_id'];
	// 							$ditem['ref_item_id'] = ( isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] == "" )? 0 : $ditem['ref_item_id'];

	// 							//Check Exists Item - V1.0.1
	// 							if( isset( $ditem['item_id'] ) && $ditem['item_id'] != "" )
	// 							{
	// 								$exist_item = $exist_items[ $ditem['item_id'] ];
	// 							}	
	// 							else 
	// 							{
	// 								$exist_item = $this->cw_get_exists_document_items( $ditem['doc_id'] , $ditem['product_id'] ,$ditem['ref_doc_id'] ,$ditem['ref_item_id'] , $ditem['block']);	
	// 							}
	// 							if( ! $exist_item )
	// 							{
	// 								$ditem['status'] = isset( $detail_item['dstatus'] )? $detail_item['dstatus'] : 1;
	// 								if($exist_header['status']>1) $ditem['status'] = $exist_header['status'];
	// 								$ditem['created_by'] = $this->user_id;
	// 								$ditem['created_at'] = current_time( 'mysql' );
	// 								$ditem['strg_id'] = apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );

	// 								$detail_id = $this->cw_add_document_items( $ditem );
	// 								if( ! $detail_id )
	// 									$succ = false;
	// 								$ditem['item_id'] = $detail_id;
	// 								$detail_item['strg_id'] = $ditem['strg_id'];
	// 							}
	// 							else 
	// 							{
	// 								$upd_item = array_map_key( $ditem , $this->item_defaults );
	// 								$upd_item['strg_id'] = ( $exist_item['strg_id'] > 0 )? $exist_item['strg_id'] : apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );
	// 								if ( ! $this->cw_update_document_items( array ( 'item_id' => $exist_item['item_id']) , $upd_item ) )
	// 								{
	// 									$succ = false;
	// 								}
	// 								$ditem['item_id'] = $exist_item['item_id']; //V1.0.3
	// 								$ditem['strg_id'] = $upd_item['strg_id'];
	// 								$detail_item['strg_id'] = $ditem['strg_id'];
	// 							}
	// 							//UPDATE Used Qty & Status - V1.0.1
	// 							if( $this->_upd_uqty_flag && isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] != "0" ) //V1.0.2 //V1.0.7
	// 							{
	// 								$succ = $this->update_items_uqty_handles( $exist_item , $ditem );
	// 								$parent_doc[$ditem['ref_doc_id']] = $ditem['ref_doc_id'];
	// 								if( $exist_item['ref_doc_id'] != "" && $exist_item['ref_doc_id'] != $ditem['ref_doc_id'] )
	// 								{
	// 									$parent_doc[$exist_item['ref_doc_id']] = $exist_item['ref_doc_id'];
	// 								}
	// 							}

	// 							$active_item[] = $ditem['item_id'];
	// 							$detail_item['item_id']= $ditem['item_id'];
	// 							if( ! isset( $detail_item['_item_number'] ) || $detail_item['_item_number'] <= 0 || $detail_item['_item_number'] == '' ) 
	// 								$detail_item['_item_number'] = $itm_cnt++;; //V1.0.6
								
	// 							$this->detail_item[] = $detail_item;
	// 						}
	// 					}
						
	// 					//Remove deleted item
	// 					if( $succ && ! $this->_no_details )
	// 					{
	// 						$exists_delete_item = $this->cw_get_deletion_document_items( $header_item['doc_id'] , $active_item ); //Item to be deleted

	// 						if ( $exists_delete_item )
	// 						{
	// 							//OFFSET uqty - V1.0.1
	// 							if( $this->_upd_uqty_flag )  //V1.0.7 add condition
	// 								$succ = $this->deleted_items_uqty_handles( $exists_delete_item );
	// 							if( $succ )
	// 								$succ = $this->cw_delete_document_items( $header_item['doc_id'] , $active_item );
	// 						}
	// 					}
	// 				}
	// 			}

	// 			if( $this->_upd_uqty_flag && $succ && count( $parent_doc ) > 0 ) //V1.0.7 add condition
	// 			{
	// 				//Check if valid uqty updated
	// 				if( $this->_ctrl_uqty )
	// 				{
	// 					$invalid_records = $this->get_incorrect_uqty_updates( $parent_doc );
	// 					if ( isset( $invalid_records) && count($invalid_records) > 0 )
	// 						$succ = false;
	// 				}
	// 				//UPDATE Linked Status - V1.0.1
	// 				if( $succ )
	// 				{
	// 					$succ = $this->update_document_header_status_handles( $parent_doc );
	// 				}
	// 			}
	// 			$exist_header = $this->cw_get_document_header( $header_item['doc_id'] );
	// 			$this->header_item = wp_parse_args( $header, $exist_header ); 
	// 		break;
	// 		case "delete":
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			$exists = $this->cw_get_document_header( $header_item['doc_id'], "1" );
	// 			if( ! $exists )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			/*if( $succ && $this->useFlag && $exists['flag'] > 0 )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "Not allow to delete", "warning", $this->_doc_type."|document_action_handle" );
	// 				$succ = false;
	// 			}*/
	// 			if( $succ )
	// 			{
	// 				$del_item = array();
	// 				$del_item['status'] = $this->stat['trash'];
	// 				$del_item['lupdate_by'] = $this->user_id;
	// 				$del_item['lupdate_at'] = current_time( 'mysql' );
	// 				//Inactive Header
	// 				$succ = $this->cw_update_document_header( array( 'doc_id' => $header_item['doc_id'] , 'status' => $exists['status'] ) , $del_item ); //V1.0.3

	// 				//OFFSET uqty - V1.0.1
	// 				$exists_delete_item = $this->cw_get_deletion_document_items( $header_item['doc_id'] ); //Item to be deleted
	// 				if ( $succ && $exists_delete_item && ! $this->_no_details )
	// 				{
	// 					if( $this->_upd_uqty_flag )  //V1.0.7 add condition
	// 						$succ = $this->deleted_items_uqty_handles( $exists_delete_item );
	// 					//Inactive Item
	// 					if( $succ )
	// 						$succ = $this->cw_update_document_items( array( 'doc_id' => $header_item['doc_id'] , 'status' => $exists['status'] ) , $del_item );//V1.0.3
	// 				}
	// 				$exists['status'] = $del_item['status'];
	// 			}
	// 			$this->header_item = $exists;
	// 		break;
	// 		case "post": //V1.0.2 Post Action
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			$exists = $this->cw_get_document_header( $header_item['doc_id'] , $this->_stat_to_post, "1" );
	// 			if( ! $exists )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			else 
	// 			{
	// 				$post_item = array();
	// 				$post_item['status'] = $this->stat['post'];
	// 				$post_item['lupdate_by'] = $this->user_id;
	// 				$post_item['lupdate_at'] = current_time( 'mysql' );
					
	// 				$post_header = $post_item;
	// 				$post_header['post_date'] = ( !empty( (int)$exists['posting_date'] ) )? date_formating( $exists['posting_date'] ) : current_time( 'mysql' );
	// 				$post_header['post_date'] = !empty( (int)$post_header['post_date'] )? $post_header['post_date'] : current_time( 'mysql' );
	// 				//Post Header
	// 				$succ = $this->cw_update_document_header( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_header );//V1.0.3
	// 				if( $succ && ! $this->_no_details )
	// 				{
	// 					//Post Item
	// 					$succ = $this->cw_update_document_items( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_item );//V1.0.3
	// 				}

	// 			}
	// 			$this->header_item = $exists; // = $header; V1.0.4
	// 			$this->detail_item = $this->cw_get_document_items_by_doc( $this->header_item['doc_id'] );
	// 		break;
	// 		case "unpost": //V1.0.3 Un-Post Action
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			$exists = $this->cw_get_document_header( $header_item['doc_id'] , $this->stat['post'] ); //Only Posted Status can be Unpost
	// 			if( ! $exists )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			else 
	// 			{
	// 				$post_item = array(); $post_header = array();
	// 				$post_item['status'] = $this->_stat_to_post;
	// 				$post_item['lupdate_by'] = $this->user_id;
	// 				$post_item['lupdate_at'] = current_time( 'mysql' );
					
	// 				$post_header = $post_item;
	// 				if( $this->useFlag ) $post_header['flag'] = 0;
	// 				//Post Header
	// 				$succ = $this->cw_update_document_header( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_header );
	// 				if( $succ && ! $this->_no_details )
	// 				{
	// 					//Post Item
	// 					$succ = $this->cw_update_document_items( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_item );
	// 				}
	// 			}
	// 			$this->header_item = $exists; // = $header; V1.0.4
	// 			$this->detail_item = $this->cw_get_document_items_by_doc( $this->header_item['doc_id'] );
	// 		break;
	// 		case "confirm":
	// 		case "refute":
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			$exists = $this->cw_get_document_header( $header_item['doc_id'] );
	// 			if( ! $exists )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			else 
	// 			{
	// 				$act_item = array();
	// 				$post_item['status'] = $this->stat[$action];
	// 				$act_item['lupdate_by'] = $this->user_id;
	// 				$act_item['lupdate_at'] = current_time( 'mysql' );
	// 				//Inactive Header
	// 				$succ = $this->cw_update_document_header( array( 'doc_id' => $header_item['doc_id'] , 'status' => $exists['status'], 'flag' => $exists['flag'] ) , $act_item ); //V1.0.3
	// 			}
	// 			$this->header_item = $exists;
	// 			$this->detail_item = $this->cw_get_document_items_by_doc( $this->header_item['doc_id'] );
	// 		break;
	// 		case "approve":
	// 		case "reject":
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			$exists = $this->cw_get_document_header( $header_item['doc_id'], "1", "0" );
	// 			if( ! $exists )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			else 
	// 			{
	// 				$act_item = array();
	// 				$act_item['status'] = 1;
	// 				$act_item['flag'] = $header_item['flag'];
	// 				$act_item['lupdate_by'] = $this->user_id;
	// 				$act_item['lupdate_at'] = current_time( 'mysql' );
	// 				//Inactive Header
	// 				$succ = $this->cw_update_document_header( array( 'doc_id' => $header_item['doc_id'] , 'status' => $exists['status'], 'flag' => $exists['flag'] ) , $act_item ); //V1.0.3
	// 			}
	// 			$exists['flag'] = $act_item['flag'];
	// 			$this->header_item = $exists;
	// 			$this->detail_item = $this->cw_get_document_items_by_doc( $this->header_item['doc_id'] );
	// 		break;
	// 		case "complete":
	// 		case "incomplete":
	// 		case "close":
	// 		case "reopen":
	// 		case "trash":
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			$exists = $this->cw_get_document_header( $header_item['doc_id'] );
	// 			if( ! $exists )
	// 			{
	// 				if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
	// 				return false;
	// 			}
	// 			else 
	// 			{
	// 				$post_item = array();
	// 				$post_item['status'] = $this->stat[$action];
	// 				$post_item['lupdate_by'] = $this->user_id;
	// 				$post_item['lupdate_at'] = current_time( 'mysql' );
					
	// 				$post_header = $post_item;
	// 				//Post Header
	// 				$succ = $this->cw_update_document_header( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_header );//V1.0.3
	// 				if( $succ && ! $this->_no_details )
	// 				{
	// 					//Post Item
	// 					$succ = $this->cw_update_document_items( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_item );//V1.0.3
	// 				}

	// 			}
	// 			$this->header_item = $exists; // = $header; V1.0.4
	// 			$this->detail_item = $this->cw_get_document_items_by_doc( $this->header_item['doc_id'] );
	// 		break;
	// 		case "delete-item":
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) || count( $details ) == 0 )
	// 			{
	// 				return false;
	// 			}
	// 			$arr_item = array();
	// 			//GET Item ID
	// 			foreach ( $details as $detail_item )
	// 			{
	// 				$arr_item[ $detail_item['item_id']] = $detail_item['item_id'];
	// 			}

	// 			$exists_delete_item = $this->get_exists_document_items_by_item_id( $arr_item ); //Item to be deleted
	// 			if ( $exists_delete_item )
	// 			{
	// 				//OFFSET uqty - V1.0.1
	// 				if( $this->_upd_uqty_flag )  //V1.0.7 add condition
	// 					$succ = $this->deleted_items_uqty_handles( $exists_delete_item );
	// 				if( $succ )
	// 				{
	// 					$succ = $this->delete_document_items( $header_item['doc_id'] , $arr_item , false );

	// 					$item_status = $this->get_distinct_document_item_status( array( $header_item['doc_id'] ) );
	// 					if( $item_status ) // V1.0.3
	// 					{
	// 						//Get Document Status
	// 						foreach( $item_status as $item )
	// 						{
	// 							$sta_array = explode( "," , $item['status'] );
	// 							if( count( $sta_array ) == 1 )
	// 							{
	// 								//Update Document Status = Item Status
	// 								$sta_doc_arr[ $sta_array[0] ][] = $item['doc_id'];
	// 							}
	// 							else 
	// 							{
	// 								//More than 1 status = Partial Status
	// 								$sta_doc_arr[ $this->parent_status['partial'] ][] = $item['doc_id'];
	// 							}
	// 						}
	// 					}
	// 					else
	// 					{
	// 						$sta_doc_arr[0][] = $header_item['doc_id'];
	// 					}
	// 					//Update Document Status = Item Status
	// 					if( count( $sta_doc_arr ) > 0 )
	// 					{
	// 						foreach( $sta_doc_arr as $status => $arr_doc_id )
	// 						{	
	// 							if( ! $this->update_document_status( $arr_doc_id , $status ) )
	// 								$succ = false; 
	// 						}
	// 					}
	// 				}
	// 			}
	// 			else
	// 			{
	// 				//NO VALID RECORD FOUND
	// 				$succ = false;
	// 			}
	// 			$this->header_item = $header;
	// 		break;
	// 		case "update-item":
	// 			$header_item = wp_parse_args( $header, $this->header_defaults ); 
	// 			$header_item['doc_type'] = $this->_doc_type;
	// 			if( ! $header_item['doc_id'] || empty($header_item['doc_id']) || count( $details ) == 0 )
	// 			{
	// 				return false;
	// 			}
	// 			$exist_header = $this->get_document_header( $header_item['doc_id'] );
	// 			if( ! $exist_header )
	// 			{
	// 				return false;
	// 			}
				
	// 			$arr_item = array();
	// 			$exist_item = array();
	// 			//GET Item ID
	// 			foreach ( $details as $detail_item )
	// 			{
	// 				$arr_item[ $detail_item['item_id']] = $detail_item['item_id'];
	// 			}
	// 			$exists = $this->get_exists_document_items_by_item_id( $arr_item ); 
	// 			if ( $exists )
	// 			{	
	// 				foreach ( $exists as $item )
	// 				{
	// 					$exist_item[ $item['item_id']] = $item;
	// 				}
	// 				foreach ( $details as $detail_item )
	// 				{ 
	// 					$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
	// 					$exist = $exist_item[ $ditem['item_id'] ];

	// 					$ditem['doc_id'] = $header_item['doc_id'];
	// 					$ditem['lupdate_by'] = $this->user_id;
	// 					$ditem['lupdate_at'] = current_time( 'mysql' );
						
	// 					//fix ref null issue
	// 					$ditem['ref_doc_id'] = ( isset( $ditem['ref_doc_id'] ) && $ditem['ref_doc_id'] == "" )? 0 : $ditem['ref_doc_id'];
	// 					$ditem['ref_item_id'] = ( isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] == "" )? 0 : $ditem['ref_item_id'];
						
	// 					if( ! $exist )
	// 					{
	// 						$ditem['status'] = 1;
	// 						$ditem['created_by'] = $this->user_id;
	// 						$ditem['created_at'] = current_time( 'mysql' );
	// 						$ditem['strg_id'] = apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );

	// 						$detail_id = $this->add_document_items( $ditem );
	// 						if( ! $detail_id )
	// 							$succ = false;
	// 						$ditem['item_id'] = $detail_id;
	// 						$detail_item['strg_id'] = $ditem['strg_id'];
	// 					}
	// 					else
	// 					{
	// 						$upd_item = array_map_key( $ditem , $this->item_defaults );
	// 						unset( $upd_item['strg_id'] );
	// 						if ( ! $this->update_document_items( array ( 'item_id' => $exist['item_id']) , $upd_item ) )
	// 						{
	// 							$succ = false;
	// 						}
	// 						$ditem['item_id'] = $exist['item_id']; //V1.0.3
	// 						$ditem['strg_id'] = $exist['strg_id'];
	// 						$detail_item['strg_id'] = $ditem['strg_id'];
	// 					}

	// 					//UPDATE Used Qty & Status
	// 					if( $this->_upd_uqty_flag && isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] != "0" ) //V1.0.2 //V1.0.7
	// 					{
	// 						$succ = $this->update_items_uqty_handles( $exist , $ditem );
	// 						$parent_doc[$ditem['ref_doc_id']] = $ditem['ref_doc_id'];
	// 						if( $exist_item['ref_doc_id'] != "" && $exist_item['ref_doc_id'] != $ditem['ref_doc_id'] )
	// 						{
	// 							$parent_doc[$exist_item['ref_doc_id']] = $exist_item['ref_doc_id'];
	// 						}
	// 						if( ! $succ )
	// 						{
	// 							break;
	// 						}
	// 					}
						
	// 					$detail_item['item_id']= $ditem['item_id'];
	// 					$this->detail_item[] = $detail_item;	//idw added	for inventory allocation handler
	// 				}
	// 			}
	// 			else 
	// 			{
	// 				foreach ( $details as $detail_item )
	// 				{ 
	// 					$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
	// 					$ditem['doc_id'] = $header_item['doc_id'];
	// 					$ditem['lupdate_by'] = $this->user_id;
	// 					$ditem['lupdate_at'] = current_time( 'mysql' );
						
	// 					//fix ref null issue
	// 					$ditem['ref_doc_id'] = ( isset( $ditem['ref_doc_id'] ) && $ditem['ref_doc_id'] == "" )? 0 : $ditem['ref_doc_id'];
	// 					$ditem['ref_item_id'] = ( isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] == "" )? 0 : $ditem['ref_item_id'];
						
	// 					$ditem['status'] = 1;
	// 					$ditem['created_by'] = $this->user_id;
	// 					$ditem['created_at'] = current_time( 'mysql' );
						
	// 					$ditem['strg_id'] = apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );
						
	// 					$detail_id = $this->add_document_items( $ditem );
	// 					if( ! $detail_id )
	// 						$succ = false;
	// 					$ditem['item_id'] = $detail_id;
	// 					$detail_item['strg_id'] = $ditem['strg_id'];

	// 					//UPDATE Used Qty & Status
	// 					if( $this->_upd_uqty_flag && isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] != "0" ) //V1.0.2 //V1.0.7
	// 					{
	// 						$succ = $this->update_items_uqty_handles( $exist , $ditem );
	// 						$parent_doc[$ditem['ref_doc_id']] = $ditem['ref_doc_id'];
	// 						if( $exist_item['ref_doc_id'] != "" && $exist_item['ref_doc_id'] != $ditem['ref_doc_id'] )
	// 						{
	// 							$parent_doc[$exist_item['ref_doc_id']] = $exist_item['ref_doc_id'];
	// 						}
	// 						if( ! $succ )
	// 						{
	// 							break;
	// 						}
	// 					}
						
	// 					$detail_item['item_id']= $ditem['item_id'];
	// 					$this->detail_item[] = $detail_item;	//idw added	for inventory allocation handler
	// 				}
	// 			}
	// 			if( $this->_upd_uqty_flag && $succ && count( $parent_doc ) > 0 ) //V1.0.7
	// 			{
	// 				//Check if valid uqty updated
	// 				if( $this->_ctrl_uqty )
	// 				{
	// 					$invalid_records = $this->get_incorrect_uqty_updates( $parent_doc );
	// 					if ( isset( $invalid_records) && count($invalid_records) > 0 )
	// 						$succ = false;
	// 				}
	// 				//UPDATE Linked Status - V1.0.1
	// 				if( $succ )
	// 				{
	// 					$succ = $this->update_document_header_status_handles( $parent_doc );
	// 				}
				

	// 			}
	// 			$this->header_item = $header;	//idw added	for inventory allocation handler
	// 		break;
	// 	}
	// 	//results_table( $this->get_deletion_document_items( $header_item['doc_id'] ) );
	// 	//echo "<br />".$succ."--".$action."--". $this->_doc_type. " --->BBBBB"; exit;
		
	// 	//idw_added - to allow further action through hook
	// 	$succ = apply_filters( 'warehouse_after_'.$this->getDocumentType().'_document_action', $succ, $action, $this->header_item, $this->detail_item, $exist_items );
		
	// 	return $succ;
	// }


	// ////CUSTOMIZATION OF DOCUMENT FUNCTION------------------
	// //generate running docno based on warehouse selected
	// public function cw_generate_docno( $sdocno, $doc_type = '', $def_prefix = '', $args = array() )
	// {
	// 	if( empty( $doc_type ) )
	// 		return $sdocno;
		
	// 	global $wcwh;
	// 	$wpdb = $this->db_wpdb;
	// 	$dbname = get_warehouse_meta( $this->warehouse['id'], 'dbname', true );
	// 	$dbname = ( $dbname )? $dbname."." : "";
	// 	$table = "$dbname{$this->tables['doc_runningno']} a ";
	// 	$cond = $wpdb->prepare( " WHERE doc_type = %s ", $doc_type );

	// 	if( !empty( $args ) )
	// 	{
	// 		foreach( $args as $key => $val )
	// 		{
	// 			if( is_array( $val ) )
	// 				$cond .= " AND {$key} IN ('" .implode( "','", $val ). "') ";
	// 			else
	// 				$cond .= $wpdb->prepare( " AND {$key} = %s ", $val );
	// 		}
	// 	}

	// 	$sql = "SELECT * FROM {$table} {$cond} GROUP BY doc_type ";
	// 	$runningno = $wpdb->get_row( $sql, ARRAY_A );
		
	// 	if ( null !== $runningno ) 
	// 	{
	// 		switch( $runningno['type'] )
	// 		{
	// 			case 'default':
	// 			default:
	// 				$prefix = !empty( $runningno['prefix'] )? $runningno['prefix'] : $def_prefix;

	// 				if( $runningno['length'] > 0 )
	// 				{
	// 					$sdocno = $prefix.str_pad( $runningno['next_no'], $runningno['length'], "0", STR_PAD_LEFT ).$runningno['suffix'];
	// 				}
	// 				else
	// 				{
	// 					$sdocno = $prefix.$runningno['suffix'];
	// 				}

	// 				$sdocno = apply_filters( 'wcwh_docno_replacer', $sdocno, $doc_type, $runningno );
					
	// 				if( $runningno['next_no'] > 0 )
	// 				{
	// 					$update_sql = $wpdb->prepare( "UPDATE {$table} SET next_no = next_no + 1 WHERE id = %s ", $runningno['id'] );
	// 					$wpdb->query( $update_sql );
	// 				}
	// 			break;
	// 			case 'random':
	// 				$sdocno = generateSerial( $runningno['length'] );
	// 			break;
	// 			case 'range':
	// 				$sdocno = generateRangeSerial( (int)$runningno['prefix'], (int)$runningno['suffix'] );
	// 			break;
	// 		}
	// 	}
		
	// 	return $sdocno;
	// }

	// //add document header based on warehouse selected
	// public function cw_add_document_header( $item ){
	// 	global $wpdb;
	// 	$dbname= $this->dbName().$this->tables['document'];
	// 	$this->cw_insert_replace_helper(
	// 		$dbname,
	// 		array(
	// 			'docno' 			=> $item['docno'],
	// 			'sdocno' 			=> $item['sdocno'],
	// 			'warehouse_id' 		=> $item['warehouse_id'],
	// 			'doc_type' 			=> $item['doc_type'],
	// 			'doc_date' 			=> $item['doc_date'],
	// 			'post_date'			=> $item['post_date'],
	// 			'status' 			=> $item['status'],
	// 			'flag'				=> $item['flag'],
	// 			'parent'			=> $item['parent'],
	// 			'created_by' 		=> $item['created_by'],
	// 			'created_at' 	    => $item['created_at'],
	// 			'lupdate_by' 		=> $item['lupdate_by'],
	// 			'lupdate_at' 	    => $item['lupdate_at']
	// 		)
	// 	);
	// 	$item_id = absint( $wpdb->insert_id );
	// 	return $item_id;
	// }

	// public function cw_add_document_items( $item ){
	// 	global $wpdb;
	// 	$dbname= $this->dbName().$this->tables['document_items'];
	// 	$this->cw_insert_replace_helper(
	// 		$dbname,
	// 		array(
	// 			'doc_id' 			=> $item['doc_id'],
	// 			'strg_id'			=> $item['strg_id'],
	// 			'product_id' 		=> $item['product_id'],
	// 			'uom_id' 			=> $item['uom_id'],
	// 			'bqty' 				=> $item['bqty'],
	// 			'bunit' 			=> $item['bunit'],
	// 			'ref_doc_id' 		=> $item['ref_doc_id'],
	// 			'ref_item_id' 		=> $item['ref_item_id'],
	// 			'status' 			=> $item['status'],
	// 			'created_by' 		=> $item['created_by'],
	// 			'created_at' 	    => $item['created_at'],
	// 			'lupdate_by' 		=> $item['lupdate_by'],
	// 			'lupdate_at' 	    => $item['lupdate_at']
	// 		)
	// 	);
	// 	$item_id = absint( $wpdb->insert_id );
	// 	return $item_id;
	// }

	// //get document header based on warehouse selected
	// public function cw_get_document_header( $doc_id , $status = '', $flag = '' ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $doc_id ) {
	// 		return false;
	// 	}
	// 	$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document." WHERE doc_id = %d ", $doc_id );

	// 	$fld = "h.*, ma.meta_value AS posting_date ";
	// 	$tbl = "$dbname"."{$this->tables['document']} h ";
	// 	$tbl.= "LEFT JOIN $dbname"."{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'posting_date' ";
	// 	$cond = $wpdb->prepare( "AND h.doc_id = %s ", $doc_id );

	// 	if( $status != '' )
	// 	{
	// 		$cond .= $wpdb->prepare( " AND status = %s ", $status );
	// 	}
	// 	else
	// 	{
	// 		$cond .= $wpdb->prepare( " AND status != %s ", '0' );
	// 	}
	// 	if( $flag != '' )
	// 	{
	// 		$cond .= $wpdb->prepare( " AND flag = %s ", $flag );
	// 	}

	// 	$get_items_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

	// 	return $wpdb->get_row( $get_items_sql , ARRAY_A );
	// }

	// public function cw_get_exists_document_items_by_item_id( $item_id_arr = array() ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	$item_id_arr = array_filter( $item_id_arr );

	// 	if ( ! $item_id_arr  || count( $item_id_arr ) == 0 ) {
	// 		return false;
	// 	}
	// 	$get_items_sql  = "SELECT * FROM ".$dbname.$this->tables['document_items']." WHERE status != 0 ";

	// 	if( count($item_id_arr) > 0 ) 
	// 	{
	// 		$get_items_sql.=" AND item_id IN ( " . implode( ',', $item_id_arr ) . ")";
	// 	}
	// 	return $wpdb->get_results( $get_items_sql , ARRAY_A );
	// }

	// public function cw_get_exists_document_items( $doc_id, $product_id , $ref_doc_id = 0, $ref_item_id = 0 , $block = 0 ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $doc_id  || ! $product_id ) {
	// 		return false;
	// 	}
	// 	if( isset( $block ) && $block > 0 )
	// 	{
	// 		$table = " LEFT JOIN ".$dbname.$this->tables['document_items']." b ON b.doc_id = a.doc_id AND b.item_id = a.item_id AND b.meta_key = 'block' ";
	// 		$fld = ", b.meta_value as block";
	// 		$cond = " AND b.meta_value = '".$block."'";
	// 	}
	// 	else
	// 	{
	// 		$table = "";
	// 		$fld = ", 0 as block";	
	// 		$cond = "";
	// 	}
	// 	$get_items_sql  = $wpdb->prepare( "SELECT a.* ".$fld." FROM ".$dbname.$this->tables['document_items']." a ".$table." WHERE a.doc_id = %d AND a.product_id = %d AND a.status != 0 ".$cond, $doc_id ,$product_id );

	// 	if( $ref_item_id > 0 )
	// 	{
	// 		$get_items_sql .= $wpdb->prepare( " AND a.ref_doc_id = %d AND a.ref_item_id = %d " , $ref_doc_id , $ref_item_id );
	// 	}
	// 	return $wpdb->get_row( $get_items_sql , ARRAY_A );
	// }

	// public function cw_get_document_items_by_doc( $doc_id , $status = 'active' ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $doc_id ) {
	// 		return false;
	// 	}
	// 	$get_items_sql = $wpdb->prepare( "SELECT * FROM ".$dbname.$this->tables['document_items']." WHERE doc_id = %d ", $doc_id );

	// 	if( $status == 'active' )
	// 		$get_items_sql .= " AND status != 0 ";
	// 	else if ( $status >= 0 )
	// 		$get_items_sql .= " AND status = ".$status;

	// 	return $wpdb->get_results( $get_items_sql , ARRAY_A );
	// }

	// public function cw_get_deletion_document_items( $doc_id , $active_items = array() ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $doc_id  ) {
	// 		return false;
	// 	}
	// 	$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$dbname.$this->tables['document_items']." WHERE doc_id = %d AND status != %s ", $doc_id, '0' );

	// 	if( count($active_items) > 0 ) 
	// 	{
	// 		$get_items_sql.=" AND item_id NOT IN ( " . implode( ',', $active_items ) . ")";
	// 	}
	// 	return $wpdb->get_results( $get_items_sql , ARRAY_A );
	// }

	// // document header based on warehouse selected
	// public function cw_update_document_header( $cond , $item ){
	// 	global $wpdb;
	// 	$dbname= $this->dbName();
	// 	if ( ! $cond || ! $item) {
	// 		return false;
	// 	}
	// 	$update = $this->cw_update( $dbname.$this->tables['document'], $item, $cond );

	// 	if ( false === $update ) {
	// 		return false;
	// 	}
	// 	return true;
	// }

	// public function cw_update_document_items( $cond , $item ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $cond || ! $item ) {
	// 		return false;
	// 	}
	// 	$update = $this->cw_update( $dbname.$this->tables['document_items'], $item, $cond );

	// 	if ( false === $update ) {
	// 		return false;
	// 	}
	// 	return true;
	// }

	// public function cw_meta_handle( $doc_id, $header_item, $sanitize = false )
	// {
	// 	if( !$doc_id || !$header_item ) return;
		
	// 	$succ = true;
	// 	$header_keys = $this->header_def;
		
	// 	foreach( $header_item as $key => $value )
	// 	{
	// 		if( !in_array( $key, $header_keys ) )
	// 		{
	// 			$value = isset( $value ) ? $value : '';
	// 			$value = ( $sanitize )? sanitize_text_field( $value ) : $value;
	// 			if( ! $this->cw_add_document_meta_value( $key , $value , $doc_id ) )
	// 			{
	// 				$succ = false;
	// 				break;
	// 			}
	// 		}
	// 	}
		
	// 	return $succ;
	// }

	// public function cw_add_document_meta_value( $meta_key , $meta_value , $doc_id, $item_id = 0 ){
	// 	global $wpdb;
	// 	$succ = true;

	// 	if( ! $doc_id )
	// 	{
	// 		return false;
	// 	}
	// 	$exists = $this->cw_get_doc_meta( $doc_id , $meta_key , $item_id );
	// 	if ( ! $exists )
	// 	{
	// 		if( ! empty ( $meta_value ) ) //DELETE IF EMPTY
	// 		{
	// 			$dbname = $this->dbName();
	// 			$this->cw_insert_replace_helper(
	// 				$dbname.$this->tables['document_meta'],
	// 				array(
	// 					'doc_id' 			=> $doc_id,
	// 					'item_id' 			=> $item_id,
	// 					'meta_key' 			=> $meta_key,
	// 					'meta_value' 		=> $meta_value
	// 				),
	// 				array(
	// 					'%d', '%d', '%s', '%s'
	// 				)
	// 			);
	// 			$meta_id = absint( $wpdb->insert_id ); //V1.0.3
	// 			if( ! $meta_id )
	// 				$succ = false;
	// 		}
	// 	} else {
	// 		if( ! $meta_value || empty ( $meta_value ) ) //DELETE IF EMPTY
	// 		{
	// 			if( ! $this->cw_delete_document_meta( array( "meta_key" => $meta_key , "doc_id" => $doc_id , "item_id" => $item_id ) ) ) //V1.0.3
	// 				return false;
	// 		}
	// 		else //UPDATE IF NOT EMPTY
	// 		{
	// 			$meta_id = $exists['meta_id']; //V1.0.3
	// 			if( ! $this->cw_update_document_meta( $exists['meta_id'] , array( "meta_value" => $meta_value) ) )
	// 			{
	// 				$succ = false;
	// 			}
	// 		}
	// 	}
	// 	//echo "<br />------------------".$exists."--D-".$doc_id."----I-".$item_id."----".$meta_key."-----".$meta_value."----".$item_id."----".$succ;
	// 	return $succ;
	// }

	
	// public function cw_get_doc_meta( $doc_id , $meta_key, $item_id = 0, $val_only = false ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $doc_id || empty ( $meta_key ) ) {
	// 		return false;
	// 	}
	// 	$get_items_sql  = $wpdb->prepare( "SELECT * FROM $dbname".$this->tables['document_meta']." WHERE doc_id = %d AND item_id = %d AND meta_key = %s", $doc_id , $item_id , $meta_key );
	// 	$row = $wpdb->get_row( $get_items_sql , ARRAY_A );
		
	// 	return ( $val_only )? $row['meta_value'] : $row;
	// }

	// public function cw_delete_document_items( $doc_id , $active_items = array() , $excluded = true ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $doc_id ) {
	// 		return false;
	// 	}
	// 	$update_items_sql = $wpdb->prepare( "UPDATE ".$dbname.$this->tables['document_items']." set status = 0 WHERE doc_id = %d AND status != 0 ", $doc_id );

	// 	if( count($active_items) > 0 ) 
	// 	{
	// 		$upd_exclude = $excluded === false ? " IN " : " NOT IN ";
	// 		$update_items_sql.=" AND item_id ".$upd_exclude." ( " . implode( ',', $active_items ) . ")";
	// 	}
	// 	$update = $wpdb->query( $update_items_sql );
	// 	//echo "<BR /> DELETED ITEM : ".$update_items_sql."<BR />";
	// 	if ( false === $update ) {
	// 		return false;
	// 	}
	// 	return true;
	// }

	// public function cw_delete_document_meta( $cond ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $cond ) {
	// 		return false;
	// 	}
	// 	$update = $this->cw_delete( $dbname.$this->tables['document_meta'], $cond );

	// 	if ( false === $update ) {
	// 		return false;
	// 	}
	// 	return true;
	// }

	// /**
	//  *	Update Document Meta
	//  */
	// public function cw_update_document_meta( $meta_id , $item ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	if ( ! $meta_id ) {
	// 		return false;
	// 	}
	// 	$update = $this->cw_update( $dbname.$this->tables['document_meta'], $item, array( 'meta_id' => $meta_id ) );

	// 	if ( false === $update ) {
	// 		return false;
	// 	}
	// 	return true;
	// }

	// public function cw_delete( $table, $where, $where_format = null ) {
	// 	global $wpdb;
	// 	if ( ! is_array( $where ) ) {
	// 		return false;
	// 	}

	// 	$where = $this->process_fields( $table, $where, $where_format );
	// 	if ( false === $where ) {
	// 		return false;
	// 	}

	// 	$conditions = array();
	// 	$values     = array();
	// 	foreach ( $where as $field => $value ) {
	// 		if ( is_null( $value['value'] ) ) {
	// 			$conditions[] = "`$field` IS NULL";
	// 			continue;
	// 		}

	// 		$conditions[] = "`$field` = " . $value['format'];
	// 		$values[]     = $value['value'];
	// 	}

	// 	$conditions = implode( ' AND ', $conditions );

	// 	$sql = "DELETE FROM $table WHERE $conditions";

	// 	$this->check_current_query = false;
	// 	return $this->query( $wpdb->prepare( $sql, $values ) );
	// }

	// public function add_document_meta( $doc_id = 0, $meta_key = '', $meta_value = '', $item_id = 0 )
	// {
	// 	if( !$doc_id || empty( $meta_key ) || empty( $meta_value ) )
	// 		return false;
		
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	$succ=$this->cw_insert_replace_helper(
	// 		$dbname.$this->tables['document_meta'],
	// 		array( 
	// 			'doc_id'	=> $doc_id, 
	// 			'item_id'	=> $item_id,
	// 			'meta_key'	=> $meta_key,
	// 			'meta_value'=> $meta_value
	// 		)
	// 	);
		
	// 	if( $succ )
	// 		return $wpdb->insert_id;
	// 	else
	// 		return false;
	// }

	// public function cw_add_document_meta( $item ){
	// 	global $wpdb;
	// 	$dbname = $this->dbName();
	// 	$this->cw_insert_replace_helper(
	// 		$dbname.$this->tables['document_meta'],
	// 		array(
	// 			'doc_id' 			=> $item['doc_id'],
	// 			'item_id' 			=> $item['item_id'],
	// 			'meta_key' 			=> $item['meta_key'],
	// 			'meta_value' 		=> $item['meta_value']
	// 		),
	// 		array(
	// 			'%d', '%d', '%s', '%s'
	// 		)
	// 	);
	// 	$item_id = absint( $wpdb->insert_id );
	// 	return $item_id;
	// }

	// public function cw_detail_meta_handle( $doc_id, $detail_item, $sanitize = false ){
	// 	if( !$doc_id || !$detail_item ) return;
		
	// 	$succ = true;
	// 	$detail_keys = $this->item_def;
		
	// 	foreach ( $detail_item as $items ){
	// 		if( $items ){
	// 			foreach( $items as $key => $value ){
	// 				$value = isset( $value ) ? $value : '';
	// 				$value = ( $sanitize )? sanitize_text_field( $value ) : $value;
	// 				if( !in_array( $key, $detail_keys ) ){
	// 					if( ! $this->cw_add_document_meta_value( $key , $value , $doc_id, $items['item_id'] ) )
	// 					{
	// 						$succ = false;
	// 						break;
	// 					}
	// 				}
	// 			}
	// 		}
	// 	}
		
	// 	return $succ;
	// }

	
	// public function cw_insert_replace_helper( $table, $data, $format = null, $type = 'INSERT' ) {
	// 	global $wpdb;
	// 	$wpdb->insert_id = 0;

	// 	if ( ! in_array( strtoupper( $type ), array( 'REPLACE', 'INSERT' ) ) ) {
	// 		return false;
	// 	}

	// 	$data = $this->process_fields( $table, $data, $format );
	// 	if ( false === $data ) {
	// 		return false;
	// 	}

	// 	$formats = array();
	// 	$values  = array();
	// 	foreach ( $data as $value ) {
	// 		if ( is_null( $value['value'] ) ) {
	// 			$formats[] = 'NULL';
	// 			continue;
	// 		}

	// 		$formats[] = $value['format'];
	// 		$values[]  = $value['value'];
	// 	}

	// 	$fields  = '`' . implode( '`, `', array_keys( $data ) ) . '`';
	// 	$formats = implode( ', ', $formats );

	// 	$sql = "$type INTO $table ($fields) VALUES ($formats)";

	// 	$wpdb->check_current_query = false;
	// 	return $wpdb->query( $wpdb->prepare( $sql, $values ) );
	// }

	// public function cw_update( $table, $data, $where, $format = null, $where_format = null ) {
	// 	global $wpdb;
	// 	if ( ! is_array( $data ) || ! is_array( $where ) ) {
	// 		return false;
	// 	}

	// 	$data = $this->process_fields( $table, $data, $format );
	// 	if ( false === $data ) {
	// 		return false;
	// 	}
	// 	$where = $this->process_fields( $table, $where, $where_format );
	// 	if ( false === $where ) {
	// 		return false;
	// 	}

	// 	$fields     = array();
	// 	$conditions = array();
	// 	$values     = array();
	// 	foreach ( $data as $field => $value ) {
	// 		if ( is_null( $value['value'] ) ) {
	// 			$fields[] = "`$field` = NULL";
	// 			continue;
	// 		}

	// 		$fields[] = "`$field` = " . $value['format'];
	// 		$values[] = $value['value'];
	// 	}
	// 	foreach ( $where as $field => $value ) {
	// 		if ( is_null( $value['value'] ) ) {
	// 			$conditions[] = "`$field` IS NULL";
	// 			continue;
	// 		}

	// 		$conditions[] = "`$field` = " . $value['format'];
	// 		$values[]     = $value['value'];
	// 	}

	// 	$fields     = implode( ', ', $fields );
	// 	$conditions = implode( ' AND ', $conditions );

	// 	$sql = "UPDATE $table SET $fields WHERE $conditions";

	// 	$wpdb->check_current_query = false;
	// 	return $wpdb->query( $wpdb->prepare( $sql, $values ) );
	// }

	// protected function process_fields( $table, $data, $format ) {
	// 	global $wpdb;
	// 	$data = $this->process_field_formats( $data, $format );
	// 	if ( false === $data ) {
	// 		return false;
	// 	}

	// 	$data = $this->process_field_charsets( $data, $table );
	// 	if ( false === $data ) {
	// 		return false;
	// 	}

	// 	$data = $this->process_field_lengths( $data, $table );
	// 	if ( false === $data ) {
	// 		return false;
	// 	}

	// 	$converted_data = $this->strip_invalid_text( $data );

	// 	if ( $data !== $converted_data ) {
	// 		return false;
	// 	}

	// 	return $data;
	// }

	// /**
	//  * Prepares arrays of value/format pairs as passed to wpdb CRUD methods.
	//  *
	//  * @since 4.2.0
	//  *
	//  * @param array $data   Array of fields to values.
	//  * @param mixed $format Formats to be mapped to the values in $data.
	//  * @return array Array, keyed by field names with values being an array
	//  *               of 'value' and 'format' keys.
	//  */
	// protected function process_field_formats( $data, $format ) {
	// 	global $wpdb;
	// 	$formats          = (array) $format;
	// 	$original_formats = $formats;

	// 	foreach ( $data as $field => $value ) {
	// 		$value = array(
	// 			'value'  => $value,
	// 			'format' => '%s',
	// 		);

	// 		if ( ! empty( $format ) ) {
	// 			$value['format'] = array_shift( $formats );
	// 			if ( ! $value['format'] ) {
	// 				$value['format'] = reset( $original_formats );
	// 			}
	// 		} elseif ( isset( $wpdb->field_types[ $field ] ) ) {
	// 			$value['format'] = $wpdb->field_types[ $field ];
	// 		}

	// 		$data[ $field ] = $value;
	// 	}

	// 	return $data;
	// }

	// /**
	//  * Adds field charsets to field/value/format arrays generated by
	//  * the wpdb::process_field_formats() method.
	//  *
	//  * @since 4.2.0
	//  *
	//  * @param array  $data  As it comes from the wpdb::process_field_formats() method.
	//  * @param string $table Table name.
	//  * @return array|false The same array as $data with additional 'charset' keys.
	//  */
	// protected function process_field_charsets( $data, $table ) {
	// 	global $wpdb;
	// 	foreach ( $data as $field => $value ) {
	// 		if ( '%d' === $value['format'] || '%f' === $value['format'] ) {
	// 			/*
	// 			 * We can skip this field if we know it isn't a string.
	// 			 * This checks %d/%f versus ! %s because its sprintf() could take more.
	// 			 */
	// 			$value['charset'] = false;
	// 		} else {
	// 			$value['charset'] = $wpdb->get_col_charset( $table, $field );
	// 			if ( is_wp_error( $value['charset'] ) ) {
	// 				return false;
	// 			}
	// 		}

	// 		$data[ $field ] = $value;
	// 	}

	// 	return $data;
	// }

	// /**
	//  * For string fields, record the maximum string length that field can safely save.
	//  *
	//  * @since 4.2.1
	//  *
	//  * @param array  $data  As it comes from the wpdb::process_field_charsets() method.
	//  * @param string $table Table name.
	//  * @return array|false The same array as $data with additional 'length' keys, or false if
	//  *                     any of the values were too long for their corresponding field.
	//  */
	// protected function process_field_lengths( $data, $table ) {
	// 	global $wpdb;
	// 	foreach ( $data as $field => $value ) {
	// 		if ( '%d' === $value['format'] || '%f' === $value['format'] ) {
	// 			/*
	// 			 * We can skip this field if we know it isn't a string.
	// 			 * This checks %d/%f versus ! %s because its sprintf() could take more.
	// 			 */
	// 			$value['length'] = false;
	// 		} else {
	// 			$value['length'] = $wpdb->get_col_length( $table, $field );
	// 			if ( is_wp_error( $value['length'] ) ) {
	// 				return false;
	// 			}
	// 		}

	// 		$data[ $field ] = $value;
	// 	}

	// 	return $data;
	// }

	// protected function strip_invalid_text( $data ) {
	// 	$db_check_string = false;

	// 	foreach ( $data as &$value ) {
	// 		$charset = $value['charset'];

	// 		if ( is_array( $value['length'] ) ) {
	// 			$length                  = $value['length']['length'];
	// 			$truncate_by_byte_length = 'byte' === $value['length']['type'];
	// 		} else {
	// 			$length = false;
	// 			/*
	// 			 * Since we have no length, we'll never truncate.
	// 			 * Initialize the variable to false. true would take us
	// 			 * through an unnecessary (for this case) codepath below.
	// 			 */
	// 			$truncate_by_byte_length = false;
	// 		}

	// 		// There's no charset to work with.
	// 		if ( false === $charset ) {
	// 			continue;
	// 		}

	// 		// Column isn't a string.
	// 		if ( ! is_string( $value['value'] ) ) {
	// 			continue;
	// 		}

	// 		$needs_validation = true;
	// 		if (
	// 			// latin1 can store any byte sequence
	// 			'latin1' === $charset
	// 		||
	// 			// ASCII is always OK.
	// 			( ! isset( $value['ascii'] ) && $this->check_ascii( $value['value'] ) )
	// 		) {
	// 			$truncate_by_byte_length = true;
	// 			$needs_validation        = false;
	// 		}

	// 		if ( $truncate_by_byte_length ) {
	// 			mbstring_binary_safe_encoding();
	// 			if ( false !== $length && strlen( $value['value'] ) > $length ) {
	// 				$value['value'] = substr( $value['value'], 0, $length );
	// 			}
	// 			reset_mbstring_encoding();

	// 			if ( ! $needs_validation ) {
	// 				continue;
	// 			}
	// 		}

	// 		// utf8 can be handled by regex, which is a bunch faster than a DB lookup.
	// 		if ( ( 'utf8' === $charset || 'utf8mb3' === $charset || 'utf8mb4' === $charset ) && function_exists( 'mb_strlen' ) ) {
	// 			$regex = '/
	// 				(
	// 					(?: [\x00-\x7F]                  # single-byte sequences   0xxxxxxx
	// 					|   [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx
	// 					|   \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
	// 					|   [\xE1-\xEC][\x80-\xBF]{2}
	// 					|   \xED[\x80-\x9F][\x80-\xBF]
	// 					|   [\xEE-\xEF][\x80-\xBF]{2}';

	// 			if ( 'utf8mb4' === $charset ) {
	// 				$regex .= '
	// 					|    \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
	// 					|    [\xF1-\xF3][\x80-\xBF]{3}
	// 					|    \xF4[\x80-\x8F][\x80-\xBF]{2}
	// 				';
	// 			}

	// 			$regex         .= '){1,40}                          # ...one or more times
	// 				)
	// 				| .                                  # anything else
	// 				/x';
	// 			$value['value'] = preg_replace( $regex, '$1', $value['value'] );

	// 			if ( false !== $length && mb_strlen( $value['value'], 'UTF-8' ) > $length ) {
	// 				$value['value'] = mb_substr( $value['value'], 0, $length, 'UTF-8' );
	// 			}
	// 			continue;
	// 		}

	// 		// We couldn't use any local conversions, send it to the DB.
	// 		$value['db']     = true;
	// 		$db_check_string = true;
	// 	}
	// 	unset( $value ); // Remove by reference.

	// 	if ( $db_check_string ) {
	// 		$queries = array();
	// 		foreach ( $data as $col => $value ) {
	// 			if ( ! empty( $value['db'] ) ) {
	// 				// We're going to need to truncate by characters or bytes, depending on the length value we have.
	// 				if ( isset( $value['length']['type'] ) && 'byte' === $value['length']['type'] ) {
	// 					// Using binary causes LEFT() to truncate by bytes.
	// 					$charset = 'binary';
	// 				} else {
	// 					$charset = $value['charset'];
	// 				}

	// 				if ( $this->charset ) {
	// 					$connection_charset = $this->charset;
	// 				} else {
	// 					if ( $this->use_mysqli ) {
	// 						$connection_charset = mysqli_character_set_name( $this->dbh );
	// 					} else {
	// 						$connection_charset = mysql_client_encoding();
	// 					}
	// 				}

	// 				if ( is_array( $value['length'] ) ) {
	// 					$length          = sprintf( '%.0f', $value['length']['length'] );
	// 					$queries[ $col ] = $this->prepare( "CONVERT( LEFT( CONVERT( %s USING $charset ), $length ) USING $connection_charset )", $value['value'] );
	// 				} elseif ( 'binary' !== $charset ) {
	// 					// If we don't have a length, there's no need to convert binary - it will always return the same result.
	// 					$queries[ $col ] = $this->prepare( "CONVERT( CONVERT( %s USING $charset ) USING $connection_charset )", $value['value'] );
	// 				}

	// 				unset( $data[ $col ]['db'] );
	// 			}
	// 		}

	// 		$sql = array();
	// 		foreach ( $queries as $column => $query ) {
	// 			if ( ! $query ) {
	// 				continue;
	// 			}

	// 			$sql[] = $query . " AS x_$column";
	// 		}

	// 		$this->check_current_query = false;
	// 		$row                       = $this->get_row( 'SELECT ' . implode( ', ', $sql ), ARRAY_A );
	// 		if ( ! $row ) {
	// 			return new WP_Error( 'wpdb_strip_invalid_text_failure' );
	// 		}

	// 		foreach ( array_keys( $data ) as $column ) {
	// 			if ( isset( $row[ "x_$column" ] ) ) {
	// 				$data[ $column ]['value'] = $row[ "x_$column" ];
	// 			}
	// 		}
	// 	}

	// 	return $data;
	// }

	// protected function check_ascii( $string ) {
	// 	if ( function_exists( 'mb_check_encoding' ) ) {
	// 		if ( mb_check_encoding( $string, 'ASCII' ) ) {
	// 			return true;
	// 		}
	// 	} elseif ( ! preg_match( '/[^\x00-\x7F]/', $string ) ) {
	// 		return true;
	// 	}

	// 	return false;
	// }


	// /**
	//  * Strips any invalid characters from the query.
	//  *
	//  * @since 4.2.0
	//  *
	//  * @param string $query Query to convert.
	//  * @return string|WP_Error The converted query, or a WP_Error object if the conversion fails.
	//  */
	// protected function strip_invalid_text_from_query( $query ) {
	// 	// We don't need to check the collation for queries that don't read data.
	// 	$trimmed_query = ltrim( $query, "\r\n\t (" );
	// 	if ( preg_match( '/^(?:SHOW|DESCRIBE|DESC|EXPLAIN|CREATE)\s/i', $trimmed_query ) ) {
	// 		return $query;
	// 	}

	// 	$table = $this->get_table_from_query( $query );
	// 	if ( $table ) {
	// 		$charset = $this->get_table_charset( $table );
	// 		if ( is_wp_error( $charset ) ) {
	// 			return $charset;
	// 		}

	// 		// We can't reliably strip text from tables containing binary/blob columns.
	// 		if ( 'binary' === $charset ) {
	// 			return $query;
	// 		}
	// 	} else {
	// 		$charset = $this->charset;
	// 	}

	// 	$data = array(
	// 		'value'   => $query,
	// 		'charset' => $charset,
	// 		'ascii'   => false,
	// 		'length'  => false,
	// 	);

	// 	$data = $this->strip_invalid_text( array( $data ) );
	// 	if ( is_wp_error( $data ) ) {
	// 		return $data;
	// 	}

	// 	return $data[0]['value'];
	// }

	// /**
	//  * Strips any invalid characters from the string for a given table and column.
	//  *
	//  * @since 4.2.0
	//  *
	//  * @param string $table  Table name.
	//  * @param string $column Column name.
	//  * @param string $value  The text to check.
	//  * @return string|WP_Error The converted string, or a WP_Error object if the conversion fails.
	//  */
	// public function strip_invalid_text_for_column( $table, $column, $value ) {
	// 	if ( ! is_string( $value ) ) {
	// 		return $value;
	// 	}

	// 	$charset = $this->get_col_charset( $table, $column );
	// 	if ( ! $charset ) {
	// 		// Not a string column.
	// 		return $value;
	// 	} elseif ( is_wp_error( $charset ) ) {
	// 		// Bail on real errors.
	// 		return $charset;
	// 	}

	// 	$data = array(
	// 		$column => array(
	// 			'value'   => $value,
	// 			'charset' => $charset,
	// 			'length'  => $this->get_col_length( $table, $column ),
	// 		),
	// 	);

	// 	$data = $this->strip_invalid_text( $data );
	// 	if ( is_wp_error( $data ) ) {
	// 		return $data;
	// 	}

	// 	return $data[ $column ]['value'];
	// }

	// /**
	//  * Find the first table name referenced in a query.
	//  *
	//  * @since 4.2.0
	//  *
	//  * @param string $query The query to search.
	//  * @return string|false $table The table name found, or false if a table couldn't be found.
	//  */
	// protected function get_table_from_query( $query ) {
	// 	// Remove characters that can legally trail the table name.
	// 	$query = rtrim( $query, ';/-#' );

	// 	// Allow (select...) union [...] style queries. Use the first query's table name.
	// 	$query = ltrim( $query, "\r\n\t (" );

	// 	// Strip everything between parentheses except nested selects.
	// 	$query = preg_replace( '/\((?!\s*select)[^(]*?\)/is', '()', $query );

	// 	// Quickly match most common queries.
	// 	if ( preg_match(
	// 		'/^\s*(?:'
	// 			. 'SELECT.*?\s+FROM'
	// 			. '|INSERT(?:\s+LOW_PRIORITY|\s+DELAYED|\s+HIGH_PRIORITY)?(?:\s+IGNORE)?(?:\s+INTO)?'
	// 			. '|REPLACE(?:\s+LOW_PRIORITY|\s+DELAYED)?(?:\s+INTO)?'
	// 			. '|UPDATE(?:\s+LOW_PRIORITY)?(?:\s+IGNORE)?'
	// 			. '|DELETE(?:\s+LOW_PRIORITY|\s+QUICK|\s+IGNORE)*(?:.+?FROM)?'
	// 		. ')\s+((?:[0-9a-zA-Z$_.`-]|[\xC2-\xDF][\x80-\xBF])+)/is',
	// 		$query,
	// 		$maybe
	// 	) ) {
	// 		return str_replace( '`', '', $maybe[1] );
	// 	}

	// 	// SHOW TABLE STATUS and SHOW TABLES WHERE Name = 'wp_posts'
	// 	if ( preg_match( '/^\s*SHOW\s+(?:TABLE\s+STATUS|(?:FULL\s+)?TABLES).+WHERE\s+Name\s*=\s*("|\')((?:[0-9a-zA-Z$_.-]|[\xC2-\xDF][\x80-\xBF])+)\\1/is', $query, $maybe ) ) {
	// 		return $maybe[2];
	// 	}

	// 	/*
	// 	 * SHOW TABLE STATUS LIKE and SHOW TABLES LIKE 'wp\_123\_%'
	// 	 * This quoted LIKE operand seldom holds a full table name.
	// 	 * It is usually a pattern for matching a prefix so we just
	// 	 * strip the trailing % and unescape the _ to get 'wp_123_'
	// 	 * which drop-ins can use for routing these SQL statements.
	// 	 */
	// 	if ( preg_match( '/^\s*SHOW\s+(?:TABLE\s+STATUS|(?:FULL\s+)?TABLES)\s+(?:WHERE\s+Name\s+)?LIKE\s*("|\')((?:[\\\\0-9a-zA-Z$_.-]|[\xC2-\xDF][\x80-\xBF])+)%?\\1/is', $query, $maybe ) ) {
	// 		return str_replace( '\\_', '_', $maybe[2] );
	// 	}

	// 	// Big pattern for the rest of the table-related queries.
	// 	if ( preg_match(
	// 		'/^\s*(?:'
	// 			. '(?:EXPLAIN\s+(?:EXTENDED\s+)?)?SELECT.*?\s+FROM'
	// 			. '|DESCRIBE|DESC|EXPLAIN|HANDLER'
	// 			. '|(?:LOCK|UNLOCK)\s+TABLE(?:S)?'
	// 			. '|(?:RENAME|OPTIMIZE|BACKUP|RESTORE|CHECK|CHECKSUM|ANALYZE|REPAIR).*\s+TABLE'
	// 			. '|TRUNCATE(?:\s+TABLE)?'
	// 			. '|CREATE(?:\s+TEMPORARY)?\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?'
	// 			. '|ALTER(?:\s+IGNORE)?\s+TABLE'
	// 			. '|DROP\s+TABLE(?:\s+IF\s+EXISTS)?'
	// 			. '|CREATE(?:\s+\w+)?\s+INDEX.*\s+ON'
	// 			. '|DROP\s+INDEX.*\s+ON'
	// 			. '|LOAD\s+DATA.*INFILE.*INTO\s+TABLE'
	// 			. '|(?:GRANT|REVOKE).*ON\s+TABLE'
	// 			. '|SHOW\s+(?:.*FROM|.*TABLE)'
	// 		. ')\s+\(*\s*((?:[0-9a-zA-Z$_.`-]|[\xC2-\xDF][\x80-\xBF])+)\s*\)*/is',
	// 		$query,
	// 		$maybe
	// 	) ) {
	// 		return str_replace( '`', '', $maybe[1] );
	// 	}

	// 	return false;
	// }

	// //////////// END OF FUNCTION CUSTOMIZATION

} //class

}
?>