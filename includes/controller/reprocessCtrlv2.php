<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Simplify_Reprocess_Class" ) ) include_once( WCWH_DIR . "/includes/classes/reprocessv2.php" ); 

if ( !class_exists( "WCWH_Simplify_Reprocess_Controller" ) ) 
{

class WCWH_Simplify_Reprocess_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_simplify_reprocess";

	public $Notices;
	public $className = "Simplify_Reprocess_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newRP',
		'row' => 'rowRP',
	);

	public $useFlag = false;

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
		$this->Logic = new WCWH_Simplify_Reprocess_Class( $this->db_wpdb );
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
						$items = [];

						foreach( $datas['detail'] as $i => $row )
						{
							if(array_key_exists($row['material_ids'], $items))
							{
								$items[$row['material_ids']]['material_uqty'] += $row['material_uqty'];
							}
							else
							{
								$items[$row['material_ids']]['material_uqty'] = $row['material_uqty'];
							}
						}

						foreach($items as $i => $row)
						{
							$filters = [ 'status'=>1, 'id'=>$i ];
							if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
							$inventory = [];
                        	$materials = apply_filters( 'wcwh_get_item', $filters, [], true, [ 'uom'=>1, 'isUnit'=>1, 'stocks'=>$this->warehouse['code'], 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'], 'meta'=>['tolerance', 'tolerance_rounding']] );
							if( $materials )
							{
								if( $materials['parent'] > 0 )
                            	{
                                	$temp_filters = ['wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging'];
                                	$storage = apply_filters( 'wcwh_get_storage', $temp_filters, [], true, [ 'usage'=>1 ] );

                                	$temp_filters = [ 'wh_code'=>$this->warehouse['code'], 'strg_id'=>$storage['id'], 'item_id'=>$materials['parent'] ];
                                	$stock = apply_filters( 'wcwh_get_stocks', $temp_filters, [], false, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
                                	if($stock) $inventory = $stock;
                            	}
                            	$stk = ( $inventory['qty'] )? $inventory['qty'] : $materials['stock_qty'];
                            	$stk-= ( $inventory['stock_allocated'] )? $inventory['allocated_qty'] : $materials['stock_allocated'];
                            	if($row['material_uqty']>$stk)
								{
									$succ = false;
									$this->Notices->set_notice( 'Material Usage could not exceed material Qty.', 'warning' );
								}
							}
							else
							{
								$succ = false;
								$this->Notices->set_notice( 'Material Not Found or Status Inactive', 'error' );
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
			//$succ = false;
			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$header = $datas['header'];
					$detail = $datas['detail'];

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					$refDetail = [];
					if( $header['ref_doc_id'] )
					{	
						$ref_header = $this->Logic->get_header( [ 'doc_id'=>$header['ref_doc_id'], 'doc_type'=>'none' ], [], true, [ 'company'=>1 ] );
						if( $ref_header )
						{
							$header['ref_warehouse'] = $ref_header['warehouse_id'];
							$header['ref_doc_type'] = $ref_header['doc_type'];
							$header['ref_doc'] = $ref_header['docno'];

							if( ! $header['doc_id'] )
							{
								$header['ref_status'] = $ref_header['status'];
							}

							$metas = get_document_meta( $ref_header['doc_id'] );
							$ref_header = $this->combine_meta_data( $ref_header, $metas );
							$ref_detail = $this->Logic->get_detail( [ 'doc_id'=>$header['ref_doc_id'] ], [], false, [ 'transact'=>1, 'usage'=>1 ] );
							if( $ref_detail )
							{
								foreach( $ref_detail as $i => $row )
								{
									$metas = get_document_meta( $header['ref_doc_id'], '', $row['item_id'] );
									$row = $this->combine_meta_data( $row, $metas );

									$refDetail[ $row['item_id'] ] = $row;
								}
							}
						}
					}

					if($detail)
					{
						foreach( $detail as $i => $row )
						{
							if( ! $row['bqty'] || !$row['material_uqty'])
							{
								unset( $detail[$i] );
								continue;
							}

							if($row['product_id'])
							{
								$filters = ['id'=>$row['product_id'] ];
								if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
								$reprocess_item = apply_filters( 'wcwh_get_item', $filters, [], true, [ 'isUnit'=>1 ] );
								if($reprocess_item)
								{
									$detail[$i]['batch_serial'] = $reprocess_item['serial'].get_datime_fragment( $header['doc_date'], 'ymd' );
								}
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

							if( $succ && in_array( $action, [ 'post' ] ) )//----create /post good issue first
							{
								$succ = $this->direct_issue_handler( $id, $action );
								if( ! $succ )
								{
									//$this->Notices->set_notice( 'GI Failed', 'error' );
									break;
								}
							}

							if( $succ )//-----post case: post reprocess doc
							{
								$result = $this->Logic->child_action_handle( $action, $header );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}
							}

							if( $succ && in_array( $action, [ 'unpost' ] ) )
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
			}
		}

		return $succ;
	}


	public function direct_issue_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		if ( !class_exists( "WCWH_GoodIssue_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-issue.php" ); 
		$Inst = new WCWH_GoodIssue_Class( $this->db_wpdb );
		//$Inst->setUpdateUqtyFlag( false );

		$succ = true;
		$issue_type = 'reprocess';
		
		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'reprocess' ], [], true, [] );
				//---get header details for good issue
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					$header = [
						'warehouse_id' => $this->warehouse['code'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'good_issue_type' => $issue_type,
						'reprocess_doc' => $doc_header['doc_id'],
					];

					if( $doc_header['ref_doc_id'] )
					{
						$header['parent'] = $doc_header['ref_doc_id'];
						$header['ref_doc'] = $doc_header['ref_doc'];
						$header['ref_doc_id'] = $doc_header['ref_doc_id'];
						$header['ref_doc_type'] = $doc_header['ref_doc_type'];
						$header['ref_status'] = $doc_header['ref_status'];
						$header['ref_warehouse'] = $doc_header['ref_warehouse'];
					}
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$line = [
								'product_id' => $row['material_ids'],
								'bqty' => $row['material_uqty'],
								'item_id' => '',
							];

							if( $doc_header['ref_doc_id'] )
							{
								$line['ref_doc_id'] = $row['ref_doc_id'];
								$line['ref_item_id'] = $row['ref_item_id'];
								$line['stock_item_id'] = $row['ref_item_id'];
							}

							$detail[] = $line;
						}
					}

					if( $header && $detail )
					{
						$result = $Inst->child_action_handle( 'save', $header, $detail );//---create good issue
		                if( ! $result['succ'] )
		                {
							$succ = false;
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$gi_id = $result['id'];
		                	$result = $Inst->child_action_handle( 'post', [ 'doc_id'=>$gi_id ] );//----post good issue
			                if( ! $result['succ'] )
			                {
								$succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	$gi_doc = $this->Logic->get_header( [ 'doc_id'=>$gi_id, 'doc_type'=>'good_issue' ], [], true, [] );
			                	if( $gi_doc ) //---- update reprocess doc meta with good issue reference
			                	{
			                		update_document_meta( $doc_header['doc_id'], 'ref_doc_id', $gi_doc['doc_id'] );
			                		update_document_meta( $doc_header['doc_id'], 'ref_doc_type', $gi_doc['doc_type'] );
			                		update_document_meta( $doc_header['doc_id'], 'ref_doc', $gi_doc['docno'] );
			                		update_document_meta( $doc_header['doc_id'], 'ref_status', $gi_doc['status'] );
			                		update_document_meta( $doc_header['doc_id'], 'ref_warehouse', $gi_doc['warehouse_id'] );

			                		$gi_detail = $this->Logic->get_detail( [ 'doc_id'=>$gi_id ], [], false, [ 'transact'=>1, 'usage'=>1 ] );
			                		$giDetail = [];
			                		if( $gi_detail )
			                		{
			                			foreach( $gi_detail as $gi_row )
			                			{
			                				//-----idx == _item_number
			                				//---$giDetail[ $gi_row['product_id'] ] cannot be used
			                				//---- as same product id can have many type of reprocessed item
			                				$giDetail[ $gi_row['idx'] ] = $gi_row;
			                			}

			                			foreach( $doc_detail as $i => $row )
										{
											$metas = get_document_meta( $doc_id, '', $row['item_id'] );
											$row = $this->combine_meta_data( $row, $metas );

											$gi_ref = $giDetail[ $row['_item_number'] ];
											$uprice = round_to( $gi_ref['weighted_total'] / $row['bqty'], 5 );
											update_document_meta( $row['doc_id'], 'uprice', $uprice, $row['item_id'] );
											update_document_meta( $row['doc_id'], 'total_amount', $gi_ref['weighted_total'], $row['item_id'] );

											$upd_item = [
												'ref_doc_id' =>$gi_ref['doc_id'],
												'ref_item_id' => $gi_ref['item_id'],
											];
											if( ! $Inst->update_document_items( [ 'item_id'=>$row['item_id'] ] , $upd_item ) )
											{
												$succ = false;
											}
											else
											{
												if( ! $Inst->update_document_items_uqty( $gi_ref['item_id'], $row['bqty'], $row['material_uqty'], "+" ) )
													$succ = false;

												if( $succ ) $succ = $Inst->update_document_item_status( $gi_ref['item_id'], 9 );
											}
										}

										if( $succ )
										{
											$parent_doc[ $gi_doc['doc_id'] ] = $gi_doc['doc_id'];
											$succ = $Inst->update_document_header_status_handles( $parent_doc );
										}
			                		}
			                	}
			                	else
			                	{
			                		$succ = false;
			                	}
			                }
		                }
					}
				}
			break;
			case 'unpost':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'reprocess' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					$gi_header = $this->Logic->get_header( [ 'doc_id'=>$doc_header['ref_doc_id'], 'doc_type'=>'good_issue' ], [], true, [] );
					if( $gi_header )
					{
						$metas = get_document_meta( $gi_header['doc_id'] );
						$gi_header = $this->combine_meta_data( $gi_header, $metas );
					}

					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						foreach( $doc_detail as $i => $row )
						{
							if( ! $Inst->update_document_items_uqty( $row['ref_item_id'], $row['bqty'], $row['bunit'], "-", $this->Logic->getParentStatus() ) )
									$succ = false;
							else
							{
								$upd_item = [
									'ref_doc_id' => 0,
									'ref_item_id' => 0,
								];
								if( $gi_header )
								{
									$gi_row = $this->Logic->get_detail( [ 'doc_id'=>$row['ref_doc_id'], 'item_id'=>$row['ref_item_id'] ], [], true, [ 'usage'=>1 ] );
									$upd_item = [
										'ref_doc_id' => $gi_row['ref_doc_id'],
										'ref_item_id' => $gi_row['ref_item_id'],
									];
								}
								
								if( ! $Inst->update_document_items( [ 'item_id'=>$row['item_id'] ] , $upd_item ) )
								{
									$succ = false;
								}
								else
								{
									delete_document_meta( $row['doc_id'], 'uprice', '', $row['item_id'] );
									delete_document_meta( $row['doc_id'], 'total_amount', '', $row['item_id'] );
								}
							}
						}
					}

					if( $succ )
					{
						$parent_doc[ $doc_header['ref_doc_id'] ] = $doc_header['ref_doc_id'];
						$succ = $Inst->update_document_header_status_handles( $parent_doc );

						delete_document_meta( $doc_header['doc_id'], 'ref_doc_id' );
						delete_document_meta( $doc_header['doc_id'], 'ref_doc_type' );
						delete_document_meta( $doc_header['doc_id'], 'ref_doc' );
						delete_document_meta( $doc_header['doc_id'], 'ref_status' );
						delete_document_meta( $doc_header['doc_id'], 'ref_warehouse' );
						
						if( $gi_header )
						{
							update_document_meta( $doc_header['doc_id'], 'ref_doc_id', $gi_header['ref_doc_id'] );
			                update_document_meta( $doc_header['doc_id'], 'ref_doc_type', $gi_header['ref_doc_type'] );
			                update_document_meta( $doc_header['doc_id'], 'ref_doc', $gi_header['ref_doc'] );
			                update_document_meta( $doc_header['doc_id'], 'ref_status', $gi_header['ref_status'] );
			                update_document_meta( $doc_header['doc_id'], 'ref_warehouse', $gi_header['ref_warehouse'] );
						}

						$header = [ 'doc_id'=>$doc_header['ref_doc_id'] ];
						$result = $Inst->child_action_handle( 'unpost', $header );
		                if( ! $result['succ'] )
		                {
							$succ = false;
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$result = $Inst->child_action_handle( 'delete', $header );
		                	if( ! $result['succ'] )
			                {
								$succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
		                }
					}
				}
			break;
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
				<button id="reprocess_action" class="btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Simplify Reprocess"
					data-title="<?php echo $actions['save'] ?> Simplify Reprocess" 
					data-action="reprocess_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					<?php //data-source="#reprocess_reference" data-strict="yes" ?>
				>
					<?php echo $actions['save'] ?> Simplify Reprocess
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_reference_doc( $doc_id = 0, $title = '', $doc_type = 'sale_order' )
	{
		if( ! $doc_id || ! $doc_type ) return;

		switch( $doc_type )
		{
			case 'good_issue':
				if( ! class_exists( 'WCWH_GoodIssue_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
				$Inst = new WCWH_GoodIssue_Controller();
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
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		do_action( 'wcwh_get_template', 'form/reprocess-formv2.php', $args );
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
			if($datas)
			{
				$datas['post_date'] = !empty((int)$header['post_date']) ? $header['post_date'] : "";
				$metas = $this ->Logic ->get_document_meta($id);
				$datas = $this ->combine_meta_data($datas, $metas);

				$hasRef = false;
				if( !empty( $datas['ref_doc_id'] ) ) 
				{
					$ref_datas = $this->Logic->get_header( [ 'doc_id'=>$datas['ref_doc_id'], 'doc_type'=>'none' ], [], true, [] );
					if( $ref_datas ) $datas['ref_doc_date'] = $ref_datas['doc_date'];
					if( $ref_datas ) $hasRef = true;
				}

				if( $datas['status'] > 0 )
					$ag = [ 'uom'=>1, 'usage'=>1, 'stocks'=>$this->warehouse['code'] ];
				else
					[ 'uom'=>1, 'stocks'=>$this->warehouse['code'] ];

				if( $hasRef ) $ag['ref_transact'] = 1;

				$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, $ag );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();
				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_reprocess'] ) )
				{
					$hides = [ 'uprice', 'total_amount' ];
				}

				if( $datas['details'] )
				{
		        	$details = [];
		        	$total_amount = 0;
		        	$num = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$num++;
		        		$row = $item;
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$row = $this->combine_meta_data( $row, $detail_metas );

		        		$row['index'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".$num.".</span>" : $num.".";
		        		$row['id'] = $item['product_id'];
		        		$row['item'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        		$row['mat_id'] = $row['material_ids'];
		        		$row['mat_req'] = $row['material_req'];
		        		$row['fraction'] = ( $item['uom_fraction'] )? 'positive-number' : 'positive-integer';

		        		$filters = ['id'=>$row['mat_id']];
		        		if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];

		        		$material = apply_filters( 'wcwh_get_item', $filters, [], true, [ 'uom'=>1, 'isUnit'=>1, 'stocks'=>$this->warehouse['code'], 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'], 'meta'=>['tolerance', 'tolerance_rounding']] );
		        		if( $material['parent'] > 0 )
                        {
                            $temp_filters = ['wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging'];
                            $storage = apply_filters( 'wcwh_get_storage', $temp_filters, [], true, [ 'usage'=>1 ] );

                            $temp_filters = [ 'wh_code'=>$this->warehouse['code'], 'strg_id'=>$storage['id'], 'item_id'=>$material['parent'] ];
                            $stock = apply_filters( 'wcwh_get_stocks', $temp_filters, [], false, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
                            if($stock) $inventory = $stock;
                        }
                        $stk = ( $inventory['qty'] )? $inventory['qty'] : $material['stock_qty'];
                        $stk-= ( $inventory['stock_allocated'] )? $inventory['allocated_qty'] : $material['stock_allocated'];

                        $row['mat'] = $material['code'].' - '.$material['name'];
                        $row['mat_uom'] = $material['_uom_code'];
                        $row['mat_stock'] = $stk;
                        $row['tolerance'] = ( $material['tolerance'] )?  $material['tolerance']: 0;
                        $row['tolerance_rounding'] = ( $material['tolerance_rounding'] )?  $material['tolerance_rounding']: 0;
                        $row['tolerance_pholder'] = '';

                        $row['uprice'] = round_to( $row['uprice'], 5, true );//---uprice
                        $row['total_amount'] = round_to( $row['total_amount'], 2, true );//--- total_amount
                        $total_amount+= $row['total_amount'];

                        $details[] = $row;		        		
		        	}

		        	if( $isView && !$hides)
		        	{
		        		$final = [];
			        	$final['mat'] = '<strong>TOTAL:</strong>';
			        	$final['total_amount'] = '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
			        	$details[] = $final;
		        	}
		        	$datas['details'] = $details;

		       		$args['data'] = $datas;
					unset( $args['new'] );
		        
		        	$args['render'] = $Inst->get_listing( [
		        		'index' => '',
		        		'mat' => 'From Item',
		        		'mat_uom' => 'From UOM',
		        		'item' => 'To Item',
		        		'uom' => 'To UOM',
		        		'bqty' => 'Qty',
		        		'unit' => 'Metric (kg/l)',
		        		'material_uqty' => 'Usage',
		        		'uprice' => 'Price',
		        		'total_amount' => 'Total Amt',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	$hides,
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        	);
				}
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/reprocess-formv2.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/reprocess-formv2.php', $args );
		}
	}

	//---generate barcode/qr column for each row
	public function column_serial( $item, $datas, $Inst )
	{
		$actions = array();

		$batch_serial = ( $item['batch_serial'] )? $item['batch_serial'] : $item['prdt_serial'].get_datime_fragment( $datas['doc_date'], 'ymd' );
		
		if( $item['prdt_serial'] ) 
		{	
			$data = [ 'dataitem'=>$item['item_name'], 'dataserial'=>$batch_serial ];
				
			$actions['qr'] = $Inst->get_action_btn( $item, 'qr', [ 'serial'=>$batch_serial, 'tpl'=>'product_label', 'datas'=>$data ] );
			$actions['barcode'] = $Inst->get_action_btn( $item, 'barcode', [ 'serial'=>$batch_serial, 'tpl'=>'product_label', 'datas'=>$data ] );
		}

		return sprintf( '%1$s %2$s', '<strong>'.$item['batch_serial'].'</strong>', $Inst->row_actions( $actions, true ) ); 
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/reprocess-rowv2.php', $this->tplName['row'] );
	}

	public function print_tpl()
	{
		$tpl_code = "itemlabel0001";
		$tpl = apply_filters( 'wcwh_get_suitable_template', $tpl_code );
		if( $tpl )
		{
			do_action( 'wcwh_templating', $tpl['tpl_path'].$tpl['tpl_file'], 'product_label', $args );
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
			include_once( WCWH_DIR . "/includes/listing/reprocessListingv2.php" ); 
			$Inst = new WCWH_Simplify_Reprocess_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$count = $this->Logic->count_statuses( $this->warehouse['code'], $this->ref_doc_type, $this->ref_issue_type );
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

			$metas = [ 'remark', 'ref_doc', 'ref_doc_id', 'ref_doc_type' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'parent'=>1, 'meta'=>$metas, 'transact_out'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}