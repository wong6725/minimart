<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_BlockStock_Class" ) ) include_once( WCWH_DIR . "/includes/classes/block-stock.php" ); 

if ( !class_exists( "WCWH_BlockStock_Controller" ) ) 
{

class WCWH_BlockStock_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_block_stock";

	public $Notices;
	public $Files;
	public $className = "BlockStock_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newBS',
		'row' => 'rowBS',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1 ];

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		$this->Files = new WCWH_Files();

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
		$this->Logic = new WCWH_BlockStock_Class( $this->db_wpdb );
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
					/*if( ! $_FILES && ! $datas['attachment'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'Attachment is required', 'warning' );
					}*/

					if( ! $datas['detail'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					else
					{
						foreach( $datas['detail'] as $i => $row )
						{
							if( isset( $row['ref_item_id'] ) && $row['ref_item_id'] > 0 && isset( $row['ref_bal'] ) && $row['bqty'] > $row['ref_bal'] )
							{
								$succ = false;
								$this->Notices->set_notice( 'Item Qty could not exceed reference.', 'warning' );
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
					$attachment = $datas['attachment'];
					$files = $_FILES;

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					$ref_header = []; $refDetail = []; $sourceDetail = [];
					if( $header['ref_doc_id'] )
					{	
						//$header['ref_doc'] = $header['delivery_doc'];
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

							if( in_array( $ref_header['doc_type'], [ 'good_return' ] ) && $ref_header['delivery_doc'] )
							{	
								$do = $this->Logic->get_header( [ 
									'docno'=>$ref_header['delivery_doc'], 
									'doc_type'=>'delivery_order',
								], [], true, [ 'posting'=>1 ] );

								if( $do )
								{
									$metas = get_document_meta( $do['doc_id'] );
									$do = $this->combine_meta_data( $do, $metas );

									$header['do_ref_doc_id'] = $do['doc_id'];
									$header['inv_ref_doc_id'] = $do['doc_id'];
									$header['inv_doc_type'] = 'delivery_order';
									
									if( $do['ref_doc_id'] && in_array( $do['ref_doc_type'], [ 'good_issue' ] ) )
									{
										$do = $this->Logic->get_header( [ 
											'doc_id'=>$do['ref_doc_id'], 
											'doc_type'=>'good_issue',
										], [], true, [ 'posting'=>1 ] );

										if( $do )
										{
											//$metas = get_document_meta( $do['doc_id'] );
											//$do = $this->combine_meta_data( $do, $metas );

											$header['inv_ref_doc_id'] = $do['doc_id'];
											$header['inv_doc_type'] = 'good_issue';
										}
									}

									$inv_detail = $this->Logic->get_detail( [ 'doc_id'=>$do['doc_id'] ], [], false, [ 'transact'=>1, 'usage'=>1 ] );
									
									if( $inv_detail )
									{
										foreach( $inv_detail as $i => $row )
										{
											//$metas = get_document_meta( $row['doc_id'], '', $row['item_id'] );
											//$row = $this->combine_meta_data( $row, $metas );
											$ref_base = apply_filters( 'wcwh_item_uom_conversion', $row['product_id'] );
											if( $ref_base && count( $ref_base ) > 1 )
											{
												foreach( $ref_base as $pid => $val )
												{
													$sourceDetail[ $pid ] = $row;
												}
											}
											else
											{
												$sourceDetail[ $row['product_id'] ] = $row;
											}
										}
									}
								}
							}

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
							
							if( $row['ref_item_id'] )
							{
								if( $refDetail[ $row['ref_item_id'] ] )
								{
									$detail[$i]['bunit'] = ( $refDetail[ $row['ref_item_id'] ]['bunit'] > 0 )? $refDetail[ $row['ref_item_id'] ]['bunit'] : $refDetail[ $row['ref_item_id'] ]['tran_bunit'];
									$detail[$i]['bunit'] = ( $detail[$i]['bunit'] )? $detail[$i]['bunit'] : 0;
									$detail[$i]['total_amount'] = $refDetail[ $row['ref_item_id'] ]['weighted_total'] / $refDetail[ $row['ref_item_id'] ]['bqty'] * $detail[$i]['bqty'];
									$detail[$i]['uprice'] = $detail[$i]['total_amount'] / $detail[$i]['bqty'];
								}
							}

							//Link with DO / GI
							if( in_array( $ref_header['doc_type'], [ 'good_return' ] ) )
							{
								if( $sourceDetail && !empty( $sourceDetail[ $row['product_id'] ] ) )
								{
									$doc_row = $sourceDetail[ $row['product_id'] ];
									//$b_qty = apply_filters( 'wcwh_item_uom_conversion', $doc_row['product_id'], $doc_row['bqty'], $row['product_id'] );

									$detail[$i]['uprice'] = ( $doc_row['weighted_price'] > 0 )? $doc_row['weighted_price'] : $detail[$i]['ucost'];
									$detail[$i]['uprice'] = apply_filters( 'wcwh_item_uom_conversion', $doc_row['tran_prdt_id'], $detail[$i]['uprice'], $row['product_id'], 'amt' );

									//added to fix amount not linked with DO amount issue
									$detail[$i]['total_amount'] = $detail[$i]['uprice'] * $detail[$i]['bqty'];
									
									$detail[$i]['inv_ref_item_id'] = $doc_row['item_id'];

									switch( $header['inv_doc_type'] )
									{
										case 'delivery_order':
											$detail[$i]['do_ref_item_id'] = $doc_row['item_id'];
										break;
										case 'good_issue':
											$args = [ 'ref_doc_id'=>$doc_row['doc_id'], 'ref_item_id'=>$doc_row['item_id'] ];
											$do_detail = $this->Logic->get_detail( $args, [], true, [ 'usage'=>1 ] );
											if( $do_detail )
											{
												$detail[$i]['do_ref_item_id'] = $do_detail['item_id'];
											}
										break;
									}
								}
								/*else
								{
									$succ = false;
									$this->Notices->set_notice( 'Unable to match returned item with related delivery document', 'error' );
								}*/
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

							$doc_id = $result['id'];
							if( !empty( $attachment ) )
							{
								$succ = $this->Files->attachment_handler( $attachment, $this->section_id, $doc_id );
							}
							if( !empty( $files ) )
							{
								$fr = $this->Files->upload_files( $files, $this->section_id, $doc_id );
								if( $fr )
								{
									update_document_meta( $doc_id, 'attachments', maybe_serialize( $fr ) );
								}
								else{
									$succ = false;
									$this->Notices->set_notice( 'File Upload Failed', 'error' );
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


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$reference = $this->Logic->get_reference_documents( $this->warehouse['code'] );
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'remark' ], 'New Block Stock by Document (GT/GI Required)' );
	        echo '<div id="block_stock_reference_content" class="col-md-6">';
	        wcwh_form_field( '', 
	            [ 'id'=>'block_stock_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#block_stock_action"'], 'class'=>['select2','triggerChange'], 
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
				<button id="block_stock_action" class="btn btn-sm btn-primary linkAction display-none" title="Add <?php echo $actions['save'] ?> Block Stock"
					data-title="<?php echo $actions['save'] ?> Block Stock" 
					data-action="block_stock_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#block_stock_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> Block Stock
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
			case 'good_return':
				if( ! class_exists( 'WCWH_GoodReturn_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodReturnCtrl.php" ); 
				$Inst = new WCWH_GoodReturn_Controller();
			break;
			case 'reprocess':
				if( ! class_exists( 'WCWH_Reprocess_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/reprocessCtrl.php" ); 
				$Inst = new WCWH_Reprocess_Controller();
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
					case 'block_action':
						if( current_user_cans( [ 'access_wh_block_action' ] ) && empty( $Objs[ $doc_type ] ) )
						{
							include_once( WCWH_DIR . "/includes/controller/blockActionCtrl.php" ); 
							$Objs[ $doc_type ] = new WCWH_BlockAction_Controller();
							$titles[ $doc_type ] = "Block Stock Action";
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
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );
			
			if( $items )
			{
				$details = array();
				foreach( $items as $i => $item )
				{	
					$uprice = get_document_meta( $id, 'sprice', $item['item_id'], true );
					$details[] = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
						],
						'bqty' => ( $item['bqty'] - $item['uqty'] ),
						'bunit' => ( $item['bunit'] - $item['uunit'] ),
						'uprice' => $uprice,
						'ref_bqty' => $item['bqty'],
						'ref_bal' => ( $item['bqty'] - $item['uqty'] ),
						'ref_doc_id' => $item['doc_id'],
						'ref_item_id' => $item['item_id'],
					);
				}
				$args['data']['details'] = $details;
			}
		}

		if( $args['data']['ref_doc_id'] )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

		do_action( 'wcwh_get_template', 'form/blockStock-form.php', $args );
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

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_block_stock'] ) )
				{
					$hides = [ 'uprice', 'total_amount' ];
				}

				$attachs = $this->Files->get_infos( [ 'section_id'=>$this->section_id, 'ref_id'=>$id, 'seller'=>$args['seller'] ], [], false, [ 'usage'=>1 ] );
				if( $attachs )
				{
					if( $args['seller'] )
					{
						foreach( $attachs as $x => $attach )
						{
							if( $this->warehouse['api_url'] ) $attachs[$x]['api_url'] = $this->warehouse['api_url'];
						}
					}
					$datas['attachment'] = $attachs;
				}
		        	
		        if( $datas['details'] )
		        {
		        	$total_amount = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];

						$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;

						$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

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
		        		'prdt_name' => 'Item',
		        		'uom_code' => 'UOM',
		        		'bqty' => 'Qty',
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
			do_action( 'wcwh_templating', 'form/blockStock-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/blockStock-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/blockStock-row.php', $this->tplName['row'] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/blockStockListing.php" ); 
			$Inst = new WCWH_BlockStock_Listing();
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
	
} //class

}