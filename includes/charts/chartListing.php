<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Chart_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_charts";

	public $useFlag;

	private $users;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		$this->users = get_simple_users();
	}

	public function set_refs()
	{
		global $wcwh;
		$this->refs = ( $this->refs )? $this->refs : $wcwh->get_plugin_ref();
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}
	
	public function get_columns() 
	{
		return array(

		);
	}

	public function get_hidden_column()
	{
		/*$col = [ "store_type" ];
		if( ! $this->useFlag ) $col[] = 'approval';

		return $col;*/
	}

	public function get_sortable_columns()
	{
		$col = [

		];

		return $col;
	}

	public function get_data_alters( $datas = array() )
	{
		return $datas;
	}

    public function render()
    {
		if( ! $this ) return;

		
		$this->prepare_items();
		$this->display();
	}

	public function filter_search()
	{
		$from_date = date( 'Y-m-d', strtotime( $this->filters['from_date'] ) );
		$to_date = date( 'Y-m-d', strtotime( $this->filters['to_date'] ) );
		
		$def_from = date( 'm/d/Y', strtotime( $this->filters['from_date'] ) );
		$def_to = date( 'm/d/Y', strtotime( $this->filters['to_date'] ) );
	?>
		<div class="row">
			<div class="col-md-4">
				<label class="" for="flag">From Date <sup>Current: <?php echo $this->filters['from_date']; ?></sup></label><br>
				<?php
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
			<div class="segment col-md-2">
				<label class="" for="flag">Chart Type</label><br>
				<?php
	                $options = [ 'line'=>'line', 'pie'=>'pie', 'doughnut'=>'doughnut', 'mixed' => 'mixed', 'bar' => 'bar' ];
	                
	                wcwh_form_field( 'filter[chartType]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['chartType'] )? $this->filters['chartType'] : '', $view 
	                ); 
				?>
			</div>
		</div>
		<div class="row">
			<div class="segment col-md-2">
				<label class="" for="flag">Legend Position</label><br>
				<?php
	                $options = [ 'top'=>'Top', 'bottom'=>'Bottom', 'left'=>'Left', 'right'=>'Right' ];
	                
	                wcwh_form_field( 'filter[legendPos]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['legendPos'] )? $this->filters['legendPos'] : '', $view 
	                ); 
				?>
			</div>
		</div>
	<?php
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */

} //class