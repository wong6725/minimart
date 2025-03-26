<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_StockAdjust_Report extends WCWH_Listing 
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
			'docno'			=> 'Doc No.',
			"doc_date" 		=> "Doc Date",
			"post_date" 	=> "Post Date",
			"created_at"	=> "Create Date",
			"remark"		=> "Remark",
			"category"		=> "Category",
			"product"		=> "Product",
			"uom"			=> "UOM",
			"adj_direction"	=> "Direction",
			"in_qty"		=> "In Qty",
			"in_metric"		=> "In Metric (kg/l)",
			"unit_price"	=> "Price",
			"total_price"	=> "Total Price",
			"out_qty"		=> "Out Qty",
			"out_metric"	=> "Out Metric (kg/l)",
			"unit_cost"		=> "Cost Price",
			"total_cost"	=> "Total Cost",
		);
	}

	public function get_hidden_column()
	{
		$col = array( 'remark', 'metric' );

		if( current_user_cans( ['hide_amt_movement_wh_reports'] ) )
		{
			$col[] = 'unit_price';
			$col[] = 'total_price';
			$col[] = 'unit_cost';
			$col[] = 'total_cost';
		}

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'docno' => [ 'docno', true ],
			'doc_date' => [ 'doc_date', true ],
			'post_date' => [ 'post_date', true ],
			'created_at' => [ 'created_at', true ],
			'product' => [ 'prdt_code', true ],
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
				<label class="" for="flag">Document Date By</label><br>
				<?php
					$options = [ 'post_date'=>'Posting Date', 'doc_date'=>'Document Date' ];
					wcwh_form_field( 'filter[date_type]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'class'=>[], 'attrs'=>[],
	                    	'options'=>$options,
	                    ], 
	                   isset( $this->filters['date_type'] )? $this->filters['date_type'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-3 segment">
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
	
	public function print_column_footers( $datas = array(), $all_datas = array() ) 
	{
		if( count( $all_datas ) < $this->args['per_page_row'] ) return;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_inqty = 0;
		$t_inunit = 0;
		$t_intotal = 0;

		$t_outqty = 0;
		$t_outunit = 0;
		$t_outtotal = 0;

		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_inqty+= ( $data['in_qty'] )? $data['in_qty'] : 0;
				$t_inunit+= ( $data['in_metric'] )? $data['in_metric'] : 0;
				$t_intotal+= ( $data['total_price'] )? $data['total_price'] : 0;
				$t_outqty+= ( $data['out_qty'] )? $data['out_qty'] : 0;
				$t_outunit+= ( $data['out_metric'] )? $data['out_metric'] : 0;
				$t_outtotal+= ( $data['total_cost'] )? $data['total_cost'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'docno', 'doc_date' ];
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
			if( $column_key == 'in_qty' ) $column_display_name = round_to( $t_inqty, 2, 1, 1 );
			if( $column_key == 'in_metric' ) $column_display_name = round_to( $t_inunit, 2, 1, 1 );
			if( $column_key == 'total_price' ) $column_display_name = round_to( $t_intotal, 2, 1, 1 );
			if( $column_key == 'out_qty' ) $column_display_name = round_to( $t_outqty, 2, 1, 1 );
			if( $column_key == 'out_metric' ) $column_display_name = round_to( $t_outunit, 2, 1, 1 );
			if( $column_key == 'total_cost' ) $column_display_name = round_to( $t_outtotal, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_inqty = 0;
		$t_inunit = 0;
		$t_intotal = 0;

		$t_outqty = 0;
		$t_outunit = 0;
		$t_outtotal = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_inqty+= ( $data['in_qty'] )? $data['in_qty'] : 0;
				$t_inunit+= ( $data['in_metric'] )? $data['in_metric'] : 0;
				$t_intotal+= ( $data['total_price'] )? $data['total_price'] : 0;
				$t_outqty+= ( $data['out_qty'] )? $data['out_qty'] : 0;
				$t_outunit+= ( $data['out_metric'] )? $data['out_metric'] : 0;
				$t_outtotal+= ( $data['total_cost'] )? $data['total_cost'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'docno', 'doc_date' ];
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
			if( $column_key == 'in_qty' ) $column_display_name = round_to( $t_inqty, 2, 1, 1 );
			if( $column_key == 'in_metric' ) $column_display_name = round_to( $t_inunit, 2, 1, 1 );
			if( $column_key == 'total_price' ) $column_display_name = round_to( $t_intotal, 2, 1, 1 );
			if( $column_key == 'out_qty' ) $column_display_name = round_to( $t_outqty, 2, 1, 1 );
			if( $column_key == 'out_metric' ) $column_display_name = round_to( $t_outunit, 2, 1, 1 );
			if( $column_key == 'total_cost' ) $column_display_name = round_to( $t_outtotal, 2, 1, 1 );

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

	public function column_docno( $item ) 
	{	
		return sprintf( '%1$s', '<strong>'.$item['docno'].'</strong>' );  
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

	public function column_adj_direction( $item )
	{
		return ( $item['adj_direction'] == '+' )? 'In +' : ( ( $item['adj_direction'] == '-' )? 'Out -' : '' );
	}
	
	public function column_in_qty( $item )
	{
		return ( $item['in_qty'] != 0 )? round_to( $item['in_qty'], 2, 1, 1 ) : '';
	}

	public function column_in_metric( $item )
	{
		return ( $item['in_metric'] != 0 )? round_to( $item['in_metric'], 3, 1, 1 ) : '';
	}
	
	public function column_unit_price( $item )
	{
		return ( $item['unit_price'] != 0 )? round_to( $item['unit_price'], 5, 1, 1 ) : '';
	}
	
	public function column_total_price( $item )
	{
		return ( $item['total_price'] != 0 )? round_to( $item['total_price'], 2, 1, 1 ) : '';
	}

	public function column_out_qty( $item )
	{
		return ( $item['out_qty'] != 0 )? round_to( $item['out_qty'], 2, 1, 1 ) : '';
	}

	public function column_out_metric( $item )
	{
		return ( $item['out_metric'] != 0 )? round_to( $item['out_metric'], 3, 1, 1 ) : '';
	}
	
	public function column_unit_cost( $item )
	{
		return ( $item['unit_cost'] != 0 )? round_to( $item['unit_cost'], 5, 1, 1 ) : '';
	}
	
	public function column_total_cost( $item )
	{
		return ( $item['total_cost'] != 0 )? round_to( $item['total_cost'], 2, 1, 1 ) : '';
	}
	
} //class