<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Repleaceable_Class" ) ) include_once( WCWH_DIR . "/includes/classes/repleaceable.php" ); 

if ( !class_exists( "WCWH_Inventory_Class" ) ) include_once( WCWH_DIR . "/includes/classes/inventory.php" );

if ( !class_exists( "WCWH_Repleaceable_Controller" ) ) 
{

class WCWH_Repleaceable_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_repleaceable";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Repleaceable_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newRepleaceable',
	);

	protected $warehouse = array();
	protected $view_outlet = false;

	public $filters = array();

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_Repleaceable_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );

		$this->Inv = new WCWH_Inventory_Class( $this->db_wpdb );
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

	
	public function view_fragment( $type = 'save', $html='' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'save':
			default:
				if( current_user_cans( [ 'access_wh_repleaceable'] ) && ! $this->view_outlet ):
					echo $html;	
			?>			 
			
				
			<?php
			
				endif;
			break;
		}
	}

	public function save_total($total,$transact=true)
	{
		$this->Notices->reset_operation_notice();
		$outcome = array();
		try
        {
        if( $transact ) wpdb_start_transaction( $this->db_wpdb );
				$option_total = get_option('gt_total',0);
				
				if( $option_total > 0 )
				{
						$option = $total['total'];
						//insert previous total value
						if(get_option('gt_total_prev',0)==get_option('gt_total',0))
						{
							$succ=true;
						}else {
							$succ = update_option('gt_total_prev',get_option('gt_total',0));
						}
						
						if($succ)
						{
							//insert current total value
							if(get_option('gt_total',0)==$option)
							{
								$succ = true;
							}else{
								$succ = update_option('gt_total',$option);
							}
							
						}
					
				}else {

					//insert previous total value
					if(get_option('gt_total_prev',0)==get_option('gt_total',0))
					{
						$succ=true;
					}else {
						$succ = update_option('gt_total_prev',get_option('gt_total',0));
					}

					$option = $total['total'];
					if($succ) $succ = add_option('gt_total', $option);
				}

				if($succ)
				{
					//insert total value input by user
					if(get_option('gt_total_user',0)==$option)
					{
						$succ=true;
					}else {
						$succ = update_option('gt_total_user',$option);
					}
					
				}

				// if($succ)
				// {
				// 	//update egt based on total
				// 	$val = $total['total'] - ($option_total)?get_option('gt_total_prev',0):$option_total;
				// 	$succ = update_option('egt',get_option('egt',0)+$val);
				// }
		}
		catch (\Exception $e) 
		{
			$succ = false;
			if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
		}
		finally
		{
			if( $succ )
				$this->Notices->set_notice( 'success', 'success' );
				if( $transact ) wpdb_end_transaction( true, $this->db_wpdb );
			else 
				if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
		}
		$outcome['succ'] = $succ;
		
		return $outcome;
	}

	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */

	public function view_transaction( $id = 0, $filters = array() )
	{
		$args = [ 'setting'	=> $this->setting, 'section' => $this->section_id ];
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
			if( $args['seller'] > 0 ) $filter['seller'] = $args['seller'];
			$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

			$filter = [
				'product_id' => $id,
				'warehouse_id' => $this->warehouse['code'],
				'strg_id' => ( $filters['strg_id'] )? $filters['strg_id'] : $storage['id'],
				'transact' => $filters['transact'],
			];
			if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];

			$datas = $this->Inv->get_transaction( $filter, [], false, [] );
			if( $datas )
			{	
				include_once( WCWH_DIR."/includes/listing/sub_list/invTransactListing.php" ); 
				$Inst = new WCWH_InvTransact_List();
				$Inst->seller = $args['seller'];
				$Inst->per_page_limit = 10000;
				$Inst->set_args( [ 'off_footer'=>true, 'list_only'=>true ] );

				$Inst->styles = [
					'.plus_sign' => [ 'text-align'=>'center !important' ],
					'.from_qty , .bqty, .bal_qty, .bunit, .unit_price, .total_price, .unit_cost, .total_cost' => [ 'text-align'=>'right !important' ],
				];
				
				$Inst->set_details( $datas );
				$Inst->render();
			}
		}
	}

	public function view_pos_sales( $id = 0, $filters = array() )
	{
		$args = [ 'setting'	=> $this->setting, 'section' => $this->section_id ];
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
			if( $args['seller'] > 0 ) $filter['seller'] = $args['seller'];
			$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );
			
			$from_date = date( 'Y-m-d 00:00:00', strtotime( current_time( 'Y-m-d' )." -1 month" ) );
			$to_date = date( 'Y-m-d 23:59:59', strtotime( current_time( 'Y-m-d' ) ) );
				
			$last_transact = $this->Inv->get_last_sale( $id, $args['seller'] );
			if( $last_transact )
			{
				$from_date = date( 'Y-m-d 00:00:00 ', strtotime( $last_transact['sales_date']." -1 month" ) );
				$to_date = date( 'Y-m-d 23:59:59 ', strtotime( $last_transact['sales_date'] ) );
			}
			
			$filter = [
				'product_id' => $id,
				'warehouse_id' => $this->warehouse['code'],
				'strg_id' => ( $filters['strg_id'] )? $filters['strg_id'] : $storage['id'],
				'from_date' => $from_date,
				'to_date' => $to_date,
			];
			if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
			$datas = $this->Inv->get_pos_sales( $filter, [], false, [] );
			
			if( $datas )
			{	
				include_once( WCWH_DIR."/includes/listing/sub_list/invPosListing.php" ); 
				$Inst = new WCWH_InvPOS_List();
				$Inst->seller = $args['seller'];
				$Inst->per_page_limit = 10000;
				$Inst->set_args( [ 'off_footer'=>true, 'list_only'=>true ] );

				$Inst->styles = [
					'.qty, .weight, .avg_weight, .avg_price, .line_total' => [ 'text-align'=>'right !important' ],
				];
				
				$Inst->set_details( $datas );
				$Inst->render();
			}
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook' 		=> $this->section_id.'_form',
			'action' 	=> 'save',
			'token' 	=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		$data['total'] = get_option('gt_total',0);
		$data['total_prev'] = get_option('gt_total_prev',0);
		$data['total_user'] = get_option('gt_total_user',0); 

		$args['data'] = $data;
		
		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/repleaceable-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/repleaceable-form.php', $args );
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
			if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

			$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
			if( $args['seller'] > 0 ) $filter['seller'] = $args['seller'];
			$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

			include_once( WCWH_DIR."/includes/listing/repleaceableListing.php" ); 
			$Inst = new WCWH_Repleaceable_Listing();
			$Inst->set_args( ['off_footer'=>true, 'list_only' => true ] );
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->seller = $args['seller'];
			$Inst->styles = [
				'.total_in, .total_out, .qty, .allocated_qty, .total_sales_qty, .balance' => [ 
					'text-align'=>'right !important', 
					'font-size'=>'15px !important',
					'color'=> '#000 !important' 
				],
				'.total_in, .total_out, .allocated_qty, .total_sales_qty' => [ 'font-weight'=>'600' ],
				'.qty' => [ 'font-weight'=>'700' ],
				'.balance' => [ 'font-weight'=>'800' ],
				'.subQty' => [ 'font-size' =>'12px' ],
			];
			
			$filters['seller'] = $args['seller'];
			$filters['wh_code'] = $this->warehouse['code'];
			$filters['strg_id'] = !empty( $filters['strg_id'] )? $filters['strg_id'] : $storage['id'];
			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 1;
			
			$Inst->filters = $filters;
			$this->filters = $Inst->filters;

			$Inst->hidden_col = [ 'item_group', 'category', 'store_type', 'ref_by', 'ret_by' ];

			//$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, 
				[ 'group'=>1, 'store'=>1, 'category'=>1, 'brand'=>1, 'inconsistent'=>1, 'converse'=>1, 'ref_rep'=>1], [], $limit 
			);
			$datas = ( $datas )? $datas : array();
			$count = sizeof($datas);
			
			$gt_options = [];
			$gt = $this->Logic->get_gt_option( $filters, $order, false );
			if( $gt )
			foreach( $gt as $j => $value) {
				$gt_options[ $value['option_name'] ]= $value['option_value'];
			}
			//check if egt(dc) is required to show
			$has_egtDC = false; $balance = 0; $egt_dc_item = [];
			foreach ($datas as $i => $row)
			{
				$balance+= $row['qty'] - $row['allocated_qty'];

				$f = [ 'id'=>$row['id'] ];
				if( $filters['seller'] ) $f['seller'] = $filters['seller'];
				$found = apply_filters( 'wcwh_get_item', $f, [], true, [ 'meta'=>[ 'is_returnable', 'calc_egt' ] ] );
				if( $found && $found['is_returnable'] && $found['calc_egt'] )
				{
					$egt_dc_item = $row;
					$has_egtDC = true;
				}
			}
			
			if( $has_egtDC )
			{
				//assign data 
				$total = ( $gt_options['gt_total'] )? $gt_options['gt_total'] : 0;
				
				//amount gt owed by dc
				$egtDc = $total - $balance;
				
				$datas[] = [ 'name'=>$egt_dc_item['name'].'(dc)', 'qty'=>$egtDc, 'allocated_qty'=>0];
				
				//add button
				$html = '<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="'. $this->tplName["new"] .'" 
						data-title="Modify Total" data-modal="wcwhModalForm" 
						data-actions="close|submit" 
						title="Modify Total">
						Modify Total
						<i class="fa fa-plus-circle" aria-hidden="true"></i>
						</button> <br><br>';
				$this->view_fragment('save',$html);
			}
			
			$Inst->set_details( $datas );
			$Inst->render();

			//----------------------------------------Second Listing
			echo "<br><hr><br>";

			if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

			$filter = [ 'wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging' ];
			if( $args['seller'] > 0 ) $filter['seller'] = $args['seller'];
			$storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

			$InstM = new WCWH_Repleaceable_Listing();
			$InstM->set_args( [ 'list_only' => true ] );
			$InstM->set_warehouse( $this->warehouse );
			$InstM->set_section_id( $this->section_id );
			$InstM->seller = $args['seller'];
			$InstM->styles = [
				'.total_in, .total_out, .qty, .allocated_qty, .total_sales_qty, .balance' => [ 
					'text-align'=>'right !important', 
					'font-size'=>'15px !important',
					'color'=> '#000 !important' 
				],
				'.total_in, .total_out, .allocated_qty, .total_sales_qty' => [ 'font-weight'=>'600' ],
				'.qty' => [ 'font-weight'=>'700' ],
				'.balance' => [ 'font-weight'=>'800' ],
				'.subQty' => [ 'font-size' =>'12px' ],
			];
			
			$filters['seller'] = $args['seller'];
			$filters['wh_code'] = $this->warehouse['code'];
			$filters['strg_id'] = !empty( $filters['strg_id'] )? $filters['strg_id'] : $storage['id'];
			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 1;
			
			$InstM->filters = $filters;

			$InstM->hidden_col = [ 'item_group', 'category', 'store_type', 'ref_by', 'ret_by', 'balance' ];

			$order = $InstM->get_data_ordering();
			$limit = $InstM->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, 
				[ 'group'=>1, 'store'=>1, 'category'=>1, 'brand'=>1, 'inconsistent'=>1, 'converse'=>1, 'not_ref'=>1], [], $limit 
			);
			$datas = ( $datas )? $datas : array();
			
			$InstM->set_details( $datas );
			$InstM->prepare_items();
			$InstM->display();
		?>
		</form>
		<?php
	}
	
} //class

}