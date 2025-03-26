<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TODO_Class" ) ) 
{

class WCWH_TODO_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_todo";

	protected $tbl = "todo";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "TODO_Class";

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
		global $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"todo" 			=> $prefix.$this->tbl,
			"todo_action"	=> $prefix.$this->tbl."_action",
			"arrangement"	=> $prefix."todo_arrangement",
			"stage_header"	=> $prefix."stage_header",
			"stage_details"	=> $prefix."stage_details",
			"section"		=> $prefix."section",
			"status"    	=> $prefix."status",
		);
	}
	
	public function action_handler( $action, $datas = array(), $metas = array(), $obj = array() )
	{
		$succ = true;

		if( ! $action || ! $datas )
		{
			$succ = false;
		}

		$outcome = array();
		
		if( $succ )
		{
			$exist = array();

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

	                if( $succ && $deleted )
	                {
	                    //
	                }
				break;
			}
		}
		
		$outcome['succ'] = $succ; 
		$outcome['data'] = $datas;

		return $this->after_handler( $outcome, $action , $datas, $metas, $obj );
	}

	public function update_section_document_status( $id = 0, $section = "", $status = 0 )
	{
		if( ! $id || ! $section ) return false;

		$section = get_section( $section );
		if( ! $section ) return false;
		
		$result = $this->rawUpdate( [ 'status' => $status ], [ 'id' => $id ], $section['table'] );
		if ( false === $result )
			return false;

		return true;
	}

	public function get_infos( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$field = "a.* ";
		$table = "{$this->tables['todo']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$isArr = ( $args && $args['arrangement'] )? true : false;
		$isAction = ( $args && $args['action'] )? true : false;
		$isSection = ( $args && $args['section'] )? true : false;

		//join arrangement
		if( $isArr || $isSection )
		{
			$field.= ", b.section, b.match_status, b.match_proceed, b.match_halt, b.action_type, b.title, b.desc, b.order ";
			$table.= "LEFT JOIN {$this->tables['arrangement']} b ON b.id = a.arr_id ";
		}

		//join action
		if( $isAction )
		{
			$field.= ", c.next_action, c.responsible, c.trigger_action ";
			$table.= "LEFT JOIN {$this->tables['todo_action']} c ON c.id = a.action_taken ";
		}

		//join section
		if( $isSection )
		{
			$field.= ", d.table, d.desc AS section_name ";
			$table.= "LEFT JOIN {$this->tables['section']} d ON d.section_id = b.section ";
		}
		
		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value === "" || $value === null ) unset( $filters[$key] );
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
			$cond.= $wpdb->prepare( "AND a.arr_id = %d ", $filters['arr_id'] );
		}
		if( isset( $filters['ref_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.ref_id = %s ", $filters['ref_id'] );
		}
		if( isset( $filters['docno'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.docno = %s ", $filters['docno'] );
		}
		if( isset( $filters['doc_title'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_title = %s ", $filters['doc_title'] );
		}
		if( isset( $filters['action_taken'] ) )
		{	
			$cond.= $wpdb->prepare( "AND a.action_taken = %s ", $filters['action_taken'] );
		}
		if( isset( $filters['action_by'] ) )
		{	
			$cond.= $wpdb->prepare( "AND a.action_by = %d ", $filters['action_by'] );
		}
		if( $isArr && isset( $filters['section'] ) )
		{	
			$cond.= $wpdb->prepare( "AND b.section = %s ", $filters['section'] );
		}
		if( $isArr && isset( $filters['action_type'] ) )
		{	
			$cond.= $wpdb->prepare( "AND b.action_type = %s ", $filters['action_type'] );
		}
		if( $isArr && isset( $filters['s'] ) )
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
				$cd[] = "a.doc_title LIKE '%".$kw."%' ";
				$cd[] = "b.desc LIKE '%".$kw."%' ";

				if( $isSection ) $cd[] = "d.desc LIKE '%".$kw."%' ";

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

            $table.= "LEFT JOIN {$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.status = %s ", $filters['status'] );
        }
        //flag
        if( isset( $filters['flag'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.flag = %s ", $filters['flag'] );
        }
        $table.= "LEFT JOIN {$this->tables['status']} flag ON flag.status = a.flag AND flag.type = 'flag' ";
        $corder["flag.order"] = "DESC";

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

	public function get_arrangement( $args = array() )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;

		$field = "a.* ";
		$table = "{$this->tables['arrangement']} a ";
		$cond = "";

		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				if( is_array( $val ) )
					$cond .=  "AND {$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( "AND {$key} = %s ", $val );
			}
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ;";

		return $wpdb->get_results( $sql , ARRAY_A );
	}

	public function check_todo_arrangement( $id = 0, $section = "" )
	{	
		if( ! $id || ! $section ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;

		$field = "a.id AS stage_id, a.ref_id ";
		$table = "{$this->tables['stage_header']} a ";

		$field.= ", b.id AS arr_id, b.section, b.match_status, b.match_proceed, b.match_halt, b.action_type, b.title, b.desc, b.order ";
		$table.= "RIGHT JOIN {$this->tables['arrangement']} b ON b.section = a.ref_type AND b.match_status = a.status AND b.match_proceed = a.proceed_status AND b.match_halt = a.halt_status ";

		$table.= "LEFT JOIN {$this->tables['todo']} c ON c.arr_id = b.id AND c.ref_id = a.ref_id ";

		$cond = $wpdb->prepare( "AND b.status != %d ", 0 );
		$cond.= $wpdb->prepare( "AND ( c.id IS NULL OR ( c.id > 0 && c.status = 0 ) OR ( c.id > 0 && c.flag = 1 ) ) AND b.section = %s AND a.ref_id = %s ", $section, $id );

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ;";

		$results = $wpdb->get_results( $sql , ARRAY_A );
		if( count( $results ) > 0 )
			return $results[0];

		return false;
	}

	public function todo_title_replacer( $title = "", $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;

		$find = [];
		$replace = [];
		$find['action_type'] = '{action_type}';
		$replace['action_type'] = $this->refs['action_type'][ $args['action_type'] ];

		if( $args['section'] )
		{
			$sql = $wpdb->prepare( "SELECT * FROM {$this->tables['section']} WHERE section_id = %s ", $args['section'] );
			$section = $this->rawSelect( $sql );
			
			if( $section )
			{
				$find['section'] = '{section}';
				$replace['section'] = $section['desc'];

				$prefix = $this->get_prefix();
				$tbl = $prefix.$section['table'];
				$primary_key = ( $section['table_key'] )?  $section['table_key'] : 'id';
				$sql = $wpdb->prepare( "SELECT * FROM {$tbl} WHERE {$primary_key} = %s ;", $args['ref_id'] );
				$result = $this->rawSelect( $sql );
				
				if( $result )
				{
					$find['custno'] = '{custno}'; 	$replace['custno'] = ( $result['custno'] )? $result['custno'] : '';
					$find['code'] = '{code}'; 		$replace['code'] = ( $result['code'] )? $result['code'] : '';
					$find['regno'] = '{regno}'; 	$replace['regno'] = ( $result['regno'] )? $result['regno'] : '';
					$find['name'] = '{name}'; 		$replace['name'] = ( $result['name'] )? $result['name'] : '';
					$find['docno'] = '{docno}'; 	$replace['docno'] = ( $result['docno'] )? $result['docno'] : '';
					$find['sdocno'] = '{sdocno}'; 	$replace['sdocno'] = ( $result['sdocno'] )? $result['sdocno'] : '';
					$find['serial'] = '{serial}'; 	$replace['serial'] = ( $result['serial'] )? $result['serial'] : '';
				}
			}
		}
		
		$title = !empty( $title )? str_replace( $find, $replace, $title ) : '';

		return $title;
	}

	public function get_todo( $type = "approval", $filters = [] )
	{
		global $wcwh, $current_user;
		$wpdb = $this->db_wpdb;
		$permissions = array();

		if( ! $current_user ) return false;

		$user_role = $current_user->roles[0];
		$user_id = $current_user->ID;

		$Right = new WCWH_Permission_Class();
		$results = $Right->get_permission( $user_role, $user_id, 'max', true );
		if( $results )
		{
			$permissions = is_json( $results['permission'] )? json_decode( $results['permission'], true ) : array();
		}

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value === "" || $value === null ) unset( $filters[$key] );
			}
		}

		$field = "a.id, a.arr_id, a.ref_id, a.docno, a.doc_title, a.remark, a.created_by, a.created_at ";
		$table = "{$this->tables['todo']} a ";

		$field.= ", b.section, b.action_type, b.title, b.desc, b.order ";
		$table.= "LEFT JOIN {$this->tables['arrangement']} b ON b.id = a.arr_id ";

		$field.= ", c.table, c.table_key, c.desc AS section_name ";
		$table.= "LEFT JOIN {$this->tables['section']} c ON c.section_id = b.section ";

		$cdTbl = "{$this->tables['todo']} i ";
		$cdTbl.= "LEFT JOIN {$this->tables['todo_action']} j ON j.arr_id = i.arr_id ";
		$cd = $wpdb->prepare( "AND i.action_taken = %s ", 0 );
		if( $permissions ) $cd.= "AND j.responsible IN ( '" .implode( "', '", $permissions ). "' ) ";
		$cdSql = "SELECT DISTINCT i.id FROM {$cdTbl} WHERE 1 {$cd} ";

		$cond = "AND a.id IN ( {$cdSql} ) ";
		$cond.= $wpdb->prepare( "AND b.action_type = %s AND a.status != %d ", $type, 0 );

		if( isset( $filters['arr_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.arr_id = %d ", $filters['arr_id'] );
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
				$cd[] = "a.doc_title LIKE '%".$kw."%' ";
				$cd[] = "a.remark LIKE '%".$kw."%' ";
				$cd[] = "b.section LIKE '%".$kw."%' ";
				$cd[] = "c.desc LIKE '%".$kw."%' ";

	            $seg[] = "( ".implode( "OR ", $cd ).") ";
        	}
        	$cond.= implode( "OR ", $seg );

        	$cond.= ") ";
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ;";

		return $wpdb->get_results( $sql , ARRAY_A );
	}

	public function get_todo_action( $id = 0, $args = array() )
	{
		if( ! $id && ! $args ) return false;

		global $wcwh, $current_user;
		$wpdb = $this->db_wpdb;

		$field = "* "; 
		$table = "{$this->tables['todo_action']} ";
		$cond = "";

		if( $id > 0 )
		{
			$cond .= $wpdb->prepare("AND id = %d ", $id );
		}
		
		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				if( is_array( $val ) )
					$cond .=  "AND {$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND {$key} = %s ", $val );
			}
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ;";

		return $wpdb->get_row( $sql , ARRAY_A );
	}

	public function count_statuses()
	{
		$wpdb = $this->db_wpdb;

		$fld = "'all' AS status, COUNT( flag ) AS count ";
		$tbl = "{$this->tables['todo']} a ";
		$cond = $wpdb->prepare( "AND a.status > %d ", 0 );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "flag AS status, COUNT( flag ) AS count ";
		$tbl = "{$this->tables['todo']} b ";
		$cond = $wpdb->prepare( "AND b.status > %d ", 0 );
		$group = "GROUP BY b.flag ";
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