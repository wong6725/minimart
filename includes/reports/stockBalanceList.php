<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_StockBalance_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $default_column_title = array();
	public $default_column_inclusion = array();

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();
	}

	public function set_refs()
	{
		global $wcwh;
		$this->refs = ( $this->refs )? $this->refs : $wcwh->get_plugin_ref();
	}
	
	public function get_columns() 
	{
		//$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		$cols = array(
			//'no'					=> '',
			"item_code"				=> "Item Code",
			"item_name"				=> "Item Name",
			"gtin"					=> "Gtin",
			"extra_gtin"			=> "Extra Gtin",
			"category_code"			=> "Category Code",
			"category_name"			=> "Category Name",
			"category_group_code"	=> "Category Group Code",
			"category_group_name"	=> "Category Group Name",
			"item_group"			=> "Group",
			"store_type"			=> "Store Type",
			"uom"					=> "UOM",
			"base_item_code"		=> "Base Item Code",
			"base_conversion"		=> "Base Conversion",
			"required_metric"		=> "Need Metric (kg/l)",
			"in_qty"				=> "In Qty",
			"out_qty"				=> "Out Qty",
			"pos_qty"				=> "POS Qty",
			"total_out_qty"			=> "Total Out Qty",
			"foc_qty"				=> "FOC Qty",
			"balance_qty"			=> "Balance Qty",
			"stock_in_amt"			=> "Stock In Amt",
			"stock_out_amt"			=> "Stock Out Amt",
			"pos_amt"				=> "POS Amt",
			"balance_cost"			=> "Balance Cost",
			"avg_unit_price"		=> "Bal Unit Price",
			"converted_qty"			=> "Converted Qty",
			"converted_bal_qty"		=> "Converted Bal Qty",
		);

		$filters = $this->filters;

		return $cols;
	}

	public function get_hidden_column()
	{
		$col = [ 'gtin', 'extra_gtin', 'category_code', 'category_group_code', 'category_group_name', 'foc_qty', 'converted_qty', 'converted_bal_qty' ];

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
		];

		return $cols;
	}

	public function get_bulk_actions() 
	{
		$actions = array();
		
		return $actions;
	}

	public function no_items() 
	{
		echo "<strong class='font16'>Please Submit for Report Generating.</strong>";
	}

	public function get_data_alters( $datas = array() )
	{
		return $datas;
	}

    public function render()
    {
		if( ! $this ) return;

		$this->search_box( 'Submit', "s" );
		$this->prepare_items();
		$this->display();
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">Until Date</label><br>
				<?php
					wcwh_form_field( 'filter[to_date]', 
	                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
	                    isset( $this->filters['to_date'] )? $this->filters['to_date'] : '', $view 
	                );
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">Hour</label><br>
				<?php
					$hour = [];
					for( $i = 0; $i <= 23; $i++ )
					{
	                    $h = str_pad( $i, 2, "0", STR_PAD_LEFT );
	                    $hh = date( 'h A', strtotime( " {$h}:00:00" ) );
	                    $hour[ $i ] = $hh;
                	}
	                
	                wcwh_form_field( 'filter[to_hour]', 
	                    [ 'id'=>'to_hour', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[],
	                        'options'=> $hour
	                    ], 
	                    isset( $this->filters['to_hour'] )? $this->filters['to_hour'] : '23', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">Minute</label><br>
				<?php
					$min = [];
	                for( $i = 0; $i <= 50; $i+= 5 )
	                {
	                    $m = str_pad( $i, 2, "0", STR_PAD_LEFT );
	                    $min[ $i ] = $m;
	                }

	                wcwh_form_field( 'filter[to_minute]', 
	                    [ 'id'=>'to_minute', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[],
	                        'options'=> $min
	                    ], 
	                    isset( $this->filters['to_minute'] )? $this->filters['to_minute'] : '', $view 
	                );
				?>
			</div>
		<?php
			$filter = [];
			$filter['wh_code'] = $this->filters['wh_code'];	
			if( $this->seller ) 
			{
				if( !$this->filters['wh_code'] )
				{
					$warehouse = apply_filters( 'wcwh_get_warehouse', ['id'=>$this->seller, 'status'=>1, 'visible'=>1], [], true, [ 'company'=>1 ] );
				}				

				$filter['seller'] = $this->seller;
				$filter['wh_code'] = ($warehouse)? $warehouse['code'] : $filter['wh_code'];				
			}

			$options = options_data( apply_filters( 'wcwh_get_storage', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ], '' );

			if( $options ):
		?>
			<div class="segment col-md-4">
				<label class="" for="flag">Inventory Type <sup>Current: <?php echo $options[$this->filters['strg_id']]; ?></sup></label>
				<?php
					wcwh_form_field( 'filter[strg_id]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        'options'=> $options, 'offClass'=>true
	                    ], 
	                    isset( $this->filters['strg_id'] )? $this->filters['strg_id'] : '', $view 
	                ); 
				?>
			</div>
		<?php endif; ?>

			<div class="segment col-md-4">
				<label class="" for="flag">By Item Group</label><br>
				<?php
					$filter = [];
					if( $this->seller ) $filter['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_group', $filter, [], false, [] ), 'id', [ 'code', 'name' ], '' );
                
	                wcwh_form_field( 'filter[grp_id][]', 
	                    [ 'id'=>'grp_id', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['grp_id'] )? $this->filters['grp_id'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By UOM</label>
				<?php
					$filter = [];
					if( $this->seller ) $filter['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_uom', $filter, [], false, [] ), 'code', [ 'code', 'name' ], '' );
                
	                wcwh_form_field( 'filter[_uom_code][]', 
	                    [ 'id'=>'_uom_code', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['_uom_code'] )? $this->filters['_uom_code'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-6">
				<label class="" for="flag">By Category</label><br>
				<?php
					$filter = [];
                	if( $this->seller ) $filter['seller'] = $this->seller;
                	$options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'term_id', [ 'slug', 'name' ], '' );
	                
	                wcwh_form_field( 'filter[category][]', 
	                    [ 'id'=>'category', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-6">
				<label class="" for="flag">By Items</label>
				<?php
					$filter = [];
					if( $this->seller ) $filter['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'usage'=>0 ] ), 'id', [ 'code', '_sku', '_uom_code', 'name', 'status_name' ], '' );
                
	                wcwh_form_field( 'filter[item_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['item_id'] )? $this->filters['item_id'] : '', $view 
	                ); 
				?>
			</div>

			<!-- <div class="segment col-md-4">
				<label class="" for="flag">By Inconsistent Metric (kg/l)</label><br>
				<?php
	                $options = [ ''=>'All', '1'=>'Yes', '0'=>'No' ];
	                
	                wcwh_form_field( 'filter[inconsistent_unit]', 
	                    [ 'id'=>'inconsistent_unit', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['inconsistent_unit'] )? $this->filters['inconsistent_unit'] : '', $view 
	                ); 
				?>
			</div>-->

			<div class="segment col-md-4">
				<label class="" for="flag">By Sellable</label><br>
				<?php
	                $options = [ '' => 'All', 'yes' => 'Sellable', 'no' => "Not For Sale", 'force' => 'Force Sellable' ];

		            wcwh_form_field( 'filter[sellable]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options, 
		                ], 
		                isset( $this->filters['sellable'] )? $this->filters['sellable'] : '', $view 
		            ); 
				?>
			</div>
		</div>

		<!--<div class="row">
			<div class="segment col">
				<label class="" for="flag">Listing Column Selection</label>
				<?php
					$options = options_data( $this->default_column_title, '', [], '' );
                
	                wcwh_form_field( 'filter[d_column][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['d_column'] )? $this->filters['d_column'] : $this->default_column_inclusion, $view 
	                ); 
				?>
			</div>
		</div>-->
	<?php
	}

	public function get_status_action( $item )
	{
		return array(
			'0' => array(
				'view' => [ 'wcwh_user' ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
			),
		);
	}

	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
    
	
} //class