<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Chart" ) ) include_once( WCWH_DIR . "/includes/chart.php" ); 

if ( !class_exists( "WCWH_EstateChart" ) ) 
{

class WCWH_EstateChart extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "EstateChart";

	public $Chart;

	public $seller = 0;

	public $tplName = array(
		'export' => 'exportEstateChart',
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
			"document"		=> $prefix."document",
			"document_items"=> $prefix."document_items",
			"document_meta"	=> $prefix."document_meta",

			"items"			=> $prefix."items",
			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
			"status"		=> $prefix."status",
			
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
							$datas['filename'] = 'Estate Summary ';
							$params['from_date'] = empty( $datas['from_date'] )? date( 'Y-m-d', strtotime( current_time( 'Y-m-1' )." -1 month" ) ) : $datas['from_date'];
							$params['to_date'] = empty( $datas['to_date'] )? current_time( 'Y-m-t' ) : $datas['to_date'];

							$params['from_date'] = date( 'Y-m-1 H:i:s', strtotime( $params['from_date'] ) );
							$params['to_date'] = date( 'Y-m-t H:i:s', strtotime( $params['to_date']." 23:59:59" ) );
						break;
						case 'category':
							$datas['filename'] = 'Estate Category ';
							if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
							if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
						break;
						case 'item':
							$datas['filename'] = 'Estate Items';
							if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
							if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
						break;
					}

					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];

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
				return $this->get_estate_summary( $params, [], [] );
			break;
			case 'category':
				return $this->get_estate_category( $params, [], [] );
			break;
			case 'item':
				return $this->get_estate_item( $params, [], [] );
			break;

		}
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_latest( $type = 'summary' )
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
		$action_id = 'estate_export';
		$args = array(
			'setting'	=> $this->setting,
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
				do_action( 'wcwh_templating', 'chart/export-et_summary-chart.php', $this->tplName['export'], $args );
			break;
			case 'category':
				do_action( 'wcwh_templating', 'chart/export-et_category-chart.php', $this->tplName['export'], $args );
			break;
			case 'item':
				do_action( 'wcwh_templating', 'chart/export-et_item-chart.php', $this->tplName['export'], $args );
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
					$this->datas = $this->get_estate_summary( $params, [], [] );
					break;
				case 'category':
					$this->datas = $this->get_estate_category( $params, [], [] );
					break;
				case 'item':
					$this->datas = $this->get_estate_item( $params, [], [] );
					break;
			}
		}
		$datas = $this->datas;

		$x = '';
		$y = '';
		$chart_args = [];

		switch ( strtolower( $display_type ) ) 
		{
			case 'summary':
				$x = 'months';
				$y = '~chart_group';
				$chart_args = [ 'colors_num' => 3, 'y_axis_title' => 'Amount', 'x_axis_title' => 'Date', 'unit' => 'RM', 'title' => 'Estate Summary', 'interaction_mode' => 'point', 'tooltip' => 'currency' ];
				break;
			case 'category':
				$x = '~chart_group';
				$y = 'category_code-category';
				$chart_args = [ 'x_axis_title' => 'Amount(RM)', 'title' => 'Estate Category', 'horizontal_bar' => true, 'labels_sort' => false, 'interaction_mode' => 'y', 'tooltip' => 'currency' ];
				break;
			case 'item':
				$x = '~chart_group';
				$y = 'item_code-item_name';
				$chart_args = [ 'x_axis_title' => 'Amount(RM)', 'title' => 'Estate Item', 'horizontal_bar' => true, 'labels_sort' => false, 'interaction_mode' => 'y', 'tooltip' => 'currency' ];
				break;
		}

		$chart_info = [];

		if( $datas )
		{
			$chart = [
				//Pass group as array in this format to tell the function that use the array key as the key value and the array value as the label value of the chart
				'group' => [ 'amount' => 'Amount' ],
				'x' => $x,
				//Pass ~chart_group to the function to tell the function that axis value based on the group passed to the function
				'y' => $y,
				//'label' => [ 'item_code', 'item_name' ],
				//'xformat' => [ 'date' => 'Y-m-d' ],
			];
			$chartDatas = $this->Chart->chart_data_conversion( $datas, 'bar', $chart );
			$chart_info = $this->Chart->chart_generator( $chartDatas, 'bar', $chart_args );
		}
		
		return $chart_info;
	}

	public function estate_summary( $filters = array(), $order = array() )
	{
		$action_id = 'estate_summary';
		$token = apply_filters( 'wcwh_generate_token', $action_id );

		if( $filters['initial'] ){ $init = 1; unset( $filters['initial'] ); } 
		if( $this->seller ) $filters['seller'] = $this->seller;
		
		$filters['from_date'] = empty( $filters['from_date'] )? date( 'Y-m-d', strtotime( current_time( 'Y-m-1' )." -1 month" ) ) : $filters['from_date'];
		$filters['to_date'] = empty( $filters['to_date'] )? current_time( 'Y-m-t' ) : $filters['to_date'];

		$filters['from_date'] = date( 'Y-m-1', strtotime( $filters['from_date'] ) );
		$filters['to_date'] = date( 'Y-m-t', strtotime( $filters['to_date'] ) );

		$filter = [ 'status'=>1, 'indication'=>1 ];
		if( isset( $filters['seller'] ) ) $filter['seller'] = $filters['seller'];
		$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_client', 'estate_customer' ] ] );
		if( $wh )
		{
			if( empty( $filters['client'] ) && empty( $filters['customer'] ) )
			{
				$filters['client'] = !empty( $filters['client'] )? $filters['client'] : ( is_json( $wh['estate_client'] )? json_decode( stripslashes( $wh['estate_client'] ), true ) : $wh['estate_client'] );
				$filters['customer'] = !empty( $filters['customer'] )? $filters['customer'] : ( is_json( $wh['estate_customer'] )? json_decode( stripslashes( $wh['estate_customer'] ), true ) : $wh['estate_customer'] );
			}
		}

		if( !empty( $filters ) ) $this->filters = $filters;
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
			<div class="row">
				<div class="col-md-4">
					<label class="" for="flag">From Date <sup>Current: <?php echo $this->filters['from_date']; ?></sup></label><br>
					<?php
						$from_date = date( 'Y-m', strtotime( $filters['from_date'] ) );
						$def_from = date( 'm/d/Y', strtotime( $filters['from_date'] ) );

						wcwh_form_field( 'filter[from_date]', 
		                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
								'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"', 'data-dd-default-date="'.$def_from.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $from_date )? $from_date : '', $view 
		                ); 
					?>
				</div>

				<div class="col-md-4">
					<label class="" for="flag">To Date <sup>Current: <?php echo $this->filters['to_date']; ?></sup></label><br>
					<?php
						$to_date = date( 'Y-m', strtotime( $filters['to_date'] ) );
						$def_to = date( 'm/d/Y', strtotime( $filters['to_date'] ) );

						wcwh_form_field( 'filter[to_date]', 
		                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
								'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
		                    ], 
		                    isset( $to_date )? $to_date : '', $view 
		                ); 
					?>
				</div>
			</div>

			<div class="row">
				<div class="col-md-6">
					<label class="" for="flag">By Estate Client </label><br>
					<?php
						$filter = [ 'status'=>1, 'indication'=>1 ];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_client', 'estate_customer' ] ] );
						if( $wh )
						{
							if( is_json( $detail['serial2'] ) ) $detail['serial2'] = json_decode( stripslashes( $detail['serial2'] ), true );
							$Client = is_json( $wh['estate_client'] )? json_decode( stripslashes( $wh['estate_client'] ), true ) : $wh['estate_client'];
							$Customer = is_json( $wh['estate_customer'] )? json_decode( stripslashes( $wh['estate_customer'] ), true ) : $wh['estate_customer'];
						}

						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						if( $Client ) $filter['code'] = $Client;
						$options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, [] ), 'code', [ 'code', 'name' ], '' );
						
		                wcwh_form_field( 'filter[client][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['client'] )? $this->filters['client'] : '', $view 
		                ); 
					?>
				</div>
				
				<div class="col-md-6">
					<label class="" for="flag">By Estate Customer </label><br>
					<?php
		                $filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						if( $Customer ) $filter['id'] = $Customer;
						$options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [] ), 'id', [ 'code', 'uid', 'name' ], '' );
						
		                wcwh_form_field( 'filter[customer][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
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
					$datas = $this->get_estate_summary( $this->filters, $order, [] );

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

	public function estate_category( $filters = array(), $order = array() )
	{
		$action_id = 'estate_category';
		$token = apply_filters( 'wcwh_generate_token', $action_id );

		if( $filters['initial'] ){ $init = 1; unset( $filters['initial'] ); } 
		if( $this->seller ) $filters['seller'] = $this->seller;
		
		$filters['from_date'] = empty( $filters['from_date'] )? current_time( 'Y-m-1' ) : $filters['from_date'];
		$filters['to_date'] = empty( $filters['to_date'] )? current_time( 'Y-m-t' ) : $filters['to_date'];
		
		$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );
		$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );

		$filter = [ 'status'=>1, 'indication'=>1 ];
		if( isset( $filters['seller'] ) ) $filter['seller'] = $filters['seller'];
		$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_client', 'estate_customer' ] ] );
		if( $wh )
		{
			if( empty( $filters['client'] ) && empty( $filters['customer'] ) )
			{
				$filters['client'] = !empty( $filters['client'] )? $filters['client'] : ( is_json( $wh['estate_client'] )? json_decode( stripslashes( $wh['estate_client'] ), true ) : $wh['estate_client'] );
				$filters['customer'] = !empty( $filters['customer'] )? $filters['customer'] : ( is_json( $wh['estate_customer'] )? json_decode( stripslashes( $wh['estate_customer'] ), true ) : $wh['estate_customer'] );
			}
		}

		if( !empty( $filters ) ) $this->filters = $filters;
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
			<div class="row">
				<div class="col-md-4">
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

				<div class="col-md-4">
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
			</div>

			<div class="row">
				<div class="col-md-6">
					<label class="" for="flag">By Estate Client </label><br>
					<?php
						$filter = [ 'status'=>1, 'indication'=>1 ];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_client', 'estate_customer' ] ] );
						if( $wh )
						{
							if( is_json( $detail['serial2'] ) ) $detail['serial2'] = json_decode( stripslashes( $detail['serial2'] ), true );
							$Client = is_json( $wh['estate_client'] )? json_decode( stripslashes( $wh['estate_client'] ), true ) : $wh['estate_client'];
							$Customer = is_json( $wh['estate_customer'] )? json_decode( stripslashes( $wh['estate_customer'] ), true ) : $wh['estate_customer'];
						}

						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						if( $Client ) $filter['code'] = $Client;
						$options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, [] ), 'code', [ 'code', 'name' ], '' );
						
		                wcwh_form_field( 'filter[client][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['client'] )? $this->filters['client'] : '', $view 
		                ); 
					?>
				</div>
				
				<div class="col-md-6">
					<label class="" for="flag">By Estate Customer </label><br>
					<?php
		                $filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						if( $Customer ) $filter['id'] = $Customer;
						$options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [] ), 'id', [ 'code', 'uid', 'name' ], '' );
						
		                wcwh_form_field( 'filter[customer][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
		                ); 
					?>
				</div>
			</div>

			<div class="row">
				<div class="col-md-6">
					<label class="" for="flag">By Category </label><br>
					<?php
		                $filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'id', [ 'slug', 'name' ], '' );
						
		                wcwh_form_field( 'filter[category][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
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
					$datas = $this->get_estate_category( $this->filters, $order, [] );

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

	public function estate_item( $filters = array(), $order = array() )
	{
		$action_id = 'estate_item';
		$token = apply_filters( 'wcwh_generate_token', $action_id );

		if( $filters['initial'] ){ $init = 1; unset( $filters['initial'] ); } 
		if( $this->seller ) $filters['seller'] = $this->seller;
		
		$filters['from_date'] = empty( $filters['from_date'] )? current_time( 'Y-m-1' ) : $filters['from_date'];
		$filters['to_date'] = empty( $filters['to_date'] )? current_time( 'Y-m-t' ) : $filters['to_date'];
		
		$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );
		$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );

		$filter = [ 'status'=>1, 'indication'=>1 ];
		if( isset( $filters['seller'] ) ) $filter['seller'] = $filters['seller'];
		$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_client', 'estate_customer' ] ] );
		if( $wh )
		{
			if( empty( $filters['client'] ) && empty( $filters['customer'] ) )
			{
				$filters['client'] = !empty( $filters['client'] )? $filters['client'] : ( is_json( $wh['estate_client'] )? json_decode( stripslashes( $wh['estate_client'] ), true ) : $wh['estate_client'] );
				$filters['customer'] = !empty( $filters['customer'] )? $filters['customer'] : ( is_json( $wh['estate_customer'] )? json_decode( stripslashes( $wh['estate_customer'] ), true ) : $wh['estate_customer'] );
			}
		}

		if( !empty( $filters ) ) $this->filters = $filters;
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
			<div class="row">
				<div class="col-md-4">
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

				<div class="col-md-4">
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
			</div>

			<div class="row">
				<div class="col-md-6">
					<label class="" for="flag">By Estate Client </label><br>
					<?php
						$filter = [ 'status'=>1, 'indication'=>1 ];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_client', 'estate_customer' ] ] );
						if( $wh )
						{
							if( is_json( $detail['serial2'] ) ) $detail['serial2'] = json_decode( stripslashes( $detail['serial2'] ), true );
							$Client = is_json( $wh['estate_client'] )? json_decode( stripslashes( $wh['estate_client'] ), true ) : $wh['estate_client'];
							$Customer = is_json( $wh['estate_customer'] )? json_decode( stripslashes( $wh['estate_customer'] ), true ) : $wh['estate_customer'];
						}

						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						if( $Client ) $filter['code'] = $Client;
						$options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, [] ), 'code', [ 'code', 'name' ], '' );
						
		                wcwh_form_field( 'filter[client][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['client'] )? $this->filters['client'] : '', $view 
		                ); 
					?>
				</div>
				
				<div class="col-md-6">
					<label class="" for="flag">By Estate Customer </label><br>
					<?php
		                $filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						if( $Customer ) $filter['id'] = $Customer;
						$options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [] ), 'id', [ 'code', 'uid', 'name' ], '' );
						
		                wcwh_form_field( 'filter[customer][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
		                ); 
					?>
				</div>
			</div>

			<div class="row">
				<div class="col-md-6">
					<label class="" for="flag">By Category </label><br>
					<?php
		                $filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'id', [ 'slug', 'name' ], '' );
						
		                wcwh_form_field( 'filter[category][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                        'options'=> $options, 'multiple'=>1
		                    ], 
		                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-6">
					<label class="" for="flag">By Item </label><br>
					<?php
						$filter = [];
						if( $this->seller ) $filter['seller'] = $this->seller;
						$options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ], '' );
						
		                wcwh_form_field( 'filter[product][]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
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
					$datas = $this->get_estate_item( $this->filters, $order, [] );

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
	public function get_estate_summary( $filters = [], $order = [], $args = [] )
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

		//----------------------------------
		if( isset( $filters['client'] ) && isset( $filters['customer'] ) )
		{
			$segment = $this->get_good_issue( $filters, false );
			$subsql = "{$segment} ";

			$subsql.= "UNION ALL ";

			$segment = $this->get_pos( $filters, false );
			$subsql.= "{$segment} ";
		}
		else if( isset( $filters['client'] ) && ! isset( $filters['customer'] ) )
		{
			$segment = $this->get_good_issue( $filters, false );
			$subsql = "{$segment} ";
		}
		else if( ! isset( $filters['client'] ) && isset( $filters['customer'] ) )
		{
			$segment = $this->get_pos( $filters, false );
			$subsql = "{$segment} ";
		}
		//----------------------------------
		
		$field = "DATE_FORMAT( a.date, '%Y-%m' ) AS months ";
		$field.= ", SUM( a.amount ) AS amount";

		$table = "( $subsql ) a ";
		
		$cond = "";
		$grp = "GROUP BY months ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'months' => 'ASC' ];
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

	public function get_estate_category( $filters = [], $order = [], $args = [] )
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

		//----------------------------------
		$segment = $this->get_good_issue( $filters, false );
		$subsql = "{$segment} ";

		$subsql.= "UNION ALL ";

		$segment = $this->get_pos( $filters, false );
		$subsql.= "{$segment} ";
		//----------------------------------
		
		$field = "a.category_code, a.category ";
		$field.= ", SUM( a.qty ) AS qty, SUM( a.weight ) AS metric, SUM( a.amount ) AS amount ";

		$table = "( $subsql ) a ";
		
		$cond = "";
		$grp = "";

		$grp = "GROUP BY a.category_code ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'amount' => 'DESC', 'a.category_code' => 'ASC' ];
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

	public function get_estate_item( $filters = [], $order = [], $args = [] )
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

		//----------------------------------
		$segment = $this->get_good_issue( $filters, false );
		$subsql = "{$segment} ";

		$subsql.= "UNION ALL ";

		$segment = $this->get_pos( $filters, false );
		$subsql.= "{$segment} ";
		//----------------------------------
		
		$field = "a.item_code, a.item_name ";
		$field.= ", SUM( a.qty ) AS qty, SUM( a.weight ) AS metric, SUM( a.amount ) AS amount ";

		$table = "( $subsql ) a ";
		
		$cond = "";
		$grp = "";

		$grp = "GROUP BY a.item_code ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'amount' => 'DESC', 'a.item_code' => 'ASC' ];
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

		public function get_good_issue( $filters = [], $run = false )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			if( isset( $filters['seller'] ) )
			{
				$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
				$dbname = ( $dbname )? $dbname."." : "";
			}

			$field = "doh.docno AS order_no, doh.doc_date AS date, h.doc_date AS receive_date ";
			$field.= ", cat.slug AS category_code, cat.name AS category ";
			$field.= ", i.code AS item_code, i.name AS item_name, i._uom_code AS uom ";
			$field.= ", d.bqty AS qty, IFNULL( dma.meta_value, d.bunit ) AS weight, dmb.meta_value AS price, ROUND( d.bqty * dmb.meta_value, 2 ) AS amount, mc.meta_value AS remark ";
			
			$table = "{$dbname}{$this->tables['document']} h ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'good_issue_type' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = h.doc_id AND mb.item_id = 0 AND mb.meta_key = 'client_company_code' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = h.doc_id AND mc.item_id = 0 AND mc.meta_key = 'remark' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dma ON dma.doc_id = h.doc_id AND dma.item_id = d.item_id AND dma.meta_key = 'sunit' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} dmb ON dmb.doc_id = h.doc_id AND dmb.item_id = d.item_id AND dmb.meta_key = 'sprice' ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} grd ON grd.doc_id = d.ref_doc_id AND grd.item_id = d.ref_item_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} dod ON dod.doc_id = grd.ref_doc_id AND dod.item_id = grd.ref_item_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document']} doh ON doh.doc_id = dod.doc_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} doma ON doma.doc_id = dod.doc_id AND doma.item_id = dod.item_id AND doma.meta_key = 'ucost'  ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} i ON i.id = d.product_id ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = i.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			$cond = $wpdb->prepare( "AND h.doc_type = %s AND h.status >= %d AND d.status > %d ", 'good_issue', 6, 0 );
			$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", 'direct_consume' );

			if( isset( $filters['from_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.doc_date >= %s ", $filters['from_date'] );
			}
			if( isset( $filters['to_date'] ) )
			{
				$cond.= $wpdb->prepare( "AND h.doc_date <= %s ", $filters['to_date'] );
			}
			if( isset( $filters['client'] ) )
			{
				if( is_array( $filters['client'] ) )
					$cond.= "AND mb.meta_value IN ('" .implode( "','", $filters['client'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND mb.meta_value = %d ", $filters['client'] );
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

			$grp = "";
			$ord = "";

			$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
			return $query;
		}
		public function get_pos( $filters = [], $run = false )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			if( isset( $filters['seller'] ) )
			{
				$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
				$dbname = ( $dbname )? $dbname."." : "";
			}

			$field = "c.meta_value AS order_no, a.post_date AS date, '' AS receive_date ";
			$field.= ", cat.slug AS category_code, cat.name AS category ";
			$field.= ", it.code AS item_code, it.name AS item_name, it._uom_code AS uom ";
			$field.= ", k.meta_value AS qty, ROUND( k.meta_value * n.meta_value, 3 ) AS weight, ROUND( l.meta_value, 2 ) AS price, ROUND( m.meta_value, 2 ) AS amount, e.meta_value AS remark ";
			
			$table = "{$dbname}{$wpdb->posts} a ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = 'customer_id' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_order_number' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = '_order_total' ";
			$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = 'order_comments' ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_price' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_line_total' ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} n ON n.order_item_id = i.order_item_id AND n.meta_key = '_unit' ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['items']} it ON it.id = j.meta_value ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = it.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

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
				if( is_array( $filters['customer'] ) )
					$cond.= "AND b.meta_value IN ('" .implode( "','", $filters['customer'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND b.meta_value = %d ", $filters['customer'] );
			}
			if( isset( $filters['product'] ) )
			{
				if( is_array( $filters['product'] ) )
					$cond.= "AND it.id IN ('" .implode( "','", $filters['product'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND it.id = %d ", $filters['product'] );
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

			$grp = "";
			$ord = "";

			$query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );
		
			return $query;
		}
	
} //class

}