<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ServiceCharge_Class" ) ) 
{

class WCWH_ServiceCharge_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_service_charge";

	protected $tbl = "service_charge";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "ServiceCharge_Class";

	public $one_step_delete = false;
	public $true_delete = false;
	public $useFlag = false;

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
			"status"		=> $prefix."status"
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

			$action = strtolower( $action );
			switch ( $action )
			{
				case "save":
				case "update":
				$id = ( isset( $datas['id'] ) && !empty( $datas['id'] ) )? $datas['id'] : "0";

				if( $id != "0" )
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
							//if( $metas && method_exists( $this, 'update_metas' ) ) $this->update_metas( $id, $metas );
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
						//$datas['id'] = $id;
						//if( $metas && method_exists( $this, 'update_metas' ) ) $this->update_metas( $id, $metas );
					}

				}
				if( $succ )
				{
					$outcome['id'] = $id;
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
					if($succ)
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
				break;
				case "restore":
				$id = $datas['id'];
				if( $id > 0 )
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
					if($succ)
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
							$this->Notices->set_notice( "delete-fail", "error", $this->className."|action_handler|".$action );
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
		if( isset( $filters['code'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
		}
		if( isset( $filters['scode'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.scode = %s ", $filters['scode'] );
		}
		if( isset( $filters['type'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.type = %s ", $filters['type'] );
		}
		if( isset( $filters['from_amt'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.from_amt = %d ", $filters['from_amt'] );
		}
		if( isset( $filters['to_amt'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.to_amt = %d ", $filters['to_amt'] );
		}
		if( isset( $filters['from_currency'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.from_currency = %s ", $filters['from_currency'] );
		}
		if( isset( $filters['to_currency'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.to_currency = %s ", $filters['to_currency'] );
		}
		if( isset( $filters['charge'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.charge = %d ", $filters['charge'] );
		}
		if( isset( $filters['desc'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.accounto_no = %s ", $filters['desc'] );
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
                $cd[] = "a.type LIKE '%".$kw."%' ";
				$cd[] = "a.desc LIKE '%".$kw."%' ";
				$cd[] = "a.from_amt LIKE '%".$kw."%' ";
				$cd[] = "a.to_amt LIKE '%".$kw."%' ";
				$cd[] = "a.charge LIKE '%".$kw."%' ";

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
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->tables['meta']} {$meta_key} ON {$meta_key}.brand_id = a.id AND {$meta_key}.meta_key = %s ", $meta_key );
				
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

            $table.= "LEFT JOIN {$dbname}{$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {
        	//------ status only 1,0 ???? 
        	//-----  amendment if post, unpost, complete or any status other than 1 exists
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
			$order = [ 'a.id' => 'ASC' ];
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



	public function get_export_data( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		$dbname = "";

		$field = "a.code, a.type, a.from_amt, a.to_amt, a.from_currency, a.to_currency, a.charge, a.charge_type, a.since, a.desc, a.status ";
		if( $this->useFlag ) $field.= ", a.flag ";
		
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = $wpdb->prepare("AND a.type = 'bank_in'");
		$grp = "";
		$ord = "";
		$l = "";

		if( isset( $filters['id'] ) && !empty( $filters['id'] )  )
		{
			$cond.= $wpdb->prepare( "AND a.id = %s ", $filters['id'] );
		}
		if( isset( $filters['code'] ) && !empty( $filters['code'] )  )
		{
			$cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
		}
		if( isset( $filters['code'] ) && !empty( $filters['code'] )  )
		{
			$cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
		}
		if( isset( $filters['from_amt'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.from_amt >= %d ", $filters['from_amt'] );
			unset( $filters['from_amt'] );
		}
		if( isset( $filters['to_amt'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.to_amt <= %d ", $filters['to_amt'] );
			unset( $filters['to_amt'] );
		}
		if( isset( $filters['charge'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.charge = %d ", $filters['charge'] );
			unset( $filters['charge'] );
		}
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.lupdate_at >= %s ", $filters['from_date'] );
			unset( $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.lupdate_at <= %s ", $filters['to_date'] );
			unset( $filters['to_date'] );
		}
	    if( isset( $filters['status'] ) && $filters['status'] != 'all' )
	    {   
	        $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
	    }

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'a.code' => 'ASC' ];
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