<?php
if ( !defined( "ABSPATH" ) ) exit;

if ( !class_exists( "WCWH_StageDetail" ) )
{

class WCWH_StageDetail extends WCWH_CRUD_Controller
{
    protected $section_id = "wh_stage";

    protected $tbl = "stage_details";

    protected $primary_key = "id";

    protected $tables = array();

    public $Notices;
	public $className = "StageDetail";

    public $update_tree_child = true;
    public $one_step_delete = false;
    public $true_delete = false;
    public $useFlag = false;

    public function __construct( $db_wpdb = array() )
    {
    	parent::__construct();

        if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->set_db_tables();
    }

    public function __destruct()
    {
        unset($this->db_wpdb);
        unset($this->tables);
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
            "main"          => $prefix.$this->tbl,
            "stage_header"  => $prefix."stage_header",
            "users"         => $wpdb->users,
            "usermeta"      => $wpdb->usermeta,
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

                    if( $id != "0" )    //update
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

        $isUser = ( $args && $args['user'] )? true : false;
        if( $isUser )
        {
            $field.= ", u.display_name, um.meta_value AS actor_name ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['users']} u ON u.ID = a.action_by ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['usermeta']} um ON um.user_id = u.ID AND um.meta_key = 'first_name' ";
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
        if( isset( $filters['stage_id'] ) )
        {
            if( is_array( $filters['stage_id'] ) )
                $cond.= "AND a.stage_id IN ('" .implode( "','", $filters['stage_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.stage_id = %d ", $filters['stage_id'] );
        }
        if( isset( $filters['not_stage_id'] ) )
        {
            if( is_array( $filters['not_stage_id'] ) )
                $cond.= "AND a.stage_id NOT IN ('" .implode( "','", $filters['not_stage_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.stage_id != %s ", $filters['not_stage_id'] );
        }
        if( isset( $filters['action'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.action = %s ", $filters['action'] );
        }
        if( isset( $filters['action_by'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.action_by = %d ", $filters['action_by'] );
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
                $cd[] = "a.action LIKE '%".$kw."%' ";
                $cd[] = "a.remark LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

            unset( $filters['status'] );
        }

        if( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' )
        {
            unset( $filters['status'] );
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
}

}