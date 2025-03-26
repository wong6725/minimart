<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_IssueReturn_Class" ) ) include_once( WCWH_DIR . "/includes/classes/issue-return.php" ); 

if ( !class_exists( "WCWH_IssueReturn_Controller" ) ) 
{

class WCWH_IssueReturn_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_issue_return";

	public $Notices;
	public $className = "IssueReturn_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newIR',
		'row' => 'rowIR',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $issue_returns = [
		'inventory_inbound' => 'Inventory Inbound',
		'dispose' => 'Dispose',
	];

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
		$this->Logic = new WCWH_IssueReturn_Class( $this->db_wpdb );
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
					
					$refDetail = [];
					if( $header['ref_doc_id'] )
					{	//get GI
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

							$metas = get_document_meta( $header['ref_doc_id'] );
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

							if( $refDetail[ $row['ref_item_id'] ] )
							{
								$ref_row = $refDetail[ $row['ref_item_id'] ];

								if( $ref_row['weighted_total'] > 0 )
								{
									$detail[$i]['total_amount'] = round_to( $ref_row['weighted_total'] / $ref_row['bqty'] * $row['bqty'], 2 );
									$detail[$i]['uprice'] = round_to( $ref_row['weighted_total'] / $ref_row['bqty'], 5 );
								}
								else
								{
									$detail[$i]['uprice'] = ( $ref_row['weighted_price'] > 0 )? $ref_row['weighted_price'] : $detail[$i]['uprice'];
									$detail[$i]['total_amount'] = round_to( $row['bqty'] * $detail[$i]['uprice'], 2 );
								}

								$sunit = get_document_meta( $row['ref_doc_id'], 'sunit', $row['ref_item_id'], true );
								if( $sunit > 0 )
								{
									$detail[$i]['bunit'] = round_to( $sunit / $ref_row['bqty'] * $row['bqty'], 3 );
								}
							}
							else
							{
								$header['doc_type'] = $this->Logic->getDocumentType();
								$f = [ 
									'warehouse_id' => $header['warehouse_id'],
									'strg_id' => apply_filters( 'wcwh_get_system_storage', '', $header, $row ),
									'prdt_id' => $row['product_id'], 
								];
								
								$inv = apply_filters( 'wcwh_get_inventory', $f, [], true );
								if( $inv )
								{
									$detail[$i]['uprice'] = $inv['total_in_avg'];
									$detail[$i]['total_amount'] = round_to( $inv['total_in_avg'] * $row['bqty'], 2 );
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

			$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false, [ 'meta'=>[ 'supply_to_seller' ] ] );
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

		if ( !class_exists( "WCWH_GoodReceive_Class" ) ) include_once( WCWH_DIR . "/includes/classes/good-receive.php" ); 
		$Inst = new WCWH_GoodReceive_Class( $this->db_wpdb );
		//$Inst->setUpdateUqtyFlag( false );

		$succ = false;

		$doc_header = $this->Logic->get_header( [ 'doc_id'=>$doc_id, 'doc_type'=>'issue_return' ], [], true, [] );
		if( $doc_header )
		{
			$metas = get_document_meta( $doc_id );
			$doc_header = $this->combine_meta_data( $doc_header, $metas );

			if( in_array( $doc_header['issue_return_type'], [ 'inventory_inbound' ] ) )
			{
				switch( $action )
				{
					case 'post':
						$header = [
							'warehouse_id' => $this->warehouse['code'],
							'doc_date' => $doc_header['doc_date'],
							'post_date' => $doc_header['post_date'],
							'parent' => $doc_header['doc_id'],
							'hstatus' => 1,
							'ref_doc_type' => $doc_header['doc_type'],
							'ref_doc_id' => $doc_header['doc_id'],
							'ref_doc' => $doc_header['docno'],
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
									'bunit' => ( $row['bunit'] > 0 )? $row['bunit'] : 0,
									'uprice' => $row['uprice'],
									'total_amount' => $row['total_amount'],
									'item_id' => '',
									'ref_doc_id' => $row['doc_id'],
									'ref_item_id' => $row['item_id'],
									'dstatus' => 1,
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
				            	$result = $Inst->child_action_handle( 'post', [ 'doc_id' => $result['id'] ], [] );
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
					break;
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
			
			$options = options_data( $reference, 'doc_id', [ 'docno', 'warehouse_id', 'good_issue_type', 'client', 'remark' ], 'Issue Return by Document (Required)' );
	        echo '<div id="issue_return_reference_content" class="col-md-7">';
	        wcwh_form_field( '', 
	            [ 'id'=>'issue_return_reference', 'type'=>'select', 'label'=>'', 'required'=>false, 
	            	'attrs'=>['data-change="#issue_return_action"'], 'class'=>['select2','triggerChange'], 
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
				<button id="issue_return_action" class="btn btn-sm btn-primary linkAction display-none" title="Add <?php echo $actions['save'] ?> Issue Return"
					data-title="<?php echo $actions['save'] ?> Issue Return" 
					data-action="issue_return_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#issue_return_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> Issue Return
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'save_item':
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="issue_return_item_action" class="btn btn-sm btn-primary linkAction" title="<?php echo $actions['save'] ?> Issue Return"
					data-title="<?php echo $actions['save'] ?> Issue Return" 
					data-action="issue_return_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					Issue Return
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
			'issue_returns' => $this->issue_returns,
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

				$args['NoAddItem'] = true;
			}

			$filters = [ 'doc_id'=>$id ];
			$items = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );
			
			if( $items )
			{
				$details = array();
				foreach( $items as $i => $item )
				{	
					$details[] = array(
						'id' =>  $item['product_id'],
						'product_id' => $item['product_id'],
						'item_id' => '',
						'line_item' => [ 'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 'stocks' => $item['stock_qty'], ],
						'bqty' => ( $item['bqty'] - $item['uqty'] ),
						'bunit' => ( $item['bunit'] - $item['uunit'] ),
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

		do_action( 'wcwh_get_template', 'form/issueReturn-form.php', $args );
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
			'issue_returns' => $this->issue_returns,
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
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'ref'=>1, 'transact'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'ref'=>1, 'transact'=>1 ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				$args['NoAddItem'] = true;

				$Inst = new WCWH_Listing();

				$hides = [];
				if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_issue_return'] ) )
				{
					$hides = [ 'uprice', 'total_amount' ];
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
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit'], 
							'stocks' => $item['stock_qty'],
		        		];

		        		$datas['details'][$i]['ref_bal'] = $item['ref_bqty']? $item['ref_bqty'] - $item['ref_uqty'] + $item['bqty'] : 0;
		        		
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2, true );
		        		$datas['details'][$i]['bunit'] = round_to( $datas['details'][$i]['sunit'], 3, true );

		        		$datas['details'][$i]['uprice'] = round_to( $datas['details'][$i]['uprice'], 5, true );
		        		$datas['details'][$i]['total_amount'] = round_to( $datas['details'][$i]['total_amount'], 2, true );
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
		        		'total_amount' => 'Total',
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
			do_action( 'wcwh_templating', 'form/issueReturn-form.php', $this->tplName['new'], $args );
		}
		else
		{
			if( !empty( $args['data']['ref_doc_id'] ) && ( isset( $config['ref'] ) && $config['ref'] ) )
				$this->view_reference_doc( $args['data']['ref_doc_id'], $args['data']['ref_doc'], $args['data']['ref_doc_type'] );

			do_action( 'wcwh_get_template', 'form/issueReturn-form.php', $args );

			if( $isView && !empty( $args['data']['doc_id'] ) && ( isset( $config['link'] ) && $config['link'] ) ) 
				$this->view_linked_doc( $args['data']['doc_id'], $config );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/issueReturn-row.php', $this->tplName['row'] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/issueReturnListing.php" ); 
			$Inst = new WCWH_IssueReturn_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#action' => [ 'width' => '90px' ],
			];
			$Inst->issue_returns = $this->issue_returns;

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

			$metas = [ 'remark', 'ref_doc_id', 'ref_doc_type', 'ref_doc', 'issue_return_type' ];

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