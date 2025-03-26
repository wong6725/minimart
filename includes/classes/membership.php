<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Membership_Class" ) ) 
{

class WCWH_Membership_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_membership";

	protected $tbl = "member";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "Membership_Class";

	public $update_tree_child = true;
	public $one_step_delete = false;
	public $true_delete = false;
	public $useFlag = false;

	protected $warehouse = array();

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

    public function setWarehouse( $wh )
    {
    	$this->warehouse = $wh;
    }

    protected function dbName()
	{
		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] && $this->warehouse['dbname'] )
		{
			return $this->warehouse['dbname'].".";
		}

		return '';
	}

	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"main" 			=> $prefix.$this->tbl,
			"meta"			=> $prefix.$this->tbl."meta",
			
			"customer"		=> $prefix."customer",
			"customermeta"	=> $prefix."customermeta",
			"acc_type"		=> $prefix."customer_acc_type",
			"customer_group"=> $prefix."customer_group",
			"origin"		=> $prefix."customer_origin",
			"company"		=> $prefix."company",

			"wp_user"		=> $wpdb->users,
			"wp_usermeta"	=> $wpdb->usermeta,

			"transact"		=> $prefix."member_transact",
			"transactmeta"	=> $prefix."member_transactmeta",
			
			"status"		=> $prefix."status",
			"attachment"	=> $prefix."attachment",
		);
	}
	
	public function update_metas( $id, $metas )
	{
		if( !$id || ! $metas ) return false;
		
		foreach( $metas as $key => $value )
		{
			if( is_array( $value ) )
			{
				delete_member_meta( $id, $key );
				foreach( $value as $val )
				{
					add_member_meta( $id, $key, $val );
				}
			}
			else
			{
				update_member_meta( $id, $key, $value );
			}
		}
		
		return true;
	}
	
	public function delete_metas( $id )
	{
		if( ! $id ) return false;
		
		$metas = get_member_meta( $id );
		if( $metas )
		{
			foreach( $metas as $key => $value )
			{
				delete_member_meta( $id, $key );
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

		$dbname = !empty( $dbname )? $dbname : $this->dbName();
		
		$field = "a.* ";
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$ord = "";

		$field.= ", c.wh_code, c.name, c.uid, c.code, c.acc_type, c.origin, c.cjob_id, c.cgroup_id, c.comp_id, c.phone_no AS customer_phone, c.email AS customer_email, c.status AS customer_status ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = a.customer_id ";

		//join company
		$isCompany = ( $args && $args['company'] )? true : false;
		if( $isCompany )
		{
			$field.= ", comp.custno AS comp_custno, comp.code AS comp_code, comp.name AS comp_name ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['company']} comp ON comp.id = c.comp_id ";
		}
		
		//join customer group
		$isCGroup = ( $args && $args['group'] )? true : false;
		if( $isCGroup )
		{
			$field.= ", cgroup.name AS cgroup_name, cgroup.code AS cgroup_code, cgroup.topup_percent AS gtopup_percent ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} cgroup ON cgroup.id = c.cgroup_id ";
		}

		//join customer job
		$isCJob = ( $args && $args['job'] )? true : false;
		if( $isCJob )
		{
			$field.= ", cjob.name AS cjob_name, cjob.code AS cjob_code ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer_job']} cjob ON cjob.id = c.cjob_id ";
		}

		//join origin group
		$isOrigin = ( $args && $args['origin'] )? true : false;
		if( $isOrigin )
		{
			$field.= ", origrp.name AS origin_name, origrp.code AS origin_code ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['origin']} origrp ON origrp.id = c.origin ";
		}

		//join account type
		$isAccType = ( $args && $args['account'] )? true : false;
		if( $isAccType )
		{
			$field.= ", acctype.name AS acc_name, acctype.code AS acc_code ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} acctype ON acctype.id = c.acc_type ";
		}

		//get wp user_id
		$isUserId = ( $args && $args['get_user'] )? true : false;
		if( $isUserId )
		{
			$field.= ", wpu.user_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['wp_usermeta']} wpu ON wpu.meta_value = c.id AND wpu.meta_key = 'customer_id' ";
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
		if( isset( $filters['serial'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.serial = %s ", $filters['serial'] );
		}

		//customer table
		if( isset( $filters['customer_id'] ) )
		{
			if( is_array( $filters['customer_id'] ) )
				$cond.= "AND a.customer_id IN ('" .implode( "','", $filters['customer_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.customer_id = %d ", $filters['customer_id'] );
		}
		if( isset( $filters['not_customer_id'] ) )
		{
			if( is_array( $filters['not_customer_id'] ) )
				$cond.= "AND a.customer_id NOT IN ('" .implode( "','", $filters['not_customer_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.customer_id != %d ", $filters['not_customer_id'] );
		}
		if( isset( $filters['wh_code'] ) )
		{
			$cond.= $wpdb->prepare( "AND c.wh_code = %s ", $filters['wh_code'] );
		}
		if( isset( $filters['code'] ) )
		{
			if( is_array( $filters['code'] ) )
				$cond.= "AND c.code IN ('" .implode( "','", $filters['code'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.code = %s ", $filters['code'] );
		}
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND c.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.acc_type = %s ", $filters['acc_type'] );
		}
		if( isset( $filters['not_acc_type'] ) )
		{
			if( is_array( $filters['not_acc_type'] ) )
				$cond.= "AND c.acc_type NOT IN ('" .implode( "','", $filters['not_acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.acc_type != %s ", $filters['not_acc_type'] );
		}
		if( isset( $filters['origin'] ) )
		{
			if( is_array( $filters['origin'] ) )
				$cond.= "AND c.origin IN ('" .implode( "','", $filters['origin'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.origin = %s ", $filters['origin'] );
		}
		if( isset( $filters['cjob_id'] ) )
		{
			if( is_array( $filters['cjob_id'] ) )
				$cond.= "AND c.cjob_id IN ('" .implode( "','", $filters['cjob_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.cjob_id = %s ", $filters['cjob_id'] );
		}
		if( isset( $filters['cgroup_id'] ) )
		{
			if( is_array( $filters['cgroup_id'] ) )
				$cond.= "AND c.cgroup_id IN ('" .implode( "','", $filters['cgroup_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.cgroup_id = %s ", $filters['cgroup_id'] );
		}
		if( isset( $filters['not_cgroup_id'] ) )
		{
			if( is_array( $filters['not_cgroup_id'] ) )
				$cond.= "AND c.cgroup_id NOT IN ('" .implode( "','", $filters['not_cgroup_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.cgroup_id != %s ", $filters['not_cgroup_id'] );
		}
		if( isset( $filters['comp_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND c.comp_id = %s ", $filters['comp_id'] );
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
                $cd[] = "a.serial LIKE '%".$kw."%' ";
                $cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.uid LIKE '%".$kw."%' ";
				$cd[] = "c.serial LIKE '%".$kw."%' ";

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
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['meta']} {$meta_key} ON {$meta_key}.member_id = a.id AND {$meta_key}.meta_key = %s ", $meta_key );
				
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
            $cond.= $wpdb->prepare( "AND a.status != %d AND c.status != %d ", -1, -1 );

            $field.= ", IF( stat.status <= 0, stat.title, '' ) AS status_name ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['status']} stat ON stat.status = c.status AND stat.type = 'default' ";
            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND c.status = %d ", $filters['status'] );
        }
        //flag
        if( isset( $filters['flag'] ) && $filters['flag'] != "" )
        {   
            $cond.= $wpdb->prepare( "AND a.flag = %d ", $filters['flag'] );
        }
        if( $this->useFlag )
        {
             $table.= "LEFT JOIN {$dbname}{$this->tables['status']} flag ON flag.status = a.flag AND flag.type = 'flag' ";
             $corder["flag.order"] = "DESC";
        }

		$isUse = ( $args && isset( $args['usage'] ) )? true : false;
		if( $isUse )
		{
			$cond.= $wpdb->prepare( "AND a.status >= %d AND c.status >= %d AND a.flag = %d ", $args['usage'], $args['usage'], 1 );
		}

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        if( empty( $order ) )
		{
			$order = [ 'c.code' => 'ASC' ];
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

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ";
		
		if( $args['query'] ) return $sql;

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
		$dbname = $this->dbName();

		$fld = "'all' AS status, COUNT( b.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['main']} a  
			LEFT JOIN {$dbname}{$this->tables['customer']} b ON b.id = a.customer_id ";
		$cond = $wpdb->prepare( "AND a.status != %d AND b.status != %d ", -1, -1 );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "b.status, COUNT( b.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['main']} a 
			LEFT JOIN {$dbname}{$this->tables['customer']} b ON b.id = a.customer_id ";
		$cond = $wpdb->prepare( "AND a.status != %d AND b.status != %d ", -1, -1 );
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

	public function get_export_data( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
			unset( $filters['seller'] );
		}
		
		$field = "a.name, a.uid, a.code, a.serial, acc.code AS acc_code, cjob.code AS job_code, cgrp.code AS grp_code 
			, ori.code AS ori_code, prt.code AS prt_code, comp.code AS comp_code, a.email, a.phone_no 
			, rc.transaction AS receipt, IF( a.status > 0, 'Ready', 'Trashed' ) AS status 
			, a.created_at AS created, a.lupdate_at AS updated, ma.meta_value AS last_day ";
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} acc ON acc.id = a.acc_type ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['origin']} ori ON ori.id = a.origin ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} cgrp ON cgrp.id = a.cgroup_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_job']} cjob ON cjob.id = a.cjob_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['company']} comp ON comp.id = a.comp_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['main']} prt ON prt.id = a.parent ";
		
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
			if( isset( $filters['acc_type'] ) )
			{
				if( is_array( $filters['acc_type'] ) )
					$cond.= "AND a.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.acc_type = %s ", $filters['acc_type'] );
			}

			if( isset( $filters['status'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.status = %s ", $filters['status'] );
			}
		}

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'a.code' => 'ASC' ];
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

	public function update_member_debit( $c_id = 0, $amt = 0, $sign = '+' )
    {
        if( ! $c_id || ! $amt ) return false;

        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();

        $tbl = $this->tables['main'];
        
        $update_fld = $wpdb->prepare( "total_debit = total_debit ".$sign." %s", $amt );
        $update_fld.= $wpdb->prepare( ", balance = balance ".$sign." %s", $amt );

        $cond = $wpdb->prepare( "AND customer_id = %d AND status > 0 AND flag > 0 ", $c_id );

        $query = "UPDATE {$tbl} SET {$update_fld} WHERE 1 {$cond} ";

        $update = $wpdb->query( $query );
        if ( false === $update ) {
            return false;
        }

        return true;
    }

    public function update_member_credit( $c_id = 0, $amt = 0, $sign = '-' )
    {
        if( ! $c_id || ! $amt ) return false;

        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();

        $tbl = $this->tables['main'];
        
        $r_sign = ( $sign == '+' )? '-' : '+';
        $update_fld = $wpdb->prepare( "total_used = total_used ".$r_sign." %s", $amt );
        $update_fld.= $wpdb->prepare( ", balance = balance ".$sign." %s", $amt );

        $cond = $wpdb->prepare( "AND customer_id = %d AND status > 0 AND flag > 0 ", $c_id );

        $query = "UPDATE {$tbl} SET {$update_fld} WHERE 1 {$cond} ";

        $update = $wpdb->query( $query );
        if ( false === $update ) {
            return false;
        }

        return true;
    }
	
} //class

}