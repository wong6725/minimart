<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Chart" ) ) include_once( WCWH_DIR . "/includes/chart.php" ); 

if ( !class_exists( "WCWH_QueryChart" ) ) 
{

class WCWH_QueryChart extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "QueryChart";

	public $Chart;

	public $tplName = array(
		'export' => 'exportQueryChart',
	);
	
	protected $tables = array();

	public $filters = array();

	public $datas = array();

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->Chart = new WCWH_Chart();
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

					if( !empty( $datas['rQuery'] ) ) $params['rQuery'] = $datas['rQuery'];
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
		$order = [];

		$rQuery = $params['rQuery'];
		$rQuery = $rQuery? $rQuery : get_transient( get_current_user_id().$this->className );

		return $this->get_query_chart( $rQuery );
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
	public function export_form()
	{
		$action_id = 'query_chart';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		do_action( 'wcwh_templating', 'chart/export-query-chart.php', $this->tplName['export'], $args );
	}

	public function query_chart( $filters = array(), $order = array() )
	{
		$action_id = 'query_chart';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
			<div class="form-row">
		        <div class="col form-group">
		        	 <?php
		            	$rQuery = '';
		            	if( !empty($filters['rQuery']) ){
		            		$filters['rQuery'] = str_replace("\\", "", $filters['rQuery']);
		            		$this->filters = $filters;
		            		$rQuery = $filters['rQuery'];
		            	}
		                wcwh_form_field( 'filter[rQuery]', 
		                    [ 'id'=>'rQuery', 'type'=>'textarea', 'label'=>'Raw Query', 'required'=>false, 'attrs'=>[] ], 
		                    $rQuery, $view 
		                );
		            ?>
		        </div>
	    	</div>
			<div class="form-row">
			<div class="col form-group">
					
					<?php
		                wcwh_form_field( 'filter[title]', 
		                    [ 'id'=>'', 'type'=>'text', 'label'=>'Chart Title', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                    ], 
		                    isset( $this->filters['title'] )? $this->filters['title'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col form-group" id="xAxisLabel_div">
					
					<?php
		                wcwh_form_field( 'filter[xAxisLabel]', 
		                    [ 'id'=>'xAxisLabel', 'type'=>'text', 'label'=>'X Axis Label', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                    ], 
		                    isset( $this->filters['xAxisLabel'] )? $this->filters['xAxisLabel'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col form-group" id="yAxisLabel_div">
					
					<?php
		                wcwh_form_field( 'filter[yAxisLabel]', 
		                    [ 'id'=>'yAxisLabel', 'type'=>'text', 'label'=>'Y Axis Label', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                    ], 
		                    isset( $this->filters['yAxisLabel'] )? $this->filters['yAxisLabel'] : '', $view 
		                ); 
					?>
				</div>
					</div>
					<div class="form-row">
				<div class="col-md-3 form-group">
					<?php
		                $options = [ ''=>'-----SELECT-----','bar' => 'Bar' ,'line'=>'Line','mixed' => 'Mixed', 'pie'=>'Pie', 'doughnut'=>'Doughnut' ];
		                
		                wcwh_form_field( 'filter[chartType]', 
		                    [ 'id'=>'chartType', 'type'=>'select', 'label'=>'Chart Type', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['chartType'] )? $this->filters['chartType'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-3 form-group" id="stepped_div" style='display:none'>
					<?php
		                $options = [ 'false'=>'False','true'=>'True'];
		                
		                wcwh_form_field( 'filter[stepped]', 
		                    [ 'id'=>'stepped', 'type'=>'select', 'label'=>'Stepped Line', 'required'=>false, 'attrs'=>['disabled'], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['stepped'] )? $this->filters['stepped'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-3 form-group" id="tension_div" style='display:none'>
					<?php
		                $options = [ '0'=>'0','0.1'=>'0.1','0.2'=>'0.2','0.3'=>'0.3','0.4'=>'0.4','0.5'=>'0.5','0.6'=>'0.6','0.7'=>'0.7'
						,'0.8'=>'0.8','0.9'=>'0.9','1.0'=>'1.0'];
		                
		                wcwh_form_field( 'filter[tension]', 
		                    [ 'id'=>'tension', 'type'=>'select', 'label'=>'Tension', 'required'=>false, 'attrs'=>['disabled'], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['tension'] )? $this->filters['tension'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-3 form-group" id="fill_div" style='display:none'>
					<?php
		                $options = [ 'false'=>"False",'true'=>"True"];
		                
		                wcwh_form_field( 'filter[fill]', 
		                    [ 'id'=>'fill', 'type'=>'select', 'label'=>'Area Fill', 'required'=>false, 'attrs'=>['disabled'], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['fill'] )? $this->filters['fill'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-3 form-group" id="horizontal_div" style='display:none'>
					<?php
		                $options = [ 'false'=>"False",'true'=>"True"];
		                
		                wcwh_form_field( 'filter[horizontal]', 
		                    [ 'id'=>'horizontal', 'type'=>'select', 'label'=>'Horizontal Bar', 'required'=>false, 'attrs'=>['disabled'], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['horizontal'] )? $this->filters['horizontal'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-3 form-group" id="stacked_div" style='display:none'>
					<?php
		                $options = [ 'false'=>"False",'true'=>"True"];
		                
		                wcwh_form_field( 'filter[stacked]', 
		                    [ 'id'=>'stacked', 'type'=>'select', 'label'=>'Stacked', 'required'=>false, 'attrs'=>['disabled'], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['stacked'] )? $this->filters['stacked'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-3 form-group" id="stack_group_div" style='display:none'>
					<?php
		                		                
		                wcwh_form_field( 'filter[stack_group]', 
		                    [ 'id'=>'stack_group', 'type'=>'text', 'label'=>'Stack Group', 'required'=>false, 'attrs'=>['disabled'], 'class'=>[],
							'description'=>'Enter more parameter using , eg. amt_cash,amt_credit' 
		                    ], 
		                    isset( $this->filters['stack_group'] )? $this->filters['stack_group'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-3 form-group" id="mixed_data_div" style='display:none'>
					<?php
		                		                
		                wcwh_form_field( 'filter[mixed_data]', 
		                    [ 'id'=>'mixed_data', 'type'=>'text', 'label'=>'Bar Data', 'required'=>false, 'attrs'=>['disabled'], 'class'=>[],
							'description'=>'Enter more parameter using , eg. amt_cash,amt_credit'
		                    ], 
		                    isset( $this->filters['mixed_data'] )? $this->filters['mixed_data'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col-md-3 form-group" id="line_data_div" style='display:none'>
					<?php
		                		                
		                wcwh_form_field( 'filter[line_data]', 
		                    [ 'id'=>'line_data', 'type'=>'text', 'label'=>'Line Data', 'required'=>false, 'attrs'=>['disabled'], 'class'=>[],
							'description'=>'Enter more parameter using , eg. amt_cash,amt_credit' 
		                    ], 
		                    isset( $this->filters['line_data'] )? $this->filters['line_data'] : '', $view 
		                ); 
					?>
				</div>
			</div>
			<div class="form-row">
						
		        <div class="col form-group">
					<?php
		                wcwh_form_field( 'filter[data_group]', 
		                    [ 'id'=>'', 'type'=>'text', 'label'=>'Chart Data Group', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                    	'description'=>'Enter more parameter using , eg. amt_cash,amt_credit (only applicable for bar, line and mixed chart)'], 
		                    isset( $this->filters['data_group'] )? $this->filters['data_group'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col form-group" id="x_key_div">
					<?php
		                wcwh_form_field( 'filter[x_key]', 
		                    [ 'id'=>'x_key', 'type'=>'text', 'label'=>'Chart X Key', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                    ], 
		                    isset( $this->filters['x_key'] )? $this->filters['x_key'] : '', $view 
		                ); 
					?>
				</div>
				<div class="col form-group" id="y_key_div">
					<?php
		                wcwh_form_field( 'filter[y_key]', 
		                    [ 'id'=>'y_key', 'type'=>'text', 'label'=>'Chart Y Key', 'required'=>false, 'attrs'=>[], 'class'=>[],
							    'description'=>'-Multiple parameter only applicable for pie, doughnut and polar area chart'], 
		                    isset( $this->filters['y_key'] )? $this->filters['y_key'] : '', $view 
		                ); 
					?>
				</div>
					</div>
	    	
			<div class="form-row">
				<div class="col-md-3 form-group">
					<?php
		                $options = [ 'top'=>'Top', 'bottom'=>'Bottom', 'left'=>'Left', 'right'=>'Right' ];
		                
		                wcwh_form_field( 'filter[legendPos]', 
		                    [ 'id'=>'', 'type'=>'select', 'label'=>'Legend Position', 'required'=>false, 'attrs'=>[], 'class'=>[],
		                        'options'=> $options
		                    ], 
		                    isset( $this->filters['legendPos'] )? $this->filters['legendPos'] : '', $view 
		                ); 
					?>
				</div>
			</div>
			<div class="row">
				<div class="col-md-4">
					<br>
					<button type="submit" id="search-submit" class="btn btn-primary btn-sm">Search</button>
				</div>
			</div>
						
		<?php
			include_once( WCWH_DIR."/includes/listing.php" );
			$Inst = new WCWH_Listing();
			$Inst->advSearch = array( 'isOn'=>1 );
			$Inst->per_page_limit = 1000;

			$datas = $this->get_query_chart( $rQuery );
			$datas = ( $datas )? $datas : array();
			if( $datas ){ 
				set_transient( get_current_user_id().$this->className, $rQuery );
				$this->datas = $datas;
			}

			//$Inst->search_box( 'Search', 's' );
			$keys = array();
			if( !empty($datas) ){
				$keys = array_keys( $datas[0] );
			}
			?>
			<div id="chart-container">
			<canvas id="graphCanvas"  data-pageLoadChart="1"></canvas>
			</div>
			<?php
			echo $Inst->get_listing( $keys, $datas );		
		?>
		</form>
		<?php
	}

	/*
		$params['data_group']
		$params['x_key']
		$params['y_key']
		$params['chartType']
		$params['legendPos']
	*/
	public function get_chart( $filters = [] )
	{	
		$groupArr=[];
		$yKeyArr=[];
		$xKeyArr=[];
		$mixedData=[];
		$stackGroup=[];
		$line_arr=[];
		$params = $filters['filter'];
		
		//if( ! $params['data_group'] || ! $params['y_key'] ) return false;
		$params['chartType'] = ( ! $params['chartType'] )? 'line' : $params['chartType'];

		$params['data_group'] = preg_replace('/\s+/', '', strtolower($params['data_group']));
		$params['mixed_data'] = preg_replace('/\s+/', '', strtolower($params['mixed_data']));
		$params['line_data']= preg_replace('/\s+/', '', strtolower($params['line_data']));
		$params['x_key'] =  preg_replace('/\s+/', '', strtolower($params['x_key']));
		$params['y_key'] =  preg_replace('/\s+/', '', strtolower($params['y_key']));

		if( ! $this->datas )
		{
			$rQuery = $filters['rQuery'];
			$rQuery = $rQuery? $rQuery : get_transient( get_current_user_id().$this->className );

			$this->datas = $this->get_query_chart( $rQuery );
		}
		$datas = $this->datas;

		if(($params['chartType']!="pie"&&$params['chartType']!="doughnut")){

			//check if input of data_group contains comma, convert it into array
			// multiple data for x-axis
			if(strstr($params['data_group'],","))
			{
				//y-axis value will be based on data of data_group entered
				$params['y_key']='~chart_group';
				$arr = explode(",",$params['data_group']);
				foreach($arr as $key=>$val)
				{
					$groupArr[$val]=$val;
				}
			}else
			{
				//single data for x-axis
				//assign a value to the key of the array to serve as label, eg ['amt_credit'=>'amt_credit'] amt_credit will be used as label for legend
				$groupArr[$params['data_group']]=$params['y_key'];

				//x-axis value will be based on data of data_group entered
				$params['x_key']='~chart_group';
			}  

			
			if($params['horizontal']=='true'&&strstr($params['data_group'],"-")){
				

				//x-axis value will be based on data of data_group entered 
				$xKey = $params['x_key'];

				//y-axis label will be based on data_group entered
				$yKey = $params['data_group'];

			}else if($params['horizontal']=='false'&&strstr($params['data_group'],"-")){
				

				//x-axis label will be based on data of data_group entered
				$xKey =  $params['data_group'];

				//data of y-axis will be based on data_group entered
				$yKey = $params['x_key'];
			}
			else if($params['horizontal']=='true'){

				//swap data of x and y axis
				$xKey = $params['y_key'];
				$yKey = $params['x_key'];
	
				$xLabel = $params['yAxisLabel'];
				$yLabel = $params['xAxisLabel'];
	
			}else{

				$xKey = $params['x_key'];
				$yKey = $params['y_key'];
	
				$xLabel = $params['xAxisLabel'];
				$yLabel = $params['yAxisLabel'];
			}
			
			//check if mixed_data contains comma, convert it into array for displaying bar data in mixed chart
			if(strstr($params['mixed_data'],",")&&$params['chartType']=='mixed')
			{
				$bar_arr = explode(",",$params['mixed_data']);
		
				foreach($bar_arr as $key=>$val)
				{
					$k=array_search($val,$arr,false);
					array_push($mixedData,$k);
				}

			}else
			{
				$k=array_search($params['mixed_data'],$arr,false);
				$mixedData = $k;
			}

			//check if line_data contains comma, convert it into array for displaying line data in mixed chart
			if(strstr($params['line_data'],",")&&$params['chartType']=='mixed')
			{
				$line_arr = explode(",",$params['line_data']);
			}else{
				array_push($line_arr, $params['line_data']);
			}
			
			
			if(!empty($params['stack_group'])&&strstr($params['stack_group'],","))
			{
				$stack_arr = explode(",",$params['stack_group']);

				foreach($stack_arr as $key=>$val)
				{
					$s=array_search($val,$arr,false);
					array_push($stackGroup,$s);
				}
			}else if(empty($params['stack_group']))
			{
				$stackName = '';
				$stackGroup = [];
			}			
			else{
				
				$s=array_search($params['stack_group'],$arr,false);
				$stackGroup = $s;
			}

		}
		$value= empty($groupArr)? $params['data_group']:$groupArr;

		
		
		
		if($params['chartType']=='pie'||$params['chartType']=='doughnut')
		{
			if(strstr($params['y_key'],","))
			{
				
				$yarr = explode(",",$params['y_key']);
				foreach($yarr as $key=>$val)
				{
					$yKeyArr[$val]=$val;
				}				
			}
			$count= count($yKeyArr);
			$params['legend_callback'] = true;
			$yKey=( empty($yKeyArr) )? $params['y_key'] : $yKeyArr;
		}

		

		if( $datas )
		{
			$chart = [
				'group' => $value, //[ 'amt_credit' => 'Amount Credit', 'total' => 'Order Total', 'amt_cash' => 'Amount Cash' ],//
				'x' => $xKey,
				'y' => $yKey, 
				//'label' => [ 'item_code', 'item_name' ],
				//'xformat' => [ 'date' => 'Y-m-d' ],
			];
			$chartDatas = $this->Chart->chart_data_conversion( $datas, $params['chartType'], $chart );
			$chart_info = $this->Chart->chart_generator( $chartDatas, $params['chartType'], [ 'colors_num' => 76,  
			'y_axis_title' => $yLabel,'x_axis_title'=>$xLabel,'legend_pos'=>$params['legendPos'],  'mixed_chart_type'  => [ $mixedData,'bar'],
			'title'=>$params['title'],'line_stepped'=>filter_var($params['stepped'],FILTER_VALIDATE_BOOLEAN),'line_tension'=>$params['tension'],
			'line_fill'=>filter_var($params['fill'],FILTER_VALIDATE_BOOLEAN),'horizontal_bar'=>filter_var($params['horizontal'],FILTER_VALIDATE_BOOLEAN),
			'x_axis_stacked'=>filter_var($params['stacked'],FILTER_VALIDATE_BOOLEAN),'y_axis_stacked'=>filter_var($params['stacked'],FILTER_VALIDATE_BOOLEAN),
			'mixed_Data' => $line_arr,'stackGroup'=>[$stackGroup,'stack 0'],'legend_callback'=>filter_var($params['legend_callback'],FILTER_VALIDATE_BOOLEAN),'count'=>$count]);
		}
/*		(boolean)json_decode(strtolower($params['stepped']))
		$in_Dates_data = [
			[
				'label' => 'seller a item a',
				'data' => [
					//Order can be not arranged but the x[0]( 2021/02/21 ) must be the label of  y[0]( 20 )
					'x' => [ '2021/02/21', '2021/03/12', '2021/04/01', '2021/05/06', '2021/06/07', '2021/07/05', '2021/08/12', '2021/01/02', '2020/09/31' ],
					'y' => [ 20, 50, 20, 30, 43, 28, 25, 10, 80 ]
				]
			],
			[
				'label' => 'seller a item b',
				'data' => [
					'x' => [ '2021/02/21', '2021/03/15', '2021/04/02', '2021/05/06', '2021/06/07', '2021/07/05', '2021/08/12', '2021/01/02' ],
					'y' => [ 22, 33, 22, 45, 32, 48, 75, 25 ]
				]
			],
			[
				'label' => 'seller a item c',
				'data' => [
					'x' => [ '2021/02/21', '2021/03/15', '2021/04/02', '2021/05/06', '2021/06/07', '2021/07/05', '2021/08/12' ],
					'y' => [ 25, 28, 12, 55, 22, 48, 75 ]
				]
			],
		];
		

		$pie_or_doughnut_data = [
			[
				'labels' => [ 'Pie Or Doughnut Section 1', 'Pie Or Doughnut Section 2', 'Pie Or Doughnut Section 3', 'Pie Or Doughnut Section 4', 'Pie Or Doughnut Section 5' ],
				'data' => [
					'y' => [ 20, 10, 30, 20, 20 ]
				]
			],
		];

		$chtData = $pie_or_doughnut_data;
		$chtType = 'pie';
		$args = [
			'legend_pos' => $params['legendPos'], 
			'title' => 'Query Chart', 
			'x_axis_title' => 'X Axis', 
			'y_axis_title' => 'Y Axis', 
			'mixed_chart_type' => [ [0,1], 'bar' ], 
			'line_tension' => 0.2, 
			'colors_num' => 5, 
			'unit' => 'RM'
		];
		$chartDatas = $this->Chart->chart_generator( $chtData, $chtType, $args );
		*/
		
		return $chart_info;
	}


	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_query_chart( $rQuery = '' )
	{
		if( ! $rQuery ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;

		$sql = trim( $rQuery, ' /*!`@#$%^&-=+_<>?\\' );//Trim space and special characters
		$sql = str_replace("\\", "", $sql);
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
} //class

}