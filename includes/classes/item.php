<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Item_Class" ) ) 
{

class WCWH_Item_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_items";

	protected $tbl = "items";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "Item_Class";

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
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"main" 			=> $prefix.$this->tbl,
			"tree"			=> $prefix.$this->tbl."_tree",
			"meta"			=> $prefix.$this->tbl."meta",
			"uom"			=> $prefix."uom",
			"item_group"	=> $prefix."item_group",
			"item_store_type" => $prefix."item_store_type",
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			"item_converse" => $prefix."item_converse",
			"product"		=> $wpdb->posts,
			"product_meta"	=> $wpdb->postmeta,
			"brand"			=> $prefix."brand",
			"supplier"		=> $prefix."supplier",
			"status"		=> $prefix."status",
			"storage"		=> $prefix."storage",
			"inventory"		=> $prefix."inventory",
			"reprocess"		=> $prefix."reprocess_item",

			"item_relation"	=> $prefix."item_relation",
			"reorder_type"	=> $prefix."item_reorder_type",
		);
	}
	
	public function update_metas( $id, $metas )
	{
		if( !$id || ! $metas ) return false;
		
		foreach( $metas as $key => $value )
		{
			if( is_array( $value ) )
			{
				delete_items_meta( $id, $key );
				foreach( $value as $val )
				{
					add_items_meta( $id, $key, $val );
				}
			}
			else
			{
				update_items_meta( $id, $key, $value );
			}
		}
		
		return true;
	}
	
	public function delete_metas( $id )
	{
		if( ! $id ) return false;
		
		$metas = get_items_meta( $id );
		if( $metas )
		{
			foreach( $metas as $key => $value )
			{
				delete_items_meta( $id, $key );
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
							$datas['id'] = $id;
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

	public function after_handler( $outcome, $action, $datas = array(), $metas = array(), $obj = array() )
	{
		//converse
		if( $datas['id'] )
		{
			$succ = $outcome['succ'];
			$id = $datas['id'];

			$succ = $this->item_converse( $id );
			if( ! $succ )
			{
				$outcome['succ'] = $succ;
				if( $this->Notices ) $this->Notices->set_notice( "error", "error", $this->className."|after_handler|".$action );
			}
		}
		
		return $outcome;
	}

	public function item_converse( $id = 0 )
	{
		if( ! $id ) return false;

		$succ = true;
		$item = $this->select( $id );
		if( ! $item ) return false;

		if( $item['status'] < 0 )	//delete permanent
		{
			$exists = $this->get_converse( $id );
			if( $exists )
			{
				$result = $this->remove_converse( $id );
				if( $result === false )
				{
					$succ = false;
				}
				else
				{
					$id = $exists['base_id'];
				}
			}
		}
			
		global $wpdb;

		//get all descendant required to update
		$sql = $wpdb->prepare( "SELECT a.*
			FROM wp_stmm_wcwh_items_tree a 
			WHERE a.ancestor = %d AND a.level = 1
			ORDER BY a.level ASC ", $id );
		$lvls = $wpdb->get_results( $sql , ARRAY_A );
		if( ! $lvls ) $lvls = [ [ 'ancestor'=>$id, 'descendant'=>$id, 'level'=>0 ] ];

		if( $lvls )
		{
			foreach( $lvls as $lvl )
			{
				$subSql = $wpdb->prepare( "SELECT descendant as id
					FROM {$this->tables['tree']}
					WHERE ancestor = %d 
					ORDER BY level DESC LIMIT 0,1 ", $lvl['descendant'] );
				//---------------------------------------------
				$fld = "a.ancestor AS item_id, @rownum := @rownum + 1 AS level, @root:= IF( @rownum = 0, a.ancestor, @root ) AS base_id
					, b._self_unit, b._parent_unit 
					, @converse := IF( @rownum > 0, ( b._self_unit * b._parent_unit ), 1 ) AS converse 
					, @base_unit := @base_unit * @converse AS base_unit ";

				$tbl = "{$this->tables['tree']} a ";
				$tbl.= "LEFT JOIN {$this->tables['main']} b ON b.id = a.ancestor ";
				$tbl.= ", ( SELECT @base_unit := 1 ) r ";
				$tbl.= ", (SELECT @rownum := -1) s ";
				$tbl.= ", (SELECT @root := 0) t ";

				$cond = "AND a.descendant = ( $subSql ) ";
				$ord = "ORDER BY a.level DESC ";
				$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$ord} ";

				$converses = $wpdb->get_results( $sql , ARRAY_A );

				if( ! $converses ) return false;

				foreach( $converses as $i => $converse )
				{
					$datas = [
						'item_id' => $converse['item_id'],
						'base_id' => $converse['base_id'],
						'level' => $converse['level'],
						'converse' => round( $converse['converse'], 2 ),
						'base_unit' => round( $converse['base_unit'], 2 ),
					];

					$exists = $this->get_converse( $converse['item_id'] );
					if( $exists )
					{
						$result = $this->update_converse( $converse['item_id'], $datas );
						if ( false === $result )
						{
							$succ = false;
						}
					}
					else
					{
						$id = $this->add_converse( $datas );
						if ( ! $id )
						{
							$succ = false;
						}
					}
				}
			}
		}

		return $succ;
	}

	public function add_converse( $datas )
	{
		if( ! $datas ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$succ = $wpdb->insert( $this->tables['item_converse'], $datas );
		
		return ( $succ === false )? false : true;
	}

	public function update_converse( $id = 0, $datas = array(), $args = array() )
	{
		if( ! $datas ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$find = array();
		if( $id )
		{
			$find = array( 'item_id' => $id );
			unset( $datas['item_id'] );
		}
		
		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				$find[$key] = $val;
			}
		}
		
		return $wpdb->update( $this->tables['item_converse'], $datas, $find );
	}

	public function remove_converse( $id, $args = array() )
	{
		if( ! $id && ! $args  ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$find = array();
		if( $id )
		{
			$find = array( 'item_id' => $id );
		}

		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				$find[$key] = $val;
			}
		}

		if( ! $find ) return false;
		
		return $wpdb->delete( $this->tables['item_converse'], $find );
	}

	public function get_converse( $id = 0, $args = array() )
	{
		if( ! $id && ! $args ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$fld = "* "; 
		$table = "{$this->tables['item_converse']} ";
		$cond = "";

		if( $id > 0 )
		{
			$cond.= $wpdb->prepare("AND item_id = %d ", $id );
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

		$sql = "SELECT {$fld} FROM {$table} WHERE 1 {$cond} ;";
		
		return $wpdb->get_row( $sql , ARRAY_A );
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
		
		$field = "a.*, ROUND( a._self_unit * a._parent_unit  ) AS converse ";
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		//tree concat
		$cgroup = array();
		$isTree = ( $args && $args['tree'] )? true : false;
		$needTree = ( $args && $args['needTree'] )? true : false;
		if( $isTree || $needTree )
		{
			if( $isTree )
			{
				$field.= ", group_concat( distinct ta.code order by t.level desc separator ',' ) as breadcrumb_code ";
				//$field.= ", group_concat( distinct ta.serial order by t.level desc separator ',' ) as breadcrumb_serial ";
				$field.= ", group_concat( distinct ta.id order by t.level desc separator ',' ) as breadcrumb_id ";
				$field.= ", group_concat( ta.status order by t.level desc separator ',' ) as breadcrumb_status ";
			}
			if( $needTree )
			{
				$field.= ", group_concat( distinct ta.code order by t.level desc separator ',' ) as breadcrumb_code ";
			}
			
			$table.= "INNER JOIN {$dbname}{$this->tables['tree']} t ON t.descendant = a.id ";
			$table.= "INNER JOIN {$dbname}{$this->tables['main']} ta force index(primary) ON ta.id = t.ancestor ";
			$table.= "INNER JOIN {$dbname}{$this->tables['tree']} tt ON tt.ancestor = a.id ";

			$cgroup = [ "a.code", "a.serial", "a.id " ];
		}

		//join parent
		$isParent = ( $args && $args['parent'] )? true : false;
		if( $isParent )
		{
			$field.= ", prt.name AS prt_name, prt._sku AS prt_sku, prt._uom_code AS prt_uom_code ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['main']} prt ON prt.id = a.parent ";
		}

		$isUom = ( $args && $args['uom'] )? true : false;
		if( $isUom )
		{
			$field.= ", uom.name AS uom_name, uom.code AS uom_code, uom.fraction AS uom_fraction ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['uom']} uom ON uom.code = a._uom_code ";
		}

		$isGrp = ( $args && $args['group'] )? true : false;
		if( $isGrp )
		{
			$field.= ", grp.name AS grp_name, grp.code AS grp_code ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['item_group']} grp ON grp.id = a.grp_id ";
		}

		$isStore = ( $args && $args['store'] )? true : false;
		if( $isStore )
		{
			$field.= ", store.name AS store_name, store.code AS store_code ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['item_store_type']} store ON store.id = a.store_type_id  ";
		}

		$isCat = ( $args && $args['category'] )? true : false;
		if( $isCat )
		{
			$field.= ", cat.name AS cat_name, cat.slug AS cat_slug ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = a.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
		}

		$isBrand = ( $args && $args['brand'] )? true : false;
		if( $isBrand )
		{
			$field.= ", brand.id AS brand_id, brand.name AS brand_name, brand.code AS brand_code, brand.brand_no ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['meta']} ibr ON ibr.items_id = a.id AND ibr.meta_key = '_brand' ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['brand']} brand ON brand.code = ibr.meta_value ";

			if( isset( $filters['_brand'] ) )
			{
				if( is_array( $filters['_brand'] ) )
					$cond.= "AND brand.code IN ('" .implode( "','", $filters['_brand'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND brand.code = %s ", $filters['_brand'] );
			}
		}

		$isStocks = ( $args && $args['stocks'] )? true : false;
		if( $isStocks )
		{
			$field.= ", inv.qty AS stock_qty, inv.allocated_qty AS stock_allocated ";
			$table.= $wpdb->prepare( " LEFT JOIN {$dbname}{$this->tables['inventory']} inv ON inv.prdt_id = a.id AND inv.warehouse_id = %s ", $args['stocks'] );
			$table.= "LEFT JOIN {$dbname}{$this->tables['storage']} strg ON strg.id = inv.strg_id AND strg.sys_reserved = 'staging' ";

			if( isset( $filters['has_stock'] ) )
			{
				$cond.= $wpdb->prepare( "AND inv.qty > %s ", 0 );
			}
		}

		$isStk = ( $args && $args['inventory'] )? true : false;
        if( $isStk )
        {
            $field.= ", stk.total_in_avg AS avg_cost, stk.latest_in_cost AS latest_cost ";
            $table.= " LEFT JOIN {$this->tables['inventory']} stk ON stk.prdt_id = a.id ";
            $table.= $wpdb->prepare( "AND stk.strg_id = %s ", $args['inventory'] );
        }

		$isReprocess = ( $args && $args['reprocess'] )? true : false;
		if( $isReprocess )
		{
			$field.= ", rep.items_id AS end_product, rep.required_item, rep.required_qty ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['reprocess']} rep ON rep.items_id = a.id ";

			$cond.= $wpdb->prepare( "AND rep.status > %d AND rep.flag = %d ", 0, 1 );
			if( isset( $filters['reprocess'] ) )
			{
				$cond.= "AND rep.id IS NOT NULL ";
			}
		}

		$isReorderType = ( $args && $args['reorder_type'] )? true : false;
        if( $isReorderType )
        {
            $field.= ", ir.reorder_type ";
            $table.= " LEFT JOIN {$this->tables['item_relation']} ir ON ir.items_id = a.id AND ir.status > 0 ";
            $table.= $wpdb->prepare( "AND ir.wh_id = %s AND ir.rel_type = %s ", $args['reorder_type'], 'reorder_type' );

            if( isset( $filters['reorder_type'] ) )
			{
				if( is_array( $filters['reorder_type'] ) )
					$cond.= "AND ir.reorder_type IN ('" .implode( "','", $filters['reorder_type'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND ir.reorder_type = %s ", $filters['reorder_type'] );
			}
        }

		$field.= ", meta_a.meta_value AS inconsistent_unit ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} meta_a ON meta_a.items_id = a.id AND meta_a.meta_key = 'inconsistent_unit' ";

		$field.= ", meta_b.meta_value AS unit_stock ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} meta_b ON meta_b.items_id = a.id AND meta_b.meta_key = 'kg_stock' ";

		$field.= ", vir.meta_value AS virtual ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} vir ON vir.items_id = a.id AND vir.meta_key = 'virtual' ";

		$isUnit = ( $args && $args['isUnit'] )? true : false;
		if( $isUnit )
		{
			if( $this->refs['metric'] )
			{
				foreach( $this->refs['metric'] AS $each )
				{
					$each = strtoupper($each);
					$met[] = "UPPER( a._uom_code ) = '{$each}' ";
				}

				$metric = "AND NOT ( ".implode( "OR ", $met ).") ";
			}

			$field.= ", IF( rr.id > 0 AND meta_a.meta_value > 0 {$metric}, 1, 0 ) AS required_unit ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['reprocess']} rr ON rr.items_id = a.id AND rr.status > 0 ";
		}

		$isMetric = ( $args && $args['isMetric'] )? true : false;
		if( $isMetric && $this->refs['metric'] )
		{
			if( $args['isMetric'] == 'yes' )
			{
				if( $args['isMetricExclCat'] )
				{
					$args['isMetricExclCat'] = ( is_array( $args['isMetricExclCat'] ) )? $args['isMetricExclCat'] : [ $args['isMetricExclCat'] ];
					$cond.= "AND a.category NOT IN ( '".implode( "', '", $args['isMetricExclCat'] )."' ) ";
				}
				$cond.= "AND UPPER( a._uom_code ) IN ( '".implode( "', '", $this->refs['metric'] )."' ) ";
			}
			else if( $args['isMetric'] == 'no' )
			{
				if( $args['isMetricExclCat'] )
				{
					$args['isMetricExclCat'] = ( is_array( $args['isMetricExclCat'] ) )? $args['isMetricExclCat'] : [ $args['isMetricExclCat'] ];
					$cond.= "AND ( UPPER( a._uom_code ) NOT IN ( '".implode( "', '", $this->refs['metric'] )."' ) OR a.category IN ( '".implode( "', '", $args['isMetricExclCat'] )."' ) ) ";
				}
				else
				{
					$cond.= "AND UPPER( a._uom_code ) NOT IN ( '".implode( "', '", $this->refs['metric'] )."' ) ";
				}
			}
		}

		$isVirtual = ( $args && $args['virtual'] )? true : false;
		if( $isVirtual )
		{
			$cond.= "AND vir.meta_value > 0 ";
		}

		$mspo_items = $this->setting['mspo_hide']['items'];
		if( !empty( $mspo_items ) && !isset( $args['mspo'] ) )
		{
			$cond.= "AND a.id NOT IN ('" .implode( "','", $mspo_items ). "') ";
		}

		if( isset( $filters['id'] ) )
		{
			if( $isTree || $needTree )
			{
				if( is_array( $filters['id'] ) )
					$cond.= "AND ( tt.descendant IN ('" .implode( "','", $filters['id'] ). "') OR t.ancestor IN ('" .implode( "','", $filters['id'] ). "') ) ";
				else
					$cond.= $wpdb->prepare( "AND ( tt.descendant = %s OR t.ancestor = %s ) ", $filters['id'], $filters['id'] );
			}
			else
			{
				if( is_array( $filters['id'] ) )
					$cond.= "AND a.id IN ('" .implode( "','", $filters['id'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.id = %d ", $filters['id'] );
			}
		}
		if( isset( $filters['not_id'] ) )
		{
			if( is_array( $filters['not_id'] ) )
				$cond.= "AND a.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.id != %s ", $filters['not_id'] );
		}
		if( isset( $filters['name'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.name = %s ", $filters['name'] );
		}
		if( isset( $filters['_sku'] ) )
		{
			$cond.= $wpdb->prepare( "AND a._sku = %s ", $filters['_sku'] );
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
		if( isset( $filters['serial2'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.serial2 LIKE %s ", '%'.$filters['serial'].'%' );
		}
		if( isset( $filters['product_type'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.product_type = %s ", $filters['product_type'] );
		}
		if( isset( $filters['_material'] ) )
		{
			$cond.= $wpdb->prepare( "AND a._material = %s ", $filters['_material'] );
		}
		if( isset( $filters['_uom_code'] ) )
		{
			if( is_array( $filters['_uom_code'] ) )
				$cond.= "AND a._uom_code IN ('" .implode( "','", $filters['_uom_code'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a._uom_code = %s ", $filters['_uom_code'] );
		}
		if( isset( $filters['_tax_status'] ) )
		{
			$cond.= $wpdb->prepare( "AND a._tax_status = %s ", $filters['_tax_status'] );
		}
		if( isset( $filters['_tax_class'] ) )
		{
			$cond.= $wpdb->prepare( "AND a._tax_class = %s ", $filters['_tax_class'] );
		}
		if( isset( $filters['grp_id'] ) )
		{
			if( is_array( $filters['grp_id'] ) )
				$cond.= "AND a.grp_id IN ('" .implode( "','", $filters['grp_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.grp_id = %s ", $filters['grp_id'] );
		}
		if( isset( $filters['store_type_id'] ) )
		{
			if( is_array( $filters['store_type_id'] ) )
				$cond.= "AND a.store_type_id IN ('" .implode( "','", $filters['store_type_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.store_type_id = %s ", $filters['store_type_id'] );
		}
		if( ! $isCat && isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
				$cond.= "AND a.category IN ('" .implode( "','", $filters['category'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.category = %s ", $filters['category'] );
		}
		if( isset( $filters['parent'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.parent = %d ", $filters['parent'] );
		}
		if( isset( $filters['ref_prdt '] ) )
		{
			$cond.= $wpdb->prepare( "AND a.ref_prdt  = %s ", $filters['ref_prdt '] );
		}
		if( isset( $filters['action_by'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( a.created_by = %d OR a.lupdate_by = %d ) ", $filters['action_by'], $filters['action_by'] );
		}
		//-------- 7/9/22 jeff DashboardWid -----//	
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.created_at >= %s ", $filters['from_date']);
		}
		//-------- 7/9/22 jeff DashboardWid -----//	
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
                $cd[] = "a._sku LIKE '%".$kw."%' ";
				$cd[] = "a.name LIKE '%".$kw."%' ";
				$cd[] = "a.code LIKE '%".$kw."%' ";
				$cd[] = "a.serial LIKE '%".$kw."%' ";
				$cd[] = "a.serial2 LIKE '%".$kw."%' ";

				if( $isParent )
				{
					$cd[] = "prt._sku LIKE '%".$kw."%' ";
					$cd[] = "prt.name LIKE '%".$kw."%' ";
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
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['meta']} {$meta_key} ON {$meta_key}.items_id = a.id AND {$meta_key}.meta_key = %s ", $meta_key );
				
				if( isset( $filters[$meta_key] ) )
				{
					$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
				}

				if( isset( $filters['returnable']) && $meta_key == 'returnable_item' )
				{
					$cond.= "AND {$meta_key}.meta_value > 0 ";
				}
			}

			if( isset( $filters['has_scale_key'] ) )
			{
				switch( $filters['has_scale_key'] )
				{
					case 'yes':
						$cond.= "AND ( _weight_scale_key.meta_value IS NOT NULL AND TRIM( _weight_scale_key.meta_value ) != '' ) ";
					break;
					case 'no':
						$cond.= "AND ( _weight_scale_key.meta_value IS NULL OR TRIM( _weight_scale_key.meta_value ) = '' ) ";
					break;
				}
			}

			if( isset( $filters['sellable'] ) )
			{
				switch( $filters['sellable'] )
				{
					case 'yes':
						$cond.= "AND ( _sellable.meta_value IS NULL OR TRIM( _sellable.meta_value ) = 'yes' ) ";
					break;
					case 'no':
						$cond.= "AND ( _sellable.meta_value IS NOT NULL AND TRIM( _sellable.meta_value ) = 'no' ) ";
					break;
					case 'force':
						$cond.= "AND ( _sellable.meta_value IS NOT NULL AND TRIM( _sellable.meta_value ) = 'force' ) ";
					break;
				}
			}
		}

		$corder = array();
        //status
        if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
        {
            unset( $filters['status'] );
            $cond.= $wpdb->prepare( "AND a.status != %d ", -1 );

            $field.= ", IF( stat.status <= 0, stat.title, '' ) AS status_name ";
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
        if( ( $isTree || $needTree ) && $isTreeOrder )
        {
        	$corder[ $args['treeOrder'][0] ] = $args['treeOrder'][1];
        }

		$isUse = ( $args && isset( $args['usage'] ) )? true : false;
		if( $isUse )
		{
			$cond.= $wpdb->prepare( "AND a.status >= %d AND a.flag = %d ", $args['usage'], 1 );
		}

		//group
		$group = array_merge( $cgroup, $group );
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

	public function check_serial2_unique( $serial = [], $id = 0 )
	{
		if( ! $serial ) return true;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$field = "a.id ";
		$table = "{$this->tables['main']} a ";
		$cd = [];
		$cd[] = " a._sku IN( '".implode( "',' ", $serial )."' ) ";

		foreach( $serial as $s )
		{
			$cd[].= " a.serial2 LIKE '%".$s."%' ";
		}

		$cond = "AND ( ".implode( " OR ", $cd )." ) ";
		
		if( $id > 0 )
			$cond.= $wpdb->prepare( "AND a.id != %d ", $id );

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond};";
		
		return $wpdb->get_row( $sql , ARRAY_A );
	}

	public function get_item_by_pos( $id = 0, $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$field = "a.* ";
		$table = "{$this->tables['main']} a ";
		$cond = "";

		$field.= ", meta_a.meta_value AS inconsistent_unit ";
		$table.= "LEFT JOIN {$this->tables['meta']} meta_a ON meta_a.items_id = a.id AND meta_a.meta_key = 'inconsistent_unit' ";

		$field.= ", meta_b.meta_value AS unit_stock ";
		$table.= "LEFT JOIN {$this->tables['meta']} meta_b ON meta_b.items_id = a.id AND meta_b.meta_key = 'kg_stock' ";

		$field.= ", meta_c.meta_value AS virtual ";
		$table.= "LEFT JOIN {$this->tables['meta']} meta_c ON meta_c.items_id = a.id AND meta_c.meta_key = 'virtual' ";

		$field.= ", meta_d.meta_value AS label_name ";
		$table.= "LEFT JOIN {$this->tables['meta']} meta_d ON meta_d.items_id = a.id AND meta_d.meta_key = 'label_name' ";

		$field.= ", meta_e.meta_value AS indo_name ";
		$table.= "LEFT JOIN {$this->tables['meta']} meta_e ON meta_e.items_id = a.id AND meta_e.meta_key = 'indo_name' ";

		$field.= ", meta_f.meta_value AS sellable ";
		$table.= "LEFT JOIN {$this->tables['meta']} meta_f ON meta_f.items_id = a.id AND meta_f.meta_key = '_sellable' ";

		$cond.= $wpdb->prepare( "AND a.status > %d AND a.flag = %d ", 0, 1 );
		if( $id )
		{
			if( is_array( $id ) )
				$cond.= "AND a.id IN( '".implode( "',' ", $id )."' ) ";
			else
				$cond.= $wpdb->prepare( "AND a.id = %s ", $id );
		} 

		if( $args )
		{
			foreach( $args as $key => $arg )
			{
				if( $arg )
				{
					if( is_array( $arg ) )
						$cond.= "AND {$key} IN( '".implode( "',' ", $arg )."' ) ";
					else
						$cond.= $wpdb->prepare( "AND {$key} = %s ", $arg );
				}
			}
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond};";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( ! is_array( $id ) ) //&& count( $results ) > 0
		{
			$results = $results[0];
		}
		
		return $results;
	}

	public function get_all_parent_child( $id = 0, $exclude_self = false )
	{
		if( ! $id ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$fld = "ancestor AS id ";
		$cond = $wpdb->prepare( "AND descendant = %d ", $id );
		$ord = "ORDER BY level DESC LIMIT 0,1";
		$subSql = "SELECT {$fld} FROM {$this->tables['tree']} WHERE 1 {$cond} {$ord}";

		$fld = "descendant AS id ";
		$cond = "AND ancestor = ( {$subSql} ) ";
		if( $exclude_self ) $cond.= $wpdb->prepare( "AND descendant != %d ", $id );
		$ord = "ORDER BY level DESC";
		$sql = "SELECT {$fld} FROM {$this->tables['tree']} WHERE 1 {$cond} {$ord}";
		
		return $wpdb->get_results( $sql , ARRAY_A );
	}
	
	public function uom_conversion( $id = 0, $amt = 0, &$to_id = 0, $type = 'qty', $args = array() )
	{
		if( ! $id ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( isset( $args['seller'] ) && $args['seller'] > 0 )
		{
			$dbname = get_warehouse_meta( $args['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$subSql = $wpdb->prepare( "SELECT ancestor AS id
			FROM {$dbname}{$this->tables['tree']} 
			WHERE 1 AND descendant = %d 
			ORDER BY level DESC LIMIT 0,1 ", $id );

		//---------------------------------------------

		$fld = "a.item_id AS id, a.base_id, a.level
			, b.name, b.code, b._uom_code, b._content_uom, b._self_unit, b._parent_unit, b.status
			, a.converse, a.base_unit ";

		$tbl = "{$dbname}{$this->tables['item_converse']} a ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['main']} b ON b.id = a.item_id ";

		$cond = "AND a.base_id = ( {$subSql} ) ";
		$ord = "ORDER BY a.level ASC";
		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$ord} ";

		$result = $wpdb->get_results( $sql , ARRAY_A );
		if( $result )
		{	
			$conversion = []; $j = 0;
			foreach( $result as $i => $val )
			{
				if( ! $to_id && $j == 0 ) $to_id = $val['id'];
				$conversion[ $val['id'] ] = $val;
				$j++;
			}

			switch( strtolower( $type ) )
			{
				case 'amt':
					if( $amt && $to_id )	//convert to specific child
					{
						$base_amt = $amt / $conversion[ $id ]['base_unit'];
						return $converted = $base_amt * $conversion[ $to_id ][ 'base_unit' ];
					}
					else if( $amt && ! $to_id )
					{
						return $amt / $conversion[ $id ]['base_unit'];
					}
				break;
				case 'qty':
				default:
					if( $amt && $to_id )	//convert to specific child
					{
						$base_qty = $conversion[ $id ]['base_unit'] * $amt;
						return $converted = $base_qty / $conversion[ $to_id ][ 'base_unit' ];
					}
					else if( $amt && ! $to_id )
					{
						return $conversion[ $id ]['base_unit'] * $amt;
					}
				break;
			}

			return $conversion;	
		}
		
		return false;
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

	public function get_product( $item_id = 0, $single = true, $args = array() )
	{
		global $wpdb;

		$fld = "a.*, b.meta_value AS item_id ";
		$tbl = "{$wpdb->posts} a ";
		$tbl.= "LEFT JOIN {$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'item_id' ";
		$cond = $wpdb->prepare( " AND a.post_type = %s ", 'product' );

		if( $item_id )
		{
			if( is_array( $item_id ) )
			{
				$cond.= "AND b.meta_value IN ('" .implode( "','", $item_id ). "') ";
			}
			else
			{
				$cond.= $wpdb->prepare( "AND b.meta_value = %d ", $item_id );
			}
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

	public function woocommerce_product_handler( $action, $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		if( ! $datas['id'] )
		{
			$succ = false;
			$this->Notices->set_notice( "invalid-record", "error", $this->className."|woocommerce_product_handler|".$action );
		}

		if( $succ )
		{
			$action = strtolower( $action );
			switch ( $action )
			{
				case "save":
				case "update":
					$exist = $this->get_product( $datas['id'], true );
					$product_id = 0;
					$isSave = false;

					if( ! $exist )	//save
					{
						$prdt = [
							'post_title' => $datas['name'],
							'post_type' => 'product',
							'post_status' => 'publish',
							'post_content' => $datas['desc'],
						];
						$product_id = wp_insert_post( $prdt );
						if ( ! $product_id )
						{
							$succ = false;
							$this->Notices->set_notice( "create-fail", "error", $this->className."|woocommerce_product_handler|".$action );
						}
						else
						{
							$isSave = true;
							update_post_meta( $product_id, 'item_id', $datas['id'] );
						}
					}
					else 	//update
					{
						$product_id = $exist['ID'];

						$prdt = [
							'ID' => $product_id,
							'post_title' => $datas['name'],
							'post_content' => $datas['desc'],
						];
						$result = wp_update_post( $prdt );
						if ( false === $result )
						{
							$succ = false;
							$this->Notices->set_notice( "update-fail", "error", $this->className."|woocommerce_product_handler|".$action );
						}
					}

					if( $succ )
					{
						$outcome['id'] = $product_id;
						$id = $product_id;

						if( $isSave )
						{
							// set product is simple/variable/grouped
							wp_set_object_terms( $id, 'simple', 'product_type' );
							update_post_meta( $id, '_visibility', 'visible' );
							update_post_meta( $id, '_stock_status', 'instock');
							update_post_meta( $id, 'total_sales', '0' );
							update_post_meta( $id, '_downloadable', 'no' );
							update_post_meta( $id, '_virtual', 'yes' );
							update_post_meta( $id, '_regular_price', '' );
							update_post_meta( $id, '_sale_price', '' );
							update_post_meta( $id, '_purchase_note', '' );
							update_post_meta( $id, '_featured', 'no' );
							update_post_meta( $id, '_product_attributes', array() );
							update_post_meta( $id, '_sale_price_dates_from', '' );
							update_post_meta( $id, '_sale_price_dates_to', '' );
							update_post_meta( $id, '_price', '' );
							update_post_meta( $id, '_sold_individually', '' );
						}
						update_post_meta( $id, '_thumbnail_id', $datas['_thumbnail_id']	);
						update_post_meta( $id, '_sku', $datas['_sku'] );
						//update_post_meta( $id, 'code', $datas['code'] );
						//update_post_meta( $id, 'serial', $datas['serial'] );
						update_post_meta( $id, 'product_type', $datas['product_type'] );
						//update_post_meta( $id, '_stock_out_type', $datas['_stock_out_type'] );
						//update_post_meta( $id, '_uom_code', $datas['_uom_code'] );
						//update_post_meta( $id, '_content_uom', $datas['_content_uom'] );

						update_post_meta( $id, '_tax_status', $datas['_tax_status'] );
						update_post_meta( $id, '_tax_class', $datas['_tax_class'] );
						update_post_meta( $id, '_manage_stock', $datas['_manage_stock'] );
						update_post_meta( $id, '_backorders', $datas['_backorders'] );

						//update_post_meta( $id, '_weight', $datas['_weight'] );
						//update_post_meta( $id, '_length', $datas['_length'] );
						//update_post_meta( $id, '_width', $datas['_width'] );
						//update_post_meta( $id, '_height', $datas['_height'] );
						//update_post_meta( $id, '_thickness', $datas['_thickness'] );
					}
				break;
				case 'delete':
				case 'restore':
					$exist = $this->get_product( $datas['id'], true );
					$product_id = 0;
					
					if( ! $exist )
					{
						$succ = false;
						$this->Notices->set_notice( "invalid-record", "error", $this->className."|woocommerce_product_handler|".$action );
					}
					else 	//update
					{
						$product_id = $exist['ID'];

						if( $action == 'delete' )
							$post_status = 'trash';
						else if( $action == 'restore' )
							$post_status = 'publish';

						$prdt = [
							'ID' => $product_id,
							'post_status' => $post_status,
						];
						$result = wp_update_post( $prdt );
						if ( false === $result )
						{
							$succ = false;
							$this->Notices->set_notice( "update-fail", "error", $this->className."|woocommerce_product_handler|".$action );
						}
					}
				break;
				case 'delete-permanant':
					$exist = $this->get_product( $datas['id'], true );
					$product_id = 0;

					if( ! $exist )	//save
					{
						$succ = false;
						$this->Notices->set_notice( "invalid-input", "error", $this->className."|woocommerce_product_handler|".$action );
					}
					else
					{
						$product_id = $exist['ID'];
						$result = wp_delete_post( $product_id, true);
						if ( false === $result )
						{
							$succ = false;
							$this->Notices->set_notice( "update-fail", "error", $this->className."|woocommerce_product_handler|".$action );
						}
						else
						{
							global $wpdb;
							$result = $wpdb->delete( $this->tables['product_meta'], array( 'post_id' => $product_id ) );
							if( false === $result )
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "delete-fail", "error", $this->className."|woocommerce_product_handler|".$action );
							}
						}
					}
				break;
			}
		}

		if( $succ && $this->Notices->count_notice( "error" ) > 0 )
            $succ = false;
		
		$outcome['succ'] = $succ; 
		$outcome['data'] = $datas;

		return $outcome;
	}

	public function get_export_data( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$field = "a.name, IF( LENGTH(b.meta_value)>0, b.meta_value, a.name ) AS print_name, b.meta_value AS label_name, c.meta_value AS chinese_name, d.meta_value AS indo_name, a._sku, a.code, a.serial, a.serial2 ";
		$field.= ", prt.serial AS parent, a._uom_code, a._content_uom, a._self_unit, a._parent_unit, s.meta_value AS inconsistent_unit ";
		$field.= ", t.meta_value AS kg_stock, grp.code AS grp_id, cat.slug AS category ";//st.code AS store_type_id, 
		$field.= ", a._stock_out_type, e.meta_value AS _weight_scale_key, IFNULL( n.meta_value, 'yes' ) AS _sellable, f.meta_value AS _length ";
		$field.= ", g.meta_value AS _height, h.meta_value AS _width, i.meta_value AS _thickness, j.meta_value AS _weight, k.meta_value AS _volume ";
		$field.= ", l.meta_value AS _capacity, br.name AS _brand, a._material ";
		$field.= ", o.meta_value AS _model, p.meta_value AS _halal, q.meta_value AS _origin_country, r.meta_value AS _website, a.desc ";
		$field.= ", u.meta_value AS virtual, a.status, a.flag, a._thumbnail_id ";
		
		$table = "{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$this->tables['meta']} b ON b.items_id = a.id AND b.meta_key = 'label_name' ";
		$table.= "LEFT JOIN {$this->tables['meta']} c ON c.items_id = a.id AND c.meta_key = 'chinese_name' ";
		$table.= "LEFT JOIN {$this->tables['meta']} d ON d.items_id = a.id AND d.meta_key = 'indo_name' ";
		$table.= "LEFT JOIN {$this->tables['meta']} e ON e.items_id = a.id AND e.meta_key = '_weight_scale_key' ";
		$table.= "LEFT JOIN {$this->tables['meta']} f ON f.items_id = a.id AND f.meta_key = '_length' ";
		$table.= "LEFT JOIN {$this->tables['meta']} g ON g.items_id = a.id AND g.meta_key = '_height' ";
		$table.= "LEFT JOIN {$this->tables['meta']} h ON h.items_id = a.id AND h.meta_key = '_width' ";
		$table.= "LEFT JOIN {$this->tables['meta']} i ON i.items_id = a.id AND i.meta_key = '_thickness' ";
		$table.= "LEFT JOIN {$this->tables['meta']} j ON j.items_id = a.id AND j.meta_key = '_weight' ";
		$table.= "LEFT JOIN {$this->tables['meta']} k ON k.items_id = a.id AND k.meta_key = '_volume' ";
		$table.= "LEFT JOIN {$this->tables['meta']} l ON l.items_id = a.id AND l.meta_key = '_capacity' ";
		$table.= "LEFT JOIN {$this->tables['meta']} m ON m.items_id = a.id AND m.meta_key = '_brand' ";

		$table.= "LEFT JOIN {$this->tables['meta']} o ON o.items_id = a.id AND o.meta_key = '_model' ";
		$table.= "LEFT JOIN {$this->tables['meta']} p ON p.items_id = a.id AND p.meta_key = '_halal' ";
		$table.= "LEFT JOIN {$this->tables['meta']} q ON q.items_id = a.id AND q.meta_key = '_origin_country' ";
		$table.= "LEFT JOIN {$this->tables['meta']} r ON r.items_id = a.id AND r.meta_key = '_website' ";
		$table.= "LEFT JOIN {$this->tables['meta']} s ON s.items_id = a.id AND s.meta_key = 'inconsistent_unit' ";
		$table.= "LEFT JOIN {$this->tables['meta']} n ON n.items_id = a.id AND n.meta_key = '_sellable' ";
		$table.= "LEFT JOIN {$this->tables['meta']} t ON t.items_id = a.id AND t.meta_key = 'kg_stock' ";
		$table.= "LEFT JOIN {$this->tables['meta']} u ON u.items_id = a.id AND u.meta_key = 'virtual' ";

		$table.= "LEFT JOIN {$this->tables['main']} prt ON prt.id = a.parent ";
		$table.= "LEFT JOIN {$this->tables['item_group']} grp ON grp.id = a.grp_id ";
		//$table.= "LEFT JOIN {$this->tables['item_store_type']} st ON st.id = a.store_type_id ";
		$table.= "LEFT JOIN {$this->tables['category']} cat ON cat.term_id = a.category ";
		//$table.= "LEFT JOIN {$this->tables['main']} ref ON ref.id = a.ref_prdt ";
		$table.= "LEFT JOIN {$this->tables['brand']} br ON br.code = m.meta_value ";
		//$table.= "LEFT JOIN {$this->tables['product']} p_url ON p_url.id = a._thumbnail_id ";

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
			if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
	        {
	            $cond.= $wpdb->prepare( "AND a.status >= %d ", 0 );
	        }
	        else if( isset( $filters['status'] ) && strtolower( $filters['status'] ) != 'all' )
	        {   
	            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
	        }

			foreach( $filters as $key => $val )
			{
				if( in_array( $key, [ 'status', 'from_date', 'to_date' ] ) ) continue;
				
				if( is_array( $val ) )
					$cond .=  " AND a.{$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND a.{$key} = %s ", $val );
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

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $this->export_data_alter( $results );
	}
		public function export_data_alter( $datas )
		{
			if( $datas )
			{
				foreach( $datas as $i => $data )
				{
					if( $data['_thumbnail_id'] )
					{
						$item = $this->get_infos( [ 'code'=>$data['code'],'serial'=>$data['serial'] ], [], true, [ 'meta'=>[ '_thumbnail_url' ] ] );

						if( $item && !empty( $item['_thumbnail_url'] ) )
						{
							$url = $item['_thumbnail_url'];
						}
						else
						{
							$url = wp_get_attachment_image_url( $data['_thumbnail_id'], 'medium' );
						}
						
						$datas[$i]['_thumbnail_id'] = $url;
					}
				}
			}

			return $datas;
		}
	
} //class

}