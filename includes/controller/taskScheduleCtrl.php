<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TaskSchedule_Class" ) ) include_once( WCWH_DIR . "/includes/classes/task-schedule.php" ); 

if ( !class_exists( "WCWH_TaskSchedule_Controller" ) ) 
{

class WCWH_TaskSchedule_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_task_schedule";

	// protected $primary_key = "id";

	public $Notices;
	public $className = "TaskSchedule_Controller";

	public $processing_stat = [ 1, 6 ];

	public $Logic;

	public $tplName = array(
		'new' => 'newTaskSchedule',
		'row' => 'rowTaskSchedule',
		'import' => 'importTaskSchedule',
		'export' => 'exportTaskSchedule',
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
		$this->Logic = new WCWH_TaskSchedule_Class( $this->db_wpdb );
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
					if( ! $datas['detail'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'delete':
				case 'post':
				case 'unpost':
				case "complete":
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
					}
				break;
				case "delete":
				case "post":
				case "complete":
				case "incomplete":
					$datas = $this->data_sanitizing( $datas );
					$doc_id = $datas['id'];
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
							$result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}
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
				case "unpost":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
							$result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}
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
				case "import":
					$files = $_FILES;
					if( $files )
					{
						$succ = $this->import_data( $files, $datas );
					}
					else
					{
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "export":
					$datas['filename'] = 'TaskSchedule';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					$params['status'] = $datas['status'];

					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
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

	public function after_action( $succ, $id, $action = "save" )
	{
		if( ! $id ) return $succ;
		
		if( $succ )
		{
			$id = is_array( $id )? $id : [ $id ];

			$exists = $this->Logic->get_export_data( [ 'doc_id' => $id ], false);

			$handled = [];
			foreach( $exists as $exist )
			{
				$handled[ $exist['doc_id'] ] = $exist;
			}
			foreach( $id as $ref_id )
			{
				if( $handled[ $ref_id ]['flag'] == 0 )
				{
					$succ = apply_filters( 'wcwh_todo_arrangement', $ref_id, $this->section_id, $action );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
					}
				}

				$wh = $handled[ $ref_id ]['warehouse_id'];
				if( $handled[ $ref_id ]['dstatus'] >= 6 && $wh && $this->warehouse['code'] != "1025-MWT3")
				{
					$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $wh );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
					}
				}
			}
		}

		return $succ;
	}

	public function next_run_date($doc_date, $period_value){
        $now_date = date('Y-m-d', time());
        $next_date = date('Y-m-d', strtotime($doc_date . $period_value));
        $result = array();
        
        if ($now_date > $doc_date && $now_date <= $next_date) {
            $result = [
                'doc_date' => $next_date,
                'last_run_date' => $now_date
            ];  
        } elseif ($now_date > $next_date) {
            $result = [
                'doc_date' => date('Y-m-d', strtotime($now_date)),
                'last_run_date' => $now_date
            ];
        } else {
            $result = [
                'doc_date' => $doc_date
            ];
        }
         
        return $result;
    }


	public function check_action_date( $doc_id ){
		$succ = true;
		$header = $this->Logic->get_document_header( [ 'doc_id' => $doc_id ], [], false, [] );
		$doc_date = $header['doc_date'];
		$recursive_period = $this->Logic->get_document_meta( $doc_id, "recursive_period" );
		$recursive_period = $recursive_period[0];
		$now_date = date('Y-m-d', time());

		$r_period = options_data( apply_filters( 'wcwh_get_i18n', 'recursive-period' , [], [], false, [] ), 'key', [ 'value' ]);
		if($recursive_period){
			foreach ($r_period as $key => $value) {
				if ($key == $recursive_period) {
					$period_value = $value;
					break;
				}
			}
		}	

		$result = $this->next_run_date($doc_date, $period_value);

		$succ = $this->Logic->update_document_header(array('doc_id' => $doc_id), array('doc_date'=>$result['doc_date']));

		$metas = array();
		if( isset($result['last_run_date']) && $result['doc_date'] != $doc_date){
			$metas = array(
	        	'last_run_date' => $result['last_run_date'],
        	);
			$update_meta = $this->Logic->update_metas($doc_id, $metas);
			$succ = $this->task_duplicator($doc_id);
		}else{
			$succ = false;
		}
		
		return $succ;
	}

	public function update_document_items_meta($doc_id, $meta_key){
		$succ = true;
		$old_data = $this->Logic->get_document_metas($doc_id, $meta_key);
		$item_data = $this->Logic->get_document_items_by_doc( $doc_id, 6 );
		$datas = array();

		foreach($old_data as $key => $value){
			unset($old_data[$key]['meta_id']);
			$datas[$key]['doc_id'] = $doc_id;
			$datas[$key]['meta_key'] = $meta_key;
			foreach($item_data as $item ){
				if($item['ref_item_id'] == $value['item_id']){
					$datas[$key]['item_id'] = $item['item_id'];
					$datas[$key]['meta_value'] = $value['meta_value'];
				}

			}
			$succ = $this->Logic->add_document_meta($datas[$key]);
		}

		return $succ;
	}
	public function duplicator_validate($doc_id){
		$succ =false;
		$base_items = $this->Logic->get_document_items_by_doc($doc_id);
		foreach($base_items as $value){
			//bqty -> remain times of recursive period
			if($value['status'] != 9 ){
				$succ =false;
				break;
			}else{
				$succ =true;
			}
		}
		return $succ;
	}

	public function task_duplicator( $doc_id ){
		$succ = true;
		
		$old_items = $this->Logic->get_document_items_by_doc($doc_id, 9);
		$update_data = array();
		foreach($old_items as $key => $value){
			$update_data = $old_items[$key];
			$update_data['ref_doc_id'] = $value['doc_id'];
			$update_data['ref_item_id'] = $value['item_id'];
			$update_data['status'] = 6;
			$update_data['lupdate_at'] = current_time( 'mysql' );
			$update_data['created_at'] = current_time( 'mysql' );
			// need change if use times of recursive
			$update_data['bqty'] = 1;

			$item_id = $old_items[$key]['item_id'];
			unset($old_items[$key]['item_id']);

			if(($old_items[$key]['bqty']-1) == 0 && $old_items[$key]['status'] == 9){
				$succ = $this->Logic->add_document_items($update_data);
				$succ = $this->Logic->update_document_items(['item_id' => $item_id ], [ 'bqty' => ($old_items[$key]['bqty']-1) ]);
			}
		}

		// Duplicate Document Meta
		if($succ){
			$succ = $this->update_document_items_meta($doc_id, '_serial2');
			$succ = $this->update_document_items_meta($doc_id, '_item_number');
		}
		
		return $succ;
	}

	public function task_refresher(){
		$succ = true;
		$filters = ['status' => 6];
		$doc_list = $this->Logic->get_infos($filters, false);
		$doc_ids = array_values(array_unique(array_column($doc_list, "doc_id")));

		if (is_array($doc_list) && !empty($doc_list)) {
			foreach($doc_list as $key => $value){
				// Ensure all items completed
				$check = $this->duplicator_validate($doc_list[$key]['doc_id']);

				if($check){
					$succ = $this->check_action_date($doc_list[$key]['doc_id']);
				}

				// if($succ){
				// 	//can put recursive(bqty) decrement for complete task
				// 	$succ = $this->Logic->update_document_header(array('doc_id' => $doc_list[$key]['doc_id']), array('status'=>6));
				// }
			}
		}

		return $doc_list;
	}

	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = [];

		$default_column['header'] = [ 'doc_id', 'warehouse_id', 'docno', 'sdocno', 'doc_date', 'post_date', 'doc_type', 'hstatus', 'hflag', 'parent', 'client_company_code', 'remark', 'recursive_period'
		];

		$default_column['detail'] = [ 'item_id', 'strg_id', 'product_id', 'uom_id', 'bqty', 'uqty', 'bunit', 'uunit', 'ref_doc_id', 'ref_item_id', 'dstatus', '_item_number', '_serial2' ];

		$default_column['title'] = array_merge( $default_column['header'], $default_column['detail'] );
		$default_column['default'] = array_merge( $default_column['header'], $default_column['detail'] );

		$default_column['unchange'] = [ 'doc_id', 'item_id' ];

		return $default_column;
	}

	public function export_data_handler( $params = array() )
	{
		$columns = $this->im_ex_default_column();

		$raws = $this->Logic->get_export_data( $params );

		$cols = array_merge( $columns['header'], $columns['detail'] );

		$datas = [];
		foreach( $raws as $i => $row )
		{
			$line = [];
			foreach( $cols as $col )
			{
				$line[ $col ] = $row[ $col ];
				$datas[$i] = $line;
			}
		}

		return $datas;
	}

	public function import_data_handler( $datas, $args = array() )
	{
		if( ! $datas ) return false;

		$succ = true;
		$columns = $this->im_ex_default_column();

		$header_col = $columns['header'];
		$detail_col = $columns['detail'];
		$unchange_col = $columns['unchange'];

		$datas = $this->seperate_import_data( $datas, $header_col, [ 'sdocno' ], $detail_col );
		if( $datas )
		{
			wpdb_start_transaction( $this->db_wpdb );
			
			foreach( $datas as $i => $data )
			{
				if( !empty( $unchange_col ) )
				{
					foreach( $unchange_col as $key )
					{
						unset( $data['header'][$key] );
						unset( $data['detail'][$key] );
						foreach( $data['detail'] as $i => $row )
						{
							unset( $data['detail'][$i][$key] );
						}
					}
				}
				
				$header = $data['header'];
				$details = $data['detail'];
				
				if( $succ )
				{
					$succ = $this->Logic->import_handler( 'save', $header, $details );
					if( !$succ )
					{
						break;
					}
				}
			}
			
			wpdb_end_transaction( $succ, $this->db_wpdb );
		}

		return $succ;
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
					data-title="New Task Schedule" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="New Task Schedule"
				>
					New Task Schedule
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?> Task Schedule" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?> Task Schedule"
				>
					<i class="fa fa-upload" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'export':
				if( current_user_cans( [ 'export_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Task Schedule" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Task Schedule"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
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

		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true );
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				$datas['doc_date'] = date('Y-m-d', strtotime($datas['doc_date']));
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				
				// $datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id, 'status' => 6 ], [], false, [ 'uom'=>1 ] );
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

				// if($datas['header']){
				// 	$args['action'] = 'update';
				// 	if( $isView ) $args['view'] = true;
				// 	$args['data'] = $datas;

				$args['data'] = $datas;
				unset( $args['new'] );

				$args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'serial2' => 'Task',
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

	public function import_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'import',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['import'],
		);

		do_action( 'wcwh_templating', 'import/import-taskSchedule.php', $this->tplName['import'], $args );
	}

	public function export_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['export'],
		);

		do_action( 'wcwh_templating', 'export/export-taskSchedule.php', $this->tplName['export'], $args );
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/taskSchedule-row.php', $this->tplName['row'] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/taskScheduleListing.php" ); 
			$Inst = new WCWH_TaskSchedule_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->warehouse = $this->warehouse;

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

			$metas = [ 'remark', 'recursive_period', '_serial2', 'last_run_date', '_item_number' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			if( ! $order ) $order = ['a.lupdate_at'=>'DESC','a.doc_date'=>'DESC'];

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			foreach($datas as $key => $value){
				$datas[$key]['doc_date'] = date('Y-m-d', strtotime($value['doc_date']));
			}

			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}