<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Reprocess_Class" ) ) include_once( WCWH_DIR . "/includes/classes/reprocess.php" ); 

if ( !class_exists( "WCWH_Reprocess_Controller" ) ) 
{

class WCWH_Reprocess_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_reprocess";

	public $Notices;
	public $className = "Reprocess_Controller";

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

	public function __destruct()
	{
		unset($this->Logic);
		unset($this->Notices);
		unset($this->warehouse);
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
		$this->Logic = new WCWH_Reprocess_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
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
							if( !empty( $row['ref_base'] ) && $row['ref_base'] > 0 )
							{
								$items[ $row['product_id'] ]['ref_bqty'] = $row['ref_bqty'];
								$items[ $row['product_id'] ]['bqty']+= $row['bunit'];
								$items[ $row['product_id'] ]['lqty']+= $row['lqty'];
								$items[ $row['product_id'] ]['wqty']+= $row['wqty'];
							}
						}

						if( $items )
						{
							foreach( $items as $i => $row )
							{
								if( isset( $row['ref_bqty'] ) && $row['bqty'] + $row['lqty'] + $row['wqty'] > $row['ref_bqty'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'Material usage could not exceed material Qty.', 'warning' );
									break;
								}
								if( isset( $row['ref_bqty'] ) && $row['bqty'] + $row['lqty'] + $row['wqty'] < $row['ref_bqty'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'Please use up all materials.', 'warning' );
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
			//$succ = false;
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

							if( ! $header['doc_id'] )
							{
								$header['ref_status'] = $ref_header['status'];
							}

							$header['ref_doc'] = $ref_header['docno'];
						}
					}
					if( $detail )
					{
						//regroup details
						$temp = $detail;
						$detail = [];
						$item_ids = [];
						$pdt_ids = [];
						foreach( $temp as $i => $row )
						{
							if( !empty( $row['row_type'] ) && $row['row_type'] == 'material_usage' )	//material usage
							{
								$detail[ $row['ref_base'] ]['materials'][ $row['ref_item_id'] ] = $row;
								$item_ids[] = $row['ref_item_id'];

								if( $row['lqty'] > 0 ) $detail[ $row['product_id'] ] = $row;
							}
							else 	//outcome
							{
								$detail[ $row['product_id'] ] = $row;
								$pdt_ids[] = $row['product_id'];
							}
						}
						
						$items = apply_filters( 'wcwh_get_item', [ 'id'=>$pdt_ids ], [], false, [ 'isUnit'=>1 ] );
						$end_prdt = [];
						if( $items )
						{
							foreach( $items as $i => $item )
							{
								$end_prdt[ $item['id'] ] = $item;
							}
						} 

						$filters = [ 'item_id'=> array_unique( $item_ids ) ];
						$materials = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'transact'=>1, 'usage'=>1 ] );
						if( $materials )
						{
							$temp = $materials;
							$materials = [];
							foreach( $temp as $i => $material )
							{
								$materials[ $material['item_id'] ] = $material;
							}
						}
						
						foreach( $detail as $i => $row )
						{
							//$detail[$i]['total_unit'] = 0;
							if( $row['materials'] )
							{
								$total_cost = 0; $mat_ids = [];
								foreach( $row['materials'] as $item_id => $material )
								{
									$ref = $materials[ $item_id ];
									$ref_uprice = $ref['weighted_total'] / $ref['bqty'];
									$total_cost+= $cost = round_to( $ref_uprice * $material['bunit'], 2 );

									$detail[$i]['material_uqty_'.$item_id] = $material['bunit'];
									$detail[$i]['material_cost_'.$item_id] = $cost;
									
									$detail[$i]['wastage_qty_'.$item_id] = '';
									$detail[$i]['wastage_cost_'.$item_id] = '';
									if( $material['wqty'] > 0 )
									{
										$detail[$i]['wastage_qty_'.$item_id] = $material['wqty'];
										$detail[$i]['wastage_cost_'.$item_id] = round_to( $ref_uprice * $material['wqty'], 2 );
									}
									
									$detail[$i]['bunit']+= $material['bunit'];
									$mat_ids[] = $material['product_id'];

									$materials[ $item_id ]['uqty']+= $material['bunit'] + $material['lqty'];
								}
								$detail[$i]['material_ids'] = implode( ',', $mat_ids );

								$total_cost+= $row['other_cost'];
								$detail[$i]['total_amount'] = round_to( $total_cost, 2 );
								$detail[$i]['uprice'] = round_to( $total_cost / $row['bqty'], 5 );

								if( $end_prdt )
								{
									$detail[$i]['batch_serial'] = $end_prdt[ $row['product_id'] ]['serial'].get_datime_fragment( $header['doc_date'], 'ymd' );
								}
							}
							else
							{
								$ref = $materials[ $row['ref_item_id'] ];

								$detail[$i]['bqty'] = $row['lqty'];
								$detail[$i]['total_amount'] = round_to( $ref['weighted_price'] * $row['lqty'], 2 );
								$detail[$i]['uprice'] = $ref['weighted_price'];
								$detail[$i]['leftover'] = 1;

								unset( $detail[$i]['ref_bqty'] );
								unset( $detail[$i]['ref_base'] );
								unset( $detail[$i]['bunit'] );
								unset( $detail[$i]['lqty'] );
								unset( $detail[$i]['wqty'] );

								$detail[$i]['batch_serial'] = $ref['prdt_serial'].get_datime_fragment( $header['doc_date'], 'ymd' );
							}

							unset( $detail[$i]['materials'] );
						}
						//pd($detail,1);
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
							$result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							if( $succ && in_array( $action, [ 'post', 'unpost' ] ) )
							{
								$succ = $this->direct_block_handler( $result['id'], $action );
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

	public function direct_block_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		if ( !class_exists( "WCWH_BlockStock_Class" ) ) include_once( WCWH_DIR . "/includes/classes/block-stock.php" ); 
		$Inst = new WCWH_BlockStock_Class( $this->db_wpdb );
		$Inst->setUpdateUqtyFlag( false );

		$succ = false;
		$bs_id = 0;

		switch( $action )
		{
			case 'post':
				$doc_metas = $this->Logic->find_document_metas( $doc_id, 'wastage_qty_' );
				if( sizeof( $doc_metas ) <= 0 ) return true;

				$waste_keys = [];
				if( $doc_metas )
				{
					foreach( $doc_metas as $metas )
					{
						$row = [
							'key' => $metas['meta_key'],
							'ref_id' => str_replace( 'wastage_qty_', '', $metas['meta_key'] ),
						];
						$waste_keys[ $metas['item_id'] ] = $row;
					}
				}

				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'reprocess' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					$header = [
						'warehouse_id' => $this->warehouse['code'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'parent' => $doc_header['doc_id'],
						'ref_doc_type' => $doc_header['doc_type'],
						'ref_doc_id' => $doc_header['doc_id'],
						'ref_doc' => $doc_header['docno'],
						'ref_warehouse' => $doc_header['warehouse_id'],
						'remark' => 'Spoilt or wastage of reprocess '.$doc_header['docno'],
					];
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							if( $row[ $waste_keys[ $row['item_id'] ]['key'] ] > 0 )
							{
								$ref_id = $waste_keys[ $row['item_id'] ]['ref_id'];
								$material = $this->Logic->get_detail( [ 'item_id'=>$ref_id ], [], true, [ 'usage'=>1 ] );
								if( $material )
								{
									$cost = $row[ 'wastage_cost_'.$ref_id ];
									$uprice = round_to( $cost / $row[ $waste_keys[ $row['item_id'] ]['key'] ], 5 );
									$detail[] = [
										'product_id' => $material['product_id'],
										'bqty' => $row[ 'wastage_qty_'.$ref_id ],
										'bunit' => 0,
										'uprice' => $uprice,
										'total_amount' => $cost,
										'item_id' => '',
										'ref_doc_id' => $material['doc_id'],
										'ref_item_id' => $material['item_id'],
									];
								}
							}
						}
					}

					if( $header && $detail )
					{
						$result = $Inst->child_action_handle( 'save', $header, $detail );
		                if( ! $result['succ'] )
		                {
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$succ = true;

		                	$bs_id = $result['id'];
		                	$header = [ 'doc_id'=>$result['id'] ];
		                    $result = $Inst->child_action_handle( 'post', $header, $detail );
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

				if( $succ )
				{
					$succ = $this->direct_block_action_handler( $bs_id, $action );
				}
			break;
			case 'unpost':
				$block_header = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'block_stock' ], [], true, [] );
				if( $block_header )
				{
					$bs_id = $block_header['doc_id'];
					$succ = $this->direct_block_action_handler( $bs_id, $action );

					if( $succ )
					{
						$header = [ 'doc_id'=>$block_header['doc_id'] ];
						$result = $Inst->child_action_handle( 'unpost', $header, [] );
			            if( ! $result['succ'] )
			            {
			                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			            }
			            else
			            {
			                $result = $Inst->child_action_handle( 'delete', $header, [] );
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
			break;
		}

		return $succ;
	}

	public function direct_block_action_handler( $doc_id = 0, $action = '' )
	{
		if( ! $doc_id || ! $action ) return false;

		if ( !class_exists( "WCWH_BlockAction_Class" ) ) include_once( WCWH_DIR . "/includes/classes/block-action.php" ); 
		$Inst = new WCWH_BlockAction_Class( $this->db_wpdb );

		$succ = false;

		switch( $action )
		{
			case 'post':
				$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'block_stock' ], [], true, [] );
				if( $doc_header )
				{
					$metas = get_document_meta( $doc_id );
					$doc_header = $this->combine_meta_data( $doc_header, $metas );

					$header = [
						'warehouse_id' => $this->warehouse['code'],
						'doc_date' => $doc_header['doc_date'],
						'post_date' => $doc_header['post_date'],
						'parent' => $doc_header['doc_id'],
						'ref_doc_type' => $doc_header['doc_type'],
						'ref_doc_id' => $doc_header['doc_id'],
						'ref_doc' => $doc_header['docno'],
						'ref_warehouse' => $doc_header['warehouse_id'],
						'remark' => $doc_header['remark'],
						'block_action_type' => 'dispose',
						'block_reason' => 'write-off',
					];
					
					$doc_detail = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1 ] );
					if( $doc_detail )
					{
						$detail = [];
						foreach( $doc_detail as $i => $row )
						{
							$metas = get_document_meta( $doc_id, '', $row['item_id'] );
							$row = $this->combine_meta_data( $row, $metas );

							$cost = $row[ 'wastage_cost_'.$ref_id ];
							$uprice = round_to( $cost / $row[ $waste_keys[ $row['item_id'] ]['key'] ], 5 );
							$detail[] = [
								'product_id' => $row['product_id'],
								'bqty' => $row['bqty'],
								'bunit' => $row['bunit'],
								'item_id' => '',
								'ref_doc_id' => $row['doc_id'],
								'ref_item_id' => $row['item_id'],
							];
						}
					}

					if( $header && $detail )
					{
						$result = $Inst->child_action_handle( 'save', $header, $detail );
		                if( ! $result['succ'] )
		                {
		                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
		                }
		                else
		                {
		                	$succ = true;
		                	$header = [ 'doc_id'=>$result['id'] ];
		                    $result = $Inst->child_action_handle( 'post', $header, $detail );
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
			break;
			case 'unpost':
				$block_header = $this->Logic->get_header( [ 'parent'=>$doc_id, 'doc_type'=>'block_action' ], [], true, [] );
				if( $block_header )
				{
					$header = [ 'doc_id'=>$block_header['doc_id'] ];
					$result = $Inst->child_action_handle( 'unpost', $header, [] );
	                if( ! $result['succ'] )
	                {
	                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
	                }
	                else
	                {
	                    $result = $Inst->child_action_handle( 'delete', $header, [] );
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
				else
					$succ = true;
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
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'remark' ], 'New Reprocess by Document (GI Required)' );
	        echo '<div id="reprocess_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'reprocess_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#reprocess_action"'], 'class'=>['select2','triggerChange'], 
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
				<button id="reprocess_action" class="display-none btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Reprocess"
					data-title="<?php echo $actions['save'] ?> Reprocess" 
					data-action="reprocess_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#reprocess_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> Reprocess
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
		
		if( $id )
		{
			$header = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [] );
			if( $header )
			{
				$metas = get_document_meta( $id );
				$header = $this->combine_meta_data( $header, $metas );

				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];

				$args['data']['parent'] = $header['doc_id'];
			}

			$filters = [ 'doc_id'=>$id ];
			$materials = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'transact'=>1, 'usage'=>1 ] );
			
			//reference materials
			$mat_ids = []; $material_info = [];
			if( $materials )
			{
				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_reprocess'] ) )
				{
					$hides = [ 'unit_cost', 'total_cost' ];
				}
		        
		        $total_cost = 0;
		        foreach( $materials as $i => $item )
		        {
		        	$materials[$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        	$materials[$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        	$materials[$i]['line_item'] = [ 
		        		'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
						'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        	];

		        	$materials[$i]['bqty'] = round_to( $materials[$i]['bqty'], 2 );
		        	$materials[$i]['unit_cost'] = round_to( $item['weighted_price'], 5 );
		        	$materials[$i]['total_cost'] = round_to( $item['weighted_total'], 2 );

		        	$total_cost+= $item['weighted_total'];

		        	$mat_ids[] = $item['product_id'];
		        	$material_info[ $item['product_id'] ] = $item;
		        }

		        if( ! $hides )
		        {
		        	$final = [];
				    $final['prdt_name'] = '<strong>TOTAL:</strong>';
				    $final['total_cost'] = '<strong>'.round_to( $total_cost, 2, true ).'</strong>';
				    $materials[] = $final;
		        }

				$args['references'] = $Inst->get_listing( [
						'num' => '',
		        		'prdt_name' => 'Item',
		        		'uom_code' => 'UOM',
		        		'bqty' => 'Qty',
		        		'unit_cost' => 'Cost',
			        	'total_cost' => 'Total Cost',
		        	], 
		        	$materials, 
		        	[], 
		        	$hides,
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}

			//outcomes items
			$reprocess = apply_filters( 'wcwh_get_reprocess_item', [ 'material'=>$mat_ids ], [], false, [ 'usage'=>1, 'uom'=>1 ] );
			
			if( $reprocess )
			{
				$reprocess_items = []; $materials = [];
                foreach( $reprocess as $i => $item )
                {
                    $materials[ $item['items_id'] ][ $item['mat_id'] ] = [
                    	'id' => $item['mat_id'],
                        'prdt_name' => $item['material'],
                        'prdt_sku' => $item['mat_sku'],
                        'prdt_code' => $item['mat_code'],
                        'prdt_serial' => $item['mat_serial'],
                        'uom_code' => $item['mat_uom'],
                        'uom_fraction' => $item['mat_fraction'],
                    ];

                    $rep_item = [
                        'id' => $item['out_id'],
                        'prdt_name' => $item['outcome'],
                        'prdt_sku' => $item['out_sku'],
                        'prdt_code' => $item['out_code'],
                        'prdt_serial' => $item['out_serial'],
                        'uom_code' => $item['out_uom'],
                        'uom_fraction' => $item['out_fraction'],
                        'materials' => $materials[ $item['items_id'] ],
                    ];
                    $reprocess_items[ $item['items_id'] ] = $rep_item;
                }
                //pd( $reprocess_items );
				
				if( $reprocess_items )
				{
					$details = array();
					$num = 0;
					foreach( $reprocess_items as $i => $item )
					{	
						$num++;

						$details[] = array(
							'index' => '<strong>'.$num.'.</strong>',
							'id' =>  $item['id'],
							'product_id' => $item['id'],
							'item_id' => '',
							'line_item' => [ 'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
								'uom_fraction' => $item['uom_fraction'], ],
							'type' => 'outcome',
							'rowspan' => count( $item['materials'] )+1,
						);

						if( $item['materials'] )
						{
							$n = 0;
							foreach( $item['materials'] as $j => $mat )
							{
								$n++;
								$details[] = array(
									'index' => ' &nbsp;'.$num.'.'.$n.'.',
									'id' =>  $mat['id'],
									'product_id' => $mat['id'],
									'item_id' => '',
									'line_item' => [ 'name'=>$mat['prdt_name'], 'code'=>$mat['prdt_code'], 'uom_code'=>$mat['uom_code'], 
										'uom_fraction' => $mat['uom_fraction'], ],
									'type' => 'material',
									'ref_base' => $item['id'],
									'ref_bqty' => $material_info[ $mat['id'] ]['bqty'],
									'ref_doc_id' => $material_info[ $mat['id'] ]['doc_id'],
									'ref_item_id' => $material_info[ $mat['id'] ]['item_id'],
									'bunit' => $material_info[ $mat['id'] ]['bqty'],
								);
							}
						}
					}
					$args['data']['details'] = $details;
				}
			}
		}

		if( $args['data']['ref_doc_id'] )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/reprocess-form.php', $args );
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

				$filters = [ 'doc_id'=>$datas['parent'] ];
				$materials = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'transact'=>1, 'usage'=>1 ] );
				
				$material_info = [];
				if( $materials )
				{
					$Inst = new WCWH_Listing();

					$hides = [];
					if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_reprocess'] ) )
					{
						$hides = [ 'unit_cost', 'total_cost' ];
					}

			        $total_cost = 0;
			        foreach( $materials as $i => $item )
			        {
			        	$materials[$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
			        	$materials[$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
			        	$materials[$i]['line_item'] = [ 
			        		'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
			        	];

			        	$materials[$i]['bqty'] = round_to( $materials[$i]['bqty'], 2, true );
			        	$materials[$i]['unit_cost'] = round_to( $item['weighted_price'], 5 );
		        		$materials[$i]['total_cost'] = round_to( $item['weighted_total'], 2 );

			        	$total_cost+= $item['weighted_total'];

			        	$mat_ids[] = $item['product_id'];
			        	$material_info[ $item['product_id'] ] = $item;
			        }

			        if( ! $hides )
			        {
			        	$final = [];
					    $final['prdt_name'] = '<strong>TOTAL:</strong>';
					    $final['total_cost'] = '<strong>'.round_to( $total_cost, 2, true ).'</strong>';
					    $materials[] = $final;
			        }

					$args['references'] = $Inst->get_listing( [
							'num' => '',
			        		'prdt_name' => 'Item',
			        		'uom_code' => 'UOM',
			        		'bqty' => 'Qty',
			        		'unit_cost' => 'Cost',
				        	'total_cost' => 'Total Cost',
			        		//'uqty' => 'Used Qty',
			        		//'status' => 'Status',
			        	], 
			        	$materials, 
			        	[], 
			        	$hides,
			        	[ 'off_footer'=>true, 'list_only'=>true ]
			        );
				}

				//details
				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1 ] );

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

		        	$detail = []; $leftovers = []; 
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$item = $this->combine_meta_data( $item, $detail_metas );
		        		
		        		if( $item['leftover'] )
		        		{
		        			$leftovers[ $item['ref_item_id'] ] = $item;
		        		}
		        		else
		        		{
		        			$detail[ $item['item_id'] ] = $item;
		        		}
		        	}

		        	foreach( $detail as $i => $item )
		        	{
		        		$num++;
		        		$row = $item;
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$row = $this->combine_meta_data( $row, $detail_metas );

		        		$row['index'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".$num.".</span>" : $num.".";
		        		$row['id'] = $item['product_id'];
		        		$row['item_name'] = $item['prdt_name'];
			        	$row['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
			        	$row['line_item'] = [ 
			        		'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction']
			        	];
			        	$row['bqty'] = round_to( $item['bqty'], 2, true );
			        	$row['unit'] = round_to( $item['bunit'], 3, true );
			        	$row['bunit'] = round_to( $materials[$i]['bunit'], 3, true );
			        	//$row['serial'] = $this->column_serial( $row, $datas, $Inst );

		        		//materials
		        		$mat_rows = []; $good_rows = []; $waste_rows = [];
		        		if( $row['material_ids'] )
		        		{
		        			$mat_ids = explode( ',', $row['material_ids'] );

		        			$row['type'] = 'outcome';
		        			$row['rowspan'] = count( $mat_ids ) + 1;
		        			
		        			if( $mat_ids )
		        			{	
		        				$n = 0;
		        				foreach( $mat_ids as $mat_id )
		        				{
		        					$n++;
		        					$mat = $material_info[ $mat_id ];
		        					
		        					$mat_row = [
		        						'index' => $num.'.'.$n.'.',
										'id' =>  $mat['product_id'],
										'product_id' => $mat['product_id'],
										'item_id' => '',
										'prdt_name' => '[Material] '.$mat['prdt_code'].' - '.$mat['prdt_name'],
										'line_item' => [ 'name'=>$mat['prdt_name'], 'code'=>$mat['prdt_code'], 'uom_code'=>$mat['uom_code'], 
											'uom_fraction' => $mat['uom_fraction'], 
										],
										'uom_code' => $mat['uom_code'],
										'type' => 'material',
										'bunit' => round_to( $row['material_uqty_'.$mat['item_id']], 3, true ),
										'wqty' => round_to( $row['wastage_qty_'.$mat['item_id']], 3, true ),
										'ref_base' => $item['product_id'],
										'ref_bqty' => $mat['bqty'],
										'ref_doc_id' => $mat['doc_id'],
										'ref_item_id' => $mat['item_id'],
		        					];

		        					$left = $leftovers[ $mat['item_id'] ];
		        					if( $left )
		        					{
		        						$mat_row['lqty'] = $left['bqty'];
		        						$mat_row['item_id'] = $left['item_id'];

		        						$g_row = $mat_row;
		        						$g_row['prdt_name'] = str_replace( 'Material', 'Leftover', $g_row['prdt_name'] );
		        						$g_row['bqty'] = $left['bqty'];
		        						$g_row['bunit'] = $left['bunit'];
		        						$g_row['total_amount'] = $left['total_amount'];
		        						$g_row['uprice'] = $left['uprice'];
		        						$good_rows[] = $g_row;
		        					}

		        					if( $row['wastage_qty_'.$mat['item_id']] > 0 )
		        					{
		        						$w_row = $mat_row;

		        						$w_row['prdt_name'] = str_replace( 'Material', 'Wastage', $w_row['prdt_name'] );
		        						$w_row['bqty'] = $mat_row['wqty'];
		        						$w_row['bunit'] = 0;
		        						$w_row['total_amount'] = round_to( $row['wastage_cost_'.$mat['item_id']], 2, true );
		        						$w_row['uprice'] = round_to( $w_row['total_amount'] / $w_row['bqty'], 5 );
		        						$waste_rows[] = $w_row;
		        					}

		        					$mat_rows[] = $mat_row;
		        				}
		        			}
		        		}

		        		$row['uprice'] = round_to( $row['uprice'], 5, true );
			        	$row['total_amount'] = round_to( $row['total_amount'], 2, true );
		        		$total_amount+= $row['total_amount'];

		        		$details[] = $row;
		        		foreach( $mat_rows as $mat_row )
			        	{
			        		$details[] = $mat_row;
			        	}

			        	if( $isView )
			        	{
			        		if( $good_rows )
			        		foreach( $good_rows as $good_row )
				        	{
				        		$details[] = $good_row;

				        		$total_amount+= $good_row['total_amount'];
				        	}	

				        	if( $waste_rows )
				        	foreach( $waste_rows as $waste_row )
				        	{
				        		$details[] = $waste_row;

				        		$total_amount+= $waste_row['total_amount'];
				        	}
			        	}
		        	}

		        	if( $isView )
		        	{
		        		$final = [];
			        	$final['prdt_name'] = '<strong>TOTAL:</strong>';
			        	$final['total_amount'] = '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
			        	$details[] = $final;
		        	}
		        }
		        $datas['details'] = $details;

		        $args['data'] = $datas;
				unset( $args['new'] );
		        
		        $args['render'] = $Inst->get_listing( [
		        		'index' => '',
		        		'prdt_name' => 'Item',
		        		'uom_code' => 'UOM',
		        		'bqty' => 'Qty',
		        		'unit' => 'Metric (kg/l)',
		        		'bunit' => 'Usage',
		        		'uprice' => 'Price',
		        		'total_amount' => 'Total Amt',
		        		'dremark' => 'Remark',
		        		//'uqty' => 'Used Qty',
		        		//'status' => 'Status',
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
			do_action( 'wcwh_templating', 'form/reprocess-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/reprocess-form.php', $args );
		}
	}

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
		do_action( 'wcwh_templating', 'segment/reprocess-row.php', $this->tplName['row'] );
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
			include_once( WCWH_DIR . "/includes/listing/reprocessListing.php" ); 
			$Inst = new WCWH_Reprocess_Listing();
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

			$metas = [ 'remark', 'ref_doc' ];

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