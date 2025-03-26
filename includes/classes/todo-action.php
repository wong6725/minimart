<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TodoAction_Class" ) ) 
{

class WCWH_TodoAction_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_todo_action";

	protected $tbl = "todo_action";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "TodoAction_Class";

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
			"arrangement" 	=> $prefix."todo_arrangement",
			"section"		=> $prefix."section",
		);
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
		
		$field = "a.* ";
		$table = "{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$field.= ", b.section, b.action_type ";
		$table.="LEFT JOIN {$this->tables['arrangement']} b ON b.id = a.arr_id ";

		$field.= ", c.desc AS section_name ";
		$table.="LEFT JOIN {$this->tables['section']} c ON c.section_id = b.section ";
		
		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( $value == "" || $value === null ) unset( $filters[$key] );
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
		if( isset( $filters['arr_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.arr_id = %s ", $filters['arr_id'] );
		}
		if( isset( $filters['next_action'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.next_action = %s ", $filters['next_action'] );
		}
		if( isset( $filters['responsible'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.responsible = %s ", $filters['responsible'] );
		}
		if( isset( $filters['trigger_action'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.trigger_action = %s ", $filters['trigger_action'] );
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
                $cd[] = "a.next_action LIKE '%".$kw."%' ";
				$cd[] = "a.responsible LIKE '%".$kw."%' ";
				$cd[] = "a.trigger_action LIKE '%".$kw."%' ";
				$cd[] = "b.section LIKE '%".$kw."%' ";
				$cd[] = "c.desc LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.id' => 'ASC' ];
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