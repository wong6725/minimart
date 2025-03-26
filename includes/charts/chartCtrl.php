<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Chart" ) ) include_once( WCWH_DIR . "/includes/chart.php" ); 

if ( !class_exists( "WCWH_Chart_Controller" ) ) 
{

class WCWH_Chart_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_charts";

	protected $primary_key = "";

	public $Notices;
	public $className = "Chart_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newChart',
	);

	public $useFlag = false;

	private $temp_data = array();
	
	private $unique_field = array( 'name', '_sku' );

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->arrangement_init();
		
		$this->set_logic();

		add_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10, 2 );
	}
	
	public function __destruct() 
	{
        remove_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10 );
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
		$this->Logic = new WCWH_Chart();
	}

	public function get_section_id()
	{
		return $this->section_id;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			
		);
	}

	protected function get_uniqueFields()
	{
		return $this->unique_field;
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

	public function validate_unique( $action, $datas = array() )
	{
		$succ = true;

		$unique = $this->get_uniqueFields();
		if( $unique )
		{
			foreach( $unique as $key )
			{
				if( empty( $datas[ $key ] ) ) continue;

				$result = $this->Logic->get_infos( [ $key => $datas[$key] ], [], true );
				if( $result ) 
				{	
					if( ! $datas[ $this->get_primaryKey() ] || 
						( $datas[ $this->get_primaryKey() ] && $datas[ $this->get_primaryKey() ] != $result[ $this->get_primaryKey() ] ) )
					{
						$succ = false;
					}
				}
			}
		}

		if( ! $succ )
			$this->Notices->set_notice( 'not-unique', 'error' );

		return $succ;
	}

	public function docno_replacer( $sdocno, $doc_type = '' )
	{
		if( $doc_type && $doc_type == $this->section_id )
		{	
			$datas = $this->temp_data;
			$ref = array();
			
			if( $datas['grp_id'] )
			{
				$ref = apply_filters( 'wcwh_get_item_group', [ 'id'=>$datas['grp_id'] ], [], true, [] );
			}
			
			$find = [ 
				'GrpCode' => '{GrpCode}',
			];

			$replace = [ 
				'GrpCode' => ( $ref['prefix'] )? $ref['prefix'] : '',
			];

			$sdocno = str_replace( $find, $replace, $sdocno );
		}

		return $sdocno;
	}

	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */

	public function get_chart_data( $datas )
	{
		$in_Days_data = [
			'datasets' => [
				[
					'label' => 'seller a item b',
					'data' => [
						//Order can be not arranged but the x[0]( 1 ) must be the label of  y[0]( 22 )
						'x' => [ '1', '2', '5', '23', '22', '25','28', '3', '6' ],
						'y' => [ 22, 33, 10, 45, 32, 48, 75, 11, 4 ]
					]
				]
			]
		];

		$in_Dates_data = [
			'datasets' => [
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
				]
			]
		];
		

		$pie_or_doughnut_data = [
			//The order must be correct likes labels[0] ( Pie or Doughnut Section 1 ) is used for data[0] ( 20 )
			'labels' => [ 'Pie Or Doughnut Section 1', 'Pie Or Doughnut Section 2', 'Pie Or Doughnut Section 3', 'Pie Or Doughnut Section 4', 'Pie Or Doughnut Section 5' ],
			'data' => [ 20, 10, 30, 20, 20 ]
		];

		$chtData = $in_Dates_data;
		$chtType = 'mixed';
		$chartDatas = $this->Logic->chart_generator( $chtData, $chtType, [ 'legend_pos' => $datas['filter']['legendPos'], 'title' => 'Testing for args', 'x_axis_title' => 'Title of x Axis', 'y_axis_title' => 'Title of y Axis', 'mixed_chart_type' => [ [0,1], 'bar' ], 'line_tension' => 0.2, 'colors_num' => 5, 'unit' => 'RM' ] );
		
		return $chartDatas;
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/charts/chartListing.php" ); 
			$Inst = new WCWH_Chart_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
			];
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			$Inst->search_box( 'Search', 's' );
			//$Inst->advSearch_onoff();
			?>
			<div id="chart-container">
			    <canvas id="graphCanvas"></canvas>
			</div>
			<?php
		?>
		</form>
		<?php
	}
	
} //class

}