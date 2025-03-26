<?php
if ( !defined( "ABSPATH" ) ) exit;

if ( !class_exists( "WCWH_ExchangeRate_Class" ) )
{

class WCWH_ExchangeRate_Class extends WCWH_CRUD_Controller
{
    protected $section_id = "wh_exchange_rate";

    protected $tbl = "exchange_rate";

    protected $primary_key = "id";

    protected $tables = array();

    public $Notices;
	public $className = "ExchangeRate_Class";

    public $update_tree_child = true;
    public $one_step_delete = false;
    public $true_delete = false;
    public $useFlag = true;

    public $approvalDelete = false;

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
           
            "status"        => $prefix."status",
           //"scheme"        => $preifx."scheme",
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

        if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

        //$dbname = "";
        
        $field = "a.* ";
        $table = "{$dbname}{$this->tables['main']} a ";
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
        if( isset( $filters['not_id'] ) )
        {
            if( is_array( $filters['not_id'] ) )
                $cond.= "AND a.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.id != %d ", $filters['not_id'] );
        }
        if( isset( $filters['docno'] ) )
        {
            if( is_array( $filters['docno'] ) )
                $cond.= "AND a.docno NOT IN ('" .implode( "','", $filters['docno'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.docno = %s ", $filters['docno'] );
        }
        if( isset( $filters['from_currency'] ) )
        {
            if( is_array( $filters['from_currency'] ) && !empty( $filters['from_currency'] ) )
                $cond .=  " AND a.from_currency IN ('" .implode( "','", $filters['from_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND a.from_currency = %s ", $filters['from_currency'] );
        }
        if( isset( $filters['to_currency'] ) )
        {
            if( is_array( $filters['to_currency'] ) && !empty( $filters['to_currency'] ))
                $cond .=  " AND a.to_currency IN ('" .implode( "','", $filters['to_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND a.to_currency = %s ", $filters['to_currency'] );
        }

        //Use in Remittance Money form
        if( isset( $filters['effective_date'] ) )
        {
            if( is_array( $filters['effective_date'] ) && !empty( $filters['effective_date'] ))
                $cond .=  " AND a.since IN ('" .implode( "','", $filters['effective_date'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND a.since <= %s ", $filters['effective_date'] );
        }
        if( isset( $filters['e_date'] ) )
        {
            $cond .= $wpdb->prepare( " AND a.since <= CAST(NOW() AS DATE) " );
        }
        //End

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
                $cd[] = "a.from_currency LIKE '%".$kw."%' ";
                $cd[] = "a.to_currency LIKE '%".$kw."%' ";
                $cd[] = "a.rate LIKE '%".$kw."%' ";
                $cd[] = "a.since LIKE '%".$kw."%' ";

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
            $order = [ 'a.docno' => 'DESC' ];
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
        
        $field = "a.docno, a.sdocno, a.from_currency, a.to_currency, a.rate, a.base, a.desc, a.since, a.status, a.flag, a.created_at, a.created_by, a.lupdate_at, a.lupdate_by ";
        $table = "{$this->tables['main']} a ";

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

        if( isset( $filters['id'] ) )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND a.id IN ('" .implode( "','", $filters['id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.id = %d ", $filters['id'] );
        }

        if( isset( $filters['docno'] ) )
        {
            if( is_array( $filters['docno'] ) )
                $cond.= "AND a.docno IN ('" .implode( "','", $filters['docno'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.docno = %d ", $filters['docno'] );
        }

        if( isset( $filters['from_currency'] ) && !empty( $filters['from_currency'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.from_currency = %s ", $filters['from_currency'] );
        }

        if( isset( $filters['to_currency'] ) && !empty( $filters['to_currency'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.to_currency = %s ", $filters['to_currency'] );
        }

        if( isset( $filters['from_date'] ) && !empty( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.since >= %s ", $filters['from_date'] );
        }
        
        if( isset( $filters['to_date'] ) && !empty( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.since <= %s ", $filters['to_date'] );
        }
        if( $filters['status'] != 'all' )
        {
            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
        }
        if( $filters['flag'] != 'all' )
        {
            $cond.= $wpdb->prepare( "AND a.flag = %d ", $filters['flag'] );
        }
        
        //group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

        //order
        $order = !empty( $order )? $order : [ 'a.from_currency' => 'ASC', 'a.to_currency' => 'ASC' ];
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

    /*
        SELECT
            latest_exr.docno,
            latest_exr.base,
            latest_exr.rate,
            latest_exr.status,
            latest_exr.flag,
            latest_exr.id,
            latest_exr.from_currency,
            latest_exr.to_currency,
            latest_exr.since
        FROM
            (
                SELECT
                    ner.docno,
                    ner.base,
                    ner.rate,
                    ner.status,
                    ner.flag,
                    ner.id,
                    ner.from_currency,
                    ner.to_currency,
                    ner.since
                FROM
                    wp_stmm_wcwh_exchange_rate ner
                WHERE
                    1 AND ner.status > 0 AND ner.flag = 1 AND DATE(ner.since) < DATE(NOW())
                GROUP BY
                    ner.from_currency,
                    ner.to_currency,
                    ner.since
                ORDER BY
                    ner.since
                DESC
                ) AS latest_exr
            GROUP BY
                latest_exr.from_currency,
                latest_exr.to_currency;
    */

    /*
        SELECT
            ner.docno,
            ner.base,
            ner.rate,
            ner.status,
            ner.flag,
            ner.id,
            ner.from_currency,
            ner.to_currency,
            ner.since
        FROM
            wp_stmm_wcwh_exchange_rate ner
        WHERE
            1 AND ner.status > 0 AND ner.flag = 1 AND DATE(ner.since) <= DATE('2022-06-23') AND ner.from_currency = "MYR" AND ner.to_currency = "IDR"
        GROUP BY
            ner.from_currency,
            ner.to_currency,
            ner.since,
            ner.docno
        ORDER BY
            ner.since
        DESC
            ,
            ner.id
        DESC
            ;
    */
    public function get_latest_exchange_rate( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
        
        isset($filters['on_date'])? $time = $filters['on_date'] : $time = current_time( 'mysql' );
        
        $dbname = "";
        $field = " latest_exr.docno, latest_exr.base, latest_exr.rate, latest_exr.status, 
        latest_exr.flag, latest_exr.id, latest_exr.from_currency, latest_exr.to_currency,
        latest_exr.since ";
        $table = " (
                    SELECT
                        ner.docno, ner.base,
                        ner.rate,  ner.status,
                        ner.flag,  ner.id,
                        ner.from_currency, ner.to_currency,
                        ner.since
                    FROM
                        {$dbname}{$this->tables['main']} ner
                    WHERE
                        1 AND ner.status > 0 AND ner.flag = 1 AND DATE(ner.since) <= DATE('{$time}')
                    GROUP BY
                        ner.from_currency,
                        ner.to_currency,
                        ner.since,
                        ner.docno
                    ORDER BY
                        ner.since DESC,
                        ner.id DESC
                ) AS latest_exr   
                ";
        $cond = "";
        $grp = "";
        $ord = "";
        $l = ""; 

        //Use in Remittance Money form
        if( isset( $filters['effective_date'] ) )
        {
            if( is_array( $filters['effective_date'] ) && !empty( $filters['effective_date'] ))
                $cond .=  " AND a.since IN ('" .implode( "','", $filters['effective_date'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND latest_exr.since <= %s ", $filters['effective_date'] );
        }
        //End
        
        if( isset( $filters['id'] ) )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND latest_exr.id IN ('" .implode( "','", $filters['id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND latest_exr.id = %d ", $filters['id'] );
        }
        if( isset( $filters['not_id'] ) )
        {
            if( is_array( $filters['not_id'] ) )
                $cond.= "AND latest_exr.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND latest_exr.id != %d ", $filters['not_id'] );
        }
        if( isset( $filters['docno'] ) )
        {
            if( is_array( $filters['docno'] ) )
                $cond.= "AND latest_exr.docno NOT IN ('" .implode( "','", $filters['docno'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND latest_exr.docno = %s ", $filters['docno'] );
        }
        if( isset( $filters['from_currency'] ) )
        {
            if( is_array( $filters['from_currency'] ) && !empty( $filters['from_currency'] ) )
                $cond .=  " AND latest_exr.from_currency IN ('" .implode( "','", $filters['from_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND latest_exr.from_currency = %s ", $filters['from_currency'] );
        }
        if( isset( $filters['to_currency'] ) )
        {
            if( is_array( $filters['to_currency'] ) && !empty( $filters['to_currency'] ))
                $cond .=  " AND latest_exr.to_currency IN ('" .implode( "','", $filters['to_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND latest_exr.to_currency = %s ", $filters['to_currency'] );
        }
        if( isset( $filters['rate'] ) )
        {
            $cond.= $wpdb->prepare( "AND latest_exr.rate = %s ", $filters['rate'] );
        }
        if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND er.since >= %s ", $filters['from_date'] );
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
                $cd[] = "latest_exr.docno LIKE '%".$kw."%' ";
                $cd[] = "latest_exr.from_currency LIKE '%".$kw."%' ";
                $cd[] = "latest_exr.to_currency LIKE '%".$kw."%' ";
                $cd[] = "latest_exr.rate LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

            unset( $filters['status'] );
        }
        
        $group = ["latest_exr.from_currency" , "latest_exr.to_currency"];
        //group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

        //order
        $corder = array();
        if( empty( $order ) )
        {
            $order = ['latest_exr.from_currency' => 'ASC', 'latest_exr.to_currency' => 'ASC'];
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

    public function get_export_latest_data( $filters = array() )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();
              
        if( $filters )
        {
            foreach( $filters as $key => $value )
            {
                if( is_numeric( $value ) ) continue;
                if( $value == "" || $value === null ) unset( $filters[$key] );
                if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
            }
        }

        isset($filters['on_date'])? $time = $filters['on_date'] : $time = current_time( 'mysql' );
        
        $dbname = "";
        $field = " latest_exr.from_currency, latest_exr.to_currency, latest_exr.base, latest_exr.rate, latest_exr.docno ";
        $table = " (
                    SELECT
                        ner.docno, ner.base,
                        ner.rate,  ner.status,
                        ner.flag,  ner.id,
                        ner.from_currency, ner.to_currency,
                        ner.since
                    FROM
                        {$dbname}{$this->tables['main']} ner
                    WHERE
                        1 AND ner.status > 0 AND ner.flag = 1 AND DATE(ner.since) <= DATE('{$time}')
                    GROUP BY
                        ner.from_currency,
                        ner.to_currency,
                        ner.since
                    ORDER BY
                        ner.created_at DESC, ner.since DESC 
                ) AS latest_exr   
                ";
        $cond = "";
        $grp = "";
        $ord = "";
        $l = "";

        if( isset( $filters['id'] ) )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND latest_exr.id IN ('" .implode( "','", $filters['id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND latest_exr.id = %d ", $filters['id'] );
        }
        if( isset( $filters['not_id'] ) )
        {
            if( is_array( $filters['not_id'] ) )
                $cond.= "AND latest_exr.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND latest_exr.id != %d ", $filters['not_id'] );
        }
        if( isset( $filters['docno'] ) )
        {
            if( is_array( $filters['docno'] ) )
                $cond.= "AND latest_exr.docno NOT IN ('" .implode( "','", $filters['docno'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND latest_exr.docno = %s ", $filters['docno'] );
        }
        if( isset( $filters['from_currency'] ) )
        {
            if( is_array( $filters['from_currency'] ) && !empty( $filters['from_currency'] ) )
                $cond .=  " AND latest_exr.from_currency IN ('" .implode( "','", $filters['from_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND latest_exr.from_currency = %s ", $filters['from_currency'] );
        }
        if( isset( $filters['to_currency'] ) )
        {
            if( is_array( $filters['to_currency'] ) && !empty( $filters['to_currency'] ))
                $cond .=  " AND latest_exr.to_currency IN ('" .implode( "','", $filters['to_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND latest_exr.to_currency = %s ", $filters['to_currency'] );
        }
        
        $group = ["latest_exr.from_currency" , "latest_exr.to_currency"];
        //group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

        //order
        $corder = array();
        if( empty( $order ) )
        {
            $order = ['latest_exr.from_currency' => 'ASC', 'latest_exr.to_currency' => 'ASC'];
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
        SELECT
            er.docno,
            er.from_currency,
            er.to_currency,
            er.base,
            er.rate,
            er.desc,
            max_date
        FROM
            wp_stmm_wcwh_exchange_rate t
        INNER JOIN(
            SELECT ner.docno,
                ner.from_currency,
                ner.to_currency,
                ner.base,
                ner.rate,
                ner.desc,
                ner.status,
                ner.flag,
                MAX(CAST(ner.since AS DATE)) AS max_date
            FROM
                wp_stmm_wcwh_exchange_rate b
            WHERE
                ner.status > 0 AND ner.flag = 1
            GROUP BY
                ner.from_currency,
                ner.to_currency
        ) a
        ON
            a.from_currency = er.from_currency AND a.to_currency = er.to_currency AND a.max_date = er.since
        WHERE
            1
        LIMIT 0, 100;
  
    public function get_latest_exchange_rate( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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

        $dbname = "";
        
        $field = " er.id, er.docno, er.from_currency, er.to_currency, er.base, er.rate, er.desc, max_date ";
        $table = "{$dbname}{$this->tables['main']} er ";
        $cond = "";
        $grp = "";
        $ord = "";
        $l = ""; 

        $field2 = "SELECT ner.id, ner.docno, ner.from_currency, ner.to_currency, ner.base, ner.rate, ner.desc, ner.status, ner.flag, MAX(CAST(ner.since AS DATE)) AS max_date";
        $cond2 = $wpdb->prepare( " ner.status > %d AND ner.flag = %d ", 0, 1 );
        $grp2 = "ner.from_currency, ner.to_currency";

        $table .= "INNER JOIN 
                    ( 
                        {$field2} 
                        FROM {$dbname}{$this->tables['main']} ner 
                        WHERE {$cond2} 
                        GROUP BY {$grp2} 
                    ) subquery_er 
                    ON subquery_er.from_currency = er.from_currency AND subquery_er.to_currency = er.to_currency AND subquery_er.max_date = er.since ";
        
        if( isset( $filters['id'] ) )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND er.id IN ('" .implode( "','", $filters['id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND er.id = %d ", $filters['id'] );
        }
        if( isset( $filters['not_id'] ) )
        {
            if( is_array( $filters['not_id'] ) )
                $cond.= "AND er.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND er.id != %d ", $filters['not_id'] );
        }
        if( isset( $filters['docno'] ) )
        {
            if( is_array( $filters['docno'] ) )
                $cond.= "AND er.docno NOT IN ('" .implode( "','", $filters['docno'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND er.docno = %s ", $filters['docno'] );
        }
        if( isset( $filters['from_currency'] ) )
        {
            if( is_array( $filters['from_currency'] ) && !empty( $filters['from_currency'] ) )
                $cond .=  " AND er.from_currency IN ('" .implode( "','", $filters['from_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND er.from_currency = %s ", $filters['from_currency'] );
        }
        if( isset( $filters['to_currency'] ) )
        {
            if( is_array( $filters['to_currency'] ) && !empty( $filters['to_currency'] ))
                $cond .=  " AND er.to_currency IN ('" .implode( "','", $filters['to_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND er.to_currency = %s ", $filters['to_currency'] );
        }
        if( isset( $filters['rate'] ) )
        {
            $cond.= $wpdb->prepare( "AND er.rate = %s ", $filters['rate'] );
        }
       if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND er.since >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND er.since <= %s ", $filters['to_date'] );
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
                $cd[] = "er.docno LIKE '%".$kw."%' ";
                $cd[] = "er.from_currency LIKE '%".$kw."%' ";
                $cd[] = "er.to_currency LIKE '%".$kw."%' ";
                $cd[] = "er.rate LIKE '%".$kw."%' ";
                $cd[] = "er.since LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

            unset( $filters['status'] );
        }
        
        //group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

        //order
        $corder = array();
        if( empty( $order ) )
        {
            $order = [ 'er.from_currency' => 'ASC', 'er.to_currency' => 'ASC' ];
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
        
        echo $sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
        $results = $wpdb->get_results( $sql , ARRAY_A );

        if( $single && count( $results ) > 0 )
        {
            $results = $results[0];
        }
        
        return $results;
    }  
  
    public function get_export_latest_data( $filters = array() )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();
              
        if( $filters )
        {
            foreach( $filters as $key => $value )
            {
                if( is_numeric( $value ) ) continue;
                if( $value == "" || $value === null ) unset( $filters[$key] );
                if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
            }
        }
        
        $dbname = "";
        
        $field = " er.docno, er.from_currency, er.to_currency, er.base, er.rate, er.desc, max_date ";
        $table = "{$dbname}{$this->tables['main']} er ";
        $cond = "";
        $grp = "";
        $ord = "";
        $l = ""; 

        $field2 = "SELECT ner.id, ner.docno, ner.from_currency, ner.to_currency, ner.base, ner.rate, ner.desc, ner.status, ner.flag, MAX(CAST(ner.since AS DATE)) AS max_date";
        $cond2 = $wpdb->prepare( " ner.status > %d AND ner.flag = %d ", 0, 1 );
        $grp2 = "ner.from_currency, ner.to_currency";

        $table .= "INNER JOIN 
                    ( 
                        {$field2} 
                        FROM {$dbname}{$this->tables['main']} ner 
                        WHERE {$cond2} 
                        GROUP BY {$grp2} 
                    ) subquery_er 
                    ON subquery_er.from_currency = er.from_currency AND subquery_er.to_currency = er.to_currency AND subquery_er.max_date = er.since ";
        
        if( isset( $filters['id'] ) )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND er.id IN ('" .implode( "','", $filters['id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND er.id = %d ", $filters['id'] );
        }
        if( isset( $filters['not_id'] ) )
        {
            if( is_array( $filters['not_id'] ) )
                $cond.= "AND er.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND er.id != %d ", $filters['not_id'] );
        }
        if( isset( $filters['docno'] ) )
        {
            if( is_array( $filters['docno'] ) )
                $cond.= "AND er.docno NOT IN ('" .implode( "','", $filters['docno'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND er.docno = %s ", $filters['docno'] );
        }
        if( isset( $filters['from_currency'] ) )
        {
            if( is_array( $filters['from_currency'] ) && !empty( $filters['from_currency'] ) )
                $cond .=  " AND er.from_currency IN ('" .implode( "','", $filters['from_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND er.from_currency = %s ", $filters['from_currency'] );
        }
        if( isset( $filters['to_currency'] ) )
        {
            if( is_array( $filters['to_currency'] ) && !empty( $filters['to_currency'] ))
                $cond .=  " AND er.to_currency IN ('" .implode( "','", $filters['to_currency'] ). "') ";
            else
                $cond .= $wpdb->prepare( " AND er.to_currency = %s ", $filters['to_currency'] );
        }
        if( isset( $filters['since'] ) )
        {
            $cond.= $wpdb->prepare( "AND er.since = %s ", $filters['since'] );
        }
        
        //group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

        //order
        $corder = array();
        if( empty( $order ) )
        {
            $order = [ 'er.from_currency' => 'ASC', 'er.to_currency' => 'ASC' ];
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
      */
}

}