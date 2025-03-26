<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_AssetMovement extends WCWH_CRUD_Controller
{
	protected $tbl = "asset_movement";

	protected $primary_key = "id";

	protected $tables = array();

	protected $doc_type = 'asset_movement';

	protected $Document;

	/**
	 * Constructor for the Period
	 */
	public function __construct() 
	{
		parent::__construct();

		$this->set_db_tables();

		$this->Document = new WC_DocumentTemplate();

		$this->init_hooks();
	}

	public function set_db_tables()
	{
		global $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"main" => $prefix.$this->tbl,
			"asset" 			=> $prefix."asset",
			"asset_meta"		=> $prefix."asset_meta",
			"asset_tree"		=> $prefix."asset_tree",
			"document"			=> $prefix."document",
			"document_items"	=> $prefix."document_items",
			"document_meta"		=> $prefix."document_meta",
			"company"			=> $prefix."company",
			"warehouse"			=> $prefix."warehouse",
			"status"			=> $prefix."status",
		);
	}

	protected function get_defaultFields()
	{
		return array(
			'code'  			=> '',  
			'asset_id' 			=> 0, 
			'asset_no' 			=> '', 
			'location_code' 	=> '',  //client_company_code
			'post_date'		 	=> '',  
  			'end_date'			=> '', 
  			'status'			=> 1,
  			'created_by'		=> 0,
  			'created_at'		=> '',
			'lupdate_by' 		=> 0,
			'lupdate_at' 		=> '',
		);
	}

	/**
	 * init hooks
	 */
	private function init_hooks() 
	{
		remove_all_filters( 'warehouse_asset_movement_filter', 1 );
		//$options = get_option( 'warehouse_option' );
		add_filter( 'warehouse_asset_movement_filter', array( $this, 'asset_movement_handle' ) ,1 , 2 );

		add_filter( 'wcwh_get_available_asset', array( $this, 'get_available_asset' ), 10, 1 );
	}

	public function asset_movement_handle( $action = 'save', $doc_id = 0 )
	{
		if( ! $action || ! $doc_id ) return false;

		$succ = true;
		
		$user_id = get_current_user_id();
		$now = current_time( 'mysql' );

		$container = get_document_meta( $doc_id, 'container', 0, true );
		$exist_movement_code = get_document_meta( $doc_id, 'asset_movement_code', 0, true );

		$action = strtolower( $action );
		switch( $action )
		{
			case 'save':
			case 'update':
			case 'restore':
				if( $container )
				{	
					$client = '';
					$doc = $this->Document->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'none' ], [], true, [] );
					if( $doc )
					{
						$metas = get_document_meta( $doc_id );
						$doc = $this->combine_meta_data( $doc, $metas );
						
						$client = ( $client )? $client : $doc['client_company_code'];
					}

					$available = $this->get_available_asset( [ 'serial'=>$container ] );

					if( $available )
					{	
						$result = array();

						$exists = $this->get_exist_asset_movement( $container, $client );

						if( ! $exists )	//add new movement
						{
							$datas = array();
							$datas['lupdate_by'] = $user_id;
							$datas['lupdate_at'] = $now;
							$datas['created_by'] = $user_id;
							$datas['created_at'] = $now;

							$datas['code'] = $this->generate_serial();
							$datas['asset_no'] = $container;
							$datas['location_code'] = $client;

							foreach( $available as $asset )
							{
								if( $asset['serial'] == $container ) $datas['asset_id'] = $asset['id'];
							}

				     		$datas = wp_parse_args( $datas, $this->get_defaultFields() );

				     		$result = $this->action_handler( 'save', $datas );
							if( ! $result['succ'] )
							{
								$succ = false;
							}

							if( $succ )
							{	
								update_document_meta( $doc_id, 'asset_movement_code', $result['data']['code'] );
							}
						}
						else 	//movement exists; update info
						{
							update_document_meta( $doc_id, 'asset_movement_code', $exists['code'] );
						}
					}
					else
					{
						$succ = false;
					}
				}
				else if( ! $container && $exist_movement_code )
				{
					delete_document_meta( $doc_id, 'asset_movement_code' );

					$movement = $this->get_asset_movement( [ 'code'=>$exist_movement_code ], [], true, [] );
					
					//check if movement hav other documents linkage
					if( $movement )
					{
						$linkages = $this->get_movement_linkage( $movement['location_code'], $movement['asset_no'] );

						if( ! $linkages )//no more linkage; delete current movement
						{
							$datas = [ 'id'=>$movement['id'] ];
							$datas['lupdate_by'] = $user_id;
							$datas['lupdate_at'] = $now;

							$result = $this->action_handler( 'delete', $datas );
							if( ! $result['succ'] )
							{
								$succ = false;
							}
						}
					}
				}
			break;
			case 'delete':
				delete_document_meta( $doc_id, 'asset_movement_code' );

				$movement = $this->get_asset_movement( [ 'code'=>$exist_movement_code ], [], true, [] );
					
				//check if movement hav others document linkage
				if( $movement )
				{
					$linkages = $this->get_movement_linkage( $movement['location_code'], $movement['asset_no'] );

					if( ! $linkages )
					{
						$datas = [ 'id'=>$movement['id'] ];
						$datas['lupdate_by'] = $user_id;
						$datas['lupdate_at'] = $now;

						$result = $this->action_handler( 'delete', $datas );
						if( ! $result['succ'] )
						{
							$succ = false;
						}
					}
				}
			break;
			case 'post':
			case 'unpost':
				if( $container && $exist_movement_code )
				{	
					$movement = $this->get_asset_movement( [ 'code'=>$exist_movement_code ], [], true, [] );

					if( $movement )
					{
						$linkages = $this->get_movement_linkage_by_movement( $movement['code'], -1 );
						$posted = $this->get_movement_linkage_by_movement( $movement['code'], 6 );

						if( null === $linkages || null === $posted )
							$succ = false;

						if( $succ )
						{	
							if( $action == 'post' )
							{
								if( sizeof( $linkages ) == sizeof( $posted ) && $movement['status'] == 1 )
								{
									$datas = [ 'id'=>$movement['id'], 'post_date'=>$now, 'status'=>6 ];
									$datas['lupdate_by'] = $user_id;
									$datas['lupdate_at'] = $now;

									$result = $this->action_handler( 'update', $datas );
									if( ! $result['succ'] )
									{
										$succ = false;
									}
								}
							}
							else if( $action == 'unpost' )
							{
								if( sizeof( $linkages ) != sizeof( $posted ) && $movement['status'] == 6 )
								{
									$datas = [ 'id'=>$movement['id'], 'status'=>1 ];
									$datas['lupdate_by'] = $user_id;
									$datas['lupdate_at'] = $now;

									$result = $this->action_handler( 'update', $datas );
									if( ! $result['succ'] )
									{
										$succ = false;
									}
								}
							}
						}
					}
				}
			break;
		}

		return $succ;
	}
	
	/**
	 *	Add/Update Asset Movement Handler
	 */
	public function action_handler( $action, $datas = array(), $obj = array() )
	{
		$succ = true;

		if( ! $action || ! $datas )
		{
			$succ = false;
		}

		$outcome = array();

		if( $succ )
		{
			$exist = array();

			$action = strtolower( $action );
			switch ( $action )
			{
				case "save":
				case "update":
					$id = ( isset( $datas['id'] ) && !empty( $datas['id'] ) )? $datas['id'] : "0";

					if( $id != "0" )	//update
					{
						$exist = $this->select( $id );
						if( null === $exist )
						{
							$succ = false;
						}
						else 
						{
							$result = $this->update( $id, $datas );
							if ( false === $result )
							{
								$succ = false;
							}
						}
					}
					else
					{
						$id = $this->create( $datas );
						if ( ! $id )
						{
							$succ = false;
						}
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
				case "delete":
					$id = $datas['id'];
					if( $id > 0 )
					{
						$exist = $this->select( $id );
						if( null === $exist )
						{
							$succ = false;
						}
						else
						{
							$datas['status'] = 0;

							if( $succ )
							{
								$result = $this->update( $id, $datas );
								if( false === $result )
								{
									$succ = false;
								}
							}
						}
					}
					else 
					{
						$succ = false;
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
			}
		}
		
		$outcome['succ'] = $succ; 
		$outcome['data'] = $datas;

		return $this->after_handler( $outcome, $action , $datas, $metas, $obj );
	}

	protected function generate_serial()
	{
		$serial = '';
		$isUnique = true;
		do 
		{
			$serial = apply_filters( 'warehouse_generate_docno', $serial, $this->doc_type );
			$result = $this->get_asset_movement( [ 'code' => $serial ], [], true );

			if( $result ) 
				$isUnique = false;
			else
				$isUnique = true;
		} while( ! $isUnique ); 

		return $serial;
	}

	/*
		SELECT a.* FROM wp_stmm_wcwh_asset a 
		LEFT JOIN ( 
			SELECT * FROM wp_stmm_wcwh_asset_movement WHERE status = 6 GROUP BY asset_no ORDER BY created_at DESC 
		) b ON b.asset_no = a.serial  
		WHERE 1 AND b.code IS NULL
		GROUP BY a.serial ORDER BY a.name
	*/
	public function get_available_asset( $args = array() )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$field = "a.* ";
		$table = "{$this->tables['asset']} a ";
		
		$subSql = "SELECT * FROM {$this->tables['main']} WHERE status = 6 GROUP BY asset_no ORDER BY created_at DESC ";
		$table.= "LEFT JOIN ( {$subSql} ) b ON b.asset_no = a.serial ";

		$cond = "AND b.code IS NULL ";
		$ord = "GROUP BY a.serial ORDER BY a.name";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ;";
		
		return $wpdb->get_results( $sql , ARRAY_A );

		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				if( is_array( $val ) )
					$cond .= " AND a.{$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND a.{$key} = %s ", $val );
			}
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ;";
		
		return $wpdb->get_row( $sql , ARRAY_A );
	}

	public function get_exist_asset_movement( $container = '', $client = '', $status = 1 )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$field = "a.* ";
		$table = "{$this->tables['main']} a ";

		$cond = $wpdb->prepare( "AND a.asset_no = %s AND status = %s AND location_code = %s ", $container, $status, $client );

		$ord = "";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ;";
		
		return $wpdb->get_row( $sql , ARRAY_A );
	}

	public function get_movement_linkage( $location_code, $asset_no )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$field = "a.*, b.meta_value AS container, c.meta_value AS client ";
		$table = "{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key='container' ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} c ON b.doc_id = a.doc_id AND c.item_id = 0 AND b.meta_key='client_company_code' ";

		$cond = $wpdb->prepare( 
			"AND c.meta_value = %s AND b.meta_value = %s AND a.status != %s ", 
			$location_code, 
			$asset_no, 
			0
		);

		$ord = "";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ;";

		return $wpdb->get_results( $sql , ARRAY_A );
	}

	public function get_movement_linkage_by_movement( $code, $status = 1, $args = array() )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$field = "a.*, b.meta_value AS code ";
		$table = "{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$this->tables['document_meta']} b ON b.doc_id = a.doc_id AND b.item_id = 0 AND b.meta_key='asset_movement_code' ";
		$cond = $wpdb->prepare( "AND b.meta_value = %s ", $code );

		$isCompany = ( $args && $args['company'] )? true : false;
		$isWarehouse = ( $args && $args['warehouse'] )? true : false;
		$isClient = ( $args && $args['client'] )? true : false;
		//joins
		if( $isWarehouse || $isCompany )
		{
			$field.= ", wh.id AS wh_id, wh.name AS wh_name, wh.code AS wh_code ";
			$table.= "LEFT JOIN {$this->tables['warehouse']} wh ON wh.code = a.warehouse_id ";
		}
		if( $isCompany )
		{
			$field.= ", comp.id AS comp_id, comp.name AS comp_name, comp.code AS comp_code, comp.custno ";
			$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.id = wh.comp_id ";
		}
		if( $isClient )
		{
			$field.= ", ccomp.id AS client_id, ccomp.code AS client_code, ccomp.name AS client_name ";
			$table.= "LEFT JOIN {$this->tables['document_meta']} c ON c.doc_id = a.doc_id AND c.item_id = 0 AND c.meta_key='client_company_code' ";
			$table.= "LEFT JOIN {$this->tables['company']} ccomp ON ccomp.code = c.meta_value ";
		}

		if( $status >= 0 )
			$cond.= $wpdb->prepare( "AND a.status = %d ", $status );
		else
			$cond.= $wpdb->prepare( "AND a.status != %d ", 0 );

		$ord = "";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ;";

		return $wpdb->get_results( $sql , ARRAY_A );
	}

	/**
	 *	Get Asset Movement
	 */
	public function get_asset_movement( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$field = "a.* ";
		$table = "{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";
		
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( $value == "" || $value === null ) unset( $filters[$key] );
			}
		}

		$isAsset = ( $args && $args['asset'] )? true : false;
		$isCompany = ( $args && $args['company'] )? true : false;
		$isWarehouse = ( $args && $args['warehouse'] )? true : false;
		//joins
		if( $isAsset )
		{
			$field.= ", ast.id AS ast_id, ast.name AS asset_name, ast.code AS asset_code, ast.serial AS asset_serial, ast.type AS asset_type ";
			$table.= "LEFT JOIN {$this->tables['asset']} ast ON ast.serial = a.asset_no ";
		}
		if( $isCompany || $isWarehouse )
		{
			$field.= ", comp.id AS comp_id, comp.name AS comp_name, comp.code AS comp_code, comp.custno ";
			$table.= "LEFT JOIN {$this->tables['company']} comp ON comp.code = a.location_code ";
		}
		if( $isWarehouse )
		{
			$field.= ", wh.id AS wh_id, wh.name AS wh_name, wh.code AS wh_code ";
			$table.= "LEFT JOIN {$this->tables['warehouse']} wh ON wh.comp_id = comp.id ";
			if( isset( $filters['wh_code'] ) )
			{
				$cond.= $wpdb->prepare( "AND wh.code = %s ", $filters['wh_code'] );
			}
		}

		$cond = "";
		if( isset( $filters['id'] ) )
		{
			if( is_array( $filters['id'] ) )
				$cond.= "AND a.id IN ('" .implode( "','", $filters['id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.id = %d ", $filters['id'] );
		}
		if( isset( $filters['not_id'] ) )
		{
			if( is_array( $filters['not_id'] ) )
				$cond.= "AND a.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.id != %d ", $filters['not_id'] );
		}
		if( isset( $filters['code'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
		}
		if( isset( $filters['asset_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.asset_id = %s ", $filters['asset_id'] );
		}
		if( isset( $filters['asset_no'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.asset_no = %s ", $filters['asset_no'] );
		}
		if( isset( $filters['location_code'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.location_code = %s ", $filters['location_code'] );
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
               $cd[] = "a.code LIKE '%".$kw."%' ";
				$cd[] = "a.asset_no LIKE '%".$kw."%' ";

				if( $isAsset )
				{
					$cd[] = "ast.name LIKE '%".$kw."%' ";
					$cd[] = "ast.code LIKE '%".$kw."%' ";
				}
				if( $isCompany )
				{
					$cd[] = "comp.name LIKE '%".$kw."%' ";
				}
				if( $isWarehouse )
				{
					$cd[] = "wh.name LIKE '%".$kw."%' ";
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

            $table.= "LEFT JOIN {$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
        }

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'a.code' => 'ASC' ];
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

	public function count_statuses()
	{
		$wpdb = $this->db_wpdb;

		$fld = "'all' AS status, COUNT( status ) AS count ";
		$tbl = "{$this->tables['main']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( status ) AS count ";
		$tbl = "{$this->tables['main']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );
		$group = "GROUP BY status ";
		$sql2 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$sql = $sql1." UNION ALL ".$sql2;

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
}

new WC_AssetMovement();
?>