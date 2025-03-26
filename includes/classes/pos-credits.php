<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WC_POS_CreditLimit_Class" ) ) 
{

class WC_POS_CreditLimit_Class extends WCWH_CRUD_Controller
{
	protected $section_id = "pos_credits";

	protected $tbl = "wc_poin_of_sale_credit_registry";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "CreditLimit_Class";

	protected $seller = 0;
	
	public function __construct()
	{
		parent::__construct();
		
		global $wpdb;
		$this->set_prefix( $wpdb->prefix );

		$this->set_db_tables();
		
		add_filter( 'wc_credit_limit_action_handler', array( $this, 'action_handler' ), 10, 2 );
		
		//get client credits details
		add_filter( 'wc_credit_limit_get_client_credits', array( $this, 'get_client_credits' ), 10, 3 );
		add_filter( 'wc_credit_limit_get_client_debits', array( $this, 'get_client_debits' ), 10, 3 );
		
		//perform credit registry
		add_action( 'wc_pos_after_order_creation', array( $this, 'pos_after_order_creation' ), 10, 3 );
		add_action( 'woocommerce_rest_insert_shop_order_object', array( $this, 'pos_after_order_double_check' ), 10, 3 );
		//add_action( 'wc_pos_after_order_creation_resque', array( $this, 'pos_after_order_creation_resque' ), 10, 3 );

		add_action('woocommerce_order_status_pending_to_processing', array( $this, 'pos_after_order_payment' ), 10, 2 );
		add_action('woocommerce_order_status_pending_to_completed', array( $this, 'pos_after_order_payment' ), 10, 2 );

		//perform order cancellation
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'pos_after_order_cancelled' ), 10, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'pos_after_order_cancelled' ), 10, 1 );
		add_action( 'wc_order_passive_cancel_trigger', array( $this, 'pos_after_order_failure' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing_to_pending', array( $this, 'pos_after_order_failure' ), 10, 2 );

		//process terminated unexpectedly
		add_action( 'wc_pos_after_order_creation_failed', array( $this, 'pos_after_order_creation_failed' ), 10, 3 );
		
		//perform refund credit registry
		add_action( 'wc_pos_after_order_refund', array( $this, 'pos_after_order_refund' ), 10, 3 );

		add_filter( 'wcwh_get_credit_period', array( $this, 'get_credit_period' ), 10, 5 );

		add_filter( 'wcwh_sales_verification', array( $this, 'pos_verification' ), 10, 3 );

		add_action( 'wc_pos_after_close_register', array( $this, 'pos_after_close_register' ), 10, 2 );
	}

	public function set_db_tables()
    {
        global $wcwh;
        $prefix = $this->get_prefix();

        global $wcwh, $wpdb;
		$pre = $wcwh->prefix;

        $this->tables = array(
            "main" => $prefix.$this->tbl,
            "credit_term" => $pre."credit_term",
            "credit_limit" => $pre."credit_limit",
			"credit_topup" => $pre."credit_topup",
			"acc_type" => $pre."customer_acc_type",

			"selling_price" => $pre."selling_price",
			"order" => $wpdb->posts,
        );
    }
	
	public function get_defaultFields()
	{
		return array(
			'user_id' => 0,
			'order_id' => 0,
			'type' => '',
			'amount' => 0,
			'note' => '',
			'parent' => 0,
			'time' => '',
			'status' => 1,
		);
	}
	
	public function action_handler( $action , $datas = array() )
	{
		$succ = true;

		if( ! $action || ! $datas )
        {
            $succ = false;
        }

		wpdb_start_transaction();

		$outcome = array();

		if( $succ )
        {
        	$exist = array();

			switch ( strtolower( $action ) )
			{
				case "save":
					$datas = $this->data_sanitizing( $datas );
					$datas = wp_parse_args( $datas, $this->get_defaultFields() );
				case "update":
					$id = ( isset( $datas['id'] ) && !empty( $datas['id'] ) )? $datas['id'] : "0";

	                if( $id != "0" )    //update
	                {
	                    $exist = $this->select( $id );
	                    if( null === $exist )
	                    {
	                        $succ = false;
	                    }
	                    else 
	                    {
	                        $result = $this->update( $id, $datas );
	                        if ( false === $result )
	                        {
	                            $succ = false;
	                        }
	                    }
	                }
	                else
	                {
	                    $id = $this->create( $datas );
	                    if ( ! $id )
	                    {
	                        $succ = false;
	                    }
	                }

	                if( $succ )
	                {
	                    $outcome['id'] = $id;
	                }
				break;
				case "delete":
					$deleted = false;

					$id = $datas['id'];
					if( $id > 0 )
					{
						$exist = $this->select( $id );
						if( null === $exist )
						{
							$succ = false;
						}
						else
						{
							$datas['status'] = 0;

							if( $succ )
							{
								$result = $this->update( $id, $datas );
								if( false === $result )
								{
									$succ = false;
								}
							}
						}
					}
					else 
					{
						$succ = false;
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
				case "delete-permanent":
					$deleted = false;

	                $id = $datas['id'];
	                if( $id > 0 )
	                {
	                    $exist = $this->select( $id );
	                    if( null === $exist )
	                    {
	                        $succ = false;
	                    }
	                    else
	                    {
	                        $result = $this->delete( $id );
	                        if( $result === false )
	                        {
	                            $succ = false;
	                        }

	                        if( $succ ) $deleted = true;
	                    }
	                }
	                else 
	                {
	                    $succ = false;
	                }

	                if( $succ )
	                {
	                    $outcome['id'] = $id;
	                }
				break;
			}
		}
		
		wpdb_end_transaction( $succ );
		
		return $succ;
	}

	public function get_user_credit_term( $c_id = 0, $group_id = 0 )
	{
		if( ! $c_id ) return false;

		global $wpdb;

		if( $this->seller > 0 )
		{
			$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$field = "a.*, b.name, b.days, b.offset, b.type, b.parent, b.apply_date ";
		$table = "{$dbname}{$this->tables['credit_limit']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['credit_term']} b ON b.id = a.term_id ";
		$cond = $wpdb->prepare( "AND a.status > %d ", 0 );
		$ord = "ORDER BY a.scheme_lvl DESC ";

		$cd = array();
		$cd[] = $wpdb->prepare( "( a.scheme = %s AND a.ref_id = %s )", 'customer', $c_id );
		if( $group_id )
			$cd[] = $wpdb->prepare( "( a.scheme = %s AND a.ref_id = %s )", 'customer_group', $group_id );

		$cond.= "AND ( ".implode( "OR ", $cd ).") ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ;";

		return $wpdb->get_row( $sql , ARRAY_A );
	}

	public function get_acc_type_credit_term( $acc_type = 0 )
	{
		if( ! $acc_type ) return false;

		global $wpdb;

		if( $this->seller > 0 )
		{
			$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$field = "a.*, b.name, b.days, b.offset, b.type, b.parent, b.apply_date ";
		$table = "{$dbname}{$this->tables['acc_type']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['credit_term']} b ON b.id = a.term_id ";
		$cond = $wpdb->prepare( "AND a.id = %s AND a.term_id > %d ", $acc_type, 0 );
		$ord = "";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$ord} ;";

		return $wpdb->get_row( $sql , ARRAY_A );
	}
	
	public function get_user_credit_topup( $id = 0, $from = '', $to = '' )
	{
		if( ! $id ) return false;
		
		global $wpdb;

		if( $this->seller > 0 )
		{
			$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		
		$field = "round( SUM( a.credit_limit * ( a.percentage / 100 ) ), 2 ) AS topup ";
		$table = "{$dbname}{$this->tables['credit_topup']} a ";
		$cond = $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );
		
		$cond.= $wpdb->prepare( "AND a.customer_id = %s ", $id );
		
		if( $from )
		{
			$cond.= $wpdb->prepare(" AND a.effective_from >= %s ", date( 'Y-m-d H:i:s', strtotime( $from ) ) );
			$cond.= $wpdb->prepare(" AND a.effective_from <= %s ", current_time( 'mysql' ) );
			$cond.= $wpdb->prepare(" AND a.effective_from <= %s ", ( empty( $to ) )? current_time( 'mysql' ) : date( 'Y-m-d H:i:s', strtotime( $to." 23:59:59" ) ) );
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ;";

		return $wpdb->get_var( $sql );
	}

	public function get_date( $first_day = 1, $type ="begin", $month = 0, $year = 0, $today = 0 )
	{
		$day = ( $today > 0 )? (string)$today : current_time( 'd' );
		$month = ( $month > 0 && $month < 13 )? (string)$month : 'm';
		$year = ( $year > 0 )? (string)$year : 'Y';

		$date = date( $year.'-'.$month.'-'.$first_day );

		switch( $type )
		{
			case 'begin':
			case 'start':
				$date = date( 'Y-m-d', strtotime( date( "{$year}-{$month}-{$first_day}" )." -1 month" ) );
				if( (int)$day >= $first_day && (int)$day <= 31 )
				{
					$date = date( 'Y-m-d', strtotime( date( "{$year}-{$month}-{$first_day}" ) ) );
				}
			break;
			case 'end':
			case 'last':
				$date = date( 'Y-m-d', strtotime( date( "{$year}-{$month}-{$first_day}" )." +1 month" ) );
				if( (int)$day < $first_day )
				{
					$date = date( 'Y-m-d', strtotime( date( "{$year}-{$month}-{$first_day}" ) ) );
				}

				$date = date( 'Y-m-d', strtotime( $date." -1 days" ) );
			break;
		}

		return $date;
	}

	public function offset_date_by_day( $date, $offset = 0 )
	{
		if( $offset == 0 ) return $date;

		$offset = $offset > 0 ? '+'.$offset : $offset;
		$date = date( 'Y-m-d', strtotime( date( $date )." {$offset} days" ) );

		return $date;
	}

	public function check_in_dates( $from = '', $to = '', $date = '' )
	{
		if( ! $from || ! $to ) return false;
		$now = ( $date )? date( 'Y-m-d H:i:s', strtotime( $date.current_time( " H:i:s" ) ) ) : current_time( 'Y-m-d H:i:s' );

		$f = strtotime( $from.' 00:00:00' );
		$t = strtotime( $to.' 23:59:59' );
		$n = strtotime( date( $now ) );

		if( $n >= $f && $n <= $t ) return true;

		return false;
	}

	public function get_credit_period( $day_of_month = 1, $offset = 0, $term_id = 0, $current_date = '', $seller = 0 )
	{
		$current_date = ( $current_date )? $current_date : current_time( 'Y-m-d' );
		$cy = ( $current_date )? date( 'Y', strtotime( $current_date ) ) : 0;
		$cm = ( $current_date )? date( 'm', strtotime( $current_date ) ) : 0;
		$cd = ( $current_date )? date( 'd', strtotime( $current_date ) ) : 0;

		$date_from = WC_POS_CreditLimit_Class::get_date( $day_of_month, 'begin', $cm, $cy, $cd );
		$date_from = WC_POS_CreditLimit_Class::offset_date_by_day( $date_from, $offset, $cd );
		$date_to = WC_POS_CreditLimit_Class::get_date( $day_of_month, 'end', $cm, $cy, $cd );

		if( $term_id )
		{
			$filters = [ 'parent'=>$term_id, 'status'=>1 ];
			if( $seller ) $filters['seller'] = $seller;
			$child_terms = apply_filters( 'wcwh_get_credit_term', $filters, [], false );
			if( $child_terms )
			{
				foreach( $child_terms as $i => $child_term )
				{
					$y = date( 'Y', strtotime( date( $child_term['apply_date'] ) ) );
					$m = date( 'm', strtotime( date( $child_term['apply_date'] ) ) );
					$d_from = WC_POS_CreditLimit_Class::get_date( $child_term['days'], 'begin', $m, $y, 1 );
					$d_from = WC_POS_CreditLimit_Class::offset_date_by_day( $d_from, $child_term['offset'] );
					$d_to = WC_POS_CreditLimit_Class::get_date( $child_term['days'], 'end', $m, $y, 1 );
					
					$inDate = WC_POS_CreditLimit_Class::check_in_dates( $d_from, $d_to, date( 'Y-m-d', strtotime( $current_date ) ) );
					if( $inDate )
					{
						$date_from = $d_from;
						$date_to = $d_to;
						break;
					}
				}
			}
		}

		return [ 'from'=>$date_from, 'to'=>$date_to ];
	}
	
	public function get_client_credits( $customer_id = 0, $customer = array(), $seller = 0 )
	{
		if( !$customer_id ) return false;

		if( $seller > 0 ) $this->seller = $seller;

		$f = [ 'id'=>$customer_id ];
		if( $this->seller > 0 ) $f['seller'] = $this->seller;
		$customer = (  $customer )?  $customer : apply_filters( 'wcwh_get_customer', $f, [], true, [ 'group'=>1, 'usage'=>1 ] );
		if( !$customer ) return false;

		$term = $this->get_user_credit_term( $customer['id'], $customer['cgroup_id'] );
		$at_term = $this->get_acc_type_credit_term( $customer['acc_type'] );
		if( $at_term && $at_term['term_id'] )
		{
			$term['term_id'] = !empty( $at_term['term_id'] )? $at_term['term_id'] : $term['term_id'];
			$term['name'] = !empty( $at_term['name'] )? $at_term['name'] : $term['name'];
			$term['days'] = !empty( $at_term['days'] )? $at_term['days'] : $term['days'];
			$term['offset'] = !empty( $at_term['offset'] )? $at_term['offset'] : $term['offset'];
			$term['type'] = !empty( $at_term['type'] )? $at_term['type'] : $term['type'];
			$term['parent'] = !empty( $at_term['parent'] )? $at_term['parent'] : $term['parent'];
			$term['apply_date'] = !empty( $at_term['apply_date'] )? $at_term['apply_date'] : $term['apply_date'];
		}
		$day_of_month = ( $term )? $term['days'] : 1;
		$offset = ( $term )? $term['offset'] : 0;

		$date_range = $this->get_credit_period( $day_of_month, $offset, $term['term_id'], '', $seller );
		$date_from = $date_range['from'];
		$date_to = $date_range['to'];
		
		$credits = $term['credit_limit'];
		
		$topup = $this->get_user_credit_topup( $customer['id'], $date_from, $date_to );
		$topup = ( $topup )? $topup : 0;
			
		$credit_used = $this->sum_credit( $customer_id, $date_from, $date_to );
		$credit_used = ( $credit_used != 0 )? $credit_used : 0;
		
		$total = $credits + $topup;
		$credits = $total + $credit_used;
		
		$data = array(
			'credit_term' => $term,
			'total_creditable' => $total,
			'usable_credit' => $credits,
			'used_credit' => $credit_used,
			'credit_history' => $this->get_history( $customer_id, $date_from, $date_to ),
			'from_date' => $date_from,
			'to_date' => $date_to,
			'topup_credit' => $topup,
		);
		
		return $data;
	}

	public function get_client_debits( $customer_id = 0, $customer = array(), $seller = 0 )
	{
		if( !$customer_id ) return false;

		if( $seller > 0 ) $this->seller = $seller;

		$f = [ 'id'=>$customer_id ];
		if( $this->seller > 0 ) $f['seller'] = $this->seller;
		$customer = (  $customer )?  $customer : apply_filters( 'wcwh_get_customer', $f, [], true, [ 'group'=>1, 'member'=>1, 'usage'=>1 ] );
		if( !$customer || ! $customer['member_serial'] ) return false;
		
		$data = array(
			'credit_term' => [],
			'total_creditable' => ( $customer['balance'] > 0 )? $customer['balance'] : 0,
			'usable_credit' => ( $customer['balance'] > 0 )? $customer['balance'] : 0,
			'used_credit' => ( $customer['total_used'] > 0 )? $customer['total_used'] : 0,
			'credit_history' => $this->get_history( $customer_id, '', '', 'debit_sales' ),
			'from_date' => $customer['member_create_date'],
			'to_date' => current_time('mysql'),
			'total_debited' => ( $customer['total_debit'] > 0 )? $customer['total_debit'] : 0,
		);
		
		return $data;
	}
	
	public function sum_credit( $customer_id = 0, $from = '', $to = '', $type = '' )
	{
		if( ! $customer_id ) return 0;
		
		global $wpdb;

		if( $this->seller > 0 )
		{
			$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		
		$fld = " SUM(a.amount) "; 
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = $wpdb->prepare(" AND a.user_id = %d AND a.status != %d ", $customer_id, 0, 0 );

		if( !empty( $type ) )
		{
			$cond.= $wpdb->prepare(" AND a.type = %s ", $type );
		}
		else
		{
			$cond.= $wpdb->prepare(" AND a.type = %s ", 'sales' );
		}
		
		if( $from )
		{
			$cond.= $wpdb->prepare(" AND ( a.time >= %s AND a.time <= %s ) ", date( 'Y-m-d H:i:s', strtotime( $from ) ), 
				( empty( $to ) )? current_time( 'mysql' ) : date( 'Y-m-d H:i:s', strtotime( $to." 23:59:59" ) ) );
		}
		
		$sql = "SELECT {$fld} FROM {$table} WHERE 1 {$cond};";
		
		return $wpdb->get_var( $sql );
	}
	
	public function get_history( $customer_id = 0, $from = '', $to = '', $type = '' )
	{
		if( ! $customer_id ) return array();
		
		global $wpdb;

		if( $this->seller > 0 )
		{
			$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		
		$fld = " a.*, IFNULL( pb.meta_value, b.meta_value ) AS 'order_no', IFNULL( pc.meta_value, c.meta_value ) AS 'order_total', DATE_FORMAT( a.time, '%Y.%m.%d %H:%i' ) AS 'date' "; 
		$table = " {$dbname}{$this->tables['main']} a ";
		$table.= " LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.order_id and b.meta_key = '_order_number' ";
		$table.= " LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.order_id and c.meta_key = '_order_total' ";
		$table.= " LEFT JOIN {$dbname}{$this->tables['main']} p ON p.id = a.parent ";
		$table.= " LEFT JOIN {$dbname}{$wpdb->postmeta} pb ON pb.post_id = p.order_id and pb.meta_key = '_order_number' ";
		$table.= " LEFT JOIN {$dbname}{$wpdb->postmeta} pc ON pc.post_id = p.order_id and pc.meta_key = '_order_total' ";
		
		$cond = $wpdb->prepare(" AND a.user_id = %d AND a.status != %d ", $customer_id, 0 );
		if( !empty( $type ) )
		{
			$cond.= $wpdb->prepare(" AND a.type = %s ", $type );
		}
		else
		{
			$cond.= $wpdb->prepare(" AND a.type = %s ", 'sales' );
		}
		
		if( $from )
		{
			$cond.= $wpdb->prepare(" AND ( a.time >= %s AND a.time <= %s ) ", date( 'Y-m-d H:i:s', strtotime( $from ) ), 
				( empty( $to ) )? current_time( 'mysql' ) : date( 'Y-m-d H:i:s', strtotime( $to." 23:59:59" ) ) );
		}
		
		$ords = " ORDER BY a.time DESC ";
		
		$sql = "SELECT {$fld} FROM {$table} WHERE 1 {$cond} {$ords};";
		
		return $wpdb->get_results( $sql , ARRAY_A );
	}
	
	public function pos_after_order_creation( $order, $request, $creating )
	{
		$action = ( $creating )? 'save' : 'update';
		$setting = $this->setting['pos'];

		if( $order->get_id() )
		{
			$order_type = get_post_type( $order->get_id() );
			if( $order_type == 'pos_temp_register_or' ) 
			{
				wp_update_post( array(
	                'ID' => $order->get_id(),
	                'post_type' => 'shop_order',
	            ));
			}

			/*add_post_meta( $order->get_id(), 'ip_address', apply_filters( 'wcwh_get_user_ip', 1 ) );*/
		}

		if( $order->get_id() && in_array( $order->get_status(), array( 'processing', 'completed' ) ) )
		{
			$succ = true;
			$order_id = $order->get_id();
			$order_metas = get_post_meta( $order_id );
			$order_metas = array_combine( array_keys( $order_metas ), array_column( $order_metas, '0' ) );

			/*if( $order_metas['finalize_timestamp'] )
			{
				$pa = get_option( 'pos_ambiguous', [] );
				$pa[] = $order_id;
				$pa = array_unique($pa);
				update_option( 'pos_ambiguous', $pa );

				throw new WC_REST_Exception( 'woocommerce_possible_duplicate', __( 'Possible Duplication Occur', 'woocommerce' ), 400 );

				return true;
			}
			update_post_meta( $order_id, 'finalize_timestamp', time() );*/

			$user_id = $order_metas['_customer_user'];//get_post_meta( $order_id, '_customer_user', true );

			$customer_id = get_user_meta( $user_id, 'customer_id', true );
			if( $customer_id ){
				update_post_meta( $order_id, 'customer_id', $customer_id );

				$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$customer_id ], [], true );
				if( $customer ){
					update_post_meta( $order_id, '_customer_serial', $customer['serial'] );
					$order_metas['_customer_serial'] = $customer['serial'];
				}
			}

			$serial = $order_metas['_customer_serial'];//get_post_meta( $order_id, '_customer_serial', true );
			$credit = $order_metas['wc_pos_credit_amount'];//get_post_meta( $order_id, 'wc_pos_credit_amount', true );
			$total = $order_metas['_order_total'];//get_post_meta( $order_id, '_order_total', true );
			if( $customer_id && $credit )
			{
				if( ! $order_metas['customer_counted'] )
				{
					$cc = apply_filters( 'wcwh_update_customer_count', $customer_id, $serial, $total, $credit, '+' );

					update_post_meta( $order_id, 'customer_counted', 1 );
				}
			}
			
			if( !empty( $request['warehouse_id'] ) )
				update_post_meta( $order_id, 'wc_pos_warehouse_id', $request['warehouse_id'] );

			$sdocno = "";
			if( $action == 'save' )
			{
				if( empty( $order_metas['_order_number'] ) || $order_metas['_order_number'] == $order_id )
				{
					$sdocno = apply_filters( 'warehouse_generate_docno', $order_id, 'shop_order' );
					if( $sdocno ) update_post_meta( $order_id, '_order_number', $sdocno );
				}
				else
				{
					$sdocno = $order_metas['_order_number'];
				}

				$the_post = [
				    'ID'           => $order_id,
				    'post_excerpt' => $sdocno,
				    'post_type'    => 'shop_order',
				];
				if( $customer_id ) $the_post['post_content'] = $customer_id;
				if( $the_post['ID'] > 0 ) wp_update_post( $the_post );
			}
			else
			{
				$sdocno = $order_metas['_order_number'];
			}
			
			if( $customer_id && $credit > 0 )	//credit registry
			{
				$credit_type = ( $order_metas['tool_request_id'] > 0 )? 'tools' : 'sales';
				if( $order_metas['membership_id'] )
				{
					delete_post_meta( $order_id, 'membership_pin' );
					$credit_type = "debit_sales";

					if( ! class_exists( 'WCWH_Membership_Class' ) ) include_once( WCWH_DIR . "/includes/classes/membership.php" ); 
					$Member = new WCWH_Membership_Class();
					$succ = $Member->update_member_credit( $customer_id, $credit, "-" );
				}

				$datas = array(
					'user_id' => $customer_id,
					'order_id' => $order_id,
					'type' => $credit_type,
					'amount' => $credit * -1,
					'note' => '',
					'parent' => 0,
					'time' => current_time( 'mysql' )
				);
				
				$result = $this->select( 0, array( 'user_id' => $customer_id, 'order_id' => $order_id, 'type' => $credit_type ) );
				if( $result ) $datas['id'] = $result['id'];
				
				$this->action_handler( $action, $datas );
			}

			//promotion
			if( !empty( $request['promotion_data'] ) )
			{
				if ( !class_exists( "WCWH_PromoHeader" ) ) require_once( WCWH_DIR . "/includes/classes/promo-header.php" );
				$Promo = new WCWH_PromoHeader();
				foreach( $request['promotion_data'] as $hid => $promo )
				{
					$used = ( $promo['fulfil'] < $promo['occurrence'] )? $promo['fulfil'] : $promo['occurrence'];
					$Promo->update_promo_usage( $promo['id'], $used, '+' );
					update_post_meta( $order_id, 'wc_pos_promotion_'.$promo['id'], $used );
				}
			}
			
			$header_item = [ 'doc_type'=>'sales', 'warehouse_id'=> $request['warehouse_id'] ];
			
			$items = $order->get_items();
			$price_logs = array();
			$pos_transacts = array();
			$pos_do_detail = array();
			$tool_request = array();

			foreach( $items as $item_id => $item )//_items_id
			{
				//$uprice = $order->get_item_subtotal( $item, $order->prices_include_tax, true );
				$prdt_id = ( $item['variation_id'] )? $item['variation_id'] : $item['product_id'];
				$items_id = ( $item['items_id'] )? $item['items_id'] : get_post_meta( $prdt_id, 'item_id', true );
				$item['uom'] = ( $item['uom'] )? $item['uom'] : wc_get_order_item_meta( $item_id, '_uom', true );
				$item['uprice'] = ( $item['uprice'] )? $item['uprice'] : wc_get_order_item_meta( $item_id, '_uprice', true );
				$item['price'] = ( $item['price'] )? $item['price'] : wc_get_order_item_meta( $item_id, '_price', true );
				$qty = $item->get_quantity();

				/*if( $qty == 0 )
				{
					throw new WC_REST_Exception( 'woocommerce_zero_qty', __( 'Zero Quantity Occur', 'woocommerce' ), 400 );

					return true;
				}*/

				$item['tool_item_id'] = ( $item['tool_item_id'] )? $item['tool_item_id'] : wc_get_order_item_meta( $item_id, '_tool_item_id', true );
				if( $item['tool_item_id'] && $order_metas['tool_request_id'] )
				{
					$tool_request[] = [
						'item_id' => $item['tool_item_id'],
						'doc_id' => $order_metas['tool_request_id'],
						'product_id' => $items_id,
						'qty' => $qty,
						'plus_sign' => '+',
					];
				}
				
				$isVirtual = get_items_meta( $items_id, 'virtual', true );
				if( $isVirtual )
				{
					$itemize_ids = wc_get_order_item_meta( $item_id, '_itemize', true );
					if( ! $itemize_ids )
					{
						$filters = [ 'product_id'=>$items_id, 'status'=>1, 'stock'=>1, 'expiry'=>current_time('Y-m-d') ];
						$itemize = apply_filters( 'wcwh_get_itemize', $filters, [ 'expiry'=>'ASC', 'id'=>'ASC' ], false, [], [], [ 0, $qty ] );
						if( $itemize )
						{	
							$per_itemize = [];
							foreach( $itemize as $j => $per )
							{
								$per_itemize[] = $per['id'];
								
								$dat = [
									'id' => $per['id'],
									'sales_item_id' => $item_id,
									'unit_price' => $item['price'],
									'stock' => 0,
								];
								$result = apply_filters( 'wcwh_itemize_handler', 'update', $dat );
							}
							wc_update_order_item_meta( $item_id, '_itemize', $per_itemize );
						}
					}
				}

				$rowa = array(
					'sales_item_id'   	=> $item_id, 
					'order_id'   		=> $order_id, 
					'docno'				=> $sdocno,
					'warehouse_id' 		=> $request['warehouse_id'], 
					'strg_id'			=> apply_filters( 'wcwh_get_system_storage', 0, $header_item, $item ),
					'customer' 			=> $customer_id,
					'sales_date' 		=> $order->order_date, 
					'tool_id'			=> $item['tool_item_id'],
					'prdt_id' 			=> $items_id,
					'uom'				=> $item['uom'],
					'qty' 				=> $qty,
					'unit'				=> ( $item['unit'] )? $qty * $item['unit'] : 0,
					'uprice' 			=> $item['uprice'],
					'price' 			=> $item['price'],
					'total_amount' 		=> $item['price'] * $qty,
				);
				$price_logs[] = $rowa;

				$return_item = get_items_meta( $items_id, 'returnable_item', true );
				if( $return_item > 0 )
				{
					$it = apply_filters('wcwh_get_item', [ 'id'=>$return_item ], [], true, [ 'usage'=>1 ] );
					if( $it )
					{
						$rowb = array(
							'product_id' 		=> $it['id'],
							'uom_id'			=> $it['_uom_code'],
							'bqty' 				=> $qty,
							'bunit'				=> ( $item['unit'] )? $qty * $item['unit'] : 0,
							'ref_doc_id' 		=> $order_id,
							'ref_item_id' 		=> $item_id,
							//Custom Field 
							'uprice' 			=> 0,
							'price'				=> 0,
							'total_amount' 		=> 0,
							'plus_sign'			=> '+',
						);	
						$pos_transacts[] = $rowb;
					}
				}

				if(  $setting['pos_auto_do'] && current_user_can( 'save_wh_pos_do' ) )
				{
					$pos_do_detail[] = [
						'product_id' => $items_id,
						'bqty'		 => $qty,
						'bunit'		 => '0',
						'ref_doc_id' => '0',
						'ref_item_id'=> '0',
						//'sprice'	 => ($item['price'])? $item['price']:'0',
					];
				}
				
				if( $items_id )
				{
					$is_returnable = get_items_meta( $items_id, 'is_returnable', true );
					if( $is_returnable )
					{
						$add_gt_total = get_items_meta( $items_id, 'add_gt_total', true );
						if( $add_gt_total )
						{
							$gt_added = wc_get_order_item_meta( $item_id, '_gt_total_added', true );
							if( ! $gt_added )
							{
								$gtd = get_option( 'gt_total', 0 );
								$gtd-= $qty;
								update_option( 'gt_total', $gtd );

								wc_update_order_item_meta( $item_id, '_gt_total_added', $qty );
							}
						}
					}
				}
			}

			if( $order_metas['tool_request_id'] && $tool_request )
			{
				$tool_doc = [
					'doc_id' => $order_metas['tool_request_id'],
					'details' => $tool_request,
				];

				if( $order_metas['tool_doc_type'] && $order_metas['tool_doc_type'] == 'parts_request' )
					$succ = apply_filters( 'wcwh_parts_request_completion', $succ, $tool_doc );
				else
					$succ = apply_filters( 'wcwh_tool_request_completion', $succ, $tool_doc );
			}
			
			if( $creating && $price_logs && $setting['price_log'] )		//price log
			{
				$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'save' , $price_logs );
			}
			
			if( $succ && $pos_transacts )	// POS transactions (if any)
			{
				$reg_id = $order_metas['wc_pos_id_register'];//get_post_meta( $order_id, 'wc_pos_id_register', true );
				$sess_id = $order_metas['_pos_session_id'];//get_post_meta( $order_id, '_pos_session_id', true );

				$header_item = [ 'warehouse_id'=>$request['warehouse_id'], 'register'=>$reg_id, 'session'=>$sess_id 
					, 'receipt'=>$sdocno, 'receipt_id' =>$order_id ];
				
				/*$filters = [
					'warehouse_id' => $request['warehouse_id'],
					'register' => $reg_id,
					'session' => $sess_id,
					'doc_type' => 'pos_transactions',
					'status' => 1,
				];
				$exist = apply_filters( 'wcwh_get_doc_header', $filters, [], true, [ 'meta'=>[ 'register', 'session' ] ] );

				if( $exist )
				{	
					$header_item['doc_id'] = $exist['doc_id'];
					$result = apply_filters( 'wcwh_pos_transaction' , 'update-item', $header_item , $pos_transacts );
				}
				else
				{
					$result = apply_filters( 'wcwh_pos_transaction' , 'save', $header_item , $pos_transacts );
				}*/

				$pt_datas = [ 'header'=>$header_item, 'detail'=>$pos_transacts ];
				$result = apply_filters( 'wcwh_pos_transaction' , 'save', $pt_datas , $pt_datas );
				if( ! $result['succ'] )
				{
					$succ = false;
				}
				else
				{
					$header_item = [ 'id'=>$result['id'] ];
					$result = apply_filters( 'wcwh_pos_transaction' , 'post', $header_item, [] );

					if( ! $result['succ'] )
					{
						$succ = false;
					}
				}
			}

			if( $succ && $pos_do_detail )
			{
				if ( !class_exists( "WCWH_PosDO_Class" ) ) include_once( WCWH_DIR . "/includes/classes/pos-do.php" ); 
				$PosDO = new WCWH_PosDO_Class( $this->db_wpdb );
				$PosDO->setUpdateUqtyFlag( false );

				$pos_do_header = [
					'warehouse_id' => $request['warehouse_id'],
					'doc_date' 	   => $order_metas['_paid_date'],
					'ref_order_id' => $order_id,
					'ref_order_no' => $sdocno,
					'remark'       => $order_metas['order_comments'],
				];

				$result = $PosDO->child_action_handle( 'save', $pos_do_header, $pos_do_detail );
				if( ! $result['succ'] )
				{
					$succ = false;
				}
				else
				{
					if( $result['id'] )
					{
						$pos_do_header = [ 'doc_id'=>$result['id'] ];
						$result = $PosDO->child_action_handle( 'post', $pos_do_header );
						if( ! $result['succ'] ) $succ = false;
					}
					else $succ = false;
				}
			}
		}
		else if( $order->get_id() && in_array( $order->get_status(), array( 'pending' ) ) )
		{
			$succ = true;
			$order_id = $order->get_id();
			//$order_metas = get_post_meta( $order_id );
			//$order_metas = array_combine( array_keys( $order_metas ), array_column( $order_metas, '0' ) );

			$user_id = get_post_meta( $order_id, '_customer_user', true );//$order_metas['_customer_user'];

			$customer_id = get_user_meta( $user_id, 'customer_id', true );
			if( $customer_id ) update_post_meta( $order_id, 'customer_id', $customer_id );

			if( !empty( $request['warehouse_id'] ) )
				update_post_meta( $order_id, 'wc_pos_warehouse_id', $request['warehouse_id'] );

			$sdocno = "";
			if( $action == 'save' )
			{
				$sdocno = apply_filters( 'warehouse_generate_docno', $order_id, 'shop_order' );
				if( $sdocno ) update_post_meta( $order_id, '_order_number', $sdocno );

				$the_post = [
				    'ID'           => $order_id,
				    'post_excerpt' => $sdocno,
				    'post_type'    => 'shop_order',
				];
				if( $customer_id ) $the_post['post_content'] = $customer_id;
				if( $the_post['ID'] > 0 ) wp_update_post( $the_post );
			}

			//promotion
			if( !empty( $request['promotion_data'] ) )
			{
				update_post_meta( $order_id, 'request_promotion_data', $request['promotion_data'] );
			}
		}

		//check duplication header meta || remove unused meta
		$keys = [ '_order_number', '_payment_method', '_payment_method_title', 'wc_pos_id_register', '_pos_session_id', 'wc_pos_amount_pay', 'wc_pos_amount_change', 'wc_pos_credit_amount', 'customer_id', '_customer_serial' ];
		$unused_keys = [ 'wc_pos_signature', 'wc_pos_prefix_suffix_order_number', 'wc_pos_order_saved', 'wc_pos_dining_option', '_shipping_address_index', '_download_permissions_granted', '_billing_address_index', '_customer_name' ];
		$ord = get_post_meta( $order_id );
		if( $ord )
		{
			foreach( $ord as $meta => $val )
			{
				if( in_array( $meta, $keys ) )
				{
					if( sizeof( $val ) > 1 )
					{
						delete_post_meta( $order_id, $meta );
						update_post_meta( $order_id, $meta, $val[0] );
					}
				}

				if( in_array( $meta, $unused_keys ) )
				{
					delete_post_meta( $order_id, $meta );
				}
			}
		}
	}

	public function pos_after_order_double_check( $order, $request, $creating )
	{
		$order_id = $order->get_id();

		$subtotal = $order->get_subtotal();
		$discount = $order->get_discount_total();
		$total = $order->order_total;

		if( $subtotal - $discount != $total )
		{
			$order->calculate_totals( true );
		}
	}

	public function pos_after_order_payment( $order_id = 0, $order = [] )
	{
		$setting = $this->setting['pos'];
		
		//$order = wc_get_order( $order_id );
		$succ = true;
		$order_id = ( $order_id )? $order_id : $order->get_id();
		$order_metas = get_post_meta( $order_id );
		$order_metas = array_combine( array_keys( $order_metas ), array_column( $order_metas, '0' ) );

		$is_cdn = $order_metas['_credit_debit'];
		if( $is_cdn ) return true;

		$customer_id = $order_metas['customer_id'];//get_user_meta( $order_id, 'customer_id', true );
		$customer_id = ( $customer_id )? $customer_id : 0;

		$serial = $order_metas['_customer_serial'];//get_post_meta( $order_id, '_customer_serial', true );
		$credit = $order_metas['wc_pos_credit_amount'];//get_post_meta( $order_id, 'wc_pos_credit_amount', true );
		$total = $order_metas['_order_total'];//get_post_meta( $order_id, '_order_total', true );

		$warehouse_id = ( $order_metas['wc_pos_warehouse_id'] )? $order_metas['wc_pos_warehouse_id'] : get_post_meta( $order_id, 'wc_pos_warehouse_id', true );

		$items = $order->get_items();
		$item_arr = [];
		if( $items )
		{	
			foreach( $items as $item_id => $item )
			{
				$item_arr[] = $item_id;
			}
		}

		if( $order_metas['_payment_method'] != 'cod' )
		{  
			$header_item = [ 'doc_type'=>'sales', 'warehouse_id'=> $warehouse_id ];
			$strg_id = apply_filters( 'wcwh_get_system_storage', 0, $header_item, [] );

			$price_logs = apply_filters( 'warehouse_get_items_selling_price', $warehouse_id, $strg_id, $item_arr );
			if( ! $price_logs )
			{
				$request = [
					'warehouse_id' => $warehouse_id,
					'request_promotion_data' => ( $order_metas['request_promotion_data'] )? $order_metas['request_promotion_data'] : get_post_meta( $order_id, 'request_promotion_data', true )
				];
				$this->pos_after_order_creation( $order, $request, true );
			}

			update_post_meta( $order_id, 'wc_pos_amount_pay', $total );

			$succ = apply_filters( 'wcwh_after_pos_order_payment', $order_id, $order, $order_metas );
		}
	}

	public function pos_after_order_failure( $order_id = 0, $order = [] )
	{
		if( $order_id ) $this->pos_after_order_cancelled( $order_id, true );
	}

	public function pos_after_order_cancelled( $order_id = 0, $force = false )
	{
		if( ! $order_id ) return false;

		try{
			$order = new WC_Order( $order_id );
		} 
		catch ( Exception $e ) {
            $order = [];
        }
		
		$setting = $this->setting['pos'];

		//if( ! $order ) return false;

		$order_metas = get_post_meta( $order_id );
		$order_metas = array_combine( array_keys( $order_metas ), array_column( $order_metas, '0' ) );

		update_post_meta( $order_id, 'wc_cancel_date', current_time( 'mysql' ) );

		$is_cdn = $order_metas['_credit_debit'];
		if( $is_cdn ) return true;

		$customer_id = $order_metas['customer_id'];//get_post_meta( $order_id, 'customer_id', true );
		$warehouse_id = $order_metas['wc_pos_warehouse_id'];//get_post_meta( $order_id, 'wc_pos_warehouse_id', true );

		if( ! $customer_id )
		{
			$user_id = $order_metas['_customer_user'];//get_post_meta( $order_id, '_customer_user', true );
			if( $user_id ) $customer_id = get_user_meta( $user_id, 'customer_id', true );
		}

		//promotion
		$promotions = [];
		foreach( $order_metas as $key => $value )
		{	
			if( strpos( $key, 'wc_pos_promotion' ) !== false)
			{
				$val = explode( 'wc_pos_promotion_', $key );
			    $promotions[ $val[1] ] = $value;
			} 
		}
		if( !empty( $promotions ) )
		{
			if ( !class_exists( "WCWH_PromoHeader" ) ) require_once( WCWH_DIR . "/includes/classes/promo-header.php" );
			$Promo = new WCWH_PromoHeader();
			foreach( $promotions as $hid => $used )
			{
				$Promo->update_promo_usage( $hid, $used, '-' );
			}
		}

		$items = [];
		if( $order ) $items = $order->get_items();
		$header_item = [ 'doc_type'=>'sales', 'warehouse_id'=> $warehouse_id ];
		$details = array();	$has_pos_transact = false; $tool_request = [];
		if( $items && ! $force )
		{
			foreach( $items as $item_id => $item )
			{
				$prdt_id = ( $item['variation_id'] )? $item['variation_id'] : $item['product_id'];
				$items_id = ( $item['items_id'] )? $item['items_id'] : get_post_meta( $prdt_id, 'item_id', true );
				$qty = $item->get_quantity();

				$item['tool_item_id'] = ( $item['tool_item_id'] )? $item['tool_item_id'] : wc_get_order_item_meta( $item_id, '_tool_item_id', true );
				if( $item['tool_item_id'] && $order_metas['tool_request_id'] )
				{
					$tool_request[] = [
						'item_id' => $item['tool_item_id'],
						'doc_id' => $order_metas['tool_request_id'],
						'product_id' => $items_id,
						'qty' => $qty,
						'plus_sign' => '-',
					];
				}
					
				$isVirtual = get_items_meta( $items_id, 'virtual', true );
				if( $isVirtual )
				{	
					$itemize_ids = wc_get_order_item_meta( $item_id, '_itemize', true );
					if( $itemize_ids )
					{
						$filters = [ 'id'=>$itemize_ids ];
						$itemize = apply_filters( 'wcwh_get_itemize', $filters, [ 'expiry'=>'ASC', 'id'=>'ASC' ], false, [] );
						if( $itemize )
						{	
							$per_itemize = [];
							foreach( $itemize as $j => $per )
							{
								$per_itemize[] = $per['id'];
								
								$dat = [
									'id' => $per['id'],
									'sales_item_id' => 0,
									'unit_price' => 0,
									'stock' => 1,
								];
								$result = apply_filters( 'wcwh_itemize_handler', 'update', $dat );
							}
						}
					}
				}
					
				$row = array(
					'sales_item_id'   	=> $item_id, 
					'warehouse_id' 		=> $warehouse_id, 
					'strg_id'			=> apply_filters( 'wcwh_get_system_storage', 0, $header_item, $item ),
				);
				$details[] = $row;

				$return_item = get_items_meta( $items_id, 'returnable_item', true );
				if( $return_item > 0 )
				{
					$has_pos_transact = true;
				}

				if( $items_id )
				{
					$is_returnable = get_items_meta( $items_id, 'is_returnable', true );
					if( $is_returnable )
					{
						$add_gt_total = get_items_meta( $items_id, 'add_gt_total', true );
						if( $add_gt_total )
						{
							$gt_added = wc_get_order_item_meta( $item_id, '_gt_total_added', true );
							if( $gt_added )
							{
								$gtd = get_option( 'gt_total', 0 );
								$gtd+= $qty;
								update_option( 'gt_total', $gtd );

								wc_delete_order_item_meta( $item_id, '_gt_total_added' );
							}
						}
					}
				}
			}
		}
		else
		{
			$items = apply_filters( 'warehouse_get_items_selling_price_by_order', $order_id, $warehouse_id );
			if( $items )
			{
				$sp_total = 0;
				foreach( $items as $i => $item )
				{
					$items_id = $item['prdt_id'];
					$qty = $item['qty'];

					if( $item['tool_id'] )
					{
						if( ! $order_metas['tool_request_id'] )
						{
							$tr_row = apply_filters( 'wcwh_get_doc_detail', [ 'item_id'=>$item['tool_id'] ], [], true, [ 'usage'=>1 ] );
							if( $tr_row ) $order_metas['tool_request_id'] = $tr_row['doc_id'];
						}
						$tool_request[] = [
							'item_id' => $item['tool_id'],
							'doc_id' => $order_metas['tool_request_id'],
							'product_id' => $items_id,
							'qty' => $qty,
							'plus_sign' => '-',
						];
					}

					$isVirtual = get_items_meta( $items_id, 'virtual', true );
					if( $isVirtual )
					{	
						$filters = [ 'sales_item_id'=>$item['sales_item_id'], 'status'=>1, 'stock'=>0 ];
						$itemize = apply_filters( 'wcwh_get_itemize', $filters, [ 'expiry'=>'ASC', 'id'=>'ASC' ], false, [] );
						if( $itemize )
						{	
							$per_itemize = [];
							foreach( $itemize as $j => $per )
							{
								$per_itemize[] = $per['id'];
								
								$dat = [
									'id' => $per['id'],
									'sales_item_id' => 0,
									'unit_price' => 0,
									'stock' => 1,
								];
								$result = apply_filters( 'wcwh_itemize_handler', 'update', $dat );
							}
						}
					}

					$row = array(
						'sales_item_id'   	=> $item['sales_item_id'], 
						'warehouse_id' 		=> $item['warehouse_id'], 
						'strg_id'			=> $item['strg_id'],
					);
					$details[] = $row;

					$return_item = get_items_meta( $items_id, 'returnable_item', true );
					if( $return_item > 0 )
					{
						$has_pos_transact = true;
					}

					if( $items_id )
					{
						$is_returnable = get_items_meta( $items_id, 'is_returnable', true );
						if( $is_returnable )
						{
							$add_gt_total = get_items_meta( $items_id, 'add_gt_total', true );
							if( $add_gt_total )
							{
								$gt_added = wc_get_order_item_meta( $item_id, '_gt_total_added', true );
								if( $gt_added )
								{
									$gtd = get_option( 'gt_total', 0 );
									$gtd+= $qty;
									update_option( 'gt_total', $gtd );

									wc_delete_order_item_meta( $item_id, '_gt_total_added' );
								}
							}
						}
					}

					if( ! $warehouse_id ) 
					{
						$warehouse_id = $item['warehouse_id'];
						$header_item = [ 'doc_type'=>'sales', 'warehouse_id'=> $warehouse_id ];
					}
					if( ! $customer_id ) $customer_id = $item['customer'];
					$sp_total+= $item['total_amount'];
				}

				if( ! $order_metas['_order_total'] ) $order_metas['_order_total'] = $sp_total;
			}

			if( ! $order_metas['_customer_serial'] && $customer_id )
			{
				$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$customer_id ], [], true, [ 'member'=>1 ] );
				if( $customer )
				{
					$order_metas['_customer_serial'] = $customer['serial'];
					if( $customer['member_id'] ) $order_metas['membership_id'] = $customer['member_id'];
				}
			}
			if( ! $order_metas['wc_pos_credit_amount'] && $customer_id )
			{
				$credit_type = ( $order_metas['tool_request_id'] > 0 )? 'tools' : 'sales';
				if( $order_metas['membership_id'] ) $credit_type = "debit_sales";
				$credit_log = $this->select( 0, array( 'user_id' => $customer_id, 'order_id' => $order_id, 'type' => $credit_type ) );
				if( $credit_log )
				{
					$order_metas['wc_pos_credit_amount'] = abs( $credit_log['amount'] );
				}
			}
		}

		//afterward header
		$serial = $order_metas['_customer_serial'];//get_post_meta( $order_id, '_customer_serial', true );
		$credit = $order_metas['wc_pos_credit_amount'];//get_post_meta( $order_id, 'wc_pos_credit_amount', true );
		$total = $order_metas['_order_total'];//get_post_meta( $order_id, '_order_total', true );
		if( $customer_id && $credit != 0 )
		{
			if( ! $order_metas['customer_counted'] ) $order_metas['customer_counted'] = get_post_meta( $order_id, 'customer_counted', true );
			if( $order_metas['customer_counted'] || $force )
			{
				$cc = apply_filters( 'wcwh_update_customer_count', $customer_id, $serial, $total, $credit, '-' );

				delete_post_meta( $order_id, 'customer_counted' );
			}
		}
		
		if( $customer_id && $credit != 0 )	//credit registry delete
		{
			$credit_type = ( $order_metas['tool_request_id'] > 0 )? 'tools' : 'sales';
			if( $order_metas['membership_id'] )
			{
				$credit_type = "debit_sales";

				if( ! class_exists( 'WCWH_Membership_Class' ) ) include_once( WCWH_DIR . "/includes/classes/membership.php" ); 
				$Member = new WCWH_Membership_Class();
				$succ = $Member->update_member_credit( $customer_id, $credit, "+" );
			}

			$datas = array();
			$result = $this->select( 0, array( 'user_id' => $customer_id, 'order_id' => $order_id, 'type' => $credit_type ) );
			if( $result ) $datas['id'] = $result['id'];
			
			if( $datas['id'] ) $this->action_handler( 'delete', $datas );
		}

		if( $order_metas['tool_request_id'] && $tool_request )
		{
			$tool_doc = [
				'doc_id' => $order_metas['tool_request_id'],
				'details' => $tool_request,
			];
			if( $order_metas['tool_doc_type'] && $order_metas['tool_doc_type'] == 'parts_request' )
				$succ = apply_filters( 'wcwh_parts_request_completion', $succ, $tool_doc );
			else
				$succ = apply_filters( 'wcwh_tool_request_completion', $succ, $tool_doc );
		}

		//price log delete
		if( $details && $setting['price_log'] )
		{
			$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'delete' , $details );
		}

		// POS transactions (if any)
		if( $succ && $has_pos_transact )
		{
			$reg_id = $order_metas['wc_pos_id_register'];//get_post_meta( $order_id, 'wc_pos_id_register', true );
			$sess_id = $order_metas['_pos_session_id'];//get_post_meta( $order_id, '_pos_session_id', true );

			$filters = [
				'warehouse_id' => $warehouse_id,
				'register' => $reg_id,
				'session' => $sess_id,
				'receipt_id' => $order_id,
				'doc_type' => 'pos_transactions',
			];
			$exists = apply_filters( 'wcwh_get_doc_header', $filters, [], false, [ 'meta'=>[ 'register', 'session', 'receipt_id' ] ] );
			if( $exists )
			{	
				foreach( $exists as $exist )
				{
					$header_item = [ 'id' => $exist['doc_id'] ];
					$result = apply_filters( 'wcwh_pos_transaction' , 'unpost', $header_item , $details );
					if( ! $result['succ'] )
					{
						$succ = false;
					}
					else
					{
						$result = apply_filters( 'wcwh_pos_transaction' , 'delete', $header_item, [] );
						if( ! $result['succ'] )
						{
							$succ = false;
						}
					}
				}
			}
		}

		if( $succ && $order_metas['pos_delivery_doc'] )
		{
			if ( !class_exists( "WCWH_PosDO_Class" ) ) include_once( WCWH_DIR . "/includes/classes/pos-do.php" ); 
			$PosDO = new WCWH_PosDO_Class( $this->db_wpdb );
			$PosDO->setUpdateUqtyFlag( false );

			$pos_do = $PosDO->get_header( [ 'docno' => $order_metas['pos_delivery_doc'] ], [], true, [ 'usage'=>1 ] );

			if($pos_do)
			{
				$pos_do_header = [ 'doc_id'=>$pos_do['doc_id'] ];
				$result = $PosDO->child_action_handle( 'unpost', $pos_do_header, [] );
				if( ! $result['succ'] )
				{
					$succ = false;
				}
				else
				{
					$result = $PosDO->child_action_handle( 'delete', $pos_do_header );
					if( ! $result['succ'] )
					{
						$succ = false;
					}
				}
				
			}
		}

		//remove unused meta
		$unused_keys = [ 'wc_pos_signature', 'wc_pos_prefix_suffix_order_number', 'wc_pos_order_saved', 'wc_pos_dining_option', '_shipping_address_index', '_download_permissions_granted', '_billing_address_index', '_customer_name' ];
		$ord = get_post_meta( $order_id );
		if( $ord )
		{
			foreach( $ord as $meta => $val )
			{
				if( in_array( $meta, $unused_keys ) )
				{
					delete_post_meta( $order_id, $meta );
				}
			}
		}
	}
	
	public function pos_after_order_refund( $refund_id, $order, $request )
	{
		$action = 'save';
		
		if( $order->get_id() )
		{
			$order_id = $order->get_id();
			$user_id = get_post_meta( $order_id, '_customer_user', true );
			$warehouse_id = get_post_meta( $order_id, 'wc_pos_warehouse_id', true );
			$credit = get_post_meta( $order_id, 'wc_pos_credit_amount', true );
			$total = get_post_meta( $order_id, '_order_total', true );

			$customer_id = get_user_meta( $user_id, 'customer_id', true );
			$customer_id = ( $customer_id )? $customer_id : get_post_meta( $order_id, 'customer_id', true );
			
			if( $customer_id && $credit > 0 )
			{
				$parent = $this->select( 0, array( 'order_id' => $order_id, 'type' => 'sales' ) );
				$refunds = $this->selects( 0, array( 'order_id' => $order_id, 'type' => 'refund' ) );
				$refunded = 0;

				if( $refunds )
				{
					foreach( $refunds as $i => $refund )
					{
						$refunded+= $refund['amount'];
					}
				}
				
				$refundable = $credit - $refunded;
				$refund_credit = ( $request['amount'] < $refundable )? $request['amount'] : $refundable;
				
				if( $refundable > 0 )
				{
					$datas = array(
						'user_id' => $customer_id,
						'order_id' => $refund_id,
						'type' => 'refund',
						'amount' => $refund_credit,
						'note' => !empty( $request['reason'] )? $request['reason'] : 'Order Refund',
						'parent' => ( $parent )? $parent['id'] : 0,
						'time' => current_time( 'mysql' )
					);
					
					update_post_meta( $refund_id, '_refund_credit', $refund_credit );
					$refund_amt = get_post_meta( $refund_id, '_refund_amount', true );
					update_post_meta( $refund_id, '_refund_amount', $refund_amt - $refund_credit );
					
					update_post_meta( $refund_id, 'wc_pos_served_by', get_current_user_id() );
					
					$this->action_handler( $action, $datas );
				}
			}
		}
	}

	public function pos_after_order_creation_failed( $order, $request, $creating )
	{
		if( ! $creating || ! $order ) return;

		$order_id = $order->get_id();
		if( $order_id && $order->payment_method == 'cod' && in_array( $order->get_status(), array( 'pending', 'processing' ) ) )
		{
			$order->update_status( 'on-hold' );
		}
	}

	public function pos_verification( $succ, $request, $creating = false )
	{
		$response = [ 'succ'=>$succ ];
		if( ! $request ) return $response;

		$metas = [];
		if( $request['meta_data'] )
			foreach( $request['meta_data'] as $i => $meta )
			{
				$metas[ $meta['key'] ] = $meta['value'];
			}

		//credit validation
		if( $request['customer_id'] > 0 && $metas['wc_pos_credit_amount'] > 0 && ! $metas['tool_request_id'] )
		{
			$user_id = $request['customer_id'];
			$customer_id = get_user_meta( $user_id, 'customer_id', true );

			if( $customer_id )
			{
				$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$customer_id ], [], true, [ 'group'=>1, 'member'=>1, 'meta'=>['sapuid','sapuid_date','last_day'], 'usage'=>1 ] );

				$today = strtotime( date( 'Y-m-d H:i:s' ) );	
				$last_day = strtotime( date( 'Y-m-d', strtotime( date( 'Y-m-d' )." +10 year" ) ) );
				if( !empty( $customer['last_day'] ) )
				{
					$last_day = strtotime( $customer['last_day'] );
				}
				
				if( $customer && 
					( empty( $customer['last_day'] ) || 
						( !empty( $customer['last_day'] ) && $today < $last_day ) 
					) 
				)
				{
					$customer['active'] = true;
				}
				else
				{
					$customer['active'] = false;

					return [ 'succ'=>false, 'message'=>'Customer Last Day Not Allow to Buy.' ];
				}

				if( ! $customer['active'] )
				{
					return [ 'succ'=>false, 'message'=>'Customer Not Allow to Buy.' ];
				}

				if( $metas['membership_id'] && $metas['_debit_deduction'] )
				{
					if( ! $metas['membership_pin'] )
					{
						return [ 'succ'=>false, 'message'=>'6 Digit Pin is required' ];
					}
					else
					{
						$pin = wcwh_simple_decrypt( $metas['membership_pin'] );
						//$hashed = wp_hash_password( $pin );
						if( ! $customer || ! wp_check_password( $pin, $customer['member_pin'] ) )
						{
							return [ 'succ'=>false, 'message'=>'Pin Incorrect.' ];
						}
					}

					$user_debits = $this->get_client_debits( $customer_id, $customer );
					if( $user_debits && ( round( $user_debits['usable_credit'], 2 ) < round( $metas['wc_pos_credit_amount'], 2 ) ) )
					{
						$leftover = round_to( $user_debits['usable_credit'], 2, 1, 1 );
						return [ 'succ'=>false, 'message'=>"Credit Left [D:{$leftover}], Not Enough."  ];
					}
				}
				else
				{
					$user_credits = $this->get_client_credits( $customer_id, $customer );
					if( $user_credits && 
						( round( $user_credits['total_creditable'] +  $user_credits['used_credit'], 2 ) < round( $metas['wc_pos_credit_amount'], 2 ) ) )
					{
						$leftover = round_to( $user_credits['total_creditable'] + $user_credits['used_credit'], 2, 1, 1 );
						return [ 'succ'=>false, 'message'=>"Credit Left [C:{$leftover}], Not Enough."  ];
					}
				}
			}
			
			//return [ 'succ'=>false, 'message'=>'Testing' ];
		}
		
		return $response;
	}

	public function pos_after_close_register( $reg_id = 0, $reg_dat = [] )
	{
		if( ! $reg_id || ! $reg_dat ) return;

		if( ! $reg_dat )
		{
			$data = WC_POS()->register()->get_data( $register_id );
			$reg_dat = $data ? $data[0] : array();
		}

		if( ! $reg_dat['opened'] || ! $reg_dat['detail']['assigned_warehouse'] ) return;

		global $wpdb;

		$cond = $wpdb->prepare( "AND sp.sales_date >= %s ", $reg_dat['opened'] );
		$cond.= $wpdb->prepare( "AND sp.warehouse_id = %s ", $reg_dat['detail']['assigned_warehouse'] );

		$sql1 = "SELECT sp.order_id, sp.docno, sp.warehouse_id, sp.strg_id, sp.customer, sp.sales_date, sp.tool_id
			FROM {$this->tables['selling_price']} sp 
			LEFT JOIN {$this->tables['order']} o ON o.ID = sp.order_id
			WHERE 1 AND o.post_status NOT IN ( 'wc-processing', 'wc-completed' ) AND o.post_type ='shop_order'
			AND sp.status > 0 {$cond}
			GROUP BY sp.order_id ";

		$sql2 = "SELECT sp.order_id, sp.docno, sp.warehouse_id, sp.strg_id, sp.customer, sp.sales_date, sp.tool_id
			FROM {$this->tables['selling_price']} sp 
			LEFT JOIN {$this->tables['order']} o ON o.ID = sp.order_id 
			WHERE 1 AND sp.status > 0 {$cond}
			AND o.ID IS NULL
			GROUP BY sp.order_id ";

		$sql = "SELECT a.* 
			FROM ( ( {$sql1} ) UNION ALL ( {$sql2} ) ) a 
			WHERE 1 GROUP BY a.order_id ";

		$results = $wpdb->get_results( $sql , ARRAY_A );
		if( $results )
		{
			foreach( $results as $i => $ord )
			{
				$this->pos_after_order_cancelled( $ord['order_id'], true );
			}
		}
	}

	/*public function pos_after_order_creation_resque( $order, $request = [], $creating = true )
	{
		$action = ( $creating )? 'save' : 'update';
		$setting = $this->setting['pos'];
		
		if( $order->get_id() && in_array( $order->get_status(), array( 'processing', 'completed' ) ) )
		{
			$succ = true;
			$order_id = $order->get_id();
			$customer_id = get_post_meta( $order_id, 'customer_id', true );
			$request['warehouse_id'] = get_post_meta( $order_id, 'wc_pos_warehouse_id', true );
			
			$items = $order->get_items();
			$header_item = [ 'doc_type'=>'sales', 'warehouse_id'=> $request['warehouse_id'] ];
			if( $creating && $setting['price_log'] )		//price log
			{
				$details = array();
				foreach( $items as $item_id => $item )
				{
					//$uprice = $order->get_item_subtotal( $item, $order->prices_include_tax, true );
					//$prdt_id = ( $item['variation_id'] )? $item['variation_id'] : $item['product_id'];
					//$prdt_id = get_post_meta( $prdt_id, 'item_id', true );

					$row = array(
						'sales_item_id'   	=> $item_id, 
						'warehouse_id' 		=> $request['warehouse_id'], 
						'strg_id'			=> apply_filters( 'wcwh_get_system_storage', 0, $header_item, $item ),
						'customer' 			=> $customer_id,
						'sales_date' 		=> $order->order_date, 
						'prdt_id' 			=> $item['items_id'],
						'uom'				=> $item['uom'],
						'qty' 				=> $item->get_quantity(),
						'unit'				=> ( $item['unit'] )? $item->get_quantity() * $item['unit'] : 0,
						'uprice' 			=> $item['uprice'],
						'price' 			=> $item['price'],
						'total_amount' 		=> $item['price'] * $item->get_quantity(),
					);
					$details[] = $row;
				}
				
				$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'save' , $details );
			}
			
			if( $succ && $setting['pos_transaction'] )	// stock allocation
			{
				$reg_id = get_post_meta( $order_id, 'wc_pos_id_register', true );
				$sess_id = get_post_meta( $order_id, '_pos_session_id', true );

				$header_item = [ 'warehouse_id'=> $request['warehouse_id'], 'register'=>$reg_id, 'session'=>$sess_id ];
				$filters = [
					'warehouse_id' => $request['warehouse_id'],
					'register' => $reg_id,
					'session' => $sess_id,
					'doc_type' => 'pos_transactions',
					'status' => 1,
				];
				$exist = apply_filters( 'wcwh_get_doc_header', $filters, [], true, [ 'meta'=>[ 'register', 'session' ] ] );

				$details = array();
				foreach( $items as $item_id => $item )
				{
					//$uprice = $order->get_item_subtotal( $item, $order->prices_include_tax, true );
					//$prdt_id = ( $item['variation_id'] )? $item['variation_id'] : $item['product_id'];
					//$prdt_id = get_post_meta( $prdt_id, 'item_id', true );

					$row = array(
						'product_id' 		=> $item['items_id'],
						'uom_id'			=> $item['uom'],
						'bqty' 				=> $item->get_quantity(),
						'bunit'				=> ( $item['unit'] )? $item->get_quantity() * $item['unit'] : 0,
						'ref_doc_id' 		=> $order_id,
						'ref_item_id' 		=> $item_id,
						//Custom Field 
						'uprice' 			=> $item['uprice'],
						'price'				=> $item['price'],
						'total_amount' 		=> $item['price'] * $item->get_quantity(),
					);	

					$details[] = $row;
				}

				if( $exist )
				{	
					$header_item['doc_id'] = $exist['doc_id'];
					$result = apply_filters( 'wcwh_pos_transaction' , 'update-item', $header_item , $details );
				}
				else
				{
					$result = apply_filters( 'wcwh_pos_transaction' , 'save', $header_item , $details );
				}
				if( ! $result['succ'] )
				{
					$succ = false;
				}
			}
		}
	}*/
	
} //class

new WC_POS_CreditLimit_Class();
}