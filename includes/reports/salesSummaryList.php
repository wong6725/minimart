<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_SO_Summary_report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;
	public $doc_opts = [];

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
			"docno"			=> "SO No",
			"doc_date"		=> "SO Date",
			"created_at"	=> "Created Date",
			"client" 		=> "Client",
			"item_no"		=> "Item #",
			"item"			=> "Item",
			"category"		=> "Category",
			"qty"			=> "Qty",
			"uom"			=> "UOM",
			"foc"			=> "Foc",
			"def_price"		=> "Unit Price",
			"line_subtotal"	=> "Line Subtotal",
			"line_discount"	=> "Line Discount",
			"order_discount"=> "Order Discount",
			"final_sprice"		=> "Selling Price",
			"line_final_total"	=> "Line Total",
			"order_total"	=> "Order Total",
			"adj_total_sale"=> "Adj Sale",
		);
	}

	public function get_header_cols()
	{
		return array(
			"docno",
			"doc_date",
			"created_at",
			"client",
			"order_discount",
			"order_total",
			"adj_total_sale",
		);
	}

	public function get_header_match()
	{
		return 'docno';
	}

	public function get_hidden_column()
	{
		$col = array();

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'client' => [ 'client_code', true ],
			'docno' => [ 'docno', true ],
			'doc_date' => [ 'doc_date', true ],
			'created_at' => [ 'created_at', true ],
		];

		return $cols;
	}

	public function get_bulk_actions() 
	{
		$actions = [];
		
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
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
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
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $to_date )? $to_date : '', $view 
	                ); 
				?>
			</div>
			
			<div class="col-md-3 segment">
				<label class="" for="flag">By Status </label><br>
				<?php
					$options = [ 'all'=>'All', 'posted'=>'Post / Complete', '1'=>'Ready', '6'=>'Posted', '9'=>'Completed' ];
                
		            wcwh_form_field( 'filter[doc_stat]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['doc_stat'] )? $this->filters['doc_stat'] : 'all', $view 
		            ); 
				?>
			</div>

			<div class="col-md-3 segment">
				<label class="" for="flag">By Client </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_client', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'name', 'status_name' ], '' );
                
		            wcwh_form_field( 'filter[client][]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['client'] )? $this->filters['client'] : '', $view 
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

			<div class="col-md-6 segment">
				<label class="" for="flag">By Doc No. </label><br>
				<?php
					$options = $this->doc_opts;

	                wcwh_form_field( 'filter[doc_id][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    isset( $this->filters['doc_id'] )? $this->filters['doc_id'] : '', $view 
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
		$t_foc = 0;
		$t_amt = 0;

		$totals = []; $adj_totals = [];
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$totals[ $data['docno'] ] = $data['order_total'];
				$adj_totals[ $data['docno'] ] = $data['adj_total_sale'];

				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_foc+= ( $data['foc'] )? $data['foc'] : 0;
				$t_amt+= ( $data['line_final_total'] )? $data['line_final_total'] : 0;
			}
		}
		
		$t_total = ( $totals )? array_sum( $totals ) : 0;
		$t_adj_total = ( $adj_totals )? array_sum( $adj_totals ) : 0;

		$colspan = 'no';
		$colnull = [ 'client', 'docno', 'doc_date', 'created_at' ];
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
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'foc' ) $column_display_name = round_to( $t_foc, 2, 1, 1 );
			if( $column_key == 'line_final_total' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'order_total' ) $column_display_name = round_to( $t_total, 2, 1, 1 );
			if( $column_key == 'adj_total_sale' ) $column_display_name = round_to( $t_adj_total, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_foc = 0;
		$t_amt = 0;
		
		$totals = []; $adj_totals = [];
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$totals[ $data['docno'] ] = $data['order_total'];
				$adj_totals[ $data['docno'] ] = $data['adj_total_sale'];

				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_foc+= ( $data['foc'] )? $data['foc'] : 0;
				$t_amt+= ( $data['line_final_total'] )? $data['line_final_total'] : 0;
			}
		}

		$t_total = ( $totals )? array_sum( $totals ) : 0;
		$t_adj_total = ( $adj_totals )? array_sum( $adj_totals ) : 0;

		$colspan = 'no';
		$colnull = [ 'client', 'docno', 'doc_date', 'created_at' ];
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
			if( $column_key == 'foc' ) $column_display_name = round_to( $t_foc, 2, 1, 1 );
			if( $column_key == 'line_final_total' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'order_total' ) $column_display_name = round_to( $t_total, 2, 1, 1 );
			if( $column_key == 'adj_total_sale' ) $column_display_name = round_to( $t_adj_total, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		$h_match = $this->get_header_match();
		if( $item[ $h_match ] != $this->prev_match )
			$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['doc_id'], $item['id'] );
		
		return $html;
    }

	public function column_no( $item ) 
	{
		return $item['no'];
    }

    public function column_client( $item ) 
	{
		$html = [];
		if( $item['client_code'] ) $html[] = $item['client_code'];
		if( $item['client_name'] ) $html[] = $item['client_name'];

		return implode( ' - ', $html );
    }

    public function column_category( $item ) 
	{
		$html = [];
		if( $item['category_code'] ) $html[] = $item['category_code'];
		if( $item['category_name'] ) $html[] = $item['category_name'];

		return implode( ' - ', $html );
    }

    public function column_item( $item ) 
	{
		$html = [];
		if( $item['item_code'] ) $html[] = $item['item_code'];
		if( $item['item_name'] ) $html[] = $item['item_name'];

		return implode( ' - ', $html );
    }
	
	public function column_qty( $item )
	{
		return ( $item['qty'] != 0 )? round_to( $item['qty'], 2, 1, 1 ) : '';
	}

	public function column_foc( $item )
	{
		return ( $item['foc'] != 0 )? round_to( $item['foc'], 2, 1, 1 ) : '';
	}
	
	public function column_def_price( $item )
	{
		return ( $item['def_price'] != 0 )? round_to( $item['def_price'], 2, 1, 1 ) : '';
	}

	public function column_final_sprice( $item )
	{
		return ( $item['final_sprice'] != 0 )? round_to( $item['final_sprice'], 2, 1, 1 ) : '';
	}

	public function column_line_final_total( $item )
	{
		return ( $item['line_final_total'] != 0 )? round_to( $item['line_final_total'], 2, 1, 1 ) : '';
	}

	public function column_order_total( $item )
	{
		return ( $item['order_total'] != 0 )? round_to( $item['order_total'], 2, 1, 1 ) : '';
	}
	
} //class