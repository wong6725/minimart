<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ClosingPR_Class" ) ) include_once( WCWH_DIR . "/includes/classes/closingPR.php" ); 

if ( !class_exists( "WCWH_ClosingPR_Controller" ) ) 
{

class WCWH_ClosingPR_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_closing_pr";

	public $Notices;
	public $className = "WCWH_ClosingPR_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newPR',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ ];

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
		$this->Logic = new WCWH_ClosingPR_Class( $this->db_wpdb );
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
				case "post":
				case "unpost":
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

							/*if( in_array( $ref_header['doc_type'], [ 'purchase_request', 'purchase_order' ] ) )
							{
								$header['purchase_warehouse_id'] = $header['ref_warehouse'];
							}*/
						}
					}

					if( $detail )
					{
						foreach( $detail as $i => $row )
						{
							if( $row['closing'] )
							{
								$detail[$i]['bqty'] = $detail[$i]['ref_pr_balance'];
								$detail[$i]['closed_item_row'] = 1;
								unset($detail[$i]['closing']); 
							}
							else
							{
								unset( $detail[$i] );
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
							if( $this->setting['wh_purchase_request']['strict_unpost'] && ! $this->skip_strict_unpost )
							{
								//check need handshake
								$ref_doc_id = get_document_meta( $id, 'ref_doc_id', 0, true );
								if( $ref_doc_id ) $sync_seller = get_document_meta( $ref_doc_id, 'client_company_code', 0, true );
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
										$remote = apply_filters( 'wcwh_api_request', 'unpost_close_purchase_request', $id, '', $this->section_id );
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


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = [];

		$default_column['header'] = [ 'doc_id', 'warehouse_id', 'docno', 'sdocno', 'doc_date', 'post_date', 'doc_type', 'hstatus', 'hflag', 'parent', 
			'purchase_doc', 'remark'
		];

		$default_column['detail'] = [ 'item_id', 'strg_id', 'product_id', 'uom_id', 'bqty', 'uqty', 'bunit', 'uunit', 'ref_doc_id', 'ref_item_id', 'dstatus'
			, '_item_number'
		];

		$default_column['title'] = array_merge( $default_column['header'], $default_column['detail'] );
		$default_column['default'] = array_merge( $default_column['header'], $default_column['detail'] );

		$default_column['unchange'] = [ 'doc_id', 'item_id' ];

		return $default_column;
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
		//update_option('testing',$datas);
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
				$details = [];

				if($header['ref_doc'])
				{
					$parent = $this->Logic->get_header( [ 'docno'=>$header['ref_doc'], 'doc_type'=>'purchase_request' ], [], true, [] );
					if($parent) $header['parent'] = $parent['doc_id'];
				}
				$ref_items = [];
				if( $header['purchase_doc'] )
				{
					$ref_doc = $this->Logic->get_header( [ 'docno'=>$header['purchase_doc'], 'warehouse_id'=>$header['warehouse_id'], 'doc_type'=>'purchase_request' ], [], true, [ 'usage'=>1 ] );
					if( $ref_doc )
					{
						$header['ref_doc_id'] = $ref_doc['doc_id'];
						$header['ref_doc_type'] = $ref_doc['doc_type'];
						$header['ref_doc'] = $ref_doc['docno'];
						$header['parent'] = $ref_doc['doc_id'];

						$ref_detail = $this->Logic->get_detail( [ 'doc_id'=>$ref_doc['doc_id'] ], [], false, [ 'usage'=>1 ] );
						if( $ref_detail )
						{
							foreach( $ref_detail as $j => $det )
							{
								$ref_items[ $det['product_id'] ] = $det;
							}
						}
					}
				}
				
				foreach( $data['detail'] as $i => $row )
				{
					$found = apply_filters( 'wcwh_get_item', [ 'serial'=>$row['product_id'] ], [], true, [] );
					if( $found )
					{
						$details[ $row['_item_number'] ] = $row;
						$details[ $row['_item_number'] ]['product_id'] = $found['id'];
						//$details[$i]['name'] = $found['name'];
						//$details[$i]['code'] = $found['code'];

						if( $ref_items && !empty( $ref_items[ $found['id'] ] ) )
						{
							$details[ $row['_item_number'] ]['ref_doc_id'] = $ref_items[ $found['id'] ]['doc_id'];
							$details[ $row['_item_number'] ]['ref_item_id'] = $ref_items[ $found['id'] ]['item_id'];
						}
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
					else
					{
						$header_item = $this->Logic->getHeaderItem();
						$detail_items = $this->Logic->getDetailItem();
						
						if( $succ )
						{
							$doc_id = $header_item['doc_id'];
						}
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
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'] );
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'comp_name', 'remark' ], 'Closing Purchase Request (PR)' );
	        echo '<div id="closing_pr_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'closing_pr_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#closing_pr_action"'], 'class'=>['select2','triggerChange'], 
	                'options'=> $options, 'offClass'=>true
	            ], 
	            ''
	        );
	        echo '</div>';
		}
	}

	public function view_reference_doc( $doc_id = 0, $title = '', $doc_type = 'good_receive' )
	{
		if( ! $doc_id || ! $doc_type ) return;

		switch( $doc_type )
		{
			case 'good_receive':
				if( ! class_exists( 'WCWH_GoodReceive_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
				$Inst = new WCWH_GoodReceive_Controller();
			break;
		}
		
		$ref_segment = [];
		
		if( $Inst )
		{
			$Inst->set_warehouse(  $this->warehouse );

			if(is_array($doc_id))
			{
				foreach($doc_id as $id => $value)
				{
					ob_start();
					$Inst->view_form( $value, false, true, true, [ 'ref'=>1, 'link'=>0 ] );
					$ref_segment[ $title[$id] ] = ob_get_clean();
					$args = [];
					$args[ 'accordions' ] = $ref_segment;
					$args[ 'id' ] = $this->section_id;
				}
				do_action( 'wcwh_get_template', 'segment/accordion.php', $args );
			}
			else
			{
				ob_start();
				$Inst->view_form( $doc_id, false, true, true, [ 'ref'=>1, 'link'=>0 ] );
				$ref_segment[ $title ] = ob_get_clean();
			
				$args = [];
				$args[ 'accordions' ] = $ref_segment;
				$args[ 'id' ] = $this->section_id;

				do_action( 'wcwh_get_template', 'segment/accordion.php', $args );

			}
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
				<button id='closing_pr_action' class="btn btn-sm btn-primary linkAction display-none" title="Closing Purchase Request"
					data-title="Closing Purchase Request (PR)" 
					data-action="closing_pr_reference"
					data-service="<?php echo $this->section_id; ?>_action" 
					data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-modal="wcwhModalForm" 
					data-actions="close|submit"
					data-source="#closing_pr_reference" 
					data-strict="yes"
				>
					Closing Purchase Request
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
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$existing_item = [];
			$gr_docs = [];

			$filters = [ 'doc_id'=>$id, 'doc_type' =>'none' ];
			$pr_header = $this->Logic->get_header( $filters, [], true, [ 'warehouse'=>1, 'company'=>1 ] );

			if($pr_header)
			{
				$args['data']['ref_doc_date'] = $pr_header['doc_date'];
				$args['data']['ref_doc_id'] = $pr_header['doc_id'];
				$args['data']['ref_doc'] = $pr_header['docno'];
				$args['data']['ref_warehouse'] = $pr_header['warehouse_id'];
				$args['data']['ref_doc_type'] = $pr_header['doc_type'];
				$args['data']['purchase_doc'] = $pr_header['docno'];

				/*$existing_used_gr = $this->Logic->get_header( ['parent'=>$id], [], false, [ 'usage'=>1, 'meta'=>['good_receive_id'] ] );
				$exclude_gr_ids = '';
				if($existing_used_gr)
				{
					foreach($existing_used_gr as $i =>$gr)
					{
						if($gr['good_receive_id'])
						{
							if(empty($exclude_gr_ids))
							{
								$exclude_gr_ids = $gr['good_receive_id'];
							}
							else $exclude_gr_ids .= ', '.$gr['good_receive_id'];
						}
					}
				}
				if($exclude_gr_ids) $exclude_gr_ids = explode(", ",$exclude_gr_ids);
				*/

				$gr_headers = $this->Logic->get_goods_receipt_by_pr($id, $exclude_gr_ids);
				if($gr_headers)
				{
					foreach($gr_headers as $g => $gr)
					{
						$gr_docs[] = [
							'doc_id' => $gr['doc_id'],
							'docno' => $gr['docno'],
						];
						$filters = [ 'doc_id'=>$gr['doc_id'] ];
						$gr_items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );

						if($gr_items)
						{
							foreach($gr_items as $i => $item)
							{
								if ($existing_item[$item['product_id']])
									$existing_item[$item['product_id']] += $item['bqty'];
								else
									$existing_item[$item['product_id']] = $item['bqty'];
							}
						}
					}
				}

				$filters = ['doc_id'=>$id];
				$pr_items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );

				if($pr_items)
				{
					$details = array();
					foreach($pr_items as $i => $item)
					{
						$gr_bqty = ($existing_item[$item['product_id']])? round_to( $existing_item[$item['product_id']], 2 ) : 0;
						$ref_pr_bqty = round_to( $item['bqty'], 2 );
                        $ref_pr_balance = round_to( ($item['bqty'] - $item['uqty']), 2 );
                        $bqty = $ref_pr_balance;//($gr_bqty >= $ref_pr_balance)? $ref_pr_balance : $gr_bqty;
                        if( $ref_pr_balance <= 0 ) $closed_item_row = 1;
                        else $closed_item_row = 0;

						$details[$i] = array(
							'id' => '',
							'bqty' => $bqty,
							'gr_bqty' => $gr_bqty,
							'cpr_bqty' => round_to( 0, 2 ),
							'ref_pr_bqty' => $ref_pr_bqty,
							'ref_pr_balance' => $ref_pr_balance,
							'product_id' => $item['product_id'],
							'item_id' => '',
							'ref_item_id' => $item['item_id'],
							'ref_doc_id' => $item['doc_id'],
							'line_item' => [
								'name'=>$item['prdt_name'],
								'code'=>$item['prdt_code'],
								'uom_code'=>$item['uom_code'],
								'uom_fraction'=>$item['uom_fraction'],
								'required_unit'=>$item['required_unit'],
							],
							'closed_item_row' => $closed_item_row,
						);
					}
					$args['data']['details'] = $details;
				}
			}	
		}

		if( $gr_docs )
		{
			foreach( $gr_docs as $gr_doc )
			{
				$this->view_reference_doc( $gr_doc['doc_id'], $gr_doc['docno'] );
			}
		}

		do_action( 'wcwh_get_template', 'form/closingPR-form.php', $args );
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
			'get_content' => $getContent,
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$existing_item = [];
			$gr_docs = [];
			$latest_pr = [];

			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true );
			if( $datas )
			{
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				if( $datas['parent'] )
				{
					$pr_header = $this->Logic->get_header( [ 'doc_id' => $datas['parent'], 'doc_type' =>'none' ], [], true );
					if( $pr_header )
					{
						$gr_headers = $this->Logic->get_goods_receipt_by_pr( $pr_header['doc_id'], $exclude_gr_ids);
						if($gr_headers)
						{
							foreach($gr_headers as $g => $gr)
							{
								$gr_docs[] = [
									'doc_id' => $gr['doc_id'],
									'docno' => $gr['docno'],
								];

								$filters = [ 'doc_id'=>$gr['doc_id'] ];
								$gr_items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );

								if($gr_items)
								{
									foreach($gr_items as $i => $item)
									{
										if ($existing_item[$item['product_id']])
											$existing_item[$item['product_id']] += $item['bqty'];
										else
											$existing_item[$item['product_id']] = $item['bqty'];
									}
								}
							}
						}

						$pr_items = $this->Logic->get_detail( [ 'doc_id' => $pr_header['doc_id'] ], [], false, [ 'uom'=>1, 'usage'=>1] );
						if( $pr_items )
						{
							foreach( $pr_items as $pr => $item )
							{
								$latest_pr[$item['item_id']] = [
									'gr_bqty' => ($existing_item[$item['product_id']])? round_to( $existing_item[$item['product_id']], 2 ) : 0,
									'ref_pr_bqty' => round_to( $item['bqty'], 2 ),
									'ref_pr_balance' => round_to( ($item['bqty'] - $item['uqty']), 2 ),
								];
							}
						}
					}
				}

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1, 'usage'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1 ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();
		        	
		        if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];

		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		if($latest_pr)
		        		{
		        			$datas['details'][$i]['gr_bqty'] = $latest_pr[$item['ref_item_id']]['gr_bqty'];
		        			$datas['details'][$i]['ref_pr_bqty'] = $latest_pr[$item['ref_item_id']]['ref_pr_bqty'];
		        			$datas['details'][$i]['ref_pr_balance'] = round_to( $latest_pr[$item['ref_item_id']]['ref_pr_balance'] + $item['bqty'], 2 );

		        			$datas['details'][$i]['closed_item_row'] = 0;
		        			$datas['details'][$i]['closed'] = 1;
		        		}
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );
				
		        $args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'prdt_name' => 'Item',
		        		'uom_code' => 'UOM',
		        		'ref_pr_bqty' => 'PR Qty',
		        		'gr_bqty' => 'GR Used Qty',
		        		'ref_pr_balance' => 'PR Bal Qty',
		        		'closed' => 'Closing',
		        		//'status' => 'Status',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}
		
		if( $gr_docs )
		{
			foreach( $gr_docs as $gr_doc )
			{
				if( !empty( $gr_doc['doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
					$this->view_reference_doc( $gr_doc['doc_id'], $gr_doc['docno'] );
			}
		}
		/*
		if( $args['data']['good_receive_id'] )
		{
			if(strrchr($args['data']['good_receive_id'], ','))
			{
				$gr_ids = explode(", ",$args['data']['good_receive_id']);
				$gr_docnos = explode(", ",$args['data']['good_receive_doc']);
				$this->view_reference_doc( $gr_ids, $gr_docnos);
			}
			else
				$this->view_reference_doc( $args['data']['good_receive_id'], $args['data']['good_receive_doc']);
		}*/

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/closingPR-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/closingPR-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/closingPRListing.php" ); 
			$Inst = new WCWH_ClosingPR_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$wh_code = ( empty( $filters['warehouse_id'] ) )? $this->warehouse['code'] : $filters['warehouse_id'];
			$count = $this->Logic->count_statuses( $wh_code );
			if( $count ) $Inst->viewStats = $count;

			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 'all';
			
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