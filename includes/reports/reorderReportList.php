<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Reorder_Report extends WCWH_Listing 
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
		return array(
			'no'			=> '',
			"item_code"		=> 'Item Code',
			"item_name"		=> "Item Name",
			"category"		=> "Category",
			"uom_code"		=> "UOM",
			"order_type" 	=> "Order Type",
			"lead_time"		=> "Lead Time (Day)",
			"order_period"	=> "Order Period (Day)",
			"hms_month"		=> "Highest Month",
			"hms_qty" 		=> "Highest Qty",
			"hms_metric"	=> "Highest Metric",
			"stock_bal"		=> "Stock Bal",
			"rov"			=> "Recommend Ord Vol(ROV)",
			"po_qty"		=> "PO Pending Qty",
			"final_rov"		=> "Final ROV",
		);
	}

	public function get_hidden_column()
	{
		$col = [];

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'item_code' => [ 'item_code', true ],
			'item_name' => [ 'item_name', true ],
			'category'	=> [ 'category_code', true ],
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
			<div class="col col-md-4 segment">
				<label class="" for="flag">Sales Period (Month)</sup></label><br>
				<?php
					wcwh_form_field( 'filter[sales_period]', 
	                    [ 'id'=>'from_date', 'type'=>'number', 'label'=>'', 'required'=>false, 'class'=>[],
							'attrs'=>[ 'min="1"', 'max="12"' ], 
	                    ], 
	                    isset( $this->filters['sales_period'] )? $this->filters['sales_period'] : 6, $view 
	                );
				?>
			</div>

			<div class="col col-md-4 segment">
				<label class="" for="flag">By Stock Sufficient </label><br>
				<?php
					$options = [ ''=>'All', 'insufficient'=>'Insufficient', 'sufficient'=>'Sufficient' ];
					
	                wcwh_form_field( 'filter[sufficient]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options,
	                    ], 
	                    isset( $this->filters['sufficient'] )? $this->filters['sufficient'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col col-md-6 segment">
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

			<div class="col col-md-6 segment">
				<label class="" for="flag">By Item </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
					
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
		
		$t_bal = 0;
		$t_rov = 0;
		$t_po = 0;
		$t_frov = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_bal+= $data['stock_bal'];
				$t_rov+= $data['rov'];
				$t_po+= $data['po_qty'];
				$t_frov+= $data['final_rov'];
			}
		}

		$colspan = 'no';
		$colnull = [ 'item_code', 'item_name' ];
		foreach ( $columns as $column_key => $column_display_name ) 
		{
			if( $colnull && in_array( $column_key, $colnull ) ) continue;

			$class = array( 'manage-column', "column-$column_key", $column_key );

			if ( in_array( $column_key, $hidden ) )
				$class[] = 'hidden';

			$span	= ( $colspan === $column_key )? 'colspan="'.( sizeof( $colnull )+1 ).'"' : '';
			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) )
				$class = "class='" . join( ' ', $class ) . "'";
			
			$column_display_name = '';
			if( $column_key == 'no' ) $column_display_name = 'Subtotal:';
			if( $column_key == 'stock_bal' ) $column_display_name = round_to( $t_bal, 0, 1, 1 );
			if( $column_key == 'rov' ) $column_display_name = round_to( $t_rov, 0, 1, 1 );
			if( $column_key == 'po_qty' ) $column_display_name = round_to( $t_po, 0, 1, 1 );
			if( $column_key == 'final_rov' ) $column_display_name = round_to( $t_frov, 0, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_bal = 0;
		$t_rov = 0;
		$t_po = 0;
		$t_frov = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_bal+= $data['stock_bal'];
				$t_rov+= $data['rov'];
				$t_po+= $data['po_qty'];
				$t_frov+= $data['final_rov'];
			}
		}

		$colspan = 'no';
		$colnull = [ 'item_code', 'item_name' ];
		foreach ( $columns as $column_key => $column_display_name ) 
		{
			if( $colnull && in_array( $column_key, $colnull ) ) continue;

			$class = array( 'manage-column', "column-$column_key", $column_key );

			if ( in_array( $column_key, $hidden ) )
				$class[] = 'hidden';

			$span	= ( $colspan === $column_key )? 'colspan="'.( sizeof( $colnull )+1 ).'"' : '';
			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) )
				$class = "class='" . join( ' ', $class ) . "'";
			
			$column_display_name = '';
			if( $column_key == 'no' ) $column_display_name = 'TOTAL:';
			if( $column_key == 'stock_bal' ) $column_display_name = round_to( $t_bal, 0, 1, 1 );
			if( $column_key == 'rov' ) $column_display_name = round_to( $t_rov, 0, 1, 1 );
			if( $column_key == 'po_qty' ) $column_display_name = round_to( $t_po, 0, 1, 1 );
			if( $column_key == 'final_rov' ) $column_display_name = round_to( $t_frov, 0, 1, 1 );

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
		return $item['category_code'].' - '.$item['category_name'];
	}

	public function column_hms_qty( $item )
	{
		return ( $item['hms_qty'] )? round_to( $item['hms_qty'], 2, 1, 1 ) : '';
	}

	public function column_stock_bal( $item )
	{
		return ( $item['stock_bal'] )? round_to( $item['stock_bal'], 2, 1, 1 ) : '';
	}

	public function column_rov( $item )
	{
		return ( $item['rov'] )? round_to( $item['rov'], 2, 1, 1 ) : '';
	}

	public function column_po_qty( $item )
	{
		return ( $item['po_qty'] )? round_to( $item['po_qty'], 2, 1, 1 ) : '';
	}

	public function column_final_rov( $item )
	{
		return ( $item['final_rov'] )? round_to( $item['final_rov'], 2, 1, 1 ) : '';
	}
	
} //class