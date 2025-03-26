<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_FoodBoard_Category_Report extends WCWH_Listing 
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
		$cols = array(
			'no'			=> '',
			"customer"		=> "Client / Customer",
			"category"		=> "Category",
			"qty"			=> "Qty",
			"metric"		=> "Metric (kg/l)",
			"amount"		=> "Amount ({$currency})",
		);

		$filters = $this->filters;

		return $cols;
	}

	public function get_hidden_column()
	{
		$col = array( 'metric' );

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'customer' => [ 'customer_code', true ],
			'category' => [ 'category_code', true ],
			'amount' => [ 'amount', true ],
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

			<div class="col-md-6 segment">
				<label class="" for="flag">By FoodBoard Client </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;

					$filter = [ 'status'=>1, 'indication'=>1 ];
					$filter['seller'] = $filters['seller'];
					$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'foodboard_client', 'foodboard_customer' ] ] );
					if( $wh )
					{
						if( is_json( $detail['serial2'] ) ) $detail['serial2'] = json_decode( stripslashes( $detail['serial2'] ), true );
						$Client = is_json( $wh['foodboard_client'] )? json_decode( stripslashes( $wh['foodboard_client'] ), true ) : $wh['foodboard_client'];
						$Customer = is_json( $wh['foodboard_customer'] )? json_decode( stripslashes( $wh['foodboard_customer'] ), true ) : $wh['foodboard_customer'];
					}

					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					if( $Client ) $filters['code'] = $Client;
					$options = options_data( apply_filters( 'wcwh_get_client', $filters, [], false, [] ), 'code', [ 'code', 'name' ], '' );
					
	                wcwh_form_field( 'filter[client][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['client'] )? $this->filters['client'] : '', $view 
	                ); 
				?>
			</div>
			
			<div class="col-md-6 segment">
				<label class="" for="flag">By FoodBoard Customer </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					if( $Customer ) $filters['id'] = $Customer;
					$options = options_data( apply_filters( 'wcwh_get_customer', $filters, [], false, [] ), 'id', [ 'code', 'uid', 'name' ], '' );
					
	                wcwh_form_field( 'filter[customer][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
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

					if( $this->setting['foodboard_report']['categories'] )
					{
						$c = [];
						if( $this->seller )
						{
							$self_cat = apply_filters( 'wcwh_get_item_category', [ 'id'=>$this->setting['foodboard_report']['categories'] ], [], false );
							if( $self_cat )
							{
								$slug = [];
								foreach( $self_cat as $cat )
								{
									$slug[] = $cat['slug'];
								}
								
								$f = [ 'slug'=>$slug, 'seller'=>$this->seller ];
								$outlet_cat = apply_filters( 'wcwh_get_item_category', $f, [], false );
								
								if( $outlet_cat )
								{
									foreach( $outlet_cat as $cat )
									{
										$c[] = $cat['term_id'];
									}
								}
							}
						}
						$filters['ancestor'] = $c;
					}

					$options = options_data( apply_filters( 'wcwh_get_item_category', $filters, [], false, [ 'child'=>1 ] ), 'id', [ 'slug', 'name' ], '' );
					
	                wcwh_form_field( 'filter[category][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
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
		$t_amount = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['metric'] )? $data['metric'] : 0;
				$t_amount+= ( $data['amount'] )? $data['amount'] : 0;
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
			if( $column_key == 'metric' ) $column_display_name = round_to( $t_unit, 2, 1, 1 );
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amount, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_unit = 0;
		$t_amount = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['metric'] )? $data['metric'] : 0;
				$t_amount+= ( $data['amount'] )? $data['amount'] : 0;
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
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'metric' ) $column_display_name = round_to( $t_unit, 2, 1, 1 );
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amount, 2, 1, 1 );

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

    public function column_customer( $item ) 
	{
		$html = [];
		if( $item['customer_code'] ) $html[] = $item['customer_code'];
		if( $item['customer_name'] ) $html[] = $item['customer_name'];

		return implode( ' - ', $html );
    }

    public function column_category( $item ) 
	{
		$html = [];
		if( $item['category_code'] ) $html[] = $item['category_code'];
		if( $item['category_name'] ) $html[] = $item['category_name'];

		return implode( ' - ', $html );
    }
	
	public function column_qty( $item )
	{
		return ( $item['qty'] )? round_to( $item['qty'], 2, 1, 1 ) : '';
	}
	
	public function column_metric( $item )
	{
		return ( $item['metric'] )? round_to( $item['metric'], 2, 1, 1 ) : '';
	}
	
	public function column_amount( $item )
	{
		return ( $item['amount'] )? round_to( $item['amount'], 2, 1, 1 ) : '';
	}
	
} //class