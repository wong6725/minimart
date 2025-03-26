<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_POSCDN_Class" ) ) 
{

class WCWH_POSCDN_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_pos_cdn";

	protected $tables = array();

	public $Notices;
	public $className = "POSCDN_Class";

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
		global $wcwh, $wpdb;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"order" 		=> $wpdb->posts,
			"ordermeta"		=> $wpdb->postmeta,
			"order_item"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"product"		=> $wpdb->posts,
			"productmeta"	=> $wpdb->postmeta,
			
			"user"			=> $wpdb->users,
			"usermeta"		=> $wpdb->usermeta,

			"price_log"		=> $prefix."selling_price",
			"credit_log"	=> $wpdb->prefix."wc_poin_of_sale_credit_registry",

			"items"			=> $prefix."items",
			"itemmeta"		=> $prefix."itemsmeta",
			"uom"			=> $prefix."uom",

			"itemize"		=> $prefix."itemize",
			"itemizemeta"	=> $prefix."itemizemeta",

			"reprocess_item"	=> $prefix."reprocess_item",

			"customer"		=> $prefix."customer",

			"pos_registers"	=> $wpdb->prefix."wc_poin_of_sale_registers",
			"pos_sessions"	=> $wpdb->prefix."wc_point_of_sale_sessions",

			"status"		=> $prefix."status",
		);
	}

	public function add_price_log( $params = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$wpdb->insert(
			$this->tables['price_log'],
			array(
				'sales_item_id' 	=> $params['sales_item_id'],
				'warehouse_id' 		=> $params['warehouse_id'],
				'strg_id' 			=> $params['strg_id'],
				'customer' 			=> $params['customer'],
				'sales_date' 		=> $params['sales_date'],
				'prdt_id'			=> $params['prdt_id'],
				'uom' 				=> $params['uom'],
				'qty'				=> $params['qty'],
				'unit'				=> $params['unit'],
				'uprice' 			=> $params['uprice'],
				'price' 	    	=> $params['price'],
				'total_amount' 		=> $params['total_amount'],
				'status'			=> 1,
			)
		);
		$item_id = absint( $wpdb->insert_id );

		return $item_id;
	}

	public function update_price_log( $cond = [] , $params = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if ( ! $cond || ! $params ) return false;
		
		$update = $wpdb->update( $this->tables['price_log'], $params, $cond );

		if ( false === $update ) return false;
		
		return true;
	}

	public function get_credit_registry( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		else
		{
			$dbname = $this->dbName();
		}

        $field = "a.* ";
		$table = "{$dbname}{$this->tables['credit_log']} a ";
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
		if( isset( $filters['user_id'] ) )
		{
			if( is_array( $filters['user_id'] ) )
				$cond.= "AND a.user_id IN ('" .implode( "','", $filters['user_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.user_id = %s ", $filters['user_id'] );
		}
		if( isset( $filters['order_id'] ) )
		{
			if( is_array( $filters['order_id'] ) )
				$cond.= "AND a.order_id IN ('" .implode( "','", $filters['order_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.order_id = %s ", $filters['order_id'] );
		}
		if( isset( $filters['type'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.type = %s ", $filters['type'] );
		}
		if( isset( $filters['parent'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.parent = %d ", $filters['parent'] );
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

	public function add_credit_registry( $params = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$wpdb->insert(
			$this->tables['credit_log'],
			array(
				'user_id' 	=> $params['user_id'],
				'order_id' => $params['order_id'],
				'type' 		=> $params['type'],
				'amount' 	=> $params['amount'],
				'note' 		=> $params['note'],
				'parent'	=> $params['parent'],
				'time' 		=> $params['time'],
			)
		);
		$item_id = absint( $wpdb->insert_id );

		return $item_id;
	}

	public function update_credit_registry( $cond = [] , $params = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if ( ! $cond || ! $params ) return false;
		
		$update = $wpdb->update( $this->tables['credit_log'], $params, $cond );

		if ( false === $update ) return false;
		
		return true;
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
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		else
		{
			$dbname = $this->dbName();
		}

		$field = "a.ID AS id, ma.meta_value AS order_no, a.post_date AS order_date, a.post_status AS order_status, a.post_author AS created 
			, c.id AS customer_id, c.name AS customer_name, c.code AS customer_code, c.uid AS employee_no, mg.meta_value AS book_qr
			, IF( tr.meta_value > 0, 'tool', 'grocery' ) AS order_type, tr.meta_value AS tool_request_id 
			, ref.meta_value AS ref_doc_id
			, COUNT( oi.order_item_id ) AS item, me.meta_value AS total, mf.meta_value AS total_credit
			, mi.meta_value AS amt_paid, mj.meta_value AS amt_change
			, mc.meta_value AS register, mb.meta_value AS session_id, mk.meta_value AS wh_id, ml.meta_value AS order_comments 
			, mm.meta_value AS cancel_remark, mn.meta_value AS payment_method, mo.meta_value AS payment_method_title
		";
		$table = "{$dbname}{$this->tables['order']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} ma ON ma.post_id = a.ID AND ma.meta_key = '_order_number' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mb ON mb.post_id = a.ID AND mb.meta_key = '_pos_session_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mc ON mc.post_id = a.ID AND mc.meta_key = 'wc_pos_id_register' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} md ON md.post_id = a.ID AND md.meta_key = 'customer_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} me ON me.post_id = a.ID AND me.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mf ON mf.post_id = a.ID AND mf.meta_key = 'wc_pos_credit_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mg ON mg.post_id = a.ID AND mg.meta_key = '_customer_serial' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mh ON mh.post_id = a.ID AND mh.meta_key = 'wc_pos_rounding_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mi ON mi.post_id = a.ID AND mi.meta_key = 'wc_pos_amount_pay' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mj ON mj.post_id = a.ID AND mj.meta_key = 'wc_pos_amount_change' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mk ON mk.post_id = a.ID AND mk.meta_key = 'wc_pos_warehouse_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} ml ON ml.post_id = a.ID AND ml.meta_key = 'order_comments' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mm ON mm.post_id = a.ID AND mm.meta_key = 'cancel_remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mn ON mn.post_id = a.ID AND mn.meta_key = '_payment_method' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} mo ON mo.post_id = a.ID AND mo.meta_key = '_payment_method_title' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} tr ON tr.post_id = a.ID AND tr.meta_key = 'tool_request_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} ref ON ref.post_id = a.ID AND ref.meta_key = 'ref_doc_id' ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['customer']} c ON c.id = a.post_content ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['order_item']} oi ON oi.order_id = a.ID AND oi.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} ima ON ima.order_item_id = oi.order_item_id AND ima.meta_key = '_items_id' ";

		$cond.= $wpdb->prepare( "AND a.post_type = %s ", "shop_order" );

		if( $args['meta'] )
		{
			foreach( $args['meta'] as $meta_key )
			{
				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['ordermeta']} {$meta_key} ON {$meta_key}.post_id = a.ID AND {$meta_key}.meta_key = %s ", $meta_key );

				if( isset( $filters[$meta_key] ) )
				{
					if( is_array( $filters[$meta_key] ) )
						$cond.= "AND {$meta_key}.meta_value IN ('" .implode( "','", $filters[$meta_key] ). "') ";
					else
					{
						if( $filters[$meta_key] == 'IS_NULL' )
						{
							$cond.= "AND ( {$meta_key}.meta_value IS NULL OR {$meta_key}.meta_value = '' ) ";
						}
						else if( $filters[$meta_key] == 'IS_NOT_NULL' )
						{
							$cond.= "AND {$meta_key}.meta_value IS NOT NULL ";
						}
						else
						{
							$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
						}
					}
				}
			}
		}

		if( isset( $filters['cd_note'] ) && $filters['cd_note'] )
		{
			$table.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} cd ON cd.post_id = a.ID AND cd.meta_key = '_credit_debit' ";
			$cond.= "AND cd.meta_value > 0 ";
		}

		if( isset( $filters['id'] ) )
		{
			if( is_array( $filters['id'] ) )
				$cond.= "AND a.ID IN ('" .implode( "','", $filters['id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.ID = %s ", $filters['id'] );
		}
		if( isset( $filters['not_id'] ) )
		{
			if( is_array( $filters['not_id'] ) )
				$cond.= "AND a.ID NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.ID != %s ", $filters['not_id'] );
		}
		if( isset( $filters['docno'] ) )
		{
			$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['docno'] );
		}
		if( isset( $filters['customer'] ) )
		{
			if( $filters['customer'] == 'guest' )
				$cond.= $wpdb->prepare( "AND ( c.id IS NULL OR c.id = %s ) ", '0' );
			else
			{
				if( is_array( $filters['customer'] ) )
					$cond.= "AND c.id IN ('" .implode( "','", $filters['customer'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND c.id = %s ", $filters['customer'] );
			}
		}
		if( isset( $filters['product_id'] ) )
        {
            if( is_array( $filters['product_id'] ) )
				$cond.= "AND ima.meta_value IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND ima.meta_value = %s ", $filters['product_id'] );
        }
        if( isset( $filters['order_type'] ) )
        {
        	if( $filters['order_type'] == 'grocery' )
        	{
        		$cond.= "AND ( tr.meta_value IS NULL OR tr.meta_value = '' ) ";
        	}
        	else if( $filters['order_type'] == 'tool' )
        	{
        		$cond.= "AND tr.meta_value > 0 ";
        	}
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
                $cd[] = "a.post_excerpt LIKE '%".$kw."%' ";
                $cd[] = "mb.meta_value LIKE '%".$kw."%' ";
				//$cd[] = "c.code LIKE '%".$kw."%' ";
				//$cd[] = "c.name LIKE '%".$kw."%' ";
				//$cd[] = "c.uid LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

			unset( $filters['status'] );
		}

        //status
        if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
        {
            unset( $filters['status'] );
            $cond.= $wpdb->prepare( "AND a.post_status != %s ", "-1" );
        }
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.post_status = %s ", $filters['status'] );
        }

		//group
		$group = [ 'a.id' ];
		if( ! empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.id' => 'DESC' ];
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

	public function get_details( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		else
		{
			$dbname = $this->dbName();
		}

		$field = "a.order_item_id AS id, a.order_item_name, a.order_item_type, a.order_id 
			, ma.meta_value AS item_id, i.code AS item_code, i.name AS item_name
			, i._uom_code AS uom, uom.fraction AS uom_fraction 
			, IF( ima.meta_value > 0, 1, 0 ) AS required_unit
			, mc.meta_value AS qty, ROUND( mc.meta_value * md.meta_value, 3 ) AS metric 
			, me.meta_value AS uprice, mf.meta_value AS price, mi.meta_value AS price_code, mj.meta_value AS tool_item_id 
			, mg.meta_value AS subtotal, mh.meta_value AS total
		";
		$table = "{$dbname}{$this->tables['order_item']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = ""; 

		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} ma ON ma.order_item_id = a.order_item_id AND ma.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} mb ON mb.order_item_id = a.order_item_id AND mb.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} mc ON mc.order_item_id = a.order_item_id AND mc.meta_key = '_qty' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} md ON md.order_item_id = a.order_item_id AND md.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} me ON me.order_item_id = a.order_item_id AND me.meta_key = '_uprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} mf ON mf.order_item_id = a.order_item_id AND mf.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} mg ON mg.order_item_id = a.order_item_id AND mg.meta_key = '_line_subtotal' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} mh ON mh.order_item_id = a.order_item_id AND mh.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} mi ON mi.order_item_id = a.order_item_id AND mi.meta_key = '_price_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} mj ON mj.order_item_id = a.order_item_id AND mj.meta_key = '_tool_item_id' ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = ma.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['uom']} uom ON uom.code = i._uom_code ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['itemmeta']} ima ON ima.items_id = i.id AND ima.meta_key = 'inconsistent_unit' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['reprocess_item']} rep ON rep.items_id = ma.meta_value AND rep.status > 0 ";

		$cond.= $wpdb->prepare( "AND a.order_item_type = %s ", "line_item" );

		if( isset( $filters['id'] ) )
		{
			if( is_array( $filters['id'] ) )
				$cond.= "AND a.order_item_id IN ('" .implode( "','", $filters['id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.order_item_id = %s ", $filters['id'] );
		}
		if( isset( $filters['not_id'] ) )
		{
			if( is_array( $filters['not_id'] ) )
				$cond.= "AND a.order_item_id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.order_item_id != %s ", $filters['not_id'] );
		}
		if( isset( $filters['order_id'] ) )
        {
            if( is_array( $filters['order_id'] ) )
				$cond.= "AND a.order_id IN ('" .implode( "','", $filters['order_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.order_id = %s ", $filters['order_id'] );
        }
		if( isset( $filters['product_id'] ) )
        {
            if( is_array( $filters['product_id'] ) )
				$cond.= "AND ma.meta_value IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['product_id'] );
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
                $cd[] = "a.order_item_name LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

			unset( $filters['status'] );
		}

		//group
		if( ! empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.order_id' => 'DESC', 'a.order_item_id' => 'ASC' ];
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

	public function wc_pos_get_register( $id = 0, $seller = 0 )
	{
	    global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$dbname = $this->dbName();

		if( $seller )
		{
			$dbname = get_warehouse_meta( $seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		else
		{
			$dbname = $this->dbName();
		}

		$field = " a.* ";
		$table = "{$dbname}{$this->tables['pos_registers']} a ";
		$cond = "";

		if( $id )
		{
			$cond.= $wpdb->prepare( "AND a.ID = %s ", $id );
		}

	    $sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond};";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $id > 0 && count( $results ) > 0 )
		{
			$results = $results[0];
		}

		return $results;
	}

	public function count_statuses()
	{
		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$fld = "'all' AS status, COUNT( a.post_status ) AS count ";
		$tbl = "{$dbname}{$this->tables['order']} a ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} cd ON cd.post_id = a.ID AND cd.meta_key = '_credit_debit'";
		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status != %s AND cd.meta_value > 0 ", "shop_order", "-1" );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "a.post_status AS status, COUNT( a.post_status ) AS count ";
		$tbl = "{$dbname}{$this->tables['order']} a ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['ordermeta']} cd ON cd.post_id = a.ID AND cd.meta_key = '_credit_debit'";
		$cond = $wpdb->prepare( "AND a.post_type = %s AND a.post_status != %s AND cd.meta_value > 0 ", "shop_order", "-1" );
		$group = "GROUP BY a.post_status ";
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
	
} //class

}