<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ItemCategory_Class" ) ) 
{

class WCWH_ItemCategory_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_items_category";

	protected $tbl = "terms";

	protected $primary_key = "term_id";

	protected $taxonomy = "product_cat";

	protected $tables = array();

	public $Notices;
	public $className = "ItemCategory_Class";

	public $update_tree_child = true;
	public $one_step_delete = false;
	public $true_delete = false;
	public $useFlag = false;

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		global $wpdb;
		$this->set_prefix( $wpdb->prefix );

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

        global $wcwh;
		$pre = $wcwh->prefix;

        $this->tables = array(
            "main" 		=> $prefix.$this->tbl,
            "meta" 		=> $prefix."termmeta",
            "tree"		=> $pre."item_category_tree",
            "term_taxonomy"	=> $prefix."term_taxonomy",
			"term_relationships"	=> $prefix."term_relationships",
        );
	}

	public function update_metas( $id, $metas )
	{
		if( !$id || ! $metas ) return false;
		
		foreach( $metas as $key => $value )
		{
			if( is_array( $value ) )
			{
				delete_term_meta( $id, $key );
				foreach( $value as $val )
				{
					add_term_meta( $id, $key, $val );
				}
			}
			else
			{
				update_term_meta( $id, $key, $value );
			}
		}
		
		return true;
	}
	
	public function delete_metas( $id )
	{
		if( ! $id ) return false;
		
		$metas = get_term_meta( $id );
		if( $metas )
		{
			foreach( $metas as $key => $value )
			{
				delete_term_meta( $id, $key );
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
					$id = ( isset( $datas['term_id'] ) && !empty( $datas['term_id'] ) )? $datas['term_id'] : "0";
					unset( $datas['term_id'] );

					if( $id != "0" )	//update
					{
						$exist = get_term( $id, $this->taxonomy, ARRAY_A );
						if( null === $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						if( $succ ) 
						{
							$result = wp_update_term( $id, $this->taxonomy, $datas );
							if ( is_wp_error( $result ) ) 
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
							}
							else
							{
								$tresult = $this->update_term( $id, [ 'slug'=>$datas['slug'] ] );
								if( false === $tresult )
								{
									$succ = false;
								}
								if( $metas && method_exists( $this, 'update_metas' ) ) $this->update_metas( $id, $metas );
							}
						}
					}
					else
					{
						$term = $datas['name']; unset( $datas['name'] );
						$result = wp_insert_term( $term, $this->taxonomy, $datas );
						if ( is_wp_error( $result ) ) 
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "create-fail", "error", $this->className."|action_handler|".$action );
						}
						else
						{
							$id = $result['term_id'];

							$tresult = $this->update_term( $id, [ 'slug'=>$datas['slug'] ] );
							if( false === $tresult )
							{
								$succ = false;
							}
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
					$deleted = false;
					$tree_data = [];
					$child_list = [];
					
					$id = $datas['term_id'];
					if( $id > 0 )
					{
						$exist = get_term( $id, $this->taxonomy, ARRAY_A );
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

							$result = wp_delete_term( $id, $this->taxonomy );
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
			}
		}

		if( $succ && $this->Notices && $this->Notices->count_notice( "error" ) > 0 )
            $succ = false;
		
		$outcome['succ'] = $succ; 
		$outcome['data'] = $datas;

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

		        $result = $this->update_term_taxonomy( $child['descendant'], [ 'parent'=>$newParent ] );
		        if ( false === $result )
				{
					$succ = false;
					if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|update_childs_parent|".$action );
				}
		    }
		}

		return $succ;
	}

	public function update_term( $term_id, $params = array() )
	{
		if( ! $term_id || ! $params ) return false;

		$wpdb = $this->db_wpdb;

		return $wpdb->update( $this->tables['main'], $params, [ 'term_id'=>$term_id ] );
	}

	public function update_term_taxonomy( $term_id, $params = array() )
	{
		if( ! $term_id || ! $params ) return false;

		$wpdb = $this->db_wpdb;

		return $wpdb->update( $this->tables['term_taxonomy'], $params, [ 'term_id'=>$term_id, 'taxonomy'=>$this->taxonomy ] );
	}

	public function get_infos( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		$wpdb = $this->db_wpdb;
		$prefix = $wpdb->prefix;

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
		
		$field = "a.*, a.term_id AS id ";
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$field.= ", b.term_taxonomy_id, b.taxonomy, b.description, b.parent, b.count ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['term_taxonomy']} b ON b.term_id = a.term_id ";
		$cond.= $wpdb->prepare( "AND b.taxonomy = %s ", $this->taxonomy );

		//tree concat
		$isTree = ( $args && $args['tree'] )? true : false;
		if( $isTree )
		{
			$field.= ", group_concat( distinct ta.slug order by t.level desc separator ',' ) as breadcrumb_slug ";
			$field.= ", group_concat( distinct ta.term_id order by t.level desc separator ',' ) as breadcrumb_id ";
			//$field.= ", group_concat( ta.status order by t.level desc separator ',' ) as breadcrumb_status ";
			$table.= "INNER JOIN {$dbname}{$this->tables['tree']} t ON t.descendant = a.term_id ";
			$table.= "INNER JOIN {$dbname}{$this->tables['main']} ta force index(primary) ON ta.term_id = t.ancestor ";

			$group[] = "a.slug";
		}
		
		//join parent
		$isParent = ( $args && $args['parent'] )? true : false;
		if( $isParent )
		{
			$field.= ", prt.name AS prt_name, prt.slug AS prt_slug ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['main']} prt ON prt.term_id = b.parent ";
		}

		if( isset( $filters['id'] ) )
		{
			if( is_array( $filters['id'] ) )
				$cond.= "AND a.term_id IN ('" .implode( "','", $filters['id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.term_id = %d ", $filters['id'] );
		}
		if( isset( $filters['not_id'] ) )
		{
			if( is_array( $filters['not_id'] ) )
				$cond.= "AND a.term_id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.term_id != %d ", $filters['not_id'] );
		}
		if( isset( $filters['term_id'] ) )
		{
			if( is_array( $filters['term_id'] ) )
				$cond.= "AND a.term_id IN ('" .implode( "','", $filters['term_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.term_id = %d ", $filters['term_id'] );
		}
		if( isset( $filters['ancestor'] ) )
		{
			$table.= "RIGHT JOIN {$dbname}{$this->tables['tree']} c ON c.descendant = a.term_id ";

			if( is_array( $filters['ancestor'] ) )
				$cond.= "AND c.ancestor IN ('" .implode( "','", $filters['ancestor'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND c.ancestor = %d ", $filters['ancestor'] );
		}
		if( isset( $filters['slug'] ) )
		{
			if( is_array( $filters['slug'] ) )
				$cond.= "AND a.slug IN ('" .implode( "','", $filters['slug'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.slug = %s ", $filters['slug'] );
		}
		if( isset( $filters['name'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.name = %s ", $filters['name'] );
		}
		if( isset( $filters['parent'] ) )
		{
			$cond.= $wpdb->prepare( "AND b.parent = %s ", $filters['parent'] );
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
                $cd[] = "a.slug LIKE '%".$kw."%' ";
				$cd[] = "a.name LIKE '%".$kw."%' ";
				$cd[] = "b.description LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$isBase = ( $args && $args['base'] )? true : false;
		if( $isBase )
		{
			$cond.= $wpdb->prepare( "AND b.parent = %s ", 0 );
		}

		$corder = array();
		$isTreeOrder = ( $args && $args['treeOrder'] )? true : false;
        if( $isTree && $isTreeOrder )
        {
        	$corder[ $args['treeOrder'][0] ] = $args['treeOrder'][1];
        }

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        if( empty( $order ) )
		{
			$order = [ 'a.slug' => 'ASC' ];
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
	
} //class

}