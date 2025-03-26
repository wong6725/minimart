<?php

if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TaskChecklist_Class" ) ) 
{

class WCWH_TaskChecklist_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_task_checklist";

	protected $tbl = "document_items";

	// protected $primary_key = "meta_id";

	protected $tables = array();

	public $Notices;
	public $className = "TaskChecklist_Class";

	private $doc_type = 'task_schedule';

	public $useFlag = false;

	public $processing_stat = [];

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();

		$this->setDocumentType( $this->doc_type );

		$stats = $this->getStat();
		$this->setStat( $stats );
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

		);
	}


	public function update_metas($id, $metas)
	{
	    if (!$id || !$metas) return false;

	    foreach ($metas as $key => $value) {
	        if (is_array($value)) {
	            delete_document_meta($id, $key);
	            foreach ($value as $val) {
	                add_document_meta($id, $key, $val);
	            }
	        } else {
	            update_document_meta($id, $key, $value);
	        }
	    }

	    return true;
	}

	//filtering checked data
	public function filterDataChecked($array_one, $array_two)
	{
		foreach ($array_two as $data) {
			$matching = false;

			foreach ($array_one as $array_one_data) {
				if ($data['doc_id'] === $array_one_data['doc_id'] && $data['doc_date'] === $array_one_data['doc_date'] && array_diff($data['serial2'], $array_one_data['serial2']) == []) 
				{
					$matching = true;
					break;
				}
			}

			if ($matching) {
				$filtered_array_two[] = $data;
			}
		}

		return $filtered_array_two;
	}

	//validate data(complete document tasks)
	public function validate_filter_data($id){
		$filters = array( 'wh_code' => "1025-MWT3", 'section' => "wh_task_schedule", 'ref_id' => $id);
			$exists = apply_filters('wcwh_get_sync', $filters);
			$newArray = [];
			foreach($exists as $key => $data){
				$exists[$key] = json_decode($exists[$key]['details'],true);
				$exists[$key] = $exists[$key][0];

				foreach ($exists[$key]['serial2'] as $value) {
					$newItem = $exists[$key];
					$newItem['serial2'] = $value;
					$newArray[] = $newItem;
				}
			}


			$check = $this->get_checklist(array('doc_id' => $id, ),[],false,[],[],[]);
			$check = ( $check )? $check : array();

			foreach($check as $key => $value){
				$info = explode(":",$check[$key]['raw_data'], 3);
				$check[$key]['doc_id'] = $info[0];
				$check[$key]['serial2'] = $info[1];
				$check[$key]['doc_date'] = $info[2];
				unset($check[$key]['raw_data']);
			}

			$result = [];

			foreach($check as $item) {
				$docDate = $item['doc_date'];
				if(!isset($result[$docDate])) {
					$result[$docDate] = [
						"doc_id" => $item['doc_id'],
						"checklist" => $item['checklist'],
						"doc_date" => $item['doc_date'],
						"serial2" => []
					];
				}
				$result[$docDate]['serial2'][] = $item['serial2'];
			}

			$check = array_values($result);

			$exists = $this->filterDataChecked($check, $exists);

			return $exists;
	}
	
	public function child_action_handle( $action , $header = array() , $details = array() )
	{	
		$succ = true;
		$outcome = array();
		$user_id = get_current_user_id();
		$now = current_time( 'mysql' );

		//wpdb_start_transaction();
		
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
				// $succ = $this->detail_meta_handle( $doc_id, $this->detail_item );
				// $metas = array(
                // 	'serial2' => isset($header_item['serial2']) ? $header_item['serial2'] : array()
	            // );
	            // if ($succ) {
	            //     $succ = $this->update_metas($doc_id, $metas);
	            // }

			break;
			case "delete":
			case "approve":
			case "reject":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
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
			case "complete":
				// $succ = $this->document_action_handle( "update" , $header , $details );
			break;
			case "incomplete":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
		}	
		
		$this->succ = apply_filters( "after_{$this->doc_type}_handler", $succ, $header, $details );
		
		//wpdb_end_transaction( $succ );

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
		$tbl = "{$dbname}{$this->tables['document_items']} a ";
		$tbl.="LEFT JOIN {$this->tables['document']} b ON a.doc_id = b.doc_id AND b.doc_type = 'task_schedule' AND b.status = 6 ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		// $cond.= $wpdb->prepare( "AND b.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND b.warehouse_id = %s ", $wh );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "a.status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document_items']} a ";
		$tbl.="LEFT JOIN {$this->tables['document']} b ON a.doc_id = b.doc_id AND b.doc_type = 'task_schedule' AND b.status = 6 ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		// $cond.= $wpdb->prepare( "AND b.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND b.warehouse_id = %s ", $wh );
		$group = "GROUP BY a.status ";

		$sql2 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$sql = $sql1." UNION ALL ".$sql2;

		if( $this->processing_stat )
		{
			$fld = "'process' AS status, COUNT( a.status ) AS count ";
			$tbl = "{$dbname}{$this->tables['document_items']} a ";
			$tbl.="LEFT JOIN {$this->tables['document']} b ON a.doc_id = b.doc_id AND b.doc_type = 'task_schedule' AND b.status = 6 ";
			$cond = "AND a.status IN( ".implode( ', ', $this->processing_stat )." ) ";
			// $cond.= $wpdb->prepare( "AND b.doc_type = %s ", $this->doc_type );
			if( $wh ) $cond.= $wpdb->prepare( "AND b.warehouse_id = %s ", $wh );
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

	public function get_doc_id_by_item($id, $single = true){
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$field = "doc_id ";
		$table = "{$this->tables['document_items']} ";
		$cond ="";
		$cond.= $wpdb->prepare( "AND item_id = %s ", $id );

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}
	

	

	public function get_checklist( $filters = array(), $single = false )
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
			}
		}

		$field = "a.doc_id, a.docno, a.sdocno, a.doc_date, a.post_date, a.doc_type, a.status AS dstatus, b.item_id, b.bqty, b.ref_doc_id, b.ref_item_id, b.status AS hstatus, b.created_at, b.lupdate_at, c.meta_value AS _serial2, d.meta_value AS _item_number ";
		$table = "{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$this->tables['document_items']} b ON a.doc_id = b.doc_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON b.item_id = c.item_id AND c.meta_key = '_serial2' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON b.item_id = d.item_id AND d.meta_key = '_item_number' ";

		$cond = "";
		$cond.= $wpdb->prepare( "AND b.status > %d ", 0 );
		$grp = "";
		$ord = "";
		$l = "";


		if( isset( $filters['doc_id'] ) && !empty( $filters['doc_id']))
		{
			$cond.= $wpdb->prepare( "AND a.doc_id = %s ", $filters['doc_id'] );
			unset($filters['doc_id']);
		}
		if( isset( $filters['dstatus'] ) && !empty( $filters['dstatus']))
		{
			$cond.= $wpdb->prepare( "AND a.status = %d ", $filters['dstatus'] );
			unset($filters['dstatus']);
		}else{
			$cond.= $wpdb->prepare( "AND a.status > %d ", 0 );
		}
		if( isset( $filters['hstatus'] ) && !empty( $filters['hstatus']))
		{
			$cond.= $wpdb->prepare( "AND b.status = %s ", $filters['hstatus'] );
			unset($filters['hstatus']);
		}
		if( isset( $filters['bqty'] ) && !empty( $filters['bqty']))
		{
			if($filters['bqty'] == 'uncheck'){
				$cond.= $wpdb->prepare( "AND b.bqty = %d ", 0 );
				unset($filters['bqty']);
			}else{
				$cond.= $wpdb->prepare( "AND b.bqty >= %d ", $filters['bqty'] );
				unset($filters['bqty']);
			}
		}
		


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


	public function get_schedule( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
			}
		}


		$field = "a.*,b.*, c.meta_value AS _serial2, d.meta_value AS _item_number, e.meta_value AS remark, f.meta_value AS recursive_period ";
		$table = "{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$this->tables['document_items']} b ON a.doc_id = b.doc_id ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON b.item_id = c.item_id AND c.meta_key = '_serial2' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} d ON b.item_id = d.item_id AND d.meta_key = '_item_number' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} e ON a.doc_id = e.doc_id AND e.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} f ON a.doc_id = f.doc_id AND f.meta_key = 'recursive_period' ";
		$cond = "";
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", "task_schedule" );
		
		$grp = "";
		$ord = "";
		$l = "";

		$cond.= $wpdb->prepare( "AND a.status = %d ", 6 );

		if( ! isset( $filters['status'] ) || ( !is_array($filters['status']) && isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
        {
            unset( $filters['status'] );
			$cond.= $wpdb->prepare( "AND b.status > %d ", -1 );

            $table.= "LEFT JOIN {$dbname}{$this->_tbl_status} stat ON stat.status = b.status AND stat.type = 'default' ";
			$corder["stat.order"] = "DESC";
            
        }
        if( isset( $filters['status'] ) )
        {   
        	if( $filters['status'] == 'process' && $this->processing_stat )
        	{
        		$cond.= "AND b.status IN( ".implode( ', ', $this->processing_stat )." ) ";

        		$table.= "LEFT JOIN {$dbname}{$this->_tbl_status} stat ON stat.status = b.status AND stat.type = 'default' ";
				$corder["stat.order"] = "DESC";
        	}
        	else
			{
				if( is_array( $filters['status'] ) )
					$cond.= "AND b.status IN ('" .implode( "','", $filters['status'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND b.status = %d ", $filters['status'] );
			}
        }

		if( isset( $filters['doc_id'] ) && !empty( $filters['doc_id']))
		{
			$cond.= $wpdb->prepare( "AND a.doc_id >= %d ", $filters['doc_id'] );			
			unset($filters['doc_id']);
		}

		if( isset( $filters['warehouse_id'] ) )
		{
			if( is_array( $filters['warehouse_id'] ) )
				$cond.= "AND a.warehouse_id IN ('" .implode( "','", $filters['warehouse_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}

		if( $args['meta'] )
		{
			foreach( $args['meta'] as $meta_key )
			{
				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->_tbl_document_meta} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id = 0 AND {$meta_key}.meta_key = %s ", $meta_key );

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

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
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

        //order
		if( empty( $order ) )
		{
			$order = [ 'a.doc_date' => 'DESC', 'a.doc_id' => 'DESC' ];
        	// $order = array_merge( $corder, $order );
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
       
		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		return $results;
	}


	public function get_export_data( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$header_meta = [ 'remark', 'client_company_code', 'recursive_period' ];
		$detail_meta = [ '_item_number', '_serial2' ];
		
		$field = "a.doc_id, a.warehouse_id, a.docno, a.sdocno, a.doc_date, a.post_date, a.doc_type, a.status AS hstatus, a.flag AS hflag, a.parent ";
		$table = "{$this->tables['document']} a ";
		
		$client_company_code_key = '';
		if( $header_meta )
		{
			foreach( $header_meta as $i => $meta_key )
			{
				$k = 'h'.($i+1);
				$field.= ", {$k}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$this->_tbl_document_meta} {$k} ON {$k}.doc_id = a.doc_id AND {$k}.item_id = 0 AND {$k}.meta_key = %s ", $meta_key );
				
				if( $meta_key == 'client_company_code' ) $client_company_code_key = $k;
			}
		}
		
		$field.= ", b.item_id, b.strg_id, b.uom_id, b.bqty, b.uqty, b.bunit, b.uunit, '0' AS ref_doc_id, '0' AS ref_item_id, b.status AS dstatus ";
		$table.= "LEFT JOIN {$this->tables['document_items']} b ON b.doc_id = a.doc_id ";
		// $table.= "LEFT JOIN {$this->tables['items']} c ON c.id = b.product_id ";
		if( $detail_meta )
		{
			foreach( $detail_meta as $i => $meta_key )
			{
				$k = 'd'.($i+1);
				if( $meta_key == '_item_number' )
					$field.= ", CAST( {$k}.meta_value AS UNSIGNED ) AS {$meta_key} ";
				else
					$field.= ", IFNULL( {$k}.meta_value, '' ) AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$this->_tbl_document_meta} {$k} ON {$k}.doc_id = a.doc_id AND {$k}.item_id = b.item_id AND {$k}.meta_key = %s ", $meta_key );
			}
		}
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status = %d ", $this->doc_type, 6 );
		$grp = "";
		$ord = "";
		$l = "";

		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
			}
		}

		if( !empty( $filters ) )
		{
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
			if( isset( $filters['doc_id'] ) && !empty( $filters['doc_id'] )  )
			{
				$cond.= $wpdb->prepare( "AND a.doc_id = %s ", $filters['doc_id'] );
				unset( $filters['doc_id'] );
			}
			if( isset( $filters['client_company_code'] ) && !empty( $filters['client_company_code'] )  )
			{
				$cond.= $wpdb->prepare( "AND {$client_company_code_key}.meta_value = %s ", $filters['client_company_code'] );
				unset( $filters['client_company_code'] );
			}
			// if( isset( $filters['recursive_period'] ) && !empty( $filters['recursive_period'] )  )
			// {
			// 	$cond.= $wpdb->prepare( "AND {$recursive_period}.meta_value = %s ", $filters['recursive_period'] );
			// 	unset( $filters['recursive_period'] );
			// }

			foreach( $filters as $key => $val )
			{
				if( is_array( $val ) )
					$cond .=  " AND a.{$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND a.{$key} = %s ", $val );
			}
		}
		$cond.= $wpdb->prepare( "AND a.status > %d ", 0 );

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'a.doc_id' => 'ASC', '_item_number' => 'ASC' ];
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
	
} //class

}
?>