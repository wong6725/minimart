<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Foodboard_Pricing_Report extends WCWH_Listing 
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
			"item"			=> "Item",
			"category"		=> "Category",
			"uom"			=> "UOM",
			"dc_price"		=> "DC Price",
			"canteen_price"	=> "Canteen Price",
		);

		$filters = $this->filters;

		return $cols;
	}

	public function get_hidden_column()
	{
		$col = array();

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'item' => [ 'item_code', true ],
			'category' => [ 'category_code', true ],
			'dc_price' => [ 'dc_price', true ],
			'canteen_price' => [ 'canteen_price', true ],
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
		$on_date = date( 'Y-m-d', strtotime( $this->filters['on_date'] ) );
		
		$def_date = date( 'm/d/Y', strtotime( $this->filters['on_date'] ) );
	?>
		<div class="row">
			<div class="col-md-4 segment">
				<label class="" for="flag">Price On Date <sup>Current: <?php echo $this->filters['on_date']; ?></sup></label><br>
				<?php
					wcwh_form_field( 'filter[on_date]', 
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_date.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $on_date )? $on_date : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-4 segment">
				<label class="" for="flag">By Client </label><br>
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
					
	                wcwh_form_field( 'filter[client]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['client'] )? $this->filters['client'] : '', $view 
	                ); 
				?>
			</div>
			
			<div class="col-md-4 segment">
				<label class="" for="flag">By Customer </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					if( $Customer ) $filters['id'] = $Customer;
					$options = options_data( apply_filters( 'wcwh_get_customer', $filters, [], false, [] ), 'id', [ 'code', 'uid', 'name' ], '' );
					
	                wcwh_form_field( 'filter[customer]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
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
					$cats = array_keys( $options );
					
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
					if( $cats ) $filters['category'] = $cats;
					$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'code', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
					
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
		$html = [];
		if( $item['category_code'] ) $html[] = $item['category_code'];
		if( $item['category'] ) $html[] = $item['category'];

		return implode( ' - ', $html );
    }

    public function column_item( $item ) 
	{
		$html = [];
		if( $item['item_code'] ) $html[] = $item['item_code'];
		if( $item['item_name'] ) $html[] = $item['item_name'];

		return implode( ' - ', $html );
    }
	
	public function column_dc_price( $item )
	{
		return ( $item['dc_price'] )? round_to( $item['dc_price'], 2, 1, 1 ) : '';
	}
	
	public function column_canteen_price( $item )
	{
		return ( $item['canteen_price'] )? round_to( $item['canteen_price'], 2, 1, 1 ) : '';
	}
	
} //class