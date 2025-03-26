<?php
if ( !defined( "ABSPATH" ) ) exit;

if ( !class_exists( "WCWH_Pricing" ) )
{

class WCWH_Pricing extends WCWH_CRUD_Controller
{
    protected $section_id = "wh_pricing";

    protected $tbl = "pricing";

    protected $primary_key = "id";

    protected $tables = array();

    public $Notices;
    public $className = "Pricing";

    public $update_tree_child = true;
    public $one_step_delete = false;
    public $true_delete = false;
    public $useFlag = false;

    public $approvalDelete = false;

    public $price_type = 'price';

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
            "main"          => $prefix.$this->tbl,
            "meta"          => $prefix.$this->tbl."meta",
            "price"         => $prefix."price",
            "price_ref"     => $prefix."price_ref",
            "price_margin"  => $prefix."price_margin",
            "items"         => $prefix."items",
            "items_tree"    => $prefix."items_tree",
            "itemsmeta"     => $prefix."itemsmeta",
            "reprocess"     => $prefix."reprocess_item",
            "uom"           => $prefix."uom",
            "item_group"    => $prefix."item_group",
            "item_store_type" => $prefix."item_store_type",
            "category"      => $wpdb->prefix."terms",
            "category_tree" => $prefix."item_category_tree",
            "brand"         => $prefix."brand",
            "supplier"      => $prefix."supplier",
            "company"       => $prefix."company",
            "warehouse"     => $prefix."warehouse",
            "inventory"     => $prefix."inventory",
            "status"        => $prefix."status",
            "scheme"        => $preifx."scheme",
        );
    }

    public function update_metas( $id, $metas )
    {
        if( !$id || ! $metas ) return false;
        
        foreach( $metas as $key => $value )
        {
            if( is_array( $value ) )
            {
                delete_pricing_meta( $id, $key );
                foreach( $value as $val )
                {
                    add_pricing_meta( $id, $key, $val );
                }
            }
            else
            {
                update_pricing_meta( $id, $key, $value );
            }
        }
        
        return true;
    }
    
    public function delete_metas( $id )
    {
        if( ! $id ) return false;
        
        $metas = get_pricing_meta( $id );
        if( $metas )
        {
            foreach( $metas as $key => $value )
            {
                delete_pricing_meta( $id, $key );
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

        /*if( isset( $filters['seller'] ) )
        {
            $dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }*/
        $dbname = "";
        
        $field = "a.* ";
        $table = "{$dbname}{$this->tables['main']} a ";
        $cond = "";
        $grp = "";
        $ord = "";
        $l = "";

        if( isset( $filters['seller'] ) || isset( $filters['scheme'] ) || isset( $filters['scheme_lvl'] ) || isset( $filters['ref_id'] ) )
        {
            $table.= "LEFT JOIN {$dbname}{$this->tables['price_ref']} ref ON ref.price_id = a.id AND ref.status != 0 ";

            if( isset( $filters['seller'] ) )
            {
                $cond.= $wpdb->prepare( "AND ref.seller = %s ", $filters['seller'] );
            }
            if( isset( $filters['scheme'] ) )
            {
                $cond.= $wpdb->prepare( "AND ref.scheme = %s ", $filters['scheme'] );
            }
            if( isset( $filters['scheme_lvl'] ) )
            {
                $cond.= $wpdb->prepare( "AND ref.scheme_lvl = %s ", $filters['scheme_lvl'] );
            }
            if( isset( $filters['ref_id'] ) )
            {
                $cond.= $wpdb->prepare( "AND ref.ref_id = %s ", $filters['ref_id'] );
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
        if( isset( $filters['docno'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.docno = %s ", $filters['docno'] );
        }
        if( isset( $filters['sdocno'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.sdocno = %s ", $filters['sdocno'] );
        }
        if( isset( $filters['name'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.name = %s ", $filters['name'] );
        }
        if( isset( $filters['code'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
        }
        if( isset( $filters['type'] ) )
        {
            if( is_array( $filters['type'] ) )
                $cond.= "AND a.type IN ('" .implode( "','", $filters['type'] ). "') ";
            else
                 $cond.= $wpdb->prepare( "AND a.type = %s ", $filters['type'] );
        }
        else
        {
            $cond.= $wpdb->prepare( "AND a.type = %s ", $this->price_type );
        }
        if( isset( $filters['created_by'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.created_by = %d ", $filters['created_by'] );
        }
        
        //-------- 7/9/22 jeff DashboardWid -----//
        if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND ( a.created_at >= %s OR a.lupdate_at >= %s ) ", $filters['from_date'], $filters['from_date']);
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
                $cd[] = "a.docno LIKE '%".$kw."%' ";
                $cd[] = "a.sdocno LIKE '%".$kw."%' ";
                $cd[] = "a.name LIKE '%".$kw."%' ";
                $cd[] = "a.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

            unset( $filters['status'] );
        }

        if( isset( $filters['product_id'] ) )
        {
            $table.= "LEFT JOIN {$dbname}{$this->tables['price']} pr ON pr.price_id = a.id ";
            if( is_array( $filters['product_id'] ) )
                $cond.= "AND pr.product_id IN ('" .implode( "','", $filters['product_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND pr.product_id = %d ", $filters['product_id'] );
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
        else
        {
            $group[] = "a.id";
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
        $cond = $wpdb->prepare( "AND status != %d AND type = %s ", -1, $this->price_type );

        $sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

        $fld = "status, COUNT( status ) AS count ";
        $tbl = "{$this->tables['main']} ";
        $cond = $wpdb->prepare( "AND status != %d AND type = %s ", -1, $this->price_type );
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
    
    //-------- 7/9/22 jeff DashboardWid -----//     
    public function get_dist_item($filters=[], $orders=[], $args=[])
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
        if(!$filters['pricing_id']) return false;

        $dbname = "";
        $fld = "distinct product_id ";
        $tbl = "{$dbname}{$this->tables['price']} ";
        $cond = "";
        $grp = "";
        $ord = "";
        $l = "";

        if( is_array( $filters['pricing_id'] ) )
            $cond = "AND price_id IN ('" .implode( "','", $filters['pricing_id'] ). "') ";
        else
            $cond = $wpdb->prepare( "AND price_id = %d ", $filters['pricing_id'] );

        $sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
        $results = $wpdb->get_results( $sql , ARRAY_A );

        return $results;
    }
    //-------- 7/9/22 jeff DashboardWid -----// 

    /*
SELECT main.* 
, cat.slug AS cat_slug, cat.name AS cat_name
, grp.name AS grp_name, grp.code AS grp_code
, uom.code AS uom_code, uom.name AS uom_name
, group_concat( distinct ta.code order by t.level desc separator ',' ) as breadcrumb_code , group_concat( distinct ta.id order by t.level desc separator ',' ) as breadcrumb_id , group_concat( ta.status order by t.level desc separator ',' ) as breadcrumb_status 
, prc.*, stk.*
FROM wp_stmm_wcwh_items main 
INNER JOIN wp_stmm_wcwh_items_tree t ON t.descendant = main.id 
INNER JOIN wp_stmm_wcwh_items ta force index(primary) ON ta.id = t.ancestor 
LEFT JOIN wp_stmm_wcwh_uom uom ON uom.code = main._uom_code 
LEFT JOIN wp_stmm_wcwh_item_group grp ON grp.id = main.grp_id 
LEFT JOIN wp_stmm_terms cat ON cat.term_id = main.category 
LEFT JOIN wp_stmm_wcwh_status stat ON stat.status = main.status AND stat.type = 'default' 
LEFT JOIN wp_stmm_wcwh_status flag ON flag.status = main.flag AND flag.type = 'flag' 
LEFT JOIN wp_stmm_wcwh_inventory stk ON stk.prdt_id = main.id AND stk.strg_id = 1
LEFT JOIN (
    SELECT a.*
    FROM (
        SELECT i.id as item_id, pr.price_id, p.docno, p.sdocno, p.code AS price_code, mp.type AS price_type, mp.since, p.created_by, p.created_at
        , mg.price_value AS margin, pr.unit_price AS uprice, ROUND( pr.unit_price + ( pr.unit_price * ( mg.price_value / 100 ) ), 2 ) AS unit_price
        FROM wp_stmm_wcwh_items i 
        LEFT JOIN wp_stmm_wcwh_price_margin mg ON mg.id = (
            SELECT b.id 
            FROM wp_stmm_wcwh_pricing a 
            LEFT JOIN wp_stmm_wcwh_price_margin b ON b.price_id = a.id AND b.status > 0
            LEFT JOIN wp_stmm_wcwh_price_ref c ON c.price_id = a.id AND c.status > 0
            WHERE 1 AND ( b.product_id = i.id OR b.product_id = 0 ) AND c.seller = '1025-MWT3' AND a.type = 'margin'
            AND a.since <= '2021-07-01 23:59:59' AND a.status > 0 AND a.flag > 0 
            AND ( ( c.scheme = 'default' ) OR ( c.scheme = 'client_code' AND c.ref_id = 'C0002' ) ) 
            ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC LIMIT 0,1
        )
        LEFT JOIN wp_stmm_wcwh_pricing mp ON mp.id = mg.price_id
        LEFT JOIN wp_stmm_wcwh_pricingmeta mga ON mga.pricing_id = mg.price_id AND mga.meta_key = 'margin_source'
        LEFT JOIN wp_stmm_wcwh_price pr ON pr.id = (
            SELECT b.id
            FROM wp_stmm_wcwh_pricing a 
            LEFT JOIN wp_stmm_wcwh_price b ON b.price_id = a.id AND b.status > 0 
            LEFT JOIN wp_stmm_wcwh_price_ref c ON c.price_id = a.id AND c.status > 0 
            WHERE 1 AND b.product_id = i.id AND c.seller = mga.meta_value AND a.type = 'price'
            AND a.since <= '2021-07-01 23:59:59' AND a.status > 0 AND a.flag > 0 
            AND ( ( c.scheme = 'default' ) ) 
            ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC LIMIT 0,1
        )
        LEFT JOIN wp_stmm_wcwh_pricing p ON p.id = pr.price_id
    UNION ALL
        SELECT i.id as item_id, pr.price_id, p.docno, p.sdocno, p.code AS price_code, p.type AS price_type, p.since, p.created_by, p.created_at
        , 0 AS margin, pr.unit_price AS uprice, pr.unit_price
        FROM wp_stmm_wcwh_items i 
        LEFT JOIN wp_stmm_wcwh_price pr ON pr.id = (
            SELECT b.id
            FROM wp_stmm_wcwh_pricing a 
            LEFT JOIN wp_stmm_wcwh_price b ON b.price_id = a.id AND b.status > 0 
            LEFT JOIN wp_stmm_wcwh_price_ref c ON c.price_id = a.id AND c.status > 0 
            WHERE 1 AND b.product_id = i.id AND c.seller = '1025-MWT3' AND a.type = 'price'
            AND a.since <= '2021-07-01 23:59:59' AND a.status > 0 AND a.flag > 0 
            AND ( ( c.scheme = 'default' ) OR ( c.scheme = 'client_code' AND c.ref_id = 'C0002' ) ) 
            ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC LIMIT 0,1
        )
        LEFT JOIN wp_stmm_wcwh_pricing p ON p.id = pr.price_id
        ORDER BY created_at DESC, since DESC, price_id DESC
    ) a
    WHERE 1 
    GROUP BY a.item_id
) prc ON prc.item_id = main.id
WHERE 1 AND main.status > 0 AND main.flag = 1 
GROUP BY main.code, main.serial, main.id 
ORDER BY stat.order DESC , flag.order DESC , breadcrumb_code ASC , main.code ASC ;
    */
    public function get_latest_price( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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

        $field = "a.* ";

        $table = "{$this->tables['items']} a ";
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

            $table.= "INNER JOIN {$this->tables['items_tree']} t ON t.descendant = a.id ";
            $table.= "INNER JOIN {$this->tables['items']} ta force index(primary) ON ta.id = t.ancestor ";
            $table.= "INNER JOIN {$this->tables['items_tree']} tt ON tt.ancestor = a.id ";

            $cgroup = [ "a.code", "a.serial", "a.id " ];
        }

        $field.= ", meta_a.meta_value AS inconsistent_unit ";
        $table.= "LEFT JOIN {$this->tables['itemsmeta']} meta_a ON meta_a.items_id = a.id AND meta_a.meta_key = 'inconsistent_unit' ";

        $isUom = ( $args && $args['uom'] )? true : false;
        if( $isUom )
        {
            $field.= ", uom.code AS uom_code, uom.name AS uom_name, uom.fraction AS uom_fraction ";
            $table.= "LEFT JOIN {$this->tables['uom']} uom ON uom.code = a._uom_code ";
        }

        $isGrp = ( $args && $args['group'] )? true : false;
        if( $isGrp )
        {
            $field.= ", grp.code AS grp_code, grp.name AS grp_name ";
            $table.= " LEFT JOIN {$this->tables['item_group']} grp ON grp.id = a.grp_id ";
        }

        $isStore = ( $args && $args['store'] )? true : false;
        if( $isStore )
        {
            $field.= ", store.code AS store_code, store.name AS store_name ";
            $table.= " LEFT JOIN {$this->tables['item_store_type']} store ON store.id = a.store_type_id  ";
        }

        $isCat = ( $args && $args['category'] )? true : false;
        if( $isCat )
        {
            $field.= ", cat.slug AS cat_code, cat.name AS cat_name ";
            $table.= " LEFT JOIN {$this->tables['category']} cat ON cat.term_id = a.category ";

            $subsql = "SELECT ancestor AS id FROM {$this->tables['category_tree']} ";
            $subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

            $table.= "LEFT JOIN {$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

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
            $field.= ", brand.code AS brand_code, brand.name AS brand_name ";
            $table.= " LEFT JOIN {$this->tables['itemsmeta']} ibr ON ibr.items_id = a.id AND ibr.meta_key = '_brand' ";
            $table.= " LEFT JOIN {$this->tables['brand']} brand ON brand.code = ibr.meta_value ";

            if( isset( $filters['_brand'] ) )
            {
                if( is_array( $filters['_brand'] ) )
                    $cond.= "AND brand.code IN ('" .implode( "','", $filters['_brand'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND brand.code = %s ", $filters['_brand'] );
            }
        }

        $isStk = ( $args && $args['inventory'] )? true : false;
        if( $isStk )
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

            $field.= ", @needMetric:= IF( rep.id > 0 AND meta_a.meta_value > 0 {$metric}, 1, 0 ) AS required_unit ";
            $table.= " LEFT JOIN {$this->tables['reprocess']} rep ON rep.items_id = a.id ";
            
            //--------12/9
            $field.= ", stk.qty AS stock_qty, stk.allocated_qty AS stock_allocated, stk.reserved_qty ";
            //---------12/9

            $field.= ", IF( stk.wa_price > 0, stk.wa_price, stk.wa_last_price ) AS avg_cost, stk.latest_in_cost AS latest_cost ";
            $field.= ", ROUND( IF( stk.wa_unit > 0, stk.wa_unit / stk.wa_qty, stk.total_in_unit / stk.total_in ), 3 ) AS average_in_unit ";
            $field.= ", IF( @needMetric, IF( stk.wa_unit > 0, stk.wa_unit / stk.wa_qty, stk.total_in_unit / stk.total_in ) * pr.unit_price, 0 ) AS avg_unit_price ";
            $table.= " LEFT JOIN {$this->tables['inventory']} stk ON stk.prdt_id = a.id ";
            $table.= $wpdb->prepare( "AND stk.strg_id = %s ", $args['inventory'] );
        }

        $subSql = $this->get_price_list( $filters );
        $field.= ", pr.* ";
        $table.= "LEFT JOIN ( {$subSql} ) pr ON pr.item_id = a.id ";

        $mspo_items = $this->setting['mspo_hide']['items'];
        if( !empty( $mspo_items ) && !isset( $args['mspo'] ) )
        {
            $cond.= "AND a.id NOT IN ('" .implode( "','", $mspo_items ). "') ";
        }
        
        if( isset( $filters['id'] ) )
        {
            if( $isTree )
            {
                if( is_array( $filters['id'] ) )
                    $cond.= "AND ( tt.descendant IN ('" .implode( "','", $filters['id'] ). "') OR t.ancestor IN ('" .implode( "','", $filters['id'] ). "') ) ";
                else
                    $cond.= $wpdb->prepare( "AND ( tt.descendant = %d OR t.ancestor = %d ) ", $filters['id'], $filters['id'] );
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
            $cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
        }
        if( isset( $filters['serial'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.serial = %s ", $filters['serial'] );
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
        if( isset( $filters['inconsistent_unit'] ) )
            {
                $cond.= $wpdb->prepare( "AND meta_a.meta_value = %s ", $filters['inconsistent_unit'] );
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
                $cd[] = "a._sku LIKE '%".$kw."%' ";
                $cd[] = "a.name LIKE '%".$kw."%' ";
                $cd[] = "a.code LIKE '%".$kw."%' ";
                $cd[] = "a.serial LIKE '%".$kw."%' ";
                $cd[] = "a.serial2 LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
        }

        if( isset( $filters['pricing'] ) )
        {
            switch( $filters['pricing'] )
            {
                case 'yes':
                    $cond.= "AND pr.unit_price > 0 ";
                break;
                case 'no':
                    $cond.= "AND ( pr.unit_price IS NULL OR pr.unit_price <= 0 ) ";
                break;
            }
        }

        //metas
        if( $args['meta'] )
        {
            foreach( $args['meta'] as $meta_key )
            {
                $field.= ", {$meta_key}.meta_value AS {$meta_key} ";
                $table.= $wpdb->prepare( "LEFT JOIN {$this->tables['itemsmeta']} {$meta_key} ON {$meta_key}.items_id = a.id AND {$meta_key}.meta_key = %s ", $meta_key );
                
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

            $table.= "LEFT JOIN {$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
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
             $table.= "LEFT JOIN {$this->tables['status']} flag ON flag.status = a.flag AND flag.type = 'flag' ";
             $corder["flag.order"] = "DESC";
        }

        $isTreeOrder = ( $args && $args['treeOrder'] )? true : false;
        if( $isTree && $isTreeOrder )
        {
            $corder[ $args['treeOrder'][0] ] = $args['treeOrder'][1];
        }

        $isUse = ( $args && $args['usage'] )? true : false;
        if( $isUse )
        {
            $cond.= $wpdb->prepare( "AND a.status > %d AND a.flag = %d ", 0, 1 );
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

    /*
    SELECT a.*
    FROM (
        SELECT i.id as item_id, pr.price_id, p.docno, p.sdocno, p.code AS price_code, mp.type AS price_type, mp.since, mp.created_by, mp.created_at
        , mg.price_value AS margin, pr.unit_price AS uprice, ROUND( pr.unit_price + ( pr.unit_price * ( mg.price_value / 100 ) ), 2 ) AS unit_price
        FROM wp_stmm_wcwh_items i 
        LEFT JOIN wp_stmm_wcwh_price_margin mg ON mg.id = (
            SELECT b.id 
            FROM wp_stmm_wcwh_pricing a 
            LEFT JOIN wp_stmm_wcwh_price_margin b ON b.price_id = a.id AND b.status > 0
            LEFT JOIN wp_stmm_wcwh_price_ref c ON c.price_id = a.id AND c.status > 0
            WHERE 1 AND ( b.product_id = i.id OR b.product_id = 0 ) AND c.seller = '1025-MWT3' AND a.type = 'margin'
            AND a.since <= '2021-07-01 23:59:59' AND a.status > 0 AND a.flag > 0 
            AND ( ( c.scheme = 'default' ) OR ( c.scheme = 'client_code' AND c.ref_id = 'C0002' ) ) 
            ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC LIMIT 0,1
        )
        LEFT JOIN wp_stmm_wcwh_pricing mp ON mp.id = mg.price_id
        LEFT JOIN wp_stmm_wcwh_pricingmeta mga ON mga.pricing_id = mg.price_id AND mga.meta_key = 'margin_source'
        LEFT JOIN wp_stmm_wcwh_price pr ON pr.id = (
            SELECT b.id
            FROM wp_stmm_wcwh_pricing a 
            LEFT JOIN wp_stmm_wcwh_price b ON b.price_id = a.id AND b.status > 0 
            LEFT JOIN wp_stmm_wcwh_price_ref c ON c.price_id = a.id AND c.status > 0 
            WHERE 1 AND b.product_id = i.id AND c.seller = mga.meta_value AND a.type = 'price'
            AND a.since <= '2021-07-01 23:59:59' AND a.status > 0 AND a.flag > 0 
            AND ( ( c.scheme = 'default' ) ) 
            ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC LIMIT 0,1
        )
        LEFT JOIN wp_stmm_wcwh_pricing p ON p.id = pr.price_id
    UNION ALL
        SELECT i.id as item_id, pr.price_id, p.docno, p.sdocno, p.code AS price_code, p.type AS price_type, p.since, p.created_by, p.created_at
        , 0 AS margin, pr.unit_price AS uprice, pr.unit_price
        FROM wp_stmm_wcwh_items i 
        LEFT JOIN wp_stmm_wcwh_price pr ON pr.id = (
            SELECT b.id
            FROM wp_stmm_wcwh_pricing a 
            LEFT JOIN wp_stmm_wcwh_price b ON b.price_id = a.id AND b.status > 0 
            LEFT JOIN wp_stmm_wcwh_price_ref c ON c.price_id = a.id AND c.status > 0 
            WHERE 1 AND b.product_id = i.id AND c.seller = '1025-MWT3' AND a.type = 'price'
            AND a.since <= '2021-07-01 23:59:59' AND a.status > 0 AND a.flag > 0 
            AND ( ( c.scheme = 'default' ) OR ( c.scheme = 'client_code' AND c.ref_id = 'C0002' ) ) 
            ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC LIMIT 0,1
        )
        LEFT JOIN wp_stmm_wcwh_pricing p ON p.id = pr.price_id
        ORDER BY created_at DESC, since DESC, price_id DESC
    ) a
    WHERE 1 
    GROUP BY a.item_id
    */
    public function get_price_list( $filters = [], $run = false )
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

        if( ! $filters['seller'] ) return false;
        $seller = $filters['seller'];
        $since = ( isset( $filters['on_date'] ) )? $filters['on_date']." 23:59:59" : current_time( 'Y-m-d 23:59:59' );

        $scheme = $wpdb->prepare( "AND c.scheme = %s ", 'default' );
        if( isset( $filters['client_code'] ) )
        {
            $scheme = $wpdb->prepare( "AND ( ( c.scheme = 'default' ) OR ( c.scheme = 'client_code' AND c.ref_id = %s ) ) ", $filters['client_code'] );
        }

    //---------------------------------------------------------------------------------------------------------
        //Get Margined Price
        $field = "i.id as item_id, pr.price_id, p.docno, p.sdocno, p.code AS price_code, mp.type AS price_type, p.since, p.created_by ";
        $field.= ", p.created_at , mg.price_value AS margin, pr.unit_price AS uprice ";
        $field.= ", @rn:= IF( mgc.meta_value IS NULL OR mgc.meta_value = 0, 0.01, mgc.meta_value ) AS rn 
            , ROUND( CASE 
            WHEN mgb.meta_value = 'ROUND' 
                THEN ROUND( ROUND( pr.unit_price+( pr.unit_price*( mg.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mgb.meta_value = 'CEIL' 
                THEN CEIL( ROUND( pr.unit_price+( pr.unit_price*( mg.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mgb.meta_value = 'FLOOR' 
                THEN FLOOR( ROUND( pr.unit_price+( pr.unit_price*( mg.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mgb.meta_value IS NULL OR mgb.meta_value = 'DEFAULT' 
                THEN ROUND( pr.unit_price+( pr.unit_price*( mg.price_value/100 ) ), 2 ) 
            END, 2 ) AS unit_price ";

        $table = "{$this->tables['items']} i ";

        //----------------------------------------
            $tbl = "{$this->tables['main']} a ";
            $tbl.= "LEFT JOIN {$this->tables['price_margin']} b ON b.price_id = a.id AND b.status > 0 ";
            $tbl.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
            $cd = $wpdb->prepare( "AND ( b.product_id = i.id OR b.product_id = 0 ) AND a.type = %s ", 'margin' );
            $cd.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );
            $cd.= $wpdb->prepare( " AND c.seller = %s AND a.since <= %s ", $seller, $since );
            $cd.= $scheme;
            $o = "ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC ";
            $l = "LIMIT 0,1 ";
            $marginSql = "SELECT b.id FROM {$tbl} WHERE 1 {$cd} {$o} {$l} ";
        //----------------------------------------

        $table.= "LEFT JOIN {$this->tables['price_margin']} mg ON mg.id = ( {$marginSql} ) ";
        $table.= "LEFT JOIN {$this->tables['main']} mp ON mp.id = mg.price_id ";
        $table.= "LEFT JOIN {$this->tables['meta']} mga ON mga.pricing_id = mg.price_id AND mga.meta_key = 'margin_source' ";
        $table.= "LEFT JOIN {$this->tables['meta']} mgb ON mgb.pricing_id = mg.price_id AND mgb.meta_key = 'round_type' ";
        $table.= "LEFT JOIN {$this->tables['meta']} mgc ON mgc.pricing_id = mg.price_id AND mgc.meta_key = 'round_nearest' ";

        //----------------------------------------
            $tbl = "{$this->tables['main']} a ";
            $tbl.= "LEFT JOIN {$this->tables['price']} b ON b.price_id = a.id AND b.status > 0 ";
            $tbl.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
            $cd = $wpdb->prepare( "AND b.product_id = i.id AND a.type = %s ", 'price' );
            $cd.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );
            $cd.= $wpdb->prepare( " AND c.seller = mga.meta_value AND a.since <= %s ", $since );
            $cd.= "AND ( c.scheme = 'default' ) ";
            $o = "ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC ";
            $l = "LIMIT 0,1 ";
            $priceSql = "SELECT b.id FROM {$tbl} WHERE 1 {$cd} {$o} {$l} ";
        //----------------------------------------

        $table.= "LEFT JOIN {$this->tables['price']} pr ON pr.id = ( {$priceSql} ) ";
        $table.= "LEFT JOIN {$this->tables['main']} p ON p.id = pr.price_id ";

        $cond = "AND pr.price_id > 0 ";
        
        if( $filters['id'] )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND i.id IN( '".implode( "', '", $filters['id'] )."' ) ";
            else
                $cond.= $wpdb->prepare( "AND i.id = %d ", $filters['id'] );
        }

        $margined = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

    //---------------------------------------------------------------------------------------------------------
        //Get Price  
        $field = "i.id as item_id, pr.price_id, p.docno, p.sdocno, p.code AS price_code, p.type AS price_type, p.since, p.created_by ";
        $field.= ", p.created_at , 0 AS margin, pr.unit_price AS uprice, '0.01' AS rn, pr.unit_price ";

        $table = "{$this->tables['items']} i ";

        //----------------------------------------
            $tbl = "{$this->tables['main']} a ";
            $tbl.= "LEFT JOIN {$this->tables['price']} b ON b.price_id = a.id AND b.status > 0 ";
            $tbl.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
            $cd = $wpdb->prepare( "AND b.product_id = i.id AND a.type = %s ", 'price' );
            $cd.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );
            $cd.= $wpdb->prepare( " AND c.seller = %s AND a.since <= %s ", $seller, $since );
            $cd.= $scheme;
            $o = "ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC ";
            $l = "LIMIT 0,1 ";
            $priceSql = "SELECT b.id FROM {$tbl} WHERE 1 {$cd} {$o} {$l} ";
        //----------------------------------------

        $table.= "LEFT JOIN {$this->tables['price']} pr ON pr.id = ( {$priceSql} ) ";
        $table.= "LEFT JOIN {$this->tables['main']} p ON p.id = pr.price_id ";

        $cond = "";
        
        if( $filters['id'] )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND i.id IN( '".implode( "', '", $filters['id'] )."' ) ";
            else
                $cond.= $wpdb->prepare( "AND i.id = %d ", $filters['id'] );
        }

        $priced = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

    //---------------------------------------------------------------------------------------------------------
        //Main
        $field = "a.*";
        $sub_ord = "ORDER BY created_at DESC, since DESC, price_id DESC ";
        $table = "( ( {$margined} ) UNION ALL ( {$priced} ) {$sub_ord} ) a ";
        $cond = "";
        $grp = "GROUP BY a.item_id ";
        $ord = "";
        $lmt = "";

        $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$lmt} ";
        
        if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );

        return $query;
    }
    
    public function get_price( $prdt_id = 0, $seller = '', $schemes = array(), $datetime = '' )
    {
        if( ! $prdt_id || ! $seller ) return false;

        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();
        
        $filters = [
            'seller' => $seller,
            'id' => $prdt_id,
        ];
        if( $datetime ) $filters['on_date'] = $datetime;
        if( $schemes['client_code'] ) $filters['client_code'] = $schemes['client_code'];
        
        $rows = $this->get_price_list( $filters, true );
        if( $rows )
            return $rows[0];
        
        return [];
    }
    
    public function get_price_old( $prdt_id = 0, $seller = '', $schemes = array(), $datetime = '' )
    {
        if( ! $prdt_id || ! $seller ) return false;

        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();
        
    //---------------------------------------------------------------------------------------------------------
        //Get Price
        $field = "a.id AS price_id, a.docno, a.sdocno, a.code AS price_code, a.since, a.created_by, a.created_at ";
        $field.= ", c.seller, c.scheme, c.scheme_lvl, c.ref_id ";
        $field.= ", b.product_id, b.unit_price AS uprice, 0 AS margin, '0.01' AS rn, b.unit_price AS unit_price ";
        
        $table = "{$this->tables['main']} a ";
        $table.= "LEFT JOIN {$this->tables['price']} b ON b.price_id = a.id AND b.status > 0 ";
        $table.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
        
        $cond = $wpdb->prepare( "AND b.product_id = %s AND c.seller = %s AND a.type = %s ", $prdt_id, $seller, 'price' );
        $cond.= $wpdb->prepare( "AND a.since <= %s ", ( $datetime )? $datetime : current_time( 'Y-m-d 23:59:59' ) );
        $cond.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );

        $scheme_cd = array( "( c.scheme = 'default' )" );
        if( $schemes )
        {
            foreach( $schemes as $scheme => $ref_id )
            {
                if( $ref_id ) $scheme_cd[] = $wpdb->prepare( "( c.scheme = %s AND c.ref_id = %s )", $scheme, $ref_id );
            }
        }
        $cond.= "AND ( ".implode( " OR ", $scheme_cd )." ) ";

        $grp = "";
        $ord = "";

        $mainSub = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord}";
        
    //---------------------------------------------------------------------------------------------------------
        //Get price by Margin
            $tbl = "{$this->tables['main']} a ";
            $tbl.= "LEFT JOIN {$this->tables['price']} b ON b.price_id = a.id AND b.status > 0 ";
            $tbl.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
            $cd = $wpdb->prepare( "AND b.product_id = %s AND c.seller = ma.meta_value AND a.type = %s ", $prdt_id, 'price' );
            $cd.= $wpdb->prepare( "AND a.since <= %s ", ( $datetime )? $datetime : current_time( 'Y-m-d 23:59:59' ) );
            $cd.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );
            $cd.= $wpdb->prepare( "AND ( ( c.scheme = %s ) ) ", 'default' );
            $o = "ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC ";
            $l = "LIMIT 0,1 ";
            $innerSql = "SELECT b.id FROM {$tbl} WHERE 1 {$cd} {$o} {$l}";

        $field = "a.id AS price_id, a.docno, a.sdocno, a.code AS price_code, p.since, p.created_by, p.created_at ";
        $field.= ", c.seller, c.scheme, c.scheme_lvl, c.ref_id ";
        $field.= ", dp.product_id, dp.unit_price AS uprice, b.price_value AS margin ";
        $field.= ", @rn:= IF( mc.meta_value IS NULL OR mc.meta_value = 0, 0.01, mc.meta_value ) AS rn 
            , ROUND( CASE 
            WHEN mb.meta_value = 'ROUND' 
                THEN ROUND( ROUND( dp.unit_price+( dp.unit_price*( b.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mb.meta_value = 'CEIL' 
                THEN CEIL( ROUND( dp.unit_price+( dp.unit_price*( b.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mb.meta_value = 'FLOOR' 
                THEN FLOOR( ROUND( dp.unit_price+( dp.unit_price*( b.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mb.meta_value IS NULL OR mb.meta_value = 'DEFAULT' 
                THEN ROUND( dp.unit_price+( dp.unit_price*( b.price_value/100 ) ), 2 ) 
            END, 2 ) AS unit_price ";
        
        $table = "{$this->tables['main']} a ";
        $table.= "LEFT JOIN {$this->tables['price_margin']} b ON b.price_id = a.id AND b.status > 0 ";
        $table.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
        $table.= "LEFT JOIN {$this->tables['meta']} ma ON ma.pricing_id = a.id AND ma.meta_key = 'margin_source' ";
        $table.= "LEFT JOIN {$this->tables['meta']} mb ON mb.pricing_id = a.id AND mb.meta_key = 'round_type' ";
        $table.= "LEFT JOIN {$this->tables['meta']} mc ON mc.pricing_id = a.id AND mc.meta_key = 'round_nearest' ";
        $table.= "LEFT JOIN {$this->tables['price']} dp ON dp.id = ( {$innerSql} ) ";
        $table.= "LEFT JOIN {$this->tables['main']} p ON p.id = dp.price_id ";
        
        $cond = $wpdb->prepare( "AND ( b.product_id = %s OR b.product_id = %s ) ", $prdt_id, 0 );
        $cond.= $wpdb->prepare( "AND c.seller = %s AND a.type = %s ", $seller, 'margin' );
        $cond.= $wpdb->prepare( "AND a.since <= %s ", ( $datetime )? $datetime : current_time( 'Y-m-d 23:59:59' ) );
        $cond.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );

        $scheme_cd = array( "( c.scheme = 'default' )" );
        if( $schemes )
        {
            foreach( $schemes as $scheme => $ref_id )
            {
                if( $ref_id ) $scheme_cd[] = $wpdb->prepare( "( c.scheme = %s AND c.ref_id = %s )", $scheme, $ref_id );
            }
        }
        $cond.= "AND ( ".implode( " OR ", $scheme_cd )." ) ";

        $grp = "";
        $ord = "";

        $marginSub = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord}";

    //---------------------------------------------------------------------------------------------------------

        $field = "a.* ";
        $table = "{$mainSub} UNION ALL {$marginSub} ";
        $cond = $wpdb->prepare( "AND a.product_id > %d ", 0 );
        
        $grp = "";
        $ord = "ORDER BY a.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.price_id DESC LIMIT 0,1 ";

        $sql = "SELECT {$field} FROM ( {$table} ) a WHERE 1 {$cond} {$ord} ;";
        //pd($sql);
        $results = $wpdb->get_row( $sql , ARRAY_A );
        
        return $results;
    }
    
    public function get_export_data( $filters = array() )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();
        
        $field = "a.docno, a.sdocno, a.code AS price_code, c.seller, c.scheme, c.scheme_lvl, c.ref_id, a.since, a.status, a.flag ";
        $field.= ", i.name, i.code, i.serial, b.unit_price ";
        $table = "{$this->tables['main']} a ";
        $table.= "LEFT JOIN {$this->tables['price']} b ON b.price_id = a.id AND b.status != 0 ";
        $table.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status != 0 ";
        $table.= "LEFT JOIN {$this->tables['items']} i ON i.id = b.product_id ";

        $cond = $wpdb->prepare( "AND a.status != %d AND a.flag = %d ", 0, 1 );
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

        if( isset( $filters['seller'] ) && !empty( $filters['seller'] ) )
        {
            $cond.= $wpdb->prepare( "AND c.seller = %s ", $filters['seller'] );
        }
        if( isset( $filters['client_code'] ) && !empty( $filters['client_code'] ) )
        {
            $cond.= $wpdb->prepare( "AND ( ( c.scheme = 'client_code' AND c.ref_id = %s ) OR ( c.scheme = 'default' ) ) ", $filters['client_code'] );
        }
        else
        {
            $cond.= $wpdb->prepare( "AND c.scheme = %s ", 'default' );
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

    public function get_export_data_for_user( $filters = array() )
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

        $field = "a.name, a._sku, a.code, a.serial, a._uom_code, cat.name AS cat_name ";

        $table = "{$this->tables['items']} a ";
        $cond = "";
        $grp = "";
        $ord = "";
        $l = "";

        //$field.= ", grp.code AS grp_code, grp.name AS grp_name ";
        //$table.= " LEFT JOIN {$this->tables['item_group']} grp ON grp.id = a.grp_id ";

        $table.= " LEFT JOIN {$this->tables['category']} cat ON cat.term_id = a.category ";

        //$field.= ", brand.code AS brand_code, brand.name AS brand_name ";
        //$table.= " LEFT JOIN {$this->tables['itemsmeta']} ibr ON ibr.items_id = a.id AND ibr.meta_key = '_brand' ";
        //$table.= " LEFT JOIN {$this->tables['brand']} brand ON brand.code = ibr.meta_value ";

        //$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
        if( $filters['seller'] )
        {
            $strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$filters['seller'], 'doc_type'=>'pricing' ] );
        }
        
        $field.= ", stk.total_in_avg AS avg_cost, stk.latest_in_cost AS latest_cost ";
        $table.= " LEFT JOIN {$this->tables['inventory']} stk ON stk.prdt_id = a.id ";
        $table.= $wpdb->prepare( "AND stk.strg_id = %d ", $strg_id );

        $subSql = $this->get_price_list( $filters );
        $field.= ", pr.uprice AS def_price, pr.margin, pr.unit_price ";
        $table.= "LEFT JOIN ( {$subSql} ) pr ON pr.item_id = a.id ";

        $cond.= $wpdb->prepare( "AND a.status > %d AND a.flag = %d ", 0, 1 );

        //group
        $group = [];
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

        $sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
        $results = $wpdb->get_results( $sql , ARRAY_A );
        
        return $results;
    }
}

}