<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TaskChecklist_Class" ) ) include_once( WCWH_DIR . "/includes/classes/task-checklist.php" ); 

if ( !class_exists( "WCWH_TaskChecklist_Controller" ) ) 
{

class WCWH_TaskChecklist_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_task_checklist";

	protected $primary_key = "id";

	public $Notices;
	public $className = "TaskChecklist_Controller";

	public $processing_stat = [ 6 ];

	public $Logic;

	public $tplName = array(
		'new' => 'newTaskChecklist',
		'row' => 'rowTaskChecklist',
		'import' => 'importTaskChecklist',
		'export' => 'exportTaskChecklist',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	private $temp_data = array();

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->arrangement_init();
		
		$this->set_logic();
	}

	public function arrangement_init()
	{
		$Inst = new WCWH_TODO_Class();

		$arr = $Inst->get_arrangement( [ 'section'=>$this->section_id, 'action_type'=>'approval', 'status'=>1 ] );
		if( $arr )
		{
			$this->useFlag = true;
		}
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_TaskChecklist_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->processing_stat = $this->processing_stat;
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function set_warehouse( $warehouse = array() )
	{
		$this->warehouse = $warehouse;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'doc_id' => '',
			'warehouse_id' => '',
			'docno' => '',
			'sdocno' => '',
			'doc_date' => '',
			'post_date' => '',
			'doc_type' => '',
			'hstatus' => 1,
			'hflag' => ( $this->useFlag )? 0 : 1,
			'parent' => 0,
			'remark' => '',
			'client_company_code' => '',
            'recursive_period' => '',
            'last_run_date' => '',
            'ref_doc_id' => 0,
            'ref_item_id' => 0,
            'dstatus' => '',
            '_serial2' => '',
		);
	}


	protected function get_unneededFields()
	{
		return array( 
			'action', 
			'token', 
			'wh', 
			'filter',
			'_wpnonce',
			'action2',
			'_wp_http_referer',
		);
	}

	public function validate( $action , $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		if( ! $action || $action < 0 )
		{
			$succ = false;
			$this->Notices->set_notice( 'invalid-action', 'warning' );
		}

		if( ! $datas )
		{
			$succ = false;
			$this->Notices->set_notice( 'insufficient-data', 'warning' );
		}

		if( $succ )
		{
			$action = strtolower( $action );
			switch( $action )
			{
				case 'update':
				case 'save':
					if( ! $datas['header'] || ! $datas['header']['warehouse_id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'delete':
				case 'post':
				case 'unpost':
				case 'approve':
				case 'reject':
				case 'complete':
				case "incomplete":
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
			}
		}

		return $succ;
	}


	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		$count_succ = 0;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

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
					$datas = $this->data_sanitizing( $datas );
					$header = $datas['header'];
					$detail = $datas['detail'];

					$header['doc_date'] = ( $header['doc_date'] )? date( 'Y-m-d', strtotime( $header['doc_date'] ) ) : current_time( 'Y-m-d' );
					$header['doc_time'] = "00:00:00";

					$header['warehouse_id'] = !empty( $header['warehouse_id'] )? $header['warehouse_id'] : $this->warehouse['code'];

					$f = [ 'warehouse_id'=>$header['warehouse_id'], 'doc_date'=>$header['doc_date'] ];
					if( $header['doc_id'] ) $f['not_doc_id'] = $header['doc_id'];

					$datas['serial2'] = isset($datas['serial2']) ? $datas['serial2'] : array(); 
					
					if( $succ )
					{
						$result = $this->Logic->child_action_handle( $action, $header, $detail );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $succ )
						{
							$outcome['id'][] = $result['id'];
							$count_succ++;

							if( $action == 'save' )
							{
								//Doc Stage
						        $stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
						            'ref_type'		=> $this->section_id,
						            'ref_id'		=> $result['id'],
						            'action'        => $action,
						            'status'    	=> 1,
						        ] );
						    }
						}
					}
				break;
				case "delete":
				case "post":
				case "complete":
					$datas = $this->data_sanitizing( $datas );
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					foreach($ids as $id){
						if( $succ )
						{
							$item = $this->Logic->get_document_items($id);
							$update_items = array(
								'status' 		=> 9, 
								// 'bqty'			=> (--$item['bqty']),
								'lupdate_at' 	=> current_time( 'mysql' ),
								'lupdate_by' 	=> $this->Logic->user_id
							);
							if(($item['bqty'] - 1) != 0){
								$update_items['status'] = 6;
							}
							$result = $this->Logic->update_document_items(['item_id' => $id], $update_items);
							unset($item);
						}
					}

					if($succ)
							{
								// Auto Refresh complete Doc (maybe no need)
								// $check = $this->check_complete_task();
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
							}else{
								$succ = false;
							}
							
							if( $succ )
							{
								$outcome['id'][] = $result['id'];
								//$outcome['data'][] = $result['data'];
								$count_succ++;

								if( $action == 'save' )
								{
									//Doc Stage
							        $stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
							            'ref_type'		=> $this->section_id,
							            'ref_id'		=> $result['id'],
							            'action'        => $action,
							            'status'    	=> 1,
							        ] );
							    }

							}
					
				break;
				case "incomplete":
					$datas = $this->data_sanitizing( $datas );
					
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$data = $this->Logic->get_document_items($id);
							if($data['status'] == 9 && $data['bqty'] > 0){
								$update_items = array(
									'status' => 6, 
									'lupdate_at' => current_time( 'mysql' ),
									'lupdate_by' => $this->Logic->user_id
								);
								$succ = $this->Logic->update_document_items(['item_id' => $id], $update_items);
							}else{
								$succ = false;
							}
							
						}
						if($succ)
						{
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
							break;
						}else{
							$this->Notices->set_notice( 'prevent-action', 'error' );
						}

						if( $succ )
						{
							$outcome['id'][] = $result['id'];
							$count_succ++;

							//Doc Stage
							$dat = $result['data'];
							$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
								'ref_type'	=> $this->section_id,
								'ref_id'	=> $result['id'],
								'action'	=> $action,
								'status'    => $dat['status'],
								'remark'	=> ( $datas['remark'] )? $datas['remark'] : '',
							] );
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}

					
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	//if( is_array( $datas["id"] ) && $count_succ > 0 ) $succ = true;

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		if( ! $this->after_action( $succ, $outcome['id'], $action ) )
           			$succ = false;
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

	// public function after_action( $succ, $id, $action = "save" )
	// {
	// 	if( ! $id ) return $succ;
		
	// 	if( $succ )
	// 	{
	// 		$id = is_array( $id )? $id : [ $id ];

	// 		$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false, [] );
	// 		$handled = [];
	// 		foreach( $exists as $exist )
	// 		{
	// 			$handled[ $exist['doc_id'] ] = $exist;
	// 		}
			
	// 		foreach( $id as $ref_id )
	// 		{
	// 			// if( $handled[ $ref_id ]['flag'] == 0 )
	// 			// {
	// 			// 	$succ = apply_filters( 'wcwh_todo_arrangement', $ref_id, $this->section_id, $action );
	// 			// 	if( ! $succ )
	// 			// 	{
	// 			// 		$this->Notices->set_notice( 'arrange-fail', 'error' );
	// 			// 	}
	// 			// }

	// 			// $wh = $handled[ $ref_id ]['warehouse_id'];
	// 			// if( $handled[ $ref_id ]['status'] >= 6 && $wh )
	// 			// {
	// 			// 	$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $wh );
	// 			// 	if( ! $succ )
	// 			// 	{
	// 			// 		$this->Notices->set_notice( 'arrange-fail', 'error' );
	// 			// 	}
	// 			// }
	// 		}
	// 	}
	// 	return $succ;
	// }
	
	//currently no use
	// public function check_complete_task(){
	// 	$succ = true;
	// 	$wh = ( $this->warehouse['code'] )? $this->warehouse['code'] : '';
	// 	if( $this->warehouse['code'] ) $filters['warehouse_id'] = $this->warehouse['code'];
	// 	$filters['status'] = 6;
		
	// 	//uncheck data
	// 	$datas = $this->Logic->get_schedule($filters, [], false, [], [], []);

	// 	$u_datas = [];
	// 	foreach ($datas as $key => $value) {
	// 	    if ($value['status'] != 9) {
	// 	        $u_datas[] = $value;
	// 	    }
	// 	}

	// 	$diff_datas = array_udiff($datas, $u_datas, function($a, $b) {
	// 	    return strcmp(serialize($a), serialize($b));
	// 	});

	// 	//uncompleted doc id
	// 	$u_doc_ids = array_column($u_datas, 'doc_id');
	// 	$u_doc_ids = array_unique($u_doc_ids);

	// 	$diff_doc_ids = array_column($diff_datas, 'doc_id');
	// 	$diff_doc_ids = array_unique($diff_doc_ids);

	// 	$complete_docIds = array_values(array_diff($diff_doc_ids, $u_doc_ids));

	// 	if ( !class_exists( "WCWH_TaskSchedule_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/taskScheduleCtrl.php" );
	// 	$Inst = new WCWH_TaskSchedule_Controller();

	// 	foreach($complete_docIds as $value){
	// 		$succ = $this->Logic->update_document_header( [ 'doc_id' => $value ], [ 'status' => 9] );
	// 	}

	// 	return $succ;
	// }


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = array();
		$default_column['default'] = array( 'doc_id', 'warehouse_id', 'docno', 'sdocno', 'doc_date', 'post_date', 'doc_type', 'hstatus', 'hflag', 'parent', 'remark', 'recursive_period', 'run_date', 'next_date', 'serial2' );
		$default_column['unique'] = array( 'docno' );
		$default_column['required'] = array( 'doc_id', 'warehouse_id', 'docno', 'sdocno' );
		$default_column['unchange'] = array( 'doc_type');
		$default_column['important'] = array();

		return $default_column;
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
		switch( strtolower( $type ) )
		{
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php //echo $actions['close'] ?>New Task Schedule" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="<?php //echo $actions['close'] ?>New Task Schedule"
				>
					<?php //echo $actions['close']; ?>New
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

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

		$id = $this->Logic->get_doc_id_by_item($id, true);
		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id, 'status' => 6 ], [], true );
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );
				
				$datas['details'] =  $this->Logic->get_checklist([ 'doc_id' => $id, 'bqty' => 1 ], false);
				
				foreach( $datas['details'] as $i => $item )
		        {
					$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        	$datas['details'][$i]['i_num'] = $datas['details'][$i]['_item_number'];
		        	$datas['details'][$i]['serial2'] = $datas['details'][$i]['_serial2'];
		        }
		
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();


				$args['data'] = $datas;
				unset( $args['new'] );

				$args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'_serial2' => 'Task',
		        		// 'bqty' => 'Qty',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
				
				$checklist = $this->Logic->get_checklist([ 'doc_id' => $id, 'dstatus' => 6 ], false);
				
				if(!empty($checklist)) $args['Check'] = true;

				
				foreach( $checklist as $i => $item )
		        {
					$checklist[$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        	$checklist[$i]['i_num'] = $checklist[$i]['_item_number'];
		        	$checklist[$i]['serial2'] = $checklist[$i]['_serial2'];
					$checklist[$i]['create_date'] = date('Y-m-d', strtotime($item['created_at']));
					if($item['hstatus'] == 9){
						$checklist[$i]['run_date'] = date('Y-m-d', strtotime($item['lupdate_at']));
						$checklist[$i]['check'] = "Complete";
					}else{
						// $checklist[$i]['run_date'] = "-";
						$checklist[$i]['check'] = "Incomplete";
					}
		        }
				$args['checklist'] = $Inst->get_listing( [
					'num' => '',
					'serial2' => 'Task',
					'check' => 'Status',
					'create_date' => 'Created At',
					'run_date' => 'Run Date',
				], 
				$checklist, 
				[], 
				[], 
				[ 'off_footer'=>true, 'list_only'=>true ]
				);
			}
		}
		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/taskSchedule-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/taskSchedule-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/taskChecklistListing.php" ); 
			$Inst = new WCWH_TaskChecklist_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$wh = ( $this->warehouse['code'] )? $this->warehouse['code'] : '';
			$count = $this->Logic->count_statuses( $wh );
			if( $count ) $Inst->viewStats = $count;

			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : ( $count[ 'process' ]? 'process' : 'all' );

			if( $this->warehouse['code'] ) $filters['warehouse_id'] = $this->warehouse['code'];
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$metas = [ 'remark', 'recursive_period' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_schedule($filters, $order, false, [ 'meta' => $metas ], [], $limit);
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}