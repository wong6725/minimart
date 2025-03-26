<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_SelfBill_Class" ) ) include_once( WCWH_DIR . "/includes/classes/self-bill.php" ); 
if ( !class_exists( "WCWH_EInvoice_Convert" ) ) include_once( WCWH_DIR . "/includes/classes/einvoice-convert.php" ); 

if ( !class_exists( "WCWH_SelfBill_Controller" ) ) 
{

class WCWH_SelfBill_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_self_bill";

	public $Notices;
	public $className = "SelfBill_Controller";

	public $Logic;
	public $Einv;

	public $tplName = array(
		'new' => 'newINV',
		'row' => 'rowINV',
		'inv' => 'printINV',
	);

	public $useFlag = false;
	public $directPost = true;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 6,9 ];

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
		$this->Logic = new WCWH_SelfBill_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->processing_stat = $this->processing_stat;

		$this->Einv = new WCWH_EInvoice_Convert();
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
				case 'save':
					if( ! $datas['header']['po_doc'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'update-header':
				case 'delete':
				case 'post':
				case 'unpost':
				case 'approve':
				case 'reject':
				case "complete":
				case "incomplete":
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'cancel':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( empty( $datas['remark'] ) )
					{
						$succ = false;
						$this->Notices->set_notice( 'Remark required for Cancel Document', 'warning' );
					}
				break;
			}
		}

		return $succ;
	}

	public function action_handler( $action, $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		$count_succ = 0;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				//case "update":
					$header = $datas['header'];
					$detail = [];
					
					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;
					if( !empty( $header['po_doc'] ) )
					{	
						$po_doc = $header['po_doc']; unset( $header['po_doc'] );
						$header['po_doc'] = implode( ',', $po_doc );

						$ref_headers = $this->Logic->get_header( [ 'doc_id'=>$po_doc, 'doc_type'=>'none' ], [], false, [ 'company'=>1 ] );
						if( $ref_headers )
						{
							$detail = [];
							$subtotal = 0;
							foreach( $ref_headers as $i => $ref )
							{
								$metas = get_document_meta( $ref['doc_id'] );
								$ref = $this->combine_meta_data( $ref, $metas );

								$ref_detail = $this->Logic->get_detail( [ 'doc_id'=>$ref['doc_id'] ], [], false, [ 'usage'=>1 ] );
								if( $ref_detail )
								{
									foreach( $ref_detail as $j => $item )
									{
										$detail_metas = get_document_meta( $item['doc_id'], '', $item['item_id'] );
		        						$item = $this->combine_meta_data( $item, $detail_metas );

		        						$row = [];
										$row['product_id'] = $item['product_id'];
										$row['uom_id'] = $item['uom_id'];
										$row['bqty'] = $item['bqty'];
										$row['bunit'] = $item['bunit'];
										$row['ref_doc_id'] = $item['doc_id'];
										$row['ref_item_id'] = $item['item_id'];

										$row['line_subtotal'] = $item['total_amount'];
										$row['uprice'] = round_to( $item['total_amount'] / $item['bqty'], 5 );

										$row['item_doc'] = $ref['docno'];

										$detail[] = $row;

										$subtotal+= $item['total_amount'];
									}
								}
							}

							$header['total'] = $header['subtotal'] = $subtotal;
						}
					}
					//pd($header);pd($detail,1);
					if( !empty($detail) && $succ )
					{
						$result = $this->Logic->child_action_handle( $action, $header, $detail );
						
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $succ )
						{
							foreach( $po_doc as $po_id )
							{
								update_document_meta( $po_id, 'self_billed', $result['id'] );
							}
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
					else
					{
						$succ = false;
						$this->Notices->set_notice( "There is empty detail.", 'warning' );
					}
				break;
				case "update-header":
					$header = $datas['header'];
					$detail = $datas['detail'];

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					$result = $this->Logic->child_action_handle( $action, $header, [] );
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
					}
				break;
				case "delete":
				case "post":
				case "complete":
				case "incomplete":
				case "close":
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

							if( $succ && $action == 'delete' )
							{
								$po_doc = get_document_meta( $id, 'po_doc', 0, true );
								if( $po_doc )
								{
									$po_doc = explode( ",", $po_doc );
									foreach( $po_doc as $po_id )
									{
										delete_document_meta( $po_id, 'self_billed' );
									}
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
							
							$proceed = false;
							$f = [ 'direction'=>'out', 'section'=>$this->section_id, 'ref_id'=>$id, 'status'=>1 ];
							$syncs = apply_filters( 'wcwh_get_sync', $f, [], false, [] );
							if( $syncs && count( $syncs ) > 0 )
							{
								foreach( $syncs as $idx => $sync )
								{
									if( $sync['handshake'] <= 0 )//&& empty( (int)$sync['lsync_at'] )
									{
										$r = apply_filters( 'wcwh_sync_handler', 'delete', [ 'id'=>$sync['id'] ] );
										if( ! $r['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( 'Remove sync failed.', 'error' );
											break;
										}
										else
										{
											$proceed = true;
										}
									}
								}
							}
							else
							{
								$proceed = true;
							}

							//remote check unpost availability
							if( ! $proceed && $succ )
							{
								$wh_code = $this->refs['einv_id'];
								$api_url = $this->refs['einv_url'];
								$remote = apply_filters( 'wcwh_api_request', 'unpost_myinvoice', $id, $wh_code, $this->section_id, [], $api_url );
								if( ! $remote['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( $remote['notice'], 'error' );
								}
								else
								{
									$remote_result = $remote['result'];
									if( $remote_result['succ'] )
									{
										$proceed = true;
									}
									else
									{
										$succ = false;
										$this->Notices->set_notice( $remote_result['notification'], 'error' );
									}
								}
							}

							if( ! $proceed )
							{
								$succ = false;
								if( ! $this->Notices->has_notice() )
								$this->Notices->set_notice( 'Document synced, please double check on client side.', 'error' );
							}
							
							if( $proceed && $succ )
							{
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
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "cancel":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( !$datas['remark'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'Need Remark', 'error' );
					}
					else
					{
						if($ids)
						{
							foreach ($ids as $id)
							{
								$header = [];
								$header['doc_id'] = $id;

								$doc = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [] );
								if( empty( $doc ) ) continue;

								$succ = true;
								$proceed = false;
								
								$wh_code = $this->refs['einv_id'];
								$api_url = $this->refs['einv_url'];
								$remote = apply_filters( 'wcwh_api_request', 'cancel_myinvoice', $id, $wh_code, $this->section_id, [$datas], $api_url );
							
								if( ! $remote['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( $remote['notice'], 'error' );
								}
								else
								{
									$remote_result = $remote['result'];
									if( $remote_result['succ'] )
									{
										$proceed = true;
									}
									else
									{
										$succ = false;
										$this->Notices->set_notice( $remote_result['notification'], 'error' );
									}
								}

								if( $proceed && $succ )
								{
									$operation = true; $error_count = 0;
									do {
										if( $doc['status'] == 9 ) 
										{
											$act = 'incomplete';
											$doc['status'] = 6;
										}
										else if( $doc['status'] == 6 ) 
										{
											$act = 'unpost';
											$doc['status'] = 1;
										}

										$header = [];
										$header['doc_id'] = $id;
										$result = $this->Logic->child_action_handle( $act, $header );
							            if( ! $result['succ'] )
							            {
							            	$error_count++;
							                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
							            }
							            
							            if( $error_count > 0 ) break;

							            if( $doc['status'] < 3 ) 
							            {
							            	$operation = false;
							            	break;
							            }
									} while( $operation === true );

									if( $error_count > 0 ) $succ = false;
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

			$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false );
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

				if( ( $handled[ $ref_id ]['status'] >= 6 && $action == 'post' ) || ( $handled[ $ref_id ]['status'] == 1 && $action == 'unpost' ) )
				{
					$einv = $this->refs['einv_id'];
					$remote_url = $this->refs['einv_url'];
					$succ = apply_filters( 'wcwh_einv_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $einv, $remote_url, [], true );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
					}
				}
			}
		}

		return $succ;
	}

	public function myinvoice_handler( $action = '', $response = [] )
	{
		if( empty( $action ) || empty( $response ) || empty( $response['eirno'] ) ) return false;
		if( ! in_array( $action, [ 'myinv_api_transaction', 'myinv_lhdn_search_document' ] ) ) return false;

		$this->Notices->reset_operation_notice();
		$succ = true;

		$doc = $this->Logic->get_header( [ 'doc_id'=>$response['eirno'], 'doc_type'=>'none' ], [], true, [ 'company'=>1, 'usage'=>1 ] );
		if( empty( $doc ) ) return false;

		$datas = []; $irb_status = '';

		$datas['header']['warehouse_id'] = $doc['warehouse_id'];
		$datas['header']['docno'] = $doc['docno'];
		$datas['header']['doc_type'] = $doc['doc_type'];
		$datas['header']['doc_date'] = $doc['doc_date'];
		$datas['header']['parent'] = $doc['parent'];
		
		$datas['header']['doc_id'] = $response['eirno'];
		$datas['header']['uuid'] = $response['uuid'];
		$datas['header']['ref_uuid'] = $response['ref_uuid'];
		$datas['header']['longid'] = $response['longid'];
		$datas['header']['submission_id'] = $response['submission_id'];
		$datas['header']['submission_date'] = $response['submission_date'];
		$datas['header']['validate_date'] = $response['validate_date'];
		$datas['header']['irb_status'] = $irb_status = strtoupper( $response['irb_status'] );
		if( !empty( $response['submitter_email'] ) ) $datas['header']['submitter_email'] = $response['submitter_email'];
		if( !empty( $response['submission_error'] ) ) $datas['header']['submission_error'] = $response['submission_error'];
		
		$result = $this->action_handler( 'update-header', $datas, [], false );
		if( ! $result['succ'] )
		{
			$succ = false;
		    $this->Notices->set_notices( $this->Notices->get_operation_notice() );
		}

		if( $succ )
		{	
			if( $doc['doc_type'] == 'sb_invoice' )
			{
				if( $irb_status == 'V' && $doc['status'] != 9 )
				{
					$datas = [];
					$datas['doc_id'] = $response['eirno'];
					$action = 'complete';
					$result = $this->Logic->child_action_handle( $action, $datas );
					if( ! $result['succ'] )
				    {
				    	$succ = false;
				        $this->Notices->set_notices( $this->Notices->get_operation_notice() );
				    }
				}
				else if( ( $irb_status == 'C' || $irb_status == 'I' ) && $doc['status'] != 1 )
				{
					$datas = [];
					$datas['doc_id'] = $response['eirno'];
					
					$operation = true; $error_count = 0;
					do {
						if( $doc['status'] == 9 ) 
						{
							$act = 'incomplete';
							$doc['status'] = 6;
						}
						else if( $doc['status'] == 6 ) 
						{
							$act = 'unpost';
							$doc['status'] = 1;
						}
						else if( $doc['status'] == 3 ) 
						{
							$act = 'unpost';
							$doc['status'] = 1;
						}

						$result = $this->Logic->child_action_handle( $act, $datas );
			            if( ! $result['succ'] )
			            {
			            	$error_count++;
			                $this->Notices->set_notices( $this->Notices->get_operation_notice() );
			            }
			            
			            if( $error_count > 0 ) break;

			            if( $doc['status'] < 3 ) 
			            {
			            	$operation = false;
			            	break;
			            }
					} while( $operation === true );

					if( $error_count > 0 ) $succ = false;
				}
			}

			if( $succ && in_array( $irb_status, [ 'V', 'I' ] ) )
			{
				if( method_exists( $this, 'mailing' ) ) $this->mailing( $doc['doc_id'], $irb_status, $doc );
			}
		}

		return $succ;
	}

		public function mailing( $ref_id = 0, $action = '', $doc = [] )
		{
			if( ! $ref_id || ! $action ) return;

			if( empty( $doc ) )
			{
				$doc = $this->Logic->get_header( [ 'doc_id'=>$ref_id, 'doc_type'=>'none' ], [], true, [ 'company'=>1, 'usage'=>1 ] );
			}
			if( ! $doc ) return;

			set_time_limit(180); 

			$metas = $this->Logic->get_document_meta( $ref_id );
			$doc = $this->combine_meta_data( $doc, $metas );

			$receivers = [];
			$creator_id = $doc['created_by'];
			$creator = get_user_by( 'id', $creator_id );
			if( !empty( $creator ) ) $receivers[] = $creator->user_email;
			if( !empty( $doc['submitter_email'] ) ) $receivers[] = $doc['submitter_email'];

			$subject = "Minimart - MyInvoice Notification for {$doc['docno']} is ";
			$message = '';

			$attachment = ''; $file_path = "";
			switch( $action )
			{
				case 'valid':
				case 'V':
					$subject.= "VALID";

					if( $doc['uuid'] && $doc['longid'] )
					{
	                    $doc['irb_url'] = $this->refs['irb_url'].$doc['uuid'].'/share/'.$doc['longid'];
	                    $doc['qrcode'] = apply_filters( 'qrcode_img_data', $doc['irb_url'], "M", 4.4, 2 );
					}
					$message.= apply_filters( 'wcwh_get_template_content', 'email/einv-valid.php', [ 'doc'=>$doc ] );

					$file_url = $this->refs['einv_url']."?action=myinv_einvoice_print&docno=".$doc['docno']."&uuid=".$doc['uuid'].'&longid='.$doc['longid'];
					$pdf = file_get_contents( $file_url );
					if( !empty( $pdf ) )
					{
						$temp_dir = sys_get_temp_dir();
						$file_path = $temp_dir."/{$doc['docno']}_{$doc['uuid']}.pdf";
						file_put_contents( $file_path, $pdf );
						
						$attachment = $file_path;
					}
				break;
				case 'invalid':
				case 'I':
					$subject.= "INVALID";
					
					if( !empty( $doc['submission_error'] ) ) $doc['submission_error'] = $error['submission_error'];

					$message.= apply_filters( 'wcwh_get_template_content', 'email/einv-invalid.php', [ 'doc'=>$doc ] );
				break;
			}

			$args = [
				'id' => 'lhdn_einvoice_mail',
				'section' => $this->section_id,
				'datas' => $doc,
				'ref_id' => $ref_id,
				'subject' => $subject,
				'message' => $message,
			];
			if( !empty( $attachment ) ) $args['attachment'] = realpath( $attachment );

			$args['recipient'] = implode( ",", $receivers );
			//if( current_user_cans( [ 'manage_options' ] ) ) 
				//$args['recipient'] = 'wid001@suburtiasa.com';

			if( !empty( $args['recipient'] ) )
			{
				do_action( 'wcwh_set_email', $args );
				do_action( 'wcwh_trigger_email', [] );

				if( !empty( $file_path ) ) unlink( $file_path );
			}
		}
		
	public function myinvoice_synced( $datas = [], $response = [] )
	{
		if( empty( $datas ) ) return;

		if( empty( $this->setting['myinvoice']['recipient'] ) ) return;

		$doc = $datas['details']['header'];
		$subject = "Minimart - Document {$doc['docno']} are ready on MyInvoice for Submission ";
		$message = "Document {$doc['docno']} are ready on MyInvoice for Submission.";
		$message.= "<br><br>For Quick Access: ";

		$link = $this->refs['einv_url']."wp-admin/admin.php?page=myinv_mnmart";
		$message.= "<a href='{$link}'>View On MyInvoice</a>";

		$args = [
			'id' => 'myinvoice_synced_mail',
			'section' => $this->section_id,
			'datas' => $doc,
			'ref_id' => $datas['ref_id'],
			'subject' => $subject,
			'message' => $message,
		];

		$args['recipient'] = $this->setting['myinvoice']['recipient'];
		//if( current_user_cans( [ 'manage_options' ] ) ) 
			//$args['recipient'] = 'wid001@suburtiasa.com';

		if( !empty( $args['recipient'] ) )
		{
			do_action( 'wcwh_set_email', $args );
			do_action( 'wcwh_trigger_email', [] );
		}
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
	    $supplier = apply_filters( 'wcwh_get_supplier', [ 'self_bill'=>1 ], [], false, [ 'usage'=>1 ] );

	    $options = options_data( $supplier, 'id', [ 'code', 'name' ], "Self Bill Invoice by Supplier" );
	    echo '<div id="self_bill_reference_content" class="col-md-7">';
	    wcwh_form_field( '', 
	        [ 'id'=>'self_bill_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	        	'attrs'=>['data-change="#self_bill_action"'], 'class'=>['select2','triggerChange'], 
	            'options'=> $options, 'offClass'=>true
	        ], 
	        ''
	    );
	    echo '</div>';
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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
				{
				?>
					<button id="self_bill_action" class="btn btn-sm btn-primary linkAction display-none" title="Create Self Bill Invoice"
						data-title="Create Self Bill Invoice" 
						data-action="self_bill_reference" data-service="<?php echo $this->section_id; ?>_action" 
						data-modal="wcwhModalForm" data-actions="close|submit" 
						data-source="#self_bill_reference" 
					>
						Create Invoice
						<i class="fa fa-plus-circle" aria-hidden="true"></i>
					</button>
				<?php
				}
			break;
		}
	}

	public function gen_form( $id = 0 )
	{
		$succ = true;

		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}
		
		if( ! is_array( $id ) && $id )
		{
			$supplier = apply_filters( 'wcwh_get_supplier', [ 'id'=>$id ], [], true, [ 'usage'=>1 ] );
			if( $supplier )
			{
				$args['data']['supplier_company_code'] = $supplier['code'];
				$args['data']['supplier_id'] = $supplier['id'];
				
				$filters = [ 'supplier_company_code'=>$supplier['code'], 'self_billed'=>'IS_NULL', 'doc_type'=>'purchase_order', 'doc_date_from'=>$this->refs['einv_start'], 'total_amount'=>'IS_NOT_NULL' ];
				$docs = $this->Logic->get_header( $filters, [], false, [ 'company'=>1, 'posting'=>1, 'meta'=>['supplier_company_code','self_billed', 'total_amount'] ] );
			}
			else
			{
				$succ = false;
				$this->Notices->set_notice( 'Supplier not found', 'error' );
				return $succ;
			}
			
			if( $docs )
			{
				$details = [];
				$po = [];
				$total_amount = 0; $tqty = 0; $j = 0;
		        foreach( $docs as $h => $doc )
		        {
		        	//$metas = $this->Logic->get_document_meta( $doc['doc_id'] );
					//$doc = $this->combine_meta_data( $doc, $metas );

					if( is_null( $doc['total_amount'] ) || $doc['total_amount'] < 0 ) continue;

		        	$po[ $doc['doc_id'] ] = $doc;

		        	$items = $this->Logic->get_detail( [ 'doc_id'=>$doc['doc_id'] ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1 ] );
		        	if( $items )
		        	foreach( $items as $i => $item )
		        	{
		        		$detail_metas = $this->Logic->get_document_meta( $doc['doc_id'], '', $item['item_id'] );
		        		$item = $this->combine_meta_data( $item, $detail_metas );

		        		$j++;
		        		$row['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($j).".</span>" : ($j).".";
		        		$row['docno'] = $doc['docno']."<br>".$doc['doc_date'];
		        		$row['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        		$row['uom_code'] = $item['uom_code'];

		        		$row['line_item'] = [ 
			        		'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk,
			        	];

			        	if( $item['custom_item'] ) 
			        	{
			        		$row['prdt_name'] = $item['custom_item'];
			        		if( $item['uom_id'] ) $row['uom_code'] = $item['uom_id'];
			        	}

			        	$row['bqty'] = round_to( $item['bqty'], 2 );
		        		$row['bunit'] = round_to( $item['sunit'], 2 );

		        		$row['total_amount'] = round_to( $item['total_amount'], 2, true );
		        		$row['sprice'] = round_to( 0, 2, true );
		        		if( $row['total_amount'] > 0 ) $row['sprice'] = round_to( $row['total_amount'] / $item['bqty'], 2, true );

		        		$total_amount+= $row['total_amount'];
		        		$tqty+= $row['bqty'];

		        		$details[] = $row;
		        	}
		        }

		        $final = [];
			    $final['prdt_name'] = '<strong>TOTAL:</strong>';
			    $final['bqty'] = '<strong>'.$tqty.'</strong>';
			    $final['total_amount'] = '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
			    $details[] = $final;

		        $args['data']['details'] = $details;
		        $args['po'] = $po;

		        $cols = [
		        	'num' => '',
		        	'docno' => 'PO',
		        	'prdt_name' => 'Item',
		        	'uom_code' => 'UOM',
		        	'bqty' => 'Qty',
		        	'bunit' => 'Metric (kg/l)',
		        	'sprice' => 'Price',
		        	'total_amount' => 'Amt',
		        ];

		        $Inst = new WCWH_Listing();

		        $args['render'] = $Inst->get_listing( $cols, 
		        	$args['data']['details'], 
		        	[], 
		        	$hides, 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}

			do_action( 'wcwh_get_template', 'form/selfBill-form.php', $args );
		}
		else
		{
			$succ = false;
			$this->Notices->set_notice( 'Please Select Supplier.', 'error' );
		}

		if($succ)
		{
			return $succ;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section' => $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'update-header',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'get_content' => $getContent,
			'seller'	=> $this->warehouse['code'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true );
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				$datas['po_doc'] = explode(",", $datas['po_doc']);
				$filters = [ 'doc_id'=>$datas['po_doc'], 'doc_type'=>'purchase_order' ];
				$args['po'] = $this->Logic->get_header( $filters, [], false, [ 'posting'=>1, 'meta'=>['supplier_company_code','self_billed', 'total_amount'] ] );

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1 ] );

				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();

				$hides = [];
		        	
		        if( $datas['details'] )
		        {	
		        	$total_amount = 0; $tqty = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $stk,
		        		];
		        		
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$item = $this->combine_meta_data( $item, $detail_metas );

		        		if( $datas['details'][$i]['custom_item'] ) 
		        		{
		        			$datas['details'][$i]['prdt_name'] = $item['custom_item'];
		        			if( $item['uom_id'] ) $datas['details'][$i]['uom_code'] = $item['uom_id'];
		        		}

		        		$datas['details'][$i]['docno'] = $item['item_doc'];

		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2 );
		        		$datas['details'][$i]['bunit'] = round_to( $item['bunit'], 2 );

		        		$datas['details'][$i]['uprice'] = round_to( $item['uprice'], 2, true );
		        		$datas['details'][$i]['line_subtotal'] = round_to( $item['line_subtotal'] , 2, true );
		        		
		        		$tqty+= $item['bqty'];
		        		$total_amount+= $item['line_subtotal'];
		        	}

		        	if( !$hides )
		        	{
		        		$sub = [];
			        	$sub['prdt_name'] = 'TOTAL:';
			        	$sub['bqty'] = round_to( $tqty, 2, true );
			        	$sub['line_subtotal'] = round_to( $total_amount, 2, true );
			        	$datas['details'][] = $sub;
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );

				$cols = [
		        	'num' => '',
		        	'docno' => 'PO',
		        	'prdt_name' => 'Item',
		        	'uom_code' => 'UOM',
		        	'bqty' => 'Qty',
		        	'bunit' => 'Metric (kg/l)',
		        	'uprice' => 'Price',
		        	'line_subtotal' => 'Amt',
		        ];

		        $args['render'] = $Inst->get_listing( $cols, 
		        	$datas['details'], 
		        	[], 
		        	$hides, 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/selfBill-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/selfBill-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/selfBillListing.php" ); 
			$Inst = new WCWH_SelfBill_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#cd' => [ 'width' => '120px' ],
			];

			$this->Logic->processing_stat = [1,6];

			$count = $this->Logic->count_statuses( $this->warehouse['code'] );
			if( $count ) $Inst->viewStats = $count;

			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 'process';
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			if( empty( $filters['warehouse_id'] ) )
			{
				$filters['warehouse_id'] = $this->warehouse['code'];
			}

			$metas = [ 'remark', 'ref_doc_id', 'ref_doc', 'supplier_company_code', 'uuid', 'longid', 'validate_date', 'irb_status' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas ], [], $limit );
			
			$datas = ( $datas )? $datas : array();
			//pd( $datas );
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}