<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_StockMovement_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;
	public $show_diff = false;

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

	public function main_columns()
	{
		$cols = [
			'no'			=> [ 'title'=>'', 'col'=>1 ],
			'prdt_name' 	=> [ 'title'=>'', 'col'=>1 ],
			'category'		=> [ 'title'=>'', 'col'=>1 ],
			"uom"			=> [ 'title'=>'', 'col'=>1 ],
			"op_qty"		=> [ 'title'=>'Opening Balance', 'col'=>2 ],
			"df_qty"		=> [ 'title'=>'FIFO Opening', 'col'=>2 ],
			"gr_qty"		=> [ 'title'=>'Goods Receipt (In+)', 'col'=>2 ],
			"other_in_qty"	=> [ 'title'=>'Other Stock (In+)', 'col'=>2 ],
			"sale_qty"		=> [ 'title'=>'Sales (Out-)', 'col'=>4 ],
			"pos_qty"		=> [ 'title'=>'POS Sales (Out-)', 'col'=>5 ],
			"other_out_qty"	=> [ 'title'=>'Other Stock (Out-)', 'col'=>2 ],
			"adjustment"	=> [ 'title'=>'Adjustment (+/-)', 'col'=>2 ],
			"closing_qty"	=> [ 'title'=>'Closing Balance', 'col'=>2 ],
			"profit"		=> [ 'title'=>'Expecting', 'col'=>1 ],
		];

		if( current_user_cans( ['hide_amt_movement_wh_reports'] ) )
		{
			$cols['op_qty']['col'] = 1;
			$cols['gr_qty']['col'] = 1;
			$cols['other_in_qty']['col'] = 1;
			$cols['sale_qty']['col'] = 1;
			$cols['pos_qty']['col'] = 3;
			$cols['other_out_qty']['col'] = 1;
			$cols['adjustment']['col'] = 1;
			$cols['closing_qty']['col'] = 1;
			unset($cols['profit']);
		}

		if( ! $this->show_diff )
		{
			unset($cols['df_qty']);
		}

		$filters = $this->filters;

		return $cols;
	}
	
	public function get_columns() 
	{
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();

		$cols = [
			'no'			=> '',
			"prdt_name"		=> "Product",
			"category" 		=> "Category",
			"uom"			=> "UOM",
			"op_qty"		=> "Qty",
			"op_amt"		=> "Amt",
			"df_qty"		=> "Qty",
			"df_amt"		=> "Amt",
			"gr_qty"		=> "Qty",
			"gr_amt"		=> "Amt",
			"other_in_qty"	=> "Qty",
			"other_in_amt"	=> "Amt",
			"so_qty"		=> "Qty",
			"so_sale"		=> "Sale",
			"so_adj"		=> "Adj",
			"so_amt"		=> "Cost",
			"pos_qty"		=> "SQty<sup data-toggle='tooltip' data-placement='right' data-original-title='Sale Quantity'>&nbsp;?&nbsp;</sup>",
			"pos_sale"		=> "SAmt<sup data-toggle='tooltip' data-placement='right' data-original-title='Sale Amount'>&nbsp;?&nbsp;</sup>",
			"pos_uom_qty"	=> "UOM Qty<sup data-toggle='tooltip' data-placement='right' data-original-title='Quantity by UOM'>&nbsp;?&nbsp;</sup>",
			"pos_mtr"		=> "Metric<sup data-toggle='tooltip' data-placement='right' data-original-title='Metric In kg/l'>&nbsp;?&nbsp;</sup>",
			"pos_amt"		=> "Cost",
			"other_out_qty"	=> "Qty",
			"other_out_amt"	=> "Cost",
			"adj_qty"		=> "Qty",
			"adj_amt"		=> "Amt",
			"closing_qty"	=> "Qty",
			"closing_amt"	=> "Amt",
			"profit"		=> "Profit",
		];

		if( ! $this->show_diff )
		{
			unset($cols['df_qty']); unset($cols['df_amt']); 
		}

		$filters = $this->filters;
		
		return $cols;
	}

	public function get_hidden_column()
	{
		$col = array();

		if( current_user_cans( ['hide_amt_movement_wh_reports'] ) )
		{
			$col[] = 'op_amt';
			$col[] = 'gr_amt';
			$col[] = 'other_in_amt';
			$col[] = 'so_sale';
			$col[] = 'so_adj';
			$col[] = 'so_amt';
			$col[] = 'pos_sale';
			$col[] = 'pos_amt';
			$col[] = 'other_out_amt';
			$col[] = 'adj_amt';
			$col[] = 'closing_amt';
			$col[] = 'profit';
		}

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'category' => [ 'category_code', true ],
			'prdt_name' => [ 'prdt_code', true ],
			'closing_qty' => [ 'closing_qty', true ],
			'closing_amt' => [ 'closing_amt', true ],
			'profit' => [ 'profit', true ],
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
		$month = date( 'Y-m', strtotime( $this->filters['month'] ) );
		
		$def_m = date( 'm/1/Y', strtotime( $this->filters['month'] ) );
	?>
		<div class="row">
			<div class="col-md-3 segment">
				<label class="" for="flag">Month <sup>Current: <?php echo $this->filters['month']; ?></sup></label><br>
				<?php
					wcwh_form_field( 'filter[month]', 
	                    [ 'id'=>'month', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
							'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"', 'data-dd-default-date="'.$def_m.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $month )? $month : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-3 segment">
				<label class="" for="flag">By Weighted Item</label><br>
				<?php
	                $options = [ ''=>'All', '1'=>'Yes', '0'=>'No' ];
	                
	                wcwh_form_field( 'filter[inconsistent_unit]', 
	                    [ 'id'=>'inconsistent_unit', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['inconsistent_unit'] )? $this->filters['inconsistent_unit'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-6 segment">
				<label class="" for="flag">By Item Group </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_group', $filters, [], false, [] ), 'id', [ 'code', 'name' ], '' );
					
	                wcwh_form_field( 'filter[group][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['group'] )? $this->filters['group'] : '', $view 
	                ); 
				?>
			</div>
		</div>
		<br>
		<div class="row">
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

	public function print_column_main()
	{
		$main_cols = $this->main_columns();

		if( $main_cols )
		{
			foreach( $main_cols as $key => $col )
			{
				$tag   = 'th';
				$scope = '';
				$id    = $key ? "id='{$key}'" : '';
				$column_display_name = $col['title'];
				$span = $col['col'] > 1 ? "colspan='{$col['col']}'" : '';

				if ( ! empty( $class ) ) {
					$class = "class='" . join( ' ', $class ) . "'";
				}

				echo "<$tag $scope $id $class $span>$column_display_name</$tag>";
			}
		}
	}
	
	public function print_column_footers( $datas = array(), $all_datas = array() ) 
	{
		if( count( $all_datas ) < $this->args['per_page_row'] ) return;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$op_qty = 0;
		$op_amt = 0;
		$df_qty = 0;
		$df_amt = 0;
		$gr_qty = 0;
		$gr_amt = 0;
		$other_in_qty = 0;
		$other_in_amt = 0;
		$so_qty = 0;
		$so_sale = 0;
		$so_adj = 0;
		$so_amt = 0;
		$pos_qty = 0;
		$pos_uom_qty = 0;
		$pos_mtr = 0;
		$pos_sale = 0;
		$pos_amt = 0;
		$other_out_qty = 0;
		$other_out_amt = 0;
		$adj_qty = 0;
		$adj_amt = 0;
		$closing_qty = 0;
		$closing_amt = 0;
		$profit = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{	
				$op_qty 		+= ( $data['op_qty'] )? $data['op_qty'] : 0;
				$op_amt 		+= ( $data['op_amt'] )? $data['op_amt'] : 0;
				$df_qty 		+= ( $data['df_qty'] )? $data['df_qty'] : 0;
				$df_amt 		+= ( $data['df_amt'] )? $data['df_amt'] : 0;
				$gr_qty 		+= ( $data['gr_qty'] )? $data['gr_qty'] : 0;
				$gr_amt 		+= ( $data['gr_amt'] )? $data['gr_amt'] : 0;
				$other_in_qty 	+= ( $data['other_in_qty'] )? $data['other_in_qty'] : 0;
				$other_in_amt 	+= ( $data['other_in_amt'] )? $data['other_in_amt'] : 0;
				$so_qty 		+= ( $data['so_qty'] )? $data['so_qty'] : 0;
				$so_sale 		+= ( $data['so_sale'] )? $data['so_sale'] : 0;
				$so_adj 		+= ( $data['so_adj'] )? $data['so_adj'] : 0;
				$so_amt 		+= ( $data['so_amt'] )? $data['so_amt'] : 0;
				$pos_qty 		+= ( $data['pos_qty'] )? $data['pos_qty'] : 0;
				$pos_uom_qty 	+= ( $data['pos_uom_qty'] )? $data['pos_uom_qty'] : 0;
				$pos_mtr		+= ( $data['pos_mtr'] )? $data['pos_mtr'] : 0;
				$pos_sale		+= ( $data['pos_sale'] )? $data['pos_sale'] : 0;
				$pos_amt 		+= ( $data['pos_amt'] )? $data['pos_amt'] : 0;
				$other_out_qty 	+= ( $data['other_out_qty'] )? $data['other_out_qty'] : 0;
				$other_out_amt 	+= ( $data['other_out_amt'] )? $data['other_out_amt'] : 0;
				$adj_qty 		+= ( $data['adj_qty'] )? $data['adj_qty'] : 0;
				$adj_amt 		+= ( $data['adj_amt'] )? $data['adj_amt'] : 0;
				$closing_qty 	+= ( $data['closing_qty'] )? $data['closing_qty'] : 0;
				$closing_amt 	+= ( $data['closing_amt'] )? $data['closing_amt'] : 0;
				$profit 		+= ( $data['profit'] )? $data['profit'] : 0;
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
			if( $column_key == 'op_qty' ) $column_display_name = $this->val_col( $op_qty );
			if( $column_key == 'op_amt' ) $column_display_name = $this->val_col( $op_amt );
			if( $column_key == 'df_qty' ) $column_display_name = $this->val_col( $df_qty );
			if( $column_key == 'df_amt' ) $column_display_name = $this->val_col( $df_amt );
			if( $column_key == 'gr_qty' ) $column_display_name = $this->val_col( $gr_qty );
			if( $column_key == 'gr_amt' ) $column_display_name = $this->val_col( $gr_amt );
			if( $column_key == 'other_in_qty' ) $column_display_name = $this->val_col( $other_in_qty );
			if( $column_key == 'other_in_amt' ) $column_display_name = $this->val_col( $other_in_amt );
			if( $column_key == 'so_qty' ) $column_display_name = $this->val_col( $so_qty );
			if( $column_key == 'so_sale' ) $column_display_name = $this->val_col( $so_sale );
			if( $column_key == 'so_adj' ) $column_display_name = $this->val_col( $so_adj );
			if( $column_key == 'so_amt' ) $column_display_name = $this->val_col( $so_amt );
			if( $column_key == 'pos_qty' ) $column_display_name = $this->val_col( $pos_qty );
			if( $column_key == 'pos_uom_qty' ) $column_display_name = $this->val_col( $pos_uom_qty );
			if( $column_key == 'pos_mtr' ) $column_display_name = $this->val_col( $pos_mtr );
			if( $column_key == 'pos_sale' ) $column_display_name = $this->val_col( $pos_sale );
			if( $column_key == 'pos_amt' ) $column_display_name = $this->val_col( $pos_amt );
			if( $column_key == 'other_out_qty' ) $column_display_name = $this->val_col( $other_out_qty );
			if( $column_key == 'other_out_amt' ) $column_display_name = $this->val_col( $other_out_amt );
			if( $column_key == 'adj_qty' ) $column_display_name = $this->val_col( $adj_qty );
			if( $column_key == 'adj_amt' ) $column_display_name = $this->val_col( $adj_amt );
			if( $column_key == 'closing_qty' ) $column_display_name = $this->val_col( $closing_qty );
			if( $column_key == 'closing_amt' ) $column_display_name = $this->val_col( $closing_amt );
			if( $column_key == 'profit' ) $column_display_name = $this->val_col( $profit );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$op_qty = 0;
		$op_amt = 0;
		$df_qty = 0;
		$df_amt = 0;
		$gr_qty = 0;
		$gr_amt = 0;
		$other_in_qty = 0;
		$other_in_amt = 0;
		$so_qty = 0;
		$so_sale = 0;
		$so_adj = 0;
		$so_amt = 0;
		$pos_qty = 0;
		$pos_uom_qty = 0;
		$pos_mtr = 0;
		$pos_sale = 0;
		$pos_amt = 0;
		$other_out_qty = 0;
		$other_out_amt = 0;
		$adj_qty = 0;
		$adj_amt = 0;
		$closing_qty = 0;
		$closing_amt = 0;
		$profit = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{	
				$op_qty 		+= ( $data['op_qty'] )? $data['op_qty'] : 0;
				$op_amt 		+= ( $data['op_amt'] )? $data['op_amt'] : 0;
				$df_qty 		+= ( $data['df_qty'] )? $data['df_qty'] : 0;
				$df_amt 		+= ( $data['df_amt'] )? $data['df_amt'] : 0;
				$gr_qty 		+= ( $data['gr_qty'] )? $data['gr_qty'] : 0;
				$gr_amt 		+= ( $data['gr_amt'] )? $data['gr_amt'] : 0;
				$other_in_qty 	+= ( $data['other_in_qty'] )? $data['other_in_qty'] : 0;
				$other_in_amt 	+= ( $data['other_in_amt'] )? $data['other_in_amt'] : 0;
				$so_qty 		+= ( $data['so_qty'] )? $data['so_qty'] : 0;
				$so_sale 		+= ( $data['so_sale'] )? $data['so_sale'] : 0;
				$so_adj 		+= ( $data['so_adj'] )? $data['so_adj'] : 0;
				$so_amt 		+= ( $data['so_amt'] )? $data['so_amt'] : 0;
				$pos_qty 		+= ( $data['pos_qty'] )? $data['pos_qty'] : 0;
				$pos_uom_qty 	+= ( $data['pos_uom_qty'] )? $data['pos_uom_qty'] : 0;
				$pos_mtr		+= ( $data['pos_mtr'] )? $data['pos_mtr'] : 0;
				$pos_sale		+= ( $data['pos_sale'] )? $data['pos_sale'] : 0;
				$pos_amt 		+= ( $data['pos_amt'] )? $data['pos_amt'] : 0;
				$other_out_qty 	+= ( $data['other_out_qty'] )? $data['other_out_qty'] : 0;
				$other_out_amt 	+= ( $data['other_out_amt'] )? $data['other_out_amt'] : 0;
				$adj_qty 		+= ( $data['adj_qty'] )? $data['adj_qty'] : 0;
				$adj_amt 		+= ( $data['adj_amt'] )? $data['adj_amt'] : 0;
				$closing_qty 	+= ( $data['closing_qty'] )? $data['closing_qty'] : 0;
				$closing_amt 	+= ( $data['closing_amt'] )? $data['closing_amt'] : 0;
				$profit 		+= ( $data['profit'] )? $data['profit'] : 0;
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
			if( $column_key == 'op_qty' ) $column_display_name = $this->val_col( $op_qty );
			if( $column_key == 'op_amt' ) $column_display_name = $this->val_col( $op_amt );
			if( $column_key == 'df_qty' ) $column_display_name = $this->val_col( $df_qty );
			if( $column_key == 'df_amt' ) $column_display_name = $this->val_col( $df_amt );
			if( $column_key == 'gr_qty' ) $column_display_name = $this->val_col( $gr_qty );
			if( $column_key == 'gr_amt' ) $column_display_name = $this->val_col( $gr_amt );
			if( $column_key == 'other_in_qty' ) $column_display_name = $this->val_col( $other_in_qty );
			if( $column_key == 'other_in_amt' ) $column_display_name = $this->val_col( $other_in_amt );
			if( $column_key == 'so_qty' ) $column_display_name = $this->val_col( $so_qty );
			if( $column_key == 'so_sale' ) $column_display_name = $this->val_col( $so_sale );
			if( $column_key == 'so_adj' ) $column_display_name = $this->val_col( $so_adj );
			if( $column_key == 'so_amt' ) $column_display_name = $this->val_col( $so_amt );
			if( $column_key == 'pos_qty' ) $column_display_name = $this->val_col( $pos_qty );
			if( $column_key == 'pos_uom_qty' ) $column_display_name = $this->val_col( $pos_uom_qty );
			if( $column_key == 'pos_mtr' ) $column_display_name = $this->val_col( $pos_mtr );
			if( $column_key == 'pos_sale' ) $column_display_name = $this->val_col( $pos_sale );
			if( $column_key == 'pos_amt' ) $column_display_name = $this->val_col( $pos_amt );
			if( $column_key == 'other_out_qty' ) $column_display_name = $this->val_col( $other_out_qty );
			if( $column_key == 'other_out_amt' ) $column_display_name = $this->val_col( $other_out_amt );
			if( $column_key == 'adj_qty' ) $column_display_name = $this->val_col( $adj_qty );
			if( $column_key == 'adj_amt' ) $column_display_name = $this->val_col( $adj_amt );
			if( $column_key == 'closing_qty' ) $column_display_name = $this->val_col( $closing_qty );
			if( $column_key == 'closing_amt' ) $column_display_name = $this->val_col( $closing_amt );
			if( $column_key == 'profit' ) $column_display_name = $this->val_col( $profit );

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
		$val = [];
		if( !empty( $item['category_code'] ) ) $val[] = $item['category_code'];
		if( !empty( $item['category_name'] ) ) $val[] = $item['category_name'];
		
		return implode( ', ', $val );
	}

	public function column_prdt_name( $item )
	{
		$val = [];
		if( !empty( $item['prdt_code'] ) ) $val[] = $item['prdt_code'];
		if( !empty( $item['prdt_name'] ) ) $val[] = $item['prdt_name'];
		
		return implode( ', ', $val );
	}

	public function column_op_qty( $item )
	{
		return $this->val_col( $item['op_qty'] );
	}
	public function column_op_amt( $item )
	{
		return $this->val_col( $item['op_amt'] );
	}

	public function column_df_qty( $item )
	{
		return $this->val_col( $item['df_qty'] );
	}
	public function column_df_amt( $item )
	{
		return $this->val_col( $item['df_amt'] );
	}

	public function column_gr_qty( $item )
	{
		return $this->val_col( $item['gr_qty'] );
	}
	public function column_gr_amt( $item )
	{
		return $this->val_col( $item['gr_amt'] );
	}

	public function column_other_in_qty( $item )
	{
		return $this->val_col( $item['other_in_qty'] );
	}
	public function column_other_in_amt( $item )
	{
		return $this->val_col( $item['other_in_amt'] );
	}

	public function column_so_qty( $item )
	{
		return $this->val_col( $item['so_qty'] );
	}
	public function column_so_sale( $item )
	{
		return $this->val_col( $item['so_sale'] );
	}
	public function column_so_adj( $item )
	{
		return $this->val_col( $item['so_adj'] );
	}
	public function column_so_amt( $item )
	{
		return $this->val_col( $item['so_amt'] );
	}

	public function column_pos_qty( $item )
	{
		return $this->val_col( $item['pos_qty'] );
	}
	public function column_pos_uom_qty( $item )
	{
		return $this->val_col( $item['pos_uom_qty'] );
	}
	public function column_pos_mtr( $item )
	{
		return $this->val_col( $item['pos_mtr'], 3 );
	}
	public function column_pos_sale( $item )
	{
		return $this->val_col( $item['pos_sale'] );
	}
	public function column_pos_amt( $item )
	{
		return $this->val_col( $item['pos_amt'] );
	}

	public function column_other_out_qty( $item )
	{
		return $this->val_col( $item['other_out_qty'] );
	}
	public function column_other_out_amt( $item )
	{
		return $this->val_col( $item['other_out_amt'] );
	}

	public function column_adj_qty( $item )
	{
		return $this->val_col( $item['adj_qty'] );
	}
	public function column_adj_amt( $item )
	{
		return $this->val_col( $item['adj_amt'] );
	}

	public function column_closing_qty( $item )
	{
		return $this->val_col( $item['closing_qty'] );
	}
	public function column_closing_amt( $item )
	{
		return $this->val_col( $item['closing_amt'] );
	}

	public function column_profit( $item )
	{
		return $this->val_col( $item['profit'] );
	}

		public function val_col( $value = 0, $decimal = 2 )
		{
			$clr = $value >= 0 ? '' : 'clr-red';
			$value = $value != 0 ? round_to( $value, $decimal, 1, 1 ) : '';
			
			return "<span class='{$clr}'>{$value}</span>";
		}
	
} //class