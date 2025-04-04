<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_POSSalesDetail_Report extends WCWH_Listing 
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
			"order_no" 		=> "Order No.",
			"date" 			=> "Date",
			"status"		=> "Status",
			"register"		=> "Register",
			"cashier"		=> "Cashier",
			"customer"		=> "Customer",
			"order_total"	=> "Total ({$currency})",
			"item_no"		=> "No.",
			"item_name"		=> "Item Name",
			"category"		=> "Category",
			"uom"			=> "UOM",
			"qty"			=> "Qty",
			"weight"		=> "Metric (kg/l)",
			"price"			=> "Price ({$currency})",
			"line_total"	=> "Line Total ({$currency})",
		);
	}

	public function get_header_cols()
	{
		return array(
			"order_no",
			"date",
			"status",
			"register",
			"cashier",
			"customer",
			"order_total",
		);
	}

	public function get_header_match()
	{
		return 'order_id';
	}

	public function get_hidden_column()
	{
		$col = array();

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'order_no' => [ 'order_no', true ],
			'date' => [ 'date', true ],
			'customer' => [ 'employee_id', true ],
			'order_total' => [ 'order_total', true ],
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
			<div class="col-md-3 segment">
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

			<div class="col-md-3 segment">
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

			<div class="col-md-3 segment">
				<label class="" for="flag">By Order Status </label><br>
				<?php
					$options = [ ''=>'Paid', 'wc-cancelled'=>'Cancelled', 'wc-pending'=>'Pending' ];
                
		            wcwh_form_field( 'filter[order_status]', 
		                [ 'id'=>'order_status', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['order_status'] )? $this->filters['order_status'] : '', $view 
		            ); 
				?>
			</div>
			
			<div class="col-md-3 segment">
				<label class="" for="flag">By Customer </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_customer', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], 'Select', [ 'guest'=>'Guest' ] );
                
		            wcwh_form_field( 'filter[customer]', 
		                [ 'id'=>'customer', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
		            ); 
				?>
			</div>
			
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
		
		$t_qty = 0;
		$t_unit = 0;
		$t_line_total = 0;
		
		$totals = [];
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$totals[ $data['order_id'] ] = $data['order_total'];
				
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['weight'] )? $data['weight'] : 0;
				$t_line_total+= ( $data['line_total'] )? $data['line_total'] : 0;
			}
		}
		
		$t_total = 0;
		if( $totals )
		{
			foreach( $totals as $id => $t )
			{
				$t_total+= $t;
			}
		}

		$colspan = 'no';
		$colnull = [ 'order_no' ];
		foreach ( $columns as $column_key => $column_display_name ) 
		{
			if( $colnull && in_array( $column_key, $colnull ) ) continue;

			$class = array( 'manage-column', "column-$column_key", $column_key );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			$span	= ( $colspan === $column_key )? 'colspan="'.( sizeof( $colnull )+1 ).'"' : '';
			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}
			
			$column_display_name = '';
			if( $column_key == 'no' ) $column_display_name = 'Subtotal:';
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, true );
			if( $column_key == 'weight' ) $column_display_name = round_to( $t_unit, 2, true );
			if( $column_key == 'line_total' ) $column_display_name = round_to( $t_line_total, 2, true );
			
			if( $column_key == 'order_total' ) $column_display_name = round_to( $t_total, 2, true );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_unit = 0;
		$t_line_total = 0;
		
		$totals = [];
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$totals[ $data['order_id'] ] = $data['order_total'];
				
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['weight'] )? $data['weight'] : 0;
				$t_line_total+= ( $data['line_total'] )? $data['line_total'] : 0;
			}
		}
		
		$t_total = 0;
		if( $totals )
		{
			foreach( $totals as $id => $t )
			{
				$t_total+= $t;
			}
		}

		$colspan = 'no';
		$colnull = [ 'order_no' ];
		foreach ( $columns as $column_key => $column_display_name ) 
		{
			if( $colnull && in_array( $column_key, $colnull ) ) continue;

			$class = array( 'manage-column', "column-$column_key", $column_key );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			$span	= ( $colspan === $column_key )? 'colspan="'.( sizeof( $colnull )+1 ).'"' : '';
			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}
			
			$column_display_name = '';
			if( $column_key == 'no' ) $column_display_name = 'TOTAL:';
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'weight' ) $column_display_name = round_to( $t_unit, 2, 1, 1 );
			if( $column_key == 'line_total' ) $column_display_name = round_to( $t_line_total, 2, 1, 1 );
			
			if( $column_key == 'order_total' ) $column_display_name = round_to( $t_total, 2, 1, 1 );

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

	public function column_order_no( $item ) 
	{	
		return sprintf( '%1$s', '<strong>'.$item['order_no'].'</strong>' );  
	}
	
	public function column_status( $item )
	{
		$status = explode( '-', $item['status'] );
		return ucfirst( $status[1] );
	}
	
	public function column_customer( $item )
	{
		$val = [];
		if( !empty( $item['employee_id'] ) ) $val[] = $item['employee_id'];
		if( !empty( $item['receipt_cus_serial'] ) ) $val[] = $item['receipt_cus_serial'];
		//if( !empty( $item['customer_code'] ) ) $val[] = $item['customer_code'];
		if( !empty( $item['customer_name'] ) ) $val[] = $item['customer_name'];
		
		return implode( ', ', $val );
	}

	public function column_item_name( $item )
	{
		$val = [];
		if( !empty( $item['item_name'] ) ) $val[] = $item['item_name'];
		if( !empty( $item['item_code'] ) ) $val[] = $item['item_code'];
		
		return implode( ', ', $val );
	}

	public function column_category( $item )
	{
		return $item['category_code'].' - '.$item['category'];
	}

	public function column_order_total( $item )
	{
		return ( $item['order_total'] )? round_to( $item['order_total'], 2, 1, 1 ) : '';
	}
	
	public function column_qty( $item )
	{
		return ( $item['qty'] )? round_to( $item['qty'], 2, 1, 1 ) : '';
	}
	
	public function column_weight( $item )
	{
		return ( $item['weight'] )? round_to( $item['weight'], 2, 1, 1 ) : '';
	}
	
	public function column_price( $item )
	{
		return ( $item['price'] )? round_to( $item['price'], 2, 1, 1 ) : '';
	}
	
	public function column_line_total( $item )
	{
		return ( $item['line_total'] )? round_to( $item['line_total'], 2, 1, 1 ) : '';
	}
	
} //class