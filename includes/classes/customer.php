<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Customer_Class" ) ) 
{

class WCWH_Customer_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_customer";

	protected $tbl = "customer";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "Customer_Class";

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
			"tree"			=> $prefix.$this->tbl."_tree",
			"meta"			=> $prefix.$this->tbl."meta",
			"customer_group"	=> $prefix.$this->tbl."_group",
			"customer_job"	=> $prefix.$this->tbl."_job",
			"addresses"		=> $prefix."addresses",
			"company"		=> $prefix."company",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",
			"status"		=> $prefix."status",
			"wp_user"		=> $wpdb->users,
			"wp_usermeta"	=> $wpdb->usermeta,

			"member"		=> $prefix."member",
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",
			"items"			=> $prefix."items",

			"count"			=> $prefix."customer_count",
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
				delete_customer_meta( $id, $key );
				foreach( $value as $val )
				{
					add_customer_meta( $id, $key, $val );
				}
			}
			else
			{
				update_customer_meta( $id, $key, $value );
			}
		}
		
		return true;
	}
	
	public function delete_metas( $id )
	{
		if( ! $id ) return false;
		
		$metas = get_customer_meta( $id );
		if( $metas )
		{
			foreach( $metas as $key => $value )
			{
				delete_customer_meta( $id, $key );
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
			$field.= ", prt.name AS prt_name, prt.uid AS prt_uid, prt.serial AS prt_serial ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['main']} prt ON prt.id = a.parent ";
		}

		//join company
		$isCompany = ( $args && $args['company'] )? true : false;
		if( $isCompany )
		{
			$field.= ", comp.custno AS comp_custno, comp.code AS comp_code, comp.name AS comp_name ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['company']} comp ON comp.id = a.comp_id ";
		}
		
		//join customer group
		$isCGroup = ( $args && $args['group'] )? true : false;
		if( $isCGroup )
		{
			$field.= ", cgroup.name AS cgroup_name, cgroup.code AS cgroup_code, cgroup.topup_percent AS gtopup_percent ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} cgroup ON cgroup.id = a.cgroup_id ";
		}

		//join customer job
		$isCJob = ( $args && $args['job'] )? true : false;
		if( $isCJob )
		{
			$field.= ", cjob.name AS cjob_name, cjob.code AS cjob_code ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['customer_job']} cjob ON cjob.id = a.cjob_id ";
		}

		//join origin group
		$isOrigin = ( $args && $args['origin'] )? true : false;
		if( $isOrigin )
		{
			$field.= ", origrp.name AS origin_name, origrp.code AS origin_code ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['origin']} origrp ON origrp.id = a.origin ";
		}

		//join account type
		$isAccType = ( $args && $args['account'] )? true : false;
		if( $isAccType )
		{
			$field.= ", acctype.name AS acc_name, acctype.code AS acc_code, acctype.status AS acc_stat ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} acctype ON acctype.id = a.acc_type ";
		}

		//join member
		$joinMember = ( $args && $args['member'] )? true : false;
		$isMember = ( $args && isset( $args['is_member'] ) )? true : false;
		if( $joinMember || $isMember )
		{
			if( $joinMember )	
			{
				$field.= ", mb.id AS member_id, mb.serial AS member_serial, mb.pin AS member_pin, mb.total_debit, mb.total_used, mb.balance, mb.phone_no AS member_phone, mb.email AS member_email, mb.created_at AS member_create_date ";
			}
			
			$table.= "LEFT JOIN {$dbname}{$this->tables['member']} mb ON mb.customer_id = a.id AND mb.status > 0 ";

			if( $isMember )
			{
				if( $args['is_member'] > 0 )
					$cond.= "AND mb.id > 0 ";
				else
					$cond.= "AND mb.id IS NULL ";
			}
		}

		//join count
		$isCount = ( $args && $args['count'] )? true : false;
		if( $isCount )
		{
			$field.= ", rc.transaction AS receipt_count, rc.credit AS credited ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['count']} rc ON rc.cust_id = a.id AND rc.serial = a.serial AND rc.status > 0 ";
		}

		//get wp user_id
		$isUserId = ( $args && $args['get_user'] )? true : false;
		if( $isUserId )
		{
			$field.= ", wpu.user_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['wp_usermeta']} wpu ON wpu.meta_value = a.id AND wpu.meta_key = 'customer_id' ";
		}

		$isPhoto = ( $args && $args['photo'] )? true: false;
		if( $isPhoto )
		{
			$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} img ON img.customer_id = a.id AND img.meta_key = 'attachment' ";
			if( isset( $filters['photo'] ) )
			{
				if( $filters['photo'] == 'yes' )
					$cond.= "AND img.meta_value IS NOT NULL ";
				else if( $filters['photo'] == 'no' )
					$cond.= "AND ( img.meta_value IS NULL OR img.meta_value = '' ) ";
			}
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
		if( isset( $filters['uid'] ) )
		{	
			if( $args && $args['uid_strip'] > 0 )
			{
				$cond.= $wpdb->prepare( "AND SUBSTRING( a.uid, -6 ) = %s ", $filters['uid'] );
			}
			else
			{
				$cond.= $wpdb->prepare( "AND a.uid = %s ", $filters['uid'] );
			}
		}
		if( isset( $filters['code'] ) )
		{
			if( is_array( $filters['code'] ) )
				$cond.= "AND a.code IN ('" .implode( "','", $filters['code'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
		}
		if( isset( $filters['serial'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.serial = %s ", $filters['serial'] );
		}
		if( isset( $filters['acc_type'] ) )
		{
			if( is_array( $filters['acc_type'] ) )
				$cond.= "AND a.acc_type IN ('" .implode( "','", $filters['acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.acc_type = %s ", $filters['acc_type'] );
		}
		if( isset( $filters['not_acc_type'] ) )
		{
			if( is_array( $filters['not_acc_type'] ) )
				$cond.= "AND a.acc_type NOT IN ('" .implode( "','", $filters['not_acc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.acc_type != %s ", $filters['not_acc_type'] );
		}
		if( isset( $filters['origin'] ) )
		{
			if( is_array( $filters['origin'] ) )
				$cond.= "AND a.origin IN ('" .implode( "','", $filters['origin'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.origin = %s ", $filters['origin'] );
		}
		if( isset( $filters['cjob_id'] ) )
		{
			if( is_array( $filters['cjob_id'] ) )
				$cond.= "AND a.cjob_id IN ('" .implode( "','", $filters['cjob_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.cjob_id = %s ", $filters['cjob_id'] );
		}
		if( isset( $filters['cgroup_id'] ) )
		{
			if( is_array( $filters['cgroup_id'] ) )
				$cond.= "AND a.cgroup_id IN ('" .implode( "','", $filters['cgroup_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.cgroup_id = %s ", $filters['cgroup_id'] );
		}
		if( isset( $filters['not_cgroup_id'] ) )
		{
			if( is_array( $filters['not_cgroup_id'] ) )
				$cond.= "AND a.cgroup_id NOT IN ('" .implode( "','", $filters['not_cgroup_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.cgroup_id != %s ", $filters['not_cgroup_id'] );
		}
		if( isset( $filters['comp_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.comp_id = %s ", $filters['comp_id'] );
		}
		if( isset( $filters['email'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.email = %s ", $filters['email'] );
		}
		if( isset( $filters['phone_no'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.phone_no = %s ", $filters['phone_no'] );
		}
		if( isset( $filters['parent'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.parent = %d ", $filters['parent'] );
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
				$cd[] = "a.uid LIKE '%".$kw."%' ";
				$cd[] = "a.serial LIKE '%".$kw."%' ";

				if( $isParent )
				{
					$cd[] = "prt.name LIKE '%".$kw."%' ";
					$cd[] = "prt.uid LIKE '%".$kw."%' ";
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
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['meta']} {$meta_key} ON {$meta_key}.customer_id = a.id AND {$meta_key}.meta_key = %s ", $meta_key );
				
				if( isset( $filters[$meta_key] ) )
				{
					$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
				}
			}
		}

		$corder = array();
        //status
        $field.= ", IF( stat.status <= 0, stat.title, '' ) AS status_name ";
        $table.= "LEFT JOIN {$dbname}{$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
        if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
        {
            unset( $filters['status'] );
            $cond.= $wpdb->prepare( "AND a.status != %d ", -1 );

            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
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

        $isTreeOrder = ( $args && $args['treeOrder'] )? true : false;
        if( $isTree && $isTreeOrder )
        {
        	$corder[ $args['treeOrder'][0] ] = $args['treeOrder'][1];
        }

		$isUse = ( $args && isset( $args['usage'] ) )? true : false;
		if( $isUse )
		{
			$cond.= $wpdb->prepare( "AND a.status >= %d AND a.flag = %d ", $args['usage'], 1 );
		}

		$isUid = ( $args && $args['uid'] )? true : false;
		if( $isUid )
		{
			$cond.= $wpdb->prepare( "AND CHAR_LENGTH( a.uid ) > %s ", $args['uid'] );
		}

		if( $args && $args['incl'] )
		{
			if( is_array( $args['incl'] ) )
				$cond.= "OR a.id IN ('" .implode( "','", $args['incl'] ). "') ";
			else
				$cond.= $wpdb->prepare( "OR a.id = %d ", $args['incl'] );
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

		$fld = "'all' AS status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$this->tables['main']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$this->tables['main']} ";
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

	public function get_user( $customer_id = 0, $single = true, $args = array() )
	{
		global $wpdb;

		$fld = "a.*, b.meta_value AS customer_id ";
		$tbl = "{$this->tables['wp_user']} a ";
		$tbl.= "LEFT JOIN {$this->tables['wp_usermeta']} b ON b.user_id = a.ID AND b.meta_key = 'customer_id' ";
		$cond = "";

		if( $customer_id )
		{
			$cond.= $wpdb->prepare( "AND b.meta_value = %s ", $customer_id );
		}

		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				if( is_array( $val ) )
					$cond .=  " AND {$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND {$key} = %s ", $val );
			}
		}

		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}

	public function woocommerce_customer_handler( $action, $datas = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		if( ! $datas['id'] )
		{
			$succ = false;
			$this->Notices->set_notice( "invalid-record", "error", $this->className."|product_handler|".$action );
		}

		$customer_data = $this->get_infos( [ 'id'=>$datas['id'] ], [], true, [] );

		if( $succ )
		{
			$exist = array();
			
			$action = strtolower( $action );
			switch ( $action )
			{
				case "save":
				case "update":
					$exist = $this->get_user( $datas['id'], true );

					if( ! $exist )	//save
					{
						$password = wp_generate_password( 12, false );
						$user_id = wp_create_user( $datas['code'], $password );

						$user = new WP_User( $user_id );
	 					$user->set_role( 'customer' );

	 					update_user_meta( $user_id, 'customer_id', $datas['id'] );
					}
					else
					{
						$user_id = $exist['ID'];
					}

					$dat = array(
						'ID' => $user_id,
						'user_nicename' => $datas['name'],
						'display_name' => $datas['name'],
					);
					$user_data = wp_update_user( $dat );
					if( is_wp_error( $user_data ) ) 
					{
						$succ = false;
						$this->Notices->set_notice( "update-fail", "error", $this->className."|product_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $user_id;

						update_user_meta( $user_id, 'code', $datas['code'] );
						update_user_meta( $user_id, 'member_id', $customer_data['serial'] );
					}
				break;
				case 'delete-permanant':
					$exist = $this->get_user( $datas['id'], true );
					$user_id = 0;

					if( ! $exist )	//save
					{
						$succ = false;
						$this->Notices->set_notice( "invalid-input", "error", $this->className."|woocommerce_product_handler|".$action );
					}
					else
					{
						$user_id = $exist['ID'];
						$result = wp_delete_user( $user_id );
						if ( ! $result )
						{
							$succ = false;
							$this->Notices->set_notice( "update-fail", "error", $this->className."|woocommerce_product_handler|".$action );
						}
						else
						{
							global $wpdb;
							$result = $wpdb->delete( $this->tables['wp_usermeta'], array( 'user_id' => $user_id ) );
							if( false === $result )
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "delete-fail", "error", $this->className."|woocommerce_product_handler|".$action );
							}
						}
					}
				break;
				case "new-serial":
					$exist = $this->get_user( $datas['id'], true );
					if( $exist )
					{
						update_user_meta( $exist['ID'], 'member_id', $customer_data['serial'] );
					}
				break;
			}
		}

		if( $succ && $this->Notices->count_notice( "error" ) > 0 )
            $succ = false;
		
		$outcome['succ'] = $succ; 
		$outcome['data'] = $datas;

		return $this->after_handler( $outcome, $action , $datas, $metas, $obj );
	}

	public function add_customer_count( $datas = array() )
	{
		if( ! $datas ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$succ = $wpdb->insert( $this->tables['count'], $datas );
		
		return ( $succ )? $wpdb->insert_id : $succ;
	}

	public function update_customer_count( $user_id = 0, $serial = '', $total = 0, $credit = 0, $plus = "+" )
	{
		if( ! $user_id || ! $serial ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$succ = true;

		$exist = $this->get_customer_count( [ 'cust_id' => $user_id, 'serial' => $serial, 'status' => 1 ], true );
		if( ! $exist )
		{
			$dat = [
				'cust_id' => $user_id,
				'serial' => $serial,
				'transaction' => 0,
				'purchase' => $total,
				'credit' => $credit	
			];
			$id = $this->add_customer_count( $dat );
			if ( ! $id )
			{
				$succ = false;
			}
			else
			{
				$exist = $this->get_customer_count( [ 'id' => $id ], true );
			}
		}

		if( $exist )
		{
			$update_fld = $wpdb->prepare( " transaction = transaction ".$plus." %s, purchase = purchase ".$plus." %s , credit = credit ".$plus." %s ", 1, $total, $credit );

			$update_items_sql = $wpdb->prepare( "UPDATE ".$this->tables['count']." SET ".$update_fld." WHERE id = %s AND cust_id = %s AND serial = %s ", $exist['id'], $user_id, $serial );
			$update = $wpdb->query( $update_items_sql );
			if ( false === $update ) {
				$succ = false;
			}
		}
		else
			$succ = false;

		return $succ;
	}

	public function get_customer_count( $filters = array(), $single = false )
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
		$table = "{$dbname}{$this->tables['count']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		if( isset( $filters['id'] ) )
        {   
        	if( is_array( $filters['id'] ) )
				$cond.= "AND a.id IN ('" .implode( "','", $filters['id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.id = %d ", $filters['id'] );
        }
		if( isset( $filters['cust_id'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.cust_id = %s ", $filters['cust_id'] );
        }
        if( isset( $filters['serial'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.serial = %s ", $filters['serial'] );
        }
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
        }

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}

	public function get_customer_all( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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

		$whs = [];
		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'meta'=>['dbname'] ] );
		if( $wh )
		{
			$whs[] = $wh;
			$cwh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'parent'=>$wh['id'] ], [], false, [ 'meta'=>['dbname'] ] );
			if( $cwh )
			{
				foreach( $cwh as $i => $w )
				{
					if( !empty( $w['dbname'] ) )
					{
						$whs[] = $w;
					}
				}
			}
		}

		if( sizeof( $whs ) > 1 )
		{
			$union = [];
			foreach( $whs as $i => $wh )
			{
				$filters['seller'] = $wh['id'];
				$args['query'] = 1;
				$union[] = $this->get_infos( $filters, $order, $single, $args, $group, $limit );
			}

			if( $union )
			{
				$union_sql = " ( ".implode( " ) UNION ALL ( ", $union )." ) ";

				//group
				if( !empty( $group ) )
				{
			        $grp.= "GROUP BY ".implode( ", ", $group )." ";
				}

				//order
		        if( empty( $order ) )
				{
					$order = [ 'a.code' => 'ASC' ];
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

				$sql = "SELECT a.* FROM ( $union_sql ) a WHERE 1 {$cond} {$grp} {$ord} {$l} ;";

				$results = $wpdb->get_results( $sql , ARRAY_A );

				if( $single && count( $results ) > 0 )
				{
					$results = $results[0];
				}
				
				return $results;
			}
		}
		else
		{
			return $this->get_infos( $filters, $order, $single, $args, $group, $limit );
		}
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
			, rc.transaction AS receipt
			, mb.meta_value AS ibs, TRIM(LEADING '0' FROM SUBSTRING(a.uid, -6, 6) ) AS ID
			, md.meta_value AS passport, me.meta_value AS phase
			, IF( a.status > 0, 'Ready', 'Trashed' ) AS status 
			, a.created_at AS created, a.lupdate_at AS updated, ma.meta_value AS last_day ";
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} ma ON ma.customer_id = a.id AND ma.meta_key = 'last_day' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} mb ON mb.customer_id = a.id AND mb.meta_key = 'ibs' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} md ON md.customer_id = a.id AND md.meta_key = 'passport' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} me ON me.customer_id = a.id AND me.meta_key = 'phase' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['acc_type']} acc ON acc.id = a.acc_type ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['origin']} ori ON ori.id = a.origin ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_group']} cgrp ON cgrp.id = a.cgroup_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_job']} cjob ON cjob.id = a.cjob_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['company']} comp ON comp.id = a.comp_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['main']} prt ON prt.id = a.parent ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['count']} rc ON rc.cust_id = a.id AND rc.serial = a.serial AND rc.status > 0 ";
		
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
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.lupdate_at >= %s ", $filters['from_date'] );
				unset( $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.lupdate_at <= %s ", $filters['to_date'] );
				unset( $filters['to_date'] );
			}

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

	public function get_print_data( $filters = array(), $single = false )
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
		
		$field = "a.name, a.serial,a.uid, cjob.name AS job_name, ma.meta_value AS last_day, a.code,
					mb.meta_value AS ibs, md.meta_value AS passport, 
					me.meta_value AS phase, att.id AS attach_id";
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} ma ON ma.customer_id = a.id AND ma.meta_key = 'last_day' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} mb ON mb.customer_id = a.id AND mb.meta_key = 'ibs' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} mc ON mc.customer_id = a.id AND mc.meta_key = 'agent' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} md ON md.customer_id = a.id AND md.meta_key = 'passport' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} me ON me.customer_id = a.id AND me.meta_key = 'phase' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['customer_job']} cjob ON cjob.id = a.cjob_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['attachment']} att ON att.ref_id = a.id AND att.status > 0";
		
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
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.lupdate_at >= %s ", $filters['from_date'] );
				unset( $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.lupdate_at <= %s ", $filters['to_date'] );
				unset( $filters['to_date'] );
			}

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
			
			if( isset( $filters['id'] ) )
			{
				if( is_array( $filters['id'] ) )
					$cond.= "AND a.id IN ('" .implode( "','", $filters['id'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.id = %s ", $filters['id'] );
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
	
} //class

}