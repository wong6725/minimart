<?php
if ( !defined( "ABSPATH" ) ) exit;

if ( !class_exists( "WCWH_AccPeriod_Class" ) ) include_once( WCWH_DIR . "/includes/classes/acc-period.php" ); 

if ( !class_exists( "WCWH_StockMovementWA_Class" ) )
{

class WCWH_StockMovementWA_Class extends WCWH_CRUD_Controller
{
    protected $tables = array();

    public $Notices;
    public $className = "StockMovementWA";

    public $Logic;

    protected $dbname = "";

    public $exclude_doc = [
        'purchase_request', 
        'purchase_order', 
        'sale_order',
        'transfer_order',
        'account_period', 
    ];

    protected $temp_stock_movement;

    public function __construct( $db_wpdb = array() )
    {
        parent::__construct();

        if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

        $this->Notices = new WCWH_Notices();

        $this->set_db_tables();

        $this->Logic = new WCWH_AccPeriod_Class( $this->db_wpdb );
    }

    public function set_db_tables()
    {
        global $wcwh, $wpdb;
        $prefix = $this->get_prefix();

        $this->tables = array(
            "stock_movement"    => $prefix."stock_movement_wa",
            "fifo_movement"    => $prefix."stock_movement",
            "margining_sales"   => $prefix."margining_sales",

            "document"      => $prefix."document",
            "document_items"=> $prefix."document_items",
            "document_meta" => $prefix."document_meta",

            "transaction"           => $prefix."transaction",
            "transaction_items"     => $prefix."transaction_items",
            "transaction_meta"      => $prefix."transaction_meta",
            "transaction_out_ref"   => $prefix."transaction_out_ref",
            "transaction_conversion"=> $prefix."transaction_conversion",

            "items"         => $prefix."items",
            "itemsmeta"     => $prefix."itemsmeta",
            "item_group"    => $prefix."item_group",
            "uom"           => $prefix."uom",
            "reprocess_item"=> $prefix."reprocess_item",
            "item_converse" => $prefix."item_converse",

            "category"      => $wpdb->prefix."terms",
            "category_tree" => $prefix."item_category_tree",
            
            "status"        => $prefix."status",

            "order_items"   => $wpdb->prefix."woocommerce_order_items",
            "order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

            "margining"     => $prefix."margining",
            "margining_sect"=> $prefix."margining_sect",
            "margining_det" => $prefix."margining_det",
            "margining_sales"   => $prefix."margining_sales",

            "client"        => $prefix."client",
            "clientmeta"    => $prefix."clientmeta",

            "temp_so"       => "temp_sales_order",
            "temp_st"       => "temp_sales_total",
        );

        $this->set_temp_stock_movement( 'temp_stock_movement' );
        $this->tables['temp_sm'] = $this->get_temp_stock_movement();
    }

    public function set_temp_stock_movement( $tbl_name = '' )
    {
        $this->temp_stock_movement = $tbl_name;
    }

    public function get_temp_stock_movement()
    {
        return $this->temp_stock_movement;
    }

    public function set_dbname( $tbl_name = '' )
    {
        $this->dbname = $tbl_name;
    }

    public function get_dbname()
    {
        return $this->dbname;
    }

    public function get_latest_stock_movement_month( $wh, $strg_id )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();

        $dbname = !empty( $this->dbname )? $this->dbname : "";

        $fld = "DISTINCT a.month ";

        $cond = $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $wh, $strg_id );

        $ord = "ORDER BY a.month DESC LIMIT 0,1 ";

        $sql = "SELECT {$fld} FROM {$dbname}{$this->tables['stock_movement']} a WHERE 1 {$cond} {$ord} ";

        return $wpdb->get_var( $sql );
    }

    public function get_latest_account_period( $wh )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();

        $dbname = !empty( $this->dbname )? $this->dbname : "";

        $fld = "a.*, DATE_FORMAT( a.doc_date, '%Y-%m' ) AS month ";

        $cond = $wpdb->prepare( "AND a.doc_type = %s AND a.warehouse_id = %s ", 'account_period', $wh );
        $cond.= $wpdb->prepare( "AND a.status = %d AND a.flag > %d ", 10, 0 );

        $ord = "ORDER BY a.doc_date DESC LIMIT 0,1 ";

        $sql = "SELECT {$fld} FROM {$dbname}{$this->tables['document']} a WHERE 1 {$cond} {$ord} ";

        return $wpdb->get_row( $sql , ARRAY_A );
    }

    public function get_earliest_operation( $wh = '' )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();

        $dbname = !empty( $this->dbname )? $this->dbname : "";

        $fld = "a.*, DATE_FORMAT( a.post_date, '%Y-%m' ) AS month ";

        $tbl = "{$dbname}{$this->tables['document']} a ";

        $cond = $wpdb->prepare( "AND a.status >= %d AND a.flag > %d ", 6, 0 );
        $cond.= "AND DATE_FORMAT( a.post_date, '%Y-%m' ) != '0000-00' ";
        if( $this->exclude_doc ) $cond.= "AND a.doc_type NOT IN ( '".implode( "', '", $this->exclude_doc )."' ) ";

        if( !empty( $wh ) ) $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $wh );
        
        $ord = "ORDER BY a.post_date ASC LIMIT 0,1 ";
        
        $sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$ord} ";

        return $wpdb->get_row( $sql , ARRAY_A );
    }

    public function get_previous_closing( $wh, $date )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();

        $dbname = !empty( $this->dbname )? $this->dbname : "";

        $fld = "a.*, DATE_FORMAT( a.doc_date, '%Y-%m' ) AS month ";

        $cond = $wpdb->prepare( "AND a.doc_type = %s AND a.warehouse_id = %s ", 'account_period', $wh );
        $cond.= $wpdb->prepare( "AND a.doc_date < %s ", $date );
        
        $ord = "ORDER BY a.doc_date DESC LIMIT 0,1 ";
        
        $sql = "SELECT {$fld} FROM {$dbname}{$this->tables['document']} a WHERE 1 {$cond} {$ord} ";

        return $wpdb->get_row( $sql , ARRAY_A );
    }
    
    public function stock_movement_handler( $wh = '', $id = 0, $doc = [] )
    {
        if( ! $wh || ! $id ) return false;

        if( empty( $doc ) && $id )
        {
            $doc = $this->Logic->get_header( [ 'doc_id'=>$id ], [], true, [] );
        }
        if( ! $doc ) return false;

        $succ = true;
        $curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
        $strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$wh, 'doc_type'=>'inventory' ] );

        if( ! empty( $doc ) && ! empty( $curr_wh ) && $doc['warehouse_id'] != $curr_wh['code'] ) return $succ;

        //get last or final closed account period
        $latest_period = $this->get_latest_account_period( $wh );
        if( ! $latest_period ) return false;

        //get previous closing doc
        $prev_closing = $this->get_previous_closing( $wh, $doc['doc_date'] );

        //get earliest operation date
        $oper_begin = $this->get_earliest_operation( $wh );
        if( $oper_begin )
            $begin_month = date( 'Y-m', strtotime( $oper_begin['doc_date'] ) );
        else
            $begin_month = date( 'Y-m', strtotime( ( $this->setting['begin_date'] )? $this->setting['begin_date'] : $this->refs['starting'] ) );

        //begin--
        @set_time_limit(900);

        $from_month = date( 'Y-m', strtotime( $begin_month ) );
        $to_month = date( 'Y-m', strtotime( $latest_period['month'] ) );

        if( strtotime( $from_month ) > strtotime( $to_month ) ) $from_month = $to_month;

        if( $prev_closing ) $from_month = date( 'Y-m', strtotime( $prev_closing['month']." +1 month" ) );

        $month = $from_month;
        while( $month !== date( 'Y-m', strtotime( $to_month." +1 month" ) ) )
        {
            $succ = $this->margining_sales_handling( $month, $wh );

            $succ = $this->stock_movement_handling( $month, $wh, $strg_id );
            if( ! $succ ) break;

            $month = date( 'Y-m', strtotime( $month." +1 month" ) );
        }

        return $succ;
    }

        public function stock_movement_handling( $month = '', $wh = '', $strg_id = 0 )
        {
            if( ! $month || ! $wh || ! $strg_id ) return false;

            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";
            
            $filters = [];
            $filters['from_date'] = date( 'Y-m-1 00:00:00', strtotime( $month ) );
            $filters['to_date'] = date( 'Y-m-t 23:59:59', strtotime( $month ) );
            $filters['month'] = date( 'Y-m', strtotime( $month." -1 month" ) );     //prev_month
            $filters['wh'] = $wh;
            $filters['strg_id'] = $strg_id;

            //-------------------------------------------------------------------------------------
            //Deletion
            $cond = $wpdb->prepare( "AND warehouse_id = %s AND strg_id = %s AND month = %s ", $wh, $strg_id, $month );
            $delete = "DELETE FROM {$this->tables['stock_movement']} WHERE 1 {$cond} ; ";
            $result = $wpdb->query( $delete );
            if( $result === false ) return false;

            //-------------------------------------------------------------------------------------
            //SQL for all inventory transaction

            $union = [];
            $union[] = $this->get_goods_receipt( $filters );
            $union[] = $this->get_reprocess( $filters );
            $union[] = $this->get_transfer_item( $filters );
            $union[] = $this->get_do_revise( $filters );
            $union[] = $this->get_sale_delivery_order( $filters );
            $union[] = $this->get_transfer_delivery_order( $filters );
            $union[] = $this->get_good_issue( $filters );
            $union[] = $this->get_good_return( $filters );
            $union[] = $this->get_pos( $filters );
            $union[] = $this->get_pos_transact( $filters );
            $union[] = $this->get_adjustment( $filters );
            $union[] = $this->get_opening( $filters );

            $union[] = $this->get_purchase_debit_credit( $filters );
            $union[] = $this->get_sale_debit_credit( $filters );

            $fld = "ic.base_id AS product_id 
                , SUM( IFNULL(a.op_qty,0) * IFNULL(ic.base_unit,1) ) AS op_qty, SUM( IFNULL(a.op_mtr,0) ) AS op_mtr, SUM( IFNULL(a.op_amt,0) ) AS op_amt 
                , SUM( IFNULL(a.qty,0) * IFNULL(ic.base_unit,1) ) AS qty, SUM( IFNULL(a.mtr,0) ) AS mtr, SUM( IFNULL(a.amt,0) ) AS amt 
                , SUM( IFNULL(a.gr_qty,0) * IFNULL(ic.base_unit,1) ) AS gr_qty, SUM( IFNULL(a.gr_mtr,0) ) AS gr_mtr, SUM( IFNULL(a.gr_amt,0) ) AS gr_amt
                , SUM( IFNULL(a.rp_qty,0) * IFNULL(ic.base_unit,1) ) AS rp_qty, SUM( IFNULL(a.rp_mtr,0) ) AS rp_mtr, SUM( IFNULL(a.rp_amt,0) ) AS rp_amt
                , SUM( IFNULL(a.ti_qty,0) * IFNULL(ic.base_unit,1) ) AS ti_qty, SUM( IFNULL(a.ti_mtr,0) ) AS ti_mtr, SUM( IFNULL(a.ti_amt,0) ) AS ti_amt
                , SUM( IFNULL(a.dr_qty,0) * IFNULL(ic.base_unit,1) ) AS dr_qty, SUM( IFNULL(a.dr_mtr,0) ) AS dr_mtr, SUM( IFNULL(a.dr_amt,0) ) AS dr_amt
                , SUM( IFNULL(a.so_qty,0) * IFNULL(ic.base_unit,1) ) AS so_qty, SUM( IFNULL(a.so_mtr,0) ) AS so_mtr, SUM( IFNULL(a.so_amt,0) ) AS so_amt, SUM( IFNULL(a.so_sale,0) ) AS so_sale
                , SUM( IFNULL(a.to_qty,0) * IFNULL(ic.base_unit,1) ) AS to_qty, SUM( IFNULL(a.to_mtr,0) ) AS to_mtr, SUM( IFNULL(a.to_amt,0) ) AS to_amt
                , SUM( IFNULL(a.gi_qty,0) * IFNULL(ic.base_unit,1) ) AS gi_qty, SUM( IFNULL(a.gi_mtr,0) ) AS gi_mtr, SUM( IFNULL(a.gi_amt,0) ) AS gi_amt
                , SUM( IFNULL(a.gt_qty,0) * IFNULL(ic.base_unit,1) ) AS gt_qty, SUM( IFNULL(a.gt_mtr,0) ) AS gt_mtr, SUM( IFNULL(a.gt_amt,0) ) AS gt_amt
                , SUM( IFNULL(a.pos_qty,0) * IFNULL(ic.base_unit,1) ) AS pos_qty, SUM( IFNULL(a.pos_uom_qty,0) * IFNULL(ic.base_unit,1) ) AS pos_uom_qty, SUM( IFNULL(a.pos_mtr,0) ) AS pos_mtr, SUM( IFNULL(a.pos_sale,0) ) AS pos_sale
                , SUM( IFNULL(a.adj_qty,0) * IFNULL(ic.base_unit,1) ) AS adj_qty, SUM( IFNULL(a.adj_mtr,0) ) AS adj_mtr, SUM( IFNULL(a.adj_amt,0) ) AS adj_amt ";

            $tbl = "( ";
            if( $union ) $tbl.= "( ".implode( " ) UNION ALL ( ", $union )." )";
            $tbl.= ") a ";
            $tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.product_id ";
            
            $cond = "";
            $grp = "GROUP BY ic.base_id ";
            $ord = "ORDER BY ic.base_id ASC ";

            $dat_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

            //-------------------------------------------------------------------------------------
            //SQL for stock movement calculation

$fld = "'{$wh}' AS warehouse_id, '{$strg_id}' AS strg_id, '{$month}' AS month, a.product_id
    , @op_qty:= IFNULL(a.op_qty,0) AS op_qty, @op_mtr:= IFNULL(a.op_mtr,0) AS op_mtr, @op_amt:= IFNULL(a.op_amt,0) AS op_amt
    , @gr_qty:= IFNULL(a.gr_qty,0) AS gr_qty, @gr_mtr:= IFNULL(a.gr_mtr,0) AS gr_mtr, @gr_amt:= IFNULL(a.gr_amt,0) AS gr_amt
    , @rp_qty:= IFNULL(a.rp_qty,0) AS rp_qty, @rp_mtr:= IFNULL(a.rp_mtr,0) AS rp_mtr, @rp_amt:= IFNULL(a.rp_amt,0) AS rp_amt
    , @ti_qty:= IFNULL(a.ti_qty,0) AS ti_qty, @ti_mtr:= IFNULL(a.ti_mtr,0) AS ti_mtr, @ti_amt:= IFNULL(a.ti_amt,0) AS ti_amt
    , @dr_qty:= IFNULL(a.dr_qty,0) AS dr_qty, @dr_mtr:= IFNULL(a.dr_mtr,0) AS dr_mtr, @dr_amt:= IFNULL(a.dr_amt,0) AS dr_amt
    , @so_qty:= IFNULL(a.so_qty,0) AS so_qty, @so_mtr:= IFNULL(a.so_mtr,0) AS so_mtr, @so_amt:= IFNULL(a.so_amt,0) AS so_amt
    , @so_sale:= IFNULL(a.so_sale,0) AS so_sale
    , @to_qty:= IFNULL(a.to_qty,0) AS to_qty, @to_mtr:= IFNULL(a.to_mtr,0) AS to_mtr, @to_amt:= IFNULL(a.to_amt,0) AS to_amt
    , @gi_qty:= IFNULL(a.gi_qty,0) AS gi_qty, @gi_mtr:= IFNULL(a.gi_mtr,0) AS gi_mtr, @gi_amt:= IFNULL(a.gi_amt,0) AS gi_amt
    , @gt_qty:= IFNULL(a.gt_qty,0) AS gt_qty, @gt_mtr:= IFNULL(a.gt_mtr,0) AS gt_mtr, @gt_amt:= IFNULL(a.gt_amt,0) AS gt_amt
    , @adj_qty:= IFNULL(a.adj_qty,0) AS adj_qty, @adj_mtr:= IFNULL(a.adj_mtr,0) AS adj_mtr, @adj_amt:= IFNULL(a.adj_amt,0) AS adj_amt
    , @qty:= IFNULL(a.op_qty,0)+@gr_qty+@rp_qty+@ti_qty+@dr_qty-@so_qty-@to_qty-@gi_qty-@gt_qty+@adj_qty AS qty
    , @mtr:= IFNULL(a.op_mtr,0)+@gr_mtr+@rp_mtr+@ti_mtr+@dr_mtr-@so_mtr-@to_mtr-@gi_mtr-@gt_mtr+@adj_mtr AS mtr
    , @amt:= IFNULL(a.op_amt,0)+@gr_amt+@rp_amt+@ti_amt+@dr_amt-@so_amt-@to_amt-@gi_amt-@gt_amt+@adj_amt AS amt
    , @pos_qty:= IFNULL(a.pos_qty,0) AS pos_qty, @pos_uom_qty:= IFNULL(a.pos_uom_qty,0) AS pos_uom_qty, @pos_mtr:= IFNULL(a.pos_mtr,0) AS pos_mtr  
    , @pos_amt:= IFNULL( IF( @qty>@pos_uom_qty, (@amt/@qty) * @pos_uom_qty, @amt ), 0 ) AS pos_amt
    , @pos_sale:= IFNULL(a.pos_sale,0) AS pos_sale
    , @op_qty+@gr_qty+@rp_qty+@ti_qty+@dr_qty-@so_qty-@to_qty-@gi_qty-@gt_qty-@pos_uom_qty+@adj_qty AS closing_qty
    , @op_mtr+@gr_mtr+@rp_mtr+@ti_mtr+@dr_mtr-@so_mtr-@to_mtr-@gi_mtr-@gt_mtr-@pos_mtr+@adj_mtr AS closing_mtr
    , IF( @op_qty+@gr_qty+@rp_qty+@ti_qty+@dr_qty-@so_qty-@to_qty-@gi_qty-@gt_qty-@pos_uom_qty+@adj_qty = 0 AND 
            ABS( IF(@op_amt != 0,@op_amt,@op_amt)+@gr_amt+@rp_amt+@ti_amt+@dr_amt-@so_amt-@to_amt-@gi_amt-@gt_amt-IFNULL(@pos_amt,0)+@adj_amt ) < 0.5
        , 0, IF(@op_amt != 0,@op_amt,@op_amt)+@gr_amt+@rp_amt+@ti_amt+@dr_amt-@so_amt-@to_amt-@gi_amt-@gt_amt-IFNULL(@pos_amt,0)+@adj_amt ) AS closing_amt ";

            $tbl = "( {$dat_sql} ) a ";

            $cond = "";
            $grp = "";
            $ord = "ORDER BY product_id ASC ";

            $sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

            //-------------------------------------------------------------------------------------
            //Query Insertion

            $fld = "warehouse_id, strg_id, month, product_id
            , op_qty, op_mtr, op_amt, gr_qty, gr_mtr, gr_amt, rp_qty, rp_mtr, rp_amt, ti_qty, ti_mtr, ti_amt, dr_qty, dr_mtr, dr_amt
            , so_qty, so_mtr, so_amt, so_sale, to_qty, to_mtr, to_amt, gi_qty, gi_mtr, gi_amt, gt_qty, gt_mtr, gt_amt
            , adj_qty, adj_mtr, adj_amt, qty, mtr, amt, pos_qty, pos_uom_qty, pos_mtr, pos_amt, pos_sale
            , closing_qty, closing_mtr, closing_amt ";

            $insert = "INSERT INTO {$this->tables['stock_movement']} ( {$fld} ) {$sql} ";
            $result = $wpdb->query( $insert );
            if( $result === false ) return false;

            return true;
        }

        public function get_latest_fifo_movement_month( $wh, $strg_id )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = "DISTINCT a.month ";

            $cond = $wpdb->prepare( "AND a.warehouse_id = %s AND a.strg_id = %s ", $wh, $strg_id );

            $ord = "ORDER BY a.month DESC LIMIT 0,1 ";

            $sql = "SELECT {$fld} FROM {$dbname}{$this->tables['fifo_movement']} a WHERE 1 {$cond} {$ord} ";

            return $wpdb->get_var( $sql );
        }

        public function get_old_opening( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            //Union previous month data
            $field = " b.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt
                , 0 AS qty, 0 AS mtr, 0 AS amt
                , SUM( b.closing_qty ) AS df_qty, SUM( b.closing_mtr ) AS df_mtr
                , IF( SUM( b.closing_qty ) = 0 AND ABS( SUM( b.closing_amt ) ) < 0.5, 0, SUM( b.closing_amt ) ) AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt 
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt 
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt 
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj 
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt 
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt 
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt 
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale 
                , 0 AS adj_qty , 0 AS adj_mtr , 0 AS adj_amt ";

            $table = "{$dbname}{$this->tables['fifo_movement']} b ";

            if( $args['table'] )
            {
                $table = "{$args['table']} b ";
            }

            $cond = "";//"AND ( b.closing_amt != 0 OR b.amt != 0 ) ";

            if( isset( $filters['month'] ) )
            {
                $cond.= $wpdb->prepare( "AND b.month = %s ", $filters['month'] );
            }
            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND b.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['strg_id'] ) )
            {
                $cond.= $wpdb->prepare( "AND b.strg_id = %s ", $filters['strg_id'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }

        public function get_opening( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            //Union previous month data
            $field = " b.product_id {$fld}
                , SUM( b.closing_qty ) AS op_qty, SUM( b.closing_mtr ) AS op_mtr
                , IF( SUM( b.closing_qty ) = 0 AND ABS( SUM( b.closing_amt ) ) < 0.5, 0, SUM( b.closing_amt ) ) AS op_amt
                , SUM( b.qty ) AS qty, SUM( b.mtr ) AS mtr, SUM( b.amt ) AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt 
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt 
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt 
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj 
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt 
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt 
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt 
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale 
                , 0 AS adj_qty , 0 AS adj_mtr , 0 AS adj_amt ";

            $table = "{$dbname}{$this->tables['stock_movement']} b ";

            if( $args['table'] )
            {
                $table = "{$args['table']} b ";
            }

            $cond = "";//"AND ( b.closing_amt != 0 OR b.amt != 0 ) ";

            if( isset( $filters['month'] ) )
            {
                $cond.= $wpdb->prepare( "AND b.month = %s ", $filters['month'] );
            }
            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND b.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['strg_id'] ) )
            {
                $cond.= $wpdb->prepare( "AND b.strg_id = %s ", $filters['strg_id'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }

        /* Purchase Debit Note Credit Note */
        public function get_purchase_debit_credit( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 
            //2-;1+
            $field = " d.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty
                , 0 AS gr_mtr
                , SUM( ROUND( IF( ti.plus_sign = '-', ti.weighted_total * -1, ti.weighted_total ), 2 ) ) AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'note_action' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'inventory_action' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
             $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $cond = $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );
            $cond.= "AND h.doc_type IN( 'purchase_debit_note', 'purchase_credit_note' ) ";
            $cond.= "AND ( mb.meta_value IS NULL OR mb.meta_value <= 0 OR mb.meta_value = '' ) ";

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $args['group'] )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }

        /*  Goods Receipt
        SELECT DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, ti.product_id
        , ROUND( SUM( ti.bqty ), 2 ) AS gr_qty, ROUND( SUM( ti.bunit ), 3 ) AS gr_mtr, ROUND( SUM( ti.total_price ), 2 ) AS gr_amt 
        , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
        , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
        , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale
        , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
        , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
        , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
        , 0 AS pos_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
        , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt
        FROM wp_stmm_wcwh_document h 
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0 
        LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = d.item_id AND ma.meta_key = 'uprice' 
        LEFT JOIN wp_stmm_wcwh_document_meta mb ON mb.doc_id = h.doc_id AND mb.item_id = d.item_id AND mb.meta_key = 'total_amount' 
        LEFT JOIN wp_stmm_wcwh_document_meta mc ON mc.doc_id = h.doc_id AND mc.item_id = d.item_id AND mc.meta_key = 'sunit' 
        LEFT JOIN wp_stmm_wcwh_transaction t ON t.doc_id = h.doc_id
        LEFT JOIN wp_stmm_wcwh_transaction_items ti ON ti.hid = t.hid AND ti.item_id = d.item_id 
        WHERE 1 AND h.doc_type = 'good_receive' AND h.status >= 6 
        AND t.status > 0 AND ti.status > 0
        AND h.post_date >= '2021-08-01 00:00:00' AND h.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id 
        */
        public function get_goods_receipt( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , SUM( ti.bqty ) AS gr_qty, SUM( ROUND( ti.bunit, 3 ) ) AS gr_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc_id' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'uprice' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'total_amount' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'sunit' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = ma.meta_value ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} pma ON pma.doc_id = ph.doc_id AND pma.item_id = 0 AND pma.meta_key = 'client_company_code' ";

            if( $filters['margining'] && !empty( $filters['margining_id'] ) )
            {
                $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , SUM( ti.bqty ) AS gr_qty, SUM( ROUND( ti.bunit, 3 ) ) AS gr_mtr
, IF( mg.id > 0, SUM( ROUND( 
    ROUND( 
        CASE 
        WHEN mg.round_type = 'ROUND' 
            THEN ROUND( ROUND( ROUND(ti.weighted_total/ti.bqty,5)*( 1+( IFNULL(mg.margin,0)/100 ) ), 5 ) / IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) ) * IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) 
        WHEN mg.round_type = 'CEIL' 
            THEN CEIL( ROUND( ROUND(ti.weighted_total/ti.bqty,5)*( 1+( IFNULL(mg.margin,0)/100 ) ), 5 ) / IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) ) * IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) 
        WHEN mg.round_type = 'FLOOR' 
            THEN FLOOR( ROUND( ROUND(ti.weighted_total/ti.bqty,5)*( 1+( IFNULL(mg.margin,0)/100 ) ), 5 ) / IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) ) * IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) 
        WHEN mg.round_type IS NULL OR mg.round_type = 'DEFAULT' 
            THEN ROUND( ROUND(ti.weighted_total/ti.bqty,5)*( 1+( IFNULL(mg.margin,0)/100 ) ), 5 ) 
        END
    , 5 ) 
* ti.bqty, 2 ) ), SUM( ROUND( ti.weighted_total, 2 ) ) ) AS gr_amt 
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";

                $subsql = $wpdb->prepare( "SELECT a.id 
                    FROM {$this->tables['margining']} a 
                    LEFT JOIN {$this->tables['margining_sect']} s ON s.mg_id = a.id AND s.status > 0
                    WHERE 1 AND a.status > 0 AND a.flag > 0 
                    AND a.wh_id = h.warehouse_id AND a.type = %s AND s.sub_section = %s 
                    AND a.since <= h.doc_date AND ( a.until >= h.doc_date OR a.until = '' ) 
                    ORDER BY a.effective DESC, a.since DESC, a.created_at DESC 
                    LIMIT 0,1 ", 'def', $filters['margining_id'] );

                //$table.= "LEFT JOIN {$this->tables['margining_det']} mgd ON mgd.id = ( {$subsql} ) ";
                $table.= "LEFT JOIN {$this->tables['margining']} mh ON mh.id = ( {$subsql} ) ";

                $subsql = "SELECT m.id
                    FROM {$this->tables['margining']} m 
                    LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
                    WHERE 1 AND m.id = mh.id AND m.inclusive = 'excl' AND md.client = pma.meta_value ";
                $table.= "LEFT JOIN {$this->tables['margining']} mx ON mx.id = ( {$subsql} ) ";

                $subsql = "SELECT m.id
                    FROM {$this->tables['margining']} m 
                    LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
                    WHERE 1 AND m.id = mh.id 
                    AND ( ( m.inclusive = 'incl' AND md.client = pma.meta_value ) OR ( m.inclusive = 'excl' AND ( m.id != mx.id OR mx.id IS NULL ) ) ) 
                    ORDER BY m.effective DESC, m.since DESC, m.created_at DESC 
                    LIMIT 0,1
                ";
                $table.= "LEFT JOIN {$this->tables['margining']} mg ON mg.id = ( {$subsql} ) ";
            }

            $cond = $wpdb->prepare( "AND h.doc_type = %s ", 'good_receive' );
            $cond.= $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*  Reprocess
        SELECT DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, ti.product_id
        , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
        , ROUND( SUM( ti.bqty ), 2 ) AS rp_qty, ROUND( SUM( ti.bunit ), 3 ) AS rp_mtr, ROUND( SUM( ti.total_price ), 2 ) AS rp_amt 
        , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
        , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale
        , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
        , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
        , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
        , 0 AS pos_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
        , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt
        FROM wp_stmm_wcwh_document h 
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
        LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = d.item_id AND ma.meta_key = 'uprice' 
        LEFT JOIN wp_stmm_wcwh_document_meta mb ON mb.doc_id = h.doc_id AND mb.item_id = d.item_id AND mb.meta_key = 'total_amount' 
        LEFT JOIN wp_stmm_wcwh_document_meta mc ON mc.doc_id = h.doc_id AND mc.item_id = d.item_id AND mc.meta_key = 'sunit' 
        LEFT JOIN wp_stmm_wcwh_transaction t ON t.doc_id = h.doc_id
        LEFT JOIN wp_stmm_wcwh_transaction_items ti ON ti.hid = t.hid AND ti.item_id = d.item_id 
        WHERE 1 AND h.doc_type = 'reprocess' AND h.status >= 6 
        AND t.status > 0 AND ti.status > 0
        AND h.post_date >= '2021-08-01 00:00:00' AND h.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id 
        */
        public function get_reprocess( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , SUM( ti.bqty ) AS rp_qty, SUM( ROUND( ti.bunit, 3 ) ) AS rp_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS rp_amt 
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = d.item_id AND ma.meta_key = 'uprice' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = d.item_id AND mb.meta_key = 'total_amount' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = d.item_id AND mc.meta_key = 'sunit' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $cond = $wpdb->prepare( "AND h.doc_type = %s ", 'reprocess' );
            $cond.= $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*  Transfer Item
        SELECT DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, ti.product_id
        , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
        , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
        , ROUND( SUM( ti.bqty ), 2 ) AS ti_qty, ROUND( SUM( ti.bunit ), 3 ) AS ti_mtr, ROUND( SUM( ti.total_price ), 2 ) AS ti_amt 
        , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale
        , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
        , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
        , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
        , 0 AS pos_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
        , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt
        FROM wp_stmm_wcwh_document h 
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
        LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = d.item_id AND ma.meta_key = 'uprice' 
        LEFT JOIN wp_stmm_wcwh_document_meta mb ON mb.doc_id = h.doc_id AND mb.item_id = d.item_id AND mb.meta_key = 'total_amount' 
        LEFT JOIN wp_stmm_wcwh_document_meta mc ON mc.doc_id = h.doc_id AND mc.item_id = d.item_id AND mc.meta_key = 'sunit' 
        LEFT JOIN wp_stmm_wcwh_transaction t ON t.doc_id = h.doc_id
        LEFT JOIN wp_stmm_wcwh_transaction_items ti ON ti.hid = t.hid AND ti.item_id = d.item_id 
        WHERE 1 AND h.doc_type = 'transfer_item' AND h.status >= 6 
        AND t.status > 0 AND ti.status > 0
        AND h.post_date >= '2021-08-01 00:00:00' AND h.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id 
        */
        public function get_transfer_item( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , SUM( ti.bqty ) AS ti_qty, SUM( ROUND( ti.bunit, 3 ) ) AS ti_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS ti_amt 
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = d.item_id AND ma.meta_key = 'uprice' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = d.item_id AND mb.meta_key = 'total_amount' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = d.item_id AND mc.meta_key = 'sunit' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $cond = $wpdb->prepare( "AND h.doc_type = %s ", 'transfer_item' );
            $cond.= $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*
         *  Delivery Order Revise
        */
        public function get_do_revise( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt 
                , SUM( ti.bqty ) AS dr_qty, SUM( ROUND( ti.bunit, 3 ) ) AS dr_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = d.item_id AND ma.meta_key = 'uprice' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = d.item_id AND mb.meta_key = 'total_amount' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = d.item_id AND mc.meta_key = 'sunit' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $cond = $wpdb->prepare( "AND h.doc_type = %s ", 'do_revise' );
            $cond.= $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }

        /* Sales Debit Note Credit Note */
        public function get_sale_debit_credit( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 
            //2+;1-
            $field = " d.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , SUM( IF( ma.meta_value = 2, d.bqty, d.bqty * -1 ) ) AS so_qty
                , SUM( ROUND( IF( ma.meta_value = 2, d.bunit, d.bunit * -1 ), 3 ) ) AS so_mtr, 0 AS so_amt
                , SUM( ROUND( IF( ma.meta_value = 2, dma.meta_value, dma.meta_value * -1 ), 2 ) ) AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'note_action' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'amount' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'line_total' ";

            $cond = $wpdb->prepare( "AND h.status >= %d ", 6 );
            $cond.= "AND h.doc_type IN( 'sale_debit_note', 'sale_credit_note' ) ";

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $args['group'] )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }

        /*  DO by SO
        SELECT DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, ti.product_id
        , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
        , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
        , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
        , ROUND( SUM( ti.bqty ), 2 ) AS so_qty, ROUND( SUM( ti.bunit ), 3 ) AS so_mtr, ROUND( SUM( ti.total_cost ), 2 ) AS so_amt
        , ROUND( SUM( d.bqty * dmb.meta_value ), 2 ) AS so_sale
        , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
        , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
        , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
        , 0 AS pos_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
        , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt
        FROM wp_stmm_wcwh_document h 
        LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' 
        LEFT JOIN wp_stmm_wcwh_document_meta mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' 
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
        LEFT JOIN wp_stmm_wcwh_document_meta dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_cost' 
        LEFT JOIN wp_stmm_wcwh_transaction t ON t.doc_id = h.doc_id 
        LEFT JOIN wp_stmm_wcwh_transaction_items ti ON ti.hid = t.hid AND ti.item_id = d.item_id 
        WHERE 1 AND h.status >= 6 AND t.status > 0 AND ti.status > 0 
        AND ( ( h.doc_type = 'delivery_order' AND mb.meta_value = 'sale_order' ) OR 
            ( h.doc_type = 'good_issue' AND ma.meta_value = 'delivery_order' AND mb.meta_value = 'sale_order' ) ) 
        AND h.post_date >= '2021-08-01 00:00:00' AND h.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id 
        */
        public function get_sale_delivery_order( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , SUM( ti.bqty ) AS so_qty, SUM( ROUND( ti.bunit, 3 ) ) AS so_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS so_amt
                , SUM( ROUND( d.bqty * dmb.meta_value, 2 ) ) AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'ref_doc_id' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_cost' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            if( $filters['margining'] && !empty( $filters['margining_id'] ) )
            {
                if( !empty( $args['usage'] ) && $args['usage'] = 'stock_movement_report' )
                {
                    $field = " ti.product_id {$fld}
                    , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                    , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                    , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                    , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                    , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                    , SUM( ti.bqty ) AS so_qty, SUM( ROUND( ti.bunit, 3 ) ) AS so_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS so_amt
                    , SUM( ROUND( IFNULL(d.bqty * pi.final_sprice,0), 2 ) ) AS so_sale
                    , SUM( ROUND( IF( pia.id > 0, IFNULL(d.bqty * pia.final_sprice,0) - IFNULL(d.bqty * pi.final_sprice,0), 0 ), 2 ) ) AS so_adj
                    , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                    , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                    , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                    , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                    , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";

                    $table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = mc.meta_value ";
                    $table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pi ON pi.doc_id = ph.doc_id AND pi.product_id = d.product_id AND pi.warehouse_id = ph.warehouse_id AND pi.type = 'def' AND pi.status > 0 ";
                    $table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pia ON pia.doc_id = ph.doc_id AND pia.product_id = d.product_id AND pia.warehouse_id = ph.warehouse_id AND pia.type = 'adj' AND pia.status > 0 ";
                }
            }
            if( $args['fields'] ) $field = $args['fields'];

            $cond = $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );
            $cond.= "AND ( ( h.doc_type = 'delivery_order' AND ( mb.meta_value = 'sale_order' OR mb.meta_value IS NULL ) ) OR 
                ( h.doc_type = 'good_issue' AND ma.meta_value = 'delivery_order' AND ( mb.meta_value = 'sale_order' OR mb.meta_value IS NULL ) ) ) ";

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $args['group'] )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*  DO by TO
        SELECT DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, ti.product_id
        , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
        , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
        , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
        , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale
        , ROUND( SUM( ti.bqty ), 2 ) AS to_qty, ROUND( SUM( ti.bunit ), 3 ) AS to_mtr, ROUND( SUM( ti.total_cost ), 2 ) AS to_amt 
        , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
        , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
        , 0 AS pos_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
        , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt
        FROM wp_stmm_wcwh_document h 
        LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' 
        LEFT JOIN wp_stmm_wcwh_document_meta mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'ref_doc_type' 
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
        LEFT JOIN wp_stmm_wcwh_document_meta dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_cost' 
        LEFT JOIN wp_stmm_wcwh_transaction t ON t.doc_id = h.doc_id 
        LEFT JOIN wp_stmm_wcwh_transaction_items ti ON ti.hid = t.hid AND ti.item_id = d.item_id 
        WHERE 1 AND h.status >= 6 AND t.status > 0 AND ti.status > 0 
        AND ( h.doc_type = 'delivery_order' AND mb.meta_value = 'transfer_order' ) 
        AND h.post_date >= '2021-08-01 00:00:00' AND h.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id 
        */
        public function get_transfer_delivery_order( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , SUM( ti.bqty ) AS to_qty, SUM( ROUND( ti.bunit, 3 ) ) AS to_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS to_amt 
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND ma.item_id = 0 AND mb.meta_key = 'ref_doc_type' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_cost' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $cond = $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );
            $cond.= "AND ( h.doc_type = 'delivery_order' AND mb.meta_value = 'transfer_order' ) ";

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*  Goods Issue
        SELECT DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, IF( ti.product_id, ti.product_id, d.product_id ) AS product_id 
        , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
        , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
        , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
        , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale
        , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
        , ROUND( SUM( IF( ti.product_id, ti.bqty, d.bqty ) ), 2 ) AS gi_qty
        , ROUND( SUM( IF( ti.product_id, ti.bunit, IF( dma.meta_value > 0, dma.meta_value, d.bunit ) ) ), 3 ) AS gi_mtr 
        , ROUND( SUM( IF( ti.product_id, ti.total_cost, d.bqty * dmc.meta_value ) ), 2 ) AS gi_amt 
        , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
        , 0 AS pos_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
        , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt
        FROM wp_stmm_wcwh_document h 
        LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' 
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
        LEFT JOIN wp_stmm_wcwh_document_meta dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_cost' 
        LEFT JOIN wp_stmm_wcwh_transaction t ON t.doc_id = h.doc_id AND t.status > 0 
        LEFT JOIN wp_stmm_wcwh_transaction_items ti ON ti.hid = t.hid AND ti.item_id = d.item_id AND ti.status > 0 
        WHERE 1 AND h.status >= 6 
        AND ( ( h.doc_type = 'good_issue' AND ma.meta_value IN ( 'reprocess', 'own_use', 'block_stock', 'transfer_item', 'direct_consume' ) ) ) 
        AND h.post_date >= '2021-08-01 00:00:00' AND h.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id
        */
        public function get_good_issue( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " IF( ti.product_id, ti.product_id, d.product_id ) AS product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , SUM( ti.bqty ) AS gi_qty
                , SUM( ROUND( ti.bunit, 3 ) ) AS gi_mtr 
                , SUM( ROUND( ti.weighted_total, 2 ) ) AS gi_amt 
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type'  ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_cost' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id AND t.status > 0 ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id AND ti.status > 0 ";

            $cond = $wpdb->prepare( "AND h.status >= %d ", 6 ); 
            $cond.= "AND ( h.doc_type = 'good_issue' AND ma.meta_value IN ( 'reprocess', 'own_use', 'vending_machine', 'block_stock', 'transfer_item', 'other', 'returnable' ) ) ";

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*
        SELECT DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, ti.product_id
        , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
        , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
        , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
        , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale
        , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
        , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
        , ROUND( SUM( ti.bqty ), 2 ) AS gt_qty, ROUND( SUM( ti.bunit ), 3 ) AS gt_mtr, ROUND( SUM( ti.total_cost ), 2 ) AS gt_amt 
        , 0 AS pos_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
        , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt
        FROM wp_stmm_wcwh_document h 
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
        LEFT JOIN wp_stmm_wcwh_document_meta dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_cost' 
        LEFT JOIN wp_stmm_wcwh_transaction t ON t.doc_id = h.doc_id 
        LEFT JOIN wp_stmm_wcwh_transaction_items ti ON ti.hid = t.hid AND ti.item_id = d.item_id 
        WHERE 1 AND h.status >= 6 AND t.status > 0 AND ti.status > 0 
        AND ( h.doc_type = 'good_return' ) 
        AND h.post_date >= '2021-08-01 00:00:00' AND h.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id 
        */
        public function get_good_return( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , SUM( ti.bqty ) AS gt_qty, SUM( ROUND( ti.bunit, 3 ) ) AS gt_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS gt_amt 
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'ucost' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'total_cost' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $cond = $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );
            $cond.= $wpdb->prepare( "AND h.doc_type = %s ", 'good_return' );

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*  POS
        SELECT DATE_FORMAT( a.post_date, '%Y-%m' ) AS month, d.meta_value AS product_id
        , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
        , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
        , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
        , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale
        , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
        , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
        , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
        , ROUND( SUM( e.meta_value ), 2 ) AS pos_qty, ROUND( SUM( g.meta_value * e.meta_value ), 3 ) AS pos_mtr, 0 AS pos_amt, ROUND( SUM( f.meta_value ), 2 ) AS pos_sale 
        , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt
        FROM wp_stmm_posts a 
        LEFT JOIN wp_stmm_postmeta b ON b.post_id = a.ID AND b.meta_key = '_order_total' 
        LEFT JOIN wp_stmm_woocommerce_order_items c ON c.order_id = a.ID AND c.order_item_type = 'line_item' 
        LEFT JOIN wp_stmm_woocommerce_order_itemmeta d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' 
        LEFT JOIN wp_stmm_woocommerce_order_itemmeta e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' 
        LEFT JOIN wp_stmm_woocommerce_order_itemmeta f ON f.order_item_id = c.order_item_id AND f.meta_key = '_line_total' 
        LEFT JOIN wp_stmm_woocommerce_order_itemmeta g ON g.order_item_id = c.order_item_id AND g.meta_key = '_unit' 
        WHERE 1 AND a.post_type = 'shop_order' AND b.meta_value > 0 AND a.post_status IN( 'wc-processing', 'wc-completed' ) 
        AND a.post_date >= '2021-08-01 00:00:00' AND a.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id 
        */
        public function get_pos( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            if( $this->refs['metric'] )
            {
                foreach( $this->refs['metric'] AS $each )
                {
                    $each = strtoupper($each);
                    $met[] = "UPPER( i._uom_code ) = '{$each}' ";
                }

                $metric = "AND ( ".implode( "OR ", $met ).") ";
            }

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " d.meta_value AS product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , SUM( e.meta_value ) AS pos_qty
                , SUM( ROUND( IF( ia.meta_value > 0 {$metric}, e.meta_value, e.meta_value ), 2 ) ) AS pos_uom_qty
                , SUM( ROUND( g.meta_value * e.meta_value, 3 ) ) AS pos_mtr, 0 AS pos_amt
                , SUM( ROUND( f.meta_value, 2 ) ) AS pos_sale 
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$wpdb->posts} a ";
            $table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_line_total' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_unit' ";

            $table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.meta_value ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['itemsmeta']} ia ON ia.items_id = i.id AND ia.meta_key = 'inconsistent_unit' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['reprocess_item']} rep ON rep.items_id = i.id AND rep.status > 0 ";

            $cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value != %d ", 'shop_order', 0 );
            $cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";

            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            $pos_arc = apply_filters( 'wcwh_get_setting', '', '', $filters['seller'], 'wcwh_pos_arc_date' );
            if( $pos_arc )
            {
                $arc_dbname = ( $dbname )? str_replace( '.', '', $dbname )."_arc." : $wpdb->dbname."_arc.";

                $field = " d.meta_value AS product_id {$fld}
                    , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                    , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                    , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                    , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                    , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                    , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                    , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                    , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                    , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                    , SUM( e.meta_value ) AS pos_qty
                    , SUM( ROUND( IF( ia.meta_value > 0 {$metric}, e.meta_value, e.meta_value ), 2 ) ) AS pos_uom_qty
                    , SUM( ROUND( g.meta_value * e.meta_value, 3 ) ) AS pos_mtr, 0 AS pos_amt
                    , SUM( ROUND( f.meta_value, 2 ) ) AS pos_sale 
                    , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
                
                $table = "{$arc_dbname}{$wpdb->posts} a ";
                $table.= "LEFT JOIN {$arc_dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
                $table.= "LEFT JOIN {$arc_dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
                $table.= "LEFT JOIN {$arc_dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
                $table.= "LEFT JOIN {$arc_dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
                $table.= "LEFT JOIN {$arc_dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_line_total' ";
                $table.= "LEFT JOIN {$arc_dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_unit' ";

                $table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.meta_value ";
                $table.= "LEFT JOIN {$dbname}{$this->tables['itemsmeta']} ia ON ia.items_id = i.id AND ia.meta_key = 'inconsistent_unit' ";
                //$table.= "LEFT JOIN {$arc_dbname}{$this->tables['reprocess_item']} rep ON rep.items_id = i.id AND rep.status > 0 ";

                $cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value > %d ", 'shop_order', 0 );
                $cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";

                if( isset( $filters['from_date'] ) )
                {
                    $cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
                }
                if( isset( $filters['to_date'] ) )
                {
                    $cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
                }

                $grp = "GROUP BY product_id ";
                if( ! empty( $args['group'] ) )
                {
                    $grp = "GROUP BY ".implode( ", ", $group )." ";
                }

                $ord = "";
                if( ! empty( $args['order'] ) )
                {
                    foreach( $args['order'] as $order_by => $seq )
                    {
                        $o[] = "{$order_by} {$seq} ";
                    }
                    $ord = "ORDER BY ".implode( ", ", $o )." ";
                } 

                $query2 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

                $field = " a.product_id {$fld}
                    , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                    , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                    , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                    , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                    , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                    , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                    , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                    , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                    , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                    , SUM( a.pos_qty ) AS pos_qty, SUM( a.pos_uom_qty ) AS pos_uom_qty
                    , SUM( a.pos_mtr ) AS pos_mtr, 0 AS pos_amt, SUM( a.pos_sale ) AS pos_sale
                    , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
                
                $table = "( ( {$query1} ) UNION ALL ( {$query2} ) ) a ";

                $cond = "";

                $grp = "GROUP BY product_id ";
                if( ! empty( $args['group'] ) )
                {
                    $grp = "GROUP BY ".implode( ", ", $group )." ";
                }

                $ord = "";
                if( ! empty( $args['order'] ) )
                {
                    foreach( $args['order'] as $order_by => $seq )
                    {
                        $o[] = "{$order_by} {$seq} ";
                    }
                    $ord = "ORDER BY ".implode( ", ", $o )." ";
                } 

                $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
            }
            else
            {
                $query = $query1;
            }

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*
            pos_transactions
        */
        public function get_pos_transact( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , SUM( ti.bqty ) AS gr_qty, SUM( ROUND( ti.bunit, 3 ) ) AS gr_mtr, SUM( ROUND( ti.weighted_total, 2 ) ) AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt 
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , 0 AS adj_qty, 0 AS adj_mtr, 0 AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'ucost' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'total_cost' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $cond = $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );
            $cond.= $wpdb->prepare( "AND h.doc_type = %s ", 'pos_transactions' );

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            } 

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }
        /*  Adjustment
        SELECT DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, ti.product_id
        , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
        , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
        , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
        , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale
        , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
        , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
        , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
        , 0 AS pos_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
        , ROUND( SUM( IF( ti.plus_sign = '-', ti.bqty * -1, ti.bqty ) ), 2 ) AS adj_qty 
        , ROUND( SUM( IF( ti.plus_sign = '-', ti.bunit * -1, ti.bunit ) ), 3 ) AS adj_mtr 
        , ROUND( SUM( ti.total_price ) - SUM( ti.total_cost ), 2 ) AS adj_amt
        FROM wp_stmm_wcwh_document h 
        LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0 
        LEFT JOIN wp_stmm_wcwh_document_meta dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'plus_sign' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'adjust_qty' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'uprice' 
        LEFT JOIN wp_stmm_wcwh_document_meta dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_amount' 
        LEFT JOIN wp_stmm_wcwh_transaction t ON t.doc_id = h.doc_id
        LEFT JOIN wp_stmm_wcwh_transaction_items ti ON ti.hid = t.hid AND ti.item_id = d.item_id 
        WHERE 1 AND h.status >= 6 AND h.doc_type IN ( 'stock_adjust', 'stocktake' ) 
        AND t.status > 0 AND ti.status > 0 
        AND h.post_date >= '2021-08-01 00:00:00' AND h.post_date <= '2021-09-30 23:59:59' 
        GROUP BY month, product_id 
        */
        public function get_adjustment( $filters = [], $run = false, $args = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $fld = ( $args['field'] )? $args['field'] : "";//DATE_FORMAT( h.post_date, '%Y-%m' ) AS month, 

            $field = " ti.product_id {$fld}
                , 0 AS op_qty, 0 AS op_mtr, 0 AS op_amt, 0 AS qty, 0 AS mtr, 0 AS amt, 0 AS df_qty, 0 AS df_mtr, 0 AS df_amt
                , 0 AS gr_qty, 0 AS gr_mtr, 0 AS gr_amt
                , 0 AS rp_qty, 0 AS rp_mtr, 0 AS rp_amt
                , 0 AS ti_qty, 0 AS ti_mtr, 0 AS ti_amt
                , 0 AS dr_qty, 0 AS dr_mtr, 0 AS dr_amt 
                , 0 AS so_qty, 0 AS so_mtr, 0 AS so_amt, 0 AS so_sale, 0 AS so_adj
                , 0 AS to_qty, 0 AS to_mtr, 0 AS to_amt
                , 0 AS gi_qty, 0 AS gi_mtr, 0 AS gi_amt
                , 0 AS gt_qty, 0 AS gt_mtr, 0 AS gt_amt
                , 0 AS pos_qty, 0 AS pos_uom_qty, 0 AS pos_mtr, 0 AS pos_amt, 0 AS pos_sale
                , SUM( IF( ti.plus_sign = '-', ti.bqty * -1, ti.bqty ) ) AS adj_qty 
                , SUM( ROUND( IF( ti.plus_sign = '-', ti.bunit * -1, ti.bunit ), 3 ) ) AS adj_mtr 
                , SUM( ROUND( IF( ti.plus_sign = '-', ti.weighted_total * -1, ti.weighted_total ), 2 ) ) AS adj_amt ";
            
            $table = "{$dbname}{$this->tables['document']} h ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'plus_sign' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'adjust_qty' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'uprice' ";
            //$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_amount' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";

            $cond = $wpdb->prepare( "AND h.status >= %d AND t.status > %d AND ti.status > %d ", 6, 0, 0 );
            $cond.= "AND h.doc_type IN ( 'stock_adjust', 'stocktake' ) ";

            if( isset( $filters['wh'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['wh'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND h.post_date <= %s ", $filters['to_date'] );
            }

            $grp = "GROUP BY product_id ";
            if( ! empty( $args['group'] ) )
            {
                $grp = "GROUP BY ".implode( ", ", $group )." ";
            }

            $ord = "";
            if( ! empty( $args['order'] ) )
            {
                foreach( $args['order'] as $order_by => $seq )
                {
                    $o[] = "{$order_by} {$seq} ";
                }
                $ord = "ORDER BY ".implode( ", ", $o )." ";
            }

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
        }


    public function get_sales_date( $filters = [] )
    {
        global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();

        $dbname = !empty( $this->dbname )? $this->dbname : "";

        $field = "DATE_FORMAT( MIN(ph.doc_date), '%Y-%m-%d 00:00:00' ) AS from_date
        , DATE_FORMAT( MAX(ph.doc_date), '%Y-%m-%d 23:59:59' ) AS to_date ";
        
        $table = "{$dbname}{$this->tables['document']} h ";
        $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'base_doc_id' ";
        $table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = md.meta_value ";
        
        $cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d ", 'delivery_order', 6 );

        if( isset( $filters['warehouse_id'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
        }
        $date_type = 'post_date';
        if( isset( $filters['date_type'] ) )
        {
            $date_type = $filters['date_type'];
        }
        $date_type = empty( $date_type )? $this->def_date_type : $date_type;
        if( isset( $filters['from_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.{$date_type} >= %s ", $filters['from_date'] );
        }
        if( isset( $filters['to_date'] ) )
        {
            $cond.= $wpdb->prepare( "AND h.{$date_type} <= %s ", $filters['to_date'] );
        }
        if( isset( $filters['doc_id'] ) )
        {
            if( is_array( $filters['doc_id'] ) )
                $cond.= "AND h.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND h.doc_id = %d ", $filters['doc_id'] );
        }

        $sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";
        
        return $date = $wpdb->get_row( $sql, ARRAY_A );
    }

        public function margining_sales_handling( $month = '', $wh = '', $type = 'def' )
        {
            if( ! $month || ! $wh ) return false;

            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";
            
            $filters = [];
            $filters['from_date'] = date( 'Y-m-1 00:00:00', strtotime( $month ) );
            $filters['to_date'] = date( 'Y-m-t 23:59:59', strtotime( $month ) );
            //$filters['month'] = date( 'Y-m', strtotime( $month." -1 month" ) );     //prev_month
            $filters['warehouse_id'] = $wh;

            $dates = $this->get_sales_date( $filters );
            if( $dates )
            {
                $filters['from_date'] = ( $dates['from_date'] )? $dates['from_date'] : $filters['from_date'];
                $filters['to_date'] = ( $dates['to_date'] )? date( 'Y-m-t 23:59:59', strtotime( $dates['to_date'] ) ) : $filters['to_date'];
            }
            
            if( ! $filters['from_date'] && ! $filters['to_date'] ) return true;

            //-------------------------------------------------------------------------------------
            //Deletion
            $deletion = $filters;
            $deletion['type'] = $type;
            $result = $this->delete_margining_sales( $deletion );
            if( $result === false ) return false;

            //-------------------------------------------------------------------------------------
            //Saving
            $saving = $filters;
            $saving['type'] = $type;
            $saving['date'] = $month;
            $result = $this->save_margining_sales( $saving, 'wh_sales_order_invoice' );

            if( $result === false ) return false;

            return true;
        }

        public function margining_sales_handler( $filters = [], $margining_id = '' )
        {
            if( ! $filters['warehouse_id'] || ! $filters['doc_id'] ) return false;

            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";
            
            $filters['type'] = ( $filters['type'] )? $filters['type'] : 'def';
            $filters['month'] = date( 'Y-m', strtotime( $filters['date'] ) );

            //-------------------------------------------------------------------------------------
            //Deletion
            $result = $this->delete_margining_sales( $filters );
            if( $result === false ) return false;

            //-------------------------------------------------------------------------------------
            //Saving
            $result = $this->save_margining_sales( $filters, $margining_id );

            if( $result === false ) return false;

            return true;
        }

        public function save_margining_sales( $filters = [], $margining_id = "" )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $wh = $filters['warehouse_id'];
            $date = '';
            if( $filters['date'] ) $date = date( 'Y-m-1', strtotime( $filters['date'] ) );
            $type = 'def';
            if( $filters['type'] ) $type = $filters['type'];

            //$margining = apply_filters( 'wcwh_get_margining', $wh, $margining_element, [], $date, $type );
            //pd($margining);
            //$margining_id = ( $margining )? $margining['id'] : 0;

            $sales_query = $this->temp_sales_order( $filters, $type, $margining_id, false );

            $fld = "a.margining AS margining, '{$type}' AS type, '{$wh}' AS warehouse_id
            , a.doc_id, a.item_id, a.product_id, IFNULL( a.margin, 0 ) AS margin, a.doc_date, a.status
            , a.qty, a.foc, a.def_price, a.line_subtotal, a.line_discount, a.sprice, a.line_total
            , a.final_sprice, a.line_final_total, a.order_subtotal, a.order_discount, a.order_total ";

            $tbl = "( {$sales_query} ) a ";

            $cond = "";
            if( $type == 'adj' ) $cond.= "AND a.margining > 0 ";
            $grp = "";
            $ord = "ORDER BY doc_id ASC, product_id ASC ";

            $sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

            //-------------------------------------------------------------------------------------
            //Query Insertion

            $fld = "margining, type, warehouse_id
            , doc_id, item_id, product_id, margin, doc_date, status
            , qty, foc, def_price, line_subtotal, line_discount, sprice, line_total
            , final_sprice, line_final_total, order_subtotal, order_discount, order_total ";

            $insert = "INSERT INTO {$this->tables['margining_sales']} ( {$fld} ) {$sql} ";
            $result = $wpdb->query( $insert );

            //$this->drop_temp_sales_order();
            $this->drop_temp_sales_total();

            if( $result === false ) return false;
            return true;
        }

        public function delete_margining_sales( $filters = [] )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            if( isset( $filters['warehouse_id'] ) )
            {
                $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $filters['warehouse_id'] );
            }
            if( isset( $filters['type'] ) )
            {
                $cond.= $wpdb->prepare( "AND type = %s ", $filters['type'] );
            }
            else
                $cond.= $wpdb->prepare( "AND type = %s ", 'def' );
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND doc_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND doc_date <= %s ", $filters['to_date'] );
            }
            if( isset( $filters['doc_id'] ) )
            {
                if( is_array( $filters['doc_id'] ) )
                    $cond.= "AND doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND doc_id = %d ", $filters['doc_id'] );
            }

            $delete = "DELETE FROM {$dbname}{$this->tables['margining_sales']} WHERE 1 {$cond} ; ";
            
            $result = $wpdb->query( $delete );
            
            if( $result === false ) return false;
            return true;
        }

        public function temp_sales_order( $filters = [], $type = '', $margining_id = '', $run = false )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $this->temp_sales_total( $filters, $type, $margining_id );

            $field = "a.doc_id, b.item_id, b.product_id, a.doc_date, b.status, 0 AS margining, 0 AS margin 
                , @qty:= ROUND( b.bqty - IF( id.meta_value != 0, id.meta_value, 0 ), 2 ) AS qty 
                , @foc:= IF( id.meta_value != 0, id.meta_value, 0 ) AS foc
                , @price:= IFNULL( ia.meta_value, ib.meta_value ) AS price
                , @def_price:= IFNULL( ia.meta_value, ib.meta_value ) AS def_price
                , @line_subtotal:= ROUND( @qty * @def_price, 2 ) AS line_subtotal
                , @discount:= IF( ic.meta_value IS NULL, 0,IF( RIGHT( TRIM( ic.meta_value ), 1 ) = '%', 
                    ROUND( ( @line_subtotal / 100 ) * REPLACE( TRIM( ic.meta_value ), '%', '' ), 2 ), ic.meta_value ) ) AS line_discount
                , ROUND( ( @line_subtotal - @discount ) / ( @qty + @foc ), 5 ) AS sprice 
                , @line_total:= ROUND( @line_subtotal - @discount, 2 ) AS line_total 
                , ROUND( ROUND( ROUND( st.subtotal - st.discounted, 2 ) * ( @line_total / st.subtotal ), 2 ) / (@qty + @foc), 5 ) AS final_sprice 
                , ROUND( ROUND( st.subtotal - st.discounted, 2 ) * ( @line_total / st.subtotal ), 2 ) AS line_final_total 
                , st.subtotal AS order_subtotal, st.discounted AS order_discount
                , ROUND( st.subtotal - st.discounted, 2 ) AS order_total ";
            
            $table = "{$dbname}{$this->tables['document']} a ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = a.doc_id AND mc.item_id = 0 AND mc.meta_key = 'sap_po' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = b.doc_id AND ia.item_id = b.item_id AND ia.meta_key = 'def_sprice' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = b.doc_id AND ib.item_id = b.item_id AND ib.meta_key = 'sprice' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ic ON ic.doc_id = b.doc_id AND ic.item_id = b.item_id AND ic.meta_key = 'discount' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} id ON id.doc_id = b.doc_id AND id.item_id = b.item_id AND id.meta_key = 'foc' ";

            $table.= "LEFT JOIN {$this->tables['temp_st']} st ON st.doc_id = a.doc_id ";

            if( $type )
            {
                $field = "a.doc_id, b.item_id, b.product_id, a.doc_date, b.status 
                , IFNULL( mg.id, 0 ) AS margining, @mg:= IFNULL( mg.margin, 0 ) AS margin
                , @rn:= IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) AS round_nearest 
                , @qty:= ROUND( b.bqty - IF( id.meta_value != 0, id.meta_value, 0 ), 2 ) AS qty 
                , @foc:= IF( id.meta_value != 0, id.meta_value, 0 ) AS foc 
                , @price:= IFNULL( ia.meta_value, ib.meta_value ) AS price
                , @def_price:= IF( mg.id > 0, ROUND( CASE 
                    WHEN mg.round_type = 'ROUND' THEN ROUND( ROUND( @price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
                    WHEN mg.round_type = 'CEIL' THEN CEIL( ROUND( @price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
                    WHEN mg.round_type = 'FLOOR' THEN FLOOR( ROUND( @price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
                    WHEN mg.round_type IS NULL OR mg.round_type = 'DEFAULT' THEN ROUND( @price*( 1+( @mg/100 ) ), 5 ) 
                    END, 5 ), @price ) AS def_price 
                , @line_subtotal:= ROUND( @qty * @def_price, 2 ) AS line_subtotal 
                , @discount:= IF( ic.meta_value IS NULL, 0, IF( RIGHT( TRIM( ic.meta_value ), 1 ) = '%', 
                    ROUND( ( @line_subtotal / 100 ) * REPLACE( TRIM( ic.meta_value ), '%', '' ), 2 ), ic.meta_value ) ) AS line_discount 
                , ROUND( ( @line_subtotal - @discount ) / ( @qty + @foc ), 5 ) AS sprice 
                , @line_total:= ROUND( @line_subtotal - @discount, 2 ) AS line_total 
                , ROUND( ROUND( ROUND( st.subtotal - st.discounted, 2 ) * ( @line_total / st.subtotal ), 2 ) / (@qty + @foc), 5 ) AS final_sprice 
                , ROUND( ROUND( st.subtotal - st.discounted, 2 ) * ( @line_total / st.subtotal ), 2 ) AS line_final_total 
                , st.subtotal AS order_subtotal, st.discounted AS order_discount
                , ROUND( st.subtotal - st.discounted, 2 ) AS order_total ";

                if( $margining_id )
                {
                    $mg_sect = $wpdb->prepare( "AND s.sub_section = %s ", $margining_id );
                }

                $subsql = $wpdb->prepare( "SELECT h.id 
                    FROM {$this->tables['margining']} h 
                    LEFT JOIN {$this->tables['margining_sect']} s ON s.mg_id = h.id AND s.status > 0
                    WHERE 1 AND h.status > 0 AND h.flag > 0 
                    AND h.wh_id = a.warehouse_id AND h.type = %s {$mg_sect} 
                    AND ( ( h.po_inclusive = 'def' ) OR ( h.po_inclusive = 'with' AND LENGTH( mc.meta_value ) > 0 ) OR 
                            ( h.po_inclusive = 'without' AND LENGTH( mc.meta_value ) = 0 OR mc.meta_value IS NULL ) )
                    AND h.since <= a.doc_date AND ( h.until >= a.doc_date OR h.until = '' ) 
                    ORDER BY h.effective DESC, h.since DESC, h.created_at DESC 
                    LIMIT 0,1 ", $type );

                //$table.= "LEFT JOIN {$this->tables['margining_det']} mgd ON mgd.id = ( {$subsql} ) ";
                $table.= "LEFT JOIN {$this->tables['margining']} mh ON mh.id = ( {$subsql} ) ";
                
                $subsql = "SELECT m.id
                    FROM {$this->tables['margining']} m 
                    LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
                    WHERE 1 AND m.id = mh.id AND m.inclusive = 'excl' AND md.client = c.code ";
                $table.= "LEFT JOIN {$this->tables['margining']} mx ON mx.id = ( {$subsql} ) ";

                $subsql = "SELECT m.id
                    FROM {$this->tables['margining']} m 
                    LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
                    WHERE 1 AND m.id = mh.id 
                    AND ( ( m.inclusive = 'incl' AND md.client = c.code ) OR ( m.inclusive = 'excl' AND ( m.id != mx.id OR mx.id IS NULL ) ) ) 
                    ORDER BY m.effective DESC, m.since DESC, m.created_at DESC 
                    LIMIT 0,1
                ";
                $table.= "LEFT JOIN {$this->tables['margining']} mg ON mg.id = ( {$subsql} ) ";
            }
            
            $cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status > %d ", 'sale_order', 0 );

            if( isset( $filters['warehouse_id'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
            }
            if( isset( $filters['client'] ) )
            {
                if( is_array( $filters['client'] ) )
                    $cond.= "AND c.id IN ('" .implode( "','", $filters['client'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND c.id = %s ", $filters['client'] );
            }
            if( isset( $filters['not_client'] ) )
            {
                if( is_array( $filters['not_client'] ) )
                    $cond.= "AND c.id NOT IN ('" .implode( "','", $filters['not_client'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND c.id != %s ", $filters['not_client'] );
            }
            if( isset( $filters['doc_stat'] ) )
            {
                if( $filters['doc_stat'] == 'posted' )
                    $cond.= $wpdb->prepare( "AND a.status >= %s ", 6 );
                else if( $filters['doc_stat'] == 'all' )
                    $cond.= $wpdb->prepare( "AND a.status > %s ", 0 );
                else
                    $cond.= $wpdb->prepare( "AND a.status = %s ", $filters['doc_stat'] );
            }
            if( isset( $filters['doc_id'] ) )
            {
                if( is_array( $filters['doc_id'] ) )
                    $cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND a.doc_id = %s ", $filters['doc_id'] );
            }
            
            $grp = "";
            $ord = "ORDER BY a.doc_id ASC, b.item_id ASC ";

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run )
            {
                $query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_so']} AS ( {$query} ) ";
                $query = $wpdb->query( $query );
            } 
        
            return $query;
        }

        public function temp_sales_total( $filters = [], $type = '', $margining_id = '' )
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $dbname = !empty( $this->dbname )? $this->dbname : "";

            $field = "a.doc_id, b.item_id 
                , @qty:= ROUND( b.bqty - IF( id.meta_value != 0, id.meta_value, 0 ), 2 ) AS qty
                , IF( id.meta_value != 0, id.meta_value, 0 ) AS foc
                , 'DEFAULT' AS round_type, @mg:= 0 AS margin, '0.01' AS round_nearest 
                , @price:= IFNULL( ia.meta_value, ib.meta_value ) AS price
                , @def_price:= IFNULL( ia.meta_value, ib.meta_value ) AS def_price 
                , @subtotal:= ROUND( @qty * @def_price, 2 ) AS line_subtotal
                , @discount:= IF( ic.meta_value IS NULL, 0,IF( RIGHT( TRIM( ic.meta_value ), 1 ) = '%', 
                    ROUND( ( @subtotal / 100 ) * REPLACE( TRIM( ic.meta_value ), '%', '' ), 2 ), ic.meta_value ) ) AS line_discount
                , @total:= ROUND( @subtotal - @discount, 2 ) AS line_total, ROUND( @total / @qty, 5 ) AS sprice
                , mb.meta_value AS order_discount ";
            
            $table = "{$dbname}{$this->tables['document']} a ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = ma.meta_value ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = a.doc_id AND mb.item_id = 0 AND mb.meta_key = 'discount' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = a.doc_id AND mc.item_id = 0 AND mc.meta_key = 'sap_po' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ia ON ia.doc_id = b.doc_id AND ia.item_id = b.item_id AND ia.meta_key = 'def_sprice' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ib ON ib.doc_id = b.doc_id AND ib.item_id = b.item_id AND ib.meta_key = 'sprice' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ic ON ic.doc_id = b.doc_id AND ic.item_id = b.item_id AND ic.meta_key = 'discount' ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} id ON id.doc_id = b.doc_id AND id.item_id = b.item_id AND id.meta_key = 'foc' ";
            
            if( $type )
            {
                $field = "a.doc_id, b.item_id 
                , @qty:= ROUND( b.bqty - IF( id.meta_value != 0, id.meta_value, 0 ), 2 ) AS qty
                , IF( id.meta_value != 0, id.meta_value, 0 ) AS foc
                , mg.round_type AS round_type, @mg:= IFNULL( mg.margin, 0 ) AS margin
                , @rn:= IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) AS round_nearest 
                , @price:= IFNULL( ia.meta_value, ib.meta_value ) AS price 
                , @def_price:= IF( mg.id > 0, ROUND( CASE 
                    WHEN mg.round_type = 'ROUND' THEN ROUND( ROUND( @price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
                    WHEN mg.round_type = 'CEIL' THEN CEIL( ROUND( @price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
                    WHEN mg.round_type = 'FLOOR' THEN FLOOR( ROUND( @price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
                    WHEN mg.round_type IS NULL OR mg.round_type = 'DEFAULT' THEN ROUND( @price*( 1+( @mg/100 ) ), 5 ) 
                    END, 5 ), @price ) AS def_price 
                , @subtotal:= ROUND( @qty * @def_price, 2 ) AS line_subtotal
                , @discount:= IF( ic.meta_value IS NULL, 0,IF( RIGHT( TRIM( ic.meta_value ), 1 ) = '%', 
                    ROUND( ( @subtotal / 100 ) * REPLACE( TRIM( ic.meta_value ), '%', '' ), 2 ), ic.meta_value ) ) AS line_discount
                , @total:= ROUND( @subtotal - @discount, 2 ) AS line_total, ROUND( @total / @qty, 5 ) AS sprice
                , mb.meta_value AS order_discount ";

                if( $margining_id )
                {
                    $mg_sect = $wpdb->prepare( "AND s.sub_section = %s ", $margining_id );
                }
                
                $subsql = $wpdb->prepare( "SELECT h.id 
                    FROM {$this->tables['margining']} h 
                    LEFT JOIN {$this->tables['margining_sect']} s ON s.mg_id = h.id AND s.status > 0
                    WHERE 1 AND h.status > 0 AND h.flag > 0 
                    AND h.wh_id = a.warehouse_id AND h.type = %s {$mg_sect} 
                    AND ( ( h.po_inclusive = 'def' ) OR ( h.po_inclusive = 'with' AND LENGTH( mc.meta_value ) > 0 ) OR 
                            ( h.po_inclusive = 'without' AND LENGTH( mc.meta_value ) = 0 OR mc.meta_value IS NULL ) )
                    AND h.since <= a.doc_date AND ( h.until >= a.doc_date OR h.until = '' ) 
                    ORDER BY h.effective DESC, h.since DESC, h.created_at DESC 
                    LIMIT 0,1 ", $type );

                //$table.= "LEFT JOIN {$this->tables['margining_det']} mgd ON mgd.id = ( {$subsql} ) ";
                $table.= "LEFT JOIN {$this->tables['margining']} mh ON mh.id = ( {$subsql} ) ";
                
                $subsql = "SELECT m.id
                    FROM {$this->tables['margining']} m 
                    LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
                    WHERE 1 AND m.id = mh.id AND m.inclusive = 'excl' AND md.client = c.code ";
                $table.= "LEFT JOIN {$this->tables['margining']} mx ON mx.id = ( {$subsql} ) ";

                $subsql = "SELECT m.id
                    FROM {$this->tables['margining']} m 
                    LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
                    WHERE 1 AND m.id = mh.id 
                    AND ( ( m.inclusive = 'incl' AND md.client = c.code ) OR ( m.inclusive = 'excl' AND ( m.id != mx.id OR mx.id IS NULL ) ) ) 
                    ORDER BY m.effective DESC, m.since DESC, m.created_at DESC 
                    LIMIT 0,1
                ";
                $table.= "LEFT JOIN {$this->tables['margining']} mg ON mg.id = ( {$subsql} ) ";
            }

            $cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status > %d ", 'sale_order', 0 );

            if( isset( $filters['warehouse_id'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
            }
            if( isset( $filters['from_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['from_date'] );
            }
            if( isset( $filters['to_date'] ) )
            {
                $cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['to_date'] );
            }
            if( isset( $filters['client'] ) )
            {
                if( is_array( $filters['client'] ) )
                    $cond.= "AND c.id IN ('" .implode( "','", $filters['client'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND c.id = %s ", $filters['client'] );
            }
            if( isset( $filters['not_client'] ) )
            {
                if( is_array( $filters['not_client'] ) )
                    $cond.= "AND c.id NOT IN ('" .implode( "','", $filters['not_client'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND c.id != %s ", $filters['not_client'] );
            }
            if( isset( $filters['doc_stat'] ) )
            {
                if( $filters['doc_stat'] == 'posted' )
                    $cond.= $wpdb->prepare( "AND a.status >= %s ", 6 );
                else if( $filters['doc_stat'] == 'all' )
                    $cond.= $wpdb->prepare( "AND a.status > %s ", 0 );
                else
                    $cond.= $wpdb->prepare( "AND a.status = %s ", $filters['doc_stat'] );
            }
            if( isset( $filters['doc_id'] ) )
            {
                if( is_array( $filters['doc_id'] ) )
                    $cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND a.doc_id = %s ", $filters['doc_id'] );
            }

            $ord = "ORDER BY a.doc_id ASC ";

            $sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ";

            //-----------------------------------------------------------------------------
            $field = "a.doc_id, a.round_type, a.margin, a.round_nearest
                , SUM( a.line_total ) AS subtotal, a.order_discount
                , IF( a.order_discount IS NULL, 0, IF( RIGHT( TRIM(a.order_discount), 1 ) = '%', 
                    ROUND( ( SUM( a.line_total ) / 100 ) * REPLACE( TRIM(a.order_discount), '%', '' ), 2 ), a.order_discount ) ) AS discounted ";

            $table = "( {$sql} ) a ";
            $cond = "";
            $grp = "GROUP BY a.doc_id ";

            $select = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} ";

            $query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_st']} ";
            $query.= "AS ( {$select} ) ";

            $query = $wpdb->query( $query );
        }

        public function drop_temp_sales_order()
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $drop = "DROP TEMPORARY TABLE {$this->tables['temp_so']} ";
            $succ = $wpdb->query( $drop );
        }

        public function drop_temp_sales_total()
        {
            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();

            $drop = "DROP TEMPORARY TABLE {$this->tables['temp_st']} ";
            $succ = $wpdb->query( $drop );
        }
    
}

}