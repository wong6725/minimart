<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_POSItemCost_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

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
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		return array(
			'no'			=> '',
			"category"		=> "Category",
			"category_code"	=> "Cat Code",
			"item_name"		=> "Item Name",
			"item_code"		=> "Item Code",
			"uom"			=> "UOM",
			"pos_qty"			=> "POS Sale Qty",
			//"pos_weight"		=> "Metric (kg/l)",
			"pos_price"		=> "POS Sale Price",
			"pos_amt"		=> "POS Sale Amt",
			"dc_po_price"	=> "DC PO Price",
			"dc_po_amt"		=> "DC PO Amt",
			"dc_sale_price"	=> "DC Sale Price",
			"dc_sale_amt"	=> "DC Sale Amt",
			
		);
	}

	public function get_hidden_column()
	{
		$col = array();

		if( ! current_user_cans( [ 'item_visible_wh_reports' ] ) )
			$col[] = "item_name";

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'category' => [ 'category', true ],
			'category_code' => [ 'category_code', true ],
			'item_name' => [ 'item_name', true ],
			'item_code' => [ 'item_code', true ],
			'qty' => [ 'qty', true ],
			//'weight' => [ 'weight', true ],
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
	
	public function filter_search()
	{
		$from_date = date( 'Y-m-d', strtotime( $this->filters['from_date'] ) );
		$to_date = date( 'Y-m-d', strtotime( $this->filters['to_date'] ) );
		
		$def_from = date( 'm/d/Y', strtotime( $this->filters['from_date'] ) );
		$def_to = date( 'm/d/Y', strtotime( $this->filters['to_date'] ) );
	?>
		<div class="row">
			<div class="col-md-4 segment">
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

			<div class="col-md-4 segment">
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
			
			<!--<div class="col-md-4 segment">
				<label class="" for="flag">By Customer </label><br>
				<?php
					/*$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_customer', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], 'Select', [ 'guest'=>'Guest' ] );
                
		            wcwh_form_field( 'filter[customer]', 
		                [ 'id'=>'customer', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
		            ); */
				?>
			</div>-->
			
			<div class="col-md-6 segment">
				<label class="" for="flag">By Category </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_category', $filters, [], false, [] ), 'id', [ 'slug', 'name' ], '' );
					
	                wcwh_form_field( 'filter[category][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-6 segment">
				<label class="" for="flag">By Item </label><br>
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
	                    isset( $this->filters['product'] )? $this->filters['product'] : '', $view 
	                ); 
				?>
			</div>

		</div>
	<?php
	}
	
	public function print_column_footers( $datas = array(), $all_datas = array() ) 
	{
		if( count( $all_datas ) < $this->args['per_page_row'] ) return;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_unit = 0;
		$t_line_total = 0;
		$t_dc_po = 0;
		$t_dc_sale = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['weight'] )? $data['weight'] : 0;
				$t_line_total+= ( $data['sale_amt'] )? $data['sale_amt'] : 0;
				$t_dc_po+= ( $data['dc_po_amt'] )? $data['dc_po_amt'] : 0;
				$t_dc_sale+= ( $data['dc_sale_amt'] )? $data['dc_sale_amt'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'category' ];
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
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) 
			{
				$class = "class='" . join( ' ', $class ) . "'";
			}
			
			$column_display_name = '';
			if( $column_key == 'no' ) $column_display_name = 'Subtotal:';
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'weight' ) $column_display_name = round_to( $t_unit, 2, 1, 1 );
			if( $column_key == 'sale_amt' ) $column_display_name = round_to( $t_line_total, 2, 1, 1 );
			if( $column_key == 'dc_po_amt' ) $column_display_name = round_to( $t_dc_po, 2, 1, 1 );
			if( $column_key == 'dc_sale_amt' ) $column_display_name = round_to( $t_dc_sale, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_amt = 0;
		$t_unit = 0;
		$t_line_total = 0;
		$t_dc_po = 0;
		$t_dc_sale = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_qty+= ( $data['pos_qty'] )? $data['pos_qty'] : 0;
				$t_unit+= ( $data['weight'] )? $data['weight'] : 0;
				$t_amt+= ( $data['pos_amt'] )? $data['pos_amt'] : 0;
				$t_line_total+= ( $data['sale_amt'] )? $data['sale_amt'] : 0;
				$t_dc_po+= ( $data['dc_po_amt'] )? $data['dc_po_amt'] : 0;
				$t_dc_sale+= ( $data['dc_sale_amt'] )? $data['dc_sale_amt'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'category' ];
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
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) 
			{
				$class = "class='" . join( ' ', $class ) . "'";
			}
			
			$column_display_name = '';
			if( $column_key == 'no' ) $column_display_name = 'TOTAL:';
			if( $column_key == 'pos_qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'weight' ) $column_display_name = round_to( $t_unit, 2, 1, 1 );
			if( $column_key == 'pos_amt' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'sale_amt' ) $column_display_name = round_to( $t_line_total, 2, 1, 1 );
			if( $column_key == 'dc_po_amt' ) $column_display_name = round_to( $t_dc_po, 2, 1, 1 );
			if( $column_key == 'dc_sale_amt' ) $column_display_name = round_to( $t_dc_sale, 2, 1, 1 );

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
	
	public function column_pos_qty( $item )
	{
		return ( $item['pos_qty'] )? round_to( $item['pos_qty'], 2, 1, 1 ) : '';
	}
	
	public function column_pos_weight( $item )
	{
		return ( $item['pos_weight'] )? round_to( $item['pos_weight'], 2, 1, 1 ) : '';
	}
	
	public function column_pos_price( $item )
	{
		return ( $item['pos_price'] )? round_to( $item['pos_price'], 5, 1, 1 ) : '';
	}
	
	public function column_pos_amt( $item )
	{
		return ( $item['pos_amt'] )? round_to( $item['pos_amt'], 2, 1, 1 ) : '';
	}

	public function column_dc_po_price( $item )
	{
		return ( $item['dc_po_price'] )? round_to( $item['dc_po_price'], 5, 1, 1 ) : '';
	}

	public function column_dc_po_amt( $item )
	{
		return ( $item['dc_po_amt'] )? round_to( $item['dc_po_amt'], 2, 1, 1 ) : '';
	}

	public function column_dc_sale_price( $item )
	{
		return ( $item['dc_sale_price'] )? round_to( $item['dc_sale_price'], 5, 1, 1 ) : '';
	}

	public function column_dc_sale_amt( $item )
	{
		return ( $item['dc_sale_amt'] )? round_to( $item['dc_sale_amt'], 2, 1, 1 ) : '';
	}
	
} //class