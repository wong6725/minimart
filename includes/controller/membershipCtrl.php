<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Membership_Class" ) ) include_once( WCWH_DIR . "/includes/classes/membership.php" ); 

if ( !class_exists( "WCWH_Membership_Controller" ) ) 
{
	
class WCWH_Membership_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_membership";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Membership_Controller";
	public $Logic;

	public $tplName = array(
		'new' => 'newMembership',
		'export' => 'exportMembership',
		'print' => 'printMembership',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	protected $serial_key = 'member_serial';

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
		$this->Logic = new WCWH_Membership_Class( $this->db_wpdb );
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

		$this->Logic->setWarehouse( $this->warehouse );
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'customer_id' => 0,
			'serial' => '',
			'pin' => '',
			'total_debit' => 0,
			'total_used' => 0,
			'balance' => 0,
			'point' => 0,
			'phone_no' => '',
			'email' => '',
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
		return array(
			[ 'customer_id' ],
		);
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
					if( empty( $datas['new_pin'] ) )
					{
						$succ = false;
						$this->Notices->set_notice( 'Please set 6 Digit Pin', 'warning' );
					}
					else
					{
						if( strlen( $datas['new_pin'] ) != 6 )
						{
							$succ = false;
							$this->Notices->set_notice( 'Please use 6 Digit Number for Pin', 'warning' );
						}
						if( ! $datas['confirm_pin'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'Confirm Pin is Required', 'warning' );
						}
						if( $succ && $datas['new_pin'] != $datas['confirm_pin'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'Pin Confirmation Not Identical', 'warning' );
						}
					}
				break;
				case 'update':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( $succ && !empty( $datas['new_pin'] ) )
					{
						if( strlen( $datas['new_pin'] ) != 6 )
						{
							$succ = false;
							$this->Notices->set_notice( 'Please use 6 Digit Number for Pin', 'warning' );
						}
						if( ! $datas['old_pin'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'Old Pin Required for Pin Changes', 'warning' );
						}
						else
						{
							$member = $this->Logic->get_infos( [ 'id'=>$datas['id'] ], [], true, [] );
							if( $member && ! wp_check_password( $datas['old_pin'], $member['pin'] ) )
							{
								$succ = false;
								$this->Notices->set_notice( 'Incorrect Old Pin! If forgotten, please proceed to reset Pin', 'warning' );
							}
						}
						if( ! $datas['confirm_pin'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'Confirm Pin is Required', 'warning' );
						}
						if( $succ && $datas['new_pin'] != $datas['confirm_pin'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'Pin Confirmation Not Identical', 'warning' );
						}
					}
				break;
				case 'update':
				case 'restore':
				case 'delete':
				case 'approve':
				case 'reject':
				case 'new-serial':
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

	public function validate_unique( $action, $datas = array() )
	{
		$succ = true;

		$unique = $this->get_uniqueFields();
		if( $unique )
		{
			foreach( $unique as $keys )
			{
				$filter = [];
				if( is_array( $keys ) )
				{
					foreach( $keys as $key )
					{
						$filter[$key] = $datas[$key];
					}
				}
				else
				{
					$filter = [ $keys=>$datas[$keys] ];
				}
				$result = $this->Logic->get_infos( $filter, [], true, [] ); //'usage'=>1
				if( $result ) 
				{	
					if( ! $datas[ $this->get_primaryKey() ] || 
						( $datas[ $this->get_primaryKey() ] && $datas[ $this->get_primaryKey() ] != $result[ $this->get_primaryKey() ] ) )
					{
						$succ = false;
					}
				}
			}
		}

		if( ! $succ )
			$this->Notices->set_notice( 'Member already created, please double confirm.', 'error' );

		return $succ;
	}

	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
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

			$datas['lupdate_by'] = $user_id;
			$datas['lupdate_at'] = $now;

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$datas = $this->data_sanitizing( $datas );

					$pass_update = false;
					if( !empty( $datas['new_pin'] ) )
					{
						$datas['pin'] = wp_hash_password( $datas['new_pin'] );
						unset( $datas['new_pin'] );
						unset( $datas['old_pin'] );
						unset( $datas['confirm_pin'] );
						$pass_update = true;
					}

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
						}

						$datas['created_by'] = $user_id;
						$datas['created_at'] = $now;

						do {
							$datas['serial'] = apply_filters( 'warehouse_renew_docno', $datas['serial'], $this->serial_key );
							
							$found = $this->Logic->get_infos( [ 'serial'=>$datas['serial'] ], [], true, [] );
						} while( $found );

						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
						$isSave = true;
					}

					if( $datas[ $this->get_primaryKey() ] && $action == 'update' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
						}	
					}

					if( $succ )
					{
						$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
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

							if( $pass_update )
							{
								$user = $this->Logic->get_infos( [ 'id'=>$result['id'] ], [], true, [ 'get_user'=>1 ] );
								if( $user )
								{
									global $wpdb;
									$this->prefix = $wpdb->prefix;
									$this->tbl = 'users';
									$this->primary_key = 'ID';
									$this->update( $user['user_id'], [ 'user_pass'=>trim( $datas['pin'] ) ] );
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
					}
				break;
				case "delete":
				case "delete-permanent":
				case "restore":
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
				case 'new-serial':
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$exist = $this->Logic->get_infos( [ 'id' => $id ], [], true );
							if( $exist )
							{
								if( $this->warehouse['code'] && $this->warehouse['parent'] > 0 && $this->warehouse['indication'] <= 0 )
								{
									$remote = apply_filters( 'wcwh_api_request', 'membership_new_no', $id, $this->warehouse['code'], $this->section_id, [ 'id'=>$id ] );
							
									if( ! $remote['succ'] )
									{
										$succ = false;
										$proceed = false;
										$this->Notices->set_notice( $remote['notice'], 'error' );
									}
									else
									{
										$remote_result = $remote['result'];
										if( $remote_result['succ'] )
										{
											$proceed = true;
											if($remote_result['datas'])
											{	
												$outcome['modal_data'] = $remote_result['datas'];
												$outcome['modal_form'] = 'remote_new_serial';
												$outcome['modal']['tpl'] = '';
				                                $outcome['modal']['service'] = $this->section_id.'_action';
				                                $outcome['modal']['actionBtn'] = [ 'print', 'close' ];
				                                $outcome['modal']['modal'] = "wcwhModalPrint";
				                                $outcome['modal']['title'] = "Remote View";
											}
										}
										else
										{
											$succ = false;
											$proceed = false;
											$this->Notices->set_notice( $remote_result['notification'], 'error' );
										}
									}
								}
								else
								{
									$dat = [];
									$metas['prev_serial'] = $exist['serial'];
									do {
										$dat['serial'] = apply_filters( 'warehouse_renew_docno', $exist['serial'], $this->serial_key );
										
										$found = $this->Logic->get_infos( [ 'serial'=>$dat['serial'] ], [], true, [] );
									} while( $found );

									$dat['id'] = $id;
									$result = $this->Logic->action_handler( 'update', $dat, $metas, $obj );
									if( ! $result['succ'] )
									{
										$succ = false;
										$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
										break;
									}
								}
							}
							else
							{
								$succ = false;
								$this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
							}

							if( $succ )
							{
								$outcome['id'][] = $result['id'];
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case 'reset-pin':
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$exist = $this->Logic->get_infos( [ 'id' => $id ], [], true );
							if( $exist )
							{
								$reset_pin = generateRangeSerial( 100000, 999999 );

								if( $this->warehouse['code'] && $this->warehouse['parent'] > 0 && $this->warehouse['indication'] <= 0 )
								{
									$remote = apply_filters( 'wcwh_api_request', 'membership_reset_pin', $id, $this->warehouse['code'], $this->section_id, [ 'id'=>$id ] );
							
									if( ! $remote['succ'] )
									{
										$succ = false;
										$proceed = false;
										$this->Notices->set_notice( $remote['notice'], 'error' );
									}
									else
									{
										$remote_result = $remote['result'];
										if( $remote_result['succ'] )
										{
											$proceed = true;
											if($remote_result['datas'])
											{	
												$outcome['modal_data'] = $remote_result['datas'];
												$outcome['modal_form'] = 'remote_reset_pin';
												$outcome['modal']['tpl'] = '';
						                        $outcome['modal']['service'] = $this->section_id.'_action';
						                        $outcome['modal']['actionBtn'] = [ 'close' ];
						                        $outcome['modal']['modal'] = "wcwhModalPrint";
						                        $outcome['modal']['title'] = "Pin";
											}
										}
										else
										{
											$succ = false;
											$proceed = false;
											$this->Notices->set_notice( $remote_result['notification'], 'error' );
										}
									}
								}
								else
								{
									$dat = [];
									$dat['pin'] = wp_hash_password( $reset_pin );

									$dat['id'] = $id;
									$result = $this->Logic->action_handler( 'update', $dat, $metas, $obj );
									if( ! $result['succ'] )
									{
										$succ = false;
										$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
										break;
									}
									else
									{
										$user = $this->Logic->get_infos( [ 'id'=>$id ], [], true, [ 'get_user'=>1 ] );
										if( $user )
										{
											global $wpdb;
											$this->prefix = $wpdb->prefix;
											$this->tbl = 'users';
											$this->primary_key = 'ID';
											$this->update( $user['user_id'], [ 'user_pass'=>trim( $dat['pin'] ) ] );
										}

										$outcome['modal_data'] = [
											'name' => $exist['name'],
											'uid' => $exist['uid'],
											'code' => $exist['code'],
											'phone_no' => $exist['phone_no'],
											'email' => $exist['email'],
											'reset_pin' => $reset_pin,
											'pin' => $dat['pin'],
										];
										$outcome['modal_form'] = 'remote_reset_pin';
										$outcome['modal']['tpl'] = '';
				                        $outcome['modal']['service'] = $this->section_id.'_action';
				                        $outcome['modal']['actionBtn'] = [ 'close' ];
				                        $outcome['modal']['modal'] = "wcwhModalPrint";
				                        $outcome['modal']['title'] = "Pin";
									}
								}
							}
							else
							{
								$succ = false;
								$this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
							}

							if( $succ )
							{
								$outcome['id'][] = $result['id'];
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "export":
					$datas['filename'] = 'membership';

					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( isset( $datas['status'] ) ) $params['status'] = $datas['status'];

					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
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


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		return $this->Logic->get_export_data( $params );
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$not_acc_type = $this->setting['wh_customer']['non_editable_by_acc_type'];
			
			$filters = [ 'status'=>'1'];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			if( $not_acc_type ) $filters['not_acc_type'] = $not_acc_type;
			$employee = apply_filters( 'wcwh_get_customer', $filters, [], false, ['account'=>1,'is_member'=>0] );
			
	        echo '<div id="membership_reference_content" class="col-md-8">';
	        echo '<select id="membership_reference" class="select2 triggerChange barcodeTrigger" data-change="#membership_action" data-placeholder="Add Membership by Select: Employer ID/ Serial/ Acc Type/ Name">';
	        echo '<option></option>';
	        foreach( $employee as $i => $emp )
	        {
	        	echo '<option 
                            value="'.$emp['code'].'" 
                            data-uid="'.$emp['uid'].'" 
                            data-code="'.$emp['code'].'" 
                            data-serial="'.$emp['serial'].'"
                            data-name="'.$emp['name'].'"
                >'. $emp['uid'].', '.$emp['serial'].', '.$emp['acc_name'].', '.$emp['name'] .'</option>';
	        }
	        echo '</select>';
	        echo '</div>';
		}
	}

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
				<button id="membership_action" class="display-none btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Membership"
					data-title="<?php echo $actions['save'] ?> Membership" 
					data-action="membership_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#membership_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> Membership
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'export':
				if( current_user_cans( [ 'export_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Membership" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Membership"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function gen_form( $id = 0 )
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
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$filters = [ 'code'=>$id ];
			//if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			$data = apply_filters( 'wcwh_get_customer', $filters, [], true, ['account'=>1] );
			if($data)
			{
				$args['data']['customer_id'] = $data['id'];
				
				$member = [ $data['uid'], $data['code'], $data['acc_name'], $data['name'] ];
				$args['data']['member'] = implode( ", ", $member );
				$args['data']['email'] = $data['email'];
				$args['data']['phone_no'] = $data['phone_no'];
			}
		}

		do_action( 'wcwh_get_template', 'form/membership-form.php', $args );
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $ref_datas = [] )
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
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$datas = ( $ref_datas )? $ref_datas : $this->Logic->get_infos( $filters, [], true, [ 'account'=>1 ] );
			if( $datas )
			{
				$metas = get_member_meta( $id );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				
				$args['data'] = $datas;
				if( $metas )
				{
					foreach( $metas as $key => $value )
					{
						$args['data'][$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
					}
				}
				
				$member = [ $datas['uid'], $datas['code'], $datas['acc_name'], $datas['name'] ];
				$args['data']['member'] = implode( ", ", $member );
				$args['data']['email'] = ( $datas['email'] )? $datas['email'] : $datas['customer_email'];
				$args['data']['phone_no'] = ( $datas['phone_no'] )? $datas['phone_no'] : $datas['customer_phone'];

				if( ! $isView )
				{
					$args['data']['full_uid'] = $args['data']['uid'];
					$args['data']['uid'] = substr( $args['data']['uid'], strlen( $args['data']['uid'] ) - 6 );
				}

				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/membership-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/membership-form.php', $args );
		}
	}

	public function print_tpl()
	{
		$tpl_code = "memberlabel0001";
		$tpl = apply_filters( 'wcwh_get_suitable_template', $tpl_code );
		
		if( $tpl )
		{
			do_action( 'wcwh_templating', $tpl['tpl_path'].$tpl['tpl_file'], 'customer_label', $args );
		}
	}

	public function remote_new_serial( $ref_datas = [] )
	{
		$id = ( $ref_datas['id'] )? $ref_datas['id'] : 0;
		if( $id )
		{
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$datas = ( $ref_datas )? $ref_datas : $this->Logic->get_infos( $filters, [], true, [ 'account'=>1 ] );

			$args['data'] = $datas;

			if( $datas['wh_code'] )
			{
				$estate = explode( "-", $datas['wh_code'] );
				$args['data']['estate'] = $estate[1];
			}
			
			$args['quality'] = 'M';
			$args['size'] = 4.4;
		}

		$tpl_code = "memberlabel0001";
		$tpl = apply_filters( 'wcwh_get_suitable_template', $tpl_code );
		
		if( $tpl )
		{
			do_action( 'wcwh_get_template', $tpl['tpl_path'].$tpl['tpl_file'], $args );
		}
	}

	public function remote_reset_pin( $ref_datas = [] )
	{
		if( $ref_datas['reset_pin'] )
		{
			echo "<h3>New Pin: {$ref_datas['reset_pin']}</h3>";
			echo "<p>This Pin used as Old Pin during Pin Changes!</p>";

			echo "<h5>Name: {$ref_datas['name']}</h5>";
			echo "<h5>Employee No: {$ref_datas['uid']}</h5>";
			echo "<h5>Customer No: {$ref_datas['code']}</h5>";
			echo "<h5>Phone No: {$ref_datas['phone_no']}</h5>";
			echo "<h5>Email: {$ref_datas['email']}</h5>";
		}
	}

	/*public function export_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $this->section_id,
			'seller'	=> $this->warehouse['id'],
		);

		do_action( 'wcwh_templating', 'export/export-membership.php', $this->tplName['export'], $args );
	}*/

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/membershipListing.php" ); 
			$Inst = new WCWH_Membership_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#origin' => [ 'width' => '70px' ],
				'#status' => [ 'width' => '90px' ],
			];

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-section'=>$this->section_id,
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, [ 'group'=>1, 'account'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();

		?>
		</form>
		<?php
	}
	
} //class

}