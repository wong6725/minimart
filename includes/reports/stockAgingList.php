<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_StockAging_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $period_counter = 6;

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
			"total_qty"		=> [ 'title'=>'Total', 'col'=>2 ],
		];

		$filters = $this->filters;

		$suff = 'mth'; $mul = 1;
		switch( strtoupper( $filters['period_type'] ) )
		{
			case 'QUARTER':
				$suff = 'mths';
				$mul = 3;
			break;
			case 'YEAR':
				$suff = 'year';
			break;
			case 'MONTH':
			default:
				$suff = 'mth';
			break;
		}

		$pc = $this->period_counter;
		for( $i = 1; $i <= $pc; $i++ )
		{
			$cols[ 'qty_'.($i) ] = [ 'title'=>( $i * $mul ).' '.$suff, 'col'=>2 ];
		}

		$cols['above_qty'] = [ 'title'=>'Above', 'col'=>2 ];

		return $cols;
	}

	public function get_columns() 
	{
		$cols = [
			'no'			=> '',
			"prdt_name"		=> "Product",
			"category" 		=> "Category",
			"uom"			=> "UOM",
			"total_qty"		=> "Stk Bal",
			"total_amt"		=> "Stk Val",
		];

		$filters = $this->filters;

		$pc = $this->period_counter;
		for( $i = 1; $i <= $pc; $i++ )
		{
			$cols[ 'qty_'.($i) ] = 'Qty';
			$cols[ 'amt_'.($i) ] = 'Val';
		}

		$cols['above_qty'] = 'Qty';
		$cols['above_amt'] = 'Val';

		return $cols;
	}

	public function get_hidden_column()
	{
		$col = array( 'uqty' );

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'category' => [ 'category_code', true ],
			'prdt_name' => [ 'prdt_code', true ],
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
	?>
		<div class="row">
			<div class="col-md-3 segment">
				<label class="" for="flag">Period Type </label><br>
				<?php
					$options = [ 'MONTH'=>'Monthly', 'QUARTER'=>'Quarterly', 'YEAR'=>'Yearly' ];
					wcwh_form_field( 'filter[period_type]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        'options'=> $options,
	                    ], 
	                    isset( $this->filters['period_type'] )? $this->filters['period_type'] : 'MONTH', $view 
	                ); 
				?>
			</div>
		</div>
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

		$totals = [];
		$pc = $this->period_counter;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{	
				$totals['total_qty']+= ( $data['total_qty'] )? $data['total_qty'] : 0;
				$totals['total_amt']+= ( $data['total_amt'] )? $data['total_amt'] : 0;

				for( $i = 1; $i <= $pc; $i++ )
				{
					$totals[ 'qty_'.($i) ]+= ( $data['qty_'.($i)] )? $data['qty_'.($i)] : 0;
					$totals[ 'amt_'.($i) ]+= ( $data['amt_'.($i)] )? $data['amt_'.($i)] : 0;
				}

				$totals['above_qty']+= ( $data['above_qty'] )? $data['above_qty'] : 0;
				$totals['above_amt']+= ( $data['above_amt'] )? $data['above_amt'] : 0;
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
			if( $column_key == 'total_qty' ) $column_display_name = $this->val_col( $totals['total_qty'] );
			if( $column_key == 'total_amt' ) $column_display_name = $this->val_col( $totals['total_amt'] );

			for( $i = 1; $i <= $pc; $i++ )
			{
				if( $column_key == 'qty_'.($i) ) $column_display_name = $this->val_col( $totals['qty_'.($i)] );
				if( $column_key == 'amt_'.($i) ) $column_display_name = $this->val_col( $totals['amt_'.($i)] );
			}

			if( $column_key == 'above_qty' ) $column_display_name = $this->val_col( $totals['above_qty'] );
			if( $column_key == 'above_amt' ) $column_display_name = $this->val_col( $totals['above_amt'] );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$totals = [];
		$pc = $this->period_counter;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{	
				$totals['total_qty']+= ( $data['total_qty'] )? $data['total_qty'] : 0;
				$totals['total_amt']+= ( $data['total_amt'] )? $data['total_amt'] : 0;

				for( $i = 1; $i <= $pc; $i++ )
				{
					$totals[ 'qty_'.($i) ]+= ( $data['qty_'.($i)] )? $data['qty_'.($i)] : 0;
					$totals[ 'amt_'.($i) ]+= ( $data['amt_'.($i)] )? $data['amt_'.($i)] : 0;
				}

				$totals['above_qty']+= ( $data['above_qty'] )? $data['above_qty'] : 0;
				$totals['above_amt']+= ( $data['above_amt'] )? $data['above_amt'] : 0;
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
			if( $column_key == 'total_qty' ) $column_display_name = $this->val_col( $totals['total_qty'] );
			if( $column_key == 'total_amt' ) $column_display_name = $this->val_col( $totals['total_amt'] );

			for( $i = 1; $i <= $pc; $i++ )
			{
				if( $column_key == 'qty_'.($i) ) $column_display_name = $this->val_col( $totals['qty_'.($i)] );
				if( $column_key == 'amt_'.($i) ) $column_display_name = $this->val_col( $totals['amt_'.($i)] );
			}

			if( $column_key == 'above_qty' ) $column_display_name = $this->val_col( $totals['above_qty'] );
			if( $column_key == 'above_amt' ) $column_display_name = $this->val_col( $totals['above_amt'] );

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

	public function column_total_qty( $item )
	{
		return round_to( $item['total_qty'], 2, 1, 1 );
	}

	public function column_total_amt( $item )
	{
		return round_to( $item['total_amt'], 2, 1, 1 );
	}

	public function column_qty_1( $item )
	{
		return round_to( $item['qty_1'], 2, 1, 1 );
	}
	public function column_amt_1( $item )
	{
		return round_to( $item['amt_1'], 2, 1, 1 );
	}

	public function column_qty_2( $item )
	{
		return round_to( $item['qty_2'], 2, 1, 1 );
	}
	public function column_amt_2( $item )
	{
		return round_to( $item['amt_2'], 2, 1, 1 );
	}
	
	public function column_qty_3( $item )
	{
		return round_to( $item['qty_3'], 2, 1, 1 );
	}
	public function column_amt_3( $item )
	{
		return round_to( $item['amt_3'], 2, 1, 1 );
	}

	public function column_qty_4( $item )
	{
		return round_to( $item['qty_4'], 2, 1, 1 );
	}
	public function column_amt_4( $item )
	{
		return round_to( $item['amt_4'], 2, 1, 1 );
	}

	public function column_qty_5( $item )
	{
		return round_to( $item['qty_5'], 2, 1, 1 );
	}
	public function column_amt_5( $item )
	{
		return round_to( $item['amt_5'], 2, 1, 1 );
	}

	public function column_qty_6( $item )
	{
		return round_to( $item['qty_6'], 2, 1, 1 );
	}
	public function column_amt_6( $item )
	{
		return round_to( $item['amt_6'], 2, 1, 1 );
	}

	public function column_above_qty( $item )
	{
		return round_to( $item['above_qty'], 2, 1, 1 );
	}
	public function column_above_amt( $item )
	{
		return round_to( $item['above_amt'], 2, 1, 1 );
	}
	
		public function val_col( $value = 0, $decimal = 2 )
		{
			$clr = $value >= 0 ? '' : 'clr-red';
			$value = $value != 0 ? round_to( $value, $decimal, 1, 1 ) : '';
			
			return "<span class='{$clr}'>{$value}</span>";
		}

} //class