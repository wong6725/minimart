<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_TransferItem_Class" ) ) include_once( WCWH_DIR . "/includes/classes/transfer-item.php" ); 

if ( !class_exists( "WCWH_TransferItem_Controller" ) ) 
{

class WCWH_TransferItem_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_transfer_item";

	public $Notices;
	public $className = "TransferItem_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newTI',
		'row' => 'rowTI',
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
		$this->Logic = new WCWH_TransferItem_Class( $this->db_wpdb );
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
						/*foreach( $datas['detail'] as $i => $row )
						{
							if( isset( $row['ref_item_id'] ) && $row['ref_item_id'] > 0 && isset( $row['ref_bal'] ) && $row['fbqty'] > $row['ref_bal'] )
							{
								$succ = false;
								$this->Notices->set_notice( 'From Qty could not exceed reference.', 'warning' );
							}
						}*/
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

					$refDetail = [];
					if( $header['ref_doc_id'] )
					{	
						$header['ref_doc'] = $header['delivery_doc'];
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

					if( $detail )
					{
						foreach( $detail as $i => $row )
						{
							if( ! $row['bqty'] )
							{
								unset( $detail[$i] );
								continue;
							}

							if( $row['to_product_id'] )
							{
								$detail[$i]['ref_product_id'] = $row['product_id'];
								$detail[$i]['product_id'] = $row['to_product_id'];

								if( $refDetail[ $row['ref_item_id'] ] )
								{
									$detail[$i]['bunit'] = ( $refDetail[ $row['ref_item_id'] ]['bunit'] > 0 )? $refDetail[ $row['ref_item_id'] ]['bunit'] : $refDetail[ $row['ref_item_id'] ]['tran_bunit'];
									$detail[$i]['uprice'] = ( $refDetail[ $row['ref_item_id'] ]['ucost'] > 0 )? $refDetail[ $row['ref_item_id'] ]['ucost'] : $refDetail[ $row['ref_item_id'] ]['weighted_price'];
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
								$succ = $this->direct_issue_handler( $id, $action );
								if( ! $succ )
								{
									//$this->Notices->set_notice( 'GI Failed', 'error' );
									break;
								}
							}

							if( $succ )
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
		$issue_type = 'transfer_item';
		
		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'transfer_item' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					$header = [
						'warehouse_id' => $this->warehouse['code'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'good_issue_type' => $issue_type,
						'transfer_item_doc' => $doc_header['doc_id'],
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
								'product_id' => $row['ref_product_id'],
								'bqty' => ( $row['fbqty'] > 0 )? $row['fbqty'] : $row['bqty'],
								'sunit' => $row['sunit'],
								'item_id' => '',
							];

							if( $doc_header['ref_doc_id'] )
							{
								$line['ref_doc_id'] = $row['ref_doc_id'];
								$line['ref_item_id'] = $row['ref_item_id'];
								//$line['stock_item_id'] = $row['ref_item_id'];
							}

							$detail[] = $line;
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
		                	$gi_id = $result['id'];
		                	$result = $Inst->child_action_handle( 'post', [ 'doc_id'=>$gi_id ] );
			                if( ! $result['succ'] )
			                {
								$succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
			                else
			                {
			                	$gi_doc = $this->Logic->get_header( [ 'doc_id'=>$gi_id, 'doc_type'=>'good_issue' ], [], true, [] );
			                	if( $gi_doc )
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
			                				$giDetail[ $gi_row['product_id'] ] = $gi_row;
			                			}

			                			foreach( $doc_detail as $i => $row )
										{
											$metas = get_document_meta( $doc_id, '', $row['item_id'] );
											$row = $this->combine_meta_data( $row, $metas );

											$gi_ref = $giDetail[ $row['ref_product_id'] ];
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
												if( ! $Inst->update_document_items_uqty( $gi_ref['item_id'], $row['bqty'], $row['bunit'], "+" ) )
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
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'transfer_item' ], [], true, [] );
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
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'] );
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'remark' ], 'New Transfer Item by Document (GI Required)' );
	        echo '<div id="transfer_item_reference_content" class="col-md-6">';
	        wcwh_form_field( '', 
	            [ 'id'=>'transfer_item_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#transfer_item_action"'], 'class'=>['select2','triggerChange'], 
	                'options'=> $options, 'offClass'=>true
	            ], 
	            ''
	        );
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
				<button id="transfer_item_action" class="btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Transfer Item"
					data-title="<?php echo $actions['save'] ?> Transfer Item" 
					data-action="transfer_item_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>	<?php //data-source="#transfer_item_reference" data-strict="yes" ?>
					<?php echo $actions['save'] ?> Transfer Item
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
			case 'good_receive':
				if( ! class_exists( 'WCWH_GoodReceive_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
				$Inst = new WCWH_GoodReceive_Controller();
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
		
		if( $id )
		{
			$header = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [ 'company'=>1 ] );
			if( $header )
			{
				$metas = get_document_meta( $id );
				$header = $this->combine_meta_data( $header, $metas );
				
				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];

				$args['data']['delivery_doc'] = $header['delivery_doc'];

				$args['NoAddItem'] = true;
			}

			$filters = [ 'doc_id'=>$id ];
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1, 'transact'=>1, 'stocks'=>$this->warehouse['code'] ] );
			
			if( $items )
			{
				$c_items = []; $inventory = [];
		        foreach( $items as $i => $item ) if( $item['parent'] > 0 ) $c_items[] = $item['id'];
		        if( count( $c_items ) > 0 )
		        {
		        	$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
					$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

					$filter = [ 'wh_code'=>$this->warehouse['code'], 'strg_id'=>$storage['id'], 'item_id'=>$c_items ];
					$stocks = apply_filters( 'wcwh_get_stocks', $filter, [], false, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
					if( $stocks )
					foreach( $stocks as $i => $stock )
					{
						$inventory[ $stock['id'] ] = $stock;
					}
		        }

				$details = array();
				foreach( $items as $i => $item )
				{	
					$stk = ( $inventory[ $item['id'] ] )? $inventory[ $item['id'] ]['qty'] : $item['stock_qty'];
		        	$stk-= ( $inventory[ $item['id'] ] )? $inventory[ $item['id'] ]['allocated_qty'] : $item['stock_allocated'];

					$details[] = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'],
							'stocks' => $stk,
						],
						'bqty' => ( $item['tran_bqty'] - $item['deduct_qty'] ),
						'bunit' => ( $item['tran_bunit'] - $item['deduct_unit'] ),
						'uprice' => $item['unit_price'],
						'total_amount' => $item['total_price'],
						'ref_bqty' => $item['tran_bqty'],
						'ref_bal' => ( $item['tran_bqty'] - $item['deduct_qty'] ),
						'ref_doc_id' => $item['doc_id'],
						'ref_item_id' => $item['item_id'],
					);
				}
				$args['data']['details'] = $details;
			}
		}

		if( $args['data']['ref_doc_id'] )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/transferItem-form.php', $args );
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
				//$args['NoAddItem'] = true;

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_transfer_item'] ) )
				{
					$hides = [ 'uprice', 'total_amount' ];
				}
		        	
		        if( $datas['details'] )
		        {
		        	$c_items = []; $inventory = [];
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$item = $this->combine_meta_data( $item, $detail_metas );

		        		if( $item['parent'] > 0 ) $c_items[] = $item['product_id'];
		        		if( $item['ref_product_id'] > 0 ) $c_items[] = $item['ref_product_id'];
		        		$c_items = array_unique( $c_items );
		        	} 
		        	if( count( $c_items ) > 0 )
		        	{
		        		$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
						$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

						$filter = [ 'wh_code'=>$this->warehouse['code'], 'strg_id'=>$storage['id'], 'item_id'=>$c_items ];
						$stocks = apply_filters( 'wcwh_get_stocks', $filter, [], false, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
						if( $stocks )
						foreach( $stocks as $i => $stock )
						{
							$inventory[ $stock['id'] ] = $stock;
						}
		        	}

		        	$total_amount = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";

		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['to_product_id'] = $datas['details'][$i]['product_id'];
		        		$datas['details'][$i]['product_id'] = $pdt = $datas['details'][$i]['ref_product_id'];

		        		$filter = [ 'id'=>$datas['details'][$i]['product_id'] ];
		        		if( $this->view_outlet && $this->warehouse['id'] ) $filter['seller'] = $this->warehouse['id'];
		        		$prdt = apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'isUnit'=>1 ] );

		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        		
		        		$stk = ( $inventory[ $pdt ] )? $inventory[ $pdt ]['qty'] : $item['stock_qty'];
		        		$stk-= ( $inventory[ $pdt ] )? $inventory[ $pdt ]['allocated_qty'] : $item['stock_allocated'];
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'],
							'stocks' => $stk,
		        		];

		        		if( $prdt )
		        		{
		        			$datas['details'][$i]['to_prdt_name'] = $datas['details'][$i]['prdt_name'];
		        			$datas['details'][$i]['to_uom_code'] = $datas['details'][$i]['uom_code'];

		        			$datas['details'][$i]['prdt_name'] = $prdt['code'].' - '.$prdt['name'];
		        			$datas['details'][$i]['uom_code'] = $prdt['_uom_code'];

		        			$stk = ( $inventory[ $pdt ] )? $inventory[ $pdt ]['qty'] : $item['stock_qty'];
		        			$stk-= ( $inventory[ $pdt ] )? $inventory[ $pdt ]['allocated_qty'] : $item['stock_allocated'];
		        			$datas['details'][$i]['line_item'] = [ 
			        			'name'=>$prdt['name'], 'code'=>$prdt['code'], 'uom_code'=>$prdt['_uom_code'], 
								'uom_fraction'=>$prdt['uom_fraction'], 'required_unit'=>$prdt['required_unit'],
								'stocks' => $stk,
			        		];
		        		}

		        		if( $hasRef ) $datas['details'][$i]['ref_bal'] = $item['ref_tran_bqty']? $item['ref_tran_bqty'] - $item['ref_deduct_qty'] : 0;
		        		else $datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;

						$datas['details'][$i]['fbqty'] = ( $datas['details'][$i]['fbqty'] > 0 )? $datas['details'][$i]['fbqty'] : $datas['details'][$i]['bqty'];
						$datas['details'][$i]['fbqty'] = round_to( $datas['details'][$i]['fbqty'], 2 );
		        		$datas['details'][$i]['bqty'] = round_to( $datas['details'][$i]['bqty'], 2 );
		        		$datas['details'][$i]['bunit'] = round_to( $datas['details'][$i]['bunit'], 3, true );

		        		$datas['details'][$i]['uprice'] = round_to( $datas['details'][$i]['uprice'], 5, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['bqty'] * $datas['details'][$i]['uprice'], 2, true );

		        		$total_amount+= $datas['details'][$i]['total_amount'];
		        	}

		        	if( $isView && ! $hides )
		        	{
		        		$final = [];
			        	$final['prdt_name'] = '<strong>TOTAL:</strong>';
			        	$final['total_amount'] = '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
			        	$datas['details'][] = $final;
		        	}
		        }
		        
		        $args['data'] = $datas;
				unset( $args['new'] );

		        $args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'prdt_name' => 'From Item',
		        		'uom_code' => 'From UOM',
		        		'fbqty' => 'From Qty',
		        		'to_prdt_name' => 'To Item',
		        		'to_uom_code' => 'To UOM',
		        		'bqty' => 'To Qty',
		        		'bunit' => 'Metric (kg/l)',
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

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/transferItem-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/transferItem-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/transferItem-row.php', $this->tplName['row'] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/transferItemListing.php" ); 
			$Inst = new WCWH_TransferItem_Listing();
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

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function view_ref_gr_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>_ref-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_by_gr_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/transferItemGRListing.php" ); 
			$Inst = new WCWH_TransferItemGR_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = false;

			$filters['doc_type'] = 'good_receive';
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff( ['doc_type'] );

			if( empty( $filters['warehouse_id'] ) )
			{
				$filters['warehouse_id'] = $this->warehouse['code'];
			}
			
			$metas = [ 'remark', 'delivery_doc', 'invoice', 'ref_doc_id', 'ref_doc', 'ref_doc_type', 'purchase_doc', 'supplier_company_code' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_avaliable_gr( $filters, $order, false, [ 'meta'=>$metas, 'posting'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}