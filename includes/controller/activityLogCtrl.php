<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_ActivityLog' ) ) include_once( WCWH_DIR . "/includes/classes/activity-log.php" ); 

if ( !class_exists( "WCWH_ActivityLog_Controller" ) ) 
{

class WCWH_ActivityLog_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_logs";

	protected $primary_key = "id";

	public $Notices;
	public $className = "ActivityLog_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newLogs',
	);

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->set_logic();
	}

	public function __destruct()
	{
		unset($this->Logic);
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_ActivityLog( $this->db_wpdb );
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function set_warehouse( $warehouse = array() )
	{
		$this->warehouse = $warehouse;

		if( ! isset( $this->warehouse['permissions'] ) )
		{
			$metas = get_warehouse_meta( $this->warehouse['id'] );
			$this->warehouse = $this->combine_meta_data( $this->warehouse, $metas );
		}

		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] )
			$this->view_outlet = true;

		//$this->Logic->setWarehouse( $this->warehouse );
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'wh_code' => '',
			'page' => '',
			'section' => '',
			'ref_id' => 0,
			'action' => '',
			'ip_address' => '',
			'agent' => '',
			'data' => '',
			'parent' => 0,
			'status' => 0,
			'error_remark' => '',
			'action_by' => 0,
			'log_at' => ''
		);
	}

	public function action_handler( $action = 'save', $datas = array(), $obj = array(), $transact = true )
	{
		$succ = true;

		$outcome = array();

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			//if( $datas['data'] ) $datas['data'] = json_encode( $datas['data'] );
			//if( $datas['error_remark'] ) $datas['error_remark'] = json_encode( $datas['error_remark'] );
			
			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					if( ! $datas[ $this->get_primaryKey() ] )
					{
						$datas['action_by'] = $user_id;
						$datas['log_at'] = $now;
						
						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
					}

					$datas = $this->json_encoding( $datas );

					if( $succ )
					{
						$result = $this->Logic->action_handler( $action, $datas, $obj );
						if( ! $result['succ'] )
						{
							$succ = false;
						}

						if( $result['succ'] )
						{
							$outcome['id'] = $result['id'];
						}
					}
				break;
				case "print":
					$this->print_form( $datas['id'] );

					exit;
				break;
				default:
					$succ = false;
				break;
			}

			if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		$succ = $this->after_action( $succ, $outcome['id'], $action );
           	}
        }
        catch (\Exception $e) 
        {
            $succ = false;
            if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }
        finally
        {
        	if( $succ )
                if( $transact ) wpdb_end_transaction( true, $this->db_wpdb );
            else 
                if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }

        $outcome['succ'] = $succ;
		
		return $outcome;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
	}

	public function view_form( $id = 0, $templating = true, $isView = true )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'token' => apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['new'],
		);

		if( $id )
		{
			$datas = $this->Logic->get_infos( [ 'id' => $id ], [], true, [] );
			if( $datas )
			{
				$args['data'] = $datas;
				if( $isView ) $args['view'] = true;
			}

			do_action( 'wcwh_get_template', 'form/activityLog-form.php', $args );
		}
	}

	public function view_doc_log( $id = 0, $section = '' )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'token' => apply_filters( 'wcwh_generate_token', $this->section_id ),
		);

		if( $id )
		{
			if( current_user_cans( ['wh_support'] ) )
				$filters = [ 'section'=>$section, 'ref_id'=>$id ];
			else
				$filters = [ 'section'=>$section, 'ref_id'=>$id, 'status'=>1 ];

			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$datas = $this->Logic->get_infos( $filters, [], false, [ 'user'=>1 ] );

			if( $datas )
			{
				$Inst = new WCWH_Listing();

		        foreach( $datas as $i => $row )
		        {
		        	$datas[$i]['num'] = $i+1;
		        	$datas[$i]['action_by'] = ( $row['actor_name'] )? $row['actor_name'] : $row['display_name'];
		        }

		        $cols = [
		        	'num' => '',
		        	'id' => 'ID',
		        	'action' => 'Action',
		        	'action_by' => 'Action By',
		        	'log_at' => 'At Time',
		        	'ip_address' => 'IP',
		        	'agent' => 'Agent',
		        	'status' => 'Status',
		        	'error_remark' => 'Error',
		        ];
		        if( ! current_user_cans( ['wh_support'] ) )
		        {
		        	unset( $cols['id'] );
		        	unset( $cols['ip_address'] );
		        	unset( $cols['agent'] );
		        	unset( $cols['status'] );
		        	unset( $cols['error_remark'] );
		        }
		        	
		        $args['render'] = $Inst->get_listing( $cols, $datas, [], [], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );

				$args['data'] = $datas;
				if( $isView ) $args['view'] = true;
			}

			do_action( 'wcwh_get_template', 'form/view-log.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/activityLogListing.php" ); 
			$Inst = new WCWH_ActivityLog_Listing();
			$Inst->set_section_id( $this->section_id );

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, [], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}