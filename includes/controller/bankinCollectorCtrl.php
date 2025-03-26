<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_BankInCollector_Class" ) ) include_once( WCWH_DIR . "/includes/classes/bankin-collector.php" );

if ( !class_exists( "WCWH_BankInCollector_Controller" ) ) 
{

class WCWH_BankInCollector_Controller extends WCWH_CRUD_Controller
{
	protected $section_id = "wh_bankin_collector";

	public $Notices;
	public $className = "BankInCollector_Controller";

	public $Logic;
	public $Bankinfo;
	public $BankinfoCtrl;

	public $tplName = array(
		'new' => 'newBIC',
		'row' => 'rowBIC',
		'bic' => 'printBIC',
	);

	public $useFlag = false;
	public $outlet_post = true;

	protected $warehouse = array();
	protected $view_outlet = false;
	
	public $processing_stat = [ 1 ];

	public $running_no = [];

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
		$this->Logic = new WCWH_BankInCollector_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->processing_stat = $this->processing_stat;

		$running_no = apply_filters( 'wcwh_get_running_no', [ 'doc_type'=>'bank_in', 'ref_type'=>'default' ], [], true );
		if( $running_no ) $this->running_no = $running_no;
		else $this->running_no = [ 'doc_type'=>'bank_in', 'prefix'=>'RMS' ];

		add_filter( 'wcwh_get_bankin_servises', array( $this->Logic, 'get_bankin_servises' ), 10, 6 );
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

	public function collected_to_id()
	{
		$docs = $this->Logic->get_header( [ 'doc_type'=>'bankin_collector' ], [], false, [ 'usage'=>1, 'meta'=>['ref_ids'] ] );
		foreach( $docs as $doc )
		{
			$ref_ids = explode( ",", $doc['ref_ids'] );
			if( $ref_ids )
			{
				foreach( $ref_ids as $ref_id )
				{
					$collected = get_document_meta( $ref_id, 'collected', 0, true );
					//if( ! $collected ) echo $ref_id." | ".$doc['doc_id']."<br>";
					//update_document_meta( $ref_id, 'collected', $doc['doc_id'], 0 );
				}
			}
		}
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
					if( ! isset( $datas['header']['doc_id'] ) || ! $datas['header']['doc_id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}	
				case 'save':
					if( empty( $datas['header']['from_doc'] ) || empty( $datas['header']['to_doc'] ) )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					else
					{
						$from_doc = $this->Logic->get_header( [ 'doc_id' => $datas['header']['from_doc'], 'doc_type'=>'bank_in' ], [], true, [] );
						$to_doc = $this->Logic->get_header( [ 'doc_id' => $datas['header']['to_doc'], 'doc_type'=>'bank_in' ], [], true, [] );
						if( $from_doc && $to_doc )
						{
							$prefix = ( $this->running_no['prefix'] )? $this->running_no['prefix'] : 'RMS';

							$from = str_replace( $prefix, '', trim( $from_doc['docno'] ) );
							$to = str_replace( $prefix, '', trim( $to_doc['docno'] ) );
							if( (int)$to < (int)$from )
							{
								$succ = false;
								$this->Notices->set_notice( 'Until Doc No. can not be smaller than From Doc No.', 'warning' );
							}
							else
							{
								$docs = $this->Logic->get_bankin_servises( [ 'from_doc'=>$from, 'to_doc'=>$to ], [ 'a.docno'=>'ASC' ], false, [ 'prefix'=>$this->running_no['prefix'], 'checking'=>($datas['header']['doc_id'])? $datas['header']['doc_id'] : true ] );
								if( sizeof( $docs ) > 0 )
								{
									$d = [];
									foreach( $docs as $doc ) $d[] = $doc['docno'];
									$succ = false;
									$this->Notices->set_notice( 'Selected range found previously completed document: '.implode( ",", $d ), 'warning' );
								}
							}
						}
						else
						{
							$succ = false;
							$this->Notices->set_notice( 'Document not Found', 'error' );
						}
					}
				break;
				case 'post':
				case "complete":
				case 'delete':
				case 'unpost':
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
					
					$header['warehouse_id'] = !empty( $header['warehouse_id'] )? $header['warehouse_id'] : $this->warehouse['code'];
					
					if( $header['doc_id'] )
					{
						$prev_ids = get_document_meta( $header['doc_id'], 'ref_ids', 0, true );
						$prev_ids = explode( ",", $prev_ids );
					}

					$from_doc = $this->Logic->get_header( [ 'doc_id'=>$header['from_doc'], 'doc_type'=>'bank_in' ], [], true, [] );
					$to_doc = $this->Logic->get_header( [ 'doc_id'=>$header['to_doc'], 'doc_type'=>'bank_in' ], [], true, [] );
					$refs = []; $ref_ids = [];
					if( $from_doc && $to_doc )
					{
						$prefix = ( $this->running_no['prefix'] )? $this->running_no['prefix'] : 'RMS';

						$from = str_replace( $prefix, '', trim( $from_doc['docno'] ) );
						$to = str_replace( $prefix, '', trim( $to_doc['docno'] ) );
						$header['from_docno'] = $from_doc['docno'];
						$header['to_docno'] = $to_doc['docno'];

						$docs = $this->Logic->get_bankin_servises( [ 'from_doc'=>$from, 'to_doc'=>$to ], [ 'a.docno'=>'ASC' ], false, [ 'prefix'=>$this->running_no['prefix'], 'meta'=>[ 'total_amount', 'service_charge', 'convert_amount' ], 'selection'=>( $header['doc_id'] )? $header['doc_id'] : true, 'posting'=>1 ] );
						if( $docs )
						{
							$tmyr = 0; $tidr = 0; $tsvc = 0; 
							foreach( $docs as $doc )
							{
								$refs[] = $doc['docno'];
								$ref_ids[] = $doc['doc_id'];

								$tmyr+= $doc['total_amount'];
								$tidr+= $doc['convert_amount'];
								$tsvc+= $doc['service_charge'];
							}

							$header['ref_docs'] = implode( ",", $refs );
							$header['ref_ids'] = implode( ",", $ref_ids );

							$header['total_amount'] = $tmyr;
							$header['convert_amount'] = $tidr;
							$header['service_charge'] = $tsvc;
							$header['order_count'] = sizeof( $docs );
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

							//update refs meta
							if( $ref_ids )
							{
								foreach( $ref_ids as $ref_id )
								{
									$this->Logic->add_document_meta_value( 'collected', $result['id'], $ref_id, 0 );
								}

								if( $prev_ids )
								{
									foreach( $prev_ids as $ref_id )
									{
										if( ! in_array( $ref_id, $ref_ids ) )
											$this->Logic->add_document_meta_value( 'collected', 0, $ref_id, 0 );
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

								$prev_ids = get_document_meta( $id, 'ref_ids', 0, true );
								$prev_ids = explode( ",", $prev_ids );
								if( $prev_ids )
								{
									foreach( $prev_ids as $ref_id )
									{
										$this->Logic->add_document_meta_value( 'collected', 0, $ref_id, 0 );
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
								
								$params['heading']['company'] = $warehouse['name']." (".$doc['warehouse_id'].")";
								$params['heading']['title'] = "Remittance Money Collector";
								
								$params['heading']['docno'] = $doc['docno'];
								$params['heading']['doc_date'] = date_i18n( $date_format, strtotime( $doc['doc_date'] ) );
								$user_info = get_userdata( get_current_user_id() );
								$params['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
								$params['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
								
								$params['detail'] = $doc;

								//mha004 - print receipt
								$params['heading']['infos1'] = [
									'Remittance Money Receipts ' => $doc['from_docno']." - ".$doc['to_docno'],
									'Total Receipts ' => $doc['order_count'],
									'Total Remittance Amount' => round_to( $doc['total_amount'] - $doc['service_charge'], 2,1,1 ),
									'Total Service Charge' => round_to( $doc['service_charge'], 2,1,1 ),
									'Total Amount' => round_to( $doc['total_amount'], 2,1,1 ),
								];

								$params['heading']['infos3'] = [
									'Print On' => date('Y-m-d',strtotime($doc['doc_date'])),
								];
								//end mha
							}

							switch( strtolower( $datas['paper_size'] ) )
							{
								case 'receipt':
									$params['print'] = 1;
									ob_start();
										do_action( 'wcwh_get_template', 'template/receipt-remittance-collection.php', $params );
									$content.= ob_get_clean();
	
									echo $content;
								break;
								case 'default':
								default:
									ob_start();
									do_action( 'wcwh_get_template', 'template/doc-remittance-collection.php', $params );
			
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
				<button id="bankin_collector_action" class="btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Remittance Money Collector"
					data-title="<?php echo $actions['save'] ?> Remittance Money Collector" 
					data-action="bankin_collector_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
				>
					<?php echo $actions['save'] ?> Remittance Money Collector
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
			'prefix'	=> $this->running_no['prefix'],
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		do_action( 'wcwh_get_template', 'form/bankinCollector-form.php', $args );
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
			'prefix'	=> $this->running_no['prefix'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}
		
		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id, 'doc_type'=>$this->Logic->getDocumentType() ], [], true, [] );
			if($datas)
			{
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";

				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				$args['action'] = 'update';
				
				if( $isView ) $args['view'] = true;

				$args['data'] = $datas;
				unset( $args['new'] );	

				$Inst = new WCWH_Listing();

				$datas['details'] = $this->Logic->get_header( [ 'doc_id'=>explode( ",", $datas['ref_ids'] ), 'doc_type'=>'bank_in' ], [], false, [ 'meta'=>[ 'sender_name', 'total_amount', 'service_charge', 'convert_amount', 'currency', 'customer_serial' ] ] );

				if( $datas['details'] )
				{
					$amt = 0; $charges = 0; $total = 0; $converted = 0;
					foreach( $datas['details'] as $i => $row )
					{
						$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']}'>".($i+1).".</span>" : ($i+1).".";

						$datas['details'][$i]['amt'] = round_to( $row['total_amount'] - $row['service_charge'], 2, 1, 1 );
						$datas['details'][$i]['service_charge'] = round_to( $row['service_charge'], 2, 1, 1 );
						$datas['details'][$i]['total_amount'] = round_to( $row['total_amount'], 2, 1, 1 );
						$datas['details'][$i]['convert_amount'] = round_to( $row['convert_amount'], 2, 1, 1 );

						$amt+= $row['total_amount'] - $row['service_charge'];
						$charges+= $row['service_charge'];
						$total+= $row['total_amount'];
						$converted+= $row['convert_amount'];
					}

					$datas['details'][] = [
		        		'docno' => '<strong>Total:</strong>',
		        		'amt' => '<strong>'.round_to( $amt, 2, 1, 1 ).'</strong>',
		        		'service_charge' => '<strong>'.round_to( $charges, 2, 1, 1 ).'</strong>',
		        		'total_amount' => '<strong>'.round_to( $total, 2, 1, 1 ).'</strong>',
		        		'convert_amount' => '<strong>'.round_to( $converted, 2, 1, 1 ).'</strong>',
		        	];
				}

				$cols = [
		        	'num' => '',
		        	'docno' => 'Docno',
		        	'doc_date' => 'Date',
		        	'sender_name' => 'Sender',
		        	'customer_serial' => 'Customer No.',
		        	'amt' => 'Sending Amt',
		        	'service_charge' => 'Service Charge',
		        	'total_amount' => 'Total Amt',
		        	'currency' => 'Currency',
		        	'convert_amount' => 'Converted Amt',
		        ];

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
			do_action( 'wcwh_templating', 'form/bankinCollector-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/bankinCollector-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/bankinCollector-row.php', $this->tplName['row'] );
	}

	public function bic_form()
	{
		$pdat = get_transient( get_current_user_id().$this->section_id."print" );
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['bic'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
			'print_opt' => $pdat,
		);
	
		do_action( 'wcwh_templating', 'form/bankinCollector-print-form.php', $this->tplName['bic'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
		include_once( WCWH_DIR . "/includes/listing/bankinCollectorListing.php" ); 
		$Inst = new WCWH_BankInCollector_Listing();
		$Inst->set_warehouse( $this->warehouse );
		$Inst->set_section_id( $this->section_id );
		$Inst->useFlag = $this->useFlag;
		
		$count = $this->Logic->count_statuses();
		if( $count ) $Inst->viewStats = $count;

		$Inst->filters = $filters;
		$Inst->advSearch_onoff();

		$Inst->bulks = array( 
			'data-section'=>$this->section_id,
			'data-tpl' => 'remark',
			'data-service' => $this->section_id.'_action',
			'data-form' => 'edit-'.$this->section_id,
		);

		$meta = [ 'order_count', 'total_amount', 'remark', 'ref_docs', 'from_docno', 'to_docno' ];
		
		$order = $Inst->get_data_ordering();
		$limit = $Inst->get_data_limit();
		
		$datas = $this->Logic->get_header( $filters, $order, false, [ 'parent'=>1, 'meta'=>$meta ], [], $limit );
		
		$datas = ( $datas )? $datas : array();

		$Inst->set_details( $datas );
		$Inst->render();

		?>		
		</form>
		<?php
	}

} //class

}