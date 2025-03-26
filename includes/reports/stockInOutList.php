<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_StockInOut_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $date_format;
	public $converse = [];
	public $i = 0;
	private $bal_qty = 0;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		$this->date_format = get_option( 'date_format' );
	}

	public function set_refs()
	{
		global $wcwh;
		$this->refs = ( $this->refs )? $this->refs : $wcwh->get_plugin_ref();
	}
	
	public function get_columns() 
	{
		return array(
			'no'			=> '',
			"product"		=> "Product",
			"category"		=> "Category",
			"uom"			=> "UOM",
			"doc_post_date" => "Date",
			"plus_sign"		=> "Transact",
			"bqty"			=> "Qty",
			"metric"		=> "Metric (kg/l)",
			"unit_price"	=> "Price",
			"total_price"	=> "Total Price",
			"unit_cost"		=> "Cost",
			"total_cost"	=> "Total Cost",
			"bal_qty"		=> "Bal Qty",
			"bal_unit"		=> "Bal Metric",
			"bal_price"		=> "Bal Price",
			"bal_amount"	=> "Bal Amount",
		);
	}

	public function get_hidden_column()
	{
		$col = array();

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [];

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

	public function get_status_action( $item )
	{
		return array();
	}
	
	public function filter_search()
	{
		$from_date = date( 'Y-m-d', strtotime( $this->filters['from_date'] ) );
		$to_date = date( 'Y-m-d', strtotime( $this->filters['to_date'] ) );
		
		$def_from = date( 'm/d/Y', strtotime( $this->filters['from_date'] ) );
		$def_to = date( 'm/d/Y', strtotime( $this->filters['to_date'] ) );
	?>
		<div class="form-row">
			<div class="segment col-4">
				<label class="" for="flag">From Date <sup>Current: <?php echo $this->filters['from_date']; ?></sup></label>
				<?php
					wcwh_form_field( 'filter[from_date]', 
	                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $from_date )? $from_date : '' 
	                ); 
				?>
			</div>

			<div class="segment col-4">
				<label class="" for="flag">To Date <sup>Current: <?php echo $this->filters['to_date']; ?></sup></label>
				<?php
					wcwh_form_field( 'filter[to_date]', 
	                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $to_date )? $to_date : '' 
	                ); 
				?>
			</div>
		</div>

		<div class="form-row">
			
			<div class="segment col-md-4">
				<label class="" for="flag">By Category </label>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_category', $filters, [], false, [] ), 'id', [ 'slug', 'name' ], '' );
					
	                wcwh_form_field( 'filter[category][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['category'] )? $this->filters['category'] : ''
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
			<label class="" for="flag">By UOM</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_uom', $filter, [], false, [] ), 'code', [ 'code', 'name' ], '' );
                
	                wcwh_form_field( 'filter[uom][]', 
	                    [ 'id'=>'uom', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['uom'] )? $this->filters['uom'] : ''
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Product </label>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;

					if( current_user_cans( [ 'item_visible_wh_reports' ] ) )
					{
						$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
					}
					else
					{
						$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'status_name' ], '' );
					}
					
	                wcwh_form_field( 'filter[product][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['product'] )? $this->filters['product'] : ''
	                ); 
				?>
			</div>

		</div>
	<?php
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden ) = $this->get_column_info();
		
		$qty_in = 0;
		$qty_out = 0;
		$unit_in = 0;
		$unit_out = 0;
		$t_total_price = 0;
		$t_total_cost = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $data )
			{
				$qty_in+= ( $data['plus_sign'] == '+' && $data['bqty'] )? $data['bqty'] : 0;
				$qty_out+= ( $data['plus_sign'] == '-' && $data['bqty'] )? $data['bqty'] : 0;
				$unit_in+= ( $data['plus_sign'] == '+' && $data['metric'] )? $data['metric'] : 0;
				$unit_out+= ( $data['plus_sign'] == '-' && $data['metric'] )? $data['metric'] : 0;
				$t_total_price+= ( $data['total_price'] )? $data['total_price'] : 0;
				$t_total_cost+= ( $data['total_cost'] )? $data['total_cost'] : 0;
			}
		}
		
		$colspan = 'no';
		$colnull = [ 'doc_post_date' ];
		foreach ( $columns as $column_key => $column_display_name ) 
		{
			if( $colnull && in_array( $column_key, $colnull ) ) continue;

			$class = array( 'manage-column', "column-$column_key", $column_key );

			if ( in_array( $column_key, $hidden ) ) 
			{
				$class[] = 'hidden';
			}

			$span	= ( $colspan === $column_key )? 'colspan="'.( sizeof( $colnull )+1 ).'"' : '';
			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = "id='$column_key'";

			if ( ! empty( $class ) ) 
			{
				$class = "class='" . join( ' ', $class ) . "'";
			}
			
			$column_display_name = '';
			if( $column_key == 'product' ) $column_display_name = 'TOTAL:';
			if( $column_key == 'bqty' ) $column_display_name = round_to( $qty_in - $qty_out, 2, 1, 1 );
			if( $column_key == 'metric' ) $column_display_name = round_to( $unit_in - $unit_out, 2, 1, 1 );
			if( $column_key == 'unit_price' ) $column_display_name = ($qty_in != 0) ? round_to($t_total_price / $qty_in, 5, 1, 1) : 0;
			if( $column_key == 'total_price' ) $column_display_name = round_to( $t_total_price, 2, 1, 1 );
			if( $column_key == 'unit_cost' ) $column_display_name = ($qty_out != 0) ? round_to($t_total_cost / $qty_out, 5, 1, 1) : 0;
			if( $column_key == 'total_cost' ) $column_display_name = round_to( $t_total_cost, 2, 1, 1 );
			if( $column_key == 'bal_amount' ) $column_display_name = round_to( $t_total_price - $t_total_cost, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
    public function column_no( $item ) 
	{	
		return $item['no'];
    }

	public function column_category( $item )
	{
		$html = [];
		if( $item['category_code'] ) $html[] = $item['category_code'];
		if( $item['category'] ) $html[] = $item['category'];

		return implode( ' - ', $html );
	}
	
	public function column_product( $item )
	{
		$html = [];
		if( $item['prdt_code'] ) $html[] = $item['prdt_code'];
		if( $item['prdt_name'] ) $html[] = $item['prdt_name'];

		return implode( ' - ', $html );
	}

    public function column_plus_sign( $item )
    {
    	return ( $item['plus_sign'] == '+' )? 'In +' : ( ( $item['plus_sign'] == '-' )? 'Out -' : '' );
    }
	
	public function column_bqty( $item )
	{
		return ( $item['bqty'] )? round_to( $item['bqty'], 2, 1, 1 ) : '';
	}

	public function column_metric( $item )
	{
		return ( $item['metric'] )? round_to( $item['metric'], 3, 1, 1 ) : '';
	}
	
	public function column_unit_price( $item )
	{
		return ( $item['unit_price'] > 0 )? round_to( $item['unit_price'], 5, 1, 1 ) : '';
	}
	
	public function column_total_price( $item )
	{
		return ( $item['total_price'] > 0 )? round_to( $item['total_price'], 2, 1, 1 ) : '';
	}
	
	public function column_unit_cost( $item )
	{
		return ( $item['unit_cost'] > 0 )? round_to( $item['unit_cost'], 5, 1, 1 ) : '';
	}

	public function column_total_cost( $item )
	{
		return ( $item['total_cost'] > 0 )? round_to( $item['total_cost'], 2, 1, 1 ) : '';
	}


	public function column_bal_qty( $item )
	{
		return ( $item['bal_qty'] )? round_to( $item['bal_qty'], 2, 1, 1 ) : '';
	}

	public function column_bal_unit( $item )
	{
		return ( $item['bal_unit'] )? round_to( $item['bal_unit'], 3, 1, 1 ) : '';
	}
	
	public function column_bal_price( $item )
	{
		return ( $item['bal_price'] > 0 )? round_to( $item['bal_price'], 5, 1, 1 ) : '';
	}
	
	public function column_bal_amount( $item )
	{
		return ( $item['bal_amount'] > 0 )? round_to( $item['bal_amount'], 2, 1, 1 ) : '';
	}
	
} //class