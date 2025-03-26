<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_OrderingPR_Class" ) ) include_once( WCWH_DIR . "/includes/classes/orderingPR.php" ); 

if ( !class_exists( "WCWH_OrderingPR_Controller" ) ) 
{

class WCWH_OrderingPR_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_ordering_pr";

	public $Notices;
	public $className = "OrderingPR_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newPR',
		'row' => 'rowPR'
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
		$this->Logic = new WCWH_OrderingPR_Class( $this->db_wpdb );
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
				// case 'save':
				// 	if( ! $datas['id'] )
				// 	{
				// 		$succ = false;
				// 		$this->Notices->set_notice( 'no-selection', 'warning' );
				// 	}
				// break;
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

					$ccc = $this->warehouse['client_company_code'];
					if( $ccc ) 
					{
						$ccc = json_decode( $ccc, true );
						$header['client_company_code'] = $ccc[0];
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
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */

	public function gen_form( $ids = array() )
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

		if( $ids )
		{
			$dist_items = [];
			$exclude_base_item = [];
			if( !is_array( $ids ) )
			{
				$ids = array($ids);
			}

			foreach ($ids as $i => $id) 
			{
				$header = $this->Logic->get_header( [ 'doc_id'=>$id, 'doc_type'=>'none' ], [], true, [ 'warehouse'=>1, 'company'=>1 ] );
				if( $header )
				{
					$metas = get_document_meta( $id );
					$header = $this->combine_meta_data( $header, $metas );
					//-----check condition and skip if the doc is already in used
		        	if($header['pr_ordering'] )
		        	{
		        		continue;
		        	}
		        	$ordering_ref[$id] = $header['docno'];
				}

				$filters = ['doc_id' => $id];
				$details = $this->Logic->get_detail( $filters, [], false, [ 'item'=>1, 'uom'=>1, 'usage'=>1 ] );
				if($details)
				{
					//------- get reorder info---//
					$filters = [];
					if($this->warehouse['id']) $filters['seller'] = $this->warehouse['id'];
		        	include_once( WCWH_DIR . "/includes/reports/reorderReport.php" );
		        	$RR = new WCWH_Reorder_Rpt();
		        	$reorder_item_info = $RR->get_reorder_report( $filters, [], [ 'uom'=>1] );

		        	if( $reorder_item_info )
		        	{
		        		$args['data']['reorder_item_info'] = $reorder_item_info;
		        		$rii = [];
		        		foreach ($reorder_item_info as $key => $item)
		        		{
		        			$rii[$item['item_id']] = $item;
		        		}
		        	}
		        	//pd($rii);
		        	//------- get reorder info---//

		        	foreach( $details as $i => $item )
		        	{
		        		$metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$item = $this->combine_meta_data( $details[$i], $metas );

		        		if($rii &&  $rii[$item['product_id']])
		        		{
		        			$hms = ($rii[$item['product_id']]['hms_month'])? $rii[$item['product_id']]['hms_qty'].'<br> ('.$rii[$item['product_id']]['hms_month'].')' : $rii[$item['product_id']]['hms_qty'];
		        			$order_type = $rii[$item['product_id']]['order_type'];
		        			$stock_bal = $rii[$item['product_id']]['stock_bal'];
		        			$po_qty = $rii[$item['product_id']]['po_qty'];
		        			$rov = $rii[$item['product_id']]['final_rov'];
		        		}

		        		if( !$dist_items || ($dist_items && !isset( $dist_items[$item['product_id']]) ) )
						{
							//-----26/9/22
							if($item['parent'])
							{
								$exclude_base_item[$item['product_id']] = $item['parent'];
							}
							//-----26/9/22

							$dist_items[$item['product_id']] = array(
								'id' =>  $item['product_id'],
								'product_id' =>  $item['product_id'],
								'item_id' =>  '',
								'line_item' => [
									'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
								],
								'bqty' =>  ( $rov )? (( $item['uom_fraction'] )? $rov :ceil($rov)) : '',
								'ref_bqty' =>  $item['bqty'],
								'ref_doc_id' =>  '',
								'ref_item_id' =>  '',
								'hms' =>  ( $hms )? $hms : '',
								'order_type' =>  ( $order_type )? $order_type : '',
								'stock_bal' =>  ( $stock_bal )? $stock_bal : '',
								'po_qty' =>  ( $po_qty )? $po_qty : '',
								'rov' =>  ( $rov )? $rov : '',																
							);
						}
						else if( $dist_items && isset($dist_items[$item['product_id']]) )
						{
							$dist_items[$item['product_id']]['ref_bqty'] += $item['bqty'];
						}

		        	}

				}
			}
			if($ordering_ref)
			{
				$ordering_ref = htmlspecialchars(maybe_serialize($ordering_ref));
				$args['data']['ordering_ref'] = $ordering_ref;
			} 
			if($dist_items) 
			{
				//--------26/9/22
				if($exclude_base_item)
				{
					foreach ($exclude_base_item as $child_id =>$base_id) 
					{
						if($dist_items[$base_id])
						{
							$temp_bqty = ''; 
							$temp_uom = '';
							$temp_bqty = $dist_items[$base_id]['ref_bqty'];
							$temp_uom = $dist_items[$base_id]['line_item']['uom_code'];
							unset($dist_items[$base_id]);

							$dist_items[$child_id]['ref_bqty'] .= '<br> +'.$temp_bqty.' '.$temp_uom;
						}
					}
				}
				//------26/9/22
				$dist_items = $this->items_sorting($dist_items);
				$args['data']['details'] = $dist_items;
			}
		}

		do_action( 'wcwh_get_template', 'form/orderingPR-form.php', $args );
	}

	public function items_sorting( $details )
	{
		if( ! $details ) return $details;
;
		$items = []; $items_list = [];
		foreach ( $details as $i => $detail_item )
		{
			if( ! $detail_item['product_id'] ) continue;

			$items_list[] = $detail_item['product_id'];
			$items[$detail_item['product_id']] = $detail_item;
		}

		if( $items_list )
		{
			$sort = apply_filters( 'wcwh_get_item', [ 'id'=>$items_list ], [ 'grp.code'=>'ASC', 'cat.slug'=>'ASC', 'a.code'=>'ASC' ], false, [ 'group'=>1, 'category'=>1 ] );
		}		
		
		if( $sort )//_item_number
		{
			$temp= [];
			foreach( $sort as $j => $item )
			{
				if($items[$item['id']])
				{
					$temp[] = $items[$item['id']];
				}
			}
		}

		if($temp && count($temp) == count($details) )
		{
			$details = $temp;
		}

		return $details;
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
		        	$reorder_item_info = $RR->get_reorder_report( $filters, [], [ 'uom'=>1] );

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
			do_action( 'wcwh_templating', 'form/orderingPR-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/orderingPR-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/orderingPR-row.php', $this->tplName['row'] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/orderingPRListing.php" ); 
			$Inst = new WCWH_OrderingPR_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			//if($this->warehouse['indication'] && !$this->warehouse['view_outlet']) $wh_code = $this->warehouse['code'];

			$filters['status'] = 6;
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-modal' => 'wcwhModalForm',
				'data-actions' => 'close|submit',
				'data-tpl' => '', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			if($this->warehouse['indication'] && !$this->warehouse['view_outlet'])
			{
				$filters['not_warehouse_id'] = $this->warehouse['code'];
			}

			$metas = [ 'remark', 'ref_doc', 'pr_ordering' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas ], [], $limit );
			$datas = ( $datas )? $datas : array();

			if($datas)
			{
				foreach($datas as $d => $data)
				{
					if($data['pr_ordering'])
					{
						unset($datas[$d]);
					}
				}
				$datas = array_values($datas);
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}