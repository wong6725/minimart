<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PurchaseCDNote_Class" ) ) include_once( WCWH_DIR . "/includes/classes/purchase-cdnote.php" ); 

if ( !class_exists( "WCWH_PurchaseCDNote_Controller" ) ) 
{

class WCWH_PurchaseCDNote_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_purchase_cdnote";

	public $Notices;
	public $className = "PurchaseCDNote_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newCD',
		'row' => 'rowCD',
		'import' => 'importCD',
		'export' => 'exportCD',
		'cn' => 'printCN',
		'dn' => 'printDN',
	);

	public $useFlag = false;
	public $directPost = true;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1, 6 ];

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
		$this->Logic = new WCWH_PurchaseCDNote_Class( $this->db_wpdb );
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
					$header['invoice'] = trim( $header['invoice'] );
					
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
					}
					
					//$header['inventory_action'] = 0;
					if( $detail )
					{
						foreach( $detail as $i => $row )
						{
							if( !$row['bqty'] && !$row['amount'] )
							{
								$succ = false;
								$this->Notices->set_notice( "Both column cannot be blank.", 'warning' );
							}
							if( $row['amount'] ) $detail[$i]['total_amount'] = $row['amount'];
							else $detail[$i]['total_amount'] = 0;
							
							if( $row['to_product_id'] )
							{
								$detail[$i]['ref_product_id'] = $row['product_id'];
								$detail[$i]['product_id'] = $row['to_product_id'];
							}

							//if( $row['bqty'] > 0 ) $header['inventory_action'] = 1;
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
							$result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							if( $succ && in_array( $action, [ 'post', 'unpost' ] ) )
							{
								$succ = $this->direct_issue_handler( $result['id'], $action );
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
				case "print":
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );
					
					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
						case 'purchase_debitnote':
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
									'Debit Note No.' => $doc['docno'],
									'Invoice No.' => $doc['invoice'],
									
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
								];
								$params['heading']['note_reason'] = $doc['note_reason'];
								
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
								
								$supplier = apply_filters( 'wcwh_get_supplier', [ 'code'=>$doc['supplier_company_code'] ], [], true, [ 'address'=>'default' ] );
								if( $supplier )
								{
									$params['heading']['first_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $supplier['contact_person'],
										'company'    => $supplier['name'],
										'address_1'  => $supplier['address_1'],
										'city'       => $supplier['city'],
										'state'      => $supplier['state'],
										'postcode'   => $supplier['postcode'],
										'country'    => $supplier['country'],
										'phone'		 => $supplier['contact_no'],
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
										do_action( 'wcwh_get_template', 'template/doc-purchase-debit-note.php', $params );
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
						case 'purchase_creditnote':
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
									'Credit Note No.' => $doc['docno'],
									'Invoice No.' => $doc['invoice'],
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
								];
								$params['heading']['note_reason'] = $doc['note_reason'];
								
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
								
								$supplier = apply_filters( 'wcwh_get_supplier', [ 'code'=>$doc['supplier_company_code'] ], [], true, [ 'address'=>'default' ] );
								if( $supplier )
								{
									$params['heading']['first_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $supplier['contact_person'],
										'company'    => $supplier['name'],
										'address_1'  => $supplier['address_1'],
										'city'       => $supplier['city'],
										'state'      => $supplier['state'],
										'postcode'   => $supplier['postcode'],
										'country'    => $supplier['country'],
										'phone'		 => $supplier['contact_no'],
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
							        	$row['qty'] = round_to( ($item['bqty']*-1), 2 );
										($item['amount'] && $item['bqty']) ? $row['uprice'] = round_to( ( ($item['amount'] / $item['bqty']) * -1) ,2) : '';
			
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
										do_action( 'wcwh_get_template', 'template/doc-purchase-credit-note.php', $params );
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
			}
		}

		return $succ;
	}

	public function direct_issue_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		if ( !class_exists( "WCWH_GoodIssue_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-issue.php" ); 
		$Inst = new WCWH_GoodIssue_Class( $this->db_wpdb );
		$Inst->setUpdateUqtyFlag( false );

		$inventory_action = get_document_meta( $doc_id, 'inventory_action', 0, true );
		$note_action = get_document_meta( $doc_id, 'note_action', 0, true );
		if( ! $inventory_action ) return true;
		if( $note_action != 2 ) return true;

		$succ = true;
		$issue_type = 'other';
		
		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'none' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					$header = [
						'warehouse_id' => ( $this->warehouse['code'] )? $this->warehouse['code'] : $doc_header['warehouse_id'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'good_issue_type' => $issue_type,
						'parent' => $doc_header['doc_id'],
						'ref_warehouse' => $doc_header['warehouse_id'],
						'ref_doc_type' => $doc_header['doc_type'],
						'ref_doc_id' => $doc_header['doc_id'],
						'ref_doc' => $doc_header['docno'],
						'remark' => $doc_header['note_reason'].( $doc_header['remark']? " ".$doc_header['remark'] : '' ),
					];
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );

					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$detail[] = [
								'product_id' => $row['product_id'],
								'bqty' => $row['bqty'],
								'bunit' => ($row['bunit']>0)? $row['bunit']:0,
								'item_id' => '',
								'ref_doc_id' => $row['doc_id'],
								'ref_item_id' => $row['item_id'],
								'total_amount' => $row['total_amount'],
								'transact_imp_amt' => 1,
							];
						}
					}

					if( $header && $detail )
					{
						$result = $Inst->child_action_handle( 'save', $header, $detail );

			            if( ! $result['succ'] )
			            {
							$succ = false;
			                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			            }
			            else
			            {
			            	$result = $Inst->child_action_handle( 'post', [ 'doc_id' => $result['id'] ], [] );
			            	if( ! $result['succ'] )
					        {
					        	$succ = false;
					            $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
					        }
					        else
					        {
					        	update_document_meta( $doc_id, 'automate_issue_id', $result['id'] );
					        }
			            }
					}
				}
			break;
			case 'unpost':
				$good_issue = get_document_meta( $doc_id, 'automate_issue_id', 0, true );
				if( $good_issue )
				{
					$doc_header = $this->Logic->get_header( [ 'doc_id'=>$good_issue, 'doc_type'=>'good_issue' ], [], true, ['usage'=>1] );
					if( $doc_header )
					{
						$header = [ 'doc_id'=>$doc_header['doc_id'] ];
						$result = $Inst->child_action_handle( 'unpost', $header, [] );
						if( ! $result['succ'] )
		                {
							$succ = false;
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$result = $Inst->child_action_handle( 'delete', $header, [] );
		                	if( ! $result['succ'] )
			                {
								$succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	delete_document_meta( $doc_id, 'automate_issue_id' );
			                }
		                }

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


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		echo '<div id="purchase_cdnote_reference_content" class="col-md-7">';	
		wcwh_form_field( '', 
			[ 'id'=>'purchase_cdnote_reference', 'class'=>['inputSearch'], 'type'=>'text', 'label'=>'', 'required'=>false, 
				'attrs'=>['data-change="#purchase_cdnote_action"'], 'placeholder'=>'Search By PO No. (Better Input Complete PO No.)' 
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
					<button id="purchase_cdnote_action" class="btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Purchase Order (PO)"
						data-title="Search Credit/Debit Note" 
						data-action="purchase_cdnote_reference" data-service="<?php echo $this->section_id; ?>_action" 
						data-modal="wcwhModalForm" data-actions="close|submit" 
						data-source="#purchase_cdnote_reference" 
					>
						Search Credit/Debit Note
						<i class="fa fa-plus-circle" aria-hidden="true"></i>
					</button>
				<?php
				}
			break;
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
		}
	}

	public function view_reference_doc( $doc_id = 0, $title = '', $doc_type = 'sale_order' )
	{
		if( ! $doc_id || ! $doc_type ) return;

		switch( $doc_type )
		{
			case 'purchase_order':
				if( ! class_exists( 'WCWH_PurchaseOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/purchaseOrderCtrl.php" ); 
				$Inst = new WCWH_PurchaseOrder_Controller();
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
					case 'good_issue':
						if( current_user_cans( [ 'access_wh_good_issue' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_GoodIssue_Controller();
							$titles[ $doc_type ] = "Goods Issue";
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
			$header = $this->Logic->get_header( [ 's'=>$id, 'doc_type'=>'purchase_order', 'CDNote_Status'=>[6,9]], [], true, [ 'company'=>1 ] );
			
			if( $header )
			{
				$metas = get_document_meta( $header['doc_id'] );
				$header = $this->combine_meta_data( $header, $metas );
			
				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];
				$args['data']['invoice'] = $header['invoice'];
				$args['data']['supplier_company_code'] = $header['supplier_company_code'];

				$args['data']['purchase_doc'] = $header['docno'];

				$filters = [ 'doc_id'=>$header['doc_id'] ];
				$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );
			}
			else
			{
				$succ = false;
				$this->Notices->set_notice( 'PO No. not found', 'error' );
				return $succ;
			}
			
			if( $items )
			{
				$has_tree = false;
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
					
					$trees = apply_filters( 'wcwh_item_uom_conversion', $item['product_id'] );
					if( $trees && count( $trees ) > 1 )
					{	
						foreach( $trees as $j => $t )
						{
							$trees[$j]['converse'] = ( $det['ref_bal'] <= 0 )? 0 : apply_filters( 'wcwh_item_uom_conversion', $item['product_id'], $det['ref_bal'], $t['id'] );
						}

						$det['options'] = $trees;
						$has_tree = true;
					}

					$details[] = $det;

				}
				//exit();

				//$args['NoAddItem'] = true;

				$args['data']['details'] = $details;

				if( $has_tree ) $args['data']['has_tree'] = 1;
				//pd($args['data']);
			}
		}
		
		if( $args['data']['ref_doc_id'] )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		if($header)
		{
			do_action( 'wcwh_get_template', 'form/purchaseCDNote-form.php', $args );
		}
		else
		{
			$succ = false;
			$this->Notices->set_notice( 'Please Input Valid PO No.', 'error' );
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
				$args['NoAddItem'] = true;

				if($args['action'] == 'update')
				{
					foreach ($datas['details'] as $key => $value)
					{
						$trees = apply_filters( 'wcwh_item_uom_conversion', $value['product_id'] );
						if( $trees && count( $trees ) > 1 )
						{	
							foreach( $trees as $j => $t )
							{
								$trees[$j]['converse'] = ( $det['ref_bal'] <= 0 )? 0 : apply_filters( 'wcwh_item_uom_conversion', $item['product_id'], $det['ref_bal'], $t['id'] );
							}

							$item_child_options[] = $trees;
							$has_tree = true;
						}
					}
				}
		
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
						
		        		if( $datas['details'][$i]['to_product_id'] ) 
		        		{
		        			$item_child = apply_filters( 'wcwh_get_item', [ 'id'=>$datas['details'][$i]['to_product_id'] ], [], true, [] );
		        			$datas['details'][$i]['item_child_name'] = $item_child['name'];
		        		}
						
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
				$datas['option'] = $item_child_options;
				
				$args['data'] = $datas;
				unset( $args['new'] );

				$cols = [
					'num' => '',
					'prdt_name' => 'Item',
					'ref_bqty' => 'Ref Qty',
					'item_child_name' => 'Item Child',
					'bqty' => 'Qty',
					'amount' => 'Total Amount',
				];
		        if( $datas['status'] >= 9 )
		        {
		        	$cols['uqty'] = 'Final Qty';
		        	$cols['final_amount'] = 'Final Amt';
		        }

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
			do_action( 'wcwh_templating', 'form/purchaseCDNote-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );
			
			do_action( 'wcwh_get_template', 'form/purchaseCDNote-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}
	
	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/purchaseCDNote-row.php', $this->tplName['row'] );
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

		do_action( 'wcwh_templating', 'export/export-cdNote.php', $this->tplName['export'], $args );
	}

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
		
		do_action( 'wcwh_templating', 'form/purchaseCreditNote-print-form.php', $this->tplName['cn'], $args );
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
		
		do_action( 'wcwh_templating', 'form/purchaseDebitNote-print-form.php', $this->tplName['dn'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/purchaseCDNoteListing.php" ); 
			$Inst = new WCWH_PurchaseCDNote_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#cd' => [ 'width' => '120px' ],
			];

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

			$metas = [ 'note_action', 'remark', 'purchase_doc', 'ref_doc_id', 'ref_doc', 'ref_doc_type', 'invoice', 'supplier_company_code', 'note_reason' ];

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