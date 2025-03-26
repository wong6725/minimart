<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_SaleCDNote_Class" ) ) include_once( WCWH_DIR . "/includes/classes/sale-cdnote.php" ); 

if ( !class_exists( "WCWH_SaleCDNote_Controller" ) ) 
{

class WCWH_SaleCDNote_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_sale_cdnote";

	public $Notices;
	public $className = "SaleCDNote_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newCD',
		'row' => 'rowCD',
		'import' => 'importCD',
		//'export' => 'exportCD',
		'cn' => 'printCN',
		'dn' => 'printDN',

	);

	public $useFlag = false;
	public $directPost = true;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 3,6,9 ];

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
		$this->Logic = new WCWH_SaleCDNote_Class( $this->db_wpdb );
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
				case 'update':
				case 'save':
					if( ! $datas['detail'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					else
					{
						$match = true;

						if($datas['header']['note_action'] == 1) //1 = Seller issue Credit Note, CN must match with SO item
						{
							if(is_array($datas['detail']))
							{	
								$metas = $this->Logic->get_document_items_by_doc( $datas['header']['ref_doc_id'] );
								
								foreach ($datas['detail'] as $key => $value)
								{
									$arr[] = $value['product_id'];

									foreach ($metas as $key2 => $value2)
									{
										$arr2[] = $value2['product_id'];

										if($value['product_id'] == $value2['product_id'])
										{
											if($value['bqty'] > $value2['bqty']) //Check Qty cannot > ref SO's qty
											{
												$succ = false;
												$this->Notices->set_notice( 'Item '.$value['_item_number'].' \'s Qty > '.$datas['header']['ref_doc'].' \'s Qty', 'error' );
												break;
											}
										}
									}
								}
						
								$result = array_diff($arr,$arr2); //check item must inside ref SO
								
								if(!empty($result))
								{
									$succ = false;

									foreach ($result as $key3 => $value3) 
									{
										$this->Notices->set_notice( 'Item '.($key3+1).' Not Found In '.$datas['header']['ref_doc'], 'error' );
									}
								}
								
							}
						}
					}
				break;
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
				case "update":
					$header = $datas['header'];
					$detail = $datas['detail'];
					
					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;
					if( $header['ref_doc_id'] )
					{	
						$ref_header = $this->Logic->get_header( [ 'doc_id'=>$header['ref_doc_id'], 'doc_type'=>'none' ], [], true, [ 'company'=>1 ] );
						if( $ref_header )
						{
							$header['ref_warehouse'] = $ref_header['warehouse_id'];
							$header['ref_doc_type'] = $ref_header['doc_type'];
							$header['ref_doc'] = $ref_header['docno'];
							$header['parent'] = $header['ref_doc_id'];

							if( ! $header['doc_id'] )
							{
								$header['ref_status'] = $ref_header['status'];
							}
						}


						$einv_doc = $this->Logic->get_header( [ 'parent'=>$header['ref_doc_id'], 'doc_type'=>'e_invoice' ], [], true, [] );
						if( !empty( $einv_doc ) )
						{
							$header['submit_einv'] = 1;
						}
						$header['submit_einv'] = 1;
					}
					if( $detail )
					{
						$header['total'] = 0;
						
						foreach( $detail as $i => $row )
						{
							if( !$row['bqty'] && !$row['amount'] )
							{
								$succ = false;
								$this->Notices->set_notice( "Both column cannot be blank.", 'warning' );
							}
							
							//$detail[$i]['sprice'] = round_to(($row['amount']/$row['bqty']),2);
							$row['bqty'] = ( $row['bqty'] )? $row['bqty'] : 0;
							$detail[$i]['sprice'] = ( $row['bqty'] )? round_to( ( $row['amount']/$row['bqty'] ), 2 ) : 0;

							$detail[$i]['def_sprice'] = round_to( $detail[$i]['sprice'], 2 );

							$detail[$i]['line_subtotal'] = ($row['bqty']) ? $row['bqty'] * round_to( $detail[$i]['sprice'], 2 ) : round_to( $row['amount'], 2 );
							$detail[$i]['line_total'] = $detail[$i]['line_subtotal'];

							$header['subtotal']+= $detail[$i]['line_subtotal'];
							//$header['discounted_subtotal']+= $detail[$i]['line_total'];
							$header['total']+= $detail[$i]['line_total'];

							$total = $header['total'];

							/*foreach( $detail as $i => $row )
							{
								$rate = $row['line_total'] / $total;
								$detail[$i]['line_total'] = $header['total'] * $rate;
								$detail[$i]['sprice'] = ($row['bqty']) ? round_to( $detail[$i]['line_total'] / $row['bqty'], 5 ) : round_to( $detail[$i]['line_total'], 5 );
							}*/
						}
					}
					
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
				case "delete":
				case "post":
				case "unpost":
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

							if( $succ && in_array( $action, [ 'post' ] ) )
							{
								$doc = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [ 'meta'=>[ 'submit_einv' ] ] );
								if( !empty( $doc ) && $doc['submit_einv'] )
								{
									$action = 'confirm';
								}
							}

							$result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
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
				case "refute":
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
				case "approve":
				case "reject":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$succ = apply_filters( 'wcwh_todo_external_action', $id, $this->section_id, $action, ( $datas['remark'] )? $datas['remark'] : '' );
							
							if( $succ )
							{
								$status = apply_filters( 'wcwh_get_status', $action );

								$header = [];
								$header['doc_id'] = $id;
								$header['flag'] = 0;
								$header['flag'] = ( $status > 0 )? 1 : ( ( $status < 0 )? -1 : $header['flag'] );
								$result = $this->Logic->child_action_handle( $action, $header );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}
								
								if( $succ && $this->directPost && $header['flag'] )	
								{
									//approve = direct post, reject = direct delete
									($action == 'approve') ? $act = 'post' : $act = 'delete';

									$result = $this->Logic->child_action_handle( $act, $header );
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
									$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
										'ref_type'	=> $this->section_id,
										'ref_id'	=> $result['id'],
										'action'	=> $action,
										'status'    => $status,
										'remark'	=> ( $datas['remark'] )? $datas['remark'] : '',
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
				case "print":
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );
					
					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
						case 'sale_debitnote':
							$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
							$doc = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [] );
							
							if( $doc )
							{	
								$doc['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
								//metas
								$metas = get_document_meta( $id );
								$doc = $this->combine_meta_data( $doc, $metas );
								
								$date_format = get_option( 'date_format' );
								$params['heading']['docno'] = $doc['docno'];
								$params['heading']['remark'] = $doc['remark'];
								$params['heading']['infos'] = [
									'No.' => $doc['docno'],
									'UUID' => $doc['uuid'],
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
									'Ref. Doc.' => $doc['ref_doc'],
									'Ref UUID' => $doc['ref_uuid'],
								];
								$params['heading']['note_reason'] = $doc['note_reason'];

								if( !empty( $doc['uuid'] ) )
									$params['heading']['irb_qr'] = $this->refs['irb_url'].$doc['uuid'].'/share/'.$doc['longid'];
								if( empty( $doc['ref_uuid'] ) ) unset( $params['heading']['infos']['Ref UUID'] );
								
								if( $this->useFlag )
								{
									$f = [ 
										'ref_id' => $doc['doc_id'], 
										'section' => $this->section_id, 
										'action_type' => 'approval',
										'status' => 1,
										'flag' => 1,
									];
									$todo = apply_filters( 'wcwh_get_todo', $f, [], true, [ 'arrangement'=>1 ] );
									if( $todo )
									{
										$uid = [];
										if( $todo['created_by'] ) $uid[] = $todo['created_by'];
										if( $todo['action_by'] ) $uid[] = $todo['action_by'];
										$actors = get_simple_users( $uid, 0 );

										$creator = $actors[ $todo['created_by'] ];
										$approver = $actors[ $todo['action_by'] ];
										$params['prepare_by'] = ( $creator['name'] )? $creator['name'] : $creator['display_name'];
										$params['approve_by'] = ( $approver['name'] )? $approver['name'] : $approver['display_name'];
									}
								}
								
								$client = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'default' ] );
								if( $client )
								{
									$params['heading']['first_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $client['contact_person'],
										'company'    => $client['name'],
										'address_1'  => $client['address_1'],
										'city'       => $client['city'],
										'state'      => $client['state'],
										'postcode'   => $client['postcode'],
										'country'    => $client['country'],
										'phone'		 => $client['contact_no'],
									] );
								}
								$self = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ 'address'=>'default' ] );
								if( $self )
								{
									$comp = apply_filters( 'wcwh_get_company', [ 'id'=>$self['comp_id'] ], [], true, [ 'address'=>'default' ] );
									$addr = ( !empty( $self['address_1'] ) || ( empty( $self['address_1'] ) && empty( $comp['address_1'] ) ) )? $self : $comp;
									$params['heading']['second_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $addr['contact_person'],
										'company'    => $addr['name'],
										'address_1'  => $addr['address_1'],
										'city'       => $addr['city'],
										'state'      => $addr['state'],
										'postcode'   => $addr['postcode'],
										'country'    => $addr['country'],
										'phone'		 => $addr['contact_no'],
									] );
								}

								if( $doc['status'] > 0 )
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'usage'=>1, 'ref'=>1 ] );
								else
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'ref'=>1 ] );
							    
							    if( $doc['details'] )
							    {
							    	$detail = [];
									
							        foreach( $doc['details'] as $i => $item )
							        {
							        	$detail_metas = get_document_meta( $id, '', $item['item_id'] );
		        						$item = $this->combine_meta_data( $item, $detail_metas );
										
										$row = [];
							        	$row['item'] = $item['prdt_code'].' - '.$item['prdt_name'];
										
							        	$row['qty'] = round_to( $item['bqty'], 2 );
										($item['amount'] && $item['bqty']) ? $row['uprice'] = round_to( ($item['amount'] / $item['bqty']),2) : '';
			
							        	$row['total_amount'] = round_to( $item['amount'], 2 );

							        	$detail[] = $row;
							        }

							        $params['detail'] = $detail;
							    }
							}

							switch( strtolower( $datas['paper_size'] ) )
							{
								case 'default':
								default:
									ob_start();
										do_action( 'wcwh_get_template', 'template/doc-sale-debit-note.php', $params );
									$content.= ob_get_clean();
									
									if( ! is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) || $datas['html'] > 0 )
									{
										echo $content;
									}
									else
									{
										$paper = [ 'size' => 'A4', 'orientation' => 'portrait' ];
										$args = [ 'filename' => $params['heading']['docno'] ];
										do_action( 'dompdf_generator', $content, $paper, array(), $args );
									}
								break;
							}
						break;
						case 'sale_creditnote':
							$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
							$doc = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [] );
							if( $doc )
							{	
								$doc['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
								//metas
								$metas = get_document_meta( $id );
								$doc = $this->combine_meta_data( $doc, $metas );
							
								$date_format = get_option( 'date_format' );
								$params['heading']['docno'] = $doc['docno'];
								$params['heading']['remark'] = $doc['remark'];
								$params['heading']['infos'] = [
									'No.' => $doc['docno'],
									'UUID' => $doc['uuid'],
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
									'Ref. Doc.' => $doc['ref_doc'],
									'Ref UUID' => $doc['ref_uuid'],
								];
								$params['heading']['note_reason'] = $doc['note_reason'];

								if( !empty( $doc['uuid'] ) )
									$params['heading']['irb_qr'] = $this->refs['irb_url'].$doc['uuid'].'/share/'.$doc['longid'];
								if( empty( $doc['ref_uuid'] ) ) unset( $params['heading']['infos']['Ref UUID'] );
								
								if( $this->useFlag )
								{
									$f = [ 
										'ref_id' => $doc['doc_id'], 
										'section' => $this->section_id, 
										'action_type' => 'approval',
										'status' => 1,
										'flag' => 1,
									];
									$todo = apply_filters( 'wcwh_get_todo', $f, [], true, [ 'arrangement'=>1 ] );
									if( $todo )
									{
										$uid = [];
										if( $todo['created_by'] ) $uid[] = $todo['created_by'];
										if( $todo['action_by'] ) $uid[] = $todo['action_by'];
										$actors = get_simple_users( $uid, 0 );

										$creator = $actors[ $todo['created_by'] ];
										$approver = $actors[ $todo['action_by'] ];
										$params['prepare_by'] = ( $creator['name'] )? $creator['name'] : $creator['display_name'];
										$params['approve_by'] = ( $approver['name'] )? $approver['name'] : $approver['display_name'];
									}
								}
								
								$client = apply_filters( 'wcwh_get_client', [ 'code'=>$doc['client_company_code'] ], [], true, [ 'address'=>'default' ] );
								if( $client )
								{
									$params['heading']['first_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $client['contact_person'],
										'company'    => $client['name'],
										'address_1'  => $client['address_1'],
										'city'       => $client['city'],
										'state'      => $client['state'],
										'postcode'   => $client['postcode'],
										'country'    => $client['country'],
										'phone'		 => $client['contact_no'],
									] );
								}
								$self = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ 'address'=>'default' ] );
								if( $self )
								{
									$comp = apply_filters( 'wcwh_get_company', [ 'id'=>$self['comp_id'] ], [], true, [ 'address'=>'default' ] );
									$addr = ( !empty( $self['address_1'] ) || ( empty( $self['address_1'] ) && empty( $comp['address_1'] ) ) )? $self : $comp;
									$params['heading']['second_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $addr['contact_person'],
										'company'    => $addr['name'],
										'address_1'  => $addr['address_1'],
										'city'       => $addr['city'],
										'state'      => $addr['state'],
										'postcode'   => $addr['postcode'],
										'country'    => $addr['country'],
										'phone'		 => $addr['contact_no'],
									] );
								}

								if( $doc['status'] > 0 )
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'usage'=>1, 'ref'=>1 ] );
								else
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'ref'=>1 ] );
							    
							    if( $doc['details'] )
							    {
							    	$detail = [];
									
							        foreach( $doc['details'] as $i => $item )
							        {
							        	$detail_metas = get_document_meta( $id, '', $item['item_id'] );
		        						$item = $this->combine_meta_data( $item, $detail_metas );

										$row = [];
							        	$row['item'] = $item['prdt_code'].' - '.$item['prdt_name'];
										
							        	//if( $datas['view_type'] == 'category' ) $row['item'] = $item['cat_code'].' - '.$item['cat_name'];
							        	//$row['uom'] = $item['uom_code'];
							        	$row['qty'] = round_to( ($item['bqty']), 2 );
										($item['amount'] && $item['bqty']) ? $row['uprice'] = round_to( ( ($item['amount'] / $item['bqty']) ) ,2) : '';
			
							        	$row['total_amount'] = round_to( ($item['amount']), 2 );

							        	$detail[] = $row;
							        }

							        $params['detail'] = $detail;
							    }
							}

							switch( strtolower( $datas['paper_size'] ) )
							{
								case 'default':
								default:
									ob_start();
										do_action( 'wcwh_get_template', 'template/doc-sale-credit-note.php', $params );
									$content.= ob_get_clean();
									
									if( ! is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) || $datas['html'] > 0 )
									{
										echo $content;
									}
									else
									{
										$paper = [ 'size' => 'A4', 'orientation' => 'portrait' ];
										$args = [ 'filename' => $params['heading']['docno'] ];
										do_action( 'dompdf_generator', $content, $paper, array(), $args );
									}
								break;
							}
						break;
					}
					exit;
				break;
				/*
				case "export":
					//pd($datas);
					//exit();
					$datas['filename'] = 'C/D_Note';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['supplier_code'] ) ) $params['supplier_company_code'] = $datas['supplier_code'];
					if( !empty( $datas['status'] ) ) $params['status'] = $datas['status'];
					if( !empty( $datas['note_action'] ) ) $params['note_action'] = $datas['note_action'];

					
					//$succ = $this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				*/
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

				if( ( $handled[ $ref_id ]['status'] >= 3 && $action == 'confirm' ) || ( $handled[ $ref_id ]['status'] == 1 && $action == 'refute' ) )
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

	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	/*
	public function im_ex_default_column( $params = array() )
	{
		$default_column = [];

		$default_column['header'] = [ 'doc_id', 'warehouse_id', 'docno', 'sdocno', 'doc_date', 'post_date', 'doc_type', 'hstatus', 'hflag', 'parent', 
			'supplier_company_code', 'note_reason', 'remark'
		];

		$default_column['detail'] = [ 'item_id', 'product_serial', 'bqty', 'amount', 'dstatus', '_item_number'	];

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
	*/


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		echo '<div id="sale_cdnote_reference_content" class="col-md-7">';	
		wcwh_form_field( '', 
			[ 'id'=>'sale_cdnote_reference', 'class'=>['inputSearch'], 'type'=>'text', 'label'=>'', 'required'=>false, 
				'attrs'=>['data-change="#sale_cdnote_action"'], 'placeholder'=>'Search By SO No. (Better Input Complete SO No.)' 
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
					<button id="sale_cdnote_action" class="btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Purchase Order (PO)"
						data-title="Search Credit/Debit Note" 
						data-action="sale_cdnote_reference" data-service="<?php echo $this->section_id; ?>_action" 
						data-modal="wcwhModalForm" data-actions="close|submit" 
						data-source="#sale_cdnote_reference" 
					>
						Search Credit/Debit Note
						<i class="fa fa-plus-circle" aria-hidden="true"></i>
					</button>
				<?php
				}
			break;
			/*
			case 'export':
				if( current_user_cans( [ 'export_'.$this->section_id ] ) && ! $this->view_outlet )
				{
				?>
					<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
						data-title="<?php echo $actions['export'] ?> Items" data-modal="wcwhModalImEx" 
						data-actions="close|export" 
						title="<?php echo $actions['export'] ?> Items"
					>
						<i class="fa fa-download" aria-hidden="true"></i>
					</button>
				<?php
				}
			break;
			*/
		}
	}

	public function view_reference_doc( $doc_id = 0, $title = '', $doc_type = 'sale_order' )
	{
		if( ! $doc_id || ! $doc_type ) return;

		switch( $doc_type )
		{
			case 'sale_order':
				if( ! class_exists( 'WCWH_SaleOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
				$Inst = new WCWH_SaleOrder_Controller();
			break;
		}
		
		$ref_segment = [];
		
		if( $Inst )
		{
			$Inst->set_warehouse(  $this->warehouse );

			ob_start();
			$Inst->view_form( $doc_id, false, true, true, [ 'ref'=>1, 'link'=>0 ] );
			$ref_segment[ $title ] = ob_get_clean();
			
			$args = [];
			$args[ 'accordions' ] = $ref_segment;
			$args[ 'id' ] = $this->section_id;

			do_action( 'wcwh_get_template', 'segment/accordion.php', $args );
		}
	}

	public function view_linked_doc( $doc_id = 0, $config = [] )
	{
		if( ! $doc_id ) return false;

		$childs = [];
		$childs = $this->Logic->get_child_doc_ids( $doc_id );
		if( !empty( $childs ) )
		{
			$Objs = []; $segments = []; $titles = [];
			foreach( $childs as $i => $doc )
			{
				$doc_type = $doc['doc_type'];
				switch( $doc_type )
				{
					case 'good_receive':
						if( current_user_cans( [ 'access_wh_good_receive' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_GoodReceive_Controller();
							$titles[ $doc_type ] = "Goods Receipt";
						}
					break;
					case 'delivery_order':
						if( current_user_cans( [ 'access_wh_delivery_order' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_DeliveryOrder_Controller();
							$titles[ $doc_type ] = "Delivery Order";
						}
					break;
				}

				if( !empty( $Objs[ $doc_type ] ) )
				{
					$Inst = $Objs[ $doc_type ];

					$segment = [];
					ob_start();
						$Inst->view_form( $doc['doc_id'], false, true, true, [ 'ref'=>0, 'link'=>1 ] );
					$segment[ $doc['docno'] ] = ob_get_clean();

					$args = [];
					$args[ 'id' ] = $this->section_id;
					$args[ 'accordions' ] = $segment;

					ob_start();
					do_action( 'wcwh_get_template', 'segment/accordion.php', $args );
					$segments[ $doc_type ][] = ob_get_clean();
				}
			}

			if( !empty( $segments ) )
			{
				foreach( $segments as $type => $docs )
				{
					if( !empty( $docs ) ) 
					{
						$content = implode( " ", $docs );
						echo "<div class='form-rows-group'><h5>{$titles[ $type ]}</h5>{$content}</div>";
					}
				}
			}
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
			$header = $this->Logic->get_header( [ 's'=>$id, 'doc_type'=>'sale_order', 'CDNote_Status'=>[6,9] ], [], true, [ 'company'=>1 ] );
			
			if( $header )
			{
				$metas = get_document_meta( $header['doc_id'] );
				$header = $this->combine_meta_data( $header, $metas );
				
				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];
				//$args['data']['invoice'] = $header['invoice'];
				$args['data']['client_company_code'] = $header['client_company_code'];
				//$args['data']['purchase_doc'] = $header['docno'];
				
				$filters = [ 'doc_id'=>$header['doc_id'] ];
				$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );
			}
			else
			{
				$succ = false;
				$this->Notices->set_notice( 'SO No. not found', 'error' );
				return $succ;
			}
			
			if( $items )
			{
				//$has_tree = false;
				$details = array();
				foreach( $items as $i => $item )
				{	
					$metas = get_document_meta( $id, '', $item['item_id'] );
					$item = $this->combine_meta_data( $item, $metas );
					
					$det = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
						],
						'bqty' => '',
						'bunit' => '',
						'ref_bqty' => $item['bqty'],
						'ref_bal' => ( $item['bqty'] - $item['uqty'] ),
						'ref_doc_id' => $item['doc_id'],
						'ref_item_id' => $item['item_id'],
					);

					$details[] = $det;
				}
				//exit();

				//$args['NoAddItem'] = true;
				
				$args['data']['details'] = $details;
				//if( $has_tree ) $args['data']['has_tree'] = 1;
				//pd($args['data']);
			}
		}
		
		if( $args['data']['ref_doc_id'] )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );
		
		if($header)
		{
			do_action( 'wcwh_get_template', 'form/saleCDNote-form.php', $args );
		}
		else
		{
			$succ = false;
			$this->Notices->set_notice( 'Please Input Valid SO No.', 'error' );
		}

		if($succ)
		{
			return $succ;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false, $config = [ 'ref'=>1, 'link'=>1 ] )
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
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}
	
		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [] );
			
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );
				
				if( !empty( $datas['ref_doc_id'] ) ) 
				{
					$ref_datas = $this->Logic->get_header( [ 'doc_id'=>$datas['ref_doc_id'], 'doc_type'=>'none' ], [], true, [] );
					if( $ref_datas ) $datas['ref_doc_date'] = $ref_datas['doc_date'];
				}

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1 ] );
				
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				//$args['NoAddItem'] = true;
				
				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_purchase_cdnote'] ) )
				{
					$hides = [ 'uprice', 'total_amount', 'avg_price' ];
				}
			
		        if( $datas['details'] )
		        {	
		        	$total_amount = 0; $final_amount = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['row_id'] = $item['item_id'];

		        		$datas['details'][$i]['item_name'] = $item['prdt_name'];
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];
					
						//$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] : 0;
						$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

						if( $datas['details'][$i]['ref_product_id'] && $datas['details'][$i]['to_product_id'] )
		        		{
		        			$datas['details'][$i]['to_product_id'] = $datas['details'][$i]['product_id'];
		        			$datas['details'][$i]['product_id'] = $datas['details'][$i]['ref_product_id'];

		        			$filter = [ 'id'=>$datas['details'][$i]['product_id'] ];
		        			if( $this->view_outlet && $this->warehouse['id'] ) $filter['seller'] = $this->warehouse['id'];
		        			$prdt = apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'isUnit'=>1 ] );

		        			if( $prdt )
			        		{
			        			$datas['details'][$i]['to_prdt_name'] = $datas['details'][$i]['prdt_name'];
			        			$datas['details'][$i]['to_uom_code'] = $datas['details'][$i]['uom_code'];

			        			$datas['details'][$i]['prdt_name'] = $prdt['code'].' - '.$prdt['name'];
			        			$datas['details'][$i]['uom_code'] = $prdt['_uom_code'];
			        			$datas['details'][$i]['line_item'] = [ 
				        			'name'=>$prdt['name'], 'code'=>$prdt['code'], 'uom_code'=>$prdt['_uom_code'], 
									'uom_fraction'=>$prdt['uom_fraction'], 'required_unit'=>$prdt['required_unit']
				        		];
			        		}

			        		if( $this->view_outlet && $this->warehouse['id'] )
			        			$bqty = apply_filters( 'wcwh_item_uom_conversion', $datas['details'][$i]['to_product_id'], $item['bqty'], $datas['details'][$i]['product_id'], 'qty', [ 'seller'=>$this->warehouse['id'] ] );
			        		else
			        			$bqty = apply_filters( 'wcwh_item_uom_conversion', $datas['details'][$i]['to_product_id'], $item['bqty'], $datas['details'][$i]['product_id'] );

			        		if( $this->view_outlet && $this->warehouse['id'] )
			        			$trees = apply_filters( 'wcwh_item_uom_conversion', $item['product_id'], 0, 0, 'qty', [ 'seller'=>$this->warehouse['id'] ] );
			        		else
			        			$trees = apply_filters( 'wcwh_item_uom_conversion', $item['product_id'] );

							if( $trees && count( $trees ) > 1 )
							{	
								foreach( $trees as $j => $t )
								{
									if( $this->view_outlet && $this->warehouse['id'] )
										$trees[$j]['converse'] = apply_filters( 'wcwh_item_uom_conversion', $datas['details'][$i]['product_id'], $item['ref_bqty'] - $bqty, $t['id'], 0, 'qty', [ 'seller'=>$this->warehouse['id'] ] );
									else
										$trees[$j]['converse'] = apply_filters( 'wcwh_item_uom_conversion', $datas['details'][$i]['product_id'], $item['ref_bqty'] - $bqty, $t['id'] );
								}
								
								$datas['details'][$i]['options'] = $trees;
							}

							$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $bqty : 0;
		        		}
						
		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2 );
						
		        		$item_child = apply_filters( 'wcwh_get_item', [ 'id'=>$datas['details'][$i]['to_product_id'] ], [], true, [] );
						$datas['details'][$i]['item_child_name'] = $item_child['name'];
						
		        		//$datas['details'][$i]['uprice'] = round_to( $datas['details'][$i]['uprice'], 5, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['amount'], 2, true );
		        		//$datas['details'][$i]['avg_price'] = round_to( $datas['details'][$i]['avg_price'], 5, true );
		        		$total_amount+= $datas['details'][$i]['amount'];
						
						/*
		        		if( $datas['status'] >= 9 )
		        		{
		        			$datas['details'][$i]['final_amount'] = round_to( $item['uqty'] * $datas['details'][$i]['avg_price'], 2, true );
		        			$final_amount+= $datas['details'][$i]['final_amount'];
		        		}
						*/
		        	}
					
		        	if( $isView && !$hides )
		        	{
		        		$final = [];
			        	$final['prdt_name'] = '<strong>TOTAL:</strong>';
			        	$final['amount'] = '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
			        	//if( $final_amount ) $final['final_amount'] = '<strong>'.round_to( $final_amount, 2, true ).'</strong>';
			        	$datas['details'][] = $final;
		        	}
		        }
				
				$args['data'] = $datas;
				unset( $args['new'] );

				$cols = [
					'num' => '',
					'prdt_name' => 'Item',
					'bqty' => 'Qty',
					'def_sprice' => 'Unit Price',
					'amount' => 'Total Amount',
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
			do_action( 'wcwh_templating', 'form/saleCDNote-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/saleCDNote-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}
	
	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/saleCDNote-row.php', $this->tplName['row'] );
	}
	/*
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

		do_action( 'wcwh_templating', 'export/export-cdNote.php', $this->tplName['export'], $args );
	}
	*/
	public function cn_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['cn'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
		);
		
		do_action( 'wcwh_templating', 'form/saleCreditNote-print-form.php', $this->tplName['cn'], $args );
	}

	public function dn_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['dn'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
		);
		
		do_action( 'wcwh_templating', 'form/saleDebitNote-print-form.php', $this->tplName['dn'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/saleCDNoteListing.php" ); 
			$Inst = new WCWH_SaleCDNote_Listing();
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

			$metas = [ 'note_reason', 'note_action', 'remark', 'ref_doc_id', 'ref_doc', 'ref_doc_type', 'client_company_code', 'uuid', 'longid', 'validate_date', 'irb_status' ];

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