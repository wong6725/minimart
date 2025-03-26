<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_RemoteCPR_Class" ) ) include_once( WCWH_DIR . "/includes/classes/remote-cpr.php" ); 

if ( !class_exists( "WCWH_RemoteCPR_Controller" ) ) 
{

class WCWH_RemoteCPR_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_remote_cpr";

	public $Notices;
	public $className = "WCWH_RemoteCPR_Controller";

	public $Logic;

	public $tplName = array();

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 6 ];

	public $skip_strict_unpost = false;
	public $skip_strict_co = false;

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
		$this->Logic = new WCWH_RemoteCPR_Class( $this->db_wpdb );
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
				case 'close':
				case 'reopen':
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
				case "close":
				case "reopen":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;

							$proceed = false;

							if( $action && in_array( $action, ['close', 'reopen']) && !$skip_strict_co )
							{
								$doc = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [ 'usage'=>1 ] );
								if( $doc && $succ )
								{
									$api_action = $action.'_purchase_request';
									$target = '';

									$filters = ['code'=>$doc['warehouse_id'], 'status'=>1, 'indication'=>1];
									$wh = apply_filters( 'wcwh_get_warehouse', $filters, [], true );

									if( !$wh ) $target = $doc['warehouse_id']; // dc to outlet
									else if( $wh && !$wh['parent']) $proceed = true;// dc to dc->skip
									//-- else outlet to dc

									if( !$proceed )
									{
										$remote = apply_filters( 'wcwh_api_request', $api_action, $id, $target, $this->section_id );

										if( ! $remote['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( $remote['notice'], 'error' );
										}
										else
										{
											$remote_result = $remote['result'];
											if( !$remote_result['succ'] )
											{
												$succ = false;
												$this->Notices->set_notice( $remote_result['notification'], 'error' );
											}
											else
												$proceed = true;
										}
									}

									if( !$proceed )
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

							if( $succ && $proceed)
							{
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

	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */

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
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

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
			include_once( WCWH_DIR . "/includes/listing/remoteCPRListing.php" ); 
			$Inst = new WCWH_RemoteCPR_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$wh_code = '';
			$exclude_wh = '';

			if( !$this->warehouse['parent'] )
				$exclude_wh = $this->warehouse['code'];
			else
				$wh_code = ( empty( $filters['warehouse_id'] ) )? $this->warehouse['code'] : $filters['warehouse_id'];
			
			$count = $this->Logic->count_statuses( $wh_code, $exclude_wh );
			if( $count ) $Inst->viewStats = $count;

			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 'process';
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			if( !$this->warehouse['parent'] )
				$filters['not_warehouse_id'] = $this->warehouse['code'];
			else
				$filters['warehouse_id'] = $this->warehouse['code'];

			$metas = [ 'remark', 'ref_doc' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			if( ! $order ) $order = ['a.warehouse_id'=>'ASC','a.docno'=>'ASC'];

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