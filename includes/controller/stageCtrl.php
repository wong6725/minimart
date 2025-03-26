<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_Stage' ) ) include_once( WCWH_DIR . "/includes/classes/stage.php" ); 
if( ! class_exists( 'WCWH_StageDetail' ) ) include_once( WCWH_DIR . "/includes/classes/stage-detail.php" ); 

if ( !class_exists( "WCWH_Stage_Controller" ) ) 
{

class WCWH_Stage_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_stage";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Stage_Controller";

	public $Logic;
	public $Detail;

	public $tplName = array(
		'new' => 'newStage',
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
		unset($this->Detail);
		unset($this->warehouse);
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_Stage( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );

		$this->Detail = new WCWH_StageDetail( $this->db_wpdb );
		$this->Detail->set_section_id( $this->section_id );
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
			'ref_type' => '',
			'ref_id' => 0,
			'status' => 1,
			'proceed_status' => 0,
			'halt_status' => 0,
			'latest_stage' => 0,
			'created_by' => 0,
			'created_at' => '',
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
			
			$action = strtolower( $action );
        	switch ( $action )
        	{
        		case "save":
        			$result = $this->perform_stage( $datas, $obj );
        			if( ! $result['succ'] )
        			{
        				$succ = false;
        			}
        			else
        			{
        				$outcome['id'][] = $result['id'];
        			}
        		break;
				case "update":
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					if( $succ )
					{
						$result = $this->Logic->action_handler( $action, $datas );
						if( ! $result['succ'] )
						{
							$succ = false;
						}
						else
						{
							$outcome['id'][] = $result['id'];
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

	public function perform_stage( $datas = array(), $obj = array() )
	{
		$succ = true;

		$outcome = array();

		$action = "save";
		$result = array();
        $user_id = get_current_user_id();
		$now = current_time( 'mysql' );
		$stage_id = 0;
		
       	//Check Exists & Action Decision
		$filter = array();
		if( ! empty( $datas['id'] ) ) $filter['id'] = $datas['id'];
		if( ! empty( $datas['ref_type'] ) ) $filter['ref_type'] = $datas['ref_type'];
		if( ! empty( $datas['ref_id'] ) ) $filter['ref_id'] = $datas['ref_id'];

		unset( $datas['id'] );

		$exists = $this->Logic->get_infos( $filter, [], true );
		if( $exists ) 
		{
			$action = 'update';
		}

		$action = strtolower( $action );
		switch( $action )
		{
			case "save":
				$extracted = $this->extract_data( $datas );
				$stage_datas = $extracted['datas'];

				$stage_datas['created_by'] = $user_id;
				$stage_datas['created_at'] = $now;

				$stage_datas = wp_parse_args( $stage_datas, $this->get_defaultFields() );

				if( $succ )
				{
					$result = $this->Logic->action_handler( $action, $stage_datas );
					if( ! $result['succ'] )
					{
						$succ = false;
					}

					if( $result['succ'] )
					{
						//child action
						$stage_id = $result['id'];
						if( ! $this->perform_child( $stage_id, $datas ) )
						{
							$succ = false;
						}
						else
						{
							$outcome['id'] = $stage_id;
						}
					}
				}
			break;
			case "update":
				$stage_id = $exists['id'];

				if( $succ )
				{
					//child action
					if( ! $this->perform_child( $stage_id, $datas ) )
					{
						$succ = false;
					}
					else
					{
						$outcome['id'] = $stage_id;
					}
				}
			break;
			default:
				$succ = false;
			break;
		}

		$outcome['succ'] = $succ;
		
		return $outcome;
	}

	public function perform_child( $stage_id = 0, $datas = array() )
	{
		if( ! $stage_id || ! $datas ) return false;

		$datas['status'] = $this->get_status( $datas['status'], $datas['action'] );

		$child_datas = array(
			'stage_id' => $stage_id,
			'action' => $datas['action'],
			'status' => $datas['status'],
			'remark' => $datas['remark'],
			'metas' => '',
		);

		$child_result = $this->child_action_handler( 'save', $child_datas, [], false );
		
		if( ! $child_result['succ'] )
		{
			return false;
		}
		else
		{
			//update header
			$header_datas = [ 'id' => $stage_id, 'latest_stage' => $child_result['id'] ];
			if( $datas['status'] >= 10 )
			{
				$header_datas['proceed_status'] = $datas['status'];
			}
			else if( $datas['status'] <= -10 )
			{
				$header_datas['halt_status'] = $datas['status'];
			}
			else
			{
				$header_datas['status'] = $datas['status'];
			}

			if( in_array( $datas['action'], [ 'unpost' ] ) )
			{
				$header_datas['proceed_status'] = 0;
				$header_datas['halt_status'] = 0;
			}

			$result = $this->Logic->action_handler( 'update', $header_datas );
			if( ! $result['succ'] )
			{
				return false;
			}
		}

		return true;
	}

	private function get_status( $status, $action = 'save' )
	{
		switch( $action )
		{
			case 'post':
				$status = 6;
			break;
			case 'unpost':
				$status = 1;
			break;
			case 'complete':
				$status = 9;
			break;
			case 'incomplete':
				$status = 6;
			break;
			case 'close':
				$status = 10;
			break;
		}
		
		return $status;
	}


	/**
     *  Child action
     *  ---------------------------------------------------------------------------------------------------
     */
	protected function get_defaultChildFields()
	{
		return array(
			'stage_id' => 0,
			'action' => '',
			'status' => 0,
			'remark' => '',
			'metas' => '',
			'action_by' => 0,
			'action_at' => '',
		);
	}

	public function child_action_handler( $action = 'save', $datas = array(), $obj = array(), $transact = true )
	{
		$succ = true;

		$outcome = array();

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );
			
			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						$datas['action_by'] = $user_id;
						$datas['action_at'] = $now;
						
						$datas = wp_parse_args( $datas, $this->get_defaultChildFields() );
					}
					//print_data($datas);
					if( $succ )
					{
						$result = $this->Detail->action_handler( $action, $datas );
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
				default:
					$succ = false;
				break;
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
			$datas = $this->Logic->get_infos( [ 'id' => $id ], [], true );
			if( $datas )
			{
				$Inst = new WCWH_Listing();

				$dat = $this->Detail->get_infos( [ 'stage_id'=>$datas['id'] ], [], false, [ 'user'=>1 ] );
		        $dat = ( $dat )? $dat : array();
		        if( $dat )
		        {
		        	foreach( $dat as $i => $d )
		        	{
		        		$dat[$i]['num'] = $i+1;
		        		$dat[$i]['action_by'] = ( $d['actor_name'] )? $d['actor_name'] : $d['display_name'];
		        	}
		        }
		        	
		        $args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'id' => 'ID',
		        		'action' => 'Action',
		        		'status' => 'Status',
		        		'remark' => 'Remark',
		        		'action_by' => 'Action By',
		        		'action_at' => 'At Time',
		        	], 
		        	$dat, 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );

				$args['data'] = $datas;
				if( $isView ) $args['view'] = true;
			}

			do_action( 'wcwh_get_template', 'form/stage-form.php', $args );
		}
	}

	public function view_doc_stage( $id = 0, $section = '' )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'token' => apply_filters( 'wcwh_generate_token', $this->section_id ),
		);

		if( $id )
		{
			$filters = [ 'ref_type' => $section, 'ref_id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$datas = $this->Logic->get_infos( $filters, [], true );
			if( $datas )
			{
				$Inst = new WCWH_Listing();

				$filters = [ 'stage_id'=>$datas['id'] ];
				if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
				
				$dat = $this->Detail->get_infos( $filters, [], false, [ 'user'=>1 ] );
		        $dat = ( $dat )? $dat : array();
		        if( $dat )
		        {
		        	foreach( $dat as $i => $d )
		        	{
		        		$dat[$i]['num'] = $i+1;
		        		$dat[$i]['action_by'] = ( $d['actor_name'] )? $d['actor_name'] : $d['display_name'];
		        	}
		        }

		        $cols = [
		        	'num' => '',
		        	'id' => 'ID',
		        	'action' => 'Action',
		        	'status' => 'Status',
		        	'remark' => 'Remark',
		        	'action_by' => 'Action By',
		        	'action_at' => 'At Time',
		        ];
		        if( !current_user_cans( ['wh_support'] ) )
		        {
		        	unset( $cols['id'] );
		        	unset( $cols['status'] );
		        }
		        	
		        $args['render'] = $Inst->get_listing( $cols, $dat, [], [], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );

				$args['data'] = $datas;
				if( $isView ) $args['view'] = true;
			}

			do_action( 'wcwh_get_template', 'form/view-stage.php', $args );
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
			include_once( WCWH_DIR."/includes/listing/stageListing.php" ); 
			$Inst = new WCWH_Stage_Listing();
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