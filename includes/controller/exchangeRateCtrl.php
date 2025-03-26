<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ExchangeRate_Class" ) ) include_once( WCWH_DIR . "/includes/classes/exchange-rate.php" ); 

if ( !class_exists( "WCWH_ExchangeRate_Controller" ) ) 
{
	
class WCWH_ExchangeRate_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_exchange_rate";

	protected $primary_key = "id";

	private $temp_data = array();

	public $Notices;
	public $className = "ExchangeRate_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newExchangeRate',
		'import' => 'importExchangeRate',
		'export' => 'exportExchangeRate',
	);

	public $useFlag = false;
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
		$this->Logic = new WCWH_ExchangeRate_Class( $this->db_wpdb );
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
			'docno' => '',
			'sdocno' => '',
			'title' => '',
			'from_currency' => '',
			'to_currency' => '',
			'base' => 1,
			'rate' => 0,
			'desc' => '',
			'since' => '',
			'status' => 1,
			'flag' => ( $this->useFlag )? 0 : 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}

	protected function get_updateFields()
	{
		return array(
			'from_currency' => '',
			'to_currency' => '',
			'rate' => 0,
			'desc' => '',
			'since' => '',
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
				case 'update':
				case 'restore':
				case 'delete':
				case 'approve':
				case 'reject':
				//case 'new-serial':
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

	/*
		public function error_checking( $action, $datas = array() )
		{
			$succ = true;

			$erResult = $this->Logic->get_infos( $filters, $order, false, [], [], $limit );
			
			foreach ($erResult as $key => $value)
			{
				//currency cannot be the same
				if ($datas['from_currency'] == $datas['to_currency'] )
				{
					$succ = false;
				}

				//only allow the same currency in one day. (Save)
				if($action == "save" && $datas['from_currency'] == $value['from_currency'] && $datas['to_currency'] == $value['to_currency'] && $datas['since'] == $value['since'] )
				{
					$succ = false;
				}

				//only allow the same currency in one day. (Update)
				if($action == "update" && $datas['docno'] != $value['docno'] && $datas['from_currency'] == $value['from_currency'] && $datas['to_currency'] == $value['to_currency'] && $datas['since'] == $value['since'] )
				{
					$succ = false;
				}
				
			}

			if( ! $succ )
				$this->Notices->set_notice( 'Record Found/Same Currency', 'error' );
		
			return $succ;
		}
	*/
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
				
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];
					
					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						$datas['created_by'] = $user_id;
						$datas['created_at'] = $now;

						$datas['sdocno'] =	empty( $datas['sdocno'] )? apply_filters( 'warehouse_generate_docno', $sdocno, $this->section_id ) : $datas['sdocno'];
						$datas['docno'] = empty( $datas['docno'] ) ? $datas['sdocno'] : $datas['docno'];

						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
						$isSave = true;
					}

					if( $datas[ $this->get_primaryKey() ] && $action == 'update' )
					{
						//$datas['created_by'] = 1;
						$datas = wp_parse_args( $datas, $this->get_updateFields() );
						unset($datas['docno']);
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
				case "export":
					$datas['filename'] = 'Exchange Rate ';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );

					$params['from_currency'] = $datas['from_currency'];
					$params['to_currency'] = $datas['to_currency'];
					$params['status'] = $datas['status'];
					$params['flag'] = $datas['flag'];
					$params['export_type'] = $datas['export_type']; 
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
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

					if( $this->tempImpData )
					{
						$import_key = apply_filters( 'wcwh_generate_token', $this->section_id.'import' );
						$this->save_transient( $import_key, $this->tempImpData, $this->importExpiry );

						$Inst = new WCWH_Listing();
						
						$columns = $this->im_ex_default_column();
						$cols = $columns['cols'];
						$required = $columns['required'];
						
						foreach ($cols as $key => $value) 
						{
							foreach ($required as $required_key => $required_value) 
							{
								if($key == $required_value)
								{
									$cols[$key] = $cols[$key]."<span class='asterisk'>*</span>";
								}
							}
						}

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
				
				$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'] );
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
	
	protected function im_ex_default_column( $params = [], $args = [] )
	{
		$default_column = [];

		switch( $params['export_type'] )
		{
			case 'latest':
				$default_column['cols'] = [
					'from_currency' => 'From Currency',
					'to_currency' => 'To Currency',
					'base' => 'Base',
					'rate' => 'Rate',
					'docno' => 'Doc No',
				];
			break;
			case 'default':
			default:
				$default_column['cols'] = [
					'docno' => 'Docno',
					'sdocno' => 'Sdocno',
					'from_currency' => 'From Currency',
					'to_currency' => 'To Currency',
					'rate' => 'Rate',
					'base' => 'Base',
					'desc' => 'Desc',
					'since' => 'Effective Date(YYYY-mm-dd)',
					'status' => 'Status',
					'flag' => 'Flag',
					'created_at' => 'Created A',
					'created_by' => 'Created By',
					'lupdate_at' => 'Updated',
					'lupdate_by' => 'Updated By',
				];
			break;
		}

		$default_column['title'] = array_values( $default_column['cols'] );
		$default_column['default'] = array_keys( $default_column['cols'] );
		$default_column['match'] = array( 'docno');
		$default_column['required'] = array( 'from_currency', 'to_currency', 'rate', 'since' );

		return $default_column;
	}

	protected function export_data_handler( $params = [], $args = [] )
	{
		$type = $params['export_type']; unset( $params['export_type'] );
		switch( $type )
		{
			case 'default':
				return $this->Logic->get_export_data( $params, [], false, [], [], [] );
			break;
			case 'latest':
				return $this->Logic->get_export_latest_data( $params, [], false, [], [], [] );
			break;
		}

	}
	
	public function import_data_handler( $datas, $args = array() , $simulate = false)
	{
		if( ! $datas ) return false;
	
		$succ = true;
		$columns = $this->im_ex_default_column();
	
		$unique = $columns['match'];
		$required = $columns['required'];

		$impDatas = array();
		//$repeated = array();

		foreach( $datas as $i => $data )
		{
			if(empty(array_filter($data)))
			{
				unset($data);
			}
			else
			{
				//----------------------------------------------------------------- Map Data
				$data['status'] = ( $data['status'] == '0' )? 0 : 1;
				//-----------------------------------------------------------------

				$id = 0; $prev = [];
				if( !empty( $unique ) )
				{
					foreach( $unique as $key )
					{
						if( ! empty( $data[ $key ] ) )
						{
							$found = apply_filters( 'wcwh_get_exchange_rate', [ $key=>$data[ $key ] ], [], true, [] );
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
		}

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
			$empty = false;
			$succ = true;
			if( $succ && $line )
			{
				$columns = $this->im_ex_default_column();
				$required = $columns['required'];
				//validation
				if( !empty( $required ) )
				{
					$hasEmpty = false;
					foreach( $required as $key )
					{
						if( empty( $line[ $key ] ) )
						{
							$hasEmpty = true;
						} 
					}
					if( $hasEmpty )
					{
						$succ = false;
						$empty = true;
						$impDatas[ $i ]['operation'] = 'error';
						$impDatas[ $i ]['ope_status'] = '-1';
						$impDatas[ $i ]['ope_notice'] = 'Missing Required Date';
					}
				}
				if($succ)
				{
					$outcome = $this->action_handler( $line['operation'], $line['datas'], [], false );
					if( ! $outcome['succ'] ) 
					{
						$succ = false;
						$impDatas[ $i ]['ope_status'] = '-1';
						$impDatas[ $i ]['ope_notice'] = $this->Notices->get_notice()[0]['message'];
					}
					else
					{
						$impDatas[ $i ]['ope_status'] = 1;
						$impDatas[ $i ]['ope_notice'] = '';
					}
				}
			}
		}

		if( $simulate )
			wpdb_end_transaction( false, $this->db_wpdb );
		else
			wpdb_end_transaction( $succ, $this->db_wpdb );
		
		if($empty)
		{
			$succ = false;
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
					data-title="<?php echo $actions['save'] ?> Exchange Rate" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Exchange Rate"
				>
					<?php echo $actions['save'] ?> Exchange Rate
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'export':
				if( current_user_cans( [ 'export_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Exchange Rate" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Exchange Rate"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?> Exchange Rate" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?> Exchange Rate"
				>
					<i class="fa fa-upload" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false )
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

		if( $id )
		{
			$filters = [ 'id' => $id ];
			
			$datas = $this->Logic->get_infos( $filters, [], true, [ ] );
			if( $datas )
			{
				//$metas = get_exchange_rate_meta( $id );

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
			do_action( 'wcwh_templating', 'form/exchangeRate-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/exchangeRate-form.php', $args );
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
			'prefixName'=> 'exchange_rate',
		);
		
		do_action( 'wcwh_templating', 'import/import-default.php', $this->tplName['import'], $args );
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
			'section'	=> $this->section_id,
		);

		do_action( 'wcwh_templating', 'export/export-exchangeRate.php', $this->tplName['export'], $args );
	}

	public function export_latest_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $this->section_id,
		);

		do_action( 'wcwh_templating', 'export/export-exchangeRateLatest.php', $this->tplName['export'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/exchangeRateListing.php" ); 
			$Inst = new WCWH_ExchangeRate_Listing();
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
		
			$datas = $this->Logic->get_infos( $filters, $order, false, [], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	
	public function latest_exchange_rate_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-latest-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-latest-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="wh_latest_exchange_rate_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/exrateListing.php" ); 
			$Inst = new WCWH_ExRate_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			
			$Inst->advSearch = array( 'isOn'=>1 );
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch_onoff();

			//$count = $this->Logic->count_statuses();
			//if( $count ) $Inst->viewStats = $count;
			
			$order = $Inst->get_data_ordering();
			//$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_latest_exchange_rate( $filters, $order, false, [], [], $limit );
			$datas = ( $datas )? $datas : array();
			//pd($datas);
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}