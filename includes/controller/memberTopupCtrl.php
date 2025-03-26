<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_MemberTopup_Class' ) ) include_once( WCWH_DIR . "/includes/classes/member-topup.php" ); 

if ( !class_exists( "WCWH_MemberTopup_Controller" ) ) 
{

class WCWH_MemberTopup_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_member_topup";

	protected $primary_key = "id";

	public $Notices;
	public $Files;
	public $className = "MemberTopup_Controller";

	public $Logic;

	public $custom_columns = [];

	public $tplName = array(
		'new' => 'newMemberTopup',
		'import' => 'importMemberTopup',
		'export' => 'exportMemberTopup',
	);

	public $doc_type = 'topup';

	public $useFlag = false;

	protected $import_data = array();

	protected $warehouse = array();
	protected $view_outlet = false;

	public $usageMode = 1;	// 1:DC->Estate, 2:Estate->DC approval

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		$this->Files = new WCWH_Files();

		$this->arrangement_init();

		$this->set_logic();
	}

	public function __destruct() {}

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
		$this->Logic = new WCWH_MemberTopup_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
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

		if( $this->usageMode != 1 )
			$this->Logic->setWarehouse( $this->warehouse );
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'warehouse_id' => '',
			'member_id' => '',
			'docno' => '',
			'sdocno' => '',
			'doc_type' => $this->doc_type,
			'amount' => 0,
			'remarks' => '',
			'status' => 1,
			'flag' => ( $this->useFlag )? 0 : 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}

	protected function get_uniqueFields()
	{
		return array();
	}

	protected function get_unneededFields()
	{
		return array( 
			'action', 
			'token', 
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
				case 'save':
					if( ! $_FILES && ! $datas['attachment'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'Attachment is required', 'warning' );
					}
				break;
				case 'update':
					if( ! isset( $datas['header']['id'] ) || ! $datas['header']['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}

					if( ! $_FILES && ! $datas['attachment'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'Attachment is required', 'warning' );
					}
				break;
				case 'restore':
				case 'delete':
				case 'post':
				case 'unpost':
				case 'approve':
				case 'reject':
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

	public function action_handler( $action = 'save', $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		
		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$isSave = false;
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$header = $datas['header'];
					$header = $this->data_sanitizing( $header );
					
					$header = ( $header )? $header : $datas['header'];
					$attachment = $datas['attachment'];
					$files = $_FILES;
					
					$header['lupdate_by'] = $user_id;
					$header['lupdate_at'] = $now;
					
					if( ! $header['warehouse_id'] )
					{
						$wh = $this->warehouse;
						if( ! $this->warehouse )
						{
							$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
						}
						$header['warehouse_id'] = $wh['code'];

						if( $header['member_id'] )
						{
							$f = [ 'id'=>$header['member_id'] ];
							if( $wh['id'] ) $f['seller'] = $wh['id'];

							$member = apply_filters( 'wcwh_get_membership', $f, [], true, [ 'account'=>1 ] );
							if( $member )
							{
								$header['customer_code'] = $member['code'];
							}
						}
					}

					$extracted = $this->extract_data( $header );
					$header = $extracted['datas'];
					$metas = $extracted['metas'];
					
					if( ! $header[ $this->get_primaryKey() ] && $action == 'save' )
					{
						$sdocno = "MT".get_current_time('YmdHis');
        				$header['sdocno'] =	empty( $header['sdocno'] )? apply_filters( 'warehouse_generate_docno', $sdocno, $this->section_id ) : $header['sdocno'];
						$header['docno'] = empty( $header['docno'] ) ? $header['sdocno'] : $header['docno'];

						$header['created_by'] = $user_id;
						$header['created_at'] = $now;

						$header = wp_parse_args( $header, $this->get_defaultFields() );
						
						$isSave = true;
						
					}
					else if( isset( $header[ $this->get_primaryKey() ] ) && $header[ $this->get_primaryKey() ] ) //update
					{	

					}

					$result = $this->Logic->action_handler( $action, $header, $metas );
					if( ! $result['succ'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'error', 'error' );
					}

					if( $succ )
					{
						$outcome['id'][] = $result['id'];

						$doc_id = $result['id'];
						if( !empty( $attachment ) )
						{
							$succ = $this->Files->attachment_handler( $attachment, $this->section_id, $doc_id );
						}
						if( !empty( $files ) )
						{
							$fr = $this->Files->upload_files( $files, $this->section_id, $doc_id );
							if( $fr )
							{
								update_member_transact_meta( $doc_id, 'attachments', maybe_serialize( $fr ) );
							}
							else{
								$succ = false;
								$this->Notices->set_notice( 'File Upload Failed', 'error' );
							}
						}

						if( $isSave )
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
				case "delete":
				case "restore":
				case "post":
				case "unpost":
					$datas['lupdate_by'] = $user_id;
					$datas['lupdate_at'] = $now;

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$datas['id'] = $id;
							$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							if( $succ && in_array( $action, [ 'post', 'unpost' ] ) && 
								( ! $this->view_outlet ) )
							{
								$succ = $this->topup_posting_handler( $result['id'], $action );
							}

							if( $succ )
							{
								$outcome['id'][] = $result['id'];

								//Doc Stage
								$dat = $result['data'];
								$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
								    'ref_type'	=> $this->section_id,
								    'ref_id'	=> $result['id'],
								    'action'	=> $action,
								    'status'    => $dat['status'],
								    'remark'	=> ( $metas['remark'] )? $metas['remark'] : '',
								] );
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "approve":
				case "reject":
					$datas['lupdate_by'] = $user_id;
					$datas['lupdate_at'] = $now;

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];
					
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$succ = apply_filters( 'wcwh_todo_external_action', $id, $this->section_id, $action, ( $metas['remark'] )? $metas['remark'] : '' );
							if( $succ )
							{
								$status = apply_filters( 'wcwh_get_status', $action );
							
								$datas['flag'] = 0;
								$datas['flag'] = ( $status > 0 )? 1 : ( ( $status < 0 )? -1 : $datas['flag'] );

								$datas['id'] = $id;
								$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}

								if( $succ )
								{
									$outcome['id'][] = $result['id'];

									//Doc Stage
									$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
									    'ref_type'	=> $this->section_id,
									    'ref_id'	=> $result['id'],
									    'action'	=> $action,
									    'status'    => $status,
									    'remark'	=> ( $metas['remark'] )? $metas['remark'] : '',
									] );
								}
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "import":
					$files = $this->files_grouping( $_FILES['import'] );
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
					$datas['filename'] = 'MemberTopup ';

					$params = [];
					$params['warehouse_id'] = $datas['warehouse_id'];
					
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					//if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];

					$date = current_time( 'Y-m-d' );
					if( $datas['on_date'] ) $datas['filename'].= date( 'Y-m-d', strtotime( $date ) );

					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$this->print_form( $datas['id'] );

					exit;
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

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

	public function after_action( $succ, $id, $action = "save" )
	{
		if( ! $id ) return $succ;

		if( $succ )
		{
			$id = is_array( $id )? $id : [ $id ];

			$exists = $this->Logic->get_infos( [ 'id' => $id ], [], false );
			$handled = [];
			foreach( $exists as $exist )
			{
				$handled[ $exist['id'] ] = $exist;
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

				if( $handled[ $ref_id ]['flag'] && $handled[ $ref_id ]['warehouse_id'] && $handled[ $ref_id ]['status'] >= 6 )
				{
					$seller = $handled[ $ref_id ]['warehouse_id'];
					$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $seller );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
						break;
					}
				}
			}
		}

		return $succ;
	}

	public function topup_posting_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		if( ! class_exists( 'WCWH_Membership_Class' ) ) include_once( WCWH_DIR . "/includes/classes/membership.php" ); 
		$Inst = new WCWH_Membership_Class( $this->db_wpdb );

		$succ = true;
		
		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_infos( [ 'id'=>$doc_id ], [], true, [] );
				if( $doc_header )
				{
					$succ = $Inst->update_member_debit( $doc_header['customer_id'], $doc_header['amount'], "+" );
					if( ! $succ )
	                {
	                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
	                }
				}
			break;
			case 'unpost':
				$doc_header = $this->Logic->get_infos( [ 'id'=>$doc_id ], [], true, [] );
				if( $doc_header )
				{
					$succ = $Inst->update_member_debit( $doc_header['customer_id'], $doc_header['amount'], "-" );
					if( ! $succ )
	                {
	                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
	                }
				}
			break;
		}

		return $succ;
	}


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		$default_column['title'] = [ 'Warehouse', 'Member', 'Docno', 'Sdocno', 'Doc Type', 'Amount', 'Remarks', 'status', 'flag', 
			'Customer Code' ];
		
		$default_column['default'] = [ 'warehouse_id', 'member_id', 'docno', 'sdocno', 'doc_type', 'amount', 'remarks', 'status', 'flag', 
			'customer_code' ];

		$default_column['unique'] = array();
		$default_column['required'] = array( 'amount' );

		return ( $this->custom_columns )? $this->custom_columns : $default_column;
	}

	public function export_data_handler( $params = array() )
	{
		$type = $params['export_type']; unset( $params['export_type'] );
		switch( $type )
		{
			case '':
			default:
				return $this->Logic->get_export_data( $params );
			break;
		}
	}

	public function import_data_handler( $datas, $args = array() )
	{
		if( ! $datas ) return false;

		$succ = true;
		$columns = $this->im_ex_default_column();
		
		$unique = $columns['unique'];
		$unchange = $columns['unchange'];
		$required = $columns['required'];

		if( $datas )
		{
			wpdb_start_transaction( $this->db_wpdb );
			
			foreach( $datas as $i => $data )
			{
				if( !empty( $unchange ) )
				{
					foreach( $unchange as $key )
					{
						unset( $data[$key] );
					}
				}
				
				//validation
				if( !empty( $required ) )
				{
					$hasEmpty = false;
					foreach( $required as $key )
					{
						if( empty( $data[ $key ] ) ) $hasEmpty = true;
					}
					if( $hasEmpty )
					{
						$this->Notices->set_notice( 'Data missing required fields', 'error' );
						$succ = false;
						break;
					}
				}
				if( ! $succ ) break;

				$curr = [];
				if( !empty( $unique ) )
				{
					foreach( $unique as $key )
					{
						if( ! empty( $data[ $key ] ) )
						{
							$found = $this->Logic->get_infos( [ $key=>$data[ $key ] ] );
							if( $found )
							{
								$curr = $found;
								break;
							}
						}
					}
				}
				if( $curr )
				{
					$data['id'] = $curr['id'];
				}
				
				//pd($data);exit;
				if( $succ )
				{
					$exists = $this->Logic->get_infos( [ 'sdocno' => $data['sdocno'] ], [], true );
					if( $exists )
					{
						$data['id'] = $exists['id'];

						if( $data['status'] > 0 && $exists['status'] > 0 )
						{
							unset($_FILES);
							$dat = [ 'header'=> [
								'warehouse_id' => $data['warehouse_id'],
								'member_id' => $data['member_id'],
								'docno' => $data['docno'],
								'doc_type' => $data['doc_type'],
								'amount' => $data['amount'],
								'remarks' => $data['remarks'],
								'id' => $data['id'],
							] ];
							$outcome = $this->action_handler( 'update', $dat, [], false );
							if( ! $outcome['succ'] ) 
							{
								$succ = false;
								break;
							}
							
							if( $succ && $data['status'] >= 6 && $exists['status'] < 6 )
							{
								$dat = [ 'id'=> $data['id'] ];
								$outcome = $this->action_handler( 'post', $dat, [], false );
								if( ! $outcome['succ'] ) 
								{
									$succ = false;
									break;
								}
							}
						}
						else if( $data['status'] > 0 && $exists['status'] <= 0 )
						{
							$dat = [ 'id' => $exists['id'] ];
							$outcome = $this->action_handler( 'restore', $dat, [], false );
							if( ! $outcome['succ'] ) 
							{
								$succ = false;
								break;
							}
							
							if( $succ && $data['status'] >= 6 )
							{
								$dat = [ 'id'=> $data['id'] ];
								$outcome = $this->action_handler( 'post', $dat, [], false );
								if( ! $outcome['succ'] ) 
								{
									$succ = false;
									break;
								}
							}
						}
						else if( $data['status'] <= 0 && $exists['status'] > 0 )
						{
							if( $succ && $exists['status'] >= 6 )
							{
								$dat = [ 'id'=> $data['id'] ];
								$outcome = $this->action_handler( 'unpost', $dat, [], false );
								if( ! $outcome['succ'] ) 
								{
									$succ = false;
									break;
								}
							}
							
							$dat = [ 'id' => $exists['id'] ];
							$outcome = $this->action_handler( 'delete', $dat, [], false );
							if( ! $outcome['succ'] ) 
							{
								$succ = false;
								break;
							}
						}
					}
					else
					{
						if( $data['status'] > 0 )
						{
							unset($_FILES);
							$dat = [ 'header'=> [
								'warehouse_id' => $data['warehouse_id'],
								'member_id' => $data['member_id'],
								'docno' => $data['docno'],
								'doc_type' => $data['doc_type'],
								'amount' => $data['amount'],
								'remarks' => $data['remarks'],
							] ];
							$outcome = $this->action_handler( 'save', $dat, [], false );
							if( ! $outcome['succ'] ) 
							{
								$succ = false;
								break;
							}

							if( $succ && $data['status'] >= 6 )
							{
								$dat = [ 'id'=> $outcome['id'] ];
								$outcome = $this->action_handler( 'post', $dat, [], false );
								if( ! $outcome['succ'] ) 
								{
									$succ = false;
									break;
								}
							}
						}
					}
				}
			}
			
			wpdb_end_transaction( $succ, $this->db_wpdb );
		}

		if( ! $succ )
			$this->Notices->set_notice( 'Import Failed', 'error' );

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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && 
					( ( $this->view_outlet && $this->usageMode == 1 ) || ( ! $this->view_outlet && $this->usageMode != 1 ) ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Member Topup" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Member Topup"
				>
					<?php echo $actions['save'] ?> Member Topup
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?>"
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
					data-title="<?php echo $actions['export'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = true )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];
		
		if( $id )
		{
			$filters = [ 'id' => $id ]; $ag = [];
			if( $this->view_outlet && $this->usageMode != 1 )
			{
				$filters['seller'] = $this->warehouse['id'];
				$ag = [ 'mst_seller' => $this->warehouse['id'] ];
			}

			$datas = $this->Logic->get_infos( $filters, [], true, $ag );
			if( $datas )
			{
				$metas = get_member_transact_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				$attachs = $this->Files->get_infos( [ 'section_id'=>$this->section_id, 'ref_id'=>$id ], [], false, [ 'usage'=>1 ] );
				if( $attachs )
				{
					$datas['attachment'] = $attachs;
				}
				
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

		        $args['data'] = $datas;
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/memberTopup-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/memberTopup-form.php', $args );
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

		do_action( 'wcwh_templating', 'import/import-memberTopup.php', $this->tplName['import'], $args );
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

		if( $this->filters ) $args['filters'] = $this->filters;

		do_action( 'wcwh_templating', 'export/export-memberTopup.php', $this->tplName['export'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/memberTopupListing.php" ); 
			$Inst = new WCWH_MemberTopup_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, [ 'customer'=>1, 'mst_seller'=>$this->warehouse['id'] ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}