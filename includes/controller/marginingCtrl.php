<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_Margining' ) ) include_once( WCWH_DIR . "/includes/classes/margining.php" ); 
if( ! class_exists( 'WCWH_MarginingSect' ) ) include_once( WCWH_DIR . "/includes/classes/margining-sect.php" ); 
if( ! class_exists( 'WCWH_MarginingDet' ) ) include_once( WCWH_DIR . "/includes/classes/margining-det.php" ); 

if ( !class_exists( "WCWH_Margining_Controller" ) ) 
{

class WCWH_Margining_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_margining";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Margining_Controller";

	public $Logic;
	public $Sect;
	public $Detail;

	public $tplName = array(
		'new' => 'newMargining',
		'sect' => 'sectMargining',
		'row' => 'rowMargining',
	);

	public $matters = [
		"wh_sales_rpt_summary" => [
			"title" => "Sales Reports > Sales Order Listing",
			"section" => "wh_sales_rpt",
		],
		"wh_sales_rpt_delivery_order" => [
			"title" => "Sales Reports > Sales Order with DO",
			"section" => "wh_sales_rpt",
		],
		"wh_sales_rpt_canteen_einvoice" => [
			"title" => "Sales Reports > Minimart's e-Invoice",
			"section" => "wh_sales_rpt",
		],
		"wh_sales_rpt_non_canteen_einvoice" => [
			"title" => "Sales Reports > Direct Sales e-Invoice",
			"section" => "wh_sales_rpt",
		],
		"wh_movement_rpt_stock_in" => [
			"title" => "Inventory Reports > Stock In",
			"section" => "wh_movement_rpt",
		],
		"wh_movement_rpt_stock_out" => [
			"title" => "Inventory Reports > Stock Out",
			"section" => "wh_movement_rpt",
		],
		"wh_sales_order_invoice" => [
			"title" => "Sale Order > Invoice",
			"section" => "wh_sales_order",
		],
		"wh_good_receive_sale_order_automate" => [
			"title" => "Goods Receipt > Automate Sales Order",
			"section" => "wh_good_receive",
		],
	];

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->arrangement_init();

		$this->set_logic();
	}

	public function __destruct() {}

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
		$this->Logic = new WCWH_Margining( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;

		$this->Sect = new WCWH_MarginingSect( $this->db_wpdb );
		$this->Sect->set_section_id( $this->section_id );

		$this->Detail = new WCWH_MarginingDet( $this->db_wpdb );
		$this->Detail->set_section_id( $this->section_id );
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function set_warehouse( $warehouse = array() )
	{
		$this->warehouse = $warehouse;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'wh_id' => '',
			'since' => '',
			'until' => '',
			'effective' => '',
			'inclusive' => 'incl',
			'margin' => 0.00,
			'round_type' => '',
			'round_nearest' => 0.00,
			'po_inclusive' => 'def',
			'type' => 'def',
			'remarks' => '',
			'status' => 1,
			'flag' => ( $this->useFlag )? 0 : 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}
		protected function get_defaultSectionFields()
		{
			return array(
				'mg_id' => 0,
				'section' => '',
				'sub_section' => '',
				'status' => 1,
			);
		}
		protected function get_defaultDetailFields()
		{
			return array(
				'mg_id' => 0,
				'client' => '',
				'margin' => 0.00,
				'status' => 1,
			);
		}

	protected function get_uniqueFields()
	{
		return array();
	}

	protected function get_unneededFields()
	{
		return array( 
			'action', 
			'token', 
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
					if( ! $datas['element'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( ! $datas['detail'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'restore':
				case 'delete':
				case 'approve':
				case 'reject':
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

	public function action_handler( $action = 'save', $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		
		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$isSave = false;
        	$mg_id = 0;
        	$result = array();
        	$child_result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$header = $datas['header'];
					$header = $this->data_sanitizing( $header );

					$element = $datas['element'];
					$detail = $datas['detail'];
					
					$header['lupdate_by'] = $user_id;
					$header['lupdate_at'] = $now;
					
					$metas = [];

					$header['round_nearest'] = ( $header['round_nearest'] > 0 )? $header['round_nearest'] : 0.01;
					
					$source = [];
					if( ! $header[ $this->get_primaryKey() ] && $action == 'save' )
					{
						$header['created_by'] = $user_id;
						$header['created_at'] = $now;

						$header = wp_parse_args( $header, $this->get_defaultFields() );
						
						$isSave = true;
						$result = $this->Logic->action_handler( $action, $header, $metas );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'error', 'error' );
						}
						else
						{
							$outcome['id'][] = $result['id'];
							$mg_id = $result['id'];
						}

						if( $succ && $element )
						{
							foreach( $element as $i => $row )
							{
								unset( $row['item_id'] );
								$row['mg_id'] = $mg_id;

								$row = wp_parse_args( $row, $this->get_defaultSectionFields() );

								$child_result = $this->Sect->action_handler( 'save', $row );
								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}
							}
						}

						if( $succ && $detail )
						{
							foreach( $detail as $i => $row )
							{
								unset( $row['item_id'] );
								$row['mg_id'] = $mg_id;

								$row = wp_parse_args( $row, $this->get_defaultDetailFields() );

								$child_result = $this->Detail->action_handler( 'save', $row );
								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}
							}
						}
					}
					else if( isset( $header[ $this->get_primaryKey() ] ) && $header[ $this->get_primaryKey() ] ) //update
					{	
						$result = $this->Logic->action_handler( $action, $header, $metas );

						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'error', 'error' );
						}
						else
						{
							$outcome['id'][] = $result['id'];
							$mg_id = $result['id'];
						}

						//Section
						$item_ids = [];
						$exists = $this->Sect->get_infos( [ 'mg_id'=>$mg_id, 'status'=>1 ] );
						if( $exists )
						{
							foreach( $exists as $exist )
							{	
								$item_ids[] = $exist['id'];
							}
						}

						if( $succ && $element )
						{
							$items = array();
							foreach( $element as $i => $row )
							{
								$row['mg_id'] = $mg_id;

								$row['id'] = $row['item_id'];
								unset( $row['item_id'] );

								if( ! $row['id'] || ! in_array( $row['id'], $item_ids ) )		//save
								{
									$row = wp_parse_args( $row, $this->get_defaultSectionFields() );

									$child_result = $this->Sect->action_handler( 'save', $row );
								}
								else if( $row['id'] && in_array( $row['id'], $item_ids ) )	//update
								{
									$child_result = $this->Sect->action_handler( 'update', $row );
								}

								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}

								if( $child_result['id'] ) $items[] = $child_result['id'];
							}

							//remove unneeded row
							if( $item_ids && $items )
							{
								foreach( $item_ids as $id )
								{
									if( ! in_array( $id, $items ) )
									{
										$child_result = $this->Sect->action_handler( 'delete', [ 'id' => $id ] );

										if( ! $child_result['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( 'error', 'error' );
											break;
										}
									}
								}
							}
						}

						//Detail
						$item_ids = [];
						$exists = $this->Detail->get_infos( [ 'mg_id'=>$mg_id, 'status'=>1 ] );
						if( $exists )
						{
							foreach( $exists as $exist )
							{	
								$item_ids[] = $exist['id'];
							}
						}

						if( $succ && $detail )
						{
							$items = array();
							foreach( $detail as $i => $row )
							{
								$row['mg_id'] = $mg_id;

								$row['id'] = $row['item_id'];
								unset( $row['item_id'] );

								if( ! $row['id'] || ! in_array( $row['id'], $item_ids ) )		//save
								{
									$row = wp_parse_args( $row, $this->get_defaultDetailFields() );

									$child_result = $this->Detail->action_handler( 'save', $row );
								}
								else if( $row['id'] && in_array( $row['id'], $item_ids ) )	//update
								{
									$child_result = $this->Detail->action_handler( 'update', $row );
								}

								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}

								if( $child_result['id'] ) $items[] = $child_result['id'];
							}

							//remove unneeded row
							if( $item_ids && $items )
							{
								foreach( $item_ids as $id )
								{
									if( ! in_array( $id, $items ) )
									{
										$child_result = $this->Detail->action_handler( 'delete', [ 'id' => $id ] );

										if( ! $child_result['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( 'error', 'error' );
											break;
										}
									}
								}
							}
						}
					}

					if( $succ )
					{
						if( $isSave )
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
				break;
				case "delete":
				case "restore":
					$datas['lupdate_by'] = $user_id;
					$datas['lupdate_at'] = $now;

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$datas['id'] = $id;
							$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							if( $succ ) //delete section
							{
								$outcome['id'][] = $result['id'];

								$args = [ 'mg_id'=>$id ];
								if( $action == 'delete' ) $args['status'] = 1;
								$exists = $this->Sect->get_infos( $args );
								if( $exists )
								{
									foreach( $exists as $i => $row )
									{
										$child_result = $this->Sect->action_handler( $action, [ 'id'=>$row['id'] ] );
										if( ! $child_result['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( 'error', 'error' );
											break;
										}
									}
								}
							}

							if( $succ ) //delete details
							{
								$outcome['id'][] = $result['id'];

								$args = [ 'mg_id'=>$id ];
								if( $action == 'delete' ) $args['status'] = 1;
								$exists = $this->Detail->get_infos( $args );
								if( $exists )
								{
									foreach( $exists as $i => $row )
									{
										$child_result = $this->Detail->action_handler( $action, [ 'id'=>$row['id'] ] );
										if( ! $child_result['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( 'error', 'error' );
											break;
										}
									}
								}
							}

							if( $succ )
							{
								//Doc Stage
								$dat = $result['data'];
								$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
								    'ref_type'	=> $this->section_id,
								    'ref_id'	=> $result['id'],
								    'action'	=> $action,
								    'status'    => $dat['status'],
								    'remark'	=> ( $metas['remark'] )? $metas['remark'] : '',
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
					$datas['lupdate_by'] = $user_id;
					$datas['lupdate_at'] = $now;

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];
					
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$succ = apply_filters( 'wcwh_todo_external_action', $id, $this->section_id, $action, ( $metas['remark'] )? $metas['remark'] : '' );
							if( $succ )
							{
								$status = apply_filters( 'wcwh_get_status', $action );
							
								$datas['flag'] = 0;
								$datas['flag'] = ( $status > 0 )? 1 : ( ( $status < 0 )? -1 : $datas['flag'] );

								$datas['id'] = $id;
								$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}

								if( $succ )
								{
									$outcome['id'][] = $result['id'];

									//Doc Stage
									$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
									    'ref_type'	=> $this->section_id,
									    'ref_id'	=> $result['id'],
									    'action'	=> $action,
									    'status'    => $status,
									    'remark'	=> ( $metas['remark'] )? $metas['remark'] : '',
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

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{	
           		$succ = $this->after_action( $succ, $outcome['id'], $action );
           	}
        }
        catch (\Exception $e) 
        {
            $succ = false;
            if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }
        finally
        {
        	if( $succ )
                if( $transact ) wpdb_end_transaction( true, $this->db_wpdb );
            else 
                if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
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

			$exists = $this->Logic->get_infos( [ 'id' => $id ], [], false );
			$handled = [];
			foreach( $exists as $exist )
			{
				$handled[ $exist['id'] ] = $exist;
			}

			if ( !class_exists( "WCWH_StockMovementWA_Class" ) ) require_once( WCWH_DIR . "/includes/classes/stock-movement-wa.php" );
			$Inst = new WCWH_StockMovementWA_Class();
			
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

				if( $handled[ $ref_id ] )
				{
					$doc = $handled[ $ref_id ];

					$from_month = date( 'Y-m', strtotime( $doc['since'] ) );
        			$to_month = ( $doc['until'] )? date( 'Y-m', strtotime( $doc['until'] ) ) : date( 'Y-m' );

					$month = $from_month;
			        while( $month !== date( 'Y-m', strtotime( $to_month." +1 month" ) ) )
			        {
			            $succ = $Inst->margining_sales_handling( $month, $doc['wh_id'], $handled[ $ref_id ]['type'] );

			            if( ! $succ ) break;

			            $month = date( 'Y-m', strtotime( $month." +1 month" ) );
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
	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Margining" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Margining"
				>
					<?php echo $actions['save'] ?> Margining
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = true )
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
			'sectTpl'	=> $this->tplName['sect'],
			'wh_code'	=> $this->warehouse['code'],
			'matters'	=> $this->matters,
		);
		
		if( $id )
		{
			$filters = [ 'id' => $id ];

			$datas = $this->Logic->get_infos( $filters, [], true, [] );
			if( $datas )
			{
				$filters = [ 'mg_id'=>$id ];
				$arg = [ 'client'=>1, 'usage'=>1 ];
				$datas['elements'] = $this->Sect->get_infos( $filters, [], false, $arg );
				$datas['details'] = $this->Detail->get_infos( $filters, [], false, $arg );
				
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();

				if( $datas['elements'] )
		        {
		        	foreach( $datas['elements'] as $i => $item )
		        	{
		        		$datas['elements'][$i] = $item;
		        		$datas['elements'][$i]['num'] = ($i+1).".";
		        		$datas['elements'][$i]['title'] = $this->matters[ $item['sub_section'] ]['title'];
		        	}
		        }
		        	
		        if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i] = $item;
		        		$datas['details'][$i]['num'] = ($i+1).".";
		        		$datas['details'][$i]['client_name'] = $item['client_code'].' - '.$item['client_name'];
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );

				$args['render_element'] = $Inst->get_listing( [
		        		'num' => '',
		        		'title' => 'Section',
		        	], 
		        	$datas['elements'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );

		        $args['render_detail'] = $Inst->get_listing( [
		        		'num' => '',
		        		'client_name' => 'Client',
		        		'margin' => 'Margin (%)',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/margining-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/margining-form.php', $args );
		}
	}

	public function view_sect()
	{
		do_action( 'wcwh_templating', 'segment/marginingSect-row.php', $this->tplName['sect'], [] );
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/margining-row.php', $this->tplName['row'], [] );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/marginingListing.php" ); 
			$Inst = new WCWH_Margining_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->matters = $this->matters;

			if( $this->warehouse['code'] ) $filters['warehouse_id'] = $this->warehouse['code'];

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$wh = ( $this->warehouse['code'] )? $this->warehouse['code'] : '';
			$count = $this->Logic->count_statuses( $wh );
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, [], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}