<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_POSSession_Controller" ) ) 
{

class WCWH_POSSession_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_pos_session";

	protected $primary_key = "id";

	public $Notices;
	public $className = "POSSession_Controller";
	protected $tbl = "wc_point_of_sale_sessions";

	public $Logic;

	public $seller = 0;

	public $tplName = array(
		'new' => 'newPOSSession'
	);

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

	}

	public function get_section_id()
	{
		return $this->section_id;
	}
	
	
	public function update_data( $id = 0, $datas = array(), $args = array() )
	{
		if( ! $datas ) return false;
		
		$wpdb = $this->db_wpdb;
	
		$find = array();
		if( $id && $this->primary_key )
		{
			$find = array( $this->primary_key => $id );
			if( ! $this->inclPrimary ) unset( $datas[$this->primary_key] );
		}
		
		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				$find[$key] = $val;
			}
		}
		return $wpdb->update( $wpdb->prefix.$this->tbl, $datas, $find );
	}


	public function action_handler( $action, $datas = array(), $metas = array(), $obj = array() )
	{
		if( $this->Notices ) $this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		if( $succ )
		{
			$exist = array();

			$action = strtolower( $action );
			switch ( $action )
			{
				case "save":
				case "update":
					$id = $datas['id'];
					unset($datas['id']);

					if( $id != "0" )	//update
					{
						$datas['opening'] = ( $datas['opening'] && $datas['opening'] >= 500 ) ? round_to( $datas['opening'], 2 ) : '0';
						$datas['closing'] = ( $datas['closing'] && $datas['closing'] > 0 ) ? round_to( $datas['closing'], 2 ) : '0';
						if( isset( $datas['opening'] ) && isset( $datas['closing'] ) )
						{
							$succ = true;
						}
						else
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
						}

						if( $succ ) 
						{
							$result = $this->update_data( $id, $datas );
							if ( false === $result )
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
							}
						}
					}
				break;
			}
		}

		if( $succ && $this->Notices && $this->Notices->count_notice( "error" ) > 0 )
            $succ = false;
		
		$outcome['succ'] = $succ; 

		return $outcome;
	}

	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
			'get_content' => $getContent,
			'wh_code'	=> $this->warehouse['code'],
		);
		if( $id )
		{
			$datas =  $this->get_infos([ 'id' => $id, 'status' => 0, 'seller' => $this->seller], [], false, [], [], []);
			if( $datas )
			{	
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();

				$args['data'] = $datas[0];
				
				unset( $args['new'] );
			}
		}
		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/posSession-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/posSession-form.php', $args );
		}
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/posSessionListing.php" ); 
			$Inst = new WCWH_POSSession_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->seller = $this->seller;

			if( $this->seller ) $filters['seller'] = $this->seller;

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->count_statuses( $filters );
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->get_infos( $filters, $order, false, [], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}


	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_infos( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh, $wpdb;
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
		
		$field = "a.*, b.display_name ";
		$table = "{$dbname}{$wpdb->prefix}wc_point_of_sale_sessions a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->prefix}users b ON b.ID = a.cashier_id ";
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
				$cond.= $wpdb->prepare( "AND a.id != %s ", $filters['not_id'] );
		}
		if( isset( $filters['register'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.register = %s ", $filters['register'] );
		}
		if( isset( $filters['outlet_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.outlet_id = %s ", $filters['outlet_id'] );
		}
		if( isset( $filters['cashier_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.cashier_id = %d ", $filters['cashier_id'] );
		}
		//status
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
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
				$cd[] = "a.register_name LIKE '%".$kw."%' ";
				$cd[] = "b.display_name LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
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

	public function count_statuses( $filters = [] )
	{
		$wpdb = $this->db_wpdb;

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

		$fld = "'all' AS status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$wpdb->prefix}wc_point_of_sale_sessions ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$wpdb->prefix}wc_point_of_sale_sessions ";
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
	
} //class

}