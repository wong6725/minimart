<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Storage_Class" ) ) 
{

class WCWH_Storage_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_storage";

	protected $tbl = "storage";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "Storage_Class";

	public $update_tree_child = true;
	public $one_step_delete = false;
	public $true_delete = false;
	public $useFlag = false;

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();
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
			"main" 			=> $prefix.$this->tbl,
			"tree"			=> $prefix.$this->tbl."_tree",
			"meta"			=> $prefix.$this->tbl."meta",
			"storage_group"		=> $prefix."storage_group_rel",
			"storage_term"		=> $prefix."storage_term_rel",
			"status"		=> $prefix."status",
		);
	}
	
	public function update_metas( $id, $metas )
	{
		if( !$id || ! $metas ) return false;
		
		foreach( $metas as $key => $value )
		{
			if( is_array( $value ) )
			{
				delete_storage_meta( $id, $key );
				foreach( $value as $val )
				{
					add_storage_meta( $id, $key, $val );
				}
			}
			else
			{
				update_storage_meta( $id, $key, $value );
			}
		}
		
		return true;
	}
	
	public function delete_metas( $id )
	{
		if( ! $id ) return false;
		
		$metas = get_storage_meta( $id );
		if( $metas )
		{
			foreach( $metas as $key => $value )
			{
				delete_storage_meta( $id, $key );
			}
		}
		
		return true;
	}

	public function action_handler( $action, $datas = array(), $metas = array(), $obj = array() )
	{
		if( $this->Notices ) $this->Notices->reset_operation_notice();
		$succ = true;

		if( ! $this->tables || ! $action || ! $datas )
		{
			$succ = false;
			if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->className."|action_handler" );
		}

		$outcome = array();

		if( $succ )
		{
			$exist = array();

			$Tree = array();
			if( $this->tables['tree'] )
			{
				$Tree = new WCWH_TreeAction( $this->tables['tree'] );
			}

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
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						if( $succ && $this->useFlag && $exist['flag'] != 0 )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "prevent-action", "error", $this->className."|action_handler|".$action );
						}
						if( $succ ) 
						{
							$result = $this->update( $id, $datas );
							if ( false === $result )
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
							}
							else
							{
								if( $metas && method_exists( $this, 'update_metas' ) ) $this->update_metas( $id, $metas );
							}
						}
					}
					else
					{
						$id = $this->create( $datas );
						if ( ! $id )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "create-fail", "error", $this->className."|action_handler|".$action );
						}
						else
						{
							if( $metas && method_exists( $this, 'update_metas' ) ) $this->update_metas( $id, $metas );
						}
					}

					if( $succ )
					{
						$outcome['id'] = $id;

						//Tree handling
						if( $Tree )
						{
							$tree_data = [ "descendant" => $id, "ancestor" => ( $datas["parent"] == 0 )? "" : $datas["parent"] ];
			    			$child_list = $Tree->getTreePaths( [ "ancestor" => $id ] );

			                if( ! $Tree->action_handler( "save" , $tree_data, $this->update_tree_child ) )
			                {
			                    $succ = false;
			                    if( $this->Notices ) $this->Notices->set_notices( $Tree->Notices->get_operation_notice() );
			                }

			                if( $succ && $this->update_tree_child )
			                {
			                	$succ = $this->update_childs_parent( $tree_data, $child_list );
			                }
						}
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
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						if( $succ && $this->useFlag && $exist['flag'] > 0 )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "prevent-action", "error", $this->className."|action_handler|".$action );
						}
						if( $succ ) 
						{
							if( isset( $exist['status'] ) )
							{
								if( $exist['status'] == 1 )
								{
									$datas['status'] = 0;
									$result = $this->update( $id, $datas );
									if( false === $result )
									{
										$succ = false;
										if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
									}
								}
							}
							else
							{
								$result = $this->delete( $id );
								if( $result === false )
								{
									$succ = false;
									$this->Notices->set_notice( "delete-fail", "error", $this->className."|action_handler|".$action );
								}
							}
						}
					}
					else 
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
				case "delete-permanent":
					$deleted = false;
					$tree_data = [];
					$child_list = [];

					$id = $datas['id'];
					if( $id > 0 )
					{
						$exist = $this->select( $id );
						if( null === $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						else
						{
							if( $Tree )
							{
								$tree_data = [ "descendant" => $id, "ancestor" => ( $datas["parent"] == 0 )? "" : $datas["parent"] ];
		    					$child_list = $Tree->getTreePaths( [ "ancestor" => $id ] );
							}

							if( isset( $exist['status'] ) )
							{
								if( $this->one_step_delete || ( !$this->one_step_delete && $exist['status'] == 0 ) )
								{
									$datas['status'] = -1;
									if( $this->true_delete )
										$result = $this->delete( $id );
									else
										$result = $this->update( $id, $datas );
									if( $result === false )
									{
										$succ = false;
										if( $this->Notices ) $this->Notices->set_notice( "delete-fail", "error", $this->className."|action_handler|".$action );
									}
									else
									{
										if( $this->true_delete && method_exists( $this, 'delete_metas' ) ) $this->delete_metas( $id );
										$deleted = true;
									}
								}
							}
							else
							{
								$result = $this->delete( $id );
								if( $result === false )
								{
									$succ = false;
									$this->Notices->set_notice( "delete-fail", "error", $this->className."|action_handler|".$action );
								}
							}
						}
					}
					else 
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}

					if( $succ && $deleted && $tree_data && $Tree )
	                {
	                    //Tree handling
		                if( ! $Tree->action_handler( "delete" , $tree_data, $this->update_tree_child ) )
		                {
		                    $succ = false;
		                    if( $this->Notices ) $this->Notices->set_notices( $Tree->Notices->get_operation_notice() );
		                }

		                if( $succ && $this->update_tree_child )
		                {
		                	$succ = $this->update_childs_parent( $tree_data, $child_list );
		                }
	                }
				break;
				case "restore":
					$id = $datas['id'];
					if ( $id > 0 )
					{
						$exist = $this->select( $id );
						if( ! $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						if( $succ && $this->useFlag && $exist['flag'] < 0 )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "prevent-action", "error", $this->className."|action_handler|".$action );
						}
						if( $succ ) 
						{
							if( isset( $exist['status'] ) && $exist['status'] == 0 )
							{
								$datas['status'] = 1;

								$result = $this->update( $id, $datas );
								if( false === $result )
								{
									$succ = false;
									if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
								}
							}
							else
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
							}
						}
					}
					else 
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
				default:
					$id = $datas['id'];
					if ( $id > 0 )
					{
						$exist = $this->select( $id );
						if( ! $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						else
						{
							$result = $this->update( $id, $datas );
							if( false === $result )
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
							}
						}
					}
					else 
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
			}
		}

		if( $succ && $this->Notices && $this->Notices->count_notice( "error" ) > 0 )
            $succ = false;
		
		$outcome['succ'] = $succ; 
		$outcome['data'] = $datas;
		$outcome['after'] = $this->select( $outcome['id'] );

		return $this->after_handler( $outcome, $action , $datas, $metas, $obj );
	}
	
	public function update_childs_parent( $data, $child_list )
	{
		$succ = true;
		
		if( ! $this->tables['tree'] ) return $succ;

		if( $data && $child_list && empty( $data['ancestor'] ) )
		{
			$Tree = new WCWH_TreeAction( $this->tables['tree'] );

		    foreach( $child_list as $child )
		    {
		    	$newParent = 0;
		    	$directParent = $Tree->getTreePaths( [ 'descendant'=>$child['descendant'], 'level'=>1 ] );

		        if( $directParent && $directParent['descendant'] != $data['descendant'] )
		        {
		        	$newParent = $directParent['ancestor'];
		        }

		        $result = $this->update( $child['descendant'], [ 'parent'=>$newParent ] );
		        if ( false === $result )
				{
					$succ = false;
					if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|update_childs_parent|".$action );
				}
		    }
		}

		return $succ;
	}

	public function get_infos( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$field = "a.* ";
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		//tree concat
		$isTree = ( $args && $args['tree'] )? true : false;
		if( $isTree )
		{
			$field.= ", group_concat( distinct ta.code order by t.level desc separator ',' ) as breadcrumb_code ";
			$field.= ", group_concat( distinct ta.id order by t.level desc separator ',' ) as breadcrumb_id ";
			$field.= ", group_concat( ta.status order by t.level desc separator ',' ) as breadcrumb_status ";
			$table.= "INNER JOIN {$dbname}{$this->tables['tree']} t ON t.descendant = a.id ";
			$table.= "INNER JOIN {$dbname}{$this->tables['main']} ta force index(primary) ON ta.id = t.ancestor ";

			$group[] = "a.code";
		}

		//join parent
		$isParent = ( $args && $args['parent'] )? true : false;
		if( $isParent )
		{
			$field.= ", prt.name AS prt_name, prt.code AS prt_code, prt.serial AS prt_serial ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['main']} prt ON prt.id = a.parent ";
		}

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
		if( isset( $filters['wh_code'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.wh_code = %s ", $filters['wh_code'] );
		}
		if( isset( $filters['name'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.name = %s ", $filters['name'] );
		}
		if( isset( $filters['code'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
		}
		if( isset( $filters['serial'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.serial = %s ", $filters['serial'] );
		}
		if( isset( $filters['parent'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.parent = %d ", $filters['parent'] );
		}
		if( isset( $filters['sys_reserved'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.sys_reserved = %s ", $filters['sys_reserved'] );
		}
		if( isset( $filters['storable'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.storable = %d ", $filters['storable'] );
		}
		if( isset( $filters['single_sku'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.single_sku = %d ", $filters['single_sku'] );
		}
		if( isset( $filters['stackable'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.stackable = %d ", $filters['stackable'] );
		}
		if( isset( $filters['occupied'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.occupied = %d ", $filters['occupied'] );
		}
		if( isset( $filters['action_by'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( a.created_by = %d OR a.lupdate_by = %d ) ", $filters['action_by'], $filters['action_by'] );
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
                $cd[] = "a.name LIKE '%".$kw."%' ";
				$cd[] = "a.code LIKE '%".$kw."%' ";
				$cd[] = "a.serial LIKE '%".$kw."%' ";
				$cd[] = "a.desc LIKE '%".$kw."%' ";
				
				if( $isParent )
				{
					$cd[] = "prt.name LIKE '%".$kw."%' ";
					$cd[] = "prt.code LIKE '%".$kw."%' ";
					$cd[] = "prt.serial LIKE '%".$kw."%' ";
				}

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

			unset( $filters['status'] );
		}

		//metas
	    if( $args['meta'] )
		{
			foreach( $args['meta'] as $meta_key )
			{
				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['meta']} {$meta_key} ON {$meta_key}.storage_id = a.id AND {$meta_key}.meta_key = %s ", $meta_key );
				
				if( isset( $filters[$meta_key] ) )
				{
					$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
				}
			}
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

        $isTreeOrder = ( $args && $args['treeOrder'] )? true : false;
        if( $isTree && $isTreeOrder )
        {
        	$corder[ $args['treeOrder'][0] ] = $args['treeOrder'][1];
        }

        $isUsable = ( $args && $args['usable'] )? true : false;
		if( $isUsable )
		{
			$cond.= "AND a.sys_reserved IS NULL ";
		}

		$isUse = ( $args && $args['usage'] )? true : false;
		if( $isUse )
		{
			$cond.= $wpdb->prepare( "AND a.status > %d AND a.flag = %d ", 0, 1 );
		}

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.code' => 'ASC' ];
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

	public function count_statuses( $wh = '', $hide = false )
	{
		$wpdb = $this->db_wpdb;

		$fld = "'all' AS status, COUNT( status ) AS count ";
		$tbl = "{$this->tables['main']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );
		if( $wh ) $cond.= $wpdb->prepare( "AND wh_code = %s ", $wh );
		if( $hide ) $cond.= "AND sys_reserved IS NULL ";
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( status ) AS count ";
		$tbl = "{$this->tables['main']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );
		if( $wh ) $cond.= $wpdb->prepare( "AND wh_code = %s ", $wh );
		if( $hide ) $cond.= "AND sys_reserved IS NULL ";
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

	public function get_system_storage( $strg_id = 0, $header = array(), $detail = array() )
	{
		if( $strg_id > 0 ) return $strg_id;
		if( ! $header ) return $strg_id;
		if( ! $header['doc_type'] || ! $header['warehouse_id'] ) return $strg_id;

		$strg_type = array(
			'staging' => [ 'good_receive', 'delivery_order', 'good_issue', 'reprocess', 'good_return', 'do_revise', 'stock_adjust', 'stocktake', 'pos_transactions', 'sales', 'sale_order', 'transfer_item', 'issue_return', 'purchase_credit_note', 'purchase_debit_note', 'pricing', 'inventory' ],
			'block_staging' => [ 'block_stock', 'block_action', 'quarantine' ],
		);

		$filter = [ 'wh_code'=>$header['warehouse_id'], 'storable'=>1 ];
		foreach( $strg_type as $type => $doc_types )
		{
			if( in_array( $header['doc_type'], $doc_types ) )
			{
				$filter['sys_reserved'] = $type;
				break;
			}
		}

		if( $header['seller'] ) $filter['seller'] = $header['seller'];
		
		if( $filter['sys_reserved'] )
		{
			$result = $this->get_infos( $filter, [], true, [ 'usage'=>1 ] );
			if( $result )
			{
				$strg_id = $result['id'];
			}
		}

		return $strg_id;
	}
	
} //class

}