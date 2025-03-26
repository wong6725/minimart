<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ServiceCharge_Class" ) ) include_once( WCWH_DIR . "/includes/classes/servicecharge.php" );  

if ( !class_exists( "WCWH_ServiceCharge_Controller" ) ) 
{

class WCWH_ServiceCharge_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_service_charge";

	protected $primary_key = "id";

	private $temp_data = array();

	public $Notices;
	public $className = "ServiceCharge_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newCharge',
		'import' => 'importCharge',
		'export' => 'exportCharge',
	);

	public $useFlag = false;

	private $unique_field = array( 'code' );

	protected $tempImpData = [];
	protected $importExpiry = 900;//15*60;

	protected $warehouse = array();
	protected $view_outlet = false;

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
		$this->Logic = new WCWH_ServiceCharge_Class( $this->db_wpdb );
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
			'id' => '',
			'code' => '',
			'scode' => '',
			'type' => '',
			'from_amt' => '',
			'to_amt' => '',
			'from_currency' => 'MYR',
			'to_currency' => 'DEF',
			'charge' => '',
			'charge_type' => 'flat',
			'since' => '',
			'desc' => '',
			'status' => 1,
			'flag' => ($this->useFlag )? 0 : 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => ''
		);
	}

	protected function get_uniqueFields()
	{
		return $this->unique_field;
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
				case 'update':
				if($action == 'update')
				{
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				}
				
				if( (! isset( $datas['from_amt'] ) || ! $datas['from_amt']) || (! isset( $datas['to_amt'] ) || ! $datas['to_amt']) || !$datas['type'])
				{
					$succ = false;
					$this->Notices->set_notice( 'insufficient-data', 'error' );			
				}
				else
				{
					if($datas['from_amt']>$datas['to_amt'])
					{
						$succ = false;
						$this->Notices->set_notice( "'To Amount' value should not be smaller than 'From Amount'", 'error');
					}
					else
					{
						//---- //-------
						$sc_record = $this->Logic->get_infos(['type'=>$datas['type'],'status'=>'1']);
						if($sc_record)
						{
							foreach ($sc_record as $key) 
							{
								if($datas['id'] && $key['id'] == $datas['id'])
								{
									continue;
								}
								if( ($datas['from_currency'] == $key['from_currency'] && $datas['to_currency'] == $key['to_currency'] ) && ((($datas['from_amt'] >= $key['from_amt']) && ($datas['from_amt'] <= $key['to_amt'])) || (($datas['to_amt'] >= $key['from_amt']) && ($datas['to_amt'] <= $key['to_amt']))) )
								{
									$succ = false;
									$this->Notices->set_notice( "Amount Range Overlapping. Service Charge ID:".$key['id'], 'error');
									break;
								}
								if( ($datas['from_currency'] == $key['from_currency'] && $datas['to_currency'] == $key['to_currency'] ) && ($datas['from_amt']<=$key['from_amt'] && $datas['to_amt']>= $key['to_amt']) )
								{
									$succ= false;
									$this->Notices->set_notice( "Amount Charged is Defined in Between this Range. Service Charge ID:".$key['id'], 'error');
									break;
								}
							}
						}
					}
				}

				break;
				case 'restore':
				case 'delete':
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

	public function validate_unique( $action, $datas = array() )
	{
		$succ = true;

		$unique = $this->get_uniqueFields();
		if( $unique )
		{
			foreach( $unique as $key )
			{
				$result = $this->Logic->get_infos( [ $key => $datas[$key] ], [], true );
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
			$this->Notices->set_notice( 'not-unique', 'error' );

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
					$datas['from_amt'] = round($datas['from_amt'],2);
					$datas['to_amt'] = round($datas['to_amt'],2);

					if( !$datas['type'] || !(in_array($datas['type'], ['bank_in'])) )
					{
						$succ = false;
						$this->Notices->set_notice( 'Undefined Type of Services', 'error' );
					}
					
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					if(!$this->validate( $action , $datas ))
					{
						$succ =false;
					}

					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{

						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
							$this->Notices->set_notice( 'Duplicated Code', 'error' );
						}

						$datas['created_by'] = $user_id;
						$datas['created_at'] = $now;

						if(!$datas['code'])
						{
							$this->temp_data = $datas;
							$datas['code'] = apply_filters( 'warehouse_generate_docno', $datas['code'], $this->section_id );
							$this->temp_data = array();
						}

						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
						$isSave = true;
					}

					if( $datas[ $this->get_primaryKey() ] && $action == 'update' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
							$this->Notices->set_notice( 'Duplicated Code', 'error' );
						}
						//if( $datas['parent'] == $datas[ $this->get_primaryKey() ] ) $datas['parent'] = 0;
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
				//---------------------------------------------------------------------------------
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

					if( $this->tempImpData )
					{
						$import_key = apply_filters( 'wcwh_generate_token', $this->section_id.'import' );
						$this->save_transient( $import_key, $this->tempImpData, $this->importExpiry );

						$Inst = new WCWH_Listing();
						
						$columns = $this->im_ex_default_column();
						$cols = $columns['cols'];
						$cols['imp_operation'] = 'Operation';
						$cols['ope_notice'] = 'Notice';
						
						$outcome['import_simulate'] = $Inst->get_listing( $cols, 
				        	$this->tempImpData, 
				        	[], 
				        	[ 'datas' ], 
				        	[ 'off_footer'=>true, 'list_only'=>true ]
				        );
				        $outcome['import_key'] = $import_key;
					}
				break;
				case "confirm-import":
					if( ! $datas['import_key'] )
					{
						$succ = false;
					}

					if( $succ )
					{
						$previous_simulated = $this->load_transient( $datas['import_key'], false );
						if( ! $previous_simulated )
						{
							$succ = false;
							$this->Notices->set_notice( 'Import preview session ended, please re-import again.', 'error' );
						}

						if( $succ )
						{
							$succ = $this->import_handler( $previous_simulated );
						}

						if( $succ )
						{
							$this->del_transient( $datas['import_key'] );
						}
					}
				break;
				case "export":
					$section = get_section( $this->section_id );
					$datas['filename'] = 'Service_Charge';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					$params['status'] = $datas['status'];

					$succ = $this->export_data( $datas, $params );
				break;
				//-------------------------------------------------------------------------------
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

			$exists = $this->Logic->get_infos( [ 'id'=>$id ], [], false );
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

				$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['code'] );
				if( ! $succ )
				{
					$this->Notices->set_notice( 'arrange-fail', 'error' );
				}
			}
		}

		return $succ;
	}


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = [], $args = [] )
	{
		$default_column = [];

		$default_column['cols'] = [
			'code' => 'Service Charge Code',
			'type' => 'Service Type*',
			'from_amt' => 'From Amount*',
			'to_amt' => 'To Amount*',
			'from_currency' => 'From Currency',
			'to_currency' => 'To Currency',
			'charge' => 'Amount Charged*',
			'charge_type' => 'Charge Type',
			'since' => 'Date Since',
			'desc' => 'Remark',
			'status' => 'Status',
		];

		if( $this->useFlag ) $cols['flag'] = 'Usage';

		$default_column['title'] = array_values( $default_column['cols'] );

		$default_column['default'] = array_keys( $default_column['cols'] );

		$default_column['match'] = array( 'code' );

		$default_column['required'] = array( 'type', 'from_amt', 'to_amt', 'charge' );

		return $default_column;
	}

	public function export_data_handler( $params = [], $args = [] )
	{
		return $this->Logic->get_export_data( $params, [], false, [], [], [] );
	}

	public function import_data_handler( $datas, $args = [], $simulate = false )
	{
		if( ! $datas ) return false;

		$succ = true;
		$columns = $this->im_ex_default_column();
		$match = $columns['match'];
		$required = $columns['required'];

		$impDatas = [];
		foreach( $datas as $i => $data )
		{
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
					$this->Notices->set_notice( 'Uploaded Data missing required fields', 'error' );
					$succ = false;
					break;
				}
			}

			//----------------------------------------------------------------- Map Data

			$data['status'] = ( $data['status'] == '0' )? 0 : 1;

			//-----------------------------------------------------------------

			$id = 0; $prev = [];
			if( !empty( $match ) )
			{
				foreach( $match as $key )
				{
					if( ! empty( $data[ $key ] ) )
					{
						$found = apply_filters( 'wcwh_get_service_charge', [ $key=>$data[ $key ] ], [], true, [] );
						if( $found )
						{
							$id = $found['id'];
							$prev = $found;
							break;
						}
					}
				}
			}
			
			$rowDat = $data;
			if( $id )	//record found; update
			{
				$data['id'] = $id;
				$rowDat['operation'] = 'update';

				if( (int)$prev['status'] != (int)$data['status'] && (int)$data['status'] <= 0 )	//delete
				{
					$data['status'] = 0;
				}
				else if( (int)$prev['status'] != (int)$data['status'] && (int)$data['status'] > 0 )	//restore
				{
					$data['status'] = 1;
				}

				$rowDat['datas'] = $data;
				$impDatas[] = $rowDat;
			}
			else 		//record not found; save
			{
				$rowDat['datas'] = $data;
				$rowDat['operation'] = 'save';
				$impDatas[] = $rowDat;
			}
		}

		//pd($impDatas);exit;
		
		if( $succ && $impDatas )
		{
			$succ = $this->import_handler( $impDatas, $simulate );
			$this->tempImpData = $impDatas;
		}

		if( ! $succ )
			$this->Notices->set_notice( 'Import Failed', 'error' );

		return $succ;
	}

	public function import_handler( &$impDatas = [], $simulate = false )
	{
		if( ! $impDatas ) return false;

		$succ = true;
		wpdb_start_transaction( $this->db_wpdb );

		foreach( $impDatas as $i => $line )
		{
			if($line )
			{
				$outcome = $this->action_handler( $line['operation'], $line['datas'], [], false );
				if( ! $outcome['succ'] ) 
				{
					$succ = false;
					$impDatas[ $i ]['ope_status'] = 0;
					$temp_notices = $this->Notices->get_notice();
					foreach ($temp_notices as $key => $value) 
					{
						$temp_notices[$key] = $temp_notices[$key]['message'];
					}
					$impDatas[ $i ]['ope_notice'] = implode( ", ",$temp_notices );
				}
				else
				{
					$impDatas[ $i ]['ope_status'] = 1;
					$impDatas[ $i ]['ope_notice'] = '';
				}
			}
		}

		if( $simulate )
			wpdb_end_transaction( false, $this->db_wpdb );
		else
			wpdb_end_transaction( $succ, $this->db_wpdb );

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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Service Charge" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Service Charge"
				>
					<?php echo $actions['save'] ?> Service Charge
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

		do_action( 'wcwh_templating', 'import/import-servicecharge.php', $this->tplName['import'], $args );
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

		do_action( 'wcwh_templating', 'export/export-servicecharge.php', $this->tplName['export'], $args );
	}


	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook' 		=> $this->section_id.'_form',
			'action' 	=> 'save',
			'token' 	=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];

			$datas = $this->Logic->get_infos( $filters, [], true, [ 'parent'=>1, 'address'=>'default' ] );
			if( $datas )
			{
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				
				$args['data'] = $datas;
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/servicecharge-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/servicecharge-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/servicechargeListing.php" ); 
			$Inst = new WCWH_ServiceCharge_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			//$Inst->set_args( [ 'per_page_row'=>50 ] );

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

			$datas = $this->Logic->get_infos( $filters, $order, false, [ 'parent'=>1, 'tree'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}