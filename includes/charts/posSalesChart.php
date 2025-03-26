<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Chart" ) ) include_once( WCWH_DIR . "/includes/chart.php" ); 

if ( !class_exists( "WCWH_POSSalesChart" ) ) 
{

class WCWH_POSSalesChart extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "POSSalesChart";

	public $Chart;

	public $seller = 0;

	public $tplName = array(
		'export' => 'exportPOSSalesChart',
	);
	
	protected $tables = array();

	public $filters = array();

	public $datas = array();

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->Chart = new WCWH_Chart();

		$this->set_db_tables();
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

					switch( $datas['export_type'] )
					{
						case 'summary':
							$datas['filename'] = 'POS Sales Summary';
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
						case 'category':
							$datas['filename'] = 'POS Sales Category';
							if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
							if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
							break;
						case 'item':
							$datas['filename'] = 'POS Sales Item';
							if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
							if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );		
							break;
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
			case 'summary':
				return $this->get_pos_sales_summary( $params, [], [] );
			break;
			case 'category':
				return $this->get_pos_sales_category( $params, [], [] );
			break;
			case 'item':
				return $this->get_pos_sales_item( $params, [], [] );
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
		$action_id = 'pos_sales_chart_export';
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
			case 'summary':
				do_action( 'wcwh_templating', 'chart/export-pos_sales-summary-chart.php', $this->tplName['export'], $args );
			break;
			case 'category':
				do_action( 'wcwh_templating', 'chart/export-pos_sales-category-chart.php', $this->tplName['export'], $args );
				break;
			case 'item':
				do_action( 'wcwh_templating', 'chart/export-pos_sales-item-chart.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function get_chart( $filters = [] )
	{	
		$params = ( $this->filters )? $this->filters : $filters['filter'];

		$display_type = $params['display_type'];

		if( ! $this->datas )
		{
			switch ( strtolower( $display_type ) ) {
				case 'summary':
					$this->datas = $this->get_pos_sales_summary( $params, [], [] );
					break;
				case 'category':
					$order = $params['list_by'] == 'amount' ? [] : [ 'qty' => 'DESC', 'j.slug' => 'ASC' ];
					$this->datas = $this->get_pos_sales_category( $params, $order, [] );
					break;
				case 'item':
					$order = $params['list_by'] == 'amount' ? [] : [ 'qty' => 'DESC', 'i.code' => 'ASC' ];
					$this->datas = $this->get_pos_sales_item( $params, $order, [] );
					break;
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
				$group = [ 'amt_credit' => 'Amount Credit', 'total' => 'Order Total', 'amt_cash' => 'Amount Cash' ];
				//Pass ~chart_group to the function to tell the function that axis value based on the group passed to the function
				$x = 'date';
				$y = '~chart_group';
				$chart_args = [ 'colors_num' => 3, 'y_axis_title' => 'Amount', 'x_axis_title' => 'Date', 'unit' => 'RM', 'title' => 'POS Sales Summary', 'interaction_mode' => 'index', 'tooltip' => 'currency', 'mixed'=>'line', 'mixed_Data' => ['Order Total'] ];
				//'x_axis_stacked'=>true
				break;
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

	public function pos_sales_summary( $filters = array(), $order = array() )
	{
		$action_id = 'pos_sales_summary';
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
					$datas = $this->get_pos_sales_summary( $this->filters, $order, [] );

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

	public function pos_sales_category( $filters = array(), $order = array() )
	{
		$action_id = 'pos_sales_category';
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
				<div class="col-md-3">
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

				<div class="col-md-3">
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

				<div class="col-md-3">
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

				<div class="col-md-3">
					<label class="" for="flag">By Customer </label><br>
					<?php
						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_customer', $filter ), 'id', [ 'code', 'uid', 'name' ], 'Select', [ 'guest'=>'Guest' ] );
	                
			            wcwh_form_field( 'filter[customer]', 
			                [ 'id'=>'customer', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
			                    'options'=> $options
			                ], 
			                isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
			            ); 
					?>
				</div>
	
				<div class="col-md-6">
					<label class="" for="flag">By Category </label><br>
					<?php
						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'id', [ 'slug', 'name' ], '' );
						
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
					$order = $this->filters['list_by'] == 'amount' ? [] : [ 'qty' => 'DESC', 'j.slug' => 'ASC' ];
					$datas = $this->get_pos_sales_category( $this->filters, $order, [] );

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

	public function pos_sales_item( $filters = array(), $order = array() )
	{
		$action_id = 'pos_sales_item';
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
				<div class="col-md-3">
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

				<div class="col-md-3">
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

				<div class="col-md-3">
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

				<div class="col-md-3">
					<label class="" for="flag">By Customer </label><br>
					<?php
						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_customer', $filter ), 'id', [ 'code', 'uid', 'name' ], 'Select', [ 'guest'=>'Guest' ] );
	                
			            wcwh_form_field( 'filter[customer]', 
			                [ 'id'=>'customer', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
			                    'options'=> $options
			                ], 
			                isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
			            ); 
					?>
				</div>

				<div class="col-md-6">
					<label class="" for="flag">By Category </label><br>
					<?php
						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'id', [ 'slug', 'name' ], '' );
						
		                wcwh_form_field( 'filter[category][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
		                ); 
					?>
				</div>
	
				<div class="col-md-6">
					<label class="" for="flag">By Item </label><br>
					<?php
						$filters = [];
						if( $this->seller ) $filters['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ], '' );
						
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
					$order = $this->filters['list_by'] == 'amount' ? [] : [ 'qty' => 'DESC', 'i.code' => 'ASC' ];
					$datas = $this->get_pos_sales_item( $this->filters, $order, [] );

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


	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_pos_sales_summary( $filters = [], $order = [], $args = [] )
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

		if( isset( $filters['period'] ) )
		{
			if( $filters['period'] == 'month' )
				$date_format = '%Y-%m';
			else
				$date_format = '%Y-%m-%d';
		}

		$field = "DATE_FORMAT( a.post_date, '{$date_format}' ) AS date, COUNT( a.ID ) AS transactions, ROUND( SUM( f.meta_value ), 2 ) AS amt_paid ";
		$field.= ", ROUND( SUM( g.meta_value ), 2 ) AS amt_change, ROUND( SUM( f.meta_value - g.meta_value ), 2 ) AS amt_cash ";
		$field.= ", ROUND( SUM( h.meta_value ), 2 ) AS amt_credit, ROUND( SUM( b.meta_value ), 2 ) AS total ";
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_payment_method' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = 'wc_pos_id_register' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = '_pos_session_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_amount_pay' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = 'wc_pos_amount_change' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} h ON h.post_id = a.ID AND h.meta_key = 'wc_pos_credit_amount' ";
		
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
		if( isset( $filters['payment_method'] ) )
		{
			$cond.= $wpdb->prepare( "AND c.meta_value = %s ", $filters['payment_method'] );
		}
		if( isset( $filters['register'] ) )
		{
			$cond.= $wpdb->prepare( "AND d.meta_value = %s ", $filters['register'] );
		}
		if( isset( $filters['session'] ) )
		{
			$cond.= $wpdb->prepare( "AND e.meta_value = %s ", $filters['session'] );
		}
		
		$grp = "GROUP BY date ";

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.post_date' => 'ASC' ];
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

	public function get_pos_sales_category( $filters = [], $order = [], $args = [] )
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
		
		$field = "j.name AS category, j.slug AS category_code ";
		$field.= ", SUM( e.meta_value ) AS qty, ROUND( SUM( h.meta_value * e.meta_value ), 2 ) AS weight ";
		$field.= ", ROUND( SUM( g.meta_value ) / SUM( e.meta_value ), 5 ) AS avg_price, ROUND( SUM( g.meta_value ), 2 ) AS line_total ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = c.order_item_id AND l.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} j ON j.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = j.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} k ON k.post_id = a.ID AND k.meta_key = 'customer_id' ";
		
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
		if( isset( $filters['customer'] ) )
		{
			if( $filters['customer'] == 'guest' )
				$cond.= $wpdb->prepare( "AND ( k.meta_value IS NULL OR k.meta_value = %s ) ", '0' );
			else
				$cond.= $wpdb->prepare( "AND k.meta_value = %s ", $filters['customer'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR j.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR j.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		
		$grp = "GROUP BY j.slug ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'line_total' => 'DESC', 'j.slug' => 'ASC' ];
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

	public function get_pos_sales_item( $filters = [], $order = [], $args = [] )
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
		
		$field = "j.name AS category, j.slug AS category_code ";
		$field.= ", c.order_item_name AS item_name, i._sku AS sku, i.code AS item_code, i.serial, i._uom_code AS uom ";
		$field.= ", SUM( e.meta_value ) AS qty, ROUND( SUM( h.meta_value * e.meta_value ), 2 ) AS weight ";
		$field.= ", ROUND( SUM( g.meta_value ) / SUM( e.meta_value ), 5 ) AS avg_price, ROUND( SUM( g.meta_value ), 2 ) AS line_total ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} f ON f.order_item_id = c.order_item_id AND f.meta_key = '_price' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = c.order_item_id AND l.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.meta_value ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} j ON j.term_id = i.category ";

		$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = j.term_id ORDER BY level DESC LIMIT 0,1 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} k ON k.post_id = a.ID AND k.meta_key = 'customer_id' ";
		
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
		if( isset( $filters['customer'] ) )
		{
			if( $filters['customer'] == 'guest' )
				$cond.= $wpdb->prepare( "AND ( k.meta_value IS NULL OR k.meta_value = %s ) ", '0' );
			else
				$cond.= $wpdb->prepare( "AND k.meta_value = %s ", $filters['customer'] );
		}
		if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND i.id IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.id = %d ", $filters['product'] );
		}
		if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR j.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR j.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		
		$grp = "GROUP BY i.code ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'line_total' => 'DESC', 'i.code' => 'ASC' ];
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