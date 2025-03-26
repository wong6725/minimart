<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_StockMovement_Class" ) ) include_once( WCWH_DIR . "/includes/classes/stock-movement.php" ); 
if ( !class_exists( "WCWH_StockMovementWA_Class" ) ) include_once( WCWH_DIR . "/includes/classes/stock-movement-wa.php" ); 

if ( !class_exists( "WCWH_StockMovement_Rpt" ) ) 
{
	
class WCWH_StockMovement_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "StockMovement";

	public $Logic;

	public $tplName = array(
		'export' => 'exportMovement',
	);
	
	protected $tables = array();
	protected $dbname = '';

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public $def_export_title = [];

	private $system_begin_date = '2020-09-01 00:00:00';

	public $GIType = array(
		'delivery_order'	=> 'Delivery Order',
		'reprocess'			=> 'Reprocess',
		'own_use'			=> 'Company Use',
		'vending_machine'	=> 'Vending Machine',
		'block_stock'		=> 'Block Stock',
		'transfer_item'		=> 'Transfer Item',
		'good_return'		=> 'Goods Return',
		'returnable'		=> 'Replaceable',
		'other'				=> 'Other',
	);

	public $need_margining;

	public $def_date_type = 'post_date';

	public function __construct()//	G0001185, G0001245, G0000795, G0000473, EGT, 
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_db_tables();

		$this->need_margining = ( $this->setting['general']['use_margining'] )? true : false;
		//$this->need_margining = false;

		$this->Logic = new WCWH_StockMovementWA_Class( $this->db_wpdb );
	}
	
	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"document"		=> $prefix."document",
			"document_items"=> $prefix."document_items",
			"document_meta"	=> $prefix."document_meta",

			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",

			"client"		=> $prefix."client",
			"clientmeta"	=> $prefix."clientmeta",
			"client_tree"	=> $prefix."client_tree",

			"supplier"		=> $prefix."supplier",
			"suppliermeta"	=> $prefix."suppliermeta",
			"supplier_tree"	=> $prefix."supplier_tree",

			"items"			=> $prefix."items",
			"itemsmeta"		=> $prefix."itemsmeta",
			"item_group"	=> $prefix."item_group",
			"uom"			=> $prefix."uom",
			"reprocess_item"=> $prefix."reprocess_item",
			"item_converse"	=> $prefix."item_converse",

			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
			"status"		=> $prefix."status",

			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",

			"stock_movement"=> $prefix."stock_movement_wa",
			"fifo_movement"	=> $prefix."stock_movement",
			"temp_sm"		=> "temp_stock_movement",

			"margining"			=> $prefix."margining",
			"margining_sect"	=> $prefix."margining_sect",
			"margining_det"		=> $prefix."margining_det",
			"margining_sales"	=> $prefix."margining_sales",
		);
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$isSave = false;
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":
					switch( $datas['export_type'] )
					{
						case 'stock_movement':
							$datas['filename'] = 'Stock Movement ';
						break;
						case 'fifo_movement':
							$datas['filename'] = 'FIFO Movement ';
						break;
						case 'movement_summary':
							$datas['filename'] = 'Movement Summary ';
						break;
						case 'stock_in':
							$datas['filename'] = 'Stock In ';
						break;
						case 'stock_out':
							$datas['filename'] = 'Stock Out ';
						break;
						case 'adjustment':
							$datas['filename'] = 'Stock Adjustment ';
						break;
					}
					
					$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					if( $datas['from_date'] ) $datas['filename'].= " ".date( 'Y-m-d', strtotime( $datas['from_date'] ) );
					if( $datas['to_date'] )  $datas['filename'].= " - ".date( 'Y-m-d', strtotime( $datas['to_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['month'] ) ) $params['month'] = date( 'Y-m', strtotime( $datas['month'] ) );
					if( !empty( $datas['from_month'] ) ) $params['from_month'] = date( 'Y-m', strtotime( $datas['from_month'] ) );
					if( !empty( $datas['to_month'] ) ) $params['to_month'] = date( 'Y-m', strtotime( $datas['to_month'] ) );
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['date_type'] ) ) $params['date_type'] = $datas['date_type'];
					if( !empty( $datas['group'] ) ) $params['group'] = $datas['group'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['supplier'] ) ) $params['supplier'] = $datas['supplier'];
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['doc_type'] ) ) $params['doc_type'] = $datas['doc_type'];
					if( !empty( $datas['good_issue_type'] ) ) $params['good_issue_type'] = $datas['good_issue_type'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					if( !empty( $datas['grouping'] ) ) $params['grouping'] = $datas['grouping'];
					if( !empty( $datas['follow_dc'] ) ) $params['follow_dc'] = $datas['follow_dc'];
					if( !empty( $datas['inconsistent_unit'] ) ) $params['inconsistent_unit'] = $datas['inconsistent_unit'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
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


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		if( $this->def_export_title )
			$default_column['title'] = $this->def_export_title;

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		$type = $params['export_type'];
		unset( $params['export_type'] );
		
		switch( $type )
		{
			case 'stock_movement':
				return $this->get_stock_movement_report( $params, [], [ 'type'=>'export' ] );
			break;
			case 'movement_summary':
				return $this->get_movement_summary_report( $params, [], [ 'type'=>'export' ] );
			break;
			case 'fifo_movement':
				return $this->get_stock_movement_report( $params, [], [ 'type'=>'export', 'use_old'=>1 ] );
			break;
			case 'stock_in':
				return $this->get_stock_in_report( $params );
			break;
			case 'stock_out':
				return $this->get_stock_out_report( $params );
			break;
			case 'adjustment':
				return $this->get_adjustment_report( $params );
			break;
		}
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_latest()
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( ! $this->seller ) return;

		$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true );
		if( ! $curr_wh || $curr_wh['indication'] ) return;
		
		$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
		$dbname = ( $dbname )? $dbname."." : "";

		$cond = $wpdb->prepare( "AND post_type = %s AND post_status = %s ", "pos_temp_register_or", "publish" );
		$ord = "ORDER BY post_date DESC ";
		$l = "LIMIT 0,1 ";
		$sql = "SELECT * FROM {$dbname}{$wpdb->posts} WHERE 1 {$cond} {$ord} {$l}";
		$result = $wpdb->get_row( $sql , ARRAY_A );

		if( $result )
		{
			$now = strtotime( current_time( 'mysql' ) );
			$latest_record = strtotime( $result['post_date'] );
			$max_diff_sec = 86400; //1day
			if( (int)$now - (int)$latest_record >= 86400 )
			{
				echo "<span class='required toolTip' title='Data delayed for more than 24 hours, data might failed to sync back from site.'>Latest data: {$result['post_date']}</span>";
			}
			else
			{
				echo "<span class='toolTip' title='Latest site data found.'>Latest data: {$result['post_date']}</span>";
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
			case 'export':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Report" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Report"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = 'summary' )
	{
		$action_id = 'movement_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
			'GIType'	=> $this->GIType,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'stock_movement':
				do_action( 'wcwh_templating', 'report/export-stock_movement-report.php', $this->tplName['export'], $args );
			break;
			case 'movement_summary':
				do_action( 'wcwh_templating', 'report/export-movement_summary-report.php', $this->tplName['export'], $args );
			break;
			case 'fifo_movement':
				do_action( 'wcwh_templating', 'report/export-stock_movement-report.php', $this->tplName['export'], $args );
			break;
			case 'stock_in':
				do_action( 'wcwh_templating', 'report/export-stock_move_in-report.php', $this->tplName['export'], $args );
			break;
			case 'stock_out':
				do_action( 'wcwh_templating', 'report/export-stock_move_out-report.php', $this->tplName['export'], $args );
			break;
			case 'adjustment':
				do_action( 'wcwh_templating', 'report/export-stock_adjustment-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	/**
	 *	Stock Movement
	 */
	public $list_diff = false;
	public function stock_movement_report( $filters = array(), $order = array() )
	{
		$action_id = 'stock_movement_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockMovementList.php" ); 
			$Inst = new WCWH_StockMovement_Report();
			$Inst->seller = $this->seller;
			
			$month = current_time( 'Y-m' );
			
			$filters['month'] = empty( $filters['month'] )? $month : $filters['month'];
			
			$filters['month'] = date( 'Y-m', strtotime( $filters['month'] ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.wcwh-page .widefat td, .wcwh-page .widefat th' => [ 'font-size' => '10px', 'padding' => '1.5px' ],
				'thead th' => [ 'top'=>'68px !important' ],
				'thead .thead th' => [ 'top'=>'50px !important', 'text-align'=>'center' ],
				'.op_qty, .op_amt, .df_qty, .df_amt, .gr_qty, .gr_amt, .other_in_qty, .other_in_amt
					, .so_qty, .so_sale, .so_adj, .so_amt, .pos_qty, .pos_uom_qty, .pos_mtr, .pos_sale, .pos_amt, .other_out_qty, .other_out_amt
					, .adj_qty, .adj_amt, .closing_qty, .closing_amt, .profit' => [ 'text-align'=>'right !important' ],
				'thead .closing_qty, thead .closing_amt, thead .profit' => [ 'text-align'=>'right !important' ],
				'#closing_qty a span, #closing_amt a span, #profit a span' => [ 'float'=>'right' ],

				'#no' => [ 'width'=>'38px' ],
				'#op_qty, .op_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#df_qty, .df_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#gr_qty, .gr_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#other_in_qty, .other_in_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#so_qty, .so_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#pos_qty, .pos_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#other_out_qty, .other_out_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#adj_qty, .adj_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#closing_qty, .closing_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#profit, .profit' => [ 'border-left' => '1px solid #ccd0d4' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_stock_movement_report( $filters, $order, [ 'type'=>'listing' ] );
				$datas = ( $datas )? $datas : array();
			}

			$Inst->show_diff = $this->list_diff;
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Movement Summary
	 */
	public function movement_summary_report( $filters = array(), $order = array() )
	{
		$action_id = 'movement_summary_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockMSummaryList.php" ); 
			$Inst = new WCWH_Movement_Summary_Report();
			$Inst->seller = $this->seller;
			
			$month = current_time( 'Y-m' );
			$from = date( 'Y-m', strtotime( date( $month )." -12 month" ) );
			
			$filters['from_month'] = empty( $filters['from_month'] )? $from : $filters['from_month'];
			$filters['to_month'] = empty( $filters['to_month'] )? $month : $filters['to_month'];

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.wcwh-page .widefat td, .wcwh-page .widefat th' => [ 'font-size' => '10px', 'padding' => '1.5px' ],
				'thead th' => [ 'top'=>'68px !important' ],
				'thead .thead th' => [ 'top'=>'50px !important', 'text-align'=>'center' ],
				'.op_qty, .op_amt, .gr_qty, .gr_amt, .other_in_qty, .other_in_amt
					, .so_qty, .so_sale, .so_adj, .so_amt, .pos_qty, .pos_uom_qty, .pos_mtr, .pos_sale, .pos_amt, .other_out_qty, .other_out_amt
					, .adj_qty, .adj_amt, .closing_qty, .closing_amt, .profit' => [ 'text-align'=>'right !important' ],
				'thead .closing_qty, thead .closing_amt, thead .profit' => [ 'text-align'=>'right !important' ],
				'#closing_qty a span, #closing_amt a span, #profit a span' => [ 'float'=>'right' ],

				'#no' => [ 'width'=>'38px' ],
				'#op_qty, .op_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#gr_qty, .gr_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#other_in_qty, .other_in_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#so_qty, .so_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#pos_qty, .pos_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#other_out_qty, .other_out_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#adj_qty, .adj_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#closing_qty, .closing_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#profit, .profit' => [ 'border-left' => '1px solid #ccd0d4' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_movement_summary_report( $filters, $order, [ 'type'=>'listing' ] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	FIFO Movement
	 */
	public function fifo_movement_report( $filters = array(), $order = array() )
	{
		$action_id = 'fifo_movement_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockMovementList.php" ); 
			$Inst = new WCWH_StockMovement_Report();
			$Inst->seller = $this->seller;
			
			$month = current_time( 'Y-m' );
			
			$filters['month'] = empty( $filters['month'] )? $month : $filters['month'];
			
			$filters['month'] = date( 'Y-m', strtotime( $filters['month'] ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.wcwh-page .widefat td, .wcwh-page .widefat th' => [ 'font-size' => '10px', 'padding' => '1.5px' ],
				'thead th' => [ 'top'=>'68px !important' ],
				'thead .thead th' => [ 'top'=>'50px !important', 'text-align'=>'center' ],
				'.op_qty, .op_amt, .gr_qty, .gr_amt, .other_in_qty, .other_in_amt
					, .so_qty, .so_sale, .so_adj, .so_amt, .pos_qty, .pos_uom_qty, .pos_mtr, .pos_sale, .pos_amt, .other_out_qty, .other_out_amt
					, .adj_qty, .adj_amt, .closing_qty, .closing_amt, .profit' => [ 'text-align'=>'right !important' ],
				'thead .closing_qty, thead .closing_amt, thead .profit' => [ 'text-align'=>'right !important' ],
				'#closing_qty a span, #closing_amt a span, #profit a span' => [ 'float'=>'right' ],

				'#no' => [ 'width'=>'38px' ],
				'#op_qty, .op_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#gr_qty, .gr_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#other_in_qty, .other_in_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#so_qty, .so_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#pos_qty, .pos_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#other_out_qty, .other_out_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#adj_qty, .adj_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#closing_qty, .closing_qty' => [ 'border-left' => '1px solid #ccd0d4' ],
				'#profit, .profit' => [ 'border-left' => '1px solid #ccd0d4' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_stock_movement_report( $filters, $order, [ 'type'=>'listing', 'use_old'=>1 ] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Stock In
	 */
	public function stock_move_in_report( $filters = array(), $order = array() )
	{
		$action_id = 'stock_move_in_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockMoveInList.php" ); 
			$Inst = new WCWH_StockMoveIn_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.qty, .metric, .uprice, .total_price' => [ 'text-align'=>'right !important' ],
				'#qty a span, #metric a span, #total_price a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_stock_in_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
	/**
	 *	Stock Out
	 */
	public function stock_move_out_report( $filters = array(), $order = array() )
	{
		$action_id = 'stock_move_out_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockMoveOutList.php" ); 
			$Inst = new WCWH_StockMoveOut_Report();
			$Inst->seller = $this->seller;
			$Inst->GIType = $this->GIType;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.qty, .metric, .ucost, .total_cost, .sprice, .amount, .profit' => [ 'text-align'=>'right !important' ],
				'#qty a span, #metric a span, #total_cost a span, #amount a span, #profit a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_stock_out_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Stock Adjustment
	 */
	public function stock_adjustment_report( $filters = array(), $order = array() )
	{
		$action_id = 'stock_adjustment_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/stockAdjustList.php" ); 
			$Inst = new WCWH_StockAdjust_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.in_qty, .in_metric, .unit_price, .total_price, .out_qty, .out_metric, .unit_cost, .total_cost' => [ 'text-align'=>'right !important' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_adjustment_report( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_stock_movement_report( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( $args['use_old'] ) 
		{
			$this->tables["stock_movement"] = $this->tables["fifo_movement"];
			$this->Logic = new WCWH_StockMovement_Class( $this->db_wpdb );
		}

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
			$this->dbname = $dbname;
			$this->Logic->set_dbname( $dbname );
		}
		if( isset( $filters['seller'] ) )
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
	    else
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	    if( $curr_wh ) $filters['warehouse_id'] = $filters['wh'] = $curr_wh['code'];
		
		//temporary stock_movement datas
		$wh = ''; $strg_id = 0;
		$succ = $this->stock_movement_handler( $filters, $wh, $strg_id );
		if( ! $succ ) return false;

		//-------------------------------------------------------------------------------------------
		$month = date( 'Y-m', strtotime( $filters['month'] ) );
		$prev_month = date( 'Y-m', strtotime( $month." -1 month" ) );

		if( ! $args['use_old'] )
		{
			$fifo_last_month = $this->Logic->get_latest_fifo_movement_month( $wh, $strg_id );
		    if( $fifo_last_month ) $fifo_last_month = date( 'Y-m', strtotime( $fifo_last_month ) );
		    if( strtotime( $fifo_last_month ) >= strtotime( $prev_month ) )
		    {
		    	$this->list_diff = $need_diff = true;
		    }
		} 

		$f = $filters;
		$f['from_date'] = date( 'Y-m-1 00:00:00', strtotime( $month ) );
        $f['to_date'] = date( 'Y-m-t 23:59:59', strtotime( $month ) );
        $f['month'] = date( 'Y-m', strtotime( $month." -1 month" ) );     //prev_month
        $f['wh'] = $wh;
        $f['strg_id'] = $strg_id;

        $mf = $f;
		$mf['margining'] = ( $this->need_margining )? true : false;
		$mfgr = $mf;
		$mfgr['margining_id'] = 'wh_movement_rpt_stock_in';
		$mfso = $mf;
		$mfso['margining_id'] = 'wh_movement_rpt_stock_out';

		//------------------------------------------------------------------

		$union = [];
        $union[] = $this->Logic->get_goods_receipt( $mfgr );	//margining
        $union[] = $this->Logic->get_reprocess( $f );
        $union[] = $this->Logic->get_transfer_item( $f );
        $union[] = $this->Logic->get_do_revise( $f );
        $a = [ 'usage'=>'stock_movement_report' ];
        $union[] = $this->Logic->get_sale_delivery_order( $mfso, false, $a );	//margining
        $union[] = $this->Logic->get_transfer_delivery_order( $f );
        $union[] = $this->Logic->get_good_issue( $f );
        $union[] = $this->Logic->get_good_return( $f );
        $union[] = $this->Logic->get_pos( $f );
        $union[] = $this->Logic->get_pos_transact( $f );
        $union[] = $this->Logic->get_adjustment( $f );
        $a = [ 'table'=>$this->tables['temp_sm'] ];
        $union[] = $this->Logic->get_opening( $f, false, $a );
        if( $need_diff ) $union[] = $this->Logic->get_old_opening( $f, false );

        $union[] = $this->Logic->get_purchase_debit_credit( $f );
        $union[] = $this->Logic->get_sale_debit_credit( $f );

        if( $need_diff ) $df_fld = ", SUM( IFNULL(a.df_qty,0) * IFNULL(ic.base_unit,1) ) AS df_qty, SUM( IFNULL(a.df_mtr,0) ) AS df_mtr, SUM( IFNULL(a.df_amt,0) ) AS df_amt ";

        $fld = "ic.base_id AS product_id
        	, SUM( IFNULL(a.op_qty,0) * IFNULL(ic.base_unit,1) ) AS op_qty, SUM( IFNULL(a.op_mtr,0) ) AS op_mtr, SUM( IFNULL(a.op_amt,0) ) AS op_amt 
        	, SUM( IFNULL(a.qty,0) * IFNULL(ic.base_unit,1) ) AS qty, SUM( IFNULL(a.mtr,0) ) AS mtr, SUM( IFNULL(a.amt,0) ) AS amt {$df_fld}
            , SUM( IFNULL(a.gr_qty,0) * IFNULL(ic.base_unit,1) ) AS gr_qty, SUM( IFNULL(a.gr_mtr,0) ) AS gr_mtr, SUM( IFNULL(a.gr_amt,0) ) AS gr_amt
            , SUM( IFNULL(a.rp_qty,0) * IFNULL(ic.base_unit,1) ) AS rp_qty, SUM( IFNULL(a.rp_mtr,0) ) AS rp_mtr, SUM( IFNULL(a.rp_amt,0) ) AS rp_amt
            , SUM( IFNULL(a.ti_qty,0) * IFNULL(ic.base_unit,1) ) AS ti_qty, SUM( IFNULL(a.ti_mtr,0) ) AS ti_mtr, SUM( IFNULL(a.ti_amt,0) ) AS ti_amt
            , SUM( IFNULL(a.dr_qty,0) * IFNULL(ic.base_unit,1) ) AS dr_qty, SUM( IFNULL(a.dr_mtr,0) ) AS dr_mtr, SUM( IFNULL(a.dr_amt,0) ) AS dr_amt
            , SUM( IFNULL(a.so_qty,0) * IFNULL(ic.base_unit,1) ) AS so_qty, SUM( IFNULL(a.so_mtr,0) ) AS so_mtr, SUM( IFNULL(a.so_amt,0) ) AS so_amt, SUM( IFNULL(a.so_sale,0) ) AS so_sale, SUM( IFNULL(a.so_adj,0) ) AS so_adj
            , SUM( IFNULL(a.to_qty,0) * IFNULL(ic.base_unit,1) ) AS to_qty, SUM( IFNULL(a.to_mtr,0) ) AS to_mtr, SUM( IFNULL(a.to_amt,0) ) AS to_amt
            , SUM( IFNULL(a.gi_qty,0) * IFNULL(ic.base_unit,1) ) AS gi_qty, SUM( IFNULL(a.gi_mtr,0) ) AS gi_mtr, SUM( IFNULL(a.gi_amt,0) ) AS gi_amt
            , SUM( IFNULL(a.gt_qty,0) * IFNULL(ic.base_unit,1) ) AS gt_qty, SUM( IFNULL(a.gt_mtr,0) ) AS gt_mtr, SUM( IFNULL(a.gt_amt,0) ) AS gt_amt
            , SUM( IFNULL(a.pos_qty,0) * IFNULL(ic.base_unit,1) ) AS pos_qty, SUM( IFNULL(a.pos_uom_qty,0) * IFNULL(ic.base_unit,1) ) AS pos_uom_qty
            , SUM( IFNULL(a.pos_mtr,0) ) AS pos_mtr, SUM( IFNULL(a.pos_sale,0) ) AS pos_sale
            , SUM( IFNULL(a.adj_qty,0) * IFNULL(ic.base_unit,1) ) AS adj_qty, SUM( IFNULL(a.adj_mtr,0) ) AS adj_mtr, SUM( IFNULL(a.adj_amt,0) ) AS adj_amt ";

        $tbl.= "( ";	
        if( $union ) $tbl.= "( ".implode( " ) UNION ALL ( ", $union )." )";
        $tbl.= ") a ";
        $tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.product_id ";

        $cond = "";
        $grp = "GROUP BY ic.base_id ";
        $ord = "ORDER BY ic.base_id ASC ";

        $dat_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

        //==============================================================================================
        $this->def_export_title = [ 
			'Item ID', 'Category Code', 'Category Name', 'Product Code', 'UOM', 'Metric Item'
			, 'Open Qty+', 'Open Metric', 'Open Amt', 'Diff Qty', 'Diff Metric', 'Diff Amt'
			, 'GR Qty+', 'GR Metric', 'GR Amt'
			, 'RP Qty+', 'RP Metric', 'RP Amt'
			, 'TI Qty+', 'TI Metric', 'TI Amt'
			, 'DO R Qty+', 'DO R Metric', 'DO R Amt'
			, 'SO Qty-', 'SO Metric', 'SO Sales', 'SO Sales Adj', 'SO Amt'
			, 'POS Sale Qty-', 'POS Sales', 'POS Qty', 'POS Metric', 'POS Amt'
			, 'TO Qty-', 'TO Metric', 'TO Amt'
			, 'GI Qty-', 'GI Metric', 'GI Amt'
			, 'GT Qty-', 'GT Metric', 'GT Amt'
			, 'ADJ Qty-', 'ADJ Metric', 'ADJ Amt'
			, 'Closing Qty', 'Closing Metric', 'Closing Amt', 'Profit'
		];

		if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) 
		{
			$prdt = ", i.name AS prdt_name ";

			$this->def_export_title = [ 
				'Item ID', 'Category Code', 'Category Name', 'Product Code', 'Product Name', 'UOM', 'Metric Item'
				, 'Open Qty+', 'Open Metric', 'Open Amt', 'Diff Qty', 'Diff Metric', 'Diff Amt'
				, 'GR Qty+', 'GR Metric', 'GR Amt'
				, 'RP Qty+', 'RP Metric', 'RP Amt'
				, 'TI Qty+', 'TI Metric', 'TI Amt'
				, 'DO R Qty+', 'DO R Metric', 'DO R Amt'
				, 'SO Qty-', 'SO Metric', 'SO Sales', 'SO Sales Adj', 'SO Amt'
				, 'POS Sale Qty-', 'POS Sales', 'POS Qty', 'POS Metric', 'POS Amt'
				, 'TO Qty-', 'TO Metric', 'TO Amt'
				, 'GI Qty-', 'GI Metric', 'GI Amt'
				, 'GT Qty-', 'GT Metric', 'GT Amt'
				, 'ADJ Qty-', 'ADJ Metric', 'ADJ Amt'
				, 'Closing Qty', 'Closing Metric', 'Closing Amt', 'Profit'
			];
		}

		if( ! $need_diff ) $this->def_export_title = array_diff( $this->def_export_title, [ 'Diff Qty', 'Diff Metric', 'Diff Amt' ] );
		if( $need_diff ) $dif_fld = ", ROUND( IFNULL(a.df_qty,0), 2 ) AS df_qty, ROUND( IFNULL(a.df_mtr,0), 3 ) AS df_mtr, ROUND( IFNULL(a.df_amt,0), 2 ) AS df_amt";

		$fld = "a.product_id, cat.slug AS category_code, cat.name AS category_name
			, i.code AS prdt_code{$prdt}, i._uom_code AS uom, IF(kg.meta_value, 'YES', 'NO') AS metriced
		    , ROUND( IFNULL(a.op_qty,0), 2 ) AS op_qty, ROUND( IFNULL(a.op_mtr,0), 3 ) AS op_mtr, ROUND( IFNULL(a.op_amt,0), 2 ) AS op_amt {$dif_fld}
			, ROUND( IFNULL(a.gr_qty,0), 2 ) AS gr_qty, ROUND( IFNULL(a.gr_mtr,0), 3 ) AS gr_mtr, ROUND( IFNULL(a.gr_amt,0), 2 ) AS gr_amt
		    , ROUND( IFNULL(a.rp_qty,0), 2 ) AS rp_qty, ROUND( IFNULL(a.rp_mtr,0), 3 ) AS rp_mtr, ROUND( IFNULL(a.rp_amt,0), 2 ) AS rp_amt
		    , ROUND( IFNULL(a.ti_qty,0), 2 ) AS ti_qty, ROUND( IFNULL(a.ti_mtr,0), 3 ) AS ti_mtr, ROUND( IFNULL(a.ti_amt,0), 2 ) AS ti_amt
		    , ROUND( IFNULL(a.dr_qty,0), 2 ) AS dr_qty, ROUND( IFNULL(a.dr_mtr,0), 3 ) AS dr_mtr, ROUND( IFNULL(a.dr_amt,0), 2 ) AS dr_amt
		    , ROUND( IFNULL(a.so_qty,0), 2 ) AS so_qty, ROUND( IFNULL(a.so_mtr,0), 3 ) AS so_mtr
		    , ROUND( IFNULL(a.so_sale,0), 2 ) AS so_sale, ROUND( IFNULL(a.so_adj,0), 2 ) AS so_adj, ROUND( IFNULL(a.so_amt,0), 2 ) AS so_amt
		    , ROUND( IFNULL(a.pos_qty,0), 2 ) AS pos_qty, ROUND( IFNULL(a.pos_sale,0), 2 ) AS pos_sale
		    , ROUND( IFNULL(a.pos_uom_qty,0), 2 ) AS pos_uom_qty, ROUND( IFNULL(a.pos_mtr,0), 3 ) AS pos_mtr
		    , @pos_amt:= ROUND( IF( IFNULL(a.pos_uom_qty,0) > 0, 
				IF( IFNULL(a.op_qty,0)+IFNULL(a.gr_qty,0)+IFNULL(a.rp_qty,0)+IFNULL(a.ti_qty,0)+IFNULL(a.dr_qty,0)-IFNULL(a.so_qty,0)-IFNULL(a.to_qty,0)-IFNULL(a.gi_qty,0)-IFNULL(a.gt_qty,0)+IFNULL(a.adj_qty,0) > IFNULL(a.pos_uom_qty,0), 
					( IFNULL(a.op_amt,0)+IFNULL(a.gr_amt,0)+IFNULL(a.rp_amt,0)+IFNULL(a.ti_amt,0)+IFNULL(a.dr_amt,0)-IFNULL(a.so_amt,0)-IFNULL(a.to_amt,0)-IFNULL(a.gi_amt,0)-IFNULL(a.gt_amt,0)+IFNULL(a.adj_amt,0) ) / 
					( IFNULL(a.op_qty,0)+IFNULL(a.gr_qty,0)+IFNULL(a.rp_qty,0)+IFNULL(a.ti_qty,0)+IFNULL(a.dr_qty,0)-IFNULL(a.so_qty,0)-IFNULL(a.to_qty,0)-IFNULL(a.gi_qty,0)-IFNULL(a.gt_qty,0)+IFNULL(a.adj_qty,0) ) * IFNULL(a.pos_uom_qty,0), 
					IFNULL(a.op_amt,0)+IFNULL(a.gr_amt,0)+IFNULL(a.rp_amt,0)+IFNULL(a.ti_amt,0)+IFNULL(a.dr_amt,0)-IFNULL(a.so_amt,0)-IFNULL(a.to_amt,0)-IFNULL(a.gi_amt,0)-IFNULL(a.gt_amt,0)+IFNULL(a.adj_amt,0) 
				), 0 ), 2 ) AS pos_amt
		    , ROUND( IFNULL(a.to_qty,0), 2 ) AS to_qty, ROUND( IFNULL(a.to_mtr,0), 3 ) AS to_mtr, ROUND( IFNULL(a.to_amt,0), 2 ) AS to_amt
		    , ROUND( IFNULL(a.gi_qty,0), 2 ) AS gi_qty, ROUND( IFNULL(a.gi_mtr,0), 3 ) AS gi_mtr, ROUND( IFNULL(a.gi_amt,0), 2 ) AS gi_amt
		    , ROUND( IFNULL(a.gt_qty,0), 2 ) AS gt_qty, ROUND( IFNULL(a.gt_mtr,0), 3 ) AS gt_mtr, ROUND( IFNULL(a.gt_amt,0), 2 ) AS gt_amt
		    , ROUND( IFNULL(a.adj_qty,0), 2 ) AS adj_qty, ROUND( IFNULL(a.adj_mtr,0), 3 ) AS adj_mtr, ROUND( IFNULL(a.adj_amt,0), 2 ) AS adj_amt
		    , ROUND( IFNULL(a.op_qty,0) + IFNULL(a.gr_qty,0) + IFNULL(a.rp_qty,0) + IFNULL(a.ti_qty,0) + IFNULL(a.dr_qty,0) - IFNULL(a.so_qty,0) - IFNULL(a.to_qty,0) - IFNULL(a.gi_qty,0) - IFNULL(a.gt_qty,0) - IFNULL(a.pos_uom_qty,0) + IFNULL(a.adj_qty,0), 2 ) AS closing_qty
			, ROUND( IFNULL(a.op_mtr,0) + IFNULL(a.gr_mtr,0) + IFNULL(a.rp_mtr,0) + IFNULL(a.ti_mtr,0) + IFNULL(a.dr_mtr,0) - IFNULL(a.so_mtr,0) - IFNULL(a.to_mtr,0) - IFNULL(a.gi_mtr,0) - IFNULL(a.gt_mtr,0) - IFNULL(a.pos_mtr,0) + IFNULL(a.adj_mtr,0), 3 ) AS closing_mtr
			, ROUND( 
				IF( IFNULL(a.op_qty,0)+IFNULL(a.gr_qty,0)+IFNULL(a.rp_qty,0)+IFNULL(a.ti_qty,0)+IFNULL(a.dr_qty,0)-IFNULL(a.so_qty,0)-IFNULL(a.to_qty,0)-IFNULL(a.gi_qty,0)-IFNULL(a.gt_qty,0)-IFNULL(a.pos_uom_qty,0)+IFNULL(a.adj_qty,0) = 0 AND 
					ABS( IF( a.op_amt != 0, a.op_amt, a.op_amt )+IFNULL(a.gr_amt,0)+IFNULL(a.rp_amt,0)+IFNULL(a.ti_amt,0)+IFNULL(a.dr_amt,0)-IFNULL(a.so_amt,0)-IFNULL(a.to_amt,0)-IFNULL(a.gi_amt,0)-IFNULL(a.gt_amt,0)-IFNULL(@pos_amt,0)+IFNULL(a.adj_amt,0) ) < 0.5, 
					0,
					IF( a.op_amt != 0, a.op_amt, a.op_amt )+IFNULL(a.gr_amt,0)+IFNULL(a.rp_amt,0)+IFNULL(a.ti_amt,0)+IFNULL(a.dr_amt,0)-IFNULL(a.so_amt,0)-IFNULL(a.to_amt,0)-IFNULL(a.gi_amt,0)-IFNULL(a.gt_amt,0)-IFNULL(@pos_amt,0)+IFNULL(a.adj_amt,0) 
				), 2 ) AS closing_amt
			, ROUND( IFNULL(a.so_sale,0) + IFNULL(a.so_adj,0) + IFNULL(a.pos_sale,0) - IFNULL(a.so_amt,0) - IFNULL(@pos_amt,0), 2 ) AS profit ";

		if( $args['type'] == 'listing' )
		{
			$fld = "a.product_id, cat.slug AS category_code, cat.name AS category_name
				, i.code AS prdt_code{$prdt}, i._uom_code AS uom, IF(kg.meta_value, 'YES', 'NO') AS metriced
			    , ROUND( IFNULL(a.op_qty,0), 2 ) AS op_qty, ROUND( IFNULL(a.op_mtr,0), 3 ) AS op_mtr, ROUND( IFNULL(a.op_amt,0), 2 ) AS op_amt 
			    {$dif_fld}
			    , ROUND( IFNULL(a.gr_qty,0), 2 ) AS gr_qty, ROUND( IFNULL(a.gr_mtr,0), 3 ) AS gr_mtr, ROUND( IFNULL(a.gr_amt,0), 2 ) AS gr_amt
			    , ROUND( IFNULL(a.rp_qty,0) + IFNULL(a.ti_qty,0) + IFNULL(a.dr_qty,0), 2 ) AS other_in_qty
			    , ROUND( IFNULL(a.rp_mtr,0) + IFNULL(a.ti_mtr,0) + IFNULL(a.dr_mtr,0), 3 ) AS other_in_mtr
			    , ROUND( IFNULL(a.rp_amt,0) + IFNULL(a.ti_amt,0) + IFNULL(a.dr_amt,0), 2 ) AS other_in_amt
			    , ROUND( IFNULL(a.so_qty,0), 2 ) AS so_qty, ROUND( IFNULL(a.so_mtr,0), 3 ) AS so_mtr
			    , ROUND( IFNULL(a.so_sale,0), 2 ) AS so_sale, ROUND( IFNULL(a.so_adj,0), 2 ) AS so_adj, ROUND( IFNULL(a.so_amt,0), 2 ) AS so_amt
			    , ROUND( IFNULL(a.pos_qty,0), 2 ) AS pos_qty, ROUND( IFNULL(a.pos_sale,0), 2 ) AS pos_sale
			    , ROUND( IFNULL(a.pos_uom_qty,0), 2 ) AS pos_uom_qty, ROUND( IFNULL(a.pos_mtr,0), 3 ) AS pos_mtr
			    , @pos_amt:= ROUND( IF( IFNULL(a.pos_uom_qty,0) > 0, 
					IF( IFNULL(a.op_qty,0)+IFNULL(a.gr_qty,0)+IFNULL(a.rp_qty,0)+IFNULL(a.ti_qty,0)+IFNULL(a.dr_qty,0)-IFNULL(a.so_qty,0)-IFNULL(a.to_qty,0)-IFNULL(a.gi_qty,0)-IFNULL(a.gt_qty,0)+IFNULL(a.adj_qty,0) > IFNULL(a.pos_uom_qty,0), 
						( IFNULL(a.op_amt,0)+IFNULL(a.gr_amt,0)+IFNULL(a.rp_amt,0)+IFNULL(a.ti_amt,0)+IFNULL(a.dr_amt,0)-IFNULL(a.so_amt,0)-IFNULL(a.to_amt,0)-IFNULL(a.gi_amt,0)-IFNULL(a.gt_amt,0)+IFNULL(a.adj_amt,0) ) / 
						( IFNULL(a.op_qty,0)+IFNULL(a.gr_qty,0)+IFNULL(a.rp_qty,0)+IFNULL(a.ti_qty,0)+IFNULL(a.dr_qty,0)-IFNULL(a.so_qty,0)-IFNULL(a.to_qty,0)-IFNULL(a.gi_qty,0)-IFNULL(a.gt_qty,0)+IFNULL(a.adj_qty,0) ) * IFNULL(a.pos_uom_qty,0), 
						IFNULL(a.op_amt,0)+IFNULL(a.gr_amt,0)+IFNULL(a.rp_amt,0)+IFNULL(a.ti_amt,0)+IFNULL(a.dr_amt,0)-IFNULL(a.so_amt,0)-IFNULL(a.to_amt,0)-IFNULL(a.gi_amt,0)-IFNULL(a.gt_amt,0)+IFNULL(a.adj_amt,0) 
					), 0 ), 2 ) AS pos_amt
			    , ROUND( IFNULL(a.to_qty,0) + IFNULL(a.gi_qty,0) + IFNULL(a.gt_qty,0), 2 ) AS other_out_qty
			    , ROUND( IFNULL(a.to_mtr,0) + IFNULL(a.gi_mtr,0) + IFNULL(a.gt_mtr,0), 3 ) AS other_out_mtr
			    , ROUND( IFNULL(a.to_amt,0) + IFNULL(a.gi_amt,0) + IFNULL(a.gt_amt,0), 2 ) AS other_out_amt
			    , ROUND( IFNULL(a.adj_qty,0), 2 ) AS adj_qty, ROUND( IFNULL(a.adj_mtr,0), 3 ) AS adj_mtr, ROUND( IFNULL(a.adj_amt,0), 2 ) AS adj_amt
			    , ROUND( IFNULL(a.op_qty,0)+IFNULL(a.gr_qty,0)+IFNULL(a.rp_qty,0)+IFNULL(a.ti_qty,0)+IFNULL(a.dr_qty,0)-IFNULL(a.so_qty,0)-IFNULL(a.to_qty,0)-IFNULL(a.gi_qty,0)-IFNULL(a.gt_qty,0)-IFNULL(a.pos_uom_qty,0)+IFNULL(a.adj_qty,0), 2 ) AS closing_qty
			    , ROUND( IFNULL(a.op_mtr,0)+IFNULL(a.gr_mtr,0)+IFNULL(a.rp_mtr,0)+IFNULL(a.ti_mtr,0)+IFNULL(a.dr_mtr,0)-IFNULL(a.so_mtr,0)-IFNULL(a.to_mtr,0)-IFNULL(a.gi_mtr,0)-IFNULL(a.gt_mtr,0)-IFNULL(a.pos_mtr,0)+IFNULL(a.adj_mtr,0), 3 ) AS closing_mtr
				, ROUND( 
					IF( IFNULL(a.op_qty,0)+IFNULL(a.gr_qty,0)+IFNULL(a.rp_qty,0)+IFNULL(a.ti_qty,0)+IFNULL(a.dr_qty,0)-IFNULL(a.so_qty,0)-IFNULL(a.to_qty,0)-IFNULL(a.gi_qty,0)-IFNULL(a.gt_qty,0)-IFNULL(a.pos_uom_qty,0)+IFNULL(a.adj_qty,0) = 0 AND 
						ABS( IF( a.op_amt != 0, a.op_amt, a.op_amt )+IFNULL(a.gr_amt,0)+IFNULL(a.rp_amt,0)+IFNULL(a.ti_amt,0)+IFNULL(a.dr_amt,0)-IFNULL(a.so_amt,0)-IFNULL(a.to_amt,0)-IFNULL(a.gi_amt,0)-IFNULL(a.gt_amt,0)-IFNULL(@pos_amt,0)+IFNULL(a.adj_amt,0) ) < 0.5, 
						0,
						IF( a.op_amt != 0, a.op_amt, a.op_amt )+IFNULL(a.gr_amt,0)+IFNULL(a.rp_amt,0)+IFNULL(a.ti_amt,0)+IFNULL(a.dr_amt,0)-IFNULL(a.so_amt,0)-IFNULL(a.to_amt,0)-IFNULL(a.gi_amt,0)-IFNULL(a.gt_amt,0)-IFNULL(@pos_amt,0)+IFNULL(a.adj_amt,0) 
					), 2 ) AS closing_amt
				, ROUND( IFNULL(a.so_sale,0) + IFNULL(a.so_adj,0) + IFNULL(a.pos_sale,0) - IFNULL(a.so_amt,0) - IFNULL(@pos_amt,0), 2 ) AS profit ";
		}

        $tbl = "( {$dat_sql} ) a ";
        $tbl.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = a.product_id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['itemsmeta']} kg ON kg.items_id = i.id AND kg.meta_key = 'inconsistent_unit' ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

        $cond = "";
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.id = %d ", $filters['product'] );
		}
		if( isset( $filters['group'] ) )
		{
			if( is_array( $filters['group'] ) )
				$cond.= "AND i.grp_id IN ('" .implode( "','", $filters['group'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.grp_id = %d ", $filters['group'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR cat.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		if( isset( $filters['inconsistent_unit'] ) )
		{
			if( $filters['inconsistent_unit'] )
				$cond.= $wpdb->prepare( "AND kg.meta_value = %s ", $filters['inconsistent_unit'] );
			else
				$cond.= $wpdb->prepare( "AND ( kg.meta_value = %s OR kg.meta_value IS NULL ) ", $filters['inconsistent_unit'] );
		}

		if( $need_diff ) $dif_cond = "OR a.df_qty != 0 ";
		$cond.= "AND ( a.op_qty != 0 OR a.gr_qty != 0 OR a.rp_qty != 0 OR a.ti_qty != 0
			OR a.dr_qty != 0 OR a.so_qty != 0 OR a.pos_qty != 0 OR a.to_qty != 0
			OR a.gi_qty != 0 OR a.gt_qty != 0 OR a.adj_qty != 0 {$dif_cond} ) ";

		$grp = "";

		//order
		if( empty( $order ) )
		{
			$order = [ 'i.code' => 'ASC', 'cat.slug' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		//==============================================================================================

		$drop = "DROP TEMPORARY TABLE {$this->tables['temp_sm']} ";
        $succ = $wpdb->query( $drop );
		
		return $results;
	}

	public function get_movement_summary_report( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
			$this->dbname = $dbname;
			$this->Logic->set_dbname( $dbname );
		}
		if( isset( $filters['seller'] ) )
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
	    else
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	    if( $curr_wh ) $filters['warehouse_id'] = $filters['wh'] = $curr_wh['code'];
		
		//temporary stock_movement datas
		$wh = ''; $strg_id = 0;
		$succ = $this->stock_movement_handler( $filters, $wh, $strg_id );
		if( ! $succ ) return false;

		//-------------------------------------------------------------------------------------------
        $filters['wh'] = $wh;
        $filters['strg_id'] = $strg_id;
		$filters['margining'] = ( $this->need_margining )? true : false;
		$filters['margining_id'] = 'wh_movement_rpt_stock_out';
		//pd($filters,1);
        //==============================================================================================
        $this->def_export_title = [ 
			'Month'
			, 'Open Qty+', 'Open Metric', 'Open Amt'
			, 'GR Qty+', 'GR Metric', 'GR Amt'
			, 'RP Qty+', 'RP Metric', 'RP Amt'
			, 'TI Qty+', 'TI Metric', 'TI Amt'
			, 'DO R Qty+', 'DO R Metric', 'DO R Amt'
			, 'SO Qty-', 'SO Metric', 'SO Sales', 'SO Sales Adj', 'SO Amt'
			, 'POS Sale Qty-', 'POS Sales', 'POS Qty', 'POS Metric', 'POS Amt'
			, 'TO Qty-', 'TO Metric', 'TO Amt'
			, 'GI Qty-', 'GI Metric', 'GI Amt'
			, 'GT Qty-', 'GT Metric', 'GT Amt'
			, 'ADJ Qty-', 'ADJ Metric', 'ADJ Amt'
			, 'Closing Qty', 'Closing Metric', 'Closing Amt', 'Profit'
		];

		if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) 
		{
			$prdt = ", i.name AS prdt_name ";

			$this->def_export_title = [ 
				'Month'
				, 'Open Qty+', 'Open Metric', 'Open Amt'
				, 'GR Qty+', 'GR Metric', 'GR Amt'
				, 'RP Qty+', 'RP Metric', 'RP Amt'
				, 'TI Qty+', 'TI Metric', 'TI Amt'
				, 'DO R Qty+', 'DO R Metric', 'DO R Amt'
				, 'SO Qty-', 'SO Metric', 'SO Sales', 'SO Sales Adj', 'SO Amt'
				, 'POS Sale Qty-', 'POS Sales', 'POS Qty', 'POS Metric', 'POS Amt'
				, 'TO Qty-', 'TO Metric', 'TO Amt'
				, 'GI Qty-', 'GI Metric', 'GI Amt'
				, 'GT Qty-', 'GT Metric', 'GT Amt'
				, 'ADJ Qty-', 'ADJ Metric', 'ADJ Amt'
				, 'Closing Qty', 'Closing Metric', 'Closing Amt', 'Profit'
			];
		}

		$fld = "a.month
, ROUND( SUM(IFNULL(a.op_qty,0)), 2 ) AS op_qty, ROUND( SUM(IFNULL(a.op_mtr,0)), 3 ) AS op_mtr, ROUND( SUM(IFNULL(a.op_amt,0)), 2 ) AS op_amt
, ROUND( SUM(IFNULL(a.gr_qty,0)), 2 ) AS gr_qty, ROUND( SUM(IFNULL(a.gr_mtr,0)), 3 ) AS gr_mtr, ROUND( SUM(IFNULL(a.gr_amt,0)), 2 ) AS gr_amt
, ROUND( SUM(IFNULL(a.rp_qty,0)), 2 ) AS rp_qty, ROUND( SUM(IFNULL(a.rp_mtr,0)), 3 ) AS rp_mtr, ROUND( SUM(IFNULL(a.rp_amt,0)), 2 ) AS rp_amt
, ROUND( SUM(IFNULL(a.ti_qty,0)), 2 ) AS ti_qty, ROUND( SUM(IFNULL(a.ti_mtr,0)), 3 ) AS ti_mtr, ROUND( SUM(IFNULL(a.ti_amt,0)), 2 ) AS ti_amt
, ROUND( SUM(IFNULL(a.dr_qty,0)), 2 ) AS dr_qty, ROUND( SUM(IFNULL(a.dr_mtr,0)), 3 ) AS dr_mtr, ROUND( SUM(IFNULL(a.dr_amt,0)), 2 ) AS dr_amt
, ROUND( SUM(IFNULL(a.so_qty,0)), 2 ) AS so_qty, ROUND( SUM(IFNULL(a.so_mtr,0)), 3 ) AS so_mtr
, ROUND( IFNULL(s.so_sale,0), 2 ) AS so_sale, ROUND( IFNULL(s.so_adj,0), 2 ) AS so_adj
, ROUND( SUM(IFNULL(a.so_amt,0)), 2 ) AS so_amt
, ROUND( SUM(IFNULL(a.pos_qty,0)), 2 ) AS pos_qty, ROUND( SUM(IFNULL(a.pos_sale,0)), 2 ) AS pos_sale
, ROUND( SUM(IFNULL(a.pos_uom_qty,0)), 2 ) AS pos_uom_qty, ROUND( SUM(IFNULL(a.pos_mtr,0)), 3 ) AS pos_mtr
, ROUND( SUM(IFNULL(a.pos_amt,0)), 2 ) AS pos_amt
, ROUND( SUM(IFNULL(a.to_qty,0)), 2 ) AS to_qty, ROUND( SUM(IFNULL(a.to_mtr,0)), 3 ) AS to_mtr, ROUND( SUM(IFNULL(a.to_amt,0)), 2 ) AS to_amt
, ROUND( SUM(IFNULL(a.gi_qty,0)), 2 ) AS gi_qty, ROUND( SUM(IFNULL(a.gi_mtr,0)), 3 ) AS gi_mtr, ROUND( SUM(IFNULL(a.gi_amt,0)), 2 ) AS gi_amt
, ROUND( SUM(IFNULL(a.gt_qty,0)), 2 ) AS gt_qty, ROUND( SUM(IFNULL(a.gt_mtr,0)), 3 ) AS gt_mtr, ROUND( SUM(IFNULL(a.gt_amt,0)), 2 ) AS gt_amt
, ROUND( SUM(IFNULL(a.adj_qty,0)), 2 ) AS adj_qty, ROUND( SUM(IFNULL(a.adj_mtr,0)), 3 ) AS adj_mtr
, ROUND( SUM(IFNULL(a.adj_amt,0)), 2 ) AS adj_amt
, ROUND( SUM(IFNULL(a.closing_qty,0)), 2 ) AS closing_qty, ROUND( SUM(IFNULL(a.closing_mtr,0)), 3 ) AS closing_mtr
, ROUND( SUM(IFNULL(a.closing_amt,0)), 2 ) AS closing_amt
, ROUND( IFNULL(s.so_sale,0) + IFNULL(s.so_adj,0) + SUM(IFNULL(a.pos_sale,0)) - SUM(IFNULL(a.so_amt,0)) - SUM(IFNULL(a.pos_amt,0)), 2 ) AS profit ";

		if( $args['type'] == 'listing' )
		{
			$fld = "a.month
, ROUND( SUM(IFNULL(a.op_qty,0)), 2 ) AS op_qty, ROUND( SUM(IFNULL(a.op_mtr,0)), 3 ) AS op_mtr, ROUND( SUM(IFNULL(a.op_amt,0)), 2 ) AS op_amt
, ROUND( SUM(IFNULL(a.gr_qty,0)), 2 ) AS gr_qty, ROUND( SUM(IFNULL(a.gr_mtr,0)), 3 ) AS gr_mtr, ROUND( SUM(IFNULL(a.gr_amt,0)), 2 ) AS gr_amt
, ROUND( SUM(IFNULL(a.rp_qty,0)) + SUM(IFNULL(a.ti_qty,0)) + SUM(IFNULL(a.dr_qty,0)), 2 ) AS other_in_qty
, ROUND( SUM(IFNULL(a.rp_mtr,0)) + SUM(IFNULL(a.ti_mtr,0)) + SUM(IFNULL(a.dr_mtr,0)), 3 ) AS other_in_mtr
, ROUND( SUM(IFNULL(a.rp_amt,0)) + SUM(IFNULL(a.ti_amt,0)) + SUM(IFNULL(a.dr_amt,0)), 2 ) AS other_in_amt
, ROUND( SUM(IFNULL(a.so_qty,0)), 2 ) AS so_qty, ROUND( SUM(IFNULL(a.so_mtr,0)), 3 ) AS so_mtr
, ROUND( IFNULL(s.so_sale,0), 2 ) AS so_sale, ROUND( IFNULL(s.so_adj,0), 2 ) AS so_adj 
, ROUND( SUM(IFNULL(a.so_amt,0)), 2 ) AS so_amt
, ROUND( SUM(IFNULL(a.pos_qty,0)), 2 ) AS pos_qty, ROUND( SUM(IFNULL(a.pos_sale,0)), 2 ) AS pos_sale
, ROUND( SUM(IFNULL(a.pos_uom_qty,0)), 2 ) AS pos_uom_qty, ROUND( SUM(IFNULL(a.pos_mtr,0)), 3 ) AS pos_mtr
, ROUND( SUM(IFNULL(a.pos_amt,0)), 2 ) AS pos_amt
, ROUND( SUM(IFNULL(a.to_qty,0)) + SUM(IFNULL(a.gi_qty,0)) + SUM(IFNULL(a.gt_qty,0)), 2 ) AS other_out_qty
, ROUND( SUM(IFNULL(a.to_mtr,0)) + SUM(IFNULL(a.gi_mtr,0)) + SUM(IFNULL(a.gt_mtr,0)), 3 ) AS other_out_mtr
, ROUND( SUM(IFNULL(a.to_amt,0)) + SUM(IFNULL(a.gi_amt,0)) + SUM(IFNULL(a.gt_amt,0)), 2 ) AS other_out_amt
, ROUND( SUM(IFNULL(a.adj_qty,0)), 2 ) AS adj_qty, ROUND( SUM(IFNULL(a.adj_mtr,0)), 3 ) AS adj_mtr
, ROUND( SUM(IFNULL(a.adj_amt,0)), 2 ) AS adj_amt
, ROUND( SUM(IFNULL(a.closing_qty,0)), 2 ) AS closing_qty, ROUND( SUM(IFNULL(a.closing_mtr,0)), 3 ) AS closing_mtr
, ROUND( SUM(IFNULL(a.closing_amt,0)), 2 ) AS closing_amt
, ROUND( IFNULL(s.so_sale,0) + IFNULL(s.so_adj,0) + SUM(IFNULL(a.pos_sale,0)) - SUM(IFNULL(a.so_amt,0)) - SUM(IFNULL(a.pos_amt,0)), 2 ) AS profit ";
		}

        $tbl = "{$this->tables['temp_sm']} a ";

        //------------------------------------------------------------
        $sof = [
        	'wh' => $filters['wh'],
        	'from_date' => date( 'Y-m-1 00:00:00', strtotime( $filters['from_month'] ) ),
        	'to_date' => date( 'Y-m-t 23:59:59', strtotime( $filters['to_month'] ) ),
        	'margining' => $filters['margining'],
        	'margining_id' => $filters['margining_id'],
        ];
        $a = [
        	'usage' => 'stock_movement_report',
        	'fields' => "DATE_FORMAT(h.post_date, '%Y-%m') AS month
        		, SUM( ROUND( IFNULL(d.bqty * pi.final_sprice,0), 2 ) ) AS so_sale
                , SUM( ROUND( IF( pia.id > 0, IFNULL(d.bqty * pia.final_sprice,0) - IFNULL(d.bqty * pi.final_sprice,0), 0 ), 2 ) ) AS so_adj ",
        	'group' => [ 'month' ],
        ];
        $sale_sql = $this->Logic->get_sale_delivery_order( $sof, false, $a );
        $tbl.= "LEFT JOIN ( 
        	{$sale_sql}
    	) s ON s.month = a.month ";

    	//------------------------------------------------------------
    	$cond = "";
    	if( isset( $filters['from_month'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.month >= %s ", $filters['from_month'] );
        }
        if( isset( $filters['to_month'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.month <= %s ", $filters['to_month'] );
        }
        
		$grp = "GROUP BY a.month ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.month' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

		$results = $wpdb->get_results( $sql , ARRAY_A );

		//==============================================================================================

		$drop = "DROP TEMPORARY TABLE {$this->tables['temp_sm']} ";
        $succ = $wpdb->query( $drop );
		
		return $results;
	}

		public function stock_movement_handler( $filters = [], &$wh = '', &$strg_id = 0 )
	    {
	    	$succ = true;

	        if( isset( $filters['seller'] ) )
	       		$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
	       	else
	       		$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	       	$wh = $curr_wh['code'];
	        $strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$wh, 'doc_type'=>'inventory', 'seller'=>$curr_wh['id'] ] );

	       	$report_month = date( 'Y-m', strtotime( $filters['month'] ) );
	       	$needed_last = date( 'Y-m', strtotime( $report_month." -1 month " ) );

	       	if( !empty( $filters['to_month'] ) ) 
	       	{
	       		$needed_last = $report_month = date( 'Y-m', strtotime( $filters['to_month'] ) );
	       	}

	       	//duplicate stock_movement datas
			$succ = $this->temporary_stock_movement( $filters, true );
			if( ! $succ ) return false;

	       	//get last or final stock movement
	        $last_month = $this->Logic->get_latest_stock_movement_month( $wh, $strg_id );
	        if( $last_month ) $calc_start = date( 'Y-m', strtotime( $last_month." +1 month " ) );

	        if( empty( $filters['to_month'] ) && $last_month && strtotime( $calc_start ) >= strtotime( $report_month ) ) return true;

	        //get earliest operation date
	        $oper_begin = $this->Logic->get_earliest_operation( $wh );
	        if( $oper_begin )
	            $begin_month = date( 'Y-m', strtotime( $oper_begin['doc_date'] ) );
	        else
	            $begin_month = date( 'Y-m', strtotime( ( $this->setting['begin_date'] )? $this->setting['begin_date'] : $this->refs['starting'] ) );

	        @set_time_limit(900);

	        $from_month = date( 'Y-m', strtotime( $begin_month ) );
	        if( $last_month ) $from_month = $calc_start;
	        $to_month = $needed_last;

	        $month = $from_month;
	        while( $month !== date( 'Y-m', strtotime( $to_month." +1 month" ) ) )
	        {
	            $succ = $this->stock_movement_handling( $month, $wh, $strg_id );
	            if( ! $succ ) break;

	            $month = date( 'Y-m', strtotime( $month." +1 month" ) );
	        }

	        return $succ;
	    }

	    public function stock_movement_handling( $month = '', $wh = '', $strg_id = 0 )
        {
            if( ! $month || ! $wh || ! $strg_id ) return false;

            global $wcwh;
            $wpdb = $this->db_wpdb;
            $prefix = $this->get_prefix();
            
            $filters = [];
            $filters['from_date'] = date( 'Y-m-1 00:00:00', strtotime( $month ) );
            $filters['to_date'] = date( 'Y-m-t 23:59:59', strtotime( $month ) );
            $filters['month'] = date( 'Y-m', strtotime( $month." -1 month" ) );     //prev_month
            $filters['wh'] = $wh;
            $filters['strg_id'] = $strg_id;

            //-------------------------------------------------------------------------------------
            //Deletion
            $cond = $wpdb->prepare( "AND warehouse_id = %s AND strg_id = %d AND month = %s ", $wh, $strg_id, $month );
            $delete = "DELETE FROM {$this->tables['temp_sm']} WHERE 1 {$cond} ; ";
            $result = $wpdb->query( $delete );
            if( $result === false ) return false;

            //transaction query
            $dat_sql = $this->get_transactions( $filters );

            //-------------------------------------------------------------------------------------
            //SQL for stock movement calculation

$fld = "'{$wh}' AS warehouse_id, '{$strg_id}' AS strg_id, '{$month}' AS month, a.product_id
    , @op_qty:= IFNULL(a.op_qty,0) AS op_qty, @op_mtr:= IFNULL(a.op_mtr,0) AS op_mtr, @op_amt:= IFNULL(a.op_amt,0) AS op_amt
    , @gr_qty:= IFNULL(a.gr_qty,0) AS gr_qty, @gr_mtr:= IFNULL(a.gr_mtr,0) AS gr_mtr, @gr_amt:= IFNULL(a.gr_amt,0) AS gr_amt
    , @rp_qty:= IFNULL(a.rp_qty,0) AS rp_qty, @rp_mtr:= IFNULL(a.rp_mtr,0) AS rp_mtr, @rp_amt:= IFNULL(a.rp_amt,0) AS rp_amt
    , @ti_qty:= IFNULL(a.ti_qty,0) AS ti_qty, @ti_mtr:= IFNULL(a.ti_mtr,0) AS ti_mtr, @ti_amt:= IFNULL(a.ti_amt,0) AS ti_amt
    , @dr_qty:= IFNULL(a.dr_qty,0) AS dr_qty, @dr_mtr:= IFNULL(a.dr_mtr,0) AS dr_mtr, @dr_amt:= IFNULL(a.dr_amt,0) AS dr_amt
    , @so_qty:= IFNULL(a.so_qty,0) AS so_qty, @so_mtr:= IFNULL(a.so_mtr,0) AS so_mtr, @so_amt:= IFNULL(a.so_amt,0) AS so_amt
    , @so_sale:= IFNULL(a.so_sale,0) AS so_sale
    , @to_qty:= IFNULL(a.to_qty,0) AS to_qty, @to_mtr:= IFNULL(a.to_mtr,0) AS to_mtr, @to_amt:= IFNULL(a.to_amt,0) AS to_amt
    , @gi_qty:= IFNULL(a.gi_qty,0) AS gi_qty, @gi_mtr:= IFNULL(a.gi_mtr,0) AS gi_mtr, @gi_amt:= IFNULL(a.gi_amt,0) AS gi_amt
    , @gt_qty:= IFNULL(a.gt_qty,0) AS gt_qty, @gt_mtr:= IFNULL(a.gt_mtr,0) AS gt_mtr, @gt_amt:= IFNULL(a.gt_amt,0) AS gt_amt
    , @adj_qty:= IFNULL(a.adj_qty,0) AS adj_qty, @adj_mtr:= IFNULL(a.adj_mtr,0) AS adj_mtr, @adj_amt:= IFNULL(a.adj_amt,0) AS adj_amt
	, @qty:= IFNULL(a.op_qty,0)+@gr_qty+@rp_qty+@ti_qty+@dr_qty-@so_qty-@to_qty-@gi_qty-@gt_qty+@adj_qty AS qty
    , @mtr:= IFNULL(a.op_mtr,0)+@gr_mtr+@rp_mtr+@ti_mtr+@dr_mtr-@so_mtr-@to_mtr-@gi_mtr-@gt_mtr+@adj_mtr AS mtr
    , @amt:= IFNULL(a.op_amt,0)+@gr_amt+@rp_amt+@ti_amt+@dr_amt-@so_amt-@to_amt-@gi_amt-@gt_amt+@adj_amt AS amt
	, @pos_qty:= IFNULL(a.pos_qty,0) AS pos_qty, @pos_uom_qty:= IFNULL(a.pos_uom_qty,0) AS pos_uom_qty, @pos_mtr:= IFNULL(a.pos_mtr,0) AS pos_mtr  
    , @pos_amt:= IFNULL( IF( @qty>@pos_uom_qty, (@amt/@qty) * @pos_uom_qty, @amt ), 0 ) AS pos_amt
    , @pos_sale:= IFNULL(a.pos_sale,0) AS pos_sale
    , @op_qty+@gr_qty+@rp_qty+@ti_qty+@dr_qty-@so_qty-@to_qty-@gi_qty-@gt_qty-@pos_uom_qty+@adj_qty AS closing_qty
    , @op_mtr+@gr_mtr+@rp_mtr+@ti_mtr+@dr_mtr-@so_mtr-@to_mtr-@gi_mtr-@gt_mtr-@pos_mtr+@adj_mtr AS closing_mtr
    , IF( @op_qty+@gr_qty+@rp_qty+@ti_qty+@dr_qty-@so_qty-@to_qty-@gi_qty-@gt_qty-@pos_uom_qty+@adj_qty = 0 AND 
            ABS( IF(@op_amt != 0,@op_amt,@op_amt)+@gr_amt+@rp_amt+@ti_amt+@dr_amt-@so_amt-@to_amt-@gi_amt-@gt_amt-IFNULL(@pos_amt,0)+@adj_amt ) < 0.5
        , 0, IF(@op_amt != 0,@op_amt,@op_amt)+@gr_amt+@rp_amt+@ti_amt+@dr_amt-@so_amt-@to_amt-@gi_amt-@gt_amt-IFNULL(@pos_amt,0)+@adj_amt ) AS closing_amt ";

            $tbl = "( {$dat_sql} ) a ";

            $cond = "";
            $grp = "";
            $ord = "ORDER BY product_id ASC ";

            $sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

            //-------------------------------------------------------------------------------------
            //Query Insertion

            $fld = "warehouse_id, strg_id, month, product_id
            , op_qty, op_mtr, op_amt, gr_qty, gr_mtr, gr_amt, rp_qty, rp_mtr, rp_amt, ti_qty, ti_mtr, ti_amt, dr_qty, dr_mtr, dr_amt
            , so_qty, so_mtr, so_amt, so_sale, to_qty, to_mtr, to_amt, gi_qty, gi_mtr, gi_amt, gt_qty, gt_mtr, gt_amt
            , adj_qty, adj_mtr, adj_amt, qty, mtr, amt, pos_qty, pos_uom_qty, pos_mtr, pos_amt, pos_sale
            , closing_qty, closing_mtr, closing_amt ";

            $insert = "INSERT INTO {$this->tables['temp_sm']} ( {$fld} ) {$sql} ";
            $result = $wpdb->query( $insert );
            if( $result === false ) return false;

            return true;
        }

        /*
			CREATE TEMPORARY TABLE IF NOT EXISTS `temp_test` 
			AS ( SELECT * FROM wp_stmm_wcwh_stock_movement );
		*/
		public function temporary_stock_movement( $filters = [], $run = false )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			$dbname = !empty( $this->dbname )? $this->dbname : "";

			$cond = "";
			$select = "SELECT * FROM {$dbname}{$this->tables['stock_movement']} WHERE 1 {$cond} ";

			$query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_sm']} ";
			$query.= "AS ( {$select} ) ";

			if( $run ) $query = $wpdb->query( $query );
		
			return $query;
		}

		public function get_transactions( $filters = [], $run = false, $args = [] )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			$dbname = !empty( $this->dbname )? $this->dbname : "";

			$mfgr = $filters;
			$mfgr['margining'] = ( $this->need_margining )? true : false;
			$mfgr['margining_id'] = 'wh_movement_rpt_stock_in';

			$union = [];
            $union[] = $this->Logic->get_goods_receipt( $mfgr );
            $union[] = $this->Logic->get_reprocess( $filters );
            $union[] = $this->Logic->get_transfer_item( $filters );
            $union[] = $this->Logic->get_do_revise( $filters );
            $union[] = $this->Logic->get_sale_delivery_order( $filters );
            $union[] = $this->Logic->get_transfer_delivery_order( $filters );
            $union[] = $this->Logic->get_good_issue( $filters );
            $union[] = $this->Logic->get_good_return( $filters );
            $union[] = $this->Logic->get_pos( $filters );
            $union[] = $this->Logic->get_pos_transact( $filters );
            $union[] = $this->Logic->get_adjustment( $filters );
            $a = [ 'table'=>$this->tables['temp_sm'] ];
            $union[] = $this->Logic->get_opening( $filters, false, $a );

            $fld = "ic.base_id AS product_id 
                , SUM( IFNULL(a.op_qty,0) * IFNULL(ic.base_unit,1) ) AS op_qty, SUM( IFNULL(a.op_mtr,0) ) AS op_mtr, SUM( IFNULL(a.op_amt,0) ) AS op_amt 
                , SUM( IFNULL(a.qty,0) * IFNULL(ic.base_unit,1) ) AS qty, SUM( IFNULL(a.mtr,0) ) AS mtr, SUM( IFNULL(a.amt,0) ) AS amt 
                , SUM( IFNULL(a.gr_qty,0) * IFNULL(ic.base_unit,1) ) AS gr_qty, SUM( IFNULL(a.gr_mtr,0) ) AS gr_mtr, SUM( IFNULL(a.gr_amt,0) ) AS gr_amt
                , SUM( IFNULL(a.rp_qty,0) * IFNULL(ic.base_unit,1) ) AS rp_qty, SUM( IFNULL(a.rp_mtr,0) ) AS rp_mtr, SUM( IFNULL(a.rp_amt,0) ) AS rp_amt
                , SUM( IFNULL(a.ti_qty,0) * IFNULL(ic.base_unit,1) ) AS ti_qty, SUM( IFNULL(a.ti_mtr,0) ) AS ti_mtr, SUM( IFNULL(a.ti_amt,0) ) AS ti_amt
                , SUM( IFNULL(a.dr_qty,0) * IFNULL(ic.base_unit,1) ) AS dr_qty, SUM( IFNULL(a.dr_mtr,0) ) AS dr_mtr, SUM( IFNULL(a.dr_amt,0) ) AS dr_amt
                , SUM( IFNULL(a.so_qty,0) * IFNULL(ic.base_unit,1) ) AS so_qty, SUM( IFNULL(a.so_mtr,0) ) AS so_mtr, SUM( IFNULL(a.so_amt,0) ) AS so_amt, SUM( IFNULL(a.so_sale,0) ) AS so_sale
                , SUM( IFNULL(a.to_qty,0) * IFNULL(ic.base_unit,1) ) AS to_qty, SUM( IFNULL(a.to_mtr,0) ) AS to_mtr, SUM( IFNULL(a.to_amt,0) ) AS to_amt
                , SUM( IFNULL(a.gi_qty,0) * IFNULL(ic.base_unit,1) ) AS gi_qty, SUM( IFNULL(a.gi_mtr,0) ) AS gi_mtr, SUM( IFNULL(a.gi_amt,0) ) AS gi_amt
                , SUM( IFNULL(a.gt_qty,0) * IFNULL(ic.base_unit,1) ) AS gt_qty, SUM( IFNULL(a.gt_mtr,0) ) AS gt_mtr, SUM( IFNULL(a.gt_amt,0) ) AS gt_amt
                , SUM( IFNULL(a.pos_qty,0) * IFNULL(ic.base_unit,1) ) AS pos_qty, SUM( IFNULL(a.pos_uom_qty,0) * IFNULL(ic.base_unit,1) ) AS pos_uom_qty, SUM( IFNULL(a.pos_mtr,0) ) AS pos_mtr, SUM( IFNULL(a.pos_sale,0) ) AS pos_sale
                , SUM( IFNULL(a.adj_qty,0) * IFNULL(ic.base_unit,1) ) AS adj_qty, SUM( IFNULL(a.adj_mtr,0) ) AS adj_mtr, SUM( IFNULL(a.adj_amt,0) ) AS adj_amt ";

            $tbl = "( ";
            if( $union ) $tbl.= "( ".implode( " ) UNION ALL ( ", $union )." )";
            $tbl.= ") a ";
            $tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.product_id ";

            $cond = "";
            $grp = "GROUP BY ic.base_id ";
            $ord = "ORDER BY ic.base_id ASC ";

            $query = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

			if( $run ) $query = $wpdb->get_results( $query, ARRAY_A );
		
			return $query;
		}

	/*
		Stock In document:
			- good_receive
			- reprocess
			- transfer_item
			-- block_stock
	*/
	public function get_stock_in_report( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		$margining_id = "wh_movement_rpt_stock_in";

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		if( isset( $filters['seller'] ) )
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
	    else
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	    if( $curr_wh ) $filters['warehouse_id'] = $curr_wh['code'];

	    $siteOnDC = false;
	    if( $curr_wh['indication'] <= 0 && $curr_wh['parent'] > 0 ) $siteOnDC = true;
		
		$field = "@B:= IF( tc.to_prdt_id IS NULL AND ic.base_id != ic.item_id, 1, 0 ) AS has_base
			, @BB:= IF( ic.base_id = ic.item_id, 1, 0 ) AS is_base 
			, h.doc_id, d.item_id, h.docno, h.doc_date, h.post_date, h.created_at, h.doc_type, mb.meta_value AS dn, ph.docno AS ref_doc 
			, s.code AS supplier_code, s.name AS supplier_name, md.meta_value AS remark 
			, IF( @B, ic.base_id, ti.product_id ) AS product_id 
			, @qty := IF( @B, d.bqty * IFNULL(ic.base_unit,1), ti.bqty ) AS qty 
			, ROUND( ti.bunit, 3 ) AS metric 
			, ROUND( ti.weighted_total, 2 ) AS total_price 
			, ROUND( ti.weighted_total / @qty, 5 ) AS uprice 
			, IF( @B, d.product_id, tc.from_prdt_id ) AS from_prdt_id, IF( @B, d.bqty, tc.from_qty ) AS from_qty 
			, IF( @BB, '', IFNULL(ic.base_unit,1) ) AS converse ";
		
		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'delivery_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'uprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_conversion']} tc ON tc.item_id = d.item_id AND tc.hid = t.hid AND tc.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = ma.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} pma ON pma.doc_id = ph.doc_id AND pma.item_id = 0 AND pma.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} s ON s.code = mc.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['supplier_tree']} ";
			$subsql.= "WHERE 1 AND descendant = s.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} ss ON ss.id = ( {$subsql} ) ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = d.product_id ";

		if( $siteOnDC )
		{
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} pmb ON pmb.doc_id = ph.doc_id AND pmb.item_id = 0 AND pmb.meta_key = 'sales_doc' ";
			$table.= "LEFT JOIN {$this->tables['document']} ps ON ps.docno = pmb.meta_value AND ps.status > 0 ";
		}

		if( $this->need_margining )
		{
			$field = "@B:= IF( tc.to_prdt_id IS NULL AND ic.base_id != ic.item_id, 1, 0 ) AS has_base
			, @BB:= IF( ic.base_id = ic.item_id, 1, 0 ) AS is_base 
			, h.doc_id, d.item_id, h.docno, h.doc_date, h.post_date, h.created_at, h.doc_type, mb.meta_value AS dn, ph.docno AS ref_doc 
			, s.code AS supplier_code, s.name AS supplier_name, md.meta_value AS remark 
			, IF( @B, ic.base_id, ti.product_id ) AS product_id 
				, @mg:= IFNULL( mg.margin, 0 ) AS margin
				, @rn:= IF( mg.round_nearest IS NULL OR mg.round_nearest = 0, 0.01, mg.round_nearest ) AS round_nearest
			, @qty := IF( @B, d.bqty * IFNULL(ic.base_unit,1), ti.bqty ) AS qty 
			, ROUND( ti.bunit, 3 ) AS metric 
			, @unit_price:= ROUND( ti.weighted_total / @qty, 5 ) AS unit_price 
			, @uprice:= IF( mg.id > 0, ROUND( CASE 
				WHEN mg.round_type = 'ROUND' THEN ROUND( ROUND( @unit_price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
				WHEN mg.round_type = 'CEIL' THEN CEIL( ROUND( @unit_price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
	          	WHEN mg.round_type = 'FLOOR' THEN FLOOR( ROUND( @unit_price*( 1+( @mg/100 ) ), 5 ) / @rn ) * @rn 
	          	WHEN mg.round_type IS NULL OR mg.round_type = 'DEFAULT' THEN ROUND( @unit_price*( 1+( @mg/100 ) ), 5 ) 
	          	END, 5 ), ROUND( ti.weighted_total / @qty, 5 ) ) AS uprice 
			, IF( mg.id > 0, ROUND( @uprice * @qty, 2 ), ROUND( ti.weighted_total, 2 ) ) AS total_price 
			, IF( @B, d.product_id, tc.from_prdt_id ) AS from_prdt_id, IF( @B, d.bqty, tc.from_qty ) AS from_qty 
			, IF( @BB, '', IFNULL(ic.base_unit,1) ) AS converse ";

			$subsql = $wpdb->prepare( "SELECT a.id 
				FROM {$this->tables['margining']} a 
				LEFT JOIN {$this->tables['margining_sect']} s ON s.mg_id = a.id AND s.status > 0
				WHERE 1 AND a.status > 0 AND a.flag > 0 
				AND a.wh_id = h.warehouse_id AND a.type = %s AND s.sub_section = %s 
				AND a.since <= h.doc_date AND ( a.until >= h.doc_date OR a.until = '' ) 
				ORDER BY a.effective DESC, a.since DESC, a.created_at DESC 
				LIMIT 0,1 ", 'def', $margining_id );

			//$table.= "LEFT JOIN {$this->tables['margining_det']} mgd ON mgd.id = ( {$subsql} ) ";
			$table.= "LEFT JOIN {$this->tables['margining']} mh ON mh.id = ( {$subsql} ) ";

			$subsql = "SELECT m.id
				FROM {$this->tables['margining']} m 
				LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
				WHERE 1 AND m.id = mh.id AND m.inclusive = 'excl' AND md.client = pma.meta_value ";
			$table.= "LEFT JOIN {$this->tables['margining']} mx ON mx.id = ( {$subsql} ) ";

			$subsql = "SELECT m.id
				FROM {$this->tables['margining']} m 
				LEFT JOIN {$this->tables['margining_det']} md ON md.mg_id = m.id AND md.status > 0
				WHERE 1 AND m.id = mh.id 
				AND ( ( m.inclusive = 'incl' AND md.client = pma.meta_value ) OR ( m.inclusive = 'excl' AND ( m.id != mx.id OR mx.id IS NULL ) ) ) 
				ORDER BY m.effective DESC, m.since DESC, m.created_at DESC 
				LIMIT 0,1
			";
			$table.= "LEFT JOIN {$this->tables['margining']} mg ON mg.id = ( {$subsql} ) ";
		}

		$cond = $wpdb->prepare( "AND h.status >= %d ", 6 );
		$cond.= $wpdb->prepare( "AND t.status > %d AND ti.status > %d AND ti.plus_sign = %s ", 0, 0, "+" );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['follow_dc'] ) )
		{
			if( isset( $filters['date_type'] ) )
			{
				$date_type = $filters['date_type'];
			}
			$date_type = empty( $date_type )? $this->def_date_type : $date_type;
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND ph.{$date_type} >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND ph.{$date_type} <= %s ", $filters['to_date'] );
			}
		}
		else
		{
			if( isset( $filters['date_type'] ) )
			{
				$date_type = $filters['date_type'];
			}
			$date_type = empty( $date_type )? $this->def_date_type : $date_type;
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.{$date_type} >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.{$date_type} <= %s ", $filters['to_date'] );
			}
		}
		
		if( isset( $filters['supplier'] ) )
		{
			if( is_array( $filters['supplier'] ) )
			{
				$catcd = "s.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$catcd.= "OR ss.id IN ('" .implode( "','", $filters['supplier'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "s.id = %d ", $filters['supplier'] );
				$catcd = $wpdb->prepare( "OR ss.id = %d ", $filters['supplier'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		if( isset( $filters['doc_type'] ) )
		{
			if( is_array( $filters['doc_type'] ) )
				$cond.= "AND h.doc_type IN ('" .implode( "','", $filters['doc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.doc_type = %s ", $filters['doc_type'] );
		}
		else
		{
			$cond.= "AND h.doc_type IN( 'good_receive', 'reprocess', 'transfer_item', 'do_revise', 'pos_transactions' ) ";
		}
		if( isset( $filters['s'] ) )
		{
			$search = explode( ',', trim( $filters['s'] ) );    
			$search = array_merge( $search, explode( ' ', str_replace( ',', ' ', trim( $filters['s'] ) ) ) );
        	$search = array_filter( $search );

            $cond.= "AND ( ";

            $seg = array();
            foreach( $search as $kw )
            {
                $kw = trim( $kw );
                $cd = array();
                $cd[] = "h.docno LIKE '%".$kw."%' ";
				$cd[] = "ph.docno LIKE '%".$kw."%' ";
				$cd[] = "s.name LIKE '%".$kw."%' ";
				$cd[] = "s.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		//-------------------------------------------------------------------
		if( ! current_user_cans( [ 'hide_amt_movement_wh_reports' ] ) ) 
		{
	    	$amt_fld = ", main.uprice, main.total_price ";
		}

		$field = "main.doc_id, main.item_id, main.docno, main.doc_date, main.post_date, main.created_at, main.ref_doc, main.doc_type, main.dn ";
		$field.= ", main.supplier_code, main.supplier_name, main.remark ";
		$field.= ", cat.name AS category, cat.slug AS category_code ";
		if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) $field.= ", prdt.name AS prdt_name ";
		$field.= ", prdt.code AS prdt_code, prdt._uom_code AS uom ";
		$field.= ", main.qty, main.metric {$amt_fld} ";
		if( current_user_cans( [ 'wh_support' ] ) )
			$field.= ", CONCAT( fprdt.code, ' - ',fprdt.name ) AS from_prdt, main.from_qty, main.converse ";
		
		$table = "( {$sql} ) main ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} prdt ON prdt.id = main.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = prdt.category ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} fprdt ON fprdt.id = main.from_prdt_id ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = "";
		
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND prdt.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND prdt.id = %d ", $filters['product'] );
		}
		if( isset( $filters['group'] ) )
		{
			if( is_array( $filters['group'] ) )
				$cond.= "AND prdt.grp_id IN ('" .implode( "','", $filters['group'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND prdt.grp_id = %d ", $filters['group'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR cat.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		
		//order
		if( empty( $order ) )
		{
			if( isset( $filters['follow_dc'] ) )
				$order = [ 'main.ref_doc' => 'ASC', 'prdt.code' => 'ASC' ];
			else
				$order = [ 'main.docno' => 'ASC', 'prdt.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
	/*
		Stock out document:
			- delivery_order
			- good_issue
			- good_return
			-- block_action
	*/
	public function get_stock_out_report( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		if( isset( $filters['seller'] ) )
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
	    else
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	    if( $curr_wh ) $filters['warehouse_id'] = $curr_wh['code'];

		if( isset( $filters['good_issue_type'] ) )
		{
			if( ! is_array( $filters['good_issue_type'] ) ) $filters['good_issue_type'] = [ $filters['good_issue_type'] ];

			if( in_array( 'delivery_order', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'delivery_order' => '' ];
				$filters['doc_type1'][] = [ 'good_issue' => 'delivery_order' ];
			}
			if( in_array( 'reprocess', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'good_issue' => 'reprocess' ];
			}
			if( in_array( 'own_use', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'good_issue' => 'own_use' ];
			}
			if( in_array( 'other', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'good_issue' => 'other' ];
			}
			if( in_array( 'vending_machine', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'good_issue' => 'vending_machine' ];
			}
			if( in_array( 'block_stock', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'good_issue' => 'block_stock' ];
			}
			if( in_array( 'transfer_item', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'good_issue' => 'transfer_item' ];
			}
			if( in_array( 'good_return', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'good_return' => '' ];
			}
			if( in_array( 'returnable', $filters['good_issue_type'] ) )
			{
				$filters['doc_type1'][] = [ 'returnable' => '' ];
			}
		}
		else
		{
			$filters['doc_type1'] = [ 
				[ 'good_issue' => [ 'delivery_order', 'reprocess', 'own_use', 'other', 'vending_machine', 'block_stock', 'transfer_item', 'returnable' ] ], 
				[ 'delivery_order' => '' ], 
				[ 'good_return' => '' ] ];
		}
		//pd($filters);

		//------------------------------------------------------------------

	    $margining_id = "wh_movement_rpt_stock_out";

		//------------------------------------------------------------------
		
		//DO, GT, GI(delivery_order, reprocess, own_use, block_stock, transfer_item)
		$field = "@B:= IF( tc.to_prdt_id IS NULL AND ic.base_id != ic.item_id, 1, 0 ) AS has_base ";
		$field.= ", @BB:= IF( ic.base_id = ic.item_id, 1, 0 ) AS is_base ";
		$field.= ", h.doc_id, d.item_id, h.docno, h.doc_date, h.post_date, h.created_at ";
		$field.= ", IF( mb.meta_value IS NOT NULL, mb.meta_value, h.doc_type ) AS issue_type ";
		$field.= ", ph.docno AS ref_doc, ph.doc_type AS ref_doc_type, IFNULL( me.meta_value, pc.docno ) AS link_doc ";
		$field.= ", c.code AS client_code, c.name AS client_name, md.meta_value AS remark ";
		$field.= ", IF( @B, ic.base_id, ti.product_id ) AS product_id ";
		$field.= ", @qty := IF( @B, d.bqty * IFNULL(ic.base_unit,1), ti.bqty ) AS qty ";
		$field.= ", ROUND( ti.bunit, 3 ) AS metric ";
		$field.= ", @tcost:= ROUND( IFNULL(ti.weighted_total,0), 2 ) AS total_cost ";
		$field.= ", ROUND( @tcost / @qty, 5 ) AS ucost ";
		$field.= ", ROUND( pi.final_sprice / IFNULL(ic.base_unit,1), 5 ) AS sprice ";
		$field.= ", @amt := ROUND( IFNULL(d.bqty * pi.final_sprice,0), 2 ) AS amount ";
		$field.= ", ROUND( @amt - @tcost, 2 ) AS profit ";
		$field.= ", IF( @B, d.product_id, tc.from_prdt_id ) AS from_prdt_id, IF( @B, d.bqty, tc.from_qty ) AS from_qty ";
		$field.= ", IF( @BB, '', IFNULL(ic.base_unit,1) ) AS converse ";
		$field.= ", ROUND( ( d.bqty * pia.final_sprice ) - ( d.bqty * pi.final_sprice ), 2 ) AS adj_total_sale ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'ref_doc_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'good_issue_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} md ON md.doc_id = h.doc_id AND md.item_id = 0 AND md.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} me ON me.doc_id = h.doc_id AND me.item_id = 0 AND me.meta_key = 'delivery_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mf ON mf.doc_id = h.doc_id AND mf.item_id = 0 AND mf.meta_key = 'ref_doc_type' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} c ON c.code = mc.meta_value ";
			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['client_tree']} ";
			$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['client']} cc ON cc.id = ( {$subsql} ) ";
			
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'ucost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_cost' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_conversion']} tc ON tc.item_id = d.item_id AND tc.hid = t.hid AND tc.status != 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = d.product_id ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} pc ON pc.parent = h.doc_id AND pc.warehouse_id = h.warehouse_id AND pc.status != 0 AND pc.doc_type NOT IN( 'do_revise', 'good_receive' ) ";
		
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} ph ON ph.doc_id = ma.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pi ON pi.doc_id = ph.doc_id AND pi.product_id = d.product_id AND pi.warehouse_id = ph.warehouse_id AND pi.type = 'def' AND pi.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['margining_sales']} pia ON pia.doc_id = ph.doc_id AND pia.product_id = d.product_id AND pia.warehouse_id = ph.warehouse_id AND pia.type = 'adj' AND pia.status > 0 ";
	
		$cond = $wpdb->prepare( "AND h.status >= %d ", 6 );
		$cond.= $wpdb->prepare( "AND t.status > %d AND ti.status > %d AND ti.plus_sign = %s ", 0, 0, "-" );
		
		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['doc_type1'] ) && ! empty( $filters['doc_type1'] ) )
		{	
			$cd = [];
			foreach( $filters['doc_type1'] as $i => $doc_type )
			{
				foreach( $doc_type as $doc => $type )
				{
					$row = $wpdb->prepare( "h.doc_type = %s ", $doc );
					if( !empty( $type ) ) 
					{
						if( is_array( $type ) )
							$row.= "AND mb.meta_value IN ('" .implode( "','", $type ). "') ";
						else
							$row.= $wpdb->prepare( "AND mb.meta_value = %s ", $type );
					}
					$cd[] = " ( ".$row." ) ";
				}
			}
			$cond.= "AND ( ".implode( " OR ", $cd )." ) ";
		}
		if( isset( $filters['date_type'] ) )
		{
			$date_type = $filters['date_type'];
		}
		$date_type = empty( $date_type )? $this->def_date_type : $date_type;
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.{$date_type} >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.{$date_type} <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['client'] ) )
		{
			if( is_array( $filters['client'] ) )
			{
				$catcd = "c.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$catcd.= "OR cc.id IN ('" .implode( "','", $filters['client'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "c.id = %d ", $filters['client'] );
				$catcd = $wpdb->prepare( "OR cc.id = %d ", $filters['client'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		if( isset( $filters['s'] ) )
		{
			$search = explode( ',', trim( $filters['s'] ) );    
			$search = array_merge( $search, explode( ' ', str_replace( ',', ' ', trim( $filters['s'] ) ) ) );
        	$search = array_filter( $search );

            $cond.= "AND ( ";

            $seg = array();
            foreach( $search as $kw )
            {
                $kw = trim( $kw );
                $cd = array();
                $cd[] = "h.docno LIKE '%".$kw."%' ";
				$cd[] = "ph.docno LIKE '%".$kw."%' ";
				$cd[] = "c.name LIKE '%".$kw."%' ";
				$cd[] = "c.code LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$sql1 = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		//-------------------------------------------------------------------
		if( ! current_user_cans( [ 'hide_amt_movement_wh_reports' ] ) ) 
		{
	    	$amt_fld = ", main.ucost, main.total_cost, main.sprice, main.amount, main.profit ";
		}

		$field = "main.doc_id, main.item_id, main.docno, main.doc_date, main.post_date, main.created_at, main.issue_type, main.ref_doc, main.ref_doc_type, main.link_doc ";
		$field.= ", main.client_code, main.client_name, main.remark ";
		$field.= ", cat.name AS category, cat.slug AS category_code ";
		if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) $field.= ", prdt.name AS prdt_name ";
		$field.= ", prdt.code AS prdt_code, prdt._uom_code AS uom ";
		$field.= ", main.qty, main.metric {$amt_fld} ";
		if( current_user_cans( [ 'wh_support' ] ) )
			$field.= ", CONCAT( fprdt.code, ' - ',fprdt.name ) AS from_prdt, main.from_qty, main.converse ";
		$field.= ", main.adj_total_sale ";

		$table = "( {$sql1} ) main ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} prdt ON prdt.id = main.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = prdt.category ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} fprdt ON fprdt.id = main.from_prdt_id ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = "";
		
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND prdt.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND prdt.id = %d ", $filters['product'] );
		}
		if( isset( $filters['group'] ) )
		{
			if( is_array( $filters['group'] ) )
				$cond.= "AND prdt.grp_id IN ('" .implode( "','", $filters['group'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND prdt.grp_id = %d ", $filters['group'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR cat.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'main.docno' => 'ASC', 'prdt.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		//pd($sql);
		return $results;
	}

	public function get_adjustment_report( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}
		if( isset( $filters['seller'] ) )
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true );
	    else
	    	$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	    if( $curr_wh ) $filters['warehouse_id'] = $curr_wh['code'];
		
		$field = "@B:= IF( tc.to_prdt_id IS NULL AND ic.base_id != ic.item_id, 1, 0 ) AS has_base ";
		$field.= ", @BB:= IF( ic.base_id = ic.item_id, 1, 0 ) AS is_base ";
		$field.= ", h.doc_id, d.item_id, h.docno, h.doc_date, h.post_date, h.created_at, ma.meta_value AS remark ";
		$field.= ", IF( @B, ic.base_id, ti.product_id ) AS product_id, ti.plus_sign AS adj_direction ";
		$field.= ", @qty:= IF( @B, d.bqty * IFNULL(ic.base_unit,1), ti.bqty ) AS qty, @metric:= ROUND( IFNULL(ti.bunit,0), 3 ) AS metric ";
		$field.= ", IF( ti.plus_sign = '+', @qty, 0 ) AS in_qty, IF( ti.plus_sign = '+', @metric, 0 ) AS in_metric ";
		$field.= ", ROUND( IF( ti.plus_sign = '+', ti.weighted_price, 0 ), 5 ) AS unit_price
					, ROUND( IF( ti.plus_sign = '+', ti.weighted_total, 0 ), 2 ) AS total_price ";
		$field.= ", IF( ti.plus_sign = '-', @qty, 0 ) AS out_qty, IF( ti.plus_sign = '-', @metric, 0 ) AS out_metric ";
		$field.= ", ROUND( IF( ti.plus_sign = '-', ti.weighted_price, 0 ), 5 ) AS unit_cost
					, ROUND( IF( ti.plus_sign = '-', ti.weighted_total, 0 ), 2 ) AS total_cost ";
		$field.= ", IF( @B, d.product_id, tc.from_prdt_id ) AS from_prdt_id, IF( @B, d.bqty, tc.from_qty ) AS from_qty ";
		$field.= ", IF( @BB, '', IFNULL(ic.base_unit,1) ) AS converse ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'remark' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'plus_sign' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'adjust_qty' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmc ON dmc.doc_id = h.doc_id AND dmc.item_id = d.item_id AND dmc.meta_key = 'uprice' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmd ON dmd.doc_id = h.doc_id AND dmd.item_id = d.item_id AND dmd.meta_key = 'total_amount' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} t ON t.doc_id = h.doc_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} ti ON ti.hid = t.hid AND ti.item_id = d.item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_conversion']} tc ON tc.item_id = d.item_id AND tc.hid = t.hid AND tc.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = d.product_id ";

		$cond = $wpdb->prepare( "AND h.status >= %d ", 6 );
		$cond.= $wpdb->prepare( "AND t.status > %d AND ti.status > %d ", 0, 0 );

		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['doc_type'] ) )
		{
			if( is_array( $filters['doc_type'] ) )
				$cond.= "AND h.doc_type IN ('" .implode( "','", $filters['doc_type'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND h.doc_type = %s ", $filters['doc_type'] );
		}
		else
		{
			$cond.= "AND h.doc_type IN( 'stock_adjust', 'stocktake' ) ";
		}
		if( isset( $filters['date_type'] ) )
		{
			$date_type = $filters['date_type'];
		}
		$date_type = empty( $date_type )? $this->def_date_type : $date_type;
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.{$date_type} >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.{$date_type} <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['s'] ) )
		{
			$search = explode( ',', trim( $filters['s'] ) );    
			$search = array_merge( $search, explode( ' ', str_replace( ',', ' ', trim( $filters['s'] ) ) ) );
        	$search = array_filter( $search );

            $cond.= "AND ( ";

            $seg = array();
            foreach( $search as $kw )
            {
                $kw = trim( $kw );
                $cd = array();
                $cd[] = "h.docno LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

		//-------------------------------------------------------------------
		if( ! current_user_cans( [ 'hide_amt_movement_wh_reports' ] ) ) 
		{
	    	$in_amt_fld = ", main.unit_price, main.total_price ";
	    	$out_amt_fld = ", main.unit_cost, main.total_cost ";
		}

		$field = "main.doc_id, main.item_id, main.docno, main.doc_date, main.post_date, main.created_at, main.remark ";
		$field.= ", cat.name AS category, cat.slug AS category_code ";
		if( current_user_cans( [ 'item_visible_wh_reports' ] ) ) $field.= ", prdt.name AS prdt_name ";
		$field.= ", prdt.code AS prdt_code, prdt._uom_code AS uom ";
		$field.= ", main.adj_direction, main.in_qty, main.in_metric {$in_amt_fld} ";
		$field.= ", main.out_qty, main.out_metric {$out_amt_fld} ";
		if( current_user_cans( [ 'wh_support' ] ) )
			$field.= ", CONCAT( fprdt.code, ' - ',fprdt.name ) AS from_prdt, main.from_qty, main.converse ";
		
		$table = "( {$sql} ) main ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} prdt ON prdt.id = main.product_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = prdt.category ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} fprdt ON fprdt.id = main.from_prdt_id ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";
		
		$cond = "";
		
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND prdt.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND prdt.id = %d ", $filters['product'] );
		}
		if( isset( $filters['group'] ) )
		{
			if( is_array( $filters['group'] ) )
				$cond.= "AND prdt.grp_id IN ('" .implode( "','", $filters['group'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND prdt.grp_id = %d ", $filters['group'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR cat.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'main.docno' => 'ASC', 'prdt.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
} //class

}