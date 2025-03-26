<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_MWTDetail_Report extends WCWH_Listing 
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
			"docno"			=> "Doc No.",
			"doc_date"		=> "Doc Date",
			"created_at"	=> "Create Date",
			"client" 		=> "Client",
			"item"			=> "Item",
			"uom"			=> "UOM",
			"qty"			=> "Qty",
			"cost"			=> "Cost Price",
			"total_cost"	=> "Cost Amt",
			"price"			=> "Sell Price",
			"amount"		=> "Sell Amt",
			"profit"		=> "Profit",
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
			'docno' => [ 'docno', true ],
			'doc_date' => [ 'doc_date', true ],
			'client' => [ 'client_code', true ],
			'item' => [ 'item_code', true ],
			'total_cost' => [ 'total_cost', true ],
			'amount' => [ 'amount', true ],
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

			<div class="col-md-4 segment">
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

			<div class="col-md-4 segment">
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
		</div>
	<?php
	}
	
	public function print_column_footers( $datas = array(), $all_datas = array() ) 
	{
		if( count( $all_datas ) < $this->args['per_page_row'] ) return;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_cost = 0;
		$t_amt = 0;
		$tp = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_cost+= ( $data['total_cost'] )? $data['total_cost'] : 0;
				$t_amt+= ( $data['amount'] )? $data['amount'] : 0;
				$tp+= ( $data['profit'] )? $data['profit'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'client', 'item' ];
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
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'total_cost' ) $column_display_name = round_to( $t_cost, 2, 1, 1 );
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'profit' ) $column_display_name = round_to( $tp, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_cost = 0;
		$t_amt = 0;
		$tp = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_cost+= ( $data['total_cost'] )? $data['total_cost'] : 0;
				$t_amt+= ( $data['amount'] )? $data['amount'] : 0;
				$tp+= ( $data['profit'] )? $data['profit'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'client', 'item' ];
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
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'total_cost' ) $column_display_name = round_to( $t_cost, 2, 1, 1 );
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'profit' ) $column_display_name = round_to( $tp, 2, 1, 1 );

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

    public function column_client( $item ) 
	{
		$html = [];
		if( $item['client_code'] ) $html[] = $item['client_code'];
		if( $item['client_name'] ) $html[] = $item['client_name'];

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
		return ( $item['qty'] )? round_to( $item['qty'], 2, 1, 1 ) : '';
	}
	
	public function column_cost( $item )
	{
		return ( $item['cost'] )? round_to( $item['cost'], 2, 1, 1 ) : '';
	}
	
	public function column_total_cost( $item )
	{
		return ( $item['total_cost'] )? round_to( $item['total_cost'], 2, 1, 1 ) : '';
	}

	public function column_price( $item )
	{
		return ( $item['price'] )? round_to( $item['price'], 2, 1, 1 ) : '';
	}
	
	public function column_amount( $item )
	{
		return ( $item['amount'] )? round_to( $item['amount'], 2, 1, 1 ) : '';
	}

	public function column_profit( $item )
	{
		return ( $item['profit'] )? round_to( $item['profit'], 2, 1, 1 ) : '';
	}
	
} //class