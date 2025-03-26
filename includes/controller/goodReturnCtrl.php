<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_GoodReturn_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-return.php" ); 

if ( !class_exists( "WCWH_GoodReturn_Controller" ) ) 
{

class WCWH_GoodReturn_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_good_return";

	public $Notices;
	public $className = "GoodReturn_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newGR',
		'row' => 'rowGR',
		'rtn' => 'printRTN',
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
		$this->Logic = new WCWH_GoodReturn_Class( $this->db_wpdb );
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
						foreach( $datas['detail'] as $i => $row )
						{
							if( isset( $datas['header']['has_tree'] ) && $datas['header']['has_tree'] )
							{
								if( isset( $row['to_product_id'] ) && $row['to_product_id'] > 0 && $row['to_product_id'] != $row['product_id'] )
								{
									$ref_base_qty = apply_filters( 'wcwh_item_uom_conversion', $row['product_id'], $row['ref_bal'] );
									$to_base_qty = apply_filters( 'wcwh_item_uom_conversion', $row['to_product_id'], $row['bqty'] );

									if( $to_base_qty > $ref_base_qty )
									{
										$succ = false;
										$this->Notices->set_notice( 'Item Qty could not exceed reference.', 'warning' );
									}
								}
								else
								{
									if( isset( $row['ref_item_id'] ) && $row['ref_item_id'] > 0 && isset( $row['ref_bal'] ) && $row['bqty'] > $row['ref_bal'] )
									{
										$succ = false;
										$this->Notices->set_notice( 'Item Qty could not exceed reference.', 'warning' );
									}
								}
							}
							else
							{
								if( isset( $row['ref_item_id'] ) && $row['ref_item_id'] > 0 && isset( $row['ref_bal'] ) && $row['bqty'] > $row['ref_bal'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'Item Qty could not exceed reference.', 'warning' );
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
				case 'print':
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

							if( in_array( $ref_header['doc_type'], ['good_receive'] ) )
							{
								$header['delivery_warehouse_id'] = get_document_meta( $ref_header['doc_id'], 'delivery_warehouse_id', 0, true );
							}
							if( in_array( $ref_header['doc_type'], ['delivery_order'] ) )
							{
								$header['delivery_warehouse_id'] = $ref_header['warehouse_id'];
							}
						}
					}

					if( $header['delivery_warehouse_id'] )
						$header['supplier_warehouse_code'] = $header['delivery_warehouse_id'];
					else
					{
						if( $header['supplier_company_code'] )
						{
							$supplier_wh = apply_filters( 'wcwh_get_warehouse', [ 'supplier_company_code'=>$header['supplier_company_code'] ], [], true, 
								[ 'meta'=>[ 'supplier_company_code' ], 'meta_like'=>[ 'supplier_company_code'=>1 ]
							] );
							if( $supplier_wh )
								$header['supplier_warehouse_code'] = $supplier_wh['code'];
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
							if( $this->setting[ $this->section_id ]['strict_unpost'] )
							{
								//check need handshake
								$sync_seller = get_document_meta( $id, 'supplier_warehouse_code', 0, true );
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
										$remote = apply_filters( 'wcwh_api_request', 'unpost_good_return', $id, $sync_seller, $this->section_id );
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
										$this->Notices->set_notice( 'Document synced, please double check on receiver side.', 'error' );
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
					if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

					$id = $datas['id'];
					switch( strtolower( $datas['type'] ) )
					{
						case 'good_return':
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
									'D.N. No.' => $doc['docno'],
									'G.R. No.' => $doc['ref_doc'],
									'D.O. No.' => $doc['delivery_doc'],
									'Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
								];

								$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ 'company'=>1 ] );
								if( $warehouse ) $params['heading']['sign_holder'] = $warehouse['comp_name'];

								$addr_format = "{company}\n{address_1}\n\n{phone}";

								$shipping = apply_filters( 'wcwh_get_supplier', [ 'code'=>$doc['supplier_company_code'] ], [], true, [ 'address'=>'default' ] );
								if( $shipping )
								{
									$params['heading']['first_addr'] = apply_filters( 'wcwh_get_formatted_address', [
										'first_name' => $shipping['contact_person'],
										'company'    => $shipping['name'],
										'address_1'  => $shipping['address_1'],
										'city'       => $shipping['city'],
										'state'      => $shipping['state'],
										'postcode'   => $shipping['postcode'],
										'country'    => $shipping['country'],
										'phone'		 => $shipping['contact_no'],
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
							        	if( $datas['view_type'] == 'category' ) $row['item'] = $item['cat_code'].' - '.$item['cat_name'];
							        	$row['uom'] = $item['uom_code'];
							        	$row['qty'] = round_to( $item['bqty'], 2 );

							        	$detail[] = $row;
							        }

							        $params['detail'] = $detail;
							    }
							}

							switch( strtolower( $datas['paper_size'] ) )
							{
								case 'receipt':
									$params['print'] = 1;
									ob_start();
										do_action( 'wcwh_get_template', 'template/receipt-delivery-note.php', $params );
									$content.= ob_get_clean();

									echo $content;
								break;
								case 'default':
								default:
									ob_start();
										do_action( 'wcwh_get_template', 'template/doc-delivery-note.php', $params );
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

			$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false, [ 'meta'=>[ 'supplier_warehouse_code' ] ] );
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

				$wh = $handled[ $ref_id ]['supplier_warehouse_code'];
				if( $handled[ $ref_id ]['status'] >= 6 && $wh )
				{
					$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $wh );
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
			'supplier_warehouse_code', 'delivery_doc', 'remark' 
		];

		$default_column['detail'] = [ 'item_id', 'strg_id', 'product_id', 'uom_id', 'bqty', 'uqty', 'bunit', 'uunit', 'ref_doc_id', 'ref_item_id', 'dstatus', 'unit_cost', 'total_cost' ];

		$default_column['title'] = array_merge( $default_column['header'], $default_column['detail'] );
		$default_column['default'] = array_merge( $default_column['header'], $default_column['detail'] );

		$default_column['unchange'] = [ 'doc_id', 'item_id', 'strg_id' ];

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
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'] );
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'invoice', 'supplier_name', 'remark' ], 'New Goods Return by Document (GR Required)' );
	        echo '<div id="good_return_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'good_return_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#good_return_action"'], 'class'=>['select2','triggerChange'], 
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
				<button id="good_return_action"  class="display-none btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Goods Return"
					data-title="<?php echo $actions['save'] ?> Goods Return" 
					data-action="good_return_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#good_return_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> Goods Return
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'save_item':
				if( current_user_cans( [ 'save_item_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="good_return_item_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> Goods Return"
					data-title="<?php echo $actions['save'] ?> Goods Return" 
					data-action="good_return_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Goods Return
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
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
			case 'delivery_order':
				if( ! class_exists( 'WCWH_DeliveryOrder_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
				$Inst = new WCWH_DeliveryOrder_Controller();
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
			$header = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>[ 'good_receive', 'delivery_order' ] ], [], true, [ 'company'=>1 ] );
			
			if( $header )
			{
				$metas = get_document_meta( $id );
				$header = $this->combine_meta_data( $header, $metas );
				
				$args['data']['ref_doc_date'] = $header['doc_date'];
				$args['data']['ref_doc_id'] = $header['doc_id'];
				$args['data']['ref_doc'] = $header['docno'];
				$args['data']['ref_warehouse'] = $header['warehouse_id'];
				$args['data']['ref_doc_type'] = $header['doc_type'];
				
				if( in_array( $header['doc_type'], [ 'good_receive' ] ) )
				{
					$args['data']['invoice'] = $header['invoice'];
					$args['data']['delivery_doc'] = $header['delivery_doc'];
					$args['data']['supplier_company_code'] = get_document_meta( $id, 'supplier_company_code', 0, true );
				}
				else if( in_array( $header['doc_type'], [ 'delivery_order' ] ) )
				{
					$args['data']['delivery_doc'] = $header['docno'];
					//$args['data']['supplier_company_code'] = get_document_meta( $id, 'supplier_company_code', 0, true );
				}

				$args['NoAddItem'] = true;
			}
			
			$filters = [ 'doc_id'=>$id ];
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1, 'transact'=>1, 'stocks'=>$this->warehouse['code'] ] );
			
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
				$args['data']['details'] = $details;
				if( $has_tree ) $args['data']['has_tree'] = 1;
			}
		}

		if( $args['data']['ref_doc_id'] )
			$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/goodReturn-form.php', $args );
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
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1, 'transact'=>1, 'stocks'=>$this->warehouse['code'] ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1, 'transact'=>1, 'stocks'=>$this->warehouse['code'] ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				$args['NoAddItem'] = true;
				
				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_good_return'] ) )
				{
					$hides = [ 'ucost', 'total_cost', 'sprice', 'total_amount', 'total_profit' ];
				}
		        	
		        if( $datas['details'] )
		        {
		        	$total_cost = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";

		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$stk = ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['qty'] : $item['stock_qty'];
		        		$stk-= ( $inventory[ $item['product_id'] ] )? $inventory[ $item['product_id'] ]['allocated_qty'] : $item['stock_allocated'];
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'],
							'stocks' => $stk,
		        		];

		        		$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;

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

		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2, true );
		        		
		        		$datas['details'][$i]['bunit'] = ( $datas['details'][$i]['bunit'] )? $datas['details'][$i]['bunit'] : $item['tran_bunit'];
		        		$datas['details'][$i]['bunit'] = round_to( $item['tran_bunit'], 3, true );
		        		
		        		$datas['details'][$i]['total_cost'] = round_to( $datas['details'][$i]['weighted_total'], 2, true );
		        		$datas['details'][$i]['ucost'] = round_to( $datas['details'][$i]['weighted_total'] / $datas['details'][$i]['bqty'], 5, true );

		        		$total_cost+= $datas['details'][$i]['total_cost'];
		        	}

		        	if( $isView && ! $hides )
		        	{
		        		$final = [];
			        	$final['prdt_name'] = '<strong>TOTAL:</strong>';
			        	$final['total_cost'] = '<strong>'.round_to( $total_cost, 2, true ).'</strong>';
			        	$datas['details'][] = $final;
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );

				$cols = [
		        	'num' => '',
		        	'prdt_name' => 'Item',
		        	'uom_code' => 'UOM',
		        	'bqty' => 'Qty',
		        	'bunit' => 'Metric (kg/l)',
		        	'ucost' => 'Cost',
		        	'total_cost' => 'Total Cost',
		        ];

				if( $datas['has_tree'] )
				{
					$cols = [
		        		'num' => '',
		        		'prdt_name' => 'Ref Item',
		        		'uom_code' => 'Ref UOM',
		        		'ref_bqty' => 'Ref Qty',
		        		'to_prdt_name' => 'Return Item',
		        		'to_uom_code' => 'Return UOM',
		        		'bqty' => 'Qty',
		        		'bunit' => 'Metric (kg/l)',
		        		'ucost' => 'Cost',
		        		'total_cost' => 'Total Cost',
		        	];
				}
		        
		        $args['render'] = $Inst->get_listing( 
		        	$cols, 
		        	$datas['details'], 
		        	[], 
		        	$hides, 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/goodReturn-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( isset( $config['ref'] ) && $config['ref'] )
			{
				if( !empty( $args['data']['ref_doc_id'] ) )
				{
					$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );
				}
				else if( empty( $args['data']['ref_doc_id'] ) && !empty( $args['data']['delivery_doc'] ) )
				{
					$params = [ 
						'docno' => $args['data']['delivery_doc'], 
						'warehouse_id' => $args['data']['supplier_warehouse_code'], 
						'doc_type' => 'delivery_order',
					];
					$ref_do = $this->Logic->get_header( $params, [], true, [] );
					if( $ref_do )
					{
						$this->view_reference_doc( $ref_do['doc_id'], $ref_do['docno'], $ref_do['doc_type'] );
					}
				}
			}
			
			do_action( 'wcwh_get_template', 'form/goodReturn-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/goodReturn-row.php', $this->tplName['row'] );
	}

	public function rtn_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['rtn'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
		);

		do_action( 'wcwh_templating', 'form/goodReturn-print-form.php', $this->tplName['rtn'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/goodReturnListing.php" ); 
			$Inst = new WCWH_GoodReturn_Listing();
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

			$metas = [ 'remark', 'delivery_doc', 'invoice', 'ref_doc', 'ref_doc_id', 'ref_doc_type', 'supplier_company_code' ];

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

	public function view_ref_do_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>_ref-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_by_do_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/goodReturnDOListing.php" ); 
			$Inst = new WCWH_GoodReturnDO_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = false;

			$filters['doc_type'] = 'delivery_order';
			$filters['status'] = 6;
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff( ['doc_type'] );

			if( $this->warehouse['parent'] > 0 )
			{
				$prt_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->warehouse['parent'] ], [], true );
				$metas = get_warehouse_meta( $wh['id'] );
				$prt_wh = $this->combine_meta_data( $prt_wh, $metas );
				
				$wh = $this->warehouse;
				$wh['client_company_code'] = is_json( $wh['client_company_code'] )? json_decode( stripslashes( $wh['client_company_code'] ), true ) : $wh['client_company_code'];
				if( !empty( $wh['client_company_code'] ) ) $wh['client_company_code'] = array_filter( $wh['client_company_code'] );

				$filters['client_company_code'] = $wh['client_company_code'];
			}
			
			if( empty( $filters['warehouse_id'] ) )
			{
				$filters['not_warehouse_id'] = $this->warehouse['code'];//!empty( $prt_wh['code'] )? $prt_wh['code'] : '';
			}

			$metas = [ 'remark', 'sales_doc', 'direct_issue', 'supply_to_seller', 'client_company_code' ];

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
			include_once( WCWH_DIR . "/includes/listing/goodReturnGRListing.php" ); 
			$Inst = new WCWH_GoodReturnGR_Listing();
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
			
			$metas = [ 'remark', 'delivery_doc', 'invoice', 'ref_doc_id', 'ref_doc', 'ref_doc_type', 'purchase_doc', 'purchase_request_doc_id', 'supplier_company_code' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas, 'transact_out'=>1, 'posting'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}