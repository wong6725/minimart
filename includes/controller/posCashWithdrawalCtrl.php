<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PosCashWithdrawal_Class" ) ) include_once( WCWH_DIR . "/includes/classes/pos-cash-withdrawal.php" ); 

if ( !class_exists( "WCWH_PosCashWithdrawal_Controller" ) ) 
{

class WCWH_PosCashWithdrawal_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_pos_cash_withdrawal";

	public $Notices;
	public $className = "PosCashWithdrawal_Controller";

	public $Logic;

	
	public $tplName = array(
		'new' => 'newCashWithdrawal',
		'cw' => 'printCW',
		'row' => 'rowCashWithdrawal',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1, 6 ];

	
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
		$this->Logic = new WCWH_PosCashWithdrawal_Class( $this->db_wpdb );
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
					if($datas['header']['doc_id'])
					{
						$amt = $datas['header']['amt'];
						foreach($datas['detail'] as $key => $value)
						{
							$amt -= $value['bankin_amt'];
						}
						if($amt<0)
						{
							$succ = false;
							$this->Notices->set_notice( 'Insufficient amount for bank in ', 'warning' );
						}
					}else
					{
						foreach($datas['detail'] as $key => $value)
						{
							$amt += $value['bankin_amt'];
						}
						if($amt>$datas['header']['amt'])
						{
							$succ = false;
							$this->Notices->set_notice( 'Insufficient amount for bank in ', 'warning' );
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
					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'],'' ) : $now;
												
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
							$currency = get_woocommerce_currency();
							$id = $datas['id'];
							$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
							//$metas = ['withdraw_person','amt', 'bankin_person', 'bankin_date'];
							$doc = $this->Logic->get_header( [ 'doc_id' => $id,'seller'=>$datas['warehouse'] ], [], true, ['meta'=>$metas] );
							
							if(!$doc)
							{
								if($this->warehouse['code'] !='1025-MWT3')
								{
									$dbname ='mndc';
								}
								$doc = $this->Logic->get_header( [ 'doc_id' => $id], [], true, ['dbname'=>$dbname] );
							}
							
							//metas
							if($dbname)
							{
								$metas = $this->Logic->get_document_meta( $id,'',0,false,$dbname );
							}else{
								$metas = $this->Logic->get_document_meta( $id );
							}
		        			$doc = $this->combine_meta_data( $doc, $metas );
							if( $doc )
							{	
								$doc['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
								

								$date_format = get_option( 'date_format' );
								$params['heading']['docno'] = $doc['docno'];
								$params['heading']['second_infos'] = [
									'Doc. No.' => $doc['docno'],
									'Doc. Date' => date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
									
								];

								if( $doc['status'] > 0 )
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'usage'=>1, 'ref'=>1,'dbname'=>$dbname ] );
								else
									$doc['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'category'=>1, 'ref'=>1 ,'dbname'=>$dbname] );
							    
							    if( $doc['details'] )
							    {
							    	$detail = [];
							        foreach( $doc['details'] as $i => $item )
							        {
							        	$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'],false,$dbname );
		        						$item = $this->combine_meta_data( $item, $detail_metas );

							        	$row = [];
							        	$row['bankin_person'] = $item['bankin_person'];
							        	$row['bankin_date'] = $item['bankin_date'];
							        	$row['bankin_amt'] = $item['bankin_amt'];


							        	$detail[] = $row;
							        }

								}

								$params['heading']['first_infos']=[
									'Withdraw Date'=> date_i18n( $date_format, strtotime( $doc['doc_date'] ) ),
									'Withdraw Person' =>$doc['withdraw_person'],
									'Withdraw Amount' =>$currency.' '. number_format($doc['amt'],2),
									
								];
								foreach ($detail as $key => $value) {
									if($key == 0 )
									{
										$count = '';
									}else{
										$count = ' ('.$key.')';
									}
									$params['heading']['first_infos']['Bank In Person '.$count] = $value['bankin_person'];
									$params['heading']['first_infos']['Bank In Date'.$count] = $value['bankin_date'];
									$params['heading']['first_infos']['Bank In Amount'.$count] =$currency.' '. number_format($value['bankin_amt'],2);
									
								}
								$params['heading']['print_date'] =  current_time( 'mysql' );
							}

							if( $params )
							{
								add_document_meta( $doc['doc_id'], 'cw_print_'.current_time( 'Ymd' ), serialize( $params ) );
							}
								
							switch( strtolower( $datas['paper_size'] ) )
							{
								case 'receipt':
									$params['print'] = 1;
									ob_start();
										do_action( 'wcwh_get_template', 'template/receipt-cash-withdrawal.php', $params );
									$content.= ob_get_clean();

									echo $content;
								break;
								case 'default':
								default:
									ob_start();
										do_action( 'wcwh_get_template', 'template/doc-cash-withdrawal.php', $params );
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
				if( current_user_cans( [ 'access_wh_pos_cash_withdrawal'] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?>  Cash Withdrawal" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Cash Withdrawal"
				>
					<?php echo $actions['save'] ?> Cash Withdrawal
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	//refresh form to show total and balance
    // public function gen_form( $id = 0, $form = [], $action='')
	// {
	// 	$args = array(
	// 		'setting'	=> $this->setting,
	// 		'section'	=> $this->section_id,
	// 		'hook'		=> $this->section_id.'_form',
	// 		'action'	=> 'save',
	// 		'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
	// 		'new'		=> 'new',
	// 		'tplName'	=> $this->tplName['new'],
	// 		'rowTpl'	=> $this->tplName['row'],
	// 		'warehouse'	=> $this->warehouse,
			
	// 	);
	
		
	// 		$datas = $form['header'];
	// 		if( $this->warehouse['id'] && $this->view_outlet ) $datas['seller'] = $this->warehouse['id'];
	// 		$filters = ['to_date'=>$datas['to_date'],'seller'=>$datas['seller']];

	// 		$datas['total'] = $this->Logic->get_pos_cash($filters, [], []);

	// 		$header = $this->Logic->get_header( [ 'doc_id' => $id ], [], true );
			

	// 		//get withdrawed cash from selected period
	// 		$withdrawed_cash = $this->Logic->get_withdrawed_cash ($filters,[],[]);
			
	// 		$datas['total'] = round($datas['total'][0]['amt_cash']?$datas['total'][0]['amt_cash']:0, 2);
	// 		$datas['total_withdrawn'] = round($withdrawed_cash[0]['amt'], 2);
	// 		$datas['total_balance'] = round($datas['total'] - $datas['total_withdrawn'], 2);
	// 		$datas['balance'] = round($datas['total_balance'] - $datas['amt'], 2);
			
	// 		if($header)
	// 		{
	// 			//metas
	// 			$metas = $this->Logic->get_document_meta( $id );
	// 			$header = $this->combine_meta_data( $header, $metas );
				
	// 			if($header['amt']!=$datas['amt'])
	// 			{
	// 				$datas['total_withdrawn'] = round($withdrawed_cash[0]['amt'] - $header['amt'], 2);
	// 				$datas['total_balance'] = round($datas['total'] - $datas['total_withdrawn'], 2);
	// 				$datas['balance'] = round($datas['total_balance'] - $datas['amt'], 2);

	// 			}elseif($header['amt']==$datas['amt'])
	// 			{
	// 				$datas['balance'] = round($datas['total_balance'], 2);
	// 			}

	// 		}

			
			

	// 	$args['data'] = $datas;

	// 	do_action( 'wcwh_get_template', 'form/CashWithdrawal-form.php', $args );
	// }

	public function view_row()
	{
		$args['load'] = 1;
		do_action( 'wcwh_templating', 'segment/cashWithdrawal-row.php', $this->tplName['row'],$args );
	}


	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false )
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
			if(!$datas)
			{
				
				unset($args['seller']);
				if($this->warehouse['code'] !='1025-MWT3')
				{
					$dbname = 'mndc';
					$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true,['dbname'=>$dbname] );
				}
				
			}
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				
				//metas
				if($dbname)
				{
					$metas = $this->Logic->get_document_meta( $id,'',0,false,$dbname );
				}else{
					$metas = $this->Logic->get_document_meta( $id );
				}
				$datas = $this->combine_meta_data( $datas, $metas );

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1, 'usage'=>1,'dbname'=>$dbname ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1,'dbname'=>$dbname] );


				$filters = ['to_date'=>$datas['to_date'],'seller'=>$args['seller']];
				
				if(!$isView)
				{
					//get withdrawed cash from selected period
					$withdrawed_cash = $this->Logic->get_withdrawed_cash ($filters,[],['dbname'=>$dbname]);
					
					$datas['total_withdrawn'] = round($withdrawed_cash[0]['amt'], 2);
					$t_amt = $datas['amt'];
					foreach ($datas['details'] as $key => $value) {

						$metas = $this->Logic->get_document_meta( $id,'',$value['item_id'],false,$dbname );
						$t_amt -= $metas['bankin_amt'][0];

					}
					$datas['available_amt'] = $t_amt;
					// $datas['total_balance'] = round($datas['total'] - $datas['total_withdrawn'], 2);
					// $datas['balance'] = round($datas['total_balance'] , 2);
					$args['action'] = 'update';
					
				}

				if( $isView ) $args['view'] = true;
				if($args['action'] == 'update' && $datas['status']>='6')
				{
					$args['view'] = true;
				}
				$Inst = new WCWH_Listing();


				if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'],false,$dbname );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$total_amount+= $datas['details'][$i]['bankin_amt'];
						
		        	}
					if($isView)
					{
						$count = $i+1;
						$datas['details'][$count]['bankin_person']= '<strong>TOTAL:</strong>';
						$datas['details'][$count]['bankin_amt']= '<strong>'.round_to( $total_amount, 2, true ).'</strong>';
					}
					
					
		        }


				$args['data'] = $datas;
		       
				unset( $args['new'] );	
		        if( $datas['details'] )
				{
					$args['render'] = $Inst->get_listing( [
			        		'num' => '',
			        		'bankin_person' => 'Bank In Person',
			        		'bankin_date' => 'Date',
							'bankin_amt' => 'Bank In Amount',
							
			        	], 
			        	$datas['details'], 
			        	[], 
			        	[], 
			        	[ 'off_footer'=>true, 'list_only'=>true ]
			        );
				}
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/posCashWithdrawal-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/posCashWithdrawal-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/posCashWithdrawalListing.php" ); 
			$Inst = new WCWH_PosCashWithdrawal_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$count = $this->Logic->count_statuses( $this->warehouse['code'] );
			if( $count ) $Inst->viewStats = $count;
			
			
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
			
			$order = ['doc_id'=>'DESC'];
			$limit = $Inst->get_data_limit();
			$metas = ['withdraw_person', 'amt', 'bankin_person', 'bankin_date'];
			if($filters['to_date']||$filters['from_date'])
			{
				$filters['to_date'] = $filters['to_date']. ' 23:59:59' ;
				$filters['from_date'] = $filters['from_date']. ' 00:00:00' ;
				unset($filters['doc_date']);
			}
			
			$datas = $this->Logic->get_header( $filters, $order, false, ['meta'=>$metas,'doc_date_greater'=>$filters['from_date'],'doc_date_lesser'=>$filters['to_date']], [], $limit );
			
			if($this->warehouse['code']!='1025-MWT3')
			{
				$dbname = 'mndc';
				$count_dc = $this->Logic->count_statuses( $this->warehouse['code'],$dbname );
				foreach($count_dc as $key => $value)
				{
					$value += $count[$key];
					$final_count[$key] = $value;
				}
				$Inst->viewStats = $final_count;
				
				$datas_dc =  $this->Logic->get_header( $filters, $order, false, ['meta'=>$metas,'doc_date_greater'=>$filters['from_date'],'doc_date_lesser'=>$filters['to_date'],'dbname'=>$dbname], [], $limit );
			}
			$datas = array_merge($datas,$datas_dc);
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	public function cw_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['cw'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
			'warehouse'	=> $this->warehouse,
		);

		do_action( 'wcwh_templating', 'form/cashWithdrawal-print-form.php', $this->tplName['cw'], $args );
	}

	
	
} //class

}