<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_User_Class" ) ) 
{

class WCWH_User_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_maintain_user";

	protected $tables = array();

	public $Notices;
	public $className = "User_Class";

	protected $warehouse = array();

	protected $excl_roles = [];

	protected $usable_roles = [];

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

	public function set_excl_roles( $excl_roles )
	{
		$this->excl_roles = $excl_roles;
	}

	public function set_usable_roles( $roles )
	{
		$this->usable_roles = $roles;
	}

	public function set_db_tables()
	{
		global $wcwh, $wpdb;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"user"			=> $wpdb->users,
			"usermeta"		=> $wpdb->usermeta,

			"status"		=> $prefix."status",
		);
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

		$field = "a.ID AS id, a.user_login, a.user_pass, a.user_email, a.display_name, a.user_registered AS created_at, a.user_modified_gmt AS lupdate_at 
			, ma.meta_value AS name, mc.meta_value AS role, md.meta_value AS nickname, me.meta_value AS last_login
			, IF( SUBSTRING( mc.meta_value, 3, 1 ) > 0, 1, 0 ) AS status ";
			
		//-----------------------------------04/10/22
		$field .= ",mf.meta_value AS start_date, mg.meta_value AS end_date ";		
		//----------------------------------04/10/22

		$table = "{$this->tables['user']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$table.= "LEFT JOIN {$this->tables['usermeta']} ma ON ma.user_id = a.ID AND ma.meta_key = 'first_name' ";
		$table.= "LEFT JOIN {$this->tables['usermeta']} mb ON mb.user_id = a.ID AND mb.meta_key = 'last_name' ";
		$table.= "LEFT JOIN {$this->tables['usermeta']} mc ON mc.user_id = a.ID AND mc.meta_key = '{$wpdb->prefix}capabilities' ";
		$table.= "LEFT JOIN {$this->tables['usermeta']} md ON md.user_id = a.ID AND md.meta_key = 'nickname' ";
		$table.= "LEFT JOIN {$this->tables['usermeta']} me ON me.user_id = a.ID AND me.meta_key = 'last_login' ";
		
		//-----------------------------------04/10/22
		$table.= "LEFT JOIN {$this->tables['usermeta']} mf ON mf.user_id = a.ID AND mf.meta_key = 'start_date' ";
		$table.= "LEFT JOIN {$this->tables['usermeta']} mg ON mg.user_id = a.ID AND mg.meta_key = 'end_date' ";
		//-----------------------------------04/10/22

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
		if( ! ( isset( $filters['id'] ) || isset( $filters['not_id'] ) ) && isset( $filters['role'] ) )
		{
			$filters['role'] = is_array( $filters['role'] )? $filters['role'] : [ $filters['role'] ];
			$cd = [];
			foreach( $filters['role'] as $role )
			{
				if( $role != 'no_role' ) $cd[] = "mc.meta_value LIKE '%".$role."%' ";
				else $cd[] = "mc.meta_value LIKE 'a:0%' ";
			}
			if( !empty( $cd ) ) $cond.= "AND ( ".implode( " OR ", $cd )." )";
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
                $cd[] = "a.user_login LIKE '%".$kw."%' ";
                $cd[] = "a.user_email LIKE '%".$kw."%' ";
				$cd[] = "a.display_name LIKE '%".$kw."%' ";
				$cd[] = "ma.meta_value LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		//status
        if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
        {
            unset( $filters['status'] );
        }
        if( isset( $filters['status'] ) )
        {   
        	if( $filters['status'] > 0 )
            	$cond.= $wpdb->prepare( "AND SUBSTRING( mc.meta_value, 3, 1 ) > %d ", 0 );
            else
            	$cond.= $wpdb->prepare( "AND SUBSTRING( mc.meta_value, 3, 1 ) <= %d ", 0 );
        }

        $corder = array();
        if( $args['stat'] )
        {
        	$table.= "LEFT JOIN {$this->tables['status']} flag ON flag.status = IF( SUBSTRING( mc.meta_value, 3, 1 ) > 0, 1, 0 ) AND flag.type = 'flag' ";
        	$corder["flag.order"] = "ASC";
        }

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.ID' => 'ASC' ];
			if( $corder ) $order = array_merge( $corder, $order );
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

		$roles = ( $this->usable_roles )? array_keys( $this->usable_roles ) : [];

		$fld = "'all' AS status, COUNT( a.ID ) AS count ";
		$tbl = "{$this->tables['user']} a ";
		$tbl.= "LEFT JOIN {$this->tables['usermeta']} ma ON ma.user_id = a.ID AND ma.meta_key = '{$wpdb->prefix}capabilities' ";
		$cond = "";
		if( $roles )
		{
			$cd = [];
			foreach( $roles as $role )
			{
				$cd[] = "ma.meta_value LIKE '%".$role."%' ";
			}
			$cd[] = "ma.meta_value LIKE 'a:0%' ";
			if( !empty( $cd ) ) $cond.= "AND ( ".implode( " OR ", $cd )." )";
		}
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "'1' AS status, COUNT( a.ID ) AS count ";
		$tbl = "{$this->tables['user']} a ";
		$tbl.= "LEFT JOIN {$this->tables['usermeta']} ma ON ma.user_id = a.ID AND ma.meta_key = '{$wpdb->prefix}capabilities' ";
		$cond = "";
		if( $roles )
		{
			$cd = [];
			foreach( $roles as $role )
			{
				$cd[] = "ma.meta_value LIKE '%".$role."%' ";
			}
			if( !empty( $cd ) ) $cond.= "AND ( ".implode( " OR ", $cd )." )";
		}
		$sql2 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$fld = "'0' AS status, COUNT( a.ID ) AS count ";
		$tbl = "{$this->tables['user']} a ";
		$tbl.= "LEFT JOIN {$this->tables['usermeta']} ma ON ma.user_id = a.ID AND ma.meta_key = '{$wpdb->prefix}capabilities' ";
		$cond = "";
		if( $roles )
		{
			$cd = [];
			$cd[] = "ma.meta_value LIKE 'a:0%' ";
			if( !empty( $cd ) ) $cond.= "AND ( ".implode( " OR ", $cd )." )";
		}
		$sql3 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$sql = $sql1." UNION ALL ".$sql2." UNION ALL ".$sql3;

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

	public function get_roles( $role = '' )
	{
		global $wp_roles;

    	$roles = $wp_roles->roles;
    	
    	$r = [];
    	if( $roles )
    	{
    		$i = 0;
    		foreach( $roles as $key => $vals )
    		{
    			if( $role && $role == $key )
    			{
    				$vals['role'] = $key;
    				$vals['id'] = $key;
    				return $vals;
    			}

    			$r[$i] = $vals;
    			$r[$i]['role'] = $key;
    			$r[$i]['id'] = $key;

    			$i++;
    		}
    	}

    	return $r;
	}
	
} //class

}