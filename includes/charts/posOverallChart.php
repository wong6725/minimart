<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Chart" ) ) include_once( WCWH_DIR . "/includes/chart.php" ); 

if ( !class_exists( "WCWH_POSOverallChart" ) ) 
{

class WCWH_POSOverallChart extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "POSOverallChart";

	public $Chart;

	public $seller = 0;

	public $tplName = array(
		'export' => 'exportPOSOverallChart',
	);
	
	protected $tables = array();

	public $filters = array();

	public $datas = array();

	public $outlets = [];

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->Chart = new WCWH_Chart();

		$this->set_db_tables();

		$this->set_outlets();
	}
	
	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"items"			=> $prefix."items",
			"item_group"	=> $prefix."item_group",
			"uom"			=> $prefix."uom",
			
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
			"customer" 		=> $prefix."customer",
			"customer_group"	=> $prefix."customer_group",
			"acc_type"		=> $prefix."customer_acc_type",
			"origin"		=> $prefix."customer_origin",
			
			"status"		=> $prefix."status",
			
			"wp_user"		=> $wpdb->users,
			"wp_usermeta"	=> $wpdb->usermeta,
			
			"order_items"	=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"=> $wpdb->prefix."woocommerce_order_itemmeta",
		);
	}

	public function set_outlets()
	{
		$main = apply_filters( 'wcwh_get_warehouse', ['indication'=>1], [], true, [ 'usage'=>1 ] );
		
		$sellers = apply_filters( 'wcwh_get_warehouse', ['parent'=>$main['id']], [], false, [ 'usage'=>1, 'meta'=>['dbname'] ] );
		if( $sellers )
		{
			foreach( $sellers as $i => $seller )
			{
				$sellers[$i]['wh_code'] = str_replace( [ " ", "-" ], [ "", "_" ], $seller['code'] );

				if( empty( $seller['dbname'] ) ) unset( $sellers[ $i ] );
			}
		}

		$custom_order = [ '1018-IFP', '1036-TSM', '1009-PMN', '1024-VPK', '1037-UBB', '1027-TSP' ];
		$arr = [];
		foreach( $custom_order as $code )
		{
			foreach( $sellers as $i => $seller )
			{
				if( $seller['code'] == $code )
				{
					$arr[] = $seller;
					break;
				}
			}
		}

		$this->outlets = $arr;
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
					$params = [];

					$datas['nodate'] = 1;
					$time_stamp = current_time( 'timestamp' ); //-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//

					switch( $datas['export_type'] )
					{
						case 'summary':					 
							$datas['filename'] = 'POS Overall Summary_'.$time_stamp;
							if( !empty( $datas['period'] ) ) $params['period'] = $datas['period'];

							if( $datas['period'] == 'month' )
							{
								$params['from_date'] = empty( $datas['from_date_month'] )? date( 'Y-m-d', strtotime( current_time( 'Y-m-1' )." -1 month" ) ) : $datas['from_date_month'];
								$params['to_date'] = empty( $datas['to_date_month'] )? current_time( 'Y-m-t' ) : $datas['to_date_month'];

								$params['from_date'] = date( 'Y-m-1 H:i:s', strtotime( $params['from_date'] ) );
								$params['to_date'] = date( 'Y-m-t H:i:s', strtotime( $params['to_date']." 23:59:59" ) );
							}
							else
							{
								$params['from_date'] = empty( $datas['from_date'] )? date( 'Y-m-d', strtotime( current_time( 'Y-m-d' )." -7 days" ) ) : $datas['from_date'];
								$params['to_date'] = empty( $datas['to_date'] )? current_time( 'Y-m-d' ) : $datas['to_date'];

								$params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $params['from_date'] ) );
								$params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $params['to_date']." 23:59:59" ) );
							}
							break;
							//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
							case 'category':
								$datas['filename'] = 'POS Overall Sales By Category_'.$time_stamp;
								if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
								if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
							break;
							case 'item':
								$datas['filename'] = 'POS Overall Sales By Item_'.$time_stamp;
								if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
								if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );		
							break;
							//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
					}

					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];

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
	protected function im_ex_default_column()
	{
		$default_column = array();

		$default_column['title'] = [];

		$default_column['default'] = [];

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		$type = $params['export_type'];
		unset( $params['export_type'] );
		
		switch( $type )
		{
			//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
			case 'summary':
				return $this->get_pos_overall_summary( $params, [], [] );
			break;
			case 'category':
				return $this->get_pos_overall_category( $params, [], [] );
			break;
			case 'item':
				return $this->get_pos_overall_item( $params, [], [] );
			break;
			//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
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
					data-title="<?php echo $actions['export'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = 'summary' )
	{
		$action_id = 'pos_overall_chart_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
			case 'summary':
				do_action( 'wcwh_templating', 'chart/export-pos_overall-summary-chart.php', $this->tplName['export'], $args );
			break;
			case 'category':
				do_action( 'wcwh_templating', 'chart/export-pos_overall-category-chart.php', $this->tplName['export'], $args );
			break;
			case 'item':
				do_action( 'wcwh_templating', 'chart/export-pos_overall-item-chart.php', $this->tplName['export'], $args );
			break;
			//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
		}
	}

	public function get_chart( $filters = [] )
	{	
		$params = ( $this->filters )? $this->filters : $filters['filter'];

		$display_type = $params['display_type'];

		if( ! $this->datas )
		{
			switch ( strtolower( $display_type ) ) {
				//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
				case 'summary':
					$this->datas = $this->get_pos_overall_summary( $params, [], [] );
				break;
				case 'category':
					$order = $params['list_by'] == 'amount' ? [] : [ 'qty' => 'DESC', 'category_code' => 'ASC' ];
					$this->datas = $this->get_pos_overall_category( $params, $order, [] );
					break;
				case 'item':
					$order = $params['list_by'] == 'amount' ? [] : [ 'qty' => 'DESC', 'item_code' => 'ASC' ];
					$this->datas = $this->get_pos_overall_item( $params, $order, [] );
					break;
				//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
			}
		}
		$datas = $this->datas;

		$group = [];
		$x = '';
		$y = '';
		$chart_args = [];

		switch ( strtolower( $display_type ) ) 
		{
			case 'summary':
				//Pass group as array in this format to tell the function that use the array key as the key value and the array value as the label value of the chart
				$group = [];
				if( $this->outlets )
				{
					foreach( $this->outlets as $i => $outlet )
					{
						$group[ $outlet['wh_code'] ] = $outlet['name'];
					}
				}
				//Pass ~chart_group to the function to tell the function that axis value based on the group passed to the function
				$x = 'date';
				$y = '~chart_group';
				$chart_args = [ 'colors_num' => sizeof( $this->outlets ), 'y_axis_title' => 'Amount', 'x_axis_title' => 'Date', 'unit' => 'RM', 'title' => 'POS Overall Summary', 'interaction_mode' => 'index', 'tooltip' => 'currency' ];
				break;
			//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
			case 'category':
				//Pass group as array in this format to tell the function that use the array key as the key value and the array value as the label value of the chart
				$group = $params['list_by'] == 'amount' ? [ 'line_total' => 'Amount' ] : [ 'qty' => 'Quantity' ];
				//Pass ~chart_group to the function to tell the function that axis value based on the group passed to the function
				$x = '~chart_group';
				$y = 'category_code-category';
				$chart_args = [ 'x_axis_title' => 'Amount(RM)', 'title' => 'POS Sales by Item Category', 'horizontal_bar' => true, 'labels_sort' => false, 'interaction_mode' => 'y', 'tooltip' => $params['list_by'] == 'amount' ? 'currency' : '' ];
				break;
			case 'item':
				//Pass group as array in this format to tell the function that use the array key as the key value and the array value as the label value of the chart
				$group = $params['list_by'] == 'amount' ? [ 'line_total' => 'Amount' ] : [ 'qty' => 'Quantity' ];
				//Pass ~chart_group to the function to tell the function that axis value based on the group passed to the function
				$x = '~chart_group';
				$y = 'item_code-item_name';
				$chart_args = [ 'x_axis_title' => 'Amount(RM)', 'title' => 'POS Sales by Item', 'horizontal_bar' => true, 'labels_sort' => false, 'interaction_mode' => 'y', 'tooltip' => $params['list_by'] == 'amount' ? 'currency' : '' ];
				break;
			//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
		}

		$chart_info = [];

		if( $datas )
		{
			$chart = [
				'group' => $group,
				'x' => $x,
				'y' => $y,
				//'label' => [ 'item_code', 'item_name' ],
				//'xformat' => [ 'date' => 'Y-m-d' ],
			];
			$chartDatas = $this->Chart->chart_data_conversion( $datas, 'bar', $chart );
			$chart_info = $this->Chart->chart_generator( $chartDatas, 'bar', $chart_args );
		}
		
		return $chart_info;
	}

	public function pos_overall_summary( $filters = array(), $order = array() )
	{
		$action_id = 'pos_overall_summary';
		$token = apply_filters( 'wcwh_generate_token', $action_id );

		if( $filters['initial'] ){ $init = 1; unset( $filters['initial'] ); } 
		if( $this->seller ) $filters['seller'] = $this->seller;
		
		if( $filters['period'] == 'month' )
		{
			$filters['from_date'] = empty( $filters['from_date_month'] )? date( 'Y-m-d', strtotime( current_time( 'Y-m-1' )." -1 month" ) ) : $filters['from_date_month'];
			$filters['to_date'] = empty( $filters['to_date_month'] )? current_time( 'Y-m-t' ) : $filters['to_date_month'];

			$filters['from_date'] = date( 'Y-m-1', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-t', strtotime( $filters['to_date'] ) );
		}
		else
		{
			$filters['from_date'] = empty( $filters['from_date'] )? date( 'Y-m-d', strtotime( current_time( 'Y-m-d' )." -7 days" ) ) : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? current_time( 'Y-m-d' ) : $filters['to_date'];
		}
		
		$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );
		$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
		
		if( !empty( $filters ) ) $this->filters = $filters;
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
			<div class="row">
				<div class="col-md-4">
					<label class="" for="flag">Time Period</label><br>
					<?php
		                $options = [ 'day'=>'By Day', 'month'=>'By Month' ];
		                
		                wcwh_form_field( 'filter[period]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>['data-showhide=".periods"'], 'class'=>['optionShowHide'],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['period'] )? $this->filters['period'] : '', $view 
		                ); 
					?>
				</div>

				<div class="col-md-4 periods day">
					<label class="" for="flag">From Date <sup>Current: <?php echo $filters['from_date']; ?></sup></label><br>
					<?php
						$from_date = date( 'Y-m-d', strtotime( $filters['from_date'] ) );
						$def_from = date( 'm/d/Y', strtotime( $filters['from_date'] ) );
						
						wcwh_form_field( 'filter[from_date]', 
		                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
								'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $from_date )? $from_date : '', $view 
		                ); 
					?>
				</div>

				<div class="col-md-4 periods day">
					<label class="" for="flag">To Date <sup>Current: <?php echo $filters['to_date']; ?></sup></label><br>
					<?php
						$to_date = date( 'Y-m-d', strtotime( $filters['to_date'] ) );
						$def_to = date( 'm/d/Y', strtotime( $filters['to_date'] ) );

						wcwh_form_field( 'filter[to_date]', 
		                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
								'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $to_date )? $to_date : '', $view 
		                ); 
					?>
				</div>

				<div class="col-md-4 periods month display-none">
					<label class="" for="flag">From Month <sup>Current: <?php echo $filters['from_date']; ?></sup></label><br>
					<?php
						$from_date = date( 'Y-m', strtotime( $filters['from_date'] ) );

						wcwh_form_field( 'filter[from_date_month]', 
		                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
								'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"', 'data-dd-default-date="'.$def_from.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $from_date )? $from_date : '', $view 
		                ); 
					?>
				</div>

				<div class="col-md-4 periods month display-none">
					<label class="" for="flag">To Month <sup>Current: <?php echo $filters['to_date']; ?></sup></label><br>
					<?php
						$to_date = date( 'Y-m', strtotime( $filters['to_date'] ) );

						wcwh_form_field( 'filter[to_date_month]', 
		                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
								'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $to_date )? $to_date : '', $view 
		                ); 
					?>
				</div>
			</div>
			<div class="row">
				<input type="hidden" name="filter[display_type]" value="summary" />
				<input type="hidden" name="filter[seller]" value="<?php echo $this->filters['seller']; ?>" />
				<div class="col-md-4">
					<br>
					<button type="submit" id="search-submit" class="btn btn-primary btn-sm">Search</button>
				</div>
			</div>
			
			<?php
				if( ! $init )
				{
					$datas = $this->get_pos_overall_summary( $this->filters, $order, [] );

					$datas = ( $datas )? $datas : array();
					if( $datas ) $this->datas = $datas;
					//pd($datas);
				}
			?>
			<div id="chart-container">
			    <canvas id="graphCanvas" data-pageLoadChart="1"></canvas>
			</div>
		</form>
		<?php
	}
	
	//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
	public function pos_overall_category( $filters = array(), $order = array() )
	{
		$action_id = 'pos_overall_category';
		$token = apply_filters( 'wcwh_generate_token', $action_id );

		if( $filters['initial'] ){ $init = 1; unset( $filters['initial'] ); } 
		if( $this->seller ) $filters['seller'] = $this->seller;
		
		$filters['from_date'] = empty( $filters['from_date'] )? date( 'Y-m-d', strtotime( current_time( 'Y-m-d' )." -7 days" ) ) : $filters['from_date'];
		$filters['to_date'] = empty( $filters['to_date'] )? current_time( 'Y-m-d' ) : $filters['to_date'];
		
		$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );
		$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );

		if( !empty( $filters ) ) $this->filters = $filters;
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
			<div class="row">
				<div class="col">
					<label class="" for="flag">From Date <sup>Current: <?php echo $this->filters['from_date']; ?></sup></label><br>
					<?php
						$from_date = date( 'Y-m-d', strtotime( $filters['from_date'] ) );
						$def_from = date( 'm/d/Y', strtotime( $filters['from_date'] ) );

						wcwh_form_field( 'filter[from_date]', 
		                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
								'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $from_date )? $from_date : '', $view 
		                ); 
					?>
				</div>

				<div class="col">
					<label class="" for="flag">To Date <sup>Current: <?php echo $this->filters['to_date']; ?></sup></label><br>
					<?php
						$to_date = date( 'Y-m-d', strtotime( $filters['to_date'] ) );
						$def_to = date( 'm/d/Y', strtotime( $filters['to_date'] ) );

						wcwh_form_field( 'filter[to_date]', 
		                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
								'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $to_date )? $to_date : '', $view 
		                ); 
					?>
				</div>

				<div class="col">
					<label class="" for="flag">List By</label><br>
					<?php
		                $options = [ 'amount'=>'Amount', 'qty'=>'Quantity' ];
		                
		                wcwh_form_field( 'filter[list_by]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['list_by'] )? $this->filters['list_by'] : '', $view 
		                ); 
					?>
				</div>
	
				<div class="col">
					<label class="" for="flag">By Category </label><br>
					<?php
						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'slug', [ 'slug', 'name' ], '' );
						
		                wcwh_form_field( 'filter[category][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
		                ); 
					?>
				</div>
			</div>
			<div class="row">
				<input type="hidden" name="filter[display_type]" value="category" />
				<input type="hidden" name="filter[seller]" value="<?php echo $this->filters['seller']; ?>" />
				<div class="col-md-4">
					<br>
					<button type="submit" id="search-submit" class="btn btn-primary btn-sm">Search</button>
				</div>
			</div>
			
			<?php
				if( ! $init )
				{
					$order = $this->filters['list_by'] == 'amount' ? [] : [ 'qty' => 'DESC', 'category_code' => 'ASC' ];
					$datas = $this->get_pos_overall_category( $this->filters, $order, [] );

					$datas = ( $datas )? $datas : array();
					if( $datas ) $this->datas = $datas;
					//pd($datas);
				}
			?>
			<div id="chart-container">
			    <canvas id="graphCanvas" data-pageLoadChart="1"></canvas>
			</div>
		</form>
		<?php
	}
	
	public function pos_overall_item( $filters = array(), $order = array() )
	{
		$action_id = 'pos_overall_item';
		$token = apply_filters( 'wcwh_generate_token', $action_id );

		if( $filters['initial'] ){ $init = 1; unset( $filters['initial'] ); } 
		if( $this->seller ) $filters['seller'] = $this->seller;
		
		$filters['from_date'] = empty( $filters['from_date'] )? date( 'Y-m-d', strtotime( current_time( 'Y-m-d' )." -7 days" ) ) : $filters['from_date'];
		$filters['to_date'] = empty( $filters['to_date'] )? current_time( 'Y-m-d' ) : $filters['to_date'];
		
		$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );
		$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );

		if( !empty( $filters ) ) $this->filters = $filters;
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
			<div class="row">
				<div class="col">
					<label class="" for="flag">From Date <sup>Current: <?php echo $this->filters['from_date']; ?></sup></label><br>
					<?php
						$from_date = date( 'Y-m-d', strtotime( $filters['from_date'] ) );
						$def_from = date( 'm/d/Y', strtotime( $filters['from_date'] ) );

						wcwh_form_field( 'filter[from_date]', 
		                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
								'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $from_date )? $from_date : '', $view 
		                ); 
					?>
				</div>

				<div class="col">
					<label class="" for="flag">To Date <sup>Current: <?php echo $this->filters['to_date']; ?></sup></label><br>
					<?php
						$to_date = date( 'Y-m-d', strtotime( $filters['to_date'] ) );
						$def_to = date( 'm/d/Y', strtotime( $filters['to_date'] ) );

						wcwh_form_field( 'filter[to_date]', 
		                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
								'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $to_date )? $to_date : '', $view 
		                ); 
					?>
				</div>

				<div class="col">
					<label class="" for="flag">List By</label><br>
					<?php
		                $options = [ 'amount'=>'Amount', 'qty'=>'Quantity' ];
		                
		                wcwh_form_field( 'filter[list_by]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['list_by'] )? $this->filters['list_by'] : '', $view 
		                ); 
					?>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<label class="" for="flag">By Category </label><br>
					<?php
						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'slug', [ 'slug', 'name' ], '' );
						
		                wcwh_form_field( 'filter[category][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col">
					<label class="" for="flag">By Item </label><br>
					<?php
						$filters = [];
						if( $this->seller ) $filters['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'serial', [ 'code', '_uom_code', 'name' ], '' );
						
		                wcwh_form_field( 'filter[product][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['product'] )? $this->filters['product'] : '', $view 
		                ); 
					?>
				</div>
			</div>
			<div class="row">
				<input type="hidden" name="filter[display_type]" value="item" />
				<input type="hidden" name="filter[seller]" value="<?php echo $this->filters['seller']; ?>" />
				<div class="col-md-4">
					<br>
					<button type="submit" id="search-submit" class="btn btn-primary btn-sm">Search</button>
				</div>
			</div>
			
			<?php
				if( ! $init )
				{
					$order = $this->filters['list_by'] == 'amount' ? [] : [ 'qty' => 'DESC', 'item_code' => 'ASC' ];
					$datas = $this->get_pos_overall_item( $this->filters, $order, [] );

					$datas = ( $datas )? $datas : array();
					if( $datas ) $this->datas = $datas;
					//pd($datas);
				}
			?>
			<div id="chart-container">
			    <canvas id="graphCanvas" data-pageLoadChart="1"></canvas>
			</div>
		</form>
		<?php
	}

	//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//


	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_pos_overall_summary( $filters = [], $order = [], $args = [] )
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

		if( ! $this->outlets ) return false;

		if( isset( $filters['period'] ) )
		{
			if( $filters['period'] == 'month' )
				$date_format = '%Y-%m';
			else
				$date_format = '%Y-%m-%d';
		}

		$field = "a.date ";

		$union = [];
		foreach( $this->outlets as $i => $outlet )
		{
			$arg['dbname'] = $outlet['dbname'];
			$arg['wh_code'] = $outlet['wh_code'];

			$field.= ", SUM( a.{$outlet['wh_code']} ) AS {$outlet['wh_code']} ";

			$union[] = $this->get_pos_sales( $filters, false, $arg );
		}

		$union_sql = implode( " UNION ALL ", $union );

		$table = "( {$union_sql} ) a ";
		
		$grp = "GROUP BY date ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'date' => 'ASC' ];
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

		public function get_pos_sales( $filters = [], $run = false, $args = [] )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			if( $args['dbname'] ) $dbname = $args['dbname'].".";

			if( isset( $filters['period'] ) )
			{
				if( $filters['period'] == 'month' )
					$date_format = '%Y-%m';
				else
					$date_format = '%Y-%m-%d';
			}

			$field = "DATE_FORMAT( a.post_date, '{$date_format}' ) AS date ";

			foreach( $this->outlets as $i => $outlet )
			{
				if( $args['wh_code'] == $outlet['wh_code'] )
					$field.= ", ROUND( SUM( b.meta_value ), 2 ) AS {$outlet['wh_code']} ";
				else
					$field.= ", 0 AS {$outlet['wh_code']} ";
			}

			$table = "{$dbname}{$wpdb->posts} a ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
			
			$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value > %d ", 'shop_order', 0 );
			$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
			
			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
			}
			
			$grp = "GROUP BY date ";

            $ord = "";

            $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

            if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
        
            return $query;
		}
		
	//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
	public function get_pos_overall_category( $filters = [], $order = [], $args = [] )
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

		if( ! $this->outlets ) return false;

		$field = "ux.category as category, ux.category_code as category_code, sum(ux.qty) AS qty, sum(round(ux.weight,2)) AS weight, sum(round(ux.line_total, 2)) AS line_total, ";
		$field.= "ROUND( SUM(ROUND(ux.line_total, 2)) / SUM(ux.qty), 5) AS avg_price ";

		$table = "";

		$subquery = [];

		foreach( $this->outlets as $i => $outlet )
		{
			$dbname = $outlet['dbname'].'.';
			if(!$dbname) return false;

			$fld = "j.name AS category, j.slug AS category_code ";
			$fld.= ", SUM( e.meta_value ) AS qty, ROUND( SUM( h.meta_value * e.meta_value ), 2 ) AS weight ";
			$fld.= ", ROUND( SUM( g.meta_value ) / SUM( e.meta_value ), 5 ) AS avg_price, ROUND( SUM( g.meta_value ), 2 ) AS line_total ";

			$tbl = "{$dbname}{$wpdb->posts} a ";
			$tbl.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
			//$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_price' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_line_total' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = c.order_item_id AND l.meta_key = '_uom' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.meta_value ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} j ON j.term_id = i.category ";

			$ssql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$ssql.= "WHERE 1 AND descendant = j.term_id ORDER BY level DESC LIMIT 0,1 ";

			$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $ssql ) ";

			$tbl.= "LEFT JOIN {$dbname}{$wpdb->postmeta} k ON k.post_id = a.ID AND k.meta_key = 'customer_id' ";
		
			$cd = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value > %d ", 'shop_order', 0 );
			$cd.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";

			if( isset( $filters['from_date'] ) )
			{
				$cd.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cd.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
			}
			if( isset( $filters['category'] ) )
			{
				if( is_array( $filters['category'] ) )
				{
					$catcd = "ct.slug IN ('" .implode( "','", $filters['category'] ). "') ";
					$catcd.= "OR j.slug IN ('" .implode( "','", $filters['category'] ). "') ";
					$cd.= "AND ( {$catcd} ) ";
				}
				else
				{
					$catcd = $wpdb->prepare( "ct.slug = %s ", $filters['category'] );
					$catcd = $wpdb->prepare( "OR j.slug = %s ", $filters['category'] );
					$cd.= "AND ( {$catcd} ) ";
				}
			}

			$gp = "GROUP BY j.slug ";
			$sqlarr = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$gp} ";
			$subquery[] = $sqlarr;
		}

		$union_sql = implode( " UNION ALL ", $subquery );

		$table = "( {$union_sql} ) ux ";
		
		$grp = "GROUP BY ux.category_code ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'line_total' => 'DESC', 'category_code' => 'ASC' ];
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

	public function get_pos_overall_item( $filters = [], $order = [], $args = [] )
	{

		////-------continue here
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

		if( ! $this->outlets ) return false;

		$field = "ux.category as category, ux.category_code as category_code, ";
		$field.= "ux.item_name as item_name, ux.sku as sku, ux.item_code as item_code, ux.serial as serial, ux.uom as uom, ";
		$field.= "sum(ux.qty) AS qty, sum(round(ux.weight,2)) AS weight, sum(round(ux.line_total, 2)) AS line_total, ROUND( SUM(ROUND(ux.line_total, 2)) / SUM(ux.qty), 5) AS avg_price ";

		$table = "";

		$subquery = [];

		foreach( $this->outlets as $i => $outlet )
		{
			$dbname = $outlet['dbname'].'.';
			if(!$dbname) return false;

			$fld = "j.name AS category, j.slug AS category_code ";
			$fld.= ", c.order_item_name AS item_name, i._sku AS sku, i.code AS item_code, i.serial as serial, i._uom_code AS uom ";
			$fld.= ", SUM( e.meta_value ) AS qty, ROUND( SUM( h.meta_value * e.meta_value ), 2 ) AS weight ";
			$fld.= ", ROUND( SUM( g.meta_value ) / SUM( e.meta_value ), 5 ) AS avg_price, ROUND( SUM( g.meta_value ), 2 ) AS line_total ";

			$tbl = "{$dbname}{$wpdb->posts} a ";
			$tbl.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
			//$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_price' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_line_total' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = c.order_item_id AND l.meta_key = '_uom' ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.meta_value ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} j ON j.term_id = i.category ";

			$ssql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$ssql.= "WHERE 1 AND descendant = j.term_id ORDER BY level DESC LIMIT 0,1 ";

			$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $ssql ) ";

			$tbl.= "LEFT JOIN {$dbname}{$wpdb->postmeta} k ON k.post_id = a.ID AND k.meta_key = 'customer_id' ";
		
			$cd = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value > %d ", 'shop_order', 0 );
			$cd.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";

			if( isset( $filters['from_date'] ) )
			{
				$cd.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cd.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
			}
			if( isset( $filters['product'] ) )
			{
				if( is_array( $filters['product'] ) )
					$cd.= "AND i.serial IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cd.= $wpdb->prepare( "AND i.serial = %d ", $filters['product'] );
			}
			if( isset( $filters['category'] ) )
			{
				if( is_array( $filters['category'] ) )
				{
					$catcd = "ct.slug IN ('" .implode( "','", $filters['category'] ). "') ";
					$catcd.= "OR j.slug IN ('" .implode( "','", $filters['category'] ). "') ";
					$cd.= "AND ( {$catcd} ) ";
				}
				else
				{
					$catcd = $wpdb->prepare( "ct.slug = %s ", $filters['category'] );
					$catcd = $wpdb->prepare( "OR j.slug = %s ", $filters['category'] );
					$cd.= "AND ( {$catcd} ) ";
				}
			}

			$gp = "GROUP BY i.code ";
			$sqlarr = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$gp} ";
			$subquery[] = $sqlarr;
		}

		$union_sql = implode( " UNION ALL ", $subquery );

		$table = "( {$union_sql} ) ux ";
		
		$grp = "GROUP BY ux.item_code ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'line_total' => 'DESC', 'item_code' => 'ASC' ];
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
	//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
	
} //class

}