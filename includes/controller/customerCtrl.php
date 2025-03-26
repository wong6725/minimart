<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Customer_Class" ) ) include_once( WCWH_DIR . "/includes/classes/customer.php" ); 

if ( !class_exists( "WCWH_Customer_Controller" ) ) 
{
	
class WCWH_Customer_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_customer";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Customer_Controller";
	public $Files;
	public $Logic;

	public $tplName = array(
		'new' => 'newCustomer',
		'import' => 'importCustomer',
		'export' => 'exportCustomer',
		'print' => 'printCustomer',
		'print_multi' => 'printMultiCustomer',
	);

	public $useFlag = false;

	private $temp_data = array();

	private $sub_serial_length = 3;

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		$this->Files = new WCWH_Files();
		$this->arrangement_init();

		$this->set_logic();

		//add_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10, 2 );
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
		$this->Logic = new WCWH_Customer_Class( $this->db_wpdb );
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
			'wh_code' => '',
			'name' => '',
			'uid' => '',
			'code' => '',
			'serial' => '',
			'acc_type' => 0,
			'origin' => 0,
			'cjob_id' => 0,
			'cgroup_id' => 0,
			'comp_id' => 0,
			'email' => '',
			'phone_no' => '',
			'parent' => 0,
			'auto_topup' => 0,
			'topup_percent' => '0.00',
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
			[ 'uid', 'acc_type' ],
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
				case 'update':
					if( !empty( $datas['uid'] ) && ( 
						( strlen( $datas['uid'] ) < 9 && strlen( $datas['uid'] ) > 6 ) || 
						( strlen( $datas['uid'] ) > 9 )
					) )
					{
						$succ = false;
						$this->Notices->set_notice( 'SAP Employee No. has 9 characters as full / less than 6 characters as partial', 'warning' );
					}
				break;
				case 'restore':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( $succ )
					{
						$custs = $this->Logic->get_infos( [ 'id'=>$datas['id'] ], [], false, [ 'account'=>1 ] );
						if( $custs )
						{
							foreach( $custs as $cust )
							{
								if( ! $cust['acc_stat'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'The Account Type '.$cust['acc_name'].' for the customer has been deleted/trashed! Please contact HRD Team.', 'warning' );
									break;
								}
							}
						}
					}
				break;
				case 'update':
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
			$this->Notices->set_notice( 'SAP employee No. repeated, please double check.', 'error' );

		return $succ;
	}

	public function validate_wh_code( $action, $datas = array(), $warehouse = array() )
	{
		$succ = true;

		if( in_array( $action, [ 'save', 'update' ] ) )
		{
			if( ( $warehouse && count( $warehouse ) > 1 ) && empty( $datas['wh_code'] ) )
			{
				$succ = false;
				$this->Notices->set_notice( 'invalid-input', 'error' );
			}
			if( ! $warehouse && ! empty( $datas['wh_code'] ) )
			{
				$succ = false;
				$this->Notices->set_notice( 'invalid-input', 'error' );
			}
		}

		return $succ;
	}

	public function docno_replacer( $sdocno, $doc_type = '' )
	{
		if( $doc_type && $doc_type == $this->section_id )
		{	
			$datas = $this->temp_data;
			$group = array();
			if( $datas['cgroup_id'] )
			{
				$group = apply_filters( 'wcwh_get_customer_group', [ 'id'=>$datas['cgroup_id'] ], [], true, [ 'usage'=>1 ] );
			}

			$find = [ 
				'group_code' => '{GroupCode}',
			];

			$replace = [ 
				'group_code' => ( $group['code'] )? $group['code'] : '',
			];

			$sdocno = str_replace( $find, $replace, $sdocno );
		}

		return $sdocno;
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
					$generate_serial = false;
					$attachment = $datas['attachment'];
					$files = $_FILES;
					
					$warehouse = array();
					if( ! empty( $datas['wh_code'] ) )
						$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'code'=>$datas['wh_code'] ] );
					else
						$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ] );
					if( ! $this->validate_wh_code( $action, $datas, $warehouse ) )
					{
						$succ = false;
					}
					if( $succ && $warehouse && count( $warehouse ) == 1 )
					{
						$datas['wh_code'] = $warehouse[0]['code'];
						$datas['warehouse_id'] = $warehouse[0]['id'];
					}

					if( ! $datas['code'] )
					{
						if( $datas[ $this->get_primaryKey() ] )
						{
							$scode = get_customer_meta( $datas[ $this->get_primaryKey() ], 'scode', true );
							$datas['code'] = ( $scode )? $scode : $datas['code'];
							
							/*if( ! $datas['code'] )
							{
								$exist = $this->Logic->get_infos( [ $this->get_primaryKey() => $datas[ $this->get_primaryKey() ] ], [], true );
								$datas['code'] = ( $exist['code'] )? $exist['code'] : $datas['code'];
							}*/
						}
						
						$this->temp_data = $datas;
							if( empty( $datas['code'] ) )
							{
								$datas['scode'] = apply_filters( 'warehouse_generate_docno', $datas['code'], $this->section_id );
								$datas['code'] = $datas['scode'];
								$generate_serial = true;
							}
						$this->temp_data = array();
					}

					$acc_type = apply_filters( 'wcwh_get_account_type', [ 'id'=>$datas['acc_type'] ], [], true, [] );
					if( ! $datas['cgroup_id'] && $acc_type['def_cgroup_id'] )
					{
						$datas['cgroup_id'] = $acc_type['def_cgroup_id'];
					}
					if( ! $datas['cgroup_id'] && $this->setting[ $this->section_id ]['default_credit_group'] )
						$datas['cgroup_id']	= $this->setting[ $this->section_id ]['default_credit_group'];

					if( $datas['cgroup_id'] > 0 && $datas[ $this->get_primaryKey() ] && $action == 'update' )
					{
						$exist = $this->Logic->get_infos( [ 'id' => $datas[ $this->get_primaryKey() ] ], [], true );
						if( $exist && $exist['acc_type'] != $datas['acc_type'] && ! current_user_cans( [ 'save_wh_credit' ] ) )
						{
							$datas['cgroup_id'] = $acc_type['def_cgroup_id'];
						}
					}

					if( $datas['usage_type'] && $this->setting[ $this->section_id ]['rms_credit_group'] )
					{
						$datas['cgroup_id'] = $this->setting[ $this->section_id ]['rms_credit_group'];
					}

					if( !empty( $datas['uid'] ) )
					{
						if( $acc_type )
						{
							if( strlen( $datas['uid'] ) >= 9 )
							{
								if( substr( $datas['uid'], 0, 3 ) != $acc_type['employee_prefix'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'SAP Employee No. does not match with SAP account type code: '.$acc_type['employee_prefix'], 'warning' );
								}
							}
							else if( strlen( $datas['uid'] ) <= 6 )
							{
								$acc_code = str_pad( $acc_type['employee_prefix'], 3, "0" );
								$uid = str_pad( $datas['uid'], 6, "0", STR_PAD_LEFT );
								$datas['uid'] = $acc_code.$uid;
							}
							else
							{
								$succ = false;
								$this->Notices->set_notice( 'SAP Employee No. has 9 characters as full / less than 6 characters as partial', 'warning' );
							}
						}
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

						$datas['serial'] = $datas['code'].str_pad( '1', $this->sub_serial_length, '0', STR_PAD_LEFT );
						$metas['serial_seq'] = 1;

						if( !empty( $datas['uid'] ) )
						{
							$metas['sapuid_date'] = $now;
						}

						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
						$isSave = true;
					}

					if( $datas[ $this->get_primaryKey() ] && $action == 'update' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
						}
						
						if( $generate_serial )
						{
							$seq = get_customer_meta( $datas[ $this->get_primaryKey() ], 'serial_seq', true );
							$datas['serial'] = $datas['code'].str_pad( $seq, $this->sub_serial_length, '0', STR_PAD_LEFT );
						}

						$sapuid = get_customer_meta( $datas['id'], 'sapuid', true );
						$sapuid = !empty( $sapuid )? $sapuid : '';

						if( $sapuid != $datas['uid'] )
						{
							$sapuid_date = get_customer_meta( $datas['id'], 'sapuid_date', true );

							$metas['prev_sapuid'] = $sapuid;
							$metas['prev_sapuid_date'] = $sapuid_date;

							$metas['sapuid_date'] = $now;
						}

						if( $datas['parent'] == $datas[ $this->get_primaryKey() ] ) $datas['parent'] = 0;

						$metas['usage_type'] = ( $metas['usage_type'] && $this->setting[ $this->section_id ]['rms_credit_group'] )? 1 : 0;
					}

					if( !empty( $datas['uid'] ) ) $metas['sapuid'] = $datas['uid'];

					$datas = $this->json_encoding( $datas );

					if( $succ )
					{
						$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $succ && $action == 'save' )
						{
							$cc = apply_filters( 'wcwh_update_customer_count', $result['id'], $datas['serial'], 0, 0, '+' );
							$cc = apply_filters( 'wcwh_update_customer_count', $result['id'], $datas['serial'], 0, 0, '-' );
						}

						if( $succ )
						{
							$datas['id'] = $result['id'];
							$wc = $this->Logic->woocommerce_customer_handler( $action, $datas );
							if( ! $wc['succ'] )
							{
								$succ = false;
								$this->Notices->set_notice( 'error', 'error' );
							}
						}

						if( $succ )
						{
							$outcome['id'][] = $result['id'];
							//$outcome['data'][] = $result['data'];
							$count_succ++;

							$cus_id = $result['id'];
							
							if( !empty( $files ))
							{
								$fr = $this->Files->upload_files( $files, $this->section_id, $cus_id );
								if( $fr )
								{
									$succ = $this->Files->attachment_handler( $fr, $this->section_id, $cus_id, true );
									if($succ) $this->Logic->update_metas( $cus_id, ['attachment'=> maybe_serialize( $fr )] );
								}
								else{
									$succ = false;
									$this->Notices->set_notice( 'File Upload Failed', 'error' );
								}

							}
							elseif( empty( $attachment )) 
							{
								$succ = $this->Files->attachment_handler( $attachment, $this->section_id, $cus_id );
								if($succ) delete_customer_meta($cus_id,'attachment');
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
								$seq = get_customer_meta( $id, 'serial_seq', true );
								$seq = ( ! $seq )? 1 : $seq;
								$new_seq = (int)$seq + 1;

								$dat = [];
								$dat['serial'] = $exist['code'].str_pad( $new_seq, $this->sub_serial_length, '0', STR_PAD_LEFT );
								$metas['serial_seq'] = $new_seq;

								$dat['id'] = $id;
								$result = $this->Logic->action_handler( 'update', $dat, $metas, $obj );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}

								if( $succ )
								{
									$cc = apply_filters( 'wcwh_update_customer_count', $dat['id'], $dat['serial'], 0, 0, '+' );
									$cc = apply_filters( 'wcwh_update_customer_count', $dat['id'], $dat['serial'], 0, 0, '-' );

									$wc = $this->Logic->woocommerce_customer_handler( $action, $datas );
									if( ! $wc['succ'] )
									{
										$succ = false;
										$this->Notices->set_notice( 'error', 'error' );
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
					$datas['filename'] = 'customer';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['acc_type'] ) ) $params['acc_type'] = $datas['acc_type'];
					if( isset( $datas['status'] ) ) $params['status'] = $datas['status'];

					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					// if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );
					
					$id = $datas['cusID']?$datas['cusID']:$datas['id'];
					$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
					
					$customer = $this->Logic->get_print_data( [ 'id' => $id,'seller'=>$this->warehouse['id'] ], [], false, [] );
					if($customer)
					{
						$params['customer'] = $customer;
						switch( strtolower( $datas['paper_size'] ) )
						{
							case 'default':
							default:
								ob_start();
								do_action( 'wcwh_get_template', 'template/doc-customer.php', $params );
								$content.= ob_get_clean();
								
								if( ! is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) || $datas['html'] > 0 )
								{
									echo $content;
								}
								else
								{
									$paper = [ 'size' => 'A4', 'orientation' => 'landscape'];
									$args = [ 'filename' => "Customer ID Card" ];
									do_action( 'dompdf_generator', $content, $paper, array(), $args );
								}
							break;
						}
					}
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


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		$default_column['title'] = array( 'Name' , 'Employee No.', 'Code', 'Barcode', 'Acc Type', 'Job', 'Group', 'Origin', 'Superior', 'Company', 'Email', 'Phone No.', 'Receipt', 'IBS', 'ID', 'Passport', 'Phase', 'Status', 'Created', 'Updated', 'Last Day Date' );

		$default_column['default'] = array( 'name', 'uid', 'code', 'serial', 'acc_type', 'cjob_id', 'cgroup_id', 'origin', 'parent', 'comp_id', 'email', 'phone_no', 'receipt', 'ibs', 'ID', 'passport', 'phase', 'status', 'created', 'updated', 'last_day' );
		$default_column['unique'] = array( 'uid', 'code', 'serial' );
		$default_column['required'] = array( 'name', 'acc_type', 'cgroup_id' );
		$default_column['unchange'] = array( 'receipt', 'status', 'created', 'updated' );
		$default_column['important'] = array();

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		return $this->Logic->get_export_data( $params );
	}

	protected function import_data_handler( $datas, $args = array() )
	{
		if( ! $datas ) return false;

		$succ = true;
		$columns = $this->im_ex_default_column();

		$unique = $columns['unique'];
		$unchange = $columns['unchange'];
		$required = $columns['required'];

		$imp_data = array();
		$repeated = array();
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
				if( $hasEmpty ) continue;
			}

			$id = 0;
			if( !empty( $unique ) )
			{
				foreach( $unique as $key )
				{
					if( ! empty( $data[ $key ] ) )
					{
						$found = apply_filters( 'wcwh_get_customer', [ $key=>$data[ $key ] ], [], true, [] );
						if( $found )
						{
							$id = $found['id'];
							break;
						}
					}
				}
			}
			if( $id > 0 )
			{
				$data['id'] = $id;
				$repeated[$i] = $data;
			}

			if( $data['uid'] )
			{
				$data['uid'] = $data['uid'];
			}

			if( $data['acc_type'] )
			{
				$key = ( $args['acc_type'] )? $args['acc_type'] : 'code';
				$dat = apply_filters( 'wcwh_get_account_type', [ $key=>$data['acc_type'] ], [], true, [] );
				$data['acc_type'] = ( $dat )? $dat['id'] : '';
			}

			if( $data['cjob_id'] )
			{
				$key = ( $args['cjob_id'] )? $args['cjob_id'] : 'code';
				$dat = apply_filters( 'wcwh_get_customer_job', [ $key=>$data['cjob_id'] ], [], true, [] );
				$data['cjob_id'] = ( $dat )? $dat['id'] : '';
			}

			if( $data['cgroup_id'] )
			{
				$key = ( $args['cgroup_id'] )? $args['cgroup_id'] : 'code';
				$dat = apply_filters( 'wcwh_get_customer_group', [ $key=>$data['cgroup_id'] ], [], true, [] );
				$data['cgroup_id'] = ( $dat )? $dat['id'] : '';
			}

			if( $data['origin'] )
			{
				$key = ( $args['origin'] )? $args['origin'] : 'code';
				$dat = apply_filters( 'wcwh_get_origin_group', [ $key=>$data['origin'] ], [], true, [] );
				$data['origin'] = ( $dat )? $dat['id'] : '';
			}

			if( $data['parent'] )
			{
				$key = ( $args['parent'] )? $args['parent'] : 'code';
				$dat = apply_filters( 'wcwh_get_customer', [ $key=>$data['parent'] ], [], true, [] );
				$data['parent'] = ( $dat )? $dat['id'] : '';
			}
			
			if( $data['comp_id'] )
			{
				$key = ( $args['comp_id'] )? $args['comp_id'] : 'code';
				$dat = apply_filters( 'wcwh_get_company', [ $key=>$data['comp_id'] ], [], true, [] );
				$data['comp_id'] = ( $dat )? $dat['id'] : '';
			}

			if( $unchange )
			{
				foreach( $unchange as $key )
				{
					unset( $data[$key] );
				}
			}
			//pd($data);
			$action = 'save';
			if( $data['id'] ) $action = 'update';
			$outcome = $this->action_handler( $action, $data );

			$imp_data[$i] = $data;
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
					data-title="<?php echo $actions['save'] ?> Customer" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Customer"
				>
					<?php echo $actions['save'] ?> Customer
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?> Customer" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?> Customer"
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
					data-title="<?php echo $actions['export'] ?> Customer" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Customer"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'print':
				if( current_user_cans( [ 'access_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['print_multi'] ?>" 
					data-title="<?php echo $actions['print'] ?> Customer" data-modal="wcwhModalImEx" 
					data-actions="close|printing" 
					title="<?php echo $actions['print'] ?> Customer"
				>
					<i class="fa fa-print" aria-hidden="true"></i>
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
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		$warehouses = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ] );
		if( sizeof( $warehouses ) <= 0 ) $args['select_warehouse'] = true;

		if( $id )
		{
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$meta = [ 'warehouse_id', 'serial_seq' ];
			$datas = $this->Logic->get_infos( $filters, [], true, [ 'parent'=>true, 'company'=>true, 'customer_group'=>true, 'meta'=>$meta ] );
			if( $datas )
			{
				$metas = [];
				if( isset( $filters['seller'] ) && $filters['seller'] > 0 )
				{
					$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
					$metas = get_customer_meta( $id, '', true, $dbname );
				}
				else
				{
					$metas = get_customer_meta( $id );
				}

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
				$attachs = $this->Files->get_infos( [ 'section_id'=>$this->section_id, 'ref_id'=>$id, 'seller'=>$args['seller'] ], [], false, [ 'usage'=>1 ] );
				if( $attachs )
				{
					if( $args['seller'] )
					{
						foreach( $attachs as $x => $attach )
						{
							if( $this->warehouse['api_url'] ) $attachs[$x]['api_url'] = $this->warehouse['api_url'];
						}
					}
					$args['attachment'] = $attachs;
				}

				if( ! $isView )
				{
					$args['data']['full_uid'] = $args['data']['uid'];
					$args['data']['uid'] = substr( $args['data']['uid'], strlen( $args['data']['uid'] ) - 6 );
				}

				unset( $args['new'] );
			}

			$user_credits = apply_filters( 'wc_credit_limit_get_client_credits', $id, [], $filters['seller'] );
			if( $user_credits )
			{
				$args['credit_info'] = [
					'total_creditable' => $user_credits['total_creditable'],
					'assigned_credit' => $user_credits['total_creditable'] - $user_credits['topup_credit'],
					'usable_credit' => $user_credits['usable_credit'],
					'topup_credit' => $user_credits['topup_credit'],
					'used_credit' => $user_credits['used_credit'],
					'from_date' => $user_credits['from_date'],
					'to_date' => $user_credits['to_date'],
				];
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/customer-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/customer-form.php', $args );
		}
	}

	public function print_tpl()
	{
		$tpl_code = "customerlabel0001";
		$tpl = apply_filters( 'wcwh_get_suitable_template', $tpl_code );
		if( $tpl )
		{
			do_action( 'wcwh_templating', $tpl['tpl_path'].$tpl['tpl_file'], 'customer_label', $args );
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
			'prefixName'=> 'customer',
		);
		
		do_action( 'wcwh_templating', 'import/import-customer.php', $this->tplName['import'], $args );
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
			'seller'	=> $this->warehouse['id'],
		);

		do_action( 'wcwh_templating', 'export/export-customer.php', $this->tplName['export'], $args );
	}

	public function printing_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $this->section_id,
			'seller'	=> $this->warehouse['id'],
		);

		do_action( 'wcwh_templating', 'form/customer-print-form.php', $this->tplName['print'], $args );
	}

	public function printing_multi_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['print_multi'],
			'section'	=> $this->section_id,
			'seller'	=> $this->warehouse['id'],
		);

		do_action( 'wcwh_templating', 'form/customer-print-multi-form.php', $this->tplName['print_multi'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/customerListing.php" ); 
			$Inst = new WCWH_Customer_Listing();
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

			// $Inst->bulks = array( 
			// 	'data-section'=>$this->section_id,
			// 	'data-tpl' => 'printMultiCustomer', 
			// 	'data-modal' => 'wcwhModalImEx',
			// 	'data-service' => $this->section_id.'_action', 
			// 	'data-form' => 'printMultiCustomer',
			// );

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, [ 'parent'=>1, 'company'=>1, 'group'=>1, 'job'=>1, 'origin'=>1, 'account'=>1, 'tree'=>1, 'count'=>1, 'photo'=>1, 'meta'=>['last_day'] ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}