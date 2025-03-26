<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Itemize_Report extends WCWH_Listing 
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
			"product_code"	=> 'Item Code',
			"product_name"	=> "Item Name",
			"expiry"		=> "Expiry",
			"created_at"	=> "Create Date",
			"op_qty" 		=> "Opening Qty",
			"in_qty"		=> "Stock In Qty",
			"out_qty"		=> "Stock Out Qty",
			"balance_qty"	=> "Closing Qty",
			"sale_qty" 		=> "Sale Qty",
			"sale_amt"		=> "Sale Amt",
		);
	}

	public function get_hidden_column()
	{
		$col = [];

		if( current_user_cans( ['hide_amt_itemize_wh_reports'] ) )
		{
			$col[] = 'sale_amt';
		}

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'product_code' => [ 'product_code', true ],
			'expiry' => [ 'expiry', true ],
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

			<div class="segment col-md-4 segment">
				<label class="" for="flag">By Item</label><br>
				<?php
					$filter = [];
					if( $this->seller ) $filter['seller'] = $this->seller;
	                $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'virtual'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
					
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
		
		$t_op = 0;
		$t_in = 0;
		$t_out = 0;
		$t_close = 0;
		$t_sqty = 0;
		$t_samt = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_op+= $data['op_qty'];
				$t_in+= $data['in_qty'];
				$t_out+= $data['out_qty'];
				$t_close+= $data['balance_qty'];
				$t_sqty+= $data['sale_qty'];
				$t_samt+= $data['sale_amt'];
			}
		}

		$colspan = 'no';
		$colnull = [ 'product_code', 'product_name' ];
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
			if( $column_key == 'op_qty' ) $column_display_name = round_to( $t_op, 0, 1, 1 );
			if( $column_key == 'in_qty' ) $column_display_name = round_to( $t_in, 0, 1, 1 );
			if( $column_key == 'out_qty' ) $column_display_name = round_to( $t_out, 0, 1, 1 );
			if( $column_key == 'balance_qty' ) $column_display_name = round_to( $t_close, 0, 1, 1 );
			if( $column_key == 'sale_qty' ) $column_display_name = round_to( $t_sqty, 0, 1, 1 );
			if( $column_key == 'sale_amt' ) $column_display_name = round_to( $t_samt, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_op = 0;
		$t_in = 0;
		$t_out = 0;
		$t_close = 0;
		$t_sqty = 0;
		$t_samt = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_op+= $data['op_qty'];
				$t_in+= $data['in_qty'];
				$t_out+= $data['out_qty'];
				$t_close+= $data['balance_qty'];
				$t_sqty+= $data['sale_qty'];
				$t_samt+= $data['sale_amt'];
			}
		}

		$colspan = 'no';
		$colnull = [ 'product_code', 'product_name' ];
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
			if( $column_key == 'no' ) $column_display_name = 'Subtotal:';
			if( $column_key == 'op_qty' ) $column_display_name = round_to( $t_op, 0, 1, 1 );
			if( $column_key == 'in_qty' ) $column_display_name = round_to( $t_in, 0, 1, 1 );
			if( $column_key == 'out_qty' ) $column_display_name = round_to( $t_out, 0, 1, 1 );
			if( $column_key == 'balance_qty' ) $column_display_name = round_to( $t_close, 0, 1, 1 );
			if( $column_key == 'sale_qty' ) $column_display_name = round_to( $t_sqty, 0, 1, 1 );
			if( $column_key == 'sale_amt' ) $column_display_name = round_to( $t_samt, 2, 1, 1 );

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

	public function column_sale_amt( $item )
	{
		return round_to( $item['sale_amt'], 2, 1, 1 );
	}
	
} //class