<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PurchaseRequest_Class" ) ) include_once( WCWH_DIR . "/includes/classes/purchase-request.php" ); 

if ( !class_exists( "WCWH_PurchaseRequest_Controller" ) ) 
{

class WCWH_PurchaseRequest_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_purchase_request";

	public $Notices;
	public $className = "PurchaseRequest_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newPR',
		'row' => 'rowPR'
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1, 6 ];

	public $duplicate_stat = [ 6, 9 ];

	public $skip_strict_unpost = false;

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
		$this->Logic = new WCWH_PurchaseRequest_Class( $this->db_wpdb );
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
						$hav_bqty = false;
						foreach( $datas['detail'] as $row )
						{
							if( $row['bqty'] > 0 ) $hav_bqty = true;
						}

						if( ! $hav_bqty ) 
						{
							$succ = false;
							$this->Notices->set_notice( 'Please confirm row item qty.', 'warning' );
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
				case "duplicate":
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					else
					{
						$ids = is_array( $datas['id'] )? $datas['id'] : array( $datas['id'] );
						$dstat = ( $this->$duplicate_stat )? $this->$duplicate_stat : [ 1, 6, 9 ];
						foreach( $ids as $id )
						{
							$exist = $this->Logic->get_header( [ 'doc_id' => $id, 'status' => $dstat], [], true, [  ] );
							if( !$exist )
							{
								$succ = false;
								$this->Notices->set_notice( 'Prohibited Document Status', 'error' );
							}
						}
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
					$datas = $this->data_sanitizing( $datas );
					$header = $datas['header'];
					$detail = $datas['detail'];
					
					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					$ccc = $this->warehouse['client_company_code'];
					if( $ccc ) 
					{
						$ccc = json_decode( $ccc, true );
						$header['client_company_code'] = $ccc[0];
					}

					if( $header['purchase_request_type'] == 'tool_request' )
					{
						if( $header['doc_id'] )
						{
							$prev_tr_ids = get_document_meta( $header['doc_id'], 'tool_request_id', true );
							if( $prev_tr_ids ) $prev_tr_ids = explode( ",", $prev_tr_ids );
						}
					}

					if( $detail )
					{
						foreach( $detail as $i => $row )
						{
							if( ! $row['bqty'] )
							{
								unset( $detail[$i] );
								continue;
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

							if( $header['purchase_request_type'] == 'tool_request' )
							{
								$result['id'];
								$tr_ids = get_document_meta( $result['id'], 'tool_request_id', 0, true );
								if( $tr_ids )
								{
									$tr_ids = explode( ",", $tr_ids );
									foreach( $tr_ids as $tr_id )
									{
										update_document_meta( $tr_id, 'ordered', $result['id'] );
									}

									if( $prev_tr_ids )
									{
										$tr_diff = array_diff( $prev_tr_ids, $tr_ids );
										if( $tr_diff )
										{
											foreach( $tr_diff as $tr_id )
											{
												delete_document_meta( $tr_id, 'ordered' );
											}
										}
									}
								}
							}

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

								if( $action == 'delete' )
								{
									$found = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [ 'meta'=>[ 'purchase_request_type' ] ] );
									
									if( $found['purchase_request_type'] == 'tool_request' )
									{
										$tr_ids = get_document_meta( $id, 'tool_request_id', 0, true );
										if( $tr_ids )
										{
											$tr_ids = explode( ",", $tr_ids );
											foreach( $tr_ids as $tr_id )
											{
												delete_document_meta( $tr_id, 'ordered' );
											}
										}
									}
								}

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
							if( $this->setting[ $this->section_id ]['strict_unpost'] && ! $this->skip_strict_unpost )
							{
								//check need handshake
								$sync_seller = get_document_meta( $id, 'client_company_code', 0, true );
								if( ! $sync_seller ) $proceed = true;

								//check handshaked
								if( ! $proceed && $sync_seller )
								{
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
										$remote = apply_filters( 'wcwh_api_request', 'unpost_purchase_request', $id, '', $this->section_id );
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
										$this->Notices->set_notice( 'Document synced, please double check on partner side.', 'error' );
									}
								}
							}
							else
							{
								$proceed = true;
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
					$this->print_form( $datas['id'] );

					exit;
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

				//$wh = $handled[ $ref_id ]['supplier_warehouse_code'];
				if( $handled[ $ref_id ]['status'] >= 6 )
				{
					$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'] );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
					}
				}
			}
		}

		return $succ;
	}

	public function pos_purchase_request( $offset_mth = 1 )
	{
		if ( !class_exists( "WCWH_POSSales_Rpt" ) ) include_once( WCWH_DIR . "/includes/reports/posSales.php" ); 
		$Inst = new WCWH_POSSales_Rpt();

		$date_from = date( 'Y-m-1', strtotime( current_time( 'mysql' )." -{$offset_mth} month" ) );
		$date_to = date( 'Y-m-t', strtotime( current_time( 'mysql' )." -{$offset_mth} month" ) );

		$exists = $this->Logic->get_header( [ 'pos_pr'=>$date_from." - ".$date_to ], [], false, [ 'meta'=>['pos_pr'] ] );
		if( !empty( $exists ) ) return;

		$filters = [
			'date_from' => $date_from,
			'date_to' => $date_to,
		];
		$results = $Inst->get_pos_item_sales_report( $filters );
		if( !empty( $results ) )
		{
			$prdts = [];
			foreach( $results as $i => $row )
			{
				$prdts[] = $row['item_code'];
			}
			
			if ( !class_exists( "WCWH_Inventory_Class" ) ) include_once( WCWH_DIR . "/includes/classes/inventory.php" ); 
			$INV = new WCWH_Inventory_Class();
			
			$invs = [];
			$filters = [
				'code' => $prdts,
			];
			$stocks = $INV->get_inventory( $filters, [], false, [ 'no_returnable'=>1 ] );
			if( !empty( $stocks ) )
			{
				foreach( $stocks as $i => $row )
				{
					$invs[ $row['code'] ] = $row;
				}
			}
			
			$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'meta'=>[ 'client_company_code' ] ] );
			if( !empty( $wh['client_company_code'] ) ) 
			{
				$wh['client_company_code'] = json_decode( $wh['client_company_code'], true );
				$wh['client_company_code'] = $wh['client_company_code'][0];
			}
			
			$header = [
				'warehouse_id' => $wh['code'],
				'doc_date' => $date_to,
				'post_date' => $date_to,
				'client_company_code' => $wh['client_company_code'],
				'remark' => "Automate PR for POS sales between {$date_from} until {$date_to}",
				'pos_pr' => $date_from." - ".$date_to,
			];
			//pd($header,1);
			$detail = [];
			foreach( $results as $i => $row )
			{
				if( ! empty( $invs[ $row['item_code'] ] ) )
				{
					$stk = $invs[ $row['item_code'] ];
					$bal = $stk['balance'] - $stk['allocated_qty'];
					if( $bal < 0 )
					{
						$detail[] = [
							'product_id' => $stk['id'],
							'bqty' => $row['qty'],
							'bunit' => !empty( $row['weight'] )? $row['weight'] : '0',
						];
					}
				}
			}
			
			if( $header && $detail )
			{
				$doc = [ 'header'=>$header, 'detail'=>$detail ];
				$result = $this->action_handler( 'save', $doc, $doc );
				if( ! $result['succ'] )
		        {
		            $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		        }
		        else
		        {
		        	$succ = true;
		        	
		        	$doc = [ 'id'=>$result['id'] ];
		        	$result = $Inst->action_handler( 'post', $doc, $doc );
		        	if( ! $result['succ'] )
			        {
			            $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			        }
			        else
			        {
			        	$succ = true;
			        }
		        }
			}
		}
	}

	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = [];

		$default_column['header'] = [ 'doc_id', 'warehouse_id', 'docno', 'sdocno', 'doc_date', 'post_date', 'doc_type', 'hstatus', 'hflag', 'parent', 'client_company_code', 'remark' 
		];

		$default_column['detail'] = [ 'item_id', 'strg_id', 'product_id', 'uom_id', 'bqty', 'uqty', 'bunit', 'uunit', 'ref_doc_id', 'ref_item_id', 'dstatus', '_item_number' ];

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
				
				foreach( $details as $i => $data )
				{
					$found = apply_filters( 'wcwh_get_item', [ 'serial'=>$data['product_id'] ], [], true, [] );
					if( $found )
					{
						$details[$i]['product_id'] = $found['id'];
						$details[$i]['name'] = $found['name'];
						$details[$i]['code'] = $found['code'];
					}
					else
					{
						$succ = false;
					}
				}
				
				//pd($header);pd($details);
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
			//----- Form Restoration 09/11/22
        		case 'transient':
					$save_trans = get_transient( get_current_user_id().$this->section_id.'_save_form' );
					$update_trans = get_transient( get_current_user_id().$this->section_id.'_update_form' );

					$save_timestamp = ($save_trans['timestamp'])? $save_trans['timestamp'] : 0;
					$update_timestamp = ($update_trans['timestamp'])? $update_trans['timestamp'] : 0;
				
					if($save_timestamp < $update_timestamp)
						$flg = 'update';
					else
						$flg = 'save';

					$trans_datas = get_transient( get_current_user_id().$this->section_id.'_'.$flg.'_form' );
					$htmltext = ($trans_datas['_form']['docno'])? $trans_datas['_form']['docno'] : ucfirst($flg);

					if( current_user_cans( [ $flg.'_'.$this->section_id ] )&& $trans_datas):
					?>
						<button 
							class="btn btn-sm btn-primary linkAction"
							title="Add <?php echo $actions[$flg] ?> Purchase Request" 
							data-title="<?php echo $actions[$flg] ?> Purchase Request"
							data-action="<?php echo $this->section_id.'_transient_'.$flg; ?>"
							data-service="<?php echo $this->section_id; ?>_action"  
							data-modal="wcwhModalForm" 
							data-actions="close|submit"						
						>
							Restore PR ( <?php echo $htmltext ?> )
							<i class="fa fa-plus-circle" aria-hidden="true"></i>
						</button>
					<?php
					endif;
				break;
				//----- Form Restoration 09/11/22
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Purchase Request" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Purchase Request"
				>
					<?php echo $actions['save'] ?> Purchase Request
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;

				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="purchase_request_tool_action" class="<?php echo $x; ?> btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Purchase Tool Request"
					data-title="<?php echo $actions['save'] ?> Purchase Tool Request" 
					data-action="purchase_request_tool_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="" 
				>
					<?php echo $actions['save'] ?> Purchase Tool Request
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
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
					case 'purchase_order':
						if( current_user_cans( [ 'access_wh_purchase_order' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/purchaseOrderCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_PurchaseOrder_Controller();
							$titles[ $doc_type ] = "Purchase Order";
						}
					break;
					case 'closed_purchase_request':
						if( current_user_cans( [ 'access_wh_closing_pr' ] ) && empty( $Objs[ $doc_type ] ) ) 
						{
							include_once( WCWH_DIR . "/includes/controller/closingPRCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_ClosingPR_Controller();
							$titles[ $doc_type ] = "Close Purchase Request";
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

	public function gen_tool_form()
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
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		$args['data']['purchase_request_type'] = 'tool_request';
		$args['NoAddItem'] = true;

		$docs = $this->Logic->get_header( [ 'doc_type'=>'tool_request', 'ordered'=>'IS_NULL', 'status'=>[6] ], [], false, [ 'usage'=>1, 'meta'=>[ 'ordered', 'remark' ] ] );
		if( $docs )
		{
			$r_docs = [];
			$r_items = [];
			foreach( $docs as $i => $doc )
			{
				if( $doc['ordered'] ) continue;

				$r_docs[ $doc['doc_id'] ] = $doc['docno'];
				if( $doc['remark'] ) $remarks[ $doc['doc_id'] ] = $doc['docno'].":".$doc['remark'];

				$ditems = $this->Logic->get_detail( [ 'doc_id'=>$doc['doc_id'] ], [], false, [ 'uom'=>1, 'usage'=>1 ] );
				if( $ditems )
				{
					foreach( $ditems as $ditem )
					{
						$r_items[ $ditem['product_id'] ]+= $ditem['bqty'];
					}
				}
			}

			if( $r_items )
			{
				$ids = array_keys( $r_items );
				$items = apply_filters( 'wcwh_get_item', [ 'id'=>$ids ], [], false, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
				if( $items )
				{
					$details = array();
					foreach( $items as $i => $item )
					{	
						$details[ $item['id'] ] = array(
							'id' =>  $item['id'],
							'bqty' => '',
							'product_id' => $item['id'],
							'item_id' => '',
							'line_item' => [ 
								'name'=>$item['name'], 'code'=>$item['code'], 'uom_code'=>$item['uom_code'], 
								'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
							],
							'bqty' => $r_items[ $item['id'] ],
						);
					}

					$filters = [];
			        if($this->warehouse['id']) $filters['seller'] = $this->warehouse['id'];
			        include_once( WCWH_DIR . "/includes/reports/reorderReport.php" );
			        $RR = new WCWH_Reorder_Rpt();

			        if( $this->setting[ $this->section_id ]['no_kg'] )
			        {
			        	$ag = [ 'uom'=>1, 'isMetric'=>'no' ];
						if( $this->setting[ $this->section_id ]['no_kg_excl_cat'] )
							$ag[ 'isMetricExclCat' ] = $this->setting[ $this->section_id ]['no_kg_excl_cat'];

				    	$reorder_item_info = $RR->get_reorder_report( $filters, [], $ag );
			        }
				    else
				    	$reorder_item_info = $RR->get_reorder_report( $filters, [], [ 'uom'=>1 ] );

			        if( $reorder_item_info )
			        {
			        	$datas['reorder_item_info'] = $reorder_item_info;
			        	$rii = [];
			        	foreach ($reorder_item_info as $key => $value)
			        	{
			        		$rii[$value['item_id']] = $value;
			        	}
			        }

			        foreach( $details as $pid => $item ) 
			        {
			        	if( $rii &&  $rii[$pid] )
			        	{
			        		$hms = ($rii[$pid]['hms_month'])? $rii[$pid]['hms_qty'].'<br> ('.$rii[$pid]['hms_month'].')' : $rii[$pid]['hms_qty'];
			        		$details[$pid]['order_type'] = $rii[$pid]['order_type'];
			        		$details[$pid]['stock_bal']	 = $rii[$pid]['stock_bal'];
			        		$details[$pid]['hms'] 		 = $hms;
			        		//$details[$pid]['po_qty'] 	 = $rii[$pid]['po_qty'];
			        		//$details[$pid]['rov'] 		 = $rii[$pid]['final_rov'];
			        	}
			        }

			        $args['data']['tool_request_id'] = array_keys( $r_docs );
			        $args['data']['tool_request_ref_doc'] = $r_docs;
			        $args['data']['remark'] = implode( "\n", $remarks );
					$args['data']['details'] = $details;
				}
			}
		}

		do_action( 'wcwh_get_template', 'form/purchaseRequest-form.php', $args );
	}

	public function gen_form( $ids = array(), $trans_action = '', $forms_ids = array() )
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
		);

		//----- Form Restoration 09/11/22
		if( $trans_action && $trans_action == 'update' ) $transient_data = get_transient( get_current_user_id().$this->section_id.'_update_form' );
		else if ( $trans_action && $trans_action == 'save' ) $transient_data = get_transient( get_current_user_id().$this->section_id.'_save_form' );
		//----- Form Restoration 09/11/22
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $ids )
		{
			$items = apply_filters( 'wcwh_get_item', [ 'id'=>$ids ], [], false, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
			if( $items )
			{
				$details = array();
				foreach( $items as $i => $item )
				{	
					$details[$i] = array(
						'id' =>  $item['id'],
						'bqty' => '',
						'product_id' => $item['id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['name'], 'code'=>$item['code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
						],
					);
				}
				$args['data']['details'] = $details;
			}
		}
		else if( $transient_data ) //----- Form Restoration 09/11/22
		{
			$header = $transient_data['_form'];
			$detail = $transient_data['_detail'];
			$datas = [];

			if( $header['doc_id'] ) $args['action'] = 'update';

			$datas = $header;

			if( !$args['data']['warehouse_id'] && $this->warehouse )
			{
				$datas['warehouse_id'] = $this->warehouse['code'];
			}

			//------detail
			if($this->warehouse['id']) $filters['seller'] = $this->warehouse['id'];
		    include_once( WCWH_DIR . "/includes/reports/reorderReport.php" );
		    $RR = new WCWH_Reorder_Rpt();

		    if( $this->setting[ $this->section_id ]['no_kg'] )
		    {
		    	$ag = [ 'uom'=>1, 'isMetric'=>'no' ];
				if( $this->setting[ $this->section_id ]['no_kg_excl_cat'] )
					$ag[ 'isMetricExclCat' ] = $this->setting[ $this->section_id ]['no_kg_excl_cat'];

				$reorder_item_info = $RR->get_reorder_report( $filters, [], $ag );
		    }
		    else
		    	$reorder_item_info = $RR->get_reorder_report( $filters, [], [ 'uom'=>1 ] );

		    if( $reorder_item_info )
		    {
		        $datas['reorder_item_info'] = $reorder_item_info;
		        $rii = [];
		        foreach ($reorder_item_info as $key => $value)
		        {
		        	$rii[$value['item_id']] = $value;
		        }
		    }

		    foreach( $detail as $i => $item )
		    {
		    	$datas['details'][$i] = $item;
		    	if($rii &&  $rii[$item['product_id']])
		        {
		        	$hms = ($rii[$item['product_id']]['hms_month'])? $rii[$item['product_id']]['hms_qty'].'<br> ('.$rii[$item['product_id']]['hms_month'].')' : $rii[$item['product_id']]['hms_qty'];
		        	$datas['details'][$i]['order_type'] = $rii[$item['product_id']]['order_type'];
		        	$datas['details'][$i]['stock_bal'] = $rii[$item['product_id']]['stock_bal'];
		        	$datas['details'][$i]['hms'] = $hms;
		        	$datas['details'][$i]['po_qty'] = $rii[$item['product_id']]['po_qty'];
		        	$datas['details'][$i]['rov'] = $rii[$item['product_id']]['final_rov'];

		        	$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        	$datas['details'][$i]['prdt_name'] = $rii[$item['product_id']]['item_code'].' - '.$rii[$item['product_id']]['item_name'];
		        	$datas['details'][$i]['line_item'] = [
		        		'name'=>$rii[$item['product_id']]['item_name'], 'code'=>$rii[$item['product_id']]['item_code'], 'uom_code'=>$rii[$item['product_id']]['uom_code'],
		        		'uom_fraction'=>$rii[$item['product_id']]['uom_fraction'], 'required_unit'=>$item['required_unit']
		        	];

		        	$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2, true );
		        }

		    }
		    $args['data'] = $datas;
		    //--------detail
		}
		else if( $forms_ids && !$transient_data && !$ids)
		{
			$fids = $forms_ids;
			$fids = is_array( $forms_ids )? $forms_ids : array( $forms_ids );

			$details = [];
			$datas = [];

			foreach ($fids as $id) 
			{
				if( !$id ) continue;
				$ditems = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1, 'usage'=>1 ] );

				foreach($ditems as $item)
				{
					if( !$details[$item['product_id']] )
					{
						$details[$item['product_id']] = array(
							'id' =>  $item['product_id'],
							'bqty' => $item['bqty'],
							'product_id' => $item['product_id'],
							'item_id' => '',
							'line_item' => [ 
								'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom'], 
								'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'],
							],
						);
					}
					else
					{
						$details[$item['product_id']]['bqty'] += $item['bqty'];
					}					
				}							
			}

			if( $details )
			{
				$filters = [];
		        if($this->warehouse['id']) $filters['seller'] = $this->warehouse['id'];
		        include_once( WCWH_DIR . "/includes/reports/reorderReport.php" );
		        $RR = new WCWH_Reorder_Rpt();

		        if( $this->setting[ $this->section_id ]['no_kg'] )
		        {
		        	$ag = [ 'uom'=>1, 'isMetric'=>'no' ];
					if( $this->setting[ $this->section_id ]['no_kg_excl_cat'] )
						$ag[ 'isMetricExclCat' ] = $this->setting[ $this->section_id ]['no_kg_excl_cat'];

					$reorder_item_info = $RR->get_reorder_report( $filters, [], $ag );
		        }
			    else
			    	$reorder_item_info = $RR->get_reorder_report( $filters, [], [ 'uom'=>1 ] );

		        if( $reorder_item_info )
		        {
		        	$datas['reorder_item_info'] = $reorder_item_info;
		        	$rii = [];
		        	foreach ($reorder_item_info as $key => $value)
		        	{
		        		$rii[$value['item_id']] = $value;
		        	}
		        }

		        foreach ($details as $pid => $item) 
		        {
		        	if( $rii &&  $rii[$pid] )
		        	{
		        		$hms = ($rii[$pid]['hms_month'])? $rii[$pid]['hms_qty'].'<br> ('.$rii[$pid]['hms_month'].')' : $rii[$pid]['hms_qty'];
		        		$details[$pid]['order_type'] = $rii[$pid]['order_type'];
		        		$details[$pid]['stock_bal']	 = $rii[$pid]['stock_bal'];
		        		$details[$pid]['hms'] 		 = $hms;
		        		$details[$pid]['po_qty'] 	 = $rii[$pid]['po_qty'];
		        		$details[$pid]['rov'] 		 = $rii[$pid]['final_rov'];
		        	}
		        }

		        $args['data']['details'] = $details;
			}

		}
		//----- Form Restoration 09/11/22

		do_action( 'wcwh_get_template', 'form/purchaseRequest-form.php', $args );
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
			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true );
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				if( $datas['tool_request_ref_doc'] ) $datas['tool_request_ref_doc'] = explode( ",", $datas['tool_request_ref_doc'] );
				if( $datas['tool_request_id'] ) $datas['tool_request_id'] = explode( ",", $datas['tool_request_id'] );

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1, 'usage'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1 ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();
		        	
		        if( $datas['details'] )
		        {
		        	//--------- 14/9/22 ROV
		        	if($this->warehouse['id']) $filters['seller'] = $this->warehouse['id'];
		        	include_once( WCWH_DIR . "/includes/reports/reorderReport.php" );
		        	$RR = new WCWH_Reorder_Rpt();

		        	if( $this->setting[ $this->section_id ]['no_kg'] )
		        	{
		        		$ag = [ 'uom'=>1, 'isMetric'=>'no' ];
						if( $this->setting[ $this->section_id ]['no_kg_excl_cat'] )
							$ag[ 'isMetricExclCat' ] = $this->setting[ $this->section_id ]['no_kg_excl_cat'];

						$reorder_item_info = $RR->get_reorder_report( $filters, [], $ag );
		        	}
				    else
				    	$reorder_item_info = $RR->get_reorder_report( $filters, [], [ 'uom'=>1 ] );

		        	if( $reorder_item_info )
		        	{
		        		$datas['reorder_item_info'] = $reorder_item_info;
		        		$rii = [];
		        		foreach ($reorder_item_info as $key => $value)
		        		{
		        			$rii[$value['item_id']] = $value;
		        		}
		        	}
		        	//--------- 14/9/22 ROV

		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		//--------- 14/9/22 ROV
		        		if($rii &&  $rii[$item['product_id']])
		        		{
		        			$hms = ($rii[$item['product_id']]['hms_month'])? $rii[$item['product_id']]['hms_qty'].'<br> ('.$rii[$item['product_id']]['hms_month'].')' : $rii[$item['product_id']]['hms_qty'];
		        			$datas['details'][$i]['order_type'] = $rii[$item['product_id']]['order_type'];
		        			$datas['details'][$i]['stock_bal'] = $rii[$item['product_id']]['stock_bal'];
		        			$datas['details'][$i]['hms'] = $hms;
		        			$datas['details'][$i]['po_qty'] = $rii[$item['product_id']]['po_qty'];
		        			$datas['details'][$i]['rov'] = $rii[$item['product_id']]['final_rov'];
		        		}
		        		//--------- 14/9/22 ROV

		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];

		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['bqty'] = round_to( $datas['details'][$i]['bqty'], 2, true );
		        		$datas['details'][$i]['lqty'] = round_to( $item['bqty'] - $item['uqty'], 2, true );
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );

				//--------- 14/9/22 ROV				
		        $args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'prdt_name' => 'Item',
		        		'uom_code' => 'UOM',
		        		'order_type' => 'Order Type',
		        		'stock_bal' => 'Stock',
		        		'hms' => 'HMS Qty',
		        		'po_qty' => 'PO Qty',
		        		'rov' => 'ROV',
		        		'bqty' => 'Qty',
		        		'lqty' => 'Leftover',
		        		//'status' => 'Status',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
		        //--------- 14/9/22 ROV
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/purchaseRequest-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/purchaseRequest-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/purchaseRequest-row.php', $this->tplName['row'] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/purchaseRequestListing.php" ); 
			$Inst = new WCWH_PurchaseRequest_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

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

			$metas = [ 'remark', 'ref_doc', 'purchase_request_type' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}