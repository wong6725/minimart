<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_BankInService_Class" ) ) include_once( WCWH_DIR . "/includes/classes/bankinservice.php" );

if ( !class_exists( "WCWH_BankInInfo_Class" ) ) include_once( WCWH_DIR . "/includes/classes/bankininfo.php" ); 
if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" ); 

if ( !class_exists( "WCWH_BankInService_Controller" ) ) 
{

class WCWH_BankInService_Controller extends WCWH_CRUD_Controller
{
	protected $section_id = "wh_bankin_service";

	public $Notices;
	public $className = "BankInService_Controller";

	public $Logic;
	public $Bankinfo;
	public $BankinfoCtrl;

	public $tplName = array(
		'new' => 'newBIS',
		'row' => 'rowBIS',
		'bis' => 'printBIS',
		'multiBIS' => 'printMultiBIS',
	);

	public $useFlag = false;
	public $outlet_post = true;

	protected $warehouse = array();
	protected $view_outlet = false;
	
	public $processing_stat = [ 1 ];

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
		$this->Logic = new WCWH_BankInService_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->processing_stat = $this->processing_stat;

		$this->Bankinfo = new WCWH_BankInInfo_Class( $this->db_wpdb );

		$this->BankinfoCtrl = new WCWH_BankInInfo_Controller( $this->db_wpdb );
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
		{
			$this->view_outlet = true;
		}

		$this->Logic->setWarehouse( $this->warehouse );
	}

	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */

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
				case 'update_api':
				case 'save':
					if( !$datas['header']['customer_id'] || !$datas['header']['bankAccID'] || !$datas['header']['total_amount'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'warning' );						
					}
					else
					{
						if($datas['header']['bankAccID'] != 'new')
						{
							$filters = [ 'id'=>$datas['header']['bankAccID'], 'status'=>1 ];
							if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
							$bi = apply_filters('wcwh_get_bankin_info',$filters);
							if(!$bi)
							{
								$succ = false;
								$this->Notices->set_notice( 'Invalid Bank Account Info', 'error' );
							}
						}
					}
				break;
				case 'post':
				case "complete":
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					else
					{
						(is_array($datas['id'])) ? $single = false : $single = true;
						
						$exist = $this->Logic->get_header( ['doc_id' =>$datas['id']], [], $single, [] );
						
						if( ! $exist )
						{
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
							$succ = false;
						}
						else
						{
							if($single == false)
							{
								foreach ($datas['id'] as $key => $value)
								{
									$metas = $this->Logic->get_document_meta( $value );
									$exist = $this->combine_meta_data( $exist, $metas );
									if($exist['bankAccID'] != 'new')
									{
										$filters = [ 'id'=>$exist['bankAccID'], 'status'=>1];
										if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
										$bi = apply_filters('wcwh_get_bankin_info', $filters);
										if(!$bi)
										{
											$succ = false;
											$this->Notices->set_notice( 'Invalid Action. Please Update the User Bank Account Info', 'error' );
										}
									}
								}
							}
							else
							{
								$metas = $this->Logic->get_document_meta( $datas['id'] );
								$exist = $this->combine_meta_data( $exist, $metas );
								if($exist['bankAccID'] != 'new')
								{
									$filters = [ 'id'=>$exist['bankAccID'], 'status'=>1];
									if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
									$bi = apply_filters('wcwh_get_bankin_info', $filters);
									if(!$bi)
									{
										$succ = false;
										$this->Notices->set_notice( 'Invalid Action. Please Update the User Bank Account Info', 'error' );
									}
								}
							}
						}  
					}
				break;
				case 'unpost':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					else
					{
						$datas['id'] = ( is_array($datas['id']) )? $datas['id'] : [ $datas['id'] ];
						foreach( $datas['id'] as $id )
						{
							$collected = $this->Logic->get_document_meta( $id, 'collected', 0, true );
							if( $collected > 1 )
							{
								$succ = false;
								$exist = $this->Logic->get_header( [ 'doc_id' =>$collected, 'doc_type'=>'bankin_collector' ], [], true );
								if( $exist ) $msg = $exist['docno'];
								$this->Notices->set_notice( "Invalid Action. Selected Document Found In Remittance Money Collector {$msg}!", 'error' );
							}
						}
					}
				break;
				case 'delete':
				case 'approve':
				case 'reject':
				case 'print':				
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
			//$succ = false;
			$action = strtolower( $action );
        	switch ( $action )
        	{				
				case "save":
				case "update":
					$header = $datas['header'];
					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;
					$header['doc_time'] = ( $header['doc_time'] )? date( ' H:i:s', strtotime( $header['doc_time'] ) ) : " 23:59:59";
					$header['warehouse_id'] = !empty( $header['warehouse_id'] )? $header['warehouse_id'] : $this->warehouse['code'];
					$header['service_charge'] = round($header['service_charge'],2);
					$header['exchange_rate'] = round($header['exchange_rate'],3);
					$header['convert_amount'] = round($header['convert_amount'],2);
					$header['total_amount'] = round($header['total_amount'],2);

					//Just in case missing $header['ref_exchange_rate'] or $header['exchange_rate']
					if( empty($header['ref_exchange_rate']) || empty($header['exchange_rate']) )
					{
						//Both missing, Get latest exchange rate based on $header['currency']
						$arr = apply_filters( 'wcwh_get_latest_exchange_rate', ['from_currency'=>'MYR', 'to_currency'=>$header['currency']], [], true );
						
						if($arr)
						{
							$header['ref_exchange_rate'] = $arr['id'];
							$header['exchange_rate'] = $arr['rate'];
						}
						else
						{
							// IF missing exchange rate only, get rate by using it's ref_exchange_rate (ID) 
							if(!empty($header['ref_exchange_rate']))
							{
								$arr2 = apply_filters( 'wcwh_get_exchange_rate', ['id'=>$header['ref_exchange_rate']], [], true );
								
								if($arr2)
								{
									$header['exchange_rate'] = $arr2['rate'];
								}
								else
								{
									$succ = false;
									$this->Notices->set_notice( 'Exchange Rate Not Found', 'error' );
								}
							}
							else
							{
								$succ = false;
								$this->Notices->set_notice( 'Exchange Rate ID Not Found', 'error' );
							}
						}
					}
					
					if($header['bankAccID'] != 'new' )
					{
						$filters = [ 'id'=>$header['bankAccID'], 'status'=>1 ];
						if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
						$bi = apply_filters('wcwh_get_bankin_info',$filters);
						if(!$bi)
						{
							$succ = false;
							$this->Notices->set_notice( 'Invalid Bank Account Info', 'error' );
						}
						else
						{
							$datas = $header;
							$datas['id'] = $bi[0]['id'];
							
							$result = $this->BankinfoCtrl->action_handler( $action, $datas, $obj, true );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}
						}
					}

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
				case "update_api":
					$header = $datas['header'];
					$id = $header['doc_id'];
					$doc = $this->Logic->get_header( [ 'doc_id' => $id, 'warehouse_id'=>$datas['warehouse_id']], [], true, [] );
					if($doc)
					{
						if( !$datas['header']['docno'] ) $datas['header']['docno'] = $doc['docno'];
						if( !$datas['header']['sdocno'] ) $datas['header']['sdocno'] = $doc['sdocno'];
					}
					$proceed = true;
					$remote_result = '';
					if( $this->outlet_post )
					{
						$wh_code = $this->warehouse['code'];
						if( !$wh_code )$proceed = false;

						if( $proceed )
						{
							$remote = apply_filters( 'wcwh_api_request', 'update_bankin_info', $id, $wh_code, $this->section_id, $datas );
							
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
										$outcome['modal'] = $remote_result['datas'];
										$outcome['modalargs'] = array(
											'setting'   => $this->setting,
											'section'   => $this->section_id,
											'hook'      => $this->section_id.'_form',
											'action'    => 'update',
											'token'     => apply_filters( 'wcwh_generate_token', $this->section_id ),
											'tplName'   => $this->tplName['new'],
											'rowTpl'    => $this->tplName['row'],
											'wh_id'     => $this->warehouse['id'],
										);
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
						if( !$proceed )
						{
							$succ = false;
							if( ! $this->Notices->has_notice() )
							$this->Notices->set_notice( 'Client side operation failed.', 'error' );
						}
					}
					else
					{
						$proceed = false;
					}

					if($proceed && $succ)
					{
						$outcome['id'][] = $id;
						$dat = $result['data'];
						$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
							'ref_type'	=> $this->section_id,
							'ref_id'	=> $id,
							'action'	=> $action,
							'status'    => $succ,
							'remark'	=> ( $header['remark'] )? $header['remark'] : '',
						] );
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
				case "approve":
				case "reject":
				break;
				case "print":
				case "print_receipt":
				case "print_a4":
					if( in_array( $action, [ 'print_a4', 'print_receipt' ] ) )
					{
						$datas['docID'] = $datas['id'];
						$datas['type'] = 'bank_in';

						$datas['paper_size'] = 'default';
						$datas['html'] = 1;
						if( $action == 'print_receipt' ) $datas['paper_size'] = 'receipt';
					}

					if( $datas['paper_size'] || $datas['html'] )
					{
						$pdat = [];
						if( $datas['paper_size'] ) $pdat['paper_size'] = $datas['paper_size'];
						if( $datas['html'] ) $pdat['html'] = $datas['html'];
						set_transient( get_current_user_id().$this->section_id."print", $pdat, 0 );
					}

					if($datas['docID'] || $datas['id'] == 0) //When print multiple form (print icon at Top right corner ).
					{
						//status = 0 will be unset in document.php (Line 1426)
						//so use process and store in processing_stat array.
					
						//Check if 'all' not been seleted
						if($datas['status'] && !in_array('all', $datas['status']))
						{
							foreach ($datas['status'] as $key => $value)
							{
								$array[] = $value;
							}
							$this->Logic->processing_stat = $array;
							$datas['status'] = 'process';
						}
						else // direct print all doc
						{
							$datas['status'] = 'all';
						}

						switch( strtolower( $datas['type'] ) )
						{
							case 'bank_in':
							default:
								$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
								if($datas['InExclude'] == 1) //exclude
								{
									$doc = $this->Logic->get_header( [ 'not_doc_id'=> $datas['docID'],'status'=>$datas['status']], [], false, [] );
								}
								else
								{
									
									$doc = $this->Logic->get_header( [ 'doc_id'=> $datas['docID'],'status'=>$datas['status']], [], false, [] );
								}
								
								if( !empty($doc) )
								{
									foreach ($doc as $key => $value)
									{
										$doc[$key]['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
										//metas
										$metas = $this->Logic->get_document_meta( $value['doc_id'] );
										$doc[$key] = $this->combine_meta_data( $doc[$key], $metas );

										$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc[$key]['warehouse_id'] ], [], true, [ ] );
							
										$date_format = get_option( 'date_format' );
										$params['heading'][$key]['docno'] = $doc[$key]['docno'];
										$params['heading'][$key]['company'] = $warehouse['name']." (".$doc[$key]['warehouse_id'].")";
										$params['heading'][$key]['title'] = "BORANG KIRIMAN UANG (REMITTANCE FORM)";
										
										$params['heading'][$key]['doc_no'] = $doc[$key]['docno'];
										$user_info = get_userdata( get_current_user_id() );
										$params['heading'][$key]['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
										$params['heading'][$key]['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
									
										$filter['id'] = $doc[$key]['ref_exchange_rate'];
										$filter['seller'] = $warehouse['id'];
										$exchange_rate_id = apply_filters( 'wcwh_get_exchange_rate', $filter, [], true, [] );
										
										$doc['from_currency'] = $exchange_rate_id['from_currency'];
										
										$params['detail'][] = $doc[$key];

										//mha004 - print receipt
										$params['heading2'][$value['doc_id']]['infos1'] = [
											'NAME PENERIMA' => $doc[$key]['account_holder'],
											'BANK PENERIMA' => $doc[$key]['bank'],
											'NO. REKENING' => $doc[$key]['account_no'],
										];

										$from_currency = get_woocommerce_currency_symbol($doc[$key]['from_currency']);//get_woocommerce_currency_symbol();
										$to_currency = get_woocommerce_currency_symbol($doc[$key]['currency']);//get_woocommerce_currency_symbol();

										$params['heading2'][$value['doc_id']]['infos2'] = [
											'A. UANG DI KIRIM' => $from_currency." ".round_to( $doc[$key]['amount'], 2, 1, 1). " x ".$doc[$key]['exchange_rate']. " = ".$to_currency." ".round_to( $doc[$key]['convert_amount'], 2, 1, 1 ),
											'B. CAJ PERKHIDMATAN' => $from_currency." ".round_to( $doc[$key]['service_charge'], 2, 1, 1),
											'JUMLAH' => $from_currency." ".round_to(($doc[$key]['amount']+$doc[$key]['service_charge']), 2, 1, 1)
										];

										$params['heading2'][$value['doc_id']]['infos3'] = [
											'TANGGAL' => date('Y-m-d',strtotime($doc[$key]['doc_date'])),
											'NAMA PENGIRIM' => $doc[$key]['sender_name'],
											'NO. H/P PENGIRIM' => $doc[$key]['sender_contact'],
										];
										//end mha
									}

									switch( strtolower( $datas['paper_size'] ) )
									{
										case 'receipt':
											$params['print'] = 1;
											ob_start();
												do_action( 'wcwh_get_template', 'template/receipt-remittance-money-multi.php', $params );
											$content.= ob_get_clean();
		
											echo $content;
										break;
										case 'default':
										default:
											ob_start();
											do_action( 'wcwh_get_template', 'template/doc-remittanceform_multi.php', $params );
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
								}
								else
								{
									$succ = false;
									echo "Document(s) not found.";
								}						
							break;
						}
						exit;
					}
					else
					{
						if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

						$id = $datas['id'];
						switch( strtolower( $datas['type'] ) )
						{
							case 'bank_in':
								$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
								$doc = $this->Logic->get_header( [ 'doc_id' => $id], [], true, [] );
						
								if( $doc )
								{
									$doc['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
									//metas
									$metas = $this->Logic->get_document_meta( $id );
									$doc = $this->combine_meta_data( $doc, $metas );

									$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ ] );
						
									$date_format = get_option( 'date_format' );
									$params['heading']['docno'] = $doc['docno'];
									$params['heading']['company'] = $warehouse['name']." (".$doc['warehouse_id'].")";
									$params['heading']['title'] = "BORANG KIRIMAN UANG (REMITTANCE FORM)";
									
									$params['heading']['doc_no'] = $doc['docno'];
									$user_info = get_userdata( get_current_user_id() );
									$params['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
									$params['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
								
									$filter['id'] = $doc['ref_exchange_rate'];
									$filter['seller'] = $warehouse['id'];
									$exchange_rate_id = apply_filters( 'wcwh_get_exchange_rate', $filter, [], true, [] );
									
									$doc['from_currency'] = $exchange_rate_id['from_currency'];
									
									$params['detail'] = $doc;

									//mha004 - print receipt
									$params['heading']['infos1'] = [
										'NAME PENERIMA' => $doc['account_holder'],
										'BANK PENERIMA' => $doc['bank'],
										'NO. REKENING' => $doc['account_no'],
									];

									$from_currency = get_woocommerce_currency_symbol($doc['from_currency']);//get_woocommerce_currency_symbol();
									$to_currency = get_woocommerce_currency_symbol($doc['currency']);//get_woocommerce_currency_symbol();

									$params['heading']['infos2'] = [
										'A. UANG DI KIRIM' => $from_currency." ".round_to( $doc['amount'], 2, 1, 1). " x ".$doc['exchange_rate']. " = ".$to_currency." ".round_to( $doc['convert_amount'], 2, 1, 1 ),
										'B. CAJ PERKHIDMATAN' => $from_currency." ".round_to( $doc['service_charge'], 2, 1, 1),
										'JUMLAH' => $from_currency." ".round_to(($doc['amount']+$doc['service_charge']), 2, 1, 1)
									];

									$params['heading']['infos3'] = [
										'TANGGAL' => date('Y-m-d',strtotime($doc['doc_date'])),
										'NAMA PENGIRIM' => $doc['sender_name'],
										'NO. H/P PENGIRIM' => $doc['sender_contact'],
									];
									//end mha
								}

								switch( strtolower( $datas['paper_size'] ) )
								{
									case 'receipt':
										$params['print'] = 1;
										ob_start();
											do_action( 'wcwh_get_template', 'template/receipt-remittance-money.php', $params );
										$content.= ob_get_clean();
	
										echo $content;
									break;
									case 'default':
									default:
										ob_start();
										do_action( 'wcwh_get_template', 'template/doc-remittanceform.php', $params );
			
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
					}
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	//if( is_array( $datas["id"] ) && $count_succ > 0 ) $succ = true;

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

	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$not_acc_type = $this->setting['wh_customer']['non_editable_by_acc_type'];
			
			$filters = [ 'wh_code'=>$this->warehouse['code'], 'status'=>'1'];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			if( $not_acc_type ) $filters['not_acc_type'] = $not_acc_type;
			$employee = apply_filters( 'wcwh_get_customer', $filters, [], false);
			
	        echo '<div id="bankin_service_reference_content" class="col-md-8">';
	        echo '<select id="bankin_service_reference" class="select2 triggerChange barcodeTrigger" data-change="#bankin_service_action" data-placeholder="Employer ID/ Code/ Serial">';
	        echo '<option></option>';
	        foreach( $employee as $i => $emp )
	        {
	        	echo '<option 
                            value="'.$emp['id'].'" 
                            data-uid="'.$emp['uid'].'" 
                            data-code="'.$emp['code'].'" 
                            data-serial="'.$emp['serial'].'"
                            data-name="'.$emp['name'].'"
                >'. $emp['uid'].', '.$emp['code'].', '.$emp['serial'] .', '.$emp['name'] .'</option>';
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
			case 'print':
				?>
					<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['multiBIS'] ?>" 
						data-title="<?php echo $actions['print'] ?> Remittance Money Form" data-modal="wcwhModalImEx" 
						data-actions="close|printing" 
						title="<?php echo $actions['print'] ?> Remittance Money Form"
					>
						<i class="fa fa-print" aria-hidden="true"></i>
					</button>
				<?php
			break;
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="bankin_service_action" class="display-none btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Remittance Money Service"
					data-title="<?php echo $actions['save'] ?> Remittance Money Service" 
					data-action="bankin_service_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#bankin_service_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> Remittance Money Service
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
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
			'rowTpl'	=> $this->tplName['row'],
			'wh_id'		=> $this->warehouse['id'],
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$filters = ['id'=>$id, 'wh_code'=>$this->warehouse['code']];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			$data = apply_filters( 'wcwh_get_customer', $filters, [], true);
			if($data)
			{
				$args['data']['customer_id'] = $data['id'];
				$args['data']['sender_name'] = $data['name'];
				$args['data']['receiver_contact'] = $data['phone_no'];
				$args['data']['customer_serial'] = $data['serial'];

				$filters = [ 'customer_id'=> $id, 'status'=>1  ];
				if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
				$bankinfo = $this->Bankinfo->get_infos($filters);
				if($bankinfo)
				{
					$args['data']['bank_account'] = $bankinfo;
				}
			}
		}

		do_action( 'wcwh_get_template', 'form/bankinservice-form.php', $args );
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $UpdateSync = false )
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
			'wh_id'		=> $this->warehouse['id'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}
		
		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id, 'doc_type'=>'bank_in' ], [], true, [] );
			if($datas)
			{
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";

				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );
				if($datas['customer_id'])
				{
					if($isView && $datas['bankAccID'] && $datas['bankAccID'] != 'new')
					{
						$filters = [ 'customer_id'=> $datas['customer_id']  ];
						if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
						$bankinfo = $this->Bankinfo->get_infos($filters,'',true);
					}
					else if(!$isView)
					{
						$filters = [ 'customer_id'=> $datas['customer_id'], 'status'=>1  ];
						if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
						$bankinfo = $this->Bankinfo->get_infos($filters);
					}
					
					if($bankinfo)
					{
						$datas['bank_account'] = $bankinfo;
					}
				}

				/*
				if( !empty( $datas['ref_doc_id'] ) ) 
				{
					$ref_datas = $this->Logic->get_header( [ 'doc_id'=>$datas['ref_doc_id'], 'doc_type'=>'none' ], [], true, [] );
					if( $ref_datas ) $datas['ref_doc_date'] = $ref_datas['doc_date'];
				}*/
				if($UpdateSync) $args['action'] = 'update_api';
				else $args['action'] = 'update';
				
				if( $isView ) $args['view'] = true;

				$args['data'] = $datas;
				unset( $args['new'] );						
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/bankinservice-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/bankinservice-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/bankinservice-row.php', $this->tplName['row'] );
		//do_action( 'wcwh_templating', 'segment/bankinservice-view-row.php', $this->tplName['row'].'View' );
	}

	public function bis_form()
	{
		$pdat = get_transient( get_current_user_id().$this->section_id."print" );
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['bis'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
			'print_opt' => $pdat,
		);
	
		do_action( 'wcwh_templating', 'form/bankinservice-print-form.php', $this->tplName['bis'], $args );
	}

	public function multiBIS_form()
	{
		$pdat = get_transient( get_current_user_id().$this->section_id."print" );
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['multi_bis'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
			'print_opt' => $pdat,
		);
	
		do_action( 'wcwh_templating', 'form/bankinservice-print-multi-form.php', $this->tplName['multiBIS'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
		include_once( WCWH_DIR . "/includes/listing/bankinserviceListing.php" ); 
		$Inst = new WCWH_BankInService_Listing();
		$Inst->set_warehouse( $this->warehouse );
		$Inst->set_section_id( $this->section_id );
		$Inst->useFlag = $this->useFlag;

		$count = $this->Logic->count_statuses();
		if( $count ) $Inst->viewStats = $count;

		$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 'process';

		$Inst->filters = $filters;
		$Inst->advSearch_onoff();

		$Inst->bulks = array( 
			'data-section'=>$this->section_id,
			'data-tpl' => 'remark',
			'data-service' => $this->section_id.'_action',
			'data-form' => 'edit-'.$this->section_id,
		);

		$meta = [ 'amount', 'service_charge', 'currency', 'convert_amount', 'exchange_rate', 'sender_name', 'customer_id' ];

		$order = $Inst->get_data_ordering();
		$limit = $Inst->get_data_limit();
		
		$datas = $this->Logic->get_header( $filters, $order, false, [ 'parent'=>1, 'off_det'=>1, 'meta'=>$meta, 'recent'=>1 ], [], $limit );
		$datas = ( $datas )? $datas : array();

		$Inst->set_details( $datas );
		$Inst->render();

		?>		
		</form>
		<?php
	}

} //class

}