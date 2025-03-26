<?php
if ( !defined( "ABSPATH" ) ) exit;

if ( !class_exists( "WCWH_MemberTopup_Class" ) )
{

class WCWH_MemberTopup_Class extends WCWH_CRUD_Controller
{
    protected $section_id = "wh_member_topup";

    protected $tbl = "member_transact";

    protected $primary_key = "id";

    protected $tables = array();

    public $Notices;
	public $className = "MemberTopup";

    public $update_tree_child = true;
    public $one_step_delete = false;
    public $true_delete = false;
    public $useFlag = false;

    public $approvalDelete = false;

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
            "main"          => $prefix.$this->tbl,
            "meta"          => $prefix.$this->tbl."meta",
           
            "warehouse"     => $prefix."warehouse",
            "status"        => $prefix."status",

            "customer"      => $prefix."customer",
            "member"        => $prefix."member",
        );
    }

    public function update_metas( $id, $metas )
    {
        if( !$id || ! $metas ) return false;
        
        foreach( $metas as $key => $value )
        {
            if( is_array( $value ) )
            {
                delete_member_transact_meta( $id, $key );
                foreach( $value as $val )
                {
                    add_member_transact_meta( $id, $key, $val );
                }
            }
            else
            {
                update_member_transact_meta( $id, $key, $value );
            }
        }
        
        return true;
    }
    
    public function delete_metas( $id )
    {
        if( ! $id ) return false;
        
        $metas = get_member_transact_meta( $id );
        if( $metas )
        {
            foreach( $metas as $key => $value )
            {
                delete_member_transact_meta( $id, $key );
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
                        if( $succ && $this->useFlag && $exist['flag'] > 0 && ! $this->approvalDelete )
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
                case "post":
                    $id = $datas['id'];
                    if( $id > 0 )
                    {
                        $exist = $this->select( $id );
                        if( null === $exist )
                        {
                            $succ = false;
                            if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
                        }
                        if( $succ && $this->useFlag && $exist['flag'] > 0 && ! $this->approvalDelete )
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
                                    $datas['status'] = 6;
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
                                    $this->Notices->set_notice( "post-fail", "error", $this->className."|action_handler|".$action );
                                }
                            }
                            else
                            {
                                $succ = false;
                                $this->Notices->set_notice( "post-fail", "error", $this->className."|action_handler|".$action );
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
                case "unpost":
                    $id = $datas['id'];
                    if( $id > 0 )
                    {
                        $exist = $this->select( $id );
                        if( null === $exist )
                        {
                            $succ = false;
                            if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
                        }
                        if( $succ && $this->useFlag && $exist['flag'] > 0 && ! $this->approvalDelete )
                        {
                            $succ = false;
                            if( $this->Notices ) $this->Notices->set_notice( "prevent-action", "error", $this->className."|action_handler|".$action );
                        }
                        if( $succ ) 
                        {
                            if( isset( $exist['status'] ) )
                            {
                                if( $exist['status'] == 6 )
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
                                    $this->Notices->set_notice( "unpost-fail", "error", $this->className."|action_handler|".$action );
                                }
                            }
                            else
                            {
                                $succ = false;
                                $this->Notices->set_notice( "unpost-fail", "error", $this->className."|action_handler|".$action );
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
                if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
            }
        }

        if( isset( $filters['seller'] ) )
        {
            $dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

        $dbname = !empty( $dbname )? $dbname : $this->dbName();

        $mdb = "";
        if( ! $dbname && isset( $args['mst_seller'] ) )
        {
            $mdb = get_warehouse_meta( $args['mst_seller'], 'dbname', true );
            $mdb = ( $mdb )? $mdb."." : "";
        }
        
        $field = "a.* ";
        $table = "{$dbname}{$this->tables['main']} a ";
        $cond = "";
        $grp = "";
        $ord = "";
        $l = "";

        $field.= ", m.customer_id, m.serial, m.total_debit, m.total_used, m.balance, m.phone_no, m.email ";
        $table.= "LEFT JOIN {$dbname}{$mdb}{$this->tables['member']} m ON m.id = a.member_id ";

        $isCustomer = ( $args && $args['customer'] )? true : false;
        if( $isCustomer )
        {
            $field.= ", c.name AS customer_name, c.code AS customer_code, c.serial AS customer_serial, c.uid AS customer_uid ";
            $table.= "LEFT JOIN {$dbname}{$mdb}{$this->tables['customer']} c ON c.id = m.customer_id ";
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
        if( isset( $filters['warehouse_id'] ) )
        {
            if( is_array( $filters['warehouse_id'] ) )
                $cond.= "AND a.warehouse_id IN ('" .implode( "','", $filters['warehouse_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
        }
        if( isset( $filters['member_id'] ) )
        {
            if( is_array( $filters['member_id'] ) )
                $cond.= "AND a.member_id IN ('" .implode( "','", $filters['member_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.member_id = %s ", $filters['member_id'] );
        }
        if( isset( $filters['doc_type'] ) )
        {
            if( is_array( $filters['doc_type'] ) )
                $cond.= "AND a.doc_type IN ('" .implode( "','", $filters['doc_type'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.doc_type = %s ", $filters['doc_type'] );
        }
        if( isset( $filters['docno'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.docno = %s ", $filters['docno'] );
        }
        if( isset( $filters['sdocno'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.sdocno = %s ", $filters['sdocno'] );
        }
        
        if( isset( $filters['created_by'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.created_by = %d ", $filters['created_by'] );
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
                $cd[] = "a.remarks LIKE '%".$kw."%' ";

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
                $table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['meta']} {$meta_key} ON {$meta_key}.member_transact_id = a.id AND {$meta_key}.meta_key = %s ", $meta_key );
                
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
            $order = [ 'a.id' => 'DESC' ];
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
    
    public function get_export_data( $filters = array() )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();
        
        $field = "a.warehouse_id, a.member_id, a.docno, a.sdocno, a.doc_type, a.amount, a.remarks, a.status, a.flag ";
        $field.= ", ma.meta_value AS customer_code ";
        
        $table = "{$this->tables['main']} a ";
        $table.= "LEFT JOIN {$this->tables['meta']} ma ON ma.member_transact_id = a.id AND ma.meta_key = 'customer_code' ";

        $cond = $wpdb->prepare( "AND a.status >= %d AND a.flag = %d ", 0, 1 );
        $grp = "";
        $ord = "";
        $l = "";

        if( $filters )
        {
            foreach( $filters as $key => $value )
            {
                if( is_numeric( $value ) ) continue;
                if( $value == "" || $value === null ) unset( $filters[$key] );
                if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
            }
        }

        if( isset( $filters['warehouse_id'] ) && !empty( $filters['warehouse_id'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
        }

        if( isset( $filters['id'] ) )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND a.id IN ('" .implode( "','", $filters['id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.id = %d ", $filters['id'] );
        }

        if( isset( $filters['from_date'] ) && !empty( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.lupdate_at >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) && !empty( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.lupdate_at <= %s ", $filters['to_date'] );
        }

        //group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

        //order
        $order = !empty( $order )? $order : [ 'a.created_at' => 'ASC' ];
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

}

}